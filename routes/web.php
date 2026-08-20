<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/students', [PlatformController::class, 'students'])->name('students.index');
    Route::middleware('role:superadmin,admin')->group(function () {
        Route::resource('instructors', \App\Http\Controllers\InstructorController::class);
        Route::resource('departments', \App\Http\Controllers\DepartmentController::class);
        Route::resource('sections', \App\Http\Controllers\SectionController::class);
        Route::resource('subjects', \App\Http\Controllers\SubjectController::class);
        Route::resource('programs', \App\Http\Controllers\ProgramController::class);
    });

    Route::get('/examinations', [PlatformController::class, 'examinations'])->name('examinations.index');
    Route::middleware('role:superadmin,admin,instructor')->group(function () {
        Route::get('/examinations/create', [\App\Http\Controllers\ExaminationController::class, 'create'])->name('examinations.create');
        Route::post('/examinations', [\App\Http\Controllers\ExaminationController::class, 'store'])->name('examinations.store');
        Route::get('/examinations/available-sections', [\App\Http\Controllers\ExaminationController::class, 'availableSections'])->name('examinations.sections');
        Route::get('/examinations/{examination}/edit', [\App\Http\Controllers\ExaminationController::class, 'edit'])->name('examinations.edit');
        Route::put('/examinations/{examination}', [\App\Http\Controllers\ExaminationController::class, 'update'])->name('examinations.update');
    });
    Route::get('/examinations/{examination}/take', [PlatformController::class, 'take'])->name('examinations.take');
    Route::get('/examinations/{examination}/result', [PlatformController::class, 'result'])->name('examinations.result');

    Route::get('/question-bank', [PlatformController::class, 'questions'])->name('questions.index');
    Route::get('/schedules', [PlatformController::class, 'schedules'])->name('schedules.index');

    Route::get('/results', [PlatformController::class, 'results'])->name('results.index');
    Route::get('/reports', [PlatformController::class, 'reports'])->name('reports.index');
    Route::get('/monitoring', [PlatformController::class, 'monitoring'])->name('monitoring.index');
    Route::get('/synchronization', [PlatformController::class, 'sync'])->name('sync.index');
    Route::get('/audit-logs', [PlatformController::class, 'audit'])->name('audit.index');
    Route::get('/settings', [PlatformController::class, 'settings'])->name('settings.index');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
