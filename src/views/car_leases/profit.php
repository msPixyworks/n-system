<?php
/**
 * views/car_leases/profit.php
 * ============================================================
 * 全車両横断のリース収支集計
 *
 * 表示内容:
 * - 指定期間フィルタ
 * - サマリー
 * - 経費内訳
 * - 車両別一覧
 *
 * JS:
 * - /public/js/carLeaseProfit.js
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me  = $me  ?? Auth::user();

$defaultFrom = (string)($defaultFrom ?? '');
$defaultTo   = (string)($defaultTo ?? '');
$defaultMode = (string)($defaultMode ?? 'all');
if (!in_array($defaultMode, ['all', 'revenue', 'deficit'], true)) {
    $defaultMode = 'all';
}
?>

<div class="k-page car-leases-profit">

  <!-- タイトル -->
  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">車両リース収支集計</h1>
      <div class="k-page__sub">指定期間で全車両の売上・経費・利益を集計します。</div>
    </div>
  </div>

  <!-- 絞り込み -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">絞り込み</div>
        <div class="k-section-sub text-muted">期間 / 表示区分</div>
      </div>
    </div>

    <div class="k-card__body">
      <form id="car-lease-profit-filter" class="row g-3 align-items-end k-filter" onsubmit="return false;">

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">集計開始日</label>
          <input
            type="date"
            name="f_from"
            class="form-control"
            value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">集計終了日</label>
          <input
            type="date"
            name="f_to"
            class="form-control"
            value="<?= htmlspecialchars($defaultTo, ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">表示区分</label>
          <select name="f_mode" class="form-select">
            <option value="all" <?= $defaultMode === 'all' ? 'selected' : '' ?>>全車両</option>
            <option value="revenue" <?= $defaultMode === 'revenue' ? 'selected' : '' ?>>売上ありのみ</option>
            <option value="deficit" <?= $defaultMode === 'deficit' ? 'selected' : '' ?>>赤字のみ</option>
          </select>
        </div>

        <div class="col-12">
          <div class="d-flex justify-content-center gap-3 mt-4">
            <button
              type="button"
              id="car-lease-profit-apply"
              class="btn btn-outline-primary btn-sm k-btn-filter"
            >
              絞り込み
            </button>

            <button
              type="button"
              id="car-lease-profit-reset"
              class="btn btn-outline-secondary btn-sm k-btn-filter"
            >
              リセット
            </button>
          </div>
        </div>

      </form>

      <div id="car-lease-profit-error"
           class="alert alert-danger mt-3 d-none"
           role="alert"></div>
    </div>
  </div>

  <!-- サマリー -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">サマリー</div>
        <div class="k-section-sub text-muted">指定期間の全体集計</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle k-table">
          <thead>
            <tr>
              <th>総売上</th>
              <th>総経費</th>
              <th>総利益</th>
              <th>対象車両台数</th>
              <th>売上あり台数</th>
              <th>赤字台数</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td id="profit-summary-revenue">0 円</td>
              <td id="profit-summary-expense">0 円</td>
              <td id="profit-summary-profit">0 円</td>
              <td id="profit-summary-cars">0 台</td>
              <td id="profit-summary-revenue-cars">0 台</td>
              <td id="profit-summary-deficit-cars">0 台</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 経費内訳 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">経費内訳</div>
        <div class="k-section-sub text-muted">指定期間に按分された内訳</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle k-table">
          <thead>
            <tr>
              <th>購入価格按分</th>
              <th>リサイクル費按分</th>
              <th>購入時諸費用按分</th>
              <th>自動車税按分</th>
              <th>自動車保険料按分</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td id="profit-breakdown-purchase">0 円</td>
              <td id="profit-breakdown-recycling">0 円</td>
              <td id="profit-breakdown-purchase-costs">0 円</td>
              <td id="profit-breakdown-tax">0 円</td>
              <td id="profit-breakdown-insurance">0 円</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 一覧 -->
  <div class="k-card k-dt">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">一覧</div>
        <div class="k-section-sub text-muted">車両別の収支明細</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="datatable-table-wrap">
        <table
          id="car-lease-profit-table"
          class="table table-striped w-100 datatable-origin k-table"
          data-api-url="/api/car_leases/profit/datatable"
          data-summary-url="/api/car_leases/profit/summary"
          data-lang-url="https://cdn.jsdelivr.net/npm/datatables.net-plugins/i18n/ja.json"
        ></table>
      </div>
    </div>
  </div>

</div>

<script src="/js/carLeaseProfit.js"></script>