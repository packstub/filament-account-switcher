<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Filament\Livewire\AccountSwitcherMenu;

it('lists linked accounts in the topbar', function (): void {
    $admin = createAdmin();
    $daily = createUser(['name' => 'Daily Driver']);
    $admin->linkAccount($daily, label: 'Daily');

    $this->actingAs($admin);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Switch to')
        ->assertSee('Daily')
        ->assertSee($daily->email)
        ->assertSee('Manage linked accounts');
});

it('shows just the icon on a phone, labelled for screen readers, and the label from sm up', function (): void {
    $admin = createAdmin();
    $admin->linkAccount(createUser(), label: 'Daily');

    $this->actingAs($admin);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('fi-labeled-from-sm', escape: false)
        ->assertSee('aria-label="Switch to"', escape: false);
});

it('is hidden without linked accounts', function (): void {
    $this->actingAs(createAdmin());

    $this->get('/admin')
        ->assertOk()
        ->assertDontSee('Switch to');
});

it('is hidden while impersonating', function (): void {
    $admin = createAdmin();
    $user = createUser();
    $userAdmin = createAdmin();
    $user->linkAccount($userAdmin, requiresPassword: false);

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    $this->get('/admin')
        ->assertOk()
        ->assertDontSee('Switch to');
});

it('switches without a password requirement', function (): void {
    $admin = createAdmin();
    $daily = createUser();
    $admin->linkAccount($daily, requiresPassword: false);

    $this->actingAs($admin);

    $component = Livewire::test(AccountSwitcherMenu::class)
        ->mountAction('switch', arguments: ['account' => $daily->id])
        ->assertActionMounted('switch');

    expect((string) $component->instance()->getMountedAction()->getModalDescription())
        ->toContain('You will be signed in as')
        ->not->toContain('password');

    $component
        ->callMountedAction()
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    expect(Filament::auth()->user()->is($daily))->toBeTrue();
});

it('asks for the target password when switching up', function (): void {
    $admin = createAdmin();
    $daily = createUser();
    $daily->linkAccount($admin, requiresPassword: true);

    $this->actingAs($daily);

    Livewire::test(AccountSwitcherMenu::class)
        ->mountAction('switch', arguments: ['account' => $admin->id])
        ->assertActionMounted('switch')
        ->callMountedAction()
        ->assertHasFormErrors(['password' => 'required']);

    expect(Filament::auth()->user()->is($daily))->toBeTrue();

    Livewire::test(AccountSwitcherMenu::class)
        ->callAction('switch', ['password' => 'wrong'], ['account' => $admin->id])
        ->assertNotified()
        ->assertNoRedirect();

    expect(Filament::auth()->user()->is($daily))->toBeTrue();

    Livewire::test(AccountSwitcherMenu::class)
        ->callAction('switch', ['password' => 'secret'], ['account' => $admin->id])
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    expect(Filament::auth()->user()->is($admin))->toBeTrue();
});

it('cannot target unlinked accounts', function (): void {
    $user = createUser();
    $other = createUser();

    $this->actingAs($user);

    Livewire::test(AccountSwitcherMenu::class)
        ->callAction('switch', arguments: ['account' => $other->id])
        ->assertNoRedirect();

    expect(Filament::auth()->user()->is($user))->toBeTrue();
});
