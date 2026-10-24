<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTest extends TestCase
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
        $this->customer = Customer::factory()->for($this->user)->create(['company_name' => 'Duschking']);
    }

    private function project(): Project
    {
        $this->actingAs($this->user)->post(route('projects.store'), [
            'customer_id' => $this->customer->id, 'type' => 'webapp', 'name' => 'Web-App Duschking', 'url' => 'https://duschking.at/',
        ])->assertSessionHasNoErrors();

        return $this->user->projects()->latest('id')->first();
    }

    private function phase(Project $project): string
    {
        return $project->fresh()->load(['offers', 'maintenanceContract'])->phase();
    }

    public function test_project_runs_through_its_phases_with_the_next_step(): void
    {
        $project = $this->project();
        $this->assertSame('https://duschking.at', $project->url);
        $this->assertSame('request', $this->phase($project));

        // The offer is prefilled from the project
        $this->get(route('offers.create', ['project' => $project->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('offer.project_id', $project->id)
                ->where('offer.customer_id', $this->customer->id)
                ->where('offer.type', 'webapp')
                ->where('offer.title', 'Web-App Duschking'));

        $this->post(route('offers.store'), [
            'customer_id' => $this->customer->id, 'project_id' => $project->id, 'type' => 'webapp', 'number' => 'A-2026-001',
            'title' => 'Web-App Duschking', 'offer_date' => '2026-10-21', 'valid_until' => '2026-11-20', 'tax_rate' => '20',
            'payment_terms' => 'on_completion', 'maintenance_monthly_price' => '59',
            'items' => [['description' => 'Web-App', 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '3500']],
        ])->assertSessionHasNoErrors();
        $offer = $project->offers()->first();
        $this->assertSame('offer', $this->phase($project));
        $next = fn () => $project->fresh()->load(['offers', 'maintenanceContract'])->nextStep();
        $this->assertSame('Angebot an den Kunden senden', $next()['label']);

        // Sent (by mail or handed over): waiting for the customer
        $this->post(route('offers.sent', $offer));
        $this->assertSame('Warten auf Antwort', $next()['label']);
        $this->assertSame('Zusage oder Absage eintragen', $next()['action']);

        $this->post(route('offers.accept', $offer));
        $this->assertSame('implementation', $this->phase($project));

        // First the actual work: no project invoice before the implementation is finished
        $this->assertSame('Projekt umsetzen', $next()['label']);
        $this->assertSame('complete', $next()['method']);
        $this->get(route('invoices.create', ['offer' => $offer->id]))->assertRedirect(route('projects.show', $project));

        $this->post(route('projects.complete', $project), ['completed_at' => '2026-11-20'])->assertSessionHas('success');
        $this->assertSame('2026-11-20', $project->fresh()->completed_at->toDateString());
        $this->assertSame('Projektrechnung erstellen', $next()['label']);

        // Can be reopened until the go-live
        $this->delete(route('projects.reopen', $project));
        $this->assertNull($project->fresh()->completed_at);
        $this->post(route('projects.complete', $project), ['completed_at' => '2026-11-20']);

        // Project invoice: linked to the project, the contract too
        $this->get(route('invoices.create', ['offer' => $offer->id]))->assertInertia(fn (Assert $page) => $page->where('invoice.project_id', $project->id));
        $this->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'project_id' => $project->id, 'offer_id' => $offer->id, 'number' => '2026-100',
            'invoice_date' => '2026-10-21', 'due_date' => '2026-11-04', 'tax_rate' => 20, 'paid_amount' => 0,
            'items' => [['description' => 'Web-App', 'quantity' => 1, 'unit_price' => 3500]],
        ])->assertSessionHasNoErrors();
        $contract = $project->fresh()->maintenanceContract;
        $this->assertSame('pending', $contract->status);
        $this->assertSame('implementation', $this->phase($project));
        $this->assertSame('Go-Live', $next()['label']);
        $this->assertSame('Vertrag starten', $next()['action']);

        // The go-live date is suggested from the completion
        $this->get(route('contracts.edit', $contract))->assertInertia(fn (Assert $page) => $page->where('contract.project.completed_at', '2026-11-20'));

        // Go-live: the maintenance invoice belongs to the project as well
        $this->post(route('contracts.start', $contract), ['start_date' => '2026-12-01']);
        $this->assertSame('live', $this->phase($project));
        $this->delete(route('projects.reopen', $project))->assertSessionHas('error');   // live: stays completed
        $this->get(route('invoices.create', ['contract' => $contract->id, 'early' => 1]))->assertInertia(fn (Assert $page) => $page->where('invoice.project_id', $project->id));

        $this->get(route('projects.show', $project))->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->where('phase', 'live')
            ->has('offers', 1)
            ->has('invoices', 1)
            ->where('contract.id', $contract->id));
    }

    public function test_project_can_be_ordered_without_an_offer(): void
    {
        $project = $this->project();
        $next = fn () => $project->fresh()->load(['offers', 'maintenanceContract'])->nextStep();

        // Ordered elsewhere: straight to the implementation
        $this->post(route('projects.order', $project), ['ordered_at' => '2026-10-22'])->assertSessionHas('success');
        $this->assertSame('implementation', $this->phase($project));
        $this->assertSame('Projekt umsetzen', $next()['label']);

        // Can be taken back until the work is done
        $this->delete(route('projects.unorder', $project));
        $this->assertSame('request', $this->phase($project));
        $this->post(route('projects.order', $project), ['ordered_at' => '2026-10-22']);

        $this->post(route('projects.complete', $project), ['completed_at' => '2026-11-15']);
        $this->delete(route('projects.unorder', $project))->assertSessionHas('error');
        $this->assertSame('Projektrechnung erstellen', $next()['label']);
        $this->assertSame(route('invoices.create', ['project' => $project->id]), $next()['url']);

        // Project invoice (free, with the project)
        $this->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'project_id' => $project->id, 'number' => '2026-300',
            'invoice_date' => '2026-11-15', 'due_date' => '2026-11-29', 'tax_rate' => 20, 'paid_amount' => 0,
            'items' => [['description' => 'Web-App', 'quantity' => 1, 'unit_price' => 3500]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Wartungsvertrag anlegen', $next()['label']);

        // The contract is prefilled from the project, starting with the completion
        $this->get(route('contracts.create', ['project' => $project->id]))->assertInertia(fn (Assert $page) => $page
            ->where('prefill.project_id', $project->id)
            ->where('prefill.type', 'webapp')
            ->where('prefill.name', 'Web-App Duschking')
            ->where('prefill.url', 'https://duschking.at')
            ->where('prefill.start_date', '2026-11-15'));

        $this->post(route('contracts.store'), [
            'customer_id' => $this->customer->id, 'project_id' => $project->id, 'type' => 'webapp', 'name' => 'Web-App Duschking',
            'url' => 'https://duschking.at', 'monthly_price' => 59, 'start_date' => '2026-11-15', 'next_invoice_date' => '2026-11-15',
        ])->assertSessionHasNoErrors();

        $this->assertSame('live', $this->phase($project));
    }

    public function test_existing_offers_and_invoices_can_be_assigned_and_detached(): void
    {
        $project = $this->project();
        $invoice = $this->user->invoices()->create([
            'customer_id' => $this->customer->id, 'number' => '2026-400', 'invoice_date' => '2026-10-01', 'due_date' => '2026-10-15',
            'tax_rate' => 20, 'paid_amount' => 0, 'recipient' => [], 'issuer' => [],
        ]);
        $offer = $this->user->offers()->create([
            'customer_id' => $this->customer->id, 'type' => 'webapp', 'number' => 'A-2026-009', 'title' => 'Altes Angebot', 'offer_date' => '2026-09-01',
            'valid_until' => '2026-10-01', 'features' => [], 'integrations' => [], 'payment_terms' => 'prepaid', 'maintenance_monthly_price' => 59,
            'recipient' => [], 'issuer' => [],
        ]);
        $offer->forceFill(['accepted_at' => now()])->save();
        $foreign = Customer::factory()->for($this->user)->create();
        $other = $this->user->invoices()->create([
            'customer_id' => $foreign->id, 'number' => '2026-401', 'invoice_date' => '2026-10-01', 'due_date' => '2026-10-15',
            'tax_rate' => 20, 'paid_amount' => 0, 'recipient' => [], 'issuer' => [],
        ]);

        // Only the customer's own documents without a project are offered
        $this->get(route('projects.show', $project))->assertInertia(fn (Assert $page) => $page
            ->has('assignable.invoices', 1)->where('assignable.invoices.0.value', $invoice->id)
            ->has('assignable.offers', 1)->where('assignable.offers.0.value', $offer->id));

        $this->post(route('projects.attach', $project), ['type' => 'invoice', 'id' => $invoice->id])->assertSessionHas('success');
        $this->post(route('projects.attach', $project), ['type' => 'offer', 'id' => $offer->id])->assertSessionHas('success');
        $this->assertSame($project->id, $invoice->fresh()->project_id);
        // The accepted offer is the order: the project is in the implementation
        $this->assertSame('implementation', $this->phase($project));

        // Other customers' documents can't be assigned
        $this->post(route('projects.attach', $project), ['type' => 'invoice', 'id' => $other->id])->assertStatus(422);

        $this->delete(route('projects.detach', [$project, 'invoice', $invoice->id]))->assertSessionHas('success');
        $this->assertNull($invoice->fresh()->project_id);
        $this->assertSame(1, $this->user->invoices()->where('number', '2026-400')->count());   // kept

        $this->actingAs(User::factory()->create())->post(route('projects.attach', $project), ['type' => 'invoice', 'id' => $invoice->id])->assertForbidden();
    }

    public function test_order_and_invoice_pdfs_from_elsewhere_go_with_the_project(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF");

        // The order as PDF lands in the project files
        $this->post(route('projects.order', $project), ['ordered_at' => '2026-10-22', 'document' => $pdf('auftrag.pdf')])->assertSessionHasNoErrors();
        $this->assertSame(['auftrag.pdf'], $project->fresh()->getMedia('files')->pluck('file_name')->all());

        // An invoice written elsewhere: captured with its PDF, belongs to the project
        $this->get(route('invoices.create', ['project' => $project->id]))->assertInertia(fn (Assert $page) => $page->where('invoice.project_id', $project->id));
        $this->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'project_id' => $project->id, 'number' => '2026-500', 'invoice_date' => '2026-10-22',
            'due_date' => '2026-11-05', 'tax_rate' => 20, 'paid_amount' => 0, 'original' => $pdf('rechnung.pdf'),
            'items' => [['description' => 'Website', 'quantity' => 1, 'unit_price' => 2500]],
        ])->assertSessionHasNoErrors();

        $invoice = $this->user->invoices()->where('number', '2026-500')->first();
        $this->assertSame($project->id, $invoice->project_id);
        $this->assertSame('rechnung.pdf', $invoice->getFirstMedia('original')->file_name);
    }

    public function test_with_prepayment_the_invoice_comes_before_the_work(): void
    {
        $this->actingAs($this->user)->post(route('offers.store'), [
            'customer_id' => $this->customer->id, 'project_id' => 'new', 'type' => 'website', 'number' => 'A-2026-001', 'title' => 'Website',
            'offer_date' => '2026-10-21', 'valid_until' => '2026-11-20', 'tax_rate' => '20', 'payment_terms' => 'prepaid', 'maintenance_monthly_price' => '39',
            'items' => [['description' => 'Website', 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '2500']],
        ])->assertSessionHasNoErrors();
        $offer = $this->user->offers()->sole();
        $project = $offer->project;
        $next = fn () => $project->fresh()->load(['offers', 'maintenanceContract'])->nextStep();

        $this->post(route('offers.accept', $offer));
        $this->assertSame('Projektrechnung erstellen', $next()['label']);
        $this->get(route('invoices.create', ['offer' => $offer->id]))->assertOk();

        $this->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'project_id' => $project->id, 'offer_id' => $offer->id, 'number' => '2026-100',
            'invoice_date' => '2026-10-21', 'due_date' => '2026-11-04', 'tax_rate' => 20, 'paid_amount' => 0,
            'items' => [['description' => 'Website', 'quantity' => 1, 'unit_price' => 2500]],
        ])->assertSessionHasNoErrors();

        // Paid in advance: now the actual work, then the go-live
        $this->assertSame('Projekt umsetzen', $next()['label']);
        $this->post(route('projects.complete', $project), ['completed_at' => '2026-11-15']);
        $this->assertSame('Go-Live', $next()['label']);
        $this->assertSame('Vertrag starten', $next()['action']);
    }

    public function test_offer_can_create_its_project_and_be_detached_again(): void
    {
        $payload = [
            'customer_id' => $this->customer->id, 'type' => 'website', 'number' => 'A-2026-001', 'title' => 'Website Duschking',
            'url' => 'https://duschking.at', 'offer_date' => '2026-10-21', 'valid_until' => '2026-11-20', 'tax_rate' => '20',
            'payment_terms' => 'prepaid', 'maintenance_monthly_price' => '39',
            'items' => [['description' => 'Website', 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '2500']],
        ];

        // New offers create their project unless chosen otherwise
        $this->actingAs($this->user)->get(route('offers.create'))->assertInertia(fn (Assert $page) => $page->where('offer.project_id', 'new'));

        $this->post(route('offers.store'), [...$payload, 'project_id' => 'new'])->assertSessionHasNoErrors();
        $offer = $this->user->offers()->sole();
        $project = $offer->project;
        $this->assertSame('Website Duschking', $project->name);
        $this->assertSame('https://duschking.at', $project->url);
        $this->assertSame($this->customer->id, $project->customer_id);
        $this->assertSame('website', $project->type->value);

        // Back to "Ohne Projekt"
        $this->put(route('offers.update', $offer), [...$payload, 'project_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($offer->fresh()->project_id);
        $this->assertSame(1, $this->user->projects()->count());

        // Projects of other users can't be chosen
        $foreign = User::factory()->create()->projects()->create(['customer_id' => Customer::factory()->create()->id, 'type' => 'website', 'name' => 'X']);
        $this->put(route('offers.update', $offer), [...$payload, 'project_id' => $foreign->id])->assertSessionHasErrors('project_id');
    }

    public function test_an_offer_written_elsewhere_can_be_uploaded(): void
    {
        Storage::fake('local');
        $project = $this->project();

        $this->post(route('projects.offer-documents.store', $project), ['document' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('document');
        $this->post(route('projects.offer-documents.store', $project), ['document' => UploadedFile::fake()->createWithContent('angebot.pdf', "%PDF-1.4\n%%EOF")])
            ->assertSessionHas('success');

        // Counts as the offer: waiting for the answer, then the order is entered on the project
        $this->assertSame('offer', $this->phase($project));
        $this->get(route('projects.show', $project))->assertInertia(fn (Assert $page) => $page
            ->has('offerDocuments', 1)->where('offerDocuments.0.name', 'angebot.pdf')
            ->has('files', 0)
            ->where('lost', false)
            ->where('next.method', 'order'));

        $this->post(route('projects.order', $project), ['ordered_at' => '2026-10-24'])->assertSessionHasNoErrors();
        $this->assertSame('implementation', $this->phase($project));

        $this->actingAs(User::factory()->create())
            ->post(route('projects.offer-documents.store', $project), ['document' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF')])
            ->assertForbidden();
    }

    public function test_files_are_attached_privately_and_shown_in_the_browser(): void
    {
        Storage::fake('local');
        $project = $this->project();

        $this->post(route('projects.files.store', $project), ['files' => [
            UploadedFile::fake()->image('entwurf.png', 800, 600),
            UploadedFile::fake()->createWithContent('briefing.pdf', "%PDF-1.4\n%%EOF"),
        ]])->assertSessionHas('success');
        $this->post(route('projects.files.store', $project), ['files' => [UploadedFile::fake()->create('virus.exe', 10)]])->assertSessionHasErrors('files.0');

        $files = $project->fresh()->getMedia('files');
        $this->assertCount(2, $files);
        $this->assertSame('local', $files->first()->disk);

        $this->get(route('projects.show', $project))->assertInertia(fn (Assert $page) => $page
            ->has('files', 2)
            ->where('files', fn ($files) => collect($files)->pluck('image')->sort()->values()->all() === [false, true]));

        $image = $files->firstWhere('file_name', 'entwurf.png');
        $this->get(route('projects.files.show', [$project, $image]))->assertOk()->assertHeader('Content-Disposition', 'inline; filename=entwurf.png');
        $this->actingAs(User::factory()->create())->get(route('projects.files.show', [$project, $image]))->assertForbidden();

        $this->actingAs($this->user)->delete(route('projects.files.destroy', [$project, $image]));
        $this->assertCount(1, $project->fresh()->getMedia('files'));

        // Deleting the customer takes the project and its files along
        $path = $project->fresh()->getFirstMedia('files')->getPath();
        $this->customer->delete();
        $this->assertFileDoesNotExist($path);
    }

    public function test_board_groups_projects_by_phase(): void
    {
        $this->project();

        $this->get(route('projects.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects', 1)
            ->where('projects.0.phase', 'request')
            ->where('projects.0.next.label', 'Angebot schreiben'));
    }

    public function test_plain_invoices_have_no_project_and_create_no_contract(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'number' => '2026-200', 'invoice_date' => '2026-10-21', 'due_date' => '2026-11-04',
            'tax_rate' => 20, 'paid_amount' => 0, 'items' => [['description' => 'PC-Hilfe vor Ort', 'quantity' => 1, 'unit_price' => 70]],
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->user->invoices()->first()->project_id);
        $this->assertSame(0, $this->user->maintenanceContracts()->count());
    }

    public function test_deleting_a_project_keeps_offers_and_contract(): void
    {
        $project = $this->project();
        $contract = $this->user->maintenanceContracts()->create(['customer_id' => $this->customer->id, 'project_id' => $project->id, 'type' => 'webapp', 'name' => 'X', 'monthly_price' => 59]);

        $this->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

        $this->assertNull($contract->fresh()->project_id);
    }

    public function test_projects_are_private_and_part_of_the_backup(): void
    {
        $project = $this->project();

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($other)->put(route('projects.update', $project), ['customer_id' => $this->customer->id, 'type' => 'website', 'name' => 'X'])->assertForbidden();

        $json = $this->actingAs($this->user)->get(route('backup.download'))->streamedContent();
        $this->actingAs($this->user)->post(route('backup.restore'), [
            'file' => UploadedFile::fake()->createWithContent('backup.json', $json), 'mode' => 'replace',
        ])->assertSessionHas('success');

        $restored = $this->user->projects()->with('customer')->sole();
        $this->assertSame('Web-App Duschking', $restored->name);
        $this->assertSame('Duschking', $restored->customer->company_name);
    }
}
