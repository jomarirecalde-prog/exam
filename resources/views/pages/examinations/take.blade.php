<x-exam-layout>
    @php
        $payload = [
            'title' => $examination->title,
            'total' => $questions->count(),
            'remaining' => $remaining,
            'resultUrl' => route('examinations.result', $examination),
            'questions' => $questions->values()->all(),
        ];
    @endphp

    <div class="flex min-h-screen flex-col" x-data="examTaking(@js($payload))">
        <header class="sticky top-0 z-20 border-b border-line bg-surface">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ $examination->title }}</p>
                    <p class="text-xs text-muted">{{ $examination->subject?->code }}</p>
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
        </header>

        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 px-4 py-8 sm:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_14rem]">
            <main class="pb-28 lg:pb-8">
                <p class="text-sm text-muted">Question <span x-text="current"></span> of <span x-text="total"></span></p>
                <template x-for="(question, index) in questions" :key="index">
                    <article x-show="current === index + 1">
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
                        <ul class="mt-3 flex flex-wrap gap-2" x-show="unanswered.length">
                            <template x-for="item in unanswered" :key="item.number">
                                <button type="button" class="btn-secondary btn-sm" @click="go(item.number); submitOpen = false" x-text="item.number"></button>
                            </template>
                        </ul>
                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button" class="btn-secondary" @click="submitOpen = false">Cancel</button>
                            <button type="button" class="btn-primary" @click="submitExam()">Submit Examination</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-exam-layout>
