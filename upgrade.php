<?php
/**
 * 数据库升级脚本（幂等，可重复访问）
 * 用途：把旧版库升级到当前版本的结构。
 * 用法：浏览器访问 http://你的域名/upgrade.php ，看到「升级完成」后【删除本文件】。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';

header('Content-Type: text/html; charset=utf-8');

$log = array();
$err = '';

try {
    $pdo = db();

    // ---- 1) records.customer（v1.1 新增：涉及客户）----
    $col = $pdo->query("SHOW COLUMNS FROM `records` LIKE 'customer'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE `records`
                    ADD COLUMN `customer` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '涉及客户'
                    AFTER `related_people`");
        $log[] = array('ok', '已新增字段 records.customer（涉及客户）');
    } else {
        $log[] = array('skip', 'records.customer 已存在，跳过');
    }

    // ---- 2) 确认三张表齐全 ----
    $tables = array('users', 'records', 'attachments');
    foreach ($tables as $t) {
        $has = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetch();
        $log[] = $has
            ? array('skip', '表 ' . $t . ' 存在')
            : array('bad', '表 ' . $t . ' 缺失！请先导入 install.sql');
    }
} catch (Exception $e) {
    $err = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>数据库升级 · <?php echo htmlspecialchars(APP_NAME); ?></title>
<style>
    body{margin:0;background:#f4f6fa;font:15px/1.7 -apple-system,"Segoe UI","Microsoft YaHei",sans-serif;color:#1f2937}
    .box{max-width:560px;margin:48px auto;background:#fff;border:1px solid #e6e9ef;border-radius:14px;
         box-shadow:0 8px 26px rgba(16,24,40,.08);padding:26px 22px}
    h1{margin:0 0 6px;font-size:19px}
    .sub{color:#8a94a6;font-size:13px;margin:0 0 18px}
    ul{list-style:none;padding:0;margin:0 0 18px}
    li{padding:8px 11px;border-radius:9px;margin-bottom:7px;font-size:14px}
    .ok{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
    .skip{background:#f5f7fb;color:#4b5563;border:1px solid #e6e9ef}
    .bad{background:#fef2f2;color:#b91c1c;border:1px solid #f6c9c9}
    .err{background:#fef2f2;color:#b91c1c;border:1px solid #f6c9c9;padding:11px;border-radius:9px;
         font-size:13px;word-break:break-all;margin-bottom:16px}
    .tip{background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:11px;border-radius:9px;font-size:13px}
    a{color:#2563eb}
</style>
</head>
<body>
<div class="box">
  <h1>数据库升级</h1>
  <p class="sub">目标版本：v1.1（新增「涉及客户」字段）</p>

  <?php if ($err !== ''): ?>
    <div class="err"><b>升级失败：</b><br><?php echo htmlspecialchars($err); ?></div>
    <div class="tip">请检查 config.php 里的数据库配置是否正确。</div>
  <?php else: ?>
    <ul>
      <?php foreach ($log as $item): ?>
        <li class="<?php echo $item[0]; ?>">
          <?php echo $item[0] === 'ok' ? '✅ ' : ($item[0] === 'skip' ? '· ' : '❌ '); ?>
          <?php echo htmlspecialchars($item[1]); ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="tip">
      <b>升级完成。</b><br>
      请立刻<b>删除本文件</b>（upgrade.php），避免暴露数据库结构信息。<br>
      然后回到 <a href="<?php echo htmlspecialchars(APP_ROOT . 'index.php'); ?>">首页</a> 继续使用。
    </div>
  <?php endif; ?>
</div>
</body>
</html>
