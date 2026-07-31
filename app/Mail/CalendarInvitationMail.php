<?php

namespace App\Mail;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

final class CalendarInvitationMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public CalendarEvent $event, public CalendarEventAttendee $attendee, public string $change = 'request') {}
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(env('CALENDAR_MAIL_FROM_ADDRESS', 'Calender@brijgroup.in'), env('CALENDAR_MAIL_FROM_NAME', 'Builder360 Calendar')),
            subject: ($this->change === 'cancel' ? 'Cancelled: ' : 'Invitation: ').$this->event->title
        );
    }
    public function content(): Content { return new Content(view: 'mail.calendar-invitation', with: ['responseUrl'=>URL::temporarySignedRoute('calendar.guest-invitations.show', now()->addDays(30), ['calendarEventAttendee'=>$this->attendee->id])]); }
    public function attachments(): array { return [Attachment::fromData(fn () => $this->ics(), 'event-'.$this->event->event_number.'.ics')->withMime('text/calendar')]; }
    private function ics(): string
    {
        $escape=fn(?string $v)=>str_replace(["\\","\n",",",";"],["\\\\","\\n","\\,","\\;"],(string)$v);
        return implode("\r\n",['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Builder360//Calendar//EN','METHOD:'.($this->change==='cancel'?'CANCEL':'REQUEST'),'BEGIN:VEVENT','UID:'.$this->event->event_number.'@builder360','DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),'DTSTART:'.$this->event->starts_at->utc()->format('Ymd\THis\Z'),'DTEND:'.$this->event->ends_at->utc()->format('Ymd\THis\Z'),'SUMMARY:'.$escape($this->event->title),'LOCATION:'.$escape($this->event->location),'DESCRIPTION:'.$escape($this->event->description),'STATUS:'.($this->change==='cancel'?'CANCELLED':'CONFIRMED'),'END:VEVENT','END:VCALENDAR','']);
    }
}
