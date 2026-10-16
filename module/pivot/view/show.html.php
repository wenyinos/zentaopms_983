<?php
/**
 * The show view file of pivot module of ZenTaoPMS (WenYinOS community build).
 *
 * 表格 HTML 由 pivotModel::buildPivotTable 生成（带跨行合并与两级表头）。
 * 按计划 §4.1 第 4 条：不启用 data-ride='table' 与 datatable 模式。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     pivot
 */
?>
<?php include '../../common/view/header.html.php';?>
<div id='mainMenu' class='clearfix'>
  <div class='btn-toolbar pull-left'>
    <?php echo html::a(inlink('preview'), '<i class="icon icon-back"></i> ' . $lang->goback, '', "class='btn btn-link'");?>
    <span class='btn btn-link btn-active-text'><span class='text'><?php echo $pivot->name;?></span></span>
  </div>
</div>

<div id='mainContent'>
  <div class='cell'><span class='text-muted'><?php echo $pivot->desc;?></span></div>
  <div class='main-table pivot-table'>
    <?php echo $this->pivot->buildPivotTable($data, $configs);?>
  </div>
</div>
<?php include '../../common/view/footer.html.php';?>
