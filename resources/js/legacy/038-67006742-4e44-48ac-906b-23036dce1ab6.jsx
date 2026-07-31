const React = window.React;
const ReactDOM = window.ReactDOM;

/* Builder360 — App shell: sidebar, topbar, router, theme + role state */
(function () {
  const { Icon, Avatar, Badge } = window;
  const e = React.createElement;
  const DB = window.DB;

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const apiJson = async (url, options = {}) => {
    const response = await fetch(url, {
      credentials: "same-origin",
      ...options,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrf(),
        "X-Requested-With": "XMLHttpRequest",
        ...(options.headers || {}),
      },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const errors = payload.errors || {};
      const first = Object.values(errors).flat().filter(Boolean)[0];
      throw new Error(first || payload.message || "Role context switch failed.");
    }
    return payload;
  };
  const postLaravelLogout = () => {
    const logoutRoute = window.Builder360Server?.auth_security_options?.logout_route || "/logout";
    const form = document.createElement("form");
    form.method = "POST";
    form.action = logoutRoute;
    form.style.display = "none";
    const tokenInput = document.createElement("input");
    tokenInput.type = "hidden";
    tokenInput.name = "_token";
    tokenInput.value = csrf();
    form.appendChild(tokenInput);
    document.body.appendChild(form);
    form.submit();
  };

  // ---- Toasts ----
  function useToasts() {
    const [toasts, setToasts] = React.useState([]);
    const push = (msg, tone = "accent") => {
      const id = Math.random();
      setToasts(t => [...t, { id, msg, tone }]);
      setTimeout(() => setToasts(t => t.filter(x => x.id !== id)), 2800);
    };
    return [toasts, push];
  }
  function ToastHost({ toasts }) {
    const map = { green: ["check", "var(--green)", "var(--green-soft)"], red: ["x", "var(--red)", "var(--red-soft)"], orange: ["alert", "var(--orange)", "var(--orange-soft)"], accent: ["spark", "var(--accent)", "var(--accent-soft)"] };
    return e("div", { className: "toast-wrap" }, toasts.map(t => {
      const [ic, c, bg] = map[t.tone] || map.accent;
      return e("div", { className: "toast", key: t.id },
        e("div", { className: "toast-ic", style: { background: bg, color: c } }, e(Icon, { name: ic, size: 16 })),
        e("span", { style: { fontWeight: 600, fontSize: 13 } }, t.msg));
    }));
  }

  function roleNavigationGroups(role) {
    return role.id === "buyer"
      ? [{ group: "My Home", items: [{ id: "dashboard", label: "Dashboard", icon: "home" }, { id: "buyer", label: "My Booking", icon: "tag" }, { id: "complaints", label: "Service Requests", icon: "headset" }] }]
      : role.id === "employee"
        ? [{ group: "My Work", items: [{ id: "dashboard", label: "Dashboard", icon: "grid" }, { id: "ess", label: "Employee Self-Service", icon: "id" }, { id: "calendar", label: "My Calendar", icon: "calendar" }, { id: "tasks", label: "My Tasks", icon: "tasks" }] }]
        : (role.id === "channel_partner" || role.id === "executive_partner_broker")
          ? [{ group: "Partner Portal", items: [{ id: "dashboard", label: role.id === "executive_partner_broker" ? "Broker Dashboard" : "Partner Dashboard", icon: "grid" }, { id: "leads", label: "Lead Management", icon: "users" }, { id: "qualification", label: "Lead Qualification", icon: "filter" }, { id: "sitevisits", label: "Site Visits", icon: "calendar" }, { id: "sales", label: "Sales & Booking", icon: "tag" }, { id: "collections", label: "Customer Collections", icon: "rupee" }, { id: "funnel", label: "Lead Funnel Analytics", icon: "funnel" }, { id: "performance", label: "Performance Analytics", icon: "star" }, { id: "documents", label: "Document Mgmt", icon: "folder" }] }]
          : DB.sidebar;
  }

  function roleRouteIds(role) {
    return new Set(roleNavigationGroups(role).flatMap(group => group.items.map(item => item.id)));
  }

  function isRouteAllowedForRole(route, role) {
    if (route === "dashboard" || route === "notifications" || route === "profile") return true;
    const allowedRoutes = roleRouteIds(role);
    if (route === "buyer") return role.id === "buyer" && allowedRoutes.has(route);
    if (route === "partner") return (role.id === "channel_partner" || role.id === "executive_partner_broker") && allowedRoutes.has(route);
    if (route === "ess") return role.id === "employee" || allowedRoutes.has(route);
    if (route === "approvals") return allowedRoutes.has(route) && window.Builder360Server?.approval_inbox_options !== null;
    return allowedRoutes.has(route);
  }

  // ---- Sidebar ----
  function Sidebar({ active, onNav, collapsed, role, mobileOpen }) {
    const [profileOpen, setProfileOpen] = React.useState(false);
    const [loggingOut, setLoggingOut] = React.useState(false);
    const groups = roleNavigationGroups(role);
    const profileRoute = "profile";
    const profileLabel = "Account and access";
    const closeAndNavigate = route => {
      setProfileOpen(false);
      onNav(route);
    };
    const submitLogout = () => {
      if (loggingOut) return;
      setLoggingOut(true);
      postLaravelLogout();
    };
    return e("aside", { className: "sidebar" + (collapsed ? " collapsed" : "") + (mobileOpen ? " mobile-open" : ""), "aria-label": "Primary navigation" },
      e("div", { className: "sb-brand" },
        e("div", { className: "sb-logo" }, e(Icon, { name: "building", size: 21 })),
        e("div", null, e("div", { className: "sb-brand-name" }, "Builder360"), e("div", { className: "sb-brand-sub" }, "ERP · CRM"))),
      !collapsed && e("div", { className: "sb-search", onClick: () => onNav("__search") }, e(Icon, { name: "search", size: 15 }), e("span", { style: { flex: 1 } }, "Search…"), e("kbd", null, "⌘K")),
      e("nav", { className: "sb-nav" }, groups.map((g, gi) =>
        e("div", { key: gi },
          e("div", { className: "sb-group-label" }, g.group),
          g.items.map(it => e("div", { key: it.id, className: "nav-item" + (active === it.id ? " active" : ""), onClick: () => onNav(it.id), title: it.label,
            role: "button", tabIndex: 0, "aria-current": active === it.id ? "page" : undefined, "aria-label": it.label + (it.badge ? " (" + it.badge + " new)" : ""),
            onKeyDown: ev => { if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); onNav(it.id); } } },
            e("span", { className: "ni-ic" }, e(Icon, { name: it.icon, size: 18 })),
            e("span", { className: "nav-label", style: { flex: 1 } }, it.label),
            it.badge && e("span", { className: "nav-badge" }, it.badge)))))),
      e("div", { className: "sb-foot", style: { position: "relative" } },
        profileOpen && e("div", null,
          e("div", { style: { position: "fixed", inset: 0, zIndex: 40 }, onClick: () => setProfileOpen(false) }),
          e("div", { className: "card", style: { position: "absolute", left: collapsed ? 0 : 14, bottom: 58, width: collapsed ? 250 : "calc(100% - 28px)", minWidth: 230, zIndex: 41, boxShadow: "var(--shadow-lg)", padding: 6 } },
            e("div", { style: { display: "flex", alignItems: "center", gap: 10, padding: "10px 10px 12px", borderBottom: "1px solid var(--border)", marginBottom: 6 } },
              e(Avatar, { name: role.person, size: 32, color: role.color }),
              e("div", { style: { flex: 1, minWidth: 0 } },
                e("div", { style: { fontWeight: 800, fontSize: 13, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" } }, role.person),
                e("div", { className: "cell-sub" }, role.name, " · ", role.title))),
            e("div", { className: "nav-item", role: "button", tabIndex: 0, "aria-label": "Open my dashboard", style: { minHeight: 42 }, onClick: () => closeAndNavigate("dashboard"), onKeyDown: ev => { if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); closeAndNavigate("dashboard"); } } },
              e("span", { className: "ni-ic" }, e(Icon, { name: "grid", size: 17 })),
              e("div", { style: { flex: 1 } }, e("div", { style: { fontWeight: 800, fontSize: 12.5 } }, "My Dashboard"), e("div", { className: "cell-sub" }, "Open role dashboard"))),
            e("div", { className: "nav-item", role: "button", tabIndex: 0, "aria-label": "Open my profile", style: { minHeight: 42 }, onClick: () => closeAndNavigate(profileRoute), onKeyDown: ev => { if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); closeAndNavigate(profileRoute); } } },
              e("span", { className: "ni-ic" }, e(Icon, { name: "id", size: 17 })),
              e("div", { style: { flex: 1 } }, e("div", { style: { fontWeight: 800, fontSize: 12.5 } }, "My Profile"), e("div", { className: "cell-sub" }, profileLabel))),
            e("div", { style: { height: 1, background: "var(--border)", margin: "6px 8px" }, "aria-hidden": true }),
            e("div", { className: "nav-item", role: "button", tabIndex: loggingOut ? -1 : 0, "aria-label": "Logout current Builder360 session", style: { minHeight: 42, cursor: loggingOut ? "wait" : "pointer" }, onClick: submitLogout, onKeyDown: ev => { if (!loggingOut && (ev.key === "Enter" || ev.key === " ")) { ev.preventDefault(); submitLogout(); } } },
              e("span", { className: "ni-ic", style: { color: "var(--red)" } }, e(Icon, { name: loggingOut ? "refresh" : "x", size: 17 })),
              e("div", { style: { flex: 1 } }, e("div", { style: { fontWeight: 800, fontSize: 12.5 } }, loggingOut ? "Logging out…" : "Logout"), e("div", { className: "cell-sub" }, "End current Laravel session"))))),
        e("div", { className: "nav-item", role: "button", tabIndex: 0, "aria-label": "Open profile menu", "aria-expanded": profileOpen, style: { background: "var(--surface-3)", cursor: "pointer" }, onClick: () => setProfileOpen(open => !open), onKeyDown: ev => { if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); setProfileOpen(open => !open); } } },
          e(Avatar, { name: role.person, size: 30, color: role.color }),
          !collapsed && e("div", { style: { flex: 1, minWidth: 0 } }, e("div", { style: { fontWeight: 700, fontSize: 12.5, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" } }, role.person), e("div", { className: "cell-sub" }, role.title)),
          !collapsed && e(Icon, { name: "chevD", size: 14, style: { color: "var(--text-3)", transform: profileOpen ? "rotate(180deg)" : "none", transition: ".15s" } }))));
  }

  // ---- Topbar ----
  function Topbar({ theme, setTheme, role, projectContext, onRoleSwitch, onProjectSwitch, onToggleSidebar, onOpenSearch, onBell, toast }) {
    const [roleOpen, setRoleOpen] = React.useState(false);
    const [scopeOpen, setScopeOpen] = React.useState(false);
    const [switchingRole, setSwitchingRole] = React.useState(null);
    const [switchingProject, setSwitchingProject] = React.useState(false);
    const [projectSearch, setProjectSearch] = React.useState("");
    const notificationPayload = window.Builder360Server && window.Builder360Server.notifications;
    const [notificationSummary, setNotificationSummary] = React.useState(notificationPayload || null);
    const unreadNotifications = notificationSummary && notificationSummary.counts ? notificationSummary.counts.unread : 0;
    const activeProjectContext = projectContext || window.Builder360Server?.active_project_context || {};
    const serverProjects = window.Builder360Server?.projects || window.Builder360Server?.dashboard?.projects || [];
    const scopedProjects = Array.isArray(serverProjects)
      ? serverProjects.map(project => ({ id: project.id, code: project.code, name: project.name || project.label, status: project.status, city: project.city || project.location }))
      : [];
    const selectedProjectId = activeProjectContext.project_id ? String(activeProjectContext.project_id) : "all";
    const projectPillLabel = activeProjectContext.mode === "selected_project"
      ? (activeProjectContext.project_code || activeProjectContext.project_name || "Selected Project")
      : "All Projects";
    const filteredProjects = scopedProjects.filter(project => {
      const haystack = [project.code, project.name, project.city, project.status].filter(Boolean).join(" ").toLowerCase();
      return haystack.includes(projectSearch.trim().toLowerCase());
    });
    React.useEffect(() => {
      if (!roleOpen && !scopeOpen) return;
      const onKey = ev => {
        if (ev.key === "Escape") {
          setRoleOpen(false);
          setScopeOpen(false);
        }
      };
      window.addEventListener("keydown", onKey);
      return () => window.removeEventListener("keydown", onKey);
    }, [roleOpen, scopeOpen]);
    React.useEffect(() => {
      setNotificationSummary(window.Builder360Server?.notifications || null);
    }, [role.id]);
    React.useEffect(() => {
      const onSummary = ev => setNotificationSummary(ev.detail || window.Builder360Server?.notifications || null);
      window.addEventListener("builder360:notifications-summary", onSummary);
      return () => window.removeEventListener("builder360:notifications-summary", onSummary);
    }, []);
    const chooseProject = async projectId => {
      if (switchingProject) return;
      setSwitchingProject(true);
      try {
        await onProjectSwitch(projectId);
        setScopeOpen(false);
        setProjectSearch("");
      } finally {
        setSwitchingProject(false);
      }
    };
    const roleMenu = roleOpen && e("section", { id: "top-role-menu", className: "top-role-strip", role: "menu", "aria-label": "View as role" },
      e("div", { className: "top-role-strip-head" },
        e("div", { style: { minWidth: 0 } },
          e("div", { className: "sb-group-label", style: { padding: 0 } }, "View as role"),
          e("div", { className: "cell-sub" }, "Choose the role dashboard you want to review.")),
        e("button", { type: "button", className: "btn btn-ghost btn-sm", onClick: () => setRoleOpen(false), "aria-label": "Close role selector" }, e(Icon, { name: "x", size: 14 }), "Close")),
      e("div", { className: "top-role-strip-list" },
        DB.roles.map(r => e("button", { key: r.id, type: "button", role: "menuitem", className: "top-role-chip" + (role.id === r.id ? " active" : ""), disabled: !!switchingRole && switchingRole !== r.id, onClick: async () => {
          if (switchingRole) return;
          setSwitchingRole(r.id);
          try {
            await onRoleSwitch(r);
            setRoleOpen(false);
          } finally {
            setSwitchingRole(null);
          }
        } },
          e(Avatar, { name: r.person, size: 26, color: r.color }),
          e("span", { className: "top-role-chip-text" }, e("b", null, r.name), e("small", null, r.person)),
          switchingRole === r.id ? e(Icon, { name: "refresh", size: 15 }) : role.id === r.id && e(Icon, { name: "check", size: 15 })))));
    return e(React.Fragment, null,
      e("header", { className: "topbar" },
      e("button", { className: "icon-btn", onClick: onToggleSidebar, "aria-label": "Toggle navigation menu" }, e(Icon, { name: "menu", size: 18 })),
      e("div", { className: "topbar-search", onClick: onOpenSearch, role: "button", tabIndex: 0, "aria-label": "Search (Command or Control K)", onKeyDown: ev => { if (ev.key === "Enter") onOpenSearch(); } },
        e(Icon, { name: "search", size: 16 }), e("input", { placeholder: "Search projects, units, leads, vouchers…", onFocus: onOpenSearch, readOnly: true }), e("kbd", null, "⌘K")),
      e("div", { style: { flex: 1 } }),
      // project scope selector
      e("div", { style: { position: "relative" } },
        e("button", { className: "proj-pill", "aria-label": "Project scope: " + projectPillLabel, "aria-haspopup": "menu", "aria-controls": "top-project-menu", "aria-expanded": scopeOpen, onClick: () => setScopeOpen(open => !open) }, e("span", { className: "pp-dot", style: { background: activeProjectContext.mode === "selected_project" ? "var(--green)" : "var(--accent)" } }), projectPillLabel, e(Icon, { name: "chevD", size: 15, style: { color: "var(--text-3)", transform: scopeOpen ? "rotate(180deg)" : "none", transition: ".15s" } })),
        scopeOpen && e("div", null,
          e("div", { style: { position: "fixed", inset: 0, zIndex: 40 }, onClick: () => setScopeOpen(false) }),
          e("div", { id: "top-project-menu", className: "card top-project-menu", role: "menu", style: { position: "absolute", right: 0, top: 42, width: 320, maxWidth: "calc(100vw - 24px)", zIndex: 41, boxShadow: "var(--shadow-lg)", padding: 8 } },
            e("div", { className: "sb-group-label", style: { padding: "8px 10px 6px" } }, "Authorized project scope"),
            e("div", { className: "cell-sub", style: { padding: "0 10px 8px", lineHeight: 1.35 } }, "Select a global project scope for dashboard summaries and project-aware workspaces. All Projects resets the scope."),
            e("div", { style: { padding: "0 6px 8px" } },
              e("input", { value: projectSearch, onChange: ev => setProjectSearch(ev.target.value), placeholder: "Search projects…", disabled: switchingProject, style: { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "9px 10px", background: "var(--surface)", color: "var(--text)", font: "inherit" }, "aria-label": "Search authorized projects" })),
            e("div", { className: "nav-item" + (selectedProjectId === "all" ? " active" : ""), style: { minHeight: 42, cursor: switchingProject ? "wait" : "pointer" }, onClick: () => chooseProject("all") },
              e("span", { className: "ni-ic" }, e(Icon, { name: "grid", size: 17 })),
              e("div", { style: { flex: 1, minWidth: 0 } }, e("div", { style: { fontWeight: 800, fontSize: 12.5 } }, "All Projects"), e("div", { className: "cell-sub" }, "Show every authorized project")),
              selectedProjectId === "all" && e(Icon, { name: "check", size: 15 })),
            filteredProjects.length === 0 && e("div", { className: "cell-sub", style: { padding: "14px 10px" } }, scopedProjects.length ? "No projects match your search." : "No authorized projects are available for this role."),
            filteredProjects.slice(0, 10).map(project => e("div", { key: project.id || project.code || project.name, className: "nav-item" + (String(project.id) === selectedProjectId ? " active" : ""), style: { minHeight: 42, cursor: switchingProject ? "wait" : "pointer" }, onClick: () => chooseProject(project.id) },
              e("span", { className: "ni-ic" }, e(Icon, { name: "building", size: 17 })),
              e("div", { style: { flex: 1, minWidth: 0 } }, e("div", { style: { fontWeight: 800, fontSize: 12.5, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" } }, project.name || project.label || project.code || "Project"), e("div", { className: "cell-sub" }, [project.code, project.city || project.location, project.status].filter(Boolean).join(" · ") || "Scoped project")),
              String(project.id) === selectedProjectId ? e(Icon, { name: "check", size: 15 }) : e(Badge, { tone: "b-blue" }, "Select"))),
            filteredProjects.length > 10 && e("div", { className: "cell-sub", style: { padding: "8px 10px 4px" } }, "+" + (filteredProjects.length - 10) + " more project(s). Use search to narrow.")))),
      // role switcher
      e("div", { style: { position: "relative" } },
        e("button", { className: "user-chip", "aria-haspopup": "menu", "aria-controls": "top-role-menu", "aria-expanded": roleOpen, onClick: () => setRoleOpen(o => !o) },
          e(Avatar, { name: role.person, size: 34, color: role.color }),
          e("div", { style: { textAlign: "left", lineHeight: 1.15 } }, e("div", { style: { fontWeight: 700, fontSize: 12.5 } }, role.name), e("div", { className: "cell-sub" }, "Switch role")),
          e(Icon, { name: "chevD", size: 14, style: { color: "var(--text-3)", transform: roleOpen ? "rotate(180deg)" : "none", transition: ".15s" } }))),
      e("button", { className: "icon-btn", onClick: () => setTheme(theme === "dark" ? "light" : "dark"), title: "Toggle theme", "aria-label": "Switch to " + (theme === "dark" ? "light" : "dark") + " theme" }, e(Icon, { name: theme === "dark" ? "sun" : "moon", size: 17 })),
      e("button", { className: "icon-btn", style: { position: "relative" }, onClick: onBell, "aria-label": unreadNotifications > 0 ? "Notifications (" + unreadNotifications + " unread)" : "Notifications" },
        e(Icon, { name: "bell", size: 17 }),
        unreadNotifications > 0 ? e("span", { className: "nav-badge", style: { position: "absolute", top: -4, right: -4, minWidth: 18, height: 18, display: "grid", placeItems: "center", padding: "0 4px" } }, unreadNotifications > 99 ? "99+" : unreadNotifications) : e("span", { className: "dot" }))),
      roleMenu
    );
  }

  // ---- Command palette ----
  function CommandPalette({ onNav, onClose }) {
    const [q, setQ] = React.useState("");
    const all = DB.sidebar.flatMap(g => g.items.map(it => ({ ...it, group: g.group })));
    const filtered = all.filter(it => it.label.toLowerCase().includes(q.toLowerCase()));
    return e("div", { className: "scrim", style: { alignItems: "flex-start", paddingTop: "12vh" }, onClick: onClose },
      e("div", { className: "modal", style: { width: 560 }, onClick: ev => ev.stopPropagation() },
        e("div", { style: { display: "flex", alignItems: "center", gap: 10, padding: "16px 18px", borderBottom: "1px solid var(--border)" } },
          e(Icon, { name: "search", size: 18, style: { color: "var(--text-3)" } }),
          e("input", { autoFocus: true, value: q, onChange: ev => setQ(ev.target.value), placeholder: "Jump to module…", style: { border: "none", outline: "none", background: "none", flex: 1, fontSize: 15, color: "var(--text)", fontFamily: "inherit" } }),
          e("kbd", { style: { fontFamily: "var(--font-ui)", fontSize: 11, fontWeight: 700, background: "var(--surface-3)", borderRadius: 6, padding: "3px 7px", color: "var(--text-3)" } }, "ESC")),
        e("div", { style: { maxHeight: 360, overflowY: "auto", padding: 8 } },
          filtered.length === 0 ? e("div", { style: { padding: 30, textAlign: "center", color: "var(--text-3)" } }, "No results")
          : filtered.map(it => e("div", { key: it.id, className: "nav-item", style: { height: 42 }, onClick: () => { onNav(it.id); onClose(); } },
            e("span", { className: "ni-ic" }, e(Icon, { name: it.icon, size: 18 })),
            e("span", { style: { flex: 1, fontWeight: 600 } }, it.label),
            e("span", { className: "cell-sub" }, it.group))))));
  }

  function UnknownModule({ route }) {
    return e("div", { className: "page page-wide" },
      e("div", { className: "page-head" },
        e("div", null,
          e("div", { className: "crumbs" }, "Builder360 / Navigation"),
          e("h1", { className: "page-title" }, "Module not available in approved UI"),
          e("div", { className: "page-sub" }, "This navigation target is not part of the approved compact Builder360 sidebar."))),
      e("div", { className: "hrx-warning", style: { marginBottom: 16 } },
        e(Icon, { name: "alert", size: 17 }),
        e("div", null,
          e("b", null, "Unmapped route blocked"),
          e("span", null, "Route #", route || "unknown", " is intentionally not redirected to Dashboard. Use the parent module tabs or Admin & Masters actions instead.")),
        e(Badge, { tone: "b-orange" }, "APPROVED UI ONLY")),
      e("div", { className: "card card-pad" },
        e("div", { style: { fontWeight: 800, marginBottom: 6 } }, "What to do next"),
        e("div", { className: "cell-sub" }, "If this route is needed, add a dedicated approved UI component and tests before exposing it in the main sidebar.")));
  }

  function RestrictedModule({ route }) {
    return e("div", { className: "page page-wide" },
      e("div", { className: "page-head" },
        e("div", null,
          e("div", { className: "crumbs" }, "Builder360 / Navigation"),
          e("h1", { className: "page-title" }, "Module not available for this role"),
          e("div", { className: "page-sub" }, "This route is not part of the approved navigation for the selected role. Use the available sidebar options."))),
      e("div", { className: "hrx-warning", style: { marginBottom: 16 } },
        e(Icon, { name: "shield", size: 17 }),
        e("div", null,
          e("b", null, "Role restricted route blocked"),
          e("span", null, "Route #", route || "unknown", " is hidden for the selected role and cannot be opened by direct hash navigation.")),
        e(Badge, { tone: "b-orange" }, "ROLE RESTRICTED")),
      e("div", { className: "card card-pad" },
        e("div", { style: { fontWeight: 800, marginBottom: 6 } }, "What to do next"),
        e("div", { className: "cell-sub" }, "Switch to an authorized internal role or use the modules shown in the current sidebar.")));
  }

  // ---- Router ----
  function renderScreen(route, role, toast) {
    const W = window;
    const direct = {
      dashboard: W.Dashboard, approvals: W.Approvals, funnel: W.FunnelAnalytics, reports: W.Reports,
      leads: W.Leads, qualification: W.Qualification, sales: W.Sales, collections: W.Collections, marketing: W.Marketing,
      projects: W.Projects, inventory: W.UnitInventory, cost: W.CostROI, pricing: W.Pricing,
      planning: W.Planning, progress: W.DailyProgress, materials: W.Materials, procurement: W.Procurement,
      hr: W.HR, finance: W.Finance, possession: W.Possession,
      notifications: W.Notifications, workflows: W.Workflows, sitevisits: W.SiteVisits, audit: W.Audit,
      complaints: W.Complaints, legal: W.Legal, documents: W.Documents, boq: W.BOQ,
      vendors: W.Vendors, contractors: W.Contractors, admin: W.Admin, settings: W.Settings,
      buyer: W.BuyerPortal, inquiry: W.Inquiry, mobile: W.MobileApps, auth: W.Auth, profile: W.Profile,
      performance: W.Performance, maintenance: W.Maintenance, payroll: W.HR, recruitment: W.HR,
      chat: W.ChatConnect, mailbox: W.Mailbox, tasks: W.TaskManagement, calendar: W.CalendarManagement, ess: W.EmployeeSelfService, partner: W.PartnerPortal,
    };
    if (direct[route]) {
      if (!isRouteAllowedForRole(route, role)) return e(RestrictedModule, { route });
      return e(direct[route], { key: route, role, toast });
    }
    const cfg = W.MODULE_CFG[route];
    if (cfg) return e(W.ModuleScreen, { cfg, toast });
    return e(UnknownModule, { route });
  }

  // ---- App ----
  function App() {
    const currentRoleFromServer = () => {
      const roles = window.DB?.roles || [];
      const currentSlug = window.Builder360Server?.active_role_context?.role_slug || window.Builder360Server?.user?.role;
      return roles.find(item => item.id === currentSlug) || roles[0] || { id: "dashboard", name: "Dashboard", person: "Builder360 User", title: "Current role", initials: "BU", color: "#F6740C", permissions: [] };
    };
    const routeFromHash = () => {
      const hash = (location.hash || "").replace(/^#/, "");
      if (!hash) return "dashboard";
      const routeOnly = hash.split("?")[0];
      if (routeOnly === "ess") return "ess";
      if (routeOnly === "hr") return "hr";
      if (routeOnly.startsWith("hr/")) {
        const hrRoute = routeOnly.split("/")[1] || "dashboard";
        return ["payroll", "recruitment"].includes(hrRoute) ? hrRoute : "hr";
      }
      return /^[a-z][a-z0-9_-]*$/.test(routeOnly) ? routeOnly : "dashboard";
    };
    const writeRouteHash = id => {
      if (!id || typeof id !== "string") return;
      if (id === "dashboard") {
        history.replaceState(null, "", location.pathname + location.search);
        return;
      }
      location.hash = id === "hr" ? "#hr/dashboard" : ["payroll", "recruitment"].includes(id) ? "#hr/" + id : "#" + id;
    };
    const [theme, setTheme] = React.useState(() => localStorage.getItem("b360-theme") || "light");
    const [role, setRole] = React.useState(currentRoleFromServer);
    const [route, setRoute] = React.useState(routeFromHash);
    const [collapsed, setCollapsed] = React.useState(false);
    const [mobileOpen, setMobileOpen] = React.useState(false);
    const [search, setSearch] = React.useState(false);
    const [projectContext, setProjectContext] = React.useState(() => window.Builder360Server?.active_project_context || {});
    const [toasts, pushToast] = useToasts();
    const contentRef = React.useRef(null);
    const previousRouteRef = React.useRef(route);

    React.useEffect(() => { document.documentElement.setAttribute("data-theme", theme); localStorage.setItem("b360-theme", theme); }, [theme]);
    React.useEffect(() => {
      const syncRouteFromHash = () => setRoute(routeFromHash());
      window.addEventListener("hashchange", syncRouteFromHash);
      syncRouteFromHash();
      return () => window.removeEventListener("hashchange", syncRouteFromHash);
    }, []);
    React.useEffect(() => {
      const h = ev => { if ((ev.metaKey || ev.ctrlKey) && ev.key === "k") { ev.preventDefault(); setSearch(true); } if (ev.key === "Escape") setSearch(false); };
      window.addEventListener("keydown", h); return () => window.removeEventListener("keydown", h);
    }, []);
    React.useEffect(() => { if (contentRef.current) contentRef.current.scrollTop = 0; }, [route]);
    React.useEffect(() => {
      const previousRoute = previousRouteRef.current;
      if (route === "mailbox" && previousRoute !== "mailbox") {
        if (window.matchMedia("(max-width: 940px)").matches) setMobileOpen(false);
        else setCollapsed(true);
      }
      previousRouteRef.current = route;
    }, [route]);
    React.useEffect(() => {
      const navigate = id => {
        if (!id || typeof id !== "string") return;
        setRoute(id);
        writeRouteHash(id);
      };
      window.Builder360Navigate = navigate;
      const handler = ev => navigate(ev.detail && ev.detail.route);
      window.addEventListener("builder360:navigate", handler);
      return () => {
        window.removeEventListener("builder360:navigate", handler);
      };
    }, []);

    const switchRoleContext = async nextRole => {
      try {
        const payload = await apiJson("/builder360/role-context", {
          method: "POST",
          body: JSON.stringify({ role_slug: nextRole.id }),
        });
        if (typeof window.Builder360ApplyBootstrap === "function") {
          window.Builder360ApplyBootstrap(payload);
        } else {
          window.Builder360Server = payload;
        }
        const selected = currentRoleFromServer();
        setRole(selected);
        setProjectContext(payload.active_project_context || {});
        setRoute("dashboard");
        writeRouteHash("dashboard");
        pushToast("Dashboard switched to " + (selected.name || nextRole.name || "selected role") + ".", "green");
      } catch (error) {
        pushToast(error.message || "Role context switch failed.", "red");
        throw error;
      }
    };

    const switchProjectContext = async projectId => {
      try {
        const payload = await apiJson("/builder360/project-context", {
          method: "POST",
          body: JSON.stringify({ project_id: projectId === "all" ? null : projectId }),
        });
        if (typeof window.Builder360ApplyBootstrap === "function") {
          window.Builder360ApplyBootstrap(payload);
        } else {
          window.Builder360Server = payload;
        }
        setProjectContext(payload.active_project_context || {});
        setRole(currentRoleFromServer());
        const context = payload.active_project_context || {};
        pushToast(context.mode === "selected_project" ? "Project scope set to " + (context.project_code || context.project_name || "selected project") + "." : "Project scope reset to All Projects.", "green");
      } catch (error) {
        pushToast(error.message || "Project scope switch failed.", "red");
        throw error;
      }
    };

    const onNav = id => { if (id === "__search") setSearch(true); else { setRoute(id); writeRouteHash(id); } setMobileOpen(false); };

    const toggleSidebar = () => {
      if (window.matchMedia("(max-width: 940px)").matches) setMobileOpen(o => !o);
      else setCollapsed(c => !c);
    };

    return e("div", { className: "app" },
      e(Sidebar, { active: route, onNav, collapsed, role, mobileOpen }),
      mobileOpen && e("div", { className: "sb-scrim", onClick: () => setMobileOpen(false), "aria-hidden": "true" }),
      e("div", { className: "main" },
        e(Topbar, { theme, setTheme, role, projectContext, onRoleSwitch: switchRoleContext, onProjectSwitch: switchProjectContext, onToggleSidebar: toggleSidebar, onOpenSearch: () => setSearch(true), onBell: () => onNav("notifications"), toast: pushToast }),
        e("div", { className: "content", ref: contentRef }, renderScreen(route, role, pushToast))),
      e(ToastHost, { toasts }),
      search && e(CommandPalette, { onNav, onClose: () => setSearch(false) }),
    );
  }

  window.__BOOT__ = () => ReactDOM.createRoot(document.getElementById("root")).render(e(App));
})();
