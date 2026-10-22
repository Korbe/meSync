<?php

namespace Tests\Feature;

use App\Actions\Jetstream\DeleteUser;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\MaintenanceContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class DeleteUserDataTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    private const TABLES = [
        'business_profiles', 'catalog_items', 'customers', 'maintenance_contracts', 'maintenance_invoices',
        'invoices', 'invoice_items', 'invoice_payments', 'inventory_items', 'media', 'offers', 'offer_items', 'projects',
    ];

    public function test_deleting_a_user_removes_all_data_and_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->userWithEverything();
        $other = $this->userWithEverything();

        $userFiles = Media::all()
            ->filter(fn (Media $media) => $this->belongsTo($media, $user))
            ->map(fn (Media $media) => $media->getPath());
        $this->assertCount(2, $userFiles);   // logo + receipt

        app(DeleteUser::class)->delete($user->fresh());

        // Only the other user's rows are left: one per table, two files (logo + receipt)
        foreach (self::TABLES as $table) {
            $this->assertSame($table === 'media' ? 2 : 1, DB::table($table)->count(), $table);
        }

        foreach ($userFiles as $path) {
            $this->assertFileDoesNotExist($path);
        }

        $this->assertNotNull($other->fresh()->getFirstMedia('logo'));
        $this->assertTrue($other->inventoryItems()->first()->hasMedia('receipt'));
    }

    private function userWithEverything(): User
    {
        $user = User::factory()->create();
        $user->businessProfile()->create(['name' => 'Firma']);
        $user->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('logo');
        CatalogItem::factory()->for($user)->create();

        $customer = Customer::factory()->for($user)->create();
        $contract = MaintenanceContract::factory()->for($user)->for($customer)->create();
        $invoice = $user->invoices()->create([
            'customer_id' => $customer->id, 'maintenance_contract_id' => $contract->id, 'number' => '2026-001',
            'invoice_date' => '2026-10-01', 'due_date' => '2026-10-15', 'tax_rate' => 20, 'paid_amount' => 0,
            'recipient' => [], 'issuer' => [],
        ]);
        $invoice->items()->create(['position' => 1, 'description' => 'X', 'quantity' => 1, 'unit_price' => 100]);
        $invoice->payments()->create(['paid_at' => '2026-10-02', 'amount' => 120, 'method' => 'transfer']);
        $contract->invoices()->create(['invoice_id' => $invoice->id, 'invoiced_at' => '2026-10-01', 'period_start' => '2026-10-01', 'period_end' => '2027-09-30', 'amount' => 100]);

        $project = $user->projects()->create(['customer_id' => $customer->id, 'type' => 'website', 'name' => 'Website']);
        $offer = $user->offers()->create([
            'customer_id' => $customer->id, 'project_id' => $project->id, 'type' => 'website', 'number' => 'A-2026-001', 'title' => 'Website', 'offer_date' => '2026-10-01',
            'valid_until' => '2026-10-31', 'features' => [], 'integrations' => [], 'payment_terms' => 'prepaid', 'maintenance_monthly_price' => 39,
            'recipient' => [], 'issuer' => [],
        ]);
        $offer->items()->create(['position' => 1, 'description' => 'X', 'quantity' => 1, 'unit_price' => 1000]);

        $item = InventoryItem::factory()->for($user)->create();
        $item->addMedia(UploadedFile::fake()->createWithContent('beleg.pdf', self::PDF))->toMediaCollection('receipt');

        return $user;
    }

    private function belongsTo(Media $media, User $user): bool
    {
        return ($media->model_type === User::class && $media->model_id === $user->id)
            || ($media->model_type === InventoryItem::class && InventoryItem::find($media->model_id)?->user_id === $user->id);
    }
}
