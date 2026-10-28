<?php

namespace Packstub\AccountSwitcher\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\QueryBuilder\QueryBuilderServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Packstub\AccountSwitcher\AccountSwitcherServiceProvider;
use Packstub\AccountSwitcher\Filament\FilamentAccountSwitcherServiceProvider;
use Packstub\AccountSwitcher\Tests\Fixtures\AdminPanelProvider;
use Packstub\AccountSwitcher\Tests\Fixtures\LitePanelProvider;
use Packstub\AccountSwitcher\Tests\Fixtures\TenantPanelProvider;
use Packstub\AccountSwitcher\Tests\Fixtures\User;
use Packstub\SessionReplay\Filament\FilamentSessionReplayServiceProvider;
use Packstub\SessionReplay\SessionReplayServiceProvider;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            QueryBuilderServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            // Real apps discover filament/* before livewire; Filament binds its DataStore
            // override first and Livewire pins the shared instance. Keep that order.
            LivewireServiceProvider::class,
            AccountSwitcherServiceProvider::class,
            FilamentAccountSwitcherServiceProvider::class,
            // Filament Session Replay: the admin and the tenant panel list its recordings (nothing is recorded in tests).
            SessionReplayServiceProvider::class,
            FilamentSessionReplayServiceProvider::class,
            AdminPanelProvider::class,
            TenantPanelProvider::class,
            LitePanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'base64:2fl+Ktv6fZ7c7ZQfF1Zt6Q0Wd9jz5bJ6rKq8nX0m3Yk=');
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
    }

    protected function migrate(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('can_access_panel')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table): void {
            $table->foreignId('team_id');
            $table->foreignId('user_id');
        });

        foreach (['create_linked_accounts_table', 'create_account_switches_table', 'add_context_to_account_switches_table'] as $migration) {
            (include __DIR__."/../vendor/packstub/account-switcher/database/migrations/{$migration}.php.stub")->up();
        }
    }
}
