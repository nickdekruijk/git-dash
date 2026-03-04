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

        $searchApi = $client->api('search');

        $pager = new ResultPager($client);
        $items = $pager->fetchAll($searchApi, 'commits', [
            "author:{$username} author-date:{$from}..{$to}",
            'author-date',
            'desc',
        ]);

        $commitsByDate = collect($items)
            ->groupBy(fn (array $item) => substr($item['commit']['author']['date'], 0, 10))
            ->sortKeysDesc();

        $connections = collect(config('github.connections'))
            ->filter(fn (array $conn) => ($conn['method'] ?? '') === 'token' && ! empty($conn['token']))
            ->keys();

        return view('dashboard', compact('commitsByDate', 'from', 'to', 'username', 'connection', 'connections'));
    }
}
