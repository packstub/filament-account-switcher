<?php

namespace Packstub\AccountSwitcher\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Component;
use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\Enums\SwitchReason;

/**
 * Drop-in table or page action: sign in as the record's user.
 * Hidden unless the current user may impersonate the record.
 */
class ImpersonateAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'impersonate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => __('packstub-account-switcher::account-switcher.impersonate.label'));

        $this->icon('packstub:account:switcher-icon');

        $this->iconButton();

        $this->authorize(fn (Authenticatable $record): bool => app(AccountSwitcher::class)->canImpersonate(Filament::auth()->user(), $record));

        $this->action(function (Authenticatable $record, Component $livewire, AccountSwitcher $switcher): void {
            $switcher->impersonate($record);

            $livewire->redirect($switcher->redirectUrlFor($record, SwitchReason::Impersonation), navigate: false);
        });
    }
}
