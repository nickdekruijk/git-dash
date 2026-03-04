<?php

namespace App\Http\Controllers;

use App\Models\ShareToken;
use Illuminate\View\View;

class ShareController extends Controller
{
    public function show(string $token): View
    {
        $shareToken = ShareToken::where('token', $token)->firstOrFail();

        return view('share', ['shareToken' => $shareToken]);
    }
}
