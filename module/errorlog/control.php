<?php
/**
 * The control file of errorlog module of ZenTaoPMS (WenYinOS community build).
 *
 * 后台 -> 错误日志：列表 / 详情 / 删除 / 保存天数设置；超期清理由 cron 调用 deleteLog()。
 *
 * @copyright   Copyright 2009-2026 禅道软件（青岛）集团有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     errorlog
 */
class errorlog extends control
{
    /**
     * 错误日志列表。
     *
     * @param  string $type      all|fatal|warning
     * @param  string $orderBy
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function browse($type = 'all', $orderBy = 'id_desc', $recTotal = 0, $recPerPage = 20, $pageID = 1)
    {
        if(!in_array($type, array('all', 'fatal', 'warning'))) $type = 'all';

        /* 排序字段白名单：raw 查询里的 orderBy 不能直接拼请求参数。 */
        if(!preg_match('/^[a-zA-Z0-9_]+_(asc|desc)$/', (string)$orderBy)) $orderBy = 'id_desc';
        $orderBy = 't1.' . $orderBy;

        $this->app->loadClass('pager', true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        $query = '';
        if($type != 'all')
        {
            $groups = $this->errorlog->getLevelGroups();
            $levels = array_map('intval', $groups[$type]);
            $query  = 't2.level IN (' . implode(',', $levels) . ')';
        }

        $logList = $this->errorlog->getList($query, $orderBy, $pager);
        foreach($logList as $log)
        {
            $log->levelName = isset($this->lang->errorlog->levelList[$log->level]) ? $this->lang->errorlog->levelList[$log->level] : $log->level;
            $log->levelType = $this->errorlog->getLevelType((int)$log->level);
        }

        $this->view->title    = $this->lang->errorlog->common . $this->lang->colon . $this->lang->errorlog->browse;
        $this->view->logList  = $logList;
        $this->view->pager    = $pager;
        $this->view->orderBy  = $orderBy == 't1.id_desc' ? 'id_desc' : $orderBy;
        $this->view->type     = $type;
        $this->view->saveDays = $this->config->errorlog->saveDays;
        $this->display();
    }

    /**
     * 错误详情（iframe 弹窗）。
     *
     * @param  int $id
     * @access public
     * @return void
     */
    public function view($id)
    {
        $log = $this->errorlog->getByID($id);
        if(!$log) die(js::error($this->lang->errorlog->notFound) . js::locate('back'));

        $log->levelName = isset($this->lang->errorlog->levelList[$log->level]) ? $this->lang->errorlog->levelList[$log->level] : $log->level;
        $log->levelType = $this->errorlog->getLevelType((int)$log->level);

        $this->view->title = $this->lang->errorlog->view;
        $this->view->log   = $log;
        $this->display();
    }

    /**
     * 删除一条。
     *
     * @param  int $id
     * @access public
     * @return void
     */
    public function delete($id)
    {
        $this->errorlog->deleteByID($id);
        if(dao::isError()) $this->send(array('result' => 'fail', 'message' => dao::getError()));

        $this->send(array('result' => 'success', 'load' => true));
    }

    /**
     * 批量删除。
     *
     * @access public
     * @return void
     */
    public function batchDelete()
    {
        $idList = zget($_POST, 'idList', array());
        if(empty($idList)) $this->send(array('result' => 'fail', 'load' => true));

        $this->errorlog->deleteByIDs($idList);
        if(dao::isError()) $this->send(array('result' => 'fail', 'message' => dao::getError()));

        $this->send(array('result' => 'success', 'load' => true));
    }

    /**
     * 保存天数设置。
     *
     * @access public
     * @return void
     */
    public function setting()
    {
        if($_POST)
        {
            $data = fixer::input('post')->get();
            $days = isset($data->days) ? $data->days : 0;
            if(!validater::checkInt($days, 1, $this->config->errorlog->maxSaveDays))
            {
                /* send() 的 fail 分支要求 message 为 field => array(错误) 的嵌套结构（框架会对每项 implode），
                   传裸字符串会触发 implode 的 TypeError。 */
                $this->send(array('result' => 'fail', 'message' => array('days' => array(sprintf($this->lang->errorlog->notice->int, $this->lang->errorlog->days)))));
            }

            $this->loadModel('setting')->setItem('system.errorlog.saveDays', (string)$days);
            if(dao::isError()) $this->send(array('result' => 'fail', 'message' => dao::getError()));

            $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'load' => true, 'closeModal' => true));
        }

        $this->view->title    = $this->lang->errorlog->setting;
        $this->view->saveDays = $this->config->errorlog->saveDays;
        $this->display();
    }

    /**
     * 删除超期日志（cron 入口：moduleName=errorlog&methodName=deleteLog）。
     *
     * @access public
     * @return bool
     */
    public function deleteLog()
    {
        $date = date(DT_DATE1, strtotime('-' . (int)$this->config->errorlog->saveDays . ' days'));
        $this->errorlog->deleteByDate($date);
        return !dao::isError();
    }
}
