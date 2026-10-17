<?php

namespace App\Domains\PettyCash\Listeners;

use App\Domains\Expense\Events\ExpenseApproved;
use App\Domains\PettyCash\Services\PettyCashFundingService;

class SyncPettyCashOnExpenseApproved
{
    public function __construct(private readonly PettyCashFundingService $fundings) {}

    public function handle(ExpenseApproved $event): void
    {
        $this->fundings->syncAfterDisbursementApproved($event->expense);
    }
}
