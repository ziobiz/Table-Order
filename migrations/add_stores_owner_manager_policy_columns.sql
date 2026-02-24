-- stores 테이블에 가맹점 관리(Store Manage)에서 사용하는 컬럼 추가
-- 실행 후 admin_store_manage.php 저장이 정상 동작합니다.

-- MySQL: 컬럼이 이미 있으면 에러 나므로, 필요한 것만 실행하거나 한 번만 실행하세요.
ALTER TABLE stores ADD COLUMN owner_name VARCHAR(255) DEFAULT NULL AFTER store_name;
ALTER TABLE stores ADD COLUMN owner_tel VARCHAR(50) DEFAULT NULL AFTER owner_name;
ALTER TABLE stores ADD COLUMN owner_email VARCHAR(255) DEFAULT NULL AFTER owner_tel;
ALTER TABLE stores ADD COLUMN manager_name VARCHAR(255) DEFAULT NULL AFTER owner_email;
ALTER TABLE stores ADD COLUMN manager_tel VARCHAR(50) DEFAULT NULL AFTER manager_name;
ALTER TABLE stores ADD COLUMN manager_email VARCHAR(255) DEFAULT NULL AFTER manager_tel;
ALTER TABLE stores ADD COLUMN biz_no VARCHAR(50) DEFAULT NULL AFTER manager_email;
ALTER TABLE stores ADD COLUMN tax_no VARCHAR(50) DEFAULT NULL AFTER biz_no;
ALTER TABLE stores ADD COLUMN tax_address VARCHAR(255) DEFAULT NULL AFTER tax_no;
ALTER TABLE stores ADD COLUMN address VARCHAR(255) DEFAULT NULL AFTER tax_address;
ALTER TABLE stores ADD COLUMN tel VARCHAR(50) DEFAULT NULL AFTER address;
ALTER TABLE stores ADD COLUMN use_single TINYINT(1) NOT NULL DEFAULT 0 AFTER tel;
ALTER TABLE stores ADD COLUMN use_multi TINYINT(1) NOT NULL DEFAULT 0 AFTER use_single;
ALTER TABLE stores ADD COLUMN use_global TINYINT(1) NOT NULL DEFAULT 0 AFTER use_multi;
ALTER TABLE stores ADD COLUMN use_me_coupon TINYINT(1) NOT NULL DEFAULT 0 AFTER use_global;
ALTER TABLE stores ADD COLUMN use_ad_coupon TINYINT(1) NOT NULL DEFAULT 0 AFTER use_me_coupon;
ALTER TABLE stores ADD COLUMN use_we_coupon TINYINT(1) NOT NULL DEFAULT 0 AFTER use_ad_coupon;
ALTER TABLE stores ADD COLUMN single_threshold INT(11) NOT NULL DEFAULT 0 AFTER use_we_coupon;
ALTER TABLE stores ADD COLUMN single_amt INT(11) NOT NULL DEFAULT 0 AFTER single_threshold;
ALTER TABLE stores ADD COLUMN region_id VARCHAR(255) DEFAULT NULL AFTER single_amt;
ALTER TABLE stores ADD COLUMN me_coupon_threshold INT(11) NOT NULL DEFAULT 0 AFTER region_id;
ALTER TABLE stores ADD COLUMN me_coupon_currency VARCHAR(10) DEFAULT NULL AFTER me_coupon_threshold;
ALTER TABLE stores ADD COLUMN me_coupon_target INT(11) NOT NULL DEFAULT 0 AFTER me_coupon_currency;
ALTER TABLE stores ADD COLUMN me_coupon_reward VARCHAR(100) DEFAULT NULL AFTER me_coupon_target;
ALTER TABLE stores ADD COLUMN me_use_same_day TINYINT(1) NOT NULL DEFAULT 0 AFTER me_coupon_reward;
ALTER TABLE stores ADD COLUMN ad_coupon_threshold INT(11) NOT NULL DEFAULT 0 AFTER me_use_same_day;
ALTER TABLE stores ADD COLUMN ad_coupon_currency VARCHAR(10) DEFAULT NULL AFTER ad_coupon_threshold;
ALTER TABLE stores ADD COLUMN ad_coupon_type VARCHAR(50) DEFAULT NULL AFTER ad_coupon_currency;
ALTER TABLE stores ADD COLUMN ad_use_same_day TINYINT(1) NOT NULL DEFAULT 0 AFTER ad_coupon_type;
ALTER TABLE stores ADD COLUMN we_coupon_threshold INT(11) NOT NULL DEFAULT 0 AFTER ad_use_same_day;
ALTER TABLE stores ADD COLUMN we_coupon_currency VARCHAR(10) DEFAULT NULL AFTER we_coupon_threshold;
ALTER TABLE stores ADD COLUMN we_exchange_ratio INT(11) NOT NULL DEFAULT 0 AFTER we_coupon_currency;
ALTER TABLE stores ADD COLUMN we_exchange_fee INT(11) NOT NULL DEFAULT 0 AFTER we_exchange_ratio;
ALTER TABLE stores ADD COLUMN use_we_buy TINYINT(1) NOT NULL DEFAULT 0 AFTER we_exchange_fee;
ALTER TABLE stores ADD COLUMN we_use_same_day TINYINT(1) NOT NULL DEFAULT 0 AFTER use_we_buy;
ALTER TABLE stores ADD COLUMN use_review TINYINT(1) NOT NULL DEFAULT 0 AFTER we_use_same_day;
ALTER TABLE stores ADD COLUMN biz_file VARCHAR(500) DEFAULT NULL AFTER use_review;
ALTER TABLE stores ADD COLUMN store_code VARCHAR(50) DEFAULT NULL AFTER biz_file;
