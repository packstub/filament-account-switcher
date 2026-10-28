# Configuration

Most settings live on the plugin so each panel can differ; a small config file holds the app-wide ones (tables, user model, environments). The config file, the migrations, the models, the events and the facade belong to [`packstub/account-switcher`](https://packstub.dev/docs/account-switcher), the session core this plugin builds on; its `features`, `redirect_to`, `guard` and `developer_logins.users` keys only apply outside a panel, because inside one the plugin decides.

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

    // Developer logins: in the switcher menu too, an extra runtime gate, labels, their own landing page
    ->developerLoginsInMenu()
    ->developerLoginsWhen(fn (): bool => app()->environment('local') || Filament::auth()->check())
    ->developerLoginLabelUsing(fn (User $user): string => "{$user->role} ({$user->username})")
    ->developerLoginGroupUsing(fn (User $user): ?string => $user->role)
    ->developerLoginSearchUsing(fn (string $search) => User::query()->whereLike('email', "%{$search}%", caseSensitive: false)->limit(20)->get())
    ->developerLoginRedirectTo('/app')

    // Where to land after any switch (string or Closure(Authenticatable, ?SwitchReason): string)
    ->redirectTo(fn (User $user, ?SwitchReason $reason): string => $user->is_admin ? '/admin' : '/app')

    // After a switch, send a 403/404 landing page to a panel the account may access (on by default)
    ->fallbackToAccessiblePanel(true)

    // Audit log on/off for this panel (defaults to the config value), and extra columns for its rows
    ->logSwitches(true)
    ->switchLogContextUsing(fn (SwitchReason $reason): array => ['tenant' => tenant('id')])

    // Impersonation and developer logins only, no tables:
    // linkedAccounts(false) + logSwitches(false)
    ->withoutDatabase()

    // UI placement
    ->switcherRenderHook(PanelsRenderHook::USER_MENU_BEFORE)
    ->banner(position: 'bottom', style: 'dark')
    ->linkedAccountsPage(App\Filament\Pages\LinkedAccounts::class)

    // The "Session replays" button on the banner, with Filament Session Replay installed (on by default)
    ->sessionReplayLink(true)
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
| `sessionReplayLink()` | on | No **Session replays** button on the banner (see [Impersonation](impersonation.md#session-replays)); it only ever renders with Filament Session Replay installed |

Each accepts a `Closure` for runtime decisions, e.g. `->impersonation(fn (): bool => config('features.impersonation'))`.

`withoutDatabase()` turns off the two features that need the package's tables, linked accounts and the switch log, so the panel runs without its migrations. See [Installation](installation.md#without-migrations).

### Redirects

After a switch the plugin sends the browser to:

1. `redirectTo()` if set (string, or a closure receiving the target user and the `SwitchReason`), else
2. the **current panel** if the target user may access it, else
3. the **first panel** the target user may access, else
4. the current panel's URL (Filament will then show 403).

"May access" means `canAccessPanel()` for `FilamentUser` models, or "local environment" for other models — the same rule Filament's own `Authenticate` middleware applies.

Developer logins have their own `developerLoginRedirectTo()`; see [Developer logins](developer-logins.md#where-it-lands). And whatever the target, if the landing page answers 403 or 404 the plugin redirects once more, to a panel the account may access — see [Landing on a hidden page](impersonation.md#landing-on-a-hidden-page).

## The config file

```bash
php artisan vendor:publish --tag="account-switcher-config"
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
| `context` | Extra data you attach (JSON, nullable) — see below |
| `created_at` | When |

Query it with the `Packstub\AccountSwitcher\Models\AccountSwitch` model (`fromUser()` / `toUser()` relationships), or build a Filament resource on it for an "Account activity" page. Turn logging off globally with `log_switches` or per panel with `->logSwitches(false)`.

### Context

Attach what the row cannot infer — the tenant, a ticket number, the client app. App-wide, from a service provider:

```php
use Packstub\AccountSwitcher\AccountSwitcher;

AccountSwitcher::resolveLogContextUsing(fn (SwitchReason $reason): array => [
    'tenant' => tenant()?->getTenantKey(),
]);
```

Per panel, merged over the app-wide context: `->switchLogContextUsing(fn (SwitchReason $reason): array => [...])`.

Installs that predate 4.1 need the column: `php artisan vendor:publish --tag="account-switcher-migrations"` publishes `add_context_to_account_switches_table`, then `php artisan migrate`.

### Switches made elsewhere

Some switches never go through the plugin — a tenancy package's impersonation token redeemed on another domain, an SSO hand-off. Log them in the same table so the audit trail stays complete:

```php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

AccountSwitcher::log(from: $centralUser, to: $tenantUser, reason: SwitchReason::Impersonation, context: ['tenant' => $tenant->id]);
```

It honours `log_switches` and the panel's `->logSwitches()` and returns the row, or `null` when logging is off.

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
| `developerLoginsEnabled()` / `developerLoginUsers()` / `searchDeveloperLogins($search)` / `developerLogin($user, $search)` / `developerLoginRedirectUrl($user)` | Developer logins |
| `switchableAccounts($user)` / `passwordMatches($account, $password)` | The accounts a user may switch to right now; verify a plain-text password |
| `redirectUrlFor($user, $reason)` / `accessiblePanel($user)` / `redirectWhenPageIsInaccessible($exception, $request)` | Panel resolution and the landing-page fallback |
| `log($from, $to, $reason, $context)` / `AccountSwitcher::resolveLogContextUsing($callback)` | The switch log |
| `plugin()` / `context()` / `userModel()` | Current panel's plugin instance; the `PanelContext` behind the service; resolved user model class |

Denied operations throw `Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied` (an `AuthorizationException`).

## Translations and views

```bash
php artisan vendor:publish --tag="packstub-account-switcher-translations"
php artisan vendor:publish --tag="packstub-account-switcher-views"
```

Strings live under `lang/vendor/packstub-account-switcher/{locale}/account-switcher.php`; views under `resources/views/vendor/packstub-account-switcher/` (`menu`, `banner`, `developer-logins`, `pages/linked-accounts`). The error messages and the switch reason labels come from the core package: `php artisan vendor:publish --tag="account-switcher-translations"` puts them under `lang/vendor/account-switcher/{locale}/messages.php`.
