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

    protected function setUp(): void
    {
        $application = new Application();
        $command = new ListSystemCertsCommand();
        
        $application->add($command);
        $this->commandTester = new CommandTester($command);
    }

    /**
     * 测试命令基本执行功能
     */
    public function testExecuteBasic(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if (!$caPath || !file_exists($caPath)) {
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
        if (!$caPath || !file_exists($caPath)) {
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
     * 测试JSON输出格式
     */
    public function testJsonOutput(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if (!$caPath || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 执行命令并指定JSON输出格式
        $this->commandTester->execute([
            '--format' => 'json',
        ]);

        // 验证输出是否为有效的JSON
        $output = $this->commandTester->getDisplay();
        $this->assertJson(trim(preg_replace('/^.*?\[/s', '[', $output)));
    }
    
    /**
     * 测试证书验证功能（不进行实际的远程验证）
     */
    public function testVerifyCertificates(): void
    {
        // 确保系统根证书存在
        $caPath = CaBundle::getSystemCaRootBundlePath();
        if (!$caPath || !file_exists($caPath)) {
            $this->markTestSkipped('系统根证书文件不存在，跳过测试');
        }

        // 使用 -v 选项执行命令
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
} 