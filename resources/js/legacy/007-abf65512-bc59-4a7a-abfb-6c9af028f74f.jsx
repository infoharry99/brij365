const React = window.React;

/* Builder360 — Reports & Analytics : Sales / Finance / Construction tabs, Library + Drawer, main */
(function () {
  const e = React.createElement;
  const { Icon, Avatar, Badge, Button, Card, ProgCell, Seg, PageHead } = window;
  const { RComboChart, RHeatmap, RIDonut, RRankBars, RStackBar, RMiniArea, RRadialGauge } = window;
  const R = window.__REPORTS__;
  const { series, crf, PERIOD_LABEL, Dropdown, KpiHero, SectionHead, ExecutiveTab } = R;

  function csvCell(value) {
    const raw = value == null ? "" : String(value);
    const safe = /^[=+\-@]/.test(raw) ? "'" + raw : raw;
    return '"' + safe.replace(/"/g, '""') + '"';
  }

  function downloadCsv(filename, rows) {
    if (!Array.isArray(rows) || !rows.length) return false;
    const headers = Array.from(rows.reduce((set, row) => {
      Object.keys(row || {}).forEach(key => set.add(key));
      return set;
    }, new Set()));
    const csv = [headers.map(csvCell).join(","), ...rows.map(row => headers.map(key => csvCell(row && row[key])).join(","))].join("\r\n");
    try {
      const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      a.style.display = "none";
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.setTimeout(() => URL.revokeObjectURL(url), 500);
      return true;
    } catch (err) {
      try {
        const a = document.createElement("a");
        a.href = "data:text/csv;charset=utf-8," + encodeURIComponent(csv);
        a.download = filename;
        a.style.display = "none";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        return true;
      } catch (fallbackErr) {
        console.error("[Builder360] Report CSV export failed", fallbackErr);
        return false;
      }
    }
  }

  function exportCsv(toast, filename, rows, label) {
    if (!Array.isArray(rows) || !rows.length) {
      toast("No " + label + " rows available to export", "orange");
      return;
    }
    const ok = downloadCsv(filename, rows);
    toast(ok ? "Exported " + rows.length + " " + label + " row(s)" : "Unable to export " + label + " rows", ok ? "green" : "red");
  }

  function unavailable(toast, label) {
    toast(label + " requires a configured backend workflow and is not enabled in this legacy screen.", "orange");
  }

  function backendReportKey(report) {
    const map = {
      "Booking Report": "bookings",
      "Collection Report": "collections",
      "Outstanding Ageing": "collections",
      "Payroll Register": "payroll",
      "Service Ticket SLA": "service_tickets",
      "Lead Funnel": "leads",
      "Sales Performance": "leads",
      "Marketing ROI": "leads",
      "Material Stock": "stock_items",
      "Consumption": "stock_movements",
      "Vendor Report": "vendors",
      "Project Progress": "construction_milestones",
      "Daily Work Report": "daily_progress_reports",
      "Legal / RERA": "rera_registrations",
      "Planned vs Actual": "construction_milestones",
      "Project Health": "construction_milestones",
      "Delay Report": "construction_milestones",
      "Audit Log": "audit_events",
    };
    return map[report?.n] || null;
  }

  function reportOptions() {
    return window.Builder360Server?.governance_report_options || null;
  }

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  }

  async function apiJson(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-TOKEN": csrfToken(),
        ...(options.headers || {}),
      },
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
      const errors = body.errors || {};
      const first = Object.values(errors).flat()[0];
      throw new Error(first || body.message || "Request failed.");
    }
    return body;
  }

  function templateUrl(template, token, value) {
    return template ? template.replace(token, encodeURIComponent(String(value))) : "";
  }

  function reportLabel(key) {
    return ({
      bookings: "Bookings",
      collections: "Collections",
      payroll: "Payroll",
      service_tickets: "Service Tickets",
      leads: "Leads",
      inventory_units: "Inventory Units",
      stock_items: "Material Stock",
      stock_movements: "Stock Movements",
      purchase_orders: "Purchase Orders",
      vendors: "Vendors",
      construction_milestones: "Construction Milestones",
      daily_progress_reports: "Daily Progress Reports",
      rera_registrations: "Legal / RERA",
      audit_events: "Audit Events",
    })[key] || String(key || "Report").replace(/_/g, " ");
  }

  function backendReportUrl(reportKey, format) {
    const options = reportOptions();
    if (!options?.register_url || !reportKey) return null;
    if (Array.isArray(options.supported_reports) && !options.supported_reports.includes(reportKey)) return null;
    if (Array.isArray(options.supported_formats) && !options.supported_formats.includes(format)) return null;
    const url = new URL(options.register_url, window.location.origin);
    url.searchParams.set("report", reportKey);
    url.searchParams.set("format", format);
    return url.toString();
  }

  function openManagementSummaryCsv(toast) {
    const options = reportOptions();
    if (!options?.management_summary_url) {
      unavailable(toast, "Dashboard CSV export");
      return false;
    }
    const url = new URL(options.management_summary_url, window.location.origin);
    url.searchParams.set("format", "csv");
    const a = document.createElement("a");
    a.href = url.toString();
    a.target = "_blank";
    a.rel = "noopener";
    a.style.display = "none";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    toast("Downloading governed management summary CSV.", "green");
    return true;
  }

  function downloadBackendReport(toast, report, format) {
    const reportKey = backendReportKey(report);
    const url = backendReportUrl(reportKey, format);
    if (!url) {
      unavailable(toast, format.toUpperCase() + " export for " + report.n);
      return;
    }
    const a = document.createElement("a");
    a.href = url;
    a.target = "_blank";
    a.rel = "noopener";
    a.style.display = "none";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    toast("Downloading " + report.n + " " + format.toUpperCase() + " from Laravel reports.", "green");
  }

  async function fetchGovernedReport(reportKey, filters = {}) {
    const url = backendReportUrl(reportKey, "json");
    if (!url) throw new Error("Governed report register is not available for " + reportLabel(reportKey) + ".");
    const nextUrl = new URL(url);
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") nextUrl.searchParams.set(key, value);
    });
    const response = await fetch(nextUrl.toString(), { headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" } });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
      const errors = body.errors || {};
      const first = Object.values(errors).flat()[0];
      throw new Error(first || body.message || "Report register request failed.");
    }
    return body.data || {};
  }

  function openGovernedReport(toast, reportKey, format, filters = {}) {
    const url = backendReportUrl(reportKey, format);
    if (!url) {
      unavailable(toast, format.toUpperCase() + " export for " + reportLabel(reportKey));
      return false;
    }
    const nextUrl = new URL(url);
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") nextUrl.searchParams.set(key, value);
    });
    const a = document.createElement("a");
    a.href = nextUrl.toString();
    a.target = "_blank";
    a.rel = "noopener";
    a.style.display = "none";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    toast("Downloading " + reportLabel(reportKey) + " " + format.toUpperCase() + " from governed Laravel report register.", "green");
    return true;
  }

  function CustomReportModal({ onClose, toast }) {
    const options = reportOptions();
    const reports = Array.isArray(options?.supported_reports) ? options.supported_reports : [];
    const formats = (Array.isArray(options?.supported_formats) ? options.supported_formats : []).filter(format => format !== "json");
    const [form, setForm] = React.useState({ report: reports[0] || "", format: formats[0] || "csv", status: "", date_from: "", date_to: "" });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const statusOptions = Array.isArray(options?.supported_report_statuses?.[form.report]) ? options.supported_report_statuses[form.report] : [];
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const setReport = value => setForm(current => ({ ...current, report: value, status: "" }));

    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!form.report || !form.format) return setError("Select report and export format.");
      if (form.date_from && form.date_to && form.date_to < form.date_from) return setError("Date To cannot be before Date From.");
      try {
        setBusy(true);
        if (openGovernedReport(toast, form.report, form.format, { status: form.status, date_from: form.date_from, date_to: form.date_to })) onClose();
      } catch (err) {
        setError(err.message || "Custom report export failed.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", style: { width: 620, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "card-head" },
          e("div", null, e("div", { className: "card-title" }, "Custom Governed Report"), e("div", { className: "cell-sub" }, "Uses Laravel report register with role scope, validation, export limits and audit trail.")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose, disabled: busy }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          !options?.register_url && e("div", { style: { background: "var(--orange-soft)", color: "var(--orange)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 12, fontWeight: 800 } }, "Governed report register is not available for this role."),
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: label }, "Report", e("select", { style: field, value: form.report, disabled: busy || !reports.length, onChange: ev => setReport(ev.target.value) }, reports.map(key => e("option", { key, value: key }, reportLabel(key))))),
            e("label", { style: label }, "Format", e("select", { style: field, value: form.format, disabled: busy || !formats.length, onChange: ev => set("format", ev.target.value) }, formats.map(format => e("option", { key: format, value: format }, format.toUpperCase())))),
            e("label", { style: label }, "Status filter", e("select", { style: field, value: form.status, disabled: busy || !statusOptions.length, onChange: ev => set("status", ev.target.value) },
              e("option", { value: "" }, statusOptions.length ? "All statuses" : "No status filter"),
              statusOptions.map(status => e("option", { key: status.value, value: status.value }, status.label || status.value)))),
            e("label", { style: label }, "Date From", e("input", { type: "date", style: field, value: form.date_from, disabled: busy, onChange: ev => set("date_from", ev.target.value) })),
            e("label", { style: label }, "Date To", e("input", { type: "date", style: field, value: form.date_to, disabled: busy, onChange: ev => set("date_to", ev.target.value) }))),
          error && e("div", { style: { color: "var(--red)", fontSize: 12, fontWeight: 800, marginBottom: 12 } }, error),
          e("div", { className: "row gap-2", style: { justifyContent: "flex-end" } },
            e("button", { type: "button", className: "btn", onClick: onClose, disabled: busy }, "Cancel"),
            e("button", { type: "submit", className: "btn btn-primary", disabled: busy || !options?.register_url || !reports.length || !formats.length }, busy ? "Preparing..." : "Generate Export")))));
  }

  function ReportScheduleModal({ report, onClose, onSaved, toast }) {
    const options = reportOptions();
    const reportKey = backendReportKey(report);
    const formats = (Array.isArray(options?.supported_formats) ? options.supported_formats : []).filter(format => ["csv", "excel", "pdf"].includes(format));
    const frequencies = Array.isArray(options?.schedule_frequencies) ? options.schedule_frequencies : ["daily", "weekly", "monthly"];
    const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      label: report?.n || reportLabel(reportKey),
      frequency: frequencies[0] || "weekly",
      format: formats[0] || "csv",
      starts_on: tomorrow,
      ends_on: "",
      recipients: "",
    });
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const labelStyle = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));

    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!options?.report_schedule_store_url || !reportKey) return setError("Report schedule endpoint is not available for this report.");
      const recipients = form.recipients.split(",").map(email => email.trim()).filter(Boolean);
      if (!recipients.length) return setError("At least one recipient email is required.");
      try {
        setBusy(true);
        const body = await apiJson(options.report_schedule_store_url, {
          method: "POST",
          body: JSON.stringify({
            report_key: reportKey,
            label: form.label,
            frequency: form.frequency,
            format: form.format,
            starts_on: form.starts_on,
            ends_on: form.ends_on || null,
            recipients,
            filters: {},
          }),
        });
        onSaved(body.data);
        toast("Report schedule created in Laravel.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Report schedule could not be created.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", style: { width: 620, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "card-head" },
          e("div", null, e("div", { className: "card-title" }, "Schedule Report"), e("div", { className: "cell-sub" }, "Creates a governed schedule metadata record. Operational dispatch is handled by deployment scheduler/queue setup.")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose, disabled: busy }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          error && e("div", { style: { color: "var(--red)", fontSize: 12, fontWeight: 800, marginBottom: 12 } }, error),
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Schedule Label", e("input", { style: field, required: true, maxLength: 160, value: form.label, disabled: busy, onChange: ev => set("label", ev.target.value) })),
            e("label", { style: labelStyle }, "Frequency", e("select", { style: field, value: form.frequency, disabled: busy, onChange: ev => set("frequency", ev.target.value) }, frequencies.map(freq => e("option", { key: freq, value: freq }, reportLabel(freq))))),
            e("label", { style: labelStyle }, "Format", e("select", { style: field, value: form.format, disabled: busy, onChange: ev => set("format", ev.target.value) }, formats.map(format => e("option", { key: format, value: format }, format.toUpperCase())))),
            e("label", { style: labelStyle }, "Starts On", e("input", { style: field, type: "date", required: true, value: form.starts_on, disabled: busy, onChange: ev => set("starts_on", ev.target.value) })),
            e("label", { style: labelStyle }, "Ends On", e("input", { style: field, type: "date", value: form.ends_on, disabled: busy, onChange: ev => set("ends_on", ev.target.value) })),
            e("label", { style: labelStyle, gridColumn: "1 / -1" }, "Recipients", e("input", { style: field, required: true, value: form.recipients, disabled: busy, onChange: ev => set("recipients", ev.target.value), placeholder: "name@company.com, finance@company.com" }))),
          e("div", { className: "sys-note", style: { marginBottom: 12 } }, "Recipients, dates, report keys and formats are validated by Laravel before persistence."),
          e("div", { className: "row gap-2", style: { justifyContent: "flex-end" } },
            e("button", { type: "button", className: "btn", onClick: onClose, disabled: busy }, "Cancel"),
            e("button", { type: "submit", className: "btn btn-primary", disabled: busy || !options?.report_schedule_store_url || !reportKey }, busy ? "Saving..." : "Create Schedule")))));
  }

  // ============================================================
  //  Interactive funnel
  // ============================================================
  // ============================================================
  //  REPORT LIBRARY + DRAWER
  // ============================================================
  const LIB = [
    { g: "Project & Construction", c: "var(--accent)", items: [
      { n: "Project Progress", d: "Planned vs actual completion across all towers", chart: "progress" },
      { n: "Daily Work Report", d: "Site activity, labour and work-items closed", chart: "bars" },
      { n: "Planned vs Actual", d: "Schedule variance and delay analysis", chart: "variance" },
      { n: "Project Health", d: "Composite health score by project", chart: "health" },
      { n: "Delay Report", d: "Critical-path slippage and root cause", chart: "variance" }] },
    { g: "Finance & Cost", c: "var(--green)", items: [
      { n: "Budget vs Actual", d: "Cost head utilisation and overrun risk", chart: "cost" },
      { n: "Project ROI", d: "Return on investment by project", chart: "roi" },
      { n: "Cost per Flat", d: "Unit economics and contribution margin", chart: "bars" },
      { n: "Cash Flow Forecast", d: "Inflow / outflow projection — 12 months", chart: "cash" },
      { n: "Outstanding Ageing", d: "Receivables bucketed by age", chart: "aging" }] },
    { g: "Sales & Marketing", c: "var(--violet)", items: [
      { n: "Booking Report", d: "Bookings, value and stage by project", chart: "bars" },
      { n: "Collection Report", d: "Collected vs due across milestones", chart: "cash" },
      { n: "Lead Funnel", d: "Stage-wise conversion and drop-off", chart: "funnel" },
      { n: "Sales Performance", d: "Rep leaderboard and attainment", chart: "leaderboard" },
      { n: "Marketing ROI", d: "Spend vs bookings by channel", chart: "roi" }] },
    { g: "Inventory & Procurement", c: "var(--orange)", items: [
      { n: "Material Stock", d: "Stock on hand vs reorder level", chart: "bars" },
      { n: "Consumption", d: "Material consumed by project / head", chart: "cost" },
      { n: "Vendor Report", d: "Vendor spend, rating and on-time %", chart: "leaderboard" }] },
    { g: "HR & Compliance", c: "var(--blue)", items: [
      { n: "Manpower by Project", d: "Headcount and deployment by site", chart: "bars" },
      { n: "Payroll Register", d: "Payroll run status, earnings, deductions and net payable", chart: "cash" },
      { n: "Legal / RERA", d: "Compliance status and filing calendar", chart: "health" },
      { n: "Audit Log", d: "User activity and change history", chart: "bars" }] },
    { g: "After-Sales", c: "var(--red)", items: [
      { n: "Service Ticket SLA", d: "Complaint status, priority, assignment and SLA due tracking", chart: "aging" }] },
  ];

  function ReportDrawer({ report, onClose, toast, pinnedReports, onPinSaved, onPinRemoved, onScheduleSaved }) {
    const serverReportKey = backendReportKey(report);
    const hasGovernedReport = !!backendReportUrl(serverReportKey, "csv");
    const options = reportOptions();
    const [scheduling, setScheduling] = React.useState(false);
    const [pinBusy, setPinBusy] = React.useState(false);
    const pin = (pinnedReports || []).find(row => row.report_key === serverReportKey);
    const exportAction = format => hasGovernedReport
      ? downloadBackendReport(toast, report, format)
      : unavailable(toast, format.toUpperCase() + " export for " + report.n);
    const togglePin = async () => {
      if (!serverReportKey || !options?.report_pin_store_url) return unavailable(toast, "Pinned report persistence");
      try {
        setPinBusy(true);
        if (pin?.id) {
          await apiJson(templateUrl(options.report_pin_delete_url_template, "__PIN__", pin.id), { method: "DELETE", body: JSON.stringify({}) });
          onPinRemoved(pin.id);
          toast("Report pin removed in Laravel.", "green");
        } else {
          const body = await apiJson(options.report_pin_store_url, {
            method: "POST",
            body: JSON.stringify({ report_key: serverReportKey, label: report.n, filters: {} }),
          });
          onPinSaved(body.data);
          toast("Report pinned in Laravel.", "green");
        }
      } catch (err) {
        toast(err.message || "Report pin action failed.", "red");
      } finally {
        setPinBusy(false);
      }
    };
    const previewText = hasGovernedReport
      ? "Preview rows are generated by the Laravel governed report register during export. No local DB/project preview is fabricated in this drawer."
      : "This report is listed for discovery only. A governed Laravel report endpoint is required before preview, detail rows or exports can be enabled.";
    const detailText = hasGovernedReport
      ? "Use CSV, Excel or PDF export to retrieve Laravel-generated scoped rows with server-side permissions, filters and audit trail."
      : "No local fallback rows are shown for unsupported report definitions.";
    const header = e("div", { style: { padding: "18px 22px", borderBottom: "1px solid var(--border)", display: "flex", alignItems: "flex-start", gap: 12 } },
      e("div", { style: { width: 40, height: 40, borderRadius: 11, background: "var(--accent-soft)", color: "var(--accent)", display: "grid", placeItems: "center", flex: "0 0 40px" } }, e(Icon, { name: "doc", size: 19 })),
      e("div", { style: { flex: 1, minWidth: 0 } },
        e("div", { className: "crumbs", style: { marginBottom: 3 } }, report.group),
        e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 18, letterSpacing: "-.02em" } }, report.n),
        e("div", { className: "page-sub", style: { marginTop: 2, fontSize: 12.5 } }, report.d)),
      e("button", { className: "icon-btn", onClick: onClose }, e(Icon, { name: "x", size: 17 })));
    const body = e("div", { style: { flex: 1, overflowY: "auto", padding: 22 } },
      e("div", { className: "row gap-2", style: { marginBottom: 16, flexWrap: "wrap" } },
        e("span", { className: "tag" }, e(Icon, { name: "building", size: 13 }), "Authorized scope"),
        e("span", { className: "tag" }, e(Icon, { name: "calendar", size: 13 }), "Filter in export request"),
        e("span", { className: "badge " + (hasGovernedReport ? "b-green" : "b-orange") }, hasGovernedReport ? "Laravel report endpoint" : "Endpoint required")),
      e("div", { className: "card", style: { marginBottom: 18 } },
        e("div", { className: "card-head" }, e("div", { className: "card-title", style: { fontSize: 13 } }, "Preview")),
        e("div", { className: "card-pad" }, e("div", { className: "empty" }, previewText))),
      e("div", { className: "card" },
        e("div", { className: "card-head" },
          e("div", { className: "card-title", style: { fontSize: 13 } }, "Detail Rows"),
          e("span", { className: "badge b-slate" }, hasGovernedReport ? "export scoped" : "0 rows")),
        e("div", { className: "card-pad" }, e("div", { className: "empty" }, detailText))));
    const footer = e("div", { style: { padding: "14px 22px", borderTop: "1px solid var(--border)", display: "flex", gap: 9 } },
      e(Button, { variant: "primary", icon: "download", disabled: !hasGovernedReport, onClick: () => exportAction("csv"), children: "Export CSV" }),
      e(Button, { icon: "download", disabled: !hasGovernedReport, onClick: () => exportAction("excel"), children: "Export Excel" }),
      e(Button, { icon: "doc", disabled: !hasGovernedReport, onClick: () => exportAction("pdf"), children: "Export PDF" }),
      e(Button, { variant: "ghost", icon: "clock", disabled: !hasGovernedReport || !options?.report_schedule_store_url, title: options?.report_schedule_store_url ? "Create governed report schedule metadata" : "Report scheduling endpoint unavailable for this role.", onClick: () => setScheduling(true), children: "Schedule" }),
      e("div", { style: { flex: 1 } }),
      e(Button, { variant: "ghost", icon: "star", disabled: pinBusy || !hasGovernedReport || !options?.report_pin_store_url, title: pin ? "Remove pinned report" : "Pin this report", onClick: togglePin, children: pin ? "Pinned" : "Pin" }));
    return e("div", { className: "scrim", style: { justifyContent: "flex-end" }, onClick: onClose },
      e("div", { className: "drawer", style: { width: 560 }, onClick: ev => ev.stopPropagation() }, header, body, footer),
      scheduling && e(ReportScheduleModal, { report, onClose: () => setScheduling(false), onSaved: onScheduleSaved, toast }));
  }
  function LibraryTab({ toast }) {
    const [q, setQ] = React.useState("");
    const [open, setOpen] = React.useState(null);
    const [customOpen, setCustomOpen] = React.useState(false);
    const options = reportOptions();
    const [pinnedReports, setPinnedReports] = React.useState(Array.isArray(options?.pinned_reports) ? options.pinned_reports : []);
    const [scheduledReports, setScheduledReports] = React.useState(Array.isArray(options?.scheduled_reports) ? options.scheduled_reports : []);
    const groups = LIB.map(cat => ({ ...cat, items: cat.items.filter(it => (it.n + it.d).toLowerCase().includes(q.toLowerCase())) })).filter(c => c.items.length);
    const total = LIB.reduce((s, c) => s + c.items.length, 0);
    return e("div", { className: "grid", style: { gap: 16 } },
      e("div", { className: "card", style: { padding: 14, display: "flex", gap: 12, alignItems: "center", flexWrap: "wrap" } },
        e("div", { style: { flex: 1, minWidth: 220, height: 40, borderRadius: 10, background: "var(--surface-3)", border: "1px solid var(--border)", display: "flex", alignItems: "center", gap: 9, padding: "0 13px" } },
          e(Icon, { name: "search", size: 16, style: { color: "var(--text-3)" } }),
          e("input", { value: q, onChange: ev => setQ(ev.target.value), placeholder: "Search " + total + " reports…", style: { border: "none", outline: "none", background: "none", flex: 1, fontSize: 13.5, color: "var(--text)", fontFamily: "inherit" } })),
        e("div", { className: "row gap-2" },
          e("span", { className: "tag", title: "Report schedules are persisted as governed Laravel metadata." }, e(Icon, { name: "clock", size: 13 }), scheduledReports.length + " scheduled"),
          e("span", { className: "tag", title: "Pinned reports are persisted per Laravel user." }, e(Icon, { name: "star", size: 13 }), pinnedReports.length + " pinned"),
          e(Button, { variant: "primary", icon: "plus", onClick: () => setCustomOpen(true), children: "Custom Report" }))),
      (pinnedReports.length || scheduledReports.length) ? e("div", { className: "grid g-2" },
        e("div", { className: "card card-pad" }, e("div", { className: "cell-strong" }, "Pinned Reports"), pinnedReports.length ? pinnedReports.map(pin => e("span", { key: pin.id, className: "tag", style: { margin: "8px 8px 0 0" } }, pin.label || reportLabel(pin.report_key))) : e("div", { className: "cell-sub", style: { marginTop: 8 } }, "No pinned reports yet.")),
        e("div", { className: "card card-pad" }, e("div", { className: "cell-strong" }, "Scheduled Reports"), scheduledReports.length ? scheduledReports.map(schedule => e("span", { key: schedule.id, className: "tag", style: { margin: "8px 8px 0 0" } }, (schedule.label || reportLabel(schedule.report_key)) + " · " + reportLabel(schedule.frequency))) : e("div", { className: "cell-sub", style: { marginTop: 8 } }, "No active schedules yet."))) : null,
      e("div", { className: "grid g-3" }, groups.map((cat, i) =>
        e("div", { key: i, className: "card" },
          e("div", { className: "card-head" },
            e("div", { className: "row gap-2" }, e("span", { style: { width: 10, height: 10, borderRadius: 3, background: cat.c } }), e("div", { className: "card-title" }, cat.g)),
            e("span", { className: "badge b-slate" }, cat.items.length)),
          e("div", { style: { padding: "6px 8px 10px" } }, cat.items.map((it, j) =>
            e("div", { key: j, className: "nav-item", style: { height: "auto", padding: "9px 10px", alignItems: "flex-start" }, onClick: () => setOpen({ ...it, group: cat.g }) },
              e("div", { style: { width: 30, height: 30, borderRadius: 8, background: "var(--surface-3)", display: "grid", placeItems: "center", color: cat.c, flex: "0 0 30px" } }, e(Icon, { name: "doc", size: 15 })),
              e("div", { style: { flex: 1, minWidth: 0 } },
                e("div", { style: { fontWeight: 700, fontSize: 13 } }, it.n),
                e("div", { className: "cell-sub", style: { whiteSpace: "normal", lineHeight: 1.3, marginTop: 1 } }, it.d)),
              e(Icon, { name: "chevR", size: 15, style: { opacity: .5, marginTop: 4 } }))))))),
      open && e(ReportDrawer, { report: open, onClose: () => setOpen(null), toast, pinnedReports, onPinSaved: pin => setPinnedReports(rows => [pin, ...rows.filter(row => row.id !== pin.id && row.report_key !== pin.report_key)]), onPinRemoved: pinId => setPinnedReports(rows => rows.filter(row => row.id !== pinId)), onScheduleSaved: schedule => setScheduledReports(rows => [schedule, ...rows.filter(row => row.id !== schedule.id)]) }),
      customOpen && e(CustomReportModal, { onClose: () => setCustomOpen(false), toast }),
    );
  }

  function GovernedReportRequiredTab({ title, reports }) {
    return e("div", { className: "grid", style: { gap: 16 } },
      e("div", { className: "card card-pad" },
        e("div", { className: "row gap-3", style: { alignItems: "flex-start" } },
          e("div", { style: { width: 38, height: 38, borderRadius: 10, background: "var(--surface-3)", color: "var(--orange)", display: "grid", placeItems: "center", flex: "0 0 38px" } }, e(Icon, { name: "alert", size: 18 })),
          e("div", null,
            e("div", { className: "cell-strong", style: { fontSize: 15 } }, title + " analytics require governed Laravel report payloads"),
            e("div", { className: "cell-sub", style: { marginTop: 4, lineHeight: 1.45 } }, "Static chart series and local DB rows are intentionally hidden. Use Report Library or Custom Report to export scoped Laravel rows with server-side permissions, filters and audit trail.")))),
      e("div", { className: "grid g-3" }, reports.map(item =>
        e("div", { key: item.key, className: "card card-pad" },
          e("div", { className: "row between" },
            e("div", null, e("div", { className: "cell-strong" }, item.label), e("div", { className: "cell-sub", style: { marginTop: 3 } }, item.text)),
            e("span", { className: "badge " + (backendReportUrl(item.key, "csv") ? "b-green" : "b-orange") }, backendReportUrl(item.key, "csv") ? "Export ready" : "Endpoint required")),
          e("div", { className: "row gap-2", style: { marginTop: 12 } },
            e(Button, { sm: true, icon: "download", disabled: !backendReportUrl(item.key, "csv"), onClick: () => openGovernedReport(() => {}, item.key, "csv"), children: "CSV" }),
            e(Button, { sm: true, icon: "download", disabled: !backendReportUrl(item.key, "excel"), onClick: () => openGovernedReport(() => {}, item.key, "excel"), children: "Excel" }),
            e(Button, { sm: true, icon: "doc", disabled: !backendReportUrl(item.key, "pdf"), onClick: () => openGovernedReport(() => {}, item.key, "pdf"), children: "PDF" }))))));
  }

  // ============================================================
  //  MAIN — Reports & Analytics
  // ============================================================
  const TABS = [
    { id: "exec", label: "Executive", icon: "grid" },
    { id: "sales", label: "Sales & CRM", icon: "tag" },
    { id: "finance", label: "Finance", icon: "wallet" },
    { id: "construction", label: "Construction", icon: "hardhat" },
    { id: "library", label: "Report Library", icon: "folder" },
  ];

  function Reports({ toast }) {
    const [tab, setTab] = React.useState("exec");
    const [period, setPeriod] = React.useState("Y");
    const [projId, setProjId] = React.useState("all");
    const [dept, setDept] = React.useState("all");
    const [kpiSel, setKpiSel] = React.useState("rev");
    const [insight, setInsight] = React.useState(null);
    const [insightBusy, setInsightBusy] = React.useState(false);

    const scopedProjects = Array.isArray(window.Builder360Server?.dashboard?.projects) ? window.Builder360Server.dashboard.projects : [];
    const projOpts = [{ v: "all", label: "All Authorized Projects" }, ...scopedProjects.map(p => ({ v: p.id || p.db_id || p.code, label: p.name || p.code || "Project", dot: p.color }))];
    const deptOpts = [{ v: "all", label: "All Departments" }, { v: "sales", label: "Sales & CRM" }, { v: "fin", label: "Finance" }, { v: "const", label: "Construction" }, { v: "proc", label: "Procurement" }];
    const periodOpts = [{ v: "Q", label: "Last Quarter" }, { v: "H", label: "Last 6 Months" }, { v: "Y", label: "FY 2025–26" }];

    const kpis = [
      { id: "bookings", label: "Bookings", key: "bookings", sub: "Laravel booking report" },
      { id: "collections", label: "Collections", key: "collections", sub: "Laravel collection report" },
      { id: "payroll", label: "Payroll", key: "payroll", sub: "Laravel payroll register" },
      { id: "service_tickets", label: "Service Tickets", key: "service_tickets", sub: "Laravel SLA report" },
    ];

    const generateInsights = async () => {
      setInsight(null);
      const options = reportOptions();
      if (!options?.register_url) {
        toast("AI insights require the governed Laravel report register for this role.", "orange");
        return;
      }
      try {
        setInsightBusy(true);
        const [bookingReport, collectionReport] = await Promise.all([
          fetchGovernedReport("bookings"),
          fetchGovernedReport("collections"),
        ]);
        const bookingRows = bookingReport.rows || [];
        const collectionRows = collectionReport.rows || [];
        const bookingValue = bookingRows.reduce((sum, row) => sum + Number(row.net_receivable || 0), 0);
        const collectionValue = collectionRows.reduce((sum, row) => sum + Number(row.amount || 0), 0);
        const coverage = bookingValue > 0 ? Math.round((collectionValue / bookingValue) * 1000) / 10 : 0;
        const message = "Governed insight from Laravel reports: " + bookingRows.length + " booking row(s), " + collectionRows.length + " collection row(s), collection coverage " + coverage + "%.";
        setInsight({ message, booking_count: bookingRows.length, collection_count: collectionRows.length, coverage });
        toast("AI-style report insight generated from scoped Laravel report data.", "green");
      } catch (err) {
        toast(err.message || "AI insight generation failed.", "red");
      } finally {
        setInsightBusy(false);
      }
    };

    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Overview", "Reports & Analytics"], title: "Reports & Analytics",
        sub: "Governed report register for scoped exports and analytics. Static dashboard series are hidden unless backed by Laravel report payloads.",
        actions: [
          e(Button, { key: 1, icon: "spark", variant: "ghost", disabled: insightBusy, onClick: generateInsights, children: insightBusy ? "Generating..." : "AI Insights" }),
          e(Button, { key: 2, icon: "download", disabled: !reportOptions()?.management_summary_url, title: reportOptions()?.management_summary_url ? "Export governed management summary CSV" : "Dashboard-level CSV requires a governed management-summary export endpoint.", onClick: () => openManagementSummaryCsv(toast), children: "Dashboard CSV" }),
        ] }),

      insight && e("div", { className: "sys-note", style: { marginBottom: 14 } },
        e(Icon, { name: "spark", size: 14, style: { verticalAlign: "-2px", marginRight: 6 } }),
        insight.message),

      // filter bar
      e("div", { className: "filterbar", style: { marginBottom: 18 } },
        e(Dropdown, { label: "Project", value: (projOpts.find(o => o.v === projId) || projOpts[0]).label, options: projOpts, onChange: setProjId, icon: "building", width: 210 }),
        e(Dropdown, { label: "Period", value: (periodOpts.find(o => o.v === period) || periodOpts[2]).label, options: periodOpts, onChange: setPeriod, icon: "calendar" }),
        e(Dropdown, { label: "Dept", value: (deptOpts.find(o => o.v === dept) || deptOpts[0]).label, options: deptOpts, onChange: setDept, icon: "filter" }),
        e("div", { style: { flex: 1 } }),
        e("button", { className: "chip-select", onClick: () => { setProjId("all"); setPeriod("Y"); setDept("all"); toast("Filters reset", "accent"); } }, e(Icon, { name: "x", size: 14 }), "Reset")),

      e("div", { className: "grid g-4", style: { marginBottom: 20 } },
        kpis.map(k => e("div", { key: k.id, className: "card card-pad", onClick: () => setKpiSel(k.id), style: { cursor: "pointer", borderColor: kpiSel === k.id ? "var(--accent)" : undefined } },
          e("div", { className: "kpi-mini" }, k.label),
          e("div", { className: "mono", style: { fontWeight: 800, fontSize: 22, marginTop: 5 } }, backendReportUrl(k.key, "csv") ? "Ready" : "API"),
          e("div", { className: "cell-sub", style: { marginTop: 4 } }, backendReportUrl(k.key, "csv") ? k.sub + " export available" : k.sub + " endpoint required")))),

      // tab nav
      e("div", { className: "tabs", style: { marginBottom: 20 } }, TABS.map(t =>
        e("div", { key: t.id, className: "tab " + (tab === t.id ? "on" : ""), onClick: () => setTab(t.id), style: { display: "flex", alignItems: "center", gap: 7 } },
          e(Icon, { name: t.icon, size: 15 }), t.label))),

      // tab content
      tab === "exec" && e(GovernedReportRequiredTab, { title: "Executive", reports: [{ key: "bookings", label: "Booking Report", text: "Booking value, status and project scope" }, { key: "collections", label: "Collection Report", text: "Receipts, ageing and payment status" }] }),
      tab === "sales" && e(GovernedReportRequiredTab, { title: "Sales & CRM", reports: [{ key: "bookings", label: "Booking Report", text: "Bookings, value and stage by project" }, { key: "collections", label: "Collection Report", text: "Collections linked to sales workflow" }] }),
      tab === "finance" && e(GovernedReportRequiredTab, { title: "Finance", reports: [{ key: "collections", label: "Collection Report", text: "Approved collection rows and ageing" }, { key: "payroll", label: "Payroll Register", text: "Payroll run status and net payable" }] }),
      tab === "construction" && e(GovernedReportRequiredTab, { title: "Construction", reports: [{ key: "service_tickets", label: "Service Ticket SLA", text: "SLA due tracking and assignment status" }] }),
      tab === "library" && e(LibraryTab, { toast }),
    );
  }

  window.Reports = Reports;
})();
