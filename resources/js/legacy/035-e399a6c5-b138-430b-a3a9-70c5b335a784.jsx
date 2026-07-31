const React = window.React;

/* ============================================================
   Builder360 — Calendar Management: main shell
   summary cards · toolbar · views · drawer/modal orchestration
   → window.CalendarManagement
   ============================================================ */
(function () {
  const { Icon, Avatar, Button } = window;
  const e = React.createElement;
  const CAL = window.CAL;
  const V = window.CalViews;
  const { U, DOW, MON, fmtDate, sameDay } = window.CALUI;

  const startOfWeek = V.startOfWeek, addDays = V.addDays;
  const calendarOptions = () => window.Builder360Server?.collaboration_calendar_options || null;
  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const firstApiError = (payload) => {
    const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
    return errors[0] || payload?.message || "The calendar request could not be completed.";
  };
  const apiJson = async (url, options = {}) => {
    const response = await fetch(url, {
      ...options,
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        ...(options.headers || {}),
      },
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(firstApiError(body));
    return body;
  };
  const serverUserKey = (id) => id ? "srv-user-" + id : null;
  const serverIdFromUserKey = (id) => String(id || "").startsWith("srv-user-") ? Number(String(id).replace("srv-user-", "")) : null;
  const uiTypeToServer = (type) => ({
    appointment: "site_visit",
    followup: "payment_follow_up",
    demo: "meeting",
    call: "meeting",
    deadline: "meeting",
    internal: "meeting",
    client: "meeting",
    reminder: "meeting",
  }[type] || type || "meeting");
  const serverTypeToUi = (type) => ({
    site_visit: "appointment",
    payment_follow_up: "followup",
    interview: "meeting",
    inspection: "appointment",
    training: "internal",
  }[type] || type || "meeting");
  const normalizeStatus = (status) => status || "scheduled";
  const serverEventUrl = (template, event) => template && event?.recordId ? template.replace("__EVENT__", event.recordId) : null;
  const normalizeRelatedType = (value) => String(value || "").split("\\").pop().toLowerCase();
  const calendarCrmRoute = (event) => {
    const type = normalizeRelatedType(event?.crm?.type || event?.server?.related_type);
    return ({
      project: "projects",
      lead: "leads",
      booking: "sales",
      customer: "collections",
      buyer: "collections",
      collectionreceipt: "collections",
      collection: "collections",
      unit: "inventory",
      worktask: "tasks",
      collaborationmessage: "mailbox",
    })[type] || null;
  };
  const syncServerUsers = (options, rows = []) => {
    const seen = new Set(CAL.users.map(u => u.id));
    const add = (raw, fallbackTitle) => {
      if (!raw?.id) return null;
      const id = serverUserKey(raw.id);
      if (!seen.has(id)) {
        const colors = ["#2570eb", "#15a657", "#e08600", "#7c3aed", "#0ea5a4", "#dc2f3a", "#F6740C", "#0891b2"];
        const user = { id, serverId: raw.id, name: raw.name || raw.email || "User " + raw.id, title: raw.role || fallbackTitle || raw.email || "Team Member", dept: raw.company_id ? "Company " + raw.company_id : "Builder360", team: "Server Users", color: colors[CAL.users.length % colors.length], role: raw.role || "User" };
        CAL.users.push(user);
        CAL.U[id] = user;
        seen.add(id);
      }
      return id;
    };
    (options?.assignees || []).forEach(u => add(u, "Calendar attendee"));
    rows.forEach(row => {
      add(row.organizer, "Calendar organizer");
      (row.attendees || []).forEach(attendee => add(attendee.user || attendee, "Calendar attendee"));
    });
    const current = serverUserKey(options?.current_user_id || window.Builder360Server?.user?.id);
    if (current && CAL.U[current]) CAL.me = current;
  };
  const transformServerEvent = (row) => {
    const organizer = serverUserKey(row.organizer?.id) || CAL.me;
    const attendeeIds = (row.attendees || []).map(attendee => serverUserKey(attendee.user_id || attendee.user?.id)).filter(Boolean);
    const assignees = attendeeIds.length ? attendeeIds : [organizer].filter(Boolean);
    const reminder = Number((row.reminders || [])[0]?.minutes_before ?? 30);
    return {
      id: row.event_number || "CAL-" + row.id,
      recordId: row.id,
      title: row.title,
      type: serverTypeToUi(row.event_type),
      serverType: row.event_type,
      status: normalizeStatus(row.status),
      priority: row.metadata?.priority || "medium",
      projectId: row.project?.id || null,
      start: row.starts_at ? new Date(row.starts_at) : new Date(),
      end: row.ends_at ? new Date(row.ends_at) : new Date(),
      assignees,
      team: row.project?.name || "Server Calendar",
      crm: row.project
        ? { type: "project", recordId: row.project.id, label: row.project.name, sub: row.project.code }
        : row.related_type ? { type: normalizeRelatedType(row.related_type), recordId: row.related_id, label: normalizeRelatedType(row.related_type) + " #" + row.related_id } : null,
      location: row.location || "",
      online: row.meeting_url || null,
      desc: row.description || "",
      reminder,
      recurrence: "none",
      timezone: row.timezone || "Asia/Kolkata",
      attachments: [],
      createdBy: organizer,
      createdAt: row.created_at ? new Date(row.created_at).toLocaleDateString("en-IN", { day: "2-digit", month: "short" }) : "recently",
      collaborators: [],
      notes: "",
      server: row,
    };
  };

  function CalendarManagement({ role, toast }) {
    const options = calendarOptions();
    const hasCalendarApi = !!(options && options.index_url);
    const [events, setEvents] = React.useState(() => []);
    const [apiState, setApiState] = React.useState(() => ({ loading: !!hasCalendarApi, connected: false, error: null }));
    const [view, setView] = React.useState("month"); // month|week|day|list|employee|team
    const [cursor, setCursor] = React.useState(new Date(CAL.NOW));
    const [scope, setScope] = React.useState("all"); // all|mine|team
    const [q, setQ] = React.useState("");
    const [hiddenTypes, setHiddenTypes] = React.useState(() => new Set());
    const [quick, setQuick] = React.useState(null); // {key,label,test}
    const [advFilter, setAdvFilter] = React.useState({ status: "", priority: "", assignee: "" });
    const [filterOpen, setFilterOpen] = React.useState(false);
    const [optionsOpen, setOptionsOpen] = React.useState(() => {
      try { return window.sessionStorage?.getItem("builder360.calendar.optionsOpen") === "1"; } catch (_) { return false; }
    });
    const [fullScreen, setFullScreen] = React.useState(false);
    const [openEvent, setOpenEvent] = React.useState(null);
    const [editing, setEditing] = React.useState(null); // {} for new, event for edit, {prefill}
    const me = CAL.me, myUser = U(me);
    const canCreateEvents = !!(hasCalendarApi && options?.can_create && options?.store_url);
    const calendarApiRequiredMessage = "Calendar records are not available for your current access. Create permissions or calendar setup may be required.";

    React.useEffect(() => {
      if (!fullScreen) return undefined;
      const onKeyDown = ev => {
        if (ev.key === "Escape") setFullScreen(false);
      };
      document.addEventListener("keydown", onKeyDown);
      return () => document.removeEventListener("keydown", onKeyDown);
    }, [fullScreen]);
    React.useEffect(() => {
      try { window.sessionStorage?.setItem("builder360.calendar.optionsOpen", optionsOpen ? "1" : "0"); } catch (_) {}
    }, [optionsOpen]);

    const upd = (id, next) => setEvents(es => es.map(x => x.id === id ? next : x));
    const del = (eventOrId) => {
      const event = typeof eventOrId === "object" ? eventOrId : null;
      const id = event?.id || eventOrId;
      if (hasCalendarApi && event?.recordId && options?.delete_url_template && options?.can_delete) {
        apiJson(serverEventUrl(options.delete_url_template, event), {
          method: "DELETE",
          body: JSON.stringify({ reason: "Archived from Calendar Management screen." }),
        })
          .then(body => {
            setEvents(es => es.filter(x => x.recordId !== event.recordId && x.id !== id));
            toast("Calendar event " + (body.data?.event_number || event.id) + " archived.", "green");
          })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      if (event?.recordId) {
        toast("You do not have permission to archive this calendar event.", "orange");
        return false;
      }
      toast("Calendar archive was not saved for your current access.", "orange");
      return false;
    };
    const replaceFromServer = (row) => {
      syncServerUsers(options, [row]);
      const next = transformServerEvent(row);
      setEvents(es => es.some(x => x.recordId === next.recordId || x.id === next.id)
        ? es.map(x => (x.recordId === next.recordId || x.id === next.id) ? next : x)
        : [next, ...es]);
      return next;
    };

    React.useEffect(() => { const h = () => setFilterOpen(false); if (filterOpen) { window.addEventListener("click", h); return () => window.removeEventListener("click", h); } }, [filterOpen]);
    React.useEffect(() => {
      if (!hasCalendarApi) return;
      let alive = true;
      setApiState({ loading: true, connected: false, error: null });
      apiJson(options.index_url)
        .then(body => {
          if (!alive) return;
          const rows = body.data || [];
          syncServerUsers(options, rows);
          setEvents(rows.map(transformServerEvent));
          setApiState({ loading: false, connected: true, error: null });
        })
        .catch(error => {
          if (!alive) return;
          setEvents([]);
          setOpenEvent(null);
          setApiState({ loading: false, connected: false, error: error.message });
          toast("Calendar records could not be loaded. The workspace is read-only until records are available. " + error.message, "orange");
        });
      return () => { alive = false; };
    }, [hasCalendarApi]);
    React.useEffect(() => {
      CAL.activeEvents = events;
      return () => { CAL.activeEvents = []; };
    }, [events]);

    // ---- scope ----
    const scoped = events.filter(ev => {
      if (scope === "mine") return ev.assignees.includes(me);
      if (scope === "team") return ev.team === myUser.team;
      return true;
    });

    // ---- summary counts (respect scope) ----
    const now = CAL.NOW;
    const counts = {
      today: scoped.filter(ev => sameDay(ev.start, now) && ev.status !== "cancelled").length,
      upcoming: scoped.filter(ev => ev.start > now && ["scheduled", "pending", "rescheduled"].includes(ev.status)).length,
      followup: scoped.filter(ev => ev.type === "followup" && !["completed", "cancelled"].includes(ev.status)).length,
      completed: scoped.filter(ev => ev.status === "completed").length,
      missed: scoped.filter(ev => ev.status === "missed").length,
      overdue: scoped.filter(ev => ev.status === "overdue").length,
    };

    // ---- filtering pipeline ----
    const filtered = scoped.filter(ev => {
      if (hiddenTypes.has(ev.type)) return false;
      if (advFilter.status && ev.status !== advFilter.status) return false;
      if (advFilter.priority && ev.priority !== advFilter.priority) return false;
      if (advFilter.assignee && !ev.assignees.includes(advFilter.assignee)) return false;
      if (quick && !quick.test(ev)) return false;
      if (q.trim()) {
        const hay = (ev.title + " " + (ev.desc || "") + " " + (ev.location || "") + " " + (ev.crm ? ev.crm.label : "") + " " + ev.assignees.map(a => U(a).name).join(" ")).toLowerCase();
        if (!hay.includes(q.toLowerCase())) return false;
      }
      return true;
    });

    // ---- date nav ----
    const step = (dir) => {
      const c = new Date(cursor);
      if (view === "month" || view === "list") c.setMonth(c.getMonth() + dir);
      else if (view === "day") c.setDate(c.getDate() + dir);
      else c.setDate(c.getDate() + dir * 7);
      setCursor(c);
    };
    const periodLabel = () => {
      if (view === "month" || view === "list") return MON[cursor.getMonth()] + " " + cursor.getFullYear();
      if (view === "day") return DOW[cursor.getDay()] + ", " + cursor.getDate() + " " + MON[cursor.getMonth()];
      const ws = startOfWeek(cursor), we = addDays(ws, 6);
      return ws.getDate() + " " + MON[ws.getMonth()] + " – " + we.getDate() + " " + MON[we.getMonth()] + " " + we.getFullYear();
    };

    // ---- create / save ----
    const saveEvent = (data, id) => {
      if (id) {
        const existing = events.find(x => x.id === id);
        if (hasCalendarApi && existing?.recordId && options.update_url_template) {
          const attendeeIds = (data.assignees || []).map(serverIdFromUserKey).filter(Boolean);
          apiJson(serverEventUrl(options.update_url_template, existing), {
            method: "PATCH",
            body: JSON.stringify({
              title: data.title,
              description: data.desc || undefined,
              event_type: uiTypeToServer(data.type),
              starts_at: data.start.toISOString(),
              ends_at: data.end.toISOString(),
              timezone: data.timezone || "Asia/Kolkata",
              project_id: data.projectId || existing.projectId || undefined,
              location: data.location || undefined,
              meeting_url: data.online || undefined,
              visibility: "internal",
              attendees: attendeeIds.map(user_id => ({ user_id, response: "pending" })),
              reminders: [{ minutes_before: Number(data.reminder || 30) }],
              related_type: existing.server?.related_type || undefined,
              related_id: existing.server?.related_id || undefined,
              note: "Calendar event updated from Calendar Management screen.",
              metadata: {
                source: "calendar_management_screen",
                priority: data.priority || "medium",
                ui_event_type: data.type,
                team: data.team || null,
              },
            }),
          })
            .then(body => { replaceFromServer(body.data); setApiState(s => ({ ...s, connected: true, error: null })); toast("Calendar event " + body.data.event_number + " updated.", "green"); })
            .catch(error => toast(error.message, "red"));
          return true;
        }
        toast("Calendar event update was not saved for your current access.", "orange");
        return false;
      }

      if (canCreateEvents) {
        const attendeeIds = (data.assignees || []).map(serverIdFromUserKey).filter(Boolean);
        apiJson(options.store_url, {
          method: "POST",
          body: JSON.stringify({
            title: data.title,
            description: data.desc || undefined,
            event_type: uiTypeToServer(data.type),
            starts_at: data.start.toISOString(),
            ends_at: data.end.toISOString(),
            timezone: "Asia/Kolkata",
            location: data.location || undefined,
            meeting_url: data.online || undefined,
            visibility: "internal",
            attendees: attendeeIds.map(user_id => ({ user_id, response: "pending" })),
            reminders: [{ minutes_before: Number(data.reminder || 30) }],
            metadata: {
              source: "calendar_management_screen",
              priority: data.priority || "medium",
              ui_event_type: data.type,
              team: data.team || null,
            },
          }),
        })
          .then(body => { replaceFromServer(body.data); setApiState(s => ({ ...s, connected: true, error: null })); toast("Calendar event " + body.data.event_number + " saved.", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }

      toast(hasCalendarApi ? "You do not have permission to create calendar events." : calendarApiRequiredMessage, hasCalendarApi ? "red" : "orange");
      return false;
    };
    const cancelEvent = (event) => {
      if (hasCalendarApi && event?.recordId && options.cancel_url_template) {
        apiJson(serverEventUrl(options.cancel_url_template, event), {
          method: "PATCH",
          body: JSON.stringify({ reason: "Cancelled from Calendar Management screen." }),
        })
          .then(body => { replaceFromServer(body.data); toast("Calendar event " + body.data.event_number + " cancelled.", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      toast("Calendar cancellation was not saved for your current access.", "orange");
      return false;
    };
    const completeEvent = (event) => {
      if (hasCalendarApi && event?.recordId && options.complete_url_template) {
        apiJson(serverEventUrl(options.complete_url_template, event), {
          method: "PATCH",
          body: JSON.stringify({ note: "Completed from Calendar Management screen." }),
        })
          .then(body => { replaceFromServer(body.data); toast("Calendar event " + body.data.event_number + " completed.", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      toast("Calendar completion was not saved for your current access.", "orange");
      return false;
    };
    const openCreateEvent = (prefill = null) => {
      if (!canCreateEvents) {
        toast(hasCalendarApi ? "You do not have permission to create calendar events." : calendarApiRequiredMessage, hasCalendarApi ? "red" : "orange");
        return;
      }
      setEditing(prefill ? { prefill } : {});
    };
    const openSlot = (date) => openCreateEvent({ start: date, end: new Date(date.getTime() + 60 * 60000) });
    const openCalendarCrmRecord = (event) => {
      const route = calendarCrmRoute(event);
      if (!route) {
        toast("No Builder360 module route is configured for this linked calendar record.", "orange");
        return false;
      }
      if (window.Builder360Navigate) window.Builder360Navigate(route);
      else window.dispatchEvent(new CustomEvent("builder360:navigate", { detail: { route } }));
      toast("Opened linked " + (event.crm?.type || "CRM") + " record in " + route + " module.", "green");
      return true;
    };

    // ---- summary card ----
    const sumCard = (key, label, n, icon, tone, test) => {
      const [c, bg] = { accent: ["var(--accent)", "var(--accent-soft)"], green: ["var(--green)", "var(--green-soft)"], orange: ["var(--orange)", "var(--orange-soft)"], red: ["var(--red)", "var(--red-soft)"], blue: ["var(--blue)", "var(--blue-soft)"], violet: ["var(--violet)", "var(--violet-soft)"] }[tone];
      const active = quick && quick.key === key;
      return e("div", { className: "cal-sum" + (active ? " on" : ""), onClick: () => { if (active) { setQuick(null); } else { setQuick({ key, label, test }); setView("list"); } } },
        e("div", { className: "cal-sum-ic", style: { background: bg, color: c } }, e(Icon, { name: icon, size: 18 })),
        e("div", null, e("div", { className: "cal-sum-n" }, n), e("div", { className: "cal-sum-l" }, label)));
    };

    const views = [["month", "Month", "grid"], ["week", "Week", "columns"], ["day", "Day", "calClock"], ["list", "List", "listview"], ["employee", "Employee", "users"], ["team", "Team", "building"]];

    const viewBody = () => {
      const props = { cursor, events: filtered, onOpen: setOpenEvent, onSlot: openSlot };
      if (view === "month") return e(V.MonthView, props);
      if (view === "week") return e(V.WeekView, props);
      if (view === "day") return e(V.DayView, props);
      if (view === "list") return e(V.ListView, { events: filtered, onOpen: setOpenEvent });
      if (view === "employee") return e(V.EmployeeView, props);
      if (view === "team") return e(V.TeamView, props);
    };

    const anyAdv = advFilter.status || advFilter.priority || advFilter.assignee;

    const statusNote = apiState.connected
      ? "Calendar events are saved and shared with participants."
      : apiState.loading
        ? "Loading calendar events..."
        : calendarApiRequiredMessage + " " + (apiState.error || "Calendar records are not available for this session.");
    const showCompactWarning = !optionsOpen && !apiState.loading && !apiState.connected;

    return e("div", { className: "cal" + (fullScreen ? " cal-fullscreen" : "") },
      e("div", { className: "cal-primarybar" },
        e("div", { className: "cal-navbtns" },
          e("button", { title: "Previous", onClick: () => step(-1) }, e(Icon, { name: "chevL", size: 16 })),
          e("button", { title: "Next", onClick: () => step(1) }, e(Icon, { name: "chevR", size: 16 }))),
        e("button", { className: "cal-today-btn", onClick: () => setCursor(new Date(CAL.NOW)) }, "Today"),
        e("div", { className: "cal-period" }, periodLabel()),
        e("div", { style: { flex: 1 } }),
        e("button", { className: "cal-control-btn", "aria-expanded": optionsOpen ? "true" : "false", onClick: () => { setOptionsOpen(open => !open); setFilterOpen(false); } }, e(Icon, { name: "chevD", size: 15, style: { transform: optionsOpen ? "rotate(180deg)" : "none", transition: ".15s" } }), optionsOpen ? "Hide options" : "Show options"),
        e("button", { className: "cal-control-btn", onClick: () => setFullScreen(v => !v) }, e(Icon, { name: fullScreen ? "x" : "expand", size: 15 }), fullScreen ? "Exit Full Screen" : "Full Screen"),
        e("button", { className: "cal-new", disabled: !canCreateEvents, title: canCreateEvents ? "Create calendar event" : "Calendar creation is not available for this role", onClick: () => openCreateEvent() }, e(Icon, { name: "plus", size: 16 }), "New event")),

      showCompactWarning && e("div", { className: "cal-compact-warning" }, e(Icon, { name: "alert", size: 14 }), statusNote),

      e("div", { className: "cal-options-panel" + (optionsOpen ? "" : " closed") },
        e("div", { className: "sys-note", style: { margin: "0" } }, e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), statusNote),
        e("div", { className: "cal-summary" },
          sumCard("today", "Today's events", counts.today, "calClock", "accent", ev => sameDay(ev.start, now)),
          sumCard("upcoming", "Upcoming", counts.upcoming, "trend", "blue", ev => ev.start > now && ["scheduled", "pending", "rescheduled"].includes(ev.status)),
          sumCard("followup", "Pending follow-ups", counts.followup, "reply", "violet", ev => ev.type === "followup" && !["completed", "cancelled"].includes(ev.status)),
          sumCard("completed", "Completed", counts.completed, "check", "green", ev => ev.status === "completed"),
          sumCard("missed", "Missed", counts.missed, "x", "red", ev => ev.status === "missed"),
          sumCard("overdue", "Overdue", counts.overdue, "alert", "orange", ev => ev.status === "overdue")),

        e("div", { className: "cal-toolbar" },
        e("div", { style: { flex: 1 } }),
        // scope toggle
        e("div", { className: "cal-viewseg" },
          e("button", { className: scope === "all" ? "on" : "", onClick: () => setScope("all"), title: "All events (admin)" }, "All"),
          e("button", { className: scope === "team" ? "on" : "", onClick: () => setScope("team"), title: "My team" }, "Team"),
          e("button", { className: scope === "mine" ? "on" : "", onClick: () => setScope("mine"), title: "My events" }, "Mine")),
        e("div", { className: "cal-search" }, e(Icon, { name: "search", size: 15 }), e("input", { value: q, placeholder: "Search events…", onChange: ev => setQ(ev.target.value) }), q && e("button", { onClick: () => setQ(""), style: { border: "none", background: "none", color: "var(--text-3)", cursor: "pointer", display: "grid" } }, e(Icon, { name: "x", size: 13 }))),
        e("div", { style: { position: "relative" } },
          e("button", { className: "tm-tbtn" + (anyAdv ? " on" : ""), onClick: ev => { ev.stopPropagation(); setFilterOpen(o => !o); } }, e(Icon, { name: "filter", size: 15 }), "Filters", anyAdv && e("span", { style: { width: 7, height: 7, borderRadius: 99, background: "var(--accent)" } })),
          filterOpen && e("div", { className: "tm-menu", style: { top: 42, right: 0, width: 230, padding: 12 }, onClick: ev => ev.stopPropagation() },
            e("div", { className: "tm-field", style: { marginBottom: 10 } }, e("label", { style: { fontSize: 11, fontWeight: 700, color: "var(--text-2)", display: "block", marginBottom: 5 } }, "Status"), e("select", { className: "tm-select", value: advFilter.status, onChange: ev => setAdvFilter(s => ({ ...s, status: ev.target.value })) }, e("option", { value: "" }, "Any status"), CAL.statuses.map(s => e("option", { key: s.id, value: s.id }, s.label)))),
            e("div", { className: "tm-field", style: { marginBottom: 10 } }, e("label", { style: { fontSize: 11, fontWeight: 700, color: "var(--text-2)", display: "block", marginBottom: 5 } }, "Priority"), e("select", { className: "tm-select", value: advFilter.priority, onChange: ev => setAdvFilter(s => ({ ...s, priority: ev.target.value })) }, e("option", { value: "" }, "Any priority"), CAL.priorities.map(p => e("option", { key: p.id, value: p.id }, p.label)))),
            e("div", { className: "tm-field", style: { marginBottom: 12 } }, e("label", { style: { fontSize: 11, fontWeight: 700, color: "var(--text-2)", display: "block", marginBottom: 5 } }, "Employee"), e("select", { className: "tm-select", value: advFilter.assignee, onChange: ev => setAdvFilter(s => ({ ...s, assignee: ev.target.value })) }, e("option", { value: "" }, "Anyone"), CAL.users.map(u => e("option", { key: u.id, value: u.id }, u.name)))),
            e(Button, { sm: true, onClick: () => { setAdvFilter({ status: "", priority: "", assignee: "" }); setFilterOpen(false); }, children: "Clear filters" }))),
        e("div", { className: "cal-viewseg" }, views.map(([id, label, icon]) => e("button", { key: id, className: view === id ? "on" : "", onClick: () => setView(id) }, e(Icon, { name: icon, size: 14 }), label)))),

        e("div", { className: "cal-legend" },
          CAL.types.filter(t => !/^sam/i.test(String(t.label || t.id || ""))).map(t => e("div", { key: t.id, className: "cal-legend-item" + (hiddenTypes.has(t.id) ? " off" : ""), onClick: () => setHiddenTypes(s => { const n = new Set(s); n.has(t.id) ? n.delete(t.id) : n.add(t.id); return n; }) },
            e("span", { className: "cal-legend-dot", style: { background: t.color } }), t.label)),
          quick && e("div", { style: { marginLeft: "auto" }, className: "row gap-2" },
            e("span", { className: "tm-people-chip", style: { background: "var(--accent-soft)", color: "var(--accent)" } }, "Showing: " + quick.label, e("button", { onClick: () => setQuick(null), style: { border: "none", background: "none", color: "inherit", cursor: "pointer", display: "grid" } }, e(Icon, { name: "x", size: 12 })))))),

      // body
      e("div", { className: "cal-body" }, viewBody()),

      // overlays
      openEvent && e(window.CalDrawer, { event: events.find(x => x.id === openEvent.id) || openEvent, onClose: () => setOpenEvent(null), onEdit: (ev) => { setOpenEvent(null); setEditing(ev); }, onUpdate: upd, onDelete: del, onComplete: completeEvent, onCancel: cancelEvent, onOpenCrmRecord: openCalendarCrmRecord, toast }),
      editing && e(window.CalModal, { event: editing.id ? editing : null, prefill: editing.prefill || null, onClose: () => setEditing(null), onSave: saveEvent, toast }));
  }

  window.CalendarManagement = CalendarManagement;
})();
