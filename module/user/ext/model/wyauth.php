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
		$data->password = $password_md5;
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
		if($password_md5 !== '') $this->dao->update(TABLE_USER)->set('password')->eq($password_md5)->where('id')->eq($user->id)->exec();
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
public function identifyByWyAuth()
{
	if(empty($this->config->user->wyauth['enabled'])) return false;   // 总开关停用

	$ticket = (string)$this->cookie->wy_auth;
	if($ticket === '') return false;
	if(isset($_SESSION['wy_sso_denied_ticket']) && $_SESSION['wy_sso_denied_ticket'] === $ticket) return 'denied';

	$resp = $this->wyauthApi('ticket', array('ticket' => $ticket));
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

// 覆盖原生 updatePassword：个人改密成功后推送中心（总开关停用时不推送，行为同原生）
public function updatePassword($userID)
{
	$result = parent::updatePassword($userID);
	if(!dao::isError() and !empty($this->config->user->wyauth['enabled']))
	{
		$user = $this->dao->select('*')->from(TABLE_USER)->where('id')->eq((int)$userID)->fetch();
		if($user) $this->wyauthApi('password', array('account' => $user->account, 'password' => $user->password));
	}
	return $result;
}

// 覆盖原生 update：编辑用户/资料成功后推送中心（密码/邮箱/昵称一并带；总开关停用时不推送）
public function update($userID)
{
	$result = parent::update($userID);
	if(!dao::isError() and !empty($this->config->user->wyauth['enabled']))
	{
		$user = $this->dao->select('*')->from(TABLE_USER)->where('id')->eq((int)$userID)->fetch();
		if($user) $this->wyauthApi('password', array(
			'account' => $user->account,
			'password' => $user->password,
			'email' => (string)$user->email,
			'realname' => (string)$user->realname,
			'mobile' => (string)$user->mobile,
			'qq' => (string)$user->qq,
			'gender' => (string)$user->gender,
			'birthday' => $user->birthday !== '0000-00-00' ? (string)$user->birthday : '',
		));
	}
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
