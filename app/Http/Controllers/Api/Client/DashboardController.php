<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeSubscriptions = Subscription::with(['product', 'plan'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'expiring_soon'])
            ->get();

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
            'recent_invoices' => $recentInvoices,
            'open_tickets' => $openTickets,
            'unread_notifications' => $unreadNotifications,
        ]);
    }
}
