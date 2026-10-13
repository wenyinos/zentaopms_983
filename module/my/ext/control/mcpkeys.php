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

        if(isset($_POST['generate']))
        {
            $mcp->generateUserSecret($account);
            $this->locate($this->createLink('my', 'mcpkeys'));
        }

        $secret = $mcp->getUserSecret($account);

        $this->view->title           = $this->lang->my->mcpkeys;
        $this->view->account         = $account;
        $this->view->realname        = $this->app->user->realname;
        $this->view->secret          = $secret;
        $this->view->strict          = $mcp->isStrict();
        $this->view->hasSharedSecret = $mcp->getEnv('MCP_SECRET') !== '';
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
