<?php

namespace App\Models;

use App\Enums\MaintenanceContractType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A website or web app for a customer: bundles its offers, invoices and the maintenance
 * contract. The phase (request, offer, implementation, live) follows from those.
 */
class Project extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** File types that can be attached (shown as a grid on the project). */
    public const FILE_TYPES = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    public const PHASES = [
        'request' => 'Anfrage',
        'offer' => 'Angebot',
        'implementation' => 'Umsetzung',
        'live' => 'Live',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'type',
        'name',
        'url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceContractType::class,
            'completed_at' => 'date:Y-m-d',
            'ordered_at' => 'date:Y-m-d',
        ];
    }

    /**
     * Files of the project (briefing, designs, screenshots, contracts). Private, only served
     * through the app.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files')
            ->useDisk('local')
            ->acceptsMimeTypes(self::FILE_TYPES);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class)->latest('offer_date')->latest('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('invoice_date')->latest('id');
    }

    public function maintenanceContract(): HasOne
    {
        return $this->hasOne(MaintenanceContract::class)->latestOfMany();
    }

    /**
     * request | offer | implementation | live. Needs offers and maintenanceContract loaded.
     */
    public function phase(): string
    {
        return match (true) {
            $this->maintenanceContract && ! $this->maintenanceContract->isPending() => 'live',
            $this->offers->contains(fn (Offer $offer) => $offer->accepted_at !== null) || $this->ordered_at !== null => 'implementation',
            $this->offers->isNotEmpty() => 'offer',
            default => 'request',
        };
    }

    /**
     * Every offer was declined or ran out: the project is likely lost.
     */
    public function isLost(): bool
    {
        return $this->phase() === 'offer'
            && $this->offers->every(fn (Offer $offer) => in_array($offer->status, ['declined', 'expired'], true));
    }

    /**
     * Where the project stands and what to do: label (the situation), hint (details),
     * action (button text) and url; method "complete" opens the completion dialog instead.
     *
     * @return array<string, string|null>
     */
    public function nextStep(): array
    {
        $contract = $this->maintenanceContract;
        $accepted = $this->offers->first(fn (Offer $offer) => $offer->accepted_at !== null);
        $open = $this->offers->first(fn (Offer $offer) => in_array($offer->status, ['draft', 'sent'], true));
        $step = fn (string $label, string $hint, string $action, string $url) => compact('label', 'hint', 'action', 'url');

        return match ($this->phase()) {
            'request' => $step('Angebot schreiben', 'Noch kein Angebot', 'Angebot schreiben', route('offers.create', ['project' => $this->id])),
            'offer' => match (true) {
                $open?->status === 'draft' => $step('Angebot an den Kunden senden', "{$open->number} ist erstellt, aber noch nicht verschickt", 'Angebot öffnen', route('offers.edit', $open)),
                $open !== null => $step('Warten auf Antwort', "{$open->number} verschickt, gültig bis {$open->valid_until->format('d.m.Y')}", 'Zusage oder Absage eintragen', route('offers.edit', $open)),
                default => $step('Neues Angebot schreiben', 'Abgelehnt oder abgelaufen', 'Neues Angebot', route('offers.create', ['project' => $this->id])),
            },
            // Ordered without an offer in meSync: work, project invoice, maintenance contract
            'implementation' => $accepted === null ? $this->stepWithoutOffer() : match (true) {
                // Accepted: the actual work, the project invoice (before it with prepayment, after it
                // when billed on completion), then the go-live
                $accepted->invoice_id === null && ! $accepted->billsOnCompletion() => $step('Projektrechnung erstellen', "{$accepted->number} angenommen – Vorauskasse laut Angebot", 'Projektrechnung erstellen', route('invoices.create', ['offer' => $accepted->id])),
                $this->completed_at === null => [
                    ...$step('Projekt umsetzen', $accepted->invoice_id ? 'Projektrechnung ist raus – jetzt die eigentliche Arbeit' : "{$accepted->number} angenommen – jetzt die eigentliche Arbeit", 'Umsetzung abschließen', route('projects.complete', $this->id)),
                    'method' => 'complete',
                ],
                $accepted->invoice_id === null => $step('Projektrechnung erstellen', 'Umsetzung abgeschlossen am '.$this->completed_at->format('d.m.Y'), 'Projektrechnung erstellen', route('invoices.create', ['offer' => $accepted->id])),
                default => $step('Go-Live', 'Umsetzung abgeschlossen – sobald die Seite live ist, den Vertrag starten', 'Vertrag starten', $contract ? route('contracts.edit', $contract) : route('offers.edit', $accepted)),
            },
            'live' => in_array($contract->status, ['overdue', 'due_today', 'due_soon'], true)
                ? $step('Wartungsrechnung fällig', 'Fällig am '.$contract->next_invoice_date->format('d.m.Y'), 'Wartungsrechnung erstellen', route('invoices.create', ['contract' => $contract->id]))
                : $step('Wartung läuft', $contract->status === 'ended' ? 'Vertrag beendet' : 'Nächste Rechnung am '.$contract->next_invoice_date->format('d.m.Y'), 'Vertrag öffnen', route('contracts.edit', $contract)),
        };
    }

    /**
     * Next step of a project that was ordered without an offer (own contract, by phone or mail).
     *
     * @return array<string, string|null>
     */
    private function stepWithoutOffer(): array
    {
        $contract = $this->maintenanceContract;
        $step = fn (string $label, string $hint, string $action, string $url) => compact('label', 'hint', 'action', 'url');

        return match (true) {
            $this->completed_at === null => [
                ...$step('Projekt umsetzen', 'Beauftragt am '.$this->ordered_at->format('d.m.Y').' – jetzt die eigentliche Arbeit', 'Umsetzung abschließen', route('projects.complete', $this->id)),
                'method' => 'complete',
            ],
            ! $this->invoices()->whereNull('maintenance_contract_id')->exists() => $step('Projektrechnung erstellen', 'Umsetzung abgeschlossen am '.$this->completed_at->format('d.m.Y'), 'Projektrechnung erstellen', route('invoices.create', ['project' => $this->id])),
            $contract === null => $step('Wartungsvertrag anlegen', 'Projektrechnung ist raus – die Wartung fehlt noch', 'Wartungsvertrag anlegen', route('contracts.create', ['project' => $this->id])),
            default => $step('Go-Live', 'Sobald die Seite live ist, den Vertrag starten', 'Vertrag starten', route('contracts.edit', $contract)),
        };
    }
}
