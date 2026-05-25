<?php
/**
 * views/users/show.php
 * ============================================================
 * 役割:
 * - ユーザー詳細表示
 *
 * 改修ポイント:
 * - $tpl の上書きをしない（Response::view から渡る前提）
 * - 編集/退職処理ボタンは編集権限がある場合のみ表示
 * - 削除は論理削除（退職日セット）方針なので文言を統一
 * - 退職済みの場合は退職処理ボタンを出さない
 * - fieldVisible による項目表示制御に対応
 * - ★入れ子form禁止：退職処理は button formaction で分岐
 */

// 辞書（roles 等）
$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');

// me は controller/layout から渡る想定。無い場合も壊れないように拾う
$me = $me ?? Auth::user();

$canEdit = $me ? Policies::canEditUsers($me) : false;

// fieldVisible（将来の項目制御に備える）
$visible = function (string $field) use ($me) {
    if (!$me) return true;
    return Policies::fieldVisible($me, 'users', $field);
};

$id = (int)$item['id'];
$isResigned = !empty($item['resigned_on']);

$email = $item['email'] ?? null;
$emailHtml = $email
    ? htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8')
    : '<span class="text-muted">（未設定）</span>';

$roleText = (string)($cfg['roles'][(int)($item['role_code'] ?? 0)] ?? '-');

$joinedOn = (string)($item['joined_on'] ?? '');
$resignedOn = (string)($item['resigned_on'] ?? '');
$notes = (string)($item['notes'] ?? '');
?>
<div class="k-page users-show">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">ユーザー詳細</h1>
      <div class="k-page__sub">ユーザー情報の詳細を表示します。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/users">一覧へ戻る</a>

      <?php if ($canEdit): ?>
        <a class="btn btn-outline-primary" href="/users/<?= $id ?>/edit">編集</a>

        <?php if (!$isResigned): ?>
          <!-- ★入れ子form禁止：formaction で退職へ分岐 -->
          <form method="post" action="/users/<?= $id ?>" class="d-inline">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <button
              type="submit"
              class="btn btn-outline-danger"
              formmethod="post"
              formaction="/users/<?= $id ?>/delete"
              onclick="return confirm('退職処理（論理削除）を実行します。よろしいですか？');"
            >退職処理</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="k-card">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">基本情報</div>
        <div class="k-section-sub text-muted">ID / 社員コード / 権限 / 在籍情報</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>

            <tr>
              <th>ID</th>
              <td><?= $id ?></td>
              <th>社員コード</th>
              <td><?= htmlspecialchars((string)($item['employee_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>権限</th>
              <td><?= htmlspecialchars($roleText, ENT_QUOTES, 'UTF-8') ?></td>
              <th>氏名</th>
              <td><?= htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>フリガナ</th>
              <td><?= htmlspecialchars((string)($item['name_kana'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <th>ユーザーID（メール）</th>
              <td><?= $emailHtml ?></td>
            </tr>

            <?php if ($visible('contract_input_permission')): ?>
              <tr>
                <th>契約入力権限</th>
                <td><?= ((int)($item['contract_input_permission'] ?? 0) ? 'あり' : 'なし') ?></td>
                <th>未契約入力権限</th>
                <td>
                  <?php if ($visible('uncontract_input_permission')): ?>
                    <?= ((int)($item['uncontract_input_permission'] ?? 0) ? 'あり' : 'なし') ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php elseif ($visible('uncontract_input_permission')): ?>
              <tr>
                <th>未契約入力権限</th>
                <td><?= ((int)($item['uncontract_input_permission'] ?? 0) ? 'あり' : 'なし') ?></td>
                <th>契約入力権限</th>
                <td><span class="text-muted">—</span></td>
              </tr>
            <?php endif; ?>

            <tr>
              <th>入社日</th>
              <td><?= htmlspecialchars($joinedOn, ENT_QUOTES, 'UTF-8') ?></td>
              <th>退職日</th>
              <td>
                <?= htmlspecialchars($resignedOn, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($isResigned): ?>
                  <span class="badge text-bg-secondary ms-2">退職済み</span>
                <?php endif; ?>
              </td>
            </tr>

            <tr>
              <th>備考</th>
              <td colspan="3">
                <pre class="mb-0"><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') ?></pre>
              </td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
