<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Packstub\AccountSwitcher\Tests\Fixtures\CentralUser;

it('links symmetrically and idempotently', function (): void {
    $admin = createAdmin();
    $daily = createUser();

    $admin->linkAccount($daily, label: 'Daily', requiresPassword: false);
    $admin->linkAccount($daily, label: 'Daily work');

    expect($admin->isLinkedTo($daily))->toBeTrue()
        ->and($daily->isLinkedTo($admin))->toBeTrue()
        ->and($admin->linkedAccounts()->count())->toBe(1)
        ->and($admin->linkedAccounts()->first()->pivot->label)->toBe('Daily work');

    $admin->unlinkAccount($daily);

    expect($admin->isLinkedTo($daily))->toBeFalse()
        ->and($daily->isLinkedTo($admin))->toBeFalse();
});

it('switches down without a password requirement', function (): void {
    $admin = createAdmin();
    $daily = createUser();
    $admin->linkAccount($daily, requiresPassword: false);

    $this->actingAs($admin);

    expect(AccountSwitcher::requiresPassword($admin, $daily))->toBeFalse();

    AccountSwitcher::switchToLinkedAccount($daily);

    expect(Filament::auth()->user()->is($daily))->toBeTrue()
        ->and(AccountSwitcher::isImpersonating())->toBeFalse()
        ->and(AccountSwitch::query()->sole()->reason)->toBe(SwitchReason::LinkedAccount);
});

it('requires the target password when switching up', function (): void {
    $admin = createAdmin();
    $daily = createUser();
    $daily->linkAccount($admin, requiresPassword: true);

    $this->actingAs($daily);

    expect(AccountSwitcher::requiresPassword($daily, $admin))->toBeTrue();

    expect(fn () => AccountSwitcher::switchToLinkedAccount($admin, 'wrong'))
        ->toThrow(AccountSwitchDenied::class);

    expect(Filament::auth()->user()->is($daily))->toBeTrue();

    AccountSwitcher::switchToLinkedAccount($admin, 'secret');

    expect(Filament::auth()->user()->is($admin))->toBeTrue();
});

it('cannot switch to an unlinked account', function (): void {
    $user = createUser();
    $other = createUser();

    $this->actingAs($user);

    expect(AccountSwitcher::canSwitchToLinkedAccount($user, $other))->toBeFalse();

    AccountSwitcher::switchToLinkedAccount($other, 'secret');
})->throws(AccountSwitchDenied::class);

it('cannot switch while impersonating', function (): void {
    $admin = createAdmin();
    $user = createUser();
    $userAdmin = createAdmin();
    $user->linkAccount($userAdmin, requiresPassword: false);

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    expect(AccountSwitcher::canSwitchToLinkedAccount($user, $userAdmin))->toBeFalse();

    AccountSwitcher::switchToLinkedAccount($userAdmin);
})->throws(AccountSwitchDenied::class);

it('cannot switch to an account without panel access', function (): void {
    $user = createUser();
    $locked = createUser(['can_access_panel' => false]);
    $user->linkAccount($locked, requiresPassword: false);

    expect(AccountSwitcher::canSwitchToLinkedAccount($user, $locked))->toBeFalse();
});

it('lets the plugin gate deny switching', function (): void {
    $user = createUser();
    $other = createUser();
    $user->linkAccount($other, requiresPassword: false);

    AccountSwitcher::plugin()->canSwitchUsing(fn (): bool => false);

    expect(AccountSwitcher::canSwitchToLinkedAccount($user, $other))->toBeFalse();

    AccountSwitcher::plugin()->canSwitchUsing(null);
});

it('keeps the switch log on the user model connection', function (): void {
    // A second connection standing in for a tenant database: it becomes the
    // default, as a multi-database tenancy package would make it mid-request.
    config()->set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('database.default', 'tenant');
    config()->set('packstub-account-switcher.user_model', CentralUser::class);

    $admin = CentralUser::query()->create(['name' => 'Admin', 'email' => 'admin@central.test', 'password' => Hash::make('secret'), 'is_admin' => true]);
    $daily = CentralUser::query()->create(['name' => 'Daily', 'email' => 'daily@central.test', 'password' => Hash::make('secret')]);
    $admin->linkAccount($daily, requiresPassword: false);

    $this->actingAs($admin);

    expect(AccountSwitcher::connectionName())->toBe('sqlite')
        ->and((new AccountSwitch)->getConnectionName())->toBe('sqlite');

    AccountSwitcher::switchToLinkedAccount($daily);

    expect(AccountSwitch::query()->count())->toBe(1)
        ->and(Schema::connection('tenant')->hasTable((new AccountSwitch)->getTable()))->toBeFalse();
});

it('pins the tables to the configured connection', function (): void {
    config()->set('database.connections.audit', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('packstub-account-switcher.connection', 'audit');

    (include __DIR__.'/../../vendor/packstub/account-switcher/database/migrations/create_account_switches_table.php.stub')->up();

    expect((new AccountSwitch)->getConnectionName())->toBe('audit')
        ->and(Schema::connection('audit')->hasTable((new AccountSwitch)->getTable()))->toBeTrue();
});
