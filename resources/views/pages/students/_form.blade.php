@php
    $student = $student ?? null;
    $user = $student?->user;
    $selectedDepartmentId = (int) old('department_id', $student?->program?->department_id);
    $selectedProgramId = (int) old('program_id', $student?->program_id);
    $selectedYearLevelId = (int) old('year_level_id', $student?->year_level_id);
    $selectedSectionId = (int) old('section_id', $student?->section_id);
@endphp

<div
    x-data="{
        departmentId: @js($selectedDepartmentId),
        programId: @js($selectedProgramId),
        yearLevelId: @js($selectedYearLevelId),
        sectionId: @js($selectedSectionId),
        programs: @js($programs->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values()),
        yearLevels: @js($yearLevels->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values()),
        sections: @js($sections->map(fn ($item) => ['id' => $item->id, 'name' => $item->displayName()])->values()),
        programsUrl: @js(route('student-registration.programs')),
        yearLevelsUrl: @js(route('student-registration.year-levels')),
        sectionsUrl: @js(route('student-registration.sections')),
        async loadPrograms() {
            if (! this.departmentId) {
                this.programs = [];
                this.programId = '';
                this.yearLevels = [];
                this.yearLevelId = '';
                this.sections = [];
                this.sectionId = '';
                return;
            }

            const response = await fetch(`${this.programsUrl}?department_id=${this.departmentId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            this.programs = data.programs || [];
            if (! this.programs.some((item) => item.id === this.programId)) {
                this.programId = this.programs[0]?.id ?? '';
            }
            await this.loadYearLevels();
        },
        async loadYearLevels() {
            if (! this.programId) {
                this.yearLevels = [];
                this.yearLevelId = '';
                this.sections = [];
                this.sectionId = '';
                return;
            }

            const response = await fetch(`${this.yearLevelsUrl}?program_id=${this.programId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            this.yearLevels = data.year_levels || [];
            if (! this.yearLevels.some((item) => item.id === this.yearLevelId)) {
                this.yearLevelId = this.yearLevels[0]?.id ?? '';
            }
            await this.loadSections();
        },
        async loadSections() {
            if (! this.programId || ! this.yearLevelId) {
                this.sections = [];
                this.sectionId = '';
                return;
            }

            const params = new URLSearchParams({
                program_id: this.programId,
                year_level_id: this.yearLevelId,
            });
            const response = await fetch(`${this.sectionsUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            this.sections = data.sections || [];
            if (! this.sections.some((item) => item.id === this.sectionId)) {
                this.sectionId = this.sections[0]?.id ?? '';
            }
        },
    }"
    class="space-y-8"
>
    <div>
        <h2 class="ui-section">Personal Information</h2>
        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <x-ui.field label="First name" for="first_name" :error="$errors->first('first_name')">
                <x-text-input id="first_name" name="first_name" type="text" value="{{ old('first_name', $user?->first_name) }}" required autocomplete="given-name" />
            </x-ui.field>

            <x-ui.field label="Middle name" for="middle_name" :error="$errors->first('middle_name')">
                <x-text-input id="middle_name" name="middle_name" type="text" value="{{ old('middle_name', $user?->middle_name) }}" autocomplete="additional-name" />
            </x-ui.field>

            <x-ui.field label="Last name" for="last_name" :error="$errors->first('last_name')">
                <x-text-input id="last_name" name="last_name" type="text" value="{{ old('last_name', $user?->last_name) }}" required autocomplete="family-name" />
            </x-ui.field>

            <x-ui.field label="Suffix" for="suffix" :error="$errors->first('suffix')">
                <x-text-input id="suffix" name="suffix" type="text" value="{{ old('suffix', $user?->suffix) }}" />
            </x-ui.field>

            <x-ui.field label="Sex / Gender" for="sex" :error="$errors->first('sex')">
                <select id="sex" name="sex" class="ui-input">
                    <option value="">Prefer not to say</option>
                    <option value="male" @selected(old('sex', $student?->sex) === 'male')>Male</option>
                    <option value="female" @selected(old('sex', $student?->sex) === 'female')>Female</option>
                    <option value="other" @selected(old('sex', $student?->sex) === 'other')>Other</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Date of birth" for="date_of_birth" :error="$errors->first('date_of_birth')">
                <x-text-input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $student?->date_of_birth?->format('Y-m-d')) }}" />
            </x-ui.field>

            <x-ui.field label="Contact number" for="phone" :error="$errors->first('phone')">
                <x-text-input id="phone" name="phone" type="tel" value="{{ old('phone', $student?->phone) }}" required autocomplete="tel" />
            </x-ui.field>

            <x-ui.field label="Email" for="email" help="Used to sign in to the examination platform." :error="$errors->first('email')">
                <x-text-input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required autocomplete="username" />
            </x-ui.field>

            <x-ui.field class="sm:col-span-2" label="Home address" for="home_address" :error="$errors->first('home_address')">
                <textarea id="home_address" name="home_address" rows="2" class="ui-input">{{ old('home_address', $student?->home_address) }}</textarea>
            </x-ui.field>
        </div>
    </div>

    <div>
        <h2 class="ui-section">Academic Information</h2>
        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <x-ui.field label="Student ID" for="student_id" :error="$errors->first('student_id')">
                <x-text-input id="student_id" name="student_id" type="text" value="{{ old('student_id', $student?->student_id) }}" required />
            </x-ui.field>

            <x-ui.field label="Department" for="department_id" :error="$errors->first('department_id')">
                <select
                    id="department_id"
                    name="department_id"
                    class="ui-input"
                    required
                    x-model.number="departmentId"
                    @change="loadPrograms()"
                >
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected($selectedDepartmentId === $department->id)>
                            {{ $department->name }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Program" for="program_id" :error="$errors->first('program_id')">
                <select
                    id="program_id"
                    name="program_id"
                    class="ui-input"
                    required
                    x-model.number="programId"
                    @change="loadYearLevels()"
                >
                    <option value="">Select program</option>
                    <template x-for="program in programs" :key="program.id">
                        <option :value="program.id" x-text="program.name" :selected="program.id === programId"></option>
                    </template>
                </select>
            </x-ui.field>

            <x-ui.field label="Year level" for="year_level_id" :error="$errors->first('year_level_id')">
                <select
                    id="year_level_id"
                    name="year_level_id"
                    class="ui-input"
                    required
                    x-model.number="yearLevelId"
                    @change="loadSections()"
                >
                    <option value="">Select year level</option>
                    <template x-for="yearLevel in yearLevels" :key="yearLevel.id">
                        <option :value="yearLevel.id" x-text="yearLevel.name" :selected="yearLevel.id === yearLevelId"></option>
                    </template>
                </select>
            </x-ui.field>

            <x-ui.field class="sm:col-span-2" label="Section" for="section_id" :error="$errors->first('section_id')">
                <select id="section_id" name="section_id" class="ui-input" required x-model.number="sectionId">
                    <option value="">Select section</option>
                    <template x-for="section in sections" :key="section.id">
                        <option :value="section.id" x-text="section.name || section.code" :selected="section.id === sectionId"></option>
                    </template>
                </select>
            </x-ui.field>
        </div>
    </div>

    <div>
        <h2 class="ui-section">Account</h2>
        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <x-ui.field label="New password" for="password" help="Leave blank to keep the current password." :error="$errors->first('password')">
                <x-text-input id="password" name="password" type="password" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.field label="Confirm password" for="password_confirmation" :error="$errors->first('password_confirmation')">
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
            </x-ui.field>

            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="rounded border-line text-navy-800 focus:ring-navy-700" @checked((string) old('is_active', $student?->is_active ?? true) === '1' || old('is_active', $student?->is_active ?? true) === true)>
                Active account
            </label>
        </div>
    </div>
</div>
