<?php
/**
 * The moveDoc view file of doc module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     doc
 */
?>
<div class='modal-dialog'>
  <div class='modal-content'>
    <div class='modal-header'>
      <button type='button' class='close' data-dismiss='modal'><span aria-hidden='true'>×</span><span class='sr-only'>X</span></button>
      <h4 class='modal-title'><?php echo $lang->doc->moveDoc;?></h4>
    </div>
    <div class='modal-body'>
      <form method='post' target='hiddenwin' id='moveDocForm'>
        <table class='table table-form'>
          <tr>
            <th class='w-90px'><?php echo $lang->doc->lib;?></th>
            <td><?php echo html::select('lib', $libs, $libID, "class='form-control chosen' onchange='loadModules(this.value)'");?></td>
          </tr>
          <tr>
            <th><?php echo $lang->doc->module;?></th>
            <td><div id='moduleBox'><?php echo html::select('module', array('0' => '/'), 0, "class='form-control chosen'");?></div></td>
          </tr>
        </table>
      </form>
    </div>
    <div class='modal-footer'>
      <?php echo html::submitButton('', 'btn btn-primary', 'btn btn-primary', "form='moveDocForm'");?>
      <button type='button' class='btn btn-default' data-dismiss='modal'><?php echo $lang->cancel;?></button>
    </div>
  </div>
</div>
<script>
function loadModules(libID)
{
    $.get(createLink('doc', 'ajaxGetModules', 'libID=' + libID), function(data)
    {
        $('#moduleBox').html(data);
        $('#moduleBox select').chosen();
    });
}
$(function()
{
    loadModules($('#moveDocForm select[name=lib]').val());
});
</script>
