-- ============================================================
-- 아래 SQL 전체를 복사하여 phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- Driver → Rider, 가맹점 rider → Deliver 용어 변경
-- driver_type: STORE → DELIVER (가맹점 소속)
-- ============================================================

-- 1) drivers.driver_type: STORE → DELIVER (enum 변경 + 기존 데이터)
ALTER TABLE `drivers` MODIFY COLUMN `driver_type` enum('HQ','STORE','DELIVER') NOT NULL DEFAULT 'DELIVER' COMMENT 'HQ=본사 Rider, DELIVER=가맹점 Deliver';
UPDATE `drivers` SET `driver_type` = 'DELIVER' WHERE `driver_type` = 'STORE';
ALTER TABLE `drivers` MODIFY COLUMN `driver_type` enum('HQ','DELIVER') NOT NULL DEFAULT 'DELIVER' COMMENT 'HQ=본사 Rider, DELIVER=가맹점 Deliver';

-- 2) driver_applications.driver_type (테이블이 있는 경우, varchar이면 UPDATE만)
UPDATE `driver_applications` SET `driver_type` = 'DELIVER' WHERE `driver_type` = 'STORE';
