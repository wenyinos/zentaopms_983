# zentao-mcp 部署指南（v0.3.0）

AI agent 依据 git 提交记录维护禅道项目管理：提交关联任务 / 需求 / Bug、任务状态流转、工时记录，共 25 个 MCP 工具。

- 服务端：禅道 `module/mcp/`（HMAC 自鉴权，与用户会话无关，无需用户名密码）
- 客户端：`zentao-mcp-py/`，单文件 Python（仅标准库），要求 `python3` + `git`，**不需要 PHP**
- **无需任何数据库变更**：表结构不变，同步点等运行时数据由服务端自动写入禅道原生表

## 一、服务端部署（禅道根目录）

1. 落位文件。**只有本包的 `module/` 属于服务端**，整包解压后将其覆盖到禅道根目录即可：

   ```bash
   unzip -o zentao-mcp-deploy-0.3.0.zip 'module/*' -d <禅道根目录>
   ```

   对应关系：

   | 包内路径 | 目标 |
   |---|---|
   | `module/mcp/control.php` | 禅道根目录 `module/mcp/` |
   | `module/mcp/model.php` | 禅道根目录 `module/mcp/` |
   | `module/common/ext/model/mcp.php` | 禅道根目录 `module/common/ext/model/`（放行文件；若 MCP 已部署且该文件未变更可跳过） |

2. 创建 `module/mcp/.env`（参考 `.env.example`）：

   ```ini
   MCP_SECRET=<独立随机密钥，如 openssl rand -hex 32>
   ; 可选安全项：
   ; MCP_READONLY=1
   ; MCP_IPS=<服务器出口 IP 或内网段>
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
   - 写操作审计日志：禅道 `tmp/mcp/access-YYYYMMDD.log`

## 二、客户端部署（每台开发机）

要求：`python3` ≥ 3.7、`git`。

1. 从包中取出客户端目录到任意位置（如 `~/tools/`；客户端不属于服务端，请勿放到禅道目录）：

   ```bash
   unzip -o zentao-mcp-deploy-0.3.0.zip 'zentao-mcp-py/*' -d ~/tools/
   ```
2. 创建 `zentao-mcp-py/.env`（参考 `.env.example`）：

   ```ini
   ZENTAO_URL=https://<禅道域名>
   MCP_SECRET=<与服务端一致>
   MCP_REPO_PATH=/path/to/your/repo
   MCP_ACCOUNT=<本机禅道账号，须在禅道中已存在>
   GIT_AUTHOR_MAP=<本机 git 作者转禅道账号，如 zhangsan@co.com:zhangsan>
   ```

3. 注册到 MCP 客户端。Qoder：

   ```bash
   qodercli mcp add zentao -s user -t stdio -- python3 <path>/zentao-mcp-py/mcp.py --keepalive
   ```

   通用 stdio JSON（Claude Desktop / Cursor 等）：

   ```json
   {"mcpServers": {"zentao": {"command": "python3", "args": ["<path>/zentao-mcp-py/mcp.py", "--keepalive"]}}}
   ```

   `--keepalive` 必须保留：客户端关闭 stdin 后进程保活等待复用（Qoder 进程模型需要）。Windows 以 `python` 替代 `python3`。

## 三、部署验证

| 步骤 | 预期 |
|---|---|
| `zentao_ping` | `pong` |
| `zentao_get_git_commits` | 提交列表含 `authorAccount`（按本机 GIT_AUTHOR_MAP 映射） |
| 对含 `task #N` / `story #N` / `bug #N` 标记的提交执行 `zentao_sync_commits` | 返回关联结果；重复执行不产生重复记录（原生去重） |
| 无 `MCP_ACCOUNT` 时执行写工具 | 客户端本地报错不触网；服务端收到无 actor 写请求 → 403 `ACTOR_REQUIRED` |

## 四、升级说明（从旧 PHP 客户端版本）

- 服务端：覆盖 `module/mcp/` 两个文件即可（ext 放行文件未变更可不动）
- 客户端：改用本包 `zentao-mcp-py/`；旧客户端已弃用，`sync_commits` 协议不兼容且会明确报错 `LEGACY_FORMAT`
- 首次同步：服务端同步点为空，自动回退"最近 20 条提交"模式；禅道原生去重保证不产生重复关联记录
