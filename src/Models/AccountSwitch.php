<?php

namespace Packstub\AccountSwitcher\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\Enums\SwitchReason;

/**
 * Audit row written for every switch (see config log_switches).
 *
 * @property int $id
 * @property int|string|null $from_user_id
 * @property int|string $to_user_id
 * @property SwitchReason $reason
 * @property string|null $panel
 * @property string|null $guard
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $context
 * @property Carbon $created_at
 */
class AccountSwitch extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return $this->connection ?? AccountSwitcher::connectionName();
    }

    public function getTable(): string
    {
        return config('packstub-account-switcher.tables.account_switches', 'account_switches');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => SwitchReason::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(AccountSwitcher::userModel(), 'from_user_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(AccountSwitcher::userModel(), 'to_user_id');
    }
}
