<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Question Bank" subtitle="Reusable questions for examinations." />
        <x-ui.toolbar placeholder="Search questions" />
        <div class="ui-table-wrap mt-4">
            @if ($questions->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No questions yet." icon="file-question">
                        Add questions here, then attach them while creating an examination.
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
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($questions as $question)
                                <tr>
                                    <td class="max-w-md font-medium">{{ \Illuminate\Support\Str::limit($question->question_text, 80) }}</td>
                                    <td class="text-muted">{{ str_replace('_', ' ', $question->type->value) }}</td>
                                    <td class="text-muted">{{ $question->subject?->code }}</td>
                                    <td class="text-muted">{{ ucfirst($question->difficulty) }}</td>
                                    <td class="text-right">
                                        <button type="button" class="btn-ghost btn-sm">View</button>
                                        <button type="button" class="btn-ghost btn-sm">Edit</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-4 py-3">{{ $questions->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
