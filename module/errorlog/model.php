<?php
/**
 * The model file of errorlog module of ZenTaoPMS (WenYinOS community build).
 *
 * 数据来自框架 router::saveErrorLog() 写入的 zt_errorlog（错误本体，按 md5 唯一）
 * 与 zt_errorlogreq（请求上下文，requestID+md5 唯一）。
 *
 * @copyright   Copyright 2009-2026 禅道软件（青岛）集团有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     errorlog
 */
class errorlogModel extends model
{
    /**
     * 列表联表字段。
     *
     * @access protected
     * @return string
     */
    protected function getSelectFields()
    {
        return 't1.id, t1.requestID, t1.module, t1.method, t1.account, t1.url, t1.createdDate, t2.md5, t2.level, t2.message, t2.file, t2.line, t2.trace';
    }

    /**
     * 错误日志列表。
     *
     * @param  string $query
     * @param  string $orderBy
     * @param  object $pager
     * @access public
     * @return array
     */
    public function getList($query = '', $orderBy = 'id_desc', $pager = null)
    {
        return $this->dao->select($this->getSelectFields())->from(TABLE_ERRORLOGREQ)->alias('t1')
            ->leftJoin(TABLE_ERRORLOG)->alias('t2')->on('t1.md5 = t2.md5')
            ->where('1=1')
            ->beginIF(!empty($query))->andWhere($query)->fi()
            ->orderBy($orderBy)
            ->page($pager)
            ->fetchAll();
    }

    /**
     * 按 id 取一条（含请求上下文与错误本体）。
     *
     * @param  int $id
     * @access public
     * @return object|false
     */
    public function getByID($id)
    {
        return $this->dao->select($this->getSelectFields())->from(TABLE_ERRORLOGREQ)->alias('t1')
            ->leftJoin(TABLE_ERRORLOG)->alias('t2')->on('t1.md5 = t2.md5')
            ->where('t1.id')->eq((int)$id)
            ->fetch();
    }

    /**
     * 同一请求产生的全部错误。
     *
     * @param  string $requestID
     * @access public
     * @return array
     */
    public function getByRequestID($requestID)
    {
        return $this->dao->select($this->getSelectFields())->from(TABLE_ERRORLOGREQ)->alias('t1')
            ->leftJoin(TABLE_ERRORLOG)->alias('t2')->on('t1.md5 = t2.md5')
            ->where('t1.requestID')->eq($requestID)
            ->orderBy('t1.id_asc')
            ->fetchAll();
    }

    /**
     * 错误级别对应的展示类型（用于彩色标签）。
     *
     * @param  int $level
     * @access public
     * @return string danger|warning|primary
     */
    public function getLevelType($level)
    {
        $fatalLevels   = E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE | E_RECOVERABLE_ERROR;
        $warningLevels = E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING;

        if($level & $fatalLevels)   return 'danger';
        if($level & $warningLevels) return 'warning';
        return 'primary';
    }

    /**
     * 级别分组 -> 级别值列表（列表页页签筛选用）。
     *
     * @access public
     * @return array
     */
    public function getLevelGroups()
    {
        return array(
            'fatal'   => array(E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR),
            'warning' => array(E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING),
        );
    }

    /**
     * 有错误日志的模块（列表页筛选下拉用）。
     *
     * @access public
     * @return array
     */
    public function getModulePairs()
    {
        return $this->dao->select('DISTINCT `module`')->from(TABLE_ERRORLOGREQ)->where('module')->ne('')->orderBy('module')->fetchPairs('module', 'module');
    }

    /**
     * 删除一条（并清理不再被引用的错误本体）。
     *
     * @param  int $id
     * @access public
     * @return bool
     */
    public function deleteByID($id)
    {
        $log = $this->dao->select('md5')->from(TABLE_ERRORLOGREQ)->where('id')->eq((int)$id)->fetch();
        if($log)
        {
            $this->dao->delete()->from(TABLE_ERRORLOGREQ)->where('id')->eq((int)$id)->exec();
            $this->deleteOrphanErrorLogs(array($log->md5));
        }
        return !dao::isError();
    }

    /**
     * 批量删除。
     *
     * @param  array $ids
     * @access public
     * @return bool
     */
    public function deleteByIDs($ids)
    {
        $ids = array_filter((array)$ids, 'is_numeric');
        if(empty($ids)) return true;

        $md5s = $this->dao->select('md5')->from(TABLE_ERRORLOGREQ)->where('id')->in($ids)->fetchPairs('md5', 'md5');
        $this->dao->delete()->from(TABLE_ERRORLOGREQ)->where('id')->in($ids)->exec();
        $this->deleteOrphanErrorLogs(array_keys($md5s));
        return !dao::isError();
    }

    /**
     * 删除指定日期之前的请求记录（cron 调用）。
     *
     * @param  string $date
     * @access public
     * @return bool
     */
    public function deleteByDate($date)
    {
        $md5s = $this->dao->select('md5')->from(TABLE_ERRORLOGREQ)->where('createdDate')->lt($date)->fetchPairs('md5', 'md5');
        $this->dao->delete()->from(TABLE_ERRORLOGREQ)->where('createdDate')->lt($date)->exec();
        $this->deleteOrphanErrorLogs(array_keys($md5s));
        return !dao::isError();
    }

    /**
     * 清理不再被任何请求引用的错误本体。
     *
     * 本仓 dao 没有 subNotIn（22.6 才有），改为"取仍在引用的 md5 → notin 排除"。
     *
     * @param  array $md5s
     * @access protected
     * @return void
     */
    protected function deleteOrphanErrorLogs($md5s)
    {
        $md5s = array_values(array_unique(array_filter((array)$md5s)));
        if(empty($md5s)) return;

        $referenced = $this->dao->select('md5')->from(TABLE_ERRORLOGREQ)->where('md5')->in($md5s)->fetchPairs('md5', 'md5');
        if(!empty($referenced)) $md5s = array_values(array_diff($md5s, $referenced));
        if(empty($md5s)) return;

        $this->dao->delete()->from(TABLE_ERRORLOG)->where('md5')->in($md5s)->exec();
    }
}
