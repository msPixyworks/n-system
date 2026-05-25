/**
 * K-Core 共通フォームヘルパ
 * ============================================================
 * 役割:
 * - 郵便番号 → 都道府県・住所自動補完（zipcloud）
 * - 電話番号系の数字化・ハイフン削除
 *
 * 改修ポイント:
 * - overwrite:'autoOnly' の仕様ズレ修正（「自動補完済 or 空」のときだけ上書き）
 * - zipcloud 連打防止（同一ZIPの重複fetch抑止 + 簡易デバウンス）
 * - 都道府県マスタは window.KCore.PREFS があれば優先（サーバ注入に対応）
 *
 * 追加（今回）:
 * - mb_convert_kana('KV') 相当の“実務用”カナ正規化（name_kana 入力補助）
 *   - JSではPHPと完全一致は難しいため、「業務で困らない同等挙動」を狙う
 *   - ひらがな→カタカナ / 半角カナ→全角カナ / 濁点・半濁点の結合 / 長音の正規化
 *   - 英数字の全角化はしない（PHP側Validation::kanaの意図に合わせる）
 * - メール等の小文字化・trim（blurで正規化）
 *
 * attachZipAutoFill options.overwrite:
 *   - 'always'   : 常に上書き
 *   - 'autoOnly' : 以前に自動補完した or 空のときだけ上書き（デフォルト）
 *   - 'emptyOnly': 空のときだけ上書き
 */

const KCore = window.KCore || {};

KCore.FormHelpers = {
  /**
   * 電話番号フィールド正規化
   * - 数字以外を除去
   * - 最大15桁
   * @param {HTMLElement[]} elements
   */
  normalizePhones(elements) {
    elements.forEach((el) => {
      if (!el) return;

      el.addEventListener('input', () => {
        const digits = (el.value || '').replace(/[^0-9]/g, '').slice(0, 15);
        if (el.value !== digits) el.value = digits;
      });

      // モバイル入力最適化
      el.setAttribute('inputmode', 'numeric');
      el.setAttribute('pattern', '[0-9]*');
      el.setAttribute('maxlength', '15');
    });
  },

  /**
   * mb_convert_kana('KV') 相当の“実務用”カナ正規化（JS版）
   * - ひらがな → カタカナ
   * - 半角カナ → 全角カナ
   * - 濁点/半濁点の結合
   * - 長音（ｰ/ー）を全角へ寄せる
   * - 英数字は触らない（全角化しない）
   *
   * @param {string} s
   * @returns {string}
   */
  normalizeKanaKV(s) {
    const input = String(s ?? '');
    if (input === '') return '';

    let t = input;

    // 0) ひらがな → カタカナ
    //    Unicode: 3041–3096 → 30A1–30F6（+0x60）
    //    ※一般的な「ぁ〜ゖ」範囲を対象にする
    t = t.replace(/[\u3041-\u3096]/g, function (ch) {
      return String.fromCharCode(ch.charCodeAt(0) + 0x60);
    });

    // 1) 半角カナ → 全角カナ
    const halfToFullMap = {
      '｡':'。','｢':'「','｣':'」','､':'、','･':'・',
      'ｦ':'ヲ','ｧ':'ァ','ｨ':'ィ','ｩ':'ゥ','ｪ':'ェ','ｫ':'ォ',
      'ｬ':'ャ','ｭ':'ュ','ｮ':'ョ','ｯ':'ッ','ｰ':'ー',
      'ｱ':'ア','ｲ':'イ','ｳ':'ウ','ｴ':'エ','ｵ':'オ',
      'ｶ':'カ','ｷ':'キ','ｸ':'ク','ｹ':'ケ','ｺ':'コ',
      'ｻ':'サ','ｼ':'シ','ｽ':'ス','ｾ':'セ','ｿ':'ソ',
      'ﾀ':'タ','ﾁ':'チ','ﾂ':'ツ','ﾃ':'テ','ﾄ':'ト',
      'ﾅ':'ナ','ﾆ':'ニ','ﾇ':'ヌ','ﾈ':'ネ','ﾉ':'ノ',
      'ﾊ':'ハ','ﾋ':'ヒ','ﾌ':'フ','ﾍ':'ヘ','ﾎ':'ホ',
      'ﾏ':'マ','ﾐ':'ミ','ﾑ':'ム','ﾒ':'メ','ﾓ':'モ',
      'ﾔ':'ヤ','ﾕ':'ユ','ﾖ':'ヨ',
      'ﾗ':'ラ','ﾘ':'リ','ﾙ':'ル','ﾚ':'レ','ﾛ':'ロ',
      'ﾜ':'ワ','ﾝ':'ン',
      'ﾞ':'゛','ﾟ':'゜'
    };

    let u = '';
    for (const ch of t) {
      u += (halfToFullMap[ch] ?? ch);
    }
    t = u;

    // 2) 濁点/半濁点を結合（例: カ゛ → ガ、ハ゜ → パ）
    const dakutenMap = {
      'カ゛':'ガ','キ゛':'ギ','ク゛':'グ','ケ゛':'ゲ','コ゛':'ゴ',
      'サ゛':'ザ','シ゛':'ジ','ス゛':'ズ','セ゛':'ゼ','ソ゛':'ゾ',
      'タ゛':'ダ','チ゛':'ヂ','ツ゛':'ヅ','テ゛':'デ','ト゛':'ド',
      'ハ゛':'バ','ヒ゛':'ビ','フ゛':'ブ','ヘ゛':'ベ','ホ゛':'ボ',
      'ウ゛':'ヴ',
      'ハ゜':'パ','ヒ゜':'ピ','フ゜':'プ','ヘ゜':'ペ','ホ゜':'ポ'
    };

    for (const k in dakutenMap) {
      // eslint-disable-next-line no-prototype-builtins
      if (!dakutenMap.hasOwnProperty(k)) continue;
      t = t.split(k).join(dakutenMap[k]);
    }

    // 3) 半角の長音が残っている場合は全角へ
    t = t.replace(/ｰ/g, 'ー');

    return t;
  },

  /**
   * カナ入力フィールドに正規化を付与（usersのname_kanaなど）
   *
   * options:
   * - mode: 'KV'（既定）… normalizeKanaKV を使用
   * - trim: 前後空白をtrimするか（既定 true）
   * - debounceMs: 入力をまとめる（既定 0 = 即時）
   *
   * @param {HTMLInputElement|HTMLTextAreaElement|null} el
   * @param {Object} options
   */
  attachKanaNormalize(el, options = {}) {
    if (!el) return;

    const opts = Object.assign({ mode: 'KV', trim: true, debounceMs: 0 }, options);
    let timer = null;

    const apply = () => {
      let v = String(el.value ?? '');
      if (opts.trim) v = v.trim();

      if (opts.mode === 'KV') {
        v = KCore.FormHelpers.normalizeKanaKV(v);
      }

      if (el.value !== v) el.value = v;
    };

    const schedule = () => {
      if (opts.debounceMs > 0) {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, opts.debounceMs);
      } else {
        apply();
      }
    };

    // 入力中とフォーカスアウトで整形（※usersは blur-only を使う運用も可）
    el.addEventListener('input', schedule);
    el.addEventListener('blur', apply);
  },

  /**
   * メール等の小文字化（blurで正規化）
   *
   * 目的:
   * - Auth/DB/検索で email の大小揺れを減らす
   * - “入力中に勝手に変わる”違和感を避けるため blur で適用
   *
   * options:
   * - trim: 前後空白をtrimする（既定 true）
   * - toLower: 小文字化する（既定 true）
   *
   * @param {HTMLInputElement|HTMLTextAreaElement|null} el
   * @param {Object} options
   */
  attachLowercaseNormalize(el, options = {}) {
    if (!el) return;

    const opts = Object.assign({ trim: true, toLower: true }, options);

    const apply = () => {
      let v = String(el.value ?? '');
      if (opts.trim) v = v.trim();
      if (opts.toLower) v = v.toLowerCase();
      if (el.value !== v) el.value = v;
    };

    el.addEventListener('blur', apply);
  },

  /**
   * 郵便番号フィールド → zipcloudで住所補完
   * @param {HTMLInputElement} postalEl
   * @param {HTMLSelectElement|null} prefEl
   * @param {HTMLInputElement|null} addr1El
   * @param {Object} options
   *   - overwrite: 'always' | 'autoOnly' | 'emptyOnly' 既定 'autoOnly'
   *   - debounceMs: 郵便番号入力確定後の待ち（既定 250ms）
   */
  attachZipAutoFill(postalEl, prefEl = null, addr1El = null, options = {}) {
    if (!postalEl) return;

    const opts = Object.assign({ overwrite: 'autoOnly', debounceMs: 250 }, options);

    // 都道府県マスタは可能ならサーバ側から注入したものを優先（ズレ防止）
    const PREFS = Array.isArray(KCore.PREFS) && KCore.PREFS.length >= 48
      ? KCore.PREFS
      : [
          '', '北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県',
          '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
          '新潟県','富山県','石川県','福井県','山梨県','長野県',
          '岐阜県','静岡県','愛知県','三重県',
          '滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県',
          '鳥取県','島根県','岡山県','広島県','山口県',
          '徳島県','香川県','愛媛県','高知県',
          '福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'
        ];

    const prefCodeFromName = (name) => {
      for (let i = 1; i <= 47; i++) if (PREFS[i] === name) return i;
      return 0;
    };

    // 住所1がユーザー操作されたら「手入力扱い」にする（上書き抑止に使う）
    if (addr1El) {
      addr1El.addEventListener('input', () => {
        addr1El.dataset.userEdited = '1';
        addr1El.dataset.autofilled = '0';
      });
    }

    // zipcloud連打防止用
    let lastZipFetched = '';
    let timer = null;

    const fetchZipAndFill = async (zip) => {
      // 同一ZIPは連打しない
      if (zip === lastZipFetched) return;

      try {
        const url = 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + encodeURIComponent(zip);
        const res = await fetch(url, { cache: 'no-store' });
        const json = await res.json();

        if (!json || json.status !== 200 || !Array.isArray(json.results) || json.results.length === 0) return;
        const r = json.results[0];

        // ここまで来たら「このZIPは取得済み」とする
        lastZipFetched = zip;

        // 都道府県（selectが code の場合を想定）
        const code = prefCodeFromName(r.address1);
        if (prefEl && code) {
          prefEl.value = String(code);
          prefEl.dispatchEvent(new Event('change'));
        }

        // 住所1（市区町村 + 町域）
        if (addr1El) {
          const current = (addr1El.value || '').trim();
          const next = (r.address2 || '') + (r.address3 || '');

          const wasAuto = addr1El.dataset.autofilled === '1';
          const userEdited = addr1El.dataset.userEdited === '1';

          let shouldOverwrite = false;
          switch (opts.overwrite) {
            case 'always':
              shouldOverwrite = true;
              break;

            case 'emptyOnly':
              shouldOverwrite = current === '';
              break;

            case 'autoOnly':
            default:
              // ★仕様どおり：自動補完済 or 空のときだけ上書き（手入力済は触らない）
              shouldOverwrite = wasAuto || current === '';
              break;
          }

          // ただし “autoOnlyでも手入力フラグが立ってない” 初期状態で上書きしたい運用があるなら、
          // ここに条件を追加する（例：current==='' 以外でも上書きしたい等）
          if (shouldOverwrite && current !== next) {
            addr1El.value = next;
            addr1El.dataset.autofilled = '1';
            addr1El.dataset.userEdited = '0';
          }
        }
      } catch (e) {
        // 失敗は握る（画面操作を止めない）
        // console.warn('zipcloud fetch error', e);
      }
    };

    // 郵便番号：数字のみ・最大7桁
    const normalizeZip = () => (postalEl.value || '').replace(/[^0-9]/g, '').slice(0, 7);

    const scheduleFetchIfReady = () => {
      const digits = normalizeZip();
      if (postalEl.value !== digits) postalEl.value = digits;

      // 7桁のみ検索
      if (digits.length !== 7) return;

      // デバウンス（短時間の連打をまとめる）
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => fetchZipAndFill(digits), opts.debounceMs);
    };

    postalEl.addEventListener('input', scheduleFetchIfReady);
    postalEl.addEventListener('blur', scheduleFetchIfReady);

    postalEl.setAttribute('inputmode', 'numeric');
    postalEl.setAttribute('pattern', '[0-9]*');
    postalEl.setAttribute('maxlength', '7');
  }
};

// グローバル公開
window.KCore = KCore;
