<?php

namespace Tourze\CATrustBundle\Tests\Verification;

use PHPUnit\Framework\TestCase;
use Tourze\CATrustBundle\Verification\VerificationStatus;

class VerificationStatusTest extends TestCase
{
    /**
     * 测试枚举值是否正确
     */
    public function testEnumValues(): void
    {
        $this->assertSame('通过', VerificationStatus::PASSED->value);
        $this->assertSame('失败', VerificationStatus::FAILED->value);
        $this->assertSame('存疑', VerificationStatus::UNCERTAIN->value);
    }

    /**
     * 测试枚举基本功能
     */
    public function testEnumFunctionality(): void
    {
        $status = VerificationStatus::PASSED;
        
        $this->assertInstanceOf(VerificationStatus::class, $status);
        $this->assertTrue($status === VerificationStatus::PASSED);
        $this->assertFalse($status === VerificationStatus::FAILED);
    }

    /**
     * 测试枚举比较
     */
    public function testEnumComparison(): void
    {
        $this->assertTrue(VerificationStatus::PASSED === VerificationStatus::PASSED);
        $this->assertFalse(VerificationStatus::PASSED === VerificationStatus::FAILED);
        $this->assertFalse(VerificationStatus::FAILED === VerificationStatus::UNCERTAIN);
    }
} 