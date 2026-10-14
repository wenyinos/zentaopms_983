<?php
/**
 * The html template file of afterExec method of upgrade module of ZenTaoPMS (WenYinOS community build).
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
      <strong><?php echo $lang->upgrade->result;?></strong>
    </div>
    <div class='modal-body'>
      <div class='alert alert-success mgb-10'>
        <strong><?php echo $lang->upgrade->success;?></strong>
        <span class='pull-right'><?php echo html::a('index.php', $lang->upgrade->tohome, '', "class='btn btn-sm'")?></span>
      </div>
    </div>
  </div>
</div>
<?php include '../../common/view/footer.lite.html.php';?>
