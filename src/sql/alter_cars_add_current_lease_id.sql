-- src/sql/alter_cars_add_current_lease_id.sql
-- ============================================================
-- cars に「現在有効なリースID（current_lease_id）」を追加する
--
-- 目的:
-- - ある車両が「現在リース中か」を高速・確実に判定するため
-- - リース登録/終了確定処理で cars.current_lease_id をセット/解除する運用を確立するため
--
-- 運用ルール（アプリ側で担保）:
-- - car_leases.status='active' のとき:
--     cars.current_lease_id = car_leases.id
--     cars.status_code = 2（リース中）
-- - リース終了確定（ended/canceled）のとき:
--     cars.current_lease_id = NULL
--     cars.status_code = 1（在庫）
--
-- 注意:
-- - 既存 cars テーブルには status_code があるが、将来の事故防止のため
--   判定は current_lease_id を主に使い、status_code は表示/運用の補助とする。 
-- ============================================================

ALTER TABLE cars
  ADD COLUMN current_lease_id BIGINT UNSIGNED NULL COMMENT '現在有効なリースID（car_leases.id）' AFTER status_code,
  ADD KEY idx_cars_current_lease_id (current_lease_id);

-- 外部キー（運用方針により有効化）
-- ALTER TABLE cars
--   ADD CONSTRAINT fk_cars_current_lease_id
--     FOREIGN KEY (current_lease_id) REFERENCES car_leases(id)
--     ON UPDATE RESTRICT ON DELETE SET NULL;
