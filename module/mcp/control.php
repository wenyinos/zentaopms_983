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

    private function daoFail($prefix = '')
    {
        $parts = array();
        foreach(dao::getError() as $msg)
        {
            if(is_array($msg)) $msg = join('；', $msg);
            $parts[] = $msg;
        }
        $this->deny($prefix . ($parts ? join('; ', $parts) : 'UNKNOWN_ERROR'));
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
        $mcp     = $this->loadModel('mcp');
        $account = trim((string)$mcp->getQueryParam('actor'));
        if($account === '') $this->deny('ACTOR_REQUIRED: configure MCP_ACCOUNT in client .env');
        if(!$mcp->setActor($account)) $this->deny('MCP_ACCOUNT invalid');
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

        $this->onlyPost(array('name', 'type', 'estimate', 'left', 'estStarted', 'deadline', 'desc', 'pri', 'story', 'module', 'assignedTo', 'parent', 'comment'));
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

        /* 子任务：刷新父任务聚合状态（原生单条创建路径无此逻辑，批量创建才有） */
        $parentID = (int)$this->post->parent;
        if($parentID) $this->loadModel('task')->updateParentStatus($parentID);

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
        if(dao::isError()) $this->daoFail();

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
        if(dao::isError()) $this->daoFail();

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
        if(dao::isError()) $this->daoFail();

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
        if(dao::isError()) $this->daoFail();

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
        if(dao::isError()) $this->daoFail();

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
        if(dao::isError()) $this->daoFail();

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

    /* ============ 项目 ============ */

    public function createProject()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('name', 'code', 'begin', 'end', 'team', 'days', 'type', 'acl', 'desc', 'whitelist'));
        if(trim((string)$this->post->name) === '')  $this->deny('name required');
        if(trim((string)$this->post->code) === '')  $this->deny('code required');
        if(trim((string)$this->post->begin) === '') $this->deny('begin required');
        if(trim((string)$this->post->end) === '')   $this->deny('end required');
        if(is_array($this->post->whitelist)) $_POST['whitelist'] = join(',', $this->post->whitelist);

        $projectID = $this->loadModel('project')->create();
        if(dao::isError()) $this->daoFail();
        if(!$projectID) $this->deny('CREATE_FAILED');

        $this->loadModel('action')->create('project', $projectID, 'opened');
        $mcp->auditLog('createProject', "project#$projectID name=" . $this->post->name);
        $this->ok(array('projectID' => (int)$projectID));
    }

    public function updateProject()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $projectID = (int)$mcp->getQueryParam('projectID');
        if(!$projectID) $this->deny('projectID required');

        $projectModel = $this->loadModel('project');
        $project      = $projectModel->getById($projectID);
        if(empty($project)) $this->deny('PROJECT_NOT_FOUND');

        $this->onlyPost(array('name', 'code', 'begin', 'end', 'days', 'type', 'team', 'desc', 'acl', 'whitelist'));
        $current = array('name' => $project->name, 'code' => $project->code, 'begin' => $project->begin, 'end' => $project->end);
        foreach($current as $key => $value)
        {
            if(!isset($_POST[$key]) or $_POST[$key] === '') $_POST[$key] = $value;
        }
        if(is_array($this->post->whitelist)) $_POST['whitelist'] = join(',', $this->post->whitelist);

        /* 回填现有产品关联：updateProducts 会先清空关联，不传 products 键即丢关联 */
        $_POST['products'] = array();
        $_POST['branch']   = array();
        $i = 0;
        foreach($projectModel->getProducts($projectID) as $row)
        {
            $_POST['products'][$i] = $row->id;
            $_POST['branch'][$i]   = $row->branch;
            if(!empty($row->plan)) $_POST['plans'][$row->id] = $row->plan;
            $i++;
        }

        $changes = $projectModel->update($projectID);
        $projectModel->updateProducts($projectID);
        if(dao::isError()) $this->daoFail();

        if(!empty($changes))
        {
            $actionID = $this->loadModel('action')->create('project', $projectID, 'edited');
            $this->loadModel('action')->logHistory($actionID, $changes);
        }
        $mcp->auditLog('updateProject', "project#$projectID");
        $this->ok($changes ? 'updated' : 'nochange');
    }

    public function projectFlow()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $projectID  = (int)$mcp->getQueryParam('projectID');
        if(!$projectID) $this->deny('projectID required');
        $flowAction = (string)$mcp->getQueryParam('action');
        if(!in_array($flowAction, array('start', 'suspend', 'activate', 'close'))) $this->deny('action invalid');

        $this->onlyPost(array('comment'));
        $changes = $mcp->projectFlow($projectID, $flowAction);
        if(dao::isError()) $this->daoFail();

        $actMap   = array('start' => 'Started', 'suspend' => 'Suspended', 'activate' => 'Activated', 'close' => 'Closed');
        $actionID = $this->loadModel('action')->create('project', $projectID, $actMap[$flowAction], (string)$this->post->comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('projectFlow', "project#$projectID $flowAction");
        $this->ok($changes ? $flowAction : 'nochange');
    }

    public function manageTeam()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $projectID = (int)$mcp->getQueryParam('projectID');
        if(!$projectID) $this->deny('projectID required');

        $projectModel = $this->loadModel('project');
        if(empty($projectModel->getById($projectID))) $this->deny('PROJECT_NOT_FOUND');

        $members = json_decode((string)$this->post->members, true);
        if(!is_array($members) or empty($members)) $this->deny('members must be a non-empty JSON array');

        $existing = array();
        foreach($projectModel->getTeamMembers($projectID) as $member) $existing[$member->account] = true;

        $_POST = array();
        $i = 0;
        foreach($members as $member)
        {
            if(!is_array($member) or empty($member['account'])) continue;
            $_POST['accounts'][$i] = (string)$member['account'];
            $_POST['roles'][$i]    = isset($member['role']) ? (string)$member['role'] : '';
            $_POST['days'][$i]     = isset($member['days']) ? $member['days'] : 0;
            $_POST['hours'][$i]    = isset($member['hours']) ? $member['hours'] : 0;
            $_POST['limited'][$i]  = isset($member['limited']) ? (string)$member['limited'] : 'no';
            $_POST['modes'][$i]    = isset($member['mode']) ? (string)$member['mode'] : (isset($existing[$member['account']]) ? 'update' : 'create');
            $i++;
        }
        if(empty($_POST['accounts'])) $this->deny('members[].account required');

        $projectModel->manageMembers($projectID);
        if(dao::isError()) $this->daoFail();

        $this->loadModel('action')->create('project', $projectID, 'ManagedMembers', 'members: ' . join(',', $_POST['accounts']));
        $mcp->auditLog('manageTeam', "project#$projectID members=" . count($_POST['accounts']));
        $this->ok('managed');
    }

    /* ============ 产品 ============ */

    public function createProduct()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('name', 'code', 'PO', 'QD', 'RD', 'type', 'line', 'desc', 'acl', 'whitelist'));
        if(trim((string)$this->post->name) === '') $this->deny('name required');
        if(trim((string)$this->post->code) === '') $this->deny('code required');
        if(is_array($this->post->whitelist)) $_POST['whitelist'] = join(',', $this->post->whitelist);

        $productID = $this->loadModel('product')->create();
        if(dao::isError()) $this->daoFail();
        if(!$productID) $this->deny('CREATE_FAILED');

        $this->loadModel('action')->create('product', $productID, 'opened');
        $mcp->auditLog('createProduct', "product#$productID name=" . $this->post->name);
        $this->ok(array('productID' => (int)$productID));
    }

    public function updateProduct()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $productID = (int)$mcp->getQueryParam('productID');
        if(!$productID) $this->deny('productID required');

        $productModel = $this->loadModel('product');
        $product      = $productModel->getById($productID);
        if(empty($product)) $this->deny('PRODUCT_NOT_FOUND');

        $this->onlyPost(array('name', 'code', 'PO', 'QD', 'RD', 'type', 'line', 'desc', 'acl', 'whitelist'));
        $current = array('name' => $product->name, 'code' => $product->code);
        foreach($current as $key => $value)
        {
            if(!isset($_POST[$key]) or $_POST[$key] === '') $_POST[$key] = $value;
        }
        if(is_array($this->post->whitelist)) $_POST['whitelist'] = join(',', $this->post->whitelist);

        $changes = $productModel->update($productID);
        if(dao::isError()) $this->daoFail();

        if(!empty($changes))
        {
            $actionID = $this->loadModel('action')->create('product', $productID, 'edited');
            $this->loadModel('action')->logHistory($actionID, $changes);
        }
        $mcp->auditLog('updateProduct', "product#$productID");
        $this->ok($changes ? 'updated' : 'nochange');
    }

    /* ============ 需求 ============ */

    public function createStory()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('product', 'title', 'spec', 'verify', 'module', 'plan', 'source', 'sourceNote', 'pri', 'estimate', 'assignedTo', 'needNotReview', 'keywords'));
        if(trim((string)$this->post->product) === '') $this->deny('product required');
        if(trim((string)$this->post->title) === '')   $this->deny('title required');
        if(trim((string)$this->post->module) === '')  unset($_POST['module']);

        $result = $this->loadModel('story')->create(0, 0, '');
        if(dao::isError()) $this->daoFail();
        if(!is_array($result) or empty($result['id'])) $this->deny('CREATE_FAILED');

        $storyID = (int)$result['id'];
        if($result['status'] === 'exists')
        {
            $mcp->auditLog('createStory', "story#$storyID duplicate");
            $this->ok(array('storyID' => $storyID, 'note' => 'duplicate exists'));
        }

        $this->loadModel('action')->create('story', $storyID, 'Opened');
        $mcp->auditLog('createStory', "story#$storyID title=" . $this->post->title);
        $this->ok(array('storyID' => $storyID, 'status' => $result['status']));
    }

    public function updateStory()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $storyID = (int)$mcp->getQueryParam('storyID');
        if(!$storyID) $this->deny('storyID required');

        $storyModel = $this->loadModel('story');
        $story      = $storyModel->getById($storyID);
        if(empty($story)) $this->deny('STORY_NOT_FOUND');

        $this->onlyPost(array('title', 'pri', 'estimate', 'module', 'plan', 'stage', 'source', 'sourceNote', 'assignedTo', 'keywords', 'comment'));
        if(!isset($_POST['title']) or trim((string)$_POST['title']) === '') $_POST['title'] = $story->title;

        $comment = (string)$this->post->comment;
        unset($_POST['comment']);

        $changes = $storyModel->update($storyID);
        if(dao::isError()) $this->daoFail();

        $action   = !empty($changes) ? 'Edited' : 'Commented';
        $actionID = $this->loadModel('action')->create('story', $storyID, $action, $comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('updateStory', "story#$storyID");
        $this->ok($changes ? 'updated' : 'nochange');
    }

    public function changeStory()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $storyID = (int)$mcp->getQueryParam('storyID');
        if(!$storyID) $this->deny('storyID required');

        $storyModel = $this->loadModel('story');
        $story      = $storyModel->getById($storyID);
        if(empty($story)) $this->deny('STORY_NOT_FOUND');

        $this->onlyPost(array('title', 'spec', 'verify', 'assignedTo', 'needNotReview', 'comment'));
        if(!isset($_POST['title']) or trim((string)$_POST['title']) === '') $_POST['title'] = $story->title;
        if(!isset($_POST['spec']))   $_POST['spec']   = isset($story->spec) ? $story->spec : '';
        if(!isset($_POST['verify'])) $_POST['verify'] = isset($story->verify) ? $story->verify : '';
        if(!isset($_POST['assignedTo']) or $_POST['assignedTo'] === '') $_POST['assignedTo'] = $story->assignedTo;

        $comment = (string)$this->post->comment;

        $changes = $storyModel->change($storyID);
        if(dao::isError()) $this->daoFail();

        $action   = !empty($changes) ? 'Changed' : 'Commented';
        $actionID = $this->loadModel('action')->create('story', $storyID, $action, $comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('changeStory', "story#$storyID");
        $this->ok($changes ? 'changed' : 'nochange');
    }

    public function storyFlow()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $storyID    = (int)$mcp->getQueryParam('storyID');
        if(!$storyID) $this->deny('storyID required');
        $flowAction = (string)$mcp->getQueryParam('action');
        if(!in_array($flowAction, array('close', 'activate'))) $this->deny('action invalid');

        $storyModel = $this->loadModel('story');
        if(empty($storyModel->getById($storyID))) $this->deny('STORY_NOT_FOUND');

        if($flowAction === 'close')
        {
            $this->onlyPost(array('closedReason', 'duplicateStory', 'childStories', 'comment'));
            if(trim((string)$this->post->closedReason) === '') $this->deny('closedReason required (done/subdivided/duplicate/postponed/willnotdo/cancel)');
            if($this->post->closedReason === 'duplicate' and trim((string)$this->post->duplicateStory) === '') $this->deny('duplicateStory required when closedReason=duplicate');

            $changes  = $storyModel->close($storyID);
            $extra    = ucfirst((string)$this->post->closedReason);
            if($this->post->closedReason === 'duplicate') $extra .= ':' . $this->post->duplicateStory;
            $actionID = $this->loadModel('action')->create('story', $storyID, 'Closed', (string)$this->post->comment, $extra);
        }
        else
        {
            $this->onlyPost(array('assignedTo', 'comment'));
            $_POST['status'] = 'active';
            if(trim((string)$this->post->assignedTo) === '') $_POST['assignedTo'] = $storyModel->getById($storyID)->assignedTo;

            $changes  = $storyModel->activate($storyID);
            $actionID = $this->loadModel('action')->create('story', $storyID, 'Activated', (string)$this->post->comment);
        }
        if(dao::isError()) $this->daoFail();
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);

        $mcp->auditLog('storyFlow', "story#$storyID $flowAction");
        $this->ok($changes ? $flowAction : 'nochange');
    }

    /* ============ 任务 / Bug 编辑 ============ */

    public function updateTask()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $taskID = (int)$mcp->getQueryParam('taskID');
        if(!$taskID) $this->deny('taskID required');

        $taskModel = $this->loadModel('task');
        $task      = $taskModel->getById($taskID);
        if(empty($task)) $this->deny('TASK_NOT_FOUND');

        $this->onlyPost(array('name', 'type', 'desc', 'pri', 'estimate', 'left', 'consumed', 'deadline', 'estStarted', 'assignedTo', 'comment'));

        /* 回填：name/type/consumed 等为空会被 setDefault 覆盖或触发校验失败 */
        $defaults = array('name' => $task->name, 'type' => $task->type, 'desc' => $task->desc, 'estimate' => $task->estimate, 'left' => $task->left, 'consumed' => $task->consumed);
        foreach($defaults as $key => $value)
        {
            if(!isset($_POST[$key]) or $_POST[$key] === '') $_POST[$key] = $value;
        }
        if((float)$_POST['consumed'] < (float)$task->consumed) $this->deny('consumed must be >= current consumed (' . $task->consumed . ')');

        $comment = (string)$this->post->comment;
        unset($_POST['comment']);

        $changes = $taskModel->update($taskID);
        if(dao::isError()) $this->daoFail();

        $action   = !empty($changes) ? 'Edited' : 'Commented';
        $actionID = $this->loadModel('action')->create('task', $taskID, $action, $comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('updateTask', "task#$taskID");
        $this->ok($changes ? 'updated' : 'nochange');
    }

    public function updateBug()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $bugID = (int)$mcp->getQueryParam('bugID');
        if(!$bugID) $this->deny('bugID required');

        $bugModel = $this->loadModel('bug');
        $bug      = $bugModel->getById($bugID);
        if(empty($bug)) $this->deny('BUG_NOT_FOUND');

        $this->onlyPost(array('title', 'openedBuild', 'module', 'type', 'severity', 'pri', 'steps', 'assignedTo', 'deadline', 'keywords', 'comment'));
        if(!isset($_POST['title']) or trim((string)$_POST['title']) === '') $_POST['title'] = $bug->title;
        if(!isset($_POST['openedBuild']) or $_POST['openedBuild'] === '')   $_POST['openedBuild'] = $bug->openedBuild;

        /* module 缺失会被 fixer setDefault 为 0 从而触发『所属模块不能为空』校验：优先回填现值，无模块时给出明确指引 */
        if(empty($_POST['module']) and (int)$bug->module > 0) $_POST['module'] = $bug->module;
        if(empty($_POST['module'])) $this->deny('该 Bug 无所属模块（module=0），禅道编辑要求先指定模块：请传 module（有效模块 ID）后重试。');

        /* 已解决/已关闭的 Bug 必须回传 resolution/closed 相关字段，否则会被强制置回 active */
        if($bug->status == 'resolved' or $bug->status == 'closed')
        {
            $_POST['resolution']   = $bug->resolution;
            $_POST['resolvedBy']   = $bug->resolvedBy;
            $_POST['resolvedDate'] = $bug->resolvedDate;
            if(!empty($bug->resolvedBuild)) $_POST['resolvedBuild'] = $bug->resolvedBuild;
        }
        if($bug->status == 'closed')
        {
            $_POST['closedBy']   = $bug->closedBy;
            $_POST['closedDate'] = $bug->closedDate;
        }

        $comment = (string)$this->post->comment;
        unset($_POST['comment']);

        $changes = $bugModel->update($bugID);
        if(dao::isError()) $this->daoFail('UPDATE_FAILED: ');

        $action   = !empty($changes) ? 'Edited' : 'Commented';
        $actionID = $this->loadModel('action')->create('bug', $bugID, $action, $comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('updateBug', "bug#$bugID");
        $this->ok($changes ? 'updated' : 'nochange');
    }

    public function activateBug()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $bugID = (int)$mcp->getQueryParam('bugID');
        if(!$bugID) $this->deny('bugID required');

        $bugModel = $this->loadModel('bug');
        if(empty($bugModel->getById($bugID))) $this->deny('BUG_NOT_FOUND');

        $this->onlyPost(array('comment', 'assignedTo', 'openedBuild'));
        $bugModel->activate($bugID);
        if(dao::isError()) $this->daoFail();

        $this->loadModel('action')->create('bug', $bugID, 'Activated', (string)$this->post->comment);
        $mcp->auditLog('activateBug', "bug#$bugID");
        $this->ok('activated');
    }

    public function assignBug()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $bugID = (int)$mcp->getQueryParam('bugID');
        if(!$bugID) $this->deny('bugID required');

        $bugModel = $this->loadModel('bug');
        if(empty($bugModel->getById($bugID))) $this->deny('BUG_NOT_FOUND');

        $this->onlyPost(array('assignedTo', 'comment', 'mailto'));
        if(trim((string)$this->post->assignedTo) === '') $this->deny('assignedTo required');
        if(is_array($this->post->mailto)) $_POST['mailto'] = join(',', $this->post->mailto);

        $changes = $bugModel->assign($bugID);
        if(dao::isError()) $this->daoFail();

        $actionID = $this->loadModel('action')->create('bug', $bugID, 'Assigned', (string)$this->post->comment, (string)$this->post->assignedTo);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('assignBug', "bug#$bugID to " . $this->post->assignedTo);
        $this->ok($changes ? 'assigned' : 'nochange');
    }

    public function deleteEffort()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $estimateID = (int)$mcp->getQueryParam('estimateID');
        if(!$estimateID) $this->deny('estimateID required');

        $taskModel = $this->loadModel('task');
        $estimate  = $taskModel->getEstimateById($estimateID);
        if(empty($estimate)) $this->deny('ESTIMATE_NOT_FOUND');

        $changes  = $taskModel->deleteEstimate($estimateID);
        if(dao::isError()) $this->daoFail();

        $actionID = $this->loadModel('action')->create('task', $estimate->task, 'DeleteEstimate');
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('deleteEffort', "estimate#$estimateID task#" . $estimate->task);
        $this->ok('deleted');
    }

    /* ============ 发布 / 测试 / 文档 ============ */

    public function createRelease()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $productID = (int)$mcp->getQueryParam('productID');
        if(!$productID) $this->deny('productID required');
        $branch = (string)$mcp->getQueryParam('branch');
        if($branch === '') $branch = 0; // 空串会拼坏 build 唯一性检查 SQL，原生默认值为 0

        $this->onlyPost(array('name', 'date', 'build', 'desc', 'stories', 'bugs'));
        if(trim((string)$this->post->name) === '') $this->deny('name required');
        if(trim((string)$this->post->date) === '') $this->deny('date required (YYYY-MM-DD)');
        if(is_array($this->post->stories)) $_POST['stories'] = join(',', $this->post->stories);
        if(is_array($this->post->bugs))    $_POST['bugs']    = join(',', $this->post->bugs);

        $buildCreated = !($this->post->build);
        $releaseID    = $this->loadModel('release')->create($productID, $branch);
        if(dao::isError()) $this->daoFail();
        if(!$releaseID) $this->deny('CREATE_FAILED');

        $this->loadModel('action')->create('release', $releaseID, 'opened');
        $mcp->auditLog('createRelease', "release#$releaseID name=" . $this->post->name);
        $this->ok(array('releaseID' => (int)$releaseID, 'buildCreated' => $buildCreated));
    }

    public function createTesttask()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('product', 'project', 'build', 'owner', 'pri', 'begin', 'end', 'name', 'desc', 'mailto'));
        if(trim((string)$this->post->product) === '') $this->deny('product required');
        if(trim((string)$this->post->project) === '') $this->deny('project required');
        if(trim((string)$this->post->build) === '')   $this->deny('build required (e.g. trunk)');
        if(trim((string)$this->post->begin) === '')   $this->deny('begin required');
        if(trim((string)$this->post->end) === '')     $this->deny('end required');
        if(trim((string)$this->post->name) === '')    $this->deny('name required');
        if(is_array($this->post->mailto)) $_POST['mailto'] = join(',', $this->post->mailto);

        $taskID = $this->loadModel('testtask')->create();
        if(dao::isError()) $this->daoFail();
        if(!$taskID) $this->deny('CREATE_FAILED');

        $this->loadModel('action')->create('testtask', $taskID, 'opened');
        $mcp->auditLog('createTesttask', "testtask#$taskID name=" . $this->post->name);
        $this->ok(array('testtaskID' => (int)$taskID));
    }

    public function testtaskFlow()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $taskID = (int)$mcp->getQueryParam('taskID');
        if(!$taskID) $this->deny('taskID required');
        $flowAction = (string)$mcp->getQueryParam('action');
        if(!in_array($flowAction, array('start', 'close'))) $this->deny('action invalid (start|close)');

        $this->onlyPost(array('comment'));
        $testtaskModel = $this->loadModel('testtask');

        /* testtask->getById 在所属项目未关联产品时返回 false，model 的 createChanges 会因此恒空，故用状态对比判定变化 */
        $statusBefore = $this->dao->select('status')->from(TABLE_TESTTASK)->where('id')->eq($taskID)->fetch('status');

        if($flowAction === 'start')
        {
            $changes  = $testtaskModel->start($taskID);
            $actName  = 'Started';
        }
        else
        {
            $changes  = $testtaskModel->close($taskID);
            $actName  = 'Closed';
        }
        if(dao::isError()) $this->daoFail();

        $statusAfter = $this->dao->select('status')->from(TABLE_TESTTASK)->where('id')->eq($taskID)->fetch('status');
        $changed     = ($statusBefore !== false and $statusAfter !== false and $statusBefore != $statusAfter);

        $actionID = $this->loadModel('action')->create('testtask', $taskID, $actName, (string)$this->post->comment);
        if(!empty($changes)) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('testtaskFlow', "testtask#$taskID $flowAction $statusBefore->$statusAfter");
        $this->ok($changed ? $flowAction : 'nochange');
    }

    public function createTestcase()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('product', 'branch', 'module', 'story', 'title', 'type', 'pri', 'stage', 'precondition', 'keywords', 'forceNotReview', 'steps'));
        if(trim((string)$this->post->product) === '') $this->deny('product required');
        if(trim((string)$this->post->title) === '')   $this->deny('title required');
        if(trim((string)$this->post->type) === '')    $this->deny('type required (feature/performance/config/install/security/interface/unit/other)');

        $steps = json_decode((string)$this->post->steps, true);
        unset($_POST['steps']);
        if(is_array($steps) and !empty($steps))
        {
            foreach($steps as $i => $step)
            {
                $_POST['steps'][$i]    = isset($step['desc']) ? (string)$step['desc'] : '';
                $_POST['expects'][$i]  = isset($step['expect']) ? (string)$step['expect'] : '';
                $_POST['stepType'][$i] = isset($step['type']) ? (string)$step['type'] : 'item';
            }
        }

        $result = $this->loadModel('testcase')->create(0);
        if(dao::isError()) $this->daoFail();
        if(!is_array($result) or empty($result['id'])) $this->deny('CREATE_FAILED');

        $caseID = (int)$result['id'];
        if($result['status'] === 'exists')
        {
            $mcp->auditLog('createTestcase', "case#$caseID duplicate");
            $this->ok(array('caseID' => $caseID, 'note' => 'duplicate exists'));
        }

        $this->loadModel('action')->create('case', $caseID, 'Opened');
        $mcp->auditLog('createTestcase', "case#$caseID title=" . $this->post->title);
        $this->ok(array('caseID' => $caseID));
    }

    public function updateTestcase()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $caseID = (int)$mcp->getQueryParam('caseID');
        if(!$caseID) $this->deny('caseID required');

        $caseModel = $this->loadModel('testcase');
        $case      = $caseModel->getById($caseID);
        if(empty($case)) $this->deny('CASE_NOT_FOUND');

        $this->onlyPost(array('title', 'type', 'pri', 'stage', 'precondition', 'keywords', 'story', 'module', 'comment'));
        if(!isset($_POST['title']) or trim((string)$_POST['title']) === '') $_POST['title'] = $case->title;
        if(!isset($_POST['type']) or trim((string)$_POST['type']) === '')   $_POST['type']  = $case->type;

        $comment = (string)$this->post->comment;
        unset($_POST['comment']);

        $changes = $caseModel->update($caseID);
        if(dao::isError()) $this->daoFail();

        $action   = !empty($changes) ? 'Edited' : 'Commented';
        $actionID = $this->loadModel('action')->create('case', $caseID, $action, $comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('updateTestcase', "case#$caseID");
        $this->ok($changes ? 'updated' : 'nochange');
    }

    public function createDoc()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('lib', 'product', 'project', 'title', 'content', 'contentType', 'type', 'keywords', 'module', 'acl', 'users', 'groups'));
        if(trim((string)$this->post->title) === '')   $this->deny('title required');
        if(trim((string)$this->post->content) === '') $this->deny('content required');

        if(trim((string)$this->post->lib) === '')
        {
            $libID = $mcp->getMainDoclib((int)$this->post->product, (int)$this->post->project);
            if(!$libID) $this->deny('lib required (doclib not found for given product/project)');
            $_POST['lib'] = $libID;
        }
        $contentType = (string)$this->post->contentType;
        if($contentType === 'markdown')
        {
            $_POST['contentMarkdown'] = (string)$this->post->content;
            unset($_POST['content']);
        }
        else
        {
            $_POST['contentType'] = 'html';
        }
        if(is_array($this->post->users))  $_POST['users']  = join(',', $this->post->users);
        if(is_array($this->post->groups)) $_POST['groups'] = join(',', $this->post->groups);

        $result = $this->loadModel('doc')->create();
        if(dao::isError()) $this->daoFail();
        if(!is_array($result) or empty($result['id'])) $this->deny('CREATE_FAILED');

        $docID = (int)$result['id'];
        if($result['status'] === 'exists')
        {
            $mcp->auditLog('createDoc', "doc#$docID duplicate");
            $this->ok(array('docID' => $docID, 'note' => 'duplicate exists'));
        }

        $this->loadModel('action')->create('doc', $docID, 'Created');
        $mcp->auditLog('createDoc', "doc#$docID title=" . $this->post->title);
        $this->ok(array('docID' => $docID));
    }

    /* ============ 提交同步 ============ */

    public function getSyncState()
    {
        $mcp      = $this->loadModel('mcp');
        $repoRoot = trim((string)$mcp->getQueryParam('repoRoot'));
        if($repoRoot === '') $this->deny('repoRoot required');
        $this->ok(array('lastHash' => $mcp->getSyncState($repoRoot)));
    }

    public function syncCommits()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $commits = json_decode((string)$this->post->commits, true);
        if(!is_array($commits)) $this->deny('commits must be a JSON array');

        $first = reset($commits);
        if(is_array($first) && !isset($first['hash'])) $this->deny('LEGACY_FORMAT: structured commits required, please update client');

        $repoRoot = trim((string)$this->post->repoRoot);
        if($repoRoot === '') $this->deny('repoRoot required');

        $result = $mcp->syncCommits($commits, $repoRoot);
        $state  = (is_array($first) && !empty($first['hash'])) ? ' state=' . substr((string)$first['hash'], 0, 10) : '';
        $mcp->auditLog('syncCommits', 'count=' . count($result) . $state);
        $this->ok($result);
    }
}
