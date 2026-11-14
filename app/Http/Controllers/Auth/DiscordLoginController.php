<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Discord\DiscordUnavailable;
use App\Services\Discord\MembershipSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Discord OAuth2 login: authorization code flow with the `identify` scope
 * and a `state` parameter against CSRF (AUTH-1). Membership and roles are
 * then checked with the bot (AUTH-2, AUTH-3). The OAuth access token is not
 * stored anywhere and no refresh token is kept (AUTH-7).
 */
class DiscordLoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('discord')->setScopes(['identify'])->redirect();
    }

    public function callback(Request $request, MembershipSync $membership): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->fail('auth.discord.cancelled');
        }

        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (InvalidStateException) {
            return $this->fail('auth.discord.invalid_state');
        } catch (Throwable $e) {
            Log::warning('Discord OAuth callback failed.', ['exception' => $e::class]);

            return $this->fail('auth.discord.failed');
        }

        $raw = $discordUser->getRaw();

        try {
            $user = $membership->login([
                'id' => (string) $raw['id'],
                'username' => (string) $raw['username'],
                'global_name' => $raw['global_name'] ?? null,
                'avatar' => $raw['avatar'] ?? null,
            ]);
        } catch (DiscordUnavailable $e) {
            Log::warning('Membership check failed during login.', ['status' => $e->status]);

            return $this->fail('auth.discord.unavailable');
        }

        if ($user === null) {
            return $this->fail('auth.discord.not_member');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function fail(string $messageKey): RedirectResponse
    {
        return redirect()->route('login')->with('error', __($messageKey));
    }
}
