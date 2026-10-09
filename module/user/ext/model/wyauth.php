<?php
// wenyinos 统一认证中心接入 · user 模型扩展方法
// 本文件内容被框架合并进 extuserModel（module/user/ext/model/）

// 调用中心 API；返回解析后的响应数组；网络不可达/非 JSON 返回 null（触发降级语义）
public function wyauthApi($action, $data)
{
	$cfg = $this->config->user->wyauth;
	$body = json_encode(array('action' => $action, 'data' => $data), JSON_UNESCAPED_UNICODE);
	if($body === false) return null;

	$timestamp = time();
	$nonce = bin2hex(random_bytes(8));
	$sign = hash_hmac('sha256', $cfg['appId'] . '|' . $action . '|' . md5($body) . '|' . $timestamp . '|' . $nonce, $cfg['secret']);

	$headers = array(
		'Content-Type: application/json',
		'X-Wy-App: ' . $cfg['appId'],
		'X-Wy-Timestamp: ' . $timestamp,
		'X-Wy-Nonce: ' . $nonce,
		'X-Wy-Sign: ' . $sign,
	);

	if(function_exists('curl_init'))
	{
		$ch = curl_init($cfg['apiUrl']);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => intval($cfg['timeout']),
			CURLOPT_CONNECTTIMEOUT => intval($cfg['timeout']),
		));
		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		curl_close($ch);
		if($errno !== 0 || $response === false) return null;
	}
	else
	{
		$ctx = stream_context_create(array('http' => array(
			'method' => 'POST',
			'header' => implode("\r\n", $headers),
			'content' => $body,
			'timeout' => intval($cfg['timeout']),
			'ignore_errors' => true,
		)));
		$response = @file_get_contents($cfg['apiUrl'], false, $ctx);
		if($response === false) return null;
	}

	$result = json_decode($response, true);
	if(!is_array($result)) return null;
	return $result;
}

// ============ 密码双层哈希（bcrypt(md5(明文))，安全报告 H-1） ============
// 历史存储为无盐 md5(明文)；已离线批量升级为双层哈希。
// 以下三个辅助方法供本类及 hook 调用（public 以便 checkPriv hook 经 loadModel 访问）。

public function wyauthPasswordHash($password_md5)
{
	return password_hash((string)$password_md5, PASSWORD_BCRYPT);
}

public function wyauthPasswordVerify($password_md5, $stored)
{
	$stored = (string)$stored;
	if(strlen($stored) === 32) return $stored === (string)$password_md5;   // 兼容历史 md5 值（纵深防御）
	return password_verify((string)$password_md5, $stored);
}

// 写后修正：原生写入路径（admin 建号/改用户等）若落库为 32 位 md5，原地升级为双层哈希（幂等）
public function wyauthFixStoredPassword($account)
{
	$account = trim((string)$account);
	if($account === '') return;
	$record = $this->dao->select('id,password')->from(TABLE_USER)->where('account')->eq($account)->fetch();
	if(!$record) return;
	if(strlen((string)$record->password) === 32 and ctype_xdigit((string)$record->password))
	{
		$this->dao->update(TABLE_USER)->set('password')->eq($this->wyauthPasswordHash($record->password))->where('id')->eq($record->id)->exec();
	}
}

// 用户 upsert（中心权威）：不存在则建号（visits=1），存在则按需同步密码；
// 并按 zentao_groups 数组重写 zt_usergroup；返回最新 user 记录
public function wyauthUpsert($payload)
{
	$password_md5 = isset($payload['__password_md5']) ? $payload['__password_md5'] : '';
	unset($payload['__password_md5']);

	$account = isset($payload['username']) ? trim($payload['username']) : '';
	if($account === '') return false;

	$user = $this->dao->select('*')->from(TABLE_USER)->where('account')->eq($account)->fetch();
	$now = $this->server->request_time;

	if(!$user)
	{
		$data = new stdclass();
		$data->account  = $account;
		$data->password = $password_md5 !== '' ? $this->wyauthPasswordHash($password_md5) : '';
		$data->realname = !empty($payload['realname']) ? $payload['realname'] : $account;
		$data->email    = isset($payload['email']) ? $payload['email'] : '';
		$data->role     = 'dev';
		$data->commiter = '';
		$data->visits   = 1;              // 绕过首次登录强制改密
		// 入职日期 = 中心注册日期（中心 create_date 时间戳；缺省保持 0000-00-00）
		$data->join     = !empty($payload['create_date']) ? date('Y-m-d', intval($payload['create_date'])) : '0000-00-00';
		$this->dao->insert(TABLE_USER)->data($data)->exec();
		if(dao::isError()) return false;
	}
	else
	{
		if($password_md5 !== '') $this->dao->update(TABLE_USER)->set('password')->eq($this->wyauthPasswordHash($password_md5))->where('id')->eq($user->id)->exec();
		if(intval($user->visits) == 0) $this->dao->update(TABLE_USER)->set('visits')->eq(1)->where('id')->eq($user->id)->exec();

		// 中心权威回传：昵称/邮箱以中心为准同步到本地
		if(!empty($payload['realname']) && $payload['realname'] !== $user->realname)
		{
			$this->dao->update(TABLE_USER)->set('realname')->eq($payload['realname'])->where('id')->eq($user->id)->exec();
		}
		if(isset($payload['email']) && (string)$payload['email'] !== '' && (string)$payload['email'] !== (string)$user->email)
		{
			$this->dao->update(TABLE_USER)->set('email')->eq($payload['email'])->where('id')->eq($user->id)->exec();
		}
		// 基本资料回传（非空才覆盖：中心空值不抹掉本地数据）
		if(!empty($payload['mobile']) && (string)$payload['mobile'] !== (string)$user->mobile)
		{
			$this->dao->update(TABLE_USER)->set('mobile')->eq($payload['mobile'])->where('id')->eq($user->id)->exec();
		}
		if(!empty($payload['qq']) && (string)$payload['qq'] !== (string)$user->qq)
		{
			$this->dao->update(TABLE_USER)->set('qq')->eq($payload['qq'])->where('id')->eq($user->id)->exec();
		}
		if(isset($payload['gender']) && in_array($payload['gender'], array('f', 'm'), true) && $payload['gender'] !== $user->gender)
		{
			$this->dao->update(TABLE_USER)->set('gender')->eq($payload['gender'])->where('id')->eq($user->id)->exec();
		}
		if(!empty($payload['birthday']) && (string)$payload['birthday'] !== (string)$user->birthday)
		{
			$this->dao->update(TABLE_USER)->set('birthday')->eq($payload['birthday'])->where('id')->eq($user->id)->exec();
		}
	}

	// 统计与最后登录（与原生 identify 行为一致）
	$this->dao->update(TABLE_USER)->set('visits = visits + 1')->set('ip')->eq($this->server->remote_addr)->set('last')->eq($now)->where('account')->eq($account)->exec();

	// 按中心的 zentao_groups 数组重写用户组（中心权威）
	$groups = isset($payload['zentao_groups']) && is_array($payload['zentao_groups']) ? $payload['zentao_groups'] : array();
	$this->dao->delete()->from(TABLE_USERGROUP)->where('account')->eq($account)->exec();
	foreach($groups as $gid)
	{
		$gid = intval($gid);
		if($gid <= 0) continue;
		$row = new stdclass();
		$row->account = $account;
		$row->group   = $gid;
		$this->dao->insert(TABLE_USERGROUP)->data($row)->exec();
	}

	return $this->dao->select('*')->from(TABLE_USER)->where('account')->eq($account)->fetch();
}

// 登录路径：中心 verify + 同步。返回 'ok'（已同步，走原生逻辑命中）/ 'deny'（中心明确拒绝）/ 'fallback'（中心不可达，降级本地）
public function wyauthVerifyAndSync($account, $password_md5)
{
	$payload = array('account' => $account, 'password' => $password_md5);
	$resp = $this->wyauthApi('verify', $payload);
	if($resp === null) return 'fallback';

	$code = isset($resp['code']) ? intval($resp['code']) : -1;
	if($code === 0)
	{
		$data = $resp['data'];
		$data['__password_md5'] = $password_md5;
		return $this->wyauthUpsert($data) ? 'ok' : 'fallback';
	}
	if($code >= 1000 && $code < 2000) return 'deny';   // 业务明确拒绝（不存在/密码错/禁用/未授权/锁定）
	return 'fallback';                                  // 协议/签名异常（2xxx/3xxx）：本地兜底，避免配置问题导致全站不可登录
}

// 登录态继承：中心票据兑换（模仿 identifyByCookie 流程）
// 返回三态：true=已同步 / 'denied'=中心业务拒绝（未开通/禁用/不存在，调用方定向回中心）/
//          false=静默（票据无效/过期/中心不可达）
// 负缓存按票据值记录：同一业务拒绝票据不重复请求中心（命中即 denied）；
// 换新票据（重新登录中心）后自动重试；中心退出（票据清除）后本方法不再触发
public function identifyByWyAuth($resp = false)
{
	if(empty($this->config->user->wyauth['enabled'])) return false;   // 总开关停用

	$ticket = (string)$this->cookie->wy_auth;
	if($ticket === '') return false;
	if(isset($_SESSION['wy_sso_denied_ticket']) && $_SESSION['wy_sso_denied_ticket'] === $ticket) return 'denied';

	if($resp === false) $resp = $this->wyauthApi('ticket', array('ticket' => $ticket));   // 调用方可传入已获取的响应，避免重复请求
	if($resp === null) return false;

	$code = isset($resp['code']) ? intval($resp['code']) : -1;
	if($code !== 0)
	{
		if($code > 0 && $code < 2000)
		{
			// 业务拒绝（1004 未开通 / 1003 禁用 / 1001 不存在）：按票据缓存，调用方每次访问定向回中心
			$_SESSION['wy_sso_denied_ticket'] = $ticket;
			return 'denied';
		}
		return false;   // 票据无效/过期（2xxx）与协议异常（3xxx）：静默游客，不缓存
	}

	$user = $this->wyauthUpsert($resp['data']);
	if(!$user) return false;

	unset($_SESSION['wy_sso_denied_ticket']);
	$_SESSION['wy_sso_ticket_cur'] = $ticket;   // 记录本次票据（供一致性比对）
	$user->lastTime       = $user->last;
	$user->admin          = strpos($this->app->company->admins, ",{$user->account},") !== false;
	$user->modifyPassword = false;   // upsert 已保证 visits>=1，跳过强制改密
	$user->rights = $this->authorize($user->account);
	$user->groups = $this->getGroups($user->account);
	$this->session->set('user', $user);
	$this->app->user = $this->session->user;
	$this->loadModel('action')->create('user', $user->id, 'login');
	$this->loadModel('common')->loadConfigFromDB();
	$this->keepLogin($user);
	return true;
}

// 登出：销毁中心票据（服务端黑名单；不清 wy_auth cookie，避免影响其他分站的进行中会话）
public function wyauthRevoke()
{
	if(empty($this->config->user->wyauth['enabled'])) return;   // 总开关停用

	$ticket = $this->cookie->wy_auth;
	if(empty($ticket)) return;
	$this->wyauthApi('revoke', array('ticket' => (string)$ticket));
}

// ============ 反向同步：分站改密/改资料推回中心 ============

// 覆盖原生 updatePassword：个人改密成功后推送中心（携带旧密码校验），并升级库值为双层哈希
public function updatePassword($userID)
{
	$result = parent::updatePassword($userID);
	if(!dao::isError())
	{
		// parent 刚写入的密码为 md5(明文)（32 位）：先行推送中心，再升级为双层
		$user = $this->dao->select('*')->from(TABLE_USER)->where('id')->eq((int)$userID)->fetch();
		if($user)
		{
			if(!empty($this->config->user->wyauth['enabled']))
			{
				$payload = array('account' => $user->account, 'password' => $user->password);
				$old = isset($this->post->originalPassword) ? (string)$this->post->originalPassword : '';
				if($old !== '') $payload['old_password'] = md5($old);   // H-2：自助改密携带旧密码校验
				$this->wyauthApi('password', $payload);
			}
			$this->wyauthFixStoredPassword($user->account);
		}
	}
	return $result;
}

// 覆盖原生 update：编辑用户/资料成功后推送中心（密码仅在本次改密时携带；总开关停用时不推送）
public function update($userID)
{
	$result = parent::update($userID);
	if(!dao::isError() and !empty($this->config->user->wyauth['enabled']))
	{
		$user = $this->dao->select('*')->from(TABLE_USER)->where('id')->eq((int)$userID)->fetch();
		if($user)
		{
			$payload = array(
				'account' => $user->account,
				'email' => (string)$user->email,
				'realname' => (string)$user->realname,
				'mobile' => (string)$user->mobile,
				'qq' => (string)$user->qq,
				'gender' => (string)$user->gender,
				'birthday' => $user->birthday !== '0000-00-00' ? (string)$user->birthday : '',
			);
			// 密码仅在本次提交了改密时携带（parent 刚写库为 md5(明文) 32 位；admin 场景无旧密码，不带校验）
			if(isset($this->post->password1) and $this->post->password1 != false) $payload['password'] = $user->password;
			$this->wyauthApi('password', $payload);
		}
	}

	// 写后修正：parent 若把密码写为 32 位 md5（admin 改密），升级为双层哈希
	$fixedUser = $this->dao->select('account')->from(TABLE_USER)->where('id')->eq((int)$userID)->fetch();
	if($fixedUser) $this->wyauthFixStoredPassword($fixedUser->account);

	return $result;
}

// 覆盖原生 authorize 的 guest 分支：内置"未登录访问"（游客）的权限源原生按组名 'guest' 查找，
// 组名已中文化为「注册用户」，改为按固定组 ID 查找；其余账号完全走原生逻辑
public function authorize($account)
{
	if($account != 'guest') return parent::authorize($account);

	// 与原生 guest 分支同构，仅将 name 查找改为 id（11 = 注册用户组，见 production-zentao-groups.sql）
	$rights = array();
	$acl    = $this->dao->select('acl')->from(TABLE_GROUP)->where('id')->eq(11)->fetch('acl');
	$acls   = empty($acl) ? array() : json_decode($acl, true);

	$sql = $this->dao->select('module, method')->from(TABLE_GROUP)->alias('t1')
		->leftJoin(TABLE_GROUPPRIV)->alias('t2')->on('t1.id = t2.group')
		->where('t1.id')->eq(11);

	$stmt = $sql->query();
	if(!$stmt) return array('rights' => $rights, 'acls' => $acls);
	while($row = $stmt->fetch(PDO::FETCH_ASSOC))
	{
		$rights[strtolower($row['module'])][strtolower($row['method'])] = true;
	}
	return array('rights' => $rights, 'acls' => $acls);
}

// 覆盖原生 create：admin 建号后若密码落库为 32 位 md5（原生写法），写后修正为双层哈希
public function create()
{
	$result = parent::create();

	$account = isset($this->post->account) ? trim((string)$this->post->account) : '';
	if($account !== '') $this->wyauthFixStoredPassword($account);

	return $result;
}

// 覆盖原生 identify：本地验证支持双层哈希（bcrypt(md5(明文))）。
// 原生 SQL 以 password = md5($password) 预筛，双层值无法命中，故改为先按账号取记录、在 PHP 层校验；
// 32 位（auth hash）与 40 位（sha1）路径保持原生逻辑（库值作为"密钥"参与计算，与格式无关）
public function identify($account, $password)
{
	if(!$account or !$password) return false;

	/* 先按账号取出记录（密码在下方 PHP 层校验） */
	$record = $this->dao->select('*')->from(TABLE_USER)
		->where('account')->eq($account)
		->andWhere('deleted')->eq(0)
		->fetch();

	/* If the length of $password is 32 or 40, checking by the auth hash. */
	$user = false;
	if($record)
	{
		$passwordLength = strlen($password);
		if($passwordLength < 32)
		{
			// 明文交互登录：双层哈希校验（兼容历史 32 位 md5 值）
			if($this->wyauthPasswordVerify(md5($password), $record->password)) $user = $record;
		}
		elseif($passwordLength == 32)
		{
			$hash = $this->session->rand ? md5($record->password . $this->session->rand) : $record->password;
			$user = $password == $hash ? $record : '';
		}
		elseif($passwordLength == 40)
		{
			$hash = sha1($record->account . $record->password . $record->last);
			$user = $password == $hash ? $record : '';
		}
		if(!$user and md5($password) == $record->password) $user = $record;
	}

	if($user)
	{
		$ip   = $this->server->remote_addr;
		$last = $this->server->request_time;

		$user->lastTime       = $user->last;
		$user->last           = date(DT_DATETIME1, $last);
		$user->admin          = strpos($this->app->company->admins, ",{$user->account},") !== false;
		$user->modifyPassword = ($user->visits == 0 and !empty($this->config->safe->modifyPasswordFirstLogin));
		if($user->modifyPassword) $user->modifyPasswordReason = 'modifyPasswordFirstLogin';
		if(!$user->modifyPassword and !empty($this->config->safe->changeWeak))
		{
			$user->modifyPassword = $this->loadModel('admin')->checkWeak($user);
			if($user->modifyPassword) $user->modifyPasswordReason = 'weak';
		}

		$this->dao->update(TABLE_USER)->set('visits = visits + 1')->set('ip')->eq($ip)->set('last')->eq($last)->where('account')->eq($account)->exec();

		/* Create cycle todo in login. */
		$todoList = $this->dao->select('*')->from(TABLE_TODO)->where('cycle')->eq(1)->andWhere('account')->eq($user->account)->fetchAll('id');
		$this->loadModel('todo')->createByCycle($todoList);
	}
	return $user;
}
