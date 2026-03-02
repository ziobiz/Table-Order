-- KDS 주문 히스토리 보관 시간 설정 컬럼 추가
ALTER TABLE `stores`
  ADD COLUMN `kds_history_hours` TINYINT(3) NOT NULL DEFAULT 24 COMMENT 'KDS 히스토리 보관 시간 (24/48/72시간)' AFTER `kds_disable_alerts`;

