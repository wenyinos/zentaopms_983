<?php
/**
 * The model file of upgrade module of ZenTaoPMS (WenYinOS community build).
 *
 * 本升级模块只服务于本社区版（WenYinOS ZenTaoPMS）自身的版本序列：
 * 支持从 9.8.3_1（首个社区定制版）及后续自研版本升级，官方禅道版本一律拒绝。
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @package     upgrade
 */
class upgradeModel extends model
{
    static $errors = array();

    /**
     * The execute method. According to the $fromVersion call related methods.
     *
     * 9.9.0 相比 9.8.3_1 只移植了界面（骨架/模板/JS/CSS），业务数据结构未变，无需执行 SQL。
     * 后续版本若有数据库变更，在此按版本号追加分支即可（如 case '9_9_0': ...）。
     *
     * @param  string $fromVersion
     * @access public
     * @return void
     */
    public function execute($fromVersion)
    {
        /* 补充 9.9.0 新增的文档收藏字段（幂等：旧库用旧版 zentao.sql 安装时缺 collector 列）。 */
        $this->addCollectorColumns();

        /* 9.10.0 新增：错误日志两张表 + 清理定时任务（幂等，任意来源版本都会补齐）。 */
        $this->addErrorLogTables();
        $this->addErrorLogCron();

        /* 9.10.0 新增：文档增强（文档排序列、文档库类型列、浏览动作表、模板库种子）。 */
        $this->addDocOrderColumn();
        $this->addDoclibTypeColumn();
        $this->createDocActionTable();
        $this->seedTemplateLib();

        switch($fromVersion)
        {
            case '9_8_3_1':
            case '9_9_0':
            case '9_9_1':
                /* 本版新增的库结构由上方幂等方法按需补齐（支持跨版本跳跃升级，见计划 §1.4）。 */
                break;
        }

        $this->deletePatch();
        $this->clearModelCache();
    }

    /**
     * Create the errorlog tables (idempotent).
     *
     * 9.10.0 新增：错误本体 zt_errorlog + 请求上下文 zt_errorlogreq。
     * 用 information_schema 探测 + CREATE TABLE IF NOT EXISTS 双保险；可重复执行。
     * 注意：本仓库允许跨版本跳跃升级（缺省来源版本不执行 case 分支），
     * 故 DDL 一律放在 execute() 顶部按需补齐，见计划 §1.4。
     *
     * @access public
     * @return void
     */
    public function addErrorLogTables()
    {
        $tables = array(
            'errorlog' => "CREATE TABLE IF NOT EXISTS `%s` (
  `md5` char(32) NOT NULL DEFAULT '',
  `file` varchar(255) NOT NULL DEFAULT '',
  `line` int unsigned NOT NULL DEFAULT '0',
  `level` smallint unsigned NOT NULL DEFAULT '0',
  `message` text,
  `trace` text,
  PRIMARY KEY (`md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8",
            'errorlogreq' => "CREATE TABLE IF NOT EXISTS `%s` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `requestID` varchar(64) NOT NULL DEFAULT '',
  `md5` char(32) NOT NULL DEFAULT '',
  `module` varchar(30) NOT NULL DEFAULT '',
  `method` varchar(100) NOT NULL DEFAULT '',
  `account` varchar(30) NOT NULL DEFAULT '',
  `url` varchar(255) NOT NULL DEFAULT '',
  `createdDate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_requestID_md5` (`requestID`,`md5`),
  KEY `idx_requestID` (`requestID`),
  KEY `idx_md5` (`md5`),
  KEY `idx_createdDate` (`createdDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        );

        foreach($tables as $suffix => $sql)
        {
            $tableName = $this->config->db->prefix . $suffix;
            $exists = $this->dao->select('COUNT(*) AS count')->from('information_schema.tables')
                ->where('table_schema')->eq($this->config->db->name)
                ->andWhere('table_name')->eq($tableName)
                ->fetch('count');
            if($exists) continue;

            try
            {
                $this->dbh->exec(sprintf($sql, $tableName));
            }
            catch (PDOException $e)
            {
                self::$errors[] = $e->getMessage() . "<p>The sql is: CREATE TABLE {$tableName}</p>";
            }
        }
    }

    /**
     * Add the doc order column (idempotent).
     *
     * 9.10.0 文档增强：zt_doc.order 用于文档列表拖拽排序。`order` 是 MySQL 保留字，
     * SQL 里一律加反引号。可重复执行。
     *
     * @access public
     * @return void
     */
    public function addDocOrderColumn()
    {
        $tableName = trim(TABLE_DOC, '`');
        $exists = $this->dao->select('COUNT(*) AS count')->from('information_schema.columns')
            ->where('table_schema')->eq($this->config->db->name)
            ->andWhere('table_name')->eq($tableName)
            ->andWhere('column_name')->eq('order')
            ->fetch('count');
        if($exists) return;

        try
        {
            $this->dbh->exec("ALTER TABLE `{$tableName}` ADD COLUMN `order` int unsigned NOT NULL DEFAULT 0");
            $this->dbh->exec("UPDATE `{$tableName}` SET `order` = `id` WHERE `order` = 0");
        }
        catch (PDOException $e)
        {
            self::$errors[] = $e->getMessage() . '<p>The sql is: ALTER TABLE zt_doc ADD order</p>';
        }
    }

    /**
     * Add the doclib type column (idempotent).
     *
     * 9.10.0 文档增强：zt_doclib.type 区分普通库与「文档模板」范围库。
     * 默认空串，既有库行为不变。可重复执行。
     *
     * @access public
     * @return void
     */
    public function addDoclibTypeColumn()
    {
        $tableName = trim(TABLE_DOCLIB, '`');
        $exists = $this->dao->select('COUNT(*) AS count')->from('information_schema.columns')
            ->where('table_schema')->eq($this->config->db->name)
            ->andWhere('table_name')->eq($tableName)
            ->andWhere('column_name')->eq('type')
            ->fetch('count');
        if($exists) return;

        try
        {
            $this->dbh->exec("ALTER TABLE `{$tableName}` ADD COLUMN `type` varchar(30) NOT NULL DEFAULT ''");
        }
        catch (PDOException $e)
        {
            self::$errors[] = $e->getMessage() . '<p>The sql is: ALTER TABLE zt_doclib ADD type</p>';
        }
    }

    /**
     * Create the doc action table (idempotent).
     *
     * 9.10.0 文档增强：zt_docaction 记录浏览/收藏动作，供「最近浏览」取数。
     *
     * @access public
     * @return void
     */
    public function createDocActionTable()
    {
        $tableName = $this->config->db->prefix . 'docaction';
        $exists = $this->dao->select('COUNT(*) AS count')->from('information_schema.tables')
            ->where('table_schema')->eq($this->config->db->name)
            ->andWhere('table_name')->eq($tableName)
            ->fetch('count');
        if($exists) return;

        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `doc` int unsigned NOT NULL DEFAULT 0,
  `action` varchar(80) NOT NULL DEFAULT '',
  `actor` varchar(30) NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doc` (`doc`),
  KEY `actor` (`actor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        try
        {
            $this->dbh->exec($sql);
        }
        catch (PDOException $e)
        {
            self::$errors[] = $e->getMessage() . '<p>The sql is: CREATE TABLE ' . $tableName . '</p>';
        }
    }

    /**
     * Seed the doc template lib (idempotent).
     *
     * 9.10.0 文档增强：模板必须挂在 doclib 上，故建一个 type='template' 的范围库。
     * 已有同类库时跳过，保证重复升级不产生第二行。
     *
     * @access public
     * @return void
     */
    public function seedTemplateLib()
    {
        $exists = $this->dao->select('COUNT(*) AS count')->from(TABLE_DOCLIB)
            ->where('type')->eq('template')
            ->fetch('count');
        if($exists) return;

        $lib = new stdclass();
        $lib->product   = 0;
        $lib->project   = 0;
        $lib->name      = '文档模板';
        $lib->acl       = 'open';
        $lib->groups    = '';
        $lib->users     = '';
        $lib->main      = '1';
        $lib->collector = '';
        $lib->order     = 0;
        $lib->deleted   = '0';
        $lib->type      = 'template';

        try
        {
            $this->dao->insert(TABLE_DOCLIB)->data($lib)->exec();
        }
        catch (PDOException $e)
        {
            self::$errors[] = $e->getMessage() . '<p>The sql is: insert template doclib</p>';
        }
    }

    /**
     * Register the errorlog cleanup cron (idempotent).
     *
     * lastTime 必须是 '0000-00-00 00:00:00'：cron 模块的 checkChange() 以此为"新任务"判据，
     * 否则运行中的常驻进程不会重载任务列表（见 module/cron/model.php）。
     *
     * @access public
     * @return void
     */
    public function addErrorLogCron()
    {
        $command = 'moduleName=errorlog&methodName=deleteLog';

        $exists = $this->dao->select('COUNT(*) AS count')->from(TABLE_CRON)
            ->where('command')->eq($command)
            ->fetch('count');
        if($exists) return;

        $cron = new stdclass();
        $cron->m         = '*/5';
        $cron->h         = '*';
        $cron->dom       = '*';
        $cron->mon       = '*';
        $cron->dow       = '*';
        $cron->command   = $command;
        $cron->remark    = '删除过期错误日志';
        $cron->type      = 'zentao';
        $cron->buildin   = 1;
        $cron->status    = 'normal';
        $cron->lastTime  = '0000-00-00 00:00:00';

        try
        {
            $this->dao->insert(TABLE_CRON)->data($cron)->exec();
        }
        catch (PDOException $e)
        {
            self::$errors[] = $e->getMessage() . '<p>The sql is: insert errorlog cron</p>';
        }
    }

    /**
     * Add the collector columns required by the 10.1 doc-collect feature.
     *
     * doc/doclib/module 三表新增 collector 列（10.1 收藏功能的存储位置）。
     * 11.3 血统的库（本社区生产库）已含该列，检测到即跳过，可重复执行。
     *
     * @access public
     * @return void
     */
    public function addCollectorColumns()
    {
        $tables = array(TABLE_DOC, TABLE_DOCLIB, TABLE_MODULE);
        foreach($tables as $table)
        {
            $tableName = trim($table, '`');
            $exists = $this->dao->select('COUNT(*) AS count')->from('information_schema.columns')
                ->where('table_schema')->eq($this->config->db->name)
                ->andWhere('table_name')->eq($tableName)
                ->andWhere('column_name')->eq('collector')
                ->fetch('count');
            if($exists) continue;

            try
            {
                $this->dbh->exec("ALTER TABLE `{$tableName}` ADD COLUMN `collector` TEXT NOT NULL");
            }
            catch (PDOException $e)
            {
                self::$errors[] = $e->getMessage() . "<p>The sql is: ALTER TABLE `{$tableName}` ADD COLUMN `collector` TEXT NOT NULL</p>";
            }
        }
    }

    /**
     * Check whether the installed version belongs to the WenYinOS build series.
     *
     * 官方版本（9.8.3 及以下、10.x 等）不在升级支持范围：本版本业务核心取自
     * 9.8.3、界面取自 10.1，与任何单个官方发布版都不完全一致，直接升级可能损坏数据。
     *
     * 允许的两条自研序列：
     *   9.8.3_<n>        首个社区定制版起的定制序列
     *   9.9.x 与 9.10.x+ 自研版本号（中段为 9 或两位数及以上）
     *
     * 拒绝的关键：官方 9.x 的中段是 0-8（如 9.8.3），官方 10.x 写作 10.x 而不是 9.10.x，
     * 所以 `9.` 开头且中段为 `9` 或 `>= 10` 即必然是自研版本。
     *
     * @param  string $version
     * @access public
     * @return bool
     */
    public function isWenyinVersion($version)
    {
        if(preg_match('/^9\.8\.3_\d+$/', (string)$version))            return true;    // WenYinOS 定制序列（9.8.3_1 起）
        if(preg_match('/^9\.(?:9|[1-9]\d+)\.\d+$/', (string)$version)) return true;    // 9.9.x 与 9.10.x 起的自研序列
        return false;
    }

    /**
     * Refuse to upgrade when the installed version is not a WenYinOS build.
     *
     * 版本优先取 $config->installedVersion（web 入口会预先赋值）；CLI 等入口未赋值时
     * 自行从 setting 读取，保证任何调用方式下守卫都生效。
     *
     * @access public
     * @return void
     */
    public function checkSource()
    {
        $installedVersion = isset($this->config->installedVersion) ? $this->config->installedVersion : '';
        if(empty($installedVersion)) $installedVersion = $this->loadModel('setting')->getVersion();
        if($this->isWenyinVersion($installedVersion)) return true;
        $this->printUnsupportedSource($installedVersion);
    }

    /**
     * Print the refusal page for unsupported (official) ZenTao sources and exit.
     *
     * @param  string $installedVersion
     * @access public
     * @return void
     */
    public function printUnsupportedSource($installedVersion)
    {
        $installedVersion = htmlspecialchars((string)$installedVersion, ENT_QUOTES, 'utf-8');
        $html = <<<EOT
<!DOCTYPE html>
<html lang='zh-cn'>
<head>
<meta charset='utf-8'>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<title>无法升级 · WenYinOS ZenTaoPMS</title>
<style>
body{font-family:-apple-system,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f1f1f1;color:#333;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
.box{background:#fff;border-radius:6px;box-shadow:0 2px 12px rgba(0,0,0,.08);max-width:640px;margin:20px;padding:32px 36px;}
.box h3{margin:0 0 16px;color:#d9534f;font-size:20px;}
.box p{margin:10px 0;line-height:1.7;font-size:14px;}
.box .tip{color:#777;}
.box a{display:inline-block;margin-top:14px;color:#6f42c1;text-decoration:none;}
.box a:hover{text-decoration:underline;}
</style>
</head>
<body>
<div class='box'>
  <h3>无法升级</h3>
  <p>检测到当前数据库版本为 <strong>{$installedVersion}</strong>，它不属于本社区版（WenYinOS ZenTaoPMS）支持的升级来源，升级已被拒绝。</p>
  <p>本项目仅支持从 <strong>9.8.3_1</strong>（首个社区定制版）及后续自研版本（<strong>9.9.x</strong>、<strong>9.10.x 及以上</strong>）升级。官方禅道版本（9.8.3 及以下、10.x 等）不在兼容范围内——本版本业务核心取自 9.8.3、界面取自 10.1，与任何单个官方发布版都不完全一致，直接升级可能造成数据损坏。</p>
  <p class='tip'>如果你正在使用官方禅道，请先通过官方升级流程升级到官方最新版，或联系维护者评估迁移方案。</p>
  <a href='index.php'>返回首页</a>
</div>
</body>
</html>
EOT;
        die($html);
    }

    /**
     * Clear the merged-model cache under tmp/model.
     *
     * 部署覆盖时文件时间戳可能早于旧缓存，框架的 mtime 判断会把 9.8.3_1 时代的
     * 合并缓存继续当作有效代码使用；升级时主动清掉，强制重新合并。
     *
     * @access public
     * @return void
     */
    public function clearModelCache()
    {
        $modelCacheRoot = $this->app->getTmpRoot() . 'model' . DS;
        if(!is_dir($modelCacheRoot)) return;

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelCacheRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file)
        {
            if($file->isFile() and strtolower($file->getExtension()) == 'php') unlink($file->getPathname());
        }
    }

    /**
     * Check safe file.
     *
     * @access public
     * @return string|false
     */
    public function checkSafeFile()
    {
        if($this->app->getModuleName() == 'upgrade' and $this->app->getMethodName() == 'ajaxupdatefile') return false;
        $statusFile = $this->app->getAppRoot() . 'www' . DIRECTORY_SEPARATOR . 'ok.txt';
        return (!file_exists($statusFile) or (time() - filemtime($statusFile)) > 3600) ? $statusFile : false;
    }

    /**
     * Delete patch extensions.
     *
     * @access public
     * @return void
     */
    public function deletePatch()
    {
        $this->dao->delete()->from(TABLE_EXTENSION)->where('type')->eq('patch')->exec();
        $this->dao->delete()->from(TABLE_EXTENSION)->where('code')->in('zentaopatch,patch')->exec();
    }

    /**
     * Judge any error occers.
     *
     * @access public
     * @return bool
     */
    public function isError()
    {
        return !empty(self::$errors);
    }

    /**
     * Get errors during the upgrading.
     *
     * @access public
     * @return array
     */
    public function getError()
    {
        $errors = self::$errors;
        self::$errors = array();
        return $errors;
    }
}
