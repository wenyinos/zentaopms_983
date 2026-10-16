<?php
/**
 * The preview view file of pivot module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     pivot
 */
?>
<?php include '../../common/view/header.html.php';?>
<div id='mainMenu' class='clearfix'>
  <div class='btn-toolbar pull-left'>
    <span class='btn btn-link btn-active-text'><span class='text'><?php echo $lang->pivot->preview;?></span></span>
  </div>
  <div class='btn-toolbar pull-right'>
    <?php if(common::hasPriv('report', 'productSummary')) echo html::a($this->createLink('report', 'productSummary'), '<i class="icon icon-back"></i> ' . $lang->pivot->backToReport, '', "class='btn btn-link'");?>
  </div>
</div>

<div id='mainContent'>
  <?php if(empty($pivots)):?>
  <div class='table-empty-tip'><p><span class='text-muted'><?php echo $lang->pivot->noPivot;?></span></p></div>
  <?php else:?>
  <div class='main-table' data-ride='table'>
    <table class='table has-sort-head' id='pivotList'>
      <thead>
        <tr>
          <th class='c-id w-60px'><?php echo $lang->pivot->id;?></th>
          <th class='w-200px'><?php echo $lang->pivot->name;?></th>
          <th><?php echo $lang->pivot->desc;?></th>
          <th class='w-100px'><?php echo $lang->pivot->group;?></th>
          <th class='w-80px'><?php echo $lang->pivot->builtin;?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($groups as $groupName => $groupPivots):?>
        <?php if(!empty($groupName)):?>
        <tr class='active'><td colspan='5' class='text-left'><strong><?php echo $groupName;?></strong></td></tr>
        <?php endif;?>
        <?php foreach($groupPivots as $pivot):?>
        <tr>
          <td><?php echo $pivot->id;?></td>
          <td class='text-left'><?php echo html::a(inlink('show', "pivotID={$pivot->id}"), $pivot->name);?></td>
          <td class='text-left'><?php echo $pivot->desc;?></td>
          <td><?php echo $pivot->group;?></td>
          <td><?php echo $pivot->builtin ? $lang->yes : $lang->no;?></td>
        </tr>
        <?php endforeach;?>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif;?>
</div>
<?php include '../../common/view/footer.html.php';?>
