/**
 * /public/js/carLeaseProfit.js
 * ============================================================
 * 役割:
 * - 全車両リース収支集計ページ
 * - summary API 取得
 * - DataTables 初期化
 * - フィルタ連動
 */

(function () {
  'use strict';

  function initWhenReady(maxTries, intervalMs) {
    let tries = 0;

    const tick = function () {
      tries++;

      const tableEl = document.getElementById('car-lease-profit-table');
      const formEl  = document.getElementById('car-lease-profit-filter');

      if (!tableEl) {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        return;
      }

      if (typeof window.DataTable === 'undefined') {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        return;
      }

      if (tableEl.dataset.dtInited === '1') return;
      tableEl.dataset.dtInited = '1';

      const apiUrl     = tableEl.dataset.apiUrl || '/api/car_leases/profit/datatable';
      const summaryUrl = tableEl.dataset.summaryUrl || '/api/car_leases/profit/summary';
      const langUrl    = tableEl.dataset.langUrl || '';

      const applyBtn = document.getElementById('car-lease-profit-apply');
      const resetBtn = document.getElementById('car-lease-profit-reset');

      const errorBox = document.getElementById('car-lease-profit-error');

      function getFilters() {
        if (!formEl) {
          return { f_from:'', f_to:'', f_mode:'all' };
        }

        const fd = new FormData(formEl);

        return {
          f_from : fd.get('f_from') || '',
          f_to   : fd.get('f_to') || '',
          f_mode : fd.get('f_mode') || 'all'
        };
      }

      function collectFilters(d) {
        const f = getFilters();
        d.f_from = f.f_from;
        d.f_to   = f.f_to;
        d.f_mode = f.f_mode;
      }

      function formatYen(v) {
        const n = Number(v || 0);
        return n.toLocaleString('ja-JP') + ' 円';
      }

      function updateSummary(data) {
        if (!data || !data.summary) return;

        const s = data.summary;

        const revenueEl  = document.getElementById('profit-summary-revenue');
        const expenseEl  = document.getElementById('profit-summary-expense');
        const profitEl   = document.getElementById('profit-summary-profit');

        const carsEl     = document.getElementById('profit-summary-cars');
        const revCarsEl  = document.getElementById('profit-summary-revenue-cars');
        const defCarsEl  = document.getElementById('profit-summary-deficit-cars');

        const purchaseEl = document.getElementById('profit-breakdown-purchase');
        const recycleEl  = document.getElementById('profit-breakdown-recycling');
        const pCostsEl   = document.getElementById('profit-breakdown-purchase-costs');
        const taxEl      = document.getElementById('profit-breakdown-tax');
        const insEl      = document.getElementById('profit-breakdown-insurance');

        if (revenueEl) revenueEl.textContent = formatYen(s.revenue_total);
        if (expenseEl) expenseEl.textContent = formatYen(s.expense_total);
        if (profitEl)  profitEl.textContent  = formatYen(s.profit_total);

        if (carsEl)    carsEl.textContent    = String(s.car_count || 0) + ' 台';
        if (revCarsEl) revCarsEl.textContent = String(s.revenue_car_count || 0) + ' 台';
        if (defCarsEl) defCarsEl.textContent = String(s.deficit_car_count || 0) + ' 台';

        if (purchaseEl) purchaseEl.textContent = formatYen(s.purchase_total);
        if (recycleEl)  recycleEl.textContent  = formatYen(s.recycling_total);
        if (pCostsEl)   pCostsEl.textContent   = formatYen(s.purchase_costs_total);
        if (taxEl)      taxEl.textContent      = formatYen(s.tax_total);
        if (insEl)      insEl.textContent      = formatYen(s.insurance_total);
      }

      async function loadSummary() {
        const filters = getFilters();

        const qs = new URLSearchParams();
        if (filters.f_from) qs.set('f_from', filters.f_from);
        if (filters.f_to)   qs.set('f_to', filters.f_to);
        if (filters.f_mode) qs.set('f_mode', filters.f_mode);

        const url = summaryUrl + '?' + qs.toString();

        try {
          const res = await fetch(url, { credentials:'same-origin' });
          const json = await res.json();

          if (!res.ok) {
            throw new Error(json.message || 'summary error');
          }

          updateSummary(json);

          if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
          }

        } catch (err) {
          console.error('[carLeaseProfit] summary error:', err);

          if (errorBox) {
            errorBox.textContent = '集計取得に失敗しました。';
            errorBox.classList.remove('d-none');
          }
        }
      }

      const columns = [
        { title:'No', orderable:false },
        { title:'車両番号' },
        { title:'メーカー' },
        { title:'車種' },
        { title:'状態' },
        { title:'購入日' },
        { title:'経過月数' },
        { title:'集計対象月数' },
        { title:'売上' },
        { title:'経費' },
        { title:'利益' },
        { title:'警告', orderable:false }
      ];

      const dt = new window.DataTable(tableEl, {
        serverSide: true,
        processing: true,
        searching: false,

        pageLength: 200,
        lengthMenu: [50, 100, 200],

        ajax: {
          url: apiUrl,
          dataSrc: 'data',
          data: collectFilters,
          error: function (xhr, _status, err) {
            console.error('[carLeaseProfit] datatable ajax error:', err);
            try { console.error(xhr.responseText); } catch (_) {}
          }
        },

        order: [],
        columns: columns,
        language: langUrl ? { url: langUrl } : undefined
      });

      async function reloadAll() {
        await loadSummary();
        dt.ajax.reload();
      }

      if (applyBtn) {
        applyBtn.addEventListener('click', function () {
          reloadAll();
        });
      }

      if (resetBtn) {
        resetBtn.addEventListener('click', function () {
          if (formEl) formEl.reset();
          reloadAll();
        });
      }

      // 初期ロード
      loadSummary();
    };

    tick();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initWhenReady(50, 100);
  });

})();