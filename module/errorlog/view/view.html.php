<?php
/**
 * The view view file of errorlog module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2026 禅道软件（青岛）集团有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     errorlog
 */
?>
<div class='modal-dialog'>
  <div class='modal-content'>
    <div class='modal-header'>
      <button type='button' class='close' data-dismiss='modal'><i class='icon icon-close'></i></button>
      <h4 class='modal-title'><?php echo $lang->errorlog->view;?></h4>
    </div>
    <div class='modal-body'>
      <table class='table table-form'>
        <tr>
          <th class='w-90px'><?php echo $lang->errorlog->level;?></th>
          <td><span class='label label-<?php echo $log->levelType;?>'><?php echo htmlspecialchars((string)$log->levelName);?></span></td>
        </tr>
        <tr>
          <th><?php echo $lang->errorlog->module;?></th>
          <td><?php echo htmlspecialchars((string)$log->module);?> :: <?php echo htmlspecialchars((string)$log->method);?></td>
        </tr>
        <tr>
          <th><?php echo $lang->errorlog->requestID;?></th>
          <td class='text-muted'><?php echo htmlspecialchars((string)$log->requestID);?></td>
        </tr>
        <tr>
          <th><?php echo $lang->errorlog->account;?></th>
          <td><?php echo $log->account == '' ? 'guest' : htmlspecialchars((string)$log->account);?></td>
        </tr>
        <tr>
          <th><?php echo $lang->errorlog->url;?></th>
          <td class='text-muted'><?php echo htmlspecialchars((string)$log->url);?></td>
        </tr>
        <tr>
          <th><?php echo $lang->errorlog->file;?></th>
          <td><?php echo htmlspecialchars((string)$log->file);?> : <?php echo (int)$log->line;?></td>
        </tr>
        <tr>
          <th><?php echo $lang->errorlog->createdDate;?></th>
          <td><?php echo $log->createdDate;?></td>
        </tr>
        <tr>
          <th><?php echo $lang->errorlog->message;?></th>
          <td><pre class='pre-code' style='max-height:200px;overflow:auto;white-space:pre-wrap'> <?php echo htmlspecialchars((string)$log->message);?> </pre></td>
        </tr>
        <tr>
          <th><?php echo $lang->errorlog->trace;?></th>
          <td><pre class='pre-code' style='max-height:320px;overflow:auto;white-space:pre-wrap'> <?php echo htmlspecialchars((string)$log->trace);?> </pre></td>
        </tr>
      </table>
    </div>
  </div>
</div>
