<?php
/**
 * 分享态附件输出：只需正确的 token，无需登录
 */
require_once __DIR__ . '/lib/serve.php';

$t   = input('t', '');
$aid = input_int('a', 0);

if ($t === '' || !preg_match('/^[a-zA-Z0-9]{16,64}$/', $t) || $aid <= 0) {
    http_response_code(400);
    exit('链接无效');
}

$st = db()->prepare('SELECT id FROM records WHERE share_token = ? LIMIT 1');
$st->execute(array($t));
$rec = $st->fetch();
if (!$rec) {
    http_response_code(404);
    exit('分享已关闭或链接无效');
}

$st = db()->prepare('SELECT * FROM attachments WHERE id = ? AND record_id = ? LIMIT 1');
$st->execute(array($aid, (int)$rec['id']));
$att = $st->fetch();
if (!$att) {
    http_response_code(404);
    exit('附件不存在');
}

if (input_int('thumb', 0) === 1 && $att['kind'] === 'image') {
    send_thumb($att, 420);
}

send_attachment($att, array('download' => input_int('download', 0) === 1));
