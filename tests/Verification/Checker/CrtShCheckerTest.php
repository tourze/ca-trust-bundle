<?php

namespace Tourze\CATrustBundle\Tests\Verification\Checker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CATrustBundle\Verification\Checker\CrtShChecker;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CrtShChecker::class)]
#[RunTestsInSeparateProcesses]
final class CrtShCheckerTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 这个测试需要特殊的容器配置
    }

    /**
     * 测试验证器接口实现
     */
    public function testCheckerInterface(): void
    {
        $checker = self::getService(CrtShChecker::class);

        // 验证名称不为空
        $this->assertNotEmpty($checker->getName());
    }

    /**
     * 测试getName方法
     */
    public function testGetName(): void
    {
        $checker = self::getService(CrtShChecker::class);
        $this->assertSame('crt.sh', $checker->getName());
    }

    /**
     * 测试verify方法存在且可调用
     */
    public function testVerifyMethodExists(): void
    {
        $checker = self::getService(CrtShChecker::class);

        // 验证verify方法存在且可调用
        $this->assertTrue(method_exists($checker, 'verify'));
        $reflection = new \ReflectionMethod($checker, 'verify');
        $this->assertTrue($reflection->isPublic());
    }
}
