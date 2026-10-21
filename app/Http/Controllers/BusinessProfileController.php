<?php

namespace App\Http\Controllers;

use App\Support\InvoiceColors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessProfileController extends Controller
{
    /**
     * Save the sender and bank details printed on invoices.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'iban' => $request->filled('iban') ? strtoupper(preg_replace('/\s+/', '', $request->input('iban'))) : null,
            'bic' => $request->filled('bic') ? strtoupper(preg_replace('/\s+/', '', $request->input('bic'))) : null,
            'uid_number' => $request->filled('uid_number') ? strtoupper(preg_replace('/\s+/', '', $request->input('uid_number'))) : null,
        ]);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'uid_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z]{2}[0-9A-Z]{2,13}$/'],
            'bank_name' => 'nullable|string|max:255',
            'account_holder' => 'nullable|string|max:255',
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/'],
            'bic' => ['nullable', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
            'payment_terms_days' => 'required|integer|min:0|max:365',
            'default_tax_rate' => 'required|numeric|min:0|max:100',
            'invoice_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'invoice_color.regex' => 'Bitte wähle eine gültige Farbe, z. B. #1fadae.',
            'uid_number.regex' => 'Die UID-Nummer muss das Format ATU12345678 haben.',
            'iban.regex' => 'Bitte gib eine gültige IBAN an, z. B. AT61 1904 3002 3457 3201.',
            'bic.regex' => 'Bitte gib einen gültigen BIC an, z. B. BKAUATWW.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
        ]);

        $validated['invoice_color'] = strtolower($validated['invoice_color'] ?? InvoiceColors::DEFAULT);

        $request->user()->businessProfile()->updateOrCreate([], $validated);

        return back()->with('success', 'Rechnungsdaten wurden gespeichert.');
    }
}
