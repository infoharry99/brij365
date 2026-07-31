const React = window.React;

/* Builder360 - Dashboard, approvals and sales-funnel screens */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Donut, PageHead, ChipSelect, Seg, Empty, HBars, ProgCell } = window;
  const e = React.createElement;

  const number = value => Number(value || 0).toLocaleString("en-IN");
  const decimal = value => Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 2 });
  const percent = value => value === null || value === undefined || value === "" ? "-" : `${Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 1 })}%`;
  const moneyCr = value => `INR ${decimal(value)} Cr`;
  const serverDashboardPayload = () => window.Builder360Server?.dashboard || null;
  const hasServerMetricSource = payload => payload?.source === "laravel-sqlite";
  const sourceTone = payload => hasServerMetricSource(payload) ? "b-green" : "b-red";
  const sourceLabel = payload => hasServerMetricSource(payload) ? "Live metrics" : "Setup incomplete";
  const safeArray = value => Array.isArray(value) ? value : [];
  const asList = value => Array.isArray(value) ? value : [];
  const textValue = value => {
    if (value === null || value === undefined || value === "") return "—";
    if (typeof value === "object") return value.name || value.label || value.title || value.code || value.slug || value.status || JSON.stringify(value);
    return String(value);
  };
  const roleSlug = role => String(role?.id || role?.slug || "").toLowerCase();
  const roleName = role => String(role?.name || role?.title || roleSlug(role)).toLowerCase();
  const rolePermissions = role => Array.isArray(role?.permissions) ? role.permissions.map(permission => String(permission).toLowerCase()) : [];
  const explicitDashboardKindByRole = {
    director: "management",
    sales_head: "sales",
    construction_head: "construction",
    finance_head: "finance",
    hr_manager: "hr",
    payroll: "payroll",
    recruiter: "recruitment",
    auditor: "audit",
    compliance: "compliance",
    system_admin: "system_admin",
    buyer: "buyer",
    employee: "employee",
    channel_partner: "channel_partner",
    executive_partner_broker: "executive_partner_broker",
  };
  const hasAnyPermission = (role, prefixes) => {
    const permissions = rolePermissions(role).filter(permission => permission !== "*");
    return prefixes.some(prefix => permissions.some(permission => permission === prefix || permission.startsWith(prefix + ".")));
  };
  const roleDashboardKind = role => {
    const slug = roleSlug(role);
    if (explicitDashboardKindByRole[slug]) return explicitDashboardKindByRole[slug];
    const name = roleName(role);
    if (role?.scopeLevel === "partner") return "channel_partner";
    if (slug.includes("finance") || name.includes("finance") || hasAnyPermission(role, ["finance"])) return "finance";
    if (slug.includes("sales") || name.includes("sales") || hasAnyPermission(role, ["crm", "booking", "collections"])) return "sales";
    if (slug.includes("construction") || name.includes("construction") || hasAnyPermission(role, ["construction", "procurement"])) return "construction";
    if (slug.includes("payroll") || name.includes("payroll") || hasAnyPermission(role, ["payroll"])) return "payroll";
    if (slug.includes("recruiter") || name.includes("recruiter") || hasAnyPermission(role, ["recruitment"])) return "recruitment";
    if (slug.includes("hr") || name.includes("human resource") || hasAnyPermission(role, ["hr", "leave", "attendance"])) return "hr";
    if (hasAnyPermission(role, ["audit"])) return "audit";
    if (hasAnyPermission(role, ["compliance", "legal"])) return "compliance";
    if (hasAnyPermission(role, ["settings", "users", "roles"])) return "system_admin";
    return "management";
  };
  const roleDashboardLabel = role => ({
    buyer: "Buyer Dashboard",
    employee: "Employee Dashboard",
    channel_partner: "Channel Partner Dashboard",
    executive_partner_broker: "Executive Partner Broker Dashboard",
    sales: "Sales Dashboard",
    construction: "Construction Dashboard",
    finance: "Finance Dashboard",
    hr: "HR Dashboard",
    payroll: "Payroll Dashboard",
    recruitment: "Recruitment Dashboard",
    audit: "Audit & Governance Dashboard",
    compliance: "Compliance Dashboard",
    system_admin: "System Administration Dashboard",
    management: "Management Dashboard",
  }[roleDashboardKind(role)] || "Management Dashboard");
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const navigate = route => {
    if (window.Builder360Navigate) window.Builder360Navigate(route);
    else window.dispatchEvent(new CustomEvent("builder360:navigate", { detail: { route } }));
  };
  const apiJson = async (url, options = {}) => {
    const response = await fetch(url, {
      ...options,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrf(),
        ...(options.headers || {}),
      },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = payload.message || Object.values(payload.errors || {}).flat().join(" ") || "Request failed";
      throw new Error(message);
    }
    return payload;
  };
  const csvCell = value => {
    const text = value === null || value === undefined ? "" : String(value);
    return /[",\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
  };
  const downloadCsv = (filename, rows) => {
    if (!rows.length) return false;
    const headers = Object.keys(rows[0]);
    const lines = [headers.join(","), ...rows.map(row => headers.map(header => csvCell(row[header])).join(","))];
    const csv = lines.join("\n");
    const url = window.URL && URL.createObjectURL
      ? URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }))
      : "data:text/csv;charset=utf-8," + encodeURIComponent(csv);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    if (url.startsWith("blob:")) URL.revokeObjectURL(url);
    return true;
  };

  const countRows = (...values) => {
    for (const value of values) {
      if (Array.isArray(value)) return value.length;
      if (value && typeof value === "object") {
        if (Array.isArray(value.rows)) return value.rows.length;
        if (Array.isArray(value.data)) return value.data.length;
      }
      if (Number.isFinite(Number(value))) return Number(value);
    }
    return 0;
  };

  const firstValue = (...values) => {
    for (const value of values) {
      if (value !== null && value !== undefined && value !== "") return value;
    }
    return "—";
  };

  const statTone = tone => String(tone || "accent").replace(/^b-/, "") || "accent";
  const routeTarget = (route, filter) => {
    if (!route || route === "—") return null;
    const params = new URLSearchParams();
    if (filter && typeof filter === "object") {
      Object.entries(filter).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== "") params.set(key, String(value));
      });
    }
    const query = params.toString() ? `?${params.toString()}` : "";
    return `${route}${query}`;
  };
  const navigateDashboardRoute = (route, filter) => {
    const target = routeTarget(route, filter);
    if (!target) return;
    if (target.includes("?")) {
      window.location.hash = "#" + target;
      window.dispatchEvent(new HashChangeEvent("hashchange"));
      return;
    }
    navigate(target);
  };

  function DashboardPeriodSelector({ period, onChange, busy }) {
    const [open, setOpen] = React.useState(false);
    const [customOpen, setCustomOpen] = React.useState(period?.key === "custom");
    const [customFrom, setCustomFrom] = React.useState(period?.date_from || "");
    const [customTo, setCustomTo] = React.useState(period?.date_to || "");
    const [error, setError] = React.useState("");
    const options = asList(period?.options);
    const activeKey = period?.key || "current_month";
    const activeLabel = period?.label || "Current Month";
    const activeRange = [period?.date_from, period?.date_to].filter(Boolean).join(" to ");
    React.useEffect(() => {
      setCustomFrom(period?.date_from || "");
      setCustomTo(period?.date_to || "");
      setCustomOpen(period?.key === "custom");
      setError("");
    }, [period?.key, period?.date_from, period?.date_to]);
    React.useEffect(() => {
      if (!open) return;
      const onKey = ev => {
        if (ev.key === "Escape") setOpen(false);
      };
      window.addEventListener("keydown", onKey);
      return () => window.removeEventListener("keydown", onKey);
    }, [open]);
    React.useEffect(() => {
      if (customOpen && customFrom && customTo && customFrom > customTo) setError("End date cannot be before start date.");
      else if (error === "End date cannot be before start date.") setError("");
    }, [customOpen, customFrom, customTo]);
    const choose = option => {
      if (!option || busy) return;
      setError("");
      if (option.key === "custom") {
        setCustomOpen(true);
        return;
      }
      setCustomOpen(false);
      setOpen(false);
      onChange(option.key);
    };
    const applyCustom = () => {
      if (busy || !customFrom || !customTo) return;
      if (customFrom > customTo) {
        setError("End date cannot be before start date.");
        return;
      }
      setOpen(false);
      onChange("custom", customFrom, customTo);
    };
    const customInvalid = !customFrom || !customTo || customFrom > customTo;

    return e("div", { className: "dash-period" },
      e("button", { className: "chip-select dash-period-btn", "aria-haspopup": "menu", "aria-controls": "dashboard-period-menu", "aria-expanded": open, disabled: busy, onClick: () => setOpen(value => !value) },
        e("span", { className: "faint", style: { fontWeight: 700 } }, "Period:"),
        e("span", null, busy ? "Updating..." : activeLabel),
        activeRange && e("span", { className: "dash-period-range" }, activeRange),
        e(Icon, { name: "chevD", size: 15 })),
      open && e("div", null,
        e("div", { className: "dash-period-scrim", onClick: () => setOpen(false) }),
        e("div", { id: "dashboard-period-menu", className: "card dash-period-menu", role: "menu", "aria-label": "Dashboard period" },
          options.map(option => e("button", { key: option.key, role: "menuitem", className: "dash-period-option" + (activeKey === option.key ? " active" : ""), disabled: busy, onClick: () => choose(option) },
            e("span", null, option.label || option.key),
            (activeKey === option.key || (option.key === "custom" && customOpen)) && e(Icon, { name: option.key === "custom" && activeKey !== "custom" ? "chevD" : "check", size: 15 }))),
          customOpen && e("div", { className: "dash-custom-range" },
            e("label", null, "From", e("input", { type: "date", value: customFrom, disabled: busy, onInput: ev => setCustomFrom(ev.target.value), onChange: ev => setCustomFrom(ev.target.value) })),
            e("label", null, "To", e("input", { type: "date", value: customTo, disabled: busy, onInput: ev => setCustomTo(ev.target.value), onChange: ev => setCustomTo(ev.target.value) })),
            error && e("div", { className: "dash-field-error", role: "alert" }, error),
            e(Button, { sm: true, variant: "primary", disabled: busy || customInvalid, onClick: applyCustom, children: "Apply Custom" })))));
  }

  function DashboardStatCard({ stat }) {
    const route = stat?.is_actionable === false ? null : routeTarget(stat?.route, stat?.route_filter);
    return e(Stat, {
      label: textValue(stat?.label),
      value: textValue(firstValue(stat?.value, 0)),
      unit: stat?.unit || "",
      icon: textValue(stat?.icon || "grid"),
      tone: statTone(stat?.tone),
      sub: textValue(stat?.sub || ""),
      title: route ? `Open ${textValue(stat?.label)}` : undefined,
      onClick: route ? () => navigateDashboardRoute(stat.route, stat.route_filter) : undefined,
      tabIndex: route ? 0 : undefined,
      role: route ? "button" : undefined,
      "aria-label": route ? `Open ${textValue(stat?.label)}` : undefined,
    });
  }

  function DashboardRow({ row, last }) {
    const route = row?.is_actionable === false ? null : routeTarget(row?.route, row?.route_filter);
    return e("div", {
      className: "dash-row" + (route ? " clickable" : ""),
      role: route ? "button" : undefined,
      tabIndex: route ? 0 : undefined,
      "aria-label": route ? `Open ${textValue(row?.label || "record")}` : undefined,
      onClick: route ? () => navigateDashboardRoute(row.route, row.route_filter) : undefined,
      onKeyDown: route ? ev => { if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); navigateDashboardRoute(row.route, row.route_filter); } } : undefined,
      style: { borderBottom: last ? "none" : "1px solid var(--border)" },
    },
      e("div", { style: { minWidth: 0 } },
        e("div", { className: "cell-strong dash-row-title" }, textValue(row?.label || "Record")),
        e("div", { className: "cell-sub dash-row-sub" }, textValue(row?.sub || ""))),
      e("div", { className: "row gap-2" },
        e(Badge, { tone: textValue(row?.tone || "b-slate") }, textValue(firstValue(row?.value, row?.status, "View"))),
        route && e(Icon, { name: "chevR", size: 15 })));
  }

  function DashboardSectionCard({ section }) {
    const [mode, setMode] = React.useState(section?.view_mode || "");
    const modeRows = section?.mode_rows && typeof section.mode_rows === "object" ? section.mode_rows : {};
    const rows = asList(mode && modeRows[mode] ? modeRows[mode] : section?.rows);
    const shownRows = rows.slice(0, 8);
    const route = section?.is_actionable === false ? null : routeTarget(section?.route, section?.route_filter);
    const options = asList(section?.view_options).filter(option => modeRows[option]);
    return e(Card, {
      className: "dash-section-card",
      title: textValue(section?.title || "Records"),
      sub: textValue(section?.sub || ""),
      action: e("div", { className: "row gap-2" },
        options.length > 0 && e(Seg, { options, value: mode || options[0], onChange: setMode }),
        route && e(Button, { sm: true, variant: "ghost", icon: "chevR", onClick: () => navigateDashboardRoute(section.route, section.route_filter), children: "View all" })),
      pad: true,
    },
      shownRows.length
        ? shownRows.map((row, index) => e(DashboardRow, { key: textValue(row?.label || index) + index, row, last: index === shownRows.length - 1 }))
        : e(Empty, { title: "No records available", sub: textValue(section?.empty || "No records are available for your selected view.") }));
  }

  function numericDashboardValue(value) {
    const raw = String(value ?? "").replace(/[^\d.-]/g, "");
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function DashboardChartCard({ chart }) {
    const rows = asList(chart?.rows).slice(0, 7);
    const route = chart?.is_actionable === false ? null : routeTarget(chart?.route, chart?.route_filter);
    const max = Math.max(...rows.map(row => Math.abs(numericDashboardValue(row?.value))), 1);
    return e(Card, {
      className: "dash-chart-card",
      title: textValue(chart?.title || "Chart"),
      sub: textValue(chart?.sub || ""),
      action: route && e(Button, { sm: true, variant: "ghost", icon: "chevR", onClick: () => navigateDashboardRoute(chart.route, chart.route_filter), children: "View all" }),
      pad: true,
    },
      rows.length
        ? e("div", { className: "dash-chart-bars", role: "list", "aria-label": textValue(chart?.title || "Dashboard chart") },
          rows.map((row, index) => {
            const rowRoute = row?.is_actionable === false ? null : routeTarget(row?.route, row?.route_filter);
            const width = Math.max(6, Math.round(Math.abs(numericDashboardValue(row?.value)) / max * 100));
            return e("div", {
              key: textValue(row?.key || row?.label || index),
              className: "dash-chart-row" + (rowRoute ? " clickable" : ""),
              role: rowRoute ? "button" : "listitem",
              tabIndex: rowRoute ? 0 : undefined,
              "aria-label": rowRoute ? `Open ${textValue(row?.label || "chart item")}` : undefined,
              onClick: rowRoute ? () => navigateDashboardRoute(row.route, row.route_filter) : undefined,
              onKeyDown: rowRoute ? ev => { if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); navigateDashboardRoute(row.route, row.route_filter); } } : undefined,
            },
              e("div", { className: "dash-chart-label" }, textValue(row?.label || "Item")),
              e("div", { className: "dash-chart-track" }, e("div", { className: "dash-chart-fill " + textValue(row?.tone || "b-blue"), style: { width: `${width}%` } })),
              e("div", { className: "dash-chart-value" }, textValue(firstValue(row?.value, "0"))));
          }))
        : e(Empty, { title: "No chart data", sub: textValue(chart?.empty || "No chart data is available for your selected view.") }));
  }

  function DashboardAlertCard({ alerts }) {
    const rows = asList(alerts).slice(0, 8);
    return e(Card, { className: "dash-alert-card", title: "Alerts & Actions", sub: "Items needing attention", pad: true },
      rows.length
        ? rows.map((row, index) => e(DashboardRow, { key: textValue(row?.key || row?.label || index), row, last: index === rows.length - 1 }))
        : e(Empty, { title: "No urgent items", sub: "Nothing requires immediate action in this view." }));
  }

  function DashboardTableCard({ table }) {
    const rows = asList(table?.rows).slice(0, 8);
    const route = table?.is_actionable === false ? null : routeTarget(table?.route, table?.route_filter);
    return e(Card, {
      className: "dash-table-card",
      title: textValue(table?.title || "Records"),
      sub: textValue(table?.sub || ""),
      action: route && e(Button, { sm: true, variant: "ghost", icon: "chevR", onClick: () => navigateDashboardRoute(table.route, table.route_filter), children: "View all" }),
      pad: false,
    },
      rows.length
        ? e("div", { className: "dash-table-wrap" }, e("table", { className: "dash-table" },
          e("thead", null, e("tr", null, asList(table?.columns).slice(0, 3).map((heading, index) => e("th", { key: index }, textValue(heading))))),
          e("tbody", null, rows.map((row, index) => {
            const rowRoute = row?.is_actionable === false ? null : routeTarget(row?.route, row?.route_filter);
            return e("tr", {
              key: textValue(row?.key || row?.label || index),
              className: rowRoute ? "clickable" : "",
              role: rowRoute ? "button" : undefined,
              tabIndex: rowRoute ? 0 : undefined,
              "aria-label": rowRoute ? `Open ${textValue(row?.label || "record")}` : undefined,
              onClick: rowRoute ? () => navigateDashboardRoute(row.route, row.route_filter) : undefined,
              onKeyDown: rowRoute ? ev => { if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); navigateDashboardRoute(row.route, row.route_filter); } } : undefined,
            },
              e("td", null, e("div", { className: "cell-strong" }, textValue(row?.label || "Record")), e("div", { className: "cell-sub" }, textValue(row?.sub || ""))),
              e("td", null, e(Badge, { tone: textValue(row?.tone || "b-slate") }, textValue(firstValue(row?.value, row?.status, "Open")))),
              e("td", null, rowRoute ? e(Icon, { name: "chevR", size: 15 }) : "—"));
          }))))
        : e("div", { style: { padding: 18 } }, e(Empty, { title: "No records available", sub: textValue(table?.empty || "No table records are available for your selected view.") })));
  }

  function RoleControlDashboard({ role, title, sub, stats, sections, primaryRoute, primaryLabel, toast }) {
    const context = window.Builder360Server?.active_role_context || {};
    const [dashboard, setDashboard] = React.useState(window.Builder360Server?.role_dashboard || {});
    const [periodBusy, setPeriodBusy] = React.useState(false);
    React.useEffect(() => {
      setDashboard(window.Builder360Server?.role_dashboard || {});
    }, [role?.id, window.Builder360Server?.active_project_context?.project_id]);
    const normalized = dashboard || {};
    const scopedUser = window.Builder360Server?.user || {};
    const firstName = String(role.person || scopedUser.name || "User").split(" ")[0];
    const dashboardStats = asList(stats || normalized.stats);
    const dashboardSections = asList(sections || normalized.sections).map(section => ({ ...section, rows: asList(section?.rows) }));
    const dashboardCharts = asList(normalized.charts);
    const dashboardAlerts = asList(normalized.alerts);
    const dashboardTables = asList(normalized.tables);
    const quickActions = asList(normalized.quick_actions);
    const resolvedTitle = textValue(title || normalized.title || roleDashboardLabel(role));
    const resolvedSub = textValue(sub || normalized.subtitle || `Dashboard for ${firstName}.`);
    const resolvedPrimaryRoute = primaryRoute || normalized.primary_route;
    const resolvedPrimaryLabel = primaryLabel || normalized.primary_label || "Open Workspace";
    const changePeriod = async (periodKey, dateFrom, dateTo) => {
      setPeriodBusy(true);
      try {
        const payload = await apiJson("/builder360/dashboard-context", {
          method: "POST",
          body: JSON.stringify({ period_key: periodKey, date_from: dateFrom || null, date_to: dateTo || null }),
        });
        if (typeof window.Builder360ApplyBootstrap === "function") window.Builder360ApplyBootstrap(payload);
        setDashboard(payload.role_dashboard || {});
        if (toast) toast("Dashboard period updated.", "green");
      } catch (error) {
        console.error("[Builder360] Dashboard period update failed", error);
        if (toast) toast(error.message || "Dashboard period update failed.", "red");
      } finally {
        setPeriodBusy(false);
      }
    };

    return e("div", { className: "page page-wide dash-page" },
      e(PageHead, {
        title: resolvedTitle,
        sub: resolvedSub,
        actions: [
          e(DashboardPeriodSelector, { key: "period", period: normalized.period || window.Builder360Server?.active_dashboard_period, busy: periodBusy, onChange: changePeriod }),
          e(Badge, { key: "role", tone: "b-blue", children: resolvedTitle }),
          e(Badge, { key: "scope", tone: "b-green", children: context.mode === "selected_role_preview" ? "Role preview" : "Your role" }),
          resolvedPrimaryRoute && e(Button, { key: "open", icon: "chevR", variant: "primary", onClick: () => navigateDashboardRoute(resolvedPrimaryRoute, normalized.primary_route_filter), children: resolvedPrimaryLabel }),
        ].filter(Boolean),
      }),
      quickActions.length > 0 && e("div", { className: "dash-quick-actions", "aria-label": "Dashboard quick actions" },
        quickActions.map(action => e(Button, {
          key: textValue(action?.key || action?.label),
          sm: true,
          icon: textValue(action?.icon || "chevR"),
          variant: action?.key === "open" ? "primary" : "ghost",
          disabled: !action?.is_actionable,
          onClick: action?.is_actionable ? () => navigateDashboardRoute(action.route, action.route_filter) : undefined,
          children: textValue(action?.label || "Open"),
        }))),
      e("div", { className: "dash-stat-grid" },
        dashboardStats.map((stat, index) => e(DashboardStatCard, { key: textValue(stat?.label || index), stat }))),
      dashboardCharts.length > 0 && e("div", { className: "dash-chart-grid" },
        dashboardCharts.map((chart, index) => e(DashboardChartCard, { key: textValue(chart?.key || chart?.title || index), chart }))),
      dashboardAlerts.length > 0 && e("div", { className: "dash-alert-grid" },
        e(DashboardAlertCard, { alerts: dashboardAlerts })),
      dashboardTables.length > 0 && e("div", { className: "dash-table-grid" },
        dashboardTables.map((table, index) => e(DashboardTableCard, { key: textValue(table?.key || table?.title || index), table }))),
      e("div", { className: "dash-section-grid" },
        dashboardSections.map((section, index) => e(DashboardSectionCard, { key: textValue(section?.title || index), section }))),
    );
  }

  function PayrollDashboard({ role, toast }) {
    return e(RoleControlDashboard, { role, toast });
  }

  function RecruitmentDashboard({ role, toast }) {
    const recruitmentSource = window.Builder360Server?.role_dashboard || window.Builder360Server?.hr_recruitment_options?.job_openings;
    return e(RoleControlDashboard, { role, source: recruitmentSource, toast });
  }

  function AuditDashboard({ role, toast }) {
    return e(RoleControlDashboard, { role, toast });
  }

  function ComplianceDashboard({ role, toast }) {
    return e(RoleControlDashboard, { role, toast });
  }

  function SystemAdminDashboard({ role, toast }) {
    const adminRows = asList(window.Builder360Server?.admin_governance_options?.users).map(row => row.role?.name || row.role_name || row.role_slug || row.status);
    return e(RoleControlDashboard, { role, adminRows, toast });
  }

  function BuyerDashboard({ role, toast }) {
    return e(RoleControlDashboard, { role, toast });
  }

  function EmployeeDashboard({ role, toast }) {
    const selfServiceSource = window.Builder360Server?.role_dashboard || window.Builder360Server?.hr_self_service_options?.recent_attendance;
    return e(RoleControlDashboard, { role, source: selfServiceSource, toast });
  }

  function PartnerDashboard({ role, toast }) {
    const partnerSource = window.Builder360Server?.role_dashboard || window.Builder360Server?.partner_portal?.my_leads;
    return e(RoleControlDashboard, { role, source: partnerSource, toast });
  }

  function Dashboard({ role, toast }) {
    return e(RoleControlDashboard, { role, toast });
  }

  function ApprovalDropdown({ label, value, options, onChange }) {
    const [open, setOpen] = React.useState(false);
    const rootRef = React.useRef(null);
    const safeOptions = safeArray(options);
    const selected = safeOptions.find(item => item.value === value);
    const selectedLabel = selected?.label || label;

    React.useEffect(() => {
      if (!open) return undefined;
      const onPointer = event => {
        if (!rootRef.current || rootRef.current.contains(event.target)) return;
        setOpen(false);
      };
      const onKey = event => {
        if (event.key === "Escape") setOpen(false);
      };
      document.addEventListener("pointerdown", onPointer);
      window.addEventListener("keydown", onKey);
      return () => {
        document.removeEventListener("pointerdown", onPointer);
        window.removeEventListener("keydown", onKey);
      };
    }, [open]);

    return e("div", { className: "approval-dd", ref: rootRef },
      e("button", {
        type: "button",
        className: "approval-dd-btn " + (open ? "open" : ""),
        "aria-haspopup": "menu",
        "aria-expanded": open,
        onClick: () => setOpen(current => !current),
      },
        e("span", { className: "approval-dd-label" }, selectedLabel),
        e(Icon, { name: "chevD", size: 15 })),
      open && e("div", { className: "approval-dd-menu", role: "menu" },
        safeOptions.map(item => e("button",
          {
            key: item.value || "__all",
            type: "button",
            role: "menuitem",
            className: "approval-dd-option " + (item.value === value ? "selected" : ""),
            onClick: () => {
              onChange(item.value);
              setOpen(false);
            },
          },
          e("span", null, item.label),
          item.value === value && e(Icon, { name: "check", size: 15 })
        ))
      )
    );
  }

  function Approvals({ toast }) {
    const initialPayload = window.Builder360Server?.approval_inbox_options || null;
    const [tab, setTab] = React.useState("pending");
    const [payload, setPayload] = React.useState(initialPayload);
    const [rows, setRows] = React.useState(safeArray(initialPayload?.rows));
    const [summary, setSummary] = React.useState(initialPayload?.summary || {});
    const [filters, setFilters] = React.useState({ q: "", module: "", priority: "", status: "" });
    const [filterOptions, setFilterOptions] = React.useState(initialPayload?.filters || { modules: [], priorities: [], statuses: [] });
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState("");
    const [busyId, setBusyId] = React.useState(null);
    const [exporting, setExporting] = React.useState(false);
    const [decision, setDecision] = React.useState(null);
    const [decisionNote, setDecisionNote] = React.useState("");
    const [decisionError, setDecisionError] = React.useState("");
    const isRestrictedApprovalRoute = payload === null || payload?.restricted === true;
    const tabs = [
      ["pending", "Pending", summary.pending || 0],
      ["high_priority", "High Priority", summary.high_priority || 0],
      ["actionable", "Actionable", summary.actionable || 0],
      ["restricted", "Restricted", summary.restricted || 0],
      ["approved", "Approved", summary.approved || 0],
    ];
    const qs = extra => {
      const params = new URLSearchParams();
      params.set("tab", extra?.tab || tab);
      if (filters.q) params.set("q", filters.q);
      if (filters.module) params.set("module", filters.module);
      if (filters.priority) params.set("priority", filters.priority);
      if (filters.status) params.set("status", filters.status);
      params.set("per_page", "50");
      return params.toString();
    };
    const refreshApprovals = React.useCallback(async (nextTab = tab) => {
      if (window.Builder360Server?.approval_inbox_options === null) {
        setPayload(null);
        setRows([]);
        return;
      }
      setLoading(true);
      setError("");
      try {
        const data = await apiJson(`/builder360/approvals?${qs({ tab: nextTab })}`);
        setPayload(data);
        setRows(safeArray(data?.rows));
        setSummary(data?.summary || {});
        setFilterOptions(data?.filters || { modules: [], priorities: [], statuses: [] });
      } catch (err) {
        console.error("[Builder360] Approval Center load failed", err);
        setError(err.message || "Approval records could not be loaded.");
      } finally {
        setLoading(false);
      }
    }, [tab, filters.q, filters.module, filters.priority, filters.status]);
    React.useEffect(() => {
      refreshApprovals(tab);
    }, [tab, filters.module, filters.priority, filters.status]);
    React.useEffect(() => {
      const timer = setTimeout(() => refreshApprovals(tab), 350);
      return () => clearTimeout(timer);
    }, [filters.q]);
    React.useEffect(() => {
      const onBootstrap = () => {
        const next = window.Builder360Server?.approval_inbox_options || null;
        setPayload(next);
        setRows(safeArray(next?.rows));
        setSummary(next?.summary || {});
        setFilterOptions(next?.filters || { modules: [], priorities: [], statuses: [] });
        setTab("pending");
        setFilters({ q: "", module: "", priority: "", status: "" });
      };
      window.addEventListener("builder360:bootstrap-applied", onBootstrap);
      return () => window.removeEventListener("builder360:bootstrap-applied", onBootstrap);
    }, []);
    const rowNumber = item => item.number || item.id || "-";
    const rowDescription = item => item.description || item.desc || "";
    const rowRaisedBy = item => item.raised_by || item.who || "System";
    const rowAmount = item => item.amount_display || item.amt || "—";
    const rowPriority = item => item.priority || item.pr || "low";
    const priorityLabel = value => value === "high" ? "High" : value === "med" ? "Medium" : "Low";
    const updateFilter = (key, value) => setFilters(current => ({ ...current, [key]: value }));
    const moduleOptions = [{ value: "", label: "All modules" }].concat(safeArray(filterOptions.modules).map(item => ({ value: item, label: item })));
    const priorityOptions = [{ value: "", label: "All priorities" }].concat(safeArray(filterOptions.priorities).map(item => ({ value: item, label: priorityLabel(item) })));
    const statusOptions = [{ value: "", label: "All statuses" }].concat(safeArray(filterOptions.statuses).map(item => ({ value: item, label: item })));
    const routeTo = item => {
      if (!item.open_route) return;
      const params = new URLSearchParams(item.open_route_filter || {});
      window.location.hash = params.toString() ? `${item.open_route}?${params.toString()}` : item.open_route;
    };
    const exportApprovals = async () => {
      setExporting(true);
      try {
        const response = await fetch(`/builder360/approvals/export?${qs()}`, {
          headers: { Accept: "text/csv" },
          credentials: "same-origin",
        });
        if (!response.ok) throw new Error("Approval export failed.");
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `builder360-approvals-${tab}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        if (toast) toast("Approval export downloaded", "green");
      } catch (err) {
        console.error("[Builder360] Approval export failed", err);
        if (toast) toast(err.message || "Approval export failed", "red");
      } finally {
        setExporting(false);
      }
    };
    const submitDecision = async () => {
      if (!decision?.row) return;
      if (decision.type === "reject" && !decisionNote.trim()) {
        setDecisionError("Rejection note is required.");
        return;
      }
      const row = decision.row;
      const url = decision.type === "reject" ? row.reject_url : row.approve_url;
      const key = decision.type === "reject" ? row.reject_payload_key || "decision_note" : row.approve_payload_key || "note";
      if (!url) {
        setDecisionError("This action is not available for this record.");
        return;
      }
      setBusyId(row.id);
      setDecisionError("");
      try {
        await apiJson(url, { method: "PATCH", body: JSON.stringify({ [key]: decisionNote.trim() || `${decision.type === "reject" ? "Rejected" : "Approved"} from Approval Center.` }) });
        setDecision(null);
        setDecisionNote("");
        await refreshApprovals(tab);
        if (toast) toast(`${rowNumber(row)} ${decision.type === "reject" ? "rejected" : "approved"} successfully`, "green");
      } catch (err) {
        console.error("[Builder360] Approval action failed", err);
        setDecisionError(err.message || "Action failed.");
      } finally {
        setBusyId(null);
      }
    };

    if (isRestrictedApprovalRoute) {
      return e("div", { className: "page page-wide" },
        e(PageHead, {
          crumbs: ["Overview", "Approvals"],
          title: "Module not available for this role",
          sub: "This route is not part of the approved navigation for the selected role. Use the available sidebar options.",
          actions: [e(Badge, { key: "restricted", tone: "b-orange" }, "ROLE RESTRICTED")],
        }),
        e("div", { className: "hrx-warning", style: { marginBottom: 16 } },
          e(Icon, { name: "shield", size: 17 }),
          e("div", null,
            e("b", null, "Role restricted route blocked"),
            e("span", null, "Approval Center is an internal workflow inbox and is hidden for this selected role.")),
          e(Badge, { tone: "b-orange" }, "ROLE RESTRICTED")),
        e("div", { className: "card card-pad" },
          e(Empty, { title: "Approval Center unavailable", sub: "Switch to an authorized internal role or use the modules shown in the current sidebar." })));
    }

    return e("div", { className: "page" },
      e(PageHead, {
        crumbs: ["Overview", "Approvals"],
        title: "Approval Center",
        sub: "Centralized approval inbox for records available to your role and selected project.",
        actions: [
          e(Button, { key: "refresh", icon: "refresh", variant: "ghost", disabled: loading, onClick: () => refreshApprovals(tab), children: loading ? "Refreshing..." : "Refresh" }),
          e(Button, { key: "export", icon: "download", disabled: exporting || loading, onClick: exportApprovals, children: exporting ? "Exporting..." : "Export CSV" }),
          e(Button, { key: "bulk", icon: "shield", variant: "ghost", disabled: true, "aria-disabled": true, title: "Bulk approval is disabled because approval order and segregation of duties are enforced per source record.", children: "Bulk approval disabled" }),
        ],
      }),
      e("div", { className: "grid g-4 approvals-stats", style: { marginBottom: 18 } },
        [
          ["pending", "Pending", summary.pending || 0, "clock", "orange", "records awaiting decision"],
          ["high_priority", "High Priority", summary.high_priority || 0, "flame", (summary.high_priority || 0) > 0 ? "red" : "green", "urgent approval records"],
          ["actionable", "Actionable", summary.actionable || 0, "check", (summary.actionable || 0) > 0 ? "green" : "slate", "available actions for this role"],
          ["approved", "Approved Recently", summary.approved || 0, "box", "green", `${number(summary.value_tagged || 0)} value-tagged item(s)`],
        ].map(card => e("button", { key: card[0], type: "button", className: "approval-stat-btn", onClick: () => setTab(card[0]) },
          e(Stat, { label: card[1], value: number(card[2]), icon: card[3], tone: card[4], sub: card[5] }))),
      ),
      e("div", { className: "approval-toolbar" },
        e("div", { className: "searchbox approval-search" }, e(Icon, { name: "search", size: 17 }), e("input", { value: filters.q, onChange: ev => updateFilter("q", ev.target.value), placeholder: "Search approvals, numbers, modules, people..." })),
        e("div", { className: "approval-filter-actions" },
          e(ApprovalDropdown, { label: "All modules", value: filters.module, options: moduleOptions, onChange: value => updateFilter("module", value) }),
          e(ApprovalDropdown, { label: "All priorities", value: filters.priority, options: priorityOptions, onChange: value => updateFilter("priority", value) }),
          e(ApprovalDropdown, { label: "All statuses", value: filters.status, options: statusOptions, onChange: value => updateFilter("status", value) })),
      ),
      e("div", { className: "tabs approval-tabs", role: "tablist", "aria-label": "Approval states" }, tabs.map(item => e("button", { key: item[0], type: "button", role: "tab", "aria-selected": tab === item[0], className: "tab " + (tab === item[0] ? "on" : ""), onClick: () => setTab(item[0]) }, item[1], e("span", { className: "tab-count" }, number(item[2]))))),
      error && e("div", { className: "hrx-warning", style: { marginBottom: 14 } },
        e(Icon, { name: "alert", size: 17 }),
        e("div", null, e("b", null, "Approval records could not be loaded"), e("span", null, error)),
        e(Button, { sm: true, variant: "ghost", onClick: () => refreshApprovals(tab), children: "Retry" })),
      e("div", { className: "card" },
        loading ? e("div", { style: { padding: 28 } }, e(Empty, { title: "Loading approval records", sub: "Please wait while the latest approval records are loaded." })) :
        rows.length ? e("div", { className: "tbl-wrap" }, e("table", { className: "tbl approval-table" },
          e("thead", null, e("tr", null, ["Request", "Type", "Raised By", "Amount", "Age", "Priority", "Status", "Action"].map((heading, index) => e("th", { key: index, style: index === 3 ? { textAlign: "right" } : {} }, heading)))),
          e("tbody", null, rows.map(item => e("tr", { key: item.id, className: item.open_route ? "approval-row-openable" : "", onDoubleClick: () => routeTo(item) },
            e("td", null, e("div", { className: "approval-request-cell" },
              e("button", {
                type: "button",
                className: "approval-request-link",
                onClick: () => routeTo(item),
                disabled: !item.open_route,
                "aria-label": `Open approval request ${rowNumber(item)}`,
                title: item.open_route ? "Open request details" : "Request details unavailable",
              }, rowNumber(item)),
              e("div", { className: "cell-sub", style: { whiteSpace: "normal", maxWidth: 280 } }, rowDescription(item)))),
            e("td", null, e("div", { className: "col gap-1" }, e(Badge, { tone: "b-blue" }, item.type), e("span", { className: "cell-sub" }, item.source_module || "—"))),
            e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: rowRaisedBy(item), sm: true }), rowRaisedBy(item))),
            e("td", { className: "num cell-strong" }, rowAmount(item)),
            e("td", { className: "faint" }, item.age || "-"),
            e("td", null, e(Badge, { tone: rowPriority(item) === "high" ? "b-red" : rowPriority(item) === "med" ? "b-orange" : "b-slate", dot: true }, rowPriority(item) === "high" ? "High" : rowPriority(item) === "med" ? "Medium" : "Low")),
            e("td", null, e(Badge, { tone: item.status === "approved" || item.status === "active" || item.status === "open" ? "b-green" : item.status === "rejected" ? "b-red" : "b-orange" }, item.status || "pending")),
            e("td", null, e("div", { className: "row gap-2" },
              e(Button, { sm: true, variant: "ghost", onClick: () => routeTo(item), children: "Open" }),
              item.can_approve && item.approve_url && e(Button, { sm: true, icon: "check", variant: "primary", disabled: busyId === item.id, onClick: () => { setDecision({ type: "approve", row: item }); setDecisionNote(""); setDecisionError(""); }, children: busyId === item.id ? "Working..." : "Approve" }),
              item.can_reject && item.reject_url && e(Button, { sm: true, icon: "x", variant: "ghost", disabled: busyId === item.id, onClick: () => { setDecision({ type: "reject", row: item }); setDecisionNote(""); setDecisionError(""); }, children: "Reject" }),
              !item.can_approve && !item.can_reject && e(Badge, { tone: item.status === "approved" || item.status === "active" || item.status === "open" ? "b-green" : "b-slate" }, item.status === "approved" || item.status === "active" || item.status === "open" ? "Approved" : "View only"))),
          ))),
        )) : e("div", { style: { padding: 18 } }, e(Empty, { title: tab === "pending" ? "No pending approvals" : "No records in this view", sub: "No approval records are available for the selected filters and role." }))),
      decision && e("div", { className: "scrim", role: "dialog", "aria-modal": true },
        e("div", { className: "modal approval-decision-modal" },
          e("div", { className: "modal-head" },
            e("div", null,
              e("h2", null, decision.type === "reject" ? "Reject approval" : "Approve record"),
              e("p", null, rowNumber(decision.row), " · ", decision.row.type, decision.row.amount_display ? ` · ${decision.row.amount_display}` : "")),
            e(Button, { icon: "x", variant: "ghost", onClick: () => setDecision(null), "aria-label": "Close" })),
          e("div", { className: "modal-body" },
            e("div", { className: "grid g-2" },
              e("div", null, e("label", { className: "label" }, "Raised by"), e("div", { className: "input-like" }, rowRaisedBy(decision.row))),
              e("div", null, e("label", { className: "label" }, "Module"), e("div", { className: "input-like" }, decision.row.source_module || "—"))),
            e("label", { className: "label", style: { marginTop: 12 } }, decision.type === "reject" ? "Rejection note" : "Approval note"),
            e("textarea", { className: "input-like", style: { minHeight: 90, width: "100%" }, value: decisionNote, onChange: ev => setDecisionNote(ev.target.value), placeholder: decision.type === "reject" ? "Enter reason for rejection" : "Optional approval note" }),
            decisionError && e("div", { className: "form-error" }, decisionError)),
          e("div", { className: "modal-foot" },
            e(Button, { variant: "ghost", onClick: () => setDecision(null), children: "Cancel" }),
            e(Button, { variant: decision.type === "reject" ? "ghost" : "primary", disabled: busyId === decision.row.id, onClick: submitDecision, children: busyId === decision.row.id ? "Working..." : decision.type === "reject" ? "Reject" : "Approve" }))))
    );
  }

  function FunnelAnalytics({ toast } = {}) {
    const metrics = window.Builder360Server?.sales_funnel_metrics || {
      source: "unavailable",
      summary: {},
      funnel: [],
      lost_reasons: [],
      source_conversion: [],
      project_booking_rates: [],
      stage_durations: [],
    };
    const summary = metrics.summary || {};
    const funnel = safeArray(metrics.funnel);
    const lostReasons = safeArray(metrics.lost_reasons);
    const sourceConversion = safeArray(metrics.source_conversion);
    const projectBookingRates = safeArray(metrics.project_booking_rates);
    const stageDurations = safeArray(metrics.stage_durations);
    const firstStageCount = Math.max(Number(funnel[0]?.n || 0), 1);
    const biggestDrop = summary.biggest_dropoff_label ? `${summary.biggest_dropoff_label} (-${summary.biggest_dropoff_percent || 0}%)` : "Not available";
    const exportFunnel = () => {
      const rows = [
        ...funnel.map(row => ({ section: "funnel", label: row.stage, count: row.n, value: "", display: "" })),
        ...lostReasons.map(row => ({ section: "lost_reason", label: row.label, count: "", value: row.value, display: row.display || "" })),
        ...sourceConversion.map(row => ({ section: "source_conversion", label: row.label, count: "", value: row.value, display: row.display || "" })),
        ...projectBookingRates.map(row => ({ section: "project_booking_rate", label: row.label, count: "", value: row.value, display: row.display || "" })),
        ...stageDurations.map(row => ({ section: "stage_duration", label: row.label, count: "", value: row.value, display: row.display || "" })),
      ];
      try {
        const ok = downloadCsv("builder360-lead-funnel-analytics.csv", rows);
        if (toast) toast(ok ? `Exported ${rows.length} funnel analytics row(s)` : "No funnel rows available to export", ok ? "green" : "orange");
      } catch (error) {
        console.error("[Builder360] Funnel CSV export failed", error);
        if (toast) toast("Funnel CSV export failed. Check browser download permissions.", "red");
      }
    };

    return e("div", { className: "page page-wide" },
      e(PageHead, {
        crumbs: ["Sales & CRM", "Lead Funnel Analytics"],
        title: "Lead Funnel Analytics",
        sub: "Stage-wise conversion, drop-off and lost-reason analysis from scoped CRM database records.",
        actions: [
          e(ChipSelect, { key: "project", label: "Project", value: "Current Scope" }),
          e(ChipSelect, { key: "source", label: "Source", value: metrics.source || "Unavailable" }),
          e(Button, { key: "export", icon: "download", onClick: exportFunnel, children: "Export CSV" }),
        ],
      }),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.5fr 1fr", alignItems: "start" } },
        e(Card, { title: "Conversion Funnel", sub: `${metrics.source || "server"} scoped stage counts`, pad: true },
          funnel.length ? funnel.map((stage, index) => {
            const currentCount = Number(stage.n || 0);
            const pct = Math.round(currentCount / firstStageCount * 100);
            const previousCount = Math.max(Number(funnel[index - 1]?.n || 0), 1);
            const conversion = index === 0 ? 100 : Math.round(currentCount / previousCount * 100);
            const drop = index === 0 ? 0 : Math.max(0, 100 - conversion);

            return e("div", { key: stage.stage || index, style: { marginBottom: 14 } },
              e("div", { className: "row between", style: { marginBottom: 6 } },
                e("span", { style: { fontWeight: 700, fontSize: 13 } }, stage.stage),
                e("span", { className: "row gap-2" },
                  e("span", { className: "mono", style: { fontWeight: 800, fontSize: 14 } }, number(currentCount)),
                  index > 0 && e(Badge, { tone: drop > 45 ? "b-red" : drop > 30 ? "b-orange" : "b-green" }, `-${drop}%`))),
              e("div", { style: { height: 30, borderRadius: 9, background: "var(--surface-3)", overflow: "hidden" } },
                e("div", { style: { width: `${pct}%`, height: "100%", background: stage.color || "var(--accent)", borderRadius: 9, transition: ".5s", display: "flex", alignItems: "center", paddingLeft: 12 } },
                  e("span", { style: { color: "#fff", fontWeight: 800, fontSize: 11.5 } }, `${pct}% of total`))));
          }) : e(Empty, { title: "No funnel data", sub: "No scoped lead records are available." })),
        e("div", { className: "grid", style: { gap: 16 } },
          e(Card, { title: "Overall Conversion", pad: true },
            e("div", { className: "row", style: { justifyContent: "space-around", textAlign: "center" } },
              e("div", null, e("div", { className: "mono", style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 30, color: "var(--green)" } }, percent(summary.booking_conversion_percent)), e("div", { className: "kpi-mini" }, "Lead to Booking")),
              e("div", { style: { width: 1, background: "var(--border)" } }),
              e("div", null, e("div", { className: "mono", style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 30 } }, percent(summary.visit_to_booking_percent)), e("div", { className: "kpi-mini" }, "Visit to Booking"))),
            e("div", { className: "divider" }),
            e("div", { className: "kpi-mini", style: { textAlign: "center" } }, "Biggest drop-off: ", e("b", { style: { color: "var(--red)" } }, biggestDrop))),
          e(Card, { title: "Lost / Drop Reasons", sub: "Captured disposition reasons", pad: true }, lostReasons.length ? e(HBars, { data: lostReasons }) : e(Empty, { title: "No lost reasons", sub: "No scoped lost leads have captured reasons." })),
        ),
      ),
      e("div", { className: "grid g-3", style: { marginTop: 16 } },
        e(Card, { title: "Source-wise Conversion", pad: true }, sourceConversion.length ? e(HBars, { data: sourceConversion }) : e(Empty, { title: "No source conversion", sub: "No scoped source records are available." })),
        e(Card, { title: "Project-wise Booking", pad: true }, projectBookingRates.length ? e(HBars, { data: projectBookingRates }) : e(Empty, { title: "No project booking rates", sub: "No scoped project inventory is available." })),
        e(Card, { title: "Avg. Stage Duration", pad: true }, stageDurations.length ? e(HBars, { data: stageDurations }) : e(Empty, { title: "No stage duration data", sub: "Stage transition history is not yet available for the scoped leads." })),
      ),
    );
  }

  Object.assign(window, { Dashboard, Approvals, FunnelAnalytics });
})();
