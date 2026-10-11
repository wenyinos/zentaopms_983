# zentao-mcp-py

让 AI agent 从 git 提交记录自动维护禅道项目管理的 MCP 客户端（Python 版）。

- **单文件、零第三方依赖**：仅 Python 标准库 + 本机 `git`，不要求 PHP / npm / pip 安装任何包
- **薄客户端**：仅保留 stdio MCP 协议壳、本地 `git log` 执行、本机身份配置（禅道账号与 git 作者映射）与 HMAC 签名 HTTP；同步点状态、提交格式组装等逻辑在禅道服务端（`module/mcp/`）
- **真 API token 鉴权**：HMAC 签名 + 时效校验（±300 秒），不经用户账号体系，无需用户名密码

## 依赖

- Python ≥ 3.7（`python3` 在 PATH 中）
- `git`（`get_git_commits` / `sync_commits` 需要）
- 网络可达禅道站点（生产需 HTTPS）

## 配置

`cp .env.example .env` 并填写：

```ini
ZENTAO_URL=http://dev.wenyinos.test:8080   # 禅道站点地址
MCP_SECRET=change-me                        # 与禅道 module/mcp/.env 中 MCP_SECRET 一致
MCP_REPO_PATH=/path/to/repo                 # 默认 git 仓库路径（可选，缺省为当前目录）
MCP_ACCOUNT=your-account                    # 本机禅道账号（写操作署名，须真实存在）
GIT_AUTHOR_MAP=you@example.com:your-account # 本机 git 作者 -> 禅道账号映射（可选）
```

> 身份配置随人走：`MCP_ACCOUNT` 是本机操作人；`GIT_AUTHOR_MAP` 把本机 git 提交作者映射为禅道账号（用于 `authorAccount`），未命中返回空。服务端只保留 `MCP_SECRET`，无需配置任何账号。

## 接入 MCP 客户端

Qoder：

```bash
qodercli mcp add zentao -s user -t stdio -- python3 <path>/zentao-mcp-py/mcp.py --keepalive
```

通用 stdio JSON（Claude Desktop / Cursor 等）：

```json
{
  "mcpServers": {
    "zentao": {
      "command": "python3",
      "args": ["/path/to/zentao-mcp-py/mcp.py", "--keepalive"]
    }
  }
}
```

说明：`--keepalive` 使进程在 stdin 被客户端关闭后保活等待复用（Qoder 的 MCP 进程模型需要；对标准客户端无副作用）。Windows 环境请将 `python3` 换为 `python`。

## 工具清单（25 个）

**任务**：`zentao_get_task` / `zentao_list_tasks` / `zentao_search_tasks` / `zentao_create_task` / `zentao_get_task_history` / `zentao_get_task_estimates` / `zentao_start_task` / `zentao_finish_task` / `zentao_record_effort` / `zentao_add_comment` / `zentao_assign_task` / `zentao_task_flow`（pause/restart/close/activate/cancel）

**需求**：`zentao_get_story` / `zentao_list_stories`

**Bug**：`zentao_get_bug` / `zentao_list_bugs` / `zentao_create_bug` / `zentao_resolve_bug` / `zentao_close_bug`

**项目与产品**：`zentao_list_projects` / `zentao_list_products`

**提交与报表**：`zentao_get_git_commits` / `zentao_sync_commits` / `zentao_effort_report` / `zentao_ping`

## 提交标记规范

提交信息中使用禅道原生标记语法，`zentao_sync_commits` 会自动关联（署名恒为 git 作者）：

```
feat(module): 描述文字 task #12
fix: 修复问题 bug #34,35
story #56 相关实现
```

## 多人协作

- 每台客户端在 `.env` 配置自己的 `MCP_ACCOUNT`（禅道账号）；所有写操作自动以该账号署名，可用可选参数 `actor` 覆盖；无效账号返回 403
- `zentao_get_git_commits` 返回每条提交的 `authorAccount`（按本机 `GIT_AUTHOR_MAP` 映射），agent 应将其作为对应流转操作的 `actor`
- 同步点由服务端统一维护：`sync_commits` 成功后自动推进（存储于禅道 `zt_config`），多客户端共享；重复同步由禅道原生逻辑去重，不产生重复关联记录

## Agent 工作流

1. `zentao_get_git_commits` 查看新提交（确认每条提交的引用对象与 `authorAccount`）
2. 按提交内容更新对应任务：wait 状态被引用 → `start_task`；含完成意图（finish/done/完成）→ `finish_task` 并记工时；过程性提交 → `record_effort`。多人场景用各自的 `authorAccount` 作为 `actor`
3. 需要时 `assign_task` 转派、`task_flow` 暂停/关闭/激活、`create_task` 补建缺失任务、`create_bug` 记录开发中发现的缺陷
4. `zentao_sync_commits` 写入提交关联记录
5. `zentao_effort_report` 生成日报数据供人工核对
