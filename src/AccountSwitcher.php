<?php

namespace Packstub\AccountSwitcher;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Packstub\AccountSwitcher\Concerns\HasLinkedAccounts;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Events\AccountSwitched;
use Packstub\AccountSwitcher\Events\AccountSwitching;
use Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The session core shared by all three features. Every switch goes through
 * authenticateAs(): re-login on the panel guard, session regeneration, and
 * clearing Laravel's per-guard password hash so AuthenticateSession does not
 * log the new user out on the next request.
 */
class AccountSwitcher
{
    public const SESSION_KEY = 'packstub_account_switcher';

    /**
     * Flashed on every switch: where to send the browser when the page it lands on is hidden from the new account.
     */
    public const FALLBACK_URL_SESSION_KEY = 'packstub_account_switcher_fallback_url';

    protected static ?Closure $logContextResolver = null;

    /**
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        return config('packstub-account-switcher.user_model')
            ?? config('auth.providers.users.model');
    }

    /**
     * The connection the plugin's tables live on: the configured one, or
     * the user model's own connection (null = the application default).
     */
    public static function connectionName(): ?string
    {
        $connection = config('packstub-account-switcher.connection');

        if (filled($connection)) {
            return $connection;
        }

        $userModel = static::userModel();

        return $userModel ? (new $userModel)->getConnectionName() : null;
    }

    public function plugin(): ?AccountSwitcherPlugin
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if (! $panel?->hasPlugin(AccountSwitcherPlugin::ID)) {
            return null;
        }

        return $panel->getPlugin(AccountSwitcherPlugin::ID);
    }

    // ------------------------------------------------------------------
    // Impersonation
    // ------------------------------------------------------------------

    public function canImpersonate(Authenticatable $by, Authenticatable $target): bool
    {
        if ($by->getAuthIdentifier() === $target->getAuthIdentifier()) {
            return false;
        }

        if ($this->isImpersonating()) {
            return false;
        }

        $plugin = $this->plugin();

        if ($plugin && ! $plugin->hasImpersonation()) {
            return false;
        }

        if (method_exists($by, 'canImpersonate') && ! $by->canImpersonate($target)) {
            return false;
        }

        if (method_exists($target, 'canBeImpersonated') && ! $target->canBeImpersonated($by)) {
            return false;
        }

        if ($plugin && ! $plugin->evaluateCanImpersonate($by, $target)) {
            return false;
        }

        return true;
    }

    /**
     * @throws AccountSwitchDenied
     */
    public function impersonate(Authenticatable $target, ?Authenticatable $by = null): void
    {
        $by ??= $this->guard()->user();

        if (! $by || ! $this->canImpersonate($by, $target)) {
            throw AccountSwitchDenied::cannotImpersonate();
        }

        AccountSwitching::dispatch($by, $target, SwitchReason::Impersonation);

        session()->put(self::SESSION_KEY.'.impersonator_id', $by->getAuthIdentifier());
        session()->put(self::SESSION_KEY.'.impersonator_guard', $this->guardName());
        session()->put(self::SESSION_KEY.'.back_to', url()->previous());

        $this->authenticateAs($target);
        $this->record($by, $target, SwitchReason::Impersonation);

        AccountSwitched::dispatch($by, $target, SwitchReason::Impersonation);
    }

    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY.'.impersonator_id');
    }

    public function impersonator(): ?Authenticatable
    {
        if (! $this->isImpersonating()) {
            return null;
        }

        $guard = session()->get(self::SESSION_KEY.'.impersonator_guard', $this->guardName());

        return auth()->guard($guard)->getProvider()->retrieveById(
            session()->get(self::SESSION_KEY.'.impersonator_id'),
        );
    }

    /**
     * Restore the impersonator's session and return the URL to send them to.
     */
    public function stopImpersonating(): string
    {
        $impersonator = $this->impersonator();
        $impersonated = $this->guard()->user();
        $backTo = session()->get(self::SESSION_KEY.'.back_to');

        session()->forget(self::SESSION_KEY);

        if (! $impersonator) {
            $this->guard()->logout();

            return Filament::getCurrentOrDefaultPanel()?->getLoginUrl() ?? url('/');
        }

        $this->authenticateAs($impersonator);

        if ($impersonated) {
            $this->record($impersonated, $impersonator, SwitchReason::ImpersonationEnded);
            AccountSwitched::dispatch($impersonated, $impersonator, SwitchReason::ImpersonationEnded);
        }

        return $backTo ?: $this->redirectUrlFor($impersonator, SwitchReason::ImpersonationEnded);
    }

    // ------------------------------------------------------------------
    // Linked accounts
    // ------------------------------------------------------------------

    public function canSwitchToLinkedAccount(Authenticatable $from, Authenticatable $target): bool
    {
        if ($this->isImpersonating()) {
            return false;
        }

        if ($from->getAuthIdentifier() === $target->getAuthIdentifier()) {
            return false;
        }

        if (! $this->supportsLinkedAccounts($from) || ! $from->isLinkedTo($target)) {
            return false;
        }

        if ($this->accessiblePanel($target) === null) {
            return false;
        }

        $plugin = $this->plugin();

        if ($plugin && (! $plugin->hasLinkedAccounts() || ! $plugin->evaluateCanSwitch($from, $target))) {
            return false;
        }

        return true;
    }

    public function requiresPassword(Authenticatable $from, Authenticatable $target): bool
    {
        if (! $this->supportsLinkedAccounts($from)) {
            return true;
        }

        $link = $from->linkedAccounts()->whereKey($target->getAuthIdentifier())->first();

        return $link?->pivot->requires_password ?? true;
    }

    /**
     * @throws AccountSwitchDenied
     */
    public function switchToLinkedAccount(Authenticatable $target, ?string $password = null, ?Authenticatable $from = null): void
    {
        $from ??= $this->guard()->user();

        if ($this->isImpersonating()) {
            throw AccountSwitchDenied::whileImpersonating();
        }

        if (! $from || ! $this->canSwitchToLinkedAccount($from, $target)) {
            throw AccountSwitchDenied::notLinked();
        }

        if ($this->requiresPassword($from, $target) && ! $this->passwordMatches($target, $password)) {
            throw AccountSwitchDenied::invalidPassword();
        }

        AccountSwitching::dispatch($from, $target, SwitchReason::LinkedAccount);

        $this->authenticateAs($target);
        $this->record($from, $target, SwitchReason::LinkedAccount);

        AccountSwitched::dispatch($from, $target, SwitchReason::LinkedAccount);
    }

    public function supportsLinkedAccounts(?Authenticatable $user): bool
    {
        return $user !== null && in_array(HasLinkedAccounts::class, class_uses_recursive($user), true);
    }

    /**
     * Verify that the given plain-text password belongs to the account.
     */
    public function passwordMatches(Authenticatable $account, ?string $password): bool
    {
        $hash = $account->getAuthPassword();

        return filled($password) && filled($hash) && Hash::check($password, $hash);
    }

    // ------------------------------------------------------------------
    // Developer logins
    // ------------------------------------------------------------------

    public function developerLoginsEnabled(): bool
    {
        $environments = config('packstub-account-switcher.developer_logins.environments', ['local']);

        $plugin = $this->plugin();

        return app()->environment($environments)
            && ($plugin?->hasDeveloperLogins() ?? false)
            && $plugin->evaluateDeveloperLoginsWhen();
    }

    /**
     * Where a developer login lands: the plugin's developerLoginRedirectTo() if set, else the usual switch redirect.
     */
    public function developerLoginRedirectUrl(Authenticatable $user): string
    {
        $redirect = $this->plugin()?->getDeveloperLoginRedirectTo();

        if ($redirect instanceof Closure) {
            $redirect = $redirect($user);
        }

        return filled($redirect) ? $redirect : $this->redirectUrlFor($user, SwitchReason::DeveloperLogin);
    }

    /**
     * @return Collection<int, Authenticatable>
     */
    public function developerLoginUsers(): Collection
    {
        if (! $this->developerLoginsEnabled()) {
            return collect();
        }

        return $this->plugin()->resolveDeveloperLoginUsers();
    }

    /**
     * The accounts matching a search in the menu: the plugin's search resolver when it has one,
     * else the listed accounts filtered by the text. Empty outside the developer-login gates.
     *
     * @return Collection<int, Authenticatable>
     */
    public function searchDeveloperLogins(string $search): Collection
    {
        if (! $this->developerLoginsEnabled()) {
            return collect();
        }

        $plugin = $this->plugin();

        if ($plugin?->hasDeveloperLoginSearch()) {
            return $plugin->searchDeveloperLogins($search);
        }

        $search = mb_strtolower(trim($search));

        return $this->developerLoginUsers()
            ->filter(fn (Authenticatable $user): bool => $search === '' || str_contains(mb_strtolower(implode(' ', array_filter([
                $plugin?->developerLoginLabel($user),
                $plugin?->developerLoginGroup($user),
                $user->name ?? null,
                $user->email ?? null,
                $user->username ?? null,
            ]))), $search))
            ->values();
    }

    /**
     * Whether the account may be signed into through the developer logins: listed, or found by the search resolver.
     */
    public function isDeveloperLoginAllowed(Authenticatable $user, ?string $search = null): bool
    {
        $matches = fn (Authenticatable $candidate): bool => (string) $candidate->getAuthIdentifier() === (string) $user->getAuthIdentifier();

        if ($this->developerLoginUsers()->contains($matches)) {
            return true;
        }

        return filled($search) && $this->searchDeveloperLogins($search)->contains($matches);
    }

    /**
     * @throws AccountSwitchDenied
     */
    public function developerLogin(Authenticatable $user, ?string $search = null): void
    {
        if (! $this->developerLoginsEnabled()) {
            throw AccountSwitchDenied::developerLoginsDisabled();
        }

        if (! $this->isDeveloperLoginAllowed($user, $search)) {
            throw AccountSwitchDenied::developerLoginsDisabled();
        }

        $from = $this->guard()->user();

        AccountSwitching::dispatch($from, $user, SwitchReason::DeveloperLogin);

        session()->forget(self::SESSION_KEY);
        $this->authenticateAs($user);
        $this->record($from, $user, SwitchReason::DeveloperLogin);

        AccountSwitched::dispatch($from, $user, SwitchReason::DeveloperLogin);
    }

    // ------------------------------------------------------------------
    // Panels & redirects
    // ------------------------------------------------------------------

    /**
     * Where to land after switching: the plugin's redirectTo() if set, else the
     * current panel when the user may access it, else the first panel they may.
     */
    public function redirectUrlFor(Authenticatable $user, ?SwitchReason $reason = null): string
    {
        $redirect = $this->plugin()?->getRedirectTo();

        if ($redirect instanceof Closure) {
            $redirect = $redirect($user, $reason);
        }

        if (filled($redirect)) {
            return $redirect;
        }

        $panel = $this->accessiblePanel($user) ?? Filament::getCurrentOrDefaultPanel();

        return $panel?->getUrl() ?? url('/');
    }

    public function accessiblePanel(Authenticatable $user): ?Panel
    {
        $current = Filament::getCurrentOrDefaultPanel();

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
    // Internals
    // ------------------------------------------------------------------

    protected function guard(): StatefulGuard
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return $panel ? $panel->auth() : auth()->guard();
    }

    protected function guardName(): string
    {
        return Filament::getCurrentOrDefaultPanel()?->getAuthGuard() ?? auth()->getDefaultDriver();
    }

    protected function authenticateAs(Authenticatable $user): void
    {
        $this->guard()->login($user);

        session()->regenerate();

        session()->forget(array_unique([
            'password_hash_'.$this->guardName(),
            'password_hash_'.auth()->getDefaultDriver(),
            'password_hash_'.config('auth.defaults.guard'),
        ]));

        if ($this->plugin()?->hasFallbackToAccessiblePanel() ?? true) {
            session()->flash(self::FALLBACK_URL_SESSION_KEY, $this->accessiblePanel($user)?->getUrl() ?? url('/'));
        }
    }

    /**
     * After a switch, a 403 or 404 on the page the browser landed on is answered with a redirect to a panel
     * the new account may access. The fallback URL is flashed, so it applies to that one request only.
     */
    public function redirectWhenPageIsInaccessible(HttpExceptionInterface $exception, Request $request): ?RedirectResponse
    {
        if (! in_array($exception->getStatusCode(), [403, 404], true) || ! $request->isMethod('GET') || ! $request->hasSession()) {
            return null;
        }

        $fallbackUrl = $request->session()->pull(self::FALLBACK_URL_SESSION_KEY);

        if (! is_string($fallbackUrl) || $fallbackUrl === $request->url()) {
            return null;
        }

        // Not redirect(): while Livewire mounts a page its own redirector is bound in place of Laravel's.
        return new RedirectResponse($fallbackUrl);
    }

    // ------------------------------------------------------------------
    // Switch log
    // ------------------------------------------------------------------

    /**
     * Extra columns for every switch-log row, e.g. the current tenant: fn (SwitchReason $reason): array
     */
    public static function resolveLogContextUsing(?Closure $callback): void
    {
        static::$logContextResolver = $callback;
    }

    /**
     * Write a switch-log row for a switch that happened outside the plugin (an SSO hand-off, a tenancy
     * impersonation token, ...). Honours the same on/off switches as the plugin's own rows.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(?Authenticatable $from, Authenticatable $to, SwitchReason $reason, array $context = []): ?AccountSwitch
    {
        $enabled = $this->plugin()?->shouldLogSwitches() ?? config('packstub-account-switcher.log_switches', true);

        if (! $enabled) {
            return null;
        }

        $context = [
            ...(static::$logContextResolver ? (array) (static::$logContextResolver)($reason) : []),
            ...($this->plugin()?->evaluateSwitchLogContext($reason) ?? []),
            ...$context,
        ];

        return AccountSwitch::query()->create([
            'from_user_id' => $from?->getAuthIdentifier(),
            'to_user_id' => $to->getAuthIdentifier(),
            'reason' => $reason,
            'panel' => Filament::getCurrentOrDefaultPanel()?->getId(),
            'guard' => $this->guardName(),
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
            'context' => $context ?: null,
            'created_at' => now(),
        ]);
    }

    protected function record(?Authenticatable $from, Authenticatable $to, SwitchReason $reason): void
    {
        $this->log($from, $to, $reason);
    }
}
