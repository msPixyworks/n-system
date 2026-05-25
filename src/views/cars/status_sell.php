<?php
/**
 * views/cars/status_sell.php
 * ============================================================
 * 役割:
 * - 車両を「お客様所有（販売済）」へ変更する画面
 *
 * 対象:
 * - 在庫 → 販売済
 *
 * 入力:
 * - 販売日（必須）
 * - 販売先（任意）
 * - 販売額（必須）
 * - 消費税（必須）
 * - リサイクル料（必須）
 * - その他諸費用（必須）
 * - 合計金額（表示用・JS計算）
 * - 備考（任意）
 *
 * 方針:
 * - 既存の lessee picker を流用する
 * - hidden は customer_type / customer_id を使う
 * - 表示用は customer_name_display を使う
 * - submit 時の実際の合計は Controller 側で再計算する
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me  = $me  ?? Auth::user();
$car = $car ?? [];

$e   = (isset($errors) && is_array($errors)) ? $errors : [];
$old = (isset($old) && is_array($old)) ? $old : [];

$val = function (string $k, $default = '') use ($old) {
    if (array_key_exists($k, $old)) return $old[$k];
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

$calcTotal = function () use ($val) {
    $a = (int)preg_replace('/[^0-9]/', '', (string)$val('sale_price', '0'));
    $b = (int)preg_replace('/[^0-9]/', '', (string)$val('tax_amount', '0'));
    $c = (int)preg_replace('/[^0-9]/', '', (string)$val('recycle_fee', '0'));
    $d = (int)preg_replace('/[^0-9]/', '', (string)$val('other_fee', '0'));
    return $a + $b + $c + $d;
};

$backUrl = '/cars/' . (int)($car['id'] ?? 0);
?>
<div class="k-page cars-status-sell">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">販売済へ変更</h1>
      <div class="k-page__sub">在庫車両をお客様所有（販売済）へ変更します。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">戻る</a>
    </div>
  </div>

  <?php if (!empty($e['__global'])): ?>
    <div class="alert alert-danger mb-3">
      <?= htmlspecialchars(implode(' / ', (array)$e['__global']), ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form id="car-status-sell-form"
        method="post"
        action="/cars/<?= (int)($car['id'] ?? 0) ?>/status/sell"
        autocomplete="off">

    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="d-flex flex-column gap-3">

      <!-- 対象車両 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">対象車両</div>
          <div class="k-section-sub text-muted">管理番号 / 車両番号 / 車台番号</div>
        </div>

        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-md-4">
              <label class="form-label">管理番号</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars((string)($car['management_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">車両番号</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars((string)($car['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">車台番号</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars((string)($car['chassis_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

          </div>
        </div>
      </div>

      <!-- 販売先 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">販売先</div>
          <div class="k-section-sub text-muted">法人 / 個人 横断検索（任意）</div>
        </div>

        <div class="k-card__body">
          <?php require __DIR__ . '/../components/customer_select_inline.php'; ?>
          <?php $err('customer_type'); ?>
          <?php $err('customer_id'); ?>
        </div>
      </div>

      <!-- 販売情報 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">販売情報</div>
          <div class="k-section-sub text-muted">販売日 / 金額内訳</div>
        </div>

        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">販売日 <span class="text-danger">*</span></label>
              <input type="date"
                     name="sold_at"
                     class="form-control"
                     value="<?= htmlspecialchars((string)$val('sold_at'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('sold_at'); ?>
            </div>

            <div class="col-md-6">
              <label class="form-label">合計金額</label>
              <input type="text"
                     id="sale_total_display"
                     class="form-control"
                     readonly
                     value="<?= htmlspecialchars(number_format($calcTotal()), ENT_QUOTES, 'UTF-8') ?>">
              <div class="form-text">※ 合計金額 = 販売額 + 消費税 + リサイクル料 + その他諸費用</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">販売額 <span class="text-danger">*</span></label>
              <input type="text"
                     name="sale_price"
                     class="form-control js-num-comma js-sale-part"
                     value="<?= htmlspecialchars($fmt($val('sale_price')), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('sale_price'); ?>
            </div>

            <div class="col-md-6">
              <label class="form-label">消費税 <span class="text-danger">*</span></label>
              <input type="text"
                     name="tax_amount"
                     class="form-control js-num-comma js-sale-part"
                     value="<?= htmlspecialchars($fmt($val('tax_amount')), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('tax_amount'); ?>
            </div>

            <div class="col-md-6">
              <label class="form-label">リサイクル料 <span class="text-danger">*</span></label>
              <input type="text"
                     name="recycle_fee"
                     class="form-control js-num-comma js-sale-part"
                     value="<?= htmlspecialchars($fmt($val('recycle_fee')), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('recycle_fee'); ?>
            </div>

            <div class="col-md-6">
              <label class="form-label">その他諸費用 <span class="text-danger">*</span></label>
              <input type="text"
                     name="other_fee"
                     class="form-control js-num-comma js-sale-part"
                     value="<?= htmlspecialchars($fmt($val('other_fee')), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('other_fee'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">備考</label>
              <textarea name="notes"
                        class="form-control"
                        rows="4"><?= htmlspecialchars((string)$val('notes'), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

          </div>
        </div>
      </div>

      <!-- 注意 -->
      <div class="alert alert-warning mb-0">
        リース中・リース予定の車両は販売済へ変更できません。
      </div>

      <!-- 操作 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">操作</div>
          <div class="k-section-sub text-muted">保存 / 戻る</div>
        </div>

        <div class="k-card__body">
          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-success">販売済へ変更する</button>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">戻る</a>
          </div>
        </div>
      </div>

    </div>

  </form>

  <?php require __DIR__ . '/../components/lessee_picker_modal.php'; ?>

</div>

<script src="/js/components/lesseePicker.js" defer></script>
<script src="/js/carStatusForm.js" defer></script>