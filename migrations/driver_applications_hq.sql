-- ============================================================
-- 아래 SQL 전체를 복사하여 phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- 본사(HQ) 배달기사 등록 신청 허용: store_id NULL 허용
-- ============================================================

ALTER TABLE `driver_applications` MODIFY COLUMN `store_id` int(11) DEFAULT NULL COMMENT '지원 가맹점 (NULL=본사 등록)';
