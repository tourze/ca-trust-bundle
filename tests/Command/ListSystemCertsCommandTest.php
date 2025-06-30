<?php

namespace Tourze\CATrustBundle\Tests\Command;

use Composer\CaBundle\CaBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CATrustBundle\Command\ListSystemCertsCommand;

class ListSystemCertsCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private ListSystemCertsCommand $command;

    protected function setUp(): void
    {
        $application = new Application();
        $this->command = new ListSystemCertsCommand();
        
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * 测试命令基本执行功能
     */
    public function testExecuteBasic(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if ($caPath === null || $caPath === '' || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 执行命令
        $this->commandTester->execute([]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('系统根证书列表', $output);
        $this->assertStringContainsString('证书存储位置', $output);
        $this->assertStringContainsString('共找到', $output);
    }

    /**
     * 测试带关键词的搜索功能
     */
    public function testSearchWithKeyword(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if ($caPath === null || $caPath === '' || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 执行带关键词的命令
        $this->commandTester->execute([
            '--keyword' => 'Root',
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
    }

    /**
     * 测试带签名算法过滤的搜索功能
     */
    public function testSearchWithSignature(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if ($caPath === null || $caPath === '' || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 执行带签名算法过滤的命令
        $this->commandTester->execute([
            '--signature' => 'SHA256',
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
    }

    /**
     * 测试显示过期证书功能
     */
    public function testShowExpiredCertificates(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if ($caPath === null || $caPath === '' || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 执行命令并显示过期证书
        $this->commandTester->execute([
            '--show-expired' => true,
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
    }

    /**
     * 测试JSON输出格式
     */
    public function testJsonOutput(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if ($caPath === null || $caPath === '' || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 执行命令并指定JSON输出格式
        $this->commandTester->execute([
            '--format' => 'json',
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        
        // 提取JSON部分
        if (preg_match('/\[\s*{.*}\s*\]/s', $output, $matches)) {
            $jsonPart = $matches[0];
            $this->assertJson($jsonPart);
        } else {
            $this->assertTrue(false, '无法从输出中提取JSON');
        }
    }
    
    /**
     * 测试组合选项的使用
     */
    public function testCombinedOptions(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if ($caPath === null || $caPath === '' || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 执行命令并使用多个组合选项
        $this->commandTester->execute([
            '--keyword' => 'Root',
            '--signature' => 'SHA256',
            '--show-expired' => true,
            '--format' => 'json',
        ]);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        
        // 提取JSON部分
        if (preg_match('/\[\s*{.*}\s*\]/s', $output, $matches)) {
            $jsonPart = $matches[0];
            $this->assertJson($jsonPart);
        } else {
            // 如果没有找到JSON格式，可能是因为没有匹配的证书
            $this->assertStringContainsString('未找到匹配的证书', $output);
        }
    }
    
    /**
     * 测试证书验证功能（不进行实际的远程验证）
     */
    public function testVerifyCertificates(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if ($caPath === null || $caPath === '' || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 使用 --verify 选项执行命令
        $this->commandTester->execute([
            '--verify' => true,
            // 限制处理的证书数量，避免测试过长
            '--keyword' => 'DigiCert', 
        ]);

        // 验证输出中包含验证相关的信息
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('过滤后剩余', $output);
        
        // 注意：由于这是集成测试，我们不验证具体的验证结果
        // 只确保命令能够正常执行且不抛出异常
    }
    
    /**
     * 测试空匹配结果场景
     */
    public function testEmptyMatchResults(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if ($caPath === null || $caPath === '' || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 执行命令并使用一个不太可能匹配的关键词
        $this->commandTester->execute([
            '--keyword' => 'ThisIsAVeryUnlikelyKeywordToMatchAnyCertificate_' . uniqid(),
        ]);

        // 验证输出中包含警告信息
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('未找到匹配的证书', $output);
    }
} 