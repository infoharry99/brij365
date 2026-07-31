const React = window.React;

/* Builder360 — Role-based dashboards: Sales, Construction, Finance, HR */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, BarChart, LineChart, Gauge, Spark, HBars, PageHead, ChipSelect, Seg } = window;
  const e = React.createElement;
  const DB = window.DB;

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  }

  async function apiJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: "same-origin",
      ...options,
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        "X-Requested-With": "XMLHttpRequest",
        ...(options.headers || {}),
      },
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
      const errors = body.errors || {};
      const first = Object.values(errors).flat().filter(Boolean)[0];
      throw new Error(first || body.message || "Request failed.");
    }
    return body;
  }

  function Head({ role, sub, actions }) {
    const roleDashboardTitle = window.Builder360Server?.role_dashboard?.title;
    return e(PageHead, { title: roleDashboardTitle || "Good morning, " + role.person.split(" ")[0], sub, actions });
  }

  // simple section card with a right-aligned link
  const link = (label) => e(Button, { sm: true, variant: "ghost", icon: "chevR", children: label });

  // ---------------- SALES HEAD ----------------
  function SalesDash({ role, toast }) {
    const dashboard = window.Builder360Server?.dashboard || {};
    const kpis = dashboard.kpis || {};
    const projects = Array.isArray(dashboard.projects) ? dashboard.projects : [];
    const funnel = Array.isArray(dashboard.funnel) ? dashboard.funnel : [];
    const approvals = Array.isArray(dashboard.approvals) ? dashboard.approvals : [];
    const alerts = Array.isArray(dashboard.alerts) ? dashboard.alerts : [];
    const scope = dashboard.scope || {};
    const moneyCr = value => "₹" + Number(value || 0).toFixed(2);
    const conversion = Number(kpis.leads || 0) > 0 ? Math.round((Number(kpis.bookings || 0) / Number(kpis.leads || 1)) * 100) : 0;
    const collectionEfficiency = Number(kpis.collection || 0) + Number(kpis.outstanding || 0) > 0
      ? Math.round((Number(kpis.collection || 0) / (Number(kpis.collection || 0) + Number(kpis.outstanding || 0))) * 100)
      : 0;
    const projectRows = projects.slice(0, 6);
    const openSalesWorkspace = () => {
      window.location.hash = "#sales";
      toast && toast("Opening Sales workspace with leads, qualification, site visits, bookings and collections.", "accent");
    };

    return e("div", { className: "page page-wide" },
      e(Head, { role, sub: "Scoped sales funnel, bookings, collections and project performance from Laravel.",
        actions: [e(ChipSelect, { key: 1, label: "Scope", value: scope.level || "current user" }), e(Button, { key: 2, icon: "plus", variant: "primary", onClick: openSalesWorkspace, children: "Open Sales" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Bookings", value: String(kpis.bookings ?? 0), icon: "tag", tone: "green", sub: String(kpis.soldOnly ?? 0) + " sold/booked units" }),
        e(Stat, { label: "Booking Value", value: moneyCr(kpis.collection), unit: "Cr", icon: "rupee", tone: "accent", sub: moneyCr(kpis.outstanding) + " Cr outstanding" }),
        e(Stat, { label: "Site Visits", value: String(kpis.siteVisits ?? 0), icon: "calendar", tone: "orange", sub: String(kpis.verified ?? 0) + " verified/qualified" }),
        e(Stat, { label: "Leads", value: String(kpis.leads ?? 0), icon: "flame", tone: "red", sub: conversion + "% lead-to-booking conversion" }),
      ),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.5fr 1fr", alignItems: "start" } },
        e("div", { className: "grid", style: { gap: 16 } },
          e(Card, { title: "Project Sales Performance", sub: "Revenue, collections and unit conversion from Laravel", action: link("Full report") },
            e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
              e("thead", null, e("tr", null, ["Project", "Units", "Booked/Sold", "Revenue", "Collected", "Collection %", "Health"].map((h, i) => e("th", { key: h, style: i > 2 && i < 5 ? { textAlign: "right" } : {} }, h)))),
              e("tbody", null, (projectRows.length ? projectRows : [{ id: "empty", name: "No scoped projects", units: 0, sold: 0, revenue: 0, collected: 0, health: 0 }]).map((p, i) => {
                const collected = Number(p.collected || 0);
                const revenue = Number(p.revenue || 0);
                const pct = revenue > 0 ? Math.round((collected / revenue) * 100) : 0;
                return e("tr", { key: p.db_id || p.id || i },
                  e("td", null, e("div", { className: "cell-user" }, e("i", { style: { width: 9, height: 26, borderRadius: 3, background: p.color || "var(--accent)" } }), e("span", { className: "cell-strong" }, p.name || "Project"))),
                  e("td", { className: "num mono" }, String(p.units || 0)),
                  e("td", { className: "num mono cell-strong" }, String(p.sold || 0)),
                  e("td", { className: "num mono" }, moneyCr(revenue)),
                  e("td", { className: "num mono" }, moneyCr(collected)),
                  e("td", null, e(Badge, { tone: pct >= 80 ? "b-green" : pct >= 50 ? "b-orange" : "b-red" }, pct + "%")),
                  e("td", null, e("div", { className: "prog-cell" }, e(Bar, { value: Number(p.health || 0), w: 56 }), e("span", { className: "pv" }, String(p.health || 0)))));
              }))
            ))),
          e("div", { className: "grid g-2" },
            e(Card, { title: "Funnel This Month", sub: "Laravel lead → booking", pad: true },
              (funnel.length ? funnel : [{ stage: "No funnel data", n: 0, color: "var(--muted)" }]).slice(0, 7).map((f, i) => e("div", { key: f.stage || i, className: "row between", style: { padding: "7px 0", borderBottom: i < Math.min((funnel.length || 1), 7) - 1 ? "1px solid var(--border)" : "none" } },
                e("span", { style: { fontSize: 12.5, fontWeight: 600 } }, f.stage),
                e("span", { className: "row gap-2" }, e("span", { className: "mono", style: { fontWeight: 700 } }, String(f.n || 0)), e("i", { style: { width: 6, height: 6, borderRadius: 99, background: f.color || "var(--accent)" } }))))),
            e(Card, { title: "Collection Performance", sub: "approved collections vs outstanding", pad: true },
              e("div", { className: "center", style: { paddingTop: 6 } }, e(Gauge, { value: collectionEfficiency, color: "var(--accent)", label: "% collected" })),
              e("div", { className: "divider" }),
              e("div", { className: "row between", style: { fontSize: 13 } }, e("span", { className: "muted" }, moneyCr(kpis.collection) + " Cr collected"), e(Badge, { tone: kpis.outstanding > 0 ? "b-orange" : "b-green" }, moneyCr(kpis.outstanding) + " Cr outstanding"))),
          )),
        e("div", { className: "grid", style: { gap: 16 } },
          e(Card, { title: "Sales Workflow Queue", sub: String(approvals.length) + " scoped approval item(s)" },
            (approvals.length ? approvals.slice(0, 5) : [{ id: "empty", type: "Approvals", desc: "No pending sales approvals in current scope", amt: "0", b: "b-slate" }]).map((a, i) =>
              e("div", { key: a.id || i, className: "row gap-3", style: { padding: "11px 16px", borderBottom: i < Math.min((approvals.length || 1), 5) - 1 ? "1px solid var(--border)" : "none" } },
                e("div", { style: { flex: 1 } }, e("div", { style: { fontWeight: 700, fontSize: 13 } }, a.type || "Approval"), e("div", { className: "cell-sub" }, a.desc || a.description || "Workflow item")),
                e(Badge, { tone: a.b || "b-blue" }, a.amt || a.status || "View"))),
          ),
          e(Card, { title: "Sales Alerts", sub: "Laravel dashboard alerts", pad: true },
            (alerts.length ? alerts.slice(0, 4) : [{ title: "No active alerts", sub: "Lead, booking, collection and approval alerts will appear here.", tone: "b-green" }]).map((alert, i) =>
              e("div", { key: alert.title || i, className: "row between", style: { padding: "7px 0", borderBottom: i < Math.min((alerts.length || 1), 4) - 1 ? "1px solid var(--border)" : "none" } },
                e("span", { style: { fontSize: 12.5, fontWeight: 700 } }, alert.title || alert.type || "Alert"),
                e(Badge, { tone: alert.tone || "b-orange" }, alert.sub || alert.status || "Open")))),
        ),
      ),
    );
  }

  // ---------------- CONSTRUCTION HEAD ----------------
  function ConstructionDash({ role, toast }) {
    const dashboard = window.Builder360Server?.dashboard || {};
    const siteOptions = window.Builder360Server?.construction_site_options || {};
    const kpis = dashboard.kpis || {};
    const projects = Array.isArray(dashboard.projects) ? dashboard.projects : [];
    const alerts = Array.isArray(dashboard.alerts) ? dashboard.alerts : [];
    const milestones = Array.isArray(siteOptions.milestones) ? siteOptions.milestones : [];
    const dailyReports = Array.isArray(siteOptions.daily_reports) ? siteOptions.daily_reports : [];
    const summary = siteOptions.summary || {};
    const openConstructionWorkspace = () => {
      window.location.hash = "#construction";
      toast && toast("Opening Construction workspace with milestones, DPR, stores and procurement.", "accent");
    };
    const projectRows = projects.slice(0, 6);
    const delayedRows = milestones.filter(row => ["delayed", "blocked"].includes(row.status)).slice(0, 5);
    const averageHealth = projectRows.length ? Math.round(projectRows.reduce((sum, row) => sum + Number(row.health || 0), 0) / projectRows.length) : 0;
    const progressTrend = projectRows.length ? projectRows.slice(0, 6).map(row => Number(row.progress || 0)) : [0];
    const healthTrend = projectRows.length ? projectRows.slice(0, 6).map(row => Number(row.health || 0)) : [0];

    return e("div", { className: "page page-wide" },
      e(Head, { role, sub: "Execution health, delays and site activity from scoped Laravel construction records.",
        actions: [e(ChipSelect, { key: 1, label: "Site", value: dashboard.scope?.level || "current scope" }), e(Button, { key: 2, icon: "hardhat", onClick: openConstructionWorkspace, children: "Open Construction" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Active Sites", value: String(kpis.activeSites ?? summary.active_milestones ?? 0), icon: "hardhat", tone: "accent", sub: String(kpis.projects ?? projects.length) + " scoped project(s)" }),
        e(Stat, { label: "Avg. Progress", value: String(summary.average_progress ?? 0), unit: "%", icon: "trend", tone: "blue", sub: "Milestone progress from Laravel" }),
        e(Stat, { label: "Delayed Activities", value: String(summary.delayed_milestones ?? delayedRows.length), icon: "alert", tone: "red", sub: "Delayed / blocked milestones" }),
        e(Stat, { label: "Open Site Issues", value: String(summary.open_site_issues ?? 0), icon: "headset", tone: "orange", sub: "DPR blockers in current scope" }),
      ),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.5fr 1fr", alignItems: "start" } },
        e("div", { className: "grid", style: { gap: 16 } },
          e(Card, { title: "Site Progress Overview", sub: "Laravel project progress and health", action: link("Daily reports") },
            e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
              e("thead", null, e("tr", null, ["Project", "Status", "Units", "Progress", "Health", "Risk"].map((h, i) => e("th", { key: h }, h)))),
              e("tbody", null, (projectRows.length ? projectRows : [{ id: "empty", name: "No scoped construction projects", status: "—", units: 0, progress: 0, health: 0 }]).map((p, i) =>
                e("tr", { key: p.db_id || p.id || i },
                  e("td", null, e("div", { className: "cell-user" }, e("i", { style: { width: 9, height: 28, borderRadius: 3, background: p.color || "var(--accent)" } }), e("span", { className: "cell-strong" }, p.name || "Project"))),
                  e("td", { style: { fontSize: 12.5 } }, p.status || "active"),
                  e("td", { className: "num mono" }, String(p.units || 0)),
                  e("td", { className: "cell-strong" }, String(p.progress || 0) + "%"),
                  e("td", null, e(ProgCell, { value: Number(p.health || 0) })),
                  e("td", null, e(Badge, { tone: Number(p.health || 0) >= 70 ? "b-green" : Number(p.health || 0) >= 40 ? "b-orange" : "b-red", dot: true }, Number(p.health || 0) >= 70 ? "On Track" : Number(p.health || 0) >= 40 ? "Watch" : "At Risk"))))),
            ))),
          e("div", { className: "grid g-2" },
            e(Card, { title: "Progress vs Health", sub: "Scoped project sequence", pad: true },
              e(LineChart, { height: 150, labels: projectRows.length ? projectRows.slice(0, 6).map(row => row.code || row.name || "Project") : ["No data"], series: [{ data: progressTrend, color: "var(--accent)", fill: true }, { data: healthTrend, color: "var(--orange)" }] })),
            e(Card, { title: "Delayed / Blocked Milestones", sub: "Laravel milestone queue", pad: true },
              (delayedRows.length ? delayedRows : [{ name: "No delayed milestones", project: { name: "Current scope" }, progress_percent: 0, status: "clear" }]).map((row, i) =>
                e("div", { key: row.id || row.name || i, className: "row between", style: { padding: "7px 0", borderBottom: i < Math.min((delayedRows.length || 1), 5) - 1 ? "1px solid var(--border)" : "none" } },
                  e("span", { style: { fontSize: 12.5, fontWeight: 600 } }, row.name || "Milestone"),
                  e(Badge, { tone: row.status === "clear" ? "b-green" : "b-red" }, (row.status || "open") + " · " + String(row.progress_percent || 0) + "%")))),
          )),
        e("div", { className: "grid", style: { gap: 16 } },
          e(Card, { title: "Critical Alerts" },
            (alerts.length ? alerts.slice(0, 4) : [{ title: "No construction alerts", sub: "Material, DPR, milestone and approval alerts will appear here.", tone: "b-green" }]).map((item, i) =>
              e("div", { key: item.title || i, className: "row gap-3", style: { padding: "11px 16px", borderBottom: i < Math.min((alerts.length || 1), 4) - 1 ? "1px solid var(--border)" : "none" } },
                e("div", { style: { width: 30, height: 30, borderRadius: 8, background: "var(--surface-3)", color: item.color || "var(--orange)", display: "grid", placeItems: "center", flex: "0 0 30px" } }, e(Icon, { name: item.icon || "alert", size: 16 })),
                e("div", null, e("div", { style: { fontSize: 12.5, fontWeight: 700 } }, item.title || item.type || "Alert"), e("div", { className: "cell-sub", style: { whiteSpace: "normal" } }, item.sub || item.description || "Open item"))))),
          e(Card, { title: "Daily Reports Today", sub: "Laravel DPR summary", pad: true },
            e("div", { className: "row between", style: { marginBottom: 12 } }, e("span", { className: "muted", style: { fontSize: 13 } }, "Submitted"), e("span", { className: "mono", style: { fontWeight: 800, fontSize: 20 } }, String(summary.reports_today ?? 0))),
            e("div", { className: "bar", style: { width: "100%", height: 8 } }, e("i", { style: { width: Math.max(Math.min(Number(summary.reports_today || 0) * 10, 100), 0) + "%", background: "var(--green)" } })),
            e("div", { className: "divider" }),
            e("div", { className: "row between", style: { fontSize: 13 } }, e("span", { className: "muted" }, "Latest DPR rows"), e(Badge, { tone: "b-blue" }, String(dailyReports.length))),
            e("div", { className: "divider" }),
            e("div", { className: "row between", style: { fontSize: 13 } }, e("span", { className: "muted" }, "Average project health"), e(Badge, { tone: averageHealth >= 70 ? "b-green" : averageHealth >= 40 ? "b-orange" : "b-red" }, String(averageHealth) + "%"))),
        ),
      ),
    );
  }

  // ---------------- FINANCE HEAD ----------------
  function FinanceDash({ role, toast }) {
    const financeDashboard = window.Builder360Server?.finance_dashboard || null;
    const cashPosition = financeDashboard?.cash_position || {};
    const periodSummary = financeDashboard?.period_summary || {};
    const receivables = financeDashboard?.receivables || {};
    const payables = financeDashboard?.payables || {};
    const gst = financeDashboard?.gst || {};
    const approvals = financeDashboard?.approvals || {};
    const recent = financeDashboard?.recent_activity || {};
    const period = financeDashboard?.period || {};
    const money = amount => "₹" + Number(amount || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
    const moneyCr = amount => "₹" + (Number(amount || 0) / 10000000).toFixed(2);
    const openFinanceWorkspace = () => {
      window.location.hash = "#finance";
      toast && toast("Opening Finance workspace with vouchers, payment requests, ledgers, GST and cash-flow registers.", "accent");
    };
    const approvalRows = [
      ["Collection receipts", approvals.submitted_collection_receipts || 0, "b-green"],
      ["Payment vouchers", approvals.submitted_payment_vouchers || 0, "b-orange"],
      ["Finance vouchers", approvals.submitted_finance_vouchers || 0, "b-blue"],
      ["GST entries", approvals.submitted_gst_entries || 0, "b-violet"],
      ["Payment links", approvals.requested_payment_links || 0, "b-slate"],
    ];
    const ageingBuckets = receivables.aging_buckets || {};
    const ageingChart = [
      { label: "Not due", value: Number(ageingBuckets.not_due_or_no_due_date || 0), color: "var(--green)" },
      { label: "Overdue", value: Number(ageingBuckets.overdue || 0), color: "var(--red)" },
      { label: "0-30d", value: Number(ageingBuckets.due_0_30 || 0), color: "var(--accent)" },
      { label: "31-60d", value: Number(ageingBuckets.due_31_60 || 0), color: "var(--orange)" },
      { label: "61d+", value: Number(ageingBuckets.due_61_plus || 0), color: "var(--red)" },
    ];
    const recentCollections = Array.isArray(recent.collections) ? recent.collections.slice(0, 5) : [];
    const gstRows = Array.isArray(gst.by_transaction_type) && gst.by_transaction_type.length
      ? gst.by_transaction_type.slice(0, 4)
      : [{ transaction_type: "No approved GST entries", entry_count: 0, taxable_amount: 0, total_tax_amount: 0 }];

    return e("div", { className: "page page-wide" },
      e(Head, { role, sub: "Collections, spend, payables and cash position across scoped Laravel records.",
        actions: [e(ChipSelect, { key: 1, label: "Period", value: (period.date_from || "Current") + " → " + (period.date_to || "today") }), e(Button, { key: 2, icon: "download", onClick: openFinanceWorkspace, children: "Open Finance" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Cash Position", value: moneyCr(cashPosition.net_cash_position), unit: "Cr", icon: "wallet", tone: Number(cashPosition.net_cash_position || 0) >= 0 ? "green" : "red", sub: "As of " + (cashPosition.as_of_date || "current period") }),
        e(Stat, { label: "Collections", value: moneyCr(periodSummary.approved_collections), unit: "Cr", icon: "rupee", tone: "accent", sub: "Approved receipts in selected period" }),
        e(Stat, { label: "Outstanding", value: moneyCr(receivables.schedule_outstanding), unit: "Cr", icon: "clock", tone: "orange", sub: money(receivables.overdue_outstanding) + " overdue" }),
        e(Stat, { label: "Payables Due", value: moneyCr(payables.forecast_outflow), unit: "Cr", icon: "alert", tone: "red", sub: "Forecast outflow next " + String(period.forecast_days || 90) + " days" }),
      ),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.5fr 1fr", alignItems: "start" } },
        e("div", { className: "grid", style: { gap: 16 } },
          e("div", { className: "grid g-2" },
            e(Card, { title: "Cash Flow Forecast", sub: "Laravel approved cash in/out", pad: true },
              e("div", { className: "grid g-2", style: { gap: 12 } },
                [
                  ["Opening cash", cashPosition.net_cash_position, "var(--green)"],
                  ["Forecast inflow", receivables.forecast_inflow, "var(--accent)"],
                  ["Forecast outflow", payables.forecast_outflow, "var(--red)"],
                  ["Net period flow", periodSummary.net_period_flow, "var(--violet)"],
                ].map(row => e("div", { key: row[0] }, e("div", { className: "mono", style: { fontWeight: 800, fontSize: 20, color: row[2] } }, money(row[1])), e("div", { className: "kpi-mini" }, row[0]))))),
            e(Card, { title: "Outstanding Ageing", sub: "Laravel receivable buckets", pad: true }, e(BarChart, { height: 160, data: ageingChart })),
          ),
          e(Card, { title: "Recent Collections", sub: "Approved/submitted Laravel receipts", action: link("Finance workspace") },
            e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
              e("thead", null, e("tr", null, ["Receipt", "Customer", "Project", "Amount", "Date", "Status"].map((h, i) => e("th", { key: h, style: i === 3 ? { textAlign: "right" } : {} }, h)))),
              e("tbody", null, (recentCollections.length ? recentCollections : [{ receipt_number: "—", customer: "No collection receipts in current scope", project: "—", amount: 0, receipt_date: "—", status: "—" }]).map((row, i) =>
                e("tr", { key: row.receipt_number || i },
                  e("td", { className: "mono cell-strong" }, row.receipt_number || "—"),
                  e("td", null, row.customer || "Customer"),
                  e("td", null, row.project || "Company"),
                  e("td", { className: "num mono" }, money(row.amount)),
                  e("td", { className: "faint" }, row.receipt_date || "—"),
                  e("td", null, e(Badge, { tone: row.status === "approved" ? "b-green" : "b-orange" }, row.status || "—"))))))),
        ),
        e("div", { className: "grid", style: { gap: 16 } },
          e(Card, { title: "Financial Approvals", sub: "Laravel pending workflow counts", action: e("span", { className: "badge b-red" }, String(approvalRows.reduce((sum, row) => sum + Number(row[1] || 0), 0))) },
            approvalRows.map((row, i) =>
              e("div", { key: row[0], className: "row gap-2", style: { padding: "11px 16px", borderBottom: i < approvalRows.length - 1 ? "1px solid var(--border)" : "none", alignItems: "center" } },
                e("div", { style: { flex: 1, minWidth: 0 } }, e("div", { className: "row gap-2" }, e(Badge, { tone: row[2] }, row[0])), e("div", { style: { fontSize: 12, fontWeight: 600, marginTop: 3 } }, "Scoped workflow queue")),
                e("span", { className: "mono", style: { fontWeight: 700, fontSize: 12.5 } }, String(row[1]))))),
          e(Card, { title: "Payables & Loans", sub: "Laravel payable controls", pad: true },
            [
              ["Submitted payment vouchers", payables.submitted_payment_vouchers],
              ["Approved claims not paid", payables.approved_claims_not_paid],
              ["Approved loans not disbursed", payables.approved_loans_not_disbursed],
              ["Period payment vouchers", periodSummary.approved_payment_vouchers],
            ].map((row, i) =>
              e("div", { key: row[0], className: "row between", style: { padding: "7px 0", fontSize: 13, borderBottom: i < 3 ? "1px solid var(--border)" : "none" } }, e("span", { className: "muted" }, row[0]), e("span", { className: "mono", style: { fontWeight: 700 } }, money(row[1]))))),
          e(Card, { title: "GST Summary", sub: "Approved GST entries", pad: true },
            e("div", { className: "row between", style: { marginBottom: 8 } }, e("span", { className: "muted", style: { fontSize: 13 } }, "Tax amount"), e("span", { className: "mono", style: { fontWeight: 800, fontSize: 18 } }, money(gst.total_tax_amount))),
            e("div", { className: "kpi-mini" }, String(gst.approved_entry_count || 0) + " entries · taxable " + money(gst.taxable_amount)),
            e("div", { className: "divider" }),
            gstRows.map(row => e("div", { key: row.transaction_type, className: "row between", style: { fontSize: 12.5, padding: "4px 0" } }, e("span", null, row.transaction_type), e("span", { className: "mono" }, money(row.total_tax_amount))))),
        ),
      ),
    ));
  }

  // ---------------- HR MANAGER ----------------
  function HRDash({ role, toast }) {
    const dashboardOptions = window.Builder360Server?.hr_dashboard_options || null;
    const summary = dashboardOptions?.summary || {};
    const departmentHeadcount = Array.isArray(dashboardOptions?.department_headcount)
      ? dashboardOptions.department_headcount.slice(0, 6)
      : [];
    const manpowerChart = departmentHeadcount.length
      ? departmentHeadcount.map((row, index) => ({
        label: row.department || "Unassigned",
        value: Number(row.employees || 0),
        color: ["#F6740C", "#15a657", "#e08600", "#2570eb", "#7c3aed", "#64748b"][index % 6],
      }))
      : [{ label: "No scoped employees", value: 0, color: "#64748b" }];
    const payrollCr = Number(summary.latest_payroll_net_payable || 0) / 10000000;
    const attendanceValue = summary.attendance_today_percent == null ? "—" : String(summary.attendance_today_percent);
    const attendanceUnit = summary.attendance_today_percent == null ? "" : "%";
    const approvalOptions = window.Builder360Server?.approval_inbox_options || null;
    const initialLeaveApprovals = Array.isArray(approvalOptions?.rows)
      ? approvalOptions.rows.filter(row => row.type === "Leave Approval" || row.source_module === "hr").slice(0, 3)
      : [];
    const [leaveApprovals, setLeaveApprovals] = React.useState(initialLeaveApprovals);
    const [busyApprovalId, setBusyApprovalId] = React.useState(null);
    const approveLeave = async item => {
      if (!item.can_approve || !item.approve_url) {
        toast && toast("This leave approval is visible for tracking, but your role cannot approve it here.", "orange");
        return;
      }
      const noteKey = item.approve_payload_key || "decision_note";
      setBusyApprovalId(item.id);
      try {
        await apiJson(item.approve_url, { method: "PATCH", body: JSON.stringify({ [noteKey]: "Approved from HR Manager dashboard." }) });
        setLeaveApprovals(rows => rows.map(row => row.id === item.id ? { ...row, status: "approved", can_approve: false, approve_url: null } : row));
        toast && toast((item.number || item.id) + " approved through Laravel workflow.", "green");
      } catch (error) {
        toast && toast(error.message || "Leave approval failed.", "red");
      } finally {
        setBusyApprovalId(null);
      }
    };
    const reviewApprovalCenter = () => {
      window.location.hash = "#approvals";
      toast && toast("Opening governed Approval Center for rejection, remarks and full workflow history.", "accent");
    };
    const openPayrollWorkspace = () => {
      window.location.hash = "#hr/payroll";
      toast && toast("Opening Payroll Admin workspace with Laravel payroll runs, salary structures and bank batches.", "accent");
    };
    const openEmployeeMaster = () => {
      window.location.hash = "#hr/employees";
      toast && toast("Opening Employee Master workspace with governed employee records and profile tabs.", "accent");
    };

    return e("div", { className: "page page-wide" },
      e(Head, { role, sub: "Headcount, attendance, payroll and recruitment overview.",
        actions: [e(Button, { key: 1, icon: "wallet", onClick: openPayrollWorkspace, children: "Run Payroll" }), e(Button, { key: 2, icon: "plus", variant: "primary", onClick: openEmployeeMaster, children: "Add Employee" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Total Employees", value: String(summary.total_headcount ?? 0), icon: "id", tone: "accent", sub: String(summary.active_headcount ?? 0) + " active · scoped Laravel employees" }),
        e(Stat, { label: "Attendance Today", value: attendanceValue, unit: attendanceUnit, icon: "check", tone: "green", sub: String(summary.attendance_present_today ?? 0) + " present of " + String(summary.attendance_marked_today ?? 0) + " marked" }),
        e(Stat, { label: "Payroll Cost (MTD)", value: "₹" + payrollCr.toFixed(2), unit: "Cr", icon: "rupee", tone: "violet", sub: summary.latest_payroll_label || "No payroll run" }),
        e(Stat, { label: "Open Vacancies", value: String(summary.open_positions ?? 0), icon: "users", tone: "orange", sub: String(summary.candidate_pipeline ?? 0) + " candidates in pipeline" }),
      ),
      e("div", { className: "grid", style: { gridTemplateColumns: "1fr 1fr", alignItems: "start" } },
        e("div", { className: "grid", style: { gap: 16 } },
          e(Card, { title: "Manpower by Department", sub: "scoped Laravel headcount", pad: true },
            e(BarChart, { height: 168, data: manpowerChart })),
          e(Card, { title: "Recruitment Pipeline", sub: String(summary.open_positions ?? 0) + " open positions" },
            [
              ["Open positions", String(summary.open_positions ?? 0), "b-orange"],
              ["Candidate pipeline", String(summary.candidate_pipeline ?? 0), "b-blue"],
              ["Pending HR approvals", String(summary.pending_approvals ?? 0), "b-violet"],
            ].map((r, i) =>
              e("div", { key: r[0], className: "row between", style: { padding: "12px 16px", borderBottom: i < 2 ? "1px solid var(--border)" : "none" } },
                e("div", null, e("div", { className: "cell-strong" }, r[0]), e("div", { className: "cell-sub" }, "Laravel scoped HR dashboard metric")),
                e(Badge, { tone: r[2] }, r[1])))),
        ),
        e("div", { className: "grid", style: { gap: 16 } },
          e(Card, { title: "Attendance Today Snapshot", sub: "scoped Laravel attendance", pad: true },
            e("div", { className: "grid g-2", style: { gap: 12 } },
              [
                ["Present", String(summary.attendance_present_today ?? 0), "var(--green)"],
                ["Marked", String(summary.attendance_marked_today ?? 0), "var(--accent)"],
                ["Pending regularizations", String(summary.pending_attendance_regularizations ?? 0), "var(--orange)"],
                ["Open helpdesk", String(summary.open_helpdesk_tickets ?? 0), "var(--blue)"],
              ].map((r, i) =>
                e("div", { key: r[0] }, e("div", { className: "mono", style: { fontWeight: 800, fontSize: 20, color: r[2] } }, r[1]), e("div", { className: "kpi-mini" }, r[0]))))),
          e(Card, { title: "Leave & Approvals", sub: "pending" },
            leaveApprovals.length
              ? leaveApprovals.map((item, i) =>
                e("div", { key: item.id, className: "row between", style: { padding: "11px 16px", borderBottom: i < leaveApprovals.length - 1 ? "1px solid var(--border)" : "none", gap: 10 } },
                  e("div", { style: { minWidth: 0 } },
                    e("div", { style: { fontSize: 13, fontWeight: 700, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" } }, item.raised_by || "Employee"),
                    e("div", { className: "cell-sub" }, (item.number || item.id) + " · " + (item.description || "Leave request") + " · " + (item.status || "submitted"))),
                  e("div", { className: "row gap-2" },
                    e(Button, { sm: true, variant: "success", disabled: busyApprovalId === item.id || !item.can_approve, onClick: () => approveLeave(item), children: busyApprovalId === item.id ? "Approving..." : "Approve" }),
                    e(Button, { sm: true, variant: "ghost", onClick: reviewApprovalCenter, children: "Review" }))))
              : e("div", { className: "empty", style: { padding: 16 } }, "No Laravel leave approvals are pending in your current scope.")),
          e(Card, { title: "This Month", sub: "Laravel HR workflow summary", pad: true },
            e("div", { className: "grid g-2", style: { gap: 12 } },
              [
                ["Leave pending", String(summary.pending_leave_requests ?? 0), "var(--green)"],
                ["Payroll pending", String(summary.pending_payroll_runs ?? 0), "var(--accent)"],
                ["Performance pending", String(summary.pending_performance_reviews ?? 0), "var(--violet)"],
                ["Compliance alerts", String(summary.compliance_alerts ?? 0), "var(--orange)"],
              ].map((r, i) =>
                e("div", { key: r[0] }, e("div", { className: "mono", style: { fontWeight: 800, fontSize: 20, color: r[2] } }, r[1]), e("div", { className: "kpi-mini" }, r[0]))))),
        ),
      ),
    );
  }

  Object.assign(window, { SalesDash, ConstructionDash, FinanceDash, HRDash });
})();
