const React = window.React;

/* Builder360 - Sales Performance Analytics */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, HBars, BarChart, PageHead, ChipSelect, Empty } = window;
  const e = React.createElement;

  function T(head, rows) {
    const th = head.map((h, i) => e("th", { key: i, style: h.r ? { textAlign: "right" } : {} }, h.l != null ? h.l : h));
    const body = rows.length
      ? rows.map((r, i) => e("tr", { key: i }, r.map((c, j) => e("td", { key: j, className: head[j] && head[j].r ? "num" : "" }, c))))
      : [e("tr", { key: "empty" }, e("td", { colSpan: head.length }, e(Empty, { title: "No scoped rows", sub: "No records are available for the current role and scope." })))];

    return e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" }, e("thead", null, e("tr", null, th)), e("tbody", null, body)));
  }

  const userCell = (name, sub) => e("div", { className: "cell-user" },
    e(Avatar, { name, sm: true }),
    e("div", null, e("div", { className: "cell-strong" }, name), sub && e("div", { className: "cell-sub" }, sub)));
  const money = value => e("span", { className: "mono cell-strong" }, value || "-");
  const num = value => e("span", { className: "mono" }, value ?? 0);
  const rank = i => e("span", { className: "mono", style: { fontWeight: 800, color: i === 0 ? "var(--green)" : i < 3 ? "var(--accent)" : "var(--text-3)", fontSize: 13 } }, "#" + (i + 1));
  const safeRows = value => Array.isArray(value) ? value : [];
  const csvCell = value => {
    let text = value === null || value === undefined ? "" : String(value);
    if (/^[=+\-@]/.test(text)) text = "'" + text;
    return /[",\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
  };
  const downloadCsv = (filename, rows) => {
    if (!rows.length) return false;
    const headers = Object.keys(rows[0]);
    const csv = [headers.join(","), ...rows.map(row => headers.map(header => csvCell(row[header])).join(","))].join("\n");
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
  const tgt = pct => e("div", { style: { display: "inline-flex", alignItems: "center", gap: 8 } },
    e(Bar, { value: Math.min(Number(pct || 0), 100), w: 58 }),
    e("span", { className: "mono", style: { fontWeight: 700, fontSize: 12.5, color: pct >= 100 ? "var(--green)" : pct >= 80 ? "var(--text-2)" : "var(--red)" } }, Number(pct || 0) + "%"));

  function Performance({ toast }) {
    const [tab, setTab] = React.useState("Sales Team");
    const tabs = ["Sales Team", "Pre-Sales / Qualifiers", "Site & Project", "Department View"];
    const serverMetrics = window.Builder360Server?.sales_performance_metrics || null;
    const hasPerformanceApi = serverMetrics?.source === "laravel-sqlite";
    const performanceMetrics = hasPerformanceApi ? serverMetrics : {
      source: "unavailable",
      summary: {},
      sales_rows: [],
      revenue_leaderboard: [],
      target_chart: [],
    };
    const summary = performanceMetrics.summary || {};
    const salesRows = hasPerformanceApi ? safeRows(performanceMetrics.sales_rows) : [];
    const revenueLeaderboard = hasPerformanceApi && safeRows(performanceMetrics.revenue_leaderboard).length
      ? performanceMetrics.revenue_leaderboard
      : hasPerformanceApi
        ? salesRows.map((row, i) => ({ label: row.name, value: Number(row.revenue || 0) / 10000000, display: row.rev || "-", color: row.color || ["var(--green)", "var(--accent)", "var(--blue)", "var(--orange)", "var(--red)"][i % 5] }))
        : [];
    const targetChart = hasPerformanceApi && safeRows(performanceMetrics.target_chart).length
      ? performanceMetrics.target_chart
      : hasPerformanceApi
        ? salesRows.map(row => ({ label: String(row.name || "User").split(" ")[0], value: row.tpct || 0, color: row.tpct >= 100 ? "var(--green)" : row.tpct >= 80 ? "var(--orange)" : "var(--red)" }))
        : [];
    const exportCsv = (filename, rows, label) => {
      try {
        const ok = downloadCsv(filename, rows);
        if (toast) toast(ok ? `Exported ${rows.length} ${label} row(s)` : `No ${label} rows available to export`, ok ? "green" : "orange");
      } catch (error) {
        console.error("[Builder360] Performance CSV export failed", error);
        if (toast) toast("Performance CSV export failed. Check browser download permissions.", "red");
      }
    };
    const performanceRowsForExport = () => salesRows.map((row, i) => ({
      rank: i + 1,
      performer: row.name || "",
      project_or_scope: row.proj || "",
      assigned_leads: row.assigned ?? 0,
      verified_leads: row.verified ?? 0,
      site_visits: row.visits ?? 0,
      bookings: row.bookings ?? 0,
      revenue_display: row.rev || "",
      conversion_percent: row.conv ?? 0,
      response_time: row.resp || "",
      missed_followups: row.missed ?? 0,
      target_percent: row.tpct ?? 0,
      incentive_display: row.inc || "",
      metric_source: performanceMetrics.source || "unavailable",
    }));
    const fullReportRows = () => [
      ...performanceRowsForExport().map(row => ({ section: "sales_rows", ...row })),
      ...safeRows(revenueLeaderboard).map((row, i) => ({
        section: "revenue_leaderboard",
        rank: i + 1,
        performer: row.label || "",
        project_or_scope: "",
        assigned_leads: "",
        verified_leads: "",
        site_visits: "",
        bookings: "",
        revenue_display: row.display || "",
        conversion_percent: "",
        response_time: "",
        missed_followups: "",
        target_percent: "",
        incentive_display: "",
        metric_source: performanceMetrics.source || "unavailable",
      })),
      ...safeRows(targetChart).map((row, i) => ({
        section: "target_chart",
        rank: i + 1,
        performer: row.label || "",
        project_or_scope: "",
        assigned_leads: "",
        verified_leads: "",
        site_visits: "",
        bookings: "",
        revenue_display: "",
        conversion_percent: "",
        response_time: "",
        missed_followups: "",
        target_percent: row.value ?? 0,
        incentive_display: "",
        metric_source: performanceMetrics.source || "unavailable",
      })),
    ];
    const refreshMetrics = () => {
      toast("Refreshing performance metrics from server scope", "accent");
      window.setTimeout(() => {
        if (location.hash !== "#performance") location.hash = "#performance";
        location.reload();
      }, 200);
    };

    const salesTable = e(Card, {
      title: "Sales Executive Performance",
      sub: "Leads, qualification, visits, bookings, revenue and target achievement from scoped database records",
      action: e(Button, { sm: true, icon: "download", onClick: () => exportCsv("builder360-sales-performance-rows.csv", performanceRowsForExport(), "performance"), children: "Export CSV" }),
    }, T([
      { l: "#" },
      { l: "Executive / Partner" },
      { l: "Assigned", r: true },
      { l: "Verified", r: true },
      { l: "Visits", r: true },
      { l: "Bookings", r: true },
      { l: "Revenue", r: true },
      { l: "Conv %", r: true },
      { l: "Resp.", r: true },
      { l: "Missed", r: true },
      { l: "Target" },
      { l: "Incentive", r: true },
    ], salesRows.map((row, i) => [
      rank(i),
      userCell(row.name, row.proj),
      num(row.assigned),
      num(row.verified),
      num(row.visits),
      e("span", { className: "mono cell-strong" }, row.bookings ?? 0),
      money(row.rev),
      e("span", { className: "mono", style: { fontWeight: 700, color: row.conv >= 8 ? "var(--green)" : row.conv >= 6 ? "var(--orange)" : "var(--red)" } }, Number(row.conv || 0) + "%"),
      num(row.resp),
      e("span", { className: "mono", style: { color: row.missed > 6 ? "var(--red)" : "var(--text-2)" } }, row.missed ?? 0),
      tgt(row.tpct),
      row.inc === "-" || row.inc === "â€”" ? e("span", { className: "faint" }, "-") : e("span", { className: "mono", style: { fontWeight: 700, color: "var(--violet)" } }, row.inc),
    ])));

    const qualifierTable = e(Card, { title: "Lead Qualifier Performance", sub: "Verification metrics are pending a dedicated qualifier workflow feed" },
      T([{ l: "#" }, { l: "Qualifier" }, { l: "Calls", r: true }, { l: "Verified", r: true }, { l: "Hot Leads", r: true }, { l: "Passed to Sales", r: true }, { l: "Verify %", r: true }, { l: "Avg SLA", r: true }, { l: "Quality" }], []));

    const siteTable = e(Card, { title: "Site & Project Team Performance", sub: "Execution metrics are available in construction dashboards and reports" },
      T([{ l: "#" }, { l: "Member" }, { l: "Role" }, { l: "Project" }, { l: "Progress" }, { l: "Plan Adherence", r: true }, { l: "Delays", r: true }, { l: "Budget" }, { l: "Quality" }], []));

    const departmentTable = e(Card, { title: "Department-wise Performance", sub: "Department aggregates will use HR performance cycles and operational metrics" },
      T([{ l: "Department" }, { l: "Head" }, { l: "Team Size", r: true }, { l: "Performance Score" }, { l: "Target Achv." }], []));

    const content = {
      "Sales Team": salesTable,
      "Pre-Sales / Qualifiers": qualifierTable,
      "Site & Project": siteTable,
      "Department View": departmentTable,
    }[tab];

    return e("div", { className: "page page-wide" },
      e(PageHead, {
        crumbs: ["Analytics", "Performance"],
        title: "Employee Performance Analytics",
        sub: "Cross-role productivity from database-backed sales, booking and collection records.",
        actions: [
          e(ChipSelect, { key: 1, label: "Period", value: "Current Scope" }),
          e(Button, { key: 2, icon: "download", variant: "primary", onClick: () => exportCsv("builder360-sales-performance-report.csv", fullReportRows(), "performance report"), children: "Export Report" }),
        ],
      }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Top Performer", value: summary.top_performer || "No performer", icon: "star", tone: "green", sub: summary.top_performer_sub || "No sales data available" }),
        e(Stat, { label: "Team Target Achv.", value: String(summary.team_target_achievement || 0), unit: "%", icon: "trend", tone: "accent", sub: `${performanceMetrics.source || "server"} scoped` }),
        e(Stat, { label: "Avg. Conversion", value: String(summary.avg_conversion || 0), unit: "%", icon: "funnel", tone: "blue", sub: "lead to booking" }),
        e(Stat, { label: "Eligible Performers", value: String(summary.eligible_count || 0), icon: "wallet", tone: "violet", sub: `${summary.row_count || 0} performer row(s)` })),
      !hasPerformanceApi && e("div", { style: { marginBottom: 16, background: "rgba(245,158,11,.10)", color: "var(--orange)", border: "1px solid rgba(245,158,11,.25)", borderRadius: 10, padding: 12, fontSize: 13, fontWeight: 700 } },
        "Sales performance API required; no local performer, revenue, target, incentive, or conversion rows are fabricated."),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.5fr 1fr", alignItems: "start", marginBottom: 16 } },
        e(Card, { title: "Revenue Leaderboard", sub: `${performanceMetrics.source || "server"} scoped booked value`, pad: true }, e(HBars, { data: revenueLeaderboard })),
        e(Card, { title: "Target vs Achievement", sub: "% of scoped expected lead value", pad: true }, e(BarChart, { height: 168, data: targetChart }))),
      e("div", { className: "tabs" }, tabs.map(item => e("div", { key: item, className: "tab " + (tab === item ? "on" : ""), onClick: () => setTab(item) }, item))),
      content,
      e("div", { className: "card", style: { marginTop: 16, padding: "16px 18px", background: "var(--violet-soft)", border: "none", display: "flex", gap: 12, alignItems: "center" } },
        e("div", { style: { width: 38, height: 38, borderRadius: 11, background: "var(--surface)", color: "var(--violet)", display: "grid", placeItems: "center", flex: "0 0 38px" } }, e(Icon, { name: "spark", size: 19 })),
        e("div", { style: { flex: 1 } },
          e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, "Performance calculation"),
          e("div", { className: "cell-sub", style: { marginTop: 2 } }, "Sales performance is calculated from scoped leads, qualifications, site visits, bookings and approved receipts.")),
        e(Button, { variant: "primary", icon: "check", onClick: refreshMetrics, children: "Refresh Metrics" })),
    );
  }

  window.Performance = Performance;
})();
