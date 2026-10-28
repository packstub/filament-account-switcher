# Impersonation

Sign in as another user to see what they see, then switch back. Impersonation is for support and debugging; for your *own* accounts use [linked accounts](linked-accounts.md).

## The action

![The Impersonate icon button on each row of the users table](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/impersonate-action.png)

`ImpersonateAction` works as a table record action or a page header action:

```php
use Packstub\AccountSwitcher\Filament\Actions\ImpersonateAction;

// UserResource table
->recordActions([
    ImpersonateAction::make(),
])

// EditUser / ViewUser page
protected function getHeaderActions(): array
{
    return [
        ImpersonateAction::make()->record($this->getRecord()),
    ];
}
```

It's an icon button by default; chain the usual `Action` methods to change that (`->button()`, `->label()`, `->color()`, `->requiresConfirmation()`).

Clicking it re-authenticates the session as the record, stores the impersonator in the session, writes an audit row, and redirects to a panel the impersonated user may access (the current one when possible — see `redirectTo()` in [Configuration](configuration.md#redirects)).

## Authorization

The action is hidden, and `AccountSwitcher::impersonate()` throws, unless **all** of these pass:

1. The target is not the current user.
2. The session is not already impersonating (no nesting).
3. `canImpersonate($target)` on the current user returns true — if the method exists.
4. `canBeImpersonated($by)` on the target returns true — if the method exists.
5. The plugin's `canImpersonateUsing()` callback returns true — if set.

```php
class User extends Authenticatable
{
    public function canImpersonate(User $target): bool
    {
        return $this->hasRole('super-admin');
    }

    public function canBeImpersonated(User $by): bool
    {
        return ! $this->hasRole('super-admin');
    }
}
```

> [!IMPORTANT]
> Without `canImpersonate()` on your model, every panel user can impersonate. Add it before going to production, or use `canImpersonateUsing()` on the plugin.

```php
AccountSwitcherPlugin::make()
    ->canImpersonateUsing(fn (User $by, User $target): bool => $by->can('impersonate', $target))
```

## The banner

![The impersonation banner at the bottom of the panel with a Switch back button](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/impersonation-banner.png)

While impersonating, a fixed banner reads *"You are signed in as Jane, impersonating from Admin"* with a **Switch back** button, and a ⋮ [menu](#the-banner-menu) when there is something to put in it. It renders at the bottom of every panel page; move it to the top or switch to the light style:

```php
AccountSwitcherPlugin::make()
    ->banner(position: 'top', style: 'light')
```

![The banner at the top of the page in the light style](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/impersonation-banner-top-light.png)

To restyle it completely, publish the views and edit `resources/views/vendor/packstub-account-switcher/banner.blade.php`:

```bash
php artisan vendor:publish --tag="packstub-account-switcher-views"
```

The banner is a plain Blade view with inline styles so it works with any panel theme and doesn't need a custom Tailwind build. On a phone (below 40rem) the bar is a little taller and the message keeps to two lines beside the buttons. Before publishing the view to add something, look at the [menu](#the-banner-menu) and the [render hooks](#render-hooks): they survive upgrades, a published view does not.

### The banner menu

Next to **Switch back** sits a ⋮ button that opens a menu, so the banner stays one row on a phone however many things you hang on it. It only appears when the menu has items: [Session replays](#session-replays) when Filament Session Replay is installed, and your own.

Your own items are Filament actions with a URL, the same kind you give `userMenuItems()`:

```php
use Filament\Actions\Action;

AccountSwitcherPlugin::make()
    ->bannerMenuItems([
        Action::make('crm')
            ->label('Open in CRM')
            ->icon('heroicon-m-arrow-top-right-on-square')
            ->url('https://crm.example.com', shouldOpenInNewTab: true),
    ])
```

Pass a closure to build them from the people involved. It gets the impersonated person and the impersonator (null when that account no longer exists):

```php
AccountSwitcherPlugin::make()
    ->bannerMenuItems(fn (User $impersonated, ?User $impersonator): array => [
        Action::make('orders')
            ->label('Their orders')
            ->url(OrderResource::getUrl('index', ['filters' => ['customer' => ['value' => $impersonated->getKey()]]])),
        Action::make('notes')
            ->label('Support notes')
            ->url(route('support.notes', $impersonated))
            ->visible(fn (): bool => $impersonator?->can('viewSupportNotes') ?? false),
    ])
```

- Items render in order, Session replays first; each call to `bannerMenuItems()` adds to the menu.
- `->postToUrl()` makes an item a CSRF-protected POST form, for an endpoint that changes something, like the built-in Session replays.
- `->visible()` and `->hidden()` are honoured. An action named like an earlier one replaces it, so `Action::make('session-replays')` relabels or hides the built-in item.
- Items open URLs; an action with a modal or a Livewire method needs a page, not the banner.

`AccountSwitcherPlugin::get()->getBannerMenuItems($impersonated, $impersonator)` returns the visible items, keyed by name.

### Render hooks

The banner has render hooks of its own, for anything that is not a menu item: a badge, a countdown, a link of your own design. They render only while impersonating, on panels where the plugin is registered.

```php
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Packstub\AccountSwitcher\Filament\View\AccountSwitcherRenderHook;

FilamentView::registerRenderHook(
    AccountSwitcherRenderHook::BANNER_MESSAGE_AFTER,
    fn (): string => Blade::render('<x-filament::badge color="warning">Production</x-filament::badge>'),
);
```

| Hook | Where |
| --- | --- |
| `AccountSwitcherRenderHook::BANNER_START` | First thing in the banner, before the message |
| `AccountSwitcherRenderHook::BANNER_MESSAGE_AFTER` | Right after the message |
| `AccountSwitcherRenderHook::BANNER_ACTIONS_BEFORE` | Before **Switch back** |
| `AccountSwitcherRenderHook::BANNER_MENU_BEFORE` | Inside the ⋮ menu, before its items |
| `AccountSwitcherRenderHook::BANNER_MENU_AFTER` | Inside the ⋮ menu, after its items |
| `AccountSwitcherRenderHook::BANNER_ACTIONS_AFTER` | After the ⋮ menu |
| `AccountSwitcherRenderHook::BANNER_END` | Last thing in the banner |

The two menu hooks take dropdown items (`<x-filament::dropdown.list.item>`), and either one alone is enough for the ⋮ button to appear. Keep anything in the bar itself small: the banner is 3rem high, 3.5rem on a phone.

### Session replays

With [Filament Session Replay](https://packstub.dev/docs/filament-session-replay) on a panel, the banner menu gets **Session replays**. Impersonation and replay answer the same support question from two sides: "let me see it as they do" and "let me see what they did". Session Replay already names the impersonator on a recording made while impersonating; this item is the way back: it ends the impersonation and opens the Sessions list filtered to the person you were signed in as.

It ends the impersonation first because the session is theirs until you switch back, and the list is yours: Session Replay's `viewSessionReplay` gate (or its policy) is asked about *you*, the impersonator, never about the impersonated person. The list opens in the current panel when it has the Sessions resource, else in the first panel that does and that you may access; in a panel with tenancy it is the current workspace's list, when you belong to that workspace, else the next panel. The item is left out when no such panel exists, when the gate says no, or on a tenant-less page of a panel with tenancy.

Nothing to configure; Session Replay is detected with a `class_exists()` check, the same way it detects this plugin. To leave it out:

```php
AccountSwitcherPlugin::make()
    ->sessionReplayLink(false)
```

The item posts to `filament.{panel}.account-switcher.session-replays`, a CSRF-protected route under the panel's authenticated (and, with tenancy, tenant) routes; `AccountSwitcherPlugin::get()->getSessionReplaysUrl($impersonated, $impersonator)` is the URL it lands on, or null. Requires Filament Session Replay 1.1.

## Landing on a hidden page

The page the browser lands on after a switch may be one the new account cannot see — a record the impersonated user has no access to, or a resource page that does not exist for them. Rather than show them a 403 or 404 as their first impression, the plugin flashes a fallback URL on every switch and, if the landing request fails with 403 or 404, redirects to a panel the new account may access. It applies to that one request only; later 403s and 404s are shown as usual.

```php
AccountSwitcherPlugin::make()
    ->fallbackToAccessiblePanel(false) // on by default
```

## Switching back

**Switch back** posts to `filament.{panel}.account-switcher.switch-back`, a CSRF-protected route inside the panel's authenticated group. It restores the impersonator's session, logs the end of the impersonation, and returns to the page the impersonation started from.

Programmatically:

```php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

AccountSwitcher::isImpersonating();  // bool
AccountSwitcher::impersonator();     // ?Authenticatable
$url = AccountSwitcher::stopImpersonating();
```

## Turning it off

```php
AccountSwitcherPlugin::make()->impersonation(false)
```

The action stays hidden, the route isn't registered, and `AccountSwitcher::impersonate()` throws `AccountSwitchDenied`.

## Events and log

Impersonating dispatches `AccountSwitching` then `AccountSwitched` with `SwitchReason::Impersonation`; switching back dispatches `AccountSwitched` with `SwitchReason::ImpersonationEnded`. Both write to the [switch log](configuration.md#the-switch-log). Listen to them to alert on impersonation of sensitive accounts or to invalidate API tokens.
