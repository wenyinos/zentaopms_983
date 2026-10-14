<?php
/**
 * The html template file of backup method of upgrade module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     upgrade
 */
?>
<?php include '../../common/view/header.lite.html.php';?>
<div class='container'>
  <div class='modal-dialog'>
    <div class='modal-header'>
      <strong><?php echo $lang->upgrade->warnning;?></strong>
    </div>
    <div class='modal-body'>
      <div class='alert alert-pure'>
        <div class='content'><?php echo $lang->upgrade->warnningContent;?></div>
      </div>
      <table class='table table-form'>
        <tr>
          <th class='w-100px'><?php echo $lang->upgrade->fromVersion;?></th>
          <td><?php echo $config->installedVersion;?></td>
        </tr>
        <tr>
          <th><?php echo $lang->upgrade->toVersion;?></th>
          <td><?php echo $config->version;?></td>
        </tr>
      </table>
    </div>
    <div class='modal-footer'><?php echo html::linkButton($lang->upgrade->sureExecute, inlink('execute'), 'self', '', 'btn btn-primary');?></div>
  </div>
</div>
<?php include '../../common/view/footer.lite.html.php';?>
