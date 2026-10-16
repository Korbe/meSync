<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The demo account is shared by everyone who tries meSync: its profile is read only.
 * Blocks every change below /user (name, email, password, two factor, sessions, logo,
 * company data, deleting the account). Business data stays editable.
 */
class ProtectDemoProfile
{
    public const MESSAGE = 'Im Demo-Modus kann das Profil nicht geändert werden.';

    public function handle(Request $request, Closure $next): Response
    {
        $changesProfile = ! $request->isMethodSafe()
            && ($request->is('user') || $request->is('user/*'))
            && ! $request->is('user/confirm-password');

        if ($changesProfile && $request->user()?->isDemo()) {
            return $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['message' => self::MESSAGE], 403)
                : back(303)->with('error', self::MESSAGE);
        }

        return $next($request);
    }
}
