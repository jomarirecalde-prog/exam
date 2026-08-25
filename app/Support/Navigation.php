<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    public static function greeting(?User $user = null): string
    {
        $hour = now()->hour;
        $name = static::firstName($user);

        $hello = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return $name ? "{$hello}, {$name}" : $hello;
    }

    public static function firstName(?User $user = null): string
    {
        $user ??= auth()->user();

        if (! $user) {
            return '';
        }

        if (filled($user->first_name)) {
            return $user->first_name;
        }

        return explode(' ', (string) $user->name)[0];
    }

    public static function breadcrumbs(): array
    {
        $name = Route::currentRouteName();

        $map = [
            'dashboard' => [['Dashboard']],
            'profile' => [['Dashboard', 'dashboard'], ['Profile']],
            'students.index' => [['Academic'], ['Students']],
            'students.deleted.index' => [['Academic'], ['Students', 'students.index'], ['Deleted']],
            'students.show' => [['Academic'], ['Students', 'students.index'], ['Profile']],
            'students.edit' => [['Academic'], ['Students', 'students.index'], ['Edit']],
            'admin.student-registrations.index' => [['Academic'], ['Student Registrations']],
            'admin.student-registrations.show' => [['Academic'], ['Student Registrations', 'admin.student-registrations.index'], ['Details']],
            'admin.student-subject-requests.index' => [['Academic'], ['Subject Change Requests']],
            'admin.student-subject-requests.show' => [['Academic'], ['Subject Change Requests', 'admin.student-subject-requests.index'], ['Details']],
            'instructors.index' => [['Academic'], ['Instructors']],
            'instructors.create' => [['Academic'], ['Instructors', 'instructors.index'], ['Add Instructor']],
            'instructors.show' => [['Academic'], ['Instructors', 'instructors.index'], ['Profile']],
            'instructors.edit' => [['Academic'], ['Instructors', 'instructors.index'], ['Edit']],
            'departments.index' => [['Academic'], ['Departments']],
            'departments.create' => [['Academic'], ['Departments', 'departments.index'], ['Create Department']],
            'departments.show' => [['Academic'], ['Departments', 'departments.index'], ['Details']],
            'departments.edit' => [['Academic'], ['Departments', 'departments.index'], ['Edit']],
            'programs.index' => [['Academic'], ['Programs']],
            'programs.create' => [['Academic'], ['Programs', 'programs.index'], ['Create Program']],
            'programs.show' => [['Academic'], ['Programs', 'programs.index'], ['Details']],
            'programs.edit' => [['Academic'], ['Programs', 'programs.index'], ['Edit']],
            'sections.index' => [['Academic'], ['Sections']],
            'sections.create' => [['Academic'], ['Sections', 'sections.index'], ['Create Section']],
            'sections.show' => [['Academic'], ['Sections', 'sections.index'], ['Details']],
            'sections.edit' => [['Academic'], ['Sections', 'sections.index'], ['Edit']],
            'subjects.index' => [['Academic'], ['Subjects']],
            'subjects.create' => [['Academic'], ['Subjects', 'subjects.index'], ['Create Subject']],
            'subjects.show' => [['Academic'], ['Subjects', 'subjects.index'], ['Details']],
            'subjects.edit' => [['Academic'], ['Subjects', 'subjects.index'], ['Edit']],
            'instructor.teaching.index' => [['My Classes']],
            'instructor.teaching.show' => [['My Classes', 'instructor.teaching.index'], ['Subject']],
            'instructor.teaching.section' => [['My Classes', 'instructor.teaching.index'], ['Section Roster']],
            'student.enrollment.index' => [['My Subjects']],
            'student.enrollment.show' => [['My Subjects', 'student.enrollment.index'], ['Subject Details']],
            'examinations.index' => [['Examinations'], ['All Examinations']],
            'examinations.create' => [['Examinations', 'examinations.index'], ['Create Examination']],
            'examinations.edit' => [['Examinations', 'examinations.index'], ['Edit Examination']],
            'questions.index' => [['Examinations'], ['Question Bank']],
            'results.index' => [['Results'], ['Examination Results']],
            'reports.index' => [['Results'], ['Reports']],
            'monitoring.index' => [['Monitoring']],
            'monitoring.show' => [['Monitoring', 'monitoring.index'], ['Monitor Examination']],
            'sync.index' => [['Synchronization']],
            'offline.sync' => [['Sync Status']],
            'audit.index' => [['Audit Logs']],
            'settings.index' => [['Settings']],
            'examinations.take' => [['Examination']],
            'examinations.result' => [['Results']],
        ];

        return $map[$name] ?? [['Dashboard', 'dashboard']];
    }

    public static function items(?User $user = null): array
    {
        $user ??= auth()->user();

        if (! $user) {
            return [];
        }

        if ($user->hasRole('student')) {
            return [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
                ['label' => 'My Subjects', 'route' => 'student.enrollment.index', 'icon' => 'book-open'],
                ['label' => 'Examinations', 'route' => 'examinations.index', 'icon' => 'clipboard-list'],
                ['label' => 'Results', 'route' => 'results.index', 'icon' => 'bar-chart-3'],
                ['label' => 'Settings', 'route' => 'settings.index', 'icon' => 'settings'],
            ];
        }

        $groups = [
            [
                'label' => null,
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
                ],
            ],
        ];

        if ($user->hasRole('instructor')) {
            $groups[0]['items'][] = ['label' => 'My Classes', 'route' => 'instructor.teaching.index', 'icon' => 'book-open'];
        }

        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            $academicItems = [
                ['label' => 'Students', 'route' => 'students.index', 'icon' => 'graduation-cap'],
                ['label' => 'Student Registrations', 'route' => 'admin.student-registrations.index', 'icon' => 'user-plus'],
                ['label' => 'Subject Change Requests', 'route' => 'admin.student-subject-requests.index', 'icon' => 'file-edit'],
                ['label' => 'Instructors', 'route' => 'instructors.index', 'icon' => 'users'],
                ['label' => 'Departments', 'route' => 'departments.index', 'icon' => 'building-2'],
                ['label' => 'Programs', 'route' => 'programs.index', 'icon' => 'library'],
                ['label' => 'Sections', 'route' => 'sections.index', 'icon' => 'layers'],
                ['label' => 'Subjects', 'route' => 'subjects.index', 'icon' => 'book-open'],
            ];

            if ($user->hasRole('superadmin')) {
                array_splice($academicItems, 1, 0, [[
                    'label' => 'Deleted Students',
                    'route' => 'students.deleted.index',
                    'icon' => 'archive',
                ]]);
            }

            $groups[] = [
                'label' => 'Academic',
                'items' => $academicItems,
            ];
        }

        $groups[] = [
            'label' => $user->hasAnyRole(['superadmin', 'admin']) ? null : 'Examinations',
            'items' => array_values(array_filter([
                ['label' => 'All Examinations', 'route' => 'examinations.index', 'icon' => 'clipboard-list'],
                $user->hasRole('instructor')
                    ? ['label' => 'Create Examination', 'route' => 'examinations.create', 'icon' => 'plus']
                    : null,
                $user->hasAnyRole(['superadmin', 'admin'])
                    ? null
                    : ['label' => 'Question Bank', 'route' => 'questions.index', 'icon' => 'file-question'],
            ])),
        ];

        $groups[] = [
            'label' => 'Results',
            'items' => [
                ['label' => 'Examination Results', 'route' => 'results.index', 'icon' => 'bar-chart-3'],
                ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'file-text'],
            ],
        ];

        $groups[] = [
            'label' => null,
            'items' => array_values(array_filter([
                $user->hasAnyRole(['superadmin', 'admin', 'instructor'])
                    ? ['label' => 'Monitoring', 'route' => 'monitoring.index', 'icon' => 'activity']
                    : null,
                $user->hasAnyRole(['superadmin', 'admin'])
                    ? ['label' => 'Synchronization', 'route' => 'sync.index', 'icon' => 'refresh-cw']
                    : null,
                $user->hasRole('student')
                    ? ['label' => 'Sync Status', 'route' => 'offline.sync', 'icon' => 'refresh-cw']
                    : null,
                $user->hasAnyRole(['superadmin', 'admin'])
                    ? ['label' => 'Audit Logs', 'route' => 'audit.index', 'icon' => 'scroll']
                    : null,
                ['label' => 'Settings', 'route' => 'settings.index', 'icon' => 'settings'],
            ])),
        ];

        return $groups;
    }

    public static function isActive(string $route): bool
    {
        if ($route === 'dashboard') {
            return request()->routeIs('dashboard');
        }

        return request()->routeIs($route) || request()->routeIs(str_replace('.index', '.*', $route));
    }

    public static function flattened(?User $user = null): array
    {
        $items = static::items($user);

        if ($items === [] || ! isset($items[0]['items'])) {
            return $items;
        }

        return collect($items)->flatMap(fn (array $group) => $group['items'] ?? [])->all();
    }
}
