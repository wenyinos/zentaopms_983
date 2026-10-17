# AGENT-GUIDE —— zentao-mcp 填写规范（供 AI agent 参考）

本文件是 zentao-mcp 全部写工具的**填写规范与模板**。调用任何写工具前先读本节通用规则；每个工具的具体填写见对应小节。

## 通用规则

1. **署名（actor）**：所有写操作自动以本机 `.env` 的 `MCP_ACCOUNT` 署名；多人协作场景（同步他人提交）传入该提交的 `actor` 覆盖（无效账号报 403）。密钥按账号区分：写操作须用签名账号的个人密钥（在禅道「我的地盘 → MCP密钥」自助生成），他人密钥无法冒用；无权/无密钥错用会返回 401 `INVALID_TOKEN`。
2. **日期格式**：一律 `YYYY-MM-DD`（如 `2026-10-11`）。
3. **ID 获取**：`projectID` → `zentao_list_projects`；`productID` → `zentao_list_products`；`taskID` → `zentao_list_tasks`/`zentao_search_tasks`；`storyID` → `zentao_list_stories`；`bugID` → `zentao_list_bugs`；建完任务/需求等从返回值的 `data.taskID`/`data.storyID` 等取。
4. **数组参数**：工具 schema 中 `type: array` 的字段直接传 JSON 数组（如 `stories: [1,2]`、`whitelist: ["zhangsan"]`）。
5. **错误信息**：403 + 中文/英文提示即为业务拒绝（如 `『所属模块』不能为空`、`ACTOR_REQUIRED`），按提示修正参数重试；**不要盲目重试**。
6. **幂等**：`sync_commits` 重复执行安全（原生去重）；其余写工具重复执行可能产生重复数据（换名称重试前先查询确认）。

## 核心工作流（git 提交 → 禅道）

```
1. zentao_get_git_commits          查看新提交（含 authorAccount）
2. 按提交内容更新任务（actor 用 authorAccount）：
   - 等待中的任务被引用      → zentao_start_task
   - 过程性提交              → zentao_record_effort（记工时）
   - 含完成意图（finish/done/完成）→ zentao_finish_task（consumed 必填）
3. zentao_sync_commits             写入提交关联（默认增量）
4. zentao_effort_report            生成日报数据供核对
```
提交信息用 `task #N` / `story #N` / `bug #N` 标记引用对象。

---

## 一、项目

### zentao_get_project 查询项目详情
```json
{ "projectID": 9 }
```
- 返回项目基本信息 + **关联产品列表**（`products: [{id, name, branch, plan}]`）

### zentao_create_project 创建项目
```json
{ "name": "WenYinOS ZenTaoPMS", "code": "zentao", "begin": "2026-10-11", "end": "2027-12-31",
  "desc": "禅道定制版开发项目", "type": "sprint" }
```
- 必填：`name`、`code`（唯一）、`begin`、`end`
- 可选：`team`（默认同名称）、`days`、`type`（sprint/waterfall/ops）、`acl`（open/private）、`whitelist`
- 返回 `data.projectID`；创建人自动入团队，项目自带主文档库

### zentao_update_project 编辑项目 / 关联产品
```json
{ "projectID": 9, "desc": "新的项目描述" }
{ "projectID": 9, "products": [10, 11] }   // 全量设置项目关联的产品
{ "projectID": 9, "products": [] }          // 清空产品关联
```
- **只传要改的字段**，未传保持原值；不传 `products` 时产品关联自动保持
- `products` 为**全量替换**语义（传入的列表就是关联结果）；增/减关联先 `get_project` 看现值再传调整后的完整列表
- 产品 ID 不存在会报 403 PRODUCT_NOT_FOUND

### zentao_project_flow 项目生命周期
```json
{ "projectID": 9, "action": "start", "comment": "启动开发" }
```
- `action`：`start` 启动 / `suspend` 挂起 / `activate` 激活 / `close` 关闭

### zentao_manage_team 项目团队
```json
{ "projectID": 9, "members": [
    { "account": "weijie", "role": "开发", "days": 10, "hours": 80 },
    { "account": "ruojiner", "role": "产品", "days": 5 }
] }
```
- 已存在成员自动走更新、新成员自动加入；`role/days/hours/limited` 均可选

---

## 二、产品

### zentao_create_product 创建产品
```json
{ "name": "WenYinOS ZenTaoPMS", "code": "zentao", "PO": "ruojiner", "desc": "产品说明" }
```
- 必填：`name`、`code`（唯一）；可选：`PO`/`QD`/`RD`、`type`（normal/branch/platform）、`line`、`acl`
- 返回 `data.productID`

### zentao_update_product 编辑产品
```json
{ "productID": 10, "desc": "新说明" }
```
- 只传要改的字段

---

## 三、需求（story）

> 需求描述 = `spec`，验收标准 = `verify`（二者同属需求字段，按版本存储）

### zentao_create_story 创建需求
```json
{ "product": 10, "title": "支持多仓库同步", "spec": "允许每台客户端配置多个仓库……",
  "verify": "1. 单仓配置可同步；2. 多仓切换无误", "pri": 3, "estimate": 4, "needNotReview": 1 }
```
- 必填：`product`、`title`；`spec`/`verify`/`module`/`plan`/`source`/`pri`/`estimate`/`assignedTo` 可选
- `needNotReview: 1` = 免评审直接生效（active）；不传则按禅道配置进入评审（draft）
- 返回 `data.storyID`

### zentao_update_story 编辑需求基础字段
```json
{ "storyID": 20, "title": "新名称", "pri": 2, "estimate": 8, "comment": "调整预估" }
```
- **改需求描述/验收标准请勿用本工具**（走 `change_story`）；本工具覆盖 title/pri/estimate/module/plan/stage 等

### zentao_change_story 变更需求（改需求描述/验收标准）
```json
{ "storyID": 20, "spec": "更新后的需求描述", "verify": "更新后的验收标准", "comment": "需求变更说明" }
```
- 改 `spec`/`verify` 会自动**升版本**并记录变更历史（禅道版本机制）
- active 状态变更后转为 `changed` 待确认；传 `needNotReview: 1` 保持 active
- 未传的字段保持原值；建议填写 `comment` 说明变更原因

### zentao_story_flow 需求状态流转
```json
{ "storyID": 20, "action": "close", "closedReason": "willnotdo", "comment": "不再需要" }
{ "storyID": 20, "action": "activate", "assignedTo": "ruojiner", "comment": "重新启动" }
```
- `close`：`closedReason` 必填（done/subdivided/duplicate/postponed/willnotdo/cancel）；`duplicate` 时还需 `duplicateStory`
- `activate`：建议传 `assignedTo`（激活后回给谁）

---

## 四、任务（task）

### zentao_create_task 创建任务
```json
{ "projectID": 9, "name": "实现多仓库支持", "type": "devel", "estimate": 8, "left": 8,
  "assignedTo": "ruojiner", "desc": "任务描述……", "story": 20, "parent": 0 }
```
- 必填：`projectID`、`name`；`parent` 传父任务 ID 可建**子任务**（自动刷新父任务聚合状态）
- 子任务场景：一个大任务拆解时，先建父任务拿 `taskID`，再把每块的 `parent` 设为它

### zentao_update_task 编辑任务
```json
{ "taskID": 27, "name": "新名称", "desc": "新描述", "pri": 2, "left": 4, "comment": "调整" }
```
- 只传要改的字段；`consumed` 只能**不小于**当前值（改小工时请先用 `delete_effort` 删除记录再重记）
- 改状态请用 `start_task`/`finish_task`/`task_flow`

### 任务状态与工时
```json
{ "taskID": 27 }  // start_task：wait→doing（可带 consumed/left/realStarted）
{ "taskID": 27, "consumed": 8, "comment": "完成" }  // finish_task：done（consumed 必填）
{ "taskID": 27, "consumed": 2, "left": 6, "work": "今天的工作" }  // record_effort
{ "estimateID": 44 }  // delete_effort：删除误报工时（自动重算 consumed/left）
```

---

## 五、Bug

### zentao_create_bug 创建 Bug
```json
{ "title": "登录超时", "product": 10, "module": 1, "severity": 2, "pri": 2,
  "openedBuild": "trunk", "steps": "1. 打开登录页\n2. ……", "type": "codeerror" }
```
- 必填：`title`、`product`；建议传 `module`（有效模块 ID）

### zentao_update_bug 编辑 Bug
```json
{ "bugID": 5, "title": "新标题", "severity": 3, "steps": "更新后的步骤", "comment": "补充" }
```
- `module`：Bug 已有模块时自动保持；**无模块的 Bug 必须显式传 `module`**（禅道编辑要求，否则 403 提示）
- 已解决/已关闭的 Bug 编辑安全（内部自动回填解决/关闭字段）

### zentao_resolve_bug / zentao_close_bug / zentao_activate_bug / zentao_assign_bug
```json
{ "bugID": 5, "resolution": "fixed", "comment": "已修复", "resolvedBuild": "trunk" }   // resolve
{ "bugID": 5, "comment": "验收通过" }                                                   // close
{ "bugID": 5, "comment": "复测仍复现", "assignedTo": "weijie" }                          // activate 重开
{ "bugID": 5, "assignedTo": "weijie", "comment": "转派" }                                // assign
```
- `resolution` 取值：fixed/bydesign/duplicate/external/postponed/willnotfix/notrepro/worked

---

## 六、发布 / 测试 / 文档

### zentao_create_release 创建发布（git tag 登记）
```json
{ "productID": 10, "name": "v0.4.0", "date": "2026-10-11", "desc": "本次发布说明" }
```
- 必填：`productID`、`name`、`date`；不传 `build` 会**自动创建**同名版本记录
- 可关联 `stories: [20]` / `bugs: [5]`

### zentao_create_testtask 创建测试单
```json
{ "product": 10, "project": 9, "build": "trunk", "name": "v0.4.0 回归测试",
  "begin": "2026-10-11", "end": "2026-10-31" }
```
- 必填：`product`、`project`、`build`、`begin`、`end`、`name`
- 建议项目的所属产品已关联该 `product`（否则禅道原生的变更记录会受限）

### zentao_testtask_flow 测试单流转
```json
{ "taskID": 1, "action": "start" }   // start 开始 / close 关闭
```

### zentao_create_testcase 创建测试用例
```json
{ "product": 10, "title": "登录流程", "type": "feature", "forceNotReview": 1,
  "steps": [ { "type": "item", "desc": "打开登录页", "expect": "页面正常" },
             { "type": "item", "desc": "输入账号密码", "expect": "登录成功" } ] }
```
- 必填：`product`、`title`、`type`（feature/performance/config/install/security/interface/unit/other）
- `steps` 为步骤数组（`item`=普通步骤；`group`/`step` 供高级分组用）

### zentao_update_testcase 编辑测试用例
```json
{ "caseID": 1, "pri": 2, "precondition": "已登录", "comment": "调整" }
```

### zentao_create_doc 创建文档
```json
{ "product": 10, "title": "部署说明", "content": "# 标题\n正文……", "contentType": "markdown" }
```
- 必填：`title`、`content`；`lib` 或 `product`/`project` 至少一个（用于定位文档库，产品/项目自带主库）
- `contentType`：`html`（默认）或 `markdown`

---

## 七、v0.5.0 新增能力速查

### 计划 / 账号 / 构建
```json
{ "product": 10, "title": "v1.0", "begin": "2026-11-01", "end": "2026-12-31" }   // create_plan
{ "productID": 10 }                                                              // list_plans → [{id, title}]
{}                                                                               // list_users → [{account, realname, role, dept}]
{ "projectID": 9, "product": 10, "name": "v1.0-build", "date": "2026-10-11" }    // create_build
```
- 需求的 `plan` 字段用 `list_plans` 拿到的 id；指派/团队填写前用 `list_users` 查账号

### 需求评审与细分
```json
{ "storyID": 20, "result": "pass", "comment": "评审通过" }                       // review_story（pass/reject/revert/clarify）
{ "storyID": 20, "result": "reject", "closedReason": "willnotdo", "comment": "不做" }
{ "storyID": 22, "children": [ {"title": "子需求A", "spec": "..."}, {"title": "子需求B", "verify": "..."} ] }  // subdivide_story
```
- 细分 = 批量建子需求 + 父需求标记"已细分关闭"；子需求默认免评审直接生效

### 项目 ↔ 需求关联
```json
{ "projectID": 9, "stories": [20, 21] }        // link_project_stories（需求须属于项目已关联的产品）
{ "projectID": 9, "storyID": 20 }              // unlink_project_story（会取消该项目下该需求的未完成任务）
```
- 查看现值：`get_project` 返回 `stories: [{id, title, status, stage}]` 与 `products`

### Bug 确认 / 通用留言
```json
{ "bugID": 6, "comment": "确认有效", "assignedTo": "weijie" }                    // confirm_bug
{ "objectType": "bug", "objectID": 6, "comment": "留言" }                        // add_comment（task/bug/story/doc）
```

### 发布管理
```json
{ "releaseID": 3, "action": "link_story", "stories": [21] }                      // manage_release
{ "releaseID": 3, "action": "unlink_story", "storyID": 21 }
{ "releaseID": 3, "action": "link_bug", "bugs": [6] }
{ "releaseID": 3, "action": "change_status", "status": "terminate" }             // normal/terminate
```

### 测试执行
```json
{ "taskID": 2, "cases": [1, 2] }                                                 // link_testtask_cases（加入测试单）
{ "taskID": 2 }                                                                  // list_testruns → [{id, case, caseTitle, status}]
{ "runID": 2, "steps": [ {"stepID": 0, "result": "pass", "real": "通过"} ] }     // record_test_result
```
- `record_test_result` 的 `result` 取值 pass/fail/blocked/n/a；无步骤用例用 `stepID: 0`

### 测试用例步骤编辑 / 评审 / 文档
```json
{ "caseID": 2, "steps": [ {"type": "item", "desc": "步骤", "expect": "预期"} ] }  // update_testcase（步骤全量替换，变更自动升版本）
{ "caseID": 2, "result": "pass", "comment": "评审通过" }                          // review_testcase（pass/clarify）
{ "docID": 1, "content": "新内容", "contentType": "markdown", "comment": "更新" } // update_doc（内容变更自动升版本）
{ "name": "自定义库", "libType": "custom" }                                       // create_doclib
```

### 项目复制
```json
{ "name": "新项目", "code": "newproj", "begin": "2026-10-11", "end": "2026-12-31",
  "copyFrom": 9, "products": [10] }   // create_project：复制来源项目团队 + 直接关联产品
```

### 删除（v0.6.2 新增，5 个 · 软删除）
```json
{ "taskID": 27, "confirm": "yes" }      // delete_task
{ "bugID": 6,   "confirm": "yes" }      // delete_bug
{ "storyID": 20,"confirm": "yes" }      // delete_story
{ "caseID": 2,  "confirm": "yes" }      // delete_testcase
{ "docID": 1,   "confirm": "yes" }      // delete_doc
```
- **`confirm: "yes"` 是必填**（不是可选项）：不传或传别的值 → 403 `CONFIRM_REQUIRED`。客户端会先拦一次，服务端也独立校验（硬门）。
- **软删除**：等价于界面删除 —— 只把 `deleted` 置 1，**行仍在库中**（管理员可恢复），并写入一条 `deleted` 操作记录。MCP 不提供物理删除，也不提供恢复。
- **删除前先查**：拿不准 ID 就先 `get_task`/`get_bug`/`get_story` 确认对象与 `status`，避免删错；已删对象再删返回 403 `*_ALREADY_DELETED`，ID 不存在返回 `*_NOT_FOUND`。
- **依赖联动**（与服务端控制层一致）：删任务会连带软删其**子任务**，并回写父任务聚合状态、清空来源 Bug 的 `toTask`、重算所属需求阶段；返回体 `data.children` 给出连带删除的子任务数。删 Bug **不会**动它转出的任务（`data.linkedTask` 给出该任务 ID，需要时另行处理）。
- **删后自查**：`get_task`/`get_bug`/`get_story` 返回体带 `deleted` 字段（1 = 已删）。列表工具始终过滤已删对象，"列表里查不到"不能当判据 —— 请看 `deleted`。
- **写入审计**：每次删除记入 `tmp/mcp/access-YYYYMMDD.log`，署名 `personal:<account>`；只读模式（`MCP_READONLY=1`）下一律 403 `READONLY_MODE`。
- 删除不可撤销（MCP 侧无恢复工具）：**只有明确要求清理时才调用，不要作为"重试用"手段**（重复执行写工具本来就可能产生脏数据，删了也一样）。

### 批量操作建议
批量场景（批量建任务/Bug 等）请**循环调用单条目工具**（每次调用独立校验、错误定位清晰）；服务端不做批量接口。

## 八、已知边界

- **删除只做软删**：5 个删除工具均为软删除（`deleted=1`，行保留），无物理删除与恢复工具；彻底清除或恢复请走界面/管理员操作。`delete_effort` 是唯一删除工时明细的工具。
- **禅道 9.8.3 限制**：Bug 编辑必须能确定所属模块；需求的描述/验收标准按版本存储（读回时 `get_story` 返回当前版本内容）。
- **同步点**：`sync_commits` 的增量游标由服务端维护，多客户端共享；换机器同步同一仓库建议显式传 `repoPath`。
