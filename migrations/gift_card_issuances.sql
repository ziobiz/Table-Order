-- ============================================================
-- 기프트카드 발급 내역 (날짜별·가맹점별·발급자별 조회용)
-- phpMyAdmin [SQL] 탭에 붙여넣기 후 실행
-- ============================================================

CREATE TABLE IF NOT EXISTS `gift_card_issuances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issuer_id` int(11) NOT NULL DEFAULT 0 COMMENT '발급자 admin_id',
  `issuer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '발급자 표시명',
  `store_id` int(11) DEFAULT NULL COMMENT 'NULL=전체매장',
  `store_name` varchar(200) DEFAULT NULL COMMENT '매장명 (조회용)',
  `amount` int(11) NOT NULL DEFAULT 0 COMMENT '장당 금액(원)',
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT '발급 수량(장)',
  `total_amount` int(11) NOT NULL DEFAULT 0 COMMENT '총 발급액(원)',
  `expires_at` date DEFAULT NULL COMMENT '유효기간',
  `codes_sample` varchar(500) DEFAULT NULL COMMENT '코드 샘플 (최대 5개)',
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_issued_at` (`issued_at`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_store` (`store_id`),
  KEY `idx_issuer` (`issuer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='기프트카드 발급 내역';

-- 기존 테이블에 idx_expires_at 인덱스 추가 (이미 테이블이 있는 경우)
-- ALTER TABLE gift_card_issuances ADD KEY idx_expires_at (expires_at);
