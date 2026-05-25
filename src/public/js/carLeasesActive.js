(function () {
  'use strict';

  function initWhenReady(maxTries, intervalMs) {
    let tries = 0;

    const tick = function () {
      tries++;

      const tableEl = document.getElementById('car-leases-active-table');
      const formEl  = document.getElementById('lease-filter');

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

      const apiUrl    = tableEl.dataset.apiUrl || '/api/car_leases/active';
      const exportUrl = tableEl.dataset.exportUrl || '/car_leases/export';
      const langUrl   = tableEl.dataset.langUrl || '';

      const applyBtn = document.getElementById('lease-filter-apply');
      const resetBtn = document.getElementById('lease-filter-reset');
      const exportBtn = document.getElementById('car-leases-export-btn');

      const countActiveEl = document.getElementById('lease-count-active');
      const countScheduledEl = document.getElementById('lease-count-scheduled');

      function ensureAlertBox() {
        let el = document.getElementById('lease-start-alerts');
        if (el) return el;

        const pageHeader = document.querySelector('.car-leases-active .k-page__header');
        if (!pageHeader || !pageHeader.parentNode) return null;

        el = document.createElement('div');
        el.id = 'lease-start-alerts';
        el.className = 'alert alert-warning mb-3';
        el.style.display = 'none';

        if (pageHeader.nextSibling) {
          pageHeader.parentNode.insertBefore(el, pageHeader.nextSibling);
        } else {
          pageHeader.parentNode.appendChild(el);
        }

        return el;
      }

      function escapeHtml(value) {
        return String(value ?? '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function renderAlerts(alerts) {
        const alertBox = ensureAlertBox();
        if (!alertBox) return;

        if (!Array.isArray(alerts) || alerts.length === 0) {
          alertBox.style.display = 'none';
          alertBox.innerHTML = '';
          return;
        }

        const items = alerts.map(function (a) {
          const mgmt = a && a.management_number ? escapeHtml(a.management_number) : '';
          const veh  = a && a.vehicle_number ? escapeHtml(a.vehicle_number) : '';
          const msg  = a && a.message ? escapeHtml(a.message) : '前回のリースが未終了です。';

          let carText = '';
          if (mgmt && veh) {
            carText = mgmt + '（' + veh + '）';
          } else if (mgmt) {
            carText = mgmt;
          } else if (veh) {
            carText = veh;
          } else if (a && a.car_id) {
            carText = '車両ID:' + escapeHtml(a.car_id);
          } else {
            carText = '対象車両';
          }

          return '<li>' + carText + '：' + msg + '</li>';
        }).join('');

        alertBox.innerHTML =
          '<strong>リース開始できない契約があります。</strong>' +
          '<ul class="mb-0 mt-2">' + items + '</ul>';
        alertBox.style.display = '';
      }

      function getFilterValues() {
        if (!formEl) {
          return {
            f_status: 'both',
            f_from: '',
            f_to: ''
          };
        }

        const fd = new FormData(formEl);

        return {
          f_status: fd.get('f_status') || 'both',
          f_from: fd.get('f_from') || '',
          f_to: fd.get('f_to') || ''
        };
      }

      function collectFilters(d) {
        const filters = getFilterValues();
        d.f_status = filters.f_status;
        d.f_from   = filters.f_from;
        d.f_to     = filters.f_to;
      }

      function buildExportUrl() {
        const filters = getFilterValues();
        const qs = new URLSearchParams();

        if (filters.f_status) qs.set('f_status', filters.f_status);
        if (filters.f_from) qs.set('f_from', filters.f_from);
        if (filters.f_to) qs.set('f_to', filters.f_to);

        const query = qs.toString();
        return query ? (exportUrl + '?' + query) : exportUrl;
      }

      const columns = [
        { title: 'No', orderable: false },
        { title: '車種' },
        { title: '車両番号' },
        { title: 'リース先' },
        { title: '期間' },
        { title: '状態' },
        { title: '月額（円）' },
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
              if (meta) {
                if (countActiveEl) countActiveEl.textContent = String(meta.active_count ?? 0);
                if (countScheduledEl) countScheduledEl.textContent = String(meta.scheduled_count ?? 0);
                renderAlerts(meta.alerts || []);
              } else {
                renderAlerts([]);
              }
            } catch (_) {
              renderAlerts([]);
            }
            return json.data;
          },
          data: collectFilters,
          error: function (xhr, _status, err) {
            console.error('[carLeasesActive] ajax error:', err);
            try { console.error(xhr.responseText); } catch (_) {}
            renderAlerts([]);
          }
        },

        order: [],
        columns: columns,
        language: langUrl ? { url: langUrl } : undefined
      });

      if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
          e.preventDefault();
          window.location.href = buildExportUrl();
        });
      }

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