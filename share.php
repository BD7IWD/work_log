<?php
/**
 * 公开分享页：无需登录，凭 token 只读访问
 */
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/render_record.php';

$t = input('t', '');

if ($t === '' || !preg_match('/^[a-zA-Z0-9]{16,64}$/', $t)) {
    render_header('链接无效');
    echo '<div class="container"><div class="empty"><div class="em-ico">🔗</div>'
       . '<div class="em-t">链接无效</div><div class="em-s">请向分享者索取完整链接</div></div></div>';
    render_footer();
    exit;
}

$st = db()->prepare('SELECT * FROM records WHERE share_token = ? LIMIT 1');
$st->execute(array($t));
$rec = $st->fetch();

if (!$rec) {
    render_header('分享已关闭');
    echo '<div class="container"><div class="empty"><div class="em-ico">🔒</div>'
       . '<div class="em-t">分享已关闭或链接已失效</div>'
       . '<div class="em-s">该条记录的分享可能已被分享者取消</div></div></div>';
    render_footer();
    exit;
}

$st = db()->prepare('SELECT * FROM attachments WHERE record_id = ? ORDER BY id ASC');
$st->execute(array((int)$rec['id']));
$atts = $st->fetchAll();

$pageTitle = trim($rec['title']) !== '' ? $rec['title'] : ('工作记录 ' . date('Y-m-d', strtotime($rec['happened_at'])));
render_header($pageTitle);
?>
<div class="container">

  <div class="share-banner">
    <span>👁</span>
    <span style="flex:1 1 auto">你正在通过分享链接查看（只读）</span>
  </div>

  <?php render_record_detail($rec, $atts, 'share', $t); ?>

  <div style="text-align:center;font-size:12px;color:var(--muted);padding:14px 0 24px">
    由「<?php echo e(APP_NAME); ?>」分享
  </div>
</div>
<?php render_footer(); ?>
