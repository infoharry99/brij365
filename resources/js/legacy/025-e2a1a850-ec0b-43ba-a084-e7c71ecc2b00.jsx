const React = window.React;

/* ============================================================
   Builder360 — Mailbox: main module (rail · list · reading pane · CRM)
   window.Mailbox
   ============================================================ */
(function () {
  const { Icon, Avatar, Badge, Button, Empty } = window;
  const e = React.createElement;
  const MBOX = window.MBOX;

  const crmName = { contact: "Contact", company: "Company", deal: "Deal", project: "Project", lead: "Lead", booking: "Booking", customer: "Customer", ticket: "Ticket" };
  const mailboxOptions = () => window.Builder360Server?.collaboration_mailbox_options || null;
  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const firstApiError = (payload) => {
    const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
    return errors[0] || payload?.message || "The mailbox request could not be completed.";
  };
  const apiJson = async (url, options = {}) => {
    const response = await fetch(url, {
      ...options,
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        ...(options.headers || {}),
      },
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(firstApiError(body));
    return body;
  };
  const messageUrl = (template, em) => template && em?.recordId ? template.replace("__MESSAGE__", em.recordId) : null;
  const serverUserColor = (id) => ["#2570eb", "#15a657", "#e08600", "#7c3aed", "#0ea5a4", "#dc2f3a", "#F6740C", "#0891b2"][Number(id || 0) % 8];
  const dateLabel = (iso) => iso ? new Date(iso).toLocaleDateString("en-IN", { day: "2-digit", month: "short" }) : "Today";
  const timeLabel = (iso) => iso ? new Date(iso).toLocaleTimeString("en-IN", { hour: "2-digit", minute: "2-digit" }) : "";
  const dateTimeLabel = (iso) => iso ? new Date(iso).toLocaleString("en-IN", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" }) : null;
  const stripHtml = (value) => String(value || "").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
  const emailTokens = (value) => String(value || "").split(",").map(s => s.trim().toLowerCase()).filter(Boolean);
  const mailboxSettingValue = (options) => options?.mailbox_settings?.value || {};
  const mailboxUserState = (row, userId) => row?.metadata?.mailbox_user_state?.["user_" + String(userId)] || row?.metadata?.mailbox_user_state?.[String(userId)] || {};
  const normalizeMailboxAccounts = (options) => {
    const value = mailboxSettingValue(options);
    const configured = Array.isArray(value.accounts) ? value.accounts : [];
    return configured.map((account, index) => ({
      id: account.id || "acc-mailbox-" + index,
      provider: account.provider || "internal_builder360",
      email: account.email || "internal.mailbox@builder360.test",
      name: account.name || account.email || "Builder360 Mailbox",
      authType: account.authType || "Mailbox settings",
      color: account.color || ["#2570eb", "#dc2f3a", "#0ea5a4", "#7c3aed"][index % 4],
      isDefault: account.isDefault === true || index === 0,
      syncStatus: account.syncStatus || "metadata_only",
      lastSync: account.lastSync || (value.external_sync_enabled ? "configured" : "not connected"),
      signature: account.signature || MBOX.sig,
    }));
  };
  const normalizeMailboxLabels = (options) => {
    const value = mailboxSettingValue(options);
    const configured = Array.isArray(value.labels) ? value.labels : [];
    const source = configured.length ? configured : MBOX.labels;
    return source
      .map((label, index) => ({
        id: String(label.id || label.key || "").trim().toLowerCase().replace(/[^a-z0-9_-]/g, "_") || "label_" + index,
        label: String(label.label || label.name || label.id || "Label").trim().slice(0, 40) || "Label",
        color: /^#[0-9a-f]{6}$/i.test(String(label.color || "")) ? label.color : ["#F6740C", "#e08600", "#15a657", "#dc2f3a", "#64748b"][index % 5],
      }))
      .filter(label => label.id && label.label)
      .slice(0, 20);
  };
  const normalizeBlockedSenders = (options) => {
    const value = mailboxSettingValue(options);
    const configured = Array.isArray(value.blocked_senders) ? value.blocked_senders : [];
    return configured
      .map(row => String(row.email || row.sender || row).trim().toLowerCase())
      .filter(email => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
      .filter((email, index, rows) => rows.indexOf(email) === index)
      .slice(0, 500);
  };
  const mailboxDraftValue = (options, patch) => {
    const active = mailboxSettingValue(options);
    return {
      internal_messages_enabled: active.internal_messages_enabled !== false,
      external_sync_enabled: active.external_sync_enabled === true,
      allowed_providers: active.allowed_providers || ["internal_builder360", "google_oauth_metadata", "imap_smtp_metadata"],
      accounts: Array.isArray(active.accounts) ? active.accounts : [],
      sync_scope: active.sync_scope || {
        inbox: true,
        sent: true,
        archived: true,
        trash: false,
        spam: false,
        historical: false,
        frequency: "manual",
      },
      crm_linking: active.crm_linking || {
        auto_match: true,
        auto_create_contacts: false,
        domain_link: true,
        deal_link: true,
        ignore_newsletters: true,
        ignore_no_reply: true,
        review_queue: true,
      },
      notifications: active.notifications || {
        new_email: true,
        failed_sync: true,
        failed_send: true,
        in_app: true,
        desktop: false,
      },
      labels: active.labels || MBOX.labels,
      blocked_senders: Array.isArray(active.blocked_senders) ? active.blocked_senders : [],
      integration_notice: active.integration_notice || "External Gmail, IMAP and SMTP connections require approved provider setup before live use.",
      ...patch,
    };
  };
  const crmLinkFromServer = (row) => {
    const link = row?.metadata?.crm_link;
    if (link && link.record_type && link.record_id) {
      return {
        type: link.record_type,
        id: link.record_id,
        name: link.label || (crmName[link.record_type] || "CRM") + " #" + link.record_id,
        linked: true,
        linkedAt: link.linked_at || null,
        linkedBy: link.linked_by_name || null,
        note: link.note || null,
      };
    }

    if (row.project) {
      return { type: "project", id: row.project.id, name: row.project.name, linked: true, inherited: true };
    }

    return { type: "none", linked: false };
  };
  const transformServerMessage = (row, options) => {
    const currentUserId = Number(options?.current_user_id || window.Builder360Server?.user?.id || 0);
    const isSender = Number(row.sender?.id || 0) === currentUserId;
    const sender = row.sender || {};
    const recipient = row.recipient || {};
    const other = isSender ? recipient : sender;
    const archived = row.status === "archived" || !!row.recipient_archived_at;
    const userState = mailboxUserState(row, currentUserId);
    const stateFolder = ["archived", "spam", "trash", "snoozed"].includes(userState.folder) ? userState.folder : null;
    const folder = row.status === "scheduled" ? "scheduled" : (row.status === "cancelled" ? "drafts" : (archived ? "archived" : (stateFolder || (isSender ? "sent" : "inbox"))));
    const bodyText = stripHtml(row.body);
    const scheduledFor = dateTimeLabel(row.scheduled_for);
    return {
      id: row.message_number || "MSG-" + row.id,
      recordId: row.id,
      folder,
      read: isSender || !!row.read_at || row.status === "read",
      starred: userState.starred === true,
      important: typeof userState.important === "boolean" ? userState.important : ["high", "critical"].includes(row.priority),
      hasAttach: false,
      from: { name: sender.name || sender.email || "Sender", email: sender.email || "", color: serverUserColor(sender.id) },
      to: [{ name: recipient.name || recipient.email || "Recipient", email: recipient.email || "" }],
      subject: row.subject || "(no subject)",
      snippet: bodyText.slice(0, 120),
      body: row.body || "",
      date: row.status === "scheduled" ? (scheduledFor || dateLabel(row.created_at)) : dateLabel(row.sent_at || row.created_at),
      time: row.status === "scheduled" ? "" : timeLabel(row.sent_at || row.created_at),
      labels: Array.isArray(userState.labels) ? userState.labels : ["internal"],
      direction: isSender ? "out" : "in",
      scheduledFor,
      snoozedUntil: userState.snoozed_until ? dateTimeLabel(userState.snoozed_until) : null,
      crm: crmLinkFromServer(row),
      attachments: [],
      threadKey: row.thread_key,
      parentMessageId: row.parent_message_id,
      priority: row.priority || "normal",
      thread: [{
        id: "srv-msg-" + row.id,
        you: isSender,
        from: isSender ? "You" : (sender.name || sender.email || "Sender"),
        color: serverUserColor(sender.id),
        time: row.status === "scheduled" ? (scheduledFor || timeLabel(row.created_at)) : timeLabel(row.sent_at || row.created_at),
        body: row.body || "",
        attachments: [],
      }],
      server: row,
    };
  };

  // virtual folders compute differently
  const inFolder = (em, folder) => {
    if (folder === "starred") return em.starred && em.folder !== "trash";
    if (folder === "important") return em.important && em.folder !== "trash";
    return em.folder === folder;
  };

  function MailboxShellConfirmModal({ confirm, onCancel }) {
    if (!confirm) return null;

    return e("div", { onClick: onCancel, style: { position: "fixed", inset: 0, zIndex: 1100, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("div", { onClick: ev => ev.stopPropagation(), style: { width: "min(460px,94vw)", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, confirm.title), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, confirm.message)),
          e("button", { type: "button", className: "icon-btn", onClick: onCancel }, e(Icon, { name: "x" }))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e("div", { className: "muted", style: { fontSize: 12 } }, confirm.note || "Confirm to continue."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onCancel, children: "Cancel" }), e(Button, { type: "button", variant: confirm.variant || "primary", icon: "check", onClick: confirm.onConfirm, children: confirm.confirmLabel || "Confirm" })))));
  }

  function MailboxTaskModal({ em, taskOptions, onClose, toast }) {
    const assignees = taskOptions?.assignees || [];
    const projects = taskOptions?.projects || [];
    const crm = em?.crm || {};
    const currentUserId = Number(taskOptions?.current_user_id || window.Builder360Server?.user?.id || 0);
    const defaultAssignee = assignees.find(user => Number(user.id) === currentUserId) || assignees[0] || null;
    const messageProjectId = em?.server?.project?.id || (crm.type === "project" ? crm.id : null);
    const defaultProject = projects.find(project => Number(project.id) === Number(messageProjectId)) || null;
    const [form, setForm] = React.useState(() => ({
      title: "Follow up: " + (em?.subject || "Mailbox message"),
      description: stripHtml(em?.snippet || em?.body || "").slice(0, 500),
      assigned_to_user_id: defaultAssignee ? String(defaultAssignee.id) : "",
      project_id: defaultProject ? String(defaultProject.id) : "",
      priority: em?.important ? "high" : "medium",
      due_at: "",
    }));
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 12px", background: "var(--surface)", color: "var(--text)" };
    const set = key => ev => setForm(prev => ({ ...prev, [key]: ev.target.value }));

    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!taskOptions?.can_create || !taskOptions?.store_url) {
        setError("Your role cannot create tasks from mailbox.");
        return;
      }
      if (!form.title.trim()) {
        setError("Task title is required.");
        return;
      }
      if (!form.assigned_to_user_id) {
        setError("Select an available assignee.");
        return;
      }
      try {
        setBusy(true);
        const payload = {
          title: form.title.trim(),
          description: [
            form.description.trim(),
            "Mailbox source: " + (em?.id || "message"),
            crm?.linked ? "Linked CRM: " + (crmName[crm.type] || crm.type) + " #" + crm.id : "Linked CRM: none",
          ].filter(Boolean).join("\n\n"),
          assigned_to_user_id: Number(form.assigned_to_user_id),
          priority: form.priority,
          due_at: form.due_at || undefined,
          module_context: "mailbox",
          related_type: em?.recordId ? "App\\Models\\CollaborationMessage" : undefined,
          related_id: em?.recordId || undefined,
          project_id: form.project_id ? Number(form.project_id) : undefined,
          checklist: [
            { label: "Review mailbox conversation", done: false },
            { label: "Update linked CRM/customer record if required", done: false },
          ],
          metadata: {
            source: "mailbox_quick_action",
            mailbox_message_number: em?.id || null,
            mailbox_message_id: em?.recordId || null,
            mailbox_subject: em?.subject || null,
            mailbox_direction: em?.direction || null,
            crm_link: crm?.linked ? { type: crm.type, id: crm.id, name: crm.name || null } : null,
          },
        };
        const body = await apiJson(taskOptions.store_url, { method: "POST", body: JSON.stringify(payload) });
        toast && toast("Task " + (body.data?.task_number || "") + " created from mailbox.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Task could not be created from mailbox.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { onClick: onClose, style: { position: "fixed", inset: 0, zIndex: 1200, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(560px,94vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, "Create task from mailbox"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, em?.subject || "Selected mailbox message")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { className: "mbx-banner warn", style: { marginBottom: 12 } }, e(Icon, { name: "alert", size: 15 }), error),
        !taskOptions?.can_create && e("div", { className: "mbx-banner info", style: { marginBottom: 12 } }, e(Icon, { name: "shield", size: 15 }), "Read-only: collaboration task creation permission is required."),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 12 } }, "Task title", e("input", { style: field, value: form.title, onChange: set("title"), required: true, maxLength: 255, autoFocus: true })),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 12 } }, "Description", e("textarea", { style: { ...field, minHeight: 96, resize: "vertical" }, value: form.description, onChange: set("description"), maxLength: 5000 })),
        e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 12 } },
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" } }, "Assignee",
            e("select", { style: field, value: form.assigned_to_user_id, onChange: set("assigned_to_user_id"), required: true },
              e("option", { value: "" }, "Select assignee"),
              assignees.map(user => e("option", { key: user.id, value: user.id }, user.name + (user.role ? " · " + user.role : ""))))),
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" } }, "Priority",
            e("select", { style: field, value: form.priority, onChange: set("priority"), required: true },
              (taskOptions?.priorities || ["low", "medium", "high", "critical"]).map(priority => e("option", { key: priority, value: priority }, priority))))),
        e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 12 } },
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" } }, "Project",
            e("select", { style: field, value: form.project_id, onChange: set("project_id") },
              e("option", { value: "" }, "No project"),
              projects.map(project => e("option", { key: project.id, value: project.id }, (project.code ? project.code + " · " : "") + project.name)))),
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" } }, "Due date / time", e("input", { type: "datetime-local", style: field, value: form.due_at, onChange: set("due_at") }))),
        e("div", { className: "mbx-banner info", style: { marginBottom: 14 } }, e(Icon, { name: "link", size: 15 }), "The task will keep the selected message and CRM link for reference."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Saved to Task Management."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { type: "submit", variant: "primary", icon: "check", disabled: busy || !taskOptions?.can_create, children: busy ? "Creating…" : "Create task" })))));
  }

  function MailboxLeadNoteModal({ em, mailboxOptions, onClose, toast }) {
    const crm = em?.crm || {};
    const [form, setForm] = React.useState(() => ({
      subject: "Mailbox note: " + (em?.subject || "Lead follow-up"),
      description: stripHtml(em?.body || em?.snippet || "").slice(0, 1000),
      outcome: "mailbox_note",
    }));
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 12px", background: "var(--surface)", color: "var(--text)" };
    const canCreate = !!(mailboxOptions?.can_create_lead_activity && mailboxOptions?.lead_activity_store_url && crm?.type === "lead" && crm?.id);
    const set = key => ev => setForm(prev => ({ ...prev, [key]: ev.target.value }));

    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!canCreate) {
        setError("Lead activity creation is available only for lead-linked mailbox messages with CRM activity permission.");
        return;
      }
      if (!form.subject.trim()) {
        setError("Subject is required.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(mailboxOptions.lead_activity_store_url, {
          method: "POST",
          body: JSON.stringify({
            lead_id: Number(crm.id),
            activity_type: "note",
            subject: form.subject.trim(),
            description: form.description.trim(),
            outcome: form.outcome.trim() || "mailbox_note",
            metadata: {
              source: "mailbox_quick_action",
              mailbox_action: "note",
              mailbox_message_number: em?.id || null,
              mailbox_message_id: em?.recordId || null,
              mailbox_subject: em?.subject || null,
              mailbox_direction: em?.direction || null,
            },
          }),
        });
        toast && toast("Lead activity " + (body.data?.activity_number || "") + " saved from mailbox.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Mailbox note could not be saved to lead activity.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { onClick: onClose, style: { position: "fixed", inset: 0, zIndex: 1200, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(560px,94vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, "Add lead note from mailbox"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, crm?.name || "Linked lead")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { className: "mbx-banner warn", style: { marginBottom: 12 } }, e(Icon, { name: "alert", size: 15 }), error),
        !canCreate && e("div", { className: "mbx-banner info", style: { marginBottom: 12 } }, e(Icon, { name: "shield", size: 15 }), "Read-only: link the message to a Lead and use a role with CRM activity permission."),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 12 } }, "Subject", e("input", { style: field, value: form.subject, onChange: set("subject"), required: true, maxLength: 255, autoFocus: true })),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 12 } }, "Note", e("textarea", { style: { ...field, minHeight: 130, resize: "vertical" }, value: form.description, onChange: set("description"), maxLength: 5000 })),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 14 } }, "Outcome", e("input", { style: field, value: form.outcome, onChange: set("outcome"), maxLength: 80 })),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Saved to the linked lead timeline."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { type: "submit", variant: "primary", icon: "check", disabled: busy || !canCreate, children: busy ? "Saving..." : "Save note" })))));
  }

  function MailboxLabelSettingsModal({ labels, onClose, onSave }) {
    const [rows, setRows] = React.useState(() => labels.map(label => ({ ...label })));
    const [error, setError] = React.useState("");
    const [busy, setBusy] = React.useState(false);
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 12px", background: "var(--surface)", color: "var(--text)" };
    const setRow = (index, key, value) => setRows(current => current.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row));
    const addRow = () => setRows(current => [...current, { id: "label_" + (current.length + 1), label: "New Label", color: "#F6740C" }]);
    const removeRow = index => setRows(current => current.filter((_, rowIndex) => rowIndex !== index));

    const submit = async ev => {
      ev.preventDefault();
      setError("");
      const normalized = rows.map(row => ({
        id: String(row.id || "").trim().toLowerCase().replace(/[^a-z0-9_-]/g, "_"),
        label: String(row.label || "").trim(),
        color: String(row.color || "").trim(),
      }));
      const ids = normalized.map(row => row.id);
      if (!normalized.length) {
        setError("At least one mailbox label is required.");
        return;
      }
      if (normalized.some(row => !/^[a-z0-9_-]{2,40}$/.test(row.id))) {
        setError("Label ids must be 2-40 lowercase letters, numbers, underscores or hyphens.");
        return;
      }
      if (new Set(ids).size !== ids.length) {
        setError("Label ids must be unique.");
        return;
      }
      if (normalized.some(row => !row.label || row.label.length > 40)) {
        setError("Label names are required and must not exceed 40 characters.");
        return;
      }
      if (normalized.some(row => !/^#[0-9a-f]{6}$/i.test(row.color))) {
        setError("Label colors must use #RRGGBB format.");
        return;
      }
      setBusy(true);
      try {
        await onSave(normalized);
        onClose();
      } finally {
        setBusy(false);
      }
    };

    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1200, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(640px,94vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, "Manage mailbox labels"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates a mailbox label settings draft for approval.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { className: "mbx-banner warn", style: { marginBottom: 12 } }, e(Icon, { name: "alert", size: 15 }), error),
        rows.map((row, index) => e("div", { key: index, className: "row gap-2", style: { alignItems: "end", marginBottom: 10 } },
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", flex: 1 } }, "Label id", e("input", { style: field, value: row.id, disabled: busy, onChange: ev => setRow(index, "id", ev.target.value), required: true, maxLength: 40 })),
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", flex: 1 } }, "Name", e("input", { style: field, value: row.label, disabled: busy, onChange: ev => setRow(index, "label", ev.target.value), required: true, maxLength: 40 })),
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", width: 120 } }, "Color", e("input", { style: field, value: row.color, disabled: busy, onChange: ev => setRow(index, "color", ev.target.value), required: true, maxLength: 7 })),
          e("button", { type: "button", className: "mbx-abtn", disabled: busy || rows.length <= 1, title: "Remove label", onClick: () => removeRow(index) }, e(Icon, { name: "trash", size: 15 })))),
        e("div", { className: "mbx-banner info", style: { marginBottom: 14 } }, e(Icon, { name: "shield", size: 15 }), "Label changes become active after settings approval."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e(Button, { type: "button", icon: "plus", onClick: addRow, disabled: busy || rows.length >= 20, children: "Add label" }),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, disabled: busy, children: "Cancel" }), e(Button, { type: "submit", variant: "primary", icon: "check", disabled: busy, children: busy ? "Saving..." : "Create settings draft" })))));
  }

  function Mailbox({ role, toast }) {
    const options = mailboxOptions();
    const taskOptions = window.Builder360Server?.collaboration_task_options || null;
    const hasMailboxApi = !!(options && options.index_url);
    const [emails, setEmails] = React.useState(() => []);
    const [apiState, setApiState] = React.useState(() => ({ loading: !!hasMailboxApi, connected: false, error: null }));
    const [accounts, setAccounts] = React.useState(() => normalizeMailboxAccounts(options));
    const [labels, setLabels] = React.useState(() => normalizeMailboxLabels(options));
    const [connected, setConnected] = React.useState(() => normalizeMailboxAccounts(options).length > 0);
    const [view, setView] = React.useState("inbox"); // inbox | connect | settings
    const [acct, setAcct] = React.useState("all");
    const [folder, setFolder] = React.useState("inbox");
    const [sel, setSel] = React.useState(null);
    const [q, setQ] = React.useState("");
    const [filters, setFilters] = React.useState({ unread: false, attach: false, linked: false });
    const [checked, setChecked] = React.useState(() => new Set());
    const [compose, setCompose] = React.useState(null);
    const [syncing, setSyncing] = React.useState(false);
    const [acctMenu, setAcctMenu] = React.useState(false);
    const [rowMenu, setRowMenu] = React.useState(null); // {id, kind}
    const [collapsed, setCollapsed] = React.useState(() => new Set());
    const [linkPanel, setLinkPanel] = React.useState(false);
    const [confirm, setConfirm] = React.useState(null);
    const [labelSettingsOpen, setLabelSettingsOpen] = React.useState(false);
    const [fullScreen, setFullScreen] = React.useState(false);
    const mailboxApiRequiredMessage = "Mailbox records are not available for your current access. Connect or configure mailbox settings to view messages.";
    const labelById = id => labels.find(label => label.id === id);
    const blockedSenders = normalizeBlockedSenders(options);

    React.useEffect(() => {
      const nextAccounts = normalizeMailboxAccounts(options);
      setAccounts(nextAccounts);
      setConnected(nextAccounts.length > 0);
      setLabels(normalizeMailboxLabels(options));
    }, [options?.mailbox_settings?.id, options?.mailbox_settings?.version]);

    const upRow = (id, patch) => setEmails(es => es.map(x => x.id === id ? { ...x, ...(typeof patch === "function" ? patch(x) : patch) } : x));
    const replaceFromServer = (row) => {
      const next = transformServerMessage(row, options);
      setEmails(es => es.some(x => x.recordId === next.recordId || x.id === next.id)
        ? es.map(x => (x.recordId === next.recordId || x.id === next.id) ? next : x)
        : [next, ...es]);
      return next;
    };
    const loadMailboxMessages = React.useCallback((silent = false) => {
      if (!hasMailboxApi) return Promise.resolve();
      if (!silent) setApiState({ loading: true, connected: false, error: null });

      return apiJson(options.index_url)
        .then(body => {
          const rows = (body.data || []).map(row => transformServerMessage(row, options));
          setEmails(rows);
          setSel(current => current && rows.some(row => row.id === current) ? current : null);
          setApiState({ loading: false, connected: true, error: null });
        })
        .catch(error => {
          if (!silent) {
            setEmails([]);
            setSel(null);
            setApiState({ loading: false, connected: false, error: error.message });
            toast("Mailbox records could not be loaded. The mailbox is read-only until records are available. " + error.message, "orange");
            return;
          }

          setApiState(state => ({ ...state, loading: false, error: error.message }));
          toast("Mailbox refresh failed: " + (error.message || "Unknown error"), "orange");
        });
    }, [hasMailboxApi, options?.index_url, options?.current_user_id, toast]);
    const selected = emails.find(x => x.id === sel);

    React.useEffect(() => {
      loadMailboxMessages(false).catch(() => {});
    }, [loadMailboxMessages]);
    React.useEffect(() => {
      if (!hasMailboxApi || view !== "inbox") return;
      const seconds = Number(options?.mailbox_refresh_interval_seconds || 30);
      const intervalMs = Math.max(10, seconds) * 1000;
      const timer = window.setInterval(() => {
        if (document.hidden) return;
        loadMailboxMessages(true).catch(() => {});
      }, intervalMs);

      return () => window.clearInterval(timer);
    }, [hasMailboxApi, view, options?.mailbox_refresh_interval_seconds, loadMailboxMessages]);

    // ----- filtering -----
    const visible = emails.filter(em => {
      const isLabelFolder = folder.startsWith("label:");
      const senderEmail = String(em?.from?.email || "").trim().toLowerCase();
      if (senderEmail && blockedSenders.includes(senderEmail) && !["spam", "trash"].includes(folder)) return false;
      if (!isLabelFolder && !inFolder(em, folder)) return false;
      if (acct !== "all") {
        const a = accounts.find(x => x.id === acct);
        if (a && em.from.email !== a.email && !(em.to || []).some(t => t.email === a.email)) {
          // keep if account email appears anywhere; otherwise drop
          if (em.from.email !== a.email) return false;
        }
      }
      if (filters.unread && em.read) return false;
      if (filters.attach && !em.hasAttach) return false;
      if (filters.linked && !(em.crm && em.crm.linked)) return false;
      if (isLabelFolder) {
        const labelId = folder.slice(6);
        if (!(em.labels || []).includes(labelId)) return false;
      }
      if (q.trim()) {
        const s = (em.from.name + " " + em.from.email + " " + em.subject + " " + em.snippet).toLowerCase();
        if (!s.includes(q.toLowerCase())) return false;
      }
      return true;
    });

    const folderUnread = (fid) => emails.filter(em => inFolder(em, fid) && !em.read).length;
    const folderTotal = (fid) => emails.filter(em => inFolder(em, fid)).length;

    const openEmail = (em) => {
      setSel(em.id);
      setLinkPanel(false);
      if (!em.read) {
        if (hasMailboxApi && em.recordId && options.read_url_template && em.direction !== "out") {
          apiJson(messageUrl(options.read_url_template, em), { method: "PATCH" })
            .then(body => replaceFromServer(body.data))
            .catch(error => toast(error.message, "red"));
          return;
        }
        toast("Read state was not saved for your current access.", "orange");
      }
    };
      const archiveEmail = (em) => {
        if (hasMailboxApi && em?.recordId && options.archive_url_template && em.direction !== "out") {
          apiJson(messageUrl(options.archive_url_template, em), { method: "PATCH" })
            .then(body => { replaceFromServer(body.data); setSel(null); toast("Mailbox message " + body.data.message_number + " archived.", "green"); })
            .catch(error => toast(error.message, "red"));
          return true;
        }
        toast("Archive was not saved for your current access.", "orange");
        return false;
      };
      const cancelScheduledEmail = (em) => {
        if (hasMailboxApi && em?.recordId && options.cancel_scheduled_url_template) {
          apiJson(messageUrl(options.cancel_scheduled_url_template, em), {
            method: "PATCH",
            body: JSON.stringify({ reason: "Cancelled from mailbox scheduled view" }),
          })
            .then(body => {
              replaceFromServer(body.data);
              toast("Scheduled mailbox message cancelled.", "green");
            })
            .catch(error => toast(error.message, "red"));
          return;
        }

        toast("Scheduled send cancellation was not saved for your current access.", "orange");
      };
      const updateMailboxState = (em, payload, fallbackPatch, successMessage) => {
      if (hasMailboxApi && em?.recordId && options?.state_url_template && options?.can_update_state) {
        apiJson(messageUrl(options.state_url_template, em), {
          method: "PATCH",
          body: JSON.stringify(payload),
        })
          .then(body => {
            replaceFromServer(body.data);
            toast(successMessage || body.message || "Mailbox state updated.", "green");
          })
          .catch(error => toast(error.message, "red"));
        return;
      }

      toast("Mailbox state was not saved for your current access.", "orange");
    };
    const exportSelectedMessage = (em, format = "pdf") => {
      if (!hasMailboxApi || !options?.export_url || !em?.recordId) {
        toast("Printable mailbox export requires a saved selected mailbox message.", "orange");
        return;
      }

      const url = new URL(options.export_url, window.location.origin);
      url.searchParams.set("folder", "all");
      url.searchParams.set("message_id", String(em.recordId));
      url.searchParams.set("format", format);
      setRowMenu(null);
      window.open(url.toString(), "_blank", "noopener");
      toast("Mailbox message export requested.", "green");
    };
    const snoozeEmail = (em) => {
      const until = new Date();
      until.setDate(until.getDate() + 1);
      until.setHours(9, 0, 0, 0);
      const iso = until.toISOString();

      updateMailboxState(
        em,
        { action: "snooze", snoozed_until: iso, note: "Snoozed from Mailbox screen." },
        { folder: "snoozed", snoozedUntil: dateTimeLabel(iso) },
        "Snoozed until " + dateTimeLabel(iso) + "."
      );
      setRowMenu(null);
      setSel(null);
    };
    const blockSender = async (em) => {
      const senderEmail = String(em?.from?.email || "").trim().toLowerCase();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(senderEmail)) {
        toast("A valid sender email is required before a mailbox sender block can be created.", "orange");
        return;
      }

      const activeValue = mailboxSettingValue(options);
      const existingRows = Array.isArray(activeValue.blocked_senders) ? activeValue.blocked_senders : [];
      const nextRows = [
        ...existingRows.filter(row => String(row.email || row.sender || row).trim().toLowerCase() !== senderEmail),
        {
          email: senderEmail,
          name: em?.from?.name || senderEmail,
          blocked_at: new Date().toISOString(),
          source: "mailbox_screen_sender_block",
          message_id: em?.recordId || null,
          message_number: em?.id || null,
        },
      ].slice(-500);

      const created = await createMailboxSettingsDraft({
        blocked_senders: nextRows,
      }, "Sender block is pending approval.");

      if (!created) return;

      if (hasMailboxApi && em?.recordId && options?.state_url_template && options?.can_update_state) {
        updateMailboxState(em, { action: "move", folder: "spam" }, { folder: "spam" }, "Sender block draft created and message moved to spam.");
        setSel(null);
        return;
      }

      toast("Sender block draft created. Moving the message to spam requires mailbox state permission.", "orange");
    };
    const updateCrmLink = (em, candidate = null, action = "link") => {
      if (!hasMailboxApi || !em?.recordId || !options?.crm_link_url_template || !options?.can_link_crm) {
        toast("CRM linking is available only for saved internal mailbox messages with CRM-link access.", "orange");
        return;
      }

      const payload = action === "unlink"
        ? { action: "unlink" }
        : { action: "link", record_type: candidate?.type, record_id: candidate?.id };

      apiJson(messageUrl(options.crm_link_url_template, em), {
        method: "PATCH",
        body: JSON.stringify(payload),
      })
        .then(body => {
          replaceFromServer(body.data);
          setLinkPanel(false);
          toast(body.message || (action === "unlink" ? "CRM link removed." : "CRM record linked."), "green");
        })
        .catch(error => toast(error.message, "red"));
    };

    // ----- compose helpers -----
    const newCompose = (patch) => setCompose(Object.assign({
      accountId: accounts[0] ? accounts[0].id : null, to: "", cc: "", bcc: "", subject: "", body: "", title: "New message",
      min: false, full: false, attachments: [],
    }, patch || {}));

    const replyTo = (em, all) => {
      const last = (em.thread && em.thread.length) ? em.thread[em.thread.length - 1] : null;
      const quote = "<br><br><div style='border-left:2px solid var(--border-strong);padding-left:12px;color:var(--text-3)'>On " + (last ? last.time : em.time) + ", " + em.from.name + " wrote:<br>" + (em.snippet) + "</div>";
      newCompose({
        to: em.direction === "out" ? (em.to[0] ? em.to[0].email : "") : em.from.email,
        cc: all && em.cc ? em.cc.map(c => c.email).join(", ") : "",
        subject: (/^re:/i.test(em.subject) ? "" : "Re: ") + em.subject,
        body: quote, title: (all ? "Reply all" : "Reply") + " · " + em.subject,
        replyToId: em.id,
        replyToRecordId: em.recordId || null,
      });
    };
    const forward = (em) => newCompose({
      subject: (/^fwd:/i.test(em.subject) ? "" : "Fwd: ") + em.subject,
      body: "<br><br>---------- Forwarded message ----------<br>From: " + em.from.name + " &lt;" + em.from.email + "&gt;<br>Subject: " + em.subject + "<br><br>" + em.snippet,
      attachments: em.attachments || [], title: "Forward · " + em.subject,
    });

    const doSend = (c, scheduled) => {
      if (hasMailboxApi && options.can_create) {
        if (scheduled && !options.can_schedule_send) {
          toast("Your role cannot schedule mailbox messages.", "red");
          return;
        }

        const recipientMap = new Map((options.recipients || []).map(recipient => [String(recipient.email || "").toLowerCase(), recipient]));
        const recipientIds = emailTokens(c.to).map(email => recipientMap.get(email)?.id).filter(Boolean);

        if (!recipientIds.length) {
          toast("Select at least one internal Builder360 recipient from the configured mailbox user list.", "red");
          return;
        }

        apiJson(options.store_url, {
          method: "POST",
          body: JSON.stringify({
            recipient_user_ids: recipientIds,
            parent_message_id: c.replyToRecordId || undefined,
            subject: c.subject || "(no subject)",
            body: c.body || "",
            priority: c.priority || "normal",
            scheduled_for: scheduled ? (c.scheduled_for || c.scheduleFor) : undefined,
            metadata: {
              source: "mailbox_screen",
              raw_recipient_text: c.to || "",
              scheduled_label: scheduled ? (c.scheduleAt || null) : null,
            },
          }),
        })
          .then(body => {
            (body.data || []).forEach(replaceFromServer);
            setApiState(s => ({ ...s, connected: true, error: null }));
            setCompose(null);
            toast((body.data || []).length + (scheduled ? " internal mailbox message(s) scheduled." : " internal mailbox message(s) sent."), "green");
          })
          .catch(error => toast(error.message, "red"));
        return;
      }

      toast(scheduled ? "Scheduled message was not saved for your current access." : "Message was not sent. Check your recipient and mailbox access.", "orange");
    };
    const saveDraft = (c) => {
      toast("Draft was not saved. Draft saving is not available yet.", "orange");
    };
    const discard = () => {
      if (compose && (compose.subject || (compose.body && compose.body.replace(/<[^>]+>/g, "").trim()) || compose.to)) {
        setConfirm({
          title: "Discard message?",
          message: "Unsaved recipients, subject, body and attachments will be lost.",
          confirmLabel: "Discard",
          variant: "danger",
          note: "This only closes the current compose draft.",
          onConfirm: () => { setConfirm(null); setCompose(null); },
        });
        return;
      }
      setCompose(null);
    };

    // ----- bulk -----
    const toggleCheck = (id) => setChecked(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
    const clearChecks = () => setChecked(new Set());
    const bulkMailboxState = (payloadFor, fallbackPatchFor, msg, predicate = () => true) => {
      const selectedRows = emails.filter(em => checked.has(em.id) && predicate(em));

      if (!selectedRows.length) {
        toast("No selected mailbox messages support this action.", "orange");
        return;
      }

      if (hasMailboxApi && options?.state_url_template && options?.can_update_state) {
        Promise.all(selectedRows.map(em => apiJson(messageUrl(options.state_url_template, em), {
          method: "PATCH",
          body: JSON.stringify(payloadFor(em)),
        })))
          .then(results => {
            results.forEach(body => replaceFromServer(body.data));
            if (selectedRows.some(em => em.id === sel && ["archived", "spam", "trash"].includes(payloadFor(em).folder))) setSel(null);
            toast(msg + " · " + selectedRows.length + " conversation" + (selectedRows.length > 1 ? "s" : ""), "green");
            clearChecks();
          })
          .catch(error => toast(error.message, "red"));
        return;
      }

      toast(msg + " was not saved. Bulk mailbox actions are available only for saved messages.", "orange");
      clearChecks();
    };

    const createMailboxSettingsDraft = async (patch, message) => {
      if (!options?.can_manage_settings || !options?.system_settings_store_url) {
        toast("Mailbox settings are managed from System Settings. You need settings access to create a draft.", "orange");
        return false;
      }

      try {
        const body = await apiJson(options.system_settings_store_url, {
          method: "POST",
          body: JSON.stringify({
            setting_group: "collaboration",
            setting_key: options.mailbox_settings_key || "collaboration.mailbox_settings",
            label: "Collaboration Mailbox Settings",
            description: "Governed mailbox account metadata, internal message controls, CRM linking preferences and notification settings.",
            value_type: "object",
            value: mailboxDraftValue(options, patch),
            effective_from: new Date().toISOString().slice(0, 10),
            metadata: { source: "mailbox_settings_screen" },
          }),
        });
        toast((body.message || "Mailbox settings draft created.") + " Approve it from Administration → System Settings before it becomes active. " + (message || ""), "green");
        return true;
      } catch (error) {
        toast(error.message || "Mailbox settings draft could not be created.", "red");
        return false;
      }
    };

    const saveMailboxLabels = async nextLabels => {
      const created = await createMailboxSettingsDraft({
        labels: nextLabels,
      }, "Mailbox label changes are pending approval.");
      if (created) setLabels(nextLabels);
    };

    // ----- sync -----
    const doSync = () => {
      if (syncing) return;
      setSyncing(true);
      if (hasMailboxApi && options.index_url) {
        loadMailboxMessages(true)
          .then(() => toast("Mailbox refreshed. External Gmail/IMAP sync is not connected in this setup.", "green"))
          .finally(() => setSyncing(false));
        return;
      }
      setTimeout(() => {
        setSyncing(false);
        toast("External mailbox sync is not configured. Only internal mailbox records are available.", "orange");
      }, 700);
    };

    // ----- account actions -----
    const connectGmail = async (kind, imapData = {}) => {
      const activeAccounts = Array.isArray(mailboxSettingValue(options).accounts) ? mailboxSettingValue(options).accounts : [];
      const nextAccount = kind === "imap"
        ? {
            id: "acc-imap-metadata-" + Date.now(),
            provider: "imap_smtp_metadata",
            email: imapData.email,
            name: imapData.name || imapData.email,
            authType: "IMAP / SMTP metadata",
            color: "#0ea5a4",
            isDefault: activeAccounts.length === 0,
            syncStatus: "pending_approval",
            lastSync: "not connected",
            imapHost: imapData.imapHost,
            imapPort: imapData.imapPort,
            smtpHost: imapData.smtpHost,
            smtpPort: imapData.smtpPort,
            imapEnc: imapData.imapEnc,
            smtpEnc: imapData.smtpEnc,
            signature: MBOX.sig,
          }
        : {
            id: "acc-google-metadata-" + Date.now(),
            provider: "google_oauth_metadata",
            email: "sales@builder360.example",
            name: "Google Workspace Metadata",
            authType: "Google Workspace metadata",
            color: "#dc2f3a",
            isDefault: activeAccounts.length === 0,
            syncStatus: "pending_approval",
            lastSync: "not connected",
            signature: MBOX.sig,
          };

      const created = await createMailboxSettingsDraft({
        external_sync_enabled: false,
        accounts: [...activeAccounts.filter(account => account.email !== nextAccount.email), nextAccount],
      }, "No OAuth or IMAP/SMTP connection was attempted.");
      if (created) setView("settings");
    };
    const disconnect = async (id) => {
      setConfirm({
        title: "Remove mailbox metadata?",
        message: "This creates a System Settings draft. Active mailbox settings change only after approval.",
        confirmLabel: "Create Draft",
        note: "No external mailbox connection is modified from this screen.",
        onConfirm: async () => {
          setConfirm(null);
          const activeAccounts = Array.isArray(mailboxSettingValue(options).accounts) ? mailboxSettingValue(options).accounts : [];
          const created = await createMailboxSettingsDraft({
            accounts: activeAccounts.filter(account => account.id !== id),
          }, "Account removal is pending approval.");
          if (created) setView("settings");
        },
      });
    };

    // close menus on outside click
    React.useEffect(() => {
      const h = () => { setAcctMenu(false); setRowMenu(null); };
      if (acctMenu || rowMenu) { window.addEventListener("click", h); return () => window.removeEventListener("click", h); }
    }, [acctMenu, rowMenu]);
    React.useEffect(() => {
      if (!fullScreen) return undefined;
      const h = ev => {
        if (ev.key === "Escape") setFullScreen(false);
      };
      window.addEventListener("keydown", h);
      return () => window.removeEventListener("keydown", h);
    }, [fullScreen]);

    // ============ EMPTY: not connected ============
    if (!connected && view !== "connect") {
      return e("div", { className: "mbx" }, e("div", { style: { flex: 1, display: "grid", placeItems: "center", padding: 30 } },
        e("div", { style: { textAlign: "center", maxWidth: 420 } },
          e("div", { className: "empty-ic", style: { width: 72, height: 72, margin: "0 auto 18px", borderRadius: 20 } }, e(Icon, { name: "mail", size: 32 })),
          e("h2", { style: { fontSize: 22, marginBottom: 8 } }, "Configure mailbox metadata"),
          e("div", { className: "page-sub", style: { margin: "0 auto 20px" } }, "Internal messages are available now. External Gmail/IMAP providers require approved connection settings before live use."),
          e("button", { className: "btn btn-primary", style: { margin: "0 auto", height: 44, padding: "0 22px" }, onClick: () => setView("connect") }, e(Icon, { name: "plus", size: 16 }), "Configure provider"))));
    }

    if (view === "connect") return e("div", { className: "content-inner" }, e(window.MbxConnectView, { accounts, onConnectGmail: connectGmail, onBack: () => setView(connected ? "inbox" : "connect"), toast, mailboxOptions: options }));
    if (view === "settings") return e("div", { className: "content-inner" }, e(window.MbxSettingsView, { accounts, onBack: () => setView("inbox"), onDisconnect: disconnect, toast, mailboxOptions: options, onCreateSettingsDraft: createMailboxSettingsDraft }));

    const activeAcct = acct === "all" ? null : accounts.find(a => a.id === acct);

    // ============ RAIL ============
    const rail = e("div", { className: "mbx-rail" },
      e("div", { className: "mbx-rail-head" },
        e("button", { className: "mbx-compose", onClick: () => newCompose() }, e(Icon, { name: "pencil", size: 17 }), "Compose")),
      // account switcher
      e("div", { className: "mbx-acct-wrap" },
        e("div", { className: "mbx-acct", onClick: (ev) => { ev.stopPropagation(); setAcctMenu(o => !o); } },
          e("span", { className: "mbx-acct-dot", style: { background: activeAcct ? activeAcct.color : "var(--accent)" } }),
          e("div", { className: "mbx-acct-main" },
            e("div", { className: "mbx-acct-email" }, activeAcct ? activeAcct.email : "All accounts"),
            e("div", { className: "mbx-acct-sub" }, activeAcct ? activeAcct.authType : accounts.length + " connected")),
          e(Icon, { name: "chevD", size: 15, style: { color: "var(--text-3)" } })),
        acctMenu && e("div", { className: "mbx-menu mbx-acct-menu" },
          e("div", { className: "mbx-mitem mbx-acct-option", onClick: () => { setAcct("all"); setAcctMenu(false); } },
            e("span", { className: "mbx-acct-dot", style: { background: "var(--accent)" } }),
            e("span", { className: "mbx-menu-label" }, "All accounts")),
          accounts.map(a => e("div", { key: a.id, className: "mbx-mitem", onClick: () => { setAcct(a.id); setAcctMenu(false); } },
            e("span", { className: "mbx-acct-dot", style: { background: a.color } }),
            e("span", { className: "mbx-menu-label", title: a.email }, a.email))),
          e("div", { className: "mbx-msep" }),
          e("div", { className: "mbx-mitem", onClick: () => setView("connect") }, e(Icon, { name: "plus", size: 15 }), "Connect account"))),
      // folders
      e("div", { className: "mbx-folders" },
        MBOX.folders.map(f => {
          const unread = f.id === "inbox" || f.id === "starred" || f.id === "important" ? folderUnread(f.id) : 0;
          const total = folderTotal(f.id);
          return e("div", { key: f.id, className: "mbx-fold" + (folder === f.id ? " on" : ""), onClick: () => { setFolder(f.id); setSel(null); clearChecks(); },
            role: "button", tabIndex: 0, onKeyDown: ev => { if (ev.key === "Enter") { setFolder(f.id); setSel(null); } } },
            e(Icon, { name: f.icon, size: 17 }),
            e("span", { style: { flex: 1 } }, f.label),
            unread > 0 ? e("span", { className: "mbx-unread" }, unread) : (total > 0 && (f.id === "drafts" || f.id === "scheduled") ? e("span", { className: "mbx-fcount" }, total) : null));
        }),
        e("div", { className: "mbx-rail-sec" }, e("span", null, "Labels"), e("button", { className: "mbx-tbtn", style: { width: 24, height: 24 }, title: "Manage labels", onClick: () => options?.can_manage_settings ? setLabelSettingsOpen(true) : toast("Mailbox label administration requires System Settings permission.", "orange") }, e(Icon, { name: "plus", size: 14 }))),
        labels.map(l => {
          const total = emails.filter(em => (em.labels || []).includes(l.id)).length;
          return e("div", { key: l.id, className: "mbx-fold" + (folder === "label:" + l.id ? " on" : ""), onClick: () => { setFolder("label:" + l.id); setSel(null); clearChecks(); } },
            e("span", { className: "mbx-lbl-dot", style: { background: l.color } }),
            e("span", { style: { flex: 1 } }, l.label),
            total > 0 && e("span", { className: "mbx-fcount" }, total));
        })),
      // sync status
      e("div", { className: "mbx-sync" },
        e("span", { className: "sync-ic" + (syncing ? " spin" : "") }, e(Icon, { name: syncing ? "refresh" : "check", size: 14, style: syncing ? {} : { color: "var(--green)" } })),
        e("span", null, syncing ? "Syncing…" : "Synced " + (accounts[0] ? accounts[0].lastSync : "")),
        e("button", { onClick: doSync }, "Sync now")));

    // ============ LIST ============
    const filterChip = (key, label, icon) => e("button", { className: "mbx-fchip" + (filters[key] ? " on" : ""), onClick: () => setFilters(f => ({ ...f, [key]: !f[key] })) }, icon && e(Icon, { name: icon, size: 13 }), label);

    const allChecked = visible.length > 0 && visible.every(em => checked.has(em.id));

    const list = e("div", { className: "mbx-list" + (sel ? " has-sel" : "") },
      e("div", { className: "mbx-ltop" },
        e("div", { className: "mbx-ltitle" },
          e("button", { className: "mbx-cb" + (allChecked ? " on" : ""), title: "Select all", onClick: () => { if (allChecked) clearChecks(); else setChecked(new Set(visible.map(em => em.id))); } }, allChecked && e(Icon, { name: "check", size: 11 })),
          e("h2", null, folder.startsWith("label:") ? (labelById(folder.slice(6))?.label || "Label") : (MBOX.folders.find(f => f.id === folder) ? MBOX.folders.find(f => f.id === folder).label : folder)),
          e("button", { className: "mbx-abtn", title: "Refresh", style: { width: 32, height: 32 }, onClick: doSync }, e(Icon, { name: "refresh", size: 15, className: syncing ? "spin" : "" })),
          e("button", { className: "mbx-abtn", title: "Settings", style: { width: 32, height: 32 }, onClick: () => setView("settings") }, e(Icon, { name: "gear", size: 15 })),
          e("button", { className: "mbx-fullscreen-toggle", title: fullScreen ? "Exit Full Screen" : "Full Screen", onClick: () => setFullScreen(v => !v) }, e(Icon, { name: fullScreen ? "collapse" : "expand", size: 15 }), fullScreen ? "Exit Full Screen" : "Full Screen")),
        e("div", { className: "mbx-lsearch" },
          e(Icon, { name: "search", size: 15 }),
          e("input", { value: q, placeholder: "Search mail — sender, subject, body…", onChange: ev => setQ(ev.target.value) }),
          q && e("button", { className: "mbx-tbtn", style: { width: 24, height: 24 }, onClick: () => setQ("") }, e(Icon, { name: "x", size: 13 }))),
        e("div", { className: "mbx-lfilters" },
          filterChip("unread", "Unread", "mail"),
          filterChip("attach", "Attachments", "clip"),
          filterChip("linked", "CRM-linked", "link"),
          (filters.unread || filters.attach || filters.linked || q) && e("button", { className: "mbx-fchip", onClick: () => { setFilters({ unread: false, attach: false, linked: false }); setQ(""); } }, "Clear"))),
      e("div", { className: "mbx-banner info", style: { margin: "10px" } }, e(Icon, { name: "shield", size: 15 }),
        apiState.connected
          ? "Mailbox messages, scheduling, CRM links and message states are saved for your account."
          : apiState.loading
            ? "Loading mailbox messages…"
            : mailboxApiRequiredMessage),
      // bulk bar
      checked.size > 0 && e("div", { className: "mbx-bulk" },
        e("span", { className: "bcount" }, checked.size, " selected"),
        e("button", { className: "mbx-bbtn", onClick: () => bulkMailboxState(() => ({ action: "mark_read" }), () => ({ read: true }), "Marked read", em => em.direction !== "out") }, e(Icon, { name: "mailOpen", size: 14 }), "Read"),
        e("button", { className: "mbx-bbtn", onClick: () => bulkMailboxState(() => ({ action: "mark_unread" }), () => ({ read: false }), "Marked unread", em => em.direction !== "out") }, e(Icon, { name: "mail", size: 14 }), "Unread"),
        e("button", { className: "mbx-bbtn", onClick: () => bulkMailboxState(em => ({ action: "set_flags", starred: !em.starred }), em => ({ starred: !em.starred }), "Starred") }, e(Icon, { name: "star", size: 14 }), "Star"),
        e("button", { className: "mbx-bbtn", onClick: () => bulkMailboxState(() => ({ action: "move", folder: "archived" }), () => ({ folder: "archived" }), "Archived") }, e(Icon, { name: "archive", size: 14 }), "Archive"),
        e("button", { className: "mbx-bbtn", onClick: () => bulkMailboxState(() => ({ action: "move", folder: "trash" }), () => ({ folder: "trash" }), "Moved to trash") }, e(Icon, { name: "trash", size: 14 }), "Delete"),
        e("button", { className: "mbx-bbtn", style: { marginLeft: "auto" }, onClick: clearChecks }, e(Icon, { name: "x", size: 14 }), "Clear")),
      // rows
      e("div", { className: "mbx-rows" },
        visible.length === 0
          ? e("div", { style: { padding: "10px" } }, e(Empty, {
              icon: q ? "search" : "mail",
              title: q ? "No matching mail" : "This folder is empty",
              sub: q ? "Try a different search or clear your filters." : "Sync your connected mailbox or adjust filters to view messages.",
              action: q ? e(Button, { onClick: () => { setQ(""); setFilters({ unread: false, attach: false, linked: false }); }, children: "Clear search" }) : null }))
          : visible.map(em => {
            const isChecked = checked.has(em.id);
            return e("div", { key: em.id, className: "mbx-row" + (sel === em.id ? " active" : "") + (!em.read ? " unread" : ""), onClick: () => openEmail(em) },
              e("div", { className: "mbx-rcheck" },
                e("button", { className: "mbx-cb" + (isChecked ? " on" : ""), onClick: ev => { ev.stopPropagation(); toggleCheck(em.id); } }, isChecked && e(Icon, { name: "check", size: 11 })),
                e("button", { className: "mbx-star" + (em.starred ? " on" : ""), onClick: ev => { ev.stopPropagation(); updateMailboxState(em, { action: "set_flags", starred: !em.starred }, { starred: !em.starred }, em.starred ? "Star removed." : "Star saved."); } }, e(Icon, { name: "star", size: 15 }))),
              e("div", { className: "mbx-ravatar" }, e(Avatar, { name: em.from.name, color: em.from.color, size: 38 })),
              e("div", { className: "mbx-rmain" },
                e("div", { className: "mbx-rtop" },
                  em.important && e(Icon, { name: "flag", size: 12, style: { color: "var(--orange)", flex: "none" } }),
                  e("span", { className: "mbx-rname" }, folder === "sent" || folder === "drafts" || em.direction === "out" ? "To: " + (em.to[0] ? em.to[0].name : "—") : em.from.name),
                  e("span", { className: "mbx-rtime" }, em.time || em.date)),
                e("div", { className: "mbx-rsubj" }, em.isDraft && e("span", { style: { color: "var(--red)", fontWeight: 700 } }, "Draft "), em.subject),
                e("div", { className: "mbx-rsnip" }, em.snippet),
                e("div", { className: "mbx-rmeta" },
                  (em.labels || []).map(lid => { const l = labelById(lid); return l && e("span", { key: lid, className: "mbx-chip", style: { background: l.color + "1f", color: l.color } }, e("span", { className: "ic", style: { background: l.color } }), l.label); }),
                  em.scheduledFor && e("span", { className: "mbx-chip", style: { background: "var(--accent-soft)", color: "var(--accent)" } }, e(Icon, { name: "calendar", size: 10 }), em.scheduledFor),
                  em.snoozedUntil && e("span", { className: "mbx-chip", style: { background: "var(--blue-soft)", color: "var(--blue)" } }, e(Icon, { name: "snooze", size: 10 }), "Snoozed ", em.snoozedUntil),
                  em.hasAttach && e("span", { className: "mbx-rattach" }, e(Icon, { name: "clip", size: 13 })),
                  em.crm && em.crm.linked && e("span", { className: "mbx-rlinked" }, e(Icon, { name: "link", size: 10 }), crmName[em.crm.type] || "CRM"))));
          })));

    // ============ READING PANE ============
    let read;
    if (!selected) {
      read = e("div", { className: "mbx-read" }, e("div", { className: "mbx-read-main", style: { alignItems: "center", justifyContent: "center" } },
        e("div", { style: { textAlign: "center", maxWidth: 340, padding: 30 } },
          e("div", { className: "empty-ic", style: { width: 64, height: 64, margin: "0 auto 16px", borderRadius: 18 } }, e(Icon, { name: "mailOpen", size: 28 })),
          e("h3", { style: { fontSize: 17, marginBottom: 6 } }, "Select a message"),
          e("div", { className: "page-sub", style: { margin: "0 auto" } }, "Choose a message from the list to read the conversation and linked CRM details."))));
    } else {
      const em = selected;
      const ackMenu = rowMenu && rowMenu.id === em.id ? rowMenu.kind : null;
      const moveTargets = [["inbox", "Inbox", "inbox"], ["archived", "Archive", "archive"], ["spam", "Spam", "alert"], ["trash", "Trash", "trash"]];
      read = e("div", { className: "mbx-read" },
        e("div", { className: "mbx-read-main" },
          e("div", { className: "mbx-read-top" },
            e("div", { className: "mbx-read-actions" },
              e("button", { className: "mbx-abtn mbx-back", title: "Back", onClick: () => setSel(null) }, e(Icon, { name: "chevL", size: 16 })),
              e("button", { className: "mbx-abtn", title: "Archive", onClick: () => archiveEmail(em) }, e(Icon, { name: "archive", size: 16 })),
              e("button", { className: "mbx-abtn", title: "Delete", onClick: () => { updateMailboxState(em, { action: "move", folder: "trash" }, { folder: "trash" }, "Moved to Trash."); setSel(null); } }, e(Icon, { name: "trash", size: 16 })),
              e("button", { className: "mbx-abtn", title: "Mark unread", onClick: () => {
                if (em.direction === "out") { toast("Sent messages do not have recipient unread state.", "orange"); return; }
                updateMailboxState(em, { action: "mark_unread" }, { read: false }, "Marked unread.");
                setSel(null);
              } }, e(Icon, { name: "mail", size: 16 })),
              e("div", { className: "mbx-asep" }),
              e("button", { className: "mbx-abtn" + (em.starred ? " on" : ""), title: "Star", onClick: () => updateMailboxState(em, { action: "set_flags", starred: !em.starred }, { starred: !em.starred }, em.starred ? "Star removed." : "Star saved.") }, e(Icon, { name: "star", size: 16 })),
              e("button", { className: "mbx-abtn", title: "Mark important", onClick: () => updateMailboxState(em, { action: "set_flags", important: !em.important }, { important: !em.important }, em.important ? "Important flag removed." : "Important flag saved.") }, e(Icon, { name: "flag", size: 16, style: em.important ? { color: "var(--orange)" } : {} })),
              // label menu
              e("div", { style: { position: "relative" } },
                e("button", { className: "mbx-abtn", title: "Label", onClick: ev => { ev.stopPropagation(); setRowMenu(ackMenu === "label" ? null : { id: em.id, kind: "label" }); } }, e(Icon, { name: "tag", size: 16 })),
                ackMenu === "label" && e("div", { className: "mbx-menu", style: { top: 40, left: 0 } },
                  e("div", { className: "mbx-rail-sec", style: { padding: "4px 11px 6px" } }, "Apply label"),
                  labels.map(l => { const on = (em.labels || []).includes(l.id); const nextLabels = on ? (em.labels || []).filter(z => z !== l.id) : [...(em.labels || []), l.id]; return e("div", { key: l.id, className: "mbx-mitem", onClick: () => { updateMailboxState(em, { action: "set_labels", labels: nextLabels }, { labels: nextLabels }, (on ? "Removed " : "Added ") + l.label + "."); setRowMenu(null); } }, e("span", { className: "mbx-lbl-dot", style: { background: l.color } }), l.label, on && e(Icon, { name: "check", size: 14, style: { marginLeft: "auto", color: "var(--green)" } })); }))),
              // move menu
              e("div", { style: { position: "relative" } },
                e("button", { className: "mbx-abtn", title: "Move to", onClick: ev => { ev.stopPropagation(); setRowMenu(ackMenu === "move" ? null : { id: em.id, kind: "move" }); } }, e(Icon, { name: "folder", size: 16 })),
                ackMenu === "move" && e("div", { className: "mbx-menu", style: { top: 40, left: 0 } },
                  e("div", { className: "mbx-rail-sec", style: { padding: "4px 11px 6px" } }, "Move to"),
                  moveTargets.map(([fid, lbl, ic]) => e("div", { key: fid, className: "mbx-mitem", onClick: () => { updateMailboxState(em, { action: "move", folder: fid }, { folder: fid }, "Moved to " + lbl + "."); setSel(null); setRowMenu(null); } }, e(Icon, { name: ic, size: 15 }), lbl)))),
              e("button", { className: "mbx-abtn", title: "Link to CRM", onClick: () => setLinkPanel(true) }, e(Icon, { name: "link", size: 16 })),
              e("div", { style: { flex: 1 } }),
              // more
              e("div", { style: { position: "relative" } },
                e("button", { className: "mbx-abtn", title: "More", onClick: ev => { ev.stopPropagation(); setRowMenu(ackMenu === "more" ? null : { id: em.id, kind: "more" }); } }, e(Icon, { name: "dots", size: 16 })),
                ackMenu === "more" && e("div", { className: "mbx-menu", style: { top: 40, right: 0 } },
                  e("div", { className: "mbx-mitem", role: "menuitem", onClick: () => exportSelectedMessage(em, "pdf"), title: "Download the selected mailbox message as a PDF." }, e(Icon, { name: "doc", size: 15 }), "Export message PDF"),
                  e("div", { className: "mbx-mitem", onClick: () => snoozeEmail(em) }, e(Icon, { name: "snooze", size: 15 }), "Snooze until tomorrow"),
                  e("div", { className: "mbx-mitem", onClick: () => { setRowMenu(null); updateMailboxState(em, { action: "move", folder: "spam" }, { folder: "spam" }, "Marked as spam."); setSel(null); } }, e(Icon, { name: "alert", size: 15 }), "Report spam"),
                  e("div", { className: "mbx-msep" }),
                  e("div", { className: "mbx-mitem", role: "menuitem", onClick: () => { setRowMenu(null); blockSender(em); }, title: "Create a sender block settings draft and move this message to spam." }, e(Icon, { name: "x", size: 15 }), "Block sender"))),
              e("button", { className: "mbx-abtn", title: linkPanel ? "Hide CRM panel" : "Show CRM panel", onClick: () => setLinkPanel(p => !p), style: { display: "none" } })),
            e("div", { className: "mbx-read-subjrow" },
              e("h1", { className: "mbx-read-subj", style: { flex: 1 } }, em.subject),
              (em.labels || []).map(lid => { const l = labelById(lid); return l && e("span", { key: lid, className: "mbx-chip", style: { background: l.color + "1f", color: l.color, height: 22 } }, l.label); }))),
          // thread body
          e("div", { className: "mbx-read-body" },
            em.scheduledFor && e("div", { className: "mbx-banner info" }, e(Icon, { name: "calendar", size: 16 }), "This message is scheduled to send ", e("b", { style: { marginLeft: 4 } }, em.scheduledFor), e("button", { className: "mbx-bbtn", style: { marginLeft: "auto" }, onClick: () => cancelScheduledEmail(em) }, "Cancel send")),
            em.snoozedUntil && e("div", { className: "mbx-banner info" }, e(Icon, { name: "snooze", size: 16 }), "This message is snoozed until ", e("b", { style: { marginLeft: 4 } }, em.snoozedUntil)),
            (em.thread && em.thread.length ? em.thread : [{ id: em.id + "-only", you: em.direction === "out", from: em.from.name, color: em.from.color, time: em.time, body: em.body || "<p>" + em.snippet + "</p>", attachments: em.attachments || [] }]).map((m, mi, arr) => {
              const isCollapsed = collapsed.has(m.id) && mi !== arr.length - 1;
              return e("div", { key: m.id, className: "mbx-msg" + (isCollapsed ? " mbx-collapsed" : "") },
                e("div", { className: "mbx-msg-head", onClick: () => setCollapsed(s => { const n = new Set(s); n.has(m.id) ? n.delete(m.id) : n.add(m.id); return n; }) },
                  e(Avatar, { name: m.from, color: m.color, size: 38 }),
                  e("div", { className: "mbx-msg-who" },
                    e("div", { className: "mbx-msg-from" }, m.from, m.you && e("span", { className: "faint", style: { fontWeight: 600 } }, "  ·  you")),
                    isCollapsed ? e("div", { className: "mbx-msg-snip" }, (m.body || "").replace(/<[^>]+>/g, " ").trim().slice(0, 80)) : e("div", { className: "mbx-msg-to" }, "to ", (em.to || []).map(t => t.name).join(", ") || "me")),
                  e("span", { className: "mbx-msg-time" }, m.time),
                  e(Icon, { name: "chevD", size: 15, style: { color: "var(--text-3)", transform: isCollapsed ? "" : "rotate(180deg)" } })),
                !isCollapsed && e("div", { className: "mbx-msg-body", dangerouslySetInnerHTML: { __html: window.MbxSanitize(m.body || "") } }),
                !isCollapsed && (m.attachments || []).length > 0 && e("div", { className: "mbx-atts" }, m.attachments.map((a, ai) =>
                  e("div", { key: ai, className: "mbx-att", onClick: () => toast("Attachment download is not available for this file: " + a.name, "orange") },
                    e("div", { className: "mbx-att-ic", style: { background: a.color || "var(--blue)" } }, a.type),
                    e("div", { style: { minWidth: 0 } }, e("div", { className: "mbx-att-name" }, a.name), e("div", { className: "mbx-att-sub" }, a.type + " · " + a.size)),
                    e(Icon, { name: "download", size: 15, style: { color: "var(--text-3)", marginLeft: 4 } })))));
            }),
            // reply bar
            em.folder !== "scheduled" && e("div", { className: "mbx-reply-bar" },
              e("button", { className: "mbx-reply-btn", onClick: () => replyTo(em, false) }, e(Icon, { name: "reply", size: 15 }), "Reply"),
              (em.cc || (em.to && em.to.length > 1)) && e("button", { className: "mbx-reply-btn", onClick: () => replyTo(em, true) }, e(Icon, { name: "replyAll", size: 15 }), "Reply all"),
              e("button", { className: "mbx-reply-btn", onClick: () => forward(em) }, e(Icon, { name: "forward", size: 15 }), "Forward")))),
        // CRM panel
        e(CrmPanel, { em, mailboxOptions: options, taskOptions, linkPanel, setLinkPanel, toast, onCrmLink: updateCrmLink }));
    }

    return e("div", { className: "mbx" + (fullScreen ? " mbx-fullscreen" : "") },
      rail, list, read,
      compose && e(window.MbxComposeDock, { compose, setCompose, accounts, onSend: doSend, onSaveDraft: saveDraft, onDiscard: discard, toast, mailboxOptions: options }),
      labelSettingsOpen && e(MailboxLabelSettingsModal, { labels, onClose: () => setLabelSettingsOpen(false), onSave: saveMailboxLabels }),
      confirm && e(MailboxShellConfirmModal, { confirm, onCancel: () => setConfirm(null) }));
  }

  /* ---------------- CRM context panel ---------------- */
  function CrmPanel({ em, mailboxOptions, taskOptions, linkPanel, setLinkPanel, toast, onCrmLink }) {
    const crm = em.crm || { type: "none", linked: false };
    const [taskModal, setTaskModal] = React.useState(false);
    const [noteModal, setNoteModal] = React.useState(false);
    const [loggingLeadActivity, setLoggingLeadActivity] = React.useState(false);
    const groups = [
      ["projects", "Projects"],
      ["leads", "Leads"],
      ["bookings", "Bookings"],
      ["customers", "Customers"],
    ].map(([key, label]) => [key, label, mailboxOptions?.crm_link_records?.[key] || []])
      .filter(([, , rows]) => rows.length > 0);
    const canLink = !!(mailboxOptions?.can_link_crm && mailboxOptions?.crm_link_url_template && em?.recordId);
    const canCreateLeadActivity = !!(mailboxOptions?.can_create_lead_activity && mailboxOptions?.lead_activity_store_url && crm?.type === "lead" && crm?.id);
    const crmRoute = { project: "projects", lead: "leads", booking: "sales", customer: "collections" }[crm?.type] || null;
    const openLinkedCrmModule = () => {
      if (!crmRoute) {
        toast("No Builder360 module route is configured for this linked CRM record type.", "orange");
        return;
      }
      window.dispatchEvent(new CustomEvent("builder360:navigate", { detail: { route: crmRoute } }));
      toast("Opened " + (crmName[crm.type] || "CRM") + " module for linked record #" + crm.id + ".", "green");
    };
    const requireLeadActivity = () => {
      toast("CRM activity quick actions require this mailbox message to be linked to a Lead and a role with CRM activity permission.", "orange");
    };
    const logLeadEmailActivity = async () => {
      if (!canCreateLeadActivity) {
        requireLeadActivity();
        return;
      }
      try {
        setLoggingLeadActivity(true);
        const body = await apiJson(mailboxOptions.lead_activity_store_url, {
          method: "POST",
          body: JSON.stringify({
            lead_id: Number(crm.id),
            activity_type: "email",
            subject: "Mailbox email: " + (em?.subject || "Lead email"),
            description: [
              "Direction: " + (em?.direction === "out" ? "Outbound" : "Inbound"),
              "Mailbox message: " + (em?.id || "message"),
              stripHtml(em?.body || em?.snippet || ""),
            ].filter(Boolean).join("\n\n").slice(0, 5000),
            outcome: "mailbox_logged",
            metadata: {
              source: "mailbox_quick_action",
              mailbox_action: "log_email",
              mailbox_message_number: em?.id || null,
              mailbox_message_id: em?.recordId || null,
              mailbox_subject: em?.subject || null,
              mailbox_direction: em?.direction || null,
            },
          }),
        });
        toast("Lead activity " + (body.data?.activity_number || "") + " logged from mailbox.", "green");
      } catch (err) {
        toast(err.message || "Mailbox email could not be logged to lead activity.", "red");
      } finally {
        setLoggingLeadActivity(false);
      }
    };

    if (linkPanel || !crm.linked) {
      return e("div", { className: "mbx-crm" },
        e("div", { className: "mbx-crm-sec" },
          e("div", { className: "row between", style: { marginBottom: 12 } },
            e("div", { className: "mbx-crm-label", style: { margin: 0 } }, "Link to CRM record"),
            linkPanel && crm.linked && e("button", { className: "mbx-tbtn", style: { width: 24, height: 24 }, onClick: () => setLinkPanel(false) }, e(Icon, { name: "x", size: 14 }))),
          !crm.linked && e("div", { className: "mbx-banner warn", style: { marginBottom: 12 } }, e(Icon, { name: "alert", size: 15 }), "Not yet linked to a record"),
          !canLink && e("div", { className: "mbx-banner info", style: { marginBottom: 12 } }, e(Icon, { name: "shield", size: 15 }), "CRM linking is available only for saved internal mailbox messages with CRM-link access."),
          canLink && groups.length === 0 && e("div", { className: "mbx-banner info" }, e(Icon, { name: "search", size: 15 }), "No CRM records are available for linking."),
          canLink && groups.map(([key, label, rows], groupIndex) => e(React.Fragment, { key },
            e("div", { className: "mbx-crm-label", style: { marginTop: groupIndex === 0 ? 0 : 14 } }, label),
            rows.slice(0, 6).map(record => e("div", {
              key: record.type + "-" + record.id,
              className: "mbx-deal",
              style: { cursor: "pointer" },
              onClick: () => onCrmLink(em, record, "link"),
            },
              e("div", { className: "mbx-deal-name" }, record.label || (crmName[record.type] || "CRM") + " #" + record.id),
              e("div", { className: "mbx-deal-meta" },
                e("span", { className: "faint" }, crmName[record.type] || record.type),
                record.meta && e("span", { className: "mono", style: { fontWeight: 700 } }, record.meta))))))));
    }

    return e("div", { className: "mbx-crm" },
      e("div", { className: "mbx-crm-sec" },
        e("div", { className: "row between", style: { marginBottom: 12 } },
          e("div", { className: "mbx-crm-label", style: { margin: 0 } }, "Linked " + (crmName[crm.type] || "record")),
          e("button", { className: "mbx-tbtn", style: { width: 26, height: 26 }, title: "Unlink", onClick: () => onCrmLink(em, null, "unlink") }, e(Icon, { name: "x", size: 14 }))),
        e("div", { className: "mbx-crm-contact" },
          e("div", { style: { width: 46, height: 46, borderRadius: 13, background: "var(--blue-soft)", display: "grid", placeItems: "center", color: "var(--blue)" } }, e(Icon, { name: "link", size: 22 })),
          e("div", null,
            e("div", { className: "mbx-crm-name" }, crm.name || (crmName[crm.type] || "CRM") + " #" + crm.id),
            e("div", { className: "mbx-crm-meta" }, crmName[crm.type] || crm.type || "CRM record"))),
        e("div", { style: { marginTop: 12 } },
          e("div", { className: "mbx-crm-kv" }, e("span", { className: "k" }, "Record type"), e(Badge, { tone: "b-accent", dot: true }, crmName[crm.type] || crm.type || "CRM")),
          e("div", { className: "mbx-crm-kv" }, e("span", { className: "k" }, "Record ID"), e("span", { className: "v mono" }, crm.id || "—")),
          crm.linkedBy && e("div", { className: "mbx-crm-kv" }, e("span", { className: "k" }, "Linked by"), e("span", { className: "v" }, crm.linkedBy)),
          crm.linkedAt && e("div", { className: "mbx-crm-kv" }, e("span", { className: "k" }, "Linked at"), e("span", { className: "v" }, dateLabel(crm.linkedAt) + " " + timeLabel(crm.linkedAt))),
          crm.inherited && e("div", { className: "mbx-banner info", style: { marginTop: 10 } }, e(Icon, { name: "info", size: 15 }), "Inherited from the message project. Use Link to CRM to persist a specific CRM link."),
          crm.note && e("div", { className: "mbx-banner info", style: { marginTop: 10 } }, e(Icon, { name: "doc", size: 15 }), crm.note))),
      e("div", { className: "mbx-crm-sec" },
        e("div", { className: "mbx-crm-label" }, "Quick actions"),
        e("div", { className: "mbx-crm-qa" },
          e("button", { className: "mbx-qa", onClick: openLinkedCrmModule, title: crmRoute ? "Open the existing Builder360 module for this linked CRM record." : "No module route is configured for this record type." }, e(Icon, { name: "eye", size: 14 }), "Open module"),
          e("button", { className: "mbx-qa", disabled: loggingLeadActivity, onClick: logLeadEmailActivity, title: canCreateLeadActivity ? "Log this mailbox email to the linked Lead timeline." : "Link to a Lead and use CRM activity permission to log email." }, e(Icon, { name: "check", size: 14 }), loggingLeadActivity ? "Logging..." : "Log email"),
          e("button", { className: "mbx-qa", onClick: () => taskOptions?.can_create && taskOptions?.store_url ? setTaskModal(true) : toast("Task creation from mailbox requires collaboration task create permission.", "orange") }, e(Icon, { name: "calendar", size: 14 }), "Create task"),
          e("button", { className: "mbx-qa", onClick: () => canCreateLeadActivity ? setNoteModal(true) : requireLeadActivity(), title: canCreateLeadActivity ? "Add a note to the linked Lead timeline." : "Link to a Lead and use CRM activity permission to add notes." }, e(Icon, { name: "pencil", size: 14 }), "Add note"))),
      e("div", { className: "mbx-crm-sec" },
        e("div", { className: "mbx-crm-label" }, "On timeline"),
        e("div", { className: "row gap-2", style: { fontSize: 12, color: "var(--text-2)" } },
          e("span", { style: { width: 28, height: 28, borderRadius: 8, background: em.direction === "out" ? "var(--blue-soft)" : "var(--green-soft)", color: em.direction === "out" ? "var(--blue)" : "var(--green)", display: "grid", placeItems: "center", flex: "none" } }, e(Icon, { name: em.direction === "out" ? "paperplane" : "mailOpen", size: 14 })),
          e("div", null, e("div", { style: { fontWeight: 700, color: "var(--text)" } }, em.direction === "out" ? "Outbound email" : "Inbound email"), e("div", { className: "faint", style: { fontSize: 11 } }, "Logged · " + (em.date || "") + " " + (em.time || ""))))),
      taskModal && e(MailboxTaskModal, { em, taskOptions, onClose: () => setTaskModal(false), toast }),
      noteModal && e(MailboxLeadNoteModal, { em, mailboxOptions, onClose: () => setNoteModal(false), toast }));
  }

  // very small HTML sanitizer for email bodies — strips scripts/handlers
  window.MbxSanitize = function (html) {
    if (!html) return "";
    return String(html)
      .replace(/<\s*(script|style|iframe|object|embed|link|meta)[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, "")
      .replace(/<\s*(script|style|iframe|object|embed|link|meta)[^>]*\/?>/gi, "")
      .replace(/\son\w+\s*=\s*"[^"]*"/gi, "")
      .replace(/\son\w+\s*=\s*'[^']*'/gi, "")
      .replace(/javascript:/gi, "");
  };

  window.Mailbox = Mailbox;
})();
