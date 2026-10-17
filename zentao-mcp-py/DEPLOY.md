# zentao-mcp 部署指南（v0.6.1）

> ⚠️ **本文是 v0.6.1 时期的历史部署记录。当前（9.10.0 起）密钥为单通道**：
> 所有请求（读与写）都必须用**个人密钥**签名，服务端**不提供团队共享密钥**，
> 也没有 `MCP_STRICT` 开关。**下文凡涉及「团队共享密钥」「过渡模式」「MCP_STRICT」的配置说明均已失效**，
> 请以 `module/mcp/.env.example` 与 `zentao-mcp-py/README.md` 的当前描述为准；
> 服务端 `module/mcp/.env` 只需保留可选项 `MCP_READONLY` / `MCP_IPS`（若要用）。

AI agent 依据 git 提交记录维护禅道项目管理：提交关联任务 / 需求 / Bug、任务状态流转、工时记录，并可创建/维护项目/产品/需求/计划/发布/测试/文档等，共 68 个 MCP 工具（填写规范见 `AGENT-GUIDE.md`）。

发布包含**两个独立附件**，归属不同、请勿混淆：

| 附件 | 部署位置 | 内容 |
|---|---|---|
| `zentao-mcp-server-0.6.1.zip` | **禅道服务器**：直接解压到禅道根目录（增量包，可安全覆盖） | **仅含相对 v0.5.0 的变更文件**：`module/mcp/model.php`（个人密钥验签）、`module/mcp/.env.example`、`module/my/ext/`（自助密钥页）、`module/common/ext/model/hook/checkPriv.z.mcpkeys.php`（权限放行）、`module/common/ext/model/mcp.php`（注释更正） |
| `zentao-mcp-client-0.6.1.zip` | **开发机**：解压到任意目录（可选更新，本版代码功能不变） | Python 客户端：`zentao-mcp-py/`（版本号与文档更新），**不要放到禅道服务器** |

> 全新部署：先落位仓库完整代码（或 v0.5.0 完整包），再叠加本增量包。

- 服务端：签名自鉴权，与用户会话无关（无需用户名密码）；密钥分「团队共享」与「按账号个人密钥」两级
- **密钥自助分发**：每个用户在禅道「我的地盘 → MCP密钥」页面生成自己的密钥并复制配置，无需管理员逐个分发
- 客户端：单文件 Python（仅标准库），要求 `python3` + `git`，**不需要 PHP**；本版客户端无需升级代码
- **无需任何数据库变更**：表结构不变，个人密钥与同步点等运行时数据由服务端自动写入禅道原生表 `zt_config`
- **需开启扩展机制**：`config/my.php` 中 `$config->framework->extensionLevel = 1;`（本仓库已配置）

## 一、服务端部署（zentao-mcp-server-0.6.1.zip）

1. 直接解压到禅道根目录（包内仅含变更文件，可安全合并覆盖）：

   ```bash
   unzip -o zentao-mcp-server-0.6.1.zip -d <禅道根目录>
   ```

   落位文件（仅变更项）：

   | 路径 | 说明 |
   |---|---|
   | `module/mcp/model.php` | 个人密钥验签（checkToken 两态判定 + `zt_config` 密钥读写 + 审计归属） |
   | `module/common/ext/model/mcp.php` | MCP 端点放行文件（仅注释更正，逻辑未变） |
   | `module/common/ext/model/hook/checkPriv.z.mcpkeys.php` | 自助密钥页权限放行 hook（新增） |
   | `module/my/ext/control/mcpkeys.php`、`module/my/ext/view/mcpkeys.html.php`、`module/my/ext/lang/{zh-cn,en}/mcpkeys.php` | 「我的地盘 → MCP密钥」自助页（新增） |
   | `module/mcp/.env.example` | 配置模板（新增 `MCP_STRICT` 说明；不影响已有 `.env`） |

2. 创建 `module/mcp/.env`（参考 `.env.example`）：

   ```ini
   MCP_SECRET=<独立随机密钥，如 openssl rand -hex 32，供过渡期共享使用>
   ; 可选安全项：
   ; MCP_READONLY=1
   ; MCP_IPS=<服务器出口 IP 或内网段>
   ; MCP_STRICT=1                 写操作严格模式：拒绝一切共享密钥写操作（全员生成个人密钥后启用）
   ```

3. 清扩展合并缓存并重载 PHP：

   ```bash
   rm -f tmp/model/*.php
   # 重载 php-fpm / 对应服务
   ```

4. 检查项：
   - 入口务必为 HTTPS（签名参数经 URL 传输）
   - 建议启用 `MCP_IPS` 白名单
   - 禅道原生 `api` / `editor` 调试入口保持封禁（与 MCP 无关）
   - 写操作审计日志：禅道 `tmp/mcp/access-YYYYMMDD.log`（每行含密钥归属：`shared` 或 `personal:<账号>`）

## 二、客户端部署（zentao-mcp-client-0.6.1.zip，每台开发机）

要求：`python3` ≥ 3.7、`git`。客户端**不属于服务端**，请勿解压到禅道目录。

1. 解压到任意位置（如 `~/tools/`）：

   ```bash
   unzip -o zentao-mcp-client-0.6.1.zip -d ~/tools/
   ```

2. 生成个人密钥（推荐，每人在禅道操作）：登录禅道 → 「我的地盘」→ 顶部菜单「MCP密钥」→ 点「生成密钥」→ 复制页面给出的配置。

   创建 `~/tools/zentao-mcp-py/.env`（参考 `.env.example`）：

   ```ini
   ZENTAO_URL=https://<禅道域名>
   MCP_SECRET=<个人密钥；过渡期也可填团队共享密钥>
   MCP_REPO_PATH=/path/to/your/repo
   MCP_ACCOUNT=<本机禅道账号，须在禅道中已存在>
   GIT_AUTHOR_MAP=<本机 git 作者转禅道账号，如 zhangsan@co.com:zhangsan>
   ```

3. 注册到 MCP 客户端。Qoder：

   ```bash
   qodercli mcp add zentao -s user -t stdio -- python3 ~/tools/zentao-mcp-py/mcp.py --keepalive
   ```

   通用 stdio JSON（Claude Desktop / Cursor 等）：

   ```json
   {"mcpServers": {"zentao": {"command": "python3", "args": ["~/tools/zentao-mcp-py/mcp.py", "--keepalive"]}}}
   ```

   `--keepalive` 必须保留：客户端关闭 stdin 后进程保活等待复用（Qoder 进程模型需要）。Windows 以 `python` 替代 `python3`。

## 三、部署验证

| 步骤 | 预期 |
|---|---|
| 浏览器访问「我的地盘 → MCP密钥」 | 页面正常显示；点「生成密钥」后出现密钥与可复制配置 |
| `zentao_ping` | `pong` |
| `zentao_get_git_commits` | 提交列表含 `authorAccount`（按本机 GIT_AUTHOR_MAP 映射） |
| 对含 `task #N` / `story #N` / `bug #N` 标记的提交执行 `zentao_sync_commits` | 返回关联结果；重复执行不产生重复记录（原生去重） |
| 无 `MCP_ACCOUNT` 时执行写工具 | 客户端本地报错不触网；服务端收到无 actor 写请求 → 403 `ACTOR_REQUIRED` |
| 已生成个人密钥的账号，用他人密钥/共享密钥签 `actor` 写操作 | 401 `INVALID_TOKEN`（无法冒用） |

## 四、密钥管理与升级说明

**v0.6.1 修复说明**：修复「生成密钥」按钮在真实浏览器中点击无效的问题（v0.6.0 缺陷）。原因：禅道全局 JS（`all.js` 的 `setForm`）在表单提交瞬间会禁用 submit 按钮，被禁用按钮的 `name/value` 不会进入请求体；原实现以按钮字段判断提交动作因而失效（curl 等无 JS 环境不受影响）。现改为按请求方法判定，并保留隐藏字段。已实测：真实浏览器点击生成/重置均正常。

**密钥轮换**（个人）：在「MCP密钥」页点「重置密钥」，旧密钥立即失效；本机 `.env` 换新密钥后重启 MCP 进程。无需管理员介入。

**~~过渡到严格模式~~（已废弃）**：v0.6.1 时期可用 `MCP_STRICT=1` 关闭共享密钥写通道；**9.10.0 起服务端已直接去掉共享密钥与 `MCP_STRICT`，无需此步骤**。

**升级步骤（v0.5.0 → v0.6.1，生产服务器）**：
1. 解压 `zentao-mcp-server-0.6.1.zip` 到禅道根目录，覆盖同名文件
2. 确认 php-fpm 用户可读（宝塔环境：`chown -R www:www module/mcp module/my module/common/ext`，目录 755 / 文件 644）
3. 清扩展合并缓存：`rm -f tmp/model/*.php`
4. 重载 php-fpm
5. 登录禅道验证：「我的地盘 → MCP密钥」页面可访问、可生成密钥；`zentao_ping` 正常

**其余说明**：
- 服务端：`MCP_SECRET` 保持不变则旧客户端继续可用
- 客户端：无需强制升级；各人可随时在禅道生成个人密钥并替换 `.env` 中的 `MCP_SECRET` 值，从而启用防冒用保护
- 旧 PHP 客户端已弃用，`sync_commits` 协议不兼容且会明确报错 `LEGACY_FORMAT`
- 首次同步：服务端同步点为空，自动回退"最近 20 条提交"模式；禅道原生去重保证不产生重复关联记录
