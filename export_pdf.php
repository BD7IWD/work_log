<?php
/**
 * 单条记录导出 PDF（TCPDF + 内置中文字体 stsongstdlight）
 */
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/upload.php';
$me = require_login();

$id = input_int('id', 0);
if ($id <= 0) {
    exit('缺少记录 ID');
}

$st = db()->prepare('SELECT * FROM records WHERE id = ?');
$st->execute(array($id));
$rec = $st->fetch();
if (!$rec) {
    exit('记录不存在');
}
if (!can_edit_record($rec, $me)) {
    exit('无权导出这条记录');
}

$st = db()->prepare('SELECT * FROM attachments WHERE record_id = ? ORDER BY id ASC');
$st->execute(array($id));
$atts = $st->fetchAll();

/* ---------------- TCPDF ---------------- */
if (!defined('K_PATH_CACHE')) {
    define('K_PATH_CACHE', rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR);
}
require_once __DIR__ . '/lib/tcpdf/tcpdf.php';

class WorkLogPdf extends TCPDF
{
    public $docTitle = '';

    public function Header()
    {
        // 页眉留空，标题在正文里排版
    }

    public function Footer()
    {
        $this->SetY(-13);
        $this->SetFont('stsongstdlight', '', 8);
        $this->SetTextColor(150, 156, 170);
        $this->Cell(0, 5, '第 ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages() . ' 页'
            . '          导出于 ' . date('Y-m-d H:i'), 0, 0, 'C');
    }
}

$titleTxt = trim($rec['title']);
if ($titleTxt === '') {
    $first = preg_split('/\r\n|\r|\n/', trim($rec['content']));
    $titleTxt = mb_substr($first[0], 0, 40, 'UTF-8');
    if ($titleTxt === '') $titleTxt = '工作记录';
}

$pdf = new WorkLogPdf('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(APP_NAME);
$pdf->SetAuthor(APP_NAME);
$pdf->SetTitle($titleTxt);
$pdf->SetSubject('工作留痕记录');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetFooterMargin(16);

$pdf->AddPage();

$pageW = $pdf->getPageWidth();
$left  = 15;
$width = $pageW - 30;   // 180mm

/* ---------------- 标题 ---------------- */
$pdf->SetTextColor(15, 23, 42);
$pdf->SetFont('stsongstdlight', '', 17);
$pdf->MultiCell($width, 9, $titleTxt, 0, 'L', false, 1, $left, 15);

// 分隔线
$pdf->SetDrawColor(37, 99, 235);
$pdf->SetLineWidth(0.7);
$y = $pdf->GetY() + 1.5;
$pdf->Line($left, $y, $left + $width, $y);
$pdf->SetLineWidth(0.2);
$pdf->SetY($y + 5);

/* ---------------- 基本信息 ---------------- */
$pdf->SetFont('stsongstdlight', '', 10);
$pdf->SetFillColor(244, 247, 252);
$pdf->SetTextColor(70, 78, 92);

$rowsInfo = array(
    '工作时间' => date('Y-m-d H:i', strtotime($rec['happened_at'])),
);
if (trim($rec['customer']) !== '')       $rowsInfo['涉及客户'] = $rec['customer'];
if (trim($rec['related_people']) !== '') $rowsInfo['相关人员'] = $rec['related_people'];
if (trim($rec['location']) !== '')       $rowsInfo['地点']     = $rec['location'];
$rowsInfo['记录时间'] = $rec['created_at'];
if ($rec['updated_at'] && $rec['updated_at'] !== $rec['created_at']) {
    $rowsInfo['最后修改'] = $rec['updated_at'];
}

$labelW = 26;
foreach ($rowsInfo as $k => $v) {
    $x0 = $pdf->GetX();
    $y0 = $pdf->GetY();
    // 标签
    $pdf->SetFillColor(238, 243, 253);
    $pdf->SetTextColor(55, 65, 81);
    $pdf->SetFont('stsongstdlight', '', 10);
    $pdf->Cell($labelW, 7.5, $k, 0, 0, 'L', true);
    // 值（自动换行）
    $pdf->SetFillColor(250, 251, 253);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetXY($x0 + $labelW, $y0);
    $pdf->MultiCell($width - $labelW, 7.5, $v, 0, 'L', true, 1, $x0 + $labelW, $y0);
    $pdf->SetX($x0);
}

$pdf->SetY($pdf->GetY() + 4);

/* ---------------- 工作内容 ---------------- */
$pdf->SetTextColor(15, 23, 42);
$pdf->SetFont('stsongstdlight', '', 11.5);
$pdf->Cell($width, 7, '工作内容', 0, 1, 'L');
$pdf->SetDrawColor(225, 229, 237);
$pdf->SetLineWidth(0.3);
$pdf->Line($left, $pdf->GetY(), $left + $width, $pdf->GetY());
$pdf->SetY($pdf->GetY() + 2.5);

$pdf->SetFont('stsongstdlight', '', 10.8);
$pdf->SetTextColor(35, 42, 54);
// 注意：第 14 个参数是 $maxh（最大高度），必须传 0 表示不限制。
// 之前误传了行高 6.6，导致整段工作内容被裁到只剩一行。
$pdf->MultiCell($width, 6.6, $rec['content'], 0, 'L', false, 1, $left, $pdf->GetY(), true, 0, false, true, 0, 'T');

$pdf->SetY($pdf->GetY() + 5);

/* ---------------- 附件清单 ---------------- */
if ($atts) {
    $pdf->SetFont('stsongstdlight', '', 11.5);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell($width, 7, '附件（' . count($atts) . ' 个）', 0, 1, 'L');
    $pdf->SetDrawColor(225, 229, 237);
    $pdf->Line($left, $pdf->GetY(), $left + $width, $pdf->GetY());
    $pdf->SetY($pdf->GetY() + 2);

    $wNo = 12; $wKind = 20; $wSize = 26;
    $wName = $width - $wNo - $wKind - $wSize;

    // 表头
    $pdf->SetFont('stsongstdlight', '', 9);
    $pdf->SetFillColor(37, 99, 235);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($wNo, 7, '序号', 1, 0, 'C', true);
    $pdf->Cell($wKind, 7, '类型', 1, 0, 'C', true);
    $pdf->Cell($wName, 7, '文件名', 1, 0, 'L', true);
    $pdf->Cell($wSize, 7, '大小', 1, 1, 'C', true);

    $pdf->SetTextColor(45, 55, 70);
    $pdf->SetFont('stsongstdlight', '', 8.8);
    $n = 0;
    foreach ($atts as $a) {
        $n++;
        $pdf->SetFillColor(250, 251, 253);
        $pdf->Cell($wNo, 6.8, (string)$n, 1, 0, 'C', true);
        $pdf->Cell($wKind, 6.8, kind_label($a['kind']), 1, 0, 'C', true);
        $pdf->Cell($wName, 6.8, mb_strimwidth($a['orig_name'], 0, 62, '…', 'UTF-8'), 1, 0, 'L', true);
        $pdf->Cell($wSize, 6.8, fmt_size($a['size']), 1, 1, 'C', true);
    }
    $pdf->SetY($pdf->GetY() + 5);
}

/* ---------------- 图片预览（最多 4 张，单张 < 12MB） ---------------- */
$imgs = array();
foreach ($atts as $a) {
    if ($a['kind'] === 'image' && $a['size'] <= 12 * 1024 * 1024) {
        $p = attach_path($a);
        if (is_file($p)) $imgs[] = $p;
    }
}
$imgs = array_slice($imgs, 0, 4);

if ($imgs) {
    $pdf->SetFont('stsongstdlight', '', 11.5);
    $pdf->SetTextColor(15, 23, 42);
    if ($pdf->GetY() > $pdf->getPageHeight() - 60) $pdf->AddPage();
    $pdf->Cell($width, 7, '图片预览', 0, 1, 'L');
    $pdf->SetY($pdf->GetY() + 1);

    foreach ($imgs as $p) {
        $info = @getimagesize($p);
        if (!$info) continue;
        $iw = $info[0]; $ih = $info[1];
        if ($iw <= 0 || $ih <= 0) continue;

        $maxW = $width;
        $maxH = 95;
        $w = $maxW;
        $h = $ih * $maxW / $iw;
        if ($h > $maxH) {
            $h = $maxH;
            $w = $iw * $maxH / $ih;
        }

        // 空间不够就换页
        if ($pdf->GetY() + $h + 8 > $pdf->getPageHeight() - 20) {
            $pdf->AddPage();
        }

        $yNow = $pdf->GetY();
        try {
            $pdf->Image($p, $left, $yNow, $w, $h, '', '', '', true, 300, '', false, false, 0, false, false, false);
        } catch (Exception $e) {
            // 单张图失败不影响整份文档
        }
        $pdf->SetY($yNow + $h + 3);
    }
}

/* ---------------- 输出 ---------------- */
$fn = '工作留痕_' . date('Ymd_Hi', strtotime($rec['happened_at'])) . '_' . $id . '.pdf';

while (ob_get_level() > 0) { @ob_end_clean(); }
$pdf->Output($fn, 'I');
exit;
