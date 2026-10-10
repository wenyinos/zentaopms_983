<?php
// 读取本地 git 提交：返回结构化数组（含服务端 syncCommits 所需的 lines 与作者信息）
function ztGitCommits($repoPath, $sinceHash = '', $limit = 20)
{
    // since 指向的提交不存在（如历史被重置）时回退到 limit 模式
    if($sinceHash !== '')
    {
        $exists = shell_exec('git -C ' . escapeshellarg($repoPath) . ' cat-file -e ' . escapeshellarg($sinceHash . '^{commit}') . ' 2>/dev/null && echo ok');
        if(trim((string)$exists) !== 'ok') $sinceHash = '';
    }

    $range = $sinceHash !== '' ? escapeshellarg($sinceHash . '..HEAD') : '';
    $cmd   = 'git -C ' . escapeshellarg($repoPath) . ' log --pretty=format:' . escapeshellarg('%x01%an*_*%ad*_*%H*_*%s*_*%ae') . ' --date=iso --name-only ' . $range;
    if($range === '') $cmd .= ' --max-count=' . (int)$limit;

    $output = shell_exec($cmd . ' 2>/dev/null');
    if(!$output) return array();

    $commits = array();
    foreach(explode("\x01", $output) as $block)
    {
        $block = trim($block);
        if($block === '') continue;

        $lines  = explode("\n", $block);
        $header = array_shift($lines);

        $parts = explode('*_*', $header);
        $email = (count($parts) > 4 && strpos(end($parts), '@') !== false) ? array_pop($parts) : '';
        $name  = array_shift($parts);
        $date  = array_shift($parts);
        $hash  = array_shift($parts);
        $msg   = str_replace('*_*', ' * * ', join('*_*', $parts));

        $logLines = array($name . '*_*' . $date . '*_*' . $hash . '*_*' . $msg);
        $files    = array();
        foreach($lines as $file)
        {
            $file = trim($file);
            if($file === '') continue;
            $logLines[] = $file . '|M';
            $files[]    = $file;
        }

        $commits[] = array(
            'lines'  => $logLines,
            'name'   => $name,
            'email'  => $email,
            'hash'   => $hash,
            'date'   => $date,
            'msg'    => $msg,
            'files'  => $files,
        );
    }
    return $commits;
}

// git 作者 -> 禅道账号映射（先按 email 后按 name 匹配，均忽略大小写）
function ztAuthorAccount($name, $email)
{
    $map = ztConfig();
    $map = $map['authorMap'];
    if(empty($map)) return '';

    $emailKey = strtolower(trim((string)$email));
    $nameKey  = strtolower(trim((string)$name));
    if($emailKey !== '' && isset($map[$emailKey])) return $map[$emailKey];
    if($nameKey !== '' && isset($map[$nameKey]))   return $map[$nameKey];
    return '';
}

function ztStateFile($repoPath)
{
    return dirname(__FILE__) . '/.state-' . md5($repoPath) . '.json';
}

function ztReadState($repoPath)
{
    $stateFile = ztStateFile($repoPath);
    if(!file_exists($stateFile)) return '';
    $state = json_decode(file_get_contents($stateFile), true);
    return isset($state['lastHash']) ? (string)$state['lastHash'] : '';
}

function ztWriteState($repoPath, $hash)
{
    file_put_contents(ztStateFile($repoPath), json_encode(array('lastHash' => $hash)));
}
