-- orders 테이블에 주방 호출 플래그 컬럼 추가
ALTER TABLE `orders`
  ADD COLUMN `kitchen_call` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=주방에서 호출함' AFTER `status`,
  ADD COLUMN `kitchen_call_at` DATETIME NULL COMMENT '주방 호출 시각' AFTER `kitchen_call`;

