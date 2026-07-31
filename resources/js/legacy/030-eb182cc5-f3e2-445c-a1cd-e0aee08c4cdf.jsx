const React = window.React;

/* ============================================================
   Builder360 — Tasks: Templates, Activity Center, Reports,
   Analytics, Settings  → window.TMTemplates ... etc
   ============================================================ */
(function () {
  const { Icon, Avatar, Badge, Button, Card, Stat, Donut, BarChart, LineChart, HBars, Empty } = window;
  const e = React.createElement;
  const TM = window.TM;
  const { U, PriPill, StatusPill, AvatarStack } = window.TMUI;
  const safeTasks = (tasks) => Array.isArray(tasks) ? tasks : [];
  const dayMs = 86400000;
  const parseDate = (value) => {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
  };
  const taskCreatedAt = (task) => parseDate(task?.server?.created_at) || parseDate(task?.created_at);
  const taskCompletedAt = (task) => parseDate(task?.server?.completed_at) || parseDate(task?.complete);
  const taskAgeDays = (task) => {
    const created = taskCreatedAt(task);
    if (!created) return 0;
    return Math.max(0, Math.floor((Date.now() - created.getTime()) / dayMs));
  };
  const isCompletedTask = (task) => ["completed", "archived"].includes(task?.status);
  const isOpenTask = (task) => !isCompletedTask(task) && task?.status !== "cancelled";
  const avg = (values) => values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : 0;
  const pct = (num, den) => den > 0 ? Math.round(num / den * 100) : 0;
  const taskOptions = () => window.Builder360Server?.collaboration_task_options || {};
  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const firstApiError = (payload) => {
    const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
    return errors[0] || payload?.message || "The request could not be completed.";
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
  const slug = (value) => String(value || "template").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 60) || "template";
  const uniqById = (rows) => {
    const seen = new Set();
    return rows.filter(row => {
      if (!row?.id || seen.has(row.id)) return false;
      seen.add(row.id);
      return true;
    });
  };

  /* ============ TEMPLATES ============ */
  function TaskTemplateDraftModal({ options, existingTemplates, onClose, onCreated, toast }) {
    const [form, setForm] = React.useState({ name: "", cat: "Operations", desc: "", icon: "template", color: "#2570eb", stepsText: "Review requirements\nAssign owner\nTrack completion" });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 12px", background: "var(--surface)", color: "var(--text)" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const set = key => ev => setForm(prev => ({ ...prev, [key]: ev.target.value }));
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      const name = form.name.trim();
      const steps = form.stepsText.split(/\r?\n/).map(step => step.trim()).filter(Boolean);
      if (!options.can_manage_settings || !options.system_settings_store_url) { setError("You need System Settings access to create task-template drafts."); return; }
      if (!name) { setError("Template name is required."); return; }
      if (!steps.length) { setError("Add at least one task step."); return; }
      if (steps.length > 25) { setError("A task template may contain a maximum of 25 steps."); return; }
      const activeValue = options.task_settings?.value || {};
      const template = { id: slug(name) + "-" + Date.now().toString(36), name, cat: form.cat.trim() || "Operations", desc: form.desc.trim(), icon: form.icon.trim() || "template", color: form.color || "#2570eb", steps, uses: 0, source: "system_settings_draft" };
      const templates = uniqById([...(existingTemplates || []), template]);
      try {
        setBusy(true);
        const body = await apiJson(options.system_settings_store_url, { method: "POST", body: JSON.stringify({ setting_group: "collaboration", setting_key: options.task_settings_key || "collaboration.task_settings", label: "Collaboration Task Settings", description: "Configurable task workflow, notification, archival controls and reusable task templates used by Task Management.", value_type: "object", value: { ...activeValue, templates }, effective_from: new Date().toISOString().slice(0, 10), metadata: { source: "task_template_builder", template_id: template.id, template_name: template.name } }) });
        onCreated && onCreated(template, body.data);
        toast((body.message || "Task template draft created.") + " Approve it from Administration → System Settings before it becomes active.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Task template draft could not be created.");
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: onClose, style: { position: "fixed", inset: 0, zIndex: 1200, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(620px,94vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } }, e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, "New task template"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates an approval-controlled System Settings draft.")), e("button", { type: "button", className: "icon-btn", onClick: onClose, disabled: busy }, e(Icon, { name: "x" }))),
        error && e("div", { className: "sys-note", style: { marginBottom: 12, color: "var(--red)" } }, e(Icon, { name: "alert", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), error),
        e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 12 } }, e("label", { style: label }, "Template name", e("input", { style: field, value: form.name, onChange: set("name"), required: true, maxLength: 120, autoFocus: true, disabled: busy })), e("label", { style: label }, "Category", e("input", { style: field, value: form.cat, onChange: set("cat"), required: true, maxLength: 80, disabled: busy })), e("label", { style: label }, "Icon", e("input", { style: field, value: form.icon, onChange: set("icon"), maxLength: 40, disabled: busy })), e("label", { style: label }, "Accent color", e("input", { style: field, type: "color", value: form.color, onChange: set("color"), disabled: busy }))),
        e("label", { style: { ...label, marginBottom: 12 } }, "Description", e("textarea", { style: { ...field, minHeight: 72, resize: "vertical" }, value: form.desc, onChange: set("desc"), maxLength: 500, disabled: busy })),
        e("label", { style: { ...label, marginBottom: 14 } }, "Steps, one per line", e("textarea", { style: { ...field, minHeight: 150, resize: "vertical" }, value: form.stepsText, onChange: set("stepsText"), required: true, disabled: busy })),
        e("div", { className: "sys-note", style: { marginBottom: 14 } }, e(Icon, { name: "shield", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), "Template steps become active after the System Settings draft is approved."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } }, e("div", { className: "muted", style: { fontSize: 12 } }, "Saved under ", e("span", { className: "mono" }, options.task_settings_key || "collaboration.task_settings")), e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "check", type: "submit", disabled: busy || !options.can_manage_settings, children: busy ? "Creating..." : "Create settings draft" })))));
  }

  function TMTemplates({ onUse, toast }) {
    const options = taskOptions();
    const activeValue = options.task_settings?.value || {};
    const activeTemplates = Array.isArray(activeValue.templates) ? activeValue.templates : [];
    const [draftTemplates, setDraftTemplates] = React.useState([]);
    const [creating, setCreating] = React.useState(false);
    const templates = uniqById([...draftTemplates, ...activeTemplates]).map(tpl => ({
      icon: "template",
      color: "#2570eb",
      uses: 0,
      desc: "",
      steps: [],
      ...tpl,
    }));

    return e("div", null,
      e("div", { className: "row between", style: { marginBottom: 18, flexWrap: "wrap", gap: 10 } },
        e("div", null, e("h2", { style: { fontSize: 18, fontWeight: 800 } }, "Task templates"), e("div", { className: "page-sub" }, "Reusable workflows from System Settings; using a template creates subtasks when the task is saved.")),
        e(Button, { variant: "primary", icon: "plus", disabled: !options.can_manage_settings, onClick: () => options.can_manage_settings ? setCreating(true) : toast("Task template builder requires System Settings permission.", "orange"), children: "New template" })),
      e("div", { className: "sys-note", style: { marginBottom: 16 } },
        e(Icon, { name: "shield", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }),
        activeTemplates.length ? "Showing approved System Settings templates." : "No approved task templates loaded; create and approve a System Settings draft before templates can be used."),
      templates.length === 0 ? e(Empty, { icon: "template", title: "No task templates", sub: "Approved task templates will appear here after setup." }) :
      e("div", { className: "tm-tpl-grid" }, templates.map(tpl =>
        e("div", { key: tpl.id, className: "tm-tpl-card" },
          e("div", { className: "tm-tpl-ic", style: { background: tpl.color + "1f", color: tpl.color } }, e(Icon, { name: tpl.icon, size: 22 })),
          e("div", { className: "row between" }, e("h3", { style: { fontSize: 15, fontWeight: 800 } }, tpl.name), e(Badge, { tone: "b-slate" }, tpl.cat)),
          e("div", { className: "page-sub", style: { marginTop: 6, fontSize: 12.5 } }, tpl.desc),
          e("div", { className: "tm-tpl-steps" }, (tpl.steps || []).map((s, i) => e("div", { key: i, className: "tm-tpl-step" }, e("span", { className: "n" }, i + 1), s))),
          e("div", { className: "row between", style: { marginTop: 4 } },
            e("span", { className: "faint", style: { fontSize: 11.5, fontWeight: 700 } }, "Used " + (tpl.uses || 0) + "×"),
            e(Button, { sm: true, variant: "primary", icon: "play", onClick: () => onUse(tpl), children: "Use template" }))))),
      creating && e(TaskTemplateDraftModal, { options, existingTemplates: templates, onClose: () => setCreating(false), onCreated: (tpl) => setDraftTemplates(rows => [tpl, ...rows]), toast }));
  }

  /* ============ ACTIVITY CENTER ============ */
  function TMActivityCenter({ tasks, onOpen, toast }) {
    const [filter, setFilter] = React.useState("All");
    const filters = ["All", "Comments", "Status", "Transfers", "Approvals"];
    const rows = safeTasks(tasks).flatMap(task => {
      const activities = (task.activity || []).map((item, index) => ({
        type: String(item.action || "").toLowerCase().includes("comment") ? "Comments"
          : String(item.action || "").toLowerCase().includes("status") || String(item.action || "").toLowerCase().includes("moved") ? "Status"
          : String(item.action || "").toLowerCase().includes("transfer") || String(item.action || "").toLowerCase().includes("assign") ? "Transfers"
          : String(item.action || "").toLowerCase().includes("approval") ? "Approvals"
          : "Status",
        who: item.who,
        action: item.action || "updated task",
        target: task.id,
        title: task.title,
        detail: task.project + " · " + task.priority,
        time: item.time || task.updatedAt || "recently",
        icon: item.icon || "activity",
        c: task.status === "completed" ? "#15a657" : task.status === "inprogress" ? "#F6740C" : task.status === "onhold" ? "#dc2f3a" : "#2570eb",
        taskId: task.id,
        order: index,
      }));
      const comments = (task.comments || []).map((comment, index) => ({
        type: "Comments",
        who: comment.who,
        action: "commented on",
        target: task.id,
        title: task.title,
        detail: String(comment.text || "").slice(0, 120),
        time: comment.time || "recently",
        icon: "bubble",
        c: "#2570eb",
        taskId: task.id,
        order: activities.length + index,
      }));
      return activities.concat(comments);
    }).slice(0, 40);
    const visibleRows = rows.filter(row => filter === "All" || row.type === filter);
    return e("div", { style: { maxWidth: 860 } },
      e("div", { className: "row between", style: { marginBottom: 16, flexWrap: "wrap", gap: 10 } },
        e("div", null, e("h2", { style: { fontSize: 18, fontWeight: 800 } }, "Activity center"), e("div", { className: "page-sub" }, "Activity generated from task records, comments and workflow history.")),
        e("div", { className: "tm-viewseg" }, filters.map(f => e("button", { key: f, className: filter === f ? "on" : "", onClick: () => setFilter(f) }, f)))),
      e(Card, { className: "card-pad" },
        visibleRows.length === 0 ? e(Empty, { icon: "activity", title: "No activity found", sub: "No task workflow, comment or assignment activity is available for this filter." }) :
        visibleRows.map((a, i) => e("div", { key: a.taskId + "-" + i, className: "tm-act-row" },
          e("div", { className: "tm-act-ic", style: { background: a.c + "1f", color: a.c } }, e(Icon, { name: a.icon, size: 15 })),
          e("div", { style: { flex: 1, minWidth: 0 } },
            e("div", { style: { fontSize: 13, lineHeight: 1.5 } }, e("b", null, U(a.who).name), " ", a.action, " ", e("span", { style: { color: "var(--accent)", fontWeight: 700, cursor: "pointer" }, onClick: () => onOpen ? onOpen(a.taskId) : toast("Task opening is unavailable for this activity row: " + a.target, "orange") }, a.target), " · ", e("span", { className: "faint" }, a.title)),
            a.detail && e("div", { className: "faint", style: { fontSize: 12, marginTop: 2 } }, a.detail)),
          e("span", { className: "faint", style: { fontSize: 11.5, fontWeight: 600, whiteSpace: "nowrap" } }, a.time)))));
  }

  /* ============ REPORTS ============ */
  function TMReports({ tasks, onExportCsv, onExportPdf, toast }) {
    const rows = safeTasks(tasks);
    const byStatus = {};
    rows.forEach(t => { const l = TM.ST[t.status]?.label || t.status || "Unknown"; byStatus[l] = (byStatus[l] || 0) + 1; });
    const aging = [
      { label: "0–2 days", value: rows.filter(t => isOpenTask(t) && taskAgeDays(t) <= 2).length, color: "#15a657" },
      { label: "3–5 days", value: rows.filter(t => isOpenTask(t) && taskAgeDays(t) >= 3 && taskAgeDays(t) <= 5).length, color: "#2570eb" },
      { label: "6–10 days", value: rows.filter(t => isOpenTask(t) && taskAgeDays(t) >= 6 && taskAgeDays(t) <= 10).length, color: "#e08600" },
      { label: "10+ days", value: rows.filter(t => isOpenTask(t) && taskAgeDays(t) > 10).length, color: "#dc2f3a" },
    ];
    const byDept = TM.departments.map(d => ({ label: d.name, value: rows.filter(t => t.dept === d.name).length, display: rows.filter(t => t.dept === d.name).length + " tasks", color: d.color })).filter(d => d.value > 0);
    const reportStats = {
      total: rows.length,
      completed: rows.filter(isCompletedTask).length,
      overdue: rows.filter(t => t.overdue).length,
      approval: rows.filter(t => t.status === "waitapproval").length,
      hours: rows.reduce((sum, t) => sum + Number(t.actual || 0), 0).toFixed(1),
      transfers: rows.reduce((sum, t) => sum + (t.transfers || []).length, 0),
    };
    const reports = [
      ["Task Completion", reportStats.completed + " completed of " + reportStats.total + " tasks", "trend"],
      ["Employee Productivity", TM.users.filter(u => rows.some(t => t.assignees.includes(u.id))).length + " active assignee(s)", "users"],
      ["Department Performance", byDept.length + " department(s) with active task load", "building"],
      ["Task Aging", aging.reduce((sum, item) => sum + item.value, 0) + " open task(s) in aging buckets", "clock"],
      ["Overdue Report", reportStats.overdue + " overdue task(s)", "alert"],
      ["Transfer Report", reportStats.transfers + " reassignment trail record(s)", "swap"],
      ["Approval Report", reportStats.approval + " task(s) awaiting approval", "shield"],
      ["Time Tracking", reportStats.hours + " actual hour(s) logged", "timer"],
    ];
    return e("div", null,
      e("div", { className: "row between", style: { marginBottom: 16, flexWrap: "wrap", gap: 10 } },
        e("div", null, e("h2", { style: { fontSize: 18, fontWeight: 800 } }, "Reports"), e("div", { className: "page-sub" }, "Reports calculated from available task records. CSV and PDF exports use your current access.")),
        e("div", { className: "row gap-2" }, e(Button, { icon: "download", onClick: () => onExportCsv ? onExportCsv() : toast("Task CSV export is unavailable for this role.", "orange"), children: "Export CSV" }), e(Button, { icon: "doc", onClick: () => onExportPdf ? onExportPdf() : toast("Task PDF export is unavailable for this role.", "orange"), children: "Export PDF" }))),
      e("div", { className: "grid g-2", style: { marginBottom: 16 } },
        e(Card, { title: "Task aging", sub: "Open tasks by age bucket", className: "card-pad" }, e(BarChart, { data: aging, height: 170 })),
        e(Card, { title: "Tasks by department", className: "card-pad" }, byDept.length ? e(HBars, { data: byDept }) : e(Empty, { icon: "building", title: "No department task load", sub: "No task has a mapped department." }))),
      e("div", { className: "tm-tpl-grid" }, reports.map(([name, sub, ic]) =>
        e("div", { key: name, className: "tm-tpl-card", style: { cursor: "pointer" }, onClick: () => toast(name + " uses the currently available task data. Use Export CSV for the report.", "accent") },
          e("div", { className: "row gap-2", style: { marginBottom: 8 } }, e("div", { className: "tm-act-ic", style: { background: "var(--accent-soft)", color: "var(--accent)" } }, e(Icon, { name: ic, size: 15 })), e("h3", { style: { fontSize: 14.5, fontWeight: 800 } }, name)),
          e("div", { className: "page-sub", style: { fontSize: 12.5 } }, sub),
          e("div", { className: "row gap-2", style: { marginTop: 12 } }, e(Button, { sm: true, icon: "eye", onClick: () => toast(name + " refreshed from available task data.", "green"), children: "Run" }), e(Button, { sm: true, variant: "ghost", icon: "download", onClick: () => onExportCsv ? onExportCsv() : toast("Task CSV export is unavailable for this role.", "orange"), children: "Export CSV" }))))));
  }

  /* ============ ANALYTICS ============ */
  function TMAnalytics({ tasks }) {
    const rows = safeTasks(tasks);
    const total = rows.length;
    const completed = rows.filter(isCompletedTask).length;
    const overdue = rows.filter(t => t.overdue).length;
    const completedWithDates = rows.filter(isCompletedTask).map(t => {
      const created = taskCreatedAt(t);
      const done = taskCompletedAt(t) || parseDate(t?.server?.updated_at);
      return created && done ? Math.max(0, (done.getTime() - created.getTime()) / dayMs) : null;
    }).filter(v => v !== null);
    const onTimeCompleted = rows.filter(t => isCompletedTask(t) && (!t.dueAt || (taskCompletedAt(t) && parseDate(t.dueAt) && taskCompletedAt(t).getTime() <= parseDate(t.dueAt).getTime()))).length;
    const statusData = [
      { label: "Completed", value: rows.filter(isCompletedTask).length, color: "#15a657" },
      { label: "In Progress", value: rows.filter(t => t.status === "inprogress").length, color: "#F6740C" },
      { label: "Open", value: rows.filter(t => ["open", "assigned", "accepted", "todo", "draft"].includes(t.status)).length, color: "#2570eb" },
      { label: "Blocked", value: rows.filter(t => ["onhold", "waitinfo", "waitdep", "rejected"].includes(t.status)).length, color: "#dc2f3a" },
      { label: "Approval", value: rows.filter(t => t.status === "waitapproval").length, color: "#e08600" },
    ].filter(d => d.value > 0);
    const weekLabels = ["W-6", "W-5", "W-4", "W-3", "W-2", "W-1", "Now"];
    const trendData = weekLabels.map((_, index) => {
      const start = new Date(); start.setDate(start.getDate() - ((6 - index) * 7 + 6)); start.setHours(0, 0, 0, 0);
      const end = new Date(start); end.setDate(end.getDate() + 7);
      return rows.filter(t => {
        const done = taskCompletedAt(t);
        return done && done >= start && done < end;
      }).length;
    });
    const trend = { series: [{ data: trendData, color: "var(--accent)", fill: true }] };
    const efficiency = TM.users.filter(u => rows.some(t => t.assignees.includes(u.id))).slice(0, 6).map(u => {
      const mine = rows.filter(t => t.assignees.includes(u.id));
      const done = mine.filter(t => t.progress === 100).length;
      return { label: u.name, value: Math.round(done / (mine.length || 1) * 100), display: Math.round(done / (mine.length || 1) * 100) + "%", color: u.color };
    });
    return e("div", null,
      e("h2", { style: { fontSize: 18, fontWeight: 800, marginBottom: 4 } }, "Analytics"),
      e("div", { className: "page-sub", style: { marginBottom: 18 } }, "Analytics calculated from task status, due dates, time logs and workflow timestamps."),
      e("div", { className: "tm-dash-grid", style: { marginBottom: 16 } },
        e(Stat, { label: "Completion rate", value: pct(completed, total), unit: "%", icon: "check", tone: "green" }),
        e(Stat, { label: "On-time delivery", value: pct(onTimeCompleted, completed), unit: "%", icon: "target", tone: "accent" }),
        e(Stat, { label: "Avg cycle time", value: avg(completedWithDates).toFixed(1), unit: "days", icon: "clock", tone: "blue" }),
        e(Stat, { label: "Overdue rate", value: pct(overdue, total), unit: "%", icon: "alert", tone: "red" })),
      e("div", { className: "grid g-2", style: { marginBottom: 16 } },
        e(Card, { title: "Completion trend", sub: "Tasks completed per week from completion timestamps", className: "card-pad" }, e(LineChart, { series: trend.series, height: 200, labels: weekLabels } )),
        e(Card, { title: "Status distribution", className: "card-pad" },
          e("div", { className: "row", style: { gap: 22, alignItems: "center" } },
            e(Donut, { data: statusData, size: 150, thickness: 22, center: e("div", null, e("div", { className: "mono", style: { fontSize: 24, fontWeight: 800 } }, total), e("div", { className: "faint", style: { fontSize: 11, fontWeight: 700 } }, "tasks")) }),
            e("div", { style: { display: "flex", flexDirection: "column", gap: 9, flex: 1 } }, statusData.map(d =>
              e("div", { key: d.label, className: "row gap-2" }, e("span", { style: { width: 10, height: 10, borderRadius: 3, background: d.color } }), e("span", { style: { flex: 1, fontSize: 12.5, fontWeight: 600 } }, d.label), e("span", { className: "mono", style: { fontWeight: 800, fontSize: 12.5 } }, d.value)))))),
      ),
      e(Card, { title: "Team efficiency", sub: "Completion % by assignee from current task view", className: "card-pad" }, efficiency.length ? e(HBars, { data: efficiency }) : e(Empty, { icon: "users", title: "No assignee load", sub: "No task has an assignee." })));
  }

  /* ============ SETTINGS ============ */
  function TMSettings({ toast }) {
    const options = taskOptions();
    const activeSetting = options.task_settings || null;
    const activeValue = activeSetting?.value || {};
    const activeNotifications = activeValue.notifications || {};
    const permissionSummary = Array.isArray(options.permission_summary) ? options.permission_summary : [];
    const defaults = {
      autoProgress: activeValue.auto_progress !== false,
      requireApproval: activeValue.require_completion_approval !== false,
      lockCompleted: activeValue.lock_completed === true,
      notifAssign: activeNotifications.assignment !== false,
      notifComment: activeNotifications.comments_mentions !== false,
      notifDue: activeNotifications.due_soon !== false,
      notifOverdue: activeNotifications.overdue !== false,
      transferApproval: activeValue.transfer_requires_approval !== false,
      autoArchive: Number(activeValue.auto_archive_days || 0) > 0,
    };
    const [tab, setTab] = React.useState("Statuses");
    const [tg, setTg] = React.useState(defaults);
    const [saving, setSaving] = React.useState(false);
    React.useEffect(() => setTg(defaults), [activeSetting?.id, activeSetting?.version]);
    const flip = k => () => {
      if (!options.can_manage_settings) {
        toast("Task settings are managed from System Settings. You have read-only access.", "orange");
        return;
      }
      setTg(s => ({ ...s, [k]: !s[k] }));
    };
    const settingValue = () => ({
      auto_progress: tg.autoProgress,
      require_completion_approval: tg.requireApproval,
      lock_completed: tg.lockCompleted,
      transfer_requires_approval: tg.transferApproval,
      auto_archive_days: tg.autoArchive ? Number(activeValue.auto_archive_days || 30) : 0,
      notifications: {
        assignment: tg.notifAssign,
        comments_mentions: tg.notifComment,
        due_soon: tg.notifDue,
        overdue: tg.notifOverdue,
      },
      status_map: activeValue.status_map || {
        open: "todo",
        in_progress: "inprogress",
        blocked: "blocked",
        completed: "done",
        cancelled: "cancelled",
      },
    });
    const saveDraft = async () => {
      if (!options.can_manage_settings || !options.system_settings_store_url) {
        toast("You do not have access to create task-setting drafts.", "orange");
        return;
      }
      setSaving(true);
      try {
        const body = await apiJson(options.system_settings_store_url, {
          method: "POST",
          body: JSON.stringify({
            setting_group: "collaboration",
            setting_key: options.task_settings_key || "collaboration.task_settings",
            label: "Collaboration Task Settings",
            description: "Configurable task workflow, notification and archival controls used by Task Management.",
            value_type: "object",
            value: settingValue(),
            effective_from: new Date().toISOString().slice(0, 10),
            metadata: { source: "task_settings_screen" },
          }),
        });
        toast((body.message || "System setting draft created.") + " Approve it from Administration → System Settings before it becomes active.", "green");
      } catch (error) {
        toast(error.message || "Task setting draft could not be created.", "red");
      } finally {
        setSaving(false);
      }
    };
    const tabs = ["Statuses", "Workflow", "Permissions", "Notifications"];
    const Toggle = ({ on, onClick }) => e("button", { className: "mbx-toggle" + (on ? " on" : ""), onClick });
    const setRow = (name, sub, key) => e("div", { className: "mbx-setrow", key: key },
      e("div", { className: "st-main" }, e("div", { className: "st-name" }, name), sub && e("div", { className: "st-sub" }, sub)), e(Toggle, { on: tg[key], onClick: flip(key) }));

    return e("div", { style: { maxWidth: 920 } },
      e("h2", { style: { fontSize: 18, fontWeight: 800, marginBottom: 4 } }, "Task settings"),
      e("div", { className: "page-sub", style: { marginBottom: 16 } }, "Task settings are managed through System Settings. Changes create an approval-controlled draft."),
      e("div", { className: "sys-note", style: { marginBottom: 14 } },
        e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
        activeSetting
          ? "Active configuration: v" + activeSetting.version + " · " + activeSetting.status + " · " + (activeSetting.effective_from || "no effective date")
          : "No active task settings are visible. Safe defaults are shown until a setting is approved."),
      e("div", { className: "tabs" }, tabs.map(t => e("div", { key: t, className: "tab " + (tab === t ? "on" : ""), onClick: () => setTab(t) }, t))),

      tab === "Statuses" && e(Card, { className: "card-pad" },
        e("div", { className: "tm-sec-label" }, "Workflow statuses"),
        e("div", { style: { display: "flex", flexDirection: "column", gap: 8 } }, TM.statuses.map(s =>
          e("div", { key: s.id, className: "tm-sub-row" },
            e(Icon, { name: "grip", size: 16, style: { color: "var(--text-3)", cursor: "grab" } }),
            e("span", { style: { flex: 1 } }, e(StatusPill, { id: s.id })),
            e("span", { className: "faint", style: { fontSize: 12 } }, "maps to ", e("b", null, TM.columns.find(c => c.id === s.col) ? TM.columns.find(c => c.id === s.col).label : s.col), " column")))),
        e("button", { className: "tm-addline", style: { marginTop: 12 }, onClick: () => toast("Custom statuses require workflow settings before they can be saved.", "orange") }, e(Icon, { name: "plus", size: 15 }), "Add custom status")),

      tab === "Workflow" && e(Card, { className: "card-pad" },
        setRow("Auto-calculate progress", "Parent task % rolls up from subtasks", "autoProgress"),
        setRow("Require approval to complete", "High-value tasks need approver sign-off", "requireApproval"),
        setRow("Lock completed tasks", "Prevent edits once marked complete", "lockCompleted"),
        setRow("Transfer needs approval", "Manager approves before reassignment", "transferApproval"),
        setRow("Auto-archive after " + Number(activeValue.auto_archive_days || 30) + " days", "Move completed tasks to archive", "autoArchive"),
        e("div", { className: "row gap-2", style: { marginTop: 16 } },
          e(Button, { variant: "primary", icon: "check", onClick: saveDraft, disabled: saving || !options.can_manage_settings, children: saving ? "Saving…" : "Create settings draft" }),
          e("span", { className: "faint", style: { fontSize: 12, fontWeight: 600 } }, options.can_manage_settings ? "Draft requires approval before activation." : "Read-only: settings.manage permission required."))),

      tab === "Permissions" && e(Card, { className: "card-pad", style: { overflowX: "auto" } },
        e("div", { className: "tm-sec-label" }, "Current role permissions"),
        permissionSummary.length ? e("table", { className: "tm-perm-table" },
          e("thead", null, e("tr", null, ["Role", "View", "Create", "Manage", "Settings", "Export"].map(label => e("th", { key: label }, label)))),
          e("tbody", null, permissionSummary.map(row => e("tr", { key: row.role },
            e("td", null, row.role),
            ["view", "create", "manage", "settings", "export"].map(action => e("td", { key: action },
              row[action] ? e(Icon, { name: "check", size: 15, className: "tm-perm-yes", style: { display: "inline" } })
              : e(Icon, { name: "x", size: 14, className: "tm-perm-no", style: { display: "inline" } })))))))
          : e(Empty, { icon: "shield", title: "Access summary unavailable", sub: "Task access details are not available for this session." })),

      tab === "Notifications" && e(Card, { className: "card-pad" },
        setRow("Task assigned to me", "When someone assigns me a task", "notifAssign"),
        setRow("Comments & mentions", "When I'm @mentioned or a watched task gets a comment", "notifComment"),
        setRow("Due soon", "Reminder before a task is due", "notifDue"),
        setRow("Overdue alerts", "When my task passes its due date", "notifOverdue"),
        e("div", { className: "row gap-2", style: { marginTop: 16 } },
          e(Button, { variant: "primary", icon: "check", onClick: saveDraft, disabled: saving || !options.can_manage_settings, children: saving ? "Saving…" : "Create settings draft" }),
          e("span", { className: "faint", style: { fontSize: 12, fontWeight: 600 } }, options.can_manage_settings ? "Notification preference changes are saved as setting drafts." : "Read-only: settings access required."))));
  }

  window.TMTemplates = TMTemplates;
  window.TMActivityCenter = TMActivityCenter;
  window.TMReports = TMReports;
  window.TMAnalytics = TMAnalytics;
  window.TMSettings = TMSettings;
})();
