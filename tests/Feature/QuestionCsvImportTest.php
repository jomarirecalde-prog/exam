<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_instructor_can_import_questions_from_csv(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        $csv = implode("\n", [
            'question,type,choice_a,choice_b,choice_c,choice_d,correct_answer,points,difficulty,topic,sample_answer',
            'What is 2 + 2?,multiple_choice,3,4,,,B,1,easy,Math,',
        ]);

        $file = UploadedFile::fake()->createWithContent('questions.csv', $csv);

        $response = $this->actingAs($user)->postJson(route('examinations.import-questions'), [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('questions.0.type', 'multiple_choice')
            ->assertJsonPath('questions.0.correctAnswer', 'B');
    }

    public function test_import_rejects_invalid_csv(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        $csv = "question,type,correct_answer\n,identification,cpu\n";

        $file = UploadedFile::fake()->createWithContent('questions.csv', $csv);

        $response = $this->actingAs($user)->postJson(route('examinations.import-questions'), [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
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
