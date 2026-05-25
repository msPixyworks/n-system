/**
 * /public/js/cars.js
 * ============================================================
 * 役割:
 * - 車両一覧（DataTables）の初期化
 * - フィルタフォームの値をサーバへ渡す
 * - 絞り込み/リセットの操作
 *
 * 前提:
 * - layout.php で DataTables / Bootstrap / jQuery が先に読み込まれていること
 * - views/cars/index.php に以下の要素が存在すること
 *   - #cars-table（data-cars-url, data-lang-url, data-can-edit）
 *   - #cars-filter, #cars-filter-apply, #cars-filter-reset
 *   - #sum-stock, #sum-leasing, #sum-scheduled, #sum-loaner, #sum-sold, #sum-scrap
 */

(function () {
  'use strict';

  // ------------------------------------------------------------
  // 追加（今回）:
  // - window.DataTable が未ロードのタイミングでも確実に初期化できるように
  //   "待ってから初期化" を入れる（沈黙して終わらない）
  // - サマリーに「在庫」を追加
  // - フィルタから「ID」を削除
  // ------------------------------------------------------------
  function initWhenReady(maxTries, intervalMs) {
    let tries = 0;

    const tick = function () {
      tries++;

      const tableEl = document.getElementById('cars-table');
      const formEl = document.getElementById('cars-filter');
      const applyBtn = document.getElementById('cars-filter-apply');
      const resetBtn = document.getElementById('cars-filter-reset');

      if (!tableEl) {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        return; // carsページではない
      }

      // DataTables が未ロードなら待つ
      if (typeof window.DataTable === 'undefined') {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        console.warn('[cars] window.DataTable is undefined. DataTables script not loaded?');
        return;
      }

      // 二重初期化防止
      if (tableEl.dataset.dtInited === '1') return;
      tableEl.dataset.dtInited = '1';

      const carsUrl = tableEl.dataset.carsUrl || '/api/cars';
      const langUrl = tableEl.dataset.langUrl || '';
      const canEdit = (tableEl.dataset.canEdit || '0') === '1';

      function setTextById(id, value) {
        const el = document.getElementById(id);
        if (el) {
          el.innerText = String(value ?? 0);
        }
      }

      function updateSummary(summary) {
        const s = summary || {};
        setTextById('sum-stock', s.stock || 0);
        setTextById('sum-leasing', s.leasing || 0);
        setTextById('sum-scheduled', s.scheduled || 0);
        setTextById('sum-loaner', s.loaner || 0);
        setTextById('sum-sold', s.sold || 0);
        setTextById('sum-scrap', s.scrap || 0);
      }

      function collectFilters(d) {
        if (!formEl) return;

        const fd = new FormData(formEl);

        d.f_vehicle_number  = fd.get('f_vehicle_number') || '';
        d.f_chassis_number  = fd.get('f_chassis_number') || '';
        d.f_maker           = fd.get('f_maker') || '';
        d.f_car_model       = fd.get('f_car_model') || '';
        d.f_status          = fd.get('f_status') || '';
        d.f_include_deleted = fd.get('f_include_deleted') ? '1' : '0';
      }

      // ------------------------------------------------------------
      // 変更（今回）:
      // - 一覧表示から「ID/管理番号/年式/走行距離」を除外
      // - 代わりに一番左に「No（連番）」を表示
      //   ※ serverSide のため、No はAPI側で start+index+1 を返す
      // ------------------------------------------------------------
      const columns = [
        { title: 'No', orderable: false, searchable: false },
        { title: '車両番号' },
        { title: '車台番号' },
        { title: 'メーカー' },
        { title: '車種' },
      ];

      if (canEdit) {
        columns.push({ title: '操作', orderable: false, searchable: false });
      }

      const dt = new window.DataTable(tableEl, {
        serverSide: true,
        processing: true,
        searching: false,

        pageLength: 200,
        lengthMenu: [50, 100, 200],

        ajax: {
          url: carsUrl,
          data: collectFilters,
          dataSrc: function (json) {
            updateSummary(json && json.summary ? json.summary : {});
            return (json && Array.isArray(json.data)) ? json.data : [];
          },
          error: function (xhr, _status, err) {
            console.error('[cars datatable] ajax error:', err);
            // デバッグ用：レスポンス本文も出す
            try {
              console.error('[cars datatable] response:', xhr && xhr.responseText);
            } catch (_) {}
          }
        },

        order: [], // デフォルトはAPI側（id DESC）に任せる
        columns: columns,
        language: langUrl ? { url: langUrl } : undefined
      });

      if (applyBtn) {
        applyBtn.addEventListener('click', function () {
          dt.ajax.reload();
        });
      }

      if (resetBtn) {
        resetBtn.addEventListener('click', function () {
          if (formEl) formEl.reset();
          dt.ajax.reload();
        });
      }
    };

    tick();
  }

  document.addEventListener('DOMContentLoaded', function () {
    // 最大5秒待つ（50回×100ms）
    initWhenReady(50, 100);
  });
})();