<?php

/**
 * UserController.php
 * ============================================================
 * 役割:
 * - ユーザー管理（users）の CRUD + DataTables API
 *
 * 改修ポイント（n-system / K-Core方針に統一）:
 * - 権限ガードは Policies::guardView/guardEdit に寄せる（403の返しもResponse統一）
 * - 未ログイン時は、画面は / にリダイレクト、APIは 401 JSON（Response::fail）で返す
 * - DataTables の操作列（編集リンク）は編集権限がある人だけ表示
 * - create の監査は残すが、差分は“全項目”ではなく“最小限”にしてノイズを減らす
 * - destroy は物理DELETEではなく論理削除（resigned_on セット）に変更
 * - update は diff が空なら監査しない（変更なし更新のログノイズ抑制）
 *
 * 注意（論理削除）:
 * - ここでは users.resigned_on を論理削除の指標として使う
 * - 実運用では “退職＝無効化” と “単なる退職日管理” を分けたい場合があるため、
 *   将来的に is_active / deleted_at 等へ発展可能
 */

class UserController
{
    /**
     * 監査対象フィールド（V1差分用）
     * - users の監査は全項目を残しても良いが、create ではノイズになりがちなので別途制御する
     */
    private array $fields = [
        'employee_code','role_code','name','name_kana','email',
        'contract_input_permission','uncontract_input_permission',
        'joined_on','resigned_on','notes'
    ];

    /**
     * create 時に「最小限」だけ差分として残すフィールド
     * - 作成ログは必要、ただし全項目 null→値の詳細はうるさい、という運用向け
     */
    private array $createAuditFields = [
        'employee_code','role_code','name','name_kana','email',
        'contract_input_permission','uncontract_input_permission',
        'joined_on','resigned_on','notes'
    ];

    // ============================================================
    // Guards
    // ============================================================
    /**
     * 閲覧ガード（画面/JSONで挙動を揃える）
     */
    private function guardView(): array
    {
        $u = Auth::user();
        if (!$u) {
            // APIなら 401、画面ならログインへ
            if (Response::isApi()) {
                Response::fail('UNAUTHORIZED', 'Unauthorized', 401);
            }
            Response::redirect('/');
        }

        Policies::guardView($u, 'users');
        return $u;
    }

    /**
     * 編集ガード（画面/JSONで挙動を揃える）
     */
    private function guardEdit(): array
    {
        $u = $this->guardView();
        Policies::guardEdit($u, 'users');
        return $u;
    }

    // ============================================================
    // Pages
    // ============================================================
    public function index(): void
    {
        $me = $this->guardView();
        Response::view('users/index', ['title' => 'ユーザー一覧', 'me' => $me]);
    }

    public function create(): void
    {
        $me = $this->guardEdit();
        $cfg = require __DIR__ . '/../config.php';

        Response::view('users/form', [
            'title'  => 'ユーザー登録',
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
        $st = $pdo->prepare("SELECT * FROM users WHERE id = :i LIMIT 1");
        $st->execute([':i' => (int)$id]);
        $item = $st->fetch();

        if (!$item) {
            Response::notFound();
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('users/show', [
            'title' => 'ユーザー詳細',
            'cfg'   => $cfg,
            'item'  => $item,
            'me'    => $me,
        ]);
    }

    public function edit($id): void
    {
        $me = $this->guardEdit();

        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM users WHERE id = :i LIMIT 1");
        $st->execute([':i' => (int)$id]);
        $item = $st->fetch();

        if (!$item) {
            Response::notFound();
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('users/form', [
            'title'  => 'ユーザー編集',
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

            // ===== DataTables 基本パラメータ =====
            $draw  = (int)($_GET['draw'] ?? 0);
            $start = max(0, (int)($_GET['start'] ?? 0));
            // 初期表示200件（以降はクライアント指定を尊重、上限200）
            $len = isset($_GET['length']) ? (int)$_GET['length'] : 200;
            $len = max(1, min(200, $len));

            // ===== 列ごとのフィルタ（グローバル検索なし）=====
            $f_id            = trim((string)($_GET['f_id'] ?? ''));
            $f_employee_code = trim((string)($_GET['f_employee_code'] ?? ''));
            $f_role_code     = trim((string)($_GET['f_role_code'] ?? ''));
            $f_name          = trim((string)($_GET['f_name'] ?? ''));
            $f_email         = trim((string)($_GET['f_email'] ?? ''));
            $f_contract      = trim((string)($_GET['f_contract'] ?? ''));
            $f_uncontract    = trim((string)($_GET['f_uncontract'] ?? ''));

            $where  = [];
            $params = [];

            if ($f_id !== '') {
                $where[] = "id = :fid";
                $params[':fid'] = (int)$f_id;
            }
            if ($f_employee_code !== '') {
                $where[] = "employee_code LIKE :fec";
                $params[':fec'] = "%{$f_employee_code}%";
            }
            if ($f_role_code !== '' && ctype_digit($f_role_code)) {
                $where[] = "role_code = :frc";
                $params[':frc'] = (int)$f_role_code;
            }
            if ($f_name !== '') {
                $where[] = "name LIKE :fnm";
                $params[':fnm'] = "%{$f_name}%";
            }
            if ($f_email !== '') {
                $where[] = "email LIKE :fem";
                $params[':fem'] = "%{$f_email}%";
            }
            if ($f_contract !== '' && ($f_contract === '0' || $f_contract === '1')) {
                $where[] = "contract_input_permission = :fcp";
                $params[':fcp'] = (int)$f_contract;
            }
            if ($f_uncontract !== '' && ($f_uncontract === '0' || $f_uncontract === '1')) {
                $where[] = "uncontract_input_permission = :fup";
                $params[':fup'] = (int)$f_uncontract;
            }

            $wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

            // ===== 並び替え（サーバサイド）=====
            $colMap = [
                0 => 'id',
                1 => 'employee_code',
                2 => 'role_code',
                3 => 'name',
                4 => 'email',
                5 => 'contract_input_permission',
                6 => 'uncontract_input_permission',
            ];
            $orderByParts = [];
            if (isset($_GET['order']) && is_array($_GET['order'])) {
                $count = 0;
                foreach ($_GET['order'] as $ord) {
                    if ($count >= 3) break;
                    $colIdx = isset($ord['column']) ? (int)$ord['column'] : null;
                    $dir    = strtolower((string)($ord['dir'] ?? ''));
                    if (!isset($colMap[$colIdx])) continue;
                    $dir = ($dir === 'asc' ? 'ASC' : ($dir === 'desc' ? 'DESC' : 'ASC'));
                    $orderByParts[] = $colMap[$colIdx] . ' ' . $dir;
                    $count++;
                }
            }
            if (!$orderByParts) {
                $orderByParts[] = 'id DESC';
            }
            $orderBySql = ' ORDER BY ' . implode(', ', $orderByParts);

            // ===== 件数 =====
            $total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

            $stc = $pdo->prepare("SELECT COUNT(*) FROM users{$wsql}");
            foreach ($params as $k => $v) { $stc->bindValue($k, $v); }
            $stc->execute();
            $filtered = (int)$stc->fetchColumn();

            // ===== データ取得 =====
            $limitClause = " LIMIT {$start}, {$len}";
            $sql = "SELECT id, employee_code, role_code, name, email,
                           contract_input_permission, uncontract_input_permission
                    FROM users{$wsql}{$orderBySql}{$limitClause}";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) { $st->bindValue($k, $v); }
            $st->execute();
            $rows = $st->fetchAll() ?: [];

            $canEdit = Policies::canEditUsers($me);

            $data = array_map(function ($r) use ($cfg, $canEdit) {
                $id = (int)$r['id'];

                $buttons = '<a class="btn btn-sm btn-outline-primary" href="/users/'.$id.'">詳細</a>';
                if ($canEdit) {
                    $buttons .= ' <a class="btn btn-sm btn-outline-secondary" href="/users/'.$id.'/edit">編集</a>';
                }

                return [
                    $id,
                    htmlspecialchars($r['employee_code'] ?? '', ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($cfg['roles'][(int)$r['role_code']] ?? '-', ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($r['email'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    ((int)$r['contract_input_permission'] ? 'あり' : 'なし'),
                    ((int)$r['uncontract_input_permission'] ? 'あり' : 'なし'),
                    $buttons,
                ];
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

        $in['employee_code'] = trim((string)($in['employee_code'] ?? ''));
        if ($in['employee_code'] === '') $v->add('employee_code', '社員コードは必須です。');

        $in['role_code'] = (int)($in['role_code'] ?? 0);
        if (!isset($cfg['roles'][$in['role_code']])) $v->add('role_code', '権限を選択してください。');

        $in['name'] = trim((string)($in['name'] ?? ''));
        if ($in['name'] === '') $v->add('name', '社員名は必須です。');

        $in['name_kana'] = Validation::kana(trim((string)($in['name_kana'] ?? '')));
        if ($in['name_kana'] === '') $v->add('name_kana', '社員名フリガナは必須です。');
        if ($in['name_kana'] !== '' && !preg_match('/^[ァ-ヶー　]+$/u', $in['name_kana'])) {
            $v->add('name_kana', '社員名フリガナは全角カタカナで入力してください。');
        }

        // email はログイン・重複判定の揺れを減らすため小文字化（Auth側と合わせる）
        $in['email'] = trim((string)($in['email'] ?? ''));
        $in['email'] = ($in['email'] !== '') ? mb_strtolower($in['email'], 'UTF-8') : '';
        if ($in['email'] !== '' && !Validation::email($in['email'])) {
            $v->add('email', 'ユーザーIDはメールアドレス形式で入力してください。');
        }

        $in['contract_input_permission'] = (int)($in['contract_input_permission'] ?? 0);
        $in['uncontract_input_permission'] = (int)($in['uncontract_input_permission'] ?? 0);

        $in['joined_on'] = trim((string)($in['joined_on'] ?? ''));
        $in['resigned_on'] = trim((string)($in['resigned_on'] ?? ''));

        // パスワード
        $password = (string)($in['password'] ?? '');
        $password_confirm = (string)($in['password_confirm'] ?? '');

        // emailが入っているユーザーはログイン対象 -> 新規は必須、更新は入力があれば更新
        $needPassword = ($in['email'] !== '') && ($id === null || $password !== '');
        if ($needPassword) {
            $min = (int)$cfg['password']['min'];
            $max = (int)$cfg['password']['max'];

            if ($password === '') $v->add('password', 'パスワードは必須です。');
            if (strlen($password) < $min || strlen($password) > $max) {
                $v->add('password', "パスワードは{$min}文字以上{$max}文字以下で入力してください。");
            }
            if (!preg_match($cfg['password']['regex'], $password)) {
                $v->add('password', 'パスワードは半角英数記号で入力してください。');
            }
            if ($password !== $password_confirm) {
                $v->add('password_confirm', '確認用パスワードが一致しません。');
            }
        }

        // 重複チェック
        $pdo = Db::pdo();

        $st = $pdo->prepare("SELECT id FROM users WHERE employee_code = :c LIMIT 1");
        $st->execute([':c' => $in['employee_code']]);
        $row = $st->fetch();
        if ($row && (int)$row['id'] !== (int)($id ?? 0)) {
            $v->add('employee_code', '社員コードは既に使用されています。');
        }

        if ($in['email'] !== '') {
            $st = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
            $st->execute([':e' => $in['email']]);
            $row = $st->fetch();
            if ($row && (int)$row['id'] !== (int)($id ?? 0)) {
                $v->add('email', 'ユーザーID（メール）は既に使用されています。');
            }
        }

        if (!$v->ok()) {
            return ['ok' => false, 'errors' => $v->errors, 'in' => $in];
        }

        $save = [
            'employee_code'               => $in['employee_code'],
            'role_code'                   => $in['role_code'],
            'name'                        => $in['name'],
            'name_kana'                   => $in['name_kana'],
            'email'                       => ($in['email'] !== '') ? $in['email'] : null,
            'contract_input_permission'   => $in['contract_input_permission'],
            'uncontract_input_permission' => $in['uncontract_input_permission'],
            'joined_on'                   => ($in['joined_on'] !== '') ? $in['joined_on'] : null,
            'resigned_on'                 => ($in['resigned_on'] !== '') ? $in['resigned_on'] : null,
            'notes'                       => trim((string)($in['notes'] ?? '')),
        ];

        if ($needPassword) {
            $save['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
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
            Response::view('users/form', [
                'title'  => 'ユーザー登録',
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
            $sql = "INSERT INTO users (".implode(',', $cols).") VALUES (".implode(',', array_map(fn($c) => ":$c", $cols)).")";
            $st = $pdo->prepare($sql);

            $bind = [];
            foreach ($cols as $c) $bind[":$c"] = $res['data'][$c];
            $st->execute($bind);

            $id = (int)$pdo->lastInsertId();
            $pdo->commit();

            // create の監査は「必要」。ただし詳細は最小限にする（ノイズ対策）
            $createDiff = Audit::diff([], $res['data'], $this->createAuditFields);
            Audit::log('users', 'User', $id, 'create', (int)$me['id'], $createDiff);

            Response::redirect('/users/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();

            // 画面はフォームに戻してメッセージ
            Response::view('users/form', [
                'title'  => 'ユーザー登録',
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

        // 現在値
        $st = $pdo->prepare("SELECT * FROM users WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        $old = $st->fetch();
        if (!$old) {
            Response::notFound();
        }

        // 入力検証
        $res = $this->validate($_POST, $id);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('users/form', [
                'title'  => 'ユーザー編集',
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
            $st2 = $pdo->prepare("UPDATE users SET {$sets} WHERE id = :id");

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

            // 差分がある時だけ監査（変更なし更新のノイズ抑制）
            $newMerged = array_merge($old, $res['data']);
            $diff = Audit::diff($old, $newMerged, $this->fields);
            if (!empty($diff)) {
                Audit::log('users', 'User', $id, 'update', (int)$me['id'], $diff);
            }

            Response::redirect('/users/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();

            Response::view('users/form', [
                'title'  => 'ユーザー編集',
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
    /**
     * 論理削除（resigned_on をセット）
     *
     * 運用:
     * - 物理削除はしない（監査や actor_user_id の参照整合を壊さない）
     * - 退職日が既に入っている場合は上書きしない（必要なら上書き運用に変更）
     */
    public function destroy($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $id = (int)$id;
        $pdo = Db::pdo();

        // 対象取得（存在確認）
        $st = $pdo->prepare("SELECT * FROM users WHERE id = :i LIMIT 1");
        $st->execute([':i' => $id]);
        $old = $st->fetch();
        if (!$old) {
            Response::notFound();
        }

        // 自分自身の削除を禁止したい場合はここで止める（任意）
        // if ((int)$me['id'] === $id) Response::fail('INVALID_OPERATION', '自分自身は削除できません。', 400);

        // 既に退職日が入っているなら何もしない（任意の運用）
        if (!empty($old['resigned_on'])) {
            Response::redirect('/users/' . $id);
        }

        $pdo->beginTransaction();
        try {
            // 退職日＝今日（ローカル日付）
            $today = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');

            $st2 = $pdo->prepare("UPDATE users SET resigned_on = :d WHERE id = :i");
            $st2->execute([':d' => $today, ':i' => $id]);

            $pdo->commit();

            // 論理削除も監査（old/new を残す）
            $diff = Audit::diff($old, array_merge($old, ['resigned_on' => $today]), ['resigned_on']);
            Audit::log('users', 'User', $id, 'delete', (int)$me['id'], $diff);

            Response::redirect('/users');
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::fail('SERVER_ERROR', '削除に失敗しました。', 500);
        }
    }
}
