(function () {
  'use strict';

  /**
   * ============================================================
   * /public/js/components/lesseePicker.js
   * ============================================================
   * 役割:
   * - lessee picker モーダルの共通制御
   * - resolve API による表示名補完
   * - 各フォームへの反映
   *
   * 既存用途:
   * - car_leases/form.php
   *   - hidden: lessee_type / lessee_id
   *   - display: lessee_name_display
   *
   * 今回追加:
   * - 車両の代車先選択
   *   - hidden: partner_type / partner_id
   *   - display: partner_name_display
   * - 車両の販売先選択
   *   - hidden: customer_type / customer_id
   *   - display: customer_name_display
   *
   * 方針:
   * - ボタンの data-lessee-target-* 属性があればそれを優先
   * - 無ければ従来どおり lessee_type / lessee_id / lessee_name_display を使う
   * - 既存画面を壊さない後方互換を維持する
   */

  async function resolveLessee(type, id) {
    const t = String(type || '').trim();
    const i = String(id || '').replace(/[^0-9]/g, '');
    if (!t || !i) return null;

    try {
      const url = '/api/lessees/resolve?type=' + encodeURIComponent(t) + '&id=' + encodeURIComponent(i);
      const res = await fetch(url, { cache: 'no-store' });
      const json = await res.json().catch(() => null);
      if (!json || json.ok !== true || !json.data) return null;
      return json.data;
    } catch (_e) {
      return null;
    }
  }

  function ensureHidden(form, name, defaultValue) {
    let el = form.querySelector('input[name="' + name + '"]');
    if (!el) {
      el = document.createElement('input');
      el.type = 'hidden';
      el.name = name;
      el.value = String(defaultValue ?? '');
      form.appendChild(el);
    }
    return el;
  }

  function setTargetDisplay(form, displayId, name, kana) {
    if (!form || !displayId) return;
    const el = form.querySelector('#' + displayId);
    if (!el) return;

    const n = String(name || '');
    const k = String(kana || '');

    // 既存 lessee 表示は「名前（カナ）」、新規 partner/customer も同じ揃えで表示
    el.value = (n && k) ? (n + '（' + k + '）') : n;
  }

  function getDefaultTargets() {
    return {
      typeName: 'lessee_type',
      idName: 'lessee_id',
      displayId: 'lessee_name_display'
    };
  }

  /**
   * トリガーボタンから反映先を決定
   * - data 属性があればそれを優先
   * - 無ければ従来の lessee_* を使う
   */
  function getTargetsFromTrigger(btn) {
    const defaults = getDefaultTargets();
    if (!btn) return defaults;

    const typeName = btn.dataset.lesseeTargetType || defaults.typeName;
    const idName = btn.dataset.lesseeTargetId || defaults.idName;
    const displayId = btn.dataset.lesseeTargetDisplay || defaults.displayId;

    return { typeName, idName, displayId };
  }

  /**
   * フォームごとに現在のターゲット定義を保持
   * - lessee:changed 発火時にどの hidden / display を見るか使う
   */
  function setFormTargets(form, targets) {
    if (!form || !targets) return;
    form.dataset.lesseeTargetType = String(targets.typeName || 'lessee_type');
    form.dataset.lesseeTargetId = String(targets.idName || 'lessee_id');
    form.dataset.lesseeTargetDisplay = String(targets.displayId || 'lessee_name_display');
  }

  function getFormTargets(form) {
    const defaults = getDefaultTargets();
    if (!form) return defaults;

    return {
      typeName: form.dataset.lesseeTargetType || defaults.typeName,
      idName: form.dataset.lesseeTargetId || defaults.idName,
      displayId: form.dataset.lesseeTargetDisplay || defaults.displayId
    };
  }

  function initResolveWiring(form) {
    if (!form || form.dataset.lesseeResolveInited === '1') return;
    form.dataset.lesseeResolveInited = '1';

    // 初期ターゲットは既定値。後でボタン押下時に上書きされる。
    setFormTargets(form, getDefaultTargets());

    let timer = null;
    const schedule = () => {
      if (timer) clearTimeout(timer);
      timer = setTimeout(async () => {
        const targets = getFormTargets(form);
        const typeEl = ensureHidden(form, targets.typeName, '');
        const idEl   = ensureHidden(form, targets.idName, '');

        const t = typeEl.value;
        const i = idEl.value;

        if (!t || !i) {
          setTargetDisplay(form, targets.displayId, '', '');
          return;
        }

        const data = await resolveLessee(t, i);
        if (!data) {
          setTargetDisplay(form, targets.displayId, '', '');
          return;
        }
        setTargetDisplay(form, targets.displayId, data.name, data.kana);
      }, 200);
    };

    // hidden は通常 change が発火しにくいので、外から lessee:changed を投げる
    form.addEventListener('lessee:changed', schedule);

    // 初期表示
    schedule();
  }

  function initWhenReady(maxTries, intervalMs) {
    let tries = 0;

    const tick = function () {
      tries++;

      // resolve はフォームがあるなら常時配線
      Array.from(document.querySelectorAll('form')).forEach(initResolveWiring);

      const modalEl = document.getElementById('lesseePickerModal');
      const tableEl = document.getElementById('lessee-picker-table');

      if (!modalEl || !tableEl) {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        return;
      }

      if (typeof window.DataTable === 'undefined') {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        return;
      }
      if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
        if (tries < maxTries) return setTimeout(tick, intervalMs);
        return;
      }

      if (tableEl.dataset.dtInited === '1') return;
      tableEl.dataset.dtInited = '1';

      const apiUrl  = tableEl.dataset.apiUrl || '/api/lessees';
      const langUrl = tableEl.dataset.langUrl || '';

      const filterEl = document.getElementById('lessee-picker-filter');
      const applyBtn = document.getElementById('lessee-picker-apply');
      const resetBtn = document.getElementById('lessee-picker-reset');
      const qInput = filterEl ? filterEl.querySelector('input[name="f_q"]') : null;

      let activeForm = null;
      let activeTargets = getDefaultTargets();

      function collectFilters(d) {
        if (!filterEl) return;
        const fd = new FormData(filterEl);
        d.f_type = fd.get('f_type') || '';
        d.f_q    = fd.get('f_q') || '';
      }

      const columns = [
        { title: '種別' },
        { title: 'ID' },
        { title: '名前' },
        { title: 'フリガナ' },
        { title: 'TEL' },
        { title: '住所' },
        { title: '操作', orderable: false, searchable: false }
      ];

      const dt = new window.DataTable(tableEl, {
        serverSide: true,
        processing: true,
        searching: false,
        pageLength: 50,
        lengthMenu: [25, 50, 100, 200],
        ajax: { url: apiUrl, dataSrc: 'data', data: collectFilters },
        order: [],
        columns: columns,
        language: langUrl ? { url: langUrl } : undefined
      });

      if (applyBtn) applyBtn.addEventListener('click', () => dt.ajax.reload());
      if (resetBtn) resetBtn.addEventListener('click', () => {
        if (filterEl) filterEl.reset();
        dt.ajax.reload();
      });

      if (qInput) qInput.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          dt.ajax.reload();
        }
      });

      const bsModal = new window.bootstrap.Modal(modalEl);
      modalEl.addEventListener('shown.bs.modal', function () {
        if (qInput) {
          try {
            qInput.focus();
            qInput.select();
          } catch (_) {}
        }
      });

      document.addEventListener('click', function (ev) {
        const btn = ev.target && ev.target.closest ? ev.target.closest('.js-open-lessee-picker') : null;
        if (!btn) return;

        activeForm = btn.closest('form') || document.getElementById('car-lease-form') || null;
        if (!activeForm) return;

        initResolveWiring(activeForm);

        // どの hidden / display に反映するかをトリガーボタンから決定
        activeTargets = getTargetsFromTrigger(btn);
        setFormTargets(activeForm, activeTargets);

        dt.ajax.reload();
        bsModal.show();
      });

      tableEl.addEventListener('click', function (ev) {
        const pickBtn = ev.target && ev.target.closest ? ev.target.closest('.js-lessee-pick') : null;
        if (!pickBtn) return;

        const type = pickBtn.dataset.lesseeType || '';
        const id   = pickBtn.dataset.lesseeId || '';
        const name = pickBtn.dataset.lesseeName || '';
        const kana = pickBtn.dataset.lesseeKana || '';

        const form = activeForm || document.getElementById('car-lease-form');
        if (!form) return;

        const targets = activeTargets || getFormTargets(form);

        const typeEl = ensureHidden(form, targets.typeName, '');
        const idEl   = ensureHidden(form, targets.idName, '');

        typeEl.value = String(type);
        idEl.value   = String(id);

        setTargetDisplay(form, targets.displayId, name, kana);

        // resolve wiring に通知
        form.dispatchEvent(new Event('lessee:changed'));

        bsModal.hide();
      });

      /**
       * クリアボタン共通対応
       * - 各部品側にも個別スクリプトを置いているが、
       *   ここでも data-lessee-target-* を読んで安全にクリアできるようにする
       * - 二重に動いても同じ値を入れるだけなので実害はない
       */
      document.addEventListener('click', function (ev) {
        const clearBtn = ev.target && ev.target.closest ? ev.target.closest('.js-clear-lessee') : null;
        if (!clearBtn) return;

        const form = clearBtn.closest('form');
        if (!form) return;

        const targets = getTargetsFromTrigger(clearBtn);

        const typeEl = form.querySelector('input[name="' + targets.typeName + '"]');
        const idEl   = form.querySelector('input[name="' + targets.idName + '"]');
        const dispEl = form.querySelector('#' + targets.displayId);

        if (typeEl) typeEl.value = '';
        if (idEl) idEl.value = '';
        if (dispEl) dispEl.value = '';

        setFormTargets(form, targets);
        form.dispatchEvent(new Event('lessee:changed'));
      });
    };

    tick();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initWhenReady(50, 100);
  });
})();