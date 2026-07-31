<?php

namespace Tests\Feature;

use App\Domain\Collaboration\Services\CalendarRecurrenceGenerator;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CalendarManagementCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_workspace_has_a_real_responsive_options_and_scroll_contract(): void
    {
        $index = file_get_contents(resource_path('views/collaboration/calendar-events/index.blade.php'));
        $views = file_get_contents(resource_path('views/collaboration/calendar-events/partials/views.blade.php'));
        $script = file_get_contents(resource_path('js/app.js'));
        $styles = file_get_contents(resource_path('css/task-calendar.css'));

        $this->assertStringContainsString('x-show="optionsOpen"', $index);
        $this->assertStringContainsString('<span x-show="optionsOpen">Hide options</span>', $index);
        $this->assertStringContainsString('<span x-show="!optionsOpen" x-cloak>Show options</span>', $index);
        $this->assertStringContainsString('aria-controls="calendar-summary-options calendar-scope-options calendar-filter-options calendar-legend-options calendar-active-filter-options"', $index);
        $this->assertStringNotContainsString('<style', $index);
        $this->assertStringContainsString('x-ref="calendarBody"', $views);
        $this->assertStringContainsString('x-on:scroll.passive="rememberScroll"', $views);

        $this->assertStringContainsString("Alpine.data('calendarWorkspace'", $script);
        $this->assertStringContainsString('new window.ResizeObserver', $script);
        $this->assertStringContainsString('builder360.calendar.options-open', $script);
        $this->assertStringContainsString('builder360.calendar.scroll.', $script);

        $this->assertStringContainsString('container: calendar-workspace / inline-size', $styles);
        $this->assertStringContainsString('Calendar base component presentation (moved from Blade)', $styles);
        $this->assertStringContainsString('scrollbar-gutter: stable both-edges', $styles);
        $this->assertStringContainsString('@container calendar-workspace', $styles);
        $this->assertMatchesRegularExpression('/\.cal-body\s*\{[^}]*overflow:\s*auto/s', $styles);
    }

    public function test_local_calendar_input_is_persisted_as_the_correct_utc_instant(): void
    {
        $this->seed();
        $actor = User::where('email','priya.nair@builder360.test')->firstOrFail();
        $project = Project::where('code','SKY-PUN')->firstOrFail();

        $this->actingAs($actor)->postJson(route('collaboration.calendar-events.store'), [
            'title'=>'Timezone accuracy review','event_type'=>'meeting','starts_at'=>'2026-08-20T10:00','ends_at'=>'2026-08-20T11:00',
            'timezone'=>'Asia/Kolkata','project_id'=>$project->id,'attendees'=>[['user_id'=>$actor->id]],
        ])->assertCreated();

        $event = CalendarEvent::where('title','Timezone accuracy review')->firstOrFail();
        $this->assertSame('2026-08-20 04:30:00', $event->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('10:00', $event->starts_at->setTimezone('Asia/Kolkata')->format('H:i'));
    }

    public function test_all_reference_calendar_views_render_server_authoritative_structures(): void
    {
        $this->seed();
        $actor = User::where('email','priya.nair@builder360.test')->firstOrFail();
        foreach (['month'=>'cal-month-grid','week'=>'cal-tg-stage','day'=>'cal-tg-stage','list'=>'cal-list','employee'=>'cal-lanes','team'=>'cal-lanes'] as $view=>$class) {
            $this->actingAs($actor)->get(route('collaboration.calendar-events.index',['view'=>$view,'focus_date'=>'2026-07-18']))
                ->assertOk()->assertSee($class,false)->assertSee('Today’s events')->assertSee('Invitation response');
        }
    }

    public function test_recurring_occurrence_generation_is_deterministic(): void
    {
        $this->seed();
        $actor = User::where('email','priya.nair@builder360.test')->firstOrFail();
        $this->actingAs($actor)->postJson(route('collaboration.calendar-events.store'), [
            'title'=>'Daily operations stand-up','event_type'=>'internal','starts_at'=>'2026-09-01T09:00','ends_at'=>'2026-09-01T09:30',
            'timezone'=>'Asia/Kolkata','attendees'=>[['user_id'=>$actor->id]],'recurrence'=>['frequency'=>'daily','interval'=>1,'occurrence_limit'=>3],
        ])->assertCreated();
        $root = CalendarEvent::where('title','Daily operations stand-up')->firstOrFail();
        $rule = $root->recurrenceRule()->firstOrFail();
        $this->assertSame(1, app(CalendarRecurrenceGenerator::class)->generateDue(CarbonImmutable::instance($rule->next_run_at)));
        $this->assertDatabaseCount('calendar_events', 3); // seeded event, root, generated occurrence
        $this->assertDatabaseHas('calendar_events',['series_root_id'=>$root->id,'occurrence_key'=>'calendar-series:'.$rule->id.':'.$rule->next_run_at->utc()->format('YmdHis')]);
    }

    public function test_private_calendar_attachment_is_authorized_and_downloadable(): void
    {
        Storage::fake('local');
        $this->seed();
        $actor = User::where('email','priya.nair@builder360.test')->firstOrFail();
        $event = CalendarEvent::firstOrFail();
        $event->forceFill(['organizer_user_id'=>$actor->id])->save();

        $this->actingAs($actor)->post(route('collaboration.calendar-events.attachments.store',$event), [
            'attachment'=>UploadedFile::fake()->image('agenda.png'),
        ])->assertRedirect();
        $attachment = $event->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($actor)->get(route('collaboration.calendar-events.attachments.download',[$event,$attachment]))->assertOk();
    }

    public function test_internal_and_signed_guest_invitation_responses_are_scoped(): void
    {
        $this->seed();
        $organizer = User::where('email','priya.nair@builder360.test')->firstOrFail();
        $invitee = User::where('email','rajesh.kulkarni@builder360.test')->firstOrFail();
        $this->actingAs($organizer)->postJson(route('collaboration.calendar-events.store'), [
            'title'=>'Invitation lifecycle review','event_type'=>'meeting','starts_at'=>'2026-10-10T10:00','ends_at'=>'2026-10-10T11:00','timezone'=>'Asia/Kolkata',
            'attendees'=>[['user_id'=>$invitee->id]],'guest_emails'=>'guest@example.com',
        ])->assertCreated();
        $event = CalendarEvent::where('title','Invitation lifecycle review')->firstOrFail();

        $this->actingAs($invitee)->patch(route('collaboration.calendar-events.response',$event),['response'=>'accepted'])->assertRedirect();
        $this->assertDatabaseHas('calendar_event_attendees',['calendar_event_id'=>$event->id,'user_id'=>$invitee->id,'response'=>'accepted']);

        $guest = $event->attendeeRecords()->where('email','guest@example.com')->firstOrFail();
        $signed = URL::temporarySignedRoute('calendar.guest-invitations.respond',now()->addHour(),['calendarEventAttendee'=>$guest->id]);
        $this->post($signed,['response'=>'tentative'])->assertRedirect();
        $this->assertDatabaseHas('calendar_event_attendees',['id'=>$guest->id,'response'=>'tentative']);
    }

    public function test_external_guest_conflicts_are_rejected_without_misclassifying_the_guest(): void
    {
        $this->seed();
        $actor = User::where('email','priya.nair@builder360.test')->firstOrFail();

        $base = [
            'event_type'=>'meeting', 'timezone'=>'Asia/Kolkata', 'guest_emails'=>'guest@example.com',
            'starts_at'=>'2026-11-10T10:00', 'ends_at'=>'2026-11-10T11:00',
        ];
        $this->actingAs($actor)->postJson(route('collaboration.calendar-events.store'), $base + ['title'=>'Guest planning call'])->assertCreated();

        $event = CalendarEvent::where('title','Guest planning call')->firstOrFail();
        $this->assertDatabaseHas('calendar_event_attendees', [
            'calendar_event_id'=>$event->id, 'email'=>'guest@example.com', 'attendee_type'=>'guest',
        ]);

        $this->actingAs($actor)->postJson(route('collaboration.calendar-events.store'), $base + ['title'=>'Overlapping guest call'])
            ->assertUnprocessable()->assertJsonValidationErrors(['starts_at','attendees']);
        $this->assertDatabaseMissing('calendar_events', ['title'=>'Overlapping guest call']);
    }
}
