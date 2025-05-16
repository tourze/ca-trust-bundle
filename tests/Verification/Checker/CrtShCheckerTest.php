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
     * 测试证书验证通过的情况
     */
    public function testVerifyPassed(): void
    {
        // 创建模拟证书
        $certificate = $this->createMock(SslCertificate::class);
        $certificate->method('getFingerprint')->willReturn('AA:BB:CC:DD:EE:FF');
        
        // 创建模拟HTTP响应，包含验证通过的内容
        $mockResponse = new MockResponse('<html><body>crt.sh ID: 12345</body></html>', [
            'http_code' => 200,
        ]);
        
        // 使用反射修改私有属性
        $checker = $this->getMockBuilder(CrtShChecker::class)
            ->onlyMethods(['createHttpClient'])
            ->getMock();
            
        $mockHttpClient = new MockHttpClient($mockResponse);
        $checker->method('createHttpClient')->willReturn($mockHttpClient);
        
        // 执行验证
        $status = $this->invokeMethod($checker, 'verify', [$certificate]);
        
        // 断言结果
        $this->assertSame(VerificationStatus::PASSED, $status);
    }
    
    /**
     * 测试证书验证失败的情况
     */
    public function testVerifyFailed(): void
    {
        // 创建模拟证书
        $certificate = $this->createMock(SslCertificate::class);
        $certificate->method('getFingerprint')->willReturn('AA:BB:CC:DD:EE:FF');
        
        // 创建模拟HTTP响应，包含验证失败的内容 (无 crt.sh ID)
        $mockResponse = new MockResponse('<html><body>No certificates found</body></html>', [
            'http_code' => 200,
        ]);
        
        // 使用反射修改私有属性
        $checker = $this->getMockBuilder(CrtShChecker::class)
            ->onlyMethods(['createHttpClient'])
            ->getMock();
            
        $mockHttpClient = new MockHttpClient($mockResponse);
        $checker->method('createHttpClient')->willReturn($mockHttpClient);
        
        // 执行验证
        $status = $this->invokeMethod($checker, 'verify', [$certificate]);
        
        // 断言结果
        $this->assertSame(VerificationStatus::FAILED, $status);
    }
    
    /**
     * 测试HTTP请求异常情况
     */
    public function testVerifyWithHttpException(): void
    {
        // 创建模拟证书
        $certificate = $this->createMock(SslCertificate::class);
        $certificate->method('getFingerprint')->willReturn('AA:BB:CC:DD:EE:FF');
        
        // 创建模拟HTTP响应，模拟网络错误
        $mockResponse = new MockResponse('', [
            'http_code' => 500,
            'error' => 'Server error',
        ]);
        
        // 使用反射修改私有属性
        $checker = $this->getMockBuilder(CrtShChecker::class)
            ->onlyMethods(['createHttpClient'])
            ->getMock();
            
        $mockHttpClient = new MockHttpClient($mockResponse);
        $checker->method('createHttpClient')->willReturn($mockHttpClient);
        
        // 执行验证
        $status = $this->invokeMethod($checker, 'verify', [$certificate]);
        
        // 断言结果为存疑
        $this->assertSame(VerificationStatus::UNCERTAIN, $status);
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
    
    /**
     * 通过反射调用对象的私有/受保护方法
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $parameters);
    }
} 