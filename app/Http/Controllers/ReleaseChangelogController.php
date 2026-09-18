<?php

namespace App\Http\Controllers;

use App\Services\ReleaseChangelogService;
use Illuminate\View\View;

class ReleaseChangelogController extends Controller
{
    public function show(string $app, ReleaseChangelogService $changelog): View
    {
        abort_unless(array_key_exists($app, config('release.apps', [])), 404);

        $data = $changelog->forApp($app);
        $current = $data['releases'][0] ?? null;
        $history = array_slice($data['releases'], 1);

        if (auth()->check() && $data['readable']) {
            $changelog->markSeen(auth()->user(), $app);
        }

        return view('changelog.show', [
            'changelog' => $data,
            'current' => $current,
            'history' => $history,
        ]);
    }
}
