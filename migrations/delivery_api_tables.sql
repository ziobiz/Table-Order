-- ============================================================
-- 외부 배달앱 API 연동 (본사 계약 API 관리 + 가맹점 키 입력)
-- 적용 대상: 한국(KR), 태국(TH), 일본(JP)
-- phpMyAdmin에서 전체 복사 후 붙여넣기하여 실행하세요.
-- ============================================================

-- 1) 본사에서 계약·등록한 배달 API 목록 (가맹점은 여기서 선택 후 키만 입력)
CREATE TABLE IF NOT EXISTS `delivery_api_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '영문 표기 (예: Baemin, Grab, LINE)',
  `name_local` varchar(100) DEFAULT NULL COMMENT '현지어/로컬명 (예: 배민, 그랩)',
  `country_code` char(2) NOT NULL DEFAULT 'KR' COMMENT 'KR=한국, TH=태국, JP=일본',
  `api_base_url` varchar(500) DEFAULT NULL COMMENT 'API 기본 URL (본사 연동용)',
  `auth_type` enum('API_KEY','KEY_SECRET','OAUTH') NOT NULL DEFAULT 'KEY_SECRET' COMMENT '인증 방식',
  `description` text COMMENT '연동 안내 (가맹점용)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name_country` (`name`,`country_code`),
  KEY `idx_country` (`country_code`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='본사 계약 배달 API (배민, 라인, 그렙 등)';

-- 2) 가맹점별 배달앱 키/연동 정보 (가맹점이 직접 입력)
CREATE TABLE IF NOT EXISTS `store_delivery_api_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `credential_key` varchar(255) DEFAULT NULL COMMENT 'API Key / Client ID / 가맹점 ID',
  `credential_secret` varchar(500) DEFAULT NULL COMMENT 'Secret / Password (저장 시 암호화 권장)',
  `extra_config` text COMMENT 'JSON 등 추가 설정',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_store_provider` (`store_id`,`provider_id`),
  KEY `fk_sdac_provider` (`provider_id`),
  KEY `fk_sdac_store` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='가맹점별 배달앱 API 키';

-- 샘플 데이터 (한국/태국/일본 대표 배달앱)
INSERT INTO `delivery_api_providers` (`name`, `name_local`, `country_code`, `api_base_url`, `auth_type`, `description`, `sort_order`) VALUES
('Baemin', '배민', 'KR', 'https://api.baemin.com', 'KEY_SECRET', '배민(우아한형제들) 연동. 가맹점 ID와 API Key/Secret을 입력하세요.', 10),
('Yogiyo', '요기요', 'KR', 'https://api.yogiyo.co.kr', 'KEY_SECRET', '요기요 연동. 발급받은 키를 입력하세요.', 20),
('Coupang Eats', '쿠팡이츠', 'KR', 'https://api.coupangeats.com', 'KEY_SECRET', '쿠팡이츠 연동.', 30),
('GrabFood', '그랩푸드', 'TH', 'https://api.grab.com', 'KEY_SECRET', 'Grab Food (태국). Partner Key/Secret을 입력하세요.', 10),
('LINE MAN', 'LINE MAN', 'TH', 'https://api.linemans.com', 'KEY_SECRET', 'LINE MAN (태국) 연동.', 20),
('Foodpanda', '푸드판다', 'TH', 'https://api.foodpanda.co.th', 'KEY_SECRET', 'Foodpanda Thailand.', 30),
('Demae-can', '출전관', 'JP', 'https://api.demaecan.com', 'KEY_SECRET', '出前館(데마에칸) 일본 연동.', 10),
('Uber Eats', '우버이츠', 'JP', 'https://api.ubereats.com', 'OAUTH', 'Uber Eats Japan. OAuth 연동.', 20)
ON DUPLICATE KEY UPDATE name_local = VALUES(name_local), description = VALUES(description);
