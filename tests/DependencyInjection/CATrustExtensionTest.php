<?php

namespace Tourze\CATrustBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\CATrustBundle\DependencyInjection\CATrustExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(CATrustExtension::class)]
final class CATrustExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    protected function getContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', true);

        return $container;
    }

    /**
     * 测试扩展别名和配置
     */
    public function testExtensionAlias(): void
    {
        $extension = new CATrustExtension();

        // 验证扩展别名
        $this->assertSame('ca_trust', $extension->getAlias());
    }
}
