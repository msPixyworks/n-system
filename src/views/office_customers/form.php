<?php
/**
 * views/office_customers/form.php
 * ============================================================
 * 役割:
 * - 法人顧客 登録/編集フォーム（共通）
 *
 * 方針（users/form.php と同期）:
 * - cfg/errors/old/item/me を受ける
 * - Old -> Item -> Default で値を出す
 * - CSRF token
 * - 削除は入れ子form禁止（button formaction で分岐）
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
$titleText = $isEdit ? '法人顧客編集' : '法人顧客登録';

$actionUrl = $isEdit
    ? '/office_customers/' . (int)$item['id']
    : '/office_customers';

$canEdit = $me ? Policies::canEditOfficeCustomers($me) : false;

$pref = $cfg['prefectures'] ?? [];
$bg = $cfg['office_customer_backgrounds'] ?? [1 => 'HP', 2 => 'チラシ', 3 => '営業'];

$deleted = $isEdit ? !empty($item['deleted_at']) : false;
$id = $isEdit ? (int)$item['id'] : 0;
?>
<div class="k-page office-customers-form">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title"><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="k-page__sub">法人顧客の登録／編集を行います。</div>
    </div>
  </div>

  <?php if (!empty($e['__global'])): ?>
    <div class="alert alert-danger mb-3">
      <?= htmlspecialchars(implode(' / ', (array)$e['__global']), ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <!-- ===================================================== -->
    <!-- タイル（カード）縦積み：1段に1個（フル幅） -->
    <!-- ===================================================== -->
    <div class="d-flex flex-column gap-3">

      <!-- 法人のお客様 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">法人のお客様</div>
            <div class="k-section-sub text-muted">基本情報／連絡先／住所</div>
          </div>
        </div>

        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-12 col-md-6">
              <label class="form-label">会社名 <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control"
                     value="<?= htmlspecialchars((string)$val('name'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('name'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">会社名フリガナ</label>
              <input type="text" name="company_name_phonetic" class="form-control"
                     value="<?= htmlspecialchars((string)$val('company_name_phonetic'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('company_name_phonetic'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">代表者</label>
              <input type="text" name="representative" class="form-control"
                     value="<?= htmlspecialchars((string)$val('representative'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('representative'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">代表者フリガナ</label>
              <input type="text" name="representative_letter" class="form-control"
                     value="<?= htmlspecialchars((string)$val('representative_letter'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('representative_letter'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">ご担当者</label>
              <input type="text" name="manager" class="form-control"
                     value="<?= htmlspecialchars((string)$val('manager'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('manager'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">ご担当者フリガナ</label>
              <input type="text" name="manager_letter" class="form-control"
                     value="<?= htmlspecialchars((string)$val('manager_letter'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('manager_letter'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">ご担当者部署</label>
              <input type="text" name="department_in_charge" class="form-control"
                     value="<?= htmlspecialchars((string)$val('department_in_charge'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('department_in_charge'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">ご担当者役職</label>
              <input type="text" name="person_in_charge" class="form-control"
                     value="<?= htmlspecialchars((string)$val('person_in_charge'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('person_in_charge'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">ドライバー様</label>
              <input type="text" name="driver" class="form-control"
                     value="<?= htmlspecialchars((string)$val('driver'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('driver'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">ドライバー様フリガナ</label>
              <input type="text" name="driver_letter" class="form-control"
                     value="<?= htmlspecialchars((string)$val('driver_letter'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('driver_letter'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">本社電話番号</label>
              <input type="text" name="tel" class="form-control"
                     value="<?= htmlspecialchars((string)$val('tel'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('tel'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">FAX番号</label>
              <input type="text" name="fax" class="form-control"
                     value="<?= htmlspecialchars((string)$val('fax'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('fax'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">郵便番号 <span class="text-danger">*</span></label>
              <input type="text" name="zip" class="form-control"
                     value="<?= htmlspecialchars((string)$val('zip'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('zip'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">都道府県 <span class="text-danger">*</span></label>
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
              <label class="form-label">住所（市町村以下） <span class="text-danger">*</span></label>
              <input type="text" name="addr01" class="form-control"
                     value="<?= htmlspecialchars((string)$val('addr01'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('addr01'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">支店等 郵便番号</label>
              <input type="text" name="zip02" class="form-control"
                     value="<?= htmlspecialchars((string)$val('zip02'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('zip02'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">支店等 都道府県</label>
              <select name="pref02_code" class="form-select">
                <option value="">選択してください</option>
                <?php foreach ($pref as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('pref02_code') === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php $err('pref02_code'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">支店等 住所</label>
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

            <div class="col-12">
              <label class="form-label">ご利用目的</label>
              <textarea name="purpose" class="form-control" rows="4"><?= htmlspecialchars((string)$val('purpose'), ENT_QUOTES, 'UTF-8') ?></textarea>
              <?php $err('purpose'); ?>
            </div>

          </div>
        </div>
      </div>

      <!-- ご来社経緯 -->
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
              <label class="form-label">来社経緯 <span class="text-danger">*</span></label>
              <select name="background" class="form-select">
                <option value="">選択してください</option>
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

            <div class="col-12 col-md-6">
              <label class="form-label">その他</label>
              <input type="text" name="others" class="form-control"
                     value="<?= htmlspecialchars((string)$val('others'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('others'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">備考</label>
              <textarea name="remarks" class="form-control" rows="4"><?= htmlspecialchars((string)$val('remarks'), ENT_QUOTES, 'UTF-8') ?></textarea>
              <?php $err('remarks'); ?>
            </div>

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
            <a class="btn btn-outline-secondary" href="/office_customers">戻る</a>

            <?php if ($isEdit && $canEdit && !$deleted): ?>
              <!-- 入れ子禁止：button formaction で削除へ分岐 -->
              <button
                type="submit"
                class="btn btn-outline-danger"
                formmethod="post"
                formaction="/office_customers/<?= $id ?>/delete"
                onclick="return confirm('削除（論理削除）します。よろしいですか？');"
              >削除（論理削除）</button>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div><!-- /tiles stack -->

  </form>

</div>

<script src="/js/officeCustomersForm.js" defer></script>
