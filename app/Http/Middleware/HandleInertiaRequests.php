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
            // Company logo, only needed on the profile page
            'logoUrl' => fn () => $request->routeIs('profile.show')
                ? ($request->user()?->getFirstMediaUrl('logo') ?: null)
                : null,
            // Sender / bank details for invoices, only needed on the profile page
            'businessProfile' => fn () => $request->routeIs('profile.show')
                ? $request->user()?->businessProfile
                : null,
            // Badge in the sidebar: overdue + due soon maintenance invoices
            'dueContractsCount' => fn () => $request->user()?->maintenanceContracts()
                ->billable()
                ->where('next_invoice_date', '<=', today()->addDays(MaintenanceContract::DUE_SOON_DAYS))
                ->count() ?? 0,
            // Shared demo account: profile is read only (see ProtectDemoProfile)
            'isDemo' => fn () => (bool) $request->user()?->isDemo(),
            // Red dot on "Monitoring" in the sidebar
            'sitesDownCount' => fn () => $request->user()?->maintenanceContracts()
                ->monitored()
                ->where('monitor_status', 'down')
                ->count() ?? 0,
        ]);
    }
}
