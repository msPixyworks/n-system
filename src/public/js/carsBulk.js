(function () {
  'use strict';

  const form = document.getElementById('cars-bulk-form');
  const table = document.getElementById('cars-bulk-table');
  const saveBtn = document.getElementById('cars-bulk-save');
  const messageBox = document.getElementById('cars-bulk-message');

  if (!form || !table || !saveBtn) return;

  let dirty = false;
  let saving = false;

  function normalizeNumberText(value) {
    if (value == null) return '';
    let s = String(value).trim();

    s = s.replace(/[０-９]/g, function (ch) {
      return String.fromCharCode(ch.charCodeAt(0) - 0xFEE0);
    });

    s = s.replace(/,/g, '');
    s = s.replace(/[^\d]/g, '');

    return s;
  }

  function formatNumberText(value) {
    const normalized = normalizeNumberText(value);
    if (normalized === '') return '';
    return Number(normalized).toLocaleString('ja-JP');
  }

  function showMessage(type, text) {
    if (!messageBox) return;

    messageBox.style.display = '';
    messageBox.className = '';

    if (type === 'success') {
      messageBox.className = 'alert alert-success';
    } else if (type === 'error') {
      messageBox.className = 'alert alert-danger';
    } else {
      messageBox.className = 'alert alert-secondary';
    }

    messageBox.textContent = text;
  }

  function markDirty() {
    dirty = true;
  }

  function getRowsPayload() {
    const rows = [];
    const trs = table.querySelectorAll('tbody tr[data-id]');

    trs.forEach(function (tr) {
      const id = tr.getAttribute('data-id');
      const inputs = tr.querySelectorAll('input[data-field]');
      const row = { id: id };

      inputs.forEach(function (input) {
        const field = input.getAttribute('data-field');
        row[field] = normalizeNumberText(input.value);
      });

      rows.push(row);
    });

    return rows;
  }

  table.querySelectorAll('.js-bulk-num').forEach(function (input) {
    input.addEventListener('input', function () {
      markDirty();
    });

    input.addEventListener('blur', function () {
      input.value = formatNumberText(input.value);
    });
  });

  window.addEventListener('beforeunload', function (e) {
    if (!dirty || saving) return;
    e.preventDefault();
    e.returnValue = '';
  });

  saveBtn.addEventListener('click', async function () {
    if (saving) return;

    saving = true;
    saveBtn.disabled = true;
    showMessage('info', '保存中です...');

    try {
      const tokenInput = form.querySelector('input[name="_token"]');

      const payload = {
        _token: tokenInput ? tokenInput.value : '',
        rows: getRowsPayload()
      };

      const res = await fetch(form.getAttribute('action'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      });

      const raw = await res.text();
      let json = null;

      try {
        json = JSON.parse(raw);
      } catch (e) {
        console.error('bulk-update raw response:', raw);
        showMessage(
          'error',
          'JSON以外の応答です: ' + raw.replace(/\s+/g, ' ').slice(0, 300)
        );
        return;
      }

      if (!res.ok || !json.ok) {
        throw new Error(json && json.message ? json.message : '保存に失敗しました。');
      }

      dirty = false;

      table.querySelectorAll('.js-bulk-num').forEach(function (input) {
        input.value = formatNumberText(input.value);
      });

      const updated = Number(json.updated || 0);
      const skipped = Number(json.skipped || 0);
      showMessage('success', `一括更新が完了しました。更新 ${updated} 件 / 変更なし ${skipped} 件`);
    } catch (err) {
      console.error(err);
      showMessage('error', err && err.message ? err.message : '保存に失敗しました。');
    } finally {
      saving = false;
      saveBtn.disabled = false;
    }
  });
})();