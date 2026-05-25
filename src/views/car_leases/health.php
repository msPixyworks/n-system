<?php
/**
 * views/car_leases/health.php
 * ============================================================
 * リース整合性チェック一覧
 *
 * 前提:
 * - DataTables（server-side ではなく API結果をそのまま返す形でも使える構成）
 * - JS: /public/js/carLeaseHealth.js
 * - K-Core の一覧画面デザインに統一
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me  = $me  ?? Auth::user();

$issueLabels    = (isset($issueLabels) && is_array($issueLabels)) ? $issueLabels : [];
$severityLabels = (isset($severityLabels) && is_array($severityLabels)) ? $severityLabels : [];
?>

<div class="k-page car-leases-health">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">リース整合性チェック</h1>
      <div class="k-page__sub">車両リースの不整合・異常値を検出して一覧表示します。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/car_leases/active">リース中一覧へ</a>
    </div>
  </div>

  <!-- サマリー -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">サマリー</div>
        <div class="k-section-sub text-muted">現在検出されている異常件数</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="d-flex flex-wrap gap-3 align-items-center">
        <span class="badge text-bg-danger">
          異常件数：<span id="lease-health-total-count">0</span> 件
        </span>
        <span class="small text-muted">
          ※ 絞り込み前の総件数
        </span>
      </div>
    </div>
  </div>

  <!-- 絞り込み -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">絞り込み</div>
        <div class="k-section-sub text-muted">異常種別 / 車両ID / 管理番号 / リースID</div>
      </div>
    </div>
    <div class="k-card__body">
      <form id="lease-health-filter" class="row g-3 align-items-end k-filter" onsubmit="return false;">

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">異常種別</label>
          <select name="f_issue_type" class="form-select">
            <option value="">（すべて）</option>
            <?php foreach ($issueLabels as $k => $v): ?>
              <option value="<?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label">車両ID</label>
          <input type="number" min="1" step="1" name="f_car_id" class="form-control" placeholder="例: 12">
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">管理番号</label>
          <input type="text" name="f_management_number" class="form-control" placeholder="部分一致">
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label">リースID</label>
          <input type="number" min="1" step="1" name="f_lease_id" class="form-control" placeholder="例: 5">
        </div>

        <div class="col-12">
          <div class="d-flex justify-content-center gap-3 mt-4">
            <button type="button"
                    id="lease-health-filter-apply"
                    class="btn btn-outline-primary btn-sm k-btn-filter">
              絞り込み
            </button>

            <button type="button"
                    id="lease-health-filter-reset"
                    class="btn btn-outline-secondary btn-sm k-btn-filter">
              リセット
            </button>
          </div>
        </div>

      </form>
    </div>
  </div>

  <!-- 一覧 -->
  <div class="k-card k-dt">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">一覧</div>
        <div class="k-section-sub text-muted">異常内容ごとに車両詳細・リース詳細へ遷移できます</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="datatable-table-wrap">
        <table
          id="car-lease-health-table"
          class="table table-striped w-100 datatable-origin k-table"
          data-api-url="/api/car_leases/health"
          data-lang-url="https://cdn.jsdelivr.net/npm/datatables.net-plugins/i18n/ja.json"
          data-has-action-col="1"
        ></table>
      </div>
    </div>
  </div>

</div>

<script src="/js/carLeaseHealth.js"></script>