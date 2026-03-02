-- 체크 분할(Split check) 기능: orders 테이블 확장
-- 인원 수로 나누기(BY_GUESTS) / 한 번에 결제(FULL) 저장용
-- phpMyAdmin에서 실행 후 적용

ALTER TABLE `orders`
  ADD COLUMN `split_type` VARCHAR(20) NOT NULL DEFAULT 'FULL' COMMENT 'FULL=한번에, BY_GUESTS=인원수로나누기' AFTER `payment_method`,
  ADD COLUMN `split_guests` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '분할 시 인원 수 (1~20)' AFTER `split_type`;
