<div>
    {{-- Loading indicator bar --}}
    <div wire:loading class="fixed top-0 left-0 right-0 h-1 bg-indigo-500 z-50 animate-pulse"></div>

    {{-- Header --}}
    <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-0 z-10">
        {{-- Top row: logo + filters + menu --}}
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3">
            <h1 class="text-xl font-semibold shrink-0"><a href="{{ route('dashboard') }}">Git Dash</a></h1>

            <div class="flex-1"></div>

            {{-- Filters inline in header --}}
            <div class="flex items-center gap-2">
                <select wire:model.live="preset"
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
                    <option value="last_year">Last year</option>
                    <option value="this_quarter">This quarter</option>
                    <option value="last_quarter">Last quarter</option>
                </select>
                <input type="date" wire:model.live="from"
                    class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <span class="text-gray-400 text-sm">to</span>
                <input type="date" wire:model.live="to"
                    class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">

                @if ($lockedConnection === null && $connections->count() > 1)
                    <select wire:model.live="connection"
                        class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach ($connections as $conn)
                            <option value="{{ $conn->name }}">{{ $conn->label }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            {{-- Share mode badge --}}
            @if ($lockedConnection !== null)
                <span class="text-xs bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 rounded-full px-3 py-1 shrink-0">
                    Shared view · {{ $lockedConnection }}{{ $lockedRepository ? ' · ' . $lockedRepository : '' }}
                </span>
            @endif

            @if ($lockedConnection === null)
                {{-- Dropdown menu --}}
                <div x-data="{ open: false }" class="relative shrink-0">
                    <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400 px-3 py-1.5 text-sm transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" x-transition
                        class="absolute right-0 mt-1 w-44 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1 z-20">
                        @if ($connections->isNotEmpty())
                            <button wire:click="$set('view', 'sharing')" @click="open = false"
                                class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 {{ $view === 'sharing' ? 'font-medium text-indigo-600 dark:text-indigo-400' : '' }}">
                                Share Links
                            </button>
                        @endif
                        <button wire:click="$set('view', 'connections')" @click="open = false"
                            class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 {{ $view === 'connections' ? 'font-medium text-indigo-600 dark:text-indigo-400' : '' }}">
                            Connections
                        </button>
                        <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6" wire:loading.class="opacity-50">

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

        {{-- Summary cards (hidden on Share Links / Connections tabs, or when no connections) --}}
        @if ($view !== 'sharing' && $view !== 'connections' && $connections->isNotEmpty())
            <div class="grid @if ($lockedRepository) grid-cols-3 @else grid-cols-4 @endif gap-4 mb-4">
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 text-center">
                    <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalCommits }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Commits</div>
                </div>
                @if (!$lockedRepository)
                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 text-center">
                        <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalRepos }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Repositories</div>
                    </div>
                @endif
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 text-center">
                    <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $commitsByDate->count() }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Active days</div>
                </div>
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 text-center">
                    <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">~{{ $fmt($totalMinutes) }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Est. time</div>
                </div>
            </div>
        @endif

        {{-- View tabs (only shown when there is more than one tab to choose from) --}}
        @if ($connections->isNotEmpty() && !$lockedRepository)
            <div class="flex gap-1 mb-6 border-b border-gray-200 dark:border-gray-800">
                <button wire:click="$set('view', 'timeline')"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                       {{ $view === 'timeline' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                    Timeline
                </button>
                <button wire:click="$set('view', 'repositories')"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                       {{ $view === 'repositories' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                    By Repository
                </button>
            </div>
        @endif

        {{-- ── By Repository view ── --}}
        @if ($view === 'repositories' && $connections->isNotEmpty())

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
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
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
                                        <button wire:click="selectCommit('{{ $commit['sha'] }}', '{{ $repo['full_name'] }}')"
                                            class="contents">
                                        <div class="shrink-0 text-xs text-gray-400 dark:text-gray-500 w-36 tabular-nums">
                                            {{ $commitDate->format('D M j') }}
                                            <span class="text-gray-300 dark:text-gray-600">·</span>
                                            {{ $commitDate->format('H:i') }}
                                        </div>
                                        <span class="flex-1 min-w-0 text-sm text-gray-700 dark:text-gray-300 truncate text-left">
                                            {{ $message }}
                                        </span>
                                        <span class="shrink-0 font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-gray-500 dark:text-gray-400">
                                            {{ $sha }}
                                        </span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif

            {{-- ── Timeline view ── --}}
        @elseif ($view === 'timeline' && $connections->isNotEmpty())
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

                    {{-- Work sessions --}}
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
                                        <button wire:click="selectCommit('{{ $item['sha'] }}', '{{ $repoName }}')"
                                            class="px-4 py-3 flex items-start gap-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors w-full text-left">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-0.5">
                                                    <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400 truncate">
                                                        {{ $repoName }}
                                                    </span>
                                                </div>
                                                <span class="text-sm text-gray-800 dark:text-gray-200 line-clamp-2 break-words">
                                                    {{ $message }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0 text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                <span>{{ $time }}</span>
                                                <span class="font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
                                                    {{ $sha }}
                                                </span>
                                            </div>
                                        </button>
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

        {{-- ── Share Links tab (owner only) ── --}}
        @if ($view === 'sharing')

            {{-- Create new token --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 mb-4">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Generate a read-only share link, optionally scoped to a single repository.</p>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Label (optional)</label>
                        <input type="text" wire:model="newTokenLabel" placeholder="e.g. John's view"
                            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-44">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Connection</label>
                        <select wire:model="newTokenConnection"
                            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach ($connections as $conn)
                                <option value="{{ $conn->name }}">{{ $conn->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Repository <span class="text-gray-400">(optional)</span></label>
                        <select wire:model="newTokenRepository"
                            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-56">
                            <option value="">All repositories</option>
                            @foreach ($newTokenRepositories as $repo)
                                <option value="{{ $repo }}">{{ $repo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button wire:click="createToken"
                        class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 text-sm font-medium transition-colors">
                        Generate link
                    </button>
                </div>
            </div>

            {{-- Token list --}}
            @if ($shareTokens->isEmpty())
                <p class="text-sm text-gray-400 dark:text-gray-500">No share links yet.</p>
            @else
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($shareTokens as $st)
                        <div class="px-4 py-3 flex items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @if ($st->label)
                                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $st->label }}</span>
                                        <span class="text-xs text-gray-400">·</span>
                                    @endif
                                    <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded px-1.5 py-0.5">{{ $st->connection }}</span>
                                    @if ($st->repository)
                                        <span class="text-xs bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 rounded px-1.5 py-0.5 font-mono">{{ $st->repository }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <input type="text" readonly
                                        value="{{ url('/s/' . $st->token) }}"
                                        onclick="this.select()"
                                        class="text-xs font-mono text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-2 py-0.5 w-full max-w-md cursor-text">
                                </div>
                            </div>
                            <div class="text-xs text-gray-400 shrink-0">{{ $st->created_at->diffForHumans() }}</div>
                            <button wire:click="deleteToken({{ $st->id }})"
                                wire:confirm="Revoke this share link?"
                                class="shrink-0 text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400 transition-colors">
                                Revoke
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

        @endif

        {{-- ── Connections tab (owner only) ── --}}
        @if ($view === 'connections')

            {{-- Add new connection --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Add connection</h3>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Name <span class="text-gray-400">(slug, immutable)</span></label>
                        <input type="text" wire:model="newConnName" placeholder="e.g. work"
                            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-36">
                        @error('newConnName')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Label</label>
                        <input type="text" wire:model="newConnLabel" placeholder="e.g. Work account"
                            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-40">
                        @error('newConnLabel')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">GitHub token</label>
                        <input type="password" wire:model="newConnToken" placeholder="ghp_…"
                            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-56">
                        @error('newConnToken')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <button wire:click="createConnection"
                        class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 text-sm font-medium transition-colors">
                        Add connection
                    </button>
                </div>
            </div>

            {{-- Connection list --}}
            @if ($connections->isEmpty())
                <p class="text-sm text-gray-400 dark:text-gray-500">No connections yet. Add one above to get started.</p>
            @else
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($connections as $conn)
                        @php $hasTokens = $shareTokens->where('connection', $conn->name)->isNotEmpty(); @endphp
                        <div class="px-4 py-3">
                            @if ($editConnId === $conn->id)
                                {{-- Inline edit form --}}
                                <div class="flex flex-wrap items-end gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Label</label>
                                        <input type="text" wire:model="editConnLabel"
                                            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-40">
                                        @error('editConnLabel')
                                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">New token <span class="text-gray-400">(leave blank to keep current)</span></label>
                                        <input type="password" wire:model="editConnToken" placeholder="ghp_…"
                                            class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-56">
                                        @error('editConnToken')
                                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button wire:click="saveConnection"
                                        class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 text-sm font-medium transition-colors">
                                        Save
                                    </button>
                                    <button wire:click="cancelEditConnection"
                                        class="rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            @else
                                {{-- Read-only row --}}
                                <div class="flex items-center gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $conn->label }}</span>
                                            <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded px-1.5 py-0.5 font-mono">{{ $conn->name }}</span>
                                            @if ($hasTokens)
                                                <span class="text-xs bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 rounded px-1.5 py-0.5">
                                                    {{ $shareTokens->where('connection', $conn->name)->count() }} share {{ Str::plural('link', $shareTokens->where('connection', $conn->name)->count()) }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Added {{ $conn->created_at->diffForHumans() }}</p>
                                    </div>
                                    <button wire:click="editConnection({{ $conn->id }})"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline transition-colors">
                                        Edit
                                    </button>
                                    <button wire:click="deleteConnection({{ $conn->id }})"
                                        wire:confirm="{{ $hasTokens ? 'This connection has active share links that will break. Delete anyway?' : 'Delete this connection?' }}"
                                        class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400 transition-colors">
                                        Delete
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

        @endif

    </main>

    {{-- ── Commit detail modal ── --}}
    @if ($selectedCommit !== null)
        @php
            $cDetail = $selectedCommit;
            $cSha = $cDetail['sha'];
            $cShort = substr($cSha, 0, 7);
            $cMessage = trim($cDetail['commit']['message']);
            $cSubject = explode("\n", $cMessage)[0];
            $cBody = trim(implode("\n", array_slice(explode("\n", $cMessage), 1)));
            $cAuthor = $cDetail['commit']['author']['name'];
            $cDate = \Carbon\Carbon::parse($cDetail['commit']['author']['date']);
            $cRepoName = $cDetail['repository']['full_name'] ?? null;
            $cRepoUrl = $cDetail['repository']['html_url'] ?? null;
            $cUrl = $cDetail['html_url'];
            $cStats = $cDetail['stats'] ?? null;
            $cFiles = $cDetail['files'] ?? [];
        @endphp

        {{-- Backdrop --}}
        <div wire:click="closeCommit"
            class="fixed inset-0 bg-black/40 dark:bg-black/60 z-40 backdrop-blur-sm"></div>

        {{-- Panel --}}
        <div class="fixed inset-y-0 right-0 w-full max-w-xl bg-white dark:bg-gray-900 shadow-2xl z-50 flex flex-col overflow-hidden">

            {{-- Modal header --}}
            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-800 shrink-0">
                <div class="flex-1 min-w-0">
                    <div class="font-mono text-xs text-gray-400 dark:text-gray-500 mb-0.5">{{ $cShort }}</div>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $cSubject }}</h2>
                </div>
                <button wire:click="closeCommit"
                    class="shrink-0 p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 dark:text-gray-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Scrollable body --}}
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">

                {{-- Meta --}}
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    @if ($cRepoName)
                        <dt class="text-gray-400 dark:text-gray-500 whitespace-nowrap">Repository</dt>
                        <dd>
                            <a href="{{ $cRepoUrl }}" target="_blank"
                                class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $cRepoName }}</a>
                        </dd>
                    @endif
                    <dt class="text-gray-400 dark:text-gray-500 whitespace-nowrap">Author</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $cAuthor }}</dd>
                    <dt class="text-gray-400 dark:text-gray-500 whitespace-nowrap">Date</dt>
                    <dd class="text-gray-700 dark:text-gray-300 tabular-nums">
                        {{ $cDate->format('D, d M Y H:i:s') }}
                    </dd>
                    <dt class="text-gray-400 dark:text-gray-500 whitespace-nowrap">SHA</dt>
                    <dd class="font-mono text-gray-700 dark:text-gray-300 break-all text-xs">{{ $cSha }}</dd>
                    @if ($cStats)
                        <dt class="text-gray-400 dark:text-gray-500 whitespace-nowrap">Changes</dt>
                        <dd class="text-gray-700 dark:text-gray-300">
                            <span class="text-green-600 dark:text-green-400">+{{ $cStats['additions'] }}</span>
                            <span class="mx-1 text-gray-300 dark:text-gray-600">/</span>
                            <span class="text-red-500 dark:text-red-400">-{{ $cStats['deletions'] }}</span>
                            <span class="ml-1 text-gray-400 dark:text-gray-500">({{ $cStats['total'] }} total)</span>
                        </dd>
                    @endif
                </dl>

                {{-- Commit body / description --}}
                @if ($cBody !== '')
                    <div>
                        <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1.5">Description</h3>
                        <pre class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans leading-relaxed bg-gray-50 dark:bg-gray-800/50 rounded-lg px-4 py-3">{{ $cBody }}</pre>
                    </div>
                @endif

                {{-- Files changed --}}
                @if (!empty($cFiles))
                    <div>
                        <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1.5">
                            Files changed ({{ count($cFiles) }})
                        </h3>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden">
                            @foreach ($cFiles as $file)
                                <div class="px-3 py-2 flex items-center gap-2 text-xs bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    @php
                                        $statusColor = match ($file['status']) {
                                            'added' => 'text-green-600 dark:text-green-400',
                                            'removed' => 'text-red-500 dark:text-red-400',
                                            'renamed' => 'text-amber-600 dark:text-amber-400',
                                            default => 'text-blue-600 dark:text-blue-400',
                                        };
                                        $statusLabel = match ($file['status']) {
                                            'added' => 'A',
                                            'removed' => 'D',
                                            'renamed' => 'R',
                                            default => 'M',
                                        };
                                    @endphp
                                    <span class="shrink-0 font-mono font-bold w-4 {{ $statusColor }}">{{ $statusLabel }}</span>
                                    <span class="flex-1 min-w-0 font-mono text-gray-700 dark:text-gray-300 truncate">{{ $file['filename'] }}</span>
                                    <span class="shrink-0 text-green-600 dark:text-green-400">+{{ $file['additions'] }}</span>
                                    <span class="shrink-0 text-red-500 dark:text-red-400">-{{ $file['deletions'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

            {{-- Footer --}}
            <div class="shrink-0 px-5 py-3 border-t border-gray-200 dark:border-gray-800 flex justify-end">
                <a href="{{ $cUrl }}" target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 text-sm font-medium transition-colors">
                    View on GitHub
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" />
                        <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z" />
                    </svg>
                </a>
            </div>
        </div>
    @endif

</div>
