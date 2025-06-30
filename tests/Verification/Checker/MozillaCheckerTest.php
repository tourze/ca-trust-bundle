<?php

namespace Tourze\CATrustBundle\Tests\Verification\Checker;

use PHPUnit\Framework\TestCase;
use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tourze\CATrustBundle\Verification\Checker\MozillaChecker;
use Tourze\CATrustBundle\Verification\VerificationStatus;

class MozillaCheckerTest extends TestCase
{
    /**
     * 测试验证器基本实例化
     */
    public function testInstantiation(): void
    {
        $checker = new MozillaChecker();
        $this->assertInstanceOf(MozillaChecker::class, $checker);
    }
    
    /**
     * 测试getName方法
     */
    public function testGetName(): void
    {
        $checker = new MozillaChecker();
        $this->assertSame('Mozilla', $checker->getName());
    }
    
    
    
    
    
    /**
     * 测试空指纹情况
     */
    public function testVerifyWithEmptyFingerprint(): void
    {
        // 创建模拟证书，返回空指纹
        $certificate = $this->createMock(SslCertificate::class);
        $certificate->method('getFingerprintSha256')->willReturn('');
        
        $checker = new MozillaChecker();
        
        // 执行验证
        $status = $checker->verify($certificate);
        
        // 断言结果为存疑
        $this->assertSame(VerificationStatus::UNCERTAIN, $status);
    }
    
    /**
     * 测试解析CSV内容
     */
    public function testParseCsvContent(): void
    {
        $checker = new MozillaChecker();
        
        // 准备测试数据
        $csvContent = "CA Owner,Common Name,SHA-256 Fingerprint,Valid From,Valid To\n";
        $csvContent .= "Mozilla,Test CA 1,AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99,2020-01-01,2025-01-01\n";
        $csvContent .= "Mozilla,Test CA 2,11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00,2020-01-01,2025-01-01\n";
        
        // 使用反射调用私有方法
        $reflection = new \ReflectionClass(MozillaChecker::class);
        $method = $reflection->getMethod('parseCsvContent');
        $method->setAccessible(true);
        
        // 调用私有方法
        $result = $method->invoke($checker, $csvContent);
        
        // 断言结果
        $this->assertTrue($result);
        
        // 使用反射获取私有属性
        $property = $reflection->getProperty('rootFingerprints');
        $property->setAccessible(true);
        $fingerprints = $property->getValue($checker);
        
        // 验证解析后的指纹列表是否包含期望的指纹
        $this->assertIsArray($fingerprints);
        $this->assertCount(2, $fingerprints);
        $this->assertContains('aabbccddeeff00112233445566778899', $fingerprints);
        $this->assertContains('112233445566778899aabbccddeeff00', $fingerprints);
    }
    
    /**
     * 测试解析无效的CSV内容
     */
    public function testParseCsvContentWithInvalidFormat(): void
    {
        $checker = new MozillaChecker();
        
        // 准备无效CSV数据
        $invalidCsv = "Invalid,CSV\nFormat,Data";
        
        // 使用反射调用私有方法
        $reflection = new \ReflectionClass(MozillaChecker::class);
        $method = $reflection->getMethod('parseCsvContent');
        $method->setAccessible(true);
        
        // 调用私有方法
        $result = $method->invoke($checker, $invalidCsv);
        
        // 断言结果
        $this->assertFalse($result);
    }
} 