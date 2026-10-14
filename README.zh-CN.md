# WenYinOS 禅道项目管理系统 9.9.0

[English](README.md) | 简体中文

> **非官方版本** —— 这是由[玟茵开源社区](https://github.com/wenyinos/ZenTaoPMS)维护的社区定制版。`9.9.0` 不对应任何官方禅道版本号，其含义是：**9.8.3 功能内核 + 移植 10.1 全新界面**（详见下）。

## 项目简介
WenYinOS 禅道项目管理系统基于经典 ZenTao 单体架构，采用 PHP + MySQL，集产品、项目、测试、文档与组织管理于一体。

本定制版融合了两个上游代际：

- **功能内核**：禅道 **9.8.3** —— 业务逻辑原封不动、功能冻结，所有模块行为与官方一致。
- **界面层**：禅道 **10.1** —— 应用骨架（`module/common/view/` 页头页脚）、页面模板、皮肤基座（ZUI 1.8.1）与 WenYin 紫色主题，并配套兼容层（`www/theme/default/compat.css`、`www/js/compat.js`）保证老页面正常渲染。

内置主题：**WenYin**（紫色，默认）、**亮蓝**、**黑莓**。

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
5. **安全收尾——关闭安装/升级入口**，两种方式任选：
   - 从站点根目录删除安装文件：
     ```bash
     rm www/install.php www/upgrade.php
     ```
   - 或保留文件、在 Web 服务器层封禁（生产加固配置会对三个残留入口统一返回 404，覆盖大小写与 pathinfo 变体）：
     ```nginx
     location ~* /(install|upgrade|checktable)\.php(/|$) { return 404; }
     ```
     采用封禁方案时，**重装/升级前需临时注释掉这一行**（或改写为 `allow <你的IP>; deny all;`），完成后立即恢复并重载 nginx；`api`/`editor` 的封禁不要动。详见下方「升级」章节。
6. 可选检查：访问 `后台 → checkdb`（数据库一致性检查），各表应全部 OK。

> 说明
> - 向导会生成 `config/my.php`（含数据库口令），该文件已 gitignore，不要提交真实凭据。
> - 如需启用统一认证（SSO）与 MCP 模块的扩展钩子，请在 `config/my.php` 中追加 `$config->framework->extensionLevel = 1;`（见上文对应章节）。
> - 通过 Web 服务器访问时，先配置下方伪静态规则再使用简洁 URL。

## 升级

本定制版自成版本序列：业务内核取自禅道 9.8.3、界面取自 10.1，与任何单个官方发布版都不完全一致，因此升级脚本刻意收窄：

- **支持的来源**：`9.8.3_1`（首个社区定制版）及后续社区版本（`9.9.x` 起）。
- **官方禅道数据库会被拒绝升级**——`upgrade.php` 会显示「无法升级」页面并给出检测到的版本号，不做任何修改。
- 一键流程为 备份警告 → 开始升级 → 升级成功；历史 `db/update*.sql` 链路与逐版本选择器均已移除，后续版本如需变更库结构，在 `module/upgrade/model.php::execute()` 中追加一个分支即可。

完整交付包（全量源码 + `zentao-mcp-py/` MCP 客户端，附 `SHA256SUMS` 校验）随每个版本发布在 [Releases 页面](https://github.com/wenyinos/ZenTaoPMS/releases)——解压覆盖到代码根目录即可原地升级，不影响 `config/my.php`、附件与缓存。

社区版之间的升级步骤：

1. **先备份数据库**（向导页会重复此警告）。
2. 将新代码覆盖部署到旧目录。
3. 创建安全放行文件：`touch www/ok.txt`（若已有但超过 1 小时，重新 touch 即可；没有新鲜的放行文件时升级向导会拒绝运行）。
4. 执行升级——Web 向导或 CLI 二选一：
   - **Web 方式**：若已按「安装步骤 5」在 nginx 封禁升级入口（生产推荐），先**临时注释掉那一行 404 规则**（或改写为 `allow <你的IP>; deny all;`）并重载 nginx，然后打开 `http://<你的地址>/upgrade.php` 按向导完成（确认前会显示当前版本与目标版本），完成后**立即恢复封禁**并再次重载；`api`/`editor` 封禁全程保持启用。
   - **CLI 方式（无需放开任何 Web 入口）**：用站点相同的 PHP 二进制执行两条命令——CLI 通道不受 Web 服务器封禁影响，也无需 `ok.txt`：
     ```bash
     php bin/ztcli 'http://<你的地址>/upgrade-execute.html'
     php bin/ztcli 'http://<你的地址>/upgrade-afterExec.html'
     ```
     第一条执行升级动作，第二条把新版本号写入数据库。
5. 仅 Web 方式需要恢复现场（恢复 nginx 封禁或再次删除安装文件）；CLI 方式无此步骤。

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
- **发布已自动化**：推送 `v9.*` tag（如 `v9.9.0`）后，`.github/workflows/release.yml` 会全量 lint PHP、校验 tag 与 `config/config.php` 的 `$config->version`、`VERSION` 三处一致，然后把全量源码包（顶层布局，可直接覆盖部署）与独立 MCP 客户端包挂到 GitHub Release。打 tag 前先同步这三处版本号。

## 安全与运维
- 将 `tmp/` 与运行期数据排除在版本控制之外。
- 部署时检查 `config/`、`tmp/` 与上传目录权限。
- 运行态封禁安装/升级残留（`install.php`、`upgrade.php`、`checktable.php`）与 `api`/`editor` 调试入口；安装/升级时只临时放行残留规则，完成后立即恢复（见「安装步骤 5」）。
- 涉及数据库变更后执行一致性检查。

## 许可证
Z PUBLIC LICENSE 1.2（见 `LICENSE`）。
