-- ============================================================
-- 아래 SQL 전체를 복사하여 phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- drivers (HQ/STORE 통합) + deliveries (배달 현황) 테이블 생성
-- ============================================================

-- 1) drivers 테이블 (본사 소속 HQ / 가맹점 전용 STORE 통합)
CREATE TABLE IF NOT EXISTS `drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `driver_type` enum('HQ','STORE') NOT NULL DEFAULT 'STORE' COMMENT 'HQ=본사 소속(콜 수락), STORE=가맹점 전용(점주 지정)',
  `store_id` int(11) DEFAULT NULL COMMENT 'STORE일 때만 해당 매장 ID, HQ일 때 NULL',
  `name` varchar(100) NOT NULL COMMENT '기사명',
  `phone` varchar(50) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL UNIQUE COMMENT '로그인 ID',
  `password` varchar(255) DEFAULT NULL COMMENT '로그인 비밀번호 (password_hash 권장)',
  `commission_rate` decimal(5,2) DEFAULT 0.00 COMMENT '건당 수수료 비율(HQ 정산용)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_drivers_type` (`driver_type`),
  KEY `idx_drivers_store` (`store_id`),
  KEY `idx_drivers_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='배달 기사 (HQ 본사 소속 / STORE 가맹점 전용)';

-- 2) deliveries 테이블 (배달 현황: 주문 1건 = 배달 1건)
CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '주문 ID (orders.id, delivery 1건당 1행)',
  `driver_id` int(11) DEFAULT NULL COMMENT '배차된 기사 ID, 배차 전 NULL',
  `dispatch_type` enum('AUTO','MANUAL') NOT NULL DEFAULT 'AUTO' COMMENT 'AUTO=기사 수락, MANUAL=점주 지정',
  `status` varchar(20) NOT NULL DEFAULT 'WAITING' COMMENT 'WAITING→ACCEPTED→PICKED_UP→DELIVERED',
  `delivery_fee` int(11) NOT NULL DEFAULT 0 COMMENT '배달비(원), 본사 정산용',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_delivery_order` (`order_id`),
  KEY `idx_deliveries_driver` (`driver_id`),
  KEY `idx_deliveries_status` (`status`),
  KEY `idx_deliveries_status_type` (`status`,`dispatch_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='배달 현황 (상태·기사·배차방식·배달비)';

-- 3) 기존 riders 데이터가 있으면 drivers로 복사 (driver_type=STORE)
-- riders 테이블이 있고 username이 있을 때만 실행. 오류 나면 해당 블록 건너뛰세요.
-- INSERT INTO `drivers` (`driver_type`, `store_id`, `name`, `phone`, `username`, `password`, `is_active`)
-- SELECT 'STORE', r.`store_id`, r.`rider_name`, r.`phone`, r.`username`, r.`password`, IFNULL(r.`is_active`,1) FROM `riders` r
-- WHERE r.`username` IS NOT NULL AND r.`username` != '' AND NOT EXISTS (SELECT 1 FROM `drivers` d WHERE d.username = r.username);
