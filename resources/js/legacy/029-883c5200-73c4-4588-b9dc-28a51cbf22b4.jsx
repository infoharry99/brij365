const React = window.React;

/* ============================================================
   Builder360 — Tasks: Kanban board with drag & drop
   → window.TMKanbanView
   ============================================================ */
(function () {
  const { Icon, Avatar } = window;
  const e = React.createElement;
  const TM = window.TM;
  const { U, PriPill, AvatarStack, DueChip } = window.TMUI;

  // map a task to its kanban column (status drives it)
  const colOf = (t) => t.column || (TM.ST[t.status] ? TM.ST[t.status].col : "todo");

  function TaskCard({ t, onOpen, onDragStart, onDragEnd, dragging }) {
    const subDone = t.subtasks.filter(s => s.done).length;
    return e("div", {
      className: "tm-card" + (dragging ? " dragging" : ""), draggable: true,
      onDragStart: ev => { ev.dataTransfer.effectAllowed = "move"; onDragStart(t.id); },
      onDragEnd, onClick: () => onOpen(t.id),
    },
      e("div", { className: "tm-card-top" },
        e("span", { className: "tm-card-id" }, t.id),
        t.priority === "critical" && e("span", { className: "tm-flame", style: { color: "var(--red)" } }, e(Icon, { name: "fire", size: 13 })),
        e("div", { style: { flex: 1 } }),
        e(PriPill, { id: t.priority })),
      e("div", { className: "tm-card-title" }, t.title),
      t.tags.length > 0 && e("div", { className: "tm-card-tags" }, t.tags.slice(0, 3).map(tag => e("span", { key: tag, className: "tm-tag" }, "#" + tag))),
      e("div", { className: "tm-card-foot" },
        e(AvatarStack, { ids: t.assignees, size: 24, max: 3 }),
        e("div", { style: { flex: 1 } }),
        t.subtasks.length > 0 && e("span", { className: "tm-subprog", title: "Subtasks" }, e("span", { className: "tm-miniring", style: { "--p": (subDone / t.subtasks.length * 100) } }), subDone + "/" + t.subtasks.length),
        t.comments.length > 0 && e("span", { className: "tm-card-meta" }, e(Icon, { name: "bubble", size: 13 }), t.comments.length),
        t.attachments.length > 0 && e("span", { className: "tm-card-meta" }, e(Icon, { name: "clip", size: 13 }), t.attachments.length)),
      e("div", { className: "tm-card-foot", style: { marginTop: 8 } },
        e(DueChip, { due: t.due, overdue: t.overdue }),
        e("div", { style: { flex: 1 } }),
        t.progress === 100 && e(Icon, { name: "circleCheck", size: 16, style: { color: "var(--green)" } })));
  }

  function TMKanbanView({ tasks, onOpen, onMove, onAddTo, toast }) {
    const [dragId, setDragId] = React.useState(null);
    const [overCol, setOverCol] = React.useState(null);

    const drop = (colId) => {
      if (dragId == null) return;
      const t = tasks.find(x => x.id === dragId);
      if (t && colOf(t) !== colId) onMove(dragId, colId);
      setDragId(null); setOverCol(null);
    };

    return e("div", { className: "tm-kanban" },
      TM.columns.map(col => {
        const colTasks = tasks.filter(t => colOf(t) === col.id);
        return e("div", { key: col.id, className: "tm-col" },
          e("div", { className: "tm-col-head" },
            e("span", { className: "tm-col-dot", style: { background: col.color } }),
            e("span", { className: "tm-col-title" }, col.label),
            e("span", { className: "tm-col-count" }, colTasks.length),
            e("button", { className: "tm-col-add", title: "Add task", onClick: () => onAddTo(col.id) }, e(Icon, { name: "plus", size: 15 }))),
          e("div", {
            className: "tm-col-body" + (overCol === col.id ? " dragover" : ""),
            onDragOver: ev => { ev.preventDefault(); if (overCol !== col.id) setOverCol(col.id); },
            onDragLeave: ev => { if (ev.currentTarget === ev.target) setOverCol(null); },
            onDrop: () => drop(col.id),
          },
            colTasks.length === 0 && overCol !== col.id && e("div", { style: { padding: "18px 4px", textAlign: "center", color: "var(--text-3)", fontSize: 12, fontWeight: 600 } }, "No tasks"),
            colTasks.map(t => e(TaskCard, { key: t.id, t, onOpen, dragging: dragId === t.id, onDragStart: setDragId, onDragEnd: () => { setDragId(null); setOverCol(null); } }))));
      }));
  }

  window.TMKanbanView = TMKanbanView;
})();
