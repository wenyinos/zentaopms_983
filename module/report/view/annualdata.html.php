<?php
/**
 * The annualData view file of report module of ZenTaoPMS (WenYinOS community build).
 *
 * 年度总结（9.10.0 移植自 22.6）：基础数据 / 贡献与能力雷达 / 月度操作趋势 / 状态分布。
 * 图表用 eCharts 5.5.1 全量构建（不能用 common 构建，其不含 radar）。
 *
 * @copyright   Copyright 2009-2026 禅道软件（青岛）集团有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     report
 */
?>
<?php include '../../common/view/header.html.php';?>
<div id='mainMenu' class='clearfix'>
  <div class='btn-toolbar pull-left'>
    <form method='get' class='form-inline' id='annualForm'>
      <?php
      echo html::select('year',    $years, $year,        "class='form-control' onchange='this.form.submit()'");
      echo html::select('dept',    $depts, $dept,        "class='form-control' onchange='this.form.submit()'");
      echo html::select('account', $users, $account,     "class='form-control' onchange='this.form.submit()'");
      ?>
    </form>
  </div>
</div>

<div id='mainContent'>
  <div class='row'>
    <div class='col-md-6'>
      <div class='panel'>
        <div class='panel-heading'><strong><?php echo $lang->report->annualData->basic;?></strong></div>
        <div class='panel-body'>
          <table class='table table-data table-bordered'>
            <tr>
              <th class='w-120px'><?php echo $lang->report->annualData->logins;?></th><td><?php echo (int)$data['logins'];?></td>
              <th class='w-120px'><?php echo $lang->report->annualData->actions;?></th><td><?php echo (int)$data['actions'];?></td>
            </tr>
            <tr>
              <th><?php echo $lang->report->annualData->todoUndone;?></th><td><?php echo (int)$data['todos']->undone;?></td>
              <th><?php echo $lang->report->annualData->todoDone;?></th><td><?php echo (int)$data['todos']->done;?></td>
            </tr>
            <tr>
              <th><?php echo $lang->report->annualData->efforts;?></th><td><?php echo $data['efforts']->consumed;?></td>
              <th><?php echo $lang->report->annualData->effortCount;?></th><td><?php echo (int)$data['efforts']->count;?></td>
            </tr>
            <tr>
              <th><?php echo $lang->report->annualData->contributionCount;?></th><td colspan='3'><strong class='text-primary'><?php echo (int)$data['contributionCount'];?></strong></td>
            </tr>
          </table>
        </div>
      </div>

      <div class='panel'>
        <div class='panel-heading'><strong><?php echo $lang->report->annualData->statusBlock;?></strong> <span class='text-muted small'><?php echo $lang->report->annualData->statusHint;?></span></div>
        <div class='panel-body'>
          <table class='table table-data table-bordered'>
            <thead><tr><th></th><th><?php echo $lang->report->annualData->status;?></th><th class='w-80px'><?php echo $lang->report->value;?></th></tr></thead>
            <tbody>
              <?php $hasStatus = false;?>
              <?php foreach($objectStat as $objectType => $stat):?>
              <?php foreach($stat['statusStat'] as $status => $count):?>
              <?php $hasStatus = true;?>
              <tr>
                <td><?php echo $lang->report->annualData->objects[$objectType];?></td>
                <?php $langModule = $objectType == 'case' ? 'testcase' : $objectType; /* 用例的语言对象是 testcase */ ?>
                <td><?php echo zget($lang->$langModule->statusList, $status, $status);?></td>
                <td><?php echo (int)$count;?></td>
              </tr>
              <?php endforeach;?>
              <?php endforeach;?>
              <?php if(!$hasStatus):?><tr><td colspan='3' class='text-center text-muted'><?php echo $lang->report->annualData->noData;?></td></tr><?php endif;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class='col-md-6'>
      <div class='panel'>
        <div class='panel-heading'><strong><?php echo $lang->report->annualData->contributionBlock;?></strong></div>
        <div class='panel-body'>
          <div id='annualRadar' style='height:300px'></div>
        </div>
      </div>
      <div class='panel'>
        <div class='panel-heading'><strong><?php echo $lang->report->annualData->trendBlock;?></strong> <span class='text-muted small'><?php echo $lang->report->annualData->trendHint;?></span></div>
        <div class='panel-body'>
          <div id='annualTrend' style='height:300px'></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
js::set('annualRadarLabels', array_values($lang->report->annualData->radarTypes));
js::set('annualRadarData',   array_values($radar));
js::set('annualRadarMax',    max(1, max(array_values($radar))) * 1.2);
js::set('annualMonths',      array_values($months));
js::set('annualTrendNames',  array_values($lang->report->annualData->objects));
$trendData = array();
foreach($objectStat as $objectType => $stat) $trendData[] = array_values($stat['monthlyStat']);
js::set('annualTrendData',   $trendData);
?>
<?php include '../../common/view/footer.html.php';?>
