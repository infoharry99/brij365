/* ============================================================
   Builder360 — Calendar Management shared configuration → window.CAL
   Event rows are not seeded here. The active calendar screen loads
   persisted Laravel collaboration events and registers them as
   window.CAL.activeEvents for client-side conflict hints.
   ============================================================ */
(function () {
  const TM = window.TM;
  const now = new Date();
  const Y = now.getFullYear();
  const MO = now.getMonth();
  const NOW = now;
  const mk = (day, h, min) => new Date(Y, MO, day, h, min || 0);

  const types = [
    { id: "meeting", label: "Meeting", color: "#F6740C", icon: "users" },
    { id: "call", label: "Call", color: "#0ea5a4", icon: "phone" },
    { id: "followup", label: "Follow-up", color: "#e08600", icon: "reply" },
    { id: "demo", label: "Demo", color: "#7c3aed", icon: "video" },
    { id: "appointment", label: "Appointment", color: "#2570eb", icon: "calClock" },
    { id: "deadline", label: "Task Deadline", color: "#dc2f3a", icon: "flag" },
    { id: "internal", label: "Internal", color: "#64748b", icon: "building" },
    { id: "client", label: "Client Event", color: "#15a657", icon: "tag" },
    { id: "reminder", label: "Reminder", color: "#db2777", icon: "bellRing" },
  ];
  const T = Object.fromEntries(types.map(t => [t.id, t]));

  const statuses = [
    { id: "scheduled", label: "Scheduled", badge: "b-blue" },
    { id: "pending", label: "Pending", badge: "b-slate" },
    { id: "completed", label: "Completed", badge: "b-green" },
    { id: "missed", label: "Missed", badge: "b-red" },
    { id: "cancelled", label: "Cancelled", badge: "b-red" },
    { id: "rescheduled", label: "Rescheduled", badge: "b-violet" },
    { id: "overdue", label: "Overdue", badge: "b-orange" },
  ];
  const ST = Object.fromEntries(statuses.map(s => [s.id, s]));

  const priorities = [
    { id: "low", label: "Low", color: "#64748b" },
    { id: "medium", label: "Medium", color: "#2570eb" },
    { id: "high", label: "High", color: "#e08600" },
    { id: "urgent", label: "Urgent", color: "#dc2f3a" },
  ];
  const PR = Object.fromEntries(priorities.map(p => [p.id, p]));

  const recurrenceLabels = {
    none: "Does not repeat",
    daily: "Daily",
    weekly: "Weekly",
    monthly: "Monthly",
    yearly: "Yearly",
    custom: "Custom",
  };

  const eventRows = () => Array.isArray(window.CAL?.activeEvents) ? window.CAL.activeEvents : [];

  function conflictsFor(userId, start, end, excludeId) {
    return eventRows().filter(event =>
      event.id !== excludeId &&
      Array.isArray(event.assignees) &&
      event.assignees.includes(userId) &&
      !["cancelled", "missed"].includes(event.status) &&
      start < event.end && end > event.start
    );
  }

  const reminderOptions = [
    { v: 0, label: "At event time" },
    { v: 5, label: "5 min before" },
    { v: 10, label: "10 min before" },
    { v: 15, label: "15 min before" },
    { v: 30, label: "30 min before" },
    { v: 60, label: "1 hour before" },
    { v: 1440, label: "1 day before" },
  ];

  window.CAL = {
    NOW, Y, MO, mk, types, T, statuses, ST, priorities, PR,
    events: [],
    activeEvents: [],
    recurrenceLabels, conflictsFor, reminderOptions,
    users: TM.users, U: TM.U, teams: TM.teams, departments: TM.departments, me: TM.me,
  };
})();
