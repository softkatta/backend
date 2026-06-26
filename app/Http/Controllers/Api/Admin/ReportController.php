<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends BaseApiController
{
    public function dashboard(ReportService $service): JsonResponse
    {
        return $this->success($service->dashboard());
    }

    public function revenue(Request $request, ReportService $service): JsonResponse
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to = Carbon::parse($request->input('to', now()->endOfMonth()));

        return $this->success($service->revenueReport($from, $to));
    }

    public function subscriptions(ReportService $service): JsonResponse
    {
        return $this->success($service->subscriptionReport());
    }

    public function products(ReportService $service): JsonResponse
    {
        return $this->success([
            'distribution' => $service->productDistribution(),
            'regions' => $service->customersByRegion(),
        ]);
    }

    public function export(Request $request, ReportService $service): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to = Carbon::parse($request->input('to', now()->endOfMonth()));
        $dashboard = $service->dashboard($from, $to);
        $revenue = $service->revenueReport($from, $to);
        $filename = 'softkatta-report-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($dashboard, $revenue, $from, $to): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['SoftKatta Report', $from->toDateString(), 'to', $to->toDateString()]);
            fputcsv($out, []);
            fputcsv($out, ['Metric', 'Value']);
            fputcsv($out, ['Total Tenants', $dashboard['tenants']['total'] ?? 0]);
            fputcsv($out, ['Active Tenants', $dashboard['tenants']['active'] ?? 0]);
            fputcsv($out, ['Total Clients', $dashboard['users']['total'] ?? 0]);
            fputcsv($out, ['Active Clients', $dashboard['users']['active'] ?? 0]);
            fputcsv($out, ['Active Subscriptions', $dashboard['subscriptions']['active'] ?? 0]);
            fputcsv($out, ['Order Revenue', $dashboard['revenue']['total_orders'] ?? 0]);
            fputcsv($out, ['Paid Invoices', $dashboard['revenue']['paid_invoices'] ?? 0]);
            fputcsv($out, ['Open Tickets', $dashboard['support']['open_tickets'] ?? 0]);
            fputcsv($out, []);
            fputcsv($out, ['Revenue by Gateway', 'Count', 'Total']);
            foreach ($revenue['by_gateway'] ?? [] as $row) {
                fputcsv($out, [$row->gateway ?? 'unknown', $row->count ?? 0, $row->total ?? 0]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
