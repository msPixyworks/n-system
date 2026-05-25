<?php

/**
 * PersonalCustomerController.php
 * ============================================================
 * 役割:
 * - 個人顧客（personal_customers）の CRUD + DataTables API
 *
 * 方針（OfficeCustomer と同期）:
 * - 権限ガードは Policies::guardView/guardEdit
 * - 未ログイン時は、画面は / にリダイレクト、APIは 401 JSON
 * - DataTables の操作列は編集権限がある人だけ表示
 * - 監査は全カラム（create/update/delete）
 * - destroy は論理削除（deleted_at セット）
 * - update は diff が空なら監査しない（Audit 側の policy にも準拠）
 *
 * 前提:
 * - personal_customers テーブルが存在すること（sql は別ファイルで生成）
 */

class PersonalCustomerController
{
    /**
     * 監査対象フィールド（全カラム）
     * - SQL定義（personal_customers）と同期させる
     */
    private array $fields = [
        // 本人情報
        'name',
        'letter',
        'tel01',
        'zip',
        'pref_code',
        'addr01',
        'addr02',
        'mail01',
        'mail02',
        'birthday_year',
        'birthday_month',
        'birthday_day',
        'license_color',
        'mobile01',
        'emergency_contact',
        'emergency_relationship',
        'emergency_tel',

        // お勤め先
        'office',
        'office_letter',
        'office_zip',
        'office_pref_code',
        'office_addr01',
        'office_addr02',
        'office_tel01',
        'office_tel02',
        'years_of_service',

        // ご来社経緯
        'background',
        'introducer',
        'others',

        // 備考
        'remarks',

        // ご家族（1）
        'first_relationship',
        'first_name',
        'first_letter',
        'first_tel01',
        'first_tel02',
        'first_zip',
        'first_pref_code',
        'first_addr01',
        'first_addr02',
        'first_mail01',
        'first_mail02',
        'first_remarks',

        // ご家族（2）
        'second_relationship',
        'second_name',
        'second_letter',
        'second_tel01',
        'second_tel02',
        'second_zip',
        'second_pref_code',
        'second_addr01',
        'second_addr02',
        'second_mail01',
        'second_mail02',
        'second_remarks',

        // ご家族（3）
        'third_relationship',
        'third_name',
        'third_letter',
        'third_tel01',
        'third_tel02',
        'third_zip',
        'third_pref_code',
        'third_addr01',
        'third_addr02',
        'third_mail01',
        'third_mail02',
        'third_remarks',

        // ご家族（4）
        'fourth_relationship',
        'fourth_name',
        'fourth_letter',
        'fourth_tel01',
        'fourth_tel02',
        'fourth_zip',
        'fourth_pref_code',
        'fourth_addr01',
        'fourth_addr02',
        'fourth_mail01',
        'fourth_mail02',
        'fourth_remarks',

        // ご家族（5）
        'fifth_relationship',
        'fifth_name',
        'fifth_letter',
        'fifth_tel01',
        'fifth_tel02',
        'fifth_zip',
        'fifth_pref_code',
        'fifth_addr01',
        'fifth_addr02',
        'fifth_mail01',
        'fifth_mail02',
        'fifth_remarks',

        // 運用
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ============================================================
    // Guards
    // ============================================================
    private function guardView(): array
    {
        $u = Auth::user();
        if (!$u) {
            if (Response::isApi()) {
                Response::fail('UNAUTHORIZED', 'Unauthorized', 401);
            }
            Response::redirect('/');
        }

        Policies::guardView($u, 'personal_customers');
        return $u;
    }

    private function guardEdit(): array
    {
        $u = $this->guardView();
        Policies::guardEdit($u, 'personal_customers');
        return $u;
    }

    // ============================================================
    // Pages
    // ============================================================
    public function index(): void
    {
        $me = $this->guardView();
        Response::view('personal_customers/index', ['title' => '個人顧客一覧', 'me' => $me]);
    }

    public function create(): void
    {
        $me = $this->guardEdit();
        $cfg = require __DIR__ . '/../config.php';

        Response::view('personal_customers/form', [
            'title'  => '個人顧客登録',
            'cfg'    => $cfg,
            'item'   => null,
            'errors' => [],
            'old'    => [],
            'me'     => $me,
        ]);
    }

    public function show($id): void
    {
        $me = $this->guardView();

        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM personal_customers WHERE id = :i LIMIT 1");
        $st->execute([':i' => (int)$id]);
        $item = $st->fetch();

        if (!$item) {
            Response::notFound();
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('personal_customers/show', [
            'title' => '個人顧客詳細',
            'cfg'   => $cfg,
            'item'  => $item,
            'me'    => $me,
        ]);
    }

    public function edit($id): void
    {
        $me = $this->guardEdit();

        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM personal_customers WHERE id = :i LIMIT 1");
        $st->execute([':i' => (int)$id]);
        $item = $st->fetch();

        if (!$item) {
            Response::notFound();
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('personal_customers/form', [
            'title'  => '個人顧客編集',
            'cfg'    => $cfg,
            'item'   => $item,
            'errors' => [],
            'old'    => [],
            'me'     => $me,
        ]);
    }

    // ============================================================
    // DataTables API
    // ============================================================
    public function datatable(): void
    {
        $me = $this->guardView();

        try {
            $pdo = Db::pdo();
            $cfg = require __DIR__ . '/../config.php';

            $draw  = (int)($_GET['draw'] ?? 0);
            $start = max(0, (int)($_GET['start'] ?? 0));

            $len = isset($_GET['length']) ? (int)($_GET['length']) : 200;
            $len = max(1, min(200, $len));

            // フィルタ（グローバル検索なし）
            $f_id              = trim((string)($_GET['f_id'] ?? ''));
            $f_name            = trim((string)($_GET['f_name'] ?? ''));
            $f_tel01           = trim((string)($_GET['f_tel01'] ?? ''));
            $f_zip             = trim((string)($_GET['f_zip'] ?? ''));
            $f_pref_code       = trim((string)($_GET['f_pref_code'] ?? ''));
            $f_background      = trim((string)($_GET['f_background'] ?? ''));
            $f_include_deleted = trim((string)($_GET['f_include_deleted'] ?? ''));

            $where  = [];
            $params = [];

            if ($f_id !== '') {
                $where[] = "id = :fid";
                $params[':fid'] = (int)$f_id;
            }
            if ($f_name !== '') {
                $where[] = "name LIKE :fnm";
                $params[':fnm'] = "%{$f_name}%";
            }
            if ($f_tel01 !== '') {
                $where[] = "tel01 LIKE :ftl";
                $params[':ftl'] = "%{$f_tel01}%";
            }
            if ($f_zip !== '') {
                $where[] = "zip LIKE :fzip";
                $params[':fzip'] = "%{$f_zip}%";
            }
            if ($f_pref_code !== '' && ctype_digit($f_pref_code)) {
                $pc = (int)$f_pref_code;
                if ($pc >= 1 && $pc <= 47) {
                    $where[] = "pref_code = :fpc";
                    $params[':fpc'] = $pc;
                }
            }
            if ($f_background !== '' && ctype_digit($f_background)) {
                $bg = (int)$f_background;
                if (in_array($bg, [0, 1, 2, 3], true)) {
                    $where[] = "background = :fbg";
                    $params[':fbg'] = $bg;
                }
            }

            // 論理削除除外（デフォルト）
            $includeDeleted = ($f_include_deleted === '1');
            if (!$includeDeleted) {
                $where[] = "deleted_at IS NULL";
            }

            $wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

            // 並び替え
            $colMap = [
                0 => 'id',
                1 => 'name',
                2 => 'tel01',
                3 => 'zip',
                4 => 'pref_code',
                5 => 'background',
            ];

            $orderByParts = [];
            if (isset($_GET['order']) && is_array($_GET['order'])) {
                $count = 0;
                foreach ($_GET['order'] as $ord) {
                    if ($count >= 3) {
                        break;
                    }
                    $colIdx = isset($ord['column']) ? (int)$ord['column'] : null;
                    $dir    = strtolower((string)($ord['dir'] ?? ''));
                    if (!isset($colMap[$colIdx])) {
                        continue;
                    }
                    $dir = ($dir === 'asc' ? 'ASC' : ($dir === 'desc' ? 'DESC' : 'ASC'));
                    $orderByParts[] = $colMap[$colIdx] . ' ' . $dir;
                    $count++;
                }
            }
            if (!$orderByParts) {
                $orderByParts[] = 'id DESC';
            }
            $orderBySql = ' ORDER BY ' . implode(', ', $orderByParts);

            // 件数
            $total = (int)$pdo->query("SELECT COUNT(*) FROM personal_customers")->fetchColumn();

            $stc = $pdo->prepare("SELECT COUNT(*) FROM personal_customers{$wsql}");
            foreach ($params as $k => $v) {
                $stc->bindValue($k, $v);
            }
            $stc->execute();
            $filtered = (int)$stc->fetchColumn();

            // データ取得
            $limitClause = " LIMIT {$start}, {$len}";
            $sql = "SELECT id, name, tel01, zip, pref_code, addr01, background, deleted_at
                    FROM personal_customers{$wsql}{$orderBySql}{$limitClause}";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->execute();
            $rows = $st->fetchAll() ?: [];

            $canEdit = Policies::canEditPersonalCustomers($me);

            $pref = $cfg['prefectures'] ?? [];
            $bgLabels = $cfg['options']['personal_customers']['backgrounds'] ?? [
                0 => '（未選択）', 1 => 'HP', 2 => 'チラシ', 3 => '営業'
            ];

            $data = array_map(function ($r) use ($canEdit, $pref, $bgLabels) {
                $id = (int)$r['id'];

                $prefText = '-';
                $pc = (int)($r['pref_code'] ?? 0);
                if ($pc && isset($pref[$pc])) {
                    $prefText = (string)$pref[$pc];
                }

                $bg = (int)($r['background'] ?? 0);
                $bgText = isset($bgLabels[$bg]) ? (string)$bgLabels[$bg] : '-';

                $row = [
                    $id,
                    htmlspecialchars((string)($r['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($r['tel01'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($r['zip'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($prefText, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($bgText, ENT_QUOTES, 'UTF-8'),
                ];

                if ($canEdit) {
                    $menuItems = [];
                    $menuItems[] = '<li><a class="dropdown-item" href="/personal_customers/' . $id . '/edit">編集</a></li>';
                    $menuItems[] = '<li><a class="dropdown-item" href="/car_leases/lessees/personal/' . $id . '">リース確認</a></li>';

                    $buttons  = '<div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap text-nowrap">';
                    $buttons .= '<a class="btn btn-sm btn-outline-primary text-nowrap" href="/personal_customers/' . $id . '">詳細</a>';

                    if (!empty($menuItems)) {
                        $buttons .= '
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle text-nowrap" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    その他
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">'
                                    . implode('', $menuItems) .
                                '</ul>
                            </div>';
                    }

                    $buttons .= '</div>';

                    $row[] = $buttons;
                }

                return $row;
            }, $rows);

            Response::json([
                'draw'            => $draw,
                'recordsTotal'    => $total,
                'recordsFiltered' => $filtered,
                'data'            => $data,
            ]);
        } catch (Throwable $e) {
            Response::json([
                'error'   => 'SERVER_ERROR',
                'message' => 'Unexpected error in datatable endpoint.',
            ], 500);
        }
    }

    // ============================================================
    // Validation
    // ============================================================
    private function validate(array $in, ?int $id = null): array
    {
        $v = new Validation();
        $cfg = require __DIR__ . '/../config.php';

        $trim = fn($k) => trim((string)($in[$k] ?? ''));

        $onlyDigits = function (string $s): string {
            $s = str_replace(['-', 'ー', '−', '―', '‐', '–', '—', ' '], '', $s);
            $s = preg_replace('/[^\d]/', '', $s);
            return (string)$s;
        };

        $limitLen = function (string $s, int $max): bool {
            return mb_strlen($s, 'UTF-8') <= $max;
        };

        // ------------------------------------------------------------
        // 本人情報
        // ------------------------------------------------------------
        $in['name'] = $trim('name');
        if ($in['name'] === '') {
            $v->add('name', '氏名は必須です。');
        }
        if ($in['name'] !== '' && (mb_strlen($in['name'], 'UTF-8') < 1 || mb_strlen($in['name'], 'UTF-8') > 100)) {
            $v->add('name', '氏名は1〜100文字で入力してください。');
        }

        $in['letter'] = Validation::kana($trim('letter'));
        if ($in['letter'] === '') {
            $v->add('letter', '氏名フリガナは必須です。');
        }
        if ($in['letter'] !== '' && (mb_strlen($in['letter'], 'UTF-8') < 1 || mb_strlen($in['letter'], 'UTF-8') > 100)) {
            $v->add('letter', '氏名フリガナは1〜100文字で入力してください。');
        }
        if ($in['letter'] !== '' && !preg_match('/^[ァ-ヶー　 ]+$/u', $in['letter'])) {
            $v->add('letter', '氏名フリガナは全角カタカナ（長音・空白可）で入力してください。');
        }

        $in['tel01'] = $onlyDigits($trim('tel01'));
        if ($in['tel01'] !== '' && (strlen($in['tel01']) < 1 || strlen($in['tel01']) > 15)) {
            $v->add('tel01', '電話番号1は15桁以下で入力してください。');
        }

        $in['zip'] = $onlyDigits($trim('zip'));
        if ($in['zip'] !== '' && !preg_match('/^\d{7}$/', $in['zip'])) {
            $v->add('zip', '郵便番号は7桁の数字で入力してください。');
        }

        $in['pref_code'] = (int)$trim('pref_code');
        if ($in['pref_code'] !== 0 && ($in['pref_code'] < 1 || $in['pref_code'] > 47)) {
            $v->add('pref_code', '県名を選択してください。');
        }

        $in['addr01'] = $trim('addr01');
        if ($in['addr01'] !== '' && !$limitLen($in['addr01'], 255)) {
            $v->add('addr01', '住所1は255文字以内で入力してください。');
        }

        $in['addr02'] = $trim('addr02');
        if ($in['addr02'] !== '' && !$limitLen($in['addr02'], 255)) {
            $v->add('addr02', '住所2は255文字以内で入力してください。');
        }

        $in['mail01'] = $trim('mail01');
        $in['mail01'] = ($in['mail01'] !== '') ? mb_strtolower($in['mail01'], 'UTF-8') : '';
        if ($in['mail01'] !== '' && (!Validation::email($in['mail01']) || mb_strlen($in['mail01'], 'UTF-8') > 191)) {
            $v->add('mail01', 'メールアドレス1は正しい形式で191文字以内で入力してください。');
        }

        $in['mail02'] = $trim('mail02');
        $in['mail02'] = ($in['mail02'] !== '') ? mb_strtolower($in['mail02'], 'UTF-8') : '';
        if ($in['mail02'] !== '' && (!Validation::email($in['mail02']) || mb_strlen($in['mail02'], 'UTF-8') > 191)) {
            $v->add('mail02', 'メールアドレス2は正しい形式で191文字以内で入力してください。');
        }
        if ($in['mail01'] !== '' && $in['mail02'] !== '' && $in['mail01'] === $in['mail02']) {
            $v->add('mail02', 'メールアドレス2はメールアドレス1と同じ値にできません。');
        }

        // 誕生日（年/月/日）
        $in['birthday_year']  = (int)$trim('birthday_year');
        $in['birthday_month'] = (int)$trim('birthday_month');
        $in['birthday_day']   = (int)$trim('birthday_day');

        $hasAnyBirthday = ($in['birthday_year'] || $in['birthday_month'] || $in['birthday_day']);
        if ($hasAnyBirthday && !($in['birthday_year'] && $in['birthday_month'] && $in['birthday_day'])) {
            $v->add('birthday', '誕生日は年・月・日をすべて選択してください。');
        }
        if ($in['birthday_year'] && $in['birthday_month'] && $in['birthday_day']) {
            if (!checkdate($in['birthday_month'], $in['birthday_day'], $in['birthday_year'])) {
                $v->add('birthday', '存在しない日付です。');
            }
        }

        $in['license_color'] = (int)$trim('license_color');
        if (!in_array($in['license_color'], [0, 1, 2, 3], true)) {
            $v->add('license_color', '免許証の色が不正です。');
        }

        $in['mobile01'] = $onlyDigits($trim('mobile01'));
        if ($in['mobile01'] !== '' && (strlen($in['mobile01']) < 1 || strlen($in['mobile01']) > 15)) {
            $v->add('mobile01', '携帯は15桁以下で入力してください。');
        }

        $in['emergency_contact'] = $trim('emergency_contact');
        if ($in['emergency_contact'] !== '' && !$limitLen($in['emergency_contact'], 100)) {
            $v->add('emergency_contact', '緊急連絡先名は100文字以内で入力してください。');
        }

        $in['emergency_relationship'] = $trim('emergency_relationship');
        if ($in['emergency_relationship'] !== '' && !$limitLen($in['emergency_relationship'], 50)) {
            $v->add('emergency_relationship', '緊急連絡先の続柄は50文字以内で入力してください。');
        }

        $in['emergency_tel'] = $onlyDigits($trim('emergency_tel'));
        if ($in['emergency_tel'] !== '' && (strlen($in['emergency_tel']) < 1 || strlen($in['emergency_tel']) > 15)) {
            $v->add('emergency_tel', '緊急連絡先の電話番号は15桁以下で入力してください。');
        }

        // ------------------------------------------------------------
        // お勤め先情報（すべて任意、入力がある場合は安全側チェック）
        // ------------------------------------------------------------
        $in['office'] = $trim('office');
        if ($in['office'] !== '' && !$limitLen($in['office'], 200)) {
            $v->add('office', '会社名は200文字以内で入力してください。');
        }

        $in['office_letter'] = Validation::kana($trim('office_letter'));
        if ($in['office_letter'] !== '' && (!$limitLen($in['office_letter'], 200) || !preg_match('/^[ァ-ヶー　・（）() ]+$/u', $in['office_letter']))) {
            $v->add('office_letter', '会社名フリガナは全角カタカナで入力してください。');
        }

        $in['office_zip'] = $onlyDigits($trim('office_zip'));
        $in['office_pref_code'] = (int)$trim('office_pref_code');
        $in['office_addr01'] = $trim('office_addr01');
        $in['office_addr02'] = $trim('office_addr02');

        $hasOfficeZip = ($in['office_zip'] !== '');
        $hasAnyOffice = $hasOfficeZip || $in['office_pref_code'] !== 0 || $in['office_addr01'] !== '' || $in['office_addr02'] !== '';

        if ($hasAnyOffice) {
            if (!$hasOfficeZip || !preg_match('/^\d{7}$/', $in['office_zip'])) {
                $v->add('office_zip', '勤務先 郵便番号は7桁の数字で入力してください。');
            }
            if ($in['office_pref_code'] < 1 || $in['office_pref_code'] > 47) {
                $v->add('office_pref_code', '勤務先 県名を選択してください。');
            }
            if ($in['office_addr01'] === '') {
                $v->add('office_addr01', '勤務先 住所1は必須です。');
            }
            if ($in['office_addr01'] !== '' && !$limitLen($in['office_addr01'], 255)) {
                $v->add('office_addr01', '勤務先 住所1は255文字以内で入力してください。');
            }
            if ($in['office_addr02'] !== '' && !$limitLen($in['office_addr02'], 255)) {
                $v->add('office_addr02', '勤務先 住所2は255文字以内で入力してください。');
            }
        } else {
            $in['office_zip'] = '';
            $in['office_pref_code'] = 0;
            $in['office_addr01'] = '';
            $in['office_addr02'] = '';
        }

        $in['office_tel01'] = $onlyDigits($trim('office_tel01'));
        if ($in['office_tel01'] !== '' && (strlen($in['office_tel01']) < 1 || strlen($in['office_tel01']) > 15)) {
            $v->add('office_tel01', '勤務先 電話番号1は15桁以下で入力してください。');
        }

        $in['office_tel02'] = $onlyDigits($trim('office_tel02'));
        if ($in['office_tel02'] !== '' && (strlen($in['office_tel02']) < 1 || strlen($in['office_tel02']) > 15)) {
            $v->add('office_tel02', '勤務先 電話番号2は15桁以下で入力してください。');
        }

        $yos = $trim('years_of_service');
        $in['years_of_service'] = $yos;
        if ($yos !== '') {
            $yosNum = (int)preg_replace('/[^\d]/', '', $yos);
            if ((string)$yosNum !== preg_replace('/[^\d]/', '', $yos) || $yosNum < 0 || $yosNum > 99) {
                $v->add('years_of_service', '勤続年数は0〜99の整数で入力してください。');
            }
            $in['years_of_service'] = (string)$yosNum;
        }

        // ------------------------------------------------------------
        // ご来社経緯（任意: 0/1/2/3）
        // ------------------------------------------------------------
        $in['background'] = (int)$trim('background');
        if (!in_array($in['background'], [0, 1, 2, 3], true)) {
            $v->add('background', 'ご来社経緯が不正です。');
        }

        $in['introducer'] = $trim('introducer');
        if ($in['introducer'] !== '' && !$limitLen($in['introducer'], 100)) {
            $v->add('introducer', 'ご紹介者は100文字以内で入力してください。');
        }

        $in['others'] = $trim('others');
        if ($in['others'] !== '' && !$limitLen($in['others'], 200)) {
            $v->add('others', 'その他は200文字以内で入力してください。');
        }

        $in['remarks'] = (string)($in['remarks'] ?? '');
        if ($in['remarks'] !== '' && mb_strlen($in['remarks'], 'UTF-8') > 10000) {
            $v->add('remarks', '備考は10000文字以内で入力してください。');
        }

        // ------------------------------------------------------------
        // ご家族（1〜5）: ブロック入力開始で条件付き必須
        // ------------------------------------------------------------
        $familyBlocks = [
            'first',
            'second',
            'third',
            'fourth',
            'fifth',
        ];

        foreach ($familyBlocks as $pfx) {
            $relKey = $pfx . '_relationship';
            $nameKey = $pfx . '_name';
            $kanaKey = $pfx . '_letter';
            $tel1Key = $pfx . '_tel01';
            $tel2Key = $pfx . '_tel02';
            $zipKey  = $pfx . '_zip';
            $prefKey = $pfx . '_pref_code';
            $a1Key   = $pfx . '_addr01';
            $a2Key   = $pfx . '_addr02';
            $m1Key   = $pfx . '_mail01';
            $m2Key   = $pfx . '_mail02';
            $rmKey   = $pfx . '_remarks';

            $in[$relKey] = $trim($relKey);
            $in[$nameKey] = $trim($nameKey);
            $in[$kanaKey] = Validation::kana($trim($kanaKey));

            $in[$tel1Key] = $onlyDigits($trim($tel1Key));
            $in[$tel2Key] = $onlyDigits($trim($tel2Key));

            $in[$zipKey] = $onlyDigits($trim($zipKey));
            $in[$prefKey] = (int)$trim($prefKey);

            $in[$a1Key] = $trim($a1Key);
            $in[$a2Key] = $trim($a2Key);

            $in[$m1Key] = $trim($m1Key);
            $in[$m1Key] = ($in[$m1Key] !== '') ? mb_strtolower($in[$m1Key], 'UTF-8') : '';

            $in[$m2Key] = $trim($m2Key);
            $in[$m2Key] = ($in[$m2Key] !== '') ? mb_strtolower($in[$m2Key], 'UTF-8') : '';

            $in[$rmKey] = (string)($in[$rmKey] ?? '');

            $started = (
                $in[$relKey] !== '' ||
                $in[$nameKey] !== '' ||
                $in[$kanaKey] !== '' ||
                $in[$tel1Key] !== '' ||
                $in[$tel2Key] !== '' ||
                $in[$zipKey] !== '' ||
                $in[$prefKey] !== 0 ||
                $in[$a1Key] !== '' ||
                $in[$a2Key] !== '' ||
                $in[$m1Key] !== '' ||
                $in[$m2Key] !== '' ||
                $in[$rmKey] !== ''
            );

            if ($started) {
                if ($in[$relKey] === '' || !$limitLen($in[$relKey], 50)) {
                    $v->add($relKey, '続柄は必須（50文字以内）です。');
                }
                if ($in[$nameKey] === '' || !$limitLen($in[$nameKey], 100)) {
                    $v->add($nameKey, '氏名は必須（100文字以内）です。');
                }

                if ($in[$kanaKey] !== '' && (!$limitLen($in[$kanaKey], 100) || !preg_match('/^[ァ-ヶー　・（）() ]+$/u', $in[$kanaKey]))) {
                    $v->add($kanaKey, '氏名フリガナは全角カタカナで入力してください。');
                }

                if ($in[$tel1Key] !== '' && (strlen($in[$tel1Key]) < 1 || strlen($in[$tel1Key]) > 15)) {
                    $v->add($tel1Key, '電話番号1は15桁以下で入力してください。');
                }
                if ($in[$tel2Key] !== '' && (strlen($in[$tel2Key]) < 1 || strlen($in[$tel2Key]) > 15)) {
                    $v->add($tel2Key, '電話番号2は15桁以下で入力してください。');
                }

                if ($in[$zipKey] !== '' && !preg_match('/^\d{7}$/', $in[$zipKey])) {
                    $v->add($zipKey, '郵便番号は7桁の数字で入力してください。');
                }
                if ($in[$prefKey] !== 0 && ($in[$prefKey] < 1 || $in[$prefKey] > 47)) {
                    $v->add($prefKey, '県名を選択してください。');
                }
                if ($in[$a1Key] !== '' && !$limitLen($in[$a1Key], 255)) {
                    $v->add($a1Key, '住所1は255文字以内で入力してください。');
                }
                if ($in[$a2Key] !== '' && !$limitLen($in[$a2Key], 255)) {
                    $v->add($a2Key, '住所2は255文字以内で入力してください。');
                }

                if ($in[$m1Key] !== '' && (!Validation::email($in[$m1Key]) || mb_strlen($in[$m1Key], 'UTF-8') > 191)) {
                    $v->add($m1Key, 'メールアドレス1は正しい形式で191文字以内で入力してください。');
                }
                if ($in[$m2Key] !== '' && (!Validation::email($in[$m2Key]) || mb_strlen($in[$m2Key], 'UTF-8') > 191)) {
                    $v->add($m2Key, 'メールアドレス2は正しい形式で191文字以内で入力してください。');
                }

                if ($in[$rmKey] !== '' && mb_strlen($in[$rmKey], 'UTF-8') > 10000) {
                    $v->add($rmKey, '備考は10000文字以内で入力してください。');
                }
            } else {
                $in[$relKey] = '';
                $in[$nameKey] = '';
                $in[$kanaKey] = '';
                $in[$tel1Key] = '';
                $in[$tel2Key] = '';
                $in[$zipKey]  = '';
                $in[$prefKey] = 0;
                $in[$a1Key]   = '';
                $in[$a2Key]   = '';
                $in[$m1Key]   = '';
                $in[$m2Key]   = '';
                $in[$rmKey]   = '';
            }
        }

        if (!$v->ok()) {
            return ['ok' => false, 'errors' => $v->errors, 'in' => $in];
        }

        // 保存データ（NULL化）
        $save = [];
        foreach ($this->fields as $f) {
            if (in_array($f, ['created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $val = $in[$f] ?? null;

            if (in_array($f, ['pref_code','office_pref_code','first_pref_code','second_pref_code','third_pref_code','fourth_pref_code','fifth_pref_code'], true)) {
                $val = (int)$val;
                $save[$f] = ($val >= 1 && $val <= 47) ? $val : null;
                continue;
            }

            if (in_array($f, ['birthday_year','birthday_month','birthday_day','license_color','background'], true)) {
                $val = (int)$val;
                $save[$f] = ($val === 0) ? null : $val;
                continue;
            }

            if ($val === '') {
                $val = null;
            }
            $save[$f] = $val;
        }

        return ['ok' => true, 'data' => $save, 'in' => $in];
    }

    // ============================================================
    // Create
    // ============================================================
    public function store(): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $res = $this->validate($_POST, null);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('personal_customers/form', [
                'title'  => '個人顧客登録',
                'cfg'    => $cfg,
                'item'   => null,
                'errors' => $res['errors'],
                'old'    => $res['in'],
                'me'     => $me,
            ]);
            return;
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            $cols = array_keys($res['data']);
            $sql = "INSERT INTO personal_customers (" . implode(',', $cols) . ")
                    VALUES (" . implode(',', array_map(fn($c) => ":$c", $cols)) . ")";
            $st = $pdo->prepare($sql);

            $bind = [];
            foreach ($cols as $c) {
                $bind[":$c"] = $res['data'][$c];
            }
            $st->execute($bind);

            $id = (int)$pdo->lastInsertId();
            $pdo->commit();

            $st2 = $pdo->prepare("SELECT * FROM personal_customers WHERE id = :i LIMIT 1");
            $st2->execute([':i' => $id]);
            $new = $st2->fetch() ?: [];

            $diff = Audit::diff([], $new, $this->fields);
            Audit::log('personal_customers', 'PersonalCustomer', $id, 'create', (int)$me['id'], $diff);

            Response::redirect('/personal_customers/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();

            Response::view('personal_customers/form', [
                'title'  => '個人顧客登録',
                'cfg'    => $cfg,
                'item'   => null,
                'errors' => ['__global' => ['保存に失敗しました。']],
                'old'    => $res['in'] ?? $_POST,
                'me'     => $me,
            ]);
        }
    }

    // ============================================================
    // Update
    // ============================================================
    public function update($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $id = (int)$id;
        $pdo = Db::pdo();

        $st = $pdo->prepare("SELECT * FROM personal_customers WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        $old = $st->fetch();
        if (!$old) {
            Response::notFound();
        }

        $res = $this->validate($_POST, $id);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('personal_customers/form', [
                'title'  => '個人顧客編集',
                'cfg'    => $cfg,
                'item'   => $old,
                'errors' => $res['errors'],
                'old'    => $res['in'],
                'me'     => $me,
            ]);
            return;
        }

        $data = $res['data'];
        $cols = array_keys($data);
        $sets = implode(',', array_map(fn($c) => "$c=:$c", $cols));
        $data['id'] = $id;

        $pdo->beginTransaction();

        try {
            $st2 = $pdo->prepare("UPDATE personal_customers SET {$sets} WHERE id = :id");

            foreach ($data as $k => $v) {
                $type = match (true) {
                    is_int($v)  => PDO::PARAM_INT,
                    is_null($v) => PDO::PARAM_NULL,
                    default     => PDO::PARAM_STR,
                };
                $st2->bindValue(':' . $k, $v, $type);
            }

            $st2->execute();
            $pdo->commit();

            $st3 = $pdo->prepare("SELECT * FROM personal_customers WHERE id = :i LIMIT 1");
            $st3->execute([':i' => $id]);
            $new = $st3->fetch() ?: [];

            $diff = Audit::diff($old, $new, $this->fields);
            if (!empty($diff)) {
                Audit::log('personal_customers', 'PersonalCustomer', $id, 'update', (int)$me['id'], $diff);
            }

            Response::redirect('/personal_customers/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();

            Response::view('personal_customers/form', [
                'title'  => '個人顧客編集',
                'cfg'    => $cfg,
                'item'   => $old,
                'errors' => ['__global' => ['更新に失敗しました。']],
                'old'    => $_POST,
                'me'     => $me,
            ]);
        }
    }

    // ============================================================
    // Logical delete
    // ============================================================
    public function destroy($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $id = (int)$id;
        $pdo = Db::pdo();

        $st = $pdo->prepare("SELECT * FROM personal_customers WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        $old = $st->fetch();
        if (!$old) {
            Response::notFound();
        }

        if (!empty($old['deleted_at'])) {
            Response::redirect('/personal_customers/' . $id);
        }

        $pdo->beginTransaction();
        try {
            $now = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s');

            $st2 = $pdo->prepare("UPDATE personal_customers SET deleted_at = :d WHERE id = :i");
            $st2->execute([':d' => $now, ':i' => $id]);

            $pdo->commit();

            $st3 = $pdo->prepare("SELECT * FROM personal_customers WHERE id = :i LIMIT 1");
            $st3->execute([':i' => $id]);
            $new = $st3->fetch() ?: array_merge($old, ['deleted_at' => $now]);

            $diff = Audit::diff($old, $new, $this->fields);
            Audit::log('personal_customers', 'PersonalCustomer', $id, 'delete', (int)$me['id'], $diff);

            Response::redirect('/personal_customers');
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::fail('SERVER_ERROR', '削除に失敗しました。', 500);
        }
    }
}