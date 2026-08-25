<div class="flex min-h-screen flex-col" x-data="examTaking(@js($payload))" x-cloak>
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
                </div>

                @if(($offlineMeta['supported'] ?? false) || ($offlineMode ?? false))
                    <div class="mt-6 rounded-card border border-brand/20 bg-brand-soft p-4 text-sm leading-6">
                        <p class="font-medium text-ink">Offline examination notice</p>
                        <p class="mt-2 text-muted">This examination can continue temporarily without an internet connection after preparation.</p>
                    </div>
                @endif

                <label class="mt-6 flex cursor-pointer items-start gap-3">
                    <input type="checkbox" class="mt-1 rounded border-line text-navy-800" x-model="policyAccepted">
                    <span class="text-sm leading-6">I have read and understood the Examination Rules and Warning Policy.</span>
                </label>

                <p class="mt-2 text-sm text-danger-ink" x-show="policyError" x-text="policyError"></p>
                <p class="mt-2 text-sm text-danger-ink" x-show="bootstrapError" x-text="bootstrapError"></p>

                <div class="mt-4 flex items-center gap-2 text-sm" x-show="offline.supported" x-cloak>
                    <span class="inline-block h-2 w-2 rounded-full" :class="networkOnline ? 'bg-success-ink' : 'bg-warning-ink'"></span>
                    <span x-text="networkOnline ? 'Connection: Online' : 'Connection: Offline'"></span>
                </div>

                <div class="mt-8 flex justify-end gap-2">
                    <button type="button" class="btn-secondary" x-show="offline.supported && !offlinePrepared && !offline.require_preparation && networkOnline" @click="prepareOfflineMode()" :disabled="preparingOffline || !policyAccepted">Prepare Offline Mode</button>
                    <button type="button" class="btn-primary" :disabled="!policyAccepted || policySubmitting" @click="acceptPolicy()">
                        <span x-show="!requireFullscreen">Start Examination</span>
                        <span x-show="requireFullscreen">Enter Fullscreen and Start Examination</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <template x-if="phase === 'preparing'">
        <div class="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center px-4 py-10 sm:px-6">
            <div class="rounded-modal border border-line bg-surface p-6 shadow-pop sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted">Preparing Examination</p>
                <h1 class="mt-2 text-xl font-semibold">Downloading examination for offline use</h1>
                <div class="mt-6">
                    <div class="h-2 overflow-hidden rounded-full bg-canvas">
                        <div class="h-full bg-brand transition-all duration-300" :style="`width: ${prepProgress?.percent || 0}%`"></div>
                    </div>
                    <p class="mt-2 text-sm text-muted" x-text="prepProgress?.complete ? 'Examination ready for offline use' : 'Preparing offline mode...'"></p>
                </div>
                <p class="mt-4 text-sm text-danger-ink" x-show="prepError" x-text="prepError"></p>
                <div class="mt-8 flex justify-end gap-2" x-show="prepStepsComplete">
                    <button type="button" class="btn-primary" @click="beginExamination()">Start Examination</button>
                </div>
            </div>
        </div>
    </template>

    <template x-if="phase === 'pending_submission'">
        <div class="mx-auto flex w-full max-w-xl flex-1 flex-col justify-center px-4 py-10 sm:px-6">
            <div class="rounded-modal border border-warning/30 bg-surface p-8 text-center shadow-pop">
                <h1 class="text-2xl font-semibold">Examination Submission Pending</h1>
                <p class="mt-3 text-sm leading-6 text-muted">Your examination has been completed and securely saved on this device.</p>
                <p class="mt-4 text-sm text-muted">Status: <span x-text="networkOnline ? 'Synchronizing...' : 'Waiting for internet connection'"></span></p>
                <div class="mt-8 flex justify-center gap-2">
                    <a :href="urls.syncStatus" class="btn-secondary">View Sync Status</a>
                    <button type="button" class="btn-primary" x-show="networkOnline" @click="syncWhenOnline()">Retry Sync</button>
                </div>
            </div>
        </div>
    </template>

    <template x-if="phase === 'locked'">
        <div class="mx-auto flex w-full max-w-xl flex-1 flex-col justify-center px-4 py-10 sm:px-6">
            <div class="rounded-modal border border-danger/30 bg-surface p-8 text-center shadow-pop">
                <h1 class="mt-5 text-2xl font-semibold">Examination Locked</h1>
                <p class="mt-4 text-lg font-semibold tabular-nums text-danger-ink">Warnings: <span x-text="warningCount"></span> / <span x-text="maxWarnings"></span></p>
                <p class="mt-4 text-sm text-muted">Your examination progress has been saved on this device.</p>
                <p class="mt-2 text-sm text-muted">Status: ⏳ Waiting to synchronize</p>
                <div class="mt-8">
                    <a :href="urls.offlineApp || urls.syncStatus" class="btn-secondary">Return</a>
                </div>
            </div>
        </div>
    </template>

    <template x-if="phase === 'resume'">
        <div class="mx-auto flex w-full max-w-xl flex-1 flex-col justify-center px-4 py-10 sm:px-6">
            <div class="rounded-modal border border-line bg-surface p-8 shadow-pop">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted">Examination In Progress</p>
                <h1 class="mt-2 text-2xl font-semibold" x-text="title"></h1>
                <p class="mt-3 text-sm text-muted">Progress: <span x-text="answeredCount"></span> of <span x-text="total"></span> questions</p>
                <p class="mt-2 text-sm text-muted" x-show="lastSavedAt">Last saved: <span x-text="lastSavedAt"></span></p>
                <div class="mt-8 flex justify-end gap-2">
                    <a :href="urls.offlineApp || urls.syncStatus" class="btn-secondary">Back</a>
                    <button type="button" class="btn-primary" @click="resumeExamination()">Resume Examination</button>
                </div>
            </div>
        </div>
    </template>

    <template x-if="phase === 'active'">
        <div class="flex min-h-screen flex-col">
            <header class="sticky top-0 z-20 border-b border-line bg-surface">
                <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold" x-text="title"></p>
                        <p class="text-xs text-muted" x-text="subjectCode"></p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="hidden items-center gap-2 text-xs sm:flex" :class="networkOnline ? 'text-success-ink' : 'text-warning-ink'">
                            <span class="inline-block h-2 w-2 rounded-full" :class="networkOnline ? 'bg-success-ink' : 'bg-warning-ink'"></span>
                            <span x-text="connectionLabel"></span>
                        </div>
                        <div class="rounded-btn border px-3 py-1 text-sm tabular-nums" :class="warningCount > 0 ? 'border-warning bg-warning-soft text-warning-ink' : 'border-line text-muted'">
                            Warnings: <span x-text="warningCount"></span> / <span x-text="maxWarnings"></span>
                        </div>
                        <div class="tabular-nums text-2xl font-semibold tracking-tight" x-text="clock"></div>
                    </div>
                </div>
            </header>

            <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 px-4 py-8 sm:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_14rem]">
                <main class="pb-28 lg:pb-8">
                    <p class="text-sm text-muted">Question <span x-text="current"></span> of <span x-text="total"></span></p>
                    <template x-for="(question, index) in questions" :key="question.id || index">
                        <article x-show="current === index + 1" class="exam-protected-content select-none">
                            <h1 class="mt-3 text-xl font-semibold leading-8" x-text="question.text"></h1>
                            <fieldset class="mt-6 space-y-3">
                                <template x-for="choice in question.choices" :key="choice.id">
                                    <label class="flex cursor-pointer items-start gap-3 rounded-card border border-line px-4 py-3 hover:bg-brand-soft" :class="answers[current] === choice.id ? 'border-brand bg-brand-soft' : ''">
                                        <input type="radio" class="mt-1 border-line text-navy-800" :name="'q'+current" :value="choice.id" @change="select(choice.id)" :checked="answers[current] === choice.id">
                                        <span><span class="mr-2 text-sm text-muted" x-text="choice.id"></span><span x-text="choice.text"></span></span>
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
                            <button type="button" class="h-9 rounded-btn border text-sm" @click="go(n)" x-text="n"></button>
                        </template>
                    </div>
                </aside>
            </div>

            <div class="fixed inset-x-0 bottom-0 border-t border-line bg-surface lg:static lg:border-0">
                <div class="mx-auto flex max-w-5xl items-center justify-between gap-2 px-4 py-3 sm:px-6">
                    <button type="button" class="btn-secondary" @click="prev()" :disabled="current === 1">Previous</button>
                    <div class="flex gap-2">
                        <button type="button" class="btn-ghost" @click="flag()">Flag</button>
                        <button type="button" class="btn-secondary" x-show="current < total" @click="next()">Next</button>
                        <button type="button" class="btn-primary" x-show="current === total" @click="submitOpen = true">Submit</button>
                    </div>
                </div>
            </div>

            <div x-show="submitOpen || submitting" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                <div class="absolute inset-0 bg-navy-950/50" @click="!submitting && (submitOpen = false)"></div>
                <div class="relative w-full max-w-md rounded-modal border border-line bg-surface p-6 shadow-pop">
                    <h2 class="text-lg font-semibold">Submit examination?</h2>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="submitOpen = false">Cancel</button>
                        <button type="button" class="btn-primary" @click="submitExam()">Submit Examination</button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <div x-show="violationModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-navy-950/60"></div>
        <div class="relative w-full max-w-md rounded-modal border border-warning/30 bg-surface p-6 shadow-pop">
            <h2 class="text-lg font-semibold">Examination Policy Warning</h2>
            <p class="mt-4 text-sm" x-text="violationMessage"></p>
            <button type="button" class="btn-primary mt-6" @click="acknowledgeViolation()">I Understand</button>
        </div>
    </div>

    <div x-show="phase === 'starting' || phase === 'loading'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-canvas">
        <div class="text-center">
            <p class="text-lg font-medium" x-text="phase === 'loading' ? 'Loading examination...' : 'Starting examination...'"></p>
        </div>
    </div>
</div>
