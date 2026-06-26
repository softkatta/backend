<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class BillingAdminService
{
    public function deletePayment(Payment $payment): void
    {
        Payment::withoutGlobalScopes()->whereKey($payment->id)->delete();
    }

    public function deleteInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice = Invoice::withoutGlobalScopes()->findOrFail($invoice->id);

            Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->delete();

            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    public function deleteOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::withoutGlobalScopes()->findOrFail($order->id);

            Payment::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->delete();

            $invoices = Invoice::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->get();

            foreach ($invoices as $invoice) {
                $this->deleteInvoice($invoice);
            }

            $order->delete();
        });
    }

    public function deleteSubscription(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $subscription = Subscription::withoutGlobalScopes()->findOrFail($subscription->id);

            Invoice::withoutGlobalScopes()
                ->where('subscription_id', $subscription->id)
                ->update(['subscription_id' => null]);

            $subscription->delete();
        });
    }
}
