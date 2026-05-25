<?php
/**
 * views/personal_customers/form.php
 * ============================================================
 * 役割:
 * - 個人顧客 登録/編集フォーム（共通）
 *
 * 方針（office_customers/form.php と同期）:
 * - cfg/errors/old/item/me を受ける
 * - Old -> Item -> Default で値を出す
 * - CSRF token
 * - 削除は入れ子form禁止（button formaction で分岐）
 *
 * UI（今回）:
 * - セクションごとにカード（タイル）分割（1段に1個＝フル幅で縦積み）
 * - 入力項目は原則「1行2項目まで」（col-12 col-md-6）
 *
 * JS:
 * - /public/js/personalCustomersForm.js（zipcloud/電話/カナ/メール/誕生日day絞り）
 */

$cfg  = $cfg  ?? (require __DIR__ . '/../../app/config.php');

$e    = (isset($errors) && is_array($errors)) ? $errors : [];
$old  = (isset($old)    && is_array($old))    ? $old    : [];
$item = (isset($item)   && is_array($item))   ? $item   : null;

$me = $me ?? Auth::user();

$val = function (string $k, $default = '') use ($old, $item) {
    if (is_array($old)  && array_key_exists($k, $old))  return $old[$k];
    if (is_array($item) && array_key_exists($k, $item)) return $item[$k];
    return $default;
};

$err = function (string $k) use ($e) {
    if (!empty($e[$k])) {
        echo '<div class="form-error">' .
            htmlspecialchars(implode(' / ', (array)$e[$k]), ENT_QUOTES, 'UTF-8') .
            '</div>';
    }
};

$isEdit = (bool)$item;
$titleText = $isEdit ? '個人顧客編集' : '個人顧客登録';

$actionUrl = $isEdit
    ? '/personal_customers/' . (int)$item['id']
    : '/personal_customers';

$canEdit = $me ? Policies::canEditPersonalCustomers($me) : false;

$pref = $cfg['prefectures'] ?? [];
$bg   = $cfg['options']['personal_customers']['backgrounds'] ?? [0 => '（未選択）', 1 => 'HP', 2 => 'チラシ', 3 => '営業'];
$lic  = $cfg['options']['personal_customers']['license_colors'] ?? [0 => '（未選択）', 1 => 'ブルー', 2 => 'ゴールド', 3 => 'グリーン'];

$deleted = $isEdit ? !empty($item['deleted_at']) : false;
$id = $isEdit ? (int)$item['id'] : 0;

// 誕生日 年リスト（安全側：1900〜今年+1）
$nowY = (int)date('Y');
$yearMin = 1900;
$yearMax = $nowY + 1;

// ご家族情報（1〜5）
$families = [
  'first'  => 'ご家族情報1',
  'second' => 'ご家族情報2',
  'third'  => 'ご家族情報3',
  'fourth' => 'ご家族情報4',
  'fifth'  => 'ご家族情報5',
];
?>
<div class="k-page personal-customers-form">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title"><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="k-page__sub">個人顧客の登録／編集を行います。</div>
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

      <!-- 1. 本人情報 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">本人情報</div>
            <div class="k-section-sub text-muted">氏名／連絡先／住所／誕生日など</div>
          </div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-12 col-md-6">
              <label class="form-label">氏名 <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control"
                     value="<?= htmlspecialchars((string)$val('name'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('name'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">氏名フリガナ <span class="text-danger">*</span></label>
              <input type="text" name="letter" class="form-control"
                     value="<?= htmlspecialchars((string)$val('letter'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('letter'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">電話番号1</label>
              <input type="text" name="tel01" class="form-control"
                     value="<?= htmlspecialchars((string)$val('tel01'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('tel01'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">携帯</label>
              <input type="text" name="mobile01" class="form-control"
                     value="<?= htmlspecialchars((string)$val('mobile01'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('mobile01'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">郵便番号</label>
              <input type="text" name="zip" class="form-control"
                     value="<?= htmlspecialchars((string)$val('zip'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('zip'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">都道府県</label>
              <select name="pref_code" class="form-select">
                <option value="">選択してください</option>
                <?php foreach ($pref as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('pref_code') === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php $err('pref_code'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">住所1（市町村以下）</label>
              <input type="text" name="addr01" class="form-control"
                     value="<?= htmlspecialchars((string)$val('addr01'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('addr01'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">住所2（地番以降）</label>
              <input type="text" name="addr02" class="form-control"
                     value="<?= htmlspecialchars((string)$val('addr02'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('addr02'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">メールアドレス1</label>
              <input type="text" name="mail01" class="form-control"
                     value="<?= htmlspecialchars((string)$val('mail01'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('mail01'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">メールアドレス2</label>
              <input type="text" name="mail02" class="form-control"
                     value="<?= htmlspecialchars((string)$val('mail02'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('mail02'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">誕生日（年）</label>
              <select name="birthday_year" class="form-select js-bday-year">
                <option value="0">（未選択）</option>
                <?php for ($y = $yearMax; $y >= $yearMin; $y--): ?>
                  <option value="<?= (int)$y ?>" <?= ((int)$val('birthday_year') === (int)$y) ? 'selected' : '' ?>>
                    <?= (int)$y ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="col-6 col-md-3">
              <label class="form-label">誕生日（月）</label>
              <select name="birthday_month" class="form-select js-bday-month">
                <option value="0">（未選択）</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                  <option value="<?= (int)$m ?>" <?= ((int)$val('birthday_month') === (int)$m) ? 'selected' : '' ?>>
                    <?= (int)$m ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="col-6 col-md-3">
              <label class="form-label">誕生日（日）</label>
              <select name="birthday_day" class="form-select js-bday-day">
                <option value="0">（未選択）</option>
                <?php for ($d = 1; $d <= 31; $d++): ?>
                  <option value="<?= (int)$d ?>" <?= ((int)$val('birthday_day') === (int)$d) ? 'selected' : '' ?>>
                    <?= (int)$d ?>
                  </option>
                <?php endfor; ?>
              </select>
              <?php $err('birthday'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">免許証の色</label>
              <select name="license_color" class="form-select">
                <?php foreach ($lic as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('license_color') === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php $err('license_color'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">緊急連絡先名</label>
              <input type="text" name="emergency_contact" class="form-control"
                     value="<?= htmlspecialchars((string)$val('emergency_contact'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('emergency_contact'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">緊急連絡先の続柄</label>
              <input type="text" name="emergency_relationship" class="form-control"
                     value="<?= htmlspecialchars((string)$val('emergency_relationship'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('emergency_relationship'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">緊急連絡先の電話番号</label>
              <input type="text" name="emergency_tel" class="form-control"
                     value="<?= htmlspecialchars((string)$val('emergency_tel'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('emergency_tel'); ?>
            </div>

          </div>
        </div>
      </div>

      <!-- 2. お勤め先情報 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">お勤め先情報</div>
            <div class="k-section-sub text-muted">勤務先／住所／電話</div>
          </div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-12 col-md-6">
              <label class="form-label">会社名</label>
              <input type="text" name="office" class="form-control"
                     value="<?= htmlspecialchars((string)$val('office'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('office'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">会社名フリガナ</label>
              <input type="text" name="office_letter" class="form-control"
                     value="<?= htmlspecialchars((string)$val('office_letter'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('office_letter'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">勤務先 郵便番号</label>
              <input type="text" name="office_zip" class="form-control"
                     value="<?= htmlspecialchars((string)$val('office_zip'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('office_zip'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">勤務先 都道府県</label>
              <select name="office_pref_code" class="form-select">
                <option value="">選択してください</option>
                <?php foreach ($pref as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('office_pref_code') === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php $err('office_pref_code'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">勤続年数（年）</label>
              <input type="text" name="years_of_service" class="form-control"
                     value="<?= htmlspecialchars((string)$val('years_of_service'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('years_of_service'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">勤務先 住所1（市町村以下）</label>
              <input type="text" name="office_addr01" class="form-control"
                     value="<?= htmlspecialchars((string)$val('office_addr01'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('office_addr01'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">勤務先 住所2（地番以降）</label>
              <input type="text" name="office_addr02" class="form-control"
                     value="<?= htmlspecialchars((string)$val('office_addr02'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('office_addr02'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">勤務先 電話番号1</label>
              <input type="text" name="office_tel01" class="form-control"
                     value="<?= htmlspecialchars((string)$val('office_tel01'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('office_tel01'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">勤務先 電話番号2</label>
              <input type="text" name="office_tel02" class="form-control"
                     value="<?= htmlspecialchars((string)$val('office_tel02'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('office_tel02'); ?>
            </div>

          </div>
        </div>
      </div>

      <!-- 3. ご来社経緯 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">ご来社経緯</div>
            <div class="k-section-sub text-muted">来社経緯／紹介者／その他</div>
          </div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-12 col-md-6">
              <label class="form-label">来社経緯</label>
              <select name="background" class="form-select">
                <?php foreach ($bg as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('background') === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php $err('background'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">ご紹介者</label>
              <input type="text" name="introducer" class="form-control"
                     value="<?= htmlspecialchars((string)$val('introducer'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('introducer'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">その他</label>
              <input type="text" name="others" class="form-control"
                     value="<?= htmlspecialchars((string)$val('others'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('others'); ?>
            </div>

          </div>
        </div>
      </div>

      <!-- 4. 備考 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">備考</div>
            <div class="k-section-sub text-muted">メモ</div>
          </div>
        </div>
        <div class="k-card__body">
          <label class="form-label">備考</label>
          <textarea name="remarks" class="form-control" rows="4"><?= htmlspecialchars((string)$val('remarks'), ENT_QUOTES, 'UTF-8') ?></textarea>
          <?php $err('remarks'); ?>
        </div>
      </div>

      <!-- 5. ご家族情報（1〜5） -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">ご家族情報（1〜5）</div>
            <div class="k-section-sub text-muted">必要な分だけ入力</div>
          </div>
        </div>
        <div class="k-card__body">

          <div class="d-flex flex-column gap-3">
            <?php foreach ($families as $pfx => $label): ?>
              <div class="k-card">
                <div class="k-card__header">
                  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="k-section-title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="k-section-sub text-muted">続柄／氏名／連絡先／住所／メール</div>
                  </div>
                </div>
                <div class="k-card__body">
                  <div class="row g-3">

                    <div class="col-12 col-md-6">
                      <label class="form-label">続柄</label>
                      <input type="text" name="<?= $pfx ?>_relationship" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_relationship'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_relationship'); ?>
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">氏名</label>
                      <input type="text" name="<?= $pfx ?>_name" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_name'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_name'); ?>
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">氏名フリガナ</label>
                      <input type="text" name="<?= $pfx ?>_letter" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_letter'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_letter'); ?>
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">電話番号1</label>
                      <input type="text" name="<?= $pfx ?>_tel01" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_tel01'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_tel01'); ?>
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">電話番号2</label>
                      <input type="text" name="<?= $pfx ?>_tel02" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_tel02'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_tel02'); ?>
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">郵便番号</label>
                      <input type="text" name="<?= $pfx ?>_zip" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_zip'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_zip'); ?>
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">都道府県</label>
                      <select name="<?= $pfx ?>_pref_code" class="form-select">
                        <option value="">選択してください</option>
                        <?php foreach ($pref as $k => $v): ?>
                          <option value="<?= (int)$k ?>" <?= ((int)$val($pfx.'_pref_code') === (int)$k) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <?php $err($pfx.'_pref_code'); ?>
                    </div>

                    <div class="col-12">
                      <label class="form-label">住所1（市町村以下）</label>
                      <input type="text" name="<?= $pfx ?>_addr01" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_addr01'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_addr01'); ?>
                    </div>

                    <div class="col-12">
                      <label class="form-label">住所2（地番以降）</label>
                      <input type="text" name="<?= $pfx ?>_addr02" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_addr02'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_addr02'); ?>
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">メールアドレス1</label>
                      <input type="text" name="<?= $pfx ?>_mail01" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_mail01'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_mail01'); ?>
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">メールアドレス2</label>
                      <input type="text" name="<?= $pfx ?>_mail02" class="form-control"
                             value="<?= htmlspecialchars((string)$val($pfx.'_mail02'), ENT_QUOTES, 'UTF-8') ?>">
                      <?php $err($pfx.'_mail02'); ?>
                    </div>

                    <div class="col-12">
                      <label class="form-label">備考</label>
                      <textarea name="<?= $pfx ?>_remarks" class="form-control" rows="4"><?= htmlspecialchars((string)$val($pfx.'_remarks'), ENT_QUOTES, 'UTF-8') ?></textarea>
                      <?php $err($pfx.'_remarks'); ?>
                    </div>

                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

        </div>
      </div>

      <!-- 操作 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">操作</div>
            <div class="k-section-sub text-muted"><?= $isEdit ? '更新 / 戻る / 削除' : '登録 / 戻る' ?></div>
          </div>
        </div>
        <div class="k-card__body">
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? '更新する' : '登録する' ?></button>
            <a class="btn btn-outline-secondary" href="/personal_customers">戻る</a>

            <?php if ($isEdit && $canEdit && !$deleted): ?>
              <!-- 入れ子禁止：button formaction で削除へ分岐 -->
              <button
                type="submit"
                class="btn btn-outline-danger"
                formmethod="post"
                formaction="/personal_customers/<?= $id ?>/delete"
                onclick="return confirm('削除（論理削除）します。よろしいですか？');"
              >削除（論理削除）</button>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div><!-- /tiles stack -->

  </form>

</div>

<script src="/js/personalCustomersForm.js" defer></script>
