<x-registration-layout>
    <div
        x-data="studentRegistrationWizard(@js([
            'storeUrl' => $formAction ?? route('student-registration.store'),
            'programsUrl' => route('student-registration.programs'),
            'yearLevelsUrl' => route('student-registration.year-levels'),
            'sectionsUrl' => route('student-registration.sections'),
            'subjectsUrl' => route('student-registration.subjects'),
            'departments' => $departments,
            'old' => old(),
            'errors' => $errors->messages(),
            'googleMode' => $googleMode ?? false,
            'googleProfile' => $googleProfile ?? null,
        ]))"
        class="mt-8"
    >
        <header>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $pageTitle ?? 'Create Student Account' }}</h1>
            <p class="mt-2 text-sm leading-6 text-muted">
                {{ $pageDescription ?? 'Register to access the student portal. Fields marked with * are required.' }}
            </p>

            @if(!empty($googleProfile))
                <div class="mt-4 rounded-lg border border-success-line bg-success-soft px-4 py-3 text-sm">
                    <p class="font-medium text-success-ink">Google Account</p>
                    <p class="mt-1">{{ $googleProfile['email'] ?? '' }}</p>
                    <p class="mt-1 text-success-ink">✓ Verified</p>
                </div>
            @else
                @php
                    $googleSettings = app(\App\Services\Google\GoogleIntegrationSettings::class);
                @endphp
                @if($googleSettings->isConfigured() && $googleSettings->registrationEnabled())
                    <div class="mt-6">
                        @include('components.google-auth-section', ['intent' => 'register', 'label' => 'Continue with Google'])
                    </div>
                @endif
            @endif
        </header>

        <nav class="mt-8" aria-label="Registration progress">
            <ol class="flex items-center gap-2">
                <template x-for="(item, index) in steps" :key="item.id">
                    <li class="flex flex-1 items-center gap-2">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors"
                                :class="stepStatus(item.id) === 'current' ? 'bg-brand text-white' : (stepStatus(item.id) === 'complete' ? 'bg-success-soft text-success-ink' : 'bg-brand-soft text-muted')"
                                x-text="item.id"
                            ></span>
                            <span class="hidden text-sm font-medium sm:inline" :class="stepStatus(item.id) === 'current' ? 'text-ink' : 'text-muted'" x-text="item.label"></span>
                        </div>
                        <div x-show="index < steps.length - 1" class="hidden h-px flex-1 bg-line sm:block"></div>
                    </li>
                </template>
            </ol>
            <p class="mt-3 text-sm font-medium sm:hidden" x-text="steps.find((item) => item.id === step)?.label"></p>
        </nav>

        <form method="post" action="{{ $formAction ?? route('student-registration.store') }}" @submit="submit" novalidate class="mt-8 space-y-6">
            @csrf

            <template x-for="offeringId in form.subject_offering_ids" :key="'offering-' + offeringId">
                <input type="hidden" name="subject_offering_ids[]" :value="offeringId">
            </template>

            {{-- Step 1: Personal --}}
            <div x-show="step === 1" x-cloak class="ui-card ui-card-pad space-y-5">
                <h2 class="text-lg font-semibold">Personal Information</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="First Name *" for="first_name">
                        <input type="text" id="first_name" name="first_name" x-model="form.first_name" class="ui-input" autocomplete="given-name" required>
                        <p class="ui-error" x-show="errors.first_name" x-text="errors.first_name" role="alert"></p>
                    </x-ui.field>

                    <x-ui.field label="Middle Name" for="middle_name">
                        <input type="text" id="middle_name" name="middle_name" x-model="form.middle_name" class="ui-input" autocomplete="additional-name">
                    </x-ui.field>

                    <x-ui.field label="Last Name *" for="last_name">
                        <input type="text" id="last_name" name="last_name" x-model="form.last_name" class="ui-input" autocomplete="family-name" required>
                        <p class="ui-error" x-show="errors.last_name" x-text="errors.last_name" role="alert"></p>
                    </x-ui.field>

                    <x-ui.field label="Suffix" for="suffix" help="e.g. Jr., III">
                        <input type="text" id="suffix" name="suffix" x-model="form.suffix" class="ui-input">
                    </x-ui.field>

                    <x-ui.field label="Sex / Gender" for="sex">
                        <select id="sex" name="sex" x-model="form.sex" class="ui-input">
                            <option value="">Prefer not to say</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </x-ui.field>

                    <x-ui.field label="Date of Birth" for="date_of_birth">
                        <input type="date" id="date_of_birth" name="date_of_birth" x-model="form.date_of_birth" class="ui-input" :max="today">
                    </x-ui.field>

                    <x-ui.field label="Contact Number *" for="phone" class="sm:col-span-2">
                        <input type="tel" id="phone" name="phone" x-model="form.phone" class="ui-input" autocomplete="tel" required>
                        <p class="ui-error" x-show="errors.phone" x-text="errors.phone" role="alert"></p>
                    </x-ui.field>

                    <x-ui.field label="Email Address *" for="email" class="sm:col-span-2">
                        <input type="email" id="email" name="email" x-model="form.email" class="ui-input" autocomplete="email" required @if(!empty($googleProfile)) readonly @endif>
                        @if(!empty($googleProfile))
                            <p class="ui-help text-success-ink">Verified through Google Sign-In</p>
                        @endif
                        <p class="ui-error" x-show="errors.email" x-text="errors.email" role="alert"></p>
                    </x-ui.field>

                    <x-ui.field label="Home Address" for="home_address" class="sm:col-span-2">
                        <textarea id="home_address" name="home_address" x-model="form.home_address" rows="2" class="ui-input"></textarea>
                    </x-ui.field>
                </div>
            </div>

            {{-- Step 2: Academic --}}
            <div x-show="step === 2" x-cloak class="ui-card ui-card-pad space-y-5">
                <h2 class="text-lg font-semibold">Academic Information</h2>

                <x-ui.field label="Student ID / Student Number *" for="student_id">
                    <input type="text" id="student_id" name="student_id" x-model="form.student_id" class="ui-input" required>
                    <p class="ui-error" x-show="errors.student_id" x-text="errors.student_id" role="alert"></p>
                </x-ui.field>

                <x-ui.field label="College / Department *" for="department_id">
                    <select id="department_id" name="department_id" x-model="form.department_id" @change="onDepartmentChange" class="ui-input" required>
                        <option value="">Select department</option>
                        <template x-for="department in departments" :key="department.id">
                            <option :value="department.id" x-text="department.name"></option>
                        </template>
                    </select>
                    <p class="ui-error" x-show="errors.department_id" x-text="errors.department_id" role="alert"></p>
                </x-ui.field>

                <x-ui.field label="Program / Course *" for="program_id">
                    <select id="program_id" name="program_id" x-model="form.program_id" @change="onProgramChange" class="ui-input" :disabled="!form.department_id || programsLoading" required>
                        <option value="" x-text="programsLoading ? 'Loading programs...' : 'Select program'"></option>
                        <template x-for="program in programs" :key="program.id">
                            <option :value="program.id" x-text="program.name"></option>
                        </template>
                    </select>
                    <p class="ui-error" x-show="errors.program_id" x-text="errors.program_id" role="alert"></p>
                </x-ui.field>

                <x-ui.field label="Year Level *" for="year_level_id">
                    <select id="year_level_id" name="year_level_id" x-model="form.year_level_id" @change="onYearLevelChange" class="ui-input" :disabled="!form.program_id || yearLevelsLoading" required>
                        <option value="" x-text="yearLevelsLoading ? 'Loading year levels...' : 'Select year level'"></option>
                        <template x-for="level in yearLevels" :key="level.id">
                            <option :value="level.id" x-text="level.name"></option>
                        </template>
                    </select>
                    <p class="ui-error" x-show="errors.year_level_id" x-text="errors.year_level_id" role="alert"></p>
                </x-ui.field>
            </div>

            {{-- Step 3: Section --}}
            <div x-show="step === 3" x-cloak class="ui-card ui-card-pad space-y-5">
                <h2 class="text-lg font-semibold">Select Section</h2>
                <p class="text-sm leading-6 text-muted">Choose your primary section. This does not automatically determine your enrolled subjects.</p>

                <x-ui.field label="Section *" for="section_id">
                    <select id="section_id" name="section_id" x-model="form.section_id" @change="onSectionChange" class="ui-input" :disabled="!form.year_level_id || sectionsLoading" required>
                        <option value="" x-text="sectionsLoading ? 'Loading sections...' : (sections.length === 0 && form.year_level_id ? 'No sections available' : 'Select section')"></option>
                        <template x-for="section in sections" :key="section.id">
                            <option :value="section.id" x-text="section.name || section.code"></option>
                        </template>
                    </select>
                    <p class="ui-help" x-show="!sectionsLoading && sections.length === 0 && form.year_level_id">No sections are available for the selected program and year level. Please contact the administrator.</p>
                    <p class="ui-error" x-show="errors.section_id" x-text="errors.section_id" role="alert"></p>
                </x-ui.field>
            </div>

            {{-- Step 4: Enrolled Subjects --}}
            <div x-show="step === 4" x-cloak class="ui-card ui-card-pad space-y-5">
                <div>
                    <p class="text-sm font-medium text-muted">Step 4 of 5 — Enrolled Subjects</p>
                    <h2 class="mt-1 text-lg font-semibold">Select Your Enrolled Subjects</h2>
                    <p class="mt-2 text-sm leading-6 text-muted">
                        Select only the subjects you are officially enrolled in with the School Registrar.
                        If you are an irregular student, select all subjects included in your current enrollment.
                    </p>
                    <div class="mt-3 rounded-lg border border-warning-line bg-warning-soft px-4 py-3 text-sm leading-6 text-warning-ink">
                        <strong>Important:</strong> Your selected subjects will be used to determine which examinations and subject-related activities you can access.
                    </div>
                </div>

                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </span>
                    <input
                        type="search"
                        x-model="subjectSearch"
                        @input.debounce.300ms="fetchSubjects"
                        class="ui-input pl-10"
                        placeholder="Search by subject code, name, instructor, or section..."
                        autocomplete="off"
                    >
                </div>

                <div x-show="subjectsLoading" class="text-sm text-muted">Loading subjects...</div>

                <div x-show="!subjectsLoading && recommendedSubjects.length > 0" class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-muted">Recommended for Your Section</h3>
                    <div class="space-y-2">
                        <template x-for="offering in recommendedSubjects" :key="'rec-' + offering.id">
                            <label class="block cursor-pointer rounded-lg border border-line px-4 py-3 transition hover:border-brand hover:bg-brand-soft/30">
                                <span class="flex items-start gap-3">
                                    <input type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded border-line" :checked="isOfferingSelected(offering.id)" @change="toggleOffering(offering.id)">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold tracking-wide" x-text="offering.code"></span>
                                        <span class="mt-0.5 block text-sm leading-6" x-text="offering.name"></span>
                                        <span class="mt-3 grid gap-1 text-sm text-muted sm:grid-cols-[5.5rem_minmax(0,1fr)]">
                                            <span>Instructor</span>
                                            <span class="text-ink" x-text="offering.instructor_name"></span>
                                            <span>Section</span>
                                            <span class="text-ink" x-text="offering.section_name"></span>
                                        </span>
                                        <span class="mt-2 block text-sm text-muted" x-show="offering.program_name || offering.year_level_name" x-text="[offering.program_name, offering.year_level_name].filter(Boolean).join(' • ')"></span>
                                    </span>
                                </span>
                            </label>
                        </template>
                    </div>
                </div>

                <div x-show="!subjectsLoading" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-muted">Other Available Subjects</h3>
                        <button type="button" class="text-sm font-medium text-brand hover:underline" @click="toggleBrowseAll()" x-text="browseAllSubjects ? 'Show department subjects only' : 'Browse all available subjects'"></button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="offering in otherSubjects" :key="'other-' + offering.id">
                            <label class="block cursor-pointer rounded-lg border border-line px-4 py-3 transition hover:border-brand hover:bg-brand-soft/30">
                                <span class="flex items-start gap-3">
                                    <input type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded border-line" :checked="isOfferingSelected(offering.id)" @change="toggleOffering(offering.id)">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold tracking-wide" x-text="offering.code"></span>
                                        <span class="mt-0.5 block text-sm leading-6" x-text="offering.name"></span>
                                        <span class="mt-3 grid gap-1 text-sm text-muted sm:grid-cols-[5.5rem_minmax(0,1fr)]">
                                            <span>Instructor</span>
                                            <span class="text-ink" x-text="offering.instructor_name"></span>
                                            <span>Section</span>
                                            <span class="text-ink" x-text="offering.section_name"></span>
                                        </span>
                                        <span class="mt-2 block text-sm text-muted" x-show="offering.program_name || offering.year_level_name" x-text="[offering.program_name, offering.year_level_name].filter(Boolean).join(' • ')"></span>
                                    </span>
                                </span>
                            </label>
                        </template>
                        <p x-show="otherSubjects.length === 0 && !subjectsLoading" class="text-sm text-muted">No additional subject offerings match your search.</p>
                    </div>
                </div>

                <div class="rounded-lg border border-line bg-surface-2 px-4 py-4">
                    <h3 class="text-sm font-semibold">Your Selected Subjects</h3>
                    <template x-if="selectedOfferingsList.length === 0">
                        <p class="mt-2 text-sm text-muted">No subjects selected yet.</p>
                    </template>
                    <ol x-show="selectedOfferingsList.length > 0" class="mt-3 space-y-3">
                        <template x-for="(offering, index) in selectedOfferingsList" :key="'sel-' + offering.id">
                            <li class="rounded-lg border border-line bg-surface px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold" x-text="offering.code"></p>
                                        <p class="text-sm leading-6" x-text="offering.name"></p>
                                        <p class="mt-2 text-sm text-muted">Instructor: <span class="text-ink" x-text="offering.instructor_name"></span></p>
                                        <p class="text-sm text-muted">Section: <span class="text-ink" x-text="offering.section_name"></span></p>
                                    </div>
                                    <button type="button" class="shrink-0 text-sm text-danger-ink hover:underline" @click="toggleOffering(offering.id)">Remove</button>
                                </div>
                            </li>
                        </template>
                    </ol>
                    <p class="mt-3 text-sm font-medium">Total Selected: <span x-text="form.subject_offering_ids.length"></span></p>
                </div>

                <p class="ui-error" x-show="errors.subject_offering_ids" x-text="errors.subject_offering_ids" role="alert"></p>
            </div>

            {{-- Step 5: Review & Account --}}
            <div x-show="step === 5" x-cloak class="space-y-6">
                <div class="ui-card ui-card-pad space-y-5">
                    <h2 class="text-lg font-semibold">Review Your Registration</h2>

                    <div class="space-y-4 text-sm">
                        <div>
                            <h3 class="font-semibold">Student Information</h3>
                            <p class="mt-1" x-text="reviewFullName"></p>
                            <p class="text-muted">Student No.: <span x-text="form.student_id"></span></p>
                            <p class="text-muted" x-text="form.email"></p>
                        </div>

                        <div>
                            <h3 class="font-semibold">Academic Information</h3>
                            <dl class="mt-2 grid gap-1 text-muted">
                                <div>Program: <span class="text-ink" x-text="selectedProgramName"></span></div>
                                <div>Year Level: <span class="text-ink" x-text="selectedYearLevelName"></span></div>
                                <div>Section: <span class="text-ink" x-text="selectedSectionName"></span></div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="font-semibold">Enrolled Subjects</h3>
                            <ul class="mt-2 space-y-2">
                                <template x-for="offering in selectedOfferingsList" :key="'review-' + offering.id">
                                    <li class="rounded-lg border border-line px-4 py-3 text-sm">
                                        <p class="font-medium" x-text="'✓ ' + offering.code + ' — ' + offering.name"></p>
                                        <p class="mt-1 text-muted">Instructor: <span class="text-ink" x-text="offering.instructor_name"></span></p>
                                        <p class="text-muted">Section: <span class="text-ink" x-text="offering.section_name"></span></p>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="ui-card ui-card-pad space-y-5">
                    <h2 class="text-lg font-semibold">Account Security</h2>
                    <p class="text-sm text-muted" x-show="googleMode">Optional: set a password if you also want to sign in with email and password.</p>

                    <x-ui.field :label="empty($googleProfile) ? 'Password *' : 'Password (optional)'" for="password">
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" id="password" name="password" x-model="form.password" class="ui-input pr-10" autocomplete="new-password" :required="!googleMode">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-muted hover:text-ink" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                <x-icon name="eye" :size="18" x-show="!showPassword" />
                                <x-icon name="eye-off" :size="18" x-show="showPassword" x-cloak />
                            </button>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-brand-soft">
                            <div class="h-full rounded-full transition-all" :class="passwordStrengthClass" :style="`width: ${passwordStrengthPercent}%`"></div>
                        </div>
                        <p class="ui-help" x-text="passwordStrengthLabel"></p>
                        <p class="ui-error" x-show="errors.password" x-text="errors.password" role="alert"></p>
                    </x-ui.field>

                    <x-ui.field :label="empty($googleProfile) ? 'Confirm Password *' : 'Confirm Password (optional)'" for="password_confirmation">
                        <div class="relative">
                            <input :type="showConfirmPassword ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" x-model="form.password_confirmation" class="ui-input pr-10" autocomplete="new-password" :required="!googleMode">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-muted hover:text-ink" @click="showConfirmPassword = !showConfirmPassword">
                                <x-icon name="eye" :size="18" x-show="!showConfirmPassword" />
                                <x-icon name="eye-off" :size="18" x-show="showConfirmPassword" x-cloak />
                            </button>
                        </div>
                        <p class="ui-error" x-show="errors.password_confirmation" x-text="errors.password_confirmation" role="alert"></p>
                    </x-ui.field>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" class="btn-secondary" x-show="step > 1" @click="back">← Back</button>
                <div class="ms-auto flex flex-wrap gap-2">
                    <button type="button" class="btn-primary" x-show="step < 5" @click="next">Continue →</button>
                    <button type="submit" class="btn-primary" x-show="step === 5" :disabled="submitting">
                        <span x-show="!submitting">Submit Registration</span>
                        <span x-show="submitting">Submitting...</span>
                    </button>
                </div>
            </div>

            <p class="text-center text-sm text-muted">
                Already registered?
                <a href="{{ route('login') }}" class="font-medium text-ink underline-offset-4 hover:underline">Sign in</a>
            </p>
        </form>
    </div>
</x-registration-layout>
