@php
    $subject = $subject ?? null;
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Code" for="code" help="A short unique code, such as IS101." :error="$errors->first('code')">
        <x-text-input id="code" name="code" type="text" value="{{ old('code', $subject?->code) }}" required />
    </x-ui.field>

    <x-ui.field label="Units" for="units" :error="$errors->first('units')">
        <x-text-input id="units" name="units" type="number" min="1" max="12" value="{{ old('units', $subject?->units ?? 3) }}" required />
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Subject name" for="name" :error="$errors->first('name')">
        <x-text-input id="name" name="name" type="text" value="{{ old('name', $subject?->name) }}" required />
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Department" for="department_id" help="Optional. The department that owns this subject." :error="$errors->first('department_id')">
        <select id="department_id" name="department_id" class="ui-input">
            <option value="">No department</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $subject?->department_id) == $department->id)>
                    {{ $department->code }} — {{ $department->name }}
                </option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Description" for="description" help="Optional. Shown on the subject profile." :error="$errors->first('description')">
        <textarea id="description" name="description" class="ui-input min-h-24" rows="3">{{ old('description', $subject?->description) }}</textarea>
    </x-ui.field>

    <label class="flex items-center gap-2 text-sm sm:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-line text-navy-800 focus:ring-navy-700" @checked((string) old('is_active', $subject?->is_active ?? true) === '1' || old('is_active', $subject?->is_active ?? true) === true)>
        Active subject
    </label>
</div>

@if (! $subject && isset($instructors))
    <div
        class="grid gap-5 border-t border-line pt-6 sm:grid-cols-2"
        x-data="{
            departmentId: @js(old('department_id')),
            instructorId: @js(old('instructor_id')),
            academicYearId: @js(old('academic_year_id', $defaultAcademicYearId)),
            semesterId: @js(old('semester_id', $defaultSemesterId)),
            sectionId: @js(old('section_id')),
            instructors: @js($instructors->map(fn ($instructor) => [
                'id' => $instructor->id,
                'name' => $instructor->user?->fullName() ?: $instructor->employee_id,
                'employee_id' => $instructor->employee_id,
                'department_id' => $instructor->department_id,
            ])),
            semesters: @js($semesters),
            sections: @js($sections->map(fn ($section) => [
                'id' => $section->id,
                'label' => trim(($section->code ?: $section->name) . ($section->code && $section->name !== $section->code ? ' — ' . $section->name : '')),
                'academic_year_id' => $section->academic_year_id,
                'semester_id' => $section->semester_id,
            ])),
            get filteredInstructors() {
                if (! this.departmentId) {
                    return this.instructors;
                }

                return this.instructors.filter((instructor) => ! instructor.department_id || String(instructor.department_id) === String(this.departmentId));
            },
            get filteredSemesters() {
                return this.semesters.filter((semester) => String(semester.academic_year_id) === String(this.academicYearId));
            },
            get filteredSections() {
                return this.sections.filter((section) => {
                    if (this.academicYearId && String(section.academic_year_id) !== String(this.academicYearId)) {
                        return false;
                    }

                    if (this.semesterId && String(section.semester_id) !== String(this.semesterId)) {
                        return false;
                    }

                    return true;
                });
            },
            onDepartmentChange() {
                if (this.instructorId && ! this.filteredInstructors.some((instructor) => String(instructor.id) === String(this.instructorId))) {
                    this.instructorId = '';
                }
            },
            onAcademicYearChange() {
                if (this.semesterId && ! this.filteredSemesters.some((semester) => String(semester.id) === String(this.semesterId))) {
                    this.semesterId = '';
                }

                this.onTermChange();
            },
            onTermChange() {
                if (this.sectionId && ! this.filteredSections.some((section) => String(section.id) === String(this.sectionId))) {
                    this.sectionId = '';
                }
            }
        }"
        x-init="
            $watch('departmentId', () => onDepartmentChange());
            document.getElementById('department_id')?.addEventListener('change', (event) => { departmentId = event.target.value; });
        "
    >
        <div class="sm:col-span-2">
            <h2 class="ui-section">Instructor assignment</h2>
            <p class="mt-1 text-sm text-muted">Optionally assign an instructor to teach this subject for a specific term.</p>
        </div>

        <x-ui.field class="sm:col-span-2" label="Instructor" for="instructor_id" help="Optional. Limits exam section access for the selected instructor." :error="$errors->first('instructor_id')">
            <select id="instructor_id" name="instructor_id" class="ui-input" x-model="instructorId">
                <option value="">No instructor</option>
                <template x-for="instructor in filteredInstructors" :key="instructor.id">
                    <option :value="instructor.id" x-text="`${instructor.name} (${instructor.employee_id})`" :selected="String(instructorId) === String(instructor.id)"></option>
                </template>
            </select>
            <p class="ui-help" x-show="departmentId && filteredInstructors.length === 0">No instructors are available for the selected department.</p>
        </x-ui.field>

        <x-ui.field label="Academic year" for="academic_year_id" :error="$errors->first('academic_year_id')">
            <select id="academic_year_id" name="academic_year_id" class="ui-input" x-model="academicYearId" @change="onAcademicYearChange()" :disabled="! instructorId">
                <option value="">Select academic year</option>
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field label="Semester" for="semester_id" :error="$errors->first('semester_id')">
            <select id="semester_id" name="semester_id" class="ui-input" x-model="semesterId" @change="onTermChange()" :disabled="! instructorId">
                <option value="">Select semester</option>
                <template x-for="semester in filteredSemesters" :key="semester.id">
                    <option :value="semester.id" x-text="semester.name" :selected="String(semesterId) === String(semester.id)"></option>
                </template>
            </select>
            <p class="ui-help" x-show="instructorId && academicYearId && filteredSemesters.length === 0">No semesters are available for this academic year.</p>
        </x-ui.field>

        <x-ui.field class="sm:col-span-2" label="Section" for="section_id" help="Optional. Leave blank to allow all sections for this subject and term." :error="$errors->first('section_id')">
            <select id="section_id" name="section_id" class="ui-input" x-model="sectionId" :disabled="! instructorId">
                <option value="">All sections</option>
                <template x-for="section in filteredSections" :key="section.id">
                    <option :value="section.id" x-text="section.label" :selected="String(sectionId) === String(section.id)"></option>
                </template>
            </select>
        </x-ui.field>
    </div>
@endif
