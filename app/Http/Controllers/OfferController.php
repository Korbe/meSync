<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceContractType;
use App\Http\Requests\Offer\OfferRequest;
use App\Jobs\SendOfferEmail;
use App\Models\Customer;
use App\Models\Offer;
use App\Services\OfferPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Offers for websites and web apps. The maintenance is always part of them: the invoice bills
 * the project plus its first year and creates the contract, which starts with the go-live.
 */
class OfferController extends Controller
{
    private const TABS = ['all', 'open', 'accepted', 'declined', 'expired'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'all';
        $search = trim((string) $request->query('search', ''));

        $offers = $user->offers()
            ->when($tab !== 'all', fn ($query) => $query->whereStatus($tab))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('recipient->name', 'like', "%{$search}%")))
            ->with('items')
            ->latest('offer_date')
            ->latest('id')
            ->paginate($this->perPage($request, 'offers'))
            ->withQueryString();

        $open = $user->offers()->whereStatus('open')->with('items')->get();
        $year = today()->year;
        $decidedThisYear = $user->offers()->whereYear('offer_date', $year)
            ->where(fn ($query) => $query->whereNotNull('accepted_at')->orWhereNotNull('declined_at'))->with('items')->get();
        $accepted = $decidedThisYear->whereNotNull('accepted_at');

        return Inertia::render('Offers/Index', [
            'offers' => $offers,
            'tab' => $tab,
            'search' => $search,
            'year' => $year,
            'stats' => [
                'open' => ['count' => $open->count(), 'sum' => round($open->sum('subtotal'), 2)],
                'accepted' => ['count' => $accepted->count(), 'sum' => round($accepted->sum('subtotal'), 2)],
                'maintenance' => round($accepted->sum(fn (Offer $offer) => (float) $offer->maintenance_monthly_price), 2),
                'rate' => $decidedThisYear->isEmpty() ? null : (int) round($accepted->count() / $decidedThisYear->count() * 100),
            ],
            'counts' => collect(self::TABS)->mapWithKeys(fn (string $key) => [
                $key => $key === 'all' ? $user->offers()->count() : $user->offers()->whereStatus($key)->count(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $today = today();
        $type = MaintenanceContractType::Website;

        return Inertia::render('Offers/Create', [
            ...$this->formOptions($request),
            'offer' => [
                'customer_id' => $request->integer('customer') ?: null,
                'type' => $type->value,
                'number' => Offer::nextNumberFor($user, $today->year),
                'title' => '',
                'url' => '',
                'offer_date' => $today->toDateString(),
                'valid_until' => $today->copy()->addDays(config('offers.valid_days'))->toDateString(),
                'tax_rate' => $user->businessProfile->default_tax_rate ?? 20,
                'package' => null,
                'features' => [],
                'integrations' => [],
                'other_function' => '',
                'content_delivery' => null,
                'payment_terms' => 'prepaid',
                'reference_customer' => false,
                'maintenance_package' => '',
                'maintenance_monthly_price' => $type->defaultMonthlyPrice(),
                'intro' => '',
                'notes' => '',
                'items' => [['description' => '', 'quantity' => 1, 'unit' => 'Pauschale', 'unit_price' => '', 'list_price' => null]],
            ],
        ]);
    }

    public function store(OfferRequest $request): RedirectResponse
    {
        $offer = DB::transaction(function () use ($request) {
            $offer = $request->user()->offers()->create($this->offerAttributes($request));
            $this->syncItems($offer, $request->validated('items'));

            return $offer;
        });

        return to_route('offers.edit', $offer)->with('success', "Angebot {$offer->number} wurde erstellt.");
    }

    public function edit(Request $request, Offer $offer): Response
    {
        Gate::authorize('view', $offer);

        $sender = $request->user()->businessProfile?->displayName() ?? ($offer->issuer['name'] ?? null);

        return Inertia::render('Offers/Update', [
            ...$this->formOptions($request),
            // Complete models: their computed attributes (status, totals) need all columns
            'offer' => $offer->load(['items', 'maintenanceContract', 'invoice.items', 'invoice.payments']),
            'mail' => [
                'to' => $offer->customer?->email ?? '',
                'subject' => "Angebot {$offer->number}".(filled($sender) ? " von {$sender}" : '')." – {$offer->title}",
                'replyTo' => $offer->issuer['email'] ?? $request->user()->businessProfile?->email,
                'demo' => $request->user()->isDemo(),
            ],
            'locked' => $offer->isLocked(),
            'canReopen' => $offer->canReopen(),
        ]);
    }

    public function update(OfferRequest $request, Offer $offer): RedirectResponse
    {
        Gate::authorize('update', $offer);

        DB::transaction(function () use ($request, $offer) {
            $offer->update($this->offerAttributes($request));
            $this->syncItems($offer, $request->validated('items'));
        });

        return to_route('offers.edit', $offer)->with('success', 'Angebot wurde gespeichert.');
    }

    public function destroy(Offer $offer): RedirectResponse
    {
        Gate::authorize('delete', $offer);

        $offer->delete();

        return to_route('offers.index')->with('success', "Angebot {$offer->number} wurde gelöscht.");
    }

    public function download(Offer $offer, OfferPdf $pdf)
    {
        Gate::authorize('view', $offer);

        return $pdf->download($offer);
    }

    /**
     * Email the offer (PDF attached) to the customer, through the queue.
     */
    public function send(Request $request, Offer $offer): RedirectResponse
    {
        Gate::authorize('view', $offer);

        $validated = $request->validate([
            'to' => 'required|email:rfc|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'nullable|string|max:5000',
        ], [
            'to.required' => 'Bitte gib die E-Mail-Adresse des Kunden an.',
            'to.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'subject.required' => 'Bitte gib einen Betreff an.',
        ]);

        // The public demo must not become a way to send mails to any address
        if ($request->user()->isDemo()) {
            return back()->with('error', 'Im Demo-Modus werden keine E-Mails verschickt.');
        }

        if (blank($offer->issuer['email'] ?? $request->user()->businessProfile?->email)) {
            throw ValidationException::withMessages(['to' => 'Hinterlege zuerst im Profil deine Firmen-E-Mail – an sie gehen die Antworten des Kunden.']);
        }

        SendOfferEmail::dispatch($offer, $validated['to'], $validated['subject'], $validated['message'] ?? null);

        return back()->with('success', "Angebot {$offer->number} wird an {$validated['to']} verschickt.");
    }

    /**
     * Mark as sent without emailing it, e.g. handed over in person.
     */
    public function markSent(Offer $offer): RedirectResponse
    {
        Gate::authorize('view', $offer);

        $offer->forceFill(['sent_at' => $offer->sent_at ?? now()])->save();

        return back()->with('success', "Angebot {$offer->number} ist als übergeben markiert.");
    }

    public function accept(Offer $offer): RedirectResponse
    {
        Gate::authorize('view', $offer);

        if ($offer->accepted_at !== null) {
            return back()->with('error', 'Dieses Angebot ist bereits angenommen.');
        }

        $offer->accept();

        return back()->with('success', "Angebot {$offer->number} angenommen – jetzt die Rechnung erstellen.");
    }

    public function decline(Offer $offer): RedirectResponse
    {
        Gate::authorize('view', $offer);

        if ($offer->accepted_at !== null) {
            return back()->with('error', 'Ein angenommenes Angebot erst zurücksetzen.');
        }

        $offer->decline();

        return back()->with('success', "Angebot {$offer->number} ist als abgelehnt markiert.");
    }

    public function reopen(Offer $offer): RedirectResponse
    {
        Gate::authorize('view', $offer);

        if (! $offer->canReopen()) {
            return back()->with('error', 'Das Angebot ist schon verrechnet – erst die Rechnung löschen oder stornieren.');
        }

        $offer->reopen();

        return back()->with('success', 'Angebot ist wieder offen.');
    }

    public function duplicate(Offer $offer): RedirectResponse
    {
        Gate::authorize('view', $offer);

        $copy = $offer->load('items')->duplicate();

        return to_route('offers.edit', $copy)->with('success', "Kopie {$copy->number} erstellt – du kannst sie jetzt bearbeiten.");
    }

    /**
     * @return array<string, mixed>
     */
    private function offerAttributes(OfferRequest $request): array
    {
        $customer = Customer::findOrFail($request->validated('customer_id'));
        $isWebapp = $request->validated('type') === MaintenanceContractType::Webapp->value;

        return [
            ...$request->safe()->only([
                'customer_id', 'type', 'number', 'title', 'url', 'tax_rate', 'package', 'other_function',
                'content_delivery', 'payment_terms', 'maintenance_package', 'maintenance_monthly_price', 'intro', 'notes',
            ]),
            // Dynamic functions and integrations need a backend: only web apps have them
            'features' => $isWebapp ? array_values($request->validated('features', [])) : [],
            'integrations' => $isWebapp ? array_values($request->validated('integrations', [])) : [],
            'reference_customer' => $request->boolean('reference_customer'),
            'offer_date' => Carbon::parse($request->validated('offer_date')),
            'valid_until' => Carbon::parse($request->validated('valid_until')),
            'recipient' => [
                ...$customer->toRecipient(),
                'company' => $customer->company,
                'contact' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'issuer' => $request->user()->businessProfile?->toIssuer() ?? [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Offer $offer, array $items): void
    {
        $offer->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $offer->items()->create([
                'position' => $index + 1,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? null,
                'unit_price' => $item['unit_price'],
                'list_price' => $item['list_price'] ?? null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $user = $request->user();
        $option = fn (array $labels) => collect($labels)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values();

        return [
            'customers' => $user->customers()->orderBy('last_name')->orderBy('company_name')->get()
                ->map(fn (Customer $customer) => [
                    'value' => $customer->id,
                    'label' => $customer->display_name,
                    'recipient' => $customer->toRecipient(),
                ]),
            'catalog' => $user->catalogItems()->where('pricing', '!=', 'time')->orderBy('name')->get(['id', 'name', 'description', 'unit', 'unit_price']),
            'types' => collect(MaintenanceContractType::cases())->map(fn (MaintenanceContractType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => config("offers.types.{$type->value}"),
                'maintenance' => $type->defaultMonthlyPrice(),
                'services' => $type->services(),
            ]),
            'packages' => collect(config('offers.packages'))->map(fn ($package, $key) => ['value' => $key, ...$package])->values(),
            'features' => $option(config('offers.features')),
            'integrations' => $option(config('offers.integrations')),
            'contentDelivery' => $option(config('offers.content_delivery')),
            'paymentTerms' => $option(config('offers.payment_terms')),
            'hints' => config('offers.hints'),
        ];
    }
}
