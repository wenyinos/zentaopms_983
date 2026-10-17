# zentao-mcp-py

让 AI agent 从 git 提交记录自动维护禅道项目管理的 MCP 客户端（Python 版）。

- **单文件、零第三方依赖**：仅 Python 标准库 + 本机 `git`，不要求 PHP / npm / pip 安装任何包
- **薄客户端**：仅保留 stdio MCP 协议壳、本地 `git log` 执行、本机身份配置（禅道账号与 git 作者映射）与签名 HTTP（双层 MD5）；同步点状态、提交格式组装等逻辑在禅道服务端（`module/mcp/`）
- **真 API token 鉴权**：双层 MD5 签名 + 时效校验（±300 秒），不经用户账号体系，无需用户名密码
- **个人密钥（唯一方式）**：每个账号在禅道「我的地盘 → MCP密钥」页面自助生成专属密钥（每人一把、互不相同）。读写都必须用它签名——无法冒用他人账号，管理员无需手动分发

## 依赖

- Python ≥ 3.7（`python3` 在 PATH 中）
- `git`（`get_git_commits` / `sync_commits` 需要）
- 网络可达禅道站点（生产需 HTTPS）

## 配置

`cp .env.example .env` 并填写：

```ini
ZENTAO_URL=http://dev.wenyinos.test:8080   # 禅道站点地址
MCP_SECRET=<个人密钥>                        # 禅道「我的地盘 → MCP密钥」页面生成并复制整段配置
MCP_REPO_PATH=/path/to/repo                 # 默认 git 仓库路径（可选，缺省为当前目录）
MCP_ACCOUNT=your-account                    # 本机禅道账号（写操作署名，须真实存在）
GIT_AUTHOR_MAP=you@example.com:your-account # 本机 git 作者 -> 禅道账号映射（可选）
```

> **密钥获取**：登录禅道 → 「我的地盘」→ 顶部菜单「MCP密钥」→ 点「生成密钥」，页面会给出可直接粘贴的客户端配置（含个人密钥、账号与 git 作者映射模板）。
>
> 身份配置随人走：`MCP_ACCOUNT` 是本机操作人；`GIT_AUTHOR_MAP` 把本机 git 提交作者映射为禅道账号（用于 `authorAccount`），未命中返回空。服务端只认个人密钥（存禅道数据库），**不提供团队共享密钥**，无需为每个账号改服务端配置。

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

## 工具清单（68 个）

**任务**：`zentao_get_task` / `zentao_list_tasks` / `zentao_search_tasks` / `zentao_create_task`（支持子任务 `parent`）/ `zentao_update_task` / `zentao_get_task_history` / `zentao_get_task_estimates` / `zentao_start_task` / `zentao_finish_task` / `zentao_record_effort` / `zentao_delete_effort` / `zentao_add_comment`（支持 task/bug/story/doc）/ `zentao_assign_task` / `zentao_task_flow` / `zentao_delete_task`

**需求**：`zentao_get_story` / `zentao_list_stories` / `zentao_create_story` / `zentao_update_story` / `zentao_change_story` / `zentao_review_story`（评审通过/驳回/回退）/ `zentao_subdivide_story`（细分）/ `zentao_story_flow` / `zentao_delete_story`

**Bug**：`zentao_get_bug` / `zentao_list_bugs` / `zentao_create_bug` / `zentao_update_bug` / `zentao_confirm_bug` / `zentao_resolve_bug` / `zentao_close_bug` / `zentao_activate_bug` / `zentao_assign_bug` / `zentao_delete_bug`

**项目与产品**：`zentao_list_projects` / `zentao_get_project`（含产品/需求关联） / `zentao_create_project`（支持 `copyFrom` 复制团队、`products` 关联产品） / `zentao_update_project` / `zentao_project_flow` / `zentao_manage_team` / `zentao_link_project_stories` / `zentao_unlink_project_story` / `zentao_list_products` / `zentao_create_product` / `zentao_update_product`

**计划 / 账号 / 构建**：`zentao_create_plan` / `zentao_list_plans` / `zentao_list_users` / `zentao_create_build`

**发布 / 测试 / 文档**：`zentao_create_release` / `zentao_manage_release`（关联需求/Bug、状态变更） / `zentao_create_testtask` / `zentao_testtask_flow` / `zentao_link_testtask_cases` / `zentao_list_testruns` / `zentao_record_test_result` / `zentao_create_testcase` / `zentao_update_testcase`（含步骤）/ `zentao_review_testcase` / `zentao_delete_testcase` / `zentao_create_doc` / `zentao_update_doc` / `zentao_create_doclib` / `zentao_delete_doc`

**提交与报表**：`zentao_get_git_commits` / `zentao_sync_commits` / `zentao_effort_report` / `zentao_ping`

> 各工具的填写规范与示例见 [AGENT-GUIDE.md](./AGENT-GUIDE.md)。

### 删除工具（5 个 · 软删除）

`zentao_delete_task` / `zentao_delete_bug` / `zentao_delete_story` / `zentao_delete_testcase` / `zentao_delete_doc`

- **必须显式传 `confirm: "yes"`**：客户端与服务端各拦一次，缺参直接 403 `CONFIRM_REQUIRED`（服务端是硬门，绕过客户端同样拦得住）。
- **软删除**：走禅道模型层 `delete()`，与界面删除等价 —— 只把 `deleted` 置 1，**行仍留在库中**，管理员可在库内恢复；同时写入 `deleted` 操作记录（历史可查）。
- **幂等保护**：对已删对象再次调用返回 403 `*_ALREADY_DELETED`；ID 不存在返回 `*_NOT_FOUND`。
- **依赖联动**（与服务端控制层一致）：删任务会连带软删其子任务、回写父任务聚合状态、清空来源 Bug 的 `toTask`、重算所属需求阶段；删 Bug 不会动它转出的任务（返回值 `linkedTask` 给出该任务 ID 供判断）。
- **删后自查**：`zentao_get_task` / `zentao_get_bug` / `zentao_get_story` 返回体新增 `deleted` 字段（1 即已删）。列表类工具始终过滤已删对象，所以"列表里查不到"不能作为删除成功的判据，请看 `deleted` 字段。
- **只做软删除**：不提供物理删除（`DELETE FROM`）与恢复（undelete）工具；彻底清除或恢复请走界面/管理员操作。
- 写操作受 `MCP_READONLY=1` 约束（只读模式一律 403 `READONLY_MODE`），并记入 `tmp/mcp/access-YYYYMMDD.log`（署名 `personal:<account>`）。

## 提交标记规范

提交信息中使用禅道原生标记语法，`zentao_sync_commits` 会自动关联（署名恒为 git 作者）：

```
feat(module): 描述文字 task #12
fix: 修复问题 bug #34,35
story #56 相关实现
```

## 多人协作

- **密钥按人区分**：每人登录禅道在「我的地盘 → MCP密钥」自助生成个人密钥，填入本机 `.env` 的 `MCP_SECRET`。写操作（带 `actor`）必须用签名账号的个人密钥，无法冒用他人账号署名；读操作用个人密钥签名，命中哪个账号即以哪个账号的权限读取
- **没有共享密钥、也没有开关**：服务端不接受团队共享密钥，也未生成个人密钥的账号无法使用 MCP（读写皆 401）——请先在该账号的「我的地盘 → MCP密钥」生成；如需临时停用写能力，用服务端 `MCP_READONLY=1`
- 每台客户端在 `.env` 配置自己的 `MCP_ACCOUNT`（禅道账号）；所有写操作自动以该账号署名，可用可选参数 `actor` 覆盖；无效账号返回 403
- `zentao_get_git_commits` 返回每条提交的 `authorAccount`（按本机 `GIT_AUTHOR_MAP` 映射），agent 应将其作为对应流转操作的 `actor`
- 同步点由服务端统一维护：`sync_commits` 成功后自动推进（存储于禅道 `zt_config`），多客户端共享；重复同步由禅道原生逻辑去重，不产生重复关联记录

## Agent 工作流

1. `zentao_get_git_commits` 查看新提交（确认每条提交的引用对象与 `authorAccount`）
2. 按提交内容更新对应任务：wait 状态被引用 → `start_task`；含完成意图（finish/done/完成）→ `finish_task` 并记工时；过程性提交 → `record_effort`。多人场景用各自的 `authorAccount` 作为 `actor`
3. 需要时 `assign_task` 转派、`task_flow` 暂停/关闭/激活、`create_task` 补建缺失任务、`create_bug` 记录开发中发现的缺陷
4. `zentao_sync_commits` 写入提交关联记录
5. `zentao_effort_report` 生成日报数据供人工核对
