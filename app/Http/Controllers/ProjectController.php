<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceContractType;
use App\Models\Offer;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Websites and web apps from request to go-live: offers, invoices and the maintenance
 * contract in one place, on a board by phase.
 */
class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = $request->user()->projects()
            ->with(['customer', 'offers.items', 'maintenanceContract'])
            ->latest('updated_at')
            ->get()
            ->map(fn (Project $project) => [
                ...$project->only(['id', 'name', 'url', 'updated_at']),
                'type_label' => $project->type->label(),
                'customer' => $project->customer->display_name,
                'phase' => $project->phase(),
                'lost' => $project->isLost(),
                'next' => $project->nextStep(),
                // Latest offer: what the project is about money-wise
                'total' => $project->offers->first()?->subtotal,
                'monthly' => $project->maintenanceContract?->monthly_price ?? $project->offers->first()?->maintenance_monthly_price,
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'phases' => Project::PHASES,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Projects/Create', [
            ...$this->formOptions($request),
            'project' => ['customer_id' => $request->integer('customer') ?: null, 'type' => 'website', 'name' => '', 'url' => '', 'notes' => ''],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($this->validated($request));

        return to_route('projects.show', $project)->with('success', "Projekt „{$project->name}“ wurde angelegt.");
    }

    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load(['customer', 'offers.items', 'invoices.items', 'invoices.payments', 'invoices.cancellation', 'maintenanceContract', 'media']);

        return Inertia::render('Projects/Show', [
            'project' => [
                ...$project->only(['id', 'name', 'url', 'notes', 'customer_id', 'type', 'completed_at']),
                'type_label' => $project->type->label(),
                'customer' => $project->customer->only(['id', 'display_name', 'email']),
            ],
            'phase' => $project->phase(),
            'lost' => $project->isLost(),
            'next' => $project->nextStep(),
            'phases' => Project::PHASES,
            'offers' => $project->offers,
            // Project invoice and the maintenance invoices of its contract
            'invoices' => $project->invoices,
            'contract' => $project->maintenanceContract,
            // Completion can be undone until the go-live
            'canReopen' => $project->completed_at !== null && ! $project->maintenanceContract?->start_date,
            'files' => $project->getMedia('files')->sortByDesc('created_at')->values()->map(fn (Media $media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'size' => $media->size,
                'mime' => $media->mime_type,
                'image' => str_starts_with($media->mime_type, 'image/'),
                'url' => route('projects.files.show', [$project, $media]),
                'created_at' => $media->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function edit(Request $request, Project $project): Response
    {
        Gate::authorize('update', $project);

        return Inertia::render('Projects/Edit', [
            ...$this->formOptions($request),
            'project' => $project,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($this->validated($request));

        // Contract and open offers follow name and address
        $project->maintenanceContract?->update(['name' => $project->name, 'url' => $project->url]);

        return to_route('projects.show', $project)->with('success', 'Projekt wurde gespeichert.');
    }

    /**
     * The actual work is done: now the project invoice can be written.
     */
    public function complete(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $date = $request->validate(['completed_at' => 'required|date'], [
            'completed_at.required' => 'Bitte gib an, wann die Umsetzung fertig war.',
        ])['completed_at'];

        $project->forceFill(['completed_at' => $date])->save();

        return back()->with('success', 'Umsetzung abgeschlossen – jetzt die Projektrechnung erstellen.');
    }

    /**
     * Back to the implementation, as long as the site isn't live.
     */
    public function reopen(Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        if ($project->maintenanceContract?->start_date) {
            return back()->with('error', 'Die Seite ist schon live – der Wartungsvertrag läuft.');
        }

        $project->forceFill(['completed_at' => null])->save();

        return back()->with('success', 'Umsetzung ist wieder offen.');
    }

    /**
     * Attach files (PDF, images): several at once.
     */
    public function storeFiles(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $request->validate([
            'files' => 'required|array|max:20',
            'files.*' => 'file|mimes:pdf,png,jpg,jpeg,webp,gif|max:20480',
        ], [
            'files.required' => 'Bitte wähle eine Datei aus.',
            'files.*.mimes' => 'Erlaubt sind PDF und Bilder (PNG, JPG, WebP, GIF).',
            'files.*.max' => 'Eine Datei darf maximal 20 MB groß sein.',
        ]);

        foreach ($request->file('files') as $file) {
            $project->addMedia($file)->toMediaCollection('files');
        }

        $count = count($request->file('files'));

        return back()->with('success', $count === 1 ? 'Datei wurde hochgeladen.' : "{$count} Dateien wurden hochgeladen.");
    }

    /**
     * Files are private: shown through this route only, in the browser (inline).
     */
    public function showFile(Project $project, Media $media)
    {
        Gate::authorize('view', $project);
        abort_unless($media->model_type === Project::class && $media->model_id === $project->id && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $media->file_name, Str::ascii($media->file_name)),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroyFile(Project $project, Media $media): RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_unless($media->model_type === Project::class && $media->model_id === $project->id, 404);

        $media->delete();

        return back()->with('success', "„{$media->file_name}“ wurde gelöscht.");
    }

    /**
     * Offers, invoices and the contract stay, they just lose the link to the project.
     */
    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return to_route('projects.index')->with('success', "Projekt „{$project->name}“ wurde gelöscht. Angebote, Rechnungen und Vertrag bleiben erhalten.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $url = trim((string) $request->input('url'));
        $request->merge(['url' => $url === '' ? null : rtrim($url, '/')]);

        return $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('user_id', $request->user()->id)],
            'type' => ['required', Rule::enum(MaintenanceContractType::class)],
            'name' => 'required|string|max:255',
            'url' => 'nullable|url:http,https|max:255',
            'notes' => 'nullable|string|max:5000',
        ], [
            'customer_id.required' => 'Bitte wähle einen Kunden aus.',
            'name.required' => 'Bitte gib dem Projekt einen Namen, z. B. „Website Musterfirma“.',
            'url.url' => 'Bitte gib eine gültige Domain an, z. B. musterfirma.at',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'customers' => $request->user()->customers()->orderBy('last_name')->orderBy('company_name')->get()
                ->map(fn ($customer) => ['value' => $customer->id, 'label' => $customer->display_name, 'recipient' => $customer->toRecipient()]),
            'types' => MaintenanceContractType::options(),
        ];
    }
}
