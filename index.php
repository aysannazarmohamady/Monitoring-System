<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/jsondb.php';
requireLoginPage();

$me = currentUser();
$reporter = $me['display_name'];

$activeDates = excelFilesActiveDates();
$date = normalizeJalaliDate($_GET['date'] ?? '') ?? normalizeJalaliDate($activeDates[0] ?? '') ?? todayJalali();
$hasExcelForDate = in_array($date, $activeDates, true);

$dateEntries = newsEntriesByDate($date);

$newsCount = count($dateEntries);
$monitorCounts = [];
foreach ($dateEntries as $r) {
    $enteredBy = trim((string)($r['entered_by_display'] ?? ''));
    if ($enteredBy === '') continue; // خبرهای قدیمی‌تر از این تغییر، این فیلد را ندارند
    $monitorCounts[$enteredBy] = ($monitorCounts[$enteredBy] ?? 0) + 1;
}
arsort($monitorCounts);
$topReporter = array_key_first($monitorCounts) ?? '';
$topReporterCount = $monitorCounts[$topReporter] ?? 0;

$recent = array_slice($dateEntries, 0, 5);

// ---- اورویو آماری روز: بر اساس کل فایل اکسل آپلودشده (excel_rows)، مشابه بخش ارزیابی ----
$fileRows = $hasExcelForDate ? rowsInRange($date, $date) : [];
$fileCount = count($fileRows);
$totalViewsFile = array_sum(array_map(fn($r) => (int)($r['views'] ?? 0), $fileRows));
$avgViewsAll = $fileCount > 0 ? round($totalViewsFile / $fileCount) : 0;

$fileReporterCounts = [];
$filePublisherCounts = [];
$serviceStats = []; // service_main => ['count'=>, 'views_sum'=>, 'top'=>['title'=>,'views'=>]]
foreach ($fileRows as $r) {
    $rep = trim((string)($r['reporter'] ?? ''));
    if ($rep !== '' && $rep !== '-' && $rep !== '—') $fileReporterCounts[$rep] = ($fileReporterCounts[$rep] ?? 0) + 1;
    $pub = trim((string)($r['publisher'] ?? ''));
    if ($pub !== '') $filePublisherCounts[$pub] = ($filePublisherCounts[$pub] ?? 0) + 1;

    $svc = trim((string)($r['service_main'] ?? '')) ?: 'نامشخص';
    $v = (int)($r['views'] ?? 0);
    if (!isset($serviceStats[$svc])) $serviceStats[$svc] = ['count' => 0, 'views_sum' => 0, 'top_title' => '', 'top_views' => -1];
    $serviceStats[$svc]['count']++;
    $serviceStats[$svc]['views_sum'] += $v;
    if ($v > $serviceStats[$svc]['top_views']) {
        $serviceStats[$svc]['top_views'] = $v;
        $serviceStats[$svc]['top_title'] = (string)($r['title'] ?? '');
    }
}
arsort($fileReporterCounts);
$topActualReporter = array_key_first($fileReporterCounts) ?? '';
$topActualReporterCount = $fileReporterCounts[$topActualReporter] ?? 0;
arsort($filePublisherCounts);
$topPublisher = array_key_first($filePublisherCounts) ?? '';
$topPublisherCount = $filePublisherCounts[$topPublisher] ?? 0;

uasort($serviceStats, fn($a, $b) => $b['count'] <=> $a['count']);
$topServices = array_slice($serviceStats, 0, 6, true);

$trendsData = jsonRead('google_trends');
$trendsList = $trendsData['trends'] ?? null;
$trendsFetchedAt = $trendsData['fetched_at'] ?? null;

require __DIR__ . '/includes/layout_top.php';
?>
<div class="card shadow-sm p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="text-muted small">
      آمار برای تاریخ: <strong class="text-dark"><?= htmlspecialchars(jalaliDateLabel($date)) ?></strong>
      <span class="text-muted">(آخرین تاریخی که فایل اکسل آن آپلود شده)</span>
    </div>
    <form method="get" class="d-flex gap-2 align-items-center">
      <select name="date" class="form-select form-select-sm" style="min-width:170px" onchange="this.form.submit()">
        <?php if (!in_array($date, $activeDates, true)): ?>
          <option value="<?= htmlspecialchars($date) ?>" selected><?= htmlspecialchars($date) ?> (بدون فایل اکسل)</option>
        <?php endif; ?>
        <?php foreach ($activeDates as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $d === $date ? 'selected' : '' ?>><?= htmlspecialchars(jalaliDateLabel($d)) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="card shadow-sm p-3 text-center h-100">
      <div class="fs-3 fw-bold text-primary"><?= (int)$newsCount ?></div>
      <div class="small text-muted">خبر ثبت‌شده</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card shadow-sm p-3 text-center h-100">
      <?php if ($topReporter !== ''): ?>
        <div class="fs-6 fw-bold text-primary text-truncate"><?= htmlspecialchars($topReporter) ?></div>
        <div class="small text-muted">ناظر با بیشترین خبر ثبت‌شده (<?= (int)$topReporterCount ?> خبر)</div>
      <?php else: ?>
        <div class="fs-6 fw-bold text-muted">بدون داده</div>
        <div class="small text-muted">ناظر با بیشترین خبر ثبت‌شده (فقط خبرهای ثبت‌شده از این پس محاسبه می‌شود)</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <?php if ($hasExcelForDate): ?>
      <div class="card shadow-sm p-3 text-center h-100">
        <div class="fs-6 fw-bold text-success">فایل اکسل موجود است</div>
        <div class="small text-muted">برای این تاریخ آپلود شده</div>
      </div>
    <?php else: ?>
      <a href="upload.php" class="card shadow-sm p-3 text-center h-100 text-decoration-none border-danger">
        <div class="fs-6 fw-bold text-danger">فایل اکسل موجود نیست</div>
        <div class="small text-danger">برای آپلود کلیک کنید</div>
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h6 class="mb-0">آمار کلی روز  (<?= htmlspecialchars(jalaliDateLabel($date)) ?>) <span class="small text-muted fw-normal"> بر اساس کل اخبار ارسالی </span></h6>
  </div>
  <?php if (!$hasExcelForDate): ?>
    <div class="text-muted small">برای این تاریخ فایل اکسلی آپلود نشده؛ آماری برای نمایش وجود ندارد.</div>
  <?php else: ?>
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm p-3 text-center h-100">
        <div class="fs-4 fw-bold text-primary"><?= (int)$fileCount ?></div>
        <div class="small text-muted">تعداد کل اخبار</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm p-3 text-center h-100">
        <div class="fs-4 fw-bold text-primary"><?= (int)$avgViewsAll ?></div>
        <div class="small text-muted">میانگین بازدید</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm p-3 text-center h-100">
        <?php if ($topActualReporter !== ''): ?>
          <div class="fs-6 fw-bold text-primary text-truncate"><?= htmlspecialchars($topActualReporter) ?></div>
          <div class="small text-muted">خبرنگار با بیشترین خبر (<?= (int)$topActualReporterCount ?>)</div>
        <?php else: ?>
          <div class="fs-6 fw-bold text-muted">بدون داده</div>
          <div class="small text-muted">خبرنگار با بیشترین خبر</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm p-3 text-center h-100">
        <?php if ($topPublisher !== ''): ?>
          <div class="fs-6 fw-bold text-primary text-truncate"><?= htmlspecialchars($topPublisher) ?></div>
          <div class="small text-muted">ناشر با بیشترین خبر (<?= (int)$topPublisherCount ?>)</div>
        <?php else: ?>
          <div class="fs-6 fw-bold text-muted">بدون داده</div>
          <div class="small text-muted">ناشر با بیشترین خبر</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php if (empty($topServices)): ?>
    <div class="text-muted small">داده‌ای برای این تاریخ ثبت نشده است.</div>
  <?php else: ?>
    <div class="text-muted small mb-2">۶ سرویس با بیشترین تعداد خبر ارسالی</div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle sortable-table">
        <thead><tr>
          <th>سرویس</th><th>تعداد خبر</th><th>درصد از کل</th><th>میانگین بازدید</th><th>پربازدیدترین خبر</th><th>بازدید خبر</th>
        </tr></thead>
        <tbody>
          <?php foreach ($topServices as $svcName => $s):
            $pct = $fileCount > 0 ? round($s['count'] / $fileCount * 100, 1) : 0;
            $svcAvg = $s['count'] > 0 ? round($s['views_sum'] / $s['count']) : 0;
          ?>
            <tr>
              <td><?= htmlspecialchars($svcName) ?></td>
              <td><?= (int)$s['count'] ?></td>
              <td><?= $pct ?>%</td>
              <td><?= (int)$svcAvg ?></td>
              <td><?= htmlspecialchars($s['top_title']) ?></td>
              <td><?= (int)max(0, $s['top_views']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card shadow-sm p-4 mb-4">
  <h6 class="mb-3">تعداد اخبار بررسی‌شده به تفکیک ناظر (<?= htmlspecialchars(jalaliDateLabel($date)) ?>)</h6>
  <?php if (empty($monitorCounts)): ?>
    <div class="text-muted small">داده‌ای برای این تاریخ ثبت نشده است.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle sortable-table">
        <thead><tr><th>ناظر</th><th>تعداد اخبار بررسی‌شده</th></tr></thead>
        <tbody>
          <?php foreach ($monitorCounts as $mon => $cnt): ?>
            <tr><td><?= htmlspecialchars($mon) ?></td><td><?= (int)$cnt ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <a href="entry.php?date=<?= urlencode($date) ?>" class="btn btn-primary w-100 py-3 fw-bold">ثبت خبر</a>
  </div>
  <div class="col-6 col-md-3">
    <a href="file_entry.php" class="btn btn-outline-primary w-100 py-3 fw-bold">ثبت از پرونده</a>
  </div>
  <div class="col-6 col-md-3">
    <a href="report.php" class="btn btn-outline-primary w-100 py-3 fw-bold">گزارش‌گیری</a>
  </div>
  <div class="col-6 col-md-3">
    <a href="evaluation.php" class="btn btn-outline-primary w-100 py-3 fw-bold">ارزیابی</a>
  </div>
</div>

<div class="card shadow-sm p-4 mb-4">
  <h6 class="mb-3">آخرین اخبار ثبت‌شده در این تاریخ</h6>
  <?php if (empty($recent)): ?>
    <div class="text-muted small">هنوز خبری برای این تاریخ ثبت نشده است.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>تیتر</th><th>ناشر</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['title'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['publisher'] ?? '') ?></td>
            <td><a href="entries_edit.php?id=<?= (int)$r['id'] ?>" class="small">ویرایش</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="card shadow-sm p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h6 class="mb-0">ترندهای گوگل ایران که ایسنا پوشش داده</h6>
    <span class="small text-muted">
      <?= $trendsFetchedAt ? 'آخرین به‌روزرسانی: ' . htmlspecialchars($trendsFetchedAt) : '' ?>
    </span>
  </div>
  <?php if ($trendsList === null): ?>
    <div class="text-muted small">هنوز داده‌ای دریافت نشده است. کران‌جاب <code>cron_fetch_trends.php</code> باید هر ۳۰ دقیقه اجرا شود.</div>
  <?php elseif (empty($trendsList)): ?>
    <div class="text-muted small">در حال حاضر ترند مرتبطی یافت نشد.</div>
  <?php else: ?>
    <div class="list-group list-group-flush">
      <?php foreach ($trendsList as $t): ?>
        <div class="list-group-item px-0">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <span class="badge bg-secondary"><?= htmlspecialchars($t['keyword'] ?? '') ?></span>
              <?php if (!empty($t['traffic'])): ?><span class="small text-muted ms-1"><?= htmlspecialchars($t['traffic']) ?> جستجو</span><?php endif; ?>
            </div>
          </div>
          <?php if (!empty($t['isna'])): ?>
            <div class="mt-1">
              <span class="badge bg-success">ایسنا</span>
              <a href="<?= htmlspecialchars($t['isna']['url'] ?? '#') ?>" target="_blank" rel="noopener" class="ms-1"><?= htmlspecialchars($t['isna']['title'] ?? '') ?></a>
            </div>
          <?php else: ?>
            <div class="mt-1 text-danger small">از ایسنا خبری نیست</div>
          <?php endif; ?>
          <?php foreach (($t['others'] ?? []) as $o): ?>
            <div class="mt-1">
              <span class="badge bg-info text-dark"><?= htmlspecialchars($o['agency']) ?></span>
              <a href="<?= htmlspecialchars($o['url'] ?? '#') ?>" target="_blank" rel="noopener" class="ms-1"><?= htmlspecialchars($o['title'] ?? '') ?></a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="alert alert-info small mb-0">
  راهنمای سریع: ۱) اگر فایل اکسل این تاریخ آپلود نشده، اول از «آپلود اکسل روزانه» شروع کنید.
  ۲) در «ثبت خبر» کد خبر را بزنید و «دریافت اطلاعات» را بزنید.
  ۳) فیلدهای باقی‌مانده را تکمیل و ثبت کنید.
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
