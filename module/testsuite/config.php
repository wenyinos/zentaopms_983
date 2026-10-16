<?php
$config->testsuite = new stdclass();
$config->testsuite->create     = new stdclass();
$config->testsuite->edit       = new stdclass();
$config->testsuite->createlib  = new stdclass();
$config->testsuite->createcase = new stdclass();
$config->testsuite->create->requiredFields     = 'name';
$config->testsuite->edit->requiredFields       = 'name';
$config->testsuite->createlib->requiredFields  = 'name';
$config->testsuite->createcase->requiredFields = 'title,type';

$config->testsuite->editor = new stdclass();
$config->testsuite->editor->create    = array('id' => 'desc', 'tools' => 'simpleTools');
$config->testsuite->editor->edit      = array('id' => 'desc', 'tools' => 'simpleTools');
$config->testsuite->editor->createlib = array('id' => 'desc', 'tools' => 'simpleTools');

/* 用例库导出（与导入模板同构：stepDesc/stepExpect 成对，可导出→改→再导入）。 */
$config->testsuite->exportFields = 'id,module,title,type,pri,stage,precondition,stepDesc,stepExpect,keywords,files,openedBy,openedDate,lastEditedBy,lastEditedDate';

$config->testsuite->datatable = new stdclass();
$config->testsuite->datatable->defaultField = array('id', 'pri', 'title', 'type', 'assignedTo', 'lastRunner', 'lastRunDate', 'lastRunResult', 'status', 'bugs', 'results', 'actions');
