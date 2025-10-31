# CA Trust Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/ca-trust-bundle.svg?style=flat-square)](
https://packagist.org/packages/tourze/ca-trust-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/ca-trust-bundle.svg?style=flat-square)](
https://packagist.org/packages/tourze/ca-trust-bundle)
[![License](https://img.shields.io/packagist/l/tourze/ca-trust-bundle.svg?style=flat-square)](
https://packagist.org/packages/tourze/ca-trust-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/test.yml?branch=master&style=flat-square)](
https://github.com/tourze/php-monorepo/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/ca-trust-bundle.svg?style=flat-square)](
https://scrutinizer-ci.com/g/tourze/ca-trust-bundle)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)](
https://codecov.io/gh/tourze/php-monorepo)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/ca-trust-bundle.svg?style=flat-square)](
https://packagist.org/packages/tourze/ca-trust-bundle)

用于检查和验证系统根 CA 证书安全性和可信度的 Symfony 包。

## 目录

- [功能特性](#功能特性)
- [系统要求](#系统要求)
- [安装](#安装)
- [配置](#配置)
- [快速开始](#快速开始)
- [使用方法](#使用方法)
  - [基本命令](#基本命令)
  - [输出格式](#输出格式)
- [证书验证](#证书验证)
  - [验证服务](#验证服务)
  - [验证结果](#验证结果)
- [高级用法](#高级用法)
  - [自定义验证服务](#自定义验证服务)
  - [日志集成](#日志集成)
  - [性能考虑](#性能考虑)
- [依赖项](#依赖项)
- [测试](#测试)
- [贡献](#贡献)
- [许可证](#许可证)

## 功能特性

- 列出并检查系统根 CA 证书
- 通过关键词、签名算法或过期状态过滤证书
- 通过多个在线服务验证证书可信度
- 支持表格和 JSON 两种输出格式
- 提供证书的完整详情，包括指纹、颁发者和有效期

## 系统要求

- PHP 8.1 或更高版本
- OpenSSL 扩展
- Symfony 6.4 或更高版本

## 安装

```bash
composer require tourze/ca-trust-bundle
```

## 配置

该包开箱即用，无需额外配置。安装后会自动注册相关服务。

## 快速开始

安装后，您可以立即开始使用该包：

```bash
# 注册该包到您的 bundles.php 中（大多数情况下自动配置）
# 在 config/bundles.php 中添加：
# Tourze\CATrustBundle\CATrustBundle::class => ['all' => true],

# 列出所有系统证书
bin/console ca-trust:list-certs

# 搜索特定证书
bin/console ca-trust:list-certs --keyword="DigiCert"

# 验证证书可信度
bin/console ca-trust:list-certs --verify
```

## 使用方法

### 基本命令

```bash
# 列出所有证书
bin/console ca-trust:list-certs

# 按关键词搜索证书（匹配组织名、颁发者或域名）
bin/console ca-trust:list-certs --keyword="DigiCert"
bin/console ca-trust:list-certs -k "DigiCert"

# 按签名算法搜索证书
bin/console ca-trust:list-certs --signature="SHA256"
bin/console ca-trust:list-certs -s "SHA256"

# 显示已过期的证书
bin/console ca-trust:list-certs --show-expired

# 以JSON格式输出
bin/console ca-trust:list-certs --format=json
bin/console ca-trust:list-certs -f json

# 验证证书是否可信
bin/console ca-trust:list-certs --verify
bin/console ca-trust:list-certs -v

# 组合使用
bin/console ca-trust:list-certs -k "DigiCert" -s "SHA256" --show-expired -v -f json
```

### 输出格式

#### 表格输出

表格输出包含以下列：
- `#`: 序号
- `组织`: 证书所属组织
- `颁发者`: 证书颁发者
- `域名`: 主域名
- `签名`: 证书指纹
- `生效日期`: 证书开始生效日期
- `过期日期`: 证书过期日期
- `签名算法`: 使用的签名算法

当使用 `--verify` 选项时，还会包含以下列：
- 各验证服务的验证结果（通过/失败/存疑）
- 综合验证结果

#### JSON 输出

JSON 输出包含更详细的信息：
- 完整的证书指纹
- 完整的域名列表
- 验证结果（使用 `--verify` 时）
- 额外的证书元数据

## 证书验证

当使用 `--verify` 选项时，系统会通过多个在线服务来验证证书是否可信：

### 验证服务

1. **crt.sh** - 检查证书是否在公共 CT（证书透明度）日志中
2. **Mozilla** - 检查证书是否在 Mozilla 根证书列表中

### 验证结果

- **通过** - 证书经过验证，被认为是可信的
- **失败** - 证书未通过验证，可能不可信
- **存疑** - 由于连接问题或 API 限制，无法确定证书状态

## 高级用法

### 自定义验证服务

您可以通过创建自定义证书验证服务来扩展该包。实现 `CheckerInterface` 接口并注册您的服务：

```php
use Tourze\CATrustBundle\Verification\CheckerInterface;
use Tourze\CATrustBundle\Verification\VerificationStatus;

class CustomChecker implements CheckerInterface 
{
    public function verify(SslCertificate $certificate): VerificationStatus
    {
        // 您的验证逻辑
        return VerificationStatus::PASSED;
    }
    
    public function getName(): string
    {
        return 'Custom';
    }
}
```

### 日志集成

该包支持符合 PSR-3 标准的日志记录，用于审计追踪：

```php
use Tourze\CATrustBundle\Verification\Checker\CrtShChecker;
use Psr\Log\LoggerInterface;

$logger = // 您的日志实例
$checker = new CrtShChecker($logger);
```

### 性能考虑

- 验证请求是同步的，可能需要一定时间
- 考虑为经常检查的证书缓存验证结果
- 网络超时可能影响验证准确性

## 依赖项

该包依赖以下主要库：
- `composer/ca-bundle`: 用于获取系统 CA 根证书
- `spatie/ssl-certificate`: 用于解析和处理 SSL 证书
- `symfony/console`: 用于创建命令行工具
- `symfony/http-client`: 用于与在线验证服务交互

## 测试

```bash
# 运行测试
./vendor/bin/phpunit packages/ca-trust-bundle/tests

# 运行静态分析
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/ca-trust-bundle
```

## 贡献

请参阅 [CONTRIBUTING.md](CONTRIBUTING.md) 了解如何为此项目做贡献的详细信息。

## 许可证

MIT 许可证。详见 [LICENSE](LICENSE) 文件。