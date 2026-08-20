@php
    $instructor = $instructor ?? null;
    $user = $instructor?->user;
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="First name" for="first_name" :error="$errors->first('first_name')">
        <x-text-input id="first_name" name="first_name" type="text" value="{{ old('first_name', $user?->first_name) }}" required autocomplete="given-name" />
    </x-ui.field>

    <x-ui.field label="Last name" for="last_name" :error="$errors->first('last_name')">
        <x-text-input id="last_name" name="last_name" type="text" value="{{ old('last_name', $user?->last_name) }}" required autocomplete="family-name" />
    </x-ui.field>

    <x-ui.field label="Email" for="email" help="Used to sign in to the examination platform." :error="$errors->first('email')">
        <x-text-input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required autocomplete="username" />
    </x-ui.field>

    <x-ui.field label="Employee ID" for="employee_id" help="A unique identifier for this faculty member." :error="$errors->first('employee_id')">
        <x-text-input id="employee_id" name="employee_id" type="text" value="{{ old('employee_id', $instructor?->employee_id) }}" required />
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Department" for="department_id" :error="$errors->first('department_id')">
        <select id="department_id" name="department_id" class="ui-input" required>
            <option value="">Select department</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $instructor?->department_id) == $department->id)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="{{ $instructor ? 'New password' : 'Password' }}" for="password" :help="$instructor ? 'Leave blank to keep the current password.' : 'Share this password with the instructor so they can sign in.'" :error="$errors->first('password')">
        <x-text-input id="password" name="password" type="password" autocomplete="new-password" :required="! $instructor" />
    </x-ui.field>

    <x-ui.field label="Confirm password" for="password_confirmation" :error="$errors->first('password_confirmation')">
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" :required="! $instructor" />
    </x-ui.field>

    <label class="flex items-center gap-2 text-sm sm:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-line text-navy-800 focus:ring-navy-700" @checked((string) old('is_active', $instructor?->is_active ?? true) === '1' || old('is_active', $instructor?->is_active ?? true) === true)>
        Active account
    </label>
</div>
