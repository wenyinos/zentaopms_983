<?php if($extView = $this->getExtViewFile(__FILE__)){include $extView; return helper::cd();}?>
<?php
$webRoot      = $this->app->getWebRoot();
$jsRoot       = $webRoot . "js/";
$themeRoot    = $webRoot . "theme/";
$defaultTheme = $webRoot . 'theme/default/';
$langTheme    = $themeRoot . 'lang/' . $app->getClientLang() . '.css';
$clientTheme  = $this->app->getClientTheme();
/* 主题/语言名须白名单校验：cookie 中可能残留已下线的旧主题（如 default/green/purple），
   框架只纠正 $clientTheme 属性、setcookie 也要下次请求才生效，直接拼 CSS 会引用不存在的
   皮肤包导致整站无样式。此处按注册表回退到默认主题。 */
$clientThemeName = $this->cookie->theme;
if(!isset($lang->themes[$clientThemeName])) $clientThemeName = $config->default->theme;
$clientLang = $this->cookie->lang;
if(!isset($config->langs[$clientLang])) $clientLang = $config->default->lang;
$onlybody     = zget($_GET, 'onlybody', 'no');
?>
<!DOCTYPE html>
<html lang='<?php echo $app->getClientLang();?>'>
<head>
  <meta charset='utf-8'>
  <meta http-equiv='X-UA-Compatible' content='IE=edge'>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="renderer" content="webkit">
  <?php
  echo html::title($title . ' - ' . $lang->zentaoPMS);
  js::exportConfigVars();
  echo '<script>config.onlybody = "' . $onlybody . '";</script>';
  if($config->debug)
  {
      $timestamp = time();
      js::import($jsRoot . 'jquery/lib.js');
      js::import($jsRoot . 'zui/min.js?t=' . $timestamp);
      js::import($jsRoot . 'my.full.js?t=' . $timestamp);
      js::import($jsRoot . 'compat.js?t=' . $timestamp);

      css::import($themeRoot . 'zui/css/min.css?t=' . $timestamp);
      css::import($defaultTheme . 'style.css?t=' . $timestamp);

      css::import($langTheme);
      if(strpos($clientTheme, 'default') === false) css::import($clientTheme . 'style.css?t=' . $timestamp);
      css::import($defaultTheme . 'compat.css?t=' . $timestamp);
  }
  else
  {
      js::import($jsRoot . 'all.js');
      js::import($jsRoot . 'compat.js');
      css::import($defaultTheme . $clientLang . '.' . $clientThemeName . '.css');
      /* 兼容层随 UI 调整会持续更新，用文件修改时间作缓存指纹（内容变 URL 即变），
         避免 v=9.9.0 这类固定版本号导致浏览器一直用旧缓存。 */
      $compatFile = $this->app->getWwwRoot() . 'theme/default/compat.css';
      $compatTs   = file_exists($compatFile) ? filemtime($compatFile) : 0;
      css::import($defaultTheme . 'compat.css?t=' . $compatTs);
  }

  if(!defined('IN_INSTALL') and commonModel::isTutorialMode())
  {
      $wizardModule    = defined('WIZARD_MODULE') ? WIZARD_MODULE : $this->moduleName;
      $wizardMethod    = defined('WIZARD_METHOD') ? WIZARD_METHOD : $this->methodName;
      $requiredFields  = '';
      if(isset($config->$wizardModule->$wizardMethod->requiredFields)) $requiredFields = str_replace(' ', '', $config->$wizardModule->$wizardMethod->requiredFields);
      echo "<script>window.TUTORIAL = {'module': '$wizardModule', 'method': '$wizardMethod', tip: '$lang->tutorialConfirm'}; if(config) config.requiredFields = '$requiredFields'; </script>";
  }

  if(isset($pageCSS)) css::internal($pageCSS);

  echo html::favicon($webRoot . 'favicon.ico');
  ?>
<!--[if lt IE 9]>
<?php
js::import($jsRoot . 'html5shiv/min.js');
js::import($jsRoot . 'respond/min.js');
?>
<![endif]-->
<!--[if lt IE 10]>
<?php js::import($jsRoot . 'jquery/placeholder/min.js'); ?>
<![endif]-->
</head>
<body>
