<?php
require_once __DIR__ . '/auth.php';

/**
 * 页面外壳：输出 <head> + 顶栏
 * @param string $title  页面标题
 * @param string $active 预留（单账号无需高亮菜单）
 * @param array  $opt    'bare' => true 时不输出顶栏（登录页/分享页）
 */
function render_header($title, $active = '', $opt = array())
{
    $bare = !empty($opt['bare']);
    $u = current_user();
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="format-detection" content="telephone=no">
<meta name="theme-color" content="#ffffff">
<meta name="apple-mobile-web-app-capable" content="yes">
<title><?php echo e($title); ?> · <?php echo e(APP_NAME); ?></title>
<link rel="stylesheet" href="<?php echo e(url('assets/style.css')); ?>?v=1">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%232563eb'/><text y='70' x='50' font-size='58' text-anchor='middle' fill='white' font-family='sans-serif'>痕</text></svg>">
<script>window.WD = { root: <?php echo json_encode(APP_ROOT); ?> };</script>
<script src="<?php echo e(url('assets/app.js')); ?>?v=1"></script>
</head>
<body>
<?php if (!$bare): ?>
<header class="topbar">
  <div class="topbar-in">
    <a class="brand" href="<?php echo e(url('index.php')); ?>">
      <span class="brand-logo">痕</span>
      <span><?php echo e(APP_NAME); ?></span>
    </a>
    <span class="topbar-spacer"></span>
    <?php if ($u): ?>
      <span class="topbar-user" title="<?php echo e($u['display_name'] ? $u['display_name'] : $u['username']); ?>">
        <?php echo e($u['display_name'] ? $u['display_name'] : $u['username']); ?>
      </span>
      <a class="tb-link" href="<?php echo e(url('settings.php')); ?>">设置</a>
      <a class="tb-link" href="<?php echo e(url('logout.php')); ?>">退出</a>
    <?php endif; ?>
  </div>
</header>
<?php endif; ?>
<div id="toastBox"></div>
<?php
}

function render_footer()
{
    // 注意：app.js 已在 <head> 中同步加载（保证页面内联脚本能立即使用 window.WD）
    ?>
</body>
</html>
<?php
}
