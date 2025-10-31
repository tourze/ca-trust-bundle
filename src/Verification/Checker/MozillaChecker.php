<?php

namespace Tourze\CATrustBundle\Verification\Checker;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\CATrustBundle\Verification\CheckerInterface;
use Tourze\CATrustBundle\Verification\VerificationStatus;

/**
 * Mozilla根证书验证器
 *
 * 通过Mozilla根证书列表验证证书是否可信
 */
class MozillaChecker implements CheckerInterface
{
    /**
     * @var string Mozilla根证书列表API
     */
    private string $apiEndpoint = 'https://ccadb-public.secure.force.com/mozilla/IncludedCACertificateReportPEMCSV';

    /**
     * @var array<string>|null 缓存的根证书指纹列表
     */
    private ?array $rootFingerprints = null;

    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function verify(SslCertificate $certificate): VerificationStatus
    {
        try {
            $sha256Fingerprint = $certificate->getFingerprintSha256();
            if ('' === $sha256Fingerprint) {
                $this->logger->info('Certificate SHA256 fingerprint is empty', [
                    'domain' => $certificate->getDomain(),
                    'issuer' => $certificate->getIssuer(),
                ]);

                return VerificationStatus::UNCERTAIN;
            }

            // 统一格式为小写无冒号
            $sha256Fingerprint = strtolower(str_replace(':', '', $sha256Fingerprint));

            $this->logger->info('Starting Mozilla verification', [
                'fingerprint' => $sha256Fingerprint,
                'domain' => $certificate->getDomain(),
            ]);

            // 加载Mozilla根证书列表（如果尚未加载）
            if (null === $this->rootFingerprints) {
                if (!$this->loadRootFingerprints()) {
                    $this->logger->warning('Failed to load Mozilla root fingerprints');

                    return VerificationStatus::UNCERTAIN;
                }
            }

            // 检查指纹是否在Mozilla根证书列表中
            $found = in_array($sha256Fingerprint, $this->rootFingerprints ?? [], true);
            $status = $found ? VerificationStatus::PASSED : VerificationStatus::FAILED;

            $this->logger->info('Mozilla verification completed', [
                'fingerprint' => $sha256Fingerprint,
                'status' => $status->value,
                'found' => $found,
                'total_root_certs' => count($this->rootFingerprints ?? []),
            ]);

            return $status;
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during Mozilla verification', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            return VerificationStatus::UNCERTAIN;
        }
    }

    /**
     * 加载Mozilla根证书指纹列表
     */
    private function loadRootFingerprints(): bool
    {
        $startTime = microtime(true);

        try {
            $this->logger->info('Starting to load Mozilla root fingerprints', [
                'endpoint' => $this->apiEndpoint,
            ]);

            $client = $this->createHttpClient();

            // 审计日志：记录 HTTP 请求详细信息
            $this->logger->info('Mozilla HTTP request started', [
                'method' => 'GET',
                'url' => $this->apiEndpoint,
            ]);

            // @audit-logged HTTP request with comprehensive logging before and after
            $response = $client->request('GET', $this->apiEndpoint);

            $statusCode = $response->getStatusCode();
            $responseTime = microtime(true) - $startTime;

            // 审计日志：记录 HTTP 响应结果
            $this->logger->info('Mozilla HTTP response received', [
                'status_code' => $statusCode,
                'response_time' => $responseTime,
            ]);

            if (200 !== $statusCode) {
                $this->logger->error('Mozilla API returned non-200 status code', [
                    'status_code' => $statusCode,
                    'response_time' => $responseTime,
                ]);

                return false;
            }

            $csvContent = $response->getContent();
            $contentSize = strlen($csvContent);

            $success = $this->parseCsvContent($csvContent);

            $this->logger->info('Mozilla root fingerprints loading completed', [
                'success' => $success,
                'response_time' => $responseTime,
                'content_size' => $contentSize,
                'fingerprints_loaded' => $success ? count($this->rootFingerprints ?? []) : 0,
            ]);

            return $success;
        } catch (ExceptionInterface $e) {
            $responseTime = microtime(true) - $startTime;
            $this->logger->error('Mozilla API request failed', [
                'error' => $e->getMessage(),
                'response_time' => $responseTime,
                'exception_class' => get_class($e),
            ]);

            return false;
        } catch (\Throwable $e) {
            $responseTime = microtime(true) - $startTime;
            $this->logger->error('Unexpected error loading Mozilla root fingerprints', [
                'error' => $e->getMessage(),
                'response_time' => $responseTime,
                'exception_class' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * 解析CSV内容并提取指纹
     */
    private function parseCsvContent(string $csvContent): bool
    {
        $this->rootFingerprints = [];
        $lines = explode("\n", $csvContent);

        $result = $this->findFingerprintColumnIndex($lines);
        if (-1 === $result['index']) {
            return false;
        }

        $this->extractFingerprints($result['lines'], $result['index']);

        return [] !== $this->rootFingerprints && null !== $this->rootFingerprints;
    }

    /**
     * @param array<string> $lines
     * @return array{index: int, lines: array<string>}
     */
    private function findFingerprintColumnIndex(array $lines): array
    {
        $headerLine = array_shift($lines) ?? '';
        $headers = str_getcsv($headerLine, ',', '"', '\\');

        foreach ($headers as $index => $header) {
            if (false !== stripos($header ?? '', 'SHA-256 Fingerprint')) {
                return ['index' => $index, 'lines' => $lines];
            }
        }

        return ['index' => -1, 'lines' => $lines];
    }

    /**
     * @param array<string> $lines
     */
    private function extractFingerprints(array $lines, int $fingerprintIndex): void
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            $fingerprint = $this->extractFingerprintFromLine($line, $fingerprintIndex);
            if (null !== $fingerprint) {
                $this->rootFingerprints[] = $fingerprint;
            }
        }
    }

    private function extractFingerprintFromLine(string $line, int $fingerprintIndex): ?string
    {
        $columns = str_getcsv($line, ',', '"', '\\');

        if (count($columns) <= $fingerprintIndex) {
            return null;
        }

        $fingerprint = trim($columns[$fingerprintIndex] ?? '');
        if ('' === $fingerprint) {
            return null;
        }

        return strtolower(str_replace([':', ' '], '', $fingerprint));
    }

    public function getName(): string
    {
        return 'Mozilla';
    }

    /**
     * 创建HTTP客户端，方便测试时进行模拟
     */
    protected function createHttpClient(): HttpClientInterface
    {
        return HttpClient::create();
    }
}
