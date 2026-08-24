<x-registration-layout>
    <div
        x-data="studentRegistrationWizard(@js([
            'storeUrl' => route('student-registration.store'),
            'programsUrl' => route('student-registration.programs'),
            'yearLevelsUrl' => route('student-registration.year-levels'),
            'sectionsUrl' => route('student-registration.sections'),
            'departments' => $departments,
            'old' => old(),
            'errors' => $errors->messages(),
        ]))"
        class="mt-8"
    >
        <header>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Create Student Account</h1>
            <p class="mt-2 text-sm leading-6 text-muted">
                Register to access the student portal. Fields marked with <span class="text-danger-ink">*</span> are required.
            </p>
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

        <form method="post" action="{{ route('student-registration.store') }}" @submit="submit" class="mt-8 space-y-6">
            @csrf

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
                        <input type="email" id="email" name="email" x-model="form.email" class="ui-input" autocomplete="email" required>
                        <p class="ui-error" x-show="errors.email" x-text="errors.email" role="alert"></p>
                    </x-ui.field>

                    <x-ui.field label="Home Address" for="home_address" class="sm:col-span-2">
                        <textarea id="home_address" name="home_address" x-model="form.home_address" rows="2" class="ui-input"></textarea>
                    </x-ui.field>
                </div>
            </div>

            <div x-show="step === 2" x-cloak class="ui-card ui-card-pad space-y-5">
                <h2 class="text-lg font-semibold">Student Information</h2>

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

                <x-ui.field label="Section *" for="section_id">
                    <select id="section_id" name="section_id" x-model="form.section_id" class="ui-input" :disabled="!form.year_level_id || sectionsLoading" required>
                        <option value="" x-text="sectionsLoading ? 'Loading sections...' : (sections.length === 0 && form.year_level_id ? 'No sections available' : 'Select section')"></option>
                        <template x-for="section in sections" :key="section.id">
                            <option :value="section.id" x-text="section.name || section.code"></option>
                        </template>
                    </select>
                    <p class="ui-help" x-show="!sectionsLoading && sections.length === 0 && form.year_level_id">No sections are available for the selected program and year level. Please contact the administrator.</p>
                    <p class="ui-error" x-show="errors.section_id" x-text="errors.section_id" role="alert"></p>
                </x-ui.field>
            </div>

            <div x-show="step === 3" x-cloak class="ui-card ui-card-pad space-y-5">
                <h2 class="text-lg font-semibold">Account Security</h2>

                <x-ui.field label="Password *" for="password">
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" x-model="form.password" class="ui-input pr-10" autocomplete="new-password" required>
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

                <x-ui.field label="Confirm Password *" for="password_confirmation">
                    <div class="relative">
                        <input :type="showConfirmPassword ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" x-model="form.password_confirmation" class="ui-input pr-10" autocomplete="new-password" required>
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-muted hover:text-ink" @click="showConfirmPassword = !showConfirmPassword">
                            <x-icon name="eye" :size="18" x-show="!showConfirmPassword" />
                            <x-icon name="eye-off" :size="18" x-show="showConfirmPassword" x-cloak />
                        </button>
                    </div>
                    <p class="ui-error" x-show="errors.password_confirmation" x-text="errors.password_confirmation" role="alert"></p>
                </x-ui.field>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" class="btn-secondary" x-show="step > 1" @click="back">Back</button>
                <div class="ms-auto flex flex-wrap gap-2">
                    <button type="button" class="btn-primary" x-show="step < 3" @click="next">Continue</button>
                    <button type="submit" class="btn-primary" x-show="step === 3" :disabled="submitting">
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
