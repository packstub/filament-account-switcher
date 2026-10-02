# Filament Account Switcher

![Filament Account Switcher](https://raw.githubusercontent.com/packstub/art/main/filament-account-switcher/banner.jpg)

Switch between accounts in a Filament panel without signing out — safely, in production. Free and open source (MIT).

- Repository: [github.com/packstub/filament-account-switcher](https://github.com/packstub/filament-account-switcher)
- Packagist: [packstub/filament-account-switcher](https://packagist.org/packages/packstub/filament-account-switcher)
- Support: [GitHub issues](https://github.com/packstub/filament-account-switcher/issues)

## Features

- **[Linked accounts](linked-accounts.md)**: work from a low-privilege account and switch up to admin only when you need it.
- **[Impersonation](impersonation.md)**: an action for your users table, a banner with Switch back, rules on your model.
- **[Developer logins](developer-logins.md)**: one-click sign-in buttons for your seeded accounts, only in the environments you allow.
- **[Audit trail](configuration.md#the-switch-log)**: every switch logged with who, why and from where, plus events.
- **[Security](security.md)**: switching up asks for the target account's password; each feature has a written threat model.

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
