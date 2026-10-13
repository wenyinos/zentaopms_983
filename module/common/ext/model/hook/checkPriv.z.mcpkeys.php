<?php
/**
 * 权限放行：MCP密钥自助页面对所有登录用户开放（个人凭据自助管理，不进可配置权限体系）。
 * 本 hook 合并进 commonModel::checkPriv()，仅注入到本次请求的用户权利数组，不落库。
 *
 * 文件名含 ".z."：框架按 glob 字典序加载 hook，checkPriv.php（统一认证票据兑换）须先执行，
 * 本 hook 才能读到兑换后已登录的 $app->user（SSO 首次兑换直达本页的场景）。
 *
 * guest（未登录）不注入，仍由禅道原生逻辑引导到登录页。
 */
if(isset($this->app->user) and !empty($this->app->user->account) and $this->app->user->account != 'guest')
{
    if(!isset($this->app->user->rights['rights']['my']['mcpkeys'])) $this->app->user->rights['rights']['my']['mcpkeys'] = true;
}
