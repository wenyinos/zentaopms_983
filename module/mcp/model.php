<?php
class mcpModel extends model
{
    public function getEnv($key)
    {
        $envFile = dirname(__FILE__) . '/.env';
        if(!file_exists($envFile)) return '';
        $env = parse_ini_file($envFile);
        return isset($env[$key]) ? (string)$env[$key] : '';
    }

    public function checkToken()
    {
        $secret = $this->getEnv('MCP_SECRET');
        if($secret === '') return false;

        parse_str($this->server->query_string, $query);
        $token = isset($query['token']) ? (string)$query['token'] : '';
        unset($query['token']);
        $sign = md5(md5(http_build_query($query)) . $secret);
        return $token !== '' && hash_equals($sign, $token);
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
        $line = date('Y-m-d H:i:s') . "\t" . $this->server->remote_addr . "\t" . $action . "\t" . trim($summary) . "\n";
        @file_put_contents($dir . '/access-' . date('Ymd') . '.log', $line, FILE_APPEND);
    }

    public function setActor($account = '')
    {
        if($account === '') $account = $this->getEnv('MCP_ACCOUNT');
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

        $fields = array('id', 'name', 'status', 'pri', 'assignedTo', 'openedBy', 'finishedBy', 'project', 'story', 'estimate', 'consumed', 'left', 'deadline', 'realStarted', 'finishedDate');
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

        $fields = array('id', 'title', 'status', 'stage', 'pri', 'estimate', 'product', 'openedBy', 'assignedTo', 'version', 'closedReason');
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

        $fields = array('id', 'title', 'status', 'severity', 'pri', 'type', 'product', 'openedBy', 'assignedTo', 'resolvedBy', 'resolution', 'openedBuild', 'resolvedBuild', 'closedBy');
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

    /* ============ 提交同步 ============ */

    public function syncCommits($logs, $repoRoot)
    {
        $gitModel = $this->loadModel('git');
        $results  = array();
        foreach($logs as $logLines)
        {
            if(empty($logLines) || !is_array($logLines)) continue;

            $log     = $gitModel->convertLog($logLines);
            $objects = $gitModel->parseComment($log->msg);
            if($objects) $gitModel->saveAction2PMS($objects, $log, $repoRoot);

            $results[] = array(
                'revision' => substr($log->revision, 0, 10),
                'msg'      => $log->msg,
                'objects'  => $objects ? $objects : array(),
            );
        }
        return $results;
    }
}
