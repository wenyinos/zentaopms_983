<?php
$lang->my->mcpkeys = 'MCP Key';

$lang->my->menu->mcpkeys = 'MCP Key|my|mcpkeys';
$lang->my->menuOrder[65] = 'mcpkeys';

$lang->my->mcpIntro        = 'This key authenticates the zentao-mcp client as your ZenTao account (%s); actions performed through MCP are recorded under this account. Keep it private and never commit it to git.';
$lang->my->mcpAccount      = 'Account';
$lang->my->mcpSecret       = 'Personal Key';
$lang->my->mcpNoSecret     = 'No personal key yet. Once generated, write actions must be signed with this key and nobody else can act as your account.';
$lang->my->mcpSecretHint   = 'The key equals your account write permission. Store it securely (e.g. in the local zentao-mcp-py/.env).';
$lang->my->mcpGenerate     = 'Generate Key';
$lang->my->mcpGenFail      = 'Failed to generate the key. Please retry; if it persists, ask the administrator to check the server log.';
$lang->my->mcpReset        = 'Reset Key';
$lang->my->mcpResetConfirm = 'The old key will stop working immediately (machines using it must update .env). Reset anyway?';
$lang->my->mcpCopy         = 'Copy';
$lang->my->mcpCopyOk       = 'Copied to clipboard.';
$lang->my->mcpCopyManual   = 'Auto copy failed, please select the text and copy manually.';
$lang->my->mcpEnvTitle     = 'Client Config';
$lang->my->mcpEnvHint      = 'Paste into the local zentao-mcp-py/.env; set MCP_REPO_PATH to your git repository path.';
$lang->my->mcpStepsTitle   = 'Steps';
$lang->my->mcpStep1        = 'Copy the config above into the local zentao-mcp-py/.env (create the file if missing).';
$lang->my->mcpStep2        = 'Restart the MCP client process (IDE/editor host or Qoder, etc.) to apply the config.';
$lang->my->mcpStep3        = 'Run zentao_ping; pong means connected. Write actions will be attributed to "%s".';
$lang->my->mcpRulesTitle   = 'Security Rules';
$lang->my->mcpRulePersonal = 'Write actions carrying an actor must be signed with that account personal key; impersonation is rejected.';
$lang->my->mcpRuleRead     = 'Read actions must be signed with a personal key; the matched account determines the privileges used.';
$lang->my->mcpRuleNoShared = 'The server keeps no shared key: an account without a personal key cannot use MCP. Generate one on this page first.';
