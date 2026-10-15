<?php

namespace Packstub\AccountSwitcher\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Packstub\AccountSwitcher\AccountSwitcher;

class DeveloperLoginController
{
    public function __invoke(Request $request, AccountSwitcher $switcher): RedirectResponse
    {
        abort_unless($switcher->developerLoginsEnabled(), 404);

        $validated = $request->validate([
            'user' => ['required'],
        ]);

        $user = $switcher->developerLoginUsers()->first(
            fn (Authenticatable $candidate): bool => (string) $candidate->getAuthIdentifier() === (string) $validated['user'],
        );

        abort_unless($user !== null, 404);

        $switcher->developerLogin($user);

        return redirect()->to($switcher->developerLoginRedirectUrl($user));
    }
}
