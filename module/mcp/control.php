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

    private function requireConfirm($mcp)
    {
        if(strtolower(trim((string)$mcp->getQueryParam('confirm'))) !== 'yes') $this->deny('CONFIRM_REQUIRED: pass confirm=yes (soft delete, the row is kept with deleted=1)');
    }

    private function getRow($table, $id, $fields)
    {
        return $this->dao->select($fields)->from($table)->where('id')->eq((int)$id)->fetch();
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
        $mcp = $this->actor();

        $objectType = trim((string)$mcp->getQueryParam('objectType'));
        if($objectType === '') $objectType = 'task';
        if(!in_array($objectType, array('task', 'bug', 'story', 'doc'))) $this->deny('objectType invalid (task|bug|story|doc)');

        $objectID = (int)$mcp->getQueryParam('objectID');
        if(!$objectID) $objectID = (int)$mcp->getQueryParam('taskID'); /* 兼容旧参数 */
        if(!$objectID) $this->deny('objectID required');

        $comment  = trim((string)$this->post->comment);
        $actionID = $this->loadModel('action')->create($objectType, $objectID, 'commented', $comment);
        $mcp->auditLog('addComment', "{$objectType}#$objectID");
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

    public function getProject()
    {
        $mcp       = $this->loadModel('mcp');
        $projectID = (int)$mcp->getQueryParam('projectID');
        if(!$projectID) $this->deny('projectID required');

        $projectModel = $this->loadModel('project');
        $project      = $projectModel->getById($projectID);
        if(empty($project)) $this->deny('PROJECT_NOT_FOUND');

        $products = array();
        foreach($projectModel->getProducts($projectID) as $product)
        {
            $products[] = array('id' => (int)$product->id, 'name' => $product->name, 'branch' => (int)$product->branch, 'plan' => (int)$product->plan);
        }

        $stories = array();
        foreach($this->loadModel('story')->getProjectStories($projectID) as $story)
        {
            $stories[] = array('id' => (int)$story->id, 'title' => $story->title, 'status' => $story->status, 'stage' => $story->stage);
        }

        $this->ok(array(
            'id'       => (int)$project->id,
            'name'     => $project->name,
            'code'     => $project->code,
            'status'   => $project->status,
            'begin'    => $project->begin,
            'end'      => $project->end,
            'desc'     => $project->desc,
            'products' => $products,
            'stories'  => $stories,
        ));
    }

    public function createProject()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('name', 'code', 'begin', 'end', 'team', 'days', 'type', 'acl', 'desc', 'whitelist', 'products'));
        if(trim((string)$this->post->name) === '')  $this->deny('name required');
        if(trim((string)$this->post->code) === '')  $this->deny('code required');
        if(trim((string)$this->post->begin) === '') $this->deny('begin required');
        if(trim((string)$this->post->end) === '')   $this->deny('end required');
        if(is_array($this->post->whitelist)) $_POST['whitelist'] = join(',', $this->post->whitelist);

        $copyFrom   = (int)$mcp->getQueryParam('copyFrom');
        $productIDs = array();
        if(isset($_POST['products']) and is_string($_POST['products']))
        {
            $decoded = json_decode($_POST['products'], true);
            if(is_array($decoded)) $productIDs = $decoded;
        }
        unset($_POST['products']);

        $projectModel = $this->loadModel('project');
        $projectID    = $projectModel->create(); /* 团队复制由本端点自行处理：原生 create($copyProjectID) 的拷贝未剔除源行主键 id，会触发 zt_team 主键冲突 */
        if(dao::isError()) $this->daoFail();
        if(!$projectID) $this->deny('CREATE_FAILED');

        if($copyFrom)
        {
            $members = $this->dao->select('*')->from(TABLE_TEAM)->where('root')->eq($copyFrom)->andWhere('type')->eq('project')->fetchAll();
            $today   = date('Y-m-d');
            foreach($members as $member)
            {
                if($member->account == $this->app->user->account) continue; /* 创建者已由 create 自动加入 */
                unset($member->id);
                $member->root = $projectID;
                $member->join = $today;
                $member->type = 'project';
                $this->dao->insert(TABLE_TEAM)->data($member)->exec();
            }
            if(dao::isError()) $this->daoFail();
        }

        if(!empty($productIDs))
        {
            $productModel = $this->loadModel('product');
            $_POST = array('products' => array(), 'branch' => array());
            $i = 0;
            foreach($productIDs as $productID)
            {
                $productID = (int)$productID;
                if(!$productID or empty($productModel->getById($productID))) $this->deny('PRODUCT_NOT_FOUND: ' . $productID);
                $_POST['products'][$i] = $productID;
                $_POST['branch'][$i]   = 0;
                $i++;
            }
            $projectModel->updateProducts($projectID);
            if(dao::isError()) $this->daoFail();
        }

        $this->loadModel('action')->create('project', $projectID, 'opened');
        $mcp->auditLog('createProject', "project#$projectID name=" . $this->post->name . ($copyFrom ? " copyFrom=$copyFrom" : ''));
        $this->ok(array('projectID' => (int)$projectID, 'copiedFrom' => $copyFrom ? $copyFrom : null));
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

        $this->onlyPost(array('name', 'code', 'begin', 'end', 'days', 'type', 'team', 'desc', 'acl', 'whitelist', 'products'));
        $current = array('name' => $project->name, 'code' => $project->code, 'begin' => $project->begin, 'end' => $project->end);
        foreach($current as $key => $value)
        {
            if(!isset($_POST[$key]) or $_POST[$key] === '') $_POST[$key] = $value;
        }
        if(is_array($this->post->whitelist)) $_POST['whitelist'] = join(',', $this->post->whitelist);

        $productsInput = isset($_POST['products']) ? $_POST['products'] : null;
        $_POST['products'] = array();
        $_POST['branch']   = array();
        if($productsInput !== null and is_string($productsInput))
        {
            /* 显式传 products：全量替换关联（空数组 [] 表示清空） */
            $productIDs = json_decode($productsInput, true);
            if(!is_array($productIDs)) $this->deny('products must be a JSON array of product IDs');
            $productModel = $this->loadModel('product');
            $i = 0;
            foreach($productIDs as $productID)
            {
                $productID = (int)$productID;
                if(!$productID or empty($productModel->getById($productID))) $this->deny('PRODUCT_NOT_FOUND: ' . $productID);
                $_POST['products'][$i] = $productID;
                $_POST['branch'][$i]   = 0;
                $i++;
            }
        }
        else
        {
            /* 未传 products：回填现有产品关联，避免 updateProducts 清空关联 */
            $i = 0;
            foreach($projectModel->getProducts($projectID) as $row)
            {
                $_POST['products'][$i] = $row->id;
                $_POST['branch'][$i]   = $row->branch;
                if(!empty($row->plan)) $_POST['plans'][$row->id] = $row->plan;
                $i++;
            }
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
        $this->ok(($changes or $productsInput !== null) ? 'updated' : 'nochange');
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

    /* ============ 删除（软删除：model::delete 置 deleted=1 并写 action；须显式 confirm=yes） ============ */

    public function deleteTask()
    {
        $this->writeGuard();
        $mcp = $this->actor();
        $this->requireConfirm($mcp);

        $taskID = $this->requireTaskID($mcp);
        $task   = $this->getRow(TABLE_TASK, $taskID, 'id,name,parent,fromBug,story,deleted');
        if(empty($task))           $this->deny('TASK_NOT_FOUND');
        if(!empty($task->deleted)) $this->deny('TASK_ALREADY_DELETED');

        $children  = $this->dao->select('id,story')->from(TABLE_TASK)->where('parent')->eq($taskID)->andWhere('deleted')->eq('0')->fetchAll();
        $taskModel = $this->loadModel('task');

        $taskModel->delete(TABLE_TASK, $taskID);
        if(!empty($task->parent))  $taskModel->updateParentStatus($task->id);
        if(!empty($task->fromBug)) $this->dao->update(TABLE_BUG)->set('toTask')->eq(0)->where('id')->eq($task->fromBug)->exec();
        if(!empty($task->story))   $this->loadModel('story')->setStage($task->story);
        foreach($children as $child)
        {
            $taskModel->delete(TABLE_TASK, $child->id);
            if(!empty($child->story)) $this->loadModel('story')->setStage($child->story);
        }
        if(dao::isError()) $this->daoFail();

        $mcp->auditLog('deleteTask', "task#$taskID " . $task->name . " children=" . count($children));
        $this->ok(array('deleted' => 'task', 'id' => $taskID, 'name' => $task->name, 'children' => count($children), 'soft' => true));
    }

    public function deleteBug()
    {
        $this->writeGuard();
        $mcp = $this->actor();
        $this->requireConfirm($mcp);

        $bugID = (int)$mcp->getQueryParam('bugID');
        if(!$bugID) $this->deny('bugID required');

        $bug = $this->getRow(TABLE_BUG, $bugID, 'id,title,toTask,deleted');
        if(empty($bug))            $this->deny('BUG_NOT_FOUND');
        if(!empty($bug->deleted))  $this->deny('BUG_ALREADY_DELETED');

        $this->loadModel('bug')->delete(TABLE_BUG, $bugID);
        if(dao::isError()) $this->daoFail();

        $mcp->auditLog('deleteBug', "bug#$bugID " . $bug->title);
        $this->ok(array('deleted' => 'bug', 'id' => $bugID, 'name' => $bug->title, 'linkedTask' => (int)$bug->toTask, 'soft' => true));
    }

    public function deleteStory()
    {
        $this->writeGuard();
        $mcp = $this->actor();
        $this->requireConfirm($mcp);

        $storyID = (int)$mcp->getQueryParam('storyID');
        if(!$storyID) $this->deny('storyID required');

        $story = $this->getRow(TABLE_STORY, $storyID, 'id,title,deleted');
        if(empty($story))           $this->deny('STORY_NOT_FOUND');
        if(!empty($story->deleted)) $this->deny('STORY_ALREADY_DELETED');

        $this->loadModel('story')->delete(TABLE_STORY, $storyID);
        if(dao::isError()) $this->daoFail();

        $mcp->auditLog('deleteStory', "story#$storyID " . $story->title);
        $this->ok(array('deleted' => 'story', 'id' => $storyID, 'name' => $story->title, 'soft' => true));
    }

    public function deleteTestcase()
    {
        $this->writeGuard();
        $mcp = $this->actor();
        $this->requireConfirm($mcp);

        $caseID = (int)$mcp->getQueryParam('caseID');
        if(!$caseID) $this->deny('caseID required');

        $case = $this->getRow(TABLE_CASE, $caseID, 'id,title,deleted');
        if(empty($case))            $this->deny('TESTCASE_NOT_FOUND');
        if(!empty($case->deleted))  $this->deny('TESTCASE_ALREADY_DELETED');

        $this->loadModel('testcase')->delete(TABLE_CASE, $caseID);
        if(dao::isError()) $this->daoFail();

        $mcp->auditLog('deleteTestcase', "case#$caseID " . $case->title);
        $this->ok(array('deleted' => 'testcase', 'id' => $caseID, 'name' => $case->title, 'soft' => true));
    }

    public function deleteDoc()
    {
        $this->writeGuard();
        $mcp = $this->actor();
        $this->requireConfirm($mcp);

        $docID = (int)$mcp->getQueryParam('docID');
        if(!$docID) $this->deny('docID required');

        /* 直接读表，避免 doc::getById 内的 checkPrivDoc 在无会话下 die 掉 JSON 响应。 */
        $doc = $this->getRow(TABLE_DOC, $docID, 'id,title,lib,deleted');
        if(empty($doc))             $this->deny('DOC_NOT_FOUND');
        if(!empty($doc->deleted))   $this->deny('DOC_ALREADY_DELETED');

        $this->loadModel('doc')->delete(TABLE_DOC, $docID);
        if(dao::isError()) $this->daoFail();

        $mcp->auditLog('deleteDoc', "doc#$docID " . $doc->title);
        $this->ok(array('deleted' => 'doc', 'id' => $docID, 'name' => $doc->title, 'lib' => (int)$doc->lib, 'soft' => true));
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

        $this->onlyPost(array('title', 'type', 'pri', 'stage', 'precondition', 'keywords', 'story', 'module', 'comment', 'steps'));
        if(!isset($_POST['title']) or trim((string)$_POST['title']) === '') $_POST['title'] = $case->title;
        if(!isset($_POST['type']) or trim((string)$_POST['type']) === '')   $_POST['type']  = $case->type;

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

    /* ============ 计划 / 账号 / 构建 ============ */

    public function createPlan()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('product', 'branch', 'title', 'begin', 'end', 'future', 'desc'));
        if(trim((string)$this->post->product) === '') $this->deny('product required');
        if(trim((string)$this->post->title) === '')   $this->deny('title required');

        $planID = $this->loadModel('productplan')->create();
        if(dao::isError()) $this->daoFail();
        if(!$planID) $this->deny('CREATE_FAILED');

        $this->loadModel('action')->create('productplan', $planID, 'opened');
        $mcp->auditLog('createPlan', "plan#$planID title=" . $this->post->title);
        $this->ok(array('planID' => (int)$planID));
    }

    public function listPlans()
    {
        $mcp       = $this->loadModel('mcp');
        $productID = (int)$mcp->getQueryParam('productID');
        if(!$productID) $this->deny('productID required');

        $pairs  = $this->loadModel('productplan')->getPairs($productID);
        $result = array();
        foreach($pairs as $planID => $title)
        {
            if(!$planID) continue;
            $result[] = array('id' => (int)$planID, 'title' => $title);
        }
        $this->ok($result);
    }

    public function listUsers()
    {
        $users  = $this->loadModel('user')->getList();
        $result = array();
        foreach($users as $user)
        {
            $result[] = array('id' => (int)$user->id, 'account' => $user->account, 'realname' => $user->realname, 'role' => isset($user->role) ? $user->role : '', 'dept' => (int)$user->dept);
        }
        $this->ok($result);
    }

    public function createBuild()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $projectID = (int)$mcp->getQueryParam('projectID');
        if(!$projectID) $this->deny('projectID required');

        $this->onlyPost(array('product', 'branch', 'name', 'builder', 'date', 'desc', 'scmPath', 'filePath'));
        if(trim((string)$this->post->product) === '') $this->deny('product required');
        if(trim((string)$this->post->name) === '')    $this->deny('name required');
        if(trim((string)$this->post->builder) === '') $_POST['builder'] = $this->app->user->account;
        if(trim((string)$this->post->date) === '')    $_POST['date']    = date('Y-m-d');

        $buildID = $this->loadModel('build')->create($projectID);
        if(dao::isError()) $this->daoFail();
        if(!$buildID) $this->deny('CREATE_FAILED');

        $this->loadModel('action')->create('build', $buildID, 'opened');
        $mcp->auditLog('createBuild', "build#$buildID name=" . $this->post->name);
        $this->ok(array('buildID' => (int)$buildID));
    }

    public function updateDoc()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $docID = (int)$mcp->getQueryParam('docID');
        if(!$docID) $this->deny('docID required');

        $docModel = $this->loadModel('doc');
        $doc      = $docModel->getByID($docID);
        if(empty($doc)) $this->deny('DOC_NOT_FOUND');

        $this->onlyPost(array('title', 'content', 'contentType', 'type', 'url', 'keywords', 'module', 'acl', 'users', 'groups', 'comment'));
        if(!isset($_POST['title']) or trim((string)$_POST['title']) === '') $_POST['title'] = $doc->title;
        if(!isset($_POST['content']) or (string)$_POST['content'] === '')  unset($_POST['content']);
        $_POST['lib'] = $doc->lib; /* 必带 lib，否则 model 会把 product/project 清空 */
        $_POST['contentType'] = ((string)$this->post->contentType === 'markdown') ? 'markdown' : 'html';
        if(is_array($this->post->users))  $_POST['users']  = join(',', $this->post->users);
        if(is_array($this->post->groups)) $_POST['groups'] = join(',', $this->post->groups);

        $comment = (string)$this->post->comment;
        unset($_POST['comment']);

        $result = $docModel->update($docID);
        if(dao::isError()) $this->daoFail();

        $changes  = (is_array($result) and !empty($result['changes'])) ? $result['changes'] : array();
        $action   = !empty($changes) ? 'Edited' : 'Commented';
        $actionID = $this->loadModel('action')->create('doc', $docID, $action, $comment);
        if(!empty($changes)) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('updateDoc', "doc#$docID");
        $this->ok(!empty($changes) ? 'updated' : 'nochange');
    }

    public function createDoclib()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $this->onlyPost(array('name', 'libType', 'product', 'project', 'acl', 'groups', 'users'));
        if(trim((string)$this->post->name) === '') $this->deny('name required');
        if(is_array($this->post->users))  $_POST['users']  = join(',', $this->post->users);
        if(is_array($this->post->groups)) $_POST['groups'] = join(',', $this->post->groups);

        $libID = $this->loadModel('doc')->createLib();
        if(dao::isError()) $this->daoFail();
        if(!$libID) $this->deny('CREATE_FAILED');

        $this->loadModel('action')->create('docLib', $libID, 'Created');
        $mcp->auditLog('createDoclib', "doclib#$libID name=" . $this->post->name);
        $this->ok(array('libID' => (int)$libID));
    }

    /* ============ 评审 / 确认 ============ */

    public function reviewStory()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $storyID = (int)$mcp->getQueryParam('storyID');
        if(!$storyID) $this->deny('storyID required');

        $storyModel = $this->loadModel('story');
        $story      = $storyModel->getById($storyID);
        if(empty($story)) $this->deny('STORY_NOT_FOUND');

        $this->onlyPost(array('result', 'comment', 'reviewedBy', 'assignedTo', 'closedReason', 'duplicateStory', 'childStories', 'preVersion'));
        $reviewResult = trim((string)$this->post->result);
        if(!in_array($reviewResult, array('pass', 'reject', 'revert', 'clarify'))) $this->deny('result required (pass|reject|revert|clarify)');
        if(!isset($_POST['assignedTo']) or $_POST['assignedTo'] === '') $_POST['assignedTo'] = $story->assignedTo ? $story->assignedTo : $story->openedBy;
        if(!isset($_POST['reviewedBy']) or $_POST['reviewedBy'] === '') $_POST['reviewedBy'] = $this->app->user->account;
        if(is_array($this->post->reviewedBy)) $_POST['reviewedBy'] = join(',', $this->post->reviewedBy);

        $changes = $storyModel->review($storyID);
        if(dao::isError()) $this->daoFail();

        $actionID = $this->loadModel('action')->create('story', $storyID, 'Reviewed', (string)$this->post->comment, ucfirst($reviewResult));
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        if($reviewResult === 'reject') $this->loadModel('action')->create('story', $storyID, 'Closed', '', ucfirst((string)$this->post->closedReason));
        $mcp->auditLog('reviewStory', "story#$storyID result=$reviewResult");
        $this->ok($reviewResult);
    }

    public function confirmBug()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $bugID = (int)$mcp->getQueryParam('bugID');
        if(!$bugID) $this->deny('bugID required');

        $bugModel = $this->loadModel('bug');
        if(empty($bugModel->getById($bugID))) $this->deny('BUG_NOT_FOUND');

        $this->onlyPost(array('assignedTo', 'type', 'pri', 'mailto', 'comment'));
        if(is_array($this->post->mailto)) $_POST['mailto'] = join(',', $this->post->mailto);

        $comment = (string)$this->post->comment;
        unset($_POST['comment']);

        $changes  = $bugModel->confirm($bugID);
        if(dao::isError()) $this->daoFail();
        $actionID = $this->loadModel('action')->create('bug', $bugID, 'bugConfirmed', $comment);
        if($changes) $this->loadModel('action')->logHistory($actionID, $changes);
        $mcp->auditLog('confirmBug', "bug#$bugID");
        $this->ok($changes ? 'confirmed' : 'nochange');
    }

    public function reviewTestcase()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $caseID = (int)$mcp->getQueryParam('caseID');
        if(!$caseID) $this->deny('caseID required');

        $caseModel = $this->loadModel('testcase');
        if(empty($caseModel->getById($caseID))) $this->deny('CASE_NOT_FOUND');

        $this->onlyPost(array('result', 'comment', 'reviewedBy'));
        $reviewResult = trim((string)$this->post->result);
        if(!in_array($reviewResult, array('pass', 'clarify'))) $this->deny('result required (pass|clarify)');
        if(!isset($_POST['reviewedBy']) or $_POST['reviewedBy'] === '') $_POST['reviewedBy'] = $this->app->user->account;
        if(is_array($this->post->reviewedBy)) $_POST['reviewedBy'] = join(',', $this->post->reviewedBy);

        $caseModel->review($caseID);
        if(dao::isError()) $this->daoFail();

        $this->loadModel('action')->create('case', $caseID, 'Reviewed', (string)$this->post->comment, ucfirst($reviewResult));
        $mcp->auditLog('reviewTestcase', "case#$caseID result=$reviewResult");
        $this->ok($reviewResult);
    }

    /* ============ 关联管理（发布 / 测试单 / 项目需求 / 细分） ============ */

    public function manageRelease()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $releaseID = (int)$mcp->getQueryParam('releaseID');
        if(!$releaseID) $this->deny('releaseID required');
        $relAction = (string)$mcp->getQueryParam('action');
        if(!in_array($relAction, array('link_story', 'unlink_story', 'link_bug', 'unlink_bug', 'change_status'))) $this->deny('action invalid (link_story|unlink_story|link_bug|unlink_bug|change_status)');

        $releaseModel = $this->loadModel('release');
        if(empty($releaseModel->getByID($releaseID))) $this->deny('RELEASE_NOT_FOUND');

        if($relAction === 'link_story' or $relAction === 'link_bug')
        {
            $key = ($relAction === 'link_story') ? 'stories' : 'bugs';
            $this->onlyPost(array($key));
            $ids = json_decode((string)$this->post->$key, true);
            if(!is_array($ids) or empty($ids)) $this->deny($key . ' must be a non-empty JSON array');

            $_POST[$key] = array();
            foreach($ids as $id) $_POST[$key][] = (int)$id;

            if($relAction === 'link_story') $releaseModel->linkStory($releaseID);
            else                            $releaseModel->linkBug($releaseID);
            if(dao::isError()) $this->daoFail();
            $mcp->auditLog('manageRelease', "release#$releaseID $relAction count=" . count($ids));
            $this->ok('linked');
        }
        elseif($relAction === 'unlink_story' or $relAction === 'unlink_bug')
        {
            $this->onlyPost(array('storyID', 'bugID', 'bugType'));
            if($relAction === 'unlink_story')
            {
                $itemID = (int)$this->post->storyID;
                if(!$itemID) $this->deny('storyID required');
                $releaseModel->unlinkStory($releaseID, $itemID);
            }
            else
            {
                $itemID = (int)$this->post->bugID;
                if(!$itemID) $this->deny('bugID required');
                $bugType = trim((string)$this->post->bugType);
                $releaseModel->unlinkBug($releaseID, $itemID, $bugType === '' ? 'bug' : $bugType);
            }
            if(dao::isError()) $this->daoFail();
            $mcp->auditLog('manageRelease', "release#$releaseID $relAction item=$itemID");
            $this->ok('unlinked');
        }
        else
        {
            $this->onlyPost(array('status'));
            $status = trim((string)$this->post->status);
            if(!in_array($status, array('normal', 'terminate'))) $this->deny('status invalid (normal|terminate)');
            $releaseModel->changeStatus($releaseID, $status);
            if(dao::isError()) $this->daoFail();
            $this->loadModel('action')->create('release', $releaseID, 'changestatus', '', $status);
            $mcp->auditLog('manageRelease', "release#$releaseID status=$status");
            $this->ok($status);
        }
    }

    public function linkProjectStories()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $projectID = (int)$mcp->getQueryParam('projectID');
        if(!$projectID) $this->deny('projectID required');

        $projectModel = $this->loadModel('project');
        if(empty($projectModel->getById($projectID))) $this->deny('PROJECT_NOT_FOUND');

        $storyIDs = json_decode((string)$this->post->stories, true);
        if(!is_array($storyIDs) or empty($storyIDs)) $this->deny('stories must be a non-empty JSON array of story IDs');

        $storyModel = $this->loadModel('story');
        $_POST = array('stories' => array(), 'products' => array());
        foreach($storyIDs as $storyID)
        {
            $storyID = (int)$storyID;
            $story   = $storyModel->getById($storyID);
            if(empty($story)) $this->deny('STORY_NOT_FOUND: ' . $storyID);
            $_POST['stories'][] = $storyID;
            $_POST['products'][$storyID] = (int)$story->product;
        }

        $projectModel->linkStory($projectID);
        if(dao::isError()) $this->daoFail();
        $mcp->auditLog('linkProjectStories', "project#$projectID stories=" . join(',', $_POST['stories']));
        $this->ok('linked');
    }

    public function unlinkProjectStory()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $projectID = (int)$mcp->getQueryParam('projectID');
        if(!$projectID) $this->deny('projectID required');
        $storyID = (int)$mcp->getQueryParam('storyID');
        if(!$storyID) $this->deny('storyID required');

        $projectModel = $this->loadModel('project');
        if(empty($projectModel->getById($projectID))) $this->deny('PROJECT_NOT_FOUND');

        $projectModel->unlinkStory($projectID, $storyID);
        if(dao::isError()) $this->daoFail();
        $mcp->auditLog('unlinkProjectStory', "project#$projectID story#$storyID");
        $this->ok('unlinked');
    }

    public function subdivideStory()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $storyID = (int)$mcp->getQueryParam('storyID');
        if(!$storyID) $this->deny('storyID required');

        $storyModel = $this->loadModel('story');
        $parent     = $storyModel->getById($storyID);
        if(empty($parent)) $this->deny('STORY_NOT_FOUND');

        $children = json_decode((string)$this->post->children, true);
        if(!is_array($children) or empty($children)) $this->deny('children must be a non-empty JSON array of {title, spec?, verify?, module?, pri?, estimate?}');

        $childIDs = array();
        foreach($children as $child)
        {
            if(!is_array($child) or empty($child['title'])) $this->deny('children[].title required');

            $_POST = array('product' => (int)$parent->product, 'title' => (string)$child['title'], 'needNotReview' => 1);
            if(isset($child['spec']))     $_POST['spec']     = (string)$child['spec'];
            if(isset($child['verify']))   $_POST['verify']   = (string)$child['verify'];
            if(!empty($child['module']))  $_POST['module']   = (int)$child['module'];
            if(isset($child['pri']))      $_POST['pri']      = (int)$child['pri'];
            if(isset($child['estimate'])) $_POST['estimate'] = $child['estimate'];

            $result = $storyModel->create(0, 0, '');
            if(dao::isError()) $this->daoFail();
            if(!is_array($result) or empty($result['id'])) $this->deny('CREATE_FAILED: ' . $child['title']);

            $childIDs[] = (int)$result['id'];
            $this->loadModel('action')->create('story', $result['id'], 'Opened');
        }

        $mails = array();
        foreach($childIDs as $childID)
        {
            $item          = new stdclass();
            $item->storyID = $childID;
            $mails[]       = $item;
        }
        /* subdivide 内部依赖 $this->action 懒加载属性（原生流程由 batchCreate 顺带挂载），此处显式挂载 */
        $storyModel->loadModel('action');
        $storyModel->subdivide($storyID, $mails);
        if(dao::isError()) $this->daoFail();

        $mcp->auditLog('subdivideStory', "story#$storyID children=" . join(',', $childIDs));
        $this->ok(array('parentID' => $storyID, 'childIDs' => $childIDs));
    }

    public function linkTesttaskCases()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $taskID = (int)$mcp->getQueryParam('taskID');
        if(!$taskID) $this->deny('taskID required');

        $testtaskModel = $this->loadModel('testtask');
        if(empty($testtaskModel->getById($taskID))) $this->deny('TESTTASK_NOT_FOUND');

        $cases = json_decode((string)$this->post->cases, true);
        if(!is_array($cases) or empty($cases)) $this->deny('cases must be a non-empty JSON array of case IDs');

        $caseModel = $this->loadModel('testcase');
        $_POST = array('cases' => array(), 'versions' => array());
        foreach($cases as $caseID)
        {
            $caseID = (int)$caseID;
            $case   = $caseModel->getById($caseID);
            if(empty($case)) $this->deny('CASE_NOT_FOUND: ' . $caseID);
            $_POST['cases'][] = $caseID;
            $_POST['versions'][$caseID] = (int)$case->version;
        }

        $type = trim((string)$mcp->getQueryParam('type'));
        if($type === '') $type = 'all';

        $testtaskModel->linkCase($taskID, $type);
        if(dao::isError()) $this->daoFail();
        $mcp->auditLog('linkTesttaskCases', "testtask#$taskID cases=" . count($cases));
        $this->ok('linked');
    }

    public function listTestruns()
    {
        $mcp    = $this->loadModel('mcp');
        $taskID = (int)$mcp->getQueryParam('taskID');
        if(!$taskID) $this->deny('taskID required');

        $runs = $this->dao->select('t1.id, t1.case, t1.version, t1.status, t2.title AS caseTitle')
            ->from(TABLE_TESTRUN)->alias('t1')
            ->leftJoin(TABLE_CASE)->alias('t2')->on('t1.case = t2.id')
            ->where('t1.task')->eq($taskID)
            ->orderBy('t1.id_desc')
            ->fetchAll();

        $result = array();
        foreach($runs as $run)
        {
            $result[] = array('id' => (int)$run->id, 'case' => (int)$run->case, 'caseTitle' => $run->caseTitle, 'version' => (int)$run->version, 'status' => $run->status);
        }
        $this->ok($result);
    }

    public function recordTestResult()
    {
        $this->writeGuard();
        $mcp = $this->actor();

        $runID = (int)$mcp->getQueryParam('runID');
        if(!$runID) $this->deny('runID required');

        $run = $this->dao->select('id, `case`, version')->from(TABLE_TESTRUN)->where('id')->eq($runID)->fetch();
        if(empty($run)) $this->deny('RUN_NOT_FOUND');

        $steps = json_decode((string)$this->post->steps, true);
        if(!is_array($steps) or empty($steps)) $this->deny('steps must be a non-empty JSON array of {stepID?, result, real?}');

        $_POST = array('case' => (int)$run->case, 'version' => (int)$run->version, 'steps' => array(), 'reals' => array());
        foreach($steps as $step)
        {
            $stepID = isset($step['stepID']) ? (int)$step['stepID'] : 0;
            $_POST['steps'][$stepID] = isset($step['result']) ? (string)$step['result'] : 'pass';
            $_POST['reals'][$stepID] = isset($step['real']) ? (string)$step['real'] : '';
        }

        /* 整体结果跟随首个非 pass/n/a 的步骤（与原生一致） */
        $_POST['result'] = 'pass';
        foreach($_POST['steps'] as $stepResult)
        {
            if($stepResult !== 'pass' and $stepResult !== 'n/a')
            {
                $_POST['result'] = $stepResult;
                break;
            }
        }

        $resultID = $this->loadModel('testtask')->createResult($runID);
        if(dao::isError()) $this->daoFail();
        $mcp->auditLog('recordTestResult', "run#$runID result=" . $_POST['result']);
        $this->ok(is_numeric($resultID) ? array('resultID' => (int)$resultID) : 'recorded');
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
