<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/upload.php';

$me = require_login();

$id = input_int('id', 0);
if ($id <= 0) {
    json_out(array('ok' => 0, 'msg' => '缺少记录 ID'), 400);
}

$st = db()->prepare('SELECT * FROM records WHERE id = ?');
$st->execute(array($id));
$rec = $st->fetch();
if (!$rec) {
    json_out(array('ok' => 0, 'msg' => '记录不存在'), 404);
}

$st = db()->prepare('SELECT id, orig_name, size, kind, ext FROM attachments WHERE record_id = ? ORDER BY id ASC');
$st->execute(array($id));
$list = $st->fetchAll();

$ts = strtotime($rec['happened_at']);

json_out(array(
    'ok' => 1,
    'record' => array(
        'id'             => (int)$rec['id'],
        'happened_at'    => $rec['happened_at'],
        'happened_local' => date('Y-m-d\TH:i', $ts),
        'title'          => $rec['title'],
        'content'        => $rec['content'],
        'related_people' => $rec['related_people'],
        'customer'       => $rec['customer'],
        'location'       => $rec['location'],
        'share_token'    => $rec['share_token'],
    ),
    'attachments' => array_map(function ($a) {
        return array(
            'id'        => (int)$a['id'],
            'orig_name' => $a['orig_name'],
            'size'      => (int)$a['size'],
            'kind'      => $a['kind'],
            'ext'       => $a['ext'],
        );
    }, $list),
));
