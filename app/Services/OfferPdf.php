<?php

namespace App\Services;

use App\Models\Offer;
use App\Support\PdfPageNumbers;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the offer PDF in memory, in the look of the invoices. Nothing is written to disk.
 */
class OfferPdf
{
    public function render(Offer $offer): string
    {
        $offer->loadMissing(['items', 'user.businessProfile']);

        $issuer = $offer->issuer;
        $issuer['invoice_color'] ??= $offer->user->businessProfile?->invoice_color;

        $logo = $offer->user->getFirstMedia('logo');
        $signature = $offer->user->getFirstMedia('signature');
        $image = fn ($media) => $media && is_file($media->getPath())
            ? 'data:'.$media->mime_type.';base64,'.base64_encode(file_get_contents($media->getPath()))
            : null;

        $pdf = Pdf::loadView('pdf.offer', [
            'offer' => $offer,
            'issuer' => $issuer,
            'recipient' => $offer->recipient,
            'logo' => $image($logo),
            // Own handwritten signature from the profile (private), printed on the line
            'signature' => $image($signature),
            'types' => config('offers.types'),
            'packages' => collect(config('offers.packages'))->where('type', $offer->type->value)->all(),
            'features' => config('offers.features'),
            'integrations' => config('offers.integrations'),
            'contentDelivery' => config('offers.content_delivery'),
            'paymentTerms' => config('offers.payment_terms'),
            'hints' => config('offers.hints'),
            // Uploaded in the profile: goes along with the offer mail
            'termsAttached' => $offer->user->getFirstMedia('terms') !== null,
            'services' => $offer->type->services(),
        ])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();
        PdfPageNumbers::draw($pdf->getDomPDF(), "Seite {PAGE_NUM} von {PAGE_COUNT} von Angebot {$offer->number}");

        return $pdf->output();
    }

    public function download(Offer $offer): Response
    {
        $name = $offer->fileName();

        return response($this->render($offer), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $name, Str::ascii($name)),
        ]);
    }
}
