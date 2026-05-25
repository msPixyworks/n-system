(function () {
  'use strict';

  function initWhenReady(maxTries, intervalMs) {
    let tries = 0;

    const tick = function () {
      tries++;

      const tableEl = document.getElementById('car-lease-health-table');
      const formEl  = document.getElementById('lease-health-filter');

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

      const apiUrl  = tableEl.dataset.apiUrl || '/api/car_leases/health';
      const langUrl = tableEl.dataset.langUrl || '';

      const applyBtn = document.getElementById('lease-health-filter-apply');
      const resetBtn = document.getElementById('lease-health-filter-reset');

      const totalCountEl = document.getElementById('lease-health-total-count');

      function getFilterValues() {
        if (!formEl) {
          return {
            f_issue_type: '',
            f_car_id: '',
            f_management_number: '',
            f_lease_id: ''
          };
        }

        const fd = new FormData(formEl);

        return {
          f_issue_type: fd.get('f_issue_type') || '',
          f_car_id: fd.get('f_car_id') || '',
          f_management_number: fd.get('f_management_number') || '',
          f_lease_id: fd.get('f_lease_id') || ''
        };
      }

      function collectFilters(d) {
        const filters = getFilterValues();
        d.f_issue_type = filters.f_issue_type;
        d.f_car_id = filters.f_car_id;
        d.f_management_number = filters.f_management_number;
        d.f_lease_id = filters.f_lease_id;
      }

      const columns = [
        { title: 'No', orderable: false },
        { title: '異常種別' },
        { title: '重要度' },
        { title: '車両' },
        { title: 'リースID' },
        { title: '内容' },
        { title: '操作', orderable: false, searchable: false }
      ];

      const dt = new window.DataTable(tableEl, {
        serverSide: true,
        processing: true,
        searching: false,

        pageLength: 200,
        lengthMenu: [50, 100, 200],

        ajax: {
          url: apiUrl,
          dataSrc: function (json) {

            try {
              const meta = json && json.meta ? json.meta : null;

              if (meta && totalCountEl) {
                totalCountEl.textContent = String(meta.total_issues ?? 0);
              }

            } catch (_) {}

            return json.data;
          },
          data: collectFilters,
          error: function (xhr, _status, err) {
            console.error('[carLeaseHealth] ajax error:', err);
            try { console.error(xhr.responseText); } catch (_) {}
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
    initWhenReady(50, 100);
  });

})();