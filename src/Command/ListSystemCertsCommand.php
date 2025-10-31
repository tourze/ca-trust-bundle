<?php

namespace Tourze\CATrustBundle\Command;

use Composer\CaBundle\CaBundle;
use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\CATrustBundle\Verification\Checker\CrtShChecker;
use Tourze\CATrustBundle\Verification\Checker\MozillaChecker;

#[AsCommand(
    name: self::NAME,
    description: '列出系统根证书，支持关键词和签名搜索'
)]
class ListSystemCertsCommand extends Command
{
    public const NAME = 'ca-trust:list-certs';

    private CertificateFilter $filter;

    private CertificateVerifier $verifier;

    private CertificateTableFormatter $tableFormatter;

    private CertificateJsonFormatter $jsonFormatter;

    public function __construct()
    {
        parent::__construct();

        $checkers = [
            new CrtShChecker(),
            new MozillaChecker(),
        ];

        $this->filter = new CertificateFilter();
        $this->verifier = new CertificateVerifier($checkers);
        $this->tableFormatter = new CertificateTableFormatter($checkers);
        $this->jsonFormatter = new CertificateJsonFormatter();
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
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('系统根证书列表');

        $caPath = $this->getCaPath();
        if (!$this->validateCaPath($caPath, $io)) {
            return Command::FAILURE;
        }

        $certificates = $this->loadCertificates($caPath, $io);
        if (null === $certificates) {
            return Command::FAILURE;
        }

        $filteredCerts = $this->filterCertificates($certificates, $input, $io);
        if ([] === $filteredCerts) {
            $io->warning('未找到匹配的证书');

            return Command::SUCCESS;
        }

        $this->outputCertificates($input, $output, $filteredCerts);

        return Command::SUCCESS;
    }

    /**
     * 获取系统CA证书路径，方便测试时进行模拟
     */
    protected function getCaPath(): string
    {
        return CaBundle::getSystemCaRootBundlePath();
    }

    private function validateCaPath(string $caPath, SymfonyStyle $io): bool
    {
        if ('' === $caPath || !file_exists($caPath)) {
            $io->error('无法找到系统根证书文件');

            return false;
        }

        $io->text(sprintf('证书存储位置: %s', $caPath));

        return true;
    }

    /**
     * @return array<string>|null
     */
    private function loadCertificates(string $caPath, SymfonyStyle $io): ?array
    {
        $pemContents = file_get_contents($caPath);
        if (false === $pemContents) {
            $io->error('无法读取证书文件');

            return null;
        }

        $certificates = $this->parseCertificates($pemContents);
        $io->text(sprintf('共找到 %d 个证书', count($certificates)));

        return $certificates;
    }

    /**
     * @param array<string> $certificates
     * @return array<SslCertificate>
     */
    private function filterCertificates(array $certificates, InputInterface $input, SymfonyStyle $io): array
    {
        $keyword = $input->getOption('keyword');
        $signature = $input->getOption('signature');
        $showExpired = $input->getOption('show-expired');

        $filteredCerts = $this->filter->filter($certificates, $keyword, $signature, $showExpired);
        $io->text(sprintf('过滤后剩余 %d 个证书', count($filteredCerts)));

        return $filteredCerts;
    }

    /**
     * @param array<SslCertificate> $certificates
     */
    private function outputCertificates(InputInterface $input, OutputInterface $output, array $certificates): void
    {
        $format = $input->getOption('format');
        $verify = $input->getOption('verify');

        if (true === $verify && $output instanceof ConsoleOutputInterface) {
            $this->outputWithVerification($output, $certificates, $format);
        } else {
            $this->outputWithoutVerification($output, $certificates, $format);
        }
    }

    /**
     * @param array<SslCertificate> $certificates
     */
    private function outputWithVerification(ConsoleOutputInterface $output, array $certificates, string $format): void
    {
        $progressSection = $output->section();
        $tableSection = $output->section();

        $verificationResults = $this->verifier->verifyWithProgress(
            $certificates,
            $progressSection,
            'table' === $format ? $tableSection : null
        );

        if ('json' === $format) {
            $this->jsonFormatter->format($certificates, $tableSection, $verificationResults);
        }
    }

    /**
     * @param array<SslCertificate> $certificates
     */
    private function outputWithoutVerification(OutputInterface $output, array $certificates, string $format): void
    {
        if ('json' === $format) {
            $this->jsonFormatter->format($certificates, $output);
        } else {
            $io = new SymfonyStyle(new ArrayInput([]), $output);
            $this->tableFormatter->format($certificates, $io);
        }
    }

    /**
     * 解析PEM格式文件中的多个证书
     * @return array<string>
     */
    private function parseCertificates(string $pemContents): array
    {
        $pattern = '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s';
        preg_match_all($pattern, $pemContents, $matches);

        return $matches[0];
    }
}
