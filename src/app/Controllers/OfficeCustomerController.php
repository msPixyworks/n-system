<?php

/**
 * OfficeCustomerController.php
 * ============================================================
 * 役割:
 * - 法人顧客（office_customers）の CRUD + DataTables API
 *
 * 方針（K-Core / cars 完全同期）:
 * - 権限ガードは Policies::guardView/guardEdit
 * - 未ログイン時は、画面は / にリダイレクト、APIは 401 JSON（Response::fail）
 * - DataTables の操作列は編集権限がある人だけ表示
 * - 監査は全カラム（create/update/delete）
 * - destroy は論理削除（deleted_at セット）
 * - update は diff が空なら監査しない（ノイズ抑制）
 */

class OfficeCustomerController
{
    /**
     * 監査対象フィールド（全カラム）
     * - SQL定義（office_customers）と同期させる
     */
    private array $fields = [
        'name',
        'company_name_phonetic',

        'representative',
        'representative_letter',

        'manager',
        'manager_letter',

        'department_in_charge',
        'person_in_charge',

        'driver',
        'driver_letter',

        'tel',
        'fax',

        'zip',
        'pref_code',
        'addr01',

        'zip02',
        'pref02_code',
        'addr02',

        'mail01',
        'mail02',

        'purpose',

        'background',
        'introducer',
        'others',

        'remarks',

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

        Policies::guardView($u, 'office_customers');
        return $u;
    }

    private function guardEdit(): array
    {
        $u = $this->guardView();
        Policies::guardEdit($u, 'office_customers');
        return $u;
    }

    // ============================================================
    // Pages
    // ============================================================
    public function index(): void
    {
        $me = $this->guardView();
        Response::view('office_customers/index', ['title' => '法人顧客一覧', 'me' => $me]);
    }

    public function create(): void
    {
        $me = $this->guardEdit();
        $cfg = require __DIR__ . '/../config.php';

        Response::view('office_customers/form', [
            'title'  => '法人顧客登録',
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
        $st = $pdo->prepare("SELECT * FROM office_customers WHERE id = :i LIMIT 1");
        $st->execute([':i' => (int)$id]);
        $item = $st->fetch();

        if (!$item) {
            Response::notFound();
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('office_customers/show', [
            'title' => '法人顧客詳細',
            'cfg'   => $cfg,
            'item'  => $item,
            'me'    => $me,
        ]);
    }

    public function edit($id): void
    {
        $me = $this->guardEdit();

        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM office_customers WHERE id = :i LIMIT 1");
        $st->execute([':i' => (int)$id]);
        $item = $st->fetch();

        if (!$item) {
            Response::notFound();
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('office_customers/form', [
            'title'  => '法人顧客編集',
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
            $f_manager         = trim((string)($_GET['f_manager'] ?? ''));
            $f_tel             = trim((string)($_GET['f_tel'] ?? ''));
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
            if ($f_manager !== '') {
                $where[] = "manager LIKE :fmg";
                $params[':fmg'] = "%{$f_manager}%";
            }
            if ($f_tel !== '') {
                $where[] = "tel LIKE :ftl";
                $params[':ftl'] = "%{$f_tel}%";
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
                2 => 'manager',
                3 => 'tel',
                4 => 'zip',
                5 => 'pref_code',
                6 => 'background',
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
            $total = (int)$pdo->query("SELECT COUNT(*) FROM office_customers")->fetchColumn();

            $stc = $pdo->prepare("SELECT COUNT(*) FROM office_customers{$wsql}");
            foreach ($params as $k => $v) {
                $stc->bindValue($k, $v);
            }
            $stc->execute();
            $filtered = (int)$stc->fetchColumn();

            // データ取得
            $limitClause = " LIMIT {$start}, {$len}";
            $sql = "SELECT id, name, manager, tel, zip, pref_code, addr01, background, deleted_at
                    FROM office_customers{$wsql}{$orderBySql}{$limitClause}";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->execute();
            $rows = $st->fetchAll() ?: [];

            $canEdit = Policies::canEditOfficeCustomers($me);

            $pref = $cfg['prefectures'] ?? [];
            $bgLabels = $cfg['office_customer_backgrounds'] ?? [0 => '不明', 1 => 'HP', 2 => 'チラシ', 3 => '営業'];

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
                    htmlspecialchars((string)($r['manager'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($r['tel'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($r['zip'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($prefText, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($bgText, ENT_QUOTES, 'UTF-8'),
                ];

                if ($canEdit) {
                    $menuItems = [];
                    $menuItems[] = '<li><a class="dropdown-item" href="/office_customers/' . $id . '/edit">編集</a></li>';

                    $buttons  = '<div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap text-nowrap">';
                    $buttons .= '<a class="btn btn-sm btn-outline-primary text-nowrap" href="/office_customers/' . $id . '">詳細</a>';

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

        // --- helper ---
        $trim = fn($k) => trim((string)($in[$k] ?? ''));

        $onlyDigits = function (string $s): string {
            $s = str_replace(['-', 'ー', '−', '―', '‐', '-', '–', '—', ' '], '', $s);
            $s = preg_replace('/[^\d]/', '', $s);
            return (string)$s;
        };

        // --- company ---
        $in['name'] = $trim('name');
        if ($in['name'] === '') {
            $v->add('name', '会社名は必須です。');
        }
        if ($in['name'] !== '' && mb_strlen($in['name'], 'UTF-8') > 200) {
            $v->add('name', '会社名は200文字以内で入力してください。');
        }

        $in['company_name_phonetic'] = Validation::kana($trim('company_name_phonetic'));
        if ($in['company_name_phonetic'] !== '' && mb_strlen($in['company_name_phonetic'], 'UTF-8') > 200) {
            $v->add('company_name_phonetic', '会社名フリガナは200文字以内で入力してください。');
        }
        if ($in['company_name_phonetic'] !== '' && !preg_match('/^[ァ-ヶー　・（）()]+$/u', $in['company_name_phonetic'])) {
            $v->add('company_name_phonetic', '会社名フリガナは全角カタカナで入力してください。');
        }

        $in['representative'] = $trim('representative');
        if ($in['representative'] !== '' && mb_strlen($in['representative'], 'UTF-8') > 100) {
            $v->add('representative', '代表者は100文字以内で入力してください。');
        }

        $in['representative_letter'] = Validation::kana($trim('representative_letter'));
        if ($in['representative_letter'] !== '' && mb_strlen($in['representative_letter'], 'UTF-8') > 100) {
            $v->add('representative_letter', '代表者フリガナは100文字以内で入力してください。');
        }
        if ($in['representative_letter'] !== '' && !preg_match('/^[ァ-ヶー　・（）()]+$/u', $in['representative_letter'])) {
            $v->add('representative_letter', '代表者フリガナは全角カタカナで入力してください。');
        }

        $in['manager'] = $trim('manager');
        if ($in['manager'] !== '' && mb_strlen($in['manager'], 'UTF-8') > 100) {
            $v->add('manager', 'ご担当者は100文字以内で入力してください。');
        }

        $in['manager_letter'] = Validation::kana($trim('manager_letter'));
        if ($in['manager_letter'] !== '' && mb_strlen($in['manager_letter'], 'UTF-8') > 100) {
            $v->add('manager_letter', 'ご担当者フリガナは100文字以内で入力してください。');
        }
        if ($in['manager_letter'] !== '' && !preg_match('/^[ァ-ヶー　・（）()]+$/u', $in['manager_letter'])) {
            $v->add('manager_letter', 'ご担当者フリガナは全角カタカナで入力してください。');
        }

        $in['department_in_charge'] = $trim('department_in_charge');
        if ($in['department_in_charge'] !== '' && mb_strlen($in['department_in_charge'], 'UTF-8') > 100) {
            $v->add('department_in_charge', 'ご担当者部署は100文字以内で入力してください。');
        }

        $in['person_in_charge'] = $trim('person_in_charge');
        if ($in['person_in_charge'] !== '' && mb_strlen($in['person_in_charge'], 'UTF-8') > 100) {
            $v->add('person_in_charge', 'ご担当者役職は100文字以内で入力してください。');
        }

        $in['driver'] = $trim('driver');
        if ($in['driver'] !== '' && mb_strlen($in['driver'], 'UTF-8') > 100) {
            $v->add('driver', 'ドライバー様は100文字以内で入力してください。');
        }

        $in['driver_letter'] = Validation::kana($trim('driver_letter'));
        if ($in['driver_letter'] !== '' && mb_strlen($in['driver_letter'], 'UTF-8') > 100) {
            $v->add('driver_letter', 'ドライバー様フリガナは100文字以内で入力してください。');
        }
        if ($in['driver_letter'] !== '' && !preg_match('/^[ァ-ヶー　・（）()]+$/u', $in['driver_letter'])) {
            $v->add('driver_letter', 'ドライバー様フリガナは全角カタカナで入力してください。');
        }

        // --- tel/fax ---
        $in['tel'] = $onlyDigits($trim('tel'));
        if ($in['tel'] !== '' && (strlen($in['tel']) < 1 || strlen($in['tel']) > 15)) {
            $v->add('tel', '本社電話番号は15桁以下で入力してください。');
        }

        $in['fax'] = $onlyDigits($trim('fax'));
        if ($in['fax'] !== '' && (strlen($in['fax']) < 1 || strlen($in['fax']) > 15)) {
            $v->add('fax', 'FAX番号は15桁以下で入力してください。');
        }

        // --- address (HQ) ---
        $in['zip'] = $onlyDigits($trim('zip'));
        if ($in['zip'] === '') {
            $v->add('zip', '郵便番号は必須です。');
        }
        if ($in['zip'] !== '' && !preg_match('/^\d{7}$/', $in['zip'])) {
            $v->add('zip', '郵便番号は7桁の数字で入力してください。');
        }

        $in['pref_code'] = (int)$trim('pref_code');
        if ($in['pref_code'] < 1 || $in['pref_code'] > 47) {
            $v->add('pref_code', '都道府県を選択してください。');
        }

        $in['addr01'] = $trim('addr01');
        if ($in['addr01'] === '') {
            $v->add('addr01', '住所（市町村以下）は必須です。');
        }
        if ($in['addr01'] !== '' && mb_strlen($in['addr01'], 'UTF-8') > 255) {
            $v->add('addr01', '住所（市町村以下）は255文字以内で入力してください。');
        }

        // --- address (branch) ---
        $in['zip02'] = $onlyDigits($trim('zip02'));
        $in['pref02_code'] = (int)$trim('pref02_code');
        $in['addr02'] = $trim('addr02');

        $hasZip02 = ($in['zip02'] !== '');
        $hasAnyBranch = $hasZip02 || $in['pref02_code'] !== 0 || $in['addr02'] !== '';

        if ($hasAnyBranch) {
            if (!$hasZip02 || !preg_match('/^\d{7}$/', $in['zip02'])) {
                $v->add('zip02', '支店等の郵便番号は7桁の数字で入力してください。');
            }
            if ($in['pref02_code'] < 1 || $in['pref02_code'] > 47) {
                $v->add('pref02_code', '支店等の都道府県を選択してください。');
            }
            if ($in['addr02'] === '') {
                $v->add('addr02', '支店等の住所は必須です。');
            }
            if ($in['addr02'] !== '' && mb_strlen($in['addr02'], 'UTF-8') > 255) {
                $v->add('addr02', '支店等の住所は255文字以内で入力してください。');
            }
        } else {
            $in['zip02'] = '';
            $in['pref02_code'] = 0;
            $in['addr02'] = '';
        }

        // --- mail ---
        $in['mail01'] = $trim('mail01');
        $in['mail01'] = ($in['mail01'] !== '') ? mb_strtolower($in['mail01'], 'UTF-8') : '';
        if ($in['mail01'] !== '' && (!Validation::email($in['mail01']) || mb_strlen($in['mail01'], 'UTF-8') > 254)) {
            $v->add('mail01', 'メールアドレス1は正しい形式で入力してください。');
        }

        $in['mail02'] = $trim('mail02');
        $in['mail02'] = ($in['mail02'] !== '') ? mb_strtolower($in['mail02'], 'UTF-8') : '';
        if ($in['mail02'] !== '' && (!Validation::email($in['mail02']) || mb_strlen($in['mail02'], 'UTF-8') > 254)) {
            $v->add('mail02', 'メールアドレス2は正しい形式で入力してください。');
        }
        if ($in['mail01'] !== '' && $in['mail02'] !== '' && $in['mail01'] === $in['mail02']) {
            $v->add('mail02', 'メールアドレス2はメールアドレス1と同じ値にできません。');
        }

        // --- purpose / remarks ---
        $in['purpose'] = (string)($in['purpose'] ?? '');
        $in['remarks'] = (string)($in['remarks'] ?? '');

        // --- background ---
        $in['background'] = (int)$trim('background');
        if (!in_array($in['background'], [0, 1, 2, 3], true)) {
            $v->add('background', 'ご来社経緯を選択してください。');
        }

        $in['introducer'] = $trim('introducer');
        if ($in['introducer'] !== '' && mb_strlen($in['introducer'], 'UTF-8') > 100) {
            $v->add('introducer', 'ご紹介者は100文字以内で入力してください。');
        }

        $in['others'] = $trim('others');
        if ($in['others'] !== '' && mb_strlen($in['others'], 'UTF-8') > 100) {
            $v->add('others', 'その他は100文字以内で入力してください。');
        }

        if (!$v->ok()) {
            return ['ok' => false, 'errors' => $v->errors, 'in' => $in];
        }

        // 保存データ（NULL化もここで統一）
        $save = [
            'name' => $in['name'],
            'company_name_phonetic' => ($in['company_name_phonetic'] !== '') ? $in['company_name_phonetic'] : null,

            'representative' => ($in['representative'] !== '') ? $in['representative'] : null,
            'representative_letter' => ($in['representative_letter'] !== '') ? $in['representative_letter'] : null,

            'manager' => ($in['manager'] !== '') ? $in['manager'] : null,
            'manager_letter' => ($in['manager_letter'] !== '') ? $in['manager_letter'] : null,

            'department_in_charge' => ($in['department_in_charge'] !== '') ? $in['department_in_charge'] : null,
            'person_in_charge' => ($in['person_in_charge'] !== '') ? $in['person_in_charge'] : null,

            'driver' => ($in['driver'] !== '') ? $in['driver'] : null,
            'driver_letter' => ($in['driver_letter'] !== '') ? $in['driver_letter'] : null,

            'tel' => ($in['tel'] !== '') ? $in['tel'] : null,
            'fax' => ($in['fax'] !== '') ? $in['fax'] : null,

            'zip' => $in['zip'],
            'pref_code' => (int)$in['pref_code'],
            'addr01' => $in['addr01'],

            'zip02' => ($in['zip02'] !== '') ? $in['zip02'] : null,
            'pref02_code' => ($in['pref02_code'] >= 1 && $in['pref02_code'] <= 47) ? (int)$in['pref02_code'] : null,
            'addr02' => ($in['addr02'] !== '') ? $in['addr02'] : null,

            'mail01' => ($in['mail01'] !== '') ? $in['mail01'] : null,
            'mail02' => ($in['mail02'] !== '') ? $in['mail02'] : null,

            'purpose' => ($in['purpose'] !== '') ? (string)$in['purpose'] : null,

            'background' => (int)$in['background'],
            'introducer' => ($in['introducer'] !== '') ? $in['introducer'] : null,
            'others' => ($in['others'] !== '') ? $in['others'] : null,

            'remarks' => ($in['remarks'] !== '') ? (string)$in['remarks'] : null,
        ];

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
            Response::view('office_customers/form', [
                'title'  => '法人顧客登録',
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
            $sql = "INSERT INTO office_customers (" . implode(',', $cols) . ") VALUES (" . implode(',', array_map(fn($c) => ":$c", $cols)) . ")";
            $st = $pdo->prepare($sql);

            $bind = [];
            foreach ($cols as $c) {
                $bind[":$c"] = $res['data'][$c];
            }
            $st->execute($bind);

            $id = (int)$pdo->lastInsertId();
            $pdo->commit();

            // 最新を再取得（監査用）
            $st2 = $pdo->prepare("SELECT * FROM office_customers WHERE id = :i LIMIT 1");
            $st2->execute([':i' => $id]);
            $new = $st2->fetch() ?: [];

            $diff = Audit::diff([], $new, $this->fields);
            Audit::log('office_customers', 'OfficeCustomer', $id, 'create', (int)$me['id'], $diff);

            Response::redirect('/office_customers/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();

            Response::view('office_customers/form', [
                'title'  => '法人顧客登録',
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

        $st = $pdo->prepare("SELECT * FROM office_customers WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        $old = $st->fetch();
        if (!$old) {
            Response::notFound();
        }

        $res = $this->validate($_POST, $id);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('office_customers/form', [
                'title'  => '法人顧客編集',
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
            $st2 = $pdo->prepare("UPDATE office_customers SET {$sets} WHERE id = :id");

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

            $st3 = $pdo->prepare("SELECT * FROM office_customers WHERE id = :i LIMIT 1");
            $st3->execute([':i' => $id]);
            $new = $st3->fetch() ?: [];

            $diff = Audit::diff($old, $new, $this->fields);
            if (!empty($diff)) {
                Audit::log('office_customers', 'OfficeCustomer', $id, 'update', (int)$me['id'], $diff);
            }

            Response::redirect('/office_customers/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();

            Response::view('office_customers/form', [
                'title'  => '法人顧客編集',
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

        $st = $pdo->prepare("SELECT * FROM office_customers WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        $old = $st->fetch();
        if (!$old) {
            Response::notFound();
        }

        if (!empty($old['deleted_at'])) {
            Response::redirect('/office_customers/' . $id);
        }

        $pdo->beginTransaction();
        try {
            $now = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s');

            $st2 = $pdo->prepare("UPDATE office_customers SET deleted_at = :d WHERE id = :i");
            $st2->execute([':d' => $now, ':i' => $id]);

            $pdo->commit();

            $st3 = $pdo->prepare("SELECT * FROM office_customers WHERE id = :i LIMIT 1");
            $st3->execute([':i' => $id]);
            $new = $st3->fetch() ?: array_merge($old, ['deleted_at' => $now]);

            $diff = Audit::diff($old, $new, $this->fields);
            Audit::log('office_customers', 'OfficeCustomer', $id, 'delete', (int)$me['id'], $diff);

            Response::redirect('/office_customers');
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::fail('SERVER_ERROR', '削除に失敗しました。', 500);
        }
    }
}