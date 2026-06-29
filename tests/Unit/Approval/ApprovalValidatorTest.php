<?php

namespace Tests\Unit\Approval;

use App\Domains\Approval\Validators\ApprovalValidator;
use App\Exceptions\DomainException;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApprovalValidatorTest extends TestCase
{
    #[Test]
    public function it_accepts_entities_with_approvals_relationship(): void
    {
        $validator = new ApprovalValidator;
        $validator->assertApprovable(new Receipt);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_entities_without_approvals_relationship(): void
    {
        $validator = new ApprovalValidator;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tidak mendukung workflow approval');

        $validator->assertApprovable(new class extends Model
        {
            protected $table = 'users';
        });
    }
}
