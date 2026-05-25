<?php
/**
 * views/office_customers/index.php
 * ============================================================
 * 役割:
 * - 法人顧客一覧（DataTables）
 * - 列ごとの絞り込みフォーム（ボタン押下時のみ反映）
 *
 * 方針（users/cars と同期）:
 * - JSは /public/js/officeCustomers.js に分離
 * - 権限（編集可否）は Policies に従い、UIにも反映
 * - 論理削除（deleted_at）運用に合わせ、デフォルトは「削除除外」
 *   - 「削除も含める」チェックで f_include_deleted=1
 */

// app/config.php（辞書）を読み込み
$cfg = require __DIR__ . '/../../app/config.php';

// 編集権限（viewからJSへ渡す）
$canEdit = Policies::canEditOfficeCustomers($me);

$pref = $cfg['prefectures'] ?? [];
$bg = $cfg['office_customer_backgrounds'] ?? [1 => 'HP', 2 => 'チラシ', 3 => '営業'];
?>
<div class="k-page office-customers-index">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">法人顧客一覧</h1>
      <div class="k-page__sub">法人顧客の検索・一覧表示を行います。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <?php if ($canEdit): ?>
        <a class="btn btn-primary" href="/office_customers/create">新規登録</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- 絞り込みフォーム -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">絞り込み</div>
        <div class="k-section-sub text-muted">条件を指定して「絞り込み」を押してください。</div>
      </div>
    </div>

    <div class="k-card__body">
      <form id="office-customers-filter" class="row g-2 align-items-end k-filter" autocomplete="off" onsubmit="return false;">
        <div class="col-md-2">
          <label class="form-label">ID</label>
          <input type="number" min="1" step="1" name="f_id" class="form-control" placeholder="例: 1">
        </div>

        <div class="col-md-4">
          <label class="form-label">会社名</label>
          <input type="text" name="f_name" class="form-control" placeholder="部分一致">
        </div>

        <div class="col-md-3">
          <label class="form-label">担当者</label>
          <input type="text" name="f_manager" class="form-control" placeholder="部分一致">
        </div>

        <div class="col-md-3">
          <label class="form-label">TEL</label>
          <input type="text" name="f_tel" class="form-control" placeholder="部分一致（ハイフン可）">
        </div>

        <div class="col-md-2">
          <label class="form-label">郵便番号</label>
          <input type="text" name="f_zip" class="form-control" placeholder="部分一致">
        </div>

        <div class="col-md-4">
          <label class="form-label">都道府県</label>
          <select name="f_pref_code" class="form-select">
            <option value="">（すべて）</option>
            <?php foreach ($pref as $k => $v): ?>
              <option value="<?= (int)$k ?>"><?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">来社経緯</label>
          <select name="f_background" class="form-select">
            <option value="">（すべて）</option>
            <?php foreach ($bg as $k => $v): ?>
              <option value="<?= (int)$k ?>"><?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- 論理削除 -->
        <div class="col-md-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="f_include_deleted" name="f_include_deleted">
            <label class="form-check-label" for="f_include_deleted">削除も含める</label>
          </div>
        </div>

        <!-- ★ボタンを全体中央へ -->
        <div class="col-12">
          <div class="d-flex justify-content-center gap-2 mt-3">
            <button type="button" id="office-customers-filter-apply" class="btn btn-outline-primary btn-sm k-btn-filter">絞り込み</button>
            <button type="button" id="office-customers-filter-reset" class="btn btn-outline-secondary btn-sm k-btn-filter">リセット</button>
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
        <div class="k-section-sub text-muted">※ デフォルトは削除除外（削除も含める場合は「削除も含める」をON）</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="datatable-table-wrap">
        <table
          id="office-customers-table"
          class="table table-striped w-100 datatable-origin k-table"
          data-office-customers-url="/api/office_customers"
          data-lang-url="https://cdn.jsdelivr.net/npm/datatables.net-plugins/i18n/ja.json"
          data-can-edit="<?= $canEdit ? '1' : '0' ?>"
          data-default-include-deleted="0"
        ></table>
      </div>

      <div class="small text-muted mt-2">
        ※ デフォルトは削除除外（削除も含める場合は「削除も含める」をON）
      </div>
    </div>
  </div>

</div>

<script src="/js/officeCustomers.js"></script>
