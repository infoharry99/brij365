import Alpine from '@alpinejs/csp';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import '@fortawesome/fontawesome-free/css/all.min.css';

window.Alpine = Alpine;

window.getChatComponent = function() {
    const el = document.querySelector('[x-data="chatRealtime"]');
    if (el && window.Alpine) {
        return window.Alpine.$data(el);
    }
    return null;
};

window.handleTimelineClick = function(event) {
    window.getChatComponent()?.handleTimelineClick?.(event);
};

window.deleteMessage = function(event) {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }
    window.getChatComponent()?.deleteMessage?.(event);
};

window.submitTimelineAction = function(event) {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }
    window.getChatComponent()?.submitTimelineAction?.(event);
};

window.selectReply = function(event) {
    window.getChatComponent()?.selectReply?.(event);
};

window.handlePaste = function(event) {
    window.getChatComponent()?.handlePaste?.(event);
};

window.filterPeople = function(event) {
    const input = event?.currentTarget || event?.target;
    if (! input) return;

    const picker = input.closest('.people-search-picker') || input.closest('[x-data="peopleSearch"]') || input.closest('.tm-assignee-overlay') || input.closest('fieldset') || input.closest('details');
    if (! picker) return;

    const query = String(input.value || '').trim().toLowerCase();
    const words = query.split(/\s+/).filter(Boolean);

    picker.querySelectorAll('[data-person-search]').forEach((row) => {
        const haystack = String(row.getAttribute('data-person-search') || row.dataset.personSearch || '').toLowerCase();
        const matches = words.length === 0 || words.every((word) => haystack.includes(word));
        row.hidden = ! matches;
        if (matches) {
            row.classList.remove('is-hidden');
            row.style.setProperty('display', '', 'important');
        } else {
            row.classList.add('is-hidden');
            row.style.setProperty('display', 'none', 'important');
        }
    });
};

const BUILDER_SIDEBAR_KEY = 'builder360.sidebar.collapsed';
const BUILDER_SHELL_BREAKPOINT = 860;
const TASK_WORKSPACE_KEY = 'builder360.task.workspace.open';
const TASK_WORKSPACE_BREAKPOINT = 900;
const PEOPLE_WORKSPACE_BREAKPOINT = 900;

Alpine.data('builderShell', () => ({
    navigationOpen: false,
    sidebarCollapsed: false,
    theme: 'light',
    themeBusy: false,
    themeError: '',
    menuTrigger: null,

    init() {
        this.theme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
        const storedSidebar = this.storageGet(BUILDER_SIDEBAR_KEY);
        this.sidebarCollapsed = ! this.isMobile() && (storedSidebar === null || storedSidebar === undefined ? true : storedSidebar === '1');
    },

    get navigationClasses() {
        return {
            'nav-open': this.navigationOpen,
            'sidebar-collapsed': this.sidebarCollapsed && ! this.isMobile(),
        };
    },

    get sidebarClasses() {
        return {
            'is-open': this.navigationOpen,
            'is-collapsed': this.sidebarCollapsed && ! this.isMobile(),
        };
    },

    get navigationExpanded() {
        return String(this.isMobile() ? this.navigationOpen : ! this.sidebarCollapsed);
    },

    get navigationLabel() {
        if (this.isMobile()) return this.navigationOpen ? 'Close navigation' : 'Open navigation';

        return this.sidebarCollapsed ? 'Expand navigation' : 'Collapse navigation';
    },

    get targetTheme() {
        return this.theme === 'dark' ? 'light' : 'dark';
    },

    get themeLabel() {
        return `Switch to ${this.targetTheme} theme`;
    },

    handleMenuToggle(event) {
        this.menuTrigger = event?.currentTarget || this.menuTrigger;
        if (this.isMobile()) {
            this.toggleNavigation();
            return;
        }

        this.toggleSidebar();
    },

    toggleNavigation() {
        this.navigationOpen = ! this.navigationOpen;

        if (this.navigationOpen) {
            this.$nextTick(() => this.$refs.sidebar?.focus());
        }
    },

    closeNavigation(restoreFocus = false) {
        const wasOpen = this.navigationOpen;
        this.navigationOpen = false;

        if (restoreFocus && wasOpen) {
            this.$nextTick(() => this.menuTrigger?.focus());
        }
    },

    toggleSidebar() {
        if (this.isMobile()) {
            this.toggleNavigation();
            return;
        }

        this.sidebarCollapsed = ! this.sidebarCollapsed;
        this.storageSet(BUILDER_SIDEBAR_KEY, this.sidebarCollapsed ? '1' : '0');
    },

    handleEscape() {
        if (this.navigationOpen) this.closeNavigation(true);
    },

    handleResize() {
        if (! this.isMobile()) {
            this.closeNavigation();
        }
    },

    async changeTheme(event) {
        event.preventDefault();
        if (this.themeBusy) return;

        const form = event.currentTarget;
        const previousTheme = this.theme;
        const nextTheme = this.targetTheme;
        const data = new FormData(form);
        data.set('theme', nextTheme);

        this.themeBusy = true;
        this.themeError = '';
        this.theme = nextTheme;
        document.documentElement.dataset.theme = nextTheme;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });

            if (! response.ok) throw new Error('Theme preference could not be saved.');
        } catch (_error) {
            this.theme = previousTheme;
            document.documentElement.dataset.theme = previousTheme;
            this.themeError = 'Theme preference could not be saved. Please try again.';
        } finally {
            this.themeBusy = false;
        }
    },

    isMobile() {
        return window.innerWidth <= BUILDER_SHELL_BREAKPOINT;
    },

    storageGet(key) {
        try { return window.localStorage.getItem(key); } catch (_error) { return null; }
    },

    storageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (_error) { /* Preferences may be unavailable. */ }
    },
}));

Alpine.data('autoSubmitForm', () => ({
    submitContext() {
        this.$root.requestSubmit();
    },
}));

Alpine.data('serverFormState', () => ({
    busy: false,
    pageShowHandler: null,

    init() {
        this.pageShowHandler = () => { this.busy = false; };
        window.addEventListener('pageshow', this.pageShowHandler);
    },

    destroy() {
        if (this.pageShowHandler) window.removeEventListener('pageshow', this.pageShowHandler);
    },

    get busyAria() {
        return String(this.busy);
    },

    get submitLabel() {
        return this.busy
            ? (this.$root.dataset.busyLabel || 'Saving…')
            : (this.$root.dataset.idleLabel || 'Save');
    },

    beginSubmit(event) {
        if (this.busy) {
            event.preventDefault();
            return;
        }

        this.busy = true;
    },
}));

Alpine.data('profilePhotoPicker', () => ({
    selectedName: '',
    previewUrl: '',
    error: '',
    busy: false,

    choosePhoto(event) {
        const file = event.currentTarget.files?.[0];
        this.error = '';
        this.selectedName = '';
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = '';

        if (! file) return;
        if (! ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            this.error = 'Choose a JPG, PNG, or WebP image.';
            event.currentTarget.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            this.error = 'The profile photo must be 5 MB or smaller.';
            event.currentTarget.value = '';
            return;
        }

        this.selectedName = file.name;
        this.previewUrl = URL.createObjectURL(file);
    },

    submitPhoto(event) {
        if (this.error || ! this.selectedName) {
            event.preventDefault();
            if (! this.error) this.error = 'Choose a profile photo before saving.';
            return;
        }
        this.busy = true;
    },
}));

Alpine.data('peopleWorkspace', () => ({
    railOpen: false,
    createOpen: false,
    submitting: false,
    railTrigger: null,
    modalTrigger: null,
    formSubmitHandler: null,
    pageShowHandler: null,

    init() {
        this.createOpen = this.$root.dataset.createOpen === '1';
        this.formSubmitHandler = (event) => this.handleMutationSubmit(event);
        this.pageShowHandler = () => this.resetMutationForms();
        this.$root.addEventListener('submit', this.formSubmitHandler);
        window.addEventListener('pageshow', this.pageShowHandler);
        this.syncPeopleOverlayLock();
        if (this.createOpen) {
            this.$nextTick(() => this.focusCreateDialog());
        } else {
            this.$nextTick(() => this.focusValidationSummary());
        }
    },

    destroy() {
        if (this.formSubmitHandler) this.$root.removeEventListener('submit', this.formSubmitHandler);
        if (this.pageShowHandler) window.removeEventListener('pageshow', this.pageShowHandler);
        document.documentElement.classList.remove('people-overlay-open');
    },

    get peopleRailClasses() {
        return { 'is-open': this.railOpen };
    },

    get createModalClasses() {
        return { 'is-open': this.createOpen };
    },

    get railExpanded() {
        return String(this.railOpen);
    },

    get createAriaHidden() {
        return String(! this.createOpen);
    },

    get submitLabel() {
        return this.submitting ? 'Creating employee…' : 'Create employee';
    },

    togglePeopleRail(event) {
        this.railTrigger = event?.currentTarget || this.$refs.railTrigger || this.railTrigger;
        this.railOpen = ! this.railOpen;
        this.syncPeopleOverlayLock();
        if (this.railOpen) this.$nextTick(() => this.$refs.peopleRail?.focus());
    },

    closePeopleRail(restoreFocus = false) {
        const wasOpen = this.railOpen;
        this.railOpen = false;
        this.syncPeopleOverlayLock();
        if (restoreFocus && wasOpen) this.$nextTick(() => this.railTrigger?.focus());
    },

    handlePeopleResize() {
        if (window.innerWidth > PEOPLE_WORKSPACE_BREAKPOINT) this.closePeopleRail();
    },

    openCreateEmployee(event) {
        this.modalTrigger = event?.currentTarget || this.modalTrigger;
        this.createOpen = true;
        this.syncPeopleOverlayLock();
        this.$nextTick(() => this.focusCreateDialog());
    },

    closeCreateEmployee() {
        if (this.submitting) return;
        this.createOpen = false;
        this.syncPeopleOverlayLock();
        window.history.replaceState({}, '', `${window.location.pathname}${window.location.search}`);
        this.$nextTick(() => this.modalTrigger?.focus());
    },

    submitEmployeeForm() {
        this.submitting = true;
    },

    handleMutationSubmit(event) {
        const form = event.target;
        if (! (form instanceof HTMLFormElement)) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
        if (form.dataset.peopleSubmitBusy === 'false' || event.defaultPrevented) return;

        if (form.dataset.peopleSubmitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.peopleSubmitting = 'true';
        form.classList.add('is-submitting');
        form.setAttribute('aria-busy', 'true');

        window.setTimeout(() => {
            if (form.dataset.peopleSubmitting !== 'true') return;
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                control.disabled = true;
            });
        }, 0);
    },

    resetMutationForms() {
        this.submitting = false;
        this.$root.querySelectorAll('form[data-people-submitting="true"]').forEach((form) => {
            delete form.dataset.peopleSubmitting;
            form.classList.remove('is-submitting');
            form.removeAttribute('aria-busy');
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                control.disabled = false;
            });
        });
    },

    handlePeopleEscape() {
        if (this.createOpen) {
            this.closeCreateEmployee();
            return;
        }
        if (this.railOpen) this.closePeopleRail(true);
    },

    trapCreateFocus(event) {
        if (! this.createOpen || event.key !== 'Tab') return;
        const dialog = this.$refs.createDialog;
        const focusable = Array.from(dialog?.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])') || []);
        if (! focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },

    focusCreateDialog() {
        const dialog = this.$refs.createDialog;
        const target = dialog?.querySelector('[aria-invalid="true"], input:not([type="hidden"]), select, textarea, button');
        target?.focus();
    },

    focusValidationSummary() {
        const summary = this.$root.querySelector('[role="alert"][tabindex="-1"]');
        summary?.focus();
    },

    syncPeopleOverlayLock() {
        document.documentElement.classList.toggle('people-overlay-open', this.createOpen || this.railOpen);
    },
}));

Alpine.data('mailboxComposer', () => ({
    status: 'Draft ready',
    timer: null,
    dirty: false,
    saving: false,

    init() {
        const form = this.$root;
        form.addEventListener('input', () => this.queueSave());
        form.addEventListener('change', () => this.queueSave());
        form.addEventListener('submit', () => {
            clearTimeout(this.timer);
            this.dirty = false;
            this.status = 'Processing…';
            queueMicrotask(() => form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true; }));
        });
        window.addEventListener('online', () => { if (this.dirty) this.save(); });
        window.addEventListener('beforeunload', (event) => {
            if (! this.dirty || this.saving) return;
            event.preventDefault();
            event.returnValue = '';
        });
    },

    queueSave() {
        this.dirty = true;
        this.status = navigator.onLine ? 'Unsaved changes' : 'Offline · changes waiting';
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(), 1200);
    },

    async save() {
        if (! this.dirty || this.saving || ! navigator.onLine) return;
        this.saving = true;
        this.status = 'Saving draft…';
        const data = new FormData(this.$root);
        data.set('state', 'draft');
        try {
            const response = await fetch(this.$root.dataset.autosaveUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data,
            });
            if (response.status === 419) throw new Error('Your session expired. Copy your message, sign in again, and reopen the draft.');
            const payload = await response.json().catch(() => ({}));
            if (! response.ok) {
                const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                throw new Error(validation || payload.message || 'Draft could not be saved.');
            }
            this.$refs.lockVersion.value = payload.data.lock_version;
            this.dirty = false;
            this.status = 'Draft saved';
        } catch (error) {
            this.status = error.message || 'Draft save failed · changes remain here';
        } finally {
            this.saving = false;
        }
    },
}));

Alpine.data('gmailMailboxComposer', () => ({
    to: [], cc: [], bcc: [], ccVisible: false, bccVisible: false,
    status: 'Draft ready', busy: false, saving: false, dirty: false, expanded: false, dragging: false,
    selectedAttachments: [], uploadProgress: 0, timer: null,
    init() {
        this.to = this.readAddresses('to');
        this.cc = this.readAddresses('cc');
        this.bcc = this.readAddresses('bcc');
        this.ccVisible = this.cc.length > 0;
        this.bccVisible = this.bcc.length > 0;
        this.$refs.editor.innerHTML = this.$root.dataset.bodyHtml || this.escapeText(this.$root.dataset.body || '');
        this.syncBody();
        this.$refs.form.addEventListener('input', () => this.queueSave());
        this.$refs.form.addEventListener('change', () => this.queueSave());
        window.addEventListener('online', () => { if (this.dirty) this.saveDraft(); });
        window.addEventListener('beforeunload', (event) => { if (!this.dirty || this.saving) return; event.preventDefault(); event.returnValue = ''; });
    },
    readAddresses(type) { try { const value = JSON.parse(this.$root.dataset[type] || '[]'); return Array.isArray(value) ? value : []; } catch (_) { return []; } },
    escapeText(value) { const div = document.createElement('div'); div.textContent = value; return div.innerHTML.replace(/\n/g, '<br>'); },
    allAddresses() { return [...this.to, ...this.cc, ...this.bcc].map((value) => value.toLowerCase()); },
    list(type) { return type === 'cc' ? this.cc : (type === 'bcc' ? this.bcc : this.to); },
    commitAddress(event) {
        const input = event.currentTarget;
        const type = input.dataset.recipientType;
        const values = String(input.value || '').split(/[,;\s]+/).map((value) => value.trim().toLowerCase()).filter(Boolean);
        let invalid = '';
        values.forEach((value) => {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) { invalid = `${value} is not a valid email address.`; return; }
            if (this.allAddresses().includes(value)) return;
            this.list(type).push(value);
        });
        input.value = invalid ? values.find((value) => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) || '' : '';
        this.$refs.recipientError.textContent = invalid;
        if (!invalid) this.queueSave();
    },
    recipientKeydown(event) {
        if (['Enter', ',', ';', 'Tab'].includes(event.key) && event.currentTarget.value.trim() !== '') {
            event.preventDefault(); this.commitAddress(event);
        }
    },
    removeAddress(event) {
        const type = event.currentTarget.dataset.recipientType;
        const value = event.currentTarget.dataset.address;
        const list = this.list(type); const index = list.indexOf(value);
        if (index >= 0) list.splice(index, 1);
        this.queueSave();
    },
    showCc() { this.ccVisible = true; this.$nextTick(() => this.$refs.ccInput?.focus()); },
    showBcc() { this.bccVisible = true; this.$nextTick(() => this.$refs.bccInput?.focus()); },
    accountChanged(event) { if (this.dirty && !window.confirm('Switch accounts? Save or discard this draft before changing the sender.')) { event.currentTarget.value = this.$root.dataset.accountId; return; } window.location.assign(event.currentTarget.selectedOptions[0].dataset.composeUrl); },
    format(event) { this.$refs.editor.focus(); document.execCommand(event.currentTarget.dataset.command, false, event.currentTarget.dataset.value || null); this.syncBody(); this.queueSave(); },
    createLink() { const url = window.prompt('Enter a secure link URL'); if (!url || !/^https?:\/\//i.test(url)) return; this.$refs.editor.focus(); document.execCommand('createLink', false, url); this.syncBody(); this.queueSave(); },
    syncBody() { const clone=this.$refs.editor.cloneNode(true);clone.querySelectorAll('img[data-inline-name]').forEach((image)=>image.setAttribute('src',`cid:${image.dataset.inlineName}`));this.$refs.bodyHtml.value = clone.innerHTML; this.$refs.bodyText.value = this.$refs.editor.innerText; },
    selectInlineImages(event) { Array.from(event.currentTarget.files||[]).slice(0,10).forEach((file)=>{const image=document.createElement('img');image.src=URL.createObjectURL(file);image.dataset.inlineName=file.name;image.alt=file.name;image.className='b360-inline-email-image';this.$refs.editor.appendChild(image);});this.queueSave(); },
    selectAttachments(event) { this.setFiles(Array.from(event.currentTarget.files || [])); },
    dropAttachments(event) { this.dragging = false; const transfer = new DataTransfer(); [...this.selectedAttachments.map((item) => item.file), ...Array.from(event.dataTransfer?.files || [])].slice(0, 10).forEach((file) => transfer.items.add(file)); this.$refs.attachments.files = transfer.files; this.setFiles(Array.from(transfer.files)); },
    setFiles(files) { this.selectedAttachments.forEach((item) => item.preview && URL.revokeObjectURL(item.preview)); this.selectedAttachments = files.map((file, index) => ({file,name:file.name,size:this.fileSize(file.size),preview:file.type.startsWith('image/')?URL.createObjectURL(file):null,key:`${file.name}-${file.size}-${file.lastModified}-${index}`})); this.queueSave(); },
    removeAttachment(event) { const files = this.selectedAttachments.filter((item) => item.key !== event.currentTarget.dataset.fileKey).map((item) => item.file); const transfer = new DataTransfer(); files.forEach((file) => transfer.items.add(file)); this.$refs.attachments.files = transfer.files; this.setFiles(files); },
    fileSize(bytes) { return bytes < 1048576 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1048576).toFixed(1)} MB`; },
    queueSave() { this.syncBody(); this.dirty = true; this.status = navigator.onLine ? 'Unsaved changes' : 'Offline · changes waiting'; clearTimeout(this.timer); this.timer = setTimeout(() => this.saveDraft(), 1200); },
    saveDraft() {
        if (!this.dirty || this.saving || !navigator.onLine) return;
        this.saving = true; this.status = 'Saving draft…'; this.syncBody();
        const data = new FormData(this.$refs.form); data.set('state', 'draft');
        const xhr = new XMLHttpRequest(); xhr.open('POST', this.$root.dataset.autosaveUrl); xhr.setRequestHeader('Accept','application/json'); xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
        xhr.upload.onprogress = (event) => { if (event.lengthComputable) this.uploadProgress = Math.round(event.loaded / event.total * 100); };
        xhr.onload = () => { let payload={}; try{payload=JSON.parse(xhr.responseText)}catch(_){} if(xhr.status===419)this.status='Session expired · your draft remains on this screen'; else if(xhr.status>=200&&xhr.status<300){this.$refs.lockVersion.value=payload.data.lock_version;this.$root.dataset.discardUrl=payload.data.discard_url||this.$root.dataset.discardUrl;this.dirty=false;this.status='Draft saved';}else this.status=Object.values(payload.errors||{}).flat().at(0)||payload.message||'Draft could not be saved';this.saving=false;this.uploadProgress=0; };
        xhr.onerror = () => { this.status='Connection lost · changes remain here';this.saving=false;this.uploadProgress=0; };
        xhr.send(data);
    },
    prepareSubmit(event) { this.commitPendingRecipients(); this.syncBody(); if(this.to.length===0){event.preventDefault();this.$refs.recipientError.textContent='Add at least one recipient.';return;} clearTimeout(this.timer);this.busy=true;this.dirty=false;this.status='Sending…';queueMicrotask(()=>this.$refs.form.querySelectorAll('button[type="submit"]').forEach((button)=>button.disabled=true)); },
    commitPendingRecipients() { ['toInput','ccInput','bccInput'].forEach((ref) => { const input=this.$refs[ref]; if(input?.value.trim()) this.commitAddress({currentTarget:input}); }); },
    minimize() { if(this.dirty)this.saveDraft();this.$root.removeAttribute('open'); },
    toggleExpanded() { this.expanded=!this.expanded;this.$root.classList.toggle('is-expanded',this.expanded); },
    closeComposer() { if(this.dirty&&!window.confirm('Close this draft? Your latest changes will be saved first.'))return;if(this.dirty)this.saveDraft();this.$root.removeAttribute('open'); },
    async discardDraft() {
        if (!window.confirm('Discard this draft and its attachments?')) return;
        clearTimeout(this.timer);
        const url = this.$root.dataset.discardUrl;
        if (url) {
            const response = await fetch(url, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
            if (!response.ok && !response.redirected) { this.status = 'Draft could not be discarded.'; return; }
        }
        this.dirty = false;
        window.location.assign(window.location.pathname);
    },
}));

Alpine.data('unifiedMailboxComposer', () => ({
    senderKey: 'internal', peopleQuery: '', selectedPeople: [], selectedAttachments: [], visiblePeopleCount: 0,
    status: 'Draft ready', busy: false, dirty: false, saving: false, dragging: false, expanded: false, uploadProgress: 0,
    timer: null, draftId: null, draftSource: '',
    init() {
        this.senderKey = this.$root.dataset.initialSender || 'internal';
        this.draftId = this.$root.dataset.draftId || null; this.draftSource = this.$root.dataset.draftSource || '';
        this.$root.querySelectorAll('[data-mail-person]').forEach((row) => { if (row.dataset.selectedType) { row.querySelector('input[type="checkbox"]').checked = true; this.addPerson(row, row.dataset.selectedType); } });
        this.applyPeopleFilter();
        this.$refs.form.addEventListener('input', () => this.queueAutosave()); this.$refs.form.addEventListener('change', () => this.queueAutosave());
        window.addEventListener('online', () => { if (this.dirty) this.autosave(); });
        window.addEventListener('beforeunload', (event) => { if (!this.dirty || this.saving) return; event.preventDefault(); event.returnValue = ''; });
        this.$watch('peopleQuery', () => this.applyPeopleFilter());
    },
    get internalMode() { return this.senderKey === 'internal'; },
    get deliveryDescription() { return this.internalMode ? 'Send securely to Builder360 employees.' : 'Send through your connected email account.'; },
    get selectedPeopleLabel() { return `${this.selectedPeople.length} recipient${this.selectedPeople.length === 1 ? '' : 's'} selected`; },
    senderChanged() { this.queueAutosave(); },
    addPerson(row, type = 'to') { const id = Number(row.dataset.personId); if (this.selectedPeople.some((person) => person.id === id)) return; const name = row.dataset.personName; this.selectedPeople.push({ id, name, email: row.dataset.personEmail, role: row.dataset.personRole, type, initials: name.split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase() }); },
    togglePerson(event) { const row = event.currentTarget.closest('[data-mail-person]'); if (event.currentTarget.checked) this.addPerson(row, row.dataset.selectedType || 'to'); else this.selectedPeople = this.selectedPeople.filter((person) => person.id !== Number(row.dataset.personId)); this.queueAutosave(); },
    removePerson(event) { const id = Number(event.currentTarget.dataset.personId); this.selectedPeople = this.selectedPeople.filter((person) => person.id !== id); const row = this.$root.querySelector(`[data-mail-person][data-person-id="${id}"]`); row?.querySelector('input[type="checkbox"]')?.removeAttribute('checked'); if (row?.querySelector('input[type="checkbox"]')) row.querySelector('input[type="checkbox"]').checked = false; this.queueAutosave(); },
    changeRecipientType(event) { const id = Number(event.currentTarget.dataset.personId); const person = this.selectedPeople.find((item) => item.id === id); if (person) person.type = event.currentTarget.value; this.queueAutosave(); },
    applyPeopleFilter() { const query = this.peopleQuery.trim().toLowerCase(); let visible = 0; this.$root.querySelectorAll('[data-mail-person]').forEach((row) => { const matches = query === '' || (row.dataset.personSearch || '').includes(query); row.hidden = ! matches; row.style.display = matches ? '' : 'none'; if (matches) visible++; }); this.visiblePeopleCount = visible; },
    selectAttachments(event) { this.setFiles(Array.from(event.currentTarget.files || [])); },
    dropAttachments(event) { this.dragging = false; const incoming = Array.from(event.dataTransfer?.files || []); const transfer = new DataTransfer(); [...this.selectedAttachments.map((item) => item.file), ...incoming].slice(0, 10).forEach((file) => transfer.items.add(file)); this.$refs.attachments.files = transfer.files; this.setFiles(Array.from(transfer.files)); },
    setFiles(files) { this.selectedAttachments.forEach((item) => { if (item.preview) URL.revokeObjectURL(item.preview); }); this.selectedAttachments = files.map((file, index) => ({ file, name: file.name, sizeLabel: this.fileSize(file.size), preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null, key: `${file.name}-${file.size}-${file.lastModified}-${index}` })); this.queueAutosave(); },
    removeAttachment(event) { const key = event.currentTarget.dataset.fileKey; const remaining = this.selectedAttachments.filter((item) => item.key !== key); const transfer = new DataTransfer(); remaining.forEach((item) => transfer.items.add(item.file)); this.$refs.attachments.files = transfer.files; this.setFiles(remaining.map((item) => item.file)); },
    fileSize(bytes) { if (bytes < 1024) return `${bytes} B`; if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`; return `${(bytes / 1048576).toFixed(1)} MB`; },
    formatBody(event) { const textarea = this.$refs.body; const action = event.currentTarget.dataset.format; const start = textarea.selectionStart; const end = textarea.selectionEnd; const selected = textarea.value.slice(start, end); const formats = { bold: ['**', '**'], italic: ['*', '*'], list: ['- ', ''], link: ['[', '](https://)'] }; const pair = formats[action]; if (!pair) return; textarea.setRangeText(pair[0] + selected + pair[1], start, end, 'select'); textarea.dispatchEvent(new Event('input', { bubbles: true })); textarea.focus(); },
    chooseIntent(event) { this.$refs.intent.value = event.currentTarget.dataset.intent; },
    prepareSubmit(event) { clearTimeout(this.timer); const intent = this.$refs.intent.value; if (intent === 'schedule' && !this.$refs.form.querySelector('[name="scheduled_for"]').value) { event.preventDefault(); this.status = 'Choose a future date and time before scheduling.'; return; } this.busy = intent === 'send'; this.dirty = false; this.status = intent === 'send' ? 'Sending…' : 'Saving…'; },
    queueAutosave() { this.dirty = true; this.status = navigator.onLine ? 'Unsaved changes' : 'Offline · changes waiting'; clearTimeout(this.timer); this.timer = setTimeout(() => this.autosave(), 1200); },
    autosave() { if (!this.dirty || this.saving || !navigator.onLine) return; this.saving = true; this.status = 'Saving draft…'; const data = new FormData(this.$refs.form); data.set('intent', 'draft'); const xhr = new XMLHttpRequest(); xhr.open('POST', this.$root.dataset.autosaveUrl); xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); xhr.upload.onprogress = (event) => { if (event.lengthComputable) this.uploadProgress = Math.round((event.loaded / event.total) * 100); }; xhr.onload = () => { let payload = {}; try { payload = JSON.parse(xhr.responseText); } catch (_) {} if (xhr.status === 419) this.status = 'Session expired · your text remains here'; else if (xhr.status >= 200 && xhr.status < 300) { this.draftId = payload.data.id; this.draftSource = payload.data.source; this.$refs.lockVersion.value = payload.data.lock_version || ''; this.dirty = false; this.status = 'Draft saved'; } else this.status = Object.values(payload.errors || {}).flat().at(0) || payload.message || 'Draft save failed · changes remain here'; this.saving = false; this.uploadProgress = 0; }; xhr.onerror = () => { this.status = 'Connection lost · changes remain here'; this.saving = false; this.uploadProgress = 0; }; xhr.send(data); },
    minimize() { if (this.dirty) this.autosave(); this.$root.removeAttribute('open'); },
    toggleExpanded() { this.expanded = !this.expanded; this.$root.classList.toggle('is-expanded', this.expanded); },
    requestClose() { if (this.dirty && !window.confirm('Close this message? Unsaved changes will remain until the next autosave.')) return; this.minimize(); },
    async discard() { if (!window.confirm('Discard this draft and its attachments?')) return; if (!this.draftId) { this.$refs.form.reset(); this.selectedPeople = []; this.setFiles([]); this.$root.removeAttribute('open'); return; } let url = this.draftSource === 'external' ? this.$root.dataset.externalDiscardTemplate.replace('__ACCOUNT__', this.senderKey.split(':')[1]).replace('__DRAFT__', this.draftId) : this.$root.dataset.internalDiscardTemplate.replace('__DRAFT__', this.draftId); const response = await fetch(url, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } }); if (response.ok || response.redirected) window.location.reload(); else this.status = 'Draft could not be discarded.'; },
}));

Alpine.data('periodSelector', () => ({
    periodKey: 'current_month',

    init() {
        this.periodKey = this.$root.dataset.periodKey || 'current_month';
    },

    get customPeriod() {
        return this.periodKey === 'custom';
    },
}));

Alpine.data('commissionRuleForm', () => ({
    ruleType: 'percentage',

    init() {
        this.ruleType = this.$root.dataset.initialRuleType || 'percentage';
    },

    selectRuleType(event) {
        this.ruleType = event.currentTarget.value;
    },

    get isFixed() {
        return this.ruleType === 'fixed';
    },

    get isPercentage() {
        return this.ruleType === 'percentage';
    },

    get isTarget() {
        return this.ruleType === 'target';
    },

    get isSlab() {
        return this.ruleType === 'slab';
    },
}));

Alpine.data('peopleFilter', () => ({
    peopleQuery: '',
    conversationType: 'direct_message',
    selectedPeopleCount: 0,
    visiblePeopleCount: 0,

    init() {
        this.conversationType = this.$root.dataset.initialType || 'direct_message';
        this.visiblePeopleCount = Number(this.$root.dataset.peopleCount || 0);
        this.updateSelectedPeopleCount();
        this.$watch('peopleQuery', () => this.applyPeopleFilter());
    },

    get singleRecipient() {
        return this.conversationType === 'direct_message';
    },

    get requiresTitle() {
        return ! this.singleRecipient;
    },

    get requiresProject() {
        return this.conversationType === 'project_channel';
    },

    get memberFieldLabel() {
        return this.singleRecipient ? 'Recipient' : 'Members';
    },

    get selectionHelp() {
        if (this.singleRecipient) {
            return this.selectedPeopleCount === 1 ? '1 recipient selected' : 'Select one recipient';
        }

        return `${this.selectedPeopleCount} member${this.selectedPeopleCount === 1 ? '' : 's'} selected`;
    },

    get canCreateConversation() {
        return this.singleRecipient ? this.selectedPeopleCount === 1 : this.selectedPeopleCount > 0;
    },

    get noPeopleMatches() {
        return this.visiblePeopleCount === 0;
    },

    applyPeopleFilter() {
        const query = this.peopleQuery.trim().toLowerCase();
        let visible = 0;

        this.$root.querySelectorAll('[data-people-option]').forEach((option) => {
            const searchable = option.dataset.search || option.textContent || '';
            const matches = query === '' || searchable.toLowerCase().includes(query);
            option.hidden = ! matches;
            option.style.display = matches ? '' : 'none';
            if (matches) {
                visible += 1;
            }
        });

        this.visiblePeopleCount = visible;
    },

    updateSelectedPeopleCount() {
        this.selectedPeopleCount = this.$root.querySelectorAll('input[name="member_user_ids[]"]:checked').length;
    },

    togglePerson(event) {
        if (this.singleRecipient && event.currentTarget.checked) {
            this.$root.querySelectorAll('input[name="member_user_ids[]"]').forEach((input) => {
                if (input !== event.currentTarget) {
                    input.checked = false;
                }
            });
        }

        this.updateSelectedPeopleCount();
    },

    changeConversationType() {
        if (this.singleRecipient) {
            const selected = Array.from(this.$root.querySelectorAll('input[name="member_user_ids[]"]:checked'));
            selected.slice(1).forEach((input) => { input.checked = false; });
        }

        this.updateSelectedPeopleCount();
    },

    closePeoplePanel() {
        this.$root.removeAttribute('open');
    },
}));

Alpine.data('pollComposer', () => ({
    pollOptions: ['', ''],

    init() {
        try {
            const seeded = JSON.parse(this.$refs.pollSeed?.textContent || '[]');
            if (Array.isArray(seeded) && seeded.length > 0) {
                this.pollOptions = seeded.slice(0, 10).map((option) => String(option || ''));
            }
        } catch (_error) {
            this.pollOptions = ['', ''];
        }

        while (this.pollOptions.length < 2) {
            this.pollOptions.push('');
        }
    },

    get canAddPollOption() {
        return this.pollOptions.length < 10;
    },

    get pollOptionCountLabel() {
        return `${this.pollOptions.length} of 10 options`;
    },

    addPollOption() {
        if (! this.canAddPollOption) {
            return;
        }

        this.pollOptions.push('');
        this.$nextTick(() => {
            const inputs = this.$root.querySelectorAll('input[name="options[]"]');
            inputs[inputs.length - 1]?.focus();
        });
    },

    removePollOption(event) {
        if (this.pollOptions.length <= 2) {
            return;
        }

        const index = Number(event.currentTarget.dataset.optionIndex);
        if (Number.isInteger(index) && index >= 0 && index < this.pollOptions.length) {
            this.pollOptions.splice(index, 1);
        }
    },

    updatePollOption(event) {
        const index = Number(event.currentTarget.dataset.optionIndex);
        if (Number.isInteger(index) && index >= 0 && index < this.pollOptions.length) {
            this.pollOptions[index] = event.currentTarget.value;
        }
    },

    closePollPanel() {
        this.$root.removeAttribute('open');
    },
}));

Alpine.data('togglePanel', () => ({
    open: false,

    get openState() {
        return this.open ? 'true' : 'false';
    },

    openPanel() {
        this.open = true;
        this.$nextTick(() => this.$refs.panel?.focus());
    },

    closePanel() {
        this.open = false;
        this.$nextTick(() => this.$refs.trigger?.focus());
    },

    togglePanel() {
        this.open ? this.closePanel() : this.openPanel();
    },
}));

Alpine.data('rotationPatternEditor', () => ({
    cycleDays: 7,

    init() {
        this.cycleDays = this.clampCycleDays(this.$root.dataset.initialCycleDays);
        this.syncPatternRows();
    },

    updateCycleDays(event) {
        this.cycleDays = this.clampCycleDays(event.currentTarget.value);
        event.currentTarget.value = String(this.cycleDays);
        this.syncPatternRows();
    },

    syncPatternRows() {
        const container = this.$refs.days;
        const template = this.$refs.dayTemplate;
        if (! container || ! template) {
            return;
        }

        while (container.querySelectorAll('[data-rotation-day]').length > this.cycleDays) {
            container.querySelector('[data-rotation-day]:last-child')?.remove();
        }

        while (container.querySelectorAll('[data-rotation-day]').length < this.cycleDays) {
            const index = container.querySelectorAll('[data-rotation-day]').length;
            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('[data-rotation-day]');
            if (! row) {
                return;
            }

            row.querySelector('[data-day-label]').textContent = `Day ${index + 1}`;
            row.querySelector('[data-day-type]').name = `pattern[${index}][type]`;
            row.querySelector('[data-day-shift]').name = `pattern[${index}][attendance_shift_id]`;
            container.appendChild(fragment);
        }

        if (this.$refs.cycleLabel) {
            this.$refs.cycleLabel.textContent = `${this.cycleDays}-day rotation pattern`;
        }

        container.querySelectorAll('[data-rotation-day]').forEach((row) => this.normalizeRow(row));
    },

    normalizeDay(event) {
        const row = event.target.closest('[data-rotation-day]');
        if (row) {
            this.normalizeRow(row);
        }
    },

    normalizeRow(row) {
        const type = row.querySelector('[data-day-type]');
        const shift = row.querySelector('[data-day-shift]');
        if (! type || ! shift) {
            return;
        }

        const isShift = type.value === 'shift';
        shift.disabled = ! isShift;
        if (! isShift) {
            shift.value = '';
        }
    },

    clampCycleDays(value) {
        const parsed = Number.parseInt(String(value), 10);

        return Number.isInteger(parsed) ? Math.min(31, Math.max(1, parsed)) : 7;
    },
}));

Alpine.data('dismissibleAlert', () => ({
    visible: true,

    dismiss() {
        this.visible = false;
    },
}));

Alpine.data('tabSet', () => ({
    activeTab: '',

    init() {
        this.activeTab = this.$root.dataset.initialTab || '';
    },

    selectTab(event) {
        this.activeTab = event.currentTarget.dataset.tabKey;
    },
}));

Alpine.data('taskDrawer', () => ({
    activeTab: 'details',
    lastContentTab: 'details',
    compactInfo: false,
    actionMenuOpen: false,
    transferOpen: false,
    assigneeOpen: false,
    drawerObserver: null,
    resizeFallback: null,

    init() {
        this.activeTab = this.$root.dataset.initialTab || 'details';
        this.lastContentTab = this.activeTab === 'info' ? 'details' : this.activeTab;
        this.transferOpen = this.$root.dataset.openTransfer === '1';
        this.assigneeOpen = this.$root.dataset.openAssignee === '1';

        this.$nextTick(() => {
            const drawer = this.$refs.drawer;
            if (!drawer) return;

            const update = (width) => this.updateDrawerMode(width);
            update(drawer.getBoundingClientRect().width);

            if ('ResizeObserver' in window) {
                this.drawerObserver = new ResizeObserver((entries) => {
                    const width = entries[0]?.contentRect?.width ?? drawer.getBoundingClientRect().width;
                    update(width);
                });
                this.drawerObserver.observe(drawer);
            } else {
                this.resizeFallback = () => update(drawer.getBoundingClientRect().width);
                window.addEventListener('resize', this.resizeFallback, { passive: true });
            }

            this.syncTabStops();
        });
    },

    destroy() {
        this.drawerObserver?.disconnect();
        if (this.resizeFallback) window.removeEventListener('resize', this.resizeFallback);
    },

    selectTab(event) {
        this.activateTab(event.currentTarget.dataset.tabKey);
        this.closeMenus();
    },

    activateTab(tab) {
        if (!tab || (tab === 'info' && !this.compactInfo)) return;
        this.activeTab = tab;
        if (tab !== 'info') this.lastContentTab = tab;
        this.$nextTick(() => this.syncTabStops());
    },

    navigateTabs(event) {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

        const tabs = this.visibleTabs();
        if (!tabs.length) return;

        event.preventDefault();
        const current = Math.max(0, tabs.indexOf(document.activeElement));
        let next = current;
        if (event.key === 'ArrowRight') next = (current + 1) % tabs.length;
        if (event.key === 'ArrowLeft') next = (current - 1 + tabs.length) % tabs.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = tabs.length - 1;

        const button = tabs[next];
        this.activateTab(button.dataset.tabKey);
        this.$nextTick(() => button.focus());
    },

    visibleTabs() {
        return Array.from(this.$root.querySelectorAll('[data-tab-key]'))
            .filter((button) => button.offsetParent !== null);
    },

    syncTabStops() {
        this.visibleTabs().forEach((button) => {
            button.tabIndex = button.dataset.tabKey === this.activeTab ? 0 : -1;
        });
    },

    updateDrawerMode(width) {
        const nextCompact = width < 820;
        if (nextCompact === this.compactInfo) return;

        const infoHadFocus = document.activeElement?.dataset?.tabKey === 'info';
        this.compactInfo = nextCompact;

        if (!nextCompact && this.activeTab === 'info') {
            this.activeTab = this.lastContentTab || 'details';
        }

        this.$nextTick(() => {
            this.syncTabStops();
            if (infoHadFocus && !nextCompact) {
                this.$root.querySelector(`[data-tab-key="${this.activeTab}"]`)?.focus();
            }
        });
    },

    toggleActionMenu() { this.actionMenuOpen = ! this.actionMenuOpen; },
    openTransfer() { this.transferOpen = true; this.actionMenuOpen = false; },
    closeTransfer() { this.transferOpen = false; },
    toggleAssignee() { this.assigneeOpen = ! this.assigneeOpen; },
    closeMenus() { this.actionMenuOpen = false; this.assigneeOpen = false; },
    async copyLink() {
        try { await navigator.clipboard.writeText(window.location.href); } catch (_error) { /* URL remains visible in the address bar. */ }
        this.actionMenuOpen = false;
    },
    escape() {
        if (this.transferOpen) { this.transferOpen = false; return; }
        this.closeMenus();
    },
}));

Alpine.data('taskTransferForm', () => ({
    ready: false,
    init() { this.$nextTick(() => this.sync()); },
    sync() {
        const recipient = this.$root.querySelector('[name="assigned_to_user_id"]:checked');
        const reason = this.$root.querySelector('[name="reason"]');
        this.ready = Boolean(recipient && reason?.value.trim());
    },
}));

Alpine.data('taskStatusForm', () => ({
    confirmTransition(event) {
        const target = this.$root.dataset.targetStatus || '';
        if (!['waiting_approval', 'completed', 'rejected', 'cancelled'].includes(target)) return;

        const note = window.prompt(`Add a note before moving this task to ${target.replaceAll('_', ' ')}:`, '')?.trim() || '';
        if (!note) {
            event.preventDefault();
            return;
        }

        const noteInput = this.$root.querySelector('[name="note"]');
        if (noteInput) noteInput.value = note;
    },
}));

Alpine.data('taskBoard', () => ({
    draggedTaskId: null,
    allowedTargets: [],

    init() {
        this.$nextTick(() => {
            const stored = Number(window.sessionStorage.getItem(this.$root.dataset.scrollKey || '') || 0);
            if (this.$refs.viewport && Number.isFinite(stored)) this.$refs.viewport.scrollLeft = stored;
        });
    },

    rememberScroll() {
        if (!this.$refs.viewport || !this.$root.dataset.scrollKey) return;
        window.sessionStorage.setItem(this.$root.dataset.scrollKey, String(Math.round(this.$refs.viewport.scrollLeft)));
    },

    scrollColumns(direction) {
        if (!this.$refs.viewport) return;
        const distance = Math.max(280, Math.floor(this.$refs.viewport.clientWidth * 0.82));
        this.$refs.viewport.scrollBy({ left: distance * direction, behavior: 'smooth' });
    },

    navigateBoard(event) {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        if (event.key === 'ArrowLeft') this.scrollColumns(-1);
        if (event.key === 'ArrowRight') this.scrollColumns(1);
        if (event.key === 'Home') this.$refs.viewport?.scrollTo({ left: 0, behavior: 'smooth' });
        if (event.key === 'End' && this.$refs.viewport) {
            this.$refs.viewport.scrollTo({ left: this.$refs.viewport.scrollWidth, behavior: 'smooth' });
        }
    },

    beginDrag(event) {
        const card = event.currentTarget.closest('[data-task-id]');
        if (!card) return;
        this.draggedTaskId = card.dataset.taskId;
        try { this.allowedTargets = JSON.parse(card.dataset.allowedTargets || '[]'); } catch (_error) { this.allowedTargets = []; }
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', this.draggedTaskId);
        card.classList.add('is-dragging');
    },

    dragOver(event) {
        const column = event.currentTarget;
        if (!this.draggedTaskId || !this.allowedTargets.includes(column.dataset.targetStatus)) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        column.classList.add('is-drop-target');
    },

    dragLeave(event) {
        if (!event.currentTarget.contains(event.relatedTarget)) event.currentTarget.classList.remove('is-drop-target');
    },

    dropTask(event) {
        const column = event.currentTarget;
        column.classList.remove('is-drop-target');
        const targetStatus = column.dataset.targetStatus;
        if (!this.draggedTaskId || !this.allowedTargets.includes(targetStatus)) return;

        const form = document.getElementById(`task-board-status-${this.draggedTaskId}`);
        if (!form) return;
        const status = form.querySelector('[name="status"]');
        const note = form.querySelector('[name="note"]');
        if (!status || !note) return;

        const confirmationTargets = ['waiting_approval', 'completed', 'rejected', 'cancelled'];
        let explanation = `Moved to ${targetStatus.replaceAll('_', ' ')} from Board view.`;
        if (confirmationTargets.includes(targetStatus)) {
            explanation = window.prompt(`Add a note before moving this task to ${targetStatus.replaceAll('_', ' ')}:`, '')?.trim() || '';
            if (!explanation) return;
        }

        status.value = targetStatus;
        note.value = explanation;
        this.rememberScroll();
        form.requestSubmit();
    },

    endDrag(event) {
        event.currentTarget.closest('[data-task-id]')?.classList.remove('is-dragging');
        this.$root.querySelectorAll('.is-drop-target').forEach((column) => column.classList.remove('is-drop-target'));
        this.draggedTaskId = null;
        this.allowedTargets = [];
    },
}));

Alpine.data('taskWorkspace', () => ({
    railOpen: true,
    compact: false,
    optionsOpen: false,
    fullScreen: false,
    createOpen: false,
    stale: false,
    echo: null,
    pollTimer: null,
    taskVersion: 0,

    init() {
        this.compact = this.isCompact();
        this.railOpen = this.compact ? false : this.storageGet(TASK_WORKSPACE_KEY) !== '0';
        this.createOpen = this.$root.dataset.openCreate === '1';
        this.taskVersion = Number(this.$root.dataset.taskVersion || 0);
        this.connectRealtime();
        this.startPolling();
    },

    get railClasses() {
        return {
            'workspace-hidden': ! this.railOpen && ! this.compact,
            'workspace-open': this.railOpen && this.compact,
        };
    },
    get railExpanded() { return String(this.railOpen); },
    toggleRail() {
        this.railOpen = ! this.railOpen;
        if (! this.compact) this.storageSet(TASK_WORKSPACE_KEY, this.railOpen ? '1' : '0');
        if (this.railOpen && this.compact) this.$nextTick(() => this.$refs.taskRail?.focus());
    },
    closeRail() { if (this.compact) this.railOpen = false; },
    handleWorkspaceResize() {
        const wasCompact = this.compact;
        this.compact = this.isCompact();
        if (this.compact && ! wasCompact) this.railOpen = false;
        if (! this.compact && wasCompact) this.railOpen = this.storageGet(TASK_WORKSPACE_KEY) !== '0';
    },
    toggleOptions() { this.optionsOpen = ! this.optionsOpen; },
    toggleFullScreen() { this.fullScreen = ! this.fullScreen; },
    openCreate() { this.createOpen = true; },
    closeCreate() { this.createOpen = false; },
    refresh() { window.location.reload(); },
    changed(payload = {}) {
        if (Number(payload.updated_at ? Date.parse(payload.updated_at) / 1000 : 0) > this.taskVersion || !payload.updated_at) this.stale = true;
    },
    connectRealtime() {
        if (this.$root.dataset.realtimeEnabled !== '1' || !this.$root.dataset.reverbKey) return;
        try {
            window.Pusher = Pusher;
            this.echo = new Echo({
                broadcaster: 'reverb', key: this.$root.dataset.reverbKey,
                wsHost: this.$root.dataset.reverbHost || window.location.hostname,
                wsPort: Number(this.$root.dataset.reverbPort || 8080),
                wssPort: Number(this.$root.dataset.reverbPort || 443),
                forceTLS: this.$root.dataset.reverbScheme === 'https', enabledTransports: ['ws', 'wss'],
            });
            const companyId = Number(this.$root.dataset.companyId || 0);
            const userId = Number(this.$root.dataset.userId || 0);
            if (companyId) this.echo.private(`tasks.company.${companyId}`).listen('.task.changed', (payload) => this.changed(payload));
            if (userId) this.echo.private(`tasks.user.${userId}`).listen('.task.changed', (payload) => this.changed(payload));
        } catch (_error) { this.echo = null; }
    },
    startPolling() {
        this.pollTimer = window.setInterval(async () => {
            if (document.hidden || this.stale) return;
            try {
                const response = await fetch(window.location.href, { credentials: 'same-origin', headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) return;
                const documentCopy = new DOMParser().parseFromString(await response.text(), 'text/html');
                const next = Number(documentCopy.querySelector('[data-task-version]')?.dataset.taskVersion || 0);
                if (next > this.taskVersion) this.stale = true;
            } catch (_error) { /* The next polling cycle retries safely. */ }
        }, 30000);
    },
    destroy() {
        if (this.pollTimer) window.clearInterval(this.pollTimer);
        if (this.echo) this.echo.disconnect();
    },
    escapeWorkspace() {
        if (this.createOpen) { this.createOpen = false; return; }
        if (this.compact && this.railOpen) { this.railOpen = false; return; }
        if (this.fullScreen) { this.fullScreen = false; }
    },
    isCompact() { return window.innerWidth <= TASK_WORKSPACE_BREAKPOINT; },
    storageGet(key) {
        try { return window.localStorage.getItem(key); } catch (_error) { return null; }
    },
    storageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (_error) { /* Display preferences are optional. */ }
    },
}));

Alpine.data('calendarWorkspace', () => ({
    optionsOpen: true,
    optionPreferenceStored: false,
    workspaceCompact: false,
    workspaceShort: false,
    hiddenCategories: [],
    fullScreen: false,
    createOpen: false,
    filterOpen: false,
    stale: false,
    echo: null,
    pollTimer: null,
    resizeObserver: null,
    pageHideHandler: null,
    scrollSaveTimer: null,

    init() {
        this.createOpen = this.$root.dataset.openCreate === '1';
        const storedOptionState = this.localStorageGet('builder360.calendar.options-open');
        this.optionPreferenceStored = storedOptionState === 'open' || storedOptionState === 'closed';
        this.measureWorkspace();
        this.optionsOpen = this.optionPreferenceStored
            ? storedOptionState === 'open'
            : ! this.workspaceCompact && ! this.workspaceShort;

        const storedCategories = this.sessionStorageGet('builder360.calendar.hidden-categories');
        if (storedCategories) {
            try {
                const categories = JSON.parse(storedCategories);
                this.hiddenCategories = Array.isArray(categories) ? categories : [];
            } catch (_error) { this.hiddenCategories = []; }
        }

        if (typeof window.ResizeObserver === 'function') {
            this.resizeObserver = new window.ResizeObserver(() => this.measureWorkspace());
            this.resizeObserver.observe(this.$root);
        }

        this.pageHideHandler = () => this.saveScroll();
        window.addEventListener('pagehide', this.pageHideHandler);
        this.$nextTick(() => this.restoreScroll());
        this.connectRealtime();
        this.pollTimer = window.setInterval(() => {
            if (document.visibilityState === 'visible' && !this.createOpen && !this.filterOpen && !this.$root.dataset.selectedEvent) this.stale = true;
        }, 60000);
    },
    measureWorkspace() {
        const bounds = this.$root.getBoundingClientRect();
        this.workspaceCompact = bounds.width < 1180;
        this.workspaceShort = bounds.height < 760;
        if (! this.optionPreferenceStored) {
            this.optionsOpen = ! this.workspaceCompact && ! this.workspaceShort;
        }
    },
    workspaceClass() {
        return [
            this.fullScreen ? 'cal-fullscreen' : '',
            this.optionsOpen ? 'cal-options-open' : 'cal-options-closed',
            this.workspaceCompact ? 'cal-workspace-compact' : '',
            this.workspaceShort ? 'cal-workspace-short' : '',
        ].filter(Boolean).join(' ');
    },
    connectRealtime() {
        if (this.$root.dataset.realtimeEnabled !== '1' || !this.$root.dataset.reverbKey) return;
        try {
            this.echo = new Echo({ broadcaster:'reverb', key:this.$root.dataset.reverbKey, wsHost:this.$root.dataset.reverbHost || window.location.hostname, wsPort:Number(this.$root.dataset.reverbPort || 8080), wssPort:Number(this.$root.dataset.reverbPort || 443), forceTLS:this.$root.dataset.reverbScheme === 'https', enabledTransports:['ws','wss'] });
            const companyId=this.$root.dataset.companyId; const userId=this.$root.dataset.userId;
            const changed=() => { this.stale=true; };
            if (companyId) this.echo.private(`calendar.company.${companyId}`).listen('.calendar.changed', changed);
            if (userId) this.echo.private(`calendar.user.${userId}`).listen('.calendar.changed', changed);
        } catch (_) { this.echo=null; }
    },
    toggleOptions() {
        this.optionsOpen = ! this.optionsOpen;
        this.optionPreferenceStored = true;
        this.filterOpen = false;
        this.localStorageSet('builder360.calendar.options-open', this.optionsOpen ? 'open' : 'closed');
        this.$nextTick(() => this.restoreScroll());
    },
    toggleFullScreen() {
        this.saveScroll();
        this.fullScreen = ! this.fullScreen;
        this.$nextTick(() => {
            this.measureWorkspace();
            this.restoreScroll();
        });
    },
    toggleFilters() { this.filterOpen = ! this.filterOpen; },
    toggleCategory(type) {
        this.hiddenCategories = this.hiddenCategories.includes(type)
            ? this.hiddenCategories.filter((value) => value !== type)
            : [...this.hiddenCategories, type];
        this.sessionStorageSet('builder360.calendar.hidden-categories', JSON.stringify(this.hiddenCategories));
    },
    categoryHidden(type) { return this.hiddenCategories.includes(type); },
    scrollStorageKey() { return `builder360.calendar.scroll.${this.$root.dataset.scrollKey || 'default'}`; },
    rememberScroll() {
        if (this.scrollSaveTimer) window.clearTimeout(this.scrollSaveTimer);
        this.scrollSaveTimer = window.setTimeout(() => this.saveScroll(), 100);
    },
    saveScroll() {
        const body = this.$refs.calendarBody;
        if (! body) return;
        this.sessionStorageSet(this.scrollStorageKey(), JSON.stringify({ top: body.scrollTop, left: body.scrollLeft }));
    },
    restoreScroll() {
        const body = this.$refs.calendarBody;
        if (! body) return;
        const stored = this.sessionStorageGet(this.scrollStorageKey());
        if (stored) {
            try {
                const position = JSON.parse(stored);
                body.scrollTop = Number(position.top || 0);
                body.scrollLeft = Number(position.left || 0);
                return;
            } catch (_error) { /* Fall through to the view default. */ }
        }

        if (['week', 'day'].includes(this.$root.dataset.view)) {
            const nowLine = body.querySelector('.cal-now-line');
            body.scrollTop = nowLine ? Math.max(0, nowLine.offsetTop - 160) : 84;
        }
    },
    localStorageGet(key) {
        try { return window.localStorage.getItem(key); } catch (_error) { return null; }
    },
    localStorageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (_error) { /* Optional display preference. */ }
    },
    sessionStorageGet(key) {
        try { return window.sessionStorage.getItem(key); } catch (_error) { return null; }
    },
    sessionStorageSet(key, value) {
        try { window.sessionStorage.setItem(key, value); } catch (_error) { /* Optional display preference. */ }
    },
    openCreate() { this.createOpen = true; },
    closeCreate() { this.createOpen = false; },
    escapeWorkspace() {
        if (this.createOpen) { this.createOpen = false; return; }
        if (this.filterOpen) { this.filterOpen = false; return; }
        if (this.fullScreen) { this.fullScreen = false; }
    },
    destroy() {
        this.saveScroll();
        if (this.pollTimer) window.clearInterval(this.pollTimer);
        if (this.scrollSaveTimer) window.clearTimeout(this.scrollSaveTimer);
        if (this.resizeObserver) this.resizeObserver.disconnect();
        if (this.pageHideHandler) window.removeEventListener('pagehide', this.pageHideHandler);
        if (this.echo) this.echo.disconnect();
    },
}));

Alpine.data('peopleSearch', () => ({
    query: '',
    filterPeople(event) {
        if (window.filterPeople) {
            window.filterPeople(event);
            return;
        }
        this.query = String(event.currentTarget.value || '').trim().toLowerCase();
        this.$root.querySelectorAll('[data-person-search]').forEach((row) => {
            const haystack = String(row.dataset.personSearch || '').toLowerCase();
            const matches = this.query === '' || haystack.includes(this.query);
            row.hidden = ! matches;
            row.style.display = matches ? '' : 'none';
        });
    },
}));

Alpine.data('taskMentionComposer', () => ({
    open: false,
    query: '',
    triggerStart: null,
    input() {
        const value = this.$refs.body.value;
        const caret = this.$refs.body.selectionStart;
        const match = value.slice(0, caret).match(/(^|\s)@([^\s@]*)$/);
        if (! match) { this.close(); return; }
        this.triggerStart = caret - match[2].length - 1;
        this.query = match[2].toLowerCase();
        this.open = true;
        this.filter();
    },
    show() {
        this.triggerStart = this.$refs.body.selectionStart;
        this.query = '';
        this.open = true;
        this.filter();
        this.$refs.body.focus();
    },
    filter() {
        this.$root.querySelectorAll('[data-task-mention-option]').forEach((row) => {
            const matches = this.query === '' || String(row.dataset.personSearch || '').includes(this.query);
            row.hidden = ! matches;
            row.style.display = matches ? '' : 'none';
        });
    },
    select(event) {
        const button = event.currentTarget;
        const name = button.dataset.personName;
        const id = button.dataset.personId;
        const body = this.$refs.body;
        const start = this.triggerStart ?? body.selectionStart;
        const end = body.selectionStart;
        body.value = `${body.value.slice(0, start)}@${name} ${body.value.slice(end)}`;
        const checkbox = this.$root.querySelector(`[data-mention-id="${id}"]`);
        if (checkbox) checkbox.checked = true;
        this.close();
        this.$nextTick(() => { body.focus(); body.selectionStart = body.selectionEnd = start + name.length + 2; });
    },
    close() {
        this.open = false;
        this.query = '';
        this.triggerStart = null;
    },
}));

Alpine.data('chatRealtime', () => ({
    echo: null,
    pollTimer: null,
    busy: false,
    connectionState: 'connecting',
    statusMessage: '',
    statusTone: 'info',
    onlineCount: 0,
    selectedAttachments: [],
    mentionMatchCount: 0,
    mentionTriggerStart: null,
    replyTarget: null,

    get hasSelectedAttachments() {
        return this.selectedAttachments.length > 0;
    },

    get noMentionMatches() {
        return this.mentionMatchCount === 0;
    },

    get connectionLabel() {
        if (this.connectionState === 'live' && this.onlineCount > 0) {
            return `${this.onlineCount} online · Live updates`;
        }

        return {
            live: 'Live updates',
            periodic: 'Refreshing periodically',
            connecting: 'Connecting…',
            offline: 'Connection unavailable',
        }[this.connectionState] || 'Refreshing periodically';
    },

    get connectionClass() {
        return `is-${this.connectionState}`;
    },

    get statusClass() {
        return `is-${this.statusTone}`;
    },

    init() {
        const conversationId = Number(this.$root.dataset.conversationId || 0);
        this.mentionMatchCount = Number(this.$root.dataset.mentionCount || 0);

        document.addEventListener('visibilitychange', () => {
            if (! document.hidden) {
                this.refreshTimeline(false);
                this.refreshSidebar();
            }
        });

        if (! conversationId) {
            this.connectionState = 'periodic';
            this.startPolling(1000);
            return;
        }

        this.$nextTick(() => this.scrollTimelineToBottom(false));
        this.autoMarkRead();

        if (this.$root.dataset.realtimeEnabled === '1' && this.$root.dataset.reverbKey) {
            try {
                window.Pusher = Pusher;
                this.echo = new Echo({
                    broadcaster: 'reverb',
                    key: this.$root.dataset.reverbKey,
                    wsHost: this.$root.dataset.reverbHost || window.location.hostname,
                    wsPort: Number(this.$root.dataset.reverbPort || 8080),
                    wssPort: Number(this.$root.dataset.reverbPort || 443),
                    forceTLS: this.$root.dataset.reverbScheme === 'https',
                    enabledTransports: ['ws', 'wss'],
                });

                this.echo.private(`chat.conversation.${conversationId}`)
                    .listen('.message.sent', () => this.handleConversationEvent())
                    .listen('.message.deleted', () => this.handleConversationEvent())
                    .listen('.conversation.read', () => this.handleConversationEvent())
                    .listen('.poll.created', () => this.handleConversationEvent())
                    .listen('.poll.voted', () => this.handleConversationEvent())
                    .listen('.poll.closed', () => this.handleConversationEvent());

                this.echo.join(`chat.presence.${conversationId}`)
                    .here((users) => { this.onlineCount = users.length; })
                    .joining(() => { this.onlineCount += 1; })
                    .leaving(() => { this.onlineCount = Math.max(0, this.onlineCount - 1); })
                    .error(() => { this.onlineCount = 0; });

                const userId = Number(this.$root.dataset.userId || 0);
                if (userId) {
                    this.echo.private(`chat.user.${userId}`)
                        .listen('.message.sent', (payload) => this.handleUserEvent(payload));
                }

                const connection = this.echo.connector.pusher.connection;
                connection.bind('connected', () => {
                    this.connectionState = 'live';
                    this.startPolling(15000, true);
                });
                connection.bind('error', () => this.enablePollingFallback());
                connection.bind('unavailable', () => this.enablePollingFallback());
                connection.bind('disconnected', () => this.enablePollingFallback());

                this.startPolling(1000);

                return;
            } catch (_error) {
                this.disconnectRealtime();
            }
        }

        this.enablePollingFallback();
    },

    filterConversations(event) {
        const query = (event?.target?.value ?? this.$refs.sidebarSearch?.value ?? '').trim().toLowerCase();
        const rows = this.$root.querySelectorAll('[data-conversation-row]');

        rows.forEach((row) => {
            const searchable = row.dataset.search || row.textContent || '';
            const matches = query === '' || searchable.toLowerCase().includes(query);
            row.hidden = ! matches;
            row.style.display = matches ? '' : 'none';
        });

        this.$root.querySelectorAll('.cc-section-head').forEach((head) => {
            let next = head.nextElementSibling;
            let hasVisibleRow = false;
            while (next && ! next.classList.contains('cc-section-head')) {
                if (next.hasAttribute('data-conversation-row') && ! next.hidden && next.style.display !== 'none') {
                    hasVisibleRow = true;
                    break;
                }
                next = next.nextElementSibling;
            }
            head.hidden = query !== '' && ! hasVisibleRow;
            head.style.display = head.hidden ? 'none' : '';
        });
    },

    csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    formAction(form) {
        const action = form.getAttribute('action');

        if (! action) {
            throw new Error('This action is temporarily unavailable.');
        }

        return new URL(action, window.location.href).toString();
    },

    async request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: options.accept || 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfToken(),
                ...(options.headers || {}),
            },
            ...options,
        });

        if (response.status === 419) {
            this.connectionState = 'offline';
            throw new Error('Your session expired. Refresh the page and sign in again.');
        }

        if (! response.ok) {
            let message = 'The action could not be completed.';
            try {
                const payload = await response.json();
                message = Object.values(payload.errors || {}).flat().at(0) || payload.message || message;
            } catch (_error) {
                // Keep the business-safe fallback message.
            }

            throw new Error(message);
        }

        return response;
    },

    async sendMessage(event) {
        if (this.busy) {
            return;
        }

        const form = event.currentTarget;
        this.busy = true;
        this.statusTone = 'info';
        this.statusMessage = 'Sending…';

        try {
            await this.request(this.formAction(form), {
                method: 'POST',
                body: new FormData(form),
            });
            form.reset();
            this.selectedAttachments = [];
            this.cancelReply();
            form.querySelectorAll('details[open]').forEach((details) => details.removeAttribute('open'));
            this.statusMessage = '';
            await Promise.all([this.refreshTimeline(true), this.refreshSidebar()]);
        } catch (error) {
            this.statusTone = 'error';
            this.statusMessage = error.message;
        } finally {
            this.busy = false;
        }
    },

    handleComposerKeydown(event) {
        if (event.shiftKey || event.isComposing || this.busy) {
            return;
        }

        event.preventDefault();
        event.currentTarget.form?.requestSubmit();
    },

    handleTimelineClick(event) {
        const replyBtn = event?.target?.closest?.('.b360-chat-reply-action');
        if (replyBtn) {
            this.selectReply({ currentTarget: replyBtn });
        }
    },

    selectReply(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        const target = (event?.currentTarget || event?.target)?.closest?.('.b360-chat-reply-action') || event?.currentTarget;
        if (! target) {
            return;
        }

        const messageId = target.dataset.messageId;
        const sender = target.dataset.messageSender || 'Message';
        const body = target.dataset.messageBody || '';

        if (! messageId) {
            return;
        }

        this.replyTarget = { id: messageId, sender, body };

        const parentInput = this.$refs.parentMessageInput || this.$refs.composer?.querySelector('[name="parent_message_id"]') || document.querySelector('[name="parent_message_id"]');
        if (parentInput) {
            if (parentInput.tagName === 'SELECT') {
                let option = parentInput.querySelector(`option[value="${messageId}"]`);
                if (! option) {
                    option = document.createElement('option');
                    option.value = messageId;
                    option.text = `Replying to ${sender}`;
                    parentInput.appendChild(option);
                }
                parentInput.value = messageId;
            } else {
                parentInput.value = messageId;
            }
        }

        this.statusTone = 'info';
        this.statusMessage = `Replying to ${sender}`;
        
        const textarea = this.$refs.composer?.querySelector('textarea[name="body"]') || document.querySelector('textarea[name="body"]');
        if (textarea) {
            textarea.focus();
        }
    },

    cancelReply() {
        this.replyTarget = null;
        const parentInput = this.$refs.parentMessageInput || this.$refs.composer?.querySelector('[name="parent_message_id"]') || document.querySelector('[name="parent_message_id"]');
        if (parentInput) {
            parentInput.value = '';
        }
        if (this.statusMessage?.startsWith('Replying to')) {
            this.statusMessage = '';
        }
    },

    closeComposerPanel(event) {
        event.currentTarget.closest('details')?.removeAttribute('open');
        this.$refs.composer?.querySelector('textarea[name="body"]')?.focus();
    },

    handlePaste(event) {
        const clipboard = event.clipboardData || window.clipboardData;
        if (! clipboard) {
            return;
        }

        const files = [];

        // 1. Check direct clipboard files (File Explorer, Snipping Tool, Desktop, etc.)
        if (clipboard.files && clipboard.files.length > 0) {
            Array.from(clipboard.files).forEach((file) => {
                if (file.type && file.type.startsWith('image/')) {
                    files.push(file);
                }
            });
        }

        // 2. Check clipboard items (Browser images, Canvas, PrintScreen, Clipboard)
        if (files.length === 0 && clipboard.items && clipboard.items.length > 0) {
            Array.from(clipboard.items).forEach((item) => {
                if (item.type && item.type.startsWith('image/')) {
                    const file = item.getAsFile();
                    if (file) {
                        files.push(file);
                    }
                }
            });
        }

        if (files.length === 0) {
            return;
        }

        if (typeof DataTransfer === 'undefined') {
            return;
        }

        // Ensure file input exists in composer form
        let input = this.$refs.composer?.querySelector('input[type="file"][name="attachments[]"]');
        if (! input) {
            input = document.createElement('input');
            input.type = 'file';
            input.name = 'attachments[]';
            input.multiple = true;
            input.hidden = true;
            this.$refs.composer?.appendChild(input);
        }

        const transfer = new DataTransfer();
        // Preserve any existing attachments
        Array.from(input.files || []).forEach((f) => transfer.items.add(f));

        let addedCount = 0;
        files.forEach((file, idx) => {
            const rawExt = file.type ? file.type.split('/')[1] : 'png';
            const ext = rawExt ? rawExt.replace('+xml', '').replace('svg', 'png') : 'png';
            const filename = (file.name && file.name !== 'image.png' && file.name !== 'blob')
                ? file.name
                : `pasted-image-${Date.now()}-${idx + 1}.${ext}`;

            const renamedFile = new File([file], filename, { type: file.type || 'image/png' });
            transfer.items.add(renamedFile);
            addedCount++;
        });

        if (addedCount > 0) {
            input.files = transfer.files;
            this.selectedAttachments = Array.from(transfer.files).map((file, index) => ({
                file,
                name: file.name,
                size: file.size,
                key: `${file.name}-${file.size}-${file.lastModified}-${index}`,
            }));
        }
    },

    selectAttachments(event) {
        this.selectedAttachments = Array.from(event.currentTarget.files || []).map((file, index) => ({
            file,
            name: file.name,
            size: file.size,
            key: `${file.name}-${file.size}-${file.lastModified}-${index}`,
        }));
    },

    removeAttachment(event) {
        const fileKey = event.currentTarget.dataset.fileKey;
        const index = this.selectedAttachments.findIndex((attachment) => attachment.key === fileKey);
        const input = this.$refs.composer?.querySelector('input[type="file"][name="attachments[]"]');

        if (! input || index < 0) {
            return;
        }

        this.selectedAttachments.splice(index, 1);

        if (typeof DataTransfer === 'undefined') {
            input.value = '';
            this.selectedAttachments = [];
            return;
        }

        const transfer = new DataTransfer();
        this.selectedAttachments.forEach((attachment) => transfer.items.add(attachment.file));
        input.files = transfer.files;
    },

    filterMentionOptions(event) {
        this.applyMentionFilter(event.currentTarget.value);
    },

    applyMentionFilter(value = '') {
        const query = value.trim().toLowerCase();
        const options = Array.from(this.$refs.mentionMenu?.querySelectorAll('[data-mention-option]') || []);
        let visible = 0;

        options.forEach((option) => {
            const matches = ! query || (option.dataset.search || '').includes(query);
            option.hidden = ! matches;
            option.style.display = matches ? '' : 'none';
            if (matches) {
                visible += 1;
            }
        });

        this.mentionMatchCount = visible;
    },

    handleComposerInput(event) {
        const textarea = event.currentTarget;
        const cursor = textarea.selectionStart ?? textarea.value.length;
        const beforeCursor = textarea.value.slice(0, cursor);
        const match = beforeCursor.match(/(?:^|\s)@([^\s@]*)$/);

        if (! match) {
            this.mentionTriggerStart = null;
            return;
        }

        this.mentionTriggerStart = cursor - match[1].length - 1;

        if (this.$refs.mentionMenu) {
            this.$refs.mentionMenu.open = true;
        }

        if (this.$refs.mentionSearch) {
            this.$refs.mentionSearch.value = match[1];
        }

        this.applyMentionFilter(match[1]);
    },

    toggleMention(event) {
        if (! event.currentTarget.checked) {
            return;
        }

        const name = event.currentTarget.dataset.mentionName;
        const textarea = this.$refs.composer?.querySelector('textarea[name="body"]');
        if (! name || ! textarea) {
            return;
        }

        const tag = `@${name}`;
        const cursor = textarea.selectionStart ?? textarea.value.length;

        if (this.mentionTriggerStart !== null) {
            textarea.value = `${textarea.value.slice(0, this.mentionTriggerStart)}${tag} ${textarea.value.slice(cursor)}`;
        } else if (! textarea.value.includes(tag)) {
            textarea.value = `${textarea.value}${textarea.value && ! textarea.value.endsWith(' ') ? ' ' : ''}${tag} `;
        }

        const nextCursor = this.mentionTriggerStart !== null ? this.mentionTriggerStart + tag.length + 1 : textarea.value.length;
        this.mentionTriggerStart = null;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.setSelectionRange(nextCursor, nextCursor);

        if (this.$refs.mentionMenu) {
            this.$refs.mentionMenu.open = false;
        }

        textarea.focus();
    },

    async submitTimelineAction(event) {
        if (this.busy) {
            return;
        }

        const form = event.currentTarget;
        this.busy = true;
        this.statusMessage = '';

        try {
            await this.request(this.formAction(form), { method: 'POST', body: new FormData(form) });
            await Promise.all([this.refreshTimeline(false), this.refreshSidebar()]);
            this.autoMarkRead();
        } catch (error) {
            this.statusTone = 'error';
            this.statusMessage = error.message;
        } finally {
            this.busy = false;
        }
    },

    async deleteMessage(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (! window.confirm('Are you sure you want to delete this message?')) {
            return;
        }

        const form = event?.target ? (event.target.tagName === 'FORM' ? event.target : event.target.closest('form')) : (event?.currentTarget || null);
        if (! form) {
            return;
        }

        const article = form.closest('article.b360-thread-message') || form.closest('article');
        if (article) {
            article.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            article.style.opacity = '0';
            article.style.transform = 'scale(0.95)';
            setTimeout(() => {
                if (article.parentNode) {
                    article.remove();
                }
            }, 200);
        }

        try {
            await this.request(this.formAction(form), { method: 'POST', body: new FormData(form) });
            await Promise.all([this.refreshTimeline(false), this.refreshSidebar()]);
        } catch (error) {
            this.statusTone = 'error';
            this.statusMessage = error.message;
            await this.refreshTimeline(false);
        }
    },

    async refreshTimeline(forceBottom = false) {
        if (! this.$root.dataset.timelineUrl) {
            return;
        }

        const current = this.$root.querySelector('[x-ref="timeline"]');
        if (! current) {
            return;
        }

        const nearBottom = current.scrollHeight - current.scrollTop - current.clientHeight < 140;
        const previousScrollTop = current.scrollTop;

        try {
            const response = await this.request(this.$root.dataset.timelineUrl, { accept: 'text/html' });
            const html = await response.text();
            const template = document.createElement('template');
            template.innerHTML = html.trim();
            const incoming = template.content.querySelector('.b360-thread-timeline');

            if (! incoming || incoming.innerHTML === current.innerHTML) {
                return;
            }

            current.innerHTML = incoming.innerHTML;
            window.Alpine.initTree(this.$root);

            if (forceBottom || nearBottom) {
                current.scrollTop = current.scrollHeight;
            } else {
                current.scrollTop = previousScrollTop;
            }
        } catch (_error) {
            this.enablePollingFallback();
        }
    },

    async refreshSidebar() {
        if (! this.$root.dataset.sidebarUrl) {
            return;
        }

        const current = this.$root.querySelector('[x-ref="sidebar"]');
        if (! current) {
            return;
        }

        try {
            const response = await this.request(this.$root.dataset.sidebarUrl, { accept: 'text/html' });
            const html = await response.text();

            if (html.trim() !== current.innerHTML) {
                current.innerHTML = html.trim();
                window.Alpine.initTree(current);
            }
        } catch (_error) {
            this.enablePollingFallback();
        }
    },

    async autoMarkRead() {
        if (! this.$root.dataset.readUrl) {
            return;
        }

        try {
            await this.request(this.$root.dataset.readUrl, { method: 'PATCH' });
            this.$root.dataset.selectedUnread = '0';
            const activeRow = this.$root.querySelector('[data-conversation-row].is-active');
            if (activeRow) {
                const badge = activeRow.querySelector('.cc-unread-badge');
                if (badge) {
                    badge.remove();
                }
            }
        } catch (_error) {
            // Manual mark-read remains available in the conversation header.
        }
    },

    handleConversationEvent() {
        this.refreshTimeline(false).then(() => {
            this.autoMarkRead();
        });
        this.refreshSidebar();
    },

    handleUserEvent(payload) {
        const currentConversationId = Number(this.$root.dataset.conversationId || 0);
        if (Number(payload?.conversation_id || 0) === currentConversationId) {
            this.refreshTimeline(false);
            this.autoMarkRead();
        }
        this.refreshSidebar();
    },

    enablePollingFallback() {
        this.connectionState = navigator.onLine ? 'periodic' : 'offline';
        this.startPolling(1000, true);
    },

    startPolling(interval = 1000, restart = false) {
        if (restart && this.pollTimer) {
            window.clearInterval(this.pollTimer);
            this.pollTimer = null;
        }

        if (this.pollTimer) {
            return;
        }

        this.pollTimer = window.setInterval(() => {
            if (document.hidden) {
                return;
            }

            this.refreshTimeline(false);
            this.refreshSidebar();
        }, interval);
    },

    scrollTimelineToBottom(smooth = true) {
        const timeline = this.$root.querySelector('[x-ref="timeline"]');
        timeline?.scrollTo({ top: timeline.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    },

    disconnectRealtime() {
        if (this.echo) {
            const conversationId = Number(this.$root.dataset.conversationId || 0);
            if (conversationId) {
                this.echo.leave(`chat.presence.${conversationId}`);
            }
            this.echo.disconnect();
            this.echo = null;
        }
    },

    destroy() {
        this.disconnectRealtime();
        window.clearInterval(this.pollTimer);
    },
}));

window.getChatComponent = function() {
    const el = document.querySelector('[x-data="chatRealtime"]');
    if (el && window.Alpine) {
        return window.Alpine.$data(el);
    }
    return null;
};

window.handleTimelineClick = function(event) {
    window.getChatComponent()?.handleTimelineClick?.(event);
};

window.deleteMessage = function(event) {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }
    window.getChatComponent()?.deleteMessage?.(event);
};

window.submitTimelineAction = function(event) {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }
    window.getChatComponent()?.submitTimelineAction?.(event);
};

window.selectReply = function(event) {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }
    window.getChatComponent()?.selectReply?.(event);
};

window.cancelReply = function() {
    window.getChatComponent()?.cancelReply?.();
};

Alpine.start();
