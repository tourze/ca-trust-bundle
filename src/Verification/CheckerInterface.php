<?php

namespace Tourze\CATrustBundle\Verification;

use Spatie\SslCertificate\SslCertificate;

interface CheckerInterface
{
    /**
     * 验证证书是否可信
     */
    public function verify(SslCertificate $certificate): VerificationStatus;
    
    /**
     * 获取验证器名称
     */
    public function getName(): string;
}
