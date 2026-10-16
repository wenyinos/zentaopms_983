<?php
/**
 * The browseTemplate view file of doc module of ZenTaoPMS (WenYinOS community build).
 *
 * 文档模板列表：左侧分类树 + 右侧模板列表。模板本体是 type='template' 库里的 zt_doc 行，
 * 因此版本/ACL/动作日志都复用文档既有机制。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     doc
 */
?>
<?php include '../../common/view/header.html.php';?>
<div id='mainMenu' class='clearfix'>
  <div class='btn-toolbar pull-left'>
    <span class='btn btn-link btn-active-text'><span class='text'><?php echo $lang->doc->browseTemplate;?></span></span>
  </div>
  <div class='btn-toolbar pull-right'>
    <?php if(common::hasPriv('doc', 'createTemplate')) echo html::a($this->createLink('doc', 'createTemplate'), '<i class="icon icon-plus"></i> ' . $lang->doc->createTemplate, '', "class='btn btn-primary iframe' data-width='800'");?>
  </div>
</div>

<div id='mainContent' class='main-row'>
  <div class='side-col' id='sidebar'>
    <div class='cell'>
      <ul class='tree' id='typeTree'>
        <li<?php echo empty($typeModule) ? " class='active'" : '';?>>
          <?php echo html::a(inlink('browseTemplate', "libID=$libID&moduleID=$moduleID"), $lang->doc->allTypes);?>
        </li>
        <?php foreach($modules as $module):?>
        <li<?php echo $typeModule == $module->id ? " class='active'" : '';?>>
          <?php echo html::a(inlink('browseTemplate', "libID=$libID&moduleID=$moduleID&typeModule={$module->id}"), $module->name);?>
          <?php if(common::hasPriv('doc', 'editTemplateType')) echo html::a($this->createLink('doc', 'editTemplateType', "moduleID={$module->id}"), "<i class='icon icon-edit'></i>", '', "class='btn btn-link iframe' data-width='500' title='{$lang->edit}'");?>
          <?php if(common::hasPriv('doc', 'deleteTemplateType')) echo html::a($this->createLink('doc', 'deleteTemplateType', "moduleID={$module->id}"), "<i class='icon icon-trash'></i>", '', "class='btn btn-link' title='{$lang->delete}'");?>
        </li>
        <?php endforeach;?>
      </ul>
      <?php if(common::hasPriv('doc', 'addTemplateType')) echo html::a($this->createLink('doc', 'addTemplateType'), "<i class='icon icon-plus'></i> " . $lang->doc->addTemplateType, '', "class='btn btn-link iframe' data-width='500'");?>
    </div>
  </div>

  <div class='main-col'>
    <?php if(empty($templates)):?>
    <div class='table-empty-tip'>
      <p><span class='text-muted'><?php echo $lang->doc->noTemplate;?></span></p>
    </div>
    <?php else:?>
    <div class='main-table' data-ride='table'>
      <table class='table has-sort-head' id='templateList'>
        <thead>
          <tr>
            <th class='c-id w-60px'><?php echo $lang->idAB;?></th>
            <th><?php echo $lang->doc->title;?></th>
            <th class='w-140px'><?php echo $lang->doc->type;?></th>
            <th class='w-140px'><?php echo $lang->doc->editedBy;?></th>
            <th class='w-160px'><?php echo $lang->doc->actions;?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($templates as $template):?>
          <?php if($typeModule and $template->module != $typeModule) continue;?>
          <tr>
            <td><?php echo $template->id;?></td>
            <td class='text-left'><?php echo html::a($this->createLink('doc', 'editTemplate', "templateID={$template->id}"), $template->title, '', "class='iframe' data-width='800'");?></td>
            <td><?php echo zget($modulePairs, $template->module, $lang->doc->allTypes);?></td>
            <td><?php echo zget($users, $template->editedBy, $template->editedBy);?></td>
            <td class='text-left'>
              <?php if($libID and common::hasPriv('doc', 'create')) echo html::a($this->createLink('doc', 'create', "libID=$libID&moduleID=$moduleID&templateID={$template->id}"), "<i class='icon icon-copy'></i> " . $lang->doc->createFromTemplate, '', "class='btn btn-link'");?>
              <?php if(common::hasPriv('doc', 'editTemplate')) echo html::a($this->createLink('doc', 'editTemplate', "templateID={$template->id}"), "<i class='icon icon-edit'></i>", '', "class='btn btn-link iframe' data-width='800' title='{$lang->edit}'");?>
              <?php if(common::hasPriv('doc', 'deleteTemplate')) echo html::a($this->createLink('doc', 'deleteTemplate', "templateID={$template->id}"), "<i class='icon icon-trash'></i>", '', "class='btn btn-link' title='{$lang->delete}'");?>
            </td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
      <div class='table-footer'><?php $pager->show('right', 'pagerjs');?></div>
    </div>
    <?php endif;?>
  </div>
</div>
<?php include '../../common/view/footer.html.php';?>
