<?php
require dirname(__FILE__) . '/config.php';
require dirname(__FILE__) . '/client.php';
require dirname(__FILE__) . '/gitlog.php';

function ztJson($data)
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function ztRespond($id, $result)
{
    if($id === null) return;
    echo json_encode(array('jsonrpc' => '2.0', 'id' => $id, 'result' => $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    fflush(STDOUT);
}

function ztRespondError($id, $code, $message)
{
    if($id === null) return;
    echo json_encode(array('jsonrpc' => '2.0', 'id' => $id, 'error' => array('code' => $code, 'message' => $message))), "\n";
    fflush(STDOUT);
}

function ztParam($args, $name, $default = null)
{
    return isset($args[$name]) ? $args[$name] : $default;
}

function ztActorParams(&$params, $args)
{
    if(!empty($args['actor'])) $params['actor'] = (string)$args['actor'];
}

function ztToolResult($name, $args)
{
    $config = ztConfig();
    if($config['secret'] === '') throw new Exception('MCP_SECRET 未配置：请在 zentao-mcp/.env 中设置');

    switch($name)
    {
        case 'zentao_ping':
            return ztJson(ztCall('ping'));

        /* ============ 任务 ============ */

        case 'zentao_get_task':
            if(empty($args['taskID'])) throw new Exception('taskID 必填');
            return ztJson(ztCall('getTask', array('taskID' => (int)$args['taskID'])));

        case 'zentao_list_tasks':
        {
            $params = array();
            if(!empty($args['account'])) $params['account'] = (string)$args['account'];
            if(!empty($args['status']))  $params['status']  = (string)$args['status'];
            return ztJson(ztCall('listTasks', $params));
        }

        case 'zentao_search_tasks':
        {
            if(empty($args['keyword'])) throw new Exception('keyword 必填');
            return ztJson(ztCall('searchTasks', array('keyword' => (string)$args['keyword'])));
        }

        case 'zentao_get_task_history':
            if(empty($args['taskID'])) throw new Exception('taskID 必填');
            return ztJson(ztCall('getTaskHistory', array('taskID' => (int)$args['taskID'])));

        case 'zentao_get_task_estimates':
            if(empty($args['taskID'])) throw new Exception('taskID 必填');
            return ztJson(ztCall('getTaskEstimates', array('taskID' => (int)$args['taskID'])));

        case 'zentao_create_task':
        {
            if(empty($args['projectID'])) throw new Exception('projectID 必填');
            if(empty($args['name']))      throw new Exception('name 必填');
            $params = array('projectID' => (int)$args['projectID']);
            ztActorParams($params, $args);
            $post = array('name' => (string)$args['name']);
            foreach(array('type', 'estimate', 'left', 'estStarted', 'deadline', 'desc', 'pri', 'story', 'module', 'assignedTo', 'comment') as $key)
            {
                if(isset($args[$key])) $post[$key] = $args[$key];
            }
            return ztJson(ztCall('createTask', $params, $post));
        }

        case 'zentao_task_flow':
        {
            if(empty($args['taskID'])) throw new Exception('taskID 必填');
            if(empty($args['action'])) throw new Exception('action 必填');
            $params = array('taskID' => (int)$args['taskID'], 'action' => (string)$args['action']);
            ztActorParams($params, $args);
            $post = array();
            foreach(array('left', 'assignedTo', 'comment') as $key)
            {
                if(isset($args[$key])) $post[$key] = $args[$key];
            }
            return ztJson(ztCall('flowTask', $params, $post));
        }

        case 'zentao_assign_task':
        {
            if(empty($args['taskID']))     throw new Exception('taskID 必填');
            if(empty($args['assignedTo'])) throw new Exception('assignedTo 必填');
            $params = array('taskID' => (int)$args['taskID']);
            ztActorParams($params, $args);
            $post = array('assignedTo' => (string)$args['assignedTo']);
            if(isset($args['comment'])) $post['comment'] = (string)$args['comment'];
            return ztJson(ztCall('assignTask', $params, $post));
        }

        case 'zentao_start_task':
        {
            if(empty($args['taskID'])) throw new Exception('taskID 必填');
            $params = array('taskID' => (int)$args['taskID']);
            ztActorParams($params, $args);
            $post = array(
                'consumed'    => isset($args['consumed']) ? $args['consumed'] : 0,
                'left'        => isset($args['left']) ? $args['left'] : 0,
                'realStarted' => !empty($args['realStarted']) ? (string)$args['realStarted'] : date('Y-m-d'),
                'comment'     => isset($args['comment']) ? (string)$args['comment'] : '',
            );
            return ztJson(ztCall('startTask', $params, $post));
        }

        case 'zentao_finish_task':
        {
            if(empty($args['taskID'])) throw new Exception('taskID 必填');
            if(!isset($args['consumed'])) throw new Exception('consumed 必填（任务总消耗工时）');
            $params = array('taskID' => (int)$args['taskID']);
            ztActorParams($params, $args);
            $post = array(
                'consumed' => $args['consumed'],
                'comment'  => !empty($args['comment']) ? (string)$args['comment'] : '完成',
            );
            return ztJson(ztCall('finishTask', $params, $post));
        }

        case 'zentao_record_effort':
        {
            if(empty($args['taskID'])) throw new Exception('taskID 必填');
            if(!isset($args['consumed'])) throw new Exception('consumed 必填（本次消耗工时）');
            if(!isset($args['left'])) throw new Exception('left 必填（剩余工时，0 表示将完成）');
            $params = array('taskID' => (int)$args['taskID']);
            ztActorParams($params, $args);
            $post = array(
                'consumed' => $args['consumed'],
                'left'     => $args['left'],
                'date'     => !empty($args['date']) ? (string)$args['date'] : date('Y-m-d'),
                'work'     => isset($args['work']) ? (string)$args['work'] : '',
            );
            return ztJson(ztCall('recordEffort', $params, $post));
        }

        case 'zentao_add_comment':
        {
            if(empty($args['taskID'])) throw new Exception('taskID 必填');
            if(empty($args['comment'])) throw new Exception('comment 必填');
            $params = array('taskID' => (int)$args['taskID']);
            ztActorParams($params, $args);
            return ztJson(ztCall('addComment', $params, array('comment' => (string)$args['comment'])));
        }

        /* ============ 需求 ============ */

        case 'zentao_get_story':
            if(empty($args['storyID'])) throw new Exception('storyID 必填');
            return ztJson(ztCall('getStory', array('storyID' => (int)$args['storyID'])));

        case 'zentao_list_stories':
        {
            $params = array();
            if(!empty($args['productID'])) $params['productID'] = (int)$args['productID'];
            if(!empty($args['status']))    $params['status']    = (string)$args['status'];
            return ztJson(ztCall('listStories', $params));
        }

        /* ============ Bug ============ */

        case 'zentao_get_bug':
            if(empty($args['bugID'])) throw new Exception('bugID 必填');
            return ztJson(ztCall('getBug', array('bugID' => (int)$args['bugID'])));

        case 'zentao_list_bugs':
        {
            $params = array();
            if(!empty($args['productID'])) $params['productID'] = (int)$args['productID'];
            if(!empty($args['status']))    $params['status']    = (string)$args['status'];
            return ztJson(ztCall('listBugs', $params));
        }

        case 'zentao_create_bug':
        {
            if(empty($args['title']))   throw new Exception('title 必填');
            if(empty($args['product'])) throw new Exception('product 必填');
            $params = array();
            ztActorParams($params, $args);
            $post = array('title' => (string)$args['title'], 'product' => (int)$args['product']);
            foreach(array('openedBuild', 'module', 'severity', 'pri', 'type', 'steps', 'assignedTo', 'project', 'story', 'keywords') as $key)
            {
                if(isset($args[$key])) $post[$key] = $args[$key];
            }
            return ztJson(ztCall('createBug', $params, $post));
        }

        case 'zentao_resolve_bug':
        {
            if(empty($args['bugID']))      throw new Exception('bugID 必填');
            if(empty($args['resolution'])) throw new Exception('resolution 必填（如 fixed/duplicate/bydesign/willnotfix/notrepro/postponed）');
            $params = array('bugID' => (int)$args['bugID']);
            ztActorParams($params, $args);
            $post = array('resolution' => (string)$args['resolution']);
            foreach(array('resolvedBuild', 'comment', 'duplicateBug') as $key)
            {
                if(isset($args[$key])) $post[$key] = $args[$key];
            }
            return ztJson(ztCall('resolveBug', $params, $post));
        }

        case 'zentao_close_bug':
        {
            if(empty($args['bugID'])) throw new Exception('bugID 必填');
            $params = array('bugID' => (int)$args['bugID']);
            ztActorParams($params, $args);
            $post = array();
            if(isset($args['comment'])) $post['comment'] = (string)$args['comment'];
            return ztJson(ztCall('closeBug', $params, $post));
        }

        /* ============ 项目与产品 ============ */

        case 'zentao_list_projects':
            return ztJson(ztCall('listProjects'));

        case 'zentao_list_products':
            return ztJson(ztCall('listProducts'));

        case 'zentao_effort_report':
        {
            $params = array();
            if(!empty($args['account']))  $params['account']  = (string)$args['account'];
            if(!empty($args['dateFrom'])) $params['dateFrom'] = (string)$args['dateFrom'];
            if(!empty($args['dateTo']))   $params['dateTo']   = (string)$args['dateTo'];
            return ztJson(ztCall('effortReport', $params));
        }

        /* ============ 提交 ============ */

        case 'zentao_get_git_commits':
        {
            $repo    = !empty($args['repoPath']) ? (string)$args['repoPath'] : $config['repo'];
            $commits = ztGitCommits($repo, isset($args['since']) ? (string)$args['since'] : '', isset($args['limit']) ? (int)$args['limit'] : 20);

            $result = array();
            foreach($commits as $commit)
            {
                $result[] = array(
                    'hash'          => $commit['hash'],
                    'name'          => $commit['name'],
                    'email'         => $commit['email'],
                    'authorAccount' => ztAuthorAccount($commit['name'], $commit['email']),
                    'date'          => $commit['date'],
                    'msg'           => $commit['msg'],
                    'files'         => $commit['files'],
                );
            }
            return ztJson(array('count' => count($result), 'commits' => $result));
        }

        case 'zentao_sync_commits':
        {
            $repo  = !empty($args['repoPath']) ? (string)$args['repoPath'] : $config['repo'];
            $since = !empty($args['since']) ? (string)$args['since'] : ztReadState($repo);
            $commits = ztGitCommits($repo, $since, isset($args['limit']) ? (int)$args['limit'] : 20);
            if(empty($commits)) return '没有需要同步的新提交。';

            $lines = array();
            foreach($commits as $commit) $lines[] = $commit['lines'];

            $params = array();
            ztActorParams($params, $args);
            $resp = ztCall('syncCommits', $params, array('commits' => json_encode($lines), 'repoRoot' => $repo));
            if(!isset($resp['code']) or $resp['code'] !== 0) throw new Exception('syncCommits 失败: ' . ztJson($resp));

            ztWriteState($repo, $commits[0]['hash']);
            return ztJson($resp);
        }

        default:
            throw new Exception('未知工具: ' . $name);
    }
}

function ztToolSchema($name, $description, $properties, $required = array())
{
    $schema = array('type' => 'object', 'properties' => empty($properties) ? new stdclass() : $properties);
    if(!empty($required)) $schema['required'] = $required;
    return array('name' => $name, 'description' => $description, 'inputSchema' => $schema);
}

$ztActorProp     = array('type' => 'string', 'description' => '操作人禅道账号（可选，默认配置账号；多人协作时传对应开发者账号）');
$ztTaskIDProp    = array('type' => 'integer', 'description' => '任务 ID');
$ztCommentProp   = array('type' => 'string', 'description' => '备注（可选）');

$ztTools = array(
    ztToolSchema('zentao_ping', '测试 zentao-mcp 与禅道服务的连通性。', array()),

    ztToolSchema('zentao_get_task', '查询禅道任务详情（名称/状态/工时/指派等）。', array(
        'taskID' => $ztTaskIDProp,
    ), array('taskID')),

    ztToolSchema('zentao_list_tasks', '列出禅道任务（默认最近 50 条；可按负责人和状态过滤）。状态可选 wait/doing/done/closed/cancel/pause。', array(
        'account' => array('type' => 'string', 'description' => '按负责人账号过滤（可选）'),
        'status'  => array('type' => 'string', 'description' => '按状态过滤，多状态用逗号分隔（可选）'),
    )),

    ztToolSchema('zentao_search_tasks', '按名称关键词搜索禅道任务。', array(
        'keyword' => array('type' => 'string', 'description' => '名称关键词'),
    ), array('keyword')),

    ztToolSchema('zentao_get_task_history', '查询任务的历史操作记录（action 流：谁在何时做了什么）。', array(
        'taskID' => $ztTaskIDProp,
    ), array('taskID')),

    ztToolSchema('zentao_get_task_estimates', '查询任务的工时明细记录。', array(
        'taskID' => $ztTaskIDProp,
    ), array('taskID')),

    ztToolSchema('zentao_create_task', '创建禅道任务。projectID 可通过 zentao_list_projects 获取。', array(
        'projectID'  => array('type' => 'integer', 'description' => '所属项目 ID'),
        'name'       => array('type' => 'string', 'description' => '任务名称'),
        'type'       => array('type' => 'string', 'description' => '任务类型（design/devel/test/study/discuss/ui/affair，默认 devel）'),
        'assignedTo' => array('type' => 'string', 'description' => '指派给（可选，默认操作人）'),
        'estimate'   => array('type' => 'number', 'description' => '预估工时（可选）'),
        'left'       => array('type' => 'number', 'description' => '剩余工时（可选，默认等于预估）'),
        'estStarted' => array('type' => 'string', 'description' => '预计开始日期 YYYY-MM-DD（可选）'),
        'deadline'   => array('type' => 'string', 'description' => '截止日期 YYYY-MM-DD（可选）'),
        'desc'       => array('type' => 'string', 'description' => '任务描述（可选）'),
        'pri'        => array('type' => 'integer', 'description' => '优先级 0-4（可选）'),
        'story'      => array('type' => 'integer', 'description' => '关联需求 ID（可选）'),
        'module'     => array('type' => 'integer', 'description' => '模块 ID（可选）'),
        'actor'      => $ztActorProp,
        'comment'    => $ztCommentProp,
    ), array('projectID', 'name')),

    ztToolSchema('zentao_task_flow', '任务生命周期流转：pause 暂停 / restart 重启 / close 关闭 / activate 激活（需 left）/ cancel 取消。', array(
        'taskID'     => $ztTaskIDProp,
        'action'     => array('type' => 'string', 'description' => 'pause | restart | close | activate | cancel'),
        'left'       => array('type' => 'number', 'description' => '剩余工时（activate 时必填）'),
        'assignedTo' => array('type' => 'string', 'description' => '指派给（activate 可选，默认任务创建人）'),
        'actor'      => $ztActorProp,
        'comment'    => $ztCommentProp,
    ), array('taskID', 'action')),

    ztToolSchema('zentao_assign_task', '转派任务给其他成员。', array(
        'taskID'     => $ztTaskIDProp,
        'assignedTo' => array('type' => 'string', 'description' => '转派给（禅道账号）'),
        'actor'      => $ztActorProp,
        'comment'    => $ztCommentProp,
    ), array('taskID', 'assignedTo')),

    ztToolSchema('zentao_start_task', '开始任务（wait→doing）。等待中的任务被提交引用时应调用。', array(
        'taskID'      => $ztTaskIDProp,
        'consumed'    => array('type' => 'number', 'description' => '累计消耗工时（可选，默认 0）'),
        'left'        => array('type' => 'number', 'description' => '剩余工时（可选，默认 0；为 0 时任务直接完结）'),
        'realStarted' => array('type' => 'string', 'description' => '实际开始日期 YYYY-MM-DD（可选，默认今天）'),
        'actor'       => $ztActorProp,
        'comment'     => $ztCommentProp,
    ), array('taskID')),

    ztToolSchema('zentao_finish_task', '完成任务（doing→done）。提交信息含 finish/done/完成 等完结意图时调用。', array(
        'taskID'   => $ztTaskIDProp,
        'consumed' => array('type' => 'number', 'description' => '任务总消耗工时（必填，需 >= 已有消耗）'),
        'actor'    => $ztActorProp,
        'comment'  => array('type' => 'string', 'description' => '完成备注（可选，默认"完成"）'),
    ), array('taskID', 'consumed')),

    ztToolSchema('zentao_record_effort', '记录工时（不改任务状态，除非 left 归零则自动完成）。', array(
        'taskID'   => $ztTaskIDProp,
        'consumed' => array('type' => 'number', 'description' => '本次消耗工时（必填）'),
        'left'     => array('type' => 'number', 'description' => '剩余工时（必填，0 表示将完成）'),
        'date'     => array('type' => 'string', 'description' => '日期 YYYY-MM-DD（可选，默认今天）'),
        'work'     => array('type' => 'string', 'description' => '工作说明（可选）'),
        'actor'    => $ztActorProp,
    ), array('taskID', 'consumed', 'left')),

    ztToolSchema('zentao_add_comment', '给任务添加备注（写入任务历史记录）。', array(
        'taskID'  => $ztTaskIDProp,
        'comment' => array('type' => 'string', 'description' => '备注内容'),
        'actor'   => $ztActorProp,
    ), array('taskID', 'comment')),

    ztToolSchema('zentao_get_story', '查询禅道需求（story）详情。', array(
        'storyID' => array('type' => 'integer', 'description' => '需求 ID'),
    ), array('storyID')),

    ztToolSchema('zentao_list_stories', '列出禅道需求（默认最近 50 条；可按产品和状态过滤）。', array(
        'productID' => array('type' => 'integer', 'description' => '产品 ID（可选）'),
        'status'    => array('type' => 'string', 'description' => '状态过滤（可选，如 active/closed）'),
    )),

    ztToolSchema('zentao_get_bug', '查询禅道 Bug 详情。', array(
        'bugID' => array('type' => 'integer', 'description' => 'Bug ID'),
    ), array('bugID')),

    ztToolSchema('zentao_list_bugs', '列出禅道 Bug（默认最近 50 条；可按产品和状态过滤）。', array(
        'productID' => array('type' => 'integer', 'description' => '产品 ID（可选）'),
        'status'    => array('type' => 'string', 'description' => '状态过滤（可选，如 active/resolved/closed）'),
    )),

    ztToolSchema('zentao_create_bug', '创建禅道 Bug。product 可通过 zentao_list_products 获取。', array(
        'title'       => array('type' => 'string', 'description' => 'Bug 标题'),
        'product'     => array('type' => 'integer', 'description' => '所属产品 ID'),
        'openedBuild' => array('type' => 'string', 'description' => '影响版本（可选，默认 trunk）'),
        'module'      => array('type' => 'integer', 'description' => '模块 ID（可选）'),
        'severity'    => array('type' => 'integer', 'description' => '严重程度 1-4（可选）'),
        'pri'         => array('type' => 'integer', 'description' => '优先级 1-4（可选）'),
        'type'        => array('type' => 'string', 'description' => 'Bug 类型（codeerror/config/install/security/performance/standard/automation/designdefect/othersaffair）'),
        'steps'       => array('type' => 'string', 'description' => '重现步骤（可选）'),
        'assignedTo'  => array('type' => 'string', 'description' => '指派给（可选）'),
        'project'     => array('type' => 'integer', 'description' => '所属项目（可选）'),
        'story'       => array('type' => 'integer', 'description' => '相关需求（可选）'),
        'actor'       => $ztActorProp,
    ), array('title', 'product')),

    ztToolSchema('zentao_resolve_bug', '解决 Bug（active→resolved）。', array(
        'bugID'         => array('type' => 'integer', 'description' => 'Bug ID'),
        'resolution'    => array('type' => 'string', 'description' => '解决方案：fixed/bydesign/duplicate/external/postponed/willnotfix/notrepro/worked'),
        'resolvedBuild' => array('type' => 'string', 'description' => '解决版本（可选）'),
        'comment'       => $ztCommentProp,
        'actor'         => $ztActorProp,
    ), array('bugID', 'resolution')),

    ztToolSchema('zentao_close_bug', '关闭 Bug（resolved→closed）。', array(
        'bugID'   => array('type' => 'integer', 'description' => 'Bug ID'),
        'comment' => $ztCommentProp,
        'actor'   => $ztActorProp,
    ), array('bugID')),

    ztToolSchema('zentao_list_projects', '列出禅道项目（建任务前需要 projectID 时使用）。', array()),

    ztToolSchema('zentao_list_products', '列出禅道产品（建 Bug 前需要 productID 时使用）。', array()),

    ztToolSchema('zentao_effort_report', '工时报表：按账号与日期范围聚合工时记录（默认今天、全部账号）。', array(
        'account'  => array('type' => 'string', 'description' => '账号过滤（可选）'),
        'dateFrom' => array('type' => 'string', 'description' => '起始日期 YYYY-MM-DD（可选，默认今天）'),
        'dateTo'   => array('type' => 'string', 'description' => '截止日期 YYYY-MM-DD（可选，默认同 dateFrom）'),
    )),

    ztToolSchema('zentao_get_git_commits', '读取本地 git 仓库的提交记录（只读，不同步禅道）。返回 hash/作者/邮箱/authorAccount（按 GIT_AUTHOR_MAP 映射的禅道账号）/时间/消息/文件列表。', array(
        'repoPath' => array('type' => 'string', 'description' => 'git 仓库路径（可选，默认配置的仓库）'),
        'since'    => array('type' => 'string', 'description' => '起始 commit hash，读取该点之后的提交（可选）'),
        'limit'    => array('type' => 'integer', 'description' => '无 since 时最多读取的提交数，默认 20'),
    )),

    ztToolSchema('zentao_sync_commits', '把本地 git 提交同步到禅道：解析提交信息中的 task #N / story #N / bug #N 标记，在对应对象上写入关联记录（gitcommited action，署名恒为 git 作者）。默认从上次同步点增量同步。建议工作流：先 get_git_commits 查看新提交 → 用 start_task/record_effort/finish_task 按提交内容更新任务状态与工时（actor 用 authorAccount）→ 最后 sync_commits 写入关联。', array(
        'repoPath' => array('type' => 'string', 'description' => 'git 仓库路径（可选，默认配置的仓库）'),
        'actor'    => $ztActorProp,
        'since'    => array('type' => 'string', 'description' => '起始 commit hash（可选，默认上次同步点）'),
        'limit'    => array('type' => 'integer', 'description' => '无同步点时最多同步的提交数，默认 20'),
    )),
);

$ztKeepalive = in_array('--keepalive', isset($argv) ? $argv : array());

while(true)
{
    $line = fgets(STDIN);
    if($line === false)
    {
        if(!feof(STDIN)) continue;                  // 瞬时读取错误，重试
        if($ztKeepalive) { sleep(3); continue; }    // stdin 被关闭：保活等待客户端复用（Qoder MCP 进程模型）
        break;                                      // stdin EOF：常规退出
    }

    $line = trim($line);
    if($line === '') continue;

    $req = json_decode($line, true);
    if(!is_array($req) or !isset($req['method'])) continue;

    $method = (string)$req['method'];
    $id     = isset($req['id']) ? $req['id'] : null;

    try
    {
        if($method === 'initialize')
        {
            ztRespond($id, array(
                'protocolVersion' => '2024-11-05',
                'capabilities'    => array('tools' => new stdclass()),
                'serverInfo'      => array('name' => 'zentao-mcp', 'version' => '0.2.0'),
            ));
        }
        elseif($method === 'notifications/initialized' or $method === 'notifications/cancelled')
        {
            // 通知无需响应
        }
        elseif($method === 'ping')
        {
            ztRespond($id, new stdclass());
        }
        elseif($method === 'resources/list')
        {
            ztRespond($id, array('resources' => array()));
        }
        elseif($method === 'prompts/list')
        {
            ztRespond($id, array('prompts' => array()));
        }
        elseif($method === 'tools/list')
        {
            ztRespond($id, array('tools' => $ztTools));
        }
        elseif($method === 'tools/call')
        {
            $name = isset($req['params']['name']) ? (string)$req['params']['name'] : '';
            $args = isset($req['params']['arguments']) ? $req['params']['arguments'] : array();
            if(!is_array($args)) $args = array();
            try
            {
                $text = ztToolResult($name, $args);
                ztRespond($id, array('content' => array(array('type' => 'text', 'text' => $text))));
            }
            catch(Exception $e)
            {
                ztRespond($id, array('content' => array(array('type' => 'text', 'text' => $e->getMessage())), 'isError' => true));
            }
        }
        else
        {
            ztRespondError($id, -32601, 'Method not found: ' . $method);
        }
    }
    catch(Throwable $e)
    {
        fwrite(STDERR, '[zentao-mcp] ' . $e->getMessage() . "\n");
        if($id !== null) ztRespondError($id, -32603, 'Internal error: ' . $e->getMessage());
    }
}
