<?php

namespace App\Modules\Auth;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user() && ! $request->user() instanceof User, 403, 'API tokens can only use /api/v1.');

        if ($request->user() && ! $request->user()->active) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }
            abort(401, 'Your account is inactive.');
        }

        return $next($request);
    }
}
