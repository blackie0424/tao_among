<?php

namespace App\Http\Middleware;

use App\Services\AudioVisibilityService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilterRestrictedAudio
{
    public function __construct(private readonly AudioVisibilityService $audioVisibility)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse && ! $request->user()?->canAccessAudio()) {
            $response->setData(
                $this->audioVisibility->filter($response->getData(true), $request->user())
            );
        }

        return $response;
    }
}
