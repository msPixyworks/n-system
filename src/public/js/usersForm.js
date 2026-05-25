/**
 * /public/js/usersForm.js
 * ============================================================
 * 役割:
 * - users フォーム固有の入力補助を付与する
 *
 * 方針:
 * - 共通化できる処理は common.js（KCore.FormHelpers）に寄せる
 * - このファイルは「usersでだけ必要な wiring（要素を拾って適用）」に徹する
 *
 * 変更点（今回）:
 * - カナ正規化は「入力中」ではなく「入力後（blur）」で行う
 *   → 変換でカーソルが飛ぶ等の違和感を減らす
 *
 * 前提:
 * - common.js が先に読み込まれていることが望ましい
 *   （未読み込みでも落ちないようにガードする）
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // users/form.php 以外で読み込まれても落ちないように
    const form = document.querySelector('form[action^="/users"]');
    if (!form) return;

    const helpers = (window.KCore && window.KCore.FormHelpers) ? window.KCore.FormHelpers : null;
    if (!helpers) {
      // common.js が読み込まれていない場合は何もしない（致命ではない）
      console.warn('[usersForm] KCore.FormHelpers not found. common.js is not loaded?');
      return;
    }

    // 社員名フリガナ（入力後にカナ正規化）
    const kanaEl = form.querySelector('input[name="name_kana"]');
    if (kanaEl) {
      // common.js の attachKanaNormalize は input + blur を付けるので、
      // 「blurだけ」にしたい場合はここで blur のみで呼ぶ。
      kanaEl.addEventListener('blur', function () {
        const v = helpers.normalizeKanaKV(kanaEl.value);
        const next = (v ?? '').trim();
        if (kanaEl.value !== next) kanaEl.value = next;
      });
    }

    // email（入力後に小文字化）
    const emailEl = form.querySelector('input[name="email"]');
    if (emailEl) {
      // common側の helper を使う（blurで適用）
      helpers.attachLowercaseNormalize(emailEl, {
        trim: true,
        toLower: true
      });
    }
  });
})();
