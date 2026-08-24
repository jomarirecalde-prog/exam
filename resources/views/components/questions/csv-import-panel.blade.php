@props([
    'templateUrl' => '',
    'previewUrl' => '',
    'confirmUrl' => '',
    'errorReportUrl' => '',
    'subjectField' => false,
    'subjects' => [],
    'importModes' => ['append' => 'Append imported questions', 'replace' => 'Replace existing questions'],
    'bankModes' => false,
])

<div
    x-data="csvImportPanel({
        templateUrl: @js($templateUrl),
        previewUrl: @js($previewUrl),
        confirmUrl: @js($confirmUrl),
        errorReportUrl: @js($errorReportUrl),
        subjectField: @js($subjectField),
        bankModes: @js($bankModes),
        importModes: @js($importModes),
    })"
    {{ $attributes }}
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-ink">Upload CSV File</h3>
            <p class="mt-1 text-sm text-muted">Import data into the system using a CSV file.</p>
        </div>
        @if ($templateUrl)
            <x-ui.button variant="secondary" size="sm" :href="$templateUrl">
                Download CSV Template
            </x-ui.button>
        @endif
    </div>

    @if ($subjectField)
        <div class="mt-4">
            <x-ui.field label="Subject" for="csv-subject-id">
                <select class="ui-input max-w-md" id="csv-subject-id" x-model="subjectId">
                    <option value="">Select subject</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->code }} — {{ $subject->name }}</option>
                    @endforeach
                </select>
                <p class="ui-error" x-show="subjectError" x-cloak x-text="subjectError"></p>
            </x-ui.field>
        </div>
    @endif

    <div
        class="mt-4 rounded-card border-2 border-dashed px-6 py-10 text-center transition-colors"
        :class="dragActive ? 'border-brand bg-brand-soft' : 'border-line bg-surface'"
        @dragover.prevent="dragActive = true"
        @dragleave.prevent="dragActive = false"
        @drop.prevent="handleDrop($event)"
    >
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-soft text-brand">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
        </div>
        <p class="mt-4 text-sm font-medium text-ink">Drag and drop your CSV file here</p>
        <p class="mt-1 text-sm text-muted">or</p>
        <input type="file" accept=".csv,text/csv" class="hidden" x-ref="csvInput" @change="handleFileSelect($event)">
        <x-ui.button class="mt-4" variant="secondary" size="sm" @click="$refs.csvInput.click()" x-bind:disabled="busy">
            Browse File
        </x-ui.button>
        <p class="mt-3 text-xs text-faint">Supported format: .CSV (max 2 MB)</p>
    </div>

    <div class="mt-4 rounded-card border border-line bg-surface px-4 py-3" x-show="selectedFile" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-ink">Selected File</p>
                <p class="text-sm text-muted" x-text="selectedFile?.name"></p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-faint" x-text="formatFileSize(selectedFile?.size)"></span>
                <button type="button" class="btn-ghost btn-sm text-danger-ink" @click="clearFile()">Remove</button>
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-4 text-sm" x-show="!bankModes">
        <template x-for="(label, value) in importModes" :key="value">
            <label class="flex items-center gap-2">
                <input type="radio" class="border-line text-navy-800" :value="value" x-model="importMode">
                <span x-text="label"></span>
            </label>
        </template>
    </div>

    <div class="mt-4 space-y-3" x-show="bankModes" x-cloak>
        <p class="text-sm font-medium text-ink">Import mode</p>
        <label class="flex items-start gap-2 text-sm">
            <input type="radio" class="mt-1 border-line text-navy-800" value="create" x-model="bankImportMode">
            <span><span class="font-medium">Create New Records Only</span><br><span class="text-muted">Skip rows that already exist in the question bank.</span></span>
        </label>
        <label class="flex items-start gap-2 text-sm">
            <input type="radio" class="mt-1 border-line text-navy-800" value="update" x-model="bankImportMode">
            <span><span class="font-medium">Update Existing Records</span><br><span class="text-muted">Update matching questions and skip new ones.</span></span>
        </label>
        <label class="flex items-start gap-2 text-sm">
            <input type="radio" class="mt-1 border-line text-navy-800" value="upsert" x-model="bankImportMode">
            <span><span class="font-medium">Create or Update Records</span><br><span class="text-muted">Create new questions or update existing matches.</span></span>
        </label>
    </div>

    <div class="mt-4 rounded-card border border-info/30 bg-info-soft px-4 py-3 text-sm text-info-ink" x-show="statusMessage" x-cloak>
        <div class="flex items-center gap-2">
            <svg x-show="busy" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span x-text="statusMessage"></span>
        </div>
    </div>

    <template x-if="rowErrors.length">
        <div class="mt-4 rounded-card border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger-ink">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-medium">Import issues</p>
                <button type="button" class="btn-ghost btn-sm" @click="downloadErrorReport()" x-show="errorReportUrl && selectedFile" x-cloak>
                    Download error report
                </button>
            </div>
            <ul class="mt-3 space-y-3">
                <template x-for="(error, index) in rowErrors" :key="'csv-error-' + index">
                    <li>
                        <p class="font-medium" x-text="'Row ' + error.row"></p>
                        <p x-text="error.field + ': ' + error.message"></p>
                    </li>
                </template>
            </ul>
        </div>
    </template>

    <div class="mt-4 flex flex-wrap justify-end gap-2">
        <x-ui.button variant="secondary" @click="clearFile()" x-bind:disabled="busy || !selectedFile">Cancel</x-ui.button>
        <x-ui.button @click="startPreview()" x-bind:disabled="busy || !selectedFile">
            <span x-show="!busy">Import Data</span>
            <span x-show="busy" x-cloak>Processing…</span>
        </x-ui.button>
    </div>

    <div
        x-show="previewOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-navy-950/50" @click="previewOpen = false"></div>
        <div class="relative mx-auto mt-8 w-full max-w-4xl overflow-hidden rounded-modal border border-line bg-surface shadow-pop">
            <div class="border-b border-line px-6 py-4">
                <h2 class="text-lg font-semibold text-ink">CSV Import Preview</h2>
                <p class="mt-1 text-sm text-muted">Review the detected records before confirming the import.</p>
            </div>
            <div class="grid gap-4 px-6 py-5 sm:grid-cols-4">
                <div class="rounded-card border border-line px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-muted">Total Records</p>
                    <p class="mt-1 text-2xl font-semibold" x-text="stats.total ?? 0"></p>
                </div>
                <div class="rounded-card border border-line px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-muted">Valid Records</p>
                    <p class="mt-1 text-2xl font-semibold text-success-ink" x-text="stats.valid ?? 0"></p>
                </div>
                <div class="rounded-card border border-line px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-muted">Records with Errors</p>
                    <p class="mt-1 text-2xl font-semibold text-danger-ink" x-text="stats.errors ?? 0"></p>
                </div>
                <div class="rounded-card border border-line px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-muted">Duplicates</p>
                    <p class="mt-1 text-2xl font-semibold text-warning-ink" x-text="stats.duplicates ?? 0"></p>
                </div>
            </div>
            <div class="px-6 pb-5">
                <div class="overflow-x-auto rounded-card border border-line">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Question</th>
                                <th>Type</th>
                                <th>Topic</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in previewRows" :key="'preview-' + row.row">
                                <tr>
                                    <td x-text="row.row"></td>
                                    <td class="max-w-sm" x-text="row.question"></td>
                                    <td x-text="row.type"></td>
                                    <td x-text="row.topic"></td>
                                    <td>
                                        <span
                                            class="ui-badge"
                                            :class="{
                                                'bg-success-soft text-success-ink': row.status === 'valid',
                                                'bg-danger-soft text-danger-ink': row.status === 'error',
                                                'bg-warning-soft text-warning-ink': row.status === 'duplicate',
                                            }"
                                            x-text="row.status === 'valid' ? 'Valid' : (row.status === 'duplicate' ? 'Duplicate' : 'Error')"
                                        ></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
                <x-ui.button variant="secondary" @click="previewOpen = false">Cancel</x-ui.button>
                <x-ui.button @click="openConfirm()" x-bind:disabled="!previewToken || (stats.valid ?? 0) === 0">
                    Continue
                </x-ui.button>
            </div>
        </div>
    </div>

    <div
        x-show="confirmOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-navy-950/50" @click="confirmOpen = false"></div>
        <div class="relative mx-auto mt-24 w-full max-w-md overflow-hidden rounded-modal border border-line bg-surface shadow-pop">
            <div class="px-6 py-5">
                <h2 class="text-lg font-semibold text-ink">Confirm Import</h2>
                <p class="mt-2 text-sm leading-6 text-muted">
                    You are about to import <span class="font-medium text-ink" x-text="stats.valid ?? 0"></span> valid record(s) into the system.
                    This action will <span x-text="bankModes ? 'create or update records in the question bank based on the selected import mode.' : 'add the validated questions to this examination.'"></span>
                </p>
            </div>
            <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
                <x-ui.button variant="secondary" @click="confirmOpen = false" x-bind:disabled="confirming">Cancel</x-ui.button>
                <x-ui.button @click="confirmImport()" x-bind:disabled="confirming">
                    <span x-show="!confirming">Confirm Import</span>
                    <span x-show="confirming" x-cloak>Importing…</span>
                </x-ui.button>
            </div>
        </div>
    </div>
</div>
