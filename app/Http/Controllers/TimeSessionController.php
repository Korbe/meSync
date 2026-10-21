<?php

namespace App\Http\Controllers;

use App\Models\TimeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stopwatch for on-site work: start, pause, stop, then bill one or more runs in an invoice.
 */
class TimeSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Stopwatch/Index', [
            'active' => $this->withDetails($user->timeSessions()->whereNull('stopped_at')->latest('id')->limit(1))->first(),
            'unbilled' => $this->withDetails($user->timeSessions()->whereNotNull('stopped_at')->whereNull('invoice_id')->latest('started_at')),
            'billed' => $this->withDetails($user->timeSessions()->whereNotNull('invoice_id')->latest('started_at')->limit(10)),
            'customers' => $user->customers()->orderBy('last_name')->orderBy('company_name')->get()
                ->map(fn ($customer) => ['value' => $customer->id, 'label' => $customer->display_name]),
            'rates' => $user->catalogItems()->where('pricing', 'time')->orderBy('name')->get(),
        ]);
    }

    /**
     * Start a new run. Only one can run (or be paused) at a time.
     */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->timeSessions()->whereNull('stopped_at')->exists()) {
            return back()->with('error', 'Es läuft bereits eine Stoppuhr – erst stoppen.');
        }

        $user->timeSessions()->create([
            ...$this->details($request),
            'catalog_item_id' => $request->input('catalog_item_id') ?: $user->catalogItems()->where('pricing', 'time')->orderBy('name')->value('id'),
            'started_at' => now(),
            'running_since' => now(),
        ]);

        return back();
    }

    public function pause(TimeSession $session): RedirectResponse
    {
        Gate::authorize('update', $session);
        $session->pause();

        return back();
    }

    public function resume(TimeSession $session): RedirectResponse
    {
        Gate::authorize('update', $session);
        $session->resume();

        return back();
    }

    public function stop(TimeSession $session): RedirectResponse
    {
        Gate::authorize('update', $session);
        $session->stop();

        return back()->with('success', 'Stoppuhr gestoppt – der Einsatz steht unter „Noch zu verrechnen“.');
    }

    /**
     * Customer, rate, notes and a corrected duration can be changed until the run is billed.
     */
    public function update(Request $request, TimeSession $session): RedirectResponse
    {
        Gate::authorize('update', $session);

        if ($session->invoice_id) {
            return back()->with('error', 'Dieser Einsatz ist schon verrechnet.');
        }

        $session->update([
            ...$this->details($request),
            ...$request->has('catalog_item_id') ? ['catalog_item_id' => $request->input('catalog_item_id') ?: null] : [],
            ...$request->has('billed_minutes') ? $request->validate(['billed_minutes' => 'nullable|integer|min:1|max:1440']) : [],
        ]);

        return back();
    }

    public function destroy(TimeSession $session): RedirectResponse
    {
        Gate::authorize('delete', $session);
        $session->delete();

        return back()->with('success', 'Einsatz wurde gelöscht.');
    }

    /**
     * Runs with customer, rate and the invoice number. Only the number is loaded for the
     * invoice, so its computed amounts and payment status must not be appended.
     */
    private function withDetails($query): Collection
    {
        return $query
            ->with(['customer:id,title_prefix,first_name,last_name,title_suffix,company_name,company_suffix', 'catalogItem', 'invoice:id,number'])
            ->get()
            ->each(fn (TimeSession $session) => $session->invoice?->setAppends([]));
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Request $request): array
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('user_id', $userId)],
            'catalog_item_id' => ['nullable', Rule::exists('catalog_items', 'id')->where('user_id', $userId)],
            'notes' => 'nullable|string|max:5000',
        ]);

        return array_intersect_key($validated, array_flip(array_filter(['customer_id', 'notes'], fn ($key) => $request->has($key))));
    }
}
