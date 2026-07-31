/* ============================================================
   Builder360 — Task Management runtime configuration → window.TM
   This file intentionally does not seed local tasks, people,
   activity, or reusable workflows. Visible Task Management rows
   must be loaded from the Laravel collaboration APIs and governed
   System Settings only.
   ============================================================ */
(function () {
  const statuses = [
    { id: "draft", label: "Draft", badge: "b-slate", col: "backlog" },
    { id: "open", label: "Open", badge: "b-blue", col: "todo" },
    { id: "assigned", label: "Assigned", badge: "b-blue", col: "todo" },
    { id: "accepted", label: "Accepted", badge: "b-violet", col: "todo" },
    { id: "inprogress", label: "In Progress", badge: "b-accent", col: "inprogress" },
    { id: "onhold", label: "On Hold", badge: "b-slate", col: "blocked" },
    { id: "waitinfo", label: "Waiting Info", badge: "b-orange", col: "blocked" },
    { id: "waitdep", label: "Waiting Dependency", badge: "b-orange", col: "blocked" },
    { id: "review", label: "Under Review", badge: "b-violet", col: "review" },
    { id: "waitapproval", label: "Waiting Approval", badge: "b-orange", col: "approval" },
    { id: "completed", label: "Completed", badge: "b-green", col: "done" },
    { id: "rejected", label: "Rejected", badge: "b-red", col: "blocked" },
    { id: "cancelled", label: "Cancelled", badge: "b-red", col: "cancelled" },
    { id: "archived", label: "Archived", badge: "b-slate", col: "done" },
  ];
  const ST = Object.fromEntries(statuses.map(s => [s.id, s]));

  const priorities = [
    { id: "critical", label: "Critical", color: "#dc2f3a", tone: "red" },
    { id: "high", label: "High", color: "#e08600", tone: "orange" },
    { id: "medium", label: "Medium", color: "#2570eb", tone: "blue" },
    { id: "low", label: "Low", color: "#64748b", tone: "slate" },
  ];
  const PR = Object.fromEntries(priorities.map(p => [p.id, p]));

  const columns = [
    { id: "backlog", label: "Backlog", color: "#64748b" },
    { id: "todo", label: "To Do", color: "#2570eb" },
    { id: "inprogress", label: "In Progress", color: "#F6740C" },
    { id: "review", label: "Review", color: "#7c3aed" },
    { id: "approval", label: "Approval", color: "#e08600" },
    { id: "blocked", label: "Blocked", color: "#dc2f3a" },
    { id: "done", label: "Completed", color: "#15a657" },
    { id: "cancelled", label: "Cancelled", color: "#94a3b8" },
  ];

  const categories = ["Sales", "Site Execution", "Procurement", "Finance", "Legal / RERA", "HR", "Marketing", "Customer Success", "Operations"];
  const departments = categories.map((name, index) => ({
    id: name.toLowerCase().replace(/[^a-z0-9]+/g, "-"),
    name,
    color: ["#15a657", "#e08600", "#7c3aed", "#0ea5a4", "#dc2f3a", "#db2777", "#2570eb", "#F6740C", "#64748b"][index] || "#64748b",
  }));

  window.TM = {
    users: [],
    U: {},
    me: null,
    teams: [],
    departments,
    statuses,
    ST,
    priorities,
    PR,
    columns,
    categories,
    tagPool: [],
    tasks: [],
    templates: [],
    activityFeed: [],
    permRoles: [],
    permActions: [],
    permMatrix: {},
  };
})();
