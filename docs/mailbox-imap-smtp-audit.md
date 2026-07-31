# Mailbox IMAP/SMTP Audit and Delivery Report

## Verified root causes

| Area | Previous state | Root cause | Correction |
|---|---|---|---|
| External retrieval | No IMAP connection existed | Mailbox used internal `collaboration_messages` only | Added account/folder/email/attachment/sync models and Webklex IMAP gateway |
| External sending | Global mailer was configured as `log` | No account-specific SMTP transport | Added per-account Symfony SMTP transport and connection test |
| Credentials | No external account store | No credential boundary | Added encrypted model cast, hidden serialization, owner-only routes, certificate validation |
| Duplicate messages | No remote identity model | No UID/UIDVALIDITY contract | Unique folder/UID key, UIDVALIDITY reset, idempotent upsert |
| Cross-device flags | No IMAP state reconciliation | Internal mailbox status was unrelated to remote flags | Rolling IMAP reconciliation and remote-first Seen/Flagged mutations |
| Missing Sent items | SMTP and IMAP Sent are separate | Generic SMTP does not guarantee Sent-folder persistence | Append delivered MIME message to discovered Sent folder; failure does not encourage duplicate resend |
| Attachments | Internal mailbox had no external MIME import | No private attachment cache | Private storage, checksum deduplication, owner-authorized downloads |
| Background synchronization | No external sync job | No account scheduler | Unique per-account queued job with retries/backoff and one-minute due-account dispatcher |
| Error recovery | No protocol diagnostics | No sync-run records | Persistent sync runs, account error state, user-visible last error, retry action |
| Search | Internal records only | No external message query | Owner/account/folder-scoped subject, body and address search |
| HTML safety | External HTML was unsupported | Rendering provider HTML directly would be unsafe | Sandboxed iframe with escaped `srcdoc`; no script or parent access |
| Draft loss | Compose existed only as a request form | Refresh, session expiry, or provider failure could lose work | Durable outbox, debounced autosave, recovery list, discard, and failed-send retention |
| Duplicate sends | No idempotency key or send lock | Double clicks and concurrent tabs could submit twice | Per-compose UUID, account lock, state guard, and attempt tracking |
| Scheduled delivery | No external outbox lifecycle | Provider send was immediate-only | Scheduled state, due-message dispatcher, unique queued job, retry/backoff |
| Reply/forward continuity | Reply headers were partial and Forward/Reply All were absent | Compose and reading pane were disconnected | Reply, Reply All, Forward, `In-Reply-To`, `References`, and normalized recipients |
| Concurrent drafts | Last write silently won | No version contract between tabs | Optimistic `lock_version` with a recoverable conflict response |
| Provider failure UX | Exceptions reached generic failure handling | No persistent failed message | Failed state retains body/recipients/attachments and exposes review/retry |

## Implemented workflows

- Connect account after successful IMAP and SMTP authentication checks.
- Discover folders and map Inbox, Sent, Drafts, Archive, Trash, and Spam by provider folder name.
- Synchronize messages, addresses, bodies, headers, flags, attachments, and remote identity.
- Browse account/folder, search, filter unread/starred, read a message, and download private attachments.
- Compose email with CC, BCC, and validated attachments.
- Autosave, recover, schedule, discard, and retry failed drafts.
- Compose with contact suggestions, cross-field recipient deduplication, signatures, and safely rendered basic Markdown.
- Reply, Reply All, and Forward with `In-Reply-To` and `References` headers.
- Thread related provider messages by root `References`/`In-Reply-To` identity.
- Sort, search, paginate, filter unread/starred/attachment mail, and restore Spam/Trash messages to Inbox.
- Record account, sync, send, draft, schedule, discard, and state-change audit events without message-body or credential leakage.
- Mark read/unread, star/unstar, archive, move to spam, and move to trash through IMAP before local state changes.
- Synchronize accounts manually or through queued background jobs.
- Disconnect an account and remove cached private data.

## Security controls

- Password/app password is encrypted at rest and hidden from serialization.
- Route-model access is explicitly owner and company checked even for wildcard administrator roles.
- Attachments are stored on the private local disk and downloaded only after ownership authorization.
- Provider certificates are verified by default.
- Message HTML is isolated in a sandboxed iframe.
- Connection, sync, and send endpoints are rate limited.
- Recipient and attachment validation is server authoritative.

## Validation completed

- `ExternalMailboxTest`: 7 workflows and 36 assertions covering account encryption, owner isolation, address parsing/deduplication, unsafe attachment rejection, account-specific SMTP, idempotency, autosave conflicts, scheduled delivery, provider failure recovery, and remote-first state mutation.
- `CollaborationMailboxTest`: 15 existing internal-mailbox workflows and 247 assertions remain green.
- PHP lint passed for new protocol, controller, model, action, request, job, and migration files.
- Blade compilation passed.
- Mailbox route discovery passed.
- Production Vite build passed.
- Migration applied successfully to the configured local database.
- Combined mailbox regression result: 22 passing workflows and 283 assertions.

## Remaining deployment validation

The following require real provider credentials/infrastructure and cannot be proven by deterministic local fakes:

- Provider-specific folder naming and OAuth-only providers.
- Live TLS/certificate/authentication behavior for each IMAP/SMTP host.
- Large-mailbox throughput and provider throttling.
- Supervisor processing of the database queue and the minute scheduler on the target VPS.
- Live SMTP acceptance, bounce handling, and provider spam-policy behavior.

Provider push/IDLE, bounce ingestion, OAuth token refresh, undo-send, delivery-status notifications, arbitrary rich HTML editing, and antivirus scanning are not silently simulated. They remain explicit follow-up work rather than misleading UI controls. Polling synchronization, safe Markdown formatting, private attachment validation, and in-app new-mail notifications are active.
