<?php
/**
 * views/car_leases/index_active.php
 * ============================================================
 * リース中車両一覧（予定/中 含む）
 *
 * 方針:
 * - DataTables（server-side）
 * - scheduled（予定）+ active（リース中）を表示（絞り込みで切替）
 * - 操作列は常に表示（閲覧でも「詳細」などを出す）
 *
 * UI:
 * - 上部に「リース中：〇台 / リース予定：〇台」（期間フィルタ適用後の件数）を表示
 * - 状態絞り込み（両方/リース中のみ/リース予定のみ）
 * - リース先ジャンプ（ピッカー）カード
 * - 期間しぼりこみカード
 *
 * JS:
 * - /public/js/carLeasesActive.js
 * - /public/js/components/lesseePicker.js（ピッカー用）
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me  = $me  ?? Auth::user();

$canEdit = $me ? Policies::canEditCarLeases($me) : false;

$alerts = $alerts ?? [];
?>

<div class="k-page car-leases-active">

  <!-- タイトル -->
  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">リース中車両一覧（予定/中）</h1>
      <div class="k-page__sub">リース予定・リース中の車両を一覧表示します。リース先・期間・状態で絞り込みできます。</div>
    </div>
  </div>

  <!-- ★追加：リース開始不可アラート -->
  <?php if (!empty($alerts)): ?>
    <div class="alert alert-warning mb-3">
      <strong>リース開始できない契約があります。</strong>
      <ul class="mb-0 mt-2">
        <?php foreach ($alerts as $a): ?>
          <li>
            車両
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

  <!-- 件数表示 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">サマリー</div>
        <div class="k-section-sub text-muted">※ 期間フィルタ適用後の件数</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="d-flex flex-wrap gap-3 align-items-center">
        <span class="badge text-bg-info">
          リース中：<span id="lease-count-active">0</span> 台
        </span>
        <span class="badge text-bg-warning">
          リース予定：<span id="lease-count-scheduled">0</span> 台
        </span>
        <span class="small text-muted">
          ※ 期間フィルタ適用後の件数
        </span>
      </div>
    </div>
  </div>

  <!-- リース先ジャンプ -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">リース先で絞り込み（ジャンプ）</div>
        <div class="k-section-sub text-muted">ピッカーで選択して対象へ移動</div>
      </div>
    </div>
    <div class="k-card__body">
      <?php
        require __DIR__ . '/../components/lessee_jump_inline.php';
        require __DIR__ . '/../components/lessee_picker_modal.php';
      ?>
    </div>
  </div>

  <!-- 絞り込み -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">絞り込み</div>
        <div class="k-section-sub text-muted">状態 / 開始日 / 終了日 を指定</div>
      </div>
    </div>
    <div class="k-card__body">

      <form id="lease-filter" class="row g-3 align-items-end k-filter" onsubmit="return false;">

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">状態</label>
          <select name="f_status" class="form-select">
            <option value="both">両方</option>
            <option value="active">リース中のみ</option>
            <option value="scheduled">リース予定のみ</option>
          </select>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">期間（開始日以降）</label>
          <input type="date" name="f_from" class="form-control">
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">期間（終了日以前）</label>
          <input type="date" name="f_to" class="form-control">
        </div>

        <div class="col-12">
          <div class="d-flex justify-content-center gap-3 mt-4">
            <button type="button" id="lease-filter-apply" class="btn btn-outline-primary btn-sm k-btn-filter">
              絞り込み
            </button>

            <button type="button" id="lease-filter-reset" class="btn btn-outline-secondary btn-sm k-btn-filter">
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

        <div class="d-flex align-items-center flex-wrap gap-2">
          <a href="/car_leases/export"
             id="car-leases-export-btn"
             class="btn btn-outline-success btn-sm">
            CSVダウンロード
          </a>
        </div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="datatable-table-wrap">
        <table
          id="car-leases-active-table"
          class="table table-striped w-100 datatable-origin k-table"
          data-api-url="/api/car_leases/active"
          data-export-url="/car_leases/export"
          data-lang-url="https://cdn.jsdelivr.net/npm/datatables.net-plugins/i18n/ja.json"
          data-can-edit="<?= $canEdit ? '1' : '0' ?>"
          data-has-action-col="1"
        ></table>
      </div>
    </div>
  </div>

</div>

<script src="/js/carLeasesActive.js"></script>
<script src="/js/components/lesseePicker.js" defer></script>