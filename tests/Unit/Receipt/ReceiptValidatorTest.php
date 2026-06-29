<?php

namespace Tests\Unit\Receipt;

use App\Domains\Receipt\Validators\ReceiptValidator;
use App\Enums\ReceiptStatus;
use App\Exceptions\DomainException;
use App\Models\Account;
use App\Models\Fund;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReceiptValidatorTest extends TestCase
{
    use RefreshDatabase;

    private ReceiptValidator $validator;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(ReceiptValidator::class);
        $this->actor = User::factory()->create();
    }

    #[Test]
    public function it_requires_at_least_one_allocation(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('minimal satu alokasi');

        $this->validator->assertAllocationsMatch('100000.00', []);
    }

    #[Test]
    public function it_requires_positive_allocation_amounts(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('lebih besar dari nol');

        $this->validator->assertAllocationsMatch('100000.00', [
            ['fund_id' => 1, 'amount' => '0.00'],
        ]);
    }

    #[Test]
    public function it_requires_allocation_total_to_match_receipt_amount(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('harus sama dengan total penerimaan');

        $this->validator->assertAllocationsMatch('500000.00', [
            ['fund_id' => 1, 'amount' => '400000.00'],
        ]);
    }

    #[Test]
    public function it_validates_existing_allocations_on_receipt(): void
    {
        $account = Account::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'is_active' => true, 'created_by' => $this->actor->id]);
        $fund = Fund::create(['code' => 'ZKT', 'name' => 'Zakat', 'type' => 'restricted', 'is_active' => true, 'created_by' => $this->actor->id]);

        $receipt = Receipt::create([
            'receipt_number' => 'RCP-TEST',
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'cash',
            'amount' => '500000.00',
            'status' => ReceiptStatus::DRAFT->value,
            'created_by' => $this->actor->id,
        ]);

        ReceiptAllocation::create([
            'receipt_id' => $receipt->id,
            'fund_id' => $fund->id,
            'amount' => '500000.00',
            'status' => 'draft',
            'created_by' => $this->actor->id,
        ]);

        $this->validator->assertAllocationsMatchExisting($receipt);
        $this->validator->assertHasAllocations($receipt);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_invalid_status_transition(): void
    {
        $receipt = new Receipt(['status' => ReceiptStatus::APPROVED]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Aksi tidak valid');

        $this->validator->assertStatus($receipt, [ReceiptStatus::DRAFT]);
    }

    #[Test]
    public function it_allows_reversal_only_for_approved_receipts(): void
    {
        $draft = new Receipt(['status' => ReceiptStatus::DRAFT]);
        $approved = new Receipt(['status' => ReceiptStatus::APPROVED]);

        $this->expectException(DomainException::class);
        $this->validator->assertApprovedForReversal($draft);

        $this->validator->assertApprovedForReversal($approved);
        $this->assertTrue(true);
    }
}
