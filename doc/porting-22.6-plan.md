# 22.6 功能移植实施计划（第一批）

> **状态**：待评审 —— 主人评估后再动手，本文件不代表已开工。
> **编制日期**：2026-10-16
> **基准仓库**：`/home/zemi/MyDev/ZenTaoPMS`（禅道 **9.8.3 业务内核 + 10.1 界面**，前端 ZUI 1.8.1）
> **参照源码**：`/home/zemi/下载/zentaopms_22.6`（104 模块 / 305 表；前端 zin DSL + ZUI 1.10/3，**界面不可复用**）
> **产品定位**：开源社区（纯兴趣项目）—— 不做流程管控、工期/工时/预算、PMO 类功能。

---

## 0. 范围与结论速览

### 0.0 进度看板（滚动更新）

| 里程碑 | 内容 | 预估 | 状态 | 交付物 |
|---|---|---|---|---|
| **热修** | 燃尽图 500（工期为 0 的项目 `days` 为空串 → PHP 8 TypeError；9.9.0 既有缺陷，非本批引入） | 1h | ✅ **已发布 9.9.1**（2026-10-16） | main `e7d8a4b`（cherry-pick）+ `b131fc1`（升版）→ tag `v9.9.1` → Actions 全绿；包内三处版本 9.9.1 且零分支内容泄漏 |
| **M0** | §1 前置 + §18.1 四个 spike + 两个既有缺陷 | 29h | ✅ **已完成并推送**（2026-10-16） | 分支 `feat/port-22.6-m0` 5 个提交：版本号规则放开 `5828ccd` · helper 补 6 个 `bd5670e` · 两项缺陷修复 `00daca1` · eCharts 5.5.1 入库 `b664aab` · 计划 v1.2 `50dc4e7` |
| **M1** | 功能 1 看板升级 + 功能 6-2 用例库增量 | 91h | ✅ **已完成并推送**（2026-10-16） | 分支 `feat/port-22.6-m0` 2 个提交：看板升级 `1365ecf` · 用例库导出 `e65f0e9`（均带 `task #77` 并已同步禅道） |
| **M2** | 功能 4 错误日志 + 功能 3-1 年度总结 | 83h | ✅ **已完成**（错误日志：框架层+数据层+后台页面；年度总结：基础数据/贡献雷达/月度趋势/状态分布，数值与库逐项核对一致） | 错误日志 `07360b9`/`44ea806`/`cae27cd`；年度总结见下 |
| **M3** | 功能 6-1 CSV + 功能 6-3 需求矩阵 | 137h | ✅ **已完成**（2026-10-16） | 矩阵 `ba45187` · CSV `fa6ab45`（均带 `task #80`） |
| **M4-1** | 功能 5 文档增强（一期） | 31h | 🟡 **部分完成**（2026-10-16）：DDL/升级/模板库过滤/排序/移动/复制/最近浏览已交付；**文档模板分类与 CRUD 未做** | `5b09120`（带 `task #81`） |
| **M4-2** | 功能 3-2 pivot 透视表（只读版） | 79h | ✅ **已完成**（2026-10-16）：三表+幂等升级+引擎（22.6 移植 46 方法）+5 张内置表+列表/详情+reportList 改指，5 张内置表渲染与旧报表逐格核对一致 | `ba46f91` / `ebb5fba` / `59cb5e0`（`task #81`） |

**分支与版本策略（2026-10-16 主人明确）**：

| 分支 | 角色 | 版本 | 规则 |
|---|---|---|---|
| `main` | **稳定线（长期维护）** | 9.9.x（当前 9.9.1） | 只接受热修/小修；继续按需发 9.9.2、9.9.3… |
| `feat/port-22.6-m0` | **新特性线** | 9.10.0 | 累积 22.6 移植批次；**先不合回 main** |

- **合并方向固定为 main → 特性分支**（把稳定线的热修带进来，如已合并的 9.9.1 燃尽图热修），**特性分支绝不提前合回 main**；
- 特性分支发 9.10.0 需主人明确指示（本批 M2–M4 完成后再评估）；
- 每次 main 发版后，记得把 main 合并进特性分支，避免后续冲突；
- `isWenyinVersion` 已支持 9.9.x 与 9.10.x（§1.4），两条线的升级链路都通。

**M0 实测改动（对计划的修正已回写 §1.2 / §1.3 / §6 / §14.13 / §18.1）**：dao 无需补齐（省 2h）；eCharts 必须用全量构建；文档 Bug 根因是 `getPrivDocs` 的 `module IN ('0')` 而非 `tree::delete`；四个 spike 全过且 `sortable` 同列拖拽实测生效。总工时 441 → **455**。

### 0.1 本次范围（功能 1 / 3 / 4 / 5 / 6）

| 编号 | 功能 | 对应 22.6 | 新表 | 改表 |
|---|---|---|---|---|
| **1** | 看板升级（拖拽 + 泳道） | `module/kanban` 思路（**不移植该模块**） | 0 | 0 |
| **3-1** | 年度总结 | `report|annualData` | 0 | 0 |
| **3-2** | pivot 透视表（只读版） | `module/pivot` 引擎 | 3 | 0 |
| **4** | 错误日志 | `module/errorlog` | 2 | 0 |
| **5** | 文档/知识库增强（一期） | `module/doc` 的模板 + 目录/排序/移动/复制 | 1 | 2 列 |
| **6-1** | CSV 批量导入导出 | 自研（22.6 的 `transfer` 不可用） | 0 | 0 |
| **6-2** | 用例库增量补齐 | `module/caselib` 增量 | 0 | 0 |
| **6-3** | 需求矩阵 + 批量操作 | `module/projectstory` 增量 | 0 | 0 |

**不在本次范围**：AI 辅助（功能 2，暂缓）；`holiday`/`weekly`/`workestimation`/`personnel`/`stakeholder`/`program`/`charter`/`stage`/`demand`/`roadmap`/`repo`/CI 全线（详见 §0.4 排除理由）。

### 0.2 总工作量

| 分组 | 人时 | 人日 |
|---|---|---|
| 前置技术准备（§1，共享） | 18 | 2.3 |
| 功能 1 看板升级 | 52 | 6.5 |
| 功能 3 贡献可视化（年度总结 62 + pivot 79） | 141 | 17.6 |
| 功能 4 错误日志 | 21 | 2.6 |
| 功能 5 文档增强（一期） | 31 | 3.9 |
| 功能 6 顺手补（CSV 79 + 用例库 39 + 矩阵 58） | 176 | 22.0 |
| 自审补充项（§14 的窗口期保护 / 回填 / 三语 / spike / 性能，共 16） | 16 | 2.0 |
| **合计** | **455** | **≈ 57** |

估算口径：单人熟悉本仓、无自动化测试基建（AGENTS 明确"未配置 PHPUnit，走手工回归"），编码 : 自测 ≈ 1 : 0.6。误差约 ±30%（最坏情况见 §18）。

### 0.3 三条改变原判断的关键结论（已实测）

1. **看板不用从零做**：`module/project/js/kanban.js:17` 已有 `statusMap`、`:92` 已有 `dropTo`、`:107` 已有 `$kanban.droppable(...)` —— **跨列拖拽已经能用**（走 iframe 动作表单）。真正缺的只有三样：同列排序、泳道（多行分组）、顺序持久化。因此 52h 即可，**且零表结构变更**（顺序存 `zt_config`）。
2. **`dimension` / `mark` / `bi` 三个模块都不需要**（上一轮我判断需要，实测推翻）：
   - `dimension` 的侧栏分组可被 `zt_pivot.group` 一个字段替代；且它依赖 `tree->getGroupPairs()`，本仓 `module/tree/model.php` **没有该方法**。
   - `mark`（已读/版本角标）在只读版无写入端，不建表。
   - `bi` 只需内联 2 个方法（`processVars` SQL 变量替换、`queryWithDriver` 走 mysql），**不建 `module/bi/`**，DuckDB 整条线不要。
3. **`doc` 的"目录模型"已经存在，不要加 `catalog`/`path`/`grade`**：目录就是 `zt_module`（`root=libID`、`type='doc'`）+ `zt_doc.module`，`module/tree/` 已具备目录 CRUD 全部能力，`module/doc/view/browsebymenu.html.php:51/52/55` 已有入口。22.6 的 `zt_doc.parent/path/grade` 是**文档内章节**（块编辑器时代），与目录无关。一期真正缺的是：模板体系、文档级 `order`、移动/复制、以及**两个既有 Bug**（见 §6）。

4. **升级机制有两条硬约束（自审发现，原计划写错）**：本仓库允许跨版本跳跃（`isWenyinVersion` 白名单同时接受 `9.8.3_1` 与自研序列），所以 DDL **不能只挂单一 `case` 分支**；版本号规则已在 M0 放开为支持 `9.10.0` 形式（仍拒绝官方版本）。详见 §1.4 与 §14.1。

### 0.4 全局硬约束（每个功能都必须遵守）

1. **界面一律用 10.1 的 `module/<m>/view/*.html.php` + ZUI 1.8.1 重写**，绝不搬 22.6 的 `ui/*.html.php`（`namespace zin;`，本仓无 `lib/zin`）。切换器用 `$lang->modulePageNav` + `dropdown/search-list`；列表用 10.1 `datatable`；弹窗 `modal`；工具条 `featurebar`。
2. **不引入新依赖**，唯一例外是 eCharts（需单独授权，见 §1.3）。拖拽用 ZUI 1.8.1 原生 `$.fn.sortable` + `$.fn.droppable`（已实测存在），**不要引 SortableJS**。
3. **不补 22.6 的抽象层**：`$this->app->tab`、`$config->edition`/`vision`、`form::data()`、`$this->sendError()`/`sendSuccess()`、`$this->app->loadDriver()`、`router::loadCache()` —— 移植时删分支，改写成 `$this->post`/`fixer`/`send()`。
4. **数据库变更只做"新增"**：新增表、新增可空/有默认值的列；**绝不 ALTER 既有列语义**。每项变更都要在 `module/upgrade/model.php` 加幂等方法（范式见 §1.4）。
5. **禁止绕过状态机直写状态字段**（如 `UPDATE zt_task SET status`）：会丢工时/日期/父任务汇总/需求阶段/操作历史。所有状态流转必须复用既有 model 方法。
6. **Excel 一律不做**（22.6 的读写实现位于商业包 `extension/{max,biz}/transfer/ext/model/class/excel.class.php`，且需 PHPExcel）；只做 CSV。

### 0.5 排除清单与理由（一句话）

| 排除项 | 理由 |
|---|---|
| `program`/`programplan`/`stage`/`charter`/`execution` 三层重构 | 要改 `zt_project`（32→90 列）/`zt_story`（36→80 列）核心模型 |
| `epic`/`requirement` 三层需求 | 同上；我们现有 `childStories` + 细分需求已够用 |
| `demand`/`roadmap` | ionCube 加密（`module/demand/*`、`module/roadmap/*`、`module/charter/*` 共 8 个加密文件），代码不可读 |
| `repo`/CI/`pipeline`/`runner`/`codescan`/`artifact` | 唯一数据源是禅道官方 **GitFox** 专有服务；且与 GitHub + 已有 MCP 提交关联重复 |
| `screen` 大屏 / `bi` DuckDB / `metric` 441 文件 | 渲染器是 7.63MB 编译 Vue；DuckDB 二进制走商业 CDN；指标库属企业数据中台 |
| `cache` | 要改 `lib/base/dao/dao.class.php` 加 38 处跨请求缓存，与"内核冻结"冲突 |
| `holiday`/`weekly`/`workestimation`/`stakeholder`/`personnel`/`transfer` | 兴趣项目无交付承诺/工时/PMO 需求 |

---

## 1. 前置技术准备（共享，约 20 人时）

### 1.1 `framework/base/helper.class.php` 补齐（实缺 7 个）

实测缺失（`grep -c "function X" framework/base/helper.class.php framework/helper.class.php`）：

| 函数 | 用途 | 谁依赖 |
|---|---|---|
| `hasFeature($feature)` | 22.6 的功能开关（`$config->features`），几十处引用 | 几乎每个移植功能 |
| `setCookie($name,$value,$life,$path)` | 22.6 各模块高频调用 | 看板/文档/pivot |
| `arrayColumn($array,$column)` | 取列 | pivot/矩阵 |
| `formatHours($hours)` | 工时格式化 | 年度总结 |
| `mbPathinfo($path,$option)` | 路径解析 | repo（本次不做，顺手补） |
| `isApiRequest()` | 请求类型判定 | errorlog |
| `decodeHtmlSpecialChars($str)` | 消息净化 | errorlog |

成本：7 个函数，每个 3–8 行，**约 3 人时**。
**`connectRedis()` 不补**（只有 `cache` 需要，本次不做）。

### 1.2 `lib/base/dao/dao.class.php` —— **实测定论：无需补齐**（M0 已核实）

原计划要补两处，M0 逐项验证后**全部推翻**：

| 原计划缺口 | 实测结论 | 证据 |
|---|---|---|
| `fetchAll($keyField, $autoExclude)` 第 2 参 | **不需要**。22.6 里该参数受 `dao::$autoExclude` 开关控制，而开关**默认 false** ⇒ `if(self::$autoExclude && $autoExclude)` 恒不成立，第 2 参在默认配置下是 **no-op**；pivot 全模块只有 1 处传它（`module/pivot/model.php:2943` 的 `fetchAll('', false)`），移植时去掉即可 | `dao.class.php:200`（`public static $autoExclude = false;`）与 `:1400`（生效条件）；本仓 `fetchAll(:817)` 已支持 `$keyField` |
| `dao->getColumns($table)` | **不需要**。全仓**除 `module/bi/model.php` 外零调用**；pivot 唯一相关的列信息依赖是 `$this->bi->getColumnsType($querySQL)`（`module/pivot/model.php:335`），已计入功能 3-2 的"内联 2 个方法"工作量；dataview 用静态 `module/dataview/table/*.php` 定义，不做运行时探测 | `grep -rn 'dao->getColumns' module/` 仅命中 `bi/model.php` |

⇒ **`lib/base/dao/dao.class.php` 零改动**，前置成本由 2 人时降为 **0**。

**仍需记住的结构差异**：本仓 `lib/base/dao/` 下**只有 `dao.class.php`，没有 `dbh` 类**；数据库连接是 `framework/base/router.class.php:2125` 直接 `new PDO(...)`，`$this->dbh` 即 PDO 实例。故 22.6 的 `$this->dbh->prepare()` 可用（PDO 原生方法），但 **`formatSQL()` 本仓不存在**（全仓 0 命中），拼 SQL 必须用 PDO 绑定参数（功能 4 errorlog 即按此设计）。

### 1.3 前端：eCharts（**已批准引入** · §13 决策 1）

- 现状：`www/js/` 下**无 echarts**（只有 9.8.3 的 `chartjs`），无 gantt，无 kanban，无 dtable。
- 需要它的功能：年度总结（雷达图 + 月度柱状）、pivot 图表（二期）、CFD（不在本批）。
- 方案（**M0 已实施**）：新增 **`www/js/echarts/echarts.min.js`（全量构建）**，Apache-2.0，**1,030,855 字节，version 5.5.1，sha256 `e84270bd0cd5bdf60fefc26d00c2a391cb2e81f4d26a7a9ee16185a54773a3cf`**（与 npmmirror 官方文件一致）。不引 `echarts-liquidfill`、不引 `html2canvas`（22.6 的"下载长图"删除）。
- **⚠ 关键修正：不能用 `echarts.common.min.js`（常用构建）** —— Spike 3 实测：common 构建**不含 radar 图表类型**，年度总结的"能力雷达图"一渲染就抛 `TypeError: Cannot read properties of undefined (reading 'getProgressive')`。全量构建 1.03MB（比 common 多约 370KB）才能同时支持 radar + bar + pie + gauge。
- 成本：**2 人时**（已含下载/校验/接线/三主题 + 窄屏实测，见 §18）。

### 1.4 升级通道（**本仓库有专用约定，务必遵守**）

> **前提（主人 2026-10-16 明确）**：现有生产库通过 `upgrade.php` 执行升级来变更结构，**不是直接覆盖生产库**。`db/zentao.sql` 只服务"全新安装"，**不得**把"覆盖 zentao.sql"当作生产升级手段。

**机制现状**（`module/upgrade/model.php`）

```php
public function execute($fromVersion)
{
    $this->addCollectorColumns();       // ← 顶部：幂等"按需补齐"（先例）
    switch($fromVersion)                // ← $fromVersion = 站点当前已安装版本
    {
        case '9_8_3_1': break;
    }
    $this->deletePatch();
    $this->clearModelCache();           // ← 自动清 tmp/model 扩展合并缓存
}
```

**关键约束 1：DDL 不能只挂在单一 `case` 分支上（本仓库允许跨版本跳跃）**

`module/upgrade/control.php:56` 取 `$fromVersion = $this->config->installedVersion`；`checkSource()`（`model.php:101`）的白名单 `isWenyinVersion()`（`:85`）**同时接受 `9.8.3_1` 与 `9.9.x`**：

```php
if(preg_match('/^9\.8\.3_\d+$/', $version)) return true;   // 9.8.3_1 起
if(preg_match('/^9\.9\.\d+$/',   $version)) return true;   // 9.9.0 起
```

⇒ 一个停留在 **9.8.3_1** 的站点可以直接升到最新版，此时 `$fromVersion = '9_8_3_1'`，**`case '9_9_0':` 分支根本不会执行**。若把建表只写在 `case '9_9_0'` 里，这类站点升级后**永远缺表**，页面直接 SQL 报错。

**结论（推荐方案）**：新表/新列一律用**幂等"按需探测"方法**（先例 `addCollectorColumns()`），在 `execute()` 顶部调用；同时在 `switch` 里为新版本补一个**显式 case 分支**（可为空 + 注释指向该方法），满足仓库可读性约定。即**双保险**，两者都幂等、重复执行无害。

```php
public function execute($fromVersion)
{
    $this->addCollectorColumns();     // 既有
    $this->addDocOrderColumn();       // 本批新增（幂等探测）
    $this->addDoclibTypeColumn();
    $this->createDocActionTable();
    $this->seedTemplateLib();
    $this->createPivotTables();
    $this->addErrorLogTables();
    $this->addErrorLogCron();

    switch($fromVersion)
    {
        case '9_8_3_1':               // 兼容：任何 9.8.3_1 / 9.9.x 来源都能补齐（方法本身幂等）
        case '9_9_0':
            /* 本批新增的库结构由上方各方法按需补齐，无需在此重复。 */
            break;
    }

    $this->deletePatch();
    $this->clearModelCache();
}

/** 通用范式（照抄 addCollectorColumns，module/upgrade/model.php:51-73） */
public function addXxxColumns()
{
    $exists = $this->dao->select('COUNT(*) AS count')->from('information_schema.columns')
        ->where('table_schema')->eq($this->config->db->name)
        ->andWhere('table_name')->eq($this->config->db->prefix . 'xxx')
        ->andWhere('column_name')->eq('col')->fetch('count');
    if($exists) return;                       // 幂等：存在即跳过
    try { $this->dbh->exec('ALTER TABLE ...'); }
    catch (PDOException $e) { self::$errors[] = $e->getMessage() . "<p>The sql is: ...</p>"; }
}
```

**关键约束 2：版本号规则已放开（支持 `9.10.0` 形式）**

原 `isWenyinVersion()` 只匹配 `^9\.9\.\d+$`，会让 `9.10.0` 在**下一次升级**时被 `checkSource()` 当作"非本社区版本"拒绝（`printUnsupportedSource()`）。

**已按主人要求放开（2026-10-16，M0 首批实施）**：

```php
public function isWenyinVersion($version)
{
    if(preg_match('/^9\.8\.3_\d+$/', (string)$version))            return true;  // WenYinOS 定制序列（9.8.3_1 起）
    if(preg_match('/^9\.(?:9|[1-9]\d+)\.\d+$/', (string)$version)) return true;  // 9.9.x 与 9.10.x 起的自研序列
    return false;
}
```

**边界（必须保持"拒绝官方版本"这一核心语义）**：

| 版本号 | 判定 | 理由 |
|---|---|---|
| `9.8.3_1`、`9.8.3_2` | ✅ 允许 | 本社区定制序列 |
| `9.9.0`、`9.9.7` | ✅ 允许 | 中段为 `9`（官方无 9.9.x 发布号） |
| `9.10.0`、`9.11.3`、`9.99.0`、`9.100.0` | ✅ 允许 | 中段为两位数及以上，官方禅道的 10.x 写作 `10.x`，不存在 `9.10.x` |
| `9.8.3`（官方） | ❌ 拒绝 | 中段 `8` 不匹配 `9\|[1-9]\d+` |
| `9.0.x`–`9.8.2`（官方） | ❌ 拒绝 | 同上 |
| `10.1`、`18.5`、`20.0`、`22.6` | ❌ 拒绝 | 不以 `9.` 开头 |

⇒ **版本号序列**：`9.9.1` → `9.9.2` → … → 也可直接跳到 `9.10.0`、`9.11.0`。**不再有"必须留在 9.9.x"的限制**，但每次跳段（如 9.9.x → 9.10.0）要单独验证一次升级链路（`checkSource` 用的是**来源版本**，所以跳段后"来源 = 9.10.0 升到 9.11.0"必须能过）。

**关键约束 3：`db/zentao.sql` 被 `explode(';')` 切分执行**

`module/install/model.php:441` 是 `explode(';', file_get_contents($dbFile))` 后逐段执行。⇒ 写进 `db/zentao.sql` 的新 DDL **必须满足**：
- **不能出现字面量分号**（含注释与字符串值，如 `COMMENT 'a;b'`）；
- 不能有存储过程 / 触发器 / `DELIMITER`；
- 每段必须是独立可执行语句；`DROP TABLE` 段会被 `str_replace('--','')` 处理（`:453`）。

**关键约束 4：`db/zentao.sql` 仍须同步**（全新安装路径），表名用固定 `zt_` 前缀（遵循该文件既有惯例）。

每个功能的升级方法在对应章节列出，汇总见 §10。

### 1.5 明确不补清单

| 不补 | 理由 |
|---|---|
| `$this->app->tab` | 22.6 的"顶部导航上下文"，我们用自己的 `$moduleName`/`$methodName` |
| `$config->edition` / `$config->vision` | 商业版功能开关，删分支即可 |
| `form::data()` / `lib/form` | 改写为 `fixer::input('post')` |
| `$this->sendError()` / `sendSuccess()` | 改写为 `$this->send()`（`framework/base/control.class.php:811`） |
| `$this->app->loadDriver()` / `router::loadCache()` / `connectSqlite()` | 分别只服务于 DuckDB / cache / SQLite（都不做） |
| `helper::connectRedis()` | 只服务 cache |
| `module/dimension/`、`module/mark/`、`module/bi/` | 见 §0.3 第 2 条，用字段与内联方法替代 |
| `lib/zin/`、`lib/dtable/` | 22.6 前端框架，与本仓 ZUI 1.8.1 不同代 |

### 1.6 前置准备汇总

| 项 | 人时 | 授权 |
|---|---|---|
| helper 6 函数（M0 已完成） | 3 | 否 |
| dao 补齐 —— **实测无需改动**（§1.2） | 0 | — |
| eCharts 引入（M0 已完成：官方 5.5.1，662KB） | 2 | **是** |
| pivot 依赖的 2 个内联方法（`processVars`/`queryWithDriver`） | 5 | 否 |
| ZUI sortable/droppable 技术验证（跨列 + 同列） | 2 | 否 |
| 本文件（移植对照清单）持续维护 | 6 | 否 |
| **合计** | **18** | |

---

## 2. 功能 1：看板升级（拖拽 + 泳道）—— 52 人时

### 2.1 目标与验收标准

**现状（实测）**：`module/project/control.php:1340` 的 `kanban($projectID, $type='story', $orderBy='order_asc')` 是"分组表格 + 每格一个 `.board`"：`module/project/view/kanban.html.php:56` 用 `<div id="kanban" data-ride="table" data-group="true">`，`:134` 每个状态一列。分组维度只有 `story`/`assignedTo`/`finishedBy`（`module/project/model.php:2308 getKanbanGroupData`）。跨列拖拽**已存在**（`module/project/js/kanban.js:17/92/107`），但**无同列排序、无泳道、无顺序持久化**。

**验收标准**
1. 支持 4 种泳道维度：需求(story) / 模块(module) / 指派人(assignedTo) / 优先级(pri)，切换后 URL 可分享。
2. 同列内可拖拽排序，刷新/换浏览器后顺序保持（落 `zt_config`）。
3. 跨列拖拽**不直接改 `status`**，仍走 9.8.3 原有动作表单（`task-start/finish/pause/activate/cancel/close`）；非法流转无落点。
4. 泳道可折叠（折叠状态只存 localStorage）。
5. **`zt_task`/`zt_story`/`db/zentao.sql` 零结构变更**；`printKanban` 不回归。
6. 只用 ZUI 1.8.1 的 `sortable`/`droppable`。

### 2.2 数据模型变更

**不动任何表结构，顺序存 `zt_config`。**

`zt_config` 结构（`db/zentao.sql:194-203`）：`owner char(30)` / `module varchar(30)` / `section char(30)` / `key char(30)` / `value text`，唯一键 `(owner,module,section,key)` —— **key 与 section 都是 char(30)，必须短**。

```
写入：$this->loadModel('setting')->setItem("system.project.kanbanOrder.project{$projectID}", json_encode($order));
读取：$this->loadModel('setting')->getItem("owner=system&module=project&section=kanbanOrder&key=project{$projectID}");
```

value 结构：
```json
{ "laneOrder": {"assignedTo": ["zhangsan","lisi",""]},
  "cardOrder": {"zhangsan|doing": [12,9,31], "__nokey|pause": [44]} }
```
（`cardOrder` 键 = `laneKey|status`；无需求/无模块的任务泳道键统一写 `__nokey`，避免 JSON 键类型歧义。）

**方案对比（为什么不用 `pri`、不加 `order` 列）**

| 方案 | 结论 |
|---|---|
| (a) 用 `zt_task.pri` 当人工顺序 | ❌ 否决。`pri` 是用户可见优先级（`module/project/view/kanban.html.php:119` 渲染 `label-pri-{$task->pri}`，`$lang->task->priList` 只有 0–4），改成第 37 位顺序会污染标签/筛选/排序三处 |
| **(b) `zt_config` 存 JSON** | ✅ **推荐**。零结构变更；与仓库既有做法一致（`module/project/model.php:2361 saveKanbanData` 已用 `null.project.kanban.project$ID`）；回退 = 删一行配置 |
| (c) `zt_task` 加 `order` 列 | ⚠️ 保留为逃生出口。22.6 加该列是为了**甘特拖拽**（`module/task/model.php:3618 updateOrderByGantt`），我们不做甘特；且没有任何功能需要 SQL 级顺序（看板在 PHP 内存排序即可） |

### 2.3 后端改动清单

| 文件:函数 | 改动 |
|---|---|
| `module/project/control.php:1340` `kanban()` | 新增第 4 参 `$groupBy='story'`（前 3 参保持兼容）；`$this->app->loadConfig('project')` 后读 order JSON；注入 `$this->view->groupBy/laneOrder/cellOrder/laneCount` |
| `module/project/control.php:2309` `ajaxKanbanSetting()` | 增加两个配置项：默认泳道维度、跨列拖是否只排序（沿用 `:2322` 的 `setItem` 前缀写法） |
| `module/project/control.php` **新增** `kanbanOrder($projectID)` | POST-only；`common::hasPriv('project','kanban')` 校验；落库前 GC（只保留仍存在且 `deleted=0` 的 task id）；`$this->send()` 返回 JSON |
| `module/project/control.php:1505` `printKanban()` | 透传 `$groupBy`，保证打印与屏幕一致 |
| `module/project/model.php:2275` `getKanbanTasks()` | 允许调用方传自定义 `orderBy`；返回增加 `order` 序号字段 |
| `module/project/model.php:2308` `getKanbanGroupData()` | **不改语义**（被 `printKanban`、`storyKanban` 复用），仅增加 `$groupBy` 分支转调新方法 |
| `module/project/model.php` **新增** `getKanbanLanes($tasks,$bugs,$groupBy,$laneOrder,$users,$modules)` | 泳道集合与名称：`story`→`story->getProjectStories`；`module`→`tree->getByID`（`module/tree/model.php:23`）；`assignedTo`→`user->getPairs('noletter')`；`pri`→`$lang->task->priList`；按 `laneOrder` 排序，末尾补空泳道 |
| `module/project/model.php` **新增** `getKanbanOrder/saveKanbanOrder` | 封装 `setting->getItem/setItem`；保存前做 id 白名单过滤与 JSON 兜底 |
| `module/project/model.php` **新增** `sortKanbanCards(&$kanbanGroup,$cellOrder)` | 纯内存排序；未命中 id 按 `id desc` 附加末尾 |
| `module/task/model.php` | **零改动**（状态流转必须复用既有方法，理由见 2.7） |

**9.8.3 任务状态机（决定拖拽落点白名单，与 `kanban.js:17` 的 `statusMap` 一致）**

| 从＼到 | wait | doing | done | pause | cancel | closed |
|---|---|---|---|---|---|---|
| wait | — | `start` | `finish` | ✗ | `cancel` | ✗ |
| doing | ✗ | — | `finish` | `pause` | ✗ | ✗ |
| done | ✗ | `activate` | — | ✗ | ✗ | `close` |
| pause | ✗ | `activate` | `finish` | — | `cancel` | ✗ |
| cancel | ✗ | `activate` | ✗ | ✗ | — | `close` |
| closed | ✗ | `activate` | ✗ | ✗ | ✗ | — |

### 2.4 前端改动清单

| 文件 | 改动 |
|---|---|
| `module/project/view/kanban.html.php:56-179` | 由"一张 `table-grouped`"改为多条泳道：`foreach($lanes) → <div class='kanban-lane' data-lane='..'>`，泳道内 `<div class='boards'>` + N 个 `<div class='board' data-status='wait|doing|...'>`；卡片仍用 `.board-item[data-id][data-type=task|bug]`（`:137`），保留 `kanbaniframe` 弹窗入口（`:140/146/158/163`）与空态（`:68-78`） |
| `module/project/view/kanban.html.php:12-45` | 保留现有工具条（看板设置 `:14`、打印 `:15`、导出 `:17-19`、导入 `:20-36`、新建任务 `:37-43`），新增**分组下拉**（`dropdown`，菜单项取 `$lang->project->groups`，`module/project/lang/zh-cn.php:169-176`）。**注意**：泳道维度切换器**不要塞进 `$lang->modulePageNav`** —— `header.html.php:47` 在视图渲染前已输出它，视图内再赋值无效 |
| `module/project/view/kanban.html.php`（新增局部） | 泳道头：泳道名 + 卡片数 + 折叠按钮（ZUI `collapse`）；需求泳道显示 `pri` 标签 + `estimate`（复用 `:117-122`） |
| `module/project/js/kanban.js:107-152` | 保留 `droppable` 作**跨列**落点（`target` 由 `statusMap` 驱动，`:115-121`），叠加 `sortable` 做**同列排序**：`$('#kanban').sortable({selector:'.board-item', containerSelector:'.board', dragCssClass:'dragging', finish:saveOrder})` |
| `module/project/js/kanban.js:93-105` | `dropTo()` 保持"跨列走 iframe 表单"；新增分支：`from === to`（同列）**不调表单**，只更新 DOM 顺序并提交顺序 |
| `module/project/js/kanban.js`（新增 `saveOrder()`） | `$.post($.createLink('project','kanbanOrder','projectID='+projectID), {order: JSON.stringify(...)})`；失败时 `$.zui.messager` 提示并 `location.reload()`；`refresh()`（`:83-88`）执行前先 `saveOrder()`（**否则 `:87` 的 `$kanban.load()` 重绘会丢顺序**） |
| `module/project/js/kanban.js:66-81` | 删除 `fixBoardWidth()` 对 `thead>th.c-board` 的表格宽度依赖，改按泳道内 flex 等比列宽 |
| `module/project/css/kanban.css`（现 41 行） | 新增 `.kanban-lane`/`.lane-header{position:sticky;left:0}`/`.lane-count`/`.board[data-status]` 顶色条/折叠态；复用既有 `.boards/.board/.board-item/.board-item.dragging`（`:1-22`、`:31-38`） |
| `module/project/view/ajaxkanbansetting.html.php`（65 行） | 增加"默认泳道维度"与"跨列拖拽是否同时改状态"两个控件 |

> **资源加载规则（别放错目录）**：模块 CSS/JS 由框架**按方法名**自动加载 —— `framework/base/control.class.php:456-459`（`module/<m>/css/{common,<method>}.css`）与 `:505-507`（js 同理）。新样式写 `module/project/css/kanban.css`、新脚本写 `module/project/js/kanban.js`。

### 2.5 22.6 参照索引

| 22.6 位置 | 借鉴什么 |
|---|---|
| `module/kanban/model.php:1780 getLanes4Group()` | **泳道生成算法**：按 `$groupBy` 收集 distinct 值 → 取名称 → 赋 order → 末尾补空值泳道（我们的 `getKanbanLanes()` 照此写） |
| `module/kanban/tao.php:766 getObjectPairs()` | 泳道名映射（`module`→`zt_module`、`story`→`zt_story.title`、`assignedTo`→`zt_user.realname`、其余走 `$lang-><x>->{groupBy}List`） |
| `module/kanban/model.php:908 getRDKanban()` / `:972 getRDKanbanByGroup()` | 装配顺序与返回结构形状（`{lanes, cols, items}`） |
| `module/kanban/model.php:1385 getCardGroupByExecution()` / `:1834 getCols4Group()` | "列=状态"、"列里有哪些卡片"的分桶（它把顺序存 `zt_kanbancell.cards` 逗号串，我们换成 `cardOrder` JSON） |
| `module/kanban/model.php:3237 moveCard()` / `:3206 sortGroup()` | 拖拽落库的两步语义与排序请求形状 |
| `module/kanban/css/kanban.css` | 泳道配色板与 WIP 样式 |
| **不可复用** | `module/kanban/ui/*.html.php`（zin）、`zen.php`、8 张 `zt_kanban*` 表相关方法 |

### 2.6 分步实施与检查点

| 步 | 内容 | 检查点 |
|---|---|---|
| A-1 | 泳道取数 + `kanban()` 加 `$groupBy`，视图仍用旧表格 | 4 个维度 URL 均 200；`assignedTo` 泳道数与 `zt_user` 实际指派人数一致；空泳道卡片数与旧 `getKanbanGroupData` 输出相等 |
| A-2 | `getKanbanOrder/saveKanbanOrder` + `kanbanOrder` 接口 | `zt_config` 出现 1 行；重复写入幂等；删任务后再写入，JSON 不再含该 id |
| A-3 | 渲染排序 + 同列拖拽 | 拖后刷新顺序保持；换浏览器仍保持；同列拖拽**不产生任何 `zt_action`**（证明只改顺序） |
| A-4 | 跨列拖拽复用 `statusMap` + iframe 表单 | 逐一验证 6 个状态的合法落点；非法落点无高亮；`wait→done`/`done→closed`/`closed→doing` 三条路径执行后 `zt_taskestimate` 各新增 1 行、`zt_action` 有记录、父任务状态与需求 stage 正确 |
| A-5 | 泳道折叠 + 列宽自适应 + 设置项 | 折叠不写后端（Network 无请求）；设置项写入 `zt_config` 并在下次进入生效 |
| A-6 | 回归 `printKanban` / `storyKanban` / 卡片弹窗 | 打印页与屏幕一致；`storyKanban` 无回归；任务卡 `assignTo` 弹窗保存后看板刷新且顺序不丢 |

### 2.7 风险与回退

1. **改库风险 = 0**（不 ALTER 任何表）。回退 = `deleteItems("owner=system&module=project&section=kanbanOrder&key=project{ID}")`。
2. **顺序丢失**：`module/project/js/kanban.js:87` 的 `$kanban.load(location.href + ' #kanban')` 会重绘 DOM，必须在 `refresh()` 前落库。验收时专门测"拖完立刻点开任务弹窗再关闭"。
3. **并发覆盖**：两人同时拖同一泳道会互相覆盖（最后写入者胜）。兴趣项目可接受；若不可接受则走方案 (c) 加列。
4. **`getKanbanGroupData` 被多处复用**（`control.php:1357` 看板、`:1519` 打印、`story/model.php` 供 `:1632 storyKanban`）：新逻辑独立成 `getKanbanLanes`，**不改它的语义**。
5. **禁止直接改 status**（见 2.3 状态机）：`module/task/model.php:892 start()` 会写 `consumed/left/realStarted` + 插 `zt_taskestimate`（`:930`）+ `updateParentStatus`（`:957`）+ `story->setStage`（`:960`）；`finish()`（`:1092`）写 `finishedBy/finishedDate` + `score`；`close()`（`:1195`）/`cancel()`（`:1231`）/`activate()`（`:1261`）各有清字段逻辑。直改库 → 工时/日期/父任务/需求阶段/操作历史/积分全部缺失。属**必须拦截的 Review 项**。
6. **打印页**：`module/project/view/preview.html.php:47-52` 按 12 格/页拼版，泳道化后仍按旧列拼版（可仅支持"按状态打印"，泳道信息降级为卡片标题前缀）。

### 2.8 工作量分解

| 项 | 人时 |
|---|---|
| A-1 泳道取数与视图骨架 | 8 |
| A-2 顺序持久化（model + 接口 + GC） | 6 |
| A-3 同列拖拽 + 排序渲染 | 12 |
| A-4 跨列状态机梳理 + 6 条路径回归 | 8 |
| A-5 泳道折叠 + CSS + 设置项 | 8 |
| A-6 打印/需求看板/弹窗回归 | 6 |
| A-7 自测汇总与文档 | 4 |
| **合计** | **52** |

---

## 3. 功能 3-1：年度总结（`report|annualData`）—— 62 人时

### 3.1 目标与验收标准

新增 `report|annualData($year,$dept,$account)`：支持"全公司 / 按部门 / 按人"三档，默认当前年（1、2 月落到上一年）。含 22.6 的 7 个区块：基本数据、操作数据、能力雷达图、项目数据、产品数据、需求/任务/Bug/用例月度操作、状态分布。

**验收标准**
1. 所有数字**能从 9.8.3 的表直接算出**或已明确改写口径，不出现"列不存在"的 SQL 报错。
2. 不引入 22.6 抽象层；`zt_action` 的 `execution` 一律按 `project` 处理。
3. 现有 7 个 report 方法（`module/report/control.php:20/31/52/74/100/123/181`）**全部保留可用**。
4. 页面无 `html2canvas` 引用（22.6 的"下载长图"删除）。

### 3.2 数据模型变更

**零建表、零改列**（全部是对既有表的聚合查询）。

**口径改写清单（最易踩坑处，实施时逐条勾）**

| 22.6 口径 | 位置 | 我们的处理 |
|---|---|---|
| `zt_effort(date,account,consumed,deleted)` | `report/model.php:460 getUserYearEfforts` | ❌ **必须改写**：本仓 `zt_effort` 是**同名不同义**的表（"我的地盘-日志"，列 `user/todo/begin/end/type/idvalue/name/desc/status`，`db/zentao.sql:300-314`，全仓无模块写它）。工时改从 `zt_taskestimate(consumed,date,account)`（`db/zentao.sql:637-647`）汇总 —— 这是 9.8.3 记录工时的唯一落点（`module/task/model.php:2211 addTaskEstimate`） |
| `TABLE_EXECUTION`、`zt_task.execution/order`、`zt_team(type='execution')` | `:522 getUserYearExecutions`、`tao.php:73` | ❌ 改写：本仓无 `zt_execution` 表，任务直连 `zt_project`（`db/zentao.sql:601`）；整段按 `zt_project(type IN ('sprint','waterfall','ops'))` + `zt_task.project` 重写 |
| `zt_project.isTpl`、`zt_task.isTpl` | `:374` | ❌ 删除（本仓无该列，`db/zentao.sql:466-499`、`:598-635`） |
| `zt_todo.deleted`、`zt_todo.vision` | `:441` | ❌ 删除；`done` 计为 `status IN ('done','closed')` |
| `zt_product.shadow` | `:481`、`tao.php:22` | ❌ 删除（本仓无该列） |
| `zt_story.type IN ('story','requirement','epic')` | `:544`、`:563` | ⚠️ 改写：本仓 `type` 恒空 → 去掉条件加 `deleted=0`；需求分级三列**合并为一列"需求数"**（空列比少一列更伤信任） |
| `objectType IN ('audit','issue','risk','qa','feedback','ticket','review')` | `:316`、`:563` | ⚠️ 删除这些键（本仓 `zt_action.objectType` 实测只有 story/task/bug/case/doc/... 见 `module/action/lang/zh-cn.php:66-88`） |
| `zt_action(objectType='case')` + `zt_testresult` | `:618`、`tao.php:147` | ✅ 直接可用（`db/zentao.sql:690-701`） |
| `zt_action(action='login')` | `:285` | ✅ 直接可用 |
| `monthAction` 的 `fromfeedback/fromticket` | 配置 | ⚠️ 删除 |
| `report/config.php:10-95`（`contributions/radar/...`） | 配置源 | ⚠️ 移植后逐条裁剪 |
| `report/view/annualdata.html.php` | 视图 | ✅ **不是 zin**（12.x 血统经典 PHP 视图 + `html::select` + `table table-hover`），可改造为 10.1 视图 |
| `report/js/annualdata.js:167` `html2canvas` | 依赖 | ❌ 删除"下载长图"按钮 |

### 3.3 后端改动清单

| 文件:函数 | 改动 |
|---|---|
| `module/report/control.php` **新增** `annualData($year='',$dept='',$account='')` | 仿本仓现有 7 方法写法（`$this->view->title/position[]/display()`），**不用** 22.6 的 `reportZen` 分层；把 22.6 `report/zen.php:60-212` 的三个 assign 方法**内联**进 control 或 `module/report/model.php`（本仓无 tao/zen 分层习惯） |
| `module/report/model.php` **新增** 14 个方法 | `getUserYearLogins/getUserYearActions/getUserYearContributionCount/getUserYearContributions/getUserYearTodos/getUserYearEfforts/getUserYearProducts/getUserYearExecutions/getAllTimeStatusStat/getYearObjectStat/getYearCaseStat/getYearMonths/getStatusOverview/getOutputData`，按 §3.2 逐条改写 |
| `module/report/config.php` | 新增 `$config->report->annualData[...]` 五个子表（`contributions/radar/contributionCount/monthAction/month/outputData`）+ `minMonth/colors` |
| `module/report/lang/zh-cn.php` + `en.php` | 新增 `annualData` 全量键（22.6 `report/lang/zh-cn.php:99-345`，约 120 键）+ `tips` |
| `module/common/lang/zh-cn.php`（`$lang->report->menu`） | 增加第 5 个 tab：`$lang->report->menu->annual = array('link'=>'年度总结|report|annualData','alias'=>'annualdata')`，并补 `menuOrder`；高亮由 `commonModel::printModuleMenu` 按 alias 匹配 |

### 3.4 前端改动清单

1. **新增 `module/report/view/annualdata.html.php`**（约 300 行，22.6 原文 496 行）：以 22.6 为蓝本改造 —— 头部改 10.1 骨架；范围选择用 `html::select` + `chosen`（`module/common/view/chosen.html.php` 已在 header 链）；表区沿用 `table table-hover table-fixed`（10.1 皮肤可用），横向滚动外套 `.table-responsive`；雷达/月度图用 eCharts。
2. **新增 `www/js/echarts/echarts.common.min.js`**（唯一新依赖）。
3. **新增 `module/report/css/annualdata.css`** + **`module/report/js/annualdata.js`**（**文件名必须与方法名同名**才会被自动加载，见 `framework/base/control.class.php:456/505`）。
4. **背景图不新增**：22.6 依赖 `theme/default/images/main/annual_data_bg.png` 等两张图，本仓没有 → 改纯色/渐变。

### 3.5 22.6 参照索引

| 位置 | 借鉴 |
|---|---|
| `module/report/control.php:111-131` | 入口骨架与 mode 三档判定 |
| `module/report/zen.php:60-110 assignAnnualReport()` | 视图变量清单与雷达图分组算法 |
| `module/report/zen.php:122-168 assignAnnualBaseData()` | 年份区间、默认年回退、部门/用户下拉（我们改用 `module/dept/model.php:344 getDeptUserPairs()`，9.8.3 版只有 2 参） |
| `module/report/zen.php:178-212 assignAnnualData()` | `$data` 字段名（保持一致可少改视图） |
| `module/report/model.php:285-460` / `:544-712` | 各口径（改写清单见 §3.2） |
| `module/report/tao.php:22-205` | 产品/项目/用例三块年度统计（按 project 口径重写） |
| `module/report/view/annualdata.html.php:255-430` | 雷达/柱状图 option 生成 |

### 3.6 分步实施与检查点

| 步 | 内容 | 检查点 |
|---|---|---|
| B1-1 | 语言包 + 配置 + 菜单（页面先渲染占位） | 顶部出现第 5 个 tab；`report|annualData` 200；`en/zh-tw` 不缺键 |
| B1-2 | 基础 4 项（登录/操作/待办/工时） | `logins` 与 `SELECT COUNT(1) FROM zt_action WHERE actor='x' AND LEFT(date,4)='2026' AND action='login'` 一致；`consumed` 与 `SELECT SUM(consumed) FROM zt_taskestimate WHERE account='x' AND LEFT(date,4)='2026'` 一致（**证明工时口径已切到 `zt_taskestimate`**） |
| B1-3 | 贡献数 + 雷达图 | 贡献分解各项之和 = 总数；`objectType` 不含 execution/feedback/issue/risk/qa/ticket |
| B1-4 | 项目/产品块 | 抽查 2 个项目：完成任务数 = `zt_task.finishedBy IN(...) AND LEFT(finishedDate,4)=year`；产品块"需求数"不为 0 |
| B1-5 | 月度操作 + 状态分布 | 12 个月柱状与 `zt_action` 按 `LEFT(date,7)` 计数逐个核对 |
| B1-6 | eCharts 接入 + 窄屏 | 窄屏不溢出；Network 除 eCharts 无新 JS |
| B1-7 | 权限与空数据 | `deptEmpty` 分支返回 0 不报错 |

### 3.7 风险与回退

1. **最大风险是"照抄 22.6 SQL 的列名"**（`vision/shadow/isTpl/multiple/execution/effort` 六类）。把 §3.2 打印成 checklist，每写一个 SQL 勾一行。
2. 工时口径切换后与旧报表不一致（旧 `report` 用 `zt_task.left/consumed` 快照，年度用 `zt_taskestimate` 明细）→ 文档写明"年度工时 = 工时明细累计"。
3. **回退**：纯新增（新方法/新视图/新语言键/新菜单项），删菜单项与 `annualData` 即回退，零数据影响。

> **已实施范围（2026-10-16，M2）**：`report|annualData` 落地为 **基础数据（登录/操作/待办/工时/贡献总数）+ 能力雷达（eCharts）+ 月度操作趋势（eCharts）+ 状态分布** 四个区块，年度/部门/人三档范围可切换，1–2 月默认落上一年（`minMonth=2`）。
> **口径按本仓表结构改写**：工时取 `zt_taskestimate`（本仓 `zt_effort` 是"我的地盘-日志"表，语义不同）；`execution` 一律按 `project` 处理；`zt_todo` 无 `deleted`/`vision` 列，`done` 计 `status IN ('done','closed')`。
> **本版裁剪（未做，需要时再补）**：产品明细块、项目明细块、用例结果统计、API 输出统计（22.6 的 `getUserYearProducts/getUserYearExecutions/getYearCaseStat/getOutput4API` 及其 `tao` 层）。裁剪理由是这四块占 22.6 实现约 60% 的代码量、且依赖我们没有的字段（`shadow`/`isTpl`/`execution` 等）。
> **实测**：`report-annualData.html` 四项数值与库逐项一致（登录 213 / 操作 390 / 工时 39 / 工时记录 22，2026 年）；雷达 5 轴 `[16,16,36,14,7]`；两图 canvas 像素级确认渲染（13778 / 10223 非白像素）。

### 3.8 工作量分解

| 项 | 人时 |
|---|---|
| B1-1 语言包/配置/菜单 | 6 |
| B1-2 基础 4 项（含工时口径改写） | 8 |
| B1-3 贡献数 + 雷达图 | 8 |
| B1-4 产品/项目块（口径重写最重） | 14 |
| B1-5 月度操作 + 状态分布 | 8 |
| B1-6 视图 + eCharts | 12 |
| B1-7 权限/空数据/回归 | 6 |
| **合计** | **62** |

---

## 4. 功能 3-2：pivot 透视表（只读版）—— 79 人时

### 4.1 目标与验收标准

**"只读版"定义**：不移植建表器/设计器、版本切换 UI、下钻、图表/大屏联动、xlsx 导出、DuckDB 双驱动。只保留**「按既有 SQL + 既定 settings 渲染成 rowspan 表格」**这一条链路，并替换现有 5 张静态报表。

**验收标准**
1. `pivot|preview` 列出全部透视表（只读），点击进 `pivot|show` 渲染带跨行合并（rowspan）与两级表头的表格。
2. 5 张内置透视表（产品汇总 / Bug创建 / Bug指派 / 项目偏差 / 员工负载）数字与现有 `report|productSummary/bugCreate/bugAssign/projectDeviation/workload`（`module/report/control.php:31/52/74/100/123`）**逐格一致**。
3. 引擎不依赖 zin、`loadDriver`、`sqlparser`、`workflowfield`、`dataview`、`edition/vision`、`app->tab`。
4. 前端只用 ZUI 1.8.1 普通 `table`（**不启用 `data-ride='table'` 与 datatable 模式**，理由见 4.4）。
5. 旧 `report` 5 张报表**不删除**，`reportList` 改指 pivot（可一键切回）。

### 4.2 数据模型变更（新建 3 张表）

表名用 `$config->db->prefix` 而非硬编码 `zt_`；引擎与 `db/zentao.sql` 既有风格一致。

```sql
CREATE TABLE IF NOT EXISTS `{prefix}pivot` (
  `id`          mediumint(8) unsigned NOT NULL auto_increment,
  `code`        varchar(45)  NOT NULL DEFAULT '',        -- 稳定标识（内置表靠它幂等 upsert）
  `name`        varchar(90)  NOT NULL DEFAULT '',
  `desc`        text         NOT NULL,
  `group`       varchar(45)  NOT NULL DEFAULT '',         -- 侧栏分组名（替代 dimension 模块）
  `sql`         text         NOT NULL,
  `fields`      text         NOT NULL,                    -- JSON: {fieldKey:{type,object,field}}
  `langs`       text         NOT NULL,                    -- JSON: {fieldKey:{zh-cn:'...'}}
  `settings`    text         NOT NULL,                    -- JSON: {group1,group2,group3,columns[],columnTotal,columnPosition,summary}
  `filters`     text         NOT NULL,
  `vars`        text         NOT NULL,
  `builtin`     tinyint(1)   unsigned NOT NULL DEFAULT 0,
  `version`     varchar(10)  NOT NULL DEFAULT '1',
  `createdBy`   varchar(30)  NOT NULL DEFAULT '',
  `createdDate` datetime     NOT NULL,
  `editedBy`    varchar(30)  NOT NULL DEFAULT '',
  `editedDate`  datetime     NOT NULL,
  `deleted`     enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY (`code`),
  KEY `group` (`group`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{prefix}pivotspec` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `pivot` mediumint(8) unsigned NOT NULL DEFAULT 0,
  `version` varchar(10) NOT NULL DEFAULT '1',
  `name` varchar(90) NOT NULL DEFAULT '',
  `desc` text NOT NULL,
  `sql` text NOT NULL, `fields` text NOT NULL, `langs` text NOT NULL,
  `vars` text NOT NULL, `settings` text NOT NULL, `filters` text NOT NULL,
  `createdDate` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `pivot` (`pivot`,`version`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{prefix}pivotdrill` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `pivot` mediumint(8) unsigned NOT NULL DEFAULT 0,
  `version` varchar(10) NOT NULL DEFAULT '1',
  `field` varchar(255) NOT NULL DEFAULT '',
  `object` varchar(40) NOT NULL DEFAULT '',
  `whereSql` mediumtext NOT NULL,
  `condition` mediumtext NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'published',
  `account` varchar(30) NOT NULL DEFAULT '',
  `type` varchar(10) NOT NULL DEFAULT 'manual',
  PRIMARY KEY (`id`), KEY `pivot` (`pivot`,`version`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
```

**相对 22.6 原表删掉的列**（`db/zentao.sql:14580/14613/14644`）：`driver`（只支持 mysql）、`mode`、`step/stage`（工作流状态）、`acl/whitelist`（兴趣项目只做登录可见）、`objects`。`zt_pivotdrill` 保留字段但只读（下钻 UI 不做）。

**常量与升级**：
- `config/zentaopms.php` 追加 `TABLE_PIVOT`/`TABLE_PIVOTSPEC`/`TABLE_PIVOTDRILL`（对照 `:54 TABLE_CONFIG` 写法）。
- `module/upgrade/model.php` 新增 `createPivotTables()`，范式照 §1.4（`information_schema` 探测 + `CREATE TABLE IF NOT EXISTS` 双保险）。
- `db/zentao.sql` 同步追加三表。

### 4.3 后端改动清单

**必须移植的方法（36 个，对 `genSheet` 做的传递闭包，不多不少）**

| 分组 | 方法（22.6 `module/pivot/model.php` 行号） |
|---|---|
| 主链路 | `genSheet`(1895)、`genOriginSheet`(1997)、`buildPivotTable`(2546) |
| 分组/列配置 | `getGroupsFromSettings`(864)、`generateTableCols`(1037)、`filterFieldsWithSettings`(956)、`getShowColPosition`(1081)、`isShowLastRow`(1097) |
| SQL 组装 | `trimSemicolon`(882)、`appendWhereFilterToSql`(896)、`getFilterFieldSQL`(931)、`processQueryFilterDefaults`(1861)、`getFilterFormat`(777)、`processDateVar`(167) |
| 取值/选项映射 | `mapRecordValueWithFieldOptions`(985)、`getFieldsOptions`(2485)、`getSysOptions`(2311)、`formatCellData`(1166) |
| 表头 | `getTableHeader`(2200)、`getColLabel`(2157) |
| 透视计算 | `processCrystalData`(1703)、`groupRecords`(1537)、`setUniqueSlices`(1559)、`getSliceRecords`(1590)、`getCellData`(1612)、`columnStatistics`(1112)、`addDrillFields`(1675) |
| 树与汇总 | `getGroupTreeWithKey`(1136)、`pureCrystalData`(1274)、`addRowSummary`(1244)、`getColumnSummary`(1196)、`getRowTotal`(1444)、`processPercentage`(1504)、`setPercentage`(1468) |
| 扁平行与合并 | `flattenCrystalData`(1335)、`flattenRow`(1308)、`processRowSpan`(1365)、`processRecordsForDisplay`(1746)、`getRowSpanConfig`(1811)、`getGroupsKey`(2294) |
| 下钻（留桩） | `getDrillsFromRecords`(1837) |

**外部依赖改写（逐条给替代实现）**

| 22.6 依赖 | 我们的替代 |
|---|---|
| `bi::processVars($sql,$filters)` | **内联**进 `module/pivot/model.php`（约 35 行：按 `from='query'` 的 filter 替换 `$field`/`$fieldCondition` 占位符为默认值，兜底把残留 `$Xxx_1` 替换为 `''`）。**不新建 `module/bi/`** |
| `bi::queryWithDriver($driver,$sql)` | `$this->dbh->query($sql)->fetchAll()`（`$this->dbh` 是原生 PDO，`framework/base/router.class.php:2125`；默认 `FETCH_OBJ`，`framework/base/model.class.php:61`）。删掉 `ztv_projectnotpl`/`ztv_tasknotpl` 模板视图替换（本仓无 `isTpl`）与 duckdb 分支 |
| `bi::getScopeOptions($type)` | 轻量版：`user`→`user->getPairs('noletter')`；`product`→`product->getPairs()`；`project`→`project->getPairs()`；`dept`→`dept->getOptionMenu(0)`；`'x.y'`→`$lang->x->{y}List`。`execution` 键删除 |
| `bi::getDataviewOptions/getObjectOptions/getOptionsFromSql` | `object` 型选项：`$this->dao->select("id,{$field}")->from($this->config->objectTables[$object])->fetchPairs()`；列存在性检查用 `SHOW COLUMNS FROM tbl`（本仓 dao **没有** `descTable()`） |
| `workflowfield->getFieldPairs/getList` | 走 `$config->edition != 'open'` 分支 → **直接删除**；`generateTableCols` 只保留 `$this->lang->$object->$field` 取名 |
| `$config->dataview->multipleMappingFields` | 在 `module/pivot/config.php` 直接定义 `$config->pivot->multipleMappingFields = array('testcase-stage');` |
| `$this->config->pivot->userFields` | 原样搬（`array('assignedTo','createdBy','openedBy','resolvedBy','closedBy','finishedBy','account','user')`），用于把账号转真实姓名；`user->getPairs('noletter,noempty,noclosed')` 三个 flag 本仓都支持 |

**新建文件**

| 文件 | 内容 |
|---|---|
| `module/pivot/config.php` | `userFields`、`multipleMappingFields`、`createSql`（三表 DDL）、`builtins`（5 张内置定义）、`drivers=array('mysql')` |
| `module/pivot/model.php` | 上表 36 个方法（约 900–1100 行）+ `getByID/getPivotSpec/getList` + `syncBuiltins()`（按 `code` 幂等 upsert） |
| `module/pivot/control.php` | `index()`→`locate('preview')`；`preview()`（列表）；`show($pivotID,$version='newest')`。**不写** `create/edit/design/versions/drill/chart/screen` |
| `module/pivot/lang/zh-cn.php` + `en.php` | 只取渲染所需键：`total(169)`、`colLabel(214)`、`colShowMode(215)`、`empty`、`stepDesign->statList(456-463)`、`showModeList(446-450)`、`stepDesign->total(432)`、`preview/list/previewTitle`；**删掉**设计器/图表/版本相关数百键 |
| `module/pivot/view/preview.html.php` | 只读列表（10.1：`panel` + `table` + `#featurebar` 过滤项） |
| `module/pivot/view/show.html.php` | 过滤器条 + `buildPivotTable()` 输出的表格 |

**`dimension` / `mark`：一个都不搬**（结论）
- `dimension`（22.6 `module/dimension/model.php` 共 118 行/5 方法）：`getFirst/getList/getDimension/saveState` 全部依赖 `bi::getViewableObject`、`setting->setItem`、`tree->getGroupPairs`（本仓 `module/tree/model.php` **无此方法**）。用 `zt_pivot.group` + 一句 `SELECT DISTINCT group FROM zt_pivot WHERE deleted='0' AND builtin=1 ORDER BY id` 替代。
- `mark`（22.6 共 120 行/5 方法）：只读版无写入端，不建 `zt_mark` 表。将来若需"内置表更新打新标"，只需搬 `getNeededMarks`/`getMarks`/`hasMark` 三个（约 60 行）。

### 4.4 前端：rowspan 表格怎么渲染

**核心结论：不能用 10.1 的 `datatable` 模式，必须走"服务端拼 HTML"。**
- 本仓 datatable 是客户端渲染（`module/bug/view/browse.html.php:181-190` 用 `$useDatatable` 切换，启用时 JS 接管表格并重排/重建行）→ rowspan 与 `data-fixed-*` 列宽计算都会失效。
- 22.6 自己也是这么做的：`buildPivotTable()`（`module/pivot/model.php:2546-2688`）**直接返回 `<table>` 字符串**（含 rowspan 与两级 thead），只有 5 张内置报表才用 dtable。→ 照抄 `buildPivotTable` 即可，零 zin 依赖。

**渲染实现**
1. `control::show()` 里 `list($data,$configs) = $this->pivot->genSheet(...)`，然后 `$this->view->pivotTable = $this->pivot->buildPivotTable($data,$configs);`
2. `module/pivot/view/show.html.php`：
   - 过滤器条 `<div id='featurebar'><ul class='nav'>…</ul><div class='actions'>…</div></div>`（10.1 惯例，参考 `module/project/view/taskheader.html.php:1-3`）；日期用 `module/common/view/datepicker.html.php`，多选/搜索用 `chosen`。
   - 表体 `<div class='table-responsive'><?php echo $pivotTable;?></div>`（`.table-responsive` 10.1 皮肤有定义；`buildPivotTable` 自带 `.reportData > table.table.table-condensed...`）。
   - **微调两处**：① 去掉 `buildPivotTable` 生成的 `datatable` 类名；② **表格不加 `data-ride='table'`**（ZUI 的 table 插件会做 fixed-header 克隆与行选择，会让 rowspan 表格表头副本错位）。
3. 不做排序/分页（只读版）；超大表用 `settings['summary']='notuse'` 切 `genOriginSheet`（明细模式）。

### 4.5 内置 5 张透视表怎么落地

**关键发现**：本仓 `$lang->reportList`（`module/report/lang/zh-cn.php:51-60`）与 22.6 的 `$lang->pivotList`（`module/pivot/lang/zh-cn.php:146-156`）是**同一份 5 条清单**。所以"内置 5 张透视表"= 现有 5 张静态报表的 pivot 化，一一对应，不新增业务概念。

**落地方式**：不在 SQL 里 `INSERT`，而在 `module/pivot/config.php` 用 PHP 数组定义，由 `model::syncBuiltins()` 在升级时与首次访问时按 `code` 幂等 upsert（`builtin=1`）。理由：可版本化、可 review、升级幂等、改口径无需写迁移 SQL。

| # | code | 对应旧报表 | 说明 |
|---|---|---|---|
| 1 | `productsummary` | `report|productSummary` | 产品 × 计划，需求计数（按状态切片） |
| 2 | `bugcreate` | `report|bugCreate` | 账号 × Bug，按 resolution 切片 + 日期/产品/项目过滤 |
| 3 | `bugassign` | `report|bugAssign` | 活跃 Bug 按指派人 + 优先级 |
| 4 | `projectdeviation` | `report|projectDeviation` | 项目 × 任务 estimate/consumed/left 求和 |
| 5 | `workload` | `report|workload` | 账号 × 任务 left 求和 + 任务计数 + dept/assign 过滤 |

`fields` 每个键必须给 `{type,object,field}`：`account`→`{type:'user',object:'user',field:'realname'}`、`product`→`{type:'product',...}`、`status`→`{type:'option',object:'bug',field:'statusList'}`、日期→`{type:'date'}`。

**替换旧报表的迁移路径（可回退，3 阶段）**
1. **共存**：`reportList` 不动，pivot 就绪并渲染通过；用脚本对 5 组数字逐一比对。
2. **切换入口**：把 `$lang->reportList->*->lists` 的 `module` 从 `report` 改为 `pivot`、`method` 改为 `show`（`pivotID` 作为 `params`；格式 `label|module|method|params`，解析见 `module/report/view/blockreportlist.html.php:12-19`）；旧 `report` 方法保留，直链仍可用。
3. **收尾**：`report` 的 5 个方法标记 deprecated（注释 + 发布说明），**不删代码**；`report|index`（`:20`）改为 `locate('annualData')`。

### 4.6 分步实施与检查点

| 步 | 内容 | 检查点 |
|---|---|---|
| B2-1 | 三表 DDL + 常量 + `upgrade::createPivotTables()` + `db/zentao.sql` | 全新装库与"9.8.3_1 升级"两条路径都建出三表；重复执行不报错 |
| B2-2 | 引擎 36 方法 + 3 处依赖改写（先用硬编码 SQL 在 CLI 跑） | 用一个 3 分组 + 1 切片列的测试 SQL 验证 `rowspan` 与实际行数匹配 |
| B2-3 | `config->pivot->builtins` 5 张 + `syncBuiltins()` | `zt_pivot` 恰好 5 行（`builtin=1`），`code` 唯一；再执行仍 5 行 |
| B2-4 | `preview`/`show` 视图 + 过滤器 | 5 张表都能打开；`<td rowspan=N>` 计数与 `$configs` 一致；过滤器改日期后数字变化（值经 `$this->dbh->quote`） |
| B2-5 | 与旧报表**逐格比对** | 5 张表全部行/列数字与 `report|*` 一致（建议写一次性脚本抓两次 HTTP 输出做 diff，脚本不入库） |
| B2-6 | 切换 `reportList` 指向 pivot，旧页保留 | 统计页 5 个链接进 pivot；直链 `report-productsummary` 仍可用；一键恢复 = 改回 lang 两行 |
| B2-7 | 回归 `report|index/remind`、`blockreportlist`、权限 | 统计首页不 500；`remind` 邮件模板不变 |

### 4.7 风险与回退

1. **引擎移植面偏大**（36 方法）→ 用"一条测试 SQL 端到端跑通"收敛：B2-2 不通过就不进 B2-3。**禁止顺手搬** `getSysOptions` 全套（`module/pivot/model.php:2311` 里的 dataview/workflowfield 分支）。
2. **表头标签依赖语言键**：`$lang->pivot->colLabel='{$field}的{$stat}'`（22.6 `lang/zh-cn.php:214`）与 `stepDesign->statList`（`:456-463`）必须一起搬，否则表头空白（易漏）。
3. **rowspan 与前端插件冲突**：明确禁用 `data-ride='table'` 与 datatable（见 4.4）。
4. **表结构新增属红线级**：只新增表、绝不 ALTER 既有表；升级分支必须幂等。
5. **回退**：`DROP TABLE zt_pivot/zt_pivotspec/zt_pivotdrill`（无外键、不参与任何既有业务流程）+ 改回 `reportList` 两行 + 删 `module/pivot/`。业务数据零损失。
6. **风格差异需说明**：pivot 表格是服务端渲染静态表，无列设置/排序交互 → 发布说明写明"透视表为只读静态报表"。

### 4.8 工作量分解

| 项 | 人时 |
|---|---|
| B2-1 三表 + 升级分支 + `zentao.sql` | 4 |
| B2-2 引擎 36 方法 + 3 处依赖改写 | 26 |
| B2-3 5 张内置定义 + `syncBuiltins` | 16 |
| B2-4 视图（列表 + 表格 + 过滤器） | 12 |
| B2-5 逐格比对 5 张 × 全列 + 修口径 | 10 |
| B2-6 `reportList` 切换与共存 | 4 |
| B2-7 回归 | 6 |
| B2-8 dimension/mark 决策留痕（注释） | 1 |
| **合计** | **79** |

---

## 4.0 实施记录（2026-10-16）

**已交付**（`ba46f91`，`task #81`）

| 项 | 证据 |
|---|---|
| 三张表 + `createPivotTables()` | `execute()` 连跑两次零错误、DDL 只建一次、`db/zentao.sql` 同步 |
| 5 张内置定义（`config->pivot->builtins`） | `syncBuiltins` 按 code 幂等 upsert，实测生成 5 行且重复访问不重复插 |
| `control::preview/show` + 两个视图 | 列表页、5 张详情页均 200 无错 |
| `reportList` 5 处改指 pivot | 菜单链接 `/pivot-show-productsummary.html` 可直达；旧报表页全部保留可访问 |

**内置表口径核对结果（对照 `report` 同名报表）**

| 内置表 | 结论 |
|---|---|
| Bug指派表 | **逐格一致**（`weijie / MCP扩展测试产品 / 2`） |
| Bug创建表 | **逐格一致**（`ruojiner all=3 resolved=1 fixed=1`；窗口同报表页的「上月 1 日至今」） |
| 项目偏差表 | **逐格一致**（造「已完成项目 + 父任务」数据核对：`estimate=12 / consumed=12.5`） |
| 产品汇总表 | **逐格一致**（MCP 产品 active 6 / closed 1；社区主页V2 active 15 / closed 2） |
| 员工负载表 | **口径不同，已在 config 注释注明**：报表页按工作日折算「可用工时」，本表只做「指派×项目的工时分布」 |

**踩到的两个坑（写进 config 注释了）**

1. `TABLE_XXX` 常量**自带反引号**，SQL 里再包一层会变成 ``FROM ``zt_bug`` `` 直接报「Incorrect table name」。
2. `settings.columns` 的元素是**对象**（`array('field' => 'x')`，可带 `slice`），不是字符串；`filterFieldsWithSettings` 按 `$column['field']` 取值。
3. 取数 SQL 里**不要把别名起成表里已有的列名**：`productsummary` 最初写 `... AS plan` + `GROUP BY plan`，MySQL 把 `plan` 解析成 `zt_story.plan` 列而非别名，「空计划」与「引用了已删计划」被拆成两组（实测从 1 行裂成 2 行）。改名 `planName` 后一致。

**引擎与最终验收**（`ebb5fba` / `59cb5e0`）

引擎是 22.6 的忠实移植（46 个方法，零 zin/bi/dataview/dimension/workflowfield/execution 依赖）。曾一度以为「列维度不展开」是引擎缺陷，实际**是我的 config 写错了 `settings.columns` 的结构**：

- 每项必须是 `array('field' => 取值字段, 'stat' => sum|count|avg|min|max|distinct, 'slice' => 横向展开维度字段)`
- `stat` 缺省 `count`、`slice` 缺省 `noSlice`（不展开只出一列）
- 我原来写 `array('field' => 'product')` = 「对 product 计数且不展开」→ 只出一个「列」值列、值像在数行数

另有两处 lang 缺失导致表头/总计标签丢失：`$lang->pivot->colLabel` 必须是模板 `'{$field}的{$stat}'`（写死成「列」会让 `str_replace` 找不到占位符、把 `getColLabel()` 的结果整段丢弃），以及 `$lang->pivot->stepDesign->{statList,total,showModeList}`。

最终实测（`pivot-show-<code>`，本地 dev 站）：

| 内置表 | 渲染结果 |
|---|---|
| 产品汇总表 | 产品→计划两级行分组（rowspan）、列按状态展开、每组小计 + 表尾总计；MCP 6/1、社区主页V1 1/0、社区主页V2 15/2、总计 22/3 |
| Bug指派表 | 指派给 × 所属产品（列展开）；亦可赛艇/MCP扩展测试产品 = 2 |
| Bug创建表 | 4 个数值列；果子狸9999 3/1/1/0、亦可赛艇 2/2/2/0、总计 5/3/3/0 |
| 员工负载表 | 指派给 × 项目 × 三个工时指标 |
| 项目偏差表 | 表头正常（本地无已完成项目故 0 行；造数核对时逐格一致） |

## 5. 功能 4：错误日志（`errorlog`）—— 21 人时

### 5.1 目标与验收标准

把 PHP 错误（含 SQL 错误、未捕获致命错误）落库，后台可列表/详情/批量删除/设保存天数，`cron` 按天数清理。一期只做"记录 + 查看 + 清理"。

**现状**：本仓 `module/` 下**不存在 errorlog 模块**；错误只写文件日志。

**验收标准**

| # | 场景 | 期望 |
|---|---|---|
| 1 | 制造一个 PHP Warning | 两表各新增 1 条，file/line/level/message 与实际一致 |
| 2 | 制造一个 SQL 错误 | message 含 `The sql is: ...`，level=E_USER_ERROR(256) |
| 3 | `$config->debug = false`（`config/my.php:3` 当前默认） | **仍落库**（现状 `framework/base/router.class.php:2212` 在 debug 空时直接 return，落库必须放在它之前） |
| 4 | 同请求同错误触发 10 次 | `zt_errorlogreq` 只 1 条（requestID+md5 唯一）；本体只 1 条 |
| 5 | 数据库不可用 | 不白屏、无递归；仅跳过落库，文件日志与 die 行为不变 |
| 6 | 任意错误 | message/file/trace 中不出现 `getBasePath()`（`/home/zemi/MyDev/ZenTaoPMS/`） |
| 7 | `errorlog` 模块自身报错 | 不再写库（防自噬） |
| 8 | 后台页 | 列表分页/排序/级别筛选、详情弹窗显示堆栈、批量删除、保存天数设置 |
| 9 | cron | `moduleName=errorlog&methodName=deleteLog` 删超期 req 并清理孤儿本体 |

### 5.2 数据模型变更（新建 2 张表）

```sql
CREATE TABLE IF NOT EXISTS `zt_errorlog` (
  `md5`     char(32) NOT NULL DEFAULT '',
  `file`    varchar(255) NOT NULL DEFAULT '',
  `line`    int unsigned NOT NULL DEFAULT 0,
  `level`   smallint unsigned NOT NULL DEFAULT 0,
  `message` text DEFAULT NULL,
  `trace`   text DEFAULT NULL,
  PRIMARY KEY (`md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `zt_errorlogreq` (
  `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
  `requestID`   varchar(64)  NOT NULL DEFAULT '',
  `md5`         char(32)     NOT NULL DEFAULT '',
  `module`      varchar(30)  NOT NULL DEFAULT '',
  `method`      varchar(100) NOT NULL DEFAULT '',
  `account`     varchar(30)  NOT NULL DEFAULT '',
  `url`         varchar(255) NOT NULL DEFAULT '',
  `createdDate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_requestID_md5` (`requestID`,`md5`),
  KEY `idx_requestID` (`requestID`), KEY `idx_md5` (`md5`), KEY `idx_createdDate` (`createdDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

**取舍说明**：
- 结构逐列对齐 22.6（`db/zentao.sql:1297-1323`），便于后续比对。
- **引擎用 InnoDB**（22.6 同）：本仓现有表是 MyISAM（`db/zentao.sql` 里 InnoDB = 0），但这两张是"高并发写入 + 需要 `INSERT ... ON DUPLICATE KEY UPDATE` 幂等"的日志表，MyISAM 表级锁会在错误风暴时拖住整站。**这是有意的偏离，PR 里要写明**。
- `message/trace` 是 `text`（64KB 上限），落库前必须截断（见 5.3）。

**常量**：`config/zentaopms.php` 在 `TABLE_LOG`（`:106`）附近追加 `TABLE_ERRORLOG`/`TABLE_ERRORLOGREQ`（对应 22.6 `:312-313`）。

**升级**：`module/upgrade/model.php` 新增 `addErrorLogTables()`，范式照 §1.4；`db/zentao.sql` 同步追加（建议放在 `:897-910` 的 `zt_log` 之后）。

**cron 行（幂等插入，注意本仓 `zt_cron` 没有 `timeout` 列）**：本仓 `zt_cron` 结构为 `id,m,h,dom,mon,dow,command,remark,type,buildin,status,lastTime`（`db/zentao.sql:205-220`）—— **不能照抄 22.6 的 INSERT 列清单**。
- 安装：在 `db/zentao.sql:937` 那行后追加 `('*/5','*','*','*','*','moduleName=errorlog&methodName=deleteLog','删除过期错误日志','zentao',1,'normal','0000-00-00 00:00:00'),`
- 升级：`addErrorLogCron()`，按 `command` 判重后 insert；`lastTime` 必须写 `'0000-00-00 00:00:00'`，否则 `module/cron/model.php:152-156 checkChange()` 不会让运行中的进程重载。

### 5.3 框架侧改动（关键，风险集中处）

**先给出本仓的错误捕获链（全部已核实）**

| 环节 | 位置 | 说明 |
|---|---|---|
| 注册句柄 | `framework/base/router.class.php:613-617 setErrorHandler()` | `set_error_handler([$this,'saveError'])` + `register_shutdown_function([$this,'shutdown'])` |
| 注册时机 | `framework/base/router.class.php:364`（构造函数） | **在 `:369 connectDB()` 之前** → 错误处理时 `$this->dbh` 可能为空，必须判空 |
| 子类覆盖 | `framework/router.class.php:118-123 saveError()` | 对 `E_ERROR/E_PARSE/E_CORE_ERROR/E_USER_ERROR` 强制 `debug=true` 后调 parent |
| 主实现 | `framework/base/router.class.php:2210-2290 saveError()` | `:2212 if(empty($this->config->debug)) return true;`；`:2244` 写 `php.Ymd.log.php`；`:2270` 致命错误 die |
| 未捕获致命错误 | `framework/base/router.class.php:2158-2170 shutdown()` | `error_get_last()` → `:2169` 调 `saveError` |
| 主动触发 | `framework/base/router.class.php:2183-2196 triggerError()` | `:2186 htmlspecialchars()`，`:2193` 把 `<p>` 拼进 message，`:2196 trigger_error(..., E_USER_ERROR)` |
| **数据库错误** | `lib/base/dao/dao.class.php:1380-1393 sqlError()` | PDOException → `:1393 $this->app->triggerError($message."<p>The sql is: $sql</p>", ...)` |
| 落库通道 | `framework/base/router.class.php:2098-2125 connectByPDO()` | `new PDO(...)`，`ERRMODE_EXCEPTION`（`:2137`），`$this->dbh` 即 PDO 实例 |

**改动 1：新增 4 个类属性**（`framework/base/router.class.php` 属性区，对应 22.6 `:447/455/463/471`）

```php
public $requestID            = '';
public $savingErrorLog       = false;   // 落库重入保护
public $savedErrorLogBodies  = array(); // 已写入的本体 md5
public $savedErrorLogRecords = array(); // 已写入的 requestID|md5
```

**改动 2：`saveError()`(`:2210`) 在 `:2212` 之前插一句，其余一行不动**

```php
public function saveError($level, $message, $file, $line)
{
    /* 新增：写库与 debug 无关；用 error_reporting() 过滤，尊重 @ 抑制与掩码 */
    if(error_reporting() & $level) $this->saveErrorLog($level, $message, $file, $line);

    if(empty($this->config->debug)) return true;   // ← 原 :2212，保持不变
    ...
}
```
为什么必须加 `error_reporting() & $level`：`www/index.php:15` 是 `error_reporting(E_ALL)`；9.8.3 内核在 PHP 8.5 下 notice/deprecated 极多，不加过滤会瞬间打满表。**该判断只包在 `saveErrorLog` 外层，文件日志分支完全没动**（保证文件日志行为 100% 不变）。

**改动 3：新增 `saveErrorLog($level,$message,$file,$line,$trace='')`**（插在 `saveError` 之后）

实现要点（蓝本 22.6 `framework/base/router.class.php:3486-3545`，逐条按本仓差异改写）：
1. **开关与自噬保护**（全部 `return`，不抛错）：
   ```php
   if(!defined('TABLE_ERRORLOG') or !defined('TABLE_ERRORLOGREQ')) return; // 老库未升级
   if($this->savingErrorLog)            return;   // 重入
   if($this->moduleName == 'errorlog')  return;   // 防自噬
   if(empty($this->dbh))                return;   // 构造函数 :364 注册、:369 才连库
   $this->loadErrorLogConfig();
   if(empty($this->config->errorlog->enabled))     return;
   if(!($level & $this->config->errorlog->levels)) return;
   ```
2. **requestID**：22.6 从 `HTTP_X_ZIN_REQUEST_ID` 取，本仓无该头 → `$this->requestID = 'req-' . md5(uniqid('', true));`
3. **路径脱敏**：`$basePath = $this->getBasePath();`（`:638`）非空时对 message/file/trace 做 `str_replace($basePath, '', ...)`。
4. **消息净化（本仓特有，必须做）**：9.8.3 的 `triggerError()` 先 `htmlspecialchars`（`:2186`）再拼 `<p>The sql is: ...</p>`（`:2193`）→
   ```php
   $message = strip_tags(htmlspecialchars_decode($message));   // 得到 "The sql is: SELECT ..."
   if(!mb_check_encoding($message, 'UTF-8')) $message = helper::convertEncoding($message, 'GBK', 'UTF-8');
   ```
   （`helper::convertEncoding` 在 `framework/helper.class.php:89-105`，已是 UTF-8 的串原样返回，可安全兜底。）
5. **堆栈裁剪**：致命错误消息自带 `Stack trace:` → 切出来；只留前 20 帧；`mb_substr` 到 8000 字符。
6. **md5 与去重**：`$md5 = md5(implode("\n", array($file,(string)$line,(string)$level,$dbMessage)));`；`$recordKey = $this->requestID.'|'.$md5;` 命中内存数组直接 return（同请求同 md5 **不发任何 SQL**）。
7. **写库（PDO 绑定，不用 `formatSQL`）**：
   ```php
   $this->savingErrorLog = true;
   try
   {
       if(!isset($this->savedErrorLogBodies[$md5]))
       {
           $stmt = $this->dbh->prepare('INSERT INTO '.TABLE_ERRORLOG.' (`md5`,`file`,`line`,`level`,`message`,`trace`) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE `md5`=`md5`');
           $stmt->execute(array($md5, mb_substr($file,0,255), (int)$line, (int)$level, $dbMessage, $trace));
           $this->savedErrorLogBodies[$md5] = true;
       }
       $stmt = $this->dbh->prepare('INSERT IGNORE INTO '.TABLE_ERRORLOGREQ.' (`requestID`,`md5`,`module`,`method`,`account`,`url`,`createdDate`) VALUES (?,?,?,?,?,?,?)');
       $stmt->execute(array($this->requestID, $md5, (string)$this->moduleName, (string)$this->methodName, $account, mb_substr((string)$this->getURI(),0,255), helper::now()));
       $this->savedErrorLogRecords[$recordKey] = true;
   }
   catch(Throwable $e) { /* 错误发生时库可能已不可用：任何失败一律吞掉，绝不再次抛错 */ }
   finally { $this->savingErrorLog = false; }
   ```
   - `INSERT ... ON DUPLICATE KEY UPDATE md5=md5` 替代 22.6 的"prepare 重复键抛异常再吞"，避免用异常做流程控制。
   - `$account = isset($_SESSION['user']->account) ? $_SESSION['user']->account : '';`
   - **不能用 `$this->dao`**：dao 的错误会再走 `sqlError → triggerError → saveError`，必然递归。

**改动 4：新增 `loadErrorLogConfig()` —— 这里有一个必须避开的坑**

不能直接在错误处理里 `$this->loadConfig('errorlog')` 并让 `module/errorlog/config.php` 写 `$lang`：构造函数里 `loadLang('common')` 在 `:373` 才执行，**比错误句柄注册（`:364`）晚**；若 `$lang` 仍是 null 而 config.php 执行 `$lang->errorlog = new stdclass()`，PHP 8 会致命错误；更糟的是 `framework/router.class.php:32-36 loadLang()` 只在 `!is_object($lang)` 时创建 `language` 对象 —— 被提前污染成 stdclass 会导致**语言加载整体错乱**。

**结论：`module/errorlog/config.php` 只写 `$config`，不碰 `$lang`**（文案全部放 `lang/zh-cn.php`）。router 里按需加载并给默认值：

```php
private function loadErrorLogConfig()
{
    if(isset($this->config->errorlog->levels)) return;
    global $config;
    $file = $this->getModulePath('', 'errorlog') . 'config.php';
    try { if(is_file($file)) include $file; } catch(Throwable $e) {}
    if(!isset($config->errorlog->levels))
    {
        $config->errorlog = new stdclass();
        $config->errorlog->enabled = true;
        $config->errorlog->levels  = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING;
    }
}
```
默认掩码**刻意排除** `E_NOTICE/E_DEPRECATED/E_USER_DEPRECATED/E_STRICT`（9.8.3 内核在 PHP 8.5 下 deprecated 极多）。

### 5.4 模块侧改动

新建 `module/errorlog/`：`config.php`、`control.php`、`model.php`、`lang/zh-cn.php`、`lang/en.php`、`view/browse.html.php`、`view/view.html.php`、`view/setting.html.php`。

| 我们的实现 | 22.6 蓝本 | 必须改写的地方 |
|---|---|---|
| `control::browse($type,$queryID,$orderBy,$recTotal,$recPerPage,$pageID)` | `control.php:28` | `$this->errorlogZen->buildSearchForm(...)` → `$this->errorlog->buildSearchForm(...)` + `search->setSearchParams`（照 `module/doc/model.php:754-767`）；`$orderBy` 必须**白名单**（22.6 直接把请求参数丢进 SQL） |
| `control::view($id)` | `:62` | `$this->sendError(...)` **本仓不存在** → `die(js::error($this->lang->errorlog->notFound) . js::locate('back'))`（同 `module/doc/control.php:408`） |
| `control::setting()` | `:141` | `form::data()` → `fixer::input('post')`；保存用 `setting->setItem('system.errorlog.saveDays', (string)$days)`（同 `module/admin/control.php:326-330`） |
| `control::delete($id)` / `batchDelete()` | `:110/124` | 返回 `$this->send(array('result'=>'success','message'=>$lang->deleteSuccess,'load'=>true))`（`load` 是 9.8.3 ajaxForm 既有约定，`module/doc/control.php:527` 同款） |
| `control::deleteLog()` | `:164` | 保留；`date(DT_DATE1, strtotime(...))`（同 `module/admin/control.php:352`） |
| `control::ajaxGetLog()` | `:82` | **一期删除**：22.6 靠 `config/privilege.php` 的 `logonMethods` 放行，本仓无该文件 |
| `model::getList/getByID/getByRequestID/getLevelType/deleteByID/deleteByIDs/deleteByDate/deleteOrphanErrorLogs` | `:35/54/70/87/105/124/142/155` | 直接可用（`beginIF`/`subNotIn`/`fetchPairs` 本仓都有） |
| `model::buildSearchForm` | `errorlogZen` | 自写：`$config->errorlog->search` + `search->setSearchParams` |

`module/errorlog/config.php`：`enabled=true`、`saveDays=7`、`maxSaveDays=30`、`levels`（掩码）、`$config->errorlog->search`（结构照 `module/doc/config.php:28-52`）。**不写 `$lang`**。

**改 3 个既有文件（菜单/权限）**
1. `module/common/lang/zh-cn.php`：`:363-373` 后台菜单块加 `$lang->admin->menu->errorlog = array('link'=>'错误日志|errorlog|browse','subModule'=>'errorlog');`；`:393-403` 加 `$lang->errorlog->menu = $lang->admin->menu;`；`:405-436` 加 `$lang->menugroup->errorlog = 'admin';`
2. `module/group/lang/resource.php`：照 `:1063-1078` cron 的写法加 5 个权限位（`browse/view/delete/batchDelete/setting`）+ `methodOrder` + `$lang->moduleOrder[205] = 'errorlog';`（`:59` cron 是 190，取未占用序号）。
3. 非 admin 组还需在"视图维护"勾上 `admin` 视图，否则 `commonModel::hasPriv()` 的 `if(!isset($acls['views'][$menu])) return false;`（`module/common/model.php:1419`）会拒绝 —— 与 `cron`/`backup` 行为一致。

### 5.5 前端改动

1. **`module/errorlog/view/browse.html.php`（新增）**：`header.html.php` + `#featurebar`（全部/Fatal/Warning + 搜索 tab + 右侧"保存天数"按钮）+ `<div class='main-table' data-ride='table'>` + `table has-sort-head datatable`（datatable 接线照 `module/bug/view/browse.html.php:180-208`）+ 级别彩色标签（`getLevelType()` 返回 `danger|warning|primary`）+ 行内详情/删除 + 批量删除 + `.table-footer` 分页 + `.table-empty-tip` 空态。
2. **`module/errorlog/view/view.html.php`（新增）**：iframe 弹窗，骨架照 `module/tree/view/edit.html.php:18-22`；堆栈放 `<pre style='max-height:60vh;overflow:auto'>`；**输出 message 时必须 `htmlspecialchars()`**（落库时已解码剥标签）。
3. **`module/errorlog/view/setting.html.php`（新增）**：照 `module/admin/view/log.html.php:13-33`（`ajaxForm` + `table table-form` + `html::input('days')` + `html::submitButton()`）。
4. **datatable 配置**：照 `module/bug/config.php:168-...` 的字段格式，`defaultField = array('id','level','module','method','account','message','createdDate','actions')`。
5. `module/common/view/header.html.php` **无需改**（后台入口由 `$lang->adminMenu` 与菜单 lang 驱动）。

### 5.6 22.6 参照索引

| 位置 | 借鉴 |
|---|---|
| `module/errorlog/model.php:35-77` | `getList/getByID/getByRequestID` 的联表与 `getSelectFields()` 抽象 |
| `module/errorlog/model.php:105-178` | 删除语义：删 req → `deleteOrphanErrorLogs()` 用 `subNotIn` 清孤儿本体 |
| `module/errorlog/model.php:87-95` | `getLevelType()` 位运算映射 danger/warning/primary |
| `module/errorlog/control.php:141-155` | setting：范围校验 `1..maxSaveDays`、写 `system.errorlog.saveDays` |
| `module/errorlog/control.php:164-169` | `deleteLog()` cron 入口 |
| `module/errorlog/config.php:1-25` | enabled/saveDays/maxSaveDays + search 结构 |
| `module/errorlog/lang/zh-cn.php:36-51` | `levelList` 用 `E_*` 常量 |
| `framework/base/router.class.php:3486-3545` | `saveErrorLog()` 全流程 |
| `framework/base/router.class.php:3363`（调用点）/`3375`（`error_reporting` 守卫） | 挂钩位置与守卫 |
| `db/zentao.sql:1297-1323` / `:2485` | 两表 DDL / cron 行文本 |
| `module/errorlog/ui/*.html.php`（4 个 zin 模板） | **只读作信息架构参考**，不搬语法 |

### 5.7 分步实施与检查点

1. **DDL + 常量 + 升级幂等 + cron 双处种子** → 新库 `DESC` 两表存在；老库升级后存在且**重复执行不报错**；`zt_cron` 有且仅 1 行。
2. **框架落库（改动 1-4）** → `debug=false` 时故意触发 warning：有落库、`tmp/log/php.Ymd.log.php` **无**新行；`debug=true` 时两者都有；message 无绝对路径。
3. **递归/库不可用自测** → 把 `db->name` 改成不存在的库：不白屏、无递归；把 `zt_errorlog` RENAME 掉：行为与改动前一致（仅少一条落库）。
4. **模块 + 菜单/权限位** → admin 可见"后台 → 错误日志"；列表/排序/分页/筛选可用；删掉 `zt_errorlog` 表后访问该页：报错但**不产生新日志**（自噬保护生效）。
5. **前端 3 视图 + datatable 配置** → 列自定义可保存（`zt_config` 出现配置）；详情弹窗堆栈不撑破。
6. **cron 清理联调** → 手工插 30 天前的 req + 本体：执行 `errorlog|deleteLog` 后超期 req 与孤儿本体被删；被其他请求引用的本体**不误删**。
7. **回归** → `admin->checkdb` 通过；MCP 接口未受影响。

### 5.8 风险与回退

| 风险 | 等级 | 缓解 | 回退 |
|---|---|---|---|
| 错误风暴打满表 / 拖慢请求 | 🔴 高 | 掩码默认排除 notice/deprecated；`ON DUPLICATE KEY UPDATE` + `INSERT IGNORE`；同请求内存去重 | `$config->errorlog->enabled = false`（一行）；或 RENAME 表 |
| 错误处理内再抛错 → 递归/白屏 | 🔴 高 | `savingErrorLog` 重入锁 + `try/catch(Throwable)` 全吞 + `finally` 复位 + `dbh` 判空 | 删 `saveError` 里新增的那一行（一行 diff） |
| 改变现有文件日志行为 | 🟡 中 | 只新增调用，不动 `:2212` 之后任何一行 | 同上 |
| 表结构偏离 MyISAM 约定 | 🟡 中 | 仅这两张日志表用 InnoDB，PR 写明理由 | `ALTER TABLE ... ENGINE=MyISAM` |
| 常量未定义导致 PHP 8 Error | 🟡 中 | 落库前 `defined()` 判断 return | 设计即容错 |
| 数据库结构改动（红线） | 🔴 高 | 只新增两张表 + 一行 cron + 常量 | `DROP TABLE` 两表 + 删 cron 行 |

### 5.9 工作量分解

| 步 | 内容 | 人时 |
|---|---|---|
| 1 | DDL/常量/`zentao.sql`/升级幂等/cron 双处种子 | 2.0 |
| 2 | 框架 4 属性 + `saveError` 挂钩 + `saveErrorLog` + `loadErrorLogConfig` | 4.0 |
| 3 | 递归与"库不可用"专项自测 | 1.5 |
| 4 | 模块 7 个文件 | 5.0 |
| 5 | 菜单与权限位 + 联调 | 1.0 |
| 6 | 前端 3 视图 + datatable 配置 | 4.0 |
| 7 | 9 条验收 + 回归 | 2.5 |
| 8 | PR 描述、影响面与回退说明 | 1.0 |
| **合计** | | **21.0** |

---

## 6.0 实施记录（2026-10-16）

**已交付（提交 `5b09120`，禅道 `task #81`）**

| 项 | 实测证据 |
|---|---|
| DDL：`zt_doc.order` + `zt_doclib.type` + `zt_docaction` + 模板库种子 | 升级方法**连续执行两次**无报错、模板库仍只有 1 行 |
| 6 个 `getLibs` 系函数排除模板库 | 文档主页/全部库/我的文档/项目库页均不再出现「文档模板」 |
| 目录排序 `sortCatalog`（转调 `tree::updateOrder`）、文档排序 `sortDoc` | 拖拽 JS 用 ZUI 既有 `sortable`；列表行补 `data-id` |
| 移动 `moveDoc` | 实测把 doc 2 从 lib 18 移到 lib 5，`lib/module/product/project` 四列同步正确 |
| 复制 `copyDoc` | 标题加「（副本）」、正文取源最新版、`views=0`/`collector=''`/`version=1` |
| 最近浏览 | `zt_docaction` 落 `view` 动作 + `views` 自增；`browseType=visitedbyme` 列表实测列出刚看过的文档 |

**未完成（下一批）**：§6.4-A 的模板分类与模板 CRUD（6 个 model 方法 + 7 个 control 方法）、§6.5 的 `browsetemplate`/`addtemplatetype`/模板表单三个视图。**注意**：`create()` 里已按 22.6 初始化 `order`，模板库与模板过滤已就绪，模板功能可直接接上。

**实施偏差（与本文原设计的差异，均经实测）**

1. **「用例试点」一开始被我跳过，后来补做——而且它是真 bug 修复，不是重构**（`c15539a`）。跳过时的理由是「只是验证抽象、改写既有可用链路无用户可见收益」，**这个判断错了**：旧实现只做列名精确匹配，带 UTF-8 BOM 的文件首列名变成 `\xEF\xBB\xBF所属模块` → 一列都匹配不上 → 落 gbk 兜底把已经是 utf-8 的内容再转一次成乱码 → 报「编码错误」。实测确认后，`testcase` 与 `testsuite` 的 import/showImport 四处都改走 `file->parseImportFile`；顺带修掉旧实现「把表头字段压成顺序列表按下标取值」的列错位隐患（用户调换列顺序会串值）。`parseCSV` 本体仍未改一行。
2. **新增两处 parseCSV 的前置规整**（`normalizeImportCSV`/`trimImportRows`）：`parseCSV` 处理不了「行首是空引号单元格」的行（模板里空着的「编号」列正是这种），会把前两格粘成一格导致整行列错位。规整放在 `parseImportFile` 内，`parseCSV` 本身不动。
3. **预览页做成只读**（原计划照 9.8.3 用例的「可编辑预览表单」）。只读预览同样满足验收标准第 3 条（逐行给出行号/值/错误原因，错误行不写库），且彻底避开 `suhosin`/`maxVars` 那一整类问题。
4. **需求导入去掉「当前状态」列**：CSV 能直接写「激活」会绕过评审（§7.7 列为 🔴 高风险）。改为状态一律由 `$config->story->needReview` 决定（开启则落 `draft`），实测导入后状态为 `draft`。
5. **任务/Bug/需求导入的「所属项目/产品」列**：不匹配时给错误而不是静默改用当前对象；空值回退到导入目标（URL 上的 projectID/productID）。
6. **`zt_case` 无 `project` 列**（9.8.3 结构，22.6 才有）——需求矩阵的用例列按 `product` 归属，不再查 `project`。
7. **`helper::substr($str, $len, $append)` 只有两个参数**，误传三参会渲染出 `$append` 的字面值（矩阵页第一版就踩了这个坑）。

## 6. 功能 5：文档/知识库增强（一期）—— 31 人时

### 6.1 目标与验收标准

**一期范围（只做低垂果实，不做块编辑器）**
1. **文档模板**：模板分类 CRUD + 模板 CRUD + 模板列表 + "从模板创建文档"。
2. **目录/文档树操作**：目录排序、文档排序、移动、复制、批量移动。（**"删除目录回收文档"已从本期移除**：根因是 `getPrivDocs` 的 `module IN ('0')`，已在 M0 用 22.6 的原生写法修掉；22.6 的 `tree::remove()` 同样不回收 doc，见 §6.2 与 §14.13。）
3. **已读/收藏**：**明确结论——不做"已读/未读"标记**（22.6 全库没有未读字段，`mark` 实为 `zt_docaction` 动作流水）。一期引入 `zt_docaction` 只用于"最近浏览"（存在 `action='view'` 即已读），并**保留**我们已移植的 `collector` 收藏机制不动。

**验收标准**

| # | 场景 | 期望 |
|---|---|---|
| 1 | 模板分类 | 可增/改/删两级分类；分类下有模板时删除被拒并提示（对应 22.6 `control.php:708-728`） |
| 2 | 模板 CRUD | 模板**不出现**在普通文档库列表/搜索/`getDocs()` 结果里 |
| 3 | 从模板创建 | 新文档正文 = 模板内容，版本从 1 起，**附件不继承**（一期明确） |
| 4 | 目录排序 | 拖拽后顺序持久（写 `zt_module.order`，`type='doc'`） |
| 5 | 文档排序 | 拖拽后顺序持久（写新增的 `zt_doc.order`） |
| 6 | 移动/复制/批量移动 | 移动后 `lib/module/product/project` 一致更新；复制后标题带"(副本)"、正文为源最新版、`views=0`、`collector` 清空、`version=1` |
| 7 | **删除目录** | 子目录一并软删；目录内文档**在"全部"视图仍可见**（不再"消失"）。**注：不回收文档的目录归属**——根因已在 M0 用 `getPrivDocs` 的 22.6 写法修掉，22.6 亦不回收（§14.13） |
| 8 | 最近浏览 | `view` 动作被记录（4 秒内重复访问不重复记）；`zt_doc.views` 自增 |
| 9 | 收藏 | 既有 `collect` 行为不回归 |
| 10 | 权限 | 新增方法都有权限位 |

### 6.2 数据模型变更

**结论先行（纠正题面前提）：不要给 `zt_doc` 加 `catalog`/`path`/`grade`，也不要新建目录表。**

证据：
- 我们的"目录"**已存在** = `zt_module`（`root=libID`、`type='doc'`）+ `zt_doc.module`：`module/doc/model.php:1327 getDocTree()` 调 `tree->getTreeStructure($libID,'doc')`；`:792 getDocMenu()` 直接查 `TABLE_MODULE` 且 `type='doc'`；`:590` 用 `TABLE_MODULE` 解析 `moduleName`。
- 22.6 **也是这么做的**：`module/doc/model.php:4159 updateOrder()` 更新的是 `TABLE_MODULE` 且 `type='doc'`；`control.php:1755 editCatalog()` = `fetch('tree','edit',...)`；`:1768 deleteCatalog()` = `fetch('tree','delete',...)`。22.6 `zt_doc.parent/path/grade` 是**文档内章节**，与目录无关。
- 本仓 `zt_doc` 确实无任何一列（`grep` 为 0）。
- 本仓 `tree` 模块已具备目录 CRUD 全部能力：`module/tree/control.php:216 edit`、`:277 updateOrder`、`:294 manageChild`、`:312 delete`；`model.php:1193 updateOrder`、`:1228 manageChild`、`:1382 delete`；权限位已注册（`module/group/lang/resource.php:876-890`）。
- 本仓 doc 视图**已有目录操作入口**：`module/doc/view/browsebymenu.html.php:51`（编辑目录）、`:52`（删除目录）、`:55`（维护分类）、`:86`（增加分类）。

**因此只做 3 项变更**

**变更 1：`zt_doc` 加 `order`（文档排序）**
```sql
ALTER TABLE `zt_doc` ADD COLUMN `order` int unsigned NOT NULL DEFAULT 0;
```
必须**反引号**（保留字）；`dao->set('`order`')` 与 `orderBy('order_asc')` 都可用（`lib/base/dao/dao.class.php:1993-2011` 会自动补反引号 + 正则白名单）。新文档 `order` 初始化 = 自身 id（照 22.6 `model.php:1875`）。

**变更 2：`zt_doclib` 加 `type`（模板范围）**
```sql
ALTER TABLE `zt_doclib` ADD COLUMN `type` varchar(30) NOT NULL DEFAULT '';
```
理由：22.6 的模板必须挂在 doclib 上（`module/doc/config.php:17` 的 `requiredFields='lib,title'`），模板范围来自 `getTemplateScopes()`（22.6 `model.php:4934-4937`，条件 `zt_doclib.type='template'`）。本仓 `zt_doclib` 没有 `type` 列，无法表达"模板范围库"、也无法把它从普通库列表排掉。加这一列后模板可 1:1 复用 22.6 的 `getDocTemplateList/getTemplateScopes`，且 `zt_doc.lib` 仍是合法外键（模板天然获得版本、附件、ACL、动作日志）。
**不推荐**"另建目录表/模板表"：会多出两套树/版本逻辑，与 `tree` 模块、`zt_doccontent` 版本机制全部重复。

**变更 3：新表 `zt_docaction`（浏览/收藏动作）**
```sql
CREATE TABLE IF NOT EXISTS `zt_docaction` (
  `id`     int unsigned NOT NULL AUTO_INCREMENT,
  `doc`    int unsigned NOT NULL DEFAULT 0,
  `action` varchar(80) NOT NULL DEFAULT '',
  `actor`  varchar(30) NOT NULL DEFAULT '',
  `date`   datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doc` (`doc`), KEY `actor` (`actor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

**种子数据（1 行）**
```sql
INSERT INTO zt_doclib (product,project,name,acl,groups,users,main,collector,`order`,deleted,type)
VALUES (0,0,'文档模板','open','','','1','',0,'0','template');
```

**必须同步的过滤点（否则模板库污染普通库列表）**：`module/doc/model.php:199 getLibs()`、`:952 getAllLibsByType()`、`:995 getAllLibGroups()`、`:1081 getLimitLibs()`、`:1127 getSubLibGroups()`、`:1169 getLibsByObject()` —— 统一加 `->andWhere('type')->ne('template')`。

**升级方法（4 个，范式照 §1.4）**：`addDocOrderColumn()`、`addDoclibTypeColumn()`、`createDocActionTable()`、`seedTemplateLib()`（`SELECT COUNT(*) WHERE type='template'` 为 0 才插）。`db/zentao.sql` 同步。

**对既有函数的影响面**

| 函数 | 影响 | 改动 |
|---|---|---|
| `module/doc/model.php:600 create()` | 中 | 插入后补 `order`；新增 `createTemplate()` 分支 |
| `:666 update()` | 低 | 模板走同一 update，`requiredFields` 用 `createTemplate` 组 |
| `:496 getDocs()` | 低 | 排序白名单放行 `order_asc` |
| `:792 getDocMenu()` | 低 | 已支持 `$orderBy` 参数 |
| `:540 getById()` | 低 | 模板复用；`:590` 的 moduleName 解析对模板分类同样有效 |
| **`module/doc/model.php:515 getPrivDocs()`** | **必改（Bug 修复，M0 已完成）** | 根因不在 tree 而在文档权限查询：10.1 原写法 `->andWhere('module')->in($module)` 在 `$module = 0` 时退化为 `module IN ('0')`（`helper::dbIN(0)` → `IN ('0')`），三条后果：①"全部"视图只列无目录文档；②删目录后其文档彻底不可达（树里没节点、列表里被过滤）；③`getStatisticInfo()` 的"所有文档"少算。**22.6 已修**：`->beginIF(!empty($modules))->andWhere('module')->in($modules)->fi()`。M0 已按此对齐（1 行 + 注释），A/B 实测：旧写法目录内文档=0、修复后=1；按目录筛选（bymodule）语义不变 |
| `module/tree/model.php:1382 delete()` | **不改** | 原计划要在此回收文档，实测推翻：22.6 的 `tree::remove()`（等价方法）**也没有 `case 'doc':`**，即上游有意不回收；且 `getPrivDocs` 修复后文档在"全部"视图仍可见、不再不可达。改动会偏离上游且影响 story/bug/case/task 共用的核心方法 ⇒ 放弃（若一期文档增强时确需，再单独评估） |

### 6.3 框架侧改动

**无。** 目录复用 `zt_module`（`tree` 模块已具备能力）；排序用 `dao->orderBy()` 既有校验；排序交互用 ZUI 1.8.1 既有 `sortable`（本仓已在用：`module/doc/js/common.js:27`）；弹窗用既有 `data-toggle='modal' data-type='ajax'|'iframe'`。

### 6.4 模块侧改动

**A. 文档模板**

| 我们的方法 | 22.6 蓝本 | 改写要点 |
|---|---|---|
| `control::browseTemplate(...)` | `control.php:610-658` | 去掉三个"内置模板"种子方法（一期不做内置模板，只做种子范围库） |
| `control::createTemplate($moduleID=0)` | `:744-774` | `form::data()` → `fixer::input('post')`；`sendError` → `send(array('result'=>'fail',...))` |
| `control::editTemplate($docID)` | `:780-822` | `docZen->responseAfterEditTemplate` → 直接 `send(array('result'=>'success','message'=>$lang->saveSuccess,'closeModal'=>true,'load'=>true))` |
| `control::deleteTemplate($templateID)` | `:1145-1158` | 保留；附件不继承 |
| `control::addTemplateType($scope,$parentModule='')` | `:660-706` | `form::data(...)` → `fixer::input('post')->setDefault('type','docTemplate')->setDefault('path','')->get()`；保留 `grade = $parent==0 ? 1 : 2` |
| `control::editTemplateType($moduleID)` | `:731-735` | **直接 `echo $this->fetch('tree','edit',"moduleID={$moduleID}&type=docTemplate");`**（已核实 `module/tree/view/edit.html.php:26` 对 `type != story/bug/case` 不渲染产品行） |
| `control::deleteTemplateType($moduleID)` | `:708-728` | 有模板则拒删；否则软删 `type='docTemplate'` 的模块 |
| `model::getTemplateModules/addTemplateType/getTemplateScopes/getDocTemplateList/getTemplatesByType/deleteTemplate` | `model.php:4548/4661/4934/675/4681/4643` | 照抄，去掉 `vision`/`builtIn`/`status` 条件 |
| `model::createTemplate($data)`（自写） | `model.php:1818-1897`（仅取 insert 部分） | 只 insert `TABLE_DOC` + `TABLE_DOCCONTENT`，避免带入 `removeDuplicate`/`processImgURL`/`score` 副作用 |

**B. 目录/文档树操作**

| 我们的方法 | 22.6 蓝本 | 改写要点 |
|---|---|---|
| `control::sortCatalog()` | `control.php:1846-1862` | 直接转调 `tree->updateOrder($orders)`（`module/tree/model.php:1193` 会按 parent/grade 归一为 10 的倍数），**不自写**；`$orders` 需 `json_decode` + `is_numeric` 校验 |
| `control::sortDoc()` | `:1823-1841` | 新增；`doc->updateDocOrder($orders)` 照 22.6 `model.php:4132-4148` |
| `control::sortDoclib()` | `:1802-1818` | **已存在**：`module/doc/control.php:518-529 sort()` 就是它，前端也已就绪（`module/doc/js/common.js:27-55`）。一期只需统一命名/补权限位，**不要重复实现** |
| `control::moveDoc($docID,$libID=0)` | `:1945-1992` | 去掉 `docZen->initDocContext/prepareDocFormData/responseAfterMove`；`fixer::input('post')` 取 `lib,module`；成功后 `action->create('doc',$docID,'Moved')` |
| `model::moveDoc($docID,$data)`（自写 6 行） | 22.6 `doUpdateDoc` | 取目标 lib → `update TABLE_DOC set lib,module,product=lib->product,project=lib->project` |
| `control::copyDoc($docID,$libID=0)` | `:1995-2047` | 同上简化；返回新 ID；`action->create('doc',$newID,'Copied')` |
| `model::copyDoc($docID,$data)`（自写） | 22.6 `copyDoc` | insert 新 `zt_doc`（标题加 `(副本)`、`views=0`、`collector=''`、`version=1`）+ `zt_doccontent`（复制源最新版正文，`files=''`）。**一期不复制附件**（列二期） |
| `control::batchMoveDoc($encodeDocIdList,$libID=0,$moduleID=0)` | `:2123-2191` | 去掉 `execution->getPairs`（本仓无 execution 模块）；目标库用 `getLibs()`；保持 `base64_encode(json_encode())` 传参以便复用前端 |
| `control::moveLib()` | `:1899-1943` | **一期不做**（22.6 是"空间之间搬库"，我们无 spaces 概念）。要"把库挂到别的产品/项目"用既有 `editLib`（`module/doc/control.php:225`） |

**C. 已读/收藏**
- 不做"未读"标记（见 6.1）。
- `control::view()`（`module/doc/control.php:405`）末尾加 `$this->doc->createAction($docID,'view');`
- `model::createAction/getActionByObject/deleteAction`：照 22.6 `model.php:3803-3868`（含 `view` 动作 4 秒节流、`view` 时 `views+1`）。
- **收藏保持现状**：`module/doc/control.php:486-514 collect()` 写 `zt_doc.collector`（已移植的 10.1 方案），**不要改造成 `zt_docaction`**，否则破坏已上线数据与 `module/doc/view/side.html.php` 入口。
- 新增"最近浏览"：`browse` 加 `browseType=visitedbyme` 分支（`getDocsByBrowseType` 加 1 个 elseif，按 `zt_docaction` 的 `MAX(id)` 排序，蓝本 22.6 `model.php:1203-1230`）。
- 权限位：`module/group/lang/resource.php` 的 doc 块（`:692-723`）补 13 个方法。

### 6.5 前端改动

现有 17 个视图与 10.1 版逐字节一致（`diff -q` 验证过）。

| 视图 | 处置 | 说明 |
|---|---|---|
| `browse.html.php`(144) | 改 | 加"排序模式"开关与批量移动入口；`orderBy` 支持 `order_asc` |
| `browsebymenu.html.php`(102) | 改 | `:41-58` 的 cog 菜单已是目录操作入口；新增"目录排序"入口（删除确认文案沿用现有） |
| `browsebytree.html.php`(113) | 改 | 同 browsebymenu |
| `browsebygrid.html.php`(71) | 轻改 | 卡片视图加"复制/移动" |
| `side.html.php`(145) / `index.html.php`(187) | 复用 | 左树/首页面板无需改（如需"最近浏览"仅加 1 个 `<li>`） |
| `create.html.php`(88) | 改 | 加"从模板创建"选择器 |
| `edit/view/createlib/editlib/alllibs/alllibsbylist/objectlibs/objectlibsbylist/showfiles/footer` | 复用 | 服务端已过滤模板库 |
| **`browsetemplate.html.php`** | **新增** | 模板列表：`#featurebar`（全部/我创建的）+ `main-table` + datatable + 分类树；行操作 编辑/删除/复制到文档 |
| **`addtemplatetype.html.php`** | **新增** | 分类新增弹窗（照 `module/tree/view/edit.html.php:18-22`） |
| **`movedoc.html.php` / `batchmovedoc.html.php`** | **新增** | 移动/复制弹窗：目标库 select + 目标目录 select（`ajaxGetModules` 已有，`module/doc/control.php:538`） |

**交互接线（不引新依赖）**：排序用 `$('#docMenu').sortable({...})` → POST `doc/sortCatalog`（ZUI sortable 用法蓝本 `module/doc/js/common.js:27-55`）；弹窗一律 `data-toggle='modal'`；列表用 `main-table` + `has-sort-head` + `$pager->show('right','pagerjs')`。

### 6.6 22.6 参照索引

| 位置 | 借鉴 |
|---|---|
| `module/doc/control.php:610-658 / 660-706 / 708-728 / 731-735 / 744-774 / 1145-1158` | 模板列表/分类增删改/复用 tree 弹窗/模板 CRUD |
| `module/doc/control.php:1755 / 1768 / 1802 / 1823 / 1846` | editCatalog/deleteCatalog/sortDoclib/sortDoc/sortCatalog 职责划分 |
| `module/doc/control.php:1945-2047 / 2123-2191` | moveDoc/copyDoc/batchMoveDoc 表单字段与动作记录 |
| `module/doc/model.php:4548 / 4661 / 4681 / 4934` | 模板分类与范围的 SQL（去掉 vision 即可用） |
| `module/doc/model.php:675-690` | `getDocTemplateList`（去掉 builtIn/vision/status） |
| `module/doc/model.php:4117-4162` | 三种排序（证明目录复用 `zt_module`） |
| `module/doc/model.php:3803-3868` | view/collect 动作流水与 4 秒节流 |
| `module/doc/model.php:1199-1258` | `getMySpaceDocs`（`MAX(id)` 取最近动作） |
| `module/doc/lang/zh-cn.php:76-115 / 246-249` | 模板字段与目录操作文案 |
| `module/doc/ui/*.html.php` | **只读作信息架构参考**，不搬 zin |

**`execution` 调用点改写（一期只需 6 处）**：22.6 doc 的 execution 引用极多（control 46 处 / model 168 处），但一期只涉及：`control.php:2155`（batchMoveDoc 目标执行）→ 删行，改用 `getLibs('all')`；`:1738`（ajaxGetExecution）→ 一期不实现；`:419-421/514-520`（execution 层级拼装）→ 删；`model.php:139/248-266`（getExecutionLibPairsByProject）→ 不实现；`model.php:2940-2980`（getLinkedExecutionData）→ 不实现；`model.php:1207/1225`（查询里的 type 过滤）→ 保留 product/project/custom 三态，去掉 execution 分支。

### 6.7 分步实施与检查点

| 步 | 内容 | 检查点 |
|---|---|---|
| 1 | DDL 2 列 + 1 表 + 种子行 + `zentao.sql` + 升级 4 方法 | 新库 `DESC` 含新列；老库升级**重复执行两次**无报错、种子行只有 1 条 |
| 2 | 6 个 getLibs 系函数过滤 | 文档首页/左树/`allLibs` 不出现"文档模板"库 |
| 3 | 模板分类 + 模板 CRUD（后端） | 分类下有模板时删除被拒；`zt_doc` 中模板行 `lib` = 模板库 ID |
| 4 | 模板前端 3 视图 | "从模板创建"正文与模板一致、`version=1`；模板不出现在普通列表/搜索 |
| 5 | 目录排序/移动/复制 | 目录拖拽后 `zt_module.order` 变化；移动后四列同时正确；复制后 `views=0/collector=''/version=1`；（删除目录不再改造，回归见 M0） |
| 6 | `zt_docaction` + `visitedbyme` 列表 | 连续打开同一文档 3 次（间隔 >4 秒）→ 3 条记录、`views+3`；4 秒内重复不新增 |
| 7 | 前端排序/弹窗接线 | 拖拽无 JS 报错；弹窗关闭后列表局部刷新 |
| 8 | 回归 | `checkdb` 通过；`collect`/`collectedbyme` 不回归；`getDocTree` 空库/有文档都正常 |

### 6.8 风险与回退

| 风险 | 等级 | 缓解 | 回退 |
|---|---|---|---|
| 改 `zt_doc`/`zt_doclib` 表结构（红线） | 🔴 高 | 两列均**追加列**（有默认值），不改既有列语义；`type` 默认 `''` 使既有 lib 行为不变 | 反向 DDL `DROP COLUMN` + `DROP TABLE zt_docaction` |
| 模板库污染普通库列表 | 🟡 中 | 6 个 getLibs 系函数统一过滤；`main='1'` 使 `deleteLib` 已受保护 | 把该行 `type` 置空即回普通自定义库 |
| `order` 保留字 | 🟡 中 | SQL 全部反引号；`dao->orderBy()` 自带白名单 | 移除 `orderBy('order_asc')` |
| 复制文档未复制附件 | 🟡 中 | 验收标准明确"一期不继承附件"，弹窗内提示 | 二期接 `file` 模块 |
| ~~删除目录回收文档~~ | — | **已移除**：改为在 M0 修 `getPrivDocs`（§14.13），不动 `tree::delete()` | 无需回退 |
| 模板与普通文档混表导致误统计 | 🟡 中 | 普通列表按 `lib` 过滤 + `module.type='docTemplate'` 反查；`getStatisticInfo()`（`module/doc/model.php:1519`）需加排除条件 | 隐藏模板菜单，数据仍安全 |

### 6.9 工作量分解

| 步 | 内容 | 人时 |
|---|---|---|
| 1 | DDL + 种子 + `zentao.sql` + 升级 4 方法 | 2.0 |
| 2 | 6 个 getLibs 过滤 + 回归 | 1.0 |
| 3 | 模板分类 3 + 模板 CRUD 4 + model 6 方法 | 6.0 |
| 4 | 模板前端 3 视图 | 4.0 |
| 5 | sortCatalog/sortDoc + 拖拽 JS 接线 | 2.5 |
| 6 | moveDoc/copyDoc/batchMoveDoc + 2 弹窗 | 4.5 |
| 7 | ~~tree 删除目录回收文档~~（已在 M0 用 1 行修复替代） | 0 |
| 8 | `zt_docaction` + 3 方法 + `visitedbyme` | 3.0 |
| 9 | 权限位/语言/菜单 | 1.5 |
| 10 | 10 条验收 + 回归 | 4.0 |
| 11 | PR 描述与回退说明 | 1.0 |
| **合计** | | **31.0** |

---

## 7. 功能 6-1：CSV 批量导入导出 —— 79 人时

### 7.1 目标与验收标准

**核实结论（与假设有出入）**：四个对象的**导出已全部存在**，真实缺口是「导入」+「模板下载」+「导出列加工（附件/多值/编码）」三块。

| 对象 | 导出（已存在） | 导出字段声明（已存在） | 导入 | 模板下载 |
|---|---|---|---|---|
| 任务 | `module/task/control.php:1197` | `module/task/config.php:29` | 无 | 无 |
| Bug | `module/bug/control.php:1437` | `module/bug/config.php:28` | 无 | 无 |
| 需求 | `module/story/control.php:1410` | `module/story/config.php:31` | 无 | 无 |
| 用例 | `module/testcase/control.php:1059` | `module/testcase/config.php:20` | **有**（`:1312/1449`） | **有**（`:1251`） |
| 用例库用例 | — | 同上 | **有**（`module/testsuite/control.php:738/805`） | **有**（`:684`） |

**验收标准**
1. 四对象各能下载模板 CSV，首行中文列名，枚举列在末行给出可选值清单（沿用 `module/testcase/control.php:1268-1271` 的 `typeValue/stageValue` 约定）。
2. 导入支持 GBK 与 UTF-8（含 BOM）；UTF-8 且列名匹配成功时不转码（沿用 `:1340` 判定）。
3. 预览页逐行给出：行号、解析后的值、错误原因；错误行不写库，正确行写入并汇总"成功 N / 失败 M"。
4. `insert` 模式新建（`version=1`，`openedBy/openedDate` 后端补）；`update` 模式必须带 `id` 列且校验归属，空值不覆盖。
5. 导出文件 Excel 打开中文不乱码（UTF-8 需 BOM）；用例步骤在一个单元格内多行保留，编号两级 `1. / 1.1`。
6. 附件列输出"标题 + 可复制 URL"（纯文本，多附件换行），不是 HTML。

**明确不做**：xlsx/xls（无新依赖）、导入时上传附件、多 sheet、`transfer` 模块整套移植。

### 7.2 数据模型变更

**不新增任何表/列。** 全部读写现有结构：`zt_action`（导入审计，`action=batchimported`）、`zt_usertpl`（导出模板，`type=export<module>`，`module/file/model.php:339/357`）、四对象主表 + `zt_casestep`。

`module/upgrade/model.php` **无需**新增 `case` 分支。

### 7.3 后端改动清单

**1) `module/file/model.php` 新增 2 个通用方法**（唯一新增抽象，因 4 处重复 ≥3）
- `parseImportFile($fileName, $fields, $encode)`：调 `parseCSV()`（`:511-605`）→ 剥 BOM → 列名 normalize（`trim` + 去全角空格 + 去 `*`）→ 三级匹配（精确等于 lang 名 / 忽略空格大小写包含 / `xxx(#id)` 取 `#id`）→ 返回 `array(columns, rows)`。编码判定复用 `module/testcase/control.php:1340-1361` 策略。
- `importTemplateHeader($fields)`：供四对象模板下载共用。
- **不重写 `parseCSV`**（其 `&comma;`、多行引号等土办法必须原样保留，改了会波及用例库现有导入）。

**2) `module/file/control.php:184-192`（`export2CSV`）1 行增强**
`if($this->post->encode == 'utf-8') $output = "\xEF\xBB\xBF" . $output;`
**这是公共行为变更**（影响 product/project/todo 等所有导出），需确认后执行；回退就是删这一行。

**3) 每个对象模块（task/bug/story/testcase）各新增 3 个 control + 1~2 个 model 方法**（蓝本 = testcase 现成实现）

| 新方法 | 蓝本 | 改写点 |
|---|---|---|
| `control::exportTemplet(...)` | `testcase/control.php:1251-1303`、`testsuite/control.php:684-729` | 换字段清单来源 + 枚举值行 + fileName |
| `control::import(...)` | `testcase/control.php:1312-1369` | `getUpload/parseCSV/字符集回退` → `file->parseImportFile()`，其余（存 session `importFile`、跳 `showImport`）不动 |
| `control::showImport(...)` | `testcase/control.php:1449-1622`、`testsuite/control.php:805-968` | 保留骨架（解析→翻译枚举→组装数据→suhosin 提示 `module/common/model.php:1137`），列映射换 `$config-><m>->importFields` |
| `model::getImportFields($extra)` | `testcase/model.php:1169-1189` | 换配置键名，4 处各约 20 行 |
| `model::createFromImport($extra)` | `testcase/model.php:983-1168` | insert 分支照 `:809-838`；update 分支照 `:729-808`（版本比较 + 步骤 diff + `action->logHistory`）；去掉用例专属 step 逻辑即得 task/bug/story 版 |

**字段映射各对象要新写的量**（按 1 字段 ≈ 3–5 行）
- **任务**（~120 行）：`module/task/config.php:29` 共 30 列。特殊处理：`project`/`module`/`story`（`(#id)` 解析 + 存在性校验）、`type/pri/status`（枚举反查）、`progress`/`files`（只读丢弃）、`desc`（保留换行）。
- **Bug**（~150 行）：`module/bug/config.php:28` 共 37 列。特殊处理：6 个 ID 列（`product/branch/module/project/story/task`）、`openedBuild`（多值 `\n` 分隔，参照 `module/bug/control.php:1570-1581` 导出反向解析）、8 个枚举（`severity/pri/type/os/browser/status/resolution/confirmed`）、`steps`（textarea）、`files/duplicateBug/linkBug/case`（只读）。
- **需求**（~110 行）：`module/story/config.php:31` 共 36 列。特殊处理：ID 列（`product/branch/module/plan/duplicateStory`）、**`spec/verify` 必须写 `zt_storyspec` 并置 `version=1`**（参照 `module/story/model.php:172-180`）、枚举、只读列；写库后必须调 `story->setStage()`（`:1098`）。
- **用例**（0 行新写）：`module/testcase/model.php:983-1168` + `getImportFields` 直接复用，只需接驳到 `file->parseImportFile`。

**4) 权限点**（`module/group/lang/resource.php`）：任务段（`:420` 附近）、Bug 段（`:502` 附近）、需求段（`:201` 附近）各加 `import/showImport/exportTemplet`；用例段（`:540` 起）已有。**注意 flow 分支**：`:1463` 与 `:1489` 有 `unset($lang->resource->...)`（onlyTest/onlyTask），新增权限点需同步检查。

**导出字段清单怎么声明（推荐做法）**：**不照搬** 22.6 的 `$config-><module>->templateFields`（该键在 22.6 中只被 `demand` 使用，且强耦合 `transfer` 模块）。**推荐**沿用本仓已成型、四对象一致的"模块内 config 声明"写法，紧贴现有 `exportFields` 之后追加三行：
```php
$config-><module>->importFields           = 'title,project,module,story,type,pri,estimate,estStarted,deadline,desc';
$config-><module>->import->listFields     = array('type','pri','status');
$config-><module>->import->requiredFields = 'title,project';
```
位置：`module/task/config.php`（`:39` 后）、`module/bug/config.php`（`:52` 后）、`module/story/config.php`（`:37` 后）、`module/testcase/config.php`（补 `importFields`）。理由：与现有 `$config->testcase->export->listFields`（`:17-18`）同构，语言标签直接用 `$lang-><module>-><field>`，不引入第二套字段命名体系。

### 7.4 前端改动

1. **`module/<m>/view/import.html.php`（新建 4 份）**：照 `module/testsuite/view/import.html.php`（26 行），10.1 `modal` 表单（文件域 + 编码下拉 + 说明）。
2. **`module/<m>/view/showimport.html.php`（新建 4 份）**：照 `module/testsuite/view/showimport.html.php`（133 行）。10.1 结构：`<form class="main-table table-xxx" data-ride="table">` + `table has-sort-head`；行内错误用 `label label-danger`；底部 `.table-footer` 放保存按钮；顶部 `.btn-toolbar` 放返回与编码提示。
3. **`module/<m>/view/exporttemplet.html.php`（新建 4 份）**：照 `module/testsuite/view/exporttemplet.html.php`（51 行），只需行数输入 + 提交。
4. **导出对话框零改动**：`module/file/view/export.html.php:150-217` 已是通用 10.1 表单；四对象 `export()` 只需 `$this->view->allExportFields` + `customExport = true`（现有写法 `module/testcase/control.php:1239-1240`）。
5. **列表页工具条**：在任务/Bug/需求列表的 `#mainMenu .btn-toolbar.pull-right` 追加"导入 / 导出模板"按钮（样式沿用 `module/testsuite/view/library.html.php:47-55`）。
6. 多值列在预览页用 `<textarea>` 渲染，保留换行。

### 7.5 可复用现有代码

| 文件:行号 | 复用内容 |
|---|---|
| `module/file/control.php:184-192` / `:263-288` | `export2CSV` 三段式 / `sendDownHeader` |
| `module/file/control.php:433-443 / 452-461 / 470-474` | 导出模板下拉/保存/删除 |
| `module/file/model.php:511-605` | `parseCSV`（含 `&comma;`、多行单元格） |
| `module/file/model.php:339-348 / 357-368` | `getExportTemplate` / `saveExportTemplate` |
| `module/file/view/export2csv.html.php:9-19` | CSV 输出格式（全字段加引号、`strip_tags`） |
| `module/file/view/export.html.php:150-217` | 通用导出表单 |
| `module/common/model.php:1228-1262` | `saveQueryCondition`（导出取数依据） |
| `module/common/model.php:1137-1145` | `judgeSuhosinSetting` |
| `module/common/model.php:1273-1299` | `removeDuplicate`（导入去重） |
| `framework/helper.class.php:89-101` | `convertEncoding` |
| `module/testcase/model.php:983-1168` / `:1169-1189` | **导入写库主干** / `getImportFields` 写法 |
| `module/testcase/control.php:1251-1303 / 1312-1369 / 1449-1622` | 模板/导入/预览三件套 |
| `module/testcase/control.php:1150-1230` | 导出加工样板（步骤编号 `:1160-1193`、阶段翻译 `:1213-1215`、linkCase `:1220-1230`） |
| `module/testsuite/model.php:678-843` / `control.php:684-968` | 另一套（库上下文）导入实现 |
| `module/task/control.php:1363-1370`、`module/bug/control.php:1582-1588`、`module/story/control.php:1570-1577` | 附件列 HTML 生成（**CSV 需加纯文本分支**） |

### 7.6 分步实施与检查点

| 步 | 内容 | 检查点 |
|---|---|---|
| 0 | 冻结四对象导入字段口径与列名（写进 config 三行） | `php -l` 四个 config；字段表贴进 PR |
| 1 | `file` 两个通用方法 + BOM | GBK/UTF-8/带 BOM 三种文件各解析 5 行，列映射命中率 100% |
| 2 | **用例试点**：`testcase/control.php:1312/1449` 改走 `file->parseImportFile` | ✅ 已完成（`c15539a`）：无 BOM 回归逐字段一致；**带 BOM 与 GBK 均通过**（修复前带 BOM 报编码错误）；`testsuite` 四处同步；旧 `parseCSV` 行为不变 |
| 3 | 任务导入三件套 + 权限 + 视图 | 真实项目导入 30 行；`zt_task` 字段核对；`checkdb` 通过 |
| 4 | Bug 导入（含 `openedBuild` 多值、`steps` 多行） | 导入后 `bug|view` 渲染正常；多值往返一致 |
| 5 | 需求导入（含 `zt_storyspec` + `setStage`） | `story|view` 显示 spec/verify；`zt_storystage` 有行；项目需求页 stats 正确 |
| 6 | 导出增强：附件纯文本列（3 处加 `fileType=='csv'` 分支）+ 模板下载 4 份 | Excel 打开无乱码；附件单元格可复制 URL |
| 7 | 语言包与文档 | 权限在"权限维护"页可见并可勾选 |

### 7.7 风险与回退

| 风险 | 等级 | 处理 / 回退 |
|---|---|---|
| `parseCSV` 是手写解析器，容错古怪 | 中 | 不重写、不换签名；新方法只做包装 |
| `export2CSV` 加 BOM 影响全站导出 | 中 | **已决策：全局加**（§13 决策 2）；回退 = 删 1 行 |
| 附件列 CSV 丢 URL（现状 `strip_tags` 只剩标题） | 中 | 在 `files` 生成处按 `fileType` 分支 |
| MyISAM 无事务，导入中途失败 | 中 | 先全量校验（解析阶段收集错误）再写库；失败行跳过并汇总 |
| 大文件（`parseCSV` 全量读入） | 中 | 限制 2MB / 1000 行 |
| 需求导入绕过评审触发状态 | 高 | 必须复用 `story->create` 的状态规则（`module/story/model.php:157-165`），必要时用 `needNotReview`；PR 列明状态取值 |
| 更新模式越权改他人数据 | 高 | update 前按 `id` 反查并校验 `product/branch/project/lib` 归属 |
| 回退 | — | 全部为新增方法/视图/配置行，逐个删除即可，不触碰既有导出与表结构 |

### 7.8 工作量分解

| 项 | 人时 |
|---|---|
| 字段口径 + 4 份 config 声明 | 6 |
| `file` 通用解析（含编码/模糊匹配） | 8 |
| 用例试点改造 | 6 |
| 任务导入（control 3 + model 2 + 视图 3） | 10 |
| Bug 导入 | 10 |
| 需求导入 | 12 |
| 4 对象视图（12 文件） | 12 |
| 导出增强（BOM + 附件纯文本 3 处） | 4 |
| 权限 + 语言包 | 3 |
| 自测（GBK/UTF-8/边界/往返） | 8 |
| **合计** | **79** |

---

## 8. 功能 6-2：用例库（caselib）增量补齐 —— 39 人时

### 8.1 目标与验收标准

**核实结论（与假设有出入）**：7 个"缺口"里**有 4 个其实已经能用**，真正缺口是 1 个新功能 + 2 个入口 + 1 个既有 Bug。

| 缺口 | 现状（文件:行号） | 真实缺口 |
|---|---|---|
| 1. `edit($libID)` | **已有**：`module/testsuite/control.php:158`（含 `:175-186` 的 library 分支）；视图 `view/edit.html.php:32` 已按 `type != 'library'` 隐藏公开/私有；权限点已在 `group/lang/resource.php:673` | 仅缺列表页入口（`view/libview.html.php:45` 已有编辑图标） |
| 2. `delete($libID)` | **已有但带 Bug**：`control.php:217-246`，**第 225 行** `if($suite->type == 'private' ...)` 引用**未定义变量** `$suite`（PHP 8 下 `Attempt to read property on null`） | 修 3 行 + 列表页入口 |
| 3. `exportCase()` | **完全没有** | **新功能（主体）** |
| 4. `editCase`/`viewCase` | **功能已可用**：列表页直连 `testcase|edit`（`view/library.html.php:130`）与 `testcase|view`（`:127`）；`module/testcase/control.php:537 edit` 有 `$isLibCase` 分支（`:551-565`） | 仅缺 url 一致的转发方法（各 3–5 行） |
| 5. `batchEditCase()` | **已可用**：`view/library.html.php:151` 直接 `testcase|batchEdit&type=lib`，`module/testcase/control.php:638 batchEdit` 有 `$type=='lib'` 分支（`:660-680`） | 0 |
| 6. `view($libID)` | **已有** `libView`：`control.php:613-631` + `view/libview.html.php`（51 行） | 仅缺 `users`（22.6 `caselib/control.php:342-357` 多传一个） |
| 7. `zt_case.reviewers` | **确认：22.6 也没有**。22.6 `zt_case` 只有 `reviewedBy/reviewedDate`（`22.6/db/zentao.sql:487-488`），与本仓 `db/zentao.sql:159/160` 一致；22.6 的 `reviewers` 出现在 `zt_testsuite` 的**库级评审**（`22.6/module/caselib/model.php:160/183/281` 的 `FIND_IN_SET`），属资产库（assetlib，本仓无该模块）流程 | 不做 |

**验收标准**：用例库列表页出现"编辑库/删除库/导出用例"三个入口且权限受控；导出用例能按当前浏览条件或勾选项导出，CSV 在 Excel 打开正常，步骤/阶段/附件/linkCase 加工正确；删除库时不出现 PHP 警告；`testsuite` 既有导入导出与 `testcase` 编辑链路零回归。

### 8.2 数据模型变更

**必须：0 项。**
建议项（可延后）：若采纳"库级评审"，需 `zt_testsuite` 增 `reviewers text`、`reviewedBy varchar(255)`（22.6 的 `zt_testsuite` 也没有这两列，`22.6/db/zentao.sql:2226-2241` —— 说明是 `extension` 下资产库扩展加的）。做法照 §1.4 的 `addCollectorColumns()` 范式。

### 8.3 后端改动清单（逐缺口）

**缺口 1 — 编辑库入口（0 行后端）**
`module/testsuite/view/library.html.php:47-55` 的 `btn-toolbar pull-right` 增加 `common::printLink('testsuite','edit',"libID=$libID", ..., "class='btn btn-link'")`，用 `common::hasPriv('testsuite','edit')` 包裹，文案 `$lang->testsuite->editLib`（已有）。

**缺口 2 — 删除库（修 Bug + 入口）**
- `module/testsuite/control.php:225`：先 `$suite = $this->testsuite->getById($suiteID);` 再判空跳过（3 行）。
- 建议把 `:221` 的确认文案按类型取 `libraryDelete`/`confirmDelete`（两者都已在 `module/testsuite/lang/zh-cn.php:44-45`）。
- 入口：`library.html.php` 工具栏（`hiddenwin` 提交，参考 `view/libview.html.php:45-46` 的 `printIcon`）。
- 行为确认：库删除**不**级联删用例（`module/testsuite/model.php:421-434` 只在非 library 分支删 `zt_suitecase`），与 22.6 一致；删除确认文案应标注"库内用例将不可见"。

**缺口 3 — `exportCase($libID, $orderBy, $browseType)`（新功能主体）**
- `module/testsuite/model.php` 新增 `getCasesToExport($exportType, $orderBy, $limit)`：**照抄 22.6 `module/caselib/model.php:388-412`** 的两种取数（`session->testcaseQueryCondition` + `session->testcaseOnlyCondition`，选中集用 `$this->cookie->checkedItem`）。本仓 `library()` 已在 `module/testsuite/control.php:422` 调 `saveQueryCondition($this->dao->get(),'testcase',true)`，session 一定就绪。
- `module/testsuite/control.php` 新增 `exportCase($libID, $orderBy='id_desc', $browseType='all')`：结构照抄 22.6 `caselib/control.php:544-567`，但**去掉 caselibZen 转发**，加工方法写进 `testsuite/model.php`。
- 视图：`module/testsuite/view/exportcase.html.php` 直接复用 `module/file/view/export.html.php` 的用法（照 `module/testcase/control.php:1239-1240` 只需 `allExportFields` + `customExport=true`）。
- 字段清单：新增 `$config->testsuite->exportFields`（照抄 22.6 `caselib/config.php`，删掉 22.6 专有列）与 `$config->testsuite->exportTemplateFields`。

**22.6 导出加工（`module/caselib/zen.php:533-701`）逐条判定**

| 22.6 加工 | 位置 | 结论 |
|---|---|---|
| 字段标题映射 | `:533-549` | **保留**（用 `$config->testsuite->exportFields` + `$lang->testcase-><field>`，等价 `module/testcase/control.php:1069-1078`） |
| 步骤合并三级编号 | `:624-657` | **简化保留**：本仓 `zt_casestep` 只有 `type=step/group/item` + `parent`，用 `module/testcase/control.php:1160-1193` 的两级编号即可；必须保留"同 version 才输出"（`:1177`）、CSV 下 `"`→`""`（`:1189-1193`）、末尾 `trim` |
| 阶段翻译 | `:660-668` | **必须保留**，等价写法 `module/testcase/control.php:1213-1215` |
| 附件列 | `:679-694` | **改写保留**：22.6 输出 `html::a(...)`，CSV 走 `strip_tags` 会丢 URL → 改纯文本 `标题: URL` 并 `\n` 连接 |
| linkCase 翻译 | `:695-701` | **不可照抄**：22.6 该方法用了未定义的 `$relatedCases`（潜在 bug）→ 用 `module/testcase/control.php:1220-1230`（`:1149` 已查好） |
| `appendData`/状态/用户翻译 | `:558-580` | **保留**（`testcase->appendData` 本仓同源 `module/testcase/model.php:1403`） |
| `getRelatedSteps/getRelatedFiles/getAllModulePairs` | 依赖 | **不能直接复用**：本仓 `testcase/model.php` 无前两个方法，`tree/model.php` 无 `getAllModulePairs` → 在 `testsuite/model.php` 各写 3–8 行 DAO |

**缺口 4 — `editCase`/`viewCase` 转发（各 3–5 行）**
```php
testsuite::editCase($caseID) → echo $this->fetch('testcase','edit', "caseID=$caseID");
testsuite::viewCase($caseID,$version=0,$from='testcase',$taskID=0) → echo $this->fetch('testcase','view', "caseID=$caseID&version=$version&from=$from&taskID=$taskID");
```
（22.6 写法见 `caselib/control.php:106-109`、`:368-371`。注意本仓 `testcase/control.php:475 view` 无 `$stepsType` 参数，别多传。）

**缺口 5/6**：`batchEditCase` 无需改动；`libView` 在 `module/testsuite/control.php:628-630` 之间补 `$this->view->users = $this->loadModel('user')->getPairs('noclosed|noletter');`（可选）。

**缺口 7**：不做（见 8.1）。

### 8.4 前端改动

1. `module/testsuite/view/library.html.php:47-55`：工具栏左侧拆两个 `btn-group` —— "导出用例"（`testsuite|exportCase`，`class='btn btn-link export'`）、"导出模板"/"导入"保持；右侧加"编辑库""删除库"。
2. `module/testsuite/view/exportcase.html.php`（新建）：复用 `module/file/view/export.html.php` 的分支用法。
3. `module/testsuite/view/edit.html.php`：库场景标题用 `$lang->testsuite->editLib`（现仅用 `edit`，`:25`）。
4. `module/testsuite/view/libview.html.php`：`#mainActions`（`:38-48`）补"导出用例"按钮。
5. 批量编辑沿用 `library.html.php:151-160` 既有 datatable 批量栏，无需改。

### 8.5 22.6 参照索引

| 位置 | 借鉴 |
|---|---|
| `22.6/module/caselib/model.php:388-412` | 导出的两种取数（session 条件 + 选中集） |
| `22.6/module/caselib/control.php:544-567` | `exportCase` 入口结构 |
| `22.6/module/caselib/control.php:106-109 / 368-371` | `editCase`/`viewCase` 转发写法 |
| `22.6/module/caselib/control.php:342-357` | `view($libID)` 多传 `users` |
| `22.6/module/caselib/zen.php:533-701` | 导出加工（逐条判定见 8.3） |

### 8.6 分步实施与检查点

| 步 | 内容 | 检查点 |
|---|---|---|
| 1 | 修 `control.php:225` 未定义变量 + 类型化确认文案 | 库列表点删除：无 PHP 警告，`zt_testsuite.deleted=1`，库内用例仍在但列表不可达 |
| 2 | 列表/概况页补 3 个入口 | 权限勾选/取消后按钮显隐正确 |
| 3 | `editCase`/`viewCase` 转发 + 链接改指 | 库内点用例标题/编辑，标题栏与左侧模块树正确 |
| 4 | `config.php` 加字段清单；`model.php` 加 `getCasesToExport` | 无筛选导出 = `select * from zt_case where lib=X and product=0 and deleted=0` 行数一致 |
| 5 | `exportCase` 加工（步骤/阶段/附件/linkCase/用户） | 造 3 条用例（含 group/item 步骤、多阶段、2 附件、linkCase）导出，Excel 人工核对；步骤单元格换行正确 |
| 6 | 按筛选导出（bymodule/bysearch/selected） | 与列表页当前 `recTotal` 一致 |
| 7 | 回归 | 用例库导入/模板下载/批量编辑/关联导入全部重跑 |

### 8.7 风险与回退

| 风险 | 等级 | 处理 / 回退 |
|---|---|---|
| 必修 Bug 改动 `delete` 语义 | 高 | 只加一行取对象 + 一个判空，不改删除分支 |
| 导出加工复制 `testcase->export` 造成两处维护 | 中 | 明确边界：库导出只做"库上下文"差异，其余逐字段对齐；PR 列出差异清单 |
| `export2CSV` 的 BOM 决策影响本功能 | 中 | 与功能 E 同步决策 |
| `getCasesToExport` 用 `session->testcaseQueryCondition`，与产品用例页共享 session | 中 | 导出页只在 `library()` 之后进入；POST 回传 libID（22.6 亦如此） |
| 删除库留下孤儿用例 | 低 | 与 22.6 行为一致；确认文案注明 |
| 回退 | — | 全部为新增方法/视图 + 3 行 Bug 修复 |

### 8.8 工作量分解

| 项 | 人时 |
|---|---|
| 现状核实 + 回归基线（列表/导入/批量 3 链路） | 2 |
| 修 delete Bug + 类型化确认文案 | 2 |
| 3 个入口接线 | 3 |
| `editCase`/`viewCase` 转发 + 链接改指 | 3 |
| `libView` 补 users | 1 |
| `exportCase`：config + `getCasesToExport` | 5 |
| `exportCase` 加工（含 3 个自写 DAO） | 12 |
| 导出视图 | 3 |
| 权限点 + 语言 | 2 |
| 测试（Excel、步骤往返、筛选一致性） | 6 |
| **合计** | **39** |

---

## 9. 功能 6-3：需求矩阵 + 批量操作 —— 58 人时

### 9.1 目标与验收标准

**三条决定实现规模的硬事实（已核实）**
1. 22.6 的矩阵是**需求层级矩阵**（`story/model.php:4345-4359 getTracksByStories`），依赖 `zt_story` 的 `parent/isParent/root/path/grade`（`22.6/db/zentao.sql:1847-1904`）。**本仓 `zt_story` 无这 5 列**（`db/zentao.sql:535-572`，层级用 `childStories` 字符串）→ **不能**照搬 22.6 的 lanes/cols/items。
2. 22.6 矩阵列含 `design`/`commit`/`doc`，依赖 `zt_design`/`zt_repohistory`。**本仓没有 `zt_design`、也没有 repo 表** → 必须删列。
3. 22.6 项目页的批量操作**全部是转发**到 `story` 模块（`22.6/module/projectstory/control.php:396-471`）。而**本仓 `story` 模块这些方法已全部存在** → G 的批量操作 = **视图接线，零后端开发**。

**验收标准**：项目需求页出现"矩阵"入口；矩阵每行一个需求，列含 需求（编号+标题+优先级+状态+阶段）、所属迭代、任务、Bug、用例，单元格可点进对应对象；空数据显示 `-`；批量评审/关闭/指派/改计划/批量编辑/改阶段/移除关联在项目需求页可用且状态机合法。

### 9.2 数据模型变更

**无需 DDL。** 矩阵所需结构已存在：

| 数据 | 表:列 |
|---|---|
| 需求↔项目关联 | `zt_projectstory`：`project/product/story/version/order`（`db/zentao.sql:509-516`）—— **本仓无 `branch` 列**，22.6 有（`22.6/db/zentao.sql:1674-1686`）。新代码禁止引用 `projectstory.branch` |
| 迭代（子项目） | `zt_project.parent/isCat/type`（`:466-499`），已有 `module/project/model.php:1454 getChildProjects($projectID)` |
| 需求阶段 | `zt_story.stage`（`:551`）+ 多分支覆盖 `zt_storystage(story,branch,stage)`（`:583-588`） |
| 任务/Bug/用例 | `zt_task.story/project`、`zt_bug.story/project`、`zt_case.story`（计数方法：`module/task/model.php:1602`、`module/bug/model.php:1505`、`module/testcase/model.php:493`） |

### 9.3 后端改动清单

**1) `module/project/control.php` 新增 `track($projectID, $productID=0, $browseType='allstory', $param=0, $orderBy='id_desc', $recTotal=0, $recPerPage=100, $pageID=1)`**

去掉 22.6 的 `$this->app->tab`/`hasProduct`/`setTrackMenu`/`productZen->getStories` 抽象，直接：
```php
$project   = $this->project->getById($projectID);
$products  = $this->project->getProducts($projectID);          // 已有
$pager     = pager::init(...);
$stories   = $this->loadModel('story')->getProjectStories($projectID, $sort, $browseType, $param, $pager);  // story/model.php:1648
$this->loadModel('common')->saveQueryCondition($this->dao->get(), 'story', false);   // 同 project/control.php:583
$track     = $this->project->getStoryTrackData($stories);       // 新增
```
复用 `module/project/control.php:555-647`（`story()`）的菜单/搜索/统计骨架。

**2) `module/project/model.php` 新增 `getStoryTrackData($stories)`**（唯一新增数据装配，约 80–120 行）

一次取回，避免 N+1：

| 列 | 查询 |
|---|---|
| 迭代 | `zt_projectstory` 按 `story in (...)` 取 `project`，再 `zt_project` 取 `id,name,parent,status,begin,end`；用 `getChildProjects($projectID)` 区分"本项目"与"子迭代" |
| 阶段 | `zt_storystage`（`story in`）→ 多分支展开；单分支直接用 `$story->stage` |
| 任务 | `select id,story,project,name,status,assignedTo,estimate,consumed,left,deadline from zt_task where story in (...) and project in (...) and deleted=0` |
| Bug | 同形查询 `zt_bug`（`story,project,id,title,status,severity,pri`） |
| 用例 | `select id,story,project,title,status,lastRunResult from zt_case where story in (...) and deleted=0` |
| 用户 | `user->getPairs('noletter')` 一次 |

**丢弃**：`grade`（无该列）、`roadmap`（无该模块）、`design`/`commit`/`doc`（无表）、`lane/leafNodes` 层级（无 `parent/root/path`）。

**3) 批量操作：零后端开发，只在项目需求页接线**（`module/project/view/story.html.php:236-275` 的 `.table-actions` 区）

| 批量操作 | 已有实现 | 状态机约束 |
|---|---|---|
| 批量编辑 | `module/story/control.php:452` / `model.php:536` | 已接线（`:241-245`），无改动 |
| 批量关闭 | `control.php:850` / `model.php:842` | 已接线（`:246-250`）；仅对非 `closed` 生效 |
| 批量评审 | `control.php:790` / `model.php:714` | **需接线**；只对 `status=draft|changed` 有意义；markup 照抄 `module/product/view/browse.html.php:211-240` |
| 批量指派 | `control.php:1021` / `model.php:1039` | **需接线**；照抄 `product/view/browse.html.php:343-360` |
| 批量改计划 | `control.php:954` / `model.php:940` | **需接线**；照抄 `product/view/browse.html.php:314-341`；项目上下文取 `$this->project->getPlans($products)`（`project/control.php:619-624` 已在用） |
| 批量改阶段 | `control.php:998` / `model.php:1009` | 已接线（`:251-266`） |
| 批量改模块 | `control.php:932` / `model.php:911` | 未接线（可选） |
| 批量移除关联 | `module/project/control.php:1992` / `model.php:1552` | 已接线（`:271-275`），内部会取消 wait/doing 任务（`project/model.php:1567-1568`）→ UI 需保留二次确认 |
| 批量导入需求 | 见功能 E | 入口加在项目需求页 |

**不要接线**：22.6 的 `batchSubmitReview`（依赖多产品评审人配置，本仓 `story/model.php` 无此方法）。

**4) 权限点**：`module/group/lang/resource.php:322`（`project->story` 之后）加 `$lang->resource->project->track = 'track';` + `$lang->project->methodOrder[N]`。

### 9.4 前端改动

**矩阵表格（本仓无 rowspan 版需求表格，需新写）**
- 本仓含 `rowspan` 的视图只有 13 个，**没有任何需求表格**；参照物取 `module/report/view/workload.html.php:82-107`（同份数据 rowspan 跨行 + `$id!=1` 才补 `<tr>` 的写法）。
- 10.1 **没有**"合并单元格 datatable"组件（`module/datatable/model.php` 的字段配置不支持 rowspan）→ **结论：矩阵用普通 `.table.has-sort-head` + 手工 rowspan，不要接 `data-ride="table"`/datatable**（会因列设置把合并列拆掉）。
- 文件 `module/project/view/track.html.php`（新建，约 150–200 行）：
  - `#mainMenu > .btn-toolbar.pull-left` 放 featurebar 页签（`customModel::getFeatureMenu('project','track')`，机制见 `module/custom/model.php:339-351`），需先在 `module/project/lang/zh-cn.php` 定义 `featureBar['track']`（现仓库有 `featureBar['browse']` 等，如 `module/bug/lang/zh-cn.php:382`）。
  - 表头两行：第一行分组（需求/迭代/任务/Bug/用例），第二行细分列；第一列需求固定（`position: sticky`，10.1 皮肤有该类）。
  - 单元格：任务/Bug/用例用 `html::a` 指向 `task|view`/`bug|view`/`testcase|view`；超 3 条折叠为 `<span class='label'>+N</span>`；空值 `-`。
  - 分页沿用 `pager`（默认 100，22.6 同）。

**批量入口**：`module/project/view/story.html.php:236-275` 的 `.table-actions` 内补"评审/指派/改计划"三个 `btn-group dropup`，markup 逐块复制 `module/product/view/browse.html.php:211-240 / 314-341 / 343-360`。
**矩阵入口**：`module/project/view/story.html.php:39-43` 的 `pull-left` 页签区加"矩阵"按钮（同 `:41` 的 storykanban 写法）。

### 9.5 可复用现有代码

| 文件:行号 | 复用内容 |
|---|---|
| `module/project/control.php:555-647` | `story()` 全骨架（菜单/搜索/统计/`saveQueryCondition`/分页） |
| `module/project/control.php:619-624` | `getPlans($products)` → 批量改计划下拉 |
| `module/project/control.php:1992-2010` | `batchUnlinkStory`（含 `$_POST` 重置技巧） |
| `module/project/model.php:1454-1457` | `getChildProjects($projectID)` → 迭代列 |
| `module/project/model.php:1489-1514 / 1552-1575` | `linkStory`/`unlinkStory` |
| `module/story/model.php:1648-1727` | `getProjectStories`（矩阵行数据，含 branch stage 覆盖 `:1711-1715`） |
| `module/story/model.php:1909-1921` | `batchGetStoryStage` |
| `module/story/model.php:714/842/940/1009/1039/536` | 批量评审/关闭/改计划/改阶段/指派/编辑 |
| `module/task/model.php:1602`、`module/bug/model.php:1505`、`module/testcase/model.php:493` | 计数方法（矩阵单元格现成口径） |
| `module/report/view/workload.html.php:82-107` | **rowspan 渲染唯一范例** |
| `module/product/view/browse.html.php:200-360` | 批量评审/改计划/指派的 10.1 markup 全量样板 |
| `module/custom/model.php:339-372` | featurebar 机制 |
| `22.6/module/projectstory/control.php:224-247` | `track` 参数签名（仅参考签名） |

### 9.6 分步实施与检查点

| 步 | 内容 | 检查点 |
|---|---|---|
| 1 | 冻结矩阵列定义，确认删除 grade/roadmap/design/commit/doc | 与需求方过列名；确认无 `zt_story.grade`、无 `zt_design` 依赖 |
| 2 | `project/model.php::getStoryTrackData` | 单测：1 个含 3 子迭代的项目、5 条需求，逐单元格与手工 SQL 对数一致 |
| 3 | `project/control.php::track` + 路由/菜单/权限 | 直接访问 URL 出数据；无权限用户被拦截 |
| 4 | `project/view/track.html.php`（rowspan + 折叠 + 空态 + 分页） | 5 需求 × 3 迭代渲染正确；10 迭代时横向滚动可用；空数据显示 `-` |
| 5 | featurebar 页签 + 搜索表单复用 | 页签切换 URL 正常；`mergeFeatureBar` 能并入用户快捷查询（`custom/model.php:364-371`） |
| 6 | 项目需求页补"评审/指派/改计划"入口 | 3 个操作各跑一次：有 `zt_action` 记录且 `logHistory` 写入 changes |
| 7 | 状态机回归（7 个批量操作） | 逐条对照 `story/model.php:653/801/1098` 的允许态；非法操作给出错误提示 |
| 8 | 权限/语言/文档 | 权限页可见 `project->track`；lang 无缺键 |

### 9.7 风险与回退

| 风险 | 等级 | 处理 / 回退 |
|---|---|---|
| 矩阵列数 = 迭代数，可能几十列导致渲染缓慢 | 中 | 迭代列上限（如 10）+ "展开全部"；单元格只渲染 id/标题前 20 字 |
| 每需求多查询造成 N+1 | 中 | `getStoryTrackData` 必须批量 `where story in (...)`；PR 里贴 SQL 条数（目标 ≤ 6 条） |
| 用 datatable 会把 rowspan 合并列打散 | 高 | 明确不使用 datatable；只用 `.table.has-sort-head` + 手工 rowspan |
| `zt_projectstory` 无 `branch` 列 | 高 | 所有新代码禁止引用 `projectstory.branch`；分支维度走 `zt_story.branch`/`zt_storystage.branch` |
| 批量评审在 `needReview=0` 时点了无效 | 中 | 按 `$config->story->needReview`/`forceReview` 决定是否渲染"评审"入口 |
| 需求状态机被批量操作绕过 | 高 | 所有批量按钮都走 `story` 现有 control；**禁止新增直写 `TABLE_STORY` 的 SQL** |
| 移除关联会取消进行中任务 | 中 | 保留二次确认（`project/view/story.html.php:265` 已有 `confirmUnlinkStory`），文案说明影响 |
| 回退 | — | 新增 1 control + 1 model + 1 视图 + 若干按钮/语言行，逐个删除即回退 |

### 9.8 工作量分解

| 项 | 人时 |
|---|---|
| 列口径冻结 + 依赖排查 | 3 |
| `getStoryTrackData`（6 条批量查询 + 装配） | 12 |
| `track()` control | 6 |
| `track.html.php`（两行表头 + rowspan + 折叠 + 空态） | 16 |
| featurebar 页签 + 搜索表单复用 | 6 |
| 批量入口 3 个 markup | 5 |
| 状态机回归与边界 | 8 |
| 权限/语言/入口按钮 | 2 |
| **合计** | **58** |

---

## 10. 数据库变更汇总（**已授权 · 2026-10-16**）

按项目规范"数据库结构"属绝对不改项，以下变更已获主人一次性授权（§13 决策 8）。**全部只做新增，绝不 ALTER 既有列语义。**

| 功能 | 变更 | 类型 | 幂等方法（`module/upgrade/model.php`） |
|---|---|---|---|
| 3-2 pivot | `zt_pivot`、`zt_pivotspec`、`zt_pivotdrill` | 新增 3 表 | `createPivotTables()` |
| 4 errorlog | `zt_errorlog`、`zt_errorlogreq` | 新增 2 表（**InnoDB**，有意偏离） | `addErrorLogTables()` + `addErrorLogCron()` |
| 5 文档 | `zt_doc.order` | 新增列（默认 0） | `addDocOrderColumn()` |
| 5 文档 | `zt_doclib.type` | 新增列（默认 `''`） | `addDoclibTypeColumn()` |
| 5 文档 | `zt_docaction` | 新增 1 表 | `createDocActionTable()` |
| 5 文档 | 模板范围种子库 | 1 行 INSERT（幂等） | `seedTemplateLib()` |
| 1 看板 | `zt_config` 一行 JSON | **无结构变更** | 无需升级方法 |
| 3-1 年度总结 / 6-1 CSV / 6-2 用例库 / 6-3 矩阵 | **无任何结构变更** | — | — |

**合计：新增 6 张表 + 2 个列 + 1 行种子 + 1 行 cron。**

`db/zentao.sql` 必须同步全部新增（全新安装路径）。升级方法统一照 §1.4 范式（`information_schema` 探测 + `try/catch`），**不需要改 `switch($fromVersion)`**。

---

## 11. 建议实施顺序与里程碑

依赖关系（箭头 = 前置）：

```
§1 前置（helper/dao/eCharts）
   ├─→ 功能 6-2 用例库（39h，最便宜，含 1 个必修 Bug）
   ├─→ 功能 1 看板（52h，零表结构变更，见效最快）
   ├─→ 功能 4 错误日志（21h，独立）
   └─→ 功能 3-1 年度总结（62h，纯新增，eCharts 前置）
功能 6-1 CSV 的 file 通用层 ──→ 用例试点 ──→ 任务/Bug/需求导入
功能 3-2 pivot（79h，需 §1 的 helper 与内联方法）
功能 6-3 矩阵：批量接线（1 人日即见效）→ 矩阵视图
功能 5 文档（31h，独立，但含 2 个既有 Bug 修复）
```

**推荐里程碑**

| 里程碑 | 内容 | 人时 | 交付价值 |
|---|---|---|---|
| **M0** | §1 前置（eCharts/helper/spike）+ 两个必修缺陷（`testsuite/control.php:225` 未定义变量、`doc/model.php:515` 文档可见性 `module IN ('0')`） | 29 | 用实测替换估算、消除既有缺陷、后续功能解封 |
| **M1** | 功能 1 看板 + 功能 6-2 用例库 | 91 | 每天都能用上的可视化 + 测试团队刚需 |
| **M2** | 功能 4 错误日志 + 功能 3-1 年度总结 | 83 | 降维护成本 + 对外传播 |
| **M3** | 功能 6-1 CSV（含 `file` 通用层）+ 功能 6-3 矩阵 | 137 | 日常效率（批量导入 + 交叉跟踪） |
| **M4** | 功能 3-2 pivot + 功能 5 文档 | 110 | 统计能力升级 + 知识沉淀 |
| | **合计** | **450 + §14.2 窗口期保护 / §14.3 回填 / §14.6 三语 / §14.10 性能（≈7）** | 实际承诺前先按 §18 用 spike 结果复核 |

> 若只想先做一个：**功能 1 看板（52h）** —— 唯一"零表结构变更 + 零新依赖 + 复用已有 10.1 页面 + 参与感最强"的组合。
> 若只想先做一批：**M1（91h）** —— 只新增 0 张表（看板零变更、用例库零变更），风险最低。
> **M0 必须先做**：§18 的 4 个 spike 决定 M1–M4 的真实工时，不做 spike 就承诺工时是赌博。

---

## 12. 风险登记册（跨功能）

| 风险 | 等级 | 影响面 | 缓解 |
|---|---|---|---|
| 数据库结构变更（AGENTS 红线） | 🔴 高 | 功能 3-2/4/5 | 只新增；幂等升级；每项独立提交独立验证；一次性授权（§10） |
| 错误处理内再抛错 → 整站白屏 | 🔴 高 | 功能 4 | 重入锁 + `try/catch(Throwable)` 全吞 + `dbh` 判空 + 库不可用专项自测 |
| 照抄 22.6 SQL 的列名（`vision/shadow/isTpl/multiple/execution/effort`） | 🔴 高 | 功能 3-1/3-2 | 把 §3.2 表格做成 checklist，每写一个 SQL 勾一行 |
| 绕过状态机直写 status | 🔴 高 | 功能 1/6-1/6-3 | 禁止 `UPDATE zt_xxx SET status`；全部走既有 model 方法；列为 Review 必查项 |
| rowspan 表格与 datatable/`data-ride='table'` 冲突 | 🟡 中 | 功能 3-2/6-3 | 明确禁用这两者，用服务端拼 HTML + 普通 `.table` |
| 前端重写耗时超估算 | 🟡 中 | 全部 | 22.6 交互复杂度（拖拽/级联/dtable 配置）在 ZUI 1.8.1 上要手写；估算已按 1:0.6 留量，仍可能 +30% |
| 导入绕过评审/权限 | 🟡 中 | 功能 6-1 | 复用 `story->create` 状态规则；update 前校验归属 |
| `export2CSV` 加 BOM 影响全站导出 | 🟡 中 | 功能 6-1/6-2 | **已决策：全局加**（§13 决策 2）；回退 = 删 1 行 |
| 顺序/配置并发覆盖 | 🟢 低 | 功能 1 | 兴趣项目单项目协作人数少，可接受；逃生出口 = 加 `zt_task.order` 列 |

---

## 13. 决策事项（**2026-10-16 主人已全部批准**，按此执行）

| # | 决策项 | **已批准取值** | 落地影响 |
|---|---|---|---|
| 1 | 是否引入 eCharts | **引入** | 新增 `www/js/echarts/echarts.common.min.js`（Apache-2.0，约 1MB，1 个文件）；年度总结/后续图表功能可用；发布说明单列该依赖 |
| 2 | `export2CSV` 是否全局加 UTF-8 BOM | **全局加** | `module/file/control.php:184` 加 1 行；影响 product/project/todo 等所有 CSV 导出（Excel 打开不再乱码）；回退 = 删该行 |
| 3 | 看板顺序持久化方案 | **(b) `zt_config` JSON** | 零表结构变更；不加 `zt_task.order` 列 |
| 4 | pivot 内置 5 张表是否切换 `reportList` 入口 | **切换** | `$lang->reportList` 指向 `pivot|show`；旧 `report` 方法保留不删，可一键改回 |
| 5 | 需求 CSV 导入的默认状态 | **按 `$config->story->needReview` 现状** | 配置开启评审时导入落 `draft`；关闭时用 `needNotReview` 直接 `active`。实施时先读该配置值再写死分支 |
| 6 | 矩阵默认列（"阶段"是否按分支拆列） | **单列** | 用 `zt_story.stage`；多分支产品（本仓仅 2 个 platform 产品）不拆列 |
| 7 | 文档复制是否继承附件 | **一期不继承** | 复制弹窗内明示"附件不随复制"；二期再接 `file` 模块 |
| 8 | 是否授权 §10 的数据库变更 | **授权全部** | 新增 6 表 + 2 列 + 1 行种子 + 1 行 cron 全部放行；仍须遵守"只新增、不改既有列语义"与 §1.4 的升级约定 |

> §13 原为"待决策清单"，现已全部拍板，故改为"已批准事项"。实施过程中若出现与上表冲突的技术事实，**先停下报告**，不自行改判。

---

## 14. 自审补充：12 个缺口与推荐方案（2026-10-16 grill 自审）

用 grill-me 方式对 §0–§13 做了一轮逐项盘问，**能查代码的一律查代码**。以下 12 项原计划遗漏或写错，按推荐方案执行。

### 14.1 🔴 **升级机制写错了（已修正 §1.4）**

原计划写"顶部无条件调用、不需要改 switch"，**但漏掉了两个真陷阱**：
- **跨版本跳跃**：`isWenyinVersion()`（`module/upgrade/model.php:85`）同时接受 `9.8.3_1` 与自研序列，而 `$fromVersion` 是**来源版本**（`control.php:56`）⇒ 停留在 9.8.3_1 的站点升级时，`case '9_9_0':` **不会执行**，只挂在单一 case 分支的建表会永久缺失。
- **版本号越界**（**已在 M0 修复**）：原正则 `^9\.9\.\d+$` 会让 `9.10.0` 在下次升级时被拒；现已放开为 `^9\.(?:9|[1-9]\d+)\.\d+$`（支持 `9.10.0` 及以上，仍拒绝官方 `9.8.3`/`10.1`/`22.6`）。详见 §1.4 约束 2。

**推荐（已写入 §1.4）**：幂等"按需探测"方法（先例 `addCollectorColumns()`）+ 显式 case 分支双保险；版本号可用 `9.9.x` 或跳段到 `9.10.0+`（跳段需单独验证一次升级链路）。

### 14.2 🔴 **窗口期保护缺失**

全仓**没有任何 `defined('TABLE_...')` 守卫**（`grep` 命中 0）。文件先覆盖、升级后执行的窗口期内，`pivot|preview`（表不存在）与文档排序（`order` 列不存在）会直接 SQL 报错。

**推荐**：
- `module/pivot/control.php::preview()/show()` 开头做一次表存在探测（`SHOW TABLES LIKE 'zt_pivot'`），不存在则 `die(js::error('请先执行升级（bin/ztcli upgrade-execute）'))`，不抛 SQL 错误；
- `module/doc/model.php` 的 `order_asc` 排序前探测 `order` 列存在性，缺列时回退 `id_desc`；
- `errorlog` 已有 `defined()` 守卫（§5.3 改动 3），保持。

### 14.3 🟡 **`zt_doc.order` 既有行必须回填**

新列默认 0 ⇒ 存量文档全部 `order = 0`，排序不稳定（MySQL 无第二排序键时顺序随机）。

**推荐**：升级方法里补一句幂等回填（只对 0 值生效，可重复执行）：
```sql
UPDATE `zt_doc` SET `order` = `id` WHERE `order` = 0;
```
同一方法内先加列、再回填，`information_schema` 探测保证只跑一次。

### 14.4 🟡 **新增权限点后，普通成员组看不到入口（运维步骤，不是代码问题）**

`commonModel::hasPriv()`（`module/common/model.php:1395`）第 1400 行对 **super admin 短路**（`if(!empty($app->user->admin)) return true;`），所以管理员能看到新入口；但**非 super admin 的成员组不会自动获得新权限点**，必须在"后台 → 权限 → 权限维护"里手工勾选。

**推荐**：每个含新权限点的功能，DoD 里加一条"权限页可见并可勾选 + 用非 admin 账号验证显隐"（见 §16）。这条要写进上线说明，否则社区成员会反馈"功能找不到"。

### 14.5 🟡 **`db/zentao.sql` 的 `;` 切分约束（已写入 §1.4）**

`module/install/model.php:441` 用 `explode(';', ...)` 切分执行 ⇒ 新 DDL **不能含字面量分号**（含 `COMMENT` 与字符串值），不能有 `DELIMITER`/触发器/存储过程。本批 6 段 DDL + 1 行种子 + 1 行 cron 已按此约束设计，实施时仍需 `php -l` + 真装一遍验证。

### 14.6 🟡 **语言文件必须三语同步**

`report/doc/testsuite/project/task/bug/story/testcase` 的 `zh-cn.php`/`en.php`/`zh-tw.php` **三者都存在**（已核实）。原计划只在年度总结一节提了语言包。

**推荐**：DoD 统一要求"新增语言键必须同时补 `en.php` + `zh-tw.php`"；pivot/errorlog/doc 的新增模块同样建三语文件。用 `array_keys` 对比 zh-cn 补全（§3.6 检查点已有该手法，推广到全部功能）。

### 14.7 🟡 **MCP 契约回归（doc 模块）**

`module/common/ext/model/mcp.php` 经 `module/doc/model.php:619/654` 依赖 `create()` 的返回形状 `array('status'=>'exists'|'new','id'=>...)`。**加 `order` 列不改返回形状 ⇒ 契约安全**，但必须回归：
- `zentao_create_doc` / `zentao_update_doc` / `zentao_create_doclib` 三个工具各跑一次；
- `zt_doclib` 加 `type` 后，MCP 建库仍能落库（`createLib()` 返回 `lastInsertID`，不受影响）。

**推荐**：把这三个 MCP 工具列入功能 5 的回归清单（§17）。

### 14.8 🟡 **导出/导入必须受数据权限约束**

`hasDBPriv()`（`module/common/model.php:1437`）由 `hasPriv()`（`:1409`）调用，是"限定项目/数据范围"的执行点。

**推荐（安全要求）**：
- **导出**：必须复用列表页的 `saveQueryCondition` 结果（原计划已如此），不得放宽条件；导出文件名与内容不额外暴露用户无权看到的对象。
- **导入**：写库前对目标对象调用 `common::hasDBPriv($object, $module, $method)`；**update 模式**必须先按 `id` 反查并校验归属；**禁止**导入时把 `assignedTo`/`openedBy` 设为他人的越权赋值（除非当前用户有 `admin`）。
- **pivot 过滤器**：所有来自请求的 filter 值一律 `$this->dbh->quote()`；只读版**不开放**"自定义 SQL 录入"，避免任意 SQL 执行面。

### 14.9 🟡 **移动端与三主题（原计划完全没提）**

- 移动端：`module/project/control.php` 与 `module/report/control.php` 的 `isMobile`/`viewType == 'mhtml'` 命中数均为 **0** ⇒ 这些页面本来就没有移动端分支。**推荐：本期不新增移动端支持，但新增视图必须满足"窄屏不横向溢出"**（用 440px 视口各页过一遍，方法参照此前移动端重写的验证手法）。
- 三主题：新增 CSS（看板 `kanban.css`、pivot 表格、年度总结、矩阵）必须在 `wenyin`/`lightblue`/`blackberry` 三主题下可用。**推荐：只用主题无关的类名与变量，不写死颜色**；三主题各截一张图验收（AGENTS.md 明确"三层一起动"）。

### 14.10 🟡 **性能预算（原计划未定义）**

本机 dev 数据量小，掩盖了真实成本。线上参考量级：`zt_action` 已达数百条且会持续增长（年度总结按年聚合它）。

**推荐预算（超过即需优化）**：
| 页面 | 预算 |
|---|---|
| `report|annualData` | ≤ 3s（首屏） |
| `project|track`（矩阵，≤ 10 迭代 × 100 需求） | ≤ 3s，SQL 条数 ≤ 6 |
| `pivot|show`（内置 5 张） | ≤ 2s |
| 看板（单项目 ≤ 200 任务） | ≤ 2s |
| `errorlog|browse`（10 万行内） | ≤ 1s（`createdDate`/`md5` 已有索引） |

**实测（2026-10-16，本地 dev）**：`annualData` 106ms / `project|track` 40ms / `pivot|show` 34ms / 看板 49ms / `errorlog|browse` 59ms（取服务端 TTFB，各 6–15ms），全部远低于预算；矩阵的批量取数**实测 4 条 SQL**（需求列表 3 条另计）。

⚠️ **数 SQL 条数时注意**：`dao::$querys` 对每条语句记 **两次**（`processSQL()` 构建时一次、`query()` 执行时一次），数组长度要除以 2 才是真实条数。第一版按数组长度数出 8 条、误以为超了「≤6」的预算，实际是 4 条。

### 14.11 🟡 **未定义"完成"标准与提交策略**

**推荐**：统一 DoD（§16）；提交策略：**每功能 1–2 个提交，直接在 `main`**（与仓库现状一致，`git log` 无分支流），提交信息用 `<module>: <imperative>`，引用禅道任务号（如 `task #70`），便于 `zentao_sync_commits` 自动关联。

### 14.12 🟢 **估算不确定性与 spike（见 §18）**

最不确定的是 pivot 引擎移植（36 方法 / 900–1100 行）与看板同列拖拽（ZUI sortable 在 rowspan/表格容器下的行为）。**推荐先做 §18 的 4 个 spike**，用实测替换估算，再承诺里程碑工时。

---

### 14.13 🟡 **M0 实测又推翻两项计划**（2026-10-16，已回写 §1.2 与 §6）

**(a) `dao` 根本不用补** —— 详见 §1.2：`autoExclude` 默认 false 使第 2 参成为 no-op；`dao->getColumns()` 除 `bi` 外零调用。前置成本 −2 人时。

**(b) `tree/model.php` 的"删目录不回收文档"不是根因，改法是另一处 1 行** —— 详见 §6.2：
- 真正的根因是 `module/doc/model.php:515 getPrivDocs()` 的 `->andWhere('module')->in($module)`：当 `$module = 0` 时 `helper::dbIN(0)` 生成 `IN ('0')`，于是**"全部"视图只列无目录文档**，目录内文档在"全部"视图与删除目录后都不可见（`getStatisticInfo()` 的"所有文档"也少算）。我们的代码与 10.1 **逐字节相同**，不是移植缺陷。
- **22.6 已修**：`->beginIF(!empty($modules))->andWhere('module')->in($modules)->fi()`（`22.6/module/doc/model.php`），即"仅在指定目录时才按 module 过滤"。M0 已按此对齐。
- **A/B 实测证据**（dev 环境，造数据后对比）：旧写法下"目录内文档"在全部视图出现次数 = **0**；修复后 = **1**；`bymodule` 按目录筛选语义不变（目录内=1、无目录=0）；文档首页统计从 3 变 4（与 DB 实数一致，验证后已清理测试数据）。
- **`tree::delete()` 不改**：22.6 的等价方法 `tree::remove()` 里**也没有 `case 'doc':`**（只处理 line/task/bug/case/story/chart/report/dataview/feedback/ticket），说明上游有意不回收；且改动会触及 story/bug/case/task 共用的核心方法，偏离上游。⇒ 从功能 5 移除该子项（§6.1/§6.5/§6.6/§6.8/§6.9 已同步）。

**教训（值得写进后续流程）**：计划里的"必改 Bug"必须先做**根因验证**再动刀——本项若按原计划直接改 `tree/model.php`，会改错地方、还会引入偏离上游的长期维护成本。

### 14.14 🟡 **M1 实测新发现的两个框架级坑（filter.php）**

新增带 cookie 读取的方法时，**除了写代码还必须改 `config/filter.php`**，否则 cookie 会被静默丢弃：

1. **`filterParam = 2` 会丢弃没有匹配规则的 cookie**：`framework/base/router.class.php:1685` 在 `$config->framework->filterParam == 2`（本仓 `config/config.php:100` 即此值）时调用 `validater::filterParam($_COOKIE, 'cookie')`，而 `filterParam`（`lib/base/filter/filter.class.php:688`）对**没有规则**的 cookie 执行 `checkByRule($value, '') === false` → `unset($var[$key])`。⇒ 新增方法若依赖 cookie（如 `checkedItem`、`preProductID`），必须在 `config/filter.php` 补一条规则。M1 实测：未补规则时"导出所选"静默导出全部记录（无任何报错），补后才生效。
2. **规则键必须用全小写方法名**：`$app->getMethodName()` 返回的是 **`exportcase`**（全小写），而规则按 `$filter-><module>-><method>->cookie[...]` 查找。写成驼峰 `exportCase` 不会匹配（`config/filter.php` 里既有规则全是小写方法名，正是这个原因）。M1 实测用调试打印确认 `methodName: "exportcase"` 而 `$filter->testsuite` 的键是 `exportCase` → 不命中。
3. **父级必须先显式初始化为对象**：`$filter->testsuite->exportcase->cookie['x'] = '...'` 之前必须 `$filter->testsuite->exportcase = new stdclass();`（同文件 87-88 行对 `default`/`library` 的写法），否则触发致命错误 `Attempt to modify property "cookie" on null`，**整站 500**（M1 实测踩到，已修复）。

**对后续里程碑的影响**：M2 的 errorlog（读 `saveDays` 走 setting，不涉及 cookie）不受影响；但任何新增的列表页/导出页若要用 cookie 传参，必须同步 `config/filter.php`。建议在 §16 的 DoD 第 3 条（权限）后加一条"若方法读写 cookie，检查 filter.php 规则与键名大小写"。

### 14.15 🟡 **M2 实测又踩两个框架坑（都已修，记此备查）**

1. **新模块必须先在 `module/common/lang/<lang>.php` 的初始化块里 `new stdclass()`**：该文件末尾有一段"菜单复用"（`$lang->xxx->menu = $lang->admin->menu;`），它要求 `$lang->xxx` 已存在——初始化块（`$lang->cron = new stdclass();` 那一片）在前面。只加菜单复用行而漏了初始化，会触发 `Attempt to assign property "menu" on null` → **整站致命错误**（`loadLang('common')` 在每次请求都会跑到）。三语都要改。
2. **`control::send()` 的 fail 分支要求 `message` 是嵌套结构**：`framework/base/control.class.php` 的 `send()` 对非 ajax 的失败响应会执行 `foreach((array)$message as $item => $errors) $message->$item = implode(',', $errors)` —— `message` 必须是 `array('字段' => array('错误文案'))`。传**裸字符串**会触发 `TypeError: implode(): Argument #2 ($array) must be of type ?array, string given`。`dao::getError()` 天然符合（field => [msg]），自定义校验文案要自己包一层。

---

## 15. 发布与升级流程（本仓库专用）

> 前提（主人 2026-10-16）：生产库通过 `upgrade.php` 执行升级，**不是覆盖生产库**。`db/zentao.sql` 只服务全新安装。

### 15.1 每个里程碑的版本号与三处同步

发布自动化（`.github/workflows/release.yml`）会做**三重版本校验**：tag == `config/config.php::$config->version` == `VERSION` 文件。三处必须一起改：

| 文件 | 改什么 |
|---|---|
| `VERSION` | 如 `9.9.1` |
| `config/config.php` | `$config->version = '9.9.1';` |
| git tag | `v9.9.1`（推送 `v9.*` 触发 Actions） |

**发布说明的新约定（2026-10-16 起，主人要求）**：发布页只描述**本次版本增量**（上一个版本 → 本版本），按版本维护在 **`doc/release-notes/<版本>.md`**（仓库内唯一来源，main 与分支同构）；`release.yml` 的 Publish 步骤拼装「抬头 + 该文件 + 通用尾段」，**缺文件直接失败**，杜绝再出现"把历史特性又列一遍"。已落地：`9.9.0.md`（历史增量）、`9.9.1.md`（热修）、`9.10.0.md`（本批交付项，随 M2–M4 继续补充）。⇒ **每个里程碑发版前，先补 `doc/release-notes/<版本>.md`**。

**M0/M1 的用户可见改动（✅ 已写入 `doc/release-notes/9.10.0.md`，随 9.10.0 发布）**：
- 修复用例库删除时报 `Attempt to read property on null`（`module/testsuite/control.php:217`，控制层未取 `$suite` 对象）；
- 修复文档可见性：目录内的文档在文档库"全部"视图不显示、`getStatisticInfo()` 的"所有文档"少算（`module/doc/model.php:515`，对齐 22.6 写法）；
- 版本号规则放开为支持 `9.10.0` 形式（仍拒绝官方禅道版本）。

**版本号序列**：`9.9.1` → `9.9.2` → …，或跳段到 `9.10.0`、`9.11.0`（规则见 §1.4 约束 2；跳段后须单独验证一次"来源=新段版本"的升级链路）。

### 15.2 升级（既有站点）

```bash
# 0) 先备份数据库！（§16 DoD 强制）
# 1) 覆盖部署（不影响 config/my.php、附件与各 .env）
cd /www/wwwroot/dev.wenyinos && tar -xzf ZenTaoPMS-9.9.1-full.tar.gz
# 2) 修正可写目录属主
chown -R www:www config tmp www/data
# 3) 执行升级（CLI 通道，免放开 Web 入口）
sudo -u www /www/server/php/85/bin/php bin/ztcli 'http://dev.wenyinos.com/upgrade-execute.html'
sudo -u www /www/server/php/85/bin/php bin/ztcli 'http://dev.wenyinos.com/upgrade-afterExec.html'
# 4) 清 opcache
#    （tmp/model/*.php 由 execute() 末尾的 clearModelCache() 自动清理）
```

**顺序硬约束**：**必须先跑第 3 步再访问新功能页**。窗口期内新页面会走 §14.2 的友好提示，不会 500，但仍应尽快执行升级。

**升级结果验证**（三选一即可）：
1. `bin/ztcli '...m=upgrade&f=execute'` 返回成功页（无 `self::$errors`）；
2. `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('zt_pivot','zt_pivotspec','zt_pivotdrill','zt_errorlog','zt_errorlogreq','zt_docaction');` = **6**；
3. 后台 `admin|checkdb` 跑通（注意：本仓 `checkDB()` 实现是 `REPAIR TABLE` 逐表修复，**不比对 zentao.sql**，所以它只能证明表可访问，不能证明表齐全 —— 用第 2 条作为权威校验）。

**幂等性要求**：升级脚本可重复执行，第二次执行不得报错、不得重复插入种子行/重复建表。

### 15.3 全新安装

`db/zentao.sql` 已同步全部新增 DDL，install 向导会按 `;` 切分建表（§1.4 约束 3）。装完必须核对上面第 2 条的 6 张表 + 1 行模板范围库 + 1 行 errorlog cron。

### 15.4 回退（每个功能独立可回退）

| 功能 | 回退动作 | 回退后验证 |
|---|---|---|
| 3-2 pivot | `DROP TABLE zt_pivot, zt_pivotspec, zt_pivotdrill` + `reportList` 改回 `report` + 删 `module/pivot/` | `report|productSummary` 等 5 页正常 |
| 4 errorlog | `DROP TABLE zt_errorlog, zt_errorlogreq` + 删 cron 行 + 删 `module/errorlog/`；框架侧删 `saveError` 里新增的 1 行 | 触发 PHP 错误仅落文件日志，页面行为与改动前一致 |
| 5 文档 | `ALTER TABLE zt_doc DROP COLUMN \`order\`; ALTER TABLE zt_doclib DROP COLUMN type; DROP TABLE zt_docaction;` + 删新增视图/方法 | 文档列表/树/收藏/导入导出正常 |
| 1 看板 | 删 `zt_config` 的 `kanbanOrder` 行 | 看板回默认排序 |
| 3-1 / 6-x | 纯新增代码，删文件/方法即可 | 既有页面无回归 |

**回退前必须备份数据库**（新增表虽不参与既有业务流程，但 `zt_doc.order` / `zt_doclib.type` 是既有表的列变更）。

---

## 16. Definition of Done（每个功能的统一完成标准）

任何功能只有全部满足以下 10 条才算完成：

1. **代码**：`php -l` 通过；全量 lint（排除两个部分类体 ext 文件与 `tmp/`、`www/data/`）通过。
2. **数据**：新增表/列在"全新安装"与"9.8.3_1 升级""9.9.x 升级"三条路径都存在；升级脚本重复执行不报错。
3. **权限**：新增方法有权限位（`module/group/lang/resource.php` + `methodOrder`）；用**非 admin 账号**验证入口显隐正确（§14.4）。
3b. **cookie**：若方法读写 cookie，必须在 `config/filter.php` 补规则，且**键名用全小写方法名**、父级先 `new stdclass()` 初始化（§14.14）。
4. **语言**：`zh-cn`/`en`/`zh-tw` 三语键齐备（`array_keys` 对比）。
5. **界面**：10.1 视图 + ZUI 1.8.1；三主题（wenyin/lightblue/blackberry）可用；440px 窄屏不溢出。
6. **状态机**：所有状态流转走既有 model 方法，**零 `UPDATE ... SET status`**。
7. **回归**：跑完 §17 的最小回归清单。
8. **性能**：达到 §14.10 的预算。
9. **文档**：`README.md` + `README.zh-CN.md` 的"What's New / 新特性"补条目；`AGENTS.md` 若新增机制（如 pivot 只读约定、errorlog 开关）则同步；发布说明由 release.yml 的 notes 生成，需要时同步更新。
10. **归档**：禅道建任务（含实施要点与验收）→ 完成后 `zentao_finish_task` 记工时 → 提交信息带 `task #N` → `zentao_sync_commits` 关联。

---

## 17. 最小回归清单（每个功能完成后必跑）

数据无关的固定项：

| # | 项目 | 命令/操作 | 期望 |
|---|---|---|---|
| 1 | 全量 lint | 容器内 `find module -name '*.php' \| xargs php -l` | 无输出 |
| 2 | 数据库结构 | `admin|checkdb`（后台或 ztcli） | 全部 `OK` |
| 3 | 登录/SSO | 退出 → 重新登录 | 正常，无 `wy_api.log` 新报错 |
| 4 | 看板/列表页切换器 | 项目/产品/用例库页点左上角切换器 | 下拉展开、搜索、跳转正常（上次刚修） |
| 5 | 仪表盘（我的地盘） | 访问 `/my/` | 8 个块正常分栏，无空白块 |
| 6 | 动态流 | `/my/dynamic`、`/project/dynamic-9` | 日期分组 + 双向翻页正常 |
| 7 | 文档收藏 | 文档页星标 → "我的收藏" | 写入并即时可见 |
| 8 | MCP | `zentao_ping` + `zentao_list_tasks` | 通；若改过 doc 模块，加跑 §14.7 三个工具 |
| 9 | 游客态 | 未登录访问项目页 | 正常渲染，不被权限守卫掐断（历史 Bug） |
| 10 | 扩展合并缓存 | 若有 `module/*/ext/` 改动 → 清 `tmp/model/*.php` + reload php-fpm | 页面生效 |

功能专属回归：各功能章节的"检查点"表逐条执行。

---

## 18. Spike 清单与估算不确定性

**开工前先做 4 个 spike（合计约 4 人时，已计入 §0.2 的 16）**，用实测替换估算：

| # | Spike | 目的 | 判据 |
|---|---|---|---|
| 1 | ZUI `$.fn.sortable` 在同列卡片容器内的行为 | 决定看板 A-3 是 12h 还是 20h+ | 能稳定拿到 `finish` 回调与新的 DOM 顺序；`draggable` 不与既有 `droppable` 抢事件 |
| 2 | 服务端 rowspan `<table>` 在 10.1 皮肤下的渲染 | 决定 pivot/矩阵是否需自写 CSS | 两级表头 + `rowspan` 不错位；**不启用** `data-ride='table'` 时不触发 fixed-header 克隆 |
| 3 | eCharts 在三主题 + 窄屏下的表现 | 决定年度总结 B1-6 工时 | 三主题配色可用、440px 不溢出、体积加载可接受 |
| 4 | `zt_errorlog` 写库在"库不可用"时的行为 | 验证 §5.8 的最高风险项 | 停 mysqld 后页面不白屏、无递归、`tmp/log` 只多 1 条 |

**估算的最坏情况（±30% 之上还有两类系统性风险）**：
1. **pivot 引擎**：36 方法的传递闭包是静态分析结果，若 `genSheet` 运行期还依赖未识别的辅助方法，工作量可能翻倍（26h → 50h+）。**缓解**：B2-2 用一条测试 SQL 端到端跑通才进入 B2-3，不通过就停下重新评估。
2. **前端重写**：22.6 的交互复杂度（拖拽/级联/dtable 配置体系）在 ZUI 1.8.1 上要手写 JS，历史上这一项最容易超支（上次移动端重写即为此类）。**缓解**：每个功能的前端部分单独列检查点，超支即报告而非硬扛。

### 18.1 M0 spike 实测结果（2026-10-16，已完成）

| # | Spike | 结论 | 证据 |
|---|---|---|---|
| 1 | ZUI `$.fn.sortable` 同列拖拽 | **通过，风险低** ⇒ A-3 维持 12h | `$.fn.sortable`/`$.fn.droppable` 均为函数；对容器 `sortable({selector:'.card', finish:...})` 后合成拖拽（mousedown→20×mousemove→mouseup）：**DOM 顺序由 `1,2,3` 变为 `2,3,1`，`finish` 回调捕获到 `"2,3,1"`** —— 正是看板需要的"排序 + 落库"链路。另注：ZUI 1.8.1 用**鼠标事件**实现（`dragstart/dragover` 命中 0 次），非 HTML5 拖放；仓库已有 4 处生产用法（`doc/js/common.js:27`、`branch/js/manage.js:16`、`custom/js/ajaxmenu.js:77`、`block/js/dashboard.js:220`），且 `doc`/`branch` 两处就是"`finish` 里收集顺序 → POST 落库"的现成范式，看板可直接照抄 |
| 2 | 服务端 rowspan 两级表头 | **通过**（bymodule/矩阵/pivot 可用） | 注入 `<thead>` 两级 + `td[rowspan=2]` 的表：rowspan 单元格高度 **59px** vs 单行 **30px**（跨 2 行正确）；首行单元格数 6（1 rowspan + 3 + 2）符合预期；**三主题 + 440px 窄屏**下均无错位。前提：**不要加 `data-ride='table'` 或 datatable 模式**（否则 fixed-header 克隆会打散合并列） |
| 3 | eCharts 三主题 + 窄屏 | **通过，但发现阻塞级问题**（见 §1.3 修正） | `common` 构建缺 radar；换 **全量构建 5.5.1** 后 radar+bar 同页渲染 OK（canvas 60186 非白像素；三主题一致；440px 下 `overflowPx=0`）。**图表配色需按主题注入**（默认调色板是 echarts 绿色，与 WenYin 紫不一致） |
| 4 | errorlog「库不可用」不递归 | **通过，且证明 try/catch 是硬要求** | 独立脚本实测：**带** `try/catch(Throwable)` 时脚本执行到 `REACHED_END`、exit 0（无递归、无白屏）；**不带**时直接 `Fatal error: Uncaught PDOException`（= 白屏）。⇒ §5.3 改动 3 第 7 条的"任何失败一律吞掉"不是可选项 |

**对估算的影响**：Spike 1/2/4 结果符合预期，对应工时不变；Spike 3 换构建只增加约 0 人时（下载 + 校验已含在 §1.3 的 2 人时内），但**避免了一个会在功能 3-1 中途才暴露的阻塞**。

**不建议一次开工全部 8 项**：按 §11 里程碑分批，每批结束更新本文件的"实际工时 vs 估算"，用真实数据修正后续批次的估算。

---

## 附：本文件的用途

1. **评审依据**：主人评估后决定开工范围（§11 里程碑）。§13 的 8 项决策已批准，§14 的 12 项补充按推荐方案执行。
2. **实施依据**：每个功能的 `文件:行号` 证据可直接定位，避免后续会话重复摸索。
3. **验收依据**：每节"验收标准"与"检查点" + §16 DoD + §17 最小回归清单，可直接转成测试用例。
4. **对照索引**：22.6 参照位置已逐条列出，含"不可复用"清单（zin 模板、8 张 `zt_kanban*` 表、加密模块）。
5. **升级与发布依据**：§1.4（升级机制的两条硬约束）+ §15（版本号三处同步、覆盖部署 → 跑升级 → 验证 → 回退）。

**版本**：v1.7（2026-10-16，M0 + M1 已交付；**热修 9.9.1 已发布**（main 生产线）；分支 `feat/port-22.6-m0` 版本号定为 9.10.0 并已合并 main 的 9.9.1）
**下一步**：**M2**（错误日志 21h + 年度总结 62h；首次引入新增表 `zt_errorlog`/`zt_errorlogreq`，需按 §1.4 的升级约定做幂等建表；发布版本号为 9.10.0）。
