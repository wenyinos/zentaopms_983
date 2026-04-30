<?php
$lang->admin->subMenu->dev->editor    = array('link' => '編輯器|editor|index', 'subModule' => 'editor');
$lang->admin->menu->dev['subModule'] .= ',editor';

$lang->editor            = new stdclass();
$lang->editor->menu      = $lang->admin->menu;
$lang->editor->menuOrder = $lang->admin->menuOrder;
$lang->menugroup->editor = 'admin';
