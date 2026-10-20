<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/render_record.php';
$me = require_login();

$id = input_int('id', 0);
if ($id <= 0) {
    redirect('index.php');
}

$st = db()->prepare('SELECT * FROM records WHERE id = ?');
$st->execute(array($id));
$rec = $st->fetch();
if (!$rec) {
    render_header('记录不存在');
    echo '<div class="container"><div class="empty"><div class="em-ico">🔍</div>'
       . '<div class="em-t">记录不存在或已被删除</div>'
       . '<div class="em-s"><a href="' . e(url('index.php')) . '">返回列表</a></div></div></div>';
    render_footer();
    exit;
}
if (!can_edit_record($rec, $me)) {
    render_header('无权查看');
    echo '<div class="container"><div class="empty"><div class="em-ico">🚫</div>'
       . '<div class="em-t">无权查看这条记录</div></div></div>';
    render_footer();
    exit;
}

$st = db()->prepare('SELECT * FROM attachments WHERE record_id = ? ORDER BY id ASC');
$st->execute(array($id));
$atts = $st->fetchAll();

render_header(mb_substr($rec['title'] !== '' ? $rec['title'] : '记录详情', 0, 40, 'UTF-8'));
?>
<div class="container">

  <div class="toolbar" style="margin-bottom:10px">
    <a class="btn btn-sm" href="<?php echo e(url('index.php')); ?>">← 返回列表</a>
    <span class="grow"></span>
    <a class="btn btn-sm" href="<?php echo e(url('export_pdf.php?id=' . $id)); ?>" target="_blank">导出 PDF</a>
    <button type="button" class="btn btn-sm btn-danger js-del" data-id="<?php echo $id; ?>">删除</button>
  </div>

  <?php render_record_detail($rec, $atts, 'view'); ?>

  <?php if (!empty($rec['share_token'])): ?>
    <div class="alert alert-ok" style="margin-top:4px">
      <span>🔗</span>
      <span>这条记录已开启分享：<a href="<?php echo e(full_url('share.php?t=' . $rec['share_token'])); ?>" target="_blank" rel="noopener">打开分享页</a></span>
    </div>
  <?php endif; ?>

  <div style="height:20px"></div>
</div>

<script>
(function () {
  var b = document.querySelector('.js-del');
  if (!b) return;
  b.addEventListener('click', function () {
    if (!confirm('确定删除这条记录？附件也会一并删除，且无法恢复。')) return;
    window.WD.deleteRecord(b.getAttribute('data-id'), function () {
      setTimeout(function () { location.href = window.WD.apiUrl('index.php'); }, 300);
    });
  });
})();
</script>
<?php render_footer(); ?>
