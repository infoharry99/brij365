const React = window.React;

/* Builder360 — Maintenance & Society / Association Handover (post-possession) */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, HBars, PageHead, ChipSelect, Seg } = window;
  const e = React.createElement;

  function T(head, rows) {
    const th = head.map((h, i) => e("th", { key: i, style: (h.r ? { textAlign: "right" } : {}) }, h.l != null ? h.l : h));
    const body = rows.map((r, i) => e("tr", { key: i }, r.map((c, j) => e("td", { key: j, className: (head[j] && head[j].r ? "num" : "") }, c))));
    return e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" }, e("thead", null, e("tr", null, th)), e("tbody", null, body)));
  }
  const apiJson = async (url, options = {}) => {
    const headers = Object.assign({ Accept: "application/json" }, options.headers || {});
    if (options.body && !headers["Content-Type"]) headers["Content-Type"] = "application/json";
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
  const titleCase = value => String(value || "").replace(/_/g, " ").split(" ").filter(Boolean).map(part => part.charAt(0).toUpperCase() + part.slice(1)).join(" ");
  const moneyInr = value => "₹" + Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
  const maintenanceUrl = (template, record, token) => String(template || "").replace(token, String(record?.id || record?.source_id || ""));
  const statusTone = status => {
    if (["formed", "handed_over", "complete", "paid"].includes(status)) return "b-green";
    if (["in_progress", "application_filed", "due"].includes(status)) return "b-blue";
    if (["pending", "pending_snags", "overdue", "blocked"].includes(status)) return status === "overdue" ? "b-red" : "b-orange";
    return "b-slate";
  };
  const todayIso = () => new Date().toISOString().slice(0, 10);
  const quarterDates = () => {
    const today = new Date();
    const qStart = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 1);
    const qEnd = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3 + 3, 0);
    const dueOn = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 15);
    return { start: qStart.toISOString().slice(0, 10), end: qEnd.toISOString().slice(0, 10), dueOn: dueOn.toISOString().slice(0, 10) };
  };

  function MaintenanceActionModal({ options, action, record, defaults, onClose, onSaved, toast }) {
    const projects = options?.projects || [];
    const bookings = options?.bookings || [];
    const dates = quarterDates();
    const defaultProject = defaults?.defaultProject || projects[0];
    const defaultBooking = defaults?.defaultBooking || bookings[0];
    const isSociety = action === "society_create";
    const isDue = action === "due_create";
    const isPayment = action === "due_pay";
    const [form, setForm] = React.useState({
      project_id: defaultProject?.id ? String(defaultProject.id) : "",
      society_name: defaultProject?.name ? defaultProject.name + " Co-operative Housing Society" : "",
      association_type: "cooperative_society",
      total_units: "100",
      occupied_units: "60",
      status: "application_filed",
      progress_percent: "35",
      current_stage: "Application filed",
      next_step: "Committee and AGM confirmation",
      booking_id: defaultBooking?.id ? String(defaultBooking.id) : "",
      period_start_on: dates.start,
      period_end_on: dates.end,
      due_on: dates.dueOn,
      amount: "16200",
      paid_amount: String(record?.balance_amount || record?.amount || 0),
      paid_at: todayIso(),
      payment_reference: "MNT-" + Date.now().toString().slice(-6),
      note: "Payment recorded from Maintenance & Society workspace.",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = (key, value) => setForm(current => Object.assign({}, current, { [key]: value }));
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const title = isSociety ? "New Society / Association" : isDue ? "Raise Maintenance Demand" : "Record Maintenance Payment";
    const submitLabel = isSociety ? "Create Society" : isDue ? "Raise Demand" : "Mark Paid";

    const validate = () => {
      if (isSociety) {
        if (!form.project_id) return "Select a project.";
        if (!form.society_name.trim()) return "Society / association name is required.";
        if (Number(form.total_units) < 1) return "Total units must be at least 1.";
        if (Number(form.occupied_units || 0) > Number(form.total_units || 0)) return "Occupied units cannot exceed total units.";
        if (Number(form.progress_percent) < 0 || Number(form.progress_percent) > 100) return "Progress must be between 0 and 100.";
      }
      if (isDue) {
        if (!form.booking_id) return "Select an active booking.";
        if (!form.period_start_on || !form.period_end_on || !form.due_on) return "Period start, period end and due date are required.";
        if (new Date(form.period_end_on) < new Date(form.period_start_on)) return "Period end cannot be before period start.";
        if (new Date(form.due_on) < new Date(form.period_start_on)) return "Due date cannot be before period start.";
        if (Number(form.amount) < 1) return "Demand amount must be at least 1.";
      }
      if (isPayment) {
        const balance = Number(record?.balance_amount || record?.amount || 0);
        if (Number(form.paid_amount) <= 0) return "Payment amount must be greater than zero.";
        if (Number(form.paid_amount) > balance) return "Payment amount cannot exceed outstanding balance.";
        if (!form.payment_reference.trim()) return "Payment reference is required.";
      }
      return "";
    };

    const submit = async ev => {
      ev.preventDefault();
      setError("");
      const validationError = validate();
      if (validationError) {
        setError(validationError);
        return;
      }
      let url = "";
      let method = "POST";
      let payload = {};
      if (isSociety) {
        url = options.societies_store_url;
        payload = {
          project_id: Number(form.project_id),
          society_name: form.society_name.trim(),
          association_type: form.association_type,
          total_units: Number(form.total_units),
          occupied_units: Number(form.occupied_units || 0),
          status: form.status,
          progress_percent: Number(form.progress_percent || 0),
          current_stage: form.current_stage.trim() || null,
          next_step: form.next_step.trim() || null,
          metadata: { source: "maintenance_screen_modal_create" },
        };
      } else if (isDue) {
        url = options.dues_store_url;
        payload = {
          booking_id: Number(form.booking_id),
          period_start_on: form.period_start_on,
          period_end_on: form.period_end_on,
          due_on: form.due_on,
          amount: Number(form.amount),
          metadata: { source: "maintenance_screen_modal_create" },
        };
      } else {
        url = maintenanceUrl(options.due_mark_paid_url_template, record, "__DUE__");
        method = "PATCH";
        payload = {
          paid_amount: Number(form.paid_amount),
          payment_reference: form.payment_reference.trim(),
          paid_at: form.paid_at || null,
          note: form.note.trim() || null,
        };
      }
      try {
        setBusy(true);
        const body = await apiJson(url, { method, body: JSON.stringify(payload) });
        onSaved(action, body.data);
        toast?.((body.data.formation_number || body.data.due_number || "Maintenance record") + " saved.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };

    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(780px,96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, title), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Saved through Laravel Maintenance/Society validation, permissions and audit workflow.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        isSociety && e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Project", e("select", { style: field, value: form.project_id, onChange: ev => set("project_id", ev.target.value), disabled: busy, required: true }, projects.map(project => e("option", { key: project.id, value: project.id }, project.label || project.name)))),
          e("label", { style: label }, "Association Type", e("select", { style: field, value: form.association_type, onChange: ev => set("association_type", ev.target.value), disabled: busy }, [
            ["cooperative_society", "Co-operative Society"], ["apartment_association", "Apartment Association"], ["commercial_association", "Commercial Association"],
          ].map(row => e("option", { key: row[0], value: row[0] }, row[1])))),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Society / Association Name", e("input", { style: field, value: form.society_name, onChange: ev => set("society_name", ev.target.value), maxLength: 255, disabled: busy, required: true })),
          e("label", { style: label }, "Total Units", e("input", { style: field, type: "number", min: "1", max: "10000", value: form.total_units, onChange: ev => set("total_units", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Occupied Units", e("input", { style: field, type: "number", min: "0", value: form.occupied_units, onChange: ev => set("occupied_units", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Status", e("select", { style: field, value: form.status, onChange: ev => set("status", ev.target.value), disabled: busy }, (options.statuses?.society || []).map(status => e("option", { key: status, value: status }, titleCase(status))))),
          e("label", { style: label }, "Progress %", e("input", { style: field, type: "number", min: "0", max: "100", value: form.progress_percent, onChange: ev => set("progress_percent", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Current Stage", e("input", { style: field, value: form.current_stage, onChange: ev => set("current_stage", ev.target.value), maxLength: 120, disabled: busy })),
          e("label", { style: label }, "Next Step", e("input", { style: field, value: form.next_step, onChange: ev => set("next_step", ev.target.value), maxLength: 255, disabled: busy }))),
        isDue && e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Active Booking", e("select", { style: field, value: form.booking_id, onChange: ev => set("booking_id", ev.target.value), disabled: busy, required: true }, bookings.map(booking => e("option", { key: booking.id, value: booking.id }, (booking.booking_code || ("Booking #" + booking.id)) + " · " + (booking.unit?.unit_code || booking.unit?.unit_number || "Unit") + " · " + (booking.customer?.name || "Customer"))))),
          e("label", { style: label }, "Period Start", e("input", { style: field, type: "date", value: form.period_start_on, onChange: ev => set("period_start_on", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Period End", e("input", { style: field, type: "date", value: form.period_end_on, onChange: ev => set("period_end_on", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Due Date", e("input", { style: field, type: "date", value: form.due_on, onChange: ev => set("due_on", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Demand Amount", e("input", { style: field, type: "number", min: "1", step: "0.01", value: form.amount, onChange: ev => set("amount", ev.target.value), disabled: busy, required: true }))),
        isPayment && e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Outstanding Balance", e("input", { style: field, value: moneyInr(record?.balance_amount || record?.amount || 0), disabled: true })),
          e("label", { style: label }, "Payment Amount", e("input", { style: field, type: "number", min: "0.01", step: "0.01", max: record?.balance_amount || record?.amount || undefined, value: form.paid_amount, onChange: ev => set("paid_amount", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Paid At", e("input", { style: field, type: "date", value: form.paid_at, onChange: ev => set("paid_at", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Payment Reference", e("input", { style: field, value: form.payment_reference, onChange: ev => set("payment_reference", ev.target.value), maxLength: 120, disabled: busy, required: true })),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Note", e("textarea", { style: Object.assign({}, field, { minHeight: 80 }), value: form.note, onChange: ev => set("note", ev.target.value), maxLength: 1000, disabled: busy }))),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } }, "Laravel validates company scope, active booking, duplicate period, amount limits, payment balance, permissions and audit history."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Source: ", options?.source || "Laravel"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "check", type: "submit", disabled: busy, children: busy ? "Saving…" : submitLabel })))));
  }

  function MaintenanceApiRequired() {
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "Maintenance & Society"], title: "Maintenance & Society Handover",
        sub: "Maintenance & Society API required; no local maintenance rows are fabricated.",
        actions: [e(Badge, { key: "api", tone: "b-orange", dot: true }, "API REQUIRED")] }),
      e(Card, { title: "Maintenance workspace unavailable", sub: "The Maintenance & Society screen requires the Laravel maintenance payload. Static society records, facility readiness, resident dues, collection percentages and reminder actions are intentionally hidden.", pad: true },
        e("div", { className: "empty" },
          e("div", { className: "empty-ic" }, e(Icon, { name: "wrench", size: 24 })),
          e("h3", null, "Laravel maintenance payload not loaded"),
          e("div", null, "Use a role and company scope with maintenance_society_options in Builder360Server."))),
    );
  }

  function Maintenance({ toast }) {
    const [tab, setTab] = React.useState("Society Formation");
    const tabs = ["Society Formation", "Handover Checklist", "Maintenance Dues"];
    const options = window.Builder360Server?.maintenance_society_options || null;
    const [societies, setSocieties] = React.useState(options?.societies || []);
    const [handoverItems, setHandoverItems] = React.useState(options?.handover_items || []);
    const [dues, setDues] = React.useState(options?.maintenance_dues || []);
    const [loading, setLoading] = React.useState(Boolean(options?.societies_index_url));
    const [error, setError] = React.useState("");
    const [modalAction, setModalAction] = React.useState(null);
    const initialProjectFilter = String(window.Builder360Server?.active_project_context?.project_id || "all");
    const [projectFilter, setProjectFilter] = React.useState(initialProjectFilter);
    const [dueStatusFilter, setDueStatusFilter] = React.useState("all");
    const dueStatusOptions = [["all", "All Status"], ["due", "Due"], ["overdue", "Overdue"], ["paid", "Paid"], ["cancelled", "Cancelled"]];

    if (!options?.source) {
      return e(MaintenanceApiRequired);
    }

    const refresh = React.useCallback(() => {
      setLoading(true);
      setError("");
      const societyParams = new URLSearchParams({ per_page: "30" });
      const handoverParams = new URLSearchParams({ per_page: "50" });
      const dueParams = new URLSearchParams({ per_page: "50" });
      if (projectFilter !== "all") {
        societyParams.set("project_id", projectFilter);
        handoverParams.set("project_id", projectFilter);
        dueParams.set("project_id", projectFilter);
      }
      if (dueStatusFilter !== "all") dueParams.set("status", dueStatusFilter);
      Promise.all([
        apiJson(options.societies_index_url + "?" + societyParams.toString()),
        options.can_view_handover ? apiJson(options.handover_items_index_url + "?" + handoverParams.toString()) : Promise.resolve({ data: [] }),
        options.can_view_due ? apiJson(options.dues_index_url + "?" + dueParams.toString()) : Promise.resolve({ data: [] }),
      ])
        .then(([societyBody, handoverBody, dueBody]) => {
          setSocieties(societyBody.data || []);
          setHandoverItems(handoverBody.data || []);
          setDues(dueBody.data || []);
        })
        .catch(apiError => {
          setError(apiError.message);
          toast?.("Maintenance workspace could not be loaded: " + apiError.message, "orange");
        })
        .finally(() => setLoading(false));
    }, [options.societies_index_url, options.handover_items_index_url, options.dues_index_url, options.can_view_handover, options.can_view_due, projectFilter, dueStatusFilter]);
    React.useEffect(() => refresh(), [refresh]);

    const defaultProject = options.projects?.[0];
    const selectedProject = options.projects?.find(project => String(project.id) === String(projectFilter)) || defaultProject;
    const scopedBookings = projectFilter === "all" ? (options.bookings || []) : (options.bookings || []).filter(booking => String(booking.project?.id || "") === String(projectFilter));
    const defaultBooking = scopedBookings[0] || options.bookings?.[0];
    const createSociety = () => {
      if (!options.can_create_society || !selectedProject) {
        toast("Society formation creation is not available for this role or scope.", "orange");
        return;
      }
      setModalAction({ action: "society_create", record: null });
    };

    const advanceSociety = society => {
      if (!options.can_create_society) return;
      const nextStatus = society.status === "formed" ? "handed_over" : society.status === "in_progress" ? "formed" : "in_progress";
      const nextProgress = nextStatus === "handed_over" ? 100 : nextStatus === "formed" ? 90 : 65;
      apiJson(maintenanceUrl(options.society_status_url_template, society, "__SOCIETY__"), {
        method: "PATCH",
        body: JSON.stringify({
          status: nextStatus,
          progress_percent: nextProgress,
          current_stage: titleCase(nextStatus),
          next_step: nextStatus === "handed_over" ? "Operational handover completed" : "Complete next statutory/committee step",
          registration_number: society.registration_number || "Application filed",
          note: "Updated from Maintenance & Society workspace.",
        }),
      })
        .then(body => { setSocieties(rows => rows.map(row => row.id === society.id ? body.data : row)); toast(body.data.formation_number + " updated.", "green"); })
        .catch(apiError => toast(apiError.message, "red"));
    };

    const reviewHandover = item => {
      if (!options.can_update_handover) {
        toast("Checklist update is not available for this role.", "orange");
        return;
      }
      const completed = Math.min(Number(item.checklist_completed || 0) + 1, Number(item.checklist_total || 0));
      const status = completed >= Number(item.checklist_total || 0) ? "complete" : "in_progress";
      apiJson(maintenanceUrl(options.handover_item_update_url_template, item, "__HANDOVER_ITEM__"), {
        method: "PATCH",
        body: JSON.stringify({ checklist_completed: completed, status, note: "Checklist reviewed from Maintenance & Society workspace." }),
      })
        .then(body => { setHandoverItems(rows => rows.map(row => row.id === item.id ? body.data : row)); toast(body.data.item_number + " checklist updated.", "green"); })
        .catch(apiError => toast(apiError.message, "red"));
    };

    const signOffHandover = item => {
      apiJson(maintenanceUrl(options.handover_item_signoff_url_template, item, "__HANDOVER_ITEM__"), {
        method: "PATCH",
        body: JSON.stringify({ note: "Signed off from Maintenance & Society workspace." }),
      })
        .then(body => { setHandoverItems(rows => rows.map(row => row.id === item.id ? body.data : row)); toast(body.data.item_number + " signed off.", "green"); })
        .catch(apiError => toast(apiError.message, "red"));
    };

    const raiseDemand = () => {
      if (!options.can_raise_due || !defaultBooking) {
        toast("Maintenance demand creation is not available for this role or scope.", "orange");
        return;
      }
      setModalAction({ action: "due_create", record: null });
    };

    const remindDue = due => {
      apiJson(maintenanceUrl(options.due_remind_url_template, due, "__DUE__"), { method: "PATCH", body: JSON.stringify({ note: "Reminder sent from Maintenance & Society workspace." }) })
        .then(body => { setDues(rows => rows.map(row => row.id === due.id ? body.data : row)); toast(body.data.due_number + " reminder recorded.", "green"); })
        .catch(apiError => toast(apiError.message, "red"));
    };

    const markPaid = due => {
      setModalAction({ action: "due_pay", record: due });
    };

    const onMaintenanceActionSaved = (action, data) => {
      if (action === "society_create") setSocieties(rows => [data, ...rows.filter(row => row.id !== data.id)]);
      if (action === "due_create") setDues(rows => [data, ...rows.filter(row => row.id !== data.id)]);
      if (action === "due_pay") setDues(rows => rows.map(row => row.id === data.id ? data : row));
    };

    const societyTable = e(Card, { title: "Society / Association Formation", sub: "Laravel-backed registration progress and association handover",
      action: e(Button, { sm: true, icon: "plus", onClick: createSociety, children: "New Society" }) },
      T([{ l: "Project" }, { l: "Units", r: true }, { l: "Registration" }, { l: "Status" }, { l: "Progress" }, { l: "Next Step" }, { l: "" }],
        societies.map(s => [
          e("div", null, e("div", { className: "cell-strong" }, s.project?.name || s.society_name), e("div", { className: "cell-sub" }, s.society_name)),
          e("span", { className: "mono" }, (s.occupied_units || 0) + " / " + (s.total_units || 0)),
          e("span", { className: "mono", style: { fontSize: 12 } }, s.registration_number || "Not filed"),
          e(Badge, { tone: statusTone(s.status), dot: true }, titleCase(s.status)),
          e("div", { style: { minWidth: 120 } }, e(ProgCell, { value: Number(s.progress_percent || 0) })),
          e("span", { className: "faint" }, s.next_step || s.current_stage || "Next step pending"),
          options.can_create_society && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--accent)" }, onClick: () => advanceSociety(s) }, "Advance")])) || e("div", { className: "empty" }, loading ? "Loading societies..." : "No society records"));

    const checklistTable = e(Card, { title: "Common-Area Handover Checklist", sub: "facility-wise handover to society from MySQL",
      action: e("label", { className: "chip-select", style: { gap: 8 } },
        e("span", { style: { color: "var(--text-3)" } }, "Project"),
        e("select", { "aria-label": "Filter maintenance records by project", value: projectFilter, disabled: loading, onChange: ev => setProjectFilter(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 170 } },
          [e("option", { key: "all", value: "all" }, "All scoped projects")].concat((options.projects || []).map(project => e("option", { key: project.id, value: String(project.id) }, project.label || project.name))))) },
      T([{ l: "Facility / Common Area" }, { l: "Checklist", r: true }, { l: "Completion" }, { l: "Status" }, { l: "" }],
        handoverItems.map(item => {
          const pct = item.completion_percent ?? (item.checklist_total ? Math.round(item.checklist_completed / item.checklist_total * 100) : 0);
          return [e("span", { className: "cell-strong" }, item.facility_name), e("span", { className: "mono" }, item.checklist_completed + " / " + item.checklist_total),
            e("div", { style: { minWidth: 130 } }, e(ProgCell, { value: pct })), e(Badge, { tone: statusTone(item.status), dot: true }, titleCase(item.status)),
            item.status === "complete"
              ? e("span", { className: "row gap-2", style: { color: "var(--green)", fontWeight: 700, fontSize: 12 } }, e(Icon, { name: "check", size: 14 }), "Signed off")
              : e("div", { className: "row gap-2" },
                options.can_update_handover && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--accent)" }, onClick: () => reviewHandover(item) }, "Review"),
                options.can_signoff_handover && item.checklist_completed >= item.checklist_total && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--green)" }, onClick: () => signOffHandover(item) }, "Sign off"))];
        })) || e("div", { className: "empty" }, loading ? "Loading checklist..." : "No handover items"));

    const duesTable = e(Card, { title: "Maintenance Charge Tracking", sub: "quarterly maintenance billing and collection from Laravel",
      action: e("div", { className: "row gap-2", style: { flexWrap: "wrap" } },
        e("label", { className: "chip-select", style: { gap: 8 } },
          e("span", { style: { color: "var(--text-3)" } }, "Status"),
          e("select", { "aria-label": "Filter maintenance dues by status", value: dueStatusFilter, disabled: loading, onChange: ev => setDueStatusFilter(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 112 } },
            dueStatusOptions.map(([value, label]) => e("option", { key: value, value }, label)))),
        e(Button, { sm: true, icon: "rupee", variant: "primary", onClick: raiseDemand, children: "Raise Demand" })) },
      T([{ l: "Unit" }, { l: "Resident" }, { l: "Project" }, { l: "Period" }, { l: "Amount", r: true }, { l: "Status" }, { l: "" }],
        dues.map(due => [
          e("span", { className: "cell-strong mono" }, due.unit?.unit_code || due.unit?.unit_number || "Unit"),
          e("div", { className: "cell-user" }, e(Avatar, { name: due.customer?.name || "Customer", sm: true }), e("span", { className: "cell-strong" }, due.customer?.name || "Customer")),
          e("span", { className: "tag" }, due.project?.code || "Project"), e("span", { className: "faint" }, (due.period_start_on || "-") + " to " + (due.period_end_on || "-")), e("span", { className: "mono cell-strong" }, moneyInr(due.balance_amount || due.amount)),
          e(Badge, { tone: statusTone(due.status), dot: true }, titleCase(due.status)),
          due.status === "paid" ? e("span", { className: "faint" }, "Paid") : e("div", { className: "row gap-2" },
            options.can_remind_due && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--accent)" }, onClick: () => remindDue(due) }, "Remind"),
            options.can_mark_due_paid && e("button", { className: "btn btn-sm btn-ghost", style: { color: "var(--green)" }, onClick: () => markPaid(due) }, "Mark paid"))])) || e("div", { className: "empty" }, loading ? "Loading dues..." : "No maintenance dues"));

    const summary = options.summary || {};
    const collectionTotal = Number(summary.maintenance_collected || 0) + Number(summary.maintenance_due || 0);
    const collectedPct = collectionTotal > 0 ? Math.round(Number(summary.maintenance_collected || 0) / collectionTotal * 100) : 0;
    const duePct = collectionTotal > 0 ? Math.round(Number(summary.maintenance_due || 0) / collectionTotal * 100) : 0;
    const overduePct = dues.length ? Math.round(dues.filter(d => d.status === "overdue").length / dues.length * 100) : 0;
    const content = { "Society Formation": societyTable, "Handover Checklist": checklistTable, "Maintenance Dues": duesTable }[tab];

    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "Maintenance & Society"], title: "Maintenance & Society Handover",
        sub: "Post-possession society formation, common-area handover and maintenance charge tracking backed by Laravel.",
        actions: [
          e("label", { key: "project", className: "chip-select", style: { gap: 8 } },
            e("span", { style: { color: "var(--text-3)" } }, "Project"),
            e("select", { "aria-label": "Filter maintenance workspace by project", value: projectFilter, disabled: loading, onChange: ev => setProjectFilter(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 170 } },
              [e("option", { key: "all", value: "all" }, "All scoped projects")].concat((options.projects || []).map(project => e("option", { key: project.id, value: String(project.id) }, project.label || project.name))))),
          e(Button, { key: 1, icon: "refresh", onClick: refresh, children: loading ? "Loading..." : "Refresh" }),
          e(Button, { key: 2, icon: "plus", variant: "primary", onClick: tab === "Maintenance Dues" ? raiseDemand : createSociety, children: tab === "Maintenance Dues" ? "Raise Demand" : "New Society" })
        ] }),
      error && e("div", { style: { marginBottom: 12, background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, fontSize: 13, fontWeight: 700 } }, error),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Societies Formed", value: String(summary.societies_formed ?? societies.filter(s => ["formed", "handed_over"].includes(s.status)).length), icon: "users", tone: "green", sub: (summary.societies_in_progress ?? 0) + " in progress" }),
        e(Stat, { label: "Common-Area Handover", value: String(summary.handover_percent ?? 0), unit: "%", icon: "key", tone: "accent", sub: "MySQL checklist" }),
        e(Stat, { label: "Pending Common Works", value: String(summary.pending_common_works ?? handoverItems.filter(i => i.status !== "complete").length), icon: "wrench", tone: "orange" }),
        e(Stat, { label: "Maintenance Due", value: moneyInr(summary.maintenance_due || 0), icon: "rupee", tone: "violet", sub: moneyInr(summary.maintenance_collected || 0) + " collected" })),
      e("div", { className: "grid", style: { gridTemplateColumns: "1fr 1fr", alignItems: "start", marginBottom: 16 } },
        e(Card, { title: "Handover Readiness by Facility", sub: "% complete · database records", pad: true },
          e(HBars, { data: handoverItems.slice(0, 5).map(item => ({ label: item.facility_name, value: item.completion_percent || 0, display: (item.completion_percent || 0) + "%", color: item.status === "complete" ? "var(--green)" : item.status === "pending_snags" ? "var(--orange)" : "var(--blue)" })) })),
        e(Card, { title: "Maintenance Collection", sub: "current due register", pad: true },
          e("div", { className: "row", style: { gap: 16, alignItems: "center" } },
            e(Donut, { size: 124, thickness: 18, data: [
              { label: "Collected", value: collectedPct, color: "var(--green)" },
              { label: "Due", value: duePct, color: "var(--orange)" },
              { label: "Overdue", value: overduePct, color: "var(--red)" }],
              center: e("div", null, e("div", { className: "mono", style: { fontWeight: 800, fontSize: 18, fontFamily: "var(--font-display)" } }, collectedPct + "%"), e("div", { className: "kpi-mini" }, "collected")) }),
            e("div", { className: "legend", style: { flex: 1 } },
              [["Collected", collectedPct + "%", "var(--green)"], ["Due", duePct + "%", "var(--orange)"], ["Overdue", overduePct + "%", "var(--red)"]].map((d, i) =>
                e("div", { className: "legend-row", key: i }, e("i", { className: "lk", style: { background: d[2] } }), e("span", null, d[0]), e("span", { className: "lv" }, d[1]))))))),
      e("div", { className: "tabs" }, tabs.map(t => e("div", { key: t, className: "tab " + (tab === t ? "on" : ""), onClick: () => setTab(t) }, t))),
      content,
      modalAction && e(MaintenanceActionModal, { options, action: modalAction.action, record: modalAction.record, defaults: { defaultProject: selectedProject, defaultBooking }, onClose: () => setModalAction(null), onSaved: onMaintenanceActionSaved, toast }),
    );
  }

  window.Maintenance = Maintenance;
})();
