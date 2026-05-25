/**
 * /public/js/users.js
 * ============================================================
 * 役割:
 * - ユーザー一覧（DataTables）の初期化
 * - フィルタフォームの値をサーバへ渡す
 * - 絞り込み/リセットの操作を提供
 *
 * 前提:
 * - layout.php で DataTables / Bootstrap / jQuery が先に読み込まれていること
 * - views/users/index.php に以下の要素が存在すること
 *   - #users-table（data-users-url, data-lang-url, data-can-edit）
 *   - #users-filter, #users-filter-apply, #users-filter-reset
 */

(function () {
  'use strict';

  // ------------------------------------------------------------
  // 追加（今回）:
  // - window.DataTable が未ロードのタイミングでも確実に初期化できるように
  //   "待ってから初期化" を入れる（沈黙して終わらない）
  // ------------------------------------------------------------
  function initWhenReady(maxTries, intervalMs) {
    let tries = 0;

    const tick = function () {
      tries++;

      const tableEl = document.getElementById('users-table');
      const formEl = document.getElementById('users-filter');
      const applyBtn = document.getElementById('users-filter-apply');
      const resetBtn = document.getElementById('users-filter-reset');

      // usersページ以外でも読み込まれて落ちないように
      if (!tableEl) {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        return; // usersページではない
      }

      // DataTables が未ロードなら待つ
      if (typeof window.DataTable === 'undefined') {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        console.warn('[users] window.DataTable is undefined. DataTables script not loaded?');
        return;
      }

      // 二重初期化防止
      if (tableEl.dataset.dtInited === '1') return;
      tableEl.dataset.dtInited = '1';

      const usersUrl = tableEl.dataset.usersUrl || '/api/users';
      const langUrl = tableEl.dataset.langUrl || '';
      const canEdit = (tableEl.dataset.canEdit || '0') === '1';

      function collectFilters(d) {
        if (!formEl) return;

        const fd = new FormData(formEl);

        // APIに送るGETパラメータ（UserController@datatable と一致させる）
        d.f_id            = fd.get('f_id') || '';
        d.f_employee_code = fd.get('f_employee_code') || '';
        d.f_role_code     = fd.get('f_role_code') || '';
        d.f_name          = fd.get('f_name') || '';
        d.f_email         = fd.get('f_email') || '';
        d.f_contract      = fd.get('f_contract') || '';
        d.f_uncontract    = fd.get('f_uncontract') || '';
      }

      // 権限に応じて列定義を切り替える
      // - canEdit=false の場合は「操作」列自体を出さない
      const columns = [
        { title: 'ID' },
        { title: '社員コード' },
        { title: '権限' },
        { title: '氏名' },
        { title: 'ユーザーID' },
        { title: '契約入力権限' },
        { title: '未契約入力権限' },
      ];

      if (canEdit) {
        columns.push({ title: '操作', orderable: false, searchable: false });
      }

      const dt = new window.DataTable(tableEl, {
        serverSide: true,
        processing: true,
        searching: false,        // グローバル検索なし

        // 初期表示200件（Controller側のデフォルトと合わせる）
        pageLength: 200,

        // Controller側は length 最大200 なので合わせる
        lengthMenu: [50, 100, 200],

        ajax: {
          url: usersUrl,
          dataSrc: 'data',
          data: collectFilters,

          // 通信エラー時（最低限の表示）
          error: function (xhr, _status, err) {
            // DataTablesのUIが無反応に見えるのを防ぐ
            console.error('[users datatable] ajax error:', err);
            // デバッグ用：レスポンス本文も出す
            try {
              console.error('[users datatable] response:', xhr && xhr.responseText);
            } catch (_) {}
          }
        },

        // 既定の並び替えはサーバ側で id DESC
        order: [],

        columns: columns,

        language: langUrl ? { url: langUrl } : undefined
      });

      // 絞り込み実行
      if (applyBtn) {
        applyBtn.addEventListener('click', function () {
          dt.ajax.reload();
        });
      }

      // リセット
      if (resetBtn) {
        resetBtn.addEventListener('click', function () {
          if (formEl) formEl.reset();
          dt.ajax.reload();
        });
      }

      // 入力即時反映したい場合（負荷と相談）
      // if (formEl) formEl.addEventListener('change', () => dt.ajax.reload());
    };

    tick();
  }

  document.addEventListener('DOMContentLoaded', function () {
    // 最大5秒待つ（50回×100ms）
    initWhenReady(50, 100);
  });
})();
