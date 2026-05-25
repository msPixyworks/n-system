<?php
/**
 * views/car_costs/form.php
 * ============================================================
 * 車両 年度別コスト編集（過去年度のみ）
 *
 * JS:
 * - 数値入力補助は /public/js/carFyCostsForm.js に分離
 */

$cfg  = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me   = $me  ?? Auth::user();

$e    = (isset($errors) && is_array($errors)) ? $errors : [];
$old  = (isset($old)    && is_array($old))    ? $old    : [];
$item = (isset($item)   && is_array($item))   ? $item   : null;

$carId = (int)($carId ?? 0);
$fy    = (int)($fy ?? 0);

$val = function (string $k, $default = '') use ($old, $item) {
    if (is_array($old) && array_key_exists($k, $old))  return $old[$k];
    if ($item && array_key_exists($k, $item)) return $item[$k];
    return $default;
};

$err = function (string $k) use ($e) {
    if (!empty($e[$k])) {
        echo '<div class="form-error">' .
            htmlspecialchars(implode(' / ', (array)$e[$k]), ENT_QUOTES, 'UTF-8') .
            '</div>';
    }
};

$fmt = function ($x) {
    $s = trim((string)$x);
    if ($s === '') return '';
    $s = str_replace(',', '', $s);
    if (!preg_match('/^[0-9]+$/', $s)) return (string)$x;
    return number_format((int)$s);
};

$actionUrl = '/cars/' . $carId . '/fy_costs/' . $fy;
?>
<div class="k-page car-fy-costs-form">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">年度別コスト編集（<?= (int)$fy ?> 年度）</h1>
      <div class="k-page__sub">過去年度のコスト情報を編集します。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/cars/<?= $carId ?>/fy_costs">戻る</a>
    </div>
  </div>

  <?php if (!empty($e['__global'])): ?>
    <div class="alert alert-danger mb-3">
      <?= htmlspecialchars(implode(' / ', (array)$e['__global']), ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form id="car-fy-cost-form" method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="d-flex flex-column gap-3">

      <!-- 入力 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">入力</div>
            <div class="k-section-sub text-muted">税金 / 保険 / 経費 / 備考</div>
          </div>
        </div>

        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-12 col-md-6">
              <label class="form-label">自動車税（円）</label>
              <input type="text" name="tax_amount" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('tax_amount')), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('tax_amount'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">自動車保険料（円）</label>
              <input type="text" name="insurance_amount" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('insurance_amount')), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('insurance_amount'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">経費総額（円）</label>
              <input type="text" name="expense_amount" class="form-control js-num-comma"
                     value="<?= htmlspecialchars($fmt($val('expense_amount')), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('expense_amount'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">備考</label>
              <textarea name="notes" class="form-control" rows="4"><?= htmlspecialchars((string)$val('notes'), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

          </div>
        </div>
      </div>

      <!-- 操作 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">操作</div>
            <div class="k-section-sub text-muted">更新 / キャンセル</div>
          </div>
        </div>

        <div class="k-card__body">
          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">更新する</button>
            <a class="btn btn-outline-secondary" href="/cars/<?= $carId ?>/fy_costs">キャンセル</a>
          </div>
        </div>
      </div>

    </div>

  </form>

</div>

<script src="/js/carFyCostsForm.js"></script>
