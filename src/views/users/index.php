<?php
/**
 * views/users/index.php
 * ============================================================
 * 役割:
 * - ユーザー一覧（DataTables）
 * - 列ごとの絞り込みフォーム（ボタン押下時のみ反映）
 *
 * 方針（K-Core安全側）:
 * - JSは /public/js/users.js に分離（viewには最小限のdata設定だけ渡す）
 * - 権限（編集可否）は Policies に従い、UIにも反映する
 * - 論理削除（resigned_on）運用に合わせ、デフォルトは「在籍者のみ」
 *   - 「退職者も含める」チェックで f_include_resigned=1 を送る
 *
 * 追加（安全側の小改善）:
 * - DataTablesの初期化で “デフォルトは在籍者のみ” を JS 側でも明確に扱えるよう
 *   data-default-include-resigned を付与（JSが必要なら利用）
 */

// app/config.php（roles 等の辞書）を読み込み
$cfg = require __DIR__ . '/../../app/config.php';

// 編集権限（viewからJSへ渡す）
$canEdit = Policies::canEditUsers($me);
?>
<div class="k-page users-index">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">ユーザー一覧</h1>
      <div class="k-page__sub">ユーザーの検索・一覧表示を行います。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <?php if ($canEdit): ?>
        <a class="btn btn-primary" href="/users/create">新規登録</a>
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
      <form id="users-filter" class="row g-2 align-items-end k-filter" autocomplete="off" onsubmit="return false;">
        <div class="col-md-2">
          <label class="form-label">ID</label>
          <input type="number" min="1" step="1" name="f_id" class="form-control" placeholder="例: 1">
        </div>

        <div class="col-md-2">
          <label class="form-label">社員コード</label>
          <input type="text" name="f_employee_code" class="form-control" placeholder="部分一致">
        </div>

        <div class="col-md-3">
          <label class="form-label">権限</label>
          <select name="f_role_code" class="form-select">
            <option value="">（すべて）</option>
            <?php foreach (($cfg['roles'] ?? []) as $k => $v): ?>
              <option value="<?= (int)$k ?>"><?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">氏名</label>
          <input type="text" name="f_name" class="form-control" placeholder="部分一致">
        </div>

        <div class="col-md-4">
          <label class="form-label">ユーザーID（メール）</label>
          <input type="text" name="f_email" class="form-control" placeholder="部分一致">
        </div>

        <div class="col-md-2">
          <label class="form-label">契約入力権限</label>
          <select name="f_contract" class="form-select">
            <option value="">（すべて）</option>
            <option value="1">あり</option>
            <option value="0">なし</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">未契約入力権限</label>
          <select name="f_uncontract" class="form-select">
            <option value="">（すべて）</option>
            <option value="1">あり</option>
            <option value="0">なし</option>
          </select>
        </div>

        <!-- 論理削除（退職者） -->
        <div class="col-md-3">
          <div class="form-check">
            <!-- デフォルトOFF（=在籍者のみ） -->
            <input class="form-check-input" type="checkbox" value="1" id="f_include_resigned" name="f_include_resigned">
            <label class="form-check-label" for="f_include_resigned">退職者も含める</label>
          </div>
        </div>

        <!-- ★ボタンを全体中央へ -->
        <div class="col-12">
          <div class="d-flex justify-content-center gap-2 mt-3">
            <button type="button" id="users-filter-apply" class="btn btn-outline-primary btn-sm k-btn-filter">絞り込み</button>
            <button type="button" id="users-filter-reset" class="btn btn-outline-secondary btn-sm k-btn-filter">リセット</button>
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
        <div class="k-section-sub text-muted">※ デフォルトは在籍者のみ（退職者は「退職者も含める」をON）</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="datatable-table-wrap">
        <table
          id="users-table"
          class="table table-striped w-100 datatable-origin k-table"
          data-users-url="/api/users"
          data-lang-url="https://cdn.jsdelivr.net/npm/datatables.net-plugins/i18n/ja.json"
          data-can-edit="<?= $canEdit ? '1' : '0' ?>"
          data-default-include-resigned="0"
        ></table>
      </div>

      <div class="small text-muted mt-2">
        ※ デフォルトは在籍者のみ表示（退職者は「退職者も含める」をON）
      </div>
    </div>
  </div>

</div>

<!-- users専用JS（外部ファイル） -->
<script src="/js/users.js"></script>
