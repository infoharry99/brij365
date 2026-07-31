const React = window.React;

/* Builder360 — Bespoke promotions pt.1: Complaints, Legal/RERA, Documents, BOQ */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, HBars, PageHead, ChipSelect, Seg } = window;
  const e = React.createElement;

  function T(head, rows) {
    const th = head.map((h, i) => e("th", { key: i, style: (h.r ? { textAlign: "right" } : {}) }, h.l != null ? h.l : h));
    const body = rows.map((r, i) => e("tr", { key: i }, r.map((c, j) => e("td", { key: j, className: (head[j] && head[j].r ? "num" : "") }, c))));
    return e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" }, e("thead", null, e("tr", null, th)), e("tbody", null, body)));
  }
  const user = (n, sub) => e("div", { className: "cell-user" }, e(Avatar, { name: n, sm: true }), (sub ? e("div", null, e("div", { className: "cell-strong" }, n), e("div", { className: "cell-sub" }, sub)) : e("span", { className: "cell-strong" }, n)));
  const apiJson = async (url, options = {}) => {
    const headers = Object.assign({ Accept: "application/json" }, options.headers || {});
    if (options.body && !(options.body instanceof FormData) && !headers["Content-Type"]) headers["Content-Type"] = "application/json";
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
    if (token) headers["X-CSRF-TOKEN"] = token;
    const response = await fetch(url, Object.assign({ credentials: "same-origin" }, options, { headers }));
    const text = await response.text();
    const body = text ? JSON.parse(text) : {};
    if (!response.ok) {
      const message = body.message || Object.values(body.errors || {}).flat()[0] || "Request failed.";
      throw new Error(message);
    }
    return body;
  };

  function ticketRecordUrl(template, ticket) {
    return String(template || "").replace("__TICKET__", String(ticket?.source_id || ticket?.id || ""));
  }

  function titleCase(value) {
    return String(value || "").replace(/_/g, " ").split(" ").filter(Boolean).map(x => x.charAt(0).toUpperCase() + x.slice(1)).join(" ");
  }

  function ticketPriorityTone(priority) {
    if (priority === "critical" || priority === "high") return "b-red";
    if (priority === "medium") return "b-orange";
    return "b-slate";
  }

  function ticketStatusTone(status) {
    if (status === "closed" || status === "resolved") return "b-green";
    if (status === "assigned" || status === "in_progress") return "b-blue";
    return "b-orange";
  }

  function ticketSlaLabel(ticket) {
    if (ticket.status === "resolved" || ticket.status === "closed") return "Done";
    if (!ticket.sla_due_at) return "SLA pending";
    const diffHours = Math.round((new Date(ticket.sla_due_at).getTime() - Date.now()) / 36e5);
    if (Number.isNaN(diffHours)) return "SLA pending";
    if (diffHours < 0) return Math.abs(diffHours) + "h breached";
    if (diffHours < 24) return diffHours + "h left";
    return Math.ceil(diffHours / 24) + "d left";
  }

  function complaintRow(ticket) {
    const unitCode = ticket.unit?.unit_code || ticket.unit?.unit_number || ticket.booking?.booking_code || "Unit pending";
    const projectCode = ticket.project?.code || ticket.project?.name || "Project";
    return {
      source_id: ticket.id,
      ticket_number: ticket.ticket_number,
      customer: ticket.customer?.name || "Customer pending",
      unit: unitCode + " · " + projectCode,
      category: titleCase(ticket.category || "other"),
      priority: ticket.priority || "medium",
      subject: ticket.subject || ticket.description || "Service request",
      status: ticket.status || "open",
      sla_due_at: ticket.sla_due_at,
      assigned_to: ticket.assigned_to,
      work_orders: ticket.work_orders || [],
    };
  }

  function ComplaintCreateModal({ options, onClose, onSaved, toast }) {
    const bookings = options?.bookings || [];
    const categories = options?.categories || [];
    const priorities = options?.priorities || [];
    const [form, setForm] = React.useState({
      booking_id: bookings[0]?.id ? String(bookings[0].id) : "",
      category: categories[0]?.value || "maintenance",
      priority: "medium",
      subject: "",
      description: "",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = (key, value) => setForm(current => Object.assign({}, current, { [key]: value }));
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!form.booking_id) {
        setError("Select a confirmed booking.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(options.store_url, {
          method: "POST",
          body: JSON.stringify({
            booking_id: Number(form.booking_id),
            category: form.category,
            priority: form.priority,
            source: "internal",
            subject: form.subject.trim(),
            description: form.description.trim(),
          }),
        });
        onSaved(complaintRow(body.data));
        toast("Service ticket " + body.data.ticket_number + " created in Laravel.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(760px,96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "Add After-Sales Complaint"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates a validated service ticket against a confirmed booking in MySQL.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        !bookings.length && e("div", { className: "empty", style: { marginBottom: 12 } }, e("div", { className: "empty-ic" }, e(Icon, { name: "headset", size: 24 })), e("h3", null, "No confirmed bookings available"), e("div", null, "Service tickets require a confirmed booking in your company scope.")),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Confirmed Booking", e("select", { style: field, value: form.booking_id, onChange: ev => set("booking_id", ev.target.value), disabled: busy || !bookings.length, required: true },
            bookings.map(b => e("option", { key: b.id, value: b.id }, (b.booking_code || ("Booking #" + b.id)) + " · " + (b.unit?.unit_code || b.unit?.unit_number || "Unit") + " · " + (b.customer?.name || "Customer"))))),
          e("label", { style: label }, "Category", e("select", { style: field, value: form.category, onChange: ev => set("category", ev.target.value), disabled: busy }, categories.map(c => e("option", { key: c.value, value: c.value }, c.label)))),
          e("label", { style: label }, "Priority", e("select", { style: field, value: form.priority, onChange: ev => set("priority", ev.target.value), disabled: busy }, priorities.map(p => e("option", { key: p.value, value: p.value }, p.label)))),
          e("label", { style: label }, "Subject", e("input", { style: field, value: form.subject, onChange: ev => set("subject", ev.target.value), maxLength: 255, disabled: busy, required: true }))),
        e("label", { style: Object.assign({}, label, { marginBottom: 14 }) }, "Description", e("textarea", { style: Object.assign({}, field, { minHeight: 104, resize: "vertical" }), value: form.description, onChange: ev => set("description", ev.target.value), minLength: 10, maxLength: 5000, disabled: busy, required: true })),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "SLA hours come from System Settings: after_sales.sla_hours."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "plus", type: "submit", disabled: busy || !bookings.length, children: busy ? "Creating…" : "Create Ticket" })))));
  }

  function ComplaintActionModal({ options, ticket, action, onClose, onSaved, toast }) {
    const assignees = options?.assignees || [];
    const defaultAssignee = assignees.find(u => String(u.id) === String(ticket?.assigned_to?.id)) || assignees[0] || null;
    const [form, setForm] = React.useState({
      assigned_to_user_id: defaultAssignee?.id ? String(defaultAssignee.id) : "",
      note: action === "assign" ? "Assigned from After-Sales Complaints board." : action === "close" ? "Closed after buyer/service confirmation." : "",
      resolution_summary: "Resolved after customer service verification.",
      customer_rating: "5",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const title = action === "assign" ? "Assign Service Ticket" : action === "resolve" ? "Resolve Service Ticket" : "Close Service Ticket";
    const submitLabel = action === "assign" ? "Assign Ticket" : action === "resolve" ? "Mark Resolved" : "Close Ticket";
    const endpointTemplate = action === "assign" ? options?.assign_url_template : action === "resolve" ? options?.resolve_url_template : options?.close_url_template;
    const set = (key, value) => setForm(current => Object.assign({}, current, { [key]: value }));
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!ticket?.source_id || !endpointTemplate) {
        setError("This action requires a Laravel service ticket record and route.");
        return;
      }
      let payload = {};
      if (action === "assign") {
        if (!form.assigned_to_user_id) {
          setError("Select an assignee.");
          return;
        }
        payload = { assigned_to_user_id: Number(form.assigned_to_user_id), note: form.note.trim() || null };
      } else if (action === "resolve") {
        if (form.resolution_summary.trim().length < 10) {
          setError("Resolution summary must be at least 10 characters.");
          return;
        }
        payload = { resolution_summary: form.resolution_summary.trim() };
      } else {
        payload = { customer_rating: form.customer_rating ? Number(form.customer_rating) : null, note: form.note.trim() || null };
      }
      try {
        setBusy(true);
        const body = await apiJson(ticketRecordUrl(endpointTemplate, ticket), { method: "PATCH", body: JSON.stringify(payload) });
        onSaved(complaintRow(body.data));
        toast(body.data.ticket_number + " " + (action === "assign" ? "assigned." : action === "resolve" ? "resolved." : "closed."), "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(680px,96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, title), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, ticket?.ticket_number || "Ticket", " · ", ticket?.customer || "Customer", " · ", ticket?.unit || "Unit")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("div", { className: "grid g-2", style: { marginBottom: 12 } },
          e(Stat, { label: "Priority", value: titleCase(ticket?.priority), icon: "alert", tone: ticket?.priority === "critical" || ticket?.priority === "high" ? "red" : "orange", sub: ticket?.category }),
          e(Stat, { label: "Current Status", value: titleCase(ticket?.status), icon: "headset", tone: "blue", sub: ticketSlaLabel(ticket || {}) })),
        action === "assign" && e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Assignee", e(window.SearchablePeoplePicker, {
            items: assignees,
            selected: form.assigned_to_user_id,
            mode: "single",
            placeholder: "Search employee name...",
            disabled: busy || !assignees.length,
            required: true,
            emptyText: "No matching employees",
            onChange: value => set("assigned_to_user_id", value),
            getId: user => user.id,
            getLabel: user => user.name || user.email || "Employee",
            getSubLabel: user => [user.role, user.email].filter(Boolean).join(" · "),
          })),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Assignment Note", e("textarea", { style: Object.assign({}, field, { minHeight: 82 }), value: form.note, onChange: ev => set("note", ev.target.value), disabled: busy, maxLength: 1000 }))),
        action === "resolve" && e("label", { style: Object.assign({}, label, { marginBottom: 12 }) }, "Resolution Summary", e("textarea", { style: Object.assign({}, field, { minHeight: 110 }), value: form.resolution_summary, onChange: ev => set("resolution_summary", ev.target.value), disabled: busy, minLength: 10, maxLength: 5000, required: true })),
        action === "close" && e("div", { className: "grid", style: { gridTemplateColumns: "160px 1fr", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Customer Rating", e("select", { style: field, value: form.customer_rating, onChange: ev => set("customer_rating", ev.target.value), disabled: busy },
            ["5", "4", "3", "2", "1"].map(v => e("option", { key: v, value: v }, v + " / 5")))),
          e("label", { style: label }, "Closure Note", e("textarea", { style: Object.assign({}, field, { minHeight: 82 }), value: form.note, onChange: ev => set("note", ev.target.value), disabled: busy, maxLength: 1000 }))),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "The Laravel backend enforces permission, company scope, status transition, validation and audit rules for this ticket action."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Source: ", options?.source || "Laravel"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: action === "assign" ? "userPlus" : "check", type: "submit", disabled: busy || (action === "assign" && !assignees.length), children: busy ? "Savingâ€¦" : submitLabel })))));
  }

  function Complaints({ toast }) {
    const [view, setView] = React.useState("Board");
    const options = window.Builder360Server?.after_sales_options || null;
    const [tickets, setTickets] = React.useState((options?.tickets || []).map(complaintRow));
    const [loading, setLoading] = React.useState(Boolean(options?.index_url));
    const [error, setError] = React.useState("");
    const [creating, setCreating] = React.useState(false);
    const [actionTicket, setActionTicket] = React.useState(null);
    const refreshTickets = React.useCallback(() => {
      if (!options?.index_url) return;
      setLoading(true);
      setError("");
      apiJson(options.index_url + "?per_page=25")
        .then(body => setTickets((body.data || []).map(complaintRow)))
        .catch(apiError => {
          setError(apiError.message);
          toast?.("After-sales tickets could not be loaded: " + apiError.message, "orange");
        })
        .finally(() => setLoading(false));
    }, [options?.index_url]);
    React.useEffect(() => refreshTickets(), [refreshTickets]);
    const upsertTicket = row => setTickets(rows => rows.some(t => t.source_id === row.source_id) ? rows.map(t => t.source_id === row.source_id ? row : t) : [row, ...rows]);
    const openTicketAction = (ticket, action) => {
      const template = action === "assign" ? options?.assign_url_template : action === "resolve" ? options?.resolve_url_template : options?.close_url_template;
      const allowed = action === "assign" ? options?.can_assign : action === "resolve" ? options?.can_resolve : options?.can_close;
      if (!allowed || !template || !ticket?.source_id) {
        toast("This after-sales ticket action is not available for the selected record or role.", "orange");
        return;
      }
      setActionTicket({ ticket, action });
    };
    const openCreate = () => {
      if (!options?.can_create || !options?.store_url) {
        toast("After-sales complaint creation is not available for this role.", "orange");
        return;
      }
      setCreating(true);
    };
    const hasTicketApi = options?.source === "laravel-sqlite";
    if (!hasTicketApi) {
      return e("div", { className: "page page-wide" },
        e(PageHead, { crumbs: ["Operations", "After-Sales"], title: "After-Sales Complaints", sub: "After-sales complaint API required; no local service ticket rows are fabricated.",
          actions: [e(Badge, { key: "api", tone: "b-orange", dot: true }, "API REQUIRED")] }),
        e(Card, { title: "After-sales workspace unavailable", sub: "The After-Sales Complaints screen requires the Laravel after-sales payload. Complaint counters, customer/unit rows, SLA board columns and ticket actions are intentionally hidden.", pad: true },
          e("div", { className: "empty" },
            e("div", { className: "empty-ic" }, e(Icon, { name: "headset", size: 24 })),
            e("h3", null, "Laravel after-sales payload not loaded"),
            e("div", null, "Use a role and company scope with after_sales_options in Builder360Server."))),
      );
    }
    const visibleTickets = hasTicketApi ? tickets : [];
    const columns = [
      { label: "Open", statuses: ["open"], tone: "b-orange" },
      { label: "In Progress", statuses: ["assigned", "in_progress"], tone: "b-blue" },
      { label: "Resolved", statuses: ["resolved", "closed"], tone: "b-green" },
    ];
    const summary = hasTicketApi ? (options?.summary || {}) : {};
    const openCount = summary.open_tickets ?? visibleTickets.filter(t => ["open", "assigned", "in_progress"].includes(t.status)).length;
    const resolvedMtd = summary.resolved_mtd ?? visibleTickets.filter(t => ["resolved", "closed"].includes(t.status)).length;
    const breached = summary.sla_breached ?? visibleTickets.filter(t => ticketSlaLabel(t).includes("breached")).length;
    const board = e("div", { style: { display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: 14, alignItems: "start" } },
      columns.map(c => {
        const items = visibleTickets.filter(t => c.statuses.includes(t.status));
        return e("div", { key: c.label, style: { background: "var(--surface-2)", borderRadius: 14, padding: 12, border: "1px solid var(--border)" } },
          e("div", { className: "row between", style: { padding: "2px 6px 12px" } }, e(Badge, { tone: c.tone, dot: true }, c.label), e("span", { className: "faint mono", style: { fontWeight: 800 } }, items.length)),
          !items.length && e("div", { className: "empty", style: { padding: 18 } }, e("div", null, loading ? "Loading…" : "No tickets")),
          items.map(t => {
            const sla = ticketSlaLabel(t);
            return e("div", { key: t.ticket_number, className: "card", style: { padding: 13, marginBottom: 10 } },
              e("div", { className: "row between", style: { marginBottom: 8 } }, e("span", { className: "cell-strong mono", style: { fontSize: 12.5 } }, t.ticket_number), e(Badge, { tone: ticketPriorityTone(t.priority) }, titleCase(t.priority))),
              e("div", { style: { fontWeight: 700, fontSize: 13, marginBottom: 3 } }, t.subject || t.category),
              e("div", { className: "cell-sub", style: { marginBottom: 9 } }, t.customer + " · " + t.unit),
              e("div", { className: "row between", style: { gap: 8, flexWrap: "wrap" } },
                e("span", { className: "row gap-2", style: { fontSize: 11.5, color: sla.includes("breached") || sla.includes("h left") ? "var(--red)" : "var(--text-3)", fontWeight: 700 } }, e(Icon, { name: "clock", size: 12 }), sla),
                t.status === "open" && options?.can_assign && e("button", { className: "btn btn-sm btn-ghost", style: { height: 26, color: "var(--accent)" }, onClick: () => openTicketAction(t, "assign") }, "Assign"),
                ["open", "assigned", "in_progress"].includes(t.status) && options?.can_resolve && e("button", { className: "btn btn-sm btn-ghost", style: { height: 26, color: "var(--green)" }, onClick: () => openTicketAction(t, "resolve") }, "Resolve"),
                t.status === "resolved" && options?.can_close && e("button", { className: "btn btn-sm btn-ghost", style: { height: 26, color: "var(--green)" }, onClick: () => openTicketAction(t, "close") }, "Close")));
          }));
      }));
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "After-Sales"], title: "After-Sales Complaints", sub: "Post-possession service requests with SLA tracking and resolution accountability.",
        actions: [e(Seg, { key: 1, options: ["Board", "List"], value: view, onChange: setView }), e(Button, { key: 2, icon: "refresh", onClick: refreshTickets, children: loading ? "Loading…" : "Refresh" }), e(Button, { key: 3, icon: "plus", variant: "primary", onClick: openCreate, children: "Add Complaint" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Open Complaints", value: String(openCount), icon: "headset", tone: "orange", sub: hasTicketApi ? "MySQL records" : "API required" }),
        e(Stat, { label: "Resolved (MTD)", value: String(resolvedMtd), icon: "check", tone: "green" }),
        e(Stat, { label: "SLA Breached", value: String(breached), icon: "alert", tone: "red" }),
        e(Stat, { label: "Total Tickets", value: String(summary.total_tickets ?? visibleTickets.length), icon: "clock", tone: "accent" })),
      error && e("div", { style: { marginBottom: 12, background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, fontSize: 13, fontWeight: 700 } }, error),
      view === "Board" ? board : e(Card, { title: "All Complaints", sub: hasTicketApi ? "Laravel service tickets" : "Laravel after-sales API required" },
        T([{ l: "Ticket" }, { l: "Customer" }, { l: "Category" }, { l: "Priority" }, { l: "SLA" }, { l: "Status" }],
          visibleTickets.map(t => [e("span", { className: "cell-strong mono" }, t.ticket_number), user(t.customer, t.unit), t.category, e(Badge, { tone: ticketPriorityTone(t.priority) }, titleCase(t.priority)), e("span", { className: "faint" }, ticketSlaLabel(t)), e(Badge, { tone: ticketStatusTone(t.status), dot: true }, titleCase(t.status))]))),
      creating && e(ComplaintCreateModal, { options, onClose: () => setCreating(false), onSaved: upsertTicket, toast }),
      actionTicket && e(ComplaintActionModal, { options, ticket: actionTicket.ticket, action: actionTicket.action, onClose: () => setActionTicket(null), onSaved: upsertTicket, toast }),
    );
  }

  // ================= LEGAL / RERA =================
  function LegalApiRequired() {
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "Legal / RERA"], title: "Legal / RERA Tracking", sub: "Legal/RERA API required; no local registration, approval, validity or compliance reminder rows are fabricated.",
        actions: [e(Badge, { key: "api", tone: "b-orange", dot: true }, "API REQUIRED")] }),
      e(Card, { title: "Legal/RERA workspace unavailable", sub: "The Legal/RERA screen requires the Laravel legal compliance payload. Static registrations, approval counts, expiry counters and reminder rows are intentionally hidden.", pad: true },
        e("div", { className: "empty" },
          e("div", { className: "empty-ic" }, e(Icon, { name: "shield", size: 24 })),
          e("h3", null, "Laravel legal compliance payload not loaded"),
          e("div", null, "Use a role and company/project scope with legal_compliance_options in Builder360Server."))),
    );
  }

  function legalRecordUrl(template, id, token) {
    return String(template || "").replace(token, String(id || ""));
  }

  function legalStatusTone(status) {
    if (["verified", "approved", "completed", "filed"].includes(status)) return "b-green";
    if (["submitted", "applied", "open"].includes(status)) return "b-blue";
    if (["expired", "overdue"].includes(status)) return "b-red";
    return "b-orange";
  }

  function dueLabel(dateValue) {
    if (!dateValue) return "No expiry";
    const diffDays = Math.ceil((new Date(dateValue).getTime() - Date.now()) / 86400000);
    if (Number.isNaN(diffDays)) return dateValue;
    if (diffDays < 0) return Math.abs(diffDays) + " days overdue";
    if (diffDays === 0) return "Due today";
    return diffDays + " days";
  }

  function LegalActionModal({ options, record, action, onClose, onSaved, toast }) {
    const isObligation = action === "obligation_complete";
    const [form, setForm] = React.useState({
      verification_note: action === "rera_verify" ? "Verified from statutory reference records." : "Verified against authority approval record.",
      evidence_document_reference: record?.evidence_document_reference || ("LEGAL-EVIDENCE-" + (record?.obligation_number || record?.id || "")),
      notes: "Completed from Legal/RERA tracking screen.",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const title = action === "rera_verify" ? "Verify RERA Registration" : action === "approval_verify" ? "Verify Project Approval" : "Complete Compliance Obligation";
    const reference = record?.registration_number || record?.approval_code || record?.obligation_number || ("Record #" + record?.id);
    const url = action === "rera_verify"
      ? legalRecordUrl(options?.rera_verify_url_template, record?.id, "__RERA__")
      : action === "approval_verify"
        ? legalRecordUrl(options?.approval_verify_url_template, record?.id, "__APPROVAL__")
        : legalRecordUrl(options?.obligation_complete_url_template, record?.id, "__OBLIGATION__");
    const set = (key, value) => setForm(current => Object.assign({}, current, { [key]: value }));
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!record?.id || !url) {
        setError("This legal action requires a Laravel record and route.");
        return;
      }
      const payload = isObligation
        ? { evidence_document_reference: form.evidence_document_reference.trim(), notes: form.notes.trim() || null }
        : { verification_note: form.verification_note.trim() || null };
      if (isObligation && !payload.evidence_document_reference) {
        setError("Evidence document reference is required.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(url, { method: "PATCH", body: JSON.stringify(payload) });
        onSaved(body.data);
        toast(reference + (isObligation ? " completed." : " verified."), "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(680px,96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, title), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, reference, " · ", record?.project?.code || record?.project?.name || "Project/company level")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("div", { className: "grid g-2", style: { marginBottom: 12 } },
          e(Stat, { label: "Status", value: titleCase(record?.status), icon: "shield", tone: "blue", sub: record?.authority_name || record?.title || "Legal/RERA record" }),
          e(Stat, { label: isObligation ? "Due" : "Valid Till", value: isObligation ? dueLabel(record?.due_on) : (record?.expires_on || record?.approved_on || "Pending"), icon: "clock", tone: "orange", sub: "Tracking only · not legal advice" })),
        isObligation
          ? e("div", { className: "grid", style: { gridTemplateColumns: "1fr", gap: 12, marginBottom: 12 } },
              e("label", { style: label }, "Evidence Document Reference", e("input", { style: field, value: form.evidence_document_reference, onChange: ev => set("evidence_document_reference", ev.target.value), disabled: busy, maxLength: 255, required: true })),
              e("label", { style: label }, "Completion Notes", e("textarea", { style: Object.assign({}, field, { minHeight: 104 }), value: form.notes, onChange: ev => set("notes", ev.target.value), disabled: busy, maxLength: 5000 })))
          : e("label", { style: Object.assign({}, label, { marginBottom: 12 }) }, "Verification Note", e("textarea", { style: Object.assign({}, field, { minHeight: 104 }), value: form.verification_note, onChange: ev => set("verification_note", ev.target.value), disabled: busy, maxLength: 2000 })),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "Laravel enforces legal record permissions, company/project scope, validation, status transition and audit history. This screen records tracking evidence only."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Source: ", options?.source || "Laravel"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "check", type: "submit", disabled: busy, children: busy ? "Savingâ€¦" : (isObligation ? "Complete Obligation" : "Verify Record") })))));
  }

  function Legal({ toast }) {
    const options = window.Builder360Server?.legal_compliance_options || null;
    if (!options?.source) return e(LegalApiRequired);

    const [reraRows, setReraRows] = React.useState(options.rera_registrations || []);
    const [approvalRows, setApprovalRows] = React.useState(options.project_approvals || []);
    const [obligationRows, setObligationRows] = React.useState(options.compliance_obligations || []);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState("");
    const [legalAction, setLegalAction] = React.useState(null);
    const [legalProjectFilter, setLegalProjectFilter] = React.useState("all");
    const [legalDeadlineFilter, setLegalDeadlineFilter] = React.useState("all");
    const legalDeadlineOptions = [["all", "All dates"], ["30", "Due/expiring 30 days"], ["90", "Due/expiring 90 days"]];

    const refreshLegal = React.useCallback(() => {
      setLoading(true);
      setError("");
      const reraParams = new URLSearchParams({ per_page: "20" });
      const approvalParams = new URLSearchParams({ per_page: "20" });
      const obligationParams = new URLSearchParams({ per_page: "20" });
      if (legalProjectFilter !== "all") {
        reraParams.set("project_id", legalProjectFilter);
        approvalParams.set("project_id", legalProjectFilter);
        obligationParams.set("project_id", legalProjectFilter);
      }
      if (legalDeadlineFilter !== "all") {
        reraParams.set("expires_within_days", legalDeadlineFilter);
        approvalParams.set("expires_within_days", legalDeadlineFilter);
        obligationParams.set("due_within_days", legalDeadlineFilter);
      }
      Promise.all([
        apiJson(options.rera_index_url + "?" + reraParams.toString()),
        apiJson(options.approval_index_url + "?" + approvalParams.toString()),
        apiJson(options.obligation_index_url + "?" + obligationParams.toString()),
      ])
        .then(([reraBody, approvalBody, obligationBody]) => {
          setReraRows(reraBody.data || []);
          setApprovalRows(approvalBody.data || []);
          setObligationRows(obligationBody.data || []);
        })
        .catch(apiError => {
          setError(apiError.message);
          toast?.("Legal registers could not be loaded: " + apiError.message, "orange");
        })
        .finally(() => setLoading(false));
    }, [options.rera_index_url, options.approval_index_url, options.obligation_index_url, legalProjectFilter, legalDeadlineFilter]);

    React.useEffect(() => refreshLegal(), [refreshLegal]);

    const openLegalAction = (record, action) => {
      const allowed = action === "obligation_complete" ? options.can_complete : options.can_verify;
      const template = action === "rera_verify" ? options.rera_verify_url_template : action === "approval_verify" ? options.approval_verify_url_template : options.obligation_complete_url_template;
      if (!allowed || !template || !record?.id) {
        toast("This Legal/RERA action is not available for the selected record or role.", "orange");
        return;
      }
      setLegalAction({ record, action });
    };
    const onLegalActionSaved = (record) => {
      if (legalAction?.action === "rera_verify") setReraRows(rows => rows.map(item => item.id === record.id ? record : item));
      if (legalAction?.action === "approval_verify") setApprovalRows(rows => rows.map(item => item.id === record.id ? record : item));
      if (legalAction?.action === "obligation_complete") setObligationRows(rows => rows.map(item => item.id === record.id ? record : item));
    };

    const approvalItems = [
      ...reraRows.map(row => ({
        id: "rera-" + row.id,
        type: "RERA Registration",
        authority: row.authority_name,
        project: row.project?.code || row.project?.name || "Project",
        reference: row.registration_number,
        validTill: row.expires_on || "No expiry",
        status: row.status,
        action: row.status === "submitted" && options.can_verify ? e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--accent)" }, onClick: () => openLegalAction(row, "rera_verify") }, "Verify") : e("span", { className: "faint" }, "—"),
      })),
      ...approvalRows.map(row => ({
        id: "approval-" + row.id,
        type: row.approval_type,
        authority: row.authority_name,
        project: row.project?.code || row.project?.name || "Project",
        reference: row.approval_code,
        validTill: row.expires_on || row.approved_on || "Pending",
        status: row.status,
        action: ["applied", "approved"].includes(row.status) && options.can_verify ? e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--accent)" }, onClick: () => openLegalAction(row, "approval_verify") }, "Verify") : e("span", { className: "faint" }, "—"),
      })),
    ];

    const summary = options.summary || {};

    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "Legal / RERA"], title: "Legal / RERA Tracking", sub: "RERA registrations, project approvals and compliance obligations from Laravel. Tracking only — not legal advice.",
        actions: [
          e("label", { key: "project", className: "chip-select", style: { gap: 8 } },
            e("span", { style: { color: "var(--text-3)" } }, "Project"),
            e("select", { "aria-label": "Filter legal records by project", value: legalProjectFilter, disabled: loading, onChange: ev => setLegalProjectFilter(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 170 } },
              [e("option", { key: "all", value: "all" }, "All scoped projects")].concat((options.projects || []).map(project => e("option", { key: project.id, value: String(project.id) }, project.label || project.name))))),
          e("label", { key: "deadline", className: "chip-select", style: { gap: 8 } },
            e("span", { style: { color: "var(--text-3)" } }, "Focus"),
            e("select", { "aria-label": "Filter legal records by deadline", value: legalDeadlineFilter, disabled: loading, onChange: ev => setLegalDeadlineFilter(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 150 } },
              legalDeadlineOptions.map(([value, label]) => e("option", { key: value, value }, label)))),
          e(Button, { key: 1, icon: "refresh", onClick: refreshLegal, children: loading ? "Loading…" : "Refresh" })
        ] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "RERA Projects", value: String(summary.rera_projects ?? reraRows.length), icon: "shield", tone: "green", sub: "MySQL records" }),
        e(Stat, { label: "Approvals Valid", value: String(summary.approvals_valid ?? approvalRows.filter(row => ["approved", "verified"].includes(row.status)).length), icon: "check", tone: "green" }),
        e(Stat, { label: "Expiring Soon", value: String(summary.expiring_soon ?? 0), icon: "clock", tone: "orange", sub: "within 30 days" }),
        e(Stat, { label: "Compliance Due", value: String(summary.compliance_due ?? obligationRows.filter(row => row.status === "open").length), icon: "alert", tone: "red" })),
      error && e("div", { style: { marginBottom: 12, background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, fontSize: 13, fontWeight: 700 } }, error),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.6fr 1fr", alignItems: "start" } },
        e(Card, { title: "Approval & Compliance Tracker", sub: loading ? "Loading legal registers…" : "RERA and project approvals from Laravel" },
          T([{ l: "Document / Approval" }, { l: "Project" }, { l: "Reference" }, { l: "Valid Till" }, { l: "Status" }, { l: "" }],
            approvalItems.map(row => [e("div", null, e("div", { className: "cell-strong" }, row.type), e("div", { className: "cell-sub" }, row.authority || "Authority pending")), e("span", { className: "tag" }, row.project), e("span", { className: "mono", style: { fontSize: 12 } }, row.reference), e("span", { className: "faint" }, row.validTill), e(Badge, { tone: legalStatusTone(row.status), dot: true }, titleCase(row.status)), row.action]))),
        e(Card, { title: "Compliance Reminders", sub: "open obligations and upcoming deadlines" },
          obligationRows.map((row, i) =>
            e("div", { key: row.id, className: "row gap-3", style: { padding: "13px 16px", borderBottom: i < obligationRows.length - 1 ? "1px solid var(--border)" : "none" } },
              e("div", { style: { width: 36, height: 36, borderRadius: 10, background: "var(--surface-3)", color: row.priority === "critical" ? "var(--red)" : row.priority === "high" ? "var(--orange)" : "var(--blue)", display: "grid", placeItems: "center", flex: "0 0 36px" } }, e(Icon, { name: "clock", size: 17 })),
              e("div", { style: { flex: 1 } }, e("div", { style: { fontWeight: 700, fontSize: 13 } }, row.title), e("div", { className: "cell-sub" }, (row.project?.code || "Company") + " · " + dueLabel(row.due_on))),
              row.status === "open" && options.can_complete ? e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--green)" }, onClick: () => openLegalAction(row, "obligation_complete") }, "Complete") : e(Badge, { tone: legalStatusTone(row.status), dot: true }, titleCase(row.status))))),
      ),
      legalAction && e(LegalActionModal, { options, record: legalAction.record, action: legalAction.action, onClose: () => setLegalAction(null), onSaved: onLegalActionSaved, toast }),
    );
  }

  // ================= DOCUMENT MANAGEMENT (folders + versions) =================
  function LegacyDocuments() {
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "Documents"], title: "Document Management", sub: "Document management fallback is read-only. Laravel document APIs are required for repository counts, folders, uploads, approvals and downloads.",
        actions: [e(Button, { key: 1, icon: "upload", variant: "primary", disabled: true, "aria-disabled": true, title: "Document upload requires Laravel document API bootstrap.", children: "Upload Unavailable" })] }),
      e("div", { style: { marginBottom: 16, background: "rgba(245,158,11,.10)", color: "var(--orange)", border: "1px solid rgba(245,158,11,.28)", borderRadius: 12, padding: 12, fontSize: 13, fontWeight: 700 } },
        e("div", { className: "row gap-2" }, e(Icon, { name: "alert", size: 16 }), "Laravel document API bootstrap required"),
        e("div", { className: "muted", style: { marginTop: 4, color: "inherit", fontWeight: 600 } }, "This screen does not display sample document counts or fake files. Configure document_management_options so records are loaded from managed_documents with authorization, storage policy, versioning and audit history.")),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Total Documents", value: "—", icon: "folder", tone: "accent", sub: "Load from Laravel" }),
        e(Stat, { label: "Current Versions", value: "—", icon: "ruler", tone: "blue", sub: "Load from managed_documents" }),
        e(Stat, { label: "Expiring", value: "—", icon: "clock", tone: "orange", sub: "Requires expiry query" }),
        e(Stat, { label: "Storage Used", value: "—", icon: "box", tone: "violet", sub: "Requires storage integration" })),
      e(Card, { title: "Managed Document Repository", sub: "No document API payload loaded" },
        e("div", { className: "empty" },
          e("div", { className: "empty-ic" }, e(Icon, { name: "folder", size: 24 })),
          e("h3", null, "Document repository unavailable"),
          e("div", null, "Uploads, downloads, approvals, folder counts and version history are disabled until Laravel document_management_options are available."))),
    );
  }

  function documentRecordUrl(template, document) {
    return String(template || "").replace("__DOCUMENT__", String(document?.id || document?.source_id || ""));
  }

  function documentStatusTone(status, doc) {
    if (doc?.is_expired) return "b-red";
    if (doc?.is_expiring_within_30_days) return "b-orange";
    if (status === "approved") return "b-green";
    if (status === "submitted") return "b-blue";
    return "b-slate";
  }

  function documentFolderForCategory(category) {
    const ownerType = category?.owner_type || "global";
    if (ownerType === "project") return "Project Documents";
    if (ownerType === "booking" || ownerType === "customer") return "Customer Docs";
    if (ownerType === "employee") return "Employee Docs";
    return "Global Documents";
  }

  function DocumentRegisterModal({ options, onClose, onSaved, toast }) {
    const categories = options?.categories || [];
    const firstCategory = categories.find(c => c.owner_type !== "global") || categories[0];
    const ownersFor = ownerType => options?.owners?.[ownerType] || [];
    const defaultOwnerType = firstCategory?.owner_type === "global" ? "project" : (firstCategory?.owner_type || "project");
    const [form, setForm] = React.useState({
      document_category_id: firstCategory?.id ? String(firstCategory.id) : "",
      owner_type: defaultOwnerType,
      owner_id: ownersFor(defaultOwnerType)[0]?.id ? String(ownersFor(defaultOwnerType)[0].id) : "",
      title: "",
      document_file: null,
      issue_date: new Date().toISOString().slice(0, 10),
      expires_on: "",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = (key, value) => setForm(current => Object.assign({}, current, { [key]: value }));
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const selectedCategory = categories.find(c => String(c.id) === String(form.document_category_id));
    const currentOwners = ownersFor(form.owner_type);
    const changeCategory = value => {
      const nextCategory = categories.find(c => String(c.id) === String(value));
      const nextOwnerType = nextCategory?.owner_type === "global" ? form.owner_type : (nextCategory?.owner_type || form.owner_type);
      const nextOwners = ownersFor(nextOwnerType);
      setForm(current => Object.assign({}, current, {
        document_category_id: value,
        owner_type: nextOwnerType,
        owner_id: nextOwners[0]?.id ? String(nextOwners[0].id) : "",
        expires_on: nextCategory?.expiry_required ? current.expires_on : "",
      }));
    };
    const changeOwnerType = value => {
      const nextOwners = ownersFor(value);
      setForm(current => Object.assign({}, current, { owner_type: value, owner_id: nextOwners[0]?.id ? String(nextOwners[0].id) : "" }));
    };
    const chooseFile = file => setForm(current => Object.assign({}, current, {
      document_file: file || null,
      title: current.title || (file?.name ? file.name.replace(/\.[^.]+$/, "") : ""),
    }));
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!form.owner_id) {
        setError("Select a document owner.");
        return;
      }
      if (selectedCategory?.expiry_required && !form.expires_on) {
        setError("This document category requires an expiry date.");
        return;
      }
      if (!form.document_file) {
        setError("Select the document file to upload.");
        return;
      }
      try {
        setBusy(true);
        const payload = new FormData();
        payload.append("document_category_id", String(Number(form.document_category_id)));
        payload.append("title", form.title.trim());
        payload.append("owner_type", form.owner_type);
        payload.append("owner_id", String(Number(form.owner_id)));
        payload.append("storage_disk", options.file_policy?.storage_disk || "local");
        payload.append("document_file", form.document_file);
        if (form.issue_date) payload.append("issue_date", form.issue_date);
        if (form.expires_on) payload.append("expires_on", form.expires_on);
        payload.append("metadata[source]", "document_management_screen");
        payload.append("metadata[upload_mode]", "browser_multipart_file");
        const body = await apiJson(options.store_url, {
          method: "POST",
          body: payload,
        });
        onSaved(body.data);
        toast("Document " + body.data.document_number + " registered in Laravel.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(860px,96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "Register Managed Document"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Uploads the selected file to Laravel managed storage, then records checksum, metadata, version and audit history.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Category", e("select", { style: field, value: form.document_category_id, onChange: ev => changeCategory(ev.target.value), disabled: busy, required: true }, categories.map(c => e("option", { key: c.id, value: c.id }, c.name + " · " + c.owner_type + (c.expiry_required ? " · expiry required" : ""))))),
          e("label", { style: label }, "Owner Type", e("select", { style: field, value: form.owner_type, onChange: ev => changeOwnerType(ev.target.value), disabled: busy || (selectedCategory && selectedCategory.owner_type !== "global"), required: true }, ["project", "booking", "customer", "employee"].map(type => e("option", { key: type, value: type }, titleCase(type))))),
          e("label", { style: label }, "Owner", form.owner_type === "employee" ? e(window.SearchablePeoplePicker, {
            items: currentOwners,
            selected: form.owner_id,
            mode: "single",
            placeholder: "Search employee name...",
            disabled: busy || !currentOwners.length,
            required: true,
            emptyText: "No matching employees",
            onChange: value => set("owner_id", value),
            getId: owner => owner.id,
            getLabel: owner => owner.name || owner.label || "Employee",
            getSubLabel: owner => [owner.employee_code || owner.code, owner.department || owner.email].filter(Boolean).join(" · "),
          }) : e("select", { style: field, value: form.owner_id, onChange: ev => set("owner_id", ev.target.value), disabled: busy || !currentOwners.length, required: true }, currentOwners.map(owner => e("option", { key: owner.id, value: owner.id }, owner.label)))),
          e("label", { style: label }, "Title", e("input", { style: field, value: form.title, onChange: ev => set("title", ev.target.value), maxLength: 255, disabled: busy, required: true })),
          e("label", { style: label }, "Document File", e("input", { style: field, type: "file", disabled: busy, required: true, accept: (options.file_policy?.allowed_extensions || []).map(x => "." + x).join(","), onChange: ev => chooseFile(ev.target.files?.[0] || null) })),
          e("label", { style: label }, "Issue Date", e("input", { style: field, type: "date", max: new Date().toISOString().slice(0, 10), value: form.issue_date, onChange: ev => set("issue_date", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Expiry Date", e("input", { style: field, type: "date", value: form.expires_on, onChange: ev => set("expires_on", ev.target.value), disabled: busy, required: Boolean(selectedCategory?.expiry_required) }))),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "Allowed extensions: ", (options.file_policy?.allowed_extensions || []).join(", "), ". Laravel stores the file inside ", options.file_policy?.storage_path_prefix || "documents/", " and computes the SHA-256 checksum server-side."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Server validation controls owner scope, category compatibility, expiry requirement, path safety and file policy."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "upload", type: "submit", disabled: busy || !categories.length || !currentOwners.length, children: busy ? "Registering…" : "Register Document" })))));
  }

  function DocumentApproveModal({ options, document, onClose, onSaved, toast }) {
    const [approvalNote, setApprovalNote] = React.useState("Approved after document verification.");
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!document?.id || !options?.approve_url_template) {
        setError("Select a governed document record before approval.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(documentRecordUrl(options.approve_url_template, document), {
          method: "PATCH",
          body: JSON.stringify({ approval_note: approvalNote.trim() || null }),
        });
        onSaved(body.data);
        toast(body.data.document_number + " approved.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(620px,96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "Approve Managed Document"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, document?.document_number || "Document", " · ", document?.title || "Untitled")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("div", { className: "grid g-2", style: { marginBottom: 12 } },
          e(Stat, { label: "Category", value: document?.category?.name || document?.owner_type || "Document", icon: "folder", tone: "blue", sub: "current version v" + (document?.version || 1) }),
          e(Stat, { label: "Expiry", value: document?.expires_on || "No expiry", icon: "clock", tone: document?.is_expired ? "red" : document?.is_expiring_within_30_days ? "orange" : "green", sub: titleCase(document?.status) })),
        e("label", { style: Object.assign({}, label, { marginBottom: 12 }) }, "Approval Note", e("textarea", { style: Object.assign({}, field, { minHeight: 104 }), value: approvalNote, onChange: ev => setApprovalNote(ev.target.value), disabled: busy, maxLength: 2000 })),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "Laravel validates document approval permission, owner scope, status, approval note length and audit history."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Source: ", options?.source || "Laravel"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "check", type: "submit", disabled: busy, children: busy ? "Approvingâ€¦" : "Approve Document" })))));
  }

  function Documents({ toast }) {
    const options = window.Builder360Server?.document_management_options || null;
    if (!options?.source) return e(LegacyDocuments, { toast });

    const [folder, setFolder] = React.useState("Project Documents");
    const [documents, setDocuments] = React.useState(options.documents || []);
    const [categories, setCategories] = React.useState(options.categories || []);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState("");
    const [registering, setRegistering] = React.useState(false);
    const [approvingDocument, setApprovingDocument] = React.useState(null);
    const [versionScope, setVersionScope] = React.useState("current");
    const [statusFilter, setStatusFilter] = React.useState("all");
    const documentVersionScopeOptions = [["current", "Current only"], ["all", "All versions"]];
    const documentStatusFilterOptions = [["all", "All status"], ["submitted", "Submitted"], ["approved", "Approved"], ["rejected", "Rejected"], ["archived", "Archived"]];
    const refreshDocuments = React.useCallback(() => {
      setLoading(true);
      setError("");
      const params = new URLSearchParams({ per_page: "30" });
      params.set("current_only", versionScope === "current" ? "1" : "0");
      if (statusFilter !== "all") params.set("status", statusFilter);
      Promise.all([
        apiJson(options.index_url + "?" + params.toString()),
        apiJson(options.categories_url + "?per_page=100"),
      ])
        .then(([docBody, categoryBody]) => {
          setDocuments(docBody.data || []);
          setCategories(categoryBody.data || []);
        })
        .catch(apiError => {
          setError(apiError.message);
          toast?.("Documents could not be loaded: " + apiError.message, "orange");
        })
        .finally(() => setLoading(false));
    }, [options.index_url, options.categories_url, versionScope, statusFilter]);
    React.useEffect(() => refreshDocuments(), [refreshDocuments]);

    const folders = ["Project Documents", "Customer Docs", "Employee Docs", "Global Documents"];
    const visibleDocs = documents.filter(doc => documentFolderForCategory(doc.category) === folder);
    const approveDocument = doc => {
      if (!options.can_approve || !options.approve_url_template || !doc?.id) {
        toast("Document approval is not available for this role or record.", "orange");
        return;
      }
      setApprovingDocument(doc);
    };
    const downloadDocument = doc => {
      const url = doc.download_url || documentRecordUrl(options.download_url_template, doc);
      if (!url) {
        toast("Download is not available for this document.", "orange");
        return;
      }
      window.open(url, "_blank", "noopener");
    };
    const onRegistered = doc => setDocuments(rows => [doc, ...rows.filter(item => item.id !== doc.id)]);
    const onApproved = doc => setDocuments(rows => rows.map(item => item.id === doc.id ? doc : item));
    const summary = options.summary || {};
    const submittedCount = summary.submitted ?? documents.filter(doc => doc.status === "submitted").length;

    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "Documents"], title: "Document Management", sub: "Centralized, version-controlled, permission-based repository with expiry reminders from Laravel.",
        actions: [e(Button, { key: 1, icon: "refresh", onClick: refreshDocuments, children: loading ? "Loading…" : "Refresh" }), e(Button, { key: 2, icon: "upload", variant: "primary", onClick: () => options.can_create ? setRegistering(true) : toast("Document registration is not available for this role.", "orange"), children: "Register Document" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Total Documents", value: String(summary.total_documents ?? documents.length), icon: "folder", tone: "accent", sub: "MySQL records" }),
        e(Stat, { label: "Current Versions", value: String(summary.current_documents ?? documents.filter(doc => doc.is_current).length), icon: "ruler", tone: "blue" }),
        e(Stat, { label: "Expiring", value: String(summary.expiring_soon ?? documents.filter(doc => doc.is_expiring_within_30_days).length), icon: "clock", tone: "orange", sub: "within 30 days" }),
        e(Stat, { label: "Pending Approval", value: String(submittedCount), icon: "check", tone: submittedCount ? "orange" : "green" })),
      error && e("div", { style: { marginBottom: 12, background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, fontSize: 13, fontWeight: 700 } }, error),
      e("div", { className: "grid", style: { gridTemplateColumns: "260px 1fr", alignItems: "start", gap: 16 } },
        e(Card, { title: "Folders", sub: "grouped by owner type" },
          e("div", { style: { padding: 8 } }, folders.map(f => {
            const count = documents.filter(doc => documentFolderForCategory(doc.category) === f).length;
            return e("div", { key: f, className: "nav-item" + (folder === f ? " active" : ""), style: { height: 40 }, onClick: () => setFolder(f) },
              e("span", { className: "ni-ic" }, e(Icon, { name: f.includes("Employee") ? "id" : f.includes("Customer") ? "users" : "folder", size: 17 })),
              e("span", { style: { flex: 1, fontWeight: 600 } }, f),
              e("span", { className: "faint mono", style: { fontSize: 11 } }, count));
          }))),
        e(Card, { title: folder, sub: loading ? "Loading managed documents…" : visibleDocs.length + " document(s) in selected filters", action: e("div", { className: "row gap-2", style: { flexWrap: "wrap" } },
          e("label", { className: "chip-select", style: { gap: 8 } },
            e("span", { style: { color: "var(--text-3)" } }, "Version"),
            e("select", { "aria-label": "Filter documents by version scope", value: versionScope, disabled: loading, onChange: ev => setVersionScope(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 118 } },
              documentVersionScopeOptions.map(([value, label]) => e("option", { key: value, value }, label)))),
          e("label", { className: "chip-select", style: { gap: 8 } },
            e("span", { style: { color: "var(--text-3)" } }, "Status"),
            e("select", { "aria-label": "Filter documents by status", value: statusFilter, disabled: loading, onChange: ev => setStatusFilter(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 112 } },
              documentStatusFilterOptions.map(([value, label]) => e("option", { key: value, value }, label)))),
          e(Button, { sm: true, icon: "upload", variant: "primary", onClick: () => options.can_create ? setRegistering(true) : toast("Document registration is not available for this role.", "orange"), children: "Register" })) },
          visibleDocs.length
            ? T([{ l: "Document" }, { l: "Category" }, { l: "Version" }, { l: "Expiry" }, { l: "Status" }, { l: "" }],
              visibleDocs.map(doc => [
                e("div", { className: "cell-user" }, e("div", { style: { width: 32, height: 32, borderRadius: 8, background: "var(--surface-3)", color: "var(--accent)", display: "grid", placeItems: "center", flex: "0 0 32px" } }, e(Icon, { name: "doc", size: 16 })), e("div", null, e("div", { className: "cell-strong" }, doc.title), e("div", { className: "cell-sub mono" }, doc.document_number))),
                e("span", { className: "tag" }, doc.category?.name || doc.owner_type),
                e("span", { className: doc.is_current ? "badge b-accent" : "badge b-slate" }, "v" + doc.version),
                e("span", { className: "faint" }, doc.expires_on || "No expiry"),
                e(Badge, { tone: documentStatusTone(doc.status, doc), dot: true }, doc.is_expired ? "Expired" : doc.is_expiring_within_30_days ? "Expiring" : titleCase(doc.status)),
                e("div", { className: "row gap-2" },
                  doc.status === "submitted" && options.can_approve && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--green)" }, onClick: () => approveDocument(doc) }, "Approve"),
                  e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--accent)" }, onClick: () => downloadDocument(doc) }, e(Icon, { name: "download", size: 14 })))]))
            : e("div", { className: "empty" }, e("div", { className: "empty-ic" }, e(Icon, { name: "folder", size: 24 })), e("h3", null, loading ? "Loading documents" : "No documents in this folder"), e("div", null, "Use Register Document to upload a governed document file.")))),
      registering && e(DocumentRegisterModal, { options: Object.assign({}, options, { categories }), onClose: () => setRegistering(false), onSaved: onRegistered, toast }),
      approvingDocument && e(DocumentApproveModal, { options, document: approvingDocument, onClose: () => setApprovingDocument(null), onSaved: onApproved, toast }),
    );
  }

  // ================= BOQ / MEASUREMENT BOOK =================
  function constructionRecordUrl(template, record, token) {
    return String(template || "").replace(token, String(record?.source_id || record?.id || ""));
  }

  function moneyInr(value) {
    return "₹" + Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
  }

  function numberCell(value, decimals = 2) {
    return Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: decimals });
  }

  function constructionStatusTone(status) {
    if (["approved", "paid", "closed", "active"].includes(status)) return "b-green";
    if (["submitted", "partially_paid"].includes(status)) return "b-orange";
    if (["rejected", "inactive"].includes(status)) return "b-red";
    return "b-slate";
  }

  function BoqActionModal({ options, action, record, boqItems, billableMeasurements, defaults, onClose, onSaved, toast }) {
    const today = defaults?.today || new Date().toISOString().slice(0, 10);
    const activeBoq = record || defaults?.activeBoq || boqItems?.[0] || null;
    const billMeasurement = record || billableMeasurements?.[0] || null;
    const defaultProject = defaults?.defaultProject || options?.projects?.[0] || null;
    const defaultContractor = defaults?.defaultContractor || options?.contractors?.[0] || null;
    const [form, setForm] = React.useState({
      project_id: String(activeBoq?.project?.id || defaultProject?.id || ""),
      vendor_id: String(activeBoq?.vendor?.id || defaultContractor?.id || ""),
      construction_milestone_id: "",
      boq_code: "BOQ-" + Date.now().toString().slice(-5),
      trade: "Civil",
      description: "Concrete work item",
      unit: "Nos",
      planned_quantity: "100",
      rate: "1000",
      boq_item_id: String(activeBoq?.id || ""),
      measurement_date: today,
      bill_reference: "MB UI " + Date.now().toString().slice(-4),
      measured_quantity: String(Math.max(Number(activeBoq?.balance_quantity || 1), 1)),
      certified_quantity: String(Math.max(Number(activeBoq?.balance_quantity || 1), 1)),
      remarks: "Submitted from Laravel-backed BOQ screen.",
      note: action === "bill_approve" ? "Approved after measurement and deduction verification." : "Certified after BOQ verification.",
      reason: "Rejected after BOQ verification.",
      contractor_measurement_id: String(billMeasurement?.id || ""),
      bill_date: today,
      retention_percent: "5",
      tax_amount: "0",
      paid_amount: String(record?.balance_amount || record?.payable_amount || 0),
      paid_on: today,
      payment_reference: "PAY-" + Date.now().toString().slice(-6),
      payment_note: "Payment recorded from BOQ workspace.",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const set = (key, value) => setForm(current => Object.assign({}, current, { [key]: value }));
    const numberValue = key => Number(form[key]);
    const title = {
      boq_create: "Add BOQ Item",
      measurement_create: "Create Measurement Entry",
      measurement_approve: "Approve Measurement",
      measurement_reject: "Reject Measurement",
      bill_create: "Create RA Bill",
      bill_approve: "Approve Contractor Bill",
      bill_pay: "Record Contractor Payment",
    }[action] || "BOQ Action";
    const submitLabel = {
      boq_create: "Create BOQ Item",
      measurement_create: "Submit Measurement",
      measurement_approve: "Approve Measurement",
      measurement_reject: "Reject Measurement",
      bill_create: "Create RA Bill",
      bill_approve: "Approve Bill",
      bill_pay: "Record Payment",
    }[action] || "Save";
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      let url = "";
      let payload = {};
      if (action === "boq_create") {
        if (!form.project_id || !form.boq_code.trim() || !form.description.trim()) {
          setError("Project, BOQ code and description are required.");
          return;
        }
        if (!numberValue("planned_quantity") || !numberValue("rate")) {
          setError("Planned quantity and rate must be greater than zero.");
          return;
        }
        url = options.boq_store_url;
        payload = {
          project_id: Number(form.project_id),
          construction_milestone_id: form.construction_milestone_id ? Number(form.construction_milestone_id) : null,
          vendor_id: form.vendor_id ? Number(form.vendor_id) : null,
          boq_code: form.boq_code.trim().toUpperCase(),
          trade: form.trade.trim(),
          description: form.description.trim(),
          unit: form.unit.trim(),
          planned_quantity: numberValue("planned_quantity"),
          rate: numberValue("rate"),
          status: "active",
          metadata: { source: "boq_screen_modal_create" },
        };
      } else if (action === "measurement_create") {
        const boq = boqItems.find(item => String(item.id) === String(form.boq_item_id)) || activeBoq;
        if (!boq || !form.vendor_id || !form.project_id) {
          setError("Project, contractor and BOQ item are required.");
          return;
        }
        if (!numberValue("measured_quantity") || Number.isNaN(numberValue("certified_quantity"))) {
          setError("Measured and certified quantities are required.");
          return;
        }
        if (numberValue("certified_quantity") > numberValue("measured_quantity")) {
          setError("Certified quantity cannot exceed measured quantity.");
          return;
        }
        url = options.measurement_store_url;
        payload = {
          project_id: Number(form.project_id),
          vendor_id: Number(form.vendor_id),
          measurement_date: form.measurement_date,
          bill_reference: form.bill_reference.trim() || null,
          lines: [{ boq_item_id: Number(boq.id), measured_quantity: numberValue("measured_quantity"), certified_quantity: numberValue("certified_quantity"), remarks: form.remarks.trim() || null }],
          remarks: form.remarks.trim() || null,
        };
      } else if (action === "measurement_approve") {
        url = constructionRecordUrl(options.measurement_approve_url_template, record, "__MEASUREMENT__");
        payload = { note: form.note.trim() || null };
      } else if (action === "measurement_reject") {
        if (!form.reason.trim()) {
          setError("Rejection reason is required.");
          return;
        }
        url = constructionRecordUrl(options.measurement_reject_url_template, record, "__MEASUREMENT__");
        payload = { reason: form.reason.trim() };
      } else if (action === "bill_create") {
        if (!form.contractor_measurement_id) {
          setError("Select an approved unbilled measurement.");
          return;
        }
        if (Number.isNaN(numberValue("retention_percent"))) {
          setError("Retention percent is required.");
          return;
        }
        url = options.bill_store_url;
        payload = {
          contractor_measurement_id: Number(form.contractor_measurement_id),
          bill_date: form.bill_date,
          retention_percent: numberValue("retention_percent"),
          tax_amount: Number.isNaN(numberValue("tax_amount")) ? 0 : numberValue("tax_amount"),
          deductions: [],
          remarks: form.remarks.trim() || "RA bill prepared from BOQ workspace.",
        };
      } else if (action === "bill_approve") {
        url = constructionRecordUrl(options.bill_approve_url_template, record, "__BILL__");
        payload = { note: form.note.trim() || null };
      } else if (action === "bill_pay") {
        const paidAmount = numberValue("paid_amount");
        if (!paidAmount || paidAmount <= 0) {
          setError("Payment amount must be greater than zero.");
          return;
        }
        if (paidAmount > Number(record?.balance_amount || 0)) {
          setError("Payment amount cannot exceed bill balance.");
          return;
        }
        if (!form.payment_reference.trim()) {
          setError("Payment reference is required.");
          return;
        }
        url = constructionRecordUrl(options.bill_mark_paid_url_template, record, "__BILL__");
        payload = {
          paid_amount: paidAmount,
          paid_on: form.paid_on,
          payment_reference: form.payment_reference.trim(),
          note: form.payment_note.trim() || null,
        };
      }
      if (!url) {
        setError("This BOQ action is missing its Laravel route.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(url, { method: action.includes("create") ? "POST" : "PATCH", body: JSON.stringify(payload) });
        onSaved(action, body.data, { contractor_measurement_id: Number(form.contractor_measurement_id) || null });
        toast((body.data.boq_code || body.data.measurement_number || body.data.bill_number || "BOQ record") + " saved.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(760px,96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, title), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, record?.measurement_number || record?.bill_number || record?.boq_code || "Laravel BOQ workflow")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        action === "boq_create" && e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Project", e("select", { style: field, value: form.project_id, onChange: ev => set("project_id", ev.target.value), disabled: busy, required: true }, (options.projects || []).map(p => e("option", { key: p.id, value: p.id }, p.label || p.code)))),
          e("label", { style: label }, "Contractor", e("select", { style: field, value: form.vendor_id, onChange: ev => set("vendor_id", ev.target.value), disabled: busy }, e("option", { value: "" }, "No contractor"), (options.contractors || []).map(v => e("option", { key: v.id, value: v.id }, v.label || v.name)))),
          e("label", { style: label }, "BOQ Code", e("input", { style: field, value: form.boq_code, onChange: ev => set("boq_code", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Trade", e("input", { style: field, value: form.trade, onChange: ev => set("trade", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Unit", e("input", { style: field, value: form.unit, onChange: ev => set("unit", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Planned Quantity", e("input", { style: field, type: "number", min: "0.001", step: "0.001", value: form.planned_quantity, onChange: ev => set("planned_quantity", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Rate", e("input", { style: field, type: "number", min: "0.01", step: "0.01", value: form.rate, onChange: ev => set("rate", ev.target.value), disabled: busy, required: true })),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Description", e("textarea", { style: Object.assign({}, field, { minHeight: 80 }), value: form.description, onChange: ev => set("description", ev.target.value), disabled: busy, required: true }))),
        action === "measurement_create" && e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "BOQ Item", e("select", { style: field, value: form.boq_item_id, onChange: ev => { const boq = boqItems.find(item => String(item.id) === ev.target.value); setForm(current => Object.assign({}, current, { boq_item_id: ev.target.value, project_id: String(boq?.project?.id || current.project_id), vendor_id: String(boq?.vendor?.id || current.vendor_id), measured_quantity: String(Math.max(Number(boq?.balance_quantity || 1), 1)), certified_quantity: String(Math.max(Number(boq?.balance_quantity || 1), 1)) })); }, disabled: busy, required: true }, (boqItems || []).filter(item => item.status === "active").map(item => e("option", { key: item.id, value: item.id }, item.boq_code + " · " + item.description)))),
          e("label", { style: label }, "Contractor", e("select", { style: field, value: form.vendor_id, onChange: ev => set("vendor_id", ev.target.value), disabled: busy, required: true }, (options.contractors || []).map(v => e("option", { key: v.id, value: v.id }, v.label || v.name)))),
          e("label", { style: label }, "Measurement Date", e("input", { style: field, type: "date", value: form.measurement_date, onChange: ev => set("measurement_date", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Bill Reference", e("input", { style: field, value: form.bill_reference, onChange: ev => set("bill_reference", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Measured Quantity", e("input", { style: field, type: "number", min: "0.001", step: "0.001", value: form.measured_quantity, onChange: ev => set("measured_quantity", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Certified Quantity", e("input", { style: field, type: "number", min: "0", step: "0.001", value: form.certified_quantity, onChange: ev => set("certified_quantity", ev.target.value), disabled: busy, required: true })),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Remarks", e("textarea", { style: Object.assign({}, field, { minHeight: 80 }), value: form.remarks, onChange: ev => set("remarks", ev.target.value), disabled: busy }))),
        action === "measurement_approve" && e("label", { style: Object.assign({}, label, { marginBottom: 12 }) }, "Approval Note", e("textarea", { style: Object.assign({}, field, { minHeight: 96 }), value: form.note, onChange: ev => set("note", ev.target.value), disabled: busy, maxLength: 1000 })),
        action === "measurement_reject" && e("label", { style: Object.assign({}, label, { marginBottom: 12 }) }, "Rejection Reason", e("textarea", { style: Object.assign({}, field, { minHeight: 96 }), value: form.reason, onChange: ev => set("reason", ev.target.value), disabled: busy, maxLength: 2000, required: true })),
        action === "bill_create" && e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Approved Measurement", e("select", { style: field, value: form.contractor_measurement_id, onChange: ev => set("contractor_measurement_id", ev.target.value), disabled: busy, required: true }, (billableMeasurements || []).map(m => e("option", { key: m.id, value: m.id }, m.measurement_number + " · " + moneyInr(m.certified_total))))),
          e("label", { style: label }, "Bill Date", e("input", { style: field, type: "date", value: form.bill_date, onChange: ev => set("bill_date", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Retention %", e("input", { style: field, type: "number", min: "0", max: "100", step: "0.01", value: form.retention_percent, onChange: ev => set("retention_percent", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Tax Amount", e("input", { style: field, type: "number", min: "0", step: "0.01", value: form.tax_amount, onChange: ev => set("tax_amount", ev.target.value), disabled: busy })),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Remarks", e("textarea", { style: Object.assign({}, field, { minHeight: 80 }), value: form.remarks, onChange: ev => set("remarks", ev.target.value), disabled: busy }))),
        action === "bill_approve" && e("label", { style: Object.assign({}, label, { marginBottom: 12 }) }, "Approval Note", e("textarea", { style: Object.assign({}, field, { minHeight: 96 }), value: form.note, onChange: ev => set("note", ev.target.value), disabled: busy, maxLength: 1000 })),
        action === "bill_pay" && e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Payment Amount", e("input", { style: field, type: "number", min: "0.01", step: "0.01", max: record?.balance_amount || undefined, value: form.paid_amount, onChange: ev => set("paid_amount", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Paid On", e("input", { style: field, type: "date", value: form.paid_on, onChange: ev => set("paid_on", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Payment Reference", e("input", { style: field, value: form.payment_reference, onChange: ev => set("payment_reference", ev.target.value), disabled: busy, required: true })),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Payment Note", e("textarea", { style: Object.assign({}, field, { minHeight: 80 }), value: form.payment_note, onChange: ev => set("payment_note", ev.target.value), disabled: busy, maxLength: 1000 }))),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "Laravel validates company/project scope, BOQ quantity limits, approval authority, billing limits, duplicate bills, payment balance and audit history."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Source: ", options?.source || "Laravel"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "check", type: "submit", disabled: busy, children: busy ? "Saving…" : submitLabel })))));
  }

  function BOQ({ toast }) {
    const options = window.Builder360Server?.construction_boq_options || null;
    const [boqItems, setBoqItems] = React.useState(options?.boq_items || []);
    const [measurements, setMeasurements] = React.useState(options?.measurements || []);
    const [bills, setBills] = React.useState(options?.bills || []);
    const [billableMeasurements, setBillableMeasurements] = React.useState(options?.billable_measurements || []);
    const [loading, setLoading] = React.useState(Boolean(options?.boq_index_url));
    const [error, setError] = React.useState("");
    const [boqAction, setBoqAction] = React.useState(null);
    const initialProjectFilter = String(window.Builder360Server?.active_project_context?.project_id || "all");
    const [projectFilter, setProjectFilter] = React.useState(initialProjectFilter);
    const [boqStatusFilter, setBoqStatusFilter] = React.useState("all");

    if (!options?.source) {
      return e("div", { className: "page page-wide" },
        e(PageHead, { crumbs: ["Construction", "BOQ"], title: "BOQ / Measurement Book", sub: "This role does not have access to the Laravel BOQ workspace." }),
        e("div", { className: "empty" }, e("div", { className: "empty-ic" }, e(Icon, { name: "ruler", size: 24 })), e("h3", null, "BOQ access unavailable"), e("div", null, "Ask an administrator for construction.view, construction.manage or construction.approve permission.")));
    }

    const refresh = React.useCallback(() => {
      setLoading(true);
      setError("");
      const boqParams = new URLSearchParams({ per_page: "40" });
      const measurementParams = new URLSearchParams({ per_page: "20" });
      const billParams = new URLSearchParams({ per_page: "20" });
      if (projectFilter !== "all") {
        boqParams.set("project_id", projectFilter);
        measurementParams.set("project_id", projectFilter);
        billParams.set("project_id", projectFilter);
      }
      if (boqStatusFilter !== "all") boqParams.set("status", boqStatusFilter);
      Promise.all([
        apiJson(options.boq_index_url + "?" + boqParams.toString()),
        apiJson(options.measurement_index_url + "?" + measurementParams.toString()),
        apiJson(options.bill_index_url + "?" + billParams.toString()),
      ])
        .then(([boqBody, measurementBody, billBody]) => {
          const nextBoq = boqBody.data || [];
          const nextMeasurements = measurementBody.data || [];
          const nextBills = billBody.data || [];
          const billedMeasurementIds = new Set(nextBills.map(bill => bill.measurement?.id).filter(Boolean));
          setBoqItems(nextBoq);
          setMeasurements(nextMeasurements);
          setBills(nextBills);
          setBillableMeasurements(nextMeasurements.filter(measurement => measurement.status === "approved" && !billedMeasurementIds.has(measurement.id)));
        })
        .catch(apiError => {
          setError(apiError.message);
          toast?.("BOQ data could not be loaded: " + apiError.message, "orange");
        })
        .finally(() => setLoading(false));
    }, [options.boq_index_url, options.measurement_index_url, options.bill_index_url, projectFilter, boqStatusFilter]);

    React.useEffect(() => refresh(), [refresh]);

    const defaultProject = options.projects?.[0];
    const selectedProject = options.projects?.find(project => String(project.id) === String(projectFilter)) || defaultProject;
    const defaultContractor = options.contractors?.[0];
    const activeBoq = boqItems.find(item => item.status === "active") || boqItems[0];
    const today = new Date().toISOString().slice(0, 10);

    const createBoqItem = () => {
      if (!options.can_create_boq || !selectedProject) {
        toast("BOQ item creation is not available for this role or scope.", "orange");
        return;
      }
      setBoqAction({ action: "boq_create", record: null });
    };

    const createMeasurement = () => {
      if (!options.can_create_measurement || !activeBoq) {
        toast("Measurement entry is not available until an active BOQ item exists.", "orange");
        return;
      }
      const contractorId = activeBoq.vendor?.id || defaultContractor?.id;
      if (!contractorId) {
        toast("A contractor is required before measurement entry.", "orange");
        return;
      }
      setBoqAction({ action: "measurement_create", record: activeBoq });
    };

    const approveMeasurement = measurement => {
      setBoqAction({ action: "measurement_approve", record: measurement });
    };

    const rejectMeasurement = measurement => {
      setBoqAction({ action: "measurement_reject", record: measurement });
    };

    const createBill = () => {
      if (!options.can_create_bill || !billableMeasurements.length) {
        toast("No approved unbilled measurement is available for RA bill creation.", "orange");
        return;
      }
      setBoqAction({ action: "bill_create", record: null });
    };

    const approveBill = bill => {
      setBoqAction({ action: "bill_approve", record: bill });
    };

    const markBillPaid = bill => {
      setBoqAction({ action: "bill_pay", record: bill });
    };

    const onBoqActionSaved = (action, data, meta = {}) => {
      if (action === "boq_create") setBoqItems(rows => [data, ...rows.filter(row => row.id !== data.id)]);
      if (action === "measurement_create") setMeasurements(rows => [data, ...rows.filter(row => row.id !== data.id)]);
      if (action === "measurement_approve") {
        setMeasurements(rows => rows.map(row => row.id === data.id ? data : row));
        setBillableMeasurements(rows => [data, ...rows.filter(row => row.id !== data.id)]);
        refresh();
      }
      if (action === "measurement_reject") setMeasurements(rows => rows.map(row => row.id === data.id ? data : row));
      if (action === "bill_create") {
        setBills(rows => [data, ...rows.filter(row => row.id !== data.id)]);
        setBillableMeasurements(rows => rows.filter(row => row.id !== meta.contractor_measurement_id));
      }
      if (["bill_approve", "bill_pay"].includes(action)) setBills(rows => rows.map(row => row.id === data.id ? data : row));
    };

    const summary = options.summary || {};
    const certifiedPercent = summary.budget_amount > 0 ? Math.round(Number(summary.certified_amount || 0) / Number(summary.budget_amount) * 100) : 0;
    const pendingVerification = measurements.filter(row => row.status === "submitted").length;
    const overrunAlerts = boqItems.filter(row => Number(row.certified_quantity || 0) > Number(row.planned_quantity || 0)).length;

    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Construction", "BOQ"], title: "BOQ / Measurement Book", sub: "Laravel-backed BOQ, measurement certification, RA bill and contractor payment workflow.",
        actions: [
          e("label", { key: 1, className: "chip-select", style: { gap: 8 } },
            e("span", { style: { color: "var(--text-3)" } }, "Project"),
            e("select", { "aria-label": "Filter BOQ by project", value: projectFilter, disabled: loading, onChange: ev => setProjectFilter(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 170 } },
              [e("option", { key: "all", value: "all" }, "All scoped projects")].concat((options.projects || []).map(project => e("option", { key: project.id, value: String(project.id) }, project.label || project.name))))),
          e("label", { key: "status", className: "chip-select", style: { gap: 8 } },
            e("span", { style: { color: "var(--text-3)" } }, "BOQ Status"),
            e("select", { "aria-label": "Filter BOQ items by status", value: boqStatusFilter, disabled: loading, onChange: ev => setBoqStatusFilter(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 110 } },
              [e("option", { key: "all", value: "all" }, "All")].concat((options.statuses?.boq || ["active", "inactive", "closed"]).map(status => e("option", { key: status, value: status }, titleCase(status)))))),
          e(Button, { key: 2, icon: "refresh", onClick: refresh, children: loading ? "Loading..." : "Refresh" }),
          e(Button, { key: 3, icon: "plus", onClick: createBoqItem, children: "Add BOQ Item" }),
          e(Button, { key: 4, icon: "plus", variant: "primary", onClick: createMeasurement, children: "Measurement Entry" }),
          e(Button, { key: 5, icon: "doc", onClick: createBill, children: "Create RA Bill" }),
        ] }),
      error && e("div", { style: { marginBottom: 12, background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, fontSize: 13, fontWeight: 700 } }, error),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "BOQ Items", value: String(summary.boq_items ?? boqItems.length), icon: "ruler", tone: "accent", sub: "MySQL records" }),
        e(Stat, { label: "Certified Value", value: String(certifiedPercent), unit: "%", icon: "check", tone: "green", sub: moneyInr(summary.certified_amount) + " / " + moneyInr(summary.budget_amount) }),
        e(Stat, { label: "Overrun Alerts", value: String(overrunAlerts), icon: "alert", tone: overrunAlerts ? "red" : "green" }),
        e(Stat, { label: "Pending Verification", value: String(summary.pending_measurements ?? pendingVerification), icon: "clock", tone: pendingVerification ? "orange" : "green" })),
      e("div", { className: "grid", style: { gridTemplateColumns: "minmax(0,1.3fr) minmax(360px,.7fr)", gap: 16, alignItems: "start" } },
        e(Card, { title: "BOQ Register", sub: "estimated vs measured vs certified quantities" },
          boqItems.length
            ? T([{ l: "Item" }, { l: "Project" }, { l: "Unit" }, { l: "Est. Qty", r: true }, { l: "Measured", r: true }, { l: "Certified", r: true }, { l: "Rate", r: true }, { l: "Status" }],
              boqItems.map(row => [
                e("div", null, e("div", { className: "cell-strong" }, row.description), e("div", { className: "cell-sub mono" }, row.boq_code + " · " + row.trade)),
                row.project?.code || "Project",
                row.unit,
                e("span", { className: "mono" }, numberCell(row.planned_quantity, 3)),
                e("span", { className: "mono" }, numberCell(row.measured_quantity, 3)),
                e("span", { className: "mono cell-strong" }, numberCell(row.certified_quantity, 3)),
                e("span", { className: "mono" }, moneyInr(row.rate)),
                e(Badge, { tone: constructionStatusTone(row.status), dot: true }, titleCase(row.status))]))
            : e("div", { className: "empty" }, e("div", { className: "empty-ic" }, e(Icon, { name: "ruler", size: 24 })), e("h3", null, loading ? "Loading BOQ" : "No BOQ items found"), e("div", null, "Use Add BOQ Item to create a validated construction quantity record."))),
        e("div", { style: { display: "grid", gap: 16 } },
          e(Card, { title: "Measurements", sub: "submitted, approved and rejected MB records" },
            measurements.length
              ? T([{ l: "Measurement" }, { l: "Contractor" }, { l: "Certified", r: true }, { l: "Status" }, { l: "" }],
                measurements.map(row => [
                  e("div", null, e("div", { className: "cell-strong mono" }, row.measurement_number), e("div", { className: "cell-sub" }, row.measurement_date || "No date")),
                  row.vendor?.name || "Contractor",
                  e("span", { className: "mono" }, moneyInr(row.certified_total)),
                  e(Badge, { tone: constructionStatusTone(row.status), dot: true }, titleCase(row.status)),
                  e("div", { className: "row gap-2" },
                    row.status === "submitted" && options.can_approve_measurement && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--green)" }, onClick: () => approveMeasurement(row) }, "Approve"),
                    row.status === "submitted" && options.can_approve_measurement && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--red)" }, onClick: () => rejectMeasurement(row) }, "Reject"))]))
              : e("div", { className: "empty" }, e("div", null, loading ? "Loading measurements..." : "No measurements found"))),
          e(Card, { title: "Contractor RA Bills", sub: "retention, deductions, payable and payment status" },
            bills.length
              ? T([{ l: "Bill" }, { l: "Gross", r: true }, { l: "Balance", r: true }, { l: "Status" }, { l: "" }],
                bills.map(row => [
                  e("div", null, e("div", { className: "cell-strong mono" }, row.bill_number), e("div", { className: "cell-sub" }, row.vendor?.name || row.bill_date || "Contractor")),
                  e("span", { className: "mono" }, moneyInr(row.gross_amount)),
                  e("span", { className: "mono cell-strong" }, moneyInr(row.balance_amount)),
                  e(Badge, { tone: constructionStatusTone(row.status), dot: true }, titleCase(row.status)),
                  e("div", { className: "row gap-2" },
                    row.status === "submitted" && options.can_approve_bill && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--green)" }, onClick: () => approveBill(row) }, "Approve"),
                    ["approved", "partially_paid"].includes(row.status) && options.can_mark_bill_paid && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--accent)" }, onClick: () => markBillPaid(row) }, "Pay"))]))
              : e("div", { className: "empty" }, e("div", null, loading ? "Loading bills..." : "No contractor bills found"))))),
      boqAction && e(BoqActionModal, { options, action: boqAction.action, record: boqAction.record, boqItems, billableMeasurements, defaults: { defaultProject: selectedProject, defaultContractor, activeBoq, today }, onClose: () => setBoqAction(null), onSaved: onBoqActionSaved, toast }),
    );
  }

  Object.assign(window, { Complaints, Legal, Documents, BOQ });
})();
