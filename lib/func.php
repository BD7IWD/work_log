<?php
require_once __DIR__ . '/../config.php';

/** HTML 转义 */
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** 拼 APP_ROOT 的相对地址 */
function url($path = '')
{
    $path = ltrim((string)$path, '/');
    if ($path === '') {
        return APP_ROOT;
    }
    if (strpos($path, APP_ROOT) === 0 || preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return APP_ROOT . $path;
}

/** 跳转（幂等：已带 APP_ROOT 就先剥掉再拼） */
function redirect($path)
{
    $path = (string)$path;
    if (strpos($path, APP_ROOT) === 0 && APP_ROOT !== '/') {
        $path = substr($path, strlen(APP_ROOT));
    }
    header('Location: ' . url($path));
    exit;
}

/** 取 GET/POST 字符串（已去首尾空白） */
function input($key, $default = '')
{
    if (isset($_POST[$key])) {
        $v = $_POST[$key];
    } elseif (isset($_GET[$key])) {
        $v = $_GET[$key];
    } else {
        return $default;
    }
    if (is_array($v)) {
        return $default;
    }
    return trim((string)$v);
}

/** 取整数 */
function input_int($key, $default = 0)
{
    $v = input($key, '');
    if ($v === '' || !preg_match('/^-?\d+$/', $v)) {
        return $default;
    }
    return (int)$v;
}

/** JSON 输出 */
function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 人类可读文件大小 */
function fmt_size($bytes)
{
    $bytes = (float)$bytes;
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 0) . ' KB';
    return $bytes . ' B';
}

/** 附件类型 -> 中文标签 */
function kind_label($kind)
{
    $m = array('image' => '图片', 'video' => '视频', 'audio' => '录音', 'doc' => '文档');
    return isset($m[$kind]) ? $m[$kind] : '文件';
}

/** 附件类型 -> emoji 图标（无外部依赖） */
function kind_icon($kind)
{
    $m = array('image' => '🖼', 'video' => '🎬', 'audio' => '🎙', 'doc' => '📄');
    return isset($m[$kind]) ? $m[$kind] : '📎';
}

/** 时间格式化：今天/昨天 智能显示 */
function fmt_time($dt)
{
    if (!$dt) return '';
    $ts = strtotime($dt);
    if (!$ts) return $dt;
    $today = strtotime('today');
    $yest  = strtotime('yesterday');
    if ($ts >= $today) {
        return '今天 ' . date('H:i', $ts);
    }
    if ($ts >= $yest && $ts < $today) {
        return '昨天 ' . date('H:i', $ts);
    }
    if (date('Y', $ts) === date('Y')) {
        return date('m-d H:i', $ts);
    }
    return date('Y-m-d H:i', $ts);
}

/** 纯文本摘要（保留换行折叠） */
function excerpt($text, $len = 120)
{
    $text = preg_replace('/\s+/u', ' ', (string)$text);
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') > $len) {
        return mb_substr($text, 0, $len, 'UTF-8') . '…';
    }
    return $text;
}

/** datetime-local 值规范化：2026-10-20T09:30 -> 2026-10-20 09:30:00 */
function norm_datetime($v)
{
    $v = trim((string)$v);
    if ($v === '') return '';
    $v = str_replace('T', ' ', $v);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) {
        $v .= ':00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        $v .= ' 00:00:00';
    }
    $ts = strtotime($v);
    if (!$ts) return '';
    return date('Y-m-d H:i:s', $ts);
}

/** 把 php.ini 的 '512M' / '1G' / '2048K' 解析成字节数 */
function ini_bytes($val)
{
    $val = trim((string)$val);
    if ($val === '') {
        return 0;
    }
    $last = strtolower(substr($val, -1));
    $n = (float)$val;
    if ($last === 'g') {
        $n *= 1024 * 1024 * 1024;
    } elseif ($last === 'm') {
        $n *= 1024 * 1024;
    } elseif ($last === 'k') {
        $n *= 1024;
    }
    return (int)$n;
}

/**
 * 当前 PHP 实际生效的单文件上传上限
 * （取 upload_max_filesize 与 post_max_size 中较小者）
 */
function effective_upload_max()
{
    $u = ini_bytes(ini_get('upload_max_filesize'));
    $p = ini_bytes(ini_get('post_max_size'));
    if ($u <= 0) $u = $p;
    if ($p <= 0) $p = $u;
    $m = ($u > 0 && $p > 0) ? min($u, $p) : max($u, $p);
    return $m > 0 ? $m : (2 * 1024 * 1024);
}

/** 拼完整绝对 URL（用于分享链接） */
function full_url($path = '')
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $scheme = $https ? 'https' : 'http';
    $p = ltrim((string)$path, '/');
    return $scheme . '://' . $host . APP_ROOT . $p;
}

/** 简体中文安全截断（用于 PDF） */
function cn_cut($s, $n)
{
    $s = (string)$s;
    if (mb_strlen($s, 'UTF-8') > $n) {
        return mb_substr($s, 0, $n, 'UTF-8') . '…';
    }
    return $s;
}
