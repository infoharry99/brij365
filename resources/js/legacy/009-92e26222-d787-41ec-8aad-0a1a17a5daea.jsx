const React = window.React;

/* Builder360 — Laravel-backed authentication and access-control status */
(function () {
  const { Button, Badge, Card, PageHead } = window;
  const e = React.createElement;

  const options = () => window.Builder360Server?.auth_security_options || null;
  const profileOptions = () => window.Builder360Server?.account_profile_options || null;
  const rows = value => Array.isArray(value) ? value : [];
  const titleCase = value => String(value || "")
    .replace(/^auth\./, "")
    .replace(/[._-]+/g, " ")
    .replace(/\b\w/g, char => char.toUpperCase());
  const routeLabel = route => route || "Not available";
  const safeText = value => value === null || value === undefined || value === "" ? "—" : String(value);

  function StatusBadge({ status }) {
    const tone = status === "enabled" ? "b-green" : status === "not_implemented" ? "b-orange" : "b-slate";
    return e(Badge, { tone }, String(status || "unknown").replaceAll("_", " "));
  }

  function RouteAction({ route, children, icon }) {
    return e(Button, {
      sm: true,
      icon,
      disabled: !route,
      onClick: () => { if (route) window.location.assign(route); },
      children: route ? children : children + " unavailable",
    });
  }

  function submitLaravelLogout() {
    const logoutRoute = window.Builder360Server?.account_profile_options?.security?.logout_route
      || window.Builder360Server?.auth_security_options?.logout_route
      || "/logout";
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    const form = document.createElement("form");
    form.method = "POST";
    form.action = logoutRoute;
    form.style.display = "none";
    const tokenInput = document.createElement("input");
    tokenInput.type = "hidden";
    tokenInput.name = "_token";
    tokenInput.value = token;
    form.appendChild(tokenInput);
    document.body.appendChild(form);
    form.submit();
  }

  function FieldCard({ label, value, sub, icon }) {
    return e(Card, { title: label, sub: sub || "", pad: true },
      e("div", { style: { display: "flex", alignItems: "center", gap: 10 } },
        icon && e("span", { className: "ni-ic" }, e(window.Icon, { name: icon, size: 18 })),
        e("div", { style: { fontWeight: 850, fontSize: 18, wordBreak: "break-word" } }, safeText(value))));
  }

  function ProfileTabButton({ active, children, onClick }) {
    return e("button", {
      type: "button",
      className: "tab" + (active ? " on" : ""),
      "aria-current": active ? "page" : undefined,
      onClick,
    }, children);
  }

  function ProfileMetric({ label, value, sub, icon, tone = "b-accent" }) {
    return e("div", { className: "profile-metric" },
      e("div", { className: "profile-metric-top" },
        e("span", { className: "profile-metric-ic " + tone }, e(window.Icon, { name: icon || "id", size: 17 })),
        e("span", { className: "profile-metric-label" }, label)),
      e("div", { className: "profile-metric-value" }, safeText(value)),
      sub && e("div", { className: "cell-sub" }, sub));
  }

  function ProfileInfoRow({ label, value, badgeTone, sub }) {
    return e("div", { className: "profile-info-row" },
      e("div", { className: "profile-info-main" },
        e("span", null, label),
        sub && e("small", null, sub)),
      badgeTone
        ? e(Badge, { tone: badgeTone }, safeText(value))
        : e("b", { className: "mono" }, safeText(value)));
  }

  function ProfileActionCard({ title, sub, icon, children }) {
    return e("div", { className: "profile-action-card" },
      e("div", { className: "profile-action-icon" }, e(window.Icon, { name: icon || "shield", size: 18 })),
      e("div", { className: "profile-action-body" },
        e("b", null, title),
        e("span", null, sub || "")),
      e("div", { className: "profile-action-control" }, children));
  }

  function UserProfile() {
    const profile = profileOptions();
    const [tab, setTab] = React.useState("overview");
    const [loggingOut, setLoggingOut] = React.useState(false);
    const user = profile?.user || window.Builder360Server?.user || {};
    const role = profile?.role || {};
    const company = profile?.company || {};
    const roleContext = profile?.active_role_context || window.Builder360Server?.active_role_context || {};
    const projectContext = profile?.active_project_context || window.Builder360Server?.active_project_context || {};
    const security = profile?.security || {};
    const session = security.session || window.Builder360Server?.auth_security_options?.session || {};
    const recentEvents = rows(profile?.recent_events);
    const projectLabel = projectContext.mode === "selected_project"
      ? [projectContext.project_code, projectContext.project_name].filter(Boolean).join(" · ")
      : "All Projects";

    const unavailable = e(Card, {
      title: "Profile payload unavailable",
      sub: "This screen is fail-closed until Laravel provides scoped profile data.",
      pad: true,
    }, e("div", { className: "empty" }, "No local profile, sample credential or mock account data is fabricated."));

    const doLogout = () => {
      if (loggingOut) return;
      setLoggingOut(true);
      submitLaravelLogout();
    };

    const tabs = [
      ["overview", "Overview"],
      ["access", "Account Access"],
      ["security", "Security"],
      ["activity", "Recent Activity"],
    ];

    const overview = e("div", { className: "profile-section" },
      e("div", { className: "profile-metrics-grid" },
        e(ProfileMetric, { label: "Name", value: user.name, sub: "Laravel user record", icon: "id", tone: "b-accent" }),
        e(ProfileMetric, { label: "Email", value: user.email, sub: user.email_verified ? "Verified email" : "Verification pending", icon: "mail", tone: user.email_verified ? "b-green" : "b-orange" }),
        e(ProfileMetric, { label: "Role", value: role.name || roleContext.role_name || user.role, sub: role.slug || roleContext.role_slug || "Current role", icon: "shield", tone: "b-violet" }),
        e(ProfileMetric, { label: "Company", value: company.code || user.company, sub: company.name || "Company scope", icon: "building", tone: "b-blue" })));

    const access = e("div", { className: "profile-section" },
      e("div", { className: "profile-panel-grid" },
        e(Card, { title: "Current Access Profile", sub: "Backend-scoped role and permission summary from Laravel." },
          e("div", { className: "profile-info-list" },
            e(ProfileInfoRow, { label: "Role Name", value: role.name || roleContext.role_name || user.role, badgeTone: "b-violet", sub: "Active role context" }),
            e(ProfileInfoRow, { label: "Role Slug", value: role.slug || roleContext.role_slug, sub: "Used by route and policy checks" }),
            e(ProfileInfoRow, { label: "Scope Level", value: role.scope_level, badgeTone: "b-blue", sub: "Data visibility boundary" }),
            e(ProfileInfoRow, { label: "Permission Keys", value: role.has_wildcard_permission ? "All" : safeText(role.permissions_count), badgeTone: role.has_wildcard_permission ? "b-green" : "b-slate", sub: "Assigned permission count" }),
            e(ProfileInfoRow, { label: "Preview Context", value: roleContext.is_impersonated_preview ? "Enabled" : "No", badgeTone: roleContext.is_impersonated_preview ? "b-orange" : "b-green", sub: "Role switcher simulation state" }))),
        e(Card, { title: "Company, Project & Route Scope", sub: "Scope applied before dashboards and module data are loaded." },
          e("div", { className: "profile-info-list" },
            e(ProfileInfoRow, { label: "Company", value: company.name || company.code || user.company || "—", sub: "Current company visibility" }),
            e(ProfileInfoRow, { label: "Project", value: projectLabel, badgeTone: projectContext.mode === "selected_project" ? "b-accent" : "b-slate", sub: "Global topbar project context" }),
            e(ProfileInfoRow, { label: "Dashboard Route", value: "#dashboard", sub: "Default route after role switch" }),
            e(ProfileInfoRow, { label: "Account Status", value: user.status, badgeTone: user.status === "active" ? "b-green" : "b-orange", sub: "Laravel user status" })))));

    const securityView = e("div", { className: "profile-section" },
      e("div", { className: "profile-panel-grid" },
        e(Card, { title: "Session Security", sub: "Read-only Laravel session configuration." },
          e("div", { className: "profile-info-list" },
            e(ProfileInfoRow, { label: "Session Driver", value: session.driver, badgeTone: "b-blue", sub: "Configured Laravel session backend" }),
            e(ProfileInfoRow, { label: "SameSite Policy", value: session.same_site, badgeTone: "b-slate", sub: "Browser cookie protection" }),
            e(ProfileInfoRow, { label: "Secure Cookie", value: session.secure_cookie ? "Yes" : "No", badgeTone: session.secure_cookie ? "b-green" : "b-orange", sub: "HTTPS-only cookie flag" }),
            e(ProfileInfoRow, { label: "Session Lifetime", value: safeText(session.lifetime_minutes) + " min", sub: "Configured idle/session lifetime" }),
            e(ProfileInfoRow, { label: "Email Status", value: user.email_verified ? "Verified" : "Pending", badgeTone: user.email_verified ? "b-green" : "b-orange", sub: "Laravel email verification state" }))),
        e(Card, { title: "Account Actions", sub: "Actions use protected Laravel web routes only." },
          e("div", { className: "profile-action-list" },
            e(ProfileActionCard, { title: "Password Reset", sub: security.forgot_password_route ? "Open Laravel password reset route." : "Password reset route is not available.", icon: "refresh" },
              e(RouteAction, { route: security.forgot_password_route, icon: "refresh", children: "Password Reset" })),
            e(ProfileActionCard, { title: "Logout", sub: "Invalidate this Laravel session and return to login.", icon: "x" },
              e(Button, { icon: loggingOut ? "refresh" : "x", tone: "danger", onClick: doLogout, disabled: loggingOut, children: loggingOut ? "Logging out…" : "Logout" })),
            e(ProfileActionCard, { title: "Verification", sub: "Email verification is controlled by Laravel authentication.", icon: "shield" },
              e(Badge, { tone: user.email_verified ? "b-green" : "b-orange" }, user.email_verified ? "Email verified" : "Email pending"))))));

    const activity = e(Card, { title: "Recent Authentication Activity", sub: "Own user activity only; secret metadata is not exposed." },
      recentEvents.length
        ? e("div", { className: "tbl-wrap" },
            e("table", { className: "tbl profile-activity-table" },
              e("thead", null, e("tr", null,
                e("th", null, "Event"),
                e("th", null, "Evidence"),
                e("th", null, "Result"),
                e("th", null, "Time"))),
              e("tbody", null, recentEvents.map(event =>
                e("tr", { key: event.id },
                  e("td", null, e("div", { className: "cell-user" },
                    e("span", { className: "profile-activity-dot" }),
                    e("div", null,
                      e("b", null, titleCase(event.event_type)),
                      e("div", { className: "cell-sub" }, "Auth audit event")))),
                  e("td", null, e("span", { className: "cell-sub" }, event.description || "Recorded auth event")),
                  e("td", null, e(Badge, { tone: event.outcome === "failed" ? "b-red" : "b-green" }, event.outcome || "recorded")),
                  e("td", null, e("span", { className: "cell-sub" }, event.created_at ? new Date(event.created_at).toLocaleString("en-IN") : "—")))))))
        : e("div", { className: "empty" },
            e("div", { className: "empty-ic" }, e(window.Icon, { name: "shield", size: 24 })),
            e("h3", null, "No recent activity"),
            e("p", null, "No recent authentication activity was found for this user.")));

    return e("div", { className: "page page-wide" },
      e(PageHead, {
        crumbs: ["Account", "My Profile"],
        title: "My Profile & Account",
        sub: profile
          ? "Read-only Laravel account profile, role scope, session security and own authentication activity."
          : "Profile data requires a Laravel bootstrap payload for the current user.",
        actions: [
          e(Badge, { key: "source", tone: profile ? "b-green" : "b-orange" }, profile?.source || "API required"),
          e(Badge, { key: "scope", tone: "b-blue" }, role.name || roleContext.role_name || "Current role"),
        ],
      }),
      !profile && unavailable,
      profile && e("div", { className: "grid", style: { gap: 16 } },
        e(Card, { pad: true },
          e("div", { style: { display: "flex", alignItems: "center", gap: 14, flexWrap: "wrap" } },
            e(window.Avatar, { name: user.name || "Builder360 User", size: 54, color: "#F6740C" }),
            e("div", { style: { flex: 1, minWidth: 220 } },
              e("div", { style: { fontWeight: 900, fontSize: 22, letterSpacing: "-.03em" } }, safeText(user.name)),
              e("div", { className: "cell-sub" }, safeText(user.email), " · ", safeText(role.name || roleContext.role_name))),
            e(Badge, { tone: user.status === "active" ? "b-green" : "b-orange" }, safeText(user.status)))),
        e("div", { className: "tabs profile-tabs" },
          tabs.map(([value, label]) => e(ProfileTabButton, { key: value, active: tab === value, onClick: () => setTab(value), children: label }))),
        tab === "overview" && overview,
        tab === "access" && access,
        tab === "security" && securityView,
        tab === "activity" && activity));
  }

  function Auth() {
    const auth = options();
    const controls = rows(auth?.controls);
    const eventCounts = rows(auth?.event_counts);
    const recentEvents = rows(auth?.recent_events);
    const session = auth?.session || {};

    const unavailable = e(Card, {
      title: "Authentication payload unavailable",
      sub: "This screen is fail-closed until Laravel provides scoped authentication status.",
      pad: true,
    }, e("div", { className: "empty" }, "No local authentication showcase, OTP mock, credentials or role redirect samples are fabricated."));

    const routeCards = auth && e("div", { className: "grid-4" },
      e(Card, { title: "Login Route", sub: routeLabel(auth.login_route), pad: true },
        e(RouteAction, { route: auth.login_route, icon: "shield", children: "Open Login" })),
      e(Card, { title: "Password Reset", sub: routeLabel(auth.forgot_password_route), pad: true },
        e(RouteAction, { route: auth.forgot_password_route, icon: "refresh", children: "Open Reset" })),
      e(Card, { title: "Email Verification", sub: routeLabel(auth.verification_notice_route), pad: true },
        e("div", { className: "cell-sub" }, "Verification notice and resend routes are protected by Laravel auth middleware.")),
      e(Card, { title: "Session Driver", sub: session.driver || "Not reported", pad: true },
        e("div", { className: "kpi-mini" }, "SameSite: ", session.same_site || "not configured", " · Lifetime: ", String(session.lifetime_minutes || 0), " min")));

    const controlsCard = auth && e(Card, {
      title: "Security Controls",
      sub: "Actual configured controls from Laravel, not prototype cards.",
    }, e("div", { className: "table-wrap" },
      e("table", { className: "table" },
        e("thead", null, e("tr", null,
          e("th", null, "Control"),
          e("th", null, "Status"),
          e("th", null, "Evidence"))),
        e("tbody", null,
          controls.length
            ? controls.map(control => e("tr", { key: control.key },
                e("td", null, e("b", null, control.label)),
                e("td", null, e(StatusBadge, { status: control.status })),
                e("td", null, e("span", { className: "cell-sub" }, control.detail))))
            : e("tr", null, e("td", { colSpan: 3, className: "empty" }, "No security controls were returned by Laravel."))))));

    const eventCards = auth && e("div", { className: "grid-2" },
      e(Card, { title: "Auth Events - Last 30 Days", sub: auth.can_view_audit_events ? "Audit-wide scope" : "Current user scope" },
        eventCounts.length
          ? e("div", { className: "mini-list" }, eventCounts.map(row =>
              e("div", { key: row.event_type, className: "mini-row" },
                e("span", null, titleCase(row.event_type)),
                e("b", { className: "mono" }, row.count))))
          : e("div", { className: "empty" }, "No authentication audit events in the current scope.")),
      e(Card, { title: "Recent Auth Audit", sub: "Secret fields are not exposed in this payload." },
        recentEvents.length
          ? e("div", { className: "mini-list" }, recentEvents.map(event =>
              e("div", { key: event.id, className: "mini-row" },
                e("div", null,
                  e("b", null, titleCase(event.event_type)),
                  e("div", { className: "cell-sub" }, event.actor?.email || "Guest / unknown account")),
                e("span", { className: "cell-sub" }, event.created_at ? new Date(event.created_at).toLocaleString("en-IN") : "—"))))
          : e("div", { className: "empty" }, "No recent auth events found.")));

    return e("div", { className: "page page-wide" },
      e(PageHead, {
        crumbs: ["System", "Authentication"],
        title: "Authentication & Access Security",
        sub: auth
          ? "Laravel-backed authentication routes, session controls and audited auth events. Mock login, OTP and reset screens are not rendered here."
          : "Authentication status requires a Laravel bootstrap payload for the current role.",
        actions: [
          e(Badge, { key: "source", tone: auth ? "b-green" : "b-orange" }, auth?.source || "API required"),
          e(Badge, { key: "audit", tone: auth?.can_view_audit_events ? "b-violet" : "b-slate" }, auth?.can_view_audit_events ? "Audit scope" : "Own auth events"),
          e(Button, { key: "profile", sm: true, icon: "id", onClick: () => { window.location.hash = "profile"; }, children: "Open My Profile" }),
        ],
      }),
      !auth && unavailable,
      auth && e("div", { className: "grid", style: { gap: 16 } }, routeCards, controlsCard, eventCards),
    );
  }

  window.Auth = Auth;
  window.Profile = UserProfile;
})();
