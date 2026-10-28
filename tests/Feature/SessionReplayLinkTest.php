<?php

use Illuminate\Support\Facades\Gate;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Packstub\AccountSwitcher\Tests\Fixtures\Team;
use Packstub\AccountSwitcher\Tests\Fixtures\User;
use Packstub\SessionReplay\Filament\Resources\ReplaySessions\ReplaySessionResource;

/**
 * Filament Session Replay is registered on the admin and the tenant panel (tests/TestCase.php),
 * with the Sessions resource on; who may watch is its viewSessionReplay gate.
 */
function letAdminsWatchReplays(): void
{
    Gate::define('viewSessionReplay', fn (User $user): bool => $user->is_admin);
}

it('offers the session replays of the impersonated person on the banner, to an impersonator who may watch', function (): void {
    letAdminsWatchReplays();

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Session replays')
        ->assertSee(route('filament.admin.account-switcher.session-replays'), escape: false);

    expect(AccountSwitcherPlugin::get()->getSessionReplaysUrl($user, $admin))
        ->toBe(ReplaySessionResource::getUrlForPerson($user, 'admin'));
});

it('hides the button when the impersonator may not watch, and never asks the impersonated person', function (): void {
    // The session is the customer's while impersonating; the gate is asked about the impersonator.
    Gate::define('viewSessionReplay', fn (User $user): bool => ! $user->is_admin);

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    // The customer may watch, so the Sessions resource is in their navigation; the banner still has no button.
    $this->get('/admin')
        ->assertOk()
        ->assertSee('Switch back')
        ->assertDontSee(route('filament.admin.account-switcher.session-replays'), escape: false);

    expect(AccountSwitcherPlugin::get()->getSessionReplaysUrl($user, $admin))->toBeNull();
});

it('hides the button without a gate (nobody may watch outside the local environment) and when turned off', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    $this->get('/admin')->assertOk()->assertDontSee('Session replays');

    letAdminsWatchReplays();

    $this->get('/admin')->assertOk()->assertSee('Session replays');

    AccountSwitcher::plugin()->sessionReplayLink(false);

    $this->get('/admin')->assertOk()->assertDontSee('Session replays');

    expect(AccountSwitcherPlugin::get()->getSessionReplaysUrl($user, $admin))->toBeNull();

    AccountSwitcher::plugin()->sessionReplayLink(true);
});

it('ends the impersonation and lands on the list filtered to the person', function (): void {
    letAdminsWatchReplays();

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    $this->post(route('filament.admin.account-switcher.session-replays'))
        ->assertRedirect(ReplaySessionResource::getUrlForPerson($user, 'admin'));

    expect(AccountSwitcher::isImpersonating())->toBeFalse()
        ->and(auth()->user()->is($admin))->toBeTrue()
        ->and(AccountSwitch::query()->latest('id')->first()->reason)->toBe(SwitchReason::ImpersonationEnded);
});

it('switches back the usual way when there is no list to open', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    $this->post(route('filament.admin.account-switcher.session-replays'))->assertRedirect();

    expect(AccountSwitcher::isImpersonating())->toBeFalse()
        ->and(auth()->user()->is($admin))->toBeTrue();

    // Not impersonating: a plain redirect home, nothing to end.
    $this->post(route('filament.admin.account-switcher.session-replays'))->assertRedirect();

    expect(auth()->user()->is($admin))->toBeTrue();
});

it('opens the recordings of the current workspace in a panel with tenancy, when the impersonator belongs to it', function (): void {
    letAdminsWatchReplays();

    $admin = createAdmin();
    $user = createUser();
    $team = Team::query()->create(['name' => 'Acme']);
    $team->members()->attach([$admin->getKey(), $user->getKey()]);

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    $this->get("/app/{$team->getKey()}")
        ->assertOk()
        ->assertSee('Session replays')
        ->assertSee(route('filament.app.account-switcher.session-replays', ['tenant' => $team]), escape: false);

    $this->post(route('filament.app.account-switcher.session-replays', ['tenant' => $team]))
        ->assertRedirect(ReplaySessionResource::getUrlForPerson($user, 'app', $team));

    expect(AccountSwitcher::isImpersonating())->toBeFalse();
});

// One panel per test: Filament boots the current panel once per process, as the other suites do.

it('falls back to another panel when the impersonator is outside the workspace', function (): void {
    letAdminsWatchReplays();

    $admin = createAdmin();
    $user = createUser();
    $team = Team::query()->create(['name' => 'Acme']);
    $team->members()->attach($user);

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    // The customer's workspace is not the admin's: the admin panel lists every workspace instead.
    $this->post(route('filament.app.account-switcher.session-replays', ['tenant' => $team]))
        ->assertRedirect(ReplaySessionResource::getUrlForPerson($user, 'admin'));

    expect(AccountSwitcher::isImpersonating())->toBeFalse();
});

it('falls back to another panel when the current one has no recordings', function (): void {
    letAdminsWatchReplays();

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    // The lite panel has no Session Replay: the banner still offers the admin panel's list.
    $this->get('/lite')
        ->assertOk()
        ->assertSee('Session replays')
        ->assertSee(route('filament.lite.account-switcher.session-replays'), escape: false);

    $this->post(route('filament.lite.account-switcher.session-replays'))
        ->assertRedirect(ReplaySessionResource::getUrlForPerson($user, 'admin'));
});

it('hides the button when the impersonator may not enter any panel with recordings', function (): void {
    letAdminsWatchReplays();

    $admin = createAdmin(['can_access_panel' => false]);
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    expect(AccountSwitcherPlugin::get()->getSessionReplaysUrl($user, $admin))->toBeNull();
});
