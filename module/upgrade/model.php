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

        switch($fromVersion)
        {
            case '9_8_3_1':
                /* 9.9.0 相对 9.8.3_1 无其他数据库结构变更。 */
                break;
        }

        $this->deletePatch();
        $this->clearModelCache();
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
