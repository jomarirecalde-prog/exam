<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionCsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Instructor->value);
    }

    public function test_instructor_can_preview_questions_from_csv(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        $csv = implode("\n", [
            'question,type,choice_a,choice_b,choice_c,choice_d,correct_answer,points,difficulty,topic,sample_answer',
            'What is 2 + 2?,multiple_choice,3,4,,,B,1,easy,Math,',
        ]);

        $file = UploadedFile::fake()->createWithContent('questions.csv', $csv);

        $response = $this->actingAs($user)->postJson(route('examinations.preview-questions-csv'), [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('questions.0.type', 'multiple_choice')
            ->assertJsonPath('questions.0.correctAnswer', 'B')
            ->assertJsonStructure(['token', 'stats', 'preview']);
    }

    public function test_instructor_can_confirm_import_with_preview_token(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        $token = (string) \Illuminate\Support\Str::uuid();
        Cache::put("exam-csv-import:{$token}", [
            'questions' => [[
                'type' => 'identification',
                'text' => 'Sample question',
                'choices' => [],
                'correctAnswer' => 'answer',
                'sampleAnswer' => '',
                'points' => 1,
                'difficulty' => 'Medium',
                'topic' => 'Basics',
            ]],
            'rowErrors' => [],
            'stats' => ['total' => 1, 'valid' => 1, 'errors' => 0, 'duplicates' => 0],
        ], now()->addMinutes(5));

        $response = $this->actingAs($user)->postJson(route('examinations.import-questions'), [
            'token' => $token,
            'import_mode' => 'append',
        ]);

        $response->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('questions.0.text', 'Sample question');
    }

    public function test_preview_rejects_invalid_csv(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        $csv = "question,type,correct_answer\n,identification,cpu\n";

        $file = UploadedFile::fake()->createWithContent('questions.csv', $csv);

        $response = $this->actingAs($user)->postJson(route('examinations.preview-questions-csv'), [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('stats.valid', 0)
            ->assertJsonPath('stats.errors', 1)
            ->assertJsonStructure(['message', 'errors', 'stats', 'preview']);
    }

    public function test_instructor_can_download_csv_template(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        $response = $this->actingAs($user)->get(route('examinations.question-csv-template'));

        $response->assertOk();
        $this->assertStringContainsString('question,type,choice_a', $response->streamedContent());
    }
}
