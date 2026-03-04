<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Git Dash — {{ $username }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 min-h-screen font-[Instrument_Sans,sans-serif]">

    {{-- Header --}}
    <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-0 z-10">
        <div class="max-w-5xl mx-auto px-4 py-4 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <h1 class="text-xl font-semibold"><a href="{{ route('dashboard') }}">Git Dash</a></h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Commits by <strong>{{ $username }}</strong></p>
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2">
                @if ($connections->count() > 1)
                    <select name="connection"
                            onchange="this.form.submit()"
                            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach ($connections as $conn)
                            <option value="{{ $conn }}" @selected($conn === $connection)>{{ $conn }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" name="connection" value="{{ $connection }}">
                @endif

                <input type="hidden" name="view" value="{{ $view }}">

                <select id="preset-select"
                        class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Quick select…</option>
                    <option value="this_week">This week</option>
                    <option value="last_week">Last week</option>
                    <option value="this_month">This month</option>
                    <option value="last_month">Last month</option>
                    <option value="last_7">Last 7 days</option>
                    <option value="last_30">Last 30 days</option>
                    <option value="last_90">Last 90 days</option>
                    <option value="this_year">This year</option>
                </select>

                <input type="date" id="input-from" name="from" value="{{ $from }}"
                       class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <span class="text-gray-400 text-sm">to</span>
                <input type="date" id="input-to" name="to" value="{{ $to }}"
                       class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">

                <button type="submit"
                        class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 text-sm font-medium transition-colors">
                    Filter
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6">

        @php
            /** Format minutes as e.g. "1h 30m" or "45m" */
            $fmt = function (int $minutes): string {
                if ($minutes < 60) {
                    return $minutes . 'm';
                }
                $h = intdiv($minutes, 60);
                $m = $minutes % 60;
                return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
            };
        @endphp

        {{-- Summary --}}
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalCommits }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Commits</div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalRepos }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Repositories</div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $commitsByDate->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Active days</div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 text-center">
                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">~{{ $fmt($totalMinutes) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Est. time</div>
            </div>
        </div>

        {{-- View toggle tabs --}}
        <div class="flex gap-1 mb-6 border-b border-gray-200 dark:border-gray-800">
            <a href="{{ route('dashboard', array_merge(request()->query(), ['view' => 'timeline'])) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                      {{ $view === 'timeline'
                          ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                          : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                Timeline
            </a>
            <a href="{{ route('dashboard', array_merge(request()->query(), ['view' => 'repositories'])) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                      {{ $view === 'repositories'
                          ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                          : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                By Repository
            </a>
        </div>

        {{-- ── By Repository view ── --}}
        @if ($view === 'repositories')

            @if ($timeByRepo->isEmpty())
                <div class="text-center py-16 text-gray-400 dark:text-gray-500">
                    <div class="text-4xl mb-3">🔍</div>
                    <p class="text-lg font-medium">No commits found</p>
                    <p class="text-sm mt-1">Try adjusting the date range.</p>
                </div>
            @else
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($timeByRepo as $repo)
                        @php $pct = $totalMinutes > 0 ? round($repo['minutes'] / $totalMinutes * 100) : 0; @endphp
                        <details class="group">
                            <summary class="px-4 py-3 flex items-center gap-4 cursor-pointer list-none hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors select-none">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 mb-1.5">
                                        <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500 transition-transform duration-150 group-open:rotate-90"
                                             viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                                        </svg>
                                        <a href="{{ $repo['html_url'] }}" target="_blank"
                                           class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                                           onclick="event.stopPropagation()">
                                            {{ $repo['full_name'] }}
                                        </a>
                                    </div>
                                    <div class="h-1.5 w-full bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-indigo-500 dark:bg-indigo-400 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">~{{ $fmt($repo['minutes']) }}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $repo['commit_count'] }} {{ Str::plural('commit', $repo['commit_count']) }}
                                        · {{ $pct }}%
                                    </div>
                                </div>
                            </summary>

                            <div class="border-t border-gray-100 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($repo['commits'] as $commit)
                                    @php
                                        $message = explode("\n", trim($commit['commit']['message']))[0];
                                        $sha = substr($commit['sha'], 0, 7);
                                        $commitDate = \Carbon\Carbon::parse($commit['commit']['author']['date']);
                                        $commitUrl = $commit['html_url'];
                                    @endphp
                                    <div class="px-4 py-2.5 flex items-center gap-3 bg-gray-50/50 dark:bg-gray-800/30 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                        <div class="shrink-0 text-xs text-gray-400 dark:text-gray-500 w-36 tabular-nums">
                                            {{ $commitDate->format('D M j') }}
                                            <span class="text-gray-300 dark:text-gray-600">·</span>
                                            {{ $commitDate->format('H:i') }}
                                        </div>
                                        <a href="{{ $commitUrl }}" target="_blank"
                                           class="flex-1 min-w-0 text-sm text-gray-700 dark:text-gray-300 hover:underline truncate">
                                            {{ $message }}
                                        </a>
                                        <a href="{{ $commitUrl }}" target="_blank"
                                           class="shrink-0 font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-500 dark:text-gray-400">
                                            {{ $sha }}
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif

        {{-- ── Timeline view ── --}}
        @else

            @forelse ($commitsByDate as $date => $dayData)
                <div class="mb-8">

                    {{-- Date header --}}
                    <div class="flex items-center gap-3 mb-3">
                        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                            {{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }}
                        </h2>
                        @php $dayCommitCount = collect($dayData['sessions'])->sum(fn($s) => $s['commits']->count()); @endphp
                        <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-full px-2 py-0.5">
                            {{ $dayCommitCount }} {{ Str::plural('commit', $dayCommitCount) }}
                        </span>
                        <span class="text-xs bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 rounded-full px-2 py-0.5">
                            ~{{ $fmt($dayData['total_minutes']) }}
                        </span>
                    </div>

                    {{-- Work sessions for this day --}}
                    <div class="space-y-3">
                        @foreach ($dayData['sessions'] as $session)
                            <div>
                                @if (count($dayData['sessions']) > 1)
                                    <div class="flex items-center gap-2 mb-1.5 ml-1">
                                        <span class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ $session['commits']->first()['repository']['full_name'] }} — ~{{ $fmt($session['minutes']) }}
                                        </span>
                                    </div>
                                @endif

                                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($session['commits'] as $item)
                                        @php
                                            $message = explode("\n", trim($item['commit']['message']))[0];
                                            $sha = substr($item['sha'], 0, 7);
                                            $time = \Carbon\Carbon::parse($item['commit']['author']['date'])->format('H:i');
                                            $repoName = $item['repository']['full_name'];
                                            $repoUrl = $item['repository']['html_url'];
                                            $commitUrl = $item['html_url'];
                                        @endphp
                                        <div class="px-4 py-3 flex items-start gap-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-0.5">
                                                    <a href="{{ $repoUrl }}" target="_blank"
                                                       class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline truncate">
                                                        {{ $repoName }}
                                                    </a>
                                                </div>
                                                <a href="{{ $commitUrl }}" target="_blank"
                                                   class="text-sm text-gray-800 dark:text-gray-200 hover:underline line-clamp-2 break-words">
                                                    {{ $message }}
                                                </a>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0 text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                <span>{{ $time }}</span>
                                                <a href="{{ $commitUrl }}" target="_blank"
                                                   class="font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                                                    {{ $sha }}
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div>
            @empty
                <div class="text-center py-16 text-gray-400 dark:text-gray-500">
                    <div class="text-4xl mb-3">🔍</div>
                    <p class="text-lg font-medium">No commits found</p>
                    <p class="text-sm mt-1">Try adjusting the date range.</p>
                </div>
            @endforelse

        @endif

    </main>
    <script>
        document.getElementById('preset-select').addEventListener('change', function () {
            const preset = this.value;
            if (!preset) return;

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            let from = new Date(today), to = new Date(today);

            const dow = today.getDay(); // 0=Sun … 6=Sat
            const mondayOffset = dow === 0 ? -6 : 1 - dow;

            switch (preset) {
                case 'this_week':
                    from.setDate(today.getDate() + mondayOffset);
                    break;
                case 'last_week':
                    from.setDate(today.getDate() + mondayOffset - 7);
                    to.setDate(today.getDate() + mondayOffset - 1);
                    break;
                case 'this_month':
                    from = new Date(today.getFullYear(), today.getMonth(), 1);
                    break;
                case 'last_month':
                    from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    to   = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                case 'last_7':
                    from.setDate(today.getDate() - 6);
                    break;
                case 'last_30':
                    from.setDate(today.getDate() - 29);
                    break;
                case 'last_90':
                    from.setDate(today.getDate() - 89);
                    break;
                case 'this_year':
                    from = new Date(today.getFullYear(), 0, 1);
                    break;
            }

            const fmt = d => [
                d.getFullYear(),
                String(d.getMonth() + 1).padStart(2, '0'),
                String(d.getDate()).padStart(2, '0'),
            ].join('-');
            document.getElementById('input-from').value = fmt(from);
            document.getElementById('input-to').value   = fmt(to);
            document.getElementById('input-from').closest('form').submit();
        });
    </script>
</body>
</html>
