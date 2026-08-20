<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreInstructorRequest;
use App\Http\Requests\UpdateInstructorRequest;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InstructorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $instructors = Instructor::query()
            ->with(['user', 'department'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('employee_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('department', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('pages.instructors.index', compact('instructors', 'search'));
    }

    public function create(): View
    {
        return view('pages.instructors.create', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInstructorRequest $request): RedirectResponse
    {
        $instructor = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $name = trim($data['first_name'].' '.$data['last_name']);

            $user = User::create([
                'name' => $name,
                'username' => $this->uniqueUsername($data['employee_id'], $data['email']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'],
                'email_verified_at' => now(),
            ]);

            $user->assignRole(UserRole::Instructor->value);

            return Instructor::create([
                'user_id' => $user->id,
                'employee_id' => $data['employee_id'],
                'department_id' => $data['department_id'],
                'is_active' => $data['is_active'],
            ]);
        });

        return redirect()
            ->route('instructors.show', $instructor)
            ->with('status', 'Instructor added successfully.');
    }

    public function show(Instructor $instructor): View
    {
        $instructor->load(['user', 'department']);

        return view('pages.instructors.show', compact('instructor'));
    }

    public function edit(Instructor $instructor): View
    {
        $instructor->load('user');

        return view('pages.instructors.edit', [
            'instructor' => $instructor,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateInstructorRequest $request, Instructor $instructor): RedirectResponse
    {
        DB::transaction(function () use ($request, $instructor) {
            $data = $request->validated();
            $name = trim($data['first_name'].' '.$data['last_name']);
            $user = $instructor->user;

            $payload = [
                'name' => $name,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'],
            ];

            if (filled($data['password'] ?? null)) {
                $payload['password'] = $data['password'];
            }

            $user->fill($payload)->save();

            if (! $user->hasRole(UserRole::Instructor->value)) {
                $user->assignRole(UserRole::Instructor->value);
            }

            $instructor->update([
                'employee_id' => $data['employee_id'],
                'department_id' => $data['department_id'],
                'is_active' => $data['is_active'],
            ]);
        });

        return redirect()
            ->route('instructors.show', $instructor)
            ->with('status', 'Instructor updated successfully.');
    }

    public function destroy(Instructor $instructor): RedirectResponse
    {
        DB::transaction(function () use ($instructor) {
            $instructor->update(['is_active' => false]);
            $instructor->user?->update(['is_active' => false]);
        });

        return redirect()
            ->route('instructors.index')
            ->with('status', 'Instructor deactivated.');
    }

    protected function uniqueUsername(string $employeeId, string $email): string
    {
        $base = Str::lower(preg_replace('/[^a-z0-9._-]+/i', '', str_replace(' ', '.', $employeeId)))
            ?: Str::before($email, '@');
        $base = $base !== '' ? $base : 'instructor';

        $username = $base;
        $suffix = 1;

        while (User::withTrashed()->where('username', $username)->exists()) {
            $username = $base.$suffix;
            $suffix++;
        }

        return $username;
    }
}
