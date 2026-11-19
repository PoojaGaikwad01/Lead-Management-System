<?php

namespace App\Modules\Integration;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only active API clients holding the leads:read ability may use /api/v1. */
class EnsurePortalClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->user();
        abort_unless($client instanceof ApiClient && $client->active && $client->tokenCan(ApiClient::ABILITY), 403, 'This token cannot access the portal API.');

        return $next($request);
    }
}
