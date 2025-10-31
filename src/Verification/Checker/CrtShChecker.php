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
 * crt.sh证书验证器
 *
 * 通过crt.sh API验证证书指纹是否存在于公共CT日志中
 */
class CrtShChecker implements CheckerInterface
{
    /**
     * @var string API终端
     */
    private string $apiEndpoint = 'https://crt.sh/';

    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function verify(SslCertificate $certificate): VerificationStatus
    {
        $fingerprint = $certificate->getFingerprint();
        $startTime = microtime(true);

        try {
            if ('' === $fingerprint) {
                $this->logger->info('Certificate fingerprint is empty', [
                    'domain' => $certificate->getDomain(),
                    'issuer' => $certificate->getIssuer(),
                ]);

                return VerificationStatus::UNCERTAIN;
            }

            $this->logger->info('Starting crt.sh verification', [
                'fingerprint' => $fingerprint,
                'domain' => $certificate->getDomain(),
            ]);

            $client = $this->createHttpClient();

            // 审计日志：记录 HTTP 请求详细信息
            $requestQuery = ['q' => $fingerprint];
            $this->logger->info('crt.sh HTTP request started', [
                'method' => 'GET',
                'url' => $this->apiEndpoint,
                'query' => $requestQuery,
                'fingerprint' => $fingerprint,
            ]);

            // @audit-logged HTTP request with comprehensive logging before and after
            $response = $client->request('GET', $this->apiEndpoint, [
                'query' => $requestQuery,
            ]);

            $statusCode = $response->getStatusCode();
            $responseTime = microtime(true) - $startTime;

            // 审计日志：记录 HTTP 响应结果
            $this->logger->info('crt.sh HTTP response received', [
                'status_code' => $statusCode,
                'response_time' => $responseTime,
                'fingerprint' => $fingerprint,
            ]);

            if (200 !== $statusCode) {
                $this->logger->warning('crt.sh API returned non-200 status code', [
                    'status_code' => $statusCode,
                    'fingerprint' => $fingerprint,
                    'response_time' => $responseTime,
                ]);

                return VerificationStatus::UNCERTAIN;
            }

            $data = $response->getContent();
            $found = str_contains($data, 'crt.sh ID');
            $status = $found ? VerificationStatus::PASSED : VerificationStatus::FAILED;

            $this->logger->info('crt.sh verification completed', [
                'fingerprint' => $fingerprint,
                'status' => $status->value,
                'found' => $found,
                'response_time' => $responseTime,
                'response_size' => strlen($data),
            ]);

            return $status;
        } catch (ExceptionInterface $e) {
            $responseTime = microtime(true) - $startTime;
            $this->logger->error('crt.sh API request failed', [
                'fingerprint' => $fingerprint,
                'error' => $e->getMessage(),
                'response_time' => $responseTime,
                'exception_class' => get_class($e),
            ]);

            return VerificationStatus::UNCERTAIN;
        } catch (\Throwable $e) {
            $responseTime = microtime(true) - $startTime;
            $this->logger->error('Unexpected error during crt.sh verification', [
                'fingerprint' => $fingerprint,
                'error' => $e->getMessage(),
                'response_time' => $responseTime,
                'exception_class' => get_class($e),
            ]);

            return VerificationStatus::UNCERTAIN;
        }
    }

    public function getName(): string
    {
        return 'crt.sh';
    }

    /**
     * 创建HTTP客户端，方便测试时进行模拟
     */
    protected function createHttpClient(): HttpClientInterface
    {
        return HttpClient::create();
    }
}
