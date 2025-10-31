<?php

namespace Tourze\CATrustBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\Console\Output\BufferedOutput;
use Tourze\CATrustBundle\Command\CertificateJsonFormatter;
use Tourze\CATrustBundle\Verification\VerificationStatus;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CertificateJsonFormatter::class)]
#[RunTestsInSeparateProcesses]
final class CertificateJsonFormatterTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 这个测试类不需要特殊的设置
    }

    private function createFormatter(): CertificateJsonFormatter
    {
        return self::getService(CertificateJsonFormatter::class);
    }

    public function testFormatWithoutVerification(): void
    {
        $pemCert = '-----BEGIN CERTIFICATE-----
MIIDQTCCAimgAwIBAgITBmyfz5m/jAo54vB4ikPmljZbyjANBgkqhkiG9w0BAQsF
ADA5MQswCQYDVQQGEwJVUzEPMA0GA1UEChMGQW1hem9uMRkwFwYDVQQDExBBbWF6
b24gUm9vdCBDQSAxMB4XDTE1MDUyNjAwMDAwMFoXDTM4MDExNzAwMDAwMFowOTEL
MAkGA1UEBhMCVVMxDzANBgNVBAoTBkFtYXpvbjEZMBcGA1UEAxMQQW1hem9uIFJv
b3QgQ0EgMTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALJ4gHHKeNXj
ca9HgFB0fW7Y14h29Jlo91ghYPl0hAEvrAIthtOgQ3pOsqTQNroBvo3bSMgHFzZM
9O6II8c+6zf1tRn4SWiw3te5djgdYZ6k/oI2peVKVuRF4fn9tBb6dNqcmzU5L/qw
IFAGbHrQgLKm+a/sRxmPUDgH3KKHOVj4utWp+UhnMJbulHheb4mjUcAwhmahRWa6
VOujw5H5SNz/0egwLX0tdHA114gk957EWW67c4cX8jJGKLhD+rcdqsq08p8kDi1L
93FcXmn/6pUCyziKrlA4b9v7LWIbxcceVOF34GfID5yHI9Y/QCB/IIDEgEw+OyQm
jgSubJrIqg0CAwEAAaNCMEAwDwYDVR0TAQH/BAUwAwEB/zAOBgNVHQ8BAf8EBAMC
AYYwHQYDVR0OBBYEFIQYzIU07LwMlJQuCFmcx7IQTgoIMA0GCSqGSIb3DQEBCwUA
A4IBAQCY8jdaQZChGsV2USggNiMOruYou6r4lK5IpDB/G/wkjUu0yKGX9rbxenDI
U5PMCCjjmCXPI6T53iHTfIuJruydjsw2hUwsqdmHyLYG1YqGTmV3OJhzpH+kqhF1
S2U1F3+gC7d9mXw5Tv9yOBzebhvgNr6jk+oVCb3B1vT6nZc3LhqvRr4IPyJ5LW2b
W9LdNAL6q6GmwuF0y7h6TqQV8qR2t6w2l3HZmGzEAO0s2TgfNm1AO3nYAV+2cCpw
rzBAu6g5C+3s9oc8OBlXYCcPXQ73X5o7+HuQOGtcQrE1OMNDYMaDBGOPZNCtCBhT
YpLTRBe7q1jHjrYhayiQfJ9dSlbo
-----END CERTIFICATE-----';

        $cert = SslCertificate::createFromString($pemCert);
        $output = new BufferedOutput();

        $formatter = $this->createFormatter();
        $formatter->format([$cert], $output);

        $content = $output->fetch();
        $json = json_decode($content, true);

        $this->assertIsArray($json);
        $this->assertCount(1, $json);
        $this->assertArrayHasKey('organization', $json[0]);
        $this->assertArrayHasKey('issuer', $json[0]);
        $this->assertArrayHasKey('fingerprint', $json[0]);
    }

    public function testFormatWithVerification(): void
    {
        $pemCert = '-----BEGIN CERTIFICATE-----
MIIDQTCCAimgAwIBAgITBmyfz5m/jAo54vB4ikPmljZbyjANBgkqhkiG9w0BAQsF
ADA5MQswCQYDVQQGEwJVUzEPMA0GA1UEChMGQW1hem9uMRkwFwYDVQQDExBBbWF6
b24gUm9vdCBDQSAxMB4XDTE1MDUyNjAwMDAwMFoXDTM4MDExNzAwMDAwMFowOTEL
MAkGA1UEBhMCVVMxDzANBgNVBAoTBkFtYXpvbjEZMBcGA1UEAxMQQW1hem9uIFJv
b3QgQ0EgMTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALJ4gHHKeNXj
ca9HgFB0fW7Y14h29Jlo91ghYPl0hAEvrAIthtOgQ3pOsqTQNroBvo3bSMgHFzZM
9O6II8c+6zf1tRn4SWiw3te5djgdYZ6k/oI2peVKVuRF4fn9tBb6dNqcmzU5L/qw
IFAGbHrQgLKm+a/sRxmPUDgH3KKHOVj4utWp+UhnMJbulHheb4mjUcAwhmahRWa6
VOujw5H5SNz/0egwLX0tdHA114gk957EWW67c4cX8jJGKLhD+rcdqsq08p8kDi1L
93FcXmn/6pUCyziKrlA4b9v7LWIbxcceVOF34GfID5yHI9Y/QCB/IIDEgEw+OyQm
jgSubJrIqg0CAwEAAaNCMEAwDwYDVR0TAQH/BAUwAwEB/zAOBgNVHQ8BAf8EBAMC
AYYwHQYDVR0OBBYEFIQYzIU07LwMlJQuCFmcx7IQTgoIMA0GCSqGSIb3DQEBCwUA
A4IBAQCY8jdaQZChGsV2USggNiMOruYou6r4lK5IpDB/G/wkjUu0yKGX9rbxenDI
U5PMCCjjmCXPI6T53iHTfIuJruydjsw2hUwsqdmHyLYG1YqGTmV3OJhzpH+kqhF1
S2U1F3+gC7d9mXw5Tv9yOBzebhvgNr6jk+oVCb3B1vT6nZc3LhqvRr4IPyJ5LW2b
W9LdNAL6q6GmwuF0y7h6TqQV8qR2t6w2l3HZmGzEAO0s2TgfNm1AO3nYAV+2cCpw
rzBAu6g5C+3s9oc8OBlXYCcPXQ73X5o7+HuQOGtcQrE1OMNDYMaDBGOPZNCtCBhT
YpLTRBe7q1jHjrYhayiQfJ9dSlbo
-----END CERTIFICATE-----';

        $cert = SslCertificate::createFromString($pemCert);
        $output = new BufferedOutput();

        $verificationResults = [
            $cert->getFingerprint() => [
                'TestChecker' => VerificationStatus::PASSED,
            ],
        ];

        $formatter = $this->createFormatter();
        $formatter->format([$cert], $output, $verificationResults);

        $content = $output->fetch();
        $json = json_decode($content, true);

        $this->assertIsArray($json);
        $this->assertCount(1, $json);
        $this->assertArrayHasKey('verification', $json[0]);
        $this->assertArrayHasKey('TestChecker', $json[0]['verification']);
        $this->assertArrayHasKey('overall', $json[0]['verification']);
    }
}
