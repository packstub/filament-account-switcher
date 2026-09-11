# Filament Account Switcher

<div class="filament-hidden">

![Filament Account Switcher — switch accounts, never sign out](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/banner.jpg)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/packstub/filament-account-switcher.svg?style=flat-square)](https://packagist.org/packages/packstub/filament-account-switcher)
[![Tests](https://img.shields.io/github/actions/workflow/status/packstub/filament-account-switcher/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/packstub/filament-account-switcher/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/packstub/filament-account-switcher.svg?style=flat-square)](https://packagist.org/packages/packstub/filament-account-switcher)
[![License](https://img.shields.io/packagist/l/packstub/filament-account-switcher.svg?style=flat-square)](https://github.com/packstub/filament-account-switcher/blob/main/LICENSE.md)
[![Listed on filamentphp.com](https://img.shields.io/badge/filamentphp.com-listed-fb7185?style=flat-square&logo=filament&logoColor=white)](https://filamentphp.com/plugins/packstub-account-switcher)
[![Sponsor](https://img.shields.io/badge/sponsor-%E2%9D%A4-ea4aaa?style=flat-square&logo=githubsponsors&logoColor=white)](https://github.com/sponsors/icaliman)

</div>

Switch between accounts in a Filament panel without signing out — safely, in production.

## Features

- **[Linked accounts](#linked-accounts)** — work from a low-privilege account day to day and switch to your full admin account only when you need it. Switching *up* asks for that account's password; switching *down* is one click.
- **[Impersonation](#impersonation)** — an `ImpersonateAction` for your user resource, a persistent banner with a *Switch back* button, and authorization hooks on your model.
- **[Developer logins](#developer-logins)** — one-click sign-in buttons on the login page for the accounts you seed locally, never rendered outside the environments you allow.
- **[Audit trail](https://packstub.dev/docs/filament-account-switcher/configuration#the-switch-log)** — every switch is recorded (who, to whom, why, panel, IP, user agent) and fires `AccountSwitching` / `AccountSwitched` events.
- **[Fluent plugin API](#configuration)** — enable each feature per panel, add your own authorization rules, move the banner, replace the Linked accounts page.
- **Dark mode ready** and **translatable** — built from Filament components, with every string in a language file.

Formerly `xlite-dev/filament-impersonate` — see the [upgrade guide](https://github.com/packstub/filament-account-switcher/blob/main/UPGRADE.md).

## Compatibility

| Plugin | Filament | Laravel | PHP |
| --- | --- | --- | --- |
| 4.x | 4.x, 5.x | 12.x, 13.x | 8.2+ |
| 3.x (`xlite-dev/filament-impersonate`) | 4.x, 5.x | 9.x – 12.x | 8.1+ |

## Installation

```bash
composer require packstub/filament-account-switcher
php artisan packstub-account-switcher:install
```

Add the trait to your user model and the plugin to your panel:

```php
use Packstub\AccountSwitcher\Concerns\HasLinkedAccounts;

class User extends Authenticatable implements FilamentUser
{
    use HasLinkedAccounts;

    public function canImpersonate(User $target): bool
    {
        return $this->hasRole('super-admin');
    }
}
```

```php
use Packstub\AccountSwitcher\AccountSwitcherPlugin;

$panel->plugin(
    AccountSwitcherPlugin::make()
        ->developerLogins(['admin@example.com', 'user@example.com']),
);
```

Full walkthrough: [Installation](https://packstub.dev/docs/filament-account-switcher/installation).

## Linked accounts

Link the accounts one person owns. A **Switch to** menu next to the user menu lists them with their label and e-mail.

![The Switch to menu listing the linked accounts](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/switch-to-menu.png)

Switching *down* to a lower-privilege account is one click. Switching *up* asks for the **target** account's password, so a compromised daily session can never reach the admin account on its own.

![Switching up to the admin account asks for its password](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/switch-password-modal.png)

The **Linked accounts** page (in the user menu) links an existing account, creates a sub-account, renames links and sets the per-link password rule.

![The Linked accounts page](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/linked-accounts-page.png)

```php
// Or link programmatically — one click down, password up
$admin->linkAccount($daily, label: 'Daily', requiresPassword: false);
```

Read more: [Linked accounts](https://packstub.dev/docs/filament-account-switcher/linked-accounts) · [Security model](https://packstub.dev/docs/filament-account-switcher/security).

## Impersonation

Drop `ImpersonateAction` into your users table or a page header. It is hidden for records the current user may not impersonate, based on `canImpersonate()` / `canBeImpersonated()` on your model.

```php
use Packstub\AccountSwitcher\Filament\Actions\ImpersonateAction;

->recordActions([
    ImpersonateAction::make(),
])
```

![The Impersonate action on each row of the users table](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/impersonate-action.png)

While impersonating, a banner stays visible on every page with a **Switch back** button.

![The impersonation banner with a Switch back button](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/impersonation-banner.png)

Read more: [Impersonation](https://packstub.dev/docs/filament-account-switcher/impersonation).

## Developer logins

One-click sign-in for the accounts you seed, shown under the login form. They only render in the environments listed in the config (`local` by default), whatever the panel says.

```php
AccountSwitcherPlugin::make()
    ->developerLogins(['admin@example.com', 'user@example.com'])
```

![Developer login buttons under the login form](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/docs/developer-logins.png)

Read more: [Developer logins](https://packstub.dev/docs/filament-account-switcher/developer-logins).

## Configuration

```php
AccountSwitcherPlugin::make()
    ->impersonation()                                  // on by default
    ->linkedAccounts()                                 // on by default
    ->developerLogins(['admin@example.com'])           // off by default
    ->canImpersonateUsing(fn (User $by, User $target) => $by->can('impersonate', $target))
    ->canSwitchUsing(fn (User $from, User $to) => ! $to->is_locked)
    ->banner(position: 'top', style: 'light')
    ->redirectTo('/admin')
```

Read more: [Configuration](https://packstub.dev/docs/filament-account-switcher/configuration) — the config file, events, the switch log and the `AccountSwitcher` facade.

## Documentation

- [Installation](https://packstub.dev/docs/filament-account-switcher/installation)
- [Linked accounts](https://packstub.dev/docs/filament-account-switcher/linked-accounts)
- [Impersonation](https://packstub.dev/docs/filament-account-switcher/impersonation)
- [Developer logins](https://packstub.dev/docs/filament-account-switcher/developer-logins)
- [Configuration](https://packstub.dev/docs/filament-account-switcher/configuration)
- [Security](https://packstub.dev/docs/filament-account-switcher/security)

The same pages live in the [`docs/`](https://github.com/packstub/filament-account-switcher/tree/main/docs) directory of this repository.

## Testing

```bash
composer test
```

## Changelog

See the [changelog](https://github.com/packstub/filament-account-switcher/blob/main/CHANGELOG.md).

## Security vulnerabilities

Please e-mail [support@packstub.dev](mailto:support@packstub.dev) rather than opening a public issue.

## Credits

- [Ion Caliman](https://github.com/icaliman)
- [All contributors](https://github.com/packstub/filament-account-switcher/contributors)

## License

MIT. See the [license file](https://github.com/packstub/filament-account-switcher/blob/main/LICENSE.md).
