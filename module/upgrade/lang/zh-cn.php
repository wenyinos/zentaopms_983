<?php
/**
 * The upgrade module zh-cn file of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     upgrade
 * @version     $Id: zh-cn.php 5119 2013-07-12 08:06:42Z wyd621@gmail.com $
 * @link        http://www.zentao.net
 */
$lang->upgrade->common  = '升级';
$lang->upgrade->result  = '升级结果';
$lang->upgrade->fail    = '升级失败';
$lang->upgrade->success = '升级成功';
$lang->upgrade->tohome  = '访问禅道';
$lang->upgrade->warnning= '警告';
$lang->upgrade->warnningContent = <<<EOT
<p>升级有危险，请先备份数据库，以防万一。</p>
<pre>
1. 可以通过phpMyAdmin进行备份。
2. 使用mysql命令行的工具。
   $> mysqldump -u <span class='text-danger'>username</span> -p <span class='text-danger'>dbname</span> > <span class='text-danger'>filename</span> 
   要将上面红色的部分分别替换成对应的用户名和禅道系统的数据库名。
   比如： mysqldump -u root -p zentao >zentao.bak
</pre>
EOT;
$lang->upgrade->createFileWinCMD   = '打开命令行，执行<strong style="color:#ed980f">echo > %s</strong>';
$lang->upgrade->createFileLinuxCMD = '在命令行执行: <strong style="color:#ed980f">touch %s</strong>';
$lang->upgrade->setStatusFile      = '<h4>升级之前请先完成下面的操作：</h4>
                                      <ul style="line-height:1.5;font-size:13px;">
                                      <li>%s</li>
                                      <li>或者删掉"<strong style="color:#ed980f">%s</strong>" 这个文件 ，重新创建一个<strong style="color:#ed980f">ok.txt</strong>文件，不需要内容。</li>
                                      </ul>
                                      <p><strong style="color:red">我已经仔细阅读上面提示且完成上述工作，<a href="upgrade.php">继续更新</a></strong></p>';
$lang->upgrade->fromVersion = '当前版本';
$lang->upgrade->toVersion   = '升级到';
$lang->upgrade->sureExecute = '开始升级';
