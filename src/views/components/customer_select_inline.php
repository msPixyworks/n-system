<?php
/**
 * views/components/customer_select_inline.php
 * ============================================================
 * 役割:
 * - 販売先選択（インライン部品）
 *
 * 用途:
 * - 車両の「販売済へ変更」画面で使用
 *
 * 仕様:
 * - 保存用 hidden:
 *   - customer_type
 *   - customer_id
 * - 表示用:
 *   - customer_name_display
 * - 検索は既存 lessee picker を流用
 *
 * 注意:
 * - customer_type / customer_id は任意
 * - クリア時は両方空に戻す
 * - lesseePicker.js 側で data-lessee-target-* を見て反映する
 */

$cfg = $cfg ?? (require __DIR__ . '/../../../app/config.php');
$old = $old ?? [];

$val = function (string $k, $default = '') use ($old) {
    if (is_array($old) && array_key_exists($k, $old)) return $old[$k];
    return $default;
};

$customerTypeVal = (string)$val('customer_type', '');
$customerIdVal   = (string)$val('customer_id', '');
$customerNameVal = (string)$val('customer_name_display', '');
?>

<input type="hidden" name="customer_type" value="<?= htmlspecialchars($customerTypeVal, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="customer_id" value="<?= htmlspecialchars($customerIdVal, ENT_QUOTES, 'UTF-8') ?>">

<div class="row g-3 align-items-end">
  <div class="col-md-8">
    <label class="form-label">販売先（任意）</label>
    <input type="text"
           id="customer_name_display"
           class="form-control"
           value="<?= htmlspecialchars($customerNameVal, ENT_QUOTES, 'UTF-8') ?>"
           readonly
           placeholder="検索で選択すると表示されます">
    <div class="form-text">
      ※ 保存は内部的に「種別＋ID」で行います（未指定でも保存できます）
    </div>
  </div>

  <div class="col-md-4 d-flex gap-2">
    <button type="button"
            class="btn btn-outline-secondary js-open-lessee-picker"
            data-lessee-target-type="customer_type"
            data-lessee-target-id="customer_id"
            data-lessee-target-display="customer_name_display">
      <i class="fa-solid fa-magnifying-glass"></i> 検索
    </button>

    <button type="button"
            class="btn btn-outline-secondary js-clear-lessee"
            data-lessee-target-type="customer_type"
            data-lessee-target-id="customer_id"
            data-lessee-target-display="customer_name_display"
            title="クリア">
      <i class="fa-solid fa-eraser"></i>
    </button>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const buttons = Array.from(document.querySelectorAll('.js-clear-lessee[data-lessee-target-type="customer_type"]'));
    buttons.forEach(function (btn) {
      if (btn.dataset.clearInited === '1') return;
      btn.dataset.clearInited = '1';

      btn.addEventListener('click', function () {
        const form = btn.closest('form');
        if (!form) return;

        const typeName = btn.dataset.lesseeTargetType || 'customer_type';
        const idName   = btn.dataset.lesseeTargetId || 'customer_id';
        const dispId   = btn.dataset.lesseeTargetDisplay || 'customer_name_display';

        const typeEl = form.querySelector('input[name="' + typeName + '"]');
        const idEl   = form.querySelector('input[name="' + idName + '"]');
        const dispEl = form.querySelector('#' + dispId);

        if (typeEl) typeEl.value = '';
        if (idEl) idEl.value = '';
        if (dispEl) dispEl.value = '';

        form.dispatchEvent(new Event('lessee:changed'));
      });
    });
  });
</script>