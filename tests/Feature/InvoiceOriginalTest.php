<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class InvoiceOriginalTest extends TestCase
{
    use RefreshDatabase;

    // Minimal real PDF with a marker, so the served file can be recognised
    private const PDF = "%PDF-1.4\n% ORIGINAL-RECHNUNG-2026-013\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function invoice(User $user): Invoice
    {
        $customer = Customer::factory()->for($user)->create(['title_prefix' => null, 'title_suffix' => null, 'first_name' => 'Max', 'last_name' => 'Muster', 'company_name' => null, 'company_suffix' => null]);
        $invoice = $user->invoices()->create([
            'customer_id' => $customer->id, 'number' => '2026-013', 'invoice_date' => '2026-10-05', 'due_date' => '2026-10-19',
            'tax_rate' => 20, 'paid_amount' => 0, 'recipient' => $customer->toRecipient(), 'issuer' => [],
        ]);
        $invoice->items()->create(['position' => 1, 'description' => 'Website', 'quantity' => 1, 'unit_price' => 100]);

        return $invoice;
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('2026-013-Max Muster.pdf', self::PDF);
    }

    public function test_attached_original_replaces_the_generated_pdf(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user);

        // Generated PDF before
        $this->assertStringNotContainsString('ORIGINAL-RECHNUNG', $this->actingAs($user)->get(route('invoices.download', $invoice))->getContent());

        $this->actingAs($user)->post(route('invoices.original.store', $invoice), ['original' => $this->pdf()])->assertSessionHas('success');

        // Stored privately
        $media = $invoice->fresh()->getFirstMedia('original');
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());

        // Download and export serve the original
        $this->assertStringContainsString('ORIGINAL-RECHNUNG-2026-013', $this->actingAs($user)->get(route('invoices.download', $invoice))->getContent());

        $zipResponse = $this->actingAs($user)->get(route('export.zip', ['from' => '2026-10-01', 'to' => '2026-10-31']));
        $zip = new ZipArchive();
        $zip->open($zipResponse->getFile()->getPathname());
        $this->assertStringContainsString('ORIGINAL-RECHNUNG-2026-013', $zip->getFromName('Rechnungen/2026-013-Max Muster.pdf'));
        $zip->close();

        // Removed: generated again
        $this->actingAs($user)->delete(route('invoices.original.destroy', $invoice));
        $this->assertStringNotContainsString('ORIGINAL-RECHNUNG', $this->actingAs($user)->get(route('invoices.download', $invoice))->getContent());
    }

    public function test_original_can_be_attached_when_creating_the_invoice(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'number' => '2026-013', 'invoice_date' => '2026-10-05', 'due_date' => '2026-10-19',
            'tax_rate' => 20, 'paid_amount' => 0,
            'items' => [['description' => 'Website', 'quantity' => 1, 'unit' => 'Stück', 'unit_price' => 100]],
            'original' => $this->pdf(),
        ])->assertSessionHasNoErrors();

        $invoice = $user->invoices()->first();
        $this->assertTrue($invoice->hasMedia('original'));

        $this->actingAs($user)->get(route('invoices.index'))->assertInertia(fn (Assert $page) => $page->where('invoices.0.has_original', true));
        $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertInertia(fn (Assert $page) => $page->where('original.name', fn ($name) => str_starts_with($name, '2026-013')));
    }

    public function test_only_pdfs_and_only_own_invoices(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user);

        $this->get(route('invoices.download', $invoice))->assertRedirect(route('login'));

        $this->actingAs($user)->post(route('invoices.original.store', $invoice), ['original' => UploadedFile::fake()->image('foto.jpg')])
            ->assertSessionHasErrors('original');

        $this->actingAs(User::factory()->create())->post(route('invoices.original.store', $invoice), ['original' => $this->pdf()])->assertForbidden();
    }

    public function test_original_is_part_of_the_backup_and_removed_with_the_user(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user);
        $invoice->addMedia($this->pdf())->toMediaCollection('original');

        $json = $this->actingAs($user)->get(route('backup.download'))->streamedContent();
        $this->actingAs($user)->post(route('backup.restore'), [
            'file' => UploadedFile::fake()->createWithContent('backup.json', $json), 'mode' => 'replace',
        ])->assertSessionHas('success');

        $restored = $user->invoices()->first();
        $this->assertTrue($restored->hasMedia('original'));
        $this->assertSame(1, \Spatie\MediaLibrary\MediaCollections\Models\Media::count());   // no leftover of the replaced one

        $path = $restored->getFirstMedia('original')->getPath();
        $user->delete();
        $this->assertFileDoesNotExist($path);
    }
}
