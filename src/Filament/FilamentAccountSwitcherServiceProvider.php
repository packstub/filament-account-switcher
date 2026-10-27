<?php

namespace Packstub\AccountSwitcher\Filament;

use BladeUI\Icons\Factory as IconFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Livewire;
use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\Contracts\SwitchContext;
use Packstub\AccountSwitcher\Filament\Commands\InstallCommand;
use Packstub\AccountSwitcher\Filament\Livewire\AccountSwitcherMenu;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * The panel side of packstub/account-switcher: the context that answers from
 * the current panel's plugin, the views, the menu component and the icon.
 */
class FilamentAccountSwitcherServiceProvider extends PackageServiceProvider
{
    public static string $name = 'packstub-account-switcher';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews(static::$name)
            ->hasTranslations()
            ->hasCommand(InstallCommand::class);
    }

    /** This provider sits one level below src/; package-tools resolves resources/ relative to what this returns. */
    protected function getPackageBaseDir(): string
    {
        return dirname(__DIR__);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PanelContext::class);

        // Whatever the provider order, the panel context wins: the core binds its own only when nothing is bound.
        $this->app->bind(SwitchContext::class, fn ($app) => $app->make(PanelContext::class));

        $this->callAfterResolving(IconFactory::class, function (IconFactory $factory): void {
            $factory->add('packstub-account-switcher-icons', [
                'path' => __DIR__.'/../../resources/svg',
                'prefix' => 'packstub:account:switcher',
            ]);
        });
    }

    public function packageBooted(): void
    {
        Livewire::component('packstub-account-switcher-menu', AccountSwitcherMenu::class);

        // Panel-only helpers on the facade, as they were before the core moved to its own package.
        AccountSwitcher::macro('plugin', fn () => app(PanelContext::class)->plugin());
        AccountSwitcher::macro('accessiblePanel', fn (Authenticatable $user) => app(PanelContext::class)->accessiblePanel($user));
    }
}
