const React = window.React;

/* Builder360 — Shared UI components → window */
(function () {
  const { Icon } = window;
  const e = React.createElement;

  // ---- Avatar ----
  function Avatar({ name, color, size = 28, sm }) {
    const initials = (name || "?").split(" ").map(w => w[0]).slice(0, 2).join("");
    return e("div", { className: sm ? "avatar-sm" : "avatar",
      style: { background: color ? color : "var(--accent-grad)", width: size, height: size, flexBasis: size } }, initials);
  }

  // ---- Badge ----
  function Badge({ children, tone = "b-slate", dot }) {
    return e("span", { className: "badge " + tone }, dot && e("i", { className: "bdot" }), children);
  }

  // ---- StatCard ----
  function Stat({ label, value, unit, icon, tone = "accent", delta, deltaDir, sub, onClick, title, ...rest }) {
    const toneMap = {
      accent: ["var(--accent)", "var(--accent-soft)"], green: ["var(--green)", "var(--green-soft)"],
      orange: ["var(--orange)", "var(--orange-soft)"], red: ["var(--red)", "var(--red-soft)"],
      blue: ["var(--blue)", "var(--blue-soft)"], violet: ["var(--violet)", "var(--violet-soft)"],
    };
    const [c, bg] = toneMap[tone] || toneMap.accent;
    const clickable = typeof onClick === "function";
    return e("div", { ...rest, className: "card stat" + (clickable ? " stat-clickable" : ""), role: clickable ? "button" : undefined, tabIndex: clickable ? 0 : undefined, title, onClick, onKeyDown: clickable ? ev => { if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); onClick(ev); } } : undefined },
      e("div", { className: "stat-top" },
        e("div", { className: "stat-ic", style: { background: bg, color: c } }, e(Icon, { name: icon, size: 18 })),
        delta != null && e("span", { className: "stat-delta " + (deltaDir === "up" ? "delta-up" : deltaDir === "down" ? "delta-down" : "delta-flat") },
          e(Icon, { name: deltaDir === "up" ? "arrowUp" : deltaDir === "down" ? "arrowDown" : "trend", size: 12 }), delta),
      ),
      e("div", { className: "stat-label" }, label),
      e("div", { className: "stat-val mono", style: { marginTop: 3 } }, value, unit && e("span", { className: "unit" }, " " + unit)),
      sub && e("div", { className: "kpi-mini", style: { marginTop: 6 } }, sub),
    );
  }

  // ---- Button ----
  function Button({ children, variant = "", icon, onClick, sm, style, disabled = false, title, type = "button", ...rest }) {
    const disabledStyle = disabled ? { opacity: 0.55, cursor: "not-allowed" } : {};
    return e("button", { ...rest, type, disabled, title, className: "btn " + (variant ? "btn-" + variant : "") + (sm ? " btn-sm" : ""), onClick: disabled ? undefined : onClick, style: Object.assign({}, style || {}, disabledStyle) },
      icon && e(Icon, { name: icon, size: sm ? 15 : 16 }), children);
  }

  // ---- Card wrapper ----
  function Card({ title, sub, action, children, pad, style, className = "" }) {
    return e("div", { className: "card " + className, style },
      (title || action) && e("div", { className: "card-head" },
        e("div", null, e("div", { className: "card-title" }, title), sub && e("div", { className: "card-sub" }, sub)),
        action),
      e("div", { className: pad ? "card-pad" : "", style: pad ? {} : {} }, children),
    );
  }

  // ---- Progress bar ----
  function Bar({ value, tone, w = 80 }) {
    const cls = value >= 100 ? "green" : tone || (value < 35 ? "red" : value < 70 ? "orange" : "");
    return e("div", { className: "bar " + (cls ? cls : ""), style: { width: w } }, e("i", { style: { width: Math.min(100, value) + "%" } }));
  }
  function ProgCell({ value, tone }) {
    return e("div", { className: "prog-cell" }, e(Bar, { value, tone, w: 64 }), e("span", { className: "pv" }, value + "%"));
  }

  // ================= CHARTS =================

  // Donut chart
  function Donut({ data, size = 150, thickness = 20, center }) {
    const total = data.reduce((s, d) => s + d.value, 0);
    const r = (size - thickness) / 2;
    const c = 2 * Math.PI * r;
    let off = 0;
    return e("div", { style: { position: "relative", width: size, height: size } },
      e("svg", { width: size, height: size, viewBox: `0 0 ${size} ${size}`, style: { transform: "rotate(-90deg)" } },
        e("circle", { cx: size / 2, cy: size / 2, r, fill: "none", stroke: "var(--surface-3)", strokeWidth: thickness }),
        data.map((d, i) => {
          const len = (d.value / total) * c;
          const seg = e("circle", { key: i, cx: size / 2, cy: size / 2, r, fill: "none", stroke: d.color, strokeWidth: thickness,
            strokeDasharray: `${len} ${c - len}`, strokeDashoffset: -off, strokeLinecap: "butt" });
          off += len; return seg;
        }),
      ),
      center && e("div", { style: { position: "absolute", inset: 0, display: "grid", placeItems: "center", textAlign: "center" } }, center),
    );
  }

  // Vertical bar chart (grouped budget vs actual)
  function BarChart({ data, height = 200, money, dual }) {
    const max = Math.max(...data.flatMap(d => dual ? [d.a, d.b] : [d.value])) * 1.15;
    return e("div", { style: { display: "flex", alignItems: "flex-end", gap: 14, height, padding: "0 4px" } },
      data.map((d, i) =>
        e("div", { key: i, style: { flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 8, height: "100%" } },
          e("div", { style: { flex: 1, display: "flex", alignItems: "flex-end", gap: 5, width: "100%", justifyContent: "center" } },
            dual
              ? [e("div", { key: "a", title: "Budget", style: { width: 16, height: (d.a / max * 100) + "%", background: "var(--surface-3)", border: "1px solid var(--border-strong)", borderRadius: "5px 5px 0 0" } }),
                 e("div", { key: "b", title: "Actual", style: { width: 16, height: (d.b / max * 100) + "%", background: d.over ? "var(--red)" : "var(--accent)", borderRadius: "5px 5px 0 0" } })]
              : e("div", { style: { width: 30, height: (d.value / max * 100) + "%", background: d.color || "var(--accent)", borderRadius: "6px 6px 0 0", transition: ".4s" } }),
          ),
          e("div", { style: { fontSize: 11, fontWeight: 700, color: "var(--text-3)", textAlign: "center", whiteSpace: "nowrap" } }, d.label),
        ),
      ),
    );
  }

  // Line / area chart
  function LineChart({ series, height = 200, labels, money }) {
    const W = 600, H = height;
    const safeSeries = (Array.isArray(series) ? series : []).map(s => ({ ...s, data: (Array.isArray(s.data) ? s.data : []).map(v => Number.isFinite(Number(v)) ? Number(v) : 0) }));
    const all = safeSeries.flatMap(s => s.data);
    const rawMax = Math.max(...all, 0), min = Math.min(...all, 0);
    const max = rawMax === min ? rawMax + 1 : rawMax * 1.1;
    const n = Math.max(1, safeSeries[0]?.data?.length || 1);
    const x = i => n === 1 ? W / 2 : (i / (n - 1)) * (W - 20) + 10;
    const y = v => H - 18 - ((v - min) / Math.max(max - min, 1)) * (H - 36);
    return e("div", { style: { width: "100%" } },
      e("svg", { viewBox: `0 0 ${W} ${H}`, width: "100%", height, preserveAspectRatio: "none" },
        [0.25, 0.5, 0.75, 1].map((g, i) => e("line", { key: i, x1: 10, x2: W - 10, y1: 18 + g * (H - 36), y2: 18 + g * (H - 36), stroke: "var(--grid-line)", strokeWidth: 1 })),
        safeSeries.map((s, si) => {
          const pts = s.data.map((v, i) => `${x(i)},${y(v)}`).join(" ");
          const area = `M${x(0)},${H - 18} L${pts.split(" ").join(" L")} L${x(n - 1)},${H - 18} Z`;
          return e("g", { key: si },
            s.fill && e("path", { d: area, fill: s.color, opacity: 0.08 }),
            e("polyline", { points: pts, fill: "none", stroke: s.color, strokeWidth: 2.4, strokeLinecap: "round", strokeLinejoin: "round" }),
            s.data.map((v, i) => e("circle", { key: i, cx: x(i), cy: y(v), r: 2.6, fill: "var(--surface)", stroke: s.color, strokeWidth: 2 })),
          );
        }),
      ),
      labels && e("div", { style: { display: "flex", justifyContent: "space-between", marginTop: 6, padding: "0 6px" } },
        labels.map((l, i) => e("span", { key: i, style: { fontSize: 10.5, color: "var(--text-3)", fontWeight: 600 } }, l))),
    );
  }

  // Gauge (semicircle) for health/ROI
  function Gauge({ value, max = 100, size = 140, color = "var(--accent)", label }) {
    const r = size / 2 - 12, c = Math.PI * r;
    const pct = Math.min(value / max, 1);
    return e("div", { style: { position: "relative", width: size, height: size / 2 + 18 } },
      e("svg", { width: size, height: size / 2 + 18, viewBox: `0 0 ${size} ${size / 2 + 18}` },
        e("path", { d: `M12 ${size / 2} A ${r} ${r} 0 0 1 ${size - 12} ${size / 2}`, fill: "none", stroke: "var(--surface-3)", strokeWidth: 11, strokeLinecap: "round" }),
        e("path", { d: `M12 ${size / 2} A ${r} ${r} 0 0 1 ${size - 12} ${size / 2}`, fill: "none", stroke: color, strokeWidth: 11, strokeLinecap: "round",
          strokeDasharray: `${pct * c} ${c}` }),
      ),
      e("div", { style: { position: "absolute", bottom: 0, left: 0, right: 0, textAlign: "center" } },
        e("div", { className: "mono", style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 26, letterSpacing: "-.04em" } }, value),
        label && e("div", { style: { fontSize: 11, color: "var(--text-3)", fontWeight: 700 } }, label)),
    );
  }

  // Sparkline
  function Spark({ data, color = "var(--accent)", w = 84, h = 28, fill }) {
    const safeData = (Array.isArray(data) && data.length ? data : [0]).map(v => Number.isFinite(Number(v)) ? Number(v) : 0);
    const max = Math.max(...safeData), min = Math.min(...safeData);
    const pts = safeData.map((v, i) => `${safeData.length === 1 ? w / 2 : (i / (safeData.length - 1)) * w},${h - ((v - min) / (max - min || 1)) * (h - 4) - 2}`).join(" ");
    return e("svg", { width: w, height: h, viewBox: `0 0 ${w} ${h}` },
      fill && e("polygon", { points: `0,${h} ${pts} ${w},${h}`, fill: color, opacity: .12 }),
      e("polyline", { points: pts, fill: "none", stroke: color, strokeWidth: 2, strokeLinecap: "round", strokeLinejoin: "round" }));
  }

  // Horizontal bars (ranking)
  function HBars({ data, money }) {
    const max = Math.max(...data.map(d => d.value));
    return e("div", { style: { display: "flex", flexDirection: "column", gap: 13 } },
      data.map((d, i) => e("div", { key: i },
        e("div", { className: "row between", style: { marginBottom: 5 } },
          e("span", { style: { fontSize: 12.5, fontWeight: 600 } }, d.label),
          e("span", { className: "mono", style: { fontSize: 12.5, fontWeight: 700, color: "var(--text-2)" } }, d.display || d.value)),
        e("div", { className: "bar", style: { width: "100%", height: 8 } }, e("i", { style: { width: (d.value / max * 100) + "%", background: d.color || "var(--accent)" } })),
      )));
  }

  // ---- Page header ----
  function PageHead({ crumbs, title, sub, actions }) {
    return e("div", null,
      crumbs && e("div", { className: "crumbs" }, crumbs.map((c, i) => e(React.Fragment, { key: i }, i > 0 && e("span", { className: "sep" }, "/"), e("span", { style: i === crumbs.length - 1 ? { color: "var(--text-2)" } : {} }, c)))),
      e("div", { className: "page-head" },
        e("div", null, e("h1", { className: "page-title" }, title), sub && e("div", { className: "page-sub" }, sub)),
        actions && e("div", { className: "head-actions" }, actions)),
    );
  }

  // ---- Filter bar pieces ----
  function ChipSelect({ label, value, icon = "chevD" }) {
    return e("button", { className: "chip-select" }, label && e("span", { className: "faint", style: { fontWeight: 700 } }, label + ":"), e("span", null, value), e(Icon, { name: icon, size: 15 }));
  }
  function Seg({ options, value, onChange }) {
    return e("div", { className: "seg" }, options.map(o => e("button", { key: o, className: value === o ? "on" : "", onClick: () => onChange && onChange(o) }, o)));
  }

  // ---- Empty state ----
  function Empty({ icon = "box", title, sub, action }) {
    return e("div", { className: "empty" },
      e("div", { className: "empty-ic" }, e(Icon, { name: icon, size: 26 })),
      e("h3", null, title), sub && e("div", { style: { maxWidth: 320 } }, sub), action && e("div", { style: { marginTop: 14 } }, action));
  }

  // ---- generic data table ----
  function DataTable({ columns, rows, renderCell }) {
    return e("div", { className: "card" }, e("div", { className: "tbl-wrap" },
      e("table", { className: "tbl" },
        e("thead", null, e("tr", null, columns.map((c, i) => e("th", { key: i, style: c.num ? { textAlign: "right" } : {} }, c.label)))),
        e("tbody", null, rows.map((r, ri) => e("tr", { key: ri }, columns.map((c, ci) => e("td", { key: ci, className: c.num ? "num" : "" }, renderCell ? renderCell(r, c) : r[c.key]))))),
      )));
  }

  function SearchablePeoplePicker({
    items = [],
    selected,
    onChange,
    mode = "single",
    placeholder = "Search employee name...",
    disabled = false,
    required = false,
    emptyText = "No matching employees",
    getId = item => item?.id,
    getLabel = item => item?.label || item?.name || item?.email || "Employee",
    getSubLabel = item => [item?.employee_code || item?.code, item?.designation || item?.title || item?.role, item?.department || item?.team || item?.email].filter(Boolean).join(" · "),
  }) {
    const [query, setQuery] = React.useState("");
    const rows = Array.isArray(items) ? items.filter(Boolean) : [];
    const ids = mode === "multi"
      ? new Set((Array.isArray(selected) ? selected : []).map(String))
      : new Set(selected !== undefined && selected !== null && selected !== "" ? [String(selected)] : []);
    const searchableText = item => [getLabel(item), getSubLabel(item), item?.name, item?.label, item?.employee_code, item?.code, item?.email, item?.role, item?.title, item?.designation, item?.department, item?.team]
      .filter(Boolean).join(" ").toLowerCase();
    const visible = query.trim()
      ? rows.filter(item => searchableText(item).includes(query.trim().toLowerCase()))
      : rows;
    const choose = item => {
      if (disabled) return;
      const id = getId(item);
      if (id === undefined || id === null || id === "") return;
      if (mode === "multi") {
        const value = String(id);
        const next = new Set(ids);
        next.has(value) ? next.delete(value) : next.add(value);
        onChange && onChange(Array.from(next));
      } else {
        onChange && onChange(String(id));
      }
    };
    const selectedRows = rows.filter(item => ids.has(String(getId(item))));
    const showSearch = rows.length > 1 && !disabled;
    return e("div", { className: "people-search-picker" + (disabled ? " disabled" : "") + (required && !ids.size ? " required" : "") },
      selectedRows.length > 0 && e("div", { className: "people-search-selected" }, selectedRows.map(item =>
        e("span", { key: String(getId(item)), className: "people-search-chip" },
          e(Avatar, { name: getLabel(item), color: item?.color, size: 20 }),
          e("span", null, getLabel(item))))),
      showSearch && e("div", { className: "people-search-input" },
        e(Icon, { name: "search", size: 14 }),
        e("input", {
          value: query,
          placeholder,
          disabled,
          onChange: ev => setQuery(ev.target.value),
          onKeyDown: ev => { if (ev.key === "Escape") setQuery(""); },
          "aria-label": placeholder,
        }),
        query && e("button", { type: "button", onClick: () => setQuery(""), title: "Clear search" }, e(Icon, { name: "x", size: 12 }))),
      e("div", { className: "people-search-list", role: mode === "multi" ? "listbox" : "radiogroup", "aria-multiselectable": mode === "multi" ? "true" : undefined },
        visible.length ? visible.map(item => {
          const id = String(getId(item));
          const on = ids.has(id);
          return e("button", {
            key: id,
            type: "button",
            className: "people-search-option" + (on ? " on" : ""),
            disabled,
            onClick: () => choose(item),
            role: "option",
            "aria-selected": on ? "true" : "false",
          },
            e(Avatar, { name: getLabel(item), color: item?.color, size: 26 }),
            e("span", { className: "people-search-text" },
              e("b", null, getLabel(item)),
              getSubLabel(item) && e("small", null, getSubLabel(item))),
            on && e(Icon, { name: "check", size: 15, style: { color: "var(--green)" } }));
        }) : e("div", { className: "people-search-empty" }, emptyText)));
  }

  Object.assign(window, { Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, BarChart, LineChart, Gauge, Spark, HBars, PageHead, ChipSelect, Seg, Empty, DataTable, SearchablePeoplePicker });
})();
