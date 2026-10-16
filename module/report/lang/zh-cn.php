<?php
/**
 * The report module zh-cn file of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     report
 * @version     $Id: zh-cn.php 5080 2013-07-10 00:46:59Z wyd621@gmail.com $
 * @link        http://www.zentao.net
 */
$lang->report->common     = '统计视图';
$lang->report->index      = '统计首页';
$lang->report->list       = '统计报表';
$lang->report->item       = '条目';
$lang->report->value      = '值';
$lang->report->percent    = '百分比';
$lang->report->undefined  = '未设定';
$lang->report->query      = '查询';

$lang->report->colors[]   = 'AFD8F8';
$lang->report->colors[]   = 'F6BD0F';
$lang->report->colors[]   = '8BBA00';
$lang->report->colors[]   = 'FF8E46';
$lang->report->colors[]   = '008E8E';
$lang->report->colors[]   = 'D64646';
$lang->report->colors[]   = '8E468E';
$lang->report->colors[]   = '588526';
$lang->report->colors[]   = 'B3AA00';
$lang->report->colors[]   = '008ED6';
$lang->report->colors[]   = '9D080D';
$lang->report->colors[]   = 'A186BE';

$lang->report->assign['noassign'] = '未指派';
$lang->report->assign['assign'] = '已指派';

$lang->report->singleColor[] = 'F6BD0F';

$lang->report->projectDeviation = $lang->projectCommon . '偏差报表';
$lang->report->productSummary   = $lang->productCommon . '汇总表';
$lang->report->bugCreate        = 'Bug创建表';
$lang->report->bugAssign        = 'Bug指派表';
$lang->report->workload         = '员工负载表';
$lang->report->workloadAB       = '工作负载';
$lang->report->bugOpenedDate    = 'Bug创建时间';
$lang->report->taskAssignedDate = '起止时间';
$lang->report->beginAndEnd      = '起止时间';
$lang->report->dept             = '部门';
$lang->report->deviationChart   = $lang->projectCommon . '偏差曲线';

$lang->reportList          = new stdclass();
$lang->reportList->project = new stdclass();
$lang->reportList->project->lists[10] = $lang->projectCommon . '偏差报表|pivot|show|pivotID=projectdeviation';
$lang->reportList->product = new stdclass();
$lang->reportList->product->lists[10] = $lang->productCommon . '汇总表|pivot|show|pivotID=productsummary';
$lang->reportList->test    = new stdclass();
$lang->reportList->test->lists[10]    = 'Bug创建表|pivot|show|pivotID=bugscreate';
$lang->reportList->test->lists[13]    = 'Bug指派表|pivot|show|pivotID=bugsassign';
$lang->reportList->staff   = new stdclass();
$lang->reportList->staff->lists[10]   = '员工负载表|pivot|show|pivotID=workload';
$lang->reportList->staff->lists[13]   = '年度总结|report|annualData';

$lang->report->id            = '编号';
$lang->report->project       = $lang->projectCommon;
$lang->report->product       = $lang->productCommon;
$lang->report->user          = '姓名';
$lang->report->bugTotal      = 'Bug';
$lang->report->task          = '任务数';
$lang->report->estimate      = '总预计';
$lang->report->consumed      = '总消耗';
$lang->report->remain        = '剩余工时';
$lang->report->deviation     = '偏差';
$lang->report->deviationRate = '偏差率';
$lang->report->total         = '总计';
$lang->report->to            = '至';
$lang->report->taskTotal     = "总任务数";
$lang->report->manhourTotal  = "总工时";
$lang->report->validRate     = "有效率";
$lang->report->validRateTips = "方案为已解决或延期/状态为已解决或已关闭";
$lang->report->unplanned     = '未计划';
$lang->report->workday       = '每天工时';
$lang->report->diffDays      = '工作日天数';

$lang->report->typeList['']     = '默认';
$lang->report->typeList['pie']  = '饼图';
$lang->report->typeList['bar']  = '柱状图';
$lang->report->typeList['line'] = '折线图';

$lang->report->conditions    = '筛选条件：';
$lang->report->closedProduct = '关闭' . $lang->productCommon;
$lang->report->overduePlan   = '过期计划';

/* daily reminder. */
$lang->report->idAB         = 'ID';
$lang->report->bugTitle     = 'Bug标题';
$lang->report->taskName     = '任务名称';
$lang->report->todoName     = '待办名称';
$lang->report->testTaskName = '版本名称';
$lang->report->deadline     = '截止日期';

$lang->report->mailTitle           = new stdclass();
$lang->report->mailTitle->begin    = '提醒：您有';
$lang->report->mailTitle->bug      = " Bug(%s),";
$lang->report->mailTitle->task     = " 任务(%s),";
$lang->report->mailTitle->todo     = " 待办(%s),";
$lang->report->mailTitle->testTask = " 测试版本(%s),";

$lang->report->proVersion = '<a href="http://api.zentao.net/goto.php?item=proversion&from=reportpage" target="_blank">更多精彩，尽在专业版！</a>';
$lang->report->proVersionEn = '<a href="http://api.zentao.pm/goto.php?item=proversion&from=reportpage" target="_blank">Try ZenTao Pro for more!</a>';

/* 年度总结（9.10.0）。 */
$lang->report->annualData = new stdclass();
$lang->report->annualData->title             = '%s 年度总结';
$lang->report->annualData->allUser           = '所有人';
$lang->report->annualData->allDept           = '所有部门';
$lang->report->annualData->basic             = '基本数据';
$lang->report->annualData->contributionBlock = '贡献与能力';
$lang->report->annualData->trendBlock        = '月度操作趋势';
$lang->report->annualData->status          = '状态';
$lang->report->annualData->statusBlock       = '状态分布';
$lang->report->annualData->logins            = '登录次数';
$lang->report->annualData->actions           = '操作数';
$lang->report->annualData->todoUndone        = '进行中';
$lang->report->annualData->todoDone          = '已完成';
$lang->report->annualData->efforts           = '工时（小时）';
$lang->report->annualData->effortCount       = '工时记录';
$lang->report->annualData->contributionCount = '贡献总数';
$lang->report->annualData->radar             = '能力雷达';
$lang->report->annualData->statusHint        = '按本年新建对象的当前状态统计';
$lang->report->annualData->trendHint         = '按本年每月产生的动态数统计';
$lang->report->annualData->noData            = '该年度暂无数据';
$lang->report->annualData->radarTypes = array('product' => '产品', 'execution' => '项目', 'devel' => '研发', 'qa' => '测试', 'other' => '其它');
$lang->report->annualData->objects    = array('story' => '需求', 'task' => '任务', 'bug' => 'Bug', 'case' => '用例');
