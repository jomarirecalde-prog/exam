<x-app-layout>
    <div class="ui-page" x-data="questionBankPage(@js([
        'exportUrl' => $csv['exportUrl'],
        'filters' => $filters,
    ]))">
        <x-ui.page-header title="Question Bank" subtitle="Reusable questions for examinations." />

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div>
                <form method="GET" action="{{ route('questions.index') }}" class="ui-card ui-card-pad">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="Search" for="search">
                            <input class="ui-input" id="search" name="search" value="{{ $filters['search'] }}" placeholder="Search questions">
                        </x-ui.field>
                        <x-ui.field label="Subject" for="subject_id">
                            <select class="ui-input" id="subject_id" name="subject_id">
                                <option value="">All subjects</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}" @selected((string) $filters['subject_id'] === (string) $subject->id)>
                                        {{ $subject->code }} — {{ $subject->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field label="Difficulty" for="difficulty">
                            <select class="ui-input" id="difficulty" name="difficulty">
                                <option value="">All difficulties</option>
                                @foreach (['easy', 'medium', 'hard'] as $level)
                                    <option value="{{ $level }}" @selected($filters['difficulty'] === $level)>{{ ucfirst($level) }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field label="Type" for="type">
                            <select class="ui-input" id="type" name="type">
                                <option value="">All types</option>
                                @foreach (['multiple_choice', 'true_false', 'identification', 'essay'] as $type)
                                    <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-ui.button type="submit">Apply Filters</x-ui.button>
                        <x-ui.button variant="secondary" :href="route('questions.index')">Reset</x-ui.button>
                    </div>
                </form>

                <div class="ui-table-wrap mt-4">
                    @if ($questions->isEmpty())
                        <div class="px-5 py-8">
                            <x-ui.empty-state title="No questions yet." icon="file-question">
                                Import questions from CSV or add them while creating an examination.
                            </x-ui.empty-state>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Question</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Difficulty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($questions as $question)
                                        <tr>
                                            <td class="max-w-md font-medium">{{ \Illuminate\Support\Str::limit($question->question_text, 80) }}</td>
                                            <td class="text-muted">{{ str_replace('_', ' ', $question->type->value) }}</td>
                                            <td class="text-muted">{{ $question->subject?->code }}</td>
                                            <td class="text-muted">{{ ucfirst($question->difficulty) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-line px-4 py-3">{{ $questions->links() }}</div>
                    @endif
                </div>
            </div>

            <aside class="space-y-6">
                <div class="ui-card ui-card-pad">
                    <h3 class="text-base font-semibold text-ink">Export Data</h3>
                    <p class="mt-1 text-sm text-muted">Download question bank data based on your selected filters.</p>

                    <div class="mt-4 space-y-4">
                        <x-ui.field label="Format">
                            <select class="ui-input" x-model="exportFormat">
                                <option value="csv">CSV</option>
                            </select>
                        </x-ui.field>

                        <div>
                            <p class="ui-label">Records</p>
                            <div class="mt-2 space-y-2 text-sm">
                                <label class="flex items-center gap-2">
                                    <input type="radio" class="border-line text-navy-800" value="all" x-model="exportScope">
                                    All records
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" class="border-line text-navy-800" value="filtered" x-model="exportScope">
                                    Filtered records
                                </label>
                            </div>
                        </div>

                        <x-ui.button class="w-full" @click="exportCsv()" x-bind:disabled="exporting">
                            <span x-show="!exporting">Export Data</span>
                            <span x-show="exporting" x-cloak>Exporting…</span>
                        </x-ui.button>
                    </div>
                </div>

                <div class="ui-card ui-card-pad">
                    <x-questions.csv-import-panel
                        :template-url="$csv['templateUrl']"
                        :preview-url="$csv['previewUrl']"
                        :confirm-url="$csv['confirmUrl']"
                        :error-report-url="$csv['errorReportUrl']"
                        :subject-field="true"
                        :subjects="$subjects"
                        :bank-modes="true"
                        @csv-imported.window="window.location.reload()"
                    />
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
