<?php

use Filament\Facades\Filament;
use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Facades\AccountSwitcher as AccountSwitcherFacade;
use Packstub\AccountSwitcher\Models\AccountSwitch;

afterEach(fn () => AccountSwitcher::resolveLogContextUsing(null));

it('records a switch made outside the plugin with its context', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $row = AccountSwitcherFacade::log($admin, $user, SwitchReason::Impersonation, ['tenant' => 'acme']);

    expect($row)->toBeInstanceOf(AccountSwitch::class)
        ->and($row->from_user_id)->toBe($admin->id)
        ->and($row->to_user_id)->toBe($user->id)
        ->and($row->context)->toBe(['tenant' => 'acme'])
        ->and(AccountSwitch::query()->sole()->context)->toBe(['tenant' => 'acme']);
});

it('merges the app-wide and the panel context into every row', function (): void {
    AccountSwitcher::resolveLogContextUsing(fn (SwitchReason $reason): array => ['tenant' => 'acme', 'via' => 'app']);
    Filament::getPanel('admin')->getPlugin(AccountSwitcherPlugin::ID)->switchLogContextUsing(fn (SwitchReason $reason): array => ['via' => 'panel', 'reason' => $reason->value]);

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcherFacade::impersonate($user);

    expect(AccountSwitch::query()->sole()->context)->toBe(['tenant' => 'acme', 'via' => 'panel', 'reason' => 'impersonation']);
});

it('writes nothing when the log is off', function (): void {
    Filament::getPanel('admin')->getPlugin(AccountSwitcherPlugin::ID)->logSwitches(false);

    expect(AccountSwitcherFacade::log(createAdmin(), createUser(), SwitchReason::Impersonation))->toBeNull()
        ->and(AccountSwitch::query()->count())->toBe(0);
});
