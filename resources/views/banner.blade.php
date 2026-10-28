@php
    use Filament\Facades\Filament;
    use Packstub\AccountSwitcher\Facades\AccountSwitcher;

    $impersonated = Filament::auth()->user();
    $impersonator = AccountSwitcher::impersonator();
    $panel = Filament::getCurrentPanel();
    $dark = $style === 'dark';
@endphp

<style>
    .fi-account-switcher-banner {
        position: fixed;
        {{ $position === 'top' ? 'top: 0;' : 'bottom: 0;' }}
        inset-inline: 0;
        z-index: 39;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        height: 3rem;
        padding-inline: 1rem;
        font-size: 0.875rem;
        background-color: {{ $dark ? '#111827' : '#f3f4f6' }};
        color: {{ $dark ? '#f9fafb' : '#111827' }};
        border-{{ $position === 'top' ? 'bottom' : 'top' }}: 1px solid {{ $dark ? '#374151' : '#d1d5db' }};
    }

    .fi-account-switcher-banner form {
        display: inline;
    }

    .fi-account-switcher-banner button {
        padding: 0.25rem 0.75rem;
        border-radius: 0.375rem;
        font-weight: 600;
        background-color: {{ $dark ? '#f9fafb' : '#111827' }};
        color: {{ $dark ? '#111827' : '#f9fafb' }};
    }

    .fi-account-switcher-banner button.fi-account-switcher-banner-secondary {
        background-color: transparent;
        color: inherit;
        border: 1px solid {{ $dark ? '#4b5563' : '#9ca3af' }};
    }

    @if ($position === 'top')
        {{-- Push the whole shell down and re-anchor Filament's sticky elements below the banner.
             v5 keeps the topbar in a sticky .fi-topbar-ctn outside .fi-layout; v4 has a sticky .fi-topbar inside it. --}}
        body.fi-body {
            padding-top: 3rem;
        }

        body .fi-topbar-ctn,
        body .fi-topbar,
        body .fi-sidebar {
            top: 3rem;
        }

        body .fi-sidebar {
            height: calc(100dvh - 3rem);
        }

        {{-- Desktop (lg+) with a topbar: Filament sticks the sidebar at top: 4rem, height: calc(100dvh - 4rem). --}}
        @media (min-width: 64rem) {
            body.fi-body-has-topbar .fi-sidebar {
                top: 7rem;
                height: calc(100dvh - 7rem);
            }
        }
    @else
        body .fi-layout {
            padding-bottom: 3rem;
        }
    @endif

    @media print {
        .fi-account-switcher-banner {
            display: none;
        }
    }
</style>

<div class="fi-account-switcher-banner" role="status">
    <span>
        {!! __('packstub-account-switcher::account-switcher.banner.message', [
            'impersonated' => '<strong>'.e(Filament::getUserName($impersonated)).'</strong>',
            'impersonator' => '<strong>'.e($impersonator ? Filament::getUserName($impersonator) : '—').'</strong>',
        ]) !!}
    </span>

    @if ($panel?->hasPlugin(\Packstub\AccountSwitcher\AccountSwitcherPlugin::ID))
        <form method="POST" action="{{ route($panel->generateRouteName('account-switcher.switch-back')) }}">
            @csrf
            <button type="submit">{{ __('packstub-account-switcher::account-switcher.banner.switch_back') }}</button>
        </form>

        @if ($sessionReplaysUrl ?? null)
            <form method="POST" action="{{ $sessionReplaysUrl }}">
                @csrf
                <button
                    type="submit"
                    class="fi-account-switcher-banner-secondary"
                    title="{{ __('packstub-account-switcher::account-switcher.banner.session_replays_title', ['impersonated' => Filament::getUserName($impersonated)]) }}"
                >{{ __('packstub-account-switcher::account-switcher.banner.session_replays') }}</button>
            </form>
        @endif
    @endif
</div>
