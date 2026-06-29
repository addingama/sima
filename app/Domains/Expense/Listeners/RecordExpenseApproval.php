<?php

namespace App\Domains\Expense\Listeners;

use App\Domains\Approval\Services\ApprovalService;
use App\Domains\Expense\Events\ExpenseApproved;
use App\Domains\Expense\Events\ExpenseRejected;
use App\Domains\Expense\Events\ExpenseReversed;
use App\Domains\Expense\Events\ExpenseSubmitted;
use App\Domains\Expense\Events\ExpenseVerified;
use App\Enums\ApprovalAction;

class RecordExpenseApproval
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function handleSubmitted(ExpenseSubmitted $event): void
    {
        $this->approvals->record($event->expense, ApprovalAction::SUBMITTED, $event->actor);
    }

    public function handleVerified(ExpenseVerified $event): void
    {
        $this->approvals->record($event->expense, ApprovalAction::VERIFIED, $event->actor, $event->notes);
    }

    public function handleApproved(ExpenseApproved $event): void
    {
        $this->approvals->record($event->expense, ApprovalAction::APPROVED, $event->actor, $event->notes);
        $this->approvals->record($event->expense, ApprovalAction::POSTED, $event->actor);
    }

    public function handleRejected(ExpenseRejected $event): void
    {
        $this->approvals->record($event->expense, ApprovalAction::REJECTED, $event->actor, $event->reason);
    }

    public function handleReversed(ExpenseReversed $event): void
    {
        $this->approvals->record($event->expense, ApprovalAction::REVERSED, $event->actor, $event->reason);
    }
}
