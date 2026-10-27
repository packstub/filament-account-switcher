<?php

use Filament\Facades\Filament;
use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;
use Packstub\AccountSwitcher\Facades\AccountSwitcher as AccountSwitcherFacade;
use Packstub\AccountSwitcher\Filament\Pages\LinkedAccounts;

/**
 * The Linked accounts page is forbidden while impersonating, so it stands in for any
 * page the account just switched to may not see.
 */
it('sends a hidden landing page to a panel the new account may access, once', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcherFacade::impersonate($user);

    expect(session()->get(AccountSwitcher::FALLBACK_URL_SESSION_KEY))->toBe(Filament::getPanel('admin')->getUrl());
    session()->save();

    $this->get(LinkedAccounts::getUrl())->assertRedirect(Filament::getPanel('admin')->getUrl());
    $this->get(LinkedAccounts::getUrl())->assertForbidden();
});

it('does not touch the landing page when it renders', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcherFacade::impersonate($user);
    session()->save(); // ends the request the switch was made in, like the redirect does

    $this->get('/admin')->assertOk();
    $this->get(LinkedAccounts::getUrl())->assertForbidden();
});

it('leaves a hidden page alone without a switch', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    session()->put(AccountSwitcher::SESSION_KEY.'.impersonator_id', $admin->id);

    $this->get(LinkedAccounts::getUrl())->assertForbidden();
});

it('can be turned off per panel', function (): void {
    Filament::getPanel('admin')->getPlugin(AccountSwitcherPlugin::ID)->fallbackToAccessiblePanel(false);

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcherFacade::impersonate($user);

    expect(session()->has(AccountSwitcher::FALLBACK_URL_SESSION_KEY))->toBeFalse();

    $this->get(LinkedAccounts::getUrl())->assertForbidden();
});
