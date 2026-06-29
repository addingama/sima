<?php

namespace App\Support;

use App\Models\BankFee;
use App\Models\Disbursement;
use App\Models\OperationalLiability;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use Illuminate\Database\Eloquent\Relations\Relation;

/** Pemetaan morph alias SIMA — hindari FQCN di kolom polymorphic baru. */
final class SimaMorphMap
{
    public static function register(): void
    {
        Relation::morphMap([
            'receipt' => Receipt::class,
            'disbursement' => Disbursement::class,
            'bank_fee' => BankFee::class,
            'operational_liability' => OperationalLiability::class,
            'receipt_allocation' => ReceiptAllocation::class,
        ]);
    }
}
