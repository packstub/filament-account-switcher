<?php

use Filament\Facades\Filament;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Filament\Livewire\AccountSwitcherMenu;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Packstub\AccountSwitcher\Tests\Fixtures\User;

use function Pest\Livewire\livewire;

function menuPlugin(): AccountSwitcherPlugin
{
    return Filament::getPanel('admin')->getPlugin(AccountSwitcherPlugin::ID);
}

beforeEach(function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    menuPlugin()->developerLoginsInMenu();
    $this->actingAs(createAdmin());
});

it('filters the listed accounts with the search box once there are enough of them', function (): void {
    foreach (range(1, 6) as $i) {
        createUser(['email' => "dev-user{$i}@example.com", 'name' => "Dev {$i}"]);
    }

    livewire(AccountSwitcherMenu::class)
        ->assertSee('Search accounts')
        ->assertSee('Dev 1')
        ->assertSee('Dev 6')
        ->set('developerLoginSearch', 'dev 6')
        ->assertSee('Dev 6')
        ->assertDontSee('Dev 1')
        ->set('developerLoginSearch', 'nobody')
        ->assertSee('No account matches.');
});

it('shows no search box for a short list', function (): void {
    createUser(['email' => 'dev-alice@example.com']);

    livewire(AccountSwitcherMenu::class)
        ->assertDontSee('Search accounts');
});

it('offers the groups as tags and filters by them', function (): void {
    menuPlugin()->developerLoginGroupUsing(fn (User $user): string => $user->is_admin ? 'Admin' : 'Staff');

    createUser(['email' => 'dev-alice@example.com', 'name' => 'Alice', 'is_admin' => true]);
    createUser(['email' => 'dev-bob@example.com', 'name' => 'Bob']);

    livewire(AccountSwitcherMenu::class)
        ->assertSee('All')
        ->assertSeeInOrder(['Admin', 'Staff'])
        ->assertSee('Alice')
        ->assertSee('Bob')
        ->call('filterDeveloperLogins', 'Staff')
        ->assertDontSee('Alice')
        ->assertSee('Bob')
        ->call('filterDeveloperLogins', 'Staff')
        ->assertSee('Alice');
});

it('groups the login page buttons under their tags', function (): void {
    auth()->logout();
    menuPlugin()->developerLoginGroupUsing(fn (User $user): string => $user->is_admin ? 'Admin' : 'Staff');

    createUser(['email' => 'dev-alice@example.com', 'name' => 'Alice', 'is_admin' => true]);
    createUser(['email' => 'dev-bob@example.com', 'name' => 'Bob']);

    $this->get('/admin/login')
        ->assertOk()
        ->assertSeeInOrder(['Admin', 'Alice', 'Staff', 'Bob']);
});

it('finds any account through the search resolver and signs in as it', function (): void {
    menuPlugin()->developerLoginSearchUsing(fn (string $search) => User::query()->where('email', 'like', "%{$search}%")->limit(5)->get());

    $dev = createUser(['email' => 'dev-alice@example.com', 'name' => 'Alice']);
    $customer = createUser(['email' => 'customer@example.com', 'name' => 'Customer']);

    livewire(AccountSwitcherMenu::class)
        ->assertSee('Search any account')
        ->assertSee('Alice')
        ->assertDontSee('Customer')
        ->set('developerLoginSearch', 'customer')
        ->assertSee('Customer')
        ->assertDontSee('Alice')
        ->call('developerLogin', $customer->id)
        ->assertRedirect();

    expect(Filament::auth()->user()->is($customer))->toBeTrue()
        ->and(AccountSwitch::query()->sole()->reason)->toBe(SwitchReason::DeveloperLogin);
});

it('refuses an account the current search did not return', function (): void {
    menuPlugin()->developerLoginSearchUsing(fn (string $search) => User::query()->where('email', 'like', "%{$search}%")->limit(5)->get());

    $admin = Filament::auth()->user();
    $customer = createUser(['email' => 'customer@example.com']);

    livewire(AccountSwitcherMenu::class)
        ->set('developerLoginSearch', 'nobody')
        ->call('developerLogin', $customer->id)
        ->assertNotFound();

    expect(Filament::auth()->user()->is($admin))->toBeTrue()
        ->and(AccountSwitch::query()->count())->toBe(0);
});

it('keeps the search resolver behind the environment gate', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['local']);
    menuPlugin()->developerLoginSearchUsing(fn (string $search) => User::query()->get());

    createUser(['email' => 'customer@example.com', 'name' => 'Customer']);

    livewire(AccountSwitcherMenu::class)
        ->assertDontSee('Search any account')
        ->set('developerLoginSearch', 'customer')
        ->assertDontSee('Customer');
});

it('keeps the search resolver off the login page', function (): void {
    auth()->logout();
    menuPlugin()->developerLoginSearchUsing(fn (string $search) => User::query()->get());

    $dev = createUser(['email' => 'dev-alice@example.com', 'name' => 'Alice']);
    $customer = createUser(['email' => 'customer@example.com', 'name' => 'Customer']);

    $this->get('/admin/login')->assertOk()->assertSee('Alice')->assertDontSee('Customer');

    $this->post(route('filament.admin.account-switcher.developer-login'), ['user' => $customer->id])->assertNotFound();
});
