        <section>
            <h2 class="ui-kicker">Overview</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Students" :value="number_format($counts['students'])" />
                <x-ui.stat label="Instructors" :value="number_format($counts['instructors'])" />
                <x-ui.stat label="Active Exams" :value="number_format($counts['activeExams'])" />
                <x-ui.stat label="Results" :value="number_format($counts['results'])" />
            </div>
        </section>

        <section>
            <h2 class="ui-section">Examination Activity</h2>
            <x-ui.card class="mt-4">
                @php
                    $max = max(1, (int) $chart->max());
                @endphp
                @if ($chart->isEmpty())
                    <x-ui.empty-state title="No examination activity yet." icon="bar-chart-3">
                        Published examinations will appear here as a simple activity overview.
                    </x-ui.empty-state>
                @else
                    <div class="flex h-40 items-end gap-6">
                        @foreach ($chart as $status => $total)
                            <div class="flex flex-1 flex-col items-center gap-2">
                                <div class="flex h-28 w-full items-end justify-center">
                                    <div class="w-8 rounded-t-md bg-brand" style="height: {{ max(8, ($total / $max) * 100) }}%"></div>
                                </div>
                                <p class="text-xs text-muted">{{ \Illuminate\Support\Str::title(strtolower($status)) }}</p>
                                <p class="text-sm font-medium">{{ $total }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </section>

        <section>
            <h2 class="ui-section">Upcoming Examinations</h2>
            <div class="ui-table-wrap mt-4">
                @if ($upcomingExams->isEmpty())
                    <div class="px-5">
                        <x-ui.empty-state title="No upcoming examinations." action="Create Examination" :action-href="route('examinations.create')" icon="clipboard-list">
                            Create an examination to see it here.
                        </x-ui.empty-state>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Examination</th>
                                    <th>Subject</th>
                                    <th>Section</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($upcomingExams as $exam)
                                    <tr>
                                        <td class="font-medium">{{ $exam->title }}</td>
                                        <td class="text-muted">{{ $exam->subject?->code }}</td>
                                        <td class="text-muted">{{ $exam->section?->name }}</td>
                                        <td><x-ui.badge :status="$exam->statusKey()" /></td>
                                        <td class="text-right">
                                            <a href="{{ route('examinations.index') }}" class="text-sm font-medium hover:underline">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <section>
            <h2 class="ui-section">Recent Activity</h2>
            <x-ui.card class="mt-4" :padding="false">
                @if ($activity->isEmpty())
                    <div class="px-5">
                        <x-ui.empty-state title="No recent activity." icon="activity">
                            Platform events will appear here as staff create and conduct examinations.
                        </x-ui.empty-state>
                    </div>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($activity as $item)
                            <li class="flex items-start gap-3 px-5 py-4">
                                <span class="mt-1 h-1.5 w-1.5 rounded-full bg-brand"></span>
                                <div>
                                    <p class="text-sm font-medium">{{ $item->action }}</p>
                                    <p class="text-sm text-muted">{{ $item->module }} · {{ \Illuminate\Support\Carbon::parse($item->created_at)->diffForHumans() }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </section>
