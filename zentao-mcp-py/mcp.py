#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""zentao-mcp Python client: stdio MCP shell for ZenTao (zero third-party deps).

Requires: python3 >= 3.7, git. Configuration is read from .env next to this file:
    ZENTAO_URL, MCP_SECRET, MCP_REPO_PATH
"""
import json
import os
import subprocess
import sys
import time
import hashlib
from datetime import date
from urllib.error import HTTPError, URLError
from urllib.parse import quote_plus
from urllib.request import Request, urlopen

VERSION = '0.3.0'


def load_env():
    path = os.path.join(os.path.dirname(os.path.abspath(__file__)), '.env')
    env = {}
    if os.path.exists(path):
        with open(path, encoding='utf-8') as fh:
            for line in fh:
                line = line.strip()
                if not line or line[0] in '#;' or '=' not in line:
                    continue
                key, value = line.split('=', 1)
                env[key.strip()] = value.strip()
    return env


ENV = load_env()
BASE_URL = (ENV.get('ZENTAO_URL') or 'http://dev.wenyinos.test:8080').rstrip('/')
SECRET = ENV.get('MCP_SECRET', '')
REPO_DEFAULT = ENV.get('MCP_REPO_PATH') or os.getcwd()


def php_urlencode(value):
    # byte-identical to PHP urlencode(): '+' for space, uppercase hex, '~' -> %7E
    return quote_plus(str(value), safe='').replace('~', '%7E')


def zt_call(method, params=None, post=None):
    if SECRET == '':
        raise RuntimeError('MCP_SECRET 未配置：请在 zentao-mcp-py/.env 中设置')

    query_params = dict(params or {})
    query_params['ts'] = int(time.time())
    query = '&'.join('%s=%s' % (php_urlencode(k), php_urlencode(v)) for k, v in query_params.items())
    sign = hashlib.md5((hashlib.md5(query.encode('utf-8')).hexdigest() + SECRET).encode('utf-8')).hexdigest()

    url = '%s/mcp-%s.json?%s&token=%s' % (BASE_URL, method, query, sign)
    if post:
        body = '&'.join('%s=%s' % (php_urlencode(k), php_urlencode(v)) for k, v in post.items()).encode('utf-8')
        req = Request(url, data=body, method='POST', headers={'Content-Type': 'application/x-www-form-urlencoded'})
    else:
        req = Request(url, method='GET')

    try:
        with urlopen(req, timeout=30) as resp:
            raw = resp.read()
    except HTTPError as err:
        raw = err.read()
    except URLError as err:
        raise RuntimeError('HTTP error: %s' % err.reason)

    try:
        data = json.loads(raw.decode('utf-8', 'replace'))
    except ValueError:
        raise RuntimeError('Non-JSON response: %s' % raw.decode('utf-8', 'replace')[:300])
    if not isinstance(data, dict):
        raise RuntimeError('Non-JSON response: %s' % raw.decode('utf-8', 'replace')[:300])
    return data


# ============ git ============

def zt_git_commits(repo_path, since_hash='', limit=20):
    if since_hash:
        probe = subprocess.run(
            ['git', '-C', repo_path, 'cat-file', '-e', since_hash + '^{commit}'],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        if probe.returncode != 0:
            since_hash = ''

    cmd = ['git', '-C', repo_path, 'log', '--pretty=format:%x01%an*_*%ad*_*%H*_*%s*_*%ae', '--date=iso', '--name-only']
    if since_hash:
        cmd.append(since_hash + '..HEAD')
    else:
        cmd.append('--max-count=%d' % int(limit))

    output = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL)
    text = output.stdout.decode('utf-8', 'replace')
    if not text:
        return []

    commits = []
    for block in text.split('\x01'):
        block = block.strip()
        if not block:
            continue
        lines = block.split('\n')
        header = lines.pop(0)

        parts = header.split('*_*')
        email = parts.pop() if len(parts) > 4 and '@' in parts[-1] else ''
        name = parts.pop(0)
        date_str = parts.pop(0)
        hash_str = parts.pop(0)
        msg = '*_*'.join(parts).replace('*_*', ' * * ')

        files = [f.strip() for f in lines if f.strip()]
        commits.append({
            'hash': hash_str,
            'name': name,
            'email': email,
            'date': date_str,
            'msg': msg,
            'files': files,
        })
    return commits


# ============ tools ============

def zt_actor_params(params, args):
    if args.get('actor'):
        params['actor'] = str(args['actor'])


def zt_tool_result(name, args):
    if name == 'zentao_ping':
        return zt_call('ping')

    # ---- 任务 ----
    if name == 'zentao_get_task':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        return zt_call('getTask', {'taskID': int(args['taskID'])})

    if name == 'zentao_list_tasks':
        params = {}
        if args.get('account'):
            params['account'] = str(args['account'])
        if args.get('status'):
            params['status'] = str(args['status'])
        return zt_call('listTasks', params)

    if name == 'zentao_search_tasks':
        if not args.get('keyword'):
            raise RuntimeError('keyword 必填')
        return zt_call('searchTasks', {'keyword': str(args['keyword'])})

    if name == 'zentao_get_task_history':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        return zt_call('getTaskHistory', {'taskID': int(args['taskID'])})

    if name == 'zentao_get_task_estimates':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        return zt_call('getTaskEstimates', {'taskID': int(args['taskID'])})

    if name == 'zentao_create_task':
        if not args.get('projectID'):
            raise RuntimeError('projectID 必填')
        if not args.get('name'):
            raise RuntimeError('name 必填')
        params = {'projectID': int(args['projectID'])}
        zt_actor_params(params, args)
        post = {'name': str(args['name'])}
        for key in ('type', 'estimate', 'left', 'estStarted', 'deadline', 'desc', 'pri', 'story', 'module', 'assignedTo', 'comment'):
            if key in args:
                post[key] = args[key]
        return zt_call('createTask', params, post)

    if name == 'zentao_task_flow':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        if not args.get('action'):
            raise RuntimeError('action 必填')
        params = {'taskID': int(args['taskID']), 'action': str(args['action'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('left', 'assignedTo', 'comment'):
            if key in args:
                post[key] = args[key]
        return zt_call('flowTask', params, post)

    if name == 'zentao_assign_task':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        if not args.get('assignedTo'):
            raise RuntimeError('assignedTo 必填')
        params = {'taskID': int(args['taskID'])}
        zt_actor_params(params, args)
        post = {'assignedTo': str(args['assignedTo'])}
        if 'comment' in args:
            post['comment'] = str(args['comment'])
        return zt_call('assignTask', params, post)

    if name == 'zentao_start_task':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        params = {'taskID': int(args['taskID'])}
        zt_actor_params(params, args)
        post = {
            'consumed': args.get('consumed', 0),
            'left': args.get('left', 0),
            'realStarted': str(args['realStarted']) if args.get('realStarted') else date.today().isoformat(),
            'comment': str(args.get('comment', '')),
        }
        return zt_call('startTask', params, post)

    if name == 'zentao_finish_task':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        if 'consumed' not in args:
            raise RuntimeError('consumed 必填（任务总消耗工时）')
        params = {'taskID': int(args['taskID'])}
        zt_actor_params(params, args)
        post = {
            'consumed': args['consumed'],
            'comment': str(args['comment']) if args.get('comment') else '完成',
        }
        return zt_call('finishTask', params, post)

    if name == 'zentao_record_effort':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        if 'consumed' not in args:
            raise RuntimeError('consumed 必填（本次消耗工时）')
        if 'left' not in args:
            raise RuntimeError('left 必填（剩余工时，0 表示将完成）')
        params = {'taskID': int(args['taskID'])}
        zt_actor_params(params, args)
        post = {
            'consumed': args['consumed'],
            'left': args['left'],
            'date': str(args['date']) if args.get('date') else date.today().isoformat(),
            'work': str(args.get('work', '')),
        }
        return zt_call('recordEffort', params, post)

    if name == 'zentao_add_comment':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        if not args.get('comment'):
            raise RuntimeError('comment 必填')
        params = {'taskID': int(args['taskID'])}
        zt_actor_params(params, args)
        return zt_call('addComment', params, {'comment': str(args['comment'])})

    # ---- 需求 ----
    if name == 'zentao_get_story':
        if not args.get('storyID'):
            raise RuntimeError('storyID 必填')
        return zt_call('getStory', {'storyID': int(args['storyID'])})

    if name == 'zentao_list_stories':
        params = {}
        if args.get('productID'):
            params['productID'] = int(args['productID'])
        if args.get('status'):
            params['status'] = str(args['status'])
        return zt_call('listStories', params)

    # ---- Bug ----
    if name == 'zentao_get_bug':
        if not args.get('bugID'):
            raise RuntimeError('bugID 必填')
        return zt_call('getBug', {'bugID': int(args['bugID'])})

    if name == 'zentao_list_bugs':
        params = {}
        if args.get('productID'):
            params['productID'] = int(args['productID'])
        if args.get('status'):
            params['status'] = str(args['status'])
        return zt_call('listBugs', params)

    if name == 'zentao_create_bug':
        if not args.get('title'):
            raise RuntimeError('title 必填')
        if not args.get('product'):
            raise RuntimeError('product 必填')
        params = {}
        zt_actor_params(params, args)
        post = {'title': str(args['title']), 'product': int(args['product'])}
        for key in ('openedBuild', 'module', 'severity', 'pri', 'type', 'steps', 'assignedTo', 'project', 'story', 'keywords'):
            if key in args:
                post[key] = args[key]
        return zt_call('createBug', params, post)

    if name == 'zentao_resolve_bug':
        if not args.get('bugID'):
            raise RuntimeError('bugID 必填')
        if not args.get('resolution'):
            raise RuntimeError('resolution 必填（如 fixed/duplicate/bydesign/willnotfix/notrepro/postponed）')
        params = {'bugID': int(args['bugID'])}
        zt_actor_params(params, args)
        post = {'resolution': str(args['resolution'])}
        for key in ('resolvedBuild', 'comment', 'duplicateBug'):
            if key in args:
                post[key] = args[key]
        return zt_call('resolveBug', params, post)

    if name == 'zentao_close_bug':
        if not args.get('bugID'):
            raise RuntimeError('bugID 必填')
        params = {'bugID': int(args['bugID'])}
        zt_actor_params(params, args)
        post = {}
        if 'comment' in args:
            post['comment'] = str(args['comment'])
        return zt_call('closeBug', params, post)

    # ---- 项目与产品 ----
    if name == 'zentao_list_projects':
        return zt_call('listProjects')

    if name == 'zentao_list_products':
        return zt_call('listProducts')

    if name == 'zentao_effort_report':
        params = {}
        if args.get('account'):
            params['account'] = str(args['account'])
        if args.get('dateFrom'):
            params['dateFrom'] = str(args['dateFrom'])
        if args.get('dateTo'):
            params['dateTo'] = str(args['dateTo'])
        return zt_call('effortReport', params)

    # ---- 提交 ----
    if name == 'zentao_get_git_commits':
        repo = str(args['repoPath']) if args.get('repoPath') else REPO_DEFAULT
        commits = zt_git_commits(repo, str(args.get('since', '')), int(args.get('limit', 20)))

        resp = zt_call('mapAuthors', {}, {'commits': json.dumps(commits, ensure_ascii=False)})
        if resp.get('code') != 0:
            raise RuntimeError('mapAuthors 失败: %s' % json.dumps(resp, ensure_ascii=False))
        enriched = resp.get('data', {}).get('commits', [])
        return {'count': len(enriched), 'commits': enriched}

    if name == 'zentao_sync_commits':
        repo = str(args['repoPath']) if args.get('repoPath') else REPO_DEFAULT
        since = str(args['since']) if args.get('since') else ''
        if not since:
            resp = zt_call('getSyncState', {'repoRoot': repo})
            if resp.get('code') != 0:
                raise RuntimeError('getSyncState 失败: %s' % json.dumps(resp, ensure_ascii=False))
            since = (resp.get('data') or {}).get('lastHash', '')

        commits = zt_git_commits(repo, since, int(args.get('limit', 20)))
        if not commits:
            return '没有需要同步的新提交。'

        params = {}
        zt_actor_params(params, args)
        resp = zt_call('syncCommits', params, {'commits': json.dumps(commits, ensure_ascii=False), 'repoRoot': repo})
        if resp.get('code') != 0:
            raise RuntimeError('syncCommits 失败: %s' % json.dumps(resp, ensure_ascii=False))
        return resp

    raise RuntimeError('未知工具: %s' % name)


# ============ MCP schemas ============

ACTOR_PROP = {'type': 'string', 'description': '操作人禅道账号（可选，默认配置账号；多人协作时传对应开发者账号）'}
TASKID_PROP = {'type': 'integer', 'description': '任务 ID'}
COMMENT_PROP = {'type': 'string', 'description': '备注（可选）'}


def zt_tool_schema(name, description, properties=None, required=None):
    schema = {'type': 'object', 'properties': properties if properties else {}}
    if required:
        schema['required'] = required
    return {'name': name, 'description': description, 'inputSchema': schema}


def build_tools():
    return [
        zt_tool_schema('zentao_ping', '测试 zentao-mcp 与禅道服务的连通性。'),

        zt_tool_schema('zentao_get_task', '查询禅道任务详情（名称/状态/工时/指派等）。',
                       {'taskID': TASKID_PROP}, ['taskID']),

        zt_tool_schema('zentao_list_tasks', '列出禅道任务（默认最近 50 条；可按负责人和状态过滤）。状态可选 wait/doing/done/closed/cancel/pause。',
                       {'account': {'type': 'string', 'description': '按负责人账号过滤（可选）'},
                        'status': {'type': 'string', 'description': '按状态过滤，多状态用逗号分隔（可选）'}}),

        zt_tool_schema('zentao_search_tasks', '按名称关键词搜索禅道任务。',
                       {'keyword': {'type': 'string', 'description': '名称关键词'}}, ['keyword']),

        zt_tool_schema('zentao_get_task_history', '查询任务的历史操作记录（action 流：谁在何时做了什么）。',
                       {'taskID': TASKID_PROP}, ['taskID']),

        zt_tool_schema('zentao_get_task_estimates', '查询任务的工时明细记录。',
                       {'taskID': TASKID_PROP}, ['taskID']),

        zt_tool_schema('zentao_create_task', '创建禅道任务。projectID 可通过 zentao_list_projects 获取。', {
            'projectID': {'type': 'integer', 'description': '所属项目 ID'},
            'name': {'type': 'string', 'description': '任务名称'},
            'type': {'type': 'string', 'description': '任务类型（design/devel/test/study/discuss/ui/affair，默认 devel）'},
            'assignedTo': {'type': 'string', 'description': '指派给（可选，默认操作人）'},
            'estimate': {'type': 'number', 'description': '预估工时（可选）'},
            'left': {'type': 'number', 'description': '剩余工时（可选，默认等于预估）'},
            'estStarted': {'type': 'string', 'description': '预计开始日期 YYYY-MM-DD（可选）'},
            'deadline': {'type': 'string', 'description': '截止日期 YYYY-MM-DD（可选）'},
            'desc': {'type': 'string', 'description': '任务描述（可选）'},
            'pri': {'type': 'integer', 'description': '优先级 0-4（可选）'},
            'story': {'type': 'integer', 'description': '关联需求 ID（可选）'},
            'module': {'type': 'integer', 'description': '模块 ID（可选）'},
            'actor': ACTOR_PROP,
            'comment': COMMENT_PROP,
        }, ['projectID', 'name']),

        zt_tool_schema('zentao_task_flow', '任务生命周期流转：pause 暂停 / restart 重启 / close 关闭 / activate 激活（需 left）/ cancel 取消。', {
            'taskID': TASKID_PROP,
            'action': {'type': 'string', 'description': 'pause | restart | close | activate | cancel'},
            'left': {'type': 'number', 'description': '剩余工时（activate 时必填）'},
            'assignedTo': {'type': 'string', 'description': '指派给（activate 可选，默认任务创建人）'},
            'actor': ACTOR_PROP,
            'comment': COMMENT_PROP,
        }, ['taskID', 'action']),

        zt_tool_schema('zentao_assign_task', '转派任务给其他成员。', {
            'taskID': TASKID_PROP,
            'assignedTo': {'type': 'string', 'description': '转派给（禅道账号）'},
            'actor': ACTOR_PROP,
            'comment': COMMENT_PROP,
        }, ['taskID', 'assignedTo']),

        zt_tool_schema('zentao_start_task', '开始任务（wait→doing）。等待中的任务被提交引用时应调用。', {
            'taskID': TASKID_PROP,
            'consumed': {'type': 'number', 'description': '累计消耗工时（可选，默认 0）'},
            'left': {'type': 'number', 'description': '剩余工时（可选，默认 0；为 0 时任务直接完结）'},
            'realStarted': {'type': 'string', 'description': '实际开始日期 YYYY-MM-DD（可选，默认今天）'},
            'actor': ACTOR_PROP,
            'comment': COMMENT_PROP,
        }, ['taskID']),

        zt_tool_schema('zentao_finish_task', '完成任务（doing→done）。提交信息含 finish/done/完成 等完结意图时调用。', {
            'taskID': TASKID_PROP,
            'consumed': {'type': 'number', 'description': '任务总消耗工时（必填，需 >= 已有消耗）'},
            'actor': ACTOR_PROP,
            'comment': {'type': 'string', 'description': '完成备注（可选，默认"完成"）'},
        }, ['taskID', 'consumed']),

        zt_tool_schema('zentao_record_effort', '记录工时（不改任务状态，除非 left 归零则自动完成）。', {
            'taskID': TASKID_PROP,
            'consumed': {'type': 'number', 'description': '本次消耗工时（必填）'},
            'left': {'type': 'number', 'description': '剩余工时（必填，0 表示将完成）'},
            'date': {'type': 'string', 'description': '日期 YYYY-MM-DD（可选，默认今天）'},
            'work': {'type': 'string', 'description': '工作说明（可选）'},
            'actor': ACTOR_PROP,
        }, ['taskID', 'consumed', 'left']),

        zt_tool_schema('zentao_add_comment', '给任务添加备注（写入任务历史记录）。', {
            'taskID': TASKID_PROP,
            'comment': {'type': 'string', 'description': '备注内容'},
            'actor': ACTOR_PROP,
        }, ['taskID', 'comment']),

        zt_tool_schema('zentao_get_story', '查询禅道需求（story）详情。',
                       {'storyID': {'type': 'integer', 'description': '需求 ID'}}, ['storyID']),

        zt_tool_schema('zentao_list_stories', '列出禅道需求（默认最近 50 条；可按产品和状态过滤）。',
                       {'productID': {'type': 'integer', 'description': '产品 ID（可选）'},
                        'status': {'type': 'string', 'description': '状态过滤（可选，如 active/closed）'}}),

        zt_tool_schema('zentao_get_bug', '查询禅道 Bug 详情。',
                       {'bugID': {'type': 'integer', 'description': 'Bug ID'}}, ['bugID']),

        zt_tool_schema('zentao_list_bugs', '列出禅道 Bug（默认最近 50 条；可按产品和状态过滤）。',
                       {'productID': {'type': 'integer', 'description': '产品 ID（可选）'},
                        'status': {'type': 'string', 'description': '状态过滤（可选，如 active/resolved/closed）'}}),

        zt_tool_schema('zentao_create_bug', '创建禅道 Bug。product 可通过 zentao_list_products 获取。', {
            'title': {'type': 'string', 'description': 'Bug 标题'},
            'product': {'type': 'integer', 'description': '所属产品 ID'},
            'openedBuild': {'type': 'string', 'description': '影响版本（可选，默认 trunk）'},
            'module': {'type': 'integer', 'description': '模块 ID（可选）'},
            'severity': {'type': 'integer', 'description': '严重程度 1-4（可选）'},
            'pri': {'type': 'integer', 'description': '优先级 1-4（可选）'},
            'type': {'type': 'string', 'description': 'Bug 类型（codeerror/config/install/security/performance/standard/automation/designdefect/othersaffair）'},
            'steps': {'type': 'string', 'description': '重现步骤（可选）'},
            'assignedTo': {'type': 'string', 'description': '指派给（可选）'},
            'project': {'type': 'integer', 'description': '所属项目（可选）'},
            'story': {'type': 'integer', 'description': '相关需求（可选）'},
            'actor': ACTOR_PROP,
        }, ['title', 'product']),

        zt_tool_schema('zentao_resolve_bug', '解决 Bug（active→resolved）。', {
            'bugID': {'type': 'integer', 'description': 'Bug ID'},
            'resolution': {'type': 'string', 'description': '解决方案：fixed/bydesign/duplicate/external/postponed/willnotfix/notrepro/worked'},
            'resolvedBuild': {'type': 'string', 'description': '解决版本（可选）'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['bugID', 'resolution']),

        zt_tool_schema('zentao_close_bug', '关闭 Bug（resolved→closed）。', {
            'bugID': {'type': 'integer', 'description': 'Bug ID'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['bugID']),

        zt_tool_schema('zentao_list_projects', '列出禅道项目（建任务前需要 projectID 时使用）。'),

        zt_tool_schema('zentao_list_products', '列出禅道产品（建 Bug 前需要 productID 时使用）。'),

        zt_tool_schema('zentao_effort_report', '工时报表：按账号与日期范围聚合工时记录（默认今天、全部账号）。', {
            'account': {'type': 'string', 'description': '账号过滤（可选）'},
            'dateFrom': {'type': 'string', 'description': '起始日期 YYYY-MM-DD（可选，默认今天）'},
            'dateTo': {'type': 'string', 'description': '截止日期 YYYY-MM-DD（可选，默认同 dateFrom）'},
        }),

        zt_tool_schema('zentao_get_git_commits', '读取本地 git 仓库的提交记录（只读，不同步禅道）。返回 hash/作者/邮箱/authorAccount（按服务端 GIT_AUTHOR_MAP 映射的禅道账号）/时间/消息/文件列表。', {
            'repoPath': {'type': 'string', 'description': 'git 仓库路径（可选，默认配置的仓库）'},
            'since': {'type': 'string', 'description': '起始 commit hash，读取该点之后的提交（可选）'},
            'limit': {'type': 'integer', 'description': '无 since 时最多读取的提交数，默认 20'},
        }),

        zt_tool_schema('zentao_sync_commits', '把本地 git 提交同步到禅道：解析提交信息中的 task #N / story #N / bug #N 标记，在对应对象上写入关联记录（gitcommited action，署名恒为 git 作者）。默认从上次同步点增量同步。建议工作流：先 get_git_commits 查看新提交 → 用 start_task/record_effort/finish_task 按提交内容更新任务状态与工时（actor 用 authorAccount）→ 最后 sync_commits 写入关联。', {
            'repoPath': {'type': 'string', 'description': 'git 仓库路径（可选，默认配置的仓库）'},
            'actor': ACTOR_PROP,
            'since': {'type': 'string', 'description': '起始 commit hash（可选，默认上次同步点）'},
            'limit': {'type': 'integer', 'description': '无同步点时最多同步的提交数，默认 20'},
        }),
    ]


TOOLS = build_tools()


# ============ MCP stdio protocol ============

def zt_json(data):
    return json.dumps(data, ensure_ascii=False, indent=4)


def zt_respond(req_id, result):
    if req_id is None:
        return
    sys.stdout.write(json.dumps({'jsonrpc': '2.0', 'id': req_id, 'result': result}, ensure_ascii=False) + '\n')
    sys.stdout.flush()


def zt_respond_error(req_id, code, message):
    if req_id is None:
        return
    sys.stdout.write(json.dumps({'jsonrpc': '2.0', 'id': req_id, 'error': {'code': code, 'message': message}}, ensure_ascii=False) + '\n')
    sys.stdout.flush()


def handle_request(req):
    method = str(req.get('method', ''))
    req_id = req.get('id')

    if method == 'initialize':
        zt_respond(req_id, {
            'protocolVersion': '2024-11-05',
            'capabilities': {'tools': {}},
            'serverInfo': {'name': 'zentao-mcp', 'version': VERSION},
        })
    elif method in ('notifications/initialized', 'notifications/cancelled'):
        pass
    elif method == 'ping':
        zt_respond(req_id, {})
    elif method == 'resources/list':
        zt_respond(req_id, {'resources': []})
    elif method == 'prompts/list':
        zt_respond(req_id, {'prompts': []})
    elif method == 'tools/list':
        zt_respond(req_id, {'tools': TOOLS})
    elif method == 'tools/call':
        params = req.get('params') or {}
        name = str(params.get('name', ''))
        args = params.get('arguments') or {}
        if not isinstance(args, dict):
            args = {}
        try:
            text = zt_json(zt_tool_result(name, args))
            zt_respond(req_id, {'content': [{'type': 'text', 'text': text}]})
        except Exception as exc:  # noqa: BLE001 - mirror PHP catch(Exception)
            zt_respond(req_id, {'content': [{'type': 'text', 'text': str(exc)}], 'isError': True})
    else:
        zt_respond_error(req_id, -32601, 'Method not found: %s' % method)


def main():
    keepalive = '--keepalive' in sys.argv[1:]
    while True:
        line = sys.stdin.readline()
        if line == '':
            if keepalive:
                time.sleep(3)
                continue
            break

        line = line.strip()
        if not line:
            continue
        try:
            req = json.loads(line)
        except ValueError:
            continue
        if not isinstance(req, dict) or 'method' not in req:
            continue

        try:
            handle_request(req)
        except Exception as exc:  # noqa: BLE001
            sys.stderr.write('[zentao-mcp] %s\n' % exc)
            sys.stderr.flush()
            zt_respond_error(req.get('id'), -32603, 'Internal error: %s' % exc)


if __name__ == '__main__':
    main()
