-- ============================================================
-- 기프트카드 테이블 (블루프린트: 선물권 발급·잔액·결제 시 사용)
-- phpMyAdmin [SQL] 탭에 붙여넣기 후 실행
-- ============================================================

CREATE TABLE IF NOT EXISTS `gift_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL COMMENT '기프트카드 번호 (예: GC-XXXX-XXXX)',
  `balance` int(11) NOT NULL DEFAULT 0 COMMENT '현재 잔액(원)',
  `initial_balance` int(11) NOT NULL DEFAULT 0 COMMENT '발급 시 금액(원)',
  `store_id` int(11) DEFAULT NULL COMMENT 'NULL=전체매장, 숫자=해당 매장만 사용',
  `status` enum('ACTIVE','USED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  `expires_at` date DEFAULT NULL COMMENT '유효기간, NULL=무기한',
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_gift_code` (`code`),
  KEY `idx_gift_status` (`status`),
  KEY `idx_gift_store` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='기프트카드 (발급·잔액·사용)';

-- orders 테이블에 기프트카드 사용액 컬럼 추가
ALTER TABLE `orders` 
ADD COLUMN `used_gift_card` int(11) NOT NULL DEFAULT 0 COMMENT '기프트카드 사용액(원)' AFTER `used_point`,
ADD COLUMN `gift_card_id` int(11) DEFAULT NULL COMMENT '사용한 기프트카드 ID' AFTER `used_gift_card`;
