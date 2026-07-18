<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

class ReportService
{
    public function __construct(
        private readonly SiteVisitService $visits,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'tenants' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('status', 'active')->count(),
            ],
            'users' => [
                'total' => User::where('role', 'client')->count(),
                'active' => User::where('role', 'client')->where('is_active', true)->count(),
            ],
            'subscriptions' => [
                'total' => Subscription::count(),
                'active' => Subscription::where('status', SubscriptionStatus::Active)->count(),
                'expiring_soon' => Subscription::where('status', SubscriptionStatus::ExpiringSoon)->count(),
            ],
            'revenue' => [
                'total_orders' => Order::whereBetween('created_at', [$from, $to])->sum('total_amount'),
                'paid_invoices' => Invoice::where('status', InvoiceStatus::Paid)
                    ->whereBetween('paid_at', [$from, $to])
                    ->sum('total_amount'),
                'pending_invoices' => Invoice::whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
                    ->sum('total_amount'),
            ],
            'support' => [
                'open_tickets' => SupportTicket::where('status', 'open')->count(),
                'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            ],
            'visitors' => $this->visits->dashboardCounts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revenueReport(Carbon $from, Carbon $to): array
    {
        $payments = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('gateway, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('gateway')
            ->get();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'by_gateway' => $payments,
            'total' => $payments->sum('total'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptionReport(): array
    {
        return Subscription::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * @return array<int, array{name: string, value: int}>
     */
    public function productDistribution(): array
    {
        return Subscription::query()
            ->with('product:id,name')
            ->where('status', SubscriptionStatus::Active)
            ->get()
            ->groupBy(fn (Subscription $s) => $s->product?->name ?? 'Other')
            ->map(fn ($group, $name) => ['name' => $name, 'value' => $group->count()])
            ->values()
            ->sortByDesc('value')
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{name: string, customers: int}>
     */
    public function customersByRegion(): array
    {
        $regionExpression = "COALESCE(NULLIF(state, ''), 'Others')";

        return User::query()
            ->where('role', 'client')
            ->selectRaw("{$regionExpression} as name, COUNT(*) as customers")
            ->groupByRaw($regionExpression)
            ->orderByDesc('customers')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'customers' => (int) $row->customers])
            ->toArray();
    }
}
