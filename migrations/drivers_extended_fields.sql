-- ============================================================
-- 아래 SQL 전체를 복사하여 phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- drivers 테이블에 기사 등록용 필드 추가 (성/이름, 주소, 생년월일, 이메일, 주민등록 이미지, tax/주민번호)
-- ============================================================

-- 1) drivers에 컬럼 추가 (이미 있으면 오류 → 해당 줄만 건너뛰고 실행)
ALTER TABLE `drivers` ADD COLUMN `last_name` varchar(50) DEFAULT NULL COMMENT '성' AFTER `name`;
ALTER TABLE `drivers` ADD COLUMN `first_name` varchar(50) DEFAULT NULL COMMENT '이름' AFTER `last_name`;
ALTER TABLE `drivers` ADD COLUMN `address` varchar(255) DEFAULT NULL COMMENT '주소' AFTER `phone`;
ALTER TABLE `drivers` ADD COLUMN `birth_date` date DEFAULT NULL COMMENT '생년월일' AFTER `address`;
ALTER TABLE `drivers` ADD COLUMN `email` varchar(100) DEFAULT NULL COMMENT '이메일' AFTER `birth_date`;
ALTER TABLE `drivers` ADD COLUMN `id_document_path` varchar(255) DEFAULT NULL COMMENT '주민등록증 등 이미지 경로' AFTER `email`;
ALTER TABLE `drivers` ADD COLUMN `tax_id` varchar(50) DEFAULT NULL COMMENT 'tax정보 또는 주민등록번호' AFTER `id_document_path`;

-- 2) name 컬럼이 있으면 기존 데이터는 유지. 신규 등록 시 last_name + first_name 으로 name 채우거나, name 은 표시용으로 CONCAT(last_name, first_name) 사용 가능.
