<?php
/**
 * views/car_costs/index.php
 * ============================================================
 * 車両 年度別コスト一覧
 *
 * 方針:
 * - 当年度: cars の現在値を表示（編集不可）
 * - 過去年度: car_fy_costs を表示（編集可）
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me  = $me  ?? Auth::user();

$canEdit = $me ? Policies::canEditCarFyCosts($me) : false;

$carId = (int)($car['id'] ?? 0);
$currentFy = (int)$currentFy;

$fmt = fn($v) => ($v === null || $v === '') ? '—' : number_format((int)$v) . ' 円';
?>
<div class="k-page car-fy-costs-index">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">年度別コスト</h1>
      <div class="k-page__sub">当年度は参照のみ、過去年度は編集できます。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/cars/<?= $carId ?>">車両詳細へ</a>
      <a class="btn btn-outline-secondary" href="/cars/<?= $carId ?>/profit">収支を見る</a>
    </div>
  </div>

  <!-- 車両概要 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">車両概要</div>
        <div class="k-section-sub text-muted">管理番号 / 車両番号</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>
            <tr>
              <th>管理番号</th>
              <td colspan="3"><?= htmlspecialchars((string)($car['management_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
              <th>車両番号</th>
              <td colspan="3"><?= htmlspecialchars((string)($car['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 当年度 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title"><?= $currentFy ?> 年度（当年度）</div>
        <div class="k-section-sub text-muted">
          <span class="badge text-bg-secondary">参照のみ</span>
        </div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle k-table">
          <thead>
            <tr>
              <th>自動車税</th>
              <th>保険料</th>
              <th>経費</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?= $fmt($car['car_tax'] ?? null) ?></td>
              <td><?= $fmt($car['car_insurance_premium'] ?? null) ?></td>
              <td><?= $fmt($car['total_expenses'] ?? null) ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="small text-muted mt-2">
        ※ 当年度は車両情報（cars）の現在値を使用します。
      </div>
    </div>
  </div>

  <!-- 過去年度 -->
  <?php if (!empty($rows)): ?>
    <?php foreach ($rows as $r): ?>
      <?php
        $fy = (int)$r['fy'];
        if ($fy >= $currentFy) continue; // 当年度以上は除外
      ?>
      <div class="k-card mb-3">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title"><?= $fy ?> 年度</div>
            <div class="k-section-sub text-muted">
              <?php if ($canEdit): ?>
                <a class="btn btn-sm btn-outline-primary" href="/cars/<?= $carId ?>/fy_costs/<?= $fy ?>/edit">編集</a>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="k-card__body">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle k-table">
              <thead>
                <tr>
                  <th>自動車税</th>
                  <th>保険料</th>
                  <th>経費</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><?= $fmt($r['tax_amount'] ?? null) ?></td>
                  <td><?= $fmt($r['insurance_amount'] ?? null) ?></td>
                  <td><?= $fmt($r['expense_amount'] ?? null) ?></td>
                </tr>
              </tbody>
            </table>
          </div>

          <?php if (!empty($r['notes'])): ?>
            <div class="small text-muted mt-2">
              備考: <?= nl2br(htmlspecialchars((string)$r['notes'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="text-muted">過去年度のコストはまだありません。</div>
  <?php endif; ?>

</div>
