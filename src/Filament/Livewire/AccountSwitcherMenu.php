<?php

namespace Packstub\AccountSwitcher\Filament\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied;

/**
 * The "Switch to" dropdown rendered next to the user menu.
 */
class AccountSwitcherMenu extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * Below this many developer logins the menu shows no search box.
     */
    public const DEVELOPER_LOGIN_SEARCH_THRESHOLD = 6;

    public string $developerLoginSearch = '';

    public ?string $developerLoginGroup = null;

    public function switchAction(): Action
    {
        return Action::make('switch')
            ->label(fn (): string => __('packstub-account-switcher::account-switcher.menu.switch'))
            ->modalHeading(fn (array $arguments): string => __('packstub-account-switcher::account-switcher.menu.confirm_heading', [
                'account' => $this->accountLabel($this->resolveAccount($arguments)),
            ]))
            ->modalDescription(function (array $arguments): string {
                $account = $this->resolveAccount($arguments);

                if ($account && ! $this->requiresPassword($account)) {
                    return __('packstub-account-switcher::account-switcher.menu.confirm_description_no_password', [
                        'account' => $this->accountLabel($account),
                    ]);
                }

                return __('packstub-account-switcher::account-switcher.menu.confirm_description');
            })
            ->modalSubmitActionLabel(fn (): string => __('packstub-account-switcher::account-switcher.menu.switch'))
            ->modalWidth(Width::Medium)
            ->modalAlignment(Alignment::Center)
            ->modalFooterActionsAlignment(Alignment::Center)
            ->schema(function (array $arguments): array {
                $account = $this->resolveAccount($arguments);

                if (! $account || ! $this->requiresPassword($account)) {
                    return [];
                }

                return [
                    TextInput::make('password')
                        ->label(__('packstub-account-switcher::account-switcher.menu.password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->autocomplete('current-password'),
                ];
            })
            ->action(function (Action $action, array $arguments, array $data, AccountSwitcher $switcher): void {
                $account = $this->resolveAccount($arguments);

                if (! $account) {
                    $action->halt();
                }

                try {
                    $switcher->switchToLinkedAccount($account, $data['password'] ?? null);
                } catch (AccountSwitchDenied $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $this->redirect($switcher->redirectUrlFor($account, SwitchReason::LinkedAccount), navigate: false);
            });
    }

    /**
     * @return Collection<int, Model&Authenticatable>
     */
    public function getAccounts(): Collection
    {
        return new Collection(app(AccountSwitcher::class)->switchableAccounts(Filament::auth()->user()));
    }

    /**
     * Every developer login the menu may offer, when the plugin lists them there.
     *
     * @return Collection<int, Model&Authenticatable>
     */
    public function getAllDeveloperLogins(): Collection
    {
        $switcher = app(AccountSwitcher::class);
        $plugin = $switcher->plugin();

        if (! $plugin?->hasDeveloperLoginsInMenu() || $switcher->isImpersonating()) {
            return new Collection;
        }

        return new Collection($switcher->developerLoginUsers()->all());
    }

    /**
     * The developer logins after the search box and the selected tag. With a search resolver on the
     * plugin, a non-empty search looks up any account instead of filtering the listed ones.
     *
     * @return Collection<int, Model&Authenticatable>
     */
    public function getDeveloperLogins(): Collection
    {
        $switcher = app(AccountSwitcher::class);
        $search = trim($this->developerLoginSearch);

        if ($this->getAllDeveloperLogins()->isEmpty() && ! ($search !== '' && $switcher->plugin()?->hasDeveloperLoginSearch())) {
            return new Collection;
        }

        $users = $search === ''
            ? $this->getAllDeveloperLogins()
            : new Collection($switcher->searchDeveloperLogins($search)->all());

        return $users
            ->filter(fn (Authenticatable $user): bool => $this->developerLoginGroup === null || $this->developerLoginGroup($user) === $this->developerLoginGroup)
            ->values();
    }

    /**
     * The tags offered as filters, in the order they first appear.
     *
     * @return array<int, string>
     */
    public function getDeveloperLoginGroups(): array
    {
        return $this->getAllDeveloperLogins()
            ->map(fn (Authenticatable $user): ?string => $this->developerLoginGroup($user))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function hasDeveloperLoginSearch(): bool
    {
        $switcher = app(AccountSwitcher::class);

        if (! $switcher->developerLoginsEnabled() || ! $switcher->plugin()?->hasDeveloperLoginsInMenu() || $switcher->isImpersonating()) {
            return false;
        }

        return $switcher->plugin()->hasDeveloperLoginSearch()
            || $this->getAllDeveloperLogins()->count() >= self::DEVELOPER_LOGIN_SEARCH_THRESHOLD;
    }

    public function filterDeveloperLogins(?string $group): void
    {
        $this->developerLoginGroup = $group === $this->developerLoginGroup ? null : $group;
    }

    public function developerLoginGroup(Authenticatable $user): ?string
    {
        return app(AccountSwitcher::class)->plugin()?->developerLoginGroup($user);
    }

    public function developerLogin(int|string $userId): void
    {
        $switcher = app(AccountSwitcher::class);

        $matches = fn (Authenticatable $candidate): bool => (string) $candidate->getAuthIdentifier() === (string) $userId;
        $search = trim($this->developerLoginSearch);

        $user = $switcher->developerLoginUsers()->first($matches)
            ?? ($search !== '' ? $switcher->searchDeveloperLogins($search)->first($matches) : null);

        if (! $user) {
            abort(404);
        }

        $switcher->developerLogin($user, $search);

        $this->redirect($switcher->plugin()?->getDeveloperLoginRedirectTo() !== null
            ? $switcher->developerLoginRedirectUrl($user)
            : url()->previous(), navigate: false);
    }

    public function developerLoginLabel(Authenticatable $user): string
    {
        return app(AccountSwitcher::class)->plugin()?->developerLoginLabel($user) ?? Filament::getUserName($user);
    }

    public function isCurrentUser(Authenticatable $user): bool
    {
        return (string) Filament::auth()->user()?->getAuthIdentifier() === (string) $user->getAuthIdentifier();
    }

    public function getManageUrl(): ?string
    {
        return AccountSwitcherPlugin::get()->getLinkedAccountsUrl();
    }

    public function accountLabel(?Model $account): string
    {
        if (! $account) {
            return '';
        }

        return $account->pivot?->label ?: Filament::getUserName($account);
    }

    public function render(): View
    {
        return view('packstub-account-switcher::menu', [
            'accounts' => $this->getAccounts(),
            'developerLogins' => $this->getDeveloperLogins(),
            'hasDeveloperLogins' => $this->getAllDeveloperLogins()->isNotEmpty(),
            'developerLoginGroups' => $this->getDeveloperLoginGroups(),
            'hasDeveloperLoginSearch' => $this->hasDeveloperLoginSearch(),
            'manageUrl' => $this->getManageUrl(),
            'plugin' => AccountSwitcherPlugin::get(),
        ]);
    }

    protected function requiresPassword(Model $account): bool
    {
        $user = Filament::auth()->user();

        return ! $user || app(AccountSwitcher::class)->requiresPassword($user, $account);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return (Model&Authenticatable)|null
     */
    protected function resolveAccount(array $arguments): ?Model
    {
        $key = $arguments['account'] ?? null;

        if ($key === null) {
            return null;
        }

        return $this->getAccounts()->first(
            fn (Model $account): bool => (string) $account->getKey() === (string) $key,
        );
    }
}
