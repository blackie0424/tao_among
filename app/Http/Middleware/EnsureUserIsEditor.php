<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsEditor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isEditor()) {
            abort(403);
        }

        return $next($request);
    }
}

