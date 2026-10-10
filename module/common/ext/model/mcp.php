<?php
// mcp 模块放行：mcp 端点自鉴权（HMAC token），不经用户权限体系
public function isOpenMethod($module, $method)
{
    if(strtolower($module) === 'mcp') return true;
    return parent::isOpenMethod($module, $method);
}
