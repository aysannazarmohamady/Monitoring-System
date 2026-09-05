<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();
require_once __DIR__ . '/includes/jsondb.php';
require_once __DIR__ . '/includes/trends.php';

function trendsAgencyClass(string $agency): string
{
    if (mb_strpos($agency, 'ایسنا') !== false) return 'is-isna';
    foreach (['مهر', 'فارس', 'ایرنا', 'تسنیم'] as $riv) {
        if (mb_strpos($agency, $riv) !== false) return 'is-rival';
    }
    return '';
}

$today = todayJalali();
$from = normalizeJalaliDate($_GET['from'] ?? $today) ?? $today;
$to = normalizeJalaliDate($_GET['to'] ?? $today) ?? $today;

$trends = array_slice(trendsGetRangeMerged($from, $to), 0, 10);
foreach ($trends as &$t) { $t['coverage'] = array_values($t['coverage']); }
unset($t);

$rangeLabel = ($from === $to) ? jalaliDateLabel($from) : (jalaliDateLabel($from) . ' تا ' . jalaliDateLabel($to));

require __DIR__ . '/includes/layout_top.php';
?>
<style>
.trends-table thead th{
  background: linear-gradient(135deg, var(--navy-1), var(--navy-3));
  color: #fff; font-weight: 700; text-align: center; vertical-align: middle;
}
.trends-table td{ vertical-align: middle; }
.trends-keyword{ font-size: 1.25rem; font-weight: 800; color: var(--navy-1); text-align: center; }
.trends-table tbody tr.group-alt td{ background-color: #eef3fb; }
.trends-table td.is-isna, .trends-table td.is-isna a{ color: #d32f2f !important; font-weight: 700; }
.trends-table td.is-rival, .trends-table td.is-rival a{ color: #7b1fa2 !important; font-weight: 700; }
.trends-date-heading{
  display:block; font-size: 1.5rem; font-weight: 800; color: var(--navy-1);
  border-bottom: 2px solid var(--navy-3); padding-bottom: .5rem; margin-bottom: 1rem;
}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <h5 class="mb-0">نگاهی به ترندها و بازتاب خبری آن‌ها در رسانه‌ها</h5>
</div>

<div class="card shadow-sm p-4 mb-4">
  <div class="row g-3 align-items-end mb-3">
    <div class="col-md-3">
      <label class="form-label">از تاریخ</label>
      <input type="text" id="fFrom" class="form-control jalali-date-input" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">تا تاریخ</label>
      <input type="text" id="fTo" class="form-control jalali-date-input" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-md-2">
      <button id="btnGo" class="btn btn-primary w-100">نمایش</button>
    </div>
  </div>

  <span class="trends-date-heading"><?= htmlspecialchars($rangeLabel) ?></span>

  <?php if (empty($trends)): ?>
    <div class="text-muted small">برای این بازه‌ی زمانی ترندی ثبت نشده است.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-bordered trends-table mb-0">
      <thead>
        <tr><th style="width:16%">عبارت ترند</th><th>خبر داغ مرتبط</th><th style="width:16%">منبع</th></tr>
      </thead>
      <tbody>
        <?php foreach ($trends as $gi => $t):
          $covers = $t['coverage'] ?? [];
          $rowspan = max(1, count($covers));
          $altClass = ($gi % 2 === 1) ? 'group-alt' : '';
        ?>
          <?php foreach ($covers as $i => $c):
            $cls = trendsAgencyClass($c['agency'] ?? '');
          ?>
            <tr class="<?= $altClass ?>">
              <?php if ($i === 0): ?>
                <td rowspan="<?= (int)$rowspan ?>" class="trends-keyword"><?= htmlspecialchars($t['keyword'] ?? '') ?></td>
              <?php endif; ?>
              <td class="<?= $cls ?>"><a href="<?= htmlspecialchars($c['url'] ?? '#') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($c['title'] ?? '') ?></a></td>
              <td class="<?= $cls ?>"><?= htmlspecialchars($c['agency'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
document.getElementById('btnGo').addEventListener('click', function () {
  var f = document.getElementById('fFrom').value.trim();
  var t = document.getElementById('fTo').value.trim();
  if (f && t) window.location.href = 'trends_report.php?from=' + encodeURIComponent(f) + '&to=' + encodeURIComponent(t);
});
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
