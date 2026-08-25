<?php

use App\Http\Controllers\AdminStudentRegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\StudentRegistrationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register/student', [StudentRegistrationController::class, 'create'])->name('student-registration.create');
    Route::post('/register/student', [StudentRegistrationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('student-registration.store');
    Route::get('/register/student/confirmation/{student}', [StudentRegistrationController::class, 'confirmation'])
        ->name('student-registration.confirmation');

    Route::get('/register/student/lookup/programs', [StudentRegistrationController::class, 'programs'])
        ->name('student-registration.programs');
    Route::get('/register/student/lookup/year-levels', [StudentRegistrationController::class, 'yearLevels'])
        ->name('student-registration.year-levels');
    Route::get('/register/student/lookup/sections', [StudentRegistrationController::class, 'sections'])
        ->name('student-registration.sections');
    Route::get('/register/student/lookup/subjects', [StudentRegistrationController::class, 'subjects'])
        ->name('student-registration.subjects');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:superadmin,admin')->group(function () {
        Route::resource('students', \App\Http\Controllers\StudentController::class)->only(['index', 'show', 'edit', 'update', 'destroy']);
        Route::get('/students/deleted/list', [\App\Http\Controllers\StudentController::class, 'deletedIndex'])
            ->name('students.deleted.index');
        Route::post('/students/deleted/{studentId}/restore', [\App\Http\Controllers\StudentController::class, 'restore'])
            ->name('students.restore');
        Route::delete('/students/deleted/{studentId}/force', [\App\Http\Controllers\StudentController::class, 'forceDestroy'])
            ->name('students.force-destroy');
        Route::get('/admin/student-registrations', [AdminStudentRegistrationController::class, 'index'])
            ->name('admin.student-registrations.index');
        Route::get('/admin/student-registrations/{student}', [AdminStudentRegistrationController::class, 'show'])
            ->name('admin.student-registrations.show');
        Route::post('/admin/student-registrations/{student}/approve', [AdminStudentRegistrationController::class, 'approve'])
            ->name('admin.student-registrations.approve');
        Route::post('/admin/student-registrations/{student}/reject', [AdminStudentRegistrationController::class, 'reject'])
            ->name('admin.student-registrations.reject');
        Route::post('/admin/student-registrations/{student}/subjects/verify-all', [AdminStudentRegistrationController::class, 'verifyAllSubjects'])
            ->name('admin.student-registrations.subjects.verify-all');
        Route::post('/admin/student-registrations/{student}/subjects/{enrollment}/verify', [AdminStudentRegistrationController::class, 'verifySubject'])
            ->name('admin.student-registrations.subjects.verify');
        Route::post('/admin/student-registrations/{student}/subjects/{enrollment}/reject', [AdminStudentRegistrationController::class, 'rejectSubject'])
            ->name('admin.student-registrations.subjects.reject');
        Route::post('/admin/student-registrations/{student}/subjects/add', [AdminStudentRegistrationController::class, 'addSubject'])
            ->name('admin.student-registrations.subjects.add');
        Route::delete('/admin/student-registrations/{student}/subjects/{enrollment}', [AdminStudentRegistrationController::class, 'removeSubject'])
            ->name('admin.student-registrations.subjects.remove');

        Route::get('/admin/student-subject-requests', [\App\Http\Controllers\AdminStudentSubjectRequestController::class, 'index'])
            ->name('admin.student-subject-requests.index');
        Route::get('/admin/student-subject-requests/{changeRequest}', [\App\Http\Controllers\AdminStudentSubjectRequestController::class, 'show'])
            ->name('admin.student-subject-requests.show');
        Route::post('/admin/student-subject-requests/{changeRequest}/approve', [\App\Http\Controllers\AdminStudentSubjectRequestController::class, 'approve'])
            ->name('admin.student-subject-requests.approve');
        Route::post('/admin/student-subject-requests/{changeRequest}/reject', [\App\Http\Controllers\AdminStudentSubjectRequestController::class, 'reject'])
            ->name('admin.student-subject-requests.reject');

        Route::resource('instructors', \App\Http\Controllers\InstructorController::class);
        Route::resource('departments', \App\Http\Controllers\DepartmentController::class);
        Route::resource('sections', \App\Http\Controllers\SectionController::class);
        Route::resource('subjects', \App\Http\Controllers\SubjectController::class);
        Route::resource('programs', \App\Http\Controllers\ProgramController::class);
    });

    Route::get('/examinations', [PlatformController::class, 'examinations'])->name('examinations.index');
    Route::middleware('role:student')->group(function () {
        Route::get('/my-subjects', [\App\Http\Controllers\StudentEnrollmentController::class, 'index'])->name('student.enrollment.index');
        Route::get('/my-subjects/change-request', [\App\Http\Controllers\StudentEnrollmentController::class, 'changeRequestForm'])->name('student.enrollment.change-request');
        Route::post('/my-subjects/change-request', [\App\Http\Controllers\StudentEnrollmentController::class, 'submitChangeRequest'])->name('student.enrollment.change-request.store');
        Route::get('/my-subjects/{subject}', [\App\Http\Controllers\StudentEnrollmentController::class, 'show'])->name('student.enrollment.show');
    });
    Route::middleware('role:superadmin,admin,instructor')->group(function () {
        Route::get('/my-classes', [\App\Http\Controllers\InstructorTeachingController::class, 'index'])->name('instructor.teaching.index');
        Route::get('/my-classes/{subject}', [\App\Http\Controllers\InstructorTeachingController::class, 'show'])->name('instructor.teaching.show');
        Route::get('/my-classes/{subject}/sections/{section}', [\App\Http\Controllers\InstructorTeachingController::class, 'section'])->name('instructor.teaching.section');

        Route::get('/examinations/create', [\App\Http\Controllers\ExaminationController::class, 'create'])->name('examinations.create');
        Route::post('/examinations', [\App\Http\Controllers\ExaminationController::class, 'store'])->name('examinations.store');
        Route::get('/examinations/available-sections', [\App\Http\Controllers\ExaminationController::class, 'availableSections'])->name('examinations.sections');
        Route::get('/examinations/available-offerings', [\App\Http\Controllers\ExaminationController::class, 'availableOfferings'])->name('examinations.offerings');
        Route::post('/examinations/preview-questions-csv', [\App\Http\Controllers\ExaminationController::class, 'previewQuestionsCsv'])->name('examinations.preview-questions-csv');
        Route::post('/examinations/import-questions', [\App\Http\Controllers\ExaminationController::class, 'importQuestions'])->name('examinations.import-questions');
        Route::post('/examinations/questions/csv-error-report', [\App\Http\Controllers\ExaminationController::class, 'questionCsvErrorReport'])->name('examinations.question-csv-error-report');
        Route::get('/examinations/questions/csv-template', [\App\Http\Controllers\ExaminationController::class, 'questionCsvTemplate'])->name('examinations.question-csv-template');
        Route::get('/examinations/{examination}/edit', [\App\Http\Controllers\ExaminationController::class, 'edit'])->name('examinations.edit');
        Route::put('/examinations/{examination}', [\App\Http\Controllers\ExaminationController::class, 'update'])->name('examinations.update');
    });
    Route::get('/examinations/{examination}/take', [PlatformController::class, 'take'])->name('examinations.take');
    Route::get('/examinations/{examination}/result', [PlatformController::class, 'result'])->name('examinations.result');

    Route::prefix('examinations/{examination}/attempts')->name('examinations.attempts.')->group(function () {
        Route::get('/state', [\App\Http\Controllers\ExaminationAttemptController::class, 'state'])->name('state');
        Route::post('/accept-policy', [\App\Http\Controllers\ExaminationAttemptController::class, 'acceptPolicy'])->name('accept-policy');
        Route::post('/start', [\App\Http\Controllers\ExaminationAttemptController::class, 'start'])->name('start');
        Route::post('/answers', [\App\Http\Controllers\ExaminationAttemptController::class, 'saveAnswers'])->name('answers.bulk');
        Route::post('/answers/{question}', [\App\Http\Controllers\ExaminationAttemptController::class, 'saveAnswer'])->name('answers.store');
        Route::post('/violations', [\App\Http\Controllers\ExaminationAttemptController::class, 'recordViolation'])->name('violations.store');
        Route::post('/submit', [\App\Http\Controllers\ExaminationAttemptController::class, 'submit'])->name('submit');
    });

    Route::middleware('role:superadmin,admin,instructor')->prefix('monitoring')->name('monitoring.')->group(function () {
        Route::get('/examinations/{examination}/data', [\App\Http\Controllers\ExaminationMonitoringController::class, 'data'])->name('data');
        Route::get('/attempts/{attempt}/violations', [\App\Http\Controllers\ExaminationMonitoringController::class, 'violations'])->name('violations');
        Route::post('/attempts/{attempt}/reactivate', [\App\Http\Controllers\ExaminationMonitoringController::class, 'reactivate'])->name('reactivate');
    });

    Route::middleware('role:superadmin,admin,instructor')->group(function () {
        Route::get('/question-bank', [\App\Http\Controllers\QuestionController::class, 'index'])->name('questions.index');
        Route::get('/question-bank/csv-template', [\App\Http\Controllers\QuestionController::class, 'csvTemplate'])->name('questions.csv-template');
        Route::post('/question-bank/preview-csv', [\App\Http\Controllers\QuestionController::class, 'previewCsv'])->name('questions.preview-csv');
        Route::post('/question-bank/import-csv', [\App\Http\Controllers\QuestionController::class, 'importCsv'])->name('questions.import-csv');
        Route::get('/question-bank/export-csv', [\App\Http\Controllers\QuestionController::class, 'exportCsv'])->name('questions.export-csv');
        Route::post('/question-bank/error-report', [\App\Http\Controllers\QuestionController::class, 'errorReport'])->name('questions.error-report');
    });
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
