<?php

namespace Tourze\CATrustBundle\Verification;

enum VerificationStatus: string
{
    case PASSED = '通过';
    case FAILED = '失败';
    case UNCERTAIN = '存疑';
}
