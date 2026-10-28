<?php

namespace Packstub\AccountSwitcher\Filament\View;

/**
 * Render hooks inside the impersonation banner, for FilamentView::registerRenderHook().
 * They render only while impersonating, on a panel where the plugin is registered.
 */
class AccountSwitcherRenderHook
{
    /** First thing inside the banner, before the message. */
    public const BANNER_START = 'packstub-account-switcher::banner.start';

    /** Right after the "You are signed in as ..." message. */
    public const BANNER_MESSAGE_AFTER = 'packstub-account-switcher::banner.message.after';

    /** Before the Switch back button, in the banner's row of buttons. */
    public const BANNER_ACTIONS_BEFORE = 'packstub-account-switcher::banner.actions.before';

    /** After the ⋯ menu, at the end of the banner's row of buttons. */
    public const BANNER_ACTIONS_AFTER = 'packstub-account-switcher::banner.actions.after';

    /** Inside the ⋯ menu, before its items. Return dropdown list items (<x-filament::dropdown.list.item>). */
    public const BANNER_MENU_BEFORE = 'packstub-account-switcher::banner.menu.before';

    /** Inside the ⋯ menu, after its items. Return dropdown list items (<x-filament::dropdown.list.item>). */
    public const BANNER_MENU_AFTER = 'packstub-account-switcher::banner.menu.after';

    /** Last thing inside the banner. */
    public const BANNER_END = 'packstub-account-switcher::banner.end';
}
