<x-exam-layout>
    @php
        $student = auth()->user()?->student;
        $payload = [
            'examinationId' => $examination->id,
            'studentId' => $student?->id,
            'title' => $examination->title,
            'total' => $questions->count(),
            'remaining' => $remaining,
            'maxWarnings' => $maxWarnings,
            'policyVersion' => $policyVersion,
            'attemptState' => $attemptState,
            'resultUrl' => route('examinations.result', $examination),
            'questions' => $questions->values()->all(),
            'monitoring' => $monitoring,
            'offline' => $offline,
            'urls' => [
                'state' => route('examinations.attempts.state', $examination),
                'acceptPolicy' => route('examinations.attempts.accept-policy', $examination),
                'start' => route('examinations.attempts.start', $examination),
                'saveAnswers' => route('examinations.attempts.answers.bulk', $examination),
                'saveAnswer' => route('examinations.attempts.answers.store', ['examination' => $examination, 'question' => '__QUESTION__']),
                'violations' => route('examinations.attempts.violations.store', $examination),
                'submit' => route('examinations.attempts.submit', $examination),
                'progress' => route('examinations.attempts.progress', $examination),
                'prepareOffline' => route('examinations.attempts.prepare-offline', $examination),
                'syncTemplate' => route('exam-attempts.sync', ['attempt' => '__ATTEMPT__']),
                'submitOfflineTemplate' => route('exam-attempts.submit-offline', ['attempt' => '__ATTEMPT__']),
                'syncStatus' => route('offline.sync'),
                'offlineApp' => route('offline.app'),
            ],
        ];
    @endphp

    @include('pages.examinations._take-experience', [
        'payload' => $payload,
        'offlineMode' => false,
        'examination' => $examination,
        'offlineMeta' => $offline,
    ])
</x-exam-layout>
