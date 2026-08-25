<x-app-layout>
    <div
        class="ui-page"
        x-data="offlineApp(@js([
            'userName' => $userName,
            'studentId' => $student->id,
            'bootstrapUrl' => route('offline.bootstrap'),
            'syncStatusUrl' => route('offline.sync'),
            'examinationsUrl' => route('examinations.index'),
            'syncUrlTemplate' => route('exam-attempts.sync', ['attempt' => '__ATTEMPT__']),
        ]))"
        x-init="init()"
    >
        <template x-if="!unlocked">
            <div class="mx-auto flex max-w-md flex-col justify-center py-16">
                <div class="ui-card ui-card-pad text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted">{{ config('examination.pwa.name') }}</p>
                    <h1 class="mt-2 text-xl font-semibold">Verify Your Identity</h1>
                    <p class="mt-2 text-sm text-muted">Enter your application PIN to access offline examination data on this device.</p>
                    <input
                        type="password"
                        inputmode="numeric"
                        maxlength="6"
                        class="ui-input mx-auto mt-6 max-w-[12rem] text-center tracking-[0.4em]"
                        x-model="pinInput"
                        @keydown.enter="submitPin()"
                        placeholder="••••"
                    >
                    <p class="mt-2 text-sm text-danger-ink" x-show="pinError" x-text="pinError"></p>
                    <button type="button" class="btn-primary mt-6" @click="submitPin()">Unlock</button>
                </div>
            </div>
        </template>

        <template x-if="unlocked">
            <div>
                <header class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 class="ui-title">Welcome, <span x-text="userName.split(' ')[0]"></span></h1>
                            <p class="mt-1 text-sm text-muted" x-show="online">Your examinations and offline-ready content.</p>
                        </div>
                        <div class="flex items-center gap-2 rounded-card border px-3 py-2 text-sm" :class="online ? 'border-success/30 bg-success-soft text-success-ink' : 'border-warning/30 bg-warning-soft text-warning-ink'">
                            <span class="inline-block h-2 w-2 rounded-full" :class="online ? 'bg-success-ink' : 'bg-warning-ink'"></span>
                            <span x-text="online ? 'Online' : 'Offline Mode'"></span>
                        </div>
                    </div>

                    <div class="rounded-card border border-line bg-canvas p-4 text-sm leading-6 text-muted" x-show="!online">
                        <p class="font-medium text-ink">Some online features are temporarily unavailable.</p>
                        <p class="mt-2">Available offline: prepared examinations, continue active examination, locally saved answers, and pending submissions.</p>
                        <p class="mt-2">Internet connection will automatically synchronize your examination data when it becomes available.</p>
                    </div>

                    <div class="rounded-card border border-brand/20 bg-brand-soft p-4 text-sm" x-show="syncing">
                        <p class="font-medium text-ink">Synchronizing examination data...</p>
                    </div>
                    <div class="rounded-card border border-success/20 bg-success-soft p-4 text-sm" x-show="syncComplete">
                        <p class="font-medium text-success-ink">Synchronization complete.</p>
                    </div>
                </header>

                <section class="mt-10">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="ui-kicker">My Examinations</h2>
                        <a :href="syncStatusUrl" class="text-sm font-medium text-brand hover:underline">Sync Status</a>
                    </div>

                    <div class="mt-4 space-y-4" x-show="loading">
                        <div class="ui-card ui-card-pad text-sm text-muted">Loading local examinations...</div>
                    </div>

                    <div class="mt-4 space-y-4" x-show="!loading && exams.length === 0">
                        <x-ui.empty-state title="No prepared examinations" icon="clipboard-list">
                            <span x-show="online">Prepare an examination while online to access it offline.</span>
                            <span x-show="!online">No examinations were prepared for offline use on this device.</span>
                        </x-ui.empty-state>
                    </div>

                    <div class="mt-4 space-y-4">
                        <template x-for="exam in exams" :key="exam.examination_id">
                            <article class="ui-card ui-card-pad">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <h3 class="text-base font-semibold" x-text="exam.title"></h3>
                                        <p class="mt-1 text-sm text-muted">
                                            <span x-text="exam.subject_code"></span>
                                            <span x-show="exam.subject_name"> — <span x-text="exam.subject_name"></span></span>
                                        </p>
                                        <p class="mt-2 text-sm" :class="statusClass(exam.status)" x-text="exam.status_label"></p>
                                        <p class="mt-2 text-sm text-muted" x-show="exam.status === 'in_progress'">
                                            Progress: <span x-text="exam.answered_count"></span> of <span x-text="exam.question_count"></span> questions answered
                                        </p>
                                        <p class="mt-2 text-sm text-muted" x-show="exam.status === 'not_prepared' || exam.status === 'internet_required'">
                                            Internet connection is required to prepare this examination.
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 flex-col gap-2">
                                        <button
                                            type="button"
                                            class="btn-primary"
                                            x-show="exam.status === 'ready_for_offline'"
                                            @click="startExam(exam)"
                                        >Start Examination</button>
                                        <button
                                            type="button"
                                            class="btn-primary"
                                            x-show="exam.status === 'in_progress'"
                                            @click="resumeExam(exam)"
                                        >Resume Examination</button>
                                        <a
                                            :href="syncStatusUrl"
                                            class="btn-secondary text-center"
                                            x-show="exam.status === 'submission_pending' || exam.status === 'locked'"
                                        >View Sync Status</a>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>
                </section>

                <section class="mt-10 rounded-card border border-line bg-canvas p-4 text-sm text-muted" x-show="!online">
                    <p class="font-medium text-ink">Requires internet</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>First-time login</li>
                        <li>Downloading or preparing new examinations</li>
                        <li>Real-time instructor monitoring and reactivation</li>
                        <li>Final server confirmation after synchronization</li>
                    </ul>
                </section>
            </div>
        </template>
    </div>
</x-app-layout>
