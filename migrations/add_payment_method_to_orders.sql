-- ============================================================
-- 결제 수단 타입 기록 (블루프린트: 현금/카드/기프트카드/모바일 등)
-- orders 테이블에 payment_method 컬럼 추가
-- phpMyAdmin [SQL] 탭에 붙여넣기 후 실행
-- ============================================================

ALTER TABLE `orders` 
ADD COLUMN `payment_method` VARCHAR(20) NULL DEFAULT 'CASH' 
COMMENT 'CASH|CARD|MOBILE|POINT|GIFT_CARD|MIXED|OTHER' 
AFTER `paid_amount`;

-- 기존 데이터: NULL이면 CASH로 간주 (리포트 등에서 COALESCE(payment_method, 'CASH') 사용)
