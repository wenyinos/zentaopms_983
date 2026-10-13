<?php
$lang->my->mcpkeys = 'MCP密钥';

$lang->my->menu->mcpkeys = 'MCP密钥|my|mcpkeys';
$lang->my->menuOrder[65] = 'mcpkeys';

$lang->my->mcpIntro        = '该密钥用于 zentao-mcp 客户端接入，代表你的禅道账号（%s），通过 MCP 执行的操作都会记录在该账号名下。请妥善保管，勿提交到 git 或转发给无关人员。';
$lang->my->mcpAccount      = '账号';
$lang->my->mcpSecret       = '个人密钥';
$lang->my->mcpNoSecret     = '尚未生成个人密钥。生成后，写操作必须使用该密钥签名，其他机器将无法再冒用你的账号署名。';
$lang->my->mcpSecretHint   = '密钥等同于你的账号写权限，请通过安全渠道保存（如本机 zentao-mcp-py/.env）。';
$lang->my->mcpGenerate     = '生成密钥';
$lang->my->mcpReset        = '重置密钥';
$lang->my->mcpResetConfirm = '重置后旧密钥立即失效（使用旧密钥的机器需同步更新 .env），确定重置？';
$lang->my->mcpCopy         = '复制';
$lang->my->mcpCopyOk       = '已复制到剪贴板。';
$lang->my->mcpCopyManual   = '自动复制失败，请手动全选复制。';
$lang->my->mcpNoShared     = '服务端尚未配置 MCP_SECRET，请联系管理员检查 module/mcp/.env。';
$lang->my->mcpEnvTitle     = '客户端配置';
$lang->my->mcpEnvHint      = '粘贴到本机 zentao-mcp-py/.env；MCP_REPO_PATH 请改为你的 git 仓库路径。';
$lang->my->mcpStepsTitle   = '使用步骤';
$lang->my->mcpStep1        = '复制上方配置，覆盖本机 zentao-mcp-py/.env（无该文件则新建）。';
$lang->my->mcpStep2        = '重启 MCP 客户端进程（IDE/编辑器宿主或 Qoder 等），使配置生效。';
$lang->my->mcpStep3        = '调用 zentao_ping 返回 pong 即接入成功；写操作将署名“%s”。';
$lang->my->mcpRulesTitle   = '安全规则';
$lang->my->mcpRulePersonal = '带操作人（actor）的写操作必须使用该账号的个人密钥签名，无法再冒用他人账号署名；读操作不受影响。';
$lang->my->mcpRuleRead     = '读操作可使用个人密钥或团队共享密钥（MCP_SECRET）。';
$lang->my->mcpRuleStrictOn = '当前为严格模式（MCP_STRICT=1）：未生成密钥的账号不能通过共享密钥代为署名。';
$lang->my->mcpRuleStrictOff = '当前为过渡模式：未生成密钥的账号仍可用团队共享密钥代为署名，生成密钥后该账号立即受保护。';
