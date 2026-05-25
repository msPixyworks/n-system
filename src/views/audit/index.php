<?php
/**
 * views/audit/index.php
 * ============================================================
 * 役割:
 * - 監査ログ一覧画面（DataTables）
 * - 絞り込みフォーム（module/action/date range/new value/表示オプション）
 * - 差分表示モーダル（/api/audit/{id}/details）
 *
 * 改修ポイント:
 * - JS を /public/js/auditLog.js に分離（保守性向上）
 * - 初期化に必要な値は data-* でJSへ渡す
 *
 * 追加（login_failed/login_blocked 対応）:
 * - 失敗ログ等は actor_user_id が NULL のため「実行者メール」列は '-' になる
 * - 詳細（差分）に email_masked / reason / blocked_until 等が出る想定
 *
 * 追加（要望反映）:
 * - 一覧表示から「UA」と「種別」は不要（DataTables列もそれに合わせる）
 *   ※列定義は /public/js/auditLog.js 側で調整済み前提
 *
 * 追加（今回）:
 * - 監査ログ画面ヘッダーに「リース整合性チェック」への導線を追加
 */

$cfg = require __DIR__ . '/../../app/config.php';

$moduleOptions = $cfg['modules'] ?? [];
$actionOptions = $cfg['audit_actions'] ?? [];

$canViewLeaseHealth = false;
if (isset($me) && $me) {
    $canViewLeaseHealth = Policies::canViewCarLeaseHealth($me);
}
?>
<div class="k-page audit-index">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">監査ログ</h1>
      <div class="k-page__sub">操作履歴を検索・確認します（差分は詳細で表示）。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <?php if ($canViewLeaseHealth): ?>
        <a class="btn btn-outline-secondary" href="/car_leases/health">リース整合性チェック</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- 注意 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">注意</div>
        <div class="k-section-sub text-muted">login_failed / login_blocked</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="small">
        <div>※ ログイン失敗・制限（login_failed / login_blocked）は、実行者（actor）が特定できないため「ユーザーID」が <strong>-</strong> になります。</div>
        <div>　詳細（差分）を開くと、<strong>ユーザーID（マスク）</strong>や<strong>理由</strong>などが表示されます。</div>
      </div>
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
      <form id="audit-search" class="row g-2 align-items-end k-filter" onsubmit="return false;">
        <div class="col-md-3">
          <label class="form-label">モジュール</label>
          <select name="module" class="form-select">
            <option value="">（すべて）</option>
            <?php foreach ($moduleOptions as $key => $label): ?>
              <option value="<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">アクション</label>
          <select name="action" class="form-select">
            <option value="">（すべて）</option>
            <?php foreach ($actionOptions as $key => $label): ?>
              <option value="<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">日付From</label>
          <input type="date" name="date_from" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label">日付To</label>
          <input type="date" name="date_to" class="form-control">
        </div>

        <div class="col-md-6">
          <label class="form-label">新値（差分）に含まれる文字列</label>
          <input type="text" name="f_new_value" class="form-control" placeholder="例: マネージャー / あり / レート制限 / 退職済み など">
        </div>

        <div class="col-md-6">
          <label class="form-label">表示オプション</label>
          <div class="d-flex flex-wrap gap-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" id="onlyChanged" name="only_changed">
              <label class="form-check-label" for="onlyChanged">差分があるログだけ表示（差分なしは非表示）</label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" id="excludeCreate" name="exclude_create">
              <label class="form-check-label" for="excludeCreate">作成（create）を表示しない</label>
            </div>
          </div>
          <div class="form-text">
            ※「保存しない」のではなく「一覧に出さない」ための表示フィルタです。
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex justify-content-center gap-2 mt-3">
            <button type="button" id="audit-filter-apply" class="btn btn-outline-primary btn-sm k-btn-filter">絞り込み</button>
            <button type="button" id="audit-filter-reset" class="btn btn-outline-secondary btn-sm k-btn-filter">リセット</button>
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
        <div class="k-section-sub text-muted">※ 「UA」「種別」は差分（詳細）で確認</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="datatable-table-wrap">
        <table
          id="audit-table"
          class="table table-striped w-100 datatable-origin k-table"
          data-audit-url="/api/audit"
          data-details-url-template="/api/audit/{id}/details"
          data-lang-url="https://cdn.jsdelivr.net/npm/datatables.net-plugins/i18n/ja.json"
        ></table>
      </div>

      <div class="form-text mt-2">
        ※ 一覧表示には「UA」「種別」は表示しません（必要な場合は差分（詳細）で確認してください）。
      </div>
    </div>
  </div>

</div>

<!-- 差分表示用モーダル -->
<div class="modal fade k-modal" id="diffModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">差分（変更点）</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body">
        <div id="diffAlert" class="alert alert-info d-none">差分はありません。</div>

        <div class="table-responsive">
          <table class="table table-sm align-middle k-table">
            <thead>
              <tr>
                <th style="width: 25%;">フィールド</th>
                <th style="width: 37.5%;">旧値</th>
                <th style="width: 37.5%;">新値</th>
              </tr>
            </thead>
            <tbody id="diffBody">
              <tr><td colspan="3" class="text-muted">読み込み中…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">閉じる</button>
      </div>
    </div>
  </div>
</div>

<script src="/js/auditLog.js"></script>