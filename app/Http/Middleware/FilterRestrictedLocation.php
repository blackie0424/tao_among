<?php

namespace App\Http\Middleware;

use App\Services\LocationVisibilityService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilterRestrictedLocation
{
    public function __construct(private readonly LocationVisibilityService $locationVisibility)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse && ! $request->user()?->canAccessLocation()) {
            $response->setData(
                $this->locationVisibility->filter($response->getData(true), $request->user())
            );
        }

        return $response;
    }
}