# packstub/filament-account-switcher

Free Filament v4/v5 plugin: switch between accounts in a panel. User impersonation with a banner, linked sub-accounts (password-confirmed escalation), developer logins and an audit log. Built on `packstub/account-switcher` (the sibling repo, `plugins/account-switcher`), which owns the session core: the `AccountSwitcher` service and facade, the `SwitchContext` contract, models, trait, events, enum, exception, controllers, config file and migrations. This package is the panel side. Both share the `Packstub\AccountSwitcher\` namespace (Composer merges the PSR-4 directories), so class names must not collide across them.

## Commands

```bash
composer test               # Pest suite (Testbench, in-memory SQLite, an admin panel and a tenant panel)
composer test:filter <name>
composer lint               # Pint
```

The core comes from Packagist (`^1.0`, `minimum-stability` `stable`). To work on both packages at once, add a path repository locally and leave it out of the commit:

```bash
composer config repositories.account-switcher '{"type":"path","url":"../account-switcher","options":{"symlink":true,"versions":{"packstub/account-switcher":"1.0.0"}}}'
composer update packstub/account-switcher
```

## Layout

- `src/AccountSwitcherPlugin.php`: the panel plugin (feature toggles, `canImpersonateUsing`, `canSwitchUsing`, `createSubAccountUsing`, `redirectTo`, `logSwitches`, banner placement, the Linked accounts page), the panel routes (`filament.{panel}.account-switcher.*`, the core's controllers) and the render hooks (menu, banner, developer logins).
- `src/Filament/PanelContext.php`: the core's `SwitchContext` for a Filament app: the current panel's guard, id (the log's `panel` column), `Filament::getUserName()`, `canAccessPanel()`, the plugin's options, the panel routes for the banner and the login buttons. Falls back to the core's `AppContext` outside a panel or without the plugin.
- `src/Filament/FilamentAccountSwitcherServiceProvider.php`: binds `SwitchContext` to `PanelContext` (unconditionally; the core binds its own only when nothing is bound, so the order does not matter), the views, translations, the icon, the Livewire menu, the `plugin()` and `accessiblePanel()` macros on the facade, and `packstub-account-switcher:install` (an alias of the core's installer).
- `src/Filament/Livewire/AccountSwitcherMenu`, `src/Filament/Pages/LinkedAccounts`, `src/Filament/Actions/ImpersonateAction`; `resources/views` (Filament components, inline styles for the banner); `resources/lang/en/account-switcher.php` (panel strings only; errors and reasons are the core's `account-switcher::messages`).
- `tests/Fixtures`: `AdminPanelProvider`, `TenantPanelProvider` (tenancy, the page under `{tenant}`), `User` (FilamentUser, HasTenants, HasLinkedAccounts), `CentralUser` (pinned connection). The migrations are included from `vendor/packstub/account-switcher`.
- `docs/` customer docs (synced into the store under `filament-account-switcher/1.x`), `docs/images/` screenshots, `art/` listing art.

## Conventions

- Every change needs a Pest test; `CHANGELOG.md` and `UPGRADE.md` stay current.
- Session handling, authorization and the audit log belong to the core. If a change needs them, make it there, require the new core minor here, and run this suite after a change in the core.
- Config keys, route names, the session key, the translation keys and the `SwitchContext` methods are shared with the core; they must not change inside a major.
- Naming by channel: **Filament Account Switcher** on packstub.dev, **Packstub Account Switcher** on filamentphp.com.
