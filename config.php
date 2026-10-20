<?php
/**
 * 工作留痕记录工具 - 全局配置
 * 部署到宝塔时，只需改本文件的 DB_* 四项。
 */

// ===== 应用 =====
define('APP_NAME', '工作留痕记录');
define('APP_SLOGAN', 'Work Log');

// ===== 数据库（本机 Wamp 联调：MariaDB 3307 / root 空密码）=====
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_NAME', 'worklog');
define('DB_USER', 'root');
define('DB_PASS', '');

// ===== 涉及客户：预置选项 =====
// 页面里仍可手填新客户；填过的会自动出现在下次的候选里（从历史记录里捞）。
// 想增删预置项，直接改这个数组即可。
$GLOBALS['CUSTOMER_PRESET'] = array(
    '创维', '川虹', '茂佳', 'BOE', 'TPV', '惠科',
    '广虹', '康佳','海创内部'
);
define('CUSTOMER_MAX_PER_RECORD', 12);   // 单条记录最多几个客户

// ===== 上传 =====
define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_FILES', 20);                       // 单次最多文件数
define('MAX_UPLOAD_SIZE', 512 * 1024 * 1024);          // 单文件上限 512MB
define('IMG_MAX_EDGE', 0);                             // >0 时服务端压缩图片最长边（0=不压缩）

// 允许的扩展名 => 归类
$GLOBALS['ALLOW_EXT'] = array(
    // 图片
    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
    'bmp' => 'image', 'webp' => 'image', 'heic' => 'image',
    // 视频
    'mp4' => 'video', 'mov' => 'video', 'avi' => 'video', 'mkv' => 'video',
    'wmv' => 'video', 'flv' => 'video', 'm4v' => 'video', '3gp' => 'video',
    // 音频
    'mp3' => 'audio', 'wav' => 'audio', 'm4a' => 'audio', 'aac' => 'audio',
    'amr' => 'audio', 'ogg' => 'audio', 'wma' => 'audio', 'flac' => 'audio',
    // 文档
    'pdf' => 'doc', 'doc' => 'doc', 'docx' => 'doc', 'xls' => 'doc', 'xlsx' => 'doc',
    'ppt' => 'doc', 'pptx' => 'doc', 'txt' => 'doc', 'csv' => 'doc',
    'zip' => 'doc', 'rar' => 'doc', '7z' => 'doc',
);

// ===== 运行环境 =====
date_default_timezone_set('Asia/Shanghai');
mb_internal_encoding('UTF-8');

// 运行时可改的项（PHP_INI_ALL，ini_set 有效）
@ini_set('max_execution_time', '600');
@ini_set('memory_limit', '512M');

// ⚠️ 注意：以下三项属于 PHP_INI_PERDIR，ini_set() 对它们【无效】，
//    必须改 php.ini（或站点 .user.ini / .htaccess）后重启 PHP 才生效：
//      upload_max_filesize   单个上传文件上限
//      post_max_size         单次 POST 总上限（要 >= 单文件上限）
//      max_input_time        解析输入的超时
//    当前实际生效值可在「设置」页看到，或按「部署说明.md」第 5 步调大。

// ===== APP_ROOT：自动算出「项目相对 web 根」的路径，如 /wd/ 或 / =====
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if (basename($scriptDir) === 'api' || basename($scriptDir) === 'settings') {
    $scriptDir = dirname($scriptDir);
}
$scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
if ($scriptDir === '' || $scriptDir === '.' || $scriptDir === '/') {
    define('APP_ROOT', '/');
} else {
    define('APP_ROOT', $scriptDir . '/');
}
unset($scriptDir);
