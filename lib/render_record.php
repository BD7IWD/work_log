<?php
/**
 * 单条记录的详情渲染（view.php 与 share.php 共用）
 */
require_once __DIR__ . '/customers.php';

/**
 * @param array  $rec      记录行
 * @param array  $atts     附件行数组
 * @param string $mode     'view' | 'share'
 * @param string $token    分享模式下的 token
 * @param string $baseUrl  附件地址前缀的生成器：返回 function($att) => url
 */
function render_record_detail($rec, $atts, $mode = 'view', $token = '')
{
    $isShare = ($mode === 'share');

    // 附件 URL 生成
    $urlFor = function ($a, $opt = array()) use ($isShare, $token) {
        $q = $opt ? ('&' . http_build_query($opt)) : '';
        if ($isShare) {
            return url('share_file.php?t=' . urlencode($token) . '&a=' . (int)$a['id'] . $q);
        }
        return url('file.php?a=' . (int)$a['id'] . $q);
    };

    $titleTxt = trim($rec['title']);
    if ($titleTxt === '') {
        $first = preg_split('/\r\n|\r|\n/', trim($rec['content']));
        $titleTxt = mb_substr($first[0], 0, 60, 'UTF-8');
        if ($titleTxt === '') $titleTxt = '未命名记录';
    }

    // 分组
    $images = array(); $videos = array(); $audios = array(); $docs = array();
    foreach ($atts as $a) {
        if ($a['kind'] === 'image')      $images[] = $a;
        elseif ($a['kind'] === 'video')  $videos[] = $a;
        elseif ($a['kind'] === 'audio')  $audios[] = $a;
        else                             $docs[]   = $a;
    }
    ?>
<div class="sheet">
  <div class="sheet-h">
    <h2><?php echo e($titleTxt); ?></h2>
    <span class="rec-time">🕘 <?php echo e(date('Y-m-d H:i', strtotime($rec['happened_at']))); ?></span>
  </div>

  <?php if (trim($rec['customer']) !== ''): ?>
    <div class="kv"><span class="k">涉及客户</span><span class="v"><?php foreach (customer_list($rec['customer']) as $cus): ?><span class="tagchip"><?php echo e($cus); ?></span><?php endforeach; ?></span></div>
  <?php endif; ?>
  <?php if (trim($rec['related_people']) !== ''): ?>
    <div class="kv"><span class="k">相关人员</span><span class="v"><?php echo nl2br(e($rec['related_people'])); ?></span></div>
  <?php endif; ?>
  <?php if (trim($rec['location']) !== ''): ?>
    <div class="kv"><span class="k">地点</span><span class="v"><?php echo e($rec['location']); ?></span></div>
  <?php endif; ?>
  <div class="kv"><span class="k">记录时间</span><span class="v" style="color:var(--muted);font-size:12.5px">
    <?php echo e($rec['created_at']); ?><?php if ($rec['updated_at'] && $rec['updated_at'] !== $rec['created_at']): ?>（最后修改 <?php echo e($rec['updated_at']); ?>）<?php endif; ?>
  </span></div>

  <div class="content-box"><?php echo e($rec['content']); ?></div>
</div>

<?php if ($images): ?>
<div class="sheet">
  <div class="sheet-h"><h2 style="font-size:15px">图片 · <?php echo count($images); ?> 张</h2></div>
  <?php foreach ($images as $a): ?>
    <div class="media-block">
      <a href="<?php echo e($urlFor($a, array('download' => 1))); ?>" target="_blank" rel="noopener">
        <img src="<?php echo e($urlFor($a)); ?>" alt="<?php echo e($a['orig_name']); ?>" loading="lazy">
      </a>
      <div style="font-size:12px;color:var(--muted);margin-top:5px;display:flex;gap:8px;flex-wrap:wrap">
        <span style="flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo e($a['orig_name']); ?></span>
        <span><?php echo e(fmt_size($a['size'])); ?></span>
        <a href="<?php echo e($urlFor($a, array('download' => 1))); ?>" style="flex:0 0 auto">下载原图</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($videos): ?>
<div class="sheet">
  <div class="sheet-h"><h2 style="font-size:15px">视频 · <?php echo count($videos); ?> 个</h2></div>
  <?php foreach ($videos as $a): ?>
    <div class="media-block">
      <video controls playsinline webkit-playsinline preload="metadata"
             src="<?php echo e($urlFor($a)); ?>"></video>
      <div style="font-size:12px;color:var(--muted);margin-top:5px;display:flex;gap:8px;flex-wrap:wrap">
        <span style="flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo e($a['orig_name']); ?></span>
        <span><?php echo e(fmt_size($a['size'])); ?></span>
        <a href="<?php echo e($urlFor($a, array('download' => 1))); ?>" style="flex:0 0 auto">下载</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($audios): ?>
<div class="sheet">
  <div class="sheet-h"><h2 style="font-size:15px">录音 · <?php echo count($audios); ?> 个</h2></div>
  <?php foreach ($audios as $a): ?>
    <div class="media-block">
      <audio controls preload="metadata" src="<?php echo e($urlFor($a)); ?>"></audio>
      <div style="font-size:12px;color:var(--muted);margin-top:5px;display:flex;gap:8px;flex-wrap:wrap">
        <span style="flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo e($a['orig_name']); ?></span>
        <span><?php echo e(fmt_size($a['size'])); ?></span>
        <a href="<?php echo e($urlFor($a, array('download' => 1))); ?>" style="flex:0 0 auto">下载</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($docs): ?>
<div class="sheet">
  <div class="sheet-h"><h2 style="font-size:15px">文档 · <?php echo count($docs); ?> 个</h2></div>
  <div class="att-grid">
    <?php foreach ($docs as $a): ?>
      <div class="att">
        <a class="a-media" href="<?php echo e($urlFor($a)); ?>" target="_blank" rel="noopener">📄</a>
        <div class="a-info">
          <span class="a-name" title="<?php echo e($a['orig_name']); ?>"><?php echo e($a['orig_name']); ?></span>
          <span class="a-size"><?php echo e(strtoupper($a['ext'])); ?> · <?php echo e(fmt_size($a['size'])); ?></span>
        </div>
        <div class="a-act">
          <a href="<?php echo e($urlFor($a)); ?>" target="_blank" rel="noopener">预览</a>
          <a href="<?php echo e($urlFor($a, array('download' => 1))); ?>">下载</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!$atts): ?>
<div class="sheet">
  <div style="font-size:13px;color:var(--muted);text-align:center;padding:8px 0">这条记录没有附件</div>
</div>
<?php endif; ?>
<?php
}
