@php
    $program = $program ?? null;
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Code" for="code" help="A short unique code, such as BSIS." :error="$errors->first('code')">
        <x-text-input id="code" name="code" type="text" value="{{ old('code', $program?->code) }}" required />
    </x-ui.field>

    <x-ui.field label="Program name" for="name" :error="$errors->first('name')">
        <x-text-input id="name" name="name" type="text" value="{{ old('name', $program?->name) }}" required />
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Department" for="department_id" :error="$errors->first('department_id')">
        <select id="department_id" name="department_id" class="ui-input" required>
            <option value="">Select department</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $program?->department_id) == $department->id)>
                    {{ $department->code }} — {{ $department->name }}
                </option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Description" for="description" help="Optional. Shown on the program profile." :error="$errors->first('description')">
        <textarea id="description" name="description" class="ui-input min-h-24" rows="3">{{ old('description', $program?->description) }}</textarea>
    </x-ui.field>

    <label class="flex items-center gap-2 text-sm sm:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-line text-navy-800 focus:ring-navy-700" @checked((string) old('is_active', $program?->is_active ?? true) === '1' || old('is_active', $program?->is_active ?? true) === true)>
        Active program
    </label>
</div>
