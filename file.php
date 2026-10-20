<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/serve.php';

$me = require_login();

$aid = input_int('a', 0);
if ($aid <= 0) {
    http_response_code(400);
    exit('缺少附件 ID');
}

$st = db()->prepare('SELECT a.*, r.user_id AS rec_user
                     FROM attachments a JOIN records r ON r.id = a.record_id
                     WHERE a.id = ?');
$st->execute(array($aid));
$att = $st->fetch();
if (!$att) {
    http_response_code(404);
    exit('附件不存在');
}

// 权限：记录人本人或管理员
if ((int)$att['rec_user'] !== (int)$me['id'] && $me['role'] !== 'admin') {
    http_response_code(403);
    exit('无权查看该附件');
}

if (input_int('thumb', 0) === 1 && $att['kind'] === 'image') {
    send_thumb($att, 420);
}

send_attachment($att, array('download' => input_int('download', 0) === 1));
