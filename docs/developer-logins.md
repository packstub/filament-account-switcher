# Developer logins

One-click sign-in buttons under the login form, for the accounts you seed in development. Pick a user, land in the panel — no password typing while you build.

![The login page with one-click developer login buttons under the form](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/developer-logins.png)

## Enabling

Developer logins are **off** by default. Turn them on per panel and say which users appear:

```php
use Packstub\AccountSwitcher\AccountSwitcherPlugin;

AccountSwitcherPlugin::make()
    // e-mails of seeded users
    ->developerLogins(['admin@example.com', 'editor@example.com', 'customer@example.com'])
```

Other forms:

```php
// a closure returning the users (any iterable of Authenticatable)
->developerLogins(fn () => User::query()->whereIn('role', ['admin', 'editor'])->get())

// the first ten users by id
->developerLogins(true)

// off
->developerLogins(false)
```

## In the "Switch to" menu

Signed-in developers can hop between the seeded accounts without signing out: list the developer logins in the switcher dropdown next to the user menu as well.

```php
AccountSwitcherPlugin::make()
    ->developerLogins(fn () => User::query()->whereIn('username', ['superadmin', 'admin', 'employee'])->get())
    ->developerLoginsInMenu()
```

The menu shows the current account highlighted, and a switch made from it stays on the page it was made on (see [Where it lands](#where-it-lands)). The menu is hidden while impersonating.

### Many accounts

With six or more developer logins the list scrolls and a search icon appears in the section header; it opens and focuses a search box (Escape clears and closes it). The search matches the label, the tag, the name, the e-mail and the username.

### Tags

Tag each account with what it is — its role, its team — and the menu offers the tags as filter chips; the login page groups its buttons under them:

```php
AccountSwitcherPlugin::make()
    ->developerLoginGroupUsing(fn (User $user): ?string => $user->roles->first()?->name)
```

### Any account

To sign in as *any* account, not only the listed ones, give the menu a search resolver. The listed accounts remain the default view (and the only ones offered on the login page); as soon as something is typed, the resolver's results replace them:

```php
AccountSwitcherPlugin::make()
    ->developerLogins(fn () => User::query()->whereIn('username', ['superadmin', 'admin', 'employee'])->get())
    ->developerLoginsInMenu()
    ->developerLoginSearchUsing(fn (string $search) => User::query()
        ->where(fn ($query) => $query
            ->whereLike('name', "%{$search}%", caseSensitive: false)
            ->orWhereLike('email', "%{$search}%", caseSensitive: false))
        ->limit(20)
        ->get())
```

`whereLike(..., caseSensitive: false)` is case-insensitive on every database Laravel supports (`ILIKE` on PostgreSQL, `LOWER(...) LIKE` elsewhere). Keep the result small. A switch is accepted only for an account the *current* search returned, and the resolver is never consulted outside the [gates](#how-its-gated) below.

## Labels

By default a button or menu entry shows Filament's user name and the e-mail. To show what the account *is* instead of who it is:

```php
AccountSwitcherPlugin::make()
    ->developerLoginLabelUsing(fn (User $user): string => "{$user->role_label} ({$user->username})")
```

## How it's gated

Three independent checks must pass before a button renders or a login is accepted:

1. **Environment** — `config('packstub-account-switcher.developer_logins.environments')`, `['local']` by default. This is a hard ceiling: no panel setting can make the buttons appear in an environment that isn't listed.
2. **Plugin** — `->developerLogins()` must be enabled on the panel, and the user must be in the resolved list.
3. **`developerLoginsWhen()`** — an optional runtime gate, for a rule the environment list cannot express. A staging server that should let signed-in staff switch accounts but never advertise them on the login page:

```php
// config: 'environments' => ['local', 'staging']
AccountSwitcherPlugin::make()
    ->developerLogins([...])
    ->developerLoginsInMenu()
    ->developerLoginsWhen(fn (): bool => app()->environment('local') || Filament::auth()->check())
```

The login itself is a CSRF-protected `POST` to `filament.{panel}.account-switcher.developer-login`, throttled to 10 requests per minute. The controller re-runs both checks and returns 404 when either fails, so a stray button or a crafted request never signs anyone in.

```php
// config/packstub-account-switcher.php
'developer_logins' => [
    'environments' => ['local', 'testing'],
],
```

## What happens on click

The panel guard is re-authenticated as the chosen user, the session is regenerated, an [audit row](configuration.md#the-switch-log) with reason `SwitchReason::DeveloperLogin` is written, and the browser is redirected.

## Where it lands

- From the **login page**: `developerLoginRedirectTo()` if set, else the usual [switch redirect](configuration.md#redirects) (`redirectTo()` with `SwitchReason::DeveloperLogin`, else a panel the user may access).
- From the **menu**: `developerLoginRedirectTo()` if set, else the page the switch was made on. If that page is hidden from the new account, the [landing-page fallback](impersonation.md#landing-on-a-hidden-page) takes it to the panel home.

```php
AccountSwitcherPlugin::make()
    ->developerLoginRedirectTo(fn (User $user): string => $user->is_admin ? '/admin' : '/app')
```

## Customizing the buttons

Publish the views and edit `resources/views/vendor/packstub-account-switcher/developer-logins.blade.php`:

```bash
php artisan vendor:publish --tag="packstub-account-switcher-views"
```

The view receives `$users` (a collection) and renders one form per user using Filament's `<x-filament::button>`.
