# WenYinOS 禅道项目管理系统 9.8.3_1

[English](README.md) | 简体中文

## 项目简介
WenYinOS 禅道项目管理系统基于经典 ZenTao 单体架构，采用 PHP + MySQL，集产品、项目、测试、文档与组织管理于一体。

## 核心能力
- 产品管理：需求、计划、发布
- 项目管理：任务、团队、版本、迭代执行
- 测试管理：Bug、用例、测试单
- 文档与知识管理
- 用户、组织与日常协作支持

## 统一认证（wenyinos SSO）

本项目已接入**玟茵开源社区统一认证中心**：禅道的登录页与退出均定向到认证中心，在 <https://wenyinos.com/auth/> 登录后访问禅道自动通行——一个账号同时覆盖社区论坛与禅道；禅道内修改的密码 / 资料会同步回中心。

- 接入组件：[`module/user/ext/`](./module/user/ext/) — 配置项与**总开关**（改一个配置文件的 `'enabled' => true/false` 即启停，停用立即恢复原生登录）见其 [README](./module/user/ext/README.md)。
- 需在 `config/my.php` 开启：`$config->framework->extensionLevel = 1;`

## MCP 集成（zentao-mcp）

本项目内置 MCP（Model Context Protocol）服务，AI agent 可依据 git 提交记录直接维护禅道项目管理——提交关联任务 / 需求 / Bug、驱动任务状态流转与工时记录。

- 客户端：[`zentao-mcp-py/`](./zentao-mcp-py/) — 单文件 Python 客户端（仅标准库；只需 `python3` 与 `git`），兼容任意 stdio MCP 客户端（Qoder、Claude Desktop、Cursor 等）。
- 服务端：[`module/mcp/`](./module/mcp/) — 自包含签名鉴权端点（双层 MD5 签名 + 时效校验 ±300 秒，不经用户会话）。密钥两级：团队共享密钥 + 各账号在禅道「我的地盘 → MCP密钥」自助生成的个人密钥（写操作须用本人密钥验签，无法冒用他人账号）。每台客户端自带账号与 git 作者映射（署名随人走）；仓库同步点与提交日志组装在服务端。可选 `MCP_STRICT` / `MCP_READONLY` / `MCP_IPS` 加固。
- 接入配置与完整工具清单（63 个）：见 [`zentao-mcp-py/README.md`](./zentao-mcp-py/README.md)、[`zentao-mcp-py/AGENT-GUIDE.md`](./zentao-mcp-py/AGENT-GUIDE.md) 与 [`AGENTS.md`](./AGENTS.md)。

## 安装（官方 install.php 向导）

**无需导入 SQL**——内置向导会自动创建数据库、数据表、公司与管理账号。已在全新环境端到端实测通过（安装后 `后台 → checkdb` 数据库检查 54/54 表全部 OK）。

### 环境要求
- PHP ≥ 8.4（安装向导强制检测，实测 8.5），需扩展：`pdo`、`pdo_mysql`、`json`、`openssl`、`mbstring`、`zlib`、`curl`、`filter`、`iconv`
- MySQL 5.x（utf8/utf8mb4，实测 5.7）；数据库账号需具备建库权限（也可先手工创建空库）
- Web 服务器：Nginx + php-fpm、Apache，或快速试用时的 PHP 内置服务器

### 安装步骤
1. 将 Web 服务器站点根目录指向 `www/`。快速本地试用可直接：
   ```bash
   php -S 127.0.0.1:8080 -t www
   ```
2. 确保以下目录对 PHP 进程可写：`config/`、`tmp/`、`www/data/`
   ```bash
   chmod -R 777 tmp www/data config    # 生产环境请收紧为 php-fpm 运行用户
   ```
3. 浏览器打开 `http://<你的地址>/install.php`，按向导 5 步完成：
   1. **欢迎页**——点「开始安装」
   2. **许可协议**——同意 ZPL 1.2 后继续
   3. **环境检测**——各项应全部通过（PHP 版本 / PDO / 扩展 / `tmp` 与 `www/data` 可写）
   4. **数据库设置**——填写主机、端口、账号、密码、数据库名与表前缀，向导自动建库建表；「清空现有数据」仅在覆盖重装时勾选
   5. **保存配置与管理员**——向导自动写入 `config/my.php`（页面会提示保存路径，请确认写入成功），随后填写公司名、管理员账号与密码（可选导入 demo 数据）
4. 显示「安装成功」后，用刚创建的管理员账号登录。
5. **安全收尾——从站点根目录删除安装文件：**
   ```bash
   rm www/install.php www/upgrade.php
   ```
6. 可选检查：访问 `后台 → checkdb`（数据库一致性检查），各表应全部 OK。

> 说明
> - 向导会生成 `config/my.php`（含数据库口令），该文件已 gitignore，不要提交真实凭据。
> - 如需启用统一认证（SSO）与 MCP 模块的扩展钩子，请在 `config/my.php` 中追加 `$config->framework->extensionLevel = 1;`（见上文对应章节）。
> - 通过 Web 服务器访问时，先配置下方伪静态规则再使用简洁 URL。

## CLI 辅助脚本（可选）
```bash
bash bin/init.sh /usr/bin/php http://127.0.0.1:8080    # 生成 bin/backup.sh、bin/checkdb.sh 等
php bin/ztcli 'http://127.0.0.1:8080/index.php?m=admin&f=checkdb'
```

## 目录结构
- `module/`：业务模块（如 `bug`、`story`、`task`、`project`）
- `framework/`：核心运行时、基类与路由
- `www/`：Web 入口与静态资源
- `config/`：运行配置
- `db/`：数据库结构与初始化资源
- `extension/`：扩展与定制入口
- `tmp/`：运行期缓存、日志、临时数据

## 配置说明
- 默认配置位于 `config/config.php`。
- 环境差异配置建议写入 `config/my.php`。
- 不要提交密钥或本地专用配置。

## Nginx 伪静态
在 Nginx 的 `location /` 中加入以下规则：

```nginx
if (!-d $request_filename){
set $rule_0 1$rule_0;
}
if (!-f $request_filename){
set $rule_0 2$rule_0;
}
if ($rule_0 = "21"){
rewrite /(.*)$ /index.php/$1 last;
}
```

## PHP8 升级详情
- 运行环境支持 PHP 8。
- 已将旧式字符串下标语法（`$var{0}`）迁移为方括号语法（`$var[0]`）。
- 已将旧式自动加载逻辑对齐为 `spl_autoload_register`。
- 已调整 `call_user_func_array()` 动态调用，避免 PHP 8 命名参数兼容问题。
- 已兼容 PHP 8 对动态对象属性赋值的严格行为。
- 升级 PHP 后建议先清理 OPcache 等缓存，再进行回归验证。

## 开发说明
- 默认流程不依赖 Node/Composer 构建管线。
- 自定义功能优先通过 `extension/` 增量实现。
- 变更后建议通过页面流程与 `ztcli` 双重验证。

## 安全与运维
- 将 `tmp/` 与运行期数据排除在版本控制之外。
- 部署时检查 `config/`、`tmp/` 与上传目录权限。
- 涉及数据库变更后执行一致性检查。

## 许可证
Z PUBLIC LICENSE 1.2（见 `LICENSE`）。
