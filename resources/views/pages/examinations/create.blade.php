<x-app-layout>
    <div class="ui-page" x-data="examWizard(@js($wizard))">
        <x-ui.page-header
            :title="$examination ? 'Edit Examination' : 'Create Examination'"
            :subtitle="$examination ? 'Update assigned sections and examination details.' : 'A guided workflow for publishing a complete examination.'"
        />

        <ol class="flex flex-wrap gap-4 border-b border-line pb-6" aria-label="Progress">
            <template x-for="item in steps" :key="item.id">
                <li class="flex items-center gap-2 text-sm">
                    <span
                        class="flex h-7 w-7 items-center justify-center rounded-full border text-xs font-medium"
                        :class="{
                            'border-brand bg-brand text-white dark:text-navy-950': status(item.id) === 'complete',
                            'border-brand text-ink': status(item.id) === 'current',
                            'border-line text-faint': status(item.id) === 'upcoming'
                        }"
                        x-text="status(item.id) === 'complete' ? '✓' : (status(item.id) === 'current' ? '●' : '○')"
                    ></span>
                    <span class="text-faint" x-text="item.key"></span>
                    <span class="font-medium" :class="status(item.id) === 'upcoming' ? 'text-muted' : 'text-ink'" x-text="item.label"></span>
                </li>
            </template>
        </ol>

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_16rem]">
            <div>
                <section x-show="step === 1" x-cloak>
                    <h2 class="ui-section">Information</h2>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <x-ui.field class="sm:col-span-2" label="Examination Title" for="title" help="Shown to students at the start of the examination.">
                            <input class="ui-input" id="title" x-model="form.title" autocomplete="off">
                            <p class="ui-error" x-show="fieldError('title')" x-cloak x-text="fieldError('title')"></p>
                        </x-ui.field>

                        <x-ui.field label="Academic Year" for="academic_year_id">
                            <select class="ui-input" id="academic_year_id" x-model="form.academicYearId" @change="onAcademicYearChange()">
                                <option value="">Select academic year</option>
                                <template x-for="year in academicYears" :key="year.id">
                                    <option :value="year.id" x-text="year.name"></option>
                                </template>
                            </select>
                            <p class="ui-error" x-show="fieldError('academic_year_id')" x-cloak x-text="fieldError('academic_year_id')"></p>
                        </x-ui.field>

                        <x-ui.field label="Semester" for="semester_id">
                            <select class="ui-input" id="semester_id" x-model="form.semesterId" @change="onFilterChange()" :disabled="!form.academicYearId">
                                <option value="">Select semester</option>
                                <template x-for="semester in filteredSemesters" :key="semester.id">
                                    <option :value="semester.id" x-text="semester.name"></option>
                                </template>
                            </select>
                            <p class="ui-help" x-show="form.academicYearId && filteredSemesters.length === 0">No semesters are available for this academic year yet.</p>
                            <p class="ui-error" x-show="fieldError('semester_id')" x-cloak x-text="fieldError('semester_id')"></p>
                        </x-ui.field>

                        <x-ui.field class="sm:col-span-2" label="Subject" for="subject_id">
                            <select class="ui-input" id="subject_id" x-model="form.subjectId" @change="onFilterChange()">
                                <option value="">Select subject</option>
                                <template x-for="subject in subjects" :key="subject.id">
                                    <option :value="subject.id" x-text="subject.code + ' — ' + subject.name"></option>
                                </template>
                            </select>
                            <p class="ui-error" x-show="fieldError('subject_id')" x-cloak x-text="fieldError('subject_id')"></p>
                        </x-ui.field>

                        <x-ui.field class="sm:col-span-2" label="Subject Offering / Class" for="subject_offering_id">
                            <select class="ui-input" id="subject_offering_id" x-model="form.subjectOfferingId" @change="onOfferingChange()" :disabled="!form.subjectId || offeringsLoading">
                                <option value="" x-text="offeringsLoading ? 'Loading class offerings...' : (availableOfferings.length === 0 && form.subjectId ? 'No class offerings available' : 'Select instructor and section')"></option>
                                <template x-for="offering in availableOfferings" :key="offering.id">
                                    <option :value="offering.id" x-text="offering.instructor_name + ' — ' + offering.section_name"></option>
                                </template>
                            </select>
                            <p class="ui-help" x-show="form.subjectOfferingId && selectedOfferingLabel()" x-text="selectedOfferingLabel()"></p>
                        </x-ui.field>

                        <x-ui.field label="Program / Course" for="program_id">
                            <select class="ui-input" id="program_id" x-model="form.programId" @change="onProgramChange()">
                                <option value="">Select program</option>
                                <template x-for="program in programs" :key="program.id">
                                    <option :value="program.id" x-text="program.code + ' — ' + program.name"></option>
                                </template>
                            </select>
                            <p class="ui-error" x-show="fieldError('program_id')" x-cloak x-text="fieldError('program_id')"></p>
                        </x-ui.field>

                        <x-ui.field label="Year Level" for="year_level_id">
                            <select class="ui-input" id="year_level_id" x-model="form.yearLevelId" @change="onFilterChange()" :disabled="!form.programId">
                                <option value="">Select year level</option>
                                <template x-for="level in filteredYearLevels" :key="level.id">
                                    <option :value="level.id" x-text="level.name"></option>
                                </template>
                            </select>
                            <p class="ui-help" x-show="form.programId && filteredYearLevels.length === 0">No year levels are available for this program yet.</p>
                            <p class="ui-error" x-show="fieldError('year_level_id')" x-cloak x-text="fieldError('year_level_id')"></p>
                        </x-ui.field>

                        <div class="sm:col-span-2 space-y-0">
                            <label class="ui-label">Examination Access</label>
                            <p class="mb-3 text-sm leading-6 text-muted">
                                Students must be enrolled in the selected subject. Section restrictions apply only when using the section-based access mode.
                            </p>
                            <div class="space-y-2">
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-line px-4 py-3">
                                    <input type="radio" class="mt-1" value="subject_only" x-model="form.accessMode">
                                    <span>
                                        <span class="block font-medium">All students enrolled in this subject</span>
                                        <span class="text-sm text-muted">Recommended for irregular students across sections or year levels.</span>
                                    </span>
                                </label>
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-line px-4 py-3">
                                    <input type="radio" class="mt-1" value="subject_and_sections" x-model="form.accessMode">
                                    <span>
                                        <span class="block font-medium">Only selected sections</span>
                                        <span class="text-sm text-muted">Student must be enrolled in the subject and belong to a selected section.</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="sm:col-span-2 space-y-0" x-show="form.accessMode === 'subject_and_sections'" x-cloak>
                            <label class="ui-label" for="section-search">Target Section(s) *</label>
                            <p class="mb-3 text-sm leading-6 text-muted">
                                Select the section(s) whose enrolled students may access this examination.
                            </p>

                            <div class="relative" @keydown.escape.window="sectionMenuOpen = false">
                                <div class="ui-combobox" :class="{ 'ring-2 ring-navy-700/20 border-navy-700': sectionMenuOpen, 'opacity-60': !filtersReady() }">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-faint">
                                        <x-icon name="search" :size="16" />
                                    </span>
                                    <input
                                        id="section-search"
                                        class="ui-input border-0 bg-transparent py-2.5 pl-9 shadow-none focus:ring-0"
                                        type="text"
                                        x-model="sectionQuery"
                                        @focus="openSectionMenu()"
                                        @input="sectionMenuOpen = true"
                                        :placeholder="filtersReady() ? 'Search or select sections...' : 'Select academic details first'"
                                        :disabled="!filtersReady() || sectionsLoading"
                                        autocomplete="off"
                                        aria-autocomplete="list"
                                        aria-controls="section-options"
                                    >
                                </div>

                                <div
                                    x-show="sectionMenuOpen && filtersReady()"
                                    x-cloak
                                    @click.outside="sectionMenuOpen = false"
                                    class="absolute z-30 mt-2 w-full overflow-hidden rounded-card border border-line bg-surface shadow-pop"
                                    id="section-options"
                                    role="listbox"
                                >
                                    <div class="flex items-center justify-between gap-3 border-b border-line px-3 py-2">
                                        <p class="text-xs text-muted">
                                            <span x-text="selectedCount"></span> selected
                                        </p>
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-ink hover:underline disabled:cursor-not-allowed disabled:text-faint disabled:no-underline"
                                            @click="selectAllFiltered()"
                                            x-show="filteredAvailable.length > 0"
                                            x-cloak
                                        >
                                            Select all filtered sections
                                        </button>
                                    </div>

                                    <div class="max-h-56 overflow-y-auto py-1" x-show="!sectionsLoading">
                                        <p class="px-3 py-3 text-sm text-muted" x-show="filteredAvailable.length === 0 && availableSections.length === 0">
                                            No sections match the selected academic details.
                                        </p>
                                        <p class="px-3 py-3 text-sm text-muted" x-show="filteredAvailable.length === 0 && availableSections.length > 0">
                                            No additional sections match your search.
                                        </p>
                                        <template x-for="section in filteredAvailable" :key="section.id">
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-brand-soft"
                                                @click="selectSection(section)"
                                                role="option"
                                            >
                                                <span x-text="section.name"></span>
                                                <span class="text-xs text-faint" x-show="section.code" x-text="section.code"></span>
                                            </button>
                                        </template>
                                    </div>

                                    <div class="px-3 py-3" x-show="sectionsLoading" x-cloak>
                                        <p class="text-sm text-muted">Loading sections…</p>
                                        <div class="mt-2 space-y-2" role="status" aria-label="Loading sections">
                                            <div class="skeleton h-8 w-full"></div>
                                            <div class="skeleton h-8 w-5/6"></div>
                                            <div class="skeleton h-8 w-2/3"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p class="ui-help" x-show="!filtersReady()">
                                Choose academic year, semester, subject, program, and year level to load matching sections.
                            </p>
                            <p class="ui-error" x-show="fieldError('section_ids')" x-cloak x-text="fieldError('section_ids')"></p>

                            <div class="mt-4" x-show="selectedSections.length > 0" x-cloak>
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium">Selected</p>
                                    <p class="text-xs text-muted"><span x-text="selectedCount"></span> section<span x-text="selectedCount === 1 ? '' : 's'"></span></p>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="section in selectedSections" :key="section.id">
                                        <span class="ui-chip">
                                            <span x-text="section.name"></span>
                                            <button type="button" class="ui-chip-remove" @click="removeSection(section.id)" :aria-label="'Remove ' + section.name">
                                                <x-icon name="x" :size="12" />
                                            </button>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <x-ui.field label="Examination Period" for="period">
                            <select class="ui-input" id="period" x-model="form.period">
                                <option value="PRELIM">Prelim</option>
                                <option value="MIDTERM">Midterm</option>
                                <option value="FINAL">Final</option>
                            </select>
                            <p class="ui-error" x-show="fieldError('examination_period')" x-cloak x-text="fieldError('examination_period')"></p>
                        </x-ui.field>

                        <x-ui.field class="sm:col-span-2" label="Instructions" for="instructions">
                            <textarea class="ui-input min-h-28" id="instructions" x-model="form.instructions"></textarea>
                        </x-ui.field>
                    </div>
                </section>

                <section x-show="step === 2" x-cloak>
                    <h2 class="ui-section">Settings</h2>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Examination Duration" for="duration" help="Set the maximum time allowed for students to complete the examination.">
                            <div class="relative">
                                <input class="ui-input pr-20" id="duration" type="number" min="1" x-model="form.duration">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted">minutes</span>
                            </div>
                        </x-ui.field>
                        <x-ui.field label="Passing Percentage" for="passing">
                            <input class="ui-input" id="passing" type="number" min="1" max="100" x-model="form.passing">
                        </x-ui.field>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded border-line text-navy-800" x-model="form.randomize"> Randomize questions</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded border-line text-navy-800" x-model="form.backNav"> Allow back navigation</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded border-line text-navy-800" x-model="form.autoSubmit"> Auto-submit when time expires</label>
                    </div>
                </section>

                <section x-show="step === 3" x-cloak>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="ui-section">Questions</h2>
                        <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="addQuestion()">Add question</x-ui.button>
                    </div>

                    <div class="mt-4">
                        <x-questions.csv-import-panel
                            :template-url="route('examinations.question-csv-template')"
                            :preview-url="route('examinations.preview-questions-csv')"
                            :confirm-url="route('examinations.import-questions')"
                            :error-report-url="route('examinations.question-csv-error-report')"
                            @csv-imported="handleCsvImported($event.detail)"
                        />
                    </div>

                    <div class="mt-6 space-y-4">
                        <template x-for="(question, index) in questions" :key="question.id">
                            <article class="ui-card ui-card-pad" draggable="true">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2 text-muted">
                                        <x-icon name="grip-vertical" :size="16" />
                                        <h3 class="font-semibold text-ink">Question <span x-text="String(index + 1).padStart(2, '0')"></span></h3>
                                    </div>
                                    <div class="flex gap-1">
                                        <button type="button" class="btn-ghost btn-sm" @click="duplicateQuestion(index)">Duplicate</button>
                                        <button type="button" class="btn-ghost btn-sm text-danger-ink" @click="removeQuestion(index)">Delete</button>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-4">
                                    <x-ui.field label="Question type">
                                        <select class="ui-input max-w-xs" x-model="question.type" @change="onQuestionTypeChange(question)" aria-label="Question type">
                                            <option value="multiple_choice">Multiple Choice</option>
                                            <option value="true_false">True / False</option>
                                            <option value="identification">Identification</option>
                                            <option value="essay">Essay</option>
                                        </select>
                                    </x-ui.field>
                                    <x-ui.field label="Question">
                                        <textarea class="ui-input min-h-24" placeholder="Enter question..." x-model="question.text"></textarea>
                                    </x-ui.field>

                                    <div x-show="question.type === 'multiple_choice'" x-cloak>
                                        <p class="ui-label">Answer choices</p>
                                        <p class="mb-3 text-sm text-muted">Enter each option and select the correct answer.</p>
                                        <div class="space-y-2">
                                            <template x-for="choice in question.choices" :key="choice.id">
                                                <label class="flex items-center gap-3 rounded-card border border-line px-3 py-2"
                                                    :class="question.correctAnswer === choice.id ? 'border-brand bg-brand-soft' : ''">
                                                    <input
                                                        type="radio"
                                                        class="border-line text-navy-800"
                                                        :name="'correct-' + question.id"
                                                        :value="choice.id"
                                                        x-model="question.correctAnswer"
                                                        :aria-label="'Mark ' + choice.id + ' as correct'"
                                                    >
                                                    <span class="w-6 text-sm font-medium text-muted" x-text="choice.id"></span>
                                                    <input class="ui-input border-0 bg-transparent px-0 shadow-none focus:ring-0" placeholder="Enter choice..." x-model="choice.text">
                                                </label>
                                            </template>
                                        </div>
                                    </div>

                                    <div x-show="question.type === 'true_false'" x-cloak>
                                        <p class="ui-label">Correct answer</p>
                                        <p class="mb-3 text-sm text-muted">Choose whether the statement is true or false.</p>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <template x-for="choice in question.choices" :key="choice.id">
                                                <label class="flex cursor-pointer items-center gap-3 rounded-card border border-line px-4 py-3"
                                                    :class="question.correctAnswer === choice.id ? 'border-brand bg-brand-soft' : ''">
                                                    <input
                                                        type="radio"
                                                        class="border-line text-navy-800"
                                                        :name="'tf-' + question.id"
                                                        :value="choice.id"
                                                        x-model="question.correctAnswer"
                                                    >
                                                    <span class="font-medium" x-text="choice.text"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>

                                    <div x-show="question.type === 'identification'" x-cloak>
                                        <x-ui.field label="Correct answer" help="Student responses are matched to this answer automatically (case-insensitive).">
                                            <input class="ui-input" type="text" placeholder="Enter the accepted answer..." x-model="question.correctAnswer">
                                        </x-ui.field>
                                    </div>

                                    <div x-show="question.type === 'essay'" x-cloak>
                                        <div class="rounded-card border border-line bg-brand-soft px-4 py-3 text-sm text-muted">
                                            Essay questions are graded manually after students submit their responses.
                                        </div>
                                        <x-ui.field class="mt-4" label="Sample answer / rubric notes" help="Optional reference for grading. Not shown to students.">
                                            <textarea class="ui-input min-h-24" placeholder="Add grading notes or a sample response..." x-model="question.sampleAnswer"></textarea>
                                        </x-ui.field>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-3">
                                        <x-ui.field label="Points">
                                            <input class="ui-input" type="number" min="1" x-model="question.points">
                                        </x-ui.field>
                                        <x-ui.field label="Difficulty">
                                            <select class="ui-input" x-model="question.difficulty">
                                                <option>Easy</option>
                                                <option>Medium</option>
                                                <option>Hard</option>
                                            </select>
                                        </x-ui.field>
                                        <x-ui.field label="Topic">
                                            <input class="ui-input" placeholder="Select Topic" x-model="question.topic">
                                        </x-ui.field>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>
                </section>

                <section x-show="step === 4" x-cloak>
                    <h2 class="ui-section">Review</h2>
                    <x-ui.card class="mt-6 space-y-3">
                        <p><span class="text-muted">Title</span> · <span class="font-medium" x-text="form.title || '—'"></span></p>
                        <p><span class="text-muted">Subject</span> · <span class="font-medium" x-text="selectedSubjectLabel()"></span></p>
                        <p><span class="text-muted">Period</span> · <span class="font-medium" x-text="periodLabel()"></span></p>
                        <p>
                            <span class="text-muted">Sections</span> ·
                            <span class="font-medium" x-text="selectedSections.map((item) => item.name).join(', ') || '—'"></span>
                        </p>
                        <p><span class="text-muted">Duration</span> · <span class="font-medium" x-text="form.duration + ' minutes'"></span></p>
                        <p><span class="text-muted">Questions</span> · <span class="font-medium" x-text="questions.length"></span></p>
                        <p><span class="text-muted">Passing</span> · <span class="font-medium" x-text="form.passing + '%'"></span></p>
                    </x-ui.card>
                </section>

                <div class="mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-6">
                    <x-ui.button variant="ghost" @click="back()" x-show="step > 1">Back</x-ui.button>
                    <span x-show="step === 1"></span>
                    <div class="flex gap-2">
                        <x-ui.button variant="secondary" @click="saveDraft()" x-bind:disabled="saving || publishing">
                            <span x-show="!saving">Save Draft</span>
                            <span x-show="saving" x-cloak>Saving…</span>
                        </x-ui.button>
                        <x-ui.button x-show="step < 4" @click="next()">Continue</x-ui.button>
                        <x-ui.button x-show="step === 4" x-cloak @click="publish()" x-bind:disabled="saving || publishing">
                            <span x-show="!publishing">Publish Examination</span>
                            <span x-show="publishing" x-cloak>Publishing…</span>
                        </x-ui.button>
                    </div>
                </div>
            </div>

            <aside class="hidden lg:block">
                <p class="text-sm text-muted">Only students from the selected section(s) will be able to access this examination.</p>
            </aside>
        </div>
    </div>
</x-app-layout>
