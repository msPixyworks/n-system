/**
 * /public/js/officeCustomers.js
 * ============================================================
 * 役割:
 * - 法人顧客一覧（DataTables）の初期化
 * - フィルタフォームの値をサーバへ渡す
 * - 絞り込み/リセット操作
 *
 * 前提:
 * - layout.php で DataTables / Bootstrap / jQuery が先に読み込まれていること
 * - views/office_customers/index.php に以下が存在すること
 *   - #office-customers-table（data-office-customers-url, data-lang-url, data-can-edit）
 *   - #office-customers-filter, #office-customers-filter-apply, #office-customers-filter-reset
 */

(function () {
  'use strict';

  function initWhenReady(maxTries, intervalMs) {
    let tries = 0;

    const tick = function () {
      tries++;

      const tableEl = document.getElementById('office-customers-table');
      const formEl = document.getElementById('office-customers-filter');
      const applyBtn = document.getElementById('office-customers-filter-apply');
      const resetBtn = document.getElementById('office-customers-filter-reset');

      // DOM自体がまだなら待つ
      if (!tableEl) {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        console.warn('[office_customers] table element not found.');
        return;
      }

      // DataTablesが未ロードなら待つ
      if (typeof window.DataTable === 'undefined') {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        console.warn('[office_customers] window.DataTable is undefined. DataTables script not loaded?');
        return;
      }

      // 二重初期化防止
      if (tableEl.dataset.dtInited === '1') return;
      tableEl.dataset.dtInited = '1';

      const apiUrl = tableEl.dataset.officeCustomersUrl || '/api/office_customers';
      const langUrl = tableEl.dataset.langUrl || '';
      const canEdit = (tableEl.dataset.canEdit || '0') === '1';

      function collectFilters(d) {
        if (!formEl) return;

        const fd = new FormData(formEl);

        d.f_id = fd.get('f_id') || '';
        d.f_name = fd.get('f_name') || '';
        d.f_manager = fd.get('f_manager') || '';
        d.f_tel = fd.get('f_tel') || '';
        d.f_zip = fd.get('f_zip') || '';
        d.f_pref_code = fd.get('f_pref_code') || '';
        d.f_background = fd.get('f_background') || '';
        d.f_include_deleted = fd.get('f_include_deleted') ? '1' : '0';
      }

      const columns = [
        { title: 'ID' },
        { title: '会社名' },
        { title: '担当者' },
        { title: 'TEL' },
        { title: '郵便番号' },
        { title: '都道府県' },
        { title: '来社経緯' },
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
          url: apiUrl,
          dataSrc: 'data',
          data: collectFilters,
          error: function (xhr, _status, err) {
            console.error('[office_customers datatable] ajax error:', err);
            // 失敗時にレスポンス本文も出す（デバッグ用）
            try {
              console.error('[office_customers datatable] response:', xhr && xhr.responseText);
            } catch (_) {}
          }
        },

        order: [],
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