const React = window.React;

/* ============================================================
   Builder360 — Task Management: main shell, sub-nav rail,
   dashboard, view orchestration  → window.TaskManagement
   ============================================================ */
(function () {
  const { Icon, Avatar, Badge, Button, Card, Stat, Donut, LineChart, Empty } = window;
  const e = React.createElement;
  const TM = window.TM;
  const { U, PriPill, StatusPill, AvatarStack, DueChip } = window.TMUI;

  const taskOptions = () => window.Builder360Server?.collaboration_task_options || null;
  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const firstApiError = (payload) => {
    const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
    return errors[0] || payload?.message || "The task request could not be completed.";
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
  const taskRecordUrl = (template, task) => template && task?.recordId ? template.replace("__TASK__", task.recordId) : null;
  const taskPermissions = (task) => task?.permissions || task?.server?.permissions || {};
  const canTask = (task, key) => taskPermissions(task)[key] === true;
  const pendingTransfers = (task) => Array.isArray(task?.server?.transfer_requests)
    ? task.server.transfer_requests.filter(item => item?.status === "pending")
    : [];
  const filenameFromDisposition = (value, fallback) => {
    const match = String(value || "").match(/filename="?([^"]+)"?/i);
    return match?.[1] || fallback;
  };
  const triggerDownload = async (response, fallbackFilename) => {
    const blob = await response.blob();
    const href = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = href;
    link.download = filenameFromDisposition(response.headers.get("Content-Disposition"), fallbackFilename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(href), 1000);
  };
  const serverStatusToUi = (status) => ({ in_progress: "inprogress", blocked: "onhold" }[status] || status || "open");
  const uiStatusToServer = (status) => ({
    inprogress: "in_progress",
    onhold: "blocked",
    waitinfo: "blocked",
    waitdep: "blocked",
    completed: "completed",
    cancelled: "cancelled",
    open: "open",
    assigned: "open",
    accepted: "open",
    todo: "open",
    draft: "open",
  }[status] || null);
  const serverStatusForColumn = (column) => ({
    todo: "open",
    backlog: "open",
    inprogress: "in_progress",
    blocked: "blocked",
    done: "completed",
    cancelled: "cancelled",
  }[column] || null);
  const stripHtml = (value) => String(value || "").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
  const escapeHtml = (value) => String(value || "").replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[ch]));
  const normalizeRelatedType = (value) => String(value || "").split("\\").pop().toLowerCase();
  const crmRouteForTask = (task) => {
    const type = normalizeRelatedType(task?.crm?.type || task?.server?.related_type);
    return ({
      project: "projects",
      lead: "leads",
      booking: "sales",
      customer: "collections",
      buyer: "collections",
      collectionreceipt: "collections",
      collection: "collections",
      unit: "inventory",
      collaborationmessage: "mailbox",
      calendarevent: "calendar",
      worktask: "tasks",
    })[type] || null;
  };
  const taskDeepLinkUrl = (task) => {
    if (!task?.recordId) return null;
    const url = new URL(window.location.href);
    url.hash = "tasks?task=" + encodeURIComponent(String(task.recordId));
    return url.toString();
  };
  const taskIdFromHash = () => {
    const hash = (window.location.hash || "").replace(/^#/, "");
    if (!hash.startsWith("tasks?")) return null;
    const params = new URLSearchParams(hash.slice(hash.indexOf("?") + 1));
    return params.get("task");
  };
  const copyTextFallback = (value) => {
    const node = document.createElement("textarea");
    node.value = value;
    node.setAttribute("readonly", "readonly");
    node.style.position = "fixed";
    node.style.left = "-9999px";
    document.body.appendChild(node);
    node.select();
    let copied = false;
    try {
      copied = document.execCommand("copy");
    } catch (error) {
      copied = false;
    }
    node.remove();
    return copied;
  };
  const dateLabel = (iso) => {
    if (!iso) return "—";
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return "—";
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
    const day = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const diff = Math.round((day - today) / 86400000);
    if (diff < 0) return "Overdue";
    if (diff === 0) return "Today";
    if (diff === 1) return "Tomorrow";
    if (diff <= 7) return "This Week";
    return date.toLocaleDateString("en-IN", { day: "2-digit", month: "short" });
  };
  const relativeLabel = (iso) => iso ? new Date(iso).toLocaleDateString("en-IN", { day: "2-digit", month: "short" }) : "recently";
  const dueInputToIso = (value) => {
    const now = new Date();
    const setTime = (date) => { date.setHours(17, 0, 0, 0); return date.toISOString(); };
    if (value === "Today") return setTime(new Date(now));
    if (value === "Tomorrow") { const d = new Date(now); d.setDate(d.getDate() + 1); return setTime(d); }
    if (value === "This Week") { const d = new Date(now); d.setDate(d.getDate() + 5); return setTime(d); }
    if (value === "Next week") { const d = new Date(now); d.setDate(d.getDate() + 7); return setTime(d); }
    return null;
  };
  const syncServerUsers = (options, rows = []) => {
    const seen = new Set(TM.users.map(u => u.id));
    const add = (raw, fallbackTitle) => {
      if (!raw?.id) return null;
      const id = serverUserKey(raw.id);
      if (!seen.has(id)) {
        const colors = ["#2570eb", "#15a657", "#e08600", "#7c3aed", "#0ea5a4", "#dc2f3a", "#F6740C", "#0891b2"];
        const user = { id, serverId: raw.id, name: raw.name || raw.email || "User " + raw.id, title: raw.role || fallbackTitle || raw.email || "Team Member", dept: raw.company_id ? "Company " + raw.company_id : "Builder360", team: "Server Users", color: colors[TM.users.length % colors.length], role: raw.role || "User" };
        TM.users.push(user);
        TM.U[id] = user;
        seen.add(id);
      }
      return id;
    };
    (options?.assignees || []).forEach(u => add(u, "Assignable user"));
    add(window.Builder360Server?.user, "Current user");
    rows.forEach(row => {
      add(row.created_by, "Task creator");
      add(row.assigned_to, "Task assignee");
      (row.comments || []).forEach(comment => add(comment.author, "Task commenter"));
      (row.subtasks || []).forEach(subtask => {
        add(subtask.assigned_to, "Subtask assignee");
        add(subtask.created_by, "Subtask creator");
      });
      (row.time_logs || []).forEach(log => add(log.user, "Time logger"));
    });
    const current = serverUserKey(options?.current_user_id || window.Builder360Server?.user?.id);
    if (current && TM.U[current]) TM.me = current;
  };
  const transformServerTask = (row) => {
    const owner = serverUserKey(row.created_by?.id) || TM.me;
    const assignee = serverUserKey(row.assigned_to?.id) || owner;
    const status = serverStatusToUi(row.status);
    const column = TM.ST[status]?.col || "todo";
    const due = dateLabel(row.due_at);
    const overdue = row.due_at && new Date(row.due_at).getTime() < Date.now() && !["completed", "cancelled"].includes(row.status);
    const checklist = (row.checklist || []).map((item, i) => ({ id: "srv-" + row.id + "-c" + i, text: item.label || item.text || "Checklist item", done: !!item.done }));
    const comments = (row.comments || []).map(item => ({
      id: "srv-" + row.id + "-cm" + item.id,
      recordId: item.id,
      who: serverUserKey(item.author?.id) || owner,
      time: item.created_at ? relativeLabel(item.created_at) : "recently",
      text: item.body || "",
      mentions: (item.mentions || []).map(serverUserKey).filter(Boolean),
    }));
    const subtasks = (row.subtasks || []).map(item => ({
      id: "srv-" + row.id + "-s" + item.id,
      recordId: item.id,
      title: item.title || "Subtask",
      assignee: serverUserKey(item.assigned_to?.id) || assignee,
      status: serverStatusToUi(item.status),
      priority: item.priority || "medium",
      due: dateLabel(item.due_at),
      dueAt: item.due_at || null,
      done: item.status === "completed",
    }));
    const timeLogs = (row.time_logs || []).map(item => ({
      id: "srv-" + row.id + "-tl" + item.id,
      recordId: item.id,
      user: serverUserKey(item.user?.id) || owner,
      date: item.logged_on ? relativeLabel(item.logged_on) : "Today",
      minutes: Number(item.minutes || 0),
      hours: Number(item.hours || ((Number(item.minutes || 0) / 60).toFixed(2))),
      note: item.note || item.source || "Logged work",
    }));
    const watcherIds = Array.isArray(row.metadata?.watcher_user_ids)
      ? row.metadata.watcher_user_ids.map(serverUserKey).filter(Boolean)
      : [];
    const dependencyRows = Array.isArray(row.metadata?.task_dependencies)
      ? row.metadata.task_dependencies.map(item => ({
          id: "srv-task-dep-" + item.id,
          recordId: Number(item.id),
          label: (item.task_number || "TSK-" + item.id) + " · " + (item.title || "Task"),
          status: item.status || "open",
          priority: item.priority || "medium",
        })).filter(item => item.recordId)
      : [];
    const history = (row.workflow_history || []).slice().reverse().map((item, i) => ({
      who: serverUserKey(item.user_id) || owner,
      action: item.note || item.status || "updated task",
      time: item.at ? relativeLabel(item.at) : "recently",
      icon: item.status === "completed" ? "check" : item.status === "in_progress" ? "play" : "activity",
    }));
    return {
      id: row.task_number || "TSK-" + row.id,
      recordId: row.id,
      number: row.id,
      title: row.title,
      cat: row.module_context || "Collaboration",
      sub: row.related_type || "",
      dept: row.assigned_to?.role || "Builder360",
      team: "Server Tasks",
      project: row.project?.name || "—",
      projectId: row.project?.id || null,
      crm: row.related_type ? { type: normalizeRelatedType(row.related_type), recordId: row.related_id, label: normalizeRelatedType(row.related_type) + " #" + row.related_id } : null,
      owner,
      assignees: [assignee],
      collaborators: [],
      watchers: watcherIds,
      reviewers: [],
      approvers: [],
      priority: row.priority || "medium",
      status,
      column,
      start: relativeLabel(row.created_at),
      due,
      dueAt: row.due_at,
      overdue,
      est: Number(row.metadata?.estimated_hours || 0),
      actual: Number((timeLogs.reduce((sum, item) => sum + Number(item.minutes || 0), 0) / 60).toFixed(2)),
      billable: 0,
      tags: [row.module_context].filter(Boolean),
      desc: row.description ? "<p>" + escapeHtml(row.description) + "</p>" : "<p>—</p>",
      progress: row.status === "completed" ? 100 : row.status === "in_progress" ? 40 : row.status === "blocked" ? 20 : 0,
      subtasks,
      checklist,
      comments,
      transfers: [],
      timeLogs,
      deps: { blockedBy: [], dependsOn: dependencyRows, related: [] },
      attachments: [],
      activity: history.length ? history : [{ who: owner, action: "created this task", time: relativeLabel(row.created_at), icon: "plus" }],
      createdAt: relativeLabel(row.created_at),
      updatedAt: relativeLabel(row.updated_at),
      createdIso: row.created_at || null,
      updatedIso: row.updated_at || null,
      completedAt: row.completed_at || null,
      server: row,
      permissions: row.permissions || {},
    };
  };

  const colOf = (t) => t.column || (TM.ST[t.status] ? TM.ST[t.status].col : "todo");
  const isDone = (t) => ["completed", "archived"].includes(t.status);
  const isOpen = (t) => ["draft", "open", "assigned", "accepted", "todo"].includes(t.status);
  const taskCompletionDate = (task) => {
    const iso = task.completedAt || task.server?.completed_at || (isDone(task) ? (task.updatedIso || task.server?.updated_at) : null);
    if (!iso) return null;
    const date = new Date(iso);
    return Number.isNaN(date.getTime()) ? null : date;
  };
  const completionTrend = (rows) => {
    const today = new Date();
    today.setHours(23, 59, 59, 999);
    const labels = [];
    const data = [];
    for (let i = 6; i >= 0; i -= 1) {
      const end = new Date(today);
      end.setDate(today.getDate() - (i * 7));
      const start = new Date(end);
      start.setDate(end.getDate() - 6);
      start.setHours(0, 0, 0, 0);
      labels.push(i === 0 ? "Now" : "W-" + i);
      data.push(rows.filter(task => {
        if (!isDone(task)) return false;
        const completed = taskCompletionDate(task);
        return completed !== null && completed >= start && completed <= end;
      }).length);
    }
    return { labels, data };
  };

  function TaskManagement({ role, toast }) {
    const options = taskOptions();
    const hasTaskApi = !!(options && options.index_url);
    const [tasks, setTasks] = React.useState(() => []);
    const [apiState, setApiState] = React.useState(() => ({ loading: !!hasTaskApi, connected: false, error: null }));
    const [scope, setScope] = React.useState("dashboard");
    const [viewMode, setViewMode] = React.useState("kanban");
    const [q, setQ] = React.useState("");
    const [priFilter, setPriFilter] = React.useState(null);
    const [filterMenu, setFilterMenu] = React.useState(false);
    const [railOpen, setRailOpen] = React.useState(true);
    const [optionsOpen, setOptionsOpen] = React.useState(false);
    const [fullScreen, setFullScreen] = React.useState(false);
    const [checked, setChecked] = React.useState(() => new Set());
    const [openId, setOpenId] = React.useState(null);
    const [creating, setCreating] = React.useState(null); // null | {} | {column} | {fromTemplate}
    const [transferring, setTransferring] = React.useState(null);
    const me = TM.me, myUser = U(me);
    const canCreateTasks = !!(hasTaskApi && options?.can_create && options?.store_url);
    const taskApiRequiredMessage = "Task records are not available for your current access. Create permissions or task setup may be required.";

    const update = (id, next) => setTasks(ts => ts.map(t => t.id === id ? (typeof next === "function" ? next(t) : next) : t));
    const replaceFromServer = (row) => {
      syncServerUsers(options, [row]);
      const next = transformServerTask(row);
      setTasks(ts => ts.some(t => t.recordId === next.recordId || t.id === next.id)
        ? ts.map(t => (t.recordId === next.recordId || t.id === next.id) ? next : t)
        : [next, ...ts]);
      return next;
    };

    React.useEffect(() => {
      if (!hasTaskApi) return;
      let alive = true;
      setApiState({ loading: true, connected: false, error: null });
      apiJson(options.index_url)
        .then(body => {
          if (!alive) return;
          const rows = body.data || [];
          syncServerUsers(options, rows);
          setTasks(rows.map(transformServerTask));
          setApiState({ loading: false, connected: true, error: null });
        })
        .catch(error => {
          if (!alive) return;
          setTasks([]);
          setApiState({ loading: false, connected: false, error: error.message });
          toast("Task records could not be loaded. The workspace is read-only until records are available. " + error.message, "orange");
        });
      return () => { alive = false; };
    }, [hasTaskApi]);

    // ---- scope filters ----
    const scopeFilter = (t) => {
      switch (scope) {
        case "mine": return t.assignees.includes(me) || t.owner === me;
        case "toMe": return t.assignees.includes(me);
        case "byMe": return t.owner === me;
        case "team": return t.team === myUser.team;
        case "dept": return t.dept === myUser.dept;
        case "all": return true;
        case "today": return t.due === "Today";
        case "week": return ["Today", "Tomorrow", "This Week"].includes(t.due);
        case "overdue": return t.overdue || t.due === "Overdue";
        case "pending": return !isDone(t) && t.status !== "cancelled";
        case "completed": return t.status === "completed";
        case "archived": return t.status === "archived";
        default: return true;
      }
    };
    const scopeTasks = tasks.filter(scopeFilter);
    const visible = scopeTasks.filter(t => {
      if (priFilter && t.priority !== priFilter) return false;
      if (q.trim()) { const s = (t.title + " " + t.id + " " + t.cat + " " + t.tags.join(" ")).toLowerCase(); if (!s.includes(q.toLowerCase())) return false; }
      return true;
    });
    const cnt = (s) => { const sv = scope; const f = (t) => { scope; return true; }; return tasks.filter(t => { const old = scope; return scopeOf(t, s); }).length; };
    function scopeOf(t, s) {
      switch (s) {
        case "mine": return t.assignees.includes(me) || t.owner === me;
        case "toMe": return t.assignees.includes(me);
        case "byMe": return t.owner === me;
        case "team": return t.team === myUser.team;
        case "dept": return t.dept === myUser.dept;
        case "all": return true;
        case "today": return t.due === "Today";
        case "week": return ["Today", "Tomorrow", "This Week"].includes(t.due);
        case "overdue": return t.overdue || t.due === "Overdue";
        case "pending": return !isDone(t) && t.status !== "cancelled";
        case "completed": return t.status === "completed";
        case "archived": return t.status === "archived";
        default: return true;
      }
    }

    const openTask = (id) => setOpenId(id);
    const openTaskObj = tasks.find(t => t.id === openId);
    React.useEffect(() => {
      const requested = taskIdFromHash();
      if (!requested || !tasks.length) return;
      const match = tasks.find(t => String(t.recordId) === String(requested) || String(t.id) === String(requested));
      if (match && openId !== match.id) setOpenId(match.id);
    }, [tasks, openId]);
    const copyTaskDeepLink = (task) => {
      const url = taskDeepLinkUrl(task);
      if (!url) {
        toast("Task deep link is available only after the task is saved.", "orange");
        return false;
      }
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(url)
          .then(() => toast("Task deep link copied for " + task.id + ".", "green"))
          .catch(() => {
            const copied = copyTextFallback(url);
            toast(copied ? "Task deep link copied for " + task.id + "." : "Task deep link generated but clipboard access was blocked by the browser.", copied ? "green" : "orange");
          });
      } else {
        const copied = copyTextFallback(url);
        toast(copied ? "Task deep link copied for " + task.id + "." : "Task deep link generated but clipboard access was blocked by the browser.", copied ? "green" : "orange");
      }
      return true;
    };
    const openTaskCrmRecord = (task) => {
      const crmRoute = crmRouteForTask(task);
      if (!crmRoute) {
        toast("No Builder360 module route is configured for this linked CRM record.", "orange");
        return false;
      }
      if (window.Builder360Navigate) window.Builder360Navigate(crmRoute);
      else window.dispatchEvent(new CustomEvent("builder360:navigate", { detail: { route: crmRoute } }));
      toast("Opened linked " + (task.crm?.type || "CRM") + " record in " + crmRoute + " module.", "green");
      return true;
    };
    const openCreate = (prefill = {}) => {
      if (!canCreateTasks) {
        toast(options?.can_create === false
          ? "Task creation requires collaboration task create permission."
          : taskApiRequiredMessage,
          "orange");
        return;
      }
      setCreating(prefill);
    };

    // ---- create ----
    const createTask = async (f, column) => {
      if (!canCreateTasks) {
        toast(options?.can_create === false
          ? "Task creation requires collaboration task create permission."
          : taskApiRequiredMessage,
          "orange");
        return;
      }

      try {
        const selectedAssignee = (f.assignees || []).map(serverIdFromUserKey).find(Boolean);
        const selectedProject = (options.projects || []).find(p => p.name === f.project || p.code === f.project);
        const templateSteps = (f.subtasks || []).map(step => String(step || "").trim()).filter(Boolean);
        const body = await apiJson(options.store_url, {
          method: "POST",
          body: JSON.stringify({
            title: f.title,
            description: stripHtml(f.desc || ""),
            assigned_to_user_id: selectedAssignee || undefined,
            project_id: selectedProject?.id || undefined,
            priority: f.priority || "medium",
            due_at: dueInputToIso(f.due) || undefined,
            module_context: (f.cat || "collaboration").toLowerCase().replace(/[^a-z0-9_ -]/g, "").slice(0, 64),
            metadata: { source: "task_management_screen", estimated_hours: Number(f.est || 0) },
          }),
        });
        let taskRow = body.data;
        replaceFromServer(taskRow);
        let savedTemplateSubtasks = 0;

        if (templateSteps.length && options.subtask_url_template) {
          for (const step of templateSteps) {
            try {
              const subtaskBody = await apiJson(options.subtask_url_template.replace("__TASK__", taskRow.id), {
                method: "POST",
                body: JSON.stringify({
                  title: step,
                  assigned_to_user_id: selectedAssignee || undefined,
                  priority: "medium",
                  due_at: dueInputToIso(f.due) || undefined,
                  metadata: {
                    source: "task_management_template",
                    template_name: f._tplName || null,
                  },
                }),
              });
              taskRow = subtaskBody.data || taskRow;
              savedTemplateSubtasks += 1;
            } catch (subtaskError) {
              toast("Task saved, but template subtask was not saved: " + subtaskError.message, "orange");
              break;
            }
          }
          replaceFromServer(taskRow);
        } else if (templateSteps.length) {
          toast("Task saved, but template subtasks could not be saved for your current access.", "orange");
        }

        setApiState(s => ({ ...s, connected: true, error: null }));
        toast(
          "Task " + taskRow.task_number + " saved"
            + (savedTemplateSubtasks ? " with " + savedTemplateSubtasks + " template subtask(s)" : "")
            + " and assigned through Task Management.",
          "green"
        );
      } catch (error) {
        toast(error.message, "red");
      }
    };
    const useTemplate = (tpl) => {
      if (!canCreateTasks) {
        toast("Task templates can create tasks only when task creation is available for your role.", "orange");
        return;
      }
      setCreating({ title: tpl.name + " — ", cat: tpl.cat, subtasks: tpl.steps, _tplName: tpl.name });
      toast(options.subtask_url_template
        ? "Template loaded. Saving the task will also save its subtask steps."
        : "Template loaded, but subtask steps cannot be saved for your current access.",
        options.subtask_url_template ? "accent" : "orange");
    };

    // ---- move (kanban) ----
    const moveTask = (id, colId) => {
      const st = TM.statuses.find(s => s.col === colId) || TM.ST.todo;
      const task = tasks.find(t => t.id === id);
      const serverStatus = serverStatusForColumn(colId);
      const colLabel = TM.columns.find(c => c.id === colId).label;

      if (!canTask(task, "can_update_status")) {
        toast("Task move is not available for your role on this task.", "orange");
        return;
      }

      if (hasTaskApi && task?.recordId && serverStatus && task.status !== "archived") {
        apiJson(taskRecordUrl(options.status_url_template, task), {
          method: "PATCH",
          body: JSON.stringify({ status: serverStatus, note: "Moved to " + colLabel + " from Task Management board." }),
        })
          .then(body => { replaceFromServer(body.data); toast("Task " + body.data.task_number + " status saved.", "green"); })
          .catch(error => toast(error.message, "red"));
        return;
      }

      toast("Task move was not saved. This status change is not available for " + colLabel + ".", "orange");
    };

    const changeTaskStatus = (task, status, note) => {
      const serverStatus = uiStatusToServer(status);
      if (!canTask(task, "can_update_status")) {
        toast("Status change is not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId && serverStatus && task.status !== "archived") {
        apiJson(taskRecordUrl(options.status_url_template, task), {
          method: "PATCH",
          body: JSON.stringify({ status: serverStatus, note: note || "Status changed from Task Management drawer." }),
        })
          .then(body => { replaceFromServer(body.data); toast("Task " + body.data.task_number + " status saved.", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }

      toast("Status change was not saved. This action is not available for your role.", "orange");
      return false;
    };

    const updateTaskDetails = (task, patch, note) => {
      if (!canTask(task, "can_update_details")) {
        toast("Task detail updates are not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId && options.update_url_template) {
        apiJson(taskRecordUrl(options.update_url_template, task), {
          method: "PATCH",
          body: JSON.stringify({ ...patch, note: note || "Task details updated from Task Management drawer." }),
        })
          .then(body => { replaceFromServer(body.data); toast("Task " + body.data.task_number + " details saved.", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      return false;
    };

    const archiveTask = (task, note) => {
      if (!canTask(task, "can_archive")) {
        toast("Task archive is not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId && options.archive_url_template) {
        apiJson(taskRecordUrl(options.archive_url_template, task), {
          method: "PATCH",
          body: JSON.stringify({ note: note || "Task archived from Task Management drawer." }),
        })
          .then(body => {
            setTasks(ts => ts.filter(t => t.recordId !== body.data.id && t.id !== task.id));
            setOpenId(null);
            toast("Task " + body.data.task_number + " archived.", "green");
          })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      return false;
    };

    const saveTaskChecklist = (task, checklist, note) => {
      if (!canTask(task, "can_manage_checklist")) {
        toast("Checklist updates are not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId) {
        apiJson(taskRecordUrl(options.checklist_url_template, task), {
          method: "PATCH",
          body: JSON.stringify({
            checklist: checklist.map(item => ({ label: item.label || item.text, done: !!item.done })),
            note: note || "Checklist updated from Task Management drawer.",
          }),
        })
          .then(body => { replaceFromServer(body.data); toast("Task " + body.data.task_number + " checklist saved.", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      return false;
    };

    const addTaskComment = (task, text, mentions) => {
      if (!canTask(task, "can_comment")) {
        toast("Comments are not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId) {
        apiJson(taskRecordUrl(options.comment_url_template, task), {
          method: "POST",
          body: JSON.stringify({
            body: text,
            mentions: (mentions || []).map(serverIdFromUserKey).filter(Boolean),
            metadata: { source: "task_management_drawer" },
          }),
        })
          .then(body => { replaceFromServer(body.data); toast("Comment saved on task " + body.data.task_number + ".", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      return false;
    };

    const exportTaskRegister = async (format = "csv") => {
      if (!hasTaskApi || !options.export_url) {
        toast("Task export is not available for your current access.", "orange");
        return;
      }

      try {
        const url = new URL(options.export_url, window.location.origin);
        const normalizedFormat = format === "pdf" ? "pdf" : "csv";
        url.searchParams.set("format", normalizedFormat);
        if (q.trim()) url.searchParams.set("q", q.trim());
        if (priFilter) url.searchParams.set("priority", priFilter);
        if (scope === "completed") url.searchParams.set("status", "completed");
        if (scope === "pending") url.searchParams.set("status", "open");

        const response = await fetch(url.toString(), {
          method: "GET",
          credentials: "same-origin",
          headers: {
            "Accept": normalizedFormat === "pdf" ? "application/pdf" : "text/csv",
            "X-CSRF-TOKEN": csrfToken(),
          },
        });

        if (!response.ok) {
          const payload = await response.json().catch(() => ({}));
          throw new Error(firstApiError(payload));
        }

        await triggerDownload(response, "builder360-collaboration-tasks." + normalizedFormat);
        toast("Task " + normalizedFormat.toUpperCase() + " export generated for your current filters.", "green");
      } catch (error) {
        toast(error.message || "Task export failed.", "red");
      }
    };
    const exportTaskCsv = () => exportTaskRegister("csv");
    const exportTaskPdf = () => exportTaskRegister("pdf");

    const createTaskSubtask = (task, title) => {
      if (!canTask(task, "can_manage_subtasks")) {
        toast("Subtasks are not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId) {
        const assigneeId = serverIdFromUserKey(task.assignees?.[0]);
        apiJson(taskRecordUrl(options.subtask_url_template, task), {
          method: "POST",
          body: JSON.stringify({
            title,
            assigned_to_user_id: assigneeId || undefined,
            priority: "medium",
            due_at: task.dueAt || undefined,
            metadata: { source: "task_management_drawer" },
          }),
        })
          .then(body => { replaceFromServer(body.data); toast("Subtask saved on task " + body.data.task_number + ".", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      return false;
    };

    const updateTaskSubtaskStatus = (task, subtask, status, note) => {
      if (!canTask(task, "can_manage_subtasks")) {
        toast("Subtask updates are not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId && subtask?.recordId) {
        const template = options.subtask_status_url_template;
        const url = template
          ? template.replace("__TASK__", task.recordId).replace("__SUBTASK__", subtask.recordId)
          : null;
        if (url) {
          apiJson(url, {
            method: "PATCH",
            body: JSON.stringify({ status, note: note || "Subtask status updated from Task Management drawer." }),
          })
            .then(body => { replaceFromServer(body.data); toast("Subtask status saved on task " + body.data.task_number + ".", "green"); })
            .catch(error => toast(error.message, "red"));
          return true;
        }
      }
      return false;
    };

    const logTaskTime = (task, minutes, note) => {
      if (!canTask(task, "can_log_time")) {
        toast("Time logging is not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId) {
        apiJson(taskRecordUrl(options.time_log_url_template, task), {
          method: "POST",
          body: JSON.stringify({
            minutes,
            logged_on: new Date().toISOString().slice(0, 10),
            note: note || "Timer session",
            source: "timer",
            metadata: { source: "task_management_drawer" },
          }),
        })
          .then(body => { replaceFromServer(body.data); toast("Time saved on task " + body.data.task_number + ".", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      return false;
    };

    const assignTaskFromDrawer = (task, toId, note) => {
      const serverUserId = serverIdFromUserKey(toId);
      if (!canTask(task, "can_assign")) {
        toast("Task assignment is not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId && serverUserId && options.assign_url_template) {
        apiJson(taskRecordUrl(options.assign_url_template, task), {
          method: "PATCH",
          body: JSON.stringify({
            assigned_to_user_id: serverUserId,
            note: note || "Assignee changed from Task Management drawer.",
          }),
        })
          .then(body => { replaceFromServer(body.data); toast("Task " + body.data.task_number + " assignee saved.", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      return false;
    };

    const toggleTaskWatcher = (task) => {
      if (!canTask(task, "can_update_watcher")) {
        toast("Watcher preference is not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId && options.watcher_url_template) {
        apiJson(taskRecordUrl(options.watcher_url_template, task), {
          method: "PATCH",
          body: JSON.stringify({
            action: "toggle",
            note: "Watcher preference changed from Task Management drawer.",
          }),
        })
          .then(body => { replaceFromServer(body.data); toast("Task " + body.data.task_number + " watcher preference saved.", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      toast("Watcher preference was not saved for your current access.", "orange");
      return false;
    };

    const saveTaskDependencies = (task, dependencyIds, note) => {
      if (!canTask(task, "can_update_dependencies")) {
        toast("Task dependencies are not available for your role on this task.", "orange");
        return false;
      }

      if (hasTaskApi && task?.recordId && options.dependencies_url_template) {
        apiJson(taskRecordUrl(options.dependencies_url_template, task), {
          method: "PATCH",
          body: JSON.stringify({
            dependency_task_ids: (dependencyIds || []).filter(Boolean),
            note: note || "Task dependencies updated from Task Management drawer.",
          }),
        })
          .then(body => { replaceFromServer(body.data); toast("Task " + body.data.task_number + " dependencies saved.", "green"); })
          .catch(error => toast(error.message, "red"));
        return true;
      }
      toast("Task dependencies were not saved for your current access.", "orange");
      return false;
    };

    // ---- transfer ----
    const doTransfer = (task, toId, reason, approval) => {
      const serverUserId = serverIdFromUserKey(toId);
      if (approval) {
        if (!canTask(task, "can_request_transfer")) {
          toast("Transfer approval request is not available for your role on this task.", "orange");
          return;
        }

        if (hasTaskApi && task?.recordId && serverUserId && options.transfer_request_url_template) {
          apiJson(taskRecordUrl(options.transfer_request_url_template, task), {
            method: "POST",
            body: JSON.stringify({
              assigned_to_user_id: serverUserId,
              reason,
              metadata: { source: "task_management_transfer_modal" },
            }),
          })
            .then(body => toast("Transfer approval request #" + body.data.id + " saved for " + U(toId).name + ".", "green"))
            .catch(error => toast(error.message, "red"));
          return;
        }
        toast("Transfer approval request was not saved because this task or user is not available for transfer.", "orange");
        return;
      }
      if (!canTask(task, "can_assign")) {
        toast("Task transfer is not available for your role on this task.", "orange");
        return;
      }

      if (hasTaskApi && task?.recordId && serverUserId) {
        apiJson(taskRecordUrl(options.assign_url_template, task), {
          method: "PATCH",
          body: JSON.stringify({ assigned_to_user_id: serverUserId, note: reason }),
        })
          .then(body => { replaceFromServer(body.data); toast("Task " + body.data.task_number + " reassigned to " + U(toId).name + ".", "green"); })
          .catch(error => toast(error.message, "red"));
        return;
      }

      toast("Task transfer was not saved. Assignment or transfer approval is not available for your role.", "orange");
    };

    // ---- bulk ----
    const bulkApiUnavailable = (msg) => {
      toast(msg + " was not saved. Bulk task actions are available only for saved task records.", "orange");
      setChecked(new Set());
    };

    const bulkUpdateSelected = (patch, msg, fallback) => {
      const selectedTasks = tasks.filter(t => checked.has(t.id));
      const recordIds = selectedTasks.map(t => t.recordId).filter(Boolean);
      const requiredPermission = patch && Object.prototype.hasOwnProperty.call(patch, "status") ? "can_update_status" : "can_update_details";
      if (selectedTasks.some(task => !canTask(task, requiredPermission))) {
        toast("One or more selected tasks cannot be updated by your role.", "orange");
        return;
      }

      if (hasTaskApi && options.bulk_update_url && selectedTasks.length > 0 && recordIds.length === selectedTasks.length) {
        apiJson(options.bulk_update_url, {
          method: "PATCH",
          body: JSON.stringify(Object.assign({
            task_ids: recordIds,
            note: msg + " from Task Management bulk action.",
          }, patch)),
        })
          .then(body => {
            (body.data || []).forEach(replaceFromServer);
            setChecked(new Set());
            toast((body.data || []).length + " task(s) updated.", "green");
          })
          .catch(error => toast(error.message, "red"));
        return;
      }

      bulkApiUnavailable(msg);
    };

    const bulkArchiveSelected = () => {
      const selectedTasks = tasks.filter(t => checked.has(t.id));
      const recordIds = selectedTasks.map(t => t.recordId).filter(Boolean);
      if (selectedTasks.some(task => !canTask(task, "can_archive"))) {
        toast("One or more selected tasks cannot be archived by your role.", "orange");
        return;
      }

      if (hasTaskApi && options.bulk_archive_url && selectedTasks.length > 0 && recordIds.length === selectedTasks.length) {
        apiJson(options.bulk_archive_url, {
          method: "PATCH",
          body: JSON.stringify({ task_ids: recordIds, note: "Tasks archived from Task Management bulk action." }),
        })
          .then(body => {
            const archivedIds = new Set((body.data.tasks || []).map(t => t.id));
            setTasks(ts => ts.filter(t => !archivedIds.has(t.recordId)));
            setChecked(new Set());
            toast((body.data.count || archivedIds.size) + " task(s) archived.", "green");
          })
          .catch(error => toast(error.message, "red"));
        return;
      }
      bulkApiUnavailable("Archive");
    };

    React.useEffect(() => { const h = () => setFilterMenu(false); if (filterMenu) { window.addEventListener("click", h); return () => window.removeEventListener("click", h); } }, [filterMenu]);
    React.useEffect(() => { setChecked(new Set()); }, [scope]);
    React.useEffect(() => {
      if (!fullScreen) return undefined;
      const onKeyDown = ev => {
        if (ev.key === "Escape") setFullScreen(false);
      };
      document.addEventListener("keydown", onKeyDown);
      return () => document.removeEventListener("keydown", onKeyDown);
    }, [fullScreen]);

    // ---------- RAIL ----------
    const railItem = (id, label, icon, opts = {}) => {
      const c = opts.count != null ? opts.count : scopeOf ? tasks.filter(t => scopeOf(t, id)).length : 0;
      return e("div", { key: id, className: "tm-nav-item" + (scope === id ? " on" : ""), onClick: () => { setScope(id); if (["dashboard", "activity", "reports", "analytics", "templates", "settings"].includes(id)) {} }, role: "button", tabIndex: 0 },
        e(Icon, { name: icon, size: 16 }), e("span", { style: { flex: 1 } }, label),
        opts.special ? null : (opts.alert ? e("span", { className: "tm-ndot" }, c) : c > 0 ? e("span", { className: "tm-ncount" }, c) : null));
    };

    const overdueCount = tasks.filter(t => t.overdue || t.due === "Overdue").length;
    const tasksAwaitingTransferApproval = tasks.filter(t => pendingTransfers(t).length > 0);
    const approvalCount = tasksAwaitingTransferApproval.length;
    const permissionSummary = options?.permission_summary || {};
    const canManageTasks = !!options?.can_manage;
    const canManageTaskSettings = !!options?.can_manage_settings;
    const canViewAllTaskScopes = !!permissionSummary.view || canManageTasks;
    const selfServiceTaskOnly = !!permissionSummary.create && !canViewAllTaskScopes && !canManageTasks;

    const rail = e("div", { className: "tm-rail" + (railOpen ? "" : " collapsed") },
      e("div", { className: "tm-rail-head" }, e("button", { className: "tm-new", disabled: !canCreateTasks, title: canCreateTasks ? "Create task" : "Task creation is not available for this role", onClick: () => openCreate({}) }, e(Icon, { name: "plus", size: 17 }), "New task")),
      e("div", { className: "tm-nav" },
        e("div", { className: "tm-nav-sec" }, "Workspace"),
        e("div", { className: "tm-nav-item" + (scope === "dashboard" ? " on" : ""), onClick: () => setScope("dashboard") }, e(Icon, { name: "grid", size: 16 }), e("span", { style: { flex: 1 } }, "Dashboard")),
        railItem("mine", "My Tasks", "tasks"),
        railItem("toMe", "Assigned to Me", "userPlus"),
        railItem("byMe", "Assigned by Me", "send"),
        canViewAllTaskScopes && railItem("team", "Team Tasks", "users"),
        canViewAllTaskScopes && railItem("dept", "Department", "building"),
        canViewAllTaskScopes && railItem("all", "All Tasks", "layers"),
        e("div", { className: "tm-nav-sec" }, "Due & Status"),
        railItem("today", "Due Today", "calendar"),
        railItem("week", "Due This Week", "calendar"),
        e("div", { className: "tm-nav-item" + (scope === "overdue" ? " on" : ""), onClick: () => setScope("overdue") }, e(Icon, { name: "alert", size: 16 }), e("span", { style: { flex: 1 } }, "Overdue"), overdueCount > 0 && e("span", { className: "tm-ndot" }, overdueCount)),
        railItem("pending", "Pending", "clock"),
        railItem("completed", "Completed", "circleCheck"),
        railItem("archived", "Archived", "archive"),
        !selfServiceTaskOnly && e("div", { className: "tm-nav-sec" }, "Insights"),
        !selfServiceTaskOnly && e("div", { className: "tm-nav-item" + (scope === "activity" ? " on" : ""), onClick: () => setScope("activity") }, e(Icon, { name: "activity", size: 16 }), e("span", { style: { flex: 1 } }, "Activity Center")),
        !selfServiceTaskOnly && e("div", { className: "tm-nav-item" + (scope === "reports" ? " on" : ""), onClick: () => setScope("reports") }, e(Icon, { name: "doc", size: 16 }), e("span", { style: { flex: 1 } }, "Reports")),
        !selfServiceTaskOnly && e("div", { className: "tm-nav-item" + (scope === "analytics" ? " on" : ""), onClick: () => setScope("analytics") }, e(Icon, { name: "chart", size: 16 }), e("span", { style: { flex: 1 } }, "Analytics")),
        canManageTasks && e("div", { className: "tm-nav-sec" }, "Library"),
        canManageTasks && e("div", { className: "tm-nav-item" + (scope === "templates" ? " on" : ""), onClick: () => setScope("templates") }, e(Icon, { name: "template", size: 16 }), e("span", { style: { flex: 1 } }, "Templates")),
        canManageTaskSettings && e("div", { className: "tm-nav-item" + (scope === "settings" ? " on" : ""), onClick: () => setScope("settings") }, e(Icon, { name: "gear", size: 16 }), e("span", { style: { flex: 1 } }, "Settings"))));

    const scopeLabels = { dashboard: "Task Dashboard", mine: "My Tasks", toMe: "Assigned to Me", byMe: "Assigned by Me", team: "Team Tasks", dept: "Department Tasks", all: "All Tasks", today: "Due Today", week: "Due This Week", overdue: "Overdue Tasks", pending: "Pending Tasks", completed: "Completed Tasks", archived: "Archived Tasks", activity: "Activity Center", reports: "Reports", analytics: "Analytics", templates: "Templates", settings: "Settings" };
    const taskStatusNote = apiState.connected
      ? "Task updates are saved and shared with assigned users."
      : apiState.loading
        ? "Loading task records…"
        : taskApiRequiredMessage + " " + (apiState.error || "Task records are not connected for this session.");
    const compactTaskBar = (count = null, modeControls = null, hasOptions = false) => e("div", { className: "tm-compactbar" },
      e("button", { className: "tm-tbtn", "aria-expanded": railOpen ? "true" : "false", onClick: () => setRailOpen(open => !open) }, e(Icon, { name: "layers", size: 15 }), railOpen ? "Hide workspace" : "Show workspace"),
      e("div", { className: "tm-compact-title" }, e("b", null, scopeLabels[scope] || "Tasks"), count !== null && e("span", null, count + " task" + (count === 1 ? "" : "s"))),
      hasOptions && checked.size > 0 && e("button", { className: "tm-selected-chip", onClick: () => setOptionsOpen(true) }, checked.size + " selected"),
      e("div", { style: { flex: 1 } }),
      modeControls,
      hasOptions && e("button", { className: "tm-tbtn", "aria-expanded": optionsOpen ? "true" : "false", onClick: () => { setOptionsOpen(open => !open); setFilterMenu(false); } }, e(Icon, { name: "chevD", size: 14, style: { transform: optionsOpen ? "rotate(180deg)" : "none", transition: ".15s" } }), optionsOpen ? "Hide options" : "Show options"),
      e("button", { className: "tm-tbtn", onClick: () => setFullScreen(v => !v) }, e(Icon, { name: fullScreen ? "x" : "expand", size: 15 }), fullScreen ? "Exit Full Screen" : "Full Screen"),
      e("button", { className: "tm-new compact", disabled: !canCreateTasks, title: canCreateTasks ? "Create task" : "Task creation is not available for this role", onClick: () => openCreate({}) }, e(Icon, { name: "plus", size: 16 }), "New task"));
    const taskOptionsPanel = (...children) => e("div", { className: "tm-options-panel" + (optionsOpen ? "" : " closed") }, children);

    // ---------- DASHBOARD ----------
    const dashboard = () => {
      const total = tasks.length;
      const stat = (label, n, icon, tone) => e(Stat, { label, value: n, icon, tone });
      const statusData = [
        { label: "Completed", value: tasks.filter(isDone).length, color: "#15a657" },
        { label: "In Progress", value: tasks.filter(t => t.status === "inprogress").length, color: "#F6740C" },
        { label: "Open / To Do", value: tasks.filter(isOpen).length, color: "#2570eb" },
        { label: "Blocked", value: tasks.filter(t => ["onhold", "waitinfo", "waitdep", "rejected"].includes(t.status)).length, color: "#dc2f3a" },
        { label: "Approval", value: approvalCount, color: "#e08600" },
      ].filter(d => d.value > 0);
      // workload per active user
      const workers = TM.users.filter(u => tasks.some(t => t.assignees.includes(u.id)));
      const buckets = [["done", "#15a657"], ["inprogress", "#F6740C"], ["open", "#2570eb"], ["blocked", "#dc2f3a"]];
      const bucketOf = (t) => isDone(t) ? "done" : t.status === "inprogress" ? "inprogress" : ["onhold", "waitinfo", "waitdep", "rejected"].includes(t.status) ? "blocked" : "open";
      const completedTrend = completionTrend(tasks);

      return e("div", { className: "tm-body pad" },
        e("div", { className: "sys-note", style: { marginBottom: 14 } }, e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
          apiState.connected
            ? "Task updates are saved and shared with assigned users."
            : apiState.loading
              ? "Loading task records…"
              : taskApiRequiredMessage + " " + (apiState.error || "Task records are not available for this session.")),
        e("div", { className: "row between", style: { marginBottom: 18, flexWrap: "wrap", gap: 10 } },
          e("div", null, e("h1", { className: "page-title" }, "Task Dashboard"), e("div", { className: "page-sub" }, "Operational execution across all teams and projects.")),
          e("div", { className: "row gap-2" }, e(Button, { icon: "template", onClick: () => setScope("templates"), children: "Templates" }))),
        e("div", { className: "tm-dash-grid", style: { marginBottom: 16 } },
          stat("Total Tasks", total, "tasks", "accent"),
          stat("In Progress", tasks.filter(t => t.status === "inprogress").length, "play", "violet"),
          stat("Completed", tasks.filter(isDone).length, "check", "green"),
          stat("Overdue", overdueCount, "alert", "red"),
          stat("Due Today", tasks.filter(t => t.due === "Today").length, "calendar", "orange"),
          stat("Awaiting Approval", approvalCount, "shield", "blue")),
        e("div", { className: "grid", style: { gridTemplateColumns: "1.6fr 1fr", gap: 16, marginBottom: 16 } },
          e(Card, { title: "Completion trend", sub: "Tasks completed per week from available task records", className: "card-pad" },
            e(LineChart, { series: [{ data: completedTrend.data, color: "var(--accent)", fill: true }], height: 200, labels: completedTrend.labels })),
          e(Card, { title: "By status", className: "card-pad" },
            e("div", { className: "col", style: { alignItems: "center", display: "flex", flexDirection: "column", gap: 14 } },
              e(Donut, { data: statusData, size: 140, thickness: 20, center: e("div", null, e("div", { className: "mono", style: { fontSize: 22, fontWeight: 800 } }, total), e("div", { className: "faint", style: { fontSize: 10.5, fontWeight: 700 } }, "tasks")) }),
              e("div", { style: { display: "flex", flexWrap: "wrap", gap: "6px 12px", justifyContent: "center" } }, statusData.map(d => e("div", { key: d.label, className: "row gap-2" }, e("span", { style: { width: 9, height: 9, borderRadius: 3, background: d.color } }), e("span", { style: { fontSize: 11.5, fontWeight: 600 } }, d.label, " ", e("b", null, d.value)))))))),
        e("div", { className: "grid", style: { gridTemplateColumns: "1.4fr 1fr", gap: 16 } },
          e(Card, { title: "Team workload", sub: "Active tasks by member & status", className: "card-pad" },
            workers.map(u => {
              const mine = tasks.filter(t => t.assignees.includes(u.id) && t.status !== "cancelled");
              const total2 = mine.length || 1;
              return e("div", { key: u.id, className: "tm-workload-row" },
                e("div", { className: "wl-name" }, e(Avatar, { name: u.name, color: u.color, size: 24 }), e("span", { style: { whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" } }, u.name.split(" ")[0])),
                e("div", { className: "tm-wl-bar" }, buckets.map(([b, c]) => { const n = mine.filter(t => bucketOf(t) === b).length; return n > 0 && e("div", { key: b, className: "tm-wl-seg", style: { width: (n / total2 * 100) + "%", background: c }, title: b + ": " + n }); })),
                e("span", { className: "mono", style: { fontSize: 12, fontWeight: 800, width: 24, textAlign: "right" } }, mine.length));
            })),
          e(Card, { title: "Awaiting your approval", sub: approvalCount + " pending", className: "card-pad" },
            tasksAwaitingTransferApproval.length === 0 ? e(Empty, { icon: "check", title: "All clear", sub: "No approvals pending." })
            : tasksAwaitingTransferApproval.map(t => e("div", { key: t.id, className: "tm-sub-row", style: { cursor: "pointer" }, onClick: () => openTask(t.id) },
                e("div", { style: { flex: 1, minWidth: 0 } }, e("div", { style: { fontWeight: 700, fontSize: 12.5, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" } }, t.title), e("div", { className: "faint", style: { fontSize: 11 } }, "from " + U(t.owner).name + " · " + t.due)),
                e(PriPill, { id: t.priority }))))));
    };

    // ---------- standalone screens ----------
    if (scope === "dashboard") return e("div", { className: "tm" + (fullScreen ? " tm-fullscreen" : "") }, rail, e("div", { className: "tm-main" }, compactTaskBar(tasks.length), dashboard()), modals());
    if (scope === "templates") return e("div", { className: "tm" + (fullScreen ? " tm-fullscreen" : "") }, rail, e("div", { className: "tm-main" }, compactTaskBar(tasks.length), e("div", { className: "tm-body pad" }, e(window.TMTemplates, { onUse: useTemplate, toast }))), modals());
    if (scope === "activity") return e("div", { className: "tm" + (fullScreen ? " tm-fullscreen" : "") }, rail, e("div", { className: "tm-main" }, compactTaskBar(tasks.length), e("div", { className: "tm-body pad" }, e(window.TMActivityCenter, { tasks, onOpen: openTask, toast }))), modals());
    if (scope === "reports") return e("div", { className: "tm" + (fullScreen ? " tm-fullscreen" : "") }, rail, e("div", { className: "tm-main" }, compactTaskBar(tasks.length), e("div", { className: "tm-body pad" }, e(window.TMReports, { tasks, onExportCsv: exportTaskCsv, onExportPdf: exportTaskPdf, toast }))), modals());
    if (scope === "analytics") return e("div", { className: "tm" + (fullScreen ? " tm-fullscreen" : "") }, rail, e("div", { className: "tm-main" }, compactTaskBar(tasks.length), e("div", { className: "tm-body pad" }, e(window.TMAnalytics, { tasks }))), modals());
    if (scope === "settings") return e("div", { className: "tm" + (fullScreen ? " tm-fullscreen" : "") }, rail, e("div", { className: "tm-main" }, compactTaskBar(tasks.length), e("div", { className: "tm-body pad" }, e(window.TMSettings, { toast }))), modals());

    // ---------- task list scopes (kanban / list / calendar) ----------
    const modeControls = e("div", { className: "tm-viewseg" },
        e("button", { className: viewMode === "kanban" ? "on" : "", onClick: () => setViewMode("kanban") }, e(Icon, { name: "board", size: 15 }), "Board"),
        e("button", { className: viewMode === "list" ? "on" : "", onClick: () => setViewMode("list") }, e(Icon, { name: "listview", size: 15 }), "List"),
        e("button", { className: viewMode === "calendar" ? "on" : "", onClick: () => setViewMode("calendar") }, e(Icon, { name: "calendar", size: 15 }), "Calendar"));

    const toolbar = taskOptionsPanel(
      e("div", { className: "tm-search" }, e(Icon, { name: "search", size: 15 }), e("input", { value: q, placeholder: "Search tasks…", onChange: ev => setQ(ev.target.value) }), q && e("button", { onClick: () => setQ(""), style: { border: "none", background: "none", color: "var(--text-3)", cursor: "pointer", display: "grid" } }, e(Icon, { name: "x", size: 13 }))),
      e("div", { style: { position: "relative" } },
        e("button", { className: "tm-tbtn" + (priFilter ? " on" : ""), onClick: ev => { ev.stopPropagation(); setFilterMenu(o => !o); } }, e(Icon, { name: "filter", size: 15 }), priFilter ? TM.PR[priFilter].label : "Priority", e(Icon, { name: "chevD", size: 13 })),
        filterMenu && e("div", { className: "tm-menu", style: { top: 40, right: 0, minWidth: 160 } },
          e("div", { className: "tm-mitem", onClick: () => { setPriFilter(null); setFilterMenu(false); } }, "All priorities", !priFilter && e(Icon, { name: "check", size: 14, style: { marginLeft: "auto" } })),
          TM.priorities.map(p => e("div", { key: p.id, className: "tm-mitem", onClick: () => { setPriFilter(p.id); setFilterMenu(false); } }, e(PriPill, { id: p.id }), priFilter === p.id && e(Icon, { name: "check", size: 14, style: { marginLeft: "auto" } }))))),
      e("button", { className: "tm-iconbtn", title: "Export CSV", onClick: exportTaskCsv }, e(Icon, { name: "download", size: 16 })),
      e("div", { className: "sys-note", style: { margin: "0", flex: "1 1 100%" } }, e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), taskStatusNote));

    const bulkBar = checked.size > 0 && e("div", { className: "tm-bulk" },
      e("span", { className: "bc" }, checked.size, " selected"),
      e("button", { className: "tm-bbtn", onClick: () => bulkUpdateSelected({ status: "completed" }, "Completed", t => ({ ...t, status: "completed", column: "done", progress: 100 })) }, e(Icon, { name: "check", size: 14 }), "Complete"),
      e("button", { className: "tm-bbtn", onClick: () => bulkUpdateSelected({ priority: "high" }, "Set High priority", t => ({ ...t, priority: "high" })) }, e(Icon, { name: "flag", size: 14 }), "High priority"),
      e("button", { className: "tm-bbtn", onClick: () => {
        const selected = tasks.filter(t => checked.has(t.id));
        if (checked.size === 1 && selected[0]?.recordId) { setTransferring(selected[0]); }
        else toast("Transfer requires one saved task record.", "orange");
      } }, e(Icon, { name: "swap", size: 14 }), "Transfer"),
      e("button", { className: "tm-bbtn", onClick: bulkArchiveSelected }, e(Icon, { name: "archive", size: 14 }), "Archive"),
      e("button", { className: "tm-bbtn", style: { marginLeft: "auto" }, onClick: () => setChecked(new Set()) }, e(Icon, { name: "x", size: 14 }), "Clear"));

    const viewBody = viewMode === "kanban"
      ? e(window.TMKanbanView, { tasks: visible, onOpen: openTask, onMove: moveTask, onAddTo: (col) => openCreate({ column: col }), toast })
      : viewMode === "list"
        ? e("div", { className: "tm-body" }, e(window.TMListView, { tasks: visible, onOpen: openTask, onUpdate: update, checked, setChecked, toast }))
        : e("div", { className: "tm-body" }, e(window.TMCalendarView, { tasks: visible, onOpen: openTask, toast }));

    return e("div", { className: "tm" + (fullScreen ? " tm-fullscreen" : "") }, rail,
        e("div", { className: "tm-main" }, compactTaskBar(visible.length, modeControls, true), toolbar, optionsOpen && bulkBar,
        viewMode === "kanban" ? e("div", { className: "tm-body" }, viewBody) : viewBody),
      modals());

    function modals() {
      return e(React.Fragment, null,
        openTaskObj && e(window.TMDrawer, {
          task: openTaskObj,
          availableTasks: tasks,
          onClose: () => setOpenId(null),
          onTaskDeepLink: copyTaskDeepLink,
          onOpenCrmRecord: openTaskCrmRecord,
          onTaskUpdate: canTask(openTaskObj, "can_update_details") ? updateTaskDetails : null,
          onTaskArchive: canTask(openTaskObj, "can_archive") ? archiveTask : null,
          onStatusChange: canTask(openTaskObj, "can_update_status") ? changeTaskStatus : null,
          onChecklistSave: canTask(openTaskObj, "can_manage_checklist") ? saveTaskChecklist : null,
          onCommentCreate: canTask(openTaskObj, "can_comment") ? addTaskComment : null,
          onSubtaskCreate: canTask(openTaskObj, "can_manage_subtasks") ? createTaskSubtask : null,
          onSubtaskStatusChange: canTask(openTaskObj, "can_manage_subtasks") ? updateTaskSubtaskStatus : null,
          onTimeLogCreate: canTask(openTaskObj, "can_log_time") ? logTaskTime : null,
          onAssigneeChange: canTask(openTaskObj, "can_assign") ? assignTaskFromDrawer : null,
          onWatcherToggle: canTask(openTaskObj, "can_update_watcher") ? toggleTaskWatcher : null,
          onDependenciesSave: canTask(openTaskObj, "can_update_dependencies") ? saveTaskDependencies : null,
          onTransfer: (canTask(openTaskObj, "can_request_transfer") || canTask(openTaskObj, "can_assign")) ? (t) => setTransferring(t) : null,
          toast,
        }),
        creating && e(window.TMCreateModal, { prefill: creating.column ? {} : creating, onClose: () => setCreating(null), onCreate: (f) => createTask(f, creating.column), toast }),
        transferring && e(window.TMTransferModal, { task: transferring, onClose: () => setTransferring(null), onConfirm: doTransfer, toast }));
    }
  }

  window.TaskManagement = TaskManagement;
})();
