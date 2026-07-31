<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotification;

class NotificationSummaryService
{
    public function __construct(private readonly NotificationQueryService $queryService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryFor(User $user, int $recentLimit = 8): array
    {
        $base = UserNotification::query()
            ->where('recipient_user_id', $user->id);

        $recent = (clone $base)
            ->with('triggeredBy:id,name,email')
            ->where('status', '!=', 'archived')
            ->orderByRaw("case when status = 'unread' then 0 when status = 'read' then 1 else 2 end")
            ->orderByDesc('created_at')
            ->limit($recentLimit)
            ->get();

        $counts = [
            'total' => (clone $base)->count(),
            'unread' => (clone $base)->where('status', 'unread')->count(),
            'read' => (clone $base)->where('status', 'read')->count(),
            'archived' => (clone $base)->where('status', 'archived')->count(),
            'critical_unread' => (clone $base)->where('status', 'unread')->where('severity', 'critical')->count(),
        ];

        return [
            'source' => 'current-records',
            'generated_at' => now()->toISOString(),
            'scope' => [
                'recipient_user_id' => $user->id,
                'recipient_email' => $user->email,
                'company_id' => $user->company_id,
            ],
            'unread' => $counts['unread'],
            'read' => $counts['read'],
            'archived' => $counts['archived'],
            'critical_unread' => $counts['critical_unread'],
            'counts' => $counts,
            'by_category' => (clone $base)
                ->selectRaw('category, count(*) as row_count')
                ->groupBy('category')
                ->orderBy('category')
                ->get()
                ->map(fn ($row): array => [
                    'category' => $row->category,
                    'count' => (int) $row->row_count,
                ])
                ->values()
                ->all(),
            'category_counts' => (clone $base)
                ->selectRaw('category, count(*) as row_count')
                ->groupBy('category')
                ->orderBy('category')
                ->get()
                ->map(fn ($row): array => [
                    'category' => $row->category,
                    'count' => (int) $row->row_count,
                ])
                ->values()
                ->all(),
            'status_counts' => (clone $base)
                ->selectRaw('status, count(*) as row_count')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn ($row): array => [
                    'status' => $row->status,
                    'count' => (int) $row->row_count,
                ])
                ->values()
                ->all(),
            'by_severity' => (clone $base)
                ->selectRaw('severity, count(*) as row_count')
                ->groupBy('severity')
                ->orderBy('severity')
                ->get()
                ->map(fn ($row): array => [
                    'severity' => $row->severity,
                    'count' => (int) $row->row_count,
                ])
                ->values()
                ->all(),
            'filters' => $this->queryService->filterOptionsFor($user),
            'recent' => $recent
                ->map(fn (UserNotification $notification): array => [
                    'id' => $notification->id,
                    'notification_number' => $notification->notification_number,
                    'channel' => $notification->channel,
                    'category' => $notification->category,
                    'severity' => $notification->severity,
                    'status' => $notification->status,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'action_url' => $notification->action_url,
                    'notifiable_type' => $notification->notifiable_type,
                    'notifiable_id' => $notification->notifiable_id,
                    'payload' => $notification->payload ?? [],
                    'read_at' => $notification->read_at?->toISOString(),
                    'archived_at' => $notification->archived_at?->toISOString(),
                    'created_at' => $notification->created_at?->toISOString(),
                    'triggered_by' => $notification->triggeredBy ? [
                        'id' => $notification->triggeredBy->id,
                        'name' => $notification->triggeredBy->name,
                        'email' => $notification->triggeredBy->email,
                    ] : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function filteredCountsFor(User $user, array $filters = []): array
    {
        $base = $this->queryService->forUser($user, $filters);
        $counts = [
            'total' => (clone $base)->count(),
            'unread' => (clone $base)->where('status', 'unread')->count(),
            'read' => (clone $base)->where('status', 'read')->count(),
            'archived' => (clone $base)->where('status', 'archived')->count(),
            'critical_unread' => (clone $base)->where('status', 'unread')->where('severity', 'critical')->count(),
        ];

        return [
            'counts' => $counts,
            'category_counts' => (clone $base)
                ->selectRaw('category, count(*) as row_count')
                ->groupBy('category')
                ->orderBy('category')
                ->get()
                ->map(fn ($row): array => [
                    'category' => $row->category,
                    'count' => (int) $row->row_count,
                ])
                ->values()
                ->all(),
        ];
    }
}
