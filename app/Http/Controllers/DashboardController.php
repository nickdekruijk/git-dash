<?php

namespace App\Http\Controllers;

use Github\ResultPager;
use GrahamCampbell\GitHub\Facades\GitHub;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $connection = $request->input('connection', 'main');
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to = $request->input('to', now()->toDateString());

        $client = GitHub::connection($connection);
        $me = $client->currentUser()->show();
        $username = $me['login'];

        $pager = new ResultPager($client);

        // Fetch all repos: personal + org, public + private
        $repos = $pager->fetchAll(
            $client->currentUser(),
            'repositories',
            ['owner', 'full_name', 'asc', 'all', 'owner,collaborator,organization_member']
        );

        // Fetch commits per repo filtered by author and date range
        $allCommits = [];
        foreach ($repos as $repo) {
            try {
                $commits = $pager->fetchAll(
                    $client->repo()->commits(),
                    'all',
                    [
                        $repo['owner']['login'],
                        $repo['name'],
                        [
                            'author' => $username,
                            'since' => $from.'T00:00:00Z',
                            'until' => $to.'T23:59:59Z',
                        ],
                    ]
                );

                foreach ($commits as $commit) {
                    $commit['repository'] = [
                        'full_name' => $repo['full_name'],
                        'html_url' => $repo['html_url'],
                    ];
                    $allCommits[] = $commit;
                }
            } catch (\Exception) {
                // Skip repos that are inaccessible or return errors
            }
        }

        $commitsByDate = collect($allCommits)
            ->sortByDesc(fn (array $commit) => $commit['commit']['author']['date'])
            ->groupBy(fn (array $commit) => substr($commit['commit']['author']['date'], 0, 10))
            ->sortKeysDesc();

        $connections = collect(config('github.connections'))
            ->filter(fn (array $conn) => ($conn['method'] ?? '') === 'token' && ! empty($conn['token']))
            ->keys();

        return view('dashboard', compact('commitsByDate', 'from', 'to', 'username', 'connection', 'connections'));
    }
}
