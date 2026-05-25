<?php
/**
 * views/components/partner_select_inline.php
 * ============================================================
 * 役割:
 * - 代車先選択（インライン部品）
 *
 * 用途:
 * - 車両の「代車へ変更」画面で使用
 *
 * 仕様:
 * - 保存用 hidden:
 *   - partner_type
 *   - partner_id
 * - 表示用:
 *   - partner_name_display
 * - 検索は既存 lessee picker を流用
 *
 * 注意:
 * - partner_type / partner_id は任意
 * - クリア時は両方空に戻す
 * - lesseePicker.js 側で data-lessee-target-* を見て反映する
 */

$cfg = $cfg ?? (require __DIR__ . '/../../../app/config.php');
$old = $old ?? [];

$val = function (string $k, $default = '') use ($old) {
    if (is_array($old) && array_key_exists($k, $old)) return $old[$k];
    return $default;
};

$partnerTypeVal = (string)$val('partner_type', '');
$partnerIdVal   = (string)$val('partner_id', '');
$partnerNameVal = (string)$val('partner_name_display', '');
?>

<input type="hidden" name="partner_type" value="<?= htmlspecialchars($partnerTypeVal, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="partner_id" value="<?= htmlspecialchars($partnerIdVal, ENT_QUOTES, 'UTF-8') ?>">

<div class="row g-3 align-items-end">
  <div class="col-md-8">
    <label class="form-label">代車先（任意）</label>
    <input type="text"
           id="partner_name_display"
           class="form-control"
           value="<?= htmlspecialchars($partnerNameVal, ENT_QUOTES, 'UTF-8') ?>"
           readonly
           placeholder="検索で選択すると表示されます">
    <div class="form-text">
      ※ 保存は内部的に「種別＋ID」で行います（未指定でも保存できます）
    </div>
  </div>

  <div class="col-md-4 d-flex gap-2">
    <button type="button"
            class="btn btn-outline-secondary js-open-lessee-picker"
            data-lessee-target-type="partner_type"
            data-lessee-target-id="partner_id"
            data-lessee-target-display="partner_name_display">
      <i class="fa-solid fa-magnifying-glass"></i> 検索
    </button>

    <button type="button"
            class="btn btn-outline-secondary js-clear-lessee"
            data-lessee-target-type="partner_type"
            data-lessee-target-id="partner_id"
            data-lessee-target-display="partner_name_display"
            title="クリア">
      <i class="fa-solid fa-eraser"></i>
    </button>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const buttons = Array.from(document.querySelectorAll('.js-clear-lessee[data-lessee-target-type="partner_type"]'));
    buttons.forEach(function (btn) {
      if (btn.dataset.clearInited === '1') return;
      btn.dataset.clearInited = '1';

      btn.addEventListener('click', function () {
        const form = btn.closest('form');
        if (!form) return;

        const typeName = btn.dataset.lesseeTargetType || 'partner_type';
        const idName   = btn.dataset.lesseeTargetId || 'partner_id';
        const dispId   = btn.dataset.lesseeTargetDisplay || 'partner_name_display';

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