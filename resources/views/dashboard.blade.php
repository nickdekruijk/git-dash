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
                <h1 class="text-xl font-semibold">Git Dash</h1>
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

                <input type="date" name="from" value="{{ $from }}"
                       class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <span class="text-gray-400 text-sm">to</span>
                <input type="date" name="to" value="{{ $to }}"
                       class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">

                <button type="submit"
                        class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 text-sm font-medium transition-colors">
                    Filter
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6">

        {{-- Summary --}}
        @php
            $totalCommits = $commitsByDate->flatten(1)->count();
            $totalRepos = $commitsByDate->flatten(1)->pluck('repository.full_name')->unique()->count();
        @endphp

        <div class="grid grid-cols-3 gap-4 mb-8">
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
        </div>

        {{-- Commits grouped by date --}}
        @forelse ($commitsByDate as $date => $commits)
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-3">
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        {{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }}
                    </h2>
                    <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-full px-2 py-0.5">
                        {{ $commits->count() }} {{ Str::plural('commit', $commits->count()) }}
                    </span>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($commits as $item)
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
        @empty
            <div class="text-center py-16 text-gray-400 dark:text-gray-500">
                <div class="text-4xl mb-3">🔍</div>
                <p class="text-lg font-medium">No commits found</p>
                <p class="text-sm mt-1">Try adjusting the date range.</p>
            </div>
        @endforelse

    </main>

</body>
</html>
