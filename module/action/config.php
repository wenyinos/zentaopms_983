<?php
$config->action->objectNameFields['product']     = 'name';
$config->action->objectNameFields['story']       = 'title';
$config->action->objectNameFields['productplan'] = 'title';
$config->action->objectNameFields['release']     = 'name';
$config->action->objectNameFields['project']     = 'name';
$config->action->objectNameFields['task']        = 'name';
$config->action->objectNameFields['build']       = 'name';
$config->action->objectNameFields['bug']         = 'title';
$config->action->objectNameFields['testcase']    = 'title';
$config->action->objectNameFields['case']        = 'title';
$config->action->objectNameFields['testtask']    = 'name';
$config->action->objectNameFields['user']        = 'account';
$config->action->objectNameFields['doc']         = 'title';
$config->action->objectNameFields['doclib']      = 'name';
$config->action->objectNameFields['todo']        = 'name';
$config->action->objectNameFields['branch']      = 'name';
$config->action->objectNameFields['module']      = 'name';
$config->action->objectNameFields['testsuite']   = 'name';
$config->action->objectNameFields['caselib']     = 'name';
$config->action->objectNameFields['testreport']  = 'title';
$config->action->objectNameFields['entry']       = 'name';
$config->action->objectNameFields['webhook']     = 'name';

$config->action->commonImgSize = 870;

/* 10.1 动态流：重点动作列表（时间线上高亮显示，见 transformActions 的 major 标记） */
$config->action->majorList = array();
$config->action->majorList['task']    = array('assigned', 'finished', 'activated');
$config->action->majorList['bug']     = array('assigned', 'resolved');
$config->action->majorList['release'] = array('opened');
$config->action->majorList['build']   = array('opened');
