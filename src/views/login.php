<?php
/**
 * views/login.php
 * ============================================================
 * 役割:
 * - ログイン画面
 *
 * セキュリティ方針（K-Core）:
 * - エラーメッセージは固定文言（ユーザー存在有無を漏らさない）
 * - CSRFトークン必須
 * - 退職者・無効ユーザーは Auth 側でログイン不可
 *
 * UI方針:
 * - 入力エラー時は email のみ再表示（password は保持しない）
 * - autocomplete を抑制（共用端末対策）
 */

$tpl = 'login';
?>
<div class="k-page login-page">
  <div class="row justify-content-center mt-5">
    <div class="col-md-5">

      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">ログイン</div>
            <div class="k-section-sub text-muted">ユーザーIDとパスワードを入力してください</div>
          </div>
        </div>

        <div class="k-card__body">

          <?php if (!empty($error)): ?>
            <!--
              認証失敗時の文言は固定。
              ・ユーザー不存在
              ・パスワード不一致
              ・退職者
              いずれも区別しない（情報漏えい防止）
            -->
            <div class="alert alert-danger">
              <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <form method="post" action="/login" novalidate autocomplete="off">
            <!--
              CSRFトークン
              ※ Auth::attempt 成功時に Csrf::rotate() を有効化する場合、
                 ログイン前に開いていた古いタブからのPOSTは失敗する点に注意
            -->
            <input
              type="hidden"
              name="_token"
              value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="mb-3">
              <label class="form-label">ユーザーID（メール）</label>
              <input
                type="email"
                name="email"
                class="form-control"
                value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
                autocomplete="username"
                inputmode="email"
              >
            </div>

            <div class="mb-3">
              <label class="form-label">パスワード</label>
              <input
                type="password"
                name="password"
                class="form-control"
                required
                autocomplete="current-password"
              >
            </div>

            <button type="submit" class="btn btn-primary w-100">
              ログイン
            </button>
          </form>

          <!-- 補足 -->
          <div class="small text-muted mt-3">
            ※ 退職済みのユーザーはログインできません。
          </div>

        </div>
      </div>

    </div>
  </div>
</div>
