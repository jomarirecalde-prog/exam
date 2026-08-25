<div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0 flex-1">
        <p class="text-xs font-medium uppercase tracking-wide text-muted">{{ $exam['subject_code'] ?? $exam['subject'] }}</p>
        <h3 class="mt-1 text-lg font-semibold">{{ $exam['title'] }}</h3>
        <p class="mt-1 text-sm text-muted">
            Section: {{ $exam['sections'] }}
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
            <span @class([
                'inline-flex items-center gap-1.5 font-medium',
                'text-success-ink' => $exam['is_live'],
                'text-muted' => ! $exam['is_live'],
            ])>
                <span @class([
                    'h-2 w-2 rounded-full',
                    'bg-success-ink animate-pulse' => $exam['is_live'],
                    'bg-muted' => ! $exam['is_live'],
                ])></span>
                {{ $exam['is_live'] ? 'LIVE' : ($exam['status'] ?? 'Ended') }}
            </span>
            @if ($exam['is_live'])
                <span class="text-muted">
                    Students taking exam:
                    <span class="font-semibold text-ink">{{ $exam['students_taking'] }}</span>
                    /
                    <span>{{ $exam['students_total'] }}</span>
                </span>
            @else
                <span class="text-muted">
                    Locked students:
                    <span @class(['font-semibold', 'text-danger-ink' => ($exam['students_locked'] ?? 0) > 0, 'text-ink' => ($exam['students_locked'] ?? 0) === 0])>
                        {{ $exam['students_locked'] ?? 0 }}
                    </span>
                </span>
            @endif
        </div>
    </div>
    <div class="shrink-0">
        <a href="{{ $exam['monitor_url'] }}" class="btn-primary w-full sm:w-auto">
            {{ $buttonLabel ?? 'Monitor Examination' }}
        </a>
    </div>
</div>
