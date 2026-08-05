  </div><?php /* end '.outer' in 'header.html.php'. */ ?>
  <script>setTreeBox()</script>
  <?php if($extView = $this->getExtViewFile(__FILE__)){include $extView; return helper::cd();}?>

  <div id='divider'></div>
  <iframe frameborder='0' name='hiddenwin' id='hiddenwin' scrolling='no' class='debugwin hidden'></iframe>
<?php $onlybody = zget($_GET, 'onlybody', 'no');?>
<?php if($onlybody != 'yes'):?>
</div><?php /* end '#wrap' in 'header.html.php'. */ ?>
<div id='footer'>
  <div id='crumbs'>
    <?php commonModel::printBreadMenu($this->moduleName, isset($position) ? $position : ''); ?>
  </div>
  <div id='poweredby'>
  <?php
  $phpVersion = PHP_VERSION;
  $phpBranch  = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
  $phpLink    = "https://www.php.net/releases/{$phpBranch}/en.php";
  ?>
  <a href='<?php echo $lang->website;?>' target='_blank' class='text-primary'><i class='icon-zentao'></i> <?php echo $lang->zentaoPMS . $config->version;?></a> &nbsp;
    <a href='https://github.com/wenyinos/zentaopms_983' target='_blank'>WenYinOS</a> &nbsp;
    <a href='<?php echo $phpLink;?>' target='_blank'>PHP <?php echo $phpVersion;?></a> &nbsp;
    <?php commonModel::printNotifyLink();?>
  </div>
</div>
<div id="noticeBox"><?php echo $this->loadModel('score')->getNotice(); ?></div>
<?php
/* Security notice: remind to remove install.php and upgrade.php if not deleted. */
$wwwRoot  = $this->app->getWwwRoot();
$fileList = array();
if(file_exists($wwwRoot . 'install.php')) $fileList[] = 'install.php';
if(file_exists($wwwRoot . 'upgrade.php')) $fileList[] = 'upgrade.php';
if(!empty($fileList)):?>
<div id='securityNotice' class='alert alert-warning with-icon' style='position:fixed;left:50%;bottom:20px;margin-left:-260px;width:520px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.15);'>
  <i class='icon-info-sign'></i>
  <div class='content'>
    <strong>安全提示：</strong>为保障站点安全，建议删除 <code><?php echo $wwwRoot;?></code> 目录下的 <code><?php echo implode('</code>、<code>', $fileList);?></code> 文件。测试环境可暂不删除。
    <div class='text-right' style='margin-top:8px;'>
      <button type='button' class='btn btn-sm btn-primary' onclick="$('#securityNotice').remove()">知道了</button>
    </div>
  </div>
</div>
<?php endif;?>
<script>
<?php if(!isset($config->global->browserNotice)):?>
browserNotice = '<?php echo $lang->browserNotice?>'
function ajaxIgnoreBrowser(){$.get(createLink('misc', 'ajaxIgnoreBrowser'));}
$(function(){showBrowserNotice()});
<?php endif;?>

/* Alert get message. */
$(function()
{
    var windowBlur = false;
    if(window.Notification)
    {
        window.onblur  = function(){windowBlur = true;}
        window.onfocus = function(){windowBlur = false;}
    }
    setInterval(function()
    {
        $.get(createLink('message', 'ajaxGetMessage', "windowBlur=" + (windowBlur ? '1' : '0')), function(data)
        {
           if(!windowBlur)
            {
                $('#noticeBox').append(data);
                adjustNoticePosition();
            }
            else
            {
                if(data)
                {
                    if(typeof data == 'string') data = $.parseJSON(data);
                    if(typeof data.message == 'string')	notifyMessage(data.message);
                }
            }
        });
    }, 60 * 1000);
})

<?php if(!isset($config->global->novice) and $this->loadModel('tutorial')->checkNovice() and $config->global->flow == 'full'):?>
novice = confirm('<?php echo $lang->tutorial->novice?>');
$.get(createLink('tutorial', 'ajaxSaveNovice', 'novice=' + (novice ? 'true' : 'false')), function()
{
    if(novice) location.href=createLink('tutorial', 'index');
});
<?php endif;?>

<?php if(!empty($this->config->sso->redirect)):?>
<?php
$ranzhiAddr = $this->config->sso->addr;
$ranzhiURL  = substr($ranzhiAddr, 0, strrpos($ranzhiAddr, '/sys/'));
?>
<?php if(!empty($ranzhiURL)):?>
$(function(){ redirect('<?php echo $ranzhiURL?>', '<?php echo $this->config->sso->code?>'); });
<?php endif;?>
<?php endif;?>
</script>

<?php endif;?>

<script>config.onlybody = '<?php echo $onlybody?>';</script>
<?php
if($this->loadModel('cron')->runable()) js::execute('startCron()');
if(isset($pageJS)) js::execute($pageJS);  // load the js for current page.

/* Load hook files for current page. */
$extPath      = $this->app->getModuleRoot() . '/common/ext/view/';
$extHookRule  = $extPath . 'footer.*.hook.php';
$extHookFiles = glob($extHookRule);
if($extHookFiles) foreach($extHookFiles as $extHookFile) include $extHookFile;
?>
</body>
</html>
