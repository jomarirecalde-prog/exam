<?php

namespace App\Models;

use App\Enums\OfflineExaminationMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ExaminationSetting extends Model
{
    protected $fillable = [
        'examination_id', 'randomize_questions', 'randomize_choices',
        'allow_back_navigation', 'one_question_per_page', 'show_question_numbers',
        'show_timer', 'auto_submit_on_expire', 'allow_resume', 'max_attempts',
        'show_score_immediately', 'show_correct_answers', 'show_explanations',
        'prevent_duplicate_submissions', 'require_fullscreen', 'detect_inactivity',
        'inactivity_timeout_seconds', 'enable_review_before_submit',
        'disable_copy_paste', 'disable_right_click', 'detect_tab_switch', 'question_pool_size',
        'offline_examination_mode', 'allow_offline_continuation', 'require_offline_preparation',
        'allow_pending_offline_submission', 'max_offline_duration_minutes', 'sync_grace_period_minutes',
    ];

    protected function casts(): array
    {
        return [
            'randomize_questions' => 'boolean',
            'randomize_choices' => 'boolean',
            'allow_back_navigation' => 'boolean',
            'one_question_per_page' => 'boolean',
            'show_question_numbers' => 'boolean',
            'show_timer' => 'boolean',
            'auto_submit_on_expire' => 'boolean',
            'allow_resume' => 'boolean',
            'show_score_immediately' => 'boolean',
            'show_correct_answers' => 'boolean',
            'show_explanations' => 'boolean',
            'prevent_duplicate_submissions' => 'boolean',
            'require_fullscreen' => 'boolean',
            'detect_inactivity' => 'boolean',
            'enable_review_before_submit' => 'boolean',
            'disable_copy_paste' => 'boolean',
            'disable_right_click' => 'boolean',
            'detect_tab_switch' => 'boolean',
            'offline_examination_mode' => OfflineExaminationMode::class,
            'allow_offline_continuation' => 'boolean',
            'require_offline_preparation' => 'boolean',
            'allow_pending_offline_submission' => 'boolean',
        ];
    }

    public function supportsOffline(): bool
    {
        $mode = $this->offline_examination_mode ?? OfflineExaminationMode::Disabled;

        return $mode->supportsOffline() && $this->allow_offline_continuation;
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }
}
