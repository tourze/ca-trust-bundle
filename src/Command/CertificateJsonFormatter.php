<?php

namespace Tourze\CATrustBundle\Command;

use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\CATrustBundle\Verification\VerificationStatus;

#[Autoconfigure(public: true)]
class CertificateJsonFormatter
{
    /**
     * @param array<SslCertificate> $certificates
     * @param array<string, array<string, VerificationStatus>>|null $verificationResults
     */
    public function format(array $certificates, OutputInterface $output, ?array $verificationResults = null): void
    {
        $result = [];

        foreach ($certificates as $cert) {
            $certData = [
                'organization' => $cert->getOrganization() ?? '未知',
                'issuer' => $cert->getIssuer() ?? '未知',
                'domain' => $cert->getDomain(),
                'valid_from' => $cert->validFromDate()->format('Y-m-d'),
                'valid_to' => $cert->expirationDate()->format('Y-m-d'),
                'signature_algorithm' => $cert->getSignatureAlgorithm(),
                'fingerprint' => $cert->getFingerprint(),
                'domains' => $cert->getDomains(),
            ];

            // 如果有验证结果，添加到JSON中
            if (null !== $verificationResults && isset($verificationResults[$cert->getFingerprint()])) {
                $certResults = $verificationResults[$cert->getFingerprint()];
                $verificationData = [];

                foreach ($certResults as $checkerName => $status) {
                    $verificationData[$checkerName] = $status->value;
                }

                // 添加综合验证结果
                $verificationData['overall'] = $this->getOverallVerificationStatus($certResults)->value;

                $certData['verification'] = $verificationData;
            }

            $result[] = $certData;
        }

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (false === $json) {
            $json = '{"error": "JSON encoding failed"}';
        }
        $output->writeln($json);
    }

    /**
     * @param array<string, VerificationStatus> $verificationResults
     */
    private function getOverallVerificationStatus(array $verificationResults): VerificationStatus
    {
        $hasUncertain = false;

        foreach ($verificationResults as $status) {
            if (VerificationStatus::PASSED === $status) {
                return VerificationStatus::PASSED;
            }

            if (VerificationStatus::UNCERTAIN === $status) {
                $hasUncertain = true;
            }
        }

        return $hasUncertain ? VerificationStatus::UNCERTAIN : VerificationStatus::FAILED;
    }
}
