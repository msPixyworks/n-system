<?php
/**
 * views/car_leases/by_lessee.php
 * ============================================================
 * リース先（法人/個人）ごとのリース中車両一覧
 *
 * 表示要件:
 * - 現在エリア（scheduled + active）
 * - 年度ごとの過去エリア（ended/canceled をFYでグルーピング）
 *
 * FY:
 * - config.php の fiscal_year（開始月日）に従う
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me  = $me  ?? Auth::user();

$canEdit = $me ? Policies::canEditCarLeases($me) : false;

$leaseOpts = $cfg['options']['car_leases'] ?? [];
$statusLabels = $leaseOpts['lease_status'] ?? [];
$lesseeTypeLabels = $leaseOpts['lessee_types'] ?? [];

$lesseeType = (string)($lesseeType ?? '');
$lesseeId   = (int)($lesseeId ?? 0);
$lesseeName = (string)($lesseeName ?? '');
$alerts     = (isset($alerts) && is_array($alerts)) ? $alerts : [];

// Controller から渡される currentScheduled / currentActive を受ける
$currentScheduled = (isset($currentScheduled) && is_array($currentScheduled)) ? $currentScheduled : [];
$currentActive    = (isset($currentActive) && is_array($currentActive)) ? $currentActive : [];
$pastByFy         = (isset($pastByFy) && is_array($pastByFy)) ? $pastByFy : [];

// 現在エリアは scheduled + active をまとめて表示
$current = array_merge($currentScheduled, $currentActive);

// 表示順: active を先、次に scheduled、同一状態内は開始日昇順、同日ならID昇順
usort($current, function (array $a, array $b): int {
    $sa = (string)($a['status'] ?? '');
    $sb = (string)($b['status'] ?? '');

    $wa = ($sa === 'active') ? 0 : (($sa === 'scheduled') ? 1 : 9);
    $wb = ($sb === 'active') ? 0 : (($sb === 'scheduled') ? 1 : 9);
    if ($wa !== $wb) return $wa <=> $wb;

    $da = (string)($a['lease_start_date'] ?? '');
    $db = (string)($b['lease_start_date'] ?? '');
    if ($da !== $db) return strcmp($da, $db);

    return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
});

// FY開始（月日）表示
$fyCfg = $cfg['fiscal_year'] ?? ['start_month' => 4, 'start_day' => 1];
$fyStartMonth = (int)($fyCfg['start_month'] ?? 4);
$fyStartDay   = (int)($fyCfg['start_day'] ?? 1);
if ($fyStartMonth < 1 || $fyStartMonth > 12) $fyStartMonth = 4;
if ($fyStartDay < 1 || $fyStartDay > 31) $fyStartDay = 1;

// 期間表示（実終了があればそちら優先）
$periodText = function (array $l): string {
    $s = (string)($l['lease_start_date'] ?? '');
    $e = (string)($l['lease_end_date'] ?? '');

    $effectiveEnd = $e;
    if (!empty($l['ended_at'])) {
        $effectiveEnd = substr((string)$l['ended_at'], 0, 10);
    } elseif (!empty($l['canceled_at'])) {
        $effectiveEnd = substr((string)$l['canceled_at'], 0, 10);
    }

    if ($s !== '' && $effectiveEnd !== '') {
        return str_replace('-', '/', $s) . ' ～ ' . str_replace('-', '/', $effectiveEnd);
    }
    return '';
};

$lesseeTypeText = (string)($lesseeTypeLabels[$lesseeType] ?? $lesseeType);
?>
<div class="k-page car-leases-by-lessee">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">リース先別 リース中車両一覧</h1>
      <div class="k-page__sub">リース先ごとに、現在と過去（年度別）のリース履歴を表示します。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/car_leases/active">リース中一覧へ</a>
    </div>
  </div>

  <?php if (!empty($alerts)): ?>
    <div class="alert alert-warning mb-3">
      <strong>リース開始できない契約があります。</strong>
      <ul class="mb-0 mt-2">
        <?php foreach ($alerts as $a): ?>
          <li>
            車両
            <?php if (!empty($a['management_number'])): ?>
              <?= htmlspecialchars((string)$a['management_number'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
            <?php if (!empty($a['vehicle_number'])): ?>
              （<?= htmlspecialchars((string)$a['vehicle_number'], ENT_QUOTES, 'UTF-8') ?>）
            <?php endif; ?>
            :
            <?= htmlspecialchars((string)($a['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- 条件サマリー -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">対象</div>
        <div class="k-section-sub text-muted">リース先 / 年度開始</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>
            <tr>
              <th>リース先</th>
              <td colspan="3">
                <?= htmlspecialchars($lesseeTypeText, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($lesseeName !== ''): ?>
                  <span class="ms-2 fw-semibold"><?= htmlspecialchars($lesseeName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th>年度開始</th>
              <td colspan="3">
                <?= (int)$fyStartMonth ?>/<?= (int)$fyStartDay ?> 開始
                <span class="small text-muted ms-2">（年度ごとの過去エリアはこの年度定義で表示）</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 現在エリア -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">現在</div>
        <div class="k-section-sub text-muted"><?= count($current) ?> 件</div>
      </div>
    </div>
    <div class="k-card__body">
      <?php if (!empty($current)): ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle k-table">
            <thead>
              <tr>
                <th>車両</th>
                <th>期間</th>
                <th class="text-end">月額</th>
                <th>状態</th>
                <?php if ($canEdit): ?><th>操作</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($current as $l): ?>
                <?php
                  $carId = (int)($l['car_id'] ?? 0);
                  $carLabel = trim((string)($l['management_number'] ?? ''));
                  if ($carLabel === '') $carLabel = 'CAR#' . $carId;
                ?>
                <tr>
                  <td>
                    <a href="/cars/<?= $carId ?>"><?= htmlspecialchars($carLabel, ENT_QUOTES, 'UTF-8') ?></a>
                    <?php if (!empty($l['vehicle_number'])): ?>
                      <div class="small text-muted"><?= htmlspecialchars((string)$l['vehicle_number'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($periodText($l), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-end"><?= number_format((int)($l['monthly_fee'] ?? 0)) ?> <span class="text-muted">円</span></td>
                  <td><?= htmlspecialchars((string)($statusLabels[$l['status']] ?? $l['status']), ENT_QUOTES, 'UTF-8') ?></td>
                  <?php if ($canEdit): ?>
                    <td>
                      <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="/cars/<?= $carId ?>/leases">リース詳細</a>
                        <a class="btn btn-sm btn-outline-danger" href="/car_leases/<?= (int)$l['id'] ?>/force_end">終了</a>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="text-muted">現在リース中の車両はありません。</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 年度ごとの過去 -->
  <?php if (!empty($pastByFy)): ?>
    <?php foreach ($pastByFy as $fy => $rows): ?>
      <div class="k-card mb-3">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title"><?= (int)$fy ?> 年度</div>
            <div class="k-section-sub text-muted"><?= count($rows) ?> 件</div>
          </div>
        </div>
        <div class="k-card__body">
          <div class="table-responsive">
            <table class="table table-sm align-middle k-table">
              <thead>
                <tr>
                  <th>車両</th>
                  <th>期間</th>
                  <th class="text-end">月額</th>
                  <th>状態</th>
                  <?php if ($canEdit): ?><th>操作</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $l): ?>
                  <?php
                    $carId = (int)($l['car_id'] ?? 0);
                    $carLabel = trim((string)($l['management_number'] ?? ''));
                    if ($carLabel === '') $carLabel = 'CAR#' . $carId;
                  ?>
                  <tr>
                    <td>
                      <a href="/cars/<?= $carId ?>"><?= htmlspecialchars($carLabel, ENT_QUOTES, 'UTF-8') ?></a>
                      <?php if (!empty($l['vehicle_number'])): ?>
                        <div class="small text-muted"><?= htmlspecialchars((string)$l['vehicle_number'], ENT_QUOTES, 'UTF-8') ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($periodText($l), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end"><?= number_format((int)($l['monthly_fee'] ?? 0)) ?> <span class="text-muted">円</span></td>
                    <td><?= htmlspecialchars((string)($statusLabels[$l['status']] ?? $l['status']), ENT_QUOTES, 'UTF-8') ?></td>
                    <?php if ($canEdit): ?>
                      <td>
                        <div class="d-flex flex-wrap gap-2">
                          <a class="btn btn-sm btn-outline-secondary" href="/car_leases/<?= (int)$l['id'] ?>/edit">編集</a>
                          <a class="btn btn-sm btn-outline-primary" href="/cars/<?= $carId ?>/leases">リース詳細</a>
                        </div>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="text-muted">過去のリース履歴はありません。</div>
  <?php endif; ?>

</div>