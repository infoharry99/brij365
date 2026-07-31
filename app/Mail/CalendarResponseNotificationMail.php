<?php

namespace App\Mail;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CalendarResponseNotificationMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public CalendarEvent $event,
        public CalendarEventAttendee $attendee,
        public string $recipientType = 'organizer'
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = env('CALENDAR_MAIL_FROM_ADDRESS', 'Calender@brijgroup.in');
        $fromName = env('CALENDAR_MAIL_FROM_NAME', 'Builder360 Calendar');

        $actionText = match ($this->attendee->response) {
            'accepted' => 'accepted',
            'declined' => 'declined',
            'tentative' => 'tentatively accepted',
            default => 'responded to',
        };

        if ($this->recipientType === 'organizer') {
            $subject = "[RSVP Update] {$this->attendee->name} {$actionText} \"{$this->event->title}\"";
        } else {
            $subject = "[RSVP Confirmation] You {$actionText} \"{$this->event->title}\"";
        }

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.calendar-response-notification',
            with: [
                'event' => $this->event,
                'attendee' => $this->attendee,
                'recipientType' => $this->recipientType,
            ]
        );
    }
}
