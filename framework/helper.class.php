<?php
/**
 * ZenTaoPHP的helper类。
 * The helper class file of ZenTaoPHP framework.
 *
 * The author disclaims copyright to this source code. In place of
 * a legal notice, here is a blessing:
 *
 *  May you do good and not evil.
 *  May you find forgiveness for yourself and forgive others.
 *  May you share freely, never taking more than you give.
 */

/**
 * 该类实现了一些常用的方法
 * The helper class, contains the tool functions.
 *
 * @package framework
 */
include dirname(__FILE__) . '/base/helper.class.php';
class helper extends baseHelper
{
    public static function getViewType($source = false)
    {
        global $config, $app;
        if($config->requestType != 'GET')
        {
            $pathInfo = $app->getPathInfo();
            if(!empty($pathInfo))
            {
                $dotPos = strrpos($pathInfo, '.');
                if($dotPos)
                {
                    $viewType = substr($pathInfo, $dotPos + 1);
                }
                else
                {
                    $config->default->view = $config->default->view == 'mhtml' ? 'html' : $config->default->view;
                }
            }
        }
        elseif($config->requestType == 'GET')
        {
            if(isset($_GET[$config->viewVar]))
            {
                $viewType = $_GET[$config->viewVar];
            }
            else
            {
                /* Set default view when url has not module name. such as only domain. */
                $config->default->view = ($config->default->view == 'mhtml' and isset($_GET[$config->moduleVar])) ? 'html' : $config->default->view;
            }
        }
        if($source and isset($viewType)) return $viewType;

        if(isset($viewType) and strpos($config->views, ',' . $viewType . ',') === false) $viewType = $config->default->view;
        return isset($viewType) ? $viewType : $config->default->view;
    }

    /**
     * Encode json for $.parseJSON
     *
     * @param  array  $data
     * @param  int    $options
     * @static
     * @access public
     * @return string
     */
    static public function jsonEncode4Parse($data, $options = 0)
    {
        $json = json_encode($data);
        if($options) $json = str_replace(array("'", '"'), array('\u0027', '\u0022'), $json);

        $escapers     = array("\\",  "/",   "\"", "'", "\n",  "\r",  "\t", "\x08", "\x0c", "\\\\u");
        $replacements = array("\\\\", "\\/", "\\\"", "\'", "\\n", "\\r", "\\t",  "\\f",  "\\b", "\\u");
        return str_replace($escapers, $replacements, $json);
    }

    /**
     * Convert encoding.
     *
     * @param  string $string
     * @param  string $fromEncoding
     * @param  string $toEncoding
     * @static
     * @access public
     * @return string
     */
    static public function convertEncoding($string, $fromEncoding, $toEncoding = 'utf-8')
    {
        $toEncoding = str_replace('utf8', 'utf-8', $toEncoding);
        if(function_exists('mb_convert_encoding'))
        {
            /* Remove like utf-8//TRANSLIT. */
            $position = strpos($toEncoding, '//');
            if($position !== false) $toEncoding = substr($toEncoding, 0, $position);

            /* Check string encoding. */
            $encoding = strtolower(mb_detect_encoding($string, array('ASCII','UTF-8','GB2312','GBK','BIG5')));
            if($encoding == $toEncoding) return $string;
            return mb_convert_encoding($string, $toEncoding, $encoding);
        }
        elseif(function_exists('iconv'))
        {
            if($fromEncoding == $toEncoding) return $string;
            $convertString = @iconv($fromEncoding, $toEncoding, $string);
            /* iconv error then return original. */
            if(!$convertString) return $string;
            return $convertString;
        }

        return $string;
    }

    /**
     * Calculate two working days.
     *
     * @param string $begin
     * @param string $end
     *
     * @return bool|float
     */
    static public function workDays($begin, $end)
    {
        $begin = strtotime($begin);
        $end   = strtotime($end);
        if($end < $begin) return false;

        $double = floor(($end - $begin) / (7 * 24 * 3600));
        $begin  = date('w', $begin);
        $end    = date('w', $end);
        $end    = $begin > $end ? $end + 5 : $end;
        return $double * 5 + $end - $begin;
    }

    /**
     * Send a cookie.
     *
     * 对齐禅道 22.6 的 helper::setcookie（移植 22.6 模块时不必逐个改写 setcookie 调用）。
     * 相比 22.6 去掉两条本仓不存在的分支：测试运行模式（本仓无 isRunMode）与
     * RoadRunner worker（本仓无 zand 运行时）。$config->cookieSecure 本仓未定义，取 false 兜底。
     *
     * @param  string          $name
     * @param  string|int|bool $value
     * @param  int|null        $expire
     * @param  string|null     $path
     * @param  string          $domain
     * @param  bool|null       $secure
     * @param  bool            $httponly
     * @static
     * @access public
     * @return bool
     */
    public static function setcookie($name, $value = '', $expire = null, $path = null, $domain = '', $secure = null, $httponly = true)
    {
        global $config;
        if($expire === null) $expire = $config->cookieLife;
        if($path   === null) $path   = $config->webRoot;
        if($secure === null) $secure = isset($config->cookieSecure) ? $config->cookieSecure : false;

        $value          = (string)$value;
        $_COOKIE[$name] = $value;
        return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * Verify that the system has opened on the feature.
     *
     * 本仓不移植 22.6 的 $config->featureGroup（那是一份商业版功能分组清单）；
     * 改为"默认全部开启、由 $config->disabledFeatures 显式关闭"的简化语义，
     * 使移植自 22.6 的代码不必逐处改写 hasFeature() 调用。
     *
     * @param  string $feature
     * @static
     * @access public
     * @return bool
     */
    public static function hasFeature($feature)
    {
        global $config;
        $disabled = isset($config->disabledFeatures) ? (string)$config->disabledFeatures : '';
        if($disabled === '') return true;
        return strpos(",{$disabled},", ",{$feature},") === false;
    }

    /**
     * Returns the values from a single column of the input array.
     *
     * 对齐 22.6：PHP >= 7 直接使用系统 array_column（本仓运行于 PHP 8.x）。
     *
     * @param  array      $input
     * @param  string|int $columnKey
     * @param  string|int $indexKey
     * @static
     * @access public
     * @return array
     */
    public static function arrayColumn($input, $columnKey, $indexKey = null)
    {
        if(!is_array($input)) return array();
        return array_column($input, $columnKey, $indexKey);
    }

    /**
     * Format hours, strip the trailing zeros.
     *
     * @param  float|string $hours
     * @param  int          $decimals
     * @param  string       $characters
     * @static
     * @access public
     * @return string
     */
    public static function formatHours($hours = '', $decimals = 2, $characters = '.')
    {
        return rtrim(rtrim(number_format((float)$hours, $decimals, $characters, ''), '0'), $characters);
    }

    /**
     * Whether current request is an API request.
     *
     * 本仓的 api 入口已在 nginx 安全基线中封禁，此处仅保留 RUN_MODE 判定，
     * 供移植代码调用时不报错。
     *
     * @static
     * @access public
     * @return bool
     */
    public static function isApiRequest()
    {
        return defined('RUN_MODE') and RUN_MODE == 'api';
    }

    /**
     * pathinfo for multi-byte filenames.
     *
     * Aligns with ZenTao 22.6 helper::mbPathinfo.
     *
     * @param  string $filepath
     * @static
     * @access public
     * @return array
     */
    public static function mbPathinfo($filepath)
    {
        $ret = array('dirname' => '', 'basename' => '', 'extension' => '', 'filename' => '');
        preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im', $filepath, $m);

        $ret['dirname']   = !empty($m[1]) ? $m[1] : '';
        $ret['basename']  = !empty($m[2]) ? $m[2] : '';
        $ret['extension'] = !empty($m[5]) ? $m[5] : '';
        $ret['filename']  = !empty($m[3]) ? $m[3] : '';

        return $ret;
    }
}

/**
 * 检查是否是onlybody模式。
 * Check exist onlybody param.
 *
 * @access public
 * @return void
 */
function isonlybody()
{
    return helper::inOnlyBodyMode();
}

/**
 * Format time.
 *
 * @param  int    $time
 * @param  string $format
 * @access public
 * @return void
 */
function formatTime($time, $format = '')
{
    $time = str_replace('0000-00-00', '', $time);
    $time = str_replace('00:00:00', '', $time);
    if(trim($time) == '') return ;
    if($format) return date($format, strtotime($time));
    return trim($time);
}