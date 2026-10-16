<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ZipArchive;

/**
 * Builds the accountant export (CSV files + invoice PDFs) for a period.
 * Everything is generated in memory or in a temp file that is deleted after sending.
 */
class AccountingExport
{
    private const STATUS_LABELS = [
        'paid' => 'Bezahlt',
        'partial' => 'Teilweise bezahlt',
        'open' => 'Offen',
        'overdue' => 'Überfällig',
        'cancelled' => 'Storniert',
    ];

    public function __construct(private InvoicePdf $pdf)
    {
    }

    /**
     * Invoices dated within the period (cancellation invoices included, they are negative).
     *
     * @return Collection<int, Invoice>
     */
    public function invoices(User $user, Carbon $from, Carbon $to): Collection
    {
        return $user->invoices()
            ->with(['items', 'payments', 'cancels:id,number', 'cancellation:id,number,cancels_invoice_id'])
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('invoice_date')
            ->orderBy('number')
            ->get();
    }

    /**
     * Payments received within the period (relevant for cash-basis VAT, "Ist-Versteuerung").
     *
     * @return Collection<int, InvoicePayment>
     */
    public function payments(User $user, Carbon $from, Carbon $to): Collection
    {
        return InvoicePayment::query()
            ->with('invoice.items')
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
            ->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
            ->orderBy('paid_at')
            ->get();
    }

    /**
     * VAT summary per tax rate.
     *
     * @return Collection<int, array{rate: float, count: int, net: float, tax: float, gross: float}>
     */
    public function taxSummary(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (Invoice $invoice) => (string) (float) $invoice->tax_rate)
            ->map(fn (Collection $group, string $rate) => [
                'rate' => (float) $rate,
                'count' => $group->count(),
                'net' => round($group->sum('subtotal'), 2),
                'tax' => round($group->sum('tax_amount'), 2),
                'gross' => round($group->sum('total'), 2),
            ])
            ->sortByDesc('rate')
            ->values();
    }

    public function invoicesCsv(Collection $invoices): string
    {
        $rows = $invoices->map(fn (Invoice $invoice) => [
            $invoice->invoice_date->format('d.m.Y'),
            $invoice->number,
            $invoice->cancels ? 'Stornorechnung' : 'Rechnung',
            $invoice->cancels ? "Storno zu {$invoice->cancels->number}" : ($invoice->cancellation ? "Storniert durch {$invoice->cancellation->number}" : ''),
            $invoice->recipient['name'] ?? '',
            $invoice->recipient['uid_number'] ?? '',
            $this->money($invoice->subtotal),
            $this->money($invoice->tax_rate),
            $this->money($invoice->tax_amount),
            $this->money($invoice->total),
            $this->money($invoice->paid_amount),
            $this->money($invoice->received),
            $this->money($invoice->outstanding),
            $invoice->due_date->format('d.m.Y'),
            optional($invoice->payments->last())->paid_at?->format('d.m.Y') ?? '',
            self::STATUS_LABELS[$invoice->payment_status] ?? $invoice->payment_status,
        ]);

        return $this->csv([
            'Rechnungsdatum', 'Rechnungsnummer', 'Art', 'Bezug', 'Kunde', 'UID Kunde',
            'Netto', 'USt-Satz %', 'USt', 'Brutto', 'Anzahlung', 'Eingegangen', 'Offen',
            'Fällig am', 'Zuletzt bezahlt am', 'Status',
        ], $rows);
    }

    public function paymentsCsv(Collection $payments): string
    {
        $rows = $payments->map(function (InvoicePayment $payment) {
            $invoice = $payment->invoice;

            // Split the gross payment into net and VAT using the invoice's tax rate
            $net = round((float) $payment->amount / (1 + (float) $invoice->tax_rate / 100), 2);

            return [
                $payment->paid_at->format('d.m.Y'),
                $invoice->number,
                $invoice->recipient['name'] ?? '',
                $this->money($payment->amount),
                $this->money($net),
                $this->money($invoice->tax_rate),
                $this->money((float) $payment->amount - $net),
                $payment->method->label(),
                $payment->note ?? '',
            ];
        });

        return $this->csv(['Zahlungsdatum', 'Rechnungsnummer', 'Kunde', 'Betrag brutto', 'davon Netto', 'USt-Satz %', 'davon USt', 'Zahlungsart', 'Notiz'], $rows);
    }

    public function taxSummaryCsv(Collection $summary, Carbon $from, Carbon $to): string
    {
        $rows = $summary->map(fn (array $line) => [
            $this->money($line['rate']),
            $line['count'],
            $this->money($line['net']),
            $this->money($line['tax']),
            $this->money($line['gross']),
        ])->push([
            'Gesamt',
            $summary->sum('count'),
            $this->money($summary->sum('net')),
            $this->money($summary->sum('tax')),
            $this->money($summary->sum('gross')),
        ]);

        return $this->csv(["USt-Satz % ({$from->format('d.m.Y')} – {$to->format('d.m.Y')})", 'Anzahl Rechnungen', 'Netto', 'USt', 'Brutto'], $rows);
    }

    /**
     * ZIP with all CSV files and the invoice PDFs. Returns the path of a temp file
     * the caller sends and deletes.
     */
    public function zip(User $user, Carbon $from, Carbon $to): string
    {
        $invoices = $this->invoices($user, $from, $to);
        $path = tempnam(sys_get_temp_dir(), 'mesync-export-');

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);

        $zip->addFromString('rechnungen.csv', $this->invoicesCsv($invoices));
        $zip->addFromString('zahlungen.csv', $this->paymentsCsv($this->payments($user, $from, $to)));
        $zip->addFromString('ust-uebersicht.csv', $this->taxSummaryCsv($this->taxSummary($invoices), $from, $to));

        foreach ($invoices as $invoice) {
            $zip->addFromString('Rechnungen/'.$invoice->fileName(), $this->pdf->render($invoice));
        }

        $zip->close();

        return $path;
    }

    public function fileSuffix(Carbon $from, Carbon $to): string
    {
        return $from->format('Y-m-d').'_bis_'.$to->format('Y-m-d');
    }

    /**
     * Excel-friendly CSV: UTF-8 with BOM, semicolons, decimal comma.
     *
     * @param  array<int, string>  $header
     */
    private function csv(array $header, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $header, ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function money(float|string|null $value): string
    {
        return number_format((float) $value, 2, ',', '');
    }
}
