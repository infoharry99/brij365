<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationCenterService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationQueryService $queryService,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendToUser(User $recipient, array $data, ?User $triggeredBy = null, ?Model $notifiable = null): UserNotification
    {
        return UserNotification::create([
            'company_id' => $recipient->company_id,
            'recipient_user_id' => $recipient->id,
            'triggered_by_user_id' => $triggeredBy?->id,
            'notification_number' => $this->nextNotificationNumber(),
            'channel' => $data['channel'] ?? 'in_app',
            'category' => $data['category'],
            'severity' => $data['severity'] ?? 'info',
            'status' => 'unread',
            'title' => $data['title'],
            'body' => $data['body'],
            'action_url' => $data['action_url'] ?? null,
            'notifiable_type' => $notifiable ? $notifiable::class : ($data['notifiable_type'] ?? null),
            'notifiable_id' => $notifiable?->getKey() ?? ($data['notifiable_id'] ?? null),
            'payload' => $data['payload'] ?? [],
        ]);
    }

    /**
     * @param array<int, string> $permissions
     * @param array<string, mixed> $data
     * @return array<int, UserNotification>
     */
    public function sendToPermission(array $permissions, array $data, ?User $triggeredBy = null, ?Model $notifiable = null, ?int $companyId = null): array
    {
        return DB::transaction(function () use ($permissions, $data, $triggeredBy, $notifiable, $companyId): array {
            return User::query()
                ->with('role')
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->where('status', 'active')
                ->get()
                ->filter(fn (User $user): bool => collect($permissions)->contains(fn (string $permission): bool => $user->hasPermission($permission)))
                ->map(fn (User $recipient): UserNotification => $this->sendToUser($recipient, $data, $triggeredBy, $notifiable))
                ->values()
                ->all();
        });
    }

    public function markRead(UserNotification $notification, ?User $actor = null, ?Request $request = null): UserNotification
    {
        if ($notification->status === 'archived') {
            return $notification;
        }

        $notification->forceFill([
            'status' => 'read',
            'read_at' => $notification->read_at ?? now(),
        ])->save();

        $notification = $notification->refresh();

        $this->auditLogger->record(
            $actor ?? $notification->recipient,
            'notifications.notification.read',
            'Marked notification as read',
            $notification,
            [
                'notification_number' => $notification->notification_number,
                'category' => $notification->category,
                'severity' => $notification->severity,
            ],
            $request,
        );

        return $notification;
    }

    public function archive(UserNotification $notification, ?User $actor = null, ?Request $request = null): UserNotification
    {
        $notification->forceFill([
            'status' => 'archived',
            'read_at' => $notification->read_at ?? now(),
            'archived_at' => $notification->archived_at ?? now(),
        ])->save();

        $notification = $notification->refresh();

        $this->auditLogger->record(
            $actor ?? $notification->recipient,
            'notifications.notification.archived',
            'Archived notification',
            $notification,
            [
                'notification_number' => $notification->notification_number,
                'category' => $notification->category,
                'severity' => $notification->severity,
            ],
            $request,
        );

        return $notification;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function markAllRead(User $user, array $filters = [], ?Request $request = null): int
    {
        $updated = $this->queryService
            ->unreadForMarkAll($user, $filters)
            ->update([
                'status' => 'read',
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        $this->auditLogger->record(
            $user,
            'notifications.notifications.read_all',
            'Marked unread notifications as read',
            null,
            [
                'updated' => $updated,
                'q' => $filters['q'] ?? null,
                'status' => $filters['status'] ?? null,
                'category' => $filters['category'] ?? null,
                'severity' => $filters['severity'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'created_before' => $filters['created_before'] ?? null,
            ],
            $request,
        );

        return $updated;
    }

    private function nextNotificationNumber(): string
    {
        $next_number =sprintf('NTF'.mt_rand(100000, 999999).'-' . mt_rand(100, 999).'-' . mt_rand(10, 99));  
        return $next_number;;
    }
}
