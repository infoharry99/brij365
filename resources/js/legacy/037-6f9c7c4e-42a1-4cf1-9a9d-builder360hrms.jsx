const React = window.React;

/* Builder360 HR & Employees — Laravel-governed HR module shell. */
(function () {
  const {
    Icon, Avatar, Badge, Stat, Button, Card, Bar, ProgCell, PageHead, SearchablePeoplePicker,
  } = window;

  const clone = value => JSON.parse(JSON.stringify(value));
  const nowIso = () => new Date().toISOString();
  const money = value => "₹" + Number(value || 0).toLocaleString("en-IN");
  const uid = prefix => prefix + "-" + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

  const HR_NAV = [
    ["dashboard", "HR Dashboard", "grid"], ["employees", "Employee Master", "users"],
    ["attendance", "Attendance", "check"], ["shifts", "Shifts & Rosters", "calendar"],
    ["leave", "Leave Management", "calClock"], ["payroll", "Payroll", "wallet"],
    ["recruitment", "Recruitment", "funnel"], ["performance", "Performance", "star"],
    ["lifecycle", "Employee Lifecycle", "trend"], ["documents", "Documents", "folder"],
    ["assets", "Asset Management", "box"], ["claims", "Claims", "receipt"],
    ["loans", "Loans & Advances", "rupee"], ["helpdesk", "HR Helpdesk", "headset"],
    ["compliance", "Compliance Center", "shield"], ["reports", "Reports & MIS", "chart"],
    ["settings", "HR Settings", "gear"],
  ];

  const PERMISSIONS = {
    director: { label: "Super Admin", scope: "All companies", salary: true, approve: true, configure: true },
    hr: { label: "HR Admin", scope: "All employees", salary: true, approve: true, configure: true },
    hradmin: { label: "HR Admin", scope: "All employees", salary: true, approve: true, configure: true },
    hrexec: { label: "HR Executive", scope: "All employees", salary: false, approve: false, configure: false },
    payroll: { label: "Payroll Admin", scope: "Payroll fields", salary: true, approve: false, configure: true },
    finance: { label: "Finance Manager", scope: "Financial fields", salary: true, approve: true, configure: false },
    recruiter: { label: "Recruiter", scope: "Candidates only", salary: false, approve: false, configure: false },
    auditor: { label: "Auditor", scope: "Read-only", salary: false, approve: false, configure: false },
    compliance: { label: "Compliance Officer", scope: "Compliance fields", salary: true, approve: true, configure: true },
    asset: { label: "Asset Manager", scope: "Assets only", salary: false, approve: false, configure: false },
    construction: { label: "Department Head", scope: "Construction team", salary: false, approve: true, configure: false },
    sales: { label: "Department Head", scope: "Sales team", salary: false, approve: true, configure: false },
    channel_partner: { label: "Channel Partner", scope: "Partner portal", salary: false, approve: false, configure: false },
    executive_partner_broker: { label: "Executive Partner (Broker)", scope: "Partner portal", salary: false, approve: false, configure: false },
    employee: { label: "Employee", scope: "Self only", salary: true, approve: false, configure: false },
  };

  const serverRequiredState = () => ({
    meta: { schema: 2, source: "laravel-server-required", loadedAt: nowIso(), updatedAt: nowIso() },
    organization: { group: "", companies: [], branches: [], sites: [] },
    employees: [],
    leaveRequests: [],
    attendance: { summary: { present: 0, late: 0, halfDay: 0, absent: 0 }, sites: [], exceptions: [], calculations: [], punches: [] },
    shifts: [],
    payroll: { period: "", stage: 0, stages: [], gross: 0, deductions: 0, net: 0, exceptions: 0, employees: 0, components: [] },
    candidates: [],
    reviews: [],
    lifecycle: [],
    documents: [],
    assets: [],
    claims: [],
    loans: [],
    tickets: [],
    workflows: [],
    approvals: [],
    compliance: [],
    audit: [],
    employeeProfiles: {},
    attendancePolicy: { earlyGraceMinutes: 0, roundingMinutes: 0, halfDayThresholdMinutes: 0, crossMidnight: "Backend settings API required" },
    salaryStructures: [],
    salaryAssignments: [],
    bankBatches: [],
    leaveLedger: [],
    leaveRuns: [],
    encashments: [],
    commissions: [],
    interviews: [],
    offers: [],
    confirmations: [],
    settlements: [],
    performanceCycles: [],
    exitInterviews: [],
    taxDocuments: [],
    backupDR: { schedule: "", retention: "", offsite: "", rpo: "", rto: "", owner: "", runbook: "", lastRestoreTest: "", restoreStatus: "" },
    settings: {
      policyPrecedence: "Backend settings API required",
      formulaMode: "Backend settings API required",
      notificationChannels: [],
      retention: "Backend settings API required",
      taxConfiguration: "Backend settings API required",
      payrollYearLock: "Backend settings API required",
      commissionRules: "Backend settings API required",
      leaveProcessing: "Backend settings API required",
    },
  });

  function mergeMissing(current, defaults) {
    if (Array.isArray(defaults)) return Array.isArray(current) ? current : clone(defaults);
    if (!defaults || typeof defaults !== "object") return current === undefined ? defaults : current;
    const out = current && typeof current === "object" && !Array.isArray(current) ? current : {};
    Object.keys(defaults).forEach(key => { out[key] = mergeMissing(out[key], defaults[key]); });
    return out;
  }

  function migrateState(parsed) {
    const migrated = mergeMissing(parsed || {}, serverRequiredState());
    migrated.meta.schema = 2;
    migrated.meta.migratedAt = migrated.meta.migratedAt || nowIso();
    return migrated;
  }

  function loadState() {
    return serverRequiredState();
  }

  function saveState(state) {
    state.meta.updatedAt = nowIso();
    return state;
  }

  function addAudit(state, actor, action, entity, type = "Update") {
    state.audit.unshift({ id: uid("AUD"), actor, action, entity, type, time: "Just now" });
    state.audit = state.audit.slice(0, 40);
  }

  window.HRMS = {
    version: "2.0.0-laravel", source: "laravel-server-required", load: loadState, save: saveState,
    permissions: { can(roleId, action) { const p = PERMISSIONS[roleId] || PERMISSIONS.employee; return action === "configure" ? p.configure : action === "approve" ? p.approve : action === "salary" ? p.salary : true; } },
    policies: { resolve(type, employee, date) { return { type, employee: employee && employee.id, date, precedence: loadState().settings.policyPrecedence, mode: "server-required" }; } },
    validation: {
      bank(employee) { return Boolean(employee && employee.bank && employee.bank !== "Not assigned"); },
      offer(template) { return !/\{\{[^}]+\}\}/.test(template || ""); },
      cycleOverlap(cycles, from, to) { return cycles.some(c => c.status !== "Archived" && from <= c.to && to >= c.from); },
    },
    export: { csv(rows) { return rows.map(row => Object.values(row).map(v => `"${String(v).replace(/"/g,'""')}"`).join(",")).join("\n"); } },
  };

  function useHRState(role, toast) {
    const [state, setState] = React.useState(loadState);
    const actor = (role && role.person) || "Authenticated User";
    const update = React.useCallback((mutator, message, tone = "green") => {
      setState(prev => {
        const next = clone(prev); mutator(next, actor); saveState(next); return next;
      });
      if (message && toast) toast(message, tone);
    }, [actor, toast]);
    return [state, update, setState];
  }

  const tone = status => {
    const s = String(status || "").toLowerCase();
    if (/approved|active|valid|published|paid|resolved|finalized|good|met/.test(s)) return "b-green";
    if (/reject|absent|critical|hold|overdue/.test(s)) return "b-red";
    if (/pending|draft|watch|due|progress|calibration|review|verification/.test(s)) return "b-orange";
    return "b-blue";
  };

  function StatePill({ children }) { return <Badge tone={tone(children)} dot>{children}</Badge>; }
  function KpiGrid({ children }) { return <div className="hrx-kpis">{children}</div>; }
  function Section({ title, sub, action, children, className = "" }) {
    return <div className={`card hrx-section ${className}`}><div className="hrx-section-head"><div><div className="card-title">{title}</div>{sub && <div className="card-sub">{sub}</div>}</div>{action}</div>{children}</div>;
  }
  function Table({ columns, rows, onRow, emptyTitle = "No records loaded", emptyText = "No authorized records are available for the current filters or backend data source." }) {
    const safeRows = Array.isArray(rows) ? rows : [];
    if (!safeRows.length) return <div className="tbl-wrap"><div className="hrx-empty-panel"><div className="empty-ic"><Icon name="table" size={24}/></div><h3>{emptyTitle}</h3><p>{emptyText}</p></div></div>;
    return <div className="tbl-wrap"><table className="tbl"><thead><tr>{columns.map((c, i) => <th key={i} style={c.right ? { textAlign: "right" } : null}>{c.label}</th>)}</tr></thead><tbody>{safeRows.map((r, ri) => <tr key={r.id || ri} onClick={() => onRow && onRow(r)} style={onRow ? { cursor: "pointer" } : null}>{columns.map((c, ci) => <td key={ci} className={c.right ? "num" : ""}>{c.render ? c.render(r) : r[c.key]}</td>)}</tr>)}</tbody></table></div>;
  }
  function Person({ employee, sub }) { return <div className="cell-user"><Avatar name={employee.name || employee} size={31}/><div><div className="cell-strong">{employee.name || employee}</div>{sub && <div className="cell-sub">{sub}</div>}</div></div>; }
  function ViewTitle({ title, sub, actions }) { return <div className="hrx-view-title"><div><h2>{title}</h2><p>{sub}</p></div><div className="row gap-2">{actions}</div></div>; }
  function unavailableAction(update, label, entity = "HRMS") {
    if (update) update((s, actor) => addAudit(s, actor, `${label} unavailable`, entity, "Unavailable"), `${label} requires the governed Laravel workflow and is not enabled from this prototype screen.`, "orange");
  }
  function downloadJsonFile(filename, payload) {
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.style.display = "none";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
  }
  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  }
  async function apiJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: "same-origin",
      ...options,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        ...(options.headers || {}),
      },
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
      const validation = body.errors && Object.values(body.errors).flat().filter(Boolean).join(" ");
      throw new Error(validation || body.message || "Request failed.");
    }
    return body;
  }
  function collectionUrl(baseUrl, params = {}) {
    if (!baseUrl) return null;
    const url = new URL(baseUrl, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") url.searchParams.set(key, String(value));
    });
    return url.toString();
  }

  function DashboardView({ update }) {
    return <div>
      <ViewTitle title="HR Command Center" sub="HR dashboard fallback is read-only. Backend HR dashboard API required for live KPIs, approvals, compliance and lifecycle data." actions={<Button icon="download" sm onClick={() => unavailableAction(update, "HR dashboard snapshot export", "HR Dashboard")}>Export unavailable</Button>}/>
      <div className="hrx-warning"><Icon name="alert" size={17}/><div><b>Backend HR dashboard API required</b><span>This screen will not display localStorage headcount, attendance, payroll, recruitment, compliance or approval totals because dashboard counts must come from governed Laravel records.</span></div></div>
      <KpiGrid>
        <Stat label="Active Employees" value="—" icon="users" tone="accent" sub="Load from Laravel employee records"/>
        <Stat label="Attendance Today" value="—" icon="check" tone="green" sub="Load from attendance records"/>
        <Stat label="Payroll Cost" value="—" icon="wallet" tone="violet" sub="Load from approved payroll runs"/>
        <Stat label="Pending Approvals" value="—" icon="check" tone="orange" sub="Load from workflow tables"/>
        <Stat label="Open Positions" value="—" icon="users" tone="blue" sub="Load from recruitment records"/>
        <Stat label="Compliance Alerts" value="—" icon="shield" tone="red" sub="Load from system settings"/>
      </KpiGrid>
      <div className="hrx-grid-2">
        <Section title="Approval Inbox" sub="Read-only until Laravel workflow payload is available" action={<Badge tone="b-slate">API REQUIRED</Badge>}>
          <EmptyPanel icon="check" title="No approval data loaded" text="Approval actions are not simulated in fallback mode. Use the Laravel workflow-backed dashboard payload."/>
        </Section>
        <Section title="Company Headcount" sub="Requires employee master aggregation">
          <EmptyPanel icon="users" title="No headcount data loaded" text="Company-wise headcount must be calculated from persisted employee records."/>
        </Section>
        <Section title="Compliance & Risk" sub="Requires governed System Settings records">
          <EmptyPanel icon="shield" title="No compliance data loaded" text="Compliance alerts must be calculated from active statutory and HR configuration records."/>
        </Section>
        <Section title="Lifecycle Due" sub="Requires lifecycle workflow records">
          <EmptyPanel icon="calendar" title="No lifecycle data loaded" text="Probation, confirmation, separation and F&F queues must come from Laravel workflow tables."/>
        </Section>
      </div>
    </div>;
  }

  function DashboardViewV2({ state, update, role, dashboardOptions }) {
    if (!dashboardOptions) return <DashboardView state={state} update={update} role={role}/>;
    const summary=dashboardOptions.summary||{};
    const companies=dashboardOptions.company_headcount||[];
    const departments=dashboardOptions.department_headcount||[];
    const approvals=dashboardOptions.approval_inbox||[];
    const compliance=dashboardOptions.compliance_risk||[];
    const lifecycle=dashboardOptions.lifecycle_due||[];
    const totalCompanyEmployees=Math.max(1,companies.reduce((n,c)=>n+Number(c.employees||0),0));
    const approvalSub=`${summary.pending_leave_requests||0} leave · ${summary.pending_attendance_regularizations||0} attendance · ${summary.pending_payroll_runs||0} payroll`;
    const exportSnapshot=()=>{downloadJsonFile("builder360-hr-dashboard-sqlite.json",dashboardOptions);update&&update((s,actor)=>addAudit(s,actor,"Exported MySQL HR dashboard snapshot","HR Dashboard","Export"),"HR dashboard snapshot exported");};
    return <div>
      <ViewTitle title="HR Command Center" sub="MySQL-backed HR dashboard with scoped headcount, attendance, payroll, approvals, lifecycle and compliance records." actions={<Button icon="download" sm onClick={exportSnapshot}>Export snapshot</Button>}/>
      <div className="hrx-demo-banner"><Icon name="shield" size={17}/><div><b>Laravel mode</b><span>KPIs are calculated from persisted Laravel records using role and company scoping.</span></div><Badge tone="b-green">SERVER BACKED</Badge></div>
      <KpiGrid>
        <Stat label="Active Employees" value={summary.active_headcount||0} icon="users" tone="accent" sub={`${summary.total_headcount||0} total employee records`}/>
        <Stat label="Attendance Today" value={summary.attendance_today_percent==null?"—":summary.attendance_today_percent} unit={summary.attendance_today_percent==null?"":"%"} icon="check" tone="green" sub={`${summary.attendance_present_today||0} present · ${summary.attendance_marked_today||0} marked`}/>
        <Stat label="Payroll Cost" value={money(summary.latest_payroll_net_payable||0)} icon="wallet" tone="violet" sub={summary.latest_payroll_label||"No payroll run"}/>
        <Stat label="Pending Approvals" value={summary.pending_approvals||0} icon="check" tone="orange" sub={approvalSub}/>
        <Stat label="Open Positions" value={summary.open_positions||0} icon="users" tone="blue" sub={`${summary.candidate_pipeline||0} active candidate(s)`}/>
        <Stat label="Compliance Alerts" value={summary.compliance_alerts||0} icon="shield" tone={summary.compliance_alerts?"red":"green"} sub="Required active system settings"/>
      </KpiGrid>
      <div className="hrx-grid-2">
        <Section title="Approval Inbox" sub="Read-only rollup from governed workflow tables" action={<Badge tone={approvals.length?"b-orange":"b-green"}>{approvals.length} ACTIONS</Badge>}>
          {approvals.length?<div className="hrx-list">{approvals.map(a=><div className="hrx-list-row" key={a.id}><div className="hrx-icon"><Icon name={a.type==="Payroll"?"wallet":a.type==="Leave"?"calendar":a.type==="Attendance"?"pin":a.type==="F&F"?"wallet":"check"} size={16}/></div><div className="hrx-grow"><b>{a.type} · {a.reference}</b><span>{a.subject}</span><small>{a.owner} · {a.age}</small></div><StatePill>{a.status}</StatePill>{a.can_approve?<Badge tone="b-green">CAN ACT</Badge>:<Badge tone="b-slate">VIEW</Badge>}</div>)}</div>:<EmptyPanel icon="check" title="No pending HR approvals" text="Submitted leave, attendance, confirmation, payroll and settlement items will appear here."/>}
        </Section>
        <Section title="Company Headcount" sub="Employee records grouped by legal entity">
          {companies.length?<div className="hrx-bars">{companies.map(c=><div key={c.id}><div className="row between"><span><b>{c.code}</b> · {c.name}</span><strong>{c.employees}</strong></div><Bar value={Number(c.employees||0)/totalCompanyEmployees*100} w="100%"/></div>)}</div>:<EmptyPanel icon="users" title="No company headcount found" text="Employee records in your permitted company scope will appear here."/>}
        </Section>
        <Section title="Compliance & Risk" sub="Required active settings compared with System Settings">
          {compliance.length?<div className="hrx-list">{compliance.map(c=><div className="hrx-list-row" key={`${c.key}-${c.company}-${c.version}`}><div className="hrx-state">{c.company||"Global"}</div><div className="hrx-grow"><b>{c.name}</b><span>{c.version} · {c.effective||"Not active"}</span></div><Badge tone={c.tone||"b-slate"}>{c.verification}</Badge></div>)}</div>:<EmptyPanel icon="shield" title="No compliance settings found" text="Required payroll, leave, attendance, workflow and backup settings will appear here after configuration."/>}
        </Section>
        <Section title="Lifecycle Due" sub="Probation, confirmation, separation and settlement queues">
          {lifecycle.length?<div className="hrx-list">{lifecycle.map(x=><div className="hrx-list-row" key={x.id}><Avatar name={x.employee} size={30}/><div className="hrx-grow"><b>{x.employee}</b><span>{x.event} · {x.due||"No due date"}</span><small>Owner: {x.owner}</small></div><StatePill>{x.status}</StatePill></div>)}</div>:<EmptyPanel icon="calendar" title="No lifecycle items due" text="Probation reviews, confirmations, exits and F&F settlements will appear here."/>}
        </Section>
      </div>
      <Section title="Department Headcount" sub="MySQL employee distribution by department">
        {departments.length?<div className="hrx-bars">{departments.map(d=><div key={d.department}><div className="row between"><span><b>{d.department}</b></span><strong>{d.employees}</strong></div><Bar value={Number(d.employees||0)/Math.max(1,summary.total_headcount||0)*100} w="100%"/></div>)}</div>:<EmptyPanel icon="chart" title="No department distribution found" text="Department-wise headcount appears after employee master records are created."/>}
      </Section>
    </div>;
  }

  function EmployeeModal({ options, onClose, onCreated, toast }) {
    const companies=options?.companies||[], branches=options?.branches||[], projects=options?.projects||[], managers=options?.managers||[];
    const departmentOptions=options?.departments||[], gradeOptions=options?.grades||[], employmentTypeOptions=options?.employment_types||[], statusOptions=options?.statuses||[];
    const defaultCompany=companies[0]||null;
    const defaultBranch=branches.find(b=>b.company_id===defaultCompany?.id)||null;
    const defaultProject=projects.find(p=>p.company_id===defaultCompany?.id)||null;
    const [form,setForm]=React.useState({employee_code:options?.next_employee_code_hint||("EMP-"+String(Date.now()).slice(-8)),company_id:defaultCompany?.id||"",branch_id:defaultBranch?.id||"",project_id:defaultProject?.id||"",manager_employee_id:"",name:"",designation:"",department:departmentOptions[0]||"",grade:gradeOptions[0]||"",employment_type:employmentTypeOptions[0]?.value||"",status:statusOptions[0]?.value||"",joined_on:new Date().toISOString().slice(0,10),statutory_state:defaultCompany?.state||"",monthly_ctc:""});
    const [busy,setBusy]=React.useState(false);
    const [error,setError]=React.useState("");
    const filteredBranches=branches.filter(b=>String(b.company_id)===String(form.company_id));
    const filteredProjects=projects.filter(p=>String(p.company_id)===String(form.company_id));
    const filteredManagers=managers.filter(m=>String(m.company_id)===String(form.company_id));
    const set=(k,v)=>setForm(f=>({...f,[k]:v}));
    const setCompany=value=>{const company=companies.find(c=>String(c.id)===String(value));const branch=branches.find(b=>String(b.company_id)===String(value));const project=projects.find(p=>String(p.company_id)===String(value));setForm(f=>({...f,company_id:value,branch_id:branch?.id||"",project_id:project?.id||"",manager_employee_id:"",statutory_state:company?.state||f.statutory_state}));};
    const submit=async e=>{e.preventDefault();setError("");if(!options?.store_url||!companies.length){setError("Employee creation is not available for this role or company scope.");return;}try{setBusy(true);const payload={employee_code:form.employee_code.trim().toUpperCase(),company_id:Number(form.company_id),branch_id:form.branch_id?Number(form.branch_id):null,project_id:form.project_id?Number(form.project_id):null,manager_employee_id:form.manager_employee_id?Number(form.manager_employee_id):null,name:form.name.trim(),designation:form.designation.trim(),department:form.department,grade:form.grade||null,employment_type:form.employment_type,status:form.status,joined_on:form.joined_on||null,statutory_state:form.statutory_state||null,monthly_ctc:form.monthly_ctc===""?null:Number(form.monthly_ctc)};const body=await apiJson(options.store_url,{method:"POST",body:JSON.stringify(payload)});onCreated(body.data);toast&&toast("Employee "+body.data.employee_code+" created in Laravel.","green");onClose();}catch(err){setError(err.message);}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Add Employee</h2><p>Creates a validated Laravel employee master record with company scope, audit history and role permissions.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Employee not created</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee code<input required pattern="[A-Z0-9-]+" value={form.employee_code} disabled={busy} onChange={e=>set("employee_code",e.target.value.toUpperCase())}/></label><label>Full name<input required value={form.name} disabled={busy} onChange={e=>set("name",e.target.value)} placeholder="Employee name"/></label><label>Company<select required value={form.company_id} disabled={busy} onChange={e=>setCompany(e.target.value)}>{companies.map(c=><option key={c.id} value={c.id}>{c.label}</option>)}</select></label><label>Branch<select value={form.branch_id} disabled={busy} onChange={e=>set("branch_id",e.target.value)}><option value="">No branch</option>{filteredBranches.map(b=><option key={b.id} value={b.id}>{b.label}</option>)}</select></label><label>Project / Site<select value={form.project_id} disabled={busy} onChange={e=>set("project_id",e.target.value)}><option value="">Corporate / no project</option>{filteredProjects.map(p=><option key={p.id} value={p.id}>{p.label}</option>)}</select></label><label>Reporting manager<SearchablePeoplePicker items={filteredManagers} selected={form.manager_employee_id} mode="single" disabled={busy} placeholder="Search manager name, code, department..." emptyText="No matching managers" onChange={value=>set("manager_employee_id",value||"")} getId={m=>m.id} getLabel={m=>m.name||m.label||"Manager"} getSubLabel={m=>m.label&&m.name?m.label:[m.employee_code,m.department,m.designation,m.email].filter(Boolean).join(" · ")}/></label><label>Designation<input required value={form.designation} disabled={busy} onChange={e=>set("designation",e.target.value)} placeholder="Designation"/></label><label>Department<select required value={form.department} disabled={busy||!departmentOptions.length} onChange={e=>set("department",e.target.value)}><option value="">Select configured department</option>{departmentOptions.map(x=><option key={x} value={x}>{x}</option>)}</select></label><label>Grade<select value={form.grade} disabled={busy||!gradeOptions.length} onChange={e=>set("grade",e.target.value)}><option value="">No grade</option>{gradeOptions.map(x=><option key={x} value={x}>{x}</option>)}</select></label><label>Employment type<select required value={form.employment_type} disabled={busy||!employmentTypeOptions.length} onChange={e=>set("employment_type",e.target.value)}><option value="">Select employment type</option>{employmentTypeOptions.map(x=><option key={x.value} value={x.value}>{x.label}</option>)}</select></label><label>Status<select required value={form.status} disabled={busy||!statusOptions.length} onChange={e=>set("status",e.target.value)}><option value="">Select status</option>{statusOptions.map(x=><option key={x.value} value={x.value}>{x.label}</option>)}</select></label><label>Joined on<input type="date" value={form.joined_on} disabled={busy} onChange={e=>set("joined_on",e.target.value)}/></label><label>Statutory state<input maxLength={8} value={form.statutory_state} disabled={busy} onChange={e=>set("statutory_state",e.target.value.toUpperCase())}/></label><label>Monthly CTC<input type="number" min="0" step="0.01" value={form.monthly_ctc} disabled={busy} onChange={e=>set("monthly_ctc",e.target.value)} placeholder="Optional"/></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="plus" size={15}/>{busy?"Creating…":"Create in Laravel"}</button></div></form></div>;
  }

  function EmployeeDrawer({ employee, profile, onClose, canSalary, options, toast }) {
    const [tab, setTab] = React.useState("Overview");
    const [detail,setDetail]=React.useState(null);
    const [profileData,setProfileData]=React.useState(null);
    const [loading,setLoading]=React.useState(false);
    const [savingProfile,setSavingProfile]=React.useState(false);
    const [editingProfile,setEditingProfile]=React.useState(false);
    const [movementRows,setMovementRows]=React.useState([]);
    const [savingMovement,setSavingMovement]=React.useState(false);
    const [editingMovement,setEditingMovement]=React.useState(false);
    const [documentRows,setDocumentRows]=React.useState([]);
    const [savingDocument,setSavingDocument]=React.useState(false);
    const [editingDocument,setEditingDocument]=React.useState(false);
    const [attendanceRows,setAttendanceRows]=React.useState([]);
    const [leaveBalances,setLeaveBalances]=React.useState([]);
    const [leaveRequests,setLeaveRequests]=React.useState([]);
    const [assetRows,setAssetRows]=React.useState([]);
    const [payrollSummary,setPayrollSummary]=React.useState(null);
    const [auditRows,setAuditRows]=React.useState([]);
    const [error,setError]=React.useState("");
    const tabs = ["Overview", "Personal", "Emergency", "Family", "Education", "Experience", "Job", "Statutory", "Documents", "Attendance", "Leave", "Payroll", "Lifecycle", "Assets", "Audit"];
    const detailUrl=employeeDetailUrl(options?.show_url_template,employee);
    const profileUrl=employeeDetailUrl(options?.profile_sections_url_template,employee);
    const movementUrl=employeeDetailUrl(options?.movements_url_template,employee);
    const documentUrl=employeeDetailUrl(options?.documents_url_template,employee);
    const attendanceUrl=employeeScopedCollectionUrl(options?.attendance_records_url,employee,{per_page:10});
    const leaveBalanceUrl=employeeScopedCollectionUrl(options?.leave_balances_url,employee,{per_page:20});
    const leaveRequestUrl=employeeScopedCollectionUrl(options?.leave_requests_url,employee,{per_page:10});
    const assetUrl=employeeScopedCollectionUrl(options?.assets_url,employee,{per_page:20});
    const payrollUrl=employeeDetailUrl(options?.payroll_summary_url_template,employee);
    const auditUrl=employeeDetailUrl(options?.audit_events_url_template,employee);
    React.useEffect(()=>{let alive=true;setDetail(null);setProfileData(null);setMovementRows([]);setDocumentRows([]);setAttendanceRows([]);setLeaveBalances([]);setLeaveRequests([]);setAssetRows([]);setPayrollSummary(null);setAuditRows([]);setError("");const requests=[detailUrl?apiJson(detailUrl):Promise.resolve(null),profileUrl?apiJson(profileUrl):Promise.resolve(null),movementUrl?apiJson(movementUrl):Promise.resolve(null),documentUrl?apiJson(documentUrl):Promise.resolve(null),attendanceUrl&&options?.can_view_attendance_records?apiJson(attendanceUrl):Promise.resolve(null),leaveBalanceUrl&&options?.can_view_leave_records?apiJson(leaveBalanceUrl):Promise.resolve(null),leaveRequestUrl&&options?.can_view_leave_records?apiJson(leaveRequestUrl):Promise.resolve(null),assetUrl&&options?.can_view_asset_records?apiJson(assetUrl):Promise.resolve(null),payrollUrl&&options?.can_view_payroll_records?apiJson(payrollUrl):Promise.resolve(null),auditUrl&&options?.can_view_employee_audit_events?apiJson(auditUrl):Promise.resolve(null)];if(!requests.some(Boolean))return;setLoading(true);Promise.all(requests).then(([detailBody,profileBody,movementBody,documentBody,attendanceBody,leaveBalanceBody,leaveRequestBody,assetBody,payrollBody,auditBody])=>{if(!alive)return;if(detailBody)setDetail(detailBody.data);if(profileBody?.data?.sections)setProfileData(profileBody.data.sections);if(movementBody?.data)setMovementRows(movementBody.data);if(documentBody?.data)setDocumentRows(documentBody.data);if(attendanceBody?.data)setAttendanceRows(attendanceBody.data);if(leaveBalanceBody?.data)setLeaveBalances(leaveBalanceBody.data);if(leaveRequestBody?.data)setLeaveRequests(leaveRequestBody.data);if(assetBody?.data)setAssetRows(assetBody.data);if(payrollBody?.data)setPayrollSummary(payrollBody.data);if(auditBody?.data)setAuditRows(auditBody.data);}).catch(err=>{if(alive){setError(err.message||"Employee detail could not be loaded from Laravel.");toast&&toast("Employee detail API issue: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[detailUrl,profileUrl,movementUrl,documentUrl,attendanceUrl,leaveBalanceUrl,leaveRequestUrl,assetUrl,payrollUrl,auditUrl,options?.can_view_attendance_records,options?.can_view_leave_records,options?.can_view_asset_records,options?.can_view_payroll_records,options?.can_view_employee_audit_events,toast]);
    const current = detail ? employeeRowFromApi(detail) : employee;
    const currentProfile = profileData || profile || {personal:{},emergency:[],family:[],education:[],experience:[]};
    const saveProfile=async(sections)=>{if(!profileUrl||!options?.can_update_profile_sections)return;setSavingProfile(true);setError("");try{const body=await apiJson(profileUrl,{method:"PATCH",body:JSON.stringify({sections})});setProfileData(body.data.sections);setEditingProfile(false);toast&&toast("Employee profile sections saved to Laravel.","green");}catch(err){setError(err.message||"Employee profile sections could not be saved.");toast&&toast("Profile sections not saved: "+(err.message||"save failed"),"orange");}finally{setSavingProfile(false);}};
    const saveMovement=async(payload)=>{if(!movementUrl||!options?.can_create_movement)return;setSavingMovement(true);setError("");try{const body=await apiJson(movementUrl,{method:"POST",body:JSON.stringify(payload)});setMovementRows(rows=>[body.data,...rows.filter(x=>x.id!==body.data.id)]);setEditingMovement(false);if(detailUrl){try{const fresh=await apiJson(detailUrl);setDetail(fresh.data);}catch(_e){}}toast&&toast((body.message||"Employee movement saved")+" Laravel.","green");}catch(err){setError(err.message||"Employee movement could not be saved.");toast&&toast("Movement not saved: "+(err.message||"save failed"),"orange");}finally{setSavingMovement(false);}};
    const approveMovement=async(movement)=>{const url=employeeMovementApproveUrl(options?.movement_approve_url_template,employee,movement);if(!url)return;setSavingMovement(true);setError("");try{const body=await apiJson(url,{method:"PATCH",body:JSON.stringify({remarks:"Approved from Employee Master drawer"})});setMovementRows(rows=>rows.map(x=>x.id===body.data.id?body.data:x));if(detailUrl){try{const fresh=await apiJson(detailUrl);setDetail(fresh.data);}catch(_e){}}toast&&toast("Employee movement approved and applied.","green");}catch(err){setError(err.message||"Employee movement approval failed.");toast&&toast("Movement approval failed: "+(err.message||"approval failed"),"orange");}finally{setSavingMovement(false);}};
    const saveDocument=async(payload)=>{if(!documentUrl||!options?.can_create_employee_document)return;setSavingDocument(true);setError("");try{const body=await apiJson(documentUrl,{method:"POST",body:JSON.stringify(payload)});setDocumentRows(rows=>[body.data,...rows.filter(x=>x.id!==body.data.id)]);setEditingDocument(false);if(detailUrl){try{const fresh=await apiJson(detailUrl);setDetail(fresh.data);}catch(_e){}}toast&&toast("Employee document registered in Laravel.","green");}catch(err){setError(err.message||"Employee document could not be saved.");toast&&toast("Document not saved: "+(err.message||"save failed"),"orange");}finally{setSavingDocument(false);}};
    const approveDocument=async(doc)=>{const url=employeeDocumentApproveUrl(options?.document_approve_url_template,employee,doc);if(!url)return;setSavingDocument(true);setError("");try{const body=await apiJson(url,{method:"PATCH",body:JSON.stringify({approval_note:"Approved from Employee Master drawer"})});setDocumentRows(rows=>rows.map(x=>x.id===body.data.id?body.data:x));toast&&toast("Employee document approved.","green");}catch(err){setError(err.message||"Employee document approval failed.");toast&&toast("Document approval failed: "+(err.message||"approval failed"),"orange");}finally{setSavingDocument(false);}};
    return <div className="hrx-drawer-scrim" onClick={onClose}><aside className="hrx-drawer" onClick={e => e.stopPropagation()}><div className="hrx-drawer-head"><Person employee={current} sub={`${current.code} · ${current.designation}`}/><button className="icon-btn" onClick={onClose}><Icon name="x"/></button></div><div className="hrx-profile-hero"><Avatar name={current.name} size={62}/><div><h2>{current.name}</h2><p>{current.department} · {current.project}</p><StatePill>{current.status}</StatePill></div></div><div className="hrx-toolbar" style={{marginBottom:10}}><Badge tone={detail?"b-green":detailUrl?"b-blue":"b-orange"}>{loading?"Loading employee profile":detail?"MySQL employee profile":"Profile API required"}</Badge><Badge tone={profileData?"b-green":profileUrl?"b-blue":"b-orange"}>{profileData?"MySQL profile sections":"Profile sections API required"}</Badge><Badge tone={movementUrl?"b-green":"b-orange"}>{movementUrl?`${movementRows.length} movement records`:"Movement API required"}</Badge><Badge tone={documentUrl?"b-green":"b-orange"}>{documentUrl?`${documentRows.length} employee documents`:"Document API required"}</Badge><Badge tone={options?.can_view_attendance_records?"b-green":"b-orange"}>{options?.can_view_attendance_records?`${attendanceRows.length} attendance rows`:"Attendance restricted"}</Badge><Badge tone={options?.can_view_leave_records?"b-green":"b-orange"}>{options?.can_view_leave_records?`${leaveBalances.length} leave balances`:"Leave restricted"}</Badge><Badge tone={options?.can_view_asset_records?"b-green":"b-orange"}>{options?.can_view_asset_records?`${assetRows.length} assets`:"Assets restricted"}</Badge>{current.directReports!==undefined&&<Badge tone="b-slate">{current.directReports} reports</Badge>}{options?.can_update_profile_sections&&profileUrl&&<Button icon="check" sm onClick={()=>setEditingProfile(true)}>Edit Profile Sections</Button>}{options?.can_create_movement&&movementUrl&&<Button icon="plus" sm onClick={()=>setEditingMovement(true)}>Record Movement</Button>}{options?.can_create_employee_document&&documentUrl&&<Button icon="upload" sm onClick={()=>setEditingDocument(true)}>Register Document</Button>}{error&&<Badge tone="b-orange">Detail API issue</Badge>}</div><div className="hrx-mini-tabs">{tabs.map(t => <button className={tab === t ? "on" : ""} onClick={() => setTab(t)} key={t}>{t}</button>)}</div><div className="hrx-drawer-body">
      {error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Laravel detail unavailable</b><span>{error}</span></div></div>}
      {tab === "Overview" && <div className="hrx-detail-grid"><div><span>Company</span><b>{current.companyId}</b></div><div><span>Branch</span><b>{current.branch}</b></div><div><span>Manager</span><b>{current.manager}</b></div><div><span>Join date</span><b>{current.joinDate}</b></div><div><span>Email</span><b>{current.email}</b></div><div><span>Phone</span><b>{current.phone}</b></div></div>}
      {tab === "Personal" && <div className="hrx-detail-grid"><div><span>Date of birth</span><b>{currentProfile?.personal?.dob || "Not provided"}</b></div><div><span>Gender</span><b>{currentProfile?.personal?.gender || "Not provided"}</b></div><div><span>Marital status</span><b>{currentProfile?.personal?.marital || "Not provided"}</b></div><div><span>Blood group</span><b>{currentProfile?.personal?.blood || "Not provided"}</b></div><div><span>Mobile</span><b>{currentProfile?.personal?.mobile || current.phone}</b></div><div><span>Email</span><b>{currentProfile?.personal?.email || current.email}</b></div></div>}
      {tab === "Emergency" && <ProfileRows rows={currentProfile?.emergency} empty="No emergency contacts" render={x=><><b>{x.name}</b><span>{x.relation} · {x.phone}</span></>}/>} 
      {tab === "Family" && <ProfileRows rows={currentProfile?.family} empty="No family records" render={x=><><b>{x.name}</b><span>{x.relation} · {x.dependent?"Dependent":"Non-dependent"}</span></>}/>} 
      {tab === "Education" && <ProfileRows rows={currentProfile?.education} empty="No education records" render={x=><><b>{x.qualification}</b><span>{x.institute} · {x.year}</span></>}/>} 
      {tab === "Experience" && <ProfileRows rows={currentProfile?.experience} empty="No previous experience" render={x=><><b>{x.company}</b><span>{x.role} · {x.from}–{x.to}</span></>}/>} 
      {tab === "Job" && <div className="hrx-detail-grid"><div><span>Department</span><b>{current.department}</b></div><div><span>Designation</span><b>{current.designation}</b></div><div><span>Grade</span><b>{current.grade}</b></div><div><span>Employment</span><b>{current.employmentType}</b></div><div><span>Project / Site</span><b>{current.project}</b></div><div><span>State</span><b>{current.state}</b></div></div>}
      {tab === "Statutory" && <div className="hrx-sensitive"><Icon name="shield"/><div><h3>Restricted statutory fields</h3><p>PAN {current.pan} · Aadhaar {current.aadhaar} · UAN {current.uan}</p><small>Values come from Laravel EmployeeResource with field-level masking.</small></div></div>}
      {tab === "Documents" && <EmployeeDocumentsTable rows={documentRows} canManage={options?.can_create_employee_document} busy={savingDocument} onApprove={approveDocument}/>}
      {tab === "Attendance" && <EmployeeAttendanceTable rows={attendanceRows} canView={options?.can_view_attendance_records}/>}
      {tab === "Leave" && <EmployeeLeavePanel balances={leaveBalances} requests={leaveRequests} canView={options?.can_view_leave_records}/>}
      {tab === "Payroll" && <EmployeePayrollPanel summary={payrollSummary} employee={current} canView={options?.can_view_payroll_records || canSalary}/>}
      {tab === "Lifecycle" && <div>{movementRows.length?<Table rows={movementRows} columns={[{label:"Movement",render:r=><div><b>{r.movement_number}</b><div className="cell-sub">{movementLabel(r.movement_type)}</div></div>},{label:"Effective",key:"effective_on"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Changes",render:r=><MovementChanges movement={r}/>},{label:"Action",render:r=>r.status==="pending"&&options?.can_create_movement?<button className="hrx-link" disabled={savingMovement} onClick={()=>approveMovement(r)}>Approve</button>:<span className="faint">—</span>}]}/>:<EmptyPanel icon="trend" title="No movement history recorded" text="Transfers, promotions, reporting, salary, grade and status changes will appear here after HR records them."/>}</div>}
      {tab === "Assets" && <EmployeeAssetsTable rows={assetRows} canView={options?.can_view_asset_records}/>}
      {tab === "Audit" && <EmployeeAuditTable rows={auditRows} canView={options?.can_view_employee_audit_events}/>}
    </div>{editingProfile&&<EmployeeProfileSectionsModal profile={currentProfile} busy={savingProfile} onClose={()=>setEditingProfile(false)} onSave={saveProfile}/>} {editingMovement&&<EmployeeMovementModal employee={current} options={options} busy={savingMovement} canSalary={canSalary} onClose={()=>setEditingMovement(false)} onSave={saveMovement}/>} {editingDocument&&<EmployeeDocumentModal employee={current} options={options} busy={savingDocument} onClose={()=>setEditingDocument(false)} onSave={saveDocument}/>}</aside></div>;
  }

  function movementLabel(type) {
    return ({transfer:"Transfer",promotion:"Promotion",department_change:"Department change",reporting_change:"Reporting change",salary_change:"Salary change",status_change:"Status change",grade_change:"Grade change"})[type] || type;
  }

  function EmployeeDocumentsTable({ rows, canManage, busy, onApprove }) {
    if (!rows?.length) return <EmptyPanel icon="folder" title="No employee documents registered" text="Register KYC, statutory, employment and other employee documents with expiry tracking." />;
    return <Table rows={rows} columns={[{label:"Document",render:r=><div><b>{r.title}</b><div className="cell-sub">{r.document_number} · v{r.version}</div></div>},{label:"Category",render:r=>r.category?.name||r.category?.code||"—"},{label:"Expiry",render:r=><div><b>{r.expires_on||"No expiry"}</b><div className="cell-sub">{r.is_expired?"Expired":r.is_expiring_within_30_days?"Expiring soon":"Current"}</div></div>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"File",render:r=>r.download_url?<a className="hrx-link" href={r.download_url} target="_blank" rel="noreferrer">{r.original_filename}</a>:<span className="faint">{r.original_filename||"No file"}</span>},{label:"Action",render:r=>r.status==="submitted"&&canManage&&r.uploaded_by?.email!==window.Builder360Server?.user?.email?<button className="hrx-link" disabled={busy} onClick={()=>onApprove(r)}>Approve</button>:<span className="faint">—</span>}]}/>;
  }

  function EmployeePayrollPanel({ summary, employee, canView }) {
    if (!canView) return <EmptyPanel icon="shield" title="Salary access restricted" text="Grant compensation/payroll permission to view payroll runs, payslip values and tax documents."/>;
    if (!summary) return <div className="hrx-pay-card"><span>Current net salary</span><strong>{money(employee.netSalary)}</strong><small>Bank {employee.bank} · payroll summary loading or not yet linked</small></div>;
    const assignment=summary.current_assignment, structure=assignment?.structure, totals=summary.totals||{};
    return <div>
      <div className="hrx-grid-2"><div className="hrx-pay-card"><span>Current salary structure</span><strong>{structure?money(structure.monthly_ctc):money(employee.netSalary)}</strong><small>{structure?`${structure.code} · ${structure.name} · v${structure.version}`:"No active assignment found"} · {summary.access_mode}</small></div><div className="hrx-pay-card"><span>Recent net payable</span><strong>{money(totals.net_payable||0)}</strong><small>{totals.payroll_items_count||0} payroll rows · {totals.tax_documents_count||0} tax documents</small></div></div>
      {summary.payroll_items?.length?<Section title="Payroll Run Items" sub="Employee-scoped payroll rows from Laravel"><Table rows={summary.payroll_items} columns={[{label:"Run",render:r=><div><b>{r.run_number||"—"}</b><div className="cell-sub">{r.period_month}/{r.period_year} · {r.run_status}</div></div>},{label:"Payable days",right:true,render:r=><span className="mono">{r.payable_days}</span>},{label:"Gross",right:true,render:r=><span className="mono">{money(r.gross_earnings)}</span>},{label:"Deductions",right:true,render:r=><span className="mono">−{money(r.total_deductions)}</span>},{label:"Net",right:true,render:r=><b className="mono">{money(r.net_payable)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section>:<EmptyPanel icon="wallet" title="No payroll run items found" text="Generated/approved payroll rows for this employee will appear here."/>}
      {summary.tax_documents?.length?<Section title="Tax Documents" sub="Form 16 and payroll tax artifacts scoped to this employee"><Table rows={summary.tax_documents} columns={[{label:"Document",render:r=><div><b>{r.document_number}</b><div className="cell-sub">{r.document_type} · v{r.version}</div></div>},{label:"FY",key:"financial_year"},{label:"Gross salary",right:true,render:r=><span className="mono">{money(r.gross_salary)}</span>},{label:"TDS",right:true,render:r=><span className="mono">{money(r.tds_deducted)}</span>},{label:"Net paid",right:true,render:r=><b className="mono">{money(r.net_salary_paid)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section>:null}
    </div>;
  }

  function EmployeeAuditTable({ rows, canView }) {
    if (!canView) return <EmptyPanel icon="shield" title="Audit access restricted" text="Employee audit history is visible only to authorized HR managers, auditors and system administrators."/>;
    if (!rows?.length) return <EmptyPanel icon="eye" title="No employee audit events found" text="Employee, profile, movement, document, attendance, leave, payroll, asset and lifecycle audit events will appear here."/>;
    return <Table rows={rows} columns={[{label:"When",render:r=>formatDateTime(r.created_at)},{label:"Actor",render:r=><Person employee={r.user?.name||"System"} sub={r.user?.role||r.user?.email||"audit"}/>},{label:"Event",render:r=><div><b>{r.action}</b><div className="cell-sub">{r.event_type}</div></div>},{label:"Record",render:r=><span className="mono">{auditRecordLabel(r)}</span>},{label:"Request",render:r=>r.request_id?<span className="mono">{r.request_id}</span>:<span className="faint">—</span>}]}/>;
  }

  function auditRecordLabel(row) {
    const type=String(row?.auditable_type||"").split("\\").pop()||"Record";
    return `${type} #${row?.auditable_id||"—"}`;
  }

  function formatDateTime(value) {
    if (!value) return "—";
    const date=new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString([], {year:"numeric",month:"short",day:"2-digit",hour:"2-digit",minute:"2-digit"});
  }

  function EmployeeAttendanceTable({ rows, canView }) {
    if (!canView) return <EmptyPanel icon="shield" title="Attendance access restricted" text="Attendance rows are loaded only for roles with attendance or self-service permission." />;
    if (!rows?.length) return <EmptyPanel icon="check" title="No attendance rows found" text="Daily MySQL attendance records, shift timings and late/early calculations will appear here." />;
    return <Table rows={rows} columns={[{label:"Date",key:"work_date"},{label:"Shift",render:r=>r.shift?.name||r.shift?.code||"—"},{label:"Source",key:"source"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Late",right:true,render:r=><span className="mono">{r.late_minutes||0}m</span>},{label:"Early",right:true,render:r=><span className="mono">{r.early_leave_minutes||0}m</span>},{label:"Worked",right:true,render:r=><span className="mono">{r.worked_minutes||0}m</span>}]}/>;
  }

  function EmployeeLeavePanel({ balances, requests, canView }) {
    if (!canView) return <EmptyPanel icon="shield" title="Leave access restricted" text="Leave ledger and requests are loaded only for roles with leave or self-service permission." />;
    return <div>{balances?.length?<Section title="Leave Balances" sub="Ledger-backed balances from Laravel"><Table rows={balances} columns={[{label:"Type",render:r=><div><b>{r.leave_type?.name||r.leave_type?.code||"Leave"}</b><div className="cell-sub">{r.period_year}</div></div>},{label:"Opening",right:true,render:r=><span className="mono">{r.opening_balance_days}</span>},{label:"Accrued",right:true,render:r=><span className="mono">{r.accrued_days}</span>},{label:"Used",right:true,render:r=><span className="mono">{r.used_days}</span>},{label:"Pending",right:true,render:r=><span className="mono">{r.pending_days}</span>},{label:"Available",right:true,render:r=><b className="mono">{r.available_days}</b>}]}/></Section>:<EmptyPanel icon="calendar" title="No leave balances found" text="Eligibility, accrual, carry-forward and lapse records will appear after leave balance setup."/>}{requests?.length?<Section title="Recent Leave Requests" sub="Requests, workflow status and decision history"><Table rows={requests} columns={[{label:"Request",render:r=><div><b>{r.request_number}</b><div className="cell-sub">{r.leave_type?.name||"Leave"}</div></div>},{label:"Dates",render:r=>`${r.starts_on||"—"} → ${r.ends_on||"—"}`},{label:"Days",right:true,render:r=><span className="mono">{r.requested_days}</span>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Decision",render:r=>r.decision_note||"—"}]}/></Section>:null}</div>;
  }

  function EmployeeAssetsTable({ rows, canView }) {
    if (!canView) return <EmptyPanel icon="shield" title="Asset access restricted" text="Asset issue and recovery records are loaded only for authorized HR, asset or self-service roles." />;
    if (!rows?.length) return <EmptyPanel icon="box" title="No employee assets found" text="Assigned laptops, mobile devices, access cards and recovery records will appear here." />;
    return <Table rows={rows} columns={[{label:"Asset",render:r=><div><b>{r.asset_code}</b><div className="cell-sub">{r.name}</div></div>},{label:"Category",key:"category"},{label:"Serial",key:"serial_number"},{label:"Assigned",key:"assigned_on"},{label:"Recovered",render:r=>r.recovered_on||"—"},{label:"Condition",key:"condition"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/>;
  }

  function EmployeeDocumentModal({ employee, options, onClose, onSave, busy }) {
    const categories=options?.employee_document_categories||[];
    const first=categories[0]||null;
    const today=new Date().toISOString().slice(0,10);
    const nextYear=new Date(Date.now()+365*24*60*60*1000).toISOString().slice(0,10);
    const safeCode=String(employee.code||"employee").toLowerCase().replace(/[^a-z0-9-]+/g,"-");
    const [form,setForm]=React.useState({document_category_id:first?.id||"",title:first?first.name:"Employee KYC",issue_date:today,expires_on:first?.expiry_required?nextYear:"",original_filename:`${safeCode}-kyc.pdf`,mime_type:"application/pdf",file_size_bytes:"1024",checksum_sha256:"a".repeat(64),storage_path:`documents/employees/${safeCode}-${Date.now()}.pdf`,notes:""});
    const set=(k,v)=>setForm(f=>({...f,[k]:v}));
    const selected=categories.find(c=>String(c.id)===String(form.document_category_id));
    const pickCategory=value=>{const cat=categories.find(c=>String(c.id)===String(value));setForm(f=>({...f,document_category_id:value,title:cat?.name||f.title,expires_on:cat?.expiry_required?(f.expires_on||nextYear):f.expires_on}));};
    const submit=e=>{e.preventDefault();onSave({document_category_id:Number(form.document_category_id),title:form.title.trim(),storage_disk:"local",storage_path:form.storage_path.trim(),original_filename:form.original_filename.trim(),mime_type:form.mime_type.trim(),file_size_bytes:Number(form.file_size_bytes),checksum_sha256:form.checksum_sha256.trim().toLowerCase(),issue_date:form.issue_date||null,expires_on:form.expires_on||null,metadata:{source:"hr_employee_drawer",notes:form.notes.trim()||null}});};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Register Employee Document</h2><p>Creates an employee-owned managed document record with category policy, expiry validation, audit trail and approval workflow.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{!categories.length&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>No employee document category</b><span>Configure an active employee/global document category before registering documents.</span></div></div>}<div className="hrx-form-grid"><label>Employee<input value={`${employee.code} · ${employee.name}`} disabled/></label><label>Category<select required value={form.document_category_id} disabled={busy||!categories.length} onChange={e=>pickCategory(e.target.value)}>{categories.map(c=><option key={c.id} value={c.id}>{c.label}</option>)}</select></label><label style={{gridColumn:"1 / -1"}}>Document title<input required value={form.title} disabled={busy} onChange={e=>set("title",e.target.value)} placeholder="Document title"/></label><label>Issue date<input type="date" value={form.issue_date} disabled={busy} onChange={e=>set("issue_date",e.target.value)}/></label><label>Expiry date<input type="date" required={!!selected?.expiry_required} value={form.expires_on} disabled={busy} onChange={e=>set("expires_on",e.target.value)}/></label><label>Original filename<input required value={form.original_filename} disabled={busy} onChange={e=>set("original_filename",e.target.value)} placeholder="employee-kyc.pdf"/></label><label>MIME type<input required value={form.mime_type} disabled={busy} onChange={e=>set("mime_type",e.target.value)} placeholder="application/pdf"/></label><label>File size bytes<input required type="number" min="1" value={form.file_size_bytes} disabled={busy} onChange={e=>set("file_size_bytes",e.target.value)}/></label><label>SHA-256 checksum<input required minLength={64} maxLength={64} value={form.checksum_sha256} disabled={busy} onChange={e=>set("checksum_sha256",e.target.value.toLowerCase())}/></label><label style={{gridColumn:"1 / -1"}}>Managed storage path<input required value={form.storage_path} disabled={busy} onChange={e=>set("storage_path",e.target.value)} placeholder="documents/employees/file.pdf"/></label><label style={{gridColumn:"1 / -1"}}>Notes<textarea value={form.notes} disabled={busy} onChange={e=>set("notes",e.target.value)} placeholder="Document verification notes, expiry reminder context or replacement reference."/></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy||!categories.length}><Icon name="upload" size={15}/>{busy?"Saving…":"Register Document"}</button></div></form></div>;
  }

  function MovementChanges({ movement }) {
    const values = movement?.new_values || {};
    const parts = Object.keys(values).map(k => `${k.replaceAll("_"," ")}: ${values[k]}`);
    return <span>{parts.length ? parts.join(" · ") : "—"}</span>;
  }

  function ProfileRows({ rows, empty, render }) { return rows && rows.length ? <div className="hrx-list">{rows.map((x,i)=><div className="hrx-list-row" key={i}><div className="hrx-icon"><Icon name="doc" size={15}/></div><div className="hrx-grow">{render(x)}</div></div>)}</div> : <EmptyPanel icon="doc" title={empty} text="Use Edit Profile to add validated records and supporting documents."/>; }

  function EmptyPanel({ icon, title, text }) { return <div className="hrx-empty-panel"><div className="empty-ic"><Icon name={icon} size={24}/></div><h3>{title}</h3><p>{text}</p></div>; }

  function employeeRowFromApi(row) {
    const id = "srv-" + row.id;
    const sensitive = row.sensitive_profile || row.sensitive_profile_masked || {};
    return { id, recordId: row.id, code: row.employee_code, name: row.name, designation: row.designation, department: row.department, project: row.project?.name || "Corporate", state: row.statutory_state || row.company?.state || "", grade: row.grade || "—", netSalary: Number(row.monthly_ctc || 0), attendance: 100, leaveBalance: 0, status: row.status, companyId: row.company?.code || String(row.company?.id || ""), companyRecordId: row.company?.id || row.company_id || "", branch: row.branch?.name || "—", manager: row.manager?.name || "To be assigned", employmentType: row.employment_type, email: row.user?.email || "Not linked", phone: sensitive.mobile_masked || sensitive.phone_masked || "Not provided", pan: sensitive.pan_masked || "Restricted", aadhaar: sensitive.aadhaar_masked || "Restricted", uan: sensitive.uan || sensitive.uan_masked || "Not assigned", bank: sensitive.bank_account_masked || sensitive.bank_masked || "Not assigned", joinDate: row.joined_on || "Not provided", documents: row.documents_count || 0, assets: row.assets_count || 0, directReports: row.direct_reports_count || 0, source: "Laravel" };
  }

  function employeeDetailUrl(template, employee) {
    if (!template || !employee?.recordId) return null;
    return template.replace("__EMPLOYEE__", encodeURIComponent(employee.recordId));
  }

  function employeeMovementApproveUrl(template, employee, movement) {
    if (!template || !employee?.recordId || !movement?.id) return null;
    return template.replace("__EMPLOYEE__", encodeURIComponent(employee.recordId)).replace("__MOVEMENT__", encodeURIComponent(movement.id));
  }

  function employeeDocumentApproveUrl(template, employee, document) {
    if (!template || !employee?.recordId || !document?.id) return null;
    return template.replace("__EMPLOYEE__", encodeURIComponent(employee.recordId)).replace("__DOCUMENT__", encodeURIComponent(document.id));
  }

  function employeeScopedCollectionUrl(baseUrl, employee, params={}) {
    if (!baseUrl || !employee?.recordId) return null;
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set("employee_id", employee.recordId);
    Object.entries(params).forEach(([key,value])=>{ if(value!==undefined&&value!==null&&value!=="") url.searchParams.set(key, value); });
    return url.toString();
  }

  function profileSectionDefaults(profile) {
    return { personal:{ dob:"", gender:"", marital:"", blood:"", mobile:"", email:"", ...(profile?.personal||{}) }, emergency:[...(profile?.emergency||[])], family:[...(profile?.family||[])], education:[...(profile?.education||[])], experience:[...(profile?.experience||[])] };
  }

  function normalizeLooseProfileDate(value, fallbackMonthDay) {
    const text = String(value || "").trim();
    if (!text) return "";
    if (/^\d{4}$/.test(text)) return `${text}-${fallbackMonthDay}`;
    return text;
  }

  function normalizeProfileSectionsForSave(profile) {
    const sections = profileSectionDefaults(profile);
    return {
      ...sections,
      education: sections.education.map(row => ({ ...row, year: row.year === "" || row.year == null ? "" : Number(row.year) })),
      experience: sections.experience.map(row => ({
        ...row,
        from: normalizeLooseProfileDate(row.from, "01-01"),
        to: normalizeLooseProfileDate(row.to, "12-31"),
      })),
    };
  }

  function EmployeeProfileSectionsModal({ profile, onClose, onSave, busy }) {
    const [form,setForm]=React.useState(()=>profileSectionDefaults(profile));
    const setPersonal=(key,value)=>setForm(f=>({...f,personal:{...f.personal,[key]:value}}));
    const updateRow=(section,index,key,value)=>setForm(f=>{const rows=[...(f[section]||[])];rows[index]={...(rows[index]||{}),[key]:value};return {...f,[section]:rows};});
    const addRow=(section,row)=>setForm(f=>({...f,[section]:[...(f[section]||[]),row]}));
    const removeRow=(section,index)=>setForm(f=>({...f,[section]:(f[section]||[]).filter((_,i)=>i!==index)}));
    const submit=e=>{e.preventDefault();onSave(normalizeProfileSectionsForSave(form));};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Edit Employee Profile Sections</h2><p>Personal, emergency, family, education and experience records are validated and saved to Laravel.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div><div className="hrx-form-grid"><label>Date of birth<input type="date" value={form.personal.dob||""} disabled={busy} onChange={e=>setPersonal("dob",e.target.value)}/></label><label>Gender<input value={form.personal.gender||""} disabled={busy} onChange={e=>setPersonal("gender",e.target.value)} placeholder="Gender"/></label><label>Marital status<input value={form.personal.marital||""} disabled={busy} onChange={e=>setPersonal("marital",e.target.value)} placeholder="Marital status"/></label><label>Blood group<input maxLength={10} value={form.personal.blood||""} disabled={busy} onChange={e=>setPersonal("blood",e.target.value.toUpperCase())} placeholder="B+"/></label><label>Mobile<input value={form.personal.mobile||""} disabled={busy} onChange={e=>setPersonal("mobile",e.target.value)} placeholder="+91 ..."/></label><label>Email<input type="email" value={form.personal.email||""} disabled={busy} onChange={e=>setPersonal("email",e.target.value)} placeholder="personal email"/></label></div><ProfileSectionEditor title="Emergency Contacts" section="emergency" rows={form.emergency} fields={[["name","Name"],["relation","Relation"],["phone","Phone"]]} blank={{name:"",relation:"",phone:""}} disabled={busy} updateRow={updateRow} addRow={addRow} removeRow={removeRow}/><ProfileSectionEditor title="Family" section="family" rows={form.family} fields={[["name","Name"],["relation","Relation"],["dependent","Dependent true/false"]]} blank={{name:"",relation:"",dependent:false}} disabled={busy} updateRow={updateRow} addRow={addRow} removeRow={removeRow}/><ProfileSectionEditor title="Education" section="education" rows={form.education} fields={[["qualification","Qualification"],["institute","Institute"],["year","Year"]]} blank={{qualification:"",institute:"",year:""}} disabled={busy} updateRow={updateRow} addRow={addRow} removeRow={removeRow}/><ProfileSectionEditor title="Experience" section="experience" rows={form.experience} fields={[["company","Company"],["role","Role"],["from","From YYYY-MM-DD"],["to","To YYYY-MM-DD"]]} blank={{company:"",role:"",from:"",to:""}} disabled={busy} updateRow={updateRow} addRow={addRow} removeRow={removeRow}/><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="check" size={15}/>{busy?"Saving…":"Save Profile Sections"}</button></div></form></div>;
  }

  function EmployeeMovementModal({ employee, options, onClose, onSave, busy, canSalary }) {
    const today = new Date().toISOString().slice(0,10);
    const [form,setForm]=React.useState({movement_type:"promotion",effective_on:today,status:"approved",designation:employee.designation||"",grade:employee.grade==="—"?"":employee.grade||"",department:employee.department||"",branch_id:"",project_id:"",manager_employee_id:"",monthly_ctc:employee.netSalary||"",employee_status:employee.status||"active",reason:"",remarks:""});
    const set=(k,v)=>setForm(f=>({...f,[k]:v}));
    const movementFields = type => {
      if(type==="transfer")return <><label>Branch<select value={form.branch_id} disabled={busy} onChange={e=>set("branch_id",e.target.value)}><option value="">No branch</option>{(options?.branches||[]).map(b=><option key={b.id} value={b.id}>{b.label}</option>)}</select></label><label>Project / Site<select value={form.project_id} disabled={busy} onChange={e=>set("project_id",e.target.value)}><option value="">Corporate / no project</option>{(options?.projects||[]).map(p=><option key={p.id} value={p.id}>{p.label}</option>)}</select></label><label>Department<select value={form.department} disabled={busy} onChange={e=>set("department",e.target.value)}>{(options?.departments||[]).map(d=><option key={d}>{d}</option>)}</select></label></>;
      if(type==="promotion")return <><label>Designation<input required value={form.designation} disabled={busy} onChange={e=>set("designation",e.target.value)}/></label><label>Grade<select value={form.grade} disabled={busy} onChange={e=>set("grade",e.target.value)}><option value="">No grade</option>{(options?.grades||[]).map(g=><option key={g}>{g}</option>)}</select></label>{canSalary&&<label>Monthly CTC<input type="number" min="0" step="0.01" value={form.monthly_ctc} disabled={busy} onChange={e=>set("monthly_ctc",e.target.value)}/></label>}</>;
      if(type==="department_change")return <label>Department<select value={form.department} disabled={busy} onChange={e=>set("department",e.target.value)}>{(options?.departments||[]).map(d=><option key={d}>{d}</option>)}</select></label>;
      if(type==="reporting_change")return <label>Reporting manager<SearchablePeoplePicker items={options?.managers||[]} selected={form.manager_employee_id} mode="single" disabled={busy} placeholder="Search manager name, code, department..." emptyText="No matching managers" onChange={value=>set("manager_employee_id",value||"")} getId={m=>m.id} getLabel={m=>m.name||m.label||"Manager"} getSubLabel={m=>m.label&&m.name?m.label:[m.employee_code,m.department,m.designation,m.email].filter(Boolean).join(" · ")}/></label>;
      if(type==="salary_change")return canSalary?<label>Monthly CTC<input required type="number" min="0" step="0.01" value={form.monthly_ctc} disabled={busy} onChange={e=>set("monthly_ctc",e.target.value)}/></label>:<div className="hrx-warning"><Icon name="shield"/><div><b>Salary restricted</b><span>Your role cannot create salary movements.</span></div></div>;
      if(type==="status_change")return <label>Status<select value={form.employee_status} disabled={busy} onChange={e=>set("employee_status",e.target.value)}>{(options?.statuses||[]).map(s=><option key={s.value} value={s.value}>{s.label}</option>)}</select></label>;
      return <label>Grade<select value={form.grade} disabled={busy} onChange={e=>set("grade",e.target.value)}><option value="">No grade</option>{(options?.grades||[]).map(g=><option key={g}>{g}</option>)}</select></label>;
    };
    const payloadValues=()=>{
      if(form.movement_type==="transfer")return {branch_id:form.branch_id?Number(form.branch_id):null,project_id:form.project_id?Number(form.project_id):null,department:form.department};
      if(form.movement_type==="promotion")return {designation:form.designation.trim(),grade:form.grade||null,...(canSalary?{monthly_ctc:form.monthly_ctc===""?null:Number(form.monthly_ctc)}:{})};
      if(form.movement_type==="department_change")return {department:form.department};
      if(form.movement_type==="reporting_change")return {manager_employee_id:form.manager_employee_id?Number(form.manager_employee_id):null};
      if(form.movement_type==="salary_change")return {monthly_ctc:Number(form.monthly_ctc)};
      if(form.movement_type==="status_change")return {status:form.employee_status};
      return {grade:form.grade||null};
    };
    const submit=e=>{e.preventDefault();onSave({movement_type:form.movement_type,effective_on:form.effective_on,status:form.status,new_values:payloadValues(),reason:form.reason.trim()||null,remarks:form.remarks.trim()||null});};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Record Employee Movement</h2><p>Creates an effective-dated Laravel movement with before/after values, workflow history, notification and audit trail.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div><div className="hrx-form-grid"><label>Employee<input value={`${employee.code} · ${employee.name}`} disabled/></label><label>Movement type<select value={form.movement_type} disabled={busy} onChange={e=>set("movement_type",e.target.value)}><option value="promotion">Promotion</option><option value="transfer">Transfer</option><option value="department_change">Department Change</option><option value="reporting_change">Reporting Change</option><option value="salary_change" disabled={!canSalary}>Salary Change</option><option value="status_change">Status Change</option><option value="grade_change">Grade Change</option></select></label><label>Effective on<input required type="date" value={form.effective_on} disabled={busy} onChange={e=>set("effective_on",e.target.value)}/></label><label>Workflow status<select value={form.status} disabled={busy} onChange={e=>set("status",e.target.value)}><option value="approved">Approved and apply now</option><option value="pending">Pending approval</option></select></label>{movementFields(form.movement_type)}<label style={{gridColumn:"1 / -1"}}>Reason<textarea value={form.reason} disabled={busy} onChange={e=>set("reason",e.target.value)} placeholder="Business reason, approval reference, transfer note or salary revision basis."/></label><label style={{gridColumn:"1 / -1"}}>Remarks<textarea value={form.remarks} disabled={busy} onChange={e=>set("remarks",e.target.value)} placeholder="Approval remarks or HR notes."/></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="check" size={15}/>{busy?"Saving…":"Save Movement"}</button></div></form></div>;
  }

  function ProfileSectionEditor({ title, section, rows, fields, blank, disabled, updateRow, addRow, removeRow }) {
    return <div className="hrx-subform"><div className="hrx-section-head"><div><div className="card-title">{title}</div><div className="card-sub">{rows.length} record(s)</div></div><Button type="button" sm icon="plus" onClick={()=>addRow(section,blank)}>Add</Button></div>{rows.length===0&&<div className="muted">No records added.</div>}{rows.map((row,index)=><div className="hrx-form-grid" key={`${section}-${index}`}>{fields.map(([key,label])=><label key={key}>{label}<input value={String(row[key]??"")} disabled={disabled} onChange={e=>updateRow(section,index,key,key==="dependent"?e.target.value==="true":e.target.value)} placeholder={label}/></label>)}<label>Action<button type="button" className="chip-select" disabled={disabled} onClick={()=>removeRow(section,index)}>Remove</button></label></div>)}</div>;
  }

  function EmployeesView({ state, update, role, toast }) {
    const [query, setQuery] = React.useState("");
    const [selected, setSelected] = React.useState(null);
    const [adding, setAdding] = React.useState(false);
    const [importing, setImporting] = React.useState(false);
    const employeeOptions=window.Builder360Server?.hr_employee_options||null;
    const [companyFilter,setCompanyFilter]=React.useState("All");
    const [departmentFilter,setDepartmentFilter]=React.useState("All");
    const [serverRows,setServerRows]=React.useState([]);
    const [serverLoaded,setServerLoaded]=React.useState(false);
    const [loading,setLoading]=React.useState(false);
    const [loadError,setLoadError]=React.useState("");
    const canSalary = (PERMISSIONS[role.id] || {}).salary;
    const loadEmployees=React.useCallback(async()=>{if(!employeeOptions?.index_url)return;setLoading(true);setLoadError("");try{const url=new URL(employeeOptions.index_url,window.location.origin);url.searchParams.set("per_page","50");if(companyFilter!=="All")url.searchParams.set("company_id",companyFilter);if(departmentFilter!=="All")url.searchParams.set("department",departmentFilter);if(query.trim())url.searchParams.set("search",query.trim());const body=await apiJson(url.toString());setServerRows((body.data||[]).map(employeeRowFromApi));setServerLoaded(true);}catch(err){setLoadError(err.message||"Unable to load employees from Laravel.");toast&&toast("Employee directory API issue: "+(err.message||"load failed"),"orange");}finally{setLoading(false);}},[employeeOptions?.index_url,companyFilter,departmentFilter,query,toast]);
    React.useEffect(()=>{loadEmployees();},[loadEmployees]);
    const filtered = serverLoaded ? serverRows : [];
    const emptyTitle = serverLoaded ? "No employees match current filters" : "Employee Master API required";
    const emptyText = serverLoaded ? "Adjust company, department or search filters to view authorized Laravel employee records." : "No local prototype employees are fabricated when the Laravel employee index API has not loaded.";
    const matchesCurrentFilters = row => (companyFilter==="All"||String(row.company?.id||row.company_id||"")===String(companyFilter)) && (departmentFilter==="All"||row.department===departmentFilter) && (!query.trim()||`${row.name} ${row.employee_code} ${row.department} ${row.project?.name||""}`.toLowerCase().includes(query.trim().toLowerCase()));
    const onCreated=row=>{const mapped=employeeRowFromApi(row);if(matchesCurrentFilters(row)){setServerRows(rows=>[mapped,...rows.filter(x=>x.id!==mapped.id)]);setServerLoaded(true);}update((s,actor)=>{const existing=s.employees.findIndex(e=>e.id===mapped.id);if(existing>=0)s.employees[existing]=mapped;else s.employees.unshift(mapped);s.employeeProfiles[mapped.id]=s.employeeProfiles[mapped.id]||{personal:{},emergency:[],family:[],education:[],experience:[]};addAudit(s,actor,"Created Laravel employee master record",row.employee_code,"Create");},"Employee master updated from Laravel");};
    const openCreate=()=>employeeOptions?.can_create&&employeeOptions?.store_url?setAdding(true):unavailableAction(update,"Employee creation","Employee Master");
    const openImport=()=>employeeOptions?.can_import&&employeeOptions?.import_preview_url&&employeeOptions?.import_post_url_template?setImporting(true):unavailableAction(update,"Employee bulk import","Employee Master");
    const onImportPosted=batch=>{loadEmployees();update((s,actor)=>addAudit(s,actor,"Posted Laravel employee import",batch.import_number||batch.id,"Import"),"Employee import posted to Laravel");};
    return <div><ViewTitle title="Employee Master" sub="One employee record across CRM identity, HR, payroll, documents and lifecycle." actions={[<Button key="i" icon="upload" sm onClick={openImport}>Bulk Import</Button>, <Button key="a" icon="plus" variant="primary" sm onClick={openCreate}>Add Employee</Button>]}/><div className="hrx-toolbar"><div className="hrx-search"><Icon name="search" size={15}/><input value={query} onChange={e => setQuery(e.target.value)} placeholder="Search employees, code, department or site…"/></div><select className="chip-select" value={companyFilter} onChange={e=>setCompanyFilter(e.target.value)}><option value="All">All companies</option>{(employeeOptions?.companies||[]).map(c=><option key={c.id} value={c.id}>{c.label}</option>)}</select><select className="chip-select" value={departmentFilter} onChange={e=>setDepartmentFilter(e.target.value)} disabled={!(employeeOptions?.departments||[]).length}><option value="All">All departments</option>{(employeeOptions?.departments||[]).map(d=><option key={d} value={d}>{d}</option>)}</select><Badge tone={serverLoaded?"b-green":employeeOptions?.source==="laravel-sqlite"?"b-blue":"b-orange"}>{loading?"Loading Laravel":serverLoaded?"Laravel directory":employeeOptions?.source==="laravel-sqlite"?"Laravel create enabled":"Backend API required"}</Badge><Badge tone="b-slate">{filtered.length} shown</Badge></div>{loadError&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Directory API issue</b><span>{loadError}</span></div></div>}<Section title="Employee Directory" sub={serverLoaded?"Server-backed employee master with search and scoped filters":"Click an employee for the 360° profile"}><Table rows={filtered} onRow={setSelected} columns={[
      { label: "Employee", render: r => <Person employee={r} sub={`${r.code} · ${r.designation}`}/> }, { label: "Department", render: r => <span className="tag">{r.department}</span> },
      { label: "Company / Site", render: r => <div><b>{r.companyId}</b><div className="cell-sub">{r.project} · {r.state}</div></div> }, { label: "Grade", key: "grade" },
      { label: "Attendance", render: r => <ProgCell value={r.attendance}/> }, { label: "Net salary", right: true, render: r => canSalary ? <b className="mono">{money(r.netSalary)}</b> : <span className="faint">Restricted</span> },
      { label: "Status", render: r => <StatePill>{r.status}</StatePill> },
    ]}/></Section>{selected && <EmployeeDrawer employee={selected} profile={state.employeeProfiles[selected.id]} canSalary={canSalary} options={employeeOptions} toast={toast} onClose={() => setSelected(null)}/>} {adding && <EmployeeModal options={employeeOptions} onClose={() => setAdding(false)} onCreated={onCreated} toast={toast}/>} {importing && <EmployeeImportModal options={employeeOptions} onClose={() => setImporting(false)} onPosted={onImportPosted} toast={toast}/>}</div>;
  }

  function EmployeeImportModal({ options, onClose, onPosted, toast }) {
    const companies=options?.companies||[];
    const [companyId,setCompanyId]=React.useState(options?.import_requires_company_selection?"":String(companies[0]?.id||""));
    const [file,setFile]=React.useState(null);
    const [note,setNote]=React.useState("");
    const [preview,setPreview]=React.useState(null);
    const [busy,setBusy]=React.useState(false);
    const [posting,setPosting]=React.useState(false);
    const [error,setError]=React.useState("");
    const headers=(options?.import_required_headers||[]).join(",");
    const canPost=preview?.id&&preview.status==="preview"&&Number(preview.invalid_rows||0)===0&&Number(preview.valid_rows||0)>0;
    const choose=ev=>{setFile(ev.target.files?.[0]||null);setPreview(null);setError("");};
    const previewImport=async ev=>{ev.preventDefault();setError("");if(options?.import_requires_company_selection&&!companyId){setError("Company is required for global import users.");return;}if(!file){setError("Select a CSV file before preview.");return;}const name=String(file.name||"").toLowerCase();if(!name.endsWith(".csv")&&!name.endsWith(".txt")){setError("Only CSV or TXT employee import files are supported.");return;}if(file.size>Number(options?.import_max_file_size_kb||512)*1024){setError(`File must be ${options?.import_max_file_size_kb||512} KB or smaller.`);return;}try{setBusy(true);const form=new FormData();form.append("import_type",options.import_type||"hr_employees");form.append("source_file",file);if(companyId)form.append("company_id",companyId);if(note.trim())form.append("note",note.trim());const response=await fetch(options.import_preview_url,{method:"POST",credentials:"same-origin",headers:{Accept:"application/json","X-CSRF-TOKEN":csrfToken(),"X-Requested-With":"XMLHttpRequest"},body:form});const body=await response.json().catch(()=>({}));if(!response.ok){const validation=body.errors&&Object.values(body.errors).flat().filter(Boolean).join(" ");throw new Error(validation||body.message||"Employee import preview failed.");}setPreview(body.data);toast&&toast("Employee import preview generated from Laravel.","green");}catch(err){setError(err.message||"Employee import preview failed.");}finally{setBusy(false);}};
    const postImport=async()=>{if(!canPost)return;try{setPosting(true);const body=await apiJson(options.import_post_url_template.replace("__BATCH__",preview.id),{method:"POST",body:JSON.stringify({note:note.trim()||"Posted employee import from Employee Master."})});setPreview(body.data);toast&&toast("Employee import posted to employee master.","green");onPosted&&onPosted(body.data);onClose();}catch(err){setError(err.message||"Employee import post failed.");}finally{setPosting(false);}};
    return <div className="scrim" onClick={busy||posting?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={previewImport}><div className="hrx-modal-head"><div><h2>Employee Bulk Import</h2><p>Preview-first Laravel import with header validation, duplicate checks, row errors, reconciliation and audit trail.</p></div><button type="button" className="icon-btn" disabled={busy||posting} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Employee import issue</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Company<select value={companyId} disabled={busy||posting||!options?.import_requires_company_selection} onChange={e=>setCompanyId(e.target.value)}><option value="">Select company</option>{companies.map(c=><option key={c.id} value={c.id}>{c.label||`${c.code} · ${c.name}`}</option>)}</select></label><label>CSV/TXT file<input type="file" accept=".csv,.txt,text/csv,text/plain" disabled={busy||posting} onChange={choose}/></label><label style={{gridColumn:"1 / -1"}}>Required header<textarea value={headers} disabled /></label><label style={{gridColumn:"1 / -1"}}>Sample CSV<textarea value={options?.import_sample_csv||headers} disabled /></label><label style={{gridColumn:"1 / -1"}}>Note<textarea maxLength={1000} value={note} disabled={busy||posting} onChange={e=>setNote(e.target.value)} placeholder="Import purpose, source file reference or reconciliation note."/></label></div>{preview&&<><KpiGrid><Stat label="Total Rows" value={preview.total_rows||0} icon="doc" tone="blue"/><Stat label="Valid Rows" value={preview.valid_rows||0} icon="check" tone="green"/><Stat label="Invalid Rows" value={preview.invalid_rows||0} icon="alert" tone={preview.invalid_rows?"red":"green"}/><Stat label="Status" value={preview.status} icon="shield" tone="violet"/></KpiGrid><Section title="Preview Rows" sub="Rows are not posted until validation errors are resolved and Post Import is clicked"><Table rows={preview.preview_rows||[]} columns={[{label:"Row",key:"row_number"},{label:"Code",key:"employee_code"},{label:"Employee",key:"name"},{label:"Department",key:"department"},{label:"Designation",key:"designation"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Errors",render:r=><span className="cell-sub">{Object.values(r.errors||{}).join(" ")||"—"}</span>}]}/></Section>{(preview.error_report||[]).length>0&&<Section title="Error Report" sub="Fix the source file and preview again"><Table rows={preview.error_report} columns={[{label:"Row",key:"row_number"},{label:"Field",key:"field"},{label:"Issue",key:"message"},{label:"Value",render:r=><span className="cell-sub">{String(r.value??"")}</span>}]}/></Section>}</>}<div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn" type="submit" disabled={busy||posting}>{busy?"Previewing…":"Preview Import"}</button><button className="btn btn-primary" type="button" disabled={!canPost||posting} onClick={postImport}><Icon name="upload" size={15}/>{posting?"Posting…":"Post Import"}</button></div></form></div>;
  }

  function AttendanceView({ state, update, role }) {
    const unavailable = label => unavailableAction(update,label,"Attendance Management");
    return <div><ViewTitle title="Attendance Management" sub="Attendance API required; no local punch, site, exception, geofence or calculation rows are fabricated." actions={<Button icon="refresh" variant="primary" sm onClick={() => unavailable("Attendance refresh")}>Backend attendance API required</Button>}/><div className="hrx-demo-banner"><Icon name="shield" size={17}/><div><b>Attendance capture is disabled in fallback mode.</b><span>Live GPS, biometric and geofence check-in must come through governed Laravel attendance APIs or approved external adapters; this screen does not display local demo attendance records.</span></div><Badge tone="b-orange">API REQUIRED</Badge></div><KpiGrid><Stat label="Present" value="—" icon="check" tone="green" sub="Requires attendance records API"/><Stat label="Late" value="—" icon="calClock" tone="orange" sub="Requires shift calculation API"/><Stat label="Early Leaving" value="—" icon="alert" tone="orange" sub="Requires shift calculation API"/><Stat label="Half-day" value="—" icon="trend" tone="violet" sub="Requires policy rules"/><Stat label="Absent" value="—" icon="x" tone="red" sub="Requires attendance records API"/></KpiGrid><div className="hrx-grid-2"><Section title="Site Attendance" sub="Backend attendance API required"><EmptyPanel icon="pin" title="No site attendance loaded" text="Site strength, present counts, coverage and geofence status are hidden until Laravel attendance records and approved integration data are available."/></Section><Section title="Attendance Exceptions" sub="Backend regularization workflow required"><EmptyPanel icon="funnel" title="No exception queue loaded" text="Regularization requests and approvals are hidden until the Laravel attendance regularization workflow is available."/></Section></div><Section title="Early-leaving Calculation Trace" sub="Backend shift and attendance calculation API required"><EmptyPanel icon="clock" title="No calculation trace loaded" text="Late, early-leaving, half-day, overnight and grace-boundary calculations must come from governed shift rules and persisted attendance records."/></Section></div>;
  }

  function AttendanceViewV2({ state, update, role, toast, attendanceOptions }) {
    if (!attendanceOptions?.attendance_records_index_url || !attendanceOptions?.attendance_regularizations_index_url) {
      return <AttendanceView state={state} update={update} role={role}/>;
    }
    const [records,setRecords]=React.useState([]);
    const [regularizations,setRegularizations]=React.useState([]);
    const [recordStatus,setRecordStatus]=React.useState("");
    const [regularizationStatus,setRegularizationStatus]=React.useState("");
    const [loading,setLoading]=React.useState(false);
    const [error,setError]=React.useState("");
    const [busyId,setBusyId]=React.useState("");
    const formatDate=value=>value?new Date(value).toLocaleDateString("en-IN"):"—";
    const formatDateTime=value=>value?new Date(value).toLocaleString("en-IN",{dateStyle:"medium",timeStyle:"short"}):"—";
    const minutes=value=>Number(value||0).toLocaleString("en-IN");
    const load=React.useCallback(async()=>{
      setLoading(true);setError("");
      try{
        const recordParams={per_page:50};
        const requestParams={per_page:50};
        if(recordStatus)recordParams.status=recordStatus;
        if(regularizationStatus)requestParams.status=regularizationStatus;
        const [recordBody,requestBody]=await Promise.all([
          apiJson(collectionUrl(attendanceOptions.attendance_records_index_url,recordParams)),
          apiJson(collectionUrl(attendanceOptions.attendance_regularizations_index_url,requestParams)),
        ]);
        setRecords(recordBody.data||[]);
        setRegularizations(requestBody.data||[]);
      }catch(err){
        setError(err.message||"Attendance records could not be loaded from Laravel.");
        toast&&toast("Attendance workspace load failed: "+(err.message||"request failed"),"orange");
      }finally{setLoading(false);}
    },[attendanceOptions?.attendance_records_index_url,attendanceOptions?.attendance_regularizations_index_url,recordStatus,regularizationStatus,toast]);
    React.useEffect(()=>{load();},[load]);
    const decide=async(row,decision)=>{
      const template=decision==="approved"?attendanceOptions?.attendance_regularization_approve_url_template:attendanceOptions?.attendance_regularization_reject_url_template;
      if(!template||!attendanceOptions?.can_approve_regularization){unavailableAction(update,"Attendance regularization approval","Attendance Management");return;}
      setBusyId(`${decision}-${row.id}`);
      try{
        const body=await apiJson(template.replace("__REGULARIZATION__",row.id),{method:"PATCH",body:JSON.stringify({decision_note:`${decision==="approved"?"Approved":"Rejected"} from HR Attendance workspace.`})});
        const updated=body.data||row;
        setRegularizations(current=>current.map(item=>item.id===updated.id?updated:item));
        toast&&toast(decision==="approved"?"Attendance regularization approved in Laravel workflow.":"Attendance regularization rejected in Laravel workflow.","green");
      }catch(err){
        setError(err.message||"Attendance regularization workflow action failed.");
        toast&&toast("Attendance workflow action failed: "+(err.message||"request failed"),"orange");
      }finally{setBusyId("");}
    };
    const presentCount=records.filter(r=>r.status==="present").length;
    const lateMinutes=records.reduce((sum,r)=>sum+Number(r.late_minutes||0),0);
    const earlyMinutes=records.reduce((sum,r)=>sum+Number(r.early_leave_minutes||0),0);
    const pendingCount=regularizations.filter(r=>r.status==="submitted").length;
    return <div><ViewTitle title="Attendance Management" sub="MySQL attendance records, calculated late/early minutes and regularization approval workflow." actions={<Button icon="refresh" variant="primary" sm onClick={load}>{loading?"Loading…":"Refresh"}</Button>}/>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Attendance API issue</b><span>{error}</span></div></div>}<div className="hrx-demo-banner"><Icon name="shield" size={17}/><div><b>Biometric/GPS/geofence adapters are configuration records only in this build.</b><span>Attendance rows below are loaded from Laravel; no live device feed, mobile tracking service, or operational geofence enforcement is claimed from this screen.</span></div><Badge tone="b-green">SQLITE BACKED</Badge></div><KpiGrid><Stat label="Loaded Records" value={records.length} icon="calClock" tone="blue" sub="Scoped by Laravel policy"/><Stat label="Present" value={presentCount} icon="check" tone="green"/><Stat label="Late Minutes" value={minutes(lateMinutes)} icon="alert" tone="orange"/><Stat label="Early Leave Minutes" value={minutes(earlyMinutes)} icon="trend" tone="orange"/><Stat label="Pending Requests" value={pendingCount} icon="funnel" tone="violet"/></KpiGrid><div className="hrx-grid-2"><Section title="Attendance Rule Source" sub="Configured rules are stored in governed settings and shift records"><div className="hrx-settings-grid"><Setting label="Records API" value={attendanceOptions.attendance_records_index_url}/><Setting label="Regularization API" value={attendanceOptions.attendance_regularizations_index_url}/><Setting label="Approval control" value={attendanceOptions.can_approve_regularization?"Separate approver enforced":"Read/request scope only"}/><Setting label="Shift rules" value="Grace, half-day, overnight and geofence-required flags from shift configuration"/><Setting label="Device integrations" value="Configured/simulated adapter metadata only"/><Setting label="Payroll readiness" value="Late, early, half-day and absence status available for downstream payroll"/></div></Section><Section title="Filters" sub="Server query filters with role/company scoping"><div className="hrx-form-grid"><label>Attendance status<select value={recordStatus} onChange={e=>setRecordStatus(e.target.value)}><option value="">All statuses</option>{(attendanceOptions.status_filters||[]).map(x=><option key={x.value} value={x.value}>{x.label}</option>)}</select></label><label>Regularization status<select value={regularizationStatus} onChange={e=>setRegularizationStatus(e.target.value)}><option value="">All requests</option>{(attendanceOptions.regularization_status_filters||[]).map(x=><option key={x.value} value={x.value}>{x.label}</option>)}</select></label><label>Source scope<input disabled value={(attendanceOptions.source_filters||[]).map(x=>x.label).join(" · ")}/></label><label>Data source<input disabled value="Laravel with company/employee policy scope"/></label></div></Section></div><Section title="Attendance Records" sub="Persisted check-in/check-out, late, early-leaving, worked minutes and status"><Table rows={records} columns={[{label:"Employee",render:r=><Person employee={r.employee?.name||"Employee"} sub={r.employee?.employee_code||r.employee?.department||"Scoped record"}/>},{label:"Date",render:r=>formatDate(r.work_date)},{label:"Shift",render:r=><div><b>{r.shift?.name||"—"}</b><div className="cell-sub">{r.shift?.code||"No shift code"}</div></div>},{label:"Source",render:r=><Badge tone="b-blue">{String(r.source||"manual").replaceAll("_"," ")}</Badge>},{label:"In / Out",render:r=><div><b>{formatDateTime(r.check_in_at)}</b><div className="cell-sub">{formatDateTime(r.check_out_at)}</div></div>},{label:"Worked",right:true,render:r=><span className="mono">{minutes(r.worked_minutes)} min</span>},{label:"Late",right:true,render:r=><b className="mono">{minutes(r.late_minutes)}</b>},{label:"Early",right:true,render:r=><b className="mono">{minutes(r.early_leave_minutes)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section><Section title="Regularization Workflow" sub="Submitted requests can be approved/rejected by authorized attendance approvers only"><Table rows={regularizations} columns={[{label:"Request",render:r=><div><b>{r.request_number}</b><div className="cell-sub">{r.reason||"No reason provided"}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name||"Employee"} sub={r.employee?.employee_code||r.employee?.department||"Scoped request"}/>},{label:"Work date",render:r=>formatDate(r.work_date)},{label:"Requested time",render:r=><div><b>{formatDateTime(r.requested_check_in_at)}</b><div className="cell-sub">{formatDateTime(r.requested_check_out_at)}</div></div>},{label:"Calculated result",render:r=><span className="cell-sub">Late {minutes(r.attendance_record?.late_minutes)} · Early {minutes(r.attendance_record?.early_leave_minutes)} · Worked {minutes(r.attendance_record?.worked_minutes)}</span>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="submitted"&&attendanceOptions.can_approve_regularization?<div className="hrx-chip-wrap"><button className="hrx-link" disabled={!!busyId} onClick={()=>decide(r,"approved")}>{busyId===`approved-${r.id}`?"Approving…":"Approve"}</button><button className="hrx-link" disabled={!!busyId} onClick={()=>decide(r,"rejected")}>{busyId===`rejected-${r.id}`?"Rejecting…":"Reject"}</button></div>:<span className="faint">—</span>}]}/></Section></div>;
  }

  function ShiftCreateModal({ options, onClose, onCreated, toast }) {
    const companies = options?.companies || [];
    const [form, setForm] = React.useState({ company_id: companies[0]?.id || "", code: "", name: "", starts_at: "09:30", ends_at: "18:30", is_overnight: false, late_grace_minutes: 15, early_leave_grace_minutes: 10, half_day_threshold_minutes: 240, full_day_threshold_minutes: 480, shift_type: "fixed", weekly_off_policy: "Company calendar", overtime_enabled: true, geofence_required: false });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!options?.shifts_store_url || !options?.can_create_shift) {
        setError("Attendance shift creation is not available for this role.");
        return;
      }
      if (!form.company_id) {
        setError("Select a company before creating a shift.");
        return;
      }
      if (!form.is_overnight && form.ends_at < form.starts_at) {
        setError("Enable overnight shift when the end time is earlier than start time.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(options.shifts_store_url, {
          method: "POST",
          body: JSON.stringify({
            company_id: Number(form.company_id),
            code: form.code.trim().toUpperCase(),
            name: form.name.trim(),
            starts_at: form.starts_at,
            ends_at: form.ends_at,
            is_overnight: !!form.is_overnight,
            late_grace_minutes: Number(form.late_grace_minutes),
            early_leave_grace_minutes: Number(form.early_leave_grace_minutes),
            half_day_threshold_minutes: Number(form.half_day_threshold_minutes),
            full_day_threshold_minutes: Number(form.full_day_threshold_minutes),
            rules: {
              shift_type: form.shift_type,
              weekly_off_policy: form.weekly_off_policy,
              overtime_enabled: !!form.overtime_enabled,
              geofence_required: !!form.geofence_required,
            },
          }),
        });
        onCreated && onCreated(body.data);
        toast && toast("Attendance shift created in Laravel.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Attendance shift could not be created.");
        toast && toast("Shift not created: " + (err.message || "request failed"), "orange");
      } finally {
        setBusy(false);
      }
    };
    return <div className="scrim" onClick={busy ? undefined : onClose}><form className="modal hrx-modal" onClick={e => e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>New Attendance Shift</h2><p>Creates a scoped Laravel shift with grace, half-day, full-day, overtime and geofence rules for attendance calculation.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Shift not created</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Company<select required value={form.company_id} disabled={busy || !companies.length} onChange={e => set("company_id", e.target.value)}>{companies.map(c => <option key={c.id} value={c.id}>{c.label}</option>)}</select></label><label>Shift code<input required pattern="[A-Z0-9_-]+" maxLength={32} value={form.code} disabled={busy} onChange={e => set("code", e.target.value.toUpperCase())} placeholder="GEN2"/></label><label>Shift name<input required maxLength={255} value={form.name} disabled={busy} onChange={e => set("name", e.target.value)} placeholder="General Second Shift"/></label><label>Shift type<select value={form.shift_type} disabled={busy} onChange={e => set("shift_type", e.target.value)}>{(options?.shift_types || []).map(t => <option key={t.value} value={t.value}>{t.label}</option>)}</select></label><label>Starts at<input required type="time" value={form.starts_at} disabled={busy} onChange={e => set("starts_at", e.target.value)}/></label><label>Ends at<input required type="time" value={form.ends_at} disabled={busy} onChange={e => set("ends_at", e.target.value)}/></label><label>Late grace minutes<input required type="number" min="0" max="240" value={form.late_grace_minutes} disabled={busy} onChange={e => set("late_grace_minutes", e.target.value)}/></label><label>Early leave grace<input required type="number" min="0" max="240" value={form.early_leave_grace_minutes} disabled={busy} onChange={e => set("early_leave_grace_minutes", e.target.value)}/></label><label>Half-day threshold<input required type="number" min="1" max="1440" value={form.half_day_threshold_minutes} disabled={busy} onChange={e => set("half_day_threshold_minutes", e.target.value)}/></label><label>Full-day threshold<input required type="number" min="1" max="1440" value={form.full_day_threshold_minutes} disabled={busy} onChange={e => set("full_day_threshold_minutes", e.target.value)}/></label><label>Weekly-off policy<input maxLength={120} value={form.weekly_off_policy} disabled={busy} onChange={e => set("weekly_off_policy", e.target.value)} placeholder="Company calendar"/></label><label>Controls<div className="hrx-chip-wrap"><span className="hrx-chip"><input type="checkbox" checked={form.is_overnight} disabled={busy} onChange={e => set("is_overnight", e.target.checked)}/> Overnight</span><span className="hrx-chip"><input type="checkbox" checked={form.overtime_enabled} disabled={busy} onChange={e => set("overtime_enabled", e.target.checked)}/> Overtime</span><span className="hrx-chip"><input type="checkbox" checked={form.geofence_required} disabled={busy} onChange={e => set("geofence_required", e.target.checked)}/> Geofence</span></div></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy || !companies.length}><Icon name="plus" size={15}/>{busy ? "Creating…" : "Create Shift"}</button></div></form></div>;
  }

  function ShiftsView({ state, update, toast, attendanceOptions }) {
    const serverUrl = attendanceOptions?.shifts_index_url;
    const [rows, setRows] = React.useState([]);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState("");
    const [creating, setCreating] = React.useState(false);
    React.useEffect(() => {
      if (!serverUrl) return;
      let alive = true;
      setLoading(true);
      setError("");
      apiJson(collectionUrl(serverUrl, { per_page: 50 }))
        .then(body => { if (alive) setRows(body.data || []); })
        .catch(err => { if (alive) setError(err.message || "Attendance shifts could not be loaded."); })
        .finally(() => { if (alive) setLoading(false); });
      return () => { alive = false; };
    }, [serverUrl]);
    const timeRange = row => row.starts_at ? `${String(row.starts_at).slice(0, 5)}–${String(row.ends_at).slice(0, 5)}` : row.time;
    const grace = row => row.late_grace_minutes !== undefined ? `Late ${row.late_grace_minutes} min · Early ${row.early_leave_grace_minutes} min` : `Grace ${row.grace}`;
    const openCreate = () => attendanceOptions?.can_create_shift && attendanceOptions?.shifts_store_url ? setCreating(true) : unavailableAction(update, "Shift creation", "Shifts");
    const onCreated = row => {
      setRows(current => [row, ...current.filter(item => item.id !== row.id)]);
      update((s, actor) => addAudit(s, actor, "Created Laravel attendance shift", row.code || row.name, "Shifts"), "Attendance shift created");
    };
    return <div><ViewTitle title="Shifts, Rosters & Overtime" sub="Effective-dated rules by company, branch, location, department and employee category." actions={<Button icon="plus" variant="primary" sm onClick={openCreate}>New Shift</Button>}/>{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Shift register API issue</b><span>{error}</span></div></div>}<div className="hrx-toolbar"><Badge tone={serverUrl ? "b-green" : "b-orange"}>{loading ? "Loading Laravel shifts" : serverUrl ? "Laravel shift register" : "Shift API required"}</Badge><Badge tone="b-slate">{rows.length} configured</Badge></div><div className="hrx-card-grid">{rows.map(s => <div className="card card-pad hrx-feature-card" key={s.id || s.name}><div className="row between"><div className="hrx-feature-icon"><Icon name="calClock"/></div><Badge tone={s.is_active === false ? "b-slate" : "b-green"}>{s.is_active === false ? "INACTIVE" : "ACTIVE"}</Badge></div><h3>{s.name}</h3><strong>{timeRange(s)}</strong><p>{s.rules?.shift_type ? `${s.rules.shift_type} · ${s.rules.weekly_off_policy || "Configured policy"}` : (s.location || "Company scoped attendance rule")}</p><div className="hrx-meta-row"><span>{grace(s)}</span><span>{s.is_overnight ? "Overnight" : (s.employees ? `${s.employees} employees` : "Same-day")}</span></div></div>)}</div><Section title="Roster Controls" sub="Configuration-first design"><div className="hrx-settings-grid"><Setting label="Shift types" value="Fixed · Flexible · Rotational · Night · Split"/><Setting label="Overtime" value="Threshold · cap · rate multiplier · approval"/><Setting label="Weekly offs" value="Roster, location and employee-specific"/><Setting label="Cross-midnight" value="Punch pairing and work-date policy"/><Setting label="Shift swaps" value="Employee → Manager → HR (conditional)"/><Setting label="Payroll linkage" value="Allowance, overtime and LOP inputs"/></div></Section>{creating && <ShiftCreateModal options={attendanceOptions} onClose={() => setCreating(false)} onCreated={onCreated} toast={toast}/>}</div>;
  }

  function LeaveView({ state, update, role }) {
    const unavailable = label => unavailableAction(update, label, "Leave Management");
    return <div><ViewTitle title="Leave Management" sub="Leave API required for requests, balances, processing runs, encashment and approvals." actions={<Button icon="plus" variant="primary" sm onClick={() => unavailable("Leave request submission")}>Backend leave API required</Button>}/><div className="hrx-demo-banner"><Icon name="shield" size={17}/><div><b>Leave fallback is read-only.</b><span>No local leave requests, balances, accrual runs, encashments or approvals are fabricated without the governed Laravel leave workflow.</span></div><Badge tone="b-orange">API REQUIRED</Badge></div><KpiGrid><Stat label="Pending Requests" value="—" icon="calendar" tone="orange" sub="Requires leave request API"/><Stat label="Balances" value="—" icon="users" tone="blue" sub="Requires leave ledger API"/><Stat label="Processing Runs" value="—" icon="calClock" tone="violet" sub="Requires processing API"/><Stat label="Encashment Pending" value="—" icon="rupee" tone="green" sub="Requires encashment API"/></KpiGrid><Section title="Leave Requests" sub="Backend leave request workflow required"><EmptyPanel icon="calendar" title="No leave register loaded" text="Leave requests, approval actions, balance reservations and encashments are hidden until Laravel leave APIs are available."/></Section></div>;
    const [tab,setTab]=React.useState("Requests");
    const addRequest=()=>update((s,actor)=>{const emp=s.employees.find(e=>e.name===actor)||s.employees[0];s.leaveRequests.unshift({id:uid("LV"),employee:emp.name,type:"Casual Leave",dates:"29–30 Jun",days:2,balance:emp.leaveBalance,status:"Pending Manager",company:emp.companyId});addAudit(s,actor,"Submitted leave request",emp.id,"Create");},"Leave request submitted");
    const runBalances=()=>update((s,actor)=>{if(s.leaveRuns.some(r=>r.period==="June 2026"&&r.status==="Posted"))return;const id=uid("LRUN");s.leaveRuns.unshift({id,period:"June 2026",mode:"Monthly accrual",employees:s.employees.length,credit:s.employees.length,status:"Posted",postedAt:"Just now"});s.leaveLedger.unshift({id,period:"June 2026",type:"Monthly accrual",employees:s.employees.length,credit:s.employees.length,status:"Posted",postedAt:"Just now"});addAudit(s,actor,"Posted leave balance run",id,"Leave");},state.leaveRuns.some(r=>r.period==="June 2026"&&r.status==="Posted")?"June processing was already posted":"Leave balances processed");
    return <div><ViewTitle title="Leave Management" sub="Ledger balances, effective policies, team calendar and configurable approvals." actions={<Button icon="plus" variant="primary" sm onClick={addRequest}>New Leave Request</Button>}/><div className="hrx-settings-nav">{["Requests","Balance Processing","Encashment","Policy Controls"].map(x=><button key={x} className={tab===x?"on":""} onClick={()=>setTab(x)}>{x}</button>)}</div><KpiGrid><Stat label="Pending Requests" value={state.leaveRequests.filter(x=>/Pending/.test(x.status)).length} icon="calendar" tone="orange"/><Stat label="On Leave Today" value="18" icon="users" tone="blue"/><Stat label="Upcoming" value="32" icon="calClock" tone="violet"/><Stat label="Encashment Pending" value={state.encashments.filter(x=>/Pending/.test(x.status)).length} icon="rupee" tone="green"/></KpiGrid>
      {tab==="Requests"&&<Section title="Leave Requests" sub="Balance is reserved on submit and debited on approval"><Table rows={state.leaveRequests} columns={[{label:"Employee",render:r=><Person employee={r.employee}/>},{label:"Type",render:r=><Badge tone="b-blue">{r.type}</Badge>},{label:"Dates",key:"dates"},{label:"Days",key:"days",right:true},{label:"Balance",render:r=><span className="mono">{r.balance} days</span>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>/Pending/.test(r.status)?<button className="hrx-link" onClick={()=>update((s,actor)=>{const x=s.leaveRequests.find(v=>v.id===r.id);x.status="Approved";addAudit(s,actor,"Approved leave",r.id,"Approval");},"Leave approved")}>Approve</button>:<span className="faint">—</span>}]}/></Section>}
      {tab==="Balance Processing"&&<><Section title="Balance Processing" sub="Preview, post and audit idempotent accrual/carry-forward/lapse jobs" action={<Button icon="check" variant="primary" sm onClick={runBalances}>Run June Accrual</Button>}><div className="hrx-checklist">{[["Policy version active",true],["Employee eligibility evaluated",true],["Duplicate run protection",true],["Year-end carry/lapse preview",true]].map(([x,ok])=><div key={x}><Icon name="check" size={16}/><span>{x}</span><Badge tone="b-green">PASS</Badge></div>)}</div></Section><Section title="Processing History" sub="Posted runs cannot be duplicated"><Table rows={[...state.leaveRuns.map(r=>({...r,rowKey:"run-"+r.id})),...state.leaveLedger.map(r=>({...r,rowKey:"ledger-"+r.id}))].map(r=>({...r,id:r.rowKey,displayId:r.id.replace(/^(run|ledger)-/,"")}))} columns={[{label:"Run",key:"displayId"},{label:"Period",key:"period"},{label:"Type",render:r=>r.mode||r.type},{label:"Employees",key:"employees",right:true},{label:"Credits",key:"credit",right:true},{label:"Posted",key:"postedAt"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section></>}
      {tab==="Encashment"&&<Section title="Leave Encashment Register" sub="Eligibility → HR approval → payroll inclusion → settlement"><Table rows={state.encashments} columns={[{label:"Request",key:"id"},{label:"Employee",render:r=><Person employee={r.employee}/>},{label:"Days",key:"days",right:true},{label:"Daily rate",right:true,render:r=>money(r.rate)},{label:"Amount",right:true,render:r=><b>{money(r.amount)}</b>},{label:"Payroll",key:"payroll"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>/Pending/.test(r.status)?<button className="hrx-link" onClick={()=>update((s,actor)=>{const x=s.encashments.find(v=>v.id===r.id);x.status="Approved";x.payroll="June 2026";addAudit(s,actor,"Approved leave encashment",r.id,"Approval");},"Encashment approved for payroll")}>Approve</button>:"—"}]}/></Section>}
      {tab==="Policy Controls"&&<Section title="Configurable Policy Controls" sub="No leave rule is hardcoded"><div className="hrx-settings-grid"><Setting label="Accrual" value="Monthly · annual · joining proration"/><Setting label="Carry / lapse" value="Limits, expiry and year-end job"/><Setting label="Eligibility" value="Probation · notice · grade · location"/><Setting label="Sandwich / clubbing" value="Policy expression and preview"/><Setting label="Encashment" value="Eligibility and payroll component"/><Setting label="Approvals" value="Manager · department · HR conditions"/></div></Section>}</div>;
  }

  function LeaveProcessingRunModal({ options, onClose, onCreated, toast }) {
    const companies=options?.companies||[];
    const [form,setForm]=React.useState({company_id:companies[0]?.id||"",period_year:options?.current_period_year||new Date().getFullYear(),processing_type:"monthly_accrual",is_dry_run:true,note:"Preview from HR Leave workspace."});
    const [busy,setBusy]=React.useState(false),[error,setError]=React.useState("");
    const set=(k,v)=>setForm(c=>({...c,[k]:v}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.leave_processing_runs_store_url){setError("Leave processing preview is not available for this role.");return;}setBusy(true);try{const payload={period_year:Number(form.period_year),processing_type:form.processing_type,is_dry_run:!!form.is_dry_run,note:form.note||undefined};if(form.company_id)payload.company_id=Number(form.company_id);const body=await apiJson(options.leave_processing_runs_store_url,{method:"POST",body:JSON.stringify(payload)});onCreated(body.data);toast&&toast("Leave processing preview created in Laravel workflow.","green");onClose();}catch(err){setError(err.message||"Leave processing run could not be created.");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Preview Leave Processing</h2><p>Creates an auditable dry-run for monthly accrual or year-end carry/lapse before a separate approver posts it.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Processing preview failed</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Company<select value={form.company_id} disabled={busy||!companies.length} onChange={e=>set("company_id",e.target.value)}>{companies.map(c=><option key={c.id} value={c.id}>{c.label}</option>)}</select></label><label>Period year<input required type="number" min="2000" max="2100" value={form.period_year} disabled={busy} onChange={e=>set("period_year",e.target.value)}/></label><label>Processing type<select value={form.processing_type} disabled={busy} onChange={e=>set("processing_type",e.target.value)}>{(options?.processing_types||[]).map(t=><option key={t.value} value={t.value}>{t.label}</option>)}</select></label><label>Mode<div className="hrx-chip-wrap"><span className="hrx-chip"><input type="checkbox" checked={form.is_dry_run} disabled={busy} onChange={e=>set("is_dry_run",e.target.checked)}/> Dry-run preview</span></div></label><label className="wide">Note<input maxLength={1000} value={form.note} disabled={busy} onChange={e=>set("note",e.target.value)} placeholder="Preview monthly accrual"/></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy||!companies.length}><Icon name="check" size={15}/>{busy?"Creating...":"Create Preview"}</button></div></form></div>;
  }

  function LeaveEncashmentModal({ options, onClose, onCreated, toast }) {
    const employees=options?.employees||[];
    const encashable=(options?.leave_types||[]).filter(t=>t.encashment_enabled);
    const defaultEmployee=options?.self_employee?.id||employees[0]?.id||"";
    const defaultType=encashable.find(t=>String(t.company_id)===String((employees.find(e=>String(e.id)===String(defaultEmployee))||{}).company_id))?.id||encashable[0]?.id||"";
    const [form,setForm]=React.useState({employee_id:defaultEmployee,leave_type_id:defaultType,period_year:options?.current_period_year||new Date().getFullYear(),requested_days:1,request_note:"Requested from HR Leave workspace."});
    const [busy,setBusy]=React.useState(false),[error,setError]=React.useState("");
    const set=(k,v)=>setForm(c=>({...c,[k]:v}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.leave_encashments_store_url){setError("Leave encashment submission is not available for this role.");return;}setBusy(true);try{const body=await apiJson(options.leave_encashments_store_url,{method:"POST",body:JSON.stringify({employee_id:Number(form.employee_id),leave_type_id:Number(form.leave_type_id),period_year:Number(form.period_year),requested_days:Number(form.requested_days),request_note:form.request_note||undefined})});onCreated(body.data);toast&&toast("Leave encashment submitted in Laravel workflow.","green");onClose();}catch(err){setError(err.message||"Leave encashment could not be submitted.");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>New Leave Encashment</h2><p>Validates employee scope, encashable leave type, balance, formula and approval workflow through Laravel.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Encashment not submitted</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee<SearchablePeoplePicker items={employees} selected={form.employee_id} mode="single" required disabled={busy||!employees.length} placeholder="Search employee name, code, department..." emptyText="No matching employees" onChange={value=>set("employee_id",value||"")} getId={e=>e.id} getLabel={e=>e.name||e.label||"Employee"} getSubLabel={e=>e.label&&e.name?e.label:[e.employee_code,e.department,e.designation,e.email].filter(Boolean).join(" · ")}/></label><label>Leave type<select required value={form.leave_type_id} disabled={busy||!encashable.length} onChange={e=>set("leave_type_id",e.target.value)}>{encashable.map(t=><option key={t.id} value={t.id}>{t.label}</option>)}</select></label><label>Period year<input required type="number" min="2000" max="2100" value={form.period_year} disabled={busy} onChange={e=>set("period_year",e.target.value)}/></label><label>Requested days<input required type="number" min="0.5" max="365" step="0.5" value={form.requested_days} disabled={busy} onChange={e=>set("requested_days",e.target.value)}/></label><label className="wide">Request note<input maxLength={1000} value={form.request_note} disabled={busy} onChange={e=>set("request_note",e.target.value)} placeholder="Encash earned leave balance"/></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy||!employees.length||!encashable.length}><Icon name="plus" size={15}/>{busy?"Submitting...":"Submit Encashment"}</button></div></form></div>;
  }

  function LeaveViewV2({ state, update, role, toast, leaveOptions }) {
    const [tab,setTab]=React.useState("Requests");
    const [requests,setRequests]=React.useState([]),[balances,setBalances]=React.useState([]),[runs,setRuns]=React.useState([]),[encashments,setEncashments]=React.useState([]);
    const [loading,setLoading]=React.useState(false),[error,setError]=React.useState(""),[creatingRequest,setCreatingRequest]=React.useState(false),[creatingRun,setCreatingRun]=React.useState(false),[creatingEncashment,setCreatingEncashment]=React.useState(false);
    const requestUrl=leaveOptions?.leave_requests_index_url,balanceUrl=leaveOptions?.leave_balances_index_url,runUrl=leaveOptions?.leave_processing_runs_index_url,encashmentUrl=leaveOptions?.leave_encashments_index_url;
    React.useEffect(()=>{const sources=[[requestUrl,setRequests],[balanceUrl,setBalances],[runUrl,setRuns],[encashmentUrl,setEncashments]].filter(([url])=>url);if(!sources.length)return;let alive=true;setLoading(true);setError("");Promise.all(sources.map(([url,setter])=>apiJson(collectionUrl(url,{per_page:50})).then(body=>[setter,body.data||[]]))).then(items=>{if(alive)items.forEach(([setter,rows])=>setter(rows));}).catch(err=>{if(alive){setError(err.message||"Leave registers could not be loaded.");toast&&toast("Leave fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[requestUrl,balanceUrl,runUrl,encashmentUrl,toast]);
    const requestRows=requestUrl?requests:[];
    const balanceRows=balanceUrl?balances:[];
    const runRows=runUrl?runs:[];
    const encashmentRows=encashmentUrl?encashments:[];
    const patch=(setter,row)=>setter(current=>current.map(x=>x.id===row.id?row:x));
    const addRequest=()=>leaveOptions?.can_create_leave_request&&leaveOptions?.leave_request_store_url?setCreatingRequest(true):unavailableAction(update,"Leave request submission","Leave");
    const onLeaveRequestCreated=row=>{setRequests(current=>[row,...current.filter(x=>x.id!==row.id)]);update((s,actor)=>addAudit(s,actor,"Submitted Laravel leave request",row.request_number||row.id,"Leave"),"Leave request submitted to Laravel workflow");};
    const createRun=()=>leaveOptions?.can_create_processing_run&&leaveOptions?.leave_processing_runs_store_url?setCreatingRun(true):unavailableAction(update,"Leave processing preview","Leave");
    const createEncashment=()=>leaveOptions?.can_create_encashment&&leaveOptions?.leave_encashments_store_url?setCreatingEncashment(true):unavailableAction(update,"Leave encashment request","Leave");
    const postRun=async row=>{const tpl=leaveOptions?.leave_processing_run_post_url_template;if(!tpl||!leaveOptions?.can_post_processing_run||row.status!=="preview"){unavailableAction(update,"Leave processing post approval","Leave");return;}try{const body=await apiJson(tpl.replace("__RUN__",row.id),{method:"PATCH",body:JSON.stringify({note:"Posted from HR Leave workspace after preview verification."})});patch(setRuns,body.data);toast&&toast("Leave processing run posted in Laravel workflow.","green");}catch(err){setError(err.message||"Leave processing post failed.");}};
    const approveRequest=async row=>{const tpl=leaveOptions?.leave_request_approve_url_template;if(!tpl||!leaveOptions?.can_approve_leave_request||row.status!=="submitted"){unavailableAction(update,"Leave approval","Leave");return;}try{const body=await apiJson(tpl.replace("__REQUEST__",row.id),{method:"PATCH",body:JSON.stringify({decision_note:"Approved from HR Leave workspace."})});patch(setRequests,body.data);toast&&toast("Leave request approved in Laravel workflow.","green");}catch(err){setError(err.message||"Leave approval failed.");}};
    const rejectRequest=async row=>{const tpl=leaveOptions?.leave_request_reject_url_template;if(!tpl||!leaveOptions?.can_approve_leave_request||row.status!=="submitted"){unavailableAction(update,"Leave rejection","Leave");return;}try{const body=await apiJson(tpl.replace("__REQUEST__",row.id),{method:"PATCH",body:JSON.stringify({decision_note:"Rejected from HR Leave workspace after policy review."})});patch(setRequests,body.data);toast&&toast("Leave request rejected in Laravel workflow.","green");}catch(err){setError(err.message||"Leave rejection failed.");}};
    const approveEncashment=async row=>{const tpl=leaveOptions?.leave_encashment_approve_url_template;if(!tpl||!leaveOptions?.can_approve_encashment||row.status!=="submitted"){unavailableAction(update,"Leave encashment approval","Leave");return;}try{const body=await apiJson(tpl.replace("__ENCASHMENT__",row.id),{method:"PATCH",body:JSON.stringify({approved_days:row.requested_days,decision_note:"Approved from HR Leave workspace."})});patch(setEncashments,body.data);toast&&toast("Leave encashment approved in Laravel workflow.","green");}catch(err){setError(err.message||"Encashment approval failed.");}};
    const rejectEncashment=async row=>{const tpl=leaveOptions?.leave_encashment_reject_url_template;if(!tpl||!leaveOptions?.can_approve_encashment||row.status!=="submitted"){unavailableAction(update,"Leave encashment rejection","Leave");return;}try{const body=await apiJson(tpl.replace("__ENCASHMENT__",row.id),{method:"PATCH",body:JSON.stringify({decision_note:"Rejected from HR Leave workspace after eligibility review."})});patch(setEncashments,body.data);toast&&toast("Leave encashment rejected in Laravel workflow.","green");}catch(err){setError(err.message||"Encashment rejection failed.");}};
    const markPayroll=async row=>{const tpl=leaveOptions?.leave_encashment_mark_payroll_url_template;if(!tpl||!leaveOptions?.can_mark_encashment_payroll||row.status!=="approved"){unavailableAction(update,"Leave encashment payroll marking","Leave");return;}try{const body=await apiJson(tpl.replace("__ENCASHMENT__",row.id),{method:"PATCH",body:JSON.stringify({payroll_reference:`PAY-ENC-${row.encashment_number||row.id}`,note:"Marked from HR Leave workspace."})});patch(setEncashments,body.data);toast&&toast("Leave encashment marked for payroll in Laravel workflow.","green");}catch(err){setError(err.message||"Payroll marking failed.");}};
    return <div><ViewTitle title="Leave Management" sub="MySQL-backed leave requests, balances, idempotent processing runs and encashment workflows." actions={<Button icon="plus" variant="primary" sm onClick={tab==="Balance Processing"?createRun:tab==="Encashment"?createEncashment:addRequest}>{tab==="Balance Processing"?"Preview Processing":tab==="Encashment"?"New Encashment":"New Leave Request"}</Button>}/><div className="hrx-settings-nav">{["Requests","Balances","Balance Processing","Encashment","Policy Controls"].map(x=><button key={x} className={tab===x?"on":""} onClick={()=>setTab(x)}>{x}</button>)}</div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Leave register API issue</b><span>{error}</span></div></div>}<div className="hrx-toolbar"><Badge tone={leaveOptions?.source==="laravel-sqlite"?"b-green":"b-orange"}>{loading?"Loading Laravel leave":leaveOptions?.source==="laravel-sqlite"?"Laravel leave registers":"Leave API required"}</Badge><Badge tone="b-slate">{requestRows.length} requests</Badge><Badge tone="b-slate">{runRows.length} processing runs</Badge></div><KpiGrid><Stat label="Pending Requests" value={requestRows.filter(x=>x.status==="submitted"||/Pending/.test(x.status)).length} icon="calendar" tone="orange"/><Stat label="Balances" value={balanceRows.length} icon="users" tone="blue"/><Stat label="Processing Runs" value={runRows.length} icon="calClock" tone="violet"/><Stat label="Encashment Pending" value={encashmentRows.filter(x=>x.status==="submitted").length} icon="rupee" tone="green"/></KpiGrid>
      {tab==="Requests"&&<Section title="Leave Requests" sub="Role-scoped Laravel requests with balance reservation and approval/rejection state"><Table rows={requestRows} columns={[{label:"Request",render:r=><div><b>{r.request_number||r.id}</b><div className="cell-sub">{r.starts_on} to {r.ends_on}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name||r.employee} sub={r.employee?.department}/>},{label:"Type",render:r=><Badge tone="b-blue">{r.leave_type?.code||r.type}</Badge>},{label:"Days",render:r=>r.requested_days||r.days,right:true},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="submitted"&&leaveOptions?.can_approve_leave_request?<div className="hrx-chip-wrap"><button className="hrx-link" onClick={()=>approveRequest(r)}>Approve</button><button className="hrx-link" onClick={()=>rejectRequest(r)}>Reject</button></div>:<span className="faint">-</span>}]}/></Section>}
      {tab==="Balances"&&<Section title="Leave Balance Ledger" sub="Employee-wise opening, accrual, used, pending, adjusted and available days"><Table rows={balanceRows} columns={[{label:"Employee",render:r=><Person employee={r.employee?.name||r.employee} sub={r.employee?.department}/>},{label:"Type",render:r=><Badge tone="b-blue">{r.leave_type?.code||"Leave"}</Badge>},{label:"Year",key:"period_year"},{label:"Opening",key:"opening_balance_days",right:true},{label:"Accrued",key:"accrued_days",right:true},{label:"Used",key:"used_days",right:true},{label:"Pending",key:"pending_days",right:true},{label:"Available",render:r=><b className="mono">{Number(r.available_days||0).toFixed(2)}</b>,right:true}]}/></Section>}
      {tab==="Balance Processing"&&<><Section title="Balance Processing Controls" sub="Preview first, then a separate approver posts the run" action={<Button icon="check" variant="primary" sm onClick={createRun}>Preview Accrual / Year-End</Button>}><div className="hrx-checklist">{[["Policy version snapshot captured",true],["Employee eligibility evaluated",true],["Duplicate posted-run protection",true],["Creator cannot post own run",true]].map(([x,ok])=><div key={x}><Icon name={ok?"check":"alert"} size={16}/><span>{x}</span><Badge tone={ok?"b-green":"b-orange"}>{ok?"PASS":"REVIEW"}</Badge></div>)}</div></Section><Section title="Processing History" sub="Posted runs cannot be duplicated for the same company, year and type"><Table rows={runRows} columns={[{label:"Run",render:r=><div><b>{r.run_number||r.id}</b><div className="cell-sub">{r.created_by?.name||"Created"} - {r.created_at||""}</div></div>},{label:"Year",key:"period_year"},{label:"Type",key:"processing_type"},{label:"Employees",right:true,render:r=>r.summary?.employee_count||0},{label:"Accrual",right:true,render:r=>Number(r.summary?.total_accrual_days||0).toFixed(2)},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="preview"&&leaveOptions?.can_post_processing_run?<button className="hrx-link" onClick={()=>postRun(r)}>Post</button>:<span className="faint">-</span>}]}/></Section></>}
      {tab==="Encashment"&&<Section title="Leave Encashment Register" sub="Eligibility -> HR approval/rejection -> payroll inclusion -> settlement"><Table rows={encashmentRows} columns={[{label:"Request",render:r=><div><b>{r.encashment_number||r.id}</b><div className="cell-sub">FY {r.period_year}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name||r.employee} sub={r.employee?.department}/>},{label:"Leave",render:r=><Badge tone="b-blue">{r.leave_type?.code||"EL"}</Badge>},{label:"Requested",key:"requested_days",right:true},{label:"Approved",key:"approved_days",right:true},{label:"Net Amount",right:true,render:r=><b>{money(r.net_amount||r.gross_amount||0)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="submitted"&&leaveOptions?.can_approve_encashment?<div className="hrx-chip-wrap"><button className="hrx-link" onClick={()=>approveEncashment(r)}>Approve</button><button className="hrx-link" onClick={()=>rejectEncashment(r)}>Reject</button></div>:r.status==="approved"&&leaveOptions?.can_mark_encashment_payroll?<button className="hrx-link" onClick={()=>markPayroll(r)}>Mark payroll</button>:<span className="faint">-</span>}]}/></Section>}
      {tab==="Policy Controls"&&<Section title="Configurable Policy Controls" sub="No leave rule is hardcoded"><div className="hrx-settings-grid"><Setting label="Accrual" value="Monthly, annual and joining proration from HR settings"/><Setting label="Carry / lapse" value="Limit, expiry and year-end processing rule"/><Setting label="Eligibility" value="Probation, notice, grade, location and leave-type scope"/><Setting label="Encashment formula" value="Configured tax rate, daily rate and payroll component"/><Setting label="Approval segregation" value="Creator cannot post own run; requester cannot approve own encashment"/><Setting label="Audit" value="Workflow history, notifications and audit events from Laravel"/></div></Section>}{creatingRequest&&<LeaveRequestModal options={leaveOptions} onClose={()=>setCreatingRequest(false)} onCreated={onLeaveRequestCreated} toast={toast}/>} {creatingRun&&<LeaveProcessingRunModal options={leaveOptions} onClose={()=>setCreatingRun(false)} onCreated={row=>setRuns(current=>[row,...current])} toast={toast}/>} {creatingEncashment&&<LeaveEncashmentModal options={leaveOptions} onClose={()=>setCreatingEncashment(false)} onCreated={row=>setEncashments(current=>[row,...current])} toast={toast}/>}</div>;
  }

  function PayrollView({ state, update, role }) {
    const [tab,setTab]=React.useState("Run");
    const p=state.payroll; const current=p.stages[p.stage]; const next=p.stages[p.stage+1];
    const unavailable = label => unavailableAction(update,label,"Payroll");
    const advance=()=>unavailable("Payroll stage transition");
    const makeBankBatch=()=>unavailable("Bank transfer batch generation");
    return <div><ViewTitle title="Payroll Management" sub="Payroll API required for calculation, approval, payslip publication and bank-transfer generation." actions={tab==="Run"&&<Button icon="wallet" variant="primary" sm onClick={advance}>Backend payroll API required</Button>}/><div className="hrx-demo-banner"><Icon name="shield" size={17}/><div><b>Payroll fallback is read-only.</b><span>No local payroll calculation, salary register, payslip publication or bank-transfer file is generated without the governed Laravel payroll workflow.</span></div><Badge tone="b-orange">API REQUIRED</Badge></div><div className="hrx-settings-nav">{["Run","Salary Structures","Commissions","Bank Transfers"].map(x=><button key={x} className={tab===x?"on":""} onClick={()=>setTab(x)}>{x}</button>)}</div>
      {tab==="Run"&&<><div className="hrx-payroll-stage">{p.stages.map((s,i)=><div className={i===0?"current":""} key={s}><span>{i+1}</span><b>{s}</b></div>)}</div><KpiGrid><Stat label="Gross Salary" value="—" icon="rupee" tone="accent" sub="Requires payroll run API"/><Stat label="Deductions" value="—" icon="trend" tone="orange" sub="Requires payroll run API"/><Stat label="Net Payout" value="—" icon="wallet" tone="green" sub="Requires approved run"/><Stat label="Employees" value="—" icon="users" tone="blue" sub="Requires payroll items"/><Stat label="Exceptions" value="—" icon="alert" tone="red" sub="Requires payroll controls"/></KpiGrid><div className="hrx-grid-2"><Section title="Calculation Components" sub="Configured components are visible after Laravel payroll APIs load"><div className="hrx-chip-wrap">{p.components.map(c=><span className="hrx-chip" key={c}>{c}</span>)}</div><div className="hrx-trace"><Icon name="funnel"/><div><b>Payroll calculation disabled in fallback mode</b><span>Each production payroll line must come from approved salary structures, attendance, leave, claims, loans and statutory configuration.</span></div></div></Section><Section title="Control Checks" sub="Laravel payroll workflow required"><div className="hrx-checklist">{[["Attendance period locked",false],["Salary structures assigned",false],["Bank details complete",false],["Payroll exceptions resolved",false],["Preparer ≠ approver",false]].map(([x,ok])=><div key={x}><Icon name={ok?"check":"alert"} size={16}/><span>{x}</span><Badge tone={ok?"b-green":"b-orange"}>{ok?"PASS":"API REQUIRED"}</Badge></div>)}</div></Section></div><Section title="Salary Register" sub="No fallback salary rows are displayed; use Laravel payroll runs and employee-scoped permissions"><EmptyPanel icon="wallet" title="Payroll register unavailable" text="Generated payroll rows will appear only after the Laravel payroll run API loads successfully."/></Section></>}
      {tab==="Salary Structures"&&<><Section title="Versioned Salary Structures" sub="Laravel payroll API required for effective-dated structures, component formulas, approval and assignment"><EmptyPanel icon="wallet" title="Salary structure register unavailable" text="Approved salary structures and employee assignments will appear only from the Laravel salary-structure API."/></Section></>}
      {tab==="Commissions"&&<><Section title="Commission Rules" sub="Laravel payroll API required for rule configuration, source imports, approvals and payroll inclusion"><EmptyPanel icon="rupee" title="Commission register unavailable" text="Approved CRM-linked commission rules and runs will appear only from Laravel commission APIs."/></Section></>}
      {tab==="Bank Transfers"&&<><Section title="Bank Transfer File" sub="Laravel payroll API required after Finance Approved payroll"><div className="hrx-checklist">{[["Payroll is Finance Approved",false],["No duplicate employee rows",false],["IFSC and account details validated",false],["Control total matches approved net",false]].map(([x,ok])=><div key={x}><Icon name={ok?"check":"alert"} size={16}/><span>{x}</span><Badge tone={ok?"b-green":"b-orange"}>{ok?"PASS":"API REQUIRED"}</Badge></div>)}</div></Section><Section title="Generated Batches" sub="No fallback bank-transfer files are generated"><EmptyPanel icon="wallet" title="Bank-transfer batch unavailable" text="Prepared and released bank-transfer batches will appear only after the Laravel payroll bank-batch workflow loads successfully."/></Section></>}
    </div>;
  }

  function PayrollViewV2({ state, update, toast, payrollOptions }) {
    const [tab,setTab]=React.useState("Run");
    const [runs,setRuns]=React.useState([]),[structures,setStructures]=React.useState([]),[rules,setRules]=React.useState([]),[commissionRuns,setCommissionRuns]=React.useState([]),[batches,setBatches]=React.useState([]),[taxDocs,setTaxDocs]=React.useState([]);
    const [loading,setLoading]=React.useState(false),[error,setError]=React.useState("");
    const runUrl=payrollOptions?.payroll_runs_index_url,structureUrl=payrollOptions?.salary_structures_index_url,ruleUrl=payrollOptions?.commission_rules_index_url,commissionUrl=payrollOptions?.commission_runs_index_url,batchUrl=payrollOptions?.bank_transfer_batches_index_url,taxUrl=payrollOptions?.tax_documents_index_url;
    React.useEffect(()=>{const sources=[[runUrl,setRuns],[structureUrl,setStructures],[ruleUrl,setRules],[commissionUrl,setCommissionRuns],[batchUrl,setBatches],[taxUrl,setTaxDocs]].filter(([url])=>url);if(!sources.length)return;let alive=true;setLoading(true);setError("");Promise.all(sources.map(([url,setter])=>apiJson(collectionUrl(url,{per_page:50})).then(body=>[setter,body.data||[]]))).then(items=>{if(alive)items.forEach(([setter,rows])=>setter(rows));}).catch(err=>{if(alive){setError(err.message||"Payroll registers could not be loaded.");toast&&toast("Payroll fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[runUrl,structureUrl,ruleUrl,commissionUrl,batchUrl,taxUrl,toast]);
    const runRows=runUrl?runs:[],structureRows=structureUrl?structures:[],commissionRunRows=commissionUrl?commissionRuns:[],batchRows=batchUrl?batches:[],taxRows=taxUrl?taxDocs:[];
    const latest=runRows[0]||{};
    const patchRow=(setter,row)=>setter(current=>current.map(x=>x.id===row.id?row:x));
    const approveRun=async r=>{const tpl=payrollOptions?.payroll_run_approve_url_template;if(!tpl||!payrollOptions?.can_approve_payroll_run||r.status!=="generated"){unavailableAction(update,"Payroll run approval","Payroll");return;}try{const body=await apiJson(tpl.replace("__RUN__",r.id),{method:"PATCH",body:JSON.stringify({note:"Approved from HR Payroll workspace."})});patchRow(setRuns,body.data);toast&&toast("Payroll run approved in Laravel workflow.","green");}catch(err){setError(err.message||"Payroll approval failed.");}};
    const prepareBatch=async r=>{const tpl=payrollOptions?.bank_transfer_batch_prepare_url_template;if(!tpl||!payrollOptions?.can_prepare_bank_transfer_batch||r.status!=="approved"){unavailableAction(update,"Bank batch preparation","Payroll");return;}const defaults=payrollOptions.default_bank_batch||{};try{const body=await apiJson(tpl.replace("__RUN__",r.id),{method:"POST",body:JSON.stringify({bank_name:defaults.bank_name||"HDFC Bank",payment_date:new Date(Date.now()+86400000).toISOString().slice(0,10),debit_account_number:defaults.debit_account_number||"1234567890",narration:defaults.narration||"Builder360 salary transfer"})});setBatches(current=>[body.data,...current]);toast&&toast("Bank transfer batch prepared in Laravel workflow.","green");}catch(err){setError(err.message||"Bank batch preparation failed.");}};
    const releaseBatch=async r=>{const tpl=payrollOptions?.bank_transfer_batch_release_url_template;if(!tpl||!payrollOptions?.can_release_bank_transfer_batch||r.status!=="prepared"){unavailableAction(update,"Bank batch release","Payroll");return;}try{const body=await apiJson(tpl.replace("__BATCH__",r.id),{method:"PATCH",body:JSON.stringify({release_note:"Released from HR Payroll workspace."})});patchRow(setBatches,body.data);toast&&toast("Bank transfer batch released in Laravel workflow.","green");}catch(err){setError(err.message||"Bank batch release failed.");}};
    const approveCommission=async r=>{const tpl=payrollOptions?.commission_run_approve_url_template;if(!tpl||!payrollOptions?.can_approve_commission_run||r.status!=="generated"){unavailableAction(update,"Commission run approval","Payroll");return;}try{const body=await apiJson(tpl.replace("__RUN__",r.id),{method:"PATCH",body:JSON.stringify({decision_note:"Approved from HR Payroll workspace."})});patchRow(setCommissionRuns,body.data);toast&&toast("Commission run approved in Laravel workflow.","green");}catch(err){setError(err.message||"Commission approval failed.");}};
    const issueTax=async r=>{const tpl=payrollOptions?.tax_document_issue_url_template;if(!tpl||!payrollOptions?.can_issue_tax_document||r.status!=="generated"){unavailableAction(update,"Tax document issue","Payroll");return;}try{const body=await apiJson(tpl.replace("__DOCUMENT__",r.id),{method:"PATCH",body:JSON.stringify({issue_reference:`ISSUE-${r.document_number||r.id}`})});patchRow(setTaxDocs,body.data);toast&&toast("Tax document issued in Laravel workflow.","green");}catch(err){setError(err.message||"Tax document issue failed.");}};
    return <div><ViewTitle title="Payroll Management" sub="MySQL-backed payroll runs, salary structures, commissions, Form 16 records and bank-transfer workflow." actions={<Badge tone={payrollOptions?.source==="laravel-sqlite"?"b-green":"b-orange"}>{loading?"Loading Laravel payroll":payrollOptions?.source==="laravel-sqlite"?"Laravel payroll":"Payroll API unavailable"}</Badge>}/>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Payroll register load issue</b><span>{error}</span></div></div>}{!runUrl&&<div className="hrx-demo-banner"><Icon name="shield" size={17}/><div><b>Payroll registers are not available without the Laravel payroll API.</b><span>No fallback payroll run, salary amount, commission, tax document or bank-transfer batch is fabricated in this screen.</span></div><Badge tone="b-orange">API REQUIRED</Badge></div>}<div className="hrx-settings-nav">{["Run","Salary Structures","Commissions","Bank Transfers","Tax Docs"].map(x=><button key={x} className={tab===x?"on":""} onClick={()=>setTab(x)}>{x}</button>)}</div>
      {tab==="Run"&&<><KpiGrid><Stat label="Latest Run" value={latest.run_number||"—"} icon="calendar" tone="accent"/><Stat label="Gross" value={money(latest.gross_earnings||0)} icon="wallet" tone="green"/><Stat label="Deductions" value={money(latest.total_deductions||0)} icon="rupee" tone="orange"/><Stat label="Net Payable" value={money(latest.net_payable||0)} icon="check" tone="violet"/></KpiGrid><Section title="Payroll Runs" sub="Generated payroll rows from Laravel with approval and bank-batch handoff"><Table rows={runRows} columns={[{label:"Run",render:r=><div><b>{r.run_number||r.id}</b><div className="cell-sub">{r.period_month}/{r.period_year} · {r.items?.length||0} rows</div></div>},{label:"Gross",right:true,render:r=>money(r.gross_earnings||0)},{label:"Deductions",right:true,render:r=>money(r.total_deductions||0)},{label:"Net",right:true,render:r=><b>{money(r.net_payable||0)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="generated"&&payrollOptions?.can_approve_payroll_run?<button className="hrx-link" onClick={()=>approveRun(r)}>Approve</button>:r.status==="approved"&&payrollOptions?.can_prepare_bank_transfer_batch?<button className="hrx-link" onClick={()=>prepareBatch(r)}>Prepare bank batch</button>:<span className="faint">—</span>}]}/></Section></>}
      {tab==="Salary Structures"&&<Section title="Versioned Salary Structures" sub="Effective-dated structures and component previews from Laravel"><Table rows={structureRows} columns={[{label:"Structure",render:r=><div><b>{r.name}</b><div className="cell-sub">{r.code||r.id}</div></div>},{label:"Version",render:r=>"v"+r.version},{label:"Effective",render:r=>r.effective_from||"—"},{label:"Monthly CTC",right:true,render:r=>money(r.monthly_ctc||0)},{label:"Components",render:r=>(r.components||[]).slice(0,3).map(c=>c.component_code||c.component_name).join(" · ")||"—"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section>}
      {tab==="Commissions"&&<><Section title="Commission Rules" sub="Configured source, basis, rate and effective dates from Laravel"><Table rows={rules} columns={[{label:"Rule",render:r=><div><b>{r.name}</b><div className="cell-sub">{r.rule_code}</div></div>},{label:"Type",key:"rule_type"},{label:"Basis",key:"basis"},{label:"Rate",right:true,render:r=>`${Number(r.rate_percent||0).toFixed(2)}%`},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section><Section title="Commission Runs" sub="Generated commission inputs linked to payroll inclusion"><Table rows={commissionRunRows} columns={[{label:"Run",render:r=><div><b>{r.run_number||r.id}</b><div className="cell-sub">{r.period_month}/{r.period_year} · {r.rule?.name||"Rule"}</div></div>},{label:"Items",right:true,render:r=>r.item_count||0},{label:"Commission",right:true,render:r=><b>{money(r.commission_total||0)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="generated"&&payrollOptions?.can_approve_commission_run?<button className="hrx-link" onClick={()=>approveCommission(r)}>Approve</button>:<span className="faint">—</span>}]}/></Section></>}
      {tab==="Bank Transfers"&&<Section title="Bank Transfer Batches" sub="Validation, checksum and control totals retained by Laravel"><Table rows={batchRows} columns={[{label:"Batch",render:r=><div><b>{r.batch_number||r.id}</b><div className="cell-sub">{r.bank_name||"Bank"} · {r.payment_date||"—"}</div></div>},{label:"Run",render:r=>r.payroll_run?.run_number||"—"},{label:"Employees",right:true,render:r=>r.item_count||0},{label:"Control total",right:true,render:r=>money(r.control_total||0)},{label:"Checksum",key:"checksum"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="prepared"&&payrollOptions?.can_release_bank_transfer_batch?<button className="hrx-link" onClick={()=>releaseBatch(r)}>Release</button>:<span className="faint">—</span>}]}/></Section>}
      {tab==="Tax Docs"&&<Section title="Form 16 / Tax Documents" sub="Generated, issued and acknowledged employee tax artifacts from Laravel"><Table rows={taxRows} columns={[{label:"Document",render:r=><div><b>{r.document_number||r.id}</b><div className="cell-sub">{r.document_type} · FY {r.financial_year} · v{r.version}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name||r.employee} sub={r.employee?.employee_code}/>},{label:"Gross",right:true,render:r=>money(r.gross_salary||0)},{label:"TDS",right:true,render:r=>money(r.tds_deducted||0)},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="generated"&&payrollOptions?.can_issue_tax_document?<button className="hrx-link" onClick={()=>issueTax(r)}>Issue</button>:<span className="faint">{r.acknowledged_at?"Acknowledged":"—"}</span>}]}/></Section>}
    </div>;
  }

  function RecruitmentRequisitionModal({ options, onClose, onCreated, toast }) {
    const companies=options?.companies||[];
    const departmentOptions=options?.departments||[];
    const employmentTypeOptions=options?.employment_types||[];
    const firstCompany=companies[0]||{};
    const today=new Date().toISOString().slice(0,10);
    const [form,setForm]=React.useState({company_id:firstCompany.id||"",branch_id:"",project_id:"",title:"",department:departmentOptions[0]||"",designation:"",positions:1,employment_type:employmentTypeOptions[0]?.value||"",work_location:"",budget_min_ctc:"",budget_max_ctc:"",target_hiring_date:today,required_skills:"",business_justification:""});
    const [busy,setBusy]=React.useState(false);
    const [error,setError]=React.useState("");
    const branches=(options?.branches||[]).filter(x=>String(x.company_id)===String(form.company_id));
    const projects=(options?.projects||[]).filter(x=>String(x.company_id)===String(form.company_id)&&(!form.branch_id||String(x.branch_id)===String(form.branch_id)));
    const set=(key,value)=>setForm(current=>({ ...current, [key]: value, ...(key==="company_id"?{branch_id:"",project_id:""}:{}), ...(key==="branch_id"?{project_id:""}:{}) }));
    const submit=async ev=>{
      ev.preventDefault();
      setError("");
      if(!options?.job_openings_store_url){setError("Your role is not permitted to create recruitment requisitions.");return;}
      if(!form.company_id||!form.title.trim()||!form.department.trim()||!form.designation.trim()){setError("Company, title, department and designation are required.");return;}
      if(Number(form.positions)<1){setError("Positions must be at least 1.");return;}
      if(form.budget_min_ctc!==""&&form.budget_max_ctc!==""&&Number(form.budget_max_ctc)<Number(form.budget_min_ctc)){setError("Maximum CTC must be greater than or equal to minimum CTC.");return;}
      try{
        setBusy(true);
        const payload={company_id:Number(form.company_id),branch_id:form.branch_id?Number(form.branch_id):null,project_id:form.project_id?Number(form.project_id):null,title:form.title.trim(),department:form.department.trim(),designation:form.designation.trim(),positions:Number(form.positions),employment_type:form.employment_type,work_location:form.work_location.trim()||null,budget_min_ctc:form.budget_min_ctc===""?null:Number(form.budget_min_ctc),budget_max_ctc:form.budget_max_ctc===""?null:Number(form.budget_max_ctc),target_hiring_date:form.target_hiring_date||null,required_skills:form.required_skills.split(",").map(x=>x.trim()).filter(Boolean),business_justification:form.business_justification.trim()||null};
        const body=await apiJson(options.job_openings_store_url,{method:"POST",body:JSON.stringify(payload)});
        toast&&toast("Job requisition submitted to Laravel workflow.","green");
        onCreated&&onCreated(body.data);
        onClose();
      }catch(err){
        setError(err.message||"Job requisition could not be submitted.");
        toast&&toast("Requisition not submitted: "+(err.message||"save failed"),"orange");
      }finally{setBusy(false);}
    };
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>New Job Requisition</h2><p>Creates a Laravel job-opening record with company scope, validation, approval workflow and audit trail.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Requisition not submitted</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Company<select required value={form.company_id} disabled={busy} onChange={e=>set("company_id",e.target.value)}><option value="">Select company</option>{companies.map(c=><option key={c.id} value={c.id}>{c.label||c.name}</option>)}</select></label><label>Branch<select value={form.branch_id} disabled={busy||!branches.length} onChange={e=>set("branch_id",e.target.value)}><option value="">Optional branch</option>{branches.map(b=><option key={b.id} value={b.id}>{b.label||b.name}</option>)}</select></label><label>Project<select value={form.project_id} disabled={busy||!projects.length} onChange={e=>set("project_id",e.target.value)}><option value="">Optional project/site</option>{projects.map(p=><option key={p.id} value={p.id}>{p.label||p.name}</option>)}</select></label><label>Department<select required value={form.department} disabled={busy||!departmentOptions.length} onChange={e=>set("department",e.target.value)}><option value="">Select configured department</option>{departmentOptions.map(x=><option key={x} value={x}>{x}</option>)}</select></label><label>Requisition title<input required maxLength={255} value={form.title} disabled={busy} onChange={e=>set("title",e.target.value)} placeholder="Senior Site Engineer"/></label><label>Designation<input required maxLength={120} value={form.designation} disabled={busy} onChange={e=>set("designation",e.target.value)} placeholder="Site Engineer"/></label><label>Positions<input required type="number" min="1" max="200" value={form.positions} disabled={busy} onChange={e=>set("positions",e.target.value)}/></label><label>Employment type<select required value={form.employment_type} disabled={busy||!employmentTypeOptions.length} onChange={e=>set("employment_type",e.target.value)}><option value="">Select employment type</option>{employmentTypeOptions.map(t=><option key={t.value} value={t.value}>{t.label}</option>)}</select></label><label>Work location<input maxLength={255} value={form.work_location} disabled={busy} onChange={e=>set("work_location",e.target.value)} placeholder="Pune HO / Skyline site"/></label><label>Target hiring date<input type="date" min={today} value={form.target_hiring_date} disabled={busy} onChange={e=>set("target_hiring_date",e.target.value)}/></label><label>Min annual CTC<input type="number" min="0" value={form.budget_min_ctc} disabled={busy} onChange={e=>set("budget_min_ctc",e.target.value)} placeholder="600000"/></label><label>Max annual CTC<input type="number" min="0" value={form.budget_max_ctc} disabled={busy} onChange={e=>set("budget_max_ctc",e.target.value)} placeholder="900000"/></label><label style={{gridColumn:"1 / -1"}}>Required skills<input maxLength={500} value={form.required_skills} disabled={busy} onChange={e=>set("required_skills",e.target.value)} placeholder="AutoCAD, site execution, vendor coordination"/></label><label style={{gridColumn:"1 / -1"}}>Business justification<textarea maxLength={2000} value={form.business_justification} disabled={busy} onChange={e=>set("business_justification",e.target.value)} placeholder="Explain hiring need, replacement/new headcount, project impact and approval context."/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="check" size={15}/>{busy?"Submitting...":"Submit Requisition"}</button></div></form></div>;
  }

  function CandidateCreateModal({ options, openings, onClose, onCreated, toast }) {
    const openChoices=(openings||options?.job_openings||[]).filter(o=>!o.status||o.status==="open");
    const sourceChoices=(options?.candidate_sources||[]).filter(Boolean);
    const first=openChoices[0]||{};
    const [form,setForm]=React.useState({job_opening_id:first.id||"",name:"",email:"",phone:"",source:sourceChoices[0]||"",current_company:"",experience_years:"",current_ctc:"",expected_ctc:"",notice_period_days:"",skills:"",document_type:"Resume",document_name:"",notes:""});
    const [busy,setBusy]=React.useState(false);
    const [error,setError]=React.useState("");
    const set=(key,value)=>setForm(current=>({...current,[key]:value}));
    const submit=async ev=>{
      ev.preventDefault();
      setError("");
      if(!options?.candidates_store_url){setError("Your role is not permitted to create candidates.");return;}
      if(!form.job_opening_id||!form.name.trim()||!form.email.trim()||!form.phone.trim()||!form.source.trim()){setError("Job opening, name, email, phone and source are required.");return;}
      if(form.experience_years===""||Number(form.experience_years)<0){setError("Experience years must be zero or more.");return;}
      const documents=form.document_name.trim()?[{type:form.document_type.trim()||"Resume",name:form.document_name.trim()}]:[];
      try{
        setBusy(true);
        const payload={job_opening_id:Number(form.job_opening_id),name:form.name.trim(),email:form.email.trim(),phone:form.phone.trim(),source:form.source.trim(),current_company:form.current_company.trim()||null,experience_years:Number(form.experience_years),current_ctc:form.current_ctc===""?null:Number(form.current_ctc),expected_ctc:form.expected_ctc===""?null:Number(form.expected_ctc),notice_period_days:form.notice_period_days===""?null:Number(form.notice_period_days),skills:form.skills.split(",").map(x=>x.trim()).filter(Boolean),documents,notes:form.notes.trim()||null};
        const body=await apiJson(options.candidates_store_url,{method:"POST",body:JSON.stringify(payload)});
        toast&&toast("Candidate created in Laravel workflow.","green");
        onCreated&&onCreated(body.data);
        onClose();
      }catch(err){
        setError(err.message||"Candidate could not be created.");
        toast&&toast("Candidate not created: "+(err.message||"save failed"),"orange");
      }finally{setBusy(false);}
    };
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>New Candidate</h2><p>Creates a Laravel candidate linked to an approved/open job requisition with duplicate and company-scope validation.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Candidate not created</b><span>{error}</span></div></div>}{!openChoices.length&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>No open requisition</b><span>Create and approve a job requisition before adding candidates.</span></div></div>}{!sourceChoices.length&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>No candidate sources configured</b><span>Configure recruitment source values in Laravel settings or seed data before adding candidates.</span></div></div>}<div className="hrx-form-grid"><label>Open job requisition<select required value={form.job_opening_id} disabled={busy||!openChoices.length} onChange={e=>set("job_opening_id",e.target.value)}><option value="">Select open requisition</option>{openChoices.map(o=><option key={o.id} value={o.id}>{o.opening_code||"OPEN"} - {o.title} ({o.department})</option>)}</select></label><label>Source<select required value={form.source} disabled={busy||!sourceChoices.length} onChange={e=>set("source",e.target.value)}><option value="">Select configured source</option>{sourceChoices.map(x=><option key={x} value={x}>{x}</option>)}</select></label><label>Candidate name<input required maxLength={255} value={form.name} disabled={busy} onChange={e=>set("name",e.target.value)} placeholder="Candidate full name"/></label><label>Email<input required type="email" maxLength={255} value={form.email} disabled={busy} onChange={e=>set("email",e.target.value)} placeholder="candidate@example.com"/></label><label>Phone<input required maxLength={30} value={form.phone} disabled={busy} onChange={e=>set("phone",e.target.value)} placeholder="+91 98765 43210"/></label><label>Experience years<input required type="number" min="0" max="60" step="0.5" value={form.experience_years} disabled={busy} onChange={e=>set("experience_years",e.target.value)} placeholder="5"/></label><label>Current company<input maxLength={255} value={form.current_company} disabled={busy} onChange={e=>set("current_company",e.target.value)} placeholder="Current employer"/></label><label>Notice period days<input type="number" min="0" max="365" value={form.notice_period_days} disabled={busy} onChange={e=>set("notice_period_days",e.target.value)} placeholder="30"/></label><label>Current CTC<input type="number" min="0" value={form.current_ctc} disabled={busy} onChange={e=>set("current_ctc",e.target.value)} placeholder="600000"/></label><label>Expected CTC<input type="number" min="0" value={form.expected_ctc} disabled={busy} onChange={e=>set("expected_ctc",e.target.value)} placeholder="800000"/></label><label style={{gridColumn:"1 / -1"}}>Skills<input maxLength={500} value={form.skills} disabled={busy} onChange={e=>set("skills",e.target.value)} placeholder="Sales, site visits, CRM follow-up"/></label><label>Document type<input maxLength={80} value={form.document_type} disabled={busy} onChange={e=>set("document_type",e.target.value)} placeholder="Resume"/></label><label>Document name<input maxLength={255} value={form.document_name} disabled={busy} onChange={e=>set("document_name",e.target.value)} placeholder="resume.pdf / portfolio.pdf"/></label><label style={{gridColumn:"1 / -1"}}>Recruiter notes<textarea maxLength={5000} value={form.notes} disabled={busy} onChange={e=>set("notes",e.target.value)} placeholder="Screening notes, fitment, availability and next action."/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy||!openChoices.length||!sourceChoices.length}><Icon name="check" size={15}/>{busy?"Creating...":"Create Candidate"}</button></div></form></div>;
  }

  function InterviewScheduleModal({ options, candidates, onClose, onCreated, toast }) {
    const candidateChoices=(candidates||[]).filter(c=>c.candidate_code);
    const panelUsers=options?.panel_users||[];
    const tomorrow=new Date(); tomorrow.setDate(tomorrow.getDate()+1); tomorrow.setHours(11,0,0,0);
    const localValue=d=>new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,16);
    const [form,setForm]=React.useState({candidate_id:candidateChoices[0]?.id||"",round_name:"HR Screening",scheduled_at:localValue(tomorrow),duration_minutes:45,mode:"video",venue_or_link:"",panel_user_ids:panelUsers[0]?.id?[String(panelUsers[0].id)]:[]});
    const [busy,setBusy]=React.useState(false);
    const [error,setError]=React.useState("");
    const set=(key,value)=>setForm(current=>({...current,[key]:value}));
    const togglePanel=id=>setForm(current=>{const value=String(id);const currentIds=current.panel_user_ids||[];return {...current,panel_user_ids:currentIds.includes(value)?currentIds.filter(x=>x!==value):[...currentIds,value]};});
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.interviews_store_url){setError("Your role is not permitted to schedule interviews.");return;}if(!form.candidate_id||!form.round_name.trim()||!form.scheduled_at||!form.panel_user_ids.length){setError("Candidate, round, schedule time and at least one panel member are required.");return;}try{setBusy(true);const body=await apiJson(options.interviews_store_url,{method:"POST",body:JSON.stringify({candidate_id:Number(form.candidate_id),round_name:form.round_name.trim(),scheduled_at:form.scheduled_at,duration_minutes:Number(form.duration_minutes),mode:form.mode,venue_or_link:form.venue_or_link.trim()||null,panel_user_ids:form.panel_user_ids.map(Number)})});toast&&toast("Interview scheduled in Laravel workflow with conflict validation.","green");onCreated&&onCreated(body.data);onClose();}catch(err){setError(err.message||"Interview could not be scheduled.");toast&&toast("Interview not scheduled: "+(err.message||"save failed"),"orange");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Schedule Interview</h2><p>Creates a Laravel interview with candidate scope, panel company validation and conflict checks.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Interview not scheduled</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Candidate<select required value={form.candidate_id} disabled={busy||!candidateChoices.length} onChange={e=>set("candidate_id",e.target.value)}><option value="">Select candidate</option>{candidateChoices.map(c=><option key={c.id} value={c.id}>{c.candidate_code} · {c.name}</option>)}</select></label><label>Round<input required maxLength={120} value={form.round_name} disabled={busy} onChange={e=>set("round_name",e.target.value)}/></label><label>Scheduled at<input required type="datetime-local" value={form.scheduled_at} disabled={busy} onChange={e=>set("scheduled_at",e.target.value)}/></label><label>Duration minutes<input required type="number" min="15" max="480" value={form.duration_minutes} disabled={busy} onChange={e=>set("duration_minutes",e.target.value)}/></label><label>Mode<select required value={form.mode} disabled={busy} onChange={e=>set("mode",e.target.value)}><option value="phone">Phone</option><option value="video">Video</option><option value="in_person">In person</option></select></label><label>Venue / link<input maxLength={500} value={form.venue_or_link} disabled={busy} onChange={e=>set("venue_or_link",e.target.value)} placeholder="Teams link / office room"/></label><label style={{gridColumn:"1 / -1"}}>Panel members<div className="hrx-chip-wrap">{panelUsers.map(u=><button type="button" key={u.id} className="hrx-chip" disabled={busy} onClick={()=>togglePanel(u.id)}>{form.panel_user_ids.includes(String(u.id))?"✓ ":""}{u.name}</button>)}</div></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="calendar" size={15}/>{busy?"Scheduling...":"Schedule Interview"}</button></div></form></div>;
  }

  function InterviewFeedbackModal({ options, interview, onClose, onSubmitted, toast }) {
    const [form,setForm]=React.useState({rating:"4",recommendation:"selected",strengths:"",concerns:"",feedback_note:"",next_action:""});
    const [busy,setBusy]=React.useState(false);
    const [error,setError]=React.useState("");
    const set=(key,value)=>setForm(current=>({...current,[key]:value}));
    const submit=async ev=>{ev.preventDefault();setError("");const template=options?.interviews_feedback_url_template;if(!template||!interview?.record_id){setError("Interview feedback endpoint is not available for this role.");return;}if(!form.rating||!form.recommendation){setError("Rating and recommendation are required.");return;}try{setBusy(true);const body=await apiJson(template.replace("__INTERVIEW__",interview.record_id),{method:"PATCH",body:JSON.stringify({rating:Number(form.rating),recommendation:form.recommendation,strengths:form.strengths.trim()||null,concerns:form.concerns.trim()||null,feedback_note:form.feedback_note.trim()||null,next_action:form.next_action.trim()||null})});toast&&toast("Interview feedback submitted in Laravel workflow.","green");onSubmitted&&onSubmitted(body.data);onClose();}catch(err){setError(err.message||"Interview feedback could not be submitted.");toast&&toast("Interview feedback not submitted: "+(err.message||"save failed"),"orange");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Submit Interview Feedback</h2><p>Panel-only Laravel feedback with duplicate prevention, rating summary and candidate stage update after all panel members submit.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Feedback not submitted</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Interview<input value={interview?.id||""} disabled readOnly/></label><label>Candidate<input value={interview?.candidate||""} disabled readOnly/></label><label>Rating<select required value={form.rating} disabled={busy} onChange={e=>set("rating",e.target.value)}><option value="5">5 - Excellent</option><option value="4">4 - Strong</option><option value="3">3 - Meets expectations</option><option value="2">2 - Weak</option><option value="1">1 - Not suitable</option></select></label><label>Recommendation<select required value={form.recommendation} disabled={busy} onChange={e=>set("recommendation",e.target.value)}><option value="selected">Selected</option><option value="second_round">Second round</option><option value="hold">Hold</option><option value="rejected">Rejected</option></select></label><label style={{gridColumn:"1 / -1"}}>Strengths<textarea maxLength={2000} value={form.strengths} disabled={busy} onChange={e=>set("strengths",e.target.value)} placeholder="Relevant strengths, experience, communication, domain fit."/></label><label style={{gridColumn:"1 / -1"}}>Concerns<textarea maxLength={2000} value={form.concerns} disabled={busy} onChange={e=>set("concerns",e.target.value)} placeholder="Gaps, risks, salary concerns or availability issues."/></label><label style={{gridColumn:"1 / -1"}}>Feedback note<textarea maxLength={3000} value={form.feedback_note} disabled={busy} onChange={e=>set("feedback_note",e.target.value)} placeholder="Detailed panel feedback for the recruitment record."/></label><label style={{gridColumn:"1 / -1"}}>Next action<input maxLength={1000} value={form.next_action} disabled={busy} onChange={e=>set("next_action",e.target.value)} placeholder="Schedule second round / release offer / keep on hold."/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="check" size={15}/>{busy?"Submitting...":"Submit Feedback"}</button></div></form></div>;
  }

  function OfferCreateModal({ options, candidates, onClose, onCreated, toast }) {
    const templates=options?.offer_templates||[{value:"offer_letter_v4",label:"Offer Letter v4"}];
    const eligible=(candidates||[]).filter(c=>c.candidate_code&&["interview_scheduled","interviewed","selected","offer_draft"].includes(c.stage));
    const tomorrow=new Date(); tomorrow.setDate(tomorrow.getDate()+1);
    const minDate=tomorrow.toISOString().slice(0,10);
    const first=eligible[0]||{};
    const [form,setForm]=React.useState({candidate_id:first.id||"",template_code:templates[0]?.value||"offer_letter_v4",offered_ctc:"",joining_date:minDate,designation:first.job_opening?.title||first.job_opening?.department||"",department:first.job_opening?.department||""});
    const [busy,setBusy]=React.useState(false);
    const [error,setError]=React.useState("");
    const selected=eligible.find(c=>String(c.id)===String(form.candidate_id));
    const set=(key,value)=>setForm(current=>({...current,[key]:value}));
    React.useEffect(()=>{if(!selected)return;setForm(current=>({...current,designation:current.designation||selected.job_opening?.title||selected.job_opening?.department||"",department:current.department||selected.job_opening?.department||""}));},[selected?.id]);
    const submit=async ev=>{
      ev.preventDefault();
      setError("");
      if(!options?.offers_store_url){setError("Your role is not permitted to create offer drafts.");return;}
      if(!selected){setError("Select an interview-scheduled or selected candidate.");return;}
      if(!form.template_code||!form.designation.trim()||!form.department.trim()||!form.joining_date){setError("Template, designation, department and joining date are required.");return;}
      if(Number(form.offered_ctc)<=0){setError("Offered CTC must be greater than zero.");return;}
      try{
        setBusy(true);
        const payload={candidate_id:Number(form.candidate_id),template_code:form.template_code,offered_ctc:Number(form.offered_ctc),joining_date:form.joining_date,placeholders:{candidate_name:selected.name,designation:form.designation.trim(),department:form.department.trim(),joining_date:form.joining_date,offered_ctc:Number(form.offered_ctc)}};
        const body=await apiJson(options.offers_store_url,{method:"POST",body:JSON.stringify(payload)});
        toast&&toast("Offer draft created in Laravel workflow.","green");
        onCreated&&onCreated(body.data);
        onClose();
      }catch(err){
        setError(err.message||"Offer draft could not be created.");
        toast&&toast("Offer not generated: "+(err.message||"save failed"),"orange");
      }finally{setBusy(false);}
    };
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Generate Offer</h2><p>Creates a Laravel offer draft with mandatory placeholder validation, version history, audit trail and release workflow.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Offer not generated</b><span>{error}</span></div></div>}{!eligible.length&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>No eligible candidates</b><span>Load or advance a candidate to interview scheduled, interviewed, selected or offer draft stage before generating an offer.</span></div></div>}<div className="hrx-form-grid"><label>Candidate<select required value={form.candidate_id} disabled={busy||!eligible.length} onChange={e=>set("candidate_id",e.target.value)}><option value="">Select candidate</option>{eligible.map(c=><option key={c.id} value={c.id}>{c.candidate_code} · {c.name}</option>)}</select></label><label>Template<select required value={form.template_code} disabled={busy} onChange={e=>set("template_code",e.target.value)}>{templates.map(t=><option key={t.value} value={t.value}>{t.label}</option>)}</select></label><label>Designation<input required maxLength={255} value={form.designation} disabled={busy} onChange={e=>set("designation",e.target.value)} placeholder="Sales Manager"/></label><label>Department<input required maxLength={255} value={form.department} disabled={busy} onChange={e=>set("department",e.target.value)} placeholder="Sales"/></label><label>Offered annual CTC<input required type="number" min="1" value={form.offered_ctc} disabled={busy} onChange={e=>set("offered_ctc",e.target.value)} placeholder="780000"/></label><label>Joining date<input required type="date" min={minDate} value={form.joining_date} disabled={busy} onChange={e=>set("joining_date",e.target.value)}/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy||!eligible.length}><Icon name="doc" size={15}/>{busy?"Generating...":"Generate Offer Draft"}</button></div></form></div>;
  }

  function RecruitmentView({ state, update, toast, recruitmentOptions }) {
    const [tab,setTab]=React.useState("Pipeline"); const [query,setQuery]=React.useState("");
    const [candidateSource,setCandidateSource]=React.useState("");
    const [candidateStage,setCandidateStage]=React.useState("");
    const [creating,setCreating]=React.useState(false);
    const [creatingCandidate,setCreatingCandidate]=React.useState(false);
    const [schedulingInterview,setSchedulingInterview]=React.useState(false);
    const [feedbackInterview,setFeedbackInterview]=React.useState(null);
    const [creatingOffer,setCreatingOffer]=React.useState(false);
    const [openings,setOpenings]=React.useState(recruitmentOptions?.job_openings||[]);
    const [loadingOpenings,setLoadingOpenings]=React.useState(false);
    const [openingError,setOpeningError]=React.useState("");
    const openingUrl=recruitmentOptions?.job_openings_index_url;
    const candidateUrl=recruitmentOptions?.candidates_index_url;
    const candidateStoreUrl=recruitmentOptions?.candidates_store_url;
    const interviewUrl=recruitmentOptions?.interviews_index_url;
    const interviewFeedbackTemplate=recruitmentOptions?.interviews_feedback_url_template;
    const offerUrl=recruitmentOptions?.offers_index_url;
    const [serverCandidates,setServerCandidates]=React.useState([]);
    const [serverInterviews,setServerInterviews]=React.useState([]);
    const [serverOffers,setServerOffers]=React.useState([]);
    const [loadingCandidates,setLoadingCandidates]=React.useState(false);
    const [loadingInterviews,setLoadingInterviews]=React.useState(false);
    const [loadingOffers,setLoadingOffers]=React.useState(false);
    const stages=["Applied","Screening","Interview Scheduled","Selected","Offer Released","Joined"];
    const candidateSourceChoices=(recruitmentOptions?.candidate_sources||[]).filter(Boolean);
    const candidateStageChoices=recruitmentOptions?.candidate_stages||[];
    const currentUser=recruitmentOptions?.current_user||window.Builder360Server?.user||{};
    const sameUser=(a,b)=>String(a||"").toLowerCase()===String(b||"").toLowerCase();
    const panelIncludesCurrentUser=i=>(i.panel||[]).some(p=>Number(p.id)===Number(currentUser.id)||sameUser(p.email,currentUser.email));
    const currentUserSubmittedFeedback=i=>((i.feedback?.entries)||[]).some(f=>Number(f.user_id)===Number(currentUser.id)||sameUser(f.reviewer_email,currentUser.email));
    const feedbackProgress=i=>{const summary=i.feedback?.summary||{};const submitted=Number(summary.submitted_count??((i.feedback?.entries)||[]).length);const panel=Number(summary.panel_count??((i.panel||[]).length));return panel?`${submitted}/${panel} submitted`:"No panel";};
    const candidateRows=(candidateUrl?serverCandidates:[]).map(c=>c.candidate_code?{id:c.candidate_code,record_id:c.id,name:c.name,role:c.job_opening?.title||c.job_opening?.department||"Open role",source:c.source,stage:String(c.stage||"").replace(/_/g," ").replace(/\b\w/g,m=>m.toUpperCase()),rawStage:c.stage,experience:c.experience_years?`${Number(c.experience_years).toFixed(1)} years`:"Not provided",skills:Array.isArray(c.skills)?c.skills.join(", "):c.skills,rating:c.status||"active",offerStatus:c.offer?.status||null,employeeCode:c.employee?.employee_code||null,canAdvance:c.can_transition_stage===true,canConvert:c.can_convert_to_employee===true}:c);
    const interviewRows=(interviewUrl?serverInterviews:[]).map(i=>i.interview_code?{id:i.interview_code,record_id:i.id,candidate:i.candidate?.name||"Candidate",when:i.scheduled_at?new Date(i.scheduled_at).toLocaleString([], {year:"numeric",month:"short",day:"2-digit",hour:"2-digit",minute:"2-digit"}):"Not scheduled",panel:(i.panel||[]).map(p=>p.name).join(", "),mode:String(i.mode||"").replace("_"," "),venue:i.venue_or_link||"—",status:i.status,feedback:feedbackProgress(i),feedbackAverage:i.feedback?.summary?.average_rating,canFeedback:Boolean(interviewFeedbackTemplate)&&["scheduled","rescheduled","completed"].includes(String(i.status||""))&&panelIncludesCurrentUser(i)&&!currentUserSubmittedFeedback(i),raw:i}:i);
    const offerRows=(offerUrl?serverOffers:[]).map(o=>o.offer_number?{id:o.offer_number,record_id:o.id,candidate:o.candidate?.name||"Candidate",template:String(o.template_code||"").replace(/_/g," "),version:(o.document_history||[]).length||1,ctc:o.offered_ctc,status:o.status,joining:o.joining_date||"Not set",canRelease:o.permissions?.release===true}:o);
    const filtered=candidateRows.filter(c=>`${c.name} ${c.role} ${c.source}`.toLowerCase().includes(query.toLowerCase()));
    const pipelineColumns=["Screening","Interview Scheduled","Interviewed","Selected","Offer Draft","Offer Released"];
    const pipelineRows=candidateUrl?candidateRows:[];
    const pipelineStage=(row)=>row.stage||String(row.rawStage||"").replace(/_/g," ").replace(/\b\w/g,m=>m.toUpperCase());
    React.useEffect(()=>{if(!openingUrl)return;let alive=true;setLoadingOpenings(true);setOpeningError("");apiJson(collectionUrl(openingUrl,{per_page:30})).then(body=>{if(alive)setOpenings(body.data||[]);}).catch(err=>{if(alive){setOpeningError(err.message||"Unable to load job requisitions.");toast&&toast("Recruitment requisition fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoadingOpenings(false);});return()=>{alive=false;};},[openingUrl,toast]);
    React.useEffect(()=>{if(!candidateUrl)return;let alive=true;setLoadingCandidates(true);setOpeningError("");apiJson(collectionUrl(candidateUrl,{per_page:50,search:query.trim(),source:candidateSource,stage:candidateStage})).then(body=>{if(alive)setServerCandidates(body.data||[]);}).catch(err=>{if(alive){setOpeningError(err.message||"Unable to load candidates.");toast&&toast("Candidate master API issue: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoadingCandidates(false);});return()=>{alive=false;};},[candidateUrl,query,candidateSource,candidateStage,toast]);
    React.useEffect(()=>{if(!interviewUrl)return;let alive=true;setLoadingInterviews(true);setOpeningError("");apiJson(collectionUrl(interviewUrl,{per_page:50})).then(body=>{if(alive)setServerInterviews(body.data||[]);}).catch(err=>{if(alive){setOpeningError(err.message||"Unable to load interviews.");toast&&toast("Interview scheduler API issue: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoadingInterviews(false);});return()=>{alive=false;};},[interviewUrl,toast]);
    React.useEffect(()=>{if(!offerUrl)return;let alive=true;setLoadingOffers(true);setOpeningError("");apiJson(collectionUrl(offerUrl,{per_page:50})).then(body=>{if(alive)setServerOffers(body.data||[]);}).catch(err=>{if(alive){setOpeningError(err.message||"Unable to load offers.");toast&&toast("Offer workspace API issue: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoadingOffers(false);});return()=>{alive=false;};},[offerUrl,toast]);
    const recruitmentSummary=recruitmentOptions?.summary||{};
    const openPositions=openingUrl?(recruitmentSummary.open_positions??openings.filter(x=>["pending_approval","open"].includes(x.status)).reduce((n,x)=>n+Number(x.positions||0),0)):9;
    const openCreate=()=>recruitmentOptions?.can_create_job_opening&&recruitmentOptions?.job_openings_store_url?setCreating(true):unavailableAction(update,"Recruitment requisition creation","Recruitment");
    const onCreated=row=>{setOpenings(current=>[row,...current.filter(x=>x.id!==row.id)]);update((s,actor)=>addAudit(s,actor,"Submitted Laravel job requisition",row.opening_code||row.title,"Recruitment"),"Recruitment requisition submitted");};
    const openCandidateCreate=()=>recruitmentOptions?.can_create_candidate&&candidateStoreUrl?setCreatingCandidate(true):unavailableAction(update,"Candidate creation","Recruitment");
    const onCandidateCreated=row=>{setServerCandidates(current=>[row,...current.filter(x=>x.id!==row.id)]);update((s,actor)=>addAudit(s,actor,"Created Laravel recruitment candidate",row.candidate_code||row.name,"Recruitment"),"Candidate created");};
    const reviewOpening=async(row,action)=>{const template=action==="approve"?recruitmentOptions?.job_openings_approve_url_template:recruitmentOptions?.job_openings_reject_url_template;if(!template||!recruitmentOptions?.can_approve_job_openings){unavailableAction(update,`Job requisition ${action}`,"Recruitment");return;}try{const body=await apiJson(template.replace("__OPENING__",row.id),{method:"PATCH",body:JSON.stringify({review_note:`${action==="approve"?"Approved":"Rejected"} from HR Recruitment workspace`})});setOpenings(current=>current.map(x=>x.id===body.data.id?body.data:x));update((s,actor)=>addAudit(s,actor,`${action==="approve"?"Approved":"Rejected"} Laravel job requisition`,body.data.opening_code||body.data.title,"Recruitment"),`Job requisition ${action==="approve"?"approved":"rejected"}`);toast&&toast(`Job requisition ${action==="approve"?"approved":"rejected"} in Laravel workflow.`,"green");}catch(err){setOpeningError(err.message||`Job requisition ${action} failed.`);toast&&toast(`Requisition ${action} failed: `+(err.message||"review failed"),"orange");}};
    const schedule=()=>recruitmentOptions?.can_schedule_interview&&recruitmentOptions?.interviews_store_url?setSchedulingInterview(true):unavailableAction(update,"Interview scheduling","Recruitment");
    const onInterviewCreated=row=>{setServerInterviews(current=>[row,...current.filter(x=>x.id!==row.id)]);update((s,actor)=>addAudit(s,actor,"Scheduled Laravel interview",row.interview_code||row.round_name,"Recruitment"),"Interview scheduled");};
    const submitInterviewFeedback=row=>row.canFeedback?setFeedbackInterview(row):unavailableAction(update,"Interview feedback","Recruitment");
    const onInterviewFeedbackSubmitted=row=>{setServerInterviews(current=>current.map(x=>x.id===row.id?row:x));setServerCandidates(current=>current.map(x=>x.id===row.candidate?.id?{...x,stage:row.candidate?.stage||x.stage}:x));update((s,actor)=>addAudit(s,actor,"Submitted Laravel interview feedback",row.interview_code||row.id,"Recruitment"),"Interview feedback submitted");};
    const createOffer=()=>recruitmentOptions?.can_create_offer&&recruitmentOptions?.offers_store_url?setCreatingOffer(true):unavailableAction(update,"Offer generation","Recruitment");
    const onOfferCreated=row=>{setServerOffers(current=>[row,...current.filter(x=>x.id!==row.id)]);update((s,actor)=>addAudit(s,actor,"Created Laravel offer draft",row.offer_number||row.id,"Recruitment"),"Offer draft created");};
    const releaseOffer=async(row)=>{const template=recruitmentOptions?.offers_release_url_template;if(!template||!row.canRelease){unavailableAction(update,"Offer release","Recruitment");return;}try{const body=await apiJson(template.replace("__OFFER__",row.record_id),{method:"PATCH",body:JSON.stringify({release_note:"Released from HR Recruitment workspace"})});setServerOffers(current=>current.map(x=>x.id===body.data.id?body.data:x));update((s,actor)=>addAudit(s,actor,"Released Laravel offer",body.data.offer_number||body.data.id,"Recruitment"),"Offer released");toast&&toast("Offer released in Laravel workflow.","green");}catch(err){setOpeningError(err.message||"Offer release failed.");toast&&toast("Offer release failed: "+(err.message||"release failed"),"orange");}};
    const convertCandidate=async(row)=>{const template=recruitmentOptions?.candidates_convert_url_template;if(!template||!row.canConvert){unavailableAction(update,"Candidate-to-employee conversion","Recruitment");return;}try{const body=await apiJson(template.replace("__CANDIDATE__",row.record_id),{method:"POST",body:JSON.stringify({acceptance_note:"Converted from HR Recruitment workspace after released offer acceptance."})});setServerCandidates(current=>current.map(x=>x.id===body.data.id?body.data:x));if(body.data.offer)setServerOffers(current=>current.map(x=>x.id===body.data.offer.id?body.data.offer:x));update((s,actor)=>addAudit(s,actor,"Converted Laravel candidate to employee",body.data.candidate_code||body.data.id,"Recruitment"),"Candidate converted to employee");toast&&toast("Candidate converted to employee in Laravel workflow.","green");}catch(err){setOpeningError(err.message||"Candidate conversion failed.");toast&&toast("Candidate conversion failed: "+(err.message||"conversion failed"),"orange");}};
    const advanceCandidateStage=async(row)=>{const template=recruitmentOptions?.candidates_stage_url_template;if(!template||!row.canAdvance){unavailableAction(update,"Candidate stage transition","Recruitment");return;}try{const body=await apiJson(template.replace("__CANDIDATE__",row.record_id),{method:"PATCH",body:JSON.stringify({stage:"selected",transition_note:"Selected from HR Recruitment pipeline Kanban."})});setServerCandidates(current=>current.map(x=>x.id===body.data.id?body.data:x));update((s,actor)=>addAudit(s,actor,"Updated Laravel candidate stage",body.data.candidate_code||body.data.id,"Recruitment"),"Candidate stage updated");toast&&toast("Candidate stage updated in Laravel workflow.","green");}catch(err){setOpeningError(err.message||"Candidate stage update failed.");toast&&toast("Candidate stage update failed: "+(err.message||"stage update failed"),"orange");}};
    return <div><ViewTitle title="Recruitment & Hiring" sub="Requisition-to-employee pipeline integrated with calendar, documents and onboarding tasks." actions={<Button icon="plus" variant="primary" sm onClick={openCreate}>New Requisition</Button>}/>{openingError&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Recruitment API issue</b><span>{openingError}</span></div></div>}<div className="hrx-settings-nav">{["Pipeline","Candidates","Interviews","Offers"].map(x=><button key={x} className={tab===x?"on":""} onClick={()=>setTab(x)}>{x}</button>)}</div><div className="hrx-toolbar"><Badge tone={openingUrl?"b-green":"b-orange"}>{loadingOpenings?"Loading Laravel requisitions":openingUrl?"Laravel job requisitions":"Recruitment API required"}</Badge><Badge tone={recruitmentOptions?.can_create_job_opening?"b-blue":"b-slate"}>{recruitmentOptions?.can_create_job_opening?"Create allowed":"Create restricted"}</Badge><Badge tone={recruitmentOptions?.can_approve_job_openings?"b-violet":"b-slate"}>{recruitmentOptions?.can_approve_job_openings?"Approval role":"Approval restricted"}</Badge></div><KpiGrid><Stat label="Open Positions" value={openPositions} icon="users" tone="accent"/><Stat label="Candidates" value={openingUrl?(recruitmentSummary.candidates??candidateRows.length):0} icon="funnel" tone="blue"/><Stat label="Interviews" value={openingUrl?(recruitmentSummary.interviews??interviewRows.length):0} icon="calendar" tone="orange"/><Stat label="Offers Pending" value={openingUrl?(recruitmentSummary.offers_pending??offerRows.filter(x=>/Pending/.test(x.status)).length):0} icon="doc" tone="violet"/></KpiGrid>{creating&&<RecruitmentRequisitionModal options={recruitmentOptions} onClose={()=>setCreating(false)} onCreated={onCreated} toast={toast}/>}
      {tab==="Pipeline"&&<div className="hrx-kanban">{pipelineColumns.map(stage=><div className="hrx-kanban-col" key={stage}><div className="row between"><b>{stage}</b><Badge tone="b-slate">{pipelineRows.filter(c=>pipelineStage(c)===stage).length}</Badge></div>{pipelineRows.filter(c=>pipelineStage(c)===stage).map(c=><div className="hrx-kanban-card" key={c.id}><Person employee={c.name} sub={c.role}/><div className="hrx-meta-row"><span>{c.source}</span><span>{candidateUrl?c.rating:"★ "+c.rating}</span></div>{candidateUrl?c.canAdvance&&<button className="hrx-link" onClick={()=>advanceCandidateStage(c)}>Mark selected →</button>:stage!=="Offer Released"&&<button className="hrx-link" onClick={()=>update((s,actor)=>{const x=s.candidates.find(v=>v.id===c.id);const i=stages.indexOf(x.stage);x.stage=stages[Math.min(i+1,stages.length-1)];addAudit(s,actor,"Advanced candidate stage",c.id,"Recruitment");},"Candidate moved to next stage")}>Move forward →</button>}</div>)}</div>)}</div>}
      {tab==="Candidates"&&<><div className="hrx-toolbar"><div className="hrx-search"><Icon name="search" size={15}/><input value={query} onChange={e=>setQuery(e.target.value)} placeholder="Search candidate, role or source…"/></div><select className="chip-select" value={candidateSource} disabled={!candidateUrl} onChange={e=>setCandidateSource(e.target.value)} aria-label="Filter candidates by source"><option value="">All sources</option>{candidateSourceChoices.map(source=><option key={source} value={source}>{source}</option>)}</select><select className="chip-select" value={candidateStage} disabled={!candidateUrl} onChange={e=>setCandidateStage(e.target.value)} aria-label="Filter candidates by stage"><option value="">All stages</option>{candidateStageChoices.map(stage=><option key={stage.value} value={stage.value}>{stage.label}</option>)}</select><button className="chip-select" type="button" disabled={!candidateUrl||(!query&&!candidateSource&&!candidateStage)} onClick={()=>{setQuery("");setCandidateSource("");setCandidateStage("");}}>Reset filters</button><Badge tone={loadingCandidates?"b-orange":"b-slate"}>{loadingCandidates?"Loading candidates":`${filtered.length} candidate(s)`}</Badge><Badge tone={recruitmentOptions?.can_convert_candidates?"b-violet":"b-slate"}>{recruitmentOptions?.can_convert_candidates?"Conversion role":"Conversion restricted"}</Badge><Badge tone={candidateStoreUrl?"b-blue":"b-slate"}>{candidateStoreUrl?"Candidate create allowed":"Candidate create restricted"}</Badge><Button icon="plus" variant="primary" sm onClick={openCandidateCreate}>New Candidate</Button></div><Section title="Candidate Master" sub="Server-backed search, source and stage filters with documents, stage history and candidate-to-employee conversion"><Table rows={filtered} columns={[{label:"Candidate",render:r=><Person employee={r.name} sub={r.id}/>},{label:"Role",key:"role"},{label:"Source",key:"source"},{label:"Experience",render:r=>r.experience||"3–6 years"},{label:"Skills",render:r=><span className="tag">{r.skills||"Role matched"}</span>},{label:"Offer",render:r=>r.offerStatus?<StatePill>{r.offerStatus}</StatePill>:<span className="faint">—</span>},{label:"Employee",render:r=>r.employeeCode?<span className="mono">{r.employeeCode}</span>:<span className="faint">—</span>},{label:"Stage",render:r=><StatePill>{r.stage}</StatePill>},{label:"Action",render:r=>r.canConvert?<button className="hrx-link" onClick={()=>convertCandidate(r)}>Convert to Employee</button>:<span className="faint">—</span>}]}/></Section></>}
      {tab==="Interviews"&&<Section title="Interview Scheduler" sub={interviewUrl?"MySQL-backed interview calendar with panel conflict validation and panel feedback workflow.":"Calendar-linked panel availability and conflict validation"} action={<Button icon="calendar" variant="primary" sm onClick={schedule}>Schedule Interview</Button>}><div className="hrx-toolbar"><Badge tone={interviewUrl?"b-green":"b-orange"}>{loadingInterviews?"Loading Laravel interviews":interviewUrl?"Laravel interview register":"Interview API required"}</Badge><Badge tone={interviewFeedbackTemplate?"b-blue":"b-slate"}>{interviewFeedbackTemplate?"Panel feedback enabled":"Feedback API required"}</Badge></div><Table rows={interviewRows} columns={[{label:"Interview",key:"id"},{label:"Candidate",render:r=><Person employee={r.candidate}/>},{label:"Date & Time",key:"when"},{label:"Panel",key:"panel"},{label:"Mode",key:"mode"},{label:"Venue / Link",key:"venue"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Feedback",render:r=><span>{r.feedbackAverage?`${r.feedback} · Avg ${Number(r.feedbackAverage).toFixed(1)}`:r.feedback}</span>},{label:"Action",render:r=>r.canFeedback?<button className="hrx-link" onClick={()=>submitInterviewFeedback(r)}>Submit Feedback</button>:<span className="faint">—</span>}]}/></Section>}
      {tab==="Offers"&&<><Section title="Offer & Appointment Generation" sub={offerUrl?"MySQL-backed offer drafts, release workflow, mandatory placeholder validation and document history.":"Approved templates, mandatory placeholder validation and version history"} action={<Button icon="doc" variant="primary" sm onClick={createOffer}>Generate Offer</Button>}><div className="hrx-toolbar"><Badge tone={offerUrl?"b-green":"b-orange"}>{loadingOffers?"Loading Laravel offers":offerUrl?"Laravel offer register":"Offer API required"}</Badge><Badge tone={recruitmentOptions?.can_create_offer?"b-blue":"b-slate"}>{recruitmentOptions?.can_create_offer?"Offer creation allowed":"Offer creation restricted"}</Badge><Badge tone={recruitmentOptions?.can_release_offers?"b-violet":"b-slate"}>{recruitmentOptions?.can_release_offers?"Release role":"Release restricted"}</Badge></div><Table rows={offerRows} columns={[{label:"Offer",key:"id"},{label:"Candidate",render:r=><Person employee={r.candidate}/>},{label:"Template",key:"template"},{label:"Version",render:r=>"v"+r.version},{label:"Joining",render:r=>r.joining||"—"},{label:"CTC",right:true,render:r=>money(r.ctc)},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.canRelease?<button className="hrx-link" onClick={()=>releaseOffer(r)}>Release</button>:<span className="faint">—</span>}]}/></Section><div className="hrx-warning"><Icon name="shield"/><div><b>Template guard enabled</b><span>Offer drafts cannot be released while mandatory placeholders are unresolved; creator-release segregation is enforced by Laravel policies.</span></div></div></>}
      {tab==="Pipeline"&&<Section title="Job Requisition Register" sub={openingUrl?"MySQL-backed requisitions with approval/rejection workflow and creator segregation.":"Recruitment requisition API unavailable for this role"}>{openings.length?<Table rows={openings} columns={[{label:"Opening",render:r=><div><b>{r.title}</b><div className="cell-sub">{r.opening_code||"Draft"} · {r.designation}</div></div>},{label:"Department",render:r=><Badge tone="b-blue">{r.department}</Badge>},{label:"Company",render:r=>r.company?.code||r.company?.name||"—"},{label:"Positions",key:"positions",right:true},{label:"Employment",render:r=>String(r.employment_type||"").replace("_"," ")},{label:"Target",render:r=>r.target_hiring_date||"Not set"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>recruitmentOptions?.can_approve_job_openings&&r.status==="pending_approval"?<span><button className="hrx-link" onClick={()=>reviewOpening(r,"approve")}>Approve</button><span className="faint"> / </span><button className="hrx-link" onClick={()=>reviewOpening(r,"reject")}>Reject</button></span>:<span className="faint">{r.reviewed_by?.name||"—"}</span>}]}/>:<EmptyPanel icon="funnel" title="No job requisitions found" text={openingUrl?"Create a job requisition to start the approval workflow.":"Recruitment requisition register is unavailable for this role."}/>}</Section>}
      {creatingCandidate&&<CandidateCreateModal options={recruitmentOptions} openings={openings} onClose={()=>setCreatingCandidate(false)} onCreated={onCandidateCreated} toast={toast}/>}
      {schedulingInterview&&<InterviewScheduleModal options={recruitmentOptions} candidates={serverCandidates} onClose={()=>setSchedulingInterview(false)} onCreated={onInterviewCreated} toast={toast}/>}
      {feedbackInterview&&<InterviewFeedbackModal options={recruitmentOptions} interview={feedbackInterview} onClose={()=>setFeedbackInterview(null)} onSubmitted={onInterviewFeedbackSubmitted} toast={toast}/>}
      {creatingOffer&&<OfferCreateModal options={recruitmentOptions} candidates={serverCandidates} onClose={()=>setCreatingOffer(false)} onCreated={onOfferCreated} toast={toast}/>}
    </div>;
  }

  function PerformanceCycleModal({ options, onClose, onCreated, toast }) {
    const today=new Date();
    const start=new Date(today.getFullYear(),today.getMonth()+1,1);
    const end=new Date(today.getFullYear(),today.getMonth()+2,0);
    const fmt=d=>d.toISOString().slice(0,10);
    const firstCompany=options?.companies?.[0]?.id||"";
    const [form,setForm]=React.useState({company_id:firstCompany,name:"Monthly Performance Review",frequency:"monthly",status:"active",starts_on:fmt(start),ends_on:fmt(end),review_due_on:fmt(new Date(end.getFullYear(),end.getMonth(),Math.min(end.getDate()+5,28))),department:"",project_id:"",rating_scale_min:1,rating_scale_max:5,passing_score:3,pip_threshold:2.5});
    const [busy,setBusy]=React.useState(false);
    const [error,setError]=React.useState("");
    const projects=(options?.projects||[]).filter(p=>!form.company_id||String(p.company_id)===String(form.company_id));
    const set=(key,value)=>setForm(current=>({...current,[key]:value,...(key==="company_id"?{project_id:""}:{})}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.performance_cycles_store_url){setError("Your role is not permitted to create performance cycles.");return;}if(!form.name.trim()||!form.frequency||!form.starts_on||!form.ends_on){setError("Name, frequency, start date and end date are required.");return;}if(new Date(form.ends_on)<new Date(form.starts_on)){setError("End date must be after or equal to start date.");return;}try{setBusy(true);const payload={company_id:form.company_id?Number(form.company_id):null,name:form.name.trim(),frequency:form.frequency,status:form.status,starts_on:form.starts_on,ends_on:form.ends_on,review_due_on:form.review_due_on||null,department:form.department||null,project_id:form.project_id?Number(form.project_id):null,rating_scale_min:Number(form.rating_scale_min),rating_scale_max:Number(form.rating_scale_max),passing_score:Number(form.passing_score),rules:{kpi_weight_percent:70,kra_weight_percent:30,pip_threshold:Number(form.pip_threshold)}};const body=await apiJson(options.performance_cycles_store_url,{method:"POST",body:JSON.stringify(payload)});toast&&toast("Performance cycle created in Laravel workflow.","green");onCreated&&onCreated(body.data);onClose();}catch(err){setError(err.message||"Performance cycle could not be created.");toast&&toast("Performance cycle not created: "+(err.message||"save failed"),"orange");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Launch Performance Cycle</h2><p>Creates a Laravel cycle with overlap prevention, scoped population rules, audit trail and configurable PIP threshold.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Cycle not created</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Company<select value={form.company_id} disabled={busy} onChange={e=>set("company_id",e.target.value)}><option value="">Scoped company</option>{(options?.companies||[]).map(c=><option key={c.id} value={c.id}>{c.label||c.name}</option>)}</select></label><label>Department<select value={form.department} disabled={busy} onChange={e=>set("department",e.target.value)}><option value="">All departments</option>{(options?.departments||[]).map(d=><option key={d} value={d}>{d}</option>)}</select></label><label>Project<select value={form.project_id} disabled={busy||!projects.length} onChange={e=>set("project_id",e.target.value)}><option value="">All projects</option>{projects.map(p=><option key={p.id} value={p.id}>{p.label||p.name}</option>)}</select></label><label>Frequency<select required value={form.frequency} disabled={busy} onChange={e=>set("frequency",e.target.value)}>{(options?.frequencies||[]).map(f=><option key={f.value} value={f.value}>{f.label}</option>)}</select></label><label style={{gridColumn:"1 / -1"}}>Cycle name<input required maxLength={255} value={form.name} disabled={busy} onChange={e=>set("name",e.target.value)}/></label><label>Status<select value={form.status} disabled={busy} onChange={e=>set("status",e.target.value)}>{(options?.cycle_statuses||[]).map(s=><option key={s.value} value={s.value}>{s.label}</option>)}</select></label><label>Starts on<input required type="date" value={form.starts_on} disabled={busy} onChange={e=>set("starts_on",e.target.value)}/></label><label>Ends on<input required type="date" value={form.ends_on} disabled={busy} onChange={e=>set("ends_on",e.target.value)}/></label><label>Review due on<input type="date" value={form.review_due_on} disabled={busy} onChange={e=>set("review_due_on",e.target.value)}/></label><label>Scale min<input type="number" min="1" max="9" value={form.rating_scale_min} disabled={busy} onChange={e=>set("rating_scale_min",e.target.value)}/></label><label>Scale max<input type="number" min="2" max="10" value={form.rating_scale_max} disabled={busy} onChange={e=>set("rating_scale_max",e.target.value)}/></label><label>Passing score<input type="number" min="1" max="10" step="0.1" value={form.passing_score} disabled={busy} onChange={e=>set("passing_score",e.target.value)}/></label><label>PIP threshold<input type="number" min="1" max="10" step="0.1" value={form.pip_threshold} disabled={busy} onChange={e=>set("pip_threshold",e.target.value)}/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="star" size={15}/>{busy?"Creating...":"Create Cycle"}</button></div></form></div>;
  }

  function PerformanceReviewModal({ options, cycles, onClose, onCreated, toast }) {
    const activeCycles=(cycles||[]).filter(c=>c.status==="active");
    const employees=options?.employees||[];
    const [form,setForm]=React.useState({performance_cycle_id:activeCycles[0]?.id||"",employee_id:"",manager_employee_id:"",kpi1:"Role KPI",target1:"Configured target",weight1:60,kpi2:"Behavioural KRA",target2:"Manager assessment",weight2:40,role_expectation:"Cycle-specific KPI/KRA review."});
    const [busy,setBusy]=React.useState(false);
    const [error,setError]=React.useState("");
    const selectedEmployee=employees.find(e=>String(e.id)===String(form.employee_id));
    React.useEffect(()=>{if(selectedEmployee?.manager_employee_id&&!form.manager_employee_id)setForm(current=>({...current,manager_employee_id:selectedEmployee.manager_employee_id}));},[selectedEmployee?.id]);
    const set=(key,value)=>setForm(current=>({...current,[key]:value}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.performance_reviews_store_url){setError("Your role is not permitted to create performance reviews.");return;}if(!form.performance_cycle_id||!form.employee_id||!form.kpi1.trim()||!form.kpi2.trim()){setError("Cycle, employee and two KPI rows are required.");return;}if(Number(form.weight1)+Number(form.weight2)!==100){setError("KPI weights must total exactly 100%.");return;}try{setBusy(true);const payload={performance_cycle_id:Number(form.performance_cycle_id),employee_id:Number(form.employee_id),manager_employee_id:form.manager_employee_id?Number(form.manager_employee_id):null,kpis:[{name:form.kpi1.trim(),target:form.target1.trim()||null,weight:Number(form.weight1),metric:"kpi"},{name:form.kpi2.trim(),target:form.target2.trim()||null,weight:Number(form.weight2),metric:"kra"}],kra_summary:{role_expectation:form.role_expectation.trim()||"Cycle-specific KPI/KRA review."}};const body=await apiJson(options.performance_reviews_store_url,{method:"POST",body:JSON.stringify(payload)});toast&&toast("Performance review created in Laravel workflow.","green");onCreated&&onCreated(body.data);onClose();}catch(err){setError(err.message||"Performance review could not be created.");toast&&toast("Performance review not created: "+(err.message||"save failed"),"orange");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Create Performance Review</h2><p>Creates an employee review for an active cycle with KPI weight validation, employee scope checks and notification/audit workflow.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Review not created</b><span>{error}</span></div></div>}{!activeCycles.length&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>No active cycle</b><span>Create or activate a performance cycle before assigning reviews.</span></div></div>}<div className="hrx-form-grid"><label>Active cycle<select required value={form.performance_cycle_id} disabled={busy||!activeCycles.length} onChange={e=>set("performance_cycle_id",e.target.value)}><option value="">Select cycle</option>{activeCycles.map(c=><option key={c.id} value={c.id}>{c.cycle_code||c.id} - {c.name}</option>)}</select></label><label>Employee<SearchablePeoplePicker items={employees} selected={form.employee_id} mode="single" required disabled={busy||!employees.length} placeholder="Search employee name, code, department..." emptyText="No matching employees" onChange={value=>set("employee_id",value||"")} getId={e=>e.id} getLabel={e=>e.name||e.label||"Employee"} getSubLabel={e=>e.label&&e.name?e.label:[e.employee_code,e.department,e.designation,e.email].filter(Boolean).join(" · ")}/></label><label>Manager<SearchablePeoplePicker items={employees.filter(e=>String(e.id)!==String(form.employee_id))} selected={form.manager_employee_id} mode="single" disabled={busy||!employees.length} placeholder="Search manager name, code, department..." emptyText="No matching managers" onChange={value=>set("manager_employee_id",value||"")} getId={e=>e.id} getLabel={e=>e.name||e.label||"Manager"} getSubLabel={e=>e.label&&e.name?e.label:[e.employee_code,e.department,e.designation,e.email].filter(Boolean).join(" · ")}/></label><label>Role expectation<input maxLength={500} value={form.role_expectation} disabled={busy} onChange={e=>set("role_expectation",e.target.value)}/></label><label>KPI 1<input required maxLength={160} value={form.kpi1} disabled={busy} onChange={e=>set("kpi1",e.target.value)}/></label><label>KPI 1 target<input maxLength={255} value={form.target1} disabled={busy} onChange={e=>set("target1",e.target.value)}/></label><label>KPI 1 weight<input required type="number" min="0" max="100" value={form.weight1} disabled={busy} onChange={e=>set("weight1",e.target.value)}/></label><label>KPI 2<input required maxLength={160} value={form.kpi2} disabled={busy} onChange={e=>set("kpi2",e.target.value)}/></label><label>KPI 2 target<input maxLength={255} value={form.target2} disabled={busy} onChange={e=>set("target2",e.target.value)}/></label><label>KPI 2 weight<input required type="number" min="0" max="100" value={form.weight2} disabled={busy} onChange={e=>set("weight2",e.target.value)}/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy||!activeCycles.length}><Icon name="check" size={15}/>{busy?"Creating...":"Create Review"}</button></div></form></div>;
  }

  function PerformanceView({ state, update, toast, performanceOptions }) {
    const [tab,setTab]=React.useState("Reviews");
    const [selectedDepartment,setSelectedDepartment]=React.useState("");
    const [cycles,setCycles]=React.useState([]);
    const [reviews,setReviews]=React.useState([]);
    const [loading,setLoading]=React.useState(false);
    const [error,setError]=React.useState("");
    const [creatingCycle,setCreatingCycle]=React.useState(false);
    const [creatingReview,setCreatingReview]=React.useState(false);
    const cycleUrl=performanceOptions?.performance_cycles_index_url;
    const reviewUrl=performanceOptions?.performance_reviews_index_url;
    React.useEffect(()=>{if(!cycleUrl)return;let alive=true;setLoading(true);setError("");apiJson(collectionUrl(cycleUrl,{per_page:50})).then(body=>{if(alive)setCycles(body.data||[]);}).catch(err=>{if(alive){setError(err.message||"Unable to load performance cycles.");toast&&toast("Performance cycle fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[cycleUrl,toast]);
    React.useEffect(()=>{if(!reviewUrl)return;let alive=true;setLoading(true);setError("");apiJson(collectionUrl(reviewUrl,{per_page:50})).then(body=>{if(alive)setReviews(body.data||[]);}).catch(err=>{if(alive){setError(err.message||"Unable to load performance reviews.");toast&&toast("Performance review fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[reviewUrl,toast]);
    const cycleRows=cycleUrl?cycles:[];
    const reviewRows=(reviewUrl?reviews:[]).map(r=>r.review_number?{id:r.id,review:r.review_number,employee:r.employee?.name||"—",employeeCode:r.employee?.employee_code||"—",cycle:r.cycle?.name||"—",self:r.self_score,manager:r.manager_score,final:r.final_score,status:r.status,pip:r.pip_required,raw:r}:r);
    const activeCycle=(cycleUrl?cycles.find(c=>c.status==="active"):null)||{cycle_code:"—",name:"Requires API"};
    const closed=reviewRows.filter(r=>r.status==="closed").length;
    const completion=reviewRows.length?Math.round(closed/reviewRows.length*100):0;
    const departments=Object.values(reviewRows.reduce((acc,row)=>{const raw=row.raw||{};const dep=raw.employee?.department||"Unassigned";const current=acc[dep]||{id:dep,department:dep,employees:0,total:0,rated:0,overdue:0,pip:0};current.employees+=1;if(row.final||row.manager){current.total+=Number(row.final||row.manager);current.rated+=1;}if(["draft","self_submitted","manager_submitted"].includes(row.status))current.overdue+=1;if(row.pip)current.pip+=1;acc[dep]=current;return acc;},{})).map(r=>({...r,attainment:r.rated?Math.round((r.total/r.rated)/5*100):0,rating:r.rated?r.total/r.rated:0}));
    const selectedDepartmentReviews=selectedDepartment?reviewRows.filter(row=>(row.raw?.employee?.department||"Unassigned")===selectedDepartment):[];
    if (!cycleUrl || !reviewUrl) return <div><ViewTitle title="Performance Management" sub="Performance API required for KPI/KRA cycles, reviews, department dashboards, calibration and PIP workflows." actions={[<Button key="r" icon="plus" sm onClick={()=>unavailableAction(update,"Performance review creation","Performance")}>Create Review</Button>,<Button key="c" icon="plus" variant="primary" sm onClick={()=>unavailableAction(update,"Performance cycle creation","Performance")}>Launch Cycle</Button>]}/><div className="hrx-demo-banner"><Icon name="shield" size={17}/><div><b>Performance fallback is read-only.</b><span>No local performance cycles, reviews, PIP counts, completion rates or department scorecards are fabricated without the governed Laravel performance workflow.</span></div><Badge tone="b-orange">API REQUIRED</Badge></div><KpiGrid><Stat label="Active Cycle" value="—" unit="Requires API" icon="star" tone="accent"/><Stat label="Completion" value="—" unit="%" icon="check" tone="green"/><Stat label="Calibration Pending" value="—" icon="users" tone="orange"/><Stat label="Active PIPs" value="—" icon="alert" tone="red"/></KpiGrid><Section title="Review Workspace" sub="Backend performance workflow required"><EmptyPanel icon="star" title="No performance register loaded" text="Performance cycles, KPI/KRA reviews, calibration, PIPs and department scorecards are hidden until Laravel performance APIs are available."/></Section></div>;
    const launchMonthly=()=>performanceOptions?.can_create_performance_cycle&&performanceOptions?.performance_cycles_store_url?setCreatingCycle(true):unavailableAction(update,"Performance cycle creation","Performance");
    const createReview=()=>performanceOptions?.can_create_performance_review&&performanceOptions?.performance_reviews_store_url?setCreatingReview(true):unavailableAction(update,"Performance review creation","Performance");
    const onCycleCreated=row=>{setCycles(current=>[row,...current.filter(x=>x.id!==row.id)]);update((s,actor)=>addAudit(s,actor,"Created Laravel performance cycle",row.cycle_code||row.name,"Performance"),"Performance cycle created");};
    const onReviewCreated=row=>{setReviews(current=>[row,...current.filter(x=>x.id!==row.id)]);update((s,actor)=>addAudit(s,actor,"Created Laravel performance review",row.review_number||row.id,"Performance"),"Performance review created");};
    const submitManager=async row=>{const template=performanceOptions?.performance_review_manager_submit_url_template;if(!template||!performanceOptions?.can_submit_manager_review||!["draft","self_submitted"].includes(row.status)){unavailableAction(update,"Manager performance review submission","Performance");return;}try{const score=Number(row.self||3);const body=await apiJson(template.replace("__REVIEW__",row.id),{method:"PATCH",body:JSON.stringify({manager_score:score,manager_comments:"Manager review submitted from HR Performance workspace."})});setReviews(current=>current.map(x=>x.id===body.data.id?body.data:x));toast&&toast("Manager review submitted in Laravel workflow.","green");}catch(err){setError(err.message||"Manager review submission failed.");toast&&toast("Manager review failed: "+(err.message||"submit failed"),"orange");}};
    const closeReview=async row=>{const template=performanceOptions?.performance_review_close_url_template;if(!template||!performanceOptions?.can_close_performance_review||row.status!=="manager_submitted"){unavailableAction(update,"HR performance review closure","Performance");return;}try{const score=Number(row.manager||row.final||3);const pip=score<=3;const body=await apiJson(template.replace("__REVIEW__",row.id),{method:"PATCH",body:JSON.stringify({final_score:score,final_rating:score>=4?"Exceeds Expectations":score>=3?"Meets Expectations":"Needs Improvement",hr_comments:"Closed from HR Performance workspace after manager submission.",pip_required:pip,pip_plan:pip?{objectives:["Improve measurable KPI outcomes before the next review checkpoint."],starts_on:new Date().toISOString().slice(0,10),ends_on:new Date(Date.now()+30*86400000).toISOString().slice(0,10),review_frequency:"weekly",owner:"Reporting Manager"}:null})});setReviews(current=>current.map(x=>x.id===body.data.id?body.data:x));toast&&toast("Performance review closed in Laravel workflow.","green");}catch(err){setError(err.message||"Performance review closure failed.");toast&&toast("Review closure failed: "+(err.message||"close failed"),"orange");}};
    return <div><ViewTitle title="Performance Management" sub="KPI/KRA goals, monthly and quarterly reviews, calibration, appraisal linkage and PIP." actions={[<Button key="r" icon="plus" sm onClick={createReview}>Create Review</Button>,<Button key="c" icon="plus" variant="primary" sm onClick={launchMonthly}>Launch Cycle</Button>]}/>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Performance API issue</b><span>{error}</span></div></div>}<div className="hrx-settings-nav">{["Reviews","Cycles","Department Dashboard"].map(x=><button key={x} className={tab===x?"on":""} onClick={()=>setTab(x)}>{x}</button>)}</div><div className="hrx-toolbar"><Badge tone={cycleUrl&&reviewUrl?"b-green":"b-orange"}>{loading?"Loading Laravel performance":cycleUrl&&reviewUrl?"Laravel performance register":"Performance API required"}</Badge><Badge tone={performanceOptions?.can_create_performance_cycle?"b-blue":"b-slate"}>{performanceOptions?.can_create_performance_cycle?"Cycle creation allowed":"Cycle creation restricted"}</Badge><Badge tone={performanceOptions?.can_close_performance_review?"b-violet":"b-slate"}>{performanceOptions?.can_close_performance_review?"HR closure role":"Closure restricted"}</Badge></div><KpiGrid><Stat label="Active Cycle" value={activeCycle?.cycle_code||"Q1"} unit={activeCycle?.name||"2026"} icon="star" tone="accent"/><Stat label="Completion" value={cycleUrl?completion:78} unit="%" icon="check" tone="green"/><Stat label="Calibration Pending" value={cycleUrl?reviewRows.filter(x=>x.status==="manager_submitted").length:24} icon="users" tone="orange"/><Stat label="Active PIPs" value={cycleUrl?reviewRows.filter(x=>x.pip).length:3} icon="alert" tone="red"/></KpiGrid>
      {tab==="Reviews"&&<><Section title="Review Workspace" sub={reviewUrl?"MySQL-backed Self → Manager → HR closure workflow":"Self → Manager → Calibration → HR final"}><Table rows={reviewRows} columns={[{label:"Review",render:r=><div><b>{r.review||r.id}</b><div className="cell-sub">{r.employeeCode||"—"}</div></div>},{label:"Employee",render:r=><Person employee={r.employee}/>},{label:"Cycle",key:"cycle"},{label:"Self",render:r=><b>{r.self?Number(r.self).toFixed(1):"—"}</b>},{label:"Manager",render:r=><b>{r.manager?Number(r.manager).toFixed(1):"—"}</b>},{label:"Final",render:r=><span className="mono">{r.final?Number(r.final).toFixed(1):"—"}</span>},{label:"PIP",render:r=>r.pip?<Badge tone="b-red">OPEN</Badge>:<span className="faint">—</span>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="manager_submitted"&&performanceOptions?.can_close_performance_review?<button className="hrx-link" onClick={()=>closeReview(r)}>Close</button>:["draft","self_submitted"].includes(r.status)&&performanceOptions?.can_submit_manager_review?<button className="hrx-link" onClick={()=>submitManager(r)}>Manager Submit</button>:<span className="faint">—</span>}]}/></Section><div className="hrx-card-grid"><Feature icon="target" title="KPI & KRA Library" text="Weighted measures by role, grade and department."/><Feature icon="star" title="Rating Calibration" text="Distribution, moderation and locked ratings."/><Feature icon="trend" title="Appraisal Linkage" text="Approved revision and promotion workflows."/><Feature icon="alert" title="Performance Improvement" text="Goals, evidence, meetings and closure history."/></div></>}
      {tab==="Cycles"&&<Section title="Performance Cycles" sub={cycleUrl?"MySQL-backed monthly, quarterly and annual periods with overlap rejection":"Monthly, quarterly and annual periods; overlapping populations are rejected"}><Table rows={cycleRows} columns={[{label:"Cycle",render:r=><div><b>{r.name}</b><div className="cell-sub">{r.cycle_code||r.id}</div></div>},{label:"Frequency",key:"frequency"},{label:"Department",render:r=>r.department||r.population||"All employees"},{label:"From",render:r=>r.starts_on||r.from},{label:"To",render:r=>r.ends_on||r.to},{label:"Reviews",right:true,render:r=>r.reviews_count??"—"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section>}
      {tab==="Department Dashboard"&&<><Section title="Department Performance Dashboard" sub="Role-scoped aggregate metrics with employee drill-down">{departments.length?<Table rows={departments} columns={[{label:"Department",key:"department"},{label:"Employees",key:"employees",right:true},{label:"KPI attainment",render:r=><ProgCell value={r.attainment}/>},{label:"Average rating",render:r=><b>{Number(r.rating||0).toFixed(1)}</b>},{label:"Open reviews",key:"overdue",right:true},{label:"Active PIPs",key:"pip",right:true},{label:"Trend",render:r=><Badge tone={r.attainment>=85?"b-green":"b-orange"}>{r.attainment>=85?"ON TRACK":"WATCH"}</Badge>},{label:"Action",render:r=><button className="hrx-link" onClick={()=>setSelectedDepartment(r.department)}>Drill down</button>}]}/>:<EmptyPanel icon="chart" title="No department performance records loaded" text="Department scorecards appear only after scoped Laravel performance reviews exist; no local department rows are fabricated."/>}</Section>{selectedDepartment&&<Section title={`${selectedDepartment} Employee Reviews`} sub="Employee-level drill-down from the selected department scorecard"><Table rows={selectedDepartmentReviews} columns={[{label:"Review",render:r=><div><b>{r.review||r.id}</b><div className="cell-sub">{r.employeeCode||"—"}</div></div>},{label:"Employee",render:r=><Person employee={r.employee}/>},{label:"Cycle",key:"cycle"},{label:"Self",render:r=><b>{r.self?Number(r.self).toFixed(1):"—"}</b>},{label:"Manager",render:r=><b>{r.manager?Number(r.manager).toFixed(1):"—"}</b>},{label:"Final",render:r=><span className="mono">{r.final?Number(r.final).toFixed(1):"—"}</span>},{label:"PIP",render:r=>r.pip?<Badge tone="b-red">OPEN</Badge>:<span className="faint">—</span>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section>}</>}
      {creatingCycle&&<PerformanceCycleModal options={performanceOptions} onClose={()=>setCreatingCycle(false)} onCreated={onCycleCreated} toast={toast}/>}
      {creatingReview&&<PerformanceReviewModal options={performanceOptions} cycles={cycles} onClose={()=>setCreatingReview(false)} onCreated={onReviewCreated} toast={toast}/>}
    </div>;
  }

  function LifecycleConfirmationModal({ options, onClose, onCreated, toast }) {
    const employees=options?.employees||[];
    const first=employees[0]||{};
    const today=new Date(); const ends=new Date(Date.now()+30*86400000);
    const [form,setForm]=React.useState({employee_id:first.id||"",manager_employee_id:first.manager_employee_id||"",probation_starts_on:"",probation_ends_on:ends.toISOString().slice(0,10),review_due_on:today.toISOString().slice(0,10)});
    const [busy,setBusy]=React.useState(false); const [error,setError]=React.useState("");
    const selected=employees.find(e=>String(e.id)===String(form.employee_id));
    React.useEffect(()=>{if(selected?.manager_employee_id)setForm(current=>({...current,manager_employee_id:current.manager_employee_id||selected.manager_employee_id}));},[selected?.id]);
    const set=(key,value)=>setForm(current=>({...current,[key]:value}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.confirmation_cases_store_url){setError("Your role is not permitted to create confirmation cases.");return;}if(!form.employee_id||!form.probation_ends_on){setError("Employee and probation end date are required.");return;}try{setBusy(true);const body=await apiJson(options.confirmation_cases_store_url,{method:"POST",body:JSON.stringify({employee_id:Number(form.employee_id),manager_employee_id:form.manager_employee_id?Number(form.manager_employee_id):null,probation_starts_on:form.probation_starts_on||null,probation_ends_on:form.probation_ends_on,review_due_on:form.review_due_on||null})});toast&&toast("Confirmation case created in Laravel workflow.","green");onCreated&&onCreated(body.data);onClose();}catch(err){setError(err.message||"Confirmation case could not be created.");toast&&toast("Confirmation case not created: "+(err.message||"save failed"),"orange");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>New Confirmation Case</h2><p>Creates a Laravel probation confirmation workflow with scoped employee and manager validation.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Case not created</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee<SearchablePeoplePicker items={employees} selected={form.employee_id} mode="single" required disabled={busy||!employees.length} placeholder="Search employee name, code, department..." emptyText="No matching employees" onChange={value=>set("employee_id",value||"")} getId={e=>e.id} getLabel={e=>e.name||e.label||"Employee"} getSubLabel={e=>e.label&&e.name?e.label:[e.employee_code,e.department,e.designation,e.email].filter(Boolean).join(" · ")}/></label><label>Manager<SearchablePeoplePicker items={employees.filter(e=>String(e.id)!==String(form.employee_id))} selected={form.manager_employee_id} mode="single" disabled={busy||!employees.length} placeholder="Search manager name, code, department..." emptyText="No matching managers" onChange={value=>set("manager_employee_id",value||"")} getId={e=>e.id} getLabel={e=>e.name||e.label||"Manager"} getSubLabel={e=>e.label&&e.name?e.label:[e.employee_code,e.department,e.designation,e.email].filter(Boolean).join(" · ")}/></label><label>Probation starts<input type="date" value={form.probation_starts_on} disabled={busy} onChange={e=>set("probation_starts_on",e.target.value)}/></label><label>Probation ends<input required type="date" value={form.probation_ends_on} disabled={busy} onChange={e=>set("probation_ends_on",e.target.value)}/></label><label>Review due<input type="date" value={form.review_due_on} disabled={busy} onChange={e=>set("review_due_on",e.target.value)}/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="check" size={15}/>{busy?"Creating...":"Create Case"}</button></div></form></div>;
  }

  function LifecycleSettlementModal({ options, onClose, onCreated, toast }) {
    const employees=options?.employees||[];
    const first=employees[0]||{}; const last=new Date(Date.now()+30*86400000); const settle=new Date(Date.now()+45*86400000);
    const [form,setForm]=React.useState({employee_id:first.id||"",separation_type:"resignation",resignation_date:new Date().toISOString().slice(0,10),last_working_date:last.toISOString().slice(0,10),settlement_date:settle.toISOString().slice(0,10),reason:"",bonus_amount:"0",gratuity_amount:"0",notice_recovery_amount:"0",tax_recovery_amount:"0"});
    const [busy,setBusy]=React.useState(false); const [error,setError]=React.useState("");
    const set=(key,value)=>setForm(current=>({...current,[key]:value}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.separation_settlements_store_url){setError("Your role is not permitted to initiate F&F settlements.");return;}if(!form.employee_id||!form.separation_type||!form.last_working_date){setError("Employee, separation type and last working date are required.");return;}try{setBusy(true);const payload={employee_id:Number(form.employee_id),separation_type:form.separation_type,resignation_date:form.resignation_date||null,last_working_date:form.last_working_date,settlement_date:form.settlement_date||null,reason:form.reason.trim()||null,bonus_amount:Number(form.bonus_amount||0),gratuity_amount:Number(form.gratuity_amount||0),notice_recovery_amount:Number(form.notice_recovery_amount||0),tax_recovery_amount:Number(form.tax_recovery_amount||0)};const body=await apiJson(options.separation_settlements_store_url,{method:"POST",body:JSON.stringify(payload)});toast&&toast("F&F settlement initiated in Laravel workflow.","green");onCreated&&onCreated(body.data);onClose();}catch(err){setError(err.message||"Settlement could not be initiated.");toast&&toast("Settlement not initiated: "+(err.message||"save failed"),"orange");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Initiate Full & Final</h2><p>Creates a Laravel settlement with salary, leave, claims, loans, asset blockers, approval workflow and audit trail.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Settlement not initiated</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee<SearchablePeoplePicker items={employees} selected={form.employee_id} mode="single" required disabled={busy||!employees.length} placeholder="Search employee name, code, department..." emptyText="No matching employees" onChange={value=>set("employee_id",value||"")} getId={e=>e.id} getLabel={e=>e.name||e.label||"Employee"} getSubLabel={e=>e.label&&e.name?e.label:[e.employee_code,e.department,e.designation,e.status,e.email].filter(Boolean).join(" · ")}/></label><label>Separation type<select required value={form.separation_type} disabled={busy} onChange={e=>set("separation_type",e.target.value)}>{(options?.separation_types||[]).map(t=><option key={t.value} value={t.value}>{t.label}</option>)}</select></label><label>Resignation date<input type="date" value={form.resignation_date} disabled={busy} onChange={e=>set("resignation_date",e.target.value)}/></label><label>Last working date<input required type="date" value={form.last_working_date} disabled={busy} onChange={e=>set("last_working_date",e.target.value)}/></label><label>Settlement date<input type="date" value={form.settlement_date} disabled={busy} onChange={e=>set("settlement_date",e.target.value)}/></label><label>Bonus<input type="number" min="0" value={form.bonus_amount} disabled={busy} onChange={e=>set("bonus_amount",e.target.value)}/></label><label>Gratuity<input type="number" min="0" value={form.gratuity_amount} disabled={busy} onChange={e=>set("gratuity_amount",e.target.value)}/></label><label>Notice recovery<input type="number" min="0" value={form.notice_recovery_amount} disabled={busy} onChange={e=>set("notice_recovery_amount",e.target.value)}/></label><label>Tax recovery<input type="number" min="0" value={form.tax_recovery_amount} disabled={busy} onChange={e=>set("tax_recovery_amount",e.target.value)}/></label><label style={{gridColumn:"1 / -1"}}>Reason<textarea maxLength={2000} value={form.reason} disabled={busy} onChange={e=>set("reason",e.target.value)}/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="wallet" size={15}/>{busy?"Initiating...":"Initiate Settlement"}</button></div></form></div>;
  }

  function LifecycleExitInterviewModal({ options, settlements, onClose, onCreated, toast }) {
    const employees=options?.employees||[]; const first=employees[0]||{}; const due=new Date(Date.now()+14*86400000).toISOString().slice(0,10);
    const [form,setForm]=React.useState({employee_id:first.id||"",employee_separation_settlement_id:"",interview_due_on:due,note:"Schedule before final handover."});
    const [busy,setBusy]=React.useState(false); const [error,setError]=React.useState("");
    const set=(key,value)=>setForm(current=>({...current,[key]:value}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.exit_interviews_store_url){setError("Your role is not permitted to schedule exit interviews.");return;}if(!form.employee_id||!form.interview_due_on){setError("Employee and due date are required.");return;}try{setBusy(true);const body=await apiJson(options.exit_interviews_store_url,{method:"POST",body:JSON.stringify({employee_id:Number(form.employee_id),employee_separation_settlement_id:form.employee_separation_settlement_id?Number(form.employee_separation_settlement_id):null,interview_due_on:form.interview_due_on,questionnaire_template:[{key:"primary_reason",label:"Primary reason",type:"text"},{key:"rehire_context",label:"Rehire context",type:"choice"}],note:form.note.trim()||null})});toast&&toast("Exit interview scheduled in Laravel workflow.","green");onCreated&&onCreated(body.data);onClose();}catch(err){setError(err.message||"Exit interview could not be scheduled.");toast&&toast("Exit interview not scheduled: "+(err.message||"save failed"),"orange");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Schedule Exit Interview</h2><p>Creates a confidential Laravel exit-interview workflow with employee-scope and HR-only confidential visibility.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Exit interview not scheduled</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee<SearchablePeoplePicker items={employees} selected={form.employee_id} mode="single" required disabled={busy||!employees.length} placeholder="Search employee name, code, department..." emptyText="No matching employees" onChange={value=>set("employee_id",value||"")} getId={e=>e.id} getLabel={e=>e.name||e.label||"Employee"} getSubLabel={e=>e.label&&e.name?e.label:[e.employee_code,e.department,e.designation,e.email].filter(Boolean).join(" · ")}/></label><label>Linked F&F settlement<select value={form.employee_separation_settlement_id} disabled={busy} onChange={e=>set("employee_separation_settlement_id",e.target.value)}><option value="">Optional</option>{settlements.filter(s=>String(s.employee?.id)===String(form.employee_id)).map(s=><option key={s.id} value={s.id}>{s.settlement_number}</option>)}</select></label><label>Due date<input required type="date" value={form.interview_due_on} disabled={busy} onChange={e=>set("interview_due_on",e.target.value)}/></label><label style={{gridColumn:"1 / -1"}}>Note<textarea maxLength={1000} value={form.note} disabled={busy} onChange={e=>set("note",e.target.value)}/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="doc" size={15}/>{busy?"Scheduling...":"Schedule Interview"}</button></div></form></div>;
  }

  function LifecycleView({ state, update, toast, lifecycleOptions }) {
    const [tab,setTab]=React.useState("Tracker");
    const [confirmations,setConfirmations]=React.useState([]);
    const [settlements,setSettlements]=React.useState([]);
    const [exitInterviews,setExitInterviews]=React.useState([]);
    const [summary,setSummary]=React.useState(null);
    const [loading,setLoading]=React.useState(false); const [error,setError]=React.useState("");
    const [creatingConfirmation,setCreatingConfirmation]=React.useState(false);
    const [creatingSettlement,setCreatingSettlement]=React.useState(false);
    const [creatingExitInterview,setCreatingExitInterview]=React.useState(false);
    const confirmationUrl=lifecycleOptions?.confirmation_cases_index_url;
    const settlementUrl=lifecycleOptions?.separation_settlements_index_url;
    const exitUrl=lifecycleOptions?.exit_interviews_index_url;
    React.useEffect(()=>{if(!confirmationUrl)return;let alive=true;setLoading(true);apiJson(collectionUrl(confirmationUrl,{per_page:50})).then(body=>{if(alive)setConfirmations(body.data||[]);}).catch(err=>{if(alive){setError(err.message||"Unable to load confirmations.");toast&&toast("Confirmation fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[confirmationUrl,toast]);
    React.useEffect(()=>{if(!settlementUrl)return;let alive=true;setLoading(true);apiJson(collectionUrl(settlementUrl,{per_page:50})).then(body=>{if(alive)setSettlements(body.data||[]);}).catch(err=>{if(alive){setError(err.message||"Unable to load settlements.");toast&&toast("F&F fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[settlementUrl,toast]);
    React.useEffect(()=>{if(!exitUrl)return;let alive=true;setLoading(true);apiJson(collectionUrl(exitUrl,{per_page:50})).then(body=>{if(alive)setExitInterviews(body.data||[]);}).catch(err=>{if(alive){setError(err.message||"Unable to load exit interviews.");toast&&toast("Exit interview fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[exitUrl,toast]);
    React.useEffect(()=>{if(!lifecycleOptions?.exit_interviews_summary_url)return;let alive=true;apiJson(lifecycleOptions.exit_interviews_summary_url).then(body=>{if(alive)setSummary(body.data||null);}).catch(()=>{});return()=>{alive=false;};},[lifecycleOptions?.exit_interviews_summary_url]);
    const loaded=confirmationUrl||settlementUrl||exitUrl;
    const confirmationRows=confirmationUrl?confirmations:[];
    const settlementRows=settlementUrl?settlements:[];
    const exitRows=exitUrl?exitInterviews:[];
    const tracker=[...confirmationRows.map(x=>({id:x.case_number||x.id,employee:x.employee?.name||x.employee,event:"Confirmation",due:x.review_due_on||x.due,owner:x.manager?.name||x.manager,status:x.status})),...settlementRows.map(x=>({id:x.settlement_number||x.id,employee:x.employee?.name||x.employee,event:"Full & Final",due:x.settlement_date||x.last_working_date,owner:x.initiated_by?.name||"HR",status:x.status})),...exitRows.map(x=>({id:x.interview_number||x.id,employee:x.employee?.name||x.employee,event:"Exit Interview",due:x.interview_due_on,owner:x.scheduled_by?.name||"HR",status:x.status}))];
    const approveConfirmation=async row=>{const template=lifecycleOptions?.confirmation_case_decide_url_template;if(!template||!lifecycleOptions?.can_decide_confirmation||row.status!=="manager_recommended"){unavailableAction(update,"Confirmation HR decision","Employee Lifecycle");return;}try{const body=await apiJson(template.replace("__CASE__",row.id),{method:"PATCH",body:JSON.stringify({hr_decision:"confirm",hr_comments:"Confirmed from Employee Lifecycle workspace.",confirmation_effective_on:new Date().toISOString().slice(0,10),confirmation_letter_reference:`CNF-LETTER-${row.case_number||row.id}`})});setConfirmations(current=>current.map(x=>x.id===body.data.id?body.data:x));toast&&toast("Confirmation decision recorded in Laravel workflow.","green");}catch(err){setError(err.message||"Confirmation decision failed.");toast&&toast("Confirmation failed: "+(err.message||"decision failed"),"orange");}};
    const approveSettlement=async row=>{const status=row.status;const template=status==="initiated"?lifecycleOptions?.separation_settlement_hr_approve_url_template:status==="hr_approved"?lifecycleOptions?.separation_settlement_finance_approve_url_template:status==="finance_approved"?lifecycleOptions?.separation_settlement_complete_url_template:null;if(!template){unavailableAction(update,"F&F settlement approval","Employee Lifecycle");return;}const payload=status==="finance_approved"?{payment_reference:`FNF-${row.settlement_number||row.id}`,note:"Completed from Employee Lifecycle workspace."}:{note:"Approved from Employee Lifecycle workspace."};try{const body=await apiJson(template.replace("__SETTLEMENT__",row.id),{method:"PATCH",body:JSON.stringify(payload)});setSettlements(current=>current.map(x=>x.id===body.data.id?body.data:x));toast&&toast("F&F settlement updated in Laravel workflow.","green");}catch(err){setError(err.message||"F&F workflow action failed.");toast&&toast("F&F action failed: "+(err.message||"approval failed"),"orange");}};
    const reviewExit=async row=>{const template=lifecycleOptions?.exit_interview_review_url_template;if(!template||!lifecycleOptions?.can_review_exit_interview||row.status!=="submitted"){unavailableAction(update,"Exit interview review","Employee Lifecycle");return;}try{const body=await apiJson(template.replace("__INTERVIEW__",row.id),{method:"PATCH",body:JSON.stringify({hr_review_notes:"Reviewed from Employee Lifecycle workspace.",action_items:[{owner:"HR Manager",action:"Review exit feedback themes.",status:"open"}]})});setExitInterviews(current=>current.map(x=>x.id===body.data.id?body.data:x));toast&&toast("Exit interview reviewed in Laravel workflow.","green");}catch(err){setError(err.message||"Exit interview review failed.");toast&&toast("Exit review failed: "+(err.message||"review failed"),"orange");}};
    return <div><ViewTitle title="Employee Lifecycle" sub="Onboarding, probation, confirmation, separation, no-dues and Full & Final Settlement." actions={[<Button key="c" icon="check" sm onClick={()=>lifecycleOptions?.can_create_confirmation?setCreatingConfirmation(true):unavailableAction(update,"Confirmation case creation","Employee Lifecycle")}>New Confirmation</Button>,<Button key="f" icon="wallet" sm onClick={()=>lifecycleOptions?.can_create_separation_settlement?setCreatingSettlement(true):unavailableAction(update,"F&F initiation","Employee Lifecycle")}>New F&F</Button>,<Button key="e" icon="doc" variant="primary" sm onClick={()=>lifecycleOptions?.can_create_exit_interview?setCreatingExitInterview(true):unavailableAction(update,"Exit interview scheduling","Employee Lifecycle")}>Schedule Exit</Button>]}/>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Lifecycle API issue</b><span>{error}</span></div></div>}<div className="hrx-settings-nav">{["Tracker","Confirmations","Full & Final","Exit Interviews"].map(x=><button key={x} className={tab===x?"on":""} onClick={()=>setTab(x)}>{x}</button>)}</div><div className="hrx-toolbar"><Badge tone={loaded?"b-green":"b-orange"}>{loading?"Loading Laravel lifecycle":loaded?"Laravel lifecycle registers":"Lifecycle API required"}</Badge><Badge tone={lifecycleOptions?.can_create_separation_settlement?"b-blue":"b-slate"}>{lifecycleOptions?.can_create_separation_settlement?"F&F create allowed":"F&F restricted"}</Badge><Badge tone={lifecycleOptions?.can_review_exit_interview?"b-violet":"b-slate"}>{lifecycleOptions?.can_review_exit_interview?"Confidential HR role":"Confidential masked"}</Badge></div>
      {tab==="Tracker"&&<><div className="hrx-card-grid"><Feature icon="users" title="Onboarding" text="Documents, user provisioning, tasks and assets." badge="ACTIVE"/><Feature icon="check" title="Probation & Confirmation" text="Manager recommendation, HR approval and letter." badge={String(confirmationRows.length)}/><Feature icon="trend" title="Employee Movements" text="Effective-dated transfer, promotion and department history." badge="EFFECTIVE-DATED"/><Feature icon="key" title="Exit & F&F" text="No-dues, settlement, approvals and letters." badge={String(settlementRows.length)}/></div><Section title="Lifecycle Tracker" sub="Cross-functional ownership and SLA"><Table rows={tracker} columns={[{label:"Reference",key:"id"},{label:"Employee",render:r=><Person employee={r.employee}/>},{label:"Event",key:"event"},{label:"Due",key:"due"},{label:"Owner",key:"owner"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section></>}
      {tab==="Confirmations"&&<Section title="Confirmation Approval Workspace" sub={confirmationUrl?"MySQL-backed manager recommendation → HR review → confirmation letter":"Manager recommendation → HR review → confirmation letter"}><Table rows={confirmationRows} columns={[{label:"Case",render:r=>r.case_number||r.id},{label:"Employee",render:r=><Person employee={r.employee?.name||r.employee} sub={r.employee?.employee_code}/>},{label:"Due",render:r=>r.review_due_on||r.due},{label:"Manager",render:r=>r.manager?.name||r.manager},{label:"Recommendation",render:r=>r.manager_recommendation||r.recommendation||"—"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="manager_recommended"&&lifecycleOptions?.can_decide_confirmation?<button className="hrx-link" onClick={()=>approveConfirmation(r)}>Approve confirmation</button>:<span className="faint">—</span>}]}/></Section>}
      {tab==="Full & Final"&&<Section title="Full & Final Settlements" sub={settlementUrl?"MySQL-backed locked source inputs, blocker checks, calculation trace and approvals":"Locked source inputs, blocker checks, calculation trace and Finance approval"}><Table rows={settlementRows} columns={[{label:"Settlement",render:r=>r.settlement_number||r.id},{label:"Employee",render:r=><Person employee={r.employee?.name||r.employee} sub={r.employee?.employee_code}/>},{label:"Earnings",right:true,render:r=>money(r.gross_payable??(r.lastSalary+r.leaveEncashment+r.bonus+r.arrears+r.claims))},{label:"Recoveries",right:true,render:r=>money(r.total_recoveries??(r.noticeRecovery+r.loans+r.assets+r.tax))},{label:"Net payable",right:true,render:r=><b>{money(r.net_payable??0)}</b>},{label:"Blockers",render:r=>(r.clearance_blockers||r.blockers||[]).length?<Badge tone="b-red">{(r.clearance_blockers||r.blockers).length} OPEN</Badge>:<Badge tone="b-green">CLEARED</Badge>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>["initiated","hr_approved","finance_approved"].includes(r.status)?<button className="hrx-link" onClick={()=>approveSettlement(r)}>{r.status==="initiated"?"HR approve":r.status==="hr_approved"?"Finance approve":"Complete"}</button>:<span className="faint">—</span>}]}/><div className="hrx-trace"><Icon name="funnel"/><div><b>Calculation trace</b><span>Unpaid salary + leave + bonus + gratuity + claims − notice − loans − assets − tax, persisted by Laravel.</span></div></div></Section>}
      {tab==="Exit Interviews"&&<><Section title="Confidential Exit Interviews" sub={exitUrl?"MySQL-backed HR-only confidential responses with aggregate summary":"HR-only responses; management receives aggregate reporting"}><Table rows={exitRows} columns={[{label:"Interview",render:r=>r.interview_number||r.id},{label:"Employee",render:r=><Person employee={r.employee?.name||r.employee} sub={r.employee?.employee_code}/>},{label:"Primary reason",render:r=>r.separation_reason||r.reason||"—"},{label:"Rehire eligible",render:r=>r.rehire_recommendation||r.rehire||"—"},{label:"Access",render:r=><Badge tone={r.confidential_responses_visible?"b-violet":"b-slate"}>{r.confidential_responses_visible?"CONFIDENTIAL VISIBLE":"MASKED"}</Badge>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="submitted"&&lifecycleOptions?.can_review_exit_interview?<button className="hrx-link" onClick={()=>reviewExit(r)}>Review</button>:<span className="faint">—</span>}]}/></Section><div className="hrx-card-grid"><Feature icon="trend" title="Career growth" text={`${summary?.reason_counts?.career_growth||0} completed responses`}/><Feature icon="wallet" title="Compensation" text={`${summary?.reason_counts?.compensation||0} completed responses`}/><Feature icon="users" title="Submitted interviews" text={`${summary?.status_counts?.submitted||0} pending HR review`}/></div></>}
      {creatingConfirmation&&<LifecycleConfirmationModal options={lifecycleOptions} onClose={()=>setCreatingConfirmation(false)} onCreated={row=>setConfirmations(current=>[row,...current])} toast={toast}/>}
      {creatingSettlement&&<LifecycleSettlementModal options={lifecycleOptions} onClose={()=>setCreatingSettlement(false)} onCreated={row=>setSettlements(current=>[row,...current])} toast={toast}/>}
      {creatingExitInterview&&<LifecycleExitInterviewModal options={lifecycleOptions} settlements={settlements} onClose={()=>setCreatingExitInterview(false)} onCreated={row=>setExitInterviews(current=>[row,...current])} toast={toast}/>}
    </div>;
  }

  function DocumentsView({ state, operationsOptions, toast, update }) {
    const serverUrl = operationsOptions?.employee_documents_index_url;
    const [rows, setRows] = React.useState([]);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState("");
    const [busyId, setBusyId] = React.useState("");
    const [creating, setCreating] = React.useState(false);
    React.useEffect(() => {
      if (!serverUrl) return;
      let alive = true;
      setLoading(true);
      setError("");
      apiJson(collectionUrl(serverUrl, { per_page: 25, current_only: 1 }))
        .then(body => { if (alive) setRows(body.data || []); })
        .catch(err => { if (alive) { setError(err.message || "Unable to load employee documents."); toast && toast("Employee documents fallback active: " + (err.message || "load failed"), "orange"); } })
        .finally(() => { if (alive) setLoading(false); });
      return () => { alive = false; };
    }, [serverUrl, toast]);
    const data = serverUrl ? rows : [];
    const count = status => data.filter(r => String(r.status || "").toLowerCase() === status).length;
    const expiring = data.filter(r => r.is_expiring_within_30_days || /expir/i.test(String(r.status || ""))).length;
    const patchDocument=row=>setRows(current=>current.map(x=>x.id===row.id?row:x));
    const approveDocument=async row=>{const tpl=operationsOptions?.employee_document_approve_url_template;const employeeId=row.employee?.id||row.owner_id;if(!tpl||!operationsOptions?.can_approve_employee_documents||!employeeId){setError("Employee document approval requires document approver permission and an employee-owned document.");return;}try{setBusyId(`approve-${row.id}`);const url=tpl.replace("__EMPLOYEE__",employeeId).replace("__DOCUMENT__",row.id);const body=await apiJson(url,{method:"PATCH",body:JSON.stringify({approval_note:"Approved from Employee Documents register after HR verification."})});patchDocument(body.data);toast&&toast("Employee document approved in Laravel workflow.","green");update&&update((s,actor)=>addAudit(s,actor,"Approved Laravel employee document",body.data.document_number||body.data.id,"Documents"),"Employee document approved");}catch(err){setError(err.message||"Employee document approval failed.");toast&&toast("Employee document approval failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    return <div><ViewTitle title="Employee Documents" sub={serverUrl ? "MySQL-backed employee document register with versions, expiry tracking, permissions and audit-ready metadata." : "Backend Employee Documents API required; local document records are not displayed."} actions={<Badge tone={serverUrl ? "b-green" : "b-orange"}>{loading ? "Loading MySQL documents" : serverUrl ? "Laravel register" : "API required"}</Badge>}/>{error && <div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Documents workflow warning</b><span>{error}</span></div></div>}<KpiGrid><Stat label="Current" value={data.filter(r => r.is_current !== false).length} icon="folder" tone="accent"/><Stat label="Submitted" value={count("submitted")} icon="doc" tone="blue"/><Stat label="Approved" value={count("approved")} icon="check" tone="green"/><Stat label="Expiring" value={expiring} icon="alert" tone="orange"/></KpiGrid><Section title="Employee Document Register" sub={serverUrl ? "Loaded from /hr/employee-documents with employee owner and company scoping; approvals call Laravel document workflow endpoints." : "Backend API required; no local document rows are fabricated."}><Table rows={data} columns={[{label:"Document",render:r=><div><b>{r.title || r.name}</b><div className="cell-sub">{r.document_number || r.original_filename || r.type || "—"} · v{r.version || 1}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name || r.employee || "—"} sub={r.employee?.employee_code || r.employee?.department || "—"}/>},{label:"Category",render:r=>r.category?.name || r.type || "—"},{label:"Expiry",render:r=><div><b>{r.expires_on || r.expiry || "No expiry"}</b><div className="cell-sub">{r.is_expired ? "Expired" : r.is_expiring_within_30_days ? "Expiring soon" : "Current"}</div></div>},{label:"Access",render:r=>r.download_url ? <a className="hrx-link" href={r.download_url} target="_blank" rel="noreferrer">Download</a> : <span className="faint">{r.access || "Restricted"}</span>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=><div className="hrx-chip-wrap">{String(r.status||"").toLowerCase()==="submitted"&&operationsOptions?.can_approve_employee_documents?<button className="hrx-link" disabled={!!busyId} onClick={()=>approveDocument(r)}>{busyId===`approve-${r.id}`?"Approving…":"Approve"}</button>:<span className="faint">—</span>}</div>}]}/></Section></div>;
  }

  function AssetRegisterModal({ options, onClose, onCreated, toast }) {
    const companies = options?.companies || window.Builder360Server?.companies || [];
    const categories = options?.asset_categories || ["laptop", "mobile", "sim", "access_card", "vehicle", "tool", "other"];
    const [form, setForm] = React.useState({ company_id: companies[0]?.id ? String(companies[0].id) : "", asset_code: "AST-" + Date.now().toString().slice(-6), category: categories[0] || "laptop", name: "", serial_number: "", condition: "good", estimated_value: "0" });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const label = value => String(value || "").replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase());
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!options?.assets_store_url || !options?.can_create_assets) {
        setError("Asset registration requires assets manage permission.");
        return;
      }
      if (!/^[A-Z0-9-]+$/.test(form.asset_code.trim())) {
        setError("Asset code may contain only uppercase letters, numbers and hyphen.");
        return;
      }
      try {
        setBusy(true);
        const payload = {
          company_id: form.company_id ? Number(form.company_id) : null,
          asset_code: form.asset_code.trim().toUpperCase(),
          category: form.category,
          name: form.name.trim(),
          serial_number: form.serial_number.trim() || null,
          condition: form.condition,
          estimated_value: Number(form.estimated_value || 0),
          metadata: { source: "hr_assets_register_modal" },
        };
        const body = await apiJson(options.assets_store_url, { method: "POST", body: JSON.stringify(payload) });
        onCreated(body.data);
        toast && toast("Employee asset registered in Laravel workflow.", "green");
        onClose();
      } catch (err) {
        setError(err.message || "Asset registration failed.");
      } finally {
        setBusy(false);
      }
    };
    return <div className="scrim" onClick={busy ? undefined : onClose}><form className="modal hrx-modal" onClick={e => e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Register Employee Asset</h2><p>Creates an available asset through Laravel validation, company scoping, unique asset code and audit trail.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Asset not registered</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Company<select value={form.company_id} disabled={busy || companies.length <= 1} onChange={e => set("company_id", e.target.value)}>{companies.length ? companies.map(company => <option key={company.id} value={company.id}>{company.label || `${company.code} · ${company.name}`}</option>) : <option value="">Use my company scope</option>}</select></label><label>Asset Code<input required maxLength={40} value={form.asset_code} disabled={busy} onChange={e => set("asset_code", e.target.value.toUpperCase())} placeholder="AST-LAP-0001"/></label><label>Category<select value={form.category} disabled={busy} onChange={e => set("category", e.target.value)}>{categories.map(category => <option key={category} value={category}>{label(category)}</option>)}</select></label><label>Asset Name<input required maxLength={160} value={form.name} disabled={busy} onChange={e => set("name", e.target.value)} placeholder="Dell Latitude laptop"/></label><label>Serial Number<input maxLength={120} value={form.serial_number} disabled={busy} onChange={e => set("serial_number", e.target.value)} placeholder="Manufacturer serial / IMEI"/></label><label>Condition<select value={form.condition} disabled={busy} onChange={e => set("condition", e.target.value)}>{["new","good","fair","damaged"].map(condition => <option key={condition} value={condition}>{label(condition)}</option>)}</select></label><label>Estimated Value<input type="number" min="0" step="0.01" value={form.estimated_value} disabled={busy} onChange={e => set("estimated_value", e.target.value)} /></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy || !options?.assets_store_url}><Icon name="box" size={15}/>{busy ? "Registering…" : "Register Asset"}</button></div></form></div>;
  }

  function AssetsView({ state, operationsOptions, toast, update }) {
    const serverUrl = operationsOptions?.assets_index_url;
    const [rows, setRows] = React.useState([]);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState("");
    const [busyId, setBusyId] = React.useState("");
    const [creating, setCreating] = React.useState(false);
    React.useEffect(() => {
      if (!serverUrl) return;
      let alive = true;
      setLoading(true);
      setError("");
      apiJson(collectionUrl(serverUrl, { per_page: 25 }))
        .then(body => { if (alive) setRows(body.data || []); })
        .catch(err => { if (alive) { setError(err.message || "Unable to load employee assets."); toast && toast("Assets register fallback active: " + (err.message || "load failed"), "orange"); } })
        .finally(() => { if (alive) setLoading(false); });
      return () => { alive = false; };
    }, [serverUrl, toast]);
    const data = serverUrl ? rows : [];
    const count = status => data.filter(r => String(r.status || "").toLowerCase() === status).length;
    const patchAsset=row=>setRows(current=>current.map(x=>x.id===row.id?row:x));
    const employees=operationsOptions?.asset_assignable_employees||[];
    const assignableFor=row=>{
      const companyId=String(row.company?.id||row.company_id||"");
      return employees.find(employee=>!companyId||String(employee.company_id)===companyId)||employees[0]||null;
    };
    const today=()=>new Date().toISOString().slice(0,10);
    const safeCondition=row=>["new","good","fair","damaged"].includes(String(row.condition||"").toLowerCase())?String(row.condition).toLowerCase():"good";
    const assignAsset=async row=>{const tpl=operationsOptions?.asset_assign_url_template;const employee=assignableFor(row);if(!tpl||!operationsOptions?.can_assign_assets||!employee){setError("Asset assignment requires assets manage permission and an active scoped employee.");return;}try{setBusyId(`assign-${row.id}`);const body=await apiJson(tpl.replace("__ASSET__",row.id),{method:"PATCH",body:JSON.stringify({employee_id:employee.id,assigned_on:today(),note:`Assigned from Employee Asset Management to ${employee.employee_code||employee.name}.`})});patchAsset(body.data);toast&&toast("Employee asset assigned in Laravel workflow.","green");update&&update((s,actor)=>addAudit(s,actor,"Assigned Laravel employee asset",body.data.asset_code||body.data.id,"Assets"),"Employee asset assigned");}catch(err){setError(err.message||"Asset assignment failed.");toast&&toast("Asset assignment failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    const recoverAsset=async row=>{const tpl=operationsOptions?.asset_recover_url_template;if(!tpl||!operationsOptions?.can_recover_assets){setError("Asset recovery requires assets manage permission.");return;}try{setBusyId(`recover-${row.id}`);const body=await apiJson(tpl.replace("__ASSET__",row.id),{method:"PATCH",body:JSON.stringify({recovered_on:today(),condition:safeCondition(row),status:"recovered",note:"Recovered from Employee Asset Management after physical verification."})});patchAsset(body.data);toast&&toast("Employee asset recovered in Laravel workflow.","green");update&&update((s,actor)=>addAudit(s,actor,"Recovered Laravel employee asset",body.data.asset_code||body.data.id,"Assets"),"Employee asset recovered");}catch(err){setError(err.message||"Asset recovery failed.");toast&&toast("Asset recovery failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    const onAssetCreated=asset=>{setRows(current=>[asset,...current.filter(row=>row.id!==asset.id)]);toast&&toast("Employee asset registered in Laravel workflow.","green");};
    return <div><ViewTitle title="Employee Asset Management" sub={serverUrl ? "MySQL-backed employee asset register with registration, assignment, recovery, condition and audit status." : "Backend Employee Assets API required; local asset rows are not displayed."} actions={[<Badge key="s" tone={serverUrl ? "b-green" : "b-orange"}>{loading ? "Loading MySQL assets" : serverUrl ? "Laravel register" : "API required"}</Badge>,<Button key="n" icon="plus" variant="primary" sm onClick={()=>operationsOptions?.can_create_assets&&operationsOptions?.assets_store_url?setCreating(true):setError("Asset registration requires assets manage permission and company scope.")}>Register Asset</Button>]}/>{error && <div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Assets workflow warning</b><span>{error}</span></div></div>}<KpiGrid><Stat label="Available" value={count("available")} icon="box" tone="accent"/><Stat label="Assigned" value={count("assigned")} icon="users" tone="blue"/><Stat label="Recovered" value={count("recovered")} icon="check" tone="green"/><Stat label="Exceptions" value={count("lost") + count("retired")} icon="alert" tone="red"/></KpiGrid><Section title="Asset Register" sub={serverUrl ? "Loaded from /hr/assets with role and company scoping; registration, assignment and recovery call Laravel workflow endpoints." : "Backend API required; no local asset rows are fabricated."}><Table rows={data} columns={[{label:"Asset",render:r=><div><b>{r.name}</b><div className="cell-sub">{r.asset_code || r.code}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name || r.employee || "Unassigned"} sub={r.employee?.department || r.employee?.designation || "—"}/>},{label:"Category",render:r=>r.category || "—"},{label:"Assigned",render:r=>r.assigned_on || r.issued || "—"},{label:"Condition",render:r=>r.condition || "—"},{label:"Value",right:true,render:r=><span className="mono">{money(r.estimated_value || 0)}</span>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Workflow",render:r=><span className="cell-sub">{(r.workflow_history || []).length ? `${r.workflow_history.length} event(s)` : "—"}</span>},{label:"Action",render:r=><div className="hrx-chip-wrap">{String(r.status||"").toLowerCase()==="available"&&operationsOptions?.can_assign_assets?<button className="hrx-link" disabled={!!busyId} onClick={()=>assignAsset(r)}>{busyId===`assign-${r.id}`?"Assigning…":"Assign"}</button>:null}{String(r.status||"").toLowerCase()==="assigned"&&operationsOptions?.can_recover_assets?<button className="hrx-link" disabled={!!busyId} onClick={()=>recoverAsset(r)}>{busyId===`recover-${r.id}`?"Recovering…":"Recover"}</button>:null}{!(String(r.status||"").toLowerCase()==="available"&&operationsOptions?.can_assign_assets)&&!(String(r.status||"").toLowerCase()==="assigned"&&operationsOptions?.can_recover_assets)?<span className="faint">—</span>:null}</div>}]}/></Section>{creating&&<AssetRegisterModal options={operationsOptions} onClose={()=>setCreating(false)} onCreated={onAssetCreated} toast={toast}/>}</div>;
  }
  function ClaimsView({ state, operationsOptions, toast, update }) {
    const serverUrl = operationsOptions?.claims_index_url;
    const [rows, setRows] = React.useState([]);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState("");
    const [busyId, setBusyId] = React.useState("");
    React.useEffect(() => {
      if (!serverUrl) return;
      let alive = true;
      setLoading(true);
      setError("");
      apiJson(collectionUrl(serverUrl, { per_page: 25 }))
        .then(body => { if (alive) setRows(body.data || []); })
        .catch(err => { if (alive) { setError(err.message || "Unable to load employee claims."); toast && toast("Claims register API issue: " + (err.message || "load failed"), "orange"); } })
        .finally(() => { if (alive) setLoading(false); });
      return () => { alive = false; };
    }, [serverUrl, toast]);
    const data = serverUrl ? rows : [];
    const count = status => data.filter(r => String(r.status || "").toLowerCase() === status).length;
    const patchClaim=row=>setRows(current=>current.map(x=>x.id===row.id?row:x));
    const approveClaim=async row=>{const tpl=operationsOptions?.claim_approve_url_template;if(!tpl||!operationsOptions?.can_approve_claims){setError("Claim approval requires claims approval permission.");return;}try{setBusyId(`approve-${row.id}`);const body=await apiJson(tpl.replace("__CLAIM__",row.id),{method:"PATCH",body:JSON.stringify({approved_amount:Number(row.amount||row.approved_amount||0),decision_note:"Approved from HR Claims workspace after policy verification."})});patchClaim(body.data);toast&&toast("Expense claim approved in Laravel workflow.","green");update&&update((s,actor)=>addAudit(s,actor,"Approved Laravel expense claim",body.data.claim_number||body.data.id,"Claims"),"Expense claim approved");}catch(err){setError(err.message||"Claim approval failed.");toast&&toast("Claim approval failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    const rejectClaim=async row=>{const tpl=operationsOptions?.claim_reject_url_template;if(!tpl||!operationsOptions?.can_approve_claims){setError("Claim rejection requires claims approval permission.");return;}try{setBusyId(`reject-${row.id}`);const body=await apiJson(tpl.replace("__CLAIM__",row.id),{method:"PATCH",body:JSON.stringify({decision_note:"Rejected from HR Claims workspace after policy verification."})});patchClaim(body.data);toast&&toast("Expense claim rejected in Laravel workflow.","green");}catch(err){setError(err.message||"Claim rejection failed.");toast&&toast("Claim rejection failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    const payClaim=async row=>{const tpl=operationsOptions?.claim_pay_url_template;if(!tpl||!operationsOptions?.can_pay_claims){setError("Claim payment requires finance approval permission.");return;}try{setBusyId(`pay-${row.id}`);const body=await apiJson(tpl.replace("__CLAIM__",row.id),{method:"PATCH",body:JSON.stringify({payment_reference:`CLM-PAY-${row.claim_number||row.id}`,note:"Marked paid from HR Claims workspace."})});patchClaim(body.data);toast&&toast("Expense claim marked paid in Laravel workflow.","green");}catch(err){setError(err.message||"Claim payment failed.");toast&&toast("Claim payment failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    return <div><ViewTitle title="Reimbursements & Claims" sub={serverUrl ? "MySQL-backed employee claims register with policy, workflow and finance settlement status." : "Policy validation, attachments, project allocation, approval and finance/payroll settlement."} actions={<Badge tone={serverUrl ? "b-green" : "b-orange"}>{loading ? "Loading MySQL claims" : serverUrl ? "Laravel register" : "API required"}</Badge>}/>{error && <div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Claims workflow warning</b><span>{error}</span></div></div>}<KpiGrid><Stat label="Submitted" value={count("submitted")} icon="receipt" tone="accent"/><Stat label="Approved" value={count("approved")} icon="check" tone="green"/><Stat label="Paid" value={count("paid")} icon="wallet" tone="blue"/><Stat label="Rejected" value={count("rejected")} icon="alert" tone="red"/></KpiGrid><Section title="Claims Register" sub={serverUrl ? "Loaded from /hr/expense-claims with role and company scoping; approval, rejection and payment call Laravel workflow endpoints." : "Backend API required; no local rows are fabricated."}><Table rows={data} columns={[{label:"Claim",render:r=><div><b>{r.claim_number || r.id}</b><div className="cell-sub">{r.claim_date || "—"}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name || r.employee} sub={r.employee?.department || r.project || "—"}/>},{label:"Type",render:r=>r.claim_type || r.type || "—"},{label:"Amount",right:true,render:r=><b className="mono">{money(r.approved_amount || r.amount)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Workflow",render:r=><span className="cell-sub">{(r.workflow_history || []).length ? `${r.workflow_history.length} event(s)` : "—"}</span>},{label:"Action",render:r=><div className="hrx-chip-wrap">{r.status==="submitted"&&operationsOptions?.can_approve_claims?<><button className="hrx-link" disabled={!!busyId} onClick={()=>approveClaim(r)}>{busyId===`approve-${r.id}`?"Approving…":"Approve"}</button><button className="hrx-link" disabled={!!busyId} onClick={()=>rejectClaim(r)}>{busyId===`reject-${r.id}`?"Rejecting…":"Reject"}</button></>:null}{r.status==="approved"&&operationsOptions?.can_pay_claims?<button className="hrx-link" disabled={!!busyId} onClick={()=>payClaim(r)}>{busyId===`pay-${r.id}`?"Paying…":"Mark paid"}</button>:null}{!(r.status==="submitted"&&operationsOptions?.can_approve_claims)&&!(r.status==="approved"&&operationsOptions?.can_pay_claims)?<span className="faint">—</span>:null}</div>}]}/></Section></div>;
  }
  function LoansView({ state, operationsOptions, toast, update }) {
    const serverUrl = operationsOptions?.loans_index_url;
    const [rows, setRows] = React.useState([]);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState("");
    const [busyId, setBusyId] = React.useState("");
    React.useEffect(() => {
      if (!serverUrl) return;
      let alive = true;
      setLoading(true);
      setError("");
      apiJson(collectionUrl(serverUrl, { per_page: 25 }))
        .then(body => { if (alive) setRows(body.data || []); })
        .catch(err => { if (alive) { setError(err.message || "Unable to load employee loans."); toast && toast("Loans register API issue: " + (err.message || "load failed"), "orange"); } })
        .finally(() => { if (alive) setLoading(false); });
      return () => { alive = false; };
    }, [serverUrl, toast]);
    const data = serverUrl ? rows : [];
    const count = status => data.filter(r => String(r.status || "").toLowerCase() === status).length;
    const patchLoan=row=>setRows(current=>current.map(x=>x.id===row.id?row:x));
    const nextMonthStart=()=>{const d=new Date();d.setMonth(d.getMonth()+1,1);return d.toISOString().slice(0,10);};
    const approveLoan=async row=>{const tpl=operationsOptions?.loan_approve_url_template;if(!tpl||!operationsOptions?.can_approve_loans){setError("Loan approval requires loan approval permission.");return;}try{setBusyId(`approve-${row.id}`);const body=await apiJson(tpl.replace("__LOAN__",row.id),{method:"PATCH",body:JSON.stringify({approved_amount:Number(row.principal_amount||row.principal||row.approved_amount||0),repayment_starts_on:nextMonthStart(),decision_note:"Approved from HR Loans workspace after policy verification."})});patchLoan(body.data);toast&&toast("Employee loan approved in Laravel workflow.","green");update&&update((s,actor)=>addAudit(s,actor,"Approved Laravel employee loan",body.data.loan_number||body.data.id,"Loans"),"Employee loan approved");}catch(err){setError(err.message||"Loan approval failed.");toast&&toast("Loan approval failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    const rejectLoan=async row=>{const tpl=operationsOptions?.loan_reject_url_template;if(!tpl||!operationsOptions?.can_approve_loans){setError("Loan rejection requires loan approval permission.");return;}try{setBusyId(`reject-${row.id}`);const body=await apiJson(tpl.replace("__LOAN__",row.id),{method:"PATCH",body:JSON.stringify({decision_note:"Rejected from HR Loans workspace after policy verification."})});patchLoan(body.data);toast&&toast("Employee loan rejected in Laravel workflow.","green");}catch(err){setError(err.message||"Loan rejection failed.");toast&&toast("Loan rejection failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    const disburseLoan=async row=>{const tpl=operationsOptions?.loan_disburse_url_template;if(!tpl||!operationsOptions?.can_disburse_loans){setError("Loan disbursement requires finance approval permission.");return;}try{setBusyId(`disburse-${row.id}`);const body=await apiJson(tpl.replace("__LOAN__",row.id),{method:"PATCH",body:JSON.stringify({payment_reference:`LOAN-DISB-${row.loan_number||row.id}`,note:"Disbursed from HR Loans workspace."})});patchLoan(body.data);toast&&toast("Employee loan disbursed in Laravel workflow.","green");}catch(err){setError(err.message||"Loan disbursement failed.");toast&&toast("Loan disbursement failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    return <div><ViewTitle title="Loans & Advances" sub={serverUrl ? "MySQL-backed employee loans register with approval, disbursement and payroll recovery status." : "Disbursement, EMI schedule, payroll deduction, outstanding balance and F&F recovery."} actions={<Badge tone={serverUrl ? "b-green" : "b-orange"}>{loading ? "Loading MySQL loans" : serverUrl ? "Laravel register" : "API required"}</Badge>}/>{error && <div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Loans workflow warning</b><span>{error}</span></div></div>}<KpiGrid><Stat label="Submitted" value={count("submitted")} icon="rupee" tone="accent"/><Stat label="Approved" value={count("approved")} icon="check" tone="green"/><Stat label="Disbursed" value={count("disbursed")} icon="wallet" tone="blue"/><Stat label="Closed" value={count("closed")} icon="lock" tone="slate"/></KpiGrid><Section title="Loans Register" sub={serverUrl ? "Loaded from /hr/loans with role and company scoping; approval, rejection and disbursement call Laravel workflow endpoints." : "Backend API required; no local rows are fabricated."}><Table rows={data} columns={[{label:"Loan",render:r=><div><b>{r.loan_number || r.id}</b><div className="cell-sub">{r.requested_on || "—"}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name || r.employee} sub={r.employee?.department || "—"}/>},{label:"Type",render:r=>r.loan_type || r.type || "—"},{label:"Principal",right:true,render:r=><span className="mono">{money(r.principal_amount || r.principal)}</span>},{label:"EMI",right:true,render:r=><span className="mono">{money(r.monthly_installment || r.emi)}</span>},{label:"Outstanding",right:true,render:r=><b className="mono">{money(r.outstanding || r.approved_amount || r.principal_amount || r.principal)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=><div className="hrx-chip-wrap">{r.status==="submitted"&&operationsOptions?.can_approve_loans?<><button className="hrx-link" disabled={!!busyId} onClick={()=>approveLoan(r)}>{busyId===`approve-${r.id}`?"Approving…":"Approve"}</button><button className="hrx-link" disabled={!!busyId} onClick={()=>rejectLoan(r)}>{busyId===`reject-${r.id}`?"Rejecting…":"Reject"}</button></>:null}{r.status==="approved"&&operationsOptions?.can_disburse_loans?<button className="hrx-link" disabled={!!busyId} onClick={()=>disburseLoan(r)}>{busyId===`disburse-${r.id}`?"Disbursing…":"Disburse"}</button>:null}{!(r.status==="submitted"&&operationsOptions?.can_approve_loans)&&!(r.status==="approved"&&operationsOptions?.can_disburse_loans)?<span className="faint">—</span>:null}</div>}]}/></Section></div>;
  }
  function HelpdeskView({ state, operationsOptions, toast, update }) {
    const serverUrl = operationsOptions?.helpdesk_index_url;
    const [rows, setRows] = React.useState([]);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState("");
    const [busyId, setBusyId] = React.useState("");
    React.useEffect(() => {
      if (!serverUrl) return;
      let alive = true;
      setLoading(true);
      setError("");
      apiJson(collectionUrl(serverUrl, { per_page: 25 }))
        .then(body => { if (alive) setRows(body.data || []); })
        .catch(err => { if (alive) { setError(err.message || "Unable to load HR helpdesk tickets."); toast && toast("Helpdesk queue API issue: " + (err.message || "load failed"), "orange"); } })
        .finally(() => { if (alive) setLoading(false); });
      return () => { alive = false; };
    }, [serverUrl, toast]);
    const data = serverUrl ? rows : [];
    const count = status => data.filter(r => String(r.status || "").toLowerCase() === status).length;
    const openCount = count("open");
    const assignedCount = count("assigned");
    const resolvedCount = count("resolved");
    const criticalCount = data.filter(r => String(r.priority || "").toLowerCase() === "critical").length;
    const patchTicket=row=>setRows(current=>current.map(x=>x.id===row.id?row:x));
    const firstAssignee=operationsOptions?.helpdesk_assignees?.[0]||null;
    const assignTicket=async row=>{const tpl=operationsOptions?.helpdesk_assign_url_template;if(!tpl||!operationsOptions?.can_assign_helpdesk||!firstAssignee){setError("Helpdesk assignment requires a permitted assignee in the same company scope.");return;}try{setBusyId(`assign-${row.id}`);const body=await apiJson(tpl.replace("__TICKET__",row.id),{method:"PATCH",body:JSON.stringify({assigned_to_user_id:firstAssignee.id,note:"Assigned from HR Helpdesk workspace."})});patchTicket(body.data);toast&&toast("HR helpdesk ticket assigned in Laravel workflow.","green");update&&update((s,actor)=>addAudit(s,actor,"Assigned Laravel HR helpdesk ticket",body.data.ticket_number||body.data.id,"Helpdesk"),"Helpdesk ticket assigned");}catch(err){setError(err.message||"Helpdesk assignment failed.");toast&&toast("Helpdesk assignment failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    const resolveTicket=async row=>{const tpl=operationsOptions?.helpdesk_resolve_url_template;if(!tpl||!operationsOptions?.can_resolve_helpdesk){setError("Helpdesk resolution requires helpdesk manage permission.");return;}try{setBusyId(`resolve-${row.id}`);const body=await apiJson(tpl.replace("__TICKET__",row.id),{method:"PATCH",body:JSON.stringify({resolution_summary:"Resolved from HR Helpdesk workspace after employee request verification."})});patchTicket(body.data);toast&&toast("HR helpdesk ticket resolved in Laravel workflow.","green");}catch(err){setError(err.message||"Helpdesk resolution failed.");toast&&toast("Helpdesk resolution failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    const closeTicket=async row=>{const tpl=operationsOptions?.helpdesk_close_url_template;if(!tpl||!operationsOptions?.can_close_helpdesk){setError("Helpdesk closure requires helpdesk manage permission and a resolved ticket.");return;}try{setBusyId(`close-${row.id}`);const body=await apiJson(tpl.replace("__TICKET__",row.id),{method:"PATCH",body:JSON.stringify({note:"Closed from HR Helpdesk workspace after resolution review."})});patchTicket(body.data);toast&&toast("HR helpdesk ticket closed in Laravel workflow.","green");}catch(err){setError(err.message||"Helpdesk closure failed.");toast&&toast("Helpdesk closure failed: "+(err.message||"request failed"),"orange");}finally{setBusyId("");}};
    const onTicketCreated=ticket=>{setRows(current=>[ticket,...current.filter(row=>row.id!==ticket.id)]);toast&&toast("HR helpdesk ticket created in Laravel workflow.","green");};
    return <div><ViewTitle title="HR Helpdesk" sub={serverUrl ? "MySQL-backed HR helpdesk queue with employee, category, priority, assignment and lifecycle status." : "Employee requests with assignment, SLA, escalation, comments and resolution."} actions={[<Badge key="s" tone={serverUrl ? "b-green" : "b-orange"}>{loading ? "Loading MySQL tickets" : serverUrl ? "Laravel queue" : "API required"}</Badge>,<Button key="n" icon="plus" variant="primary" sm onClick={()=>operationsOptions?.can_create_helpdesk&&operationsOptions?.helpdesk_store_url?setCreating(true):setError("Helpdesk ticket creation requires create permission and a scoped employee.")}>New Ticket</Button>]}/>{error && <div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Helpdesk workflow warning</b><span>{error}</span></div></div>}<KpiGrid><Stat label="Open" value={openCount} icon="headset" tone="accent"/><Stat label="Assigned" value={assignedCount} icon="trend" tone="blue"/><Stat label="Critical" value={criticalCount} icon="alert" tone="red"/><Stat label="Resolved" value={resolvedCount} icon="check" tone="green"/></KpiGrid><Section title="Ticket Queue" sub={serverUrl ? "Loaded from /hr/helpdesk-tickets with role and company scoping; create, assignment, resolution and closure call Laravel workflow endpoints." : "Salary, attendance, leave, documents, statutory, asset and custom requests"}><Table rows={data} columns={[{label:"Ticket",render:r=><div><b>{r.ticket_number || r.id}</b><div className="cell-sub">{r.subject || "—"}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name || r.employee} sub={r.employee?.department || "—"}/>},{label:"Category",key:"category"},{label:"Priority",render:r=><StatePill>{r.priority}</StatePill>},{label:"Assigned",render:r=>r.assigned_to?.name || r.assignee || "Unassigned"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Workflow",render:r=><span className="cell-sub">{(r.workflow_history || []).length ? `${r.workflow_history.length} event(s)` : "—"}</span>},{label:"Action",render:r=><div className="hrx-chip-wrap">{r.status==="open"&&operationsOptions?.can_assign_helpdesk?<button className="hrx-link" disabled={!!busyId} onClick={()=>assignTicket(r)}>{busyId===`assign-${r.id}`?"Assigning…":"Assign"}</button>:null}{["open","assigned"].includes(r.status)&&operationsOptions?.can_resolve_helpdesk?<button className="hrx-link" disabled={!!busyId} onClick={()=>resolveTicket(r)}>{busyId===`resolve-${r.id}`?"Resolving…":"Resolve"}</button>:null}{r.status==="resolved"&&operationsOptions?.can_close_helpdesk?<button className="hrx-link" disabled={!!busyId} onClick={()=>closeTicket(r)}>{busyId===`close-${r.id}`?"Closing…":"Close"}</button>:null}{!["open","assigned","resolved"].includes(r.status)||(!operationsOptions?.can_assign_helpdesk&&!operationsOptions?.can_resolve_helpdesk&&!operationsOptions?.can_close_helpdesk)?<span className="faint">—</span>:null}</div>}]}/></Section>{creating&&<HelpdeskRequestModal options={operationsOptions} onClose={()=>setCreating(false)} onCreated={onTicketCreated} toast={toast}/>}</div>;
  }

  function ComplianceView({ state, update }) {
    return <div><ViewTitle title="Compliance Center" sub="Compliance API required; no local statutory state packs or Form 16 rows are fabricated." actions={<Button icon="plus" variant="primary" sm onClick={() => unavailableAction(update, "Compliance rule version creation", "Compliance")}>Backend compliance API required</Button>}/><div className="hrx-warning"><Icon name="alert"/><div><b>Backend compliance configuration required</b><span>Statutory rule packs, tax documents, verification evidence and due-date monitoring must come from governed Laravel SystemSetting and payroll tax-document records.</span></div></div><Section title="Compliance workspace unavailable" sub="No fallback statutory records are displayed"><EmptyPanel icon="shield" title="No compliance data loaded" text="Use the Laravel-backed Compliance Center to load governed rule packs, client/expert validation status, Form 16 records and audit history."/></Section></div>;
  }

  function ComplianceTaxDocumentModal({ options, onClose, onCreated, toast }) {
    const employees=options?.employees||[];
    const currentYear=new Date().getFullYear();
    const [form,setForm]=React.useState({employee_id:employees[0]?.id||"",financial_year:`${currentYear-1}-${currentYear}`,force_new_version:false});
    const [busy,setBusy]=React.useState(false),[error,setError]=React.useState("");
    const set=(key,value)=>setForm(current=>({...current,[key]:value}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.tax_documents_store_url||!options?.can_generate_tax_document){setError("Your role cannot generate tax documents.");return;}if(!form.employee_id||!/^\d{4}-\d{4}$/.test(form.financial_year)){setError("Employee and financial year in YYYY-YYYY format are required.");return;}try{setBusy(true);const body=await apiJson(options.tax_documents_store_url,{method:"POST",body:JSON.stringify({employee_id:Number(form.employee_id),financial_year:form.financial_year,document_type:"form_16",force_new_version:!!form.force_new_version})});toast&&toast("Form 16 generated in Laravel tax workflow.","green");onCreated&&onCreated(body.data);onClose();}catch(err){setError(err.message||"Form 16 generation failed.");toast&&toast("Form 16 generation failed: "+(err.message||"request failed"),"orange");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Generate Form 16</h2><p>Uses the Laravel tax-document service. Generation requires locked payroll year and verified tax configuration.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Generation blocked</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee<SearchablePeoplePicker items={employees} selected={form.employee_id} mode="single" required disabled={busy||!employees.length} placeholder="Search employee name, code, department..." emptyText="No matching employees" onChange={value=>set("employee_id",value||"")} getId={e=>e.id} getLabel={e=>e.name||e.label||"Employee"} getSubLabel={e=>e.label&&e.name?e.label:[e.employee_code,e.department,e.designation,e.email].filter(Boolean).join(" · ")}/></label><label>Financial year<input required pattern="\d{4}-\d{4}" value={form.financial_year} disabled={busy} onChange={e=>set("financial_year",e.target.value)} placeholder="2025-2026"/></label><label className="hrx-check"><input type="checkbox" checked={form.force_new_version} disabled={busy} onChange={e=>set("force_new_version",e.target.checked)}/><span>Force new version if one already exists</span></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="doc" size={15}/>{busy?"Generating...":"Generate"}</button></div></form></div>;
  }

  function ComplianceRuleSettingModal({ options, sourceSetting, onClose, onCreated, toast }) {
    const companies=window.Builder360Server?.companies||[];
    const keys=options?.setting_keys||[];
    const defaultKey=sourceSetting?.setting_key||keys[0]?.value||"payroll.tax_rules";
    const presetFor=key=>sourceSetting?.setting_key===key&&sourceSetting?.value?sourceSetting.value:(options?.presets?.[key]||options?.presets?.default_hr_statutory||{verified:false,statutory_validation_required:true,approval_chain:["compliance_preparer","compliance_approver"]});
    const [form,setForm]=React.useState({company_id:sourceSetting?.company?.id||companies[0]?.id||"",setting_key:defaultKey,label:sourceSetting?.label||defaultKey.replaceAll("."," ").replaceAll("_"," "),description:sourceSetting?.description||"Compliance Center statutory rule-pack draft. Production use requires client/expert validation.",effective_from:new Date().toISOString().slice(0,10),value:JSON.stringify(presetFor(defaultKey),null,2)});
    const [busy,setBusy]=React.useState(false),[error,setError]=React.useState("");
    const set=(k,v)=>setForm(c=>({...c,[k]:v}));
    const changeKey=key=>setForm(c=>({...c,setting_key:key,label:key.replaceAll("."," ").replaceAll("_"," "),value:JSON.stringify(presetFor(key),null,2)}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.store_url||!options?.can_create){setError("Your role cannot create compliance rule drafts.");return;}let parsed;try{parsed=JSON.parse(form.value);}catch(e){setError("Rule value must be valid JSON.");return;}try{setBusy(true);const payload={setting_key:form.setting_key,label:form.label,description:form.description,effective_from:form.effective_from,value:parsed,metadata:{source:"hr_compliance_center"}};if(form.company_id)payload.company_id=Number(form.company_id);const body=await apiJson(options.store_url,{method:"POST",body:JSON.stringify(payload)});toast&&toast("Compliance rule draft created in Laravel workflow.","green");onCreated&&onCreated(body.data);onClose();}catch(err){setError(err.message||"Compliance rule draft could not be created.");toast&&toast("Compliance draft failed: "+(err.message||"request failed"),"orange");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>{sourceSetting?"Revise Compliance Rule":"New Compliance Rule Version"}</h2><p>Creates a restricted, versioned System Setting draft for statutory and tax rule packs with workflow, audit and company scope.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Rule draft blocked</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Company / scope<select value={form.company_id} disabled={busy} onChange={e=>set("company_id",e.target.value)}><option value="">Global scope</option>{companies.map(c=><option key={c.id} value={c.id}>{c.label||`${c.code} · ${c.name}`}</option>)}</select></label><label>Rule key<select required value={form.setting_key} disabled={busy||!!sourceSetting} onChange={e=>changeKey(e.target.value)}>{keys.map(k=><option key={k.value} value={k.value}>{k.label}</option>)}</select></label><label>Effective from<input required type="date" value={form.effective_from} disabled={busy} onChange={e=>set("effective_from",e.target.value)}/></label><label style={{gridColumn:"1 / -1"}}>Label<input required maxLength={255} value={form.label} disabled={busy} onChange={e=>set("label",e.target.value)}/></label><label style={{gridColumn:"1 / -1"}}>Description<textarea maxLength={5000} value={form.description} disabled={busy} onChange={e=>set("description",e.target.value)}/></label><label style={{gridColumn:"1 / -1"}}>Rule JSON<textarea required rows={12} value={form.value} disabled={busy} onChange={e=>set("value",e.target.value)} spellCheck="false"/></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="shield" size={15}/>{busy?"Creating...":"Create Draft"}</button></div></form></div>;
  }

  function ComplianceViewV2({ state, update, toast, payrollOptions, complianceOptions }) {
    const [rows,setRows]=React.useState([]);
    const [ruleRows,setRuleRows]=React.useState(complianceOptions?.settings||[]);
    const [loading,setLoading]=React.useState(false),[ruleLoading,setRuleLoading]=React.useState(false),[error,setError]=React.useState(""),[creating,setCreating]=React.useState(false),[creatingRule,setCreatingRule]=React.useState(false),[ruleSource,setRuleSource]=React.useState(null),[compareKey,setCompareKey]=React.useState("");
    const taxUrl=payrollOptions?.tax_documents_index_url;
    React.useEffect(()=>{if(!taxUrl)return;let alive=true;setLoading(true);setError("");apiJson(collectionUrl(taxUrl,{per_page:50,document_type:"form_16"})).then(body=>{if(alive)setRows(body.data||[]);}).catch(err=>{if(alive){setError(err.message||"Unable to load tax documents.");toast&&toast("Compliance tax register fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[taxUrl,toast]);
    React.useEffect(()=>{if(!complianceOptions?.index_url)return;let alive=true;setRuleLoading(true);apiJson(collectionUrl(complianceOptions.index_url,{page:1})).then(body=>{if(alive)setRuleRows(body.data||[]);}).catch(err=>{if(alive){setError(err.message||"Unable to load compliance rule settings.");toast&&toast("Compliance rule register fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setRuleLoading(false);});return()=>{alive=false;};},[complianceOptions?.index_url,toast]);
    const data=taxUrl?rows:[];
    const issueTax=async r=>{const tpl=payrollOptions?.tax_document_issue_url_template;if(!tpl||!payrollOptions?.can_issue_tax_document||r.status!=="generated"){unavailableAction(update,"Compliance tax document issue","Compliance");return;}try{const body=await apiJson(tpl.replace("__DOCUMENT__",r.id),{method:"PATCH",body:JSON.stringify({issue_reference:`ISSUE-${r.document_number||r.id}`,note:"Issued from HR Compliance Center."})});setRows(current=>current.map(x=>x.id===body.data.id?body.data:x));toast&&toast("Form 16 issued in Laravel compliance workflow.","green");}catch(err){setError(err.message||"Tax document issue failed.");toast&&toast("Form 16 issue failed: "+(err.message||"request failed"),"orange");}};
    const openRule=(row=null)=>{if(complianceOptions?.can_create&&complianceOptions?.store_url){setRuleSource(row);setCreatingRule(true);return;}unavailableAction(update,"Compliance rule version creation","Compliance");};
    const approveRule=async row=>{const tpl=complianceOptions?.approve_url_template;if(!tpl||!complianceOptions?.can_approve||row.status!=="draft"){unavailableAction(update,"Compliance rule approval","Compliance");return;}try{const body=await apiJson(tpl.replace("__SETTING__",row.id),{method:"PATCH",body:JSON.stringify({note:"Approved from HR Compliance Center after statutory validation review."})});setRuleRows(current=>current.map(x=>x.id===body.data.id?body.data:x));toast&&toast("Compliance rule approved in Laravel workflow.","green");}catch(err){setError(err.message||"Compliance rule approval failed.");toast&&toast("Compliance approval failed: "+(err.message||"request failed"),"orange");}};
    const latestRules=ruleRows.length?ruleRows:(complianceOptions?.settings||[]);
    const compared=compareKey?latestRules.filter(r=>r.setting_key===compareKey).sort((a,b)=>(b.version||0)-(a.version||0)).slice(0,5):[];
    return <div><ViewTitle title="Compliance Center" sub="Effective-dated statutory rule packs plus MySQL-backed Form 16/tax document controls." actions={[<Button key="r" icon="plus" variant="primary" sm onClick={()=>openRule()}>New Rule Version</Button>,<Button key="f" icon="doc" sm onClick={()=>payrollOptions?.can_generate_tax_document?setCreating(true):unavailableAction(update,"Form 16 generation","Compliance")}>Generate Form 16</Button>]}/><div className="hrx-warning"><Icon name="alert"/><div><b>Statutory applicability requires factual clarification.</b><span>Rule packs are configurable records. Production rates, thresholds and filing logic require client-appointed expert validation.</span></div></div>{error&&<div className="hrx-warning" style={{marginTop:12}}><Icon name="alert"/><div><b>Compliance register warning</b><span>{error}</span></div></div>}<div className="hrx-toolbar"><Badge tone={complianceOptions?.source==="laravel-sqlite"?"b-green":"b-orange"}>{ruleLoading?"Loading rule packs":complianceOptions?.source==="laravel-sqlite"?"Laravel rule packs":"Compliance API required"}</Badge><Badge tone="b-slate">{latestRules.filter(r=>r.status==="active").length} active</Badge><Badge tone="b-orange">{latestRules.filter(r=>r.status==="draft").length} draft</Badge><Badge tone={complianceOptions?.can_approve?"b-green":"b-slate"}>{complianceOptions?.can_approve?"COMPLIANCE APPROVER":"VIEW ONLY"}</Badge></div><div className="hrx-card-grid">{latestRules.length?latestRules.slice(0,6).map(r=><div className="card card-pad hrx-state-card" key={r.id||`${r.setting_key}-${r.version}`}><div className="row between"><div className="hrx-state big">{String(r.company?.code||"GLOBAL").slice(0,3).toUpperCase()}</div><StatePill>{r.status}</StatePill></div><h3>{r.label}</h3><p>{r.setting_key} · v{r.version||1} · effective {r.effective_from||"Immediate"}</p><div className="hrx-chip-wrap">{Object.keys(r.value||{}).slice(0,4).map(x=><span className="hrx-chip" key={x}>{x}</span>)}</div><div className="hrx-meta-row"><span>Draft → Review → Approve → Activate</span><button className="hrx-link" onClick={() => setCompareKey(r.setting_key)}>Compare versions</button></div></div>):<div className="card card-pad"><EmptyPanel icon="shield" title="No statutory rule packs loaded" text="Compliance state packs are hidden until governed Laravel SystemSetting records are available."/></div>}</div><Section title="Statutory Rule Packs" sub="Restricted SystemSetting records with version, effective date, approval history and audit trail" action={<Button icon="plus" sm onClick={()=>openRule()}>Draft Rule</Button>}><Table rows={latestRules} columns={[{label:"Rule",render:r=><div><b>{r.label}</b><div className="cell-sub">{r.setting_key} · {r.scope_key}</div></div>},{label:"Version",render:r=>"v"+r.version},{label:"Effective",render:r=>r.effective_from||"Immediate"},{label:"Company",render:r=>r.company?.code||"Global"},{label:"Verified",render:r=>r.value?.verified?"Yes":r.value?.statutory_validation_required?"Pending expert validation":"No"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=><div className="hrx-chip-wrap"><button className="hrx-link" onClick={()=>openRule(r)}>Revise</button><button className="hrx-link" onClick={()=>setCompareKey(r.setting_key)}>Compare</button>{r.status==="draft"&&complianceOptions?.can_approve?<button className="hrx-link" onClick={()=>approveRule(r)}>Approve</button>:null}</div>}]}/></Section>{compared.length>0&&<Section title="Version Comparison" sub={`${compareKey} · latest five governed versions`}><Table rows={compared} columns={[{label:"Version",render:r=>"v"+r.version},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Effective",render:r=>r.effective_from||"Immediate"},{label:"Approved",render:r=>r.approved_at||"—"},{label:"Value snapshot",render:r=><span className="cell-sub">{JSON.stringify(r.value||{}).slice(0,180)}{JSON.stringify(r.value||{}).length>180?"…":""}</span>} ]}/></Section>}<Section title="National Rule Types" sub="Configured by applicability, wage basis, ceiling, contribution, rounding and effective date"><div className="hrx-settings-grid"><Setting label="Provident Fund" value={complianceOptions?.source==="laravel-sqlite"?"Configured in governed rule packs":"Compliance API required"}/><Setting label="ESIC" value={complianceOptions?.source==="laravel-sqlite"?"Configured in governed rule packs":"Compliance API required"}/><Setting label="Income tax / TDS" value={payrollOptions?.tax_documents_index_url?"Tax document API available":"Tax document API required"}/><Setting label="Professional Tax" value={complianceOptions?.source==="laravel-sqlite"?"Configured in governed state packs":"Compliance API required"}/><Setting label="Labour Welfare Fund" value={complianceOptions?.source==="laravel-sqlite"?"Configured in governed state packs":"Compliance API required"}/><Setting label="Gratuity / Bonus" value={complianceOptions?.source==="laravel-sqlite"?"Settlement-linked governed settings":"Compliance API required"}/></div></Section><Section title="Form 16 / Tax Documents" sub={taxUrl?(loading?"Loading Laravel tax register":"Laravel generated/issued/acknowledged tax documents"):"Tax document API required; no local Form 16 records are fabricated."}>{data.length?<Table rows={data} columns={[{label:"Document",render:r=><div><b>{r.document_number||r.id}</b><div className="cell-sub">{r.document_type} · FY {r.financial_year} · v{r.version}</div></div>},{label:"Employee",render:r=><Person employee={r.employee?.name||r.employee} sub={r.employee?.employee_code}/>},{label:"Gross",right:true,render:r=>money(r.gross_salary||0)},{label:"TDS",right:true,render:r=>money(r.tds_deducted||0)},{label:"Net paid",right:true,render:r=><b>{money(r.net_salary_paid||0)}</b>},{label:"Acknowledged",render:r=>r.acknowledged_at?"Yes":"No"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="generated"&&payrollOptions?.can_issue_tax_document?<button className="hrx-link" onClick={()=>issueTax(r)}>Issue</button>:<span className="faint">—</span>}]}/>:<EmptyPanel icon="doc" title="No tax documents loaded" text="Form 16 records appear only after the Laravel payroll tax document API is available and returns scoped records."/>}</Section>{creating&&<ComplianceTaxDocumentModal options={payrollOptions} onClose={()=>setCreating(false)} onCreated={row=>setRows(current=>[row,...current])} toast={toast}/>} {creatingRule&&<ComplianceRuleSettingModal options={complianceOptions} sourceSetting={ruleSource} onClose={()=>setCreatingRule(false)} onCreated={row=>setRuleRows(current=>[row,...current])} toast={toast}/>}</div>;
  }

  function ReportsView({ role, update, toast }) {
    const reportOptions=window.Builder360Server?.hr_report_options||null;
    const hasReportApi=reportOptions?.source==="laravel-sqlite";
    const [misBusy,setMisBusy]=React.useState(false);
    const optionValue=o=>String(o?.value??o?.id??o);
    const optionLabel=o=>o?.label??o?.name??o?.title??String(o);
    const firstOption=(items,fallback)=>items?.length?optionValue(items[0]):fallback;
    const companyOptions=hasReportApi?(reportOptions?.company_filters||reportOptions?.companies||window.Builder360Server?.companies||[]):[];
    const departmentOptions=hasReportApi?(reportOptions?.department_filters||[]):[];
    const employeeOptions=hasReportApi?(reportOptions?.employee_filters||reportOptions?.employees||[]):[];
    const periodOptions=hasReportApi?(reportOptions?.period_filters||[]):[];
    const statusLabels=hasReportApi?(reportOptions?.status_filters||[]):[];
    const reportCatalog=hasReportApi?(reportOptions?.report_catalog||reportOptions?.report_types||[]):[];
    const formats=hasReportApi?(reportOptions?.formats||[{value:"excel",label:"Excel"},{value:"pdf",label:"PDF"},{value:"csv",label:"CSV"}]):[];
    const [filters,setFilters]=React.useState({company:"all",department:"all",employee:"all",period:firstOption(periodOptions,""),status:"all",type:firstOption(reportCatalog,"")});
    React.useEffect(()=>{setFilters(f=>({...f,period:f.period||firstOption(periodOptions,""),type:f.type||firstOption(reportCatalog,"")}));},[reportOptions?.source]);
    const set=(k,v)=>setFilters(f=>({...f,[k]:v}));
    const selectedCompany=companyOptions.find(c=>optionValue(c)===String(filters.company));
    const selectedEmployee=employeeOptions.find(e=>optionValue(e)===String(filters.employee));
    const selectedReport=reportCatalog.find(r=>optionValue(r)===String(filters.type));
    const reportType=selectedReport?optionLabel(selectedReport):(filters.type||"Employee Master Register");
    const summary=hasReportApi?(reportOptions?.summary||{}):{};
    const hasNumericSummaryValue=v=>v!==null&&v!==undefined&&v!==""&&Number.isFinite(Number(v));
    const summaryValue=(key)=>hasNumericSummaryValue(summary[key])?Number(summary[key]):"—";
    const attendanceValue=hasNumericSummaryValue(summary.average_attendance_percent)?Number(summary.average_attendance_percent):"—";
    const filteredReports=reportCatalog.filter(r=>!filters.type||optionValue(r)===String(filters.type));
    const exportHrMis=format=>{
      if(!hasReportApi||!reportOptions?.can_export||!reportOptions?.export_url){unavailableAction(update,`HR MIS ${format.toUpperCase()} export`,"HR Reports");return;}
      const url=new URL(reportOptions.export_url,window.location.origin);
      url.searchParams.set("format",format);
      url.searchParams.set("report_type",reportType);
      if(filters.department!=="all")url.searchParams.set("department",filters.department);
      if(filters.status!=="all")url.searchParams.set("status",filters.status);
      if(selectedEmployee)url.searchParams.set("search",selectedEmployee.code||selectedEmployee.employee_code||selectedEmployee.name||optionLabel(selectedEmployee));
      const numericCompany=Number(filters.company);
      if(filters.company!=="all"&&Number.isInteger(numericCompany))url.searchParams.set("company_id",String(numericCompany));
      update((s,actor)=>addAudit(s,actor,`Requested Laravel HR MIS ${format.toUpperCase()} export`,"HR Reports","Export"),`HR MIS ${format.toUpperCase()} export started`);
      toast&&toast(`Downloading HR MIS ${format.toUpperCase()} export from Laravel records.`,"green");
      window.location.assign(url.toString());
    };
    const createCustomMisDraft=async()=>{
      if(misBusy)return;
      if(!hasReportApi||!reportOptions?.can_create_custom_mis||!reportOptions?.custom_mis_store_url){unavailableAction(update,"Custom HR MIS draft creation","HR Reports");return;}
      const now=new Date();
      const columns=(reportOptions?.default_columns||["Employee Code","Employee Name","Company","Department","Designation","Status"]).filter(Boolean);
      const numericCompany=Number(filters.company);
      const definition={name:`Custom HR MIS - ${reportType}`,report_type:reportType,filters:{company_id:filters.company!=="all"&&Number.isInteger(numericCompany)?numericCompany:null,company_label:filters.company==="all"?"All companies":(selectedCompany?optionLabel(selectedCompany):filters.company),department:filters.department==="all"?null:filters.department,employee_id:selectedEmployee?.id||null,employee_label:selectedEmployee?`${selectedEmployee.employee_code||selectedEmployee.code||selectedEmployee.id} · ${selectedEmployee.name||optionLabel(selectedEmployee)}`:null,period:filters.period||null,status:filters.status==="all"?null:filters.status},columns,formats:formats.map(f=>optionValue(f)),include_compensation:!!(reportOptions?.compensation_visible&&reportType.toLowerCase().includes("payroll")),role_scope:(PERMISSIONS[role.id]||PERMISSIONS.employee).scope,approval_required:true,generated_from:"hr_reports_screen",created_at:now.toISOString()};
      const payload={setting_group:"hr",setting_key:reportOptions.custom_mis_setting_key||"hr.custom_mis_reports",label:`Custom HR MIS - ${reportType} - ${now.toLocaleDateString("en-IN")}`,description:"Governed HR custom MIS definition created from HR Reports filters.",value_type:"object",value:definition,effective_from:now.toISOString().slice(0,10),metadata:{source:"hr_reports_custom_mis_builder",screen:"HR Reports & MIS",role_id:role.id,filters:definition.filters}};
      if(definition.filters.company_id)payload.company_id=definition.filters.company_id;
      try{setMisBusy(true);const body=await apiJson(reportOptions.custom_mis_store_url,{method:"POST",body:JSON.stringify(payload)});update((s,actor)=>addAudit(s,actor,`Created ${body.data?.label||payload.label}`,"HR Reports","Configuration"),"Custom HR MIS draft created");toast&&toast("Custom HR MIS draft saved to System Settings for approval.","green");}catch(err){toast&&toast("Custom HR MIS draft failed: "+(err.message||"request failed"),"orange");}finally{setMisBusy(false);}
    };
    return <div><ViewTitle title="HR Reports & MIS" sub="Role-scoped analytics with company, department, employee, period, status and report filters." actions={<Button icon="plus" variant="primary" sm onClick={createCustomMisDraft}>{misBusy?"Saving MIS...":"Build Custom MIS"}</Button>}/>{!hasReportApi&&<div className="hrx-warning"><Icon name="alert"/><div><b>HR Reports API required</b><span>HR Reports API required; no local employee, attendance, department, audit or MIS rows are fabricated.</span></div></div>}<div className="hrx-filter-grid"><label>Company<select value={filters.company} onChange={e=>set("company",e.target.value)} disabled={!hasReportApi}><option value="all">All companies</option>{companyOptions.map(c=><option key={optionValue(c)} value={optionValue(c)}>{optionLabel(c)}</option>)}</select></label><label>Department<select value={filters.department} onChange={e=>set("department",e.target.value)} disabled={!hasReportApi}><option value="all">All departments</option>{departmentOptions.map(x=><option key={optionValue(x)} value={optionValue(x)}>{optionLabel(x)}</option>)}</select></label><label>Employee<select value={filters.employee} onChange={e=>set("employee",e.target.value)} disabled={!hasReportApi}><option value="all">All employees</option>{employeeOptions.filter(e=>filters.department==="all"||!e.department||e.department===filters.department).map(e=><option key={optionValue(e)} value={optionValue(e)}>{optionLabel(e)}</option>)}</select></label><label>Period<select value={filters.period} onChange={e=>set("period",e.target.value)} disabled={!hasReportApi}><option value="">Current scope</option>{periodOptions.map(p=><option key={optionValue(p)} value={optionValue(p)}>{optionLabel(p)}</option>)}</select></label><label>Status<select value={filters.status} onChange={e=>set("status",e.target.value)} disabled={!hasReportApi}><option value="all">All statuses</option>{statusLabels.map(s=><option key={optionValue(s)} value={optionValue(s)}>{optionLabel(s)}</option>)}</select></label><label>Report type<select value={filters.type} onChange={e=>set("type",e.target.value)} disabled={!hasReportApi}><option value="">Select report</option>{reportCatalog.map(r=><option key={optionValue(r)} value={optionValue(r)}>{optionLabel(r)}</option>)}</select></label></div><div className="hrx-toolbar"><Badge tone="b-violet">{(PERMISSIONS[role.id]||PERMISSIONS.employee).scope}</Badge><Badge tone="b-slate">{summaryValue("employees_in_scope")} employees in scope</Badge><Badge tone={hasReportApi?"b-green":"b-orange"}>{hasReportApi?"Laravel export":"Report API required"}</Badge><Badge tone={reportOptions?.can_create_custom_mis?"b-green":"b-slate"}>{reportOptions?.can_create_custom_mis?"Custom MIS drafts enabled":"Custom MIS view only"}</Badge><div className="hrx-grow"/>{formats.map(f=><Button key={optionValue(f)} icon="download" sm onClick={() => exportHrMis(optionValue(f))}>{optionLabel(f)}</Button>)}</div><KpiGrid><Stat label="Employees in Scope" value={summaryValue("employees_in_scope")} icon="users" tone="accent"/><Stat label="Average Attendance" value={attendanceValue} unit={attendanceValue==="—"?"":"%"} icon="check" tone="green"/><Stat label="Departments" value={summaryValue("departments")} icon="chart" tone="orange"/><Stat label="Exports Audited" value={summaryValue("exports_audited")} icon="shield" tone="violet"/></KpiGrid>{filteredReports.length?<div className="hrx-report-grid">{filteredReports.map((r,i)=><div className="card hrx-report-card" key={optionValue(r)}><div className="hrx-feature-icon"><Icon name={i%3===0?"users":i%3===1?"chart":"doc"}/></div><div className="hrx-grow"><b>{optionLabel(r)}</b><span>{filters.department==="all"?"All departments":filters.department} · {filters.employee==="all"?"All employees":"Selected employee"} · Laravel role filters applied</span></div><Icon name="chevR" size={16}/></div>)}</div>:<EmptyPanel icon="doc" title="Report catalogue unavailable" text={hasReportApi?"No governed HR report catalogue was returned by the Laravel bootstrap payload.":"Report catalogue is hidden until the HR Reports API is available."}/>}</div>;
  }

  function SystemSettingDraftModal({ options, sourceSetting, onClose, onCreated, toast }) {
    const companies=window.Builder360Server?.companies||[];
    const defaultValue=sourceSetting?.value||{enabled:true,policy_source:"hr_settings_screen",approval_required:true};
    const [form,setForm]=React.useState({company_id:sourceSetting?.company?.id||companies[0]?.id||"",setting_group:sourceSetting?.setting_group||"hr",setting_key:sourceSetting?.setting_key||"hr.leave.rules",label:sourceSetting?.label||"HR Leave Rules",description:sourceSetting?.description||"Governed HRMS configuration draft from HR Settings.",value_type:"object",value:JSON.stringify(defaultValue,null,2),effective_from:""});
    const [busy,setBusy]=React.useState(false),[error,setError]=React.useState("");
    const set=(k,v)=>setForm(c=>({...c,[k]:v}));
    const submit=async ev=>{ev.preventDefault();setError("");if(!options?.system_settings_store_url||!options?.can_manage_settings){setError("Your role cannot create governed settings drafts.");return;}let parsed;try{parsed=JSON.parse(form.value);}catch(e){setError("Setting value must be valid JSON.");return;}setBusy(true);try{const payload={setting_group:form.setting_group,setting_key:form.setting_key,label:form.label,description:form.description,value_type:form.value_type,value:parsed,metadata:{source:"hr_settings_screen"}};if(form.company_id)payload.company_id=Number(form.company_id);if(form.effective_from)payload.effective_from=form.effective_from;const body=await apiJson(options.system_settings_store_url,{method:"POST",body:JSON.stringify(payload)});onCreated(body.data);toast&&toast("System setting draft created in Laravel workflow.","green");onClose();}catch(err){setError(err.message||"System setting draft could not be created.");}finally{setBusy(false);}};
    return <div className="scrim" onClick={busy?undefined:onClose}><form className="modal hrx-modal" onClick={e=>e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>New Governed Setting Draft</h2><p>Creates a versioned System Settings API draft with scope, JSON value, metadata, approval workflow and audit trail.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>Setting draft failed</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Company / scope<select value={form.company_id} disabled={busy} onChange={e=>set("company_id",e.target.value)}><option value="">Global scope</option>{companies.map(c=><option key={c.id} value={c.id}>{c.label||`${c.code} · ${c.name}`}</option>)}</select></label><label>Group<input required maxLength={80} value={form.setting_group} disabled={busy} onChange={e=>set("setting_group",e.target.value.toLowerCase())}/></label><label>Setting key<select value={form.setting_key} disabled={busy} onChange={e=>{const key=e.target.value;const group=key.split(".")[0];setForm(c=>({...c,setting_key:key,setting_group:group,label:key.replaceAll("."," ").replaceAll("_"," ")}));}}>{["hr.attendance.rules","hr.leave.rules","payroll.tax_rules","payroll.commission_rules","workflow.approval_chains","governance.backup_dr"].map(k=><option key={k} value={k}>{k}</option>)}</select></label><label>Effective from<input type="date" value={form.effective_from} disabled={busy} onChange={e=>set("effective_from",e.target.value)}/></label><label style={{gridColumn:"1 / -1"}}>Label<input required maxLength={255} value={form.label} disabled={busy} onChange={e=>set("label",e.target.value)}/></label><label style={{gridColumn:"1 / -1"}}>Description<textarea maxLength={5000} value={form.description} disabled={busy} onChange={e=>set("description",e.target.value)}/></label><label style={{gridColumn:"1 / -1"}}>JSON value<textarea required rows={10} value={form.value} disabled={busy} onChange={e=>set("value",e.target.value)} spellCheck="false"/></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="gear" size={15}/>{busy?"Creating...":"Create Draft"}</button></div></form></div>;
  }

  function SettingsViewV2({ state, update, toast, adminOptions }) {
    const [tab,setTab]=React.useState("Policy Registry"),[rows,setRows]=React.useState(adminOptions?.settings||[]),[loading,setLoading]=React.useState(false),[error,setError]=React.useState(""),[draftSource,setDraftSource]=React.useState(null),[creating,setCreating]=React.useState(false);
    const settingsUrl=adminOptions?.system_settings_index_url;
    React.useEffect(()=>{if(!settingsUrl)return;let alive=true;setLoading(true);setError("");apiJson(collectionUrl(settingsUrl,{page:1})).then(body=>{if(alive)setRows(body.data||[]);}).catch(err=>{if(alive){setError(err.message||"System settings could not be loaded.");toast&&toast("System Settings API fallback active: "+(err.message||"load failed"),"orange");}}).finally(()=>{if(alive)setLoading(false);});return()=>{alive=false;};},[settingsUrl,toast]);
    const settingRows=rows.length?rows:(adminOptions?.settings||[]);
    const exportRegister=()=>{downloadJsonFile("builder360-hr-settings-register.json",{source:adminOptions?.source||"unavailable",exported_at:new Date().toISOString(),records:settingRows});update((s,actor)=>addAudit(s,actor,"Exported governed HR settings register","HR Settings","Export"),"HR settings register exported");};
    const filtered=settingRows.filter(r=>tab==="Policy Registry"?["hr.attendance.rules","hr.leave.rules","payroll.tax_rules","payroll.commission_rules"].includes(r.setting_key):tab==="Workflow Designer"?r.setting_key==="workflow.approval_chains":tab==="Security & DR"?r.setting_key==="governance.backup_dr":true);
    const createFrom=(row=null)=>{if(adminOptions?.can_manage_settings&&adminOptions?.system_settings_store_url){setDraftSource(row);setCreating(true);return;}unavailableAction(update,"System setting draft creation","HR Settings");};
    const approve=async row=>{const tpl=adminOptions?.system_setting_approve_url_template;if(!tpl||!adminOptions?.can_approve_settings||row.status!=="draft"){unavailableAction(update,"System setting approval","HR Settings");return;}try{const body=await apiJson(tpl.replace("__SETTING__",row.id),{method:"PATCH",body:JSON.stringify({note:"Approved from HR Settings workspace."})});setRows(current=>current.map(x=>x.id===body.data.id?body.data:x));toast&&toast("System setting approved in Laravel workflow.","green");}catch(err){setError(err.message||"System setting approval failed.");toast&&toast("System setting approval failed: "+(err.message||"request failed"),"orange");}};
    const valueSummary=row=>{const value=row.value||{};if(row.setting_key==="workflow.approval_chains")return `${Object.keys(value).length} workflow(s)`;if(row.setting_key==="governance.backup_dr")return `RPO ${value.rpo||"configured"} · RTO ${value.rto||"configured"}`;return Object.keys(value).slice(0,4).join(" · ")||row.value_type;};
    const settingsTabs=["Policy Registry","Workflow Designer","Payroll Components","Templates","Permissions","Imports","Integrations","Security & DR"];
    return <div><ViewTitle title="HR Settings" sub="System Settings API-backed policy, workflow, payroll, template, alert and governance configuration." actions={[<Button key="n" icon="plus" variant="primary" sm onClick={()=>createFrom()}>New Setting Draft</Button>,<Button key="e" icon="download" sm onClick={exportRegister}>Export Register</Button>]}/>{error&&<div className="hrx-warning" style={{marginBottom:12}}><Icon name="alert"/><div><b>System Settings API fallback active</b><span>{error}</span></div></div>}<div className="hrx-settings-nav">{settingsTabs.map(name=><button key={name} className={tab===name?"on":""} onClick={()=>setTab(name)}>{name}</button>)}</div><div className="hrx-toolbar"><Badge tone={adminOptions?.source==="laravel-sqlite"?"b-green":"b-orange"}>{loading?"Loading governed settings":adminOptions?.source==="laravel-sqlite"?"Laravel governed settings":"Backend settings API required"}</Badge><Badge tone="b-slate">{settingRows.filter(r=>r.status==="active").length} active</Badge><Badge tone="b-orange">{settingRows.filter(r=>r.status==="draft").length} draft</Badge><Badge tone={adminOptions?.can_approve_settings?"b-green":"b-slate"}>{adminOptions?.can_approve_settings?"APPROVER":"VIEW / DRAFT"}</Badge></div><div className="hrx-grid-2"><Section title="Configuration-First Policy Registry" sub="Versioned, scoped, effective-dated and approval controlled"><div className="hrx-settings-grid"><Setting label="Scope precedence" value={adminOptions?.source==="laravel-sqlite"?"System Settings scope hierarchy":"Backend settings API required"}/><Setting label="Formula safety" value={adminOptions?.source==="laravel-sqlite"?"Validated JSON setting values":"Backend settings API required"}/><Setting label="Settings source" value={adminOptions?.source==="laravel-sqlite"?"System Settings API":"Unavailable"}/><Setting label="Approval control" value="Draft → separate approver → active version"/><Setting label="No hardcoding" value="Rules, formulas, templates and thresholds stored as governed settings"/><Setting label="Audit" value="Every draft and approval writes audit events"/></div></Section><Section title="Workflow Definitions" sub="Dynamic approvers, conditions, SLA and escalation"><div className="hrx-list">{(adminOptions?.approval_chains||[]).length?(adminOptions.approval_chains).map((w,i)=><div className="hrx-list-row" key={w.workflow||w.name||i}><div className="hrx-icon"><Icon name="funnel" size={15}/></div><div className="hrx-grow"><b>{w.workflow||w.name}</b><span>{Array.isArray(w.steps)?w.steps.join(" → "):w.steps}</span><small>{w.sla?`SLA ${w.sla}`:"System Settings API"}</small></div><StatePill>{w.status||"active"}</StatePill></div>):<EmptyPanel icon="funnel" title="No workflow settings loaded" text="Workflow definitions are not simulated from local HR state. Configure active records in System Settings."/>}</div></Section></div><Section title="Governed Setting Register" sub={`${tab} · loaded from /settings/system-settings with role and company scoping`} action={<Button icon="plus" sm onClick={()=>createFrom()}>Draft</Button>}><Table rows={filtered} columns={[{label:"Setting",render:r=><div><b>{r.label}</b><div className="cell-sub">{r.setting_key} · {r.scope_key}</div></div>},{label:"Group",key:"setting_group"},{label:"Version",render:r=>"v"+r.version},{label:"Value",render:r=><span className="cell-sub">{valueSummary(r)}</span>},{label:"Effective",render:r=>r.effective_from||"Immediate"},{label:"Company",render:r=>r.company?.code||"Global"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=><div className="hrx-chip-wrap"><button className="hrx-link" onClick={()=>createFrom(r)}>Revise</button>{r.status==="draft"&&adminOptions?.can_approve_settings?<button className="hrx-link" onClick={()=>approve(r)}>Approve</button>:null}</div>}]}/></Section>{tab==="Security & DR"&&<Section title="Backup & Disaster Recovery" sub="Configuration/status record only; operational backup execution is deployment-specific"><div className="hrx-settings-grid"><Setting label="Configured record" value={adminOptions?.backup_dr?.setting_key||"governance.backup_dr"}/><Setting label="Status" value={adminOptions?.backup_dr?.status||"Backend settings API required"}/><Setting label="RPO / RTO" value={`${adminOptions?.backup_dr?.value?.rpo||"—"} / ${adminOptions?.backup_dr?.value?.rto||"—"}`}/><Setting label="Owner / Runbook" value={`${adminOptions?.backup_dr?.value?.owner||"—"} · ${adminOptions?.backup_dr?.value?.runbook||"—"}`}/></div></Section>}<Section title="Audit Log" sub="Local HR screen activity plus server-side setting events in Audit Trail"><Table rows={state.audit} columns={[{label:"Actor",key:"actor"},{label:"Action",key:"action"},{label:"Entity",key:"entity"},{label:"Type",render:r=><Badge tone="b-blue">{r.type}</Badge>},{label:"Timestamp",key:"time"}]}/></Section>{creating&&<SystemSettingDraftModal options={adminOptions} sourceSetting={draftSource} onClose={()=>setCreating(false)} onCreated={row=>setRows(current=>[row,...current])} toast={toast}/>}</div>;
  }

  function SettingsView({ update }) {
    const settingsTabs=["Policy Registry","Workflow Designer","Payroll Components","Templates","Permissions","Imports","Integrations","Security & DR"];
    return <div><ViewTitle title="HR Settings" sub="HR settings fallback is read-only. Backend System Settings API required for policy, workflow, payroll, template, alert and governance configuration." actions={<Button icon="download" sm onClick={()=>unavailableAction(update,"HR settings register export","HR Settings")}>Export unavailable</Button>}/><div className="hrx-warning"><Icon name="alert"/><div><b>Backend System Settings API required</b><span>Settings are not simulated or reset from localStorage. Governed configuration must be created as versioned SystemSetting records with approval and audit history.</span></div></div><div className="hrx-settings-nav">{settingsTabs.map((name,i)=><button key={name} className={i===0?"on":""} onClick={()=>i===0?null:unavailableAction(update, `${name} settings`, "HR Settings")}>{name}</button>)}</div><div className="hrx-grid-2"><Section title="Configuration-First Policy Registry" sub="Requires governed System Settings records"><div className="hrx-settings-grid"><Setting label="Scope precedence" value="Backend settings API required"/><Setting label="Formula safety" value="Backend settings API required"/><Setting label="Attendance rules" value="Backend settings API required"/><Setting label="Leave processing" value="Backend settings API required"/><Setting label="Commission rules" value="Backend settings API required"/><Setting label="State packs" value="Backend settings API required"/></div></Section><Section title="Workflow Definitions" sub="Requires active workflow configuration records"><EmptyPanel icon="funnel" title="No workflow settings loaded" text="Workflow definitions are not simulated in fallback mode."/></Section><Section title="Role & Field Security" sub="Requires server role/permission bootstrap"><EmptyPanel icon="shield" title="No permission matrix loaded" text="Role, action, record-scope and sensitive-field permissions must come from Laravel authorization data."/></Section><Section title="Integration Adapters" sub="Configuration records only"><EmptyPanel icon="gear" title="No integration settings loaded" text="Third-party service metadata must be loaded from governed configuration records."/></Section></div><Section title="Backup & Disaster Recovery" sub="Configuration/status records only; operational backup execution is deployment-specific"><div className="hrx-settings-grid"><Setting label="Schedule" value="Backend settings API required"/><Setting label="Retention" value="Backend settings API required"/><Setting label="Off-site replication" value="Backend settings API required"/><Setting label="RPO / RTO" value="— / —"/><Setting label="Owner / Runbook" value="—"/><Setting label="Last restore test" value="—"/></div></Section></div>;
  }

  function Setting({ label, value }) { return <div className="hrx-setting"><span>{label}</span><b>{value}</b></div>; }
  function Feature({ icon, title, text, badge }) { return <div className="card card-pad hrx-feature-card"><div className="row between"><div className="hrx-feature-icon"><Icon name={icon}/></div>{badge&&<Badge tone="b-violet">{badge}</Badge>}</div><h3>{title}</h3><p>{text}</p></div>; }
  function SimpleEntityView({ title, sub, action, rows, columns, features, update }) { return <div><ViewTitle title={title} sub={sub} actions={<Button icon="plus" variant="primary" sm onClick={() => unavailableAction(update, action, title)}>{action}</Button>}/><div className="hrx-card-grid">{features.map((f,i)=><Feature key={i} icon={f[0]} title={f[1]} text={f[2]}/>)}</div><Section title={title+" Register"} sub="Backend API required; no local rows are fabricated."><Table rows={rows} columns={columns}/></Section></div>; }

  function HRManagement({ role = { id: "director", person: "Authenticated User" }, toast }) {
    const [state, update, setState] = useHRState(role, toast);
    const initial = (location.hash.match(/^#hr\/(.+)$/) || [])[1] || "dashboard";
    const [view, setView] = React.useState(HR_NAV.some(x=>x[0]===initial)?initial:"dashboard");
    React.useEffect(()=>{location.hash="#hr/"+view;},[view]);
    const dashboardOptions=window.Builder360Server?.hr_dashboard_options||null;
    const operationsOptions=window.Builder360Server?.hr_operations_options||null;
    const leaveOptions=window.Builder360Server?.hr_leave_options||null;
    const attendanceOptions=window.Builder360Server?.hr_attendance_options||null;
    const recruitmentOptions=window.Builder360Server?.hr_recruitment_options||null;
    const performanceOptions=window.Builder360Server?.hr_performance_options||null;
    const lifecycleOptions=window.Builder360Server?.hr_lifecycle_options||null;
    const payrollOptions=window.Builder360Server?.hr_payroll_options||null;
    const complianceOptions=window.Builder360Server?.hr_compliance_options||null;
    const adminOptions=window.Builder360Server?.admin_governance_options||null;
    const props={state,update,setState,role,toast,dashboardOptions,operationsOptions,leaveOptions,attendanceOptions,recruitmentOptions,performanceOptions,lifecycleOptions,payrollOptions,complianceOptions,adminOptions};
    const views={dashboard:<DashboardViewV2 {...props}/>,employees:<EmployeesView {...props}/>,attendance:<AttendanceViewV2 {...props}/>,shifts:<ShiftsView {...props}/>,leave:<LeaveViewV2 {...props}/>,payroll:<PayrollViewV2 {...props}/>,recruitment:<RecruitmentView {...props}/>,performance:<PerformanceView {...props}/>,lifecycle:<LifecycleView {...props}/>,documents:<DocumentsView {...props}/>,assets:<AssetsView {...props}/>,claims:<ClaimsView {...props}/>,loans:<LoansView {...props}/>,helpdesk:<HelpdeskView {...props}/>,compliance:<ComplianceViewV2 {...props}/>,reports:<ReportsView {...props}/>,settings:<SettingsViewV2 {...props}/>};
    const permission=PERMISSIONS[role.id]||PERMISSIONS.employee;
    const pendingDashboardApprovals=Number(dashboardOptions?.summary?.pending_approvals||0);
    return <div className="hrx-page"><PageHead crumbs={["People","HR & Employees"]} title="HR & Employees" sub="Complete employee lifecycle, workforce operations and configurable payroll inside Builder360." actions={[<Badge key="r" tone="b-violet">{permission.label}</Badge>,<Badge key="s" tone="b-slate">{permission.scope}</Badge>]}/><div className="hrx-shell"><aside className="hrx-rail"><div className="hrx-rail-head"><div className="hrx-rail-logo"><Icon name="id"/></div><div><b>People Workspace</b><span>{dashboardOptions?.source==="laravel-sqlite"?"Laravel HRMS":"Backend HRMS API required"}</span></div></div><nav>{HR_NAV.map(([id,label,icon])=><button className={view===id?"on":""} onClick={()=>setView(id)} key={id}><Icon name={icon} size={16}/><span>{label}</span>{id==="dashboard"&&pendingDashboardApprovals>0&&<i>{pendingDashboardApprovals}</i>}</button>)}</nav><div className="hrx-rail-foot"><Icon name="shield" size={15}/><span>{dashboardOptions?.source==="laravel-sqlite"?"MySQL scoped data":"Server data unavailable"}</span></div></aside><main className="hrx-main">{views[view]}</main></div></div>;
  }

  function AttendanceRegularizationModal({ options, onClose, onCreated, toast }) {
    const toDate = d => d.toISOString().slice(0, 10);
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const defaultDate = toDate(yesterday);
    const [form, setForm] = React.useState({ work_date: defaultDate, check_in: "09:30", check_out: "18:30", reason: "" });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!options?.attendance_regularization_store_url || !options?.self_employee?.id) {
        setError("Your employee profile is not available for attendance regularization.");
        return;
      }
      const checkIn = `${form.work_date} ${form.check_in}:00`;
      const checkOut = `${form.work_date} ${form.check_out}:00`;
      if (checkOut <= checkIn) {
        setError("Check-out must be after check-in for the selected work date.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(options.attendance_regularization_store_url, {
          method: "POST",
          body: JSON.stringify({
            employee_id: options.self_employee.id,
            work_date: form.work_date,
            requested_check_in_at: checkIn,
            requested_check_out_at: checkOut,
            reason: form.reason.trim(),
          }),
        });
        toast && toast("Attendance regularization submitted to Laravel workflow.", "green");
        onCreated && onCreated(body.data);
        onClose();
      } catch (err) {
        setError(err.message || "Attendance regularization could not be submitted.");
        toast && toast("Attendance request not submitted: " + (err.message || "save failed"), "orange");
      } finally {
        setBusy(false);
      }
    };
    return <div className="scrim" onClick={busy ? undefined : onClose}><form className="modal hrx-modal" onClick={e => e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Attendance Regularization</h2><p>Creates a governed Laravel attendance request with own-profile scope, approval workflow, calculation and audit trail.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Request not submitted</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee<input value={`${options?.self_employee?.employee_code || "—"} · ${options?.self_employee?.name || "Own profile"}`} disabled /></label><label>Work date<input required type="date" max={toDate(new Date())} value={form.work_date} disabled={busy} onChange={e => set("work_date", e.target.value)}/></label><label>Requested check-in<input required type="time" value={form.check_in} disabled={busy} onChange={e => set("check_in", e.target.value)}/></label><label>Requested check-out<input required type="time" value={form.check_out} disabled={busy} onChange={e => set("check_out", e.target.value)}/></label><label style={{ gridColumn: "1 / -1" }}>Reason<textarea required maxLength={2000} value={form.reason} disabled={busy} onChange={e => set("reason", e.target.value)} placeholder="Explain the missed punch, biometric issue, manager confirmation or corrected timing."/></label></div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="check" size={15}/>{busy ? "Submitting…" : "Submit Attendance Request"}</button></div></form></div>;
  }

  function LeaveRequestModal({ options, onClose, onCreated, toast }) {
    const employees = options?.self_employee ? [options.self_employee] : (options?.employees || []);
    const leaveTypes = options?.leave_types || [];
    const today = new Date().toISOString().slice(0, 10);
    const defaultEmployeeId = options?.self_employee?.id || employees[0]?.id || "";
    const typesForEmployee = employeeId => {
      const employee = employees.find(e => String(e.id) === String(employeeId));
      return leaveTypes.filter(t => !employee?.company_id || !t.company_id || String(t.company_id) === String(employee.company_id));
    };
    const firstType = typesForEmployee(defaultEmployeeId)[0] || leaveTypes[0] || {};
    const [form, setForm] = React.useState({ employee_id: defaultEmployeeId, leave_type_id: firstType.id || "", starts_on: today, ends_on: today, duration_unit: "full_day", reason: "" });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const availableTypes = typesForEmployee(form.employee_id);
    const selectedType = availableTypes.find(t => String(t.id) === String(form.leave_type_id)) || availableTypes[0] || firstType;
    const set = (key, value) => setForm(current => {
      const next = { ...current, [key]: value };
      if (key === "employee_id") {
        const nextTypes = typesForEmployee(value);
        if (!nextTypes.some(t => String(t.id) === String(next.leave_type_id))) next.leave_type_id = nextTypes[0]?.id || "";
      }
      if (key === "starts_on" && next.ends_on < value) next.ends_on = value;
      if (key === "duration_unit" && value === "half_day") next.ends_on = next.starts_on;
      return next;
    });
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!options?.leave_request_store_url || !form.employee_id) {
        setError("Select an employee before submitting leave.");
        return;
      }
      if (!form.leave_type_id) {
        setError("Select a leave type before submitting.");
        return;
      }
      if (selectedType?.requires_document) {
        setError("This leave type requires a supporting document. Upload the document from Employee Documents before submitting this request.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(options.leave_request_store_url, {
          method: "POST",
          body: JSON.stringify({
            employee_id: Number(form.employee_id),
            leave_type_id: Number(form.leave_type_id),
            starts_on: form.starts_on,
            ends_on: form.duration_unit === "half_day" ? form.starts_on : form.ends_on,
            duration_unit: form.duration_unit,
            reason: form.reason.trim() || null,
          }),
        });
        toast && toast("Leave request submitted to Laravel workflow.", "green");
        onCreated && onCreated(body.data);
        onClose();
      } catch (err) {
        setError(err.message || "Leave request could not be submitted.");
        toast && toast("Leave not submitted: " + (err.message || "save failed"), "orange");
      } finally {
        setBusy(false);
      }
    };
    const balanceText = selectedType && Object.prototype.hasOwnProperty.call(selectedType, "available_days") ? `${Number(selectedType.available_days || 0).toFixed(1)} days · ${Number(selectedType.pending_days || 0).toFixed(1)} pending` : "Validated by Laravel leave balance ledger";
    return <div className="scrim" onClick={busy ? undefined : onClose}><form className="modal hrx-modal" onClick={e => e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Apply Leave</h2><p>Creates a governed Laravel leave request with balance, overlap, half-day, ownership and approval validation.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Leave not submitted</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee<SearchablePeoplePicker items={employees} selected={form.employee_id} mode="single" required disabled={busy || !!options?.self_employee || !employees.length} placeholder="Search employee name, code, department..." emptyText="No matching employees" onChange={value => set("employee_id", value || "")} getId={e=>e.id} getLabel={e=>e.name||e.label||"Employee"} getSubLabel={e=>e.label&&e.name?e.label:[e.employee_code,e.department,e.designation,e.email].filter(Boolean).join(" · ")}/></label><label>Leave type<select required value={form.leave_type_id} disabled={busy || !availableTypes.length} onChange={e => set("leave_type_id", e.target.value)}>{availableTypes.map(t => <option key={t.id} value={t.id}>{t.label || `${t.code || ""} · ${t.name || "Leave"}`} {Object.prototype.hasOwnProperty.call(t, "available_days") ? `· ${Number(t.available_days || 0).toFixed(1)} days available` : ""}</option>)}</select></label><label>Start date<input required type="date" min={today} value={form.starts_on} disabled={busy} onChange={e => set("starts_on", e.target.value)}/></label><label>End date<input required type="date" min={form.starts_on || today} value={form.ends_on} disabled={busy || form.duration_unit === "half_day"} onChange={e => set("ends_on", e.target.value)}/></label><label>Duration<select value={form.duration_unit} disabled={busy} onChange={e => set("duration_unit", e.target.value)}><option value="full_day">Full day</option><option value="half_day" disabled={selectedType && selectedType.allows_half_day === false}>Half day</option></select></label><label>Available balance<input value={balanceText} disabled /></label><label style={{ gridColumn: "1 / -1" }}>Reason<textarea maxLength={2000} value={form.reason} disabled={busy} onChange={e => set("reason", e.target.value)} placeholder="Purpose, travel plan, emergency context or handover note."/></label>{selectedType?.requires_document && <div className="hrx-warning" style={{ gridColumn: "1 / -1" }}><Icon name="doc"/><div><b>Supporting document required</b><span>Upload and approve the document from Employee Documents before submitting this leave type.</span></div></div>}</div><div className="hrx-modal-foot"><button type="button" className="btn" disabled={busy} onClick={onClose}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy || !employees.length || !availableTypes.length}><Icon name="calendar" size={15}/>{busy ? "Submitting…" : "Submit Leave"}</button></div></form></div>;
  }

  function ClaimRequestModal({ options, onClose, onCreated, toast }) {
    const claimTypes = options?.claim_types || [{ value: "other", label: "Other" }];
    const today = new Date().toISOString().slice(0, 10);
    const [form, setForm] = React.useState({ claim_type: claimTypes[0]?.value || "other", claim_date: today, amount: "", description: "" });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!options?.claim_store_url || !options?.self_employee?.id) {
        setError("Your employee profile is not available for claim submission.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(options.claim_store_url, {
          method: "POST",
          body: JSON.stringify({
            employee_id: options.self_employee.id,
            claim_type: form.claim_type,
            claim_date: form.claim_date,
            amount: Number(form.amount),
            currency: "INR",
            description: form.description.trim(),
          }),
        });
        toast && toast("Expense claim submitted to Laravel workflow.", "green");
        onCreated && onCreated(body.data);
        onClose();
      } catch (err) {
        setError(err.message || "Expense claim could not be submitted.");
        toast && toast("Claim not submitted: " + (err.message || "save failed"), "orange");
      } finally {
        setBusy(false);
      }
    };
    return <div className="scrim" onClick={busy ? undefined : onClose}><form className="modal hrx-modal" onClick={e => e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Submit Expense Claim</h2><p>Creates a governed Laravel claim for your employee profile with validation, workflow history and audit trail.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Claim not submitted</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee<input value={`${options?.self_employee?.employee_code || "—"} · ${options?.self_employee?.name || "Own profile"}`} disabled /></label><label>Claim type<select value={form.claim_type} disabled={busy} onChange={e => set("claim_type", e.target.value)}>{claimTypes.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}</select></label><label>Claim date<input required type="date" max={today} value={form.claim_date} disabled={busy} onChange={e => set("claim_date", e.target.value)}/></label><label>Amount<input required type="number" min="1" step="0.01" value={form.amount} disabled={busy} onChange={e => set("amount", e.target.value)} placeholder="Amount in INR"/></label><label style={{ gridColumn: "1 / -1" }}>Description<textarea required minLength={10} maxLength={255} value={form.description} disabled={busy} onChange={e => set("description", e.target.value)} placeholder="Describe the expense, project/client context, travel route, bill reference or reimbursement purpose."/></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy}><Icon name="receipt" size={15}/>{busy ? "Submitting…" : "Submit Claim"}</button></div></form></div>;
  }

  function PolicyAcknowledgementModal({ options, onClose, onAcknowledged, toast }) {
    const [policies, setPolicies] = React.useState([]);
    const [selectedKey, setSelectedKey] = React.useState("");
    const [note, setNote] = React.useState("");
    const [busy, setBusy] = React.useState(true);
    const [saving, setSaving] = React.useState(false);
    const [error, setError] = React.useState("");
    React.useEffect(() => {
      let mounted = true;
      const load = async () => {
        setError("");
        if (!options?.policy_acknowledgements_index_url || !options?.can_view_policy_acknowledgements) {
          setBusy(false);
          setError("Policy acknowledgement access is not available for this employee profile.");
          return;
        }
        try {
          setBusy(true);
          const body = await apiJson(options.policy_acknowledgements_index_url);
          if (!mounted) return;
          const rows = body.policies || [];
          setPolicies(rows);
          setSelectedKey(rows[0]?.policy_key || "");
        } catch (err) {
          if (!mounted) return;
          setError(err.message || "Policy acknowledgement list could not be loaded.");
          toast && toast("Policy acknowledgement not loaded: " + (err.message || "request failed"), "orange");
        } finally {
          if (mounted) setBusy(false);
        }
      };
      load();
      return () => { mounted = false; };
    }, [options?.policy_acknowledgements_index_url, options?.can_view_policy_acknowledgements]);
    const selected = policies.find(p => p.policy_key === selectedKey) || policies[0] || null;
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!selected || !options?.policy_acknowledgement_store_url || !options?.self_employee?.id) {
        setError("Select a policy before acknowledging.");
        return;
      }
      try {
        setSaving(true);
        const body = await apiJson(options.policy_acknowledgement_store_url, {
          method: "POST",
          body: JSON.stringify({
            employee_id: options.self_employee.id,
            policy_key: selected.policy_key,
            policy_version: selected.policy_version,
            acknowledgement_note: note.trim() || null,
          }),
        });
        setPolicies(current => current.map(p => p.policy_key === selected.policy_key && Number(p.policy_version) === Number(selected.policy_version) ? { ...p, status: body.data.status, acknowledged_at: body.data.acknowledged_at, acknowledgement_id: body.data.id } : p));
        onAcknowledged && onAcknowledged(body.data);
        toast && toast("Policy acknowledgement saved to Laravel audit trail.", "green");
      } catch (err) {
        setError(err.message || "Policy acknowledgement could not be saved.");
        toast && toast("Policy acknowledgement not saved: " + (err.message || "save failed"), "orange");
      } finally {
        setSaving(false);
      }
    };
    return <div className="scrim" onClick={saving ? undefined : onClose}><form className="modal hrx-modal" onClick={e => e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Policy Acknowledgement</h2><p>Loads the active HR policy from System Settings and records your acknowledgement against your own employee profile in Laravel.</p></div><button type="button" className="icon-btn" disabled={saving} onClick={onClose}><Icon name="x"/></button></div>{busy && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="doc"/><div><b>Loading policy register</b><span>Reading employee-scoped policy acknowledgement status from Laravel.</span></div></div>}{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Policy acknowledgement unavailable</b><span>{error}</span></div></div>}{!busy && !policies.length && !error && <EmptyPanel icon="doc" title="No acknowledgement policy configured" text="Active HR acknowledgement policies from System Settings will appear here."/>}{selected && <><div className="hrx-form-grid"><label>Policy<select value={selectedKey} disabled={saving} onChange={e => setSelectedKey(e.target.value)}>{policies.map(p => <option key={`${p.policy_key}-${p.policy_version}`} value={p.policy_key}>{p.policy_title} · v{p.policy_version} · {p.status}</option>)}</select></label><label>Status<input value={selected.status === "acknowledged" ? `Acknowledged ${selected.acknowledged_at || ""}` : "Pending acknowledgement"} disabled /></label><label style={{ gridColumn: "1 / -1" }}>Policy summary<textarea value={selected.summary || "No policy summary configured."} disabled /></label><label style={{ gridColumn: "1 / -1" }}>Acknowledgement note<textarea maxLength={1000} value={note} disabled={saving || selected.status === "acknowledged"} onChange={e => setNote(e.target.value)} placeholder="Optional note confirming that you reviewed and understood this policy version."/></label></div><Section title="Acknowledgement Register" sub="Employee-scoped policy status from Laravel"><Table rows={policies} columns={[{label:"Policy",render:r=><div><b>{r.policy_title}</b><div className="cell-sub">{r.policy_key} · v{r.policy_version}</div></div>},{label:"Effective",render:r=>r.effective_from || "—"},{label:"Acknowledged",render:r=>r.acknowledged_at || "—"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section></>}<div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Close</Button><button className="btn btn-primary" type="submit" disabled={saving || !selected || selected.status === "acknowledged"}><Icon name="check" size={15}/>{saving ? "Saving…" : "Acknowledge Policy"}</button></div></form></div>;
  }

  function PayrollSummaryModal({ options, onClose, toast }) {
    const [summary, setSummary] = React.useState(null);
    const [busy, setBusy] = React.useState(true);
    const [savingTaxDocument, setSavingTaxDocument] = React.useState(null);
    const [error, setError] = React.useState("");
    React.useEffect(() => {
      let mounted = true;
      const load = async () => {
        setError("");
        if (!options?.payroll_summary_url || !options?.can_view_payroll_summary) {
          setBusy(false);
          setError("Payroll summary access is not available for this employee profile.");
          return;
        }
        try {
          setBusy(true);
          const body = await apiJson(options.payroll_summary_url);
          if (!mounted) return;
          setSummary(body.data || null);
        } catch (err) {
          if (!mounted) return;
          setError(err.message || "Payroll summary could not be loaded.");
          toast && toast("Payroll summary not loaded: " + (err.message || "request failed"), "orange");
        } finally {
          if (mounted) setBusy(false);
        }
      };
      load();
      return () => { mounted = false; };
    }, [options?.payroll_summary_url, options?.can_view_payroll_summary]);
    const assignment = summary?.current_assignment?.structure;
    const totals = summary?.totals || {};
    const acknowledgeTaxDocument = async document => {
      if (!options?.can_acknowledge_tax_documents || !options?.tax_document_acknowledge_url_template || document.status !== "issued") {
        setError("Only issued tax documents can be acknowledged from Employee Self-Service.");
        return;
      }
      try {
        setSavingTaxDocument(document.id);
        setError("");
        const url = options.tax_document_acknowledge_url_template.replace("__DOCUMENT__", document.id);
        const body = await apiJson(url, {
          method: "PATCH",
          body: JSON.stringify({ employee_acknowledgement_note: "Acknowledged from Employee Self-Service payroll summary." }),
        });
        setSummary(current => current ? {
          ...current,
          tax_documents: (current.tax_documents || []).map(row => row.id === body.data.id ? { ...row, ...body.data } : row),
        } : current);
        toast && toast("Tax document acknowledged in Laravel workflow.", "green");
      } catch (err) {
        setError(err.message || "Tax document acknowledgement could not be saved.");
        toast && toast("Tax document not acknowledged: " + (err.message || "save failed"), "orange");
      } finally {
        setSavingTaxDocument(null);
      }
    };
    return <div className="scrim" onClick={busy || savingTaxDocument ? undefined : onClose}><div className="modal hrx-modal" onClick={e => e.stopPropagation()}><div className="hrx-modal-head"><div><h2>My Payroll Summary</h2><p>Laravel payroll summary for your own employee profile. Only approved payroll rows and issued tax documents are visible in self-service mode.</p></div><button type="button" className="icon-btn" disabled={busy || !!savingTaxDocument} onClick={onClose}><Icon name="x"/></button></div>{busy && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="wallet"/><div><b>Loading payroll summary</b><span>Reading scoped payroll, salary assignment and tax document records from Laravel.</span></div></div>}{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Payroll summary unavailable</b><span>{error}</span></div></div>}{summary && <><div className="hrx-grid-2"><div className="hrx-pay-card"><span>Current salary structure</span><strong>{assignment ? money(assignment.monthly_ctc) : "Not assigned"}</strong><small>{assignment ? `${assignment.code} · ${assignment.name} · v${assignment.version}` : "No active salary assignment found"} · {summary.access_mode}</small></div><div className="hrx-pay-card"><span>Recent net payable</span><strong>{money(totals.net_payable || 0)}</strong><small>{totals.payroll_items_count || 0} approved payroll row(s) · {totals.tax_documents_count || 0} tax document(s)</small></div></div>{summary.payroll_items?.length ? <Section title="Payslip Values" sub="Employee-scoped approved payroll rows from Laravel"><Table rows={summary.payroll_items} columns={[{label:"Run",render:r=><div><b>{r.run_number || "—"}</b><div className="cell-sub">{r.period_month}/{r.period_year} · {r.run_status}</div></div>},{label:"Payable days",right:true,render:r=><span className="mono">{r.payable_days}</span>},{label:"Gross",right:true,render:r=><span className="mono">{money(r.gross_earnings)}</span>},{label:"Deductions",right:true,render:r=><span className="mono">-{money(r.total_deductions)}</span>},{label:"Net",right:true,render:r=><b className="mono">{money(r.net_payable)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section> : <EmptyPanel icon="wallet" title="No approved payslip rows found" text="Approved payroll run rows for your employee profile will appear here after payroll is published."/>}{summary.tax_documents?.length ? <Section title="Tax Documents" sub="Issued Form 16 and employee tax artifacts from Laravel"><Table rows={summary.tax_documents} columns={[{label:"Document",render:r=><div><b>{r.document_number}</b><div className="cell-sub">{r.document_type} · FY {r.financial_year} · v{r.version}</div></div>},{label:"Gross",right:true,render:r=><span className="mono">{money(r.gross_salary)}</span>},{label:"TDS",right:true,render:r=><span className="mono">{money(r.tds_deducted)}</span>},{label:"Net paid",right:true,render:r=><b className="mono">{money(r.net_salary_paid)}</b>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Action",render:r=>r.status==="issued"&&options?.can_acknowledge_tax_documents?<button className="hrx-link" disabled={savingTaxDocument===r.id} onClick={()=>acknowledgeTaxDocument(r)}>{savingTaxDocument===r.id?"Saving…":"Acknowledge"}</button>:<span className="cell-sub">{r.acknowledged_at?"Acknowledged":"—"}</span>}]}/></Section> : null}</>}<div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Close</Button></div></div></div>;
  }

  function SelfReviewModal({ options, onClose, onSubmitted, toast }) {
    const [reviews, setReviews] = React.useState([]);
    const [selectedId, setSelectedId] = React.useState("");
    const [form, setForm] = React.useState({ self_score: "4", achievements: "", challenges: "", support_needed: "", strengths: "", improvement_areas: "" });
    const [busy, setBusy] = React.useState(true);
    const [saving, setSaving] = React.useState(false);
    const [error, setError] = React.useState("");
    React.useEffect(() => {
      let mounted = true;
      const load = async () => {
        setError("");
        if (!options?.performance_reviews_index_url || !options?.can_view_performance_reviews) {
          setBusy(false);
          setError("Performance review access is not available for this employee profile.");
          return;
        }
        try {
          setBusy(true);
          const url = new URL(options.performance_reviews_index_url, location.origin);
          url.searchParams.set("per_page", "8");
          const body = await apiJson(url.toString());
          if (!mounted) return;
          const rows = body.data || [];
          setReviews(rows);
          const active = rows.find(r => r.status === "draft") || rows[0] || null;
          if (active) {
            setSelectedId(String(active.id));
            setForm(current => ({
              ...current,
              self_score: active.self_score ? String(active.self_score) : current.self_score,
              achievements: active.kra_summary?.achievements || "",
              challenges: active.kra_summary?.challenges || "",
              support_needed: active.kra_summary?.support_needed || "",
              strengths: active.strengths || "",
              improvement_areas: active.improvement_areas || "",
            }));
          }
        } catch (err) {
          if (!mounted) return;
          setError(err.message || "Performance reviews could not be loaded.");
          toast && toast("Self-review not loaded: " + (err.message || "request failed"), "orange");
        } finally {
          if (mounted) setBusy(false);
        }
      };
      load();
      return () => { mounted = false; };
    }, [options?.performance_reviews_index_url, options?.can_view_performance_reviews]);
    const selected = reviews.find(r => String(r.id) === String(selectedId)) || null;
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const canSubmit = selected && selected.status === "draft" && options?.can_submit_self_review && options?.performance_review_self_submit_url_template;
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!canSubmit) {
        setError("Select a draft review before submitting your self-review.");
        return;
      }
      try {
        setSaving(true);
        const url = options.performance_review_self_submit_url_template.replace("__REVIEW__", selected.id);
        const body = await apiJson(url, {
          method: "PATCH",
          body: JSON.stringify({
            self_score: Number(form.self_score),
            kra_summary: {
              achievements: form.achievements.trim() || null,
              challenges: form.challenges.trim() || null,
              support_needed: form.support_needed.trim() || null,
            },
            strengths: form.strengths.trim() || null,
            improvement_areas: form.improvement_areas.trim() || null,
          }),
        });
        setReviews(current => current.map(r => String(r.id) === String(body.data.id) ? body.data : r));
        onSubmitted && onSubmitted(body.data);
        toast && toast("Performance self-review submitted to Laravel workflow.", "green");
      } catch (err) {
        setError(err.message || "Self-review could not be submitted.");
        toast && toast("Self-review not submitted: " + (err.message || "save failed"), "orange");
      } finally {
        setSaving(false);
      }
    };
    return <div className="scrim" onClick={saving ? undefined : onClose}><form className="modal hrx-modal" onClick={e => e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Performance Self Review</h2><p>Loads your own Laravel performance review and submits score, KRA summary, strengths and improvement areas through the governed workflow.</p></div><button type="button" className="icon-btn" disabled={saving} onClick={onClose}><Icon name="x"/></button></div>{busy && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="star"/><div><b>Loading self-review</b><span>Reading employee-scoped performance reviews from Laravel.</span></div></div>}{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Self-review unavailable</b><span>{error}</span></div></div>}{!busy && !reviews.length && !error && <EmptyPanel icon="star" title="No performance review assigned" text="Draft or submitted performance reviews assigned to your employee profile will appear here."/>}{reviews.length > 0 && <><div className="hrx-form-grid"><label>Review cycle<select value={selectedId} disabled={saving} onChange={e => setSelectedId(e.target.value)}>{reviews.map(r => <option key={r.id} value={r.id}>{r.review_number} · {r.cycle?.name || "Performance cycle"} · {r.status}</option>)}</select></label><label>Self score<input required type="number" min="1" max="10" step="0.1" value={form.self_score} disabled={saving || !canSubmit} onChange={e => set("self_score", e.target.value)}/></label><label style={{ gridColumn: "1 / -1" }}>Achievements<textarea maxLength={2000} value={form.achievements} disabled={saving || !canSubmit} onChange={e => set("achievements", e.target.value)} placeholder="Summarize achieved KRAs, delivery outcomes, customer/site contributions or measurable wins."/></label><label style={{ gridColumn: "1 / -1" }}>Challenges<textarea maxLength={2000} value={form.challenges} disabled={saving || !canSubmit} onChange={e => set("challenges", e.target.value)} placeholder="Mention blockers, dependency issues, quality/safety risks or delayed approvals."/></label><label style={{ gridColumn: "1 / -1" }}>Support needed<textarea maxLength={2000} value={form.support_needed} disabled={saving || !canSubmit} onChange={e => set("support_needed", e.target.value)} placeholder="Training, tools, manpower, manager support or process improvements needed."/></label><label>Strengths<textarea maxLength={2000} value={form.strengths} disabled={saving || !canSubmit} onChange={e => set("strengths", e.target.value)} placeholder="Strengths demonstrated this cycle."/></label><label>Improvement areas<textarea maxLength={2000} value={form.improvement_areas} disabled={saving || !canSubmit} onChange={e => set("improvement_areas", e.target.value)} placeholder="Specific improvement areas for next cycle."/></label></div>{selected && <Section title="Assigned Review Status" sub="Employee-scoped review from Laravel"><Table rows={[selected]} columns={[{label:"Review",render:r=><div><b>{r.review_number}</b><div className="cell-sub">{r.period_start} to {r.period_end}</div></div>},{label:"Cycle",render:r=>r.cycle?.name || "—"},{label:"Manager",render:r=>r.manager?.name || "—"},{label:"Self score",right:true,render:r=><span className="mono">{r.self_score || "—"}</span>},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section>}{selected && selected.status !== "draft" && <div className="hrx-warning"><Icon name="check"/><div><b>Self-review already submitted</b><span>This review is no longer in draft status. Further changes require the manager/HR performance workflow.</span></div></div>}</>}<div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Close</Button><button className="btn btn-primary" type="submit" disabled={saving || !canSubmit}><Icon name="star" size={15}/>{saving ? "Submitting…" : "Submit Self Review"}</button></div></form></div>;
  }

  function HelpdeskRequestModal({ options, onClose, onCreated, toast }) {
    const categoryOptions = (options?.categories || options?.helpdesk_categories || ["other"]).map(item => typeof item === "string" ? { value: item, label: item.replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase()) } : item);
    const priorityOptions = (options?.priorities || ["medium"]).map(item => typeof item === "string" ? { value: item, label: item.replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase()) } : item);
    const employeeOptions = options?.self_employee ? [options.self_employee] : (options?.helpdesk_request_employees || []);
    const storeUrl = options?.store_url || options?.helpdesk_store_url;
    const [form, setForm] = React.useState({ employee_id: employeeOptions[0]?.id ? String(employeeOptions[0].id) : "", category: categoryOptions[0]?.value || "other", priority: "medium", subject: "", description: "" });
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    const set = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const submit = async ev => {
      ev.preventDefault();
      setError("");
      if (!storeUrl || !form.employee_id) {
        setError("An employee profile is required for HR request submission.");
        return;
      }
      try {
        setBusy(true);
        const body = await apiJson(storeUrl, {
          method: "POST",
          body: JSON.stringify({
            employee_id: Number(form.employee_id),
            category: form.category,
            priority: form.priority,
            subject: form.subject.trim(),
            description: form.description.trim(),
          }),
        });
        onCreated(body.data);
        toast && toast("HR request " + (body.data.ticket_number || "") + " submitted.", "green");
        onClose();
      } catch (err) {
        setError(err.message);
      } finally {
        setBusy(false);
      }
    };
    return <div className="scrim" onClick={busy ? undefined : onClose}><form className="modal hrx-modal" onClick={e => e.stopPropagation()} onSubmit={submit}><div className="hrx-modal-head"><div><h2>Raise HR Request</h2><p>Creates a governed Laravel helpdesk ticket with validation, employee scope and audit history.</p></div><button type="button" className="icon-btn" disabled={busy} onClick={onClose}><Icon name="x"/></button></div>{error && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Request not submitted</b><span>{error}</span></div></div>}<div className="hrx-form-grid"><label>Employee<SearchablePeoplePicker items={employeeOptions} selected={form.employee_id} mode="single" required disabled={busy || employeeOptions.length <= 1} placeholder="Search employee name, code, department..." emptyText="No matching employees" onChange={value => set("employee_id", value || "")} getId={employee=>employee.id} getLabel={employee=>employee.name||employee.label||"Employee"} getSubLabel={employee=>employee.label&&employee.name?employee.label:[employee.employee_code,employee.department,employee.designation,employee.email].filter(Boolean).join(" · ")}/></label><label>Category<select value={form.category} disabled={busy} onChange={e => set("category", e.target.value)}>{categoryOptions.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}</select></label><label>Priority<select value={form.priority} disabled={busy} onChange={e => set("priority", e.target.value)}>{priorityOptions.map(p => <option key={p.value} value={p.value}>{p.label}</option>)}</select></label><label>Subject<input required maxLength={255} value={form.subject} disabled={busy} onChange={e => set("subject", e.target.value)} placeholder="Short request subject"/></label><label style={{ gridColumn: "1 / -1" }}>Description<textarea required minLength={10} value={form.description} disabled={busy} onChange={e => set("description", e.target.value)} placeholder="Describe the HR support required, relevant dates, documents or payroll/attendance context."/></label></div><div className="hrx-modal-foot"><Button type="button" onClick={onClose}>Cancel</Button><button className="btn btn-primary" type="submit" disabled={busy || !storeUrl || !form.employee_id}><Icon name="headset" size={15}/>{busy ? "Submitting…" : "Submit HR Request"}</button></div></form></div>;
  }

  function EmployeeSelfService({ role = { id:"employee", person:"Employee User" }, toast }) {
    const [state, update] = useHRState(role, toast);
    const [helpdeskOpen, setHelpdeskOpen] = React.useState(false);
    const [claimOpen, setClaimOpen] = React.useState(false);
    const [leaveOpen, setLeaveOpen] = React.useState(false);
    const [attendanceOpen, setAttendanceOpen] = React.useState(false);
    const [payrollOpen, setPayrollOpen] = React.useState(false);
    const [selfReviewOpen, setSelfReviewOpen] = React.useState(false);
    const [policyOpen, setPolicyOpen] = React.useState(false);
    React.useEffect(()=>{ location.hash="#ess"; },[]);
    const helpdeskOptions = window.Builder360Server?.hr_helpdesk_options || null;
    const selfServiceOptions = window.Builder360Server?.hr_self_service_options || null;
    const serverEmployee = selfServiceOptions?.self_employee || helpdeskOptions?.self_employee || null;
    const hasSelfEmployee = !!serverEmployee?.id;
    const employee = {
      id: serverEmployee?.id || "self-service-api-required",
      name: serverEmployee?.name || "Employee Self-Service",
      code: serverEmployee?.employee_code || "API REQUIRED",
      designation: serverEmployee?.designation || "Server employee profile required",
      department: serverEmployee?.department || "Employee API required",
      project: serverEmployee?.department || "Self-service API required",
      status: hasSelfEmployee ? "active" : "API required",
      companyId: serverEmployee?.company_id || null,
    };
    const summary = selfServiceOptions?.summary || {};
    const attendanceRows = Array.isArray(selfServiceOptions?.recent_attendance) ? selfServiceOptions.recent_attendance : [];
    const openHrRequest = () => {
      if (!helpdeskOptions?.can_create || !helpdeskOptions?.store_url || !helpdeskOptions?.self_employee?.id) {
        unavailableAction(update, "Employee HR request creation", employee.id);
        return;
      }
      setHelpdeskOpen(true);
    };
    const openClaimRequest = () => {
      if (!selfServiceOptions?.can_create_claim || !selfServiceOptions?.claim_store_url || !selfServiceOptions?.self_employee?.id) {
        unavailableAction(update, "Employee claim creation", employee.id);
        return;
      }
      setClaimOpen(true);
    };
    const openLeaveRequest = () => {
      if (!selfServiceOptions?.can_create_leave_request || !selfServiceOptions?.leave_request_store_url || !selfServiceOptions?.self_employee?.id) {
        unavailableAction(update, "Employee leave request creation", employee.id);
        return;
      }
      setLeaveOpen(true);
    };
    const openAttendanceRequest = () => {
      if (!selfServiceOptions?.can_create_attendance_regularization || !selfServiceOptions?.attendance_regularization_store_url || !selfServiceOptions?.self_employee?.id) {
        unavailableAction(update, "Employee attendance regularization", employee.id);
        return;
      }
      setAttendanceOpen(true);
    };
    const openPayrollSummary = () => {
      if (!selfServiceOptions?.can_view_payroll_summary || !selfServiceOptions?.payroll_summary_url || !selfServiceOptions?.self_employee?.id) {
        unavailableAction(update, "Employee payroll summary viewing", employee.id);
        return;
      }
      setPayrollOpen(true);
    };
    const openSelfReview = () => {
      if (!selfServiceOptions?.can_view_performance_reviews || !selfServiceOptions?.performance_reviews_index_url || !selfServiceOptions?.performance_review_self_submit_url_template || !selfServiceOptions?.self_employee?.id) {
        unavailableAction(update, "Employee performance self review", employee.id);
        return;
      }
      setSelfReviewOpen(true);
    };
    const openPolicyAcknowledgement = () => {
      if (!selfServiceOptions?.can_view_policy_acknowledgements || !selfServiceOptions?.can_create_policy_acknowledgement || !selfServiceOptions?.policy_acknowledgements_index_url || !selfServiceOptions?.policy_acknowledgement_store_url || !selfServiceOptions?.self_employee?.id) {
        unavailableAction(update, "Employee policy acknowledgement", employee.id);
        return;
      }
      setPolicyOpen(true);
    };
    const onHelpdeskCreated = ticket => {
      update((s,actor)=>{
        s.tickets.unshift({ id: ticket.ticket_number || `HRT-${ticket.id}`, employee: ticket.employee?.name || helpdeskOptions.self_employee.name || actor, category: ticket.category || "other", priority: ticket.priority || "medium", assignee: ticket.assigned_to?.name || "Unassigned", status: ticket.status || "open", sla: "New" });
        addAudit(s,actor,"Submitted Laravel HR helpdesk ticket",ticket.ticket_number || ticket.id,"Helpdesk");
      }, "HR request added to your ticket queue");
    };
    const onClaimCreated = claim => {
      update((s,actor)=>{
        s.claims.unshift({ id: claim.claim_number || `CLM-${claim.id}`, employee: claim.employee?.name || selfServiceOptions?.self_employee?.name || actor, type: claim.claim_type || "other", project: "Self Service", amount: claim.amount || 0, status: claim.status || "submitted" });
        addAudit(s,actor,"Submitted Laravel expense claim",claim.claim_number || claim.id,"Claims");
      }, "Expense claim added to your reimbursement queue");
    };
    const onLeaveCreated = leave => {
      update((s,actor)=>{
        s.leaveRequests.unshift({ id: leave.request_number || `LV-${leave.id}`, employee: leave.employee?.name || selfServiceOptions?.self_employee?.name || actor, type: leave.leave_type?.name || "Leave", dates: `${leave.starts_on || ""} to ${leave.ends_on || ""}`, days: leave.requested_days || 0, balance: employee.leaveBalance, status: leave.status || "submitted", company: employee.companyId });
        addAudit(s,actor,"Submitted Laravel leave request",leave.request_number || leave.id,"Leave");
      }, "Leave request added to your approval queue");
    };
    const onAttendanceRegularizationCreated = request => {
      update((s,actor)=>{
        s.attendance.exceptions.unshift({ id: request.request_number || `AR-${request.id}`, employee: request.employee?.name || selfServiceOptions?.self_employee?.name || actor, issue: "Attendance regularization request", time: request.work_date || "Submitted", status: request.status || "submitted" });
        addAudit(s,actor,"Submitted Laravel attendance regularization",request.request_number || request.id,"Attendance");
      }, "Attendance request added to your approval queue");
    };
    const onSelfReviewSubmitted = review => {
      update((s,actor)=>{
        const local = s.reviews.find(r => r.employee === employee.name || r.employee === review.employee?.name);
        if (local) {
          local.self = Number(review.self_score || local.self || 0);
          local.status = "Self Submitted";
        }
        addAudit(s,actor,"Submitted Laravel performance self-review",review.review_number || review.id,"Performance");
      }, "Performance self-review submitted");
    };
    const onPolicyAcknowledged = acknowledgement => {
      update((s,actor)=>{
        addAudit(s,actor,"Acknowledged Laravel HR policy",`${acknowledgement.policy_key} v${acknowledgement.policy_version}`,"Policy");
      }, "Policy acknowledgement recorded");
    };
    const openRequests = Number.isFinite(Number(summary.open_requests)) ? Number(summary.open_requests) : 0;
    const attendanceValue = Number.isFinite(Number(summary.attendance_percent)) ? Number(summary.attendance_percent) : "—";
    const attendanceUnit = Number.isFinite(Number(summary.attendance_percent)) ? "%" : "";
    const leaveValue = Number.isFinite(Number(summary.leave_available_days)) ? Number(summary.leave_available_days) : "—";
    const leaveUnit = Number.isFinite(Number(summary.leave_available_days)) ? "days" : "";
    const payslipPeriod = summary.latest_payslip_period || "—";
    const payslipStatus = summary.latest_payslip_status || "Payroll API required";
    const attendanceClass = code => code==="P"?"p":code==="L"||code==="HD"?"l":code==="H"?"h":code==="WO"?"wo":code==="A"?"l":"";
    return <div className="page page-wide">
      <PageHead crumbs={["People","Employee Self-Service"]} title={`Hello, ${employee.name.split(" ")[0]}`} sub="Your profile, attendance, leave, payroll, documents and requests." actions={<Badge tone="b-violet">EMPLOYEE SELF-SERVICE</Badge>}/>
      {!hasSelfEmployee && <div className="hrx-warning" style={{ marginBottom: 12 }}><Icon name="alert"/><div><b>Employee Self-Service API required</b><span>No local prototype employee identity is shown. Link the signed-in user to an authorized Laravel employee profile to enable ESS actions and personal records.</span></div></div>}
      <div className="hrx-ess-hero"><div><Avatar name={employee.name} size={58}/><div><h2>{employee.name}</h2><p>{employee.code} · {employee.designation} · {employee.project}</p></div></div><StatePill>{employee.status}</StatePill></div>
      <KpiGrid><Stat label="Attendance" value={attendanceValue} unit={attendanceUnit} icon="check" tone="green" sub={summary.attendance_marked_days ? `${summary.attendance_marked_days} marked day(s)` : "Attendance API required"}/><Stat label="Leave Balance" value={leaveValue} unit={leaveUnit} icon="calendar" tone="blue" sub={selfServiceOptions?.leave_types?.length ? "Approved balance ledger" : "Leave API required"}/><Stat label="Latest Payslip" value={payslipPeriod} icon="wallet" tone="violet" sub={payslipStatus}/><Stat label="Open Requests" value={openRequests} icon="headset" tone="orange" sub="Scoped self-service queue"/></KpiGrid>
      <div className="hrx-quick-actions">
        <button onClick={openAttendanceRequest}><Icon name="pin"/><b>Regularize attendance</b><span>{selfServiceOptions?.can_create_attendance_regularization ? "Laravel approval workflow" : "Attendance API required"}</span></button>
        <button onClick={openLeaveRequest}><Icon name="calendar"/><b>Apply leave</b><span>{selfServiceOptions?.can_create_leave_request ? "Laravel leave workflow" : "Leave API required"}</span></button>
        <button onClick={openClaimRequest}><Icon name="receipt"/><b>New claim</b><span>{selfServiceOptions?.can_create_claim ? "Laravel claim workflow" : "Claim API required"}</span></button>
        <button onClick={openHrRequest}><Icon name="headset"/><b>HR request</b><span>{helpdeskOptions?.can_create ? "Laravel ticket workflow" : "Helpdesk API required"}</span></button>
      </div>
      <div className="hrx-grid-2"><Section title="My Attendance" sub={attendanceRows.length ? "Recent Laravel attendance records" : "Attendance API required"}>{attendanceRows.length ? <div className="hrx-calendar-strip">{attendanceRows.map((row,i)=><span className={attendanceClass(row.status_code)} key={row.id || i}><b>{row.day_label || row.work_date || "—"}</b><small>{row.status_code || "—"}</small></span>)}</div> : <EmptyPanel icon="calendar" title="No attendance records loaded" text="Recent attendance appears after Laravel self-service attendance data is available."/>}</Section><Section title="My Actions" sub="Requests and acknowledgements"><div className="hrx-list"><div className="hrx-list-row"><div className="hrx-icon"><Icon name="doc"/></div><div className="hrx-grow"><b>Policy acknowledgement</b><span>{selfServiceOptions?.can_create_policy_acknowledgement ? "Laravel policy acknowledgement" : "Policy API required"}</span></div><button className="hrx-link" onClick={openPolicyAcknowledgement}>Review</button></div><div className="hrx-list-row"><div className="hrx-icon"><Icon name="star"/></div><div className="hrx-grow"><b>Performance self review</b><span>{selfServiceOptions?.can_submit_self_review ? "Laravel performance workflow" : "Performance API required"}</span></div><button className="hrx-link" onClick={openSelfReview}>Continue</button></div><div className="hrx-list-row"><div className="hrx-icon"><Icon name="wallet"/></div><div className="hrx-grow"><b>Payroll summary</b><span>{selfServiceOptions?.can_view_payroll_summary ? "Laravel payroll summary" : "Payroll API required"}</span></div><button className="hrx-link" onClick={openPayrollSummary}>View</button></div></div></Section></div>
      {attendanceOpen && <AttendanceRegularizationModal options={selfServiceOptions} onClose={() => setAttendanceOpen(false)} onCreated={onAttendanceRegularizationCreated} toast={toast}/>}
      {leaveOpen && <LeaveRequestModal options={selfServiceOptions} onClose={() => setLeaveOpen(false)} onCreated={onLeaveCreated} toast={toast}/>}
      {claimOpen && <ClaimRequestModal options={selfServiceOptions} onClose={() => setClaimOpen(false)} onCreated={onClaimCreated} toast={toast}/>}
      {payrollOpen && <PayrollSummaryModal options={selfServiceOptions} onClose={() => setPayrollOpen(false)} toast={toast}/>}
      {selfReviewOpen && <SelfReviewModal options={selfServiceOptions} onClose={() => setSelfReviewOpen(false)} onSubmitted={onSelfReviewSubmitted} toast={toast}/>}
      {policyOpen && <PolicyAcknowledgementModal options={selfServiceOptions} onClose={() => setPolicyOpen(false)} onAcknowledged={onPolicyAcknowledged} toast={toast}/>}
      {helpdeskOpen && <HelpdeskRequestModal options={helpdeskOptions} onClose={() => setHelpdeskOpen(false)} onCreated={onHelpdeskCreated} toast={toast}/>}
    </div>;
  }

  function PartnerPortal({ role = { id:"channel_partner", person:"Partner User" }, toast }) {
    const partnerName = role.person || role.name || "Partner User";
    const serverPortal = window.Builder360Server?.partner_portal || null;
    const hasServerPortal = !!serverPortal;
    const amount = value => "₹" + Number(value || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });
    const partnerUnavailable = label => toast && toast(label + " requires the governed Laravel partner document workflow.", "orange");
    const openPartnerDocument = row => {
      if (!row?.download_url) return partnerUnavailable("Partner document access");
      window.location.href = row.download_url;
    };
    const leads = hasServerPortal ? (serverPortal.my_leads || []).map(r => ({ id: r.lead_code || String(r.id), customer: r.customer || "Unassigned customer", project: r.project || "Unassigned project", source: r.source || "Partner", stage: r.stage || r.status || "Open", budget: amount(r.expected_value) })) : [];
    const visits = hasServerPortal ? (serverPortal.site_visits || []).map(r => ({ id: r.visit_number || String(r.id), customer: r.customer || "Unassigned customer", project: r.project || "Unassigned project", when: r.scheduled_at ? new Date(r.scheduled_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : "Not scheduled", executive: r.assigned_to || "Sales desk", status: r.status || "Scheduled" })) : [];
    const bookings = hasServerPortal ? (serverPortal.bookings || []).map(r => ({ id: r.booking_code || String(r.id), customer: r.customer || "Unassigned customer", unit: r.unit || "Unit pending", value: amount(r.net_receivable), status: r.status || "Confirmed", payout: (serverPortal.commission_summary?.total_items || 0) > 0 ? "Linked" : "Pending" })) : [];
    const collections = hasServerPortal ? (serverPortal.collections_follow_up || []).map(r => ({ id: String(r.id), customer: r.customer || "Unassigned customer", due: amount(r.amount), milestone: r.milestone || "Payment milestone", date: r.due_on || "Not set", status: r.status || "Pending" })) : [];
    const docs = hasServerPortal ? (serverPortal.documents || []).map(r => ({ id: r.document_number || String(r.id), name: r.title || "Document", project: r.category || r.owner_type || "Partner", status: r.is_expired ? "Expired" : (r.status || "Active"), access: r.download_url ? "Download" : "Unavailable", download_url: r.download_url || "" })) : [];
    const commissionStat = hasServerPortal ? amount(serverPortal.commission_summary?.approved_amount) : amount(0);
    const commissionCard = hasServerPortal
      ? amount(Number(serverPortal.commission_summary?.approved_amount || 0) + Number(serverPortal.commission_summary?.pending_amount || 0))
      : amount(0);
    return <div className="page page-wide">
      <PageHead crumbs={["Partner Portal","Dashboard"]} title="Partner Dashboard" sub="Scoped partner workspace for leads, visits, bookings, collections and commission visibility." actions={[<Badge key="role" tone="b-violet">{role.name || "Partner"}</Badge>, <Badge key="scope" tone="b-slate">Partner portal</Badge>]}/>
      <div className="hrx-demo-banner"><Icon name="shield" size={17}/><div><b>Partner restricted access</b><span>No HR, payroll, finance approval, administration or system settings modules are exposed for this role.</span></div><Badge tone={hasServerPortal?"b-green":"b-orange"}>{hasServerPortal?"SERVER SCOPED":"API REQUIRED"}</Badge></div>
      <KpiGrid><Stat label="My Leads" value={leads.length} icon="users" tone="accent" sub="Assigned partner pipeline"/><Stat label="Site Visits" value={visits.length} icon="calendar" tone="blue" sub="Scheduled and completed"/><Stat label="Bookings" value={bookings.length} icon="tag" tone="green" sub="Partner-attributed"/><Stat label="Collection Follow-up" value={hasServerPortal ? amount(serverPortal.metrics?.open_collection_amount) : collections.length} icon="rupee" tone="orange" sub="Open customer dues"/><Stat label="Commission" value={commissionStat} icon="wallet" tone="violet" sub="Approved eligible payout"/></KpiGrid>
      <div className="hrx-grid-2"><Section title="My Leads" sub={`Partner-visible pipeline for ${partnerName}`}><Table rows={leads} columns={[{label:"Lead",render:r=><Person employee={r.customer} sub={r.id}/>},{label:"Project",key:"project"},{label:"Source",key:"source"},{label:"Budget",key:"budget"},{label:"Stage",render:r=><StatePill>{r.stage}</StatePill>}]}/></Section><Section title="Site Visits" sub="Calendar-linked sales visits"><Table rows={visits} columns={[{label:"Customer",render:r=><Person employee={r.customer} sub={r.project}/>},{label:"When",key:"when"},{label:"Executive",key:"executive"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section><Section title="Bookings & Payout Eligibility" sub="Partner-attributed bookings"><Table rows={bookings} columns={[{label:"Booking",render:r=><Person employee={r.customer} sub={r.id}/>},{label:"Unit",key:"unit"},{label:"Value",key:"value"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Payout",render:r=><Badge tone={r.payout==="Linked"||r.payout==="Eligible"?"b-green":"b-orange"}>{r.payout}</Badge>}]}/></Section><Section title="Collections Follow-up" sub="Customer dues linked to partner deals"><Table rows={collections} columns={[{label:"Customer",render:r=><Person employee={r.customer} sub={r.id}/>},{label:"Due",key:"due"},{label:"Milestone",key:"milestone"},{label:"Target",key:"date"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>}]}/></Section></div>
      <Section title="Commission Summary & Documents" sub={hasServerPortal ? "Read-only server-scoped Laravel records" : "Partner Portal API required; no local partner rows are fabricated."}><div className="hrx-grid-2"><div className="hrx-pay-card"><span>Current projected commission</span><strong>{commissionCard}</strong><small>Subject to booking, collection and management approval rules.</small></div><Table rows={docs} columns={[{label:"Document",key:"name"},{label:"Scope",key:"project"},{label:"Status",render:r=><StatePill>{r.status}</StatePill>},{label:"Access",render:r=><button className="hrx-link" disabled={!r.download_url} onClick={() => openPartnerDocument(r)}>{r.access}</button>}]}/></div></Section>
    </div>;
  }

  // Integrate with the existing navigation before the app shell reads DB.sidebar.
  const operations=window.DB.sidebar.find(g=>g.group==="Operations");
  if(operations) operations.items=operations.items.filter(x=>x.id!=="hr");
  if(!window.DB.sidebar.some(g=>g.group==="People")){
    const opsIndex=window.DB.sidebar.findIndex(g=>g.group==="Operations");
    window.DB.sidebar.splice(Math.max(0,opsIndex),0,{group:"People",items:[{id:"hr",label:"HR & Employees",icon:"id",badge:6},{id:"ess",label:"Employee Self-Service",icon:"user"}]});
  }
  const extraRoles=[
    {id:"employee",name:"Employee",person:"Employee User",title:"Employee Self-Service",initials:"EU",color:"#0ea5a4"},
    {id:"payroll",name:"Payroll Admin",person:"Meera Joshi",title:"Payroll Executive",initials:"MJ",color:"#7c3aed"},
    {id:"recruiter",name:"Recruiter",person:"Recruitment User",title:"Talent Acquisition",initials:"RU",color:"#2570eb"},
    {id:"auditor",name:"Auditor",person:"Nikhil Rao",title:"Internal Auditor",initials:"NR",color:"#64748b"},
    {id:"compliance",name:"Compliance Officer",person:"Kavya Reddy",title:"Compliance Lead",initials:"KR",color:"#dc2f3a"},
    {id:"channel_partner",name:"Channel Partner",person:"Channel Partner User",title:"External Channel Partner",initials:"CP",color:"#f59e0b"},
    {id:"executive_partner_broker",name:"Executive Partner (Broker)",person:"Executive Partner User",title:"Executive Partner Broker",initials:"EP",color:"#14b8a6"},
  ];
  extraRoles.forEach(r=>{if(!window.DB.roles.some(x=>x.id===r.id))window.DB.roles.push(r);});
  window.HR=HRManagement;
  window.EmployeeSelfService=EmployeeSelfService;
  window.PartnerPortal=PartnerPortal;
})();
