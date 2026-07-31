const React = window.React;

/* Builder360 — Bespoke promotions pt.2: Vendors, Contractors, Admin, Settings */
(function () {
  const { Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, Donut, HBars, PageHead, ChipSelect, Seg } = window;
  const e = React.createElement;

  function T(head, rows) {
    const th = head.map((h, i) => e("th", { key: i, style: (h.r ? { textAlign: "right" } : {}) }, h.l != null ? h.l : h));
    const body = rows.map((r, i) => e("tr", { key: i }, r.map((c, j) => e("td", { key: j, className: (head[j] && head[j].r ? "num" : "") }, c))));
    return e("div", { className: "tbl-wrap" }, e("table", { className: "tbl" }, e("thead", null, e("tr", null, th)), e("tbody", null, body)));
  }
  const user = (n, sub) => e("div", { className: "cell-user" }, e(Avatar, { name: n, sm: true }), (sub ? e("div", null, e("div", { className: "cell-strong" }, n), e("div", { className: "cell-sub" }, sub)) : e("span", { className: "cell-strong" }, n)));
  const tabBar = (tabs, tab, set) => e("div", { className: "tabs", style: { overflowX: "auto" } }, tabs.map(t => e("div", { key: t, className: "tab " + (tab === t ? "on" : ""), onClick: () => set(t) }, t)));
  const server = () => window.Builder360Server || {};
  const siteOptions = () => server().construction_site_options || {};
  const boqOptions = () => server().construction_boq_options || {};
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const money = (value) => {
    const n = Number(value || 0);
    if (!Number.isFinite(n)) return "₹0";
    if (Math.abs(n) >= 10000000) return "₹" + (n / 10000000).toFixed(2).replace(/\.00$/, "") + " Cr";
    if (Math.abs(n) >= 100000) return "₹" + (n / 100000).toFixed(2).replace(/\.00$/, "") + " L";
    return "₹" + Math.round(n).toLocaleString("en-IN");
  };
  const statusTone = (status) => ({ active: "b-green", approved: "b-green", paid: "b-green", submitted: "b-orange", draft: "b-slate", pending: "b-orange", partially_paid: "b-blue", partially_received: "b-blue", rejected: "b-red", inactive: "b-slate" }[status] || "b-slate");
  const label = (value) => String(value || "not set").replace(/_/g, " ").replace(/\b\w/g, ch => ch.toUpperCase());
  const urlFromTemplate = (template, token, id) => String(template || "").replace(token, encodeURIComponent(id));
  const today = () => new Date().toISOString().slice(0, 10);
  async function apiJson(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrf(),
        ...(options.headers || {}),
      },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || "Request failed");
    return data;
  }

  function VendorCreateModal({ options, onClose, onSaved, toast }) {
    const companies = Array.isArray(options.companies) && options.companies.length ? options.companies : (Array.isArray(server().companies) ? server().companies : []);
    const typeOptions = Array.isArray(options.vendor_type_options) && options.vendor_type_options.length ? options.vendor_type_options : [
      { value: "material", label: "Material Supplier" },
      { value: "contractor", label: "Contractor" },
      { value: "service", label: "Service Provider" },
      { value: "consultant", label: "Consultant" },
    ];
    const statusOptions = Array.isArray(options.vendor_status_options) && options.vendor_status_options.length ? options.vendor_status_options : [
      { value: "active", label: "Active" },
      { value: "inactive", label: "Inactive" },
      { value: "blocked", label: "Blocked" },
    ];
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      company_id: companies[0]?.id || "",
      vendor_code: "",
      name: "",
      vendor_type: typeOptions[0]?.value || "material",
      contact_name: "",
      email: "",
      phone: "",
      gstin: "",
      pan: "",
      status: "active",
      address_line1: "",
      address_city: "",
      address_state: "",
      address_pin_code: "",
      bank_account_holder: "",
      bank_account_number: "",
      bank_ifsc: "",
      bank_account_masked: "",
    });
    const set = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const clean = value => String(value || "").trim();
    const upper = value => clean(value).toUpperCase();
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!options.can_create_vendor || !options.vendors_store_url) {
        setError("You do not have permission to create vendors from this screen.");
        return;
      }
      try {
        setBusy(true);
        const payload = {
          vendor_code: upper(form.vendor_code),
          name: clean(form.name),
          vendor_type: form.vendor_type,
          contact_name: clean(form.contact_name) || null,
          email: clean(form.email) || null,
          phone: clean(form.phone) || null,
          gstin: upper(form.gstin) || null,
          pan: upper(form.pan) || null,
          status: form.status,
          address: {
            line1: clean(form.address_line1),
            city: clean(form.address_city),
            state: clean(form.address_state),
            pin_code: upper(form.address_pin_code),
          },
          bank_details: {
            account_holder: clean(form.bank_account_holder),
            account_number: upper(form.bank_account_number),
            ifsc: upper(form.bank_ifsc),
            account_masked: clean(form.bank_account_masked),
          },
          metadata: { source: "vendor_management_create_modal" },
        };
        if (form.company_id) payload.company_id = Number(form.company_id);
        const body = await apiJson(options.vendors_store_url, { method: "POST", body: JSON.stringify(payload) });
        const row = {
          ...(body.data || {}),
          category: label((body.data || {}).vendor_type),
          rating: 0,
          purchase_orders_count: 0,
          purchase_value_total: 0,
          open_purchase_value: 0,
          latest_purchase_order: null,
        };
        onSaved(row);
        toast("Vendor " + row.vendor_code + " created in Laravel procurement.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Vendor could not be created.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", style: { width: 760, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, "Add Vendor"), e("p", { className: "muted" }, "Creates a validated vendor master through Laravel procurement workflow with company scope, encryption and audit trail.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error ? e("div", { className: "empty", style: { borderColor: "rgba(239,68,68,.35)", color: "var(--red)", marginBottom: 12 } }, error) : null,
        e("div", { className: "form-grid", style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          e("label", null, "Company", e("select", { required: !!companies.length, value: form.company_id, disabled: busy || !companies.length, onChange: ev => set("company_id", ev.target.value) },
            companies.length ? companies.map(company => e("option", { key: company.id, value: company.id }, company.label || [company.code, company.name].filter(Boolean).join(" · ") || ("Company #" + company.id))) : e("option", { value: "" }, "Current user company"))),
          e("label", null, "Vendor code", e("input", { required: true, pattern: "[A-Z0-9\\-.]+", value: form.vendor_code, disabled: busy, onChange: ev => set("vendor_code", ev.target.value.toUpperCase()), placeholder: "VEN-NEW-001" })),
          e("label", { style: { gridColumn: "1 / -1" } }, "Vendor name", e("input", { required: true, maxLength: 255, value: form.name, disabled: busy, onChange: ev => set("name", ev.target.value), placeholder: "Vendor legal / trade name" })),
          e("label", null, "Vendor type", e("select", { value: form.vendor_type, disabled: busy, onChange: ev => set("vendor_type", ev.target.value) }, typeOptions.map(opt => e("option", { key: opt.value, value: opt.value }, opt.label)))),
          e("label", null, "Status", e("select", { value: form.status, disabled: busy, onChange: ev => set("status", ev.target.value) }, statusOptions.map(opt => e("option", { key: opt.value, value: opt.value }, opt.label)))),
          e("label", null, "Contact person", e("input", { maxLength: 120, value: form.contact_name, disabled: busy, onChange: ev => set("contact_name", ev.target.value), placeholder: "Primary contact" })),
          e("label", null, "Phone", e("input", { maxLength: 30, value: form.phone, disabled: busy, onChange: ev => set("phone", ev.target.value), placeholder: "+91 98765 43210" })),
          e("label", null, "Email", e("input", { type: "email", maxLength: 255, value: form.email, disabled: busy, onChange: ev => set("email", ev.target.value), placeholder: "accounts@example.com" })),
          e("label", null, "GSTIN", e("input", { maxLength: 15, pattern: "[0-9A-Z]{15}", value: form.gstin, disabled: busy, onChange: ev => set("gstin", ev.target.value.toUpperCase()), placeholder: "27ABCDE1234F1Z5" })),
          e("label", null, "PAN", e("input", { maxLength: 10, pattern: "[A-Z]{5}[0-9]{4}[A-Z]", value: form.pan, disabled: busy, onChange: ev => set("pan", ev.target.value.toUpperCase()), placeholder: "ABCDE1234F" })),
          e("label", null, "Address line", e("input", { maxLength: 255, value: form.address_line1, disabled: busy, onChange: ev => set("address_line1", ev.target.value), placeholder: "Registered address" })),
          e("label", null, "City", e("input", { maxLength: 120, value: form.address_city, disabled: busy, onChange: ev => set("address_city", ev.target.value) })),
          e("label", null, "State", e("input", { maxLength: 120, value: form.address_state, disabled: busy, onChange: ev => set("address_state", ev.target.value) })),
          e("label", null, "PIN code", e("input", { maxLength: 12, value: form.address_pin_code, disabled: busy, onChange: ev => set("address_pin_code", ev.target.value.toUpperCase()) })),
          e("label", null, "Account holder", e("input", { maxLength: 160, value: form.bank_account_holder, disabled: busy, onChange: ev => set("bank_account_holder", ev.target.value) })),
          e("label", null, "Account number", e("input", { maxLength: 34, value: form.bank_account_number, disabled: busy, onChange: ev => set("bank_account_number", ev.target.value.toUpperCase()) })),
          e("label", null, "IFSC", e("input", { maxLength: 11, pattern: "[A-Z]{4}0[A-Z0-9]{6}", value: form.bank_ifsc, disabled: busy, onChange: ev => set("bank_ifsc", ev.target.value.toUpperCase()), placeholder: "HDFC0004321" })),
          e("label", null, "Masked account", e("input", { maxLength: 40, value: form.bank_account_masked, disabled: busy, onChange: ev => set("bank_account_masked", ev.target.value), placeholder: "XXXXXX0987" }))),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", onClick: onClose, children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: busy }, e(Icon, { name: "plus", size: 15 }), busy ? "Creating..." : "Create Vendor"))));
  }

  function ContractorBillModal({ options, measurements, onClose, onSaved, toast }) {
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      contractor_measurement_id: measurements[0]?.id || "",
      bill_date: today(),
      retention_percent: "5",
      tax_amount: "0",
      deduction_code: "",
      deduction_description: "",
      deduction_amount: "",
      remarks: "RA bill prepared from Contractor Management workspace.",
    });
    const selected = measurements.find(row => String(row.id) === String(form.contractor_measurement_id));
    const set = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!options.can_create_bill || !options.bill_store_url) {
        setError("RA bill creation is not available for this role or company scope.");
        return;
      }
      if (!form.contractor_measurement_id) {
        setError("Select an approved unbilled measurement.");
        return;
      }
      try {
        setBusy(true);
        const deductions = Number(form.deduction_amount || 0) > 0 ? [{
          code: String(form.deduction_code || "OTHER").trim().toUpperCase(),
          description: String(form.deduction_description || "Other deduction").trim(),
          amount: Number(form.deduction_amount),
        }] : [];
        const body = await apiJson(options.bill_store_url, {
          method: "POST",
          body: JSON.stringify({
            contractor_measurement_id: Number(form.contractor_measurement_id),
            bill_date: form.bill_date,
            retention_percent: form.retention_percent === "" ? null : Number(form.retention_percent),
            tax_amount: Number(form.tax_amount || 0),
            deductions,
            remarks: form.remarks || null,
          }),
        });
        onSaved(body.data);
        toast("Contractor bill " + body.data.bill_number + " submitted in Laravel.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Contractor bill could not be created.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", style: { width: 760, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, "Create RA Bill"), e("p", { className: "muted" }, "Creates a Laravel contractor bill from an approved, unbilled measurement with retention, deduction and audit workflow.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error ? e("div", { className: "empty", style: { borderColor: "rgba(239,68,68,.35)", color: "var(--red)", marginBottom: 12 } }, error) : null,
        !measurements.length ? e("div", { className: "empty", style: { marginBottom: 12 } }, "No approved unbilled contractor measurement is available for billing.") : null,
        e("div", { className: "form-grid", style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          e("label", { style: { gridColumn: "1 / -1" } }, "Approved Measurement", e("select", { required: true, value: form.contractor_measurement_id, disabled: busy || !measurements.length, onChange: ev => set("contractor_measurement_id", ev.target.value) },
            measurements.length ? measurements.map(row => e("option", { key: row.id, value: row.id }, [row.measurement_number, row.vendor?.name, row.project?.code || row.project?.name, money(row.certified_total)].filter(Boolean).join(" · "))) : e("option", { value: "" }, "No billable measurement"))),
          e("label", null, "Bill Date", e("input", { type: "date", max: today(), required: true, value: form.bill_date, disabled: busy, onChange: ev => set("bill_date", ev.target.value) })),
          e("label", null, "Certified Amount", e("input", { value: selected ? money(selected.certified_total) : "Select measurement", disabled: true })),
          e("label", null, "Retention %", e("input", { type: "number", min: 0, max: 100, step: 0.01, value: form.retention_percent, disabled: busy, onChange: ev => set("retention_percent", ev.target.value) })),
          e("label", null, "Tax Amount", e("input", { type: "number", min: 0, step: 0.01, value: form.tax_amount, disabled: busy, onChange: ev => set("tax_amount", ev.target.value) })),
          e("label", null, "Deduction Code", e("input", { maxLength: 40, pattern: "[A-Z0-9_\\-.]+", value: form.deduction_code, disabled: busy, onChange: ev => set("deduction_code", ev.target.value.toUpperCase()), placeholder: "OTHER" })),
          e("label", null, "Deduction Amount", e("input", { type: "number", min: 0, step: 0.01, value: form.deduction_amount, disabled: busy, onChange: ev => set("deduction_amount", ev.target.value) })),
          e("label", { style: { gridColumn: "1 / -1" } }, "Deduction Description", e("input", { maxLength: 255, value: form.deduction_description, disabled: busy, onChange: ev => set("deduction_description", ev.target.value), placeholder: "Retention adjustment / material recovery / other deduction" })),
          e("label", { style: { gridColumn: "1 / -1" } }, "Remarks", e("textarea", { maxLength: 3000, value: form.remarks, disabled: busy, onChange: ev => set("remarks", ev.target.value) }))),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: busy, onClick: onClose, children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: busy || !measurements.length }, e(Icon, { name: "doc", size: 15 }), busy ? "Submitting..." : "Submit RA Bill"))));
  }

  function ContractorPaymentModal({ options, bill, onClose, onSaved, toast }) {
    const balance = Number(bill?.balance_amount || bill?.payable_amount || 0);
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      paid_amount: balance > 0 ? String(balance) : "",
      paid_on: today(),
      payment_reference: "PAY-" + Date.now().toString().slice(-6),
      note: "Payment recorded from Contractor Management workspace.",
    });
    const set = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      const amount = Number(form.paid_amount || 0);
      if (!options.can_mark_bill_paid || !options.bill_mark_paid_url_template) {
        setError("Contractor bill payment posting is not available for this role.");
        return;
      }
      if (!bill?.id || amount <= 0 || amount > balance) {
        setError("Enter a payment amount greater than zero and not above the current balance.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(urlFromTemplate(options.bill_mark_paid_url_template, "__BILL__", bill.id), {
          method: "PATCH",
          body: JSON.stringify({
            paid_amount: amount,
            paid_on: form.paid_on,
            payment_reference: String(form.payment_reference || "").trim(),
            note: form.note || null,
          }),
        });
        onSaved(body.data);
        toast("Payment recorded for " + body.data.bill_number + " in Laravel.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Contractor bill payment could not be recorded.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", style: { width: 620, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, "Record Contractor Payment"), e("p", { className: "muted" }, (bill?.bill_number || "Selected bill") + " · Balance " + money(balance))),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error ? e("div", { className: "empty", style: { borderColor: "rgba(239,68,68,.35)", color: "var(--red)", marginBottom: 12 } }, error) : null,
        e("div", { className: "form-grid", style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          e("label", null, "Payment Amount", e("input", { type: "number", min: 0.01, max: balance || undefined, step: 0.01, required: true, value: form.paid_amount, disabled: busy, onChange: ev => set("paid_amount", ev.target.value) })),
          e("label", null, "Paid On", e("input", { type: "date", max: today(), required: true, value: form.paid_on, disabled: busy, onChange: ev => set("paid_on", ev.target.value) })),
          e("label", { style: { gridColumn: "1 / -1" } }, "Payment Reference", e("input", { maxLength: 80, required: true, value: form.payment_reference, disabled: busy, onChange: ev => set("payment_reference", ev.target.value), placeholder: "UTR / cheque / voucher reference" })),
          e("label", { style: { gridColumn: "1 / -1" } }, "Note", e("textarea", { maxLength: 1000, value: form.note, disabled: busy, onChange: ev => set("note", ev.target.value) }))),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: busy, onClick: onClose, children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: busy || balance <= 0 }, e(Icon, { name: "wallet", size: 15 }), busy ? "Posting..." : "Post Payment"))));
  }

  // ================= VENDORS =================
  function Vendors({ toast }) {
    const [sel, setSel] = React.useState(null);
    const [performance, setPerformance] = React.useState(null);
    const [loadingPerformance, setLoadingPerformance] = React.useState(false);
    const [creating, setCreating] = React.useState(false);
    const options = siteOptions();
    const initialVendors = Array.isArray(options.vendors) ? options.vendors : [];
    const [localVendors, setLocalVendors] = React.useState(initialVendors);
    React.useEffect(() => setLocalVendors(initialVendors), [options.vendors]);
    const vendors = localVendors;
    const purchaseOrders = Array.isArray(options.purchase_orders) ? options.purchase_orders : [];
    const summary = options.summary || {};
    const avgRating = vendors.length ? (vendors.reduce((sum, vendor) => sum + Number(vendor.rating || 0), 0) / vendors.length).toFixed(1) : "0";
    const selectedOrders = sel ? purchaseOrders.filter(order => order.vendor && order.vendor.id === sel.id) : [];
    const openVendorModal = () => {
      if (!options.can_create_vendor) {
        toast("You do not have permission to create vendors", "orange");
        return;
      }
      setCreating(true);
    };
    const addVendorRow = (row) => setLocalVendors(prev => [row, ...prev.filter(vendor => vendor.id !== row.id)]);
    const loadPerformance = async () => {
      if (!sel || !options.vendor_performance_url_template) return;
      try {
        setLoadingPerformance(true);
        const data = await apiJson(urlFromTemplate(options.vendor_performance_url_template, "__VENDOR__", sel.id));
        setPerformance(data.data || data);
        toast("Vendor performance loaded from Laravel", "green");
      } catch (error) {
        toast(error.message || "Unable to load vendor performance", "red");
      } finally {
        setLoadingPerformance(false);
      }
    };
    if (sel) return e("div", { className: "page page-wide" },
      e("div", { className: "crumbs" }, e("span", { style: { cursor: "pointer", color: "var(--accent)", fontWeight: 700 }, onClick: () => setSel(null) }, "Vendor Management"), e("span", { className: "sep" }, "/"), e("span", { style: { color: "var(--text-2)" } }, sel.name)),
      e("div", { className: "card card-pad", style: { marginBottom: 16, display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: 12 } },
        e("div", { className: "row gap-4" }, e("button", { className: "icon-btn", onClick: () => setSel(null) }, e(Icon, { name: "chevL", size: 18 })), e(Avatar, { name: sel.name, size: 46 }),
          e("div", null, e("div", { style: { fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 18 } }, sel.name), e("div", { className: "muted", style: { fontWeight: 600 } }, [sel.vendor_code, label(sel.vendor_type), sel.category].filter(Boolean).join(" · ")))),
        e("div", { className: "row gap-2" }, e("span", { className: "badge " + statusTone(sel.status) }, label(sel.status)), e("span", { className: "badge b-green" }, "★ " + Number(sel.rating || 0).toFixed(1) + " rating"), e(Button, { variant: "primary", icon: "chart", onClick: loadPerformance, children: loadingPerformance ? "Loading..." : "Load Performance" }))),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Purchase Orders", value: sel.purchase_orders_count || 0, icon: "cart", tone: "accent" }),
        e(Stat, { label: "Purchase Value", value: money(sel.purchase_value_total), icon: "wallet", tone: "blue" }),
        e(Stat, { label: "Open PO Value", value: money(sel.open_purchase_value), icon: "clock", tone: Number(sel.open_purchase_value || 0) > 0 ? "orange" : "green" }),
        e(Stat, { label: "Quality Rating", value: Number(sel.rating || 0).toFixed(1), unit: "/5", icon: "star", tone: "violet" })),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.6fr 1fr", alignItems: "start" } },
        e(Card, { title: "Purchase History", sub: sel.name },
        T([{ l: "PO" }, { l: "Material" }, { l: "Project" }, { l: "Value", r: true }, { l: "Date" }, { l: "Status" }],
          (selectedOrders.length ? selectedOrders : []).map(order => [e("span", { className: "cell-strong mono" }, order.po_number), (order.items || []).slice(0, 2).map(item => item.description || item.item_name || item.name).filter(Boolean).join(", ") || "PO items", e("span", { className: "tag" }, order.project?.code || order.project?.name || "Project"), e("span", { className: "mono cell-strong" }, money(order.total_amount)), e("span", { className: "faint" }, order.po_date || "not dated"), e(Badge, { tone: statusTone(order.status), dot: true }, label(order.status))]))),
        e(Card, { title: "Vendor API Status", pad: true },
          e("div", { className: "kpi-mini", style: { marginBottom: 8 } }, options.source === "laravel-sqlite" ? "MySQL-backed Laravel payload" : "No backend payload"),
          e("div", { className: "row between", style: { padding: "9px 0", borderBottom: "1px solid var(--border)" } }, e("span", { className: "muted" }, "Index endpoint"), e("span", { className: "mono", style: { fontSize: 12 } }, options.vendors_index_url || "not available")),
          e("div", { className: "row between", style: { padding: "9px 0", borderBottom: "1px solid var(--border)" } }, e("span", { className: "muted" }, "Create permission"), e(Badge, { tone: options.can_create_vendor ? "b-green" : "b-slate" }, options.can_create_vendor ? "Allowed" : "Restricted")),
          performance ? e("pre", { style: { marginTop: 12, whiteSpace: "pre-wrap", maxHeight: 220, overflow: "auto", fontSize: 11 } }, JSON.stringify(performance, null, 2)) : e("div", { className: "muted", style: { marginTop: 12, fontSize: 12.5 } }, "Use Load Performance to query the vendor performance endpoint for this vendor."))),
    );
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Construction", "Vendors"], title: "Vendor Management", sub: "Supplier master, purchase history, payables and performance rating. Click a vendor for full profile.",
        actions: [e(Button, { key: 1, icon: "plus", variant: "primary", onClick: openVendorModal, children: "Add Vendor" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Active Vendors", value: summary.active_vendors || vendors.filter(v => v.status === "active").length, icon: "truck", tone: "accent" }),
        e(Stat, { label: "PO Value MTD", value: money(summary.po_value_mtd), icon: "wallet", tone: "blue" }),
        e(Stat, { label: "Pending GRN", value: summary.pending_grn || 0, icon: "alert", tone: "orange" }),
        e(Stat, { label: "Avg. Rating", value: avgRating, unit: "/5", icon: "star", tone: "green" })),
      e(Card, { title: "Vendor Ledger", action: e(ChipSelect, { value: "All Categories" }) },
        vendors.length ? T([{ l: "Vendor" }, { l: "Category" }, { l: "Purchases", r: true }, { l: "Open PO Value", r: true }, { l: "Rating" }, { l: "Status" }, { l: "" }],
          vendors.map(v => [user(v.name, v.vendor_code || "Vendor"), e("span", { className: "tag" }, v.category || label(v.vendor_type)), e("span", { className: "mono cell-strong" }, money(v.purchase_value_total)), e("span", { className: "mono", style: { color: Number(v.open_purchase_value || 0) === 0 ? "var(--text-3)" : "var(--orange)" } }, money(v.open_purchase_value)), e("span", { className: "mono" }, "★ " + Number(v.rating || 0).toFixed(1)), e(Badge, { tone: statusTone(v.status), dot: true }, label(v.status)),
            e(Button, { sm: true, onClick: () => { setSel(v); setPerformance(null); }, children: "View" })])) : e("div", { className: "empty" }, "No vendor records are visible for your current company/project scope.")),
      creating ? e(VendorCreateModal, { options, onClose: () => setCreating(false), onSaved: addVendorRow, toast }) : null,
    );
  }

  // ================= CONTRACTORS =================
  function Contractors({ toast }) {
    const [localStatus, setLocalStatus] = React.useState({});
    const [creatingBill, setCreatingBill] = React.useState(false);
    const [payingBill, setPayingBill] = React.useState(null);
    const options = boqOptions();
    const contractors = Array.isArray(options.contractors) ? options.contractors : [];
    const initialBills = Array.isArray(options.bills) ? options.bills : [];
    const [localBills, setLocalBills] = React.useState(initialBills);
    React.useEffect(() => setLocalBills(initialBills), [options.bills]);
    const bills = localBills.map(bill => ({ ...bill, status: localStatus[bill.id] || bill.status }));
    const measurements = Array.isArray(options.measurements) ? options.measurements : [];
    const billedMeasurementIds = new Set(bills.map(bill => bill.measurement?.id).filter(Boolean));
    const billableMeasurements = measurements.filter(row => row.status === "approved" && !billedMeasurementIds.has(row.id));
    const summary = options.summary || {};
    const retentionHeld = bills.filter(bill => !["paid", "rejected"].includes(bill.status)).reduce((sum, bill) => sum + Number(bill.retention_amount || 0), 0);
    const openBillModal = () => {
      if (!options.can_create_bill || !options.bill_store_url) {
        toast("RA bill creation is not available for your role or company scope.", "orange");
        return;
      }
      if (!billableMeasurements.length) {
        toast("No approved unbilled contractor measurement is available for RA bill creation.", "orange");
        return;
      }
      setCreatingBill(true);
    };
    const saveBill = (bill) => {
      setLocalBills(rows => [bill, ...rows.filter(row => row.id !== bill.id)]);
    };
    const savePayment = (bill) => {
      setLocalBills(rows => rows.map(row => row.id === bill.id ? bill : row));
      setLocalStatus(prev => {
        const next = { ...prev };
        delete next[bill.id];
        return next;
      });
    };
    const approveBill = async (bill) => {
      try {
        await apiJson(urlFromTemplate(options.bill_approve_url_template, "__BILL__", bill.id), {
          method: "PATCH",
          body: JSON.stringify({ note: "Approved from Contractor Management verification queue." }),
        });
        setLocalStatus(prev => ({ ...prev, [bill.id]: "approved" }));
        toast("Contractor bill approved in Laravel", "green");
      } catch (error) {
        toast(error.message || "Unable to approve bill", "red");
      }
    };
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["Construction", "Contractors"], title: "Contractor Management", sub: "Work orders, bill verification, retention, TDS and contractor payments.",
        actions: [e(Button, { key: 1, icon: "plus", variant: "primary", onClick: openBillModal, children: "Create RA Bill" })] }),
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Active Contractors", value: contractors.filter(contractor => contractor.status === "active").length || contractors.length, icon: "wrench", tone: "accent" }),
        e(Stat, { label: "Measurements", value: measurements.length, icon: "doc", tone: "blue" }),
        e(Stat, { label: "Bills Pending", value: summary.pending_bills || bills.filter(bill => bill.status === "submitted").length, icon: "clock", tone: "orange", sub: money(summary.payable_balance) }),
        e(Stat, { label: "Retention Held", value: money(retentionHeld), icon: "wallet", tone: "violet" })),
      e("div", { className: "grid", style: { gridTemplateColumns: "1.6fr 1fr", alignItems: "start" } },
        e(Card, { title: "Contractor Bills — Verification Queue", sub: "measurement → verify → approve → pay" },
          T([{ l: "Contractor" }, { l: "Work" }, { l: "Project" }, { l: "Bill", r: true }, { l: "TDS", r: true }, { l: "Status" }, { l: "Action" }],
            bills.map(bill => [user(bill.vendor?.name || "Contractor", bill.bill_number), bill.measurement?.measurement_number || "Direct bill", e("span", { className: "tag" }, bill.project?.code || bill.project?.name || "Project"), e("span", { className: "mono cell-strong" }, money(bill.payable_amount)), e("span", { className: "mono faint" }, money(bill.tax_amount)), e(Badge, { tone: statusTone(bill.status), dot: true }, label(bill.status)),
              bill.status === "submitted" && options.can_approve_bill ? e(Button, { sm: true, variant: "primary", onClick: () => approveBill(bill), children: "Approve" }) : ["approved", "partially_paid"].includes(bill.status) && options.can_mark_bill_paid ? e(Button, { sm: true, variant: "success", onClick: () => setPayingBill(bill), children: "Record Payment" }) : e("span", { className: "faint", style: { fontSize: 12 } }, bill.status === "paid" ? "Paid" : "No action")]))),
        e(Card, { title: "Retention Summary", pad: true },
          [["Certified BOQ amount", money(summary.certified_amount)], ["Budget amount", money(summary.budget_amount)], ["Currently held", money(retentionHeld)], ["Payable balance", money(summary.payable_balance)], ["Data source", options.source === "laravel-sqlite" ? "MySQL / Laravel" : "Not available"]].map((r, i) =>
            e("div", { key: i, className: "row between", style: { padding: "10px 0", fontSize: 13, borderBottom: i < 4 ? "1px solid var(--border)" : "none" } }, e("span", { className: "muted" }, r[0]), e("span", { className: "mono", style: { fontWeight: 700 } }, r[1])))),
      ),
      creatingBill ? e(ContractorBillModal, { options, measurements: billableMeasurements, onClose: () => setCreatingBill(false), onSaved: saveBill, toast }) : null,
      payingBill ? e(ContractorPaymentModal, { options, bill: payingBill, onClose: () => setPayingBill(null), onSaved: savePayment, toast }) : null,
    );
  }

  function AdminCompanyCreateModal({ options, onClose, onSaved, toast }) {
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      code: "",
      name: "",
      legal_name: "",
      state: "",
      status: "active",
    });
    const set = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const clean = value => String(value || "").trim();
    const upper = value => clean(value).toUpperCase();
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!options.can_manage_companies || !options.admin_companies_store_url) {
        setError("Company creation is restricted to global administration users.");
        return;
      }
      if (!clean(form.code) || !clean(form.name) || !clean(form.state)) {
        setError("Company code, company name and statutory state are required.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(options.admin_companies_store_url, {
          method: "POST",
          body: JSON.stringify({
            code: upper(form.code),
            name: clean(form.name),
            legal_name: clean(form.legal_name) || null,
            state: upper(form.state),
            status: form.status,
          }),
        });
        const row = {
          ...(body.data || {}),
          counts: {
            branches: body.data?.counts?.branches || 0,
            projects: body.data?.counts?.projects || 0,
            users: body.data?.counts?.users || 0,
          },
        };
        onSaved(row);
        toast("Company " + row.code + " created through Laravel admin governance.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Company could not be created.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", style: { width: 680, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, "Add Company"), e("p", { className: "muted" }, "Creates a governed company master with unique code, statutory state, status control and audit trail.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error ? e("div", { className: "empty", style: { borderColor: "rgba(239,68,68,.35)", color: "var(--red)", marginBottom: 12 } }, error) : null,
        e("div", { className: "form-grid", style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          e("label", null, "Company Code", e("input", { required: true, maxLength: 24, pattern: "[A-Z0-9.\\-]+", value: form.code, disabled: busy, onChange: ev => set("code", ev.target.value.toUpperCase()), placeholder: "B360N" })),
          e("label", null, "Statutory State", e("input", { required: true, maxLength: 8, value: form.state, disabled: busy, onChange: ev => set("state", ev.target.value.toUpperCase()), placeholder: "MH" })),
          e("label", { style: { gridColumn: "1 / -1" } }, "Company Name", e("input", { required: true, maxLength: 255, value: form.name, disabled: busy, onChange: ev => set("name", ev.target.value), placeholder: "Builder360 New Developments Pvt Ltd" })),
          e("label", { style: { gridColumn: "1 / -1" } }, "Legal Name", e("input", { maxLength: 255, value: form.legal_name, disabled: busy, onChange: ev => set("legal_name", ev.target.value), placeholder: "Registered legal name, if different" })),
          e("label", null, "Status", e("select", { value: form.status, disabled: busy, onChange: ev => set("status", ev.target.value) },
            ["active", "inactive"].map(status => e("option", { key: status, value: status }, label(status)))))),
        e("div", { className: "sys-note", style: { marginTop: 12 } },
          e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
          "Unique company code, global-admin access and audit evidence are enforced by Laravel."),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: busy, onClick: onClose, children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: busy }, e(Icon, { name: "plus", size: 15 }), busy ? "Creating..." : "Create Company"))));
  }

  function AdminDataImportModal({ options, companies, onClose, onPreviewed, onPosted, toast }) {
    const importTypes = Array.isArray(options.data_import_types) && options.data_import_types.length ? options.data_import_types : [
      { value: "crm_prospect_inquiries", label: "CRM Prospect Inquiries" },
      { value: "hr_employees", label: "HR Employees" },
    ];
    const templates = options.data_import_templates || {};
    const firstCompany = companies[0] || null;
    const [busy, setBusy] = React.useState(false);
    const [posting, setPosting] = React.useState(false);
    const [error, setError] = React.useState("");
    const [preview, setPreview] = React.useState(null);
    const [file, setFile] = React.useState(null);
    const [form, setForm] = React.useState({
      company_id: firstCompany?.id || "",
      import_type: importTypes[0]?.value || "crm_prospect_inquiries",
      note: "",
    });
    const set = (key, value) => {
      setPreview(null);
      setForm(prev => ({ ...prev, [key]: value }));
    };
    const selectedTemplate = templates[form.import_type] || {};
    const headers = Array.isArray(selectedTemplate.required_headers) ? selectedTemplate.required_headers : [];
    const sampleCsv = selectedTemplate.sample_csv || (headers.length ? headers.join(",") : "");
    const canPost = preview && preview.status === "preview" && Number(preview.invalid_rows || 0) === 0 && options.data_import_post_url_template;
    const copyText = async (text, message) => {
      try {
        await navigator.clipboard.writeText(text);
        toast(message, "green");
      } catch (err) {
        setError("Clipboard copy is not available in this browser session.");
      }
    };
    const downloadSample = () => {
      const blob = new Blob([sampleCsv], { type: "text/csv;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = form.import_type + "-sample.csv";
      link.click();
      URL.revokeObjectURL(url);
    };
    const chooseFile = ev => {
      setPreview(null);
      setFile(ev.target.files?.[0] || null);
    };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!options.can_manage_data_imports || !options.data_imports_preview_url) {
        setError("Data import preview is restricted for this role.");
        return;
      }
      if (!form.company_id) {
        setError("Company is required for data imports.");
        return;
      }
      if (!file) {
        setError("Select a CSV or TXT file before preview.");
        return;
      }
      const fileName = String(file.name || "").toLowerCase();
      if (!fileName.endsWith(".csv") && !fileName.endsWith(".txt")) {
        setError("Only CSV or TXT files are supported.");
        return;
      }
      if (file.size > Number(options.data_import_max_file_size_kb || 512) * 1024) {
        setError("File must be " + (options.data_import_max_file_size_kb || 512) + " KB or smaller.");
        return;
      }
      try {
        setBusy(true);
        const payload = new FormData();
        payload.append("company_id", form.company_id);
        payload.append("import_type", form.import_type);
        payload.append("source_file", file);
        if (form.note.trim()) payload.append("note", form.note.trim());
        const response = await fetch(options.data_imports_preview_url, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": csrf(),
            "X-Requested-With": "XMLHttpRequest",
          },
          body: payload,
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
          const validation = body.errors && Object.values(body.errors).flat().filter(Boolean).join(" ");
          throw new Error(validation || body.message || "Import preview failed.");
        }
        setPreview(body.data);
        onPreviewed(body.data);
        toast("Import preview generated in Laravel.", "green");
      } catch (err) {
        setError(err.message || "Import preview failed.");
      } finally {
        setBusy(false);
      }
    };
    const postImport = async () => {
      if (!canPost) return;
      setError("");
      try {
        setPosting(true);
        const body = await apiJson(urlFromTemplate(options.data_import_post_url_template, "__BATCH__", preview.id), {
          method: "POST",
          body: JSON.stringify({ note: form.note.trim() || "Posted from Admin & Masters data import workspace." }),
        });
        setPreview(body.data);
        onPosted(body.data);
        toast("Import posted to business records.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Import posting failed.");
      } finally {
        setPosting(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy || posting ? undefined : onClose },
      e("form", { className: "modal admin-import-modal", style: { width: 920, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, "Preview Data Import"), e("p", { className: "muted" }, "Upload CSV/TXT, validate rows first, then post only clean preview batches.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy || posting, onClick: onClose }, e(Icon, { name: "x" }))),
        error ? e("div", { className: "admin-modal-error" }, error) : null,
        e("div", { className: "form-grid admin-import-fields" },
          e("label", null, "Company", e("select", { required: true, value: form.company_id, disabled: busy || posting || !companies.length, onChange: ev => set("company_id", ev.target.value) },
            companies.length ? companies.map(company => e("option", { key: company.id || company.code, value: company.id }, company.label || [company.code, company.name].filter(Boolean).join(" · "))) : e("option", { value: "" }, "No company available"))),
          e("label", null, "Import Type", e("select", { required: true, value: form.import_type, disabled: busy || posting, onChange: ev => set("import_type", ev.target.value) },
            importTypes.map(type => e("option", { key: type.value, value: type.value }, type.label)))),
          e("label", { className: "admin-file-drop", style: { gridColumn: "1 / -1" } },
            e("input", { type: "file", accept: ".csv,.txt,text/csv,text/plain", disabled: busy || posting, onChange: chooseFile }),
            e("span", { className: "admin-file-icon" }, e(Icon, { name: "upload", size: 18 })),
            e("span", { className: "admin-file-main" }, file ? file.name : "Choose CSV/TXT file"),
            e("span", { className: "admin-file-sub" }, file ? Math.ceil(file.size / 1024) + " KB selected" : "Drag-ready visual area. Max " + (options.data_import_max_file_size_kb || 512) + " KB.")),
          e("div", { className: "admin-import-template", style: { gridColumn: "1 / -1" } },
            e("div", { className: "admin-import-template-head" },
              e("div", null, e("b", null, "Required Header"), e("span", null, headers.length + " column(s) must match exactly")),
              e("button", { type: "button", className: "linklike", onClick: () => copyText(headers.join(","), "Required header copied.") }, "Copy Header")),
            e("pre", null, headers.join(","))),
          e("div", { className: "admin-import-template", style: { gridColumn: "1 / -1" } },
            e("div", { className: "admin-import-template-head" },
              e("div", null, e("b", null, "Sample CSV"), e("span", null, "Use this format before previewing import files")),
              e("div", { className: "row gap-2" },
                e("button", { type: "button", className: "linklike", onClick: () => copyText(sampleCsv, "Sample CSV copied.") }, "Copy Sample"),
                e("button", { type: "button", className: "linklike", onClick: downloadSample }, "Download"))),
            e("pre", null, sampleCsv)),
          e("label", { style: { gridColumn: "1 / -1" } }, "Note", e("textarea", { maxLength: 1000, value: form.note, disabled: busy || posting, onChange: ev => set("note", ev.target.value), placeholder: "Import purpose, source system reference or reconciliation note." }))),
        preview ? e("div", { className: "admin-preview-panel" },
          e("div", { className: "grid g-4", style: { marginBottom: 14 } },
            e(Stat, { label: "Total Rows", value: preview.total_rows || 0, icon: "doc", tone: "blue" }),
            e(Stat, { label: "Valid Rows", value: preview.valid_rows || 0, icon: "check", tone: "green" }),
            e(Stat, { label: "Invalid Rows", value: preview.invalid_rows || 0, icon: "alert", tone: preview.invalid_rows ? "red" : "green" }),
            e(Stat, { label: "Status", value: label(preview.status), icon: "shield", tone: "violet" })),
          T([{ l: "Row" }, { l: "Record" }, { l: "Status" }, { l: "Errors" }],
            (preview.preview_rows || []).slice(0, 8).map(row => [
              e("span", { className: "mono" }, row.row_number),
              row.name || row.employee_code || row.email || "Row " + row.row_number,
              e(Badge, { tone: row.status === "valid" ? "b-green" : "b-red", dot: true }, label(row.status)),
              e("span", { className: "cell-sub" }, Object.values(row.errors || {}).join(" ") || (row.warnings || []).join(" ") || "—"),
            ]))) : null,
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: busy || posting, onClick: onClose, children: "Cancel" }),
          e("button", { className: "btn", type: "submit", disabled: busy || posting || !companies.length }, busy ? "Previewing..." : "Preview Import"),
          e("button", { className: "btn btn-primary", type: "button", disabled: !canPost || posting, title: canPost ? "Post this clean preview batch" : "Preview a clean file with zero invalid rows before posting.", onClick: postImport }, e(Icon, { name: "upload", size: 15 }), posting ? "Posting..." : canPost ? "Post Import" : "Post after Preview"))));
  }

  function AdminSettingDraftModal({ options, companies, onClose, onSaved, toast }) {
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      company_id: companies[0]?.id || "",
      setting_group: "general",
      setting_key: "",
      label: "",
      description: "",
      value_type: "object",
      value: "{\n  \"enabled\": true\n}",
      effective_from: today(),
    });
    const set = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const parseValue = () => {
      if (["json", "object", "array"].includes(form.value_type)) return JSON.parse(form.value || (form.value_type === "array" ? "[]" : "{}"));
      if (form.value_type === "integer") return parseInt(form.value, 10);
      if (form.value_type === "decimal") return Number(form.value);
      if (form.value_type === "boolean") return ["1", "true", "yes", "on"].includes(String(form.value).trim().toLowerCase());
      return String(form.value || "");
    };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!options.can_manage_settings || !options.system_settings_store_url) {
        setError("System setting draft creation is not available for your role or company scope.");
        return;
      }
      let parsedValue;
      try {
        parsedValue = parseValue();
      } catch (err) {
        setError("Setting value must match the selected value type. JSON/object/array values must be valid JSON.");
        return;
      }
      if (["integer", "decimal"].includes(form.value_type) && !Number.isFinite(Number(parsedValue))) {
        setError("Numeric setting values must contain a valid number.");
        return;
      }
      try {
        setBusy(true);
        const payload = {
          setting_group: String(form.setting_group || "").trim().toLowerCase(),
          setting_key: String(form.setting_key || "").trim().toLowerCase(),
          label: String(form.label || "").trim(),
          description: String(form.description || "").trim() || null,
          value_type: form.value_type,
          value: parsedValue,
          effective_from: form.effective_from || null,
          metadata: { source: "admin_master_setup_modal" },
        };
        if (form.company_id) payload.company_id = Number(form.company_id);
        const body = await apiJson(options.system_settings_store_url, {
          method: "POST",
          body: JSON.stringify(payload),
        });
        onSaved(body.data);
        toast("System setting draft " + (body.data.setting_key || payload.setting_key) + " created in Laravel.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "System setting draft could not be created.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", style: { width: 760, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, "Create Master / Setting Draft"), e("p", { className: "muted" }, "Creates a governed SystemSetting draft through Laravel validation. Activation still requires the configured approval workflow.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error ? e("div", { className: "empty", style: { borderColor: "rgba(239,68,68,.35)", color: "var(--red)", marginBottom: 12 } }, error) : null,
        e("div", { className: "form-grid", style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          e("label", null, "Company", e("select", { value: form.company_id, disabled: busy || !companies.length, onChange: ev => set("company_id", ev.target.value) },
            companies.length ? companies.map(company => e("option", { key: company.id, value: company.id }, company.label || [company.code, company.name].filter(Boolean).join(" · ") || ("Company #" + company.id))) : e("option", { value: "" }, "Current user company"))),
          e("label", null, "Setting Group", e("input", { required: true, maxLength: 80, value: form.setting_group, disabled: busy, onChange: ev => set("setting_group", ev.target.value), placeholder: "general" })),
          e("label", null, "Setting Key", e("input", { required: true, maxLength: 160, pattern: "[a-z0-9_.-]+", value: form.setting_key, disabled: busy, onChange: ev => set("setting_key", ev.target.value.toLowerCase()), placeholder: "general.master_config" })),
          e("label", null, "Label", e("input", { required: true, maxLength: 255, value: form.label, disabled: busy, onChange: ev => set("label", ev.target.value), placeholder: "Master Configuration" })),
          e("label", null, "Value Type", e("select", { value: form.value_type, disabled: busy, onChange: ev => set("value_type", ev.target.value) },
            ["object", "array", "json", "string", "integer", "decimal", "boolean"].map(type => e("option", { key: type, value: type }, label(type))))),
          e("label", null, "Effective From", e("input", { type: "date", value: form.effective_from, disabled: busy, onChange: ev => set("effective_from", ev.target.value) })),
          e("label", { style: { gridColumn: "1 / -1" } }, "Description", e("textarea", { maxLength: 5000, value: form.description, disabled: busy, onChange: ev => set("description", ev.target.value), placeholder: "Purpose, scope and approval context for this configuration." })),
          e("label", { style: { gridColumn: "1 / -1" } }, "Value", e("textarea", { required: true, value: form.value, disabled: busy, onChange: ev => set("value", ev.target.value), style: { minHeight: 150, fontFamily: "var(--font-mono)" }, placeholder: "{\"enabled\": true}" }))),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: busy, onClick: onClose, children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: busy }, e(Icon, { name: "plus", size: 15 }), busy ? "Creating..." : "Create Draft"))));
  }

  function AdminUserCreateModal({ options, companies, roles, onClose, onSaved, toast }) {
    const companyOptions = companies.length ? companies : [];
    const roleOptions = roles.filter(role => role.is_active !== false);
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [form, setForm] = React.useState({
      company_id: companyOptions[0]?.id || "",
      role_id: roleOptions[0]?.id || "",
      name: "",
      email: "",
      password: "",
      status: "active",
    });
    const set = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!options.can_manage_users || !options.admin_users_store_url) {
        setError("User creation is not available for your role or company scope.");
        return;
      }
      if (!form.company_id || !form.role_id) {
        setError("Company and role are required.");
        return;
      }
      if (!form.name.trim() || !form.email.trim() || !form.password) {
        setError("Name, email and password are required.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(options.admin_users_store_url, {
          method: "POST",
          body: JSON.stringify({
            company_id: Number(form.company_id),
            role_id: Number(form.role_id),
            name: form.name.trim(),
            email: form.email.trim(),
            password: form.password,
            status: form.status,
          }),
        });
        onSaved(body.data);
        toast("User account " + body.data.email + " created through Laravel user administration.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "User account could not be created.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal", style: { width: 680, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, "Add User"), e("p", { className: "muted" }, "Creates a Laravel user account with company scope, role validation, password policy, verification email and audit trail.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error ? e("div", { className: "empty", style: { borderColor: "rgba(239,68,68,.35)", color: "var(--red)", marginBottom: 12 } }, error) : null,
        e("div", { className: "form-grid", style: { display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 12 } },
          e("label", null, "Company", e("select", { required: true, value: form.company_id, disabled: busy || !companyOptions.length, onChange: ev => set("company_id", ev.target.value) },
            companyOptions.length ? companyOptions.map(company => e("option", { key: company.id, value: company.id }, company.label || [company.code, company.name].filter(Boolean).join(" · ") || ("Company #" + company.id))) : e("option", { value: "" }, "No company available"))),
          e("label", null, "Role", e("select", { required: true, value: form.role_id, disabled: busy || !roleOptions.length, onChange: ev => set("role_id", ev.target.value) },
            roleOptions.length ? roleOptions.map(role => e("option", { key: role.id, value: role.id }, role.name + " · " + label(role.scope_level))) : e("option", { value: "" }, "No assignable role available"))),
          e("label", null, "Full Name", e("input", { required: true, maxLength: 255, value: form.name, disabled: busy, onChange: ev => set("name", ev.target.value), placeholder: "User full name" })),
          e("label", null, "Email", e("input", { required: true, type: "email", maxLength: 255, value: form.email, disabled: busy, onChange: ev => set("email", ev.target.value), placeholder: "user@company.com" })),
          e("label", null, "Temporary Password", e("input", { required: true, type: "password", minLength: 8, value: form.password, disabled: busy, onChange: ev => set("password", ev.target.value), placeholder: "Use strong temporary password" })),
          e("label", null, "Status", e("select", { value: form.status, disabled: busy, onChange: ev => set("status", ev.target.value) },
            ["active", "inactive", "suspended"].map(status => e("option", { key: status, value: status }, label(status)))))),
        e("div", { className: "sys-note", style: { marginTop: 12 } },
          e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
          "Password strength, company scope, wildcard-role protection and email uniqueness are enforced by Laravel Form Requests."),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: busy, onClick: onClose, children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: busy || !companyOptions.length || !roleOptions.length }, e(Icon, { name: "plus", size: 15 }), busy ? "Creating..." : "Create User"))));
  }

  function AdminRoleModal({ options, roles, modules, role, onClose, onSaved, toast }) {
    const editing = Boolean(role?.id);
    const permissionChoices = Array.from(new Set([
      "*", "dashboard.view", "crm.view", "crm.manage", "sales.view", "sales.manage", "projects.view", "projects.manage",
      "construction.view", "construction.manage", "construction.approve", "finance.view", "finance.manage", "finance.approve",
      "hr.view", "hr.manage", "payroll.view", "payroll.manage", "settings.view", "settings.manage", "settings.approve",
      "users.view", "users.manage", "roles.view", "roles.manage", "audit.view", "reports.view", "reports.manage",
      ...roles.flatMap(row => Array.isArray(row.permissions) ? row.permissions : []),
      ...modules.flatMap(row => Array.isArray(row.required_permissions) ? row.required_permissions : []),
    ].filter(Boolean))).sort();
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const [manualPermission, setManualPermission] = React.useState("");
    const [form, setForm] = React.useState({
      name: role?.name || "",
      slug: role?.slug || "",
      scope_level: role?.scope_level || "company",
      permissions: Array.isArray(role?.permissions) ? role.permissions : [],
      is_active: role?.is_active !== false,
    });
    const set = (key, value) => setForm(prev => ({ ...prev, [key]: value }));
    const slugify = (value) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "").slice(0, 80);
    const togglePermission = (permission) => set("permissions", form.permissions.includes(permission) ? form.permissions.filter(item => item !== permission) : [...form.permissions, permission]);
    const addManualPermission = () => {
      const permission = manualPermission.trim().toLowerCase();
      if (!permission) return;
      if (!/^(\*|[a-z0-9_.-]+)$/.test(permission)) {
        setError("Permission keys may contain lowercase letters, numbers, dot, dash, underscore, or wildcard *.");
        return;
      }
      if (!form.permissions.includes(permission)) {
        set("permissions", [...form.permissions, permission]);
      }
      setManualPermission("");
      setError("");
    };
    const submit = async (ev) => {
      ev.preventDefault();
      setError("");
      if (!options.can_manage_roles) {
        setError("Role management is not available for your role or company scope.");
        return;
      }
      if (!form.name.trim() || !form.slug.trim()) {
        setError("Role name and slug are required.");
        return;
      }
      if (!form.permissions.length) {
        setError("At least one permission is required.");
        return;
      }
      const url = editing
        ? urlFromTemplate(options.admin_role_update_url_template, "__ROLE__", role.id)
        : options.admin_roles_store_url;
      if (!url) {
        setError("Role administration endpoint is not available.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(url, {
          method: editing ? "PATCH" : "POST",
          body: JSON.stringify({
            name: form.name.trim(),
            slug: form.slug.trim(),
            scope_level: form.scope_level,
            permissions: form.permissions,
            is_active: Boolean(form.is_active),
          }),
        });
        onSaved(body.data);
        toast("Role " + body.data.name + " saved through Laravel role administration.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Role could not be saved.");
      } finally {
        setBusy(false);
      }
    };

    return e("div", { className: "scrim", onClick: busy ? undefined : onClose },
      e("form", { className: "modal admin-role-modal", style: { width: 980, maxWidth: "calc(100vw - 32px)" }, onClick: ev => ev.stopPropagation(), onSubmit: submit },
        e("div", { className: "modal-head" },
          e("div", null, e("h2", null, editing ? "Edit Role" : "Add Role"), e("p", { className: "muted" }, "Creates or updates a Laravel role with permission validation, scope controls and audit trail.")),
          e("button", { type: "button", className: "icon-btn", disabled: busy, onClick: onClose }, e(Icon, { name: "x" }))),
        error ? e("div", { className: "admin-modal-error" }, error) : null,
        e("div", { className: "form-grid admin-role-fields" },
          e("label", null, "Role Name", e("input", { required: true, maxLength: 120, value: form.name, disabled: busy, onChange: ev => { set("name", ev.target.value); if (!editing) set("slug", slugify(ev.target.value)); }, placeholder: "Role display name" })),
          e("label", null, "Role Slug", e("input", { required: true, maxLength: 80, pattern: "[a-z0-9_]+", value: form.slug, disabled: busy || editing, onChange: ev => set("slug", slugify(ev.target.value)), placeholder: "role_slug" })),
          e("label", null, "Scope Level", e("select", { required: true, value: form.scope_level, disabled: busy, onChange: ev => set("scope_level", ev.target.value) },
            ["company", "department", "project", "self", "readonly", "partner", "global"].map(scope => e("option", { key: scope, value: scope }, label(scope))))),
          e("label", null, "Status", e("select", { value: form.is_active ? "active" : "inactive", disabled: busy, onChange: ev => set("is_active", ev.target.value === "active") },
            ["active", "inactive"].map(status => e("option", { key: status, value: status }, label(status)))))),
        e("section", { className: "admin-role-permissions" },
          e("div", { className: "admin-role-permissions-head" },
            e("div", null,
              e("div", { className: "admin-role-section-title" }, "Permissions"),
              e("div", { className: "admin-role-section-sub" }, "Select existing permission keys or add a validated custom permission.")),
            e(Badge, { tone: form.permissions.length ? "b-blue" : "b-orange" }, form.permissions.length + " selected")),
          e("div", { className: "admin-permission-grid" },
            permissionChoices.length ? permissionChoices.map(permission => e("label", { key: permission, className: "admin-permission-card" + (form.permissions.includes(permission) ? " selected" : "") },
              e("input", { type: "checkbox", disabled: busy, checked: form.permissions.includes(permission), onChange: () => togglePermission(permission) }),
              e("span", { className: "mono" }, permission))) : e("div", { className: "empty" }, "No permission catalogue is visible in your scope.")),
          e("div", { className: "admin-permission-add" },
            e("input", { value: manualPermission, disabled: busy, onChange: ev => setManualPermission(ev.target.value), onKeyDown: ev => { if (ev.key === "Enter") { ev.preventDefault(); addManualPermission(); } }, placeholder: "custom.permission.key" }),
            e(Button, { type: "button", sm: true, disabled: busy, onClick: addManualPermission, children: "Add Permission" }))),
        e("div", { className: "sys-note admin-role-note" },
          e(Icon, { name: "shield", size: 12, style: { verticalAlign: "-2px", marginRight: 5 } }),
          "Laravel prevents duplicate permissions, unauthorized wildcard permissions, invalid scope levels and self-role deactivation."),
        e("div", { className: "modal-foot" },
          e(Button, { type: "button", disabled: busy, onClick: onClose, children: "Cancel" }),
          e("button", { className: "btn btn-primary", type: "submit", disabled: busy || !form.permissions.length }, e(Icon, { name: "check", size: 15 }), busy ? "Saving..." : editing ? "Save Role" : "Create Role"))));
  }

  // ================= ADMIN & MASTERS =================
  function adminInitialTab() {
    const raw = String(window.location.hash || "");
    const tab = raw.includes("?") ? new URLSearchParams(raw.split("?")[1] || "").get("tab") : "";
    if (["users", "roles"].includes(tab)) return "User Management";
    if (["data-imports", "masters", "settings"].includes(tab)) return "Master Settings";
    if (["companies", "branches", "projects"].includes(tab)) return "Company & Branches";
    if (["approvals", "matrix", "workflows"].includes(tab)) return "Approval Matrix";
    return "User Management";
  }
  const adminTabParam = tab => ({
    "User Management": "users",
    "Company & Branches": "companies",
    "Master Settings": "data-imports",
    "Approval Matrix": "approvals",
  }[tab] || "users");

  function AdminActionCard({ icon, title, sub, meta, badge, badgeTone = "b-blue", primary, secondary, onOpen }) {
    return e("div", {
      className: "card card-pad admin-action-card" + (onOpen ? " clickable" : ""),
      role: onOpen ? "button" : undefined,
      tabIndex: onOpen ? 0 : undefined,
      onClick: onOpen,
      onKeyDown: onOpen ? ev => { if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); onOpen(); } } : undefined,
      style: { minHeight: 154, display: "flex", flexDirection: "column", gap: 12 },
    },
      e("div", { className: "row between", style: { alignItems: "flex-start", gap: 12 } },
        e("div", { className: "row gap-3", style: { alignItems: "flex-start" } },
          e("div", { style: { width: 42, height: 42, borderRadius: 14, background: "var(--accent-soft)", color: "var(--accent)", display: "grid", placeItems: "center", flex: "0 0 auto" } }, e(Icon, { name: icon, size: 19 })),
          e("div", null, e("div", { style: { fontWeight: 850, fontSize: 15 } }, title), e("div", { className: "cell-sub", style: { marginTop: 4, lineHeight: 1.45 } }, sub))),
        badge ? e(Badge, { tone: badgeTone }, badge) : null),
      meta ? e("div", { className: "kpi-mini", style: { lineHeight: 1.45 } }, meta) : null,
      e("div", { className: "row gap-2", style: { marginTop: "auto", flexWrap: "wrap" }, onClick: ev => ev.stopPropagation() }, primary, secondary));
  }

  function AdminTabBar({ tabs, tab, onChange }) {
    return e("div", { className: "tabs", style: { overflowX: "auto", marginBottom: 12 } },
      tabs.map(item => e("button", {
        key: item,
        type: "button",
        className: "tab " + (tab === item ? "on" : ""),
        onClick: () => onChange(item),
        "aria-current": tab === item ? "page" : undefined,
      }, item)));
  }

  function Admin({ toast }) {
    const [tab, setTab] = React.useState(adminInitialTab);
    const [creatingSetting, setCreatingSetting] = React.useState(false);
    const [creatingUser, setCreatingUser] = React.useState(false);
    const [creatingCompany, setCreatingCompany] = React.useState(false);
    const [importingData, setImportingData] = React.useState(false);
    const [editingRole, setEditingRole] = React.useState(null);
    const [createdUsers, setCreatedUsers] = React.useState([]);
    const [createdCompanies, setCreatedCompanies] = React.useState([]);
    const [changedRoles, setChangedRoles] = React.useState([]);
    const [draftSettings, setDraftSettings] = React.useState([]);
    const [dataImports, setDataImports] = React.useState([]);
    const [importLoading, setImportLoading] = React.useState(false);
    const [importError, setImportError] = React.useState("");
    const [importFilters, setImportFilters] = React.useState({ company_id: "all", import_type: "all", status: "all" });
    const [selectedWorkflow, setSelectedWorkflow] = React.useState(null);
    const [chatAccessDraft, setChatAccessDraft] = React.useState(null);
    const [chatAccessSaving, setChatAccessSaving] = React.useState(false);
    const [chatAccessError, setChatAccessError] = React.useState("");
    const tabs = ["User Management", "Company & Branches", "Master Settings", "Approval Matrix"];
    const options = server().admin_governance_options || {};
    const users = [...createdUsers, ...(Array.isArray(options.users) ? options.users : [])];
    const roles = [...changedRoles, ...(Array.isArray(options.roles) ? options.roles : []).filter(row => !changedRoles.some(changed => String(changed.id) === String(row.id)))];
    const modules = Array.isArray(options.modules) ? options.modules : [];
    const settings = [...draftSettings, ...(Array.isArray(options.settings) ? options.settings : [])];
    const importRows = dataImports.length ? dataImports : (Array.isArray(options.data_imports) ? options.data_imports : []);
    const chains = Array.isArray(options.approval_chains) ? options.approval_chains : [];
    const activeWorkflow = selectedWorkflow || chains[0] || null;
    const companies = [...createdCompanies, ...(Array.isArray(server().companies) ? server().companies : []).filter(row => !createdCompanies.some(created => String(created.id || created.code) === String(row.id || row.code)))];
    const projects = Array.isArray(server().projects) ? server().projects : [];
    const summary = options.summary || {};
    const canCreateUser = Boolean(options.can_manage_users && options.admin_users_store_url);
    const canCreateRole = Boolean(options.can_manage_roles && options.admin_roles_store_url);
    const canCreateSetting = Boolean(options.can_manage_settings && options.system_settings_store_url);
    const canCreateCompany = Boolean(options.can_manage_companies && options.admin_companies_store_url);
    const canManageImports = Boolean(options.can_manage_data_imports && options.data_imports_preview_url);
    React.useEffect(() => {
      const syncAdminTab = () => setTab(adminInitialTab());
      window.addEventListener("hashchange", syncAdminTab);
      return () => window.removeEventListener("hashchange", syncAdminTab);
    }, []);
    const openTab = next => {
      setTab(next);
      const nextParam = adminTabParam(next);
      if (window.location.hash.startsWith("#admin")) {
        window.history.replaceState(null, "", window.location.pathname + window.location.search + "#admin?tab=" + nextParam);
      }
    };
    const restrictedToast = message => toast && toast(message, "orange");
    const openUserCreate = () => canCreateUser ? setCreatingUser(true) : restrictedToast("User creation is restricted for this role or company scope.");
    const openRoleCreate = () => canCreateRole ? setEditingRole({}) : restrictedToast("Role creation is restricted for this role.");
    const openCompanyCreate = () => canCreateCompany ? setCreatingCompany(true) : restrictedToast("Company creation is restricted to global administration users.");
    const openSettingCreate = () => canCreateSetting ? setCreatingSetting(true) : restrictedToast("Master setting creation requires System Settings permission.");
    const openImportCreate = () => canManageImports ? setImportingData(true) : restrictedToast("Data import preview requires Settings management permission.");
    const upsertImport = row => setDataImports(rows => [row, ...rows.filter(existing => String(existing.id) !== String(row.id))]);
    const setImportFilter = (key, value) => setImportFilters(prev => ({ ...prev, [key]: value }));
    const loadDataImports = React.useCallback(async () => {
      if (!options.data_imports_index_url || !options.can_view_data_imports) return;
      setImportError("");
      try {
        setImportLoading(true);
        const params = new URLSearchParams({ per_page: "20" });
        if (importFilters.company_id !== "all") params.set("company_id", importFilters.company_id);
        if (importFilters.import_type !== "all") params.set("import_type", importFilters.import_type);
        if (importFilters.status !== "all") params.set("status", importFilters.status);
        const body = await apiJson(options.data_imports_index_url + "?" + params.toString());
        setDataImports(Array.isArray(body.data) ? body.data : []);
      } catch (err) {
        setImportError(err.message || "Unable to load data import batches.");
      } finally {
        setImportLoading(false);
      }
    }, [options.data_imports_index_url, options.can_view_data_imports, importFilters.company_id, importFilters.import_type, importFilters.status]);
    React.useEffect(() => {
      if (tab === "Master Settings") loadDataImports();
    }, [tab, loadDataImports]);
    const postImportBatch = async row => {
      if (!options.data_import_post_url_template || !options.can_manage_data_imports) {
        restrictedToast("Data import posting requires Settings management permission.");
        return;
      }
      try {
        const body = await apiJson(urlFromTemplate(options.data_import_post_url_template, "__BATCH__", row.id), {
          method: "POST",
          body: JSON.stringify({ note: "Posted from Admin & Masters data import register." }),
        });
        upsertImport(body.data);
        toast("Import " + (body.data.import_number || row.import_number) + " posted.", "green");
      } catch (err) {
        toast(err.message || "Import posting failed.", "red");
      }
    };
    const settingsByGroup = settings.reduce((acc, row) => {
      const key = row.setting_group || "general";
      acc[key] = acc[key] || [];
      acc[key].push(row);
      return acc;
    }, {});
    const chatAccessSetting = settings.find(row => row.setting_key === "chat_connect.role_access") || null;
    const chatAccessValue = chatAccessSetting?.value || {};
    const chatAccessCurrent = chatAccessDraft || chatAccessValue.roles || chatAccessValue || server().chat_connect_options?.role_access || {};
    const chatRoleSlug = role => role.slug || role.role_slug || role.id || String(role.name || "").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "");
    const chatCapabilityLabels = [
      ["can_view", "Enabled"],
      ["read_only", "Read-only"],
      ["can_create_dm", "DM"],
      ["can_create_group", "Group"],
      ["can_post", "Post"],
      ["can_upload", "Files"],
      ["can_send_voice", "Voice"],
      ["can_create_poll", "Create polls"],
      ["can_vote_poll", "Vote"],
      ["can_manage_members", "Members"],
    ];
    const chatRoleRows = roles.length
      ? roles.map(role => ({ slug: chatRoleSlug(role), name: role.name, scope: role.scope_level, config: chatAccessCurrent[chatRoleSlug(role)] || {} }))
      : Object.entries(chatAccessCurrent).map(([slug, config]) => ({ slug, name: label(slug), scope: "role", config: config || {} }));
    const normalizeChatRoleConfig = config => {
      const next = { ...(config || {}) };
      if (!next.can_view) {
        return {
          ...next,
          can_view: false,
          read_only: true,
          can_create_dm: false,
          can_create_group: false,
          can_create_channel: false,
          can_post: false,
          can_upload: false,
          can_send_voice: false,
          can_create_poll: false,
          can_vote_poll: false,
          can_manage_members: false,
          can_archive: false,
          can_export: false,
        };
      }
      if (next.read_only) {
        next.can_create_dm = false;
        next.can_create_group = false;
        next.can_create_channel = false;
        next.can_post = false;
        next.can_upload = false;
        next.can_send_voice = false;
        next.can_create_poll = false;
        next.can_manage_members = false;
        next.can_archive = false;
        next.can_export = false;
      }
      if (!next.can_post) {
        next.can_upload = false;
        next.can_send_voice = false;
        next.can_create_poll = false;
      }
      return next;
    };
    const normalizeChatAccessMatrix = matrix => Object.fromEntries(
      Object.entries(matrix || {}).map(([slug, config]) => [slug, normalizeChatRoleConfig(config)])
    );
    const changeChatCapability = (slug, key) => {
      setChatAccessDraft(current => ({
        ...(current || chatAccessCurrent),
        [slug]: normalizeChatRoleConfig({
          ...((current || chatAccessCurrent)[slug] || {}),
          [key]: !Boolean(((current || chatAccessCurrent)[slug] || {})[key]),
        }),
      }));
    };
    const saveChatAccess = async () => {
      if (!options.system_settings_store_url || !canCreateSetting) {
        restrictedToast("Chat Connect access changes require System Settings permission.");
        return;
      }
      setChatAccessSaving(true);
      setChatAccessError("");
      try {
        const body = await apiJson(options.system_settings_store_url, {
          method: "POST",
          body: JSON.stringify({
            company_id: null,
            setting_group: "collaboration",
            setting_key: "chat_connect.role_access",
            label: "Chat Connect Access",
            description: "Role-based Chat Connect access and communication capability controls.",
            value_type: "json",
            value: { roles: normalizeChatAccessMatrix(chatAccessCurrent) },
            metadata: { source: "admin_chat_connect_access" },
          }),
        });
        setDraftSettings(rows => [body.data, ...rows.filter(row => String(row.id) !== String(body.data.id))]);
        setChatAccessDraft(null);
        toast("Chat Connect access setting draft saved for approval.", "green");
      } catch (err) {
        setChatAccessError(err.message || "Chat Connect access setting could not be saved.");
        toast(err.message || "Chat Connect access setting could not be saved.", "red");
      } finally {
        setChatAccessSaving(false);
      }
    };
    const usersTab = e(Card, { title: "User Management & Role Management", sub: options.source === "laravel-sqlite" ? "MySQL-scoped user and role catalogue inside the approved UI" : "No authorized admin payload", action: e("div", { className: "row gap-2" },
        e(Button, { sm: true, icon: "shield", disabled: !canCreateRole, title: canCreateRole ? "Create role through Laravel role administration" : "Role creation is not available for this role.", onClick: openRoleCreate, children: "Add Role" }),
        e(Button, { sm: true, icon: "plus", variant: "primary", disabled: !canCreateUser, title: canCreateUser ? "Create user through Laravel user administration" : "User creation is not available for this role or company scope.", onClick: openUserCreate, children: "Add User" })) },
      users.length ? T([{ l: "User" }, { l: "Role" }, { l: "Company" }, { l: "Scope" }, { l: "Last Updated" }, { l: "Status" }],
        users.map(row => [user(row.name, row.email), e(Badge, { tone: "b-accent" }, row.role?.name || "No role"), row.company?.code || "Global", e("span", { className: "tag" }, label(row.role?.scope_level)), e("span", { className: "faint" }, row.last_active_label), e(Badge, { tone: statusTone(row.status), dot: true }, label(row.status))])) : e("div", { className: "empty" }, "No user records are visible for your current permissions and company scope."),
      roles.length ? e("div", { style: { marginTop: 16 } }, T([{ l: "Role" }, { l: "Scope" }, { l: "Permissions", r: true }, { l: "Users", r: true }, { l: "Status" }, { l: "Action" }],
        roles.map(role => [e("span", { className: "cell-strong" }, role.name), e("span", { className: "tag" }, label(role.scope_level)), e("span", { className: "mono" }, role.permissions_count ?? (role.permissions || []).length), e("span", { className: "mono" }, role.users_count || 0), e(Badge, { tone: role.is_active ? "b-green" : "b-slate", dot: true }, role.is_active ? "Active" : "Inactive"), options.can_manage_roles && options.admin_role_update_url_template ? e("button", { className: "linklike", onClick: () => setEditingRole(role) }, "Edit") : e("span", { className: "faint" }, "Restricted")])) ) : null);
    const companyTab = e("div", null,
      e(Card, { title: "Company & Branches", sub: "Company masters remain inside Admin & Masters. Branch and project setup stay inside their parent governed workspaces.", action: e(Button, { sm: true, icon: "plus", variant: "primary", disabled: !canCreateCompany, title: canCreateCompany ? "Create company through Laravel admin governance" : "Company creation is restricted to global administration users.", onClick: openCompanyCreate, children: "Add Company" }) },
        e("div", { className: "grid g-3" },
          companies.length ? companies.map((company, i) => e("div", { key: "company-" + (company.id || company.code || i), className: "card card-pad" },
            e("div", { className: "row between", style: { marginBottom: 8 } }, e("div", { style: { fontWeight: 800, fontSize: 15 } }, company.name || company.code), e(Badge, { tone: statusTone(company.status), dot: true }, company.status ? label(company.status) : "Company")),
            e("div", { className: "muted", style: { fontSize: 12.5 } }, e(Icon, { name: "pin", size: 12, style: { verticalAlign: -1 } }), " ", company.state ? "State: " + company.state : "State not recorded"),
            e("div", { className: "kpi-mini", style: { marginTop: 8 } }, "Code: " + (company.code || "Not recorded") + " · Visible projects: " + (company.counts?.projects ?? projects.filter(project => String(project.company_id || "") === String(company.id || "") || project.company === company.code).length)))) : e("div", { className: "empty", style: { gridColumn: "1 / -1" } }, "No company records are visible for your current permissions."),
          projects.slice(0, 8).map((project, i) => e("div", { key: "project-" + (project.id || project.code || i), className: "card card-pad" },
            e("div", { className: "row between", style: { marginBottom: 8 } }, e("div", { style: { fontWeight: 800, fontSize: 15 } }, project.name), e(Badge, { tone: "b-blue" }, project.code)),
            e("div", { className: "muted", style: { fontSize: 12.5 } }, [project.company, project.city || "Project city not recorded"].filter(Boolean).join(" · ")),
            e("div", { className: "kpi-mini", style: { marginTop: 8 } }, "Status: " + label(project.status)))))));
    const importTypeLabel = value => (options.data_import_types || []).find(type => type.value === value)?.label || label(value);
    const importStatuses = Array.isArray(options.data_import_statuses) ? options.data_import_statuses : [];
    const mastersTab = e("div", { className: "grid g-2", style: { alignItems: "start" } },
      e(Card, { title: "Data Import Register", sub: "Preview-first CSV/TXT import workflow backed by Laravel validation, reconciliation and audit trail.", action: e("div", { className: "row gap-2" },
          e(Button, { sm: true, icon: "refresh", disabled: importLoading || !options.can_view_data_imports, onClick: loadDataImports, children: importLoading ? "Loading..." : "Refresh" }),
          e(Button, { sm: true, icon: "upload", variant: "primary", disabled: !canManageImports, onClick: openImportCreate, children: "Preview Import" })) },
        e("div", { className: "row gap-2", style: { flexWrap: "wrap", marginBottom: 12 } },
          e("select", { className: "chip-select", value: importFilters.company_id, disabled: importLoading || !companies.length, onChange: ev => setImportFilter("company_id", ev.target.value), "aria-label": "Filter imports by company" },
            e("option", { value: "all" }, "All Companies"),
            companies.map(company => e("option", { key: company.id || company.code, value: company.id }, company.code + " · " + company.name))),
          e("select", { className: "chip-select", value: importFilters.import_type, disabled: importLoading, onChange: ev => setImportFilter("import_type", ev.target.value), "aria-label": "Filter imports by type" },
            e("option", { value: "all" }, "All Import Types"),
            (options.data_import_types || []).map(type => e("option", { key: type.value, value: type.value }, type.label))),
          e("select", { className: "chip-select", value: importFilters.status, disabled: importLoading, onChange: ev => setImportFilter("status", ev.target.value), "aria-label": "Filter imports by status" },
            e("option", { value: "all" }, "All Statuses"),
            importStatuses.map(status => e("option", { key: status.value, value: status.value }, status.label))),
          e(Badge, { tone: options.can_view_data_imports ? "b-green" : "b-orange" }, options.can_view_data_imports ? "Laravel register" : "Restricted")),
        importError ? e("div", { className: "empty", style: { borderColor: "rgba(239,68,68,.35)", color: "var(--red)", marginBottom: 12 } }, importError) : null,
        importRows.length ? T([{ l: "Import" }, { l: "Company" }, { l: "Type" }, { l: "Rows", r: true }, { l: "Status" }, { l: "Created" }, { l: "Action" }],
          importRows.map(row => [
            user(row.import_number || "Import", row.source_filename || "No file name"),
            row.company?.code || "Scoped",
            e("span", { className: "tag" }, importTypeLabel(row.import_type)),
            e("span", { className: "mono" }, `${row.valid_rows || 0}/${row.total_rows || 0}`),
            e(Badge, { tone: row.status === "posted" ? "b-green" : row.status === "failed" ? "b-red" : "b-orange", dot: true }, label(row.status)),
            e("span", { className: "faint" }, row.created_at ? new Date(row.created_at).toLocaleDateString("en-IN") : "—"),
            row.status === "preview" && Number(row.invalid_rows || 0) === 0 && options.can_manage_data_imports
              ? e("button", { className: "linklike", onClick: () => postImportBatch(row) }, "Post")
              : e("span", { className: "faint" }, row.status === "preview" ? "Fix errors" : "No action"),
          ])) : e("div", { className: "empty" }, importLoading ? "Loading data import batches..." : "No data import batches are visible for the selected filters.")),
      e(Card, { title: "Chat Connect Access", sub: "Enable Chat Connect by role and control file, voice, poll and member capabilities from System Settings.", action: e("div", { className: "row gap-2" },
          e(Button, { sm: true, icon: "refresh", disabled: !chatAccessDraft, onClick: () => { setChatAccessDraft(null); setChatAccessError(""); }, children: "Reset" }),
          e(Button, { sm: true, icon: "check", variant: "primary", disabled: !canCreateSetting || chatAccessSaving || !chatAccessDraft, onClick: saveChatAccess, children: chatAccessSaving ? "Saving..." : "Save Draft" })) },
        chatAccessError ? e("div", { className: "empty", style: { borderColor: "rgba(239,68,68,.35)", color: "var(--red)", marginBottom: 12 } }, chatAccessError) : null,
        e("div", { className: "admin-workflow-controls", style: { marginBottom: 12 } },
          e("div", null, e("span", null, "Setting Key"), e("b", null, "chat_connect.role_access")),
          e("div", null, e("span", null, "Current Status"), e(Badge, { tone: chatAccessSetting?.status === "active" ? "b-green" : "b-orange", dot: true }, chatAccessSetting?.status ? label(chatAccessSetting.status) : "Default")),
          e("div", null, e("span", null, "Edit Method"), e("b", null, canCreateSetting ? "Draft setting update" : "Read-only for current role"))),
        chatRoleRows.length ? e("div", { className: "grid g-2" },
          chatRoleRows.map(row => e("div", { key: row.slug, className: "card card-pad" },
            e("div", { className: "row between", style: { marginBottom: 10, gap: 10 } },
              e("div", null,
                e("div", { className: "cell-strong" }, row.name || label(row.slug)),
                e("div", { className: "cell-sub" }, row.slug, row.scope ? " · " + label(row.scope) : "")),
              e(Badge, { tone: row.config?.can_view ? (row.config?.read_only ? "b-orange" : "b-green") : "b-slate", dot: true }, row.config?.can_view ? (row.config?.read_only ? "Read-only" : "Enabled") : "Disabled")),
            e("div", { className: "row gap-2", style: { flexWrap: "wrap" } },
              chatCapabilityLabels.map(([key, text]) => e("button", {
                key,
                type: "button",
                className: "badge " + (row.config?.[key] ? (key === "read_only" ? "b-orange" : "b-green") : "b-slate"),
                disabled: !canCreateSetting || chatAccessSaving,
                onClick: () => changeChatCapability(row.slug, key),
                title: canCreateSetting ? "Toggle " + text + " for " + (row.name || label(row.slug)) : "System Settings permission required",
              }, text)))))) : e("div", { className: "empty" }, "No role access rows are visible for Chat Connect.")),
      e("div", { className: "grid g-4" },
        Object.entries(settingsByGroup).map(([group, rows]) => e("div", { key: "setting-" + group, className: "card card-pad" },
          e("div", { className: "row between" }, e("div", { className: "row gap-3" }, e("div", { style: { width: 36, height: 36, borderRadius: 10, background: "var(--surface-3)", color: "var(--accent)", display: "grid", placeItems: "center" } }, e(Icon, { name: "sliders", size: 17 })), e("div", null, e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, label(group) + " Settings"), e("div", { className: "kpi-mini" }, rows.length + " versioned setting records"))), e(Badge, { tone: rows.some(row => row.status === "draft") ? "b-orange" : "b-green" }, rows.some(row => row.status === "draft") ? "Draft pending" : "Active")))),
        modules.slice(0, 12).map((module, i) => e("div", { key: "module-" + i, className: "card card-pad" },
          e("div", { className: "row between" }, e("div", { className: "row gap-3" }, e("div", { style: { width: 36, height: 36, borderRadius: 10, background: "var(--surface-3)", color: "var(--accent)", display: "grid", placeItems: "center" } }, e(Icon, { name: module.icon || "box", size: 17 })), e("div", null, e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, module.name), e("div", { className: "kpi-mini" }, module.group_name + " · " + (module.required_permissions || []).join(", ")))))))));
    const matrixTab = e("div", { className: "admin-approval-layout" },
      e(Card, { title: "Approval Matrix", sub: "Select a workflow to inspect its configured approval order.", action: e(Button, { sm: true, icon: "gear", disabled: !canCreateSetting, title: canCreateSetting ? "Draft workflow.approval_chains setting update" : "System setting creation is restricted.", onClick: openSettingCreate, children: "Draft Workflow Setting" }) },
        chains.length ? e("div", { className: "admin-workflow-grid" },
          chains.map(row => e("button", {
            key: row.workflow,
            type: "button",
            className: "admin-workflow-card" + (activeWorkflow?.workflow === row.workflow ? " selected" : ""),
            onClick: () => setSelectedWorkflow(row),
          },
            e("span", { className: "admin-workflow-icon" }, e(Icon, { name: "funnel", size: 17 })),
            e("span", { className: "admin-workflow-main" }, label(row.workflow)),
            e("span", { className: "admin-workflow-sub" }, (row.steps || []).length + " approval step(s) configured"),
            e("span", { className: "admin-workflow-steps" }, (row.steps || []).slice(0, 3).map(step => e("span", { key: step }, label(step))))))) : e("div", { className: "empty" }, "No active approval-chain setting is visible in your scope.")),
      e(Card, { title: activeWorkflow ? "Workflow Detail · " + label(activeWorkflow.workflow) : "Workflow Detail", sub: "Approval sequence, segregation visibility and configuration source." },
        activeWorkflow ? e("div", { className: "admin-workflow-detail" },
          e("div", { className: "admin-workflow-timeline" },
            (activeWorkflow.steps || []).map((step, index) => e("div", { key: step + index, className: "admin-workflow-step" },
              e("div", { className: "admin-workflow-step-no" }, index + 1),
              e("div", null,
                e("b", null, label(step)),
                e("span", null, index === 0 ? "First-level review / maker-checker start" : index === (activeWorkflow.steps || []).length - 1 ? "Final approval / closure control" : "Intermediate approval gate"))))),
          e("div", { className: "admin-workflow-controls" },
            e("div", null, e("span", null, "Configuration Status"), e(Badge, { tone: "b-green", dot: true }, "Active setting")),
            e("div", null, e("span", null, "Source Setting"), e("b", null, "workflow.approval_chains")),
            e("div", null, e("span", null, "Edit Method"), e("b", null, canCreateSetting ? "Draft governed setting update" : "Read-only for current role"))),
          e("div", { className: "row gap-2", style: { marginTop: 14, flexWrap: "wrap" } },
            e(Button, { sm: true, icon: "gear", disabled: !canCreateSetting, onClick: openSettingCreate, children: "Draft Change" }),
            e(Button, { sm: true, icon: "chevR", onClick: () => openTab("Master Settings"), children: "Open Master Settings" }))) : e("div", { className: "empty" }, "Select a workflow to view its approval sequence.")));
    const actionStrip = e("div", { className: "grid g-4", style: { marginBottom: 16 } },
      e(AdminActionCard, {
        icon: "users",
        title: "User Management",
        sub: "Create users, assign company scope, map roles and control account status from the approved UI.",
        meta: `${users.length} visible users · ${canCreateUser ? "Create allowed" : "Read-only"}`,
        badge: tab === "User Management" ? "OPEN" : "Admin",
        badgeTone: tab === "User Management" ? "b-green" : "b-blue",
        onOpen: () => openTab("User Management"),
        primary: e(Button, { sm: true, icon: "chevR", variant: tab === "User Management" ? "primary" : undefined, onClick: () => openTab("User Management"), children: "Open Users" }),
        secondary: e(Button, { sm: true, icon: "plus", disabled: !canCreateUser, onClick: openUserCreate, children: "Add User" }),
      }),
      e(AdminActionCard, {
        icon: "shield",
        title: "Role Management",
        sub: "Maintain role scope and permission catalogues without leaving the approved Builder360 shell.",
        meta: `${roles.length} visible roles · ${canCreateRole ? "Create/edit allowed" : "Restricted"}`,
        badge: "Governed",
        badgeTone: "b-violet",
        onOpen: () => openTab("User Management"),
        primary: e(Button, { sm: true, icon: "chevR", onClick: () => openTab("User Management"), children: "Open Roles" }),
        secondary: e(Button, { sm: true, icon: "plus", disabled: !canCreateRole, onClick: openRoleCreate, children: "Add Role" }),
      }),
      e(AdminActionCard, {
        icon: "upload",
        title: "Data Imports",
        sub: "Import controls are grouped under Master Settings so placeholder sidebar links are not exposed.",
        meta: `${summary.data_import_batches || importRows.length || 0} import batch(es) · ${summary.preview_imports || 0} awaiting post`,
        badge: tab === "Master Settings" ? "OPEN" : "Inside Masters",
        badgeTone: tab === "Master Settings" ? "b-green" : "b-slate",
        onOpen: () => openTab("Master Settings"),
        primary: e(Button, { sm: true, icon: "chevR", variant: tab === "Master Settings" ? "primary" : undefined, onClick: () => openTab("Master Settings"), children: "Open Imports" }),
        secondary: e(Button, { sm: true, icon: "upload", disabled: !canManageImports, onClick: openImportCreate, children: "Preview Import" }),
      }),
      e(AdminActionCard, {
        icon: "funnel",
        title: "Approval Matrix",
        sub: "Review approval chains from governed System Settings records.",
        meta: `${chains.length} visible workflow chain(s)`,
        badge: tab === "Approval Matrix" ? "OPEN" : "Workflow",
        badgeTone: tab === "Approval Matrix" ? "b-green" : "b-orange",
        onOpen: () => openTab("Approval Matrix"),
        primary: e(Button, { sm: true, icon: "chevR", variant: tab === "Approval Matrix" ? "primary" : undefined, onClick: () => openTab("Approval Matrix"), children: "Open Matrix" }),
        secondary: e(Button, { sm: true, icon: "gear", disabled: !canCreateSetting, onClick: openSettingCreate, children: "Draft Setting" }),
      }));
    const content = { "User Management": usersTab, "Company & Branches": companyTab, "Master Settings": mastersTab, "Approval Matrix": matrixTab }[tab];
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["System", "Admin & Masters"], title: "Admin & Master Setup", sub: "Foundational masters, users, roles and approval matrix configuration.",
        actions: [e(Button, { key: 1, icon: "plus", variant: "primary", disabled: !canCreateSetting, title: canCreateSetting ? "Create a governed SystemSetting draft" : "System setting creation is not available for this role or company scope.", onClick: openSettingCreate, children: "Add Master" })] }),
      e("div", { className: "hrx-demo-banner", style: { marginBottom: 16 } },
        e(Icon, { name: "shield", size: 17 }),
        e("div", null,
          e("b", null, "Current location: System → Admin & Masters → ", tab),
          e("span", null, "User Management, Role Management and Data Imports are internal tabs/actions here. They are not separate left-sidebar modules.")),
        e(Badge, { tone: "b-green" }, "APPROVED UI")),
      actionStrip,
      e("div", { className: "grid g-4", style: { marginBottom: 16 } },
        e(Stat, { label: "Active Users", value: summary.active_users || 0, icon: "id", tone: "accent" }),
        e(Stat, { label: "Roles", value: summary.roles || 0, icon: "shield", tone: "blue" }),
        e(Stat, { label: "Active Settings", value: summary.active_settings || 0, icon: "sliders", tone: "violet" }),
        e(Stat, { label: "Data Imports", value: summary.data_import_batches || importRows.length || 0, icon: "upload", tone: "orange" })),
      e(AdminTabBar, { tabs, tab, onChange: openTab }), content,
      creatingUser ? e(AdminUserCreateModal, { options, companies, roles, onClose: () => setCreatingUser(false), onSaved: row => setCreatedUsers(rows => [{ ...row, last_active_label: "just now" }, ...rows.filter(existing => existing.id !== row.id)]), toast }) : null,
      creatingCompany ? e(AdminCompanyCreateModal, { options, onClose: () => setCreatingCompany(false), onSaved: row => setCreatedCompanies(rows => [row, ...rows.filter(existing => String(existing.id || existing.code) !== String(row.id || row.code))]), toast }) : null,
      importingData ? e(AdminDataImportModal, { options, companies, onClose: () => setImportingData(false), onPreviewed: upsertImport, onPosted: upsertImport, toast }) : null,
      editingRole ? e(AdminRoleModal, { options, roles, modules, role: editingRole.id ? editingRole : null, onClose: () => setEditingRole(null), onSaved: row => setChangedRoles(rows => [{ ...row, permissions_count: (row.permissions || []).length }, ...rows.filter(existing => String(existing.id) !== String(row.id))]), toast }) : null,
      creatingSetting ? e(AdminSettingDraftModal, { options, companies, onClose: () => setCreatingSetting(false), onSaved: row => setDraftSettings(rows => [row, ...rows.filter(existing => existing.id !== row.id)]), toast }) : null,
    );
  }

  // ================= SETTINGS =================
  function settingsInitialTab() {
    const raw = String(window.location.hash || "");
    const tab = raw.includes("?") ? new URLSearchParams(raw.split("?")[1] || "").get("tab") : "";
    if (tab === "notifications") return "Notifications";
    if (tab === "security") return "Security";
    if (tab === "organization") return "Organization";
    return "Integrations";
  }

  function Settings({ toast }) {
    const [tab, setTab] = React.useState(settingsInitialTab);
    const tabs = ["Organization", "Security", "Integrations", "Notifications"];
    const options = server().admin_governance_options || {};
    const settings = Array.isArray(options.settings) ? options.settings : [];
    const companies = Array.isArray(server().companies) ? server().companies : [];
    const roles = Array.isArray(options.roles) ? options.roles : [];
    const backup = options.backup_dr?.value || {};
    const getSetting = (key) => settings.find(row => row.setting_key === key);
    const orgTab = e("div", { className: "grid g-2", style: { alignItems: "start" } },
      e(Card, { title: "Organization", pad: true }, (companies.length ? companies : [{ name: "No company visible", code: "N/A" }]).slice(0, 4).map((company, i) =>
        e("div", { key: i, style: { padding: "11px 0", borderBottom: i < Math.min(companies.length || 1, 4) - 1 ? "1px solid var(--border)" : "none" } }, e("div", { className: "kpi-mini", style: { marginBottom: 3 } }, company.code || "Company"), e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, company.name || "Company not available")))),
      e(Card, { title: "Configuration Source", pad: true }, [["Database", server().app?.database || "unknown"], ["Settings Endpoint", options.system_settings_index_url || "Not authorized"], ["Active Settings", options.summary?.active_settings || 0], ["Draft Settings", options.summary?.draft_settings || 0], ["Payload", options.source === "laravel-sqlite" ? "Laravel" : "Unavailable"]].map((r, i) =>
        e("div", { key: i, className: "row between", style: { padding: "11px 0", borderBottom: i < 4 ? "1px solid var(--border)" : "none", fontSize: 13 } }, e("span", { className: "muted" }, r[0]), e("span", { style: { fontWeight: 700 } }, r[1])))));
    const secTab = e(Card, { title: "Security, Access and Backup/DR Metadata" },
      [["Role catalogue", roles.length + " role(s) visible", options.can_view_roles], ["User administration", options.can_manage_users ? "Create/update access permitted" : "Read-only or restricted", options.can_manage_users], ["System settings approval", options.can_approve_settings ? "Approval permission granted" : "Approval permission restricted", options.can_approve_settings], ["Backup schedule", backup.schedule ? backup.schedule + " · RPO " + backup.rpo_hours + "h · RTO " + backup.rto_hours + "h" : "Not configured in visible settings", Boolean(backup.schedule)], ["Operational backup jobs", "Metadata only; production backup execution is deployment-specific", false]].map((r, i) =>
        e("div", { key: i, className: "row between", style: { padding: "14px 16px", borderBottom: i < 4 ? "1px solid var(--border)" : "none" } },
          e("div", null, e("div", { style: { fontWeight: 700, fontSize: 13.5 } }, r[0]), e("div", { className: "cell-sub" }, r[1])),
          e("span", { className: "badge " + (r[2] ? "b-green" : "b-slate") }, r[2] ? "Configured" : "Restricted"))));
    const intTab = e(Card, { title: "Business Configuration and Integration-Ready Settings", sub: "These are governed settings records, not live third-party service health checks." },
      settings.length ? T([{ l: "Setting" }, { l: "Group" }, { l: "Version" }, { l: "Status" }, { l: "Effective From" }],
        settings.map(row => [e("span", { className: "cell-strong" }, row.label || row.setting_key), e("span", { className: "tag" }, label(row.setting_group)), e("span", { className: "mono" }, "v" + row.version), e(Badge, { tone: statusTone(row.status), dot: true }, label(row.status)), e("span", { className: "faint" }, row.effective_from || "not set")])) : e("div", { className: "empty" }, "No system settings are visible for your current role."));
    const notifSetting = getSetting("workflow.approval_chains");
    const notifTab = e(Card, { title: "Notification and Workflow Triggers", sub: "Approval and alert behaviour is driven by governed settings and business events." },
      T([{ l: "Event Area" }, { l: "Configured Source" }, { l: "Status" }, { l: "Notes" }],
        [["Approval pending", notifSetting?.setting_key || "workflow.approval_chains", notifSetting?.status || "not configured", "In-app notifications are created by workflow services."], ["Low stock", "Stock threshold records", "active", "Generated from real stock minimum levels."], ["Document expiry", "Managed document expiry dates", "active", "Shown when documents are expiring or expired."], ["Backup/DR", options.backup_dr?.setting_key || "governance.backup_dr", options.backup_dr?.status || "not configured", "Metadata only; operational backup service is deployment-specific."]]
        .map(r => [e("span", { className: "cell-strong" }, r[0]), r[1], e(Badge, { tone: r[2] === "active" ? "b-green" : "b-slate", dot: true }, label(r[2])), e("span", { className: "faint" }, r[3])])));
    const content = { Organization: orgTab, Security: secTab, Integrations: intTab, Notifications: notifTab }[tab];
    React.useEffect(() => {
      const syncSettingsTab = () => setTab(settingsInitialTab());
      window.addEventListener("hashchange", syncSettingsTab);
      return () => window.removeEventListener("hashchange", syncSettingsTab);
    }, []);
    return e("div", { className: "page page-wide" },
      e(PageHead, { crumbs: ["System", "Settings"], title: "Settings", sub: "Organization, security, integrations and notification preferences." }),
      tabBar(tabs, tab, setTab), content,
    );
  }

  Object.assign(window, { Vendors, Contractors, Admin, Settings });
})();
