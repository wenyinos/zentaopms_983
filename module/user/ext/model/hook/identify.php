<?php
// wenyinos 统一认证：登录路径注入（明文交互登录先走中心 verify + 同步）
// 注：identifyByCookie 的 32/40 位自动登录路径不满足 <32 条件，自动跳过中心
// 总开关：$config->user->wyauth['enabled']，停用时本注入不生效（原生登录完全恢复）
$wyCfg = $this->config->user->wyauth;
if(!empty($wyCfg['enabled']) and $account and $password and strlen($password) < 32 and strtolower((string)$account) != 'guest' and strpos((string)$account, '@') === false)
{
	$wyResult = $this->wyauthVerifyAndSync($account, md5($password));
	if($wyResult === 'deny') return false;   // 中心明确拒绝（密码错误/未授权/禁用/锁定）→ 直接失败
	// 'ok'：已同步密码与组，继续原逻辑自然命中；'fallback'：中心不可达，降级本地验证
}
