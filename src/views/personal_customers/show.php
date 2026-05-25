<?php
/**
 * views/personal_customers/show.php
 * ============================================================
 * 役割:
 * - 個人顧客 詳細表示
 *
 * 方針（office_customers/show.php と同期）:
 * - 編集/削除ボタンは編集権限がある場合のみ表示
 * - 削除は論理削除（deleted_at）
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me = $me ?? Auth::user();

$canEdit = $me ? Policies::canEditPersonalCustomers($me) : false;

$pref = $cfg['prefectures'] ?? [];
$bg   = $cfg['options']['personal_customers']['backgrounds'] ?? [0 => '（未選択）', 1 => 'HP', 2 => 'チラシ', 3 => '営業'];
$lic  = $cfg['options']['personal_customers']['license_colors'] ?? [0 => '（未選択）', 1 => 'ブルー', 2 => 'ゴールド', 3 => 'グリーン'];

$id = (int)$item['id'];
$isDeleted = !empty($item['deleted_at']);

// 本人 都道府県
$prefText = '（未設定）';
$pc = (int)($item['pref_code'] ?? 0);
if ($pc && isset($pref[$pc])) $prefText = (string)$pref[$pc];

// 勤務先 都道府県
$officePrefText = '（未設定）';
$opc = (int)($item['office_pref_code'] ?? 0);
if ($opc && isset($pref[$opc])) $officePrefText = (string)$pref[$opc];

// 来社経緯 / 免許証色
$bgCode = (int)($item['background'] ?? 0);
$bgText = isset($bg[$bgCode]) ? (string)$bg[$bgCode] : '（未設定）';

$lc = (int)($item['license_color'] ?? 0);
$lcText = isset($lic[$lc]) ? (string)$lic[$lc] : '（未設定）';

// 誕生日
$birthdayText = '（未設定）';
$by = (int)($item['birthday_year'] ?? 0);
$bm = (int)($item['birthday_month'] ?? 0);
$bd = (int)($item['birthday_day'] ?? 0);
if ($by && $bm && $bd) $birthdayText = sprintf('%04d-%02d-%02d', $by, $bm, $bd);

$disp = function ($v, string $emptyLabel = '（未設定）') {
    $v = $v ?? null;
    if ($v === null) return '<span class="text-muted">' . htmlspecialchars($emptyLabel, ENT_QUOTES, 'UTF-8') . '</span>';
    $s = (string)$v;
    if (trim($s) === '') return '<span class="text-muted">' . htmlspecialchars($emptyLabel, ENT_QUOTES, 'UTF-8') . '</span>';
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

$families = [
  'first'  => 'ご家族情報1',
  'second' => 'ご家族情報2',
  'third'  => 'ご家族情報3',
  'fourth' => 'ご家族情報4',
  'fifth'  => 'ご家族情報5',
];
?>
<div class="k-page personal-customers-show">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">個人顧客詳細</h1>
      <div class="k-page__sub">個人顧客の詳細情報を表示します。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/personal_customers">一覧へ戻る</a>

      <?php if ($canEdit): ?>
        <a class="btn btn-outline-primary" href="/personal_customers/<?= $id ?>/edit">編集</a>

        <?php if (!$isDeleted): ?>
          <form method="post"
                action="/personal_customers/<?= $id ?>/delete"
                class="d-inline"
                onsubmit="return confirm('削除（論理削除）を実行します。よろしいですか？');">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-outline-danger">削除（論理削除）</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- 本人情報 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">本人情報</div>
        <div class="k-section-sub text-muted">基本情報 / 連絡先 / 住所</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>
            <tr>
              <th>ID</th>
              <td><?= $id ?></td>
              <th>氏名</th>
              <td><?= $disp($item['name'] ?? '') ?></td>
            </tr>
            <tr>
              <th>氏名フリガナ</th>
              <td><?= $disp($item['letter'] ?? '') ?></td>
              <th>電話番号1</th>
              <td><?= $disp($item['tel01'] ?? null) ?></td>
            </tr>
            <tr>
              <th>携帯</th>
              <td><?= $disp($item['mobile01'] ?? null) ?></td>
              <th>郵便番号</th>
              <td><?= $disp($item['zip'] ?? null) ?></td>
            </tr>
            <tr>
              <th>都道府県</th>
              <td><?= htmlspecialchars($prefText, ENT_QUOTES, 'UTF-8') ?></td>
              <th>住所1（市町村以下）</th>
              <td><?= $disp($item['addr01'] ?? null) ?></td>
            </tr>
            <tr>
              <th>住所2（地番以降）</th>
              <td colspan="3"><?= $disp($item['addr02'] ?? null) ?></td>
            </tr>
            <tr>
              <th>メールアドレス1</th>
              <td><?= $disp($item['mail01'] ?? null) ?></td>
              <th>メールアドレス2</th>
              <td><?= $disp($item['mail02'] ?? null) ?></td>
            </tr>
            <tr>
              <th>誕生日</th>
              <td><?= htmlspecialchars($birthdayText, ENT_QUOTES, 'UTF-8') ?></td>
              <th>免許証の色</th>
              <td><?= htmlspecialchars($lcText, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
              <th>緊急連絡先名</th>
              <td><?= $disp($item['emergency_contact'] ?? null) ?></td>
              <th>緊急連絡先の続柄</th>
              <td><?= $disp($item['emergency_relationship'] ?? null) ?></td>
            </tr>
            <tr>
              <th>緊急連絡先の電話番号</th>
              <td colspan="3"><?= $disp($item['emergency_tel'] ?? null) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- お勤め先情報 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">お勤め先情報</div>
        <div class="k-section-sub text-muted">勤務先 / 住所 / 電話</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>
            <tr>
              <th>会社名</th>
              <td><?= $disp($item['office'] ?? null) ?></td>
              <th>会社名フリガナ</th>
              <td><?= $disp($item['office_letter'] ?? null) ?></td>
            </tr>
            <tr>
              <th>勤務先 郵便番号</th>
              <td><?= $disp($item['office_zip'] ?? null) ?></td>
              <th>勤務先 都道府県</th>
              <td><?= htmlspecialchars($officePrefText, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
              <th>勤務先 住所1</th>
              <td><?= $disp($item['office_addr01'] ?? null) ?></td>
              <th>勤務先 住所2</th>
              <td><?= $disp($item['office_addr02'] ?? null) ?></td>
            </tr>
            <tr>
              <th>勤務先 電話番号1</th>
              <td><?= $disp($item['office_tel01'] ?? null) ?></td>
              <th>勤務先 電話番号2</th>
              <td><?= $disp($item['office_tel02'] ?? null) ?></td>
            </tr>
            <tr>
              <th>勤続年数</th>
              <td colspan="3"><?= $disp($item['years_of_service'] ?? null) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ご来社経緯 / 備考 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">ご来社経緯 / 備考</div>
        <div class="k-section-sub text-muted">来社経緯 / 紹介者 / その他 / 備考</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>
            <tr>
              <th>ご来社経緯</th>
              <td><?= htmlspecialchars($bgText, ENT_QUOTES, 'UTF-8') ?></td>
              <th>ご紹介者</th>
              <td><?= $disp($item['introducer'] ?? null) ?></td>
            </tr>
            <tr>
              <th>その他</th>
              <td><?= $disp($item['others'] ?? null) ?></td>
              <th>備考</th>
              <td><pre class="mb-0"><?= htmlspecialchars((string)($item['remarks'] ?? ''), ENT_QUOTES, 'UTF-8') ?></pre></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ご家族情報（1〜5） -->
  <?php foreach ($families as $pfx => $label): ?>
    <?php
      $rel = $item[$pfx.'_relationship'] ?? null;
      $nm  = $item[$pfx.'_name'] ?? null;
      $kn  = $item[$pfx.'_letter'] ?? null;

      $tel1 = $item[$pfx.'_tel01'] ?? null;
      $tel2 = $item[$pfx.'_tel02'] ?? null;

      $zip  = $item[$pfx.'_zip'] ?? null;
      $pcf  = (int)($item[$pfx.'_pref_code'] ?? 0);
      $pfText = '（未設定）';
      if ($pcf && isset($pref[$pcf])) $pfText = (string)$pref[$pcf];

      $a1 = $item[$pfx.'_addr01'] ?? null;
      $a2 = $item[$pfx.'_addr02'] ?? null;

      $m1 = $item[$pfx.'_mail01'] ?? null;
      $m2 = $item[$pfx.'_mail02'] ?? null;

      $rm = $item[$pfx.'_remarks'] ?? null;
    ?>
    <div class="k-card mb-3">
      <div class="k-card__header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="k-section-title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="k-section-sub text-muted">家族情報</div>
        </div>
      </div>
      <div class="k-card__body">
        <div class="k-show-table-wrap">
          <table class="k-show-table">
            <tbody>
              <tr>
                <th>続柄</th>
                <td><?= $disp($rel) ?></td>
                <th>氏名</th>
                <td><?= $disp($nm) ?></td>
              </tr>
              <tr>
                <th>氏名フリガナ</th>
                <td><?= $disp($kn) ?></td>
                <th>電話番号1</th>
                <td><?= $disp($tel1) ?></td>
              </tr>
              <tr>
                <th>電話番号2</th>
                <td><?= $disp($tel2) ?></td>
                <th>郵便番号</th>
                <td><?= $disp($zip) ?></td>
              </tr>
              <tr>
                <th>都道府県</th>
                <td><?= htmlspecialchars($pfText, ENT_QUOTES, 'UTF-8') ?></td>
                <th>住所1</th>
                <td><?= $disp($a1) ?></td>
              </tr>
              <tr>
                <th>住所2</th>
                <td><?= $disp($a2) ?></td>
                <th>メールアドレス1</th>
                <td><?= $disp($m1) ?></td>
              </tr>
              <tr>
                <th>メールアドレス2</th>
                <td><?= $disp($m2) ?></td>
                <th>備考</th>
                <td><pre class="mb-0"><?= htmlspecialchars((string)($rm ?? ''), ENT_QUOTES, 'UTF-8') ?></pre></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- 削除日時 -->
  <div class="k-card">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">削除情報</div>
        <div class="k-section-sub text-muted">削除日時</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>
            <tr>
              <th>削除日時</th>
              <td colspan="3">
                <?= htmlspecialchars((string)($item['deleted_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                <?php if ($isDeleted): ?>
                  <span class="badge text-bg-secondary ms-2">削除済み</span>
                <?php endif; ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
