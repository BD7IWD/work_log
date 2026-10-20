<?php
require_once __DIR__ . '/lib/layout.php';

if (current_user()) {
    redirect('index.php');
}

$err = '';
$back = input('back', '');
// 只允许站内回跳
if ($back !== '' && (strpos($back, '//') !== false || strpos($back, 'http') === 0)) {
    $back = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = input('username');
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    if ($username === '' || $password === '') {
        $err = '请输入账号和密码';
    } else {
        $r = do_login($username, $password);
        if ($r[0]) {
            if ($back !== '') {
                header('Location: ' . $back);
                exit;
            }
            redirect('index.php');
        } else {
            $err = $r[1];
        }
    }
}

render_header('登录', '', array('bare' => true));
?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">痕</div>
    <h1><?php echo e(APP_NAME); ?></h1>
    <p class="sub">登录后记录、编辑与分享你的工作留痕</p>

    <?php if ($err): ?>
      <div class="alert alert-err" style="margin-bottom:14px"><span>⚠️</span><span><?php echo e($err); ?></span></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
      <div class="field">
        <label for="username">账号</label>
        <input type="text" id="username" name="username" value="<?php echo e(input('username')); ?>"
               autocomplete="username" autocapitalize="off" autocorrect="off" required>
      </div>
      <div class="field">
        <label for="password">密码</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="height:46px">登 录</button>
    </form>

    <div class="login-foot">数据仅保存在你自己的服务器上</div>
  </div>
</div>
<?php render_footer(); ?>
