-- DB/テーブル作成（初期）

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_code VARCHAR(32) NOT NULL UNIQUE,
  role_code TINYINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  name_kana VARCHAR(100) NOT NULL,
  email VARCHAR(191) DEFAULT NULL UNIQUE,   -- ユーザーID（メール形式）
  password_hash VARCHAR(255) DEFAULT NULL,  -- email がある場合は必須（アプリ側で制御）
  contract_input_permission TINYINT(1) NOT NULL DEFAULT 0,
  uncontract_input_permission TINYINT(1) NOT NULL DEFAULT 0,
  joined_on DATE DEFAULT NULL,
  resigned_on DATE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  module VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(32) NOT NULL,              -- create/update/delete/login/logout/login_failed/login_blocked 等
  actor_user_id BIGINT UNSIGNED DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx1 (module, entity_type, entity_id),
  INDEX idx2 (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS audit_log_details (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  audit_log_id BIGINT UNSIGNED NOT NULL,
  field_name VARCHAR(100) NOT NULL,
  old_value TEXT DEFAULT NULL,
  new_value TEXT DEFAULT NULL,
  FOREIGN KEY (audit_log_id) REFERENCES audit_logs(id) ON DELETE CASCADE,
  INDEX idx1 (audit_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 認証レート制限（IP×email）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auth_login_throttles (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(191) NOT NULL,
  fail_count INT UNSIGNED NOT NULL DEFAULT 0,
  first_failed_at DATETIME DEFAULT NULL,
  last_failed_at DATETIME DEFAULT NULL,
  blocked_until DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ip_email (ip, email),
  INDEX idx_blocked_until (blocked_until),
  INDEX idx_last_failed_at (last_failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 初期ユーザー投入（n-system / K-Core）
-- ------------------------------------------------------------
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
  4,
  '管理者',
  'カンリシャ',
  'admin@example.com',
  '$2y$10$3UQe5amtAPTR28cHbbCGq.4P0a8A9e2qQsp1nCjA.iTCDWGAkbery',
  1,
  1,
  '2020-01-01',
  NULL,
  '初期管理者'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM users WHERE email = 'admin@example.com'
);
