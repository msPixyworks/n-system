/**
 * /public/js/auditLog.js
 * ============================================================
 * 役割:
 * - 監査ログ一覧（DataTables）の初期化
 * - 絞り込みフォームの値をサーバへ渡す
 * - 「差分」ボタン押下で details API を取得し、モーダル表示
 *
 * 追加（要望反映）:
 * - login_blocked を赤系バッジ表示（action_key を見て装飾）
 * - IP をクリックすると同IPで絞り込み（フォームに ip をセットして reload）
 *
 * 前提:
 * - AuditController の datatable() が
 *   - action を <span class="audit-action" data-action="...">LABEL</span> で返す
 *   - ip を <span class="audit-ip" data-ip="...">IP</span> で返す
 *   - フィルタとして GET ip を受け取る
 * - audit/index.php のフォーム（#audit-search）に name="ip" の input があること
 *   （textでもhiddenでもOK）
 *
 * 既存前提:
 * - layout.php で DataTables / Bootstrap / jQuery が先に読み込まれていること
 * - audit/index.php に以下の要素が存在すること
 *   - #audit-table（data-audit-url, data-details-url-template, data-lang-url）
 *   - #audit-search, #audit-filter-apply, #audit-filter-reset
 *   - #diffModal, #diffAlert, #diffBody
 */

(function () {
  'use strict';

  function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[m]));
  }

  function getBoolParam(fd, name) {
    return fd.get(name) ? '1' : '0';
  }

  function setFormValue(form, name, value) {
    const el = form.querySelector(`[name="${CSS.escape(name)}"]`);
    if (!el) return false;
    el.value = value;
    return true;
  }

  function getFormValue(form, name) {
    const el = form.querySelector(`[name="${CSS.escape(name)}"]`);
    return el ? String(el.value ?? '') : '';
  }

  function decorateActionBadges(tableEl) {
    // actionセルはサーバ側で <span class="audit-action" data-action="...">LABEL</span> を返す想定
    const actions = tableEl.querySelectorAll('.audit-action[data-action]');
    actions.forEach((el) => {
      const key = (el.getAttribute('data-action') || '').trim();

      // 既に badge 化済みなら二重適用しない
      if (el.classList.contains('badge')) return;

      // Bootstrap badge 化（赤系は login_blocked）
      el.classList.add('badge', 'rounded-pill');

      if (key === 'login_blocked') {
        el.classList.add('text-bg-danger');
      } else if (key === 'login_failed') {
        el.classList.add('text-bg-warning');
      } else if (key === 'delete') {
        el.classList.add('text-bg-secondary');
      } else if (key === 'update') {
        el.classList.add('text-bg-info');
      } else if (key === 'create') {
        el.classList.add('text-bg-primary');
      } else if (key === 'login') {
        el.classList.add('text-bg-success');
      } else if (key === 'logout') {
        el.classList.add('text-bg-light');
        // light は文字が薄くなりがちなので調整
        el.classList.add('text-dark', 'border');
      } else {
        // その他は控えめ
        el.classList.add('text-bg-light', 'text-dark', 'border');
      }
    });
  }

  function decorateIpLinks(tableEl) {
    // ipセルはサーバ側で <span class="audit-ip" data-ip="...">IP</span> を返す想定
    const ips = tableEl.querySelectorAll('.audit-ip[data-ip]');
    ips.forEach((el) => {
      // 既にリンク化済みなら二重適用しない
      if (el.tagName.toLowerCase() === 'a') return;

      const ip = (el.getAttribute('data-ip') || '').trim();
      if (!ip) return;

      // span -> a に差し替え
      const a = document.createElement('a');
      a.href = '#';
      a.className = 'audit-ip-link';
      a.setAttribute('data-ip', ip);
      a.textContent = ip;

      // 見た目：リンクっぽくしすぎない（監査の邪魔にならない程度）
      a.style.textDecoration = 'underline';
      a.style.textUnderlineOffset = '2px';

      el.replaceWith(a);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    const tableEl = document.getElementById('audit-table');
    const form = document.getElementById('audit-search');
    const applyBtn = document.getElementById('audit-filter-apply');
    const resetBtn = document.getElementById('audit-filter-reset');

    if (!tableEl || !form || !applyBtn || !resetBtn) return;

    const auditUrl = tableEl.dataset.auditUrl || '/api/audit';
    const detailsUrlTemplate = tableEl.dataset.detailsUrlTemplate || '/api/audit/{id}/details';
    const langUrl = tableEl.dataset.langUrl || '';

    // フォームに ip がない場合、クリック絞り込みが動かないため安全に noop
    const hasIpField = !!form.querySelector('[name="ip"]');

    function ajaxData(d) {
      const fd = new FormData(form);

      d.module      = fd.get('module') || '';
      d.action      = fd.get('action') || '';
      d.date_from   = fd.get('date_from') || '';
      d.date_to     = fd.get('date_to') || '';
      d.f_new_value = fd.get('f_new_value') || '';
      d.only_changed   = getBoolParam(fd, 'only_changed');
      d.exclude_create = getBoolParam(fd, 'exclude_create');

      // 追加: IPフィルタ
      if (hasIpField) {
        d.ip = fd.get('ip') || '';
      }
    }

    /**
     * 一覧表示列（AuditController の datatable() と同期）
     *  0: ID
     *  1: モジュール
     *  2: エンティティID
     *  3: アクション（HTML: <span class="audit-action" ...>）
     *  4: ユーザーID
     *  5: IP（HTML: <span class="audit-ip" ...> -> JSでリンク化）
     *  6: 日時
     *  7: 詳細（差分ボタン）
     */
    // eslint-disable-next-line no-undef
    const t = new DataTable(tableEl, {
      serverSide: true,
      processing: true,
      searching: false,
      ajax: {
        url: auditUrl,
        dataSrc: 'data',
        data: ajaxData,
      },
      order: [],
      columns: [
        { title: 'ID' },
        { title: 'モジュール' },
        { title: 'エンティティID' },
        { title: 'アクション' }, // サーバがHTML返す想定
        { title: 'ユーザーID' },
        { title: 'IP' },         // サーバがHTML返す想定
        { title: '日時' },
        { title: '詳細', orderable: false, searchable: false },
      ],
      language: langUrl ? { url: langUrl } : undefined,
    });

    // 描画後（ページング/検索/reload含む）に装飾を適用
    tableEl.addEventListener('draw.dt', function () {
      decorateActionBadges(tableEl);
      decorateIpLinks(tableEl);
    });

    // 初回も適用（DataTables内部タイミング用の保険）
    setTimeout(function () {
      decorateActionBadges(tableEl);
      decorateIpLinks(tableEl);
    }, 0);

    applyBtn.addEventListener('click', function () {
      t.ajax.reload();
    });

    resetBtn.addEventListener('click', function () {
      form.reset();
      // リセット時はIPフィルタも空に
      if (hasIpField) setFormValue(form, 'ip', '');
      t.ajax.reload();
    });

    // IPクリック -> 同IPで絞り込み
    tableEl.addEventListener('click', function (e) {
      const a = e.target.closest('.audit-ip-link');
      if (!a) return;
      e.preventDefault();

      if (!hasIpField) return;

      const ip = (a.getAttribute('data-ip') || '').trim();
      if (!ip) return;

      const current = getFormValue(form, 'ip');
      if (current === ip) return;

      setFormValue(form, 'ip', ip);
      t.ajax.reload();
    });

    // 差分モーダル
    const diffBody = document.getElementById('diffBody');
    const diffAlert = document.getElementById('diffAlert');
    const diffModalEl = document.getElementById('diffModal');

    tableEl.addEventListener('click', async function (e) {
      const btn = e.target.closest('.btn-diff');
      if (!btn) return;

      const id = btn.dataset.id;
      if (!id) return;

      if (diffAlert) diffAlert.classList.add('d-none');
      if (diffBody) diffBody.innerHTML = '<tr><td colspan="3" class="text-muted">読み込み中…</td></tr>';

      // eslint-disable-next-line no-undef
      const modal = new bootstrap.Modal(diffModalEl);
      modal.show();

      try {
        const url = detailsUrlTemplate.replace('{id}', encodeURIComponent(id));
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const json = await res.json();
        const rows = (json && Array.isArray(json.data)) ? json.data : [];

        if (!rows.length) {
          if (diffBody) diffBody.innerHTML = '';
          if (diffAlert) diffAlert.classList.remove('d-none');
          return;
        }

        if (diffBody) {
          diffBody.innerHTML = rows.map((r) => `
            <tr>
              <td><code>${escHtml(r.field)}</code></td>
              <td>${escHtml(r.old)}</td>
              <td>${escHtml(r.new)}</td>
            </tr>
          `).join('');
        }
      } catch (err) {
        if (diffBody) {
          diffBody.innerHTML = `<tr><td colspan="3" class="text-danger">差分の取得に失敗しました。${escHtml(String(err))}</td></tr>`;
        }
      }
    });
  });
})();
