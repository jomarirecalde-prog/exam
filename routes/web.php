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
        Route::post('/examinations/preview-questions-csv', [\App\Http\Controllers\ExaminationController::class, 'previewQuestionsCsv'])->name('examinations.preview-questions-csv');
        Route::post('/examinations/import-questions', [\App\Http\Controllers\ExaminationController::class, 'importQuestions'])->name('examinations.import-questions');
        Route::post('/examinations/questions/csv-error-report', [\App\Http\Controllers\ExaminationController::class, 'questionCsvErrorReport'])->name('examinations.question-csv-error-report');
        Route::get('/examinations/questions/csv-template', [\App\Http\Controllers\ExaminationController::class, 'questionCsvTemplate'])->name('examinations.question-csv-template');
        Route::get('/examinations/{examination}/edit', [\App\Http\Controllers\ExaminationController::class, 'edit'])->name('examinations.edit');
        Route::put('/examinations/{examination}', [\App\Http\Controllers\ExaminationController::class, 'update'])->name('examinations.update');
    });
    Route::get('/examinations/{examination}/take', [PlatformController::class, 'take'])->name('examinations.take');
    Route::get('/examinations/{examination}/result', [PlatformController::class, 'result'])->name('examinations.result');

    Route::middleware('role:superadmin,admin,instructor')->group(function () {
        Route::get('/question-bank', [\App\Http\Controllers\QuestionController::class, 'index'])->name('questions.index');
        Route::get('/question-bank/csv-template', [\App\Http\Controllers\QuestionController::class, 'csvTemplate'])->name('questions.csv-template');
        Route::post('/question-bank/preview-csv', [\App\Http\Controllers\QuestionController::class, 'previewCsv'])->name('questions.preview-csv');
        Route::post('/question-bank/import-csv', [\App\Http\Controllers\QuestionController::class, 'importCsv'])->name('questions.import-csv');
        Route::get('/question-bank/export-csv', [\App\Http\Controllers\QuestionController::class, 'exportCsv'])->name('questions.export-csv');
        Route::post('/question-bank/error-report', [\App\Http\Controllers\QuestionController::class, 'errorReport'])->name('questions.error-report');
    });
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
