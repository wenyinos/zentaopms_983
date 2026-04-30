# ZenTao PMS 9.8.3 → PHP 8.4+ 升级评估与操作报告

## TL;DR
> **Summary**: 禅道项目管理系统 v9.8.3 是一个基于 PHP 5.2+ 时代的单体应用，共计 1,138 个 PHP 文件、约 192,485 行代码。升级到 PHP 8.4+ 涉及 3 大类改动：移除已删除的函数/特性、更新过时的第三方库、修复隐含的类型安全问题。**工作量评级：大（Large）**，建议分阶段实施。
> **风险等级**: 🟡 中高风险 — 无自动化测试套件，需依赖手工回归测试
> **预估工作量**: 3-5 人周（含测试）

---

## 一、项目概况

| 维度 | 数据 |
|------|------|
| 项目名称 | ZenTao PMS (禅道项目管理系统) |
| 当前版本 | 9.8.3 |
| PHP 文件总数 | 1,138 |
| 代码总行数 | ~192,485 |
| 当前 PHP 最低要求 | 5.2.0 |
| 目标 PHP 版本 | 8.4+ |
| Composer 依赖管理 | ❌ 无 |
| 自动化测试套件 | ❌ 无 PHPUnit / Pest |
| CI/CD 配置 | ❌ 无 |

### 代码分布

| 目录 | 文件数 | 性质 | 影响级别 |
|------|--------|------|----------|
| `framework/` | 8 | 框架核心 | 🔴 必须修改 |
| `module/` | 793 | 业务模块 | 🟡 部分修改 |
| `lib/` | 292 | 第三方库 | 🔴 必须升级/替换 |
| `extension/` | 33 | 扩展点 | 🟡 部分修改 |
| `config/` | 4 | 配置文件 | 🟢 少量修改 |
| `www/` | 6 | 入口文件 | 🟡 少量修改 |

---

## 二、PHP 版本跨度分析

从 PHP 5.2 → 8.4 跨越了 **12 个大版本**，涉及以下破坏性变更：

```
PHP 5.2 → 5.3:  ereg/split 系列弃用, 命名空间引入
PHP 5.3 → 5.4:  magic_quotes 移除, safe_mode 移除
PHP 5.4 → 5.5:  each() 标记弃用
PHP 5.6 → 7.0:  ereg/split/mcrypt/mysql_* 移除, 标量类型声明
PHP 7.0 → 7.1:  可空类型, void 返回类型
PHP 7.1 → 7.2:  object 类型声明, 参数类型放宽
PHP 7.2 → 7.3:  废弃的函数/特性继续清理
PHP 7.3 → 7.4:  预加载, 类型属性
PHP 7.4 → 8.0:  命名参数, match 表达式, union 类型, create_function 移除
PHP 8.0 → 8.1:  枚举, fibers, readonly 属性, FILTER_SANITIZE_STRING 弃用
PHP 8.1 → 8.2:  动态属性弃用, utf8_encode/decode 弃用, readonly 类
PHP 8.2 → 8.3:  类型化类常量, #[\Override] 属性
PHP 8.3 → 8.4:  属性钩子, 不对称可见性, 弃用更多隐式转换
```

---

## 三、兼容性问题详细清单

### 🔴 P0 — 致命错误（会导致白屏/崩溃）

#### 1. `create_function()` — PHP 8.0 已移除

| 文件 | 行号 | 代码 |
|------|------|------|
| `lib/pinyin/pinyin.class.php` | 46 | `create_function('$matches', 'return "\t" . $matches[0];')` |

**修复方案**: 替换为匿名函数
```php
// Before
$string = preg_replace_callback('/[a-z0-9_-]+/i', create_function('$matches', 'return "\t" . $matches[0];'), $string);
// After
$string = preg_replace_callback('/[a-z0-9_-]+/i', function($matches) { return "\t" . $matches[0]; }, $string);
```

#### 2. `ereg()` — PHP 7.0 已移除

| 文件 | 行号 | 出现次数 |
|------|------|----------|
| `lib/pclzip/pclzip.class.php` | 3396, 4777 | 2 处 |

**修复方案**: 替换为 `preg_match()`
```php
// Before
if (ereg($p_options[PCLZIP_OPT_BY_EREG], $v_header['stored_filename']))
// After
if (preg_match('/' . $p_options[PCLZIP_OPT_BY_EREG] . '/', $v_header['stored_filename']))
```

#### 3. `split()` — PHP 7.0 已移除

| 文件 | 行号 |
|------|------|
| `lib/ubb/ubb.class.php` | 71 |

**修复方案**: 替换为 `explode()`

#### 4. `set_magic_quotes_runtime()` — PHP 7.4 已移除

| 文件 | 行号 | 出现次数 |
|------|------|----------|
| `lib/phpmailer/phpmailer.class.php` | 1471, 1475 | 2 处 |
| `lib/pclzip/pclzip.class.php` | 5347, 5378 | 2 处 |

**修复方案**: 整段删除 magic_quotes 相关代码

#### 5. `get_magic_quotes_gpc()` / `get_magic_quotes_runtime()` — PHP 8.0 已移除

| 文件 | 调用次数 | 说明 |
|------|----------|------|
| `framework/base/helper.class.php` | 3 处 | 框架核心 |
| `lib/base/filter/filter.class.php` | 1 处 | 过滤器核心 |
| `lib/base/dao/dao.class.php` | 1 处 | DAO 核心 |
| `module/common/model.php` | 1 处 | 业务模块 |
| `module/editor/model.php` | 1 处 | 业务模块 |
| `extension/custom/editor/model.php` | 1 处 | 扩展 |
| `lib/phpmailer/phpmailer.class.php` | 1 处 | 第三方库 |
| `lib/purifier/HTMLPurifier/Config.php` | 1 处 | 第三方库 |
| `lib/pclzip/pclzip.class.php` | 1 处 | 第三方库 |

**修复方案**: 所有 `get_magic_quotes_gpc()` 调用直接返回 `false`（PHP 5.4+ 已无 magic quotes），整段逻辑可简化移除。

#### 6. `each()` — PHP 8.0 已移除

| 文件类别 | 文件数 | 调用次数 |
|----------|--------|----------|
| lib/snoopy (第三方) | 1 | 14 |
| lib/api (第三方) | 1 | 14 |
| lib/phpmailer (第三方) | 2 | 3 |
| lib/ubb (第三方) | 1 | 1 |
| **合计** | **5** | **32** |

**修复方案**: 替换为 `foreach`
```php
// Before
while(list($key, $val) = each($array)) { ... }
// After
foreach($array as $key => $val) { ... }
```

---

### 🟡 P1 — 弃用警告（运行时大量 Warning/Deprecated 日志）

#### 7. `utf8_encode()` / `utf8_decode()` — PHP 8.2 弃用，PHP 9.0 移除

| 文件 | 行号 |
|------|------|
| `lib/purifier/HTMLPurifier/Encoder.php` | 402, 454 |

**修复方案**:
```php
// Before
$str = utf8_encode($str);
// After
$str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
```

#### 8. `FILTER_SANITIZE_STRING` — PHP 8.1 弃用

| 文件 | 行号 |
|------|------|
| `module/user/model.php` | 722 |

**修复方案**: 替换为 `FILTER_SANITIZE_FULL_SPECIAL_CHARS`

#### 9. 动态属性（Dynamic Properties）— PHP 8.2 弃用，PHP 9.0 移除

这是**影响面最广**的问题。禅道框架大量使用动态属性模式：

```php
$config->xxx = 'value';    // 在 stdClass 上 → OK
$lang->module->key = 'v';  // 在自定义类上 → 需要 #[AllowDynamicProperties]
```

**受影响核心类**（均无 `#[AllowDynamicProperties]` 标注）：

| 类 | 文件 | 风险 |
|------|------|------|
| `baseRouter` | `framework/base/router.class.php` | 🔴 核心 |
| `baseControl` | `framework/base/control.class.php` | 🔴 核心 |
| `baseModel` | `framework/base/model.class.php` | 🔴 核心 |
| `baseHelper` | `framework/base/helper.class.php` | 🔴 核心 |
| `baseDAO` | `lib/base/dao/dao.class.php` | 🔴 核心 |

**注意**: `stdClass` 本身在 PHP 8.2+ 仍然支持动态属性，但所有自定义类都需要处理。

**修复方案**:
- 方案 A: 在基类上添加 `#[AllowDynamicProperties]` 属性（快速，兼容性好）
- 方案 B: 为所有动态属性声明正式属性（彻底，但改动量巨大）

**推荐**: 方案 A 优先，方案 B 作为后续优化

#### 10. 隐式可空参数类型 — PHP 8.4 弃用

```php
// PHP 8.4 deprecated: implicit nullable via default = null
function foo(Type $param = null)   // deprecated
function foo(?Type $param = null)  // correct
```

**扫描结果**: 当前代码中仅发现 1 处（`lib/crontab/crontab.class.php:55`），影响面小。

---

### 🟢 P2 — 低风险/需关注

#### 11. `extract()` 使用 — 安全隐患 + 行为变更

| 文件 | 调用次数 |
|------|----------|
| `framework/base/control.class.php` | 2 |
| `framework/base/router.class.php` | 2 |
| `module/bug/control.php` | 3 |
| `module/todo/model.php` | 6 |
| `module/file/model.php` | 3 |
| `module/action/model.php` | 2 |
| `module/project/model.php` | 1 |

**合计**: 10 个文件, ~19 处调用

**风险**: `EXTR_OVERWRITE`（默认）可能覆盖已有变量。PHP 8.x 行为未变，但属于代码安全隐患。

#### 12. `eval()` 使用

| 文件 | 调用次数 |
|------|----------|
| `framework/base/helper.class.php` | 1 |
| `lib/pclzip/pclzip.class.php` | 8 |
| `lib/snoopy/snoopy.class.php` | 1 |
| `lib/purifier/HTMLPurifier/` | 2 |
| `lib/phpmailer/phpmailer.class.php` | 1 |
| `lib/api/api.class.php` | 1 |

**风险**: 安全风险为主，PHP 8.x 中 eval 行为未变。

#### 13. `"${var}"` 字符串插值 — PHP 8.2 弃用

仅在 `www/ioncube.php:2482` 发现 1 处，该文件为第三方加密加载器。

---

## 四、第三方库兼容性评估

| 库名 | 当前版本 | PHP 8.4 兼容性 | 处理方案 |
|------|----------|----------------|----------|
| **PHPMailer** | 5.1 | ❌ 不兼容 | 🔴 升级到 6.x（需 API 适配） |
| **Snoopy** | 未版本化 | ❌ 不兼容 | 🔴 替换为 Guzzle/cURL |
| **PclZip** | 2.8.2 | ❌ 不兼容 | 🔴 替换为 ZipArchive (PHP 内置) |
| **HTMLPurifier** | 未版本化 | ⚠️ 部分兼容 | 🟡 升级到最新版 |
| **Spyc (YAML)** | 0.5 | ⚠️ 可能兼容 | 🟢 验证后保留或升级 |
| **QRCode** | 未版本化 | ⚠️ 需验证 | 🟡 测试验证 |
| **PHPMailer POP3** | 5.1 | ❌ 不兼容 | 🔴 随 PHPMailer 一起升级 |
| **HyperDown** | 未版本化 | ⚠️ 需验证 | 🟡 测试验证 |

### 第三方库升级详情

#### PHPMailer 5.1 → 6.x
- **影响范围**: `lib/phpmailer/` (2 个文件)
- **调用方**: `module/mail/model.php` 等
- **API 变更**: 命名空间引入 (`PHPMailer\PHPMailer\PHPMailer`), 异常处理变更
- **工作量**: 约 2-3 天

#### Snoopy → cURL/Guzzle
- **影响范围**: `lib/snoopy/snoopy.class.php` (1300+ 行)
- **调用方**: `lib/api/api.class.php` (复制了一份), 多个 module
- **关键注意**: `lib/api/api.class.php` 本身就是 snoopy 的副本,有独立的 14 处 `each()` 调用
- **工作量**: 约 3-5 天

#### PclZip → ZipArchive
- **影响范围**: `lib/pclzip/pclzip.class.php` (5000+ 行)
- **调用方**: `module/backup/model.php`, `module/extension/model.php` 等
- **PHP 内置**: `ZipArchive` 从 PHP 5.2 起可用
- **工作量**: 约 2-3 天

---

## 五、风险评估矩阵

| 风险 | 等级 | 影响 | 缓解措施 |
|------|------|------|----------|
| 无自动化测试 | 🔴 高 | 回归缺陷难以发现 | 建立冒烟测试脚本 |
| 核心框架改动 | 🔴 高 | 影响所有模块 | 基类优先处理,全面回归 |
| 第三方库 API 变更 | 🟡 中 | 邮件/HTTP 功能异常 | 逐个升级并验证 |
| 动态属性弃用 | 🟡 中 | PHP 9.0 将报错 | 基类添加 AllowDynamicProperties |
| 性能回退 | 🟢 低 | PHP 8.4 通常更快 | 基准测试对比 |

---

## 六、分阶段操作计划

### 阶段 1: 准备与环境搭建（1-2 天）

1. 建立 PHP 8.4 开发/测试环境
2. 在 PHP 8.4 下运行 `php -l` 全量语法检查
3. 配置 `error_reporting(E_ALL)` 捕获所有警告
4. 建立手工冒烟测试清单（安装、登录、创建项目/产品/需求/Bug/任务等核心流程）

### 阶段 2: 框架核心修复（2-3 天）

优先级最高，影响全局：

1. **`framework/base/helper.class.php`** — 移除 `get_magic_quotes_gpc()` 调用
2. **`framework/base/router.class.php`** — 添加 `#[AllowDynamicProperties]`
3. **`framework/base/control.class.php`** — 添加 `#[AllowDynamicProperties]`
4. **`framework/base/model.class.php`** — 添加 `#[AllowDynamicProperties]`
5. **`lib/base/dao/dao.class.php`** — 移除 `get_magic_quotes_gpc()` + 添加 `#[AllowDynamicProperties]`
6. **`lib/base/filter/filter.class.php`** — 移除 `get_magic_quotes_gpc()` 调用

### 阶段 3: 第三方库升级（5-8 天）

按依赖重要性排序：

1. **PHPMailer 5.1 → 6.x** — 升级库文件 + 适配调用方
2. **Snoopy → cURL 封装** — 替换 HTTP 客户端
3. **PclZip → ZipArchive** — 替换压缩库
4. **HTMLPurifier → 最新版** — 升级库文件
5. **Spyc / QRCode / HyperDown** — 验证兼容性

### 阶段 4: 业务代码修复（3-5 天）

1. **`lib/ubb/ubb.class.php`** — `split()` → `explode()`
2. **`lib/pinyin/pinyin.class.php`** — `create_function()` → 匿名函数
3. **`module/user/model.php`** — `FILTER_SANITIZE_STRING` → `FILTER_SANITIZE_FULL_SPECIAL_CHARS`
4. **`module/common/model.php`** — 移除 `get_magic_quotes_gpc()`
5. **`module/editor/model.php`** — 移除 `get_magic_quotes_gpc()`
6. **`extension/custom/editor/model.php`** — 移除 `get_magic_quotes_gpc()`
7. 全量扫描 `each()` 在 view 模板中的使用（jQuery `.each()` 不受影响）

### 阶段 5: 版本约束与入口更新（0.5 天）

1. **`module/install/model.php:83`** — 更新 PHP 版本检查：
   ```php
   // Before
   return version_compare(PHP_VERSION, '5.2.0') >= 0 ? 'ok' : 'fail';
   // After
   return version_compare(PHP_VERSION, '8.4.0') >= 0 ? 'ok' : 'fail';
   ```
2. 更新安装引导页面的 PHP 版本提示

### 阶段 6: 全面测试（3-5 天）

1. 安装流程测试
2. 用户登录/权限测试
3. 核心业务流程：产品管理、项目管理、需求管理、Bug 管理、任务管理
4. 文档/附件上传下载
5. 邮件发送功能
6. 定时任务执行
7. 数据库升级流程
8. 扩展/插件功能
9. 性能基准对比（PHP 当前版本 vs 8.4）

---

## 七、改动影响汇总

| 分类 | 文件数 | 调用点数 | 工作量 |
|------|--------|----------|--------|
| `get_magic_quotes_gpc/runtime` 移除 | 10 | 12 | 0.5 天 |
| `each()` → `foreach` | 5 | 32 | 0.5 天 |
| `create_function()` → 匿名函数 | 1 | 1 | 0.25 天 |
| `ereg()` → `preg_match()` | 1 | 2 | 0.25 天 |
| `split()` → `explode()` | 1 | 1 | 0.1 天 |
| `set_magic_quotes_runtime()` 移除 | 2 | 4 | 0.25 天 |
| `utf8_encode/decode` → mb_* | 1 | 2 | 0.1 天 |
| `FILTER_SANITIZE_STRING` 替换 | 1 | 1 | 0.1 天 |
| `#[AllowDynamicProperties]` 添加 | 5 | 5 | 0.25 天 |
| PHPMailer 升级 | 2+ | 5+ | 2-3 天 |
| Snoopy 替换 | 2 | 30+ | 3-5 天 |
| PclZip 替换 | 1 | 5+ | 2-3 天 |
| HTMLPurifier 升级 | 10+ | — | 1 天 |
| 测试回归 | — | — | 3-5 天 |
| **合计** | **~42** | **~100+** | **~15-20 人天** |

---

## 八、建议与决策点

### 决策 1: 升级策略

| 方案 | 描述 | 优点 | 缺点 |
|------|------|------|------|
| **A. 一步到位** | 直接要求 PHP 8.4+ | 简单明确 | 风险集中 |
| **B. 阶梯升级** | 先 PHP 7.4 → 8.1 → 8.4 | 风险分散 | 耗时更长 |
| **C. 双版本兼容** | 代码同时兼容 PHP 7.4 和 8.4 | 平滑过渡 | 维护成本高 |

**推荐方案 A** — 禅道是内部部署系统，不存在"用户还在用旧版本"的问题，一步到位效率最高。

### 决策 2: 第三方库策略

| 方案 | 描述 |
|------|------|
| **A. 原地升级** | 在 lib/ 内直接替换新版文件 |
| **B. Composer 管理** | 引入 Composer 管理第三方依赖 |
| **C. 自研替代** | 封装自有 HTTP/压缩/邮件组件 |

**推荐方案 A**（短期）→ **方案 B**（中期）。保持现有项目结构不变，直接替换库文件。中期可逐步引入 Composer。

### 决策 3: 动态属性处理

| 方案 | 描述 |
|------|------|
| **A. `#[AllowDynamicProperties]`** | 基类打标注，快速解决 |
| **B. 声明正式属性** | 为所有动态属性定义类属性 |

**推荐方案 A** — 禅道框架的核心设计就是基于动态属性的全局对象（$config, $lang），全面重构工作量不现实。

---

## 九、快速修复清单（可直接执行）

以下修改可以安全地批量执行，不影响功能逻辑：

```bash
# 1. 框架核心 - 移除 get_magic_quotes_gpc 调用
# framework/base/helper.class.php 第 216, 223, 266 行

# 2. 为框架基类添加 #[AllowDynamicProperties]
# framework/base/router.class.php
# framework/base/control.class.php
# framework/base/model.class.php
# lib/base/dao/dao.class.php

# 3. 更新 PHP 版本检查
# module/install/model.php 第 83 行

# 4. 替换 create_function
# lib/pinyin/pinyin.class.php 第 46 行

# 5. 替换 FILTER_SANITIZE_STRING
# module/user/model.php 第 722 行
```

---

## 十、结论

ZenTao PMS 9.8.3 升级到 PHP 8.4+ **技术上完全可行**，但工作量不可忽视：

- **核心代码改动**（框架 + 业务）约 3-5 天，风险可控
- **第三方库升级**约 5-8 天，是最大的不确定因素
- **测试回归**约 3-5 天，因无自动化测试需全手工覆盖

**关键路径**: 第三方库升级（尤其是 Snoopy 和 PHPMailer）→ 框架核心修复 → 全量回归测试

**建议立即启动**: 阶段 2（框架核心修复）和阶段 3（第三方库升级评估）可并行推进。
