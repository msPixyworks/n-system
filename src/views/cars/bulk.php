<?php
/**
 * views/cars/bulk.php
 * ============================================================
 * 役割:
 * - 車両一括編集画面
 * - 対象項目:
 *   - 自動車保険料
 *   - 経費総額
 *   - 走行距離
 *
 * 方針:
 * - 既存 cars/index.php の見た目に寄せる
 * - JSは /public/js/carsBulk.js に分離
 * - 一括保存は /api/cars/bulk-update へPOST
 */

$optsCars = $cfg['options']['cars'] ?? [];
$makers = $optsCars['maker'] ?? [];
$modelsFlat = $optsCars['car_models_flat'] ?? [];
$statusLabels = $optsCars['status_code'] ?? [];
$csrfToken = Csrf::token();
?>
<div class="k-page cars-bulk">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">車両一括編集</h1>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/cars">車両一覧へ戻る</a>
    </div>
  </div>

  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">一括編集</div>
        <div class="k-section-sub text-muted">
          自動車保険料・経費総額・走行距離をまとめて更新できます。
        </div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="alert alert-light border mb-3">
        <div class="small text-muted">
          ・論理削除済みの車両は表示していません。<br>
          ・変更があった行だけ保存されます。<br>
          ・空欄で保存すると、その項目は未設定（NULL）になります。
        </div>
      </div>

      <div id="cars-bulk-message" class="mb-3" style="display:none;"></div>

      <form id="cars-bulk-form" method="post" action="/api/cars/bulk-update" autocomplete="off">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="datatable-table-wrap">
          <table class="table table-striped w-100 k-table" id="cars-bulk-table">
            <thead>
              <tr>
                <th style="width:70px;">No</th>
                <th style="width:140px;">メーカー</th>
                <th style="width:180px;">車種</th>
                <th style="width:160px;">車両番号</th>
                <th style="width:100px;">年式</th>
                <th style="width:120px;">状態</th>
                <th style="width:180px;">自動車保険料</th>
                <th style="width:180px;">経費総額</th>
                <th style="width:160px;">走行距離</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $row): ?>
                <?php
                  $id = (int)($row['id'] ?? 0);

                  $makerKey = (string)($row['maker'] ?? '');
                  $makerText = $makerKey !== '' ? (string)($makers[$makerKey] ?? $makerKey) : '';

                  $modelKey = (string)($row['car_model'] ?? '');
                  $modelText = $modelKey !== '' ? (string)($modelsFlat[$modelKey] ?? $modelKey) : '';

                  $statusCode = (string)($row['status_code'] ?? '');
                  $statusText = $statusLabels[$statusCode] ?? $statusCode;

                  $insurance = $row['car_insurance_premium'];
                  $expenses  = $row['total_expenses'];
                  $mileage   = $row['mileage_amount'];
                ?>
                <tr data-id="<?= $id ?>">
                  <td><?= (int)($i + 1) ?></td>
                  <td><?= htmlspecialchars($makerText, ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($modelText, ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($row['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= (int)($row['model_year'] ?? 0) > 0 ? htmlspecialchars((string)$row['model_year'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                  <td><?= htmlspecialchars((string)$statusText, ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <input
                      type="text"
                      class="form-control form-control-sm js-bulk-num"
                      inputmode="numeric"
                      data-field="car_insurance_premium"
                      value="<?= $insurance !== null ? htmlspecialchars(number_format((int)$insurance), ENT_QUOTES, 'UTF-8') : '' ?>"
                      placeholder="例: 120,000"
                    >
                  </td>
                  <td>
                    <input
                      type="text"
                      class="form-control form-control-sm js-bulk-num"
                      inputmode="numeric"
                      data-field="total_expenses"
                      value="<?= $expenses !== null ? htmlspecialchars(number_format((int)$expenses), ENT_QUOTES, 'UTF-8') : '' ?>"
                      placeholder="例: 350,000"
                    >
                  </td>
                  <td>
                    <input
                      type="text"
                      class="form-control form-control-sm js-bulk-num"
                      inputmode="numeric"
                      data-field="mileage_amount"
                      value="<?= $mileage !== null ? htmlspecialchars(number_format((int)$mileage), ENT_QUOTES, 'UTF-8') : '' ?>"
                      placeholder="例: 42,000"
                    >
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-center gap-2 mt-4">
          <button type="button" id="cars-bulk-save" class="btn btn-primary">変更を保存</button>
          <a href="/cars" class="btn btn-outline-secondary">戻る</a>
        </div>
      </form>
    </div>
  </div>

</div>

<script src="/js/carsBulk.js"></script>