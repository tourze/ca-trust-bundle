<?php

namespace Tourze\CATrustBundle\Command;

use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class CertificateFilter
{
    /**
     * @param array<string> $certificates
     * @return array<SslCertificate>
     */
    public function filter(array $certificates, ?string $keyword, ?string $signature, bool $showExpired): array
    {
        $filteredCerts = [];

        foreach ($certificates as $cert) {
            try {
                $sslCert = SslCertificate::createFromString($cert);

                if (!$this->shouldIncludeCertificate($sslCert, $keyword, $signature, $showExpired)) {
                    continue;
                }

                $filteredCerts[] = $sslCert;
            } catch (\Throwable $e) {
                // 忽略无法解析的证书
                continue;
            }
        }

        return $filteredCerts;
    }

    private function shouldIncludeCertificate(SslCertificate $cert, ?string $keyword, ?string $signature, bool $showExpired): bool
    {
        // 过滤已过期证书
        if (!$showExpired && $cert->isExpired()) {
            return false;
        }

        // 关键词过滤
        if (null !== $keyword && !$this->matchesKeyword($cert, $keyword)) {
            return false;
        }

        // 签名算法过滤
        if (null !== $signature && !$this->matchesSignature($cert, $signature)) {
            return false;
        }

        return true;
    }

    private function matchesKeyword(SslCertificate $cert, string $keyword): bool
    {
        $keyword = strtolower($keyword);

        // 在主题中搜索
        $subject = strtolower($cert->getIssuer());
        if (str_contains($subject, $keyword)) {
            return true;
        }

        // 在颁发者中搜索
        $issuer = strtolower($cert->getOrganization());
        if (str_contains($issuer, $keyword)) {
            return true;
        }

        // 在域名中搜索
        foreach ($cert->getDomains() as $domain) {
            if (str_contains(strtolower($domain), $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function matchesSignature(SslCertificate $cert, string $signature): bool
    {
        try {
            $signatureAlgorithm = $cert->getSignatureAlgorithm();

            if ('' === $signatureAlgorithm) {
                return false;
            }

            return false !== stripos($signatureAlgorithm, $signature);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
