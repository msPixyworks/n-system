<?php
/**
 * views/office_customers/show.php
 * ============================================================
 * 役割:
 * - 法人顧客 詳細表示
 *
 * 方針（users/show.php と同期）:
 * - 編集/削除ボタンは編集権限がある場合のみ表示
 * - 削除は論理削除（deleted_at）
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me = $me ?? Auth::user();

$canEdit = $me ? Policies::canEditOfficeCustomers($me) : false;

$pref = $cfg['prefectures'] ?? [];
$bg = $cfg['office_customer_backgrounds'] ?? [1 => 'HP', 2 => 'チラシ', 3 => '営業'];

$id = (int)$item['id'];
$isDeleted = !empty($item['deleted_at']);

$prefText = '-';
$pc = (int)($item['pref_code'] ?? 0);
if ($pc && isset($pref[$pc])) $prefText = (string)$pref[$pc];

$pref2Text = '（未設定）';
$pc2 = (int)($item['pref02_code'] ?? 0);
if ($pc2 && isset($pref[$pc2])) $pref2Text = (string)$pref[$pc2];

$bgCode = (int)($item['background'] ?? 0);
$bgText = ($bgCode && isset($bg[$bgCode])) ? (string)$bg[$bgCode] : '-';

$zip02 = $item['zip02'] ?? null;
$addr02 = $item['addr02'] ?? null;
$m1 = $item['mail01'] ?? null;
$m2 = $item['mail02'] ?? null;

$deletedAt = (string)($item['deleted_at'] ?? '');
?>
<div class="k-page office-customers-show">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">法人顧客詳細</h1>
      <div class="k-page__sub">法人顧客の詳細情報を表示します。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/office_customers">一覧へ戻る</a>

      <?php if ($canEdit): ?>
        <a class="btn btn-outline-primary" href="/office_customers/<?= $id ?>/edit">編集</a>

        <?php if (!$isDeleted): ?>
          <form method="post"
                action="/office_customers/<?= $id ?>/delete"
                class="d-inline"
                onsubmit="return confirm('削除（論理削除）を実行します。よろしいですか？');">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-outline-danger">削除（論理削除）</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="k-card">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">基本情報</div>
        <div class="k-section-sub text-muted">会社情報 / 連絡先 / 住所 / 来社経緯</div>
      </div>
    </div>

    <div class="k-card__body">
      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>

            <tr>
              <th>ID</th>
              <td><?= (int)$id ?></td>
              <th>会社名</th>
              <td><?= htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>会社名フリガナ</th>
              <td><?= htmlspecialchars((string)($item['company_name_phonetic'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <th>代表者</th>
              <td><?= htmlspecialchars((string)($item['representative'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>代表者フリガナ</th>
              <td><?= htmlspecialchars((string)($item['representative_letter'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <th>ご担当者</th>
              <td><?= htmlspecialchars((string)($item['manager'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>ご担当者フリガナ</th>
              <td><?= htmlspecialchars((string)($item['manager_letter'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <th>ご担当者部署</th>
              <td><?= htmlspecialchars((string)($item['department_in_charge'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>ご担当者役職</th>
              <td><?= htmlspecialchars((string)($item['person_in_charge'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <th>ドライバー様</th>
              <td><?= htmlspecialchars((string)($item['driver'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>ドライバー様フリガナ</th>
              <td><?= htmlspecialchars((string)($item['driver_letter'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <th>TEL</th>
              <td><?= htmlspecialchars((string)($item['tel'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>FAX</th>
              <td><?= htmlspecialchars((string)($item['fax'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <th>郵便番号</th>
              <td><?= htmlspecialchars((string)($item['zip'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>都道府県</th>
              <td><?= htmlspecialchars($prefText, ENT_QUOTES, 'UTF-8') ?></td>
              <th>住所（市町村以下）</th>
              <td><?= htmlspecialchars((string)($item['addr01'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>支店等 郵便番号</th>
              <td>
                <?= $zip02 ? htmlspecialchars((string)$zip02, ENT_QUOTES, 'UTF-8') : '<span class="text-muted">（未設定）</span>' ?>
              </td>
              <th>支店等 都道府県</th>
              <td><?= htmlspecialchars($pref2Text, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>支店等 住所</th>
              <td colspan="3">
                <?= $addr02 ? htmlspecialchars((string)$addr02, ENT_QUOTES, 'UTF-8') : '<span class="text-muted">（未設定）</span>' ?>
              </td>
            </tr>

            <tr>
              <th>メールアドレス1</th>
              <td>
                <?= $m1 ? htmlspecialchars((string)$m1, ENT_QUOTES, 'UTF-8') : '<span class="text-muted">（未設定）</span>' ?>
              </td>
              <th>メールアドレス2</th>
              <td>
                <?= $m2 ? htmlspecialchars((string)$m2, ENT_QUOTES, 'UTF-8') : '<span class="text-muted">（未設定）</span>' ?>
              </td>
            </tr>

            <tr>
              <th>ご利用目的</th>
              <td colspan="3">
                <pre class="mb-0"><?= htmlspecialchars((string)($item['purpose'] ?? ''), ENT_QUOTES, 'UTF-8') ?></pre>
              </td>
            </tr>

            <tr>
              <th>来社経緯</th>
              <td><?= htmlspecialchars($bgText, ENT_QUOTES, 'UTF-8') ?></td>
              <th>ご紹介者</th>
              <td><?= htmlspecialchars((string)($item['introducer'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>その他</th>
              <td><?= htmlspecialchars((string)($item['others'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <th>削除日時</th>
              <td>
                <?= htmlspecialchars($deletedAt, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($isDeleted): ?>
                  <span class="badge text-bg-secondary ms-2">削除済み</span>
                <?php endif; ?>
              </td>
            </tr>

            <tr>
              <th>備考</th>
              <td colspan="3">
                <pre class="mb-0"><?= htmlspecialchars((string)($item['remarks'] ?? ''), ENT_QUOTES, 'UTF-8') ?></pre>
              </td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
