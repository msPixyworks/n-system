<?php
/**
 * views/components/lessee_jump_inline.php
 * ============================================================
 * 一覧ページ用：リース先選択→遷移（ジャンプ）コンポーネント
 *
 * 依存:
 * - views/components/lessee_picker_modal.php（同ページにinclude）
 * - /public/js/components/lesseePicker.js（選択反映に流用）
 *
 * 注意:
 * - 既存の lessee_select_inline.php（登録/編集用）とは別用途なので分離する
 */

$cfg = $cfg ?? (require __DIR__ . '/../../../app/config.php');
$leaseOpts = $cfg['options']['car_leases'] ?? [];
$lesseeTypes = $leaseOpts['lessee_types'] ?? ['office' => '法人', 'personal' => '個人'];
?>

<form id="lessee-jump" class="row g-2 mb-3 k-filter" onsubmit="return false;">
  <!-- 遷移用 hidden（lesseePicker.js に拾わせるため name を合わせる） -->
  <input type="hidden" name="lessee_type" value="office">
  <input type="hidden" name="lessee_id" value="">

  <div class="col-12 col-md-8">
    <label class="form-label">リース先（検索して選択）</label>
    <input type="text" id="lessee_name_display" class="form-control" readonly placeholder="検索で選択すると表示されます">
  </div>

  <!-- 右側：検索＆クリア（ここに「リース先別を見る」は置かない） -->
  <div class="col-12 col-md-4 d-flex align-items-center gap-2 flex-wrap mt-md-4">
    <button type="button" class="btn btn-outline-secondary btn-sm k-btn-filter js-open-lessee-picker">
      <i class="fa-solid fa-magnifying-glass"></i> 検索
    </button>

    <button type="button" class="btn btn-outline-secondary btn-sm k-btn-filter js-clear-lessee" title="クリア" aria-label="クリア">
      <i class="fa-solid fa-eraser"></i>
    </button>
  </div>

  <!-- ★別行：全体中央の下に配置 -->
  <div class="col-12">
    <div class="d-flex justify-content-center mt-3">
      <button type="button" class="btn btn-outline-secondary btn-sm k-btn-filter" id="lessee-jump-btn">
        リース先別を見る
      </button>
    </div>
  </div>
</form>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('lessee-jump');
    if (!form) return;

    const jumpBtn = document.getElementById('lessee-jump-btn');
    if (jumpBtn) {
      jumpBtn.addEventListener('click', function () {
        const typeEl = form.querySelector('input[name="lessee_type"]');
        const idEl   = form.querySelector('input[name="lessee_id"]');
        const t = typeEl ? String(typeEl.value || '') : '';
        const id = idEl ? String(idEl.value || '').replace(/[^0-9]/g, '') : '';
        if (!t || !id) return;
        window.location.href = '/car_leases/lessees/' + encodeURIComponent(t) + '/' + encodeURIComponent(id);
      });
    }

    const clearBtn = form.querySelector('.js-clear-lessee');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        const typeEl = form.querySelector('input[name="lessee_type"]');
        const idEl   = form.querySelector('input[name="lessee_id"]');
        const nameEl = form.querySelector('#lessee_name_display');
        if (typeEl) typeEl.value = 'office';
        if (idEl) idEl.value = '';
        if (nameEl) nameEl.value = '';
        form.dispatchEvent(new Event('lessee:changed'));
      });
    }
  });
</script>
