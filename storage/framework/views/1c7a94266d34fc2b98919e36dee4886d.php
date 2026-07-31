<?php $__env->startSection('title','Drafts | '.$mailboxAccount->email); ?>

<?php $__env->startPush('styles'); ?>
<style>
  .b360-content {
        padding: 6px !important;
    }
/* ── TOKENS: white + light blue only ─────────────────────────────── */
.dft {
  --dft-bg:       #F0F6FF;   /* page background — very light blue */
  --dft-surface:  #FFFFFF;   /* card white */
  --dft-blue:     #F6740C;   /* primary accent */
  --dft-blue-lt:  #DBEAFE;   /* chip / badge tint */
  --dft-blue-mid: #93C5FD;   /* subtle border accent */
  --dft-border:   #E0EDFF;   /* dividers */
  --dft-text:     #0F172A;   /* headings */
  --dft-sub:      #475569;   /* secondary copy */
  --dft-muted:    #94A3B8;   /* timestamps, meta */
  --dft-red-text: #DC2626;   /* error text only */
  --dft-red-bg:   #FEF2F2;
  --dft-red-bd:   #FECACA;
  --dft-radius:   12px;
  --dft-ease:     0.14s ease;
}

/* ── PAGE SHELL ───────────────────────────────────────────────────── */
.dft-page {
  min-height: 100%;
  background: var(--dft-bg);
  padding: 32px 24px 48px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  font-size: 14px;
  color: var(--dft-text);
}

/* ── PAGE HEADER ──────────────────────────────────────────────────── */
.dft-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}
.dft-head-copy { min-width: 0; }
.dft-eyebrow {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--dft-blue);
  margin-bottom: 6px;
}
.dft-eyebrow i { font-size: 12px; }
.dft-head h1 {
  font-size: 22px;
  font-weight: 700;
  color: var(--dft-text);
  margin: 0 0 4px;
  line-height: 1.2;
}
.dft-head-email {
  font-size: 13px;
  color: var(--dft-sub);
}
.dft-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: 1.5px solid var(--dft-blue-mid);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  color: var(--dft-blue);
  text-decoration: none;
  background: var(--dft-surface);
  white-space: nowrap;
  flex-shrink: 0;
  transition: background var(--dft-ease), border-color var(--dft-ease);
}
.dft-back:hover {
  background: var(--dft-blue-lt);
  border-color: var(--dft-blue);
}

/* ── DRAFT LIST ───────────────────────────────────────────────────── */
.dft-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* ── DRAFT CARD ───────────────────────────────────────────────────── */
.dft-card {
  background: var(--dft-surface);
  border: 1.5px solid var(--dft-border);
  border-radius: var(--dft-radius);
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: box-shadow var(--dft-ease), border-color var(--dft-ease);
}
.dft-card:hover {
  border-color: var(--dft-blue-mid);
  box-shadow: 0 2px 12px rgba(37,99,235,.07);
}

/* Status dot */
.dft-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--dft-blue-mid);
  flex-shrink: 0;
  margin-top: 2px;
  align-self: flex-start;
}
.dft-dot.is-failed    { background: var(--dft-red-text); }
.dft-dot.is-scheduled { background: #F6740C; }
.dft-dot.is-draft     { background: #93C5FD; }

/* Card body */
.dft-card-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.dft-subject {
  font-size: 14px;
  font-weight: 600;
  color: var(--dft-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dft-recipients {
  font-size: 12px;
  color: var(--dft-sub);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dft-meta {
  font-size: 11px;
  color: var(--dft-muted);
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.dft-meta-sep { opacity: .4; }
.dft-error {
  font-size: 11px;
  color: var(--dft-red-text);
  background: var(--dft-red-bg);
  border: 1px solid var(--dft-red-bd);
  border-radius: 5px;
  padding: 3px 8px;
  margin-top: 2px;
  display: inline-block;
}

/* Card right side */
.dft-card-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

/* State pill */
.dft-pill {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 20px;
  white-space: nowrap;
  background: var(--dft-blue-lt);
  color: var(--dft-blue);
  border: 1px solid #BFDBFE;
}
.dft-pill.is-failed {
  background: var(--dft-red-bg);
  color: var(--dft-red-text);
  border-color: var(--dft-red-bd);
}
.dft-pill.is-scheduled {
  background: #EFF6FF;
  color: #f78020;
  border-color: #BFDBFE;
}

/* Continue / Review button */
.dft-continue {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border: 1.5px solid var(--dft-blue);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--dft-blue);
  background: var(--dft-surface);
  text-decoration: none;
  white-space: nowrap;
  transition: background var(--dft-ease);
}
.dft-continue:hover { background: var(--dft-blue-lt); }
.dft-continue.is-failed {
  border-color: var(--dft-red-text);
  color: var(--dft-red-text);
}
.dft-continue.is-failed:hover { background: var(--dft-red-bg); }

/* Discard button */
.dft-discard {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 1.5px solid var(--dft-border);
  border-radius: 7px;
  background: var(--dft-surface);
  color: var(--dft-muted);
  cursor: pointer;
  font-size: 13px;
  transition: border-color var(--dft-ease), color var(--dft-ease), background var(--dft-ease);
  flex-shrink: 0;
}
.dft-discard:hover {
  border-color: var(--dft-red-bd);
  color: var(--dft-red-text);
  background: var(--dft-red-bg);
}

/* ── EMPTY STATE ──────────────────────────────────────────────────── */
.dft-empty {
  background: var(--dft-surface);
  border: 1.5px dashed var(--dft-border);
  border-radius: var(--dft-radius);
  padding: 56px 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}
.dft-empty-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: var(--dft-blue-lt);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: var(--dft-blue);
  margin-bottom: 4px;
}
.dft-empty strong {
  font-size: 15px;
  font-weight: 700;
  color: var(--dft-text);
}
.dft-empty span {
  font-size: 13px;
  color: var(--dft-sub);
  max-width: 280px;
}

/* ── PAGINATION ───────────────────────────────────────────────────── */
.dft-pagination {
  margin-top: 20px;
}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="dft dft-page">

  
  <div class="dft-head">
    <div class="dft-head-copy">
      <div class="dft-eyebrow">
        <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
        Mailbox
      </div>
      <h1>Drafts &amp; scheduled</h1>
      <div class="dft-head-email"><?php echo e($mailboxAccount->email); ?></div>
    </div>
    <a class="dft-back" href="<?php echo e(route('mailbox.external.show', $mailboxAccount)); ?>">
      <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
      Back to mailbox
    </a>
  </div>

  
  <div class="dft-list">
    <?php $__empty_1 = true; $__currentLoopData = $drafts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php
        $state      = $draft->state;
        $isFailed   = $state === 'failed';
        $isScheduled= $state === 'scheduled';
        $dotClass   = $isFailed ? 'is-failed' : ($isScheduled ? 'is-scheduled' : 'is-draft');
        $pillClass  = $isFailed ? 'is-failed' : ($isScheduled ? 'is-scheduled' : '');
        $btnLabel   = $isFailed ? 'Review &amp; retry' : 'Continue';
        $btnClass   = $isFailed ? 'dft-continue is-failed' : 'dft-continue';
      ?>

      <article class="dft-card">

        
        <span class="dft-dot <?php echo e($dotClass); ?>" aria-hidden="true"></span>

        
        <div class="dft-card-body">
          <div class="dft-subject"><?php echo e($draft->subject ?: '(No subject)'); ?></div>

          <div class="dft-recipients">
            <i class="fa-regular fa-user" aria-hidden="true" style="font-size:11px;margin-right:3px;"></i>
            <?php echo e(collect($draft->to_addresses)->join(', ') ?: 'No recipients yet'); ?>

          </div>

          <div class="dft-meta">
            <span class="dft-pill <?php echo e($pillClass); ?>"><?php echo e(str($state)->headline()); ?></span>
            <span class="dft-meta-sep">·</span>
            <span>Updated <?php echo e($draft->updated_at->diffForHumans()); ?></span>
            <?php if($draft->scheduled_for): ?>
              <span class="dft-meta-sep">·</span>
              <span>
                <i class="fa-regular fa-clock" aria-hidden="true" style="font-size:10px;"></i>
                Sends <?php echo e($draft->scheduled_for->format('d M Y, h:i A')); ?>

              </span>
            <?php endif; ?>
          </div>

          <?php if($draft->last_error): ?>
            <span class="dft-error">
              <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" style="font-size:10px;margin-right:3px;"></i>
              <?php echo e($draft->last_error); ?>

            </span>
          <?php endif; ?>
        </div>

        
        <div class="dft-card-right">
          <a class="<?php echo e($btnClass); ?>"
             href="<?php echo e(route('mailbox.external.show', [$mailboxAccount, 'draft' => $draft->id])); ?>">
            <i class="fa-solid <?php echo e($isFailed ? 'fa-rotate-right' : 'fa-pen'); ?>" aria-hidden="true"></i>
            <?php echo $btnLabel; ?>

          </a>

          <form method="POST"
                action="<?php echo e(route('mailbox.drafts.destroy', [$mailboxAccount, $draft])); ?>"
                onsubmit="return confirm('Discard this draft?')">
            <?php echo csrf_field(); ?>
            <?php echo method_field('DELETE'); ?>
            <button type="submit" class="dft-discard" title="Discard draft" aria-label="Discard draft">
              <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
            </button>
          </form>
        </div>

      </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="dft-empty">
        <div class="dft-empty-icon">
          <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
        </div>
        <strong>No saved drafts</strong>
        <span>Drafts appear here while composing or after a failed delivery.</span>
      </div>
    <?php endif; ?>
  </div>

  
  <?php if($drafts->hasPages()): ?>
    <div class="dft-pagination"><?php echo e($drafts->links()); ?></div>
  <?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.builder360-classic', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/developer/public_html/build365/resources/views/mailbox/drafts/index.blade.php ENDPATH**/ ?>