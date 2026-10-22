<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CatalogItemController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Catalog/Index', [
            'items' => $request->user()->catalogItems()->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Catalog/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->catalogItems()->create($this->validated($request));

        // Created from the invoice form: stay there, it inserts the new item
        if ($request->boolean('inline')) {
            return back()->with('success', 'Leistung wurde angelegt.');
        }

        return to_route('catalog.index')->with('success', 'Leistung wurde angelegt.');
    }

    public function edit(CatalogItem $catalog): Response
    {
        Gate::authorize('update', $catalog);

        return Inertia::render('Catalog/Update', [
            'item' => $catalog,
        ]);
    }

    public function update(Request $request, CatalogItem $catalog): RedirectResponse
    {
        Gate::authorize('update', $catalog);

        $catalog->update($this->validated($request));

        return to_route('catalog.index')->with('success', 'Leistung wurde gespeichert.');
    }

    /**
     * Show the delete confirmation page.
     */
    public function delete(CatalogItem $catalog): Response
    {
        Gate::authorize('delete', $catalog);

        return Inertia::render('Catalog/Delete', [
            'item' => $catalog,
        ]);
    }

    public function destroy(CatalogItem $catalog): RedirectResponse
    {
        Gate::authorize('delete', $catalog);

        $catalog->delete();

        return to_route('catalog.index')->with('success', 'Leistung wurde gelöscht.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'pricing' => $request->input('pricing', 'fixed'),
            'unit_price' => str_replace(',', '.', (string) $request->input('unit_price')),
            'step_price' => $request->filled('step_price') ? str_replace(',', '.', (string) $request->input('step_price')) : null,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'unit' => 'nullable|string|max:30',
            'unit_price' => 'required|numeric|min:-999999|max:999999',
            'pricing' => 'required|in:fixed,time',
            'base_minutes' => 'required_if:pricing,time|nullable|integer|min:0|max:1440',
            'step_minutes' => 'required_if:pricing,time|nullable|integer|min:1|max:1440',
            'step_price' => 'required_if:pricing,time|nullable|numeric|min:0|max:999999',
        ], [
            'name.required' => 'Bitte gib einen Namen an.',
            'unit_price.required' => 'Bitte gib einen Preis an.',
            'unit_price.numeric' => 'Bitte gib einen gültigen Preis an.',
            'base_minutes.required_if' => 'Bitte gib an, wie viele Minuten der Grundpreis abdeckt.',
            'step_minutes.required_if' => 'Bitte gib den Takt in Minuten an.',
            'step_price.required_if' => 'Bitte gib den Preis pro Takt an.',
        ]);

        // Fixed prices don't need the time fields
        if ($validated['pricing'] === 'fixed') {
            $validated = [...$validated, 'base_minutes' => null, 'step_minutes' => null, 'step_price' => null];
        }

        return $validated;
    }
}
