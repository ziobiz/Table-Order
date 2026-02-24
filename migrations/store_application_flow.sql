-- ============================================================
-- 가맹점 온라인 신청 + 본사 승인 흐름용 테이블
-- phpMyAdmin에서 전체 복사 후 붙여넣기하여 실행하세요.
-- ============================================================

-- 1) 이메일 인증용 (store_register.php에서 사용)
CREATE TABLE IF NOT EXISTS `verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target` varchar(255) NOT NULL COMMENT '이메일 등 인증 대상',
  `code` varchar(20) NOT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'REGISTER',
  `expires_at` datetime NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_verifications_target_type` (`target`,`type`),
  KEY `idx_verifications_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='이메일 인증번호 등';

-- 2) 가맹점 온라인 신청 (승인 전까지 여기에만 저장)
CREATE TABLE IF NOT EXISTS `store_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_name` varchar(255) NOT NULL,
  `owner_name` varchar(100) NOT NULL,
  `owner_email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL COMMENT '가맹점 로그인 아이디',
  `password` varchar(255) NOT NULL COMMENT 'password_hash 저장',
  `business_type` varchar(30) NOT NULL DEFAULT 'CORPORATE' COMMENT 'CORPORATE, INDIVIDUAL_BIZ, PERSONAL',
  `biz_no` varchar(50) DEFAULT NULL COMMENT '사업자등록번호',
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL COMMENT '승인한 본사 관리자 id',
  `store_id` int(11) DEFAULT NULL COMMENT '승인 시 생성된 stores.id',
  `reject_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='가맹점 온라인 입점 신청';
