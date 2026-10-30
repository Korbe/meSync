<?php

namespace Tests\Feature;

use App\Jobs\SendOfferEmail;
use App\Mail\OfferMail;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-21 10:00:00');
        $this->user = User::factory()->create();
        $this->user->businessProfile()->create(['name' => 'Ing. Lukas Korbitsch', 'email' => 'info@korbitsch.at', 'default_tax_rate' => 20]);
        $this->customer = Customer::factory()->for($this->user)->create(['company_name' => 'Duschking', 'last_name' => 'Schuhmacher', 'email' => 'office@duschking.at']);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'customer_id' => $this->customer->id,
            'type' => 'webapp',
            'number' => 'A-2026-001',
            'title' => 'Web-App Duschking',
            'url' => 'https://duschking.at',
            'offer_date' => '2026-10-21',
            'valid_until' => '2026-11-20',
            'tax_rate' => '20',
            'package' => 'webapp',
            'features' => ['login', 'logic_form'],
            'integrations' => ['analytics'],
            'content_delivery' => 'mixed',
            'payment_terms' => 'prepaid',
            'reference_customer' => true,
            'maintenance_package' => 'Small',
            'maintenance_monthly_price' => '39',
            'items' => [['description' => "Web-App-Projekt\n- Login", 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '3500', 'list_price' => '3900']],
            ...$overrides,
        ];
    }

    private function offer(array $overrides = []): Offer
    {
        $this->actingAs($this->user)->post(route('offers.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return $this->user->offers()->latest('id')->first();
    }

    public function test_offer_includes_the_first_year_of_maintenance_with_tax(): void
    {
        $offer = $this->offer();

        $this->assertSame('A-2026-002', Offer::nextNumberFor($this->user, 2026));
        $this->assertSame(3500.0, $offer->project_subtotal);
        $this->assertSame(468.0, $offer->maintenance_annual_price);
        $this->assertSame(3968.0, $offer->subtotal);
        $this->assertSame(793.6, $offer->tax_amount);
        $this->assertSame(4761.6, $offer->total);
        $this->assertSame('draft', $offer->status);
        $this->assertSame('office@duschking.at', $offer->recipient['email']);
    }

    public function test_maintenance_is_mandatory_and_packages_must_fit_the_type(): void
    {
        $this->actingAs($this->user)->post(route('offers.store'), $this->payload(['maintenance_monthly_price' => '0']))
            ->assertSessionHasErrors('maintenance_monthly_price');

        $this->actingAs($this->user)->post(route('offers.store'), $this->payload(['type' => 'website', 'package' => 'webapp']))
            ->assertSessionHasErrors('package');
    }

    public function test_websites_have_no_dynamic_features_or_integrations(): void
    {
        $offer = $this->offer(['type' => 'website', 'package' => 'starter']);

        $this->assertSame([], $offer->features);
        $this->assertSame([], $offer->integrations);

        $this->actingAs($this->user)->post(route('offers.store'), $this->payload(['type' => 'website', 'package' => 'business', 'number' => 'A-2026-009']))
            ->assertSessionHasErrors('package');
    }

    public function test_pdf_shows_discount_maintenance_and_signatures(): void
    {
        $offer = $this->offer();

        $response = $this->actingAs($this->user)->get(route('offers.download', $offer))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $html = view('pdf.offer', [
            'offer' => $offer->load('items'), 'issuer' => $offer->issuer, 'recipient' => $offer->recipient, 'logo' => null, 'signature' => null,
            'types' => config('offers.types'), 'packages' => collect(config('offers.packages'))->where('type', 'webapp')->all(),
            'features' => config('offers.features'), 'integrations' => config('offers.integrations'),
            'contentDelivery' => config('offers.content_delivery'), 'paymentTerms' => config('offers.payment_terms'),
            'hints' => config('offers.hints'), 'contract' => config('offers.contract'), 'services' => $offer->type->services(),
        ])->render();

        $this->assertStringContainsString('<span class="list-price">3.900,00</span>', $html);
        $this->assertStringContainsString('Laufende Betreuung (12 Monate)', $html);
        $this->assertStringContainsString(config('offers.contract.maintenance_start'), $html);
        // Website and maintenance apart, minimum term, renewal and acceptance
        $this->assertStringContainsString($offer->type_label.'-Erstellung (einmalig)', $html);
        $this->assertStringContainsString('Wartung (12 Monate)', $html);
        $this->assertStringContainsString('verlängert sich der Vertrag automatisch', $html);
        $this->assertStringContainsString('erneut im Voraus', $html);
        $this->assertStringContainsString('Name des Unterzeichners', $html);
        $this->assertStringContainsString('innerhalb von 14 Tagen', $html);
        $this->assertStringContainsString('USt. 20 %', $html);
        $this->assertStringContainsString('4.761,60', $html);
        $this->assertStringContainsString('Unterschrift Kunde', $html);
        $this->assertStringContainsString('☑</span> Login-/Mitgliederbereich', $html);
        // Only what was chosen is printed
        $this->assertStringNotContainsString('☐', $html);
        $this->assertStringNotContainsString('Buchungssystem', $html);
        $this->assertStringNotContainsString('Website (ohne Backend-Logik', $html);
        $this->assertStringNotContainsString('Newsletter-Tool', $html);
        $this->assertStringNotContainsString('Kleinunternehmer', $html);
    }

    public function test_offers_keep_the_conditions_they_went_out_with(): void
    {
        $offer = $this->offer();
        $this->assertTrue($offer->fresh()->hasContractTerms());
        $this->assertEqualsWithDelta($offer->tax_amount, $offer->projectTaxAmount() + round($offer->tax_amount - $offer->projectTaxAmount(), 2), 0.001);

        $offer->forceFill(['terms_version' => 1])->save();
        $html = view('pdf.offer', [
            'offer' => $offer->fresh()->load('items'), 'issuer' => $offer->issuer, 'recipient' => $offer->recipient, 'logo' => null, 'signature' => null,
            'types' => config('offers.types'), 'packages' => [], 'features' => config('offers.features'), 'integrations' => config('offers.integrations'),
            'contentDelivery' => config('offers.content_delivery'), 'paymentTerms' => config('offers.payment_terms'),
            'hints' => config('offers.hints'), 'contract' => config('offers.contract'), 'services' => $offer->type->services(),
        ])->render();

        $this->assertStringNotContainsString('verlängert sich der Vertrag automatisch', $html);
        $this->assertStringContainsString('Unterschrift Auftraggeber', $html);
    }

    public function test_sent_offers_are_locked_and_revised_as_a_copy(): void
    {
        $offer = $this->offer();
        $this->post(route('offers.sent', $offer));

        $this->put(route('offers.update', $offer), $this->payload(['title' => 'Geändert']))->assertForbidden();

        $this->post(route('offers.duplicate', $offer));
        $copy = $this->user->offers()->latest('id')->first();
        $this->assertSame('A-2026-002', $copy->number);
        $this->assertNull($copy->sent_at);
        $this->assertSame(1, $copy->items()->count());
        $this->assertSame('3900.00', $copy->items()->first()->list_price);
    }

    public function test_offer_is_mailed_through_the_queue(): void
    {
        Queue::fake();
        $offer = $this->offer();

        $this->post(route('offers.send', $offer), ['to' => 'office@duschking.at', 'subject' => 'Angebot'])->assertSessionHas('success');
        Queue::assertPushed(SendOfferEmail::class);

        Mail::fake();
        (new SendOfferEmail($offer, 'office@duschking.at', 'Angebot A-2026-001'))->handle();
        Mail::assertSent(OfferMail::class, fn (OfferMail $mail) => $mail->hasReplyTo('info@korbitsch.at') && count($mail->attachments()) === 1);
        $this->assertSame('office@duschking.at', $offer->fresh()->sent_to);
        $this->assertTrue($offer->fresh()->isLocked());
    }

    private function billOffer(Offer $offer): \App\Models\Invoice
    {
        $this->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'number' => '2026-100', 'invoice_date' => '2026-10-21', 'due_date' => '2026-11-04',
            'tax_rate' => 20, 'paid_amount' => 0, 'offer_id' => $offer->id,
            'items' => collect($offer->load('items')->toInvoiceItems())->map(fn ($item) => [...$item, 'unit_price' => (float) $item['unit_price']])->all(),
        ])->assertSessionHasNoErrors();

        return $this->user->invoices()->latest('id')->first();
    }

    public function test_accepting_needs_no_date_and_creates_no_contract_yet(): void
    {
        $offer = $this->offer();

        $this->post(route('offers.accept', $offer))->assertSessionHas('success');

        $this->assertSame('accepted', $offer->fresh()->status);
        $this->assertSame(0, $this->user->maintenanceContracts()->count());
    }

    public function test_project_invoice_creates_a_contract_that_waits_for_the_go_live(): void
    {
        $offer = $this->offer();
        $this->post(route('offers.accept', $offer));

        // Only the project: the maintenance gets its own invoice once the site is live
        $this->get(route('invoices.create', ['offer' => $offer->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.offer_id', $offer->id)
                ->where('offer.number', 'A-2026-001')
                ->has('invoice.items', 1)
                ->where('invoice.items.0.unit_price', '3500.00'));

        $invoice = $this->billOffer($offer);
        $offer->refresh();
        $contract = $offer->maintenanceContract;

        $this->assertSame($invoice->id, $offer->invoice_id);
        $this->assertNull($invoice->maintenance_contract_id);
        $this->assertSame('pending', $contract->status);
        $this->assertNull($contract->monitor);   // not live yet: nothing to monitor
        $this->assertSame('Web-App Duschking', $contract->name);
        $this->assertSame('39.00', $contract->monthly_price);
        $this->assertFalse($offer->canReopen());

        // Nothing is due while waiting: no reminders, not on the dashboard
        $this->assertSame(0, $this->user->maintenanceContracts()->billable()->count());
        $this->get(route('contracts.index'))->assertOk();
        $this->get(route('contracts.edit', $contract))->assertOk();
        $this->get(route('contracts.pdf', $contract))->assertOk();
        $this->get(route('offers.edit', $offer))->assertOk();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_go_live_starts_the_contract_and_bills_the_first_maintenance_year(): void
    {
        $offer = $this->offer();
        $this->post(route('offers.accept', $offer));
        $this->billOffer($offer);
        $contract = $offer->fresh()->maintenanceContract;

        $this->post(route('contracts.start', $contract), ['start_date' => '2026-12-01', 'monitor_enabled' => true])
            ->assertRedirect(route('invoices.create', ['contract' => $contract, 'send' => 1, 'early' => 1]));

        $contract->refresh();
        $this->assertSame('2026-12-01', $contract->start_date->toDateString());
        $this->assertSame('2026-12-01', $contract->next_invoice_date->toDateString());
        $this->assertTrue($contract->monitor->enabled);
        $this->assertSame('https://duschking.at', $contract->monitor->url);

        // Maintenance invoice for 12 months from the go-live, sent right after saving
        $this->get(route('invoices.create', ['contract' => $contract, 'send' => 1, 'early' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sendAfterSave', true)
                ->where('invoice.maintenance_contract_id', $contract->id)
                ->where('invoice.items.0.unit_price', 468)
                ->where('invoice.items.0.description', "Webanwendung Wartung & Betreuung – Web-App Duschking\nZeitraum: 01.12.2026 – 30.11.2027"));

        $this->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'number' => '2026-101', 'invoice_date' => '2026-12-01', 'due_date' => '2026-12-15',
            'tax_rate' => 20, 'paid_amount' => 0, 'maintenance_contract_id' => $contract->id, 'send_after_save' => true,
            'items' => [['description' => 'Wartung', 'quantity' => 1, 'unit_price' => 468]],
        ])->assertRedirect(route('invoices.edit', ['invoice' => $this->user->invoices()->latest('id')->first(), 'send' => 1]));

        $this->assertSame('2027-12-01', $contract->fresh()->next_invoice_date->toDateString());
    }

    public function test_deleting_the_project_invoice_drops_the_waiting_contract(): void
    {
        $offer = $this->offer();
        $this->post(route('offers.accept', $offer));
        $invoice = $this->billOffer($offer);

        $this->delete(route('invoices.destroy', $invoice));

        $this->assertSame(0, $this->user->maintenanceContracts()->count());
        $this->assertNull($offer->fresh()->invoice_id);
        $this->assertTrue($offer->fresh()->canReopen());
    }

    public function test_terms_and_privacy_policy_go_along_with_the_offer_mail(): void
    {
        Storage::fake('local');
        $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF");

        $this->actingAs($this->user)->post(route('profile.documents.store', 'terms'), ['file' => $pdf('agb.pdf')])->assertSessionHasNoErrors();
        $this->post(route('profile.documents.store', 'privacy'), ['file' => $pdf('datenschutz.pdf')])->assertSessionHasNoErrors();
        $this->assertSame('local', $this->user->fresh()->getFirstMedia('terms')->disk);
        $this->get(route('profile.documents.show', 'terms'))->assertOk();

        $offer = $this->offer();
        $this->get(route('offers.edit', $offer))->assertInertia(fn (Assert $page) => $page->where('mail.attachments', ['AGB', 'Datenschutzerklärung']));

        $mail = new OfferMail($offer->fresh(), 'Angebot');
        $names = collect($mail->attachments())->map(fn ($attachment) => $attachment->as)->all();
        $this->assertSame([$offer->fileName(), 'AGB.pdf', 'Datenschutzerklärung.pdf'], $names);
        $this->assertStringContainsString('Im Anhang finden Sie außerdem: AGB und Datenschutzerklärung.', $mail->render());

        $this->delete(route('profile.documents.destroy', 'privacy'));
        $this->assertCount(2, (new OfferMail($offer->fresh(), 'Angebot'))->attachments());
    }

    public function test_list_filters_by_status(): void
    {
        $open = $this->offer();
        $expired = $this->offer(['number' => 'A-2026-002', 'offer_date' => '2026-08-01', 'valid_until' => '2026-08-31']);
        $declined = $this->offer(['number' => 'A-2026-003']);
        $this->post(route('offers.decline', $declined));

        $this->get(route('offers.index', ['tab' => 'open']))->assertInertia(fn (Assert $page) => $page
            ->component('Offers/Index')
            ->has('offers.data', 1)->where('offers.data.0.id', $open->id)
            ->where('counts.expired', 1)->where('counts.declined', 1)->where('counts.all', 3));

        $this->assertSame('expired', $expired->status);
    }

    public function test_offers_are_private_and_part_of_the_backup(): void
    {
        $offer = $this->offer();

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('offers.edit', $offer))->assertForbidden();
        $this->actingAs($other)->get(route('offers.download', $offer))->assertForbidden();
        $this->actingAs($other)->delete(route('offers.destroy', $offer))->assertForbidden();

        $json = $this->actingAs($this->user)->get(route('backup.download'))->streamedContent();
        $this->actingAs($this->user)->post(route('backup.restore'), [
            'file' => UploadedFile::fake()->createWithContent('backup.json', $json), 'mode' => 'replace',
        ])->assertSessionHas('success');

        $restored = $this->user->offers()->with('items', 'customer')->sole();
        $this->assertSame('A-2026-001', $restored->number);
        $this->assertSame('Duschking', $restored->customer->company_name);
        $this->assertSame(['login', 'logic_form'], $restored->features);
        $this->assertSame('3900.00', $restored->items->first()->list_price);
    }
}
