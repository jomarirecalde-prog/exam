import './bootstrap';
import { registerServiceWorker } from './pwa/register-sw';
import './pwa/install';
import examOfflineDb from './offline/db';
import { prepareExaminationOffline, loadPreparedExam, isAuthorizationValid } from './offline/exam-preparation';
import { enqueueSyncEvent, getQueueSummary } from './offline/sync-queue';
import { syncAttempt, installSyncListeners, isOnline, verifyServerReachable } from './offline/sync-engine';
import { createTimerState, persistTimer, loadTimer, tickTimer } from './offline/timer';
import { getDeviceIdentifier, getDeviceName } from './offline/device';
import { bootstrapOfflineAccess, isOfflineSessionValid } from './offline/session';
import { listCatalogEntries, EXAM_STATUS } from './offline/catalog';
import { verifyPin, isPinConfigured, isUnlocked } from './offline/app-lock';
import { persistExamProgress, getAttemptState } from './offline/attempt-state';

registerServiceWorker();

const THEME_KEY = 'exam-theme';
const SIDEBAR_KEY = 'exam-sidebar-collapsed';

window.examUi = {
    getStoredTheme() {
        return localStorage.getItem(THEME_KEY) || 'system';
    },
    resolveTheme(preference = null) {
        const pref = preference ?? this.getStoredTheme();
        if (pref === 'dark' || pref === 'light') {
            return pref;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    },
    applyTheme(preference = null) {
        const resolved = this.resolveTheme(preference);
        document.documentElement.classList.toggle('dark', resolved === 'dark');
        document.documentElement.style.colorScheme = resolved;
    },
    setTheme(preference) {
        localStorage.setItem(THEME_KEY, preference);
        this.applyTheme(preference);
    },
};

window.examUi.applyTheme();

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (window.examUi.getStoredTheme() === 'system') {
        window.examUi.applyTheme('system');
    }
});

window.appToast = function appToast(message, type = 'success') {
    window.dispatchEvent(new CustomEvent('app-toast', { detail: { message, type } }));
};

window.examShell = function examShell() {
    return {
        collapsed: localStorage.getItem(SIDEBAR_KEY) === '1',
        mobileOpen: false,
        theme: window.examUi.getStoredTheme(),
        toasts: [],
        init() {
            window.addEventListener('app-toast', (event) => {
                this.pushToast(event.detail.message, event.detail.type || 'success');
            });
            this.bootstrapOfflineIfStudent();
        },
        async bootstrapOfflineIfStudent() {
            const meta = document.querySelector('meta[name="exam-student-id"]');
            const bootstrapUrl = document.querySelector('meta[name="offline-bootstrap-url"]')?.content;
            if (!meta?.content || !bootstrapUrl || !navigator.onLine) {
                return;
            }
            try {
                await bootstrapOfflineAccess(bootstrapUrl, getDeviceIdentifier(), getDeviceName());
                this.cacheBuildAssetsForOffline();
            } catch {
                /* offline bootstrap is best-effort */
            }
        },
        cacheBuildAssetsForOffline() {
            const urls = [
                ...document.querySelectorAll('script[src*="/build/"], link[href*="/build/"]'),
            ].map((el) => el.src || el.href).filter(Boolean);
            if (urls.length && navigator.serviceWorker?.controller) {
                navigator.serviceWorker.controller.postMessage({
                    type: 'CACHE_BUILD_ASSETS',
                    urls,
                });
            }
        },
        toggleCollapsed() {
            this.collapsed = !this.collapsed;
            localStorage.setItem(SIDEBAR_KEY, this.collapsed ? '1' : '0');
        },
        setTheme(value) {
            this.theme = value;
            window.examUi.setTheme(value);
        },
        toast(message, type = 'success') {
            window.appToast(message, type);
        },
        pushToast(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type });
            setTimeout(() => {
                this.toasts = this.toasts.filter((item) => item.id !== id);
            }, 4200);
        },
        removeToast(id) {
            this.toasts = this.toasts.filter((item) => item.id !== id);
        },
    };
};

window.deleteRecordModal = function deleteRecordModal() {
    return {
        open: false,
        submitting: false,
        title: 'Delete Record?',
        recordName: '',
        recordDetail: '',
        warning: '',
        blocked: false,
        blockedMessage: '',
        blockers: [],
        action: '',
        confirmLabel: 'Delete',
        method: 'DELETE',
        openModal(detail = {}) {
            this.title = detail.title || 'Delete Record?';
            this.recordName = detail.recordName || '';
            this.recordDetail = detail.recordDetail || '';
            this.warning = detail.warning || '';
            this.blocked = Boolean(detail.blocked);
            this.blockedMessage = detail.blockedMessage || '';
            this.blockers = Array.isArray(detail.blockers) ? detail.blockers : [];
            this.action = detail.action || '';
            this.confirmLabel = detail.confirmLabel || 'Delete';
            this.method = detail.method || 'DELETE';
            this.submitting = false;
            this.open = true;
        },
        closeModal() {
            if (this.submitting) {
                return;
            }
            this.open = false;
        },
        handleSubmit(event) {
            if (this.blocked) {
                event.preventDefault();
                return;
            }
            if (this.submitting) {
                event.preventDefault();
                return;
            }
            this.submitting = true;
        },
    };
};

window.csvImportPanel = function csvImportPanel(config = {}) {
    return {
        templateUrl: config.templateUrl || '',
        previewUrl: config.previewUrl || '',
        confirmUrl: config.confirmUrl || '',
        errorReportUrl: config.errorReportUrl || '',
        subjectField: Boolean(config.subjectField),
        bankModes: Boolean(config.bankModes),
        importModes: config.importModes || {
            append: 'Append imported questions',
            replace: 'Replace existing questions',
        },
        dragActive: false,
        selectedFile: null,
        importMode: 'append',
        bankImportMode: 'upsert',
        subjectId: '',
        subjectError: '',
        busy: false,
        confirming: false,
        statusMessage: '',
        previewOpen: false,
        confirmOpen: false,
        previewToken: null,
        previewRows: [],
        rowErrors: [],
        stats: {},
        pendingQuestions: [],
        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },
        formatFileSize(size) {
            if (!size) {
                return '';
            }
            if (size < 1024) {
                return `${size} B`;
            }
            if (size < 1024 * 1024) {
                return `${Math.round(size / 1024)} KB`;
            }
            return `${(size / (1024 * 1024)).toFixed(1)} MB`;
        },
        handleDrop(event) {
            this.dragActive = false;
            const file = event.dataTransfer?.files?.[0];
            if (file) {
                this.setFile(file);
            }
        },
        handleFileSelect(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (file) {
                this.setFile(file);
            }
        },
        setFile(file) {
            if (!file.name.toLowerCase().endsWith('.csv') && file.type !== 'text/csv') {
                window.appToast('Please choose a CSV file.', 'error');
                return;
            }
            this.selectedFile = file;
            this.rowErrors = [];
            this.statusMessage = '';
        },
        clearFile() {
            this.selectedFile = null;
            this.previewToken = null;
            this.previewRows = [];
            this.rowErrors = [];
            this.stats = {};
            this.statusMessage = '';
            this.previewOpen = false;
            this.confirmOpen = false;
        },
        async startPreview() {
            if (!this.selectedFile || !this.previewUrl) {
                return;
            }

            if (this.subjectField && !this.subjectId) {
                this.subjectError = 'Please select a subject before importing.';
                window.appToast(this.subjectError, 'error');
                return;
            }

            this.subjectError = '';
            this.busy = true;
            this.statusMessage = 'Uploading CSV file...';

            const body = new FormData();
            body.append('file', this.selectedFile);
            if (this.subjectId) {
                body.append('subject_id', this.subjectId);
            }

            try {
                this.statusMessage = 'Validating CSV data...';
                const response = await fetch(this.previewUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body,
                });
                const data = await response.json().catch(() => ({}));

                this.rowErrors = data.rowErrors || [];
                this.stats = data.stats || {};
                this.previewRows = data.preview || [];
                this.previewToken = data.token || null;
                this.pendingQuestions = data.questions || [];

                if (!response.ok) {
                    const validationMessage = data.errors?.file?.[0];
                    window.appToast(validationMessage || data.message || 'Unable to preview this CSV file.', 'error');
                    this.statusMessage = 'Validation failed. Please review the errors and try again.';
                    if (this.rowErrors.length || this.previewRows.length) {
                        this.previewOpen = true;
                    }
                    return;
                }

                this.statusMessage = '';
                this.previewOpen = true;

                if ((this.stats.valid ?? 0) === 0) {
                    window.appToast(data.message || 'No valid questions were found in this CSV file.', 'warning');
                }
            } catch (error) {
                window.appToast('Unable to preview the CSV file.', 'error');
                this.statusMessage = 'Validation failed. Please review the errors and try again.';
            } finally {
                this.busy = false;
            }
        },
        openConfirm() {
            this.previewOpen = false;
            this.confirmOpen = true;
        },
        async confirmImport() {
            if (!this.previewToken || this.confirming) {
                return;
            }

            this.confirming = true;
            this.statusMessage = 'Importing records...';

            try {
                const payload = {
                    token: this.previewToken,
                    import_mode: this.bankModes ? this.bankImportMode : this.importMode,
                };

                if (this.bankModes) {
                    payload.subject_id = this.subjectId;
                }

                const response = await fetch(this.confirmUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify(payload),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    window.appToast(data.message || 'Import failed.', 'error');
                    this.statusMessage = 'Import failed. Please review the errors and try again.';
                    return;
                }

                this.confirmOpen = false;
                this.statusMessage = data.message || `Successfully imported ${this.stats.valid || 0} record(s).`;
                window.appToast(this.statusMessage, 'success');

                this.$dispatch('csv-imported', {
                    questions: data.questions || this.pendingQuestions,
                    importMode: this.bankModes ? this.bankImportMode : this.importMode,
                    counts: data.counts || null,
                });

                if (this.bankModes) {
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    this.clearFile();
                }
            } catch (error) {
                window.appToast('Import failed. Please try again.', 'error');
                this.statusMessage = 'Import failed. Please review the errors and try again.';
            } finally {
                this.confirming = false;
            }
        },
        async downloadErrorReport() {
            if (!this.selectedFile || !this.errorReportUrl) {
                return;
            }

            const body = new FormData();
            body.append('file', this.selectedFile);
            if (this.subjectId) {
                body.append('subject_id', this.subjectId);
            }

            try {
                const response = await fetch(this.errorReportUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'text/csv',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body,
                });

                if (!response.ok) {
                    window.appToast('No import errors to download.', 'warning');
                    return;
                }

                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `import-errors-${new Date().toISOString().slice(0, 10)}.csv`;
                link.click();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                window.appToast('Unable to download the error report.', 'error');
            }
        },
    };
};

window.questionBankPage = function questionBankPage(config = {}) {
    return {
        exportUrl: config.exportUrl || '',
        filters: config.filters || {},
        exportFormat: 'csv',
        exportScope: 'filtered',
        exporting: false,
        exportCsv() {
            if (!this.exportUrl || this.exporting) {
                return;
            }

            this.exporting = true;
            const params = new URLSearchParams({
                scope: this.exportScope,
            });

            if (this.exportScope === 'filtered') {
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value) {
                        params.set(key, value);
                    }
                });
            }

            window.location.href = `${this.exportUrl}?${params.toString()}`;
            setTimeout(() => {
                this.exporting = false;
            }, 1200);
        },
    };
};

window.examWizard = function examWizard(config = {}) {
    const incomingForm = config.form || {};

    return {
        step: config.step || 1,
        saving: false,
        publishing: false,
        examinationId: config.examinationId || null,
        storeUrl: config.storeUrl || '',
        updateUrl: config.updateUrl || '',
        sectionsUrl: config.sectionsUrl || '',
        offeringsUrl: config.offeringsUrl || '',
        importQuestionsUrl: config.importQuestionsUrl || '',
        questionCsvTemplateUrl: config.questionCsvTemplateUrl || '',
        indexUrl: config.indexUrl || '',
        academicYears: config.academicYears || [],
        semesters: config.semesters || [],
        subjects: config.subjects || [],
        programs: config.programs || [],
        yearLevels: config.yearLevels || [],
        availableSections: [],
        availableOfferings: [],
        selectedSections: config.selectedSections || [],
        sectionsLoading: false,
        offeringsLoading: false,
        sectionQuery: '',
        sectionMenuOpen: false,
        sectionAbort: null,
        importMode: 'append',
        errors: config.errors || {},
        steps: [
            { id: 1, key: '01', label: 'Information' },
            { id: 2, key: '02', label: 'Settings' },
            { id: 3, key: '03', label: 'Questions' },
            { id: 4, key: '04', label: 'Review' },
        ],
        questions: Array.isArray(config.questions)
            ? config.questions
            : [
            {
                id: 1,
                type: 'multiple_choice',
                text: 'Which of the following best describes an information system?',
                choices: [
                    { id: 'A', text: 'A collection of hardware only' },
                    { id: 'B', text: 'People, processes, and technology working together' },
                    { id: 'C', text: 'A programming language' },
                    { id: 'D', text: 'A network protocol' },
                ],
                correctAnswer: 'B',
                sampleAnswer: '',
                points: 1,
                difficulty: 'Medium',
                topic: 'IS Fundamentals',
            },
        ],
        form: {
            title: incomingForm.title || '',
            academicYearId: incomingForm.academicYearId ? String(incomingForm.academicYearId) : '',
            semesterId: incomingForm.semesterId ? String(incomingForm.semesterId) : '',
            subjectId: incomingForm.subjectId ? String(incomingForm.subjectId) : '',
            subjectOfferingId: incomingForm.subjectOfferingId ? String(incomingForm.subjectOfferingId) : '',
            programId: incomingForm.programId ? String(incomingForm.programId) : '',
            yearLevelId: incomingForm.yearLevelId ? String(incomingForm.yearLevelId) : '',
            sectionIds: (incomingForm.sectionIds || []).map((id) => Number(id)),
            accessMode: incomingForm.accessMode || 'subject_and_sections',
            period: incomingForm.period || 'MIDTERM',
            duration: incomingForm.duration ?? 60,
            passing: incomingForm.passing ?? 75,
            instructions: incomingForm.instructions || '',
            randomize: incomingForm.randomize ?? true,
            backNav: incomingForm.backNav ?? true,
            autoSubmit: incomingForm.autoSubmit ?? true,
            offlineMode: incomingForm.offlineMode || 'disabled',
            allowOfflineContinuation: incomingForm.allowOfflineContinuation ?? false,
            requireOfflinePreparation: incomingForm.requireOfflinePreparation ?? false,
            allowPendingOfflineSubmission: incomingForm.allowPendingOfflineSubmission ?? true,
            maxOfflineDuration: incomingForm.maxOfflineDuration ?? 30,
            syncGracePeriod: incomingForm.syncGracePeriod ?? 15,
            availableFromDate: incomingForm.availableFromDate || '',
            availableFromTime: incomingForm.availableFromTime || '',
            deadlineDate: incomingForm.deadlineDate || '',
            deadlineTime: incomingForm.deadlineTime || '',
            deadlinePolicy: incomingForm.deadlinePolicy || 'allow_active_finish',
        },
        availabilityMode: incomingForm.availabilityImmediate === false ? 'scheduled' : 'immediate',
        init() {
            if (this.filtersReady()) {
                this.fetchSections({ prune: false });
                this.fetchOfferings({ preserveSelection: true });
            }
        },
        get filteredSemesters() {
            return this.semesters.filter((item) => String(item.academic_year_id) === String(this.form.academicYearId));
        },
        get filteredYearLevels() {
            return this.yearLevels.filter((item) => String(item.program_id) === String(this.form.programId));
        },
        get filteredAvailable() {
            const query = this.sectionQuery.trim().toLowerCase();
            const selected = new Set(this.form.sectionIds.map((id) => Number(id)));

            return this.availableSections.filter((section) => {
                if (selected.has(Number(section.id))) {
                    return false;
                }
                if (!query) {
                    return true;
                }
                return [section.name, section.code, section.label]
                    .filter(Boolean)
                    .some((value) => String(value).toLowerCase().includes(query));
            });
        },
        get selectedCount() {
            return this.selectedSections.length;
        },
        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },
        jsonHeaders() {
            return {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfToken(),
            };
        },
        filtersReady() {
            return Boolean(
                this.form.academicYearId
                && this.form.semesterId
                && this.form.subjectId
                && this.form.programId
                && this.form.yearLevelId
            );
        },
        fieldError(name) {
            const value = this.errors[name];
            if (!value) {
                return '';
            }
            return Array.isArray(value) ? value[0] : value;
        },
        onAcademicYearChange() {
            const valid = this.filteredSemesters.some((item) => String(item.id) === String(this.form.semesterId));
            if (!valid) {
                this.form.semesterId = '';
            }
            this.onFilterChange();
        },
        onProgramChange() {
            const valid = this.filteredYearLevels.some((item) => String(item.id) === String(this.form.yearLevelId));
            if (!valid) {
                this.form.yearLevelId = '';
            }
            this.onFilterChange();
        },
        onFilterChange() {
            this.errors = { ...this.errors, section_ids: null };
            this.form.subjectOfferingId = '';
            this.fetchSections({ prune: true });
            this.fetchOfferings({ preserveSelection: false });
        },
        onOfferingChange() {
            const offering = this.availableOfferings.find((item) => String(item.id) === String(this.form.subjectOfferingId));
            if (offering?.section_id && !this.form.sectionIds.includes(Number(offering.section_id))) {
                const section = {
                    id: offering.section_id,
                    name: offering.section_name,
                    code: offering.section_code,
                    label: offering.section_name,
                };
                this.selectSection(section);
            }
        },
        selectedOfferingLabel() {
            const offering = this.availableOfferings.find((item) => String(item.id) === String(this.form.subjectOfferingId));
            if (!offering) {
                return '';
            }
            return `${offering.code} — ${offering.name}`;
        },
        async fetchOfferings({ preserveSelection = false } = {}) {
            if (!this.form.academicYearId || !this.form.semesterId || !this.form.subjectId) {
                this.availableOfferings = [];
                if (!preserveSelection) {
                    this.form.subjectOfferingId = '';
                }
                return;
            }

            this.offeringsLoading = true;

            try {
                const params = new URLSearchParams({
                    academic_year_id: this.form.academicYearId,
                    semester_id: this.form.semesterId,
                    subject_id: this.form.subjectId,
                });
                const response = await fetch(`${this.offeringsUrl}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                this.availableOfferings = data.offerings || [];
                if (!preserveSelection || !this.availableOfferings.some((item) => String(item.id) === String(this.form.subjectOfferingId))) {
                    this.form.subjectOfferingId = '';
                }
            } catch (error) {
                this.availableOfferings = [];
                if (!preserveSelection) {
                    this.form.subjectOfferingId = '';
                }
            } finally {
                this.offeringsLoading = false;
            }
        },
        openSectionMenu() {
            if (!this.filtersReady()) {
                return;
            }
            this.sectionMenuOpen = true;
            if (this.availableSections.length === 0 && !this.sectionsLoading) {
                this.fetchSections({ prune: false });
            }
        },
        async fetchSections({ prune = true } = {}) {
            if (!this.filtersReady()) {
                this.availableSections = [];
                this.sectionsLoading = false;
                if (prune) {
                    this.selectedSections = [];
                    this.form.sectionIds = [];
                }
                return;
            }

            if (this.sectionAbort) {
                this.sectionAbort.abort();
            }

            this.sectionAbort = new AbortController();
            this.sectionsLoading = true;

            const params = new URLSearchParams({
                academic_year_id: this.form.academicYearId,
                semester_id: this.form.semesterId,
                subject_id: this.form.subjectId,
                program_id: this.form.programId,
                year_level_id: this.form.yearLevelId,
            });

            try {
                const response = await fetch(`${this.sectionsUrl}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: this.sectionAbort.signal,
                });
                const data = await response.json();
                this.availableSections = data.sections || [];

                if (prune) {
                    this.pruneSelectedSections();
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.availableSections = [];
                    window.appToast('Unable to load sections right now.', 'error');
                }
            } finally {
                this.sectionsLoading = false;
            }
        },
        pruneSelectedSections() {
            const allowed = new Set(this.availableSections.map((section) => Number(section.id)));
            this.selectedSections = this.selectedSections.filter((section) => allowed.has(Number(section.id)));
            this.form.sectionIds = this.selectedSections.map((section) => Number(section.id));
        },
        selectSection(section) {
            const id = Number(section.id);
            if (this.form.sectionIds.includes(id)) {
                return;
            }
            this.selectedSections.push(section);
            this.form.sectionIds.push(id);
            this.sectionQuery = '';
            this.errors = { ...this.errors, section_ids: null };
        },
        removeSection(id) {
            const sectionId = Number(id);
            this.selectedSections = this.selectedSections.filter((section) => Number(section.id) !== sectionId);
            this.form.sectionIds = this.form.sectionIds.filter((value) => Number(value) !== sectionId);
        },
        selectAllFiltered() {
            this.filteredAvailable.forEach((section) => this.selectSection(section));
        },
        selectedSubjectLabel() {
            const subject = this.subjects.find((item) => String(item.id) === String(this.form.subjectId));
            return subject ? `${subject.code} — ${subject.name}` : '—';
        },
        periodLabel() {
            return ({ PRELIM: 'Prelim', MIDTERM: 'Midterm', FINAL: 'Final' })[this.form.period] || this.form.period;
        },
        validateInformation() {
            const errors = {};
            if (!this.form.title.trim()) {
                errors.title = 'Please enter an examination title.';
            }
            if (!this.form.academicYearId) {
                errors.academic_year_id = 'Please select an academic year.';
            }
            if (!this.form.semesterId) {
                errors.semester_id = 'Please select a semester.';
            }
            if (!this.form.subjectId) {
                errors.subject_id = 'Please select a subject.';
            }
            if (!this.form.programId) {
                errors.program_id = 'Please select a program.';
            }
            if (!this.form.yearLevelId) {
                errors.year_level_id = 'Please select a year level.';
            }
            if (this.form.accessMode === 'subject_and_sections' && this.form.sectionIds.length === 0) {
                errors.section_ids = 'Please select at least one section before continuing.';
            }
            this.errors = errors;
            return Object.keys(errors).length === 0;
        },
        validateSettings() {
            const errors = {};
            if (this.availabilityMode === 'scheduled') {
                if (!this.form.availableFromDate) {
                    errors.available_from_date = 'Please set when the examination becomes available.';
                }
                if (!this.form.availableFromTime) {
                    errors.available_from_time = 'Please set the availability start time.';
                }
            }
            this.errors = errors;
            return Object.keys(errors).length === 0;
        },
        validateReview() {
            const errors = {};
            if (!this.form.deadlineDate) {
                errors.deadline_date = 'Please set an examination deadline before publishing.';
            }
            if (!this.form.deadlinePolicy) {
                errors.deadline_policy = 'Please select what happens when the examination deadline is reached.';
            }
            if (this.form.deadlineDate) {
                const start = this.availabilityMode === 'immediate'
                    ? new Date()
                    : new Date(`${this.form.availableFromDate}T${this.form.availableFromTime || '00:00'}`);
                const deadline = new Date(`${this.form.deadlineDate}T${this.form.deadlineTime || '23:59'}`);
                if (!Number.isNaN(start.getTime()) && !Number.isNaN(deadline.getTime()) && deadline <= start) {
                    errors.deadline_date = 'The examination deadline must be after the availability start date and time.';
                }
            }
            this.errors = errors;
            return Object.keys(errors).length === 0;
        },
        payload(status) {
            return {
                title: this.form.title,
                academic_year_id: this.form.academicYearId,
                semester_id: this.form.semesterId,
                subject_id: this.form.subjectId,
                subject_offering_id: this.form.subjectOfferingId || null,
                program_id: this.form.programId,
                year_level_id: this.form.yearLevelId,
                section_ids: this.form.sectionIds,
                access_mode: this.form.accessMode,
                examination_period: this.form.period,
                instructions: this.form.instructions,
                duration_minutes: this.form.duration,
                passing_percentage: this.form.passing,
                randomize_questions: Boolean(this.form.randomize),
                allow_back_navigation: Boolean(this.form.backNav),
                auto_submit_on_expire: Boolean(this.form.autoSubmit),
                offline_examination_mode: this.form.offlineMode,
                allow_offline_continuation: Boolean(this.form.allowOfflineContinuation),
                require_offline_preparation: Boolean(this.form.requireOfflinePreparation),
                allow_pending_offline_submission: Boolean(this.form.allowPendingOfflineSubmission),
                max_offline_duration_minutes: this.form.offlineMode === 'disabled' ? null : Number(this.form.maxOfflineDuration) || null,
                sync_grace_period_minutes: Number(this.form.syncGracePeriod) || 15,
                availability_immediate: this.availabilityMode === 'immediate',
                available_from_date: this.availabilityMode === 'scheduled' ? this.form.availableFromDate : null,
                available_from_time: this.availabilityMode === 'scheduled' ? this.form.availableFromTime : null,
                deadline_date: this.form.deadlineDate || null,
                deadline_time: this.form.deadlineTime || null,
                deadline_policy: this.form.deadlinePolicy,
                status,
                questions: this.questions.map(({ id, ...question }) => question),
            };
        },
        handleCsvImported(detail = {}) {
            const imported = this.assignImportedQuestionIds(detail.questions || []);
            const mode = detail.importMode || this.importMode;

            if (mode === 'replace') {
                this.questions = imported;
            } else {
                this.questions.push(...imported);
            }

            window.appToast(`${imported.length} question(s) added to this examination.`);
        },
        firstError(errors) {
            const bag = errors || this.errors;
            const first = Object.values(bag).find((value) => value);
            if (!first) {
                return 'Please review the examination details.';
            }
            return Array.isArray(first) ? first[0] : first;
        },
        status(id) {
            if (id < this.step) {
                return 'complete';
            }
            return id === this.step ? 'current' : 'upcoming';
        },
        next() {
            if (this.step === 1 && !this.validateInformation()) {
                return;
            }
            if (this.step === 2 && !this.validateSettings()) {
                return;
            }
            this.step = Math.min(4, this.step + 1);
        },
        back() {
            this.step = Math.max(1, this.step - 1);
        },
        saveDraft() {
            this.persist('DRAFT');
        },
        publish() {
            this.persist('PUBLISHED');
        },
        async persist(status) {
            if (!this.validateInformation()) {
                this.step = 1;
                window.appToast(this.firstError(), 'error');
                return;
            }
            if (!this.validateSettings()) {
                this.step = 2;
                window.appToast(this.firstError(), 'error');
                return;
            }
            if (status === 'PUBLISHED' && !this.validateReview()) {
                this.step = 4;
                window.appToast(this.firstError(), 'error');
                return;
            }

            const publishing = status === 'PUBLISHED';
            this.saving = !publishing;
            this.publishing = publishing;

            const url = this.examinationId ? this.updateUrl : this.storeUrl;
            const method = this.examinationId ? 'PUT' : 'POST';

            try {
                const response = await fetch(url, {
                    method,
                    headers: this.jsonHeaders(),
                    body: JSON.stringify(this.payload(status)),
                });
                const data = await response.json().catch(() => ({}));

                if (response.status === 422) {
                    this.errors = data.errors || {};
                    this.step = 1;
                    window.appToast(this.firstError(data.errors), 'error');
                    return;
                }

                if (!response.ok) {
                    window.appToast(data.message || 'Unable to save the examination.', 'error');
                    return;
                }

                window.appToast(data.message || (publishing ? 'Examination published.' : 'Examination saved successfully.'));
                this.examinationId = data.examination?.id || this.examinationId;
                this.updateUrl = data.updateUrl || this.updateUrl;

                if (publishing && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }

                if (data.redirect && !this.examinationId) {
                    window.location.href = data.redirect;
                }
            } catch (error) {
                window.appToast('Unable to save the examination.', 'error');
            } finally {
                this.saving = false;
                this.publishing = false;
            }
        },
        addQuestion() {
            this.questions.push(this.createQuestion());
            window.appToast('Question added.');
        },
        nextQuestionId() {
            return this.questions.reduce((max, question) => Math.max(max, Number(question.id) || 0), 0) + 1;
        },
        assignImportedQuestionIds(items) {
            let nextId = this.nextQuestionId();

            return items.map((question) => ({
                ...question,
                id: nextId++,
            }));
        },
        createQuestion(overrides = {}) {
            return {
                id: this.nextQuestionId(),
                type: 'multiple_choice',
                text: '',
                choices: [
                    { id: 'A', text: '' },
                    { id: 'B', text: '' },
                    { id: 'C', text: '' },
                    { id: 'D', text: '' },
                ],
                correctAnswer: '',
                sampleAnswer: '',
                points: 1,
                difficulty: 'Medium',
                topic: '',
                ...overrides,
            };
        },
        questionTypeDefaults(type) {
            const defaults = {
                multiple_choice: {
                    choices: [
                        { id: 'A', text: '' },
                        { id: 'B', text: '' },
                        { id: 'C', text: '' },
                        { id: 'D', text: '' },
                    ],
                    correctAnswer: '',
                    sampleAnswer: '',
                },
                true_false: {
                    choices: [
                        { id: 'true', text: 'True' },
                        { id: 'false', text: 'False' },
                    ],
                    correctAnswer: 'true',
                    sampleAnswer: '',
                },
                identification: {
                    choices: [],
                    correctAnswer: '',
                    sampleAnswer: '',
                },
                essay: {
                    choices: [],
                    correctAnswer: '',
                    sampleAnswer: '',
                },
            };

            return defaults[type] || defaults.multiple_choice;
        },
        onQuestionTypeChange(question) {
            const defaults = this.questionTypeDefaults(question.type);
            question.choices = JSON.parse(JSON.stringify(defaults.choices));
            question.correctAnswer = defaults.correctAnswer;
            question.sampleAnswer = defaults.sampleAnswer;
        },
        duplicateQuestion(index) {
            const copy = JSON.parse(JSON.stringify(this.questions[index]));
            copy.id = this.nextQuestionId();
            this.questions.splice(index + 1, 0, copy);
        },
        removeQuestion(index) {
            this.questions.splice(index, 1);
        },
    };
};

window.examTaking = function examTaking(config) {
    const offlineOnly = Boolean(config.offlineOnly);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const api = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
            ...options,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(data.message || 'Request failed.');
            error.response = response;
            error.data = data;
            throw error;
        }

        return data;
    };

    const initialAttempt = config.attemptState || null;
    const initialStatus = initialAttempt?.status || null;

    const resolvePhase = () => {
        if (offlineOnly) {
            return 'loading';
        }
        if (initialStatus === 'LOCKED_VIOLATION_LIMIT') {
            return 'locked';
        }
        if (initialStatus === 'IN_PROGRESS') {
            return 'active';
        }
        if (initialAttempt?.policy_accepted) {
            return 'starting';
        }
        return 'policy';
    };

    const resolveOfflinePhase = (pkg, localState, answers) => {
        if (localState?.phase === 'locked' || localState?.status === 'LOCKED_VIOLATION_LIMIT') {
            return 'locked';
        }
        if (localState?.phase === 'pending_submission' || pkg?.attempt_state?.pending_submission_at) {
            return 'pending_submission';
        }
        if (localState?.phase === 'active' || pkg?.attempt_state?.status === 'IN_PROGRESS') {
            return 'resume';
        }
        if (localState?.policy_accepted || pkg?.attempt_state?.policy_accepted) {
            return 'starting';
        }
        return 'policy';
    };

    return {
        title: config.title,
        total: config.total,
        remaining: config.remaining,
        maxWarnings: config.maxWarnings || 3,
        policyVersion: config.policyVersion,
        current: 1,
        phase: resolvePhase(),
        navigatorOpen: false,
        submitOpen: false,
        submitting: false,
        policyAccepted: false,
        policySubmitting: false,
        policyError: '',
        answers: {},
        flagged: {},
        questions: config.questions,
        resultUrl: config.resultUrl,
        urls: config.urls,
        monitoring: config.monitoring || {},
        timerId: null,
        saveStatus: '',
        saveTimer: null,
        warningCount: initialAttempt?.warning_count || 0,
        lockReason: initialAttempt?.lock_reason || '',
        attemptId: initialAttempt?.attempt_id || null,
        violationModalOpen: false,
        violationMessage: '',
        violationModalWarning: 0,
        violationRemainingText: '',
        monitoringBound: false,
        progressTimer: null,
        focusLossCooldown: false,
        lastClientEventId: null,
        requireFullscreen: config.monitoring?.requireFullscreen !== false,
        offline: config.offline || { supported: false },
        examinationId: config.examinationId,
        studentId: config.studentId || null,
        networkOnline: navigator.onLine,
        offlinePrepared: false,
        preparingOffline: false,
        prepProgress: null,
        prepError: '',
        prepStepsComplete: false,
        pendingSubmission: false,
        syncConflict: false,
        syncConflictMessage: '',
        lastSyncAt: null,
        pendingSyncCount: 0,
        timingToken: initialAttempt?.offline_timing_token || null,
        durationSeconds: initialAttempt?.duration_seconds || config.remaining || null,
        reactivatedAt: initialAttempt?.reactivated_at || null,
        timerState: null,
        syncUrl: config.urls?.sync || null,
        submitOfflineUrl: config.urls?.submitOffline || null,
        offlineOnly,
        subjectCode: config.subjectCode || '',
        lastSavedAt: '',
        bootstrapError: '',
        schedule: config.schedule || null,
        examEndedMessage: '',
        finalizationStatus: initialAttempt?.finalization_status || '',
        statePollTimer: null,

        async init() {
            if (this.offlineOnly) {
                this.bootstrapFromLocalPackage();
                return;
            }

            this.hydrateFromAttempt(initialAttempt);
            this.bindNetworkListeners();

            if (initialAttempt?.reactivated_at) {
                await this.applyReactivationTimerReset(initialAttempt);
                window.toast?.('Your examination has been reactivated by your instructor. The timer has been reset to the full duration.', 'success');
            } else {
                await this.restoreLocalState();
            }

            if (this.phase === 'starting') {
                this.beginExamination();
            } else if (this.phase === 'active') {
                this.onExamActive();
            }

            installSyncListeners(() => this.syncWhenOnline());
            navigator.serviceWorker?.addEventListener('message', (event) => {
                if (event.data?.type === 'TRIGGER_SYNC') {
                    this.syncWhenOnline();
                }
            });
        },

        bindNetworkListeners() {
            const update = () => {
                this.networkOnline = navigator.onLine;
                if (this.networkOnline) {
                    this.syncWhenOnline();
                }
                this.scheduleProgressReport();
            };
            window.addEventListener('online', update);
            window.addEventListener('offline', update);
        },

        connectionStatusForMonitoring() {
            if (!this.networkOnline) {
                return 'offline';
            }
            if (this.pendingSyncCount > 0) {
                return 'reconnecting';
            }

            return 'online';
        },

        scheduleProgressReport() {
            if (this.phase !== 'active' || !this.attemptId) {
                return;
            }

            clearTimeout(this.progressTimer);
            this.progressTimer = setTimeout(() => this.reportProgress(), 400);
        },

        async reportProgress() {
            if (this.phase !== 'active' || !this.attemptId) {
                return;
            }

            const payload = {
                current_question_index: this.current,
                connection_status: this.connectionStatusForMonitoring(),
            };

            try {
                if (this.networkOnline && !this.offlineOnly && this.urls.progress) {
                    await api(this.urls.progress, {
                        method: 'POST',
                        body: JSON.stringify(payload),
                    });
                } else {
                    await enqueueSyncEvent({
                        attemptId: this.attemptId,
                        eventType: 'progress_update',
                        payload,
                    });
                }
            } catch {
                /* progress reporting is best-effort */
            }
        },

        async bootstrapFromLocalPackage() {
            this.bindNetworkListeners();
            installSyncListeners(() => this.syncWhenOnline());

            try {
                const pkg = await loadPreparedExam(this.examinationId, this.studentId);
                if (!pkg || !isAuthorizationValid(pkg)) {
                    this.bootstrapError = 'This examination is not prepared for offline use or authorization has expired.';
                    this.phase = 'policy';
                    return;
                }

                this.offlinePrepared = true;
                this.title = pkg.title || this.title;
                this.subjectCode = pkg.subject_code || '';
                this.total = pkg.questions?.length || 0;
                this.questions = pkg.questions || [];
                this.remaining = pkg.duration_seconds || (pkg.duration_minutes * 60) || this.remaining;
                this.maxWarnings = pkg.max_warnings || this.maxWarnings;
                this.monitoring = pkg.monitoring || {};
                this.requireFullscreen = this.monitoring.requireFullscreen !== false;
                this.offline = pkg.offline || { supported: true };
                this.timingToken = pkg.attempt_state?.offline_timing_token || pkg.offline_timing_token || null;

                if (pkg.attempt_state) {
                    this.hydrateFromAttempt(pkg.attempt_state);
                }

                const localState = pkg.attempt_id ? await getAttemptState(pkg.attempt_id) : null;
                if (localState?.current) {
                    this.current = localState.current;
                }
                if (localState?.warning_count != null) {
                    this.warningCount = localState.warning_count;
                }
                if (localState?.remaining_seconds != null) {
                    this.remaining = localState.remaining_seconds;
                }
                if (localState?.policy_accepted) {
                    this.policyAccepted = true;
                }

                if (this.attemptId) {
                    const storedAnswers = await examOfflineDb.getAnswersForAttempt(this.attemptId);
                    storedAnswers.forEach((row) => {
                        const index = this.questions.findIndex((q) => q.id === row.question_id);
                        if (index >= 0) {
                            const number = index + 1;
                            this.answers[number] = row.answer;
                            if (row.is_flagged) {
                                this.flagged[number] = true;
                            }
                        }
                    });

                    const timer = await loadTimer(this.attemptId);
                    if (timer) {
                        this.timerState = timer;
                        this.remaining = tickTimer(timer);
                    }

                    const summary = await getQueueSummary();
                    this.pendingSyncCount = summary.pendingCount;
                    this.lastSyncAt = summary.lastSyncedAt;
                }

                this.phase = resolveOfflinePhase(pkg, localState, this.answers);
                this.lastSavedAt = localState?.last_saved_at
                    ? new Date(localState.last_saved_at).toLocaleTimeString()
                    : '';

                if (this.phase === 'starting') {
                    await this.beginExamination();
                }
            } catch (error) {
                this.bootstrapError = error.message || 'Unable to load offline examination.';
                this.phase = 'policy';
            }
        },

        async persistLocalAttemptState(extra = {}) {
            if (!this.attemptId) {
                return;
            }
            await persistExamProgress(this.attemptId, {
                examination_id: this.examinationId,
                student_id: this.studentId,
                phase: this.phase,
                status: this.phase === 'locked' ? 'LOCKED_VIOLATION_LIMIT' : (this.phase === 'active' ? 'IN_PROGRESS' : null),
                current: this.current,
                warning_count: this.warningCount,
                policy_accepted: this.policyAccepted || extra.policy_accepted,
                remaining_seconds: this.remaining,
                pending_submission: this.pendingSubmission,
                lock_reason: this.lockReason,
                last_saved_at: new Date().toISOString(),
                ...extra,
            });
            this.lastSavedAt = new Date().toLocaleTimeString();
        },

        async resumeExamination() {
            this.phase = 'active';
            await this.persistLocalAttemptState({ phase: 'active' });
            await this.onExamActive();
        },

        async applyReactivationTimerReset(attempt) {
            if (!attempt) {
                return;
            }

            this.reactivatedAt = attempt.reactivated_at || this.reactivatedAt;
            const fullSeconds = attempt.duration_seconds ?? attempt.remaining_seconds ?? this.durationSeconds ?? this.remaining;

            if (fullSeconds > 0) {
                this.remaining = fullSeconds;
                this.durationSeconds = fullSeconds;
            }

            if (attempt.offline_timing_token) {
                this.timingToken = attempt.offline_timing_token;
            }

            if (!this.attemptId) {
                return;
            }

            if (this.timingToken) {
                this.timerState = createTimerState({
                    attemptId: this.attemptId,
                    remainingSeconds: this.remaining,
                    timingToken: this.timingToken,
                });
                await persistTimer(this.timerState);
            } else {
                this.timerState = null;
                await examOfflineDb.remove(examOfflineDb.STORES.timerState, this.attemptId);
            }

            await persistExamProgress(this.attemptId, {
                examination_id: this.examinationId,
                student_id: this.studentId,
                phase: this.phase === 'locked' ? 'active' : this.phase,
                status: 'IN_PROGRESS',
                warning_count: this.warningCount,
                remaining_seconds: this.remaining,
                lock_reason: null,
            });
        },

        async restoreLocalState() {
            if (!this.examinationId || !this.studentId) {
                return;
            }
            try {
                const pkg = await loadPreparedExam(this.examinationId, this.studentId);
                if (pkg) {
                    this.offlinePrepared = true;
                    if (pkg.attempt_state?.pending_submission_at) {
                        this.pendingSubmission = true;
                        this.phase = 'pending_submission';
                    }
                }
                if (this.attemptId) {
                    const localState = await getAttemptState(this.attemptId);
                    const skipStaleLocalState = Boolean(this.reactivatedAt);

                    if (!skipStaleLocalState && (localState?.phase === 'locked' || localState?.status === 'LOCKED_VIOLATION_LIMIT')) {
                        this.phase = 'locked';
                        this.warningCount = localState.warning_count ?? this.warningCount;
                        this.lockReason = localState.lock_reason || this.lockReason;
                    } else if (!skipStaleLocalState && localState?.phase === 'pending_submission') {
                        this.phase = 'pending_submission';
                        this.pendingSubmission = true;
                    } else if (!skipStaleLocalState && localState?.phase === 'active' && this.phase !== 'active') {
                        this.phase = 'resume';
                        this.current = localState.current || this.current;
                        this.lastSavedAt = localState.last_saved_at
                            ? new Date(localState.last_saved_at).toLocaleTimeString()
                            : '';
                    }

                    if (!skipStaleLocalState) {
                        const timer = await loadTimer(this.attemptId);
                        if (timer && (this.phase === 'active' || this.phase === 'resume')) {
                            this.timerState = timer;
                            this.remaining = tickTimer(timer);
                        }
                    }
                    const summary = await getQueueSummary();
                    this.pendingSyncCount = summary.pendingCount;
                    this.lastSyncAt = summary.lastSyncedAt;
                }
            } catch {
                /* ignore restore errors */
            }
        },

        async onExamActive() {
            localStorage.setItem('exam-active-session', '1');
            this.startTimer();
            this.startStatePolling();
            this.bindMonitoring();
            if (this.attemptId && this.timingToken) {
                this.timerState = createTimerState({
                    attemptId: this.attemptId,
                    remainingSeconds: this.remaining,
                    timingToken: this.timingToken,
                });
                await persistTimer(this.timerState);
            }
        },

        get connectionLabel() {
            return this.networkOnline ? 'Online' : 'Offline Mode';
        },

        get connectionDetail() {
            if (this.networkOnline) {
                return this.pendingSyncCount > 0
                    ? 'Synchronizing pending changes...'
                    : 'Answers are being synchronized';
            }
            return 'Your answers are safely saved on this device and will synchronize when the connection returns.';
        },

        get deadlineLabel() {
            return this.schedule?.deadline_at_formatted || '';
        },

        get showDeadlineWarning() {
            return this.schedule?.deadline_policy === 'stop_all' && Boolean(this.schedule?.deadline_at);
        },

        get deadlineCountdown() {
            const seconds = this.schedule?.deadline_remaining_seconds;
            if (seconds == null) {
                return '';
            }
            const minutes = Math.floor(seconds / 60);
            const remaining = seconds % 60;
            return `${minutes}:${String(remaining).padStart(2, '0')}`;
        },

        startStatePolling() {
            if (!this.urls.state || this.offlineOnly) {
                return;
            }
            if (this.statePollTimer) {
                clearInterval(this.statePollTimer);
            }
            this.statePollTimer = setInterval(() => this.pollAttemptState(), 5000);
        },

        stopStatePolling() {
            if (this.statePollTimer) {
                clearInterval(this.statePollTimer);
                this.statePollTimer = null;
            }
        },

        async pollAttemptState() {
            if (this.phase !== 'active' || !this.networkOnline) {
                return;
            }
            try {
                const data = await api(this.urls.state);
                if (!data.attempt) {
                    return;
                }
                if (data.attempt.schedule) {
                    this.schedule = data.attempt.schedule;
                }
                if (data.attempt.examination_ended || ['SUBMITTED', 'AUTO_SUBMITTED', 'EXPIRED'].includes(data.attempt.status)) {
                    await this.handleExaminationEnded(data.attempt);
                }
            } catch {
                /* polling should not interrupt the exam */
            }
        },

        async handleExaminationEnded(attempt) {
            if (this.phase === 'ended') {
                return;
            }
            this.stopStatePolling();
            if (this.timerId) {
                clearInterval(this.timerId);
            }
            try {
                await this.persistAnswers();
            } catch {
                /* preserve whatever is already saved */
            }
            this.hydrateFromAttempt(attempt);
            this.examEndedMessage = attempt.examination_end_message
                || 'This examination has been ended by your instructor.';
            this.finalizationStatus = attempt.finalization_status || attempt.status;
            this.phase = 'ended';
        },

        get saveStatusLabel() {
            if (this.saveStatus === 'saving') {
                return 'Saving...';
            }
            if (this.saveStatus === 'saved' && this.networkOnline && this.pendingSyncCount === 0) {
                return '✓ Saved';
            }
            if (this.saveStatus === 'saved' || this.saveStatus === 'local') {
                return this.networkOnline && this.pendingSyncCount === 0 ? '✓ Saved' : '⏳ Pending Sync';
            }
            if (this.pendingSyncCount > 0) {
                return '⏳ Pending Sync';
            }
            return '';
        },

        async prepareOfflineMode() {
            if (!this.offline.supported || this.preparingOffline) {
                return;
            }
            this.prepError = '';
            this.preparingOffline = true;
            this.phase = 'preparing';

            try {
                await prepareExaminationOffline({
                    prepareUrl: this.urls.prepareOffline,
                    examinationId: this.examinationId,
                    studentId: this.studentId,
                    onProgress: (progress) => {
                        this.prepProgress = progress;
                    },
                });
                this.offlinePrepared = true;
                this.prepStepsComplete = true;
            } catch (error) {
                this.prepError = error.message || 'Preparation failed.';
                this.phase = 'policy';
            } finally {
                this.preparingOffline = false;
            }
        },

        async syncWhenOnline() {
            if (!this.networkOnline || !this.attemptId || !this.syncUrl) {
                return;
            }
            if (this.submitting || this.phase === 'submitting' || this.phase === 'submitted') {
                return;
            }
            const reachable = await verifyServerReachable();
            if (!reachable) {
                return;
            }
            try {
                const result = await syncAttempt(this.attemptId, this.syncUrl);
                if (result.conflicts?.length) {
                    this.syncConflict = true;
                    this.syncConflictMessage = 'Your examination data needs instructor or system review.';
                    return;
                }
                if (result.attempt) {
                    const previousReactivatedAt = this.reactivatedAt;
                    this.hydrateFromAttempt(result.attempt);
                    if (
                        result.attempt.reactivated_at
                        && result.attempt.reactivated_at !== previousReactivatedAt
                    ) {
                        await this.applyReactivationTimerReset(result.attempt);
                        if (this.phase === 'locked') {
                            this.phase = 'active';
                            this.lockReason = '';
                            await this.onExamActive();
                        } else if (this.phase === 'active') {
                            clearInterval(this.timerId);
                            await this.onExamActive();
                        }
                        window.toast?.('Your examination has been reactivated. The timer has been reset to the full duration.', 'success');
                    }
                    if (result.attempt.status === 'LOCKED_VIOLATION_LIMIT') {
                        this.phase = 'locked';
                        clearInterval(this.timerId);
                    }
                }
                const summary = await getQueueSummary();
                this.pendingSyncCount = summary.pendingCount;
                this.lastSyncAt = summary.lastSyncedAt;
                if (this.pendingSyncCount === 0 && this.networkOnline) {
                    this.saveStatus = 'saved';
                }
            } catch {
                /* sync will retry later */
            }
        },

        uuid() {
            return crypto.randomUUID();
        },

        get timerTone() {
            if (this.remaining <= 60) {
                return 'critical';
            }
            if (this.remaining <= 300) {
                return 'warning';
            }
            return 'normal';
        },

        get clock() {
            const minutes = Math.floor(this.remaining / 60);
            const seconds = this.remaining % 60;
            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        },

        get answeredCount() {
            return this.questions.filter((_, index) => this.isAnswered(index + 1)).length;
        },

        get unanswered() {
            return this.questions
                .map((question, index) => ({ ...question, number: index + 1 }))
                .filter((question) => !this.isAnswered(question.number));
        },

        questionType(question) {
            return question?.type || 'multiple_choice';
        },

        isSupportedQuestionType(question) {
            return ['multiple_choice', 'true_false', 'essay', 'short_answer', 'identification'].includes(this.questionType(question));
        },

        isAnswered(questionNumber) {
            const answer = this.answers[questionNumber];
            const question = this.questions[questionNumber - 1];
            if (!question) {
                return false;
            }

            const type = this.questionType(question);
            if (type === 'essay' || type === 'short_answer' || type === 'identification') {
                return typeof answer === 'string' && answer.trim() !== '';
            }

            return answer != null && answer !== '';
        },

        wordCount(value) {
            if (typeof value !== 'string' || value.trim() === '') {
                return 0;
            }

            return value.trim().split(/\s+/).length;
        },

        flushSave() {
            clearTimeout(this.saveTimer);
            if (this.phase === 'active') {
                void this.persistAnswers();
            }
        },

        hydrateFromAttempt(attempt) {
            if (!attempt) {
                return;
            }

            this.attemptId = attempt.attempt_id;
            this.warningCount = attempt.warning_count || 0;
            this.lockReason = attempt.lock_reason || '';
            this.remaining = attempt.remaining_seconds ?? this.remaining;
            this.durationSeconds = attempt.duration_seconds ?? this.durationSeconds ?? this.remaining;
            this.reactivatedAt = attempt.reactivated_at ?? this.reactivatedAt;
            this.timingToken = attempt.offline_timing_token || this.timingToken;
            if (attempt.pending_submission_at) {
                this.pendingSubmission = true;
            }
            if (attempt.schedule) {
                this.schedule = attempt.schedule;
            }
            if (attempt.examination_ended) {
                void this.handleExaminationEnded(attempt);
                return;
            }
            if (this.attemptId && this.urls.syncTemplate) {
                this.syncUrl = this.urls.syncTemplate.replace('__ATTEMPT__', this.attemptId);
                this.submitOfflineUrl = this.urls.submitOfflineTemplate?.replace('__ATTEMPT__', this.attemptId);
            }

            if (attempt.answers) {
                this.questions.forEach((question, index) => {
                    const saved = attempt.answers[question.id];
                    if (saved) {
                        const questionNumber = index + 1;
                        this.answers[questionNumber] = saved.answer;
                        if (saved.is_flagged) {
                            this.flagged[questionNumber] = true;
                        }
                    }
                });
            }
        },

        async acceptPolicy() {
            if (!this.policyAccepted || this.policySubmitting) {
                return;
            }

            this.policyError = '';
            this.policySubmitting = true;

            try {
                if (this.requireFullscreen && this.networkOnline) {
                    await this.requestFullscreen();
                }

                if (!this.networkOnline || this.offlineOnly) {
                    if (!this.offlinePrepared) {
                        this.policyError = 'This examination is not prepared for offline use.';
                        return;
                    }

                    await enqueueSyncEvent({
                        attemptId: this.attemptId,
                        eventType: 'policy_acceptance',
                        payload: {
                            policy_version: this.policyVersion,
                            accepted_at: new Date().toISOString(),
                        },
                    });
                    this.pendingSyncCount += 1;
                    this.policyAccepted = true;
                    await this.persistLocalAttemptState({ policy_accepted: true });
                    this.phase = 'starting';
                    await this.beginExamination();
                    return;
                }

                const data = await api(this.urls.acceptPolicy, { method: 'POST', body: '{}' });
                this.hydrateFromAttempt(data.attempt);

                if (this.offline.supported && (this.offline.require_preparation || this.offline.mode === 'required_preparation')) {
                    this.phase = 'preparing';
                    await this.prepareOfflineMode();
                    if (!this.offlinePrepared) {
                        return;
                    }
                }

                this.phase = 'starting';
                await this.beginExamination();
            } catch (error) {
                this.policyError = error.message || 'Unable to accept policy.';
            } finally {
                this.policySubmitting = false;
            }
        },

        async beginExamination() {
            if (this.offline.supported && this.offline.require_preparation && !this.offlinePrepared) {
                if (this.networkOnline && !this.offlineOnly) {
                    await this.prepareOfflineMode();
                    if (!this.offlinePrepared) {
                        return;
                    }
                } else {
                    this.policyError = 'This examination is not prepared for offline use.';
                    this.phase = 'policy';
                    return;
                }
            }

            const startOffline = () => this.beginExaminationOffline();

            if (!this.networkOnline || this.offlineOnly) {
                await startOffline();
                return;
            }

            try {
                const data = await api(this.urls.start, { method: 'POST', body: '{}' });
                this.hydrateFromAttempt(data.attempt);
                this.phase = 'active';
                await this.onExamActive();
            } catch (error) {
                if (!this.networkOnline && this.offlinePrepared) {
                    await startOffline();
                    return;
                }
                if (error.data?.attempt?.status === 'LOCKED_VIOLATION_LIMIT') {
                    this.phase = 'locked';
                    this.hydrateFromAttempt(error.data.attempt);
                    await this.persistLocalAttemptState({ phase: 'locked', status: 'LOCKED_VIOLATION_LIMIT' });
                    return;
                }
                this.policyError = error.message || 'Unable to start examination.';
                this.phase = 'policy';
            }
        },

        async beginExaminationOffline() {
            const pkg = await loadPreparedExam(this.examinationId, this.studentId);
            if (!pkg?.attempt_state?.attempt_id) {
                this.policyError = 'Offline authorization is missing. Prepare this examination while online.';
                this.phase = 'policy';
                return;
            }

            this.hydrateFromAttempt(pkg.attempt_state);

            if (pkg.attempt_state.status === 'LOCKED_VIOLATION_LIMIT') {
                this.phase = 'locked';
                await this.persistLocalAttemptState({ phase: 'locked', status: 'LOCKED_VIOLATION_LIMIT' });
                return;
            }

            await enqueueSyncEvent({
                attemptId: this.attemptId,
                eventType: 'examination_started',
                payload: {
                    started_at: new Date().toISOString(),
                    timing_token: this.timingToken,
                },
            });
            this.pendingSyncCount += 1;

            this.phase = 'active';
            await this.persistLocalAttemptState({ phase: 'active', status: 'IN_PROGRESS' });
            await this.onExamActive();
        },

        async requestFullscreen() {
            const element = document.documentElement;
            if (!element.requestFullscreen) {
                return;
            }
            if (document.fullscreenElement) {
                return;
            }
            await element.requestFullscreen();
        },

        startTimer() {
            if (this.timerId) {
                clearInterval(this.timerId);
            }

            this.timerId = setInterval(async () => {
                if (this.phase !== 'active') {
                    return;
                }
                if (this.timerState) {
                    this.remaining = tickTimer(this.timerState);
                    await persistTimer(this.timerState);
                } else if (this.remaining > 0) {
                    this.remaining -= 1;
                }
                if (this.remaining === 0) {
                    clearInterval(this.timerId);
                    this.submitExam(true);
                }
            }, 1000);
        },

        bindMonitoring() {
            if (this.monitoringBound) {
                return;
            }
            this.monitoringBound = true;

            const onFocusLoss = () => {
                if (this.phase !== 'active' || this.violationModalOpen || this.focusLossCooldown) {
                    return;
                }
                this.focusLossCooldown = true;
                setTimeout(() => {
                    this.focusLossCooldown = false;
                }, 3000);
                this.reportViolation('TAB_OR_WINDOW_SWITCH');
            };

            if (this.monitoring.detectTabSwitch !== false) {
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        onFocusLoss();
                    }
                });
                window.addEventListener('blur', onFocusLoss);
            }

            if (this.monitoring.disableCopyPaste !== false) {
                document.addEventListener('copy', (event) => {
                    if (this.phase !== 'active') {
                        return;
                    }
                    event.preventDefault();
                    this.reportViolation('COPY_ATTEMPT');
                });
                document.addEventListener('cut', (event) => {
                    if (this.phase !== 'active') {
                        return;
                    }
                    event.preventDefault();
                    this.reportViolation('CUT_ATTEMPT');
                });
            }

            if (this.monitoring.disableRightClick !== false) {
                document.addEventListener('contextmenu', (event) => {
                    if (this.phase !== 'active') {
                        return;
                    }
                    if (event.target.closest('.exam-protected-content')) {
                        event.preventDefault();
                        this.reportViolation('CONTEXT_MENU');
                    }
                });
            }

            if (this.requireFullscreen) {
                document.addEventListener('fullscreenchange', () => {
                    if (this.phase !== 'active' || document.fullscreenElement) {
                        return;
                    }
                    this.reportViolation('FULLSCREEN_EXIT');
                });
            }

            window.addEventListener('beforeunload', (event) => {
                if (this.phase !== 'active' || this.submitting) {
                    return;
                }
                this.queueViolationBeacon('PAGE_LEAVE');
                event.preventDefault();
                event.returnValue = '';
            });

            window.addEventListener('pagehide', () => {
                if (this.phase === 'active' && !this.submitting) {
                    this.queueViolationBeacon('PAGE_LEAVE');
                }
            });
        },

        queueViolationBeacon(type) {
            if (!this.urls.violations) {
                return;
            }
            fetch(this.urls.violations, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    violation_type: type,
                    client_event_id: this.makeEventId(type),
                    pending_answers: this.buildAnswerPayload(),
                }),
            }).catch(() => {});
        },

        makeEventId(type) {
            return `${type}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        },

        buildAnswerPayload() {
            return this.questions
                .map((question, index) => ({
                    question_id: question.id,
                    answer: this.answers[index + 1] ?? null,
                    is_flagged: !!this.flagged[index + 1],
                }))
                .filter((item) => item.question_id);
        },

        async reportViolation(type) {
            if (this.phase !== 'active') {
                return;
            }

            const clientEventId = this.makeEventId(type);

            if (!this.networkOnline) {
                await this.saveAnswersLocally();
                await enqueueSyncEvent({
                    attemptId: this.attemptId,
                    eventType: 'violation',
                    payload: {
                        violation_type: type,
                        client_event_id: clientEventId,
                        pending_answers: this.buildAnswerPayload(),
                    },
                });
                this.pendingSyncCount += 1;
                this.applyLocalViolation();
                return;
            }

            try {
                const data = await api(this.urls.violations, {
                    method: 'POST',
                    body: JSON.stringify({
                        violation_type: type,
                        client_event_id: clientEventId,
                        pending_answers: this.buildAnswerPayload(),
                    }),
                });

                if (data.duplicate) {
                    return;
                }

                this.warningCount = data.warning_count;
                this.saveStatus = 'saved';

                if (data.locked) {
                    this.phase = 'locked';
                    this.lockReason = data.attempt?.lock_reason || '';
                    clearInterval(this.timerId);
                    return;
                }

                if (data.recorded) {
                    this.violationMessage = data.message || 'A policy violation was detected.';
                    this.violationModalWarning = data.warning_count;
                    const remaining = this.maxWarnings - data.warning_count;
                    this.violationRemainingText = remaining > 0
                        ? `${remaining} more violation${remaining === 1 ? '' : 's'} will result in your examination being automatically locked.`
                        : 'Your examination will be locked on the next violation.';
                    this.violationModalOpen = true;
                }
            } catch (error) {
                if (error.response?.status === 423) {
                    this.phase = 'locked';
                    this.hydrateFromAttempt(error.data?.attempt);
                    clearInterval(this.timerId);
                }
            }
        },

        acknowledgeViolation() {
            this.violationModalOpen = false;
        },

        applyLocalViolation() {
            this.warningCount = Math.min(this.maxWarnings, this.warningCount + 1);
            this.saveStatus = 'local';

            if (this.warningCount >= this.maxWarnings) {
                this.phase = 'locked';
                this.lockReason = 'Maximum violation warnings reached offline.';
                clearInterval(this.timerId);
                this.persistLocalAttemptState({
                    phase: 'locked',
                    status: 'LOCKED_VIOLATION_LIMIT',
                    lock_reason: this.lockReason,
                });
                this.saveAnswersLocally();
                return;
            }

            this.violationMessage = 'A policy violation was detected.';
            this.violationModalWarning = this.warningCount;
            const remaining = this.maxWarnings - this.warningCount;
            this.violationRemainingText = remaining > 0
                ? `${remaining} more violation${remaining === 1 ? '' : 's'} will result in your examination being automatically locked.`
                : 'Your examination will be locked on the next violation.';
            this.violationModalOpen = true;
            this.persistLocalAttemptState();
        },

        async saveAnswersLocally() {
            if (!this.attemptId) {
                return;
            }
            for (const [number, answer] of Object.entries(this.answers)) {
                const question = this.questions[Number(number) - 1];
                if (!question) {
                    continue;
                }
                const revision = await examOfflineDb.saveAnswer(
                    this.attemptId,
                    question.id,
                    answer,
                    !!this.flagged[number],
                    'pending',
                );
                await enqueueSyncEvent({
                    attemptId: this.attemptId,
                    eventType: 'answer_updated',
                    payload: {
                        question_id: question.id,
                        answer,
                        is_flagged: !!this.flagged[number],
                        client_revision: String(revision),
                    },
                });
            }
            this.pendingSyncCount = (await getQueueSummary()).pendingCount;
        },

        scheduleSave(isText = false) {
            if (this.phase !== 'active') {
                return;
            }
            this.saveStatus = 'saving';
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.persistAnswers(), isText ? 750 : 500);
        },

        async persistAnswers() {
            if (this.phase !== 'active') {
                return;
            }

            try {
                await this.saveAnswersLocally();
                this.saveStatus = 'local';
                await this.persistLocalAttemptState();

                if (this.networkOnline && !this.offlineOnly) {
                    await api(this.urls.saveAnswers, {
                        method: 'POST',
                        body: JSON.stringify({ answers: this.buildAnswerPayload() }),
                    });
                    this.saveStatus = 'saved';
                    await this.syncWhenOnline();
                }
                this.scheduleProgressReport();
            } catch {
                this.saveStatus = 'local';
            }
        },

        select(choice) {
            this.answers[this.current] = choice;
            this.scheduleSave();
        },

        setTextAnswer(value) {
            this.answers[this.current] = value;
            this.scheduleSave(true);
        },

        flag() {
            this.flagged[this.current] = !this.flagged[this.current];
            this.scheduleSave();
        },

        go(number) {
            this.flushSave();
            this.current = number;
            this.navigatorOpen = false;
            this.scheduleProgressReport();
        },

        prev() {
            this.flushSave();
            this.current = Math.max(1, this.current - 1);
            this.scheduleProgressReport();
        },

        next() {
            this.flushSave();
            this.current = Math.min(this.total, this.current + 1);
            this.scheduleProgressReport();
        },

        async submitExam(auto = false) {
            if (this.submitting || (this.phase !== 'active' && this.phase !== 'pending_submission')) {
                return;
            }
            this.submitting = true;
            this.submitOpen = false;
            this.phase = 'submitting';
            clearInterval(this.timerId);
            localStorage.removeItem('exam-active-session');

            await this.saveAnswersLocally();

            if (!this.networkOnline || this.offlineOnly) {
                await enqueueSyncEvent({
                    attemptId: this.attemptId,
                    eventType: 'examination_submission',
                    payload: {
                        auto,
                        answers: this.buildAnswerPayload(),
                        timing_token: this.timingToken,
                    },
                });
                this.pendingSubmission = true;
                this.phase = 'pending_submission';
                await this.persistLocalAttemptState({
                    phase: 'pending_submission',
                    pending_submission: true,
                });
                this.submitting = false;
                return;
            }

            try {
                const data = await api(this.urls.submit, {
                    method: 'POST',
                    body: JSON.stringify({
                        auto,
                        answers: this.buildAnswerPayload(),
                    }),
                });
                this.phase = 'submitted';
                if (this.examinationId && this.studentId) {
                    await examOfflineDb.clearExamData(this.examinationId, this.studentId, this.attemptId);
                }
                this.pendingSyncCount = 0;
                window.location.href = data.result_url || this.resultUrl;
            } catch (error) {
                if (!this.networkOnline || error.message?.includes('network')) {
                    this.pendingSubmission = true;
                    this.phase = 'pending_submission';
                    this.submitting = false;
                    return;
                }
                this.phase = 'active';
                this.submitting = false;
                localStorage.setItem('exam-active-session', '1');
                this.startTimer();
                window.toast?.(error.message || 'Unable to submit examination.', 'error');
            }
        },

        async retrySync() {
            this.syncConflict = false;
            await this.syncWhenOnline();
        },
    };
};

window.offlineApp = function offlineApp(config) {
    return {
        userName: config.userName || '',
        studentId: config.studentId,
        bootstrapUrl: config.bootstrapUrl,
        syncStatusUrl: config.syncStatusUrl,
        examinationsUrl: config.examinationsUrl,
        syncUrlTemplate: config.syncUrlTemplate || '',
        unlocked: false,
        pinInput: '',
        pinError: '',
        online: navigator.onLine,
        exams: [],
        loading: true,
        syncing: false,
        syncComplete: false,

        async init() {
            window.addEventListener('online', () => this.handleOnline());
            window.addEventListener('offline', () => { this.online = false; });

            await this.ensureOfflineSession();

            const pinRequired = await isPinConfigured();
            if (pinRequired) {
                this.unlocked = await isUnlocked();
            } else {
                this.unlocked = true;
            }

            if (this.unlocked) {
                await this.loadExams();
            } else {
                this.loading = false;
            }

            installSyncListeners(() => this.handleOnline());
        },

        async ensureOfflineSession() {
            const valid = await isOfflineSessionValid();
            if (valid || !navigator.onLine) {
                return;
            }
            try {
                await bootstrapOfflineAccess(
                    this.bootstrapUrl,
                    getDeviceIdentifier(),
                    getDeviceName(),
                );
            } catch {
                /* session refresh is best-effort when online */
            }
        },

        async submitPin() {
            const ok = await verifyPin(this.pinInput);
            if (ok) {
                this.unlocked = true;
                this.pinError = '';
                this.pinInput = '';
                await this.loadExams();
            } else {
                this.pinError = 'Incorrect PIN. Please try again.';
            }
        },

        async loadExams() {
            this.loading = true;
            try {
                this.exams = await listCatalogEntries(this.studentId);
            } catch {
                this.exams = [];
            } finally {
                this.loading = false;
            }
        },

        statusClass(status) {
            const map = {
                [EXAM_STATUS.READY]: 'text-success-ink',
                [EXAM_STATUS.IN_PROGRESS]: 'text-brand',
                [EXAM_STATUS.SUBMISSION_PENDING]: 'text-warning-ink',
                [EXAM_STATUS.LOCKED]: 'text-danger-ink',
                [EXAM_STATUS.NOT_PREPARED]: 'text-muted',
                [EXAM_STATUS.INTERNET_REQUIRED]: 'text-muted',
            };
            return map[status] || 'text-muted';
        },

        startExam(exam) {
            window.location.href = exam.take_url;
        },

        resumeExam(exam) {
            window.location.href = exam.take_url;
        },

        async handleOnline() {
            this.online = true;
            if (this.syncing) {
                return;
            }
            this.syncing = true;
            this.syncComplete = false;

            try {
                await this.ensureOfflineSession();
                if (this.syncUrlTemplate) {
                    const { syncAllPending } = await import('./offline/sync-engine.js');
                    await syncAllPending(this.syncUrlTemplate);
                }
                this.syncComplete = true;
                if (this.unlocked) {
                    await this.loadExams();
                }
            } catch {
                /* sync retries automatically */
            } finally {
                this.syncing = false;
            }
        },
    };
};

window.confirmLogoutIfPendingSync = async function confirmLogoutIfPendingSync() {
    try {
        const pending = await examOfflineDb.hasPendingSync();
        if (!pending) {
            return true;
        }
        return window.confirm(
            'You have examination data waiting to synchronize. Logging out may affect access to this data on this device.\n\nLog out anyway?',
        );
    } catch {
        return true;
    }
};

window.examSyncStatus = function examSyncStatus(config = {}) {
    return {
        online: navigator.onLine,
        pendingCount: 0,
        lastSyncedAt: '',
        queue: [],
        loading: true,
        syncing: false,
        error: '',
        syncUrlTemplate: config.syncUrlTemplate || '',

        init() {
            window.addEventListener('online', () => { this.online = true; });
            window.addEventListener('offline', () => { this.online = false; });
            this.refresh();
        },

        async refresh() {
            this.loading = true;
            try {
                const { getQueueSummary } = await import('./offline/sync-queue.js');
                const summary = await getQueueSummary();
                this.queue = summary.pending;
                this.pendingCount = summary.pendingCount;
                this.lastSyncedAt = summary.lastSyncedAt
                    ? new Date(summary.lastSyncedAt).toLocaleTimeString()
                    : '';
            } catch {
                this.error = 'Unable to read local sync queue.';
            } finally {
                this.loading = false;
            }
        },

        formatEvent(item) {
            const type = item.event_type || 'event';
            if (type.includes('answer')) {
                const q = item.payload?.question_id;
                return `Answer — Question ${q || '?'}`;
            }
            if (type === 'violation') {
                return 'Violation Event';
            }
            if (type === 'examination_submission') {
                return 'Exam Submission';
            }
            return type.replace(/_/g, ' ');
        },

        async retrySync() {
            if (!this.online || this.syncing) {
                return;
            }
            this.syncing = true;
            this.error = '';
            try {
                const { syncAllPending } = await import('./offline/sync-engine.js');
                await syncAllPending(this.syncUrlTemplate);
                await this.refresh();
            } catch {
                this.error = 'Synchronization failed. Please try again.';
            } finally {
                this.syncing = false;
            }
        },
    };
};

window.examMonitoring = function examMonitoring(config) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const api = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
            ...options,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const firstFieldError = data.errors
                ? Object.values(data.errors).flat().find(Boolean)
                : null;
            const error = new Error(firstFieldError || data.message || 'Request failed.');
            error.response = response;
            error.data = data;
            throw error;
        }

        return data;
    };

    return {
        selectedExam: config.exam || null,
        selectedExamId: config.exam?.id || null,
        backUrl: config.backUrl || null,
        examination: null,
        summary: {},
        students: [],
        activities: [],
        loading: false,
        polling: false,
        lastUpdated: '',
        lastSyncAt: null,
        pollTimer: null,
        searchQuery: '',
        statusFilter: 'all',
        sortBy: 'priority',
        showAllActivity: false,
        historyOpen: false,
        historyLoading: false,
        historyItems: [],
        historyMeta: '',
        historyLocked: false,
        historyOfflineNote: '',
        drawerOpen: false,
        drawerLoading: false,
        drawerRow: null,
        reactivateOpen: false,
        reactivateRow: null,
        reactivationReason: '',
        warningMode: 'reset',
        manualWarningCount: 0,
        reactivateSubmitting: false,
        reactivateError: '',
        maxWarnings: config.maxWarnings || 3,
        violationsUrlTemplate: config.violationsUrl,
        reactivateUrlTemplate: config.reactivateUrl,
        attemptUrlTemplate: config.attemptUrl,
        notifications: [],
        knownStates: {},
        control: null,
        endExamOpen: false,
        endPolicy: 'auto_submit',
        endReason: '',
        endExamError: '',
        endExamSubmitting: false,
        endOfflineStudents: 0,
        extendDeadlineOpen: false,
        newDeadlineDate: '',
        newDeadlineTime: '',
        extendReason: '',
        extendDeadlineError: '',
        extendDeadlineSubmitting: false,
        reactivateExamOpen: false,
        reactivateExamReason: '',
        reactivateDeadlineDate: '',
        reactivateDeadlineTime: '',
        reactivateExamError: '',
        reactivateExamSubmitting: false,

        get reactivateExamNeedsDeadline() {
            if (this.control?.is_ended && this.examination?.deadline_at) {
                return (this.examination?.deadline_remaining_seconds ?? 0) <= 0;
            }

            return false;
        },

        get deadlineCountdownLabel() {
            const seconds = this.examination?.deadline_remaining_seconds;
            if (seconds == null) {
                return '—';
            }
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            if (hours > 0) {
                return `${hours}h ${minutes}m remaining`;
            }
            return `${minutes}m remaining`;
        },

        init() {
            if (!this.selectedExam?.dataUrl) {
                return;
            }

            this.refresh(false, true);
            this.loadControl();
            this.startPolling();
        },

        async loadControl() {
            if (!this.selectedExam?.controlUrl) {
                return;
            }
            try {
                const data = await api(this.selectedExam.controlUrl);
                this.control = data.control || null;
            } catch {
                /* control panel is optional */
            }
        },

        openEndExamination() {
            this.endPolicy = 'auto_submit';
            this.endReason = '';
            this.endExamError = '';
            this.endOfflineStudents = this.summary.offline || 0;
            this.endExamOpen = true;
        },

        async submitEndExamination() {
            if (!this.selectedExam?.endUrl || this.endExamSubmitting) {
                return;
            }
            this.endExamSubmitting = true;
            this.endExamError = '';
            try {
                const data = await api(this.selectedExam.endUrl, {
                    method: 'POST',
                    body: JSON.stringify({
                        end_policy: this.endPolicy,
                        reason: this.endReason || null,
                    }),
                });
                this.endExamOpen = false;
                this.endOfflineStudents = data.offline_students || 0;
                if (data.activity) {
                    this.activities = [data.activity, ...(this.activities || [])];
                }
                window.appToast?.(data.message || 'Examination ended.');
                await this.refresh(false, true);
                await this.loadControl();
            } catch (error) {
                this.endExamError = error.message || 'Unable to end the examination.';
            } finally {
                this.endExamSubmitting = false;
            }
        },

        openExtendDeadline() {
            this.newDeadlineDate = '';
            this.newDeadlineTime = '';
            this.extendReason = '';
            this.extendDeadlineError = '';
            this.extendDeadlineOpen = true;
        },

        openReactivateExamination() {
            this.reactivateExamReason = '';
            this.reactivateDeadlineDate = '';
            this.reactivateDeadlineTime = '';
            this.reactivateExamError = '';
            this.reactivateExamOpen = true;
        },

        async submitReactivateExamination() {
            if (!this.selectedExam?.reactivateExaminationUrl || this.reactivateExamSubmitting) {
                return;
            }

            if (this.reactivateExamNeedsDeadline && (!this.reactivateDeadlineDate || !this.reactivateDeadlineTime)) {
                this.reactivateExamError = 'Please set a new deadline before reactivating.';
                return;
            }

            this.reactivateExamSubmitting = true;
            this.reactivateExamError = '';

            try {
                const payload = {
                    reason: this.reactivateExamReason || null,
                };

                if (this.reactivateDeadlineDate) {
                    payload.deadline_date = this.reactivateDeadlineDate;
                    payload.deadline_time = this.reactivateDeadlineTime || '23:59';
                }

                const data = await api(this.selectedExam.reactivateExaminationUrl, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                this.reactivateExamOpen = false;

                if (data.activity) {
                    this.activities = [data.activity, ...(this.activities || [])];
                }

                window.appToast?.(data.message || 'Examination reactivated.');
                await this.refresh(false, true);
                await this.loadControl();
            } catch (error) {
                this.reactivateExamError = error.message || 'Unable to reactivate the examination.';
            } finally {
                this.reactivateExamSubmitting = false;
            }
        },

        async submitExtendDeadline() {
            if (!this.selectedExam?.extendDeadlineUrl || this.extendDeadlineSubmitting) {
                return;
            }
            this.extendDeadlineSubmitting = true;
            this.extendDeadlineError = '';
            try {
                const data = await api(this.selectedExam.extendDeadlineUrl, {
                    method: 'POST',
                    body: JSON.stringify({
                        deadline_date: this.newDeadlineDate,
                        deadline_time: this.newDeadlineTime,
                        reason: this.extendReason || null,
                    }),
                });
                this.extendDeadlineOpen = false;
                window.appToast?.(data.message || 'Deadline updated.');
                await this.refresh(false, true);
                await this.loadControl();
            } catch (error) {
                this.extendDeadlineError = error.message || 'Unable to update the deadline.';
            } finally {
                this.extendDeadlineSubmitting = false;
            }
        },

        startPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }

            this.pollTimer = setInterval(() => this.refresh(true), 5000);
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        get filteredStudents() {
            let rows = [...this.students];

            if (this.statusFilter !== 'all') {
                rows = rows.filter((row) => row.status_filter === this.statusFilter);
            }

            const query = (this.searchQuery || '').trim().toLowerCase();
            if (query) {
                rows = rows.filter((row) =>
                    (row.student_name || '').toLowerCase().includes(query)
                    || (row.student_id || '').toLowerCase().includes(query),
                );
            }

            rows.sort((a, b) => {
                if (this.sortBy === 'priority') {
                    return (a.priority || 99) - (b.priority || 99);
                }
                if (this.sortBy === 'progress') {
                    return (b.progress_percent || 0) - (a.progress_percent || 0);
                }
                if (this.sortBy === 'remaining') {
                    return (a.remaining_seconds ?? 999999) - (b.remaining_seconds ?? 999999);
                }
                if (this.sortBy === 'warnings') {
                    return (b.warning_count || 0) - (a.warning_count || 0);
                }
                if (this.sortBy === 'activity') {
                    return String(b.last_activity_at || '').localeCompare(String(a.last_activity_at || ''));
                }

                return 0;
            });

            return rows;
        },

        get visibleActivities() {
            const items = this.activities || [];
            return this.showAllActivity ? items : items.slice(0, 12);
        },

        async refresh(silent = false, full = false) {
            if (!this.selectedExam?.dataUrl) {
                return;
            }

            if (!silent) {
                this.loading = true;
            }
            this.polling = silent;

            const url = new URL(this.selectedExam.dataUrl, window.location.origin);
            if (!full && this.lastSyncAt) {
                url.searchParams.set('since', this.lastSyncAt);
            }

            try {
                const data = await api(url.toString());
                this.examination = data.examination || null;
                this.summary = data.summary || {};
                this.activities = data.activities || [];

                if (full || !this.lastSyncAt) {
                    this.students = data.students || [];
                } else {
                    this.mergeStudents(data.students || []);
                }

                this.detectNotifications(data.students || this.students);
                this.lastSyncAt = data.server_time || new Date().toISOString();
                this.lastUpdated = new Date().toLocaleTimeString();

                if (this.drawerOpen && this.drawerRow?.attempt_id) {
                    const updated = this.students.find((row) => row.attempt_id === this.drawerRow.attempt_id);
                    if (updated) {
                        this.drawerRow = updated;
                    }
                }
            } catch (error) {
                if (!silent) {
                    window.toast?.(error.message, 'error');
                }
            } finally {
                this.loading = false;
                this.polling = false;
            }
        },

        mergeStudents(updates) {
            if (!updates.length) {
                return;
            }

            const map = new Map(this.students.map((row) => [row.student_db_id, row]));
            updates.forEach((row) => {
                map.set(row.student_db_id, row);
            });
            this.students = Array.from(map.values());
        },

        detectNotifications(rows) {
            rows.forEach((row) => {
                const key = row.attempt_id ? `attempt-${row.attempt_id}` : `student-${row.student_db_id}`;
                const previous = this.knownStates[key];
                const current = {
                    monitoring_status: row.monitoring_status,
                    warning_count: row.warning_count,
                    connection_status: row.connection_status,
                };

                if (previous) {
                    if (row.warning_count >= 2 && row.warning_count > previous.warning_count) {
                        this.pushNotification(
                            `${row.student_name} reached Warning ${row.warning_count}.`,
                            row.warning_count >= row.max_warnings ? 'critical' : 'warning',
                        );
                    }
                    if (row.monitoring_status === 'LOCKED' && previous.monitoring_status !== 'LOCKED') {
                        this.pushNotification(`${row.student_name}'s examination was locked.`, 'critical');
                    }
                    if (row.monitoring_status === 'OFFLINE' && previous.connection_status === 'online') {
                        this.pushNotification(`${row.student_name} went offline.`, 'warning');
                    }
                    if (row.monitoring_status === 'SUBMITTED' && previous.monitoring_status !== 'SUBMITTED') {
                        this.pushNotification(`${row.student_name} submitted the examination.`, 'info');
                    }
                    if (row.monitoring_status === 'PENDING_SUBMISSION' && previous.monitoring_status !== 'PENDING_SUBMISSION') {
                        this.pushNotification(`${row.student_name} has a pending offline submission.`, 'warning');
                    }
                }

                this.knownStates[key] = current;
            });
        },

        pushNotification(message, severity = 'info') {
            const id = `${Date.now()}-${Math.random()}`;
            this.notifications.push({ id, message, severity });
            setTimeout(() => {
                this.notifications = this.notifications.filter((note) => note.id !== id);
            }, severity === 'critical' ? 8000 : 5000);
        },

        statusBadgeClass(status) {
            return {
                'bg-brand-soft text-brand': ['TAKING_EXAM', 'REACTIVATED'].includes(status),
                'bg-danger-soft text-danger-ink': status === 'LOCKED',
                'bg-success-soft text-success-ink': status === 'SUBMITTED',
                'bg-warning-soft text-warning-ink': ['OFFLINE', 'PENDING_SUBMISSION', 'PAUSED'].includes(status),
                'bg-canvas text-muted': ['NOT_STARTED', 'PREPARING', 'EXPIRED'].includes(status),
            };
        },

        connectionClass(status) {
            return {
                'text-success-ink': status === 'online',
                'text-warning-ink': status === 'reconnecting',
                'text-muted': status === 'offline' || status === 'unknown',
            };
        },

        connectionDotClass(status) {
            return {
                'bg-success-ink': status === 'online',
                'bg-warning-ink animate-pulse': status === 'reconnecting',
                'bg-muted': status === 'offline' || status === 'unknown',
            };
        },

        attemptUrl(template, attemptId) {
            return template.replace('__ATTEMPT__', attemptId);
        },

        async openStudent(row) {
            this.drawerOpen = true;
            this.drawerRow = row;
            this.drawerLoading = false;

            if (!row.attempt_id || !this.attemptUrlTemplate) {
                return;
            }

            this.drawerLoading = true;
            try {
                const data = await api(this.attemptUrl(this.attemptUrlTemplate, row.attempt_id));
                this.drawerRow = data.student || row;
            } catch {
                /* keep row snapshot */
            } finally {
                this.drawerLoading = false;
            }
        },

        async viewViolations(row) {
            if (!row.attempt_id) {
                window.toast?.('This student has no examination attempt yet.', 'error');
                return;
            }

            this.historyOpen = true;
            this.historyLoading = true;
            this.historyItems = [];
            this.historyLocked = row.monitoring_status === 'LOCKED';
            this.historyOfflineNote = row.connection_status === 'offline'
                ? 'Offline violations shown reflect the last synchronized data. Additional offline events may still be pending.'
                : '';
            this.historyMeta = `${row.student_name || 'Student'} · ${row.warning_count}/${row.max_warnings} warnings`;

            try {
                const data = await api(this.attemptUrl(this.violationsUrlTemplate, row.attempt_id));
                this.historyItems = data.violations || [];
                this.historyLocked = data.status === 'LOCKED_VIOLATION_LIMIT' || this.historyLocked;
            } catch (error) {
                window.toast?.(error.message, 'error');
            } finally {
                this.historyLoading = false;
            }
        },

        openReactivate(row) {
            this.reactivateRow = row;
            this.reactivationReason = '';
            this.warningMode = 'reset';
            this.manualWarningCount = 0;
            this.reactivateError = '';
            this.reactivateOpen = true;
        },

        async submitReactivate() {
            if (!this.reactivateRow?.attempt_id || this.reactivateSubmitting) {
                return;
            }

            const reason = (this.reactivationReason || '').trim();
            if (!reason) {
                this.reactivateError = 'A reactivation reason is required.';
                return;
            }

            if (this.warningMode === 'manual' && (this.manualWarningCount === null || this.manualWarningCount === '')) {
                this.reactivateError = 'Please enter the warning count to apply after reactivation.';
                return;
            }

            this.reactivateError = '';
            this.reactivateSubmitting = true;

            try {
                const data = await api(this.attemptUrl(this.reactivateUrlTemplate, this.reactivateRow.attempt_id), {
                    method: 'POST',
                    body: JSON.stringify({
                        reactivation_reason: reason,
                        warning_mode: this.warningMode,
                        manual_warning_count: this.warningMode === 'manual'
                            ? Number(this.manualWarningCount)
                            : null,
                    }),
                });

                this.reactivateOpen = false;
                const pending = data.attempt?.reactivation_pending;
                window.toast?.(
                    pending
                        ? 'Reactivation authorized. It will apply when the student reconnects.'
                        : 'Examination attempt reactivated.',
                    'success',
                );
                await this.refresh(false, true);
            } catch (error) {
                this.reactivateError = error.message;
            } finally {
                this.reactivateSubmitting = false;
            }
        },
    };
};

window.toast = (message, type = 'success') => window.appToast(message, type);

window.studentRegistrationWizard = function studentRegistrationWizard(config = {}) {
    const old = config.old || {};
    const serverErrors = config.errors || {};

    return {
        step: 1,
        submitting: false,
        showPassword: false,
        showConfirmPassword: false,
        departments: config.departments || [],
        programs: [],
        yearLevels: [],
        sections: [],
        recommendedSubjects: [],
        otherSubjects: [],
        offeringCatalog: {},
        programsLoading: false,
        yearLevelsLoading: false,
        sectionsLoading: false,
        subjectsLoading: false,
        subjectSearch: '',
        browseAllSubjects: false,
        errors: Object.fromEntries(
            Object.entries(serverErrors).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value])
        ),
        steps: [
            { id: 1, label: 'Personal' },
            { id: 2, label: 'Academic' },
            { id: 3, label: 'Section' },
            { id: 4, label: 'Subjects' },
            { id: 5, label: 'Review' },
        ],
        form: {
            first_name: old.first_name || '',
            middle_name: old.middle_name || '',
            last_name: old.last_name || '',
            suffix: old.suffix || '',
            sex: old.sex || '',
            date_of_birth: old.date_of_birth || '',
            phone: old.phone || '',
            email: old.email || '',
            home_address: old.home_address || '',
            student_id: old.student_id || '',
            department_id: old.department_id ? String(old.department_id) : '',
            program_id: old.program_id ? String(old.program_id) : '',
            year_level_id: old.year_level_id ? String(old.year_level_id) : '',
            section_id: old.section_id ? String(old.section_id) : '',
            subject_offering_ids: Array.isArray(old.subject_offering_ids) ? old.subject_offering_ids.map(String) : [],
            password: '',
            password_confirmation: '',
        },
        programsUrl: config.programsUrl || '',
        yearLevelsUrl: config.yearLevelsUrl || '',
        sectionsUrl: config.sectionsUrl || '',
        subjectsUrl: config.subjectsUrl || '',
        today: new Date().toISOString().slice(0, 10),
        init() {
            if (Object.keys(serverErrors).length > 0) {
                this.step = this.inferStepFromErrors();
            }

            if (this.form.department_id) {
                this.fetchPrograms(false).then(() => {
                    if (this.form.program_id) {
                        this.fetchYearLevels(false).then(() => {
                            if (this.form.year_level_id) {
                                this.fetchSections(false).then(() => {
                                    if (this.form.section_id) {
                                        this.fetchSubjects();
                                    }
                                });
                            }
                        });
                    }
                });
            }
        },
        inferStepFromErrors() {
            const personal = ['first_name', 'last_name', 'phone', 'email'];
            const academic = ['student_id', 'department_id', 'program_id', 'year_level_id'];
            const section = ['section_id'];
            const subjects = ['subject_offering_ids'];
            const account = ['password', 'password_confirmation'];

            if (account.some((field) => serverErrors[field])) return 5;
            if (subjects.some((field) => serverErrors[field])) return 4;
            if (section.some((field) => serverErrors[field])) return 3;
            if (academic.some((field) => serverErrors[field])) return 2;
            if (personal.some((field) => serverErrors[field])) return 1;
            return 1;
        },
        stepStatus(id) {
            if (id < this.step) return 'complete';
            return id === this.step ? 'current' : 'upcoming';
        },
        get passwordStrengthScore() {
            const password = this.form.password || '';
            let score = 0;
            if (password.length >= 8) score += 1;
            if (/[A-Z]/.test(password)) score += 1;
            if (/[0-9]/.test(password)) score += 1;
            if (/[^A-Za-z0-9]/.test(password)) score += 1;
            return score;
        },
        get passwordStrengthPercent() {
            return (this.passwordStrengthScore / 4) * 100;
        },
        get passwordStrengthClass() {
            const score = this.passwordStrengthScore;
            if (score <= 1) return 'bg-danger-ink';
            if (score === 2) return 'bg-warning-ink';
            if (score === 3) return 'bg-info-ink';
            return 'bg-success-ink';
        },
        get passwordStrengthLabel() {
            const password = this.form.password || '';
            if (!password) return 'Use at least 8 characters with letters and numbers.';
            const labels = ['Weak', 'Fair', 'Good', 'Strong'];
            return labels[Math.max(0, this.passwordStrengthScore - 1)] || 'Weak';
        },
        get reviewFullName() {
            return [this.form.first_name, this.form.middle_name, this.form.last_name, this.form.suffix]
                .filter((part) => part && String(part).trim() !== '')
                .join(' ');
        },
        get selectedProgramName() {
            const program = this.programs.find((item) => String(item.id) === String(this.form.program_id));
            return program?.name || '—';
        },
        get selectedYearLevelName() {
            const level = this.yearLevels.find((item) => String(item.id) === String(this.form.year_level_id));
            return level?.name || '—';
        },
        get selectedSectionName() {
            const section = this.sections.find((item) => String(item.id) === String(this.form.section_id));
            return section?.name || section?.code || '—';
        },
        get selectedOfferingsList() {
            return this.form.subject_offering_ids
                .map((id) => this.offeringCatalog[String(id)])
                .filter(Boolean);
        },
        validateStep1() {
            this.errors = {};
            if (!this.form.first_name.trim()) this.errors.first_name = 'First name is required.';
            if (!this.form.last_name.trim()) this.errors.last_name = 'Last name is required.';
            if (!this.form.phone.trim()) this.errors.phone = 'Contact number is required.';
            if (!this.form.email.trim()) this.errors.email = 'Email address is required.';
            return Object.keys(this.errors).length === 0;
        },
        validateStep2() {
            this.errors = {};
            if (!this.form.student_id.trim()) this.errors.student_id = 'Student ID is required.';
            if (!this.form.department_id) this.errors.department_id = 'Please select a department.';
            if (!this.form.program_id) this.errors.program_id = 'Please select a program.';
            if (!this.form.year_level_id) this.errors.year_level_id = 'Please select a year level.';
            return Object.keys(this.errors).length === 0;
        },
        validateStep3() {
            this.errors = {};
            if (!this.form.section_id) this.errors.section_id = 'Please select a section.';
            return Object.keys(this.errors).length === 0;
        },
        validateStep4() {
            this.errors = {};
            if (this.form.subject_offering_ids.length === 0) {
                this.errors.subject_offering_ids = 'Please select at least one enrolled subject offering.';
            }
            return Object.keys(this.errors).length === 0;
        },
        validateStep5() {
            this.errors = {};
            if (!this.form.password) this.errors.password = 'Password is required.';
            if (this.form.password !== this.form.password_confirmation) {
                this.errors.password_confirmation = 'Password confirmation does not match.';
            }
            return Object.keys(this.errors).length === 0;
        },
        next() {
            if (this.step === 1 && !this.validateStep1()) return;
            if (this.step === 2 && !this.validateStep2()) return;
            if (this.step === 3) {
                if (!this.validateStep3()) return;
                this.fetchSubjects();
            }
            if (this.step === 4 && !this.validateStep4()) return;
            this.step = Math.min(5, this.step + 1);
        },
        back() {
            this.errors = {};
            this.step = Math.max(1, this.step - 1);
        },
        validateAll() {
            const steps = [
                { step: 1, validate: () => this.validateStep1() },
                { step: 2, validate: () => this.validateStep2() },
                { step: 3, validate: () => this.validateStep3() },
                { step: 4, validate: () => this.validateStep4() },
                { step: 5, validate: () => this.validateStep5() },
            ];

            for (const { step, validate } of steps) {
                if (!validate()) {
                    this.step = step;
                    return false;
                }
            }

            return true;
        },
        submit(event) {
            if (!this.validateAll()) {
                event.preventDefault();
                return;
            }
            this.submitting = true;
        },
        isOfferingSelected(offeringId) {
            return this.form.subject_offering_ids.includes(String(offeringId));
        },
        toggleOffering(offeringId) {
            const id = String(offeringId);
            if (this.isOfferingSelected(id)) {
                this.form.subject_offering_ids = this.form.subject_offering_ids.filter((item) => item !== id);
            } else {
                this.form.subject_offering_ids = [...this.form.subject_offering_ids, id];
            }
        },
        toggleBrowseAll() {
            this.browseAllSubjects = !this.browseAllSubjects;
            this.fetchSubjects();
        },
        indexOfferings(offerings) {
            offerings.forEach((offering) => {
                this.offeringCatalog[String(offering.id)] = offering;
            });
        },
        async onDepartmentChange() {
            this.form.program_id = '';
            this.form.year_level_id = '';
            this.form.section_id = '';
            this.form.subject_offering_ids = [];
            this.programs = [];
            this.yearLevels = [];
            this.sections = [];
            this.recommendedSubjects = [];
            this.otherSubjects = [];
            await this.fetchPrograms();
        },
        async onProgramChange() {
            this.form.year_level_id = '';
            this.form.section_id = '';
            this.form.subject_offering_ids = [];
            this.yearLevels = [];
            this.sections = [];
            this.recommendedSubjects = [];
            this.otherSubjects = [];
            await this.fetchYearLevels();
        },
        async onYearLevelChange() {
            this.form.section_id = '';
            this.form.subject_offering_ids = [];
            this.sections = [];
            this.recommendedSubjects = [];
            this.otherSubjects = [];
            await this.fetchSections();
        },
        async onSectionChange() {
            this.form.subject_offering_ids = [];
            this.recommendedSubjects = [];
            this.otherSubjects = [];
            if (this.form.section_id) {
                await this.fetchSubjects();
            }
        },
        async fetchPrograms(reset = true) {
            if (!this.form.department_id) return;
            this.programsLoading = true;
            try {
                const response = await fetch(`${this.programsUrl}?department_id=${this.form.department_id}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                this.programs = data.programs || [];
                if (reset && !this.programs.some((item) => String(item.id) === String(this.form.program_id))) {
                    this.form.program_id = '';
                }
            } catch (error) {
                this.programs = [];
            } finally {
                this.programsLoading = false;
            }
        },
        async fetchYearLevels(reset = true) {
            if (!this.form.program_id) return;
            this.yearLevelsLoading = true;
            try {
                const response = await fetch(`${this.yearLevelsUrl}?program_id=${this.form.program_id}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                this.yearLevels = data.year_levels || [];
                if (reset && !this.yearLevels.some((item) => String(item.id) === String(this.form.year_level_id))) {
                    this.form.year_level_id = '';
                }
            } catch (error) {
                this.yearLevels = [];
            } finally {
                this.yearLevelsLoading = false;
            }
        },
        async fetchSections(reset = true) {
            if (!this.form.program_id || !this.form.year_level_id) return;
            this.sectionsLoading = true;
            try {
                const params = new URLSearchParams({
                    program_id: this.form.program_id,
                    year_level_id: this.form.year_level_id,
                });
                const response = await fetch(`${this.sectionsUrl}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                this.sections = data.sections || [];
                if (reset && !this.sections.some((item) => String(item.id) === String(this.form.section_id))) {
                    this.form.section_id = '';
                }
            } catch (error) {
                this.sections = [];
            } finally {
                this.sectionsLoading = false;
            }
        },
        async fetchSubjects() {
            if (!this.form.section_id || !this.form.department_id || !this.form.program_id || !this.form.year_level_id) {
                return;
            }
            this.subjectsLoading = true;
            try {
                const params = new URLSearchParams({
                    section_id: this.form.section_id,
                    department_id: this.form.department_id,
                    program_id: this.form.program_id,
                    year_level_id: this.form.year_level_id,
                    browse_all: this.browseAllSubjects ? '1' : '0',
                });
                if (this.subjectSearch.trim()) {
                    params.set('search', this.subjectSearch.trim());
                }
                const response = await fetch(`${this.subjectsUrl}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                this.recommendedSubjects = data.recommended || [];
                this.otherSubjects = data.other || [];
                this.indexOfferings(this.recommendedSubjects);
                this.indexOfferings(this.otherSubjects);
            } catch (error) {
                this.recommendedSubjects = [];
                this.otherSubjects = [];
            } finally {
                this.subjectsLoading = false;
            }
        },
    };
};
