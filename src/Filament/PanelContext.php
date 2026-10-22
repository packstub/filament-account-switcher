<?php

namespace Packstub\AccountSwitcher\Filament;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Collection;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;
use Packstub\AccountSwitcher\AppContext;
use Packstub\AccountSwitcher\Contracts\SwitchContext;

/**
 * The switch context of a Filament app: the current panel's guard and URLs,
 * and the options of the AccountSwitcherPlugin registered on it. Outside a
 * panel, or on a panel without the plugin, it answers like a plain app.
 */
class PanelContext implements SwitchContext
{
    public function __construct(protected AppContext $app) {}

    public function plugin(): ?AccountSwitcherPlugin
    {
        $panel = $this->panel();

        if (! $panel?->hasPlugin(AccountSwitcherPlugin::ID)) {
            return null;
        }

        return $panel->getPlugin(AccountSwitcherPlugin::ID);
    }

    protected function panel(): ?Panel
    {
        return Filament::getCurrentOrDefaultPanel();
    }

    // ------------------------------------------------------------------
    // Panels
    // ------------------------------------------------------------------

    /**
     * The current panel when the user may access it, else the first panel they may.
     */
    public function accessiblePanel(Authenticatable $user): ?Panel
    {
        $current = $this->panel();

        if ($current && $this->canAccessPanel($user, $current)) {
            return $current;
        }

        return collect(Filament::getPanels())
            ->first(fn (Panel $panel): bool => $this->canAccessPanel($user, $panel));
    }

    /**
     * Mirrors Filament's own Authenticate middleware rule.
     */
    public function canAccessPanel(Authenticatable $user, Panel $panel): bool
    {
        if ($user instanceof FilamentUser) {
            return $user->canAccessPanel($panel);
        }

        return app()->environment('local');
    }

    // ------------------------------------------------------------------
    // SwitchContext
    // ------------------------------------------------------------------

    public function guard(): StatefulGuard
    {
        return $this->panel()?->auth() ?? $this->app->guard();
    }

    public function guardName(): string
    {
        return $this->panel()?->getAuthGuard() ?? $this->app->guardName();
    }

    public function scope(): ?string
    {
        return $this->panel()?->getId();
    }

    public function userLabel(Authenticatable $user): string
    {
        return Filament::getUserName($user);
    }

    public function canAccess(Authenticatable $user): bool
    {
        return $this->accessiblePanel($user) !== null;
    }

    /**
     * The plugin's redirectTo() if set, else the current panel when the user
     * may access it, else the first panel they may, else the current panel.
     */
    public function redirectUrlFor(Authenticatable $user): string
    {
        $redirect = $this->plugin()?->getRedirectTo();

        if ($redirect instanceof Closure) {
            $redirect = $redirect($user);
        }

        if (filled($redirect)) {
            return $redirect;
        }

        $panel = $this->accessiblePanel($user) ?? $this->panel();

        return $panel?->getUrl() ?? $this->app->redirectUrlFor($user);
    }

    public function loginUrl(): string
    {
        return $this->panel()?->getLoginUrl() ?? $this->app->loginUrl();
    }

    public function hasImpersonation(): bool
    {
        return $this->plugin()?->hasImpersonation() ?? $this->app->hasImpersonation();
    }

    public function hasLinkedAccounts(): bool
    {
        return $this->plugin()?->hasLinkedAccounts() ?? $this->app->hasLinkedAccounts();
    }

    public function hasDeveloperLogins(): bool
    {
        return $this->plugin()?->hasDeveloperLogins() ?? $this->app->hasDeveloperLogins();
    }

    public function allowsImpersonation(Authenticatable $by, Authenticatable $target): bool
    {
        $plugin = $this->plugin();

        return $plugin ? $plugin->evaluateCanImpersonate($by, $target) : $this->app->allowsImpersonation($by, $target);
    }

    public function allowsSwitch(Authenticatable $from, Authenticatable $target): bool
    {
        $plugin = $this->plugin();

        return $plugin ? $plugin->evaluateCanSwitch($from, $target) : $this->app->allowsSwitch($from, $target);
    }

    public function developerLoginUsers(): Collection
    {
        return $this->plugin()?->resolveDeveloperLoginUsers() ?? $this->app->developerLoginUsers();
    }

    public function logsSwitches(): bool
    {
        return $this->plugin()?->shouldLogSwitches() ?? $this->app->logsSwitches();
    }

    public function switchBackUrl(): ?string
    {
        return $this->panelRoute('account-switcher.switch-back') ?? $this->app->switchBackUrl();
    }

    public function developerLoginUrl(): ?string
    {
        return $this->panelRoute('account-switcher.developer-login') ?? $this->app->developerLoginUrl();
    }

    protected function panelRoute(string $name): ?string
    {
        $panel = $this->panel();

        if (! $panel?->hasPlugin(AccountSwitcherPlugin::ID)) {
            return null;
        }

        $name = $panel->generateRouteName($name);

        return app('router')->has($name) ? route($name) : null;
    }
}
