<?php

use Filament\Facades\Filament;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Filament\Livewire\AccountSwitcherMenu;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Packstub\AccountSwitcher\Tests\Fixtures\User;

use function Pest\Livewire\livewire;

function plugin(): AccountSwitcherPlugin
{
    return Filament::getPanel('admin')->getPlugin(AccountSwitcherPlugin::ID);
}

it('renders buttons only in allowed environments', function (): void {
    $dev = createUser(['email' => 'dev-alice@example.com']);
    createUser(['email' => 'customer@example.com']);

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('Developer logins');

    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Developer logins')
        ->assertSee($dev->name)
        ->assertDontSee('customer@example.com');
});

it('signs in a listed user', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);

    $dev = createUser(['email' => 'dev-alice@example.com']);

    $this->post(route('filament.admin.account-switcher.developer-login'), ['user' => $dev->id])
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    expect(Filament::auth()->user()->is($dev))->toBeTrue()
        ->and(AccountSwitch::query()->sole()->reason)->toBe(SwitchReason::DeveloperLogin);
});

it('rejects users outside the list', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);

    $customer = createUser(['email' => 'customer@example.com']);

    $this->post(route('filament.admin.account-switcher.developer-login'), ['user' => $customer->id])
        ->assertNotFound();

    expect(Filament::auth()->user())->toBeNull();
});

it('is unreachable outside allowed environments', function (): void {
    $dev = createUser(['email' => 'dev-alice@example.com']);

    expect(AccountSwitcher::developerLoginsEnabled())->toBeFalse();

    $this->post(route('filament.admin.account-switcher.developer-login'), ['user' => $dev->id])
        ->assertNotFound();

    expect(Filament::auth()->user())->toBeNull();
});

it('lists the developer logins in the switcher menu and switches from there', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    plugin()->developerLoginsInMenu();

    $admin = createAdmin();
    $dev = createUser(['email' => 'dev-alice@example.com']);

    $this->actingAs($admin);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Switch to')
        ->assertSee('Developer logins')
        ->assertSee($dev->name);

    livewire(AccountSwitcherMenu::class)
        ->call('developerLogin', $dev->id)
        ->assertRedirect();

    expect(Filament::auth()->user()->is($dev))->toBeTrue()
        ->and(AccountSwitch::query()->sole()->reason)->toBe(SwitchReason::DeveloperLogin);
});

it('keeps the developer logins out of the menu unless asked', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);

    $admin = createAdmin();
    createUser(['email' => 'dev-alice@example.com']);

    $this->actingAs($admin);

    $this->get('/admin')
        ->assertOk()
        ->assertDontSee('Developer logins');
});

it('gates the buttons and the route with developerLoginsWhen', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    $dev = createUser(['email' => 'dev-alice@example.com']);

    plugin()->developerLoginsWhen(fn (): bool => false);

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('Developer logins');

    $this->post(route('filament.admin.account-switcher.developer-login'), ['user' => $dev->id])
        ->assertNotFound();

    expect(Filament::auth()->user())->toBeNull();

    plugin()->developerLoginsWhen(fn (): bool => true);

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Developer logins');
});

it('labels the developer logins with developerLoginLabelUsing', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    plugin()->developerLoginLabelUsing(fn (User $user): string => 'Role: '.$user->email);

    createUser(['email' => 'dev-alice@example.com']);

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Role: dev-alice@example.com');
});

it('lands a developer login on developerLoginRedirectTo', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    plugin()->developerLoginRedirectTo(fn (User $user): string => '/landing/'.$user->id);

    $dev = createUser(['email' => 'dev-alice@example.com']);

    $this->post(route('filament.admin.account-switcher.developer-login'), ['user' => $dev->id])
        ->assertRedirect('/landing/'.$dev->id);
});

it('passes the switch reason to redirectTo', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    plugin()->redirectTo(fn (User $user, ?SwitchReason $reason): string => '/after/'.$reason?->value);

    $dev = createUser(['email' => 'dev-alice@example.com']);

    $this->post(route('filament.admin.account-switcher.developer-login'), ['user' => $dev->id])
        ->assertRedirect('/after/developer_login');
});
