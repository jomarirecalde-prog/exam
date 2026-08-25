<x-exam-layout>
    @php
        $payload = [
            'offlineOnly' => true,
            'examinationId' => $examinationId,
            'studentId' => $studentId,
            'title' => '',
            'total' => 0,
            'remaining' => 3600,
            'maxWarnings' => config('examination.max_violation_warnings', 3),
            'policyVersion' => config('examination.policy_version', '1.0'),
            'attemptState' => null,
            'resultUrl' => route('offline.sync'),
            'questions' => [],
            'monitoring' => [],
            'offline' => ['supported' => true, 'require_preparation' => true],
            'urls' => [
                'acceptPolicy' => route('examinations.attempts.accept-policy', $examinationId),
                'start' => route('examinations.attempts.start', $examinationId),
                'saveAnswers' => route('examinations.attempts.answers.bulk', $examinationId),
                'violations' => route('examinations.attempts.violations.store', $examinationId),
                'submit' => route('examinations.attempts.submit', $examinationId),
                'prepareOffline' => route('examinations.attempts.prepare-offline', $examinationId),
                'syncTemplate' => route('exam-attempts.sync', ['attempt' => '__ATTEMPT__']),
                'submitOfflineTemplate' => route('exam-attempts.submit-offline', ['attempt' => '__ATTEMPT__']),
                'syncStatus' => route('offline.sync'),
                'offlineApp' => route('offline.app'),
            ],
        ];
    @endphp

    @include('pages.examinations._take-experience', [
        'payload' => $payload,
        'offlineMode' => true,
        'offlineMeta' => ['supported' => true],
    ])
</x-exam-layout>
