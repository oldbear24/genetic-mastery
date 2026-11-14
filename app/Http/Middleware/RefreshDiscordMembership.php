<?php

namespace App\Http\Middleware;

use App\Services\Discord\MembershipSync;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks membership and roles when the last check is older than the
 * refresh interval, so a removed admin role stops working within
 * 10 minutes (AUTH-4). A user who left the server is signed out.
 */
class RefreshDiscordMembership
{
    public function __construct(private readonly MembershipSync $membership) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $this->membership->isStale($user)) {
            $this->membership->refresh($user);
        }

        if ($user !== null && ! $user->is_member) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('auth.discord.not_member'));
        }

        return $next($request);
    }
}
