# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

这是 ZenTao 项目管理系统（禅道）的定制版本 WenYinOS ZenTaoPMS 9.8.3_1，采用 PHP + MySQL 的经典单体架构，不依赖 Composer/Node 构建管线。

## 常用开发命令

### 启动本地开发服务器

```bash
php -S 127.0.0.1:8080 -t www
```

### 通过 CLI 执行模块操作

```bash
php bin/ztcli 'http://127.0.0.1:8080/index.php?m=<module>&f=<method>&params...'
```

示例：
```bash
# 检查数据库一致性
php bin/ztcli 'http://127.0.0.1:8080/index.php?m=admin&f=checkdb'

# 其他模块操作格式：m=模块名&f=方法名&参数
```

### 初始化 CLI 辅助脚本（可选）

```bash
bash bin/init.sh /usr/bin/php http://127.0.0.1:8080
```

### 数据库相关

数据库 schema 和升级脚本位于 `db/` 目录。执行数据库相关改动后，通过 Web UI 或 CLI 运行 `admin->checkdb` 验证一致性。

## 架构概述

### 核心结构

```
module/           业务模块（MVC 模式）
├── <module>/     每个模块包含：
│   ├── control.php      控制器（业务逻辑入口）
│   ├── model.php        数据模型和业务方法
│   ├── config.php       模块配置
│   ├── view/*.html.php  视图模板
│   ├── css/             模块样式
│   └── js/              模块脚本

framework/        核心框架（路由、基类）
├── base/         基础类（router、control、model、helper）
└── router.class.php    路由和请求调度

www/              Web 入口和静态资源
├── index.php     主入口（所有请求通过 PATH_INFO 路由）
├── api.php       API 入口
├── js/           全局 JavaScript
└── theme/        主题资源

config/           配置文件
├── config.php    主配置（不要直接修改，覆盖到 my.php）
└── my.php        环境特定配置（不要提交到 Git）

db/               SQL schema 和升级脚本
extension/        扩展和定制入口（升级时保留）
lib/              依赖库（phpmailer、pclzip 等）
```

### 请求流程

1. 请求通过 `www/index.php` 入口
2. 框架 router 解析 PATH_INFO 获取模块和方法名
3. 实例化对应模块的 `control.php`，调用指定方法
4. 控制器通过 `$this->loadModel('module')` 加载其他模块 model
5. 控制器调用 model 方法处理业务逻辑，设置视图变量
6. 渲染 `module/view/<action>.html.php` 模板输出

### URL 路由格式

```
/index.php/<module>/<method>/<param1>/<param2>/...
```

示例：`/index.php/bug/browse/unclosed` → `bug` 模块的 `browse` 方法

## 代码约定

### 文件组织
- 模块名全小写（`bug`、`story`、`task`）
- 控制器类名与模块名相同，继承 `control` 基类
- Model 类名格式为 `<module>Model`，继承 `model` 基类
- 视图模板：`module/<name>/view/<action>.html.php`

### PHP 编码风格
- 4 空格缩进
- K&R 括号风格（`{` 在行尾）
- 方法名描述性：`getBugs()`、`buildSearchForm()`
- 循环变量可用单字母（`$i`、`$k`、`$v`），其他变量应有描述性命名
- 业务逻辑放在 model 中，不在视图里处理

### 控制器加载模式
```php
class bug extends control
{
    public function __construct($moduleName = '', $methodName = '')
    {
        parent::__construct($moduleName, $methodName);
        $this->loadModel('product');
        $this->loadModel('story');
    }
}
```

### Model 方法示例
```php
class bugModel extends model
{
    public function getByID($bugID)
    {
        return $this->dao->select('*')->from(TABLE_BUG)->where('id')->eq($bugID)->fetch();
    }
}
```

## 配置管理

### 主配置文件
`config/config.php` 包含默认配置，**不要直接修改**。

### 环境特定配置
在 `config/my.php` 中覆盖配置项：
```php
<?php
$config->db->host = '127.0.0.1';
$config->db->port = '3306';
$config->db->name = 'zentao';
$config->db->user = 'root';
$config->db->password = '';
```

`config/my.php` 和 `config/db.php` 不要提交到 Git（已在 gitignore.txt 中）。

### 重要配置项
- `$config->requestType`：路由模式（PATH_INFO / GET）
- `$config->db`：数据库连接配置
- `$config->webRoot`：URL 根路径
- `$config->timezone`：时区（默认 Asia/Shanghai）

## 扩展开发

### 扩展点
`extension/custom/` 目录用于定制开发，升级时保留。

### 扩展模式
- 在 `extension/custom/` 下创建模块或覆盖现有模块
- 使用 hook 机制拦截核心逻辑
- 保持扩展代码与核心代码分离，降低升级风险

## PHP 8 兼容性

项目已适配 PHP 8：
- 字符串偏移语法：`$var{0}` → `$var[0]`
- 自动加载：已迁移到 `spl_autoload_register`
- 动态调用：`call_user_func_array()` 已调整以避免命名参数不兼容

升级 PHP 版本后，清除 opcode 缓存（如 OPcache）再进行回归测试。

## 测试和验证

项目没有 PHPUnit 测试套件。验证方式：

1. **Web UI 测试**：在浏览器中访问对应功能
2. **CLI 验证**：使用 `bin/ztcli` 执行模块操作
3. **数据库检查**：运行 `admin->checkdb` 验证一致性
4. **提交说明**：在 PR 中记录测试过的模块和场景

## 安全注意事项

- 不要提交 `config/my.php` 或包含密钥的文件
- `tmp/` 和 `www/data/` 是运行时数据，不提交到 Git
- 生产环境部署前检查 `config/`、`tmp/`、上传目录的文件权限
- 数据库相关改动后运行 `checkdb` 验证

## 开发节奏建议

### 改动前
1. 确认影响的模块（`module/<name>/`）
2. 理解现有 model 中的数据访问模式
3. 检查是否有扩展点可用（`extension/`）

### 改动中
- 优先在 `extension/custom/` 中实现新功能，减少升级风险
- 如果必须修改核心模块，保持改动最小化
- 遵循现有代码风格和命名约定

### 改动后
1. 本地服务器验证功能
2. 运行 `admin->checkdb` 检查数据库一致性
3. 测试相关场景的回归
