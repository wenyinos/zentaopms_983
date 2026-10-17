#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""zentao-mcp Python client: stdio MCP shell for ZenTao (zero third-party deps).

Requires: python3 >= 3.7, git. Configuration is read from .env next to this file:
    ZENTAO_URL, MCP_SECRET, MCP_REPO_PATH, MCP_ACCOUNT, GIT_AUTHOR_MAP
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

VERSION = '0.6.2'


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
ACCOUNT_DEFAULT = ENV.get('MCP_ACCOUNT', '')


def load_author_map():
    amap = {}
    for pair in ENV.get('GIT_AUTHOR_MAP', '').split(','):
        pair = pair.strip()
        if not pair or ':' not in pair:
            continue
        key, account = pair.split(':', 1)
        amap[key.strip().lower()] = account.strip()
    return amap


AUTHOR_MAP = load_author_map()


def author_account(name, email):
    if not AUTHOR_MAP:
        return ''
    email_key = str(email or '').strip().lower()
    name_key = str(name or '').strip().lower()
    if email_key and email_key in AUTHOR_MAP:
        return AUTHOR_MAP[email_key]
    if name_key and name_key in AUTHOR_MAP:
        return AUTHOR_MAP[name_key]
    return ''


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
        raise RuntimeError('Non-JSON response: %s' % raw.decode('utf-8', 'replace')[:1500])
    if not isinstance(data, dict):
        raise RuntimeError('Non-JSON response: %s' % raw.decode('utf-8', 'replace')[:1500])
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
    account = str(args['actor']) if args.get('actor') else ACCOUNT_DEFAULT
    if account == '':
        raise RuntimeError('MCP_ACCOUNT 未配置：请在 zentao-mcp-py/.env 中设置本机禅道账号')
    params['actor'] = account


def zt_list_arg(value):
    if isinstance(value, list):
        return ','.join(str(v) for v in value)
    return value


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
        for key in ('type', 'estimate', 'left', 'estStarted', 'deadline', 'desc', 'pri', 'story', 'module', 'assignedTo', 'parent', 'comment'):
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
        if not args.get('taskID') and not args.get('objectID'):
            raise RuntimeError('taskID 或 objectID 必填')
        if not args.get('comment'):
            raise RuntimeError('comment 必填')
        params = {}
        if args.get('objectType'):
            params['objectType'] = str(args['objectType'])
        if args.get('objectID'):
            params['objectID'] = int(args['objectID'])
        if args.get('taskID'):
            params['taskID'] = int(args['taskID'])
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

    # ---- 项目 ----
    if name == 'zentao_get_project':
        if not args.get('projectID'):
            raise RuntimeError('projectID 必填')
        return zt_call('getProject', {'projectID': int(args['projectID'])})

    if name == 'zentao_create_project':
        for req in ('name', 'code', 'begin', 'end'):
            if not args.get(req):
                raise RuntimeError('%s 必填' % req)
        params = {}
        zt_actor_params(params, args)
        if args.get('copyFrom'):
            params['copyFrom'] = int(args['copyFrom'])
        post = {}
        for key in ('name', 'code', 'begin', 'end', 'team', 'days', 'type', 'acl', 'desc', 'whitelist'):
            if key in args:
                post[key] = zt_list_arg(args[key])
        if 'products' in args:
            post['products'] = json.dumps(args['products'], ensure_ascii=False)
        return zt_call('createProject', params, post)

    if name == 'zentao_update_project':
        if not args.get('projectID'):
            raise RuntimeError('projectID 必填')
        params = {'projectID': int(args['projectID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('name', 'code', 'begin', 'end', 'days', 'type', 'team', 'desc', 'acl', 'whitelist'):
            if key in args:
                post[key] = zt_list_arg(args[key])
        if 'products' in args:
            post['products'] = json.dumps(args['products'], ensure_ascii=False)
        return zt_call('updateProject', params, post)

    if name == 'zentao_project_flow':
        if not args.get('projectID'):
            raise RuntimeError('projectID 必填')
        if not args.get('action'):
            raise RuntimeError('action 必填（start|suspend|activate|close）')
        params = {'projectID': int(args['projectID']), 'action': str(args['action'])}
        zt_actor_params(params, args)
        post = {'comment': str(args['comment']) if args.get('comment') else ''}
        return zt_call('projectFlow', params, post)

    if name == 'zentao_manage_team':
        if not args.get('projectID'):
            raise RuntimeError('projectID 必填')
        if not args.get('members'):
            raise RuntimeError('members 必填（数组：{account, role?, days?, hours?, limited?, mode?}）')
        params = {'projectID': int(args['projectID'])}
        zt_actor_params(params, args)
        return zt_call('manageTeam', params, {'members': json.dumps(args['members'], ensure_ascii=False)})

    # ---- 产品 ----
    if name == 'zentao_create_product':
        for req in ('name', 'code'):
            if not args.get(req):
                raise RuntimeError('%s 必填' % req)
        params = {}
        zt_actor_params(params, args)
        post = {}
        for key in ('name', 'code', 'PO', 'QD', 'RD', 'type', 'line', 'desc', 'acl', 'whitelist'):
            if key in args:
                post[key] = zt_list_arg(args[key])
        return zt_call('createProduct', params, post)

    if name == 'zentao_update_product':
        if not args.get('productID'):
            raise RuntimeError('productID 必填')
        params = {'productID': int(args['productID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('name', 'code', 'PO', 'QD', 'RD', 'type', 'line', 'desc', 'acl', 'whitelist'):
            if key in args:
                post[key] = zt_list_arg(args[key])
        return zt_call('updateProduct', params, post)

    # ---- 需求 ----
    if name == 'zentao_create_story':
        for req in ('product', 'title'):
            if not args.get(req):
                raise RuntimeError('%s 必填' % req)
        params = {}
        zt_actor_params(params, args)
        post = {}
        for key in ('product', 'title', 'spec', 'verify', 'module', 'plan', 'source', 'sourceNote', 'pri', 'estimate', 'assignedTo', 'needNotReview', 'keywords'):
            if key in args:
                post[key] = args[key]
        return zt_call('createStory', params, post)

    if name == 'zentao_update_story':
        if not args.get('storyID'):
            raise RuntimeError('storyID 必填')
        params = {'storyID': int(args['storyID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('title', 'pri', 'estimate', 'module', 'plan', 'stage', 'source', 'sourceNote', 'assignedTo', 'keywords', 'comment'):
            if key in args:
                post[key] = args[key]
        return zt_call('updateStory', params, post)

    if name == 'zentao_change_story':
        if not args.get('storyID'):
            raise RuntimeError('storyID 必填')
        params = {'storyID': int(args['storyID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('title', 'spec', 'verify', 'assignedTo', 'needNotReview', 'comment'):
            if key in args:
                post[key] = args[key]
        return zt_call('changeStory', params, post)

    if name == 'zentao_story_flow':
        if not args.get('storyID'):
            raise RuntimeError('storyID 必填')
        if not args.get('action'):
            raise RuntimeError('action 必填（close|activate）')
        params = {'storyID': int(args['storyID']), 'action': str(args['action'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('closedReason', 'duplicateStory', 'childStories', 'assignedTo', 'comment'):
            if key in args:
                post[key] = args[key]
        return zt_call('storyFlow', params, post)

    # ---- 任务 / Bug 编辑 ----
    if name == 'zentao_update_task':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        params = {'taskID': int(args['taskID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('name', 'type', 'desc', 'pri', 'estimate', 'left', 'consumed', 'deadline', 'estStarted', 'assignedTo', 'comment'):
            if key in args:
                post[key] = args[key]
        return zt_call('updateTask', params, post)

    if name == 'zentao_update_bug':
        if not args.get('bugID'):
            raise RuntimeError('bugID 必填')
        params = {'bugID': int(args['bugID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('title', 'openedBuild', 'module', 'type', 'severity', 'pri', 'steps', 'assignedTo', 'deadline', 'keywords', 'comment'):
            if key in args:
                post[key] = args[key]
        return zt_call('updateBug', params, post)

    if name == 'zentao_activate_bug':
        if not args.get('bugID'):
            raise RuntimeError('bugID 必填')
        params = {'bugID': int(args['bugID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('comment', 'assignedTo', 'openedBuild'):
            if key in args:
                post[key] = args[key]
        return zt_call('activateBug', params, post)

    if name == 'zentao_assign_bug':
        if not args.get('bugID'):
            raise RuntimeError('bugID 必填')
        if not args.get('assignedTo'):
            raise RuntimeError('assignedTo 必填')
        params = {'bugID': int(args['bugID'])}
        zt_actor_params(params, args)
        post = {'assignedTo': str(args['assignedTo'])}
        if 'comment' in args:
            post['comment'] = str(args['comment'])
        if 'mailto' in args:
            post['mailto'] = zt_list_arg(args['mailto'])
        return zt_call('assignBug', params, post)

    if name == 'zentao_delete_effort':
        if not args.get('estimateID'):
            raise RuntimeError('estimateID 必填')
        params = {'estimateID': int(args['estimateID'])}
        zt_actor_params(params, args)
        return zt_call('deleteEffort', params)

    # ---- 删除（软删除，须显式 confirm=yes）----
    zt_delete_tools = {
        'zentao_delete_task': ('deleteTask', 'taskID'),
        'zentao_delete_bug': ('deleteBug', 'bugID'),
        'zentao_delete_story': ('deleteStory', 'storyID'),
        'zentao_delete_testcase': ('deleteTestcase', 'caseID'),
        'zentao_delete_doc': ('deleteDoc', 'docID'),
    }
    if name in zt_delete_tools:
        method, id_key = zt_delete_tools[name]
        if not args.get(id_key):
            raise RuntimeError('%s 必填' % id_key)
        if str(args.get('confirm', '')).strip().lower() != 'yes':
            raise RuntimeError('confirm 必须显式传 yes（软删除：行保留在库中，deleted=1）')
        params = {id_key: int(args[id_key]), 'confirm': 'yes'}
        zt_actor_params(params, args)
        return zt_call(method, params)

    # ---- 发布 / 测试 / 文档 ----
    if name == 'zentao_create_release':
        if not args.get('productID'):
            raise RuntimeError('productID 必填')
        for req in ('name', 'date'):
            if not args.get(req):
                raise RuntimeError('%s 必填' % req)
        params = {'productID': int(args['productID'])}
        if args.get('branch'):
            params['branch'] = str(args['branch'])
        zt_actor_params(params, args)
        post = {}
        for key in ('name', 'date', 'build', 'desc', 'stories', 'bugs'):
            if key in args:
                post[key] = zt_list_arg(args[key])
        return zt_call('createRelease', params, post)

    if name == 'zentao_create_testtask':
        for req in ('product', 'project', 'build', 'begin', 'end', 'name'):
            if not args.get(req):
                raise RuntimeError('%s 必填' % req)
        params = {}
        zt_actor_params(params, args)
        post = {}
        for key in ('product', 'project', 'build', 'owner', 'pri', 'begin', 'end', 'name', 'desc', 'mailto'):
            if key in args:
                post[key] = zt_list_arg(args[key])
        return zt_call('createTesttask', params, post)

    if name == 'zentao_testtask_flow':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        if not args.get('action'):
            raise RuntimeError('action 必填（start|close）')
        params = {'taskID': int(args['taskID']), 'action': str(args['action'])}
        zt_actor_params(params, args)
        post = {'comment': str(args['comment']) if args.get('comment') else ''}
        return zt_call('testtaskFlow', params, post)

    if name == 'zentao_create_testcase':
        for req in ('product', 'title', 'type'):
            if not args.get(req):
                raise RuntimeError('%s 必填' % req)
        params = {}
        zt_actor_params(params, args)
        post = {}
        for key in ('product', 'branch', 'module', 'story', 'title', 'type', 'pri', 'stage', 'precondition', 'keywords', 'forceNotReview'):
            if key in args:
                post[key] = zt_list_arg(args[key])
        if args.get('steps'):
            post['steps'] = json.dumps(args['steps'], ensure_ascii=False)
        return zt_call('createTestcase', params, post)

    if name == 'zentao_update_testcase':
        if not args.get('caseID'):
            raise RuntimeError('caseID 必填')
        params = {'caseID': int(args['caseID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('title', 'type', 'pri', 'stage', 'precondition', 'keywords', 'story', 'module', 'comment'):
            if key in args:
                post[key] = args[key]
        if args.get('steps'):
            post['steps'] = json.dumps(args['steps'], ensure_ascii=False)
        return zt_call('updateTestcase', params, post)

    if name == 'zentao_create_doc':
        for req in ('title', 'content'):
            if not args.get(req):
                raise RuntimeError('%s 必填' % req)
        if not args.get('lib') and not args.get('product') and not args.get('project'):
            raise RuntimeError('lib 或 product/project 至少提供其一（用于定位文档库）')
        params = {}
        zt_actor_params(params, args)
        post = {}
        for key in ('lib', 'product', 'project', 'title', 'content', 'contentType', 'type', 'keywords', 'module', 'acl', 'users', 'groups'):
            if key in args:
                post[key] = zt_list_arg(args[key])
        return zt_call('createDoc', params, post)

    # ---- 计划 / 账号 / 构建 ----
    if name == 'zentao_create_plan':
        for req in ('product', 'title'):
            if not args.get(req):
                raise RuntimeError('%s 必填' % req)
        params = {}
        zt_actor_params(params, args)
        post = {}
        for key in ('product', 'branch', 'title', 'begin', 'end', 'future', 'desc'):
            if key in args:
                post[key] = args[key]
        return zt_call('createPlan', params, post)

    if name == 'zentao_list_plans':
        if not args.get('productID'):
            raise RuntimeError('productID 必填')
        return zt_call('listPlans', {'productID': int(args['productID'])})

    if name == 'zentao_list_users':
        return zt_call('listUsers')

    if name == 'zentao_create_build':
        if not args.get('projectID'):
            raise RuntimeError('projectID 必填')
        for req in ('product', 'name'):
            if not args.get(req):
                raise RuntimeError('%s 必填' % req)
        params = {'projectID': int(args['projectID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('product', 'branch', 'name', 'builder', 'date', 'desc', 'scmPath', 'filePath'):
            if key in args:
                post[key] = args[key]
        return zt_call('createBuild', params, post)

    # ---- 文档编辑 / 文档库 ----
    if name == 'zentao_update_doc':
        if not args.get('docID'):
            raise RuntimeError('docID 必填')
        params = {'docID': int(args['docID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('title', 'content', 'contentType', 'type', 'url', 'keywords', 'module', 'acl'):
            if key in args:
                post[key] = args[key]
        if 'users' in args:
            post['users'] = zt_list_arg(args['users'])
        if 'groups' in args:
            post['groups'] = zt_list_arg(args['groups'])
        if 'comment' in args:
            post['comment'] = str(args['comment'])
        return zt_call('updateDoc', params, post)

    if name == 'zentao_create_doclib':
        if not args.get('name'):
            raise RuntimeError('name 必填')
        params = {}
        zt_actor_params(params, args)
        post = {'name': str(args['name'])}
        for key in ('libType', 'product', 'project', 'acl'):
            if key in args:
                post[key] = args[key]
        if 'users' in args:
            post['users'] = zt_list_arg(args['users'])
        if 'groups' in args:
            post['groups'] = zt_list_arg(args['groups'])
        return zt_call('createDoclib', params, post)

    # ---- 评审 / 确认 ----
    if name == 'zentao_review_story':
        if not args.get('storyID'):
            raise RuntimeError('storyID 必填')
        if not args.get('result'):
            raise RuntimeError('result 必填（pass|reject|revert|clarify）')
        params = {'storyID': int(args['storyID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('result', 'comment', 'assignedTo', 'closedReason', 'duplicateStory', 'childStories', 'preVersion'):
            if key in args:
                post[key] = args[key]
        if 'reviewedBy' in args:
            post['reviewedBy'] = zt_list_arg(args['reviewedBy'])
        return zt_call('reviewStory', params, post)

    if name == 'zentao_confirm_bug':
        if not args.get('bugID'):
            raise RuntimeError('bugID 必填')
        params = {'bugID': int(args['bugID'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('assignedTo', 'type', 'pri', 'comment'):
            if key in args:
                post[key] = args[key]
        if 'mailto' in args:
            post['mailto'] = zt_list_arg(args['mailto'])
        return zt_call('confirmBug', params, post)

    if name == 'zentao_review_testcase':
        if not args.get('caseID'):
            raise RuntimeError('caseID 必填')
        if not args.get('result'):
            raise RuntimeError('result 必填（pass|clarify）')
        params = {'caseID': int(args['caseID'])}
        zt_actor_params(params, args)
        post = {'result': str(args['result'])}
        if 'comment' in args:
            post['comment'] = str(args['comment'])
        if 'reviewedBy' in args:
            post['reviewedBy'] = zt_list_arg(args['reviewedBy'])
        return zt_call('reviewTestcase', params, post)

    # ---- 关联管理 ----
    if name == 'zentao_manage_release':
        if not args.get('releaseID'):
            raise RuntimeError('releaseID 必填')
        if not args.get('action'):
            raise RuntimeError('action 必填（link_story|unlink_story|link_bug|unlink_bug|change_status）')
        params = {'releaseID': int(args['releaseID']), 'action': str(args['action'])}
        zt_actor_params(params, args)
        post = {}
        for key in ('storyID', 'bugID', 'bugType', 'status'):
            if key in args:
                post[key] = args[key]
        if 'stories' in args:
            post['stories'] = json.dumps(args['stories'], ensure_ascii=False)
        if 'bugs' in args:
            post['bugs'] = json.dumps(args['bugs'], ensure_ascii=False)
        return zt_call('manageRelease', params, post)

    if name == 'zentao_link_project_stories':
        if not args.get('projectID'):
            raise RuntimeError('projectID 必填')
        if not args.get('stories'):
            raise RuntimeError('stories 必填（需求 ID 数组）')
        params = {'projectID': int(args['projectID'])}
        zt_actor_params(params, args)
        return zt_call('linkProjectStories', params, {'stories': json.dumps(args['stories'], ensure_ascii=False)})

    if name == 'zentao_unlink_project_story':
        if not args.get('projectID'):
            raise RuntimeError('projectID 必填')
        if not args.get('storyID'):
            raise RuntimeError('storyID 必填')
        params = {'projectID': int(args['projectID']), 'storyID': int(args['storyID'])}
        zt_actor_params(params, args)
        return zt_call('unlinkProjectStory', params)

    if name == 'zentao_subdivide_story':
        if not args.get('storyID'):
            raise RuntimeError('storyID 必填')
        if not args.get('children'):
            raise RuntimeError('children 必填（数组：{title, spec?, verify?, module?, pri?, estimate?}）')
        params = {'storyID': int(args['storyID'])}
        zt_actor_params(params, args)
        return zt_call('subdivideStory', params, {'children': json.dumps(args['children'], ensure_ascii=False)})

    if name == 'zentao_link_testtask_cases':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        if not args.get('cases'):
            raise RuntimeError('cases 必填（用例 ID 数组）')
        params = {'taskID': int(args['taskID'])}
        zt_actor_params(params, args)
        if args.get('type'):
            params['type'] = str(args['type'])
        return zt_call('linkTesttaskCases', params, {'cases': json.dumps(args['cases'], ensure_ascii=False)})

    if name == 'zentao_list_testruns':
        if not args.get('taskID'):
            raise RuntimeError('taskID 必填')
        return zt_call('listTestruns', {'taskID': int(args['taskID'])})

    if name == 'zentao_record_test_result':
        if not args.get('runID'):
            raise RuntimeError('runID 必填')
        if not args.get('steps'):
            raise RuntimeError('steps 必填（数组：{stepID?, result, real?}）')
        params = {'runID': int(args['runID'])}
        zt_actor_params(params, args)
        return zt_call('recordTestResult', params, {'steps': json.dumps(args['steps'], ensure_ascii=False)})

    # ---- 提交 ----
    if name == 'zentao_get_git_commits':
        repo = str(args['repoPath']) if args.get('repoPath') else REPO_DEFAULT
        commits = zt_git_commits(repo, str(args.get('since', '')), int(args.get('limit', 20)))
        for commit in commits:
            commit['authorAccount'] = author_account(commit['name'], commit['email'])
        return {'count': len(commits), 'commits': commits}

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
CONFIRM_PROP = {'type': 'string', 'enum': ['yes'], 'description': '显式确认，必须为 yes（软删除：行保留在库中，deleted=1，可由管理员恢复）'}


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

        zt_tool_schema('zentao_add_comment', '添加备注（写入对象历史记录）：默认给任务；传 objectType/objectID 可给 bug/story/doc 留言。', {
            'taskID': {'type': 'integer', 'description': '任务 ID（objectType=task 或省略 objectType 时使用）'},
            'objectType': {'type': 'string', 'description': 'task | bug | story | doc（可选，默认 task）'},
            'objectID': {'type': 'integer', 'description': '对象 ID（非任务时使用）'},
            'comment': {'type': 'string', 'description': '备注内容'},
            'actor': ACTOR_PROP,
        }, ['comment']),

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

        # ---- 项目 ----
        zt_tool_schema('zentao_create_project', '创建禅道项目（可选复制已有项目的团队；可直接关联产品）。', {
            'name': {'type': 'string', 'description': '项目名称'},
            'code': {'type': 'string', 'description': '项目代号（团队内部简称，唯一）'},
            'begin': {'type': 'string', 'description': '开始日期 YYYY-MM-DD'},
            'end': {'type': 'string', 'description': '结束日期 YYYY-MM-DD'},
            'team': {'type': 'string', 'description': '团队名称（可选，默认同项目名）'},
            'days': {'type': 'integer', 'description': '可用工作日天数（可选）'},
            'type': {'type': 'string', 'description': '项目类型 sprint/waterfall/ops（可选）'},
            'acl': {'type': 'string', 'description': '访问控制 open/private（可选）'},
            'desc': {'type': 'string', 'description': '项目描述（可选）'},
            'whitelist': {'type': 'array', 'items': {'type': 'string'}, 'description': 'acl=private 时的白名单账号列表（可选）'},
            'products': {'type': 'array', 'items': {'type': 'integer'}, 'description': '关联的产品 ID 列表（可选）'},
            'copyFrom': {'type': 'integer', 'description': '复制来源项目 ID（可选，会复制其团队）'},
            'actor': ACTOR_PROP,
        }, ['name', 'code', 'begin', 'end']),

        zt_tool_schema('zentao_get_project', '查询项目详情（含关联产品列表，用于项目↔产品关联管理）。', {
            'projectID': {'type': 'integer', 'description': '项目 ID'},
        }, ['projectID']),

        zt_tool_schema('zentao_update_project', '编辑禅道项目（仅传入要修改的字段，未传字段保持原值）。products 可全量设置项目关联的产品（传 [] 清空；不传则保持现有）。', {
            'projectID': {'type': 'integer', 'description': '项目 ID'},
            'name': {'type': 'string', 'description': '项目名称（可选）'},
            'code': {'type': 'string', 'description': '项目代号（可选）'},
            'begin': {'type': 'string', 'description': '开始日期 YYYY-MM-DD（可选）'},
            'end': {'type': 'string', 'description': '结束日期 YYYY-MM-DD（可选）'},
            'days': {'type': 'integer', 'description': '可用工作日天数（可选）'},
            'type': {'type': 'string', 'description': '项目类型（可选）'},
            'team': {'type': 'string', 'description': '团队名称（可选）'},
            'desc': {'type': 'string', 'description': '项目描述（可选）'},
            'acl': {'type': 'string', 'description': '访问控制 open/private（可选）'},
            'whitelist': {'type': 'array', 'items': {'type': 'string'}, 'description': '白名单账号列表（可选）'},
            'products': {'type': 'array', 'items': {'type': 'integer'}, 'description': '项目关联的产品 ID 列表（可选；全量替换语义，传 [] 清空关联，不传保持现状）'},
            'actor': ACTOR_PROP,
        }, ['projectID']),

        zt_tool_schema('zentao_project_flow', '项目生命周期流转：start 启动 / suspend 挂起 / activate 激活 / close 关闭。', {
            'projectID': {'type': 'integer', 'description': '项目 ID'},
            'action': {'type': 'string', 'description': 'start | suspend | activate | close'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['projectID', 'action']),

        zt_tool_schema('zentao_manage_team', '项目团队管理：批量添加/更新团队成员（已存在成员自动更新，新成员自动加入）。', {
            'projectID': {'type': 'integer', 'description': '项目 ID'},
            'members': {'type': 'array', 'items': {'type': 'object', 'properties': {'account': {'type': 'string', 'description': '成员禅道账号'}, 'role': {'type': 'string'}, 'days': {'type': 'integer'}, 'hours': {'type': 'number'}, 'limited': {'type': 'string'}, 'mode': {'type': 'string', 'description': 'create|update（可选，默认自动判断）'}}}, 'description': '成员数组：{account, role?, days?, hours?, limited?, mode?}'},
            'actor': ACTOR_PROP,
        }, ['projectID', 'members']),

        # ---- 产品 ----
        zt_tool_schema('zentao_create_product', '创建禅道产品（建需求/发布前需要 productID 时使用）。', {
            'name': {'type': 'string', 'description': '产品名称'},
            'code': {'type': 'string', 'description': '产品代号（唯一）'},
            'PO': {'type': 'string', 'description': '产品负责人账号（可选，默认操作人）'},
            'QD': {'type': 'string', 'description': '测试负责人账号（可选）'},
            'RD': {'type': 'string', 'description': '发布负责人账号（可选）'},
            'type': {'type': 'string', 'description': '产品类型 normal/branch/platform（可选，默认 normal）'},
            'line': {'type': 'string', 'description': '产品线（可选）'},
            'desc': {'type': 'string', 'description': '产品描述（可选）'},
            'acl': {'type': 'string', 'description': '访问控制 open/private/custom（可选）'},
            'whitelist': {'type': 'array', 'items': {'type': 'string'}, 'description': 'acl=custom 时的白名单账号列表（可选）'},
            'actor': ACTOR_PROP,
        }, ['name', 'code']),

        zt_tool_schema('zentao_update_product', '编辑禅道产品（仅传入要修改的字段）。', {
            'productID': {'type': 'integer', 'description': '产品 ID'},
            'name': {'type': 'string', 'description': '产品名称（可选）'},
            'code': {'type': 'string', 'description': '产品代号（可选）'},
            'PO': {'type': 'string', 'description': '产品负责人（可选）'},
            'QD': {'type': 'string', 'description': '测试负责人（可选）'},
            'RD': {'type': 'string', 'description': '发布负责人（可选）'},
            'type': {'type': 'string', 'description': '产品类型（可选）'},
            'line': {'type': 'string', 'description': '产品线（可选）'},
            'desc': {'type': 'string', 'description': '产品描述（可选）'},
            'acl': {'type': 'string', 'description': '访问控制（可选）'},
            'whitelist': {'type': 'array', 'items': {'type': 'string'}, 'description': '白名单账号列表（可选）'},
            'actor': ACTOR_PROP,
        }, ['productID']),

        # ---- 需求 ----
        zt_tool_schema('zentao_create_story', '创建禅道需求（story）。spec=需求描述、verify=验收标准；默认按禅道配置进入评审（draft），传 needNotReview=1 直接生效。', {
            'product': {'type': 'integer', 'description': '所属产品 ID'},
            'title': {'type': 'string', 'description': '需求名称'},
            'spec': {'type': 'string', 'description': '需求描述（可选）'},
            'verify': {'type': 'string', 'description': '验收标准（可选）'},
            'module': {'type': 'integer', 'description': '所属模块 ID（可选，默认 0 无模块）'},
            'plan': {'type': 'integer', 'description': '所属计划 ID（可选）'},
            'source': {'type': 'string', 'description': '来源（可选，如 po/customer/user）'},
            'sourceNote': {'type': 'string', 'description': '来源备注（可选）'},
            'pri': {'type': 'integer', 'description': '优先级 1-4（可选）'},
            'estimate': {'type': 'number', 'description': '预计工时（可选）'},
            'assignedTo': {'type': 'string', 'description': '指派给（可选）'},
            'needNotReview': {'type': 'integer', 'description': '1=免评审直接生效（可选）'},
            'keywords': {'type': 'string', 'description': '关键词（可选）'},
            'actor': ACTOR_PROP,
        }, ['product', 'title']),

        zt_tool_schema('zentao_update_story', '编辑需求基础字段（名称/优先级/预估/模块/计划/阶段等）；改需求描述或验收标准请用 change_story。', {
            'storyID': {'type': 'integer', 'description': '需求 ID'},
            'title': {'type': 'string', 'description': '需求名称（可选）'},
            'pri': {'type': 'integer', 'description': '优先级（可选）'},
            'estimate': {'type': 'number', 'description': '预计工时（可选）'},
            'module': {'type': 'integer', 'description': '所属模块（可选）'},
            'plan': {'type': 'integer', 'description': '所属计划（可选）'},
            'stage': {'type': 'string', 'description': '阶段（可选）'},
            'source': {'type': 'string', 'description': '来源（可选）'},
            'sourceNote': {'type': 'string', 'description': '来源备注（可选）'},
            'assignedTo': {'type': 'string', 'description': '指派给（可选）'},
            'keywords': {'type': 'string', 'description': '关键词（可选）'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['storyID']),

        zt_tool_schema('zentao_change_story', '变更需求（修改需求描述 spec / 验收标准 verify，禅道会自动升版本并记录变更历史；active 状态默认转为 changed 待确认，传 needNotReview=1 保持 active）。', {
            'storyID': {'type': 'integer', 'description': '需求 ID'},
            'title': {'type': 'string', 'description': '需求名称（可选，默认保持）'},
            'spec': {'type': 'string', 'description': '新的需求描述（可选，默认保持）'},
            'verify': {'type': 'string', 'description': '新的验收标准（可选，默认保持）'},
            'assignedTo': {'type': 'string', 'description': '指派给（可选）'},
            'needNotReview': {'type': 'integer', 'description': '1=变更后免评审保持生效（可选）'},
            'comment': {'type': 'string', 'description': '变更说明（建议填写）'},
            'actor': ACTOR_PROP,
        }, ['storyID']),

        zt_tool_schema('zentao_story_flow', '需求状态流转：close 关闭（closedReason 必填：done/subdivided/duplicate/postponed/willnotdo/cancel）/ activate 激活。', {
            'storyID': {'type': 'integer', 'description': '需求 ID'},
            'action': {'type': 'string', 'description': 'close | activate'},
            'closedReason': {'type': 'string', 'description': '关闭原因（close 必填）'},
            'duplicateStory': {'type': 'integer', 'description': '重复需求 ID（closedReason=duplicate 时必填）'},
            'childStories': {'type': 'string', 'description': '细分需求 ID（closedReason=subdivided 时用，可选）'},
            'assignedTo': {'type': 'string', 'description': '指派给（activate 可选）'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['storyID', 'action']),

        # ---- 任务 / Bug 编辑 ----
        zt_tool_schema('zentao_update_task', '编辑任务（名称/类型/描述/优先级/预估/剩余/消耗/截止/预计开始/指派等；未传字段保持原值）。改状态请用 start/finish/task_flow。', {
            'taskID': TASKID_PROP,
            'name': {'type': 'string', 'description': '任务名称（可选）'},
            'type': {'type': 'string', 'description': '任务类型（可选）'},
            'desc': {'type': 'string', 'description': '任务描述（可选）'},
            'pri': {'type': 'integer', 'description': '优先级（可选）'},
            'estimate': {'type': 'number', 'description': '预估工时（可选）'},
            'left': {'type': 'number', 'description': '剩余工时（可选）'},
            'consumed': {'type': 'number', 'description': '已消耗工时（可选，须 >= 当前值）'},
            'deadline': {'type': 'string', 'description': '截止日期 YYYY-MM-DD（可选）'},
            'estStarted': {'type': 'string', 'description': '预计开始 YYYY-MM-DD（可选）'},
            'assignedTo': {'type': 'string', 'description': '指派给（可选）'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['taskID']),

        zt_tool_schema('zentao_update_bug', '编辑 Bug（标题/影响版本/模块/类型/严重度/优先级/重现步骤/指派/截止等；未传字段保持原值）。改状态请用 resolve/close/activate。', {
            'bugID': {'type': 'integer', 'description': 'Bug ID'},
            'title': {'type': 'string', 'description': 'Bug 标题（可选）'},
            'openedBuild': {'type': 'string', 'description': '影响版本（可选）'},
            'module': {'type': 'integer', 'description': '模块 ID（可选）'},
            'type': {'type': 'string', 'description': 'Bug 类型（可选）'},
            'severity': {'type': 'integer', 'description': '严重程度 1-4（可选）'},
            'pri': {'type': 'integer', 'description': '优先级 1-4（可选）'},
            'steps': {'type': 'string', 'description': '重现步骤（可选）'},
            'assignedTo': {'type': 'string', 'description': '指派给（可选）'},
            'deadline': {'type': 'string', 'description': '截止日期 YYYY-MM-DD（可选）'},
            'keywords': {'type': 'string', 'description': '关键词（可选）'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['bugID']),

        zt_tool_schema('zentao_activate_bug', '激活（重新打开）Bug：resolved/closed → active，复测不通过时使用。', {
            'bugID': {'type': 'integer', 'description': 'Bug ID'},
            'comment': {'type': 'string', 'description': '激活原因（建议填写）'},
            'assignedTo': {'type': 'string', 'description': '指派给（可选，默认回解决人）'},
            'openedBuild': {'type': 'string', 'description': '影响版本（可选）'},
            'actor': ACTOR_PROP,
        }, ['bugID']),

        zt_tool_schema('zentao_assign_bug', '指派 Bug 给其他成员。', {
            'bugID': {'type': 'integer', 'description': 'Bug ID'},
            'assignedTo': {'type': 'string', 'description': '指派给（禅道账号）'},
            'comment': COMMENT_PROP,
            'mailto': {'type': 'array', 'items': {'type': 'string'}, 'description': '抄送账号列表（可选）'},
            'actor': ACTOR_PROP,
        }, ['bugID', 'assignedTo']),

        zt_tool_schema('zentao_delete_effort', '删除工时记录（用于修正误报；删除后任务消耗/剩余自动重算）。', {
            'estimateID': {'type': 'integer', 'description': '工时记录 ID（可从 get_task_estimates 获取）'},
            'actor': ACTOR_PROP,
        }, ['estimateID']),

        # ---- 删除（软删除，须显式 confirm=yes；删除后可用 get_task/get_bug/get_story 看 deleted 字段确认）----
        zt_tool_schema('zentao_delete_task', '软删除任务（置 deleted=1，行保留在库中；连带软删子任务，并回写父任务状态、来源 Bug 的 toTask、需求阶段）。必须传 confirm=yes。', {
            'taskID': TASKID_PROP,
            'confirm': CONFIRM_PROP,
            'actor': ACTOR_PROP,
        }, ['taskID', 'confirm']),

        zt_tool_schema('zentao_delete_bug', '软删除 Bug（置 deleted=1，行保留在库中）。若 Bug 已转任务，返回值 linkedTask 会给出关联任务 ID（任务不会被删除）。必须传 confirm=yes。', {
            'bugID': {'type': 'integer', 'description': 'Bug ID'},
            'confirm': CONFIRM_PROP,
            'actor': ACTOR_PROP,
        }, ['bugID', 'confirm']),

        zt_tool_schema('zentao_delete_story', '软删除需求（置 deleted=1，行保留在库中）。必须传 confirm=yes。', {
            'storyID': {'type': 'integer', 'description': '需求 ID'},
            'confirm': CONFIRM_PROP,
            'actor': ACTOR_PROP,
        }, ['storyID', 'confirm']),

        zt_tool_schema('zentao_delete_testcase', '软删除测试用例（置 deleted=1，行保留在库中）。必须传 confirm=yes。', {
            'caseID': {'type': 'integer', 'description': '用例 ID'},
            'confirm': CONFIRM_PROP,
            'actor': ACTOR_PROP,
        }, ['caseID', 'confirm']),

        zt_tool_schema('zentao_delete_doc', '软删除文档（置 deleted=1，行保留在库中）。必须传 confirm=yes。', {
            'docID': {'type': 'integer', 'description': '文档 ID'},
            'confirm': CONFIRM_PROP,
            'actor': ACTOR_PROP,
        }, ['docID', 'confirm']),

        # ---- 发布 / 测试 / 文档 ----
        zt_tool_schema('zentao_create_release', '创建产品发布（可用于 git tag/版本发布登记）。不传 build 时会自动创建同名版本（build）记录。', {
            'productID': {'type': 'integer', 'description': '所属产品 ID'},
            'branch': {'type': 'string', 'description': '分支（可选，默认主干）'},
            'name': {'type': 'string', 'description': '发布名称/版本号（如 v1.0.0）'},
            'date': {'type': 'string', 'description': '发布日期 YYYY-MM-DD'},
            'build': {'type': 'integer', 'description': '关联已有版本 ID（可选，不传则自动创建）'},
            'desc': {'type': 'string', 'description': '发布说明（可选）'},
            'stories': {'type': 'array', 'items': {'type': 'integer'}, 'description': '关联需求 ID 列表（可选）'},
            'bugs': {'type': 'array', 'items': {'type': 'integer'}, 'description': '关联 Bug ID 列表（可选）'},
            'actor': ACTOR_PROP,
        }, ['productID', 'name', 'date']),

        zt_tool_schema('zentao_create_testtask', '创建测试单（测试任务的载体）。', {
            'product': {'type': 'integer', 'description': '所属产品 ID'},
            'project': {'type': 'integer', 'description': '所属项目 ID'},
            'build': {'type': 'string', 'description': '测试版本（如 trunk 或版本号）'},
            'name': {'type': 'string', 'description': '测试单名称'},
            'begin': {'type': 'string', 'description': '开始日期 YYYY-MM-DD'},
            'end': {'type': 'string', 'description': '结束日期 YYYY-MM-DD'},
            'owner': {'type': 'string', 'description': '负责人账号（可选，默认操作人）'},
            'pri': {'type': 'integer', 'description': '优先级（可选）'},
            'desc': {'type': 'string', 'description': '描述（可选）'},
            'mailto': {'type': 'array', 'items': {'type': 'string'}, 'description': '抄送账号列表（可选）'},
            'actor': ACTOR_PROP,
        }, ['product', 'project', 'build', 'begin', 'end', 'name']),

        zt_tool_schema('zentao_testtask_flow', '测试单流转：start 开始 / close 关闭。', {
            'taskID': {'type': 'integer', 'description': '测试单 ID'},
            'action': {'type': 'string', 'description': 'start | close'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['taskID', 'action']),

        zt_tool_schema('zentao_create_testcase', '创建测试用例。steps 为步骤数组：[{type: item|step|group, desc: 步骤, expect: 预期}]。', {
            'product': {'type': 'integer', 'description': '所属产品 ID'},
            'title': {'type': 'string', 'description': '用例标题'},
            'type': {'type': 'string', 'description': '用例类型 feature/performance/config/install/security/interface/unit/other'},
            'steps': {'type': 'array', 'items': {'type': 'object'}, 'description': '步骤数组（可选）：[{type, desc, expect}]'},
            'branch': {'type': 'string', 'description': '分支（可选）'},
            'module': {'type': 'integer', 'description': '所属模块（可选）'},
            'story': {'type': 'integer', 'description': '相关需求 ID（可选）'},
            'pri': {'type': 'integer', 'description': '优先级（可选）'},
            'stage': {'type': 'array', 'items': {'type': 'string'}, 'description': '适用阶段（可选）'},
            'precondition': {'type': 'string', 'description': '前置条件（可选）'},
            'keywords': {'type': 'string', 'description': '关键词（可选）'},
            'forceNotReview': {'type': 'integer', 'description': '1=免评审直接生效（可选）'},
            'actor': ACTOR_PROP,
        }, ['product', 'title', 'type']),

        zt_tool_schema('zentao_update_testcase', '编辑测试用例（基础字段与步骤；步骤变更自动升版本进入评审）。', {
            'caseID': {'type': 'integer', 'description': '用例 ID'},
            'title': {'type': 'string', 'description': '用例标题（可选）'},
            'type': {'type': 'string', 'description': '用例类型（可选）'},
            'pri': {'type': 'integer', 'description': '优先级（可选）'},
            'stage': {'type': 'array', 'items': {'type': 'string'}, 'description': '适用阶段（可选）'},
            'precondition': {'type': 'string', 'description': '前置条件（可选）'},
            'keywords': {'type': 'string', 'description': '关键词（可选）'},
            'story': {'type': 'integer', 'description': '相关需求（可选）'},
            'module': {'type': 'integer', 'description': '所属模块（可选）'},
            'steps': {'type': 'array', 'items': {'type': 'object'}, 'description': '步骤数组（可选，传则全量替换）：[{type, desc, expect}]'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['caseID']),

        zt_tool_schema('zentao_create_doc', '创建禅道文档：lib 或 product/project 至少提供一个（用于定位文档库，产品/项目创建时自带主库）。', {
            'lib': {'type': 'integer', 'description': '文档库 ID（可选，与 product/project 二选一）'},
            'product': {'type': 'integer', 'description': '所属产品 ID（可选）'},
            'project': {'type': 'integer', 'description': '所属项目 ID（可选）'},
            'title': {'type': 'string', 'description': '文档标题'},
            'content': {'type': 'string', 'description': '文档内容'},
            'contentType': {'type': 'string', 'description': '内容格式 html（默认）或 markdown'},
            'type': {'type': 'string', 'description': '类型 text（默认）/url（可选）'},
            'keywords': {'type': 'string', 'description': '关键词（可选）'},
            'module': {'type': 'integer', 'description': '所属模块（可选）'},
            'acl': {'type': 'string', 'description': '访问控制 open/private（可选）'},
            'users': {'type': 'array', 'items': {'type': 'string'}, 'description': '白名单账号列表（可选）'},
            'groups': {'type': 'array', 'items': {'type': 'string'}, 'description': '白名单分组列表（可选）'},
            'actor': ACTOR_PROP,
        }, ['title', 'content']),

        # ---- 计划 / 账号 / 构建 ----
        zt_tool_schema('zentao_create_plan', '创建产品计划（版本规划，供需求的 plan 字段引用）。', {
            'product': {'type': 'integer', 'description': '所属产品 ID'},
            'title': {'type': 'string', 'description': '计划名称（如 v1.0）'},
            'branch': {'type': 'integer', 'description': '分支（可选，多分支产品用）'},
            'begin': {'type': 'string', 'description': '开始日期 YYYY-MM-DD（可选）'},
            'end': {'type': 'string', 'description': '结束日期 YYYY-MM-DD（可选）'},
            'future': {'type': 'integer', 'description': '1=未来计划（可选，未传起止日期时自动视为未来计划）'},
            'desc': {'type': 'string', 'description': '描述（可选）'},
            'actor': ACTOR_PROP,
        }, ['product', 'title']),

        zt_tool_schema('zentao_list_plans', '列出产品的计划（获取 plan ID）。', {
            'productID': {'type': 'integer', 'description': '产品 ID'},
        }, ['productID']),

        zt_tool_schema('zentao_list_users', '列出禅道账号（account/realname/role，供指派与团队管理使用）。'),

        zt_tool_schema('zentao_create_build', '创建版本（build）；发布/测试单可引用其 ID。', {
            'projectID': {'type': 'integer', 'description': '所属项目 ID'},
            'product': {'type': 'integer', 'description': '所属产品 ID'},
            'name': {'type': 'string', 'description': '版本名称'},
            'branch': {'type': 'integer', 'description': '分支（可选）'},
            'builder': {'type': 'string', 'description': '构建者（可选，默认操作人）'},
            'date': {'type': 'string', 'description': '日期 YYYY-MM-DD（可选，默认今天）'},
            'desc': {'type': 'string', 'description': '描述（可选）'},
            'scmPath': {'type': 'string', 'description': '源码地址（可选）'},
            'filePath': {'type': 'string', 'description': '下载地址（可选）'},
            'actor': ACTOR_PROP,
        }, ['projectID', 'product', 'name']),

        # ---- 文档 ----
        zt_tool_schema('zentao_update_doc', '编辑文档（内容/标题变更自动升版本）。', {
            'docID': {'type': 'integer', 'description': '文档 ID'},
            'title': {'type': 'string', 'description': '标题（可选）'},
            'content': {'type': 'string', 'description': '新内容（可选）'},
            'contentType': {'type': 'string', 'description': '内容格式 html（默认）/markdown'},
            'type': {'type': 'string', 'description': '类型 text（默认）/url'},
            'url': {'type': 'string', 'description': 'type=url 时的链接'},
            'keywords': {'type': 'string', 'description': '关键词（可选）'},
            'module': {'type': 'integer', 'description': '所属模块（可选）'},
            'acl': {'type': 'string', 'description': '访问控制 open/private（可选）'},
            'users': {'type': 'array', 'items': {'type': 'string'}, 'description': '白名单账号列表（可选）'},
            'groups': {'type': 'array', 'items': {'type': 'string'}, 'description': '白名单分组列表（可选）'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['docID']),

        zt_tool_schema('zentao_create_doclib', '创建文档库（自定义库；产品/项目自带主库）。', {
            'name': {'type': 'string', 'description': '库名称'},
            'libType': {'type': 'string', 'description': '类型 product/project/custom（可选，默认 custom）'},
            'product': {'type': 'integer', 'description': '所属产品（可选）'},
            'project': {'type': 'integer', 'description': '所属项目（可选）'},
            'acl': {'type': 'string', 'description': '访问控制 open/private/custom（可选）'},
            'groups': {'type': 'array', 'items': {'type': 'string'}, 'description': '白名单分组（可选）'},
            'users': {'type': 'array', 'items': {'type': 'string'}, 'description': '白名单账号（可选）'},
            'actor': ACTOR_PROP,
        }, ['name']),

        # ---- 评审 / 确认 ----
        zt_tool_schema('zentao_review_story', '评审需求：pass 通过（draft/changed→active）/ reject 驳回（需 closedReason）/ revert 回退版本（需 preVersion）/ clarify 待明确。', {
            'storyID': {'type': 'integer', 'description': '需求 ID'},
            'result': {'type': 'string', 'description': 'pass | reject | revert | clarify'},
            'comment': COMMENT_PROP,
            'assignedTo': {'type': 'string', 'description': '指派给（可选，默认保持）'},
            'reviewedBy': {'type': 'array', 'items': {'type': 'string'}, 'description': '评审人账号列表（可选，默认操作人）'},
            'closedReason': {'type': 'string', 'description': '驳回原因（reject 必需：done/subdivided/duplicate/postponed/willnotdo/cancel/bydesign）'},
            'duplicateStory': {'type': 'integer', 'description': '重复需求 ID（closedReason=duplicate 时必需）'},
            'childStories': {'type': 'string', 'description': '细分需求（closedReason=subdivided 时用）'},
            'preVersion': {'type': 'integer', 'description': '回退到的版本号（revert 必需）'},
            'actor': ACTOR_PROP,
        }, ['storyID', 'result']),

        zt_tool_schema('zentao_confirm_bug', '确认 Bug（未确认 → 已确认，可同时调整指派/类型/优先级）。', {
            'bugID': {'type': 'integer', 'description': 'Bug ID'},
            'assignedTo': {'type': 'string', 'description': '指派给（可选）'},
            'type': {'type': 'string', 'description': 'Bug 类型（可选）'},
            'pri': {'type': 'integer', 'description': '优先级（可选）'},
            'mailto': {'type': 'array', 'items': {'type': 'string'}, 'description': '抄送（可选）'},
            'comment': COMMENT_PROP,
            'actor': ACTOR_PROP,
        }, ['bugID']),

        zt_tool_schema('zentao_review_testcase', '评审测试用例：pass（→normal）/ clarify 待明确。', {
            'caseID': {'type': 'integer', 'description': '用例 ID'},
            'result': {'type': 'string', 'description': 'pass | clarify'},
            'comment': COMMENT_PROP,
            'reviewedBy': {'type': 'array', 'items': {'type': 'string'}, 'description': '评审人账号列表（可选，默认操作人）'},
            'actor': ACTOR_PROP,
        }, ['caseID', 'result']),

        # ---- 关联管理 ----
        zt_tool_schema('zentao_manage_release', '发布管理：link_story/unlink_story 关联需求、link_bug/unlink_bug 关联 Bug、change_status 变更状态（normal/terminate）。', {
            'releaseID': {'type': 'integer', 'description': '发布 ID'},
            'action': {'type': 'string', 'description': 'link_story | unlink_story | link_bug | unlink_bug | change_status'},
            'stories': {'type': 'array', 'items': {'type': 'integer'}, 'description': '需求 ID 数组（link_story 时必需）'},
            'bugs': {'type': 'array', 'items': {'type': 'integer'}, 'description': 'Bug ID 数组（link_bug 时必需）'},
            'storyID': {'type': 'integer', 'description': '需求 ID（unlink_story 时必需）'},
            'bugID': {'type': 'integer', 'description': 'Bug ID（unlink_bug 时必需）'},
            'bugType': {'type': 'string', 'description': 'bug | leftBug（unlink_bug 可选，默认 bug）'},
            'status': {'type': 'string', 'description': 'normal | terminate（change_status 时必需）'},
            'actor': ACTOR_PROP,
        }, ['releaseID', 'action']),

        zt_tool_schema('zentao_link_project_stories', '把需求关联到项目（需求须属于项目已关联的产品；已关联的自动跳过）。', {
            'projectID': {'type': 'integer', 'description': '项目 ID'},
            'stories': {'type': 'array', 'items': {'type': 'integer'}, 'description': '需求 ID 数组'},
            'actor': ACTOR_PROP,
        }, ['projectID', 'stories']),

        zt_tool_schema('zentao_unlink_project_story', '解除项目与需求的关联（该项目下该需求的等待/进行中任务会一并取消）。', {
            'projectID': {'type': 'integer', 'description': '项目 ID'},
            'storyID': {'type': 'integer', 'description': '需求 ID'},
            'actor': ACTOR_PROP,
        }, ['projectID', 'storyID']),

        zt_tool_schema('zentao_subdivide_story', '细分需求：批量创建子需求并把父需求标记为"已细分关闭"。', {
            'storyID': {'type': 'integer', 'description': '父需求 ID'},
            'children': {'type': 'array', 'items': {'type': 'object'}, 'description': '子需求数组：[{title, spec?, verify?, module?, pri?, estimate?}]'},
            'actor': ACTOR_PROP,
        }, ['storyID', 'children']),

        zt_tool_schema('zentao_link_testtask_cases', '把测试用例加入测试单（同用例重复加入会重置其执行状态）。', {
            'taskID': {'type': 'integer', 'description': '测试单 ID'},
            'cases': {'type': 'array', 'items': {'type': 'integer'}, 'description': '用例 ID 数组'},
            'type': {'type': 'string', 'description': 'all | bybuild（可选，默认 all）'},
            'actor': ACTOR_PROP,
        }, ['taskID', 'cases']),

        zt_tool_schema('zentao_list_testruns', '列出测试单中的用例执行记录（获取 runID）。', {
            'taskID': {'type': 'integer', 'description': '测试单 ID'},
        }, ['taskID']),

        zt_tool_schema('zentao_record_test_result', '记录用例执行结果（写入测试单执行记录）。', {
            'runID': {'type': 'integer', 'description': '执行记录 ID（来自 list_testruns）'},
            'steps': {'type': 'array', 'items': {'type': 'object'}, 'description': '步骤结果数组：[{stepID?, result: pass|fail|blocked|n/a, real?}]（无步骤用例用 stepID=0）'},
            'actor': ACTOR_PROP,
        }, ['runID', 'steps']),

        zt_tool_schema('zentao_get_git_commits', '读取本地 git 仓库的提交记录（只读，不同步禅道）。返回 hash/作者/邮箱/authorAccount（按本机 GIT_AUTHOR_MAP 映射的禅道账号）/时间/消息/文件列表。', {
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
