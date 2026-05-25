-- src/sql/entity_car_leases.sql
-- ============================================================
-- 車両リース管理（car_leases）
--
-- 目的:
-- - どの車両が、どこ（法人/個人）に、いつからいつまで、いくらでリースされているかを管理する
-- - 同一車両で「同時に有効なリース（active）」は1件のみ（重複リース禁止）
-- - 強制終了（満了/途中解約）を記録し、履歴を残す
--
-- 重要:
-- - リース中車両は cars.status_code=2（リース中）として扱う（更新はリース側の確定処理で集約）
-- - cars.current_lease_id は別ALTERで追加し、active の car_leases.id を入れる想定
--
-- リース先:
-- - lessee_type: 'office' | 'personal'
-- - lessee_id:   office_customers.id または personal_customers.id
--
-- 期間表示:
-- - 画面表示は「YYYY/MM/DD ～ YYYY/MM/DD」
-- - 実終了日（ended_at / canceled_at）がある場合はそれを優先して表示する（表示ロジックで対応）
--
-- DB制約（active重複防止）:
-- - generated column active_car_id により、deleted_at IS NULL かつ status='active' の行だけ
--   car_id を保持し、UNIQUE(active_car_id) で「activeは1台につき1件」を保証する。
--   ※NULL は UNIQUE の対象外なので、ended/canceled/deleted は複数行OK
-- ============================================================

CREATE TABLE IF NOT EXISTS car_leases (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'PK',

  -- 紐付け
  car_id BIGINT UNSIGNED NOT NULL COMMENT '車両ID（cars.id）',

  -- リース先（ポリモーフィック）
  lessee_type VARCHAR(20) NOT NULL COMMENT 'リース先種別（office|personal）',
  lessee_id   BIGINT UNSIGNED NOT NULL COMMENT 'リース先ID（office_customers.id or personal_customers.id）',

  -- 期間（予定）
  lease_start_date DATE NOT NULL COMMENT 'リース開始日',
  lease_end_date   DATE NOT NULL COMMENT 'リース終了予定日',

  -- 金額（固定）
  monthly_fee BIGINT UNSIGNED NOT NULL COMMENT '月額リース料（円）',

  -- 状態
  status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT '状態（active|ended|canceled）',

  -- 終了確定（満了/解約）
  ended_at    DATETIME NULL COMMENT '満了確定日時（満了確定時にセット）',
  canceled_at DATETIME NULL COMMENT '解約確定日時（解約確定時にセット）',

  -- メモ
  notes TEXT NULL COMMENT '備考',

  -- 監査/運用
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  deleted_at DATETIME NULL COMMENT '削除日時（論理削除）',

  -- ==========================================================
  -- ★ active重複防止用 generated column
  -- - active かつ未削除のときだけ car_id を保持し UNIQUE にかける
  -- - ended/canceled/deleted は NULL になるため複数行OK
  -- ==========================================================
  active_car_id BIGINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN deleted_at IS NULL AND status = 'active' THEN car_id
        ELSE NULL
      END
    ) STORED COMMENT 'active重複防止用（activeかつ未削除のときだけcar_id）',

  -- Indexes
  KEY idx_car_leases_car_id (car_id),
  KEY idx_car_leases_lessee (lessee_type, lessee_id),
  KEY idx_car_leases_status (status),
  KEY idx_car_leases_start (lease_start_date),
  KEY idx_car_leases_end   (lease_end_date),
  KEY idx_car_leases_deleted (deleted_at),

  -- active は1台につき1件のみ（NULLは重複可）
  UNIQUE KEY uq_car_leases_active_car (active_car_id)

  -- 外部キー（運用方針により有効化）
  -- ,CONSTRAINT fk_car_leases_car_id FOREIGN KEY (car_id) REFERENCES cars(id)
  --   ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='車両リース契約';

