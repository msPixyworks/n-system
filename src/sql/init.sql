-- ============================================================
-- n-system / K-Core 初期SQL（最終版）
-- ============================================================
-- ポイント:
-- - 重複していた CREATE/INSERT ブロックを1回に整理
-- - 退職者ログイン禁止（resigned_on）/ 監査（audit_logs）/ レート制限（auth_login_throttles）を含む
-- - コメントは「テーブルコメント」「カラムコメント」も残す（MariaDB）
--   ※ 既存の `--` コメントも残しつつ、DBに永続する COMMENT も付与
--
-- 注意:
-- - 実行順: このまま上から実行でOK
-- - 既存DBに当てる場合、COMMENTやVARCHAR長変更は ALTER が必要になることがあります
-- ============================================================

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'PK',
  employee_code VARCHAR(32) NOT NULL UNIQUE COMMENT '社員コード（ユニーク）',
  role_code TINYINT UNSIGNED NOT NULL COMMENT '権限コード（config.php roles）',
  name VARCHAR(100) NOT NULL COMMENT '氏名',
  name_kana VARCHAR(100) NOT NULL COMMENT '氏名フリガナ（全角カタカナ）',
  email VARCHAR(191) DEFAULT NULL UNIQUE COMMENT 'ユーザーID（メール形式/NULL可）',
  password_hash VARCHAR(255) DEFAULT NULL COMMENT 'パスワードハッシュ（emailがある場合は必須：アプリ側で制御）',
  contract_input_permission TINYINT(1) NOT NULL DEFAULT 0 COMMENT '契約入力権限（0/1）',
  uncontract_input_permission TINYINT(1) NOT NULL DEFAULT 0 COMMENT '未契約入力権限（0/1）',
  joined_on DATE DEFAULT NULL COMMENT '入社日',
  resigned_on DATE DEFAULT NULL COMMENT '退職日（論理削除の指標）',
  notes TEXT DEFAULT NULL COMMENT '備考',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='ユーザー（ログイン/権限/退職管理）';

-- ------------------------------------------------------------
-- audit_logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'PK',
  module VARCHAR(64) NOT NULL COMMENT 'モジュールキー（例: users, audit, cars 等）',
  entity_type VARCHAR(64) NOT NULL COMMENT '表示用エンティティ種別（例: User）',
  entity_id BIGINT UNSIGNED NOT NULL COMMENT '対象ID（不明の場合は 0 を許容運用）',
  action VARCHAR(32) NOT NULL COMMENT 'アクション（create/update/delete/login/logout/login_failed/login_blocked 等）',
  actor_user_id BIGINT UNSIGNED DEFAULT NULL COMMENT '実行者 users.id（不明ならNULL）',
  ip VARCHAR(45) DEFAULT NULL COMMENT '実行元IP（IPv6対応）',
  user_agent VARCHAR(255) DEFAULT NULL COMMENT 'User-Agent（最大255）',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '記録日時',
  INDEX idx1 (module, entity_type, entity_id),
  INDEX idx2 (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='監査ログ（ヘッダ）';

-- ------------------------------------------------------------
-- audit_log_details
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log_details (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'PK',
  audit_log_id BIGINT UNSIGNED NOT NULL COMMENT 'audit_logs.id',
  field_name VARCHAR(100) NOT NULL COMMENT 'フィールドキー',
  old_value TEXT DEFAULT NULL COMMENT '旧値（文字列化）',
  new_value TEXT DEFAULT NULL COMMENT '新値（文字列化）',
  FOREIGN KEY (audit_log_id) REFERENCES audit_logs(id) ON DELETE CASCADE,
  INDEX idx1 (audit_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='監査ログ（差分詳細）';

-- ------------------------------------------------------------
-- auth_login_throttles（レート制限: IP×email）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auth_login_throttles (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'PK',
  ip VARCHAR(45) NOT NULL COMMENT 'IP（IPv6対応）',
  email VARCHAR(191) NOT NULL COMMENT 'メール（小文字正規化）',
  fail_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '失敗回数',
  first_failed_at DATETIME DEFAULT NULL COMMENT 'ウィンドウ開始（初回失敗）',
  last_failed_at DATETIME DEFAULT NULL COMMENT '最終失敗日時',
  blocked_until DATETIME DEFAULT NULL COMMENT 'ブロック期限（未来ならブロック中）',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  UNIQUE KEY uk_ip_email (ip, email),
  INDEX idx_blocked_until (blocked_until),
  INDEX idx_last_failed_at (last_failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='ログイン試行のレート制限（IP×email）';

-- ------------------------------------------------------------
-- 初期ユーザー投入（n-system / K-Core）
-- ------------------------------------------------------------
-- 注意:
-- - password_hash は「php -r "echo password_hash('任意の初期PW', PASSWORD_DEFAULT), PHP_EOL;"」で生成したものを貼る
-- - このINSERTは二重実行しても増殖しない（NOT EXISTS）
-- - email はログインで小文字化される想定なので、小文字で登録する

INSERT INTO users (
  employee_code,
  role_code,
  name,
  name_kana,
  email,
  password_hash,
  contract_input_permission,
  uncontract_input_permission,
  joined_on,
  resigned_on,
  notes
)
SELECT
  '0001',
  4, -- 例: マネージャー（config.php の roles と合わせる）
  '管理者',
  'カンリシャ',
  'admin@example.com',
  '$2y$10$3UQe5amtAPTR28cHbbCGq.4P0a8A9e2qQsp1nCjA.iTCDWGAkbery', -- admin1234
  1,
  1,
  '2020-01-01',
  NULL,
  '初期管理者'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM users WHERE email = 'admin@example.com'
);
