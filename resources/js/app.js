import './bootstrap';

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
                    window.appToast(data.message || 'Unable to preview this CSV file.', 'error');
                    this.statusMessage = 'Validation failed. Please review the errors and try again.';
                    return;
                }

                this.statusMessage = '';
                this.previewOpen = true;
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
        importQuestionsUrl: config.importQuestionsUrl || '',
        questionCsvTemplateUrl: config.questionCsvTemplateUrl || '',
        indexUrl: config.indexUrl || '',
        academicYears: config.academicYears || [],
        semesters: config.semesters || [],
        subjects: config.subjects || [],
        programs: config.programs || [],
        yearLevels: config.yearLevels || [],
        availableSections: [],
        selectedSections: config.selectedSections || [],
        sectionsLoading: false,
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
        },
        init() {
            if (this.filtersReady()) {
                this.fetchSections({ prune: false });
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
            this.fetchSections({ prune: true });
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
        payload(status) {
            return {
                title: this.form.title,
                academic_year_id: this.form.academicYearId,
                semester_id: this.form.semesterId,
                subject_id: this.form.subjectId,
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
    return {
        title: config.title,
        total: config.total,
        remaining: config.remaining,
        current: 1,
        navigatorOpen: false,
        submitOpen: false,
        submitting: false,
        answers: {},
        flagged: {},
        questions: config.questions,
        resultUrl: config.resultUrl,
        timerId: null,
        init() {
            this.timerId = setInterval(() => {
                if (this.remaining > 0) {
                    this.remaining -= 1;
                }
                if (this.remaining === 0) {
                    clearInterval(this.timerId);
                    this.submitExam(true);
                }
            }, 1000);
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
            return Object.keys(this.answers).filter((key) => this.answers[key]).length;
        },
        get unanswered() {
            return this.questions
                .map((question, index) => ({ ...question, number: index + 1 }))
                .filter((question) => !this.answers[question.number]);
        },
        select(choice) {
            this.answers[this.current] = choice;
        },
        flag() {
            this.flagged[this.current] = !this.flagged[this.current];
        },
        go(number) {
            this.current = number;
            this.navigatorOpen = false;
        },
        prev() {
            this.current = Math.max(1, this.current - 1);
        },
        next() {
            this.current = Math.min(this.total, this.current + 1);
        },
        submitExam(auto = false) {
            if (this.submitting) {
                return;
            }
            this.submitting = true;
            this.submitOpen = false;
            setTimeout(() => {
                window.location.href = this.resultUrl;
            }, auto ? 400 : 900);
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
        subjectCatalog: {},
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
            subject_ids: Array.isArray(old.subject_ids) ? old.subject_ids.map(String) : [],
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
            const subjects = ['subject_ids'];
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
        get selectedSubjectsList() {
            return this.form.subject_ids
                .map((id) => this.subjectCatalog[String(id)])
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
            if (this.form.subject_ids.length === 0) {
                this.errors.subject_ids = 'Please select at least one enrolled subject.';
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
        submit(event) {
            if (!this.validateStep5()) {
                event.preventDefault();
                this.step = 5;
                return;
            }
            this.submitting = true;
        },
        isSubjectSelected(subjectId) {
            return this.form.subject_ids.includes(String(subjectId));
        },
        toggleSubject(subjectId) {
            const id = String(subjectId);
            if (this.isSubjectSelected(id)) {
                this.form.subject_ids = this.form.subject_ids.filter((item) => item !== id);
            } else {
                this.form.subject_ids = [...this.form.subject_ids, id];
            }
        },
        toggleBrowseAll() {
            this.browseAllSubjects = !this.browseAllSubjects;
            this.fetchSubjects();
        },
        indexSubjects(subjects) {
            subjects.forEach((subject) => {
                this.subjectCatalog[String(subject.id)] = subject;
            });
        },
        async onDepartmentChange() {
            this.form.program_id = '';
            this.form.year_level_id = '';
            this.form.section_id = '';
            this.form.subject_ids = [];
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
            this.form.subject_ids = [];
            this.yearLevels = [];
            this.sections = [];
            this.recommendedSubjects = [];
            this.otherSubjects = [];
            await this.fetchYearLevels();
        },
        async onYearLevelChange() {
            this.form.section_id = '';
            this.form.subject_ids = [];
            this.sections = [];
            this.recommendedSubjects = [];
            this.otherSubjects = [];
            await this.fetchSections();
        },
        async onSectionChange() {
            this.form.subject_ids = [];
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
                this.indexSubjects(this.recommendedSubjects);
                this.indexSubjects(this.otherSubjects);
            } catch (error) {
                this.recommendedSubjects = [];
                this.otherSubjects = [];
            } finally {
                this.subjectsLoading = false;
            }
        },
    };
};
