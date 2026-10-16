<?php
/**
 * The zh-cn file of pivot module of ZenTaoPMS (WenYinOS community build).
 *
 * 只取渲染所需的键；设计器/图表/版本相关键一律不搬。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     pivot
 */

$lang->pivot = new stdclass();
$lang->pivot->common        = '透视表';
$lang->pivot->index         = '透视表首页';
$lang->pivot->preview       = '透视表';
$lang->pivot->previewTitle  = '透视表列表';
$lang->pivot->show          = '透视表详情';
$lang->pivot->list          = '透视表';
$lang->pivot->id            = '编号';
$lang->pivot->name          = '名称';
$lang->pivot->desc          = '描述';
$lang->pivot->group         = '分组';
$lang->pivot->builtin       = '内置';
$lang->pivot->total         = '总计';
$lang->pivot->colLabel      = '{$field}的{$stat}';

$lang->pivot->stepDesign = new stdclass();
$lang->pivot->stepDesign->statList = array();
$lang->pivot->stepDesign->statList['']         = '';
$lang->pivot->stepDesign->statList['count']    = '计数';
$lang->pivot->stepDesign->statList['distinct'] = '去重后计数';
$lang->pivot->stepDesign->statList['avg']      = '平均值';
$lang->pivot->stepDesign->statList['sum']      = '求和';
$lang->pivot->stepDesign->statList['max']      = '最大值';
$lang->pivot->stepDesign->statList['min']      = '最小值';
$lang->pivot->colShowMode   = '(%s)';
$lang->pivot->empty         = '暂无数据。';
$lang->pivot->noPivot       = '还没有透视表。';
$lang->pivot->backToReport  = '返回旧版报表';
$lang->pivot->rowTotal      = '行汇总';
$lang->pivot->columnTotal   = '列汇总';
$lang->pivot->allGroups     = '全部分组';
$lang->pivot->queryFailed   = '取数失败，请检查透视表的 SQL。';

$lang->pivot->showModeList = array();
$lang->pivot->showModeList['default'] = '默认（数值）';
$lang->pivot->showModeList['total']   = '总计百分比';
$lang->pivot->showModeList['row']     = '行汇总百分比';
$lang->pivot->showModeList['column']  = '列汇总百分比';

$lang->pivot->columnPositionList = array();
$lang->pivot->columnPositionList['bottom'] = '仅在整张表下方展示';
$lang->pivot->columnPositionList['row']    = '仅在每层行分组下方展示';
$lang->pivot->columnPositionList['all']    = '在整张表与每层行分组下方均展示';

$lang->pivot->columnTotalList = array();
$lang->pivot->columnTotalList['noShow'] = '不显示';
$lang->pivot->columnTotalList['sum']    = '求和';

$lang->pivot->error = new stdclass();
$lang->pivot->error->noPivot    = '没找到该透视表。';
$lang->pivot->error->queryFail  = '透视表取数失败。';

$lang->pivot->stepDesign->total = '总计';
$lang->pivot->stepDesign->showModeList = array();
$lang->pivot->stepDesign->showModeList['default'] = '默认（数值）';
$lang->pivot->stepDesign->showModeList['total']   = '总计百分比';
$lang->pivot->stepDesign->showModeList['row']     = '行汇总百分比';
$lang->pivot->stepDesign->showModeList['column']  = '列汇总百分比';
