<?php if($extView = $this->getExtViewFile(__FILE__)){include $extView; return helper::cd();}?>
<iframe frameborder='0' name='hiddenwin' id='hiddenwin' scrolling='no' class='debugwin hidden'></iframe>
<?php if($this->loadModel('cron')->runable()) js::execute('startCron()');?>
<script laguage='Javascript'>
<?php if(isset($pageJS)) echo $pageJS;?>
</script>
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
</body>
</html>
