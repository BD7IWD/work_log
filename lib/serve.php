<?php
require_once __DIR__ . '/upload.php';

/**
 * 输出附件（支持 HTTP Range，视频/音频可拖动进度条）
 * @param array $att    附件行
 * @param array $opt    inline(bool) 是否内联显示；download(bool) 强制下载
 */
function send_attachment($att, $opt = array())
{
    $path = attach_path($att);
    if (!is_file($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '文件不存在或已被删除';
        exit;
    }

    $size = filesize($path);
    $ext  = strtolower($att['ext']);
    $kind = $att['kind'];

    $inlineDefault = in_array($kind, array('image', 'video', 'audio'), true)
        || in_array($ext, array('pdf', 'txt', 'csv'), true);

    $inline = isset($opt['inline']) ? (bool)$opt['inline'] : $inlineDefault;
    if (!empty($opt['download'])) {
        $inline = false;
    }

    $mime = $att['mime'];
    if (!$mime || $mime === 'application/octet-stream') {
        $mime = guess_mime($ext);
    }

    // 清理任何已存在的输出缓冲，避免二进制被污染
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    $name    = $att['orig_name'] !== '' ? $att['orig_name'] : ('file.' . $ext);
    $ascii   = preg_replace('/[^\x20-\x7E]/', '_', $name);
    $disp    = ($inline ? 'inline' : 'attachment')
             . '; filename="' . str_replace('"', '', $ascii) . '"'
             . "; filename*=UTF-8''" . rawurlencode($name);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disp);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    // ---- Range ----
    $start = 0;
    $end   = $size - 1;
    $partial = false;

    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
        $hasStart = ($m[1] !== '');
        $hasEnd   = (isset($m[2]) && $m[2] !== '');
        if ($hasStart) {
            $start = (int)$m[1];
            if ($hasEnd) $end = (int)$m[2];
        } elseif ($hasEnd) {
            // bytes=-N 表示最后 N 字节
            $suffix = (int)$m[2];
            $start  = max(0, $size - $suffix);
        }
        if ($start > $end || $start >= $size) {
            header('HTTP/1.1 416 Range Not Satisfiable');
            header('Content-Range: bytes */' . $size);
            exit;
        }
        if ($end >= $size) $end = $size - 1;
        $partial = true;
    }

    if ($partial) {
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
    header('Content-Length: ' . ($end - $start + 1));

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        exit;
    }

    $fp = @fopen($path, 'rb');
    if (!$fp) {
        http_response_code(500);
        exit('读取文件失败');
    }
    fseek($fp, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, (int)min(262144, $left));
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        $left -= strlen($chunk);
        @flush();
    }
    fclose($fp);
    exit;
}

/**
 * 输出图片缩略图（带磁盘缓存）
 */
function send_thumb($att, $maxEdge = 420)
{
    $path = attach_path($att);
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }

    $cacheDir = UPLOAD_DIR . DIRECTORY_SEPARATOR . '_thumb';
    $cachePath = $cacheDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $att['stored_name']);
    $cacheDirOk = is_dir($cacheDir) || @mkdir($cacheDir, 0755, true);

    // 命中缓存
    if ($cacheDirOk && is_file($cachePath) && filemtime($cachePath) >= filemtime($path)) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($cachePath));
        header('Cache-Control: private, max-age=86400');
        readfile($cachePath);
        exit;
    }

    if (!function_exists('imagecreatefromstring')) {
        send_attachment($att, array('inline' => true));
    }

    $data = @file_get_contents($path);
    $src = $data !== false ? @imagecreatefromstring($data) : false;
    if (!$src) {
        // GD 解不了（如 HEIC），直接回原图
        send_attachment($att, array('inline' => true));
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= 0 || $h <= 0) {
        imagedestroy($src);
        send_attachment($att, array('inline' => true));
    }

    $scale = min(1, $maxEdge / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    // 带透明通道的图先铺白底
    $dst = imagecreatetruecolor($nw, $nh);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    // 先用输出缓冲拿到 JPEG 字节
    ob_start();
    imagejpeg($dst, null, 82);
    $jpeg = ob_get_clean();
    imagedestroy($dst);

    if ($cacheDirOk && $jpeg !== false) {
        $tmpDir = dirname($cachePath);
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
        @file_put_contents($cachePath, $jpeg);
    }

    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($jpeg));
    header('Cache-Control: private, max-age=86400');
    echo $jpeg;
    exit;
}

/** 扩展名 -> MIME 兜底表 */
function guess_mime($ext)
{
    $map = array(
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp',
        'heic' => 'image/heic',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska', 'wmv' => 'video/x-ms-wmv',
        'flv' => 'video/x-flv', '3gp' => 'video/3gpp',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4',
        'aac' => 'audio/aac', 'amr' => 'audio/amr', 'ogg' => 'audio/ogg',
        'wma' => 'audio/x-ms-wma', 'flac' => 'audio/flac',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain', 'csv' => 'text/csv',
        'zip' => 'application/zip', 'rar' => 'application/vnd.rar', '7z' => 'application/x-7z-compressed',
    );
    return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}
