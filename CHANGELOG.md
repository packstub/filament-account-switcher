# Changelog

All notable changes to `packstub/filament-account-switcher` are documented here.

## 4.2.0 — 2026-10-15

### Added

- **Search in the "Switch to" menu**: with six or more developer logins the menu gets a search box that filters the list by label, tag, name, e-mail and username; the list scrolls instead of growing past the viewport.
- **Tags**: `->developerLoginGroupUsing(fn ($user): ?string)` tags each developer login (its role, its team, ...). The menu offers the tags as filter chips and the login page groups its buttons under them.
- **Any account**: `->developerLoginSearchUsing(fn (string $search): iterable)` lets the menu's search box look up accounts in the database instead of filtering the listed ones. The listed accounts stay the default view and the only ones on the login page; a switch is accepted only for an account the current search returned, and the environment gate and `developerLoginsWhen()` still apply.

## 4.1.0 — 2026-10-15

### Added

- **Developer logins in the "Switch to" menu**: `->developerLoginsInMenu()` lists the developer logins in the switcher dropdown too, so a signed-in developer hops between the seeded accounts without signing out. From the menu a switch stays on the page it was made on.
- **`->developerLoginsWhen(fn (): bool)`**: a runtime gate on top of the environment list, checked before the buttons render *and* when a login is posted (for example: on a staging server, only offer the accounts to someone already signed in).
- **`->developerLoginLabelUsing(fn ($user): string)`**: how a developer login is labelled on the login page and in the menu.
- **`->developerLoginRedirectTo()`**: where a developer login lands when it should differ from `redirectTo()`.
- **`redirectTo()` receives the reason**: the closure is now called as `fn (Authenticatable $user, ?SwitchReason $reason)`, so one callback can send an impersonation and a developer login to different places.
- **Landing-page fallback**: after a switch, a 403 or 404 on the page the browser lands on is answered with a redirect to a panel the new account may access, once. On by default; `->fallbackToAccessiblePanel(false)` turns it off per panel.
- **Switch-log context**: a nullable `context` JSON column on `account_switches`, filled from `AccountSwitcher::resolveLogContextUsing()` (app-wide, e.g. the current tenant) and `->switchLogContextUsing()` (per panel). New migration `add_context_to_account_switches_table` for existing installs: `php artisan vendor:publish --tag="packstub-account-switcher-migrations"` then `php artisan migrate`.
- **`AccountSwitcher::log($from, $to, $reason, $context)`**: write a switch-log row for a switch that happened outside the plugin (an SSO hand-off, a tenancy impersonation token). Honours the same on/off switches as the plugin's own rows.

### Changed

- **Docs**: a shorter Features list in the README and on the docs index, one line per area.

## 4.0.4 — 2026-09-28

### Changed

- **Leaner dist archive**: `composer require` downloads what an install runs; the changelog (read it on GitHub, or in each release's notes), `pint.json` and the other development files stay in the repository (`.gitattributes` `export-ignore`).
- **Package health in CI** (`.github/workflows/package-health.yml`, `.github/scripts/package-health.sh`): every push checks what the package health score on filamentphp.com checks (Powered by [Plumb](https://plumbphp.dev/packstub/filament-account-switcher)) — a lean dist archive, no `composer.lock` in it, actions pinned to a commit SHA, Dependabot with a cooldown for every ecosystem that has a lockfile, a security policy — on the commit, before a tag ships it. A weekly run reads the published score and fails below 100.

### Fixed

- In a tenant-aware panel, pages without a tenant (tenant registration, `/app/new`) threw `Missing required parameter … [Missing parameter: tenant]`: the switcher menu and the user menu built the linked accounts page URL, which lives under `{tenant}`. The "Manage linked accounts" link and the user menu item are now left out there; switching between linked accounts still works.

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
