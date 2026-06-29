<?php

namespace Tests\Unit\Audit;

use App\Domains\Audit\Validators\AuditLogValidator;
use App\Exceptions\DomainException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogValidatorTest extends TestCase
{
    #[Test]
    public function it_requires_non_empty_action(): void
    {
        $validator = new AuditLogValidator;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Aksi audit wajib diisi');

        $validator->assertAction('');
    }

    #[Test]
    public function it_accepts_valid_action(): void
    {
        $validator = new AuditLogValidator;
        $validator->assertAction('created');
        $this->assertTrue(true);
    }
}
