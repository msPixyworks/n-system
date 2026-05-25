<?php
/**
 * views/car_leases/show_by_car.php
 * ============================================================
 * 車両ごとのリース詳細
 *
 * 既存仕様:
 * - リース先表示は $l['lessee_name'] 優先、無ければ種別 + #ID
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me  = $me  ?? Auth::user();

$canEdit = $me ? Policies::canEditCarLeases($me) : false;

$leaseOpts = $cfg['options']['car_leases'] ?? [];
$statusLabels = $leaseOpts['lease_status'] ?? [];
$lesseeTypeLabels = $leaseOpts['lessee_types'] ?? [];

$alerts = $alerts ?? [];

$carId = (int)($car['id'] ?? 0);

// FY開始（月日）
$fyCfg = $cfg['fiscal_year'] ?? ['start_month' => 4, 'start_day' => 1];
$fyStartMonth = (int)($fyCfg['start_month'] ?? 4);
$fyStartDay   = (int)($fyCfg['start_day'] ?? 1);

// 期間表示
$periodText = function (array $l): string {
    $s = (string)($l['lease_start_date'] ?? '');
    $e = (string)($l['lease_end_date'] ?? '');

    if ($s !== '' && $e !== '') {
        return str_replace('-', '/', $s) . ' ～ ' . str_replace('-', '/', $e);
    }
    return '';
};

// 実終了日表示
$actualEndText = function (array $l): string {
    $endedAt = (string)($l['ended_at'] ?? '');
    $canceledAt = (string)($l['canceled_at'] ?? '');

    $ymd = '';
    if ($endedAt !== '') {
        $ymd = substr($endedAt, 0, 10);
    } elseif ($canceledAt !== '') {
        $ymd = substr($canceledAt, 0, 10);
    }

    return $ymd !== '' ? str_replace('-', '/', $ymd) : '';
};

// リース先表示
$lesseeText = function (array $l) use ($lesseeTypeLabels): string {
    $name = trim((string)($l['lessee_name'] ?? ''));
    $kana = trim((string)($l['lessee_kana'] ?? ''));

    if ($name !== '') {
        return ($kana !== '') ? ($name . '（' . $kana . '）') : $name;
    }

    $type = (string)($l['lessee_type'] ?? '');
    $typeText = (string)($lesseeTypeLabels[$type] ?? $type);
    $id = (int)($l['lessee_id'] ?? 0);

    if ($typeText !== '') return $typeText;
    if ($id > 0) return '#' . $id;
    return '';
};

$isDeleted = !empty($car['deleted_at']);

// 振り分け
$scheduled = [];
$active = [];
$past = [];

foreach ($leases as $l) {
    $st = (string)($l['status'] ?? '');
    if ($st === 'scheduled') {
        $scheduled[] = $l;
    } elseif ($st === 'active') {
        $active[] = $l;
    } else {
        $past[] = $l;
    }
}

// 過去を年度ごとに
$resolveFiscalYear = function (array $l) use ($fyStartMonth, $fyStartDay): int {
    $base = (string)($l['lease_start_date'] ?? '');

    $endedAt = (string)($l['ended_at'] ?? '');
    $canceledAt = (string)($l['canceled_at'] ?? '');

    if ($endedAt !== '') {
        $base = substr($endedAt, 0, 10);
    } elseif ($canceledAt !== '') {
        $base = substr($canceledAt, 0, 10);
    } elseif ((string)($l['lease_end_date'] ?? '') !== '') {
        $base = (string)$l['lease_end_date'];
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $base)) {
        return 0;
    }

    $y = (int)substr($base, 0, 4);
    $m = (int)substr($base, 5, 2);
    $d = (int)substr($base, 8, 2);

    if ($m < $fyStartMonth || ($m === $fyStartMonth && $d < $fyStartDay)) {
        return $y - 1;
    }
    return $y;
};

$byFy = [];
foreach ($past as $l) {
    $fy = $resolveFiscalYear($l);
    if (!isset($byFy[$fy])) {
        $byFy[$fy] = [];
    }
    $byFy[$fy][] = $l;
}
krsort($byFy);

// 車両サマリー
$carMgmt = (string)($car['management_number'] ?? '');
$carVeh  = (string)($car['vehicle_number'] ?? '');
$carChas = (string)($car['chassis_number'] ?? '');

?>

<div class="k-page car-leases-show-by-car">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">リース詳細</h1>
      <div class="k-page__sub">車両ごとのリース履歴（予定 / リース中 / 過去）</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/cars/<?= $carId ?>">車両詳細へ</a>

      <?php if ($canEdit && !$isDeleted): ?>
        <a class="btn btn-primary" href="/car_leases/create?car_id=<?= $carId ?>">リース登録</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ★追加：開始できないリース -->
  <?php if (!empty($alerts)): ?>
    <div class="alert alert-warning mb-3">
      <strong>開始できない予定リースがあります。</strong>
      <ul class="mb-0 mt-2">
        <?php foreach ($alerts as $a): ?>
          <li>
            <?php if (!empty($a['management_number'])): ?>
              <?= htmlspecialchars($a['management_number'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
            <?php if (!empty($a['vehicle_number'])): ?>
              （<?= htmlspecialchars($a['vehicle_number'], ENT_QUOTES, 'UTF-8') ?>）
            <?php endif; ?>
            :
            <?= htmlspecialchars($a['message'], ENT_QUOTES, 'UTF-8') ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- 車両情報 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="k-section-title">車両情報</div>
    </div>

    <div class="k-card__body">

      <div class="k-show-table-wrap">
        <table class="k-show-table">

          <tr>
            <th>管理番号</th>
            <td><?= htmlspecialchars($carMgmt, ENT_QUOTES, 'UTF-8') ?></td>
          </tr>

          <tr>
            <th>車両番号</th>
            <td><?= htmlspecialchars($carVeh, ENT_QUOTES, 'UTF-8') ?></td>
          </tr>

          <tr>
            <th>車台番号</th>
            <td><?= htmlspecialchars($carChas, ENT_QUOTES, 'UTF-8') ?></td>
          </tr>

        </table>
      </div>

    </div>
  </div>

  <!-- リース予定 -->
  <div class="k-card mb-3">

    <div class="k-card__header">
      <div class="k-section-title">リース予定</div>
    </div>

    <div class="k-card__body">

      <?php if (!empty($scheduled)): ?>

        <table class="table table-sm align-middle k-table">
          <thead>
            <tr>
              <th>リース先</th>
              <th>期間</th>
              <th>月額</th>
              <th>状態</th>
              <?php if ($canEdit): ?><th>操作</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>

          <?php foreach ($scheduled as $l): ?>
            <tr>
              <td><?= htmlspecialchars($lesseeText($l), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($periodText($l), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= number_format((int)$l['monthly_fee']) ?>円</td>
              <td><?= htmlspecialchars($statusLabels[$l['status']] ?? $l['status'], ENT_QUOTES, 'UTF-8') ?></td>

              <?php if ($canEdit): ?>
              <td>
                <a class="btn btn-sm btn-outline-secondary" href="/car_leases/<?= (int)$l['id'] ?>/edit">編集</a>
                <a class="btn btn-sm btn-outline-danger" href="/car_leases/<?= (int)$l['id'] ?>/force_end">終了</a>
              </td>
              <?php endif; ?>

            </tr>
          <?php endforeach; ?>

          </tbody>
        </table>

      <?php else: ?>
        <div class="text-muted">リース予定はありません。</div>
      <?php endif; ?>

    </div>
  </div>

  <!-- リース中 -->
  <div class="k-card mb-3">

    <div class="k-card__header">
      <div class="k-section-title">リース中</div>
    </div>

    <div class="k-card__body">

      <?php if (!empty($active)): ?>

        <table class="table table-sm align-middle k-table">
          <thead>
            <tr>
              <th>リース先</th>
              <th>期間</th>
              <th>月額</th>
              <th>状態</th>
              <?php if ($canEdit): ?><th>操作</th><?php endif; ?>
            </tr>
          </thead>

          <tbody>

          <?php foreach ($active as $l): ?>
            <tr>

              <td><?= htmlspecialchars($lesseeText($l), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($periodText($l), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= number_format((int)$l['monthly_fee']) ?>円</td>
              <td><?= htmlspecialchars($statusLabels[$l['status']] ?? $l['status'], ENT_QUOTES, 'UTF-8') ?></td>

              <?php if ($canEdit): ?>
              <td>
                <a class="btn btn-sm btn-outline-secondary" href="/car_leases/<?= (int)$l['id'] ?>/edit">編集</a>
                <a class="btn btn-sm btn-outline-danger" href="/car_leases/<?= (int)$l['id'] ?>/force_end">終了</a>
              </td>
              <?php endif; ?>

            </tr>
          <?php endforeach; ?>

          </tbody>
        </table>

      <?php else: ?>
        <div class="text-muted">現在リース中ではありません。</div>
      <?php endif; ?>

    </div>
  </div>

  <!-- 過去のリース履歴 -->
  <div class="k-card mb-3">

    <div class="k-card__header">
      <div class="k-section-title">過去のリース履歴</div>
    </div>

    <div class="k-card__body">

      <?php if (!empty($byFy)): ?>

        <?php foreach ($byFy as $fy => $rows): ?>
          <div class="mb-4">
            <h3 class="h6 mb-3"><?= htmlspecialchars((string)$fy, ENT_QUOTES, 'UTF-8') ?>年度</h3>

            <div class="table-responsive">
              <table class="table table-sm align-middle k-table">
                <thead>
                  <tr>
                    <th>リース先</th>
                    <th>期間</th>
                    <th>実終了日</th>
                    <th>月額</th>
                    <th>状態</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $l): ?>
                    <tr>
                      <td><?= htmlspecialchars($lesseeText($l), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($periodText($l), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($actualEndText($l), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= number_format((int)($l['monthly_fee'] ?? 0)) ?>円</td>
                      <td><?= htmlspecialchars($statusLabels[$l['status']] ?? $l['status'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>

      <?php else: ?>
        <div class="text-muted">過去のリース履歴はありません。</div>
      <?php endif; ?>

    </div>
  </div>

</div>