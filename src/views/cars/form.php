<?php
/**
 * views/cars/form.php
 * ============================================================
 * 役割:
 * - cars の 登録/編集フォーム（共通）
 *
 * UI（今回）:
 * - セクションごとにカード（タイル）分割（1段に1個＝フル幅で縦積み）
 * - 入力項目は原則「1行2項目まで」（col-md-6）
 *
 * 追加（今回）:
 * - type_of_car（自動車の種別）に id を付与（軽自動車=10800 を JS が判定するため）
 */

$cfg  = $cfg  ?? (require __DIR__ . '/../../app/config.php');

$e    = (isset($errors) && is_array($errors)) ? $errors : [];
$old  = (isset($old)    && is_array($old))    ? $old    : [];
$item = (isset($item)   && is_array($item))   ? $item   : null;

$me = $me ?? Auth::user();

$optsCommon = $cfg['options']['common'] ?? [];
$optsCars   = $cfg['options']['cars'] ?? [];

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
$titleText = $isEdit ? '車両編集' : '車両登録';

$actionUrl = $isEdit
    ? '/cars/' . (int)$item['id']
    : '/cars';

$canEdit = $me ? Policies::canEditCars($me) : false;

$makers = $optsCars['maker'] ?? [];
$modelsByMaker = $optsCars['car_models_by_maker'] ?? [];

$dateYears  = $optsCommon['date_years'] ?? [0 => '-- Please select --'];
$dateMonths = $optsCommon['date_months'] ?? [0 => '-- Please select --'];
$dateDays   = $optsCommon['date_days'] ?? [0 => '-- Please select --'];

$currentMaker = (string)$val('maker', '');
$currentModel = (string)$val('car_model', '');

$modelsForCurrentMaker = [];
if ($currentMaker !== '' && isset($modelsByMaker[$currentMaker]) && is_array($modelsByMaker[$currentMaker])) {
    $modelsForCurrentMaker = $modelsByMaker[$currentMaker];
} else {
    $modelsForCurrentMaker = [0 => '-- Please select --'];
}

$fmt = function ($x) {
    $s = trim((string)$x);
    if ($s === '') return '';
    $s = str_replace(',', '', $s);
    if (!preg_match('/^[0-9]+$/', $s)) return (string)$x;
    return number_format((int)$s);
};
?>
<div class="k-page cars-form">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title"><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></h1>
      <!-- <div class="k-page__sub">車両情報を入力して保存します。</div> -->
    </div>
  </div>

  <?php if (!empty($e['__global'])): ?>
    <div class="alert alert-danger mb-3">
      <?= htmlspecialchars(implode(' / ', (array)$e['__global']), ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form id="cars-form" method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <!-- ===================================================== -->
    <!-- タイル（カード）縦積み：1段に1個（フル幅） -->
    <!-- ===================================================== -->
    <div class="d-flex flex-column gap-3">

      <!-- ========================= -->
      <!-- 車検証情報 -->
      <!-- ========================= -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">車検証情報</div>
          <div class="k-section-sub text-muted">管理番号・車台番号など</div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-12 col-md-6">
              <label class="form-label">管理番号</label>
              <input type="text" name="management_number" class="form-control" readonly
                     value="<?= htmlspecialchars((string)$val('management_number'), ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="登録後に自動採番">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">車両番号</label>
              <input type="text" name="vehicle_number" class="form-control"
                     value="<?= htmlspecialchars((string)$val('vehicle_number'), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <!-- 重要なので 1行で使う（例外：col-12） -->
            <div class="col-12">
              <label class="form-label">車台番号 <span class="text-danger">*</span></label>
              <input type="text" name="chassis_number" class="form-control"
                     value="<?= htmlspecialchars((string)$val('chassis_number'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('chassis_number'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">登録年月日</label>
              <div class="d-flex gap-2">
                <select name="registration_year" class="form-select">
                  <?php foreach ($dateYears as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val('registration_year', 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="registration_month" class="form-select" data-ymd-month="registration">
                  <?php foreach ($dateMonths as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val('registration_month', 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="registration_day" class="form-select" data-ymd-day="registration">
                  <?php foreach ($dateDays as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val('registration_day', 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php $err('registration_year'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">初年度登録年月</label>
              <div class="d-flex gap-2">
                <select name="first_registration_year" class="form-select">
                  <?php foreach ($dateYears as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val('first_registration_year', 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="first_registration_month" class="form-select">
                  <?php foreach ($dateMonths as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val('first_registration_month', 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php $err('first_registration_year'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">メーカー</label>
              <select name="maker" class="form-select" id="cars-maker">
                <?php foreach ($makers as $k => $v): ?>
                  <option value="<?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>"
                    <?= ((string)$val('maker', '0') === (string)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php $err('maker'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">車種</label>
              <select name="car_model" class="form-select" id="cars-model">
                <?php foreach ($modelsForCurrentMaker as $k => $v): ?>
                  <option value="<?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>"
                    <?= ((string)$currentModel === (string)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php $err('car_model'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">型式指定番号</label>
              <input type="text" name="model_designation_number" class="form-control"
                     value="<?= htmlspecialchars((string)$val('model_designation_number'), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">類別区分番号</label>
              <input type="text" name="classification_division_number" class="form-control"
                     value="<?= htmlspecialchars((string)$val('classification_division_number'), ENT_QUOTES, 'UTF-8') ?>">
            </div>

          </div>
        </div>
      </div>

      <!-- ========================= -->
      <!-- 取引情報 -->
      <!-- ========================= -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">取引情報</div>
          <div class="k-section-sub text-muted">購入日・費用・税金など</div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-12 col-md-6">
              <label class="form-label">新車/中古車</label>
              <select name="new_used" class="form-select">
                <?php foreach (($optsCars['new_used'] ?? []) as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('new_used', 0) === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">購入年月日</label>
              <div class="d-flex gap-2">
                <select name="purchase_year" class="form-select">
                  <?php foreach ($dateYears as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val('purchase_year', 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="purchase_month" class="form-select" data-ymd-month="purchase">
                  <?php foreach ($dateMonths as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val('purchase_month', 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="purchase_day" class="form-select" data-ymd-day="purchase">
                  <?php foreach ($dateDays as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val('purchase_day', 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php $err('purchase_year'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">購入価格（円）</label>
              <input type="text" name="purchase_price" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('purchase_price')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">消費税（円）</label>
              <input type="text" name="consumption_tax" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('consumption_tax')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">リサイクル費用（円）</label>
              <input type="text" name="recycling_cost" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('recycling_cost')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">購入時諸費用（円）</label>
              <input type="text" name="purchase_costs" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('purchase_costs')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">自動車税（円）</label>
              <input type="text" name="car_tax" id="cars-car-tax" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('car_tax')), ENT_QUOTES, 'UTF-8') ?>">
              <div class="form-text">
                自動計算結果：<span id="output" class="fw-semibold"></span>
              </div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">自動車保険料（円）</label>
              <input type="text" name="car_insurance_premium" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('car_insurance_premium')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">経費総額（円）</label>
              <input type="text" name="total_expenses" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('total_expenses')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

          </div>
        </div>
      </div>

      <!-- ========================= -->
      <!-- 車両情報 -->
      <!-- ========================= -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">車両情報</div>
          <div class="k-section-sub text-muted">種別・用途・諸元など</div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-12 col-md-6">
              <label class="form-label">自動車の種別</label>
              <select name="type_of_car" id="type_of_car" class="form-select">
                <?php foreach (($optsCars['type_of_car'] ?? []) as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('type_of_car', 0) === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">用途</label>
              <select name="car_purpose" id="car_purpose" class="form-select">
                <?php foreach (($optsCars['car_purpose'] ?? []) as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('car_purpose', 0) === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">自家用・事業用</label>
              <select name="how_to_use" class="form-select">
                <?php foreach (($optsCars['how_to_use'] ?? []) as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('how_to_use', 0) === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">車体の形状</label>
              <select name="body_shape" class="form-select">
                <?php foreach (($optsCars['body_shape'] ?? []) as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('body_shape', 0) === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">年式</label>
              <select name="model_year" class="form-select">
                <?php foreach (($optsCars['model_year'] ?? []) as $k => $v): ?>
                  <option value="<?= (int)$k ?>" <?= ((int)$val('model_year', 0) === (int)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">走行距離（km）</label>
              <input type="text" name="mileage_amount" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('mileage_amount')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">排気量（cc）</label>
              <input type="text" name="displacement" id="displacement" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('displacement')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">車両重量（kg）</label>
              <input type="text" name="vehicle_weight" id="vehicle_weight" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('vehicle_weight')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">販売本体価格（円）</label>
              <input type="text" name="base_price" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('base_price')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">支払総額（円）</label>
              <input type="text" name="total_to_pay" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('total_to_pay')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

          </div>
        </div>
      </div>

      <!-- ========================= -->
      <!-- 安全装備 -->
      <!-- ========================= -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">安全装備</div>
          <div class="k-section-sub text-muted">Yes/No</div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">
            <?php foreach (($optsCars['safety_yes_no_fields'] ?? []) as $field => $labelText): ?>
              <div class="col-12 col-md-6">
                <label class="form-label"><?= htmlspecialchars((string)$labelText, ENT_QUOTES, 'UTF-8') ?></label>
                <select name="<?= htmlspecialchars((string)$field, ENT_QUOTES, 'UTF-8') ?>" class="form-select">
                  <?php foreach (($optsCommon['yes_no'] ?? []) as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val($field, 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- ========================= -->
      <!-- 快適装備 -->
      <!-- ========================= -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">快適装備</div>
          <div class="k-section-sub text-muted">適用/非適用</div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">
            <?php foreach (($optsCars['comfort_applicable_fields'] ?? []) as $field => $labelText): ?>
              <div class="col-12 col-md-6">
                <label class="form-label"><?= htmlspecialchars((string)$labelText, ENT_QUOTES, 'UTF-8') ?></label>
                <select name="<?= htmlspecialchars((string)$field, ENT_QUOTES, 'UTF-8') ?>" class="form-select">
                  <?php foreach (($optsCommon['applicable'] ?? []) as $k => $v): ?>
                    <option value="<?= (int)$k ?>" <?= ((int)$val($field, 0) === (int)$k) ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- ========================= -->
      <!-- 備考 -->
      <!-- ========================= -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">備考</div>
          <div class="k-section-sub text-muted">メモ</div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">法定整備</label>
              <textarea name="legal_maintenance" class="form-control" rows="4"><?= htmlspecialchars((string)$val('legal_maintenance'), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">保証</label>
              <textarea name="guarantee" class="form-control" rows="4"><?= htmlspecialchars((string)$val('guarantee'), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================= -->
      <!-- 操作 -->
      <!-- ========================= -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">操作</div>
          <div class="k-section-sub text-muted"><?= $isEdit ? '更新 / 戻る / 削除' : '登録 / 戻る' ?></div>
        </div>
        <div class="k-card__body">
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? '更新する' : '登録する' ?></button>
            <a class="btn btn-outline-secondary" href="/cars">戻る</a>
          </div>

          <?php if ($isEdit && $canEdit): ?>
            <?php $alreadyDeleted = !empty($item['deleted_at']); ?>
            <?php if (!$alreadyDeleted): ?>
              <div class="mt-3">
                <form method="post"
                      action="/cars/<?= (int)$item['id'] ?>/delete"
                      onsubmit="return confirm('削除（論理削除）します。よろしいですか？');">
                  <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                  <button type="submit" class="btn btn-outline-danger">削除（論理削除）</button>
                </form>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /tiles stack -->

  </form>

</div>

<script>
  window.KCore = window.KCore || {};
  window.KCore.Cars = window.KCore.Cars || {};
  window.KCore.Cars.modelsByMaker = <?= json_encode($modelsByMaker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="/js/carsForm.js" defer></script>
