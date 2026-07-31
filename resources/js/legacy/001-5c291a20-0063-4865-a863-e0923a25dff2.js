/* ============================================================
   Builder360 — Laravel-backed shell bootstrap
   Exposes window.DB for legacy shell compatibility only.
   Business rows must come from window.Builder360Server payloads.
   ============================================================ */
(function () {
  let server = window.Builder360Server || {};
  let user = server.user || null;
  let dashboard = server.dashboard || null;

  const INR = (n) => "₹" + Number(n || 0).toLocaleString("en-IN");
  const cr = (n) => "₹" + Number(n || 0).toLocaleString("en-IN") + " Cr";
  const lac = (n) => "₹" + Number(n || 0).toLocaleString("en-IN") + " L";

  const shellSidebar = [
    { group: "Overview", items: [
      { id: "dashboard", label: "Dashboard", icon: "grid" },
      { id: "approvals", label: "Approvals", icon: "check" },
      { id: "notifications", label: "Notifications", icon: "bell" },
      { id: "reports", label: "Reports & Analytics", icon: "chart" },
    ]},
    { group: "Work Management", items: [
      { id: "tasks", label: "Task Management", icon: "tasks" },
      { id: "calendar", label: "Calendar Management", icon: "calClock" },
    ]},
    { group: "Collaboration", items: [
      { id: "chat", label: "Chat Connect", icon: "bubble" },
      { id: "mailbox", label: "Mailbox", icon: "mail" },
    ]},
    { group: "Sales & CRM", items: [
      { id: "leads", label: "Lead Management", icon: "users" },
      { id: "qualification", label: "Lead Qualification", icon: "filter" },
      { id: "sitevisits", label: "Site Visits", icon: "calendar" },
      { id: "sales", label: "Sales & Booking", icon: "tag" },
      { id: "marketing", label: "Marketing", icon: "mega" },
      { id: "collections", label: "Customer Collections", icon: "rupee" },
      { id: "funnel", label: "Lead Funnel Analytics", icon: "funnel" },
      { id: "performance", label: "Performance Analytics", icon: "star" },
    ]},
    { group: "Projects & Inventory", items: [
      { id: "projects", label: "Project Master", icon: "building" },
      { id: "inventory", label: "Unit Inventory", icon: "layers" },
      { id: "pricing", label: "Pricing Intelligence", icon: "spark" },
      { id: "cost", label: "Cost Control & ROI", icon: "trend" },
    ]},
    { group: "Construction", items: [
      { id: "planning", label: "Construction Planning", icon: "calendar" },
      { id: "progress", label: "Daily Site Progress", icon: "hardhat" },
      { id: "materials", label: "Material & Store", icon: "box" },
      { id: "procurement", label: "Procurement", icon: "cart" },
      { id: "vendors", label: "Vendor Management", icon: "truck" },
      { id: "contractors", label: "Contractor Mgmt", icon: "wrench" },
      { id: "boq", label: "BOQ / Measurement", icon: "ruler" },
    ]},
    { group: "Operations", items: [
      { id: "hr", label: "HR & Employees", icon: "id" },
      { id: "finance", label: "Accounts & Finance", icon: "wallet" },
      { id: "legal", label: "Legal / RERA", icon: "shield" },
      { id: "documents", label: "Document Mgmt", icon: "folder" },
      { id: "possession", label: "Possession & Handover", icon: "key" },
      { id: "complaints", label: "After-Sales", icon: "headset" },
      { id: "maintenance", label: "Maintenance & Society", icon: "wrench" },
    ]},
    { group: "Customer", items: [
      { id: "buyer", label: "Buyer Portal", icon: "home" },
      { id: "inquiry", label: "Prospect Inquiry", icon: "globe" },
      { id: "mobile", label: "Mobile Apps", icon: "phone" },
    ]},
    { group: "System", items: [
      { id: "admin", label: "Admin & Masters", icon: "sliders" },
      { id: "workflows", label: "Business Workflows", icon: "funnel" },
      { id: "audit", label: "Audit Trail", icon: "eye" },
      { id: "auth", label: "Authentication", icon: "shield" },
      { id: "settings", label: "Settings", icon: "gear" },
    ]},
  ];

  const moduleSidebar = () => {
    const approvedRoutes = new Set(shellSidebar.flatMap(group => group.items.map(item => item.id)));
    const serverModules = Array.isArray(server.modules) ? server.modules.flatMap(group => group.items || []) : [];
    const visibleServerRoutes = new Set(serverModules.map(item => item.route || item.slug).filter(Boolean));

    return shellSidebar
      .map(group => ({
        group: group.group,
        items: group.items.filter(item => visibleServerRoutes.size === 0 || visibleServerRoutes.has(item.id) || item.id === "dashboard"),
      }))
      .filter(group => group.items.length)
      .map(group => ({
        group: group.group,
        items: group.items.filter(item => approvedRoutes.has(item.id)),
      }));
  };

  const initials = name => String(name || "BU")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part[0])
    .join("")
    .toUpperCase() || "BU";

  const roleColor = index => ["#F6740C", "#15a657", "#e08600", "#2570eb", "#0ea5a4", "#7c3aed"][index % 6];

  const roleRows = () => {
    const rows = Array.isArray(server.roles) ? server.roles : [];
    if (!rows.length) {
      const name = user?.name || "Authenticated User";
      return [{
        id: user?.role || "authenticated_user",
        name: user?.role || "Current Role",
        person: name,
        title: user?.company ? `Company scope · ${user.company}` : "Laravel authenticated user",
        initials: initials(name),
        color: "#F6740C",
        scopeLevel: "current",
        permissions: [],
      }];
    }

    return rows.map((role, index) => {
      const isCurrent = user?.role && user.role === role.slug;
      const person = isCurrent ? (user?.name || role.name) : role.name;
      return {
        id: role.slug,
        name: role.name,
        person,
        title: role.scope_level ? `${role.scope_level} scope` : "Laravel role",
        initials: initials(person),
        color: roleColor(index),
        scopeLevel: role.scope_level,
        permissions: role.permissions || [],
      };
    });
  };

  const projectRows = () => {
    const dashboardProjects = Array.isArray(dashboard?.projects) ? dashboard.projects : [];
    const projectBootstrapRows = Array.isArray(server.projects) ? server.projects : [];
    const rows = dashboardProjects.length ? dashboardProjects : projectBootstrapRows;
    return rows.map((project, index) => ({
      id: project.id || project.code || `project-${index}`,
      db_id: project.db_id || project.id || null,
      name: project.name || project.code || "Project",
      code: project.code || project.name || "PROJECT",
      city: project.city || project.state || "",
      status: project.status || "not_loaded",
      color: project.color || roleColor(index),
      units: Number(project.units || project.total_units || 0),
      sold: Number(project.sold || project.sold_units || 0),
      progress: Number(project.progress || project.progress_percent || 0),
      budget: Number(project.budget || project.budget_amount || 0),
      spent: Number(project.spent || project.actual_cost || 0),
      revenue: Number(project.revenue || project.booked_revenue || 0),
      roi: Number(project.roi || project.target_roi_percent || 0),
      health: Number(project.health || project.health_score || 0),
      source: "laravel",
    }));
  };

  const kpiRows = () => ({
    projects: Number(dashboard?.kpis?.projects || server.projects?.length || 0),
    activeSites: Number(dashboard?.kpis?.activeSites || 0),
    totalUnits: Number(dashboard?.kpis?.totalUnits || 0),
    available: Number(dashboard?.kpis?.available || 0),
    hold: Number(dashboard?.kpis?.hold || 0),
    booked: Number(dashboard?.kpis?.booked || 0),
    sold: Number(dashboard?.kpis?.sold || 0),
    soldOnly: Number(dashboard?.kpis?.soldOnly || 0),
    leads: Number(dashboard?.kpis?.leads || server.crm_lead_metrics?.total || 0),
    verified: Number(dashboard?.kpis?.verified || server.crm_lead_metrics?.verified || 0),
    siteVisits: Number(dashboard?.kpis?.siteVisits || 0),
    bookings: Number(dashboard?.kpis?.bookings || 0),
    collection: Number(dashboard?.kpis?.collection || 0),
    outstanding: Number(dashboard?.kpis?.outstanding || 0),
    expenses: Number(dashboard?.kpis?.expenses || 0),
    budgetVar: Number(dashboard?.kpis?.budgetVar || 0),
    roi: Number(dashboard?.kpis?.roi || 0),
    pendingApprovals: Number(dashboard?.kpis?.pendingApprovals || server.approval_inbox_options?.summary?.pending || 0),
  });

  const buildDb = () => ({
    INR,
    cr,
    lac,
    projects: projectRows(),
    kpis: kpiRows(),
    sidebar: moduleSidebar(),
    roles: roleRows(),
    leads: Array.isArray(server.crm_leads) ? server.crm_leads : [],
    bookings: [],
    funnel: Array.isArray(dashboard?.funnel) ? dashboard.funnel : [],
    costHeads: [],
    approvals: Array.isArray(dashboard?.approvals) ? dashboard.approvals : [],
    alerts: Array.isArray(dashboard?.alerts) ? dashboard.alerts : [],
    dashboardSource: dashboard?.source || "laravel-required",
    dashboardGeneratedAt: dashboard?.generated_at || null,
    activity: [],
    schedule: [],
    materials: [],
    leadStatus: [],
    sources: [],
  });

  window.__MODULES__ = window.__MODULES__ || {};
  window.Builder360ApplyBootstrap = payload => {
    window.Builder360Server = payload || {};
    server = window.Builder360Server || {};
    user = server.user || null;
    dashboard = server.dashboard || null;
    const nextDb = buildDb();
    if (window.DB) Object.assign(window.DB, nextDb);
    else window.DB = nextDb;
    window.dispatchEvent(new CustomEvent("builder360:bootstrap-applied", { detail: { payload: server } }));
    return window.DB;
  };

  window.Builder360ApplyBootstrap(server);
})();
