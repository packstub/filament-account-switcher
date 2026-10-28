@php
    use Filament\Facades\Filament;
    use Filament\Support\Facades\FilamentView;
    use Packstub\AccountSwitcher\Facades\AccountSwitcher;
    use Packstub\AccountSwitcher\Filament\View\AccountSwitcherRenderHook;

    $impersonated = Filament::auth()->user();
    $impersonator = AccountSwitcher::impersonator();
    $panel = Filament::getCurrentPanel();
    $dark = $style === 'dark';
    $menuItems ??= [];

    // The menu's own hooks render first: a menu with nothing but hooked items still opens.
    $menuBefore = (string) FilamentView::renderHook(AccountSwitcherRenderHook::BANNER_MENU_BEFORE);
    $menuAfter = (string) FilamentView::renderHook(AccountSwitcherRenderHook::BANNER_MENU_AFTER);
    $hasMenu = $menuItems !== [] || trim($menuBefore) !== '' || trim($menuAfter) !== '';
@endphp

<style>
    :root {
        --fi-account-switcher-banner-height: 3rem;
    }

    .fi-account-switcher-banner {
        position: fixed;
        {{ $position === 'top' ? 'top: 0;' : 'bottom: 0;' }}
        inset-inline: 0;
        z-index: 39;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        height: var(--fi-account-switcher-banner-height);
        padding-inline: 1rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        background-color: {{ $dark ? '#111827' : '#f3f4f6' }};
        color: {{ $dark ? '#f9fafb' : '#111827' }};
        border-{{ $position === 'top' ? 'bottom' : 'top' }}: 1px solid {{ $dark ? '#374151' : '#d1d5db' }};
    }

    .fi-account-switcher-banner-message {
        min-width: 0;
    }

    .fi-account-switcher-banner-actions {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-account-switcher-banner-actions > form {
        display: inline;
    }

    .fi-account-switcher-banner-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 2rem;
        padding-inline: 0.75rem;
        border-radius: 0.375rem;
        font-weight: 600;
        white-space: nowrap;
        background-color: {{ $dark ? '#f9fafb' : '#111827' }};
        color: {{ $dark ? '#111827' : '#f9fafb' }};
    }

    .fi-account-switcher-banner-menu-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        color: inherit;
        border: 1px solid {{ $dark ? '#4b5563' : '#9ca3af' }};
    }

    .fi-account-switcher-banner-menu-trigger:hover,
    .fi-account-switcher-banner-menu-trigger:focus-visible {
        background-color: {{ $dark ? '#1f2937' : '#e5e7eb' }};
    }

    .fi-account-switcher-banner-menu-trigger svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    {{-- Phones: the message keeps to two lines beside the buttons, in a slightly taller bar. --}}
    @media (max-width: 40rem) {
        :root {
            --fi-account-switcher-banner-height: 3.5rem;
        }

        .fi-account-switcher-banner {
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.8125rem;
            line-height: 1.125rem;
        }

        .fi-account-switcher-banner-message {
            flex: 1 1 auto;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    }

    @if ($position === 'top')
        {{-- Push the whole shell down and re-anchor Filament's sticky elements below the banner.
             v5 keeps the topbar in a sticky .fi-topbar-ctn outside .fi-layout; v4 has a sticky .fi-topbar inside it. --}}
        body.fi-body {
            padding-top: var(--fi-account-switcher-banner-height);
        }

        body .fi-topbar-ctn,
        body .fi-topbar,
        body .fi-sidebar {
            top: var(--fi-account-switcher-banner-height);
        }

        body .fi-sidebar {
            height: calc(100dvh - var(--fi-account-switcher-banner-height));
        }

        {{-- Desktop (lg+) with a topbar: Filament sticks the sidebar at top: 4rem, height: calc(100dvh - 4rem). --}}
        @media (min-width: 64rem) {
            body.fi-body-has-topbar .fi-sidebar {
                top: calc(var(--fi-account-switcher-banner-height) + 4rem);
                height: calc(100dvh - var(--fi-account-switcher-banner-height) - 4rem);
            }
        }
    @else
        body .fi-layout {
            padding-bottom: var(--fi-account-switcher-banner-height);
        }
    @endif

    @media print {
        .fi-account-switcher-banner {
            display: none;
        }
    }
</style>

<div class="fi-account-switcher-banner" role="status">
    {{ FilamentView::renderHook(AccountSwitcherRenderHook::BANNER_START) }}

    <span class="fi-account-switcher-banner-message">
        {!! __('packstub-account-switcher::account-switcher.banner.message', [
            'impersonated' => '<strong>'.e(Filament::getUserName($impersonated)).'</strong>',
            'impersonator' => '<strong>'.e($impersonator ? Filament::getUserName($impersonator) : '—').'</strong>',
        ]) !!}
    </span>

    {{ FilamentView::renderHook(AccountSwitcherRenderHook::BANNER_MESSAGE_AFTER) }}

    <div class="fi-account-switcher-banner-actions">
        {{ FilamentView::renderHook(AccountSwitcherRenderHook::BANNER_ACTIONS_BEFORE) }}

        @if ($panel?->hasPlugin(\Packstub\AccountSwitcher\AccountSwitcherPlugin::ID))
            <form method="POST" action="{{ route($panel->generateRouteName('account-switcher.switch-back')) }}">
                @csrf
                <button type="submit" class="fi-account-switcher-banner-button">{{ __('packstub-account-switcher::account-switcher.banner.switch_back') }}</button>
            </form>
        @endif

        @if ($hasMenu)
            <x-filament::dropdown
                :placement="$position === 'top' ? 'bottom-end' : 'top-end'"
                class="fi-account-switcher-banner-menu"
            >
                <x-slot name="trigger">
                    <button
                        type="button"
                        class="fi-account-switcher-banner-menu-trigger"
                        aria-label="{{ __('packstub-account-switcher::account-switcher.banner.more') }}"
                        title="{{ __('packstub-account-switcher::account-switcher.banner.more') }}"
                    >
                        {{ \Filament\Support\generate_icon_html('heroicon-m-ellipsis-horizontal') }}
                    </button>
                </x-slot>

                <x-filament::dropdown.list>
                    {!! $menuBefore !!}

                    @foreach ($menuItems as $item)
                        {{ $item->grouped() }}
                    @endforeach

                    {!! $menuAfter !!}
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif

        {{ FilamentView::renderHook(AccountSwitcherRenderHook::BANNER_ACTIONS_AFTER) }}
    </div>

    {{ FilamentView::renderHook(AccountSwitcherRenderHook::BANNER_END) }}
</div>
