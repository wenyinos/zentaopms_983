<?php
/**
 * The addTemplateType view file of doc module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     doc
 */
?>
<?php include '../../common/view/header.lite.html.php';?>
<div id='mainContent' class='main-content'>
  <div class='main-header'>
    <h2><?php echo $lang->doc->addTemplateType;?></h2>
  </div>
  <form class='load-indicator main-form form-ajax' method='post' id='typeForm'>
    <table class='table table-form'>
      <tr>
        <th class='w-100px'><?php echo $lang->doc->typeName;?></th>
        <td><?php echo html::input('name', '', "class='form-control' autocomplete='off' required");?></td>
      </tr>
      <?php if(!empty($parentModule)):?>
      <tr>
        <th><?php echo $lang->doc->parentType;?></th>
        <td><?php echo html::input('parentName', $parentModule, "class='form-control' disabled");?><?php echo html::hidden('parent', $parentModule);?></td>
      </tr>
      <?php else:?>
      <?php echo html::hidden('parent', 0);?>
      <?php endif;?>
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
