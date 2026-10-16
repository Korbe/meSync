<?php

namespace App\Http\Controllers;

use App\Enums\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryItemController extends Controller
{
    /**
     * Paginated list, filtered by status, category and search in the database. The totals
     * cover all items (the book value comes from the depreciation, so it's summed in PHP
     * from a lean query without files).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $status = in_array($request->query('status'), ['active', 'retired', 'all'], true) ? $request->query('status') : 'active';
        $category = InventoryCategory::tryFrom((string) $request->query('category'));
        $search = trim((string) $request->query('search', ''));

        $items = $user->inventoryItems()
            ->withExists(['media as has_receipt' => fn ($query) => $query->where('collection_name', 'receipt')])
            ->when($status === 'active', fn ($query) => $query->where('status', 'active'))
            ->when($status === 'retired', fn ($query) => $query->where('status', '!=', 'active'))
            ->when($category, fn ($query) => $query->where('category', $category->value))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")
                ->orWhere('supplier', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%")))
            ->latest('purchased_at')
            ->latest('id')
            ->paginate($this->perPage($request, 'inventory'))
            ->withQueryString();

        $all = $user->inventoryItems()->get(['id', 'price', 'tax_rate', 'purchased_at', 'useful_life_years', 'status', 'retired_at', 'category']);
        $active = $all->filter->isActive();
        $used = $all->pluck('category')->map(fn ($category) => $category->value)->unique();

        return Inertia::render('Inventory/Index', [
            'items' => $items,
            'filters' => ['status' => $status, 'category' => $category?->value ?? '', 'search' => $search],
            'hasItems' => $all->isNotEmpty(),
            'categories' => collect(InventoryCategory::options())->whereIn('value', $used)->values(),
            'totals' => [
                'count' => $active->count(),
                'price' => round($active->sum(fn ($item) => (float) $item->price), 2),
                'book_value' => round($active->sum('book_value'), 2),
                'bought_this_year' => round($all->filter(fn ($item) => $item->purchased_at->isCurrentYear())->sum(fn ($item) => (float) $item->price), 2),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Create', [
            'categories' => InventoryCategory::options(),
            'gwgLimit' => InventoryItem::GWG_LIMIT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = $request->user()->inventoryItems()->create($this->validated($request));
        $this->syncReceipt($request, $item);

        return to_route('inventory.index')->with('success', "„{$item->name}“ wurde ins Inventar aufgenommen.");
    }

    public function edit(InventoryItem $inventory): Response
    {
        Gate::authorize('update', $inventory);

        $receipt = $inventory->getFirstMedia('receipt');

        return Inertia::render('Inventory/Update', [
            'item' => $inventory,
            'receipt' => $receipt ? ['name' => $receipt->file_name, 'size' => $receipt->size, 'mime' => $receipt->mime_type] : null,
            'schedule' => $inventory->depreciationSchedule(),
            'categories' => InventoryCategory::options(),
            'gwgLimit' => InventoryItem::GWG_LIMIT,
        ]);
    }

    public function update(Request $request, InventoryItem $inventory): RedirectResponse
    {
        Gate::authorize('update', $inventory);

        $inventory->update($this->validated($request));
        $this->syncReceipt($request, $inventory);

        return to_route('inventory.edit', $inventory)->with('success', 'Gegenstand wurde gespeichert.');
    }

    /**
     * Show the delete confirmation page.
     */
    public function delete(InventoryItem $inventory): Response
    {
        Gate::authorize('delete', $inventory);

        return Inertia::render('Inventory/Delete', [
            'item' => $inventory,
        ]);
    }

    public function destroy(InventoryItem $inventory): RedirectResponse
    {
        Gate::authorize('delete', $inventory);

        $inventory->delete();

        return to_route('inventory.index')->with('success', 'Gegenstand wurde gelöscht.');
    }

    /**
     * The receipt is stored privately, so it is only served through this route.
     */
    public function receipt(InventoryItem $inventory)
    {
        Gate::authorize('view', $inventory);

        $media = $inventory->getFirstMedia('receipt');
        abort_unless($media, 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
        ]);
    }

    /**
     * Inventory list (Anlagenverzeichnis) as CSV for the accountant, Excel friendly.
     */
    public function csv(Request $request)
    {
        $year = today()->year;
        $money = fn ($value) => $value === null ? '' : number_format((float) $value, 2, ',', '');
        $rows = [[
            'Bezeichnung', 'Kategorie', 'Seriennummer', 'Händler', 'Kaufdatum', 'Netto', 'USt. %', 'Brutto',
            'Nutzungsdauer', 'GWG', "AfA {$year}", "Buchwert 31.12.{$year}", 'Status', 'Ausgeschieden am', 'Verkaufserlös', 'Notizen',
        ]];

        foreach ($request->user()->inventoryItems()->orderBy('purchased_at')->get() as $item) {
            $afa = collect($item->depreciationSchedule())->firstWhere('year', $year)['depreciation'] ?? 0;

            $rows[] = [
                $item->name, $item->category_label, $item->serial_number, $item->supplier,
                $item->purchased_at->format('d.m.Y'), $money($item->price), $money($item->tax_rate), $money($item->gross_price),
                $item->is_gwg ? '' : $item->useful_life_years, $item->is_gwg ? 'ja' : 'nein', $money($afa), $money($item->book_value),
                ['active' => 'In Verwendung', 'sold' => 'Verkauft', 'disposed' => 'Ausgeschieden'][$item->status] ?? $item->status,
                $item->retired_at?->format('d.m.Y'), $money($item->sale_price), $item->notes,
            ];
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->streamDownload(fn () => print($csv), "inventar_{$year}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        foreach (['price', 'sale_price', 'tax_rate'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => str_replace(',', '.', (string) $request->input($field))]);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::enum(InventoryCategory::class)],
            'serial_number' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'purchased_at' => 'required|date',
            'price' => 'required|numeric|min:0|max:9999999',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'useful_life_years' => 'nullable|integer|min:1|max:50',
            'status' => 'required|in:active,sold,disposed',
            'retired_at' => 'required_unless:status,active|nullable|date|after_or_equal:purchased_at',
            'sale_price' => 'nullable|numeric|min:0|max:9999999',
            'notes' => 'nullable|string|max:5000',
            'receipt' => 'nullable|file|mimes:pdf,png,jpg,jpeg,webp|max:10240',
            'remove_receipt' => 'nullable|boolean',
        ], [
            'name.required' => 'Bitte gib eine Bezeichnung an.',
            'purchased_at.required' => 'Bitte gib das Kaufdatum an.',
            'price.required' => 'Bitte gib den Kaufpreis an.',
            'price.numeric' => 'Bitte gib einen gültigen Preis an.',
            'retired_at.required_unless' => 'Bitte gib an, wann der Gegenstand ausgeschieden ist.',
            'retired_at.after_or_equal' => 'Das Datum muss nach dem Kaufdatum liegen.',
            'receipt.mimes' => 'Erlaubt sind PDF, PNG, JPG und WebP.',
            'receipt.max' => 'Der Beleg darf maximal 10 MB groß sein.',
        ]);

        // GWG are written off at once and need no depreciation period
        if ((float) $validated['price'] <= InventoryItem::GWG_LIMIT) {
            $validated['useful_life_years'] = null;
        } elseif (empty($validated['useful_life_years'])) {
            $validated['useful_life_years'] = InventoryCategory::from($validated['category'])->usefulLifeYears();
        }

        if ($validated['status'] === 'active') {
            $validated = [...$validated, 'retired_at' => null, 'sale_price' => null];
        } elseif ($validated['status'] === 'disposed') {
            $validated['sale_price'] = null;
        }

        unset($validated['receipt'], $validated['remove_receipt']);

        return $validated;
    }

    private function syncReceipt(Request $request, InventoryItem $item): void
    {
        if ($request->hasFile('receipt')) {
            $item->addMediaFromRequest('receipt')->toMediaCollection('receipt');
        } elseif ($request->boolean('remove_receipt')) {
            $item->clearMediaCollection('receipt');
        }
    }
}
