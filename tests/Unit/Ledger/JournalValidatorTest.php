<?php

namespace Tests\Unit\Ledger;

use App\Domains\Ledger\Validators\JournalValidator;
use App\Enums\LedgerAccountType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JournalValidatorTest extends TestCase
{
    private JournalValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new JournalValidator;
    }

    #[Test]
    public function it_accepts_balanced_journal(): void
    {
        $this->validator->assertJournalBalanced([
            ['debit' => '100.00', 'credit' => '0.00'],
            ['debit' => '0.00', 'credit' => '100.00'],
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_unbalanced_journal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Jurnal tidak seimbang');

        $this->validator->assertJournalBalanced([
            ['debit' => '100.00', 'credit' => '0.00'],
            ['debit' => '0.00', 'credit' => '50.00'],
        ]);
    }

    #[Test]
    public function it_normalizes_lines_and_rejects_empty_row(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('debit atau credit');

        $this->validator->normalizeLines([
            [
                'ledger_account_type' => LedgerAccountType::ACCOUNT,
                'ledger_account_id' => 1,
                'debit' => '0',
                'credit' => '0',
            ],
        ]);
    }

    #[Test]
    public function it_rejects_debit_and_credit_on_same_line(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('debit dan credit sekaligus');

        $this->validator->normalizeLines([
            [
                'ledger_account_type' => LedgerAccountType::FUND,
                'ledger_account_id' => 2,
                'debit' => '10.00',
                'credit' => '10.00',
            ],
        ]);
    }

    #[Test]
    public function it_normalizes_valid_lines(): void
    {
        $lines = $this->validator->normalizeLines([
            [
                'ledger_account_type' => 'account',
                'ledger_account_id' => 5,
                'debit' => '250000.50',
                'credit' => '0',
            ],
            [
                'ledger_account_type' => LedgerAccountType::FUND,
                'ledger_account_id' => 3,
                'debit' => '0',
                'credit' => '250000.50',
            ],
        ]);

        $this->assertSame(LedgerAccountType::ACCOUNT, $lines[0]['ledger_account_type']);
        $this->assertSame('250000.50', $lines[0]['debit']);
        $this->assertSame(LedgerAccountType::FUND, $lines[1]['ledger_account_type']);
        $this->assertSame('250000.50', $lines[1]['credit']);
    }
}
