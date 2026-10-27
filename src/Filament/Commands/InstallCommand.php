<?php

namespace Packstub\AccountSwitcher\Filament\Commands;

use Illuminate\Console\Command;

/**
 * The install command under the name the plugin had before the core moved
 * to packstub/account-switcher; it runs that package's installer.
 */
class InstallCommand extends Command
{
    protected $signature = 'packstub-account-switcher:install';

    protected $description = 'Install Filament Account Switcher: publish the config file and the migrations';

    public function handle(): int
    {
        return $this->call('account-switcher:install');
    }
}
