<?php
class mcpModel extends model
{
    public $authAccount = '';    // 本次请求通过校验的个人密钥归属账号（单通道：只认个人密钥）

    public function getEnv($key)
    {
        $envFile = dirname(__FILE__) . '/.env';
        if(!file_exists($envFile)) return '';
        $env = parse_ini_file($envFile);
        return isset($env[$key]) ? (string)$env[$key] : '';
    }

    public function checkToken()
    {
        parse_str($this->server->query_string, $query);
        $token = isset($query['token']) ? (string)$query['token'] : '';
        if($token === '') return false;
        unset($query['token']);

        $actor = isset($query['actor']) ? trim((string)$query['actor']) : '';

        /* 带 actor 的请求（写操作）：必须使用该账号的个人密钥签名。 */
        if($actor !== '')
        {
            $secret = $this->getUserSecret($actor);
            if($secret === '') return false;    /* 该账号尚未生成个人密钥 —— 无共享密钥可退，直接拒绝 */
            if(!$this->verifyToken($token, $query, $secret)) return false;
            $this->authAccount = $actor;
            return true;
        }

        /* 无 actor 的请求（读操作）：命中任一已生成的个人密钥才放行。 */
        $account = $this->matchPersonalToken($token, $query);
        if($account === '') return false;
        $this->authAccount = $account;
        return true;
    }

    public function verifyToken($token, $query, $secret)
    {
        return hash_equals(md5(md5(http_build_query($query)) . $secret), $token);
    }

    /* 无 actor 的请求逐一比对已生成的个人密钥，返回命中账号。 */
    public function matchPersonalToken($token, $query)
    {
        $base  = md5(http_build_query($query));
        $items = $this->loadModel('setting')->getItems('owner=system&module=mcp&section=keys');
        foreach($items as $item)
        {
            if(hash_equals(md5($base . $item->value), $token)) return $item->key;
        }
        return '';
    }

    public function getUserSecret($account)
    {
        if($account === '') return '';
        return (string)$this->loadModel('setting')->getItem('owner=system&module=mcp&section=keys&key=' . $account);
    }

    public function generateUserSecret($account)
    {
        if($account === '') return '';

        $secret = 'mcp-' . bin2hex(random_bytes(16));
        $item   = new stdclass();
        $item->owner   = 'system';
        $item->module  = 'mcp';
        $item->section = 'keys';
        $item->key     = $account;
        $item->value   = $secret;
        /* 账号可能含点号，绕过 setItem 的路径解析直接写 zt_config。 */
        $this->dao->replace(TABLE_CONFIG)->data($item)->exec();
        return dao::isError() ? '' : $secret;
    }

    public function getQueryParam($key)
    {
        parse_str($this->server->query_string, $query);
        return isset($query[$key]) ? $query[$key] : '';
    }

    public function checkTimestamp()
    {
        $ts = (int)$this->getQueryParam('ts');
        if($ts <= 0) return false;
        return abs(time() - $ts) <= 300;
    }

    public function checkGuard()
    {
        $ips = $this->getEnv('MCP_IPS');
        if($ips !== '')
        {
            $allow = array_filter(array_map('trim', explode(',', $ips)));
            if(!in_array((string)$this->server->remote_addr, $allow)) return false;
        }
        return true;
    }

    public function isReadonly()
    {
        return $this->getEnv('MCP_READONLY') === '1';
    }

    public function auditLog($action, $summary = '')
    {
        $dir = $this->app->getTmpRoot() . 'mcp';
        if(!is_dir($dir)) @mkdir($dir, 0755, true);
        $auth = $this->authAccount === '' ? 'none' : 'personal:' . $this->authAccount;
        $line = date('Y-m-d H:i:s') . "\t" . $this->server->remote_addr . "\t" . $action . "\t" . $auth . "\t" . trim($summary) . "\n";
        @file_put_contents($dir . '/access-' . date('Ymd') . '.log', $line, FILE_APPEND);
    }

    public function setActor($account = '')
    {
        if($account === '') return false;

        $user = $this->loadModel('user')->getById($account);
        if(empty($user->account)) return false;

        $this->app->user = $user;
        return true;
    }

    /* ============ 任务查询 ============ */

    public function getTask($taskID)
    {
        $task = $this->loadModel('task')->getById($taskID);
        if(empty($task)) return null;

        $fields = array('id', 'name', 'status', 'pri', 'assignedTo', 'openedBy', 'finishedBy', 'project', 'story', 'estimate', 'consumed', 'left', 'deadline', 'realStarted', 'finishedDate', 'desc', 'deleted');
        $data   = new stdclass();
        foreach($fields as $field) $data->$field = isset($task->$field) ? $task->$field : null;
        return $data;
    }

    public function listTasks($account = '', $status = '')
    {
        $query = $this->dao->select('id,name,status,assignedTo,project,estimate,consumed,`left`,deadline')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0');
        if($account) $query->andWhere('assignedTo')->eq($account);
        if($status)  $query->andWhere('status')->in($status);
        return $query->orderBy('id_desc')->limit(50)->fetchAll();
    }

    public function searchTasks($keyword)
    {
        if($keyword === '') return array();
        return $this->dao->select('id,name,status,assignedTo,project,estimate,consumed,`left`,deadline')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('name')->like('%' . $keyword . '%')
            ->orderBy('id_desc')->limit(50)->fetchAll();
    }

    public function getTaskHistory($taskID)
    {
        $actions = $this->loadModel('action')->getList('task', $taskID);
        $result  = array();
        foreach($actions as $action)
        {
            $item = new stdclass();
            $item->id      = $action->id;
            $item->actor   = $action->actor;
            $item->action  = $action->action;
            $item->date    = $action->date;
            $item->comment = $action->comment;
            $item->extra   = $action->extra;
            $result[] = $item;
        }
        return array_reverse($result);
    }

    public function getTaskEstimates($taskID)
    {
        return $this->dao->select('id,date,account,consumed,`left`,work')
            ->from(TABLE_TASKESTIMATE)
            ->where('task')->eq($taskID)
            ->orderBy('date_desc, id_desc')
            ->fetchAll();
    }

    public function effortReport($account, $dateFrom, $dateTo)
    {
        $query = $this->dao->select('e.id, e.task, e.date, e.account, e.consumed, e.`left`, e.work, t.name AS taskName')
            ->from(TABLE_TASKESTIMATE)->alias('e')
            ->leftJoin(TABLE_TASK)->alias('t')->on('e.task = t.id')
            ->where('e.date')->ge($dateFrom)
            ->andWhere('e.date')->le($dateTo);
        if($account) $query->andWhere('e.account')->eq($account);
        return $query->orderBy('e.date_desc, e.id_desc')->fetchAll();
    }

    /* ============ 任务写操作 ============ */

    public function createTask($projectID)
    {
        $result = $this->loadModel('task')->create($projectID);
        if(!is_array($result) or empty($result)) return 0;

        $first = reset($result);
        if(is_array($first) and !empty($first['id'])) return (int)$first['id'];
        return 0;
    }

    public function assignTask($taskID)
    {
        return $this->loadModel('task')->assign($taskID);
    }

    public function flowTask($taskID, $action)
    {
        $taskModel = $this->loadModel('task');
        if($action === 'pause')    return $taskModel->pause($taskID);
        if($action === 'restart')  return $taskModel->start($taskID);
        if($action === 'close')    return $taskModel->close($taskID);
        if($action === 'activate') return $taskModel->activate($taskID);
        if($action === 'cancel')   return $taskModel->cancel($taskID);
        return false;
    }

    /* ============ 需求 ============ */

    public function getStory($storyID)
    {
        $story = $this->loadModel('story')->getById($storyID);
        if(empty($story)) return null;

        $fields = array('id', 'title', 'status', 'stage', 'pri', 'estimate', 'product', 'openedBy', 'assignedTo', 'version', 'closedReason', 'spec', 'verify', 'deleted');
        $data   = new stdclass();
        foreach($fields as $field) $data->$field = isset($story->$field) ? $story->$field : null;
        return $data;
    }

    public function listStories($productID, $status)
    {
        $query = $this->dao->select('id,title,status,stage,pri,estimate,openedBy,assignedTo')
            ->from(TABLE_STORY)
            ->where('deleted')->eq('0');
        if($productID) $query->andWhere('product')->eq((int)$productID);
        if($status)    $query->andWhere('status')->in($status);
        return $query->orderBy('id_desc')->limit(50)->fetchAll();
    }

    /* ============ Bug ============ */

    public function getBug($bugID)
    {
        $bug = $this->loadModel('bug')->getById($bugID);
        if(empty($bug)) return null;

        $fields = array('id', 'title', 'status', 'severity', 'pri', 'type', 'product', 'openedBy', 'assignedTo', 'resolvedBy', 'resolution', 'openedBuild', 'resolvedBuild', 'closedBy', 'steps', 'deleted');
        $data   = new stdclass();
        foreach($fields as $field) $data->$field = isset($bug->$field) ? $bug->$field : null;
        return $data;
    }

    public function listBugs($productID, $status)
    {
        $query = $this->dao->select('id,title,status,severity,pri,type,openedBy,assignedTo,resolution')
            ->from(TABLE_BUG)
            ->where('deleted')->eq('0');
        if($productID) $query->andWhere('product')->eq((int)$productID);
        if($status)    $query->andWhere('status')->in($status);
        return $query->orderBy('id_desc')->limit(50)->fetchAll();
    }

    public function createBug()
    {
        $result = $this->loadModel('bug')->create();
        if(!is_array($result) or !isset($result['id'])) return $result;
        return $result;
    }

    public function resolveBug($bugID)
    {
        return $this->loadModel('bug')->resolve($bugID);
    }

    public function closeBug($bugID)
    {
        return $this->loadModel('bug')->close($bugID);
    }

    /* ============ 项目与产品 ============ */

    public function listProjects()
    {
        return $this->dao->select('id,name,code,status,begin,end')
            ->from(TABLE_PROJECT)
            ->where('deleted')->eq('0')
            ->orderBy('id_desc')->limit(100)->fetchAll();
    }

    public function listProducts()
    {
        return $this->dao->select('id,name,code,status,PO')
            ->from(TABLE_PRODUCT)
            ->where('deleted')->eq('0')
            ->orderBy('id_desc')->limit(100)->fetchAll();
    }

    /* ============ 项目 / 文档 ============ */

    public function projectFlow($projectID, $action)
    {
        $projectModel = $this->loadModel('project');
        if($action === 'start')    return $projectModel->start($projectID);
        if($action === 'suspend')  return $projectModel->suspend($projectID);
        if($action === 'activate') return $projectModel->activate($projectID);
        if($action === 'close')    return $projectModel->close($projectID);
        return false;
    }

    public function getMainDoclib($product = 0, $project = 0)
    {
        $query = $this->dao->select('id')->from(TABLE_DOCLIB)->where('main')->eq('1')->andWhere('deleted')->eq('0');
        if($product) $query->andWhere('product')->eq((int)$product);
        if($project) $query->andWhere('project')->eq((int)$project);
        $lib = $query->fetch();
        return $lib ? (int)$lib->id : 0;
    }

    /* ============ 提交同步 ============ */

    public function syncStateKey($repoRoot)
    {
        return 'repo_' . substr(md5($repoRoot), 0, 25); // zt_config.key 为 char(30)：5 + 25 恰好占满
    }

    public function getSyncState($repoRoot)
    {
        if($repoRoot === '') return '';
        return (string)$this->loadModel('setting')->getItem('owner=system&module=mcp&section=sync&key=' . $this->syncStateKey($repoRoot));
    }

    public function advanceSyncState($repoRoot, $hash)
    {
        if($repoRoot === '' or $hash === '') return;
        $this->loadModel('setting')->setItem('system.mcp.sync.' . $this->syncStateKey($repoRoot), $hash);
    }

    public function buildLogLines($commit)
    {
        $msg   = str_replace('*_*', ' * * ', (string)$commit['msg']);
        $lines = array((string)$commit['name'] . '*_*' . (string)$commit['date'] . '*_*' . (string)$commit['hash'] . '*_*' . $msg);
        if(!empty($commit['files']) and is_array($commit['files']))
        {
            foreach($commit['files'] as $file)
            {
                $file = trim((string)$file);
                if($file === '') continue;
                $lines[] = $file . '|M';
            }
        }
        return $lines;
    }

    public function syncCommits($commits, $repoRoot)
    {
        $gitModel = $this->loadModel('git');
        $results  = array();
        $newest   = '';
        foreach($commits as $commit)
        {
            if(!is_array($commit) or empty($commit['hash'])) continue;

            $log     = $gitModel->convertLog($this->buildLogLines($commit));
            $objects = $gitModel->parseComment($log->msg);
            if($objects) $gitModel->saveAction2PMS($objects, $log, $repoRoot);

            if($newest === '') $newest = (string)$commit['hash'];
            $results[] = array(
                'revision' => substr($log->revision, 0, 10),
                'msg'      => $log->msg,
                'objects'  => $objects ? $objects : array(),
            );
        }
        if($newest !== '') $this->advanceSyncState($repoRoot, $newest);
        return $results;
    }
}
