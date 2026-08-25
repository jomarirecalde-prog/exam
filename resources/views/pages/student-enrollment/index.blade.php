<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="My Subjects" subtitle="Subjects you have declared as enrolled for the current term.">
            @unless ($hasPendingChangeRequest)
                <x-ui.button variant="secondary" :href="route('student.enrollment.change-request')" wire:navigate>Request Subject Change</x-ui.button>
            @endunless
        </x-ui.page-header>

        @if (session('status'))
            <x-ui.alert class="mt-4">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($hasPendingChangeRequest)
            <x-ui.alert class="mt-4" variant="warning">
                You have a pending subject change request awaiting administrator review.
            </x-ui.alert>
        @endif

        <form method="get" action="{{ route('student.enrollment.index') }}" class="grid gap-4 sm:grid-cols-3">
            <x-ui.field label="Academic year" for="academic_year_id">
                <select id="academic_year_id" name="academic_year_id" class="ui-input">
                    <option value="">All academic years</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected((int) $academicYearId === (int) $year->id)>
                            {{ $year->name }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Semester" for="semester_id">
                <select id="semester_id" name="semester_id" class="ui-input">
                    <option value="">All semesters</option>
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester->id }}" @selected((int) $semesterId === (int) $semester->id)>
                            {{ $semester->name }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>

            <div class="flex items-end">
                <x-ui.button variant="secondary" type="submit" size="sm">Apply filter</x-ui.button>
            </div>
        </form>

        @if ($enrollments->isEmpty())
            <x-ui.empty-state class="mt-6" title="No enrolled subjects yet." icon="book-open">
                Subjects you declare during registration will appear here.
            </x-ui.empty-state>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($enrollments as $enrollment)
                    @php
                        $status = $enrollment['enrollment']?->status ?? ($enrollment['status'] ?? null);
                        $isPending = $status === \App\Enums\StudentSubjectEnrollmentStatus::PendingVerification;
                    @endphp
                    <article class="ui-card ui-card-pad flex flex-col">
                        <div class="flex-1">
                            <p class="text-lg font-semibold">{{ $enrollment['subject']->code }}</p>
                            <p class="mt-1 text-sm leading-6 text-muted">{{ $enrollment['subject']->name }}</p>
                            @if ($status)
                                <div class="mt-3">
                                    <x-ui.badge :status="$status->badgeStatus()">{{ $status->label() }}</x-ui.badge>
                                </div>
                            @endif
                            @if ($isPending && $subjectVerificationRequired)
                                <p class="mt-3 text-sm leading-6 text-warning-ink">
                                    Your enrollment for this subject is currently awaiting verification.
                                </p>
                            @endif
                            <p class="mt-3 text-sm text-muted">
                                {{ $enrollment['available_exams_count'] }} available examination(s)
                            </p>
                        </div>
                        <div class="mt-4">
                            <a
                                href="{{ route('student.enrollment.show', ['subject' => $enrollment['subject'], 'academic_year_id' => $enrollment['academic_year_id'], 'semester_id' => $enrollment['semester_id']]) }}"
                                class="btn-secondary inline-flex w-full justify-center text-sm"
                                wire:navigate
                            >
                                View Details
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
