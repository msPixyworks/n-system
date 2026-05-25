<?php
/**
 * views/components/lessee_select_inline.php
 * ============================================================
 * リース先選択（インライン部品）
 *
 * 要望対応:
 * - 登録/編集画面で「種別」「リース先ID」は表示しない（hiddenで保持）
 * - モーダルを開く「検索」ボタンは常に表示する
 *
 * 依存:
 * - /public/js/components/lesseePicker.js が hidden を更新
 */

$cfg  = $cfg ?? (require __DIR__ . '/../../../app/config.php');
$item = $item ?? null;
$old  = $old  ?? [];

$val = function (string $k, $default = '') use ($old, $item) {
    if (is_array($old) && array_key_exists($k, $old)) return $old[$k];
    if (is_array($item) && array_key_exists($k, $item)) return $item[$k];
    return $default;
};

// 既定は office（検索で選択すれば上書きされる）
$lesseeTypeVal = (string)$val('lessee_type', 'office');
$lesseeIdVal   = (string)$val('lessee_id', '');
$lesseeNameVal = (string)$val('lessee_name', ''); // 表示用（POSTしない）
?>

<!-- ★POST用 hidden（必ず送信される） -->
<input type="hidden" name="lessee_type" value="<?= htmlspecialchars($lesseeTypeVal, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="lessee_id" value="<?= htmlspecialchars($lesseeIdVal, ENT_QUOTES, 'UTF-8') ?>">

<div class="row g-3 align-items-end">
  <div class="col-md-8">
    <label class="form-label">リース先（表示）</label>
    <input type="text" id="lessee_name_display" class="form-control"
      value="<?= htmlspecialchars($lesseeNameVal, ENT_QUOTES, 'UTF-8') ?>"
      readonly
      placeholder="検索で選択すると表示されます">
    <div class="form-text">
      ※ 保存は内部的に「種別＋ID」で行います（画面には表示しません）
    </div>
  </div>

  <div class="col-md-4 d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary js-open-lessee-picker">
      <i class="fa-solid fa-magnifying-glass"></i> 検索
    </button>
    <button type="button" class="btn btn-outline-secondary js-clear-lessee" title="クリア">
      <i class="fa-solid fa-eraser"></i>
    </button>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector('.js-clear-lessee');
    if (!btn) return;

    btn.addEventListener('click', function () {
      const form = btn.closest('form');
      if (!form) return;

      const typeEl = form.querySelector('input[name="lessee_type"]');
      const idEl   = form.querySelector('input[name="lessee_id"]');
      const nameEl = form.querySelector('#lessee_name_display');

      if (typeEl) typeEl.value = 'office';
      if (idEl) idEl.value = '';
      if (nameEl) nameEl.value = '';
      form.dispatchEvent(new Event('lessee:changed'));
    });
  });
</script>
