<?php

namespace Tourze\CATrustBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\CATrustBundle\Command\ListSystemCertsCommand;
use Tourze\CATrustBundle\DependencyInjection\CATrustExtension;

class CATrustExtensionTest extends TestCase
{
    /**
     * 测试扩展加载配置
     */
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new CATrustExtension();

        $extension->load([], $container);

        // 验证命令服务是否被正确注册
        $this->assertTrue($container->has(ListSystemCertsCommand::class));

        // 验证自动配置是否开启
        $definition = $container->getDefinition(ListSystemCertsCommand::class);
        $this->assertTrue($definition->isAutoconfigured());
        $this->assertTrue($definition->isAutowired());
    }

    /**
     * 测试扩展实例化
     */
    public function testExtensionInstantiation(): void
    {
        $extension = new CATrustExtension();
        $this->assertInstanceOf(CATrustExtension::class, $extension);
    }
}
