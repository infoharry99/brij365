/* ============================================================
   Builder360 — Mailbox runtime configuration → window.MBOX
   This file intentionally does not seed local inbox, sent,
   draft, scheduled, CRM, account, or template rows. Visible
   mailbox messages must be loaded from Laravel collaboration
   APIs and governed System Settings metadata only.
   ============================================================ */
(function () {
  const accounts = [];

  const folders = [
    { id: "inbox", label: "Inbox", icon: "inbox" },
    { id: "starred", label: "Starred", icon: "star" },
    { id: "important", label: "Important", icon: "flag" },
    { id: "sent", label: "Sent", icon: "send" },
    { id: "drafts", label: "Drafts", icon: "doc" },
    { id: "scheduled", label: "Scheduled", icon: "calendar" },
    { id: "snoozed", label: "Snoozed", icon: "snooze" },
    { id: "archived", label: "Archived", icon: "archive" },
    { id: "spam", label: "Spam", icon: "alert" },
    { id: "trash", label: "Trash", icon: "trash" },
  ];

  const labels = [
    { id: "lead", label: "Leads", color: "#F6740C" },
    { id: "vendor", label: "Vendors", color: "#e08600" },
    { id: "buyer", label: "Buyers", color: "#15a657" },
    { id: "legal", label: "Legal / RERA", color: "#dc2f3a" },
    { id: "internal", label: "Internal", color: "#64748b" },
  ];

  const sig = "—\nBuilder360 ERP · CRM";
  const emails = [];
  const templates = [];
  const crmRecords = { contacts: [], companies: [], deals: [] };
  const folderUnread = () => 0;
  const folderCount = () => 0;

  window.MBOX = { accounts, folders, labels, emails, templates, crmRecords, sig, folderUnread, folderCount };
})();
