# WenYinOS 禅道项目管理系统 9.8.3_1

[English](README.md) | 简体中文

## 项目简介
WenYinOS 禅道项目管理系统基于经典 ZenTao 单体架构，采用 PHP + MySQL，集产品、项目、测试、文档与组织管理于一体。

## 核心能力
- 产品管理：需求、计划、发布
- 项目管理：任务、团队、版本、迭代执行
- 测试管理：Bug、用例、测试单
- 文档与知识管理
- 用户、组织与日常协作支持

## 快速启动
### 1. 启动本地 Web 服务
```bash
php -S 127.0.0.1:8080 -t www
```

### 2. 初始化 CLI 辅助脚本（可选）
```bash
bash bin/init.sh /usr/bin/php http://127.0.0.1:8080
```

### 3. 通过 CLI 执行模块动作（示例）
```bash
php bin/ztcli 'http://127.0.0.1:8080/index.php?m=admin&f=checkdb'
```

## 目录结构
- `module/`：业务模块（如 `bug`、`story`、`task`、`project`）
- `framework/`：核心运行时、基类与路由
- `www/`：Web 入口与静态资源
- `config/`：运行配置
- `db/`：数据库结构与初始化资源
- `extension/`：扩展与定制入口
- `tmp/`：运行期缓存、日志、临时数据

## 配置说明
- 默认配置位于 `config/config.php`。
- 环境差异配置建议写入 `config/my.php`。
- 不要提交密钥或本地专用配置。

## Nginx 伪静态
在 Nginx 的 `location /` 中加入以下规则：

```nginx
if (!-d $request_filename){
set $rule_0 1$rule_0;
}
if (!-f $request_filename){
set $rule_0 2$rule_0;
}
if ($rule_0 = "21"){
rewrite /(.*)$ /index.php/$1 last;
}
```

## PHP8 升级详情
- 运行环境支持 PHP 8。
- 已将旧式字符串下标语法（`$var{0}`）迁移为方括号语法（`$var[0]`）。
- 已将旧式自动加载逻辑对齐为 `spl_autoload_register`。
- 已调整 `call_user_func_array()` 动态调用，避免 PHP 8 命名参数兼容问题。
- 已兼容 PHP 8 对动态对象属性赋值的严格行为。
- 升级 PHP 后建议先清理 OPcache 等缓存，再进行回归验证。

## 开发说明
- 默认流程不依赖 Node/Composer 构建管线。
- 自定义功能优先通过 `extension/` 增量实现。
- 变更后建议通过页面流程与 `ztcli` 双重验证。

## 安全与运维
- 将 `tmp/` 与运行期数据排除在版本控制之外。
- 部署时检查 `config/`、`tmp/` 与上传目录权限。
- 涉及数据库变更后执行一致性检查。

## 许可证
Z PUBLIC LICENSE 1.2（见 `LICENSE`）。
