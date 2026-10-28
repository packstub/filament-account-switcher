<?php

namespace Packstub\AccountSwitcher\Filament\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;

/**
 * POST account-switcher/session-replays: end the impersonation and open the
 * recordings of the person who was impersonated, in a panel where the
 * impersonator may watch them (Filament Session Replay). The session belongs
 * to the impersonated person until the switch back, so the list cannot be
 * opened as them; the URL is resolved first, for the impersonator, and the
 * switch back lands on it. Without a list to open this is a plain switch back.
 */
class SessionReplaysController
{
    public function __invoke(AccountSwitcher $switcher): RedirectResponse
    {
        if (! $switcher->isImpersonating()) {
            $user = $switcher->context()->guard()->user();

            return redirect()->to($user ? $switcher->redirectUrlFor($user) : $switcher->context()->loginUrl());
        }

        $impersonated = $switcher->context()->guard()->user();
        $impersonator = $switcher->impersonator();

        $url = $impersonated && $impersonator
            ? AccountSwitcherPlugin::get()->getSessionReplaysUrl($impersonated, $impersonator)
            : null;

        $backTo = $switcher->stopImpersonating();

        return redirect()->to($url ?? $backTo);
    }
}
