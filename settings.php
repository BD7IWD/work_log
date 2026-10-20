<?php
require_once __DIR__ . '/lib/layout.php';
$me = require_login();

$okMsg = '';
$errMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');

    if ($action === 'profile') {
        $dn = mb_substr(input('display_name'), 0, 50, 'UTF-8');
        $st = db()->prepare('UPDATE users SET display_name = ? WHERE id = ?');
        $st->execute(array($dn, (int)$me['id']));
        $okMsg = '显示名已更新';
        $me = current_user();
    } elseif ($action === 'password') {
        $cur  = isset($_POST['cur_password']) ? (string)$_POST['cur_password'] : '';
        $new  = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
        $new2 = isset($_POST['new_password2']) ? (string)$_POST['new_password2'] : '';

        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute(array((int)$me['id']));
        $row = $st->fetch();

        if (!$row || !password_verify($cur, $row['password_hash'])) {
            $errMsg = '当前密码不正确';
        } elseif (strlen($new) < 6) {
            $errMsg = '新密码至少 6 位';
        } elseif ($new !== $new2) {
            $errMsg = '两次输入的新密码不一致';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $st = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $st->execute(array($hash, (int)$me['id']));
            $okMsg = '密码已修改，下次登录请使用新密码';
        }
    }
}

$st = db()->prepare('SELECT COUNT(*) AS c FROM records');
$st->execute();
$totalRec = (int)$st->fetch()['c'];

$st = db()->prepare('SELECT COUNT(*) AS c FROM attachments');
$st->execute();
$totalAtt = (int)$st->fetch()['c'];

$st = db()->prepare('SELECT COALESCE(SUM(size),0) AS s FROM attachments');
$st->execute();
$totalSize = (float)$st->fetch()['s'];

render_header('账号设置');
?>
<div class="container">

  <div class="page-head">
    <h1 class="page-title">账号设置</h1>
    <div class="page-sub">当前账号：<?php echo e($me['username']); ?></div>
  </div>

  <?php if ($okMsg): ?>
    <div class="alert alert-ok" style="margin-bottom:12px"><span>✅</span><span><?php echo e($okMsg); ?></span></div>
  <?php endif; ?>
  <?php if ($errMsg): ?>
    <div class="alert alert-err" style="margin-bottom:12px"><span>⚠️</span><span><?php echo e($errMsg); ?></span></div>
  <?php endif; ?>

  <div class="stack">

    <div class="card-panel">
      <h2>数据概览</h2>
      <p class="cp-sub">当前实例的存储使用情况</p>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center">
        <div>
          <div style="font-size:22px;font-weight:700;color:var(--brand)"><?php echo $totalRec; ?></div>
          <div style="font-size:12px;color:var(--muted)">条记录</div>
        </div>
        <div>
          <div style="font-size:22px;font-weight:700;color:var(--brand)"><?php echo $totalAtt; ?></div>
          <div style="font-size:12px;color:var(--muted)">个附件</div>
        </div>
        <div>
          <div style="font-size:22px;font-weight:700;color:var(--brand)"><?php echo e(fmt_size($totalSize)); ?></div>
          <div style="font-size:12px;color:var(--muted)">附件占用</div>
        </div>
      </div>
    </div>

    <div class="card-panel">
      <h2>服务器上传限制</h2>
      <p class="cp-sub">决定单个附件最大能传多大（手机拍的视频常达几十 MB）</p>
      <?php
        $upRaw    = ini_get('upload_max_filesize');
        $postRaw  = ini_get('post_max_size');
        $upMaxB   = effective_upload_max();
        $upWarn   = $upMaxB < 64 * 1024 * 1024;
      ?>
      <div class="kv"><span class="k">单文件上限</span><span class="v"><b><?php echo e($upRaw); ?></b> <span style="color:var(--muted)">(upload_max_filesize)</span></span></div>
      <div class="kv"><span class="k">POST 上限</span><span class="v"><b><?php echo e($postRaw); ?></b> <span style="color:var(--muted)">(post_max_size)</span></span></div>
      <div class="kv"><span class="k">实际可用</span><span class="v"><b style="color:var(--brand)"><?php echo e(fmt_size($upMaxB)); ?></b></span></div>

      <?php if ($upWarn): ?>
        <div class="alert alert-err" style="margin-top:10px">
          <span>⚠️</span>
          <span>当前上限偏小，手机拍的视频 / 照片很可能会传不上去。
            请按「部署说明.md」第 5 步调大 php.ini 里的
            <code>upload_max_filesize</code> 与 <code>post_max_size</code>，然后重启 PHP / 网站。</span>
        </div>
      <?php else: ?>
        <div class="alert alert-ok" style="margin-top:10px"><span>✅</span><span>上传限制充足</span></div>
      <?php endif; ?>
    </div>

    <div class="card-panel">
      <h2>显示名</h2>
      <p class="cp-sub">显示在页面右上角，仅影响显示</p>
      <form method="post" class="stack">
        <input type="hidden" name="action" value="profile">
        <div class="field">
          <label for="display_name">显示名</label>
          <input type="text" id="display_name" name="display_name" maxlength="50"
                 value="<?php echo e($me['display_name']); ?>" placeholder="如：宋良才">
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-start">保存</button>
      </form>
    </div>

    <div class="card-panel">
      <h2>修改密码</h2>
      <p class="cp-sub">建议首次登录后立即修改默认密码</p>
      <form method="post" class="stack" autocomplete="off">
        <input type="hidden" name="action" value="password">
        <div class="field">
          <label for="cur_password">当前密码 <span class="req">*</span></label>
          <input type="password" id="cur_password" name="cur_password" autocomplete="current-password" required>
        </div>
        <div class="form-grid two">
          <div class="field">
            <label for="new_password">新密码 <span class="req">*</span></label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password" required minlength="6">
          </div>
          <div class="field">
            <label for="new_password2">确认新密码 <span class="req">*</span></label>
            <input type="password" id="new_password2" name="new_password2" autocomplete="new-password" required minlength="6">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-start">修改密码</button>
      </form>
    </div>

    <div class="card-panel">
      <h2>导出</h2>
      <p class="cp-sub">全部记录导出为 Excel（.xls，可用 Excel / WPS 直接打开）</p>
      <a class="btn" href="<?php echo e(url('export_excel.php')); ?>">⬇ 导出全部记录 Excel</a>
    </div>

    <div class="card-panel">
      <h2>使用提示</h2>
      <p class="cp-sub" style="margin-bottom:8px">几点使用说明</p>
      <ul style="margin:0;padding-left:18px;font-size:13.5px;color:var(--text-2);line-height:1.9">
        <li>点右下角 <b>＋</b> 或「新增记录」录入，支持一次选多个文件。</li>
        <li>视频/录音在手机上录完可直接选相册或文件上传。</li>
        <li>点「分享」生成只读链接，任何人拿到链接都能看，可随时关闭。</li>
        <li>「PDF」生成单条记录的可打印版，浏览器里可直接另存为 PDF。</li>
      </ul>
    </div>

  </div>
  <div style="height:20px"></div>
</div>
<?php render_footer(); ?>
