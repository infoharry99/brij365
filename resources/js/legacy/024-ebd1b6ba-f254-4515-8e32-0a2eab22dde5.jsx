const React = window.React;

/* ============================================================
   Builder360 — Mailbox: compose dock, connect & settings views
   Exposes helpers on window for screens-mailbox.jsx
   ============================================================ */
(function () {
  const { Icon, Avatar, Badge, Button, Empty } = window;
  const e = React.createElement;
  const MBOX = window.MBOX;

  function MailboxLinkModal({ onClose, onInsert }) {
    const [url, setUrl] = React.useState("https://");
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const submit = ev => {
      ev.preventDefault();
      const clean = url.trim();
      if (!clean || clean === "https://") {
        setError("Enter a link URL.");
        return;
      }
      if (!/^(https?:\/\/|mailto:|tel:)/i.test(clean)) {
        setError("Use https://, http://, mailto: or tel: links only.");
        return;
      }
      onInsert(clean);
      onClose();
    };
    return e("div", { onMouseDown: ev => ev.preventDefault(), onClick: onClose, style: { position: "fixed", inset: 0, zIndex: 1100, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(440px,94vw)", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, "Insert Link"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Adds a safe link to the current message selection.")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 14 } }, "URL", e("input", { style: field, value: url, onChange: ev => setUrl(ev.target.value), placeholder: "https://example.com", autoFocus: true, required: true })),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Allowed: http(s), mailto, tel"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { type: "submit", variant: "primary", icon: "link", children: "Insert" })))));
  }

  function MailboxConfirmModal({ title, message, confirmLabel, tone, onCancel, onConfirm }) {
    return e("div", { onMouseDown: ev => ev.preventDefault(), onClick: onCancel, style: { position: "fixed", inset: 0, zIndex: 1100, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("div", { onClick: ev => ev.stopPropagation(), style: { width: "min(440px,94vw)", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, title), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, message)),
          e("button", { type: "button", className: "icon-btn", onClick: onCancel }, e(Icon, { name: "x" }))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "This confirmation is recorded in the compose workflow state."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onCancel, children: "Cancel" }), e(Button, { type: "button", variant: tone || "primary", icon: "check", onClick: onConfirm, children: confirmLabel || "Confirm" })))));
  }

  /* ---------------- Compose dock ---------------- */
  function ComposeDock({ compose, setCompose, accounts, onSend, onSaveDraft, onDiscard, toast, mailboxOptions }) {
    const bodyRef = React.useRef(null);
    const attachmentInputRef = React.useRef(null);
    const [showCc, setShowCc] = React.useState(!!compose.cc);
    const [showBcc, setShowBcc] = React.useState(!!compose.bcc);
    const [sending, setSending] = React.useState(false);
    const [menu, setMenu] = React.useState(null); // sendmenu | tpl | sig
    const [atts, setAtts] = React.useState(compose.attachments || []);
    const [linkModal, setLinkModal] = React.useState(false);
    const [sendWithoutSubject, setSendWithoutSubject] = React.useState(null);
    const upd = (patch) => setCompose(c => ({ ...c, ...patch }));
    const schedulePreset = (preset) => {
      const date = new Date();
      if (preset === "tomorrow") {
        date.setDate(date.getDate() + 1);
        date.setHours(9, 0, 0, 0);
      } else {
        const day = date.getDay();
        const daysUntilMonday = (8 - day) % 7 || 7;
        date.setDate(date.getDate() + daysUntilMonday);
        date.setHours(8, 0, 0, 0);
      }
      return {
        label: date.toLocaleString("en-IN", { weekday: "short", day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" }),
        iso: date.toISOString(),
      };
    };

    const acct = accounts.find(a => a.id === compose.accountId) || accounts[0];

    React.useEffect(() => {
      if (bodyRef.current && compose.body != null && bodyRef.current.innerHTML !== compose.body) {
        bodyRef.current.innerHTML = compose.body;
      }
    }, []);

    const captureBody = () => bodyRef.current ? bodyRef.current.innerHTML : compose.body;
    const fileSizeLabel = bytes => {
      const size = Number(bytes || 0);
      if (size >= 1024 * 1024) return (size / (1024 * 1024)).toFixed(size >= 10 * 1024 * 1024 ? 0 : 1) + " MB";
      if (size >= 1024) return Math.round(size / 1024) + " KB";
      return size + " B";
    };
    const attachmentColor = ext => ext === "PDF" ? "var(--red)" : ["XLS", "XLSX", "CSV"].includes(ext) ? "var(--green)" : "var(--blue)";
    const addSelectedAttachments = ev => {
      const files = Array.from(ev.target.files || []);
      if (!files.length) return;
      const accepted = files.map(file => {
        const ext = String(file.name || "FILE").split(".").pop().toUpperCase().slice(0, 8) || "FILE";
        return { name: file.name, type: ext, size: fileSizeLabel(file.size), color: attachmentColor(ext), selectedFile: true };
      });
      setAtts(current => [...current, ...accepted]);
      toast(files.length + " attachment" + (files.length > 1 ? "s" : "") + " selected. Upload is handled when the message is sent.", "accent");
      ev.target.value = "";
    };

    const internalRecipients = mailboxOptions?.recipients || [];
    const splitRecipients = (value) => String(value || "").split(",").map(s => s.trim()).filter(Boolean);
    const appendRecipient = (recipient) => {
      if (!recipient?.email) return;
      const current = splitRecipients(compose.to);
      const exists = current.some(email => email.toLowerCase() === recipient.email.toLowerCase());
      if (exists) {
        toast(recipient.email + " is already in To", "orange");
        return;
      }
      upd({ to: [...current, recipient.email].join(", ") });
      toast("Internal recipient added: " + recipient.name, "accent");
    };

    const submitSend = (scheduled, body) => {
      if (!compose.to || !compose.to.trim()) { toast("Add at least one recipient", "red"); return; }
      setSending(true);
      setTimeout(() => {
        setSending(false);
        onSend({ ...compose, body, attachments: atts, scheduled_for: scheduled ? compose.scheduleFor : null }, scheduled);
      }, 900);
    };
    const validateAndSend = (scheduled) => {
      const body = captureBody();
      if (!compose.to || !compose.to.trim()) { toast("Add at least one recipient", "red"); return; }
      const bad = compose.to.split(",").map(s => s.trim()).filter(s => s && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(s.replace(/^.*</, "").replace(/>$/, "")));
      if (bad.length) { toast("Invalid email: " + bad[0], "red"); return; }
      if (!compose.subject || !compose.subject.trim()) {
        setSendWithoutSubject({ scheduled, body });
        return;
      }
      submitSend(scheduled, body);
    };

    const fmt = (cmd, val) => { document.execCommand(cmd, false, val || null); bodyRef.current && bodyRef.current.focus(); };

    if (compose.min) {
      return e("div", { className: "mbx-compose-dock min" },
        e("div", { className: "mbx-cdock-head", onClick: () => upd({ min: false }) },
          e(Icon, { name: "mail", size: 15 }),
          e("span", { className: "ct" }, compose.subject || "New message"),
          e("button", { onClick: (ev) => { ev.stopPropagation(); upd({ min: false }); }, title: "Restore" }, e(Icon, { name: "expand", size: 14 })),
          e("button", { onClick: (ev) => { ev.stopPropagation(); onDiscard(); }, title: "Discard" }, e(Icon, { name: "x", size: 15 }))));
    }

    const recipPill = (label, value, key) => e("div", { className: "mbx-cfield" },
      e("span", { className: "lbl" }, label),
      e("input", { value: value, list: label === "To" && internalRecipients.length ? "mbx-recipient-options" : undefined, placeholder: label === "To" ? "Recipients" : "", onChange: ev => upd({ [key]: ev.target.value }) }),
      label === "To" && internalRecipients.length > 0 && e("datalist", { id: "mbx-recipient-options" },
        internalRecipients.map(recipient => e("option", { key: recipient.id || recipient.email, value: recipient.email }, recipient.name + (recipient.role ? " - " + recipient.role : "")))),
      label === "To" && e("div", { className: "ccbcc" },
        !showCc && e("button", { onClick: () => setShowCc(true) }, "Cc"),
        !showBcc && e("button", { onClick: () => setShowBcc(true) }, "Bcc")));

    return e("div", { className: "mbx-compose-dock" + (compose.full ? " full" : "") },
      e("div", { className: "mbx-cdock-head" },
        e("span", { className: "ct" }, compose.title || "New message"),
        e("button", { onClick: () => upd({ min: true }), title: "Minimize" }, e(Icon, { name: "minimize", size: 14 })),
        e("button", { onClick: () => upd({ full: !compose.full }), title: compose.full ? "Exit full screen" : "Full screen" }, e(Icon, { name: compose.full ? "collapse" : "expand", size: 14 })),
        e("button", { onClick: onDiscard, title: "Close" }, e(Icon, { name: "x", size: 15 }))),
      e("div", { className: "mbx-cdock-body" },
        // from
        e("div", { className: "mbx-cfield" },
          e("span", { className: "lbl" }, "From"),
          e("div", { style: { position: "relative", flex: 1 } },
            e("button", { className: "mbx-pill", style: { cursor: "pointer" }, onClick: () => setMenu(menu === "from" ? null : "from") },
              e("span", { className: "mbx-acct-dot", style: { background: acct.color, width: 7, height: 7 } }), acct.email, e(Icon, { name: "chevD", size: 13 })),
            menu === "from" && e("div", { className: "mbx-menu", style: { top: 32, left: 0 } },
              accounts.map(a => e("div", { key: a.id, className: "mbx-mitem", onClick: () => { upd({ accountId: a.id }); setMenu(null); } },
                e("span", { className: "mbx-acct-dot", style: { background: a.color } }), a.email))))),
        recipPill("To", compose.to || "", "to"),
        internalRecipients.length > 0 && e("div", { className: "mbx-cfield", style: { alignItems: "flex-start", paddingTop: 8, paddingBottom: 8 } },
          e("span", { className: "lbl", style: { paddingTop: 3 } }, "Users"),
          e("div", { style: { display: "flex", gap: 6, flexWrap: "wrap", flex: 1 } },
            e("div", { style: { width: "100%", fontSize: 11, color: "var(--text-3)", fontWeight: 700 } }, "Select a user below or type an internal email."),
            internalRecipients.slice(0, 8).map(recipient => e("button", {
              key: recipient.id || recipient.email,
              className: "mbx-pill",
              title: recipient.email,
              onClick: () => appendRecipient(recipient)
            }, recipient.name, recipient.role && e("span", { className: "faint", style: { marginLeft: 4 } }, recipient.role))),
            internalRecipients.length > 8 && e("span", { className: "faint", style: { alignSelf: "center", fontSize: 11, fontWeight: 700 } }, "+" + (internalRecipients.length - 8) + " more in email suggestions"))),
        showCc && recipPill("Cc", compose.cc || "", "cc"),
        showBcc && recipPill("Bcc", compose.bcc || "", "bcc"),
        e("div", { className: "mbx-cfield" },
          e("span", { className: "lbl" }, "Subject"),
          e("input", { value: compose.subject || "", placeholder: "Subject", onChange: ev => upd({ subject: ev.target.value }) })),
        // body
        e("div", { ref: bodyRef, className: "mbx-cbody", contentEditable: true, suppressContentEditableWarning: true,
          "data-ph": "Write your message…", onBlur: () => upd({ body: captureBody() }) }),
        // attachments
        atts.length > 0 && e("div", { className: "mbx-catt-list", style: { padding: "0 14px 8px" } }, atts.map((a, i) =>
          e("div", { className: "mbx-catt", key: i },
            e("div", { className: "mbx-att-ic", style: { background: a.color || "var(--blue)" } }, a.type),
            e("div", { style: { flex: 1, minWidth: 0 } }, e("div", { className: "mbx-att-name" }, a.name), e("div", { className: "mbx-att-sub" }, a.size)),
            e("button", { className: "mbx-tbtn", style: { width: 28, height: 28 }, onClick: () => setAtts(atts.filter((_, j) => j !== i)) }, e(Icon, { name: "x", size: 14 }))))),
        // schedule banner
        compose.scheduleAt && e("div", { className: "mbx-banner info", style: { margin: "0 14px 8px" } },
          e(Icon, { name: "calendar", size: 15 }), "Scheduled for " + compose.scheduleAt,
          e("button", { className: "mbx-tbtn", style: { width: 26, height: 26, marginLeft: "auto" }, onClick: () => upd({ scheduleAt: null, scheduleFor: null }) }, e(Icon, { name: "x", size: 13 }))),
        // toolbar
        e("div", { className: "mbx-ctoolbar" },
          e("input", { ref: attachmentInputRef, type: "file", multiple: true, style: { display: "none" }, onChange: addSelectedAttachments }),
          e("button", { className: "mbx-tbtn", title: "Bold", onMouseDown: ev => ev.preventDefault(), onClick: () => fmt("bold") }, e(Icon, { name: "bold", size: 15 })),
          e("button", { className: "mbx-tbtn", title: "Italic", onMouseDown: ev => ev.preventDefault(), onClick: () => fmt("italic") }, e(Icon, { name: "italic", size: 15 })),
          e("button", { className: "mbx-tbtn", title: "Underline", onMouseDown: ev => ev.preventDefault(), onClick: () => fmt("underline") }, e(Icon, { name: "underline", size: 15 })),
          e("button", { className: "mbx-tbtn", title: "Bulleted list", onMouseDown: ev => ev.preventDefault(), onClick: () => fmt("insertUnorderedList") }, e(Icon, { name: "listul", size: 15 })),
          e("button", { className: "mbx-tbtn", title: "Insert link", onMouseDown: ev => ev.preventDefault(), onClick: () => setLinkModal(true) }, e(Icon, { name: "link", size: 15 })),
          e("div", { style: { width: 1, height: 20, background: "var(--border)", margin: "0 4px" } }),
          // template
          e("div", { style: { position: "relative" } },
            e("button", { className: "mbx-tbtn", title: "Insert template", onClick: () => setMenu(menu === "tpl" ? null : "tpl") }, e(Icon, { name: "doc", size: 15 })),
            menu === "tpl" && e("div", { className: "mbx-menu", style: { bottom: 38, left: 0 } },
              e("div", { className: "mbx-rail-sec", style: { padding: "4px 11px 6px" } }, "Templates"),
              MBOX.templates.map(t => e("div", { key: t.id, className: "mbx-mitem", onClick: () => {
                if (bodyRef.current) bodyRef.current.innerHTML = t.body; upd({ body: t.body }); setMenu(null); toast("Template inserted", "accent");
              } }, e(Icon, { name: "doc", size: 15 }), t.name)))),
          // signature
          e("button", { className: "mbx-tbtn", title: "Insert signature", onClick: () => {
            if (bodyRef.current) { bodyRef.current.innerHTML += "<p style='color:var(--text-3)'>" + (acct.signature || MBOX.sig).replace(/\n/g, "<br>") + "</p>"; upd({ body: bodyRef.current.innerHTML }); toast("Signature added", "accent"); }
          } }, e(Icon, { name: "pencil", size: 15 })),
          // attach
          e("button", { className: "mbx-tbtn", title: "Attach file", onClick: () => attachmentInputRef.current && attachmentInputRef.current.click() }, e(Icon, { name: "clip", size: 15 })),
          e("div", { style: { flex: 1 } }),
          e("button", { className: "mbx-tbtn", title: "Discard draft", onClick: onDiscard }, e(Icon, { name: "trash", size: 15 }))),
        // footer
        e("div", { className: "mbx-cfoot" },
          e("div", { style: { position: "relative", display: "inline-flex" } },
            e("button", { className: "mbx-send", disabled: sending, onClick: () => validateAndSend(!!compose.scheduleFor) },
              sending ? e(React.Fragment, null, e(Icon, { name: "refresh", size: 15, className: "spin" }), "Sending…")
                      : e(React.Fragment, null, e(Icon, { name: "paperplane", size: 15 }), compose.scheduleAt ? "Schedule" : "Send"),
              e("span", { className: "mbx-send-split", onClick: (ev) => { ev.stopPropagation(); setMenu(menu === "send" ? null : "send"); } }, e(Icon, { name: "chevD", size: 14 }))),
            menu === "send" && e("div", { className: "mbx-menu", style: { bottom: 44, left: 0 } },
              e("div", { className: "mbx-mitem", onClick: () => { setMenu(null); validateAndSend(false); } }, e(Icon, { name: "paperplane", size: 15 }), "Send now"),
              e("div", { className: "mbx-mitem", onClick: () => { const next = schedulePreset("tomorrow"); upd({ scheduleAt: next.label, scheduleFor: next.iso }); setMenu(null); toast("Scheduled send time selected", "accent"); } }, e(Icon, { name: "calendar", size: 15 }), "Tomorrow, 9:00 AM"),
              e("div", { className: "mbx-mitem", onClick: () => { const next = schedulePreset("monday"); upd({ scheduleAt: next.label, scheduleFor: next.iso }); setMenu(null); toast("Scheduled send time selected", "accent"); } }, e(Icon, { name: "calendar", size: 15 }), "Monday morning"))),
          e("button", { className: "btn btn-sm", onClick: () => { onSaveDraft({ ...compose, body: captureBody(), attachments: atts }); } }, e(Icon, { name: "doc", size: 14 }), "Save draft"),
          e("div", { style: { flex: 1 } }),
          e("span", { className: "faint", style: { fontSize: 11, fontWeight: 600 } }, atts.length ? atts.length + " attachment" + (atts.length > 1 ? "s" : "") : ""))),
        linkModal && e(MailboxLinkModal, { onClose: () => setLinkModal(false), onInsert: url => { fmt("createLink", url); upd({ body: captureBody() }); toast("Link inserted", "accent"); } }),
        sendWithoutSubject && e(MailboxConfirmModal, { title: "Send without subject?", message: "This message has no subject. Confirm before sending or scheduling it.", confirmLabel: sendWithoutSubject.scheduled ? "Schedule Anyway" : "Send Anyway", onCancel: () => setSendWithoutSubject(null), onConfirm: () => { const pending = sendWithoutSubject; setSendWithoutSubject(null); submitSend(!!pending.scheduled, pending.body); } }));
  }

  /* ---------------- Connect view ---------------- */
  function ConnectView({ accounts, onConnectGmail, onBack, toast, mailboxOptions }) {
    const [imap, setImap] = React.useState({ email: "", name: "", user: "", pass: "", imapHost: "", imapPort: "993", imapEnc: "SSL/TLS", smtpHost: "", smtpPort: "465", smtpEnc: "SSL/TLS" });
    const [test, setTest] = React.useState({ imap: null, smtp: null }); // null|testing|ok|fail
    const [oauth, setOauth] = React.useState("idle"); // idle|connecting
    const f = (k) => (ev) => setImap(s => ({ ...s, [k]: ev.target.value }));
    const gmail = accounts.find(a => a.provider === "google");

    const runTest = (which) => {
      setTest(t => ({ ...t, [which]: "testing" }));
      const host = which === "imap" ? imap.imapHost : imap.smtpHost;
      setTimeout(() => {
        const ok = host && imap.email;
        setTest(t => ({ ...t, [which]: ok ? "ok" : "fail" }));
        toast(which.toUpperCase() + (ok ? " metadata is ready for a settings draft; no external mailbox connection was attempted." : " metadata incomplete — fill host, email and provider details."), ok ? "orange" : "red");
      }, 1100);
    };
    const testBadge = (s) => s === "testing" ? e("span", { className: "faint", style: { fontSize: 12, display: "inline-flex", alignItems: "center", gap: 6 } }, e(Icon, { name: "refresh", size: 13, className: "spin" }), "Testing…")
      : s === "ok" ? e(Badge, { tone: "b-green", dot: true }, "Metadata checked")
      : s === "fail" ? e(Badge, { tone: "b-red", dot: true }, "Failed") : null;

    return e("div", { className: "page", style: { maxWidth: 1080 } },
      e("div", { className: "crumbs" }, e("span", null, "Mailbox"), e("span", { className: "sep" }, "/"), e("span", { style: { color: "var(--text-2)" } }, "Connect Email")),
      e("div", { className: "page-head" },
        e("div", null, e("h1", { className: "page-title" }, "Configure mailbox provider"), e("div", { className: "page-sub" }, "Create System Settings drafts for mailbox provider metadata. Live OAuth, IMAP and SMTP sync require approved provider setup and are not opened from this screen.")),
        e("div", { className: "head-actions" }, e(Button, { icon: "chevL", onClick: onBack, children: "Back to inbox" }))),
      e("div", { className: "sys-note", style: { marginBottom: 14 } },
        e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
        "Governed setting key: ", e("span", { className: "mono" }, mailboxOptions?.mailbox_settings_key || "collaboration.mailbox_settings"),
        ". Provider records do not store passwords or tokens in this UI."),
      e("div", { className: "grid g-2" },
        // Gmail card
        e("div", { className: "mbx-conn-card" },
          e("div", { className: "mbx-conn-logo", style: { background: "var(--red-soft)" } }, e(Icon, { name: "google", size: 26, style: { color: "var(--red)" } })),
          e("h3", { style: { fontSize: 17 } }, "Gmail / Google Workspace"),
          e("div", { className: "page-sub", style: { marginTop: 6, marginBottom: 16 } }, "Recommended provider. This screen records metadata only; OAuth consent is handled during live provider setup."),
          gmail
            ? e(React.Fragment, null,
                e("div", { className: "mbx-banner", style: { background: "var(--orange-soft)", color: "var(--orange)" } }, e(Icon, { name: "alert", size: 16 }), "Governed metadata active for ", e("b", { style: { marginLeft: 3 } }, gmail.email)),
                e("div", { className: "mbx-crm-kv" }, e("span", { className: "k" }, "External status"), e("span", { className: "v" }, gmail.lastSync)),
                e("div", { className: "mbx-crm-kv" }, e("span", { className: "k" }, "Mail access"), e("span", { className: "v", style: { fontWeight: 600, fontSize: 12 } }, "read · send · labels, after OAuth setup")),
                e("div", { className: "row gap-2", style: { marginTop: 14 } },
                  e(Button, { icon: "refresh", onClick: () => toast("Google re-authorization requires OAuth services and configuration approval.", "orange"), children: "Review OAuth setup" }),
                  e(Button, { variant: "ghost", onClick: () => toast("Use Settings → Accounts to create a removal draft.", "accent"), children: "Remove metadata" })))
            : e(React.Fragment, null,
                e("div", { style: { background: "var(--surface-2)", border: "1px solid var(--border)", borderRadius: 11, padding: "12px 14px", marginBottom: 16, fontSize: 12.5, color: "var(--text-2)", lineHeight: 1.6 } },
                  e("div", { style: { fontWeight: 700, color: "var(--text)", marginBottom: 6 } }, "Builder360 will be able to:"),
                  e("div", { className: "row gap-2", style: { marginBottom: 4 } }, e(Icon, { name: "alert", size: 14, style: { color: "var(--orange)" } }), "Live OAuth consent is not opened from this screen"),
                  e("div", { className: "row gap-2", style: { marginBottom: 4 } }, e(Icon, { name: "check", size: 14, style: { color: "var(--green)" } }), "Provider metadata can be submitted as an approval-controlled System Settings draft"),
                  e("div", { className: "row gap-2" }, e(Icon, { name: "shield", size: 14, style: { color: "var(--text-3)" } }), "Provider tokens must be handled by approved OAuth storage")),
                e("button", { className: "btn btn-primary", style: { width: "100%", height: 44, justifyContent: "center" }, disabled: oauth === "connecting",
                  onClick: () => { setOauth("connecting"); setTimeout(() => { onConnectGmail(); }, 800); } },
                  oauth === "connecting" ? e(React.Fragment, null, e(Icon, { name: "refresh", size: 16, className: "spin" }), "Creating settings draft…") : e(React.Fragment, null, e(Icon, { name: "google", size: 17 }), "Create Google metadata draft")))),
        // IMAP card
        e("div", { className: "mbx-conn-card" },
          e("div", { className: "mbx-conn-logo", style: { background: "var(--blue-soft)" } }, e(Icon, { name: "server", size: 24, style: { color: "var(--blue)" } })),
          e("h3", { style: { fontSize: 17 } }, "IMAP / SMTP"),
          e("div", { className: "page-sub", style: { marginTop: 6, marginBottom: 16 } }, "Outlook, Yahoo and custom domains. Store only host/port metadata here; credentials belong in production secret storage."),
          e("div", { className: "mbx-form-grid" },
            e("div", { className: "mbx-field", style: { gridColumn: "span 2" } }, e("label", null, "Email address"), e("input", { className: "mbx-input", placeholder: "you@company.com", value: imap.email, onChange: f("email") })),
            e("div", { className: "mbx-field" }, e("label", null, "Display name"), e("input", { className: "mbx-input", placeholder: "Your name", value: imap.name, onChange: f("name") })),
            e("div", { className: "mbx-field" }, e("label", null, "Credential reference"), e("input", { className: "mbx-input", placeholder: "vault/mailbox/sales", value: imap.pass, onChange: f("pass") })),
            e("div", { className: "mbx-field" }, e("label", null, "IMAP host"), e("input", { className: "mbx-input", placeholder: "imap.company.com", value: imap.imapHost, onChange: f("imapHost") })),
            e("div", { className: "mbx-field" }, e("label", null, "IMAP port"), e("input", { className: "mbx-input", value: imap.imapPort, onChange: f("imapPort") })),
            e("div", { className: "mbx-field" }, e("label", null, "SMTP host"), e("input", { className: "mbx-input", placeholder: "smtp.company.com", value: imap.smtpHost, onChange: f("smtpHost") })),
            e("div", { className: "mbx-field" }, e("label", null, "SMTP port"), e("input", { className: "mbx-input", value: imap.smtpPort, onChange: f("smtpPort") })),
            e("div", { className: "mbx-field" }, e("label", null, "IMAP encryption"), e("select", { className: "mbx-select", value: imap.imapEnc, onChange: f("imapEnc") }, ["SSL/TLS", "STARTTLS", "None"].map(o => e("option", { key: o }, o)))),
            e("div", { className: "mbx-field" }, e("label", null, "SMTP encryption"), e("select", { className: "mbx-select", value: imap.smtpEnc, onChange: f("smtpEnc") }, ["SSL/TLS", "STARTTLS", "None"].map(o => e("option", { key: o }, o))))),
          e("div", { className: "row between", style: { marginTop: 16, gap: 10, flexWrap: "wrap" } },
            e("div", { className: "row gap-2", style: { flexWrap: "wrap" } },
              e(Button, { sm: true, icon: "server", onClick: () => runTest("imap"), children: "Test IMAP" }), testBadge(test.imap),
              e(Button, { sm: true, icon: "paperplane", onClick: () => runTest("smtp"), children: "Test SMTP" }), testBadge(test.smtp)),
            e("button", { className: "btn btn-primary", onClick: () => {
              if (!imap.email || !imap.imapHost) { toast("Fill email and IMAP host before creating a metadata draft.", "red"); return; }
              onConnectGmail("imap", imap);
            } }, e(Icon, { name: "check", size: 15 }), "Create metadata draft")))));
  }

  /* ---------------- Settings view ---------------- */
  function Toggle({ on, onClick }) { return e("button", { className: "mbx-toggle" + (on ? " on" : ""), onClick, role: "switch", "aria-checked": on }); }

  function SettingsView({ accounts, onBack, onDisconnect, toast, mailboxOptions, onCreateSettingsDraft }) {
    const activeSetting = mailboxOptions?.mailbox_settings || null;
    const activeValue = activeSetting?.value || {};
    const activeSync = activeValue.sync_scope || {};
    const activeCrm = activeValue.crm_linking || {};
    const activeNotifications = activeValue.notifications || {};
    const [tab, setTab] = React.useState("Accounts");
    const [tg, setTg] = React.useState({
      syncInbox: activeSync.inbox !== false, syncSent: activeSync.sent !== false, syncArchived: activeSync.archived !== false, syncTrash: activeSync.trash === true, syncSpam: activeSync.spam === true, historical: activeSync.historical === true,
      autoMatch: activeCrm.auto_match !== false, autoCreate: activeCrm.auto_create_contacts === true, domainLink: activeCrm.domain_link !== false, dealLink: activeCrm.deal_link !== false, ignoreNewsletters: activeCrm.ignore_newsletters !== false, ignoreNoReply: activeCrm.ignore_no_reply !== false, reviewQueue: activeCrm.review_queue !== false,
      notifNew: activeNotifications.new_email !== false, notifFailSync: activeNotifications.failed_sync !== false, notifFailSend: activeNotifications.failed_send !== false, desktop: activeNotifications.desktop === true, inApp: activeNotifications.in_app !== false,
    });
    React.useEffect(() => setTg({
      syncInbox: activeSync.inbox !== false, syncSent: activeSync.sent !== false, syncArchived: activeSync.archived !== false, syncTrash: activeSync.trash === true, syncSpam: activeSync.spam === true, historical: activeSync.historical === true,
      autoMatch: activeCrm.auto_match !== false, autoCreate: activeCrm.auto_create_contacts === true, domainLink: activeCrm.domain_link !== false, dealLink: activeCrm.deal_link !== false, ignoreNewsletters: activeCrm.ignore_newsletters !== false, ignoreNoReply: activeCrm.ignore_no_reply !== false, reviewQueue: activeCrm.review_queue !== false,
      notifNew: activeNotifications.new_email !== false, notifFailSync: activeNotifications.failed_sync !== false, notifFailSend: activeNotifications.failed_send !== false, desktop: activeNotifications.desktop === true, inApp: activeNotifications.in_app !== false,
    }), [activeSetting?.id, activeSetting?.version]);
    const flip = (k) => () => {
      if (!mailboxOptions?.can_manage_settings) {
        toast("Mailbox settings are managed from System Settings. You have read-only access.", "orange");
        return;
      }
      setTg(s => ({ ...s, [k]: !s[k] }));
    };
    const tabs = ["Accounts", "Sync", "CRM Linking", "Notifications"];
    const saveDraft = () => onCreateSettingsDraft && onCreateSettingsDraft({
      sync_scope: {
        inbox: tg.syncInbox,
        sent: tg.syncSent,
        archived: tg.syncArchived,
        trash: tg.syncTrash,
        spam: tg.syncSpam,
        historical: tg.historical,
        frequency: activeSync.frequency || "manual",
      },
      crm_linking: {
        auto_match: tg.autoMatch,
        auto_create_contacts: tg.autoCreate,
        domain_link: tg.domainLink,
        deal_link: tg.dealLink,
        ignore_newsletters: tg.ignoreNewsletters,
        ignore_no_reply: tg.ignoreNoReply,
        review_queue: tg.reviewQueue,
      },
      notifications: {
        new_email: tg.notifNew,
        failed_sync: tg.notifFailSync,
        failed_send: tg.notifFailSend,
        in_app: tg.inApp,
        desktop: tg.desktop,
      },
    }, "Mailbox preference changes are pending approval.");

    const setRow = (name, sub, key) => e("div", { className: "mbx-setrow", key },
      e("div", { className: "st-main" }, e("div", { className: "st-name" }, name), sub && e("div", { className: "st-sub" }, sub)),
      e(Toggle, { on: tg[key], onClick: flip(key) }));

    return e("div", { className: "page", style: { maxWidth: 920 } },
      e("div", { className: "crumbs" }, e("span", null, "Mailbox"), e("span", { className: "sep" }, "/"), e("span", { style: { color: "var(--text-2)" } }, "Settings")),
      e("div", { className: "page-head" },
        e("div", null, e("h1", { className: "page-title" }, "Mailbox settings"), e("div", { className: "page-sub" }, "Accounts, sync rules, CRM auto-linking and notifications are managed through System Settings.")),
        e("div", { className: "head-actions" }, e(Button, { icon: "chevL", onClick: onBack, children: "Back to inbox" }))),
      e("div", { className: "sys-note", style: { marginBottom: 14 } },
        e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
        activeSetting
          ? "Active configuration: v" + activeSetting.version + " · " + activeSetting.status + " · " + (activeSetting.effective_from || "no effective date")
          : "No active mailbox settings are visible. Safe defaults are shown until a setting is approved."),
      e("div", { className: "tabs" }, tabs.map(t => e("div", { key: t, className: "tab " + (tab === t ? "on" : ""), onClick: () => setTab(t) }, t))),

      tab === "Accounts" && e("div", { className: "card card-pad" },
        accounts.length === 0 && e(Empty, { icon: "mail", title: "No mailbox metadata configured", sub: "Create a Gmail or IMAP/SMTP metadata draft to get started." }),
        accounts.map((a, i) => e("div", { key: a.id, className: "mbx-setrow" },
          e(Avatar, { name: a.name, color: a.color, size: 40 }),
          e("div", { className: "st-main" },
            e("div", { className: "row gap-2" }, e("span", { className: "st-name" }, a.email), a.isDefault && e(Badge, { tone: "b-accent" }, "Default")),
            e("div", { className: "st-sub" }, a.authType, " · external status: ", a.lastSync)),
          e("div", { className: "row gap-2" },
            e(Button, { sm: true, icon: "refresh", onClick: () => toast("External mailbox sync for " + a.email + " requires provider integration. Current screen only manages metadata.", "orange"), children: "Sync status" }),
            e(Button, { sm: true, variant: "ghost", onClick: () => toast("Mailbox re-authorization for " + a.email + " requires production OAuth/credential workflow.", "orange"), children: "OAuth setup" }),
            e(Button, { sm: true, variant: "ghost", disabled: !mailboxOptions?.can_manage_settings, onClick: () => onDisconnect(a.id), children: "Remove metadata" }))))),

      tab === "Sync" && e("div", { className: "card card-pad" },
        e("div", { className: "mbx-crm-label" }, "Folders to sync"),
        setRow("Inbox", "Incoming mail", "syncInbox"),
        setRow("Sent", "Outgoing mail", "syncSent"),
        setRow("Archived", "All Mail / archive", "syncArchived"),
        setRow("Trash", "Deleted items", "syncTrash"),
        setRow("Spam", "Junk folder", "syncSpam"),
        e("div", { className: "mbx-crm-label", style: { marginTop: 18 } }, "History"),
        setRow("Sync historical email", "Backfill messages from before connection", "historical"),
        e("div", { className: "mbx-setrow" },
          e("div", { className: "st-main" }, e("div", { className: "st-name" }, "Sync from date"), e("div", { className: "st-sub" }, "Oldest message to import")),
          e("select", { className: "mbx-select", style: { width: 160 }, defaultValue: "Last 90 days" }, ["Last 30 days", "Last 90 days", "Last 12 months", "Everything"].map(o => e("option", { key: o }, o)))),
        e("div", { className: "mbx-setrow" },
          e("div", { className: "st-main" }, e("div", { className: "st-name" }, "Sync frequency"), e("div", { className: "st-sub" }, "Background refresh interval")),
          e("select", { className: "mbx-select", style: { width: 160 }, defaultValue: "Manual only", disabled: true }, ["Manual only", "Every 15 min", "Every 5 min", "Real-time (push)"].map(o => e("option", { key: o }, o)))),
        e("div", { className: "row gap-2", style: { marginTop: 16 } },
          e(Button, { variant: "primary", icon: "check", disabled: !mailboxOptions?.can_manage_settings, onClick: saveDraft, children: "Create settings draft" }),
          e("span", { className: "faint", style: { fontSize: 12, fontWeight: 600 } }, mailboxOptions?.can_manage_settings ? "Draft requires approval before activation." : "Read-only: settings.manage permission required."))),

      tab === "CRM Linking" && e("div", { className: "card card-pad" },
        e("div", { className: "mbx-crm-label" }, "Auto-matching"),
        setRow("Match emails to contacts", "Link by sender / recipient address", "autoMatch"),
        setRow("Auto-create contacts", "Create a lead from unknown senders", "autoCreate"),
        setRow("Link companies by domain", "e.g. @ultratech.com → UltraTech Cement", "domainLink"),
        setRow("Link to open deals", "When a matched contact has an active deal", "dealLink"),
        e("div", { className: "mbx-crm-label", style: { marginTop: 18 } }, "Noise control"),
        setRow("Ignore newsletters", "Skip bulk / marketing mail", "ignoreNewsletters"),
        setRow("Ignore no-reply senders", "Skip automated addresses", "ignoreNoReply"),
        setRow("Manual review queue", "Hold low-confidence matches for review", "reviewQueue"),
        e("div", { className: "row gap-2", style: { marginTop: 16 } },
          e(Button, { variant: "primary", icon: "check", disabled: !mailboxOptions?.can_manage_settings, onClick: saveDraft, children: "Create settings draft" }),
          e("span", { className: "faint", style: { fontSize: 12, fontWeight: 600 } }, mailboxOptions?.can_manage_settings ? "CRM-linking preferences are saved as setting drafts." : "Read-only: settings access required."))),

      tab === "Notifications" && e("div", { className: "card card-pad" },
        setRow("New email", "Notify on incoming mail", "notifNew"),
        setRow("Failed sync alerts", "When a mailbox can't sync", "notifFailSync"),
        setRow("Failed send alerts", "When an email bounces or fails", "notifFailSend"),
        e("div", { className: "mbx-crm-label", style: { marginTop: 18 } }, "Channels"),
        setRow("In-app notifications", "Toasts and the bell menu", "inApp"),
        setRow("Desktop notifications", "Browser push (requires permission)", "desktop"),
        e("div", { className: "row gap-2", style: { marginTop: 16 } },
          e(Button, { variant: "primary", icon: "check", disabled: !mailboxOptions?.can_manage_settings, onClick: saveDraft, children: "Create settings draft" }),
          e("span", { className: "faint", style: { fontSize: 12, fontWeight: 600 } }, mailboxOptions?.can_manage_settings ? "Notification changes are saved as setting drafts." : "Read-only: settings access required."))));
  }

  window.MbxComposeDock = ComposeDock;
  window.MbxConnectView = ConnectView;
  window.MbxSettingsView = SettingsView;
})();
