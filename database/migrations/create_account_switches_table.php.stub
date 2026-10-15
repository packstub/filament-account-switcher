<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Packstub\AccountSwitcher\AccountSwitcher;

return new class extends Migration
{
    public function up(): void
    {
        $userModel = AccountSwitcher::userModel();
        $usersTable = (new $userModel)->getTable();

        Schema::connection(AccountSwitcher::connectionName())->create(config('packstub-account-switcher.tables.account_switches', 'account_switches'), function (Blueprint $table) use ($userModel, $usersTable): void {
            $table->id();
            $table->foreignIdFor($userModel, 'from_user_id')->nullable()->constrained($usersTable)->nullOnDelete();
            $table->foreignIdFor($userModel, 'to_user_id')->constrained($usersTable)->cascadeOnDelete();
            $table->string('reason', 32)->index();
            $table->string('panel', 64)->nullable();
            $table->string('guard', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::connection(AccountSwitcher::connectionName())->dropIfExists(config('packstub-account-switcher.tables.account_switches', 'account_switches'));
    }
};
