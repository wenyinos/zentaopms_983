<?php
/**
 * The MCP key page view of my module.
 */
?>
<?php include $this->app->getModuleRoot() . 'common/view/header.html.php';?>
<div id='titlebar'>
  <div class='heading'><i class='icon-key'></i> <?php echo $lang->my->mcpkeys;?></div>
</div>
<div class='container mw-800px'>
  <?php if(!empty($error)):?>
  <div class='alert alert-danger'><?php echo $error;?></div>
  <?php endif;?>
  <div class='alert alert-info'><?php printf($lang->my->mcpIntro, $account);?></div>
  <form class='form-condensed' method='post'>
    <input type='hidden' name='generate' value='1' />
    <table class='table table-form'>
      <tr>
        <th class='text-right w-120px'><?php echo $lang->my->mcpAccount;?></th>
        <td><?php echo $account;?>&nbsp;<span class='text-muted'>(<?php echo $realname;?>)</span></td>
      </tr>
      <tr>
        <th class='text-right'><?php echo $lang->my->mcpSecret;?></th>
        <td>
          <?php if($secret):?>
          <textarea id='mcpSecret' class='form-control' rows='1' readonly='readonly' onclick='this.select()'><?php echo htmlspecialchars($secret, ENT_QUOTES);?></textarea>
          <div class='text-muted'><?php echo $lang->my->mcpSecretHint;?></div>
          <div class='m-t-10px'>
            <button type='button' class='btn' onclick="copyMCPBox('mcpSecret', '<?php echo $lang->my->mcpCopyOk;?>')"><?php echo $lang->my->mcpCopy;?></button>
            <button type='submit' class='btn btn-primary' onclick="return confirm('<?php echo $lang->my->mcpResetConfirm;?>')"><?php echo $lang->my->mcpReset;?></button>
          </div>
          <?php else:?>
          <div class='text-muted'><?php echo $lang->my->mcpNoSecret;?></div>
          <button type='submit' class='btn btn-primary'><?php echo $lang->my->mcpGenerate;?></button>
          <?php endif;?>
        </td>
      </tr>
      <?php if($secret):?>
      <tr>
        <th class='text-right'><?php echo $lang->my->mcpEnvTitle;?></th>
        <td>
          <textarea id='mcpEnv' class='form-control' rows='7' readonly='readonly' onclick='this.select()'><?php echo htmlspecialchars($envText, ENT_QUOTES);?></textarea>
          <div class='text-muted'><?php echo $lang->my->mcpEnvHint;?></div>
          <button type='button' class='btn' onclick="copyMCPBox('mcpEnv', '<?php echo $lang->my->mcpCopyOk;?>')"><?php echo $lang->my->mcpCopy;?></button>
        </td>
      </tr>
      <?php endif;?>
      <tr>
        <th class='text-right'><?php echo $lang->my->mcpStepsTitle;?></th>
        <td>
          <ol>
            <li><?php echo $lang->my->mcpStep1;?></li>
            <li><?php echo $lang->my->mcpStep2;?></li>
            <li><?php echo sprintf($lang->my->mcpStep3, $account);?></li>
          </ol>
        </td>
      </tr>
      <tr>
        <th class='text-right'><?php echo $lang->my->mcpRulesTitle;?></th>
        <td>
          <ul class='m-b-0'>
            <li><?php echo $lang->my->mcpRulePersonal;?></li>
            <li><?php echo $lang->my->mcpRuleRead;?></li>
            <li><?php echo $lang->my->mcpRuleNoShared;?></li>
          </ul>
        </td>
      </tr>
    </table>
  </form>
</div>
<script>
function copyMCPBox(boxID, okTip)
{
    var box = document.getElementById(boxID);
    if(!box) return;
    box.focus();
    box.select();
    var done = false;
    try { done = document.execCommand('copy'); } catch(e) { done = false; }
    alert(done ? okTip : '<?php echo $lang->my->mcpCopyManual;?>');
}
</script>
<?php include $this->app->getModuleRoot() . 'common/view/footer.html.php';?>
