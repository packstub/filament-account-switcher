# Filament Account Switcher

![Filament Account Switcher](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/banner.jpg)

Switch between accounts in a Filament panel without signing out — safely, in production. Free and open source (MIT).

- Repository: [github.com/packstub/filament-account-switcher](https://github.com/packstub/filament-account-switcher)
- Packagist: [packstub/filament-account-switcher](https://packagist.org/packages/packstub/filament-account-switcher)
- Support: [GitHub issues](https://github.com/packstub/filament-account-switcher/issues)

## What you get

| Feature | What it means for you |
| --- | --- |
| **Linked accounts** | Do daily work from a low-privilege account and switch to your full admin account only when you need it. A "Switch to" menu in the topbar lists the accounts you've linked. Switching *up* asks for that account's password; switching *down* is one click. |
| **Impersonation** | `ImpersonateAction` for your user resource, a persistent banner with "Switch back", and `canImpersonate()` / `canBeImpersonated()` hooks on your model. |
| **Developer logins** | One-click sign-in buttons on the login page for the accounts you seed locally — never rendered outside the environments you allow. |
| **Audit trail** | Every switch is recorded with who, to whom, why, panel, IP and user agent, and fires `AccountSwitching` / `AccountSwitched` events. |

## Guides

| Guide | What it covers |
| --- | --- |
| [Installation](installation.md) | Requirements, the install command, the user model trait, and registering the plugin |
| [Linked accounts](linked-accounts.md) | The "Switch to" menu, the Linked accounts page, sub-accounts, and the password rule |
| [Impersonation](impersonation.md) | The action, the banner, authorization hooks, and switching back |
| [Developer logins](developer-logins.md) | Login-page buttons for local development and how they are gated |
| [Configuration](configuration.md) | The fluent `AccountSwitcherPlugin` API, the config file, events, the switch log, and the facade |
| [Security](security.md) | The threat model behind each feature and the guarantees the plugin makes |

## At a glance

```bash
composer require packstub/filament-account-switcher
php artisan packstub-account-switcher:install
```

```php
use Packstub\AccountSwitcher\AccountSwitcherPlugin;

$panel->plugin(
    AccountSwitcherPlugin::make()
        ->developerLogins(['admin@example.com', 'user@example.com']),
);
```

## Requirements

PHP 8.2+, Laravel 12 or 13, Filament 4 or 5.

---

These pages are published at [packstub.dev/docs/filament-account-switcher](https://packstub.dev/docs/filament-account-switcher) from the package's `docs/` directory. Spotted a mistake? [Open a pull request](https://github.com/packstub/filament-account-switcher).
