<?php

namespace App\Services;

use App\Enums\ChatbotLeadStatus;
use App\Models\ChatbotConversation;
use App\Models\ChatbotLead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChatbotAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $totalConversations = ChatbotConversation::query()->distinct('session_id')->count('session_id');
        $totalLeads = ChatbotLead::query()->count();
        $convertedLeads = ChatbotLead::query()->where('status', ChatbotLeadStatus::Converted)->count();
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0.0;

        return [
            'total_conversations' => $totalConversations,
            'total_leads' => $totalLeads,
            'conversion_rate' => $conversionRate,
            'most_asked_questions' => $this->topQuestions(5),
            'daily_chats' => $this->dailyConversations(7),
            'recent_conversations' => ChatbotConversation::query()
                ->latest('created_at')
                ->limit(10)
                ->get(['id', 'session_id', 'visitor_name', 'message', 'response', 'language', 'created_at']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        return [
            'daily_conversations' => $this->dailyConversations(30),
            'top_questions' => $this->topQuestions(10),
            'lead_conversion' => $this->leadConversionChart(),
            'device_statistics' => $this->deviceStatistics(),
            'language_statistics' => $this->languageStatistics(),
        ];
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    private function dailyConversations(int $days): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = ChatbotConversation::query()
            ->selectRaw('DATE(created_at) as day, COUNT(DISTINCT session_id) as total')
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $map = $rows->pluck('total', 'day');

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $result[] = ['date' => $day, 'count' => (int) ($map[$day] ?? 0)];
        }

        return $result;
    }

    /**
     * @return list<array{message: string, count: int}>
     */
    private function topQuestions(int $limit): array
    {
        return ChatbotConversation::query()
            ->select('message', DB::raw('COUNT(*) as total'))
            ->groupBy('message')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => ['message' => (string) $row->message, 'count' => (int) $row->total])
            ->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function leadConversionChart(): array
    {
        return ChatbotLead::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status instanceof ChatbotLeadStatus ? $row->status->label() : (string) $row->status,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return list<array{device: string, count: int}>
     */
    private function deviceStatistics(): array
    {
        return ChatbotConversation::query()
            ->selectRaw("CASE WHEN user_agent LIKE '%Mobile%' THEN 'Mobile' WHEN user_agent LIKE '%Tablet%' THEN 'Tablet' ELSE 'Desktop' END as device, COUNT(DISTINCT session_id) as total")
            ->groupBy('device')
            ->get()
            ->map(fn ($row): array => ['device' => (string) $row->device, 'count' => (int) $row->total])
            ->all();
    }

    /**
     * @return list<array{language: string, count: int}>
     */
    private function languageStatistics(): array
    {
        return ChatbotConversation::query()
            ->select('language', DB::raw('COUNT(DISTINCT session_id) as total'))
            ->groupBy('language')
            ->get()
            ->map(fn ($row): array => ['language' => (string) $row->language, 'count' => (int) $row->total])
            ->all();
    }
}
