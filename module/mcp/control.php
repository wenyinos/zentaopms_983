<?php
class mcp extends control
{
    public function __construct()
    {
        parent::__construct();
        $mcp = $this->loadModel('mcp');
        if(!$mcp->checkGuard())         die(json_encode(array('code' => 403, 'msg' => 'IP_DENIED')));
        if(!$mcp->checkToken())         die(json_encode(array('code' => 401, 'msg' => 'INVALID_TOKEN')));
        if(!$mcp->checkTimestamp())     die(json_encode(array('code' => 401, 'msg' => 'TIMESTAMP_EXPIRED')));
    }

    private function deny($msg)
    {
        die(json_encode(array('code' => 403, 'msg' => $msg)));
    }

    private function ok($data = null)
    {
        die(json_encode(array('code' => 0, 'data' => $data, 'time' => date('Y-m-d H:i:s'))));
    }

    private function writeGuard()
    {
        if($this->loadModel('mcp')->isReadonly()) $this->deny('READONLY_MODE');
    }

    private function actor()
    {
        $mcp = $this->loadModel('mcp');
        if(!$mcp->setActor((string)$mcp->getQueryParam('actor'))) $this->deny('MCP_ACCOUNT invalid');
        return $mcp;
    }

    private function requireTaskID($mcp)
    {
        $taskID = (int)$mcp->getQueryParam('taskID');
        if(!$taskID) $this->deny('taskID required');
        return $taskID;
    }

    private function onlyPost($fields)
    {
        $_POST = array_intersect_key($_POST, array_flip($fields));
    }

    /* ============ 基础 ============ */

    public function ping()
    {
        $this->ok('pong');
    }

    /* ============ 任务查询 ============ */

    public function getTask()
    {
        $mcp    = $this->loadModel('mcp');
        $taskID = (int)$mcp->getQueryParam('taskID');
        $task   = $taskID ? $mcp->getTask($taskID) : null;
        if($task === null) $this->deny('TASK_NOT_FOUND');
        $this->ok($task);
    }

    public function listTasks()
    {
        $mcp     = $this->loadModel('mcp');
        $account = (string)$mcp->getQueryParam('account');
        $status  = (string)$mcp->getQueryParam('status');
        $this->ok($mcp->listTasks($account, $status));
    }

    public function searchTasks()
    {
        $mcp     = $this->loadModel('mcp');
        $keyword = trim((string)$mcp->getQueryParam('keyword'));
        if($keyword === '') $this->deny('keyword required');
        $this->ok($mcp->searchTasks($keyword));
    }

    public function getTaskHistory()
    {
        $mcp    = $this->loadModel('mcp');
        $taskID = $this->requireTaskID($mcp);
        $this->ok($mcp->getTaskHistory($taskID));
    }

    public function getTaskEstimates()
    {
        $mcp    = $this->loadModel('mcp');
        $taskID = $this->requireTaskID($mcp);
        $this->ok($mcp->getTaskEstimates($taskID));
    }

    public function effortReport()
    {
        $mcp      = $this->loadModel('mcp');
        $account  = trim((string)$mcp->getQueryParam('account'));
        $dateFrom = trim((string)$mcp->getQueryParam('dateFrom'));
        $dateTo   = trim((string)$mcp->getQueryParam('dateTo'));
        if($dateFrom === '') $dateFrom = date('Y-m-d');
        if($dateTo === '')   $dateTo   = $dateFrom;
        $this->ok($mcp->effortReport($account, $dateFrom, $dateTo));
    }

    /* ============ 任务写操作 ============ */

    public function createTask()
    {
        $this->writeGuard();
        $mcp       = $this->actor();
        $projectID = (int)$mcp->getQueryParam('projectID');
        if(!$projectID) $this->deny('projectID required');

        $this->onlyPost(array('name', 'type', 'estimate', 'left', 'estStarted', 'deadline', 'desc', 'pri', 'story', 'module', 'comment'));
        $name = trim((string)$this->post->name);
        if($name === '') $this->deny('name required');

        $comment = (string)$this->post->comment;
        unset($_POST['comment']);
        if(empty($_POST['type'])) $_POST['type'] = 'devel';

        $assignedTo = trim((string)$this->post->assignedTo);
        if($assignedTo === '') $assignedTo = $this->app->user->account;
        $_POST['assignedTo'] = array($assignedTo);

        $taskID = $mcp->createTask($projectID);
        if(!$taskID) $this->deny('CREATE_FAILED: ' . implode('; ', dao::getError()));

        $this->loadModel('action')->create('task', $taskID, 'Opened', $comment);
        $mcp->auditLog('createTask', "task#$taskID name=$name");
        $this->ok(array('taskID' => $taskID));
    }

    public function startTask()
    {
        $this->writeGuard();
        $mcp    = $this->actor();
        $taskID = $this->requireTaskID($mcp);

        $this->onlyPost(array('consumed', 'left', 'realStarted', 'comment'));
        $changes = $this->loadModel('task')->start($taskID);
        if(dao::isError()) $this->deny(implode('; ', dao::getError()));

        $act      = $this->post->left == 0 ? 'Finished' : 'Started';
        $actionID = $this->loadModel('action')->create('task', $taskID, $act, (string)$this->post->comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('startTask', "task#$taskID actor=" . $this->app->user->account);
        $this->ok($changes ? 'started' : 'nochange');
    }

    public function finishTask()
    {
        $this->writeGuard();
        $mcp    = $this->actor();
        $taskID = $this->requireTaskID($mcp);

        $this->onlyPost(array('consumed', 'comment'));
        $changes = $this->loadModel('task')->finish($taskID);
        if(dao::isError()) $this->deny(implode('; ', dao::getError()));

        $actionID = $this->loadModel('action')->create('task', $taskID, 'Finished', (string)$this->post->comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('finishTask', "task#$taskID actor=" . $this->app->user->account);
        $this->ok($changes ? 'finished' : 'nochange');
    }

    public function recordEffort()
    {
        $this->writeGuard();
        $mcp    = $this->actor();
        $taskID = $this->requireTaskID($mcp);

        $date     = trim((string)$this->post->date);
        $consumed = (float)$this->post->consumed;
        $left     = $this->post->left;
        $work     = trim((string)$this->post->work);

        $_POST = array(
            'id'       => array(0),
            'dates'    => array($date !== '' ? $date : date('Y-m-d')),
            'consumed' => array($consumed),
            'left'     => array($left === false ? 0 : $left),
            'work'     => array($work),
        );
        $this->loadModel('task')->recordEstimate($taskID);
        $mcp->auditLog('recordEffort', "task#$taskID consumed=$consumed");
        $this->ok('recorded');
    }

    public function addComment()
    {
        $this->writeGuard();
        $mcp    = $this->actor();
        $taskID = $this->requireTaskID($mcp);

        $comment  = trim((string)$this->post->comment);
        $actionID = $this->loadModel('action')->create('task', $taskID, 'commented', $comment);
        $mcp->auditLog('addComment', "task#$taskID");
        $this->ok($actionID ? 'commented' : 'EMPTY_COMMENT');
    }

    public function assignTask()
    {
        $this->writeGuard();
        $mcp    = $this->actor();
        $taskID = $this->requireTaskID($mcp);

        $this->onlyPost(array('assignedTo', 'comment'));
        if(trim((string)$this->post->assignedTo) === '') $this->deny('assignedTo required');

        $changes = $this->loadModel('task')->assign($taskID);
        if(dao::isError()) $this->deny(implode('; ', dao::getError()));

        $actionID = $this->loadModel('action')->create('task', $taskID, 'Assigned', (string)$this->post->comment, (string)$this->post->assignedTo);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('assignTask', "task#$taskID to " . $this->post->assignedTo);
        $this->ok($changes ? 'assigned' : 'nochange');
    }

    public function flowTask()
    {
        $this->writeGuard();
        $mcp    = $this->actor();
        $taskID = $this->requireTaskID($mcp);
        $action = (string)$mcp->getQueryParam('action');
        if(!in_array($action, array('pause', 'restart', 'close', 'activate', 'cancel'))) $this->deny('action invalid');

        $this->onlyPost(array('left', 'assignedTo', 'comment'));
        if($action === 'activate' and trim((string)$this->post->assignedTo) === '')
        {
            $task = $this->loadModel('mcp')->getTask($taskID);
            if($task and !empty($task->openedBy)) $_POST['assignedTo'] = $task->openedBy;
        }
        $changes = $mcp->flowTask($taskID, $action);
        if(dao::isError()) $this->deny(implode('; ', dao::getError()));

        $actMap   = array('pause' => 'Paused', 'restart' => 'Restarted', 'close' => 'Closed', 'activate' => 'Activated', 'cancel' => 'Canceled');
        $actionID = $this->loadModel('action')->create('task', $taskID, $actMap[$action], (string)$this->post->comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('flowTask', "task#$taskID $action");
        $this->ok($changes ? $action : 'nochange');
    }

    /* ============ 需求 ============ */

    public function getStory()
    {
        $mcp     = $this->loadModel('mcp');
        $storyID = (int)$mcp->getQueryParam('storyID');
        $story   = $storyID ? $mcp->getStory($storyID) : null;
        if($story === null) $this->deny('STORY_NOT_FOUND');
        $this->ok($story);
    }

    public function listStories()
    {
        $mcp       = $this->loadModel('mcp');
        $productID = (int)$mcp->getQueryParam('productID');
        $status    = trim((string)$mcp->getQueryParam('status'));
        $this->ok($mcp->listStories($productID, $status));
    }

    /* ============ Bug ============ */

    public function getBug()
    {
        $mcp   = $this->loadModel('mcp');
        $bugID = (int)$mcp->getQueryParam('bugID');
        $bug   = $bugID ? $mcp->getBug($bugID) : null;
        if($bug === null) $this->deny('BUG_NOT_FOUND');
        $this->ok($bug);
    }

    public function listBugs()
    {
        $mcp       = $this->loadModel('mcp');
        $productID = (int)$mcp->getQueryParam('productID');
        $status    = trim((string)$mcp->getQueryParam('status'));
        $this->ok($mcp->listBugs($productID, $status));
    }

    public function createBug()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('title', 'openedBuild', 'module', 'product', 'severity', 'pri', 'type', 'steps', 'assignedTo', 'project', 'story', 'keywords'));
        if(trim((string)$this->post->title) === '')  $this->deny('title required');
        if(trim((string)$this->post->product) === '') $this->deny('product required');
        if(trim((string)$this->post->openedBuild) === '') $_POST['openedBuild'] = 'trunk';
        if(trim((string)$this->post->module) === '') unset($_POST['module']);

        $result = $mcp->createBug();
        if(is_array($result) and isset($result['status']) and $result['status'] === 'exists')
        {
            $this->ok(array('bugID' => (int)$result['id'], 'note' => 'duplicate exists'));
        }
        if(!is_array($result) or empty($result['id'])) $this->deny('CREATE_FAILED: ' . implode('; ', dao::getError()));

        $bugID = (int)$result['id'];
        $this->loadModel('action')->create('bug', $bugID, 'Opened', (string)$this->post->steps);
        $mcp->auditLog('createBug', "bug#$bugID");
        $this->ok(array('bugID' => $bugID));
    }

    public function resolveBug()
    {
        $this->writeGuard();
        $mcp   = $this->actor();
        $bugID = (int)$mcp->getQueryParam('bugID');
        if(!$bugID) $this->deny('bugID required');

        $this->onlyPost(array('resolution', 'resolvedBuild', 'comment', 'duplicateBug'));
        if(trim((string)$this->post->resolution) === '') $this->deny('resolution required');

        $mcp->resolveBug($bugID);
        if(dao::isError()) $this->deny(implode('; ', dao::getError()));

        $this->loadModel('action')->create('bug', $bugID, 'Resolved', (string)$this->post->comment, (string)$this->post->resolution);
        $mcp->auditLog('resolveBug', "bug#$bugID resolution=" . $this->post->resolution);
        $this->ok('resolved');
    }

    public function closeBug()
    {
        $this->writeGuard();
        $mcp   = $this->actor();
        $bugID = (int)$mcp->getQueryParam('bugID');
        if(!$bugID) $this->deny('bugID required');

        $this->onlyPost(array('comment'));
        $mcp->closeBug($bugID);
        if(dao::isError()) $this->deny(implode('; ', dao::getError()));

        $this->loadModel('action')->create('bug', $bugID, 'Closed', (string)$this->post->comment);
        $mcp->auditLog('closeBug', "bug#$bugID");
        $this->ok('closed');
    }

    /* ============ 项目与产品 ============ */

    public function listProjects()
    {
        $this->ok($this->loadModel('mcp')->listProjects());
    }

    public function listProducts()
    {
        $this->ok($this->loadModel('mcp')->listProducts());
    }

    /* ============ 提交同步 ============ */

    public function syncCommits()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $commits  = json_decode((string)$this->post->commits, true);
        if(!is_array($commits)) $this->deny('commits must be a JSON array');
        $repoRoot = (string)$this->post->repoRoot;

        $result = $mcp->syncCommits($commits, $repoRoot);
        $mcp->auditLog('syncCommits', 'count=' . count($result));
        $this->ok($result);
    }
}
