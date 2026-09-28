<?php

use Packstub\AccountSwitcher\Filament\Pages\LinkedAccounts;
use Packstub\AccountSwitcher\Tests\Fixtures\Team;

it('renders the switcher on tenant registration without a manage link', function (): void {
    $admin = createAdmin();
    $admin->linkAccount(createUser(), label: 'Daily');

    $this->actingAs($admin);

    $this->get('/app/new')
        ->assertOk()
        ->assertSee('Switch to')
        ->assertDontSee('Manage linked accounts');
});

it('links the manage page under the current tenant', function (): void {
    $admin = createAdmin();
    $admin->linkAccount(createUser(), label: 'Daily');
    $team = Team::query()->create(['name' => 'Acme']);
    $team->members()->attach($admin);

    $this->actingAs($admin);

    $this->get("/app/{$team->getKey()}")
        ->assertOk()
        ->assertSee('Manage linked accounts')
        ->assertSee(LinkedAccounts::getUrl(panel: 'app', tenant: $team), escape: false);
});
