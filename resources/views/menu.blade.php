<div class="fi-account-switcher-menu">
    @if ($accounts->isNotEmpty() || $hasDeveloperLogins || $hasDeveloperLoginSearch)
        <x-filament::dropdown placement="bottom-end" width="sm">
            <x-slot name="trigger">
                {{-- Just the icon on a phone, where the label wraps onto two lines in a full topbar. --}}
                <x-filament::button color="gray" size="sm" icon="heroicon-o-user" outlined labeled-from="sm">
                    {{ __('packstub-account-switcher::account-switcher.menu.switch_to') }}
                </x-filament::button>
            </x-slot>

            @if ($accounts->isNotEmpty())
                <x-filament::dropdown.list>
                    @foreach ($accounts as $account)
                        <x-filament::dropdown.list.item
                            wire:click="mountAction('switch', {{ \Illuminate\Support\Js::from(['account' => $account->getKey()]) }})"
                            :icon="$this->accountLabel($account) !== \Filament\Facades\Filament::getUserName($account) ? 'heroicon-o-tag' : 'heroicon-o-user'"
                        >
                            <span class="fi-account-switcher-menu-label">{{ $this->accountLabel($account) }}</span>
                            @if ($account->email ?? null)
                                <span class="fi-account-switcher-menu-email fi-color-gray" style="display:block;font-size:0.75rem;opacity:.7">{{ $account->email }}</span>
                            @endif
                        </x-filament::dropdown.list.item>
                    @endforeach

                    @if ($manageUrl)
                        <x-filament::dropdown.list.item :href="$manageUrl" tag="a" icon="heroicon-o-cog-6-tooth">
                            {{ __('packstub-account-switcher::account-switcher.menu.manage') }}
                        </x-filament::dropdown.list.item>
                    @endif
                </x-filament::dropdown.list>
            @endif

            @if ($hasDeveloperLogins || $hasDeveloperLoginSearch)
                @php
                    $searchPlaceholder = __('packstub-account-switcher::account-switcher.developer_logins.' . ($plugin->hasDeveloperLoginSearch() ? 'search_any' : 'search'));
                @endphp

                <div
                    class="fi-account-switcher-developer-logins-ctn"
                    x-data="{ searchOpen: @js(filled($this->developerLoginSearch)) }"
                >
                    <div
                        class="fi-account-switcher-developer-logins-header"
                        style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;"
                    >
                        <x-filament::dropdown.header icon="heroicon-o-code-bracket" color="gray" style="flex: 1;">
                            {{ __('packstub-account-switcher::account-switcher.developer_logins.menu_heading') }}
                        </x-filament::dropdown.header>

                        @if ($hasDeveloperLoginSearch)
                            {{-- The search box stays hidden until asked for, so a short list is not pushed down by it. --}}
                            <x-filament::icon-button
                                icon="heroicon-m-magnifying-glass"
                                color="gray"
                                size="sm"
                                class="fi-account-switcher-developer-logins-search-toggle"
                                style="margin-inline-end: 0.5rem;"
                                :label="$searchPlaceholder"
                                x-on:click="searchOpen = ! searchOpen; if (searchOpen) $nextTick(() => $refs.developerLoginSearch.focus())"
                            />
                        @endif
                    </div>

                    @if ($hasDeveloperLoginSearch || count($developerLoginGroups) > 1)
                        <div class="fi-account-switcher-developer-logins-filters" style="display: grid; gap: 0.5rem; padding: 0.5rem 0.75rem;">
                            @if ($hasDeveloperLoginSearch)
                                <div x-show="searchOpen" x-cloak>
                                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" prefix-icon-color="gray">
                                        <x-filament::input
                                            type="search"
                                            x-ref="developerLoginSearch"
                                            wire:model.live.debounce.300ms="developerLoginSearch"
                                            :placeholder="$searchPlaceholder"
                                            autocomplete="off"
                                            x-on:keydown.escape.prevent="searchOpen = false; $wire.set('developerLoginSearch', '')"
                                        />
                                    </x-filament::input.wrapper>
                                </div>
                            @endif

                            @if (count($developerLoginGroups) > 1)
                                <div class="fi-account-switcher-developer-logins-groups" style="display: flex; flex-wrap: wrap; gap: 0.375rem;">
                                    <x-filament::badge
                                        tag="button"
                                        type="button"
                                        size="sm"
                                        wire:click="filterDeveloperLogins(null)"
                                        :color="$developerLoginGroup === null ? 'primary' : 'gray'"
                                    >
                                        {{ __('packstub-account-switcher::account-switcher.developer_logins.all') }}
                                    </x-filament::badge>

                                    @foreach ($developerLoginGroups as $group)
                                        <x-filament::badge
                                            tag="button"
                                            type="button"
                                            size="sm"
                                            wire:click="filterDeveloperLogins({{ \Illuminate\Support\Js::from($group) }})"
                                            :color="$developerLoginGroup === $group ? 'primary' : 'gray'"
                                        >
                                            {{ $group }}
                                        </x-filament::badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="fi-account-switcher-developer-logins" style="max-height: 20rem; overflow-y: auto;">
                        @if ($developerLogins->isEmpty())
                            <p style="padding: 0.75rem; font-size: 0.875rem; opacity: .7;">
                                {{ __('packstub-account-switcher::account-switcher.developer_logins.no_results') }}
                            </p>
                        @else
                            <x-filament::dropdown.list>
                                @foreach ($developerLogins as $user)
                                    <x-filament::dropdown.list.item
                                        wire:click="developerLogin({{ \Illuminate\Support\Js::from($user->getAuthIdentifier()) }})"
                                        :color="$this->isCurrentUser($user) ? 'primary' : 'gray'"
                                        icon="heroicon-o-arrow-right-end-on-rectangle"
                                    >
                                        <span class="fi-account-switcher-menu-label">{{ $this->developerLoginLabel($user) }}</span>
                                        @if (($user->email ?? null) || $this->developerLoginGroup($user))
                                            <span class="fi-account-switcher-menu-email fi-color-gray" style="display:block;font-size:0.75rem;opacity:.7">
                                                {{ implode(' · ', array_filter([$this->developerLoginGroup($user), $user->email ?? null])) }}
                                            </span>
                                        @endif
                                    </x-filament::dropdown.list.item>
                                @endforeach
                            </x-filament::dropdown.list>
                        @endif
                    </div>
                </div>
            @endif
        </x-filament::dropdown>
    @endif

    <x-filament-actions::modals />
</div>
