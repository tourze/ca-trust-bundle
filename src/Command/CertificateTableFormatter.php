<?php

namespace Tourze\CATrustBundle\Command;

use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\CATrustBundle\Verification\CheckerInterface;
use Tourze\CATrustBundle\Verification\VerificationStatus;

#[Autoconfigure(public: true)]
class CertificateTableFormatter
{
    /**
     * @param CheckerInterface[] $checkers
     */
    public function __construct(
        private array $checkers,
    ) {
    }

    /**
     * @param array<SslCertificate> $certificates
     * @param array<string, array<string, VerificationStatus>>|null $verificationResults
     */
    public function format(array $certificates, SymfonyStyle $io, ?array $verificationResults = null): void
    {
        $headers = $this->buildHeaders(null !== $verificationResults);
        $rows = $this->buildRows($certificates, $verificationResults);

        $io->table($headers, $rows);

        // 如果在验证过程中，显示验证进度
        if (null !== $verificationResults && count($verificationResults) < count($certificates)) {
            $io->writeln(sprintf('<info>验证进度: %d/%d</info>', count($verificationResults), count($certificates)));
        }
    }

    /**
     * @return array<string>
     */
    private function buildHeaders(bool $includeVerification): array
    {
        $headers = ['#', '组织', '颁发者', '域名', '签名', '生效日期', '过期日期', '签名算法'];

        if ($includeVerification) {
            foreach ($this->checkers as $checker) {
                $headers[] = $checker->getName() . ' 验证';
            }
            $headers[] = '综合验证';
        }

        return $headers;
    }

    /**
     * @param array<SslCertificate> $certificates
     * @param array<string, array<string, VerificationStatus>>|null $verificationResults
     * @return array<array<mixed>>
     */
    private function buildRows(array $certificates, ?array $verificationResults): array
    {
        $rows = [];

        foreach ($certificates as $i => $cert) {
            $row = $this->buildCertificateRow($cert, $i + 1);

            if (null !== $verificationResults) {
                $row = $this->addVerificationColumns($row, $cert, $verificationResults);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    private function buildCertificateRow(SslCertificate $cert, int $index): array
    {
        return [
            $index,
            $cert->getOrganization(),
            $cert->getIssuer(),
            $cert->getDomain(),
            $cert->getFingerprint(),
            $cert->validFromDate()->format('Y-m-d'),
            $cert->expirationDate()->format('Y-m-d'),
            $cert->getSignatureAlgorithm(),
        ];
    }

    /**
     * @param array<mixed> $row
     * @param array<string, array<string, VerificationStatus>> $verificationResults
     * @return array<mixed>
     */
    private function addVerificationColumns(array $row, SslCertificate $cert, array $verificationResults): array
    {
        if (isset($verificationResults[$cert->getFingerprint()])) {
            $certResults = $verificationResults[$cert->getFingerprint()];

            // 添加每个验证器的结果
            foreach ($this->checkers as $checker) {
                $checkerName = $checker->getName();
                $status = $certResults[$checkerName] ?? VerificationStatus::UNCERTAIN;
                $row[] = $this->formatVerificationStatus($status);
            }

            // 添加综合验证结果
            $overallStatus = $this->getOverallVerificationStatus($certResults);
            $row[] = $this->formatVerificationStatus($overallStatus);
        } else {
            // 未验证的证书显示空的验证结果
            foreach ($this->checkers as $checker) {
                $row[] = '';
            }
            $row[] = '';
        }

        return $row;
    }

    private function formatVerificationStatus(VerificationStatus $status): string
    {
        return match ($status) {
            VerificationStatus::PASSED => '<fg=green>通过</>',
            VerificationStatus::FAILED => '<fg=red>失败</>',
            VerificationStatus::UNCERTAIN => '<fg=yellow>存疑</>',
        };
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
