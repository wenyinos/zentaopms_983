<?php
/**
 * The createTemplate / editTemplate view file of doc module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     doc
 */
?>
<?php include '../../common/view/header.lite.html.php';?>
<?php include '../../common/view/ueditor.html.php';?>
<div id='mainContent' class='main-content'>
  <div class='main-header'>
    <h2><?php echo $template ? $lang->doc->editTemplate : $lang->doc->createTemplate;?></h2>
  </div>
  <form class='load-indicator main-form form-ajax' method='post' id='templateForm'>
    <table class='table table-form'>
      <tr>
        <th class='w-100px'><?php echo $lang->doc->type;?></th>
        <td><?php echo html::select('module', $modules, $moduleID, "class='form-control chosen'");?></td>
      </tr>
      <tr>
        <th><?php echo $lang->doc->title;?></th>
        <td><?php echo html::input('title', $template ? $template->title : '', "class='form-control' autocomplete='off' required");?></td>
      </tr>
      <tr>
        <th><?php echo $lang->doc->keywords;?></th>
        <td><?php echo html::input('keywords', $template ? $template->keywords : '', "class='form-control' autocomplete='off'");?></td>
      </tr>
      <tr>
        <th><?php echo $lang->doc->content;?></th>
        <td><?php echo html::textarea('content', $template ? $template->content : '', "style='width:100%;height:300px'");?></td>
      </tr>
      <tr>
        <td colspan='2' class='text-center form-actions'>
          <?php echo html::submitButton();?>
          <button type='button' class='btn btn-default' data-dismiss='modal'><?php echo $lang->cancel;?></button>
        </td>
      </tr>
    </table>
  </form>
</div>
<?php include '../../common/view/footer.lite.html.php';?>
