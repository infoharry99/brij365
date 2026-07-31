const React = window.React;

/* ============================================================
   Builder360 — Calendar views: month, week, day, list,
   employee, team  → window.CalViews.*
   ============================================================ */
(function () {
  const { Icon, Avatar } = window;
  const e = React.createElement;
  const CAL = window.CAL;
  const { U, DOW, MON, fmtTime, fmtTimeRange, fmtDate, sameDay, durMin } = window.CALUI;

  const START_HR = 7, END_HR = 21, ROW_H = 52;
  const startOfWeek = (d) => { const x = new Date(d); x.setDate(x.getDate() - x.getDay()); x.setHours(0, 0, 0, 0); return x; };
  const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };

  /* ---- shared: small event chip color helper ---- */
  const tColor = (ev) => CAL.T[ev.type].color;

  /* ================= MONTH ================= */
  function MonthView({ cursor, events, onOpen, onSlot }) {
    const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const gridStart = startOfWeek(first);
    const cells = Array.from({ length: 42 }, (_, i) => addDays(gridStart, i));
    const today = CAL.NOW;
    return e("div", { className: "cal-month" },
      e("div", { className: "cal-month-grid" },
        DOW.map(d => e("div", { key: d, className: "cal-dow" }, d)),
        cells.map((day, i) => {
          const dayEvents = events.filter(ev => sameDay(ev.start, day)).sort((a, b) => a.start - b.start);
          const dim = day.getMonth() !== cursor.getMonth();
          return e("div", { key: i, className: "cal-cell" + (dim ? " dim" : "") + (sameDay(day, today) ? " today" : ""), onClick: () => onSlot(new Date(day.getFullYear(), day.getMonth(), day.getDate(), 11, 0)) },
            e("div", { className: "cal-cell-date" }, day.getDate()),
            dayEvents.slice(0, 3).map(ev => { const c = tColor(ev); return e("div", { key: ev.id, className: "cal-ev" + (ev.status === "completed" ? " done" : ""), style: { background: c + "1a", color: c, borderLeftColor: c }, onClick: evt => { evt.stopPropagation(); onOpen(ev); }, title: ev.title },
              e("span", { className: "cal-ev-time" }, fmtTime(ev.start)), e("span", { style: { overflow: "hidden", textOverflow: "ellipsis" } }, ev.title)); }),
            dayEvents.length > 3 && e("div", { className: "cal-ev-more", onClick: evt => { evt.stopPropagation(); onOpen(dayEvents[3]); } }, "+" + (dayEvents.length - 3) + " more"));
        })));
  }

  /* ================= TIME GRID (week + day) ================= */
  function packDay(dayEvents) {
    const sorted = [...dayEvents].sort((a, b) => a.start - b.start);
    const colEnds = [];
    sorted.forEach(ev => {
      let placed = false;
      for (let i = 0; i < colEnds.length; i++) { if (colEnds[i] <= ev.start) { ev._col = i; colEnds[i] = ev.end; placed = true; break; } }
      if (!placed) { ev._col = colEnds.length; colEnds.push(ev.end); }
    });
    const total = colEnds.length || 1;
    sorted.forEach(ev => ev._total = total);
    return sorted;
  }
  function evTop(ev) { const h = ev.start.getHours() + ev.start.getMinutes() / 60; return Math.max(0, (h - START_HR) * ROW_H); }
  function evHeight(ev) { return Math.max(20, durMin(ev.start, ev.end) / 60 * ROW_H - 3); }

  function TimeGrid({ days, events, onOpen, onSlot }) {
    const hours = []; for (let h = START_HR; h <= END_HR; h++) hours.push(h);
    const today = CAL.NOW;
    const showNow = days.some(d => sameDay(d, today));
    const nowTop = (today.getHours() + today.getMinutes() / 60 - START_HR) * ROW_H;
    const cols = "60px repeat(" + days.length + ", 1fr)";
    return e("div", { className: "cal-timegrid" },
      e("div", { className: "cal-tg-header", style: { gridTemplateColumns: cols } },
        e("div", { className: "cal-tg-corner" }),
        days.map((d, i) => e("div", { key: i, className: "cal-tg-daycol" + (sameDay(d, today) ? " today" : "") },
          e("div", { className: "dow" }, DOW[d.getDay()]), e("div", { className: "dnum" }, d.getDate())))),
      e("div", { className: "cal-tg-scroll" },
        hours.map((h, hi) => e("div", { key: h, className: "cal-tg-row", style: { gridTemplateColumns: cols } },
          e("div", { className: "cal-tg-time" }, (h % 12 || 12) + (h < 12 ? " AM" : " PM")),
          days.map((d, di) => e("div", { key: di, className: "cal-tg-slot", onClick: () => onSlot(new Date(d.getFullYear(), d.getMonth(), d.getDate(), h, 0)) })))),
        // events overlay
        days.map((d, di) => {
          const dayEvents = packDay(events.filter(ev => sameDay(ev.start, d)));
          const leftBase = "calc(60px + " + di + " * ((100% - 60px) / " + days.length + "))";
          const colW = "((100% - 60px) / " + days.length + ")";
          return dayEvents.map(ev => {
            const c = tColor(ev);
            const w = "calc(" + colW + " / " + ev._total + " - 4px)";
            const left = "calc(" + leftBase + " + " + ev._col + " * (" + colW + " / " + ev._total + "))";
            const cancelled = ev.status === "cancelled";
            return e("div", { key: ev.id, className: "cal-tg-event", style: { top: evTop(ev), height: evHeight(ev), left, width: w, background: c, borderLeftColor: "rgba(0,0,0,.22)", opacity: cancelled ? .5 : 1, textDecoration: cancelled ? "line-through" : "none" }, onClick: () => onOpen(ev), title: ev.title },
              e("div", { className: "et" }, fmtTime(ev.start)),
              e("div", { style: { whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis", fontWeight: 700 } }, ev.title));
          });
        }),
        showNow && nowTop >= 0 && e("div", { className: "cal-now-line", style: { top: nowTop } })));
  }
  function WeekView({ cursor, events, onOpen, onSlot }) {
    const ws = startOfWeek(cursor);
    const days = Array.from({ length: 7 }, (_, i) => addDays(ws, i));
    return e(TimeGrid, { days, events, onOpen, onSlot });
  }
  function DayView({ cursor, events, onOpen, onSlot }) {
    return e(TimeGrid, { days: [new Date(cursor)], events, onOpen, onSlot });
  }

  /* ================= LIST ================= */
  function ListView({ events, onOpen }) {
    const sorted = [...events].sort((a, b) => a.start - b.start);
    // group by day
    const groups = [];
    sorted.forEach(ev => {
      const key = ev.start.toDateString();
      let g = groups.find(x => x.key === key);
      if (!g) { g = { key, date: ev.start, items: [] }; groups.push(g); }
      g.items.push(ev);
    });
    if (groups.length === 0) return e("div", { style: { padding: 40 } }, e(window.Empty, { icon: "calClock", title: "No events", sub: "Nothing matches the current filters." }));
    return e("div", { className: "cal-list" }, groups.map(g =>
      e("div", { key: g.key },
        e("div", { className: "cal-list-day" }, sameDay(g.date, CAL.NOW) ? "Today · " : "", fmtDate(g.date)),
        g.items.map(ev => { const c = tColor(ev); const s = CAL.ST[ev.status]; return e("div", { key: ev.id, className: "cal-list-row", onClick: () => onOpen(ev) },
          e("div", { className: "cal-list-time" }, e("div", { className: "t1" }, fmtTime(ev.start)), e("div", { className: "t2" }, durMin(ev.start, ev.end) + " min")),
          e("div", { className: "cal-list-bar", style: { background: c } }),
          e("div", { className: "cal-list-main" },
            e("div", { className: "cal-list-title" }, ev.title),
            e("div", { className: "cal-list-meta" },
              e("span", { className: "m", style: { color: c, fontWeight: 700 } }, e(Icon, { name: CAL.T[ev.type].icon, size: 13 }), CAL.T[ev.type].label),
              ev.assignees[0] && e("span", { className: "m" }, e(Avatar, { name: U(ev.assignees[0]).name, color: U(ev.assignees[0]).color, size: 16 }), U(ev.assignees[0]).name.split(" ")[0] + (ev.assignees.length > 1 ? " +" + (ev.assignees.length - 1) : "")),
              ev.crm && e("span", { className: "m" }, e(Icon, { name: "link", size: 12 }), ev.crm.label),
              ev.online && e("span", { className: "m" }, e(Icon, { name: "video", size: 12 }), "Online"),
              ev.location && e("span", { className: "m" }, e(Icon, { name: "mapPin", size: 12 }), ev.location))),
          e(window.Badge, { tone: s.badge, dot: true }, s.label)); }))));
  }

  /* ================= EMPLOYEE / TEAM LANES ================= */
  function LanesView({ cursor, events, onOpen, groupBy }) {
    const ws = startOfWeek(cursor);
    const days = Array.from({ length: 7 }, (_, i) => addDays(ws, i));
    let lanes;
    if (groupBy === "team") {
      const teams = [...new Set(CAL.users.map(u => u.team))];
      lanes = teams.map(t => ({ id: t, name: t, sub: (CAL.departments.find(d => d.name === CAL.users.find(u => u.team === t).dept) || {}).name || "", color: "#F6740C",
        match: (ev) => ev.team === t }));
    } else {
      lanes = CAL.users.filter(u => events.some(ev => ev.assignees.includes(u.id))).map(u => ({ id: u.id, name: u.name, sub: u.title, color: u.color, av: u, match: (ev) => ev.assignees.includes(u.id) }));
    }
    return e("div", { className: "cal-lanes" },
      e("div", { className: "cal-lanes-dow" }, days.map((d, i) => e("div", { key: i, className: sameDay(d, CAL.NOW) ? "today" : "" }, DOW[d.getDay()] + " " + d.getDate()))),
      lanes.map(lane => e("div", { key: lane.id, className: "cal-lane" },
        e("div", { className: "cal-lane-head" },
          lane.av ? e(Avatar, { name: lane.av.name, color: lane.av.color, size: 30 }) : e("div", { style: { width: 30, height: 30, borderRadius: 9, background: lane.color + "1f", color: lane.color, display: "grid", placeItems: "center", flex: "none" } }, e(Icon, { name: "users", size: 15 })),
          e("div", { style: { minWidth: 0 } }, e("div", { className: "cal-lane-name" }, lane.name), e("div", { className: "cal-lane-sub" }, lane.sub))),
        e("div", { className: "cal-lane-track" }, days.map((d, di) => {
          const cellEvents = events.filter(ev => lane.match(ev) && sameDay(ev.start, d)).sort((a, b) => a.start - b.start);
          return e("div", { key: di, className: "cal-lane-cell" + (sameDay(d, CAL.NOW) ? " today" : "") },
            cellEvents.slice(0, 4).map(ev => { const c = tColor(ev); return e("div", { key: ev.id, className: "cal-lane-pill", style: { background: c, opacity: ev.status === "cancelled" ? .5 : 1 }, onClick: () => onOpen(ev), title: ev.title }, fmtTime(ev.start) + " " + ev.title); }),
            cellEvents.length > 4 && e("div", { className: "cal-ev-more" }, "+" + (cellEvents.length - 4)));
        })))));
  }

  window.CalViews = {
    MonthView, WeekView, DayView, ListView,
    EmployeeView: (p) => e(LanesView, Object.assign({}, p, { groupBy: "employee" })),
    TeamView: (p) => e(LanesView, Object.assign({}, p, { groupBy: "team" })),
    startOfWeek, addDays,
  };
})();
