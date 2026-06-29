<?php

namespace App\Domains\Expense\Listeners;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Expense\Events\BankFeeCreated;
use App\Domains\Expense\Events\BankFeeDeferred;
use App\Domains\Expense\Events\BankFeePosted;
use App\Domains\Expense\Events\BankFeeReversed;
use App\Domains\Expense\Events\ExpenseCreated;
use App\Domains\Expense\Events\ExpenseUpdated;
use App\Enums\ApprovalAction;

class LogExpenseAudit
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handleCreated(ExpenseCreated $event): void
    {
        $this->audit->log($event->expense, 'created', null, $event->expense->toArray(), $event->actor);
    }

    public function handleUpdated(ExpenseUpdated $event): void
    {
        $this->audit->log(
            $event->expense,
            'updated',
            $event->before,
            $event->expense->fresh()->toArray(),
            $event->actor,
        );
    }

    public function handleBankFeeCreated(BankFeeCreated $event): void
    {
        $this->audit->log($event->fee, 'created', null, $event->fee->toArray(), $event->actor);
    }

    public function handleBankFeePosted(BankFeePosted $event): void
    {
        $this->audit->log($event->fee, 'posted', null, ['amount' => $event->amount], $event->actor);
    }

    public function handleBankFeeDeferred(BankFeeDeferred $event): void
    {
        $this->audit->log($event->fee, 'deferred', null, [
            'liability_id' => $event->liabilityId,
            'amount' => $event->amount,
        ], $event->actor);
    }

    public function handleBankFeeReversed(BankFeeReversed $event): void
    {
        $this->audit->log($event->fee, ApprovalAction::REVERSED->value, null, ['reason' => $event->reason], $event->actor, 'reversal');
    }
}
