<?php

use Filament\Actions\Action;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Filament\View\AccountSwitcherRenderHook;
use Packstub\AccountSwitcher\Tests\Fixtures\User;

function impersonateSomeone(): array
{
    $admin = createAdmin();
    $user = createUser(['name' => 'Grace Customer']);

    test()->actingAs($admin);
    AccountSwitcher::impersonate($user);

    return [$admin, $user];
}

it('has no menu when there is nothing to put in it', function (): void {
    impersonateSomeone();

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Switch back')
        ->assertDontSee('aria-label="More"', escape: false);
});

it('puts Session replays in the menu, as a form that posts to the panel route', function (): void {
    Gate::define('viewSessionReplay', fn (User $user): bool => $user->is_admin);

    impersonateSomeone();

    $this->get('/admin')
        ->assertOk()
        ->assertSee('aria-label="More"', escape: false)
        ->assertSee('action="'.route('filament.admin.account-switcher.session-replays').'" method="post"', escape: false)
        ->assertSee('Session replays');
});

it('lists the app\'s own items, leaves out hidden ones and opens on top of a bottom banner', function (): void {
    impersonateSomeone();

    AccountSwitcher::plugin()->bannerMenuItems([
        Action::make('crm')->label('Open in CRM')->icon('heroicon-m-arrow-top-right-on-square')->url('https://crm.test/people', shouldOpenInNewTab: true),
        Action::make('secret')->label('Never shown')->url('/secret')->visible(false),
    ]);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Open in CRM')
        ->assertSee('href="https://crm.test/people" target="_blank"', escape: false)
        ->assertDontSee('Never shown')
        ->assertSee('x-float.placement.top-end', escape: false);
});

it('hands a closure the impersonated person and the impersonator, and adds up several calls', function (): void {
    [$admin, $user] = impersonateSomeone();

    AccountSwitcher::plugin()
        ->bannerMenuItems(fn (Authenticatable $impersonated, ?Authenticatable $impersonator): array => [
            Action::make('orders')->label('Their orders')->url("/admin/orders?customer={$impersonated->getAuthIdentifier()}&by={$impersonator?->getAuthIdentifier()}"),
        ])
        ->bannerMenuItems([Action::make('help')->label('Support playbook')->url('/help')]);

    expect(array_keys(AccountSwitcher::plugin()->getBannerMenuItems($user, $admin)))->toBe(['orders', 'help']);

    $this->get('/admin')
        ->assertOk()
        ->assertSeeInOrder(['Their orders', 'Support playbook'])
        ->assertSee("/admin/orders?customer={$user->id}&amp;by={$admin->id}", escape: false);
});

it('lets an item named session-replays replace or hide the built-in one', function (): void {
    Gate::define('viewSessionReplay', fn (User $user): bool => $user->is_admin);

    [$admin, $user] = impersonateSomeone();

    AccountSwitcher::plugin()->bannerMenuItems([
        Action::make('session-replays')->label('Watch what they did')->url('/replays'),
    ]);

    $this->get('/admin')->assertOk()->assertSee('Watch what they did')->assertDontSee('Session replays');

    AccountSwitcher::plugin()->bannerMenuItems([Action::make('session-replays')->hidden()]);

    expect(AccountSwitcher::plugin()->getBannerMenuItems($user, $admin))->toBe([]);
});

it('renders every banner hook while impersonating, and a menu hook alone opens the menu', function (): void {
    foreach ((new ReflectionClass(AccountSwitcherRenderHook::class))->getConstants() as $name => $hook) {
        FilamentView::registerRenderHook($hook, fn (): string => "<i>hook:{$name}</i>");
    }

    $this->actingAs(createAdmin())->get('/admin')->assertOk()->assertDontSee('hook:BANNER_START');

    impersonateSomeone();

    $this->get('/admin')
        ->assertOk()
        ->assertSee('aria-label="More"', escape: false)
        ->assertSeeInOrder([
            'hook:BANNER_START',
            'You are signed in as',
            'hook:BANNER_MESSAGE_AFTER',
            'hook:BANNER_ACTIONS_BEFORE',
            'Switch back',
            'hook:BANNER_MENU_BEFORE',
            'hook:BANNER_MENU_AFTER',
            'hook:BANNER_ACTIONS_AFTER',
            'hook:BANNER_END',
        ], escape: false);
});

it('opens the menu below a banner at the top', function (): void {
    impersonateSomeone();

    AccountSwitcher::plugin()
        ->banner(position: 'top')
        ->bannerMenuItems([Action::make('help')->label('Support playbook')->url('/help')]);

    $this->get('/admin')->assertOk()->assertSee('x-float.placement.bottom-end', escape: false);
});
