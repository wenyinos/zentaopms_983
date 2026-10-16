<?php
/* Open daily reminder.*/
$config->report                          = new stdclass();
$config->report->dailyreminder           = new stdclass();
$config->report->dailyreminder->bug      = true;
$config->report->dailyreminder->task     = true;
$config->report->dailyreminder->todo     = true;
$config->report->dailyreminder->testTask = true;

/* 年度总结（9.10.0 移植自 22.6 report|annualData）。
   按本仓表结构改写：execution → project；无 vision/shadow/isTpl 等列；
   工时口径为 zt_taskestimate（本仓 zt_effort 是"我的地盘-日志"表，语义不同）。 */
$config->report->annualData['minMonth'] = 2;
$config->report->annualData['colors']   = array('#6f42c1', '#22AC38', '#CAAC32', '#2B4D6D', '#0071a4', '#00a0e9', '#7ecef4');

$config->report->annualData['contributions'] = array(
    'product'     => array('opened' => 'create', 'edited' => 'edit', 'closed' => 'close'),
    'story'       => array('opened' => 'create', 'reviewed' => 'review', 'closed' => 'close', 'gitcommited' => 'gitCommit', 'svncommited' => 'svnCommit'),
    'productplan' => array('opened' => 'create'),
    'release'     => array('opened' => 'create'),
    'project'     => array('opened' => 'create', 'edited' => 'edit', 'started' => 'start', 'closed' => 'close'),
    'task'        => array('opened' => 'create', 'assigned' => 'assign', 'finished' => 'finish', 'activated' => 'activate', 'closed' => 'close', 'gitcommited' => 'gitCommit', 'svncommited' => 'svnCommit'),
    'bug'         => array('opened' => 'create', 'resolved' => 'resolve', 'closed' => 'close', 'activated' => 'activate', 'gitcommited' => 'gitCommit', 'svncommited' => 'svnCommit'),
    'build'       => array('opened' => 'create'),
    'case'        => array('opened' => 'create', 'run' => 'run'),
    'testtask'    => array('opened' => 'create', 'edited' => 'edit'),
    'doc'         => array('created' => 'create', 'edited' => 'edit'),
);

$config->report->annualData['contributionCount'] = array(
    'task'     => array('opened' => 'create', 'assigned' => 'assign', 'finished' => 'finish', 'closed' => 'close', 'canceled' => 'cancel'),
    'story'    => array('opened' => 'create', 'reviewed' => 'review', 'closed' => 'close', 'assigned' => 'assign'),
    'bug'      => array('opened' => 'create', 'resolved' => 'resolve', 'closed' => 'close', 'assigned' => 'assign'),
    'case'     => array('opened' => 'create'),
    'testtask' => array('closed' => 'close'),
    'doc'      => array('saveddraft' => 'create', 'releaseddoc' => 'create', 'edited' => 'edit'),
);

/* 贡献 → 能力雷达五轴（产品/项目/研发/测试/其它）。 */
$config->report->annualData['radarTypes'] = array('product', 'execution', 'devel', 'qa', 'other');
$config->report->annualData['radar'] = array(
    'product'     => array('create' => array('product'), 'edit' => array('product'), 'close' => array('product')),
    'story'       => array('create' => array('product'), 'close' => array('product'), 'review' => array('product'), 'gitCommit' => array('product'), 'svnCommit' => array('product')),
    'productplan' => array('create' => array('product')),
    'release'     => array('create' => array('product')),
    'project'     => array('create' => array('execution'), 'edit' => array('execution'), 'start' => array('execution'), 'close' => array('execution')),
    'build'       => array('create' => array('execution')),
    'task'        => array('create' => array('execution', 'devel'), 'assign' => array('execution', 'devel'), 'finish' => array('devel'), 'activate' => array('devel'), 'close' => array('devel'), 'gitCommit' => array('devel'), 'svnCommit' => array('devel')),
    'bug'         => array('create' => array('qa'), 'resolve' => array('devel'), 'close' => array('qa'), 'activate' => array('qa'), 'gitCommit' => array('devel'), 'svnCommit' => array('devel')),
    'case'        => array('create' => array('qa'), 'run' => array('qa')),
    'testtask'    => array('create' => array('qa'), 'edit' => array('qa')),
    'doc'         => array('create' => array('other'), 'edit' => array('other')),
);

/* 月度操作趋势与状态分布覆盖的对象。 */
$config->report->annualData['objects'] = array('story', 'task', 'bug', 'case');
