// wenyinos 统一认证（总开关：$config->user->wyauth['enabled']，停用时本文件所有逻辑不生效，全部恢复禅道原生行为）
// 与下方原生自动登录链（php_auth / za cookie）同构
// 注：不可用 empty() 判断 $this->cookie->xxx（super 魔术对象无 __isset，empty 恒真），与原生写法保持一致
$wyUser = $this->loadModel('user');
$wyCfg  = $this->config->user->wyauth;

if(!empty($wyCfg['enabled']))
{
	// 1) 登录页拦截：屏蔽禅道内置登录页，302 定向中心（保留原生 login 代码，仅前置跳转）
	if($this->app->getModuleName() === 'user' and $this->app->getMethodName() === 'login')
	{
		if($wyUser->isLogon()) die(header('location: ' . helper::createLink($this->config->default->module)));
		die(header('location: ' . $wyCfg['loginUrl']));
	}

	// 2) 个人资料修改拦截：改档案 / 改密码统一定向中心账号设置页（避免多端修改造成误解；本地专属字段暂不提供编辑入口）
	if($this->app->getModuleName() === 'my' and in_array($this->app->getMethodName(), array('editprofile', 'changepassword')))
	{
		die(header('location: ' . str_replace('login.php', 'profile.php', $wyCfg['loginUrl'])));
	}

	// 3) 登录态继承：未登录且持有中心票据时静默兑换
	//    中心业务拒绝（1004 未开通 / 1003 禁用 / 1001 不存在）→ 定向回中心面板查看提示；
	//    中心退出（票据清除/更换）后恢复游客浏览
	if($this->cookie->wy_auth and !$wyUser->isLogon())
	{
		if($wyUser->identifyByWyAuth() === 'denied')
		{
			die(header('location: ' . $wyCfg['loginUrl']));
		}
	}
}
