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
    }

    /**
     * 测试枚举通过函数获取描述
     */
    public function testEnumDescription(): void
    {
        $this->assertSame('通过', VerificationStatus::PASSED->value);
        $this->assertSame('失败', VerificationStatus::FAILED->value);
        $this->assertSame('存疑', VerificationStatus::UNCERTAIN->value);
    }
    
    /**
     * 测试枚举案例数量
     */
    public function testEnumCases(): void
    {
        $cases = VerificationStatus::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(VerificationStatus::PASSED, $cases);
        $this->assertContains(VerificationStatus::FAILED, $cases);
        $this->assertContains(VerificationStatus::UNCERTAIN, $cases);
    }
} 