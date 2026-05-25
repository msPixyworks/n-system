-- entity_office_customers_entries.sql
-- ============================================================
-- 法人顧客（office_customers）
--
-- 方針（cars/users と同期）
-- - 監査：create/update/delete は全カラム差分
-- - 論理削除：deleted_at をセット（NULL=有効）
-- - 一覧はデフォルト deleted_at IS NULL（「削除も含める」チェックで解除）
-- ============================================================

CREATE TABLE IF NOT EXISTS office_customers (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

  -- 会社情報
  name VARCHAR(200) NOT NULL COMMENT '会社名',
  company_name_phonetic VARCHAR(200) NULL COMMENT '会社名フリガナ（全角カタカナ）',

  representative VARCHAR(100) NULL COMMENT '代表者',
  representative_letter VARCHAR(100) NULL COMMENT '代表者フリガナ（全角カタカナ）',

  manager VARCHAR(100) NULL COMMENT 'ご担当者',
  manager_letter VARCHAR(100) NULL COMMENT 'ご担当者フリガナ（全角カタカナ）',

  department_in_charge VARCHAR(100) NULL COMMENT 'ご担当者部署',
  person_in_charge VARCHAR(100) NULL COMMENT 'ご担当者役職',

  driver VARCHAR(100) NULL COMMENT 'ドライバー様',
  driver_letter VARCHAR(100) NULL COMMENT 'ドライバー様フリガナ（全角カタカナ）',

  -- 連絡先（本社）
  tel VARCHAR(15) NULL COMMENT '本社電話番号（ハイフン除去・数字のみ）',
  fax VARCHAR(15) NULL COMMENT 'FAX番号（ハイフン除去・数字のみ）',

  zip VARCHAR(7) NOT NULL COMMENT '郵便番号（ハイフン除去7桁）',
  pref_code TINYINT UNSIGNED NOT NULL COMMENT '都道府県コード（1-47）',
  addr01 VARCHAR(255) NOT NULL COMMENT '住所（市町村以下）',

  -- 連絡先（支店等）
  zip02 VARCHAR(7) NULL COMMENT '支店等 郵便番号（ハイフン除去7桁）',
  pref02_code TINYINT UNSIGNED NULL COMMENT '支店等 都道府県コード（1-47）',
  addr02 VARCHAR(255) NULL COMMENT '支店等 住所',

  -- メール
  mail01 VARCHAR(254) NULL COMMENT 'メールアドレス1',
  mail02 VARCHAR(254) NULL COMMENT 'メールアドレス2',

  -- 利用目的
  purpose TEXT NULL COMMENT 'ご利用目的',

  -- 来社経緯
  background TINYINT UNSIGNED NOT NULL COMMENT 'ご来社経緯（1:HP/2:チラシ/3:営業）',
  introducer VARCHAR(100) NULL COMMENT 'ご紹介者',
  others VARCHAR(100) NULL COMMENT 'その他',

  -- 備考
  remarks TEXT NULL COMMENT '備考',

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- 論理削除
  deleted_at DATETIME NULL COMMENT '削除日時（論理削除）',

  INDEX idx_office_customers_deleted (deleted_at),
  INDEX idx_office_customers_name (name),
  INDEX idx_office_customers_tel (tel),
  INDEX idx_office_customers_zip (zip),
  INDEX idx_office_customers_pref (pref_code),
  INDEX idx_office_customers_background (background),
  INDEX idx_office_customers_mail01 (mail01)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
