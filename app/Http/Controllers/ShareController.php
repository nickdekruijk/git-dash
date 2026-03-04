<?php

namespace App\Http\Controllers;

use App\Models\GithubConnection;
use App\Models\ShareToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShareController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $shareToken = ShareToken::where('token', $token)->firstOrFail();

        // If the referenced connection was deleted, show a friendly error
        if (! GithubConnection::where('name', $shareToken->connection)->exists()) {
            abort(404, 'The connection for this share link no longer exists.');
        }

        return view('share', ['shareToken' => $shareToken]);
    }
}
