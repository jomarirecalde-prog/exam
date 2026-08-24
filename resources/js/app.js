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
        errors: config.errors || {},
        steps: [
            { id: 1, key: '01', label: 'Information' },
            { id: 2, key: '02', label: 'Settings' },
            { id: 3, key: '03', label: 'Questions' },
            { id: 4, key: '04', label: 'Schedule' },
            { id: 5, key: '05', label: 'Review' },
        ],
        questions: [
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
            period: incomingForm.period || 'MIDTERM',
            duration: incomingForm.duration ?? 60,
            passing: incomingForm.passing ?? 75,
            instructions: incomingForm.instructions || '',
            randomize: incomingForm.randomize ?? true,
            backNav: incomingForm.backNav ?? true,
            autoSubmit: incomingForm.autoSubmit ?? true,
            date: incomingForm.date || '',
            start: incomingForm.start || '08:00',
            end: incomingForm.end || '10:00',
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
            if (this.form.sectionIds.length === 0) {
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
                examination_period: this.form.period,
                instructions: this.form.instructions,
                duration_minutes: this.form.duration,
                passing_percentage: this.form.passing,
                randomize_questions: Boolean(this.form.randomize),
                allow_back_navigation: Boolean(this.form.backNav),
                auto_submit_on_expire: Boolean(this.form.autoSubmit),
                examination_date: this.form.date || null,
                start_time: this.form.start || null,
                end_time: this.form.end || null,
                status,
            };
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
            this.step = Math.min(5, this.step + 1);
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
        createQuestion(overrides = {}) {
            return {
                id: this.questions.length + 1,
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
            copy.id = this.questions.length + 1;
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
