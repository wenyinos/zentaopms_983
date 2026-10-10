<?php
// 禅道 mcp 端点 HTTP 客户端：HMAC 签名（签名覆盖全部 query 参数，含时效 ts）
function ztCall($method, $params = array(), $post = array())
{
    $config = ztConfig();
    if($config['secret'] === '') return array('code' => -1, 'msg' => 'MCP_SECRET is not configured in zentao-mcp/.env');

    $params['ts'] = time();
    $query = http_build_query($params);
    $sign  = md5(md5($query) . $config['secret']);
    $url   = $config['url'] . '/mcp-' . $method . '.json?';
    if($query !== '') $url .= $query . '&';
    $url .= 'token=' . $sign;

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POST           => !empty($post),
        CURLOPT_POSTFIELDS     => $post ? http_build_query($post) : null,
    ));
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if($body === false) return array('code' => -1, 'msg' => 'HTTP error: ' . $err);

    $data = json_decode($body, true);
    if(!is_array($data)) return array('code' => -1, 'msg' => 'Non-JSON response: ' . substr(strip_tags($body), 0, 300));
    return $data;
}
