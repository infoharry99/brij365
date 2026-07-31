<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Calendar Invitation Response</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; padding: 24px; }
        .email-container { max-width: 540px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E2E8F2; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .email-header { background: #F6740C; color: #FFFFFF; padding: 24px; text-align: center; }
        .email-header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .email-body { padding: 24px; }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 13px; text-transform: uppercase; margin-bottom: 16px; }
        .status-accepted { background: #DCFCE7; color: #15803D; border: 1px solid #86EFAC; }
        .status-tentative { background: #FEF9C3; color: #A16207; border: 1px solid #FDE047; }
        .status-declined { background: #FEE2E2; color: #B91C1C; border: 1px solid #FCA5A5; }
        .event-details { background: #F8FAFC; border: 1px solid #E2E8F2; border-radius: 8px; padding: 16px; margin-top: 16px; }
        .detail-item { margin-bottom: 10px; font-size: 13px; color: #475569; }
        .detail-item strong { color: #0F172A; }
        .email-footer { background: #F1F5F9; padding: 16px; text-align: center; font-size: 11px; color: #64748B; border-top: 1px solid #E2E8F2; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Calendar Invitation Response</h1>
        </div>
        <div class="email-body">
            @php
                $statusClass = match($attendee->response) {
                    'accepted' => 'status-accepted',
                    'declined' => 'status-declined',
                    'tentative' => 'status-tentative',
                    default => 'status-accepted'
                };
                $statusLabel = match($attendee->response) {
                    'accepted' => 'ACCEPTED',
                    'declined' => 'DECLINED',
                    'tentative' => 'TENTATIVE',
                    default => strtoupper($attendee->response)
                };
            @endphp

            @if($recipientType === 'organizer')
                <p>Hello <strong>{{ $event->organizer?->name ?? 'Organizer' }}</strong>,</p>
                <p><strong>{{ $attendee->name }}</strong> ({{ $attendee->email }}) has updated their RSVP response for your calendar invitation:</p>
                <div style="text-align: center; margin: 20px 0;">
                    <span class="status-badge {{ $statusClass }}">Response: {{ $statusLabel }}</span>
                </div>
            @else
                <p>Hello <strong>{{ $attendee->name }}</strong>,</p>
                <p>Your RSVP response for the following calendar invitation has been recorded as:</p>
                <div style="text-align: center; margin: 20px 0;">
                    <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
            @endif

            <div class="event-details">
                <div class="detail-item"><strong>Event:</strong> {{ $event->title }}</div>
                <div class="detail-item"><strong>Date & Time:</strong> {{ $event->starts_at->setTimezone($event->timezone)->format('D, d M Y · g:i A T') }} – {{ $event->ends_at->setTimezone($event->timezone)->format('g:i A T') }}</div>
                @if($event->location)
                    <div class="detail-item"><strong>Location:</strong> {{ $event->location }}</div>
                @endif
                @if($event->organizer)
                    <div class="detail-item"><strong>Organizer:</strong> {{ $event->organizer->name }} ({{ $event->organizer->email }})</div>
                @endif
            </div>
        </div>
        <div class="email-footer">
            Sent by Builder360 ERP CRM · Calender@brijgroup.in
        </div>
    </div>
</body>
</html>
