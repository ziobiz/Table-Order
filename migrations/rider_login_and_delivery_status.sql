-- ============================================================
-- 아래 SQL 전체를 복사하여 phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- 배달 기사 로그인·배달 할당·상태 관리를 위한 테이블/컬럼 추가입니다.
-- ============================================================

-- 1) riders 테이블이 없으면 생성 (로그인용 username, password, 소속 매장 store_id 포함)
CREATE TABLE IF NOT EXISTS `riders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) DEFAULT NULL COMMENT '소속 매장 (NULL=플랫폼 공용)',
  `rider_name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL UNIQUE COMMENT '로그인 ID',
  `password` varchar(255) DEFAULT NULL COMMENT '로그인 비밀번호 (password_hash 권장)',
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `vat_rate` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_riders_store` (`store_id`),
  KEY `idx_riders_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='배달 기사 (가맹점 등록 자체 배달)';

-- 2) 기존 riders 테이블(1번 없이 이미 있던 경우)에 로그인 컬럼 추가 시 아래 주석 해제 후 실행
-- ALTER TABLE `riders` ADD COLUMN `username` varchar(50) NULL UNIQUE COMMENT '로그인 ID' AFTER `phone`;
-- ALTER TABLE `riders` ADD COLUMN `password` varchar(255) NULL COMMENT '로그인 비밀번호' AFTER `username`;
-- ALTER TABLE `riders` ADD COLUMN `store_id` int(11) NULL COMMENT '소속 매장' AFTER `password`;
-- ALTER TABLE `riders` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `vat_rate`;

-- 3) orders에 배달 기사·배달 상태 컬럼 추가 (이미 있으면 오류 → 해당 줄만 건너뛰고 실행)
ALTER TABLE `orders` ADD COLUMN `rider_id` int(11) NULL COMMENT '배달 담당 기사' AFTER `guest_tel`;
ALTER TABLE `orders` ADD COLUMN `delivery_status` varchar(20) NOT NULL DEFAULT 'unassigned' COMMENT 'unassigned|assigned|picked_up|on_way|delivered' AFTER `rider_id`;
ALTER TABLE `orders` ADD KEY `idx_orders_rider` (`rider_id`);

-- 4) 배달 기사 로그인 테스트용 샘플 (필요 시 주석 해제 후 실행, 비밀번호는 'password')
-- INSERT INTO riders (store_id, rider_name, phone, username, password, is_active) VALUES (1, '테스트 기사', '010-0000-0000', 'rider1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);
