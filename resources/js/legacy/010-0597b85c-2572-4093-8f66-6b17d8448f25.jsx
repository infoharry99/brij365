const React = window.React;

/* Builder360 — Projects, Unit Inventory, Cost/ROI, Pricing */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, BarChart, LineChart, Gauge, Spark, HBars, PageHead, ChipSelect, Seg, Empty } = window;
  const e = React.createElement;
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const money = value => "₹" + Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
  const lakh = value => "₹" + (Number(value || 0) / 100000).toLocaleString("en-IN", { maximumFractionDigits: 1 }) + " L";
  const crore = value => "₹" + (Number(value || 0) / 10000000).toLocaleString("en-IN", { maximumFractionDigits: 2 }) + " Cr";
  const firstApiError = payload => {
    if (!payload) return "Request failed.";
    if (payload.message) return payload.message;
    const errors = payload.errors || {};
    const first = Object.values(errors)[0];
    return Array.isArray(first) ? first[0] : "Request failed.";
  };
  async function apiJson(url, options = {}) {
    const response = await fetch(url, {
      headers: {
        "Accept": "application/json",
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
  const statusTone = status => ({
    available: "b-green",
    reserved: "b-orange",
    on_hold: "b-orange",
    booked: "b-accent",
    registered: "b-violet",
    handed_over: "b-green",
    blocked: "b-slate",
    draft: "b-orange",
    active: "b-green",
    archived: "b-slate",
  }[status] || "b-slate");
  const statusLabel = status => String(status || "unknown").replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase());
  const dashboardPayload = () => window.Builder360Server?.dashboard || null;
  const serverProjectRows = () => {
    const payload = dashboardPayload();
    const projects = payload?.source === "laravel-sqlite" ? (payload.projects || []) : [];
    return projects;
  };
  const isServerBackedProjects = () => dashboardPayload()?.source === "laravel-sqlite";
  const percent = (part, total) => Number(total || 0) > 0 ? Math.round(Number(part || 0) / Number(total || 0) * 100) : 0;
  const crText = value => "₹" + Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 2 }) + " Cr";

  // ---------------- PROJECT MASTER ----------------
  function Projects({ toast }) {
    const projects = serverProjectRows();
    const serverBacked = isServerBackedProjects();
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Projects & Inventory", "Project Master"], title: "Project Master", sub: serverBacked ? "MySQL-backed project health, units, sales, cost and ROI from Laravel scope." : "Project dashboard API required; no local project list is fabricated.",
        actions: [
          e("span", { key: 0, className: "badge " + (serverBacked ? "b-blue" : "b-red"), style: { height: 28 } }, e(Icon, { name: serverBacked ? "database" : "alert", size: 13 }), serverBacked ? "DB-backed projects" : "API required"),
          e(Button, { key: 2, icon: "plus", variant: "primary", onClick: () => toast && toast("Project creation is available from the active Project Master screen using the governed Laravel project API.", "accent"), children: "Add Project" })
        ] }),
      projects.length === 0
        ? e(Empty, { icon: "building", title: "No project metrics loaded", sub: serverBacked ? "No project records are available in the current company/project scope." : "The Laravel dashboard payload is required before project health cards can be shown." })
        : e("div", { className: "grid g-3" }, projects.map(p => {
        const bUsed = percent(p.spent, p.budget);
        const soldPercent = percent(p.sold, p.units);
        return e("div", { key: p.id, className: "card", style: { overflow: "hidden", cursor: "pointer" } },
          e("div", { style: { height: 7, background: p.color } }),
          e("div", { className: "card-pad" },
            e("div", { className: "row between", style: { marginBottom: 12 } },
              e("div", null, e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 16.5 } }, p.name),
                e("div", { className: "cell-sub", style: { marginTop: 2 } }, e(Icon, { name: "pin", size: 11, style: { verticalAlign: -1, marginRight: 3 } }), p.city || p.code || "Project location")),
              e(Badge, { tone: p.status === "Possession" ? "b-green" : p.status === "Pre-launch" ? "b-violet" : "b-blue", dot: true }, p.status)),
            e("div", { className: "row gap-2", style: { marginBottom: 14, flexWrap: "wrap" } },
              e("span", { className: "tag" }, p.type || "Project"), e("span", { className: "tag" }, Number(p.units || 0).toLocaleString("en-IN") + " units"), e("span", { className: "tag mono" }, p.code || "Scoped")),
            e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 12 } },
              [["Construction", Number(p.progress || 0) + "%", Number(p.progress || 0)], ["Sold / Registered", soldPercent + "%", soldPercent]].map((r, i) =>
                e("div", { key: i }, e("div", { className: "kpi-mini", style: { marginBottom: 4 } }, r[0]),
                  e("div", { className: "row gap-2" }, e(Bar, { value: r[2], w: 70 }), e("span", { className: "mono", style: { fontSize: 12, fontWeight: 700 } }, r[1]))))),
            e("div", { className: "divider", style: { margin: "12px 0" } }),
            e("div", { className: "row between" },
              e("div", null, e("div", { className: "kpi-mini" }, "Budget used"), e("div", { className: "mono", style: { fontWeight: 800, fontSize: 15, color: bUsed > 85 ? "var(--red)" : "var(--text)" } }, crText(p.spent) + " / " + crText(p.budget))),
              e("div", { style: { textAlign: "right" } }, e("div", { className: "kpi-mini" }, "Revenue / ROI"), e("div", { className: "mono", style: { fontWeight: 800, fontSize: 15, color: "var(--green)" } }, crText(p.revenue) + " · " + Number(p.roi || 0).toLocaleString("en-IN", { maximumFractionDigits: 1 }) + "%"))),
            e("div", { className: "row gap-2", style: { marginTop: 14 } }, e(Avatar, { name: p.mgr || "Server scoped", sm: true }), e("span", { className: "cell-sub" }, "Laravel scope · " + (p.mgr || "Server scoped")))));
      })),
    );
  }

  // ---------------- UNIT INVENTORY (tower/floor grid) ----------------
  const UNIT_STATUS = {
    available: { label: "Available", c: "var(--green)", bg: "var(--green-soft)" },
    reserved: { label: "Reserved", c: "var(--orange)", bg: "var(--orange-soft)" },
    on_hold: { label: "On Hold", c: "var(--orange)", bg: "var(--orange-soft)" },
    booked: { label: "Booked", c: "var(--accent)", bg: "var(--accent-soft)" },
    registered: { label: "Registered", c: "var(--violet)", bg: "var(--violet-soft)" },
    handed_over: { label: "Handed Over", c: "var(--green)", bg: "var(--green-soft)" },
    blocked: { label: "Blocked", c: "var(--slate)", bg: "var(--slate-soft)" },
  };
  const unitStyleFor = status => UNIT_STATUS[status] || UNIT_STATUS.blocked;
  function UnitInventory({ toast }) {
    const options = window.Builder360Server?.inventory_pricing_options || null;
    const units = options?.units || [];
    const projects = options?.projects || [];
    const defaultProject = projects[0]?.id ? String(projects[0].id) : "all";
    const [projectId, setProjectId] = React.useState(defaultProject);
    const filteredByProject = projectId === "all" ? units : units.filter(unit => String(unit.project?.id) === String(projectId));
    const towers = [...new Set(filteredByProject.map(unit => unit.tower || "Tower").filter(Boolean))].sort();
    const [tower, setTower] = React.useState(towers[0] || "all");
    const [sel, setSel] = React.useState(null);
    React.useEffect(() => {
      if (towers.length && !towers.includes(tower)) setTower(towers[0]);
    }, [projectId, units.length]);
    const visibleUnits = tower === "all" ? filteredByProject : filteredByProject.filter(unit => String(unit.tower || "Tower") === String(tower));
    const rows = Object.values(visibleUnits.reduce((acc, unit) => {
      const floor = String(unit.floor || "0");
      acc[floor] = acc[floor] || { floor, units: [] };
      acc[floor].units.push(unit);
      return acc;
    }, {})).sort((a, b) => Number(b.floor) - Number(a.floor));
    const summary = options?.summary || {};
    const inventoryValue = summary.inventory_value || visibleUnits.reduce((sum, unit) => sum + Number(unit.total_price || 0), 0);
    const exportAvailability = () => {
      if (!options?.units_export_url || !options?.can_export_units) {
        toast("Your role cannot export unit availability.", "orange");
        return;
      }
      const url = new URL(options.units_export_url, window.location.origin);
      url.searchParams.set("format", "csv");
      if (projectId !== "all") url.searchParams.set("project_id", projectId);
      window.location.assign(url.toString());
      toast("Downloading scoped unit availability export from Laravel.", "green");
    };
    const unitGrid = rows.length === 0
      ? e(Empty, { icon: "layers", title: "No units found", sub: "Change project or tower filter to view scoped units." })
      : e("div", { style: { overflowX: "auto" } },
          e("div", { style: { minWidth: 560 } }, rows.map(r =>
            e("div", { key: r.floor, className: "row", style: { gap: 8, marginBottom: 8, alignItems: "center" } },
              e("div", { style: { width: 56, flex: "0 0 56px", fontSize: 11.5, fontWeight: 800, color: "var(--text-3)" } }, "FLOOR " + r.floor),
              e("div", { style: { display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(92px,1fr))", gap: 8, flex: 1 } }, r.units.map(u => {
                const s = unitStyleFor(u.status);
                return e("button", { key: u.id, onClick: () => setSel(u),
                  style: { height: 46, borderRadius: 9, border: "1.5px solid " + s.c, background: s.bg, color: s.c, fontWeight: 800, fontSize: 11.5, cursor: "pointer", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 1, transition: ".12s" } },
                  e("span", null, u.unit_number || u.unit_code),
                  e("span", { style: { fontSize: 9, fontWeight: 700, opacity: .85 } }, u.unit_type));
              }))
            )
          ))
        );
    const selectedModal = sel && e("div", { className: "scrim", onClick: () => setSel(null) },
      e("div", { className: "modal", onClick: ev => ev.stopPropagation() },
        e("div", { className: "card-head" },
          e("div", null,
            e("div", { className: "card-title", style: { fontSize: 17 } }, "Unit " + sel.unit_code),
            e("div", { className: "card-sub" }, `${sel.unit_type || "Unit"} · ${sel.project?.name || "Project"} · Tower ${sel.tower || "-"}`)),
          e("button", { className: "icon-btn", onClick: () => setSel(null) }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          e("div", { style: { marginBottom: 16 } }, e(Badge, { tone: statusTone(sel.status), dot: true }, statusLabel(sel.status))),
          e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 } },
            [["Configuration", sel.unit_type || "—"], ["Carpet Area", Number(sel.carpet_area_sqft || 0).toLocaleString("en-IN") + " sqft"], ["Saleable Area", Number(sel.saleable_area_sqft || 0).toLocaleString("en-IN") + " sqft"], ["Base Rate", money(sel.active_price_version?.base_rate || sel.base_rate) + " / sqft"], ["Active Price", lakh(sel.active_price_version?.total_price || sel.total_price)], ["Booking Ref", sel.active_booking?.booking_code || "Not linked"]].map((r, i) =>
              e("div", { key: i }, e("div", { className: "kpi-mini", style: { marginBottom: 3 } }, r[0]), e("div", { style: { fontWeight: 700, fontSize: 14 } }, r[1])))),
          e("div", { className: "divider" }),
          e("div", { className: "row gap-2" },
            sel.is_bookable
              ? e(Button, { key: 1, variant: "primary", icon: "tag", onClick: () => { toast("Use Sales & Booking to create a controlled booking for " + sel.unit_code + ".", "green"); setSel(null); }, children: "Start Booking" })
              : e(Button, { variant: "", icon: "eye", onClick: () => toast(sel.active_booking ? "Linked booking: " + sel.active_booking.booking_code : "Unit is not currently bookable.", "accent"), children: "View Linkage" }),
            sel.active_price_version && e(Button, { key: 2, icon: "rupee", onClick: () => toast("Active pricing " + sel.active_price_version.price_code + " · " + lakh(sel.active_price_version.total_price), "accent"), children: "Active Price" }))
        )
      )
    );
    if (!options?.source) {
      return e("div", { className: "page page-wide" },
        e(PageHead, { crumbs: ["Projects & Inventory", "Unit Inventory"], title: "Unit Inventory", sub: "No authorized inventory data is exposed for this user." }),
        e(Empty, { icon: "lock", title: "Inventory unavailable", sub: "The current role cannot view project inventory records." })
      );
    }
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Projects & Inventory", "Unit Inventory"], title: "Unit Inventory",
        sub: "MySQL-backed availability by project, tower and floor. Click a unit to inspect pricing, booking and status.",
        actions: [
          e("select", { key: 1, className: "input", value: projectId, onChange: ev => { setProjectId(ev.target.value); setSel(null); }, style: { height: 34, minWidth: 230 } },
            e("option", { value: "all" }, "All visible projects"),
            projects.map(project => e("option", { key: project.id, value: project.id }, project.label || project.name))),
          e(Button, { key: 2, icon: "download", onClick: exportAvailability, children: "Availability Export" })
        ] }),
      e("div", { className: "grid g-6", style: { marginBottom: 18 } },
        e(Stat, { label: "Total Units", value: summary.total_units || units.length, icon: "layers", tone: "accent" }),
        e(Stat, { label: "Available", value: summary.available || 0, icon: "home", tone: "green" }),
        e(Stat, { label: "Reserved / Hold", value: Number(summary.reserved || 0) + Number(summary.on_hold || 0), icon: "clock", tone: "orange" }),
        e(Stat, { label: "Booked", value: summary.booked || 0, icon: "tag", tone: "accent" }),
        e(Stat, { label: "Registered", value: summary.registered || 0, icon: "check", tone: "violet" }),
        e(Stat, { label: "Inventory Value", value: crore(inventoryValue), icon: "rupee", tone: "blue" }),
      ),
      e("div", { className: "card", style: { padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 16, flexWrap: "wrap", gap: 12 } },
          e("div", { className: "seg" }, (towers.length ? towers : ["all"]).map(t => e("button", { key: t, className: tower === t ? "on" : "", onClick: () => { setTower(t); setSel(null); } }, t === "all" ? "All Towers" : "Tower " + t))),
          e("div", { className: "inv-legend" }, Object.values(UNIT_STATUS).map((s, i) =>
            e("div", { className: "il", key: i }, e("i", { className: "ik", style: { background: s.bg, border: "1.5px solid " + s.c } }), s.label)))),
        unitGrid
      ),
      selectedModal,
    );
  }

  // ---------------- COST CONTROL & ROI ----------------
  function CostROI({ toast } = {}) {
    const options = window.Builder360Server?.inventory_pricing_options || null;
    const projects = serverProjectRows();
    const serverBacked = isServerBackedProjects();
    const [projectKey, setProjectKey] = React.useState(projects[0]?.id || projects[0]?.code || "all");
    React.useEffect(() => {
      if (projects.length && !projects.some(p => String(p.id || p.code) === String(projectKey))) {
        setProjectKey(projects[0].id || projects[0].code);
      }
    }, [projects.length]);
    const proj = projects.find(p => String(p.id || p.code) === String(projectKey)) || projects[0] || {};
    const budget = Number(proj.budget || 0);
    const spent = Number(proj.spent || 0);
    const revenue = Number(proj.revenue || 0);
    const roi = Number(proj.roi || 0);
    const remainingBudget = Math.max(budget - spent, 0);
    const budgetUsed = percent(spent, budget);
    const collectionCoverage = percent(revenue, budget || revenue);
    const projectedFinalCost = budget > 0 ? Math.max(spent, Math.round((spent + remainingBudget * 0.9) * 100) / 100) : spent;
    const expectedProfit = Math.max(revenue - projectedFinalCost, 0);
    const costRows = [
      { head: "Approved spend", budget, actual: spent, color: "var(--orange)", note: "Approved PO and contractor measurement spend" },
      { head: "Open budget", budget, actual: remainingBudget, color: "var(--green)", note: "Budget still available after approved spend" },
      { head: "Booked revenue", budget: Math.max(budget, revenue), actual: revenue, color: "var(--accent)", note: "Confirmed/agreement/registered booking value" },
    ];
    const exportCostRoi = () => {
      if (!options?.project_cost_roi_export_url || !options?.can_export_project_cost_roi) {
        toast && toast("Your role cannot export project cost and ROI reports.", "orange");
        return;
      }
      const url = new URL(options.project_cost_roi_export_url, window.location.origin);
      url.searchParams.set("format", "csv");
      if (proj.db_id) url.searchParams.set("project_id", proj.db_id);
      window.location.assign(url.toString());
      toast && toast("Downloading scoped project cost and ROI export from Laravel.", "green");
    };
    const distribution = costRows.filter(row => Number(row.actual || 0) > 0);
    const totalActual = distribution.reduce((sum, row) => sum + Number(row.actual || 0), 0);
    if (!projects.length) {
      return e("div", { className: "page page-wide" },
        e(PageHead, { crumbs: ["Projects & Inventory", "Cost Control & ROI"], title: "Cost Control & ROI Analytics", sub: "No project data is available for this user scope." }),
        e(Empty, { icon: "chart", title: "No project metrics", sub: "Project cost and ROI cards require scoped Laravel project records." })
      );
    }
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Projects & Inventory", "Cost Control & ROI"], title: "Cost Control & ROI Analytics",
        sub: serverBacked ? "MySQL-backed budget, approved spend, booked revenue, health and ROI from Laravel dashboard scope." : "Project dashboard API required; no local cost or ROI rows are fabricated.",
        actions: [
          e("div", { key: 1, className: "seg" }, projects.map(p => e("button", { key: p.id || p.code, className: String(projectKey) === String(p.id || p.code) ? "on" : "", onClick: () => setProjectKey(p.id || p.code) }, String(p.code || p.name || "Project").split("-")[0]))),
          e(Button, { key: 2, icon: "download", onClick: exportCostRoi, children: "Cost/ROI Export" })
        ] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Project Budget", value: crText(budget), icon: "wallet", tone: "accent", sub: "Approved project budget from Laravel" }),
        e(Stat, { label: "Approved Spend", value: crText(spent), icon: "rupee", tone: budgetUsed > 85 ? "red" : "orange", delta: budgetUsed + "% used", deltaDir: budgetUsed > 85 ? "up" : "flat" }),
        e(Stat, { label: "Booked Revenue", value: crText(revenue), icon: "trend", tone: "blue", delta: collectionCoverage + "% coverage", deltaDir: "flat", sub: "Confirmed booking value" }),
        e(Stat, { label: "Projected ROI", value: Number(roi).toLocaleString("en-IN", { maximumFractionDigits: 1 }), unit: "%", icon: "spark", tone: roi >= 20 ? "green" : "orange", sub: "Target/project ROI metric" }),
      ),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.5fr 1fr", alignItems: "start", marginBottom: 16 } },
        e(Card, { title: "Project Cost & Revenue Snapshot", sub: "₹ Cr · calculated from scoped Laravel project, booking and approved spend aggregates" },
          e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
            e("thead", null, e("tr", null, ["Metric", "Reference", "Actual", "Used", "Variance"].map((h, i) => e("th", { key: i, style: i > 0 && i < 3 ? { textAlign: "right" } : i === 4 ? { textAlign: "right" } : {} }, h)))),
            e("tbody", null, costRows.map((c, i) => {
              const used = percent(c.actual, c.budget);
              const variance = Number(c.budget || 0) - Number(c.actual || 0);
              return e("tr", { key: i },
                e("td", null, e("div", { className: "cell-user" }, e("i", { style: { width: 10, height: 10, borderRadius: 3, background: c.color } }), e("span", { className: "cell-strong" }, c.head)), e("div", { className: "cell-sub" }, c.note)),
                e("td", { className: "num" }, crText(c.budget)),
                e("td", { className: "num cell-strong" }, crText(c.actual)),
                e("td", null, e("div", { className: "prog-cell" }, e(Bar, { value: used, w: 60, tone: used > 90 ? "red" : used > 75 ? "orange" : "" }), e("span", { className: "pv" }, used + "%"))),
                e("td", { className: "num", style: { color: variance < 0 ? "var(--red)" : "var(--green)", fontWeight: 700 } }, crText(variance)));
            })),
            e("tfoot", null, e("tr", { style: { borderTop: "2px solid var(--border-strong)" } },
              e("td", { style: { fontWeight: 800, padding: "13px 16px" } }, "Total"),
              e("td", { className: "num", style: { fontWeight: 800 } }, crText(budget)),
              e("td", { className: "num", style: { fontWeight: 800 } }, crText(spent)),
              e("td", null, e("span", { className: "mono", style: { fontWeight: 800 } }, budgetUsed + "%")),
              e("td", { className: "num", style: { fontWeight: 800, color: remainingBudget > 0 ? "var(--green)" : "var(--red)" } }, crText(remainingBudget)))),
          ))),
        e("div", { className: "grid", style: { gap: 16 } },
          e(Card, { title: "Distribution", pad: true },
            e("div", { className: "center" }, e(Donut, { size: 168, thickness: 22, data: distribution.map(c => ({ value: c.actual, color: c.color })),
              center: e("div", null, e("div", { className: "mono", style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 20 } }, crText(totalActual)), e("div", { className: "kpi-mini" }, "tracked value")) })),
            e("div", { className: "legend", style: { marginTop: 16 } }, distribution.map((c, i) =>
              e("div", { className: "legend-row", key: i }, e("i", { className: "lk", style: { background: c.color } }), e("span", null, c.head), e("span", { className: "lv" }, crText(c.actual)))))),
        ),
      ),
      e("div", { className: "grid g-3" },
        e(Card, { title: "Cost-to-Complete Forecast", sub: "₹ Cr cumulative from current approved spend and remaining budget", pad: true },
          e(LineChart, { height: 150, labels: ["Now", "Q1", "Q2", "Q3", "Done"],
            series: [{ data: [spent, spent + remainingBudget * 0.25, spent + remainingBudget * 0.5, spent + remainingBudget * 0.75, projectedFinalCost], color: "var(--red)", fill: true },
              { data: [spent, budget * 0.55, budget * 0.7, budget * 0.85, budget], color: "var(--accent)" }] }),
          e("div", { className: "kpi-mini", style: { textAlign: "center", marginTop: 8 } }, "Projected final cost ", e("b", { style: { color: projectedFinalCost > budget ? "var(--red)" : "var(--green)" } }, crText(projectedFinalCost)))),
        e(Card, { title: "Cost per Unit", pad: true },
          e("div", { style: { textAlign: "center", padding: "8px 0" } },
            e("div", { className: "mono", style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 34, letterSpacing: "-.04em" } }, crText(Number(proj.units || 0) > 0 ? spent / Number(proj.units || 1) : 0)),
            e("div", { className: "kpi-mini" }, "approved spend per unit")),
          e("div", { className: "divider" }),
          [["Units", Number(proj.units || 0).toLocaleString("en-IN")], ["Sold / registered", Number(proj.sold || 0).toLocaleString("en-IN")], ["Revenue per unit", crText(Number(proj.units || 0) > 0 ? revenue / Number(proj.units || 1) : 0)], ["Budget used", budgetUsed + "%"]].map((r, i) =>
            e("div", { key: i, className: "row between", style: { padding: "6px 0", fontSize: 13 } }, e("span", { className: "muted" }, r[0]), e("span", { className: "mono", style: { fontWeight: 700, color: i === 3 ? "var(--green)" : "var(--text)" } }, r[1])))),
        e(Card, { title: "Profitability", pad: true },
          e("div", { className: "center", style: { paddingTop: 6 } }, e(Gauge, { value: roi, max: 35, color: roi >= 20 ? "var(--green)" : "var(--orange)", label: "Project ROI %" })),
          e("div", { className: "divider" }),
          [["Budget", crText(budget)], ["Booked revenue", crText(revenue)], ["Indicative profit", crText(expectedProfit)]].map((r, i) =>
            e("div", { key: i, className: "row between", style: { padding: "6px 0", fontSize: 13 } }, e("span", { className: "muted" }, r[0]), e("span", { className: "mono", style: { fontWeight: 700 } }, r[1])))),
      ),
    );
  }

  function PriceRevisionModal({ options, onClose, onSaved, toast }) {
    const units = (options?.units || []).filter(unit => unit.id && unit.saleable_area_sqft);
    const firstUnit = units[0];
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      project_unit_id: firstUnit?.id ? String(firstUnit.id) : "",
      effective_from: new Date().toISOString().slice(0, 10),
      base_rate: firstUnit?.active_price_version?.base_rate || firstUnit?.base_rate || "",
      floor_premium: firstUnit?.floor_rise || 0,
      location_premium: 0,
      parking_charges: firstUnit?.parking_charges || 0,
      other_charges: firstUnit?.other_charges || 0,
      tax_rate_percent: 5,
    });
    const update = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const selectedUnit = units.find(unit => String(unit.id) === String(form.project_unit_id));
    const preview = (() => {
      const saleable = Number(selectedUnit?.saleable_area_sqft || 0);
      const baseRate = Number(form.base_rate || 0);
      const gross = (saleable * baseRate)
        + Number(form.floor_premium || 0)
        + Number(form.location_premium || 0)
        + Number(form.parking_charges || 0)
        + Number(form.other_charges || 0);
      const tax = gross * Number(form.tax_rate_percent || 0) / 100;
      return { gross, tax, total: gross + tax };
    })();
    async function submit(ev) {
      ev.preventDefault();
      setError("");
      if (!form.project_unit_id) return setError("Select a unit.");
      if (Number(form.base_rate || 0) <= 0) return setError("Base rate must be greater than zero.");
      setBusy(true);
      try {
        const payload = await apiJson(options.price_versions_store_url, {
          method: "POST",
          body: JSON.stringify({
            ...form,
            project_unit_id: Number(form.project_unit_id),
            base_rate: Number(form.base_rate),
            floor_premium: Number(form.floor_premium || 0),
            location_premium: Number(form.location_premium || 0),
            parking_charges: Number(form.parking_charges || 0),
            other_charges: Number(form.other_charges || 0),
            tax_rate_percent: Number(form.tax_rate_percent || 0),
            metadata: { source: "legacy_pricing_screen" },
          }),
        });
        toast("Price revision " + (payload.data?.price_code || "") + " drafted for approval.", "green");
        onSaved(payload.data);
        onClose();
      } catch (err) {
        setError(err.message || "Could not create price revision.");
      } finally {
        setBusy(false);
      }
    }
    return e("div", { className: "scrim", onClick: onClose },
      e("form", { className: "modal", onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "card-head" },
          e("div", null, e("div", { className: "card-title", style: { fontSize: 17 } }, "Create Price Revision"),
            e("div", { className: "card-sub" }, "Drafts a versioned unit price record. Approval activates it and retires overlapping active versions.")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          error && e("div", { className: "alert err", style: { marginBottom: 12 } }, error),
          e("label", { className: "field" }, e("span", null, "Unit"),
            e("select", { value: form.project_unit_id, onChange: ev => update("project_unit_id", ev.target.value), disabled: busy },
              units.map(unit => e("option", { key: unit.id, value: unit.id }, `${unit.unit_code} · ${unit.project?.name || "Project"} · ${statusLabel(unit.status)}`)))),
          e("div", { className: "grid g-2" },
            e("label", { className: "field" }, e("span", null, "Effective From"), e("input", { type: "date", value: form.effective_from, onChange: ev => update("effective_from", ev.target.value), disabled: busy })),
            e("label", { className: "field" }, e("span", null, "Base Rate / sqft"), e("input", { type: "number", min: "0.01", step: "0.01", value: form.base_rate, onChange: ev => update("base_rate", ev.target.value), disabled: busy }))),
          e("div", { className: "grid g-2" },
            e("label", { className: "field" }, e("span", null, "Floor Premium"), e("input", { type: "number", min: "0", step: "0.01", value: form.floor_premium, onChange: ev => update("floor_premium", ev.target.value), disabled: busy })),
            e("label", { className: "field" }, e("span", null, "Location Premium"), e("input", { type: "number", min: "0", step: "0.01", value: form.location_premium, onChange: ev => update("location_premium", ev.target.value), disabled: busy }))),
          e("div", { className: "grid g-3" },
            e("label", { className: "field" }, e("span", null, "Parking"), e("input", { type: "number", min: "0", step: "0.01", value: form.parking_charges, onChange: ev => update("parking_charges", ev.target.value), disabled: busy })),
            e("label", { className: "field" }, e("span", null, "Other Charges"), e("input", { type: "number", min: "0", step: "0.01", value: form.other_charges, onChange: ev => update("other_charges", ev.target.value), disabled: busy })),
            e("label", { className: "field" }, e("span", null, "Tax %"), e("input", { type: "number", min: "0", max: "100", step: "0.01", value: form.tax_rate_percent, onChange: ev => update("tax_rate_percent", ev.target.value), disabled: busy }))),
          e("div", { className: "grid g-3", style: { margin: "12px 0" } },
            e(Stat, { label: "Gross Before Tax", value: lakh(preview.gross), icon: "rupee", tone: "accent" }),
            e(Stat, { label: "Tax", value: lakh(preview.tax), icon: "receipt", tone: "orange" }),
            e(Stat, { label: "Total Price", value: lakh(preview.total), icon: "tag", tone: "green" })),
          e("div", { className: "row end gap-2" },
            e(Button, { type: "button", onClick: onClose, disabled: busy, children: "Cancel" }),
            e(Button, { type: "submit", variant: "primary", icon: "check", disabled: busy || !units.length, children: busy ? "Saving..." : "Draft Revision" })))));
  }

  const priceApproveUrl = (template, version) => String(template || "").replace("__PRICE_VERSION__", version?.id || "");

  // ---------------- PRICING INTELLIGENCE ----------------
  function Pricing({ toast }) {
    const options = window.Builder360Server?.inventory_pricing_options || null;
    const [creating, setCreating] = React.useState(false);
    const [createdVersions, setCreatedVersions] = React.useState([]);
    const [approvedVersions, setApprovedVersions] = React.useState({});
    const [busyId, setBusyId] = React.useState(null);
    const versions = [...createdVersions, ...(options?.price_versions || [])].map(version => approvedVersions[version.id] || version);
    async function approve(version) {
      const url = priceApproveUrl(options?.price_versions_approve_url_template, version);
      if (!url) return;
      setBusyId(version.id);
      try {
        const payload = await apiJson(url, {
          method: "PATCH",
          body: JSON.stringify({ note: "Approved from Pricing Intelligence screen." }),
        });
        setApprovedVersions(prev => ({ ...prev, [version.id]: payload.data }));
        toast("Price version " + (payload.data?.price_code || version.price_code) + " approved.", "green");
      } catch (err) {
        toast(err.message || "Could not approve price version.", "red");
      } finally {
        setBusyId(null);
      }
    }
    if (!options?.source) {
      return e("div", { className: "page page-wide" },
        e(PageHead, { crumbs: ["Projects & Inventory", "Pricing Intelligence"], title: "Pricing Intelligence", sub: "No authorized pricing data is exposed for this user." }),
        e(Empty, { icon: "lock", title: "Pricing unavailable", sub: "The current role cannot view or manage unit price versions." }));
    }
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Projects & Inventory", "Pricing Intelligence"], title: "Pricing Intelligence", sub: "Versioned pricing, effective dates, approval control and active price traceability from MySQL records.",
        actions: [
          e("span", { key: 0, className: "badge b-blue", style: { height: 28 } }, e(Icon, { name: "shield", size: 13 }), "DB-backed pricing"),
          options.can_create_price_version && e(Button, { key: 1, icon: "plus", variant: "primary", onClick: () => setCreating(true), children: "New Revision" })
        ].filter(Boolean) }),
      e("div", { className: "card", style: { padding: 18, marginBottom: 16, background: "var(--accent-soft)", border: "1px solid transparent" } },
        e("div", { className: "row gap-3" }, e("div", { style: { width: 40, height: 40, borderRadius: 11, background: "var(--accent)", color: "#fff", display: "grid", placeItems: "center", flex: "0 0 40px" } }, e(Icon, { name: "spark", size: 20 })),
          e("div", null, e("div", { style: { fontWeight: 800, fontSize: 14, color: "var(--accent)" } }, "Controlled pricing workflow"),
            e("div", { style: { fontSize: 12.5, color: "var(--text-2)", marginTop: 2 } }, "Draft versions are created through Laravel validation. Approval activates the version, retires overlapping active versions, and records audit history.")))),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Active Versions", value: options.summary?.active_price_versions || 0, icon: "check", tone: "green" }),
        e(Stat, { label: "Draft Revisions", value: options.summary?.draft_price_versions || 0, icon: "clock", tone: "orange" }),
        e(Stat, { label: "Visible Units", value: options.summary?.total_units || 0, icon: "layers", tone: "accent" }),
        e(Stat, { label: "Inventory Value", value: crore(options.summary?.inventory_value || 0), icon: "rupee", tone: "blue" })),
      e(Card, { title: "Unit Price Version Register", sub: "Effective pricing records scoped by company/project permissions" },
        e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
          e("thead", null, e("tr", null, ["Version", "Unit", "Effective", "Base ₹/sqft", "Gross", "Tax", "Total", "Status", "Action"].map((h, i) => e("th", { key: i, style: i >= 3 && i <= 6 ? { textAlign: "right" } : {} }, h)))),
          e("tbody", null,
            versions.length === 0
              ? e("tr", null, e("td", { colSpan: 9 }, e(Empty, { icon: "tag", title: "No price versions", sub: "Create a price revision to begin controlled effective pricing." })))
              : versions.map(v =>
                e("tr", { key: v.id },
                  e("td", null, e("div", { className: "cell-strong mono" }, v.price_code), e("div", { className: "cell-sub" }, "Version " + v.version_number)),
                  e("td", null, e("div", { className: "cell-strong" }, v.unit?.unit_code || "Unit"), e("div", { className: "cell-sub" }, `${v.project?.code || "Project"} · ${v.unit?.unit_type || "Type"}`)),
                  e("td", null, e("div", { className: "mono" }, v.effective_from || "—"), e("div", { className: "cell-sub" }, v.effective_to ? "Until " + v.effective_to : "Open ended")),
                  e("td", { className: "num mono" }, money(v.base_rate)),
                  e("td", { className: "num mono" }, lakh(v.gross_price_before_tax)),
                  e("td", { className: "num mono" }, `${lakh(v.tax_amount)} (${Number(v.tax_rate_percent || 0)}%)`),
                  e("td", { className: "num mono cell-strong" }, lakh(v.total_price)),
                  e("td", null, e(Badge, { tone: statusTone(v.status), dot: true }, statusLabel(v.status))),
                  e("td", null,
                    v.can_approve
                      ? e(Button, { sm: true, variant: "primary", disabled: busyId === v.id, onClick: () => approve(v), children: busyId === v.id ? "Approving..." : "Approve" })
                      : e(Button, { sm: true, onClick: () => toast((v.workflow_history || []).slice(-1)[0]?.note || "No pending action for this version.", "accent"), children: "History" }))))),
        ))),
      creating && e(PriceRevisionModal, { options, onClose: () => setCreating(false), onSaved: row => setCreatedVersions(prev => [row, ...prev]), toast }),
    );
  }

  Object.assign(window, { Projects, UnitInventory, CostROI, Pricing });
})();
