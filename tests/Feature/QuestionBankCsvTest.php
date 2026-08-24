<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionBankCsvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Instructor->value);
    }

    public function test_instructor_can_export_questions(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        $response = $this->actingAs($user)->get(route('questions.export-csv', ['scope' => 'all']));

        $response->assertOk();
        $this->assertStringContainsString('question,type', $response->streamedContent());
    }

    public function test_instructor_can_import_questions_into_question_bank(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);
        $subject = Subject::create([
            'code' => 'IS101',
            'name' => 'Information Systems 101',
            'is_active' => true,
        ]);

        $token = (string) \Illuminate\Support\Str::uuid();
        Cache::put("question-csv-import:{$token}", [
            'questions' => [[
                'type' => 'identification',
                'text' => 'What is CPU?',
                'choices' => [],
                'correctAnswer' => 'Central Processing Unit',
                'sampleAnswer' => '',
                'points' => 1,
                'difficulty' => 'Medium',
                'topic' => 'Hardware',
            ]],
            'subject_id' => $subject->id,
            'rowErrors' => [],
            'stats' => ['total' => 1, 'valid' => 1, 'errors' => 0, 'duplicates' => 0],
        ], now()->addMinutes(5));

        $response = $this->actingAs($user)->postJson(route('questions.import-csv'), [
            'token' => $token,
            'import_mode' => 'create',
            'subject_id' => $subject->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('counts.created', 1);

        $this->assertDatabaseHas('questions', [
            'subject_id' => $subject->id,
            'question_text' => 'What is CPU?',
        ]);
    }

    public function test_question_bank_preview_requires_csv_file(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        $response = $this->actingAs($user)->postJson(route('questions.preview-csv'), []);

        $response->assertStatus(422);
    }
}
