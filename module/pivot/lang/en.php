<?php
/**
 * The en file of pivot module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     pivot
 */

$lang->pivot = new stdclass();
$lang->pivot->common        = 'Pivot';
$lang->pivot->index         = 'Pivot Home';
$lang->pivot->preview       = 'Pivot';
$lang->pivot->previewTitle  = 'Pivot List';
$lang->pivot->show          = 'Pivot Detail';
$lang->pivot->list          = 'Pivot';
$lang->pivot->id            = 'ID';
$lang->pivot->name          = 'Name';
$lang->pivot->desc          = 'Description';
$lang->pivot->group         = 'Group';
$lang->pivot->builtin       = 'Built-in';
$lang->pivot->total         = 'Total';
$lang->pivot->colLabel      = '{$field} of {$stat}';

$lang->pivot->stepDesign = new stdclass();
$lang->pivot->stepDesign->statList = array();
$lang->pivot->stepDesign->statList['']         = '';
$lang->pivot->stepDesign->statList['count']    = 'Count';
$lang->pivot->stepDesign->statList['distinct'] = 'Distinct count';
$lang->pivot->stepDesign->statList['avg']      = 'Average';
$lang->pivot->stepDesign->statList['sum']      = 'Sum';
$lang->pivot->stepDesign->statList['max']      = 'Max';
$lang->pivot->stepDesign->statList['min']      = 'Min';
$lang->pivot->colShowMode   = '(%s)';
$lang->pivot->empty         = 'No data.';
$lang->pivot->noPivot       = 'No pivot yet.';
$lang->pivot->backToReport  = 'Back to legacy reports';
$lang->pivot->rowTotal      = 'Row total';
$lang->pivot->columnTotal   = 'Column total';
$lang->pivot->allGroups     = 'All groups';
$lang->pivot->queryFailed   = 'Query failed, please check the pivot SQL.';

$lang->pivot->showModeList = array();
$lang->pivot->showModeList['default'] = 'Default (value)';
$lang->pivot->showModeList['total']   = 'Percent of total';
$lang->pivot->showModeList['row']     = 'Percent of row';
$lang->pivot->showModeList['column']  = 'Percent of column';

$lang->pivot->columnPositionList = array();
$lang->pivot->columnPositionList['bottom'] = 'Bottom of the table only';
$lang->pivot->columnPositionList['row']    = 'Below each row group only';
$lang->pivot->columnPositionList['all']    = 'Both';

$lang->pivot->columnTotalList = array();
$lang->pivot->columnTotalList['noShow'] = 'Do not show';
$lang->pivot->columnTotalList['sum']    = 'Sum';

$lang->pivot->error = new stdclass();
$lang->pivot->error->noPivot    = 'Pivot not found.';
$lang->pivot->error->queryFail  = 'Pivot query failed.';

$lang->pivot->stepDesign->total = 'Total';
$lang->pivot->stepDesign->showModeList = array();
$lang->pivot->stepDesign->showModeList['default'] = 'Default (value)';
$lang->pivot->stepDesign->showModeList['total']   = 'Percent of total';
$lang->pivot->stepDesign->showModeList['row']     = 'Percent of row';
$lang->pivot->stepDesign->showModeList['column']  = 'Percent of column';
