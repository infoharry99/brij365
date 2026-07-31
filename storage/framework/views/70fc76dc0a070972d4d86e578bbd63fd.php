<?php
    $draftToken = $composeDraft?->client_token ?? (string) Illuminate\Support\Str::uuid();
    $composeOpen = (bool) ($composeData['open'] ?? false) || $errors->any();
?>

<style>
    .b360-content {
        padding: 6px !important;
    }
    /* ── Composer shell ─────────────────────────────────────────── */
    .b360-mail-compose {
        position: fixed;
        bottom: 0;
        right: 24px;
        z-index: 900;
        width: 560px;
        max-width: calc(100vw - 32px);
        border-radius: 14px 14px 0 0;
        box-shadow: 0 8px 40px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.10);
        background: #fff;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        color: #1a1a1a;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 88vh;
        transition: box-shadow .2s;
    }
    .b360-mail-compose[open] {
        box-shadow: 0 16px 56px rgba(0,0,0,.22), 0 4px 12px rgba(0,0,0,.12);
    }

    /* hide the native <summary> triangle */
    .b360-mail-compose > summary { display: none; }

    /* ── Compose panel ──────────────────────────────────────────── */
    .b360-mail-compose-panel {
        display: flex;
        flex-direction: column;
        flex: 1;
        overflow: hidden;
    }

    /* ── Header ─────────────────────────────────────────────────── */
    .b360-unified-compose-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px 11px;
        background: #1e1e1e;
        border-radius: 14px 14px 0 0;
        color: #fff;
        flex-shrink: 0;
        cursor: default;
        user-select: none;
    }
    .b360-unified-compose-head > div:first-child { display: flex; flex-direction: column; gap: 1px; }
    .b360-unified-compose-head h2 {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.3;
        letter-spacing: -.01em;
        color: #fff;
    }
    .b360-unified-compose-head p {
        margin: 0;
        font-size: 11px;
        color: rgba(255,255,255,.45);
        line-height: 1;
    }
    .b360-unified-compose-head > div:last-child {
        display: flex;
        align-items: center;
        gap: 2px;
    }
    .b360-draft-status {
        font-size: 11px;
        color: rgba(255,255,255,.45);
        margin-right: 6px;
        min-width: 60px;
        text-align: right;
    }

    /* header icon buttons */
    .b360-collab-icon-btn {
        background: none;
        border: none;
        color: rgba(255,255,255,.65);
        width: 28px;
        height: 28px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        transition: background .15s, color .15s;
        padding: 0;
    }
    .b360-collab-icon-btn:hover {
        background: rgba(255,255,255,.12);
        color: #fff;
    }

    /* ── Error banner ───────────────────────────────────────────── */
    .b360-compose-errors {
        background: #fef2f2;
        border-bottom: 1px solid #fecaca;
        padding: 10px 16px;
        font-size: 13px;
        color: #991b1b;
        flex-shrink: 0;
    }
    .b360-compose-errors strong { font-weight: 600; }
    .b360-compose-errors ul { margin: 4px 0 0 0; padding-left: 18px; }
    .b360-compose-errors li { margin: 2px 0; }

    /* ── Form ───────────────────────────────────────────────────── */
    .b360-unified-compose-form {
        display: flex;
        flex-direction: column;
        flex: 1;
        overflow: hidden;
        min-height: 0;
    }

    /* ── From field ─────────────────────────────────────────────── */
    .b360-compose-field {
        display: flex;
        align-items: center;
        padding: 0 16px;
        border-bottom: 1px solid #f0f0f0;
        min-height: 40px;
        flex-shrink: 0;
    }
    .b360-compose-field > span {
        font-size: 12px;
        font-weight: 500;
        color: #6b7280;
        width: 52px;
        flex-shrink: 0;
        letter-spacing: .02em;
        text-transform: uppercase;
    }
    .b360-compose-field select,
    .b360-compose-field input[type="text"],
    .b360-compose-field input[type="datetime-local"] {
        flex: 1;
        border: none;
        outline: none;
        font-size: 13.5px;
        color: #1a1a1a;
        background: transparent;
        padding: 0;
        min-width: 0;
        font-family: inherit;
    }
    .b360-compose-field select {
        cursor: pointer;
        color: #374151;
    }
    .b360-compose-field input::placeholder { color: #9ca3af; }

    /* subject row — slightly bigger */
    .b360-compose-field input[name="subject"] {
        font-size: 14px;
        font-weight: 500;
    }

    /* ── Recipient section ──────────────────────────────────────── */
    .b360-email-recipient-section {
        flex-shrink: 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .b360-email-recipient-row {
        display: flex;
        align-items: flex-start;
        padding: 6px 16px;
        min-height: 40px;
        gap: 0;
        border-bottom: 1px solid #f0f0f0;
        position: relative;
    }
    .b360-email-recipient-row:last-of-type { border-bottom: none; }
    .b360-email-recipient-row > span {
        font-size: 12px;
        font-weight: 500;
        color: #6b7280;
        width: 52px;
        flex-shrink: 0;
        padding-top: 7px;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    /* token field */
    .b360-email-token-field {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        flex: 1;
        gap: 4px;
        padding: 4px 0;
        min-width: 0;
    }
    .b360-email-token-field input[type="email"] {
        border: none;
        outline: none;
        font-size: 13.5px;
        color: #1a1a1a;
        background: transparent;
        min-width: 120px;
        flex: 1;
        font-family: inherit;
        padding: 3px 0;
    }
    .b360-email-token-field input::placeholder { color: #9ca3af; }

    /* address tokens/chips */
    .b360-email-token {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f0f4ff;
        border: 1px solid #c7d7fd;
        color: #3730a3;
        border-radius: 100px;
        padding: 2px 6px 2px 9px;
        font-size: 12.5px;
        font-weight: 500;
        max-width: 200px;
    }
    .b360-email-token > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .b360-email-token button {
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        color: #6366f1;
        font-size: 11px;
        line-height: 1;
        display: flex;
        align-items: center;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        justify-content: center;
        transition: background .12s;
        flex-shrink: 0;
    }
    .b360-email-token button:hover { background: rgba(99,102,241,.15); }

    /* CC / BCC buttons */
    .b360-recipient-disclosures {
        display: flex;
        gap: 2px;
        padding-top: 6px;
        flex-shrink: 0;
    }
    .b360-recipient-disclosures button {
        background: none;
        border: none;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        cursor: pointer;
        padding: 3px 6px;
        border-radius: 4px;
        transition: background .12s, color .12s;
        letter-spacing: .03em;
    }
    .b360-recipient-disclosures button:hover { background: #f3f4f6; color: #1a1a1a; }

    /* recipient error */
    .b360-recipient-error {
        margin: 0;
        font-size: 12px;
        color: #dc2626;
        padding: 0 16px 6px;
        min-height: 0;
    }

    /* ── Rich text editor ───────────────────────────────────────── */
    .b360-compose-editor {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-height: 0;
    }

    /* formatting toolbar */
    .b360-compose-formatbar {
        display: flex;
        align-items: center;
        gap: 1px;
        padding: 5px 10px;
        border-bottom: 1px solid #f0f0f0;
        flex-shrink: 0;
        flex-wrap: wrap;
    }
    .b360-compose-formatbar button,
    .b360-inline-image-button {
        background: none;
        border: none;
        cursor: pointer;
        width: 30px;
        height: 28px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        color: #4b5563;
        transition: background .12s, color .12s;
        padding: 0;
    }
    .b360-compose-formatbar button:hover,
    .b360-inline-image-button:hover {
        background: #f3f4f6;
        color: #111827;
    }
    .b360-inline-image-button input[type="file"] { display: none; }
    .b360-inline-image-button { cursor: pointer; }

    /* toolbar separator */
    .b360-toolbar-sep {
        width: 1px;
        height: 16px;
        background: #e5e7eb;
        margin: 0 3px;
        flex-shrink: 0;
    }

    /* editor content area */
    .b360-email-editor-surface {
        flex: 1;
        overflow-y: auto;
        padding: 14px 16px;
        font-size: 14px;
        line-height: 1.65;
        color: #1a1a1a;
        outline: none;
        min-height: 120px;
    }
    .b360-email-editor-surface:empty::before {
        content: attr(placeholder);
        color: #9ca3af;
        pointer-events: none;
    }

    /* ── Attachment zone ────────────────────────────────────────── */
    .b360-compose-attachment-zone {
        border-top: 1px solid #f0f0f0;
        padding: 8px 16px;
        flex-shrink: 0;
    }
    .b360-compose-attachment-zone > label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: 12.5px;
        color: #6b7280;
        padding: 6px 8px;
        border-radius: 6px;
        transition: background .12s;
        border: 1.5px dashed transparent;
    }
    .b360-compose-attachment-zone > label:hover,
    .b360-compose-attachment-zone.is-dragging > label {
        background: #f0f4ff;
        border-color: #a5b4fc;
        color: #F6740C;
    }
    .b360-compose-attachment-zone.is-dragging > label {
        background: #eef2ff;
    }
    .b360-compose-attachment-zone > label i { font-size: 15px; }
    .b360-compose-attachment-zone > label input[type="file"] { display: none; }
    .b360-compose-attachment-zone > small {
        display: block;
        font-size: 11px;
        color: #9ca3af;
        margin-left: 8px;
    }

    /* file grid */
    .b360-compose-file-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    .b360-compose-file-grid article {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 12px;
        max-width: 200px;
        position: relative;
    }
    .b360-compose-file-grid article img {
        width: 28px;
        height: 28px;
        object-fit: cover;
        border-radius: 4px;
        flex-shrink: 0;
    }
    .b360-compose-file-grid article > i { font-size: 20px; color: #9ca3af; flex-shrink: 0; }
    .b360-compose-file-grid article span { display: flex; flex-direction: column; min-width: 0; }
    .b360-compose-file-grid article b { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .b360-compose-file-grid article small { color: #9ca3af; }
    .b360-compose-file-grid article button {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 12px;
        color: #9ca3af;
        padding: 2px;
        border-radius: 4px;
        transition: color .12s, background .12s;
        flex-shrink: 0;
        margin-left: auto;
    }
    .b360-compose-file-grid article button:hover { color: #dc2626; background: #fef2f2; }
    .b360-compose-file-grid article label { font-size: 11px; color: #6b7280; margin-left: auto; display: flex; align-items: center; gap: 4px; }

    /* ── Send later field ───────────────────────────────────────── */
    .b360-send-later-field {
        border-top: 1px solid #f0f0f0;
        border-bottom: none;
    }
    .b360-send-later-field input[type="datetime-local"] { font-size: 13px; color: #374151; }

    /* ── Footer / actions ───────────────────────────────────────── */
    .b360-unified-compose-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 10px 14px;
        border-top: 1px solid #f0f0f0;
        background: #fafafa;
        flex-shrink: 0;
    }
    .b360-upload-progress {
        flex: 1;
        height: 3px;
        background: #e5e7eb;
        border-radius: 99px;
        overflow: hidden;
        margin-right: 8px;
    }
    .b360-upload-progress i {
        display: block;
        height: 100%;
        background: #F6740C;
        border-radius: 99px;
        transition: width .2s;
        font-style: normal;
    }

    /* action buttons */
    .b360-unified-compose-actions button,
    .b360-unified-compose-actions [type="submit"] {
        border: none;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 500;
        font-family: inherit;
        cursor: pointer;
        padding: 7px 14px;
        transition: background .15s, color .15s, opacity .15s;
        white-space: nowrap;
    }

    /* Discard — ghost red */
    .blade-danger-action {
        background: transparent;
        color: #9ca3af;
        margin-right: auto;
    }
    .blade-danger-action:hover { background: #fef2f2; color: #dc2626; }

    /* Save draft — secondary */
    .blade-secondary-action {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #e5e7eb !important;
    }
    .blade-secondary-action:hover { background: #e5e7eb; }

    /* Send — primary indigo */
    .blade-primary-action {
        background: #F6740C;
        color: #fff;
        padding: 7px 20px;
    }
    .blade-primary-action:hover { background: #4338ca; }
    .blade-primary-action:disabled { opacity: .55; cursor: not-allowed; }

    /* Responsive narrow */
    @media (max-width: 600px) {
        .b360-mail-compose { right: 0; left: 0; width: 100%; border-radius: 14px 14px 0 0; }
    }
</style>

<details
    class="b360-mail-compose b360-unified-compose b360-email-compose"
    x-data="gmailMailboxComposer"
    data-account-id="<?php echo e($mailboxAccount->id); ?>"
    data-autosave-url="<?php echo e(route('mailbox.drafts.store', $mailboxAccount)); ?>"
    data-to='<?php echo json_encode(old('to', $composeData['to'] ?? []), 512) ?>'
    data-cc='<?php echo json_encode(old('cc', $composeData['cc'] ?? []), 512) ?>'
    data-bcc='<?php echo json_encode(old('bcc', $composeData['bcc'] ?? []), 512) ?>'
    data-body="<?php echo e(old('body', $composeData['body'] ?? '')); ?>"
    data-body-html="<?php echo e(old('body_html', $composeData['body_html'] ?? '')); ?>"
    data-discard-url="<?php echo e($composeDraft ? route('mailbox.drafts.destroy', [$mailboxAccount, $composeDraft]) : ''); ?>"
    <?php if($composeOpen): ?> open <?php endif; ?>
>
    <summary hidden>Compose</summary>
    <div class="b360-mail-compose-panel" role="dialog" aria-modal="true" aria-labelledby="business-email-compose-title">

        <header class="b360-unified-compose-head">
            <div>
                <h2 id="business-email-compose-title"><?php echo e($composeData['title'] ?? 'New message'); ?></h2>
                <p>Send business email through IMAP and SMTP.</p>
            </div>
            <div>
                <span class="b360-draft-status" x-text="status" aria-live="polite"></span>
                <button type="button" class="b360-collab-icon-btn" x-on:click="minimize" aria-label="Minimize email">
                    <i class="fa-solid fa-minus"></i>
                </button>
                <button type="button" class="b360-collab-icon-btn" x-on:click="toggleExpanded" x-bind:aria-label="expanded ? 'Restore email size' : 'Expand email'">
                    <i class="fa-solid fa-up-right-and-down-left-from-center"></i>
                </button>
                <button type="button" class="b360-collab-icon-btn" x-on:click="closeComposer" aria-label="Close email">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </header>

        <?php if($errors->any()): ?>
        <div class="b360-compose-errors" role="alert">
            <strong>Review the email details.</strong>
            <ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" action="<?php echo e(route('mailbox.external.send', $mailboxAccount)); ?>" class="b360-unified-compose-form" x-ref="form" x-on:submit="prepareSubmit">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="client_token" value="<?php echo e($draftToken); ?>">
            <input type="hidden" name="lock_version" value="<?php echo e($composeDraft?->lock_version); ?>" x-ref="lockVersion">
            <input type="hidden" name="in_reply_to" value="<?php echo e(old('in_reply_to', $composeData['in_reply_to'] ?? '')); ?>">
            <input type="hidden" name="references" value="<?php echo e(old('references', $composeData['references'] ?? '')); ?>">

            
            <label class="b360-compose-field">
                <span>From</span>
                <select x-on:change="accountChanged" aria-label="From email account">
                    <?php $__currentLoopData = $sendAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($account->id); ?>" data-compose-url="<?php echo e(route('mailbox.external.show', [$account, 'compose' => 'new'])); ?>" <?php if($account->id === $mailboxAccount->id): echo 'selected'; endif; ?>><?php echo e($account->name); ?> · <?php echo e($account->email); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>

            
            <section class="b360-email-recipient-section" aria-label="Email recipients">

                <div class="b360-email-recipient-row">
                    <span>To</span>
                    <div class="b360-email-token-field">
                        <template x-for="address in to" x-bind:key="'to-'+address">
                            <span class="b360-email-token">
                                <span x-text="address"></span>
                                <button type="button" data-recipient-type="to" x-bind:data-address="address" x-on:click="removeAddress" aria-label="Remove recipient">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                                <input type="hidden" name="to[]" x-bind:value="address">
                            </span>
                        </template>
                        <input type="email" list="mailbox-contact-suggestions" data-recipient-type="to" x-ref="toInput" x-on:keydown="recipientKeydown" x-on:blur="commitAddress" placeholder="Add recipients" aria-label="To">
                    </div>
                    <div class="b360-recipient-disclosures">
                        <button type="button" x-on:click="showCc">CC</button>
                        <button type="button" x-on:click="showBcc">BCC</button>
                    </div>
                </div>

                <div class="b360-email-recipient-row" x-show="ccVisible" x-cloak>
                    <span>CC</span>
                    <div class="b360-email-token-field">
                        <template x-for="address in cc" x-bind:key="'cc-'+address">
                            <span class="b360-email-token">
                                <span x-text="address"></span>
                                <button type="button" data-recipient-type="cc" x-bind:data-address="address" x-on:click="removeAddress" aria-label="Remove CC recipient">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                                <input type="hidden" name="cc[]" x-bind:value="address">
                            </span>
                        </template>
                        <input type="email" list="mailbox-contact-suggestions" data-recipient-type="cc" x-ref="ccInput" x-on:keydown="recipientKeydown" x-on:blur="commitAddress" aria-label="CC">
                    </div>
                </div>

                <div class="b360-email-recipient-row" x-show="bccVisible" x-cloak>
                    <span>BCC</span>
                    <div class="b360-email-token-field">
                        <template x-for="address in bcc" x-bind:key="'bcc-'+address">
                            <span class="b360-email-token">
                                <span x-text="address"></span>
                                <button type="button" data-recipient-type="bcc" x-bind:data-address="address" x-on:click="removeAddress" aria-label="Remove BCC recipient">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                                <input type="hidden" name="bcc[]" x-bind:value="address">
                            </span>
                        </template>
                        <input type="email" list="mailbox-contact-suggestions" data-recipient-type="bcc" x-ref="bccInput" x-on:keydown="recipientKeydown" x-on:blur="commitAddress" aria-label="BCC">
                    </div>
                </div>

                <p class="b360-recipient-error" x-ref="recipientError" role="alert"></p>

                <datalist id="mailbox-contact-suggestions">
                    <?php $__currentLoopData = $contacts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $contact): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($contact['email']); ?>"><?php echo e($contact['name']); ?> · <?php echo e($contact['source']); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </datalist>

            </section>

            
            <label class="b360-compose-field">
                <span>Subject</span>
                <input name="subject" required maxlength="255" value="<?php echo e(old('subject', $composeData['subject'] ?? '')); ?>" placeholder="Subject">
            </label>

            
            <section class="b360-compose-editor b360-email-editor">
                <div class="b360-compose-formatbar" role="toolbar" aria-label="Email formatting">
                    <button type="button" data-command="bold" x-on:click="format" aria-label="Bold"><i class="fa-solid fa-bold"></i></button>
                    <button type="button" data-command="italic" x-on:click="format" aria-label="Italic"><i class="fa-solid fa-italic"></i></button>
                    <button type="button" data-command="underline" x-on:click="format" aria-label="Underline"><i class="fa-solid fa-underline"></i></button>
                    <div class="b360-toolbar-sep" aria-hidden="true"></div>
                    <button type="button" data-command="insertUnorderedList" x-on:click="format" aria-label="Bulleted list"><i class="fa-solid fa-list-ul"></i></button>
                    <button type="button" x-on:click="createLink" aria-label="Insert link"><i class="fa-solid fa-link"></i></button>
                    <label class="b360-inline-image-button" aria-label="Insert inline image">
                        <i class="fa-regular fa-image"></i>
                        <input type="file" name="inline_images[]" multiple accept="image/jpeg,image/png,image/gif" x-ref="inlineImages" x-on:change="selectInlineImages">
                    </label>
                    <div class="b360-toolbar-sep" aria-hidden="true"></div>
                    <button type="button" data-command="undo" x-on:click="format" aria-label="Undo"><i class="fa-solid fa-rotate-left"></i></button>
                    <button type="button" data-command="redo" x-on:click="format" aria-label="Redo"><i class="fa-solid fa-rotate-right"></i></button>
                </div>
                <div
                    class="b360-email-editor-surface"
                    contenteditable="true"
                    role="textbox"
                    aria-multiline="true"
                    aria-label="Email message"
                    placeholder="Write your message…"
                    x-ref="editor"
                    x-on:input="queueSave"
                ></div>
                <input type="hidden" name="body_html" x-ref="bodyHtml">
                <textarea name="body" hidden x-ref="bodyText"></textarea>
            </section>

            
            <section class="b360-compose-attachment-zone" x-on:dragover.prevent="dragging=true" x-on:dragleave.prevent="dragging=false" x-on:drop.prevent="dropAttachments" x-bind:class="dragging ? 'is-dragging' : ''">
                <label>
                    <i class="fa-solid fa-paperclip"></i>
                    <span>Attach files or drag them here</span>
                    <input type="file" name="attachments[]" multiple x-ref="attachments" x-on:change="selectAttachments" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                </label>
                <small>Up to 10 files · 25 MB each</small>

                <div class="b360-compose-file-grid" x-show="selectedAttachments.length > 0" x-cloak>
                    <template x-for="file in selectedAttachments" x-bind:key="file.key">
                        <article>
                            <template x-if="file.preview"><img x-bind:src="file.preview" alt=""></template>
                            <i class="fa-solid fa-file" x-show="!file.preview"></i>
                            <span><b x-text="file.name"></b><small x-text="file.size"></small></span>
                            <button type="button" x-bind:data-file-key="file.key" x-on:click="removeAttachment" aria-label="Remove attachment"><i class="fa-solid fa-xmark"></i></button>
                        </article>
                    </template>
                </div>

                <?php if($composeDraft?->attachments?->isNotEmpty()): ?>
                <div class="b360-compose-file-grid">
                    <?php $__currentLoopData = $composeDraft->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <article>
                        <i class="fa-solid fa-file"></i>
                        <span><b><?php echo e($file->filename); ?></b><small><?php echo e(number_format($file->size/1024,1)); ?> KB</small></span>
                        <label><input type="checkbox" name="remove_attachment_ids[]" value="<?php echo e($file->id); ?>"> Remove</label>
                    </article>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php endif; ?>
            </section>

            
            <label class="b360-compose-field b360-send-later-field">
                <span>Later</span>
                <input type="datetime-local" name="scheduled_for" value="<?php echo e(old('scheduled_for', $composeDraft?->scheduled_for?->format('Y-m-d\TH:i'))); ?>">
            </label>

            
            <footer class="b360-unified-compose-actions">
                <span class="b360-upload-progress" x-show="uploadProgress > 0 && uploadProgress < 100" x-cloak>
                    <i x-bind:style="'width:'+uploadProgress+'%'"></i>
                </span>
                <button type="button" class="blade-danger-action" x-on:click="discardDraft">Discard</button>
                <button type="submit" class="blade-secondary-action" formaction="<?php echo e(route('mailbox.drafts.store', $mailboxAccount)); ?>" name="state" value="draft">Save draft</button>
                <button type="submit" class="blade-secondary-action" formaction="<?php echo e(route('mailbox.drafts.store', $mailboxAccount)); ?>" name="state" value="scheduled">Send later</button>
                <button type="submit" class="blade-primary-action" x-bind:disabled="busy">
                    <span x-text="busy ? 'Sending…' : 'Send'"></span>
                </button>
            </footer>
        </form>
    </div>
</details>
<?php /**PATH /home/developer/public_html/build365/resources/views/mailbox/external/partials/compose.blade.php ENDPATH**/ ?>