# zentao-mcp

让 AI agent 从 git 提交记录自动维护禅道项目管理的 MCP 服务。

- **纯 PHP 实现**，零 Composer/npm 依赖（服务端 + 客户端全部为 PHP）
- **真 API token 鉴权**：HMAC 签名 + 时效校验（entry 同款算法），不经用户账号体系
- **复用禅道原生能力**：提交标记解析、action 关联、任务/需求/Bug 全生命周期、工时账

## 架构

```
agent (Qoder 等) ──stdio JSON-RPC──> zentao-mcp/mcp.php（PHP 薄壳）
                                          │
                                          ├─ 读取本地 git log（shell）
                                          └─ HTTP + HMAC 签名
                                                 │
                                     /mcp-{method}.json?params&ts&token=签名
                                                 │
                                    禅道 module/mcp（自鉴权 → loadModel 直调）
```

## 组件

| 位置 | 文件 | 职责 |
|---|---|---|
| 禅道 | `module/mcp/control.php` | 端点入口（安全检查 + 参数白名单） |
| 禅道 | `module/mcp/model.php` | 业务逻辑（复用 task/story/bug/git/action model） |
| 禅道 | `module/common/ext/model/mcp.php` | ext 放行 mcp 模块（不经用户权限体系） |
| 禅道 | `module/mcp/.env` | 密钥与操作人配置（不入库） |
| 客户端 | `mcp.php` | MCP stdio 协议层（25 工具） |
| 客户端 | `client.php` | HMAC 签名 HTTP 客户端（自动附加时效 ts） |
| 客户端 | `gitlog.php` | 本地 git log 读取、作者映射、多仓库增量状态 |
| 客户端 | `config.php` + `.env` | 站点地址/密钥/仓库路径/作者映射（不入库） |

## 部署

### 禅道侧

1. 复制 `module/mcp/` 与 `module/common/ext/model/mcp.php` 到禅道对应目录
2. 创建 `module/mcp/.env`：

```ini
MCP_SECRET=<随机密钥>
MCP_ACCOUNT=<默认操作人账号>
; 可选安全项：
; MCP_READONLY=1            只读模式（写端点全部拒绝）
; MCP_IPS=127.0.0.1,10.0.0.5  IP 白名单（逗号分隔，留空不限制）
```

3. 清 `tmp/model/*.php` 缓存（ext 合并缓存，修改后必须清）

### 客户端

1. `cp .env.example .env` 并填写（见 `.env.example`，含 `GIT_AUTHOR_MAP` 作者映射）
2. Qoder 接入：`qodercli mcp add zentao -s user -t stdio -- php <repo>/zentao-mcp/mcp.php --keepalive`
   （或设置 UI 粘贴同等 JSON；**`--keepalive` 必须保留**：Qoder 会话结束会关闭薄壳 stdin，无此参数进程退出会被标记 failed 并导致后续会话缺工具）

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

- 所有写工具支持可选参数 `actor`（禅道账号），传入即以该账号署名；无效账号返回 403
- `zentao_get_git_commits` 返回每条提交的 `authorAccount`（按 `GIT_AUTHOR_MAP` 的邮箱/姓名映射），agent 应将其作为对应流转操作的 `actor`
- 不传 `actor` 时使用 `MCP_ACCOUNT` 默认账号
- `gitcommited` 关联记录署名恒为 git 提交作者（禅道原生行为）

## Agent 工作流规则

开发工作完成后（或用户要求同步时），agent 应：

1. `zentao_get_git_commits` 查看新提交（确认每条提交的引用对象与 `authorAccount`）
2. 按提交内容更新对应任务：wait 状态被引用 → `start_task`；含完成意图（finish/done/完成）→ `finish_task` 并记工时；过程性提交 → `record_effort`。多人场景用各自的 `authorAccount` 作为 `actor`
3. 需要时 `assign_task` 转派、`task_flow` 暂停/关闭/激活、`create_task` 补建缺失任务、`create_bug` 记录开发中发现的缺陷
4. `zentao_sync_commits` 写入提交关联记录
5. `zentao_effort_report` 生成日报数据供人工核对

## 安全设计

- HMAC 签名覆盖全部 query 参数 + `ts` 时效校验（±300 秒）防篡改防重放；密钥不经网络传输
- 可选只读模式（`MCP_READONLY`）与 IP 白名单（`MCP_IPS`）
- 写操作审计日志：`禅道/tmp/mcp/access-YYYYMMDD.log`（时间/IP/端点/摘要）
- 参数白名单：写操作仅接受指定字段，不暴露任意 model 调用/SQL
- 禅道原生 `api`/`editor` 调试入口保持封禁状态，MCP 不依赖它们
- 密钥文件 `.env` 均不入库

## 线上迁移注意

1. 同步 `module/mcp/`、ext 文件与 `.env` 到生产，清 `tmp/model` 缓存
2. 生产使用独立 `MCP_SECRET`、专用操作账号（可选启用 `MCP_READONLY` / `MCP_IPS`）
3. 生产入口需 HTTPS（防止签名请求被监听）
4. 客户端仅需改 `ZENTAO_URL`、密钥与作者映射
