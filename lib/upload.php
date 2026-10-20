<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/func.php';

/**
 * 处理 $_FILES['files'] 多文件上传
 * @param int $record_id
 * @return array array(成功保存的附件行数组, 错误消息数组)
 */
function handle_uploads($record_id)
{
    global $ALLOW_EXT;

    $saved = array();
    $errors = array();

    if (empty($_FILES['files']) || !isset($_FILES['files']['name'])) {
        return array($saved, $errors);
    }

    $f = $_FILES['files'];
    if (!is_array($f['name'])) {
        $f = array(
            'name'     => array($f['name']),
            'type'     => array($f['type']),
            'tmp_name' => array($f['tmp_name']),
            'error'    => array($f['error']),
            'size'     => array($f['size']),
        );
    }

    $count = count($f['name']);
    if ($count > MAX_UPLOAD_FILES) {
        $errors[] = '一次最多上传 ' . MAX_UPLOAD_FILES . ' 个文件，已忽略多余文件';
        $count = MAX_UPLOAD_FILES;
    }

    for ($i = 0; $i < $count; $i++) {
        $name  = (string)$f['name'][$i];
        $tmp   = (string)$f['tmp_name'][$i];
        $err   = (int)$f['error'][$i];
        $size  = (int)$f['size'][$i];

        if ($name === '' || $err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = $name . '：' . upload_err_text($err);
            continue;
        }
        if ($size <= 0) {
            $errors[] = $name . '：空文件';
            continue;
        }
        if ($size > MAX_UPLOAD_SIZE) {
            $errors[] = $name . '：超过单文件上限 ' . fmt_size(MAX_UPLOAD_SIZE);
            continue;
        }
        if (!is_uploaded_file($tmp)) {
            $errors[] = $name . '：非法上传';
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !isset($ALLOW_EXT[$ext])) {
            $errors[] = $name . '：不支持的文件类型';
            continue;
        }
        $kind = $ALLOW_EXT[$ext];

        // 图片二次校验：必须能解析出宽高
        if ($kind === 'image') {
            $info = @getimagesize($tmp);
            if ($info === false) {
                $errors[] = $name . '：图片文件损坏或格式不受支持';
                continue;
            }
        }

        // 目标目录 uploads/YYYY/MM/
        $sub = date('Y') . '/' . date('m');
        $dir = UPLOAD_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $errors[] = $name . '：上传目录不可写';
            continue;
        }

        $base = bin2hex(random_bytes(16));
        $stored = $base . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $stored;
        $n = 1;
        while (file_exists($dest)) {
            $stored = $base . '_' . $n . '.' . $ext;
            $dest = $dir . DIRECTORY_SEPARATOR . $stored;
            $n++;
        }

        if (!@move_uploaded_file($tmp, $dest)) {
            $errors[] = $name . '：保存失败（目录权限？）';
            continue;
        }
        @chmod($dest, 0644);

        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $m = @finfo_file($fi, $dest);
                if ($m) $mime = $m;
                @finfo_close($fi);
            }
        }
        if ($mime === '') $mime = 'application/octet-stream';

        $rel = $sub . '/' . $stored;
        $st = db()->prepare('INSERT INTO attachments
            (record_id, orig_name, stored_name, mime, ext, size, kind, created_at)
            VALUES (?,?,?,?,?,?,?,NOW())');
        $st->execute(array($record_id, mb_substr($name, 0, 200, 'UTF-8'), $rel, $mime, $ext, $size, $kind));
        $aid = (int)db()->lastInsertId();

        $saved[] = array(
            'id'          => $aid,
            'record_id'   => $record_id,
            'orig_name'   => $name,
            'stored_name' => $rel,
            'mime'        => $mime,
            'ext'         => $ext,
            'size'        => $size,
            'kind'        => $kind,
        );
    }

    return array($saved, $errors);
}

/** 上传错误码 -> 中文 */
function upload_err_text($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:   return '超过服务器 upload_max_filesize 限制';
        case UPLOAD_ERR_FORM_SIZE:  return '超过表单 MAX_FILE_SIZE 限制';
        case UPLOAD_ERR_PARTIAL:    return '只上传了一部分';
        case UPLOAD_ERR_NO_FILE:    return '没有选择文件';
        case UPLOAD_ERR_NO_TMP_DIR: return '服务器缺少临时目录';
        case UPLOAD_ERR_CANT_WRITE: return '服务器写入磁盘失败';
        case UPLOAD_ERR_EXTENSION:  return '被 PHP 扩展中断';
        default:                    return '未知错误（代码 ' . $code . '）';
    }
}

/** 删除附件：数据库行 + 磁盘文件 */
function delete_attachment($att)
{
    $path = UPLOAD_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $att['stored_name']);
    if (is_file($path)) {
        @unlink($path);
    }
    // 附带缩略图缓存
    $thumb = UPLOAD_DIR . DIRECTORY_SEPARATOR . '_thumb' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $att['stored_name']);
    if (is_file($thumb)) {
        @unlink($thumb);
    }
    $st = db()->prepare('DELETE FROM attachments WHERE id = ?');
    $st->execute(array((int)$att['id']));
}

/** 附件磁盘绝对路径 */
function attach_path($att)
{
    return UPLOAD_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $att['stored_name']);
}
