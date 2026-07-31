const React = window.React;

/* Builder360 — Construction, site progress, stores and procurement */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, PageHead, ChipSelect, Empty } = window;
  const e = React.createElement;
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const siteOptions = () => window.Builder360Server?.construction_site_options || null;
  const hasServerSite = () => siteOptions()?.source === "laravel-sqlite";
  const navigate = route => {
    if (window.Builder360Navigate) window.Builder360Navigate(route);
    else window.dispatchEvent(new CustomEvent("builder360:navigate", { detail: { route } }));
  };
  const money = value => "₹" + Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
  const lakh = value => "₹" + (Number(value || 0) / 100000).toLocaleString("en-IN", { maximumFractionDigits: 1 }) + " L";
  const crore = value => "₹" + (Number(value || 0) / 10000000).toLocaleString("en-IN", { maximumFractionDigits: 2 }) + " Cr";
  const label = status => String(status || "unknown").replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase());
  const safeArray = value => Array.isArray(value) ? value : [];
  const today = () => new Date().toISOString().slice(0, 10);
  const addDays = days => {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
  };
  const formGrid = { display: "grid", gridTemplateColumns: "repeat(4, minmax(0, 1fr))", gap: 12, padding: 16, borderBottom: "1px solid var(--border)" };
  const field = { width: "100%", height: 38, border: "1px solid var(--border)", borderRadius: 10, padding: "0 10px", background: "var(--surface)", color: "var(--text)" };
  const textArea = { width: "100%", minHeight: 76, border: "1px solid var(--border)", borderRadius: 10, padding: 10, background: "var(--surface)", color: "var(--text)", resize: "vertical" };
  const labelStyle = { display: "grid", gap: 6, fontSize: 12, fontWeight: 700, color: "var(--text-2)" };
  const tone = status => ({
    active: "b-green", approved: "b-green", completed: "b-green", received: "b-green",
    submitted: "b-orange", draft: "b-orange", delayed: "b-red", blocked: "b-red",
    in_progress: "b-blue", planned: "b-blue", partially_received: "b-blue",
    rejected: "b-red", inactive: "b-slate",
  }[status] || "b-slate");
  const firstApiError = payload => {
    if (payload?.message) return payload.message;
    const first = Object.values(payload?.errors || {})[0];
    return Array.isArray(first) ? first[0] : "Request failed.";
  };
  async function apiJson(url, options = {}) {
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrf(),
        ...(options.headers || {}),
      },
      credentials: "same-origin",
      ...options,
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(firstApiError(payload));
    return payload;
  }
  const projectName = row => row?.project?.name || row?.project?.code || "Project";
  const projectLabel = row => row?.project ? `${row.project.code || ""} · ${row.project.name || "Project"}` : "Unassigned project";
  const dateText = value => value ? new Date(value).toLocaleDateString("en-IN", { day: "2-digit", month: "short" }) : "—";
  const lastWorkflowNote = row => (row?.workflow_history || []).slice(-1)[0]?.note || "No action pending for your role.";
  const replaceTemplate = (template, token, id) => String(template || "").replace(token, id || "");
  const activeProjects = opts => safeArray(opts?.projects).filter(project => project.status === "active");
  const milestonesForProject = (opts, projectId) => safeArray(opts?.milestones).filter(milestone => String(milestone.project?.id || "") === String(projectId || ""));
  const normalizeMilestone = row => ({
    ...row,
    planned_start: row.planned_start || row.planned_start_on,
    planned_finish: row.planned_finish || row.planned_end_on,
    actual_start: row.actual_start || row.actual_start_on,
    actual_finish: row.actual_finish || row.actual_end_on,
    owner_name: row.owner_name || row.created_by?.name || "Construction Team",
  });
  const normalizeDailyReport = row => {
    const progressItems = safeArray(row.progress_items);
    const completion = progressItems.length
      ? Math.round(progressItems.reduce((sum, item) => sum + Number(item.completion_percent ?? item.progress_percent ?? 0), 0) / progressItems.length)
      : Number(row.computed_completion_percent || 0);

    return {
      ...row,
      computed_completion_percent: completion,
      open_issues: row.open_issues ?? (String(row.blockers || "").trim() ? 1 : 0),
      can_approve: row.can_approve === true,
    };
  };
  const normalizeRequisition = row => ({
    ...row,
    can_approve: row.can_approve === true,
    estimated_total: Number(row.estimated_total || 0),
  });
  const normalizeStock = row => ({
    ...row,
    on_hand_quantity: Number(row.on_hand_quantity || 0),
    minimum_stock_quantity: Number(row.minimum_stock_quantity || 0),
    average_rate: Number(row.average_rate || 0),
    stock_value: Number(row.stock_value || 0),
    status: row.status || "active",
    store_type: row.store_type || "site",
  });

  function ServerBadge() {
    return e("span", { className: "badge " + (hasServerSite() ? "b-blue" : "b-orange"), style: { height: 28 } },
      e(Icon, { name: hasServerSite() ? "database" : "alert", size: 13 }),
      hasServerSite() ? "MySQL-backed" : "API REQUIRED");
  }

  // ============ CONSTRUCTION PLANNING ============
  function Planning({ toast }) {
    const opts = siteOptions();
    const summary = opts?.summary || {};
    const projectChoices = activeProjects(opts);
    const [showForm, setShowForm] = React.useState(false);
    const [busy, setBusy] = React.useState(false);
    const [milestones, setMilestones] = React.useState(safeArray(opts?.milestones).map(normalizeMilestone));
    const [form, setForm] = React.useState({
      project_id: projectChoices[0]?.id || "",
      milestone_code: "MS-" + Date.now().toString().slice(-5),
      name: "",
      phase: "Structure",
      planned_start_on: today(),
      planned_end_on: addDays(30),
      weight_percent: 10,
    });
    React.useEffect(() => setMilestones(safeArray(opts?.milestones).map(normalizeMilestone)), [opts?.milestones?.length]);
    const setField = (key, value) => setForm(current => ({ ...current, [key]: value }));
    async function submitMilestone() {
      if (!opts?.can_create_milestone || !opts?.milestones_store_url) {
        toast("Milestone creation is not available for your role.", "orange");
        return;
      }
      setBusy(true);
      try {
        const payload = await apiJson(opts.milestones_store_url, {
          method: "POST",
          body: JSON.stringify({
            ...form,
            project_id: Number(form.project_id),
            weight_percent: Number(form.weight_percent),
            dependencies: [],
            metadata: { source: "construction_planning_screen" },
          }),
        });
        const row = normalizeMilestone(payload.data || {});
        setMilestones(current => [row, ...current]);
        setShowForm(false);
        toast(payload.message || "Construction milestone created.", "green");
      } catch (err) {
        toast(err.message || "Could not create milestone.", "red");
      } finally {
        setBusy(false);
      }
    }
    const rows = hasServerSite() ? milestones : [];
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Construction", "Planning"], title: "Construction Planning",
        sub: hasServerSite() ? "Milestones, owners, planned/actual dates and progress from Laravel records." : "Construction planning API required; no local milestone rows are fabricated.",
        actions: [e(ServerBadge, { key: 0 }), e(Button, { key: 1, icon: "plus", variant: "primary", onClick: () => hasServerSite() && opts?.can_create_milestone ? setShowForm(v => !v) : toast("Milestone creation is not available for your role or scope.", "orange"), children: showForm ? "Close Form" : "Add Milestone" })] }),
      showForm && e(Card, { title: "Create Controlled Milestone", sub: "Saved through Laravel validation, company/project scoping and audit workflow.", style: { marginBottom: 16 } },
        e("div", { style: formGrid },
          e("label", { style: labelStyle }, "Project", e("select", { style: field, value: form.project_id, disabled: busy, onChange: ev => setField("project_id", ev.target.value) },
            projectChoices.map(project => e("option", { key: project.id, value: project.id }, project.label || projectName({ project }))))),
          e("label", { style: labelStyle }, "Milestone Code", e("input", { style: field, value: form.milestone_code, disabled: busy, onChange: ev => setField("milestone_code", ev.target.value) })),
          e("label", { style: labelStyle }, "Phase", e("input", { style: field, value: form.phase, disabled: busy, onChange: ev => setField("phase", ev.target.value) })),
          e("label", { style: labelStyle }, "Weight %", e("input", { type: "number", min: 0, max: 100, style: field, value: form.weight_percent, disabled: busy, onChange: ev => setField("weight_percent", ev.target.value) })),
          e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Milestone Name", e("input", { style: field, placeholder: "Tower A slab casting", value: form.name, disabled: busy, onChange: ev => setField("name", ev.target.value) })),
          e("label", { style: labelStyle }, "Planned Start", e("input", { type: "date", style: field, value: form.planned_start_on, disabled: busy, onChange: ev => setField("planned_start_on", ev.target.value) })),
          e("label", { style: labelStyle }, "Planned End", e("input", { type: "date", style: field, value: form.planned_end_on, disabled: busy, onChange: ev => setField("planned_end_on", ev.target.value) })),
          e("div", { className: "row gap-2", style: { gridColumn: "1 / -1" } },
            e(Button, { variant: "primary", icon: "check", disabled: busy, onClick: submitMilestone, children: busy ? "Saving…" : "Save Milestone" }),
            e(Button, { icon: "x", disabled: busy, onClick: () => setShowForm(false), children: "Cancel" })))),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Active Milestones", value: summary.active_milestones ?? rows.length, icon: "calendar", tone: "accent" }),
        e(Stat, { label: "Delayed / Blocked", value: summary.delayed_milestones ?? rows.filter(r => ["delayed", "blocked"].includes(r.status)).length, icon: "alert", tone: "red" }),
        e(Stat, { label: "Avg. Progress", value: summary.average_progress ?? Math.round(rows.reduce((s, r) => s + Number(r.progress_percent || 0), 0) / Math.max(rows.length, 1)), unit: "%", icon: "trend", tone: "orange" }),
        e(Stat, { label: "Projects Visible", value: opts?.projects?.length ?? 0, icon: "building", tone: "blue" })),
      e(Card, { title: "Milestone Schedule", sub: "Scoped by company/project permissions" },
        rows.length === 0
          ? e(Empty, { icon: "calendar", title: "No milestones found", sub: "No construction milestone records are available in your current scope." })
          : e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
            e("thead", null, e("tr", null, ["Activity", "Project", "Owner", "Planned", "Actual", "Progress", "Status"].map((h, i) => e("th", { key: i }, h)))),
            e("tbody", null, rows.map(r =>
              e("tr", { key: r.id || r.name },
                e("td", null, e("div", { className: "cell-strong" }, r.name), e("div", { className: "cell-sub" }, r.phase || r.milestone_code || "Milestone")),
                e("td", null, projectName(r)),
                e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: r.owner_name || "Construction Team", sm: true }), r.owner_name || "Construction Team")),
                e("td", null, `${dateText(r.planned_start)} → ${dateText(r.planned_finish)}`),
                e("td", null, `${dateText(r.actual_start)} → ${dateText(r.actual_finish)}`),
                e("td", null, e(ProgCell, { value: Number(r.progress_percent || 0) })),
                e("td", null, e(Badge, { tone: tone(r.status), dot: true }, label(r.status))))))))));
  }

  // ============ DAILY SITE PROGRESS ============
  function DailyProgress({ toast }) {
    const opts = siteOptions();
    const summary = opts?.summary || {};
    const projectChoices = activeProjects(opts);
    const firstProjectId = projectChoices[0]?.id || "";
    const firstMilestoneId = milestonesForProject(opts, firstProjectId)[0]?.id || "";
    const [reports, setReports] = React.useState(safeArray(opts?.daily_reports).map(normalizeDailyReport));
    const [showForm, setShowForm] = React.useState(false);
    const [busy, setBusy] = React.useState(false);
    const [form, setForm] = React.useState({
      project_id: firstProjectId,
      milestone_id: firstMilestoneId,
      report_date: today(),
      weather: "Clear",
      manpower_count: 0,
      work_done: "",
      progress_percent: 0,
      work_summary: "",
      blockers: "",
    });
    React.useEffect(() => setReports(safeArray(opts?.daily_reports).map(normalizeDailyReport)), [opts?.daily_reports?.length]);
    const formMilestones = milestonesForProject(opts, form.project_id);
    const setField = (key, value) => setForm(current => {
      const next = { ...current, [key]: value };
      if (key === "project_id") next.milestone_id = milestonesForProject(opts, value)[0]?.id || "";
      return next;
    });
    async function submitReport() {
      if (!opts?.can_create_daily_report || !opts?.daily_reports_store_url) {
        toast("Daily report creation is not available for your role.", "orange");
        return;
      }
      setBusy(true);
      try {
        const payload = await apiJson(opts.daily_reports_store_url, {
          method: "POST",
          body: JSON.stringify({
            project_id: Number(form.project_id),
            report_date: form.report_date,
            weather: form.weather,
            manpower_count: Number(form.manpower_count || 0),
            progress_items: [{
              milestone_id: Number(form.milestone_id),
              work_done: form.work_done,
              progress_percent: Number(form.progress_percent || 0),
            }],
            work_summary: form.work_summary,
            blockers: form.blockers || null,
          }),
        });
        const row = normalizeDailyReport(payload.data || {});
        setReports(current => [row, ...current]);
        setShowForm(false);
        toast(payload.message || "Daily progress report submitted.", "green");
      } catch (err) {
        toast(err.message || "Could not submit daily report.", "red");
      } finally {
        setBusy(false);
      }
    }
    const rows = hasServerSite() ? reports : [];
    async function approve(report) {
      const url = replaceTemplate(opts?.daily_report_approve_url_template, "__REPORT__", report.id);
      try {
        const payload = await apiJson(url, { method: "PATCH", body: JSON.stringify({ note: "Approved from Daily Site Progress screen." }) });
        setReports(current => current.map(r => r.id === report.id ? payload.data : r));
        toast("Daily report " + (payload.data?.report_number || report.report_number) + " approved.", "green");
      } catch (err) {
        toast(err.message || "Daily report approval failed.", "red");
      }
    }
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Construction", "Daily Progress"], title: "Daily Site Progress",
        sub: hasServerSite() ? "Engineer/supervisor daily reports from Laravel, including manpower, blockers and approval status." : "Daily progress API required; no local DPR rows are fabricated.",
        actions: [e(ServerBadge, { key: 0 }), e(Button, { key: 1, icon: "plus", variant: "primary", onClick: () => hasServerSite() && opts?.can_create_daily_report ? setShowForm(v => !v) : toast("Daily report creation is not available for your role or scope.", "orange"), children: showForm ? "Close Form" : "New Daily Report" })] }),
      showForm && e(Card, { title: "Submit Daily Progress Report", sub: "Posts a validated DPR to Laravel with project, milestone, manpower, progress and blockers.", style: { marginBottom: 16 } },
        e("div", { style: formGrid },
          e("label", { style: labelStyle }, "Project", e("select", { style: field, value: form.project_id, disabled: busy, onChange: ev => setField("project_id", ev.target.value) },
            projectChoices.map(project => e("option", { key: project.id, value: project.id }, project.label || projectName({ project }))))),
          e("label", { style: labelStyle }, "Milestone", e("select", { style: field, value: form.milestone_id, disabled: busy, onChange: ev => setField("milestone_id", ev.target.value) },
            formMilestones.map(milestone => e("option", { key: milestone.id, value: milestone.id }, `${milestone.milestone_code || milestone.id} · ${milestone.name}`)))),
          e("label", { style: labelStyle }, "Report Date", e("input", { type: "date", style: field, value: form.report_date, disabled: busy, onChange: ev => setField("report_date", ev.target.value) })),
          e("label", { style: labelStyle }, "Weather", e("input", { style: field, value: form.weather, disabled: busy, onChange: ev => setField("weather", ev.target.value) })),
          e("label", { style: labelStyle }, "Manpower Count", e("input", { type: "number", min: 0, style: field, value: form.manpower_count, disabled: busy, onChange: ev => setField("manpower_count", ev.target.value) })),
          e("label", { style: labelStyle }, "Progress %", e("input", { type: "number", min: 0, max: 100, style: field, value: form.progress_percent, disabled: busy, onChange: ev => setField("progress_percent", ev.target.value) })),
          e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Work Done", e("input", { style: field, placeholder: "Rebar fixing completed for slab zone A", value: form.work_done, disabled: busy, onChange: ev => setField("work_done", ev.target.value) })),
          e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Work Summary", e("textarea", { style: textArea, value: form.work_summary, disabled: busy, onChange: ev => setField("work_summary", ev.target.value) })),
          e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Blockers / Delay Reasons", e("textarea", { style: textArea, value: form.blockers, disabled: busy, onChange: ev => setField("blockers", ev.target.value) })),
          e("div", { className: "row gap-2", style: { gridColumn: "1 / -1" } },
            e(Button, { variant: "primary", icon: "check", disabled: busy || !formMilestones.length, onClick: submitReport, children: busy ? "Submitting…" : "Submit DPR" }),
            e(Button, { icon: "x", disabled: busy, onClick: () => setShowForm(false), children: "Cancel" }),
            !formMilestones.length && e(Badge, { tone: "b-orange" }, "Create a milestone for this project first")))),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Reports Today", value: summary.reports_today ?? rows.length, icon: "hardhat", tone: "green" }),
        e(Stat, { label: "Manpower Logged", value: Number(summary.total_manpower_latest_reports ?? rows.reduce((s, r) => s + Number(r.manpower_count || 0), 0)).toLocaleString("en-IN"), icon: "users", tone: "accent" }),
        e(Stat, { label: "Open Site Issues", value: summary.open_site_issues ?? rows.reduce((s, r) => s + Number(r.open_issues || 0), 0), icon: "alert", tone: "orange" }),
        e(Stat, { label: "Approval Queue", value: rows.filter(r => r.status === "submitted").length, icon: "clock", tone: "violet" })),
      e(Card, { title: "Daily Progress Report Register", sub: "Scoped reports with approval controls" },
        rows.length === 0
          ? e(Empty, { icon: "hardhat", title: "No daily reports", sub: "No DPR records are available in your current scope." })
          : e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
            e("thead", null, e("tr", null, ["Report", "Project", "Reported By", "Work Summary", "Completion", "Labour", "Status", "Action"].map((h, i) => e("th", { key: i }, h)))),
            e("tbody", null, rows.map(r =>
              e("tr", { key: r.id },
                e("td", null, e("div", { className: "cell-strong mono" }, r.report_number || r.id), e("div", { className: "cell-sub" }, dateText(r.report_date))),
                e("td", null, projectName(r)),
                e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: r.prepared_by?.name || "Site Team", sm: true }), r.prepared_by?.name || "Site Team")),
                e("td", null, e("div", { className: "cell-strong" }, r.work_summary || "—"), r.blockers && e("div", { className: "cell-sub", style: { color: "var(--red)" } }, "Blocker: " + r.blockers)),
                e("td", null, e(ProgCell, { value: Number(r.computed_completion_percent || 0) })),
                e("td", { className: "mono" }, Number(r.manpower_count || 0).toLocaleString("en-IN")),
                e("td", null, e(Badge, { tone: tone(r.status), dot: true }, label(r.status))),
                e("td", null, r.can_approve
                  ? e(Button, { sm: true, variant: "primary", onClick: () => approve(r), children: "Approve" })
                  : e(Button, { sm: true, onClick: () => toast(lastWorkflowNote(r), "accent"), children: "History" })))))))));
  }

  // ============ MATERIALS AND STORE ============
  function Materials({ toast }) {
    const opts = siteOptions();
    const summary = opts?.summary || {};
    const [tab, setTab] = React.useState("Stock Register");
    const [reqs, setReqs] = React.useState(safeArray(opts?.requisitions).map(normalizeRequisition));
    const [showTransfer, setShowTransfer] = React.useState(false);
    const [busyTransfer, setBusyTransfer] = React.useState(false);
    const projectChoices = activeProjects(opts);
    const [stockRows, setStockRows] = React.useState(hasServerSite() ? safeArray(opts?.stock_items).map(normalizeStock) : []);
    const transferableStocks = stockRows.filter(row => String(row.status || "active") === "active" && Number(row.on_hand_quantity || 0) > 0);
    const [transferForm, setTransferForm] = React.useState({
      source_stock_item_id: transferableStocks[0]?.id || "",
      destination_project_id: projectChoices[0]?.id || "",
      destination_store_type: "site",
      movement_date: today(),
      quantity: 1,
      transfer_reference: "TRF-" + Date.now().toString().slice(-5),
      purpose: "",
      remarks: "",
    });
    React.useEffect(() => setReqs(safeArray(opts?.requisitions).map(normalizeRequisition)), [opts?.requisitions?.length]);
    React.useEffect(() => setStockRows(hasServerSite() ? safeArray(opts?.stock_items).map(normalizeStock) : []), [opts?.stock_items?.length]);
    React.useEffect(() => {
      setTransferForm(current => ({
        ...current,
        source_stock_item_id: current.source_stock_item_id || transferableStocks[0]?.id || "",
        destination_project_id: current.destination_project_id || projectChoices[0]?.id || "",
      }));
    }, [transferableStocks.length, projectChoices.length]);
    const stocks = stockRows;
    const setTransferField = (key, value) => setTransferForm(current => ({ ...current, [key]: value }));
    async function submitStockTransfer() {
      if (!opts?.can_transfer_stock || !opts?.stock_transfer_store_url) {
        toast("Stock transfer is not available for your role or scope.", "orange");
        return;
      }
      setBusyTransfer(true);
      try {
        const payload = await apiJson(opts.stock_transfer_store_url, {
          method: "POST",
          body: JSON.stringify({
            source_stock_item_id: Number(transferForm.source_stock_item_id),
            destination_project_id: Number(transferForm.destination_project_id),
            destination_store_type: transferForm.destination_store_type,
            movement_date: transferForm.movement_date,
            quantity: Number(transferForm.quantity || 0),
            transfer_reference: transferForm.transfer_reference || null,
            purpose: transferForm.purpose || "Stock transfer from Material & Store screen.",
            remarks: transferForm.remarks || null,
          }),
        });
        const movements = safeArray(payload.data);
        const outMovement = movements.find(row => row.movement_type === "transfer_out");
        const inMovement = movements.find(row => row.movement_type === "transfer_in");
        const destinationProject = projectChoices.find(project => String(project.id) === String(transferForm.destination_project_id));
        setStockRows(current => {
          let updated = current.map(row => {
            if (outMovement?.stock_item?.id && String(row.id) === String(outMovement.stock_item.id)) {
              return normalizeStock({
                ...row,
                on_hand_quantity: outMovement.stock_item.on_hand_quantity,
                stock_value: outMovement.stock_item.stock_value,
                average_rate: outMovement.rate,
                last_movement_at: new Date().toISOString(),
              });
            }
            if (inMovement?.stock_item?.id && String(row.id) === String(inMovement.stock_item.id)) {
              return normalizeStock({
                ...row,
                on_hand_quantity: inMovement.stock_item.on_hand_quantity,
                stock_value: inMovement.stock_item.stock_value,
                average_rate: inMovement.rate,
                last_movement_at: new Date().toISOString(),
              });
            }
            return row;
          });
          if (inMovement?.stock_item?.id && !updated.some(row => String(row.id) === String(inMovement.stock_item.id))) {
            updated = [normalizeStock({
              id: inMovement.stock_item.id,
              company_id: destinationProject?.company_id,
              project_id: destinationProject?.id,
              store_type: inMovement.store_type,
              item_code: inMovement.item_code,
              description: inMovement.description,
              unit: inMovement.unit,
              on_hand_quantity: inMovement.balance_after_quantity,
              stock_value: inMovement.balance_after_value,
              average_rate: inMovement.rate,
              minimum_stock_quantity: 0,
              status: "active",
              project: destinationProject ? { id: destinationProject.id, code: destinationProject.code, name: destinationProject.name } : null,
              recent_movements: [inMovement],
            }), ...updated];
          }
          return updated;
        });
        setShowTransfer(false);
        toast((outMovement?.movement_number || "Stock transfer") + " posted through Laravel stock ledger.", "green");
      } catch (err) {
        toast(err.message || "Stock transfer failed.", "red");
      } finally {
        setBusyTransfer(false);
      }
    }
    async function approveReq(req) {
      const url = replaceTemplate(opts?.requisition_approve_url_template, "__REQUISITION__", req.id);
      try {
        const payload = await apiJson(url, { method: "PATCH", body: JSON.stringify({ note: "Approved from Material & Store screen." }) });
        setReqs(current => current.map(r => r.id === req.id ? normalizeRequisition(payload.data || {}) : r));
        toast("Requisition " + (payload.data?.requisition_number || req.requisition_number) + " approved.", "green");
      } catch (err) {
        toast(err.message || "Requisition approval failed.", "red");
      }
    }
    function openMaterialInwardWorkflow() {
      if (!hasServerSite() || !opts?.goods_receipts_store_url) {
        toast("Goods receipt workflow is unavailable for your role or current scope.", "orange");
        return;
      }

      navigate("procurement");
      toast("Material inward is handled in Procurement: open an approved PO and click Receive to post a Laravel GRN.", "accent");
    }
    const stockTable = e(Card, { title: "Stock Register", sub: "Central and site store balances from current scope" },
      stocks.length === 0
        ? e(Empty, { icon: "box", title: "No stock items", sub: "No stock records are available in your current scope." })
        : e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
          e("thead", null, e("tr", null, ["Material", "Project / Store", "Unit", "In Stock", "Min", "Avg Rate", "Value", "Status"].map((h, i) => e("th", { key: i, style: i >= 3 && i <= 6 ? { textAlign: "right" } : {} }, h)))),
          e("tbody", null, stocks.map(s =>
            e("tr", { key: s.id },
              e("td", null, e("div", { className: "cell-strong" }, s.description), e("div", { className: "cell-sub mono" }, s.item_code || "—")),
              e("td", null, e("span", { className: "tag" }, projectName(s)), e("div", { className: "cell-sub" }, label(s.store_type))),
              e("td", null, s.unit),
              e("td", { className: "num mono" }, Number(s.on_hand_quantity || 0).toLocaleString("en-IN")),
              e("td", { className: "num mono faint" }, Number(s.minimum_stock_quantity || 0).toLocaleString("en-IN")),
              e("td", { className: "num mono" }, money(s.average_rate)),
              e("td", { className: "num mono cell-strong" }, money(s.stock_value)),
              e("td", null, e(Badge, { tone: s.is_below_minimum ? "b-red" : tone(s.status), dot: true }, s.is_below_minimum ? "Low Stock" : label(s.status)))))))));
    const requisitionTable = e(Card, { title: "Material Indents / Purchase Requisitions", sub: "Approval queue and procurement handoff" },
      reqs.length === 0
        ? e(Empty, { icon: "cart", title: "No requisitions", sub: "No purchase requisitions are available in your current scope." })
        : e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
          e("thead", null, e("tr", null, ["Requisition", "Project", "Requested By", "Purpose", "Required By", "Estimated", "Status", "Action"].map((h, i) => e("th", { key: i, style: i === 5 ? { textAlign: "right" } : {} }, h)))),
          e("tbody", null, reqs.map(r =>
            e("tr", { key: r.id },
              e("td", { className: "cell-strong mono" }, r.requisition_number),
              e("td", null, projectName(r)),
              e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: r.requested_by?.name || "Requester", sm: true }), r.requested_by?.name || "Requester")),
              e("td", null, r.purpose || (r.items || []).map(i => i.description || i.item_code).filter(Boolean).slice(0, 2).join(", ") || "—"),
              e("td", null, dateText(r.required_by)),
              e("td", { className: "num mono cell-strong" }, money(r.estimated_total)),
              e("td", null, e(Badge, { tone: tone(r.status), dot: true }, label(r.status))),
              e("td", null, r.can_approve
                ? e(Button, { sm: true, variant: "primary", onClick: () => approveReq(r), children: "Approve" })
                : e(Button, { sm: true, onClick: () => toast(lastWorkflowNote(r), "accent"), children: "History" }))))))));
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Construction", "Material & Store"], title: "Material & Store Inventory",
        sub: hasServerSite() ? "MySQL-backed stock, low-stock status, valuation and material indents." : "Material/store API required; no local stock rows are fabricated.",
        actions: [
          e(ServerBadge, { key: 0 }),
          e(Button, { key: 1, icon: "truck", onClick: () => hasServerSite() && opts?.can_transfer_stock ? setShowTransfer(v => !v) : toast("Stock transfer is not available for your role or scope.", "orange"), children: showTransfer ? "Close Transfer" : "Stock Transfer" }),
          e(Button, { key: 2, icon: "plus", variant: "primary", onClick: openMaterialInwardWorkflow, children: "Material Inward" })
        ] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        showTransfer && e(Card, { title: "Create Stock Transfer", sub: "Posts a governed transfer-out and transfer-in pair through the Laravel stock ledger.", style: { gridColumn: "1 / -1", marginBottom: 4 } },
          e("div", { style: formGrid },
            e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Source stock item",
              e("select", { style: field, value: transferForm.source_stock_item_id, disabled: busyTransfer || !transferableStocks.length, onChange: ev => setTransferField("source_stock_item_id", ev.target.value) },
                transferableStocks.length ? transferableStocks.map(item => e("option", { key: item.id, value: item.id }, `${item.item_code || "Stock"} · ${item.description || "Item"} · ${projectName(item)} · ${label(item.store_type)} · ${Number(item.on_hand_quantity || 0).toLocaleString("en-IN")} ${item.unit || ""}`)) : e("option", { value: "" }, "No transferable stock available"))),
            e("label", { style: labelStyle }, "Destination project",
              e("select", { style: field, value: transferForm.destination_project_id, disabled: busyTransfer || !projectChoices.length, onChange: ev => setTransferField("destination_project_id", ev.target.value) },
                projectChoices.length ? projectChoices.map(project => e("option", { key: project.id, value: project.id }, project.label || `${project.code || ""} · ${project.name || "Project"}`)) : e("option", { value: "" }, "No active project"))),
            e("label", { style: labelStyle }, "Destination store",
              e("select", { style: field, value: transferForm.destination_store_type, disabled: busyTransfer, onChange: ev => setTransferField("destination_store_type", ev.target.value) },
                ["site", "central"].map(store => e("option", { key: store, value: store }, label(store))))),
            e("label", { style: labelStyle }, "Movement date", e("input", { type: "date", max: today(), style: field, value: transferForm.movement_date, disabled: busyTransfer, onChange: ev => setTransferField("movement_date", ev.target.value) })),
            e("label", { style: labelStyle }, "Quantity", e("input", { type: "number", min: 0.001, step: 0.001, style: field, value: transferForm.quantity, disabled: busyTransfer, onChange: ev => setTransferField("quantity", ev.target.value) })),
            e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Transfer reference", e("input", { style: field, value: transferForm.transfer_reference, disabled: busyTransfer, onChange: ev => setTransferField("transfer_reference", ev.target.value), placeholder: "TRF-1001 / gate pass / approval ref" })),
            e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Purpose", e("textarea", { required: true, style: textArea, value: transferForm.purpose, disabled: busyTransfer, onChange: ev => setTransferField("purpose", ev.target.value), placeholder: "Reason for movement and destination work context." })),
            e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Remarks", e("textarea", { style: textArea, value: transferForm.remarks, disabled: busyTransfer, onChange: ev => setTransferField("remarks", ev.target.value), placeholder: "Vehicle, gate pass, approving engineer or handover note." })),
            e("div", { className: "row gap-2", style: { gridColumn: "1 / -1" } },
              e(Button, { variant: "primary", icon: "truck", disabled: busyTransfer || !transferableStocks.length || !projectChoices.length, onClick: submitStockTransfer, children: busyTransfer ? "Posting..." : "Post Stock Transfer" }),
              e(Button, { icon: "x", disabled: busyTransfer, onClick: () => setShowTransfer(false), children: "Cancel" })))),
        e(Stat, { label: "Total Stock Value", value: crore(summary.stock_value ?? stocks.reduce((s, r) => s + Number(r.stock_value || 0), 0)), icon: "box", tone: "accent" }),
        e(Stat, { label: "Low Stock Items", value: summary.low_stock_items ?? stocks.filter(r => r.is_below_minimum).length, icon: "alert", tone: "red" }),
        e(Stat, { label: "Stock Items", value: summary.stock_items ?? stocks.length, icon: "layers", tone: "blue" }),
        e(Stat, { label: "Open Indents", value: summary.open_indents ?? reqs.filter(r => ["draft", "submitted"].includes(r.status)).length, icon: "cart", tone: "orange" })),
      e("div", { className: "tabs" }, ["Stock Register", "Material Requests"].map(t => e("div", { key: t, className: "tab " + (tab === t ? "on" : ""), onClick: () => setTab(t) }, t))),
      tab === "Stock Register" ? stockTable : requisitionTable);
  }

  // ============ PROCUREMENT ============
  function Procurement({ toast }) {
    const opts = siteOptions();
    const summary = opts?.summary || {};
    const projectChoices = activeProjects(opts);
    const [orders, setOrders] = React.useState(opts?.purchase_orders || []);
    const [showIndent, setShowIndent] = React.useState(false);
    const [busyIndent, setBusyIndent] = React.useState(false);
    const [receivingPo, setReceivingPo] = React.useState(null);
    const [busyReceipt, setBusyReceipt] = React.useState(false);
    const [quoteComparison, setQuoteComparison] = React.useState(null);
    const [busyCompare, setBusyCompare] = React.useState(false);
    const [receiptForm, setReceiptForm] = React.useState({
      received_on: today(),
      delivery_challan_number: "",
      quality_notes: "",
      items: [],
    });
    const [indentForm, setIndentForm] = React.useState({
      project_id: projectChoices[0]?.id || "",
      department: "Construction",
      required_by: addDays(3),
      priority: "normal",
      purpose: "",
      item_code: "MAT-" + Date.now().toString().slice(-4),
      description: "",
      unit: "Nos",
      quantity: 1,
      estimated_rate: 0,
    });
    React.useEffect(() => setOrders(opts?.purchase_orders || []), [opts?.purchase_orders?.length]);
    const setIndentField = (key, value) => setIndentForm(current => ({ ...current, [key]: value }));
    const setReceiptField = (key, value) => setReceiptForm(current => ({ ...current, [key]: value }));
    const setReceiptItem = (index, key, value) => setReceiptForm(current => ({
      ...current,
      items: current.items.map((item, i) => i === index ? { ...item, [key]: value } : item),
    }));
    const openReceiptForm = (po) => {
      const items = safeArray(po.items).map(item => ({
        item_code: String(item.item_code || item.code || "").toUpperCase(),
        description: item.description || item.name || "PO item",
        unit: item.unit || "Nos",
        accepted_quantity: Number(po.status === "partially_received" ? 1 : (item.quantity || 1)),
        rejected_quantity: 0,
        remarks: "",
      })).filter(item => item.item_code);
      if (!items.length) {
        toast("This purchase order has no receivable item codes.", "orange");
        return;
      }
      setReceivingPo(po);
      setReceiptForm({
        received_on: today(),
        delivery_challan_number: "",
        quality_notes: "",
        items,
      });
    };
    async function submitGoodsReceipt() {
      if (!receivingPo || !opts?.goods_receipts_store_url) {
        toast("Goods receipt creation is not available for this purchase order.", "orange");
        return;
      }
      setBusyReceipt(true);
      try {
        const payload = await apiJson(opts.goods_receipts_store_url, {
          method: "POST",
          body: JSON.stringify({
            purchase_order_id: Number(receivingPo.id),
            received_on: receiptForm.received_on,
            delivery_challan_number: receiptForm.delivery_challan_number || null,
            quality_notes: receiptForm.quality_notes || null,
            items: receiptForm.items.map(item => ({
              item_code: item.item_code,
              accepted_quantity: Number(item.accepted_quantity || 0),
              rejected_quantity: Number(item.rejected_quantity || 0),
              remarks: item.remarks || null,
            })),
          }),
        });
        const receipt = payload.data || {};
        const poStatus = receipt.purchase_order?.status || "received";
        setOrders(current => current.map(po => String(po.id) === String(receivingPo.id)
          ? { ...po, status: poStatus, goods_receipts_count: Number(po.goods_receipts_count || 0) + 1, can_receive: ["approved", "partially_received"].includes(poStatus) }
          : po));
        setReceivingPo(null);
        toast((receipt.grn_number || "Goods receipt") + " created and stock ledger updated.", "green");
      } catch (err) {
        toast(err.message || "Goods receipt could not be created.", "red");
      } finally {
        setBusyReceipt(false);
      }
    }
    async function submitIndent() {
      if (!opts?.can_create_requisition || !opts?.requisitions_store_url) {
        toast("Material indent creation is not available for your role.", "orange");
        return;
      }
      setBusyIndent(true);
      try {
        const payload = await apiJson(opts.requisitions_store_url, {
          method: "POST",
          body: JSON.stringify({
            project_id: Number(indentForm.project_id),
            department: indentForm.department,
            required_by: indentForm.required_by,
            priority: indentForm.priority,
            purpose: indentForm.purpose || "Material indent from Procurement screen",
            items: [{
              item_code: indentForm.item_code,
              description: indentForm.description,
              unit: indentForm.unit,
              quantity: Number(indentForm.quantity || 0),
              estimated_rate: Number(indentForm.estimated_rate || 0),
            }],
          }),
        });
        setShowIndent(false);
        toast((payload.data?.requisition_number || "Requisition") + " submitted for approval.", "green");
      } catch (err) {
        toast(err.message || "Could not submit material indent.", "red");
      } finally {
        setBusyIndent(false);
      }
    }
    async function openQuoteComparison() {
      if (!opts?.requisition_quote_comparison_url_template) {
        toast("Procurement quote comparison is not available for your role or current scope.", "orange");
        return;
      }
      const requisitions = safeArray(opts?.requisitions);
      const requisition = requisitions.find(r => r.status === "approved") || requisitions[0];
      if (!requisition?.id) {
        toast("Create and approve a purchase requisition before comparing vendor quote candidates.", "orange");
        return;
      }
      setBusyCompare(true);
      try {
        const url = replaceTemplate(opts.requisition_quote_comparison_url_template, "__REQUISITION__", requisition.id);
        const payload = await apiJson(url);
        setQuoteComparison(payload.data || null);
        toast("Quote comparison loaded for " + (payload.data?.requisition?.requisition_number || requisition.requisition_number || "selected requisition") + ".", "green");
      } catch (err) {
        toast(err.message || "Quote comparison could not be loaded.", "red");
      } finally {
        setBusyCompare(false);
      }
    }
    const rows = hasServerSite() ? orders : [];
    async function approvePo(po) {
      const url = replaceTemplate(opts?.purchase_order_approve_url_template, "__PURCHASE_ORDER__", po.id);
      try {
        const payload = await apiJson(url, { method: "PATCH", body: JSON.stringify({ note: "Approved from Procurement screen." }) });
        setOrders(current => current.map(r => r.id === po.id ? payload.data : r));
        toast("Purchase order " + (payload.data?.po_number || po.po_number) + " approved.", "green");
      } catch (err) {
        toast(err.message || "Purchase order approval failed.", "red");
      }
    }
    const itemSummary = po => (po.items || []).map(i => i.description || i.item_code || i.name).filter(Boolean).slice(0, 2).join(", ") || po.purchase_requisition?.requisition_number || "Procurement item";
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Construction", "Procurement"], title: "Procurement",
        sub: hasServerSite() ? "Purchase requisitions, POs, vendors, GRN status and approval controls from Laravel." : "Procurement API required; no local PO rows are fabricated.",
        actions: [
          e(ServerBadge, { key: 0 }),
          e(Button, { key: 1, icon: "filter", disabled: busyCompare, onClick: openQuoteComparison, children: busyCompare ? "Comparing..." : "Compare Quotes" }),
          e(Button, { key: 2, icon: "plus", variant: "primary", onClick: () => hasServerSite() && opts?.can_create_requisition ? setShowIndent(v => !v) : toast("Material indent creation is not available for your role or scope.", "orange"), children: showIndent ? "Close Form" : "New Indent" })
        ] }),
      quoteComparison && e(Card, { title: "Quotation Comparison", sub: "Read-only comparison from the selected requisition and linked purchase-order candidates.", style: { marginBottom: 16 }, action: e(Button, { sm: true, icon: "x", onClick: () => setQuoteComparison(null), children: "Close" }) },
        e("div", { className: "grid g-4", style: { marginBottom: 14 } },
          e(Stat, { label: "Requisition", value: quoteComparison.requisition?.requisition_number || "—", icon: "cart", tone: "accent" }),
          e(Stat, { label: "Candidates", value: quoteComparison.summary?.candidate_count ?? 0, icon: "users", tone: "blue" }),
          e(Stat, { label: "Lowest Total", value: quoteComparison.summary?.lowest_total_amount !== null && quoteComparison.summary?.lowest_total_amount !== undefined ? money(quoteComparison.summary.lowest_total_amount) : "—", icon: "rupee", tone: "green" }),
          e(Stat, { label: "Variance", value: quoteComparison.summary?.variance_against_estimate !== null && quoteComparison.summary?.variance_against_estimate !== undefined ? money(quoteComparison.summary.variance_against_estimate) : "—", icon: "activity", tone: "orange" })),
        e("div", { className: "sys-note", style: { marginBottom: 12 } }, e(Icon, { name: "shield", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), quoteComparison.summary?.recommendation_note || "Comparison uses scoped Laravel procurement records."),
        quoteComparison.candidates?.length
          ? e("div", { className: "tbl-wrap", style: { marginBottom: 12 } }, e("table", { className: "tbl" },
            e("thead", null, e("tr", null, ["PO / Candidate", "Vendor", "Status", "Subtotal", "Tax", "Total"].map((h, i) => e("th", { key: i, style: i >= 3 ? { textAlign: "right" } : {} }, h)))),
            e("tbody", null, quoteComparison.candidates.map(row => e("tr", { key: row.id },
              e("td", null, e("div", { className: "cell-strong mono" }, row.po_number), e("div", { className: "cell-sub" }, dateText(row.po_date))),
              e("td", null, row.vendor?.name || "Vendor not linked"),
              e("td", null, e(Badge, { tone: tone(row.status), dot: true }, label(row.status))),
              e("td", { className: "num mono" }, money(row.subtotal)),
              e("td", { className: "num mono" }, money(row.tax_amount)),
              e("td", { className: "num mono cell-strong" }, money(row.total_amount)))))))
          : e(Empty, { icon: "filter", title: "No comparable quote candidates", sub: "Linked vendor PO candidates will appear here after they are created against this requisition." }),
        quoteComparison.item_comparison?.length
          ? e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
            e("thead", null, e("tr", null, ["Item", "Requested Qty", "Estimated Rate", "Lowest Rate", "Lowest Vendor", "Quotes"].map((h, i) => e("th", { key: i, style: i === 1 || i === 2 || i === 3 || i === 5 ? { textAlign: "right" } : {} }, h)))),
            e("tbody", null, quoteComparison.item_comparison.map(item => e("tr", { key: item.item_code },
              e("td", null, e("div", { className: "cell-strong" }, item.description), e("div", { className: "cell-sub mono" }, `${item.item_code} · ${item.unit}`)),
              e("td", { className: "num mono" }, Number(item.requested_quantity || 0).toLocaleString("en-IN")),
              e("td", { className: "num mono" }, money(item.estimated_rate)),
              e("td", { className: "num mono" }, item.lowest_rate !== null && item.lowest_rate !== undefined ? money(item.lowest_rate) : "—"),
              e("td", null, item.lowest_vendor_name || "—"),
              e("td", { className: "num mono" }, item.quote_count || 0))))))
          : null),
      showIndent && e(Card, { title: "Create Material Indent", sub: "Creates a submitted purchase requisition through Laravel validation and approval workflow.", style: { marginBottom: 16 } },
        e("div", { style: formGrid },
          e("label", { style: labelStyle }, "Project", e("select", { style: field, value: indentForm.project_id, disabled: busyIndent, onChange: ev => setIndentField("project_id", ev.target.value) },
            projectChoices.map(project => e("option", { key: project.id, value: project.id }, project.label || projectName({ project }))))),
          e("label", { style: labelStyle }, "Department", e("input", { style: field, value: indentForm.department, disabled: busyIndent, onChange: ev => setIndentField("department", ev.target.value) })),
          e("label", { style: labelStyle }, "Required By", e("input", { type: "date", style: field, value: indentForm.required_by, disabled: busyIndent, onChange: ev => setIndentField("required_by", ev.target.value) })),
          e("label", { style: labelStyle }, "Priority", e("select", { style: field, value: indentForm.priority, disabled: busyIndent, onChange: ev => setIndentField("priority", ev.target.value) },
            ["low", "normal", "high", "urgent"].map(priority => e("option", { key: priority, value: priority }, label(priority))))),
          e("label", { style: labelStyle }, "Item Code", e("input", { style: field, value: indentForm.item_code, disabled: busyIndent, onChange: ev => setIndentField("item_code", ev.target.value) })),
          e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Description", e("input", { style: field, placeholder: "OPC cement bags", value: indentForm.description, disabled: busyIndent, onChange: ev => setIndentField("description", ev.target.value) })),
          e("label", { style: labelStyle }, "Unit", e("input", { style: field, value: indentForm.unit, disabled: busyIndent, onChange: ev => setIndentField("unit", ev.target.value) })),
          e("label", { style: labelStyle }, "Quantity", e("input", { type: "number", min: 0.01, step: 0.01, style: field, value: indentForm.quantity, disabled: busyIndent, onChange: ev => setIndentField("quantity", ev.target.value) })),
          e("label", { style: labelStyle }, "Estimated Rate", e("input", { type: "number", min: 0, step: 0.01, style: field, value: indentForm.estimated_rate, disabled: busyIndent, onChange: ev => setIndentField("estimated_rate", ev.target.value) })),
          e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Purpose", e("textarea", { style: textArea, value: indentForm.purpose, disabled: busyIndent, onChange: ev => setIndentField("purpose", ev.target.value) })),
          e("div", { className: "row gap-2", style: { gridColumn: "1 / -1" } },
            e(Button, { variant: "primary", icon: "check", disabled: busyIndent, onClick: submitIndent, children: busyIndent ? "Submitting…" : "Submit Indent" }),
            e(Button, { icon: "x", disabled: busyIndent, onClick: () => setShowIndent(false), children: "Cancel" })))),
      receivingPo && e(Card, { title: "Create Goods Receipt", sub: `Receive against ${receivingPo.po_number || "purchase order"} through Laravel GRN and stock ledger workflow.`, style: { marginBottom: 16 } },
        e("div", { style: formGrid },
          e("label", { style: labelStyle }, "PO", e("input", { style: field, value: receivingPo.po_number || "", disabled: true })),
          e("label", { style: labelStyle }, "Received on", e("input", { type: "date", max: today(), style: field, value: receiptForm.received_on, disabled: busyReceipt, onChange: ev => setReceiptField("received_on", ev.target.value) })),
          e("label", { style: Object.assign({}, labelStyle, { gridColumn: "span 2" }) }, "Delivery challan", e("input", { style: field, value: receiptForm.delivery_challan_number, disabled: busyReceipt, onChange: ev => setReceiptField("delivery_challan_number", ev.target.value), placeholder: "Supplier challan / invoice reference" })),
          e("label", { style: Object.assign({}, labelStyle, { gridColumn: "1 / -1" }) }, "Quality notes", e("textarea", { style: textArea, value: receiptForm.quality_notes, disabled: busyReceipt, onChange: ev => setReceiptField("quality_notes", ev.target.value), placeholder: "Quality inspection remarks, shortage notes or delivery condition." })),
          e("div", { style: { gridColumn: "1 / -1" }, className: "tbl-wrap" },
            e("table", { className: "tbl" },
              e("thead", null, e("tr", null, ["Item", "Accepted", "Rejected", "Remarks"].map((h, i) => e("th", { key: i, style: i === 1 || i === 2 ? { textAlign: "right" } : {} }, h)))),
              e("tbody", null, receiptForm.items.map((item, index) =>
                e("tr", { key: item.item_code },
                  e("td", null, e("div", { className: "cell-strong" }, item.description), e("div", { className: "cell-sub mono" }, `${item.item_code} · ${item.unit}`)),
                  e("td", { className: "num" }, e("input", { type: "number", min: 0.01, step: 0.001, style: Object.assign({}, field, { textAlign: "right" }), value: item.accepted_quantity, disabled: busyReceipt, onChange: ev => setReceiptItem(index, "accepted_quantity", ev.target.value) })),
                  e("td", { className: "num" }, e("input", { type: "number", min: 0, step: 0.001, style: Object.assign({}, field, { textAlign: "right" }), value: item.rejected_quantity, disabled: busyReceipt, onChange: ev => setReceiptItem(index, "rejected_quantity", ev.target.value) })),
                  e("td", null, e("input", { style: field, value: item.remarks, disabled: busyReceipt, onChange: ev => setReceiptItem(index, "remarks", ev.target.value), placeholder: "Line remarks" })))))),
          e("div", { className: "row gap-2", style: { gridColumn: "1 / -1" } },
            e(Button, { variant: "primary", icon: "box", disabled: busyReceipt, onClick: submitGoodsReceipt, children: busyReceipt ? "Posting..." : "Post Goods Receipt" }),
            e(Button, { icon: "x", disabled: busyReceipt, onClick: () => setReceivingPo(null), children: "Cancel" }))))),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Open Indents", value: summary.open_indents ?? 0, icon: "cart", tone: "accent" }),
        e(Stat, { label: "POs This Month", value: summary.purchase_orders_month ?? rows.length, icon: "doc", tone: "green" }),
        e(Stat, { label: "Pending GRN", value: summary.pending_grn ?? rows.filter(r => ["approved", "partially_received"].includes(r.status)).length, icon: "box", tone: "orange" }),
        e(Stat, { label: "PO Value MTD", value: crore(summary.po_value_mtd ?? rows.reduce((s, r) => s + Number(r.total_amount || 0), 0)), icon: "rupee", tone: "violet" })),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.35fr .9fr", alignItems: "start" } },
        e(Card, { title: "Purchase Order Register", sub: "Approval and goods-receipt readiness" },
          rows.length === 0
            ? e(Empty, { icon: "cart", title: "No purchase orders", sub: "No PO records are available in your current scope." })
            : e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
              e("thead", null, e("tr", null, ["PO No.", "Material", "Vendor", "Project", "Value", "Status", "Action"].map((h, i) => e("th", { key: i, style: i === 4 ? { textAlign: "right" } : {} }, h)))),
              e("tbody", null, rows.map(po =>
                e("tr", { key: po.id },
                  e("td", null, e("div", { className: "cell-strong mono" }, po.po_number), e("div", { className: "cell-sub" }, dateText(po.po_date))),
                  e("td", null, itemSummary(po)),
                  e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: po.vendor?.name || "Vendor", sm: true }), po.vendor?.name || "Vendor")),
                  e("td", null, projectName(po)),
                  e("td", { className: "num mono cell-strong" }, money(po.total_amount)),
                  e("td", null, e(Badge, { tone: tone(po.status), dot: true }, label(po.status))),
                  e("td", null, po.can_approve
                    ? e(Button, { sm: true, variant: "primary", onClick: () => approvePo(po), children: "Approve" })
                    : po.can_receive
                      ? e(Button, { sm: true, onClick: () => openReceiptForm(po), children: "Receive" })
                      : e(Button, { sm: true, onClick: () => toast(lastWorkflowNote(po), "accent"), children: "History" }))))))),
        e(Card, { title: "Vendor Snapshot", sub: "Active vendors in scope", pad: true },
          e("div", { className: "grid", style: { gap: 10 } },
            (opts?.vendors || []).slice(0, 8).map(v =>
              e("div", { key: v.id, className: "row between", style: { padding: "8px 0", borderBottom: "1px solid var(--border)" } },
                e("div", null, e("div", { className: "cell-strong" }, v.name), e("div", { className: "cell-sub" }, `${v.vendor_code || "Vendor"} · ${label(v.vendor_type || v.category)}`)),
                e(Badge, { tone: tone(v.status), dot: true }, label(v.status))))),
          e("div", { className: "divider" }),
          e("div", { className: "row between" }, e("span", { className: "muted" }, "Active vendors"), e("span", { className: "mono cell-strong" }, summary.active_vendors ?? opts?.vendors?.filter(v => v.status === "active").length ?? 0)))),
      )
    );
  }

  Object.assign(window, { Planning, DailyProgress, Materials, Procurement });
})();
