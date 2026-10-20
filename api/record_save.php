<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/upload.php';
require_once __DIR__ . '/../lib/customers.php';

$me = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(array('ok' => 0, 'msg' => '请求方式错误'), 405);
}

// POST 体积超过 post_max_size 时 PHP 会丢弃整个 POST/FILES
if (empty($_POST) && empty($_FILES) && !empty($_SERVER['CONTENT_LENGTH'])) {
    json_out(array('ok' => 0, 'msg' => '上传内容超过服务器 post_max_size 限制（' . ini_get('post_max_size')
        . '），请在 php.ini 里调大 post_max_size 与 upload_max_filesize'), 413);
}

$id       = input_int('id', 0);
$happened = norm_datetime(input('happened_at'));
$content  = input('content');
$title    = mb_substr(input('title'), 0, 120, 'UTF-8');
$people   = mb_substr(input('related_people'), 0, 255, 'UTF-8');
$customer = customer_normalize(input('customer'));
$location = mb_substr(input('location'), 0, 120, 'UTF-8');

if ($happened === '') {
    json_out(array('ok' => 0, 'msg' => '请选择有效的工作时间'), 400);
}
if ($content === '') {
    json_out(array('ok' => 0, 'msg' => '请填写工作内容'), 400);
}
if (mb_strlen($content, 'UTF-8') > 20000) {
    json_out(array('ok' => 0, 'msg' => '工作内容过长（上限 2 万字）'), 400);
}

$pdo = db();

if ($id > 0) {
    /* ---------------- 编辑 ---------------- */
    $st = $pdo->prepare('SELECT * FROM records WHERE id = ?');
    $st->execute(array($id));
    $rec = $st->fetch();
    if (!$rec) {
        json_out(array('ok' => 0, 'msg' => '记录不存在或已被删除'), 404);
    }
    if (!can_edit_record($rec, $me)) {
        json_out(array('ok' => 0, 'msg' => '无权修改这条记录'), 403);
    }

    $st = $pdo->prepare('UPDATE records SET happened_at=?, title=?, content=?, related_people=?, customer=?, location=?, updated_at=NOW() WHERE id=?');
    $st->execute(array($happened, $title, $content, $people, $customer, $location, $id));

    // 删除被移除的附件（必须属于该记录）
    $deleted = isset($_POST['deleted_attach']) && is_array($_POST['deleted_attach']) ? $_POST['deleted_attach'] : array();
    $delCount = 0;
    foreach ($deleted as $aid) {
        $aid = (int)$aid;
        if ($aid <= 0) continue;
        $st = $pdo->prepare('SELECT * FROM attachments WHERE id = ? AND record_id = ?');
        $st->execute(array($aid, $id));
        $att = $st->fetch();
        if ($att) {
            delete_attachment($att);
            $delCount++;
        }
    }

    list($saved, $errors) = handle_uploads($id);
    $msg = '已更新';
    if ($delCount > 0) $msg .= '，移除 ' . $delCount . ' 个附件';
    if ($saved) $msg .= '，新增 ' . count($saved) . ' 个附件';

    json_out(array('ok' => 1, 'id' => $id, 'msg' => $msg, 'warnings' => $errors));
}

/* ---------------- 新建 ---------------- */
$st = $pdo->prepare('INSERT INTO records (user_id, happened_at, title, content, related_people, customer, location, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,NOW(),NOW())');
$st->execute(array((int)$me['id'], $happened, $title, $content, $people, $customer, $location));
$newId = (int)$pdo->lastInsertId();

list($saved, $errors) = handle_uploads($newId);

$msg = '已保存';
if ($saved) $msg .= '，' . count($saved) . ' 个附件已上传';

json_out(array('ok' => 1, 'id' => $newId, 'msg' => $msg, 'warnings' => $errors));
