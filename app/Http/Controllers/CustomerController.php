<?php

namespace App\Http\Controllers;

use App\Enums\CustomerInterest;
use App\Http\Requests\Customer\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $interest = CustomerInterest::tryFrom((string) $request->query('interest'));

        $customers = $request->user()->customers()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    foreach (['first_name', 'last_name', 'company_name', 'email', 'phone', 'city', 'uid_number'] as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->when($interest, fn ($query) => $query->whereJsonContains('interests', $interest->value))
            ->orderBy('last_name')
            ->orderBy('company_name')
            ->paginate($this->perPage($request, 'customers'))
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'search' => $search,
            'interest' => $interest?->value,
            'interests' => CustomerInterest::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create', [
            'interests' => CustomerInterest::options(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $request->user()->customers()->create($request->validated());

        // Created from the invoice form: stay there, it selects the new customer
        if ($request->boolean('inline')) {
            return back()->with('success', 'Kunde wurde angelegt.');
        }

        return to_route('customers.index')->with('success', 'Kunde wurde angelegt.');
    }

    /**
     * Customer file: contact details, contracts, invoices and revenue at a glance.
     */
    public function show(Customer $customer): Response
    {
        Gate::authorize('update', $customer);

        $invoices = $customer->invoices()
            ->with(['items', 'payments', 'cancels:id,number', 'cancellation:id,number,cancels_invoice_id'])
            ->latest('invoice_date')
            ->latest('id')
            ->get();

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'contracts' => $customer->maintenanceContracts()->orderBy('next_invoice_date')->get(),
            'invoices' => $invoices,
            'interests' => CustomerInterest::options(),
            'stats' => [
                // Cancellations are negative, so cancelled invoices cancel out automatically
                'revenue' => round($invoices->sum('subtotal'), 2),
                'outstanding' => round($invoices->sum('outstanding'), 2),
                'overdue' => $invoices->where('payment_status', 'overdue')->count(),
            ],
        ]);
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('update', $customer);

        return Inertia::render('Customers/Update', [
            'customer' => $customer,
            'interests' => CustomerInterest::options(),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $customer->update($request->validated());

        return to_route('customers.show', $customer)->with('success', 'Kunde wurde gespeichert.');
    }

    /**
     * Show the delete confirmation page.
     */
    public function delete(Customer $customer): Response
    {
        Gate::authorize('delete', $customer);

        return Inertia::render('Customers/Delete', [
            'customer' => $customer->loadCount('maintenanceContracts'),
        ]);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return to_route('customers.index')->with('success', 'Kunde wurde gelöscht.');
    }
}
