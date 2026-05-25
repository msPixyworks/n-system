/**
 * /public/js/personalCustomers.js
 * ============================================================
 * 役割:
 * - 個人顧客一覧（DataTables）の初期化
 * - フィルタフォームの値をサーバへ渡す
 * - 絞り込み/リセット操作
 *
 * 改修（今回）:
 * - window.DataTable が未ロードのタイミングでも確実に初期化できるように
 *   "待ってから初期化" を入れる（沈黙して終わらない）
 */

(function () {
  'use strict';

  function initWhenReady(maxTries, intervalMs) {
    let tries = 0;

    const tick = function () {
      tries++;

      const tableEl = document.getElementById('personal-customers-table');
      const formEl = document.getElementById('personal-customers-filter');
      const applyBtn = document.getElementById('personal-customers-filter-apply');
      const resetBtn = document.getElementById('personal-customers-filter-reset');

      // DOM自体がまだなら待つ
      if (!tableEl) {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        console.warn('[personal_customers] table element not found.');
        return;
      }

      // DataTablesが未ロードなら待つ（ここが今回の主修正）
      if (typeof window.DataTable === 'undefined') {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        console.warn('[personal_customers] window.DataTable is undefined. DataTables script not loaded?');
        return;
      }

      // 二重初期化防止（DataTables v2 は内部にインスタンスを保持するが、安全側でフラグも持つ）
      if (tableEl.dataset.dtInited === '1') return;
      tableEl.dataset.dtInited = '1';

      const apiUrl = tableEl.dataset.personalCustomersUrl || '/api/personal_customers';
      const langUrl = tableEl.dataset.langUrl || '';
      const canEdit = (tableEl.dataset.canEdit || '0') === '1';

      function collectFilters(d) {
        if (!formEl) return;

        const fd = new FormData(formEl);

        d.f_id = fd.get('f_id') || '';
        d.f_name = fd.get('f_name') || '';
        d.f_tel01 = fd.get('f_tel01') || '';
        d.f_zip = fd.get('f_zip') || '';
        d.f_pref_code = fd.get('f_pref_code') || '';
        d.f_background = fd.get('f_background') || '';
        d.f_include_deleted = fd.get('f_include_deleted') ? '1' : '0';
      }

      const columns = [
        { title: 'ID' },
        { title: '氏名' },
        { title: '電話番号' },
        { title: '郵便番号' },
        { title: '都道府県' },
        { title: 'ご来社経緯' },
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
            console.error('[personal_customers datatable] ajax error:', err);
            // 失敗時にレスポンス本文も出す（デバッグ用）
            try {
              console.error('[personal_customers datatable] response:', xhr && xhr.responseText);
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
