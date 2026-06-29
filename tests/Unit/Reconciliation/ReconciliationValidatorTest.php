<?php

namespace Tests\Unit\Reconciliation;

use App\Domains\Reconciliation\Validators\ReconciliationValidator;
use App\Exceptions\DomainException;
use App\Models\BankReconciliation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconciliationValidatorTest extends TestCase
{
    #[Test]
    public function it_allows_changes_only_on_draft_reconciliation(): void
    {
        $validator = new ReconciliationValidator;

        $draft = new BankReconciliation(['status' => 'draft']);
        $completed = new BankReconciliation(['status' => 'completed']);

        $validator->assertDraft($draft);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('draft');
        $validator->assertDraft($completed);
    }
}
