const React = window.React;

/* ============================================================
   Builder360 — Tasks: List view (data grid) + Calendar view
   → window.TMListView / window.TMCalendarView
   ============================================================ */
(function () {
  const { Icon, Avatar, Badge, Button, Empty } = window;
  const e = React.createElement;
  const TM = window.TM;
  const { U, PriPill, StatusPill, AvatarStack, DueChip } = window.TMUI;

  /* ================= LIST VIEW ================= */
  function TMListView({ tasks, onOpen, onUpdate, checked, setChecked, onBulk, toast }) {
    const [sort, setSort] = React.useState({ key: "due", dir: "asc" });
    const priRank = { critical: 0, high: 1, medium: 2, low: 3 };
    const taskPermissions = (task) => task?.permissions || task?.server?.permissions || {};
    const canSelectForBulk = (task) => ["can_update_status", "can_update_details", "can_archive"].some(key => taskPermissions(task)[key] === true);

    const sorted = [...tasks].sort((a, b) => {
      let av, bv;
      if (sort.key === "priority") { av = priRank[a.priority]; bv = priRank[b.priority]; }
      else if (sort.key === "progress") { av = a.progress; bv = b.progress; }
      else if (sort.key === "title") { av = a.title.toLowerCase(); bv = b.title.toLowerCase(); }
      else { av = a.number; bv = b.number; }
      if (av < bv) return sort.dir === "asc" ? -1 : 1;
      if (av > bv) return sort.dir === "asc" ? 1 : -1;
      return 0;
    });

    const toggleSort = (key) => setSort(s => ({ key, dir: s.key === key && s.dir === "asc" ? "desc" : "asc" }));
    const selectableTasks = tasks.filter(canSelectForBulk);
    const allChecked = selectableTasks.length > 0 && selectableTasks.every(t => checked.has(t.id));
    const th = (key, label, opts = {}) => e("th", { style: opts.style, onClick: opts.sortable ? () => toggleSort(key) : undefined },
      e("span", { className: "th-sort" }, label, opts.sortable && sort.key === key && e(Icon, { name: sort.dir === "asc" ? "arrowUp" : "arrowDown", size: 12 })));

    if (tasks.length === 0) return e("div", { style: { padding: 30 } }, e(Empty, { icon: "tasks", title: "No tasks here", sub: "Nothing matches this view or filter. Create a task or adjust the filters." }));

    return e("div", { className: "tm-grid-wrap" },
      e("table", { className: "tm-table" },
        e("thead", null, e("tr", null,
          e("th", { style: { width: 38 } }, e("button", { className: "tm-cb" + (allChecked ? " on" : ""), disabled: selectableTasks.length === 0, title: selectableTasks.length === 0 ? "Bulk actions are not available for this view." : "Select visible tasks for permitted bulk actions", onClick: () => { if (allChecked) setChecked(new Set()); else setChecked(new Set(selectableTasks.map(t => t.id))); } }, allChecked && e(Icon, { name: "check", size: 11 }))),
          th("title", "Task", { sortable: true }),
          th("assignees", "Assignees"),
          th("priority", "Priority", { sortable: true }),
          th("status", "Status"),
          th("progress", "Progress", { sortable: true }),
          th("due", "Due", { sortable: true }),
        )),
        e("tbody", null, sorted.map(t => {
          const isCh = checked.has(t.id);
          const canSelect = canSelectForBulk(t);
          return e("tr", { key: t.id, className: isCh ? "sel" : "", onClick: () => onOpen(t.id) },
            e("td", { onClick: ev => ev.stopPropagation() }, e("button", { className: "tm-cb" + (isCh ? " on" : ""), disabled: !canSelect, title: canSelect ? "Select task" : "Bulk actions are not available for this task.", onClick: () => setChecked(s => { const n = new Set(s); n.has(t.id) ? n.delete(t.id) : n.add(t.id); return n; }) }, isCh && e(Icon, { name: "check", size: 11 }))),
            e("td", null, e("div", { className: "tm-td-title" },
              t.priority === "critical" && e(Icon, { name: "fire", size: 14, style: { color: "var(--red)", flex: "none" } }),
              e("div", { style: { minWidth: 0 } },
                e("div", { style: { whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis", maxWidth: 360 } }, t.title),
                e("div", { className: "sub" }, t.id + " · " + t.cat + (t.subtasks.length ? " · " + t.subtasks.filter(s => s.done).length + "/" + t.subtasks.length + " subtasks" : ""))))),
            e("td", null, e(AvatarStack, { ids: t.assignees, size: 26 })),
            e("td", null, e(PriPill, { id: t.priority })),
            e("td", null, e(StatusPill, { id: t.status })),
            e("td", null, e("div", { className: "row gap-2", style: { minWidth: 110 } }, e("div", { className: "tm-prog-track", style: { width: 70 } }, e("div", { className: "tm-prog-fill", style: { width: t.progress + "%", background: t.progress === 100 ? "var(--green)" : "var(--accent)" } })), e("span", { className: "mono", style: { fontSize: 12, fontWeight: 700, color: "var(--text-3)" } }, t.progress + "%"))),
            e("td", null, e(DueChip, { due: t.due, overdue: t.overdue })));
        }))));
  }

  /* ================= CALENDAR VIEW ================= */
  function TMCalendarView({ tasks, onOpen, toast }) {
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    const shortMonths = { jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5, jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11 };
    const dow = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    const validDate = (value) => {
      if (!value) return null;
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? null : date;
    };
    const startOfDay = (date) => {
      const next = new Date(date);
      next.setHours(0, 0, 0, 0);
      return next;
    };
    const taskDueDate = (task) => {
      const serverDate = validDate(task?.dueAt || task?.due_at || task?.dueDate || task?.server?.due_at);
      if (serverDate) return startOfDay(serverDate);
      const label = String(task?.due || "").trim();
      if (!label || ["—", "-", "Overdue", "This Week"].includes(label)) return null;
      const today = startOfDay(new Date());
      if (label === "Today") return today;
      if (label === "Tomorrow") {
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);
        return tomorrow;
      }
      const match = label.match(/^(\d{1,2})\s+([A-Za-z]{3})$/);
      if (match) {
        const month = shortMonths[match[2].toLowerCase()];
        if (month !== undefined) return new Date(today.getFullYear(), month, Number(match[1]));
      }
      return null;
    };
    const initialMonth = () => {
      const firstDue = tasks.map(taskDueDate).find(Boolean) || new Date();
      return new Date(firstDue.getFullYear(), firstDue.getMonth(), 1);
    };
    const [cursor, setCursor] = React.useState(initialMonth);
    const today = startOfDay(new Date());
    const firstDow = cursor.getDay();
    const days = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
    const monthStart = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const monthEnd = new Date(cursor.getFullYear(), cursor.getMonth(), days, 23, 59, 59, 999);
    const cells = [];
    for (let i = 0; i < firstDow; i++) cells.push(null);
    for (let d = 1; d <= days; d++) cells.push(d);
    while (cells.length % 7 !== 0) cells.push(null);

    const ymd = (date) => date ? [date.getFullYear(), String(date.getMonth() + 1).padStart(2, "0"), String(date.getDate()).padStart(2, "0")].join("-") : "";
    const tasksWithDue = tasks.map(task => ({ task, dueDate: taskDueDate(task) })).filter(row => row.dueDate);
    const monthTasks = tasksWithDue.filter(row => row.dueDate >= monthStart && row.dueDate <= monthEnd);
    const tasksOn = (d) => {
      const key = ymd(new Date(cursor.getFullYear(), cursor.getMonth(), d));
      return tasksWithDue.filter(row => ymd(row.dueDate) === key).map(row => row.task);
    };
    const moveMonth = (delta) => setCursor(current => new Date(current.getFullYear(), current.getMonth() + delta, 1));
    const goToday = () => setCursor(new Date(today.getFullYear(), today.getMonth(), 1));
    const monthLabel = monthNames[cursor.getMonth()] + " " + cursor.getFullYear();

    return e("div", { className: "tm-cal" },
      e("div", { className: "row between", style: { marginBottom: 14 } },
        e("div", null,
          e("h3", { style: { fontSize: 17, fontWeight: 800, margin: 0 } }, monthLabel),
          e("div", { className: "page-sub", style: { fontSize: 12 } }, monthTasks.length + " task(s) with due dates in this month.")),
        e("div", { className: "row gap-2" },
          e("button", { className: "tm-iconbtn", "aria-label": "Previous task due month", onClick: () => moveMonth(-1) }, e(Icon, { name: "chevL", size: 16 })),
          e("button", { className: "tm-tbtn", onClick: goToday }, "Today"),
          e("button", { className: "tm-iconbtn", "aria-label": "Next task due month", onClick: () => moveMonth(1) }, e(Icon, { name: "chevR", size: 16 })))),
      e("div", { className: "tm-cal-grid" },
        dow.map(d => e("div", { key: d, className: "tm-cal-dow" }, d)),
        cells.map((d, i) => {
          if (d === null) return e("div", { key: i, className: "tm-cal-cell dim" });
          const dayTasks = tasksOn(d);
          const isToday = cursor.getFullYear() === today.getFullYear() && cursor.getMonth() === today.getMonth() && d === today.getDate();
          return e("div", { key: i, className: "tm-cal-cell" + (isToday ? " today" : "") },
            e("div", { className: "tm-cal-date" }, e("span", null, d)),
            dayTasks.slice(0, 3).map(t => { const p = TM.PR[t.priority]; return e("div", { key: t.id, className: "tm-cal-task", style: { background: p.color }, title: t.title, onClick: () => onOpen(t.id) }, t.title); }),
            dayTasks.length > 3 && e("div", { className: "faint", style: { fontSize: 10.5, fontWeight: 700, paddingLeft: 4 } }, "+" + (dayTasks.length - 3) + " more"));
        })),
      tasksWithDue.length === 0 && e("div", { style: { marginTop: 14 } }, e(Empty, { icon: "calendar", title: "No dated tasks", sub: "No task due dates are available for your selected view." })));
  }

  window.TMListView = TMListView;
  window.TMCalendarView = TMCalendarView;
})();
