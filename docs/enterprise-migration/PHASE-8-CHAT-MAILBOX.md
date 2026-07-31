# Phase 8 — Chat Connect and Mailbox

## Status

Completed for native Chat Connect and Mailbox workspaces, their write boundaries and Reverb realtime delivery with polling fallback.

## Implemented

- Chat Connect and Mailbox browser routes now render focused Blade page data without the broad application bootstrap payload.
- Chat availability and capabilities are read from the active role-access setting through `ChatAccessService`.
- Chat recipient options include only active, current-company users whose roles can access Chat Connect.
- Chat conversation/message list reads use focused Actions and immutable page/index DTOs.
- Mailbox list reads use a focused Action, immutable page DTO, centralized current-company options and pagination policy.
- Chat conversation creation, message send, poll create/vote/close, reaction, mark-read and archive now enter through named Form Requests/Actions and immutable commands.
- Mailbox send/schedule, read, archive, schedule cancellation, CRM link, state and reaction writes now enter through named Actions and immutable commands.
- Task and Mailbox exports now enter through focused export Actions while preserving scoped CSV/PDF output and audit evidence.
- Private attachment preview/download remains policy-protected and storage-backed.
- Reply/forward persistence remains constrained to the selected existing conversation; no implicit conversation creation was introduced.
- Laravel Echo and the Pusher protocol client are used only as the browser transport for Laravel Reverb.
- Chat listens to the actual custom broadcast names for message, read and poll events.
- A 15-second visibility-aware server polling fallback refreshes the page when Reverb is unavailable.
- Project/channel private broadcast authorization remains active-member and role-access controlled.
- Application broadcasting configuration explicitly defines Reverb, log and null transports; the production example selects Reverb.

## Architecture slices

- Chat page: `ChatConversationIndexRequest` → `CollaborationController` → `ListChatWorkspace` → chat/configuration services → `ChatWorkspaceData` → Blade.
- Chat API reads: request/policy → `ListChatConversations` / `ListChatMessages` → chat service → immutable/read resource delivery.
- Chat writes: named Chat Form Request → thin controller → one-use-case Action → immutable `ChatCommandData` → transactional chat service → Resource/redirect/event.
- Mailbox page: `CollaborationMessageIndexRequest` → `CollaborationController` → `ListMailboxWorkspace` → message/options services → `MailboxWorkspaceData` → Blade/JSON.
- Mailbox writes: named Mailbox Form Request → thin controller → one-use-case Action → immutable `CollaborationCommandData` → transactional message workflow → Resource/redirect.

## Verification

- `ChatConnectFeatureTest`: 7 tests, 65 assertions.
- `ChatConnectRealtimeTest`: 1 test, 5 assertions.
- `CollaborationMailboxTest`: 15 tests, 247 assertions.
- `CollaborationWorkflowTest`: 38 tests, 518 assertions.
- `CollaborationApplicationLayerTest`: 4 tests, 40 assertions.
- `LegacyFrontendWiringTest`: 6 tests, 416 assertions.
- Production Vite build passed with Echo/Reverb client included.
- Blade compilation passed.
- Live MySQL browser verified Chat Connect and Mailbox at desktop and 390×844 mobile widths with no server error or horizontal overflow.
- A temporary local Reverb listener started successfully on port 8080; Chat rendered an enabled Reverb configuration with no browser warning/error log and the process terminated cleanly after verification.
- Full Phase 8 regression gate: 740 tests, 16,441 assertions.

## Operational requirement

Run `php artisan reverb:start` and a queue worker under a supervised process in production. If Reverb is unavailable, Chat Connect remains usable through normal server forms and its polling fallback.

## Rollback

No schema migration was introduced in this phase. Rollback consists of reverting the focused Actions/DTOs and Echo initialization. Existing conversations, messages, attachments, polls, mailbox records and audit history remain intact.
