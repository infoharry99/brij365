<?php

namespace App\Http\Controllers\Notifications;

use App\Application\Notifications\Actions\ListNotificationInbox;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\ArchiveNotificationRequest;
use App\Http\Requests\Notifications\MarkAllNotificationsReadRequest;
use App\Http\Requests\Notifications\MarkNotificationReadRequest;
use App\Http\Requests\Notifications\NotificationIndexRequest;
use App\Http\Requests\Notifications\NotificationSummaryRequest;
use App\Http\Resources\UserNotificationResource;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Notifications\NotificationSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(
        NotificationIndexRequest $request,
        ListNotificationInbox $action,
    ): AnonymousResourceCollection|View
    {
        $filters = $request->validated();
        $page = $action->execute($request->user(), $filters);

        if ($request->wantsJson()) {
            return UserNotificationResource::collection($page->notifications)
                ->additional([
                    'summary' => $page->summary,
                    'filtered_summary' => $page->filteredSummary,
                    'filters' => $page->filterOptions,
                ]);
        }

        return view('notifications.index', [
            'notifications' => $page->notifications,
            'summary' => $page->summary,
            'filters' => $page->filters,
            'statuses' => $page->statuses,
            'severities' => $page->severities,
            'categories' => collect($page->categories),
        ]);
    }

    public function summary(NotificationSummaryRequest $request, NotificationSummaryService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->summaryFor($request->user()),
        ]);
    }

    public function markRead(
        MarkNotificationReadRequest $request,
        UserNotification $userNotification,
        NotificationCenterService $service,
    ): UserNotificationResource|RedirectResponse
    {
        $notification = $service->markRead($userNotification, $request->user(), $request)->load(['recipient', 'triggeredBy']);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('notifications.index')
                ->with('status', "Notification {$notification->notification_number} marked as read.");
        }

        return (new UserNotificationResource($notification))
            ->additional(['message' => 'Notification marked as read.']);
    }

    public function archive(
        ArchiveNotificationRequest $request,
        UserNotification $userNotification,
        NotificationCenterService $service,
    ): UserNotificationResource|RedirectResponse
    {
        $notification = $service->archive($userNotification, $request->user(), $request)->load(['recipient', 'triggeredBy']);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('notifications.index')
                ->with('status', "Notification {$notification->notification_number} archived.");
        }

        return (new UserNotificationResource($notification))
            ->additional(['message' => 'Notification archived.']);
    }

    public function markAllRead(MarkAllNotificationsReadRequest $request, NotificationCenterService $service): JsonResponse|RedirectResponse
    {
        $updated = $service->markAllRead($request->user(), $request->validated(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('notifications.index')
                ->with('status', "{$updated} unread notification(s) marked as read.");
        }

        return response()->json([
            'message' => 'Unread notifications marked as read.',
            'data' => [
                'updated' => $updated,
            ],
        ]);
    }

}
