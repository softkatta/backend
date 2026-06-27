<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Services\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $security = app(SecurityService::class);
        $restrictToDemoTenant = $security->isDemoAccount($user) && ! empty($user->tenant_id);

        $allSubscriptionsQuery = Subscription::withoutGlobalScope('tenant')
            ->with(['product', 'plan'])
            ->where('user_id', $user->id);

        if ($restrictToDemoTenant) {
            $allSubscriptionsQuery->where('tenant_id', $user->tenant_id);
        }

        $allSubscriptions = $allSubscriptionsQuery->get();

        $activeSubscriptions = $allSubscriptions
            ->whereIn('status', ['active', 'expiring_soon'])
            ->values();

        $ordersQuery = Order::withoutGlobalScope('tenant')
            ->where('user_id', $user->id)
            ->where('status', 'completed');

        if ($restrictToDemoTenant) {
            $ordersQuery->where('tenant_id', $user->tenant_id);
        }

        $orderedProductIds = $ordersQuery->pluck('product_id')
            ->unique()
            ->values();

        $purchasedProductIds = $allSubscriptions
            ->pluck('product_id')
            ->merge($orderedProductIds)
            ->filter()
            ->unique()
            ->values();

        $purchasedProducts = Product::query()
            ->whereIn('id', $purchasedProductIds)
            ->get()
            ->values();

        $recentInvoices = Invoice::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $openTickets = SupportTicket::where('user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress', 'waiting_on_client'])
            ->count();

        $unreadNotifications = $user->platformNotifications()
            ->whereNull('read_at')
            ->count();

        return $this->success([
            'user' => $user->load('tenant'),
            'active_subscriptions' => $activeSubscriptions,
            'purchased_products' => $purchasedProducts,
            'recent_invoices' => $recentInvoices,
            'open_tickets' => $openTickets,
            'unread_notifications' => $unreadNotifications,
        ]);
    }
}
