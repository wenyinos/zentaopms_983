<?php
/**
 * The exportTemplet view file of story module of ZenTaoPMS (WenYinOS community build).
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     story
 */
?>
<?php include '../../common/view/header.lite.html.php';?>
<script>
function setDownloading()
{
    if($.browser.opera) return true;   // Opera don't support, omit it.

    $.cookie('downloading', 0);
    time = setInterval("closeWindow()", 300);
    return true;
}

function closeWindow()
{
    if($.cookie('downloading') == 1)
    {
        parent.$.closeModal();
        $.cookie('downloading', null);
        clearInterval(time);
    }
}
</script>
<main id="main">
  <div class="container">
    <div id="mainContent" class='main-content'>
      <div class='main-header'>
        <h2><?php echo $lang->story->exportTemplet;?></h2>
      </div>
      <form method='post' target='hiddenwin' onsubmit='setDownloading();' style='padding: 40px 5%'>
        <table class='table table-form'>
          <tr>
            <td class='w-100px'>
              <?php echo html::select('encode', $config->charsets[$this->cookie->lang], 'utf-8', "class='form-control'");?>
            </td>
            <td class="w-150px">
              <?php echo html::submitButton('', '', 'btn btn-primary btn-block');?>
            </td>
          </tr>
          <tr>
            <td colspan='2'><span class='text-muted'><?php echo $lang->story->importTip;?></span></td>
          </tr>
        </table>
      </form>
    </div>
  </div>
</main>
<?php include '../../common/view/footer.lite.html.php';?>
