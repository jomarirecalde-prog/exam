<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header
            title="Google Classroom"
            subtitle="Optionally connect Google Classroom to help match your classes with subject offerings. Official enrollment remains authoritative."
        />

        @if (session('status'))
            <x-ui.alert type="success" class="mb-6">{{ session('status') }}</x-ui.alert>
        @endif

        @if (session('google_error'))
            <x-ui.alert type="error" title="Google Classroom" class="mb-6">{{ session('google_error') }}</x-ui.alert>
        @endif

        @error('google')
            <x-ui.alert type="error" title="Google Classroom" class="mb-6">{{ $message }}</x-ui.alert>
        @enderror

        <div
            x-data="{ online: navigator.onLine }"
            x-init="window.addEventListener('online', () => online = true); window.addEventListener('offline', () => online = false)"
            class="space-y-6"
        >
            <x-ui.alert type="warning" title="Internet connection required" x-show="!online" x-cloak>
                Google Classroom connection and synchronization require an internet connection.
            </x-ui.alert>

            <x-ui.card>
                <div class="space-y-4">
                    <h2 class="text-lg font-semibold">Connection Status</h2>

                    @if($connection)
                        <div class="rounded-lg border border-success-line bg-success-soft px-4 py-3 text-sm">
                            <p class="font-medium text-success-ink">Google Classroom Connected</p>
                            <p class="mt-1">{{ $connection->google_email }}</p>
                            @if($connection->last_synced_at)
                                <p class="mt-1 text-muted">Last synced: {{ $connection->last_synced_at->diffForHumans() }}</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('google-classroom.import') }}" class="btn-primary" x-bind:class="!online ? 'pointer-events-none opacity-50' : ''">Import from Google Classroom</a>
                            <form method="post" action="{{ route('google-classroom.disconnect') }}" onsubmit="return confirm('Disconnect Google Classroom? Saved course matches will be removed.');">
                                @csrf
                                <input type="hidden" name="confirm" value="1">
                                <button type="submit" class="btn-secondary text-danger-ink">Disconnect</button>
                            </form>
                        </div>
                    @else
                        <p class="text-sm text-muted">
                            Connect your Google Classroom account to help identify and match your available classes with subjects in this Examination Management System.
                            Google Classroom integration is optional.
                        </p>
                        @if($classroomEnabled)
                            <a href="{{ route('google-classroom.connect') }}" class="btn-primary inline-flex" x-bind:class="!online ? 'pointer-events-none opacity-50' : ''">Connect Google Classroom</a>
                        @else
                            <x-ui.alert type="info">Google Classroom integration is currently disabled by your administrator.</x-ui.alert>
                        @endif
                    @endif
                </div>
            </x-ui.card>

            @if($courseLinks->isNotEmpty())
                <x-ui.card>
                    <h2 class="text-lg font-semibold">Saved Course Matches</h2>
                    <div class="mt-4 space-y-3">
                        @foreach($courseLinks as $link)
                            <div class="rounded-lg border border-line px-4 py-3 text-sm">
                                <p class="font-medium">{{ $link->course_name }}</p>
                                @if($link->instructor_name)
                                    <p class="mt-1 text-muted">Instructor: {{ $link->instructor_name }}</p>
                                @endif
                                @if($link->subjectOffering)
                                    <p class="mt-2 text-muted">
                                        Matched offering:
                                        <span class="text-ink">{{ $link->subjectOffering->subject?->code }} — {{ $link->subjectOffering->subject?->name }}</span>
                                    </p>
                                    <p class="text-muted">Section: {{ $link->subjectOffering->sectionDisplayName() }} · Instructor: {{ $link->subjectOffering->instructorDisplayName() }}</p>
                                @else
                                    <p class="mt-2 text-warning-ink">No subject offering matched yet.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-app-layout>
