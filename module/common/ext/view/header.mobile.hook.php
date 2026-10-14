<?php
/* wenyinos 禅道移动端适配样式注入（hook：header.html.php 内，$themeRoot 已定义；主题无关共享路径，三个主题通用） */
/* 带文件指纹：样式修订后无需改版本号即可刷新浏览器缓存（与 compat.css 同机制） */
$mobileCssFile = $this->app->getWwwRoot() . 'theme/mobile.css';
$mobileCssTs   = file_exists($mobileCssFile) ? filemtime($mobileCssFile) : 0;
css::import($themeRoot . 'mobile.css?t=' . $mobileCssTs);
