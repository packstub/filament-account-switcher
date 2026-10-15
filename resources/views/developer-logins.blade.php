@php
    use Filament\Facades\Filament;

    $panel = Filament::getCurrentPanel();
@endphp

<div class="fi-account-switcher-developer-logins" style="margin-top: 1.5rem; display: grid; gap: 0.5rem;">
    <p style="font-size: 0.75rem; text-align: center; opacity: .7;">
        {{ __('packstub-account-switcher::account-switcher.developer_logins.heading', ['environment' => app()->environment()]) }}
    </p>

    @foreach ($users->groupBy(fn ($user) => (string) $plugin->developerLoginGroup($user)) as $group => $groupUsers)
        @if ($group !== '')
            <p style="font-size: 0.75rem; font-weight: 600; opacity: .7; margin-top: 0.5rem;">{{ $group }}</p>
        @endif

        @foreach ($groupUsers as $user)
            <form method="POST" action="{{ route($panel->generateRouteName('account-switcher.developer-login')) }}">
                @csrf
                <input type="hidden" name="user" value="{{ $user->getAuthIdentifier() }}">

                <x-filament::button type="submit" color="gray" outlined size="sm" icon="heroicon-o-arrow-right-end-on-rectangle" class="w-full" style="width: 100%; justify-content: center;">
                    {{ $plugin->developerLoginLabel($user) }}
                    @if ($user->email ?? null)
                        <span style="opacity: .7;">({{ $user->email }})</span>
                    @endif
                </x-filament::button>
            </form>
        @endforeach
    @endforeach
</div>
