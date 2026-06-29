<?php

namespace App\Enums;

/** Jenis transaksi sumber entri ledger (Amanah Ledger). */
enum TransactionType: string
{
    case OPENING = 'opening';
    case RECEIPT = 'receipt';
    case EXPENSE = 'expense';
    case BANK_FEE = 'bank_fee';
    case REVERSAL = 'reversal';
    case ADJUSTMENT = 'adjustment';
    case OPERATIONAL_LIABILITY = 'operational_liability';
    case TRANSFER = 'transfer';
    case RECONCILIATION = 'reconciliation';
}
