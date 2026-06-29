<?php

namespace App\Models;

use App\Enums\DisbursementStatus;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLedgerEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Disbursement extends Model implements Auditable
{
    use AuditableTrait, HasApprovals, HasAttachments, HasFactory, HasLedgerEntries;

    protected $fillable = [
        'disbursement_number',
        'disbursement_date',
        'account_id',
        'program_id',
        'amount',
        'payee',
        'category',
        'reference_number',
        'description',
        'status',
        'submitted_at',
        'submitted_by',
        'verified_at',
        'verified_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'posted_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'disbursement_date' => 'date',
            'amount' => 'decimal:2',
            'status' => DisbursementStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Sumber-sumber Dana Amanah pengeluaran ini (bisa lebih dari satu). */
    public function fundSources(): HasMany
    {
        return $this->hasMany(ExpenseFundSource::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Total nominal dari seluruh sumber dana. */
    public function sourcesTotal(): string
    {
        return (string) $this->fundSources()->sum('amount');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
