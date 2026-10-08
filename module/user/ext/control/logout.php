<?php
// wenyinos 统一认证：覆盖 user-logout（原生登出逻辑 + 启用时销毁中心票据并定向中心全域登出）
// 总开关：$config->user->wyauth['enabled']，停用时行为与原生完全一致（清会话/cookie + 回登录页）
class user extends control
{
    public function logout($referer = 0)
    {
        if(isset($this->app->user->id)) $this->loadModel('action')->create('user', $this->app->user->id, 'logout');

        $wyCfg = $this->config->user->wyauth;
        if(!empty($wyCfg['enabled'])) $this->loadModel('user')->wyauthRevoke();
        session_destroy();
        setcookie('za', false);
        setcookie('zp', false);

        if($this->app->getViewType() == 'json') die(json_encode(array('status' => 'success')));
        if(!empty($wyCfg['enabled'])) $this->locate($wyCfg['logoutUrl']);
        $vars = !empty($referer) ? "referer=$referer" : '';
        $this->locate($this->createLink('user', 'login', $vars));
    }
}
