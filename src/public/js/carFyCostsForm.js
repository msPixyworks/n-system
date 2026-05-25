/**
 * /public/js/carFyCostsForm.js
 * ============================================================
 * 役割:
 * - car_fy_costs フォームの数値入力補助
 *   - blur で 3桁カンマ表示
 *   - submit 時にカンマ除去（数字のみ残す）
 *
 * 前提:
 * - views/car_costs/form.php に以下が存在すること
 *   - #car-fy-cost-form
 *   - .js-num-comma
 */

(function () {
  'use strict';

  function formatComma(str) {
    const s = String(str ?? '').trim();
    if (s === '') return '';
    const digits = s.replace(/,/g, '').replace(/[^0-9]/g, '');
    if (digits === '') return '';
    const n = parseInt(digits, 10);
    if (isNaN(n)) return '';
    return n.toLocaleString('en-US');
  }

  function stripToDigits(str) {
    return String(str ?? '').replace(/,/g, '').replace(/[^0-9]/g, '');
  }

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('car-fy-cost-form');
    if (!form) return;

    const nums = Array.from(form.querySelectorAll('.js-num-comma'));
    nums.forEach((el) => {
      el.addEventListener('blur', function () {
        const next = formatComma(el.value);
        if (el.value !== next) el.value = next;
      });
    });

    form.addEventListener('submit', function () {
      nums.forEach((el) => {
        el.value = stripToDigits(el.value);
      });
    });
  });
})();
