<?php
/**
 * The html template file of deny method of user module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     ZenTaoPMS
 * @version     $Id: deny.html.php 4129 2013-01-18 01:58:14Z wwccss $
 */
include '../../common/view/header.lite.html.php';
?>
<div class='container'>
  <div class='modal-dialog'>
    <div class='modal-header'><strong><?php echo $app->user->account, ' ', $lang->user->deny;?></strong></div>
    <div class='modal-body'>
      <div class='alert with-icon alert-pure'>
        <i class='icon-exclamation-sign'></i>
        <div class='content'>
        <?php
        /* 只接受字符串：模块 lang 里同名键可能是语言组对象（如 $lang->report->annualData），
           直接 printf 会因 stdClass 无法转字符串而致命，权限提示页自己崩掉。 */
        $moduleName = isset($lang->$module->common) && is_string($lang->$module->common) ? $lang->$module->common : $module;
        $methodName = isset($lang->$module->$method) && is_string($lang->$module->$method) ? $lang->$module->$method : $method;

        /* find method name if method is lowercase letter. */
        if(!isset($lang->$module->$method) or !is_string($lang->$module->$method))
        {
            $tmpLang = array();
            foreach($lang->$module as $key => $value)
            {
                if(!is_string($value)) continue;
                $tmpLang[strtolower($key)] = $value;
            }    
            $methodName = isset($tmpLang[$method]) ? $tmpLang[$method] : $method;
        }

        printf($lang->user->errorDeny, $moduleName, $methodName);
        ?>
        </div>
      </div>
    </div>
    <div class='modal-footer'>
    <?php
    $isOnlybody = helper::inOnlyBodyMode();
    unset($_GET['onlybody']);
    echo html::a($this->createLink($config->default->module), $lang->my->common, ($isOnlybody ? '_parent' : ''), "class='btn'");
    if($refererBeforeDeny) echo html::a(helper::safe64Decode($refererBeforeDeny), $lang->user->goback, ($isOnlybody ? '_parent' : ''), "class='btn'");
    echo html::a($this->createLink('user', 'logout', "referer=" . helper::safe64Encode($denyPage)), $lang->user->relogin, ($isOnlybody ? '_parent' : ''), "class='btn btn-primary'");
    ?>
    </div>
  </div>
</div>
</body>
</html>
