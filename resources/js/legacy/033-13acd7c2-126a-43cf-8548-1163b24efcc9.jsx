const React = window.React;

/* ============================================================
   Builder360 — Calendar: shared helpers + event detail drawer
   + create/edit modal  → window.CALUI / window.CalDrawer / window.CalModal
   ============================================================ */
(function () {
  const { Icon, Avatar, Badge, Button } = window;
  const e = React.createElement;
  const CAL = window.CAL;
  const U = (id) => CAL.U[id] || { name: "—", color: "#94a3b8" };

  const DOW = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
  const MON = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  const pad = (n) => String(n).padStart(2, "0");
  const fmtTime = (dt) => { let h = dt.getHours(), m = dt.getMinutes(); const ap = h < 12 ? "AM" : "PM"; h = h % 12 || 12; return h + (m ? ":" + pad(m) : "") + " " + ap; };
  const fmtTimeRange = (a, b) => fmtTime(a) + " – " + fmtTime(b);
  const fmtDate = (dt) => DOW[dt.getDay()] + ", " + dt.getDate() + " " + MON[dt.getMonth()] + " " + dt.getFullYear();
  const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  const durMin = (a, b) => Math.round((b - a) / 60000);
  const safeMeetingUrl = (url) => {
    const value = String(url || "").trim();
    return /^https?:\/\//i.test(value) ? value : "";
  };

  function TypePill({ id }) { const t = CAL.T[id]; return t ? e("span", { className: "cal-dr-type", style: { background: t.color + "1f", color: t.color } }, e(Icon, { name: t.icon, size: 13 }), t.label) : null; }
  function StatusBadge({ id }) { const s = CAL.ST[id]; return s ? e(Badge, { tone: s.badge, dot: true }, s.label) : null; }
  function PriDot({ id }) { const p = CAL.PR[id]; return p ? e("span", { style: { display: "inline-flex", alignItems: "center", gap: 5, fontSize: 12, fontWeight: 700, color: p.color } }, e("span", { style: { width: 8, height: 8, borderRadius: 99, background: p.color } }), p.label) : null; }

  window.CALUI = { U, DOW, MON, fmtTime, fmtTimeRange, fmtDate, sameDay, durMin, pad, TypePill, StatusBadge, PriDot };

  function CalConfirmModal({ confirm, onCancel }) {
    if (!confirm) return null;

    return e("div", { onClick: ev => ev.stopPropagation(), style: { position: "fixed", inset: 0, zIndex: 1100, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("div", { style: { width: "min(450px,94vw)", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, confirm.title), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, confirm.message)),
          e("button", { type: "button", className: "tm-iconbtn", style: { width: 32, height: 32 }, onClick: onCancel }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e("div", { className: "muted", style: { fontSize: 12 } }, confirm.note || "Confirm to continue."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onCancel, children: "Cancel" }), e(Button, { type: "button", variant: confirm.variant || "primary", icon: "check", onClick: confirm.onConfirm, children: confirm.confirmLabel || "Confirm" })))));
  }

  /* ================= EVENT DETAIL DRAWER ================= */
  function CalDrawer({ event, onClose, onEdit, onUpdate, onDelete, onComplete, onCancel, onOpenCrmRecord, toast }) {
    const [confirm, setConfirm] = React.useState(null);
    const t = CAL.T[event.type];
    const remLabel = (CAL.reminderOptions.find(r => r.v === event.reminder) || {}).label || (event.reminder + " min before");
    const conflicts = event.assignees.flatMap(uid => CAL.conflictsFor(uid, event.start, event.end, event.id).map(c => ({ uid, c }))).slice(0, 3);

    const act = (status, msg, tone) => { onUpdate(event.id, { ...event, status }); toast(msg, tone || "green"); };
    const openLinkedCrm = () => {
      if (onOpenCrmRecord && onOpenCrmRecord(event)) return;
      toast("No Builder360 module route is configured for this linked calendar record.", "orange");
    };

    const row = (icon, k, v) => e("div", { className: "cal-dr-row" },
      e("div", { className: "cal-dr-ic" }, e(Icon, { name: icon, size: 16 })),
      e("div", { style: { flex: 1, minWidth: 0 } }, e("div", { className: "cal-dr-k" }, k), e("div", { className: "cal-dr-v" }, v)));

    const requestCancel = () => {
      setConfirm({
        title: "Cancel event?",
        message: "Attendees will be notified where the Laravel calendar workflow is connected.",
        confirmLabel: "Cancel Event",
        note: event.recordId ? "Backend cancellation preserves audit history." : "This updates the local calendar event state.",
        onConfirm: () => {
          setConfirm(null);
          if (onCancel && onCancel(event)) return;
          act("cancelled", "Event cancelled", "accent");
        },
      });
    };
    const requestDelete = () => {
      if (event.recordId) {
        setConfirm({
          title: "Archive event?",
          message: "This Laravel-backed event will be soft-deleted from active calendar views while preserving audit history.",
          confirmLabel: "Archive Event",
          variant: "danger",
          note: "The event remains recoverable through database/audit records.",
          onConfirm: () => { setConfirm(null); onDelete(event); onClose(); },
        });
        return;
      }
      setConfirm({
        title: "Delete event permanently?",
        message: "This local event will be removed from the current calendar view.",
        confirmLabel: "Delete",
        variant: "danger",
        note: "Laravel-backed events must be cancelled instead of deleted.",
        onConfirm: () => { setConfirm(null); onDelete(event.id); onClose(); toast("Event deleted", "red"); },
      });
    };

    return e("div", { className: "cal-drawer-scrim", onClick: onClose },
      e("div", { className: "cal-drawer", onClick: ev => ev.stopPropagation() },
        e("div", { className: "cal-dr-banner", style: { background: t.color } }),
        e("div", { className: "cal-dr-head" },
          e("div", { className: "row between" },
            e(TypePill, { id: event.type }),
            e("div", { className: "row gap-2" },
              e("button", { className: "tm-iconbtn", style: { width: 32, height: 32 }, title: "Edit", onClick: () => onEdit(event) }, e(Icon, { name: "pencil", size: 15 })),
              e("button", { className: "tm-iconbtn", style: { width: 32, height: 32 }, title: "Close", onClick: onClose }, e(Icon, { name: "x", size: 16 })))),
          e("div", { className: "cal-dr-title", style: { marginTop: 8 } }, event.title),
          e("div", { className: "row gap-2", style: { marginTop: 12 } }, e(StatusBadge, { id: event.status }), e(PriDot, { id: event.priority }), event.recurrence !== "none" && e("span", { className: "row gap-2", style: { fontSize: 12, fontWeight: 700, color: "var(--text-3)" } }, e(Icon, { name: "repeat", size: 13 }), CAL.recurrenceLabels[event.recurrence]))),
        e("div", { className: "cal-dr-body" },
          conflicts.length > 0 && e("div", { className: "cal-conflict" }, e(Icon, { name: "alert", size: 16 }), U(conflicts[0].uid).name.split(" ")[0] + " has " + conflicts.length + " overlapping event" + (conflicts.length > 1 ? "s" : "") + " at this time."),
          row("calClock", "When", e("div", null, fmtDate(event.start), e("div", { className: "faint", style: { fontWeight: 600, marginTop: 2 } }, fmtTimeRange(event.start, event.end) + " · " + durMin(event.start, event.end) + " min · " + event.timezone))),
          event.desc && row("doc", "Description", event.desc),
          e("div", { className: "cal-dr-row" },
            e("div", { className: "cal-dr-ic" }, e(Icon, { name: "users", size: 16 })),
            e("div", { style: { flex: 1 } }, e("div", { className: "cal-dr-k" }, "Assigned to · " + event.team),
              e("div", { className: "cal-people", style: { marginTop: 6 } }, event.assignees.map(id => e("div", { key: id, className: "tm-people-chip" }, e(Avatar, { name: U(id).name, color: U(id).color, size: 20 }), U(id).name))))),
          event.online && row("video", "Online meeting", safeMeetingUrl(event.online)
            ? e("a", { href: safeMeetingUrl(event.online), target: "_blank", rel: "noopener noreferrer", style: { color: "var(--accent)", fontWeight: 700, wordBreak: "break-all" } }, event.online)
            : e("span", { className: "faint", title: "Meeting URL must start with http:// or https:// before it can be opened." }, event.online)),
          event.location && row("mapPin", "Location", event.location),
          event.crm && row(event.crm.type === "deal" ? "tag" : event.crm.type === "project" ? "building" : "user", "Linked " + event.crm.type,
            e("button", { className: "tm-people-chip", style: { border: 0, cursor: "pointer", textAlign: "left" }, title: "Open the existing Builder360 module for this linked calendar record.", onClick: openLinkedCrm },
              e(Icon, { name: "link", size: 13 }),
              e("span", null, event.crm.label, event.crm.sub && e("span", { className: "faint", style: { fontWeight: 600, marginLeft: 6 } }, event.crm.sub)))),
          row("bellRing", "Reminder", remLabel),
          row("user", "Created by", U(event.createdBy).name + " · " + event.createdAt)),
        e("div", { className: "cal-dr-foot" },
          event.status !== "completed" && e(Button, { variant: "primary", sm: true, icon: "check", onClick: () => { if (onComplete && onComplete(event)) return; act("completed", "Marked completed"); }, children: "Complete" }),
          e(Button, { sm: true, icon: "repeat", onClick: () => onEdit(event), children: "Reschedule" }),
          event.status !== "cancelled" && e(Button, { sm: true, icon: "x", onClick: requestCancel, children: "Cancel" }),
          e("div", { style: { flex: 1 } }),
          e("button", { className: "tm-iconbtn", title: "Delete", style: { color: "var(--red)" }, onClick: requestDelete }, e(Icon, { name: "trash", size: 16 })))),
      confirm && e(CalConfirmModal, { confirm, onCancel: () => setConfirm(null) }));
  }

  /* ================= CREATE / EDIT MODAL ================= */
  function CalModal({ event, prefill, onClose, onSave, toast }) {
    const editing = !!event;
    const init = event || Object.assign({
      title: "", type: "meeting", status: "scheduled", priority: "medium", desc: "", location: "", online: "",
      assignees: [CAL.me], team: "Sales Desk", crm: null, reminder: 15, recurrence: "none",
      start: new Date(CAL.NOW.getFullYear(), CAL.NOW.getMonth(), CAL.NOW.getDate(), 11, 0),
      end: new Date(CAL.NOW.getFullYear(), CAL.NOW.getMonth(), CAL.NOW.getDate(), 12, 0),
    }, prefill || {});
    const toLocal = (dt) => dt.getFullYear() + "-" + String(dt.getMonth() + 1).padStart(2, "0") + "-" + String(dt.getDate()).padStart(2, "0") + "T" + String(dt.getHours()).padStart(2, "0") + ":" + String(dt.getMinutes()).padStart(2, "0");
    const [f, setF] = React.useState(Object.assign({}, init, { startStr: toLocal(init.start), endStr: toLocal(init.end), online: init.online || "" }));
    const [err, setErr] = React.useState("");
    const [peopleMenu, setPeopleMenu] = React.useState(false);
    const set = (k, v) => setF(s => ({ ...s, [k]: v }));
    React.useEffect(() => { const h = () => setPeopleMenu(false); if (peopleMenu) { window.addEventListener("click", h); return () => window.removeEventListener("click", h); } }, [peopleMenu]);

    const start = new Date(f.startStr), end = new Date(f.endStr);
    // live conflict detection
    const conflicts = (!isNaN(start) && !isNaN(end) && end > start)
      ? f.assignees.flatMap(uid => CAL.conflictsFor(uid, start, end, editing ? event.id : null).map(c => ({ uid, c }))) : [];

    const submit = () => {
      if (!f.title.trim()) { setErr("Event title is required."); return; }
      if (isNaN(start) || isNaN(end)) { setErr("Start and end date/time are required."); return; }
      if (end <= start) { setErr("End time must be after the start time."); return; }
      if (!f.assignees.length) { setErr("Assign at least one employee or team."); return; }
      const saved = onSave(Object.assign({}, f, { start, end, online: f.online || null }), editing ? event.id : null);
      if (saved === false) return;
      onClose();
    };

    const field = (label, node, full) => e("div", { className: "tm-field" + (full ? " full" : "") }, e("label", null, label), node);

    return e("div", { className: "tm-modal-scrim", onClick: onClose },
      e("div", { className: "tm-modal", onClick: ev => ev.stopPropagation() },
        e("div", { className: "tm-modal-head" },
          e("div", { style: { width: 34, height: 34, borderRadius: 10, background: "var(--accent-soft)", color: "var(--accent)", display: "grid", placeItems: "center" } }, e(Icon, { name: editing ? "pencil" : "calPlus", size: 18 })),
          e("h2", null, editing ? "Edit event" : "New event"),
          e("button", { className: "tm-iconbtn", style: { width: 32, height: 32, border: "none" }, onClick: onClose }, e(Icon, { name: "x", size: 17 }))),
        e("div", { className: "tm-modal-body" },
          err && e("div", { className: "cal-conflict", style: { background: "var(--red-soft)", color: "var(--red)", marginBottom: 14 } }, e(Icon, { name: "alert", size: 15 }), err),
          e("div", { className: "tm-form-grid" },
            field("Event title *", e("input", { className: "tm-input", autoFocus: true, value: f.title, placeholder: "e.g. Site visit — Rohit Agarwal", onChange: ev => set("title", ev.target.value) }), true),
            field("Event type", e("select", { className: "tm-select", value: f.type, onChange: ev => set("type", ev.target.value) }, CAL.types.map(t => e("option", { key: t.id, value: t.id }, t.label)))),
            field("Priority", e("select", { className: "tm-select", value: f.priority, onChange: ev => set("priority", ev.target.value) }, CAL.priorities.map(p => e("option", { key: p.id, value: p.id }, p.label)))),
            field("Start *", e("input", { className: "tm-input", type: "datetime-local", value: f.startStr, onChange: ev => set("startStr", ev.target.value) })),
            field("End *", e("input", { className: "tm-input", type: "datetime-local", value: f.endStr, onChange: ev => set("endStr", ev.target.value) })),
            field("Status", e("select", { className: "tm-select", value: f.status, onChange: ev => set("status", ev.target.value) }, CAL.statuses.map(s => e("option", { key: s.id, value: s.id }, s.label)))),
            field("Team / department", e("select", { className: "tm-select", value: f.team, onChange: ev => set("team", ev.target.value) }, [...new Set(CAL.users.map(u => u.team))].map(t => e("option", { key: t, value: t }, t)))),
            field("Assigned employees *", e("div", { style: { position: "relative" } },
              e("div", { className: "tm-input", style: { display: "flex", alignItems: "center", gap: 6, cursor: "pointer", flexWrap: "wrap", height: "auto", minHeight: 40, padding: "5px 10px" }, onClick: ev => { ev.stopPropagation(); setPeopleMenu(o => !o); } },
                f.assignees.length ? f.assignees.map(id => e("span", { key: id, className: "tm-people-chip", style: { padding: "2px 8px 2px 2px" } }, e(Avatar, { name: U(id).name, color: U(id).color, size: 18 }), U(id).name.split(" ")[0])) : e("span", { className: "faint" }, "Select…"),
                e(Icon, { name: "chevD", size: 14, style: { marginLeft: "auto", color: "var(--text-3)" } })),
              peopleMenu && e("div", { className: "tm-menu people-search-menu", style: { top: 44, left: 0, right: 0 } }, e(window.SearchablePeoplePicker, { items: CAL.users, selected: f.assignees, mode: "multi", placeholder: "Search employee name, team or role...", emptyText: "No matching employees", onChange: value => set("assignees", value), getId: user => user.id, getLabel: user => user.name, getSubLabel: user => [user.title, user.team, user.dept].filter(Boolean).join(" · ") }))), true),
            field("Location", e("input", { className: "tm-input", value: f.location, placeholder: "Office / site address", onChange: ev => set("location", ev.target.value) })),
            field("Online meeting link", e("input", { className: "tm-input", type: "url", value: f.online, placeholder: "https://meet…", onChange: ev => set("online", ev.target.value) })),
            field("Reminder", e("select", { className: "tm-select", value: f.reminder, onChange: ev => set("reminder", +ev.target.value) }, CAL.reminderOptions.map(r => e("option", { key: r.v, value: r.v }, r.label)))),
            field("Recurrence", e("select", { className: "tm-select", value: f.recurrence, onChange: ev => set("recurrence", ev.target.value) }, Object.entries(CAL.recurrenceLabels).map(([k, v]) => e("option", { key: k, value: k }, v)))),
            field("Description / notes", e("textarea", { className: "tm-textarea", value: f.desc, placeholder: "Agenda, context, talking points…", onChange: ev => set("desc", ev.target.value) }), true)),
          conflicts.length > 0 && e("div", { className: "cal-conflict", style: { marginTop: 14 } }, e(Icon, { name: "alert", size: 16 }),
            e("span", null, e("b", null, U(conflicts[0].uid).name.split(" ")[0]), " already has “", conflicts[0].c.title, "” during this time. You can still save (override)."))),
        e("div", { className: "tm-modal-foot" },
          e("span", { className: "faint", style: { fontSize: 12, fontWeight: 600 } }, conflicts.length ? e("span", { style: { color: "var(--orange)" } }, "⚠ Schedule conflict") : "No conflicts"),
          e("div", { style: { flex: 1 } }),
          e(Button, { onClick: onClose, children: "Cancel" }),
          e(Button, { variant: "primary", icon: editing ? "check" : "plus", onClick: submit, children: editing ? "Save changes" : "Create event" }))));
  }

  window.CalDrawer = CalDrawer;
  window.CalModal = CalModal;
})();
