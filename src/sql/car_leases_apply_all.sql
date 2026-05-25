-- src/sql/car_leases_apply_all.sql
-- ============================================================
-- 車両リース管理（car_leases / car_fy_costs / cars追加カラム）適用まとめ
--
-- ★重要:
-- - アプリ側は cars.current_lease_id を前提に動作します。
--   → これを追加しないと CarLeaseController の store/forceEnd 等で SQL エラーになります。
-- - よって「3) carsへ current_lease_id」を必ず適用してください。
--
-- 実行順（重要）:
-- 1) car_leases テーブル作成
-- 2) car_fy_costs テーブル作成
-- 3) cars に current_lease_id 追加（必須）
--
-- 適用方法（例）:
-- - まず 1) 2) は IF NOT EXISTS のため安全に実行できます
-- - 3) ALTER は二重実行で失敗するため、未適用の場合のみ実行してください
--   （SHOW COLUMNS FROM cars LIKE 'current_lease_id'; で確認）
-- ============================================================

-- ------------------------------------------------------------
-- 1) car_leases
-- ------------------------------------------------------------
-- 参照: src/sql/entity_car_leases.sql
CREATE TABLE IF NOT EXISTS car_leases (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'PK',
  car_id BIGINT UNSIGNED NOT NULL COMMENT '車両ID（cars.id）',
  lessee_type VARCHAR(20) NOT NULL COMMENT 'リース先種別（office|personal）',
  lessee_id   BIGINT UNSIGNED NOT NULL COMMENT 'リース先ID（office_customers.id or personal_customers.id）',
  lease_start_date DATE NOT NULL COMMENT 'リース開始日',
  lease_end_date   DATE NOT NULL COMMENT 'リース終了予定日',
  monthly_fee BIGINT UNSIGNED NOT NULL COMMENT '月額リース料（円）',
  status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT '状態（active|ended|canceled）',
  ended_at    DATETIME NULL COMMENT '満了確定日時（満了確定時にセット）',
  canceled_at DATETIME NULL COMMENT '解約確定日時（解約確定時にセット）',
  notes TEXT NULL COMMENT '備考',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  deleted_at DATETIME NULL COMMENT '削除日時（論理削除）',
  active_car_id BIGINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN deleted_at IS NULL AND status = 'active' THEN car_id
        ELSE NULL
      END
    ) STORED COMMENT 'active重複防止用（activeかつ未削除のときだけcar_id）',
  KEY idx_car_leases_car_id (car_id),
  KEY idx_car_leases_lessee (lessee_type, lessee_id),
  KEY idx_car_leases_status (status),
  KEY idx_car_leases_start (lease_start_date),
  KEY idx_car_leases_end   (lease_end_date),
  KEY idx_car_leases_deleted (deleted_at),
  UNIQUE KEY uq_car_leases_active_car (active_car_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='車両リース契約';

-- ------------------------------------------------------------
-- 2) car_fy_costs
-- ------------------------------------------------------------
-- 参照: src/sql/entity_car_fy_costs.sql
CREATE TABLE IF NOT EXISTS car_fy_costs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'PK',
  car_id BIGINT UNSIGNED NOT NULL COMMENT '車両ID（cars.id）',
  fy     SMALLINT UNSIGNED NOT NULL COMMENT '年度（例: 2025=2025年度）',
  tax_amount       BIGINT UNSIGNED NULL COMMENT '自動車税（年額・円）',
  insurance_amount BIGINT UNSIGNED NULL COMMENT '自動車保険料（年額・円）',
  expense_amount   BIGINT UNSIGNED NULL COMMENT '経費総額（年額・円）',
  notes TEXT NULL COMMENT '備考',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  deleted_at DATETIME NULL COMMENT '削除日時（論理削除）',
  KEY idx_car_fy_costs_car_id (car_id),
  KEY idx_car_fy_costs_fy (fy),
  KEY idx_car_fy_costs_deleted (deleted_at),
  UNIQUE KEY uq_car_fy_costs_car_fy (car_id, fy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='車両 年度別コスト台帳（税/保険/経費）';

-- ------------------------------------------------------------
-- 3) cars へ current_lease_id（必須）
-- ------------------------------------------------------------
-- 参照: src/sql/alter_cars_add_current_lease_id.sql
--
-- 適用前チェック:
--   SHOW COLUMNS FROM cars LIKE 'current_lease_id';
--
-- 未適用の場合のみ、以下を実行してください（適用済みで実行するとエラーになります）
ALTER TABLE cars
  ADD COLUMN current_lease_id BIGINT UNSIGNED NULL COMMENT '現在有効なリースID（car_leases.id）' AFTER status_code,
  ADD KEY idx_cars_current_lease_id (current_lease_id);

-- 外部キー（運用方針により有効化）
-- ALTER TABLE cars
--   ADD CONSTRAINT fk_cars_current_lease_id
--     FOREIGN KEY (current_lease_id) REFERENCES car_leases(id)
--     ON UPDATE RESTRICT ON DELETE SET NULL;
