<?php

namespace App\Services;

use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteVisitService
{
    /**
     * Record a public page visit (deduped per IP + path + day).
     *
     * @return array{recorded: bool, today: int, month: int}
     */
    public function track(Request $request, ?string $path = null): array
    {
        $path = $this->normalizePath($path ?? $request->input('path'));
        $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
        $sessionKey = $this->sessionKey($request);
        $today = now()->toDateString();

        try {
            SiteVisit::query()->firstOrCreate(
                [
                    'ip_hash' => $ipHash,
                    'path' => $path,
                    'visited_on' => $today,
                ],
                [
                    'session_key' => $sessionKey,
                ],
            );
        } catch (\Throwable) {
            // Unique race — ignore.
        }

        return [
            'recorded' => true,
            ...$this->dashboardCounts(),
        ];
    }

    /**
     * @return array{today: int, yesterday: int, month: int, total: int}
     */
    public function dashboardCounts(): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        return [
            'today' => $this->uniqueVisitorsOn($today),
            'yesterday' => $this->uniqueVisitorsOn($yesterday),
            'month' => $this->uniqueVisitorsSince($monthStart),
            'total' => $this->uniqueVisitorsSince(null),
        ];
    }

    private function uniqueVisitorsOn(string $date): int
    {
        return (int) SiteVisit::query()
            ->whereDate('visited_on', $date)
            ->selectRaw('COUNT(DISTINCT ip_hash) as aggregate')
            ->value('aggregate');
    }

    private function uniqueVisitorsSince(?string $fromDate): int
    {
        $query = SiteVisit::query()->selectRaw('COUNT(DISTINCT ip_hash) as aggregate');

        if ($fromDate) {
            $query->where('visited_on', '>=', $fromDate);
        }

        return (int) $query->value('aggregate');
    }

    private function normalizePath(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $path = parse_url($path, PHP_URL_PATH) ?: '/';

        return Str::limit($path, 500, '');
    }

    private function sessionKey(Request $request): string
    {
        $raw = (string) ($request->input('session_key') ?: $request->ip().'|'.$request->userAgent());

        return hash('sha256', $raw);
    }
}
