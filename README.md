# CA Trust Bundle

这个Symfony包用于检查和管理系统根CA证书。

## 安装

```bash
composer require tourze/ca-trust-bundle
```

## 功能

### 列出系统根证书

该命令可以列出系统中的根证书，并提供多种过滤和搜索选项。

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

## 输出说明

表格输出包含以下列：
- `#`: 序号
- `组织`: 证书所属组织
- `颁发者`: 证书颁发者
- `域名`: 主域名
- `签名`: 证书指纹
- `生效日期`: 证书开始生效日期
- `过期日期`: 证书过期日期
- `签名算法`: 使用的签名算法

当使用`--verify`选项时，还会包含以下列：
- 各验证服务的验证结果（通过/失败/存疑）
- 综合验证结果

JSON输出包含更详细的信息，包括指纹和完整的域名列表，以及验证结果（如果使用了`--verify`选项）。

## 证书验证

当使用`--verify`选项时，系统会通过多个在线服务来验证证书是否可信：

1. **crt.sh** - 检查证书是否在公共CT（证书透明度）日志中
2. **Mozilla** - 检查证书是否在Mozilla根证书列表中
3. **SSLMate** - 使用SSLMate的Cert Spotter API验证证书

验证结果可能为：
- **通过** - 证书经过验证，被认为是可信的
- **失败** - 证书未通过验证，可能不可信
- **存疑** - 由于连接问题或API限制，无法确定证书状态

## 技术说明

该包使用了以下库：
- `composer/ca-bundle`: 用于获取系统CA根证书
- `spatie/ssl-certificate`: 用于解析和处理SSL证书
- `symfony/console`: 用于创建命令行工具
- `symfony/http-client`: 用于与在线验证服务交互
