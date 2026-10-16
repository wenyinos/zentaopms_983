<?php
/**
 * The control file of pivot module of ZenTaoPMS (WenYinOS community build).
 *
 * 只读版透视表：列表 + 渲染。不提供建表器/设计器/版本切换/下钻/图表。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     pivot
 */
class pivot extends control
{
    /**
     * Index, jump to preview.
     *
     * @access public
     * @return void
     */
    public function index()
    {
        $this->locate(inlink('preview'));
    }

    /**
     * List all pivots.
     *
     * @param  string $group
     * @access public
     * @return void
     */
    public function preview($group = '')
    {
        /* 内置透视表按 code 幂等补齐（用户改过的行不会被覆盖）。 */
        $this->pivot->syncBuiltins();

        $pivots = $this->pivot->getList();
        $groups = array();
        foreach($pivots as $pivot)
        {
            if($group and $pivot->group != $group) continue;
            $groups[$pivot->group][$pivot->id] = $pivot;
        }

        $this->view->title      = $this->lang->pivot->previewTitle;
        $this->view->position[] = $this->lang->pivot->preview;
        $this->view->pivots     = $pivots;
        $this->view->groups     = $groups;
        $this->view->group      = $group;
        $this->display();
    }

    /**
     * Show one pivot as a table.
     *
     * @param  int    $pivotID
     * @param  string $version
     * @access public
     * @return void
     */
    public function show($pivotID, $version = 'newest')
    {
        /* 菜单用 code 定位（reportList 里写的是稳定标识，不是自增 ID）。 */
        if(!is_numeric($pivotID))
        {
            $pivotID = (int)$this->dao->select('id')->from(TABLE_PIVOT)
                ->where('code')->eq($pivotID)->andWhere('deleted')->eq('0')->fetch('id');
        }

        $pivot = $pivotID ? $this->pivot->getByID($pivotID) : false;
        if(!$pivot) die(js::error($this->lang->pivot->error->noPivot) . js::locate('back'));

        /* getByID 已把 settings/filters/langs 解成数组，并把 fields 拆成
           「键名列表 + fieldSettings 对象」；这里只把 fieldSettings 转成纯数组，
           与 22.6 zen.php 的调用口径一致。 */
        $settings = is_array($pivot->settings) ? $pivot->settings : array();
        $filters  = is_array($pivot->filters)  ? $pivot->filters  : array();
        $langs    = is_array($pivot->langs)    ? $pivot->langs    : array();
        $fields   = json_decode(json_encode($pivot->fieldSettings), true);
        if(!is_array($fields)) $fields = array();

        list($sql, $filterFormat) = $this->pivot->getFilterFormat($pivot->sql, $filters);
        list($data, $configs)     = $this->pivot->genSheet($fields, $settings, $sql, $filterFormat, $langs, 'mysql');

        $this->view->title      = $pivot->name;
        $this->view->position[] = html::a(inlink('preview'), $this->lang->pivot->preview);
        $this->view->position[] = $pivot->name;
        $this->view->pivot      = $pivot;
        $this->view->data       = $data;
        $this->view->configs    = $configs;
        $this->display();
    }
}
