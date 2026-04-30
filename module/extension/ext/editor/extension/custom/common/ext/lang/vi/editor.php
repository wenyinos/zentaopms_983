<?php
$lang->admin->subMenu->dev->editor = array('link' => 'Sửa code|editor|index', 'subModule' => 'editor');
$lang->admin->menu->dev['subModule'] .= ',editor';

$lang->editor   = new stdclass();
$lang->editor->menu   = $lang->admin->menu;
$lang->editor->menuOrder = $lang->admin->menuOrder;
$lang->menugroup->editor = 'admin';
