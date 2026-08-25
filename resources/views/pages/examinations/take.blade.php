<x-exam-layout>
    @php
        $payload = [
            'examinationId' => $examination->id,
            'title' => $examination->title,
            'total' => $questions->count(),
            'remaining' => $remaining,
            'maxWarnings' => $maxWarnings,
            'policyVersion' => $policyVersion,
            'attemptState' => $attemptState,
            'resultUrl' => route('examinations.result', $examination),
            'questions' => $questions->values()->all(),
            'monitoring' => $monitoring,
            'urls' => [
                'state' => route('examinations.attempts.state', $examination),
                'acceptPolicy' => route('examinations.attempts.accept-policy', $examination),
                'start' => route('examinations.attempts.start', $examination),
                'saveAnswers' => route('examinations.attempts.answers.bulk', $examination),
                'saveAnswer' => route('examinations.attempts.answers.store', ['examination' => $examination, 'question' => '__QUESTION__']),
                'violations' => route('examinations.attempts.violations.store', $examination),
                'submit' => route('examinations.attempts.submit', $examination),
            ],
        ];
    @endphp

    <div class="flex min-h-screen flex-col" x-data="examTaking(@js($payload))" x-cloak>
        {{-- Policy screen --}}
        <template x-if="phase === 'policy'">
            <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col justify-center px-4 py-10 sm:px-6">
                <div class="rounded-modal border border-line bg-surface p-6 shadow-pop sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted">Examination Policy</p>
                    <h1 class="mt-2 text-2xl font-semibold">Please read the rules before proceeding</h1>

                    <div class="mt-6 rounded-card border border-warning/30 bg-warning-soft p-5">
                        <p class="flex items-center gap-2 text-sm font-semibold text-warning-ink">
                            <x-icon name="alert-triangle" :size="18" />
                            Maximum Warning Limit: <span x-text="maxWarnings"></span>
                        </p>
                        <ul class="mt-4 space-y-2 text-sm leading-6 text-ink">
                            <li>Maintain a quiet and proper examination environment.</li>
                            <li>Do not leave or exit the examination website while the examination is ongoing.</li>
                            <li>Do not switch to another browser tab, window, application, or website.</li>
                            <li>Do not copy, reproduce, screenshot, record, or share examination questions or content.</li>
                            <li>Do not attempt to use unauthorized materials or applications during the examination.</li>
                            <li>Follow all instructions provided by the instructor or examination administrator.</li>
                        </ul>
                        <p class="mt-4 text-sm font-medium text-warning-ink">
                            <span x-text="maxWarnings"></span> violations = Examination Locked
                        </p>
                    </div>

                    <div class="mt-6 space-y-3 text-sm leading-6 text-muted">
                        <p class="font-medium text-ink">Warning Policy</p>
                        <p>You are allowed a maximum of <span x-text="maxWarnings"></span> violation warnings. Upon the third violation, your examination will automatically be stopped and locked.</p>
                        <p>If your examination is locked, you must approach your instructor. Only an authorized instructor or examination administrator can review and reactivate your examination attempt.</p>
                    </div>

                    <div class="mt-6 rounded-card border border-line bg-canvas p-4 text-sm leading-6 text-muted">
                        <p class="font-medium text-ink">What this system monitors</p>
                        <p class="mt-2">This examination system may record examination-related events, including tab visibility changes, loss of browser focus, copy attempts, fullscreen exits, and examination navigation events.</p>
                        <p class="mt-2">The system does not claim to detect all external applications, screenshots, cameras, or activities outside the browser. Only data necessary for examination integrity is collected.</p>
                    </div>

                    <label class="mt-6 flex cursor-pointer items-start gap-3">
                        <input type="checkbox" class="mt-1 rounded border-line text-navy-800" x-model="policyAccepted">
                        <span class="text-sm leading-6">I have read and understood the Examination Rules and Warning Policy.</span>
                    </label>

                    <p class="mt-2 text-sm text-danger-ink" x-show="policyError" x-text="policyError"></p>

                    <div class="mt-8 flex justify-end">
                        <button
                            type="button"
                            class="btn-primary"
                            :disabled="!policyAccepted || policySubmitting"
                            @click="acceptPolicy()"
                        >
                            <span x-show="!requireFullscreen">Start Examination</span>
                            <span x-show="requireFullscreen">Enter Fullscreen and Start Examination</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Locked screen --}}
        <template x-if="phase === 'locked'">
            <div class="mx-auto flex w-full max-w-xl flex-1 flex-col justify-center px-4 py-10 sm:px-6">
                <div class="rounded-modal border border-danger/30 bg-surface p-8 text-center shadow-pop">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-danger-soft text-danger-ink">
                        <x-icon name="lock" :size="28" />
                    </div>
                    <h1 class="mt-5 text-2xl font-semibold">Examination Locked</h1>
                    <p class="mt-3 text-sm leading-6 text-muted">
                        Your examination has been stopped because you reached the maximum number of policy violations.
                    </p>
                    <p class="mt-4 text-lg font-semibold tabular-nums text-danger-ink">
                        Warnings: <span x-text="warningCount"></span> / <span x-text="maxWarnings"></span>
                    </p>
                    <p class="mt-4 text-sm leading-6 text-muted">
                        Please approach your instructor for assistance. Only an authorized instructor or administrator can reactivate your examination attempt.
                    </p>
                    <p class="mt-2 text-sm text-muted" x-show="lockReason" x-text="lockReason"></p>
                    <div class="mt-8">
                        <a href="{{ route('examinations.index') }}" class="btn-secondary">Return to Examinations</a>
                    </div>
                </div>
            </div>
        </template>

        {{-- Active examination --}}
        <template x-if="phase === 'active'">
            <div class="flex min-h-screen flex-col">
                <header class="sticky top-0 z-20 border-b border-line bg-surface">
                    <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $examination->title }}</p>
                            <p class="text-xs text-muted">{{ $examination->subject?->code }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="hidden text-xs text-muted sm:block" x-show="saveStatus">
                                <span x-show="saveStatus === 'saving'">Saving...</span>
                                <span x-show="saveStatus === 'saved'" class="text-brand">Saved</span>
                            </div>
                            <div
                                class="rounded-btn border px-3 py-1 text-sm tabular-nums"
                                :class="warningCount > 0 ? 'border-warning bg-warning-soft text-warning-ink' : 'border-line text-muted'"
                                aria-live="polite"
                            >
                                Warnings: <span x-text="warningCount"></span> / <span x-text="maxWarnings"></span>
                            </div>
                            <div
                                class="tabular-nums text-2xl font-semibold tracking-tight"
                                :class="{
                                    'text-warning-ink': timerTone === 'warning',
                                    'text-danger-ink': timerTone === 'critical'
                                }"
                                x-text="clock"
                                aria-live="polite"
                                aria-label="Time remaining"
                            ></div>
                        </div>
                    </div>
                </header>

                <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 px-4 py-8 sm:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_14rem]">
                    <main class="pb-28 lg:pb-8">
                        <p class="text-sm text-muted">Question <span x-text="current"></span> of <span x-text="total"></span></p>
                        <template x-for="(question, index) in questions" :key="question.id || index">
                            <article x-show="current === index + 1" class="exam-protected-content select-none">
                                <h1 class="mt-3 text-xl font-semibold leading-8" x-text="question.text"></h1>
                                <fieldset class="mt-6 space-y-3" :aria-label="'Choices for question ' + (index + 1)">
                                    <template x-for="choice in question.choices" :key="choice.id">
                                        <label class="flex cursor-pointer items-start gap-3 rounded-card border border-line px-4 py-3 hover:bg-brand-soft"
                                            :class="answers[current] === choice.id ? 'border-brand bg-brand-soft' : ''">
                                            <input type="radio" class="mt-1 border-line text-navy-800" :name="'q'+current" :value="choice.id" @change="select(choice.id)" :checked="answers[current] === choice.id">
                                            <span>
                                                <span class="mr-2 text-sm text-muted" x-text="choice.id"></span>
                                                <span x-text="choice.text"></span>
                                            </span>
                                        </label>
                                    </template>
                                </fieldset>
                            </article>
                        </template>
                    </main>

                    <aside class="hidden lg:block">
                        <p class="text-sm font-medium">Question Navigator</p>
                        <div class="mt-3 grid grid-cols-5 gap-2">
                            <template x-for="n in total" :key="n">
                                <button
                                    type="button"
                                    class="h-9 rounded-btn border text-sm"
                                    :class="{
                                        'border-brand bg-brand text-white dark:text-navy-950': current === n,
                                        'border-line': current !== n && !answers[n] && !flagged[n],
                                        'border-warning text-warning-ink': flagged[n] && current !== n,
                                        'border-line bg-brand-soft': answers[n] && current !== n
                                    }"
                                    @click="go(n)"
                                    x-text="n"
                                    :aria-current="current === n ? 'true' : 'false'"
                                ></button>
                            </template>
                        </div>
                    </aside>
                </div>

                <div class="fixed inset-x-0 bottom-0 border-t border-line bg-surface lg:static lg:border-0">
                    <div class="mx-auto flex max-w-5xl items-center justify-between gap-2 px-4 py-3 sm:px-6">
                        <button type="button" class="btn-secondary" @click="prev()" :disabled="current === 1">Previous</button>
                        <div class="flex gap-2">
                            <button type="button" class="btn-ghost" @click="flag()">
                                <x-icon name="flag" :size="16" />
                                Flag
                            </button>
                            <button type="button" class="btn-secondary lg:hidden" @click="navigatorOpen = true">Navigator</button>
                            <button type="button" class="btn-secondary" x-show="current < total" @click="next()">Next</button>
                            <button type="button" class="btn-primary" x-show="current === total" x-cloak @click="submitOpen = true">Submit</button>
                        </div>
                    </div>
                </div>

                <div x-show="navigatorOpen" x-cloak class="fixed inset-0 z-40 lg:hidden">
                    <div class="absolute inset-0 bg-navy-950/40" @click="navigatorOpen = false"></div>
                    <div class="absolute inset-x-0 bottom-0 rounded-t-modal bg-surface p-5">
                        <div class="mb-4 flex items-center justify-between">
                            <p class="font-medium">Question Navigator</p>
                            <button type="button" class="btn-icon" @click="navigatorOpen = false" aria-label="Close"><x-icon name="x" :size="18" /></button>
                        </div>
                        <div class="grid grid-cols-6 gap-2">
                            <template x-for="n in total" :key="'m'+n">
                                <button type="button" class="h-10 rounded-btn border text-sm" :class="current === n ? 'border-brand bg-brand text-white' : 'border-line'" @click="go(n)" x-text="n"></button>
                            </template>
                        </div>
                    </div>
                </div>

                <div x-show="submitOpen || submitting" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                    <div class="absolute inset-0 bg-navy-950/50" @click="!submitting && (submitOpen = false)"></div>
                    <div class="relative w-full max-w-md rounded-modal border border-line bg-surface p-6 shadow-pop">
                        <template x-if="submitting">
                            <div>
                                <h2 class="text-lg font-semibold">Submitting your examination...</h2>
                                <p class="mt-2 text-sm text-muted">Please wait. Do not close this window.</p>
                            </div>
                        </template>
                        <template x-if="!submitting">
                            <div>
                                <h2 class="text-lg font-semibold">Submit examination?</h2>
                                <p class="mt-2 text-sm leading-6 text-muted">
                                    You have answered <span x-text="answeredCount"></span> of <span x-text="total"></span> questions.
                                </p>
                                <p class="mt-1 text-sm text-warning-ink" x-show="unanswered.length" x-cloak>
                                    <span x-text="unanswered.length"></span> questions are unanswered.
                                </p>
                                <div class="mt-6 flex justify-end gap-2">
                                    <button type="button" class="btn-secondary" @click="submitOpen = false">Cancel</button>
                                    <button type="button" class="btn-primary" @click="submitExam()">Submit Examination</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        {{-- Violation warning modal --}}
        <div x-show="violationModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-navy-950/60"></div>
            <div class="relative w-full max-w-md rounded-modal border border-warning/30 bg-surface p-6 shadow-pop" role="alertdialog" aria-modal="true">
                <div class="flex items-center gap-2 text-warning-ink">
                    <x-icon name="alert-triangle" :size="22" />
                    <h2 class="text-lg font-semibold">Examination Policy Warning</h2>
                </div>
                <p class="mt-4 text-sm leading-6" x-text="violationMessage"></p>
                <p class="mt-3 text-base font-semibold tabular-nums">
                    Warning: <span x-text="violationModalWarning"></span> of <span x-text="maxWarnings"></span>
                </p>
                <p class="mt-3 text-sm leading-6 text-muted" x-text="violationRemainingText"></p>
                <div class="mt-6 flex justify-end">
                    <button type="button" class="btn-primary" @click="acknowledgeViolation()">I Understand</button>
                </div>
            </div>
        </div>

        {{-- Starting overlay --}}
        <div x-show="phase === 'starting'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-canvas">
            <div class="text-center">
                <p class="text-lg font-medium">Starting examination...</p>
                <p class="mt-2 text-sm text-muted">Please wait.</p>
            </div>
        </div>
    </div>
</x-exam-layout>
