<?php

namespace Tourze\CATrustBundle\Tests\Verification\Checker;

use PHPUnit\Framework\TestCase;
use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tourze\CATrustBundle\Verification\Checker\CrtShChecker;
use Tourze\CATrustBundle\Verification\VerificationStatus;

class CrtShCheckerTest extends TestCase
{
    /**
     * 测试验证器基本实例化
     */
    public function testInstantiation(): void
    {
        $checker = new CrtShChecker();
        $this->assertInstanceOf(CrtShChecker::class, $checker);
    }
    
    /**
     * 测试getName方法
     */
    public function testGetName(): void
    {
        $checker = new CrtShChecker();
        $this->assertSame('crt.sh', $checker->getName());
    }
    
    
    
    
    /**
     * 测试空指纹情况
     */
    public function testVerifyWithEmptyFingerprint(): void
    {
        // 创建模拟证书，返回空指纹
        $certificate = $this->createMock(SslCertificate::class);
        $certificate->method('getFingerprint')->willReturn('');
        
        $checker = new CrtShChecker();
        
        // 执行验证
        $status = $checker->verify($certificate);
        
        // 断言结果为存疑
        $this->assertSame(VerificationStatus::UNCERTAIN, $status);
    }
    
} 