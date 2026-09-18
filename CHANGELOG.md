# Changelog

All notable changes to `packstub/filament-account-switcher` are documented here.

## Unreleased

### Changed

- **Leaner dist archive**: `composer require` downloads what an install runs; the changelog (read it on GitHub, or in each release's notes), `pint.json` and the other development files stay in the repository (`.gitattributes` `export-ignore`).
- **Package health in CI** (`.github/workflows/package-health.yml`, `.github/scripts/package-health.sh`): every push checks what the package health score on filamentphp.com checks (Powered by [Plumb](https://plumbphp.dev/packstub/filament-account-switcher)) — a lean dist archive, no `composer.lock` in it, actions pinned to a commit SHA, Dependabot with a cooldown for every ecosystem that has a lockfile, a security policy — on the commit, before a tag ships it. A weekly run reads the published score and fails below 100.

## 4.0.3 — 2026-09-03

### Fixed

- The switch log and the linked-accounts pivot were written on the default connection. With multi-database tenancy that is the tenant connection, where the tables do not exist (`no such table: account_switches`). Both models — and the migrations — now follow the user model's connection; a new `connection` config key pins it explicitly.
- The switch confirmation modal for a link that does not require a password no longer asks for one in its description.

## 4.0.2 — 2026-08-26

### Fixed

- `banner(position: 'top')` no longer covers the panel topbar: the page shell is pushed down and Filament's sticky topbar container and sidebar are re-anchored below the banner (Filament 4 and 5 selectors).

## 4.0.1 — 2026-08-26

### Fixed

- `ImpersonateAction` threw `SvgNotFound` at render time: the plugin's icon set was registered with a hyphenated prefix, which `blade-icons` cannot resolve. The icon is now `packstub:account:switcher-icon`.

## 4.0.0 — 2026-08-25

Renamed from `xlite-dev/filament-impersonate` and rebuilt around a shared session core. See [UPGRADE.md](UPGRADE.md).

### Added

- **Linked accounts**: `HasLinkedAccounts` trait, "Switch to" topbar menu, Linked accounts page (link an existing account, create a sub-account, rename, unlink, per-link password requirement).
- **Developer logins**: one-click login buttons on the panel login page, gated by environment and by the plugin's user list.
- **Audit log**: `account_switches` table and `AccountSwitch` model recording every switch with reason, panel, guard, IP and user agent.
- `AccountSwitching` and `AccountSwitched` events.
- `AccountSwitcherPlugin` with feature toggles and hooks: `impersonation()`, `linkedAccounts()`, `developerLogins()`, `canImpersonateUsing()`, `canSwitchUsing()`, `createSubAccountUsing()`, `redirectTo()`, `logSwitches()`, `banner()`, `switcherRenderHook()`, `linkedAccountsPage()`.
- `AccountSwitcher` service and facade.
- PHPUnit test suite covering all three features.

### Changed

- Namespace is now `Packstub\AccountSwitcher`; `ImpersonateAction` lives in `Packstub\AccountSwitcher\Filament\Actions`.
- Impersonation no longer depends on `lab404/laravel-impersonate`.
- The "leave" route is now a `POST` inside the panel's authenticated route group (`filament.{panel}.account-switcher.switch-back`), CSRF-protected.
- Redirect after a switch resolves to a panel the target account may access instead of a hard-coded `/`.
- Config file renamed to `packstub-account-switcher.php`; `guard`, `redirect_to` and `leave_middlewares` keys were removed (the panel's guard and routes are used).

### Removed

- `XliteDev\FilamentImpersonate\*` classes, the `filament-impersonate` config/view namespaces, and the `<x-filament-impersonate::banner />` component.

## 3.x

Filament v4/v5 support for the original `xlite-dev/filament-impersonate` plugin.
