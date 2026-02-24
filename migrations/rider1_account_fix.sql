-- ============================================================
-- 아래 SQL 전체를 복사하여 phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- rider1 / password 로그인 가능하도록 설정합니다. (평문 비밀번호 = 테스트용)
-- ============================================================

-- 1) 이미 rider1이 있으면 비밀번호만 평문으로 변경
UPDATE `riders` SET `password` = 'password', `store_id` = 1 WHERE `username` = 'rider1';

-- 2) username이 비어 있는 기존 행이 있으면 rider1으로 설정 (setup_test 등으로 만든 경우)
UPDATE `riders` SET `username` = 'rider1', `password` = 'password', `store_id` = 1
WHERE `username` IS NULL OR `username` = ''
ORDER BY `id` LIMIT 1;

-- 3) rider1이 하나도 없으면 새로 삽입
INSERT INTO `riders` (`store_id`, `rider_name`, `phone`, `username`, `password`, `is_active`)
SELECT 1, '테스트 기사', '010-0000-0000', 'rider1', 'password', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `riders` WHERE `username` = 'rider1');
