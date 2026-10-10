<?php
// zentao-mcp 配置：读取同目录 .env（不入库），缺失时回退到默认值
function ztConfig()
{
    $envFile = dirname(__FILE__) . '/.env';
    $env     = file_exists($envFile) ? parse_ini_file($envFile) : array();

    $authorMap = array();
    if(isset($env['GIT_AUTHOR_MAP']))
    {
        foreach(explode(',', $env['GIT_AUTHOR_MAP']) as $pair)
        {
            $pair = trim($pair);
            if($pair === '' or strpos($pair, ':') === false) continue;
            list($key, $account) = explode(':', $pair, 2);
            $authorMap[strtolower(trim($key))] = trim($account);
        }
    }

    return array(
        'url'       => isset($env['ZENTAO_URL']) ? rtrim($env['ZENTAO_URL'], '/') : 'http://dev.wenyinos.test:8080',
        'secret'    => isset($env['MCP_SECRET']) ? (string)$env['MCP_SECRET'] : '',
        'repo'      => isset($env['MCP_REPO_PATH']) ? (string)$env['MCP_REPO_PATH'] : getcwd(),
        'authorMap' => $authorMap,
    );
}
