<?php
/**
 * views/car_profits/show.php
 * ============================================================
 * 車両 収支画面
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me  = $me  ?? Auth::user();

$carId = (int)($car['id'] ?? 0);
$fmt = fn($v) => number_format((int)$v) . ' 円';
?>
<div class="k-page car-profits-show">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">車両収支</h1>
      <div class="k-page__sub">年度収支と累積収支を表示します。</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/cars/<?= $carId ?>">車両詳細へ</a>
      <a class="btn btn-outline-secondary" href="/cars/<?= $carId ?>/fy_costs">年度別コスト</a>
    </div>
  </div>

  <!-- 年度選択 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">年度選択</div>
        <div class="k-section-sub text-muted">対象期間：<?= htmlspecialchars($fyStart) ?> ～ <?= htmlspecialchars($fyEnd) ?></div>
      </div>
    </div>
    <div class="k-card__body">
      <form method="get" class="row g-2 align-items-end k-filter">
        <div class="col-12 col-md-3">
          <label class="form-label">年度</label>
          <input type="number" name="fy" class="form-control" value="<?= (int)$fy ?>">
        </div>
        <div class="col-12 col-md-3">
          <button type="submit" class="btn btn-primary">表示</button>
        </div>
        <div class="col-12 col-md-6 text-muted">
          対象期間：<?= htmlspecialchars($fyStart) ?> ～ <?= htmlspecialchars($fyEnd) ?>
        </div>
      </form>
    </div>
  </div>

  <!-- 年度収支 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title"><?= (int)$fy ?> 年度 収支</div>
        <div class="k-section-sub text-muted">年度内の収入・支出・収支</div>
      </div>
    </div>
    <div class="k-card__body">

      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>
            <tr>
              <th>収入合計</th>
              <td colspan="3"><?= $fmt($incomeTotal) ?></td>
            </tr>
            <tr>
              <th>支出合計</th>
              <td colspan="3"><?= $fmt($costTotal) ?></td>
            </tr>
            <tr>
              <th>年度収支</th>
              <td colspan="3" class="<?= $profitFy >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= $fmt($profitFy) ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="small text-muted mt-2">
        支出参照元：
        <?php if ($costSource === 'cars'): ?>
          当年度（cars の現在値）
        <?php elseif ($costSource === 'car_fy_costs'): ?>
          年度別コスト台帳
        <?php else: ?>
          <span class="text-warning">年度別コスト未登録</span>
        <?php endif; ?>
        <?php if ($costNote): ?>
          <div class="text-warning"><?= htmlspecialchars($costNote, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- 収入内訳 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">収入内訳（リース）</div>
        <div class="k-section-sub text-muted">該当年度のリース収入</div>
      </div>
    </div>
    <div class="k-card__body">
      <?php if (!empty($incomeDetails)): ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle k-table">
            <thead>
              <tr>
                <th>リースID</th>
                <th>期間</th>
                <th>月数</th>
                <th class="text-end">月額</th>
                <th class="text-end">金額</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($incomeDetails as $d): ?>
                <tr>
                  <td><?= (int)$d['lease_id'] ?></td>
                  <td>
                    <?= htmlspecialchars($d['overlap_start']) ?>
                    ～ 
                    <?= htmlspecialchars($d['overlap_end']) ?>
                  </td>
                  <td><?= (int)$d['months'] ?> ヶ月</td>
                  <td class="text-end"><?= $fmt($d['monthly_fee']) ?></td>
                  <td class="text-end"><?= $fmt($d['amount']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="text-muted">この年度に収入はありません。</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 累積収支 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">累積収支</div>
        <div class="k-section-sub text-muted">累積の収入・支出・収支</div>
      </div>
    </div>
    <div class="k-card__body">

      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>
            <tr>
              <th>累積収入</th>
              <td colspan="3"><?= $fmt($incomeAll) ?></td>
            </tr>
            <tr>
              <th>累積支出</th>
              <td colspan="3"><?= $fmt($costAll) ?></td>
            </tr>
            <tr>
              <th>累積収支</th>
              <td colspan="3" class="<?= $profitAll >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= $fmt($profitAll) ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="small text-muted mt-2">
        ※ 累積支出 = 購入代金 + 過去年度コスト合計 + 当年度コスト
      </div>

    </div>
  </div>

</div>
