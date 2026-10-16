<?php
/**
 * The import view file of bug module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     bug
 */
?>
<?php include '../../common/view/header.lite.html.php';?>
<main id="main">
  <div class="container">
    <div id="mainContent" class='main-content'>
      <div class='main-header'>
        <h2><?php echo $lang->bug->import;?></h2>
      </div>
      <form method='post' enctype='multipart/form-data' target='hiddenwin' style="padding:30px">
        <table class='table table-form'>
          <tr>
            <td align='center'>
              <input type='file' name='file' class='form-control'/>
            </td>
            <td>
              <?php echo html::select('encode', $config->charsets[$this->cookie->lang], 'utf-8', "class='form-control'");?>
            </td>
            <td class="w-150px">
              <?php echo html::submitButton('', '', 'btn btn-primary btn-block');?>
            </td>
          </tr>
          <tr>
            <td colspan='3'><span class='text-muted'><?php echo $lang->bug->importTip;?></span></td>
          </tr>
        </table>
      </form>
    </div>
  </div>
</main>
<?php include '../../common/view/footer.lite.html.php';?>
