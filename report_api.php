<?php
// API «سازنده گزارش»: هر کاربر یک گزارش کاری دارد که آیتم‌هایش (نمودار/جدول/عدد) از صفحه‌ی ارزیابی اضافه می‌شوند
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/jsondb.php';
requireLoginApi();
header('Content-Type: application/json; charset=utf-8');

const RB_MAX_ITEMS = 60;
const RB_MAX_BYTES = 2500000; // سقف اندازه‌ی هر آیتم (تصویر نمودار/HTML جدول)

function rbOut(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$me = currentUser();
$store = 'report_' . substr(sha1((string)($me['username'] ?? 'x')), 0, 16);
const RB_FOOTER_DEFAULT = ['line' => true, 'num' => true, 'showText' => true, 'text' => 'اداره ارزیابی خبرگزاری دانشجویان ایران (ایسنا)'];
$empty = ['title' => 'گزارش ارزیابی', 'pages' => 1, 'seq' => 0, 'items' => [], 'pageTitles' => new stdClass(), 'pageNums' => new stdClass(), 'footer' => RB_FOOTER_DEFAULT];
// ادغام با مقادیر پیش‌فرض (برای گزارش‌های قدیمی که فیلدهای سربرگ/پاورقی ندارند)
$rbMerge = function (array $d) use ($empty): array {
    $m = array_merge($empty, $d);
    $m['footer'] = array_merge(RB_FOOTER_DEFAULT, is_array($m['footer'] ?? null) ? $m['footer'] : []);
    return $m;
};

$in = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) $in = [];
}
$action = $in['action'] ?? ($_GET['action'] ?? 'get');

$str = fn($v, int $max) => mb_substr(trim((string)$v), 0, $max);
$num = fn($v, int $min, int $max) => max($min, min($max, (int)round((float)$v)));

try {
    switch ($action) {

        case 'get':
            rbOut(['ok' => true, 'report' => $rbMerge(jsonRead($store))]);

        case 'count':
            rbOut(['ok' => true, 'count' => count(jsonRead($store)['items'] ?? [])]);

        case 'add':
            $it = $in['item'] ?? null;
            if (!is_array($it)) rbOut(['ok' => false, 'error' => 'آیتم نامعتبر است.']);
            $type = $it['type'] ?? '';
            if (!in_array($type, ['chart', 'table', 'number', 'text'], true)) rbOut(['ok' => false, 'error' => 'نوع آیتم نامعتبر است.']);
            $clean = ['type' => $type, 'title' => $str($it['title'] ?? '', 200), 'ctx' => $str($it['ctx'] ?? '', 300), 'desc' => '', 'fs' => 1];
            if ($type === 'chart') {
                $img = (string)($it['img'] ?? '');
                if (strpos($img, 'data:image/png;base64,') !== 0 || strlen($img) > RB_MAX_BYTES) rbOut(['ok' => false, 'error' => 'تصویر نمودار نامعتبر یا بیش‌ازحد بزرگ است.']);
                $clean['img'] = $img;
            } elseif ($type === 'table') {
                $html = (string)($it['html'] ?? '');
                if ($html === '' || strlen($html) > RB_MAX_BYTES) rbOut(['ok' => false, 'error' => 'جدول نامعتبر یا بیش‌ازحد بزرگ است.']);
                $clean['html'] = $html;
                $clean['note'] = $str($it['note'] ?? '', 200);
            } elseif ($type === 'text') {
                $clean['text'] = '';
            } else {
                $clean['label'] = $str($it['label'] ?? '', 200);
                $clean['value'] = $str($it['value'] ?? '', 60);
            }
            $count = 0; $tooMany = false; $newId = 0;
            jsonUpdate($store, function ($d) use ($clean, $rbMerge, &$count, &$tooMany, &$newId) {
                $d = $rbMerge($d);
                if (count($d['items']) >= RB_MAX_ITEMS) { $tooMany = true; $count = count($d['items']); return $d; }
                $d['seq'] = (int)$d['seq'] + 1;
                $newId = $d['seq'];
                $d['items'][] = array_merge(['id' => $newId, 'x' => null, 'y' => null, 'w' => null, 'h' => null, 'page' => 0], $clean);
                $count = count($d['items']);
                return $d;
            });
            if ($tooMany) rbOut(['ok' => false, 'error' => 'حداکثر ' . RB_MAX_ITEMS . ' آیتم در گزارش مجاز است.']);
            rbOut(['ok' => true, 'count' => $count, 'id' => $newId]);

        case 'layout': // ذخیره‌ی چیدمان/عنوان/توضیح (بدون حذف آیتم‌ها؛ حذف فقط با action=delete)
            $inItems = is_array($in['items'] ?? null) ? $in['items'] : [];
            $byId = [];
            foreach ($inItems as $r) { if (is_array($r) && isset($r['id'])) $byId[(int)$r['id']] = $r; }
            jsonUpdate($store, function ($d) use ($in, $byId, $empty, $rbMerge, $str, $num) {
                $d = $rbMerge($d);
                if (isset($in['title'])) $d['title'] = $str($in['title'], 200) ?: $empty['title'];
                if (isset($in['pages'])) $d['pages'] = $num($in['pages'], 1, 20);
                // عنوان سربرگ و شماره‌ی هر صفحه (در صورت ویرایش دستی)
                foreach (['pageTitles' => 200, 'pageNums' => 20] as $k => $max) {
                    if (!isset($in[$k]) || !is_array($in[$k])) continue;
                    $o = [];
                    foreach ($in[$k] as $pi => $v) { if ((int)$pi >= 0 && (int)$pi < 20 && is_string($v)) $o[(int)$pi] = $str($v, $max); }
                    $d[$k] = $o ?: new stdClass();
                }
                if (isset($in['footer']) && is_array($in['footer'])) {
                    $f = $in['footer'];
                    $d['footer'] = [
                        'line' => !empty($f['line']), 'num' => !empty($f['num']), 'showText' => !empty($f['showText']),
                        'text' => $str($f['text'] ?? '', 200),
                    ];
                }
                foreach ($d['items'] as &$it) {
                    $r = $byId[(int)$it['id']] ?? null;
                    if (!$r) continue;
                    $it['x'] = $num($r['x'] ?? 0, 0, 3000);
                    $it['y'] = $num($r['y'] ?? 0, 0, 3000);
                    $it['w'] = $num($r['w'] ?? 200, 40, 3000);
                    $it['h'] = $num($r['h'] ?? 120, 40, 3000);
                    $it['page'] = $num($r['page'] ?? 0, 0, 19);
                    $it['title'] = $str($r['title'] ?? '', 200);
                    $it['desc'] = $str($r['desc'] ?? '', 2000);
                    $it['fs'] = max(0.5, min(3.0, round((float)($r['fs'] ?? 1), 2)));
                    if (($it['type'] ?? '') === 'text') $it['text'] = mb_substr((string)($r['text'] ?? ''), 0, 5000);
                }
                unset($it);
                return $d;
            });
            rbOut(['ok' => true]);

        case 'delete':
            $id = (int)($in['id'] ?? 0);
            jsonUpdate($store, function ($d) use ($id, $rbMerge) {
                $d = $rbMerge($d);
                $d['items'] = array_values(array_filter($d['items'], fn($it) => (int)$it['id'] !== $id));
                return $d;
            });
            rbOut(['ok' => true]);

        case 'clear':
            jsonUpdate($store, fn($d) => array_merge($rbMerge([]), ['title' => $d['title'] ?? $empty['title'], 'seq' => (int)($d['seq'] ?? 0), 'footer' => $rbMerge($d)['footer'], 'pageTitles' => $d['pageTitles'] ?? new stdClass(), 'pageNums' => $d['pageNums'] ?? new stdClass()]));
            rbOut(['ok' => true]);

        default:
            rbOut(['ok' => false, 'error' => 'عملیات نامعتبر.']);
    }
} catch (Throwable $e) {
    rbOut(['ok' => false, 'error' => $e->getMessage()]);
}
