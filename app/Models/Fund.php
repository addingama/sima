<?php

namespace App\Models;

use App\Enums\LedgerAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Fund extends Model implements Auditable
{
    use AuditableTrait, HasFactory, SoftDeletes;

    /** Kunci dana sistem. */
    public const KEY_SUSPENSE = 'suspense';

    public const KEY_BANK_ADMIN = 'bank_admin';

    public const KEY_OPERATIONAL = 'operational';

    public const KEY_OPENING_EQUITY = 'opening_equity';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'is_system',
        'system_key',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'ledger_account_id')
            ->where('ledger_account_type', LedgerAccountType::FUND->value);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    /** Sumber dana pengeluaran yang memakai dana ini (pengeluaran bisa multi-sumber). */
    public function expenseFundSources(): HasMany
    {
        return $this->hasMany(ExpenseFundSource::class);
    }

    /** @deprecated Gunakan LedgerService::balanceForFund() */
    public function balance(): string
    {
        $debit = (string) ($this->ledgerEntries()->sum('debit') ?? '0');
        $credit = (string) ($this->ledgerEntries()->sum('credit') ?? '0');

        return bcsub($credit, $debit, 2);
    }

    public static function findBySystemKey(string $key): ?self
    {
        return static::where('system_key', $key)->first();
    }
}
