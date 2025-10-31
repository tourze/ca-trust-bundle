<?php

namespace Tourze\CATrustBundle\Tests\Command;

use Composer\CaBundle\CaBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CATrustBundle\Command\ListSystemCertsCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(ListSystemCertsCommand::class)]
#[RunTestsInSeparateProcesses]
final class ListSystemCertsCommandTest extends AbstractCommandTestCase
{
    protected function onSetUp(): void
    {
        // 无需额外的初始化逻辑
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(ListSystemCertsCommand::class);

        return new CommandTester($command);
    }

    /**
     * 测试命令基本执行功能
     */
    public function testExecuteBasic(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertNotEmpty($caPath, '系统根证书路径不能为空字符串');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        // 执行命令
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        // 验证命令执行成功
        $this->assertSame(0, $exitCode, '命令应该成功执行');

        // 验证输出
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('系统根证书列表', $output);
        $this->assertStringContainsString('证书存储位置', $output);
        $this->assertStringContainsString('共找到', $output);
    }

    /**
     * 测试 keyword 选项
     */
    public function testOptionKeyword(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--keyword' => 'Root']);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
    }

    /**
     * 测试 signature 选项
     */
    public function testOptionSignature(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--signature' => 'SHA256']);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
    }

    /**
     * 测试 format 选项
     */
    public function testOptionFormat(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--format' => 'json']);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();

        // 提取JSON部分
        if (1 === preg_match('/\[\s*{.*}\s*\]/s', $output, $matches)) {
            $jsonPart = $matches[0];
            $this->assertJson($jsonPart, '输出应该包含有效的JSON格式');
        } else {
            // JSON格式可能为空数组或没有匹配结果
            $this->assertTrue(
                str_contains($output, '未找到匹配的证书') || str_contains($output, '[]'),
                '输出应该包含JSON格式或空结果信息'
            );
        }
    }

    /**
     * 测试 show-expired 选项
     */
    public function testOptionShowExpired(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--show-expired' => true]);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
    }

    /**
     * 测试 verify 选项
     */
    public function testOptionVerify(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            '--verify' => true,
            '--keyword' => 'DigiCert',
        ]);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, '过滤后剩余') || str_contains($output, '未找到匹配'),
            '输出应该包含过滤结果信息'
        );
    }

    /**
     * 测试带关键词的搜索功能
     */
    public function testSearchWithKeyword(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--keyword' => 'Root']);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
    }

    /**
     * 测试带签名算法过滤的搜索功能
     */
    public function testSearchWithSignature(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--signature' => 'SHA256']);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
    }

    /**
     * 测试显示过期证书功能
     */
    public function testShowExpiredCertificates(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--show-expired' => true]);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
    }

    /**
     * 测试JSON输出格式
     */
    public function testJsonOutput(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--format' => 'json']);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();

        // 提取JSON部分
        if (1 === preg_match('/\[\s*{.*}\s*\]/s', $output, $matches)) {
            $jsonPart = $matches[0];
            $this->assertJson($jsonPart, '输出应该包含有效的JSON格式');

            // 进一步验证JSON内容结构
            $decodedJson = json_decode($jsonPart, true);
            $this->assertIsArray($decodedJson, 'JSON应该解析为数组');
            if ([] !== $decodedJson) {
                $this->assertArrayHasKey('organization', $decodedJson[0], '证书应该包含organization字段');
                $this->assertArrayHasKey('issuer', $decodedJson[0], '证书应该包含issuer字段');
                $this->assertArrayHasKey('fingerprint', $decodedJson[0], '证书应该包含fingerprint字段');
            }
        } else {
            self::fail('无法从输出中提取JSON：' . $output);
        }
    }

    /**
     * 测试组合选项的使用
     */
    public function testCombinedOptions(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            '--keyword' => 'Root',
            '--signature' => 'SHA256',
            '--show-expired' => true,
            '--format' => 'json',
        ]);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();

        // 提取JSON部分或验证空结果信息
        if (1 === preg_match('/\[\s*{.*}\s*\]/s', $output, $matches)) {
            $jsonPart = $matches[0];
            $this->assertJson($jsonPart, '输出应该包含有效的JSON格式');
        } else {
            // 如果没有找到JSON格式，可能是因为没有匹配的证书
            $this->assertTrue(
                str_contains($output, '未找到匹配的证书') || str_contains($output, '过滤后剩余'),
                '输出应该包含空结果信息或过滤结果'
            );
        }
    }

    /**
     * 测试证书验证功能（不进行实际的远程验证）
     */
    public function testVerifyCertificates(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            '--verify' => true,
            '--keyword' => 'DigiCert',
        ]);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();
        $this->assertTrue(
            str_contains($output, '过滤后剩余') || str_contains($output, '未找到匹配'),
            '输出应该包含过滤结果信息'
        );
    }

    /**
     * 测试空匹配结果场景
     */
    public function testEmptyMatchResults(): void
    {
        $caPath = CaBundle::getSystemCaRootBundlePath();
        $this->assertNotNull($caPath, '系统根证书路径不能为空');
        $this->assertFileExists($caPath, '系统根证书文件必须存在');

        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            '--keyword' => 'ThisIsAVeryUnlikelyKeywordToMatchAnyCertificate_' . uniqid(),
        ]);

        $this->assertSame(0, $exitCode, '命令应该成功执行');
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('未找到匹配的证书', $output);
    }
}
