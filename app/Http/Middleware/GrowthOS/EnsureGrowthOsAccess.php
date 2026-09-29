<?php

namespace App\Http\Middleware\GrowthOS;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGrowthOsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('growth_os.enabled') !== true) {
            abort(404);
        }

        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Nie masz dostępu do PNE Growth OS.');
        }

        return $next($request);
    }
}
