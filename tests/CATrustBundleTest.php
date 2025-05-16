<?php

namespace Tourze\CATrustBundle\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\CATrustBundle\CATrustBundle;

class CATrustBundleTest extends TestCase
{
    /**
     * 测试Bundle基础实例化
     */
    public function testBundleInstantiation(): void
    {
        $bundle = new CATrustBundle();
        $this->assertInstanceOf(CATrustBundle::class, $bundle);
    }

    /**
     * 测试Bundle继承关系
     */
    public function testBundleInheritance(): void
    {
        $bundle = new CATrustBundle();
        $this->assertInstanceOf(\Symfony\Component\HttpKernel\Bundle\Bundle::class, $bundle);
    }
} 