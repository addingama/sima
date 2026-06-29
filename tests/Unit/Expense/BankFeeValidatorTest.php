<?php

namespace Tests\Unit\Expense;

use App\Domains\Expense\Validators\BankFeeValidator;
use App\Enums\BankFeeStatus;
use App\Exceptions\DomainException;
use App\Models\BankFee;
use App\Models\Fund;
use App\Models\User;
use Database\Seeders\SystemFundSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankFeeValidatorTest extends TestCase
{
    use RefreshDatabase;

    private BankFeeValidator $validator;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemFundSeeder::class);
        $this->validator = app(BankFeeValidator::class);
        $this->actor = User::factory()->create();
    }

    #[Test]
    public function it_allows_posting_only_from_draft(): void
    {
        $draft = new BankFee(['status' => BankFeeStatus::DRAFT]);
        $posted = new BankFee(['status' => BankFeeStatus::POSTED]);

        $this->validator->assertDraft($draft);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('draft');
        $this->validator->assertDraft($posted);
    }

    #[Test]
    public function it_allows_reversal_only_for_posted_fees(): void
    {
        $posted = new BankFee(['status' => BankFeeStatus::POSTED]);
        $draft = new BankFee(['status' => BankFeeStatus::DRAFT]);

        $this->validator->assertPostedForReversal($posted);

        $this->expectException(DomainException::class);
        $this->validator->assertPostedForReversal($draft);
    }

    #[Test]
    public function it_rejects_restricted_fund_for_bank_fee(): void
    {
        $restricted = Fund::create([
            'code' => 'ZKT',
            'name' => 'Zakat',
            'type' => 'restricted',
            'is_active' => true,
            'created_by' => $this->actor->id,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('restricted');

        $this->validator->assertFundAllowed($restricted->id);
    }

    #[Test]
    public function it_allows_operational_fund_for_bank_fee(): void
    {
        $operational = Fund::findBySystemKey(Fund::KEY_OPERATIONAL);

        $this->validator->assertFundAllowed($operational->id);
        $this->assertTrue(true);
    }
}
