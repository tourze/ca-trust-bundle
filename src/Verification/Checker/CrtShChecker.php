<?php

namespace Tourze\CATrustBundle\Verification\Checker;

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

    public function verify(SslCertificate $certificate): VerificationStatus
    {
        try {
            $fingerprint = $certificate->getFingerprint();
            if (empty($fingerprint)) {
                return VerificationStatus::UNCERTAIN;
            }

            $client = $this->createHttpClient();
            $response = $client->request('GET', $this->apiEndpoint, [
                'query' => [
                    'q' => $fingerprint,
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                return VerificationStatus::UNCERTAIN;
            }

            $data = $response->getContent();

            if (str_contains($data, 'crt.sh ID')) {
                return VerificationStatus::PASSED;
            }
            return VerificationStatus::FAILED;
        } catch (ExceptionInterface $e) {
            // 请求异常，返回存疑
            return VerificationStatus::UNCERTAIN;
        } catch (\Exception $e) {
            // 其他异常，返回存疑
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
