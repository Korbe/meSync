<?php

namespace App\Http\Middleware;

use App\Enums\CustomerInterest;
use App\Models\MaintenanceContract;
use App\Models\User;
use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            // Logo with light lettering for mails in dark mode, only needed on the profile page
            'logoDarkUrl' => fn () => $request->routeIs('profile.show')
                ? ($request->user()?->getFirstMediaUrl('logo_dark') ?: null)
                : null,
            // Signature is private: shown through its own route, with the media id against stale caches
            'signatureUrl' => fn () => $request->routeIs('profile.show') && ($signature = $request->user()?->getFirstMedia('signature'))
                ? route('profile.signature.show', ['v' => $signature->id])
                : null,
            // Terms and privacy policy (private PDFs), only needed on the profile page
            'documents' => fn () => $request->routeIs('profile.show') && $request->user()
                ? collect(User::DOCUMENTS)->map(fn ($label, $collection) => ($media = $request->user()->getFirstMedia($collection))
                    ? ['name' => $media->file_name, 'size' => $media->size, 'updated_at' => $media->updated_at]
                    : null)
                : null,
            // Sender / bank details for invoices, only needed on the profile page
            'businessProfile' => fn () => $request->routeIs('profile.show')
                ? $request->user()?->businessProfile
                : null,
            // Session timeout popup: lifetime and warning in minutes. With "remember me" an
            // expired session is restored on the next request, so there is nothing to warn about.
            'session' => fn () => $request->user() ? [
                'lifetime' => (int) config('session.lifetime'),
                'warning' => (int) config('session.warning'),
                'remembered' => $request->hasCookie(Auth::guard('web')->getRecallerName()),
            ] : null,
            // Interests to pick when creating a customer, also in the quick dialog of other forms
            'customerInterests' => fn () => $request->user() ? CustomerInterest::options() : null,
            // Badge in the sidebar: overdue + due soon maintenance invoices
            'dueContractsCount' => fn () => $request->user()?->maintenanceContracts()
                ->billable()
                ->where('next_invoice_date', '<=', today()->addDays(MaintenanceContract::DUE_SOON_DAYS))
                ->count() ?? 0,
            // Shared demo account: profile is read only (see ProtectDemoProfile)
            'isDemo' => fn () => (bool) $request->user()?->isDemo(),
            // Maintenance invoices due today: highlighted in the sidebar
            'dueTodayCount' => fn () => $request->user()?->maintenanceContracts()
                ->billable()
                ->whereDate('next_invoice_date', today())
                ->count() ?? 0,
            // Stopwatch indicator in the header (running or paused)
            'activeTimer' => fn () => $request->user()?->timeSessions()
                ->whereNull('stopped_at')
                ->with('customer:id,title_prefix,first_name,last_name,title_suffix,company_name,company_suffix')
                ->latest('id')
                ->first()
                ?->only(['id', 'status', 'elapsed_seconds', 'running_since', 'customer']),
            // Red dot on "Monitoring" in the sidebar
            'sitesDownCount' => fn () => $request->user()?->monitors()
                ->active()
                ->where('status', 'down')
                ->count() ?? 0,
        ]);
    }
}
