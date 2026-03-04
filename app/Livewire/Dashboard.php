<?php

namespace App\Livewire;

use Carbon\Carbon;
use Github\ResultPager;
use GrahamCampbell\GitHub\Facades\GitHub;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Models\ShareToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    /** Minutes gap between commits that starts a new work session. */
    private const SESSION_GAP_MINUTES = 120;

    /** Extra minutes added per session for work done before the first commit. */
    private const SESSION_PADDING_MINUTES = 30;

    public string $connection = 'main';
    public string $from = '';
    public string $to = '';
    public string $view = 'timeline';
    public string $preset = '';

    /** When set, the user can only view this connection (share link mode). */
    public ?string $lockedConnection = null;

    /** New token form fields */
    public string $newTokenLabel = '';
    public string $newTokenConnection = 'main';

    public function mount(?string $lockedConnection = null): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();

        if ($lockedConnection !== null) {
            $this->lockedConnection = $lockedConnection;
            $this->connection = $lockedConnection;
        }
    }

    /** When a preset is chosen, calculate and apply the corresponding date range. */
    public function updatedPreset(string $value): void
    {
        if (! $value) {
            return;
        }

        [$this->from, $this->to] = $this->calculatePresetDates($value);
    }

    /** Clear the preset label when the user manually edits the start date. */
    public function updatedFrom(): void
    {
        $this->preset = '';
    }

    /** Clear the preset label when the user manually edits the end date. */
    public function updatedTo(): void
    {
        $this->preset = '';
    }

    /** Prevent changing connection when in share/locked mode. */
    public function updatedConnection(): void
    {
        if ($this->lockedConnection !== null) {
            $this->connection = $this->lockedConnection;
        }
    }

    /** Generate a new share token. */
    public function createToken(): void
    {
        ShareToken::create([
            'token'      => Str::random(32),
            'connection' => $this->newTokenConnection,
            'label'      => $this->newTokenLabel ?: null,
        ]);

        $this->newTokenLabel = '';
        unset($this->shareTokens); // bust computed cache
    }

    /** Delete a share token by id. */
    public function deleteToken(int $id): void
    {
        ShareToken::destroy($id);
        unset($this->shareTokens);
    }

    #[Computed]
    public function shareTokens(): Collection
    {
        return ShareToken::orderByDesc('created_at')->get();
    }

    #[Computed]
    public function connections(): Collection
    {
        return collect(config('github.connections'))
            ->filter(fn (array $conn) => ($conn['method'] ?? '') === 'token' && ! empty($conn['token']))
            ->keys();
    }

    #[Computed]
    public function username(): string
    {
        return GitHub::connection($this->connection)->currentUser()->show()['login'];
    }

    #[Computed]
    public function items(): Collection
    {
        $client = GitHub::connection($this->connection);
        $pager = new ResultPager($client);

        return collect($pager->fetchAll($client->api('search'), 'commits', [
            "author:{$this->username} author-date:{$this->from}..{$this->to}",
            'author-date',
            'desc',
        ]));
    }

    #[Computed]
    public function commitsByDate(): Collection
    {
        return $this->items
            ->groupBy(fn (array $item) => substr($item['commit']['author']['date'], 0, 10))
            ->sortKeysDesc()
            ->map(fn (Collection $dayCommits) => $this->buildDayData($dayCommits));
    }

    #[Computed]
    public function totalCommits(): int
    {
        return $this->items->count();
    }

    #[Computed]
    public function totalRepos(): int
    {
        return $this->items->pluck('repository.full_name')->unique()->count();
    }

    #[Computed]
    public function totalMinutes(): int
    {
        return $this->commitsByDate->sum('total_minutes');
    }

    #[Computed]
    public function timeByRepo(): Collection
    {
        return collect($this->commitsByDate)
            ->flatMap(fn (array $dayData) => $dayData['sessions'])
            ->groupBy(fn (array $session) => $session['commits']->first()['repository']['full_name'])
            ->map(fn (Collection $sessions, string $repoName) => [
                'full_name' => $repoName,
                'html_url' => $sessions->first()['commits']->first()['repository']['html_url'],
                'minutes' => $sessions->sum('minutes'),
                'commit_count' => $sessions->sum(fn (array $s) => $s['commits']->count()),
                'session_count' => $sessions->count(),
                'commits' => $sessions
                    ->flatMap(fn (array $s) => $s['commits'])
                    ->sortByDesc(fn (array $c) => $c['commit']['author']['date'])
                    ->values(),
            ])
            ->sortByDesc('minutes')
            ->values();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard', [
            'commitsByDate'  => $this->commitsByDate,
            'timeByRepo'     => $this->timeByRepo,
            'totalCommits'   => $this->totalCommits,
            'totalRepos'     => $this->totalRepos,
            'totalMinutes'   => $this->totalMinutes,
            'username'       => $this->username,
            'connections'    => $this->connections,
            'shareTokens'    => $this->lockedConnection === null ? $this->shareTokens : collect(),
        ]);
    }

    /**
     * Groups a day's commits into work sessions and estimates time for each.
     *
     * @return array{total_minutes: int, sessions: list<array{minutes: int, commits: Collection}>}
     */
    private function buildDayData(Collection $commits): array
    {
        $sorted = $commits
            ->sortBy(fn (array $c) => $c['commit']['author']['date'])
            ->values();

        // Split into sessions on time gap OR repository change
        $sessions = [];
        $current = [];
        $prevTime = null;
        $prevRepo = null;

        foreach ($sorted as $commit) {
            $time = Carbon::parse($commit['commit']['author']['date']);
            $repo = $commit['repository']['full_name'];

            $timeGapExceeded = $prevTime !== null && $time->diffInMinutes($prevTime) > self::SESSION_GAP_MINUTES;
            $repoChanged = $prevRepo !== null && $repo !== $prevRepo;

            if ($timeGapExceeded || $repoChanged) {
                $sessions[] = $current;
                $current = [];
            }

            $current[] = $commit;
            $prevTime = $time;
            $prevRepo = $repo;
        }

        if (! empty($current)) {
            $sessions[] = $current;
        }

        // Calculate estimated minutes per session and reverse for newest-first display
        $totalMinutes = 0;
        $sessionsData = [];

        foreach (array_reverse($sessions) as $sessionCommits) {
            $first = Carbon::parse($sessionCommits[0]['commit']['author']['date']);
            $last = Carbon::parse(end($sessionCommits)['commit']['author']['date']);
            $minutes = $first->diffInMinutes($last) + self::SESSION_PADDING_MINUTES;
            $totalMinutes += $minutes;

            $sessionsData[] = [
                'minutes' => $minutes,
                'commits' => collect(array_reverse($sessionCommits)),
            ];
        }

        return [
            'total_minutes' => $totalMinutes,
            'sessions'      => $sessionsData,
        ];
    }

    /** Calculate from/to date strings for a named preset. Returns [from, to]. */
    private function calculatePresetDates(string $preset): array
    {
        $today = now()->startOfDay();

        return match ($preset) {
            'this_week'    => [
                $today->clone()->startOfWeek(Carbon::MONDAY)->toDateString(),
                $today->toDateString(),
            ],
            'last_week'    => [
                $today->clone()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString(),
                $today->clone()->subWeek()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ],
            'this_month'   => [
                $today->clone()->startOfMonth()->toDateString(),
                $today->toDateString(),
            ],
            'last_month'   => [
                $today->clone()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $today->clone()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'last_7'       => [$today->clone()->subDays(6)->toDateString(), $today->toDateString()],
            'last_30'      => [$today->clone()->subDays(29)->toDateString(), $today->toDateString()],
            'last_90'      => [$today->clone()->subDays(89)->toDateString(), $today->toDateString()],
            'this_year'    => [
                $today->clone()->startOfYear()->toDateString(),
                $today->toDateString(),
            ],
            'last_year'    => [
                $today->clone()->subYear()->startOfYear()->toDateString(),
                $today->clone()->subYear()->endOfYear()->toDateString(),
            ],
            'this_quarter' => [
                $today->clone()->startOfQuarter()->toDateString(),
                $today->toDateString(),
            ],
            'last_quarter' => [
                $today->clone()->subQuarter()->startOfQuarter()->toDateString(),
                $today->clone()->subQuarter()->endOfQuarter()->toDateString(),
            ],
            default        => [$this->from, $this->to],
        };
    }
}
