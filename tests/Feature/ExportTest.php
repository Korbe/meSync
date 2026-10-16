<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15');
    }

    private function invoice(User $user, string $number, string $date, float $net, float $taxRate = 20): Invoice
    {
        $customer = Customer::factory()->for($user)->create(['title_prefix' => null, 'title_suffix' => null, 'first_name' => 'Max', 'last_name' => 'Muster', 'company_name' => null, 'company_suffix' => null]);
        $invoice = $user->invoices()->create([
            'customer_id' => $customer->id, 'number' => $number, 'invoice_date' => $date, 'due_date' => $date,
            'tax_rate' => $taxRate, 'paid_amount' => 0, 'recipient' => $customer->toRecipient(), 'issuer' => [],
        ]);
        $invoice->items()->create(['position' => 1, 'description' => 'Leistung', 'quantity' => 1, 'unit' => 'Stück', 'unit_price' => $net]);

        return $invoice->fresh();
    }

    public function test_preview_shows_invoices_and_vat_summary_of_the_period(): void
    {
        $user = User::factory()->create();
        $this->invoice($user, '2026-010', '2026-10-05', 100);
        $this->invoice($user, '2026-011', '2026-10-20', 50, 10);
        $this->invoice($user, '2026-009', '2026-09-30', 999);   // outside the period
        $this->invoice(User::factory()->create(), '2026-010', '2026-10-05', 777);  // other user

        $this->actingAs($user)->get(route('export.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Export/Index')
                ->has('invoices', 2)
                ->has('summary', 2)
                ->where('summary.0.rate', 20)
                ->where('summary.0.net', 100)
                ->where('summary.0.tax', 20)
                ->where('summary.1.rate', 10));
    }

    public function test_invoices_csv_is_excel_friendly(): void
    {
        $user = User::factory()->create();
        $this->invoice($user, '2026-010', '2026-10-05', 1234.5);

        $response = $this->actingAs($user)->get(route('export.csv', ['from' => '2026-10-01', 'to' => '2026-10-31']));
        $response->assertOk();

        $csv = $response->streamedContent();
        $lines = explode("\n", trim($csv));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);                     // UTF-8 BOM for Excel
        $this->assertStringContainsString('Rechnungsdatum;Rechnungsnummer', $lines[0]);
        $this->assertStringContainsString('05.10.2026;2026-010;Rechnung;;"Max Muster"', $lines[1]);
        $this->assertStringContainsString(';1234,50;20,00;246,90;1481,40;', $lines[1]); // decimal comma
        $this->assertCount(2, $lines);
    }

    public function test_zip_contains_csv_files_and_pdfs(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user, '2026-010', '2026-10-05', 100);
        $invoice->payments()->create(['paid_at' => '2026-10-10', 'amount' => 120, 'note' => 'Überweisung']);

        $response = $this->actingAs($user)->get(route('export.zip', ['from' => '2026-10-01', 'to' => '2026-10-31']));
        $response->assertOk();

        $zip = new ZipArchive();
        $zip->open($response->getFile()->getPathname());

        $this->assertNotFalse($zip->locateName('rechnungen.csv'));
        $this->assertNotFalse($zip->locateName('ust-uebersicht.csv'));
        $this->assertNotFalse($zip->locateName('Rechnungen/2026-010-Max Muster.pdf'));
        $this->assertStringStartsWith('%PDF', $zip->getFromName('Rechnungen/2026-010-Max Muster.pdf'));

        // Payment split into net + VAT
        $payments = $zip->getFromName('zahlungen.csv');
        $this->assertStringContainsString('10.10.2026;2026-010;"Max Muster";120,00;100,00;20,00;20,00;Überweisung', $payments);

        $zip->close();
    }

    public function test_zip_works_with_cancellation_invoices(): void
    {
        $user = User::factory()->create();
        $original = $this->invoice($user, '2026-010', '2026-10-05', 100);
        $cancellation = $original->load('items')->cancel();

        $response = $this->actingAs($user)->get(route('export.zip', ['from' => '2026-10-01', 'to' => '2026-10-31']));
        $response->assertOk();

        $zip = new ZipArchive();
        $zip->open($response->getFile()->getPathname());

        // Both PDFs, the cancellation one refers to the original with its date
        $this->assertNotFalse($zip->locateName('Rechnungen/2026-010-Max Muster.pdf'));
        $this->assertStringStartsWith('%PDF', $zip->getFromName("Rechnungen/{$cancellation->number}-Max Muster.pdf"));

        $zip->close();
    }

    public function test_period_must_be_valid(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('export.index', ['from' => '2026-10-31', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
    }
}
