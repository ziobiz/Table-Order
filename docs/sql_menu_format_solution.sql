-- ============================================================
-- 메뉴 솔루션(업종별 포맷) 구조
-- 본사에서 포맷을 제공하고, 가맹점은 할당된 포맷의 메뉴를 사용.
-- 사용법: phpMyAdmin > 해당 DB 선택 > SQL 탭에 아래 전체 붙여 넣기 후 실행.
-- (한 번만 실행. 이미 컬럼/테이블이 있으면 에러 나므로 필요한 부분만 따로 실행 가능)
-- ============================================================

-- 1) 메뉴 포맷(업종) 마스터 테이블
CREATE TABLE IF NOT EXISTS `menu_formats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '업종/포맷명 (예: 카페, 한식당, 패스트푸드)',
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menu_formats_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='본사 제공 메뉴 솔루션(업종별 포맷)';

-- 기본 포맷 1개 (기존 데이터 연동용)
INSERT INTO `menu_formats` (`id`, `name`, `description`, `sort_order`, `is_active`) VALUES
(1, '기본', '기본 메뉴 포맷 (마이그레이션용)', 0, 1);

-- 2) 가맹점에 포맷 할당
ALTER TABLE `stores`
  ADD COLUMN `menu_format_id` int(11) NOT NULL DEFAULT 1 COMMENT '본사가 제공한 메뉴 솔루션(포맷) ID' AFTER `id`;

UPDATE `stores` SET `menu_format_id` = 1 WHERE `menu_format_id` = 0 OR `menu_format_id` IS NULL;

-- 3) 카테고리/메뉴/옵션그룹을 포맷 소속으로
ALTER TABLE `categories`
  ADD COLUMN `menu_format_id` int(11) NOT NULL DEFAULT 1 AFTER `id`;

UPDATE `categories` SET `menu_format_id` = 1 WHERE `menu_format_id` = 0 OR `menu_format_id` IS NULL;

ALTER TABLE `menus`
  ADD COLUMN `menu_format_id` int(11) NOT NULL DEFAULT 1 AFTER `id`;

UPDATE `menus` SET `menu_format_id` = 1 WHERE `menu_format_id` = 0 OR `menu_format_id` IS NULL;

ALTER TABLE `option_groups`
  ADD COLUMN `menu_format_id` int(11) NOT NULL DEFAULT 1 AFTER `id`;

UPDATE `option_groups` SET `menu_format_id` = 1 WHERE `menu_format_id` = 0 OR `menu_format_id` IS NULL;

-- 4) 가맹점별 가격/판매여부 오버라이드 (포맷 메뉴는 본사 제공, 가맹점은 가격·판매여부만 조정)
CREATE TABLE IF NOT EXISTS `store_menu_overrides` (
  `store_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `price_override` int(11) DEFAULT NULL COMMENT 'NULL이면 포맷 기본가 사용',
  `price_pickup_override` int(11) DEFAULT NULL,
  `price_delivery_override` int(11) DEFAULT NULL,
  `is_available_override` tinyint(1) DEFAULT NULL COMMENT 'NULL이면 포맷 기본값 사용',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`store_id`, `menu_id`),
  KEY `idx_store_menu_overrides_store` (`store_id`),
  KEY `idx_store_menu_overrides_menu` (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='가맹점별 메뉴 가격/판매여부 오버라이드';
