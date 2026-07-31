<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BRIJ Group — Calendar Invitation - {{ $attendee->event->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        
        body {
            background-color: #F8FAFC;
            color: #0F172A;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }

        /* Guest Card Layout */
        .guest-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F2;
            border-radius: 16px;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
        }
        .guest-header {
            background: #F6740C;
            color: #FFFFFF;
            padding: 32px 28px 24px;
        }
        .guest-header-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.18);
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        .guest-title {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 8px;
        }
        .guest-time {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .guest-body {
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .guest-detail-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13px;
            color: #475569;
        }
        .guest-detail-row i {
            color: #F6740C;
            font-size: 15px;
            margin-top: 2px;
            width: 16px;
            text-align: center;
        }
        .guest-detail-row strong {
            color: #0F172A;
            font-weight: 600;
        }
        .guest-status-banner {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-accepted { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
        .status-tentative { background: #FEFCE8; color: #854D0E; border: 1px solid #FEF08A; }
        .status-declined { background: #FEF2F2; color: #991B1B; border: 1px solid #FCA5A5; }
        .status-pending { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F2; }

        .rsvp-label {
            font-size: 12px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .rsvp-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .rsvp-btn {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #CBD5E1;
            background: #FFFFFF;
            color: #334155;
            cursor: pointer;
            transition: all .15s ease;
        }
        .rsvp-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .rsvp-btn-accept {
            border-color: #22C55E;
            color: #15803D;
            background: #F0FDF4;
        }
        .rsvp-btn-accept:hover {
            background: #22C55E;
            color: #FFFFFF;
        }
        .rsvp-btn-decline {
            border-color: #EF4444;
            color: #B91C1C;
            background: #FEF2F2;
        }
        .rsvp-btn-decline:hover {
            background: #EF4444;
            color: #FFFFFF;
        }
        .rsvp-btn.is-active-declined {
            background: #EF4444 !important;
            color: #FFFFFF !important;
            border-color: #EF4444 !important;
        }
        .rsvp-btn:disabled, .rsvp-btn[disabled] {
            opacity: 0.55 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            transform: none !important;
            box-shadow: none !important;
        }
        .guest-footer {
            padding: 16px 28px;
            background: #F8FAFC;
            border-top: 1px solid #F1F5F9;
            text-align: center;
            font-size: 11px;
            color: #94A3B8;
        }
    </style>
</head>
<body>
    @include('partials.brij-loader')

    <!-- Calendar Invitation Card -->
    <main class="guest-card">
        <div class="guest-header">
            <div class="guest-header-badge">
                <i class="fa-regular fa-calendar-check"></i> Calendar Invitation
            </div>
            <h1 class="guest-title">{{ $attendee->event->title }}</h1>
            <div class="guest-time">
                <i class="fa-regular fa-clock"></i>
                <span>{{ $attendee->event->starts_at->setTimezone($attendee->event->timezone)->format('D, d M Y · g:i A T') }}</span>
            </div>
        </div>

        <div class="guest-body">
            @if($attendee->event->location)
                <div class="guest-detail-row">
                    <i class="fa-solid fa-location-dot"></i>
                    <div>
                        <strong>Location</strong>
                        <div>{{ $attendee->event->location }}</div>
                    </div>
                </div>
            @endif

            @if($attendee->event->description)
                <div class="guest-detail-row">
                    <i class="fa-solid fa-align-left"></i>
                    <div>
                        <strong>Details</strong>
                        <div>{{ $attendee->event->description }}</div>
                    </div>
                </div>
            @endif

            <div class="guest-detail-row">
                <i class="fa-regular fa-envelope"></i>
                <div>
                    <strong>Invited Guest</strong>
                    <div>{{ $attendee->email }}</div>
                </div>
            </div>

            <div class="guest-status-banner status-{{ $attendee->response }}">
                @if($attendee->response === 'accepted')
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Your RSVP Response: <strong>Accepted</strong></span>
                @elseif($attendee->response === 'declined')
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span>Your RSVP Response: <strong>Declined</strong></span>
                @else
                    <i class="fa-solid fa-clock"></i>
                    <span>Your RSVP Response: <strong>Pending</strong></span>
                @endif
            </div>

            <div>
                <div class="rsvp-label">
                    @if($attendee->response !== 'pending')
                        Response Recorded (Locked)
                    @else
                        Update Your Response
                    @endif
                </div>
                <form id="rsvpForm" method="POST" action="{{ request()->fullUrl() }}">
                    @csrf
                    <input type="hidden" name="response" id="responseValue" value="">
                    <div class="rsvp-actions">
                        <button type="button" @disabled($attendee->response !== 'pending') onclick="submitResponse('accepted')" class="rsvp-btn rsvp-btn-accept {{ $attendee->response === 'accepted' ? 'is-active-accepted' : '' }}">
                            <i class="fa-solid fa-check"></i> Accept
                        </button>
                        <button type="button" @disabled($attendee->response !== 'pending') onclick="submitResponse('declined')" class="rsvp-btn rsvp-btn-decline {{ $attendee->response === 'declined' ? 'is-active-declined' : '' }}">
                            <i class="fa-solid fa-xmark"></i> Reject / Decline
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="guest-footer">
            Powered by Builder360 ERP CRM · External Guest RSVP Portal
        </div>
    </main>

    <script>
        function showBrijLoader() {
            const loader = document.getElementById('brijLoaderScreen');
            if (loader) {
                loader.classList.remove('fade-out');
            }
        }

        function showModalAlert(options) {
            if (typeof Swal !== 'undefined') {
                return Swal.fire(options);
            }
            return new Promise((resolve) => {
                const isConfirmed = confirm((options.title || '') + '\n\n' + (options.text || ''));
                resolve({ isConfirmed: isConfirmed });
            });
        }

        function redirectToBrij() {
            showBrijLoader();
            setTimeout(function() {
                window.location.href = 'https://brijgroup.in';
            }, 1200);
        }

        function submitResponse(choice) {
            if ("{{ $attendee->response }}" !== "pending") {
                return;
            }
            const form = document.getElementById('rsvpForm');
            const input = document.getElementById('responseValue');
            input.value = choice;

            if (choice === 'accepted') {
                showModalAlert({
                    title: 'Accept Invitation?',
                    text: 'Confirm your acceptance for "{{ addslashes($attendee->event->title) }}".',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#22C55E',
                    cancelButtonColor: '#94A3B8',
                    confirmButtonText: 'Yes, Accept',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        showBrijLoader();
                        form.submit();
                    }
                });
            } else if (choice === 'declined') {
                showModalAlert({
                    title: 'Decline Invitation?',
                    text: 'Are you sure you want to decline this calendar invitation?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#EF4444',
                    cancelButtonColor: '#94A3B8',
                    confirmButtonText: 'Yes, Decline',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        showBrijLoader();
                        form.submit();
                    }
                });
            }
        }

        @if($attendee->response !== 'pending' || session('status'))
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    const response = "{{ $attendee->response }}";
                    if (response === 'accepted') {
                        showModalAlert({
                            title: 'Invitation Accepted!',
                            text: 'Your response is recorded as Accepted. An email notification has been sent to the organizer.',
                            icon: 'success',
                            confirmButtonColor: '#22C55E',
                            confirmButtonText: 'Awesome!'
                        }).then(function() {
                            redirectToBrij();
                        });
                    } else if (response === 'declined') {
                        showModalAlert({
                            title: 'Invitation Declined',
                            text: 'Your response is recorded as Declined. The organizer has been notified.',
                            icon: 'info',
                            confirmButtonColor: '#EF4444',
                            confirmButtonText: 'Close'
                        }).then(function() {
                            redirectToBrij();
                        });
                    }
                }, 1600);
            });
        @endif
    </script>
</body>
</html>
