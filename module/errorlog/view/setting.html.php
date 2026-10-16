<?php
/**
 * The setting view file of errorlog module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2026 禅道软件（青岛）集团有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     errorlog
 */
?>
<div class='modal-dialog'>
  <div class='modal-content'>
    <form class='modal-form' method='post' target='hiddenwin'>
      <div class='modal-header'>
        <button type='button' class='close' data-dismiss='modal'><i class='icon icon-close'></i></button>
        <h4 class='modal-title'><?php echo $lang->errorlog->setting;?></h4>
      </div>
      <div class='modal-body'>
        <table class='table table-form'>
          <tr>
            <th class='w-100px'><?php echo $lang->errorlog->days;?></th>
            <td><?php echo html::input('days', $saveDays, "class='form-control' autocomplete='off'");?></td>
          </tr>
          <tr>
            <th></th>
            <td class='text-muted'><?php echo $lang->errorlog->info;?><br /><?php echo sprintf($lang->errorlog->daysRange, $this->config->errorlog->maxSaveDays);?></td>
          </tr>
        </table>
      </div>
      <div class='modal-footer'>
        <?php echo html::submitButton();?>
        <button type='button' class='btn' data-dismiss='modal'><?php echo $lang->cancel;?></button>
      </div>
    </form>
  </div>
</div>
