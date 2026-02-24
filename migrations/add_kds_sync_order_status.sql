-- KDS 조리완료 시 store_orders 주문 상태 연동 on/off
-- stores 테이블에 kds_sync_order_status 컬럼 추가 (1=연동함, 0=연동 안 함)
ALTER TABLE stores ADD COLUMN kds_sync_order_status TINYINT(1) NOT NULL DEFAULT 1;
