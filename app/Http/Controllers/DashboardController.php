<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Github\ResultPager;
use GrahamCampbell\GitHub\Facades\GitHub;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Minutes gap between commits that starts a new work session. */
    private const SESSION_GAP_MINUTES = 120;

    /** Extra minutes added per session for work done before the first commit. */
    private const SESSION_PADDING_MINUTES = 30;

    public function index(Request $request): View
    {
        $connection = $request->input('connection', 'main');
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to = $request->input('to', now()->toDateString());
        $view = $request->input('view', 'timeline');

        $client = GitHub::connection($connection);
        $me = $client->currentUser()->show();
        $username = $me['login'];

        $pager = new ResultPager($client);
        $items = collect($pager->fetchAll($client->api('search'), 'commits', [
            "author:{$username} author-date:{$from}..{$to}",
            'author-date',
            'desc',
        ]));

        $commitsByDate = $items
            ->groupBy(fn (array $item) => substr($item['commit']['author']['date'], 0, 10))
            ->sortKeysDesc()
            ->map(fn (Collection $dayCommits) => $this->buildDayData($dayCommits));

        $totalCommits = $items->count();
        $totalRepos = $items->pluck('repository.full_name')->unique()->count();
        $totalMinutes = $commitsByDate->sum('total_minutes');

        $timeByRepo = collect($commitsByDate)
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

        $connections = collect(config('github.connections'))
            ->filter(fn (array $conn) => ($conn['method'] ?? '') === 'token' && ! empty($conn['token']))
            ->keys();

        return view('dashboard', compact(
            'commitsByDate', 'timeByRepo', 'view',
            'from', 'to', 'username', 'connection', 'connections',
            'totalCommits', 'totalRepos', 'totalMinutes',
        ));
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
            'sessions' => $sessionsData,
        ];
    }
}
