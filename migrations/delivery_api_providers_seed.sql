-- ============================================================
-- 아래 SQL 전체를 복사하여 phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- (테이블이 없으면 생성하고, 국가별 유명 배달업체를 한 번에 넣습니다.)
-- ============================================================

-- 1) 본사 계약 배달 API 테이블
CREATE TABLE IF NOT EXISTS `delivery_api_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '영문 표기',
  `name_local` varchar(100) DEFAULT NULL COMMENT '현지어/로컬명',
  `country_code` char(2) NOT NULL DEFAULT 'KR',
  `api_base_url` varchar(500) DEFAULT NULL,
  `auth_type` enum('API_KEY','KEY_SECRET','OAUTH') NOT NULL DEFAULT 'KEY_SECRET',
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name_country` (`name`,`country_code`),
  KEY `idx_country` (`country_code`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='본사 계약 배달 API';

-- 2) 가맹점별 배달앱 키 테이블
CREATE TABLE IF NOT EXISTS `store_delivery_api_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `credential_key` varchar(255) DEFAULT NULL,
  `credential_secret` varchar(500) DEFAULT NULL,
  `extra_config` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_store_provider` (`store_id`,`provider_id`),
  KEY `fk_sdac_provider` (`provider_id`),
  KEY `fk_sdac_store` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='가맹점별 배달앱 API 키';

-- 3) 국가별 유명 배달업체 (KR, TH, JP, SG, VN, ID, IN, MY) — 중복 시 업데이트만
INSERT INTO `delivery_api_providers` (`name`, `name_local`, `country_code`, `api_base_url`, `auth_type`, `description`, `sort_order`) VALUES
('Baemin', '배민', 'KR', 'https://api.baemin.com', 'KEY_SECRET', '배민(우아한형제들) 연동. 가맹점 ID와 API Key/Secret을 입력하세요.', 10),
('Yogiyo', '요기요', 'KR', 'https://api.yogiyo.co.kr', 'KEY_SECRET', '요기요 연동. 발급받은 키를 입력하세요.', 20),
('Coupang Eats', '쿠팡이츠', 'KR', 'https://api.coupangeats.com', 'KEY_SECRET', '쿠팡이츠 연동.', 30),
('GrabFood', '그랩푸드', 'TH', 'https://api.grab.com', 'KEY_SECRET', 'Grab Food (태국). Partner Key/Secret을 입력하세요.', 10),
('LINE MAN', 'LINE MAN', 'TH', 'https://api.linemans.com', 'KEY_SECRET', 'LINE MAN (태국) 연동.', 20),
('Foodpanda', '푸드판다', 'TH', 'https://api.foodpanda.co.th', 'KEY_SECRET', 'Foodpanda Thailand.', 30),
('Demae-can', '출전관', 'JP', 'https://api.demaecan.com', 'KEY_SECRET', '出前館(데마에칸) 일본 연동.', 10),
('Uber Eats', '우버이츠', 'JP', 'https://api.ubereats.com', 'OAUTH', 'Uber Eats Japan. OAuth 연동.', 20),
('GrabFood', 'GrabFood', 'SG', 'https://api.grab.com', 'KEY_SECRET', 'Grab Food Singapore. Partner Key/Secret을 입력하세요.', 10),
('Deliveroo', 'Deliveroo', 'SG', 'https://api.deliveroo.com', 'KEY_SECRET', 'Deliveroo Singapore 연동.', 20),
('Foodpanda', 'Foodpanda', 'SG', 'https://api.foodpanda.sg', 'KEY_SECRET', 'Foodpanda Singapore.', 30),
('GrabFood', 'GrabFood', 'VN', 'https://api.grab.com', 'KEY_SECRET', 'Grab Food Vietnam. Partner Key/Secret을 입력하세요.', 10),
('ShopeeFood', 'ShopeeFood', 'VN', 'https://api.shopee.vn', 'KEY_SECRET', 'ShopeeFood Vietnam 연동.', 20),
('Baemin Vietnam', 'Baemin Vietnam', 'VN', 'https://api.baemin.vn', 'KEY_SECRET', 'Baemin Vietnam (배민 베트남).', 30),
('GoFood', 'GoFood', 'ID', 'https://api.gojek.com', 'KEY_SECRET', 'Gojek GoFood (인도네시아). Partner Key/Secret을 입력하세요.', 10),
('GrabFood', 'GrabFood', 'ID', 'https://api.grab.com', 'KEY_SECRET', 'Grab Food Indonesia. Partner Key/Secret을 입력하세요.', 20),
('ShopeeFood', 'ShopeeFood', 'ID', 'https://api.shopee.co.id', 'KEY_SECRET', 'ShopeeFood Indonesia 연동.', 30),
('Traveloka Eats', 'Traveloka Eats', 'ID', 'https://api.traveloka.com', 'KEY_SECRET', 'Traveloka Eats (인도네시아).', 40),
('Swiggy', 'Swiggy', 'IN', 'https://api.swiggy.com', 'KEY_SECRET', 'Swiggy (인도 대표 배달앱). Partner Key/Secret을 입력하세요.', 10),
('Zomato', 'Zomato', 'IN', 'https://api.zomato.com', 'KEY_SECRET', 'Zomato (인도) 연동.', 20),
('Dunzo', 'Dunzo', 'IN', 'https://api.dunzo.com', 'KEY_SECRET', 'Dunzo (퀵커머스·배달) 연동.', 30),
('GrabFood', 'GrabFood', 'MY', 'https://api.grab.com', 'KEY_SECRET', 'Grab Food Malaysia. Partner Key/Secret을 입력하세요.', 10),
('Foodpanda', 'Foodpanda', 'MY', 'https://api.foodpanda.com.my', 'KEY_SECRET', 'Foodpanda Malaysia.', 20),
('Deliveroo', 'Deliveroo', 'MY', 'https://api.deliveroo.com', 'KEY_SECRET', 'Deliveroo Malaysia 연동.', 30)
ON DUPLICATE KEY UPDATE name_local = VALUES(name_local), description = VALUES(description), sort_order = VALUES(sort_order);
