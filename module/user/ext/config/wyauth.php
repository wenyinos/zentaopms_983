<?php
// wenyinos 统一认证中心接入配置
// 敏感值（密钥、中心地址）从同目录 .env 读取；模板见 .env.example（.env 已被 gitignore，代码可全量开源）
// 注意：ext config 由 loadModuleConfig() 方法内 include，须显式声明 global
global $config, $filter;

if(!function_exists('wy_zentao_env'))
{
	function wy_zentao_env($key, $default = NULL)
	{
		static $env = NULL;
		if($env === NULL)
		{
			$env = array();
			$file = dirname(__DIR__) . '/.env';   // module/user/ext/.env
			if(is_file($file))
			{
				foreach(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line)
				{
					$line = trim($line);
					if($line === '' || $line[0] === '#') continue;
					$pos = strpos($line, '=');
					if($pos === FALSE) continue;
					$k = trim(substr($line, 0, $pos));
					$v = trim(substr($line, $pos + 1));
					if(strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
					$env[$k] = $v;
				}
			}
		}
		return array_key_exists($key, $env) ? $env[$key] : $default;
	}
}

// 总开关：WY_SSO_ENABLED=true 启用 / false 停用（停用后禅道恢复原生登录/退出）
// 密钥与地址由认证中心管理后台的「应用密钥」页提供；生产示例见 .env.example
$config->user->wyauth = array(
	'enabled'   => wy_zentao_env('WY_SSO_ENABLED', 'true') !== 'false',
	'apiUrl'    => wy_zentao_env('WY_SSO_API_URL', 'http://127.0.0.1/auth/api.php'),
	'appId'     => wy_zentao_env('WY_SSO_APP_ID', 'dev'),
	'secret'    => wy_zentao_env('WY_SSO_SECRET', ''),
	'timeout'   => intval(wy_zentao_env('WY_SSO_TIMEOUT', 3)),
	'loginUrl'  => wy_zentao_env('WY_SSO_LOGIN_URL', 'http://auth.wenyinos.test:8080/auth/login.php'),
	'logoutUrl' => wy_zentao_env('WY_SSO_LOGOUT_URL', 'http://auth.wenyinos.test:8080/auth/logout.php'),
);

// wy_auth 票据 cookie 注册进禅道 cookie 过滤白名单
// （否则 loadModule 阶段 validater::filterParam 会把不在规则表中的 cookie 从 $_COOKIE 删除）
$filter->rules->wyticket = '/^[a-zA-Z0-9\-_\.]+$/';
$filter->default->cookie['wy_auth'] = 'reg::wyticket';
