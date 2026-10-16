<?php
/**
 * The track view file of project module of ZenTaoPMS (WenYinOS community build).
 *
 * 需求跟踪矩阵（移植自 22.6 projectstory|track 的思路）：
 * 每行一个需求，列出其所属迭代与关联的任务/Bug/用例。
 * 与 22.6 的差异：本仓无需求三级分级与 design/commit 列（依赖我们没有的表），
 * 故用单层表头 + 单元格列表（超过 3 条折叠为 +N），不做 rowspan 两级表头。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     project
 */
?>
<?php include '../../common/view/header.html.php';?>
<?php
/* 单元格渲染：任务/Bug/用例 的小列表（点击进详情，超过 3 条折叠）。 */
$langMap = array('task' => 'task', 'bug' => 'bug', 'case' => 'testcase');
$linkMap = array('task' => 'task', 'bug' => 'bug', 'case' => 'testcase');
$renderCell = function($items, $objectType) use ($lang, $langMap, $linkMap)
{
    if(empty($items)) return '<span class="text-muted">-</span>';

    $langObj = $lang->{$langMap[$objectType]};
    $lines   = array();
    foreach(array_slice($items, 0, 3) as $item)
    {
        $title   = $objectType == 'task' ? $item->name : $item->title;
        $link    = $this->createLink($linkMap[$objectType], 'view', $objectType . 'ID=' . $item->id, '', true);
        $lines[] = html::a($link, '#' . $item->id . ' ' . helper::substr($title, 16), '', "class='iframe' title='" . htmlspecialchars((string)$title) . "'")
                 . ' <span class="text-muted small">' . zget($langObj->statusList, $item->status, $item->status) . '</span>';
    }
    if(count($items) > 3) $lines[] = '<span class="text-muted">+' . (count($items) - 3) . '</span>';
    return join('<br />', $lines);
};
$vars = "projectID={$project->id}&orderBy=%s&type=$type&param=$param&recTotal={$pager->recTotal}&recPerPage={$pager->recPerPage}";
?>
<div id='mainMenu' class='clearfix'>
  <div class='btn-toolbar pull-left'>
    <?php if(common::hasPriv('project', 'story')) echo html::a($this->createLink('project', 'story', "projectID=$project->id"), "<span class='text'>{$lang->story->allStories}</span>", '', "class='btn btn-link'");?>
    <span class='btn btn-link btn-active-text'><span class='text'><?php echo $lang->project->track;?></span> <span class='label label-light label-badge'><?php echo $pager->recTotal;?></span></span>
  </div>
  <div class='btn-toolbar pull-right'>
    <div class='btn-group'>
      <button type='button' class='btn btn-link' data-toggle='dropdown'><?php echo zget($lang->project->orderList, $orderBy, $lang->project->orderList['pri_asc']);?> <span class='caret'></span></button>
      <ul class='dropdown-menu pull-right'>
        <?php foreach($lang->project->orderList as $key => $value):?>
        <li<?php echo $key == $orderBy ? " class='active'" : '';?>><?php echo html::a($this->createLink('project', 'track', "projectID=$project->id&orderBy=$key&type=$type&param=$param"), $value);?></li>
        <?php endforeach;?>
      </ul>
    </div>
  </div>
</div>

<div id='mainContent'>
  <div class='main-table' data-ride='table'>
    <table class='table has-sort-head' id='storyTrack'>
      <thead>
        <tr>
          <th class='c-id w-60px'><?php echo common::printOrderLink('id', $orderBy, $vars, $lang->idAB);?></th>
          <th class='c-name'><?php echo common::printOrderLink('title', $orderBy, $vars, $lang->story->title);?></th>
          <th class='w-60px'><?php echo common::printOrderLink('pri', $orderBy, $vars, $lang->priAB);?></th>
          <th class='w-80px'><?php echo common::printOrderLink('status', $orderBy, $vars, $lang->statusAB);?></th>
          <th class='w-90px'><?php echo common::printOrderLink('stage', $orderBy, $vars, $lang->story->stageAB);?></th>
          <th class='w-140px'><?php echo $lang->project->trackIteration;?></th>
          <th class='w-200px'><?php echo $lang->story->taskCount;?></th>
          <th class='w-200px'><?php echo $lang->story->bugCount;?></th>
          <th class='w-200px'><?php echo $lang->story->caseCount;?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($stories as $storyID => $story):?>
        <?php $storyProjects = zget($track['projects'], $storyID, array());?>
        <tr>
          <td><?php echo $storyID;?></td>
          <td class='text-left'>
            <?php echo html::a($this->createLink('story', 'view', "storyID=$storyID", '', true), $story->title, '', "class='iframe' title='" . htmlspecialchars((string)$story->title) . "'");?>
            <?php if(!empty($story->sourceNote)):?><br /><span class='text-muted small'><?php echo helper::substr($story->sourceNote, 30);?></span><?php endif;?>
          </td>
          <td><span class='label-pri label-pri-<?php echo $story->pri;?>'><?php echo zget($lang->story->priList, $story->pri);?></span></td>
          <td><?php echo zget($lang->story->statusList, $story->status, $story->status);?></td>
          <td><?php echo zget($lang->story->stageList, $story->stage, $story->stage);?></td>
          <td class='text-left'>
            <?php
            if(empty($storyProjects))
            {
                echo '<span class="text-muted">-</span>';
            }
            else
            {
                $projectNames = array();
                foreach($storyProjects as $storyProject) $projectNames[] = htmlspecialchars((string)$storyProject->name);
                echo join('<br />', $projectNames);
            }
            ?>
          </td>
          <td class='text-left'><?php echo $renderCell(zget($track['tasks'],  $storyID, array()), 'task');?></td>
          <td class='text-left'><?php echo $renderCell(zget($track['bugs'],   $storyID, array()), 'bug');?></td>
          <td class='text-left'><?php echo $renderCell(zget($track['cases'],  $storyID, array()), 'case');?></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
    <?php if(empty($stories)):?>
    <div class='table-empty-tip'><p><span class='text-muted'><?php echo $lang->story->noStory;?></span></p></div>
    <?php endif;?>
    <div class='table-footer'>
      <?php if(!empty($stories)) $pager->show('right', 'pagerjs');?>
    </div>
  </div>
</div>
<?php include '../../common/view/footer.html.php';?>
