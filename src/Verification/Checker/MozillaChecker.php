<?php

namespace Tourze\CATrustBundle\Verification\Checker;

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
     * @var array|null 缓存的根证书指纹列表
     */
    private ?array $rootFingerprints = null;

    public function verify(SslCertificate $certificate): VerificationStatus
    {
        try {
            // 使用已有的getFingerprintSha256()方法
            $sha256Fingerprint = $certificate->getFingerprintSha256();
            if (empty($sha256Fingerprint)) {
                return VerificationStatus::UNCERTAIN;
            }

            // 统一格式为小写无冒号
            $sha256Fingerprint = strtolower(str_replace(':', '', $sha256Fingerprint));

            // 加载Mozilla根证书列表（如果尚未加载）
            if ($this->rootFingerprints === null) {
                if (!$this->loadRootFingerprints()) {
                    return VerificationStatus::UNCERTAIN;
                }
            }

            // 检查指纹是否在Mozilla根证书列表中
            if (in_array($sha256Fingerprint, $this->rootFingerprints, true)) {
                return VerificationStatus::PASSED;
            }

            return VerificationStatus::FAILED;
        } catch (\Throwable $e) {
            return VerificationStatus::UNCERTAIN;
        }
    }

    /**
     * 加载Mozilla根证书指纹列表
     */
    private function loadRootFingerprints(): bool
    {
        try {
            $client = $this->createHttpClient();
            $response = $client->request('GET', $this->apiEndpoint);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $csvContent = $response->getContent();

            return $this->parseCsvContent($csvContent);
        } catch (ExceptionInterface $e) {
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 解析CSV内容并提取指纹
     */
    private function parseCsvContent(string $csvContent): bool
    {
        // 初始化指纹列表
        $this->rootFingerprints = [];

        $lines = explode("\n", $csvContent);

        // 指纹列索引
        $fingerprintIndex = -1;

        // 处理头部信息，确定SHA-256指纹所在列
        $headerLine = array_shift($lines);
        $headers = str_getcsv($headerLine, ',', '"', '\\');

        foreach ($headers as $index => $header) {
            if (stripos($header, 'SHA-256 Fingerprint') !== false) {
                $fingerprintIndex = $index;
                break;
            }
        }

        // 如果找不到指纹列，返回失败
        if ($fingerprintIndex === -1) {
            return false;
        }

        // 解析每一行，提取指纹
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // 按CSV格式解析行
            $columns = str_getcsv($line, ',', '"', '\\');

            // 确保有足够的列
            if (count($columns) > $fingerprintIndex) {
                $fingerprint = trim($columns[$fingerprintIndex]);
                if (!empty($fingerprint)) {
                    // 转为小写并去除冒号和空格
                    $fingerprint = strtolower(str_replace([':', ' '], '', $fingerprint));
                    $this->rootFingerprints[] = $fingerprint;
                }
            }
        }

        return !empty($this->rootFingerprints);
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
