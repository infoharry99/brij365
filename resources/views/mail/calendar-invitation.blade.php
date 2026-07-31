<h1>{{ $change === 'cancel' ? 'Event cancelled' : 'Calendar invitation' }}</h1>
<p><strong>{{ $event->title }}</strong></p>
<p>{{ $event->starts_at->setTimezone($event->timezone)->format('D, d M Y g:i A T') }} – {{ $event->ends_at->setTimezone($event->timezone)->format('g:i A T') }}</p>
@if($event->location)<p>Location: {{ $event->location }}</p>@endif
@if($change !== 'cancel')<p><a href="{{ $responseUrl }}">Respond to this invitation</a></p>@endif
<p>This link shows invitation details only and does not provide Builder360 access.</p>
