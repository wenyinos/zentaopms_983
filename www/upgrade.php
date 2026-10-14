<?php
/**
 * The upgrade router file of ZenTaoPMS (WenYinOS community build).
 *
 * 只支持从 9.8.3_1（首个社区定制版）及其后续自研版本升级；
 * 官方禅道版本的数据库会被明确拒绝，避免不兼容升级损坏数据（见 upgradeModel::isWenyinVersion）。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     ZenTaoPMS
 */
define('IN_UPGRADE', true);

error_reporting(0);

/* Load the framework. */
include '../framework/router.class.php';
include '../framework/control.class.php';
include '../framework/model.class.php';
include '../framework/helper.class.php';

/* Instance the app. */
$app = router::createApp('pms', dirname(dirname(__FILE__)), 'router');
$common = $app->loadCommon();

/* Reset the config params to make sure the install program will be lauched. */
$config->set('requestType', 'GET');
$config->set('default.module', 'upgrade');
$app->setDebug();

/* Get the installed version, and refuse to upgrade if it is not a WenYinOS build. */
$config->installedVersion = $common->loadModel('setting')->getVersion();
$common->loadModel('upgrade')->checkSource();

/* Check the installed version is the latest or not. */
if(version_compare($config->version, $config->installedVersion) <= 0) die(header('location: index.php'));

/* Run it. */
$app->parseRequest();
$common->checkUpgradeStatus();
$app->loadModule();
