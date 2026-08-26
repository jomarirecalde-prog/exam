<x-app-layout>
    <div class="ui-page" x-data="googleClassroomImport(@js([
        'matches' => $matches,
        'manualOfferings' => $manualOfferings,
        'confirmUrl' => route('google-classroom.confirm'),
    ]))">
        <x-ui.page-header
            title="Import from Google Classroom"
            subtitle="Select the classes you want to connect and confirm matches with system subject offerings."
        />

        @if(empty($matches))
            <x-ui.empty-state
                title="No Classroom courses found"
                description="We could not find active Google Classroom courses for your account."
            >
                <a href="{{ route('google-classroom.index') }}" class="btn-secondary">Back</a>
            </x-ui.empty-state>
        @else
            <form method="post" action="{{ route('google-classroom.confirm') }}" @submit="prepareSubmit" class="space-y-6">
                @csrf

                <template x-for="(item, index) in selections" :key="item.google_course_id">
                    <input type="hidden" :name="`selections[${index}][google_course_id]`" :value="item.google_course_id">
                    <input type="hidden" :name="`selections[${index}][course_name]`" :value="item.course_name">
                    <input type="hidden" :name="`selections[${index}][course_section]`" :value="item.course_section || ''">
                    <input type="hidden" :name="`selections[${index}][instructor_name]`" :value="item.instructor_name || ''">
                    <input type="hidden" :name="`selections[${index}][subject_offering_id]`" :value="item.subject_offering_id || ''">
                    <input type="hidden" :name="`selections[${index}][match_confidence]`" :value="item.match_confidence || ''">
                    <input type="hidden" :name="`selections[${index}][manual]`" :value="item.manual ? 1 : 0">
                </template>

                <div class="space-y-4">
                    <template x-for="(item, index) in matches" :key="item.course.id">
                        <x-ui.card>
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="checkbox" class="mt-1 h-5 w-5 rounded border-line" :checked="isSelected(item.course.id)" @change="toggleCourse(item)">
                                <span class="min-w-0 flex-1 space-y-4">
                                    <span>
                                        <span class="block text-lg font-semibold" x-text="item.course.name"></span>
                                        <span class="mt-1 block text-sm text-muted" x-show="item.course.instructor_name">
                                            Instructor: <span class="text-ink" x-text="item.course.instructor_name"></span>
                                        </span>
                                    </span>

                                    <template x-if="isSelected(item.course.id)">
                                        <span class="block space-y-4">
                                            <template x-if="item.match && item.confidence !== 'none' && !manualSelections[item.course.id]">
                                                <span class="block rounded-lg border border-line bg-surface-2 p-4 text-sm">
                                                    <span class="block font-semibold">Match with System Subject Offering</span>
                                                    <span class="mt-2 block" x-text="item.match.name"></span>
                                                    <span class="block text-muted">Subject Code: <span class="text-ink" x-text="item.match.code"></span></span>
                                                    <span class="block text-muted">Instructor: <span class="text-ink" x-text="item.match.instructor_name"></span></span>
                                                    <span class="block text-muted">Section: <span class="text-ink" x-text="item.match.section_name"></span></span>
                                                    <span class="mt-2 block font-medium" x-text="'Match Confidence: ' + capitalize(item.confidence)"></span>
                                                </span>
                                            </template>

                                            <template x-if="!item.match || item.confidence === 'none' || item.manual_required || manualSelections[item.course.id]">
                                                <span class="block space-y-3 rounded-lg border border-warning-line bg-warning-soft p-4 text-sm">
                                                    <span class="block font-semibold text-warning-ink">No Automatic Match Found</span>
                                                    <span class="block text-muted">Please manually select the corresponding subject offering.</span>
                                                    <select class="ui-input" @change="setManualOffering(item.course.id, $event.target.value)">
                                                        <option value="">Select subject manually</option>
                                                        <optgroup label="Recommended">
                                                            <template x-for="offering in manualOfferings.recommended" :key="'rec-' + offering.id">
                                                                <option :value="offering.id" x-text="`${offering.code} — ${offering.name} (${offering.instructor_name}, ${offering.section_name})`"></option>
                                                            </template>
                                                        </optgroup>
                                                        <optgroup label="Other Available Subjects">
                                                            <template x-for="offering in manualOfferings.other" :key="'other-' + offering.id">
                                                                <option :value="offering.id" x-text="`${offering.code} — ${offering.name} (${offering.instructor_name}, ${offering.section_name})`"></option>
                                                            </template>
                                                        </optgroup>
                                                    </select>
                                                </span>
                                            </template>

                                            <button
                                                type="button"
                                                class="text-sm font-medium text-brand hover:underline"
                                                x-show="item.match && !manualSelections[item.course.id]"
                                                @click="enableManual(item.course.id)"
                                            >
                                                Select Subject Manually
                                            </button>
                                        </span>
                                    </template>
                                </span>
                            </label>
                        </x-ui.card>
                    </template>
                </div>

                <div class="flex flex-wrap justify-end gap-3">
                    <a href="{{ route('google-classroom.index') }}" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary" :disabled="selections.length === 0 || submitting">
                        <span x-show="!submitting">Continue</span>
                        <span x-show="submitting">Saving...</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
