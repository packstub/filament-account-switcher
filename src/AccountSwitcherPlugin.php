<?php

namespace Packstub\AccountSwitcher;

use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Packstub\AccountSwitcher\Filament\Pages\LinkedAccounts;
use Packstub\AccountSwitcher\Http\Controllers\DeveloperLoginController;
use Packstub\AccountSwitcher\Http\Controllers\SwitchBackController;

class AccountSwitcherPlugin implements Plugin
{
    public const ID = 'packstub-account-switcher';

    protected bool|Closure $impersonation = true;

    protected bool|Closure $linkedAccounts = true;

    protected bool|Closure $developerLogins = false;

    /** @var Closure | array<int, string> | null */
    protected Closure|array|null $developerLoginUsers = null;

    protected ?Closure $canImpersonateUsing = null;

    protected ?Closure $canSwitchUsing = null;

    protected ?Closure $createSubAccountUsing = null;

    protected string|Closure|null $redirectTo = null;

    protected ?bool $logSwitches = null;

    protected string $switcherRenderHook = PanelsRenderHook::USER_MENU_BEFORE;

    protected ?string $bannerPosition = null;

    protected ?string $bannerStyle = null;

    /** @var class-string<LinkedAccounts> */
    protected string $linkedAccountsPage = LinkedAccounts::class;

    protected bool $linkedAccountsUserMenuItem = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(static::ID);

        return $plugin;
    }

    public function getId(): string
    {
        return static::ID;
    }

    // ------------------------------------------------------------------
    // Configuration
    // ------------------------------------------------------------------

    /**
     * Let authorized users sign in as any other user via ImpersonateAction.
     */
    public function impersonation(bool|Closure $enabled = true): static
    {
        $this->impersonation = $enabled;

        return $this;
    }

    /**
     * The "Switch to" menu for accounts a user has explicitly linked, plus the
     * Linked accounts page. Requires the HasLinkedAccounts trait on the user.
     */
    public function linkedAccounts(bool|Closure $enabled = true, bool $userMenuItem = true): static
    {
        $this->linkedAccounts = $enabled;
        $this->linkedAccountsUserMenuItem = $userMenuItem;

        return $this;
    }

    /**
     * One-click sign-in buttons on the login page. Only ever rendered in the
     * environments listed in config (local by default).
     *
     * @param  Closure | array<int, string> | bool | null  $users  A closure returning the users, an array of e-mails, or true for the first ten users.
     */
    public function developerLogins(Closure|array|bool|null $users = true): static
    {
        if (is_bool($users)) {
            $this->developerLogins = $users;
            $this->developerLoginUsers = null;

            return $this;
        }

        $this->developerLogins = $users !== null;
        $this->developerLoginUsers = $users;

        return $this;
    }

    /**
     * Extra gate for impersonation, evaluated after canImpersonate() /
     * canBeImpersonated() on the models: fn (Authenticatable $by, Authenticatable $target): bool
     */
    public function canImpersonateUsing(?Closure $callback): static
    {
        $this->canImpersonateUsing = $callback;

        return $this;
    }

    /**
     * Extra gate for linked-account switching: fn (Authenticatable $from, Authenticatable $target): bool
     */
    public function canSwitchUsing(?Closure $callback): static
    {
        $this->canSwitchUsing = $callback;

        return $this;
    }

    /**
     * How the Linked accounts page creates a sub-account:
     * fn (array $data, Authenticatable $owner): Model. $data has name, email and a plain password.
     */
    public function createSubAccountUsing(?Closure $callback): static
    {
        $this->createSubAccountUsing = $callback;

        return $this;
    }

    /**
     * Where to land after a switch. Defaults to the first panel the target
     * account may access (the current one when possible).
     */
    public function redirectTo(string|Closure|null $url): static
    {
        $this->redirectTo = $url;

        return $this;
    }

    public function logSwitches(bool $enabled = true): static
    {
        $this->logSwitches = $enabled;

        return $this;
    }

    /**
     * Where the "Switch to" menu renders; a PanelsRenderHook constant.
     */
    public function switcherRenderHook(string $hook): static
    {
        $this->switcherRenderHook = $hook;

        return $this;
    }

    public function banner(?string $position = null, ?string $style = null): static
    {
        $this->bannerPosition = $position;
        $this->bannerStyle = $style;

        return $this;
    }

    /**
     * @param  class-string<LinkedAccounts>  $page
     */
    public function linkedAccountsPage(string $page): static
    {
        $this->linkedAccountsPage = $page;

        return $this;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function hasImpersonation(): bool
    {
        return (bool) value($this->impersonation);
    }

    public function hasLinkedAccounts(): bool
    {
        return (bool) value($this->linkedAccounts);
    }

    public function hasDeveloperLogins(): bool
    {
        return (bool) value($this->developerLogins);
    }

    public function evaluateCanImpersonate(Authenticatable $by, Authenticatable $target): bool
    {
        return $this->canImpersonateUsing === null || (bool) ($this->canImpersonateUsing)($by, $target);
    }

    public function evaluateCanSwitch(Authenticatable $from, Authenticatable $target): bool
    {
        return $this->canSwitchUsing === null || (bool) ($this->canSwitchUsing)($from, $target);
    }

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function createSubAccount(array $data, Authenticatable $owner): Model
    {
        if ($this->createSubAccountUsing) {
            return ($this->createSubAccountUsing)($data, $owner);
        }

        $model = AccountSwitcher::userModel();

        return $model::query()->create($data);
    }

    public function getRedirectTo(): string|Closure|null
    {
        return $this->redirectTo;
    }

    public function shouldLogSwitches(): ?bool
    {
        return $this->logSwitches;
    }

    public function getBannerPosition(): string
    {
        return $this->bannerPosition ?? config('packstub-account-switcher.banner.position', 'bottom');
    }

    public function getBannerStyle(): string
    {
        return $this->bannerStyle ?? config('packstub-account-switcher.banner.style', 'dark');
    }

    /**
     * @return class-string<LinkedAccounts>
     */
    public function getLinkedAccountsPage(): string
    {
        return $this->linkedAccountsPage;
    }

    /**
     * URL of the linked accounts page, or null where it cannot be reached. In a
     * tenant-aware panel the page lives under a tenant, so there is no URL on
     * tenant-less pages such as tenant registration.
     */
    public function getLinkedAccountsUrl(): ?string
    {
        if (! $this->hasLinkedAccounts()) {
            return null;
        }

        if (Filament::hasTenancy() && ! Filament::getTenant()) {
            return null;
        }

        $page = $this->linkedAccountsPage;

        return $page::canAccess() ? $page::getUrl() : null;
    }

    /**
     * @return Collection<int, Authenticatable>
     */
    public function resolveDeveloperLoginUsers(): Collection
    {
        $users = $this->developerLoginUsers;
        $model = AccountSwitcher::userModel();

        if ($users instanceof Closure) {
            return collect($users());
        }

        if (is_array($users)) {
            return $model::query()->whereIn('email', $users)->orderBy('id')->get();
        }

        return $model::query()->orderBy('id')->limit(10)->get();
    }

    // ------------------------------------------------------------------
    // Panel integration
    // ------------------------------------------------------------------

    public function register(Panel $panel): void
    {
        if ($this->hasLinkedAccounts()) {
            $panel->pages([$this->linkedAccountsPage]);

            if ($this->linkedAccountsUserMenuItem) {
                $panel->userMenuItems([
                    'linked-accounts' => Action::make('linked-accounts')
                        ->label(fn (): string => __('packstub-account-switcher::account-switcher.linked_accounts.title'))
                        ->icon('heroicon-o-users')
                        ->url(fn (): ?string => $this->getLinkedAccountsUrl())
                        ->visible(fn (): bool => $this->getLinkedAccountsUrl() !== null),
                ]);
            }
        }

        if ($this->hasImpersonation()) {
            $panel->authenticatedRoutes(function (): void {
                Route::post('account-switcher/switch-back', SwitchBackController::class)
                    ->name('account-switcher.switch-back');
            });
        }

        if ($this->hasDeveloperLogins()) {
            $panel->routes(function (): void {
                Route::post('account-switcher/developer-login', DeveloperLoginController::class)
                    ->middleware('throttle:10,1')
                    ->name('account-switcher.developer-login');
            });
        }
    }

    public function boot(Panel $panel): void
    {
        if ($this->hasLinkedAccounts()) {
            FilamentView::registerRenderHook(
                $this->switcherRenderHook,
                fn (): string => $this->renderIfCurrentPanel($panel, fn (): string => Blade::render('@livewire(\'packstub-account-switcher-menu\')')),
            );
        }

        if ($this->hasImpersonation()) {
            FilamentView::registerRenderHook(
                $this->getBannerPosition() === 'top' ? PanelsRenderHook::BODY_START : PanelsRenderHook::BODY_END,
                fn (): string => $this->renderIfCurrentPanel($panel, function (): string {
                    if (! app(AccountSwitcher::class)->isImpersonating()) {
                        return '';
                    }

                    return view('packstub-account-switcher::banner', [
                        'position' => $this->getBannerPosition(),
                        'style' => $this->getBannerStyle(),
                    ])->render();
                }),
            );
        }

        if ($this->hasDeveloperLogins()) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => $this->renderIfCurrentPanel($panel, function (): string {
                    $users = app(AccountSwitcher::class)->developerLoginUsers();

                    if ($users->isEmpty()) {
                        return '';
                    }

                    return view('packstub-account-switcher::developer-logins', ['users' => $users])->render();
                }),
            );
        }
    }

    /**
     * Render hooks are global; each panel's plugin instance only renders for
     * the panel it was registered on.
     */
    protected function renderIfCurrentPanel(Panel $panel, Closure $render): string
    {
        if (Filament::getCurrentPanel()?->getId() !== $panel->getId()) {
            return '';
        }

        return $render();
    }
}
