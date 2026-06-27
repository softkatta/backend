<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class BillingAdminService
{
    public function deletePayment(Payment $payment): void
    {
        $payment = Payment::withoutGlobalScopes()->findOrFail($payment->id);
        $this->permanentlyDelete($payment);
    }

    public function deleteInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice = Invoice::withoutGlobalScopes()->findOrFail($invoice->id);

            Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->get()
                ->each(fn (Payment $payment) => $this->permanentlyDelete($payment));

            $invoice->items()->get()->each(fn (Model $item) => $this->permanentlyDelete($item));
            $this->permanentlyDelete($invoice);
        });
    }

    public function deleteOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::withoutGlobalScopes()->findOrFail($order->id);

            Payment::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->get()
                ->each(fn (Payment $payment) => $this->permanentlyDelete($payment));

            $invoices = Invoice::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->get();

            foreach ($invoices as $invoice) {
                $this->deleteInvoice($invoice);
            }

            $this->permanentlyDelete($order);
        });
    }

    public function deleteSubscription(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $subscription = Subscription::withoutGlobalScopes()->findOrFail($subscription->id);

            Invoice::withoutGlobalScopes()
                ->where('subscription_id', $subscription->id)
                ->update(['subscription_id' => null]);

            $this->permanentlyDelete($subscription);
        });
    }

    private function permanentlyDelete(Model $model): void
    {
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $model->forceDelete();

            return;
        }

        $model->delete();
    }
}
