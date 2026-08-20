@php
    $section = $section ?? null;
@endphp

<div
    class="grid gap-5 sm:grid-cols-2"
    x-data="{
        programId: @js(old('program_id', $section?->program_id)),
        yearLevelId: @js(old('year_level_id', $section?->year_level_id)),
        academicYearId: @js(old('academic_year_id', $section?->academic_year_id)),
        semesterId: @js(old('semester_id', $section?->semester_id)),
        yearLevels: @js($yearLevels),
        semesters: @js($semesters),
        get filteredYearLevels() {
            return this.yearLevels.filter((item) => String(item.program_id) === String(this.programId));
        },
        get filteredSemesters() {
            return this.semesters.filter((item) => String(item.academic_year_id) === String(this.academicYearId));
        }
    }"
>
    <x-ui.field label="Section name" for="name" help="Example: BSIS 1A" :error="$errors->first('name')">
        <x-text-input id="name" name="name" type="text" value="{{ old('name', $section?->name) }}" required />
    </x-ui.field>

    <x-ui.field label="Code" for="code" help="Optional short code, such as BSIS-1A." :error="$errors->first('code')">
        <x-text-input id="code" name="code" type="text" value="{{ old('code', $section?->code) }}" />
    </x-ui.field>

    <x-ui.field label="Program" for="program_id" :error="$errors->first('program_id')">
        <select id="program_id" name="program_id" class="ui-input" required x-model="programId" @change="yearLevelId = ''">
            <option value="">Select program</option>
            @foreach ($programs as $program)
                <option value="{{ $program->id }}">{{ $program->code }} — {{ $program->name }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Year level" for="year_level_id" :error="$errors->first('year_level_id')">
        <select id="year_level_id" name="year_level_id" class="ui-input" required x-model="yearLevelId">
            <option value="">Select year level</option>
            <template x-for="level in filteredYearLevels" :key="level.id">
                <option :value="level.id" x-text="level.name" :selected="String(yearLevelId) === String(level.id)"></option>
            </template>
        </select>
        <p class="ui-help" x-show="programId && filteredYearLevels.length === 0">No year levels are available for this program yet.</p>
    </x-ui.field>

    <x-ui.field label="Academic year" for="academic_year_id" :error="$errors->first('academic_year_id')">
        <select id="academic_year_id" name="academic_year_id" class="ui-input" required x-model="academicYearId" @change="semesterId = ''">
            <option value="">Select academic year</option>
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Semester" for="semester_id" :error="$errors->first('semester_id')">
        <select id="semester_id" name="semester_id" class="ui-input" required x-model="semesterId">
            <option value="">Select semester</option>
            <template x-for="semester in filteredSemesters" :key="semester.id">
                <option :value="semester.id" x-text="semester.name" :selected="String(semesterId) === String(semester.id)"></option>
            </template>
        </select>
        <p class="ui-help" x-show="academicYearId && filteredSemesters.length === 0">No semesters are available for this academic year yet.</p>
    </x-ui.field>

    <label class="flex items-center gap-2 text-sm sm:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-line text-navy-800 focus:ring-navy-700" @checked((string) old('is_active', $section?->is_active ?? true) === '1' || old('is_active', $section?->is_active ?? true) === true)>
        Active section
    </label>
</div>
