<?php
// خروجی Word (قابل ویرایش) از «سازنده گزارش»: هر صفحه‌ی گزارش یک بخش (Section) افقی A4 با سربرگ/پاورقی مخصوص خودش
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/jsondb.php';
require_once __DIR__ . '/includes/docx.php';
requireLoginPage();

const RBX_PW = 1122, RBX_SIDE = 24;            // ابعاد بوم سازنده (پیکسل)
const RBX_PAGE_W = 16838, RBX_PAGE_H = 11906;  // A4 افقی (twip)
const RBX_MAR_SIDE = 567, RBX_MAR_TOP = 1100, RBX_MAR_BOT = 1000;
const RBX_USABLE = RBX_PAGE_W - 2 * RBX_MAR_SIDE;
const RBX_NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
    . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
    . 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
    . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
    . 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

$me = currentUser();
$store = 'report_' . substr(sha1((string)($me['username'] ?? 'x')), 0, 16);
$rep = jsonRead($store);
$footerDef = ['line' => true, 'num' => true, 'showText' => true, 'text' => 'اداره ارزیابی خبرگزاری دانشجویان ایران (ایسنا)'];
$title = (string)($rep['title'] ?? 'گزارش ارزیابی');
$footer = array_merge($footerDef, is_array($rep['footer'] ?? null) ? $rep['footer'] : []);
$pageTitles = is_array($rep['pageTitles'] ?? null) ? $rep['pageTitles'] : [];
$pageNums = is_array($rep['pageNums'] ?? null) ? $rep['pageNums'] : [];
$items = is_array($rep['items'] ?? null) ? $rep['items'] : [];
if (!$items) { http_response_code(400); die('گزارش خالی است؛ ابتدا آیتم‌هایی به گزارش اضافه کنید.'); }

$pages = max(1, (int)($rep['pages'] ?? 1));
foreach ($items as $it) { $pages = max($pages, (int)($it['page'] ?? 0) + 1); }
$pages = min($pages, 20);

/* ---------- ابزارهای XML ---------- */
function rbxToFa(int $n): string { return strtr((string)$n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']); }

function rbxRun(string $text, array $o = []): string
{
    $sz = (int)($o['size'] ?? 22);
    $rpr = '<w:rPr><w:rFonts w:ascii="Tahoma" w:hAnsi="Tahoma" w:cs="B Nazanin"/>'
        . (!empty($o['bold']) ? '<w:b/><w:bCs/>' : '')
        . (!empty($o['color']) ? '<w:color w:val="' . $o['color'] . '"/>' : '')
        . '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>' . '<w:rtl/></w:rPr>';
    return '<w:r>' . $rpr . '<w:t xml:space="preserve">' . docxEscape($text) . '</w:t></w:r>';
}

// $o: rtl(bool)، jc(center/left)، line(exact twip)، before/after، shd(fill)، ind(['left'=>..,'right'=>..])، tabs(xml)، sect(xml)
function rbxPara(string $runs, array $o = []): string
{
    $ppr = '';
    if (!empty($o['shd'])) $ppr .= '<w:shd w:val="clear" w:color="auto" w:fill="' . $o['shd'] . '"/>';
    if (!empty($o['tabs'])) $ppr .= $o['tabs'];
    if (($o['rtl'] ?? true)) $ppr .= '<w:bidi/>';
    $sp = '<w:spacing w:before="' . (int)($o['before'] ?? 0) . '" w:after="' . (int)($o['after'] ?? 0) . '"';
    $sp .= isset($o['line']) ? ' w:line="' . (int)$o['line'] . '" w:lineRule="exact"/>' : ' w:line="276" w:lineRule="auto"/>';
    $ppr .= $sp;
    if (!empty($o['ind'])) $ppr .= '<w:ind w:left="' . (int)($o['ind']['left'] ?? 0) . '" w:right="' . (int)($o['ind']['right'] ?? 0) . '"/>';
    if (!empty($o['jc'])) $ppr .= '<w:jc w:val="' . $o['jc'] . '"/>';
    if (!empty($o['sect'])) $ppr .= $o['sect'];
    return '<w:p><w:pPr>' . $ppr . '</w:pPr>' . $runs . '</w:p>';
}

function rbxDrawing(string $rId, int $cx, int $cy, int $id, ?array $anchor = null): string
{
    $pic = '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>'
        . '<pic:nvPicPr><pic:cNvPr id="' . $id . '" name="img' . $id . '.png"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="' . $rId . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        . '</pic:pic></a:graphicData></a:graphic>';
    if ($anchor) { // تصویر شناور پشت متن (برای پس‌زمینه‌ی گرادیانت سربرگ)
        return '<w:r><w:drawing><wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="' . $id . '" behindDoc="1" locked="0" layoutInCell="1" allowOverlap="1">'
            . '<wp:simplePos x="0" y="0"/><wp:positionH relativeFrom="page"><wp:posOffset>' . (int)$anchor['x'] . '</wp:posOffset></wp:positionH>'
            . '<wp:positionV relativeFrom="page"><wp:posOffset>' . (int)$anchor['y'] . '</wp:posOffset></wp:positionV>'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:effectExtent l="0" t="0" r="0" b="0"/><wp:wrapNone/>'
            . '<wp:docPr id="' . $id . '" name="bg' . $id . '"/><wp:cNvGraphicFramePr/>' . $pic . '</wp:anchor></w:drawing></w:r>';
    }
    return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
        . '<wp:docPr id="' . $id . '" name="pic' . $id . '"/><wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
        . $pic . '</wp:inline></w:drawing></w:r>';
}

// گرادیانت افقی به‌صورت PNG (با GD). $stops: [[pos 0..1, [r,g,b]], ...]؛ $fade: درصد محوشدن دو لبه
function rbxGradientPng(int $w, int $h, array $stops, float $fade = 0.0): ?string
{
    if (!function_exists('imagecreatetruecolor')) return null;
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false); imagesavealpha($im, true);
    for ($x = 0; $x < $w; $x++) {
        $t = $w > 1 ? $x / ($w - 1) : 0;
        for ($i = 0; $i < count($stops) - 1; $i++) { if ($t <= $stops[$i + 1][0]) break; }
        [$p0, $c0] = $stops[$i]; [$p1, $c1] = $stops[min($i + 1, count($stops) - 1)];
        $k = $p1 > $p0 ? max(0, min(1, ($t - $p0) / ($p1 - $p0))) : 0;
        $c = [(int)round($c0[0] + ($c1[0] - $c0[0]) * $k), (int)round($c0[1] + ($c1[1] - $c0[1]) * $k), (int)round($c0[2] + ($c1[2] - $c0[2]) * $k)];
        $alpha = 0;
        if ($fade > 0) { $e = min($t, 1 - $t); if ($e < $fade) $alpha = (int)round(127 * (1 - $e / $fade)); }
        $col = imagecolorallocatealpha($im, $c[0], $c[1], $c[2], $alpha);
        imageline($im, $x, 0, $x, $h - 1, $col);
    }
    ob_start(); imagepng($im); $bin = ob_get_clean(); imagedestroy($im);
    return $bin ?: null;
}

/* ---------- ثبت تصاویر ---------- */
$media = [];      // نام فایل => محتوای باینری
$docRels = [];    // [id, type, target]
$imgSeq = 100;
function rbxRegisterImage(string $bin, array &$media, array &$docRels, int &$seq): array
{
    $info = @getimagesizefromstring($bin);
    if (!$info) return [null, 0, 0];
    $seq++;
    $name = 'image' . $seq . '.png';
    $media[$name] = $bin;
    $rId = 'rIdImg' . $seq;
    $docRels[] = [$rId, 'image', 'media/' . $name];
    return [$rId, (int)$info[0], (int)$info[1]];
}

/* ---------- تبدیل جدول HTML به جدول Word (تو در تو) ---------- */
function rbxHtmlTable(string $html, int $wTw, float $fs): string
{
    // تجزیه‌ی سبک با regex (بدون وابستگی به افزونه‌ی DOM)؛ ورودی همان جدولی است که خودِ سامانه ذخیره کرده
    $rows = [];
    preg_match_all('#<tr\b[^>]*>(.*?)</tr>#is', $html, $trs);
    foreach ($trs[1] as $trHtml) {
        $cells = [];
        if (preg_match_all('#<(td|th)\b[^>]*>(.*?)</\1>#is', $trHtml, $cs, PREG_SET_ORDER)) {
            foreach ($cs as $c) {
                $txt = html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', ' ', $c[2])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $cells[] = ['t' => trim(preg_replace('/\s+/u', ' ', $txt)), 'h' => strtolower($c[1]) === 'th'];
            }
        }
        if ($cells) $rows[] = $cells;
    }
    if (!$rows) return '';
    $cols = max(array_map('count', $rows));
    $colW = max(300, intdiv($wTw - 220, $cols));
    $size = max(10, min(40, (int)round(16 * $fs)));
    $b = '<w:tblBorders>';
    foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) $b .= '<w:' . $side . ' w:val="single" w:sz="4" w:space="0" w:color="BFC8DA"/>';
    $b .= '</w:tblBorders>';
    $x = '<w:tbl><w:tblPr><w:tblW w:w="' . ($colW * $cols) . '" w:type="dxa"/><w:bidiVisual/>' . $b . '<w:tblLayout w:type="fixed"/>'
        . '<w:tblCellMar><w:top w:w="20" w:type="dxa"/><w:left w:w="60" w:type="dxa"/><w:bottom w:w="20" w:type="dxa"/><w:right w:w="60" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';
    for ($i = 0; $i < $cols; $i++) $x .= '<w:gridCol w:w="' . $colW . '"/>';
    $x .= '</w:tblGrid>';
    foreach ($rows as $cells) {
        $x .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
        for ($i = 0; $i < $cols; $i++) {
            $c = $cells[$i] ?? ['t' => '', 'h' => false];
            $x .= '<w:tc><w:tcPr><w:tcW w:w="' . $colW . '" w:type="dxa"/>' . ($c['h'] ? '<w:shd w:val="clear" w:color="auto" w:fill="D9E2F3"/>' : '') . '</w:tcPr>'
                . rbxPara(rbxRun($c['t'], ['size' => $size, 'bold' => $c['h']]), ['jc' => 'center']) . '</w:tc>';
        }
        $x .= '</w:tr>';
    }
    return $x . '</w:tbl>';
}

/* ---------- محتوای یک کارت ---------- */
function rbxItemBlocks(array $it, int $wTw, int $hTw, array &$media, array &$docRels, int &$seq): string
{
    $fs = max(0.5, min(3.0, (float)($it['fs'] ?? 1)));
    $type = $it['type'] ?? '';
    $x = '';
    if ($type !== 'text' && trim((string)($it['title'] ?? '')) !== '') $x .= rbxPara(rbxRun($it['title'], ['size' => 22, 'bold' => true, 'color' => '12314F']), ['after' => 40]);
    if ($type !== 'text' && $type !== 'number' && trim((string)($it['ctx'] ?? '')) !== '') $x .= rbxPara(rbxRun($it['ctx'], ['size' => 15, 'color' => '8A93A6']), ['after' => 40]);
    $hasDesc = trim((string)($it['desc'] ?? '')) !== '';

    if ($type === 'chart') {
        $bin = base64_decode(substr((string)($it['img'] ?? ''), strlen('data:image/png;base64,')), true);
        if ($bin) {
            [$rId, $iw, $ih] = rbxRegisterImage($bin, $media, $docRels, $seq);
            if ($rId && $iw > 0) {
                $availW = max(1000, $wTw - 200) * 635;
                $availH = max(900, $hTw - 420 - ($hasDesc ? 420 : 0) - 120) * 635;
                $cx = $availW; $cy = (int)($availW * $ih / $iw);
                if ($cy > $availH) { $cy = $availH; $cx = (int)($availH * $iw / $ih); }
                $k = min($fs, 1.0); $cx = (int)($cx * $k); $cy = (int)($cy * $k);
                $x .= rbxPara(rbxDrawing($rId, $cx, $cy, $seq), ['jc' => 'center']);
            }
        }
    } elseif ($type === 'number') {
        $px = min(0.34 * max(20, $hTw / 15 - 60), 0.22 * ($wTw / 15)) * $fs;
        $sz = max(24, min(200, (int)round($px * 1.5)));
        $x .= rbxPara(rbxRun((string)($it['value'] ?? ''), ['size' => $sz, 'bold' => true, 'color' => '1F5AA8']), ['jc' => 'center', 'before' => 60]);
    } elseif ($type === 'table') {
        $t = rbxHtmlTable((string)($it['html'] ?? ''), $wTw, $fs);
        if ($t !== '') $x .= $t . rbxPara('', ['line' => 40]);
    } elseif ($type === 'text') {
        $lines = preg_split('/\R/u', (string)($it['text'] ?? ''));
        $sz = max(12, min(120, (int)round(22 * $fs)));
        foreach ($lines as $ln) $x .= rbxPara(rbxRun($ln, ['size' => $sz, 'color' => '1D2A44']));
    }
    if ($hasDesc) $x .= rbxPara(rbxRun($it['desc'], ['size' => 20, 'color' => '33405A']), ['before' => 40]);
    return $x;
}

function rbxCell(int $wTw, string $content, array $o = []): string
{
    $bd = '<w:tcBorders>';
    foreach (['top', 'left', 'bottom', 'right'] as $s) {
        $bd .= empty($o['border']) ? '<w:' . $s . ' w:val="nil"/>' : '<w:' . $s . ' w:val="' . ($o['dash'] ?? false ? 'dashed' : 'single') . '" w:sz="4" w:space="0" w:color="D5DCEA"/>';
    }
    $bd .= '</w:tcBorders>';
    return '<w:tc><w:tcPr><w:tcW w:w="' . $wTw . '" w:type="dxa"/>' . $bd
        . '<w:tcMar><w:top w:w="60" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tcMar>'
        . (!empty($o['vcenter']) ? '<w:vAlign w:val="center"/>' : '') . '</w:tcPr>'
        . ($content === '' ? rbxPara('') : $content) . '</w:tc>';
}

/* ---------- چیدمان: آیتم‌های هر صفحه → ردیف‌های جدول (راست‌به‌چپ) ---------- */
function rbxPageBody(array $list, array &$media, array &$docRels, int &$seq): string
{
    $defs = ['number' => [210, 100], 'chart' => [520, 300], 'table' => [520, 300], 'text' => [320, 120]];
    $flowY = 760;
    foreach ($list as &$it) {           // آیتم‌هایی که هنوز در سازنده جای‌گذاری نشده‌اند: پشت سر هم
        if (!isset($it['x']) || $it['x'] === null) {
            [$dw, $dh] = $defs[$it['type'] ?? 'chart'] ?? [300, 200];
            $it['w'] = $dw; $it['h'] = $dh; $it['x'] = RBX_PW - RBX_SIDE - $dw; $it['y'] = $flowY; $flowY += $dh + 20;
        }
    }
    unset($it);
    usort($list, fn($a, $b) => [(int)$a['y'], -(int)$a['x']] <=> [(int)$b['y'], -(int)$b['x']]);

    $rows = [];
    foreach ($list as $it) {
        $n = count($rows);
        if ($n && (int)$it['y'] < $rows[$n - 1]['y'] + 0.5 * $rows[$n - 1]['h0']) {
            $rows[$n - 1]['items'][] = $it;
            $rows[$n - 1]['bottom'] = max($rows[$n - 1]['bottom'], (int)$it['y'] + (int)$it['h']);
        } else {
            $rows[] = ['y' => (int)$it['y'], 'h0' => max(1, (int)$it['h']), 'items' => [$it], 'bottom' => (int)$it['y'] + (int)$it['h']];
        }
    }

    $out = ''; $prevBottom = 56; $usablePx = RBX_USABLE / 15;
    foreach ($rows as $row) {
        $gap = $row['y'] - $prevBottom;
        $out .= rbxPara('', ['line' => max(40, min(700, (int)round($gap * 15 * 0.9)))]);
        usort($row['items'], fn($a, $b) => (int)$b['x'] <=> (int)$a['x']);   // از راست به چپ

        $cells = []; $edge = RBX_PW - RBX_SIDE; $total = 0; $maxH = 0;
        foreach ($row['items'] as $it) {
            $gapX = $edge - ((int)$it['x'] + (int)$it['w']);
            if ($gapX > 20) { $cells[] = ['spacer' => true, 'w' => $gapX]; $total += $gapX; }
            $cells[] = ['spacer' => false, 'w' => (int)$it['w'], 'it' => $it]; $total += (int)$it['w'];
            $edge = (int)$it['x']; $maxH = max($maxH, (int)$it['h']);
        }
        $k = $total > $usablePx ? $usablePx / $total : 1.0;
        $sumTw = 0; $tr = '<w:tr><w:trPr><w:cantSplit/><w:trHeight w:val="' . min(9000, (int)round($maxH * 15 * 0.9)) . '" w:hRule="atLeast"/></w:trPr>';
        $grid = '';
        foreach ($cells as $c) {
            $wTw = max(200, (int)round($c['w'] * $k * 15)); $sumTw += $wTw; $grid .= '<w:gridCol w:w="' . $wTw . '"/>';
            if ($c['spacer']) { $tr .= rbxCell($wTw, ''); continue; }
            $it = $c['it'];
            $tr .= rbxCell($wTw, rbxItemBlocks($it, $wTw, (int)round($it['h'] * 15 * 0.9), $media, $docRels, $seq),
                ['border' => ($it['type'] ?? '') !== 'text', 'dash' => false, 'vcenter' => ($it['type'] ?? '') === 'number']);
        }
        $tr .= '</w:tr>';
        $out .= '<w:tbl><w:tblPr><w:tblW w:w="' . $sumTw . '" w:type="dxa"/><w:bidiVisual/><w:tblLayout w:type="fixed"/>'
            . '<w:tblCellMar><w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>' . $tr . '</w:tbl>';
        $prevBottom = $row['bottom'];
    }
    return $out;
}

/* ---------- ساخت بسته‌ی docx ---------- */
$hdrPng = rbxGradientPng(1200, 60, [[0, [10, 26, 64]], [0.55, [18, 58, 115]], [1, [31, 90, 168]]]);
$linePng = rbxGradientPng(800, 4, [[0, [31, 90, 168]], [0.5, [18, 58, 115]], [1, [31, 90, 168]]], 0.2);

$body = ''; $partsHdr = []; $partsFtr = []; $sectRefs = [];
for ($p = 0; $p < $pages; $p++) {
    $list = array_values(array_filter($items, fn($it) => (int)($it['page'] ?? 0) === $p));
    $pageBody = rbxPageBody($list, $media, $docRels, $imgSeq);

    // سربرگ (عنوان همین صفحه)
    $hTitle = array_key_exists($p, $pageTitles) ? (string)$pageTitles[$p] : $title;
    $hRels = ''; $hRun = '';
    $hParaOpts = ['jc' => 'center', 'line' => 720];
    if ($hdrPng) {
        [$rId] = rbxRegisterImage($hdrPng, $media, $docRels, $imgSeq);
        $hRels = '<Relationship Id="rIdBg" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image' . $imgSeq . '.png"/>';
        $hRun = rbxDrawing('rIdBg', RBX_PAGE_W * 635, 50 * 9525, 900 + $p, ['x' => 0, 'y' => 0]);
    } else {
        $hParaOpts['shd'] = '123A73'; $hParaOpts['ind'] = ['left' => -RBX_MAR_SIDE, 'right' => -RBX_MAR_SIDE];
    }
    $partsHdr[$p] = ['xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr ' . RBX_NS . '>'
        . rbxPara($hRun . rbxRun($hTitle, ['size' => 34, 'bold' => true, 'color' => 'FFFFFF']), $hParaOpts) . '</w:hdr>', 'rels' => $hRels];

    // پاورقی: خط آبی وسط، متن اداره سمت چپ، شماره‌ی صفحه وسط
    $fx = ''; $fRels = '';
    if (!empty($footer['line']) && $linePng) {
        [$rId] = rbxRegisterImage($linePng, $media, $docRels, $imgSeq);
        $fRels = '<Relationship Id="rIdLine" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image' . $imgSeq . '.png"/>';
        $fx .= rbxPara(rbxDrawing('rIdLine', (int)(RBX_USABLE * 0.5 * 635), 2 * 9525, 950 + $p), ['rtl' => false, 'jc' => 'center']);
    } elseif (!empty($footer['line'])) {
        $fx .= rbxPara('', ['rtl' => false, 'jc' => 'center', 'ind' => ['left' => 3900, 'right' => 3900], 'line' => 40, 'shd' => '1F5AA8']);
    }
    $ftxt = !empty($footer['showText']) ? (string)($footer['text'] ?? '') : '';
    $fnum = !empty($footer['num']) ? (array_key_exists($p, $pageNums) ? (string)$pageNums[$p] : rbxToFa($p + 1)) : '';
    if ($ftxt !== '' || $fnum !== '') {
        $mid = (int)(RBX_USABLE / 2);
        $runs = ($ftxt !== '' ? rbxRun($ftxt, ['size' => 18, 'color' => '33405A']) : '')
            . '<w:r><w:tab/></w:r>' . ($fnum !== '' ? rbxRun($fnum, ['size' => 22, 'bold' => true, 'color' => '12314F']) : '');
        $fx .= rbxPara($runs, ['rtl' => false, 'tabs' => '<w:tabs><w:tab w:val="center" w:pos="' . $mid . '"/></w:tabs>']);
    }
    if ($fx === '') $fx = rbxPara('', ['rtl' => false]);
    $partsFtr[$p] = ['xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr ' . RBX_NS . '>' . $fx . '</w:ftr>', 'rels' => $fRels];

    $sect = '<w:sectPr><w:headerReference w:type="default" r:id="rIdHdr' . $p . '"/><w:footerReference w:type="default" r:id="rIdFtr' . $p . '"/>'
        . '<w:pgSz w:w="' . RBX_PAGE_W . '" w:h="' . RBX_PAGE_H . '" w:orient="landscape"/>'
        . '<w:pgMar w:top="' . RBX_MAR_TOP . '" w:right="' . RBX_MAR_SIDE . '" w:bottom="' . RBX_MAR_BOT . '" w:left="' . RBX_MAR_SIDE . '" w:header="0" w:footer="284" w:gutter="0"/><w:bidi/></w:sectPr>';
    if ($p < $pages - 1) {
        $body .= $pageBody . rbxPara('', ['line' => 20, 'sect' => $sect]);
    } else {
        $body .= $pageBody . rbxPara('', ['line' => 20]) . $sect;
    }
}

$cfg = appConfig();
if (!is_dir($cfg['storage_tmp'])) mkdir($cfg['storage_tmp'], 0775, true);
$tmp = $cfg['storage_tmp'] . '/report_' . uniqid() . '.docx';
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) die('امکان ساخت فایل Word نبود.');

$ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>';
$rel = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
for ($p = 0; $p < $pages; $p++) {
    $ct .= '<Override PartName="/word/header' . $p . '.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
        . '<Override PartName="/word/footer' . $p . '.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>';
    $rel .= '<Relationship Id="rIdHdr' . $p . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header' . $p . '.xml"/>'
        . '<Relationship Id="rIdFtr' . $p . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer' . $p . '.xml"/>';
    foreach (['header' => $partsHdr[$p], 'footer' => $partsFtr[$p]] as $kind => $part) {
        $zip->addFromString('word/' . $kind . $p . '.xml', $part['xml']);
        if ($part['rels'] !== '') {
            $zip->addFromString('word/_rels/' . $kind . $p . '.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $part['rels'] . '</Relationships>');
        }
    }
}
foreach ($docRels as [$id, , $target]) {
    $rel .= '<Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="' . $target . '"/>';
}
$zip->addFromString('[Content_Types].xml', $ct . '</Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
$zip->addFromString('word/_rels/document.xml.rels', $rel . '</Relationships>');
$zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Tahoma" w:hAnsi="Tahoma" w:cs="B Nazanin"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="en-US" w:bidi="fa-IR"/></w:rPr></w:rPrDefault>'
    . '<w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
    . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style></w:styles>');
$zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . RBX_NS . '><w:body>' . $body . '</w:body></w:document>');
foreach ($media as $name => $bin) $zip->addFromString('word/media/' . $name, $bin);
$zip->close();

$fname = 'گزارش_' . preg_replace('/[\\\\\/:*?"<>|\s]+/u', '_', mb_substr($title, 0, 60)) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header("Content-Disposition: attachment; filename=\"report.docx\"; filename*=UTF-8''" . rawurlencode($fname));
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: max-age=0');
readfile($tmp);
@unlink($tmp);
