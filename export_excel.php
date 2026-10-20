<?php
/**
 * 导出 Excel（Excel 2003 XML 格式，零依赖、中文不乱码、.xls 可直接双击打开）
 * 支持筛选参数：kw / from / to（与首页一致）
 */
require_once __DIR__ . '/lib/auth.php';
$me = require_login();

$kw     = input('kw');
$f_from = input('from');
$f_to   = input('to');
$ids    = input('ids');   // 可选：逗号分隔的记录 ID

$where = array();
$args  = array();

if ($ids !== '' && preg_match('/^[\d,]+$/', $ids)) {
    $idArr = array_filter(array_map('intval', explode(',', $ids)));
    if ($idArr) {
        $where[] = 'id IN (' . implode(',', array_fill(0, count($idArr), '?')) . ')';
        foreach ($idArr as $v) $args[] = $v;
    }
}
if ($kw !== '') {
    $like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $kw) . '%';
    $where[] = '(title LIKE ? OR content LIKE ? OR related_people LIKE ? OR location LIKE ?)';
    array_push($args, $like, $like, $like, $like);
}
if ($f_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) {
    $where[] = 'happened_at >= ?';
    $args[]  = $f_from . ' 00:00:00';
}
if ($f_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to)) {
    $where[] = 'happened_at <= ?';
    $args[]  = $f_to . ' 23:59:59';
}
$w = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$st = db()->prepare('SELECT * FROM records' . $w . ' ORDER BY happened_at DESC, id DESC');
$st->execute($args);

// ---- 附件按记录聚合 ----
$attMap = array();
$rows = array();
while ($r = $st->fetch()) {
    $rows[] = $r;
}

if ($rows) {
    $ridArr = array();
    foreach ($rows as $r) $ridArr[] = (int)$r['id'];
    $ph = implode(',', array_fill(0, count($ridArr), '?'));
    $st2 = db()->prepare('SELECT * FROM attachments WHERE record_id IN (' . $ph . ') ORDER BY id ASC');
    $st2->execute($ridArr);
    foreach ($st2->fetchAll() as $a) {
        $attMap[(int)$a['record_id']][] = $a;
    }
}

/* ---------------- 输出 ---------------- */
$filename = '工作留痕_' . date('Ymd_His') . '.xls';
$asciiName = 'worklog_' . date('Ymd_His') . '.xls';

while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Cache-Control: max-age=0');
header('Pragma: public');

/** XML 单元格文本转义：保留换行 */
function xcell($s)
{
    $s = str_replace(array("\r\n", "\r"), "\n", (string)$s);
    $s = str_replace('&', '&amp;', $s);
    $s = str_replace('<', '&lt;', $s);
    $s = str_replace('>', '&gt;', $s);
    $s = str_replace('"', '&quot;', $s);
    $s = str_replace("\n", '&#10;', $s);
    return $s;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Title>工作留痕记录</Title>
  <Author><?php echo xcell(APP_NAME); ?></Author>
  <Created><?php echo date('Y-m-d\TH:i:s\Z'); ?></Created>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Top"/>
   <Font ss:FontName="微软雅黑" ss:Size="10"/>
  </Style>
  <Style ss:ID="hdr">
   <Alignment ss:Vertical="Center" ss:Horizontal="Center" ss:WrapText="1"/>
   <Font ss:FontName="微软雅黑" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#2563EB" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1D4ED8"/>
   </Borders>
  </Style>
  <Style ss:ID="cell">
   <Alignment ss:Vertical="Top" ss:WrapText="1"/>
   <Font ss:FontName="微软雅黑" ss:Size="10"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6E9EF"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6E9EF"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6E9EF"/>
   </Borders>
  </Style>
  <Style ss:ID="cellc">
   <Alignment ss:Vertical="Top" ss:Horizontal="Center" ss:WrapText="1"/>
   <Font ss:FontName="微软雅黑" ss:Size="10"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6E9EF"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6E9EF"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6E9EF"/>
   </Borders>
  </Style>
 </Styles>

 <Worksheet ss:Name="工作留痕">
  <Table ss:DefaultRowHeight="15">
   <Column ss:Width="38"/>
   <Column ss:Width="110"/>
   <Column ss:Width="150"/>
   <Column ss:Width="330"/>
   <Column ss:Width="110"/>
   <Column ss:Width="110"/>
   <Column ss:Width="100"/>
   <Column ss:Width="52"/>
   <Column ss:Width="230"/>
   <Column ss:Width="110"/>
   <Column ss:Width="60"/>

   <Row ss:Height="26">
    <Cell ss:StyleID="hdr"><Data ss:Type="String">序号</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">工作时间</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">工作标题</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">工作内容</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">涉及客户</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">相关人员</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">地点</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">附件数</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">附件清单</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">记录时间</Data></Cell>
    <Cell ss:StyleID="hdr"><Data ss:Type="String">分享</Data></Cell>
   </Row>
<?php
$i = 0;
foreach ($rows as $r):
    $i++;
    $myAtts = isset($attMap[(int)$r['id']]) ? $attMap[(int)$r['id']] : array();
    $attNames = array();
    foreach ($myAtts as $a) {
        $attNames[] = '[' . kind_label($a['kind']) . '] ' . $a['orig_name'] . ' (' . fmt_size($a['size']) . ')';
    }
    $shareTxt = !empty($r['share_token']) ? '已分享' : '';
?>
   <Row>
    <Cell ss:StyleID="cellc"><Data ss:Type="Number"><?php echo $i; ?></Data></Cell>
    <Cell ss:StyleID="cellc"><Data ss:Type="String"><?php echo xcell(date('Y-m-d H:i', strtotime($r['happened_at']))); ?></Data></Cell>
    <Cell ss:StyleID="cell"><Data ss:Type="String"><?php echo xcell($r['title']); ?></Data></Cell>
    <Cell ss:StyleID="cell"><Data ss:Type="String"><?php echo xcell($r['content']); ?></Data></Cell>
    <Cell ss:StyleID="cell"><Data ss:Type="String"><?php echo xcell($r['customer']); ?></Data></Cell>
    <Cell ss:StyleID="cell"><Data ss:Type="String"><?php echo xcell($r['related_people']); ?></Data></Cell>
    <Cell ss:StyleID="cell"><Data ss:Type="String"><?php echo xcell($r['location']); ?></Data></Cell>
    <Cell ss:StyleID="cellc"><Data ss:Type="Number"><?php echo count($myAtts); ?></Data></Cell>
    <Cell ss:StyleID="cell"><Data ss:Type="String"><?php echo xcell(implode("\n", $attNames)); ?></Data></Cell>
    <Cell ss:StyleID="cellc"><Data ss:Type="String"><?php echo xcell($r['created_at']); ?></Data></Cell>
    <Cell ss:StyleID="cellc"><Data ss:Type="String"><?php echo xcell($shareTxt); ?></Data></Cell>
   </Row>
<?php endforeach; ?>
   <Row>
    <Cell ss:StyleID="cellc" ss:MergeAcross="10">
     <Data ss:Type="String">共 <?php echo count($rows); ?> 条记录 · 导出于 <?php echo date('Y-m-d H:i:s'); ?></Data>
    </Cell>
   </Row>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>1</SplitHorizontal>
   <TopRowBottomPane>1</TopRowBottomPane>
   <ActivePane>2</ActivePane>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
</Workbook>
