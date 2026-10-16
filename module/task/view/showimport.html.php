<?php
/**
 * The showImport view file of task module of ZenTaoPMS (WenYinOS community build).
 *
 * CSV 导入预览：逐行给出解析后的值与错误原因。错误行不写库，
 * 点「导入」只写没有错误的行（后端会按同一份校验结果再判一次）。
 *
 * @copyright   Copyright 2009-2015 青岛易软网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     task
 */
?>
<?php include '../../common/view/header.html.php';?>
<div id='mainMenu' class='clearfix'>
  <div class='btn-toolbar pull-left'>
    <span class='btn btn-link btn-active-text'><span class='text'><?php echo $lang->task->showImport;?></span></span>
  </div>
  <div class='btn-toolbar pull-right'>
    <?php echo html::a(inlink('import', "projectID={$project->id}"), '<i class="icon icon-back"></i> ' . $lang->goback, '', "class='btn btn-link'");?>
  </div>
</div>

<div id='mainContent'>
  <div class='cell'><span class='text-muted'><?php echo $importTip;?></span></div>
  <form method='post' id='importForm'>
    <?php echo html::hidden('import', 1);?>
    <div class='main-table' data-ride='table'>
      <table class='table table-striped' id='importRows'>
        <thead>
          <tr>
            <th class='w-60px'><?php echo $lang->task->line;?></th>
            <th class='w-80px'><?php echo $lang->task->importStatus;?></th>
            <?php foreach($columns as $field => $label):?>
            <th><?php echo $label;?></th>
            <?php endforeach;?>
            <th class='w-300px'><?php echo $lang->task->importError;?></th>
          </tr>
        </thead>
        <tbody>
          <?php $validCount = 0;?>
          <?php foreach($rows as $row):?>
          <?php $hasError = !empty($row['errors']);?>
          <?php if(!$hasError) $validCount++;?>
          <tr<?php echo $hasError ? " class='danger'" : '';?>>
            <td><?php echo $row['line'];?></td>
            <td>
              <?php if($hasError):?>
              <span class='label label-danger'><?php echo $lang->task->importInvalid;?></span>
              <?php else:?>
              <span class='label label-success'><?php echo $lang->task->importValid;?></span>
              <?php endif;?>
            </td>
            <?php foreach($columns as $field => $label):?>
            <?php
            $value = isset($row['data']->$field) ? $row['data']->$field : '';
            /* 枚举列回显中文，否则预览里是一串 devel / wait 这类内部值。 */
            if(in_array($field, $listFields) and $value !== '') $value = zget($lang->task->{$field . 'List'}, $value, $value);
            if($field == 'project' and $value) $value = $project->name;
            if(isset($row['errors'][$field])) $value = $row['errors'][$field];
            ?>
            <td class='text-left'><?php echo nl2br(htmlspecialchars((string)$value));?></td>
            <?php endforeach;?>
            <td class='text-left text-danger'>
              <?php foreach($row['errors'] as $field => $error):?>
              <div><?php echo zget($columns, $field, $field) . $lang->colon . $error;?></div>
              <?php endforeach;?>
            </td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
      <div class='table-footer'>
        <span class='text-muted'><?php echo sprintf($lang->task->importPreview, count($rows), $validCount);?></span>
        <?php if($validCount):?>
        <div class='pull-right'>
          <?php echo html::submitButton($lang->task->import);?>
        </div>
        <?php endif;?>
      </div>
    </div>
  </form>
</div>
<?php include '../../common/view/footer.html.php';?>
