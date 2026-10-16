<?php
/**
 * The browse view file of errorlog module of ZenTaoPMS (WenYinOS community build).
 *
 * 说明：本列表刻意不用 10.1 的 datatable（其列配置体系与 rowspan/复选框的自定义成本高），
 * 用 9.8.3 时代的 table + 内联 JS 实现勾选与批量删除，样式沿用 10.1 的 main-table / has-sort-head。
 *
 * @copyright   Copyright 2009-2026 禅道软件（青岛）集团有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     errorlog
 */
?>
<?php include '../../common/view/header.html.php';?>
<div id='mainMenu' class='clearfix'>
  <div class='btn-toolbar pull-left'>
    <?php
    $tabs = array(
        'all'     => $lang->all,
        'fatal'   => $lang->errorlog->fatal,
        'warning' => $lang->errorlog->warning,
    );
    foreach($tabs as $key => $label)
    {
        $active = $type == $key ? 'btn-active-text' : '';
        echo html::a(inlink('browse', "type=$key"), "<span class='text'>{$label}</span>", '', "class='btn btn-link {$active}'");
    }
    ?>
  </div>
  <div class='btn-toolbar pull-right'>
    <?php
    echo html::a(inlink('setting'), "<i class='icon-cog'></i> " . $lang->errorlog->setting, '', "class='btn btn-link iframe' data-width='500'");
    ?>
  </div>
</div>

<div id='mainContent'>
  <div class='main-table' data-ride='table' data-checkable='false'>
    <table class='table has-sort-head' id='errorlogList'>
      <?php $vars = "type={$type}&orderBy=%s&recTotal={$pager->recTotal}&recPerPage={$pager->recPerPage}";?>
      <thead>
        <tr>
          <th class='c-id'>
            <div class='checkbox-primary check-all' title='<?php echo $lang->selectAll;?>'><label></label></div>
          </th>
          <th class='w-110px'><?php common::printOrderLink('level',       $orderBy, $vars, $lang->errorlog->level);?></th>
          <th class='w-160px'><?php echo $lang->errorlog->module;?></th>
          <th><?php echo $lang->errorlog->message;?></th>
          <th class='w-100px'><?php common::printOrderLink('account',     $orderBy, $vars, $lang->errorlog->account);?></th>
          <th class='w-140px'><?php common::printOrderLink('createdDate', $orderBy, $vars, $lang->errorlog->createdDate);?></th>
          <th class='w-100px'><?php echo $lang->actions;?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($logList as $log):?>
        <tr>
          <td class='c-id'>
            <div class='checkbox-primary'><input type='checkbox' name='idList[]' value='<?php echo $log->id;?>' id='log<?php echo $log->id;?>' /><label for='log<?php echo $log->id;?>'></label></div>
          </td>
          <td><span class='label label-<?php echo $log->levelType;?>'><?php echo htmlspecialchars((string)$log->levelName);?></span></td>
          <td class='text-left'><span class='text-muted'><?php echo htmlspecialchars((string)$log->module);?></span>::<?php echo htmlspecialchars((string)$log->method);?></td>
          <td class='text-left'>
            <a href='javascript:;' class='iframe text-ellipsis' data-url='<?php echo inlink('view', "id={$log->id}");?>' title='<?php echo htmlspecialchars((string)$log->message);?>'>
              <?php echo helper::substr(strip_tags((string)$log->message), 0, 80);?>
            </a>
          </td>
          <td><?php echo $log->account == '' ? '<span class="text-muted">guest</span>' : htmlspecialchars((string)$log->account);?></td>
          <td><?php echo substr((string)$log->createdDate, 0, 16);?></td>
          <td class='c-actions'>
            <?php
            echo html::a(inlink('view', "id={$log->id}"), "<i class='icon-search icon-sm'></i>", '', "class='btn btn-icon iframe' title='{$lang->errorlog->view}' data-width='900'");
            echo html::a(inlink('delete', "id={$log->id}"), "<i class='icon-trash icon-sm'></i>", 'hiddenwin', "class='btn btn-icon' title='{$lang->delete}'");
            ?>
          </td>
        </tr>
        <?php endforeach;?>
        <?php if(empty($logList)):?>
        <tr><td colspan='7'><div class='table-empty-tip'><p><span class='text-muted'><?php echo $lang->errorlog->empty;?></span></p></div></td></tr>
        <?php endif;?>
      </tbody>
    </table>
    <div class='table-footer'>
      <div class='table-actions btn-toolbar'>
        <button type='button' class='btn' id='batchDeleteBtn'><i class='icon-trash'></i> <?php echo $lang->errorlog->batchDelete;?></button>
        <span class='text-muted'><?php echo $lang->errorlog->days . $lang->colon . $saveDays . '　' . $lang->errorlog->info;?></span>
      </div>
      <?php if(!empty($logList)) $pager->show('right', 'pagerjs');?>
    </div>
  </div>
</div>
<?php echo js::set('errorlogConfirmDelete', $lang->errorlog->confirmDelete);?>
<?php echo js::set('errorlogConfirmBatchDelete', $lang->errorlog->confirmBatchDelete);?>
<script>
$(function()
{
    /* 表头全选。 */
    $('#errorlogList .check-all').on('click', function()
    {
        var checked = $(this).hasClass('checked') ? false : true;
        $('#errorlogList tbody input[name="idList[]"]').prop('checked', checked);
        $('#errorlogList tbody .checkbox-primary').toggleClass('checked', checked);
    });

    /* 批量删除。 */
    $('#batchDeleteBtn').on('click', function()
    {
        var idList = $('#errorlogList tbody input[name="idList[]"]:checked').map(function(){ return this.value; }).get();
        if(!idList.length) { $.zui.messager.default('<?php echo $lang->errorlog->empty;?>'); return; }
        if(!confirm(errorlogConfirmBatchDelete)) return;
        $.post($.createLink('errorlog', 'batchDelete'), {idList: idList}, function(data)
        {
            if(data.result == 'success') location.reload();
            else $.zui.messager.danger(data.message ? data.message : 'fail');
        }, 'json');
    });
});
</script>
<?php include '../../common/view/footer.html.php';?>
