<?php

use Filament\Facades\Filament;
use Packstub\AccountSwitcher\AccountSwitcherServiceProvider;
use Packstub\AccountSwitcher\AppContext;
use Packstub\AccountSwitcher\Contracts\SwitchContext;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Filament\PanelContext;

it('binds the panel context', function (): void {
    expect(AccountSwitcher::context())->toBeInstanceOf(PanelContext::class)
        ->and(AccountSwitcher::context()->scope())->toBe('admin')
        ->and(AccountSwitcher::context()->guardName())->toBe(Filament::getPanel('admin')->getAuthGuard());
});

it('keeps the panel context when the core provider registers later', function (): void {
    // The order package discovery produces is not guaranteed; the core only binds its own context when nothing is bound.
    (new AccountSwitcherServiceProvider($this->app))->register();

    expect(app(SwitchContext::class))->toBeInstanceOf(PanelContext::class);
});

it('exposes the plugin and the accessible panel on the facade', function (): void {
    $user = createUser();
    $locked = createUser(['can_access_panel' => false]);

    expect(AccountSwitcher::plugin())->toBe(Filament::getPanel('admin')->getPlugin('packstub-account-switcher'))
        ->and(AccountSwitcher::accessiblePanel($user)?->getId())->toBe('admin')
        ->and(AccountSwitcher::accessiblePanel($locked))->toBeNull();
});

it('answers from the plugin first and the app context otherwise', function (): void {
    config()->set('packstub-account-switcher.features.impersonation', false);

    // The admin panel's plugin keeps impersonation on, whatever the config says.
    expect(AccountSwitcher::context()->hasImpersonation())->toBeTrue();

    AccountSwitcher::plugin()->impersonation(false);

    expect(AccountSwitcher::context()->hasImpersonation())->toBeFalse();

    AccountSwitcher::plugin()->impersonation(true);

    // The plugin has no redirect of its own, so the app context's one is not used either: the panel wins.
    app(AppContext::class)->redirectTo('/elsewhere');

    expect(AccountSwitcher::redirectUrlFor(createUser()))->toBe(Filament::getPanel('admin')->getUrl());
});

it('names users the way the panel does', function (): void {
    $user = createUser(['name' => 'Grace Hopper']);

    expect(AccountSwitcher::context()->userLabel($user))->toBe(Filament::getUserName($user));
});

it('posts the banner and the developer logins to the panel routes', function (): void {
    expect(AccountSwitcher::context()->switchBackUrl())->toBe(route('filament.admin.account-switcher.switch-back'))
        ->and(AccountSwitcher::context()->developerLoginUrl())->toBe(route('filament.admin.account-switcher.developer-login'));
});
