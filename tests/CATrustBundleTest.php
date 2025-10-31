<?php

declare(strict_types=1);

namespace Tourze\CATrustBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CATrustBundle\CATrustBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(CATrustBundle::class)]
#[RunTestsInSeparateProcesses]
final class CATrustBundleTest extends AbstractBundleTestCase
{
}
