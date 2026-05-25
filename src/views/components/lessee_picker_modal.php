<?php
/**
 * views/components/lessee_picker_modal.php
 * ============================================================
 * リース先（法人/個人）横断検索ピッカー（モーダル）
 *
 * 前提:
 * - /api/lessees を DataTables で叩く
 * - JS: /public/js/components/lesseePicker.js が操作する
 */

$cfg = $cfg ?? (require __DIR__ . '/../../../app/config.php');

$leaseOpts = $cfg['options']['car_leases'] ?? [];
$lesseeTypes = $leaseOpts['lessee_types'] ?? ['office' => '法人', 'personal' => '個人'];
?>
<div class="modal fade k-modal" id="lesseePickerModal" tabindex="-1" aria-labelledby="lesseePickerLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="lesseePickerLabel">リース先を選択</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>

      <div class="modal-body">

        <div class="k-card mb-3">
          <div class="k-card__header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="k-section-title">検索条件</div>
              <div class="k-section-sub text-muted">種別・キーワードで検索</div>
            </div>
          </div>
          <div class="k-card__body">
            <form id="lessee-picker-filter" class="row g-2 align-items-end k-filter" onsubmit="return false;">
              <div class="col-12 col-md-3">
                <label class="form-label">種別</label>
                <select name="f_type" class="form-select">
                  <option value="">（すべて）</option>
                  <?php foreach ($lesseeTypes as $k => $v): ?>
                    <option value="<?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">キーワード</label>
                <input type="text" name="f_q" class="form-control" placeholder="名前/フリガナ/TEL/住所 など">
              </div>

              <div class="col-12 col-md-3">
                <div class="d-flex justify-content-center gap-2 mt-3 mt-md-0">
                  <button type="button" id="lessee-picker-apply" class="btn btn-outline-primary btn-sm k-btn-filter">検索</button>
                  <button type="button" id="lessee-picker-reset" class="btn btn-outline-secondary btn-sm k-btn-filter">リセット</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="k-card k-dt">
          <div class="k-card__header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="k-section-title">候補一覧</div>
              <div class="k-section-sub text-muted">「選択」でフォームへ反映</div>
            </div>
          </div>
          <div class="k-card__body">
            <div class="datatable-table-wrap">
              <table
                id="lessee-picker-table"
                class="table table-striped w-100 datatable-origin k-table"
                data-api-url="/api/lessees"
                data-lang-url="https://cdn.jsdelivr.net/npm/datatables.net-plugins/i18n/ja.json"
              ></table>
            </div>

            <div class="small text-muted mt-2">
              ※ 「選択」を押すと、フォームへ反映されます。
            </div>
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">閉じる</button>
      </div>
    </div>
  </div>
</div>
