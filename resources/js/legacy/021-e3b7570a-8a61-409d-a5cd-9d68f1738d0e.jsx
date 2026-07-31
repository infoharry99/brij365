const React = window.React;

/* ============================================================
   Builder360 — Chat Connect: messaging workspace
   3-pane (list · thread · details) + admin console
   ============================================================ */
(function () {
  const { Icon, Avatar, Badge } = window;
  const e = React.createElement;
  const CHAT = window.CHAT;
  const P = CHAT.people, PR = CHAT.pres;
  const chatOptions = () => window.Builder360Server?.chat_connect_options || null;
  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const firstApiError = (payload) => {
    const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
    return errors[0] || payload?.message || "The chat message could not be saved.";
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
  const apiForm = async (url, formData, options = {}) => {
    const response = await fetch(url, {
      ...options,
      method: options.method || "POST",
      headers: {
        "Accept": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        ...(options.headers || {}),
      },
      body: formData,
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(firstApiError(body));
    return body;
  };
  const chatCollectionUrl = (base, params = {}) => {
    const qs = new URLSearchParams(params);
    return base + (String(base).includes("?") ? "&" : "?") + qs.toString();
  };
  const messageReactionUrl = (template, message) => template && message?.serverId ? template.replace("__MESSAGE__", message.serverId) : null;
  const attachmentName = attachment => attachment?.filename || attachment?.name || "Attachment";
  const attachmentMime = attachment => String(attachment?.mime_type || attachment?.mime || attachment?.content_type || "");
  const isImageAttachment = attachment => attachmentMime(attachment).startsWith("image/")
    || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(attachmentName(attachment));
  const attachmentPreviewUrl = attachment => attachment?.preview_url || (isImageAttachment(attachment) ? attachment?.download_url : null);
  const attachmentDownloadUrl = attachment => attachment?.download_url || attachment?.url || null;
  const serverUserKey = user => user?.id ? "user_" + user.id : null;
  const ensureServerPerson = user => {
    const key = serverUserKey(user);
    if (!key || P[key]) return key;
    const colors = ["#F6740C", "#15a657", "#e08600", "#2570eb", "#0ea5a4", "#7c3aed", "#dc2f3a"];
    P[key] = {
      id: key,
      serverId: user.id,
      name: user.name || user.email || "Builder360 User",
      role: "Internal User",
      dept: "Builder360",
      pres: "online",
      color: colors[Object.keys(P).length % colors.length],
      title: user.email || "Internal collaboration user",
    };
    return key;
  };
  const serverMessageTime = row => {
    if (!row?.created_at) return "now";
    const date = new Date(row.created_at);
    if (Number.isNaN(date.getTime())) return "now";
    return date.toLocaleString("en-IN", { day: "2-digit", month: "short", hour: "numeric", minute: "2-digit", hour12: true });
  };
  const serverMessageText = row => String(row?.body || "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
  const recipientLabel = recipient => recipient?.name || recipient?.label || recipient?.email || "Employee";
  const recipientSubLabel = recipient => [recipient?.employee_code || recipient?.code, recipient?.role || recipient?.title || recipient?.designation, recipient?.department || recipient?.team, recipient?.email].filter(Boolean).join(" · ");

  function RecipientPicker({ recipients = [], selected, onChange, disabled = false, compact = false }) {
    const [open, setOpen] = React.useState(false);
    const rootRef = React.useRef(null);
    const selectedRow = recipients.find(recipient => String(recipient.id) === String(selected));
    React.useEffect(() => {
      if (!open) return;
      const onKey = ev => { if (ev.key === "Escape") setOpen(false); };
      const onPointer = ev => {
        if (rootRef.current && !rootRef.current.contains(ev.target)) setOpen(false);
      };
      document.addEventListener("keydown", onKey);
      document.addEventListener("mousedown", onPointer);
      return () => {
        document.removeEventListener("keydown", onKey);
        document.removeEventListener("mousedown", onPointer);
      };
    }, [open]);
    return e("div", { ref: rootRef, className: "chat-recipient-picker" + (compact ? " compact" : ""), onClick: ev => ev.stopPropagation() },
      e("button", {
        type: "button",
        className: "chat-recipient-btn",
        disabled,
        "aria-haspopup": "listbox",
        "aria-expanded": open ? "true" : "false",
        onClick: () => !disabled && setOpen(value => !value),
      }, e("span", null, selectedRow ? recipientLabel(selectedRow) : "Select recipient"), e(Icon, { name: "chevD", size: 14 })),
      open && e("div", { className: "chat-recipient-menu" },
        e(window.SearchablePeoplePicker, {
          items: recipients,
          selected: selected || "",
          mode: "single",
          disabled,
          placeholder: "Search employee name, role or department...",
          emptyText: "No matching employees",
          onChange: value => { onChange && onChange(value || ""); setOpen(false); },
          getId: recipient => recipient.id,
          getLabel: recipientLabel,
          getSubLabel: recipientSubLabel,
        })));
  }

  function MentionPicker({ people = [], query = "", onQuery, onPick, onClose }) {
    const clean = query.trim().toLowerCase();
    const rows = people
      .filter(person => person?.id)
      .filter(person => {
        if (!clean) return true;
        return [recipientLabel(person), recipientSubLabel(person), person.email, person.department, person.team, person.role, person.title]
          .filter(Boolean)
          .join(" ")
          .toLowerCase()
          .includes(clean);
      })
      .slice(0, 12);
    React.useEffect(() => {
      const onKey = ev => { if (ev.key === "Escape") onClose && onClose(); };
      document.addEventListener("keydown", onKey);
      return () => document.removeEventListener("keydown", onKey);
    }, [onClose]);
    return e("div", { className: "chat-mention-menu", role: "dialog", "aria-label": "Mention employee" },
      e("div", { className: "chat-mention-search" },
        e(Icon, { name: "search", size: 14 }),
        e("input", {
          value: query,
          autoFocus: true,
          placeholder: "Search employee name, role or department...",
          onChange: ev => onQuery && onQuery(ev.target.value),
        }),
        e("button", { type: "button", className: "ct-btn", title: "Close mentions", onClick: onClose }, e(Icon, { name: "x", size: 13 }))),
      e("div", { className: "chat-mention-list", role: "listbox" },
        rows.length
          ? rows.map(person => e("button", {
              key: person.id,
              type: "button",
              className: "chat-mention-option",
              role: "option",
              onClick: () => onPick && onPick(person),
            },
              e(Avatar, { name: recipientLabel(person), color: person.color || "#F6740C", size: 32 }),
              e("span", { className: "chat-mention-main" },
                e("strong", null, recipientLabel(person)),
                e("small", null, recipientSubLabel(person) || "Conversation member")),
              e(Icon, { name: "at", size: 14 })))
          : e("div", { className: "chat-mention-empty" }, "No matching employees")));
  }
  const serverReactionsToChat = (row, currentUserId) => {
    if (Array.isArray(row?.reactions)) {
      return row.reactions.map(reaction => ({
        e: reaction.emoji,
        n: Number(reaction.count || 0),
        me: !!reaction.me,
      })).filter(reaction => reaction.n > 0);
    }
    const reactions = row?.metadata?.reactions || {};
    return Object.entries(reactions)
      .map(([emoji, entries]) => {
        const people = Array.isArray(entries) ? entries : [];
        return {
          e: emoji,
          n: people.length,
          me: people.some(person => String(person?.user_id || "") === String(currentUserId || "") || String(person?.user_key || "") === "user_" + String(currentUserId || "")),
        };
      })
      .filter(reaction => reaction.n > 0);
  };
  const serverMessageToChat = (row, currentUserId) => {
    const senderKey = ensureServerPerson(row.sender);
    ensureServerPerson(row.recipient);
    return {
      serverBacked: true,
      serverId: row.id,
      conversationId: row.conversation_id,
      messageNumber: row.message_number,
      threadKey: row.thread_key,
      parentMessageId: row.parent_message_id,
      by: senderKey,
      you: String(row.sender?.id || "") === String(currentUserId || ""),
      t: serverMessageTime(row),
      text: serverMessageText(row) || row.subject || row.message_number,
      read: !!row.read_at,
      priority: row.priority,
      status: row.status,
      react: serverReactionsToChat(row, currentUserId),
      attachments: Array.isArray(row.attachments) ? row.attachments : [],
      pollData: row.poll || null,
    };
  };
  const serverThreadPayload = (rows, currentUserId) => {
    const grouped = {};
    rows.forEach(row => {
      const key = row.thread_key || ("message_" + row.id);
      grouped[key] = grouped[key] || [];
      grouped[key].push(row);
    });
    const conversations = [];
    const messages = {};
    Object.entries(grouped).forEach(([threadKey, threadRows]) => {
      const sorted = threadRows.slice().sort((a, b) => String(a.created_at || "").localeCompare(String(b.created_at || "")));
      const latest = sorted[sorted.length - 1];
      const participants = [];
      sorted.forEach(row => [row.sender, row.recipient].forEach(user => {
        if (user?.id && !participants.some(existing => String(existing.id) === String(user.id))) participants.push(user);
      }));
      participants.forEach(ensureServerPerson);
      const currentId = String(currentUserId || "");
      const others = participants.filter(user => String(user.id) !== currentId);
      const other = others[0] || latest.sender || latest.recipient;
      const otherKey = ensureServerPerson(other);
      const id = "server_thread_" + String(threadKey).replace(/[^\w-]/g, "_");
      messages[id] = sorted.map(row => serverMessageToChat(row, currentUserId));
      conversations.push({
        id,
        serverBacked: true,
        threadKey,
        latestServerId: latest.id,
        serverRecipientId: other?.id,
        kind: others.length <= 1 ? "dm" : "group",
        who: others.length <= 1 ? otherKey : null,
        name: (latest.subject || "Conversation").replace(/^Chat:\s*/i, "") || (other?.name || "Conversation"),
        icon: "bubble",
        color: "#F6740C",
        members: participants.length,
        last: serverMessageText(latest) || latest.message_number,
        t: serverMessageTime(latest),
        unread: sorted.filter(row => String(row.recipient?.id || "") === currentId && row.status === "unread").length,
        mentions: 0,
        pinned: false,
        sub: latest.message_number || threadKey,
        memberUsers: participants,
      });
    });
    conversations.sort((a, b) => String(b.t || "").localeCompare(String(a.t || "")));
    return { conversations, messages };
  };
  const chatConversationPayload = (body, currentUserId) => {
    const rows = Array.isArray(body?.data) ? body.data : (body?.data ? [body.data] : []);
    const sourceMessages = body?.messages || {};
    const conversations = [];
    const messages = {};
    rows.forEach(row => {
      const members = Array.isArray(row.members) ? row.members.map(member => member.user).filter(Boolean) : [];
      members.forEach(ensureServerPerson);
      const currentId = String(currentUserId || "");
      const others = members.filter(user => String(user.id) !== currentId);
      const firstOther = others[0] || members[0] || row.owner || null;
      const otherKey = ensureServerPerson(firstOther);
      const id = "chat_conversation_" + row.id;
      const rawMessages = Array.isArray(sourceMessages[row.id]) ? sourceMessages[row.id] : (Array.isArray(body?.messages) ? body.messages : []);
      messages[id] = rawMessages.map(message => serverMessageToChat(message, currentUserId));
      const latest = row.latest_message || rawMessages[rawMessages.length - 1] || null;
      conversations.push({
        id,
        chatConversationId: row.id,
        conversationKey: row.conversation_key,
        serverBacked: true,
        kind: row.type === "direct_message" ? "dm" : "group",
        conversationType: row.type,
        who: row.type === "direct_message" ? otherKey : null,
        name: row.title || firstOther?.name || "Conversation",
        icon: row.type === "project_channel" ? "building" : row.type === "lead_conversation" ? "users" : row.type === "announcement_channel" ? "mega" : "bubble",
        color: row.type === "approval_thread" || row.type === "voucher_thread" ? "#f59e0b" : "#F6740C",
        members: row.member_count || members.length || 1,
        last: latest?.body ? String(latest.body).replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim() : (row.description || "No messages yet"),
        t: latest?.created_at ? serverMessageTime(latest) : (row.last_message_at ? serverMessageTime({ created_at: row.last_message_at }) : "now"),
        unread: Number(row.unread_count || 0),
        mentions: 0,
        pinned: false,
        sub: [row.type ? String(row.type).replace(/_/g, " ") : "", row.project?.code, row.department].filter(Boolean).join(" · "),
        canPost: row.can_post !== false,
        canManageMembers: !!row.can_manage_members,
        readonly: row.can_post === false || row.status !== "active",
        memberUsers: members,
      });
    });
    conversations.sort((a, b) => String(b.t || "").localeCompare(String(a.t || "")));
    return { conversations, messages };
  };

  const roleTone = r => ({
    "Director": ["var(--accent)", "var(--accent-soft)"], "CFO": ["var(--blue)", "var(--blue-soft)"],
    "Sales Head": ["var(--green)", "var(--green-soft)"], "Projects Head": ["var(--orange)", "var(--orange-soft)"],
    "HR Manager": ["var(--accent-2)", "var(--green-soft)"], "Project Manager": ["var(--violet)", "var(--violet-soft)"],
    "Site Engineer": ["var(--green)", "var(--green-soft)"], "Store Keeper": ["var(--slate)", "var(--slate-soft)"],
    "Sales Exec": ["var(--slate)", "var(--slate-soft)"], "Vendor · Guest": ["var(--slate)", "var(--slate-soft)"],
  }[r] || ["var(--slate)", "var(--slate-soft)"]);

  // presence avatar
  function PAv({ id, size = 40 }) {
    const p = P[id]; if (!p) return null;
    return e("div", { className: "pres-wrap", style: { width: size, height: size } },
      e(Avatar, { name: p.name, color: p.color, size }),
      e("span", { className: "pres-dot", style: { background: (PR[p.pres] || PR.offline).c } }));
  }

  // ---------- conversation list ----------
  function ConvList({ conversations, active, onPick, filter, setFilter, q, setQ, toast, onNewConversation }) {
    const filters = [
      { id: "all", label: "All" },
      { id: "unread", label: "Unread", n: conversations.filter(c => c.unread).length },
      { id: "mentions", label: "Mentions", n: conversations.filter(c => c.mentions).length },
      { id: "dms", label: "DMs" },
      { id: "groups", label: "Channels" },
    ];
    const match = c => {
      if (q && !(c.name.toLowerCase().includes(q.toLowerCase()) || (c.last || "").toLowerCase().includes(q.toLowerCase()))) return false;
      if (filter === "unread") return c.unread > 0;
      if (filter === "mentions") return c.mentions > 0;
      if (filter === "dms") return c.kind === "dm";
      if (filter === "groups") return c.kind !== "dm";
      return true;
    };
    const list = conversations.filter(match);
    const pinned = list.filter(c => c.pinned);
    const dms = list.filter(c => !c.pinned && c.kind === "dm");
    const chans = list.filter(c => !c.pinned && c.kind !== "dm");

    const typeBadge = { project: "PROJ", team: "TEAM", dept: "DEPT", announce: "ANN", group: "GRP" };

    const row = c => {
      const dm = c.kind === "dm";
      const who = dm ? P[c.who] : null;
      return e("div", { key: c.id, className: "cv-row" + (active === c.id ? " active" : "") + (c.unread ? " unread" : ""), onClick: () => onPick(c.id) },
        dm ? e(PAv, { id: c.who, size: 40 })
           : e("div", { className: "cv-ic", style: { background: c.color } }, e(Icon, { name: c.icon, size: 19 })),
        e("div", { className: "cv-main" },
          e("div", { className: "cv-top" },
            e("span", { className: "cv-name" }, c.name),
            !dm && e("span", { className: "cv-typebadge" }, typeBadge[c.kind]),
            e("span", { className: "cv-time" }, c.t)),
          e("div", { className: "cv-prev" },
            e("span", null, dm ? c.last : c.last),
            c.mentions > 0 && e("span", { className: "cv-ment" }, "@"),
            c.unread > 0 && e("span", { className: "cv-unread" }, c.unread))));
    };

    return e("div", { className: "chat-list" },
      e("div", { className: "chat-list-head" },
        e("div", { className: "cl-title" },
          e("h2", null, e("span", { className: "cl-logo" }, e(Icon, { name: "bubble", size: 17 })), "Chat Connect"),
          e("button", { className: "ch-btn", title: "New conversation", onClick: onNewConversation || (() => toast("New conversation is not available for your role.", "orange")) }, e(Icon, { name: "plus", size: 19 }))),
        e("div", { className: "cl-search" },
          e(Icon, { name: "search", size: 15 }),
          e("input", { value: q, onChange: ev => setQ(ev.target.value), placeholder: "Search messages, people, files…" }),
          q && e("button", { className: "ct-btn", style: { width: 22, height: 22 }, onClick: () => setQ("") }, e(Icon, { name: "x", size: 13 })))),
      e("div", { className: "chat-filters" }, filters.map(f =>
        e("button", { key: f.id, className: "cf" + (filter === f.id ? " on" : ""), onClick: () => setFilter(f.id) },
          f.label, f.n ? e("span", { className: "cf-n" }, f.n) : null))),
      e("div", { className: "chat-rows" },
        pinned.length > 0 && e("div", { className: "cl-sec" }, e("span", null, "Pinned"), e(Icon, { name: "pin", size: 12 })),
        pinned.map(row),
        dms.length > 0 && e("div", { className: "cl-sec" }, "Direct Messages"),
        dms.map(row),
        chans.length > 0 && e("div", { className: "cl-sec" }, "Channels"),
        chans.map(row),
        list.length === 0 && e("div", { style: { textAlign: "center", color: "var(--text-3)", padding: "40px 20px", fontSize: 13 } }, q ? "No conversations match your search." : "No conversations yet.")));
  }

  // ---------- one message ----------
  function Message({ m, idx, convId, onReact, onThread, onForward, onOpenTask, onPollVote, toast }) {
    const [pop, setPop] = React.useState(false);
    const [actionsOpen, setActionsOpen] = React.useState(false);
    if (m.sys === "date") return e("div", { className: "day-sep", key: idx }, m.text);
    if (m.sys === "note") return e("div", { className: "sys-note", key: idx }, e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), m.text);
    if (m.typing) return e("div", { className: "typing", key: idx },
      e("div", { className: "dots" }, e("i"), e("i"), e("i")),
      e("span", { className: "faint", style: { fontSize: 11.5, fontWeight: 600 } }, m.text));

    const you = m.you;
    const p = you ? null : P[m.by];
    const tone = p ? roleTone(p.role) : null;

    // rich content
    const renderText = txt => {
      if (!txt) return null;
      const parts = txt.split(/(@[\w()]+(?:\s[\w]+)?)/g);
      return parts.map((s, i) => /^@/.test(s) ? e("span", { className: "men", key: i }, s) : s);
    };

    const body = [];
    if (m.text) body.push(e("div", { className: "bubble", key: "b" },
      renderText(m.text),
      you && m.read && e("span", { className: "read-tick" }, e(Icon, { name: "checks", size: 13 }), "Read")));

    if (m.attach) {
      const a = m.attach;
      if (a.img) body.push(e("div", { className: "attach-img", key: "ai" },
        e("div", { className: "ph" }, e(Icon, { name: a.ic, size: 30 })),
        e("div", { className: "cap" }, e(Icon, { name: "camera", size: 13, style: { color: "var(--text-3)" } }), e("span", { style: { fontWeight: 700 } }, a.name), e("span", { className: "faint", style: { marginLeft: "auto" } }, a.size))));
      else body.push(e("div", { className: "attach-card", key: "ac", onClick: () => toast("Attachment download is not available for this file: " + a.name, "orange"), style: { cursor: "pointer" } },
        e("div", { className: "attach-ic", style: { background: a.type === "PDF" ? "var(--red)" : a.type === "DWG" ? "var(--blue)" : "var(--green)" } }, e(Icon, { name: a.ic, size: 18 })),
        e("div", { style: { minWidth: 0 } }, e("div", { className: "attach-name" }, a.name), e("div", { className: "attach-sub" }, a.type + " · " + a.size)),
        e(Icon, { name: "download", size: 16, style: { color: you ? "#fff" : "var(--text-3)", marginLeft: "auto" } })));
    }

    if (Array.isArray(m.attachments) && m.attachments.length) {
      body.push(e("div", { className: "chat-attachments", key: "server-attachments" },
        m.attachments.map(a => {
          const isVoice = a.type === "voice_note" || String(a.mime_type || "").startsWith("audio/");
          const previewUrl = attachmentPreviewUrl(a);
          const downloadUrl = attachmentDownloadUrl(a);
          if (previewUrl) {
            return e("div", { className: "attach-img chat-image-preview", key: a.id || a.filename },
              e("button", { type: "button", className: "chat-image-frame", title: "Open image preview", onClick: () => window.open(previewUrl, "_blank", "noopener") },
                e("img", { src: previewUrl, alt: attachmentName(a), loading: "lazy" })),
              e("div", { className: "cap" },
                e(Icon, { name: "camera", size: 13, style: { color: "var(--text-3)" } }),
                e("span", { style: { fontWeight: 700, minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" } }, attachmentName(a)),
                e("span", { className: "faint", style: { marginLeft: "auto" } }, a.size_label || ""),
                downloadUrl && e("button", { className: "ct-btn", title: "Download image", onClick: () => { window.open(downloadUrl, "_blank", "noopener"); } }, e(Icon, { name: "download", size: 15 }))));
          }
          return e("div", { className: "attach-card", key: a.id },
            e("div", { className: "attach-ic", style: { background: isVoice ? "var(--violet)" : "var(--blue)" } }, e(Icon, { name: isVoice ? "mic" : "clip", size: 18 })),
            e("div", { style: { minWidth: 0, flex: 1 } },
              e("div", { className: "attach-name" }, attachmentName(a)),
              e("div", { className: "attach-sub" }, [isVoice ? "Voice note" : (a.mime_type || "File"), a.size_label, a.scan_status === "blocked" ? "blocked" : null].filter(Boolean).join(" · ")),
              isVoice && downloadUrl && e("audio", { controls: true, src: downloadUrl, style: { width: "100%", marginTop: 8 } })),
            !isVoice && downloadUrl && e("button", { className: "ct-btn", title: "Download", onClick: () => { window.open(downloadUrl, "_blank", "noopener"); } }, e(Icon, { name: "download", size: 16 })));
        })));
    }

    if (m.poll) {
      const max = Math.max(...m.opts.map(o => o.votes));
      body.push(e("div", { className: "rich-card", key: "poll" },
        e("div", { className: "rich-head" }, e(Icon, { name: "poll", size: 13 }), "Poll"),
        e("div", { style: { fontWeight: 700, fontSize: 14, marginBottom: 12 } }, m.q),
        m.opts.map((o, i) => e("div", { key: i, className: "poll-opt" + (o.votes === max ? " lead" : ""), onClick: () => toast("Poll voting is not available yet: " + o.label, "orange") },
          e("div", { className: "poll-line" }, e("span", null, o.label), e("span", { className: "mono faint" }, Math.round(o.votes / m.total * 100) + "%")),
          e("div", { className: "poll-track" }, e("div", { className: "poll-fill", style: { width: (o.votes / m.total * 100) + "%" } })))),
        e("div", { className: "faint", style: { fontSize: 11.5, marginTop: 10, fontWeight: 600 } }, m.total + " votes · select to vote")));
    }

    if (m.pollData) {
      const poll = m.pollData;
      const opts = Array.isArray(poll.options) ? poll.options : [];
      const total = Math.max(0, Number(poll.total_votes || opts.reduce((sum, option) => sum + Number(option.votes || 0), 0)));
      body.push(e("div", { className: "rich-card", key: "server-poll" },
        e("div", { className: "rich-head" }, e(Icon, { name: "poll", size: 13 }), poll.status === "closed" ? "Closed poll" : "Poll"),
        e("div", { style: { fontWeight: 800, fontSize: 14, marginBottom: 12 } }, poll.question),
        opts.map(option => {
          const votes = Number(option.votes || 0);
          const pct = total ? Math.round(votes / total * 100) : 0;
          return e("button", {
            key: option.id,
            type: "button",
            className: "poll-opt" + (option.voted_by_me ? " lead" : ""),
            disabled: poll.status !== "open" || !poll.can_vote,
            onClick: () => onPollVote && onPollVote(poll.id, option.id),
          },
            e("div", { className: "poll-line" }, e("span", null, option.label), e("span", { className: "mono faint" }, pct + "%")),
            e("div", { className: "poll-track" }, e("div", { className: "poll-fill", style: { width: pct + "%" } })));
        }),
        e("div", { className: "faint", style: { fontSize: 11.5, marginTop: 10, fontWeight: 700 } }, total + " vote" + (total === 1 ? "" : "s"))));
    }

    if (m.task) body.push(e("div", { className: "rich-card", key: "task", style: { borderLeft: "3px solid var(--accent)" } },
      e("div", { className: "rich-head" }, e(Icon, { name: "check", size: 13, style: { color: "var(--accent)" } }), "Task created from message"),
      e("div", { style: { fontWeight: 700, fontSize: 14 } }, m.title),
      e("div", { className: "task-meta" },
        e("span", { className: "tag" }, e(Icon, { name: "users", size: 13 }), m.assignee),
        e("span", { className: "tag" }, e(Icon, { name: "calendar", size: 13 }), m.due),
        e(Badge, { tone: m.priority === "High" ? "b-red" : "b-orange" }, m.priority),
        e("span", { className: "tag" }, m.project)),
      e("div", { className: "task-card-row" }, e("button", { className: "btn btn-sm", disabled: !m.taskRecordId, title: m.taskRecordId ? "Open this task in Task Management." : "Task link is available after the task is saved.", onClick: () => m.taskRecordId ? onOpenTask(m.taskRecordId) : toast("Task link is available after the task is saved.", "orange") }, e(Icon, { name: "chevR", size: 13 }), "Open task"))));

    if (m.decision) body.push(e("div", { className: "rich-card", key: "dec", style: { borderLeft: "3px solid var(--violet)" } },
      e("div", { className: "rich-head" }, e(Icon, { name: "pin", size: 13, style: { color: "var(--violet)" } }), "Decision logged"),
      e("div", { style: { fontWeight: 700, fontSize: 14 } }, m.title),
      e("div", { className: "task-meta" },
        e(Badge, { tone: "b-green", dot: true }, m.status),
        e("span", { className: "tag" }, "by " + m.by2),
        e("span", { className: "tag" }, m.impact))));

    // reactions
    const reactRow = m.react && m.react.length > 0 && e("div", { className: "react-row", key: "rr" },
      m.react.map((r, i) => e("button", { key: i, className: "react-chip" + (r.me ? " me" : ""), onClick: () => onReact(idx, r.e) },
        r.e, e("span", { className: "rn" }, r.n))));

    const replyRow = m.replies && e("button", { className: "reply-link", key: "rl", onClick: () => onThread(idx) },
      e("span", { className: "ravs" }, m.replies.who.slice(0, 3).map((w, i) => e(Avatar, { key: i, name: P[w] ? P[w].name : w, color: P[w] ? P[w].color : "#888", sm: true }))),
      m.replies.n + " replies", e(Icon, { name: "chevR", size: 13 }));

    const actions = e("div", { className: "msg-actions" + (actionsOpen || pop ? " show" : "") },
      e("div", { style: { position: "relative" } },
        e("button", { className: "ma-btn", onClick: () => setPop(o => !o), title: "React" }, e(Icon, { name: "smile", size: 16 })),
        pop && e("div", { className: "emoji-pop", style: you ? { right: 0 } : { left: 0 } },
          CHAT.emojis.slice(0, 6).map(em => e("button", { key: em, onClick: () => { onReact(idx, em); setPop(false); } }, em)))),
      e("button", { className: "ma-btn", title: "Reply in thread", onClick: () => onThread(idx) }, e(Icon, { name: "reply", size: 15 })),
      e("button", { className: "ma-btn", title: "Forward", onClick: () => onForward ? onForward(idx) : toast("Message forwarding is not available for your role.", "orange") }, e(Icon, { name: "forward", size: 15 })));

    return e("div", {
      className: "msg-row" + (you ? " you" : ""),
      key: idx,
      tabIndex: 0,
      onMouseEnter: () => setActionsOpen(true),
      onMouseLeave: () => { if (!pop) setActionsOpen(false); },
      onFocus: () => setActionsOpen(true),
      onBlur: ev => { if (!ev.currentTarget.contains(ev.relatedTarget) && !pop) setActionsOpen(false); },
      onClick: () => setActionsOpen(true),
      onPointerDown: () => setActionsOpen(true),
      onTouchStart: () => setActionsOpen(true),
      onKeyDown: ev => {
        if (ev.key === "Enter" || ev.key === " ") {
          ev.preventDefault();
          setActionsOpen(open => !open);
        }
      },
    },
      you ? e("div", { style: { width: 34 } }) : e(PAv, { id: m.by, size: 34 }),
      e("div", { className: "msg-body" },
        !you && e("div", { className: "msg-head" },
          e("span", { className: "msg-name" }, p ? p.name : m.by),
          p && e("span", { className: "msg-role", style: { color: tone[0], background: tone[1] } }, p.guest ? "Guest" : p.role),
          e("span", { className: "msg-time" }, m.t)),
        you && e("div", { className: "msg-head", style: { justifyContent: "flex-end" } }, e("span", { className: "msg-time" }, "You · " + m.t)),
        ...body,
        reactRow,
        replyRow,
        m.readReceipt && e("div", { className: "faint", style: { fontSize: 11, marginTop: 5, fontWeight: 700, display: "flex", alignItems: "center", gap: 5 } }, e(Icon, { name: "eye", size: 12 }), m.readReceipt),
        m.mustRead && e("div", { style: { marginTop: 6 } }, e("button", { className: "btn btn-sm btn-primary", onClick: () => toast("Acknowledgement is not available yet.", "orange") }, e(Icon, { name: "check", size: 13 }), "Acknowledge")),
        actions));
  }

  // ---------- thread / details panel content handled in screens-chat-admin via window.ChatDetails ----------

  function ChatConversationModal({ options, onClose, onCreated, toast }) {
    const recipients = options?.recipients || [];
    const projects = options?.projects || [];
    const rawConversationTypes = options?.conversation_types || [
      { value: "direct_message", label: "Direct Message" },
      { value: "group_chat", label: "Group Chat" },
      { value: "department_channel", label: "Department Channel" },
      { value: "project_channel", label: "Project Channel" },
    ];
    const conversationCapability = type => {
      if (type === "direct_message") return ["can_create_dm", "create_dm"];
      if (type === "group_chat") return ["can_create_group", "create_group"];
      return ["can_create_channel", "create_channel"];
    };
    const isConversationTypeAllowed = type => {
      const [topLevel, nested] = conversationCapability(type);
      return Boolean(options?.can_create) && Boolean(options?.[topLevel] ?? options?.capabilities?.[nested]);
    };
    const conversationTypes = rawConversationTypes.filter(type => isConversationTypeAllowed(type.value));
    const defaultConversationType = conversationTypes[0]?.value || "";
    const [form, setForm] = React.useState(() => ({
      conversation_type: defaultConversationType,
      member_user_ids: [],
      project_id: "",
      subject: "",
      body: "",
      priority: "normal",
    }));
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = key => ev => setForm(prev => ({ ...prev, [key]: ev.target.value }));
    React.useEffect(() => {
      if (!defaultConversationType) return;
      setForm(current => conversationTypes.some(type => type.value === current.conversation_type)
        ? current
        : { ...current, conversation_type: defaultConversationType, project_id: "" });
    }, [defaultConversationType, conversationTypes.map(type => type.value).join("|")]);
    const requiresProject = form.conversation_type === "project_channel";
    const canCreateSelectedType = form.conversation_type && isConversationTypeAllowed(form.conversation_type);
    const priorityOptions = [
      { value: "low", label: "Low" },
      { value: "normal", label: "Normal" },
      { value: "high", label: "High" },
      { value: "critical", label: "Critical" },
    ];

    const submit = async ev => {
      ev.preventDefault();
      setError("");
      const subject = form.subject.trim();
      const bodyText = form.body.trim();
      const createUrl = options?.conversation_store_url || options?.store_url;
      if (!options?.can_create || !createUrl || !canCreateSelectedType) {
        setError("This conversation type is not available for your role.");
        return;
      }
      if (!form.member_user_ids.length) {
        setError("Select at least one internal team member.");
        return;
      }
      if (requiresProject && !form.project_id) {
        setError("Select a project for this project channel.");
        return;
      }
      if (!subject) {
        setError("Conversation subject is required.");
        return;
      }
      if (!bodyText) {
        setError("First message is required.");
        return;
      }
      try {
        setBusy(true);
        const payload = options?.conversation_store_url ? {
          type: form.conversation_type,
          title: subject,
          member_user_ids: form.member_user_ids.map(Number),
          project_id: form.project_id ? Number(form.project_id) : undefined,
          related_type: form.project_id ? "project" : undefined,
          related_id: form.project_id ? Number(form.project_id) : undefined,
          body: bodyText,
          priority: form.priority,
        } : {
          recipient_user_ids: [Number(form.member_user_ids[0])],
          subject: "Chat: " + subject,
          body: bodyText,
          priority: form.priority,
          metadata: {
            source: "chat_connect_new_conversation",
            conversation_name: subject,
          },
        };
        const body = await apiJson(createUrl, {
          method: "POST",
          body: JSON.stringify(payload),
        });
        onCreated && onCreated(options?.conversation_store_url ? body : (body.data || []));
        toast && toast("New chat conversation saved.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Conversation could not be created.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "chat-modal-scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "chat-modal", onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "chat-modal-head" },
          e("div", null, e("h2", null, "New conversation"), e("p", null, "Start an internal Builder360 team conversation.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose, "aria-label": "Close new conversation" }, e(Icon, { name: "x" }))),
        e("div", { className: "chat-modal-body" },
          error && e("div", { className: "chat-alert error" }, e(Icon, { name: "alert", size: 14 }), error),
          !options?.can_create && e("div", { className: "chat-alert" }, e(Icon, { name: "shield", size: 14 }), "Conversation creation is not available for your role."),
          options?.can_create && !conversationTypes.length && e("div", { className: "chat-alert" }, e(Icon, { name: "shield", size: 14 }), "No conversation types are available for your role."),
          e("label", { className: "chat-form-label" }, "Conversation type",
            e("select", {
              className: "chat-field",
              value: form.conversation_type,
              disabled: busy || !conversationTypes.length,
              onChange: ev => setForm(current => Object.assign({}, current, {
                conversation_type: ev.target.value,
                project_id: ev.target.value === "project_channel" ? current.project_id : "",
                member_user_ids: current.member_user_ids.slice(0, ev.target.value === "direct_message" ? 1 : 25),
              })),
            }, conversationTypes.length
              ? conversationTypes.map(type => e("option", { key: type.value, value: type.value }, type.label))
              : e("option", { value: "" }, "No available conversation types")))),
          requiresProject && e("label", { className: "chat-form-label" }, "Linked project",
            e("select", { className: "chat-field", value: form.project_id, disabled: busy, required: true, onChange: set("project_id") },
              e("option", { value: "" }, "Select project"),
              projects.map(project => e("option", { key: project.id, value: project.id }, [project.code, project.name].filter(Boolean).join(" · "))))),
          e("label", { className: "chat-form-label" }, form.conversation_type === "direct_message" ? "Recipient" : "Members",
            e(window.SearchablePeoplePicker, {
              items: recipients,
              selected: form.conversation_type === "direct_message" ? (form.member_user_ids[0] || "") : form.member_user_ids,
              mode: form.conversation_type === "direct_message" ? "single" : "multi",
              required: true,
              disabled: busy,
              placeholder: "Search employee name, email, role or department...",
              emptyText: "No matching internal users",
              onChange: value => setForm(current => Object.assign({}, current, { member_user_ids: Array.isArray(value) ? value : (value ? [value] : []) })),
              getId: recipient => recipient.id,
              getLabel: recipientLabel,
              getSubLabel: recipientSubLabel,
            })),
          e("label", { className: "chat-form-label" }, "Conversation subject",
            e("input", { className: "chat-field", value: form.subject, onChange: set("subject"), disabled: busy, required: true, maxLength: 240, placeholder: "Example: Finance handoff for Skyline booking", autoFocus: true })),
          e("label", { className: "chat-form-label" }, "First message",
            e("textarea", { className: "chat-field chat-textarea", value: form.body, onChange: set("body"), disabled: busy, required: true, maxLength: 10000, placeholder: "Write the first message for this conversation." })),
          e("label", { className: "chat-form-label" }, "Priority",
            e("select", { className: "chat-field", value: form.priority, onChange: set("priority"), disabled: busy, required: true },
              priorityOptions.map(priority => e("option", { key: priority.value, value: priority.value }, priority.label)))),
          e("div", { className: "chat-alert compact" }, e(Icon, { name: "shield", size: 14 }), "Only active internal users available to your role and company can be added.")),
        e("div", { className: "chat-modal-foot" },
          e("div", { className: "muted" }, "Saved as an internal Builder360 conversation."),
          e("div", { className: "row gap-2" },
            e("button", { type: "button", className: "btn", disabled: busy, onClick: onClose }, "Cancel"),
            e("button", { type: "submit", className: "btn btn-primary", disabled: busy || !options?.can_create || !conversationTypes.length || !canCreateSelectedType }, busy ? "Creating..." : "Create conversation"))));
  }

  function ChatForwardModal({ options, message, conversationName, conversations = [], activeConversationId, onClose, onForwarded, toast }) {
    const forwardTargets = conversations
      .filter(row => row?.chatConversationId && row.canPost !== false && !row.readonly)
      .map(row => ({
        id: row.id,
        chatConversationId: row.chatConversationId,
        name: row.name || "Conversation",
        sub: [row.conversationType ? String(row.conversationType).replace(/_/g, " ") : "", row.sub].filter(Boolean).join(" · "),
      }));
    const [form, setForm] = React.useState(() => ({
      target_conversation_id: forwardTargets.some(row => row.id === activeConversationId) ? activeConversationId : (forwardTargets[0]?.id || ""),
      note: "",
      priority: "normal",
    }));
    const [targetQuery, setTargetQuery] = React.useState("");
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 12px", background: "var(--surface)", color: "var(--text)" };
    const set = key => ev => setForm(prev => ({ ...prev, [key]: ev.target.value }));
    const selectedTarget = forwardTargets.find(row => row.id === form.target_conversation_id);
    const filteredTargets = forwardTargets.filter(row => [row.name, row.sub].filter(Boolean).join(" ").toLowerCase().includes(targetQuery.trim().toLowerCase()));

    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (options?.can_post === false || !options?.conversation_message_store_url_template) {
        setError("Your role cannot forward messages.");
        return;
      }
      if (!message?.serverId) {
        setError("Only saved chat messages can be forwarded.");
        return;
      }
      if (!selectedTarget?.chatConversationId) {
        setError("Select an existing Chat Connect conversation.");
        return;
      }
      const forwardText = [
        form.note.trim(),
        "Forwarded message " + (message.messageNumber || "#" + message.serverId) + " from " + (conversationName || "Chat Connect") + ":",
        message.text || "",
      ].filter(Boolean).join("\n\n");
      try {
        setBusy(true);
        const url = options.conversation_message_store_url_template.replace("__CONVERSATION__", selectedTarget.chatConversationId);
        const body = await apiJson(url, {
          method: "POST",
          body: JSON.stringify({
            body: forwardText,
            priority: form.priority,
            metadata: {
              source: "chat_connect_forward",
              forwarded_from_message_id: message.serverId,
              forwarded_from_message_number: message.messageNumber || null,
              forwarded_from_thread_key: message.threadKey || null,
              forwarded_from_conversation_id: message.conversationId || null,
              forwarded_from_conversation_name: conversationName || null,
            },
          }),
        });
        onForwarded && onForwarded(body, selectedTarget.id);
        toast && toast("Forwarded message sent.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Forwarded message could not be sent.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1200, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(560px,94vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, "Forward message"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Forward this message into an existing Chat Connect conversation.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { className: "sys-note", style: { marginBottom: 12, color: "var(--red)" } }, e(Icon, { name: "alert", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), error),
        e("div", { className: "sys-note", style: { marginBottom: 12 } }, e(Icon, { name: "forward", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), "Forwarding ", message?.messageNumber || "selected message", " from ", conversationName || "Chat Connect"),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 12 } }, "Forward to",
          e("div", { className: "chat-forward-targets" },
            e("input", { className: "chat-field", value: targetQuery, disabled: busy, onChange: ev => setTargetQuery(ev.target.value), placeholder: "Search existing chats, groups or channels..." }),
            e("div", { className: "chat-forward-target-list" },
              filteredTargets.length ? filteredTargets.map(target => e("button", {
                key: target.id,
                type: "button",
                className: "chat-forward-target" + (target.id === form.target_conversation_id ? " on" : ""),
                disabled: busy,
                onClick: () => setForm(current => Object.assign({}, current, { target_conversation_id: target.id })),
              },
                e("span", null, e("strong", null, target.name), e("small", null, target.sub || "Chat Connect conversation")),
                target.id === form.target_conversation_id && e(Icon, { name: "check", size: 15 })))
              : e("div", { className: "chat-forward-empty" }, "No matching conversations")))),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 12 } }, "Forward note", e("textarea", { style: { ...field, minHeight: 82, resize: "vertical" }, value: form.note, onChange: set("note"), disabled: busy, maxLength: 1000, placeholder: "Optional context for recipient." })),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 14 } }, "Priority",
          e("select", { style: field, value: form.priority, onChange: set("priority"), disabled: busy, required: true },
            ["low", "normal", "high", "critical"].map(priority => e("option", { key: priority, value: priority }, priority)))),
        e("div", { className: "sys-note", style: { marginBottom: 14 } }, e(Icon, { name: "shield", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), "Forwarded messages are posted to the selected existing conversation and keep the original message reference."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Saved as an internal Builder360 message."),
          e("div", { className: "row gap-2" }, e("button", { type: "button", className: "btn", disabled: busy, onClick: onClose }, "Cancel"), e("button", { type: "submit", className: "btn btn-primary", disabled: busy || options?.can_post === false || !selectedTarget }, busy ? "Forwarding..." : "Forward message")))));
  }

  function ChatTaskModal({ conv, draft, latestMessage, selectedRecipientId, taskOptions, onClose, onCreated, toast }) {
    const assignees = taskOptions?.assignees || [];
    const projects = taskOptions?.projects || [];
    const currentUserId = Number(taskOptions?.current_user_id || window.Builder360Server?.user?.id || 0);
    const preferredAssignee = assignees.find(user => String(user.id) === String(selectedRecipientId))
      || assignees.find(user => Number(user.id) === currentUserId)
      || assignees[0]
      || null;
    const latestText = latestMessage?.text || conv?.last || "";
    const [form, setForm] = React.useState(() => ({
      title: "Task: " + (conv?.name || "Chat follow-up"),
      description: String(draft || latestText || "Chat follow-up").slice(0, 1000),
      assigned_to_user_id: preferredAssignee ? String(preferredAssignee.id) : "",
      project_id: "",
      priority: "medium",
      due_at: "",
    }));
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 12px", background: "var(--surface)", color: "var(--text)" };
    const set = key => ev => setForm(prev => ({ ...prev, [key]: ev.target.value }));

    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!taskOptions?.can_create || !taskOptions?.store_url) {
        setError("Your role cannot create tasks from chat.");
        return;
      }
      if (!form.title.trim()) {
        setError("Task title is required.");
        return;
      }
      if (!form.assigned_to_user_id) {
        setError("Select an available assignee.");
        return;
      }
      try {
        setBusy(true);
        const payload = {
          title: form.title.trim(),
          description: [
            form.description.trim(),
            "Chat source: " + (conv?.name || conv?.id || "conversation"),
            conv?.threadKey ? "Thread key: " + conv.threadKey : null,
          ].filter(Boolean).join("\n\n"),
          assigned_to_user_id: Number(form.assigned_to_user_id),
          priority: form.priority,
          due_at: form.due_at || undefined,
          module_context: "chat",
          project_id: form.project_id ? Number(form.project_id) : undefined,
          related_type: latestMessage?.serverId ? "App\\Models\\ChatMessage" : undefined,
          related_id: latestMessage?.serverId || undefined,
          checklist: [
            { label: "Review chat context", done: false },
            { label: "Update responsible stakeholder after action", done: false },
          ],
          metadata: {
            source: "chat_connect_task",
            conversation_id: conv?.id || null,
            conversation_name: conv?.name || null,
            thread_key: conv?.threadKey || latestMessage?.threadKey || null,
            latest_message_id: latestMessage?.serverId || null,
            latest_message_number: latestMessage?.messageNumber || null,
            draft_snapshot: draft || null,
          },
        };
        const body = await apiJson(taskOptions.store_url, { method: "POST", body: JSON.stringify(payload) });
        onCreated && onCreated(body.data);
        toast && toast("Task " + (body.data?.task_number || "") + " created from chat.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Task could not be created from chat.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { onClick: onClose, style: { position: "fixed", inset: 0, zIndex: 1200, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(560px,94vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 18 } }, "Create task from chat"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, conv?.name || "Selected conversation")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { className: "sys-note", style: { marginBottom: 12, color: "var(--red)" } }, e(Icon, { name: "alert", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), error),
        !taskOptions?.can_create && e("div", { className: "sys-note", style: { marginBottom: 12 } }, e(Icon, { name: "shield", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), "Read-only: task creation is not available for your role."),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 12 } }, "Task title", e("input", { style: field, value: form.title, onChange: set("title"), required: true, maxLength: 255, autoFocus: true })),
        e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)", marginBottom: 12 } }, "Description", e("textarea", { style: { ...field, minHeight: 96, resize: "vertical" }, value: form.description, onChange: set("description"), maxLength: 5000 })),
        e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 12 } },
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" } }, "Assignee",
            e(window.SearchablePeoplePicker, {
              items: assignees,
              selected: form.assigned_to_user_id,
              mode: "single",
              required: true,
              placeholder: "Search employee name, role or department...",
              emptyText: "No matching employees",
              onChange: value => setForm(current => Object.assign({}, current, { assigned_to_user_id: value || "" })),
              getId: user => user.id,
              getLabel: recipientLabel,
              getSubLabel: recipientSubLabel,
            })),
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" } }, "Priority",
            e("select", { style: field, value: form.priority, onChange: set("priority"), required: true },
              (taskOptions?.priorities || ["low", "medium", "high", "critical"]).map(priority => e("option", { key: priority, value: priority }, priority))))),
        e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 12 } },
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" } }, "Project",
            e("select", { style: field, value: form.project_id, onChange: set("project_id") },
              e("option", { value: "" }, "No project"),
              projects.map(project => e("option", { key: project.id, value: project.id }, (project.code ? project.code + " · " : "") + project.name)))),
          e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" } }, "Due date / time", e("input", { type: "datetime-local", style: field, value: form.due_at, onChange: set("due_at") }))),
        e("div", { className: "sys-note", style: { marginBottom: 14 } }, e(Icon, { name: "link", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), "The task keeps conversation, thread and latest-message context in task metadata."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Saved to Task Management."),
          e("div", { className: "row gap-2" }, e("button", { type: "button", className: "btn", onClick: onClose }, "Cancel"), e("button", { type: "submit", className: "btn btn-primary", disabled: busy || !taskOptions?.can_create }, busy ? "Creating…" : "Create task")))));
  }

  function ChatPollModal({ options, conversation, onClose, onCreated, toast }) {
    const [question, setQuestion] = React.useState("");
    const [choices, setChoices] = React.useState(["", ""]);
    const [allowsMultiple, setAllowsMultiple] = React.useState(false);
    const [busy, setBusy] = React.useState(false);
    const url = options?.poll_store_url_template && conversation?.chatConversationId
      ? options.poll_store_url_template.replace("__CONVERSATION__", conversation.chatConversationId)
      : null;
    const updateChoice = (idx, value) => setChoices(list => list.map((item, itemIdx) => itemIdx === idx ? value : item));
    const removeChoice = idx => setChoices(list => list.length <= 2 ? list : list.filter((_, itemIdx) => itemIdx !== idx));
    const cleanChoices = choices.map(v => v.trim()).filter(Boolean);
    const uniqueChoices = Array.from(new Set(cleanChoices.map(v => v.toLowerCase())));
    const hasDuplicateChoices = uniqueChoices.length !== cleanChoices.length;
    const validation = !question.trim()
      ? "Enter the poll question."
      : cleanChoices.length < 2
      ? "Add at least two options."
      : hasDuplicateChoices
      ? "Poll options must be unique."
      : cleanChoices.length > 10
      ? "A poll can have up to 10 options."
      : "";
    const canSubmit = url && !validation && !busy;
    const submit = async ev => {
      ev.preventDefault();
      if (!canSubmit) return;
      setBusy(true);
      try {
        const body = await apiJson(url, {
          method: "POST",
          body: JSON.stringify({ question: question.trim(), options: cleanChoices, allows_multiple: allowsMultiple }),
        });
        toast && toast("Poll created.", "green");
        onCreated && onCreated(body.data);
        onClose && onClose();
      } catch (error) {
        toast && toast(error.message || "Poll could not be created.", "red");
      } finally {
        setBusy(false);
      }
    };
    return e("div", { className: "chat-modal-scrim" },
      e("form", { className: "modal-card chat-modal-card chat-poll-modal", onSubmit: submit },
        e("div", { className: "chat-modal-head" },
          e("div", null, e("h2", null, "Create poll"), e("p", { className: "muted" }, "Ask the conversation members to vote.")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose }, e(Icon, { name: "x" }))),
        e("div", { className: "chat-modal-body chat-poll-body" },
          e("div", { className: "chat-poll-form" },
            e("label", { className: "chat-form-label" }, "Question",
              e("input", { className: "chat-field", value: question, maxLength: 255, onChange: ev => setQuestion(ev.target.value), placeholder: "What should the team decide?" }),
              e("span", { className: "chat-field-hint" }, question.length + "/255")),
            e("div", { className: "chat-form-label" },
              e("span", null, "Options"),
              e("div", { className: "chat-poll-options" },
                choices.map((choice, idx) => e("div", { className: "chat-poll-option-row", key: idx },
                  e("span", { className: "chat-poll-option-index" }, idx + 1),
                  e("input", { className: "chat-field", value: choice, maxLength: 120, onChange: ev => updateChoice(idx, ev.target.value), placeholder: "Option " + (idx + 1) }),
                  choices.length > 2 && e("button", { type: "button", className: "ct-btn", title: "Remove option", onClick: () => removeChoice(idx) }, e(Icon, { name: "x", size: 14 }))))),
              choices.length < 10 && e("button", { type: "button", className: "btn chat-add-option", onClick: () => setChoices(list => [...list, ""]) }, e(Icon, { name: "plus", size: 14 }), "Add option")),
            e("label", { className: "chat-toggle-row" },
              e("input", { type: "checkbox", checked: allowsMultiple, onChange: ev => setAllowsMultiple(ev.target.checked) }),
              e("span", null, e("strong", null, "Allow multiple selections"), e("small", null, "Members can vote for more than one option."))),
            validation && e("div", { className: "chat-alert error" }, e(Icon, { name: "alert", size: 14 }), validation)),
          e("div", { className: "chat-poll-preview" },
            e("div", { className: "rich-head" }, e(Icon, { name: "poll", size: 13 }), "Poll preview"),
            e("h3", null, question.trim() || "Your poll question will appear here"),
            e("p", { className: "muted" }, allowsMultiple ? "Multiple choice" : "Single choice"),
            e("div", { className: "chat-poll-preview-options" },
              (cleanChoices.length ? cleanChoices : ["Option 1", "Option 2"]).map((choice, idx) =>
                e("div", { key: idx, className: "chat-poll-preview-option" },
                  e("span", null, choice),
                  e("span", { className: "mono faint" }, "0%")))))),
        e("div", { className: "chat-modal-foot" },
          e("button", { type: "button", className: "btn", onClick: onClose }, "Cancel"),
          e("button", { type: "submit", className: "btn btn-primary", disabled: !canSubmit }, busy ? "Creating…" : "Create poll"))));
  }

  // ---------- main component ----------
  function ChatConnect({ role, toast }) {
    const options = chatOptions();
    const taskOptions = window.Builder360Server?.collaboration_task_options || null;
    const internalRecipients = options?.recipients || [];
    const [view, setView] = React.useState("chat"); // chat | admin
    const [activeId, setActiveId] = React.useState(null);
    const [conversations, setConversations] = React.useState(() => []);
    const [filter, setFilter] = React.useState("all");
    const [q, setQ] = React.useState("");
    const [store, setStore] = React.useState(() => ({}));
    const [connectionStatus, setConnectionStatus] = React.useState((options?.conversation_index_url || options?.index_url) ? "Loading conversations…" : "Chat is not available for your current access.");
    const [readOnlyReason, setReadOnlyReason] = React.useState("");
    const [drafts, setDrafts] = React.useState({});
    const [draftMentions, setDraftMentions] = React.useState({});
    const [mentionOpen, setMentionOpen] = React.useState(false);
    const [mentionQuery, setMentionQuery] = React.useState("");
    const [emojiOpen, setEmojiOpen] = React.useState(false);
    const [replyTarget, setReplyTarget] = React.useState(null);
    const [listOpen, setListOpen] = React.useState(true);
    const [fullScreen, setFullScreen] = React.useState(false);
    const [threadSearchOpen, setThreadSearchOpen] = React.useState(false);
    const [threadSearch, setThreadSearch] = React.useState("");
    const [selectedRecipientId, setSelectedRecipientId] = React.useState("");
    const [sending, setSending] = React.useState(false);
    const [selectedFiles, setSelectedFiles] = React.useState([]);
    const [recording, setRecording] = React.useState(null);
    const [recordSeconds, setRecordSeconds] = React.useState(0);
    const [micNotice, setMicNotice] = React.useState(null);
    const [conversationModal, setConversationModal] = React.useState(false);
    const [pollModal, setPollModal] = React.useState(false);
    const [taskModal, setTaskModal] = React.useState(false);
    const [forwardingMessage, setForwardingMessage] = React.useState(null);
    const [showDetails, setShowDetails] = React.useState(false);
    const [detTab, setDetTab] = React.useState("about");
    const msgsRef = React.useRef(null);
    const fileInputRef = React.useRef(null);
    const recorderRef = React.useRef(null);
    const chunksRef = React.useRef([]);
    const recordTimerRef = React.useRef(null);
    const recipientSignature = internalRecipients.map(recipient => String(recipient.id)).join("|");

    const conv = conversations.find(c => c.id === activeId) || conversations[0] || null;
    const currentConversationId = conv?.id || activeId || null;
    const msgs = currentConversationId ? (store[currentConversationId] || []) : [];
    const activeReplyTarget = replyTarget?.conversationId === currentConversationId ? replyTarget : null;
    const loadServerThreads = React.useCallback((silent = false) => {
      const listUrl = options?.conversation_index_url || options?.index_url;
      if (!listUrl) return Promise.resolve();
      if (!silent) setConnectionStatus("Loading conversations…");

      return apiJson(options?.conversation_index_url ? chatCollectionUrl(listUrl, {}) : chatCollectionUrl(listUrl, { folder: "all" }))
        .then(body => {
          setReadOnlyReason("");
          const normalized = options?.conversation_index_url
            ? chatConversationPayload(body, options.current_user_id)
            : serverThreadPayload(Array.isArray(body.data) ? body.data : [], options.current_user_id);
          if (!normalized.conversations.length) {
            setConversations([]);
            setStore({});
            setActiveId(null);
            if (!silent) setConnectionStatus("No conversations are available in your selected view. Create a conversation to begin.");
            return;
          }

          if (normalized.conversations.length) {
            setConversations(normalized.conversations);
            setStore(normalized.messages);
            setActiveId(current => current && normalized.conversations.some(row => row.id === current) ? current : normalized.conversations[0].id);
            if (!silent) setConnectionStatus("Conversations loaded.");
          }
        })
        .catch(error => {
          if (silent) {
            toast && toast("Chat refresh failed: " + error.message, "orange");
            return;
          }

          setConversations([]);
          setStore({});
          setActiveId(null);
          const authFailed = /unauthenticated|session|csrf|token/i.test(String(error.message || ""));
          const friendly = authFailed
            ? "Chat Connect is temporarily unavailable because your session could not be verified. You can view available cached conversations in read-only mode. Please reconnect or sign in again to continue messaging."
            : "Chat Connect is temporarily unavailable. You can retry loading conversations or contact your administrator if the issue continues.";
          setReadOnlyReason(friendly);
          setConnectionStatus(friendly);
        });
    }, [options?.conversation_index_url, options?.index_url, options?.current_user_id, toast]);

    React.useEffect(() => { if (msgsRef.current) msgsRef.current.scrollTop = msgsRef.current.scrollHeight; }, [activeId, view]);
    React.useEffect(() => { setReplyTarget(null); setMentionOpen(false); setMentionQuery(""); setEmojiOpen(false); setMicNotice(null); }, [activeId]);
    React.useEffect(() => { setThreadSearch(""); setThreadSearchOpen(false); }, [activeId]);
    React.useEffect(() => {
      if (!fullScreen) return undefined;
      const onKeyDown = ev => {
        if (ev.key === "Escape") setFullScreen(false);
      };
      document.addEventListener("keydown", onKeyDown);
      return () => document.removeEventListener("keydown", onKeyDown);
    }, [fullScreen]);
    React.useEffect(() => {
      if (!internalRecipients.length) {
        if (selectedRecipientId) setSelectedRecipientId("");
        return;
      }
      const selectedStillAvailable = internalRecipients.some(recipient => String(recipient.id) === String(selectedRecipientId));
      if (selectedRecipientId && !selectedStillAvailable) setSelectedRecipientId("");
    }, [selectedRecipientId, recipientSignature]);
    React.useEffect(() => {
      let cancelled = false;
      loadServerThreads(false).catch(() => {});
      return () => { cancelled = true; };
    }, [loadServerThreads]);
    React.useEffect(() => {
      if (!(options?.conversation_index_url || options?.index_url) || view !== "chat") return;
      const seconds = Number(options.chat_poll_interval_seconds || 15);
      const intervalMs = Math.max(5, seconds) * 1000;
      const timer = window.setInterval(() => {
        if (document.hidden) return;
        loadServerThreads(true).catch(() => {});
      }, intervalMs);

      return () => window.clearInterval(timer);
    }, [options?.conversation_index_url, options?.index_url, options?.chat_poll_interval_seconds, view, loadServerThreads]);
    React.useEffect(() => {
      if (!options?.reverb?.enabled || !window?.Builder360Server?.chat_connect_options || !conversations.length) return undefined;
      let cancelled = false;
      let echo = null;
      let channels = [];
      Promise.all([import("laravel-echo"), import("pusher-js")])
        .then(([EchoModule, PusherModule]) => {
          if (cancelled) return;
          window.Pusher = PusherModule.default || PusherModule;
          echo = new (EchoModule.default || EchoModule)({
            broadcaster: "reverb",
            key: options.reverb.key,
            wsHost: options.reverb.host,
            wsPort: options.reverb.port,
            wssPort: options.reverb.port,
            forceTLS: options.reverb.scheme === "https",
            enabledTransports: ["ws", "wss"],
            auth: { headers: { "X-CSRF-TOKEN": csrfToken() } },
          });
          channels = conversations.filter(row => row.chatConversationId).map(row => {
            const channel = echo.private("chat.conversation." + row.chatConversationId);
            channel.listen(".message.sent", event => {
              const saved = serverMessageToChat(event.message, options.current_user_id);
              const targetId = "chat_conversation_" + event.message.conversation_id;
              setStore(current => ({ ...current, [targetId]: [...(current[targetId] || []).filter(item => item.serverId !== saved.serverId), saved] }));
              setConversations(current => current.map(item => item.id === targetId ? { ...item, last: saved.text || "Message", t: saved.t } : item));
            });
            channel.listen(".poll.created", () => loadServerThreads(true).catch(() => {}));
            channel.listen(".poll.voted", () => loadServerThreads(true).catch(() => {}));
            channel.listen(".poll.closed", () => loadServerThreads(true).catch(() => {}));
            return "chat.conversation." + row.chatConversationId;
          });
          setConnectionStatus("Live updates enabled.");
        })
        .catch(() => setConnectionStatus("Live updates unavailable. Refreshing periodically."));

      return () => {
        cancelled = true;
        if (echo && channels.length) channels.forEach(name => echo.leave(name));
      };
    }, [options?.reverb?.enabled, options?.reverb?.key, conversations.map(row => row.chatConversationId).join("|")]);
    React.useEffect(() => () => {
      if (recordTimerRef.current) window.clearInterval(recordTimerRef.current);
      try { recorderRef.current?.stream?.getTracks?.().forEach(track => track.stop()); } catch (_error) {}
    }, []);

    const send = async () => {
      const draftKey = currentConversationId || activeId;
      const d = (drafts[draftKey] || "").trim();
      const mentionIds = Array.from(new Set((draftMentions[draftKey] || []).map(id => Number(id)).filter(Boolean)));
      if ((!d && !selectedFiles.length) || sending) return;
      const now = new Date().toLocaleTimeString("en-IN", { hour: "numeric", minute: "2-digit", hour12: true });
      if (!conv) {
        toast("Select or create a conversation before sending.", "orange");
        return;
      }
      if (readOnlyReason) {
        toast("Chat Connect is read-only until the connection is restored.", "orange");
        return;
      }

      if (conv.chatConversationId && options?.conversation_message_store_url_template && conv.canPost !== false) {
        setSending(true);
        try {
          const url = options.conversation_message_store_url_template.replace("__CONVERSATION__", conv.chatConversationId);
          const payload = {
            parent_message_id: activeReplyTarget?.serverId || undefined,
            body: d,
            priority: "normal",
            metadata: {
              source: "chat_connect",
              conversation_id: conv.chatConversationId,
              conversation_name: conv.name,
              reply_to_message_id: activeReplyTarget?.serverId || null,
              reply_to_message_number: activeReplyTarget?.messageNumber || null,
              mentions: mentionIds,
            },
          };
          const body = selectedFiles.length
            ? await (() => {
                const fd = new FormData();
                Object.entries(payload).forEach(([key, value]) => {
                  if (value === undefined || value === null) return;
                  if (typeof value === "object") return;
                  fd.append(key, value);
                });
                Object.entries(payload.metadata || {}).forEach(([key, value]) => {
                  if (Array.isArray(value)) value.forEach(item => fd.append("metadata[" + key + "][]", String(item)));
                  else if (value !== undefined && value !== null) fd.append("metadata[" + key + "]", String(value));
                });
                fd.append("message_type", selectedFiles.some(file => String(file.type || "").startsWith("audio/")) ? "voice_note" : "file");
                selectedFiles.forEach(file => fd.append("attachments[]", file, file.name || "chat-attachment"));
                if (selectedFiles[0]?.durationSeconds) fd.append("duration_seconds", String(selectedFiles[0].durationSeconds));
                return apiForm(url, fd);
              })()
            : await apiJson(url, { method: "POST", body: JSON.stringify(payload) });

          const saved = body.data ? serverMessageToChat(body.data, options.current_user_id) : { you: true, t: now, text: d || "Attachment", read: false, saved: true };
          const targetConversationId = currentConversationId || activeId;
          setStore(s => ({ ...s, [targetConversationId]: [...(s[targetConversationId] || []).filter(m => !m.typing), saved] }));
          setConversations(rows => rows.map(row => row.id === targetConversationId ? { ...row, last: d || "Attachment", t: now, unread: 0 } : row));
          setActiveId(targetConversationId);
          setDrafts(dr => ({ ...dr, [targetConversationId]: "" }));
          setDraftMentions(current => ({ ...current, [targetConversationId]: [] }));
          setSelectedFiles([]);
          setReplyTarget(null);
          toast("Chat message sent.", "green");
          loadServerThreads(true).catch(() => {});
          setTimeout(() => { if (msgsRef.current) msgsRef.current.scrollTop = msgsRef.current.scrollHeight; }, 30);
        } catch (error) {
          toast(error.message, "red");
        } finally {
          setSending(false);
        }
        return;
      }

      if (options?.store_url && options?.can_create) {
        const recipientId = conv.serverRecipientId ? String(conv.serverRecipientId) : selectedRecipientId;
        if (!recipientId) {
          toast("Select an internal Builder360 recipient before sending chat.", "red");
          return;
        }

        setSending(true);
        try {
          const body = await apiJson(options.store_url, {
            method: "POST",
            body: JSON.stringify({
              parent_message_id: activeReplyTarget?.serverId || (conv.serverBacked && conv.latestServerId ? conv.latestServerId : undefined),
              recipient_user_ids: [Number(recipientId)],
              subject: "Chat: " + conv.name,
              body: d,
              priority: "normal",
              metadata: {
                source: "chat_connect",
                conversation_id: activeId,
                conversation_name: conv.name,
                reply_to_message_id: activeReplyTarget?.serverId || null,
                reply_to_message_number: activeReplyTarget?.messageNumber || null,
                mentions: mentionIds,
              },
            }),
          });

          const saved = body.data?.[0] ? serverMessageToChat(body.data[0], options.current_user_id) : { you: true, t: now, text: d, read: false, saved: true };
          const targetConversationId = currentConversationId || activeId;
          setStore(s => ({ ...s, [targetConversationId]: [...(s[targetConversationId] || []).filter(m => !m.typing), saved] }));
          setConversations(rows => rows.map(row => row.id === targetConversationId ? { ...row, latestServerId: saved.serverId || row.latestServerId, threadKey: saved.threadKey || row.threadKey, last: d, t: now, unread: 0 } : row));
          setActiveId(targetConversationId);
          setDrafts(dr => ({ ...dr, [targetConversationId]: "" }));
          setDraftMentions(current => ({ ...current, [targetConversationId]: [] }));
          setReplyTarget(null);
          toast("Chat reply saved.", "green");
          loadServerThreads(true).catch(() => {});
          setTimeout(() => { if (msgsRef.current) msgsRef.current.scrollTop = msgsRef.current.scrollHeight; }, 30);
        } catch (error) {
          toast(error.message, "red");
        } finally {
          setSending(false);
        }
        return;
      }

      toast("Message was not sent. Message creation is not available for your role.", "orange");
    };

    const attachFiles = files => {
      const next = Array.from(files || []);
      if (!next.length) return;
      if (!options?.capabilities?.upload && !options?.can_upload) {
        toast("File sharing is not available for your role.", "orange");
        return;
      }
      setSelectedFiles(current => [...current, ...next].slice(0, 10));
    };

    const startVoice = async () => {
      setMicNotice(null);
      if (!(options?.capabilities?.voice || options?.can_send_voice)) {
        toast("Voice notes are not available for your role.", "orange");
        return;
      }
      if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
        setMicNotice({ type: "unsupported", message: "Voice recording is not supported in this browser." });
        return;
      }
      try {
        if (navigator.permissions?.query) {
          try {
            const permission = await navigator.permissions.query({ name: "microphone" });
            if (permission?.state === "denied") {
              setMicNotice({ type: "denied", message: "Microphone permission is blocked for this browser. Allow microphone access from browser site settings, then try again." });
              return;
            }
          } catch (_permissionError) {}
        }
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mimeType = MediaRecorder.isTypeSupported("audio/webm;codecs=opus") ? "audio/webm;codecs=opus" : "";
        const recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
        chunksRef.current = [];
        recorder.ondataavailable = ev => { if (ev.data?.size) chunksRef.current.push(ev.data); };
        recorder.onstop = () => {
          const blob = new Blob(chunksRef.current, { type: recorder.mimeType || "audio/webm" });
          const file = new File([blob], "voice-note-" + Date.now() + ".webm", { type: blob.type });
          file.durationSeconds = recordSeconds;
          setSelectedFiles(current => [...current, file]);
          stream.getTracks().forEach(track => track.stop());
          recorderRef.current = null;
          setRecording(null);
        };
        recorderRef.current = recorder;
        setRecordSeconds(0);
        recordTimerRef.current = window.setInterval(() => setRecordSeconds(value => Math.min(value + 1, 600)), 1000);
        recorder.start();
        setRecording("recording");
      } catch (error) {
        if (recordTimerRef.current) window.clearInterval(recordTimerRef.current);
        recordTimerRef.current = null;
        try { recorderRef.current?.stream?.getTracks?.().forEach(track => track.stop()); } catch (_stopError) {}
        recorderRef.current = null;
        setRecording(null);
        setRecordSeconds(0);
        const denied = /denied|notallowed|permission/i.test(String(error?.name || "") + " " + String(error?.message || ""));
        setMicNotice({
          type: denied ? "denied" : "failed",
          message: denied
            ? "Microphone permission is blocked for this browser. Allow microphone access from browser site settings, then try again."
            : "Voice recording could not start. Check your microphone and try again.",
        });
      }
    };

    const stopVoice = cancel => {
      if (recordTimerRef.current) window.clearInterval(recordTimerRef.current);
      recordTimerRef.current = null;
      const recorder = recorderRef.current;
      if (!recorder) return;
      if (cancel) {
        recorder.ondataavailable = null;
        recorder.onstop = null;
        try { recorder.stream?.getTracks?.().forEach(track => track.stop()); } catch (_error) {}
        recorderRef.current = null;
        setRecording(null);
        setRecordSeconds(0);
        return;
      }
      if (recorder.state !== "inactive") recorder.stop();
    };

    const onPollCreated = message => {
      const targetConversationId = currentConversationId || activeId;
      const saved = serverMessageToChat(message, options.current_user_id);
      setStore(s => ({ ...s, [targetConversationId]: [...(s[targetConversationId] || []), saved] }));
      setConversations(rows => rows.map(row => row.id === targetConversationId ? { ...row, last: message.body || "Poll", t: saved.t, unread: 0 } : row));
      loadServerThreads(true).catch(() => {});
    };

    const votePoll = async (pollId, optionId) => {
      const url = options?.poll_vote_url_template?.replace("__POLL__", pollId);
      if (!url) return;
      try {
        const body = await apiJson(url, { method: "POST", body: JSON.stringify({ option_ids: [optionId] }) });
        const updated = serverMessageToChat(body.data, options.current_user_id);
        const targetConversationId = currentConversationId || activeId;
        setStore(current => ({
          ...current,
          [targetConversationId]: (current[targetConversationId] || []).map(item => item.serverId === updated.serverId ? updated : item),
        }));
      } catch (error) {
        toast(error.message || "Poll vote failed.", "red");
      }
    };

    const react = async (idx, emoji) => {
      const targetConversationId = currentConversationId || activeId;
      if (!targetConversationId || !store[targetConversationId]) {
        toast("Reactions require an active conversation.", "orange");
        return;
      }
      const message = store[targetConversationId][idx];
      const url = messageReactionUrl(options?.reaction_url_template, message);
      if (!url || !options?.can_react || !message?.serverId) {
        toast("Reactions are not available for this message or role.", "orange");
        return;
      }
      try {
        const body = await apiJson(url, {
          method: "PATCH",
          body: JSON.stringify({ emoji, action: "toggle" }),
        });
        const updated = serverMessageToChat(body.data, options.current_user_id);
        setStore(current => ({
          ...current,
          [targetConversationId]: (current[targetConversationId] || []).map((item, itemIdx) => itemIdx === idx ? { ...item, ...updated } : item),
        }));
      toast("Reaction updated.", "green");
      } catch (error) {
        toast(error.message, "red");
      }
    };

    const onThread = idx => {
      const targetConversationId = currentConversationId || activeId;
      if (!targetConversationId || !store[targetConversationId]) {
        toast("Thread replies require an active conversation.", "orange");
        return;
      }
      const message = store[targetConversationId][idx];
      if (!message?.serverId || options?.can_post === false || !(conv?.chatConversationId ? options?.conversation_message_store_url_template : options?.store_url)) {
        toast("Thread replies are not available for this message or role.", "orange");
        return;
      }
      setReplyTarget({ ...message, conversationId: targetConversationId });
      setDrafts(dr => ({ ...dr, [targetConversationId]: dr[targetConversationId] || "" }));
      toast("Reply target selected.", "green");
    };

    const mentionCandidates = () => {
      const memberRows = Array.isArray(conv?.memberUsers) ? conv.memberUsers : [];
      const rows = memberRows.length ? memberRows : internalRecipients;
      const seen = new Set();
      return rows
        .map(person => ({
          ...person,
          id: person.id || person.serverId,
          label: person.label || person.name,
          title: person.title || person.role || person.designation,
        }))
        .filter(person => {
          const id = String(person.id || "");
          if (!id || seen.has(id)) return false;
          seen.add(id);
          return String(id) !== String(options?.current_user_id || "");
        });
    };

    const setDraftText = (draftKey, value) => {
      setDrafts(dr => ({ ...dr, [draftKey]: value }));
    };

    const insertEmoji = em => {
      if (!activeId) {
        toast("Select or create a conversation before composing.", "orange");
        return;
      }
      const draftKey = currentConversationId || activeId;
      setDraftText(draftKey, (drafts[draftKey] || "") + em);
      setEmojiOpen(false);
    };

    const openMentionPicker = () => {
      if (!activeId) {
        toast("Select or create a conversation before mentioning a teammate.", "orange");
        return;
      }
      setMentionQuery("");
      setEmojiOpen(false);
      setMentionOpen(true);
    };

    const chooseMention = person => {
      const draftKey = currentConversationId || activeId;
      const label = recipientLabel(person).replace(/^@+/, "").trim();
      const current = drafts[draftKey] || "";
      const withoutTrigger = current.replace(/@[\w .-]*$/, "");
      const suffix = (withoutTrigger && !/\s$/.test(withoutTrigger)) ? " " : "";
      setDraftText(draftKey, withoutTrigger + suffix + "@" + label + " ");
      setDraftMentions(currentMentions => ({
        ...currentMentions,
        [draftKey]: Array.from(new Set([...(currentMentions[draftKey] || []), Number(person.id)].filter(Boolean))),
      }));
      setMentionOpen(false);
      setMentionQuery("");
      setEmojiOpen(false);
    };

    const openConversationModal = () => {
      if (options?.can_create && (options?.conversation_store_url || options?.store_url)) {
        setConversationModal(true);
        return;
      }
      toast("New conversation requires collaboration message create permission.", "orange");
    };

    const onConversationCreated = rows => {
      const normalized = rows && rows.data && rows.data.conversation_key
        ? chatConversationPayload(rows, options?.current_user_id)
        : serverThreadPayload(Array.isArray(rows) ? rows : [rows].filter(Boolean), options?.current_user_id);
      if (!normalized.conversations.length) return;
      const created = normalized.conversations[0];
      setConversations(current => [created, ...current.filter(row => row.id !== created.id)]);
      setStore(current => ({ ...current, ...normalized.messages }));
      setActiveId(created.id);
      setConnectionStatus("Chat conversation created.");
    };

    const onForward = idx => {
      const targetConversationId = currentConversationId || activeId;
      const message = (store[targetConversationId] || [])[idx];
      if (!message?.serverId || options?.can_post === false || !options?.conversation_message_store_url_template) {
        toast("Message forwarding is not available for this message or role.", "orange");
        return;
      }
      setForwardingMessage(message);
    };

    const onForwarded = (body, targetConversationId) => {
      if (!body?.data || !targetConversationId) return;
      const saved = serverMessageToChat(body.data, options?.current_user_id);
      setStore(current => ({
        ...current,
        [targetConversationId]: [...(current[targetConversationId] || []).filter(message => !message.typing && message.serverId !== saved.serverId), saved],
      }));
      setConversations(current => current.map(row => row.id === targetConversationId ? {
        ...row,
        last: saved.text || "Forwarded message",
        t: saved.t,
        unread: 0,
      } : row));
      setActiveId(targetConversationId);
      setConnectionStatus("Forwarded chat message sent.");
      loadServerThreads(true).catch(() => {});
    };

    const archiveLoadedThreads = async threadRows => {
      const rows = Array.isArray(threadRows) ? threadRows : [threadRows].filter(Boolean);
      if (!rows.length) {
        toast("Select at least one chat thread to archive.", "orange");
        return false;
      }
      if (!options?.state_url_template || !options?.can_update_state) {
        toast("Chat thread archival is not available for your role.", "orange");
        return false;
      }
      const threadIds = rows.map(row => row.id);
      const conversationArchiveRows = rows.filter(row => row.chatConversationId && options?.conversation_archive_url_template);
      if (conversationArchiveRows.length) {
        try {
          await Promise.all(conversationArchiveRows.map(row => apiJson(options.conversation_archive_url_template.replace("__CONVERSATION__", row.chatConversationId), {
            method: "PATCH",
            body: JSON.stringify({}),
          })));
          setConversations(current => current.filter(row => !threadIds.includes(row.id)));
          setStore(current => {
            const next = { ...current };
            threadIds.forEach(id => { delete next[id]; });
            return next;
          });
          if (threadIds.includes(activeId)) setActiveId(null);
          setConnectionStatus("Selected chat thread(s) archived.");
          toast("Archived " + rows.length + " chat thread" + (rows.length === 1 ? "" : "s") + ".", "green");
          return true;
        } catch (error) {
          toast(error.message || "Chat thread archive failed.", "red");
          return false;
        }
      }
      const messageRows = threadIds.flatMap(id => store[id] || []).filter(message => message?.serverId);
      if (!messageRows.length) {
        toast("No saved messages are loaded for the selected thread(s).", "orange");
        return false;
      }
      try {
        await Promise.all(messageRows.map(message => apiJson(options.state_url_template.replace("__MESSAGE__", message.serverId), {
          method: "PATCH",
          body: JSON.stringify({ action: "move", folder: "archived" }),
        })));
        setConversations(current => current.filter(row => !threadIds.includes(row.id)));
        setStore(current => {
          const next = { ...current };
          threadIds.forEach(id => { delete next[id]; });
          return next;
        });
        if (threadIds.includes(activeId)) setActiveId(null);
        setConnectionStatus("Selected chat thread(s) archived.");
        toast("Archived " + rows.length + " chat thread" + (rows.length === 1 ? "" : "s") + ".", "green");
        return true;
      } catch (error) {
        toast(error.message || "Chat thread archive failed.", "red");
        return false;
      }
    };
    const listPane = e("div", { className: "chat-list-shell" + (listOpen ? "" : " collapsed") },
      e(ConvList, { conversations, active: activeId, onPick: id => { setActiveId(id); }, filter, setFilter, q, setQ, toast, onNewConversation: openConversationModal }),
      listOpen && e("button", { className: "chat-list-toggle inside", "aria-expanded": "true", onClick: () => setListOpen(false) }, e(Icon, { name: "chevL", size: 14 }), "Hide chats"));
    const chatShellClass = "chat" + (fullScreen ? " chat-fullscreen" : "");
    const headerTools = e("div", { className: "chat-head-tools" },
      e("button", { className: "chat-list-toggle", "aria-expanded": listOpen ? "true" : "false", onClick: () => setListOpen(open => !open) }, e(Icon, { name: listOpen ? "chevL" : "layers", size: 15 }), listOpen ? "Hide chats" : "Show chats"),
      e("button", { className: "chat-list-toggle", onClick: () => setFullScreen(v => !v) }, e(Icon, { name: fullScreen ? "x" : "expand", size: 15 }), fullScreen ? "Exit Full Screen" : "Full Screen"));

    if (!conv) {
      const canCreateConversation = !!(options?.can_create && (options?.conversation_store_url || options?.store_url) && !readOnlyReason);
      if (view === "admin") return e(window.ChatAdmin, { role, toast, onBack: () => setView("chat"), conversations, messages: store, connectionStatus, mailboxOptions: options, onArchiveThreads: archiveLoadedThreads });
      return e("div", { className: chatShellClass },
        listPane,
        e("div", { className: "chat-main" },
          e("div", { className: "chat-head" },
            e("div", { className: "ch-ic", style: { background: "var(--slate)" } }, e(Icon, { name: "bubble", size: 19 })),
            e("div", { style: { minWidth: 0 } },
              e("div", { className: "ch-name" }, "Chat Connect"),
              e("div", { className: "ch-sub" }, e(Icon, { name: "shield", size: 12 }), "Internal conversations")),
            headerTools),
          readOnlyReason && e("div", { className: "sys-note", style: { margin: "10px 16px 0" } }, e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), readOnlyReason),
          e("div", { className: "msgs", style: { display: "grid", placeItems: "center", padding: 28 } },
            e("div", { style: { textAlign: "center", maxWidth: 520 } },
              e(Icon, { name: "bubble", size: 36, style: { color: "var(--text-3)", marginBottom: 10 } }),
              e("h2", { style: { margin: "0 0 8px", fontSize: 20 } }, "No conversation selected"),
              e("p", { className: "muted", style: { margin: "0 0 16px" } }, readOnlyReason || "Select a conversation from the list or create a Builder360 conversation to start collaborating."),
              e("div", { className: "row gap-2", style: { justifyContent: "center", flexWrap: "wrap" } },
                readOnlyReason && e("button", { className: "btn", onClick: () => loadServerThreads(false) }, e(Icon, { name: "refresh", size: 14 }), "Retry"),
                readOnlyReason && e("button", { className: "btn", onClick: () => { window.location.href = "/login"; } }, "Re-authenticate"),
                e("button", { className: "btn btn-primary", disabled: !canCreateConversation, onClick: openConversationModal }, "New conversation")))),
          conversationModal && e(ChatConversationModal, { options, onClose: () => setConversationModal(false), onCreated: onConversationCreated, toast })));
    }

    // ---- chat header ----
    const dm = conv.kind === "dm";
    const who = dm ? P[conv.who] : null;
    const headSub = dm
      ? e(React.Fragment, null, e("span", { className: "pres-dot", style: { position: "static", border: "none", width: 8, height: 8, background: PR[who.pres].c } }), PR[who.pres].label + " · " + who.title)
      : e(React.Fragment, null,
          e(Icon, { name: "users", size: 12 }),
          conv.members + " members",
          e("span", { className: "dot-sep" }),
          conv.sub,
          conv.conversationType && e("span", { className: "badge b-blue chat-type-badge" }, String(conv.conversationType).replace(/_/g, " ")));

    const header = e("div", { className: "chat-head" },
      dm ? e(PAv, { id: conv.who, size: 40 }) : e("div", { className: "ch-ic", style: { background: conv.color } }, e(Icon, { name: conv.icon, size: 19 })),
      e("div", { style: { minWidth: 0 } },
        e("div", { className: "ch-name" }, conv.name,
          conv.kind === "announce" && e(Badge, { tone: "b-accent" }, "Announcement"),
          conv.guest && e(Badge, { tone: "b-orange" }, "Guest access")),
      e("div", { className: "ch-sub" }, headSub)),
      e("div", { className: "ch-actions" },
        headerTools,
        e("button", { className: "ch-btn" + (threadSearchOpen ? " on" : ""), title: "Search messages", onClick: () => setThreadSearchOpen(open => !open) }, e(Icon, { name: "search", size: 17 })),
        !conv.serverBacked && e("button", { className: "ch-btn", title: "Pinned", onClick: () => { setShowDetails(true); setDetTab("pinned"); } }, e(Icon, { name: "pin", size: 17 })),
        !conv.serverBacked && e("button", { className: "ch-btn", title: "Shared files", onClick: () => { setShowDetails(true); setDetTab("files"); } }, e(Icon, { name: "clip", size: 17 })),
        e("button", { className: "ch-btn", title: "Admin console", onClick: () => setView("admin") }, e(Icon, { name: "chart", size: 17 })),
        !conv.serverBacked && e("button", { className: "ch-btn" + (showDetails ? " on" : ""), title: "Details", onClick: () => setShowDetails(o => !o) }, e(Icon, { name: "sliders", size: 17 }))));

    // ---- pinned banner ----
    const banner = conv.kind !== "dm" && !conv.serverBacked && e("div", { className: "pin-banner" },
      e(Icon, { name: "pin", size: 14, style: { color: "var(--accent)" } }),
      e("span", null, CHAT.pinnedMsgs[0] ? e(React.Fragment, null, e("b", null, CHAT.pinnedMsgs[0].by + ": "), CHAT.pinnedMsgs[0].text) : "No pinned messages"),
      e("button", { className: "ct-btn", style: { marginLeft: "auto", width: 26, height: 26 }, onClick: () => { setShowDetails(true); setDetTab("pinned"); } }, e(Icon, { name: "chevR", size: 15 })));

    const latestServerMessage = msgs.slice().reverse().find(m => m && m.serverId);
    const onTaskCreated = task => {
      const assigneeName = task?.assigned_to?.name || "Assignee";
      const targetConversationId = currentConversationId || activeId;
      setStore(s => ({
        ...s,
        [targetConversationId]: [...(s[targetConversationId] || []).filter(m => !m.typing), {
          you: true,
          t: new Date().toLocaleTimeString("en-IN", { hour: "numeric", minute: "2-digit", hour12: true }),
          task: true,
          taskRecordId: task?.id || null,
          taskNumber: task?.task_number || null,
          title: (task?.task_number ? task.task_number + " · " : "") + (task?.title || "Chat task"),
          assignee: assigneeName,
          due: task?.due_at ? new Date(task.due_at).toLocaleDateString("en-IN", { day: "2-digit", month: "short" }) : "No due date",
          priority: String(task?.priority || "medium").replace(/^./, c => c.toUpperCase()),
          project: task?.project?.code || task?.module_context || "Chat",
          read: true,
        }],
      }));
    };

    const openTaskFromChat = taskRecordId => {
      if (!taskRecordId) {
        toast("Task link is available after the task is saved.", "orange");
        return;
      }
      window.location.hash = "tasks?task=" + encodeURIComponent(String(taskRecordId));
      if (window.Builder360Navigate) window.Builder360Navigate("tasks");
      else window.dispatchEvent(new CustomEvent("builder360:navigate", { detail: { route: "tasks" } }));
      toast("Opening linked task from Chat Connect.", "green");
    };

    const visibleMessageRows = msgs
      .map((message, index) => ({ message, index }))
      .filter(row => {
        const needle = threadSearch.trim().toLowerCase();
        if (!needle) return true;
        return [
          row.message.text,
          row.message.messageNumber,
          row.message.threadKey,
          row.message.status,
          P[row.message.by]?.name,
        ].filter(Boolean).join(" ").toLowerCase().includes(needle);
      });

    // ---- composer ----
    const readonly = !!readOnlyReason || !!conv.readonly;
    const composer = readonly
      ? e("div", { className: "composer" }, e("div", { className: "composer-note" }, e(Icon, { name: readOnlyReason ? "shield" : "mega", size: 15 }), readOnlyReason || "This conversation is read-only for your role."))
      : e("div", { className: "composer" },
          options?.store_url && !conv.chatConversationId && e("div", { className: "chat-recipient-strip" },
            internalRecipients.length > 0
            ? e("label", { className: "chat-sendto-label" },
                "Send to",
                e(RecipientPicker, { recipients: internalRecipients, selected: selectedRecipientId, onChange: setSelectedRecipientId, compact: true }))
            : e("span", { style: { color: "var(--red)", fontWeight: 800 } }, "No internal recipients available")),
          activeReplyTarget && e("div", { className: "composer-note", style: { justifyContent: "space-between", gap: 10 } },
            e("span", null, e(Icon, { name: "reply", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), "Replying to ", activeReplyTarget.messageNumber || "selected message", ": ", String(activeReplyTarget.text || "").slice(0, 90)),
            e("button", { type: "button", className: "ct-btn", title: "Clear reply target", onClick: () => setReplyTarget(null) }, e(Icon, { name: "x", size: 14 }))),
          e("div", { className: "composer-box" },
            e("textarea", { rows: 2, value: drafts[currentConversationId] || "", placeholder: "Message " + conv.name + "…  (Enter to send · Shift+Enter for newline)",
              onChange: ev => {
                const value = ev.target.value;
                setDraftText(currentConversationId, value);
                const match = value.match(/@([\w .-]*)$/);
                if (match) {
                  setMentionOpen(true);
                  setMentionQuery(match[1] || "");
                } else {
                  setMentionOpen(false);
                  setMentionQuery("");
                }
                ev.target.style.height = "auto";
                ev.target.style.height = Math.min(ev.target.scrollHeight, 180) + "px";
              },
              onKeyDown: ev => { if (ev.key === "Enter" && !ev.shiftKey) { ev.preventDefault(); send(); ev.target.style.height = "auto"; } } }),
            mentionOpen && e(MentionPicker, { people: mentionCandidates(), query: mentionQuery, onQuery: setMentionQuery, onPick: chooseMention, onClose: () => setMentionOpen(false) }),
            emojiOpen && e("div", { className: "chat-composer-emoji", role: "menu", "aria-label": "Choose emoji" },
              CHAT.emojis.map(em => e("button", { key: em, type: "button", role: "menuitem", onClick: () => insertEmoji(em) }, em))),
            selectedFiles.length > 0 && e("div", { className: "chat-selected-files" },
              selectedFiles.map((file, index) => e("span", { key: index, className: "chat-file-pill" },
                e(Icon, { name: String(file.type || "").startsWith("audio/") ? "mic" : "clip", size: 13 }),
                file.name || "Attachment",
                e("button", { type: "button", onClick: () => setSelectedFiles(files => files.filter((_, fileIdx) => fileIdx !== index)) }, "×")))),
            micNotice && e("div", { className: "chat-mic-notice" },
              e(Icon, { name: micNotice.type === "unsupported" ? "alert" : "mic", size: 15 }),
              e("span", null, micNotice.message),
              micNotice.type !== "unsupported" && e("button", { type: "button", className: "btn btn-sm", onClick: startVoice }, "Retry"),
              e("button", { type: "button", className: "btn btn-sm", onClick: () => setMicNotice(null) }, "Continue typing")),
            recording && e("div", { className: "chat-recording-note" },
              e(Icon, { name: "mic", size: 14 }),
              "Recording voice note · ", recordSeconds, "s",
              e("button", { type: "button", className: "btn btn-sm", onClick: () => stopVoice(false) }, "Stop"),
              e("button", { type: "button", className: "btn btn-sm", onClick: () => stopVoice(true) }, "Cancel")),
            e("div", { className: "composer-tools" },
              e("input", { ref: fileInputRef, type: "file", multiple: true, style: { display: "none" }, onChange: ev => { attachFiles(ev.target.files); ev.target.value = ""; } }),
              e("button", { className: "ct-btn", title: "Attach files", disabled: !(options?.capabilities?.upload || options?.can_upload), onClick: () => fileInputRef.current?.click() }, e(Icon, { name: "clip", size: 17 })),
              e("button", { className: "ct-btn" + (emojiOpen ? " on" : ""), title: "Emoji", onClick: () => { setMentionOpen(false); setEmojiOpen(open => !open); } }, e(Icon, { name: "smile", size: 17 })),
              e("button", { className: "ct-btn" + (mentionOpen ? " on" : ""), title: "Mention", onClick: openMentionPicker }, e(Icon, { name: "at", size: 16 })),
              e("button", { className: "ct-btn", title: "Create poll", disabled: !(options?.capabilities?.poll || options?.can_create_poll) || !conv.chatConversationId, onClick: () => setPollModal(true) }, e(Icon, { name: "poll", size: 16 })),
              e("button", { className: "ct-btn", title: recording ? "Stop voice note" : "Record voice note", disabled: !(options?.capabilities?.voice || options?.can_send_voice), onClick: () => recording ? stopVoice(false) : startVoice() }, e(Icon, { name: "mic", size: 16 })),
              e("button", { className: "ct-btn", title: "Create task", onClick: () => taskOptions?.can_create && taskOptions?.store_url ? setTaskModal(true) : toast("Creating tasks from chat requires collaboration task create permission.", "orange") }, e(Icon, { name: "check", size: 16 })),
              e("button", { className: "send-btn", disabled: sending || (!(drafts[currentConversationId] || "").trim() && !selectedFiles.length), onClick: send }, e(Icon, { name: sending ? "clock" : "send", size: 17 })))));

    if (view === "admin") return e(window.ChatAdmin, { role, toast, onBack: () => setView("chat"), conversations, messages: store, connectionStatus, mailboxOptions: options, onArchiveThreads: archiveLoadedThreads });

    return e("div", { className: chatShellClass },
      listPane,
      e("div", { className: "chat-main" },
        header, banner,
        readOnlyReason && e("div", { className: "sys-note", style: { margin: "10px 16px 0" } }, e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }), readOnlyReason),
        threadSearchOpen && e("div", { className: "sys-note", style: { margin: "10px 16px 0", display: "flex", gap: 10, alignItems: "center" } },
          e(Icon, { name: "search", size: 13 }),
          e("input", { value: threadSearch, onChange: ev => setThreadSearch(ev.target.value), placeholder: "Search messages in this thread...", autoFocus: true, style: { flex: 1, border: "none", outline: "none", background: "transparent", color: "var(--text)", font: "inherit", fontSize: 12.5, fontWeight: 700 } }),
          e("span", { className: "faint", style: { fontSize: 11, fontWeight: 800 } }, visibleMessageRows.length + "/" + msgs.length),
          threadSearch && e("button", { className: "ct-btn", title: "Clear search", onClick: () => setThreadSearch("") }, e(Icon, { name: "x", size: 13 }))),
        e("div", { className: "msgs", ref: msgsRef },
          visibleMessageRows.length
            ? visibleMessageRows.map(row => e(Message, { key: row.index, m: row.message, idx: row.index, convId: currentConversationId, onReact: react, onThread, onForward, onOpenTask: openTaskFromChat, onPollVote: votePoll, toast }))
            : e("div", { className: "sys-note", style: { margin: 18 } }, e(Icon, { name: "search", size: 13, style: { verticalAlign: "-2px", marginRight: 5 } }), "No messages match this thread search.")),
        composer,
        conversationModal && e(ChatConversationModal, { options, onClose: () => setConversationModal(false), onCreated: onConversationCreated, toast }),
        pollModal && e(ChatPollModal, { options, conversation: conv, onClose: () => setPollModal(false), onCreated: onPollCreated, toast }),
        forwardingMessage && e(ChatForwardModal, { options, message: forwardingMessage, conversationName: conv.name, conversations, activeConversationId: currentConversationId, onClose: () => setForwardingMessage(null), onForwarded, toast }),
        taskModal && e(ChatTaskModal, { conv, draft: drafts[currentConversationId] || "", latestMessage: latestServerMessage, selectedRecipientId, taskOptions, onClose: () => setTaskModal(false), onCreated: onTaskCreated, toast })),
      showDetails && !conv.serverBacked && e(window.ChatDetails, { conv, tab: detTab, setTab: setDetTab, toast }));
  }

  window.ChatConnect = ChatConnect;
  window.__chatPAv = PAv;
  window.__chatRoleTone = roleTone;
})();
