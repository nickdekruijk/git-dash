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

        $connections = collect(config('github.connections'))
            ->filter(fn (array $conn) => ($conn['method'] ?? '') === 'token' && ! empty($conn['token']))
            ->keys();

        return view('dashboard', compact(
            'commitsByDate', 'from', 'to', 'username', 'connection', 'connections',
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

        // Split into sessions based on gap between consecutive commits
        $sessions = [];
        $current = [];
        $prevTime = null;

        foreach ($sorted as $commit) {
            $time = Carbon::parse($commit['commit']['author']['date']);

            if ($prevTime !== null && $time->diffInMinutes($prevTime) > self::SESSION_GAP_MINUTES) {
                $sessions[] = $current;
                $current = [];
            }

            $current[] = $commit;
            $prevTime = $time;
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
            $minutes = $last->diffInMinutes($first) + self::SESSION_PADDING_MINUTES;
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
