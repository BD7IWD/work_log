<?php
require_once __DIR__ . '/../lib/auth.php';

$me = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(array('ok' => 0, 'msg' => '请求方式错误'), 405);
}

$id     = input_int('id', 0);
$action = input('action', 'on');
if ($id <= 0) {
    json_out(array('ok' => 0, 'msg' => '缺少记录 ID'), 400);
}

$pdo = db();
$st = $pdo->prepare('SELECT * FROM records WHERE id = ?');
$st->execute(array($id));
$rec = $st->fetch();
if (!$rec) {
    json_out(array('ok' => 0, 'msg' => '记录不存在'), 404);
}
if (!can_edit_record($rec, $me)) {
    json_out(array('ok' => 0, 'msg' => '无权操作这条记录'), 403);
}

if ($action === 'off') {
    $st = $pdo->prepare('UPDATE records SET share_token = NULL, share_created_at = NULL WHERE id = ?');
    $st->execute(array($id));
    json_out(array('ok' => 1, 'token' => '', 'msg' => '分享已关闭'));
}

$token = $rec['share_token'];
if (!$token) {
    try {
        $token = bin2hex(random_bytes(16));
    } catch (Exception $e) {
        $token = md5(uniqid((string)mt_rand(), true));
    }
    $st = $pdo->prepare('UPDATE records SET share_token = ?, share_created_at = NOW() WHERE id = ?');
    $st->execute(array($token, $id));
}

json_out(array(
    'ok'    => 1,
    'token' => $token,
    'url'   => full_url('share.php?t=' . $token),
    'msg'   => '分享已开启',
));
