<?php
/**
 * views/users/form.php
 * ============================================================
 * 役割:
 * - users の 登録/編集フォーム（共通）
 *
 * 改修ポイント:
 * - config.php の読み込みパスを他viewと統一
 * - Policies::fieldVisible() による項目表示制御に対応
 * - 論理削除（退職処理）ボタンを追加（編集時のみ / 編集権限がある場合のみ）
 * - 出力のエスケープを徹底（テンプレとしての安全性）
 * - ★入れ子form禁止：退職処理は button formaction で分岐
 */

// 共通設定（辞書）
$cfg  = $cfg  ?? (require __DIR__ . '/../../app/config.php');

// 正規化
$e    = (isset($errors) && is_array($errors)) ? $errors : [];
$old  = (isset($old)    && is_array($old))    ? $old    : [];
$item = (isset($item)   && is_array($item))   ? $item   : null;

// me は layout / controller から渡る想定（無い場合はAuthから拾う）
$me = $me ?? Auth::user();

// 値取得：優先順 Old -> Item -> Default
$val = function (string $k, $default = '') use ($old, $item) {
    if (is_array($old)  && array_key_exists($k, $old))  return $old[$k];
    if (is_array($item) && array_key_exists($k, $item)) return $item[$k];
    return $default;
};

// エラー表示
$err = function (string $k) use ($e) {
    if (!empty($e[$k])) {
        echo '<div class="form-error">' .
            htmlspecialchars(implode(' / ', (array)$e[$k]), ENT_QUOTES, 'UTF-8') .
            '</div>';
    }
};

// 表示制御（fieldVisible）
$visible = function (string $field) use ($me) {
    if (!$me) return true;
    return Policies::fieldVisible($me, 'users', $field);
};

$isEdit = (bool)$item;
$titleText = $isEdit ? 'ユーザー編集' : 'ユーザー登録';

$actionUrl = $isEdit
    ? '/users/' . (int)$item['id']
    : '/users';

$canEdit = $me ? Policies::canEditUsers($me) : false;

// 退職済み判定（退職済みなら退職処理ボタンは出さない）
$alreadyResigned = ($isEdit && !empty($item['resigned_on']));
$userId = $isEdit ? (int)$item['id'] : 0;
?>
<div class="k-page users-form">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title"><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="k-page__sub">ユーザーの登録／編集を行います。</div>
    </div>
  </div>

  <?php if (!empty($e['__global'])): ?>
    <div class="alert alert-danger mb-3">
      <?= htmlspecialchars(implode(' / ', (array)$e['__global']), ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="d-flex flex-column gap-3">

      <!-- 基本情報 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">基本情報</div>
            <div class="k-section-sub text-muted">社員コード／権限／氏名／ログイン</div>
          </div>
        </div>

        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-12 col-md-6">
              <label class="form-label">社員コード <span class="text-danger">*</span></label>
              <input type="text" name="employee_code" class="form-control"
                     value="<?= htmlspecialchars((string)$val('employee_code'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('employee_code'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">権限 <span class="text-danger">*</span></label>
              <select name="role_code" class="form-select">
                <option value="">選択してください</option>
                <?php foreach (($cfg['roles'] ?? []) as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('role_code') === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php $err('role_code'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">社員名 <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control"
                     value="<?= htmlspecialchars((string)$val('name'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('name'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">社員名フリガナ <span class="text-danger">*</span></label>
              <input type="text" name="name_kana" class="form-control"
                     value="<?= htmlspecialchars((string)$val('name_kana'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('name_kana'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">ユーザーID（メール）</label>
              <input type="email" name="email" class="form-control"
                     value="<?= htmlspecialchars((string)$val('email'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('email'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">パスワード<?= $isEdit ? '（変更時のみ）' : '' ?></label>
              <input type="password" name="password" class="form-control" placeholder="8〜16文字、半角英数記号">
              <?php $err('password'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">パスワード（確認用）</label>
              <input type="password" name="password_confirm" class="form-control">
              <?php $err('password_confirm'); ?>
            </div>

          </div>
        </div>
      </div>

      <!-- 権限・在籍情報 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">権限・在籍情報</div>
            <div class="k-section-sub text-muted">入力権限／入社日／退職日</div>
          </div>
        </div>

        <div class="k-card__body">
          <div class="row g-3">

            <?php if ($visible('contract_input_permission')): ?>
              <div class="col-12 col-md-6">
                <label class="form-label">契約入力権限</label>
                <?php $cip = (int)$val('contract_input_permission', 0); ?>
                <select name="contract_input_permission" class="form-select">
                  <option value="0" <?= $cip === 0 ? 'selected' : '' ?>>なし</option>
                  <option value="1" <?= $cip === 1 ? 'selected' : '' ?>>あり</option>
                </select>
              </div>
            <?php endif; ?>

            <?php if ($visible('uncontract_input_permission')): ?>
              <div class="col-12 col-md-6">
                <label class="form-label">未契約入力権限</label>
                <?php $uip = (int)$val('uncontract_input_permission', 0); ?>
                <select name="uncontract_input_permission" class="form-select">
                  <option value="0" <?= $uip === 0 ? 'selected' : '' ?>>なし</option>
                  <option value="1" <?= $uip === 1 ? 'selected' : '' ?>>あり</option>
                </select>
              </div>
            <?php endif; ?>

            <div class="col-12 col-md-6">
              <label class="form-label">入社日</label>
              <input type="date" name="joined_on" class="form-control"
                     value="<?= htmlspecialchars((string)$val('joined_on'), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">退職日</label>
              <input type="date" name="resigned_on" class="form-control"
                     value="<?= htmlspecialchars((string)$val('resigned_on'), ENT_QUOTES, 'UTF-8') ?>">
            </div>

          </div>
        </div>
      </div>

      <!-- 備考 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">備考</div>
            <div class="k-section-sub text-muted">メモ</div>
          </div>
        </div>

        <div class="k-card__body">
          <label class="form-label">備考</label>
          <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars((string)$val('notes'), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
      </div>

      <!-- 操作 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">操作</div>
            <div class="k-section-sub text-muted"><?= $isEdit ? '更新 / 戻る / 退職処理' : '登録 / 戻る' ?></div>
          </div>
        </div>

        <div class="k-card__body">
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? '更新する' : '登録する' ?></button>
            <a class="btn btn-outline-secondary" href="/users">戻る</a>

            <?php if ($isEdit && $canEdit && !$alreadyResigned): ?>
              <!-- 入れ子禁止：button formaction で退職へ分岐 -->
              <button
                type="submit"
                class="btn btn-outline-danger"
                formmethod="post"
                formaction="/users/<?= $userId ?>/delete"
                onclick="return confirm('退職（論理削除）します。よろしいですか？');"
              >退職処理</button>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div><!-- /tiles stack -->

  </form>

</div>

<script src="/js/usersForm.js" defer></script>
