<?php
/**
 * The control file of upgrade module of ZenTaoPMS (WenYinOS community build).
 *
 * 流程：index → backup（备份警告）→ execute（执行升级）→ afterExec（写入新版本号）。
 * 只支持从 WenYinOS 系列版本升级（见 upgradeModel::isWenyinVersion），官方禅道版本拒绝。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     upgrade
 */
class upgrade extends control
{
    /**
     * The index page.
     *
     * @access public
     * @return void
     */
    public function index()
    {
        $this->upgrade->checkSource();

        /* Locate to index page of my module, if upgrade.php does not exist. */
        $upgradeFile = $this->app->wwwRoot . 'upgrade.php';
        if(!file_exists($upgradeFile)) $this->locate($this->createLink('my', 'index'));

        $this->locate(inlink('backup'));
    }

    /**
     * Backup.
     *
     * @access public
     * @return void
     */
    public function backup()
    {
        $this->upgrade->checkSource();

        $this->view->title = $this->lang->upgrade->common;
        $this->display();
    }

    /**
     * Execute the upgrading.
     *
     * @param  string $fromVersion
     * @access public
     * @return void
     */
    public function execute($fromVersion = '')
    {
        $this->upgrade->checkSource();

        if(empty($fromVersion)) $fromVersion = $this->config->installedVersion;
        $fromVersion = strtolower(str_replace(array(' ', '.'), array('', '_'), $fromVersion));

        $this->upgrade->execute($fromVersion);

        $this->view->title      = $this->lang->upgrade->result;
        $this->view->position[] = $this->lang->upgrade->common;

        if(!$this->upgrade->isError()) $this->locate(inlink('afterExec', "fromVersion=$fromVersion"));

        $this->view->result = 'fail';
        $this->view->errors = $this->upgrade->getError();
        $this->display();
    }

    /**
     * After execute.
     *
     * @param  string $fromVersion
     * @access public
     * @return void
     */
    public function afterExec($fromVersion = '')
    {
        $this->upgrade->checkSource();

        $this->loadModel('setting')->updateVersion($this->config->version);

        $this->view->title      = $this->lang->upgrade->result;
        $this->view->position[] = $this->lang->upgrade->common;
        $this->display();
    }
}
