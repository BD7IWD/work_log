<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/customers.php';
$me = require_login();
$custOptions = customer_options();

/* ---------------- 筛选参数 ---------------- */
$kw     = input('kw');
$f_from = input('from');
$f_to   = input('to');
$page   = input_int('page', 1);
if ($page < 1) $page = 1;
$per    = 20;

$where = array();
$args  = array();

if ($kw !== '') {
    $like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $kw) . '%';
    $where[] = "(title LIKE ? OR content LIKE ? OR related_people LIKE ? OR customer LIKE ? OR location LIKE ?)";
    array_push($args, $like, $like, $like, $like, $like);
}
if ($f_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) {
    $where[] = "happened_at >= ?";
    $args[]  = $f_from . ' 00:00:00';
}
if ($f_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to)) {
    $where[] = "happened_at <= ?";
    $args[]  = $f_to . ' 23:59:59';
}
$w = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* ---------------- 总数 ---------------- */
$st = db()->prepare("SELECT COUNT(*) AS c FROM records" . $w);
$st->execute($args);
$total = (int)$st->fetch()['c'];
$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $per;

/* ---------------- 取记录 ---------------- */
$sql = "SELECT * FROM records" . $w . " ORDER BY happened_at DESC, id DESC LIMIT " . (int)$per . " OFFSET " . (int)$offset;
$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

/* ---------------- 取附件 ---------------- */
$atts = array();
if ($rows) {
    $ids = array();
    foreach ($rows as $r) $ids[] = (int)$r['id'];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st2 = db()->prepare("SELECT * FROM attachments WHERE record_id IN ($ph) ORDER BY id ASC");
    $st2->execute($ids);
    foreach ($st2->fetchAll() as $a) {
        $atts[(int)$a['record_id']][] = $a;
    }
}

/* ---------------- 保留查询串 ---------------- */
function page_url($over = array())
{
    $q = array(
        'kw'   => input('kw'),
        'from' => input('from'),
        'to'   => input('to'),
        'page' => input_int('page', 1),
    );
    foreach ($over as $k => $v) $q[$k] = $v;
    $q = array_filter($q, function ($v) { return $v !== '' && $v !== null; });
    return url('index.php') . ($q ? ('?' . http_build_query($q)) : '');
}

$has_filter = ($kw !== '' || $f_from !== '' || $f_to !== '');
render_header('工作留痕');
?>
<div class="container">

  <div class="page-head">
    <h1 class="page-title">工作留痕</h1>
    <div class="page-sub">
      共 <b style="color:var(--brand)"><?php echo $total; ?></b> 条记录
      <?php if ($has_filter): ?>· 已筛选<?php endif; ?>
    </div>
  </div>

  <div class="toolbar">
    <form method="get" action="<?php echo e(url('index.php')); ?>" style="flex:1 1 200px;display:flex;gap:8px;min-width:0">
      <input type="search" name="kw" value="<?php echo e($kw); ?>" placeholder="搜索内容 / 人员 / 地点"
             style="flex:1 1 auto;min-width:0;height:42px">
      <?php if ($f_from !== ''): ?><input type="hidden" name="from" value="<?php echo e($f_from); ?>"><?php endif; ?>
      <?php if ($f_to !== ''): ?><input type="hidden" name="to" value="<?php echo e($f_to); ?>"><?php endif; ?>
      <button type="submit" class="btn" style="padding:0 14px">搜索</button>
    </form>

    <button type="button" class="filter-toggle" id="filterToggle">
      <span>筛选</span><span class="caret">▾</span>
    </button>

    <button type="button" class="btn btn-add-desktop btn-primary" id="btnAddDesktop">
      <span class="ico">＋</span>新增记录
    </button>
    <a class="btn btn-add-desktop" href="<?php echo e(url('export_excel.php') . ($has_filter ? '?' . http_build_query(array_filter(array('kw' => $kw, 'from' => $f_from, 'to' => $f_to))) : '')); ?>">
      <span class="ico">⬇</span>导出 Excel
    </a>
  </div>

  <!-- 筛选面板 -->
  <div class="filter-panel<?php echo ($f_from !== '' || $f_to !== '') ? ' show' : ''; ?>" id="filterPanel">
    <form method="get" action="<?php echo e(url('index.php')); ?>">
      <div class="filter-grid">
        <div class="field">
          <label>关键字</label>
          <input type="text" name="kw" value="<?php echo e($kw); ?>" placeholder="工作内容 / 相关人员 / 地点">
        </div>
        <div class="field">
          <label>开始日期</label>
          <input type="date" name="from" value="<?php echo e($f_from); ?>">
        </div>
        <div class="field">
          <label>结束日期</label>
          <input type="date" name="to" value="<?php echo e($f_to); ?>">
        </div>
        <div class="field">
          <label>&nbsp;</label>
          <button type="submit" class="btn btn-primary" style="width:100%">应用筛选</button>
        </div>
      </div>
      <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-sm" href="<?php echo e(url('index.php')); ?>">清空条件</a>
        <a class="btn btn-sm" href="<?php echo e(url('export_excel.php')); ?>">导出全部 Excel</a>
        <a class="btn btn-sm" href="<?php echo e(url('export_excel.php') . ($has_filter ? '?' . http_build_query(array_filter(array('kw' => $kw, 'from' => $f_from, 'to' => $f_to))) : '')); ?>">导出当前筛选</a>
      </div>
    </form>
  </div>

  <?php if (!$rows): ?>
    <div class="empty">
      <div class="em-ico">📋</div>
      <div class="em-t"><?php echo $has_filter ? '没有符合条件的记录' : '还没有任何记录'; ?></div>
      <div class="em-s"><?php echo $has_filter ? '换个条件再试试' : '点下方按钮记录第一条工作留痕'; ?></div>
    </div>
  <?php else: ?>
    <div class="rec-list">
      <?php foreach ($rows as $r):
          $rid   = (int)$r['id'];
          $myatts = isset($atts[$rid]) ? $atts[$rid] : array();
          $imgCount = 0;
          $thumbs = array();
          foreach ($myatts as $a) {
              if ($a['kind'] === 'image') {
                  $imgCount++;
                  if (count($thumbs) < 4) $thumbs[] = $a;
              }
          }
          $shareOn = !empty($r['share_token']);
          $titleTxt = trim($r['title']);
          if ($titleTxt === '') {
              $firstLine = preg_split('/\r\n|\r|\n/', trim($r['content']));
              $titleTxt = mb_substr($firstLine[0], 0, 40, 'UTF-8');
              if ($titleTxt === '') $titleTxt = '未命名记录';
          }
      ?>
      <article class="rec-card"
               data-id="<?php echo $rid; ?>"
               data-share="<?php echo $shareOn ? e($r['share_token']) : ''; ?>">
        <div class="rec-top">
          <span class="rec-time">🕘 <?php echo e(fmt_time($r['happened_at'])); ?></span>
        </div>
        <h3 class="rec-title" style="margin-top:8px">
          <a class="rt-link" href="<?php echo e(url('view.php?id=' . $rid)); ?>">
            <span class="rt-text"><?php echo e($titleTxt); ?></span>
            <i class="rt-arrow">›</i>
          </a>
        </h3>
        <p class="rec-body clamp"><?php echo e(mb_substr(trim($r['content']), 0, 300, 'UTF-8')); ?><?php echo mb_strlen($r['content'], 'UTF-8') > 300 ? '…' : ''; ?></p>

        <?php if ($r['customer'] !== '' || $r['related_people'] !== '' || $r['location'] !== '' || $myatts): ?>
        <div class="rec-meta">
          <?php foreach (customer_list($r['customer']) as $cus): ?>
            <span class="chip chip-cus"><span>🏢</span><span class="cl"><?php echo e($cus); ?></span></span>
          <?php endforeach; ?>
          <?php if ($r['location'] !== ''): ?>
            <span class="chip"><span>📍</span><span class="cl"><?php echo e($r['location']); ?></span></span>
          <?php endif; ?>
          <?php if ($r['related_people'] !== ''): ?>
            <span class="chip"><span>👥</span><span class="cl"><?php echo e($r['related_people']); ?></span></span>
          <?php endif; ?>
          <?php if ($myatts): ?>
            <span class="chip"><span>📎</span><span><?php echo count($myatts); ?> 个附件</span></span>
          <?php endif; ?>
          <?php if ($shareOn): ?>
            <span class="chip share-on"><span>🔗</span><span>已分享</span></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($thumbs): ?>
        <div class="thumb-row">
          <?php foreach ($thumbs as $a): ?>
            <a class="thumb" href="<?php echo e(url('view.php?id=' . $rid)); ?>">
              <img src="<?php echo e(url('file.php?a=' . (int)$a['id'] . '&thumb=1')); ?>" alt="" loading="lazy">
            </a>
          <?php endforeach; ?>
          <?php if ($imgCount > count($thumbs)): ?>
            <span class="thumb more">+<?php echo $imgCount - count($thumbs); ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="rec-actions">
          <a class="btn btn-sm" href="<?php echo e(url('view.php?id=' . $rid)); ?>">详情</a>
          <button type="button" class="btn btn-sm js-edit" data-id="<?php echo $rid; ?>">编辑</button>
          <a class="btn btn-sm" href="<?php echo e(url('export_pdf.php?id=' . $rid)); ?>" target="_blank">PDF</a>
          <button type="button" class="btn btn-sm js-share" data-id="<?php echo $rid; ?>">分享</button>
          <button type="button" class="btn btn-sm btn-danger js-del" data-id="<?php echo $rid; ?>">删除</button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pager">
      <a class="pg<?php echo $page <= 1 ? ' dis' : ''; ?>" href="<?php echo e(page_url(array('page' => $page - 1))); ?>">上一页</a>
      <span class="pg on"><?php echo $page; ?></span>
      <span class="pg-info">/ <?php echo $pages; ?> 页</span>
      <a class="pg<?php echo $page >= $pages ? ' dis' : ''; ?>" href="<?php echo e(page_url(array('page' => $page + 1))); ?>">下一页</a>
    </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<!-- 悬浮新增（手机） -->
<button type="button" class="fab" id="btnAddFab" aria-label="新增记录">＋</button>

<!-- ============ 新增 / 编辑 弹窗 ============ -->
<div class="modal" id="recModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3 id="recModalTitle">新增记录</h3>
      <span class="mh-spacer"></span>
      <button type="button" class="icon-btn" data-close aria-label="关闭">✕</button>
    </div>
    <div class="modal-body">
      <form id="recForm" autocomplete="off">
        <input type="hidden" name="id" id="f_id" value="">
        <div class="form-grid two">
          <div class="field">
            <label for="f_happened_at">工作时间 <span class="req">*</span></label>
            <input type="datetime-local" name="happened_at" id="f_happened_at" required>
          </div>
          <div class="field">
            <label for="f_title">工作标题</label>
            <input type="text" name="title" id="f_title" maxlength="120" placeholder="一句话概括（可留空）">
          </div>
        </div>

        <div class="field" style="margin-top:13px">
          <label for="f_content">工作内容 <span class="req">*</span></label>
          <textarea name="content" id="f_content" required placeholder="记录做了什么、过程与结果…"></textarea>
        </div>

        <div class="form-grid three" style="margin-top:13px">
          <div class="field">
            <label>涉及客户 <span class="hint">可多选 / 可手填</span></label>
            <div class="tagsel" id="cusPicker">
              <div class="tagsel-box" id="cusBox">
                <span class="tagsel-tags" id="cusTags"></span>
                <input type="text" class="tagsel-input" id="cusSearch"
                       placeholder="点选或输入客户名" autocomplete="off">
              </div>
              <div class="tagsel-menu" id="cusMenu"></div>
            </div>
            <input type="hidden" name="customer" id="f_customer" value="">
          </div>
          <div class="field">
            <label for="f_people">相关人员</label>
            <input type="text" name="related_people" id="f_people" maxlength="255" placeholder="张三、李四（逗号分隔）">
          </div>
          <div class="field">
            <label for="f_location">地点</label>
            <input type="text" name="location" id="f_location" maxlength="120" placeholder="如：3号会议室 / 客户现场">
          </div>
        </div>

        <div class="field" style="margin-top:13px">
          <label>附件 <span class="hint">图片 / 视频 / 录音 / 文档，可多选</span></label>
          <div class="exist-files" id="existFiles"></div>
          <input type="file" name="files[]" id="f_files" multiple
                 accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z">
          <div class="pick-list" id="pickList"></div>
        </div>
      </form>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-close>取消</button>
      <button type="button" class="btn btn-primary" id="btnSave">保存</button>
    </div>
  </div>
</div>

<!-- ============ 分享弹窗 ============ -->
<div class="modal" id="shareModal">
  <div class="modal-card" style="max-width:560px">
    <div class="modal-head">
      <h3>分享这条记录</h3>
      <span class="mh-spacer"></span>
      <button type="button" class="icon-btn" data-close aria-label="关闭">✕</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-info">
        <span>🔗</span>
        <span>开启后，任何拿到链接的人都能<b>只读</b>查看这条记录及附件（无需登录）。可随时关闭。</span>
      </div>
      <div class="field">
        <label>分享链接</label>
        <input type="text" id="shareUrl" readonly>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn" id="btnCopyShare">复制链接</button>
        <a class="btn" id="btnOpenShare" href="#" target="_blank">打开看看</a>
        <button type="button" class="btn btn-danger" id="btnUnshare">关闭分享</button>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-close>完成</button>
    </div>
  </div>
</div>

<script>window.WD_CUSTOMERS = <?php echo json_encode($custOptions, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>window.WD_UPLOAD_MAX = <?php echo (int)min(MAX_UPLOAD_SIZE, effective_upload_max()); ?>;</script>
<script>
(function () {
  var WD = window.WD;
  var $ = WD.$;

  /* ---------- 筛选面板 ---------- */
  var ft = $('#filterToggle'), fp = $('#filterPanel');
  if (ft && fp) {
    if (fp.classList.contains('show')) ft.classList.add('open');
    ft.addEventListener('click', function () {
      fp.classList.toggle('show');
      ft.classList.toggle('open');
    });
  }

  /* ---------- 本地时间默认值 ---------- */
  function localNow() {
    var d = new Date(), p = function (n) { return n < 10 ? '0' + n : '' + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
         + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
  }

  /* ---------- 打开发起弹窗 ---------- */
  var modal = $('#recModal');
  var form = $('#recForm');
  var existBox = $('#existFiles');
  var pickList = $('#pickList');
  var fileInput = $('#f_files');
  var removed = [];   // 待删除的附件 id

  /* ---------- 待上传文件：累加式选择，后一次选择不会覆盖前一次 ---------- */
  var pickedFiles = [];
  var UPLOAD_MAX = window.WD_UPLOAD_MAX || (2 * 1024 * 1024);

  function fileKey(f) {
    return f.name + '|' + f.size + '|' + (f.lastModified || 0);
  }

  function renderPicked() {
    pickList.innerHTML = '';
    if (!pickedFiles.length) return;
    pickedFiles.forEach(function (f, i) {
      var el = document.createElement('div');
      el.className = 'pick-item';
      el.innerHTML = '<span>📎</span><span class="pi-name">' + WD.escapeHtml(f.name)
        + '</span><span class="pi-size">' + WD.fmtSize(f.size) + '</span>'
        + '<button type="button" class="pi-del" aria-label="移除">✕</button>';
      el.querySelector('.pi-del').addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        pickedFiles.splice(i, 1);
        renderPicked();
      });
      pickList.appendChild(el);
    });
    var tip = document.createElement('div');
    tip.className = 'pick-tip';
    tip.textContent = '待上传 ' + pickedFiles.length + ' 个文件（可继续点「选择文件」追加）';
    pickList.appendChild(tip);
  }

  /* ---------- 涉及客户：多选标签下拉 ---------- */
  var cusPicker = WD.createTagSelect({
    wrap:    $('#cusPicker'),
    search:  $('#cusSearch'),
    tags:    $('#cusTags'),
    menu:    $('#cusMenu'),
    hidden:  $('#f_customer'),
    options: window.WD_CUSTOMERS || [],
    max:     12
  });

  function resetForm() {
    form.reset();
    if (cusPicker) cusPicker.clear();
    $('#f_id').value = '';
    existBox.innerHTML = '';
    pickedFiles = [];
    renderPicked();
    removed = [];
    $('#recModalTitle').textContent = '新增记录';
    $('#f_happened_at').value = localNow();
    $('#btnSave').disabled = false;
    $('#btnSave').textContent = '保存';
  }

  function openAdd() {
    resetForm();
    WD.openModal(modal);
  }

  function openEdit(id) {
    resetForm();
    $('#btnSave').disabled = true;
    $('#btnSave').textContent = '加载中…';
    WD.openModal(modal);
    WD.getJson('api/record_get.php?id=' + encodeURIComponent(id)).then(function (res) {
      if (!res.ok) { WD.toast(res.msg || '读取失败', 'err'); WD.closeModal(modal); return; }
      var r = res.record;
      $('#recModalTitle').textContent = '编辑记录';
      $('#f_id').value = r.id;
      $('#f_happened_at').value = r.happened_local;
      $('#f_title').value = r.title || '';
      $('#f_content').value = r.content || '';
      if (cusPicker) cusPicker.setValue(r.customer || '');
      $('#f_people').value = r.related_people || '';
      $('#f_location').value = r.location || '';
      renderExist(res.attachments || []);
      $('#btnSave').disabled = false;
      $('#btnSave').textContent = '保存';
    }).catch(function (e) {
      WD.toast((e && e.msg) || '加载失败', 'err');
      $('#btnSave').disabled = false;
      $('#btnSave').textContent = '保存';
    });
  }

  function renderExist(list) {
    existBox.innerHTML = '';
    if (!list.length) return;
    var head = document.createElement('div');
    head.style.cssText = 'font-size:12px;color:var(--muted);margin-bottom:2px';
    head.textContent = '已有附件（点 ✕ 移除）';
    existBox.appendChild(head);
    list.forEach(function (a) {
      var row = document.createElement('div');
      row.className = 'exist-file';
      var ico = { image: '🖼', video: '🎬', audio: '🎙', doc: '📄' }[a.kind] || '📎';
      row.innerHTML = '<span class="ef-ico">' + ico + '</span>'
        + '<span class="ef-name">' + WD.escapeHtml(a.orig_name) + '</span>'
        + '<span class="ef-size">' + WD.fmtSize(a.size) + '</span>'
        + '<button type="button" class="ef-del" title="移除">✕</button>';
      row.querySelector('.ef-del').addEventListener('click', function () {
        var id = parseInt(a.id, 10);
        var idx = removed.indexOf(id);
        if (idx > -1) {
          removed.splice(idx, 1);
          row.classList.remove('removing');
        } else {
          removed.push(id);
          row.classList.add('removing');
        }
      });
      existBox.appendChild(row);
    });
  }

  /* ---------- 选择文件：累加到 pickedFiles，不再覆盖之前选的 ---------- */
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var files = fileInput.files;
      if (!files || !files.length) return;

      var keys = {};
      pickedFiles.forEach(function (f) { keys[fileKey(f)] = 1; });

      var added = 0, dup = 0, tooBig = [];
      for (var i = 0; i < files.length; i++) {
        var f = files[i];
        if (f.size > UPLOAD_MAX) {
          tooBig.push(f.name + '（' + WD.fmtSize(f.size) + '）');
          continue;
        }
        var k = fileKey(f);
        if (keys[k]) { dup++; continue; }
        keys[k] = 1;
        pickedFiles.push(f);
        added++;
      }

      // 清空 input，这样下次还能重复选同一个文件
      try { fileInput.value = ''; } catch (e) {}

      renderPicked();

      if (tooBig.length) {
        alert('以下文件超过服务器上限 ' + WD.fmtSize(UPLOAD_MAX) + '，已跳过：\n\n'
          + tooBig.join('\n')
          + '\n\n要上传更大的文件，请按「部署说明」调大 php.ini 里的'
          + ' upload_max_filesize 和 post_max_size，然后重启 PHP。');
      } else if (added === 0 && dup > 0) {
        WD.toast('这些文件已经在列表里了');
      } else if (added > 0) {
        WD.toast('已添加 ' + added + ' 个，共 ' + pickedFiles.length + ' 个', 'ok');
      }
    });
  }

  /* ---------- 保存 ---------- */
  var saving = false;
  $('#btnSave').addEventListener('click', function () {
    if (saving) return;
    var happened = $('#f_happened_at').value;
    var content = $('#f_content').value.trim();
    if (!happened) { WD.toast('请选择工作时间', 'err'); $('#f_happened_at').focus(); return; }
    if (!content) { WD.toast('请填写工作内容', 'err'); $('#f_content').focus(); return; }

    var fd = new FormData();
    fd.append('id', $('#f_id').value || '');
    fd.append('happened_at', happened);
    fd.append('title', $('#f_title').value);
    fd.append('content', content);
    fd.append('customer', cusPicker ? cusPicker.getValue() : $('#f_customer').value);
    fd.append('related_people', $('#f_people').value);
    fd.append('location', $('#f_location').value);
    removed.forEach(function (id) { fd.append('deleted_attach[]', id); });

    if (pickedFiles.length) {
      pickedFiles.forEach(function (f) { fd.append('files[]', f); });
    }

    saving = true;
    var btn = $('#btnSave');
    btn.disabled = true;
    btn.textContent = '保存中…';

    WD.request('api/record_save.php', fd, function (loaded, total) {
      var pct = total ? Math.floor(loaded / total * 100) : 0;
      btn.textContent = '上传中 ' + pct + '%';
    }).then(function (res) {
      saving = false;
      btn.disabled = false;
      btn.textContent = '保存';
      if (res.ok) {
        WD.toast(res.msg || '已保存', 'ok');
        var warns = res.warnings || [];
        if (warns.length) {
          // 附件有失败：必须明确告知，否则用户会以为已经传上去了
          alert('记录已保存，但有 ' + warns.length + ' 个附件没有上传成功：\n\n'
            + warns.join('\n')
            + '\n\n本服务器当前单个文件上限是 ' + WD.fmtSize(UPLOAD_MAX) + '。'
            + '\n如需上传更大的文件，请按「部署说明」调大 php.ini 后重启。');
        }
        WD.closeModal(modal);
        setTimeout(function () { location.reload(); }, warns.length ? 900 : 420);
      } else {
        WD.toast(res.msg || '保存失败', 'err');
      }
    }).catch(function (e) {
      saving = false;
      btn.disabled = false;
      btn.textContent = '保存';
      WD.toast((e && e.msg) || '保存失败', 'err');
    });
  });

  /* ---------- 按钮绑定 ---------- */
  ['#btnAddDesktop', '#btnAddFab'].forEach(function (sel) {
    var b = $(sel);
    if (b) b.addEventListener('click', openAdd);
  });

  WD.$$('.js-edit').forEach(function (b) {
    b.addEventListener('click', function () { openEdit(b.getAttribute('data-id')); });
  });

  WD.$$('.js-del').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-id');
      if (!confirm('确定删除这条记录？附件也会一并删除，且无法恢复。')) return;
      WD.deleteRecord(id, function () {
        var card = b.closest('.rec-card');
        if (card) {
          card.style.transition = 'opacity .18s, transform .18s';
          card.style.opacity = '0';
          card.style.transform = 'scale(.98)';
          setTimeout(function () { location.reload(); }, 220);
        } else {
          location.reload();
        }
      });
    });
  });

  /* ---------- 分享 ---------- */
  var shareModal = $('#shareModal');
  var currentShareId = null;

  function showShare(url) {
    $('#shareUrl').value = url;
    $('#btnOpenShare').setAttribute('href', url);
    WD.openModal(shareModal);
  }

  WD.$$('.js-share').forEach(function (b) {
    b.addEventListener('click', function () {
      var card = b.closest('.rec-card');
      currentShareId = b.getAttribute('data-id');
      var token = card ? card.getAttribute('data-share') : '';
      if (token) {
        showShare(WD.root + 'share.php?t=' + token);
      } else {
        b.disabled = true;
        WD.toggleShare(currentShareId, 'on', function (res) {
          b.disabled = false;
          if (card) card.setAttribute('data-share', res.token);
          showShare(res.url);
          WD.toast('分享已开启', 'ok');
          var chip = document.createElement('span');
          chip.className = 'chip share-on';
          chip.innerHTML = '<span>🔗</span><span>已分享</span>';
          var meta = card.querySelector('.rec-meta');
          if (meta) meta.appendChild(chip);
        });
      }
    });
  });

  var bc = $('#btnCopyShare');
  if (bc) {
    bc.addEventListener('click', function () {
      var v = $('#shareUrl').value;
      if (!v) return;
      WD.copyText(v).then(function () { WD.toast('链接已复制', 'ok'); })
        .catch(function () { WD.toast('复制失败，请长按输入框手动复制', 'err'); });
    });
  }

  var bu = $('#btnUnshare');
  if (bu) {
    bu.addEventListener('click', function () {
      if (!currentShareId) return;
      if (!confirm('关闭后，已发出的分享链接将立即失效。')) return;
      WD.toggleShare(currentShareId, 'off', function () {
        WD.toast('分享已关闭', 'ok');
        WD.closeModal(shareModal);
        setTimeout(function () { location.reload(); }, 400);
      });
    });
  }

  /* ---------- 快捷入口：?new=1 直接开新增，?edit=ID 直接开编辑 ---------- */
  try {
    var sp = new URLSearchParams(location.search);
    if (sp.get('new') === '1') {
      openAdd();
    }
    var eid = sp.get('edit');
    if (eid) {
      openEdit(eid);
    }
  } catch (e) { /* 老浏览器忽略 */ }

})();
</script>
<?php render_footer(); ?>
