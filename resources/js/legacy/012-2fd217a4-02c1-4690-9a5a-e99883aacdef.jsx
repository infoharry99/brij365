const React = window.React;

/* Builder360 — Leads, Qualification, Sales & Booking, Collections, Marketing */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, BarChart, LineChart, Gauge, Spark, HBars, PageHead, ChipSelect, Seg, Empty } = window;
  const e = React.createElement;
  const DB = window.DB;

  function scoreTone(s) { return s >= 75 ? "b-green" : s >= 50 ? "b-orange" : "b-red"; }
  function leadBadge(stage, status) {
    if (status === "won" || stage === "Booked") return "b-green";
    if (status === "lost" || stage === "Lost") return "b-red";
    if (status === "on_hold") return "b-orange";
    if (stage === "Qualified") return "b-accent";
    if (["Site Visit Planned", "Site Visit Scheduled", "Site Visit Done", "Follow-up"].includes(stage)) return "b-orange";
    if (stage === "Negotiation") return "b-violet";
    return "b-slate";
  }
  function leadScore(stage, status) {
    if (status === "won" || stage === "Booked") return 95;
    if (status === "lost" || stage === "Lost") return 15;
    if (stage === "Negotiation") return 82;
    if (stage === "Site Visit Done") return 76;
    if (["Site Visit Planned", "Site Visit Scheduled"].includes(stage)) return 68;
    if (stage === "Qualified") return 62;
    if (stage === "Follow-up") return 44;
    return 35;
  }
  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  }
  function firstApiError(payload) {
    const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
    return errors[0] || payload?.message || "The CRM request could not be saved.";
  }
  async function apiJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: "same-origin",
      headers: Object.assign({
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
      }, options.headers || {}),
      ...options,
    });
    const text = await response.text();
    const payload = text ? JSON.parse(text) : {};
    if (!response.ok) throw new Error(firstApiError(payload));
    return payload;
  }
  function formatInr(amount) {
    return "₹" + Number(amount || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
  }
  function formatCount(value) {
    return Number(value || 0).toLocaleString("en-IN");
  }
  function leadMatchesKanbanColumn(lead, column) {
    const stages = column?.stages || [];
    const statuses = column?.statuses || [];

    return stages.includes(lead.status) || statuses.includes(lead.system_status);
  }
  function dateTimeLocalValue(date) {
    const pad = value => String(value).padStart(2, "0");
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }
  function leadActivityIcon(type) {
    if (type === "call") return "headset";
    if (type === "email") return "mail";
    if (type === "follow_up") return "calendar";
    if (type === "campaign_response") return "mega";
    return "activity";
  }
  function leadActivityColor(type, outcome) {
    if (["positive", "connected", "interested"].includes(outcome || "")) return "var(--green)";
    if (["negative", "not_connected", "not_interested"].includes(outcome || "")) return "var(--red)";
    if (type === "call") return "var(--accent)";
    if (type === "follow_up") return "var(--orange)";
    return "var(--text-3)";
  }
  function leadActivityRowFromApi(data) {
    return {
      activity_number: data.activity_number,
      activity_type: data.activity_type,
      activity_at: data.activity_at,
      t: data.activity_at ? "Just now" : "Time pending",
      who: data.actor?.name || "Current user",
      act: data.description || data.subject || "Activity recorded",
      subject: data.subject,
      outcome: data.outcome,
      old_stage: data.old_stage,
      new_stage: data.new_stage,
      next_follow_up_at: data.next_follow_up_at,
      campaign: data.marketing_campaign?.campaign_code,
      ic: leadActivityIcon(data.activity_type),
      c: leadActivityColor(data.activity_type, data.outcome),
    };
  }
  function leadRowFromApi(data) {
    const stage = data.stage || "New";
    const status = data.status || "open";
    return {
      record_id: data.id,
      company_id: data.company?.id || null,
      project_id: data.project?.id || null,
      customer_id: data.customer?.id || null,
      partner_id: data.partner?.id || null,
      id: data.lead_code,
      lead_code: data.lead_code,
      name: data.customer?.name || "Unassigned customer",
      phone: data.customer?.phone || "Phone pending",
      source: data.source || "Direct",
      project: data.project?.name || "Project pending",
      budget: formatInr(data.expected_value),
      config: "Requirement pending",
      status: stage,
      system_status: status,
      badge: leadBadge(stage, status),
      score: leadScore(stage, status),
      exec: data.owner?.name || "Unassigned",
      next: data.follow_up_at ? new Date(data.follow_up_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : "No follow-up",
      can_log_activity: true,
      activity_create_url: "/crm/lead-activities",
      can_schedule_site_visit: true,
      site_visit_store_url: "/crm/site-visits",
      can_convert_booking: true,
      booking_store_url: "/sales/bookings",
      can_disposition: true,
      disposition_url: data.id ? `/crm/leads/${data.id}/disposition` : null,
      disposition: data.disposition || {},
      activities: data.activities || [],
    };
  }

  // ---------------- LEAD MANAGEMENT ----------------
  function Leads({ toast }) {
    const [sel, setSel] = React.useState(null);
    const [view, setView] = React.useState("Table");
    const [createOpen, setCreateOpen] = React.useState(false);
    const [importOpen, setImportOpen] = React.useState(false);
    const [filters, setFilters] = React.useState({ status: "All", source: "All", executive: "All", project: "All" });
    const serverLeads = Array.isArray(window.Builder360Server?.crm_leads) ? window.Builder360Server.crm_leads : [];
    const serverLeadMetrics = window.Builder360Server?.crm_lead_metrics || null;
    const createOptions = window.Builder360Server?.crm_lead_create_options || null;
    const importOptions = window.Builder360Server?.crm_import_options || null;
    const siteVisitOptions = window.Builder360Server?.crm_site_visit_options || null;
    const bookingOptions = window.Builder360Server?.crm_booking_options || null;
    const emptyLeadMetrics = { source: "server-unavailable", summary: { total_leads: 0, open_leads: 0, new_this_week: 0, hot_leads: 0, follow_ups_due: 0, overdue_follow_ups: 0, avg_response_hours: null }, kanban_columns: [] };
    const [leads, setLeads] = React.useState(() => serverLeads);
    const leadMetrics = serverLeadMetrics || emptyLeadMetrics;
    const leadSummary = leadMetrics.summary || {};
    const activeLeadCount = leadSummary.open_leads ?? 0;
    const totalLeadCount = leadSummary.total_leads ?? 0;
    const avgResponse = leadSummary.avg_response_hours;
    const filterStyle = { height: 36, border: "1px solid var(--border)", borderRadius: 999, background: "var(--surface)", color: "var(--text)", padding: "0 34px 0 12px", fontFamily: "inherit", fontSize: 12.5, fontWeight: 700 };
    const uniqueFilterValues = key => Array.from(new Set(leads.map(lead => String(lead[key] || "").trim()).filter(Boolean))).sort((a, b) => a.localeCompare(b));
    const statusOptions = Array.from(new Set(leads.map(lead => String(lead.status || lead.system_status || "").trim()).filter(Boolean))).sort((a, b) => a.localeCompare(b));
    const sourceOptions = uniqueFilterValues("source");
    const executiveOptions = uniqueFilterValues("exec");
    const projectOptions = uniqueFilterValues("project");
    const setFilter = (key, value) => setFilters(current => ({ ...current, [key]: value }));
    const resetFilters = () => setFilters({ status: "All", source: "All", executive: "All", project: "All" });
    const filteredLeads = leads.filter(lead => {
      if (filters.status !== "All" && String(lead.status || lead.system_status || "") !== filters.status) return false;
      if (filters.source !== "All" && String(lead.source || "") !== filters.source) return false;
      if (filters.executive !== "All" && String(lead.exec || "") !== filters.executive) return false;
      if (filters.project !== "All" && String(lead.project || "") !== filters.project) return false;
      return true;
    });
    async function createLead(payload) {
      if (!createOptions?.store_url) {
        toast("Lead capture is available only for CRM manager roles.", "orange");
        return null;
      }

      const response = await fetch(createOptions.store_url, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify(payload),
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(firstApiError(body));
      }

      const row = leadRowFromApi(body.data || {});
      setLeads(rows => [row, ...rows]);
      setSel(row);
      setCreateOpen(false);
      toast("Lead captured successfully", "green");

      return row;
    }
    async function previewImport(payload) {
      if (!importOptions?.preview_url || !importOptions?.can_import) {
        toast("CRM import is available only through authorized settings governance roles.", "orange");
        return null;
      }

      const data = new FormData();
      data.append("import_type", importOptions.import_type || "crm_prospect_inquiries");
      data.append("source_file", payload.source_file);
      if (payload.company_id) data.append("company_id", payload.company_id);
      if (payload.note) data.append("note", payload.note);

      const response = await fetch(importOptions.preview_url, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
          "X-Requested-With": "XMLHttpRequest",
        },
        body: data,
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(firstApiError(body));
      }

      toast("Import preview generated", "green");
      return body.data || null;
    }
    async function postImport(batch, note) {
      if (!batch?.id || !importOptions?.post_url_template) {
        toast("Generate a valid import preview before posting.", "orange");
        return null;
      }

      const response = await fetch(importOptions.post_url_template.replace("__BATCH__", batch.id), {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({ note }),
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(firstApiError(body));
      }

      toast("Import posted to prospect inquiries", "green");
      return body.data || null;
    }
    async function submitDisposition(lead, payload) {
      if (!lead.disposition_url) {
        toast("Disposition is available after server-backed leads are loaded.", "orange");
        return null;
      }

      const response = await fetch(lead.disposition_url, {
        method: "PATCH",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify(payload),
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(firstApiError(body));
      }

      const data = body.data || {};
      const updated = {
        ...lead,
        status: data.stage || lead.status,
        system_status: data.status || lead.system_status,
        badge: leadBadge(data.stage || lead.status, data.status || lead.system_status),
        score: leadScore(data.stage || lead.status, data.status || lead.system_status),
        next: data.follow_up_at ? new Date(data.follow_up_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : "No follow-up",
        disposition: data.disposition || lead.disposition,
        activities: data.activities || lead.activities || [],
      };
      setLeads(rows => rows.map(row => row.id === lead.id ? updated : row));
      setSel(updated);
      toast("Lead disposition saved", "green");

      return updated;
    }
    async function logLeadActivity(lead, payload) {
      if (!lead.activity_create_url || !lead.can_log_activity) {
        toast("Lead activity logging is available only for CRM manager roles.", "orange");
        return null;
      }

      const response = await fetch(lead.activity_create_url, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({
          lead_id: lead.record_id,
          marketing_campaign_id: lead.marketing_campaign_id || null,
          activity_type: "call",
          activity_at: new Date().toISOString(),
          ...payload,
        }),
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(firstApiError(body));
      }

      const activity = leadActivityRowFromApi(body.data || {});
      const nextFollowUp = body.data?.next_follow_up_at || payload.next_follow_up_at || null;
      const updated = {
        ...lead,
        next: nextFollowUp ? new Date(nextFollowUp).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : lead.next,
        activities: [activity, ...(lead.activities || [])].slice(0, 5),
      };

      setLeads(rows => rows.map(row => row.id === lead.id ? updated : row));
      setSel(updated);
      toast("Call activity logged", "green");

      return updated;
    }
    async function scheduleSiteVisit(lead, payload) {
      const storeUrl = lead.site_visit_store_url || siteVisitOptions?.store_url;

      if (!storeUrl || !lead.can_schedule_site_visit) {
        toast("Site visit scheduling is available only for CRM manager roles.", "orange");
        return null;
      }

      const response = await fetch(storeUrl, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({
          lead_id: lead.record_id,
          ...payload,
        }),
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(firstApiError(body));
      }

      const visit = body.data || {};
      const scheduledAt = visit.scheduled_at || payload.scheduled_at;
      const activity = {
        activity_number: visit.visit_number,
        activity_type: "site_visit",
        activity_at: scheduledAt,
        t: "Just now",
        who: visit.scheduled_by?.name || "Current user",
        act: `Site visit ${visit.visit_number || ""} scheduled for ${scheduledAt ? new Date(scheduledAt).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : "selected time"}`,
        subject: "Site visit scheduled",
        outcome: "scheduled",
        new_stage: "Site Visit Scheduled",
        next_follow_up_at: scheduledAt,
        ic: "calendar",
        c: "var(--orange)",
      };
      const updated = {
        ...lead,
        status: "Site Visit Scheduled",
        badge: leadBadge("Site Visit Scheduled", lead.system_status),
        score: leadScore("Site Visit Scheduled", lead.system_status),
        next: scheduledAt ? new Date(scheduledAt).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : lead.next,
        activities: [activity, ...(lead.activities || [])].slice(0, 5),
      };

      setLeads(rows => rows.map(row => row.id === lead.id ? updated : row));
      setSel(updated);
      toast("Site visit scheduled", "green");

      return updated;
    }
    async function convertLeadToBooking(lead, payload) {
      const storeUrl = lead.booking_store_url || bookingOptions?.store_url;

      if (!storeUrl || !lead.can_convert_booking) {
        toast("Lead conversion is available only for booking manager roles.", "orange");
        return null;
      }

      const response = await fetch(storeUrl, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({
          customer_id: lead.customer_id,
          lead_id: lead.record_id,
          partner_id: lead.partner_id || null,
          ...payload,
        }),
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(firstApiError(body));
      }

      const booking = body.data || {};
      const updated = {
        ...lead,
        status: "Booked",
        system_status: "won",
        badge: leadBadge("Booked", "won"),
        score: leadScore("Booked", "won"),
        next: "Booking " + (booking.booking_code || "confirmed"),
        can_convert_booking: false,
        can_disposition: false,
        activities: [{
          activity_number: booking.booking_code,
          activity_type: "booking",
          activity_at: booking.created_at,
          t: "Just now",
          who: booking.booked_by?.name || "Current user",
          act: `Booking ${booking.booking_code || ""} confirmed for ${booking.unit?.unit_code || "selected unit"}`,
          subject: "Booking confirmed",
          outcome: "won",
          new_stage: "Booked",
          ic: "tag",
          c: "var(--green)",
        }, ...(lead.activities || [])].slice(0, 5),
      };

      setLeads(rows => rows.map(row => row.id === lead.id ? updated : row));
      setSel(updated);
      toast("Lead converted to booking", "green");

      return updated;
    }
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Sales & CRM", "Lead Management"], title: "Lead Management", sub: `${formatCount(totalLeadCount)} leads · ${formatCount(activeLeadCount)} active in pipeline. Capture, assign, qualify and follow up.`,
        actions: [e(Seg, { key: 0, options: ["Table", "Kanban"], value: view, onChange: setView }), e(Button, { key: 2, icon: "upload", disabled: !importOptions?.can_import, onClick: () => importOptions?.can_import ? setImportOpen(true) : toast("CRM import is available only through authorized settings governance roles.", "orange"), children: "Import" }), e(Button, { key: 3, icon: "plus", variant: "primary", onClick: () => createOptions?.can_create ? setCreateOpen(true) : toast("Lead capture is available only for CRM manager roles.", "orange"), children: "Add Lead" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "New This Week", value: formatCount(leadSummary.new_this_week), icon: "users", tone: "accent", sub: `${leadMetrics.source || "server"} scoped` }),
        e(Stat, { label: "Hot Leads", value: formatCount(leadSummary.hot_leads), icon: "flame", tone: "red", sub: "Score 75+ active leads" }),
        e(Stat, { label: "Follow-ups Due", value: formatCount(leadSummary.follow_ups_due), icon: "clock", tone: "orange", sub: `${formatCount(leadSummary.overdue_follow_ups)} overdue` }),
        e(Stat, { label: "Avg. Response Time", value: avgResponse === null || avgResponse === undefined ? "—" : String(avgResponse), unit: avgResponse === null || avgResponse === undefined ? "" : "hrs", icon: "trend", tone: avgResponse === null || avgResponse === undefined ? "slate" : "green", sub: avgResponse === null || avgResponse === undefined ? "No response data" : "First non-system activity" }),
      ),
      view === "Kanban" ? e(Kanban, { leads: filteredLeads, columns: leadMetrics.kanban_columns }) :
      e("div", null,
        e("div", { className: "filterbar", "aria-label": "Lead table filters" },
          e("label", { className: "chip-select" }, e("span", null, "Status"), e("select", { value: filters.status, onChange: ev => setFilter("status", ev.target.value), style: filterStyle, "aria-label": "Filter leads by status" }, e("option", { value: "All" }, "All"), statusOptions.map(value => e("option", { key: value, value }, value)))),
          e("label", { className: "chip-select" }, e("span", null, "Source"), e("select", { value: filters.source, onChange: ev => setFilter("source", ev.target.value), style: filterStyle, "aria-label": "Filter leads by source" }, e("option", { value: "All" }, "All"), sourceOptions.map(value => e("option", { key: value, value }, value)))),
          e("label", { className: "chip-select" }, e("span", null, "Executive"), e("select", { value: filters.executive, onChange: ev => setFilter("executive", ev.target.value), style: filterStyle, "aria-label": "Filter leads by executive" }, e("option", { value: "All" }, "All"), executiveOptions.map(value => e("option", { key: value, value }, value)))),
          e("label", { className: "chip-select" }, e("span", null, "Project"), e("select", { value: filters.project, onChange: ev => setFilter("project", ev.target.value), style: filterStyle, "aria-label": "Filter leads by project" }, e("option", { value: "All" }, "All"), projectOptions.map(value => e("option", { key: value, value }, value)))),
          e("button", { type: "button", className: "btn btn-sm", onClick: resetFilters, disabled: !Object.values(filters).some(value => value !== "All") }, "Reset"),
          e("span", { className: "cell-sub" }, filteredLeads.length + " of " + leads.length + " leads")),
        e("div", { className: "card" },
          e("div", { className: "tbl-wrap" },
            e("table", { className: "tbl" },
              e("thead", null, e("tr", null, ["Lead", "Source", "Interest", "Score", "Status", "Owner", "Next Follow-up", ""].map((h, i) => e("th", { key: i }, h)))),
              e("tbody", null,
                filteredLeads.length ? filteredLeads.map(l =>
                  e("tr", { key: l.id, style: { cursor: "pointer" }, onClick: () => setSel(l) },
                    e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: l.name, sm: true }), e("div", null, e("div", { className: "cell-strong" }, l.name), e("div", { className: "cell-sub mono" }, l.phone)))),
                    e("td", null, e("span", { className: "tag" }, l.source)),
                    e("td", null, e("div", null, e("div", { style: { fontWeight: 600, fontSize: 12.5 } }, l.config + " · " + l.budget), e("div", { className: "cell-sub" }, l.project))),
                    e("td", null, e("div", { className: "row gap-2" }, e("div", { style: { width: 30, height: 30, borderRadius: 8, display: "grid", placeItems: "center", fontWeight: 800, fontSize: 12, fontFamily: "var(--font-mono)",
                      background: l.score >= 75 ? "var(--green-soft)" : l.score >= 50 ? "var(--orange-soft)" : "var(--red-soft)", color: l.score >= 75 ? "var(--green)" : l.score >= 50 ? "var(--orange)" : "var(--red)" } }, l.score))),
                    e("td", null, e(Badge, { tone: l.badge, dot: true }, l.status)),
                    e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: l.exec, sm: true, size: 24 }), e("span", { style: { fontSize: 12.5 } }, l.exec.split(" ")[0]))),
                    e("td", null, e("span", { style: { fontSize: 12.5, fontWeight: 600, color: l.next.includes("Overdue") ? "var(--red)" : "var(--text-2)" } }, l.next)),
                    e("td", null, e("button", { className: "icon-btn btn-sm", style: { width: 30, height: 30 } }, e(Icon, { name: "dots", size: 16 })))))
                : e("tr", { key: "no-filtered-leads" }, e("td", { colSpan: 8 }, e(Empty, { title: leads.length ? "No leads match the selected filters" : "Lead Management API required", sub: leads.length ? "Change or reset the filters to see more records." : "No local prototype leads are fabricated when Laravel-scoped CRM leads are unavailable." })))))))),
      sel && e(LeadDrawer, { lead: sel, onClose: () => setSel(null), toast, onDisposition: submitDisposition, onLogActivity: logLeadActivity, onScheduleVisit: scheduleSiteVisit, siteVisitOptions, onConvertBooking: convertLeadToBooking, bookingOptions }),
      createOpen && e(AddLeadModal, { options: createOptions, onClose: () => setCreateOpen(false), onSubmit: createLead }),
      importOpen && e(ImportLeadsModal, { options: importOptions, onClose: () => setImportOpen(false), onPreview: previewImport, onPost: postImport }),
    );
  }

  function AddLeadModal({ options, onClose, onSubmit }) {
    const companies = options?.companies || [];
    const initialCompanyId = companies[0]?.id ? String(companies[0].id) : "";
    const [form, setForm] = React.useState({
      company_id: initialCompanyId,
      project_id: "",
      partner_id: "",
      marketing_campaign_id: "",
      customer_name: "",
      customer_email: "",
      customer_phone: "",
      source: options?.sources?.[0] || "Walk-in",
      stage: options?.stages?.[0] || "New",
      expected_value: "",
      budget_min: "",
      budget_max: "",
      follow_up_at: "",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const fieldStyle = { height: 38, border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: "0 10px", fontFamily: "inherit", width: "100%" };
    const labelStyle = { display: "grid", gap: 6, fontSize: 12, fontWeight: 700, color: "var(--text-2)" };
    const projects = (options?.projects || []).filter(p => !form.company_id || String(p.company_id) === String(form.company_id));
    const campaigns = (options?.campaigns || []).filter(c => {
      if (form.company_id && String(c.company_id) !== String(form.company_id)) return false;
      if (form.project_id && c.project_id && String(c.project_id) !== String(form.project_id)) return false;
      return true;
    });
    function update(key, value) {
      setForm(current => {
        const next = { ...current, [key]: value };
        if (key === "company_id") {
          next.project_id = "";
          next.marketing_campaign_id = "";
        }
        if (key === "project_id") {
          next.marketing_campaign_id = "";
        }
        if (key === "marketing_campaign_id") {
          const campaign = (options?.campaigns || []).find(c => String(c.id) === String(value));
          if (campaign?.source) next.source = campaign.source;
        }
        return next;
      });
    }
    function nullableInteger(value) {
      return value ? Number(value) : null;
    }
    async function submit(ev) {
      ev.preventDefault();
      setError("");

      if (!form.company_id || !form.customer_name.trim() || (!form.customer_email.trim() && !form.customer_phone.trim()) || !form.expected_value) {
        setError("Company, customer name, email or phone, and expected value are required.");
        return;
      }

      setBusy(true);
      try {
        await onSubmit({
          company_id: Number(form.company_id),
          project_id: nullableInteger(form.project_id),
          partner_id: nullableInteger(form.partner_id),
          marketing_campaign_id: nullableInteger(form.marketing_campaign_id),
          customer_name: form.customer_name.trim(),
          customer_email: form.customer_email.trim() || null,
          customer_phone: form.customer_phone.trim() || null,
          source: form.source,
          stage: form.stage,
          expected_value: Number(form.expected_value),
          budget_min: form.budget_min ? Number(form.budget_min) : null,
          budget_max: form.budget_max ? Number(form.budget_max) : null,
          follow_up_at: form.follow_up_at || null,
        });
      } catch (apiError) {
        setError(apiError.message || "Lead capture failed.");
      } finally {
        setBusy(false);
      }
    }

    return e("div", { className: "scrim", onClick: onClose },
      e("form", { className: "modal", style: { width: 720, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "card-head" },
          e("div", null, e("div", { className: "card-title" }, "Add Lead"), e("div", { className: "cell-sub" }, "Server-backed CRM lead capture with scoped master data.")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Company", e("select", { style: fieldStyle, value: form.company_id, onChange: ev => update("company_id", ev.target.value), disabled: busy }, companies.map(c => e("option", { key: c.id, value: c.id }, `${c.code} · ${c.name}`)))),
            e("label", { style: labelStyle }, "Project", e("select", { style: fieldStyle, value: form.project_id, onChange: ev => update("project_id", ev.target.value), disabled: busy }, e("option", { value: "" }, "No project selected"), projects.map(p => e("option", { key: p.id, value: p.id }, `${p.code} · ${p.name}`)))),
            e("label", { style: labelStyle }, "Partner", e("select", { style: fieldStyle, value: form.partner_id, onChange: ev => update("partner_id", ev.target.value), disabled: busy }, e("option", { value: "" }, "Direct / no partner"), (options?.partners || []).map(p => e("option", { key: p.id, value: p.id }, `${p.code} · ${p.name}`)))),
            e("label", { style: labelStyle }, "Campaign", e("select", { style: fieldStyle, value: form.marketing_campaign_id, onChange: ev => update("marketing_campaign_id", ev.target.value), disabled: busy }, e("option", { value: "" }, "No campaign selected"), campaigns.map(c => e("option", { key: c.id, value: c.id }, `${c.code} · ${c.name}`))))),
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Customer Name", e("input", { style: fieldStyle, value: form.customer_name, onChange: ev => update("customer_name", ev.target.value), disabled: busy, required: true })),
            e("label", { style: labelStyle }, "Email", e("input", { style: fieldStyle, type: "email", value: form.customer_email, onChange: ev => update("customer_email", ev.target.value), disabled: busy })),
            e("label", { style: labelStyle }, "Phone", e("input", { style: fieldStyle, value: form.customer_phone, onChange: ev => update("customer_phone", ev.target.value), disabled: busy })),
            e("label", { style: labelStyle }, "Source", e("select", { style: fieldStyle, value: form.source, onChange: ev => update("source", ev.target.value), disabled: busy }, (options?.sources || ["Walk-in"]).map(source => e("option", { key: source, value: source }, source))))),
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Stage", e("select", { style: fieldStyle, value: form.stage, onChange: ev => update("stage", ev.target.value), disabled: busy }, (options?.stages || ["New"]).map(stage => e("option", { key: stage, value: stage }, stage)))),
            e("label", { style: labelStyle }, "Expected Value", e("input", { style: fieldStyle, type: "number", min: "0", step: "1000", value: form.expected_value, onChange: ev => update("expected_value", ev.target.value), disabled: busy, required: true })),
            e("label", { style: labelStyle }, "Budget Min", e("input", { style: fieldStyle, type: "number", min: "0", step: "1000", value: form.budget_min, onChange: ev => update("budget_min", ev.target.value), disabled: busy })),
            e("label", { style: labelStyle }, "Budget Max", e("input", { style: fieldStyle, type: "number", min: "0", step: "1000", value: form.budget_max, onChange: ev => update("budget_max", ev.target.value), disabled: busy }))),
          e("label", { style: { ...labelStyle, marginBottom: 12 } }, "Follow-up Date/Time", e("input", { style: fieldStyle, type: "datetime-local", value: form.follow_up_at, onChange: ev => update("follow_up_at", ev.target.value), disabled: busy })),
          error && e("div", { style: { color: "var(--red)", fontSize: 12, fontWeight: 700, marginBottom: 12 } }, error),
          e("div", { className: "row gap-2", style: { justifyContent: "flex-end" } },
            e("button", { type: "button", className: "btn", onClick: onClose, disabled: busy }, "Cancel"),
            e("button", { type: "submit", className: "btn btn-primary", disabled: busy || companies.length === 0 }, busy ? "Saving..." : "Save Lead")))));
  }

  function ImportLeadsModal({ options, onClose, onPreview, onPost }) {
    const companies = options?.companies || [];
    const initialCompanyId = options?.requires_company_selection ? "" : (companies[0]?.id ? String(companies[0].id) : "");
    const [companyId, setCompanyId] = React.useState(initialCompanyId);
    const [file, setFile] = React.useState(null);
    const [note, setNote] = React.useState("");
    const [preview, setPreview] = React.useState(null);
    const [busy, setBusy] = React.useState(false);
    const [posting, setPosting] = React.useState(false);
    const [error, setError] = React.useState("");
    const fieldStyle = { height: 38, border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: "0 10px", fontFamily: "inherit", width: "100%" };
    const labelStyle = { display: "grid", gap: 6, fontSize: 12, fontWeight: 700, color: "var(--text-2)" };
    const headerText = (options?.required_headers || []).join(",");
    const invalidRows = Number(preview?.invalid_rows || 0);
    const validRows = Number(preview?.valid_rows || 0);
    const canPost = preview?.id && preview?.status === "preview" && invalidRows === 0 && validRows > 0;

    function chooseFile(ev) {
      const selected = ev.target.files?.[0] || null;
      setFile(selected);
      setPreview(null);
      setError("");
    }

    async function submitPreview(ev) {
      ev.preventDefault();
      setError("");

      if (options?.requires_company_selection && !companyId) {
        setError("Company is required for global import users.");
        return;
      }

      if (!file) {
        setError("Select a CSV file before preview.");
        return;
      }

      const fileName = String(file.name || "").toLowerCase();
      if (!fileName.endsWith(".csv") && !fileName.endsWith(".txt")) {
        setError("Only CSV or TXT import files are supported.");
        return;
      }

      const maxBytes = Number(options?.max_file_size_kb || 512) * 1024;
      if (file.size > maxBytes) {
        setError(`File must be ${options?.max_file_size_kb || 512} KB or smaller.`);
        return;
      }

      setBusy(true);
      try {
        const result = await onPreview({
          company_id: companyId,
          source_file: file,
          note: note.trim() || "Previewed from CRM Lead Management.",
        });
        setPreview(result);
      } catch (apiError) {
        setError(apiError.message || "Import preview failed.");
      } finally {
        setBusy(false);
      }
    }

    async function postBatch() {
      setError("");

      if (!canPost) {
        setError("Only valid preview batches can be posted.");
        return;
      }

      setPosting(true);
      try {
        const result = await onPost(preview, note.trim() || "Posted from CRM Lead Management.");
        setPreview(result);
      } catch (apiError) {
        setError(apiError.message || "Import posting failed.");
      } finally {
        setPosting(false);
      }
    }

    function downloadSample() {
      const blob = new Blob([options?.sample_csv || headerText], { type: "text/csv;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = "builder360-prospect-inquiry-import-sample.csv";
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    }

    return e("div", { className: "scrim", onClick: onClose },
      e("form", { className: "modal", style: { width: 820, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submitPreview },
        e("div", { className: "card-head" },
          e("div", null,
            e("div", { className: "card-title" }, "Import Prospect Inquiries"),
            e("div", { className: "cell-sub" }, "CSV preview, row validation, reconciliation and audited posting through Settings data imports.")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose, disabled: busy || posting }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Company",
              e("select", { style: fieldStyle, value: companyId, onChange: ev => setCompanyId(ev.target.value), disabled: busy || posting || companies.length === 0 },
                options?.requires_company_selection && e("option", { value: "" }, "Select company"),
                companies.map(company => e("option", { key: company.id, value: company.id }, `${company.code} - ${company.name}`)))),
            e("label", { style: labelStyle }, "CSV File",
              e("input", { style: { ...fieldStyle, paddingTop: 8 }, type: "file", accept: ".csv,.txt,text/csv,text/plain", onChange: chooseFile, disabled: busy || posting }))),
          e("label", { style: { ...labelStyle, marginBottom: 12 } }, "Note",
            e("input", { style: fieldStyle, value: note, onChange: ev => setNote(ev.target.value), disabled: busy || posting, placeholder: "Optional import note for workflow history" })),
          e("div", { className: "card", style: { padding: 12, marginBottom: 12, background: "var(--surface-2)" } },
            e("div", { className: "row between", style: { alignItems: "flex-start", gap: 12 } },
              e("div", null,
                e("div", { style: { fontWeight: 800, fontSize: 12.5, marginBottom: 5 } }, "Required CSV header"),
                e("div", { className: "mono", style: { fontSize: 11.5, color: "var(--text-2)", overflowWrap: "anywhere" } }, headerText)),
              e("button", { type: "button", className: "btn btn-sm", onClick: downloadSample, disabled: busy || posting }, "Download Sample"))),
          error && e("div", { style: { color: "var(--red)", fontSize: 12, fontWeight: 700, marginBottom: 12 } }, error),
          preview && e("div", { className: "grid g-4", style: { marginBottom: 12 } },
            e(Stat, { label: "Total Rows", value: String(preview.total_rows || 0), icon: "list", tone: "accent" }),
            e(Stat, { label: "Valid Rows", value: String(preview.valid_rows || 0), icon: "check", tone: "green" }),
            e(Stat, { label: "Invalid Rows", value: String(preview.invalid_rows || 0), icon: "alert", tone: invalidRows > 0 ? "red" : "green" }),
            e(Stat, { label: "Status", value: preview.status || "-", icon: "shield", tone: preview.status === "posted" ? "green" : "orange" })),
          preview && e("div", { className: "card", style: { padding: 12, marginBottom: 12 } },
            e("div", { className: "row between", style: { marginBottom: 10 } },
              e("div", { className: "card-title" }, `Preview ${preview.import_number || ""}`),
              e(Badge, { tone: invalidRows > 0 ? "b-red" : "b-green", dot: true }, invalidRows > 0 ? "Errors found" : "Ready to post")),
            e("div", { className: "tbl-wrap", style: { maxHeight: 230, overflow: "auto" } },
              e("table", { className: "tbl" },
                e("thead", null, e("tr", null, ["Row", "Name", "Project", "Email", "Phone", "Status", "Messages"].map(h => e("th", { key: h }, h)))),
                e("tbody", null, (preview.preview_rows || []).slice(0, 25).map(row =>
                  e("tr", { key: row.row_number },
                    e("td", { className: "mono" }, row.row_number),
                    e("td", null, row.name || "-"),
                    e("td", null, row.project_code || "-"),
                    e("td", null, row.email || "-"),
                    e("td", null, row.phone || "-"),
                    e("td", null, e(Badge, { tone: row.status === "valid" ? "b-green" : "b-red" }, row.status)),
                    e("td", { style: { fontSize: 12, color: "var(--text-2)" } },
                      [...Object.values(row.errors || {}), ...(row.warnings || [])].join(" | ") || "-"))))))),
          preview?.error_report?.length > 0 && e("div", { className: "card", style: { padding: 12, marginBottom: 12, borderColor: "rgba(220,38,38,.35)" } },
            e("div", { className: "card-title", style: { marginBottom: 8 } }, "Row Error Report"),
            preview.error_report.slice(0, 10).map((item, index) =>
              e("div", { key: index, style: { fontSize: 12, color: "var(--red)", marginBottom: 4 } }, `Row ${item.row_number} - ${item.field}: ${item.message}`))),
          e("div", { className: "row gap-2", style: { justifyContent: "flex-end" } },
            e("button", { type: "button", className: "btn", onClick: onClose, disabled: busy || posting }, "Close"),
            e("button", { type: "submit", className: "btn", disabled: busy || posting }, busy ? "Previewing..." : "Preview Import"),
            e("button", { type: "button", className: "btn btn-primary", disabled: !canPost || busy || posting, onClick: postBatch }, posting ? "Posting..." : "Post Valid Batch")))));
  }

  function Kanban({ leads, columns }) {
    const cols = Array.isArray(columns) ? columns : [];
    if (!cols.length) {
      return e(Empty, { title: "Lead Management API required", sub: "Kanban columns come from Laravel CRM lead metrics; no local prototype columns are fabricated." });
    }
    return e("div", { style: { display: "grid", gridTemplateColumns: "repeat(6,minmax(200px,1fr))", gap: 12, overflowX: "auto" } },
      cols.map(c => e("div", { key: c.label, style: { background: "var(--surface-2)", borderRadius: 12, padding: 10, border: "1px solid var(--border)" } },
        e("div", { className: "row between", style: { padding: "4px 6px 10px" } }, e(Badge, { tone: c.badge, dot: true }, c.label), e("span", { className: "faint mono", style: { fontWeight: 800 } }, formatCount(c.count))),
        leads.filter(l => leadMatchesKanbanColumn(l, c)).slice(0, 3).map(l =>
          e("div", { key: l.id, className: "card", style: { padding: 11, marginBottom: 8, cursor: "grab" } },
            e("div", { className: "row gap-2", style: { marginBottom: 7 } }, e(Avatar, { name: l.name, sm: true, size: 26 }), e("div", null, e("div", { style: { fontWeight: 700, fontSize: 12.5 } }, l.name), e("div", { className: "cell-sub" }, l.config + " · " + l.budget))),
            e("div", { className: "row between" }, e("span", { className: "tag", style: { height: 22, fontSize: 11 } }, l.source), e("span", { className: "badge " + scoreTone(l.score) }, l.score)))))));
  }

  function LeadDrawer({ lead, onClose, toast, onDisposition, onLogActivity, onScheduleVisit, siteVisitOptions, onConvertBooking, bookingOptions }) {
    const [outcome, setOutcome] = React.useState("lost");
    const [reason, setReason] = React.useState("");
    const [followUpAt, setFollowUpAt] = React.useState("");
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [logOpen, setLogOpen] = React.useState(false);
    const [visitOpen, setVisitOpen] = React.useState(false);
    const [convertOpen, setConvertOpen] = React.useState(false);
    const canDisposition = lead.can_disposition && !["won", "lost"].includes(lead.system_status || "");
    const canLogActivity = lead.can_log_activity && !!lead.activity_create_url && !!lead.record_id;
    const canScheduleVisit = lead.can_schedule_site_visit && !!(lead.site_visit_store_url || siteVisitOptions?.store_url) && !!lead.record_id;
    const canConvertBooking = lead.can_convert_booking && !!(lead.booking_store_url || bookingOptions?.store_url) && !!lead.customer_id && !!lead.record_id;
    async function submit(ev) {
      ev.preventDefault();
      setError("");

      if (!reason.trim()) {
        setError("Reason is required.");
        return;
      }

      if (outcome === "deferred" && !followUpAt) {
        setError("Follow-up date and time is required for deferred leads.");
        return;
      }

      setBusy(true);
      try {
        await onDisposition(lead, {
          outcome,
          reason: reason.trim(),
          follow_up_at: outcome === "deferred" ? followUpAt : null,
        });
        setReason("");
        setFollowUpAt("");
      } catch (apiError) {
        setError(apiError.message || "The lead disposition could not be saved.");
      } finally {
        setBusy(false);
      }
    }
    const tl = Array.isArray(lead.activities) ? lead.activities : [];
    return e("div", { className: "scrim", onClick: onClose },
      e("div", { className: "drawer", onClick: ev => ev.stopPropagation() },
        e("div", { className: "card-head" }, e("div", { className: "cell-user" }, e(Avatar, { name: lead.name, size: 42 }),
          e("div", null, e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 16 } }, lead.name), e("div", { className: "cell-sub mono" }, lead.phone))),
          e("button", { className: "icon-btn", onClick: onClose }, e(Icon, { name: "x", size: 16 }))),
        e("div", { style: { padding: "14px 18px", display: "flex", gap: 8, flexWrap: "wrap", borderBottom: "1px solid var(--border)" } },
          e(Badge, { tone: lead.badge, dot: true }, lead.status), e("span", { className: "badge " + scoreTone(lead.score) }, "Score " + lead.score), e("span", { className: "tag" }, lead.source)),
        e("div", { style: { flex: 1, overflowY: "auto", padding: 18 } },
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 20 } },
            [["Interest", lead.config], ["Budget", lead.budget], ["Project", lead.project], ["Owner", lead.exec]].map((r, i) =>
              e("div", { key: i, className: "card", style: { padding: "11px 13px" } }, e("div", { className: "kpi-mini", style: { marginBottom: 3 } }, r[0]), e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, r[1])))),
          e("div", { className: "row gap-2", style: { marginBottom: 18 } },
            e(Button, { variant: "primary", icon: "headset", sm: true, disabled: !canLogActivity, onClick: () => canLogActivity ? setLogOpen(true) : toast("Lead activity logging is available only for CRM manager roles.", "orange"), children: "Log Call" }), e(Button, { icon: "calendar", sm: true, disabled: !canScheduleVisit, onClick: () => canScheduleVisit ? setVisitOpen(true) : toast("Site visit scheduling is available only for CRM manager roles.", "orange"), children: "Schedule Visit" }), e(Button, { icon: "tag", sm: true, disabled: !canConvertBooking, onClick: () => canConvertBooking ? setConvertOpen(true) : toast("Lead conversion requires booking permission, customer, and available inventory.", "orange"), children: "Convert" })),
          e("form", { className: "card", style: { padding: 14, marginBottom: 18 }, onSubmit: submit },
            e("div", { className: "row between", style: { gap: 10, marginBottom: 12 } },
              e("div", null,
                e("div", { className: "card-title" }, "Lead Disposition"),
                e("div", { className: "cell-sub" }, lead.disposition?.outcome ? `Current: ${lead.disposition.outcome} · ${lead.disposition.reason || "reason not recorded"}` : "Close or defer the lead with an auditable reason.")),
              e(Badge, { tone: canDisposition ? "b-green" : "b-slate" }, canDisposition ? "Actionable" : "Read-only")),
            e("div", { className: "grid g-2", style: { gap: 10, marginBottom: 10 } },
              e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 700, color: "var(--text-2)" } },
                "Outcome",
                e("select", { value: outcome, disabled: !canDisposition || busy, onChange: ev => setOutcome(ev.target.value), style: { height: 38, border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: "0 10px", fontFamily: "inherit" } },
                  [["lost", "Lost"], ["deferred", "Deferred"], ["not_interested", "Not interested"], ["duplicate", "Duplicate"], ["invalid", "Invalid"]].map(opt => e("option", { key: opt[0], value: opt[0] }, opt[1])))),
              e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 700, color: "var(--text-2)" } },
                "Follow-up",
                e("input", { type: "datetime-local", value: followUpAt, disabled: !canDisposition || busy || outcome !== "deferred", onChange: ev => setFollowUpAt(ev.target.value), style: { height: 38, border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: "0 10px", fontFamily: "inherit" } }))),
            e("label", { style: { display: "grid", gap: 6, fontSize: 12, fontWeight: 700, color: "var(--text-2)", marginBottom: 10 } },
              "Reason",
              e("textarea", { value: reason, disabled: !canDisposition || busy, onChange: ev => setReason(ev.target.value), placeholder: "Enter disposition reason for audit trail", rows: 3, style: { border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: 10, resize: "vertical", fontFamily: "inherit" } })),
            error && e("div", { style: { color: "var(--red)", fontSize: 12, fontWeight: 700, marginBottom: 10 } }, error),
            e("div", { className: "row gap-2" },
              e(Button, { variant: "primary", icon: "check", sm: true, disabled: !canDisposition || busy, children: busy ? "Saving..." : "Save Disposition" }))),
          e("div", { className: "card-title", style: { marginBottom: 14 } }, "Activity Timeline"),
          tl.length === 0 ? e("div", { className: "card", style: { padding: 14, color: "var(--text-3)", fontSize: 13, fontWeight: 600 } }, "No server activity history is available for this lead yet.") :
          e("div", { style: { position: "relative", paddingLeft: 6 } }, tl.map((t, i) =>
            e("div", { key: i, style: { display: "flex", gap: 12, paddingBottom: 18, position: "relative" } },
              i < tl.length - 1 && e("div", { style: { position: "absolute", left: 14, top: 28, bottom: 0, width: 2, background: "var(--border)" } }),
              e("div", { style: { width: 30, height: 30, borderRadius: 8, background: "var(--surface-3)", color: t.c, display: "grid", placeItems: "center", flex: "0 0 30px", zIndex: 1 } }, e(Icon, { name: t.ic, size: 15 })),
              e("div", null, e("div", { style: { fontSize: 13, fontWeight: 600 } }, t.act), e("div", { className: "cell-sub" }, t.who + " · " + t.t)))))),
      ),
      logOpen && e(LogCallModal, { lead, onClose: () => setLogOpen(false), onSubmit: onLogActivity }),
      visitOpen && e(ScheduleVisitModal, { lead, options: siteVisitOptions, onClose: () => setVisitOpen(false), onSubmit: onScheduleVisit }),
      convertOpen && e(ConvertBookingModal, { lead, options: bookingOptions, onClose: () => setConvertOpen(false), onSubmit: onConvertBooking }));
  }

  function LogCallModal({ lead, onClose, onSubmit }) {
    const [form, setForm] = React.useState({
      subject: "Call logged with " + lead.name,
      outcome: "connected",
      description: "",
      next_follow_up_at: "",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const fieldStyle = { height: 38, border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: "0 10px", fontFamily: "inherit", width: "100%" };
    const labelStyle = { display: "grid", gap: 6, fontSize: 12, fontWeight: 700, color: "var(--text-2)" };

    function update(key, value) {
      setForm(current => ({ ...current, [key]: value }));
    }

    async function submit(ev) {
      ev.preventDefault();
      setError("");

      if (!form.subject.trim() || !form.description.trim()) {
        setError("Subject and call notes are required.");
        return;
      }

      setBusy(true);
      try {
        await onSubmit(lead, {
          subject: form.subject.trim(),
          description: form.description.trim(),
          outcome: form.outcome || null,
          next_follow_up_at: form.next_follow_up_at || null,
        });
        onClose();
      } catch (apiError) {
        setError(apiError.message || "Call activity could not be saved.");
      } finally {
        setBusy(false);
      }
    }

    return e("div", { className: "scrim", onClick: ev => { ev.stopPropagation(); onClose(); } },
      e("form", { className: "modal", style: { width: 560, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "card-head" },
          e("div", null, e("div", { className: "card-title" }, "Log Call"), e("div", { className: "cell-sub" }, lead.name + " Â· " + lead.phone)),
          e("button", { type: "button", className: "icon-btn", onClick: onClose, disabled: busy }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Subject", e("input", { style: fieldStyle, value: form.subject, onChange: ev => update("subject", ev.target.value), disabled: busy, required: true })),
            e("label", { style: labelStyle }, "Outcome", e("select", { style: fieldStyle, value: form.outcome, onChange: ev => update("outcome", ev.target.value), disabled: busy },
              [["connected", "Connected"], ["not_connected", "Not connected"], ["interested", "Interested"], ["not_interested", "Not interested"], ["follow_up", "Follow-up required"]].map(opt => e("option", { key: opt[0], value: opt[0] }, opt[1]))))),
          e("label", { style: { ...labelStyle, marginBottom: 12 } }, "Call Notes", e("textarea", { value: form.description, onChange: ev => update("description", ev.target.value), disabled: busy, required: true, rows: 4, placeholder: "Record discussion summary, objections, and promised follow-up.", style: { border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: 10, resize: "vertical", fontFamily: "inherit" } })),
          e("label", { style: { ...labelStyle, marginBottom: 12 } }, "Next Follow-up", e("input", { type: "datetime-local", style: fieldStyle, value: form.next_follow_up_at, onChange: ev => update("next_follow_up_at", ev.target.value), disabled: busy })),
          error && e("div", { style: { color: "var(--red)", fontSize: 12, fontWeight: 700, marginBottom: 12 } }, error),
          e("div", { className: "row gap-2", style: { justifyContent: "flex-end" } },
            e("button", { type: "button", className: "btn", onClick: onClose, disabled: busy }, "Cancel"),
            e("button", { type: "submit", className: "btn btn-primary", disabled: busy }, busy ? "Saving..." : "Save Call")))));
  }

  function ScheduleVisitModal({ lead, options, onClose, onSubmit }) {
    const assignees = options?.assignees || [];
    const visitModes = options?.visit_modes || [
      { value: "site", label: "Site visit" },
      { value: "office", label: "Office meeting" },
      { value: "virtual", label: "Virtual meeting" },
    ];
    const defaultDate = React.useMemo(() => {
      const date = new Date();
      date.setDate(date.getDate() + 1);
      date.setHours(11, 0, 0, 0);
      return dateTimeLocalValue(date);
    }, []);
    const [form, setForm] = React.useState({
      assigned_to_user_id: "",
      scheduled_at: defaultDate,
      duration_minutes: String(options?.default_duration_minutes || 60),
      visit_mode: "site",
      meeting_location: "",
      meeting_url: "",
      agenda: "",
      attendee_name: lead.name,
      attendee_phone: lead.phone && lead.phone !== "Phone pending" ? lead.phone : "",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const fieldStyle = { height: 38, border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: "0 10px", fontFamily: "inherit", width: "100%" };
    const labelStyle = { display: "grid", gap: 6, fontSize: 12, fontWeight: 700, color: "var(--text-2)" };

    function update(key, value) {
      setForm(current => ({ ...current, [key]: value }));
    }

    async function submit(ev) {
      ev.preventDefault();
      setError("");

      const scheduledDate = form.scheduled_at ? new Date(form.scheduled_at) : null;
      const duration = Number(form.duration_minutes || 0);

      if (!scheduledDate || Number.isNaN(scheduledDate.getTime()) || scheduledDate <= new Date()) {
        setError("Scheduled date and time must be in the future.");
        return;
      }

      if (!duration || duration < 15 || duration > 480) {
        setError("Duration must be between 15 and 480 minutes.");
        return;
      }

      if (form.visit_mode === "virtual" && !form.meeting_url.trim()) {
        setError("Meeting link is required for virtual visits.");
        return;
      }

      if (form.visit_mode !== "virtual" && !form.meeting_location.trim()) {
        setError("Meeting location is required for site or office visits.");
        return;
      }

      setBusy(true);
      try {
        const attendees = form.attendee_name.trim()
          ? [{ name: form.attendee_name.trim(), phone: form.attendee_phone.trim() || null, role: "Buyer" }]
          : [];

        await onSubmit(lead, {
          assigned_to_user_id: form.assigned_to_user_id ? Number(form.assigned_to_user_id) : null,
          scheduled_at: scheduledDate.toISOString(),
          duration_minutes: duration,
          visit_mode: form.visit_mode,
          meeting_location: form.visit_mode === "virtual" ? null : form.meeting_location.trim(),
          meeting_url: form.visit_mode === "virtual" ? form.meeting_url.trim() : null,
          agenda: form.agenda.trim() || null,
          attendees,
        });
        onClose();
      } catch (apiError) {
        setError(apiError.message || "Site visit could not be scheduled.");
      } finally {
        setBusy(false);
      }
    }

    return e("div", { className: "scrim", onClick: ev => { ev.stopPropagation(); onClose(); } },
      e("form", { className: "modal", style: { width: 680, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "card-head" },
          e("div", null, e("div", { className: "card-title" }, "Schedule Site Visit"), e("div", { className: "cell-sub" }, lead.name + " Â· " + lead.project)),
          e("button", { type: "button", className: "icon-btn", onClick: onClose, disabled: busy }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Owner / Assignee", e("select", { style: fieldStyle, value: form.assigned_to_user_id, onChange: ev => update("assigned_to_user_id", ev.target.value), disabled: busy },
              e("option", { value: "" }, "Assign to me"),
              assignees.map(user => e("option", { key: user.id, value: user.id }, `${user.name} Â· ${user.role || user.email}`)))),
            e("label", { style: labelStyle }, "Scheduled Date/Time", e("input", { type: "datetime-local", style: fieldStyle, value: form.scheduled_at, onChange: ev => update("scheduled_at", ev.target.value), disabled: busy, required: true })),
            e("label", { style: labelStyle }, "Duration", e("input", { type: "number", min: "15", max: "480", step: "15", style: fieldStyle, value: form.duration_minutes, onChange: ev => update("duration_minutes", ev.target.value), disabled: busy, required: true })),
            e("label", { style: labelStyle }, "Mode", e("select", { style: fieldStyle, value: form.visit_mode, onChange: ev => update("visit_mode", ev.target.value), disabled: busy },
              visitModes.map(mode => e("option", { key: mode.value, value: mode.value }, mode.label))))),
          form.visit_mode === "virtual"
            ? e("label", { style: { ...labelStyle, marginBottom: 12 } }, "Meeting Link", e("input", { type: "url", style: fieldStyle, value: form.meeting_url, onChange: ev => update("meeting_url", ev.target.value), disabled: busy, placeholder: "https://meet.example.com/visit" }))
            : e("label", { style: { ...labelStyle, marginBottom: 12 } }, "Meeting Location", e("input", { style: fieldStyle, value: form.meeting_location, onChange: ev => update("meeting_location", ev.target.value), disabled: busy, placeholder: "Project site office / sales office" })),
          e("label", { style: { ...labelStyle, marginBottom: 12 } }, "Agenda", e("textarea", { value: form.agenda, onChange: ev => update("agenda", ev.target.value), disabled: busy, rows: 3, placeholder: "Purpose, inventory to show, documents to carry, and next action.", style: { border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: 10, resize: "vertical", fontFamily: "inherit" } })),
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Attendee Name", e("input", { style: fieldStyle, value: form.attendee_name, onChange: ev => update("attendee_name", ev.target.value), disabled: busy })),
            e("label", { style: labelStyle }, "Attendee Phone", e("input", { style: fieldStyle, value: form.attendee_phone, onChange: ev => update("attendee_phone", ev.target.value), disabled: busy }))),
          error && e("div", { style: { color: "var(--red)", fontSize: 12, fontWeight: 700, marginBottom: 12 } }, error),
          e("div", { className: "row gap-2", style: { justifyContent: "flex-end" } },
            e("button", { type: "button", className: "btn", onClick: onClose, disabled: busy }, "Cancel"),
            e("button", { type: "submit", className: "btn btn-primary", disabled: busy }, busy ? "Scheduling..." : "Schedule Visit")))));
  }

  function ConvertBookingModal({ lead, options, onClose, onSubmit }) {
    const units = (options?.units || []).filter(unit => !lead.project_id || String(unit.project_id) === String(lead.project_id));
    const initialUnitId = units[0]?.id ? String(units[0].id) : "";
    const [form, setForm] = React.useState({
      project_unit_id: initialUnitId,
      booking_amount: "",
      discount_amount: "0",
    });
    const [quote, setQuote] = React.useState(null);
    const [quoteBusy, setQuoteBusy] = React.useState(false);
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const fieldStyle = { height: 38, border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)", color: "var(--text)", padding: "0 10px", fontFamily: "inherit", width: "100%" };
    const labelStyle = { display: "grid", gap: 6, fontSize: 12, fontWeight: 700, color: "var(--text-2)" };
    const selectedUnit = units.find(unit => String(unit.id) === String(form.project_unit_id));

    React.useEffect(() => {
      let cancelled = false;

      async function loadQuote() {
        if (!options?.quote_url || !form.project_unit_id) {
          setQuote(null);
          return;
        }

        setQuoteBusy(true);
        try {
          const response = await fetch(options.quote_url, {
            method: "POST",
            headers: {
              "Accept": "application/json",
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": csrfToken(),
              "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
              project_unit_id: Number(form.project_unit_id),
              discount_amount: Number(form.discount_amount || 0),
            }),
          });
          const body = await response.json().catch(() => ({}));

          if (!response.ok) {
            throw new Error(firstApiError(body));
          }

          if (!cancelled) {
            setQuote(body.data || null);
            if (!form.booking_amount && body.data?.total_payable) {
              setForm(current => current.booking_amount ? current : { ...current, booking_amount: String(Math.round(Number(body.data.total_payable) * 0.1)) });
            }
          }
        } catch (apiError) {
          if (!cancelled) {
            setQuote(null);
            setError(apiError.message || "Quote could not be calculated.");
          }
        } finally {
          if (!cancelled) {
            setQuoteBusy(false);
          }
        }
      }

      loadQuote();

      return () => {
        cancelled = true;
      };
    }, [options?.quote_url, form.project_unit_id, form.discount_amount]);

    function update(key, value) {
      setError("");
      setForm(current => ({ ...current, [key]: value }));
    }

    async function submit(ev) {
      ev.preventDefault();
      setError("");

      if (!form.project_unit_id) {
        setError("Select an available unit.");
        return;
      }

      if (!form.booking_amount || Number(form.booking_amount) <= 0) {
        setError("Booking amount must be greater than zero.");
        return;
      }

      setBusy(true);
      try {
        await onSubmit(lead, {
          project_unit_id: Number(form.project_unit_id),
          booking_amount: Number(form.booking_amount),
          discount_amount: Number(form.discount_amount || 0),
          payment_schedule: options?.default_payment_schedule || null,
        });
        onClose();
      } catch (apiError) {
        setError(apiError.message || "Lead could not be converted to booking.");
      } finally {
        setBusy(false);
      }
    }

    return e("div", { className: "scrim", onClick: ev => { ev.stopPropagation(); onClose(); } },
      e("form", { className: "modal", style: { width: 720, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "card-head" },
          e("div", null, e("div", { className: "card-title" }, "Convert Lead to Booking"), e("div", { className: "cell-sub" }, lead.name + " Â· " + lead.project)),
          e("button", { type: "button", className: "icon-btn", onClick: onClose, disabled: busy }, e(Icon, { name: "x", size: 16 }))),
        e("div", { className: "card-pad" },
          units.length === 0 && e("div", { className: "card", style: { padding: 12, color: "var(--orange)", fontSize: 12, fontWeight: 700, marginBottom: 12 } }, "No bookable units are currently available for this lead project."),
          e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Available Unit", e("select", { style: fieldStyle, value: form.project_unit_id, onChange: ev => update("project_unit_id", ev.target.value), disabled: busy || units.length === 0 },
              e("option", { value: "" }, "Select unit"),
              units.map(unit => e("option", { key: unit.id, value: unit.id }, `${unit.unit_code} Â· ${unit.unit_type} Â· ${formatInr(unit.total_price)}`)))),
            e("label", { style: labelStyle }, "Booking Amount", e("input", { type: "number", min: "1", step: "1000", style: fieldStyle, value: form.booking_amount, onChange: ev => update("booking_amount", ev.target.value), disabled: busy, required: true })),
            e("label", { style: labelStyle }, "Discount Amount", e("input", { type: "number", min: "0", step: "1000", style: fieldStyle, value: form.discount_amount, onChange: ev => update("discount_amount", ev.target.value), disabled: busy })),
            e("label", { style: labelStyle }, "Lead Customer", e("input", { style: fieldStyle, value: lead.name, disabled: true }))),
          selectedUnit && e("div", { className: "card", style: { padding: 12, marginBottom: 12 } },
            e("div", { className: "row between", style: { gap: 12, marginBottom: 8 } },
              e("div", null, e("div", { className: "card-title" }, selectedUnit.unit_code), e("div", { className: "cell-sub" }, `${selectedUnit.project_name || lead.project} Â· ${selectedUnit.tower || "Tower"} / Floor ${selectedUnit.floor || "-"}`)),
              e(Badge, { tone: "b-green", dot: true }, selectedUnit.status)),
            quoteBusy ? e("div", { className: "cell-sub" }, "Calculating quote...") :
            quote ? e("div", { className: "grid g-4", style: { gap: 10 } },
              e(QField, { label: "Gross Before Tax", value: formatInr(quote.gross_price_before_tax) }),
              e(QField, { label: "Discount", value: formatInr(quote.discount_amount) }),
              e(QField, { label: "Tax", value: formatInr(quote.tax_amount) }),
              e(QField, { label: "Net Receivable", value: formatInr(quote.total_payable) })) :
            e("div", { className: "cell-sub" }, "Quote preview unavailable.")),
          quote?.requires_discount_approval && e("div", { className: "card", style: { padding: 12, color: "var(--orange)", fontSize: 12, fontWeight: 700, marginBottom: 12 } }, "Requested discount exceeds direct approval threshold. Backend policy will enforce approval authority."),
          error && e("div", { style: { color: "var(--red)", fontSize: 12, fontWeight: 700, marginBottom: 12 } }, error),
          e("div", { className: "row gap-2", style: { justifyContent: "flex-end" } },
            e("button", { type: "button", className: "btn", onClick: onClose, disabled: busy }, "Cancel"),
            e("button", { type: "submit", className: "btn btn-primary", disabled: busy || units.length === 0 }, busy ? "Converting..." : "Confirm Booking")))));
  }

  // ---------------- LEAD QUALIFICATION ----------------
  function cloneQualityRules(rules) {
    return JSON.parse(JSON.stringify({
      criteria: rules?.criteria || {},
      bands: rules?.bands || [],
    }));
  }
  function conditionValueFromLabel(label) {
    return String(label || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "")
      .slice(0, 60);
  }
  function QualityRuleDraftModal({ qualificationOptions, rules, onClose, toast }) {
    const initialRules = cloneQualityRules(rules);
    const initialCriterionKeys = Object.keys(initialRules.criteria || {});
    const [draftRules, setDraftRules] = React.useState(initialRules);
    const [criterionKey, setCriterionKey] = React.useState(initialCriterionKeys[0] || "budget");
    const [newCriterion, setNewCriterion] = React.useState({ label: "", key: "", max_points: "25" });
    const [condition, setCondition] = React.useState({ label: "", value: "", points: "" });
    const [newBand, setNewBand] = React.useState({ label: "", min_score: "", status_hint: "nurture", tone: "slate" });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const labelStyle = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const criteriaKeys = Object.keys(draftRules.criteria || {});
    const activeCriterion = draftRules.criteria?.[criterionKey] || { label: criterionKey, max_points: 25, options: [] };
    const conditionValue = condition.value || conditionValueFromLabel(condition.label);
    const canManage = !!(qualificationOptions?.can_manage_settings && qualificationOptions?.system_settings_store_url);
    const sortedBands = (draftRules.bands || []).slice().sort((a, b) => Number(b.min_score || 0) - Number(a.min_score || 0));
    const shouldSyncGeneratedValue = (currentValue, previousLabel) => !currentValue || currentValue === conditionValueFromLabel(previousLabel);

    const updateNewCriterionLabel = value => {
      setNewCriterion(current => ({
        ...current,
        label: value,
        key: shouldSyncGeneratedValue(current.key, current.label) ? conditionValueFromLabel(value) : current.key,
      }));
    };

    const updateConditionLabel = value => {
      setCondition(current => ({
        ...current,
        label: value,
        value: shouldSyncGeneratedValue(current.value, current.label) ? conditionValueFromLabel(value) : current.value,
      }));
    };

    const addCriterion = () => {
      setError("");
      const cleanLabel = newCriterion.label.trim();
      const cleanKey = conditionValueFromLabel(newCriterion.key || newCriterion.label);
      const maxPoints = Number(newCriterion.max_points);
      if (!cleanLabel) return setError("Criterion label is required.");
      if (!/^[a-z][a-z0-9_]{1,39}$/.test(cleanKey)) return setError("Criterion key must start with a lowercase letter and use lowercase letters, numbers or underscores.");
      if (draftRules.criteria?.[cleanKey]) return setError("This criterion key already exists in the draft.");
      if (criteriaKeys.length >= 8) return setError("A lead quality score rule can include a maximum of 8 criteria.");
      if (!Number.isInteger(maxPoints) || maxPoints < 1 || maxPoints > 100) return setError("Criterion max points must be a whole number between 1 and 100.");
      setDraftRules(current => {
        const next = cloneQualityRules(current);
        next.criteria[cleanKey] = { label: cleanLabel, max_points: maxPoints, options: [] };
        return next;
      });
      setCriterionKey(cleanKey);
      setNewCriterion({ label: "", key: "", max_points: "25" });
    };

    const removeCriterion = key => {
      setError("");
      if (criteriaKeys.length <= 1) return setError("At least one scoring criterion is required.");
      setDraftRules(current => {
        const next = cloneQualityRules(current);
        delete next.criteria[key];
        return next;
      });
      if (criterionKey === key) {
        const nextKey = criteriaKeys.find(item => item !== key) || "";
        setCriterionKey(nextKey);
      }
    };

    const addCondition = () => {
      setError("");
      const cleanValue = conditionValueFromLabel(conditionValue);
      const cleanLabel = condition.label.trim();
      const points = Number(condition.points);
      if (!cleanLabel) return setError("Condition label is required.");
      if (!cleanValue) return setError("Condition value is required.");
      if (condition.points === "") return setError("Condition points are required.");
      if (!Number.isFinite(points) || points < 0 || points > Number(activeCriterion.max_points || 0)) return setError("Condition points must be between 0 and the criterion max points.");
      if ((activeCriterion.options || []).some(option => option.value === cleanValue)) return setError("This condition value already exists for " + activeCriterion.label + ".");
      setDraftRules(current => {
        const next = cloneQualityRules(current);
        next.criteria[criterionKey].options = [...(next.criteria[criterionKey].options || []), { value: cleanValue, label: cleanLabel, points }];
        return next;
      });
      setCondition({ label: "", value: "", points: "" });
    };

    const addScoreBand = () => {
      setError("");
      const label = newBand.label.trim();
      const minScore = Number(newBand.min_score);
      if (!label) return setError("Score band label is required.");
      if (!Number.isInteger(minScore) || minScore < 0 || minScore > 100) return setError("Score band minimum must be a whole number between 0 and 100.");
      if (!["qualified", "nurture", "disqualified"].includes(newBand.status_hint)) return setError("Score band status hint is invalid.");
      if (!["green", "orange", "red", "slate", "blue"].includes(newBand.tone)) return setError("Score band tone is invalid.");
      if ((draftRules.bands || []).some(band => Number(band.min_score) === minScore)) return setError("A score band already exists for this minimum score.");
      setDraftRules(current => {
        const next = cloneQualityRules(current);
        next.bands = [...(next.bands || []), { label, min_score: minScore, status_hint: newBand.status_hint, tone: newBand.tone }]
          .sort((a, b) => Number(b.min_score || 0) - Number(a.min_score || 0));
        return next;
      });
      setNewBand({ label: "", min_score: "", status_hint: "nurture", tone: "slate" });
    };

    const removeScoreBand = minScore => {
      setError("");
      if ((draftRules.bands || []).length <= 1) return setError("At least one score band is required.");
      setDraftRules(current => {
        const next = cloneQualityRules(current);
        next.bands = (next.bands || []).filter(band => Number(band.min_score) !== Number(minScore));
        return next;
      });
    };

    const removeCondition = (key, value) => {
      setDraftRules(current => {
        const next = cloneQualityRules(current);
        next.criteria[key].options = (next.criteria[key].options || []).filter(option => option.value !== value);
        return next;
      });
    };

    const saveDraft = async ev => {
      ev.preventDefault();
      setError("");
      if (!canManage) return setError("settings.manage permission is required to create a scoring-rule draft.");
      if (!criteriaKeys.length) return setError("At least one scoring criterion is required.");
      if (!criteriaKeys.every(key => (draftRules.criteria?.[key]?.options || []).length > 0)) return setError("Every scoring criterion must include at least one selectable condition before creating a draft.");
      if (!sortedBands.length) return setError("At least one score band is required before creating a draft.");
      if (!sortedBands.some(band => Number(band.min_score) === 0)) return setError("At least one score band must start at 0 so every lead score has governed routing.");
      try {
        setBusy(true);
        const body = await apiJson(qualificationOptions.system_settings_store_url, {
          method: "POST",
          body: JSON.stringify({
            setting_group: "crm",
            setting_key: rules.setting_key || "crm.lead_quality_score.rules",
            label: "CRM Lead Quality Score Rules",
            description: "Configured lead qualification condition options and score bands. Draft requires approval before activation.",
            value_type: "object",
            value: draftRules,
            metadata: {
              source: "lead_quality_rule_builder",
              previous_source: rules.source || "application_default",
              previous_version: rules.version || null,
            },
          }),
        });
        toast && toast("Lead quality scoring rule draft v" + body.data.version + " created. Approve it from Administration → System Settings before it becomes active.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Scoring rule draft could not be created.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { onClick: onClose, style: { position: "fixed", inset: 0, zIndex: 1200, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: saveDraft, style: { width: "min(760px,96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 12, gap: 12 } },
          e("div", null,
            e("h2", { style: { margin: 0, fontSize: 18 } }, "Lead quality score condition builder"),
            e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates a governed System Settings draft. Active scoring changes require separate approval.")),
          e("button", { type: "button", className: "icon-btn", onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        !canManage && e("div", { style: { background: "var(--orange-soft)", color: "var(--orange)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 12, fontWeight: 800 } }, "Read-only preview. Ask an administrator with settings.manage permission to create condition-rule drafts."),
        e("div", { className: "card", style: { padding: 12, marginBottom: 14 } },
          e("div", { className: "row between", style: { marginBottom: 10, gap: 12 } },
            e("div", null, e("div", { className: "cell-strong" }, "Add scoring criterion"), e("div", { className: "cell-sub" }, "Optional: add a new configurable scoring dimension to this draft.")),
            e(Badge, { tone: "b-slate" }, criteriaKeys.length + " / 8 criteria")),
          e("div", { className: "grid g-3", style: { gap: 12 } },
            e("label", { style: labelStyle }, "Criterion label", e("input", { style: field, value: newCriterion.label, disabled: busy || !canManage, onChange: ev => updateNewCriterionLabel(ev.target.value), placeholder: "Example: Site visit readiness" })),
            e("label", { style: labelStyle }, "Criterion key", e("input", { style: field, value: newCriterion.key || conditionValueFromLabel(newCriterion.label), disabled: busy || !canManage, onChange: ev => setNewCriterion(current => ({ ...current, key: conditionValueFromLabel(ev.target.value) })), placeholder: "site_visit_readiness" })),
            e("label", { style: labelStyle }, "Max points", e("input", { type: "number", min: "1", max: "100", step: "1", style: field, value: newCriterion.max_points, disabled: busy || !canManage, onChange: ev => setNewCriterion(current => ({ ...current, max_points: ev.target.value })) }))),
          e("div", { className: "row gap-2", style: { marginTop: 10 } },
            e("button", { type: "button", className: "btn", disabled: busy || !canManage || criteriaKeys.length >= 8, onClick: addCriterion }, e(Icon, { name: "plus", size: 14 }), "Add criterion to draft"))),
        e("div", { className: "grid g-3", style: { gap: 12, marginBottom: 14 } },
          e("label", { style: labelStyle }, "Criterion",
            e("select", { style: field, value: criterionKey, onChange: ev => setCriterionKey(ev.target.value), disabled: busy },
              criteriaKeys.map(key => e("option", { key, value: key }, (draftRules.criteria?.[key]?.label || key) + " · max " + (draftRules.criteria?.[key]?.max_points || 0))))),
          e("label", { style: labelStyle }, "New condition label", e("input", { style: field, value: condition.label, disabled: busy || !canManage, onChange: ev => updateConditionLabel(ev.target.value), placeholder: "Example: Token paid / finance approved" })),
          e("label", { style: labelStyle }, "Condition value", e("input", { style: field, value: condition.value || conditionValueFromLabel(condition.label), disabled: busy || !canManage, onChange: ev => setCondition(current => ({ ...current, value: conditionValueFromLabel(ev.target.value) })), placeholder: "token_paid" })),
          e("label", { style: labelStyle }, "Points", e("input", { type: "number", min: "0", max: activeCriterion.max_points || 100, style: field, value: condition.points, disabled: busy || !canManage, onChange: ev => setCondition(current => ({ ...current, points: ev.target.value })) }))),
        e("div", { className: "row gap-2", style: { marginBottom: 16 } },
          e("button", { type: "button", className: "btn", disabled: busy || !canManage, onClick: addCondition }, e(Icon, { name: "plus", size: 14 }), "Add condition to draft"),
          e(Badge, { tone: "b-accent", dot: true }, "Rule key " + (rules.setting_key || "crm.lead_quality_score.rules"))),
        e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 16 } },
          criteriaKeys.map(key => {
            const criterion = draftRules.criteria?.[key] || {};
            return e("div", { key, className: "card", style: { padding: 12 } },
              e("div", { className: "row between", style: { marginBottom: 8 } },
                e("div", { className: "cell-strong" }, criterion.label || key),
                e("div", { className: "row gap-2" },
                  e("span", { className: "cell-sub" }, "Max " + (criterion.max_points || 0) + " pts"),
                  e("button", { type: "button", className: "icon-btn", disabled: busy || !canManage || criteriaKeys.length <= 1, onClick: () => removeCriterion(key), title: "Remove criterion from draft" }, e(Icon, { name: "trash", size: 13 })))),
              (criterion.options || []).map(option => e("div", { key: option.value, className: "row between", style: { gap: 8, borderTop: "1px solid var(--border)", padding: "8px 0" } },
                e("div", null, e("div", { className: "cell-strong", style: { fontSize: 12 } }, option.label), e("div", { className: "cell-sub" }, option.value)),
                e("div", { className: "row gap-2" }, e("span", { className: "badge b-slate" }, option.points + " pts"), e("button", { type: "button", className: "icon-btn", disabled: busy || !canManage, onClick: () => removeCondition(key, option.value), title: "Remove from draft" }, e(Icon, { name: "trash", size: 13 }))))));
          })),
        e("div", { className: "card", style: { padding: 12, marginBottom: 16 } },
          e("div", { className: "row between", style: { marginBottom: 10, gap: 12 } },
            e("div", null, e("div", { className: "cell-strong" }, "Score bands and routing hints"), e("div", { className: "cell-sub" }, "Bands classify the calculated percentage and suggest lead routing.")),
            e(Badge, { tone: "b-blue" }, sortedBands.length + " bands")),
          e("div", { className: "grid g-4", style: { gap: 12, marginBottom: 12 } },
            e("label", { style: labelStyle }, "Band label", e("input", { style: field, value: newBand.label, disabled: busy || !canManage, onChange: ev => setNewBand(current => ({ ...current, label: ev.target.value })), placeholder: "Priority Lead" })),
            e("label", { style: labelStyle }, "Minimum score", e("input", { type: "number", min: "0", max: "100", step: "1", style: field, value: newBand.min_score, disabled: busy || !canManage, onChange: ev => setNewBand(current => ({ ...current, min_score: ev.target.value })) })),
            e("label", { style: labelStyle }, "Status hint", e("select", { style: field, value: newBand.status_hint, disabled: busy || !canManage, onChange: ev => setNewBand(current => ({ ...current, status_hint: ev.target.value })) },
              [["qualified", "Qualified"], ["nurture", "Nurture"], ["disqualified", "Disqualified"]].map(option => e("option", { key: option[0], value: option[0] }, option[1])))),
            e("label", { style: labelStyle }, "Tone", e("select", { style: field, value: newBand.tone, disabled: busy || !canManage, onChange: ev => setNewBand(current => ({ ...current, tone: ev.target.value })) },
              ["green", "orange", "red", "slate", "blue"].map(tone => e("option", { key: tone, value: tone }, tone))))),
          e("div", { className: "row gap-2", style: { marginBottom: 10 } },
            e("button", { type: "button", className: "btn", disabled: busy || !canManage, onClick: addScoreBand }, e(Icon, { name: "plus", size: 14 }), "Add score band to draft")),
          sortedBands.map(band => e("div", { key: band.label + "-" + band.min_score, className: "row between", style: { gap: 8, borderTop: "1px solid var(--border)", padding: "8px 0" } },
            e("div", null, e("div", { className: "cell-strong", style: { fontSize: 12 } }, band.label), e("div", { className: "cell-sub" }, "Score >= " + band.min_score + " · status hint " + band.status_hint + " · tone " + band.tone)),
            e("button", { type: "button", className: "icon-btn", disabled: busy || !canManage || sortedBands.length <= 1, onClick: () => removeScoreBand(band.min_score), title: "Remove score band from draft" }, e(Icon, { name: "trash", size: 13 }))))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12 } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Draft only. It will not affect qualification scoring until approved."),
          e("div", { className: "row gap-2" },
            e("button", { type: "button", className: "btn", onClick: onClose, disabled: busy }, "Cancel"),
            e("button", { type: "submit", className: "btn btn-primary", disabled: busy || !canManage }, busy ? "Creating draft..." : "Create scoring-rule draft")))));
  }

  function Qualification({ toast }) {
    const qualificationOptions = window.Builder360Server?.crm_qualification_options || null;
    const siteVisitOptions = window.Builder360Server?.crm_site_visit_options || null;
    const serverLeads = Array.isArray(window.Builder360Server?.crm_leads) ? window.Builder360Server.crm_leads : [];
    const initialQueue = serverLeads.filter(l => !["won", "lost"].includes(l.system_status || "")).slice(0, 50);
    const [queue, setQueue] = React.useState(initialQueue);
    const [activeId, setActiveId] = React.useState(initialQueue[0]?.id || "");
    const [conditions, setConditions] = React.useState({});
    const [form, setForm] = React.useState({ status: "qualified", preferred_configuration: "", verified_budget_min: "", verified_budget_max: "", expected_booking_date: "", decision_notes: "" });
    const [busy, setBusy] = React.useState(false);
    const [ruleModal, setRuleModal] = React.useState(false);
    const [visitOpen, setVisitOpen] = React.useState(false);
    const [error, setError] = React.useState("");
    const [lastQualification, setLastQualification] = React.useState(null);
    const active = queue.find(l => l.id === activeId) || queue[0] || null;
    const rules = qualificationOptions?.rules || {};
    const criteria = Object.entries(rules.criteria || {});
    const preview = qualityScorePreview(rules, conditions);
    const routedStatus = ["qualified", "nurture", "disqualified"].includes(preview.band?.status_hint) ? preview.band.status_hint : form.status;
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    React.useEffect(() => {
      if (routedStatus && form.status !== routedStatus) {
        setForm(current => current.status === routedStatus ? current : ({ ...current, status: routedStatus }));
      }
    }, [routedStatus, form.status]);
    const chooseLead = (lead) => {
      setActiveId(lead.id);
      setConditions({});
      setLastQualification(null);
      setVisitOpen(false);
      setError("");
      setForm({ status: lead.latest_qualification?.status || "qualified", preferred_configuration: lead.config && lead.config !== "Requirement pending" ? lead.config : "", verified_budget_min: "", verified_budget_max: "", expected_booking_date: "", decision_notes: "" });
    };
    const setDisqualifiedFromConfiguredConditions = () => {
      if (!criteria.length) {
        setError("No active quality-score criteria are configured for disqualification routing.");
        return;
      }
      const nextConditions = {};
      criteria.forEach(([key, criterion]) => {
        const lowest = (criterion.options || []).slice().sort((a, b) => Number(a.points || 0) - Number(b.points || 0))[0];
        if (lowest?.value) nextConditions[key] = lowest.value;
      });
      if (!criteria.every(([key]) => nextConditions[key])) {
        setError("Every quality-score criterion must have at least one configured condition before disqualification.");
        return;
      }
      const nextPreview = qualityScorePreview(rules, nextConditions);
      const nextStatus = ["qualified", "nurture", "disqualified"].includes(nextPreview.band?.status_hint) ? nextPreview.band.status_hint : "disqualified";
      setConditions(nextConditions);
      setForm(current => ({ ...current, status: nextStatus }));
      setError(nextStatus === "disqualified" ? "" : "Lowest configured conditions do not route to disqualified. Review score bands before saving.");
    };
    const scheduleQualificationSiteVisit = async (lead, payload) => {
      const storeUrl = lead.site_visit_store_url || siteVisitOptions?.store_url;

      if (!storeUrl || !lead.can_schedule_site_visit) {
        throw new Error("Site visit scheduling is available only for authorized CRM roles.");
      }

      if (!lead.record_id) {
        throw new Error("Select a server-backed lead before scheduling a site visit.");
      }

      const response = await fetch(storeUrl, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({
          lead_id: lead.record_id,
          ...payload,
        }),
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(firstApiError(body));
      }

      const visit = body.data || {};
      const scheduledAt = visit.scheduled_at || payload.scheduled_at;
      const updatedLead = {
        ...lead,
        status: "Site Visit Scheduled",
        badge: leadBadge("Site Visit Scheduled", lead.system_status),
        score: leadScore("Site Visit Scheduled", lead.system_status),
        next: scheduledAt ? new Date(scheduledAt).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : lead.next,
        latest_site_visit: {
          id: visit.id,
          visit_number: visit.visit_number,
          status: visit.status,
          scheduled_at: scheduledAt,
          assigned_to: visit.assigned_to,
        },
        activities: [{
          activity_number: visit.visit_number,
          activity_type: "site_visit",
          activity_at: scheduledAt,
          t: "Just now",
          who: visit.scheduled_by?.name || "Current user",
          act: `Site visit ${visit.visit_number || ""} scheduled from qualification screen`,
          subject: "Site visit scheduled",
          outcome: "scheduled",
          new_stage: "Site Visit Scheduled",
          next_follow_up_at: scheduledAt,
          ic: "calendar",
          c: "var(--orange)",
        }, ...(lead.activities || [])].slice(0, 5),
      };

      setQueue(rows => rows.map(row => row.id === lead.id ? updatedLead : row));
      setActiveId(updatedLead.id);
      toast("Site visit " + (visit.visit_number || "") + " scheduled from qualification.", "green");

      return updatedLead;
    };
    const submit = async () => {
      setError("");
      if (!qualificationOptions?.store_url || !qualificationOptions?.can_qualify) return toast("Lead qualification is available only for authorized CRM roles.", "orange");
      if (!active?.record_id) return setError("Select a server-backed lead before qualification.");
      if (!criteria.every(([key]) => conditions[key])) return setError("Select one configured condition for every quality-score criterion.");
      if (!form.decision_notes.trim()) return setError("Decision notes are required for audit history.");
      try {
        setBusy(true);
        const body = await apiJson(qualificationOptions.store_url, { method: "POST", body: JSON.stringify({
          lead_id: active.record_id,
          status: form.status,
          quality_conditions: conditions,
          preferred_configuration: form.preferred_configuration.trim() || null,
          verified_budget_min: form.verified_budget_min ? Number(form.verified_budget_min) : null,
          verified_budget_max: form.verified_budget_max ? Number(form.verified_budget_max) : null,
          expected_booking_date: form.expected_booking_date || null,
          decision_notes: form.decision_notes.trim(),
          requirements: { rule_source: rules.source || "application_default", entered_from: "Lead Qualification screen" },
        }) });
        const result = body.data;
        setLastQualification(result);
        const updatedLead = { ...active, status: result.lead?.stage || (form.status === "qualified" ? "Qualified" : form.status === "nurture" ? "Nurture" : "Lost"), system_status: form.status === "disqualified" ? "lost" : "open", badge: leadBadge(result.lead?.stage || active.status, form.status === "disqualified" ? "lost" : active.system_status), score: result.score, latest_qualification: { id: result.id, qualification_number: result.qualification_number, status: result.status, score: result.score, quality_score: result.quality_score, qualified_at: result.qualified_at } };
        setQueue(rows => rows.map(row => row.id === active.id ? updatedLead : row));
        setActiveId(updatedLead.id);
        toast("Lead qualification " + result.qualification_number + " saved with score " + result.score + ".", "green");
      } catch (apiError) {
        setError(apiError.message || "Lead qualification failed.");
      } finally {
        setBusy(false);
      }
    };
    if (!active) return e("div", { className: "page page-wide" }, e(PageHead, { crumbs: ["Sales & CRM", "Lead Qualification"], title: "Lead Qualification", sub: "No open Laravel leads are available in your current scope." }), e(Empty, { title: "No verification queue", sub: qualificationOptions?.store_url ? "Capture or assign Laravel leads before running qualification." : "Lead Qualification API required; no local prototype leads are fabricated." }));
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Sales & CRM", "Lead Qualification"], title: "Lead Qualification", sub: "Pre-sales verification queue with configurable quality-score rules and audited routing.",
        actions: e("div", { className: "row gap-2" },
          e(Badge, { tone: qualificationOptions?.can_qualify ? "b-green" : "b-orange", dot: true }, qualificationOptions?.can_qualify ? "Laravel connected" : "Read only"),
          e(Button, { sm: true, icon: "sliders", onClick: () => setRuleModal(true), children: "Score Rules" })) }),
      e("div", { className: "grid", style: { gridTemplateColumns: "320px 1fr", alignItems: "start", gap: 16 } },
        e(Card, { title: "Verification Queue", sub: queue.length + " awaiting", className: "" },
          e("div", { style: { maxHeight: 520, overflowY: "auto" } }, queue.map(l =>
            e("div", { key: l.id, onClick: () => chooseLead(l), style: { display: "flex", gap: 10, padding: "12px 16px", borderBottom: "1px solid var(--border)", cursor: "pointer", background: active.id === l.id ? "var(--accent-soft)" : "transparent", alignItems: "center" } },
              e(Avatar, { name: l.name, sm: true }), e("div", { style: { flex: 1, minWidth: 0 } }, e("div", { className: "cell-strong", style: { fontSize: 13 } }, l.name), e("div", { className: "cell-sub" }, l.source + " · " + l.config)),
              e("span", { className: "badge " + scoreTone(l.score) }, l.score)))),
        ),
        e(Card, { title: "Call Verification — " + active.name, sub: active.phone + " · " + active.source },
          e("div", { className: "card-pad" },
            e("div", { className: "grid g-2", style: { gap: 14, marginBottom: 18 } },
              e(QField, { label: "Budget Confirmed", value: active.budget }), e(QField, { label: "Requirement", value: active.config + " · East facing" }),
              e(QField, { label: "Purchase Timeline", value: "Within 3 months" }), e(QField, { label: "Loan Readiness", value: "Pre-approved · HDFC" })),
            e("div", { className: "card-title", style: { marginBottom: 10 } }, "Lead Quality Score Rules"),
            e("div", { className: "muted", style: { fontSize: 12, marginBottom: 12 } }, "Rule key: " + (rules.setting_key || "crm.lead_quality_score.rules") + " · Source: " + (rules.source || "application_default") + (rules.version ? " · Version " + rules.version : " · no approved setting yet")),
            e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 16 } }, criteria.map(([key, criterion]) =>
              e("label", { key, style: label }, criterion.label + " (" + criterion.max_points + " pts)",
                e("select", { style: field, value: conditions[key] || "", onChange: ev => setConditions(current => ({ ...current, [key]: ev.target.value })), disabled: busy, required: true },
                  e("option", { value: "" }, "Select condition"),
                  (criterion.options || []).map(option => e("option", { key: option.value, value: option.value }, option.label + " · " + option.points + " pts")))))),
            e("div", { className: "card-title", style: { marginBottom: 10 } }, "Classification & Routing"),
            e("div", { className: "muted", style: { fontSize: 12, marginBottom: 10 } }, "Status follows the configured score-band routing. Current band routes to: " + routedStatus + "."),
            e("div", { className: "grid g-2", style: { gap: 12, marginBottom: 16 } },
              e("label", { style: label }, "Status", e("select", { style: field, value: form.status, onChange: ev => set("status", ev.target.value), disabled: busy }, (qualificationOptions?.statuses || []).map(status => e("option", { key: status.value, value: status.value }, status.label)))),
              e("label", { style: label }, "Preferred Configuration", e("input", { style: field, value: form.preferred_configuration, onChange: ev => set("preferred_configuration", ev.target.value), disabled: busy, placeholder: "2BHK / 3BHK / office..." })),
              e("label", { style: label }, "Verified Budget Min", e("input", { style: field, type: "number", min: "0", value: form.verified_budget_min, onChange: ev => set("verified_budget_min", ev.target.value), disabled: busy })),
              e("label", { style: label }, "Verified Budget Max", e("input", { style: field, type: "number", min: "0", value: form.verified_budget_max, onChange: ev => set("verified_budget_max", ev.target.value), disabled: busy })),
              e("label", { style: label }, "Expected Booking Date", e("input", { style: field, type: "date", value: form.expected_booking_date, onChange: ev => set("expected_booking_date", ev.target.value), disabled: busy })),
              e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Decision Notes", e("textarea", { style: Object.assign({}, field, { minHeight: 82 }), value: form.decision_notes, onChange: ev => set("decision_notes", ev.target.value), disabled: busy, required: true, placeholder: "Verification notes, customer requirement, next action, risk reason..." }))),
            error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
            e("div", { className: "row gap-3", style: { marginBottom: 12 } }, e("div", { className: "bar", style: { flex: 1, height: 10 } }, e("i", { style: { width: preview.score + "%", background: preview.score >= 75 ? "var(--green)" : preview.score >= 50 ? "var(--orange)" : "var(--red)" } })), e("span", { className: "mono", style: { fontWeight: 800, fontSize: 18 } }, preview.score)),
            e("div", { className: "row between", style: { gap: 12, flexWrap: "wrap", marginBottom: 14 } },
              e("div", null, e(Badge, { tone: preview.band.tone === "green" ? "b-green" : preview.band.tone === "orange" ? "b-orange" : preview.band.tone === "red" ? "b-red" : "b-slate", dot: true }, preview.band.label || "Unclassified"), e("span", { className: "muted", style: { marginLeft: 8, fontSize: 12 } }, "Raw " + preview.raw + " / " + preview.max + " points")),
              lastQualification && e("div", { className: "cell-sub" }, "Saved: " + lastQualification.qualification_number + " · " + lastQualification.score)),
            e("div", { className: "row gap-2" },
              e(Button, { variant: "primary", icon: "check", disabled: busy, onClick: submit, children: busy ? "Saving..." : "Save Qualification" }),
              e(Button, { icon: "calendar", disabled: busy || !active.can_schedule_site_visit || !active.record_id, onClick: () => setVisitOpen(true), children: "Schedule Site Visit" }),
              e(Button, { icon: "x", disabled: busy, onClick: setDisqualifiedFromConfiguredConditions, children: "Set Disqualified" }))),
        ),
      ),
      ruleModal && e(QualityRuleDraftModal, { qualificationOptions, rules, onClose: () => setRuleModal(false), toast }),
      visitOpen && active && e(ScheduleVisitModal, { lead: active, options: siteVisitOptions, onClose: () => setVisitOpen(false), onSubmit: scheduleQualificationSiteVisit }),
    );
  }
  function qualityScorePreview(rules, conditions) {
    const criteria = Object.entries(rules.criteria || {});
    let raw = 0;
    let max = 0;
    criteria.forEach(([key, criterion]) => {
      max += Number(criterion.max_points || 0);
      const option = (criterion.options || []).find(item => item.value === conditions[key]);
      raw += Number(option?.points || 0);
    });
    const score = max > 0 ? Math.round((raw / max) * 100) : 0;
    const band = (rules.bands || []).slice().sort((a, b) => Number(b.min_score || 0) - Number(a.min_score || 0)).find(item => score >= Number(item.min_score || 0)) || { label: "Unclassified", tone: "slate" };
    return { raw, max, score, band };
  }
  function QField({ label, value }) {
    return e("div", { className: "card", style: { padding: "12px 14px" } }, e("div", { className: "kpi-mini", style: { marginBottom: 4 } }, label), e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, value));
  }

  function normalizeSalesBookingRow(row) {
    const status = row?.status || "draft";
    const statusLabel = row?.status_label || status.replaceAll("_", " ").replace(/\b\w/g, c => c.toUpperCase());
    const net = Number(row?.net_receivable || 0);
    const bookingAmount = Number(row?.booking_amount || 0);
    const paymentPercent = row?.payment_percent !== undefined ? Number(row.payment_percent || 0) : (net > 0 ? Math.min(100, Math.round((bookingAmount / net) * 1000) / 10) : 0);
    return {
      ...row,
      status,
      status_label: statusLabel,
      status_badge: row?.status_badge || (status === "registered" ? "b-green" : status === "agreement_pending" ? "b-orange" : status === "confirmed" ? "b-blue" : status === "cancelled" ? "b-red" : "b-slate"),
      net_receivable: net,
      net_receivable_lakh: row?.net_receivable_lakh !== undefined ? Number(row.net_receivable_lakh || 0) : Math.round((net / 100000) * 100) / 100,
      booking_amount: bookingAmount,
      payment_percent: paymentPercent,
    };
  }

  function SalesBookingModal({ options, onClose, onSaved, toast }) {
    const leads = options?.eligible_leads || [];
    const units = options?.units || [];
    const firstLead = leads[0] || null;
    const unitsForLead = (leadId) => {
      const lead = leads.find(l => String(l.id) === String(leadId)) || null;
      return units.filter(u => !lead?.project_id || Number(u.project_id) === Number(lead.project_id));
    };
    const firstUnit = firstLead ? unitsForLead(firstLead.id)[0] : units[0] || null;
    const [form, setForm] = React.useState({
      lead_id: firstLead?.id ? String(firstLead.id) : "",
      project_unit_id: firstUnit?.id ? String(firstUnit.id) : "",
      booked_on: new Date().toISOString().slice(0, 10),
      booking_amount: firstUnit?.total_price ? String(Math.round(Number(firstUnit.total_price) * 0.1)) : "",
      discount_amount: "0",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const selectedLead = leads.find(l => String(l.id) === String(form.lead_id)) || null;
    const availableUnits = unitsForLead(form.lead_id);
    const selectedUnit = availableUnits.find(u => String(u.id) === String(form.project_unit_id)) || null;
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const applyLead = (leadId) => {
      const nextUnits = unitsForLead(leadId);
      const nextUnit = nextUnits[0] || null;
      setForm(current => ({
        ...current,
        lead_id: leadId,
        project_unit_id: nextUnit?.id ? String(nextUnit.id) : "",
        booking_amount: nextUnit?.total_price ? String(Math.round(Number(nextUnit.total_price) * 0.1)) : current.booking_amount,
      }));
    };
    const applyUnit = (unitId) => {
      const unit = availableUnits.find(u => String(u.id) === String(unitId)) || null;
      setForm(current => ({
        ...current,
        project_unit_id: unitId,
        booking_amount: unit?.total_price ? String(Math.round(Number(unit.total_price) * 0.1)) : current.booking_amount,
      }));
    };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      const bookingAmount = Number(form.booking_amount);
      const discountAmount = Number(form.discount_amount || 0);
      if (!selectedLead) return setError("Select a lead with linked customer details.");
      if (!selectedLead.customer_id) return setError("Selected lead does not have a customer record.");
      if (!selectedUnit) return setError("Select an available or reserved unit in the lead project.");
      if (!Number.isFinite(bookingAmount) || bookingAmount <= 0) return setError("Booking amount must be above zero.");
      if (!Number.isFinite(discountAmount) || discountAmount < 0) return setError("Discount amount cannot be negative.");
      try {
        setBusy(true);
        const body = await apiJson(options.store_url, {
          method: "POST",
          body: JSON.stringify({
            lead_id: Number(selectedLead.id),
            customer_id: Number(selectedLead.customer_id),
            partner_id: selectedLead.partner_id ? Number(selectedLead.partner_id) : null,
            project_unit_id: Number(selectedUnit.id),
            booked_on: form.booked_on,
            booking_amount: bookingAmount,
            discount_amount: discountAmount,
            payment_schedule: options.default_payment_schedule || [],
          }),
        });
        const saved = normalizeSalesBookingRow(body.data);
        onSaved(saved);
        toast("Booking " + saved.booking_code + " created through Laravel booking workflow.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(760px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "New Booking"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates a lead-backed booking with Laravel validation, price calculation, unit-status update and audit trail.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        (!leads.length || !units.length) && e("div", { className: "empty", style: { marginBottom: 12 } }, "No eligible lead and bookable unit combination is available in your current scope."),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Lead / Customer", e("select", { style: field, value: form.lead_id, onChange: ev => applyLead(ev.target.value), disabled: busy || !leads.length, required: true }, leads.map(lead => e("option", { key: lead.id, value: lead.id }, (lead.lead_code || "Lead") + " · " + (lead.customer?.name || lead.name || "Customer") + " · " + (lead.project || "Project"))))),
          e("label", { style: label }, "Unit", e("select", { style: field, value: form.project_unit_id, onChange: ev => applyUnit(ev.target.value), disabled: busy || !availableUnits.length, required: true }, availableUnits.map(unit => e("option", { key: unit.id, value: unit.id }, unit.unit_code + " · " + (unit.project_code || unit.project_name || "Project") + " · " + formatInr(unit.total_price))))),
          e("label", { style: label }, "Booked On", e("input", { style: field, type: "date", value: form.booked_on, onChange: ev => set("booked_on", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Booking Amount", e("input", { style: field, type: "number", min: "1", step: "0.01", value: form.booking_amount, onChange: ev => set("booking_amount", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Discount Amount", e("input", { style: field, type: "number", min: "0", step: "0.01", value: form.discount_amount, onChange: ev => set("discount_amount", ev.target.value), disabled: busy })),
          e("div", { style: Object.assign({}, label, { alignSelf: "end" }) }, "Selected Price", e("div", { className: "cell-strong", style: { padding: "10px 11px", border: "1px dashed var(--border)", borderRadius: 10 } }, selectedUnit ? formatInr(selectedUnit.total_price) : "No unit selected"))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, marginTop: 4, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Payment schedule uses the configured booking milestones supplied by the server payload."),
          e("div", { className: "row gap-2" },
            e(Button, { type: "button", onClick: onClose, children: "Cancel" }),
            e(Button, { variant: "primary", icon: "plus", type: "submit", disabled: busy || !selectedLead || !selectedUnit, children: busy ? "Creating…" : "Create Booking" })))));
  }

  // ---------------- SALES & BOOKING ----------------
  function Sales({ toast }) {
    const options = window.Builder360Server?.sales_booking_options || null;
    const summary = options?.summary || {};
    const [bookings, setBookings] = React.useState((options?.bookings || []).map(normalizeSalesBookingRow));
    const [creating, setCreating] = React.useState(false);
    const stages = options?.stage_distribution || ["Lead", "Verified", "Site Visit", "Negotiation", "Booking", "Agreement", "Registration", "Possession"].map(label => ({ label, count: 0 }));
    const openBooking = () => {
      if (!options?.can_create || !options?.store_url) {
        toast("Booking creation is not available for this role. Use a booking-manage role or create from Lead Management after qualification.", "orange");
        return;
      }
      if (!(options.eligible_leads || []).length || !(options.units || []).length) {
        toast("No eligible customer-linked lead and bookable unit combination is available in your current scope.", "orange");
        return;
      }
      setCreating(true);
    };
    const addBooking = (booking) => setBookings(rows => [normalizeSalesBookingRow(booking), ...rows]);
    const bookingTableRows = bookings.length ? bookings.map(b =>
      e("tr", { key: b.id || b.booking_code },
        e("td", { className: "cell-strong mono" }, b.booking_code || b.id),
        e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: b.customer?.name || "Customer", sm: true }), b.customer?.name || "Customer pending")),
        e("td", null, e("div", null, e("div", { className: "cell-strong" }, b.unit?.unit_code || "Unit pending"), e("div", { className: "cell-sub" }, b.project?.code || b.project?.name || "Project pending"))),
        e("td", { className: "num cell-strong" }, "₹" + Number(b.net_receivable_lakh || 0).toLocaleString("en-IN", { maximumFractionDigits: 2 }) + " L"),
        e("td", null, e(Badge, { tone: b.status_badge, dot: true }, b.status_label)),
        e("td", null, e(ProgCell, { value: b.payment_percent || 0 })),
        e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: b.booked_by?.name || "System", sm: true, size: 24 }), e("span", { style: { fontSize: 12.5 } }, (b.booked_by?.name || "System").split(" ")[0]))),
        e("td", { className: "faint" }, b.booked_on || "—"))
    ) : [e("tr", { key: "empty" }, e("td", { colSpan: 8 }, e(Empty, { title: "No bookings found", sub: "No scoped Laravel booking records are available for this role." })))];
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Sales & CRM", "Sales & Booking"], title: "Sales & Booking Lifecycle", sub: "Laravel-backed lead-to-booking, agreement and registration visibility from current user scope.",
        actions: [e(ChipSelect, { key: 1, label: "Source", value: options?.source || "Unavailable" }), e(Button, { key: 2, icon: "plus", variant: "primary", onClick: openBooking, children: "New Booking" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Bookings (MTD)", value: formatCount(summary.bookings_mtd || 0), icon: "tag", tone: "green", sub: `${formatCount(summary.active_bookings || 0)} active booking(s)` }),
        e(Stat, { label: "Booking Value", value: "₹" + Number(summary.booking_value_crore || 0).toLocaleString("en-IN", { maximumFractionDigits: 2 }), unit: "Cr", icon: "rupee", tone: "accent", sub: "confirmed/agreement/registered" }),
        e(Stat, { label: "Pending Agreements", value: formatCount(summary.pending_agreements || 0), icon: "doc", tone: "orange", sub: "agreement_pending status" }),
        e(Stat, { label: "Avg. Deal Size", value: "₹" + Number(summary.avg_deal_size_lakh || 0).toLocaleString("en-IN", { maximumFractionDigits: 2 }), unit: "L", icon: "trend", tone: "violet", sub: `${formatCount(summary.total_bookings || 0)} total scoped booking(s)` }),
      ),
      e(Card, { title: "Booking Journey", sub: "Stage distribution calculated from scoped leads, visits, bookings and handovers", pad: true, style: { marginBottom: 16 } },
        e("div", { style: { display: "flex", gap: 6, overflowX: "auto", paddingBottom: 4 } }, stages.map((stage, i) =>
          e("div", { key: stage.key || stage.label, style: { flex: 1, minWidth: 110, textAlign: "center" } },
            e("div", { style: { height: 4, borderRadius: 99, background: i < 5 ? "var(--accent)" : "var(--green)", marginBottom: 10, opacity: Math.max(0.45, 1 - i * 0.07) } }),
            e("div", { className: "mono", style: { fontWeight: 800, fontSize: 19 } }, formatCount(stage.count || 0)),
            e("div", { className: "kpi-mini" }, stage.label))))),
      e(Card, { title: "Active Bookings", sub: "Recent scoped bookings loaded from Laravel" }, e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
        e("thead", null, e("tr", null, ["Booking", "Customer", "Unit", "Value", "Stage", "Payment", "Owner", "Date"].map((h, i) => e("th", { key: i, style: i === 3 ? { textAlign: "right" } : {} }, h)))),
        e("tbody", null, bookingTableRows),
      ))),
      creating && e(SalesBookingModal, { options, onClose: () => setCreating(false), onSaved: addBooking, toast }),
    );
  }

  function CollectionReceiptModal({ options, onClose, onSaved, toast }) {
    const bookings = options?.bookings || [];
    const modes = options?.payment_modes || ["cash", "cheque", "neft", "rtgs", "upi", "online"];
    const firstBooking = bookings[0] || null;
    const firstSchedule = firstBooking?.schedules?.[0] || null;
    const [form, setForm] = React.useState({
      booking_id: firstBooking?.id ? String(firstBooking.id) : "",
      booking_payment_schedule_id: firstSchedule?.id ? String(firstSchedule.id) : "",
      receipt_date: new Date().toISOString().slice(0, 10),
      payment_mode: "neft",
      instrument_number: "",
      bank_name: "",
      amount: String(firstSchedule?.outstanding_amount || firstBooking?.outstanding_amount || ""),
      tax_deducted_amount: "0",
      notes: "",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const selectedBooking = bookings.find(b => String(b.id) === String(form.booking_id)) || null;
    const schedules = selectedBooking?.schedules || [];
    const selectedSchedule = schedules.find(s => String(s.id) === String(form.booking_payment_schedule_id)) || null;
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const applyBooking = (bookingId) => {
      const booking = bookings.find(b => String(b.id) === String(bookingId)) || null;
      const schedule = booking?.schedules?.[0] || null;
      setForm(current => ({
        ...current,
        booking_id: bookingId,
        booking_payment_schedule_id: schedule?.id ? String(schedule.id) : "",
        amount: String(schedule?.outstanding_amount || booking?.outstanding_amount || ""),
      }));
    };
    const applySchedule = (scheduleId) => {
      const schedule = schedules.find(s => String(s.id) === String(scheduleId)) || null;
      setForm(current => ({
        ...current,
        booking_payment_schedule_id: scheduleId,
        amount: String(schedule?.outstanding_amount || selectedBooking?.outstanding_amount || ""),
      }));
    };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      const amount = Number(form.amount);
      const tds = Number(form.tax_deducted_amount || 0);
      const outstanding = Number(selectedSchedule?.outstanding_amount || selectedBooking?.outstanding_amount || 0);
      if (!selectedBooking) return setError("Select an active booking with outstanding receivable.");
      if (!Number.isFinite(amount) || amount <= 0) return setError("Enter a valid receipt amount above zero.");
      if (outstanding > 0 && amount > outstanding) return setError("Receipt amount exceeds selected outstanding amount.");
      if (tds < 0 || tds > amount) return setError("Tax deducted amount cannot exceed receipt amount.");
      if (form.payment_mode !== "cash" && !form.instrument_number.trim()) return setError("Instrument number is required for non-cash receipts.");
      try {
        setBusy(true);
        const body = await apiJson(options.store_url, {
          method: "POST",
          body: JSON.stringify({
            booking_id: Number(form.booking_id),
            booking_payment_schedule_id: form.booking_payment_schedule_id ? Number(form.booking_payment_schedule_id) : null,
            receipt_date: form.receipt_date,
            payment_mode: form.payment_mode,
            instrument_number: form.instrument_number.trim() || null,
            bank_name: form.bank_name.trim() || null,
            amount,
            tax_deducted_amount: tds,
            notes: form.notes.trim() || null,
          }),
        });
        onSaved(body.data);
        toast("Collection receipt " + body.data.receipt_number + " submitted for approval.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(760px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "New Collection Receipt"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Submits a validated receipt against a booking or payment schedule.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        !bookings.length && e("div", { className: "empty", style: { marginBottom: 12 } }, "No active booking with outstanding receivable is available in your scope."),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Booking", e("select", { style: field, value: form.booking_id, onChange: ev => applyBooking(ev.target.value), disabled: busy || !bookings.length, required: true }, bookings.map(b => e("option", { key: b.id, value: b.id }, b.booking_code + " · " + (b.customer?.name || "Customer") + " · " + formatInr(b.outstanding_amount))))),
          e("label", { style: label }, "Schedule", e("select", { style: field, value: form.booking_payment_schedule_id, onChange: ev => applySchedule(ev.target.value), disabled: busy || !schedules.length }, e("option", { value: "" }, "Booking-level receipt"), schedules.map(s => e("option", { key: s.id, value: s.id }, "#" + s.sequence + " · " + s.milestone + " · " + formatInr(s.outstanding_amount))))),
          e("label", { style: label }, "Receipt Date", e("input", { style: field, type: "date", value: form.receipt_date, onChange: ev => set("receipt_date", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Payment Mode", e("select", { style: field, value: form.payment_mode, onChange: ev => set("payment_mode", ev.target.value), disabled: busy }, modes.map(m => e("option", { key: m, value: m }, m.toUpperCase())))),
          e("label", { style: label }, "Amount", e("input", { style: field, type: "number", min: "1", step: "0.01", value: form.amount, onChange: ev => set("amount", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Tax Deducted", e("input", { style: field, type: "number", min: "0", step: "0.01", value: form.tax_deducted_amount, onChange: ev => set("tax_deducted_amount", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Instrument No.", e("input", { style: field, value: form.instrument_number, onChange: ev => set("instrument_number", ev.target.value), disabled: busy, placeholder: "Required unless cash" })),
          e("label", { style: label }, "Bank Name", e("input", { style: field, value: form.bank_name, onChange: ev => set("bank_name", ev.target.value), disabled: busy })),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Notes", e("textarea", { style: Object.assign({}, field, { minHeight: 70 }), value: form.notes, onChange: ev => set("notes", ev.target.value), disabled: busy }))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, marginTop: 4, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Receipts remain Submitted until approved by a different authorized user."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "plus", type: "submit", disabled: busy || !bookings.length, children: busy ? "Submitting…" : "Submit Receipt" })))));
  }

  function receiptApproveUrl(template, receipt) {
    return String(template || "").replace("__RECEIPT__", receipt?.id || "");
  }

  function CollectionDemandModal({ options, onClose, onSaved, toast }) {
    const bookings = options?.bookings || [];
    const firstBooking = bookings[0] || null;
    const firstSchedule = firstBooking?.schedules?.find(s => !s.has_active_request) || firstBooking?.schedules?.[0] || null;
    const expiry = new Date(Date.now() + Number(options?.default_expiry_days || 7) * 86400000).toISOString().slice(0, 16);
    const [form, setForm] = React.useState({
      booking_id: firstBooking?.id ? String(firstBooking.id) : "",
      booking_payment_schedule_id: firstSchedule?.id ? String(firstSchedule.id) : "",
      amount: String(firstSchedule?.outstanding_amount || firstBooking?.outstanding_amount || ""),
      purpose: firstSchedule?.milestone ? firstSchedule.milestone + " demand/payment link" : "Customer demand payment link",
      expires_at: expiry,
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const selectedBooking = bookings.find(b => String(b.id) === String(form.booking_id)) || null;
    const schedules = selectedBooking?.schedules || [];
    const selectedSchedule = schedules.find(s => String(s.id) === String(form.booking_payment_schedule_id)) || null;
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const applyBooking = (bookingId) => {
      const booking = bookings.find(b => String(b.id) === String(bookingId)) || null;
      const schedule = booking?.schedules?.find(s => !s.has_active_request) || booking?.schedules?.[0] || null;
      setForm(current => ({
        ...current,
        booking_id: bookingId,
        booking_payment_schedule_id: schedule?.id ? String(schedule.id) : "",
        amount: String(schedule?.outstanding_amount || booking?.outstanding_amount || ""),
        purpose: schedule?.milestone ? schedule.milestone + " demand/payment link" : "Customer demand payment link",
      }));
    };
    const applySchedule = (scheduleId) => {
      const schedule = schedules.find(s => String(s.id) === String(scheduleId)) || null;
      setForm(current => ({
        ...current,
        booking_payment_schedule_id: scheduleId,
        amount: String(schedule?.outstanding_amount || selectedBooking?.outstanding_amount || ""),
        purpose: schedule?.milestone ? schedule.milestone + " demand/payment link" : current.purpose,
      }));
    };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      const amount = Number(form.amount);
      const outstanding = Number(selectedSchedule?.outstanding_amount || selectedBooking?.outstanding_amount || 0);
      if (!selectedBooking) return setError("Select an active booking with outstanding receivable.");
      if (!Number.isFinite(amount) || amount <= 0) return setError("Enter a valid demand amount above zero.");
      if (outstanding > 0 && amount > outstanding) return setError("Demand amount exceeds selected outstanding receivable.");
      if (!form.purpose.trim()) return setError("Demand purpose is required.");
      try {
        setBusy(true);
        const body = await apiJson(options.store_url, {
          method: "POST",
          body: JSON.stringify({
            booking_id: Number(form.booking_id),
            booking_payment_schedule_id: form.booking_payment_schedule_id ? Number(form.booking_payment_schedule_id) : null,
            amount,
            purpose: form.purpose.trim(),
            expires_at: form.expires_at ? new Date(form.expires_at).toISOString() : null,
            metadata: { source: "customer_collections_demand_generation" },
          }),
        });
        onSaved(body.data);
        toast("Demand/payment request " + body.data.request_number + " created for buyer payment.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(740px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "Generate Customer Demand"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates a governed Laravel payment request against booking outstanding.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        !bookings.length && e("div", { className: "empty", style: { marginBottom: 12 } }, "No active booking with outstanding receivable is available in your scope."),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Booking", e("select", { style: field, value: form.booking_id, onChange: ev => applyBooking(ev.target.value), disabled: busy || !bookings.length, required: true }, bookings.map(b => e("option", { key: b.id, value: b.id }, b.booking_code + " · " + (b.customer?.name || "Customer") + " · " + formatInr(b.outstanding_amount))))),
          e("label", { style: label }, "Schedule", e("select", { style: field, value: form.booking_payment_schedule_id, onChange: ev => applySchedule(ev.target.value), disabled: busy || !schedules.length },
            e("option", { value: "" }, "Booking-level demand"),
            schedules.map(s => e("option", { key: s.id, value: s.id, disabled: s.has_active_request }, "#" + s.sequence + " · " + s.milestone + " · " + formatInr(s.outstanding_amount) + (s.has_active_request ? " · active link exists" : ""))))),
          e("label", { style: label }, "Demand Amount", e("input", { style: field, type: "number", min: "1", step: "0.01", value: form.amount, onChange: ev => set("amount", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Expires At", e("input", { style: field, type: "datetime-local", value: form.expires_at, onChange: ev => set("expires_at", ev.target.value), disabled: busy })),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Purpose", e("textarea", { style: Object.assign({}, field, { minHeight: 70 }), value: form.purpose, onChange: ev => set("purpose", ev.target.value), disabled: busy, required: true }))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, marginTop: 4, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Gateway: ", options?.gateway_label || "Internal simulated gateway", options?.gateway_mode === "configured" ? ". Webhook reconciliation is enabled for the configured provider." : ". Buyer payment is simulated internally; no external gateway movement is invoked."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "doc", type: "submit", disabled: busy || !bookings.length, children: busy ? "Generating…" : "Generate Demand" })))));
  }

  // ---------------- COLLECTIONS ----------------
  function Collections({ toast }) {
    const collectionMetrics = window.Builder360Server?.collection_metrics || { source: "unavailable", summary: {}, ageing_buckets: [], ledger_rows: [] };
    const receiptOptions = window.Builder360Server?.collection_receipt_options || null;
    const paymentRequestOptions = window.Builder360Server?.finance_payment_request_options || null;
    const [creatingReceipt, setCreatingReceipt] = React.useState(false);
    const [creatingDemand, setCreatingDemand] = React.useState(false);
    const [createdReceipts, setCreatedReceipts] = React.useState([]);
    const [createdPaymentRequests, setCreatedPaymentRequests] = React.useState([]);
    const [approvedReceipts, setApprovedReceipts] = React.useState({});
    const summary = collectionMetrics.summary || {};
    const ageingBuckets = collectionMetrics.ageing_buckets || [];
    const ledgerRows = collectionMetrics.ledger_rows || [];
    const visibleReceipts = [
      ...createdReceipts,
      ...((receiptOptions?.receipts || []).map(receipt => approvedReceipts[receipt.id] || receipt)),
    ];
    const visiblePaymentRequests = [
      ...createdPaymentRequests,
      ...(paymentRequestOptions?.requests || []),
    ];
    const crore = value => String(Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 2 }));
    const openReceiptModal = () => {
      if (!receiptOptions?.can_create || !receiptOptions?.store_url) {
        toast("Collection receipt capture is not available for your role.", "orange");
        return;
      }
      setCreatingReceipt(true);
    };
    const approveReceipt = async (receipt) => {
      if (!receiptOptions?.can_approve || !receiptOptions?.approve_url_template || receipt.status !== "submitted") {
        toast("Collection receipt approval is not available for this receipt.", "orange");
        return;
      }
      try {
        const body = await apiJson(receiptApproveUrl(receiptOptions.approve_url_template, receipt), { method: "PATCH", body: JSON.stringify({ note: "Approved from Customer Collections screen." }) });
        setApprovedReceipts(current => ({ ...current, [receipt.id]: body.data }));
        toast("Receipt " + receipt.receipt_number + " approved.", "green");
      } catch (apiError) {
        toast(apiError.message, "red");
      }
    };
    const exportCollectionReport = () => {
      if (!receiptOptions?.can_export || !receiptOptions?.export_url) {
        toast("Collection report export is not available for your role.", "orange");
        return;
      }
      window.location.assign(receiptOptions.export_url);
      toast("Collection report export started from Laravel.", "green");
    };
    const openDemandModal = () => {
      if (!paymentRequestOptions?.can_create || !paymentRequestOptions?.store_url) {
        toast("Demand generation requires Finance Payment Request permission and company scope.", "orange");
        return;
      }
      setCreatingDemand(true);
    };

    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Sales & CRM", "Customer Collections"], title: "Customer Collections", sub: "Demand letters, receipts, outstanding and ageing across all buyers.",
        actions: [e(Button, { key: 1, icon: "doc", onClick: openDemandModal, children: "Generate Demand" }), e(Button, { key: 2, icon: "plus", onClick: openReceiptModal, children: "New Receipt" }), e(Button, { key: 3, icon: "download", variant: "primary", onClick: exportCollectionReport, children: "Collection Report" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Collected (FY)", value: "₹" + crore(summary.collected_fy_crore), unit: "Cr", icon: "wallet", tone: "green", sub: `${collectionMetrics.source || "server"} scoped` }),
        e(Stat, { label: "Outstanding", value: "₹" + crore(summary.outstanding_crore), unit: "Cr", icon: "clock", tone: "orange", sub: `across ${formatCount(summary.outstanding_units)} booking(s)` }),
        e(Stat, { label: "Overdue", value: "₹" + crore(summary.overdue_crore), unit: "Cr", icon: "alert", tone: "red", sub: `${formatCount(summary.overdue_demands)} demand(s)` }),
        e(Stat, { label: "Due This Month", value: "₹" + crore(summary.due_this_month_crore), unit: "Cr", icon: "calendar", tone: "accent", sub: `${formatCount(summary.due_this_month_demands)} schedule line(s)` }),
      ),
      e("div", { className: "grid", style: { gridTemplateColumns: "1fr 1.6fr", alignItems: "start" } },
        e(Card, { title: "Outstanding Ageing", sub: "₹ Cr by bucket", pad: true },
          ageingBuckets.length
            ? e(BarChart, { height: 170, data: ageingBuckets })
            : e(Empty, { title: "No ageing data", sub: "No scoped payment schedules are available." })),
        e(Card, { title: "Demand & Receipt Status", sub: "Recent customer ledger activity" },
          e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
            e("thead", null, e("tr", null, ["Customer", "Unit", "Milestone", "Outstanding", "Due", "Status"].map((h, i) => e("th", { key: i, style: i === 3 ? { textAlign: "right" } : {} }, h)))),
            e("tbody", null, ledgerRows.length ? ledgerRows.map((row, i) => {
              const st = row.status || "Due";
              return e("tr", { key: `${row.customer}-${row.unit}-${row.milestone}-${i}` },
                e("td", null, e("div", { className: "cell-user" }, e(Avatar, { name: row.customer, sm: true, size: 24 }), e("span", { style: { fontSize: 12.5 } }, row.customer))),
                e("td", { className: "cell-strong" }, row.unit),
                e("td", { style: { fontSize: 12.5 } }, row.milestone),
                e("td", { className: "num cell-strong" }, row.outstanding_label || row.amount_label),
                e("td", { className: "faint", style: { fontSize: 12 } }, row.due_on || "Not scheduled"),
                e("td", null, e(Badge, { tone: row.badge || "b-blue", dot: true }, st)));
            }) : [e("tr", { key: "empty" }, e("td", { colSpan: 6 }, e(Empty, { title: "No ledger rows", sub: "No scoped collection schedules are available." })))]),
          ))),
      ),
      e(Card, { title: "Collection Receipt Register", sub: "Laravel receipt workflow: submit → finance approval", action: e(Button, { sm: true, icon: "plus", variant: "primary", onClick: openReceiptModal, children: "New Receipt" }) },
        e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
          e("thead", null, e("tr", null, ["Receipt", "Customer", "Booking", "Milestone", "Amount", "Mode", "Date", "Status", "Action"].map((h, i) => e("th", { key: i, style: i === 4 ? { textAlign: "right" } : {} }, h)))),
          e("tbody", null, visibleReceipts.length ? visibleReceipts.map((receipt, i) =>
            e("tr", { key: receipt.id || receipt.receipt_number || i },
              e("td", { className: "cell-strong mono" }, receipt.receipt_number),
              e("td", null, receipt.customer?.name || "Customer pending"),
              e("td", null, receipt.booking?.booking_code || "Booking pending"),
              e("td", null, receipt.payment_schedule?.milestone || "Booking-level"),
              e("td", { className: "num cell-strong" }, formatInr(receipt.amount)),
              e("td", null, String(receipt.payment_mode || "—").toUpperCase()),
              e("td", { className: "faint" }, receipt.receipt_date || "—"),
              e("td", null, e(Badge, { tone: receipt.status === "approved" ? "b-green" : receipt.status === "submitted" ? "b-orange" : "b-red", dot: true }, receipt.status || "—")),
              e("td", null, receipt.status === "submitted" && receiptOptions?.can_approve ? e("button", { className: "link", onClick: () => approveReceipt(receipt) }, "Approve") : "—")))
            : [e("tr", { key: "empty" }, e("td", { colSpan: 9 }, e(Empty, { title: "No receipts", sub: "No scoped collection receipts are available." })))]),
        ))),
      e(Card, { title: "Payment Demand Requests", sub: "Generated through Laravel Finance Payment Request workflow", action: e(Button, { sm: true, icon: "doc", variant: "primary", onClick: openDemandModal, children: "Generate Demand" }) },
        e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
          e("thead", null, e("tr", null, ["Request", "Customer", "Booking", "Milestone", "Amount", "Expires", "Status"].map((h, i) => e("th", { key: i, style: i === 4 ? { textAlign: "right" } : {} }, h)))),
          e("tbody", null, visiblePaymentRequests.length ? visiblePaymentRequests.map((request, i) =>
            e("tr", { key: request.id || request.request_number || i },
              e("td", { className: "cell-strong mono" }, request.request_number),
              e("td", null, request.customer?.name || "Customer pending"),
              e("td", null, request.booking?.booking_code || "Booking pending"),
              e("td", null, request.payment_schedule?.milestone || "Booking-level"),
              e("td", { className: "num cell-strong" }, formatInr(request.amount)),
              e("td", { className: "faint" }, request.expires_at ? String(request.expires_at).slice(0, 10) : "—"),
              e("td", null, e(Badge, { tone: request.status === "paid" ? "b-green" : request.status === "requested" ? "b-orange" : "b-blue", dot: true }, request.status || "—"))))
            : [e("tr", { key: "empty" }, e("td", { colSpan: 7 }, e(Empty, { title: "No payment requests", sub: paymentRequestOptions?.store_url ? "Generate a customer demand to create the first payment request." : "Payment request workflow is not available for this role." })))]),
        ))),
      creatingReceipt && e(CollectionReceiptModal, { options: receiptOptions, onClose: () => setCreatingReceipt(false), onSaved: receipt => setCreatedReceipts(rows => [receipt, ...rows]), toast }),
      creatingDemand && e(CollectionDemandModal, { options: paymentRequestOptions, onClose: () => setCreatingDemand(false), onSaved: request => setCreatedPaymentRequests(rows => [request, ...rows]), toast }),
    );
  }

  function normalizeMarketingCampaignRow(row) {
    const spend = Number(row?.spend ?? row?.budget_amount ?? 0);
    const leads = Number(row?.leads ?? row?.metrics?.total_leads ?? 0);
    const bookings = Number(row?.bookings ?? row?.metrics?.bookings ?? 0);
    const revenue = Number(row?.revenue ?? 0);
    return {
      ...row,
      channel: row?.channel || "other",
      source: row?.source || row?.channel || "Campaign",
      status: row?.status || "draft",
      spend,
      spend_lakh: row?.spend_lakh !== undefined ? Number(row.spend_lakh || 0) : Math.round((spend / 100000) * 100) / 100,
      leads,
      verified: Number(row?.verified ?? 0),
      visits: Number(row?.visits ?? 0),
      bookings,
      revenue,
      roi: row?.roi !== undefined ? row.roi : (spend > 0 && revenue > 0 ? Math.round((revenue / spend) * 1000) / 10 : null),
    };
  }

  function MarketingCampaignModal({ options, onClose, onSaved, toast }) {
    const companies = options?.companies || [];
    const projects = options?.projects || [];
    const channels = options?.channels || [];
    const firstCompany = companies[0] || null;
    const [form, setForm] = React.useState({
      company_id: firstCompany?.id ? String(firstCompany.id) : "",
      project_id: "",
      name: "",
      channel: channels[0]?.value || "digital",
      source: "",
      status: "draft",
      start_on: new Date().toISOString().slice(0, 10),
      end_on: "",
      budget_amount: "",
      target_leads: "",
      target_bookings: "",
      utm_source: "",
      utm_medium: "",
      utm_campaign: "",
      audience_segment: "",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const companyProjects = projects.filter(project => !form.company_id || String(project.company_id) === String(form.company_id));
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const changeCompany = (companyId) => setForm(current => ({ ...current, company_id: companyId, project_id: "" }));
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      const budget = form.budget_amount === "" ? 0 : Number(form.budget_amount);
      const targetLeads = form.target_leads === "" ? 0 : Number(form.target_leads);
      const targetBookings = form.target_bookings === "" ? 0 : Number(form.target_bookings);
      if (!form.company_id) return setError("Select a company scope for the campaign.");
      if (!form.name.trim()) return setError("Campaign name is required.");
      if (!form.source.trim()) return setError("Lead source label is required.");
      if (form.end_on && form.end_on < form.start_on) return setError("End date cannot be earlier than start date.");
      if (!Number.isFinite(budget) || budget < 0) return setError("Budget amount cannot be negative.");
      if (!Number.isInteger(targetLeads) || targetLeads < 0) return setError("Target leads must be a whole number.");
      if (!Number.isInteger(targetBookings) || targetBookings < 0) return setError("Target bookings must be a whole number.");
      try {
        setBusy(true);
        const body = await apiJson(options.store_url, {
          method: "POST",
          body: JSON.stringify({
            company_id: Number(form.company_id),
            project_id: form.project_id ? Number(form.project_id) : null,
            name: form.name.trim(),
            channel: form.channel,
            source: form.source.trim(),
            status: form.status,
            start_on: form.start_on,
            end_on: form.end_on || null,
            budget_amount: budget,
            target_leads: targetLeads,
            target_bookings: targetBookings,
            utm_source: form.utm_source.trim() || null,
            utm_medium: form.utm_medium.trim() || null,
            utm_campaign: form.utm_campaign.trim() || null,
            audience_segment: form.audience_segment.trim() || null,
            metadata: { source: "marketing_screen" },
          }),
        });
        const saved = normalizeMarketingCampaignRow(body.data);
        onSaved(saved);
        toast("Campaign " + saved.campaign_code + " saved through Laravel marketing workflow.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(860px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "New Marketing Campaign"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates a scoped campaign with Laravel validation, attribution fields, workflow history and audit trail.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        !companies.length && e("div", { className: "empty", style: { marginBottom: 12 } }, "No company scope is available for campaign creation."),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Company", e("select", { style: field, value: form.company_id, onChange: ev => changeCompany(ev.target.value), disabled: busy || !companies.length, required: true }, companies.map(company => e("option", { key: company.id, value: company.id }, company.label || company.code)))),
          e("label", { style: label }, "Project", e("select", { style: field, value: form.project_id, onChange: ev => set("project_id", ev.target.value), disabled: busy }, e("option", { value: "" }, "Company-wide campaign"), companyProjects.map(project => e("option", { key: project.id, value: project.id }, project.label || project.code)))),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Campaign Name", e("input", { style: field, value: form.name, maxLength: 255, onChange: ev => set("name", ev.target.value), disabled: busy, required: true, placeholder: "e.g. Monsoon Home Buyer Campaign" })),
          e("label", { style: label }, "Channel", e("select", { style: field, value: form.channel, onChange: ev => set("channel", ev.target.value), disabled: busy }, channels.map(channel => e("option", { key: channel.value, value: channel.value }, channel.label)))),
          e("label", { style: label }, "Lead Source Label", e("input", { style: field, value: form.source, maxLength: 80, onChange: ev => set("source", ev.target.value), disabled: busy, required: true, placeholder: "Google Ads / Expo / Referral" })),
          e("label", { style: label }, "Status", e("select", { style: field, value: form.status, onChange: ev => set("status", ev.target.value), disabled: busy }, ["draft", "active"].map(status => e("option", { key: status, value: status }, status.toUpperCase())))),
          e("label", { style: label }, "Start Date", e("input", { style: field, type: "date", value: form.start_on, onChange: ev => set("start_on", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "End Date", e("input", { style: field, type: "date", value: form.end_on, onChange: ev => set("end_on", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Budget Amount", e("input", { style: field, type: "number", min: "0", step: "0.01", value: form.budget_amount, onChange: ev => set("budget_amount", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Target Leads", e("input", { style: field, type: "number", min: "0", step: "1", value: form.target_leads, onChange: ev => set("target_leads", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Target Bookings", e("input", { style: field, type: "number", min: "0", step: "1", value: form.target_bookings, onChange: ev => set("target_bookings", ev.target.value), disabled: busy })),
          e("label", { style: label }, "UTM Source", e("input", { style: field, value: form.utm_source, maxLength: 120, onChange: ev => set("utm_source", ev.target.value), disabled: busy })),
          e("label", { style: label }, "UTM Medium", e("input", { style: field, value: form.utm_medium, maxLength: 120, onChange: ev => set("utm_medium", ev.target.value), disabled: busy })),
          e("label", { style: label }, "UTM Campaign", e("input", { style: field, value: form.utm_campaign, maxLength: 120, onChange: ev => set("utm_campaign", ev.target.value), disabled: busy })),
          e("label", { style: label }, "Audience Segment", e("input", { style: field, value: form.audience_segment, maxLength: 160, onChange: ev => set("audience_segment", ev.target.value), disabled: busy }))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, marginTop: 4, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Campaign response and ROI will update as leads, activities and bookings are attributed to this campaign."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "plus", type: "submit", disabled: busy || !companies.length, children: busy ? "Saving…" : "Save Campaign" })))));
  }

  // ---------------- MARKETING ----------------
  function Marketing({ toast }) {
    const marketingMetrics = window.Builder360Server?.marketing_metrics || { source: "unavailable", summary: {}, campaigns: [] };
    const [campaigns, setCampaigns] = React.useState((marketingMetrics.campaigns || []).map(normalizeMarketingCampaignRow));
    const [summary, setSummary] = React.useState(marketingMetrics.summary || {});
    const [creating, setCreating] = React.useState(false);
    const crore = value => Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 2 });
    const money = value => value === null || value === undefined ? "—" : "₹" + Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
    const lakh = value => value === null || value === undefined ? "—" : "₹" + Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 2 });
    const openCampaign = () => {
      if (!marketingMetrics?.can_create || !marketingMetrics?.store_url) {
        toast("Campaign creation is not available for your role or company scope.", "orange");
        return;
      }
      setCreating(true);
    };
    const addCampaign = (campaign) => {
      const row = normalizeMarketingCampaignRow(campaign);
      setCampaigns(current => [row, ...current]);
      setSummary(current => {
        const spend = Number(row.spend || 0);
        const campaignCount = Number(current.campaign_count || 0) + 1;
        const marketingSpend = Number(current.marketing_spend || 0) + spend;
        const leads = Number(current.leads || 0);
        const bookings = Number(current.bookings || 0);
        return {
          ...current,
          campaign_count: campaignCount,
          marketing_spend: Math.round(marketingSpend * 100) / 100,
          marketing_spend_crore: Math.round((marketingSpend / 10000000) * 100) / 100,
          cost_per_lead: leads > 0 ? Math.round((marketingSpend / leads) * 100) / 100 : current.cost_per_lead ?? null,
          cost_per_booking: bookings > 0 ? Math.round((marketingSpend / bookings) * 100) / 100 : current.cost_per_booking ?? null,
          cost_per_booking_lakh: bookings > 0 ? Math.round(((marketingSpend / bookings) / 100000) * 100) / 100 : current.cost_per_booking_lakh ?? null,
        };
      });
    };
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Sales & CRM", "Marketing"], title: "Marketing Analytics", sub: "Campaign spend, lead source performance and marketing ROI.",
        actions: e(Button, { icon: "plus", variant: "primary", onClick: openCampaign, children: "New Campaign" }) }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Marketing Spend (FY)", value: "₹" + crore(summary.marketing_spend_crore), unit: "Cr", icon: "mega", tone: "accent", sub: `${marketingMetrics.source || "server"} scoped` }),
        e(Stat, { label: "Cost / Lead", value: money(summary.cost_per_lead), icon: "users", tone: "blue", sub: `${formatCount(summary.leads)} lead(s)` }),
        e(Stat, { label: "Cost / Booking", value: lakh(summary.cost_per_booking_lakh), unit: summary.cost_per_booking_lakh === null || summary.cost_per_booking_lakh === undefined ? "" : "L", icon: "tag", tone: "violet", sub: `${formatCount(summary.bookings)} booking(s)` }),
        e(Stat, { label: "Blended ROI", value: summary.blended_roi === null || summary.blended_roi === undefined ? "—" : String(summary.blended_roi), unit: summary.blended_roi === null || summary.blended_roi === undefined ? "" : "%", icon: "trend", tone: summary.blended_roi >= 250 ? "green" : summary.blended_roi === null || summary.blended_roi === undefined ? "slate" : "orange", sub: `${formatCount(summary.campaign_count)} campaign(s)` }),
      ),
      e(Card, { title: "Campaign Performance", sub: "Spend in ₹ Lakh · ROI = revenue / spend" }, e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" },
        e("thead", null, e("tr", null, ["Campaign", "Channel", "Spend", "Leads", "Verified", "Visits", "Bookings", "ROI"].map((h, i) => e("th", { key: i, style: i > 1 && i < 7 ? { textAlign: "right" } : {} }, h)))),
        e("tbody", null, campaigns.length ? campaigns.map((c, i) =>
          e("tr", { key: c.campaign_code || i },
            e("td", { className: "cell-strong" }, c.name),
            e("td", null, e("span", { className: "tag" }, c.channel || c.source || "Campaign")),
            e("td", { className: "num mono" }, lakh(c.spend_lakh) + " L"),
            e("td", { className: "num mono" }, formatCount(c.leads)),
            e("td", { className: "num mono" }, formatCount(c.verified)),
            e("td", { className: "num mono" }, formatCount(c.visits)),
            e("td", { className: "num mono cell-strong" }, formatCount(c.bookings)),
            e("td", null, e(Badge, { tone: c.roi >= 400 ? "b-green" : c.roi >= 250 ? "b-orange" : c.roi === null || c.roi === undefined ? "b-slate" : "b-red" }, c.roi === null || c.roi === undefined ? "—" : c.roi + "%"))))
          : [e("tr", { key: "empty" }, e("td", { colSpan: 8 }, e(Empty, { title: "No campaign metrics", sub: "No scoped marketing campaigns are available." })))]),
      ))),
      creating && e(MarketingCampaignModal, { options: marketingMetrics, onClose: () => setCreating(false), onSaved: addCampaign, toast }),
    );
  }

  Object.assign(window, { Leads, Qualification, Sales, Collections, Marketing });
})();
