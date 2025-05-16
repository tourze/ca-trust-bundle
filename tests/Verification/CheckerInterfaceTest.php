<?php

namespace Tourze\CATrustBundle\Tests\Verification;

use PHPUnit\Framework\TestCase;
use Spatie\SslCertificate\SslCertificate;
use Tourze\CATrustBundle\Verification\CheckerInterface;
use Tourze\CATrustBundle\Verification\VerificationStatus;

class CheckerInterfaceTest extends TestCase
{
    /**
     * 创建模拟CheckerInterface实现
     */
    public function testCheckerImplementation(): void
    {
        $checker = $this->createMock(CheckerInterface::class);
        
        $certificate = $this->createMock(SslCertificate::class);
        
        $checker->expects($this->once())
            ->method('verify')
            ->with($certificate)
            ->willReturn(VerificationStatus::PASSED);
            
        $checker->expects($this->once())
            ->method('getName')
            ->willReturn('Test Checker');
            
        $result = $checker->verify($certificate);
        $name = $checker->getName();
        
        $this->assertSame(VerificationStatus::PASSED, $result);
        $this->assertSame('Test Checker', $name);
    }
    
    /**
     * 测试验证状态枚举的使用
     */
    public function testVerificationStatusUsage(): void
    {
        $checker = $this->createMock(CheckerInterface::class);
        $certificate = $this->createMock(SslCertificate::class);
        
        // 测试三种可能的验证状态
        $checker->method('verify')
            ->willReturnOnConsecutiveCalls(
                VerificationStatus::PASSED,
                VerificationStatus::FAILED,
                VerificationStatus::UNCERTAIN
            );
            
        $this->assertSame(VerificationStatus::PASSED, $checker->verify($certificate));
        $this->assertSame(VerificationStatus::FAILED, $checker->verify($certificate));
        $this->assertSame(VerificationStatus::UNCERTAIN, $checker->verify($certificate));
    }
} 