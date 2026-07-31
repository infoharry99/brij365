<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class NotificationQueryService
{
    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, UserNotification>
     */
    public function paginateFor(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->forUser($user, $filters)
            ->with(['recipient', 'triggeredBy'])
            ->orderByRaw("case when status = 'unread' then 0 when status = 'read' then 1 else 2 end")
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<UserNotification>
     */
    public function forUser(User $user, array $filters = []): Builder
    {
        return $this->applyFilters(
            UserNotification::query()->where('recipient_user_id', $user->id),
            $filters,
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<UserNotification>
     */
    public function unreadForMarkAll(User $user, array $filters = []): Builder
    {
        return $this->applyFilters(
            UserNotification::query()
                ->where('recipient_user_id', $user->id)
                ->where('status', 'unread'),
            $filters,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptionsFor(User $user): array
    {
        $base = UserNotification::query()->where('recipient_user_id', $user->id);

        return [
            'categories' => (clone $base)
                ->select('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values()
                ->all(),
            'statuses' => [
                ['value' => 'unread', 'label' => 'Unread'],
                ['value' => 'read', 'label' => 'Read'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
            'severities' => [
                ['value' => 'info', 'label' => 'Info'],
                ['value' => 'success', 'label' => 'Success'],
                ['value' => 'warning', 'label' => 'Warning'],
                ['value' => 'critical', 'label' => 'Critical'],
            ],
        ];
    }

    /**
     * @param Builder<UserNotification> $query
     * @param array<string, mixed> $filters
     * @return Builder<UserNotification>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status): Builder => $builder->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $builder, string $category): Builder => $builder->where('category', $category))
            ->when($filters['severity'] ?? null, fn (Builder $builder, string $severity): Builder => $builder->where('severity', $severity))
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('created_at', '>=', Carbon::parse($date)->toDateString()))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('created_at', '<=', Carbon::parse($date)->toDateString()))
            ->when($filters['created_before'] ?? null, fn (Builder $builder, string $date): Builder => $builder->where('created_at', '<=', Carbon::parse($date)))
            ->when($filters['q'] ?? null, function (Builder $builder, string $term): Builder {
                $search = trim($term);

                return $search === ''
                    ? $builder
                    : $builder->where(function (Builder $nested) use ($search): void {
                        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search) . '%';

                        $nested
                            ->where('notification_number', 'like', $like)
                            ->orWhere('title', 'like', $like)
                            ->orWhere('body', 'like', $like)
                            ->orWhere('category', 'like', $like)
                            ->orWhere('severity', 'like', $like);
                    });
            });
    }
}
