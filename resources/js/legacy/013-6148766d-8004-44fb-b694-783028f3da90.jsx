const React = window.React;

/* Builder360 — Buyer Portal, Prospect Inquiry, Mobile Apps */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, BarChart, LineChart, Gauge, Spark, HBars, PageHead, ChipSelect, Seg, Empty } = window;
  const e = React.createElement;
  const DB = window.DB;

  // ---------------- BUYER PORTAL (clean, customer-friendly) ----------------
  function BuyerPortal({ toast }) {
    const initialBuyer = window.Builder360Server?.buyer_portal || null;
    const [serverBuyer, setServerBuyer] = React.useState(initialBuyer);
    const [paymentRequests, setPaymentRequests] = React.useState([]);
    const [busy, setBusy] = React.useState(false);
    const [payingRequest, setPayingRequest] = React.useState(null);
    const [paymentForm, setPaymentForm] = React.useState({ payment_mode: "upi", instrument_number: "", gateway_response_code: "" });
    const [ticketOpen, setTicketOpen] = React.useState(false);
    const [ticketForm, setTicketForm] = React.useState({ category: "maintenance", priority: "medium", subject: "", description: "" });
    const hasServerBuyer = !!serverBuyer;
    const endpoints = serverBuyer?.endpoints || {};
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    const firstApiError = payload => {
      const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
      return errors[0] || payload?.message || "The buyer portal request could not be completed.";
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
    const collectionUrl = (url, params = {}) => {
      const next = new URL(url, window.location.origin);
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== "") next.searchParams.set(key, value);
      });
      return next.toString();
    };
    const refreshPortal = React.useCallback(() => {
      if (!endpoints.summary_url) return Promise.resolve(null);
      return apiJson(endpoints.summary_url).then(body => {
        setServerBuyer(current => ({ ...(body.data || {}), endpoints: current?.endpoints || endpoints }));
        return body.data;
      });
    }, [endpoints.summary_url]);
    const refreshPaymentRequests = React.useCallback(() => {
      if (!endpoints.payment_requests_url) return Promise.resolve([]);
      return apiJson(collectionUrl(endpoints.payment_requests_url, { per_page: 20 })).then(body => {
        const rows = body.data || [];
        setPaymentRequests(rows);
        return rows;
      });
    }, [endpoints.payment_requests_url]);
    React.useEffect(() => {
      let alive = true;
      if (!endpoints.summary_url && !endpoints.payment_requests_url) return;
      Promise.all([
        endpoints.summary_url ? apiJson(endpoints.summary_url).catch(error => ({ error })) : Promise.resolve(null),
        endpoints.payment_requests_url ? apiJson(collectionUrl(endpoints.payment_requests_url, { per_page: 20 })).catch(error => ({ error })) : Promise.resolve(null),
      ]).then(([summaryBody, requestsBody]) => {
        if (!alive) return;
        if (summaryBody?.data) setServerBuyer(current => ({ ...summaryBody.data, endpoints: current?.endpoints || endpoints }));
        if (requestsBody?.data) setPaymentRequests(requestsBody.data || []);
        const error = summaryBody?.error || requestsBody?.error;
        if (error) toast("Buyer portal stayed on current MySQL bootstrap: " + error.message, "orange");
      });
      return () => { alive = false; };
    }, [endpoints.summary_url, endpoints.payment_requests_url]);
    const moneyL = value => "₹" + (Number(value || 0) / 100000).toLocaleString("en-IN", { maximumFractionDigits: 1 }) + " L";
    const scheduleTone = status => ({ paid: "b-green", pending: "b-orange", due: "b-orange", overdue: "b-red", requested: "b-blue" }[String(status || "").toLowerCase()] || "b-slate");
    const scheduleRows = hasServerBuyer ? (serverBuyer.payment_schedule || []).map(s => ({
      milestone: s.milestone,
      bookingCode: s.booking_code,
      rawStatus: String(s.status || "pending").toLowerCase(),
      pct: (Number(s.percentage || 0)).toFixed(0) + "%",
      amtDisplay: moneyL(s.amount),
      due: s.due_on || "Not set",
      status: String(s.status || "pending").replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase()),
      b: scheduleTone(s.status),
    })) : [];
    const paid = hasServerBuyer ? Number(serverBuyer.approved_receipts_total || 0) / 100000 : 0;
    const total = hasServerBuyer ? Number(serverBuyer.scheduled_payments_total || 0) / 100000 : 0;
    const outstanding = hasServerBuyer ? Number(serverBuyer.outstanding_amount || 0) / 100000 : 0;
    const nextDue = serverBuyer?.next_due || null;
    const currentBooking = serverBuyer?.recent_bookings?.[0] || null;
    const documents = hasServerBuyer ? (serverBuyer.documents || []) : [];
    const tickets = hasServerBuyer ? (serverBuyer.service_tickets || []) : [];
    const unitTitle = currentBooking?.unit?.unit_code || (hasServerBuyer ? "Booking pending" : "Buyer portal API required");
    const unitSub = currentBooking ? [currentBooking.project?.name, currentBooking.unit?.tower, currentBooking.unit?.floor ? currentBooking.unit.floor + "th Floor" : null, currentBooking.unit?.unit_type].filter(Boolean).join(" · ") : (hasServerBuyer ? "No active booking found in current scope" : "No authorized buyer booking payload loaded.");
    const progressBadge = hasServerBuyer ? (currentBooking?.status || "Active") : "API REQUIRED";
    const paidPercent = total > 0 ? Math.round(paid / total * 100) : 0;
    const paymentForSchedule = row => paymentRequests.find(pr => ["requested", "approved"].includes(String(pr.status || "").toLowerCase()) && (!row.bookingCode || pr.booking?.booking_code === row.bookingCode) && (!row.milestone || pr.payment_schedule?.milestone === row.milestone));
    const openPaymentModal = request => {
      const template = endpoints.payment_request_pay_url_template;
      if (!template || !request?.id) {
        toast("Buyer payment action is unavailable because the payment request endpoint is not configured.", "orange");
        return;
      }
      setPaymentForm({ payment_mode: "upi", instrument_number: "SIM-" + Date.now(), gateway_response_code: "" });
      setPayingRequest(request);
    };
    const submitPayment = ev => {
      ev.preventDefault();
      const request = payingRequest;
      const template = endpoints.payment_request_pay_url_template;
      if (!template || !request?.id) {
        toast("Buyer payment action is unavailable because the payment request endpoint is not configured.", "orange");
        return;
      }
      if (!paymentForm.payment_mode || !paymentForm.instrument_number.trim()) {
        toast("Payment mode and reference are required.", "orange");
        return;
      }
      setBusy(true);
      apiJson(template.replace("__PAYMENT_REQUEST__", request.id), {
        method: "PATCH",
        body: JSON.stringify({
          payment_mode: paymentForm.payment_mode,
          instrument_number: paymentForm.instrument_number.trim(),
          gateway_response_code: paymentForm.gateway_response_code.trim() || undefined,
        }),
      })
        .then(body => {
          toast("Payment request " + body.data.request_number + " paid through the buyer portal.", "green");
          setPayingRequest(null);
          return Promise.all([refreshPortal(), refreshPaymentRequests()]);
        })
        .catch(error => toast(error.message, "red"))
        .finally(() => setBusy(false));
    };
    const openTicketModal = () => {
      if (!endpoints.service_tickets_store_url || !currentBooking?.id) {
        toast("Service request creation requires a confirmed MySQL booking for this buyer.", "orange");
        return;
      }
      setTicketForm({ category: "maintenance", priority: "medium", subject: "", description: "" });
      setTicketOpen(true);
    };
    const submitTicket = ev => {
      ev.preventDefault();
      if (!endpoints.service_tickets_store_url || !currentBooking?.id) {
        toast("Service request creation requires a confirmed MySQL booking for this buyer.", "orange");
        return;
      }
      if (!ticketForm.subject.trim() || ticketForm.description.trim().length < 10) {
        toast("Subject and at least 10 characters of request details are required.", "orange");
        return;
      }
      setBusy(true);
      apiJson(endpoints.service_tickets_store_url, {
        method: "POST",
        body: JSON.stringify({
          booking_id: currentBooking.id,
          category: ticketForm.category,
          priority: ticketForm.priority,
          source: "portal",
          subject: ticketForm.subject.trim(),
          description: ticketForm.description.trim(),
        }),
      })
        .then(body => {
          toast("Service ticket " + body.data.ticket_number + " created from buyer portal.", "green");
          setTicketOpen(false);
          return refreshPortal();
        })
        .catch(error => toast(error.message, "red"))
        .finally(() => setBusy(false));
    };
    const paymentRows = scheduleRows.map((s, i) => {
      const request = paymentForSchedule(s);
      const action = s.rawStatus === "paid"
        ? e(Badge, { tone: "b-green" }, "Receipt in Documents")
        : request
          ? e(Button, { sm: true, variant: "primary", disabled: busy, onClick: () => openPaymentModal(request), children: "Pay Request" })
          : e("span", { className: "cell-sub" }, "No active payment link");

      return e("tr", { key: i },
        e("td", { className: "cell-strong" }, s.milestone),
        e("td", { className: "faint" }, s.pct),
        e("td", { className: "num cell-strong" }, s.amtDisplay),
        e("td", { className: "faint", style: { fontSize: 12.5 } }, s.due),
        e("td", null, e(Badge, { tone: s.b, dot: true }, s.status)),
        e("td", null, action));
    });
    const documentSource = hasServerBuyer ? documents : [];
    const documentRows = !hasServerBuyer
      ? e(Empty, { icon: "doc", title: "Buyer document API required", sub: "No local buyer documents are fabricated." })
      : documents.length === 0
      ? e(Empty, { icon: "doc", title: "No approved buyer documents", sub: "Approved customer and booking documents will appear here." })
      : documentSource.map((d, i) => {
        const doc = Array.isArray(d) ? { title: d[0], category: d[1] } : d;
        return e("div", { key: doc.id || i, className: "nav-item", style: { height: 44 } },
          e("div", { style: { width: 32, height: 32, borderRadius: 8, background: "var(--surface-3)", color: "var(--accent)", display: "grid", placeItems: "center", flex: "0 0 32px" } }, e(Icon, { name: "doc", size: 16 })),
          e("div", { style: { flex: 1 } },
            e("div", { style: { fontWeight: 600, fontSize: 13 } }, doc.title),
            e("div", { className: "cell-sub" }, [doc.document_number, doc.category, doc.version ? "v" + doc.version : null].filter(Boolean).join(" · ") || "Approved document")),
          doc.download_url
            ? e("button", { className: "icon-btn", title: "Download", onClick: () => window.location.assign(doc.download_url) }, e(Icon, { name: "download", size: 15 }))
            : e(Badge, { tone: "b-orange" }, "Preview"));
      });
    const ticketRows = tickets.length === 0
      ? e("div", { className: "cell-sub" }, "No buyer-visible service tickets found.")
      : tickets.slice(0, 4).map(ticket => e("div", { key: ticket.id, className: "nav-item", style: { minHeight: 44 } },
        e("div", { style: { width: 32, height: 32, borderRadius: 8, background: "var(--surface-3)", color: "var(--orange)", display: "grid", placeItems: "center", flex: "0 0 32px" } }, e(Icon, { name: "headset", size: 16 })),
        e("div", { style: { flex: 1 } },
          e("div", { style: { fontWeight: 600, fontSize: 13 } }, ticket.subject),
          e("div", { className: "cell-sub" }, [ticket.ticket_number, ticket.category, ticket.priority].filter(Boolean).join(" · "))),
        e(Badge, { tone: ["open", "assigned", "in_progress"].includes(ticket.status) ? "b-orange" : "b-green" }, String(ticket.status || "").replace(/_/g, " "))));
    const modalField = { display: "grid", gap: 5, fontSize: 12.5, fontWeight: 700, color: "var(--text-2)" };
    const modalInput = { height: 38, border: "1px solid var(--border)", borderRadius: 9, background: "var(--surface)", color: "var(--text)", padding: "0 10px", fontFamily: "inherit" };
    const modalText = Object.assign({}, modalInput, { height: 92, padding: 10, resize: "vertical" });
    const paymentModal = payingRequest ? e("div", { className: "scrim", onClick: busy ? undefined : () => setPayingRequest(null) },
      e("form", { className: "modal", style: { width: 560, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submitPayment },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, "Pay Request"), e("p", { className: "muted" }, [payingRequest.request_number, payingRequest.payment_schedule?.milestone, moneyL(payingRequest.amount)].filter(Boolean).join(" · "))),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: () => setPayingRequest(null) }, e(Icon, { name: "x" }))),
        e("div", { style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          e("label", { style: modalField }, "Payment Mode", e("select", { style: modalInput, value: paymentForm.payment_mode, disabled: busy, onChange: ev => setPaymentForm(prev => ({ ...prev, payment_mode: ev.target.value })) },
            [["upi", "UPI"], ["card", "Card"], ["netbanking", "Net Banking"], ["wallet", "Wallet"]].map(([value, text]) => e("option", { key: value, value }, text)))),
          e("label", { style: modalField }, "Gateway Code", e("input", { style: modalInput, maxLength: 80, value: paymentForm.gateway_response_code, disabled: busy, onChange: ev => setPaymentForm(prev => ({ ...prev, gateway_response_code: ev.target.value })), placeholder: "Optional" })),
          e("label", { style: Object.assign({}, modalField, { gridColumn: "1 / -1" }) }, "Payment Reference / UTR", e("input", { style: modalInput, required: true, maxLength: 120, value: paymentForm.instrument_number, disabled: busy, onChange: ev => setPaymentForm(prev => ({ ...prev, instrument_number: ev.target.value })), placeholder: "UPI / card / bank reference" }))),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: busy, onClick: () => setPayingRequest(null), children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: busy }, e(Icon, { name: "rupee", size: 15 }), busy ? "Posting..." : "Confirm Payment")))) : null;
    const ticketModal = ticketOpen ? e("div", { className: "scrim", onClick: busy ? undefined : () => setTicketOpen(false) },
      e("form", { className: "modal", style: { width: 660, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submitTicket },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, "New Service Request"), e("p", { className: "muted" }, "Creates a scoped Laravel service ticket against " + (currentBooking?.booking_code || "your confirmed booking") + ".")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: () => setTicketOpen(false) }, e(Icon, { name: "x" }))),
        e("div", { style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          e("label", { style: modalField }, "Category", e("select", { style: modalInput, value: ticketForm.category, disabled: busy, onChange: ev => setTicketForm(prev => ({ ...prev, category: ev.target.value })) },
            [["defect", "Defect"], ["maintenance", "Maintenance"], ["billing", "Billing"], ["documentation", "Documentation"], ["society", "Society"], ["other", "Other"]].map(([value, text]) => e("option", { key: value, value }, text)))),
          e("label", { style: modalField }, "Priority", e("select", { style: modalInput, value: ticketForm.priority, disabled: busy, onChange: ev => setTicketForm(prev => ({ ...prev, priority: ev.target.value })) },
            [["low", "Low"], ["medium", "Medium"], ["high", "High"], ["critical", "Critical"]].map(([value, text]) => e("option", { key: value, value }, text)))),
          e("label", { style: Object.assign({}, modalField, { gridColumn: "1 / -1" }) }, "Subject", e("input", { style: modalInput, required: true, maxLength: 255, value: ticketForm.subject, disabled: busy, onChange: ev => setTicketForm(prev => ({ ...prev, subject: ev.target.value })), placeholder: "Short request subject" })),
          e("label", { style: Object.assign({}, modalField, { gridColumn: "1 / -1" }) }, "Details", e("textarea", { style: modalText, required: true, minLength: 10, maxLength: 5000, value: ticketForm.description, disabled: busy, onChange: ev => setTicketForm(prev => ({ ...prev, description: ev.target.value })), placeholder: "Describe the issue, location, preferred contact time, and supporting context." }))),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: busy, onClick: () => setTicketOpen(false), children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: busy }, e(Icon, { name: "headset", size: 15 }), busy ? "Submitting..." : "Submit Request")))) : null;

    return e("div", { className: "page", style: { maxWidth: 1180 } },
      e("div", { className: "card", style: { overflow: "hidden", marginBottom: 20 } },
        e("div", { style: { height: 96, background: "var(--accent-grad)", position: "relative" } },
          e("div", { style: { position: "absolute", inset: 0, opacity: .15, background: "radial-gradient(400px 200px at 80% 0%, #fff, transparent)" } })),
        e("div", { style: { padding: "0 24px 22px", marginTop: -34 } },
          e("div", { className: "row between", style: { alignItems: "flex-end", flexWrap: "wrap", gap: 14 } },
            e("div", { className: "row gap-4", style: { alignItems: "flex-end" } },
              e("div", { style: { width: 76, height: 76, borderRadius: 18, background: "var(--surface)", border: "4px solid var(--surface)", display: "grid", placeItems: "center", boxShadow: "var(--shadow-md)" } }, e(Icon, { name: "home", size: 34, style: { color: "var(--accent)" } })),
              e("div", { style: { paddingBottom: 4 } }, e("h1", { className: "page-title" }, unitTitle), e("div", { className: "muted", style: { fontWeight: 600 } }, unitSub))),
            e(Badge, { tone: hasServerBuyer ? "b-green" : "b-accent", dot: true }, hasServerBuyer ? "SERVER SCOPED · " + progressBadge : progressBadge)))),
      e("div", { className: "grid g-4", style: { marginBottom: 20 } },
        e(Stat, { label: "Total Consideration", value: "₹" + total.toFixed(1), unit: "L", icon: "rupee", tone: "accent" }),
        e(Stat, { label: "Amount Paid", value: "₹" + paid.toFixed(1), unit: "L", icon: "check", tone: "green", sub: paidPercent + "% complete" }),
        e(Stat, { label: "Outstanding", value: "₹" + outstanding.toFixed(1), unit: "L", icon: "clock", tone: "orange" }),
        e(Stat, { label: "Next Due", value: nextDue ? moneyL(nextDue.amount).replace(" L", "") : "—", unit: nextDue ? "L" : "", icon: "calendar", tone: "blue", sub: nextDue ? nextDue.milestone + " · " + nextDue.due_on : (hasServerBuyer ? "No upcoming due" : "Buyer API required") })),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.5fr 1fr", alignItems: "start" } },
        e("div", { className: "grid", style: { gap: 20 } },
          e(Card, { title: "Payment Schedule", sub: hasServerBuyer ? "Construction-linked plan" : "Buyer API required", action: e(Badge, { tone: hasServerBuyer ? "b-green" : "b-orange", dot: true }, hasServerBuyer ? "MySQL scoped" : "API REQUIRED") },
            scheduleRows.length
              ? e("div", { className: "tbl-wrap" },
                e("table", { className: "tbl" },
                  e("thead", null, e("tr", null, ["Milestone", "%", "Amount", "Due Date", "Status", ""].map((h, i) => e("th", { key: i, style: i === 2 ? { textAlign: "right" } : {} }, h)))),
                  e("tbody", null, paymentRows)))
              : e(Empty, { icon: "calendar", title: "No payment schedule", sub: hasServerBuyer ? "No payment milestones are visible for this buyer." : "Buyer portal API required; no local payment schedule is fabricated." })),
          e(Card, { title: "Construction Progress", sub: hasServerBuyer ? "Project status from scoped booking record" : "Buyer API required", pad: true },
            e("div", { className: "sys-note", style: { margin: 0 } },
              e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
              hasServerBuyer
                ? "Buyer-visible construction milestone feed and site-photo publishing are not configured in this build. The portal shows only the authorized booking/project status from MySQL."
                : "Buyer portal API required before construction progress can be shown; no local construction preview is fabricated."))),
        e("div", { className: "grid", style: { gap: 20 } },
          e(Card, { title: "Documents", pad: true }, documentRows),
          e(Card, { title: "Raise a Request", pad: true },
            e("div", { className: "muted", style: { fontSize: 13, marginBottom: 12 } }, hasServerBuyer ? "Create a scoped service ticket against your confirmed booking. SLA and ownership are enforced by Laravel." : "Buyer portal API required before service requests can be created."),
            e(Button, { variant: "primary", icon: "headset", disabled: busy || !hasServerBuyer, onClick: openTicketModal, children: "New Service Request" }),
            e("div", { className: "divider" }),
            e("div", { className: "row between", style: { fontSize: 13, marginBottom: 10 } }, e("span", { className: "muted" }, "Open requests"), e(Badge, { tone: "b-orange" }, (serverBuyer?.open_tickets_count || 0) + " in progress")),
            ticketRows))),
      paymentModal,
      ticketModal);
  }

  // ---------------- PROSPECT INQUIRY PORTAL ----------------
  function Inquiry({ toast }) {
    const options = window.Builder360Server?.prospect_inquiry_options || null;
    const projects = options?.projects || [];
    const channels = options?.channels || [];
    const contactMethods = options?.contact_methods || [];
    const metrics = options?.metrics || {};
    const [busy, setBusy] = React.useState(false);
    const [submitted, setSubmitted] = React.useState(null);
    const [form, setForm] = React.useState(() => ({
      project_id: projects[0]?.id ? String(projects[0].id) : "",
      name: "",
      phone: "",
      email: "",
      source: "Website",
      channel: "website",
      preferred_contact_method: "phone",
      budget_min: "",
      budget_max: "",
      message: "",
      consent_to_contact: false,
      utm_source: "",
      utm_medium: "",
      utm_campaign: "",
    }));
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    const firstApiError = payload => {
      const errors = payload && payload.errors ? Object.values(payload.errors).flat() : [];
      return errors[0] || payload?.message || "Prospect inquiry could not be submitted.";
    };
    const patch = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const moneyShort = value => {
      const amount = Number(value || 0);
      if (!amount) return "Price on request";
      return "From ₹" + (amount / 10000000).toLocaleString("en-IN", { maximumFractionDigits: 2 }) + " Cr";
    };
    const submitInquiry = ev => {
      ev.preventDefault();
      if (!options?.store_url) {
        toast("Prospect Inquiry API is not available for this user/session.", "orange");
        return;
      }
      if (!form.project_id || !form.name.trim() || (!form.phone.trim() && !form.email.trim()) || !form.consent_to_contact) {
        toast("Project, name, phone or email, and contact consent are required.", "orange");
        return;
      }
      setBusy(true);
      fetch(options.store_url, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify({
          project_id: Number(form.project_id),
          name: form.name.trim(),
          phone: form.phone.trim() || undefined,
          email: form.email.trim() || undefined,
          source: form.source.trim() || "Website",
          channel: form.channel,
          preferred_contact_method: form.preferred_contact_method,
          budget_min: form.budget_min === "" ? undefined : Number(form.budget_min),
          budget_max: form.budget_max === "" ? undefined : Number(form.budget_max),
          message: form.message.trim() || undefined,
          consent_to_contact: form.consent_to_contact,
          utm_source: form.utm_source.trim() || undefined,
          utm_medium: form.utm_medium.trim() || undefined,
          utm_campaign: form.utm_campaign.trim() || undefined,
        }),
      })
        .then(async response => {
          const body = await response.json().catch(() => ({}));
          if (!response.ok) throw new Error(firstApiError(body));
          return body;
        })
        .then(body => {
          setSubmitted(body.data || null);
          toast("Prospect inquiry " + (body.data?.inquiry_number || "") + " captured in Laravel CRM.", "green");
          setForm(prev => ({
            ...prev,
            name: "",
            phone: "",
            email: "",
            budget_min: "",
            budget_max: "",
            message: "",
            consent_to_contact: false,
            utm_source: "",
            utm_medium: "",
            utm_campaign: "",
          }));
        })
        .catch(error => toast(error.message, "red"))
        .finally(() => setBusy(false));
    };
    const field = { display: "grid", gap: 5, fontSize: 12.5, fontWeight: 700, color: "var(--text-2)" };
    const input = { height: 38, borderRadius: 9, border: "1px solid var(--border)", background: "var(--surface)", color: "var(--text)", padding: "0 11px", fontFamily: "inherit" };
    const textarea = Object.assign({}, input, { height: 96, padding: 10, resize: "vertical" });
    const projectCards = !options
      ? e(Empty, { icon: "globe", title: "Prospect Inquiry API unavailable", sub: "This screen requires the Laravel prospect inquiry bootstrap contract before public lead capture can be shown." })
      : projects.length === 0
        ? e(Empty, { icon: "building", title: "No active public projects", sub: "No active projects are visible in the current company/project scope for inquiry capture." })
        : projects.slice(0, 6).map(p =>
          e("button", { key: p.id, type: "button", className: "card", onClick: () => patch("project_id", String(p.id)), style: { overflow: "hidden", textAlign: "left", cursor: "pointer", borderColor: String(form.project_id) === String(p.id) ? "var(--accent)" : "var(--border)" } },
            e("div", { style: { height: 112, background: "linear-gradient(135deg, var(--accent-soft), var(--surface-2))", position: "relative", display: "grid", placeItems: "center" } },
              e(Icon, { name: "building", size: 36, style: { color: "var(--accent)", opacity: .6 } }),
              e("span", { className: "badge " + (p.available_units > 0 ? "b-green" : "b-orange"), style: { position: "absolute", top: 12, left: 12 } }, p.available_units + " available")),
            e("div", { className: "card-pad" },
              e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 16 } }, p.name),
              e("div", { className: "cell-sub", style: { marginBottom: 10 } }, e(Icon, { name: "pin", size: 11, style: { verticalAlign: -1 } }), " " + [p.city, p.state].filter(Boolean).join(", ")),
              e("div", { className: "row gap-2", style: { marginBottom: 12, flexWrap: "wrap" } }, e("span", { className: "tag" }, p.project_type || "Project"), e("span", { className: "tag" }, p.code), e("span", { className: "tag" }, moneyShort(p.starting_price))),
              e(Badge, { tone: String(form.project_id) === String(p.id) ? "b-accent" : "b-slate", dot: true }, String(form.project_id) === String(p.id) ? "Selected" : "Select"))));

    return e("div", { className: "page", style: { maxWidth: 1180 } },
      e(PageHead, { crumbs: ["Customer", "Prospect Inquiry Portal"], title: "Prospect Inquiry Portal", sub: "Public-facing project discovery backed by Laravel prospect inquiry capture, validation, duplicate detection, audit and CRM follow-up.",
        actions: e(Badge, { tone: options ? "b-green" : "b-orange", dot: true }, options ? "Laravel API" : "API REQUIRED") }),
      e("div", { className: "grid g-3", style: { marginBottom: 20 } }, projectCards),
      e("div", { className: "grid", style: { gridTemplateColumns: "1fr 1fr", alignItems: "start" } },
        e(Card, { title: "Inquiry Form", sub: "Submits to the Laravel public prospect inquiry route", pad: true },
          e("form", { onSubmit: submitInquiry, className: "grid g-2", style: { gap: 12 } },
            e("label", { style: field }, "Preferred Project",
              e("select", { style: input, required: true, value: form.project_id, disabled: busy || !options || projects.length === 0, onChange: ev => patch("project_id", ev.target.value) },
                e("option", { value: "" }, "Select active project"),
                projects.map(p => e("option", { key: p.id, value: p.id }, p.label || p.name)))),
            e("label", { style: field }, "Full Name",
              e("input", { style: input, required: true, maxLength: 255, value: form.name, disabled: busy || !options, onChange: ev => patch("name", ev.target.value), placeholder: "Prospect full name" })),
            e("label", { style: field }, "Mobile Number",
              e("input", { style: input, maxLength: 32, value: form.phone, disabled: busy || !options, onChange: ev => patch("phone", ev.target.value), placeholder: "Required if email is blank" })),
            e("label", { style: field }, "Email",
              e("input", { style: input, type: "email", maxLength: 255, value: form.email, disabled: busy || !options, onChange: ev => patch("email", ev.target.value), placeholder: "Required if phone is blank" })),
            e("div", { style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
              e("label", { style: field }, "Budget Min",
                e("input", { style: input, type: "number", min: 0, value: form.budget_min, disabled: busy || !options, onChange: ev => patch("budget_min", ev.target.value), placeholder: "₹" })),
              e("label", { style: field }, "Budget Max",
                e("input", { style: input, type: "number", min: 0, value: form.budget_max, disabled: busy || !options, onChange: ev => patch("budget_max", ev.target.value), placeholder: "₹" }))),
            e("div", { style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
              e("label", { style: field }, "Channel",
                e("select", { style: input, value: form.channel, disabled: busy || !options, onChange: ev => patch("channel", ev.target.value) },
                  channels.map(row => e("option", { key: row.value, value: row.value }, row.label)))),
              e("label", { style: field }, "Preferred Contact",
                e("select", { style: input, value: form.preferred_contact_method, disabled: busy || !options, onChange: ev => patch("preferred_contact_method", ev.target.value) },
                  contactMethods.map(row => e("option", { key: row.value, value: row.value }, row.label))))),
            e("label", { style: field }, "Source",
              e("input", { style: input, maxLength: 80, value: form.source, disabled: busy || !options, onChange: ev => patch("source", ev.target.value), placeholder: "Website / Campaign / Referral" })),
            e("div", { style: { display: "grid", gridTemplateColumns: "repeat(3, minmax(0, 1fr))", gap: 12 } },
              e("label", { style: field }, "UTM Source", e("input", { style: input, maxLength: 120, value: form.utm_source, disabled: busy || !options, onChange: ev => patch("utm_source", ev.target.value) })),
              e("label", { style: field }, "UTM Medium", e("input", { style: input, maxLength: 120, value: form.utm_medium, disabled: busy || !options, onChange: ev => patch("utm_medium", ev.target.value) })),
              e("label", { style: field }, "UTM Campaign", e("input", { style: input, maxLength: 120, value: form.utm_campaign, disabled: busy || !options, onChange: ev => patch("utm_campaign", ev.target.value) }))),
            e("label", { style: field }, "Message",
              e("textarea", { style: textarea, maxLength: 2000, value: form.message, disabled: busy || !options, onChange: ev => patch("message", ev.target.value), placeholder: "Requirement, visit preference, unit interest, or notes." })),
            e("label", { className: "row gap-2", style: { fontSize: 12.5, fontWeight: 700, color: "var(--text-2)", alignItems: "center" } },
              e("input", { type: "checkbox", checked: form.consent_to_contact, disabled: busy || !options, onChange: ev => patch("consent_to_contact", ev.target.checked) }),
              "Consent received to contact this prospect"),
            e("button", { className: "btn btn-primary", type: "submit", disabled: busy || !options || projects.length === 0 }, e(Icon, { name: "send", size: 15 }), busy ? "Submitting..." : "Submit Inquiry"))),
        e(Card, { title: "Inquiry Pipeline", sub: "Real counts from the scoped Laravel prospect inquiry register", pad: true },
          e("div", { style: { display: "flex", flexDirection: "column", gap: 14 } },
            [
              ["Inquiries captured (30d)", metrics.captured_30d ?? 0, "globe", "var(--blue)"],
              ["Open CRM follow-up", metrics.open ?? 0, "users", "var(--accent)"],
              ["Converted to leads (30d)", metrics.converted_30d ?? 0, "check", "var(--green)"],
              ["Duplicates detected (30d)", metrics.duplicates_30d ?? 0, "alert", "var(--orange)"],
            ].map((r, i) =>
              e("div", { key: i, className: "row between", style: { padding: "12px 14px", background: "var(--surface-2)", borderRadius: 12, border: "1px solid var(--border)" } },
                e("div", { className: "row gap-3" }, e("div", { style: { width: 36, height: 36, borderRadius: 10, background: "var(--surface-3)", color: r[3], display: "grid", placeItems: "center" } }, e(Icon, { name: r[2], size: 18 })), e("span", { style: { fontWeight: 600, fontSize: 13.5 } }, r[0])),
                e("span", { className: "mono", style: { fontWeight: 800, fontSize: 18 } }, String(r[1])))),
            submitted && e("div", { className: "sys-note", style: { margin: 0 } },
              e(Icon, { name: "check", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
              "Last captured inquiry: " + submitted.inquiry_number + " · " + submitted.status))),
      ),
    );
  }

  // ---------------- MOBILE APPS ----------------
  function PhoneFrame({ children, label }) {
    return e("div", { style: { textAlign: "center" } },
      e("div", { className: "phone" }, e("div", { className: "phone-notch" }),
        e("div", { className: "phone-screen" },
          e("div", { className: "phone-status" }, e("span", null, "9:41"), e("span", null, e(Icon, { name: "trend", size: 13 }))),
          children)),
      e("div", { style: { marginTop: 14, fontWeight: 700, fontSize: 13 } }, label));
  }
  function MobileApps() {
    const options = window.Builder360Server?.mobile_journey_options || null;
    const user = options?.user || {};
    const auth = options?.auth || {};
    const staff = options?.staff || {};
    const buyer = options?.buyer || {};
    const capabilities = options?.capabilities || [];
    const statusTone = status => status === "available" ? "b-green" : "b-orange";
    const availability = value => value ? "Available" : "Not available";
    const capabilityRows = capabilities.length
      ? capabilities
      : [{ name: "Mobile journey API", status: "not_available", detail: "Laravel mobile journey bootstrap is not available for this session." }];

    const authPanel = e("div", { style: { flex: 1, overflowY: "auto", padding: 18, display: "flex", flexDirection: "column", gap: 12 } },
      e("div", { className: "sb-logo", style: { margin: "4px auto 8px", width: 54, height: 54, borderRadius: 16 } }, e(Icon, { name: "building", size: 28 })),
      e("div", { style: { textAlign: "center" } },
        e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 20 } }, "Builder360 Mobile Access"),
        e("div", { className: "cell-sub" }, "Responsive web access from Laravel")),
      e("div", { className: "sys-note", style: { margin: 0, textAlign: "left" } },
        e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
        auth.message || "Mobile access status requires Laravel bootstrap data."),
      e("div", { style: { background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 12, textAlign: "left" } },
        e("div", { className: "cell-sub" }, "Login route"),
        e("div", { style: { fontWeight: 800, fontSize: 13 } }, auth.login_route || "/login"),
        e(Badge, { tone: "b-orange", dot: true }, auth.native_app_auth_status === "not_implemented" ? "Native app not implemented" : "Configured")));

    const staffPanel = e("div", { style: { flex: 1, overflowY: "auto", padding: 16 } },
      e("div", { className: "row between", style: { marginBottom: 14 } },
        e("div", null,
          e("div", { style: { fontSize: 11, color: "var(--text-3)", fontWeight: 700 } }, String(user.role || "Authenticated user").toUpperCase()),
          e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 16 } }, user.name || "Current user")),
        e(Avatar, { name: user.name || "User", size: 34 })),
      e("div", { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginBottom: 14 } },
        [["Open Tasks", staff.open_tasks ?? 0, "var(--accent)"], ["Pending Approvals", staff.pending_approvals ?? 0, "var(--orange)"]].map((c, i) =>
          e("div", { key: i, style: { background: "var(--surface)", borderRadius: 14, padding: 13, border: "1px solid var(--border)" } },
            e("div", { className: "mono", style: { fontWeight: 800, fontSize: 24, color: c[2] } }, String(c[1])),
            e("div", { style: { fontSize: 11, color: "var(--text-2)", fontWeight: 600 } }, c[0])))),
      e("div", { style: { fontSize: 12, fontWeight: 800, color: "var(--text-3)", margin: "6px 0 10px", textTransform: "uppercase", letterSpacing: ".05em" } }, "Available web journeys"),
      [
        ["users", "Employee Self-Service", staff.employee_self_service],
        ["hardhat", "Site / Construction", staff.construction_journey],
        ["trend", "Sales / Partner", staff.sales_journey],
      ].map((row, i) =>
        e("div", { key: i, style: { display: "flex", gap: 10, alignItems: "center", padding: "12px 13px", background: "var(--surface)", borderRadius: 13, border: "1px solid var(--border)", marginBottom: 9 } },
          e("div", { style: { width: 34, height: 34, borderRadius: 10, background: "var(--surface-3)", color: row[2] ? "var(--green)" : "var(--orange)", display: "grid", placeItems: "center" } }, e(Icon, { name: row[0], size: 16 })),
          e("span", { style: { fontWeight: 700, fontSize: 12.5, flex: 1 } }, row[1]),
          e(Badge, { tone: row[2] ? "b-green" : "b-orange" }, availability(row[2])))));

    const buyerPanel = e("div", { style: { flex: 1, overflowY: "auto" } },
      e("div", { style: { background: "var(--accent-grad)", padding: "16px 18px 22px", color: "#fff" } },
        e("div", { style: { fontSize: 11, opacity: .85, fontWeight: 700 } }, "BUYER MOBILE JOURNEY"),
        e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 18 } }, buyer.available ? "Buyer portal data scoped" : "Buyer role required"),
        e("div", { style: { fontSize: 12, opacity: .9 } }, buyer.available ? "Laravel booking, payment, documents and tickets" : "No buyer records are shown for this role")),
      e("div", { style: { padding: 16 } },
        e("div", { style: { background: "var(--surface)", borderRadius: 16, padding: 16, border: "1px solid var(--border)", marginBottom: 14 } },
          e("div", { className: "row between", style: { marginBottom: 8 } },
            e("span", { style: { fontSize: 12, color: "var(--text-2)", fontWeight: 600 } }, "Booking records"),
            e(Badge, { tone: buyer.available ? "b-green" : "b-orange" }, buyer.available ? "Scoped" : "Unavailable")),
          e("div", { className: "mono", style: { fontWeight: 800, fontSize: 24 } }, String(buyer.booking_count ?? 0)),
          e("div", { style: { fontSize: 11.5, color: "var(--text-3)", marginTop: 8 } }, "Open service tickets: " + String(buyer.open_tickets ?? 0))),
        [
          ["calendar", "Payment Schedule", buyer.available],
          ["download", "Documents & Receipts", buyer.available],
          ["headset", "Service Requests", buyer.available],
        ].map((row, i) =>
          e("div", { key: i, style: { display: "flex", gap: 10, alignItems: "center", padding: "12px 13px", background: "var(--surface)", borderRadius: 13, border: "1px solid var(--border)", marginBottom: 9 } },
            e("div", { style: { width: 34, height: 34, borderRadius: 10, background: "var(--surface-3)", color: row[2] ? "var(--green)" : "var(--orange)", display: "grid", placeItems: "center" } }, e(Icon, { name: row[0], size: 16 })),
            e("span", { style: { fontWeight: 700, fontSize: 12.5, flex: 1 } }, row[1]),
            e(Badge, { tone: row[2] ? "b-green" : "b-orange" }, availability(row[2]))))));

    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Customer", "Mobile Apps"], title: "Mobile Journey Readiness", sub: "Responsive mobile web journeys backed by Laravel permissions and scoped records. Native Android/iOS binaries are explicitly not included in this build.",
        actions: e(Badge, { tone: options ? "b-green" : "b-orange", dot: true }, options ? "Laravel scoped" : "API REQUIRED") }),
      e("div", { className: "grid g-3", style: { marginBottom: 22 } }, capabilityRows.map((cap, i) =>
        e("div", { key: i, className: "card card-pad" },
          e("div", { className: "row between", style: { marginBottom: 8, gap: 8 } },
            e("div", { style: { fontWeight: 800, fontSize: 14 } }, cap.name),
            e(Badge, { tone: statusTone(cap.status), dot: true }, String(cap.status || "not_available").replace(/_/g, " "))),
          e("div", { className: "cell-sub" }, cap.detail)))),
      e("div", { style: { display: "flex", gap: 48, flexWrap: "wrap", justifyContent: "center", padding: "10px 0 40px" } },
        e(PhoneFrame, { label: "Mobile Access · Auth Status", children: authPanel }),
        e(PhoneFrame, { label: "Staff Mobile · Scoped Status", children: staffPanel }),
        e(PhoneFrame, { label: "Buyer Mobile · Scoped Status", children: buyerPanel }),
      ),
    );
  }

  Object.assign(window, { BuyerPortal, Inquiry, MobileApps });
})();
