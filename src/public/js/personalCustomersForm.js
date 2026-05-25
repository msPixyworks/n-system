/**
 * /public/js/personalCustomersForm.js
 * ============================================================
 * 役割:
 * - 個人顧客フォーム固有の入力補助（wiring）
 *
 * 方針（officeCustomersForm.js と同期）:
 * - 共通処理は common.js（KCore.FormHelpers）に寄せる
 * - このファイルは wiring に徹する
 * - カナ正規化やメール小文字化は blur で行う
 *
 * 実装内容:
 * - カナ正規化（KV相当）: letter / office_letter / family *_letter
 * - メール小文字化: mail01/mail02 + family *_mail01/*_mail02
 * - 電話数字化（最大15桁）: tel01/mobile01/emergency_tel + office_tel01/02 + family tel
 * - 郵便番号→住所補完（zipcloud）:
 *   - 本人: zip -> pref_code, addr01
 *   - 勤務先: office_zip -> office_pref_code, office_addr01
 *   - 家族(1..5): *_zip -> *_pref_code, *_addr01
 * - 誕生日: 年/月に応じて 日selectをdisable/hidden（carsForm.js と同等）
 */

(function () {
  'use strict';

  function toInt(v) {
    const s = String(v ?? '').replace(/[^0-9]/g, '');
    return s === '' ? 0 : parseInt(s, 10);
  }

  function daysInMonth(year, month) {
    if (!year || !month) return 31;
    return new Date(year, month, 0).getDate(); // month: 1-12
  }

  function rebuildDaySelect(daySelect, year, month) {
    if (!daySelect) return;

    const max = daysInMonth(year, month);
    const current = toInt(daySelect.value);

    const opts = Array.from(daySelect.options || []);
    opts.forEach((opt) => {
      const val = toInt(opt.value);
      if (val === 0) {
        opt.disabled = false;
        opt.hidden = false;
        return;
      }
      const ok = val <= max;
      opt.disabled = !ok;
      opt.hidden = !ok;
    });

    if (current > max) {
      daySelect.value = '0';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[action^="/personal_customers"]');
    if (!form) return;

    const helpers = (window.KCore && window.KCore.FormHelpers) ? window.KCore.FormHelpers : null;
    if (!helpers) {
      console.warn('[personalCustomersForm] KCore.FormHelpers not found. common.js is not loaded?');
      return;
    }

    // ------------------------------------------------------------
    // 1) カナ正規化（blur）: KV 相当
    // ------------------------------------------------------------
    const kanaFields = [
      'letter',
      'office_letter',
      'first_letter',
      'second_letter',
      'third_letter',
      'fourth_letter',
      'fifth_letter',
    ];

    kanaFields.forEach(function (name) {
      const el = form.querySelector(`input[name="${name}"]`);
      if (!el) return;

      el.addEventListener('blur', function () {
        const v = helpers.normalizeKanaKV(el.value);
        const next = (v ?? '').trim();
        if (el.value !== next) el.value = next;
      });
    });

    // ------------------------------------------------------------
    // 2) メール小文字化（blur）
    // ------------------------------------------------------------
    const mailFields = [
      'mail01', 'mail02',
      'first_mail01', 'first_mail02',
      'second_mail01', 'second_mail02',
      'third_mail01', 'third_mail02',
      'fourth_mail01', 'fourth_mail02',
      'fifth_mail01', 'fifth_mail02',
    ];

    mailFields.forEach(function (name) {
      const el = form.querySelector(`input[name="${name}"]`);
      if (!el) return;
      helpers.attachLowercaseNormalize(el, { trim: true, toLower: true });
    });

    // ------------------------------------------------------------
    // 3) 電話（数字のみ・最大15桁）
    // ------------------------------------------------------------
    const phoneNames = [
      'tel01',
      'mobile01',
      'emergency_tel',
      'office_tel01',
      'office_tel02',
      'first_tel01', 'first_tel02',
      'second_tel01', 'second_tel02',
      'third_tel01', 'third_tel02',
      'fourth_tel01', 'fourth_tel02',
      'fifth_tel01', 'fifth_tel02',
    ];

    const phoneEls = phoneNames.map((n) => form.querySelector(`input[name="${n}"]`)).filter(Boolean);
    helpers.normalizePhones(phoneEls);

    // ------------------------------------------------------------
    // 4) 郵便番号 → 住所自動補完（zipcloud）
    //    overwrite はデフォルト autoOnly（共通ヘルパの仕様に従う）
    // ------------------------------------------------------------
    function attachZip(zipName, prefName, addrName) {
      const zipEl = form.querySelector(`input[name="${zipName}"]`);
      const prefEl = form.querySelector(`select[name="${prefName}"]`);
      const addrEl = form.querySelector(`input[name="${addrName}"]`);

      if (!zipEl) return;

      helpers.attachZipAutoFill(zipEl, prefEl || null, addrEl || null, {
        overwrite: 'autoOnly',
        debounceMs: 250
      });
    }

    // 本人
    attachZip('zip', 'pref_code', 'addr01');

    // 勤務先
    attachZip('office_zip', 'office_pref_code', 'office_addr01');

    // 家族(1..5)
    attachZip('first_zip',  'first_pref_code',  'first_addr01');
    attachZip('second_zip', 'second_pref_code', 'second_addr01');
    attachZip('third_zip',  'third_pref_code',  'third_addr01');
    attachZip('fourth_zip', 'fourth_pref_code', 'fourth_addr01');
    attachZip('fifth_zip',  'fifth_pref_code',  'fifth_addr01');

    // ------------------------------------------------------------
    // 5) 誕生日（日付の存在しない日をJSで抑止）
    //    - year/month が 0 の場合は絞らない（max31）
    // ------------------------------------------------------------
    const yEl = form.querySelector('select[name="birthday_year"]');
    const mEl = form.querySelector('select[name="birthday_month"]');
    const dEl = form.querySelector('select[name="birthday_day"]');

    function applyBirthdayGuard() {
      const y = yEl ? toInt(yEl.value) : 0;
      const m = mEl ? toInt(mEl.value) : 0;
      rebuildDaySelect(dEl, y, m);
    }

    if (yEl && mEl && dEl) {
      yEl.addEventListener('change', applyBirthdayGuard);
      mEl.addEventListener('change', applyBirthdayGuard);
      applyBirthdayGuard();
    }
  });
})();
