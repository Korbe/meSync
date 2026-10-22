<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MaintenanceContract;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = today();

        $invoices = $user->invoices()
            ->with(['items', 'payments', 'cancellation:id,cancels_invoice_id'])
            ->get();

        $thisYear = $invoices->filter(fn (Invoice $invoice) => $invoice->invoice_date->year === $today->year);
        $unpaid = $invoices->whereIn('payment_status', ['open', 'partial', 'overdue']);

        // Net revenue per month of the current year (cancellations are negative and cancel out)
        $revenueByMonth = collect(range(1, 12))->map(fn (int $month) => round(
            $thisYear->filter(fn (Invoice $invoice) => $invoice->invoice_date->month === $month)->sum('subtotal'),
            2,
        ));

        $contracts = $user->maintenanceContracts()->with('customer')->billable()->orderBy('next_invoice_date')->get();

        return Inertia::render('Dashboard/Dashboard', [
            'stats' => [
                'revenueMonth' => $revenueByMonth[$today->month - 1],
                'revenueYear' => round($thisYear->sum('subtotal'), 2),
                'outstanding' => round($unpaid->sum('outstanding'), 2),
                'unpaidCount' => $unpaid->count(),
                'overdueCount' => $unpaid->where('payment_status', 'overdue')->count(),
                'recurringYear' => round($contracts->sum('annual_price'), 2),
            ],
            'revenueByMonth' => $revenueByMonth,
            'year' => $today->year,
            'month' => $today->month,
            'dueToday' => $contracts
                ->filter(fn (MaintenanceContract $contract) => $contract->status === 'due_today')
                ->load('customer')
                ->values(),
            'dueContracts' => $contracts
                ->filter(fn (MaintenanceContract $contract) => $contract->next_invoice_date->lte($today->copy()->addDays(MaintenanceContract::DUE_SOON_DAYS)))
                ->values(),
            'openInvoices' => $unpaid->sortBy('due_date')->take(6)->values()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'recipient' => $invoice->recipient['name'] ?? '',
                    'due_date' => $invoice->due_date->toDateString(),
                    'outstanding' => $invoice->outstanding,
                    'payment_status' => $invoice->payment_status,
                ]),
            // Down sites first
            'sites' => $user->monitors()->active()->get()
                ->sortBy(fn (Monitor $monitor) => [$monitor->status === 'down' ? 0 : 1, $monitor->name])
                ->values(),
            'recentCustomers' => $user->customers()->latest()->take(5)->get(),
        ]);
    }
}
