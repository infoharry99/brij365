const React = window.React;

/* Builder360 — Interactive chart primitives for Reports & Analytics → window */
(function () {
  const e = React.createElement;
  const { Icon } = window;

  // ---- shared tooltip box ----
  function Tip({ x, y, children, align = "center" }) {
    return e("div", {
      style: {
        position: "absolute", left: x, top: y, transform:
          align === "center" ? "translate(-50%,-112%)" : "translate(-100%,-112%)",
        background: "var(--surface)", border: "1px solid var(--border-strong)",
        borderRadius: 10, boxShadow: "var(--shadow-lg)", padding: "9px 11px",
        pointerEvents: "none", zIndex: 20, minWidth: 120, whiteSpace: "nowrap",
      },
    }, children);
  }

  // =========================================================
  // ComboChart — grouped/stacked bars + overlaid lines, crosshair tooltip
  // props: labels[], bars:[{key,label,color,data[]}], lines:[{key,label,color,data[],fill}],
  //        height, unit, fmt(v)
  // =========================================================
  function ComboChart({ labels, bars = [], lines = [], height = 240, unit = "", fmt }) {
    const [hi, setHi] = React.useState(null);
    const wrapRef = React.useRef(null);
    const format = fmt || (v => v);
    const n = labels.length;
    const W = 760, H = height, padB = 26, padT = 14, padL = 6, padR = 6;
    const allVals = [...bars.flatMap(b => b.data), ...lines.flatMap(l => l.data)];
    const max = Math.max(...allVals, 1) * 1.16;
    const plotH = H - padB - padT;
    const colW = (W - padL - padR) / n;
    const y = v => padT + plotH - (v / max) * plotH;
    const lineX = i => padL + colW * i + colW / 2;

    const barGroupW = Math.min(colW * 0.5, 40);
    const oneBarW = bars.length ? barGroupW / bars.length : 0;

    return e("div", { ref: wrapRef, style: { position: "relative", width: "100%" },
      onMouseLeave: () => setHi(null) },
      e("svg", { viewBox: `0 0 ${W} ${H}`, width: "100%", height, preserveAspectRatio: "none", style: { display: "block", overflow: "visible" } },
        // gridlines
        [0, .25, .5, .75, 1].map((g, i) => e("line", { key: "g" + i, x1: padL, x2: W - padR,
          y1: padT + g * plotH, y2: padT + g * plotH, stroke: "var(--grid-line)", strokeWidth: 1 })),
        // crosshair
        hi != null && e("line", { x1: lineX(hi), x2: lineX(hi), y1: padT, y2: padT + plotH, stroke: "var(--border-strong)", strokeWidth: 1, strokeDasharray: "4 4" }),
        // bars
        labels.map((_, i) => {
          const gx = padL + colW * i + colW / 2 - barGroupW / 2;
          return e("g", { key: "b" + i }, bars.map((b, bi) => {
            const v = b.data[i];
            const bh = (v / max) * plotH;
            return e("rect", { key: bi, x: gx + bi * oneBarW + 1, y: y(v), width: oneBarW - 2,
              height: Math.max(0, bh), rx: 3, fill: b.color,
              opacity: hi == null || hi === i ? 1 : .38, style: { transition: "opacity .15s" } });
          }));
        }),
        // lines + areas
        lines.map((l, li) => {
          const pts = l.data.map((v, i) => `${lineX(i)},${y(v)}`).join(" ");
          const area = `M${lineX(0)},${padT + plotH} L${l.data.map((v, i) => `${lineX(i)},${y(v)}`).join(" L")} L${lineX(n - 1)},${padT + plotH} Z`;
          return e("g", { key: "l" + li },
            l.fill && e("path", { d: area, fill: l.color, opacity: .1 }),
            e("polyline", { points: pts, fill: "none", stroke: l.color, strokeWidth: 2.6, strokeLinecap: "round", strokeLinejoin: "round" }),
            l.data.map((v, i) => e("circle", { key: i, cx: lineX(i), cy: y(v), r: hi === i ? 4.5 : 2.8,
              fill: "var(--surface)", stroke: l.color, strokeWidth: 2.4, style: { transition: "r .12s" } })));
        }),
        // x labels
        labels.map((lb, i) => e("text", { key: "x" + i, x: lineX(i), y: H - 8, textAnchor: "middle",
          fontSize: 11, fontWeight: 700, fill: hi === i ? "var(--text)" : "var(--text-3)" }, lb)),
        // hover columns
        labels.map((_, i) => e("rect", { key: "h" + i, x: padL + colW * i, y: 0, width: colW, height: H,
          fill: "transparent", onMouseEnter: () => setHi(i), style: { cursor: "crosshair" } })),
      ),
      // tooltip
      hi != null && e(Tip, { x: (lineX(hi) / W * 100) + "%", y: 8 },
        e("div", { style: { fontSize: 11, fontWeight: 800, color: "var(--text-3)", marginBottom: 6, textTransform: "uppercase", letterSpacing: ".04em" } }, labels[hi]),
        [...bars, ...lines].map((s, i) => e("div", { key: i, style: { display: "flex", alignItems: "center", gap: 8, marginBottom: 3 } },
          e("i", { style: { width: 9, height: 9, borderRadius: 3, background: s.color, flex: "0 0 9px" } }),
          e("span", { style: { fontSize: 11.5, color: "var(--text-2)", fontWeight: 600, flex: 1 } }, s.label),
          e("span", { className: "mono", style: { fontSize: 12, fontWeight: 800 } }, format(s.data[hi])))) ),
    );
  }

  // =========================================================
  // Heatmap — rows × cols intensity grid with hover
  // props: rows:[{label,color,data[]}], cols[], fmt(v), accent
  // =========================================================
  function Heatmap({ rows, cols, fmt, accentRGB = "79,70,229" }) {
    const [hover, setHover] = React.useState(null);
    const format = fmt || (v => v);
    const max = Math.max(...rows.flatMap(r => r.data), 1);
    return e("div", { style: { position: "relative" } },
      e("div", { style: { display: "grid", gridTemplateColumns: `120px repeat(${cols.length}, 1fr)`, gap: 4, alignItems: "center" } },
        e("div"),
        cols.map((c, i) => e("div", { key: "c" + i, style: { fontSize: 10.5, fontWeight: 700, color: "var(--text-3)", textAlign: "center" } }, c)),
        rows.map((r, ri) => [
          e("div", { key: "rl" + ri, style: { display: "flex", alignItems: "center", gap: 7, fontSize: 12, fontWeight: 700, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" } },
            e("i", { style: { width: 8, height: 8, borderRadius: 2, background: r.color, flex: "0 0 8px" } }), r.label),
          ...r.data.map((v, ci) => {
            const t = v / max;
            const on = hover && hover.r === ri && hover.c === ci;
            return e("div", { key: ri + "-" + ci,
              onMouseEnter: () => setHover({ r: ri, c: ci, v, row: r.label, col: cols[ci] }),
              onMouseLeave: () => setHover(null),
              style: {
                aspectRatio: "1.7", borderRadius: 6, cursor: "pointer",
                background: `rgba(${accentRGB},${0.08 + t * 0.82})`,
                outline: on ? "2px solid var(--text)" : "none", outlineOffset: -1,
                display: "grid", placeItems: "center",
                transition: "outline .1s",
              } },
              e("span", { style: { fontSize: 10, fontWeight: 800, color: t > 0.5 ? "#fff" : "var(--text-2)" } }, v > 0 ? format(v) : ""));
          }),
        ]),
      ),
      hover && e("div", { style: { position: "absolute", top: -4, right: 0, transform: "translateY(-100%)",
        background: "var(--surface)", border: "1px solid var(--border-strong)", borderRadius: 9, padding: "7px 10px", boxShadow: "var(--shadow-lg)", pointerEvents: "none" } },
        e("div", { style: { fontSize: 11, fontWeight: 800 } }, hover.row),
        e("div", { style: { fontSize: 11, color: "var(--text-3)", fontWeight: 600 } }, hover.col + " · ", e("span", { className: "mono", style: { color: "var(--accent)", fontWeight: 800 } }, format(hover.v)))),
    );
  }

  // =========================================================
  // IDonut — interactive donut: hover/click a segment, center reflects it
  // props: data:[{label,value,color}], size, thickness, unit
  // =========================================================
  function IDonut({ data, size = 168, thickness = 26, fmt, totalLabel = "TOTAL" }) {
    const [sel, setSel] = React.useState(null);
    const format = fmt || (v => v.toLocaleString("en-IN"));
    const total = data.reduce((s, d) => s + d.value, 0);
    const r = (size - thickness) / 2;
    const c = 2 * Math.PI * r;
    let off = 0;
    const active = sel != null ? data[sel] : null;
    return e("div", { style: { display: "flex", gap: 20, alignItems: "center" } },
      e("div", { style: { position: "relative", width: size, height: size, flex: "0 0 " + size + "px" } },
        e("svg", { width: size, height: size, viewBox: `0 0 ${size} ${size}`, style: { transform: "rotate(-90deg)" } },
          e("circle", { cx: size / 2, cy: size / 2, r, fill: "none", stroke: "var(--surface-3)", strokeWidth: thickness }),
          data.map((d, i) => {
            const len = (d.value / total) * c;
            const seg = e("circle", { key: i, cx: size / 2, cy: size / 2, r, fill: "none", stroke: d.color,
              strokeWidth: sel === i ? thickness + 5 : thickness, strokeDasharray: `${len - 1.5} ${c - len + 1.5}`,
              strokeDashoffset: -off, strokeLinecap: "butt",
              opacity: sel == null || sel === i ? 1 : .35,
              onMouseEnter: () => setSel(i), onMouseLeave: () => setSel(null),
              style: { cursor: "pointer", transition: "stroke-width .15s, opacity .15s" } });
            off += len; return seg;
          })),
        e("div", { style: { position: "absolute", inset: 0, display: "grid", placeItems: "center", textAlign: "center", pointerEvents: "none" } },
          e("div", null,
            e("div", { className: "mono", style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: active ? 22 : 25, letterSpacing: "-.04em", color: active ? active.color : "var(--text)" } },
              active ? format(active.value) : format(total)),
            e("div", { style: { fontSize: 10.5, color: "var(--text-3)", fontWeight: 800, letterSpacing: ".05em", marginTop: 2 } },
              active ? active.label.toUpperCase() : totalLabel),
            active && e("div", { style: { fontSize: 11, fontWeight: 800, color: "var(--text-2)", marginTop: 1 } }, Math.round(active.value / total * 100) + "%"))),
      ),
      e("div", { className: "legend", style: { flex: 1 } },
        data.map((d, i) => e("div", { key: i, className: "legend-row",
          onMouseEnter: () => setSel(i), onMouseLeave: () => setSel(null),
          style: { cursor: "pointer", padding: "2px 0", opacity: sel == null || sel === i ? 1 : .5 } },
          e("i", { className: "lk", style: { background: d.color } }),
          e("span", { style: { fontWeight: sel === i ? 800 : 600 } }, d.label),
          e("span", { className: "lv" }, format(d.value))))),
    );
  }

  // =========================================================
  // RankBars — animated horizontal ranking bars with hover + rank index
  // props: data:[{label,value,display,color,sub}], showRank
  // =========================================================
  function RankBars({ data, showRank }) {
    const [hi, setHi] = React.useState(null);
    const max = Math.max(...data.map(d => d.value), 1);
    return e("div", { style: { display: "flex", flexDirection: "column", gap: 11 } },
      data.map((d, i) => e("div", { key: i, onMouseEnter: () => setHi(i), onMouseLeave: () => setHi(null),
        style: { cursor: "default" } },
        e("div", { className: "row between", style: { marginBottom: 5 } },
          e("span", { style: { fontSize: 12.5, fontWeight: 600, display: "flex", alignItems: "center", gap: 8 } },
            showRank && e("span", { style: { width: 18, height: 18, borderRadius: 5, background: i < 3 ? "var(--accent-soft)" : "var(--surface-3)", color: i < 3 ? "var(--accent)" : "var(--text-3)", fontSize: 10.5, fontWeight: 800, display: "grid", placeItems: "center" } }, i + 1),
            d.label, d.sub && e("span", { className: "faint", style: { fontWeight: 500 } }, d.sub)),
          e("span", { className: "mono", style: { fontSize: 12.5, fontWeight: 800, color: hi === i ? "var(--text)" : "var(--text-2)" } }, d.display != null ? d.display : d.value)),
        e("div", { style: { height: 9, borderRadius: 99, background: "var(--surface-3)", overflow: "hidden" } },
          e("div", { style: { width: (d.value / max * 100) + "%", height: "100%", borderRadius: 99, background: d.color || "var(--accent)", transition: "width .5s cubic-bezier(.2,.8,.2,1)", opacity: hi == null || hi === i ? 1 : .55 } })))),
    );
  }

  // =========================================================
  // StackBar — stacked vertical bars (e.g. cost composition per project)
  // props: labels[], series:[{key,label,color}], data:[ {label, [key]:v } ], height
  // =========================================================
  function StackBar({ labels, series, data, height = 220, fmt }) {
    const [hi, setHi] = React.useState(null);
    const format = fmt || (v => v);
    const totals = data.map(d => series.reduce((s, se) => s + (d[se.key] || 0), 0));
    const max = Math.max(...totals, 1) * 1.1;
    return e("div", { style: { position: "relative" } },
      e("div", { style: { display: "flex", alignItems: "flex-end", gap: 12, height, padding: "0 2px" } },
        data.map((d, i) => {
          const tot = totals[i];
          return e("div", { key: i, onMouseEnter: () => setHi(i), onMouseLeave: () => setHi(null),
            style: { flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 8, height: "100%" } },
            e("div", { style: { flex: 1, display: "flex", alignItems: "flex-end", width: "100%", justifyContent: "center" } },
              e("div", { style: { width: "62%", maxWidth: 46, height: (tot / max * 100) + "%", display: "flex", flexDirection: "column", borderRadius: "6px 6px 0 0", overflow: "hidden", opacity: hi == null || hi === i ? 1 : .5, transition: "opacity .15s", boxShadow: hi === i ? "var(--shadow-md)" : "none" } },
                series.map((se, si) => {
                  const v = d[se.key] || 0;
                  return e("div", { key: si, title: se.label, style: { height: (v / tot * 100) + "%", background: se.color } });
                }))),
            e("div", { style: { fontSize: 11, fontWeight: 700, color: hi === i ? "var(--text)" : "var(--text-3)", whiteSpace: "nowrap" } }, labels[i]));
        })),
      hi != null && e(Tip, { x: ((hi + 0.5) / data.length * 100) + "%", y: 6 },
        e("div", { style: { fontSize: 11, fontWeight: 800, marginBottom: 5 } }, labels[hi]),
        series.map((se, si) => e("div", { key: si, style: { display: "flex", alignItems: "center", gap: 8, marginBottom: 2 } },
          e("i", { style: { width: 8, height: 8, borderRadius: 2, background: se.color, flex: "0 0 8px" } }),
          e("span", { style: { fontSize: 11, color: "var(--text-2)", fontWeight: 600, flex: 1 } }, se.label),
          e("span", { className: "mono", style: { fontSize: 11.5, fontWeight: 800 } }, format(data[hi][se.key] || 0)))),
        e("div", { style: { borderTop: "1px solid var(--border)", marginTop: 5, paddingTop: 5, display: "flex", justifyContent: "space-between", gap: 14 } },
          e("span", { style: { fontSize: 11, fontWeight: 700, color: "var(--text-3)" } }, "Total"),
          e("span", { className: "mono", style: { fontSize: 12, fontWeight: 800 } }, format(totals[hi])))),
    );
  }

  // =========================================================
  // MiniArea — sparkline area with last-point dot for KPI hero cards
  // =========================================================
  function MiniArea({ data, color = "var(--accent)", w = 130, h = 42 }) {
    const max = Math.max(...data), min = Math.min(...data);
    const span = max - min || 1;
    const X = i => (i / (data.length - 1)) * w;
    const Y = v => h - 3 - ((v - min) / span) * (h - 8);
    const pts = data.map((v, i) => `${X(i)},${Y(v)}`).join(" ");
    const gid = "ga" + Math.round(Math.random() * 1e6);
    return e("svg", { width: w, height: h, viewBox: `0 0 ${w} ${h}`, preserveAspectRatio: "none", style: { display: "block" } },
      e("defs", null, e("linearGradient", { id: gid, x1: 0, y1: 0, x2: 0, y2: 1 },
        e("stop", { offset: "0%", stopColor: color, stopOpacity: .28 }),
        e("stop", { offset: "100%", stopColor: color, stopOpacity: 0 }))),
      e("polygon", { points: `0,${h} ${pts} ${w},${h}`, fill: `url(#${gid})` }),
      e("polyline", { points: pts, fill: "none", stroke: color, strokeWidth: 2, strokeLinecap: "round", strokeLinejoin: "round" }),
      e("circle", { cx: X(data.length - 1), cy: Y(data[data.length - 1]), r: 2.8, fill: color }));
  }

  // =========================================================
  // RadialGauge — full radial progress ring with center value
  // =========================================================
  function RadialGauge({ value, max = 100, size = 116, thickness = 11, color = "var(--accent)", label, suffix = "" }) {
    const r = (size - thickness) / 2;
    const c = 2 * Math.PI * r;
    const pct = Math.min(value / max, 1);
    return e("div", { style: { position: "relative", width: size, height: size } },
      e("svg", { width: size, height: size, viewBox: `0 0 ${size} ${size}`, style: { transform: "rotate(-90deg)" } },
        e("circle", { cx: size / 2, cy: size / 2, r, fill: "none", stroke: "var(--surface-3)", strokeWidth: thickness }),
        e("circle", { cx: size / 2, cy: size / 2, r, fill: "none", stroke: color, strokeWidth: thickness, strokeLinecap: "round",
          strokeDasharray: `${pct * c} ${c}`, style: { transition: "stroke-dasharray .6s cubic-bezier(.2,.8,.2,1)" } })),
      e("div", { style: { position: "absolute", inset: 0, display: "grid", placeItems: "center", textAlign: "center" } },
        e("div", null,
          e("div", { className: "mono", style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 22, letterSpacing: "-.04em" } }, value, suffix),
          label && e("div", { style: { fontSize: 10, color: "var(--text-3)", fontWeight: 700, marginTop: 1 } }, label))));
  }

  Object.assign(window, { RComboChart: ComboChart, RHeatmap: Heatmap, RIDonut: IDonut, RRankBars: RankBars, RStackBar: StackBar, RMiniArea: MiniArea, RRadialGauge: RadialGauge });
})();
