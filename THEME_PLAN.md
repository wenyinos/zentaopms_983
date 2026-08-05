# WenYinOS 主题制作执行计划

> 目标：为本项目新增一个独立主题 `wenyin`，放在 `www/theme/wenyin/` 下，参考 https://wenyinos.com/ 的紫色系配色与样式，**不覆盖、不修改任何已有主题文件**。
>
> 制定日期：2026-08-04
>
> ## ✅ 主人已确认事项（2026-08-04）
>
> 1. 主题目录名/主题 key：**`wenyin`**
> 2. 顶栏主题菜单显示名：**三种语言统一为 `WenYin`**
> 3. **不**设为默认主题，仅注册为可选项（不写 `config/my.php`，不改 `config/config.php`）
> 4. **允许**在 `theme/default/` 下新增 3 个合并 CSS（`{语言}.wenyin.css`），生产模式生效必需

---

## 一、调研结论（主题加载机制）

### 1.1 CSS 加载的两条路径

入口在 `module/common/view/header.lite.html.php`（第 20-36 行）：

| 模式 | 条件 | 加载内容 |
|------|------|----------|
| **调试模式** | `$config->debug` 为真 | `theme/zui/css/min.css` + `theme/default/style.css` + `theme/lang/{lang}.css`，且当前主题非 default 时追加 `theme/{主题名}/style.css` |
| **生产模式** | `$config->debug` 为假 | 单文件 `theme/default/{lang}.{主题名}.css`（预合并文件） |

关键代码：

```php
if($config->debug) {
    css::import($themeRoot . 'zui/css/min.css');
    css::import($defaultTheme . 'style.css');
    css::import($langTheme);
    if(strpos($clientTheme, 'default') === false) css::import($clientTheme . 'style.css');
} else {
    css::import($defaultTheme . $this->cookie->lang . '.' . $this->cookie->theme . '.css');
}
```

### 1.2 当前环境判定

- `config/config.php`、`www/index.php` 中均**无** `$config->debug` 赋值，`config/my.php` 不存在；
- 因此 `$config->debug` 为空 → **默认走生产模式（合并 CSS 分支）**；
- 已验证合并文件构成：`zh-cn.lightblue.css`（197KB）≈ `zui/css/min.css`（123KB）+ `default/style.css`（55KB）+ `lang/*.css`（0KB）+ `lightblue/style.css`（约19KB）。

**结论：新主题要在当前环境生效，必须生成 `theme/default/{lang}.wenyin.css` 合并文件。**

### 1.3 主题名注册与校验

- `framework/base/router.class.php:897`（`setClientTheme`）：主题名必须存在于 `$this->lang->themes[$theme]`，否则回退到默认主题；
- 主题名下拉菜单由 `module/common/model.php:254` 遍历 `$app->lang->themes` 输出；
- 主题名注册位于 `module/common/lang/{zh-cn,en,zh-tw}.php` 第 150-151 行；
- 框架支持扩展语言文件：`extension/custom/common/ext/lang/{lang}/*.php` 会在主语言文件之后加载（`loadLang`，router.class.php:2047），已有 `editor.php` 先例。

**结论：通过扩展语言文件注册新主题名，不修改核心 lang 文件。**

### 1.4 wenyinos.com 配色提取结果

已从 `https://wenyinos.com/assets/css/style.css` 提取 CSS 变量：

| 变量 | 值 | 用途 |
|------|-----|------|
| `--purple-primary` | `#6f42c1` | 主色（按钮/链接/激活态） |
| `--purple-dark` | `#4a2c82` | 深紫（导航/header 底色） |
| `--purple-deep` | `#3b2266` | 最深紫（hover 加深/文字强调） |
| `--bg-purple` | `#5a3e8e` | 中紫（hover 过渡） |
| 渐变 | `linear-gradient(160deg, #4a2c82 0%, #6f42c1 50%, #8b5fd6 100%)` | Hero/顶栏渐变 |
| `--page-bg` | `#fcfafd` | 页面背景（淡紫白） |
| `--card-bg-warm` | `#f6f2fa` / `#f3edf9` / `#f8f4fc` | 卡片/表格高亮背景 |
| `--text-ink` | `#2d2140` | 主文字（深紫墨） |
| `--text-body` | `#504a5e` | 正文文字 |
| `--text-muted-purple` | `#6b5b8a` | 次要文字 |
| `--border-soft` | `#e5ddf0` | 柔和边框 |
| 字体 | `'Questrial', 'Noto Sans SC', sans-serif` | 显示/正文字体栈 |
| 圆角 | 卡片 6px、胶囊 46px | 风格特征 |

### 1.5 已有主题文件结构

- `lightblue`/`blackberry`/`green`/`red` 各仅含一个 `style.css`（约 11 行压缩 CSS），本质是 **ZUI 主色覆盖表**：替换 `a` 颜色、`.btn-primary`、`.navbar-inverse`、`#header`、`#mainmenu`、`#modulemenu`、表单焦点、下拉菜单、日期选择器、chosen 组件等约 40 组选择器的颜色值；
- 其中 `lightblue`（蓝色系 #145ccd/#3280fc）与 WenYin 紫最接近，作为结构模板最合适；
- 注意：`green`/`red` 目录的 `style.css` 虽存在，但未在 lang 中注册，属于未启用主题，不影响本任务。

---

## 二、配色映射方案（蓝色 → 紫色）

以 `lightblue/style.css` 的颜色角色为基准，映射到 WenYin 紫：

| 角色 | lightblue 原值 | wenyin 新值 |
|------|---------------|-------------|
| 链接色 / 主操作色 | `#145ccd` | `#6f42c1` |
| 链接 hover | `#0d3d88` | `#3b2266` |
| btn-primary 底色 | `#3280fc` | `#6f42c1` |
| btn-primary hover | `#0a67fb` | `#5a33a5` |
| btn-primary border | `#035eed` | `#4a2c82` |
| label/badge hover | `#0462f7` | `#5a33a5` |
| 高亮背景（hover 行） | `#ebf2f9` | `#f3edf9` |
| 高亮背景 hover | `#c4d9ed` | `#e5dcf2` |
| navbar-inverse 底色 | `#145ccd` | `#4a2c82` |
| navbar border | `#10479f` | `#3b2266` |
| 菜单项底色 | `#2a74ea` | `#5a3e8e` |
| 菜单 hover | `#4284ec` | `#6f42c1` |
| `#header` 背景 | `#145ccd` | 渐变 `linear-gradient(160deg, #4a2c82, #6f42c1, #8b5fd6)` |
| 登录页背景 | `#145ccd` | 同上渐变 |
| 焦点框 rgba | `rgba(20,92,205,.6)` | `rgba(111,66,193,.6)` |

附加 WenYinOS 风格增强（在覆盖表之后追加）：

1. `body` 页面背景 `#fcfafd`，文字色 `#2d2140`；
2. 字体栈追加 `'Noto Sans SC'` 优先；
3. `#header` 使用 160deg 三段紫色渐变；
4. 表格/卡片圆角统一 6px（`table, #wrap .outer, #modulemenu li>a` 等）；
5. 按钮、输入框柔和边框 `#e5ddf0`。

---

## 三、执行步骤

### 步骤 1：创建主题目录与核心样式

- 新建目录 `www/theme/wenyin/`；
- 编写 `www/theme/wenyin/style.css`：以 `lightblue/style.css` 为结构模板，按「二、配色映射」替换全部颜色值，并追加 WenYinOS 风格增强段（渐变 header、页面背景、字体、圆角）。
- **新增文件，不覆盖任何已有文件。**

### 步骤 2：注册主题名（走扩展机制，不动核心）

新建 3 个扩展语言文件，每个文件仅追加一行主题注册：

```
extension/custom/common/ext/lang/zh-cn/wenyintheme.php   → $lang->themes['wenyin'] = 'WenYin';
extension/custom/common/ext/lang/en/wenyintheme.php      → $lang->themes['wenyin'] = 'WenYin';
extension/custom/common/ext/lang/zh-tw/wenyintheme.php   → $lang->themes['wenyin'] = 'WenYin';
```

（显示名已确认：三种语言统一为 "WenYin"。）

### 步骤 3：生成生产模式合并文件

用脚本按框架既有顺序拼接（`zui/min.css` + `default/style.css` + `lang/{lang}.css` + `wenyin/style.css`），**新增** 3 个文件：

```
www/theme/default/zh-cn.wenyin.css
www/theme/default/en.wenyin.css
www/theme/default/zh-tw.wenyin.css
```

- 只新增，不触碰已有的 `*.lightblue.css` / `*.blackberry.css` 等；
- 若主人认为 `theme/default/` 下也不应新增文件，替代方案是让环境启用 debug 模式（`config/my.php` 设 `$config->debug = true;`），则只靠步骤 1 的独立 `style.css` 即可生效 —— 但会改变全站资源加载方式，默认不采用。

### 步骤 4：验证

1. `curl -I` 确认 4 个新 CSS 均可通过 Web 访问（HTTP 200）；
2. 启动 `php -S 127.0.0.1:8080 -t www`，请求登录页/首页，确认 `<head>` 中引用的是 `zh-cn.wenyin.css`（需先通过 Cookie `theme=wenyin` 模拟选择，或经 UI 顶栏「主题」菜单切换）；
3. 检查合并文件内容与拼接顺序正确（对比 lightblue 合并文件的结构）；
4. 如数据库已配置，做一次 UI 走查：登录页（`body.m-user-login` 渐变）、顶栏、主菜单、表格 hover、按钮、表单焦点框。若未装库，则以静态请求验证为准并在结果中如实说明。

### 步骤 5：设为默认主题 —— ❌ 已确认不执行

主人确认新主题仅注册为可选项，不设为默认。不写 `config/my.php`，不改 `config/config.php`；用户通过顶栏「主题」菜单手动切换。

---

## 四、变更清单与影响面

| 操作 | 路径 | 性质 |
|------|------|------|
| 新增 | `www/theme/wenyin/style.css` | 主题核心样式 |
| 新增 | `extension/custom/common/ext/lang/{zh-cn,en,zh-tw}/wenyintheme.php` | 主题名注册（3 个小文件） |
| 新增 | `www/theme/default/{zh-cn,en,zh-tw}.wenyin.css` | 生产模式合并文件（脚本生成） |
| 修改 | **无** | 不修改任何已有文件 |

- 核心代码（framework/module/config）零改动；
- 已有主题（lightblue/blackberry/green/red/default 基础样式）零影响；
- 切换回原主题：顶栏主题菜单选回"亮蓝"即可，无任何副作用；卸载主题：删除上述新增文件即可。

## 五、风险与回滚

| 风险 | 等级 | 应对 |
|------|------|------|
| 合并文件拼接顺序与官方不一致导致样式错乱 | 低 | 已逆向确认 lightblue 合并文件构成，按同序拼接并比对字节结构 |
| 扩展语言文件加载顺序导致主题名校验失败 | 低 | `loadLang` 先加载主 lang 再加载 ext lang，且 `setClientTheme` 在其后执行；验证步骤 2 会实测 |
| 未装数据库无法完整 UI 验证 | 中 | 以静态请求 + CSS 内容校验代替，并如实报告 |
| 回滚 | — | 删除第四节所列全部新增文件即恢复原状 |

## 六、确认记录

以下事项已由主人确认（2026-08-04），不再询问：

1. ✅ 主题目录名与 key：`wenyin`
2. ✅ 主题显示名：三种语言统一为 `WenYin`
3. ✅ 不写 `config/my.php`、不改 `config/config.php`，新主题仅作为可选项
4. ✅ 允许在 `theme/default/` 下新增 `{zh-cn,en,zh-tw}.wenyin.css` 合并文件（生产模式生效必需）

下一步：按「三、执行步骤」第 1-4 步实施。
