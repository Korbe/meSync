<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\OfferPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_signature_is_stored_privately_and_only_shown_to_its_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.signature.store'), ['signature' => UploadedFile::fake()->image('unterschrift.png', 600, 200)])
            ->assertSessionHasNoErrors();

        $media = $user->getFirstMedia('signature');
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());

        $this->actingAs($user)->get(route('profile.signature.show'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('profile.signature.show'))->assertNotFound();

        $this->actingAs($user)->delete(route('profile.signature.destroy'));
        $this->assertNull($user->fresh()->getFirstMedia('signature'));
    }

    public function test_demo_user_cannot_upload_a_signature(): void
    {
        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);

        $this->actingAs($demo)
            ->post(route('profile.signature.store'), ['signature' => UploadedFile::fake()->image('unterschrift.png')])
            ->assertSessionHas('error');

        $this->assertNull($demo->getFirstMedia('signature'));
    }

    public function test_signature_is_printed_on_offers(): void
    {
        $user = User::factory()->create();
        $user->addMedia(UploadedFile::fake()->image('unterschrift.png', 600, 200))->toMediaCollection('signature');
        $offer = $user->offers()->create([
            'customer_id' => Customer::factory()->for($user)->create()->id, 'type' => 'website', 'number' => 'A-2026-001', 'title' => 'Website',
            'offer_date' => '2026-10-21', 'valid_until' => '2026-11-20', 'features' => [], 'integrations' => [], 'payment_terms' => 'prepaid',
            'maintenance_monthly_price' => 39, 'recipient' => ['name' => 'Kunde'], 'issuer' => ['name' => 'Ing. Lukas Korbitsch'],
        ]);

        $images = fn (string $pdf) => substr_count($pdf, '/Subtype /Image');

        $this->assertSame(1, $images(app(OfferPdf::class)->render($offer)));
        $user->clearMediaCollection('signature');
        $this->assertSame(0, $images(app(OfferPdf::class)->render($offer->fresh())));
    }
}
