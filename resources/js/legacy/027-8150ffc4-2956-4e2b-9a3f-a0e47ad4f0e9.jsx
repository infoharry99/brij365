const React = window.React;

/* ============================================================
   Builder360 — Tasks: shared UI helpers + detail drawer,
   create modal, transfer modal  → window.TMUI / window.TMDrawer ...
   ============================================================ */
(function () {
  const { Icon, Avatar, Badge, Button } = window;
  const e = React.createElement;
  const TM = window.TM;

  // ---------- shared UI helpers ----------
  const U = (id) => TM.U[id] || { name: "—", color: "#94a3b8" };
  function PriPill({ id }) {
    const p = TM.PR[id]; if (!p) return null;
    return e("span", { className: "tm-pri", style: { background: p.color + "1f", color: p.color } },
      id === "critical" ? e(Icon, { name: "fire", size: 12 }) : e("span", { className: "pdot", style: { background: p.color } }), p.label);
  }
  function StatusPill({ id }) { const s = TM.ST[id]; return s ? e(Badge, { tone: s.badge, dot: true }, s.label) : null; }
  function AvatarStack({ ids, size = 26, max = 4 }) {
    const shown = ids.slice(0, max), extra = ids.length - shown.length;
    return e("div", { className: "tm-avstack" },
      shown.map(id => e(Avatar, { key: id, name: U(id).name, color: U(id).color, size })),
      extra > 0 && e("div", { className: "avatar", style: { width: size, height: size, flexBasis: size, background: "var(--surface-3)", color: "var(--text-2)", fontSize: 10 } }, "+" + extra));
  }
  function DueChip({ due, overdue, small }) {
    if (!due || due === "—") return e("span", { className: "tm-card-meta" }, "—");
    const cls = overdue || due === "Overdue" ? " due-over" : due === "Today" ? " due-today" : "";
    return e("span", { className: "tm-card-meta" + cls }, e(Icon, { name: overdue ? "alert" : "calendar", size: 13 }), due);
  }
  function MentionText({ text }) {
    // replace @u-id tokens with names
    const parts = [];
    let s = text;
    TM.users.forEach(u => { s = s.split("@" + u.id).join("@@" + u.name + "@@"); });
    s.split("@@").forEach((chunk, i) => {
      if (i % 2 === 1) parts.push(e("span", { key: i, className: "mention" }, "@" + chunk));
      else if (chunk) parts.push(chunk);
    });
    return e("span", { className: "tm-cmt-text" }, parts);
  }
  window.TMUI = { U, PriPill, StatusPill, AvatarStack, DueChip, MentionText };

  // ---------- People picker dropdown ----------
  function PeoplePicker({ selected, onToggle, onClose, multi = true }) {
    const selectedIds = Array.isArray(selected) ? selected : [];
    return e("div", { className: "tm-menu people-search-menu", style: { top: 30, left: 0, minWidth: 280 } },
      e(window.SearchablePeoplePicker, {
        items: TM.users,
        selected: selectedIds,
        mode: multi ? "multi" : "single",
        placeholder: "Search employee name...",
        emptyText: "No matching employees",
        onChange: value => {
          if (multi) {
            const next = new Set((Array.isArray(value) ? value : []).map(String));
            const previous = new Set(selectedIds.map(String));
            const changed = TM.users.find(user => next.has(String(user.id)) !== previous.has(String(user.id)));
            if (changed) onToggle(changed.id);
          } else {
            onToggle(value);
            onClose && onClose();
          }
        },
        getId: user => user.id,
        getLabel: user => user.name,
        getSubLabel: user => [user.title, user.dept || user.team, user.email].filter(Boolean).join(" · "),
      }));
  }

  /* ================= TASK DRAWER ================= */
  function TaskDrawer({ task, availableTasks = [], onClose, onTaskDeepLink, onOpenCrmRecord, onTaskUpdate, onTaskArchive, onStatusChange, onChecklistSave, onCommentCreate, onSubtaskCreate, onSubtaskStatusChange, onTimeLogCreate, onAssigneeChange, onWatcherToggle, onDependenciesSave, onTransfer, toast }) {
    const [tab, setTab] = React.useState("details");
    const [menu, setMenu] = React.useState(null); // status | pri | assignee | watcher | more
    const [newSub, setNewSub] = React.useState("");
    const [newChk, setNewChk] = React.useState("");
    const [depSelection, setDepSelection] = React.useState("");
    const [addingSub, setAddingSub] = React.useState(false);
    const [comment, setComment] = React.useState("");
    const [timer, setTimer] = React.useState({ running: false, sec: 0 });
    const me = TM.me;

    React.useEffect(() => {
      if (!timer.running) return;
      const t = setInterval(() => setTimer(s => ({ ...s, sec: s.sec + 1 })), 1000);
      return () => clearInterval(t);
    }, [timer.running]);
    React.useEffect(() => {
      const h = () => setMenu(null);
      if (menu) { window.addEventListener("click", h); return () => window.removeEventListener("click", h); }
    }, [menu]);

    const fmtTimer = (s) => [Math.floor(s / 3600), Math.floor(s / 60) % 60, s % 60].map(n => String(n).padStart(2, "0")).join(":");
    const unavailable = (message) => {
      toast && toast(message, "orange");
      return false;
    };
    const missingDetailApi = (action) => unavailable(action + " was not saved for your current access.");

    const toggleSub = (sid) => {
      const selected = task.subtasks.find(s => s.id === sid);
      if (!selected) return;
      const nextStatus = selected.done ? "open" : "completed";
      if (onSubtaskStatusChange && onSubtaskStatusChange(task, selected, nextStatus, "Subtask toggled from Task Management drawer.")) return;
      missingDetailApi("Subtask status change");
    };
    const updateChecklist = (nextChecklist, note) => {
      if (onChecklistSave && onChecklistSave(task, nextChecklist, note)) return true;
      missingDetailApi("Checklist update");
      return false;
    };
    const toggleChk = (cid) => updateChecklist(task.checklist.map(c => c.id === cid ? { ...c, done: !c.done } : c), "Checklist item toggled from Task Management drawer.");
    const addSub = () => {
      const title = newSub.trim();
      if (!title) return;
      if (onSubtaskCreate && onSubtaskCreate(task, title)) { setNewSub(""); setAddingSub(false); return; }
      missingDetailApi("Subtask creation");
    };
    const addChk = () => { if (!newChk.trim()) return; if (updateChecklist([...task.checklist, { id: task.id + "-c" + Date.now(), text: newChk.trim(), done: false }], "Checklist item added from Task Management drawer.")) setNewChk(""); };
    const postComment = () => {
      if (!comment.trim()) return;
      const mentions = TM.users.filter(u => comment.includes("@" + u.name.split(" ")[0])).map(u => u.id);
      let text = comment; TM.users.forEach(u => { text = text.split("@" + u.name.split(" ")[0]).join("@" + u.id); });
      if (onCommentCreate && onCommentCreate(task, text, mentions)) { setComment(""); return; }
      missingDetailApi("Comment creation");
    };
    const setStatus = (sid) => {
      if (onStatusChange) {
        onStatusChange(task, sid, "Status changed to " + TM.ST[sid].label + " from Task Management drawer.");
        setMenu(null);
        return;
      }
      setMenu(null);
      missingDetailApi("Task status change");
    };
    const setPri = (pid) => {
      if (onTaskUpdate && onTaskUpdate(task, { priority: pid }, "Priority changed to " + TM.PR[pid].label + " from Task Management drawer.")) { setMenu(null); return; }
      setMenu(null);
      missingDetailApi("Task priority change");
    };
    const toggleAssignee = (uid) => {
      const has = task.assignees.includes(uid);
      if (has) {
        toast("Tasks use one accountable assignee. Choose another person or use Transfer task.", "orange");
        setMenu(null);
        return;
      }
      if (onAssigneeChange && onAssigneeChange(task, uid, "Assignee changed from Task Management drawer.")) {
        setMenu(null);
        return;
      }
      setMenu(null);
      missingDetailApi("Task assignee change");
    };
    const saveTime = () => {
      if (timer.sec < 1) return;
      const minutes = Math.max(1, Math.round(timer.sec / 60));
      if (onTimeLogCreate && onTimeLogCreate(task, minutes, "Timer session")) { setTimer({ running: false, sec: 0 }); return; }
      missingDetailApi("Task time log");
    };
    const dependencyRecordIds = () => (task.deps.dependsOn || []).map(dep => dep.recordId).filter(Boolean);
    const dependencyChoices = availableTasks.filter(candidate =>
      candidate.recordId
      && candidate.recordId !== task.recordId
      && !dependencyRecordIds().includes(candidate.recordId)
      && candidate.status !== "cancelled"
    );
    const selectedDependency = dependencyChoices.find(candidate => String(candidate.recordId) === String(depSelection));
    const canEditTask = !!onTaskUpdate;
    const canEditStatus = !!onStatusChange;
    const canEditPriority = !!onTaskUpdate;
    const canEditDependencies = !!onDependenciesSave;
    const DetailCard = ({ title, icon, children, className = "" }) => e("section", { className: "tm-detail-card" + (className ? " " + className : "") },
      e("div", { className: "tm-detail-card-head" },
        icon && e(Icon, { name: icon, size: 15 }),
        e("h3", null, title)),
      e("div", { className: "tm-detail-card-body" }, children));
    const EmptyPanel = ({ icon = "box", title, text }) => e("div", { className: "tm-empty-panel" },
      e("div", { className: "tm-empty-ic" }, e(Icon, { name: icon, size: 18 })),
      e("div", null, e("b", null, title), text && e("span", null, text)));
    const saveDependencies = (ids, note) => {
      if (onDependenciesSave && onDependenciesSave(task, ids, note)) return true;
      unavailable("Task dependencies were not saved for your current access.");
      return false;
    };
    const addDependency = () => {
      const id = Number(depSelection);
      if (!id) return;
      if (saveDependencies([...dependencyRecordIds(), id], "Dependency added from Task Management drawer.")) setDepSelection("");
    };
    const removeDependency = (id) => {
      saveDependencies(dependencyRecordIds().filter(depId => Number(depId) !== Number(id)), "Dependency removed from Task Management drawer.");
    };
    const copyTaskDeepLink = () => {
      if (onTaskDeepLink && onTaskDeepLink(task)) return;
      unavailable("Task deep link could not be created for this task.");
    };
    const openLinkedCrm = () => {
      if (onOpenCrmRecord && onOpenCrmRecord(task)) return;
      unavailable("No Builder360 module route is configured for this linked CRM record.");
    };

    const subDone = task.subtasks.filter(s => s.done).length;
    const chkDone = task.checklist.filter(c => c.done).length;
    const tabs = [["details", "Details", null], ["subtasks", "Subtasks", task.subtasks.length], ["checklist", "Checklist", task.checklist.length], ["comments", "Comments", task.comments.length], ["activity", "Activity", null], ["time", "Time", null]];
    const renderDetails = () => e("div", { className: "tm-detail-stack" },
      e(DetailCard, { title: "Description", icon: "doc" },
        task.desc
          ? e("div", { className: "tm-desc", dangerouslySetInnerHTML: { __html: task.desc } })
          : e(EmptyPanel, { icon: "doc", title: "No description added", text: "Add a clear description so the team understands the work." })),
      e(DetailCard, { title: "Tags", icon: "tag" },
        task.tags.length > 0
          ? e("div", { className: "tm-card-tags" }, task.tags.map(t => e("span", { key: t, className: "tm-tag" }, "#" + t)))
          : e(EmptyPanel, { icon: "tag", title: "No tags added", text: "Tags help classify task work." })),
      task.attachments.length > 0 && e(DetailCard, { title: "Attachments", icon: "paperclip" },
        e("div", { className: "mbx-atts", style: { padding: 0 } }, task.attachments.map((a, i) =>
          e("div", { key: i, className: "mbx-att", style: { opacity: .68, cursor: "not-allowed" }, title: "Attachment download is not available for this file.", "aria-disabled": true },
            e("div", { className: "mbx-att-ic", style: { background: a.color } }, a.type),
            e("div", { style: { minWidth: 0 } }, e("div", { className: "mbx-att-name" }, a.name), e("div", { className: "mbx-att-sub" }, a.size)))))),
      e(DetailCard, { title: "Dependencies", icon: "link" },
        e("div", { className: "tm-dependency-list" },
          (task.deps.blockedBy.length || task.deps.dependsOn.length)
            ? [
                task.deps.blockedBy.map((d, i) => e("div", { key: "blocked-" + i, className: "tm-sub-row" }, e(Icon, { name: "alert", size: 15, style: { color: "var(--red)" } }), e("span", { className: "tm-sub-title" }, d.label || "Blocking task"), e(Badge, { tone: "b-red" }, "Blocking"))),
                task.deps.dependsOn.map((d, i) => e("div", { key: "depends-" + (d.recordId || i), className: "tm-sub-row" }, e(Icon, { name: "link", size: 15, style: { color: "var(--accent)" } }), e("span", { className: "tm-sub-title" }, d.label || "Task dependency"), d.status && e(Badge, { tone: "b-slate" }, String(d.status).replace(/_/g, " ")), e("button", { className: "tm-iconbtn", disabled: !canEditDependencies, style: { width: 28, height: 28, marginLeft: "auto" }, title: canEditDependencies ? "Remove dependency" : "Dependency changes are read-only for your access.", onClick: () => canEditDependencies && removeDependency(d.recordId) }, e(Icon, { name: "x", size: 13 }))))
              ]
            : e(EmptyPanel, { icon: "link", title: "No dependencies added", text: "Link another task when this task depends on it." })),
        e("div", { className: "tm-dep-add" },
          e("div", { className: "tm-dep-picker", onClick: ev => ev.stopPropagation() },
            e("button", { type: "button", className: "tm-dep-select", disabled: !canEditDependencies || dependencyChoices.length === 0, "aria-haspopup": "menu", "aria-expanded": menu === "dep", onClick: ev => { ev.stopPropagation(); if (canEditDependencies && dependencyChoices.length) setMenu(menu === "dep" ? null : "dep"); } },
              e("span", null, selectedDependency ? selectedDependency.id + " · " + selectedDependency.title : (dependencyChoices.length ? "Select dependency task" : "No dependency candidates")),
              e(Icon, { name: "chevD", size: 14 })),
            menu === "dep" && e("div", { className: "tm-menu tm-dep-menu", role: "menu" },
              dependencyChoices.map(candidate => e("button", { key: candidate.recordId, type: "button", role: "menuitem", className: "tm-mitem tm-dep-option", onClick: () => { setDepSelection(String(candidate.recordId)); setMenu(null); } },
                e("span", { className: "tm-dep-option-id" }, candidate.id),
                e("span", { className: "tm-dep-option-title" }, candidate.title),
                String(candidate.recordId) === String(depSelection) && e(Icon, { name: "check", size: 14, style: { marginLeft: "auto", color: "var(--green)" } }))))),
          e(Button, { variant: "primary", sm: true, disabled: !depSelection || !canEditDependencies, onClick: addDependency, children: "Add dependency" }))));

    return e("div", { className: "tm-drawer-scrim", onClick: onClose },
      e("div", { className: "tm-drawer", onClick: ev => ev.stopPropagation() },
        // head
        e("div", { className: "tm-dr-head" },
          e("div", { className: "tm-dr-crumb" },
            e("span", { className: "tm-dr-id" }, task.id),
            e("span", { className: "faint", style: { fontSize: 12 } }, "·"),
            e("span", { className: "faint", style: { fontSize: 12, fontWeight: 600 } }, task.cat),
            e("div", { className: "tm-dr-actions" },
              e("button", { className: "tm-iconbtn", style: { width: 32, height: 32 }, title: "Transfer task", disabled: !onTransfer, onClick: () => onTransfer && onTransfer(task) }, e(Icon, { name: "swap", size: 15 })),
              e("button", { className: "tm-iconbtn", style: { width: 32, height: 32 }, title: "Copy routed task deep link", onClick: copyTaskDeepLink }, e(Icon, { name: "link", size: 15 })),
              e("div", { style: { position: "relative" } },
                e("button", { className: "tm-iconbtn", style: { width: 32, height: 32 }, title: "More", onClick: ev => { ev.stopPropagation(); setMenu(menu === "more" ? null : "more"); } }, e(Icon, { name: "dots", size: 16 })),
                menu === "more" && e("div", { className: "tm-menu", style: { top: 36, right: 0 } },
                  e("div", { className: "tm-mitem", onClick: () => { onStatusChange ? onStatusChange(task, "completed", "Task marked complete from Task Management drawer.") : missingDetailApi("Task completion"); setMenu(null); } }, e(Icon, { name: "check", size: 15 }), "Mark complete"),
                  e("div", { className: "tm-mitem", onClick: () => {
                    if (onTaskArchive && onTaskArchive(task, "Task archived from Task Management drawer.")) { setMenu(null); onClose(); return; }
                    missingDetailApi("Task archive");
                    setMenu(null);
                  } }, e(Icon, { name: "archive", size: 15 }), "Archive"),
                  e("div", { className: "tm-msep" }),
                  e("div", { className: "tm-mitem", style: { color: "var(--red)" }, onClick: () => { if (onStatusChange) { onStatusChange(task, "cancelled", "Task cancelled from Task Management drawer."); setMenu(null); onClose(); return; } missingDetailApi("Task cancellation"); setMenu(null); } }, e(Icon, { name: "x", size: 15 }), "Cancel task"))),
              e("button", { className: "tm-iconbtn", style: { width: 32, height: 32 }, title: "Close", onClick: onClose }, e(Icon, { name: "x", size: 16 })))),
          e("div", { className: "tm-dr-title" + (!canEditTask ? " readonly" : ""), contentEditable: canEditTask, suppressContentEditableWarning: true,
            onBlur: ev => {
              if (!canEditTask) return;
              const v = ev.target.textContent.trim();
              if (v && v !== task.title) {
                if (onTaskUpdate && onTaskUpdate(task, { title: v }, "Title updated from Task Management drawer.")) return;
                ev.currentTarget.textContent = task.title;
                missingDetailApi("Task title update");
              }
            } }, task.title),
          e("div", { className: "tm-dr-statusbar" },
            // status dropdown
            e("div", { style: { position: "relative" } },
              e("button", { className: "tm-statbtn" + (!canEditStatus ? " readonly" : ""), disabled: !canEditStatus, title: canEditStatus ? "Change task status" : "Status is read-only for your access.", onClick: ev => { ev.stopPropagation(); if (canEditStatus) setMenu(menu === "status" ? null : "status"); } }, e(StatusPill, { id: task.status }), canEditStatus && e(Icon, { name: "chevD", size: 14, style: { color: "var(--text-3)" } })),
              menu === "status" && e("div", { className: "tm-menu", style: { top: 30, left: 0 } }, TM.statuses.filter(s => !["archived"].includes(s.id)).map(s => e("div", { key: s.id, className: "tm-mitem", onClick: () => setStatus(s.id) }, e(StatusPill, { id: s.id }), task.status === s.id && e(Icon, { name: "check", size: 14, style: { marginLeft: "auto", color: "var(--green)" } }))))),
            // priority dropdown
            e("div", { style: { position: "relative" } },
              e("button", { className: "tm-statbtn" + (!canEditPriority ? " readonly" : ""), disabled: !canEditPriority, title: canEditPriority ? "Change priority" : "Priority is read-only for your access.", onClick: ev => { ev.stopPropagation(); if (canEditPriority) setMenu(menu === "pri" ? null : "pri"); } }, e(PriPill, { id: task.priority }), canEditPriority && e(Icon, { name: "chevD", size: 14, style: { color: "var(--text-3)" } })),
              menu === "pri" && e("div", { className: "tm-menu", style: { top: 30, left: 0, minWidth: 150 } }, TM.priorities.map(p => e("div", { key: p.id, className: "tm-mitem", onClick: () => setPri(p.id) }, e(PriPill, { id: p.id }))))),
            e(DueChip, { due: task.due, overdue: task.overdue }),
            e("div", { style: { flex: 1 } }),
            e("div", { className: "tm-subprog" }, e("span", { className: "tm-miniring", style: { "--p": task.progress } }), task.progress + "%"))),
        // body: main + side
        e("div", { className: "tm-dr-body" },
          e("div", { className: "tm-dr-main" },
            e("div", { className: "tm-dr-tabs", role: "tablist", "aria-label": "Task detail sections" }, tabs.map(([id, label, cnt]) =>
              e("button", { key: id, type: "button", role: "tab", "aria-selected": tab === id, className: "tm-dr-tab" + (tab === id ? " on" : ""), onClick: () => setTab(id) }, label, cnt != null && cnt > 0 && e("span", { className: "cnt" }, cnt)))),

            tab === "details" && renderDetails(),

            tab === "subtasks" && e("div", null,
              task.subtasks.length > 0 && e("div", { className: "tm-prog-head" },
                e("span", { className: "tm-sec-label", style: { margin: 0 } }, subDone + " of " + task.subtasks.length + " done"),
                e("div", { className: "tm-prog-track" }, e("div", { className: "tm-prog-fill", style: { width: (task.subtasks.length ? subDone / task.subtasks.length * 100 : 0) + "%" } }))),
              task.subtasks.map(s => e("div", { key: s.id, className: "tm-sub-row" },
                e("button", { className: "tm-sub-check" + (s.done ? " on" : ""), onClick: () => toggleSub(s.id) }, s.done && e(Icon, { name: "check", size: 11 })),
                e("span", { className: "tm-sub-title" + (s.done ? " done" : "") }, s.title),
                e(PriPill, { id: s.priority }),
                e(Avatar, { name: U(s.assignee).name, color: U(s.assignee).color, size: 24 }),
                e("span", { className: "tm-card-meta" }, e(Icon, { name: "calendar", size: 12 }), s.due))),
              addingSub
                ? e("div", { className: "tm-addrow", style: { marginTop: 8 } },
                    e("input", { autoFocus: true, value: newSub, placeholder: "Subtask title…", onChange: ev => setNewSub(ev.target.value), onKeyDown: ev => { if (ev.key === "Enter") addSub(); if (ev.key === "Escape") setAddingSub(false); } }),
                    e(Button, { variant: "primary", sm: true, onClick: addSub, children: "Add" }))
                : e("button", { className: "tm-addline", style: { marginTop: 8 }, onClick: () => setAddingSub(true) }, e(Icon, { name: "plus", size: 15 }), "Add subtask")),

            tab === "checklist" && e("div", null,
              task.checklist.length > 0 && e("div", { className: "tm-prog-head" },
                e("span", { className: "tm-sec-label", style: { margin: 0 } }, chkDone + " of " + task.checklist.length),
                e("div", { className: "tm-prog-track" }, e("div", { className: "tm-prog-fill", style: { width: (task.checklist.length ? chkDone / task.checklist.length * 100 : 0) + "%" } }))),
              task.checklist.map(c => e("div", { key: c.id, className: "tm-chk-row" },
                e("button", { className: "tm-chk-box" + (c.done ? " on" : ""), onClick: () => toggleChk(c.id) }, c.done && e(Icon, { name: "check", size: 10 })),
                e("span", { className: "tm-chk-text" + (c.done ? " done" : "") }, c.text))),
              e("div", { className: "tm-addrow", style: { marginTop: 12 } },
                e("input", { value: newChk, placeholder: "Add checklist item…", onChange: ev => setNewChk(ev.target.value), onKeyDown: ev => { if (ev.key === "Enter") addChk(); } }),
                e(Button, { variant: "primary", sm: true, onClick: addChk, children: "Add" }))),

            tab === "comments" && e("div", null,
              task.comments.length === 0 && e("div", { className: "faint", style: { fontSize: 13, marginBottom: 14 } }, "No comments yet. Start the conversation — use @ to mention a teammate."),
              task.comments.map(c => e("div", { key: c.id, className: "tm-cmt" },
                e(Avatar, { name: U(c.who).name, color: U(c.who).color, size: 32 }),
                e("div", { className: "tm-cmt-body" },
                  e("div", { className: "tm-cmt-head" }, e("span", { className: "tm-cmt-who" }, U(c.who).name), e("span", { className: "tm-cmt-time" }, c.time)),
                  e(MentionText, { text: c.text })))),
              e("div", { className: "tm-cmt-box" },
                e(Avatar, { name: U(me).name, color: U(me).color, size: 32 }),
                e("textarea", { className: "tm-cmt-input", value: comment, placeholder: "Write a comment… @mention to notify", onChange: ev => setComment(ev.target.value), onKeyDown: ev => { if (ev.key === "Enter" && (ev.metaKey || ev.ctrlKey)) postComment(); } }),
                e("button", { className: "tm-timer-btn", style: { background: "var(--accent)", width: 40, height: 40, flex: "none" }, onClick: postComment }, e(Icon, { name: "send", size: 17 })))),

            tab === "activity" && e("div", { className: "tm-tl" }, task.activity.map((a, i) =>
              e("div", { key: i, className: "tm-tl-row" },
                e("div", { className: "tm-tl-ic" }, e(Icon, { name: a.icon, size: 14 })),
                e("div", null, e("div", { className: "tm-tl-text" }, e("b", null, U(a.who).name), " ", a.action), e("div", { className: "tm-tl-time" }, a.time))))),

            tab === "time" && e("div", null,
              e("div", { className: "tm-timer" },
                e("button", { className: "tm-timer-btn", style: { background: timer.running ? "var(--orange)" : "var(--green)" }, onClick: () => setTimer(s => ({ ...s, running: !s.running })) }, e(Icon, { name: timer.running ? "pause" : "play", size: 18 })),
                e("div", { style: { flex: 1 } }, e("div", { className: "tm-timer-display" }, fmtTimer(timer.sec)), e("div", { className: "faint", style: { fontSize: 11.5, fontWeight: 600 } }, timer.running ? "Timer running…" : "Press play to start tracking")),
                timer.sec > 0 && e(Button, { variant: "primary", sm: true, icon: "check", onClick: saveTime, children: "Log time" })),
              e("div", { className: "row between", style: { marginBottom: 6 } },
                e("div", { className: "tm-sec-label", style: { margin: 0 } }, "Time logs"),
                e("div", { className: "faint", style: { fontSize: 12, fontWeight: 700 } }, "Est " + task.est + "h · Actual " + task.actual + "h")),
              task.timeLogs.length === 0 ? e("div", { className: "faint", style: { fontSize: 12.5 } }, "No time logged yet.")
                : task.timeLogs.map(l => e("div", { key: l.id, className: "tm-tl-log-row" },
                    e(Avatar, { name: U(l.user).name, color: U(l.user).color, size: 24 }),
                    e("span", { style: { flex: 1, fontWeight: 600 } }, U(l.user).name, e("span", { className: "faint", style: { fontWeight: 500 } }, " · " + l.note)),
                    e("span", { className: "faint", style: { fontSize: 12 } }, l.date),
                    e("span", { className: "mono", style: { fontWeight: 800 } }, l.hours + "h"))))),

          // side
          e("div", { className: "tm-dr-side" },
            e("div", { className: "tm-meta-row" },
              e("div", { className: "tm-meta-k" }, e(Icon, { name: "user", size: 13 }), "Owner (assigned by)"),
              e("div", { className: "row gap-2" }, e(Avatar, { name: U(task.owner).name, color: U(task.owner).color, size: 26 }), e("span", { className: "tm-meta-v" }, U(task.owner).name))),
            e("div", { className: "tm-meta-row" },
              e("div", { className: "tm-meta-k" }, e(Icon, { name: "userPlus", size: 13 }), "Assignees"),
              e("div", { className: "row gap-2", style: { position: "relative", flexWrap: "wrap" } },
                task.assignees.map(id => e("div", { key: id, className: "tm-people-chip", title: U(id).name }, e(Avatar, { name: U(id).name, color: U(id).color, size: 20 }), U(id).name.split(" ")[0])),
                e("button", { className: "tm-assignee-add", onClick: ev => { ev.stopPropagation(); setMenu(menu === "assignee" ? null : "assignee"); } }, e(Icon, { name: "plus", size: 13 })),
                menu === "assignee" && e(PeoplePicker, { selected: task.assignees, onToggle: toggleAssignee, onClose: () => setMenu(null) }))),
            task.collaborators.length > 0 && e("div", { className: "tm-meta-row" },
              e("div", { className: "tm-meta-k" }, e(Icon, { name: "users", size: 13 }), "Collaborators"),
              e(AvatarStack, { ids: task.collaborators, size: 26 })),
            task.approvers.length > 0 && e("div", { className: "tm-meta-row" },
              e("div", { className: "tm-meta-k" }, e(Icon, { name: "shield", size: 13 }), "Approver"),
              e("div", { className: "row gap-2" }, e(Avatar, { name: U(task.approvers[0]).name, color: U(task.approvers[0]).color, size: 26 }), e("span", { className: "tm-meta-v" }, U(task.approvers[0]).name))),
            e("div", { className: "tm-meta-row" },
              e("div", { className: "tm-meta-k" }, e(Icon, { name: "calendar", size: 13 }), "Timeline"),
              e("div", { style: { display: "flex", flexDirection: "column", gap: 5, fontSize: 12.5 } },
                e("div", { className: "row between" }, e("span", { className: "faint" }, "Start"), e("span", { style: { fontWeight: 700 } }, task.start)),
                e("div", { className: "row between" }, e("span", { className: "faint" }, "Due"), e("span", { style: { fontWeight: 700, color: task.overdue ? "var(--red)" : "var(--text)" } }, task.due)),
                task.complete && e("div", { className: "row between" }, e("span", { className: "faint" }, "Completed"), e("span", { style: { fontWeight: 700, color: "var(--green)" } }, task.complete)))),
            e("div", { className: "tm-meta-row" },
              e("div", { className: "tm-meta-k" }, e(Icon, { name: "building", size: 13 }), "Project / Dept"),
              e("div", { className: "tm-meta-v" }, task.project), e("div", { className: "faint", style: { fontSize: 12, marginTop: 2 } }, task.dept + " · " + task.team)),
            task.crm && e("div", { className: "tm-meta-row" },
              e("div", { className: "tm-meta-k" }, e(Icon, { name: "link", size: 13 }), "Linked CRM"),
              e("button", { className: "tm-people-chip", style: { border: 0, cursor: "pointer" }, title: "Open the existing Builder360 module for this linked CRM record.", onClick: openLinkedCrm }, e(Icon, { name: task.crm.type === "deal" ? "tag" : task.crm.type === "company" || task.crm.type === "project" ? "building" : "user", size: 13 }), task.crm.label)),
            e("div", { style: { marginTop: 14 } },
              e("button", { className: "tm-watchbtn" + (task.watchers.includes(me) ? " on" : ""), onClick: () => onWatcherToggle ? onWatcherToggle(task) : unavailable("Watcher preference was not saved for your current access."), title: "Save watcher preference for this task." }, e(Icon, { name: "eye", size: 15 }), task.watchers.includes(me) ? "Watching" : "Watch", task.watchers.length > 0 && e("span", { className: "faint", style: { fontWeight: 700 } }, "· " + task.watchers.length)))))));
  }

  /* ================= CREATE MODAL ================= */
  function CreateTaskModal({ onClose, onCreate, toast, prefill }) {
    const selectableUsers = TM.users.some(u => u.serverId) ? TM.users.filter(u => u.serverId) : TM.users.slice(0, 9);
    const projectOptions = (window.Builder360Server?.collaboration_task_options?.projects || []).length
      ? window.Builder360Server.collaboration_task_options.projects.map(p => p.name)
      : [];
    const defaultAssignee = selectableUsers.find(u => u.id === TM.me)?.id || selectableUsers[0]?.id || "";
    const [f, setF] = React.useState(Object.assign({
      title: "", desc: "", cat: TM.categories[0], dept: TM.departments[0].name, project: projectOptions[0] || "",
      priority: "medium", status: "open", owner: TM.me, assignees: defaultAssignee ? [defaultAssignee] : [], start: "Today", due: "This Week", est: "",
    }, prefill || {}));
    const [menu, setMenu] = React.useState(null);
    const set = (k, v) => setF(s => ({ ...s, [k]: v }));
    React.useEffect(() => { const h = () => setMenu(null); if (menu) { window.addEventListener("click", h); return () => window.removeEventListener("click", h); } }, [menu]);

    const submit = () => {
      if (!f.title.trim()) { toast("Task title is required", "red"); return; }
      if (!f.assignees.length) { toast("Select an assignee before creating the task.", "red"); return; }
      onCreate(f); onClose();
    };

    return e("div", { className: "tm-modal-scrim", onClick: onClose },
      e("div", { className: "tm-modal", onClick: ev => ev.stopPropagation() },
        e("div", { className: "tm-modal-head" },
          e("div", { style: { width: 34, height: 34, borderRadius: 10, background: "var(--accent-soft)", color: "var(--accent)", display: "grid", placeItems: "center" } }, e(Icon, { name: "plus", size: 18 })),
          e("h2", null, "Create task"),
          e("button", { className: "tm-iconbtn", style: { width: 32, height: 32, border: "none" }, onClick: onClose }, e(Icon, { name: "x", size: 17 }))),
        e("div", { className: "tm-modal-body" },
          e("div", { className: "tm-form-grid" },
            e("div", { className: "tm-field full" }, e("label", null, "Task title *"), e("input", { className: "tm-input", autoFocus: true, value: f.title, placeholder: "What needs to be done?", onChange: ev => set("title", ev.target.value) })),
            e("div", { className: "tm-field full" }, e("label", null, "Description"), e("textarea", { className: "tm-textarea", value: f.desc, placeholder: "Add detail, context, acceptance criteria…", onChange: ev => set("desc", ev.target.value) })),
            e("div", { className: "tm-field" }, e("label", null, "Category"), e("select", { className: "tm-select", value: f.cat, onChange: ev => set("cat", ev.target.value) }, TM.categories.map(c => e("option", { key: c }, c)))),
            e("div", { className: "tm-field" }, e("label", null, "Department"), e("select", { className: "tm-select", value: f.dept, onChange: ev => set("dept", ev.target.value) }, TM.departments.map(d => e("option", { key: d.id }, d.name)))),
            e("div", { className: "tm-field" }, e("label", null, "Project"), e("select", { className: "tm-select", value: f.project, disabled: !projectOptions.length, onChange: ev => set("project", ev.target.value) }, projectOptions.length ? projectOptions.map(p => e("option", { key: p }, p)) : e("option", { value: "" }, "No project available"))),
            e("div", { className: "tm-field" }, e("label", null, "Estimated hours"), e("input", { className: "tm-input", type: "number", value: f.est, placeholder: "e.g. 6", onChange: ev => set("est", ev.target.value) })),
            e("div", { className: "tm-field full" }, e("label", null, "Priority"),
              e("div", { className: "tm-prichip-row" }, TM.priorities.map(p => e("button", { key: p.id, className: "tm-prichip" + (f.priority === p.id ? " on" : ""), style: f.priority === p.id ? { background: p.color } : {}, onClick: () => set("priority", p.id) }, p.id === "critical" && e(Icon, { name: "fire", size: 13 }), p.label)))),
            e("div", { className: "tm-field" }, e("label", null, "Start date"), e("select", { className: "tm-select", value: f.start, onChange: ev => set("start", ev.target.value) }, ["Today", "Tomorrow", "Next week"].map(p => e("option", { key: p }, p)))),
            e("div", { className: "tm-field" }, e("label", null, "Due date"), e("select", { className: "tm-select", value: f.due, onChange: ev => set("due", ev.target.value) }, ["Today", "Tomorrow", "This Week", "Next week"].map(p => e("option", { key: p }, p)))),
            e("div", { className: "tm-field full" }, e("label", null, "Assign to"),
              selectableUsers.length ? e(window.SearchablePeoplePicker, {
                items: selectableUsers,
                selected: f.assignees,
                mode: "multi",
                placeholder: "Search employee name, role or department...",
                emptyText: "No matching employees",
                onChange: value => set("assignees", value),
                getId: user => user.id,
                getLabel: user => user.name,
                getSubLabel: user => [user.title, user.dept || user.team, user.email].filter(Boolean).join(" · "),
              }) : e("div", { className: "sys-note" }, e(Icon, { name: "alert", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), "No assignees are available for your company."))),
        ),
        e("div", { className: "tm-modal-foot" },
          e("span", { className: "faint", style: { fontSize: 12, fontWeight: 600 } }, "Will appear in ", e("b", null, "To Do")),
          e("div", { style: { flex: 1 } }),
          e(Button, { onClick: onClose, children: "Cancel" }),
          e(Button, { variant: "primary", icon: "plus", onClick: submit, children: "Create task" }))));
  }

  /* ================= TRANSFER MODAL ================= */
  function TransferModal({ task, onClose, onConfirm, toast }) {
    const selectableUsers = TM.users.some(u => u.serverId) ? TM.users.filter(u => u.serverId) : TM.users;
    const [to, setTo] = React.useState(null);
    const [reason, setReason] = React.useState("");
    const [approval, setApproval] = React.useState(false);
    const submit = () => {
      if (!to) { toast("Select a new owner", "red"); return; }
      if (!reason.trim()) { toast("Add a transfer reason", "red"); return; }
      onConfirm(task, to, reason, approval); onClose();
    };
    return e("div", { className: "tm-modal-scrim", onClick: onClose },
      e("div", { className: "tm-modal sm", onClick: ev => ev.stopPropagation() },
        e("div", { className: "tm-modal-head" },
          e("div", { style: { width: 34, height: 34, borderRadius: 10, background: "var(--violet-soft)", color: "var(--violet)", display: "grid", placeItems: "center" } }, e(Icon, { name: "swap", size: 17 })),
          e("h2", null, "Transfer task"),
          e("button", { className: "tm-iconbtn", style: { width: 32, height: 32, border: "none" }, onClick: onClose }, e(Icon, { name: "x", size: 17 }))),
        e("div", { className: "tm-modal-body" },
          e("div", { style: { padding: "10px 12px", background: "var(--surface-2)", border: "1px solid var(--border)", borderRadius: 10, marginBottom: 16 } },
            e("div", { className: "tm-dr-id", style: { marginBottom: 3 } }, task.id),
            e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, task.title)),
          e("div", { className: "tm-field", style: { marginBottom: 14 } },
            e("label", null, "Current owner"),
            e("div", { className: "row gap-2" }, e(Avatar, { name: U(task.assignees[0]).name, color: U(task.assignees[0]).color, size: 26 }), e("span", { style: { fontWeight: 700, fontSize: 13 } }, U(task.assignees[0]).name))),
          e("div", { className: "tm-field", style: { marginBottom: 14 } },
            e("label", null, "Transfer to *"),
            e(window.SearchablePeoplePicker, {
              items: selectableUsers.filter(u => u.id !== task.assignees[0]),
              selected: to || "",
              mode: "single",
              placeholder: "Search employee name...",
              emptyText: "No matching employees",
              onChange: value => setTo(value),
              getId: user => user.id,
              getLabel: user => user.name,
              getSubLabel: user => [user.title, user.dept || user.team, user.email].filter(Boolean).join(" · "),
            })),
          e("div", { className: "tm-field", style: { marginBottom: 14 } },
            e("label", null, "Reason *"),
            e("textarea", { className: "tm-textarea", style: { minHeight: 64 }, value: reason, placeholder: "Why is this being transferred?", onChange: ev => setReason(ev.target.value) })),
          e("label", { className: "row gap-2", style: { cursor: "pointer", fontSize: 13, fontWeight: 600 } },
            e("button", { className: "tm-cb" + (approval ? " on" : ""), onClick: () => setApproval(a => !a) }, approval && e(Icon, { name: "check", size: 11 })),
            "Require manager approval before transfer")),
        e("div", { className: "tm-modal-foot" },
          e("div", { style: { flex: 1 } }),
          e(Button, { onClick: onClose, children: "Cancel" }),
          e(Button, { variant: "primary", icon: "swap", onClick: submit, children: approval ? "Request transfer" : "Transfer now" }))));
  }

  window.TMDrawer = TaskDrawer;
  window.TMCreateModal = CreateTaskModal;
  window.TMTransferModal = TransferModal;
})();
