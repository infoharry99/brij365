const React = window.React;

/* Builder360 — Cross-cutting: Notifications, Business Workflows, Site Visits, Audit Trail */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, PageHead, ChipSelect, Seg } = window;
  const e = React.createElement;
  const DB = window.DB;

  function T(head, rows) {
    const th = head.map((h, i) => e("th", { key: i, style: (h.r ? { textAlign: "right" } : {}) }, h.l != null ? h.l : h));
    const body = rows.map((r, i) => e("tr", { key: i }, r.map((c, j) => e("td", { key: j, className: (head[j] && head[j].r ? "num" : "") }, c))));
    return e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" }, e("thead", null, e("tr", null, th)), e("tbody", null, body)));
  }
  const user = (n) => e("div", { className: "cell-user" }, e(Avatar, { name: n, sm: true }), e("span", { className: "cell-strong" }, n));
  const titleize = (value) => String(value || "not configured").replace(/_/g, " ").replace(/\b\w/g, ch => ch.toUpperCase());
  const settingTone = (status) => ({ active: "b-green", draft: "b-orange", archived: "b-slate", rejected: "b-red" }[status] || "b-slate");

  // ================= NOTIFICATIONS =================
  function Notifications({ toast, role }) {
    const [category, setCategory] = React.useState("");
    const [status, setStatus] = React.useState("");
    const [severity, setSeverity] = React.useState("");
    const [q, setQ] = React.useState("");
    const server = window.Builder360Server && window.Builder360Server.notifications;
    const canManageSettings = Boolean(role?.permissions?.includes("*") || role?.permissions?.includes("settings.manage") || window.Builder360Server?.admin_governance_options?.can_manage_settings);
    const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    const endpoints = server?.endpoints || {
      index_url: "/notifications",
      summary_url: "/notifications/summary",
      read_all_url: "/notifications/read-all",
      read_url_template: "/notifications/__NOTIFICATION__/read",
      archive_url_template: "/notifications/__NOTIFICATION__/archive",
    };
    const firstApiError = (payload) => {
      const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
      return errors[0] || payload?.message || "Notification request failed.";
    };
    const apiJson = async (url, options = {}) => {
      const response = await fetch(url, {
        ...options,
        credentials: "same-origin",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": token(),
          "X-Requested-With": "XMLHttpRequest",
          ...(options.headers || {}),
        },
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(firstApiError(body));
      return body;
    };
    const notificationUrl = (template, notification) => template && notification?.id ? template.replace("__NOTIFICATION__", notification.id) : null;
    const safeNotificationActionUrl = (rawUrl) => {
      if (!rawUrl) return null;
      try {
        const target = new URL(rawUrl, window.location.origin);
        if (target.origin !== window.location.origin) return null;
        return target.pathname + target.search + target.hash;
      } catch (error) {
        return null;
      }
    };
    const fmtTime = (iso) => {
      if (!iso) return "recent";
      const diff = Math.max(0, Date.now() - new Date(iso).getTime());
      const mins = Math.floor(diff / 60000);
      if (mins < 1) return "just now";
      if (mins < 60) return mins + " min ago";
      const hrs = Math.floor(mins / 60);
      if (hrs < 24) return hrs + "h ago";
      return Math.floor(hrs / 24) + "d ago";
    };
    const iconFor = (n) => ({
      collections: "rupee", finance: "rupee", after_sales: "headset", maintenance: "tools", sales: "users",
      legal: "shield", hr: "id", procurement: "cart", construction: "building", system: "gear",
    }[(n.category || "").toLowerCase()] || (n.severity === "critical" ? "alert" : "bellRing"));
    const colorFor = (severity) => ({
      critical: "var(--red)", warning: "var(--orange)", success: "var(--green)", info: "var(--accent)",
    }[severity] || "var(--accent)");
    const toRow = n => ({
      id: n.id, ic: iconFor(n), c: colorFor(n.severity), t: n.title, d: n.body, time: fmtTime(n.created_at),
      cat: (n.category || "general").replace(/_/g, " "), status: n.status, severity: n.severity,
      number: n.notification_number, source: "server", actionUrl: n.action_url, category: n.category || "general",
    });
    const fixedTabs = [
      { label: "All", category: null, empty: "No notifications found for this filter." },
      { label: "Approval", category: "approval", empty: "No approval notifications found." },
      { label: "Payment", category: "payment", empty: "No payment notifications found." },
      { label: "Inventory", category: "inventory", empty: "No inventory notifications found." },
      { label: "Sales", category: "sales", empty: "No sales notifications found." },
      { label: "Legal", category: "legal", empty: "No legal notifications found." },
    ];
    const initialRows = Array.isArray(server?.recent) ? server.recent.map(toRow) : [];
    const [notifs, setNotifs] = React.useState(initialRows);
    const [summary, setSummary] = React.useState(server || null);
    const [filteredSummary, setFilteredSummary] = React.useState(null);
    const [filterOptions, setFilterOptions] = React.useState(server?.filters || { categories: [], statuses: [], severities: [] });
    const [meta, setMeta] = React.useState({ total: initialRows.length });
    const [loading, setLoading] = React.useState(false);
    const [apiState, setApiState] = React.useState({ connected: !!server, error: null });
    const [busy, setBusy] = React.useState("");
    const [openFilter, setOpenFilter] = React.useState("");

    const publishSummary = React.useCallback((payload) => {
      if (!payload) return;
      window.Builder360Server = {
        ...(window.Builder360Server || {}),
        notifications: {
          ...(window.Builder360Server?.notifications || {}),
          ...payload,
          endpoints,
        },
      };
      window.dispatchEvent(new CustomEvent("builder360:notifications-summary", { detail: payload }));
    }, []);

    const categoryCount = category => {
      const rows = summary?.category_counts || summary?.by_category || [];
      if (!Array.isArray(rows)) return 0;
      return Number(rows.find(row => row.category === category)?.count || 0);
    };
    const loadSummary = React.useCallback(() => {
      if (!server || !endpoints.summary_url) return Promise.resolve(null);
      return apiJson(endpoints.summary_url)
        .then(body => {
          const payload = body.data || null;
          if (payload) {
            setSummary(payload);
            publishSummary(payload);
          }
          return payload;
        });
    }, [publishSummary]);
    const queryParams = React.useCallback(() => {
      const params = new URLSearchParams();
      if (q.trim()) params.set("q", q.trim());
      if (category) params.set("category", category);
      if (status) params.set("status", status);
      if (severity) params.set("severity", severity);
      params.set("per_page", "20");
      return params;
    }, [q, category, status, severity]);
    const loadNotifications = React.useCallback(() => {
      if (!server || !endpoints.index_url) return Promise.resolve([]);
      setLoading(true);
      const params = queryParams();
      const url = endpoints.index_url + (params.toString() ? "?" + params.toString() : "");
      return apiJson(url)
        .then(body => {
          const rows = (body.data || []).map(toRow);
          setNotifs(rows);
          setMeta(body.meta || { total: rows.length });
          if (body.summary) {
            setSummary(body.summary);
            publishSummary(body.summary);
          }
          if (body.filtered_summary) setFilteredSummary(body.filtered_summary);
          if (body.filters) setFilterOptions(body.filters);
          setApiState({ connected: true, error: null });
          return rows;
        })
        .catch(error => {
          setApiState({ connected: false, error: error.message });
          setNotifs([]);
          toast && toast("Unable to load notifications. Please retry. " + error.message, "orange");
          return [];
        })
        .finally(() => setLoading(false));
    }, [queryParams, publishSummary]);
    const refreshNotifications = React.useCallback(() => Promise.all([loadNotifications(), loadSummary()]), [loadNotifications, loadSummary]);
    React.useEffect(() => {
      if (!server || !endpoints.index_url) return;
      loadNotifications();
    }, [loadNotifications]);
    React.useEffect(() => {
      const onKey = ev => { if (ev.key === "Escape") setOpenFilter(""); };
      window.addEventListener("keydown", onKey);
      return () => window.removeEventListener("keydown", onKey);
    }, []);
    const activeTab = fixedTabs.find(item => (item.category || "") === category) || fixedTabs[0];
    const counts = filteredSummary?.counts || summary?.counts || {
      total: 0,
      unread: 0,
      read: 0,
      archived: 0,
      critical_unread: 0,
    };
    const unreadCount = Number(counts.unread || 0);
    const tabCount = item => item.category ? categoryCount(item.category) : Number(summary?.counts?.total || 0);
    const activeFilters = () => {
      const filters = {};
      if (q.trim()) filters.q = q.trim();
      if (category) filters.category = category;
      if (status) filters.status = status;
      if (severity) filters.severity = severity;
      return filters;
    };
    const markAllRead = () => {
      if (unreadCount <= 0 || busy) return;
      if (!endpoints.read_all_url) {
        toast && toast("Read state was not saved. Please retry.", "orange");
        return;
      }
      setBusy("all");
      apiJson(endpoints.read_all_url, { method: "PATCH", body: JSON.stringify(activeFilters()) })
        .then(() => refreshNotifications())
        .then(() => {
          toast && toast("Unread notifications marked read", "green");
        })
        .catch(error => toast && toast(error.message, "red"))
        .finally(() => setBusy(""));
    };
    const markRead = (notification) => {
      const url = notificationUrl(endpoints.read_url_template, notification);
      if (!url || notification.status !== "unread" || busy) return;
      setBusy("read-" + notification.id);
      apiJson(url, { method: "PATCH" })
        .then(() => refreshNotifications())
        .then(() => {
          toast && toast("Notification marked read", "green");
        })
        .catch(error => toast && toast(error.message, "red"))
        .finally(() => setBusy(""));
    };
    const archive = (notification) => {
      const url = notificationUrl(endpoints.archive_url_template, notification);
      if (!url || busy) return;
      setBusy("archive-" + notification.id);
      apiJson(url, { method: "PATCH" })
        .then(() => refreshNotifications())
        .then(() => {
          toast && toast("Notification archived", "green");
        })
        .catch(error => toast && toast(error.message, "red"))
        .finally(() => setBusy(""));
    };
    const openAction = (notification) => {
      const target = safeNotificationActionUrl(notification.actionUrl);
      if (!target) {
        toast && toast("Notification action is unavailable or outside the Builder360 application.", "orange");
        return;
      }

      if (notification.status === "unread") {
        const readUrl = notificationUrl(endpoints.read_url_template, notification);
        if (readUrl) {
          apiJson(readUrl, { method: "PATCH" })
            .then(() => refreshNotifications())
            .catch(error => toast && toast("Notification opened, but read state was not saved: " + error.message, "orange"));
        }
      }

      if (target.startsWith("/#")) {
        window.location.hash = target.slice(2);
      } else if (target.startsWith("#")) {
        window.location.hash = target;
      } else if (target === "/" || target === "") {
        window.location.hash = "#dashboard";
      } else {
        window.location.assign(target);
      }
    };
    const openPreferences = () => {
      if (canManageSettings) {
        window.location.hash = "#settings?tab=notifications";
        toast && toast("Opening notification settings.", "green");
        return;
      }
      toast && toast("Notification settings are managed by your administrator.", "orange");
    };
    const chooseMetric = (nextStatus, nextSeverity = "") => {
      setStatus(nextStatus || "");
      setSeverity(nextSeverity || "");
      if (nextStatus === "unread" && nextSeverity === "critical") setCategory("");
    };
    const resetFilters = () => {
      setQ("");
      setCategory("");
      setStatus("");
      setSeverity("");
    };
    const dropdownOptions = (items, emptyLabel) => [
      { value: "", label: emptyLabel },
      ...(items || []).map(item => typeof item === "string" ? { value: item, label: titleize(item) } : item),
    ];
    const FilterDropdown = ({ id, label, value, options, onChange }) => {
      const selected = options.find(item => item.value === value) || options[0];
      const isOpen = openFilter === id;
      return e("div", { className: "notif-dd" },
        e("button", { type: "button", className: "notif-dd-btn", "aria-haspopup": "menu", "aria-expanded": isOpen, onClick: () => setOpenFilter(isOpen ? "" : id) },
          e("span", null, selected?.label || label),
          e(Icon, { name: "chevD", size: 14 })),
        isOpen && e("div", null,
          e("div", { className: "notif-dd-scrim", onClick: () => setOpenFilter("") }),
          e("div", { className: "notif-dd-menu", role: "menu", "aria-label": label },
            options.map(item => e("button", { key: item.value || "all", type: "button", role: "menuitem", className: "notif-dd-option" + (item.value === value ? " selected" : ""), onClick: () => { onChange(item.value); setOpenFilter(""); } },
              e("span", null, item.label),
              item.value === value && e(Icon, { name: "check", size: 14 }))))));
    };
    return e("div", { className: "page" },
      e(PageHead, { crumbs: ["Overview", "Notifications"], title: "Notifications", sub: "Alerts, reminders and escalations across every module.",
        actions: [e(Badge, { key: "src", tone: apiState.connected ? "b-green" : "b-orange" }, apiState.connected ? "Available" : "Setup incomplete"), e(Button, { key: "refresh", icon: "refresh", disabled: loading, onClick: () => refreshNotifications(), children: loading ? "Refreshing..." : "Refresh" }), e(Button, { key: 1, icon: "check", disabled: unreadCount <= 0 || busy === "all", onClick: markAllRead, children: unreadCount > 0 ? (busy === "all" ? "Marking..." : "Mark all read") : "All read" }), e(Button, { key: 2, icon: "gear", onClick: openPreferences, children: "Preferences" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e("button", { type: "button", className: "notif-stat-btn", onClick: () => { setStatus(""); setSeverity(""); } }, e(Stat, { label: "Total", value: counts.total, icon: "bell", tone: "accent" })),
        e("button", { type: "button", className: "notif-stat-btn", onClick: () => chooseMetric("unread") }, e(Stat, { label: "Unread", value: counts.unread, icon: "bellRing", tone: "orange" })),
        e("button", { type: "button", className: "notif-stat-btn", onClick: () => chooseMetric("unread", "critical") }, e(Stat, { label: "Critical Unread", value: counts.critical_unread, icon: "alert", tone: "red" })),
        e("button", { type: "button", className: "notif-stat-btn", onClick: () => chooseMetric("archived") }, e(Stat, { label: "Archived", value: counts.archived, icon: "box", tone: "blue" }))),
      e("div", { className: "notif-toolbar" },
        e("label", { className: "notif-search" },
          e(Icon, { name: "search", size: 17 }),
          e("input", { value: q, onChange: ev => setQ(ev.target.value), placeholder: "Search notifications, numbers, modules, people...", "aria-label": "Search notifications" }),
          q && e("button", { type: "button", className: "notif-clear", onClick: () => setQ(""), "aria-label": "Clear notification search" }, e(Icon, { name: "x", size: 14 }))),
        e("div", { className: "notif-filter-actions" },
          e(FilterDropdown, { id: "status", label: "Status", value: status, options: dropdownOptions(filterOptions.statuses, "All statuses"), onChange: setStatus }),
          e(FilterDropdown, { id: "severity", label: "Severity", value: severity, options: dropdownOptions(filterOptions.severities, "All severities"), onChange: setSeverity }),
          (q || category || status || severity) && e("button", { type: "button", className: "btn", onClick: resetFilters }, "Clear"))),
      e("div", { className: "tabs", role: "tablist", "aria-label": "Notification categories" }, fixedTabs.map(item => e("button", { key: item.label, type: "button", role: "tab", "aria-selected": (item.category || "") === category, className: "tab " + ((item.category || "") === category ? "on" : ""), onClick: () => setCategory(item.category || "") }, item.label, e("span", { className: "tab-count" }, tabCount(item))))),
      loading && e("div", { className: "sys-note", style: { marginBottom: 12 } }, e(Icon, { name: "refresh", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), "Loading notifications..."),
      apiState.error && e("div", { className: "sys-note", style: { marginBottom: 12, color: "var(--orange)" } }, e(Icon, { name: "alert", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), apiState.error),
      e("div", { className: "card notif-list" }, notifs.length === 0
        ? e("div", { className: "notif-empty" }, activeTab.empty, meta?.total === 0 && (q || category || status || severity) ? e("button", { type: "button", className: "btn btn-sm", onClick: resetFilters }, "Clear filters") : null)
        : notifs.map((n, i) =>
        e("article", { key: n.id || i, className: "notif-row" },
          e("div", { className: "notif-icon", style: { color: n.c } }, e(Icon, { name: n.ic, size: 18 })),
          e("div", { className: "notif-main" }, e("div", { className: "notif-titleline" }, e("span", { className: "notif-title" }, n.t), e("span", { className: "badge b-slate" }, n.cat)),
            e("div", { className: "muted notif-body" }, n.d),
            n.number && e("div", { className: "faint notif-number" }, n.number)),
          e("div", { className: "notif-side" },
            e("div", { className: "notif-statusline" },
              n.status === "unread" && e("span", { className: "badge b-orange" }, "Unread"),
              n.status === "archived" && e("span", { className: "badge b-blue" }, "Archived"),
              e("span", { className: "faint notif-time" }, n.time)),
            n.source === "server" && e("div", { className: "notif-actions" },
              n.actionUrl && e("button", { className: "btn btn-sm btn-primary", disabled: busy === "open-" + n.id, onClick: () => openAction(n) }, "Open"),
              n.status === "unread" && e("button", { className: "btn btn-sm", disabled: busy === "read-" + n.id, onClick: () => markRead(n) }, busy === "read-" + n.id ? "Saving..." : "Mark read"),
              n.status !== "archived" && e("button", { className: "btn btn-sm", disabled: busy === "archive-" + n.id, onClick: () => archive(n) }, busy === "archive-" + n.id ? "Archiving..." : "Archive")))))),
    );
  }

  // ================= BUSINESS WORKFLOWS =================
  function Workflows({ toast }) {
    const adminOptions = window.Builder360Server?.admin_governance_options || null;
    const settings = Array.isArray(adminOptions?.settings) ? adminOptions.settings : [];
    const approvalChains = Array.isArray(adminOptions?.approval_chains) ? adminOptions.approval_chains : [];
    const modules = Array.isArray(adminOptions?.modules) ? adminOptions.modules : [];
    const approvalSetting = settings.find(row => row.setting_key === "workflow.approval_chains") || null;
    const workflowSettings = settings.filter(row => ["workflow", "governance", "hr", "payroll", "finance", "construction"].includes(row.setting_group));
    const colors = ["#F6740C", "#0ea5a4", "#e08600", "#7c3aed", "#15a657", "#2570eb", "#dc2f3a", "#64748b"];
    const flows = approvalChains.map((chain, index) => ({
      name: titleize(chain.workflow),
      c: colors[index % colors.length],
      steps: chain.steps || [],
      status: approvalSetting?.status || "not configured",
      source: approvalSetting?.setting_key || "workflow.approval_chains",
      version: approvalSetting?.version || null,
      effective_from: approvalSetting?.effective_from || null,
    }));
    const settingRows = workflowSettings.map(row => [
      e("span", { className: "cell-strong" }, row.label || row.setting_key),
      e("span", { className: "tag" }, titleize(row.setting_group)),
      e("span", { className: "mono" }, "v" + row.version),
      e(Badge, { tone: settingTone(row.status), dot: true }, titleize(row.status)),
      e("span", { className: "faint" }, row.effective_from || "not set"),
    ]);
    const noAccess = !adminOptions;
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["System", "Business Workflows"], title: "Core Business Workflows", sub: "Approval chains, settings records and workflow management.",
        actions: [
          e(Badge, { key: "src", tone: noAccess ? "b-orange" : "b-green" }, noAccess ? "RESTRICTED" : "SERVER SCOPED"),
          e(Button, { key: "settings", icon: "gear", onClick: () => toast && toast(noAccess ? "Workflow configuration is restricted for this role." : "Workflow configuration is managed through System Settings.", noAccess ? "orange" : "blue"), children: "Configure" }),
        ] }),
      noAccess && e("div", { className: "card card-pad", style: { marginBottom: 16 } },
        e("div", { className: "row gap-3" }, e(Icon, { name: "shield", size: 18, style: { color: "var(--orange)" } }), e("div", null, e("div", { className: "cell-strong" }, "Workflow configuration is not available for this role."), e("div", { className: "muted", style: { fontSize: 12.5 } }, "Ask a System Administrator or Director to review active approval-chain settings.")))),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Approval Chains", value: flows.length, icon: "check", tone: "accent" }),
        e(Stat, { label: "Workflow Settings", value: workflowSettings.length, icon: "sliders", tone: "blue" }),
        e(Stat, { label: "Visible Modules", value: modules.length, icon: "grid", tone: "violet" }),
        e(Stat, { label: "Active Source", value: approvalSetting ? "v" + approvalSetting.version : "N/A", icon: "shield", tone: approvalSetting ? "green" : "orange" })),
      flows.length ? e("div", { className: "grid g-2" }, flows.map((f, i) =>
        e("div", { key: i, className: "card card-pad" },
          e("div", { className: "row gap-3", style: { marginBottom: 16 } },
            e("div", { style: { width: 8, height: 24, borderRadius: 3, background: f.c } }),
            e("div", { style: { flex: 1 } }, e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 15.5 } }, f.name), e("div", { className: "cell-sub" }, f.source + (f.version ? " · v" + f.version : ""))),
            e(Badge, { tone: settingTone(f.status), dot: true }, titleize(f.status))),
          e("div", { style: { display: "flex", flexWrap: "wrap", gap: 6, alignItems: "center" } }, f.steps.map((s, j) =>
            e(React.Fragment, { key: j },
              e("span", { style: { padding: "5px 11px", borderRadius: 8, background: "var(--surface-3)", border: "1px solid var(--border)", fontSize: 11.5, fontWeight: 700, color: "var(--text)" } }, titleize(s)),
              j < f.steps.length - 1 && e(Icon, { name: "chevR", size: 13, style: { color: f.c, opacity: .7 } }))))))) : e("div", { className: "card card-pad" }, e("div", { className: "empty" }, "No active approval chains are visible in the current scope.")),
      e(Card, { title: "Workflow Configuration Register", sub: "Versioned governed settings used by workflow services" },
        settingRows.length ? T([{ l: "Setting" }, { l: "Group" }, { l: "Version" }, { l: "Status" }, { l: "Effective From" }], settingRows) : e("div", { className: "empty" }, "No workflow-related settings are visible.")),
    );
  }

  // ================= SITE VISIT MANAGEMENT =================
  function SiteVisits({ toast }) {
    const options = window.Builder360Server?.crm_site_visit_options || null;
    const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    const firstApiError = (payload) => {
      const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
      return errors[0] || payload?.message || "Site visit request failed.";
    };
    const apiJson = async (url, options = {}) => {
      const response = await fetch(url, {
        ...options,
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": token(),
          ...(options.headers || {}),
        },
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(firstApiError(body));
      return body;
    };
    const visitUrl = (template, visit) => template && visit?.id ? template.replace("__VISIT__", visit.id) : null;
    const badgeTone = status => ({ scheduled: "b-blue", completed: "b-green", cancelled: "b-red", no_show: "b-orange" }[status] || "b-slate");
    const fmtDate = iso => iso ? new Date(iso).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : "Not scheduled";
    const toRow = v => ({
      id: v.id,
      customer: v.customer?.name || "Unassigned customer",
      interest: [v.project?.name, v.visit_mode].filter(Boolean).join(" · ") || "General visit",
      executive: v.assigned_to?.name || "Sales desk",
      slot: fmtDate(v.scheduled_at),
      status: v.status || "scheduled",
      number: v.visit_number,
      leadCode: v.lead?.lead_code,
      scheduledAt: v.scheduled_at,
      durationMinutes: v.duration_minutes || 60,
      visitMode: v.visit_mode || "site",
      meetingLocation: v.meeting_location || "",
      meetingUrl: v.meeting_url || "",
      agenda: v.agenda || "",
      attendees: Array.isArray(v.attendees) ? v.attendees : [],
      assignedToId: v.assigned_to?.id || "",
      source: "server",
    });
    const scheduleLeads = Array.isArray(options?.leads) ? options.leads : [];
    const scheduleAssignees = Array.isArray(options?.assignees) ? options.assignees : [];
    const visitModes = Array.isArray(options?.visit_modes) && options.visit_modes.length ? options.visit_modes : [
      { value: "site", label: "Site visit" },
      { value: "office", label: "Office meeting" },
      { value: "virtual", label: "Virtual meeting" },
    ];
    const localDateTimeValue = date => {
      const pad = value => String(value).padStart(2, "0");
      return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate()) + "T" + pad(date.getHours()) + ":" + pad(date.getMinutes());
    };
    const nextDefaultSlot = () => {
      const date = new Date(Date.now() + 24 * 60 * 60 * 1000);
      date.setHours(11, 0, 0, 0);
      return localDateTimeValue(date);
    };
    const defaultScheduleForm = () => ({
      lead_id: scheduleLeads[0]?.id ? String(scheduleLeads[0].id) : "",
      assigned_to_user_id: "",
      scheduled_at: nextDefaultSlot(),
      duration_minutes: String(options?.default_duration_minutes || 60),
      visit_mode: visitModes[0]?.value || "site",
      meeting_location: "",
      meeting_url: "",
      agenda: "",
      attendee_name: "",
      attendee_phone: "",
      attendee_role: "Buyer",
    });
    const [visits, setVisits] = React.useState([]);
    const [status, setStatus] = React.useState("");
    const [loading, setLoading] = React.useState(false);
    const [scheduleOpen, setScheduleOpen] = React.useState(false);
    const [editingVisit, setEditingVisit] = React.useState(null);
    const [scheduleForm, setScheduleForm] = React.useState(defaultScheduleForm);
    const [scheduleSubmitting, setScheduleSubmitting] = React.useState(false);
    const [actionVisit, setActionVisit] = React.useState(null);
    const [actionForm, setActionForm] = React.useState({ type: "", outcome: "follow_up_required", note: "", next_follow_up_at: "" });
    const [apiState, setApiState] = React.useState({ connected: false, error: null, total: 0 });
    const siteVisitStatusOptions = [
      ["", "All"],
      ["scheduled", "Scheduled"],
      ["completed", "Completed"],
      ["cancelled", "Cancelled"],
      ["no_show", "No Show"],
    ];
    const completionOutcomeOptions = (Array.isArray(options?.outcomes) && options.outcomes.length ? options.outcomes : ["interested", "follow_up_required", "booking_expected", "not_interested", "no_show"])
      .map(value => [value, titleize(value)]);
    const loadVisits = (nextStatus = status) => {
      if (!options?.index_url) {
        setVisits([]);
        setApiState({ connected: false, error: "Site visit API is not available for this role.", total: 0 });
        return;
      }
      const params = new URLSearchParams();
      if (nextStatus) params.set("status", nextStatus);
      const url = options.index_url + (params.toString() ? "?" + params.toString() : "");
      setLoading(true);
      apiJson(url)
        .then(body => {
          setVisits((body.data || []).map(toRow));
          setApiState({ connected: true, error: null, total: body.meta?.total || (body.data || []).length });
        })
        .catch(error => {
          setVisits([]);
          setApiState({ connected: false, error: error.message, total: 0 });
          toast && toast("Site visit API unavailable; no local fallback visits are shown. " + error.message, "orange");
        })
        .finally(() => setLoading(false));
    };
    React.useEffect(() => { loadVisits(""); }, []);
    const changeStatus = value => {
      setStatus(value);
      loadVisits(value);
    };
    const replaceVisit = row => setVisits(items => items.map(item => item.id === row.id ? row : item));
    const openVisitAction = (visit, type) => {
      const url = visitUrl(type === "complete" ? options?.complete_url_template : options?.cancel_url_template, visit);
      if (!url) return;
      setActionVisit(visit);
      setActionForm({
        type,
        outcome: "follow_up_required",
        next_follow_up_at: type === "complete" ? nextDefaultSlot() : "",
        note: type === "complete" ? "Completed from Site Visit Management." : "Cancelled from Site Visit Management.",
      });
    };
    const submitVisitAction = ev => {
      ev.preventDefault();
      const type = actionForm.type;
      const visit = actionVisit;
      const url = visitUrl(type === "complete" ? options?.complete_url_template : options?.cancel_url_template, visit);
      if (!url || !visit) return;
      if (!actionForm.note.trim()) {
        toast && toast(type === "complete" ? "Completion notes are required." : "Cancellation reason is required.", "orange");
        return;
      }
      const payload = type === "complete"
        ? { outcome: actionForm.outcome, outcome_notes: actionForm.note.trim(), ...(actionForm.next_follow_up_at ? { next_follow_up_at: actionForm.next_follow_up_at } : {}) }
        : { reason: actionForm.note.trim() };
      apiJson(url, { method: "PATCH", body: JSON.stringify(payload) })
        .then(body => {
          replaceVisit(toRow(body.data));
          setActionVisit(null);
          toast && toast(type === "complete" ? "Site visit completed in Laravel." : "Site visit cancelled in Laravel.", "green");
        })
        .catch(error => toast && toast(error.message, "red"));
    };
    const openScheduleModal = () => {
      if (!options?.store_url) {
        toast && toast("Site visit scheduling is unavailable for this role.", "orange");
        return;
      }
      if (scheduleLeads.length === 0) {
        toast && toast("No open leads are available in your current CRM scope for scheduling.", "orange");
        return;
      }
      setScheduleForm(defaultScheduleForm());
      setEditingVisit(null);
      setScheduleOpen(true);
    };
    const openEditScheduleModal = visit => {
      const url = visitUrl(options?.update_url_template, visit);
      if (!url) {
        toast && toast("Site visit update endpoint is unavailable for this role.", "orange");
        return;
      }
      if (!visit || visit.status !== "scheduled") {
        toast && toast("Only scheduled site visits can be edited.", "orange");
        return;
      }
      const firstAttendee = Array.isArray(visit.attendees) && visit.attendees.length ? visit.attendees[0] : {};
      const scheduledValue = visit.scheduledAt ? localDateTimeValue(new Date(visit.scheduledAt)) : nextDefaultSlot();
      setEditingVisit(visit);
      setScheduleForm({
        lead_id: "",
        assigned_to_user_id: visit.assignedToId ? String(visit.assignedToId) : "",
        scheduled_at: scheduledValue,
        duration_minutes: String(visit.durationMinutes || options?.default_duration_minutes || 60),
        visit_mode: visit.visitMode || "site",
        meeting_location: visit.meetingLocation || "",
        meeting_url: visit.meetingUrl || "",
        agenda: visit.agenda || "",
        attendee_name: firstAttendee.name || "",
        attendee_phone: firstAttendee.phone || "",
        attendee_role: firstAttendee.role || "Buyer",
      });
      setScheduleOpen(true);
    };
    const submitScheduleVisit = ev => {
      ev.preventDefault();

      const leadId = Number(scheduleForm.lead_id);
      const duration = Number(scheduleForm.duration_minutes || options?.default_duration_minutes || 60);
      const scheduledAt = new Date(scheduleForm.scheduled_at);

      if (!editingVisit && !leadId) {
        toast && toast("Select a lead before scheduling the site visit.", "orange");
        return;
      }
      if (!scheduleForm.scheduled_at || Number.isNaN(scheduledAt.getTime()) || scheduledAt.getTime() <= Date.now()) {
        toast && toast("Select a future date and time for the site visit.", "orange");
        return;
      }
      if (!duration || duration < 15 || duration > 480) {
        toast && toast("Duration must be between 15 and 480 minutes.", "orange");
        return;
      }

      const payload = {
        scheduled_at: scheduleForm.scheduled_at,
        duration_minutes: duration,
        visit_mode: scheduleForm.visit_mode,
        meeting_location: scheduleForm.meeting_location.trim() || null,
        meeting_url: scheduleForm.meeting_url.trim() || null,
        agenda: scheduleForm.agenda.trim() || null,
        attendees: scheduleForm.attendee_name.trim() ? [{
          name: scheduleForm.attendee_name.trim(),
          phone: scheduleForm.attendee_phone.trim() || null,
          role: scheduleForm.attendee_role.trim() || null,
        }] : [],
        metadata: { source: "site_visit_management_schedule_modal" },
      };

      if (!editingVisit) {
        payload.lead_id = leadId;
      }

      if (scheduleForm.assigned_to_user_id) {
        payload.assigned_to_user_id = Number(scheduleForm.assigned_to_user_id);
      }

      const url = editingVisit ? visitUrl(options?.update_url_template, editingVisit) : options.store_url;
      if (!url) {
        toast && toast(editingVisit ? "Site visit update endpoint is unavailable." : "Site visit scheduling endpoint is unavailable.", "orange");
        return;
      }

      setScheduleSubmitting(true);
      apiJson(url, { method: editingVisit ? "PATCH" : "POST", body: JSON.stringify(payload) })
        .then(body => {
          const row = toRow(body.data);
          setVisits(items => {
            if (editingVisit) return items.map(item => item.id === row.id ? row : item);
            return status && status !== row.status ? items : [row, ...items.filter(item => item.id !== row.id)];
          });
          if (!editingVisit) {
            setApiState(current => ({ ...current, connected: true, error: null, total: current.total + 1 }));
          }
          setScheduleOpen(false);
          setEditingVisit(null);
          toast && toast(editingVisit ? "Site visit updated in Laravel." : "Site visit scheduled in Laravel.", "green");
        })
        .catch(error => toast && toast(error.message, "red"))
        .finally(() => setScheduleSubmitting(false));
    };
    const scheduled = visits.filter(v => v.status === "scheduled").length;
    const completed = visits.filter(v => v.status === "completed").length;
    const noShows = visits.filter(v => v.status === "no_show").length;
    const conversion = visits.length ? Math.round(completed / visits.length * 100) : 0;
    const formFieldStyle = { height: 38, border: "1px solid var(--border)", borderRadius: 9, background: "var(--surface)", color: "var(--text)", padding: "0 10px", font: "inherit" };
    const formLabelStyle = { display: "grid", gap: 5, fontSize: 12.5, fontWeight: 700, color: "var(--text-2)" };
    const scheduleModal = scheduleOpen ? e("div", { className: "scrim", onClick: () => !scheduleSubmitting && setScheduleOpen(false) },
      e("form", { className: "modal", style: { width: 760, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submitScheduleVisit },
        e("div", { className: "modal-head" },
          e("div", null,
            e("h2", null, editingVisit ? "Edit Site Visit" : "Schedule Site Visit"),
            e("p", { className: "muted" }, editingVisit ? "Updates a scheduled Laravel site visit with assignee conflict checks, workflow history and audit trail." : "Creates a validated CRM site visit with Laravel lead scope, assignee conflict checks, activity history and audit trail.")),
          e("button", { type: "button", className: "icon-btn", disabled: scheduleSubmitting, onClick: () => { setScheduleOpen(false); setEditingVisit(null); } }, e(Icon, { name: "x" }))),
        e("div", { style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          e("label", { style: { ...formLabelStyle, gridColumn: "1 / -1" } }, "Lead",
            editingVisit ? e("input", { style: formFieldStyle, disabled: true, value: [editingVisit.number, editingVisit.leadCode, editingVisit.customer].filter(Boolean).join(" · ") }) : e("select", { required: true, style: formFieldStyle, value: scheduleForm.lead_id, onChange: ev => setScheduleForm(prev => ({ ...prev, lead_id: ev.target.value })) },
              e("option", { value: "" }, "Select lead"),
              scheduleLeads.map(lead => e("option", { key: lead.id, value: lead.id },
                [lead.lead_code, lead.customer_name, lead.project_name, lead.stage].filter(Boolean).join(" · "))))),
          e("label", { style: formLabelStyle }, "Assigned Executive",
            e("select", { style: formFieldStyle, value: scheduleForm.assigned_to_user_id, onChange: ev => setScheduleForm(prev => ({ ...prev, assigned_to_user_id: ev.target.value })) },
              e("option", { value: "" }, "Current user / sales desk"),
              scheduleAssignees.map(assignee => e("option", { key: assignee.id, value: assignee.id }, [assignee.name, assignee.role].filter(Boolean).join(" · "))))),
          e("label", { style: formLabelStyle }, "Visit Mode",
            e("select", { required: true, style: formFieldStyle, value: scheduleForm.visit_mode, onChange: ev => setScheduleForm(prev => ({ ...prev, visit_mode: ev.target.value })) },
              visitModes.map(mode => e("option", { key: mode.value, value: mode.value }, mode.label || mode.value)))),
          e("label", { style: formLabelStyle }, "Scheduled Date & Time",
            e("input", { required: true, type: "datetime-local", style: formFieldStyle, value: scheduleForm.scheduled_at, onChange: ev => setScheduleForm(prev => ({ ...prev, scheduled_at: ev.target.value })) })),
          e("label", { style: formLabelStyle }, "Duration Minutes",
            e("input", { required: true, type: "number", min: 15, max: 480, step: 15, style: formFieldStyle, value: scheduleForm.duration_minutes, onChange: ev => setScheduleForm(prev => ({ ...prev, duration_minutes: ev.target.value })) })),
          e("label", { style: formLabelStyle }, "Meeting Location",
            e("input", { maxLength: 255, style: formFieldStyle, placeholder: "Site office, sales gallery or meeting point", value: scheduleForm.meeting_location, onChange: ev => setScheduleForm(prev => ({ ...prev, meeting_location: ev.target.value })) })),
          e("label", { style: formLabelStyle }, "Virtual Meeting URL",
            e("input", { type: "url", maxLength: 1024, style: formFieldStyle, placeholder: "https://...", value: scheduleForm.meeting_url, onChange: ev => setScheduleForm(prev => ({ ...prev, meeting_url: ev.target.value })) })),
          e("label", { style: formLabelStyle }, "Attendee Name",
            e("input", { maxLength: 255, style: formFieldStyle, placeholder: "Customer / buyer representative", value: scheduleForm.attendee_name, onChange: ev => setScheduleForm(prev => ({ ...prev, attendee_name: ev.target.value })) })),
          e("label", { style: formLabelStyle }, "Attendee Phone",
            e("input", { maxLength: 40, style: formFieldStyle, placeholder: "+91 ...", value: scheduleForm.attendee_phone, onChange: ev => setScheduleForm(prev => ({ ...prev, attendee_phone: ev.target.value })) })),
          e("label", { style: { ...formLabelStyle, gridColumn: "1 / -1" } }, "Agenda / Notes",
            e("textarea", { maxLength: 5000, value: scheduleForm.agenda, onChange: ev => setScheduleForm(prev => ({ ...prev, agenda: ev.target.value })), style: { minHeight: 90, border: "1px solid var(--border)", borderRadius: 9, background: "var(--surface)", color: "var(--text)", padding: 10, resize: "vertical", fontFamily: "inherit" }, placeholder: "Visit purpose, inventory to show, customer preference or follow-up context" }))),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: scheduleSubmitting, onClick: () => { setScheduleOpen(false); setEditingVisit(null); }, children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: scheduleSubmitting }, e(Icon, { name: "calendar", size: 15 }), scheduleSubmitting ? (editingVisit ? "Updating..." : "Scheduling...") : (editingVisit ? "Update Visit" : "Schedule Visit"))))) : null;
    const actionModal = actionVisit ? e("div", { className: "scrim", onClick: () => setActionVisit(null) },
      e("form", { className: "modal", style: { width: 620, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submitVisitAction },
        e("div", { className: "modal-head" },
          e("div", null,
            e("h2", null, actionForm.type === "complete" ? "Complete Site Visit" : "Cancel Site Visit"),
            e("p", { className: "muted" }, [actionVisit.number, actionVisit.customer, actionVisit.slot].filter(Boolean).join(" Â· "))),
          e("button", { type: "button", className: "icon-btn", onClick: () => setActionVisit(null) }, e(Icon, { name: "x" }))),
        e("div", { style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          actionForm.type === "complete" && e("label", { style: { display: "grid", gap: 5, fontSize: 12.5, fontWeight: 700, color: "var(--text-2)" } }, "Outcome",
            e("select", { style: { height: 38, border: "1px solid var(--border)", borderRadius: 9, background: "var(--surface)", color: "var(--text)", padding: "0 10px" }, value: actionForm.outcome, onChange: ev => setActionForm(prev => ({ ...prev, outcome: ev.target.value })) },
              completionOutcomeOptions.map(([value, text]) => e("option", { key: value, value }, text)))),
          actionForm.type === "complete" && e("label", { style: { display: "grid", gap: 5, fontSize: 12.5, fontWeight: 700, color: "var(--text-2)" } }, "Next Follow-up",
            e("input", { type: "datetime-local", value: actionForm.next_follow_up_at, onChange: ev => setActionForm(prev => ({ ...prev, next_follow_up_at: ev.target.value })), style: { height: 38, border: "1px solid var(--border)", borderRadius: 9, background: "var(--surface)", color: "var(--text)", padding: "0 10px" } })),
          e("label", { style: { display: "grid", gap: 5, fontSize: 12.5, fontWeight: 700, color: "var(--text-2)", gridColumn: "1 / -1" } }, actionForm.type === "complete" ? "Completion Notes" : "Cancellation Reason",
            e("textarea", { required: true, maxLength: 2000, value: actionForm.note, onChange: ev => setActionForm(prev => ({ ...prev, note: ev.target.value })), style: { minHeight: 96, border: "1px solid var(--border)", borderRadius: 9, background: "var(--surface)", color: "var(--text)", padding: 10, resize: "vertical", fontFamily: "inherit" } }))),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", onClick: () => setActionVisit(null), children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit" }, e(Icon, { name: actionForm.type === "complete" ? "check" : "x", size: 15 }), actionForm.type === "complete" ? "Complete Visit" : "Cancel Visit")))) : null;
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Sales & CRM", "Site Visit Management"], title: "Site Visit Management", sub: "Schedule, confirm and convert site visits — the key stage between verified lead and booking.",
        actions: [
          e(Badge, { key: "src", tone: apiState.connected ? "b-green" : "b-orange" }, apiState.connected ? "SERVER SCOPED" : "API REQUIRED"),
          e("label", { key: "status", className: "chip-select", style: { gap: 8 } },
            e("span", { style: { color: "var(--text-3)" } }, "Status"),
            e("select", { "aria-label": "Filter site visits by status", value: status, disabled: loading, onChange: ev => changeStatus(ev.target.value), style: { border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontWeight: 800, minWidth: 116 } },
              siteVisitStatusOptions.map(([value, label]) => e("option", { key: value || "all", value }, label)))),
          e(Button, { key: 2, icon: "refresh", onClick: () => loadVisits(status), children: "Refresh" }),
          e(Button, { key: 3, icon: "plus", variant: "primary", onClick: openScheduleModal, children: "Schedule Visit" })
        ] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Loaded Visits", value: apiState.total, icon: "calendar", tone: "accent" }),
        e(Stat, { label: "Scheduled", value: scheduled, icon: "clock", tone: "blue" }),
        e(Stat, { label: "Completed", value: completed, icon: "check", tone: "green", sub: conversion + "% of loaded visits" }),
        e(Stat, { label: "No-shows", value: noShows, icon: "x", tone: "red" })),
      e("div", { className: "card card-pad", style: { marginBottom: 16 } },
        e("div", { className: "row gap-2", style: { alignItems: "center", flexWrap: "wrap" } },
          e("span", { className: "cell-strong" }, "Filter"),
          siteVisitStatusOptions.map(([value, label]) => e("button", { key: value || "all", className: "btn btn-sm" + (status === value ? " btn-primary" : ""), onClick: () => changeStatus(value) }, label)))),
      loading && e("div", { className: "sys-note", style: { marginBottom: 12 } }, e(Icon, { name: "refresh", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), "Loading site visits from Laravel..."),
      apiState.error && e("div", { className: "sys-note", style: { marginBottom: 12, color: "var(--orange)" } }, e(Icon, { name: "alert", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), apiState.error),
      e(Card, { title: "Site Visits", sub: "today & upcoming" },
        T([{ l: "Prospect" }, { l: "Interest" }, { l: "Executive" }, { l: "Slot" }, { l: "Status" }, { l: "Action" }],
          visits.map(v => [user(v.customer), e("span", null, v.interest, v.number && e("div", { className: "faint", style: { fontSize: 11 } }, v.number + (v.leadCode ? " · " + v.leadCode : ""))), e("span", { style: { fontSize: 12.5 } }, v.executive), e("span", { className: "faint" }, v.slot), e(Badge, { tone: badgeTone(v.status), dot: true }, v.status),
            v.source === "server" && v.status === "scheduled" ? e("div", { className: "row gap-2" }, e(Button, { sm: true, onClick: () => openEditScheduleModal(v), children: "Edit" }), e(Button, { sm: true, variant: "primary", onClick: () => openVisitAction(v, "complete"), children: "Complete" }), e(Button, { sm: true, onClick: () => openVisitAction(v, "cancel"), children: "Cancel" })) : e("span", { className: "faint", style: { fontSize: 12 } }, "—")]))),
      scheduleModal,
      actionModal,
    );
  }

  // ================= AUDIT TRAIL =================
  function Audit({ toast }) {
    const options = window.Builder360Server?.audit_trail_options || null;
    const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    const firstApiError = (payload) => {
      const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
      return errors[0] || payload?.message || "Audit trail request failed.";
    };
    const apiJson = async (url) => {
      const response = await fetch(url, { headers: { "Accept": "application/json", "X-CSRF-TOKEN": token() } });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(firstApiError(body));
      return body;
    };
    const classify = (eventType, method) => {
      const value = String(eventType || "").toLowerCase();
      if (value.includes("approved") || value.includes("approval")) return "Approve";
      if (value.includes("export")) return "Export";
      if (value.includes("delete") || value.includes("archive") || value.includes("deactivated")) return "Delete";
      if (method === "POST" || value.includes("created")) return "Create";
      if (method === "PATCH" || method === "PUT" || value.includes("updated")) return "Update";
      return "System";
    };
    const fmtTime = (iso) => {
      if (!iso) return "recent";
      const diff = Math.max(0, Date.now() - new Date(iso).getTime());
      const mins = Math.floor(diff / 60000);
      if (mins < 1) return "just now";
      if (mins < 60) return mins + " min ago";
      const hrs = Math.floor(mins / 60);
      if (hrs < 24) return hrs + "h ago";
      return Math.floor(hrs / 24) + "d ago";
    };
    const toRow = event => ({
      id: event.id,
      actor: event.user?.name || "System",
      action: event.action || event.event_type,
      detail: [event.event_type, event.auditable_type ? event.auditable_type.split("\\").pop() + "#" + event.auditable_id : null, event.request_method || null, event.request_id || null].filter(Boolean).join(" - "),
      time: fmtTime(event.created_at),
      type: classify(event.event_type, event.request_method),
      source: "server",
    });
    const [logs, setLogs] = React.useState([]);
    const [q, setQ] = React.useState("");
    const [loading, setLoading] = React.useState(false);
    const [apiState, setApiState] = React.useState({ connected: false, error: null, total: 0 });
    const loadAudit = (search) => {
      if (!options?.index_url) {
        setLogs([]);
        setApiState({ connected: false, error: "Audit API is not available for this role.", total: 0 });
        return;
      }
      const params = new URLSearchParams();
      if (search) params.set("search", search);
      const url = options.index_url + (params.toString() ? "?" + params.toString() : "");
      setLoading(true);
      apiJson(url)
        .then(body => {
          setLogs((body.data || []).map(toRow));
          setApiState({ connected: true, error: null, total: body.meta?.total || (body.data || []).length });
        })
        .catch(error => {
          setLogs([]);
          setApiState({ connected: false, error: error.message, total: 0 });
          toast && toast("Audit API unavailable; no local fallback audit rows are shown. " + error.message, "orange");
        })
        .finally(() => setLoading(false));
    };
    React.useEffect(() => { loadAudit(""); }, []);
    const tn = a => ({ Create: "b-green", Approve: "b-accent", Update: "b-blue", Export: "b-violet", Delete: "b-red", System: "b-slate" }[a] || "b-slate");
    const recentCount = logs.filter(log => String(log.time).includes("min") || String(log.time).includes("h") || String(log.time).includes("just")).length;
    const approvalCount = logs.filter(log => log.type === "Approve").length;
    const exportCount = logs.filter(log => log.type === "Export").length;
    const exportAudit = () => {
      if (!options?.export_url) {
        toast && toast("Audit export is unavailable for this role.", "orange");
        return;
      }

      const url = new URL(options.export_url, window.location.origin);
      if (q.trim()) url.searchParams.set("search", q.trim());
      window.location.assign(url.toString());
    };
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["System", "Audit Trail"], title: "Audit Trail", sub: "User-wise action history, approvals, modifications, logins and exports — fully traceable.",
        actions: [e(Badge, { key: "src", tone: apiState.connected ? "b-green" : "b-orange" }, apiState.connected ? "SERVER SCOPED" : "API REQUIRED"), e(Button, { key: 1, icon: "refresh", onClick: () => loadAudit(q), children: "Refresh" }), e(Button, { key: 2, icon: "download", onClick: exportAudit, children: "Export Log" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Loaded Events", value: apiState.total, icon: "trend", tone: "accent" }),
        e(Stat, { label: "Recent Actions", value: recentCount, icon: "clock", tone: "blue" }),
        e(Stat, { label: "Approvals Logged", value: approvalCount, icon: "check", tone: "green" }),
        e(Stat, { label: "Data Exports", value: exportCount, icon: "download", tone: "violet" })),
      e("div", { className: "card card-pad", style: { marginBottom: 16 } },
        e("div", { className: "row gap-2", style: { alignItems: "center" } },
          e("input", { value: q, onChange: ev => setQ(ev.target.value), onKeyDown: ev => { if (ev.key === "Enter") loadAudit(q); }, placeholder: "Search event type or action", style: { flex: 1, height: 38, border: "1px solid var(--border)", borderRadius: 9, padding: "0 12px", background: "var(--surface)", color: "var(--text)" } }),
          e(Button, { icon: "search", onClick: () => loadAudit(q), children: "Search" }),
          q && e(Button, { icon: "x", onClick: () => { setQ(""); loadAudit(""); }, children: "Clear" }))),
      loading && e("div", { className: "sys-note", style: { marginBottom: 12 } }, e(Icon, { name: "refresh", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), "Loading audit events from Laravel..."),
      apiState.error && e("div", { className: "sys-note", style: { marginBottom: 12, color: "var(--orange)" } }, e(Icon, { name: "alert", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), apiState.error),
      e(Card, { title: "Activity Log", sub: "newest first" },
        T([{ l: "User" }, { l: "Action" }, { l: "Detail" }, { l: "Timestamp" }, { l: "Type" }],
          logs.map(l => [user(l.actor), e("span", { className: "cell-strong" }, l.action), e("span", { className: "muted" }, l.detail), e("span", { className: "faint" }, l.time), e(Badge, { tone: tn(l.type) }, l.type)]))),
    );
  }

  Object.assign(window, { Notifications, Workflows, SiteVisits, Audit });
})();
