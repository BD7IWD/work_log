<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/upload.php';

$me = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(array('ok' => 0, 'msg' => '请求方式错误'), 405);
}

$id = input_int('id', 0);
if ($id <= 0) {
    json_out(array('ok' => 0, 'msg' => '缺少记录 ID'), 400);
}

$pdo = db();
$st = $pdo->prepare('SELECT * FROM records WHERE id = ?');
$st->execute(array($id));
$rec = $st->fetch();
if (!$rec) {
    json_out(array('ok' => 0, 'msg' => '记录不存在或已被删除'), 404);
}
if (!can_edit_record($rec, $me)) {
    json_out(array('ok' => 0, 'msg' => '无权删除这条记录'), 403);
}

// 先删附件文件
$st = $pdo->prepare('SELECT * FROM attachments WHERE record_id = ?');
$st->execute(array($id));
$atts = $st->fetchAll();

$pdo->beginTransaction();
try {
    $st = $pdo->prepare('DELETE FROM attachments WHERE record_id = ?');
    $st->execute(array($id));
    $st = $pdo->prepare('DELETE FROM records WHERE id = ?');
    $st->execute(array($id));
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_out(array('ok' => 0, 'msg' => '删除失败：' . $e->getMessage()), 500);
}

// 文件删除放在 DB 成功之后
foreach ($atts as $a) {
    $path = attach_path($a);
    if (is_file($path)) @unlink($path);
    $thumb = UPLOAD_DIR . DIRECTORY_SEPARATOR . '_thumb' . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $a['stored_name']);
    if (is_file($thumb)) @unlink($thumb);
}

json_out(array('ok' => 1, 'msg' => '已删除', 'removed_files' => count($atts)));
