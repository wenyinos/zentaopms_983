<?php
/**
 * The zh-tw file of pivot module of ZenTaoPMS (WenYinOS community build).
 *
 * 只取渲染所需的键；设计器/图表/版本相关键一律不搬。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     pivot
 */

$lang->pivot = new stdclass();
$lang->pivot->common        = '透視表';
$lang->pivot->index         = '透視表首页';
$lang->pivot->preview       = '透視表';
$lang->pivot->previewTitle  = '透視表列表';
$lang->pivot->show          = '透視表详情';
$lang->pivot->list          = '透視表';
$lang->pivot->id            = '編號';
$lang->pivot->name          = '名稱';
$lang->pivot->desc          = '描述';
$lang->pivot->group         = '分組';
$lang->pivot->builtin       = '內置';
$lang->pivot->total         = '總計';
$lang->pivot->colLabel      = '{$field}的{$stat}';

$lang->pivot->stepDesign = new stdclass();
$lang->pivot->stepDesign->statList = array();
$lang->pivot->stepDesign->statList['']         = '';
$lang->pivot->stepDesign->statList['count']    = '計數';
$lang->pivot->stepDesign->statList['distinct'] = '去重後計數';
$lang->pivot->stepDesign->statList['avg']      = '平均值';
$lang->pivot->stepDesign->statList['sum']      = '求和';
$lang->pivot->stepDesign->statList['max']      = '最大值';
$lang->pivot->stepDesign->statList['min']      = '最小值';
$lang->pivot->colShowMode   = '(%s)';
$lang->pivot->empty         = '暫無資料。';
$lang->pivot->noPivot       = '還沒有透視表。';
$lang->pivot->backToReport  = '返回舊版報表';
$lang->pivot->rowTotal      = '行匯總';
$lang->pivot->columnTotal   = '列匯總';
$lang->pivot->allGroups     = '全部分組';
$lang->pivot->queryFailed   = '取數失敗，請檢查透視表的 SQL。';

$lang->pivot->showModeList = array();
$lang->pivot->showModeList['default'] = '預設（數值）';
$lang->pivot->showModeList['total']   = '總計百分比';
$lang->pivot->showModeList['row']     = '行匯總百分比';
$lang->pivot->showModeList['column']  = '列匯總百分比';

$lang->pivot->columnPositionList = array();
$lang->pivot->columnPositionList['bottom'] = '僅在整張表下方展示';
$lang->pivot->columnPositionList['row']    = '仅在每层行分組下方展示';
$lang->pivot->columnPositionList['all']    = '在整张表与每层行分組下方均展示';

$lang->pivot->columnTotalList = array();
$lang->pivot->columnTotalList['noShow'] = '不顯示';
$lang->pivot->columnTotalList['sum']    = '求和';

$lang->pivot->error = new stdclass();
$lang->pivot->error->noPivot    = '没找到该透視表。';
$lang->pivot->error->queryFail  = '透視表取數失敗。';

$lang->pivot->stepDesign->total = '總計';
$lang->pivot->stepDesign->showModeList = array();
$lang->pivot->stepDesign->showModeList['default'] = '預設（數值）';
$lang->pivot->stepDesign->showModeList['total']   = '總計百分比';
$lang->pivot->stepDesign->showModeList['row']     = '行匯總百分比';
$lang->pivot->stepDesign->showModeList['column']  = '列匯總百分比';
