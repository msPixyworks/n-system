<?php
/**
 * views/cars/index.php
 * ============================================================
 * 役割:
 * - 車両一覧（DataTables）
 * - 列ごとの絞り込みフォーム（ボタン押下時のみ反映）
 *
 * 方針:
 * - JSは /public/js/cars.js に分離
 * - 権限（編集可否）は Policies に従い、UIにも反映
 * - 論理削除（deleted_at）運用に合わせ、デフォルトは「未削除のみ」
 *   - 「削除も含める」チェックで f_include_deleted=1 を送る
 *
 * 追加修正（今回）:
 * - サマリーに「在庫」を追加
 * - 絞り込みの「ID」を削除
 */

$cfg = require __DIR__ . '/../../app/config.php';
$canEdit = Policies::canEditCars($me);

// ★追加（今回）: リース中一覧への導線
$canViewLeases = Policies::canViewCarLeases($me);

$optsCars = $cfg['options']['cars'] ?? [];
$makers = $optsCars['maker'] ?? [];
$modelsFlat = $optsCars['car_models_flat'] ?? [];
?>
<div class="k-page cars-index">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">車両一覧</h1>
      <!-- <div class="k-page__sub">車両の検索・一覧表示を行います。</div> -->
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <?php if ($canViewLeases): ?>
        <a class="btn btn-outline-secondary" href="/car_leases/active">リース中一覧</a>
      <?php endif; ?>

      <?php if ($canEdit): ?>
        <a class="btn btn-outline-secondary" href="/cars/bulk">一括編集</a>
        <a class="btn btn-primary" href="/cars/create">新規登録</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- サマリー -->
  <div class="row g-3 mb-3">
    <div class="col-md-2 col-sm-4 col-6">
      <div class="k-card h-100">
        <div class="k-card__body text-center">
          <div class="small text-muted">在庫</div>
          <div class="fs-4 fw-bold" id="sum-stock">0</div>
        </div>
      </div>
    </div>

    <div class="col-md-2 col-sm-4 col-6">
      <div class="k-card h-100">
        <div class="k-card__body text-center">
          <div class="small text-muted">リース中</div>
          <div class="fs-4 fw-bold" id="sum-leasing">0</div>
        </div>
      </div>
    </div>

    <div class="col-md-2 col-sm-4 col-6">
      <div class="k-card h-100">
        <div class="k-card__body text-center">
          <div class="small text-muted">リース予定</div>
          <div class="fs-4 fw-bold" id="sum-scheduled">0</div>
        </div>
      </div>
    </div>

    <div class="col-md-2 col-sm-4 col-6">
      <div class="k-card h-100">
        <div class="k-card__body text-center">
          <div class="small text-muted">代車</div>
          <div class="fs-4 fw-bold" id="sum-loaner">0</div>
        </div>
      </div>
    </div>

    <div class="col-md-2 col-sm-4 col-6">
      <div class="k-card h-100">
        <div class="k-card__body text-center">
          <div class="small text-muted">販売済み</div>
          <div class="fs-4 fw-bold" id="sum-sold">0</div>
        </div>
      </div>
    </div>

    <div class="col-md-2 col-sm-4 col-6">
      <div class="k-card h-100">
        <div class="k-card__body text-center">
          <div class="small text-muted">廃車</div>
          <div class="fs-4 fw-bold" id="sum-scrap">0</div>
        </div>
      </div>
    </div>
  </div>

  <!-- フィルタ -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">絞り込み</div>
        <div class="k-section-sub text-muted">条件を指定して「絞り込み」を押してください。</div>
      </div>
    </div>

    <div class="k-card__body">
      <form id="cars-filter" class="row g-2 align-items-end k-filter" autocomplete="off" onsubmit="return false;">

        <div class="col-md-3">
          <label class="form-label">車両番号</label>
          <input type="text" name="f_vehicle_number" class="form-control" placeholder="部分一致">
        </div>

        <div class="col-md-3">
          <label class="form-label">車台番号</label>
          <input type="text" name="f_chassis_number" class="form-control" placeholder="部分一致">
        </div>

        <div class="col-md-3">
          <label class="form-label">メーカー</label>
          <select name="f_maker" class="form-select">
            <option value="">（すべて）</option>
            <?php foreach ($makers as $k => $v): ?>
              <?php if ((string)$k === '0') continue; ?>
              <option value="<?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">車種</label>
          <select name="f_car_model" class="form-select">
            <option value="">（すべて）</option>
            <?php foreach ($modelsFlat as $k => $v): ?>
              <?php if ((string)$k === '0') continue; ?>
              <option value="<?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">状態</label>
          <select name="f_status" class="form-select">
            <option value="">（すべて）</option>
            <option value="1">在庫</option>
            <option value="2">リース中</option>
            <option value="6">リース予定</option>
            <option value="4">代車</option>
            <option value="3">販売済み</option>
            <option value="5">廃車</option>
          </select>
        </div>

        <div class="col-md-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="f_include_deleted" name="f_include_deleted">
            <label class="form-check-label" for="f_include_deleted">削除も含める</label>
          </div>
        </div>

        <!-- ★ボタンを中央寄せ -->
        <div class="col-12 d-flex justify-content-center gap-2 mt-3">
          <button type="button" id="cars-filter-apply" class="btn btn-outline-primary btn-sm k-btn-filter">絞り込み</button>
          <button type="button" id="cars-filter-reset" class="btn btn-outline-secondary btn-sm k-btn-filter">リセット</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 一覧 -->
  <div class="k-card k-dt">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">一覧</div>
        <div class="k-section-sub text-muted">デフォルトは未削除のみ（削除は「削除も含める」をON）</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="datatable-table-wrap">
        <table
          id="cars-table"
          class="table table-striped w-100 datatable-origin k-table"
          data-cars-url="/api/cars"
          data-lang-url="https://cdn.jsdelivr.net/npm/datatables.net-plugins/i18n/ja.json"
          data-can-edit="<?= $canEdit ? '1' : '0' ?>"
          data-default-include-deleted="0"
        ></table>
      </div>

      <div class="small text-muted mt-2">
        ※ デフォルトは未削除のみ表示（削除は「削除も含める」をON）
      </div>
    </div>
  </div>

</div>

<script src="/js/cars.js"></script>