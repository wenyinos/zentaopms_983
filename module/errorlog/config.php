<?php
/**
 * The config file of errorlog module of ZenTaoPMS (WenYinOS community build).
 *
 * 注意：本文件由 framework 的错误处理路径按需加载（router::loadErrorLogConfig），
 * 因此**只能写 $config，绝不能写 $lang** —— 错误句柄在 loadLang('common') 之前就已注册，
 * 此时污染 $lang 会导致语言加载整体错乱（见移植计划 §5.3）。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     errorlog
 */
$config->errorlog = new stdclass();

/* 是否把运行时错误写入数据库。 */
$config->errorlog->enabled = true;

/* 收录的错误级别（掩码）：刻意排除 NOTICE/DEPRECATED/STRICT —— 9.8.3 内核在 PHP 8.5 下
   notice/deprecated 极多，默认收录会让错误表迅速打满。需要时改这里即可。 */
$config->errorlog->levels = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING;

/* 保留天数（cron 按此清理），可在后台「错误日志 → 保存天数」调整。 */
$config->errorlog->saveDays    = 7;
$config->errorlog->maxSaveDays = 30;
