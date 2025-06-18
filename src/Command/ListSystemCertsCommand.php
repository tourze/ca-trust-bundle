<?php

namespace Tourze\CATrustBundle\Command;

use Composer\CaBundle\CaBundle;
use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\CATrustBundle\Verification\Checker\CrtShChecker;
use Tourze\CATrustBundle\Verification\Checker\MozillaChecker;
use Tourze\CATrustBundle\Verification\CheckerInterface;
use Tourze\CATrustBundle\Verification\VerificationStatus;

#[AsCommand(
    name: self::NAME,
    description: '列出系统根证书，支持关键词和签名搜索'
)]
class ListSystemCertsCommand extends Command
{
    public const NAME = 'ca-trust:list-certs';
    /**
     * @var CheckerInterface[] 证书验证器列表
     */
    private array $checkers;

    public function __construct()
    {
        parent::__construct();

        // 初始化验证器
        $this->checkers = [
            new CrtShChecker(),
            new MozillaChecker(),
            // Censys验证器需要API凭证，这里暂不添加
        ];
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'keyword',
                'k',
                InputOption::VALUE_OPTIONAL,
                '按关键词搜索证书（匹配主题或颁发者）'
            )
            ->addOption(
                'signature',
                's',
                InputOption::VALUE_OPTIONAL,
                '按签名算法搜索证书'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                '输出格式（table或json）',
                'table'
            )
            ->addOption(
                'show-expired',
                null,
                InputOption::VALUE_NONE,
                '显示已过期的证书'
            )
            ->addOption(
                'verify',
                'c',
                InputOption::VALUE_NONE,
                '验证证书是否可信'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('系统根证书列表');

        // 获取系统CA证书路径
        $caPath = $this->getCaPath();

        if ($caPath === '' || !file_exists($caPath)) {
            $io->error('无法找到系统根证书文件');
            return Command::FAILURE;
        }

        $io->text(sprintf('证书存储位置: %s', $caPath));

        // 读取PEM格式的证书文件
        $pemContents = file_get_contents($caPath);
        if ($pemContents === false) {
            $io->error('无法读取证书文件');
            return Command::FAILURE;
        }

        // 过滤条件
        $keyword = $input->getOption('keyword');
        $signature = $input->getOption('signature');
        $showExpired = $input->getOption('show-expired');
        $format = $input->getOption('format');
        $verify = $input->getOption('verify');

        // 分割证书
        $certificates = $this->parseCertificates($pemContents);
        $io->text(sprintf('共找到 %d 个证书', count($certificates)));

        $filteredCerts = [];

        foreach ($certificates as $index => $cert) {
            try {
                $sslCert = SslCertificate::createFromString($cert);

                // 过滤已过期证书
                if ($showExpired === false && $sslCert->isExpired()) {
                    continue;
                }

                // 关键词过滤 (主题或颁发者)
                if ($keyword !== null && !$this->matchesKeyword($sslCert, $keyword)) {
                    continue;
                }

                // 签名算法过滤
                if ($signature !== null && !$this->matchesSignature($sslCert, $signature)) {
                    continue;
                }

                $filteredCerts[] = $sslCert;
            } catch (\Throwable $e) {
                // 忽略无法解析的证书
                continue;
            }
        }

        // 输出过滤后的证书数量
        $io->text(sprintf('过滤后剩余 %d 个证书', count($filteredCerts)));

        // 如果没有证书，则直接返回
        if (count($filteredCerts) === 0) {
            $io->warning('未找到匹配的证书');
            return Command::SUCCESS;
        }

        // 如果需要验证证书且有ConsoleOutputInterface
        if ($verify === true && $output instanceof ConsoleOutputInterface) {
            // 获取sections
            $progressSection = $output->section();
            $tableSection = $output->section();
            
            // 显示证书表格
            $this->displayVerificationTable($input, $tableSection, $progressSection, $filteredCerts, $format);
        } else {
            // 如果不需要验证或者不是在控制台环境中，直接输出表格或JSON
            if ($format === 'json') {
                $this->outputJson($filteredCerts, $output, null);
            } else {
                $this->outputTable($filteredCerts, $io, null);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * 获取系统CA证书路径，方便测试时进行模拟
     */
    protected function getCaPath(): string
    {
        return CaBundle::getSystemCaRootBundlePath();
    }

    /**
     * 显示证书验证表格，并逐个验证证书
     */
    private function displayVerificationTable(
        InputInterface $input, 
        ConsoleSectionOutput $tableSection, 
        ConsoleSectionOutput $progressSection,
        array $certificates,
        string $format
    ): void {
        if ($format === 'json') {
            // JSON格式下只在验证完成后一次性输出
            $verificationResults = $this->verifyAllCertificates($progressSection, $certificates);
            $this->outputJson($certificates, $tableSection, $verificationResults);
            return;
        }
        
        // 表格格式下使用appendRow逐行添加验证结果
        
        // 1. 创建表格实例
        $table = new Table($tableSection);
        
        // 2. 设置标题
        $headers = ['#', '组织', '颁发者', '域名', '签名', '生效日期', '过期日期', '签名算法'];
        
        foreach ($this->checkers as $checker) {
            $headers[] = $checker->getName() . ' 验证';
        }
        $headers[] = '综合验证';
        
        $table->setHeaders($headers);
        
        // 3. 初始显示表格，但不包含任何行
        $table->render();
        
        // 4. 逐个验证证书并添加到表格
        $verificationResults = [];
        
        foreach ($certificates as $index => $cert) {
            $certName = $cert->getDomain() ?: $cert->getIssuer() ?: '未知证书';
            
            // 更新进度提示
            $progressSection->overwrite(sprintf(
                '正在验证 [%d/%d] %s', 
                $index + 1, 
                count($certificates), 
                $certName
            ));
            
            // 准备行数据
            $row = [
                ($index + 1),
                $cert->getOrganization() ?: '未知',
                $cert->getIssuer() ?: '未知',
                $cert->getDomain(),
                $cert->getFingerprint(),
                $cert->validFromDate()->format('Y-m-d'),
                $cert->expirationDate()->format('Y-m-d'),
                $cert->getSignatureAlgorithm(),
            ];
            
            // 逐个验证器进行验证
            $certResults = [];
            
            foreach ($this->checkers as $checker) {
                $checkerName = $checker->getName();
                
                $progressSection->overwrite(sprintf(
                    '正在验证 [%d/%d] %s - 使用 %s 验证器', 
                    $index + 1, 
                    count($certificates), 
                    $certName,
                    $checkerName
                ));
                
                // 执行验证
                $status = $checker->verify($cert);
                $certResults[$checkerName] = $status;
                
                // 向行添加验证结果
                $row[] = $this->formatVerificationStatus($status);
                
                // 实时更新进度
                $progressSection->overwrite(sprintf(
                    '正在验证 [%d/%d] %s - %s: %s', 
                    $index + 1, 
                    count($certificates), 
                    $certName,
                    $checkerName,
                    $this->formatVerificationStatus($status)
                ));
            }
            
            // 存储验证结果
            $verificationResults[$cert->getFingerprint()] = $certResults;
            
            // 添加综合验证结果
            $overallStatus = $this->getOverallVerificationStatus($certResults);
            $row[] = $this->formatVerificationStatus($overallStatus);
            
            // 向表格添加一行
            $table->appendRow($row);
            
            // 更新进度显示
            $progressSection->overwrite(sprintf(
                '完成验证 [%d/%d] %s', 
                $index + 1, 
                count($certificates), 
                $certName
            ));
        }
        
        // 5. 验证完成
        $progressSection->overwrite('<info>验证完成!</info>');
    }
    
    /**
     * 验证所有证书并返回结果（用于JSON输出）
     */
    private function verifyAllCertificates(
        ConsoleSectionOutput $progressSection, 
        array $certificates
    ): array {
        $verificationResults = [];
        
        foreach ($certificates as $index => $cert) {
            $certName = $cert->getDomain() ?: $cert->getIssuer() ?: '未知证书';
            
            // 更新进度提示
            $progressSection->overwrite(sprintf(
                '正在验证 [%d/%d] %s', 
                $index + 1, 
                count($certificates), 
                $certName
            ));
            
            $certResults = [];
            
            // 逐个验证器进行验证
            foreach ($this->checkers as $checker) {
                $checkerName = $checker->getName();
                
                $progressSection->overwrite(sprintf(
                    '正在验证 [%d/%d] %s - 使用 %s 验证器', 
                    $index + 1, 
                    count($certificates), 
                    $certName,
                    $checkerName
                ));
                
                // 执行验证
                $status = $checker->verify($cert);
                $certResults[$checkerName] = $status;
                
                // 实时更新进度
                $progressSection->overwrite(sprintf(
                    '正在验证 [%d/%d] %s - %s: %s', 
                    $index + 1, 
                    count($certificates), 
                    $certName,
                    $checkerName,
                    $this->formatVerificationStatus($status)
                ));
            }
            
            // 存储验证结果
            $verificationResults[$cert->getFingerprint()] = $certResults;
            
            // 更新进度显示
            $progressSection->overwrite(sprintf(
                '完成验证 [%d/%d] %s', 
                $index + 1, 
                count($certificates), 
                $certName
            ));
        }
        
        // 验证完成
        $progressSection->overwrite('<info>验证完成!</info>');
        
        return $verificationResults;
    }

    /**
     * 解析PEM格式文件中的多个证书
     */
    private function parseCertificates(string $pemContents): array
    {
        $pattern = '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s';
        preg_match_all($pattern, $pemContents, $matches);

        return $matches[0];
    }

    /**
     * 检查证书是否匹配关键词
     */
    private function matchesKeyword(SslCertificate $cert, string $keyword): bool
    {
        $keyword = strtolower($keyword);

        // 在主题中搜索
        $subject = strtolower($cert->getIssuer());
        if (str_contains($subject, $keyword)) {
            return true;
        }

        // 在颁发者中搜索
        $issuer = strtolower($cert->getOrganization());
        if (str_contains($issuer, $keyword)) {
            return true;
        }

        // 在域名中搜索
        foreach ($cert->getDomains() as $domain) {
            if (str_contains(strtolower($domain), $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查证书是否匹配签名算法
     */
    private function matchesSignature(SslCertificate $cert, string $signature): bool
    {
        // 获取证书的签名算法
        try {
            $signatureAlgorithm = $cert->getSignatureAlgorithm();
            
            if (empty($signatureAlgorithm)) {
                return false;
            }
            
            return stripos($signatureAlgorithm, $signature) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * 获取验证结果的综合状态
     */
    private function getOverallVerificationStatus(array $verificationResults): VerificationStatus
    {
        $hasUncertain = false;
        
        foreach ($verificationResults as $status) {
            if ($status === VerificationStatus::PASSED) {
                return VerificationStatus::PASSED;
            }
            
            if ($status === VerificationStatus::UNCERTAIN) {
                $hasUncertain = true;
            }
        }
        
        return $hasUncertain ? VerificationStatus::UNCERTAIN : VerificationStatus::FAILED;
    }
    
    /**
     * 格式化验证状态为彩色输出
     */
    private function formatVerificationStatus(VerificationStatus $status): string
    {
        return match ($status) {
            VerificationStatus::PASSED => '<fg=green>通过</>',
            VerificationStatus::FAILED => '<fg=red>失败</>',
            VerificationStatus::UNCERTAIN => '<fg=yellow>存疑</>',
        };
    }

    /**
     * 以表格形式输出证书
     *
     * @param array<SslCertificate> $certificates
     * @param SymfonyStyle $io
     * @param array|null $verificationResults
     * @return void
     */
    private function outputTable(array $certificates, SymfonyStyle $io, ?array $verificationResults = null): void
    {
        $rows = [];
        $headers = ['#', '组织', '颁发者', '域名', '签名', '生效日期', '过期日期', '签名算法'];
        
        // 如果有验证结果，添加验证结果列
        if ($verificationResults !== null) {
            foreach ($this->checkers as $checker) {
                $headers[] = $checker->getName() . ' 验证';
            }
            $headers[] = '综合验证';
        }

        // 对于大量证书，如果是在验证过程中更新，只显示进度前后的少量证书
        $limitOutput = false;
        $contextSize = 5; // 前后显示的证书数量
        
        if (count($certificates) > 20 && $verificationResults !== null && count($verificationResults) > 0 && count($verificationResults) < count($certificates)) {
            $limitOutput = true;
            $lastVerifiedIndex = -1;
            
            // 找出最后一个已验证的证书索引
            foreach ($certificates as $i => $cert) {
                if (isset($verificationResults[$cert->getFingerprint()])) {
                    $lastVerifiedIndex = $i;
                }
            }
            
            if ($lastVerifiedIndex >= 0) {
                $startIndex = max(0, $lastVerifiedIndex - $contextSize);
                $endIndex = min(count($certificates) - 1, $lastVerifiedIndex + $contextSize);
                
                // 为有限的显示范围创建表格行
                for ($i = $startIndex; $i <= $endIndex; $i++) {
                    $cert = $certificates[$i];
                    $row = [
                        ($i + 1),
                        $cert->getOrganization() ?: '未知',
                        $cert->getIssuer() ?: '未知',
                        $cert->getDomain(),
                        $cert->getFingerprint(),
                        $cert->validFromDate()->format('Y-m-d'),
                        $cert->expirationDate()->format('Y-m-d'),
                        $cert->getSignatureAlgorithm(),
                    ];
                    
                    // 如果有验证结果，添加验证结果
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
                    
                    $rows[] = $row;
                }
                
                // 添加总进度信息
                $io->table($headers, $rows);
                $io->writeln(sprintf('<info>验证进度: %d/%d</info>', count($verificationResults), count($certificates)));
                return;
            }
        }
        
        // 标准表格输出（所有证书，或仅少量证书的情况）
        foreach ($certificates as $i => $cert) {
            $row = [
                ($i + 1),
                $cert->getOrganization() ?: '未知',
                $cert->getIssuer() ?: '未知',
                $cert->getDomain(),
                $cert->getFingerprint(),
                $cert->validFromDate()->format('Y-m-d'),
                $cert->expirationDate()->format('Y-m-d'),
                $cert->getSignatureAlgorithm(),
            ];
            
            // 如果有验证结果，添加验证结果
            if ($verificationResults !== null && isset($verificationResults[$cert->getFingerprint()])) {
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
            } else if ($verificationResults !== null) {
                // 未验证的证书显示空的验证结果
                foreach ($this->checkers as $checker) {
                    $row[] = '';
                }
                $row[] = '';
            }
            
            $rows[] = $row;
        }

        $io->table($headers, $rows);
        
        // 如果在验证过程中，显示验证进度
        if ($verificationResults !== null && count($verificationResults) < count($certificates)) {
            $io->writeln(sprintf('<info>验证进度: %d/%d</info>', count($verificationResults), count($certificates)));
        }
    }

    /**
     * 以JSON格式输出证书
     */
    private function outputJson(array $certificates, OutputInterface $output, ?array $verificationResults = null): void
    {
        $result = [];

        foreach ($certificates as $cert) {
            $certData = [
                'organization' => $cert->getOrganization() ?: '未知',
                'issuer' => $cert->getIssuer() ?: '未知',
                'domain' => $cert->getDomain(),
                'valid_from' => $cert->validFromDate()->format('Y-m-d'),
                'valid_to' => $cert->expirationDate()->format('Y-m-d'),
                'signature_algorithm' => $cert->getSignatureAlgorithm(),
                'fingerprint' => $cert->getFingerprint(),
                'domains' => $cert->getDomains(),
            ];
            
            // 如果有验证结果，添加到JSON中
            if ($verificationResults !== null && isset($verificationResults[$cert->getFingerprint()])) {
                $certResults = $verificationResults[$cert->getFingerprint()];
                $verificationData = [];
                
                foreach ($certResults as $checkerName => $status) {
                    $verificationData[$checkerName] = $status->value;
                }
                
                // 添加综合验证结果
                $verificationData['overall'] = $this->getOverallVerificationStatus($certResults)->value;
                
                $certData['verification'] = $verificationData;
            }
            
            $result[] = $certData;
        }

        $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
