<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

beforeEach(function (): void {
    // The lite panel runs withoutDatabase(): neither table is migrated.
    Schema::drop('linked_accounts');
    Schema::drop('account_switches');

    Filament::setCurrentPanel(Filament::getPanel('lite'));
});

it('turns off linked accounts and the switch log', function (): void {
    $plugin = AccountSwitcherPlugin::make()->withoutDatabase();

    expect($plugin->hasLinkedAccounts())->toBeFalse()
        ->and($plugin->shouldLogSwitches())->toBeFalse()
        ->and($plugin->hasImpersonation())->toBeTrue()
        ->and(Filament::getPanel('lite')->getPlugin(AccountSwitcherPlugin::ID)->hasLinkedAccounts())->toBeFalse();
});

it('lets a later call turn a feature back on', function (): void {
    $plugin = AccountSwitcherPlugin::make()->withoutDatabase()->logSwitches();

    expect($plugin->shouldLogSwitches())->toBeTrue()
        ->and($plugin->hasLinkedAccounts())->toBeFalse();
});

it('impersonates, renders the banner and switches back without the tables', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    expect(Filament::auth()->user()->is($user))->toBeTrue();

    $this->get('/lite')
        ->assertOk()
        ->assertSee('fi-account-switcher-banner')
        ->assertDontSee('linked-accounts');

    $this->post(route('filament.lite.account-switcher.switch-back'))
        ->assertRedirect();

    expect(Filament::auth()->user()->is($admin))->toBeTrue()
        ->and(AccountSwitcher::isImpersonating())->toBeFalse();
});

it('signs in with a developer login without the tables', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);

    $dev = createUser(['email' => 'dev-alice@example.com']);

    $this->get('/lite/login')
        ->assertOk()
        ->assertSee('Developer logins');

    $this->post(route('filament.lite.account-switcher.developer-login'), ['user' => $dev->id])
        ->assertRedirect(Filament::getPanel('lite')->getUrl());

    expect(Filament::auth()->user()->is($dev))->toBeTrue();
});

it('does not register the linked accounts page', function (): void {
    $this->actingAs(createUser());

    expect(app('router')->has('filament.lite.pages.linked-accounts'))->toBeFalse();

    $this->get('/lite')->assertOk();
});
