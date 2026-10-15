<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Filament\Pages\LinkedAccounts;
use Packstub\AccountSwitcher\Models\LinkedAccount;
use Packstub\AccountSwitcher\Tests\Fixtures\User;

it('is reachable from the user menu', function (): void {
    $this->actingAs(createAdmin());

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Linked accounts');

    $this->get(LinkedAccounts::getUrl())
        ->assertOk()
        ->assertSee('No linked accounts yet');
});

it('is forbidden while impersonating', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);
    session()->forget(Packstub\AccountSwitcher\AccountSwitcher::FALLBACK_URL_SESSION_KEY); // the landing-page fallback is covered in FallbackAfterSwitchTest

    $this->get(LinkedAccounts::getUrl())->assertForbidden();
});

it('verifies credentials when linking an existing account', function (): void {
    $admin = createAdmin();
    $daily = createUser();

    $this->actingAs($admin);

    Livewire::test(LinkedAccounts::class)
        ->callAction(TestAction::make('link')->table(), [
            'email' => $daily->email,
            'password' => 'wrong',
            'label' => 'Daily',
            'requires_password' => false,
        ])
        ->assertNotified('No account matches those credentials.');

    expect($admin->isLinkedTo($daily))->toBeFalse();

    Livewire::test(LinkedAccounts::class)
        ->callAction(TestAction::make('link')->table(), [
            'email' => $daily->email,
            'password' => 'secret',
            'label' => 'Daily',
            'requires_password' => false,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Account linked.');

    expect($admin->fresh()->isLinkedTo($daily))->toBeTrue()
        ->and($daily->fresh()->isLinkedTo($admin))->toBeTrue()
        ->and($admin->linkedAccounts()->first()->pivot->label)->toBe('Daily');
});

it('cannot link its own account', function (): void {
    $admin = createAdmin();

    $this->actingAs($admin);

    Livewire::test(LinkedAccounts::class)
        ->callAction(TestAction::make('link')->table(), [
            'email' => $admin->email,
            'password' => 'secret',
            'requires_password' => true,
        ])
        ->assertNotified('No account matches those credentials.');

    expect(LinkedAccount::query()->count())->toBe(0);
});

it('creates and links a user as a sub-account', function (): void {
    $admin = createAdmin();

    $this->actingAs($admin);

    Livewire::test(LinkedAccounts::class)
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'Admin (daily)',
            'email' => 'admin+daily@example.com',
            'password' => 'another-secret',
            'label' => 'Daily',
            'requires_password' => false,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Sub-account created and linked.');

    $sub = User::query()->where('email', 'admin+daily@example.com')->sole();

    expect(Hash::check('another-secret', $sub->password))->toBeTrue()
        ->and($admin->isLinkedTo($sub))->toBeTrue()
        ->and(AccountSwitcher::requiresPassword($admin, $sub))->toBeFalse()
        ->and(AccountSwitcher::requiresPassword($sub, $admin))->toBeTrue();
});

it('validates a unique email when creating a sub-account', function (): void {
    $admin = createAdmin();

    $this->actingAs($admin);

    Livewire::test(LinkedAccounts::class)
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'Dup',
            'email' => $admin->email,
            'password' => 'another-secret',
            'requires_password' => false,
        ])
        ->assertHasFormErrors(['email']);
});

it('renames and unlinks accounts', function (): void {
    $admin = createAdmin();
    $daily = createUser();
    $admin->linkAccount($daily, label: 'Old');

    $this->actingAs($admin);

    $link = LinkedAccount::query()->whereBelongsTo($admin, 'user')->sole();

    Livewire::test(LinkedAccounts::class)
        ->assertCanSeeTableRecords([$link])
        ->callAction(TestAction::make('rename')->table($link), ['label' => 'New'])
        ->assertHasNoFormErrors();

    expect($link->fresh()->label)->toBe('New');

    Livewire::test(LinkedAccounts::class)
        ->callAction(TestAction::make('unlink')->table($link))
        ->assertNotified('Account unlinked.');

    expect($admin->isLinkedTo($daily))->toBeFalse()
        ->and($daily->isLinkedTo($admin))->toBeFalse();
});
