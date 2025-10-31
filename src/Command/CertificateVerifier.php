<?php

namespace Tourze\CATrustBundle\Command;

use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\CATrustBundle\Verification\CheckerInterface;
use Tourze\CATrustBundle\Verification\VerificationStatus;

#[Autoconfigure(public: true)]
class CertificateVerifier
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
     * @return array<string, array<string, VerificationStatus>>
     */
    public function verifyWithProgress(
        array $certificates,
        ConsoleSectionOutput $progressSection,
        ?ConsoleSectionOutput $tableSection = null,
    ): array {
        $verificationResults = [];

        if (null !== $tableSection) {
            $table = $this->createProgressTable($tableSection);
        }

        foreach ($certificates as $index => $cert) {
            $certName = $cert->getDomain() ?? ($cert->getIssuer() ?? '未知证书');
            $certResults = $this->verifyCertificate($cert, $progressSection, $index, count($certificates), $certName);
            $verificationResults[$cert->getFingerprint()] = $certResults;

            if (isset($table)) {
                $this->addRowToTable($table, $cert, $index, $certResults);
            }
        }

        $progressSection->overwrite('<info>验证完成!</info>');

        return $verificationResults;
    }

    /**
     * @return array<string, VerificationStatus>
     */
    private function verifyCertificate(
        SslCertificate $cert,
        ConsoleSectionOutput $progressSection,
        int $index,
        int $total,
        string $certName,
    ): array {
        $certResults = [];

        foreach ($this->checkers as $checker) {
            $checkerName = $checker->getName();

            $this->updateProgress($progressSection, $index, $total, $certName, $checkerName);

            $status = $checker->verify($cert);
            $certResults[$checkerName] = $status;

            $this->updateProgressWithResult($progressSection, $index, $total, $certName, $checkerName, $status);
        }

        return $certResults;
    }

    private function createProgressTable(ConsoleSectionOutput $tableSection): Table
    {
        $table = new Table($tableSection);
        $headers = ['#', '组织', '颁发者', '域名', '签名', '生效日期', '过期日期', '签名算法'];

        foreach ($this->checkers as $checker) {
            $headers[] = $checker->getName() . ' 验证';
        }
        $headers[] = '综合验证';

        $table->setHeaders($headers);
        $table->render();

        return $table;
    }

    /**
     * @param array<string, VerificationStatus> $certResults
     */
    private function addRowToTable(Table $table, SslCertificate $cert, int $index, array $certResults): void
    {
        $row = [
            $index + 1,
            $cert->getOrganization(),
            $cert->getIssuer(),
            $cert->getDomain(),
            $cert->getFingerprint(),
            $cert->validFromDate()->format('Y-m-d'),
            $cert->expirationDate()->format('Y-m-d'),
            $cert->getSignatureAlgorithm(),
        ];

        foreach ($this->checkers as $checker) {
            $checkerName = $checker->getName();
            $status = $certResults[$checkerName];
            $row[] = $this->formatVerificationStatus($status);
        }

        $overallStatus = $this->getOverallVerificationStatus($certResults);
        $row[] = $this->formatVerificationStatus($overallStatus);

        $table->appendRow($row);
    }

    private function updateProgress(
        ConsoleSectionOutput $progressSection,
        int $index,
        int $total,
        string $certName,
        string $checkerName,
    ): void {
        $progressSection->overwrite(sprintf(
            '正在验证 [%d/%d] %s - 使用 %s 验证器',
            $index + 1,
            $total,
            $certName,
            $checkerName
        ));
    }

    private function updateProgressWithResult(
        ConsoleSectionOutput $progressSection,
        int $index,
        int $total,
        string $certName,
        string $checkerName,
        VerificationStatus $status,
    ): void {
        $progressSection->overwrite(sprintf(
            '正在验证 [%d/%d] %s - %s: %s',
            $index + 1,
            $total,
            $certName,
            $checkerName,
            $this->formatVerificationStatus($status)
        ));
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
