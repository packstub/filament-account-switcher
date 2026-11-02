# Changelog

All notable changes to `packstub/filament-account-switcher` are documented here.

## 4.4.0 — 2026-10-28

- A ⋮ menu on the impersonation banner; add your own links with `->bannerMenuItems([...])` (#30)
- New render hooks inside the banner, so adding to it no longer means publishing the view (#30)
- **Session replays** in that menu when [Filament Session Replay](https://packstub.dev/docs/filament-session-replay) 1.1 is on the panel (#27)
- The "Switch to" button shrinks to its icon on phones instead of wrapping
- The impersonation banner no longer spills over the page on narrow screens

A published `banner.blade.php` keeps working, without the menu and the hooks; republish it to get them.

## 4.3.0 — 2026-10-27

This plugin now builds on [`packstub/account-switcher`](https://github.com/packstub/account-switcher), the session core in a package of its own, so a Laravel app without Filament gets the same features.

- `->withoutDatabase()`: impersonation and developer logins only, no migrations or tables needed
- Nothing changes for most installs: same classes, config key, migrations and routes

**Upgrading:** translations for errors and switch reasons, and the config and migration publish tags, moved to the core. If you registered the service provider by hand, it is now `FilamentAccountSwitcherServiceProvider`. See [UPGRADE.md](UPGRADE.md#upgrading-to-43).

## 4.2.1 — 2026-10-15

- The developer login search box in the menu hides behind a search icon until you need it
- Docs: the search examples work on every database, not only PostgreSQL

## 4.2.0 — 2026-10-15

- Search in the "Switch to" menu once there are six or more developer logins
- Tag developer logins (by role, team, ...) to filter the menu and group the login page buttons
- Let the menu's search look up any account in the database with `->developerLoginSearchUsing()`

## 4.1.0 — 2026-10-15

- Developer logins in the "Switch to" menu too, to hop between seeded accounts without signing out
- More control over developer logins: a runtime gate, custom labels and their own landing page
- `redirectTo()` gets the switch reason, so impersonation and developer logins can land in different places
- A 403 or 404 right after a switch sends you to a panel the new account can access (on by default)
- Extra context saved with each switch log row, and `AccountSwitcher::log()` for switches made elsewhere

**Upgrading:** for the switch log's new `context` column, run `php artisan vendor:publish --tag="packstub-account-switcher-migrations"`, then `php artisan migrate`.

## 4.0.4 — 2026-09-28

- Fixed a "Missing parameter: tenant" error on tenant-aware pages without a tenant, such as tenant registration
- A leaner download: development files stay in the repository

## 4.0.3 — 2026-09-03

- The switch log and linked accounts now use the user model's connection, so multi-database tenancy works (new `connection` config key to pin it)
- The switch confirmation no longer mentions a password when the link does not need one

## 4.0.2 — 2026-08-26

- `banner(position: 'top')` no longer covers the panel topbar

## 4.0.1 — 2026-08-26

- Fixed an `SvgNotFound` error when rendering `ImpersonateAction`

## 4.0.0 — 2026-08-25

`xlite-dev/filament-impersonate` is now Filament Account Switcher, rebuilt around a shared session core.

- Linked accounts: a "Switch to" menu in the topbar and a page to link, create, rename and unlink accounts
- One-click developer logins on the login page, limited by environment
- An audit log of every switch, plus events before and after it
- No longer depends on `lab404/laravel-impersonate`

**Upgrading:** new package name, namespace `Packstub\AccountSwitcher`, a plugin to register on the panel, and a renamed config file. Follow [UPGRADE.md](UPGRADE.md).

Docs: https://packstub.dev/docs/filament-account-switcher

## 3.x

Filament v4/v5 support for the original `xlite-dev/filament-impersonate` plugin.
