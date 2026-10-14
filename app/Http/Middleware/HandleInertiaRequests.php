<?php

namespace App\Http\Middleware;

use App\Models\MaintenanceContract;
use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'flash' => [
                'success' => Session::get('success'),
                'error' => Session::get('error'),
            ],
            // Badge in the sidebar: overdue + due soon maintenance invoices
            'dueContractsCount' => fn () => $request->user()?->maintenanceContracts()
                ->billable()
                ->where('next_invoice_date', '<=', today()->addDays(MaintenanceContract::DUE_SOON_DAYS))
                ->count() ?? 0,
        ]);
    }
}
