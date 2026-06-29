<?php

namespace App\Domains\Receipt\Listeners;

use App\Domains\Approval\Services\ApprovalService;
use App\Domains\Receipt\Events\ReceiptApproved;
use App\Domains\Receipt\Events\ReceiptRejected;
use App\Domains\Receipt\Events\ReceiptReversed;
use App\Domains\Receipt\Events\ReceiptSubmitted;
use App\Enums\ApprovalAction;

class RecordReceiptApproval
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function handleSubmitted(ReceiptSubmitted $event): void
    {
        $this->approvals->record($event->receipt, ApprovalAction::SUBMITTED, $event->actor);
    }

    public function handleApproved(ReceiptApproved $event): void
    {
        $this->approvals->record($event->receipt, ApprovalAction::APPROVED, $event->actor, $event->notes);
        $this->approvals->record($event->receipt, ApprovalAction::POSTED, $event->actor);
    }

    public function handleRejected(ReceiptRejected $event): void
    {
        $this->approvals->record($event->receipt, ApprovalAction::REJECTED, $event->actor, $event->reason);
    }

    public function handleReversed(ReceiptReversed $event): void
    {
        $this->approvals->record($event->receipt, ApprovalAction::REVERSED, $event->actor, $event->reason);
    }
}
