const React = window.React;

/* Builder360 — Project Master list + full Project Profile (overrides Projects) */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, BarChart, LineChart, Gauge, HBars, PageHead, ChipSelect, Seg } = window;
  const e = React.createElement;
  const dashboardPayload = () => window.Builder360Server?.dashboard || null;
  const projectMasterOptions = () => window.Builder360Server?.project_master_options || null;
  const inventoryPricingOptions = () => window.Builder360Server?.inventory_pricing_options || null;
  const serverProjectRows = () => {
    const projects = dashboardPayload()?.projects || [];
    return projects;
  };
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const firstApiError = payload => {
    const errors = payload?.errors ? Object.values(payload.errors).flat() : [];
    return errors[0] || payload?.message || "The request could not be completed.";
  };
  async function apiJson(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      credentials: "same-origin",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrf(),
        ...(options.headers || {}),
      },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(firstApiError(payload));
    return payload;
  }
  const asCr = value => Number(value || 0) / 10000000;
  const safePercent = (part, total) => Number(total || 0) > 0 ? Math.round(Number(part || 0) / Number(total || 0) * 100) : 0;
  const statusLabel = status => String(status || "unknown").replace(/_/g, " ").replace(/\b\w/g, ch => ch.toUpperCase());
  const statusTone = status => ({
    available: "b-green",
    reserved: "b-orange",
    on_hold: "b-orange",
    booked: "b-blue",
    registered: "b-violet",
    handed_over: "b-green",
    blocked: "b-slate",
  }[status] || "b-slate");
  const money = value => "₹" + Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
  const valueOrDash = value => (value === null || value === undefined || value === "") ? "—" : value;
  const formatDate = value => value ? new Date(value + "T00:00:00").toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" }) : "Not captured in Laravel project master";
  const sqft = value => Number(value || 0) > 0 ? Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 }) + " sqft" : "Not captured in Laravel project master";
  const projectCardFromResource = project => ({
    db_id: project.id,
    id: String(project.code || project.id || "project").toLowerCase().replace(/[^a-z0-9]+/g, "_"),
    company_id: project.company_id,
    branch_id: project.branch_id,
    name: project.name,
    code: project.code,
    city: project.city,
    type: project.project_type || "Project",
    project_type: project.project_type || "residential",
    state: project.state,
    color: "#2570eb",
    status: project.status || "active",
    rera: "Pending",
    units: 0,
    sold: 0,
    progress: 0,
    budget: asCr(project.budget_amount),
    budget_amount: Number(project.budget_amount || 0),
    spent: 0,
    revenue: 0,
    collected: Number(project.collected || 0),
    outstanding: Number(project.outstanding || 0),
    revenue_amount: Number(project.revenue_amount || 0),
    collected_amount: Number(project.collected_amount || 0),
    outstanding_amount: Number(project.outstanding_amount || 0),
    collection_percent: Number(project.collection_percent || 0),
    roi: Number(project.target_roi_percent || 0),
    target_roi_percent: Number(project.target_roi_percent || 0),
    starts_on: project.starts_on || "",
    ends_on: project.ends_on || "",
    saleable_area_sqft: Number(project.saleable_area_sqft || 0),
    unit_status_counts: project.unit_status_counts,
    tower_rows: project.tower_rows,
    unit_rows: project.unit_rows,
    team_rows: project.team_rows,
    milestone_rows: project.milestone_rows,
    cost_head_rows: project.cost_head_rows,
    document_rows: project.document_rows,
    approval_rows: project.approval_rows,
    mgr: "Unassigned",
  });

  function fact(label, value) {
    return e("div", { style: { padding: "11px 0", borderBottom: "1px solid var(--border)" } },
      e("div", { className: "kpi-mini", style: { marginBottom: 3 } }, label),
      e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, value));
  }

  function ProjectCreateModal({ options, project, onClose, onCreated, toast }) {
    const isEdit = !!project;
    const companies = options?.companies || [];
    const firstCompany = companies.find(company => String(company.id) === String(project?.company_id)) || companies[0] || null;
    const firstBranch = firstCompany ? (options.branches || []).find(branch => String(branch.company_id) === String(firstCompany.id)) : null;
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      company_id: firstCompany?.id || "",
      branch_id: project?.branch_id || firstBranch?.id || "",
      code: project?.code || "",
      name: project?.name || "",
      project_type: project?.project_type || project?.type || "residential",
      city: project?.city || firstBranch?.city || "",
      state: project?.state || firstCompany?.state || firstBranch?.state || "MH",
      status: project?.status || "planned",
      budget_amount: project?.budget_amount ?? "",
      target_roi_percent: project?.target_roi_percent ?? project?.roi ?? "",
      starts_on: project?.starts_on || "",
      ends_on: project?.ends_on || "",
    });
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 12px", background: "var(--surface)", color: "var(--text)" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const set = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const branches = (options?.branches || []).filter(branch => String(branch.company_id) === String(form.company_id));
    const changeCompany = value => {
      const company = companies.find(item => String(item.id) === String(value));
      const branch = (options?.branches || []).find(item => String(item.company_id) === String(value));
      setForm(prev => ({ ...prev, company_id: value, branch_id: branch?.id || "", city: branch?.city || prev.city, state: company?.state || branch?.state || prev.state }));
    };
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      const url = isEdit ? options?.update_url_template?.replace("__PROJECT__", project?.db_id || "") : options?.store_url;
      if (!url || (!isEdit && !options?.can_create_project) || (isEdit && !options?.can_update_project)) { setError("Project master changes are not available for this role."); return; }
      try {
        setBusy(true);
        const payload = await apiJson(url, {
          method: isEdit ? "PATCH" : "POST",
          body: JSON.stringify({
            ...form,
            branch_id: form.branch_id || null,
            budget_amount: form.budget_amount === "" ? 0 : form.budget_amount,
            target_roi_percent: form.target_roi_percent === "" ? 0 : form.target_roi_percent,
            starts_on: form.starts_on || null,
            ends_on: form.ends_on || null,
          }),
        });
        const nextProject = { ...(isEdit ? project : {}), ...projectCardFromResource(payload.data) };
        if (isEdit) ["revenue", "collected", "outstanding", "revenue_amount", "collected_amount", "outstanding_amount", "collection_percent", "saleable_area_sqft", "trend_rows", "tower_rows", "unit_rows", "team_rows", "milestone_rows", "cost_head_rows", "document_rows", "approval_rows"].forEach(key => {
          if (payload.data?.[key] === undefined && project?.[key] !== undefined) nextProject[key] = project[key];
        });
        onCreated(nextProject);
        toast(payload.message || (isEdit ? "Project master updated." : "Project master created."), "green");
        onClose();
      } catch (err) {
        setError(err.message || "Project could not be created.");
      } finally {
        setBusy(false);
      }
    };
    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { maxWidth: 760 } },
        e("div", { className: "card-head" },
          e("div", null, e("div", { className: "card-title", style: { fontSize: 18 } }, isEdit ? "Edit Project" : "Add Project"), e("div", { className: "card-sub" }, isEdit ? "Updates the scoped Laravel project master with validation, audit and notification." : "Creates a scoped Laravel project master with validation, audit and notification.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          error && e("div", { className: "sys-note", style: { marginBottom: 12, color: "var(--red)" } }, e(Icon, { name: "alert", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), error),
          e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 } },
            e("label", { style: label }, "Company", e("select", { required: true, style: field, value: form.company_id, disabled: busy, onChange: ev => changeCompany(ev.target.value) }, companies.map(company => e("option", { key: company.id, value: company.id }, company.label || company.name)))),
            e("label", { style: label }, "Branch", e("select", { style: field, value: form.branch_id, disabled: busy || !branches.length, onChange: ev => set("branch_id", ev.target.value) }, e("option", { value: "" }, "No branch"), branches.map(branch => e("option", { key: branch.id, value: branch.id }, branch.label || branch.name)))),
            e("label", { style: label }, "Project code", e("input", { required: true, pattern: "[A-Z0-9-]+", maxLength: 32, style: field, value: form.code, disabled: busy, onChange: ev => set("code", ev.target.value.toUpperCase()), placeholder: "SKY-PUN-2" })),
            e("label", { style: label }, "Project name", e("input", { required: true, maxLength: 255, style: field, value: form.name, disabled: busy, onChange: ev => set("name", ev.target.value), placeholder: "Project name" })),
            e("label", { style: label }, "Project type", e("select", { required: true, style: field, value: form.project_type, disabled: busy, onChange: ev => set("project_type", ev.target.value) }, (options?.project_types || []).map(type => e("option", { key: type.value, value: type.value }, type.label)))),
            e("label", { style: label }, "Status", e("select", { required: true, style: field, value: form.status, disabled: busy, onChange: ev => set("status", ev.target.value) }, (options?.statuses || []).map(status => e("option", { key: status.value, value: status.value }, status.label)))),
            e("label", { style: label }, "City", e("input", { required: true, maxLength: 120, style: field, value: form.city, disabled: busy, onChange: ev => set("city", ev.target.value) })),
            e("label", { style: label }, "State code", e("input", { required: true, minLength: 2, maxLength: 2, style: field, value: form.state, disabled: busy, onChange: ev => set("state", ev.target.value.toUpperCase()) })),
            e("label", { style: label }, "Budget amount", e("input", { type: "number", min: 0, step: "0.01", style: field, value: form.budget_amount, disabled: busy, onChange: ev => set("budget_amount", ev.target.value), placeholder: "0.00" })),
            e("label", { style: label }, "Target ROI %", e("input", { type: "number", min: 0, max: 999.99, step: "0.01", style: field, value: form.target_roi_percent, disabled: busy, onChange: ev => set("target_roi_percent", ev.target.value), placeholder: "18.50" })),
            e("label", { style: label }, "Starts on", e("input", { type: "date", style: field, value: form.starts_on, disabled: busy, onChange: ev => set("starts_on", ev.target.value) })),
            e("label", { style: label }, "Ends on", e("input", { type: "date", min: form.starts_on || undefined, style: field, value: form.ends_on, disabled: busy, onChange: ev => set("ends_on", ev.target.value) }))),
          e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, marginTop: 16, gap: 12 } },
            e("div", { className: "muted", style: { fontSize: 12 } }, "Project code must be unique. Branch must belong to the selected company."),
            e("div", { className: "row gap-2" }, e(Button, { type: "button", disabled: busy, onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "check", type: "submit", disabled: busy || (isEdit ? !options?.can_update_project : !options?.can_create_project), children: busy ? (isEdit ? "Saving..." : "Creating...") : (isEdit ? "Save Changes" : "Create Project") }))))));
  }

  function ProjectTeamAssignmentModal({ options, project, existingTeam, onClose, onAssigned, toast }) {
    const users = (options?.assignable_users || []).filter(user => !project?.company_id || String(user.company_id) === String(project.company_id));
    const existingUserIds = new Set((existingTeam || []).filter(row => row.status !== "revoked").map(row => String(row.user_id)));
    const availableUsers = users.filter(user => !existingUserIds.has(String(user.id)));
    const firstUser = availableUsers[0] || users[0] || null;
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      user_id: firstUser?.id || "",
      employee_id: firstUser?.employee_id || "",
      role_label: firstUser?.designation || firstUser?.role || "Project Team Member",
      department: firstUser?.department || "",
      access_level: "contribute",
      starts_on: "",
      ends_on: "",
      notes: "",
    });
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 12px", background: "var(--surface)", color: "var(--text)" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const set = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const selectUser = value => {
      const selected = users.find(user => String(user.id) === String(value));
      setForm(prev => ({
        ...prev,
        user_id: value,
        employee_id: selected?.employee_id || "",
        role_label: prev.role_label === "Project Team Member" || !prev.role_label ? (selected?.designation || selected?.role || "Project Team Member") : prev.role_label,
        department: selected?.department || prev.department || "",
      }));
    };
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      const url = options?.team_assignment_store_url_template?.replace("__PROJECT__", project?.db_id || "");
      if (!url || !options?.can_manage_project_team || !project?.db_id) { setError("Project team assignment is not available for this role."); return; }
      try {
        setBusy(true);
        const payload = await apiJson(url, {
          method: "POST",
          body: JSON.stringify({
            ...form,
            employee_id: form.employee_id || null,
            starts_on: form.starts_on || null,
            ends_on: form.ends_on || null,
          }),
        });
        onAssigned(payload.data);
        toast(payload.message || "Project team member assigned.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Project team assignment failed.");
      } finally {
        setBusy(false);
      }
    };
    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { maxWidth: 680 } },
        e("div", { className: "card-head" },
          e("div", null, e("div", { className: "card-title", style: { fontSize: 18 } }, "Assign Project Team Member"), e("div", { className: "card-sub" }, "Creates a governed project-team assignment with scope validation, audit and notification.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          error && e("div", { className: "sys-note", style: { marginBottom: 12, color: "var(--red)" } }, e(Icon, { name: "alert", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), error),
          !availableUsers.length && e("div", { className: "sys-note", style: { marginBottom: 12 } }, e(Icon, { name: "shield", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), "All currently loaded company users are already assigned or no assignable users are available."),
          e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 } },
            e("label", { style: label }, "User", e("select", { required: true, style: field, value: form.user_id, disabled: busy || !availableUsers.length, onChange: ev => selectUser(ev.target.value) }, availableUsers.map(user => e("option", { key: user.id, value: user.id }, user.label || user.name)))),
            e("label", { style: label }, "Access level", e("select", { required: true, style: field, value: form.access_level, disabled: busy, onChange: ev => set("access_level", ev.target.value) }, (options?.team_access_levels || []).map(level => e("option", { key: level.value, value: level.value }, level.label)))),
            e("label", { style: label }, "Project role", e("input", { required: true, maxLength: 120, style: field, value: form.role_label, disabled: busy, onChange: ev => set("role_label", ev.target.value), placeholder: "Project Manager / Site Engineer / CRM Owner" })),
            e("label", { style: label }, "Department", e("input", { maxLength: 120, style: field, value: form.department, disabled: busy, onChange: ev => set("department", ev.target.value), placeholder: "Construction / Sales / Finance" })),
            e("label", { style: label }, "Starts on", e("input", { type: "date", style: field, value: form.starts_on, disabled: busy, onChange: ev => set("starts_on", ev.target.value) })),
            e("label", { style: label }, "Ends on", e("input", { type: "date", min: form.starts_on || undefined, style: field, value: form.ends_on, disabled: busy, onChange: ev => set("ends_on", ev.target.value) })),
            e("label", { style: { ...label, gridColumn: "1 / -1" } }, "Notes", e("textarea", { maxLength: 2000, style: { ...field, minHeight: 76 }, value: form.notes, disabled: busy, onChange: ev => set("notes", ev.target.value), placeholder: "Scope, responsibility or approval note." }))),
          e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, marginTop: 16, gap: 12 } },
            e("div", { className: "muted", style: { fontSize: 12 } }, "Only active users from the selected project company can be assigned."),
            e("div", { className: "row gap-2" }, e(Button, { type: "button", disabled: busy, onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "check", type: "submit", disabled: busy || !options?.can_manage_project_team || !availableUsers.length, children: busy ? "Assigning..." : "Assign Member" }))))));
  }

  // -------- Project Profile --------
  function ProjectProfile({ project: p, onBack, onUpdated, toast }) {
    const options = projectMasterOptions();
    const inventoryOptions = inventoryPricingOptions();
    const [tab, setTab] = React.useState("Overview");
    const [editing, setEditing] = React.useState(false);
    const [assigning, setAssigning] = React.useState(false);
    const [teamBusyId, setTeamBusyId] = React.useState(null);
    const tabs = ["Overview", "Towers & Units", "Team", "Budget", "Timeline", "Documents", "Approval Hierarchy"];
    const bUsed = safePercent(p.spent, p.budget);
    const salesP = safePercent(p.sold, p.units);
    const revenueMetrics = [
      ["Revenue booked", "₹" + Number(p.revenue || 0).toLocaleString("en-IN") + " Cr", "var(--green)"],
      ["Collected", "₹" + Number(p.collected || 0).toLocaleString("en-IN") + " Cr", "var(--accent)"],
      ["Outstanding", "₹" + Number(p.outstanding || 0).toLocaleString("en-IN") + " Cr", "var(--orange)"],
    ];
    const trendRows = Array.isArray(p.trend_rows) ? p.trend_rows : [];
    const trendLabels = trendRows.map(row => row.label || row.period || row.month || row.quarter).filter(Boolean);
    const constructionTrend = trendRows.map(row => Number(row.construction_percent ?? row.progress_percent ?? row.progress ?? 0));
    const salesTrend = trendRows.map(row => Number(row.sales_percent ?? row.sold_percent ?? row.sales ?? 0));

    const team = Array.isArray(p.team_rows) ? p.team_rows : [];
    const setTeamRows = rows => onUpdated && onUpdated({ ...p, team_rows: rows });
    const revokeTeamAssignment = async assignment => {
      const url = options?.team_assignment_revoke_url_template?.replace("__PROJECT__", p.db_id || "").replace("__ASSIGNMENT__", assignment.id || "");
      if (!url || !options?.can_manage_project_team || !assignment?.id) { toast("Project team revoke is not available for this role.", "orange"); return; }
      try {
        setTeamBusyId(assignment.id);
        const payload = await apiJson(url, { method: "DELETE" });
        setTeamRows(team.filter(row => String(row.id) !== String(payload.data?.id || assignment.id)));
        toast(payload.message || "Project team member assignment revoked.", "green");
      } catch (err) {
        toast(err.message || "Project team revoke failed.", "red");
      } finally {
        setTeamBusyId(null);
      }
    };
    const towers = Array.isArray(p.tower_rows) ? p.tower_rows : [];
    const unitRows = Array.isArray(p.unit_rows) ? p.unit_rows : [];
    const costHeads = Array.isArray(p.cost_head_rows) ? p.cost_head_rows : [];
    const projectMilestones = Array.isArray(p.milestone_rows) ? p.milestone_rows : [];
    const documentRows = Array.isArray(p.document_rows) ? p.document_rows.map(d => ({
      title: d.name || d.title || d.document_name || "Document",
      meta: (d.type || d.category || d.category_name || "Document") + " · " + (d.version || d.revision || d.status || "Current"),
      download_url: d.download_url || null,
    })) : [];
    const approvalRows = Array.isArray(p.approval_rows) ? p.approval_rows.map(r => [r.workflow || r.approval_type || "Approval", r.initiator || r.requested_by || "Workflow", r.first_approver || r.responsible_user?.name || "Approver", r.final_approver || r.verified_by?.name || "Final approver", r.escalation || r.required_for || r.status || "Configured"]) : [];
    const tn = s => ({ Completed: "b-green", "In Progress": "b-blue", Pending: "b-slate" }[s] || "b-slate");

    const overview = e("div", { className: "grid", style: { gridTemplateColumns: "1fr 340px", alignItems: "start" } },
      e("div", { className: "grid", style: { gap: 16 } },
        e("div", { className: "grid g-4" },
          e(Stat, { label: "Total Units", value: p.units, icon: "layers", tone: "accent" }),
          e(Stat, { label: "Sold", value: salesP, unit: "%", icon: "tag", tone: "green", sub: p.sold + " units" }),
          e(Stat, { label: "Construction", value: p.progress, unit: "%", icon: "hardhat", tone: "orange" }),
          e(Stat, { label: "Project ROI", value: p.roi, unit: "%", icon: "trend", tone: "violet" })),
        e(Card, { title: "Budget Utilization", sub: "₹ Cr", pad: true },
          e("div", { className: "row between", style: { marginBottom: 10 } },
            e("span", { className: "mono", style: { fontWeight: 800, fontSize: 22 } }, "₹" + p.spent + " Cr"),
            e("span", { className: "muted" }, "of ₹" + p.budget + " Cr · " + bUsed + "% used")),
          e("div", { className: "bar", style: { width: "100%", height: 12 } }, e("i", { style: { width: bUsed + "%", background: bUsed > 85 ? "var(--red)" : "var(--accent)" } })),
          e("div", { className: "divider" }),
          e("div", { className: "grid g-3" },
            revenueMetrics.map((r, i) =>
              e("div", { key: i }, e("div", { className: "kpi-mini" }, r[0]), e("div", { className: "mono", style: { fontWeight: 800, fontSize: 16, color: r[2], marginTop: 3 } }, r[1])))),
        ),
        e(Card, { title: "Sales & Construction Trend", sub: "cumulative %", pad: true },
          trendLabels.length
            ? [
              e(LineChart, { key: "chart", height: 160, labels: trendLabels, series: [
                { data: constructionTrend, color: "var(--accent)", fill: true },
                { data: salesTrend, color: "var(--green)" }] }),
              e("div", { key: "legend", className: "row gap-4", style: { justifyContent: "center", marginTop: 8 } },
                e("span", { className: "legend-row" }, e("i", { className: "lk", style: { background: "var(--accent)" } }), "Construction"),
                e("span", { className: "legend-row" }, e("i", { className: "lk", style: { background: "var(--green)" } }), "Sales"))]
            : e("div", { className: "empty" }, "Trend API required; no local quarterly sales or construction series is fabricated.")),
      ),
      e(Card, { title: "Project Facts", pad: true },
        fact("Location", valueOrDash(p.city)), fact("Project Code", valueOrDash(p.code)), fact("Type", valueOrDash(p.type)),
        fact("RERA Number", valueOrDash(p.rera)), fact("Start Date", formatDate(p.starts_on)), fact("Expected Completion", formatDate(p.ends_on)),
        fact("Land Area", "Not captured in Laravel project master"), fact("Saleable Area", sqft(p.saleable_area_sqft)),
        e("div", { style: { paddingTop: 11 } }, e("div", { className: "kpi-mini", style: { marginBottom: 6 } }, "Status"), e(Badge, { tone: p.status === "Possession" ? "b-green" : p.status === "Pre-launch" ? "b-violet" : "b-blue", dot: true }, p.status))),
    );

    const towersTab = e("div", { className: "grid", style: { gap: 16 } },
      towers.length
        ? e("div", { className: "grid g-4" }, towers.map(t => {
          const sold = Number(t.sold || 0);
          const units = Number(t.units || 0);
          const available = Number(t.available || 0);
          return e("div", { key: t.tower, className: "card card-pad" },
          e("div", { className: "row between", style: { marginBottom: 12 } },
            e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 17 } }, "Tower " + t.tower),
            e("span", { className: "tag" }, Number(t.floors || 0) + " floors")),
          e("div", { className: "row between", style: { marginBottom: 6 } }, e("span", { className: "muted", style: { fontSize: 12.5 } }, "Sold / registered"), e("span", { className: "mono", style: { fontWeight: 700 } }, sold + " / " + units)),
          e("div", { className: "bar", style: { width: "100%" } }, e("i", { style: { width: safePercent(sold, units) + "%", background: "var(--green)" } })),
          e("div", { className: "divider", style: { margin: "12px 0" } }),
          e("div", { className: "row between" },
            e("span", { className: "badge b-green" }, available + " available"),
            e("span", { className: "cell-sub" }, money(t.inventory_value) + " inventory")));
        }))
        : e("div", { className: "card card-pad" },
          e("div", { className: "cell-strong" }, "No persisted unit rows"),
          e("div", { className: "cell-sub", style: { marginTop: 4 } }, "This project profile has no Laravel project_units records in the current user scope.")),
      e(Card, { title: "Unit Availability", sub: unitRows.length ? "First " + unitRows.length + " scoped units from Laravel" : "No unit rows available", action: e(Button, { sm: true, icon: "layers", variant: "primary", onClick: () => { window.location.hash = "#inventory"; toast("Opening Unit Inventory for full project/tower/floor grid.", "accent"); }, children: "Open Inventory" }) },
        e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
          e("thead", null, e("tr", null, ["Unit", "Tower/Floor", "Type", "Area", "Price", "Status"].map((h, i) => e("th", { key: i, style: i === 4 ? { textAlign: "right" } : {} }, h)))),
          e("tbody", null, unitRows.length ? unitRows.map(unit =>
            e("tr", { key: unit.id || unit.unit_code },
              e("td", { className: "cell-strong mono" }, unit.unit_code),
              e("td", null, "Tower " + (unit.tower || "—") + " · Floor " + (unit.floor ?? "—") + " · " + (unit.unit_number || "—")),
              e("td", null, unit.unit_type || "—"),
              e("td", { className: "mono" }, Number(unit.saleable_area_sqft || 0).toLocaleString("en-IN") + " sqft"),
              e("td", { className: "num mono cell-strong" }, money(unit.total_price)),
              e("td", null, e(Badge, { tone: statusTone(unit.status), dot: true }, statusLabel(unit.status)))))
            : [e("tr", { key: "empty" }, e("td", { colSpan: 6 }, e("div", { className: "empty" }, "No persisted units available for this project.")))]),
        ))),
    );

    const teamTab = e(Card, { title: "Project Team", sub: team.length + " active member" + (team.length === 1 ? "" : "s") + " mapped", action: e(Button, { sm: true, icon: "plus", variant: "primary", disabled: !options?.can_manage_project_team || !p.db_id, onClick: () => options?.can_manage_project_team && p.db_id ? setAssigning(true) : toast("Project team assignment is restricted to project administrators.", "orange"), children: "Assign Member" }) },
      e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
        e("thead", null, e("tr", null, ["Member", "Role", "Department", "Access", ""].map((h, i) => e("th", { key: i }, h)))),
        e("tbody", null, team.length ? team.map((m, i) =>
          e("tr", { key: i },
            e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: m.name, sm: true, color: m.color }), e("span", { className: "cell-strong" }, m.name), m.email && e("span", { className: "cell-sub" }, m.email))),
            e("td", null, m.role || m.role_label), e("td", null, e("span", { className: "tag" }, m.dept || m.department || "—")),
            e("td", null, e(Badge, { tone: m.access_level === "approve" ? "b-violet" : m.access_level === "manage" ? "b-blue" : "b-green", dot: true }, statusLabel(m.access_level || m.access || "contribute"))),
            e("td", null, e("button", { className: "btn btn-sm btn-ghost", disabled: teamBusyId === m.id || !options?.can_manage_project_team, onClick: () => revokeTeamAssignment(m), title: options?.can_manage_project_team ? "Revoke assignment" : "Project-team revoke is restricted.", style: { color: options?.can_manage_project_team ? "var(--red)" : "var(--text-3)" } }, teamBusyId === m.id ? "Revoking..." : "Revoke"))))
          : [e("tr", { key: "empty" }, e("td", { colSpan: 5 }, e("div", { className: "empty" }, "No governed project-team mappings are included in this project payload.")))]),
      )));

    const budgetTab = e("div", { className: "grid", style: { gridTemplateColumns: "1.5fr 1fr", alignItems: "start" } },
      e(Card, { title: "Cost Head — Budget vs Actual", sub: "₹ Cr" },
        e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
          e("thead", null, e("tr", null, ["Cost Head", "Budget", "Actual", "Used"].map((h, i) => e("th", { key: i, style: i > 0 && i < 3 ? { textAlign: "right" } : {} }, h)))),
          e("tbody", null, costHeads.length ? costHeads.map((c, i) => {
            const used = safePercent(c.actual, c.budget);
            return e("tr", { key: i },
              e("td", null, e("div", { className: "cell-user" }, e("i", { style: { width: 10, height: 10, borderRadius: 3, background: c.color } }), e("span", { className: "cell-strong" }, c.head))),
              e("td", { className: "num mono" }, "₹" + c.budget), e("td", { className: "num mono cell-strong" }, "₹" + c.actual),
              e("td", null, e(ProgCell, { value: used })));
          }) : [e("tr", { key: "empty" }, e("td", { colSpan: 4 }, e("div", { className: "empty" }, "No governed cost-head rows are included in this project payload.")))]),
        ))),
      e(Card, { title: "Spend Distribution", pad: true },
        costHeads.length
          ? e("div", { className: "center" }, e(Donut, { size: 160, thickness: 22, data: costHeads.map(c => ({ value: c.actual, color: c.color })),
            center: e("div", null, e("div", { className: "mono", style: { fontWeight: 800, fontSize: 19, fontFamily: "var(--font-display)" } }, "₹" + p.spent), e("div", { className: "kpi-mini" }, "Cr spent")) }))
          : e("div", { className: "empty" }, "Spend distribution requires governed project cost-head records.")),
    );

    const timelineTab = e(Card, { title: "Project Timeline", sub: "milestones · planned vs actual" },
      e("div", { style: { padding: "8px 18px 18px" } }, projectMilestones.length ? projectMilestones.map((m, i) =>
        e("div", { key: i, style: { display: "flex", gap: 14, paddingBottom: i < projectMilestones.length - 1 ? 20 : 0, position: "relative" } },
          i < projectMilestones.length - 1 && e("div", { style: { position: "absolute", left: 13, top: 28, bottom: 0, width: 2, background: "var(--border)" } }),
          e("div", { style: { width: 28, height: 28, borderRadius: 99, display: "grid", placeItems: "center", flex: "0 0 28px", zIndex: 1,
            background: m.st === "Completed" ? "var(--green)" : m.st === "In Progress" ? "var(--accent)" : "var(--surface-3)", color: m.st === "Pending" ? "var(--text-3)" : "#fff" } },
            m.st === "Completed" ? e(Icon, { name: "check", size: 14 }) : e("span", { style: { fontWeight: 800, fontSize: 11 } }, i + 1)),
          e("div", { style: { flex: 1 } },
            e("div", { className: "row between" }, e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, m.m), e(Badge, { tone: tn(m.st) }, m.st)),
            e("div", { className: "cell-sub", style: { marginTop: 3 } }, "Planned " + m.plan + (m.actual !== "—" ? " · Actual " + m.actual : "")),
            m.st === "In Progress" && e("div", { className: "row gap-2", style: { marginTop: 7 } }, e(Bar, { value: m.prog, w: 160 }), e("span", { className: "pv" }, m.prog + "%")))))
        : e("div", { className: "empty" }, "No governed project milestone rows are included in this project payload.")),
    );

    const docsTab = e(Card, { title: "Project Documents", sub: "version-controlled", action: e(Button, { sm: true, icon: "upload", variant: "primary", onClick: () => { window.location.hash = "#documents"; toast("Opening governed Document Management for uploads, versions, approvals and downloads.", "accent"); }, children: "Open Documents" }) },
      documentRows.length ? documentRows.map((d, i) =>
        e("div", { key: i, className: "row gap-3", style: { padding: "12px 16px", borderBottom: i < 5 ? "1px solid var(--border)" : "none" } },
          e("div", { style: { width: 34, height: 34, borderRadius: 9, background: "var(--surface-3)", color: "var(--accent)", display: "grid", placeItems: "center", flex: "0 0 34px" } }, e(Icon, { name: "doc", size: 17 })),
          e("div", { style: { flex: 1 } }, e("div", { style: { fontWeight: 700, fontSize: 13 } }, d.title), e("div", { className: "cell-sub" }, d.meta)),
          d.download_url
            ? e("a", { className: "btn btn-sm btn-ghost", href: d.download_url, target: "_blank", rel: "noreferrer", style: { color: "var(--accent)" } }, e(Icon, { name: "download", size: 14 }), "Download")
            : e("button", { className: "btn btn-sm btn-ghost", disabled: true, title: "Download URL is not available for this scoped document.", style: { color: "var(--text-3)", cursor: "not-allowed" } }, e(Icon, { name: "download", size: 14 }), "Restricted")))
        : e("div", { className: "empty" }, "No project document rows are included in this project payload. Use governed Document Management for uploads, versions, permissions and expiry tracking."));

    const apprTab = e(Card, { title: "Approval Hierarchy", sub: "configurable per workflow for this project" },
      e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
        e("thead", null, e("tr", null, ["Workflow", "Initiator", "First Approver", "Final Approver", "Escalation"].map((h, i) => e("th", { key: i }, h)))),
        e("tbody", null, approvalRows.length ? approvalRows.map((r, i) => e("tr", { key: i },
          e("td", { className: "cell-strong" }, r[0]),
          ...r.slice(1, 4).map((x, j) => e("td", { key: j }, e("div", { className: "cell-user" }, e(Avatar, { name: x, sm: true, size: 24 }), e("span", { style: { fontSize: 12.5 } }, x)))),
          e("td", null, e(Badge, { tone: r[4] === "Always" ? "b-orange" : "b-slate" }, r[4]))))
          : [e("tr", { key: "empty" }, e("td", { colSpan: 5 }, e("div", { className: "empty" }, "No governed approval hierarchy rows are included in this project payload.")))]),
      )));

    const tabContent = { "Overview": overview, "Towers & Units": towersTab, "Team": teamTab, "Budget": budgetTab, "Timeline": timelineTab, "Documents": docsTab, "Approval Hierarchy": apprTab }[tab];

    return e("div", { className: "page page-wide" },
      e("div", { className: "crumbs" }, e("span", { style: { cursor: "pointer", color: "var(--accent)", fontWeight: 700 }, onClick: onBack }, "Project Master"), e("span", { className: "sep" }, "/"), e("span", { style: { color: "var(--text-2)" } }, p.name)),
      e("div", { className: "card", style: { overflow: "hidden", marginBottom: 20 } },
        e("div", { style: { height: 8, background: p.color } }),
        e("div", { className: "card-pad" }, e("div", { className: "row between", style: { flexWrap: "wrap", gap: 14 } },
          e("div", { className: "row gap-4" },
            e("button", { className: "icon-btn", onClick: onBack }, e(Icon, { name: "chevL", size: 18 })),
            e("div", { style: { width: 52, height: 52, borderRadius: 14, background: "linear-gradient(135deg," + p.color + "," + p.color + "bb)", display: "grid", placeItems: "center", color: "#fff" } }, e(Icon, { name: "building", size: 26 })),
            e("div", null, e("h1", { className: "page-title", style: { fontSize: 22 } }, p.name),
              e("div", { className: "muted", style: { fontWeight: 600 } }, e(Icon, { name: "pin", size: 12, style: { verticalAlign: -1 } }), " " + p.city + " · " + p.code))),
          e("div", { className: "head-actions" },
            e(Badge, { tone: p.status === "Possession" ? "b-green" : p.status === "Pre-launch" ? "b-violet" : "b-blue", dot: true }, p.status),
            e(Button, { icon: "download", disabled: !inventoryOptions?.can_export_project_cost_roi || !inventoryOptions?.project_cost_roi_export_url, onClick: () => inventoryOptions?.can_export_project_cost_roi && inventoryOptions?.project_cost_roi_export_url ? window.location.assign(inventoryOptions.project_cost_roi_export_url) : toast("Project cost/ROI export is not available for this role.", "orange"), children: "Cost/ROI Export" }),
            e(Button, { variant: "primary", icon: "sliders", disabled: !options?.can_update_project || !p.db_id, onClick: () => options?.can_update_project && p.db_id ? setEditing(true) : toast("Project editing is restricted to system administrators.", "orange"), children: "Edit Project" })))),
      ),
      e("div", { className: "tabs", style: { overflowX: "auto" } }, tabs.map(t => e("div", { key: t, className: "tab " + (tab === t ? "on" : ""), onClick: () => setTab(t) }, t))),
      tabContent,
      editing && e(ProjectCreateModal, { options, project: p, onClose: () => setEditing(false), onCreated: project => onUpdated && onUpdated(project), toast }),
      assigning && e(ProjectTeamAssignmentModal, { options, project: p, existingTeam: team, onClose: () => setAssigning(false), onAssigned: assignment => setTeamRows([assignment, ...team]), toast }),
    );
  }

  // -------- Projects list (stateful, overrides earlier) --------
  function Projects({ toast }) {
    const options = projectMasterOptions();
    const [projects, setProjects] = React.useState(serverProjectRows());
    const [creating, setCreating] = React.useState(false);
    const [sel, setSel] = React.useState(null);
    React.useEffect(() => setProjects(serverProjectRows()), [dashboardPayload()?.generated_at]);
    if (sel) return e(ProjectProfile, { project: sel, onBack: () => setSel(null), onUpdated: project => {
      setProjects(prev => prev.map(row => ((row.db_id && project.db_id) ? String(row.db_id) === String(project.db_id) : row.id === project.id) ? { ...row, ...project, unit_status_counts: project.unit_status_counts || row.unit_status_counts, tower_rows: project.tower_rows || row.tower_rows, unit_rows: project.unit_rows || row.unit_rows, team_rows: project.team_rows || row.team_rows } : row));
      setSel(current => ({ ...current, ...project, unit_status_counts: project.unit_status_counts || current?.unit_status_counts, tower_rows: project.tower_rows || current?.tower_rows, unit_rows: project.unit_rows || current?.unit_rows, team_rows: project.team_rows || current?.team_rows }));
    }, toast });
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Projects & Inventory", "Project Master"], title: "Project Master", sub: "All projects, sites and phases — single source of truth. Click a project to open its profile.",
        actions: [
          e("span", { key: 0, className: "badge " + (dashboardPayload()?.projects?.length ? "b-blue" : "b-orange"), style: { height: 28 } }, e(Icon, { name: dashboardPayload()?.projects?.length ? "database" : "alert", size: 13 }), dashboardPayload()?.projects?.length ? "DB-backed projects" : "Project API required"),
          e(ChipSelect, { key: 1, value: "All Status" }),
          e(Button, { key: 2, icon: "plus", variant: "primary", disabled: !options?.can_create_project, onClick: () => options?.can_create_project ? setCreating(true) : toast("Project creation is restricted to system administrators.", "orange"), children: "Add Project" })
        ] }),
      projects.length ? e("div", { className: "grid g-3" }, projects.map(p => {
        const bUsed = safePercent(p.spent, p.budget);
        const soldPercent = safePercent(p.sold, p.units);
        return e("div", { key: p.id, className: "card", style: { overflow: "hidden", cursor: "pointer" }, onClick: () => setSel(p) },
          e("div", { style: { height: 7, background: p.color } }),
          e("div", { className: "card-pad" },
            e("div", { className: "row between", style: { marginBottom: 12 } },
              e("div", null, e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 16.5 } }, p.name),
                e("div", { className: "cell-sub", style: { marginTop: 2 } }, e(Icon, { name: "pin", size: 11, style: { verticalAlign: -1, marginRight: 3 } }), p.city)),
              e(Badge, { tone: p.status === "Possession" ? "b-green" : p.status === "Pre-launch" ? "b-violet" : "b-blue", dot: true }, p.status)),
            e("div", { className: "row gap-2", style: { marginBottom: 14, flexWrap: "wrap" } },
              e("span", { className: "tag" }, p.type), e("span", { className: "tag" }, p.units + " units"), e("span", { className: "tag mono" }, "RERA ✓")),
            e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 12 } },
              [["Construction", Number(p.progress || 0) + "%", Number(p.progress || 0)], ["Sold", soldPercent + "%", soldPercent]].map((r, i) =>
                e("div", { key: i }, e("div", { className: "kpi-mini", style: { marginBottom: 4 } }, r[0]),
                  e("div", { className: "row gap-2" }, e(Bar, { value: r[2], w: 70 }), e("span", { className: "mono", style: { fontSize: 12, fontWeight: 700 } }, r[1]))))),
            e("div", { className: "divider", style: { margin: "12px 0" } }),
            e("div", { className: "row between" },
              e("div", null, e("div", { className: "kpi-mini" }, "Budget used"), e("div", { className: "mono", style: { fontWeight: 800, fontSize: 15, color: bUsed > 85 ? "var(--red)" : "var(--text)" } }, "₹" + p.spent + " / " + p.budget + " Cr")),
              e("div", { style: { textAlign: "right" } }, e("div", { className: "kpi-mini" }, "ROI"), e("div", { className: "mono", style: { fontWeight: 800, fontSize: 15, color: "var(--green)" } }, p.roi + "%"))),
            e("div", { className: "row gap-2", style: { marginTop: 14 } }, e(Avatar, { name: p.mgr, sm: true }), e("span", { className: "cell-sub" }, "PM · " + p.mgr))));
      })) : e(Card, { title: "No project master records loaded", sub: "Project Master requires Laravel dashboard/project payload. Local prototype projects are not shown.", pad: true },
        e("div", { className: "muted" }, "Create an authorized project master record or check the current user's company/project scope.")),
      creating && e(ProjectCreateModal, { options, onClose: () => setCreating(false), onCreated: project => setProjects(prev => [project, ...prev]), toast }),
    );
  }

  Object.assign(window, { Projects, ProjectProfile });
})();
