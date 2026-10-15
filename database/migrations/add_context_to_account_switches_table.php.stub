<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Packstub\AccountSwitcher\AccountSwitcher;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('packstub-account-switcher.tables.account_switches', 'account_switches');
        $schema = Schema::connection(AccountSwitcher::connectionName());

        if (! $schema->hasTable($table) || $schema->hasColumn($table, 'context')) {
            return;
        }

        $schema->table($table, function (Blueprint $table): void {
            $table->json('context')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        $table = config('packstub-account-switcher.tables.account_switches', 'account_switches');
        $schema = Schema::connection(AccountSwitcher::connectionName());

        if ($schema->hasTable($table) && $schema->hasColumn($table, 'context')) {
            $schema->table($table, function (Blueprint $table): void {
                $table->dropColumn('context');
            });
        }
    }
};
