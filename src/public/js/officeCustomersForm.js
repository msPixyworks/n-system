/**
 * /public/js/officeCustomersForm.js
 * ============================================================
 * 役割:
 * - 法人顧客フォーム固有の入力補助（wiring）
 *
 * 方針（usersForm.js と同期）:
 * - 共通処理は common.js（KCore.FormHelpers）に寄せる
 * - このファイルは wiring に徹する
 * - カナ正規化やメール小文字化は blur で行う
 *
 * 追加（今回）:
 * - 郵便番号 → 住所補完は zipcloud（attachZipAutoFill）を使用
 * - 電話/FAX は normalizePhones を使用（数字のみ・最大15桁）
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[action^="/office_customers"]');
    if (!form) return;

    const helpers = (window.KCore && window.KCore.FormHelpers) ? window.KCore.FormHelpers : null;
    if (!helpers) {
      console.warn('[officeCustomersForm] KCore.FormHelpers not found. common.js is not loaded?');
      return;
    }

    // ------------------------------------------------------------
    // 1) カナ正規化（blur）: KV 相当
    // ------------------------------------------------------------
    const kanaFields = [
      'company_name_phonetic',
      'representative_letter',
      'manager_letter',
      'driver_letter',
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
    ['mail01', 'mail02'].forEach(function (name) {
      const el = form.querySelector(`input[name="${name}"]`);
      if (!el) return;

      helpers.attachLowercaseNormalize(el, { trim: true, toLower: true });
    });

    // ------------------------------------------------------------
    // 3) 電話/FAX（数字のみ・最大15桁）
    // ------------------------------------------------------------
    const telEl = form.querySelector('input[name="tel"]');
    const faxEl = form.querySelector('input[name="fax"]');
    helpers.normalizePhones([telEl, faxEl]);

    // ------------------------------------------------------------
    // 4) 郵便番号 → 住所自動補完（zipcloud）
    //    - 本社: zip -> pref_code, addr01
    //    - 支店: zip02 -> pref02_code, addr02
    //    overwrite はデフォルト autoOnly（共通ヘルパの仕様に従う）
    // ------------------------------------------------------------
    const zipEl = form.querySelector('input[name="zip"]');
    const prefEl = form.querySelector('select[name="pref_code"]');
    const addrEl = form.querySelector('input[name="addr01"]');

    if (zipEl) {
      helpers.attachZipAutoFill(zipEl, prefEl, addrEl, {
        overwrite: 'autoOnly',
        debounceMs: 250
      });
    }

    const zip2El = form.querySelector('input[name="zip02"]');
    const pref2El = form.querySelector('select[name="pref02_code"]');
    const addr2El = form.querySelector('input[name="addr02"]');

    if (zip2El) {
      helpers.attachZipAutoFill(zip2El, pref2El, addr2El, {
        overwrite: 'autoOnly',
        debounceMs: 250
      });
    }
  });
})();
