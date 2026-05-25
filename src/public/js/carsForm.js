/**
 * /public/js/carsForm.js
 * ============================================================
 * 役割:
 * - cars フォーム固有の入力補助（wiring）
 *   - maker → car_model 連動
 *   - 年/月/日の day select 絞り（存在しない日付をJSで抑止）
 *   - 数値入力：blurで3桁カンマ表示、submit時にカンマ除去
 *   - ★自動車税（簡易）: 用途 car_purpose により「排気量 or 重量」で自動計算
 *     + 軽自動車（type_of_car=1）の場合は常に 10,800
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

  function formatComma(str) {
    const s = String(str ?? '').trim();
    if (s === '') return '';
    const digits = s.replace(/,/g, '').replace(/[^0-9]/g, '');
    if (digits === '') return '';
    const n = parseInt(digits, 10);
    if (isNaN(n)) return '';
    return n.toLocaleString('en-US');
  }

  function stripComma(str) {
    return String(str ?? '').replace(/,/g, '').replace(/[^0-9]/g, '');
  }

  // ============================================================
  // ★自動車税（ユーザー提示ロジック）
  // ============================================================
  function getCarTaxDisplacement() {
    const el = document.getElementById('displacement');
    const displacement = el ? stripComma(el.value) : '';
    const d = displacement === '' ? 0 : Number(displacement);
    let outputValue;

    if ((0 < d) && (d <= 1000)) { outputValue = "29,500"; }
    else if ((1000 < d) && (d <= 1500)) { outputValue = "34,500"; }
    else if ((1500 < d) && (d <= 2000)) { outputValue = "39,500"; }
    else if ((2000 < d) && (d <= 2500)) { outputValue = "45,000"; }
    else if ((2500 < d) && (d <= 3000)) { outputValue = "51,000"; }
    else if ((3000 < d) && (d <= 3500)) { outputValue = "58,000"; }
    else if ((3500 < d) && (d <= 4000)) { outputValue = "66,500"; }
    else if ((4000 < d) && (d <= 4500)) { outputValue = "76,500"; }
    else if ((displacement === "") || (d === 0)) { outputValue = ""; }
    else { outputValue = "該当するデータがありません。"; }

    return outputValue;
  }

  function getCarTaxWeight() {
    const el = document.getElementById('vehicle_weight');
    const weight = el ? stripComma(el.value) : '';
    const w = weight === '' ? 0 : Number(weight);
    let outputValue;

    if ((0 < w) && (w <= 1000)) { outputValue = "8,000"; }
    else if ((1001 < w) && (w <= 2000)) { outputValue = "11,500"; }
    else if ((2001 < w) && (w <= 3000)) { outputValue = "16,000"; }
    else if ((3001 < w) && (w <= 4000)) { outputValue = "20,500"; }
    else if ((weight === "") || (w === 0)) { outputValue = ""; }
    else { outputValue = "該当するデータがありません。"; }

    return outputValue;
  }

  function getCarTax() {
    const purposeEl = document.getElementById('car_purpose');
    const purpose = purposeEl ? String(purposeEl.value) : '';

    // ★軽自動車（type_of_car=1）は常に 10,800
    const typeEl = document.getElementById('type_of_car');
    const type = typeEl ? String(typeEl.value) : '';
    if (type === '1') {
      const fixed = "10,800";

      const outputDiv = document.getElementById("output");
      if (outputDiv) outputDiv.textContent = fixed;

      const carTaxEl = document.getElementById('cars-car-tax');
      if (carTaxEl) carTaxEl.value = fixed;

      return fixed;
    }

    let printValue;
    if (purpose === '1') { // 乗用
      printValue = getCarTaxDisplacement();
    } else if (purpose === '2') { // 貨物
      printValue = getCarTaxWeight();
    } else if (purpose === '' || purpose === '0') {
      printValue = '';
    } else {
      printValue = "該当するデータがありません。";
    }

    const outputDiv = document.getElementById("output");
    if (outputDiv) outputDiv.textContent = printValue;

    const carTaxEl = document.getElementById('cars-car-tax');
    if (carTaxEl) {
      const isNumeric = /^[0-9,]+$/.test(printValue);
      if (printValue === '') {
        carTaxEl.value = '';
      } else if (isNumeric) {
        carTaxEl.value = printValue;
      } else {
        carTaxEl.value = '';
      }
    }

    return printValue;
  }

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('cars-form');
    if (!form) return;

    // maker -> model
    const makerEl = document.getElementById('cars-maker');
    const modelEl = document.getElementById('cars-model');

    const modelsByMaker = (window.KCore && window.KCore.Cars && window.KCore.Cars.modelsByMaker)
      ? window.KCore.Cars.modelsByMaker
      : null;

    function rebuildModels(maker, selected) {
      if (!modelEl) return;

      while (modelEl.firstChild) modelEl.removeChild(modelEl.firstChild);

      let models = null;
      if (modelsByMaker && maker && maker !== '0' && modelsByMaker[maker]) {
        models = modelsByMaker[maker];
      }

      if (!models) {
        const opt = document.createElement('option');
        opt.value = '0';
        opt.textContent = '-- Please select --';
        modelEl.appendChild(opt);
        modelEl.value = '0';
        return;
      }

      Object.keys(models).forEach((code) => {
        const label = models[code];
        const opt = document.createElement('option');
        opt.value = String(code);
        opt.textContent = String(label);
        modelEl.appendChild(opt);
      });

      const want = String(selected ?? '0');
      const exists = Array.from(modelEl.options).some(o => String(o.value) === want);
      modelEl.value = exists ? want : '0';
    }

    if (makerEl && modelEl) {
      rebuildModels(makerEl.value, modelEl.value);
      makerEl.addEventListener('change', function () {
        rebuildModels(makerEl.value, '0');
      });
    }

    // 年/月/日（registration / purchase）
    const regYear = form.querySelector('select[name="registration_year"]');
    const regMonth = form.querySelector('select[name="registration_month"]');
    const regDay = form.querySelector('select[name="registration_day"]');

    const purYear = form.querySelector('select[name="purchase_year"]');
    const purMonth = form.querySelector('select[name="purchase_month"]');
    const purDay = form.querySelector('select[name="purchase_day"]');

    function applyYmdGuard(yearEl, monthEl, dayEl) {
      if (!yearEl || !monthEl || !dayEl) return;

      const apply = () => {
        const y = toInt(yearEl.value);
        const m = toInt(monthEl.value);
        rebuildDaySelect(dayEl, y, m);
      };

      yearEl.addEventListener('change', apply);
      monthEl.addEventListener('change', apply);
      apply();
    }

    applyYmdGuard(regYear, regMonth, regDay);
    applyYmdGuard(purYear, purMonth, purDay);

    // 数値（blurでカンマ）
    const numEls = Array.from(form.querySelectorAll('.js-num-comma'));
    numEls.forEach((el) => {
      el.addEventListener('blur', function () {
        const next = formatComma(el.value);
        if (el.value !== next) el.value = next;
      });
    });

    // ★自動車税：イベント配線
    const purposeEl = document.getElementById('car_purpose');
    const dispEl = document.getElementById('displacement');
    const weightEl = document.getElementById('vehicle_weight');
    const typeEl = document.getElementById('type_of_car');

    if (typeEl) typeEl.addEventListener('change', getCarTax);
    if (purposeEl) purposeEl.addEventListener('change', getCarTax);
    if (dispEl) {
      dispEl.addEventListener('blur', getCarTax);
      dispEl.addEventListener('change', getCarTax);
    }
    if (weightEl) {
      weightEl.addEventListener('blur', getCarTax);
      weightEl.addEventListener('change', getCarTax);
    }

    // 初期反映（編集画面でも計算表示）
    getCarTax();

    // submit時にカンマ除去（サーバ側は除去しているが、念のため）
    form.addEventListener('submit', function () {
      numEls.forEach((el) => {
        el.value = stripComma(el.value);
      });
    });
  });
})();
