<?php

namespace App\Services;

use App\Models\MaintenanceContract;
use App\Support\PdfPageNumbers;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Information sheet for a maintenance contract (who, what, how long, how much).
 * Rendered in memory with the current data, nothing is stored.
 */
class ContractPdf
{
    public function render(MaintenanceContract $contract): string
    {
        $contract->loadMissing(['customer', 'invoices', 'user.businessProfile']);

        $profile = $contract->user->businessProfile;
        $issuer = $profile?->toIssuer() ?? ['name' => $contract->user->name, 'email' => $contract->user->email];
        $taxRate = (float) ($profile->default_tax_rate ?? 20);
        $annual = $contract->annual_price;

        $logo = $contract->user->getFirstMedia('logo');

        $pdf = Pdf::loadView('pdf.contract', [
            'contract' => $contract,
            'issuer' => $issuer,
            'recipient' => $contract->customer->toRecipient(),
            'logo' => $logo ? 'data:'.$logo->mime_type.';base64,'.base64_encode(file_get_contents($logo->getPath())) : null,
            'services' => $contract->type->services(),
            // Latest billed period, if any
            'period' => $contract->invoices->first(),
            'prices' => [
                'monthly' => (float) $contract->monthly_price,
                'annual' => $annual,
                'tax_rate' => $taxRate,
                'tax' => round($annual * $taxRate / 100, 2),
                'annual_gross' => round($annual * (1 + $taxRate / 100), 2),
            ],
        ])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();
        PdfPageNumbers::draw($pdf->getDomPDF(), "Seite {PAGE_NUM} von {PAGE_COUNT} · Wartungsvertrag {$contract->name}");

        return $pdf->output();
    }

    public function download(MaintenanceContract $contract): Response
    {
        $name = $this->fileName($contract);

        return response($this->render($contract), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $name, Str::ascii($name)),
        ]);
    }

    public function fileName(MaintenanceContract $contract): string
    {
        $name = preg_replace('/[^\pL\pN \-_.&]+/u', '', $contract->name);

        return trim('Wartungsvertrag-'.$name, '-').'.pdf';
    }
}
