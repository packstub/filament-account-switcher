# Configuration

Most settings live on the plugin so each panel can differ; a small config file holds the app-wide ones (tables, user model, environments).

## The plugin API

```php
use Filament\View\PanelsRenderHook;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;

AccountSwitcherPlugin::make()
    // Feature toggles (bool or Closure)
    ->impersonation(true)
    ->linkedAccounts(true, userMenuItem: true)
    ->developerLogins(['admin@example.com'])

    // Authorization hooks
    ->canImpersonateUsing(fn (User $by, User $target): bool => $by->can('impersonate', $target))
    ->canSwitchUsing(fn (User $from, User $to): bool => ! $to->is_locked)

    // Sub-account creation
    ->createSubAccountUsing(fn (array $data, User $owner): User => User::create($data))

    // Where to land after any switch (string or Closure(Authenticatable): string)
    ->redirectTo(fn (User $user): string => $user->is_admin ? '/admin' : '/app')

    // Audit log on/off for this panel (defaults to the config value)
    ->logSwitches(true)

    // UI placement
    ->switcherRenderHook(PanelsRenderHook::USER_MENU_BEFORE)
    ->banner(position: 'bottom', style: 'dark')
    ->linkedAccountsPage(App\Filament\Pages\LinkedAccounts::class)
```

### Banner placement

`banner(position: 'top', style: 'light')` renders the impersonation banner at the top of the page in the light style; the default is a dark banner at the bottom.

![The impersonation banner at the top of the page in the light style](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/impersonation-banner-top-light.png)

### Feature toggles

| Method | Default | Effect when off |
| --- | --- | --- |
| `impersonation()` | on | `ImpersonateAction` hidden, switch-back route not registered, banner not rendered, `impersonate()` throws |
| `linkedAccounts()` | on | No "Switch to" menu, no Linked accounts page or user menu item, `switchToLinkedAccount()` throws |
| `developerLogins()` | **off** | No buttons, developer-login route not registered |

Each accepts a `Closure` for runtime decisions, e.g. `->impersonation(fn (): bool => config('features.impersonation'))`.

### Redirects

After a switch the plugin sends the browser to:

1. `redirectTo()` if set (string, or a closure receiving the target user), else
2. the **current panel** if the target user may access it, else
3. the **first panel** the target user may access, else
4. the current panel's URL (Filament will then show 403).

"May access" means `canAccessPanel()` for `FilamentUser` models, or "local environment" for other models — the same rule Filament's own `Authenticate` middleware applies.

## The config file

```bash
php artisan vendor:publish --tag="packstub-account-switcher-config"
```

```php
return [
    // The model linked accounts and the switch log point to.
    // null = config('auth.providers.users.model').
    'user_model' => null,

    // The connection both tables live on.
    // null = the user model's connection.
    'connection' => null,

    'tables' => [
        'linked_accounts' => 'linked_accounts',
        'account_switches' => 'account_switches',
    ],

    // Write an account_switches row for every switch.
    'log_switches' => true,

    'developer_logins' => [
        // Hard ceiling; the plugin toggle cannot override it.
        'environments' => ['local'],
    ],

    'banner' => [
        'position' => 'bottom', // 'top' | 'bottom'
        'style' => 'dark',      // 'dark' | 'light'
    ],
];
```

### User model

Linked accounts and the switch log are relationships on one user model. It is resolved from `user_model`, falling back to `auth.providers.users.model`. Set it **before** running the migrations — they derive the foreign keys and the referenced table from it.

### Connection

Both tables follow the user model's connection, so they always sit next to the `users` table. That is what a multi-database tenancy setup needs: a user model on a central connection keeps linked accounts and the switch log central, even while a request runs on a tenant connection. Set `connection` to pin them somewhere else. The migrations honour the same setting.

## The switch log

Every switch writes one row to `account_switches`:

| Column | Meaning |
| --- | --- |
| `from_user_id` | Who was signed in (null for a developer login from the login page) |
| `to_user_id` | Who the session became |
| `reason` | `impersonation`, `impersonation_ended`, `linked_account`, `developer_login` (`SwitchReason` enum) |
| `panel`, `guard` | Where it happened |
| `ip_address`, `user_agent` | From the request |
| `created_at` | When |

Query it with the `Packstub\AccountSwitcher\Models\AccountSwitch` model (`fromUser()` / `toUser()` relationships), or build a Filament resource on it for an "Account activity" page. Turn logging off globally with `log_switches` or per panel with `->logSwitches(false)`.

## Events

| Event | When | Payload |
| --- | --- | --- |
| `Packstub\AccountSwitcher\Events\AccountSwitching` | After authorization passed, before the session changes. Throw to abort. | `?Authenticatable $from`, `Authenticatable $to`, `SwitchReason $reason` |
| `Packstub\AccountSwitcher\Events\AccountSwitched` | After the session changed and the log row was written | same |

```php
use Packstub\AccountSwitcher\Events\AccountSwitched;

Event::listen(AccountSwitched::class, function (AccountSwitched $event): void {
    if ($event->reason === SwitchReason::Impersonation && $event->to->is_vip) {
        Notification::route('mail', 'security@example.com')->notify(new VipImpersonated($event));
    }
});
```

## The facade

`Packstub\AccountSwitcher\Facades\AccountSwitcher` proxies the `AccountSwitcher` service:

| Method | Purpose |
| --- | --- |
| `canImpersonate($by, $target)` / `impersonate($target)` / `isImpersonating()` / `impersonator()` / `stopImpersonating()` | Impersonation |
| `canSwitchToLinkedAccount($from, $to)` / `requiresPassword($from, $to)` / `switchToLinkedAccount($to, $password)` | Linked accounts |
| `developerLoginsEnabled()` / `developerLoginUsers()` / `developerLogin($user)` | Developer logins |
| `redirectUrlFor($user)` / `accessiblePanel($user)` | Panel resolution |
| `plugin()` / `userModel()` | Current panel's plugin instance; resolved user model class |

Denied operations throw `Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied` (an `AuthorizationException`).

## Translations and views

```bash
php artisan vendor:publish --tag="packstub-account-switcher-translations"
php artisan vendor:publish --tag="packstub-account-switcher-views"
```

Strings live under `lang/vendor/packstub-account-switcher/{locale}/account-switcher.php`; views under `resources/views/vendor/packstub-account-switcher/` (`menu`, `banner`, `developer-logins`, `pages/linked-accounts`).
