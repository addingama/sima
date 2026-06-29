<?php

namespace App\Providers;

use App\Domains\Approval\Events\ApprovalRecorded;
use App\Domains\Approval\Listeners\LogApprovalAudit;
use App\Domains\Audit\Events\AuditLogged;
use App\Domains\Audit\Listeners\RecordAuditMetrics;
use App\Domains\Audit\Policies\AuditPolicy;
use App\Domains\Expense\Events\BankFeeCreated;
use App\Domains\Expense\Events\BankFeeDeferred;
use App\Domains\Expense\Events\BankFeePosted;
use App\Domains\Expense\Events\BankFeeReversed;
use App\Domains\Expense\Events\ExpenseApproved;
use App\Domains\Expense\Events\ExpenseCreated;
use App\Domains\Expense\Events\ExpenseRejected;
use App\Domains\Expense\Events\ExpenseReversed;
use App\Domains\Expense\Events\ExpenseSubmitted;
use App\Domains\Expense\Events\ExpenseUpdated;
use App\Domains\Expense\Events\ExpenseVerified;
use App\Domains\Expense\Listeners\LogExpenseAudit;
use App\Domains\Expense\Listeners\RecordExpenseApproval;
use App\Domains\Ledger\Events\LedgerJournalPosted;
use App\Domains\Ledger\Listeners\VerifyLedgerInvariant;
use App\Domains\Receipt\Events\ReceiptApproved;
use App\Domains\Receipt\Events\ReceiptCreated;
use App\Domains\Receipt\Events\ReceiptRejected;
use App\Domains\Receipt\Events\ReceiptReversed;
use App\Domains\Receipt\Events\ReceiptSubmitted;
use App\Domains\Receipt\Events\ReceiptUpdated;
use App\Domains\Receipt\Listeners\LogReceiptAudit;
use App\Domains\Receipt\Listeners\RecordReceiptApproval;
use App\Domains\Reconciliation\Events\ReconciliationCompleted;
use App\Domains\Reconciliation\Events\ReconciliationCreated;
use App\Domains\Reconciliation\Listeners\LogReconciliationAudit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use OwenIt\Auditing\Models\Audit;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Audit::class, AuditPolicy::class);

        Event::listen(AuditLogged::class, RecordAuditMetrics::class);
        Event::listen(ApprovalRecorded::class, LogApprovalAudit::class);
        Event::listen(LedgerJournalPosted::class, VerifyLedgerInvariant::class);

        Event::listen(ReceiptCreated::class, [LogReceiptAudit::class, 'handleCreated']);
        Event::listen(ReceiptUpdated::class, [LogReceiptAudit::class, 'handleUpdated']);
        Event::listen(ReceiptSubmitted::class, [RecordReceiptApproval::class, 'handleSubmitted']);
        Event::listen(ReceiptApproved::class, [RecordReceiptApproval::class, 'handleApproved']);
        Event::listen(ReceiptRejected::class, [RecordReceiptApproval::class, 'handleRejected']);
        Event::listen(ReceiptReversed::class, [RecordReceiptApproval::class, 'handleReversed']);

        Event::listen(ExpenseCreated::class, [LogExpenseAudit::class, 'handleCreated']);
        Event::listen(ExpenseUpdated::class, [LogExpenseAudit::class, 'handleUpdated']);
        Event::listen(ExpenseSubmitted::class, [RecordExpenseApproval::class, 'handleSubmitted']);
        Event::listen(ExpenseVerified::class, [RecordExpenseApproval::class, 'handleVerified']);
        Event::listen(ExpenseApproved::class, [RecordExpenseApproval::class, 'handleApproved']);
        Event::listen(ExpenseRejected::class, [RecordExpenseApproval::class, 'handleRejected']);
        Event::listen(ExpenseReversed::class, [RecordExpenseApproval::class, 'handleReversed']);

        Event::listen(BankFeeCreated::class, [LogExpenseAudit::class, 'handleBankFeeCreated']);
        Event::listen(BankFeePosted::class, [LogExpenseAudit::class, 'handleBankFeePosted']);
        Event::listen(BankFeeDeferred::class, [LogExpenseAudit::class, 'handleBankFeeDeferred']);
        Event::listen(BankFeeReversed::class, [LogExpenseAudit::class, 'handleBankFeeReversed']);

        Event::listen(ReconciliationCreated::class, [LogReconciliationAudit::class, 'handleCreated']);
        Event::listen(ReconciliationCompleted::class, [LogReconciliationAudit::class, 'handleCompleted']);
    }
}
