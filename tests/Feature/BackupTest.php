<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MaintenanceContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(User $user): void
    {
        $user->businessProfile()->create(['name' => 'Korbitsch IT', 'iban' => 'AT611904300234573201']);
        CatalogItem::factory()->for($user)->create();

        $customer = Customer::factory()->for($user)->create(['last_name' => 'Muster', 'interests' => ['websites']]);
        $contract = MaintenanceContract::factory()->for($user)->for($customer)->create();

        $invoice = $user->invoices()->create([
            'customer_id' => $customer->id, 'maintenance_contract_id' => $contract->id, 'number' => '2026-001',
            'invoice_date' => '2026-10-01', 'due_date' => '2026-10-15', 'tax_rate' => 20, 'paid_amount' => 0,
            'recipient' => $customer->toRecipient(), 'issuer' => ['name' => 'Korbitsch IT'],
        ]);
        $invoice->items()->create(['position' => 1, 'description' => "Website\n- Hosting", 'quantity' => 1, 'unit' => 'Stück', 'unit_price' => 468]);
        $invoice->payments()->create(['paid_at' => '2026-10-05', 'amount' => 100, 'method' => PaymentMethod::Transfer]);
        $contract->invoices()->create([
            'invoice_id' => $invoice->id, 'invoiced_at' => '2026-10-01', 'period_start' => '2026-10-01',
            'period_end' => '2027-09-30', 'amount' => 468, 'invoice_number' => '2026-001',
        ]);

        $cancellation = $invoice->load('items')->cancel();
        $this->assertSame($invoice->id, $cancellation->cancels_invoice_id);
    }

    private function download(User $user): string
    {
        return $this->actingAs($user)->get(route('backup.download'))->assertOk()->streamedContent();
    }

    private function upload(string $json): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('backup.json', $json);
    }

    public function test_backup_contains_only_own_data(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);
        $this->seedData(User::factory()->create());

        $backup = json_decode($this->download($user), true);

        $this->assertSame('mesync-backup', $backup['format']);
        $this->assertCount(1, $backup['data']['customers']);
        $this->assertCount(2, $backup['data']['invoices']);
        $this->assertCount(2, $backup['data']['invoice_items']);
        $this->assertCount(1, $backup['data']['invoice_payments']);
        $this->assertCount(1, $backup['data']['maintenance_invoices']);
    }

    public function test_replace_restores_everything_with_relations(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);
        $json = $this->download($user);

        // Changes after the backup are gone after restoring
        Customer::factory()->for($user)->create();
        $user->invoices()->where('number', '2026-001')->first()->items()->update(['description' => 'geändert']);

        $this->actingAs($user)
            ->post(route('backup.restore'), ['file' => $this->upload($json), 'mode' => 'replace'])
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, $user->customers()->count());
        $this->assertSame(1, $user->catalogItems()->count());
        $this->assertSame('Korbitsch IT', $user->businessProfile()->first()->name);

        $invoice = $user->invoices()->where('number', '2026-001')->first();
        $cancellation = $user->invoices()->where('number', '!=', '2026-001')->first();
        $contract = $user->maintenanceContracts()->first();

        $this->assertSame("Website\n- Hosting", $invoice->items->first()->description);
        $this->assertSame(['websites'], $invoice->customer->interests);
        $this->assertSame($contract->id, $invoice->maintenance_contract_id);
        $this->assertSame($invoice->id, $cancellation->cancels_invoice_id);
        $this->assertSame(100.0, (float) $invoice->payments->first()->amount);
        $this->assertSame($invoice->id, $contract->invoices()->first()->invoice_id);
    }

    public function test_append_keeps_existing_data_and_skips_existing_invoice_numbers(): void
    {
        $source = User::factory()->create();
        $this->seedData($source);
        $json = $this->download($source);

        $user = User::factory()->create();
        Customer::factory()->for($user)->create();
        $user->invoices()->create([
            'number' => '2026-001', 'invoice_date' => '2026-09-01', 'due_date' => '2026-09-15',
            'tax_rate' => 20, 'paid_amount' => 0, 'recipient' => [], 'issuer' => [],
        ]);

        $this->actingAs($user)
            ->post(route('backup.restore'), ['file' => $this->upload($json), 'mode' => 'append'])
            ->assertSessionHas('success');

        $this->assertSame(2, $user->customers()->count());
        $this->assertSame(2, $user->invoices()->count());   // existing 2026-001 + imported cancellation
        $this->assertSame('2026-09-01', $user->invoices()->where('number', '2026-001')->first()->invoice_date->toDateString());

        // Source data is untouched
        $this->assertSame(2, $source->invoices()->count());
    }

    public function test_logo_is_part_of_the_backup(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->addMedia(UploadedFile::fake()->image('logo.png', 200, 100))->toMediaCollection('logo');
        $json = $this->download($user);

        $user->clearMediaCollection('logo');
        $this->actingAs($user)->post(route('backup.restore'), ['file' => $this->upload($json), 'mode' => 'replace']);

        $this->assertNotNull($user->fresh()->getFirstMedia('logo'));
    }

    public function test_invalid_file_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->seedData($user);

        $this->actingAs($user)
            ->post(route('backup.restore'), ['file' => $this->upload('{"foo": 1}'), 'mode' => 'replace'])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, $user->customers()->count());
    }
}
