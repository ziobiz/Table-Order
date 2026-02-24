-- 가맹점(파트너)별 메뉴/카테고리 분리용
-- 본사는 가맹점 생성만, 메뉴 생성은 파트너(가맹점) 전용이므로
-- menus, categories, option_groups 에 store_id 추가

-- 1. categories
ALTER TABLE categories ADD COLUMN store_id INT(11) NOT NULL DEFAULT 1 AFTER id;
UPDATE categories SET store_id = 1 WHERE store_id = 0 OR store_id IS NULL;
ALTER TABLE categories ADD KEY idx_store (store_id);

-- 2. menus
ALTER TABLE menus ADD COLUMN store_id INT(11) NOT NULL DEFAULT 1 AFTER id;
UPDATE menus SET store_id = 1 WHERE store_id = 0 OR store_id IS NULL;
ALTER TABLE menus ADD KEY idx_store (store_id);

-- 3. option_groups (가맹점별 옵션 그룹 관리용)
ALTER TABLE option_groups ADD COLUMN store_id INT(11) NOT NULL DEFAULT 1 AFTER id;
UPDATE option_groups SET store_id = 1 WHERE store_id = 0 OR store_id IS NULL;
ALTER TABLE option_groups ADD KEY idx_store (store_id);
