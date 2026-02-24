-- ============================================================
-- 아래 SQL 전체를 복사하여 phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- 가맹점별 기사 등록 시 필수 입력 항목 설정 저장
-- 배달기사 온라인 등록 신청서에도 동일 규칙 적용
-- ============================================================

-- 1) stores 테이블에 기사 등록 필수 항목 설정 컬럼 추가
-- (store_code 컬럼이 없으면 AFTER 제거 후 실행: ADD COLUMN driver_required_fields VARCHAR(500) DEFAULT NULL;)
ALTER TABLE `stores` ADD COLUMN `driver_required_fields` VARCHAR(500) DEFAULT NULL COMMENT '기사 등록 필수 항목(쉼표구분: last_name,first_name,address,phone,birth_date,email,id_document,tax_id,username,password)';

-- 2) 배달기사 온라인 등록 신청 저장용 테이블 (선택 사항, driver_register.php 사용 시)
CREATE TABLE IF NOT EXISTS `driver_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL COMMENT '지원 가맹점',
  `driver_type` varchar(20) NOT NULL DEFAULT 'DELIVER',
  `name` varchar(100) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `id_document_path` varchar(255) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, APPROVED, REJECTED',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `store_id` (`store_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='배달기사 온라인 등록 신청';
