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

A Symfony bundle for inspecting and verifying system root CA certificates to ensure security and 
trustworthiness.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Usage](#usage)
  - [Basic Commands](#basic-commands)
  - [Output Format](#output-format)
- [Certificate Verification](#certificate-verification)
  - [Verification Services](#verification-services)
  - [Verification Results](#verification-results)
- [Advanced Usage](#advanced-usage)
  - [Custom Verification Services](#custom-verification-services)
  - [Integration with Logging](#integration-with-logging)
  - [Performance Considerations](#performance-considerations)
- [Dependencies](#dependencies)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Features

- List and inspect system root CA certificates
- Filter certificates by keyword, signature algorithm, or expiration status
- Verify certificate trustworthiness against multiple online services
- Output in both table and JSON formats
- Comprehensive certificate details including fingerprint, issuer, and validity dates

## Requirements

- PHP 8.3 or higher
- OpenSSL extension
- Symfony 7.3 or higher

## Installation

```bash
composer require tourze/ca-trust-bundle
```

## Configuration

This bundle works out of the box without additional configuration. The bundle automatically 
registers its services when installed.

## Quick Start

After installation, you can immediately start using the bundle:

```bash
# Register the bundle in your bundles.php (auto-configured in most cases)
# Add to config/bundles.php:
# Tourze\CATrustBundle\CATrustBundle::class => ['all' => true],

# List all system certificates
bin/console ca-trust:list-certs

# Search for specific certificates
bin/console ca-trust:list-certs --keyword="DigiCert"

# Verify certificate trustworthiness
bin/console ca-trust:list-certs --verify
```

## Usage

### Basic Commands

```bash
# List all certificates
bin/console ca-trust:list-certs

# Search certificates by keyword (matches organization, issuer, or domain)
bin/console ca-trust:list-certs --keyword="DigiCert"
bin/console ca-trust:list-certs -k "DigiCert"

# Filter by signature algorithm
bin/console ca-trust:list-certs --signature="SHA256"
bin/console ca-trust:list-certs -s "SHA256"

# Show expired certificates
bin/console ca-trust:list-certs --show-expired

# Output in JSON format
bin/console ca-trust:list-certs --format=json
bin/console ca-trust:list-certs -f json

# Verify certificate trustworthiness
bin/console ca-trust:list-certs --verify
bin/console ca-trust:list-certs -c

# Combine multiple options
bin/console ca-trust:list-certs -k "DigiCert" -s "SHA256" --show-expired -c -f json
```

### Output Format

#### Table Output

The table output includes the following columns:
- `#`: Index number
- `Organization`: Certificate organization
- `Issuer`: Certificate issuer
- `Domain`: Primary domain
- `Signature`: Certificate fingerprint
- `Valid From`: Certificate validity start date
- `Valid Until`: Certificate expiration date
- `Signature Algorithm`: Algorithm used for signing

When using the `--verify` option, additional columns appear:
- Verification results from each service (Pass/Fail/Unknown)
- Overall verification status

#### JSON Output

JSON output provides more detailed information including:
- Complete fingerprint
- Full domain list
- Verification results (when using `--verify`)
- Additional certificate metadata

## Certificate Verification

When using the `--verify` option, the system verifies certificate trustworthiness through 
multiple online services:

### Verification Services

1. **crt.sh** - Checks if the certificate exists in public Certificate Transparency (CT) logs
2. **Mozilla** - Verifies if the certificate is in Mozilla's trusted root certificate list

### Verification Results

- **Pass** - Certificate is verified and considered trustworthy
- **Fail** - Certificate failed verification and may not be trustworthy
- **Unknown** - Unable to determine certificate status due to connection issues or API limitations

## Advanced Usage

### Custom Verification Services

You can extend the bundle by creating custom certificate verification services. 
Implement the `CheckerInterface` and register your service:

```php
use Tourze\CATrustBundle\Verification\CheckerInterface;
use Tourze\CATrustBundle\Verification\VerificationStatus;

class CustomChecker implements CheckerInterface 
{
    public function verify(SslCertificate $certificate): VerificationStatus
    {
        // Your verification logic here
        return VerificationStatus::PASSED;
    }
    
    public function getName(): string
    {
        return 'Custom';
    }
}
```

### Integration with Logging

The bundle supports PSR-3 compatible logging for audit trails:

```php
use Tourze\CATrustBundle\Verification\Checker\CrtShChecker;
use Psr\Log\LoggerInterface;

$logger = // your logger instance
$checker = new CrtShChecker($logger);
```

### Performance Considerations

- Verification requests are made synchronously and may take time
- Consider caching verification results for frequently checked certificates
- Network timeouts may affect verification accuracy

## Dependencies

This bundle relies on the following key libraries:
- `composer/ca-bundle`: For retrieving system CA root certificates
- `spatie/ssl-certificate`: For parsing and processing SSL certificates
- `symfony/console`: For creating command-line tools
- `symfony/http-client`: For interacting with online verification services

## Testing

```bash
# Run tests
./vendor/bin/phpunit packages/ca-trust-bundle/tests

# Run static analysis
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/ca-trust-bundle
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to this project.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.