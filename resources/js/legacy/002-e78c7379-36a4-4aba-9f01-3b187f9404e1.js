/* ============================================================
   Builder360 — Chat Connect runtime configuration → window.CHAT
   This file intentionally does not seed local conversations,
   messages, people, files, analytics, audit rows, reports, or
   permissions. Visible chat content must come from Laravel
   collaboration message APIs and governed settings only.
   ============================================================ */
(function () {
  const people = {};
  const pres = {
    online:  { c: "var(--green)",  label: "Online" },
    away:    { c: "var(--orange)", label: "Away" },
    busy:    { c: "var(--red)",    label: "Busy" },
    meeting: { c: "var(--violet)", label: "In a meeting" },
    offline: { c: "var(--text-3)", label: "Offline" },
  };

  window.CHAT = {
    people,
    pres,
    conversations: [],
    M: {},
    groupMembers: {},
    sharedFiles: [],
    pinnedMsgs: [],
    analytics: { stats: [], topChannels: [], inactive: [] },
    auditLog: [],
    reported: [],
    permMatrix: { cols: [], rows: [] },
    emojis: ["👍", "❤️", "✅", "🎉", "🙏", "🔥"],
  };
})();
