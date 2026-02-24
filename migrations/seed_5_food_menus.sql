-- 음식 메뉴 5개 강제 생성
-- category_id=1(메인 메뉴), menu_format_id=1, store_id=1 기준
-- 실행 전 menus / menu_translations 의 최대 id 확인 후 아래 id를 조정하거나 AUTO_INCREMENT 사용 환경에서 실행

SET @fmt = 1;
SET @store = 1;
SET @cat = 1;

-- 기존 최대 id 확인 없이 삽입 (AUTO_INCREMENT 사용)
INSERT INTO menus (menu_format_id, store_id, category_id, is_available, is_dinein, is_pickup, is_delivery, price, price_pickup, price_delivery, daily_limit, current_stock, image_url) VALUES
(@fmt, @store, @cat, 1, 1, 1, 1, 8000, 7200, 9600, 0, 0, NULL),
(@fmt, @store, @cat, 1, 1, 1, 1, 9000, 8100, 10800, 0, 0, NULL),
(@fmt, @store, @cat, 1, 1, 1, 1, 12000, 10800, 14400, 0, 0, NULL),
(@fmt, @store, @cat, 1, 1, 1, 1, 7000, 6300, 8400, 0, 0, NULL),
(@fmt, @store, @cat, 1, 1, 1, 1, 9000, 8100, 10800, 0, 0, NULL);

-- 방금 삽입된 5개 메뉴의 id에 번역 삽입 (MySQL 8.0+ LAST_INSERT_ID() 연속 사용 주의: 한 번의 INSERT에 여러 행이면 첫 번째 행의 id만 반환)
-- 따라서 삽입된 5개 id를 변수로 받아서 처리. 단일 INSERT이므로 id는 last_insert_id(), last_insert_id()+1, ... 로 연속.
SET @id1 = LAST_INSERT_ID();
INSERT INTO menu_translations (menu_id, lang_code, menu_name, description) VALUES
(@id1,     'ko', '김치찌개',   '얼큰한 김치와 돼지고기가 어우러진 찌개'),
(@id1+1,   'ko', '비빔밥',     '신선한 나물과 고추장으로 비빈 전통 밥'),
(@id1+2,   'ko', '삼겹살',     '직화 구이 삼겹살 (1인분)'),
(@id1+3,   'ko', '된장찌개',   '구수한 된장과 두부·야채가 들어간 찌개'),
(@id1+4,   'ko', '제육볶음',   '매콤달콤한 돼지고기 볶음');
