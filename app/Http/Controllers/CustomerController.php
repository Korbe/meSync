<?php

namespace App\Http\Controllers;

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

        $customers = $request->user()->customers()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    foreach (['first_name', 'last_name', 'company_name', 'email', 'phone', 'city', 'uid_number'] as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy('last_name')
            ->orderBy('company_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $request->user()->customers()->create($request->validated());

        return to_route('customers.index')->with('success', 'Kunde wurde angelegt.');
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('update', $customer);

        return Inertia::render('Customers/Update', [
            'customer' => $customer,
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $customer->update($request->validated());

        return to_route('customers.index')->with('success', 'Kunde wurde gespeichert.');
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
