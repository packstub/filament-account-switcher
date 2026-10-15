@php
    use Filament\Facades\Filament;

    $panel = Filament::getCurrentPanel();
@endphp

<div class="fi-account-switcher-developer-logins" style="margin-top: 1.5rem; display: grid; gap: 0.5rem;">
    <p style="font-size: 0.75rem; text-align: center; opacity: .7;">
        {{ __('packstub-account-switcher::account-switcher.developer_logins.heading', ['environment' => app()->environment()]) }}
    </p>

    @foreach ($users as $user)
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
</div>
