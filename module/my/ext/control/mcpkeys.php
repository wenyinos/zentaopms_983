<?php
/**
 * 我的地盘 · MCP 密钥自助页（扩展方法）。
 * 查看 / 生成 / 重置本账号的 MCP 个人密钥，并生成可直接粘贴到客户端 .env 的配置。
 * 密钥存于 zt_config（owner=system, module=mcp, section=keys），服务端校验逻辑见 module/mcp/model.php 的 checkToken()。
 */
class my extends control
{
    public function mcpkeys()
    {
        $this->loadModel('my')->setMenu();

        $account = $this->app->user->account;
        $mcp     = $this->loadModel('mcp');

        /* 按请求方法判定生成动作：禅道全局 JS（all.js 的 setForm）在提交瞬间会禁用 submit 按钮，
           被禁用按钮的 name/value 不会进入请求体，故不能以按钮字段是否存在来判断。 */
        if($this->server->request_method === 'POST')
        {
            if($mcp->generateUserSecret($account) === '') $this->view->error = $this->lang->my->mcpGenFail;
            else $this->locate($this->createLink('my', 'mcpkeys'));
        }

        $secret = $mcp->getUserSecret($account);

        $this->view->title           = $this->lang->my->mcpkeys;
        $this->view->account         = $account;
        $this->view->realname        = $this->app->user->realname;
        $this->view->secret          = $secret;
        $this->view->envText         = $this->buildEnvText($secret, $account);
        $this->view->position[]      = $this->lang->my->mcpkeys;
        $this->display();
    }

    private function buildEnvText($secret, $account)
    {
        $siteUrl = rtrim(commonModel::getSysURL() . $this->config->webRoot, '/');
        $email   = (string)$this->app->user->email;

        $lines = array();
        $lines[] = '# zentao-mcp 客户端配置（' . $account . '）';
        $lines[] = 'ZENTAO_URL=' . $siteUrl;
        $lines[] = 'MCP_SECRET=' . $secret;
        $lines[] = 'MCP_REPO_PATH=/path/to/your/repo';
        $lines[] = 'MCP_ACCOUNT=' . $account;
        $lines[] = 'GIT_AUTHOR_MAP=' . ($email !== '' ? $email . ':' . $account : 'you@example.com:' . $account);
        return implode("\n", $lines);
    }
}
