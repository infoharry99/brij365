const React = window.React;

/* ============================================================
   Builder360 — Chat Connect: details panel + admin console
   exposes window.ChatDetails, window.ChatAdmin
   ============================================================ */
(function () {
  const { Icon, Avatar, Badge, Stat, Card, HBars, Gauge, Button, Seg, PageHead } = window;
  const e = React.createElement;
  const CHAT = window.CHAT;
  const P = CHAT.people, PR = CHAT.pres;
  const PAv = () => window.__chatPAv;
  const roleTone = window.__chatRoleTone;

  function PresAv({ id, size = 36 }) {
    const p = P[id]; if (!p) return null;
    return e("div", { className: "pres-wrap", style: { width: size, height: size } },
      e(Avatar, { name: p.name, color: p.color, size }),
      e("span", { className: "pres-dot", style: { background: (PR[p.pres] || PR.offline).c } }));
  }

  // ================= DETAILS PANEL =================
  function ChatDetails({ conv, tab, setTab, toast }) {
    const dm = conv.kind === "dm";
    const dmPerson = dm ? P[conv.who] : null;
    const memberIds = CHAT.groupMembers[conv.id] || (dm && conv.who ? [conv.who] : []);
    const tabs = dm
      ? [["about", "About"], ["files", "Files"]]
      : [["about", "About"], ["members", "Members"], ["files", "Files"], ["pinned", "Pinned"]];
    const curTab = tabs.find(t => t[0] === tab) ? tab : "about";

    const hero = e("div", { className: "det-hero" },
      dm ? e("div", { style: { display: "grid", placeItems: "center", marginBottom: 12 } }, e(PresAv, { id: conv.who, size: 66 }))
         : e("div", { className: "dh-ic", style: { background: conv.color } }, e(Icon, { name: conv.icon, size: 28 })),
      e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 18, letterSpacing: "-.02em" } }, conv.name),
      e("div", { className: "faint", style: { fontSize: 12.5, fontWeight: 600, marginTop: 3 } }, dm ? (dmPerson?.title || "Collaboration participant") : conv.sub),
      !dm && e("div", { className: "row", style: { justifyContent: "center", gap: 18, marginTop: 14 } },
        e("div", { style: { textAlign: "center" } }, e("div", { className: "mono", style: { fontWeight: 800, fontSize: 17 } }, conv.members), e("div", { className: "faint", style: { fontSize: 10.5, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".05em" } }, "Members")),
        e("div", { style: { textAlign: "center" } }, e("div", { className: "mono", style: { fontWeight: 800, fontSize: 17, color: "var(--green)" } }, memberIds.filter(i => P[i] && P[i].pres === "online").length), e("div", { className: "faint", style: { fontSize: 10.5, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".05em" } }, "Online"))));

    const aboutTab = e("div", null,
      hero,
      e("div", { className: "det-sec" },
        e("div", { className: "det-label" }, dm ? "Status" : "Description"),
        e("div", { style: { fontSize: 13, color: "var(--text-2)", lineHeight: 1.55 } },
          dm ? ((PR[dmPerson?.pres] || PR.offline).label + " · participant available in this conversation") : "Coordination channel for " + conv.name + ". Daily updates, decisions, files and pinned resources appear when available.")),
      !dm && e("div", { className: "det-sec" },
        e("div", { className: "det-label" }, "Type & Visibility"),
        e("div", { className: "row gap-2", style: { flexWrap: "wrap" } },
          e("span", { className: "tag" }, e(Icon, { name: conv.icon, size: 13 }), conv.kind === "announce" ? "Announcement" : conv.kind === "project" ? "Project group" : conv.kind === "team" ? "Team group" : conv.kind === "dept" ? "Department" : "Custom group"),
          e("span", { className: "tag" }, e(Icon, { name: "shield", size: 13 }), conv.guest ? "Invite-only · guest" : "Private"))),
      e("div", { className: "det-sec" },
        e("div", { className: "det-label" }, "Notification preferences"),
        [["All messages", true], ["Mentions only", false], ["Mute conversation", false]].map((o, i) =>
          e("label", { key: i, className: "row between", style: { padding: "7px 0", cursor: "not-allowed", opacity: .72, fontSize: 13, fontWeight: 600 } },
            o[0], e("input", { type: "radio", name: "notif", defaultChecked: o[1], disabled: true, style: { accentColor: "var(--accent)" } })))),
      e("div", { className: "det-sec", style: { borderBottom: "none", display: "flex", flexDirection: "column", gap: 8 } },
        e(Button, { variant: "ghost", icon: "bell", disabled: true, "aria-disabled": true, onClick: () => toast("Mute preferences are not available for this conversation yet.", "orange"), style: { justifyContent: "flex-start", cursor: "not-allowed", opacity: .68 }, children: "Mute for 8 hours" }),
        e(Button, { variant: "ghost", icon: "archive", disabled: true, "aria-disabled": true, onClick: () => toast("Conversation archiving is not available yet: " + conv.name, "orange"), style: { justifyContent: "flex-start", cursor: "not-allowed", opacity: .68 }, children: "Archive conversation" }),
        !dm && e("button", { className: "btn btn-ghost", disabled: true, "aria-disabled": true, style: { justifyContent: "flex-start", color: "var(--red)", cursor: "not-allowed", opacity: .68 }, onClick: () => toast("Leaving groups is not available yet: " + conv.name, "orange") }, e(Icon, { name: "x", size: 15 }), "Leave group")));

    const membersTab = e("div", null,
      e("div", { className: "det-sec", style: { display: "flex", gap: 8 } },
        e(Button, { sm: true, variant: "primary", icon: "plus", disabled: true, "aria-disabled": true, onClick: () => toast("Member invites are not available yet: " + conv.name, "orange"), children: "Add member" }),
        e(Button, { sm: true, icon: "shield", disabled: true, "aria-disabled": true, onClick: () => toast("Chat access management is not available yet.", "orange"), children: "Access" })),
      memberIds.length ? memberIds.map((id, i) => {
        const p = P[id]; if (!p) return null;
        const tone = roleTone(p.role);
        const isAdmin = ["aditya", "rajesh", "priya", "imran"].includes(id);
        return e("div", { key: i, className: "member-row" },
          e(PresAv, { id, size: 36 }),
          e("div", { style: { flex: 1, minWidth: 0 } },
            e("div", { className: "row gap-2" }, e("span", { style: { fontWeight: 700, fontSize: 13 } }, p.name), isAdmin && e(Badge, { tone: "b-accent" }, "Admin")),
            e("div", { className: "faint", style: { fontSize: 11.5, fontWeight: 600 } }, p.title)),
          p.guest && e(Badge, { tone: "b-orange" }, "Guest"));
      }) : e("div", { style: { padding: 30, textAlign: "center", color: "var(--text-3)", fontSize: 13 } }, "No member list is available for this conversation."));

    const filesTab = e("div", null,
      e("div", { className: "det-sec", style: { display: "flex", gap: 8 } },
        ["All", "Docs", "Images", "Links"].map((f, i) => e("span", { key: i, className: "tag", style: i === 0 ? { background: "var(--accent-soft)", color: "var(--accent)", borderColor: "transparent" } : {} }, f))),
      CHAT.sharedFiles.length ? CHAT.sharedFiles.map((f, i) => e("div", { key: i, className: "file-row", onClick: () => toast("Shared file opening is not available for this file: " + f.name, "orange"), style: { cursor: "pointer" } },
        e("div", { className: "attach-ic", style: { width: 36, height: 36, background: f.color } }, e(Icon, { name: f.ic, size: 16 })),
        e("div", { style: { flex: 1, minWidth: 0 } },
          e("div", { className: "attach-name", style: { fontSize: 12.5 } }, f.name),
          e("div", { className: "faint", style: { fontSize: 11, fontWeight: 600 } }, f.by + " · " + f.t)),
        e("span", { className: "faint mono", style: { fontSize: 11 } }, f.size))) : e("div", { style: { padding: 30, textAlign: "center", color: "var(--text-3)", fontSize: 13 } }, "No shared files are available for this conversation."));

    const pinnedTab = e("div", null,
      CHAT.pinnedMsgs.map((p, i) => e("div", { key: i, className: "det-pin" },
        e(Icon, { name: "pin", size: 15, className: "pic" }),
        e("div", null,
          e("div", { style: { fontWeight: 700, fontSize: 12.5, marginBottom: 2 } }, p.by, e("span", { className: "faint", style: { fontWeight: 600, marginLeft: 6 } }, p.t)),
          e("div", { style: { fontSize: 12.5, color: "var(--text-2)", lineHeight: 1.5 } }, p.text)))),
      CHAT.pinnedMsgs.length === 0 && e("div", { style: { padding: 30, textAlign: "center", color: "var(--text-3)", fontSize: 13 } }, "No pinned messages yet."));

    const bodies = { about: aboutTab, members: membersTab, files: filesTab, pinned: pinnedTab };

    return e("div", { className: "details" },
      e("div", { className: "details-tabs" }, tabs.map(t =>
        e("button", { key: t[0], className: "dt" + (curTab === t[0] ? " on" : ""), onClick: () => setTab(t[0]) }, t[1]))),
      e("div", { className: "details-body" }, bodies[curTab]));
  }

  // ================= ADMIN CONSOLE =================
  function ChatAdmin({ role, toast, onBack, conversations = [], messages = {}, connectionStatus = "", mailboxOptions = null, onArchiveThreads = null }) {
    const [tab, setTab] = React.useState("Overview");
    const [archiving, setArchiving] = React.useState(false);
    const serverThreads = conversations.filter(c => c.serverBacked);
    const allMessages = Object.values(messages || {}).flat().filter(Boolean);
    const serverMessages = allMessages.filter(m => m.serverBacked);
    const unreadCount = serverThreads.reduce((sum, c) => sum + Number(c.unread || 0), 0);
    const participantIds = new Set();
    serverMessages.forEach(m => { if (m.by) participantIds.add(m.by); });
    serverThreads.forEach(c => { if (c.who) participantIds.add(c.who); });
    const topThreads = serverThreads.slice(0, 6).map(c => {
      const count = (messages[c.id] || []).length;
      return { label: c.name, value: count, display: count + " message(s)", color: c.color || "#F6740C" };
    }).filter(row => row.value > 0);
    const recentThreads = serverThreads.slice(0, 6);
    const recentMessages = serverMessages.slice(-10).reverse();
    const configuredSettings = mailboxOptions?.mailbox_settings?.value || {};
    const retentionDays = configuredSettings.retention_days || configuredSettings.message_retention_days || null;
    const exportWorkspace = (format = "csv") => {
      if (!mailboxOptions?.export_url) {
        toast("Chat workspace export is unavailable for this role.", "orange");
        return;
      }
      const url = new URL(mailboxOptions.export_url, window.location.origin);
      url.searchParams.set("folder", "all");
      url.searchParams.set("format", format);
      const link = document.createElement("a");
      link.href = url.toString();
      link.target = "_blank";
      link.rel = "noopener";
      link.style.display = "none";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      toast("Downloading chat workspace " + format.toUpperCase() + " export.", "green");
    };
    const archiveRecentThreads = async () => {
      if (!recentThreads.length || !onArchiveThreads) {
        toast("No chat threads are loaded for archival.", "orange");
        return;
      }
      setArchiving(true);
      try {
        await onArchiveThreads(recentThreads);
      } finally {
        setArchiving(false);
      }
    };

    const overview = e("div", null,
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        [
          { label: "Threads", value: serverThreads.length, icon: "bubble", tone: serverThreads.length ? "green" : "orange", sub: serverThreads.length ? "Loaded conversations" : "No conversations loaded" },
          { label: "Messages", value: serverMessages.length, icon: "mail", tone: serverMessages.length ? "blue" : "slate", sub: "Participant message records" },
          { label: "Unread", value: unreadCount, icon: "bell", tone: unreadCount ? "orange" : "green", sub: "Recipient unread status" },
          { label: "Participants", value: participantIds.size, icon: "users", tone: "accent", sub: "Visible internal participants" },
        ].map((s, i) => e(Stat, { key: i, label: s.label, value: s.value, icon: s.icon, tone: s.tone, sub: s.sub }))),
      e("div", { className: "sys-note", style: { marginBottom: 16 } }, e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), connectionStatus || "Chat metrics use the conversations currently available to you."),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.5fr 1fr", alignItems: "start" } },
        e(Card, { title: "Most Active Threads", sub: "message count from currently loaded conversations", className: "card-pad" },
          topThreads.length ? e(HBars, { data: topThreads }) : e("div", { className: "empty" }, "No message threads are available in your current view.")),
        e(Card, { title: "Read Coverage", sub: "recipient unread status from messages" },
          e("div", { style: { display: "grid", placeItems: "center", padding: "20px 0 10px" } },
            e(Gauge, { value: serverMessages.length ? Math.round((serverMessages.length - unreadCount) / serverMessages.length * 100) : 0, color: unreadCount ? "var(--orange)" : "var(--green)", label: "% read", size: 168 }),
            e("div", { className: "faint", style: { fontSize: 12, fontWeight: 600, marginTop: 6 } }, serverMessages.length ? (serverMessages.length - unreadCount) + " of " + serverMessages.length + " visible messages read" : "No messages loaded"),
            e(Button, { sm: true, style: { marginTop: 12 }, icon: "bell", onClick: () => toast("Bulk chat reminders are not available from this screen.", "orange"), children: "Reminder unavailable" })))),
      e("div", { className: "grid g-2", style: { marginTop: 16, alignItems: "start" } },
        e(Card, { title: "Recent Threads", sub: "active participant message threads", action: e(Button, { sm: true, icon: "archive", disabled: !recentThreads.length || !mailboxOptions?.state_url_template || !mailboxOptions?.can_update_state || archiving, onClick: archiveRecentThreads, children: archiving ? "Archiving..." : "Archive loaded threads" }) },
          recentThreads.length ? recentThreads.map((c, i) => e("div", { key: i, className: "row between", style: { padding: "13px 16px", borderBottom: i < recentThreads.length - 1 ? "1px solid var(--border)" : "none" } },
            e("div", null, e("div", { style: { fontWeight: 700, fontSize: 13 } }, c.name), e("div", { className: "faint", style: { fontSize: 11.5, fontWeight: 600 } }, c.sub || "Thread")),
            e("span", { className: "tag" }, e(Icon, { name: "mail", size: 12 }), (messages[c.id] || []).length))) : e("div", { className: "empty" }, "No thread activity is available.")),
        e(Card, { title: "Moderation Workflow", sub: "No message-report queue is configured", action: e(Badge, { tone: "b-slate" }, "Not configured") },
          e("div", { style: { padding: 16, fontSize: 13, color: "var(--text-2)", lineHeight: 1.6 } },
            "Message reporting, dismissal, removal and legal-hold moderation require additional setup. Reported messages will appear here after setup."))));

    const audit = e(Card, { title: "Message Activity", sub: "Visible conversation activity; formal activity history remains in Governance → Activity History", action: e(Button, { sm: true, icon: "download", disabled: !mailboxOptions?.export_url, onClick: () => exportWorkspace("csv"), children: mailboxOptions?.export_url ? "Export CSV" : "Export unavailable" }) },
      recentMessages.length ? recentMessages.map((m, i) => {
        const p = P[m.by] || {};
        return e("div", { key: i, className: "row gap-3", style: { padding: "13px 18px", borderBottom: i < recentMessages.length - 1 ? "1px solid var(--border)" : "none" } },
          e("div", { style: { width: 36, height: 36, borderRadius: 10, background: "var(--surface-3)", color: "var(--accent)", display: "grid", placeItems: "center", flex: "0 0 36px" } }, e(Icon, { name: "mail", size: 16 })),
          e("div", { style: { flex: 1 } },
            e("div", { style: { fontSize: 13 } }, e("b", null, p.name || "Builder360 user"), " sent ", e("span", { style: { color: "var(--accent)", fontWeight: 700 } }, m.messageNumber || "message")),
            e("div", { className: "faint", style: { fontSize: 11.5, fontWeight: 600 } }, m.t + " · " + (m.status || "visible"))));
      }) : e("div", { className: "empty" }, "No message activity is available in this view."));

    const perms = e(Card, { title: "Access Matrix", sub: "Reference only; actions remain limited by your role and access." },
      CHAT.permMatrix.cols.length && CHAT.permMatrix.rows.length ? e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
        e("thead", null, e("tr", null, e("th", null, "Action"), CHAT.permMatrix.cols.map((c, i) => e("th", { key: i, style: { textAlign: "center" } }, c)))),
        e("tbody", null, CHAT.permMatrix.rows.map((r, i) => e("tr", { key: i },
          e("td", { className: "cell-strong" }, r.action),
          r.v.map((v, j) => e("td", { key: j, style: { textAlign: "center" } },
            v === 1 ? e(Icon, { name: "check", size: 16, style: { color: "var(--green)" } })
            : v === 0.5 ? e("span", { className: "badge b-orange", style: { fontSize: 10 } }, "Optional")
            : e(Icon, { name: "x", size: 14, style: { color: "var(--text-3)", opacity: .5 } })))))))) : e("div", { className: "empty" }, "No chat access matrix is configured."));

    const retention = e("div", { className: "grid g-2", style: { alignItems: "start" } },
      e(Card, { title: "Configured Retention & Compliance", className: "card-pad" },
        [["Message retention", retentionDays ? retentionDays + " days" : "Not configured", "from mailbox settings"], ["External sync", configuredSettings.external_sync_enabled ? "Enabled" : "Disabled", "mailbox metadata"], ["Archived group retention", "Not configured", "requires chat administration workflow"], ["Legal hold", "Not configured", "requires compliance/legal-hold workflow"]].map((r, i) =>
          e("div", { key: i, className: "row between", style: { padding: "12px 0", borderBottom: i < 3 ? "1px solid var(--border)" : "none" } },
            e("div", null, e("div", { style: { fontWeight: 700, fontSize: 13 } }, r[0]), e("div", { className: "faint", style: { fontSize: 11.5, fontWeight: 600 } }, r[2])),
            e("span", { className: "badge b-accent" }, r[1]))),
        e(Button, { variant: "primary", icon: "shield", style: { marginTop: 14 }, onClick: () => toast("Retention changes are managed through System Settings approvals, not directly from this chat panel.", "orange"), children: "Manage in Settings" })),
      e(Card, { title: "Data & Export", className: "card-pad" },
        e("div", { style: { fontSize: 13, color: "var(--text-2)", lineHeight: 1.6, marginBottom: 14 } }, "Visible chat data is limited to conversations available to you. Exports use the same company and participant access."),
        e("div", { style: { display: "flex", flexDirection: "column", gap: 8 } },
          e(Button, { icon: "download", disabled: !mailboxOptions?.export_url, onClick: () => exportWorkspace("csv"), style: { justifyContent: "flex-start" }, children: mailboxOptions?.export_url ? "Export workspace CSV" : "Export unavailable" }),
          e(Button, { icon: "doc", disabled: !mailboxOptions?.export_url, onClick: () => exportWorkspace("pdf"), style: { justifyContent: "flex-start" }, children: mailboxOptions?.export_url ? "Export workspace PDF" : "PDF unavailable" }),
          e(Button, { icon: "sliders", disabled: true, "aria-disabled": true, onClick: () => toast("Restricted-word administration requires compliance settings before this action can be enabled.", "orange"), style: { justifyContent: "flex-start", cursor: "not-allowed", opacity: .68 }, children: "Manage restricted words" }),
          e(Button, { icon: "users", disabled: true, "aria-disabled": true, onClick: () => toast("Guest access changes require access-control settings before this action can be enabled.", "orange"), style: { justifyContent: "flex-start", cursor: "not-allowed", opacity: .68 }, children: "Guest access policy" }))));

    const bodies = { Overview: overview, "Audit Log": audit, Permissions: perms, Retention: retention };

    return e("div", { className: "content", style: { overflowY: "auto" } },
      e("div", { className: "page page-wide" },
        e(PageHead, {
          crumbs: ["Chat Connect", "Admin Console"], title: "Chat Admin Console",
          sub: "Message-thread metrics with clear gaps for moderation, legal hold and dedicated chat administration workflows.",
          actions: [e(Button, { key: 1, icon: "chevL", onClick: onBack, children: "Back to chat" })] }),
        e("div", { className: "tabs" }, ["Overview", "Audit Log", "Permissions", "Retention"].map(t =>
          e("button", { key: t, className: "tab" + (tab === t ? " on" : ""), onClick: () => setTab(t) }, t))),
        bodies[tab]));
  }

  window.ChatDetails = ChatDetails;
  window.ChatAdmin = ChatAdmin;
})();
