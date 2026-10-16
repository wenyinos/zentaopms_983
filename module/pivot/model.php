<?php
/**
 * The model file of pivot module of ZenTaoPMS.
 *
 * 本文件是禅道 22.6 pivot 透视表渲染引擎向「9.8.3 业务内核 + 10.1 界面」的机械移植。
 * 只保留 genSheet 主链路及其传递闭包，去掉了多数据源驱动、维度/标记/工作流字段等
 * 本仓不存在的依赖，查询统一走原生 PDO（$this->dbh）。
 *
 * @copyright   Copyright 2009-2023 禅道软件（青岛）集团有限公司(ZenTao Software (Qingdao) Co., Ltd. www.cnezsoft.com)
 * @license     ZPL(http://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     pivot
 * @version     $Id: model.php 4726 2013-05-03 05:51:27Z chencongzhi520@gmail.com $
 * @link        https://www.zentao.net
 */
class pivotModel extends model
{
    /**
     * 获取透视表相关的表名。
     * Get the table name of pivot related tables.
     *
     * @param  string $name PIVOT|PIVOTSPEC|PIVOTDRILL
     * @access private
     * @return string
     */
    private function getPivotTable($name)
    {
        $constant = 'TABLE_' . strtoupper($name);
        if(defined($constant)) return constant($constant);

        /* 兜底：本仓 config 未定义相关表常量时，按库表前缀推导，避免 fatal。 */
        /* Fallback: build the table name from the db prefix when the constant is not defined. */
        return '`' . $this->config->db->prefix . strtolower($name) . '`';
    }

    /**
     * 获取透视表配置项，未定义时返回默认值。
     * Get a pivot config value, return the default value when it is not defined.
     *
     * @param  string $name
     * @param  mixed  $default
     * @access private
     * @return mixed
     */
    private function getPivotSetting($name, $default = array())
    {
        if(isset($this->config->pivot->$name)) return $this->config->pivot->$name;
        return $default;
    }

    /**
     * 执行查询并返回全部记录。
     * Run the sql and fetch all records.
     *
     * @param  string $sql
     * @access private
     * @return array
     */
    private function queryRecords($sql)
    {
        $stmt = $this->dbh->query($sql);
        return $stmt ? $stmt->fetchAll() : array();
    }

    /**
     * 获取透视表。
     * Get pivot by id.
     *
     * @param  int    $pivotID
     * @access public
     * @return object|bool
     */
    public function getByID($pivotID)
    {
        $pivot = $this->dao->select('*')->from($this->getPivotTable('pivot'))
            ->where('id')->eq($pivotID)
            ->andWhere('deleted')->eq('0')
            ->fetch();
        if(!$pivot) return false;

        $pivot->fieldSettings = array();
        if(!empty($pivot->fields) && $pivot->fields != 'null')
        {
            $pivot->fieldSettings = json_decode($pivot->fields);
            $pivot->fields        = array_keys(get_object_vars($pivot->fieldSettings));
        }

        $pivot->settings = (!empty($pivot->settings) && $pivot->settings != 'null') ? json_decode($pivot->settings, true) : array();
        $pivot->filters  = (!empty($pivot->filters)  && $pivot->filters  != 'null') ? json_decode($pivot->filters, true)  : array();
        $pivot->langs    = (!empty($pivot->langs)    && $pivot->langs    != 'null') ? json_decode($pivot->langs, true)    : array();

        return $pivot;
    }

    /**
     * 获取透视表列表。
     * Get pivot list.
     *
     * @param  string $orderBy id_desc|id_asc
     * @access public
     * @return array
     */
    public function getList($orderBy = 'id_desc')
    {
        $order = strpos($orderBy, 'desc') !== false ? 'desc' : 'asc';

        return $this->dao->select('*')->from($this->getPivotTable('pivot'))
            ->where('deleted')->eq('0')
            ->orderBy('`group` asc, id ' . $order)
            ->fetchAll();
    }

    /**
     * 获取透视表某版本。
     * Get pivot spec by id and version.
     *
     * @param  int    $pivotID
     * @param  string $version
     * @access public
     * @return object|bool
     */
    public function getPivotSpec($pivotID, $version)
    {
        $pivot = $this->dao->select('*')->from($this->getPivotTable('pivotspec'))
            ->where('pivot')->eq($pivotID)
            ->andWhere('version')->eq($version)
            ->fetch();
        if(!$pivot) return false;

        $pivot->fieldSettings = array();
        if(!empty($pivot->fields) && $pivot->fields != 'null')
        {
            $pivot->fieldSettings = json_decode($pivot->fields);
            $pivot->fields        = array_keys(get_object_vars($pivot->fieldSettings));
        }

        $pivot->settings = (!empty($pivot->settings) && $pivot->settings != 'null') ? json_decode($pivot->settings, true) : array();
        $pivot->filters  = (!empty($pivot->filters)  && $pivot->filters  != 'null') ? json_decode($pivot->filters, true)  : array();
        $pivot->langs    = (!empty($pivot->langs)    && $pivot->langs    != 'null') ? json_decode($pivot->langs, true)    : array();

        return $pivot;
    }

    /**
     * 按内置透视表配置初始化数据，已存在的 code 跳过不覆盖。
     * Sync builtin pivots by code, skip the code that already exists.
     *
     * @access public
     * @return array
     */
    public function syncBuiltins()
    {
        $builtins = $this->getPivotSetting('builtins', array());
        if(empty($builtins) || !is_array($builtins)) return array();

        $synced = array();
        foreach($builtins as $code => $builtin)
        {
            if(empty($code)) continue;

            $exists = $this->dao->select('id')->from($this->getPivotTable('pivot'))
                ->where('code')->eq($code)
                ->andWhere('deleted')->eq('0')
                ->fetch();
            if($exists) continue;

            $builtin = (object)$builtin;
            $now     = helper::now();
            $pivot   = new stdclass();
            $pivot->code        = $code;
            $pivot->group       = isset($builtin->group) ? $builtin->group : '';
            $pivot->name        = isset($builtin->name) ? (is_array($builtin->name) ? json_encode($builtin->name) : $builtin->name) : $code;
            $pivot->desc        = isset($builtin->desc) ? (is_array($builtin->desc) ? json_encode($builtin->desc) : $builtin->desc) : '';
            $pivot->sql         = isset($builtin->sql) ? $builtin->sql : '';
            $pivot->fields      = isset($builtin->fields) ? (is_array($builtin->fields) ? json_encode($builtin->fields) : $builtin->fields) : '';
            $pivot->langs       = isset($builtin->langs) ? (is_array($builtin->langs) ? json_encode($builtin->langs) : $builtin->langs) : '';
            $pivot->settings    = isset($builtin->settings) ? (is_array($builtin->settings) ? json_encode($builtin->settings) : $builtin->settings) : '';
            $pivot->filters     = isset($builtin->filters) ? (is_array($builtin->filters) ? json_encode($builtin->filters) : $builtin->filters) : '';
            $pivot->vars        = isset($builtin->vars) ? (is_array($builtin->vars) ? json_encode($builtin->vars) : $builtin->vars) : '';
            $pivot->builtin     = 1;
            $pivot->createdBy   = isset($builtin->createdBy) ? $builtin->createdBy : $this->app->user->account;
            $pivot->createdDate = $now;
            $pivot->editedBy    = '';
            $pivot->editedDate  = $now;

            $this->dao->insert($this->getPivotTable('pivot'))->data($pivot)->exec();
            $synced[] = $code;
        }

        return $synced;
    }

    /**
     * 时间占位符替换为实际的时间。
     * Replace time placeholder with actual time.
     *
     * @param  mixed  $var
     * @param  string $type
     * @access public
     * @return string
     */
    public function processDateVar($var, $type = 'date')
    {
        if(!is_string($var) || $var === '') return '';

        $format = $type == 'datetime' ? 'Y-m-d H:i:s' : 'Y-m-d';
        switch($var)
        {
            case '$MONDAY':     $var = date($format, time() - (date('N') - 1) * 24 * 3600); break;
            case '$SUNDAY':     $var = date($format, time() + (7 - date('N')) * 24 * 3600); break;
            case '$MONTHBEGIN': $var = date($format, time() - (date('j') - 1) * 24 * 3600); break;
            case '$MONTHEND':   $var = date($format, time() + (date('t') - date('j')) * 24 * 3600); break;
        }
        return $var;
    }

    /**
     * 替换 sql 中的变量占位符。
     * Replace variables in sql, inlined from bi::processVars().
     *
     * @param  string $sql
     * @param  array  $filters
     * @access private
     * @return string
     */
    private function processVars($sql, $filters = array())
    {
        foreach($filters as $index => $filter)
        {
            if(empty($filter['default'])) continue;
            if(!isset($filter['from']) || $filter['from'] != 'query') continue;

            if($filter['type'] == 'date' || $filter['type'] == 'datetime') $filters[$index]['default'] = $this->processDateVar($filter['default']);
            if($filter['type'] == 'datetime') $filters[$index]['default'] .= ':00.000000000';
            if($filter['type'] == 'multipleselect' && is_array($filter['default'])) $filters[$index]['default'] = implode("','", $filter['default']);
        }

        if($filters)
        {
            foreach($filters as $filter)
            {
                if(!isset($filter['default']) || !isset($filter['from']) || $filter['from'] !== 'query') continue;
                if(!isset($filter['field'])) continue;

                $default = $filter['default'];
                if($filter['type'] == 'multipleselect' && is_array($default)) $default = implode("','", $default);

                if(strpos($sql, $filter['field'] . 'Condition') === false)
                {
                    $sql = str_replace('$' . $filter['field'], "'{$default}'", $sql);
                }
                else
                {
                    $relatedField = zget($filter, 'relatedField', $filter['field']);
                    $sql = str_replace('$' . $filter['field'] . 'Condition', "{$relatedField}='{$default}'", $sql);
                }
            }
        }

        /* 兜底：把残留的 $Xxx_1 形式的变量替换为空串。 */
        /* Fallback: replace the leftover $Xxx_1 style variables with an empty string. */
        $matchRule = "[\$]+[a-zA-Z0-9]+_[0-9]";
        if(strpos($sql, 'Condition') !== false && strpos($sql, 'Variale_') !== false) $matchRule .= "+Condition";

        if(preg_match_all("/{$matchRule}/", $sql, $out))
        {
            foreach($out[0] as $match) $sql = str_replace($match, "''", $sql);
        }

        return trim($sql, ';');
    }

    /**
     * 格式化sql和过滤条件。
     * Format sql and filter.
     *
     * @param  string $sql
     * @param  array  $filters
     * @access public
     * @return array
     */
    public function getFilterFormat($sql, $filters)
    {
        if(empty($filters)) return array($sql, false);

        $filters = $this->processQueryFilterDefaults($filters);
        $currentFilter = current($filters);
        $isQueryFilter = (isset($currentFilter['from']) && $currentFilter['from'] == 'query');

        $filterFormat = $isQueryFilter ? array_values($filters) : array();
        foreach($filters as $filter)
        {
            $field = $filter['field'];

            if($isQueryFilter)
            {
                $queryDefault = '';
                if(isset($filter['default']))
                {
                    $queryDefault = $filter['default'];
                    if($filter['type'] == 'date' || $filter['type'] == 'datetime') $queryDefault = $this->processDateVar($filter['default']);
                    if($filter['type'] == 'datetime') $queryDefault .= ':00.000000000';
                    if($filter['type'] == 'multipleselect' && is_array($filter['default'])) $queryDefault = implode("','", $filter['default']);
                }

                $queryDefault = $this->dbh->quote($queryDefault);
                if(strpos($sql, $filter['field'] . 'Condition') === false)
                {
                    $sql = str_replace('$' . $filter['field'], $queryDefault, $sql);
                }
                else
                {
                    $sql = str_replace('$' . $filter['field'] . 'Condition', "{$filter['relatedField']}={$queryDefault}", $sql);
                }
            }
            else
            {
                if(!isset($filter['default'])) continue;

                $default = $filter['default'];
                switch($filter['type'])
                {
                    case 'select':
                        if(is_string($default) && strpos($default, ',') !== false) $default = explode(',', $default);
                        if(is_array($default))
                        {
                            $default = array_filter($default, function($val){return trim((string)$val) != '';});
                            $quotedValues = array();
                            foreach($default as $val) $quotedValues[] = $this->dbh->quote($val);
                            if(empty($quotedValues)) break;
                            $value = '(' . implode(', ', $quotedValues) . ')';
                        }
                        else
                        {
                            if($default === '') break;
                            $value = '(' . $this->dbh->quote($default) . ')';
                        }
                        $filterFormat[$field] = array('operator' => 'IN', 'value' => $value);
                        break;
                    case 'input':
                        $filterFormat[$field] = array('operator' => 'LIKE', 'value' => $this->dbh->quote('%' . $default . '%'));
                        break;
                    case 'date':
                    case 'datetime':
                        $begin = $default['begin'];
                        $end   = $default['end'];

                        if(!empty($begin)) $begin = date('Y-m-d 00:00:00', strtotime($begin));
                        if(!empty($end))   $end   = date('Y-m-d 23:59:59', strtotime($end));

                        if(!empty($begin) &&  empty($end)) $filterFormat[$field] = array('operator' => '>=',       'value' => "'{$begin}'");
                        if( empty($begin) && !empty($end)) $filterFormat[$field] = array('operator' => '<=',       'value' => "'{$end}'");
                        if(!empty($begin) && !empty($end)) $filterFormat[$field] = array('operator' => 'BETWEEN', 'value' => "'{$begin}' AND '{$end}'");
                        break;
                }
            }
        }

        return array($sql, $filterFormat);
    }

    /**
     * 从配置中获取分组字段。
     * Get groups from settings.
     *
     * @param  array    $settings
     * @access public
     * @return array
     */
    public function getGroupsFromSettings($settings)
    {
        $groups = array();
        foreach($settings as $key => $value)
        {
            if(strpos($key, 'group') !== false && $value) $groups[] = $value;
        }

        return array_unique($groups);
    }

    /**
     * 去掉 sql 末尾的分号。
     * Trim semicolon of sql.
     *
     * @param  string    $sql
     * @access public
     * @return string
     */
    public function trimSemicolon($sql)
    {
        return trim($sql, " ;");
    }

    /**
     * 将过滤条件拼接到 sql 上。
     * Append where filter to sql from filters.
     *
     * @param  string      $sql
     * @param  array|false $filters
     * @param  string      $driver
     * @access public
     * @return string
     */
    public function appendWhereFilterToSql($sql, $filters, $driver)
    {
        $connectSQL = '';
        if(!isset($filters[0]['from']) && $filters !== false)
        {
            if(!empty($filters))
            {
                $wheres = array();
                foreach($filters as $field => $filter)
                {
                    $fieldSQL = $this->getFilterFieldSQL($filter, $field, $driver);
                    if($filter['operator'] == 'LIKE' && $filter['value'] == "'%%'")
                    {
                        $wheres[] = "1=1"; // if empty filter then ignore this filter.
                    }
                    else
                    {
                        $wheres[] = "$fieldSQL {$filter['operator']} {$filter['value']}";
                    }
                }

                $whereStr    = implode(' and ', $wheres);
                $connectSQL .= " where $whereStr";
            }
            else
            {
                $connectSQL .= " where 1=0";
            }
        }

        if($connectSQL) $sql = "select * from ( $sql ) tt" . $connectSQL;

        return $sql;
    }

    /**
     * 获取过滤字段的 sql 片段。
     * Get the sql fragment of the filter field.
     *
     * @param  array  $filter
     * @param  string $field
     * @param  string $driver
     * @access public
     * @return string
     */
    public function getFilterFieldSQL($filter, $field, $driver)
    {
        /* 本仓只有 mysql 驱动，直接返回字段引用。 */
        /* This repo only has the mysql driver, return the field reference directly. */
        return "tt.`{$field}`";
    }

    /**
     * 按设置过滤字段。
     * Filter fields with settings.
     *
     * @param  array $fields
     * @param  array $groups
     * @param  array $columns
     * @access public
     * @return array
     */
    public function filterFieldsWithSettings($fields, $groups, $columns)
    {
        $filteredFields = array();
        $settingFields  = $groups;

        foreach($columns as $column)
        {
            $slice = zget($column, 'slice', 'noSlice');
            $settingFields[] = $column['field'];
            if($slice != 'noSlice') $settingFields[] = $slice;
        }

        $settingFields = array_unique($settingFields);
        foreach($settingFields as $field)
        {
            if(!isset($filteredFields[$field]) && isset($fields[$field])) $filteredFields[$field] = $fields[$field];
        }

        return $filteredFields;
    }

    /**
     * 把记录值映射为字段选项。
     * Map record value with field options.
     *
     * @param  array    $records
     * @param  array    $fields
     * @param  string   $driver
     * @access public
     * @return array
     */
    public function mapRecordValueWithFieldOptions($records, $fields, $driver)
    {
        $multipleMappingFields = $this->getPivotSetting('multipleMappingFields', array('testcase-stage'));

        $fieldOptions = $this->getFieldsOptions($fields, $records, $driver);
        $records      = json_decode(json_encode($records), true);
        foreach($records as $index => $record)
        {
            foreach($record as $field => $value)
            {
                if(!isset($fields[$field])) continue;

                $value = is_string($value) ? str_replace('"', '', htmlspecialchars_decode($value)) : $value;
                $record["{$field}_origin"] = $value;
                $tableField = !isset($fields[$field]) ? '' : $fields[$field]['object'] . '-' . $fields[$field]['field'];
                $withComma  = in_array($tableField, $multipleMappingFields);

                $optionList = isset($fieldOptions[$field]) ? $fieldOptions[$field] : array();

                if($withComma)
                {
                    $valueArr  = array_filter(explode(',', $value));
                    $resultArr = array();
                    foreach($valueArr as $val)
                    {
                        $resultArr[] = isset($optionList[$val]) ? $optionList[$val] : $val;
                    }

                    $record[$field] = implode(',', $resultArr);
                }
                else
                {
                    $valueKey       = "$value";
                    $record[$field] = isset($optionList[$valueKey]) ? $optionList[$valueKey] : $value;
                }
                $record[$field] = is_string($record[$field]) ? str_replace('"', '', htmlspecialchars_decode($record[$field])) : $record[$field];
            }

            $records[$index] = (object)$record;
        }

        return $records;
    }

    /**
     * 生成表头列配置。
     * Genereate table cols config.
     *
     * @param  array    $fields
     * @param  array    $groups
     * @param  array    $langs
     * @access public
     * @return array
     */
    public function generateTableCols($fields, $groups, $langs)
    {
        $cols       = array();
        $clientLang = $this->app->getClientLang();
        /* Build cols. */
        foreach($groups as $group)
        {
            $fieldObject  = $fields[$group]['object'];
            $relatedField = $fields[$group]['field'];

            $col = new stdclass();
            $col->name    = $group;
            $col->field   = $relatedField;
            $col->isGroup = true;

            $colLabel = $group;
            if($fieldObject)
            {
                $this->app->loadLang($fieldObject);
                if(isset($this->lang->$fieldObject->$relatedField)) $colLabel = $this->lang->$fieldObject->$relatedField;
            }

            if(isset($langs[$group]) && !empty($langs[$group][$clientLang])) $colLabel = $langs[$group][$clientLang];
            $col->label = $colLabel;

            $cols[0][] = $col;
        }

        return $cols;
    }

    /**
     * 获取列汇总的显示位置。
     * Get show col position.
     *
     * @param  array       $settings
     * @access public
     * @return string noShow | bottom | row | all
     */
    public function getShowColPosition($settings)
    {
        $columnTotal    = zget($settings, 'columnTotal', 'noShow');
        $columnPosition = zget($settings, 'columnPosition', 'bottom');

        if($columnTotal == 'noShow') return 'noShow';
        return $columnPosition;
    }

    /**
     * 判断是否需要展示最后一行汇总。
     * Check whether showColPosition should show last row.
     *
     * @param  string $showColPosition
     * @access public
     * @return bool
     */
    public function isShowLastRow($showColPosition)
    {
        return in_array($showColPosition, array('bottom', 'all'));
    }

    /**
     * 计算列的统计值。
     * Calculate column statistics.
     *
     * @param  array $records
     * @param  string $statistic
     * @param  string $field
     * @access public
     * @return mixed
     */
    public function columnStatistics($records, $statistic, $field)
    {
        $values = array_column($records, $field);
        $numericValues = array_map(function($value)
        {
            return is_numeric($value) ? floatval($value) : 0;
        }, $values);

        if($statistic == 'count')    return count($numericValues);
        if($statistic == 'sum')      return round(array_sum($numericValues), 2);
        if($statistic == 'avg')      return round(array_sum($numericValues) / count($numericValues), 2);
        if($statistic == 'min')      return min($numericValues);
        if($statistic == 'max')      return max($numericValues);
        if($statistic == 'distinct') return count(array_unique($values));
    }

    /**
     * 行数据转树。
     * Convert row data to tree.
     *
     * @param  array $data
     * @access public
     * @return array|string
     */
    public function getGroupTreeWithKey($data)
    {
        $first = reset($data);
        if(!isset($first['groups'])) return $first['groupKey'];

        $tree = array();
        foreach($data as $value)
        {
            $groups = $value['groups'];
            $parentKey = array_shift($groups);
            if(!isset($tree[$parentKey])) $tree[$parentKey] = array();
            $value['groups'] = $groups;
            if(count($groups) == 0) unset($value['groups']);
            $tree[$parentKey][] = $value;
        }

        foreach($tree as $key => $value) $tree[$key] = $this->getGroupTreeWithKey($value);

        return $tree;
    }

    /**
     * 获取单元格数据。
     * Get cell data.
     *
     * @param  string $key
     * @param  array $data
     * @access public
     * @return array
     */
    public function formatCellData($key, $data)
    {
        if(!isset($data[$key])) return array();

        $cellData = $data[$key];
        foreach($cellData as $colKey => $colValue)
        {
            if(is_scalar($colValue))
            {
                $cellData[$colKey] = array('value' => $colValue);
            }
            else
            {
                $value = $colValue['value'];
                $colValue['value'] = is_scalar($value) ? $value : '/';
                $cellData[$colKey] = $colValue;
            }
        }

        return $cellData;
    }

    /**
     * 计算列的总计值。
     * Calculate column total.
     *
     * @param  array  $data
     * @param  string $totalKey
     * @access public
     * @return array
     */
    public function getColumnSummary($data, $totalKey)
    {
        $summary = array();
        foreach($data as $columns)
        {
            foreach($columns as $colKey => $colValue)
            {
                if(!isset($summary[$colKey]))
                {
                    $summary[$colKey] = $colValue;
                }
                else
                {
                    $isGroup   = zget($colValue, 'isGroup', 1);
                    $value     = zget($colValue, 'value', '');
                    $isNumeric = is_numeric($value);

                    $summary[$colKey]['value'] = !$isGroup && $isNumeric ? $summary[$colKey]['value'] + $value : $value;
                }
            }
        }

        $summary[$totalKey] = array('value' => '$total$');
        /* 删除汇总行的下钻配置。*/
        /* Delete drilldown config of summary row. */
        foreach($summary as $key => $value)
        {
            if(isset($value['value']) && is_numeric($value['value'])) $summary[$key]['value'] = round($summary[$key]['value'], 2);
            if(isset($value['drillFields']))
            {
                unset($summary[$key]['drillFields']);
            }
        }

        return $summary;
    }

    /**
     * 添加行总计到树数据中。
     * Add row summary to tree data.
     *
     * @param  array $groupTree
     * @param  array $data
     * @param  array $groups
     * @param  int   $currentGroup
     * @access public
     * @return array
     */
    public function addRowSummary($groupTree, $data, $groups, $currentGroup = 0)
    {
        $first = reset($groupTree);
        if(is_scalar($first))
        {
            $groupData = array();
            $rows      = array();
            foreach($groupTree as $groupKey)
            {
                $groupData[$groupKey] = $this->formatCellData($groupKey, $data);
                $rows[$groupKey]      = $data[$groupKey];
            }
            return array('rows' => $rows, 'summary' => $this->getColumnSummary($groupData, $groups[$currentGroup]));
        }

        $rows = array();
        foreach($groupTree as $key => $children) $rows[$key] = $this->addRowSummary($children, $data, $groups, $currentGroup + 1);
        $groupData = array_column($rows, 'summary');

        return array('rows' => $rows, 'summary' => $this->getColumnSummary($groupData, $groups[$currentGroup]));
    }

    /**
     * 去除数据中的额外信息，只保留单元格数据。
     * Remove extra info from data, only keep cell data.
     *
     * @param  array $records
     * @access public
     * @return array
     */
    public function pureCrystalData($records)
    {
        $pureData = array();
        foreach($records as $key => $record)
        {
            $columns = $record['columns'];
            $groups  = $record['groups'];
            $pureData[$key] = $groups;
            foreach($columns as $colKey => $colValue)
            {
                $cellData = $colValue['cellData'];
                if(isset($colValue['rowTotal'])) $cellData['total'] = $colValue['rowTotal'];
                if(isset($cellData['value']))
                {
                    $pureData[$key][$colKey] = $cellData;
                }
                else
                {
                    foreach($cellData as $sliceKey => $sliceValue) $pureData[$key][$colKey . '_' . $sliceKey] = $sliceValue;
                }
            }
        }

        return $pureData;
    }

    /**
     * 拍平切片列数据。
     * Flatten slice column data.
     *
     * @param  array  $row
     * @access public
     * @return array
     */
    public function flattenRow($row)
    {
        $record = array();
        foreach($row as $colKey => $cell)
        {
            if(is_scalar($cell))
            {
                $record[$colKey] = array('value' => $cell);
            }
            elseif(isset($cell['value']))
            {
                $record[$colKey] = $cell;
            }
        }

        return $record;
    }

    /**
     * 拍平透视表树结构数据。
     * Flatten pivot table tree structure data.
     *
     * @param  array  $crystalData
     * @param  bool   $withGroupSummary
     * @access public
     * @return array
     */
    public function flattenCrystalData($crystalData, $withGroupSummary = false)
    {
        $first = reset($crystalData);
        if(!isset($first['rows']))
        {
            $records = array();
            foreach($crystalData as $row) $records[] = $this->flattenRow($row);
            return $records;
        }

        $records = array();
        foreach($crystalData as $value)
        {
            $groupRecords = $this->flattenCrystalData($value['rows'], $withGroupSummary);
            if($withGroupSummary && isset($value['summary'])) $groupRecords[] = $this->flattenRow($value['summary']);
            $records = array_merge($records, $groupRecords);
        }

        return $records;
    }

    /**
     * 处理行合并单元格。
     * Process row span cell.
     *
     * @param  array $records
     * @param  array $groups
     * @access public
     * @return array
     */
    public function processRowSpan($records, $groups)
    {
        $lastGroupValue = array();
        foreach($groups as $group) $lastGroupValue[$group] = '';

        /* 定义内部函数：获取当前行数据的分组值。*/
        /* Define internal function: get current row data's group value. */
        $getGroupValue = function($record, $key, $index) use ($groups)
        {
            $value = array($record[$key]['value']);
            $index -= 1;
            while($index >= 0)
            {
                $value[] = $record[$groups[$index]]['value'];
                $index -= 1;
            }

            return $value;
        };

        $groupsRowSpan = array();
        foreach($records as $index => $record)
        {
            $rowSpan = 1;
            foreach($record as $colKey => $cell)
            {
                if(!isset($cell['value']) || !is_array($cell['value'])) continue;
                $rowSpan = max(count($cell['value']), $rowSpan);
            }

            foreach($record as $colKey => $cell)
            {
                $record[$colKey]['rowSpan'] = is_scalar($cell['value']) ? $rowSpan : 1;
            }
            $records[$index] = $record;

            foreach($groups as $groupIndex => $group)
            {
                $groupValue    = $getGroupValue($record, $group, $groupIndex);
                $groupValueStr = implode('_', $groupValue);

                if($groupValue[0] !== '$total$' && $groupValueStr === $lastGroupValue[$group] && isset($groupsRowSpan[$group]))
                {
                    $groupRowSpan = array_pop($groupsRowSpan[$group]);
                    $groupRowSpan['index'][] = $index;
                    $groupRowSpan['rowSpan'] += $rowSpan;
                    $groupsRowSpan[$group][] = $groupRowSpan;
                }
                else
                {
                    $groupsRowSpan[$group][] = array('index' => array($index), 'rowSpan' => $rowSpan);
                }
                $lastGroupValue[$group] = $groupValueStr;
            }
        }

        foreach($groupsRowSpan as $group => $groupRowSpans)
        {
            foreach($groupRowSpans as $groupRowSpan)
            {
                $indexes = $groupRowSpan['index'];
                foreach($indexes as $index)
                {
                    $records[$index][$group]['rowSpan'] = $groupRowSpan['rowSpan'];
                }
            }
        }

        return $records;
    }

    /**
     * 计算行汇总值。
     * Calculate row total.
     *
     * @param  array $row
     * @access public
     * @return array
     */
    public function getRowTotal($row)
    {
        $rowTotal = array();
        foreach($row as $cell)
        {
            if(!isset($cell['percentage'])) continue;
            list(,,,, $columnKey) = $cell['percentage'];
            if(!isset($rowTotal[$columnKey])) $rowTotal[$columnKey] = 0;
            $rowTotal[$columnKey] += $cell['value'];
        }

        return $rowTotal;
    }

    /**
     * 计算百分比值。
     * Calculate percentage.
     *
     * @param  array $row
     * @param  array $rowTotal
     * @param  array $columnTotal
     * @access public
     * @return array
     */
    public function setPercentage($row, $rowTotal, $columnTotal)
    {
        foreach($row as $key => $cell)
        {
            if(!isset($cell['percentage'])) continue;
            list(,,$showMode,, $columnKey) = $cell['percentage'];
            if($showMode == 'row')    $cell['percentage'][1] = $rowTotal[$columnKey];
            if($showMode == 'column') $cell['percentage'][1] = $columnTotal[$key]['value'];
            if($showMode == 'total')
            {
                $total = 0;
                foreach($columnTotal as $column)
                {
                    if(!isset($column['percentage'])) continue;
                    $percentage = $column['percentage'];
                    if($percentage[4] === $columnKey) $total += $column['value'];
                }
                $cell['percentage'][1] = $total;
            }

            $cell['percentage'][0] = $cell['value'];
            $row[$key] = $cell;
        }

        return $row;
    }

    /**
     * 处理百分比值。
     * Process percentage.
     *
     * @param  array $crystalData
     * @param  array $allSummary
     * @access public
     * @return array
     */
    public function processPercentage($crystalData, $allSummary)
    {
        $rows    = $crystalData['rows'];
        $summary = $crystalData['summary'];

        foreach($rows as $key => $row)
        {
            if(isset($row['rows']))
            {
                $rows[$key] = $this->processPercentage($row, $allSummary);
            }
            else
            {
                $rowTotal = $this->getRowTotal($row);
                $rows[$key] = $this->setPercentage($row, $rowTotal, $allSummary);
            }
        }

        $rowTotal = $this->getRowTotal($summary);
        $summary = $this->setPercentage($summary, $rowTotal, $allSummary);

        return array('rows' => $rows, 'summary' => $summary);
    }

    /**
     * 对数据进行分组。
     * Group records.
     *
     * @param  array $records
     * @param  array $groups
     * @access public
     * @return array
     */
    public function groupRecords($records, $groups)
    {
        $groupsData = array();
        foreach($records as $record)
        {
            $key = $this->getGroupsKey($groups, $record);
            if(!isset($groupsData[$key])) $groupsData[$key] = array();
            $groupsData[$key][] = $record;
        }

        return $groupsData;
    }

    /**
     * 设置切片列去重后的值。
     * Set unique slices.
     *
     * @param  array $records
     * @param  array $setting
     * @access public
     * @return array
     */
    public function setUniqueSlices($records, $setting)
    {
        static $slices = array();

        $slice = zget($setting, 'slice', 'noSlice');
        if($slice == 'noSlice') return $setting;
        if(isset($slices[$slice]))
        {
            $setting['uniqueSlices'] = $slices[$slice];
            return $setting;
        }

        $uniqueSlices = array();
        foreach($records as $record)
        {
            if(!isset($uniqueSlices[$record->$slice])) $uniqueSlices[$record->$slice] = $record;
        }
        $slices[$slice] = $uniqueSlices;
        $setting['uniqueSlices'] = $uniqueSlices;
        return $setting;
    }

    /**
     * 根据字段的值过滤记录。
     * Filter records by field value.
     *
     * @param  array $records
     * @param  string $field
     * @access public
     * @return array
     */
    public function getSliceRecords($records, $field)
    {
        $sliceRecords = array();
        foreach($records as $record)
        {
            if(!isset($sliceRecords[$record->$field])) $sliceRecords[$record->$field] = array();
            $sliceRecords[$record->$field][] = $record;
        }

        return $sliceRecords;
    }

    /**
     * 计算单元格数据。
     * Calculate cell data.
     *
     * @param  string $columnKey
     * @param  array $records
     * @param  array $setting
     * @access public
     * @return array
     */
    public function getCellData($columnKey, $records, $setting)
    {
        $field      = zget($setting, 'field', '');
        $showOrigin = zget($setting, 'showOrigin', 0);

        if($showOrigin) return array('value' => array_column($records, $field), 'isGroup' => false);

        $stat       = zget($setting, 'stat', 'count');
        $slice      = zget($setting, 'slice', 'noSlice');
        $showMode   = zget($setting, 'showMode', 'default');
        $showTotal  = zget($setting, 'showTotal', 'noShow');
        $monopolize = zget($setting, 'monopolize', 0);
        $isSlice    = $slice != 'noSlice';

        if(!$isSlice)
        {
            $value = $this->columnStatistics($records, $stat, $field);
            $cell  = array('value' => $value, 'isGroup' => false);

            if($showMode == 'default') return $cell;
            $cell['percentage'] = array($value, 1, $showMode, $monopolize, $columnKey);

            return $cell;
        }

        /* 处理切片列的情况。 */
        /* Handle the slice column situation. */
        $uniqueSlices = zget($setting, 'uniqueSlices', array());
        $cell         = array();
        $sliceRecords = $this->getSliceRecords($records, $slice);
        foreach($uniqueSlices as $sliceRecord)
        {
            $sliceValue   = $sliceRecord->$slice;
            $sliceKey     = "{$slice}_{$sliceValue}";

            $value = $this->columnStatistics(zget($sliceRecords, $sliceValue, array()), $stat, $field);

            $sliceCell = array('value' => $value, 'drillFields' => array($slice => $sliceRecord->{$slice . '_origin'}), 'isGroup' => false);
            if($showMode != 'default') $sliceCell['percentage'] = array($value, 1, $showMode, $monopolize, $columnKey);

            $cell[$sliceKey] = $sliceCell;
        }

        if($showTotal != 'noShow')
        {
            $value = array_sum(array_column($cell, 'value'));
            $totalCell = array('value' => $value, 'isGroup' => false);
            if($showMode != 'default') $totalCell['percentage'] = array($value, 1, $showMode, $monopolize, "rowTotal_{$columnKey}");
            $cell['total'] = $totalCell;
        }

        return $cell;
    }

    /**
     * 添加下钻字段信息。
     * Add drill fields information.
     *
     * @param  array $cell
     * @param  array $drillFields
     * @access public
     * @return array
     */
    public function addDrillFields($cell, $drillFields)
    {
        if(isset($cell['value']))
        {
            if(!isset($cell['drillFields'])) $cell['drillFields'] = array();
            $cell['drillFields'] = array_merge($cell['drillFields'], $drillFields);
            return $cell;
        }

        foreach($cell as $sliceKey => $sliceCell)
        {
            if($sliceKey == 'total') continue;
            $cell[$sliceKey] = $this->addDrillFields($sliceCell, $drillFields);
        }

        return $cell;
    }

    /**
     * 根据列配置，计算透视表数据。
     * Calculate pivot table data.
     *
     * @param  array $groups
     * @param  array $records
     * @param  array $settings
     * @access public
     * @return array
     */
    public function processCrystalData($groups,$records, $settings)
    {
        $crystalData    = array();
        $columnSettings = $settings['columns'];
        $groupRecords   = $this->groupRecords($records, $groups);
        foreach($groupRecords as $key => $data)
        {
            $record              = reset($data);
            $groupValues         = array();
            $groupOriginalValues = array();
            foreach($groups as $group)
            {
                $groupValues[$group] = $record->$group;
                $groupOriginalValues[$group] = $record->{$group . '_origin'};
            }

            $columns = array();
            foreach($columnSettings as $colIndex => $setting)
            {
                $setting   = $this->setUniqueSlices($records, $setting);
                $field     = zget($setting, 'field', '');
                $columnKey = "{$field}{$colIndex}";

                $cellData = $this->getCellData($columnKey, $data, $setting);
                $cellData = $this->addDrillFields($cellData, $groupOriginalValues);

                $columns[$columnKey] = array('setting' => $setting, 'cellData' => $cellData);
            }

            $crystalData[$key] = array('groups' => $groupValues, 'groupKey' => $key, 'columns' => $columns);
        }

        return $crystalData;
    }

    /**
     * 处理透视表数据为可以显示的格式。
     * Process pivot table data for display.
     *
     * @param  array $records
     * @access public
     * @return array
     */
    public function processRecordsForDisplay($records)
    {
        $roundIfMoreThanTwoDecimals = function($number)
        {
            if(!is_numeric($number)) return $number;

            $number = (float)$number;
            if(floor($number) === $number) return $number;

            $decimalPart = explode('.', strval($number));
            if(isset($decimalPart[1]) && strlen($decimalPart[1]) > 2) return $number;
            return $number;
        };

        $values = array();
        foreach($records as $record)
        {
            $row        = array();
            $arrayValue = false;
            foreach($record as $colKey => $cell)
            {
                $cellValue = $cell['value'] === '$total$' ? $this->lang->pivot->total : $cell['value'];
                if(is_array($cellValue)) $arrayValue = $cellValue;

                $cellValue = $roundIfMoreThanTwoDecimals($cellValue);
                $row[$colKey] = $cellValue;
                if(isset($cell['percentage']))
                {
                    list($number, $total,, $monopolize) = $cell['percentage'];
                    if($monopolize) $colKey .= '_percentage';
                    if(!$total) $total = 100;
                    $row[$colKey] = round($number / $total * 100, 2) . '%';
                }
            }

            if(is_array($arrayValue))
            {
                foreach(array_keys($arrayValue) as $index)
                {
                    $flattenValue = array();
                    foreach($row as $key => $value)
                    {
                        $value = is_scalar($value) ? $value : $value[$index];
                        $flattenValue[$key] = $value;
                    }
                    $values[] = $flattenValue;
                }
            }
            else
            {
                $values[] = $row;
            }
        }

        return $values;
    }

    /**
     * 获取合并单元格配置。
     * Get row span config.
     *
     * @param  array $records
     * @access public
     * @return array
     */
    public function getRowSpanConfig($records)
    {
        $configs = array();
        foreach($records as $record)
        {
            $arrayValue = false;
            foreach($record as $cell)
            {
                if(is_array($cell['value'])) $arrayValue = $cell['value'];
            }

            if(!is_array($arrayValue)) $arrayValue = array(1);
            $configs = array_merge($configs, array_fill(0, count($arrayValue), array_column($record, 'rowSpan')));
        }
        return $configs;
    }

    /**
     * 获取下钻字段配置。
     * Get drill fields config.
     *
     * @param  array $records
     * @param  array $groups
     * @access public
     * @return array
     */
    public function getDrillsFromRecords($records, $groups)
    {
        $drills = array();
        foreach($records as $record)
        {
            $groupKey = $this->getGroupsKey($groups, (object)$record);
            if(!isset($drills[$groupKey])) $drills[$groupKey] = array('drillFields' => array());
            foreach($record as $colKey => $cell)
            {
                if(isset($cell['drillFields'])) $drills[$groupKey]['drillFields'][$colKey] = $cell['drillFields'];
            }
        }

        return $drills;
    }

    /**
     * 处理查询过滤器的默认值。
     * Process query filter defaults.
     *
     * @param  array|false $filters
     * @access public
     * @return array
     */
    public function processQueryFilterDefaults($filters)
    {
        if(!is_array($filters)) return $filters;
        $options = array();
        foreach($filters as $index => $filter)
        {
            if(empty($filter['default'])) continue;
            if(!isset($filter['from']) || $filter['from'] != 'query') continue;
            if($filter['type'] !== 'multipleselect') continue;

            $type       = $filter['type'];
            $typeOption = $filter['typeOption'];
            if(strpos($type, 'select') !== false && !isset($options[$typeOption])) $options[$typeOption] = $this->getSysOptions($typeOption);

            $default = $filter['default'];
            if(is_string($default)) $default = explode(',', $default);
            if(!is_array($default)) continue;
            $filters[$index]['default'] = array_intersect($default, array_keys($options[$typeOption]));
        }

        return $filters;
    }

    /**
     * 生成透视表。
     * Gen sheet.
     *
     * @param  array       $fields
     * @param  array       $settings
     * @param  string      $sql
     * @param  array|false $filters
     * @param  array       $langs
     * @param  string      $driver
     * @access public
     * @return array
     */
    public function genSheet($fields, $settings, $sql, $filters, $langs = array(), $driver = 'mysql')
    {
        $groups = $this->getGroupsFromSettings($settings);
        $cols   = $this->generateTableCols($fields, $groups, $langs);

        $data         = new stdclass();
        $data->groups = $groups;
        $data->cols   = $cols;
        $data->array  = array();
        $data->drills = array();

        if(!isset($settings['columns'])) return array($data, array());

        $filters = $this->processQueryFilterDefaults($filters);
        /* Replace the variable with the default value. */
        $sql = $this->processVars($sql, (array)$filters);
        $sql = $this->trimSemicolon($sql);
        $sql = $this->appendWhereFilterToSql($sql, $filters, $driver);

        $records = $this->queryRecords($sql);
        $settingFields = $this->filterFieldsWithSettings($fields, $groups, $settings['columns']);
        $records = $this->mapRecordValueWithFieldOptions($records, $settingFields, $driver);

        if(empty($records)) return array($data, array());

        foreach($settings['columns'] as $columnSetting)
        {
            $cols = $this->getTableHeader($records, $columnSetting, $fields, $cols, $sql, $langs, $driver);
        }

        /* 根据列配置和分组配置，计算透视表数据。*/
        /* Calculate crystal data based on column settings and group settings. */
        $crystalData = $this->processCrystalData($groups, $records, $settings);

        /* 将扁平的透视表数据转换成树形结构。*/
        /* Convert flattened pivot table data to tree structure. */
        $groupTree = $this->getGroupTreeWithKey($crystalData);

        /* 净化处理透视表数据中的额外信息，只留下与单元格数据相关的信息。*/
        /* Clean up the extra information in pivot table data. */
        $crystalData = $this->pureCrystalData($crystalData);

        /* 基于各级分组，计算每个分组的总计数据行。*/
        /* Calculate total data rows based on each group. */
        $crystalData = $this->addRowSummary($groupTree, $crystalData, $groups);

        /* 计算百分比的值。*/
        /* Calculate percentage values. */
        $crystalData = $this->processPercentage($crystalData, $crystalData['summary']);

        /* 将树形结构转换成扁平的透视表数据。*/
        /* Convert tree structure to flattened pivot table data. */
        $columnPosition = $this->getShowColPosition($settings);
        $showGroupTotal = in_array($columnPosition, array('row', 'all'));
        $showAllTotal   = in_array($columnPosition, array('bottom', 'all'));
        $records = $this->flattenCrystalData($crystalData['rows'], $showGroupTotal);
        if($showAllTotal) $records[] = $this->flattenRow($crystalData['summary']);

        /* 计算行合并单元格的配置。*/
        /* Calculate row span config. */
        $records = $this->processRowSpan($records, $groups);

        $data->cols         = $cols;
        $data->array        = $this->processRecordsForDisplay($records);
        $data->drills       = $this->getDrillsFromRecords($records, $groups);
        $data->showAllTotal = $showAllTotal;

        $configs = $this->getRowSpanConfig($records);

        /* $data->groups  array 代表分组，最多三个
         * $data->cols    array thead数据，其中对象有三个属性：name：分组，label：列的名字，isGroup：标识是不是分组
         * $data->arrays  array tbody数据, 其中每一个数组内是一行td的数据
         *
         * $configs, eg: array(0 => array(0 => 2, 1 => 1), 2 => array(0 => 2))
         * 代表在整个tbody中，位于[0,0]坐标的td rowspan为2，位于[0,1]坐标的td rowspan为1, 位于[2,0]坐标的td rowspan为2
         */
        return array($data, $configs);
    }

    /**
     * 按原始 sql 生成表格数据。
     * Gen sheet by origin sql.
     *
     * @param  array       $fields
     * @param  array       $settings
     * @param  string      $sql
     * @param  array|false $filters
     * @param  array       $langs
     * @param  string      $driver
     * @access public
     * @return array
     */
    public function genOriginSheet($fields, $settings, $sql, $filters, $langs = array(), $driver = 'mysql')
    {
        $sql = $this->processVars($sql, (array)$filters);
        $sql = $this->trimSemicolon($sql);
        $sql = $this->appendWhereFilterToSql($sql, $filters, $driver);

        $rows         = $this->queryRecords($sql);
        $rows         = $this->filterSpecialChars($rows);
        $fieldOptions = $this->getFieldsOptions($fields, $rows);

        $rows = json_decode(json_encode($rows), true);

        $cols   = array();
        $drills = zget($settings, 'drills', array());
        /* Build cols. */
        foreach($fields as $key => $field)
        {
            $col = new stdclass();
            $col->name    = $key;
            $col->isGroup = true;
            $col->label   = $this->getColLabel($key, $fields, $langs);

            if(isset($drills[$key]))
            {
                $col->isDrilling = true;
                $col->condition  = $drills[$key];
                $col->drillField = $key;
            }

            $cols[0][] = $col;
        }

        $dataDrills      = array();
        $rowsAfterFields = array();
        foreach($rows as $key => $row)
        {
            $drillFields    = array();
            $rowAfterFields = array();
            foreach($row as $field => $value)
            {
                if(isset($drills[$field]))
                {
                    $drillField = array();
                    foreach($drills[$field] as $condition)
                    {
                        $queryField = $condition['queryField'];
                        $drillField[$queryField] = $row[$queryField];
                    }
                    $drillFields[$field] = $drillField;
                }
                $optionList  = isset($fieldOptions[$field]) ? $fieldOptions[$field] : array();
                $rowAfterFields[$field] = isset($optionList[(string)$value]) ? $optionList[(string)$value] : $value;
            }
            $dataDrills[$key] = array('drillFields' => $drillFields);

            $rowsAfterFields[$key] = $rowAfterFields;
        }

        $data = new stdclass();
        $data->cols   = $cols;
        $data->array  = $rowsAfterFields;
        $data->drills = $dataDrills;

        $configs = array_fill(0, count($rows), array_fill(0, count($fields), 1));

        return array($data, $configs);
    }

    /**
     * 过滤记录中的特殊字符。
     * Filter special chars of the records.
     *
     * @param  array $records
     * @access public
     * @return array
     */
    public function filterSpecialChars($records)
    {
        if(empty($records)) return $records;

        foreach($records as $index => $record)
        {
            foreach($record as $field => $value)
            {
                $value = is_string($value) ? str_replace('"', '', htmlspecialchars_decode($value)) : $value;
                if(is_object($record)) $record->$field = $value;
                if(is_array($record))  $record[$field] = $value;
            }
            $records[$index] = $record;
        }
        return $records;
    }

    /**
     * 获取列名称。
     * Get col label.
     *
     * @param  string  $key
     * @param  array   $fields
     * @param  array   $langs
     * @access public
     * @return string
     */
    public function getColLabel($key, $fields, $langs)
    {
        $clientLang = $this->app->getClientLang();

        $fieldLang = zget($fields[$key], $clientLang, '');
        if(!empty($fieldLang)) return $fieldLang;

        if(isset($langs[$key]))
        {
            $lang = zget($langs[$key], $clientLang, '');
            if(!empty($lang)) return $lang;
        }

        $object = zget($fields[$key], 'object', '');
        if($object)
        {
            $this->app->loadLang($object);
            if(isset($this->lang->{$object}->{$key})) return $this->lang->{$object}->{$key};
        }

        $name = zget($fields[$key], 'name', '');
        if(!empty($name)) return $name;

        return $key;
    }

    /**
     * 获取表头。
     * Get the header of the table.
     *
     * @param  array  $columnRows
     * @param  array  $column
     * @param  array  $fields
     * @param  array  $cols
     * @param  string $sql
     * @param  array  $langs
     * @param  string $driver
     * @access public
     * @return array
     */
    public function getTableHeader($columnRows, $column, $fields, $cols, $sql, $langs = array(), $driver = 'mysql')
    {
        $stat       = zget($column, 'stat', '');
        $showMode   = zget($column, 'showMode', 'default');
        $monopolize = $showMode == 'default' ? '' : zget($column, 'monopolize', '');
        $showOrigin = (bool)zget($column, 'showOrigin', 0);

        $isDrilling = isset($column['drill']) && zget($column['drill'], 'condition', '');
        $drillField = $isDrilling ? zget($column['drill'], 'field', '') : '';
        $condition  = $isDrilling ? zget($column['drill'], 'condition', '') : '';

        $col = new stdclass();
        $col->name       = $column['field'];
        $col->isGroup    = false;
        $col->showOrigin = $showOrigin;
        $col->isDrilling = $isDrilling;
        $col->drillField = $drillField;
        $col->condition  = $condition;

        $fieldObject  = $fields[$column['field']]['object'];
        $relatedField = $fields[$column['field']]['field'];
        $colLabel     = $this->getColLabel($column['field'], $fields, $langs);

        if(!$showOrigin)
        {
            $colLabel = str_replace('{$field}', $colLabel, $this->lang->pivot->colLabel);
            $colLabel = str_replace('{$stat}', zget($this->lang->pivot->stepDesign->statList, $stat), $colLabel);
            if($showMode != 'default') $colLabel .= sprintf($this->lang->pivot->colShowMode, zget($this->lang->pivot->stepDesign->showModeList, $showMode));
        }
        $col->label = $colLabel;

        $slice = zget($column, 'slice', 'noSlice');
        $col->isSlice = $slice != 'noSlice';
        if($slice != 'noSlice' && !$showOrigin)
        {
            if(!isset($cols[1]))
            {
                foreach($cols[0] as $colData) $colData->rowspan = '2';
                $cols[1] = array();
            }
            $sliceList = array();
            foreach($columnRows as $rows) $sliceList[$rows->{$slice}] = $rows->{$slice};

            $optionList = $this->getSysOptions($fields[$slice]['type'], $fields[$slice]['object'], $fields[$slice]['field'], $sql, '', $driver);
            foreach($sliceList as $field)
            {
                $childCol = new stdclass();
                $childCol->name       = $field;
                $childCol->isGroup    = false;
                $childCol->label      = isset($optionList[$field]) ? $optionList[$field] : $field;
                $childCol->label      = empty($childCol->label) ? $this->lang->pivot->empty : $childCol->label;
                $childCol->colspan    = $monopolize ? 2 : 1;
                $childCol->isDrilling = $isDrilling;
                $childCol->drillField = $drillField;
                $childCol->condition  = $condition;
                $cols[1][] = $childCol;
            }
            $col->colspan = count($sliceList);
            if($monopolize) $col->colspan *= 2;

            if(zget($column, 'showTotal', 'noShow') !== 'noShow')
            {
                $childCol = new stdclass();
                $childCol->name       = 'sum';
                $childCol->isGroup    = false;
                $childCol->label      = $this->lang->pivot->stepDesign->total;
                $childCol->colspan    = $monopolize ? 2 : 1;
                $childCol->isDrilling = $isDrilling;
                $childCol->drillField = $drillField;
                $childCol->condition  = $condition;
                $cols[1][] = $childCol;
                $col->colspan += $childCol->colspan;
            }

            $cols[0][] = $col;
        }
        else
        {
            $col->rowspan = !isset($cols[1]) ? '1' : '2';
            $col->colspan = $monopolize ? 2 : 1;
            $cols[0][] = $col;
        }

        return $cols;
    }

    /**
     * 拼接记录的分组键。
     * Implode group keys of record.
     *
     * @param  array  $groups
     * @param  object $record
     * @access public
     * @return string
     */
    public function getGroupsKey($groups, $record)
    {
        $groupsKey = array();
        foreach($groups as $group) $groupsKey[] = is_scalar($record->$group) ? $record->$group : $record->$group['value'];

        return implode('_', $groupsKey);
    }

    /**
     * 获取系统选项。
     * Get sys options.
     *
     * @param  string $type
     * @param  string $object
     * @param  string $field
     * @param  mixed  $source
     * @param  string $saveAs
     * @param  string $driver
     * @access public
     * @return array
     */
    public function getSysOptions($type, $object = '', $field = '', $source = '', $saveAs = '', $driver = 'mysql')
    {
        $scopeOptionList = $this->getPivotSetting('scopeOptionList', array('user', 'product', 'project', 'dept', 'product.status', 'product.type', 'productplan.status', 'project.status', 'project.type', 'project.model', 'project.projectType'));
        if(in_array($type, $scopeOptionList)) return $this->getScopeOptions($type);
        if(!$field) return array();

        $options = array();
        switch($type)
        {
            case 'option':
            case 'object':
                /* 本仓没有字段 schema 定义，option 类型降级为对象表取值。 */
                /* No field schema in this repo, the option type degrades to the object table lookup. */
                $options = $this->getObjectOptions($object, $field);
                break;
            case 'string':
            case 'number':
                if($source)
                {
                    $options = array();
                    if(is_array($source))
                    {
                        foreach($source as $row) if(isset($row->$field)) $options["{$row->$field}"] = $row->$field;
                    }
                    else
                    {
                        $keyField   = $field;
                        $valueField = $saveAs ? $saveAs : $field;
                        $options = $this->getOptionsFromSql($source, $keyField, $valueField);
                    }
                }
                break;
        }

        if(is_string($source) && $source && $saveAs && in_array($type, array('user', 'product', 'project', 'dept', 'option', 'object')))
        {
            $options = $this->getOptionsFromSql($source, $field, $saveAs);
        }

        return array_filter($options);
    }

    /**
     * 获取系统选项（用户/产品/项目/部门/语言项）。
     * Get scope options.
     *
     * @param  string $type
     * @access private
     * @return array
     */
    private function getScopeOptions($type)
    {
        $options = array();
        switch($type)
        {
            case 'user':
                $options = $this->loadModel('user')->getPairs('noletter');
                break;
            case 'product':
                $options = $this->loadModel('product')->getPairs();
                break;
            case 'project':
                $options = $this->loadModel('project')->getPairs();
                break;
            case 'dept':
                $options = $this->loadModel('dept')->getOptionMenu(0);
                break;
            default:
                if(strpos($type, '.') !== false)
                {
                    $params = explode('.', $type);
                    if(!empty(array_filter($params)))
                    {
                        $module   = $params[0];
                        $typeList = $params[1] . 'List';
                        $this->app->loadLang($module);
                        if(isset($this->lang->$module->$typeList)) $options = $this->lang->$module->$typeList;
                    }
                }
                break;
        }

        return $options;
    }

    /**
     * 通过对象表获取字段选项。
     * Get object options from the object table.
     *
     * @param  string $object
     * @param  string $field
     * @access private
     * @return array
     */
    private function getObjectOptions($object, $field)
    {
        $options  = array();
        $useTable = isset($this->config->objectTables[$object]) ? $this->config->objectTables[$object] : '';
        if(!$useTable) return $options;

        /* 用 SHOW COLUMNS 校验字段是否存在，不存在时退化为 id。 */
        /* Use SHOW COLUMNS to check the field, degrade to id when it does not exist. */
        $useField = $field ? $field : 'id';
        $columns  = array();
        $stmt     = $this->dbh->query("SHOW COLUMNS FROM $useTable");
        if($stmt) foreach($stmt->fetchAll() as $column) $columns[] = $column->Field;
        if(!in_array($useField, $columns)) $useField = 'id';

        $options = $this->dao->select("id, $useField")->from($useTable)->fetchPairs();
        foreach($options as $key => $value) $options[$key] = is_string($value) ? str_replace('"', '', htmlspecialchars_decode($value)) : $value;

        return $options;
    }

    /**
     * 通过 sql 获取键值对选项。
     * Get pairs from sql by keyField and valueField.
     *
     * @param  string $sql
     * @param  string $keyField
     * @param  string $valueField
     * @access private
     * @return array
     */
    private function getOptionsFromSql($sql, $keyField, $valueField)
    {
        $options = array();
        $records = $this->queryRecords($sql);
        $sample  = current($records);

        if(!$sample || !isset($sample->$keyField) || !isset($sample->$valueField)) return $options;

        foreach($records as $record)
        {
            $options[$record->$keyField] = $record->$valueField;
        }

        return $options;
    }

    /**
     * 获取字段选项。
     * Get field options.
     *
     * @param  array  $fieldSettings
     * @param  mixed  $records
     * @param  string $driver
     * @access public
     * @return array
     */
    public function getFieldsOptions($fieldSettings, $records, $driver = 'mysql')
    {
        $options = array();

        foreach($fieldSettings as $key => $fieldSetting)
        {
            $type   = $fieldSetting['type'];
            $object = $fieldSetting['object'];
            $field  = $fieldSetting['field'];

            $options[$key] = $this->getSysOptions($type, $object, $field, $records, '', $driver);
        }

        return $options;
    }

    /**
     * 使用数据和行合并配置构建表格。
     * Build table use data and rowspan.
     *
     * @param  object $data
     * @param  array  $configs
     * @access public
     * @return string
     */
    public function buildPivotTable($data, $configs)
    {
        $width   = 128;

        $nowSpan = 1;
        $inFlow  = false;

        if(!empty($configs))
        {
            /* 处理不需要展示的单元格，设置为0 */
            $columnCount = count(current($configs));
            $lineCount   = count($configs);
            for($i = 0; $i < $columnCount; $i ++)
            {
                for($j = 0; $j < $lineCount; $j ++)
                {
                    if($configs[$j][$i] > 1 && !$inFlow)
                    {
                        $inFlow  = true;
                        $nowSpan = $configs[$j][$i];
                        continue;
                    }

                    if($configs[$j][$i] > 1 && $inFlow)
                    {
                        $configs[$j][$i] = 0;

                        $nowSpan --;
                        if($nowSpan == 1) $inFlow = false;
                    }
                }
            }
        }

        /* Init table. */
        $table = "<div class='reportData'><table class='table table-condensed table-striped table-bordered table-fixed datatable' style='width: auto; min-width: 100%' data-fixed-left-width='400'>";

        $showOrigins = array();
        $hasShowOrigin = false;

        foreach($data->cols[0] as $col)
        {
            $colspan       = zget($col, 'colspan', 1);
            $showOrigin    = isset($col->showOrigin) ? $col->showOrigin : false;
            $colShowOrigin = array_fill(0, $colspan, $showOrigin);
            $showOrigins   = array_merge($showOrigins, $colShowOrigin);
            if($showOrigin) $hasShowOrigin = true;
        }

        /* Init table thead. */
        $table .= "<thead>";
        foreach($data->cols as $lineCols)
        {
            $table .= "<tr>";
            foreach($lineCols as $col)
            {
                $thName  = $col->label;
                $colspan = zget($col, 'colspan', 1);
                $rowspan = zget($col, 'rowspan', 1);
                $isGroup = zget($col, 'isGroup', false);

                if($isGroup) $thHtml = "<th data-flex='false' rowspan='$rowspan' colspan='$colspan' data-width='auto' class='text-center'>$thName</th>";
                else         $thHtml = "<th data-flex='true' rowspan='$rowspan' colspan='$colspan' data-type='number' data-width=$width class='text-center'>$thName</th>";

                $table .= $thHtml;
            }
            $table .= "</tr>";
        }
        $table .= "</thead>";

        /* Init table tbody. */
        $table .= "<tbody>";
        $rowCount = 0;

        $showAllTotal = isset($data->showAllTotal) && $data->showAllTotal;
        $users        = $this->loadModel('user')->getPairs('noletter,noempty,noclosed');
        $userFields   = $this->getPivotSetting('userFields', array('assignedTo', 'createdBy', 'openedBy', 'resolvedBy', 'closedBy', 'finishedBy', 'account', 'user'));

        for($i = 0; $i < count($data->array); $i ++)
        {
            $rowCount ++;

            if($showAllTotal && $rowCount == count($data->array)) continue;

            $line   = array_values($data->array[$i]);
            $table .= "<tr class='text-center'>";
            for($j = 0; $j < count($line); $j ++)
            {
                $cols    = isset($data->cols[0][$j]) ? $data->cols[0][$j] : array();
                $isGroup = !empty($data->cols[0][$j]->isGroup) ? $data->cols[0][$j]->isGroup : false;
                $rowspan = isset($configs[$i][$j]) ? $configs[$i][$j] : 1;
                $hidden  = (isset($configs[$i][$j]) && $configs[$i][$j]) ? false : (bool)$isGroup;

                $showOrigin = zget($showOrigins, $j, false);
                if($hasShowOrigin && !$isGroup && !$showOrigin)
                {
                    $rowspan = isset($configs[$i]) ? end($configs[$i]) : 1;
                    $hidden  = isset($configs[$i]) ? false : true;
                }

                $lineValue = $line[$j];
                if(is_numeric($lineValue)) $lineValue = round($lineValue, 2);

                if(isset($cols->name) && in_array($cols->name, $userFields)) $lineValue = isset($users[$lineValue]) ? $users[$lineValue] : $lineValue;

                if(!$hidden) $table .= "<td rowspan='$rowspan'>$lineValue</td>";
            }
            $table .= "</tr>";
        }

        if($showAllTotal && !empty($data->array))
        {
            $table .= "<tr class='text-center'>";
            $table .= "<td colspan='" . count($data->groups) . "'>{$this->lang->pivot->stepDesign->total}</td>";
            foreach(end($data->array) as $field => $total)
            {
                if(in_array($field, $data->groups)) continue;
                if(is_numeric($total)) $total = round($total, 2);
                $table .= "<td>$total</td>";
            }
            $table .= "</tr>";
        }

        $table .= "</tbody>";
        $table .= "</table></div>";

        return $table;
    }
}
