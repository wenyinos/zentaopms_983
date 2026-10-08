# wenyinos 统一认证 · 禅道接入扩展

将禅道接入 [玟茵开源社区统一认证中心](https://wenyinos.com/auth)：登录 / 退出由认证中心统一处理，已登录主站后访问禅道自动通行；未开通禅道的账号访问任意页面定向回中心（中心退出后游客可只读浏览）；禅道内改密/改资料自动同步回中心。

## 文件结构

```
module/user/ext/
├── config/wyauth.php          # 配置（含总开关）
├── model/wyauth.php           # 中心 API 客户端 / 票据兑换 / 反向同步
├── model/hook/identify.php    # 登录路径注入（API 等旁路兜底）
└── control/logout.php         # 退出覆盖（原生逻辑 + 销毁中心票据）
module/common/ext/model/hook/checkPriv.php   # 登录页拦截 + 票据兑换（每请求）
```

## 环境配置（.env）

密钥与地址等敏感值通过**同目录 `.env` 文件**配置（模板：`.env.example`）。`.env` 已被 gitignore，**本扩展代码可全量开源**。

```bash
cp .env.example .env   # 然后填写实际值
```

| 变量 | 说明 | 示例（生产） |
|---|---|---|
| `WY_SSO_ENABLED` | **总开关**：`true` 启用 / `false` 停用 | `true` |
| `WY_SSO_API_URL` | 中心 API 地址 | `https://wenyinos.com/auth/api.php` |
| `WY_SSO_APP_ID` | 中心「应用密钥」页签发 | `dev` |
| `WY_SSO_SECRET` | 与中心配对，保密 | `<64位hex>` |
| `WY_SSO_TIMEOUT` | API 超时（秒），超时视为中心不可达 | `3` |
| `WY_SSO_LOGIN_URL` | 登录跳转目标 | `https://wenyinos.com/auth/login.php` |
| `WY_SSO_LOGOUT_URL` | 退出跳转目标 | `https://wenyinos.com/auth/logout.php` |

**总开关用法**：把 `WY_SSO_ENABLED` 改为 `false` 即停用——登录页/退出**立即恢复禅道原生**行为，用于中心故障或需要临时脱离统一认证；改为 `true` 恢复。修改即生效（无需重启、无需清缓存）。

## 部署前提

1. `config/my.php` 必须开启扩展机制：`$config->framework->extensionLevel = 1;`
2. PHP 需 curl 扩展（或允许 allow_url_fopen）
3. 网络可达中心 API 地址

## 注意事项

- 修改扩展文件后，禅道 `tmp/model/` 合并缓存会按文件时间自动重建，无需手动清理
- 中心不可达时：已登录用户不受影响；新登录降级为本地密码验证
- `control/logout.php` 为「原生逻辑 + 增强」实现，`enabled=false` 时行为与原生完全一致
