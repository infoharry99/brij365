<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Builder360\Builder360Bootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_summarize_own_notifications(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();

        $this->actingAs($construction)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('data.0.notification_number', 'NTF-10002')
            ->assertJsonPath('data.0.status', 'unread')
            ->assertJsonPath('data.0.severity', 'critical')
            ->assertJsonPath('data.0.action_url', '/after-sales/work-orders?status=scheduled')
            ->assertJsonPath('summary.counts.unread', 1)
            ->assertJsonPath('filtered_summary.counts.unread', 1)
            ->assertJsonPath('filters.statuses.0.value', 'unread')
            ->assertJsonPath('filters.severities.3.value', 'critical');

        $this->actingAs($construction)
            ->getJson(route('notifications.summary'))
            ->assertOk()
            ->assertJsonPath('data.unread', 1)
            ->assertJsonPath('data.counts.unread', 1)
            ->assertJsonPath('data.critical_unread', 1)
            ->assertJsonPath('data.counts.critical_unread', 1)
            ->assertJsonPath('data.by_category.0.category', 'maintenance')
            ->assertJsonPath('data.recent.0.notification_number', 'NTF-10002')
            ->assertJsonPath('data.recent.0.status', 'unread')
            ->assertJsonPath('data.recent.0.action_url', '/after-sales/work-orders?status=scheduled')
            ->assertJsonPath('data.scope.recipient_user_id', $construction->id);

        $bootstrap = app(Builder360Bootstrap::class)->forUser($construction);

        $this->assertSame('/notifications', $bootstrap['notifications']['endpoints']['index_url']);
        $this->assertSame('/notifications/summary', $bootstrap['notifications']['endpoints']['summary_url']);
        $this->assertSame('/notifications/read-all', $bootstrap['notifications']['endpoints']['read_all_url']);
        $this->assertSame('/notifications/__NOTIFICATION__/read', $bootstrap['notifications']['endpoints']['read_url_template']);
        $this->assertSame('/notifications/__NOTIFICATION__/archive', $bootstrap['notifications']['endpoints']['archive_url_template']);
    }

    public function test_user_can_use_native_blade_notification_center(): void
    {
        $this->seed();

        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $notification = UserNotification::where('notification_number', 'NTF-10002')->firstOrFail();

        $this->actingAs($construction)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Secure workflow inbox for real workflow notifications')
            ->assertDontSee('Native Laravel Blade inbox for real workflow notifications')
            ->assertSee('NTF-10002')
            ->assertSee('Maintenance work order scheduled')
            ->assertSee('name="status"', false)
            ->assertDontSee('window.Builder360Server', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($construction)
            ->get(route('notifications.index', [
                'status' => 'unread',
                'category' => 'maintenance',
                'severity' => 'critical',
            ]))
            ->assertOk()
            ->assertSee('NTF-10002')
            ->assertSee('value="unread" selected', false)
            ->assertSee('value="critical" selected', false);

        $this->actingAs($construction)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('user_notifications', [
            'id' => $notification->id,
            'status' => 'read',
        ]);

        $this->actingAs($construction)
            ->patch(route('notifications.archive', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('user_notifications', [
            'id' => $notification->id,
            'status' => 'archived',
        ]);
    }

    public function test_user_can_mark_all_read_from_native_blade_notification_center(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();

        UserNotification::create([
            'company_id' => $sales->company_id,
            'recipient_user_id' => $sales->id,
            'triggered_by_user_id' => null,
            'notification_number' => 'NTF-BLADE-MARKALL',
            'channel' => 'in_app',
            'category' => 'sales',
            'severity' => 'info',
            'status' => 'unread',
            'title' => 'Blade notification mark-all',
            'body' => 'This notification should be marked read through the native Blade inbox.',
            'notifiable_type' => Booking::class,
            'notifiable_id' => $booking->id,
            'payload' => ['booking_code' => 'BK-1001'],
        ]);

        UserNotification::create([
            'company_id' => $sales->company_id,
            'recipient_user_id' => $sales->id,
            'triggered_by_user_id' => null,
            'notification_number' => 'NTF-BLADE-MARKALL-WARNING',
            'channel' => 'in_app',
            'category' => 'sales',
            'severity' => 'warning',
            'status' => 'unread',
            'title' => 'Blade notification not selected',
            'body' => 'This warning notification should remain unread.',
            'notifiable_type' => Booking::class,
            'notifiable_id' => $booking->id,
            'payload' => ['booking_code' => 'BK-1001'],
        ]);

        $this->actingAs($sales)
            ->patch(route('notifications.read-all'), [
                'category' => 'sales',
                'severity' => 'info',
            ])
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('user_notifications', [
            'notification_number' => 'NTF-BLADE-MARKALL',
            'status' => 'read',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'notification_number' => 'NTF-BLADE-MARKALL-WARNING',
            'status' => 'unread',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'notifications.notifications.read_all',
            'user_id' => $sales->id,
        ]);
    }

    public function test_user_can_mark_notification_read_archive_and_mark_all_read(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $notification = UserNotification::where('notification_number', 'NTF-10001')->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('notifications.read', $notification))
            ->assertOk()
            ->assertJsonPath('data.status', 'read');

        $this->assertDatabaseHas('user_notifications', [
            'id' => $notification->id,
            'status' => 'read',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'notifications.notification.read',
            'action' => 'Marked notification as read',
            'user_id' => $sales->id,
            'auditable_type' => UserNotification::class,
            'auditable_id' => $notification->id,
        ]);

        $this->actingAs($sales)
            ->patchJson(route('notifications.archive', $notification))
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'notifications.notification.archived',
            'action' => 'Archived notification',
            'user_id' => $sales->id,
            'auditable_type' => UserNotification::class,
            'auditable_id' => $notification->id,
        ]);

        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();

        UserNotification::create([
            'company_id' => $sales->company_id,
            'recipient_user_id' => $sales->id,
            'triggered_by_user_id' => null,
            'notification_number' => 'NTF-TEST-MARKALL',
            'channel' => 'in_app',
            'category' => 'sales',
            'severity' => 'info',
            'status' => 'unread',
            'title' => 'Booking follow-up',
            'body' => 'Booking follow-up notification for mark-all test.',
            'notifiable_type' => Booking::class,
            'notifiable_id' => $booking->id,
            'payload' => ['booking_code' => 'BK-1001'],
        ]);

        UserNotification::create([
            'company_id' => $sales->company_id,
            'recipient_user_id' => $sales->id,
            'triggered_by_user_id' => null,
            'notification_number' => 'NTF-TEST-MARKALL-OTHER',
            'channel' => 'in_app',
            'category' => 'finance',
            'severity' => 'warning',
            'status' => 'unread',
            'title' => 'Finance follow-up',
            'body' => 'Finance follow-up notification should remain unread.',
            'notifiable_type' => Booking::class,
            'notifiable_id' => $booking->id,
            'payload' => ['booking_code' => 'BK-1001'],
        ]);

        $this->actingAs($sales)
            ->patchJson(route('notifications.read-all'), [
                'severity' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('severity');

        $this->actingAs($sales)
            ->patchJson(route('notifications.read-all'), [
                'category' => 'sales',
                'severity' => 'info',
                'q' => 'Booking',
            ])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('user_notifications', [
            'notification_number' => 'NTF-TEST-MARKALL',
            'status' => 'read',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'notification_number' => 'NTF-TEST-MARKALL-OTHER',
            'status' => 'unread',
        ]);

        $audit = AuditEvent::query()
            ->where('event_type', 'notifications.notifications.read_all')
            ->latest()
            ->firstOrFail();

        $this->assertSame(1, $audit->metadata['updated']);
        $this->assertSame('Booking', $audit->metadata['q']);
        $this->assertSame('sales', $audit->metadata['category']);
        $this->assertSame('info', $audit->metadata['severity']);
    }

    public function test_user_cannot_modify_another_users_notification(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $constructionNotification = UserNotification::where('notification_number', 'NTF-10002')->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('notifications.read', $constructionNotification))
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('notifications.archive', $constructionNotification))
            ->assertForbidden();
    }

    public function test_filters_apply_to_own_notification_inbox(): void
    {
        $this->seed();

        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();

        $this->actingAs($finance)
            ->getJson(route('notifications.index', [
                'status' => 'read',
                'category' => 'collections',
                'severity' => 'info',
                'q' => 'receipt',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notification_number', 'NTF-10003')
            ->assertJsonPath('filtered_summary.counts.total', 1)
            ->assertJsonPath('filtered_summary.category_counts.0.category', 'collections');

        $this->actingAs($finance)
            ->getJson(route('notifications.index', ['q' => 'no matching notification']))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('filtered_summary.counts.total', 0);

        $this->actingAs($finance)
            ->getJson(route('notifications.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($finance)
            ->getJson(route('notifications.index', ['recipient_user_id' => $finance->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_user_id'])
            ->assertJsonPath('errors.recipient_user_id.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($finance)
            ->getJson(route('notifications.index', [
                'status' => 'invalid',
                'date_from' => now()->toDateString(),
                'date_to' => now()->subDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'date_to']);
    }

    public function test_local_qa_seed_notifications_are_recipient_scoped_and_filterable(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $employee = User::where('email', 'amit.verma@builder360.test')->firstOrFail();
        $buyer = User::where('email', 'rohan.shah@example.test')->firstOrFail();
        $channelPartner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();
        $broker = User::where('email', 'farhan.shaikh@partners.builder360.test')->firstOrFail();
        $roleExamples = [
            'deepa.rao@builder360.test' => 'NTF-QA-HR-APPROVAL',
            'kavita.shah@builder360.test' => 'NTF-QA-PAYROLL-PAYMENT',
            'ananya.sen@builder360.test' => 'NTF-QA-RECRUITER-SALES',
            'meera.kapoor@builder360.test' => 'NTF-QA-COMPLIANCE-LEGAL',
            'ishaan.trivedi@builder360.test' => 'NTF-QA-AUDITOR-LEGAL',
            'nikhil.desai@builder360.test' => 'NTF-QA-SYSTEM-APPROVAL',
        ];

        $directorApproval = $this->actingAs($director)
            ->getJson(route('notifications.index', ['category' => 'approval', 'status' => 'unread']))
            ->assertOk()
            ->json('data');

        $this->assertContains('NTF-QA-DIRECTOR-APPROVAL', collect($directorApproval)->pluck('notification_number')->all());

        $directorSummary = $this->actingAs($director)
            ->getJson(route('notifications.summary'))
            ->assertOk()
            ->assertJsonPath('data.scope.recipient_user_id', $director->id)
            ->assertJsonPath('data.counts.unread', 1)
            ->json('data');

        $this->assertSame(
            1,
            collect($directorSummary['by_category'])->firstWhere('category', 'approval')['count'] ?? 0
        );

        $employeeInventory = $this->actingAs($employee)
            ->getJson(route('notifications.index', ['category' => 'inventory', 'status' => 'read']))
            ->assertOk()
            ->json('data');

        $this->assertContains('NTF-QA-EMPLOYEE-INVENTORY', collect($employeeInventory)->pluck('notification_number')->all());

        $buyerPayment = $this->actingAs($buyer)
            ->getJson(route('notifications.index', ['category' => 'payment', 'status' => 'unread']))
            ->assertOk()
            ->json('data');

        $this->assertContains('NTF-QA-BUYER-PAYMENT', collect($buyerPayment)->pluck('notification_number')->all());

        $channelArchivedSales = $this->actingAs($channelPartner)
            ->getJson(route('notifications.index', ['category' => 'sales', 'status' => 'archived']))
            ->assertOk()
            ->json('data');

        $this->assertContains('NTF-QA-CHANNEL-SALES', collect($channelArchivedSales)->pluck('notification_number')->all());

        $brokerLegal = $this->actingAs($broker)
            ->getJson(route('notifications.index', ['category' => 'legal', 'status' => 'unread']))
            ->assertOk()
            ->json('data');

        $this->assertContains('NTF-QA-BROKER-LEGAL', collect($brokerLegal)->pluck('notification_number')->all());
        $this->assertNotContains('NTF-QA-DIRECTOR-APPROVAL', collect($brokerLegal)->pluck('notification_number')->all());

        $brokerSummary = $this->actingAs($broker)
            ->getJson(route('notifications.summary'))
            ->assertOk()
            ->assertJsonPath('data.scope.recipient_user_id', $broker->id)
            ->assertJsonPath('data.counts.critical_unread', 1)
            ->json('data');

        $this->assertSame(
            1,
            collect($brokerSummary['by_category'])->firstWhere('category', 'legal')['count'] ?? 0
        );

        foreach ($roleExamples as $email => $notificationNumber) {
            $roleUser = User::where('email', $email)->firstOrFail();
            $rows = $this->actingAs($roleUser)
                ->getJson(route('notifications.index', ['q' => $notificationNumber]))
                ->assertOk()
                ->json('data');

            $this->assertContains($notificationNumber, collect($rows)->pluck('notification_number')->all());
            $this->assertNotContains('NTF-QA-DIRECTOR-APPROVAL', collect($rows)->pluck('notification_number')->all());
        }
    }

    public function test_after_sales_assignment_creates_assignee_notification(): void
    {
        $this->seed();

        $sales = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        $construction = User::where('email', 'rajesh.kulkarni@builder360.test')->firstOrFail();
        $booking = Booking::where('booking_code', 'BK-1001')->firstOrFail();

        $ticketNumber = $this->actingAs($sales)
            ->postJson(route('after-sales.tickets.store'), [
                'booking_id' => $booking->id,
                'category' => 'maintenance',
                'priority' => 'high',
                'source' => 'phone',
                'subject' => 'Door alignment issue',
                'description' => 'Customer reported bedroom door alignment issue after possession inspection.',
            ])
            ->assertCreated()
            ->json('data.ticket_number');

        $ticket = ServiceTicket::where('ticket_number', $ticketNumber)->firstOrFail();

        $this->actingAs($sales)
            ->patchJson(route('after-sales.tickets.assign', $ticket), [
                'assigned_to_user_id' => $construction->id,
                'note' => 'Please inspect and update resolution plan.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'recipient_user_id' => $construction->id,
            'triggered_by_user_id' => $sales->id,
            'category' => 'after_sales',
            'status' => 'unread',
            'notifiable_type' => ServiceTicket::class,
            'notifiable_id' => $ticket->id,
        ]);
    }
}
