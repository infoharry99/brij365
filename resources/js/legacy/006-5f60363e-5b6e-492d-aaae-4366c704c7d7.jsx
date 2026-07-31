const React = window.React;

/* Builder360 — Reports & Analytics helper utilities.
   This file intentionally does not seed chart series, project financials,
   insights, heatmaps, or local export rows. Governed reports come from
   the Laravel report register used by resources/js/legacy/007-*.jsx. */
(function () {
  const e = React.createElement;
  const { Icon, Button, Card, Seg } = window;

  const MONTHS = [];
  const G = {};
  const PERIODS = { Q: 3, H: 6, Y: 12 };
  const PERIOD_LABEL = { Q: "Last Quarter", H: "Last 6 Months", Y: "Financial Year" };

  function series() {
    return { cur: [], prev: [], labels: [], full: [] };
  }

  const sum = rows => (Array.isArray(rows) ? rows : []).reduce((total, value) => total + Number(value || 0), 0);
  const delta = (cur, prev) => {
    const previous = sum(prev);
    if (!previous) return 0;
    return Math.round(((sum(cur) - previous) / previous) * 1000) / 10;
  };
  const crf = value => "₹" + Number(value || 0).toLocaleString("en-IN") + " Cr";

  function downloadCsv(filename, rows, toast, label, exportUrl) {
    if (exportUrl) {
      const url = new URL(exportUrl, window.location.origin);
      url.searchParams.set("format", "csv");
      toast && toast("Downloading governed " + label + " export from Laravel.", "green");
      window.location.assign(url.toString());
      return true;
    }

    if (!Array.isArray(rows) || !rows.length) {
      toast && toast("No governed " + label + " rows are available for export.", "orange");
      return false;
    }
    toast && toast("Use the Laravel governed report register for " + label + " exports.", "orange");
    return false;
  }

  function Dropdown({ label, value, options, onChange, icon, width = 180 }) {
    const safeOptions = Array.isArray(options) ? options : [];
    const [open, setOpen] = React.useState(false);
    return e("div", { style: { position: "relative" } },
      e("button", { className: "chip-select", onClick: () => setOpen(current => !current), style: { borderColor: open ? "var(--accent)" : "var(--border)" } },
        icon && e(Icon, { name: icon, size: 15, style: { color: "var(--text-3)" } }),
        label && e("span", { className: "faint", style: { fontWeight: 700 } }, label + ":"),
        e("span", { style: { fontWeight: 700 } }, value),
        e(Icon, { name: "chevD", size: 15, style: { transition: ".15s", transform: open ? "rotate(180deg)" : "none" } })),
      open && e(React.Fragment, null,
        e("div", { style: { position: "fixed", inset: 0, zIndex: 40 }, onClick: () => setOpen(false) }),
        e("div", { className: "card", style: { position: "absolute", top: 42, left: 0, minWidth: width, zIndex: 41, boxShadow: "var(--shadow-lg)", padding: 6, maxHeight: 320, overflowY: "auto" } },
          safeOptions.length
            ? safeOptions.map(option => {
                const row = typeof option === "string" ? { v: option, label: option } : option;
                const selected = row.v === value || row.label === value;
                return e("div", { key: row.v || row.label, className: "nav-item" + (selected ? " active" : ""), style: { height: 36 }, onClick: () => { onChange(row.v); setOpen(false); } },
                  row.dot && e("span", { style: { width: 9, height: 9, borderRadius: 3, background: row.dot, flex: "0 0 9px" } }),
                  e("span", { style: { flex: 1, fontWeight: 600, fontSize: 12.5 } }, row.label),
                  selected && e(Icon, { name: "check", size: 15 }));
              })
            : e("div", { className: "cell-sub", style: { padding: 10 } }, "No options available"))));
  }

  function KpiHero({ label, value, unit, sub, active, onClick }) {
    return e("div", {
      className: "card",
      onClick,
      style: {
        padding: "16px 18px",
        cursor: onClick ? "pointer" : "default",
        border: active ? "1.5px solid var(--accent)" : "1px solid var(--border)",
      },
    },
      e("div", { className: "stat-label" }, label),
      e("div", { className: "stat-val mono", style: { marginTop: 4, fontSize: 25 } }, value, unit && e("span", { className: "unit" }, " " + unit)),
      sub && e("div", { className: "kpi-mini", style: { marginTop: 10 } }, sub));
  }

  function SectionHead({ title, sub, right }) {
    return e("div", { className: "card-head" },
      e("div", null, e("div", { className: "card-title" }, title), sub && e("div", { className: "card-sub" }, sub)),
      right);
  }

  function projectMatrixExportRows() {
    return [];
  }

  function ProjectMatrix() {
    return e("div", { className: "empty" }, "Project matrix requires the governed Laravel report register; no local project rows are fabricated.");
  }

  function ExecutiveTab({ toast }) {
    const inventoryOptions = window.Builder360Server?.inventory_pricing_options || {};
    const exportUrl = inventoryOptions.can_export_project_cost_roi ? inventoryOptions.project_cost_roi_export_url : null;
    const exportProjectMatrix = () => downloadCsv("builder360-project-performance-matrix.csv", projectMatrixExportRows(), toast, "project cost and ROI matrix", exportUrl);
    return e("div", { className: "grid", style: { gap: 16 } },
      e(Card, {
        title: "Executive Reports",
        sub: "Static executive analytics are disabled. Scoped exports use governed Laravel report endpoints with filters and audit trail.",
        action: e(Button, { sm: true, icon: "download", disabled: !exportUrl, onClick: exportProjectMatrix, children: exportUrl ? "Export Cost/ROI CSV" : "Export CSV unavailable" }),
      },
        e("div", { className: "empty" }, "No local revenue, collection, cost, heatmap, inventory or ROI series are fabricated in this helper.")));
  }

  window.__REPORTS__ = {
    series,
    sum,
    delta,
    crf,
    MONTHS,
    G,
    PERIODS,
    PERIOD_LABEL,
    Dropdown,
    KpiHero,
    SectionHead,
    ExecutiveTab,
    ProjectMatrix,
    projectMatrixExportRows,
  };
})();
