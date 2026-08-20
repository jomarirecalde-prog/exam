<?php

namespace Database\Seeders;

use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationSetting;
use App\Models\ExaminationVersion;
use App\Models\GradingFormula;
use App\Models\GradingFormulaRule;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedGradingFormula();
        $this->seedSystemSettings();
        $academic = $this->seedAcademicStructure();
        $users = $this->seedUsers($academic);
        $this->seedQuestions($academic, $users['instructor']);
        $this->seedExaminations($academic, $users['instructor']);
    }

    protected function seedRolesAndPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage users', 'manage academic', 'manage examinations', 'take examinations',
            'grade examinations', 'view results', 'manage settings', 'manage sync',
            'view reports', 'manage backups', 'view audit logs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $rolePermissions = [
            UserRole::Superadmin->value => $permissions,
            UserRole::Admin->value => [
                'manage users', 'manage academic', 'view results', 'view reports',
            ],
            UserRole::Instructor->value => [
                'manage examinations', 'grade examinations', 'view results', 'view reports',
            ],
            UserRole::Student->value => ['take examinations', 'view results'],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($perms);
        }
    }

    protected function seedGradingFormula(): void
    {
        $formula = GradingFormula::firstOrCreate(
            ['slug' => 'default-percentage'],
            [
                'name' => 'Default Percentage Grading',
                'description' => 'Standard percentage-based grading with configurable passing threshold.',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        GradingFormulaRule::firstOrCreate(
            ['grading_formula_id' => $formula->id, 'rule_type' => 'percentage'],
            [
                'config' => ['method' => 'earned_over_total', 'multiplier' => 100],
                'priority' => 1,
            ]
        );
    }

    protected function seedSystemSettings(): void
    {
        $settings = [
            ['key' => 'institution_name', 'value' => 'Sample State University', 'type' => 'string', 'group' => 'general'],
            ['key' => 'timezone', 'value' => 'Asia/Manila', 'type' => 'string', 'group' => 'general'],
            ['key' => 'default_passing_percentage', 'value' => '75', 'type' => 'float', 'group' => 'examination'],
            ['key' => 'default_duration_minutes', 'value' => '60', 'type' => 'integer', 'group' => 'examination'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }

    protected function seedAcademicStructure(): array
    {
        $year = AcademicYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_current' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => '1st Semester',
            'order' => 1,
            'is_current' => true,
        ]);

        $department = Department::create([
            'code' => 'CCIS',
            'name' => 'College of Computing and Information Sciences',
        ]);

        $program = Program::create([
            'department_id' => $department->id,
            'code' => 'BSIS',
            'name' => 'Bachelor of Science in Information Systems',
        ]);

        $yearLevel = YearLevel::create([
            'program_id' => $program->id,
            'name' => '1st Year',
            'level' => 1,
        ]);

        $section = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1A',
            'code' => 'BSIS-1A',
        ]);

        $subject = Subject::create([
            'department_id' => $department->id,
            'code' => 'IS101',
            'name' => 'Introduction to Information Systems',
            'units' => 3,
        ]);

        return compact('year', 'semester', 'department', 'program', 'yearLevel', 'section', 'subject');
    }

    protected function seedUsers(array $academic): array
    {
        $superadmin = $this->createUser(
            'Super Admin', 'superadmin', 'superadmin@exam.local', UserRole::Superadmin->value
        );

        $admin = $this->createUser(
            'System Admin', 'admin', 'admin@exam.local', UserRole::Admin->value
        );

        $instructorUser = $this->createUser(
            'Prof. Juan Dela Cruz', 'instructor', 'instructor@exam.local', UserRole::Instructor->value,
            firstName: 'Juan', lastName: 'Dela Cruz'
        );

        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'employee_id' => 'EMP-001',
            'department_id' => $academic['department']->id,
        ]);

        $studentUser = $this->createUser(
            'Maria Santos', '2026-0001', 'student@exam.local', UserRole::Student->value,
            firstName: 'Maria', lastName: 'Santos'
        );

        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-0001',
            'program_id' => $academic['program']->id,
            'year_level_id' => $academic['yearLevel']->id,
            'section_id' => $academic['section']->id,
        ]);

        return compact('superadmin', 'admin', 'instructorUser', 'instructor', 'studentUser', 'student');
    }

    protected function createUser(
        string $name,
        string $username,
        string $email,
        string $role,
        ?string $firstName = null,
        ?string $lastName = null,
    ): User {
        $user = User::create([
            'name' => $name,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function seedQuestions(array $academic, Instructor $instructor): void
    {
        $questions = [
            ['type' => QuestionType::MultipleChoice, 'text' => 'What does IS stand for?', 'answer' => 'b', 'choices' => [
                ['a', 'Internet System', false], ['b', 'Information Systems', true], ['c', 'Integrated Software', false], ['d', 'Internal Server', false],
            ]],
            ['type' => QuestionType::TrueFalse, 'text' => 'An information system includes people, processes, and technology.', 'answer' => 'true'],
            ['type' => QuestionType::MultipleSelect, 'text' => 'Select all components of an information system.', 'answer' => ['people', 'processes', 'technology']],
            ['type' => QuestionType::Identification, 'text' => 'What acronym refers to the Central Processing Unit?', 'answer' => 'cpu'],
            ['type' => QuestionType::FillBlank, 'text' => 'The _____ layer is the top layer of the OSI model.', 'answer' => 'application'],
            ['type' => QuestionType::ShortAnswer, 'text' => 'Define database normalization in one sentence.', 'answer' => null],
            ['type' => QuestionType::Essay, 'text' => 'Explain the role of information systems in modern organizations.', 'answer' => null],
            ['type' => QuestionType::Enumeration, 'text' => 'List the three types of storage in order: primary, secondary, _____.', 'answer' => ['primary', 'secondary', 'tertiary']],
            ['type' => QuestionType::Matching, 'text' => 'Match the terms.', 'answer' => ['ram' => 'volatile', 'rom' => 'non-volatile']],
        ];

        for ($i = count($questions); $i < 20; $i++) {
            $questions[] = [
                'type' => QuestionType::MultipleChoice,
                'text' => 'Sample question '.($i + 1).' about IS fundamentals.',
                'answer' => 'a',
                'choices' => [
                    ['a', 'Correct option', true],
                    ['b', 'Wrong option 1', false],
                    ['c', 'Wrong option 2', false],
                    ['d', 'Wrong option 3', false],
                ],
            ];
        }

        foreach ($questions as $index => $data) {
            $question = Question::create([
                'uuid' => (string) Str::uuid(),
                'subject_id' => $academic['subject']->id,
                'instructor_id' => $instructor->id,
                'type' => $data['type'],
                'question_text' => $data['text'],
                'correct_answer' => $data['answer'],
                'points' => 1,
                'difficulty' => $index % 3 === 0 ? 'easy' : 'medium',
            ]);

            if (isset($data['choices'])) {
                foreach ($data['choices'] as $order => [$label, $text, $isCorrect]) {
                    QuestionChoice::create([
                        'question_id' => $question->id,
                        'label' => $label,
                        'choice_text' => $text,
                        'is_correct' => $isCorrect,
                        'order' => $order,
                    ]);
                }
            }
        }
    }

    protected function seedExaminations(array $academic, Instructor $instructor): void
    {
        foreach ([ExaminationPeriod::Prelim, ExaminationPeriod::Midterm, ExaminationPeriod::Final] as $period) {
            $exam = Examination::create([
                'uuid' => (string) Str::uuid(),
                'code' => 'EXAM-2026-IS101-'.substr($period->value, 0, 3).'-001',
                'subject_id' => $academic['subject']->id,
                'section_id' => $academic['section']->id,
                'instructor_id' => $instructor->id,
                'academic_year_id' => $academic['year']->id,
                'semester_id' => $academic['semester']->id,
                'examination_period' => $period,
                'title' => ucfirst(strtolower($period->value)).' Examination',
                'description' => 'Coverage: Modules 1-5',
                'instructions' => 'Read each question carefully. Good luck!',
                'duration_minutes' => 60,
                'total_items' => 20,
                'passing_percentage' => 75,
                'examination_date' => now()->addDays(7)->toDateString(),
                'status' => ExamStatus::Draft,
            ]);

            ExaminationSetting::create(['examination_id' => $exam->id]);

            ExaminationVersion::create([
                'examination_id' => $exam->id,
                'version_number' => 1,
                'snapshot' => ['title' => $exam->title],
            ]);
        }
    }
}
