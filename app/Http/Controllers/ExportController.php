<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\AccountingExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(private AccountingExport $export)
    {
    }

    /**
     * Period selection with a preview of what will be exported.
     */
    public function index(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        $invoices = $this->export->invoices($request->user(), $from, $to);
        $payments = $this->export->payments($request->user(), $from, $to);

        return Inertia::render('Export/Index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => $this->export->taxSummary($invoices),
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'recipient' => $invoice->recipient['name'] ?? '',
                'subtotal' => $invoice->subtotal,
                'total' => $invoice->total,
                'payment_status' => $invoice->payment_status,
            ]),
            'paymentsTotal' => round($payments->sum('amount'), 2),
            'paymentsCount' => $payments->count(),
        ]);
    }

    public function csv(Request $request)
    {
        [$from, $to] = $this->period($request);

        $csv = $this->export->invoicesCsv($this->export->invoices($request->user(), $from, $to));

        return response()->streamDownload(fn () => print($csv), 'rechnungen_'.$this->export->fileSuffix($from, $to).'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * CSV files + invoice PDFs as ZIP. The temp file is deleted right after sending.
     */
    public function zip(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);

        $path = $this->export->zip($request->user(), $from, $to);

        return response()
            ->download($path, 'buchhaltung_'.$this->export->fileSuffix($from, $to).'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        // Default: current month
        return [
            Carbon::parse($validated['from'] ?? today()->startOfMonth())->startOfDay(),
            Carbon::parse($validated['to'] ?? today()->endOfMonth())->startOfDay(),
        ];
    }
}
