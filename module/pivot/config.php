<?php
/**
 * The config file of pivot module of ZenTaoPMS (WenYinOS community build).
 *
 * 透视表（只读版）：不移植 22.6 的建表器/设计器/版本切换/下钻/图表/大屏，
 * 只保留「按既有 SQL + 既定 settings 渲染成 rowspan 表格」这一条链路。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     pivot
 */

/* 需要把账号翻译成真实姓名的字段（22.6 原样搬）。 */
$config->pivot->userFields = array('assignedTo', 'createdBy', 'openedBy', 'resolvedBy', 'closedBy', 'finishedBy', 'account', 'user');

/* 多值映射字段（22.6 在 dataview 里定义，本仓就近放在这里）。 */
$config->pivot->multipleMappingFields = array('testcase-stage');

/* 只支持 mysql（本仓没有 DuckDB 那条线）。 */
$config->pivot->drivers = array('mysql');

/* 5 张内置透视表：按 code 幂等 upsert，数字口径对齐 report 模块的 5 张静态报表。
   字段说明：
   - sql      取数 SQL，列名即 field 键
   - fields   列类型：object 型可把 id 渲染成名称；user 型翻译成真实姓名；number 型参与汇总
   - langs    列显示名
   - settings group1/group2/group3 是行分组层级；columns 每项是
              array('field' => 取值字段, 'stat' => sum|count|avg|min|max|distinct,
                    'slice' => 横向展开的维度字段（省略则不展开，只出一列）)；
              columnTotal(sum|noShow) + columnPosition(bottom|row|all)；summary 打开汇总
*/
$config->pivot->builtins = array();

/* 1) Bug 指派：指派人 × 产品 的未关闭 Bug 数（对齐 report|bugAssign） */
$config->pivot->builtins['bugsassign'] = array(
    'name'  => 'Bug指派表',
    'desc'  => '按指派人与所属产品统计未关闭（激活）的 Bug 数量。',
    'group' => '测试',
    'sql'   => "SELECT t1.assignedTo AS `user`, t2.name AS product, COUNT(*) AS total
                FROM " . TABLE_BUG . " t1
                LEFT JOIN " . TABLE_PRODUCT . " t2 ON t1.product = t2.id
                WHERE t1.deleted = '0' AND t1.status = 'active' AND t2.deleted = '0' AND t1.assignedTo <> ''
                GROUP BY t1.assignedTo, t2.name",
    'fields' => array(
        'user'    => array('type' => 'user',   'object' => 'user',    'field' => 'account'),
        'product' => array('type' => 'object', 'object' => 'product', 'field' => 'name'),
        'total'   => array('type' => 'number'),
    ),
    'langs' => array(
        'user'    => array('zh-cn' => '指派给', 'en' => 'Assigned To'),
        'product' => array('zh-cn' => '所属产品', 'en' => 'Product'),
        'total'   => array('zh-cn' => 'Bug数', 'en' => 'Bugs'),
    ),
    'settings' => array(
        'group1'         => 'user',
        'group2'         => '',
        'group3'         => '',
        'columns'        => array(array('field' => 'total', 'stat' => 'sum', 'slice' => 'product')),
        'columnTotal'    => 'sum',
        'columnPosition' => 'bottom',
        'summary'        => true,
    ),
    'filters' => array(),
    'vars'    => array(),
);

/* 2) Bug 创建：创建人 × 处理结果（对齐 report|bugCreate 的默认时间窗：上月 1 日至今） */
$config->pivot->builtins['bugscreate'] = array(
    'name'  => 'Bug创建表',
    'desc'  => '按创建人统计时间窗内新建的 Bug 数、已解决数、修复数与延期数（时间窗与报表页一致：上月 1 日至今）。',
    'group' => '测试',
    'sql'   => "SELECT t1.openedBy AS `user`, COUNT(*) AS total,
                       SUM(t1.status IN ('resolved', 'closed')) AS resolved,
                       SUM(t1.resolution = 'fixed') AS fixed,
                       SUM(t1.resolution = 'postponed') AS postponed
                FROM " . TABLE_BUG . " t1
                WHERE t1.deleted = '0'
                  AND t1.openedDate >= DATE_FORMAT(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH), '%Y-%m-%d')
                GROUP BY t1.openedBy",
    'fields' => array(
        'user'      => array('type' => 'user',   'object' => 'user', 'field' => 'account'),
        'total'     => array('type' => 'number'),
        'resolved'  => array('type' => 'number'),
        'fixed'     => array('type' => 'number'),
        'postponed' => array('type' => 'number'),
    ),
    'langs' => array(
        'user'      => array('zh-cn' => '由谁创建', 'en' => 'Opened By'),
        'total'     => array('zh-cn' => '新增Bug数', 'en' => 'New Bugs'),
        'resolved'  => array('zh-cn' => '已解决', 'en' => 'Resolved'),
        'fixed'     => array('zh-cn' => '已修复', 'en' => 'Fixed'),
        'postponed' => array('zh-cn' => '已延期', 'en' => 'Postponed'),
    ),
    'settings' => array(
        'group1'         => 'user',
        'group2'         => '',
        'group3'         => '',
        'columns'        => array(
            array('field' => 'total',     'stat' => 'sum'),
            array('field' => 'resolved',  'stat' => 'sum'),
            array('field' => 'fixed',     'stat' => 'sum'),
            array('field' => 'postponed', 'stat' => 'sum'),
        ),
        'columnTotal'    => 'sum',
        'columnPosition' => 'bottom',
        'summary'        => true,
    ),
    'filters' => array(),
    'vars'    => array(),
);

/* 3) 项目偏差：项目 × 预计/消耗工时（对齐 report|projectDeviation：只取已完成的项目、父任务） */
$config->pivot->builtins['projectdeviation'] = array(
    'name'  => '项目偏差表',
    'desc'  => '已完成项目下父任务的预计工时与消耗工时对比（对齐报表页口径）。',
    'group' => '项目',
    'sql'   => "SELECT t2.name AS project, SUM(t1.estimate) AS estimate, SUM(t1.consumed) AS consumed,
                       ROUND(SUM(t1.consumed) - SUM(t1.estimate), 1) AS deviation
                FROM " . TABLE_TASK . " t1
                LEFT JOIN " . TABLE_PROJECT . " t2 ON t1.project = t2.id
                WHERE t1.status <> 'cancel' AND t1.deleted = '0' AND t2.deleted = '0'
                  AND t1.parent = 0 AND t2.status = 'done'
                GROUP BY t2.name",
    'fields' => array(
        'project'   => array('type' => 'object', 'object' => 'project', 'field' => 'name'),
        'estimate'  => array('type' => 'number'),
        'consumed'  => array('type' => 'number'),
        'deviation' => array('type' => 'number'),
    ),
    'langs' => array(
        'project'   => array('zh-cn' => '项目名称', 'en' => 'Project'),
        'estimate'  => array('zh-cn' => '预计工时', 'en' => 'Estimate'),
        'consumed'  => array('zh-cn' => '消耗工时', 'en' => 'Consumed'),
        'deviation' => array('zh-cn' => '偏差', 'en' => 'Deviation'),
    ),
    'settings' => array(
        'group1'         => 'project',
        'group2'         => '',
        'group3'         => '',
        'columns'        => array(
            array('field' => 'estimate',  'stat' => 'sum'),
            array('field' => 'consumed',  'stat' => 'sum'),
            array('field' => 'deviation', 'stat' => 'sum'),
        ),
        'columnTotal'    => 'sum',
        'columnPosition' => 'bottom',
        'summary'        => true,
    ),
    'filters' => array(),
    'vars'    => array(),
);

/* 4) 产品汇总：产品 × 计划 × 需求状态（对齐 report|productSummary 的未关闭产品口径） */
$config->pivot->builtins['productsummary'] = array(
    'name'  => '产品汇总表',
    'desc'  => '未关闭产品下，需求按所属计划与状态的数量分布（对齐报表页口径；报表页会额外剔除已过期计划，本表不过滤）。',
    'group' => '产品',
    /* 注意：别名不能叫 plan —— zt_story 自己有 plan 列，GROUP BY 会解析成那一列，
       把「空计划」和「引用了已删计划」拆成两组（实测踩到）。故用 planName。 */
    'sql'   => "SELECT p.name AS product, IF(t1.plan = '' OR t1.plan IS NULL, '未计划', IFNULL(pl.title, '未计划')) AS planName,
                       t1.status AS status, COUNT(*) AS total
                FROM " . TABLE_STORY . " t1
                LEFT JOIN " . TABLE_PRODUCT . " p ON t1.product = p.id
                LEFT JOIN " . TABLE_PRODUCTPLAN . " pl ON FIND_IN_SET(pl.id, t1.plan) AND pl.deleted = '0'
                WHERE t1.deleted = '0' AND p.deleted = '0' AND p.status <> 'closed'
                GROUP BY p.name, planName, t1.status",
    'fields' => array(
        'product' => array('type' => 'object', 'object' => 'product', 'field' => 'name'),
        'planName'=> array('type' => 'string'),
        'status'  => array('type' => 'string'),
        'total'   => array('type' => 'number'),
    ),
    'langs' => array(
        'product' => array('zh-cn' => '产品名称', 'en' => 'Product'),
        'planName'=> array('zh-cn' => '所属计划', 'en' => 'Plan'),
        'status'  => array('zh-cn' => '需求状态', 'en' => 'Status'),
        'total'   => array('zh-cn' => '需求数', 'en' => 'Stories'),
    ),
    'settings' => array(
        'group1'         => 'product',
        'group2'         => 'planName',
        'group3'         => '',
        'columns'        => array(array('field' => 'total', 'stat' => 'sum', 'slice' => 'status')),
        'columnTotal'    => 'sum',
        'columnPosition' => 'all',
        'summary'        => true,
    ),
    'filters' => array(),
    'vars'    => array(),
);

/* 5) 员工负载：指派人 × 项目 的工时分布（报表页的 workload 还会按工作日折算可用工时，
   本表只做「工时分布」这一层，不做可用工时折算，故与报表页数字口径不同，已在此注明） */
$config->pivot->builtins['workload'] = array(
    'name'  => '员工负载表',
    'desc'  => '未关闭项目的未完结任务，按指派人与项目统计预计/消耗/剩余工时（报表页另有按工作日折算可用工时的口径，本表不含该折算）。',
    'group' => '项目',
    'sql'   => "SELECT t1.assignedTo AS `user`, t2.name AS project,
                       SUM(t1.estimate) AS estimate, SUM(t1.consumed) AS consumed, SUM(t1.`left`) AS `left`
                FROM " . TABLE_TASK . " t1
                LEFT JOIN " . TABLE_PROJECT . " t2 ON t1.project = t2.id
                WHERE t1.deleted = '0' AND t1.status NOT IN ('cancel', 'closed', 'done', 'pause')
                  AND t2.deleted = '0' AND t2.status NOT IN ('cancel', 'closed', 'done', 'suspended')
                  AND t1.assignedTo <> ''
                GROUP BY t1.assignedTo, t2.name",
    'fields' => array(
        'user'     => array('type' => 'user',   'object' => 'user', 'field' => 'account'),
        'project'  => array('type' => 'object', 'object' => 'project', 'field' => 'name'),
        'estimate' => array('type' => 'number'),
        'consumed' => array('type' => 'number'),
        'left'     => array('type' => 'number'),
    ),
    'langs' => array(
        'user'     => array('zh-cn' => '指派给', 'en' => 'Assigned To'),
        'project'  => array('zh-cn' => '所属项目', 'en' => 'Project'),
        'estimate' => array('zh-cn' => '预计工时', 'en' => 'Estimate'),
        'consumed' => array('zh-cn' => '消耗工时', 'en' => 'Consumed'),
        'left'     => array('zh-cn' => '剩余工时', 'en' => 'Left'),
    ),
    'settings' => array(
        'group1'         => 'user',
        'group2'         => '',
        'group3'         => '',
        'columns'        => array(
            array('field' => 'estimate', 'stat' => 'sum', 'slice' => 'project'),
            array('field' => 'consumed', 'stat' => 'sum', 'slice' => 'project'),
            array('field' => 'left',     'stat' => 'sum', 'slice' => 'project'),
        ),
        'columnTotal'    => 'sum',
        'columnPosition' => 'bottom',
        'summary'        => true,
    ),
    'filters' => array(),
    'vars'    => array(),
);
