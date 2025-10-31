<?php

namespace Tourze\CATrustBundle\Tests\Verification;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CATrustBundle\Verification\VerificationStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(VerificationStatus::class)]
final class VerificationStatusTest extends AbstractEnumTestCase
{
    /**
     * 测试 toArray 方法
     */
    public function testToArray(): void
    {
        $status = VerificationStatus::PASSED;
        $array = $status->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertSame('通过', $array['value']);
        $this->assertSame('通过', $array['label']);
    }
}
