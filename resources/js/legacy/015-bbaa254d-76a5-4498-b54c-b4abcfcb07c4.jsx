const React = window.React;

/* Builder360 — Operations depth: HR, Finance, Possession (tabbed bespoke) */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, BarChart, LineChart, HBars, PageHead, ChipSelect, Seg, Empty } = window;
  const e = React.createElement;
  const DB = window.DB;

  // safe table helper — head: [{l,r}|string], rows: [[cell,...]]
  function T(head, rows) {
    const th = head.map((h, i) => e("th", { key: i, style: (h.r ? { textAlign: "right" } : {}) }, h.l != null ? h.l : h));
    const body = rows.map((r, i) => e("tr", { key: i }, r.map((c, j) => e("td", { key: j, className: (head[j] && head[j].r ? "num" : "") }, c))));
    return e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" }, e("thead", null, e("tr", null, th)), e("tbody", null, body)));
  }
  const user = (n, sub) => e("div", { className: "cell-user" }, e(Avatar, { name: n, sm: true }), (sub ? e("div", null, e("div", { className: "cell-strong" }, n), e("div", { className: "cell-sub" }, sub)) : e("span", { className: "cell-strong" }, n)));
  const tabBar = (tabs, tab, set) => e("div", { className: "tabs", style: { overflowX: "auto" } }, tabs.map(t => e("div", { key: t, className: "tab " + (tab === t ? "on" : ""), onClick: () => set(t) }, t)));
  function csvCell(value) {
    const raw = value == null ? "" : String(value);
    const safe = /^[=+\-@]/.test(raw) ? "'" + raw : raw;
    return '"' + safe.replace(/"/g, '""') + '"';
  }
  function downloadCsv(filename, rows) {
    if (!Array.isArray(rows) || !rows.length) return false;
    const headers = Array.from(rows.reduce((set, row) => {
      Object.keys(row || {}).forEach(key => set.add(key));
      return set;
    }, new Set()));
    const csv = [headers.map(csvCell).join(","), ...rows.map(row => headers.map(key => csvCell(row && row[key])).join(","))].join("\r\n");
    try {
      const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      a.style.display = "none";
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.setTimeout(() => URL.revokeObjectURL(url), 500);
      return true;
    } catch (err) {
      try {
        const a = document.createElement("a");
        a.href = "data:text/csv;charset=utf-8," + encodeURIComponent(csv);
        a.download = filename;
        a.style.display = "none";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        return true;
      } catch (fallbackErr) {
        console.error("[Builder360] Operations CSV export failed", fallbackErr);
        return false;
      }
    }
  }
  function exportCsv(toast, filename, rows, label) {
    if (!Array.isArray(rows) || !rows.length) {
      toast("No " + label + " rows available to export", "orange");
      return;
    }
    const ok = downloadCsv(filename, rows);
    toast(ok ? "Exported " + rows.length + " " + label + " row(s)" : "Unable to export " + label + " rows", ok ? "green" : "red");
  }
  function unavailable(toast, label) {
    toast(label + " requires the governed backend workflow and is not enabled from this legacy screen.", "orange");
  }
  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
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
    if (!response.ok) {
      const firstError = payload?.errors ? Object.values(payload.errors).flat()[0] : null;
      throw new Error(firstError || payload?.message || "The request could not be saved.");
    }
    return payload;
  }
  function formatInrNumber(amount) {
    return Number(amount || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
  }
  function formatInrCr(amount) {
    return (Number(amount || 0) / 10000000).toLocaleString("en-IN", { maximumFractionDigits: 2 });
  }
  function moneyCell(amount) {
    return "₹" + formatInrNumber(amount);
  }
  function voucherTypeLabel(type) {
    return String(type || "journal").split("_").map(x => x.charAt(0).toUpperCase() + x.slice(1)).join(" ");
  }

  function FinanceVoucherModal({ options, onClose, onSaved, toast }) {
    const projects = options?.projects || [];
    const companies = options?.companies || [];
    const voucherTypes = options?.voucher_types || [{ value: "payment", label: "Payment" }, { value: "receipt", label: "Receipt" }, { value: "journal", label: "Journal" }];
    const defaults = options?.default_accounts || {};
    const firstProject = projects[0]?.id ? String(projects[0].id) : "";
    const [form, setForm] = React.useState({
      voucher_type: "payment",
      voucher_date: new Date().toISOString().slice(0, 10),
      company_id: companies[0]?.id ? String(companies[0].id) : "",
      project_id: firstProject,
      reference_number: "",
      narration: "Site expense voucher submitted from Accounts & Finance.",
      amount: "10000",
      debit_account_code: defaults.payment?.debit?.code || "SITE-EXP",
      debit_account_name: defaults.payment?.debit?.name || "Site Expense",
      credit_account_code: defaults.payment?.credit?.code || "BANK-HDFC-001",
      credit_account_name: defaults.payment?.credit?.name || "HDFC Bank Collection Account",
      cost_center: "Finance",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const applyTypeDefaults = (type) => {
      const accountDefaults = defaults[type] || defaults.journal || {};
      setForm(current => ({
        ...current,
        voucher_type: type,
        debit_account_code: accountDefaults.debit?.code || current.debit_account_code,
        debit_account_name: accountDefaults.debit?.name || current.debit_account_name,
        credit_account_code: accountDefaults.credit?.code || current.credit_account_code,
        credit_account_name: accountDefaults.credit?.name || current.credit_account_name,
      }));
    };
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      const amount = Number(form.amount);
      if (!Number.isFinite(amount) || amount <= 0) {
        setError("Enter a valid voucher amount above zero.");
        return;
      }
      if (!form.narration.trim()) {
        setError("Narration is required.");
        return;
      }
      const lineProjectId = form.project_id ? Number(form.project_id) : null;
      const payload = {
        voucher_type: form.voucher_type,
        voucher_date: form.voucher_date,
        reference_number: form.reference_number.trim() || null,
        narration: form.narration.trim(),
        currency: "INR",
        metadata: { source: "accounts_finance_legacy_screen" },
        lines: [
          {
            project_id: lineProjectId,
            account_code: form.debit_account_code.trim(),
            account_name: form.debit_account_name.trim(),
            line_type: "debit",
            amount,
            cost_center: form.cost_center.trim() || null,
            description: form.narration.trim(),
          },
          {
            project_id: lineProjectId,
            account_code: form.credit_account_code.trim(),
            account_name: form.credit_account_name.trim(),
            line_type: "credit",
            amount,
            cost_center: form.cost_center.trim() || null,
            description: form.narration.trim(),
          },
        ],
      };
      if (!lineProjectId && form.company_id) payload.company_id = Number(form.company_id);
      try {
        setBusy(true);
        const body = await apiJson(options.store_url, { method: "POST", body: JSON.stringify(payload) });
        onSaved(body.data);
        toast("Voucher " + body.data.voucher_number + " submitted to Laravel for approval.", "green");
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
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "New Financial Voucher"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates a submitted, balanced voucher in the governed Laravel finance workflow.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Voucher Type", e("select", { style: field, value: form.voucher_type, onChange: ev => applyTypeDefaults(ev.target.value), disabled: busy }, voucherTypes.map(t => e("option", { key: t.value, value: t.value }, t.label)))),
          e("label", { style: label }, "Voucher Date", e("input", { style: field, type: "date", value: form.voucher_date, onChange: ev => set("voucher_date", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Project", e("select", { style: field, value: form.project_id, onChange: ev => set("project_id", ev.target.value), disabled: busy }, e("option", { value: "" }, "Company-level voucher"), projects.map(p => e("option", { key: p.id, value: p.id }, p.code + " · " + p.name)))),
          e("label", { style: label }, "Company", e("select", { style: field, value: form.company_id, onChange: ev => set("company_id", ev.target.value), disabled: busy || Boolean(form.project_id) }, companies.map(c => e("option", { key: c.id, value: c.id }, c.code + " · " + c.name)))),
          e("label", { style: label }, "Reference No.", e("input", { style: field, value: form.reference_number, onChange: ev => set("reference_number", ev.target.value), disabled: busy, placeholder: "Invoice / bill / receipt reference" })),
          e("label", { style: label }, "Amount", e("input", { style: field, type: "number", min: "0.01", step: "0.01", value: form.amount, onChange: ev => set("amount", ev.target.value), disabled: busy, required: true }))),
        e("label", { style: Object.assign({}, label, { marginBottom: 12 }) }, "Narration", e("textarea", { style: Object.assign({}, field, { minHeight: 74 }), value: form.narration, onChange: ev => set("narration", ev.target.value), disabled: busy, required: true })),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Debit Account Code", e("input", { style: field, value: form.debit_account_code, onChange: ev => set("debit_account_code", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Debit Account Name", e("input", { style: field, value: form.debit_account_name, onChange: ev => set("debit_account_name", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Credit Account Code", e("input", { style: field, value: form.credit_account_code, onChange: ev => set("credit_account_code", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Credit Account Name", e("input", { style: field, value: form.credit_account_name, onChange: ev => set("credit_account_name", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Cost Center", e("input", { style: field, value: form.cost_center, onChange: ev => set("cost_center", ev.target.value), disabled: busy }))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, marginTop: 4, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Debit and credit are automatically balanced at ₹", formatInrNumber(form.amount || 0), ". Approval remains segregated from creator."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "plus", type: "submit", disabled: busy, children: busy ? "Submitting…" : "Submit Voucher" })))));
  }

  function FinancePaymentRequestModal({ options, onClose, onSaved, toast }) {
    const bookings = options?.bookings || [];
    const firstBooking = bookings[0] || null;
    const firstSchedule = firstBooking?.schedules?.find(s => !s.has_active_request) || firstBooking?.schedules?.[0] || null;
    const expiry = new Date(Date.now() + Number(options?.default_expiry_days || 7) * 86400000).toISOString().slice(0, 16);
    const [form, setForm] = React.useState({
      booking_id: firstBooking?.id ? String(firstBooking.id) : "",
      booking_payment_schedule_id: firstSchedule?.id ? String(firstSchedule.id) : "",
      amount: String(firstSchedule?.outstanding_amount || firstBooking?.outstanding_amount || ""),
      purpose: firstSchedule?.milestone ? firstSchedule.milestone + " payment link" : "Customer payment link",
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
        purpose: schedule?.milestone ? schedule.milestone + " payment link" : "Customer payment link",
      }));
    };
    const applySchedule = (scheduleId) => {
      const schedule = schedules.find(s => String(s.id) === String(scheduleId)) || null;
      setForm(current => ({
        ...current,
        booking_payment_schedule_id: scheduleId,
        amount: String(schedule?.outstanding_amount || selectedBooking?.outstanding_amount || ""),
        purpose: schedule?.milestone ? schedule.milestone + " payment link" : current.purpose,
      }));
    };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      const amount = Number(form.amount);
      const available = Number(selectedSchedule?.outstanding_amount || selectedBooking?.outstanding_amount || 0);
      if (!selectedBooking) {
        setError("Select an active booking with outstanding receivable.");
        return;
      }
      if (!Number.isFinite(amount) || amount <= 0) {
        setError("Enter a valid payment request amount above zero.");
        return;
      }
      if (available > 0 && amount > available) {
        setError("Amount exceeds the selected outstanding receivable.");
        return;
      }
      if (!form.purpose.trim()) {
        setError("Purpose is required.");
        return;
      }
      const payload = {
        booking_id: Number(form.booking_id),
        booking_payment_schedule_id: form.booking_payment_schedule_id ? Number(form.booking_payment_schedule_id) : null,
        amount,
        purpose: form.purpose.trim(),
        expires_at: form.expires_at ? new Date(form.expires_at).toISOString() : null,
        metadata: { source: "accounts_finance_payment_request_tab" },
      };
      try {
        setBusy(true);
        const body = await apiJson(options.store_url, { method: "POST", body: JSON.stringify(payload) });
        onSaved(body.data);
        toast("Payment request " + body.data.request_number + " created for buyer payment.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(720px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "New Payment Request"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates a validated buyer payment link through the Laravel finance workflow.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        !bookings.length && e("div", { className: "empty", style: { marginBottom: 12 } }, "No active booking with outstanding receivable is available in your scope."),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Booking", e("select", { style: field, value: form.booking_id, onChange: ev => applyBooking(ev.target.value), disabled: busy || !bookings.length, required: true },
            bookings.map(b => e("option", { key: b.id, value: b.id }, b.booking_code + " · " + (b.customer?.name || "Customer") + " · " + moneyCell(b.outstanding_amount))))),
          e("label", { style: label }, "Schedule", e("select", { style: field, value: form.booking_payment_schedule_id, onChange: ev => applySchedule(ev.target.value), disabled: busy || !schedules.length },
            e("option", { value: "" }, "Booking-level request"),
            schedules.map(s => e("option", { key: s.id, value: s.id, disabled: s.has_active_request }, "#" + s.sequence + " · " + s.milestone + " · " + moneyCell(s.outstanding_amount) + (s.has_active_request ? " · active link exists" : ""))))),
          e("label", { style: label }, "Amount", e("input", { style: field, type: "number", min: "1", step: "0.01", value: form.amount, onChange: ev => set("amount", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Expires At", e("input", { style: field, type: "datetime-local", value: form.expires_at, onChange: ev => set("expires_at", ev.target.value), disabled: busy })),
          e("label", { style: Object.assign({}, label, { gridColumn: "1 / -1" }) }, "Purpose", e("textarea", { style: Object.assign({}, field, { minHeight: 70 }), value: form.purpose, onChange: ev => set("purpose", ev.target.value), disabled: busy, required: true }))),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, marginTop: 4, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Gateway: ", options?.gateway_label || "Internal simulated gateway", options?.gateway_mode === "configured" ? ". Webhook reconciliation is enabled for the configured provider." : ". Buyer payment is simulated internally; no external gateway movement is invoked."),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "plus", type: "submit", disabled: busy || !bookings.length, children: busy ? "Creating…" : "Create Request" })))));
  }

  function paymentRequestUrl(template, request) {
    return String(template || "").replace("__PAYMENT_REQUEST__", request?.id || "");
  }

  function FinancePaymentRequestCancelModal({ options, paymentRequest, onClose, onSaved, toast }) {
    const [reason, setReason] = React.useState("Cancelled by finance after customer/account review.");
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!paymentRequest?.id) {
        setError("Select a valid payment request to cancel.");
        return;
      }
      if (!reason.trim()) {
        setError("Cancellation reason is required.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(paymentRequestUrl(options.cancel_url_template, paymentRequest), {
          method: "PATCH",
          body: JSON.stringify({ reason: reason.trim() }),
        });
        const updated = body.data || Object.assign({}, paymentRequest, { status: "cancelled", cancellation_reason: reason.trim() });
        onSaved(updated);
        toast("Payment request " + paymentRequest.request_number + " cancelled.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(620px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null,
            e("h2", { style: { margin: 0, fontSize: 20 } }, "Cancel Payment Request"),
            e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, paymentRequest?.request_number || "Selected request", " · ", paymentRequest?.customer?.name || "Customer")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("div", { className: "grid g-2", style: { marginBottom: 12 } },
          e(Stat, { label: "Amount", value: "₹" + formatInrNumber(paymentRequest?.amount || 0), icon: "wallet", tone: "orange", sub: paymentRequest?.payment_schedule?.milestone || "Booking-level" }),
          e(Stat, { label: "Booking", value: paymentRequest?.booking?.booking_code || "—", icon: "home", tone: "blue", sub: paymentRequest?.expires_at ? "Expires " + String(paymentRequest.expires_at).slice(0, 10) : "No expiry" })),
        e("label", { style: label }, "Cancellation Reason", e("textarea", { style: Object.assign({}, field, { minHeight: 90 }), value: reason, onChange: ev => setReason(ev.target.value), disabled: busy, required: true, placeholder: "Enter the customer/account reason for cancellation" })),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginTop: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "Only requested payment links can be cancelled. The Laravel workflow records the status change, reason, actor and audit history."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Gateway: ", options?.gateway_label || "Internal simulated gateway", options?.gateway_mode === "configured" ? " · configured provider" : " · internal simulation"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Keep Active" }), e(Button, { variant: "primary", icon: "x", type: "submit", disabled: busy, children: busy ? "Cancelling…" : "Cancel Request" })))));
  }

  function handoverStatusLabel(status) {
    return String(status || "blocked").split("_").map(x => x.charAt(0).toUpperCase() + x.slice(1)).join(" ");
  }

  function handoverStatusTone(status) {
    if (status === "completed") return "b-green";
    if (status === "ready") return "b-violet";
    if (status === "blocked") return "b-orange";
    return "b-blue";
  }
  function possessionHandoverRow(handover) {
    return {
      source_id: handover.id,
      unit: handover.unit?.unit_code || handover.unit?.unit_number || handover.booking_code || handover.handover_number || "Unit pending",
      cust: handover.customer?.name || "Customer pending",
      pay: Number(handover.financial_outstanding || 0) > 0 ? "Pending" : "Cleared",
      snag: Number(handover.open_snags_count || 0),
      st: handoverStatusLabel(handover.status),
      status: handover.status,
      b: handoverStatusTone(handover.status),
      checklist: handover.checklist || [],
      blockers: handover.blockers || [],
      handover_number: handover.handover_number,
      target_handover_on: handover.target_handover_on,
      actual_handover_on: handover.actual_handover_on,
      possession_letter_reference: handover.possession_letter_reference,
    };
  }

  function possessionSnagRow(snag) {
    const handover = snag.handover || {};
    return {
      id: snag.id,
      snag_number: snag.snag_number,
      possession_handover_id: snag.possession_handover_id || handover.id,
      unit: handover.unit?.unit_code || handover.unit?.unit_number || handover.handover_number || "Unit pending",
      customer: handover.customer?.name || "Customer pending",
      area: snag.area,
      category: snag.category,
      severity: snag.severity,
      description: snag.description,
      status: snag.status,
      target_resolution_on: snag.target_resolution_on,
      resolved_at: snag.resolved_at,
      resolution_notes: snag.resolution_notes,
      reported_by: snag.reported_by?.name || "—",
      resolved_by: snag.resolved_by?.name || "—",
    };
  }

  function PossessionHandoverModal({ options, onClose, onSaved, toast }) {
    const bookings = options?.bookings || [];
    const [form, setForm] = React.useState({
      booking_id: bookings[0]?.id ? String(bookings[0].id) : "",
      target_handover_on: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10),
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const selectedBooking = bookings.find(b => String(b.id) === String(form.booking_id));
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!form.booking_id) {
        setError("Select a confirmed booking to initiate handover.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(options.store_url, {
          method: "POST",
          body: JSON.stringify({
            booking_id: Number(form.booking_id),
            target_handover_on: form.target_handover_on || null,
          }),
        });
        onSaved(body.data);
        toast("Handover " + body.data.handover_number + " initiated in Laravel.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(720px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "Start Possession Handover"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, "Creates a governed handover record, calculates blockers, and writes audit history.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        !bookings.length && e("div", { className: "empty", style: { marginBottom: 12 } }, e("div", { className: "empty-ic" }, e(Icon, { name: "key", size: 24 })), e("h3", null, "No eligible bookings"), e("div", null, "Only confirmed bookings in your company scope without an existing handover can be initiated.")),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Confirmed Booking", e("select", { style: field, value: form.booking_id, onChange: ev => setForm(current => ({ ...current, booking_id: ev.target.value })), disabled: busy || !bookings.length, required: true },
            bookings.map(b => e("option", { key: b.id, value: b.id }, (b.booking_code || ("Booking #" + b.id)) + " · " + (b.unit?.unit_code || b.unit?.unit_number || "Unit pending") + " · " + (b.customer?.name || "Customer pending"))))),
          e("label", { style: label }, "Target Handover Date", e("input", { style: field, type: "date", value: form.target_handover_on, onChange: ev => setForm(current => ({ ...current, target_handover_on: ev.target.value })), disabled: busy }))),
        selectedBooking && e("div", { className: "grid g-4", style: { marginBottom: 12 } },
          e(Stat, { label: "Project", value: selectedBooking.project?.code || "—", icon: "building", tone: "blue", sub: selectedBooking.project?.name || "Project pending" }),
          e(Stat, { label: "Unit", value: selectedBooking.unit?.unit_code || selectedBooking.unit?.unit_number || "—", icon: "home", tone: "accent", sub: selectedBooking.unit?.status || "Unit status pending" }),
          e(Stat, { label: "Collections", value: "₹" + formatInrNumber(selectedBooking.approved_collections), icon: "wallet", tone: "green", sub: "approved receipts" }),
          e(Stat, { label: "Outstanding", value: "₹" + formatInrNumber(selectedBooking.financial_outstanding), icon: "rupee", tone: selectedBooking.financial_outstanding > 0 ? "red" : "green", sub: selectedBooking.financial_outstanding > 0 ? "will block completion" : "payment clear" })),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "The backend applies the configured/default checklist, recomputes final-payment blockers, prevents duplicate handovers, and stores workflow/audit history. Completion remains a separate approval-controlled action."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Source: ", options?.source || "Laravel"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "key", type: "submit", disabled: busy || !bookings.length, children: busy ? "Starting…" : "Start Handover" })))));
  }

  function PossessionLetterModal({ options, handover, onClose, onSaved, toast }) {
    const defaultRef = "PL-" + (handover?.handover_number || handover?.source_id || "HANDOVER") + "-" + new Date().toISOString().slice(0, 10).replace(/-/g, "");
    const [reference, setReference] = React.useState(handover?.possession_letter_reference || defaultRef);
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!handover?.source_id) {
        setError("Select a Laravel handover record before issuing a letter.");
        return;
      }
      if (!reference.trim()) {
        setError("Possession letter reference is required.");
        return;
      }
      const url = (options?.letter_url_template || "").replace("__HANDOVER__", String(handover.source_id));
      try {
        setBusy(true);
        const body = await apiJson(url, {
          method: "PATCH",
          body: JSON.stringify({ possession_letter_reference: reference.trim() }),
        });
        const row = possessionHandoverRow(Object.assign({}, body.data, {
          booking_code: body.data.booking?.booking_code,
          open_snags_count: (body.data.snags || []).filter(s => s.status === "open").length,
        }));
        onSaved(row);
        toast("Possession letter " + body.data.possession_letter_reference + " issued.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(620px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "Issue Possession Letter"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, handover?.unit || "Selected handover", " · ", handover?.cust || "Customer pending")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("label", { style: Object.assign({}, label, { marginBottom: 12 }) }, "Letter Reference", e("input", { style: field, value: reference, onChange: ev => setReference(ev.target.value), disabled: busy, required: true })),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "The backend will reject issuance until final payment, checklist and snag blockers are clear. Completion must use this issued reference."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Handover: ", handover?.handover_number || "—"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "doc", type: "submit", disabled: busy, children: busy ? "Issuing…" : "Issue Letter" })))));
  }

  function PossessionCompleteModal({ options, handover, onClose, onSaved, toast }) {
    const today = new Date().toISOString().slice(0, 10);
    const [form, setForm] = React.useState({
      actual_handover_on: handover?.actual_handover_on || today,
      possession_letter_reference: handover?.possession_letter_reference || "",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const set = (key, value) => setForm(current => Object.assign({}, current, { [key]: value }));
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!handover?.source_id) {
        setError("Select a Laravel handover record before completing handover.");
        return;
      }
      if (!form.actual_handover_on) {
        setError("Actual handover date is required.");
        return;
      }
      if (!form.possession_letter_reference.trim()) {
        setError("Issued possession letter reference is required.");
        return;
      }
      const url = (options?.complete_url_template || "").replace("__HANDOVER__", String(handover.source_id));
      try {
        setBusy(true);
        const body = await apiJson(url, {
          method: "PATCH",
          body: JSON.stringify({
            actual_handover_on: form.actual_handover_on,
            possession_letter_reference: form.possession_letter_reference.trim(),
          }),
        });
        const row = possessionHandoverRow(Object.assign({}, body.data, {
          booking_code: body.data.booking?.booking_code,
          open_snags_count: (body.data.snags || []).filter(s => s.status === "open").length,
        }));
        onSaved(row);
        toast("Handover " + body.data.handover_number + " completed.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(640px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "Complete Handover"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, handover?.unit || "Selected handover", " · ", handover?.cust || "Customer pending")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Actual Handover Date", e("input", { style: field, type: "date", value: form.actual_handover_on, max: today, onChange: ev => set("actual_handover_on", ev.target.value), disabled: busy, required: true })),
          e("label", { style: label }, "Issued Letter Reference", e("input", { style: field, value: form.possession_letter_reference, onChange: ev => set("possession_letter_reference", ev.target.value), disabled: busy, required: true }))),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "The backend recomputes final payment, checklist, snag and letter blockers before completion. The reference must match the issued possession letter, and completion updates the unit status to handed over."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Handover: ", handover?.handover_number || "—"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "check", type: "submit", disabled: busy, children: busy ? "Completing…" : "Complete Handover" })))));
  }

  function HandoverSnagReportModal({ options, handover, onClose, onSaved, toast }) {
    const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    const [form, setForm] = React.useState({
      area: "Unit",
      category: "Finishing",
      severity: "medium",
      target_resolution_on: tomorrow,
      description: "",
    });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const set = (key, value) => setForm(current => Object.assign({}, current, { [key]: value }));
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!handover?.source_id) {
        setError("Select a Laravel handover before reporting a snag.");
        return;
      }
      if (!form.area.trim() || !form.category.trim() || !form.description.trim()) {
        setError("Area, category and description are required.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(options.snags_store_url, {
          method: "POST",
          body: JSON.stringify({
            possession_handover_id: Number(handover.source_id),
            area: form.area.trim(),
            category: form.category.trim(),
            severity: form.severity,
            description: form.description.trim(),
            target_resolution_on: form.target_resolution_on || null,
            attachments: [],
          }),
        });
        const row = possessionSnagRow(body.data);
        onSaved(row);
        toast("Snag " + body.data.snag_number + " reported and handover readiness recalculated.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(720px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "Report Handover Snag"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, handover?.unit || "Selected handover", " · ", handover?.cust || "Customer pending")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("div", { className: "grid", style: { gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 12, marginBottom: 12 } },
          e("label", { style: label }, "Area", e("input", { style: field, value: form.area, onChange: ev => set("area", ev.target.value), disabled: busy, required: true, maxLength: 120 })),
          e("label", { style: label }, "Category", e("input", { style: field, value: form.category, onChange: ev => set("category", ev.target.value), disabled: busy, required: true, maxLength: 120 })),
          e("label", { style: label }, "Severity", e("select", { style: field, value: form.severity, onChange: ev => set("severity", ev.target.value), disabled: busy }, ["low", "medium", "high", "critical"].map(x => e("option", { key: x, value: x }, x.toUpperCase())))),
          e("label", { style: label }, "Target Resolution", e("input", { style: field, type: "date", min: new Date().toISOString().slice(0, 10), value: form.target_resolution_on, onChange: ev => set("target_resolution_on", ev.target.value), disabled: busy }))),
        e("label", { style: Object.assign({}, label, { marginBottom: 12 }) }, "Description", e("textarea", { style: Object.assign({}, field, { minHeight: 96 }), value: form.description, onChange: ev => set("description", ev.target.value), disabled: busy, required: true, maxLength: 5000, placeholder: "Describe the defect, location, expected correction and customer impact." })),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "Reporting a snag creates an auditable Laravel record and automatically blocks handover readiness until the snag is resolved."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Handover: ", handover?.handover_number || "—"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "alert", type: "submit", disabled: busy, children: busy ? "Reporting…" : "Report Snag" })))));
  }

  function HandoverSnagResolveModal({ options, snag, onClose, onSaved, toast }) {
    const [notes, setNotes] = React.useState("");
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const field = { width: "100%", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 11px", background: "var(--surface)", color: "var(--text)", font: "inherit" };
    const label = { display: "grid", gap: 6, fontSize: 12, fontWeight: 800, color: "var(--text-2)" };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!snag?.id) {
        setError("Select an open snag before resolving.");
        return;
      }
      if (!notes.trim()) {
        setError("Resolution notes are required.");
        return;
      }
      const url = (options?.snag_resolve_url_template || "").replace("__SNAG__", String(snag.id));
      try {
        setBusy(true);
        const body = await apiJson(url, {
          method: "PATCH",
          body: JSON.stringify({ resolution_notes: notes.trim() }),
        });
        const row = possessionSnagRow(body.data);
        onSaved(row);
        toast("Snag " + body.data.snag_number + " resolved and handover readiness recalculated.", "green");
        onClose();
      } catch (apiError) {
        setError(apiError.message);
      } finally {
        setBusy(false);
      }
    };
    return e("div", { onClick: busy ? undefined : onClose, style: { position: "fixed", inset: 0, zIndex: 1000, background: "rgba(15,23,42,.52)", display: "grid", placeItems: "center", padding: 18 } },
      e("form", { onClick: ev => ev.stopPropagation(), onSubmit: submit, style: { width: "min(620px, 96vw)", maxHeight: "92vh", overflow: "auto", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 18, boxShadow: "var(--shadow-lg)", padding: 18 } },
        e("div", { className: "row between", style: { marginBottom: 14, gap: 12 } },
          e("div", null, e("h2", { style: { margin: 0, fontSize: 20 } }, "Resolve Handover Snag"), e("div", { className: "muted", style: { fontSize: 12, marginTop: 3 } }, snag?.snag_number || "Selected snag", " · ", snag?.unit || "Unit pending")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error && e("div", { style: { background: "rgba(220,47,58,.1)", color: "var(--red)", border: "1px solid rgba(220,47,58,.25)", borderRadius: 10, padding: 10, marginBottom: 12, fontSize: 13, fontWeight: 700 } }, error),
        e("div", { className: "grid g-2", style: { marginBottom: 12 } },
          e(Stat, { label: "Area", value: snag?.area || "—", icon: "home", tone: "blue", sub: snag?.category || "Category" }),
          e(Stat, { label: "Severity", value: String(snag?.severity || "—").toUpperCase(), icon: "alert", tone: snag?.severity === "critical" || snag?.severity === "high" ? "red" : "orange", sub: snag?.customer || "Customer" })),
        e("label", { style: label }, "Resolution Notes", e("textarea", { style: Object.assign({}, field, { minHeight: 100 }), value: notes, onChange: ev => setNotes(ev.target.value), disabled: busy, required: true, maxLength: 5000, placeholder: "Describe verification, corrective work completed and handover readiness impact." })),
        e("div", { className: "muted", style: { border: "1px solid var(--border)", borderRadius: 12, padding: 12, marginTop: 12, marginBottom: 14, fontSize: 12, lineHeight: 1.45 } },
          "Only open snags can be resolved. The Laravel backend records the resolver, timestamp, workflow history and recalculates handover blockers."),
        e("div", { className: "row between", style: { borderTop: "1px solid var(--border)", paddingTop: 14, gap: 12, flexWrap: "wrap" } },
          e("div", { className: "muted", style: { fontSize: 12 } }, "Target: ", snag?.target_resolution_on || "not set"),
          e("div", { className: "row gap-2" }, e(Button, { type: "button", onClick: onClose, children: "Cancel" }), e(Button, { variant: "primary", icon: "check", type: "submit", disabled: busy, children: busy ? "Resolving…" : "Resolve Snag" })))));
  }

  // ================= HR & PAYROLL =================
  function HR({ toast }) {
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "HR & Payroll"], title: "HR & Payroll", sub: "Operational HR and payroll data is served from the Laravel HRMS workspace only.",
        actions: [e(Button, { key: "open-hrms", icon: "id", variant: "primary", onClick: () => { window.location.hash = "#hr/dashboard"; toast("Opening Laravel HRMS workspace with governed employee, attendance, leave and payroll records.", "accent"); }, children: "Open HRMS Workspace" })] }),
      e(Card, { title: "Legacy HR/Payroll shell disabled", sub: "Static employee, attendance, leave, payroll and recruitment rows are intentionally hidden.", pad: true },
        e("div", { className: "grid g-4", style: { marginBottom: 16 } },
          e(Stat, { label: "Employees", value: "—", icon: "id", tone: "accent", sub: "Requires HRMS API" }),
          e(Stat, { label: "Attendance", value: "—", icon: "check", tone: "green", sub: "Requires attendance API" }),
          e(Stat, { label: "Payroll", value: "—", icon: "wallet", tone: "violet", sub: "Requires payroll API" }),
          e(Stat, { label: "Recruitment", value: "—", icon: "users", tone: "orange", sub: "Requires recruitment API" })),
        e("div", { className: "empty" }, "Use HR & Employees for governed Laravel records, role-scoped actions, payroll controls, approvals, audit and exports. This legacy Operations shell does not display sample HR/payroll data.")));
  }

  // ================= ACCOUNTS & FINANCE =================
  function Finance({ toast }) {
    const [tab, setTab] = React.useState("Vouchers");
    const [creatingVoucher, setCreatingVoucher] = React.useState(false);
    const [creatingPaymentRequest, setCreatingPaymentRequest] = React.useState(false);
    const [cancellingPaymentRequest, setCancellingPaymentRequest] = React.useState(null);
    const [createdVoucherRows, setCreatedVoucherRows] = React.useState([]);
    const [createdPaymentRequests, setCreatedPaymentRequests] = React.useState([]);
    const [cancelledPaymentRequests, setCancelledPaymentRequests] = React.useState({});
    const voucherOptions = window.Builder360Server?.finance_voucher_options || null;
    const paymentRequestOptions = window.Builder360Server?.finance_payment_request_options || null;
    const financeDashboard = window.Builder360Server?.finance_dashboard || null;
    const cashPosition = financeDashboard?.cash_position || {};
    const periodSummary = financeDashboard?.period_summary || {};
    const receivablesSummary = financeDashboard?.receivables || {};
    const payablesSummary = financeDashboard?.payables || {};
    const gstSummary = financeDashboard?.gst || {};
    const recentActivity = financeDashboard?.recent_activity || {};
    const openVoucherModal = () => {
      if (!voucherOptions?.can_create || !voucherOptions?.store_url) {
        unavailable(toast, "New voucher creation");
        return;
      }
      setCreatingVoucher(true);
    };
    const openPaymentRequestModal = () => {
      if (!paymentRequestOptions?.can_create || !paymentRequestOptions?.store_url) {
        unavailable(toast, "Payment request creation");
        return;
      }
      setCreatingPaymentRequest(true);
    };
    const onVoucherSaved = (voucher) => {
      setCreatedVoucherRows(rows => [[
        voucher.voucher_number,
        voucherTypeLabel(voucher.voucher_type),
        voucher.narration,
        voucher.project?.code || voucher.project?.name || voucher.company?.code || "Company",
        formatInrNumber(voucher.total_debit),
        voucher.voucher_date,
        voucher.status === "submitted" ? "Pending" : voucherTypeLabel(voucher.status),
      ], ...rows]);
    };
    const onPaymentRequestSaved = (paymentRequest) => {
      setCreatedPaymentRequests(rows => [paymentRequest, ...rows]);
    };
    const onPaymentRequestCancelled = (paymentRequest) => {
      setCreatedPaymentRequests(rows => rows.map(row => String(row.id) === String(paymentRequest.id) ? paymentRequest : row));
      setCancelledPaymentRequests(current => ({ ...current, [paymentRequest.id]: paymentRequest }));
    };
    const openCancelPaymentRequest = (paymentRequest) => {
      if (!paymentRequestOptions?.can_cancel || !paymentRequestOptions?.cancel_url_template || paymentRequest.status !== "requested") {
        unavailable(toast, "Payment request cancellation");
        return;
      }
      setCancellingPaymentRequest(paymentRequest);
    };
    if (financeDashboard) {
      const tabs = ["Vouchers", "Payment Requests", "Customer Ledger", "Vendor & Contractor", "GST", "Cash Flow"];
      const serverVoucherRows = Array.isArray(recentActivity.vouchers) ? recentActivity.vouchers.map(v => [
        v.voucher_number,
        voucherTypeLabel(v.voucher_type),
        "Finance voucher",
        v.project || "Company",
        formatInrNumber(v.amount),
        v.voucher_date || "—",
        voucherTypeLabel(v.status),
      ]) : [];
      const visibleVoucherRows = [...createdVoucherRows, ...serverVoucherRows];
      const voucherExportRows = visibleVoucherRows.map(r => ({ voucher: r[0], type: r[1], head: r[2], project: r[3], amount_inr: r[4], date: r[5], status: r[6] }));
      const tallyExportRows = visibleVoucherRows
        .filter(r => r[0] && r[0] !== "—")
        .map(r => ({
          voucher_number: r[0],
          voucher_type: r[1],
          voucher_date: r[5],
          narration: r[2],
          cost_centre: r[3],
          debit_ledger: r[1] === "Receipt" ? "Bank / Cash" : "Expense / Payable",
          credit_ledger: r[1] === "Receipt" ? "Customer / Receivable" : "Bank / Cash",
          amount_inr: String(r[4]).replace(/,/g, ""),
          status: r[6],
          export_format: "Tally CSV import template",
          source: "laravel_finance_dashboard",
        }));
      const ledgerRows = Array.isArray(recentActivity.collections) ? recentActivity.collections.map(r => [
        r.receipt_date || "—",
        r.receipt_number + " · " + (r.customer || "Customer collection"),
        "—",
        formatInrNumber(r.amount),
        voucherTypeLabel(r.status),
      ]) : [];
      const ledgerExportRows = ledgerRows.map(r => ({ date: r[0], particulars: r[1], debit_inr: r[2], credit_inr: r[3], balance_or_status: r[4] }));
      const gstRows = (gstSummary.by_transaction_type || []).length ? gstSummary.by_transaction_type : [{ transaction_type: "No approved entries", entry_count: 0, taxable_amount: 0, total_tax_amount: 0 }];
      const forecastRows = [
        ["Opening cash position", cashPosition.net_cash_position],
        ["Forecast inflow", receivablesSummary.forecast_inflow],
        ["Forecast outflow", payablesSummary.forecast_outflow],
        ["Net forecast", Number(receivablesSummary.forecast_inflow || 0) - Number(payablesSummary.forecast_outflow || 0)],
      ];
      const visiblePaymentRequests = [
        ...createdPaymentRequests,
        ...((paymentRequestOptions?.requests || []).map(row => cancelledPaymentRequests[row.id] || row)),
      ];

      const vouchers = e(Card, { title: "Recent Vouchers", sub: "Laravel finance voucher register", action: e(Button, { sm: true, icon: "plus", variant: "primary", onClick: openVoucherModal, children: "New Voucher" }) },
        T([{ l: "Voucher" }, { l: "Type" }, { l: "Head" }, { l: "Project" }, { l: "Amount", r: true }, { l: "Date" }, { l: "Status" }],
          (visibleVoucherRows.length ? visibleVoucherRows : [["—", "—", "No vouchers in current scope", "—", "0", "—", "—"]])
          .map(r => [e("span", { className: "cell-strong mono" }, r[0]), e(Badge, { tone: r[1] === "Receipt" ? "b-green" : "b-blue" }, r[1]), r[2], e("span", { className: "tag" }, r[3]), e("span", { className: "mono cell-strong" }, moneyCell(String(r[4]).replace(/,/g, ""))), e("span", { className: "faint" }, r[5]), e(Badge, { tone: r[6] === "Approved" ? "b-green" : "b-orange", dot: true }, r[6])])));

      const custLedger = e(Card, { title: "Customer Ledger", sub: "Recent collections from Laravel receipts", action: e(Button, { sm: true, icon: "download", onClick: () => exportCsv(toast, "builder360-customer-ledger.csv", ledgerExportRows, "customer ledger"), children: "Statement CSV" }) },
        T([{ l: "Date" }, { l: "Particulars" }, { l: "Debit", r: true }, { l: "Credit", r: true }, { l: "Status", r: true }],
          (ledgerRows.length ? ledgerRows : [["—", "No collection receipts in current scope", "—", "0", "—"]])
          .map(r => [e("span", { className: "faint" }, r[0]), r[1], e("span", { className: "mono" }, r[2]), e("span", { className: "mono" }, moneyCell(String(r[3]).replace(/,/g, ""))), e("span", { className: "mono cell-strong" }, r[4])])));

      const paymentRequests = e(Card, { title: "Payment Requests", sub: "Buyer payment links from Laravel finance workflow", action: e(Button, { sm: true, icon: "plus", variant: "primary", onClick: openPaymentRequestModal, children: "New Request" }) },
        T([{ l: "Request" }, { l: "Customer" }, { l: "Booking" }, { l: "Milestone" }, { l: "Amount", r: true }, { l: "Expires" }, { l: "Status" }, { l: "Action" }],
          (visiblePaymentRequests.length ? visiblePaymentRequests : [{ request_number: "—", customer: { name: "No payment requests in current scope" }, booking: {}, payment_schedule: {}, amount: 0, expires_at: null, status: "—" }])
          .map(r => [e("span", { className: "cell-strong mono" }, r.request_number), r.customer?.name || "—", r.booking?.booking_code || "—", r.payment_schedule?.milestone || "Booking-level", e("span", { className: "mono cell-strong" }, moneyCell(r.amount)), r.expires_at ? String(r.expires_at).slice(0, 10) : "—", e(Badge, { tone: r.status === "paid" ? "b-green" : r.status === "requested" ? "b-orange" : "b-blue", dot: true }, voucherTypeLabel(r.status)), r.status === "requested" && paymentRequestOptions?.can_cancel ? e("button", { className: "link", onClick: () => openCancelPaymentRequest(r) }, "Cancel") : "—"])));

      const payables = e("div", { className: "grid g-2", style: { alignItems: "start" } },
        e(Card, { title: "Payables", sub: "Claims, loans and submitted payment vouchers" },
          T([{ l: "Source" }, { l: "Outstanding", r: true }, { l: "Status" }],
            [["Submitted payment vouchers", payablesSummary.submitted_payment_vouchers, "Pending"], ["Approved claims not paid", payablesSummary.approved_claims_not_paid, "Due"], ["Approved loans not disbursed", payablesSummary.approved_loans_not_disbursed, "Due"], ["Forecast outflow", payablesSummary.forecast_outflow, "Forecast"]]
            .map(r => [e("span", { className: "cell-strong" }, r[0]), e("span", { className: "mono" }, moneyCell(r[1])), e(Badge, { tone: r[2] === "Forecast" ? "b-blue" : "b-orange", dot: true }, r[2])]))),
        e(Card, { title: "Approval Queue", sub: "Finance workflow counts" },
          T([{ l: "Queue" }, { l: "Count", r: true }],
            Object.entries(financeDashboard.approvals || {}).map(([label, count]) => [voucherTypeLabel(label), e("span", { className: "mono cell-strong" }, count)]))),
      );

      const gst = e("div", null,
        e("div", { className: "grid g-4", style: { marginBottom: 16 } },
          e(Stat, { label: "Approved GST Entries", value: String(gstSummary.approved_entry_count || 0), icon: "doc", tone: "accent", sub: "Laravel GST register" }),
          e(Stat, { label: "Taxable Value", value: "₹" + formatInrCr(gstSummary.taxable_amount), unit: "Cr", icon: "trend", tone: "blue" }),
          e(Stat, { label: "GST Amount", value: "₹" + formatInrCr(gstSummary.total_tax_amount), unit: "Cr", icon: "rupee", tone: "red" }),
          e(Stat, { label: "Period End", value: financeDashboard.period?.date_to || "—", icon: "calendar", tone: "orange", sub: "dashboard scope" })),
        e(Card, { title: "GST Summary by Transaction Type", sub: "Approved entries only" },
          T([{ l: "Type" }, { l: "Entries", r: true }, { l: "Taxable Value", r: true }, { l: "GST Amount", r: true }],
            gstRows.map(r => [e("span", { className: "cell-strong" }, voucherTypeLabel(r.transaction_type)), e("span", { className: "mono" }, r.entry_count), e("span", { className: "mono" }, moneyCell(r.taxable_amount)), e("span", { className: "mono cell-strong" }, moneyCell(r.total_tax_amount))]))));

      const cashflow = e("div", { className: "grid", style: { gridTemplateColumns: "1.4fr 1fr", alignItems: "start" } },
        e(Card, { title: "Cash Flow Forecast", sub: "Database forecast for next " + financeDashboard.period.forecast_days + " day(s)", pad: true },
          T([{ l: "Metric" }, { l: "Amount", r: true }],
            forecastRows.map(r => [r[0], e("span", { className: "mono cell-strong" }, moneyCell(r[1]))]))),
        e(Card, { title: "Receivables & Payment Links", pad: true },
          [["Schedule outstanding", moneyCell(receivablesSummary.schedule_outstanding)], ["Overdue outstanding", moneyCell(receivablesSummary.overdue_outstanding)], ["Due next 30 days", moneyCell(receivablesSummary.due_next_30_days)], ["Requested payment links", moneyCell(receivablesSummary.requested_payment_links)], ["Period net flow", moneyCell(periodSummary.net_period_flow)]].map((r, i) =>
            e("div", { key: i, className: "row between", style: { padding: "9px 0", fontSize: 13, borderBottom: i < 4 ? "1px solid var(--border)" : "none" } }, e("span", { className: "muted" }, r[0]), e("span", { className: "mono", style: { fontWeight: 700 } }, r[1])))));

      const content = { Vouchers: vouchers, "Payment Requests": paymentRequests, "Customer Ledger": custLedger, "Vendor & Contractor": payables, GST: gst, "Cash Flow": cashflow }[tab];
      return e("div", { className: "page page-wide" },
        e(PageHead, { crumbs: ["Operations", "Accounts & Finance"], title: "Accounts & Finance", sub: "MySQL-backed finance dashboard, vouchers, ledgers, GST and cash flow.",
          actions: [e(Button, { key: 1, icon: "download", onClick: () => exportCsv(toast, "builder360-tally-voucher-import.csv", tallyExportRows, "Tally voucher import"), children: "Export Tally CSV" }), e(Button, { key: 2, icon: "download", onClick: () => exportCsv(toast, "builder360-finance-vouchers.csv", voucherExportRows, "voucher"), children: "Export Vouchers CSV" }), e(Button, { key: 3, icon: "plus", onClick: openPaymentRequestModal, children: "New Payment Request" }), e(Button, { key: 4, icon: "plus", variant: "primary", onClick: openVoucherModal, children: "New Voucher" })] }),
        e("div", { className: "grid g-4", style: { marginBottom: 16 } },
          e(Stat, { label: "Cash Position", value: "₹" + formatInrCr(cashPosition.net_cash_position), unit: "Cr", icon: "wallet", tone: "green", sub: financeDashboard.source }),
          e(Stat, { label: "Expenses / Outflow", value: "₹" + formatInrCr(periodSummary.approved_payment_vouchers), unit: "Cr", icon: "rupee", tone: "orange", sub: "approved payments" }),
          e(Stat, { label: "Receivables", value: "₹" + formatInrCr(receivablesSummary.schedule_outstanding), unit: "Cr", icon: "trend", tone: "violet", sub: "schedule outstanding" }),
          e(Stat, { label: "GST Amount", value: "₹" + formatInrCr(gstSummary.total_tax_amount), unit: "Cr", icon: "doc", tone: "blue", sub: "approved entries" })),
        tabBar(tabs, tab, setTab), content,
        creatingVoucher && e(FinanceVoucherModal, { options: voucherOptions, onClose: () => setCreatingVoucher(false), onSaved: onVoucherSaved, toast }),
        creatingPaymentRequest && e(FinancePaymentRequestModal, { options: paymentRequestOptions, onClose: () => setCreatingPaymentRequest(false), onSaved: onPaymentRequestSaved, toast }),
        cancellingPaymentRequest && e(FinancePaymentRequestCancelModal, { options: paymentRequestOptions, paymentRequest: cancellingPaymentRequest, onClose: () => setCancellingPaymentRequest(null), onSaved: onPaymentRequestCancelled, toast }),
      );
    }
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "Accounts & Finance"], title: "Accounts & Finance", sub: "Finance dashboard API required; no local financial rows are fabricated.",
        actions: [e(Badge, { key: "api", tone: "b-orange", dot: true }, "API REQUIRED")] }),
      e(Empty, { icon: "wallet", title: "Finance dashboard unavailable", sub: "The Accounts & Finance screen requires the Laravel finance dashboard payload. Static vouchers, ledgers, GST, cash-flow and loan values are intentionally hidden." }),
    );
  }

  // ================= POSSESSION & HANDOVER =================
  function Possession({ toast }) {
    const [sel, setSel] = React.useState(null);
    const [startingHandover, setStartingHandover] = React.useState(false);
    const [issuingLetter, setIssuingLetter] = React.useState(false);
    const [completingHandover, setCompletingHandover] = React.useState(false);
    const [reportingSnag, setReportingSnag] = React.useState(false);
    const [resolvingSnag, setResolvingSnag] = React.useState(null);
    const [createdHandoverRows, setCreatedHandoverRows] = React.useState([]);
    const [createdSnagRows, setCreatedSnagRows] = React.useState([]);
    const [rowOverrides, setRowOverrides] = React.useState({});
    const [snagOverrides, setSnagOverrides] = React.useState({});
    const handoverOptions = window.Builder360Server?.possession_handover_options || null;
    const openHandoverModal = () => {
      if (!handoverOptions?.can_create || !handoverOptions?.store_url) {
        unavailable(toast, "Handover initiation");
        return;
      }
      setStartingHandover(true);
    };
    const openLetterModal = () => {
      if (!handoverOptions?.can_issue_letter || !handoverOptions?.letter_url_template) {
        unavailable(toast, "Possession letter generation");
        return;
      }
      const target = sel?.source_id ? sel : visibleUnits.find(u => u.source_id && u.status === "ready");
      if (!target?.source_id) {
        toast("Select a ready Laravel handover record before issuing a possession letter.", "orange");
        return;
      }
      setSel(target);
      setIssuingLetter(true);
    };
    const openCompleteModal = () => {
      if (!handoverOptions?.can_complete || !handoverOptions?.complete_url_template) {
        unavailable(toast, "Handover completion");
        return;
      }
      const target = sel?.source_id ? sel : visibleUnits.find(u => u.source_id && u.status === "ready" && u.possession_letter_reference);
      if (!target?.source_id) {
        toast("Select a ready Laravel handover with an issued possession letter before completion.", "orange");
        return;
      }
      if (target.status === "completed") {
        toast("Selected handover is already completed.", "orange");
        return;
      }
      if (!target.possession_letter_reference) {
        toast("Issue the possession letter before completing this handover.", "orange");
        return;
      }
      setSel(target);
      setCompletingHandover(true);
    };
    const openSnagModal = () => {
      if (!handoverOptions?.can_report_snag || !handoverOptions?.snags_store_url) {
        unavailable(toast, "Handover snag reporting");
        return;
      }
      if (!sel?.source_id) {
        toast("Select a Laravel handover record before reporting a snag.", "orange");
        return;
      }
      if (sel.status === "completed") {
        toast("Completed handovers cannot accept new snags.", "orange");
        return;
      }
      setReportingSnag(true);
    };
    const openResolveSnag = (snag) => {
      if (!handoverOptions?.can_resolve_snag || !handoverOptions?.snag_resolve_url_template) {
        unavailable(toast, "Handover snag resolution");
        return;
      }
      if (snag.status !== "open") {
        toast("Only open snags can be resolved.", "orange");
        return;
      }
      setResolvingSnag(snag);
    };
    const onHandoverSaved = (handover) => {
      const row = possessionHandoverRow(Object.assign({}, handover, {
        booking_code: handover.booking?.booking_code,
        open_snags_count: (handover.snags || []).filter(s => s.status === "open").length,
      }));
      setCreatedHandoverRows(rows => [row, ...rows]);
      setSel(row);
    };
    const onLetterSaved = (row) => {
      setRowOverrides(current => Object.assign({}, current, { [row.source_id]: row }));
      setCreatedHandoverRows(rows => rows.map(item => item.source_id === row.source_id ? row : item));
      setSel(row);
    };
    const onCompleteSaved = (row) => {
      setRowOverrides(current => Object.assign({}, current, { [row.source_id]: row }));
      setCreatedHandoverRows(rows => rows.map(item => item.source_id === row.source_id ? row : item));
      setSel(row);
    };
    const onSnagSaved = (row) => {
      setCreatedSnagRows(rows => [row, ...rows.filter(item => item.id !== row.id)]);
      setCreatedHandoverRows(rows => rows.map(item => item.source_id === row.possession_handover_id ? Object.assign({}, item, { snag: Number(item.snag || 0) + 1, status: item.status === "completed" ? item.status : "blocked", st: item.status === "completed" ? item.st : handoverStatusLabel("blocked"), b: item.status === "completed" ? item.b : handoverStatusTone("blocked") }) : item));
      setRowOverrides(current => {
        const existing = current[row.possession_handover_id];
        return existing ? Object.assign({}, current, { [row.possession_handover_id]: Object.assign({}, existing, { snag: Number(existing.snag || 0) + 1, status: existing.status === "completed" ? existing.status : "blocked", st: existing.status === "completed" ? existing.st : handoverStatusLabel("blocked"), b: existing.status === "completed" ? existing.b : handoverStatusTone("blocked") }) }) : current;
      });
      if (sel?.source_id === row.possession_handover_id) setSel(current => current ? Object.assign({}, current, { snag: Number(current.snag || 0) + 1, status: current.status === "completed" ? current.status : "blocked", st: current.status === "completed" ? current.st : handoverStatusLabel("blocked"), b: current.status === "completed" ? current.b : handoverStatusTone("blocked") }) : current);
    };
    const onSnagResolved = (row) => {
      setSnagOverrides(current => Object.assign({}, current, { [row.id]: row }));
      setCreatedSnagRows(rows => rows.map(item => item.id === row.id ? row : item));
      setCreatedHandoverRows(rows => rows.map(item => item.source_id === row.possession_handover_id ? Object.assign({}, item, { snag: Math.max(0, Number(item.snag || 0) - 1) }) : item));
      setRowOverrides(current => {
        const existing = current[row.possession_handover_id];
        return existing ? Object.assign({}, current, { [row.possession_handover_id]: Object.assign({}, existing, { snag: Math.max(0, Number(existing.snag || 0) - 1) }) }) : current;
      });
      if (sel?.source_id === row.possession_handover_id) setSel(current => current ? Object.assign({}, current, { snag: Math.max(0, Number(current.snag || 0) - 1) }) : current);
    };
    if (!handoverOptions?.source) {
      return e("div", { className: "page page-wide" },
        e(PageHead, { crumbs: ["Operations", "Possession & Handover"], title: "Possession & Handover", sub: "Possession handover API required; no local handover rows are fabricated.",
          actions: [e(Badge, { key: "api", tone: "b-orange", dot: true }, "API REQUIRED")] }),
        e(Empty, { icon: "key", title: "Possession handover unavailable", sub: "The Possession & Handover screen requires the Laravel handover payload. Static units, snag counts, handover totals and payment-pending counters are intentionally hidden." }),
      );
    }
    const serverRows = (handoverOptions?.handovers || []).map(possessionHandoverRow).map(row => rowOverrides[row.source_id] || row);
    const createdIds = new Set(createdHandoverRows.map(row => row.source_id).filter(Boolean));
    const visibleUnits = [
      ...createdHandoverRows.map(row => rowOverrides[row.source_id] || row),
      ...serverRows.filter(row => !createdIds.has(row.source_id)),
    ];
    const createdSnagIds = new Set(createdSnagRows.map(row => row.id).filter(Boolean));
    const visibleSnags = [
      ...createdSnagRows.map(row => snagOverrides[row.id] || row),
      ...(handoverOptions?.snags || []).map(possessionSnagRow).map(row => snagOverrides[row.id] || row).filter(row => !createdSnagIds.has(row.id)),
    ];
    const selectedSnags = sel?.source_id ? visibleSnags.filter(row => Number(row.possession_handover_id) === Number(sel.source_id)) : visibleSnags.slice(0, 8);
    const summary = handoverOptions?.summary || {};
    const readyCount = Number(summary.eligible_bookings || 0);
    const checklist = (sel?.checklist || []).map(item => ({ label: item.label || item.code || "Checklist item", completed: Boolean(item.completed) }));
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Operations", "Possession & Handover"], title: "Possession & Handover", sub: "Eligibility, snag lists, handover checklist and possession letters backed by Laravel records.",
        actions: [e(Button, { key: 1, icon: "alert", onClick: openSnagModal, children: "Report Snag" }), e(Button, { key: 2, icon: "doc", onClick: openLetterModal, children: "Possession Letter" }), e(Button, { key: 3, icon: "key", variant: "primary", onClick: openHandoverModal, children: "Start Handover" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Ready for Possession", value: String(readyCount), icon: "key", tone: "green", sub: "eligible bookings" }),
        e(Stat, { label: "Snags Pending", value: String(summary.open_snags || 0), icon: "alert", tone: "orange", sub: "open snag records" }),
        e(Stat, { label: "Handovers Done", value: String(summary.completed_handovers || 0), icon: "check", tone: "accent", sub: String(summary.total_handovers || 0) + " total records" }),
        e(Stat, { label: "Payment Pending", value: String(summary.payment_pending || 0), icon: "rupee", tone: "red", sub: "blocked by outstanding" })),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.4fr 1fr", alignItems: "start" } },
        e(Card, { title: "Possession Pipeline", sub: "Laravel handover records · click a unit for checklist" },
          T([{ l: "Unit" }, { l: "Customer" }, { l: "Payment" }, { l: "Snags" }, { l: "Stage" }],
            visibleUnits.length ? visibleUnits.map(u => [
              e("span", { className: "cell-strong", style: { cursor: "pointer", color: "var(--accent)" }, onClick: () => setSel(u) }, u.unit),
              user(u.cust), e(Badge, { tone: u.pay === "Cleared" ? "b-green" : "b-red", dot: true }, u.pay),
              e("span", { className: u.snag ? "badge b-orange" : "badge b-green" }, u.snag + " open"), e(Badge, { tone: u.b, dot: true }, u.st)]) : [[e("span", { className: "faint" }, "No Laravel handover records in selected scope."), "", "", "", ""]])),
        e(Card, { title: sel ? "Handover Checklist · " + sel.unit : "Handover Checklist", sub: sel ? sel.cust : "select a unit", action: sel?.source_id ? e(Button, { sm: true, icon: "check", variant: "primary", onClick: openCompleteModal, children: "Complete Handover" }) : null }, sel
          ? (checklist.length ? e("div", { style: { padding: "6px 4px" } }, checklist.map((c, i) => {
              const done = Boolean(c.completed);
              return e("div", { key: i, className: "row gap-3", style: { padding: "10px 14px", borderBottom: i < checklist.length - 1 ? "1px solid var(--border)" : "none" } },
                e("div", { style: { width: 22, height: 22, borderRadius: 7, flex: "0 0 22px", display: "grid", placeItems: "center", background: done ? "var(--green)" : "var(--surface-3)", color: done ? "#fff" : "var(--text-3)", border: done ? "none" : "1px solid var(--border-strong)" } }, done ? e(Icon, { name: "check", size: 13 }) : null),
                e("span", { style: { fontSize: 13, fontWeight: 600, color: done ? "var(--text)" : "var(--text-2)" } }, c.label));
            })) : e("div", { className: "empty" }, e("div", { className: "empty-ic" }, e(Icon, { name: "check", size: 24 })), e("h3", null, "No checklist returned"), e("div", null, "The selected Laravel handover has no checklist payload.")))
          : e("div", { className: "empty" }, e("div", { className: "empty-ic" }, e(Icon, { name: "key", size: 24 })), e("h3", null, "No unit selected"), e("div", null, "Pick a unit to view its handover progress"))),
      ),
      e(Card, { title: sel ? "Snag Register · " + sel.unit : "Snag Register", sub: "Laravel handover snags with report, resolve and readiness blocking workflow.", action: sel?.source_id ? e(Button, { sm: true, icon: "alert", onClick: openSnagModal, children: "Report Snag" }) : null },
        T([{ l: "Snag" }, { l: "Unit" }, { l: "Area" }, { l: "Severity" }, { l: "Target" }, { l: "Status" }, { l: "Action" }],
          selectedSnags.length ? selectedSnags.map(row => [
            e("div", null, e("span", { className: "cell-strong mono" }, row.snag_number), e("div", { className: "cell-sub" }, row.description)),
            user(row.customer, row.unit),
            e("span", { className: "tag" }, row.area + " · " + row.category),
            e(Badge, { tone: row.severity === "critical" || row.severity === "high" ? "b-red" : row.severity === "medium" ? "b-orange" : "b-slate", dot: true }, String(row.severity || "—").toUpperCase()),
            e("span", { className: "faint" }, row.target_resolution_on || "—"),
            e(Badge, { tone: row.status === "resolved" ? "b-green" : "b-orange", dot: true }, row.status),
            row.status === "open" && handoverOptions?.can_resolve_snag ? e("button", { className: "link", onClick: () => openResolveSnag(row) }, "Resolve") : e("span", { className: "faint" }, row.resolved_at ? "Resolved" : "—"),
          ]) : [[e("span", { className: "faint" }, "No snag records in selected scope."), "", "", "", "", "", ""]])),
      startingHandover && e(PossessionHandoverModal, { options: handoverOptions, onClose: () => setStartingHandover(false), onSaved: onHandoverSaved, toast }),
      issuingLetter && e(PossessionLetterModal, { options: handoverOptions, handover: sel, onClose: () => setIssuingLetter(false), onSaved: onLetterSaved, toast }),
      completingHandover && e(PossessionCompleteModal, { options: handoverOptions, handover: sel, onClose: () => setCompletingHandover(false), onSaved: onCompleteSaved, toast }),
      reportingSnag && e(HandoverSnagReportModal, { options: handoverOptions, handover: sel, onClose: () => setReportingSnag(false), onSaved: onSnagSaved, toast }),
      resolvingSnag && e(HandoverSnagResolveModal, { options: handoverOptions, snag: resolvingSnag, onClose: () => setResolvingSnag(null), onSaved: onSnagResolved, toast }),
    );
  }

  Object.assign(window, { HR, Finance, Possession });
})();

