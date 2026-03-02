-- 온라인 예약(reservations) 테이블 생성
-- phpMyAdmin에서 이 스크립트를 실행해 주세요.

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL COMMENT '가맹점 ID (stores.id)',
  `user_id` int(11) DEFAULT NULL COMMENT '회원 예약일 경우 users.id, 비회원이면 NULL',
  `customer_name` varchar(100) NOT NULL COMMENT '예약자 이름',
  `tel` varchar(50) NOT NULL COMMENT '연락처',
  `party_size` int(11) NOT NULL DEFAULT '2' COMMENT '인원 수',
  `reserve_date` date NOT NULL COMMENT '예약 날짜 (YYYY-MM-DD)',
  `reserve_time` time NOT NULL COMMENT '예약 시간 (HH:MM:SS)',
  `status` enum('PENDING','CONFIRMED','SEATED','CANCELLED','NO_SHOW','COMPLETED') NOT NULL DEFAULT 'PENDING' COMMENT '예약 상태',
  `note` varchar(255) DEFAULT NULL COMMENT '요청사항/메모',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reservations_store_date` (`store_id`,`reserve_date`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='온라인 예약 관리 테이블';

