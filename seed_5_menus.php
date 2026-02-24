<?php
/**
 * 음식 메뉴 5개 강제 생성 스크립트
 * 브라우저에서 실행: /seed_5_menus.php
 * CLI: php seed_5_menus.php
 */
include __DIR__ . '/db_config.php';

$menu_format_id = 1;
$store_id = 1;
$category_id = 1; // 메인 메뉴

$menus_to_insert = [
    ['name_ko' => '김치찌개',   'desc_ko' => '얼큰한 김치와 돼지고기가 어우러진 찌개', 'price' => 8000],
    ['name_ko' => '비빔밥',     'desc_ko' => '신선한 나물과 고추장으로 비빈 전통 밥', 'price' => 9000],
    ['name_ko' => '삼겹살',     'desc_ko' => '직화 구이 삼겹살 (1인분)', 'price' => 12000],
    ['name_ko' => '된장찌개',   'desc_ko' => '구수한 된장과 두부·야채가 들어간 찌개', 'price' => 7000],
    ['name_ko' => '제육볶음',   'desc_ko' => '매콤달콤한 돼지고기 볶음', 'price' => 9000],
];

$insert_menu = $pdo->prepare("
    INSERT INTO menus (menu_format_id, store_id, category_id, is_available, is_dinein, is_pickup, is_delivery, price, price_pickup, price_delivery, daily_limit, current_stock, image_url)
    VALUES (?, ?, ?, 1, 1, 1, 1, ?, ?, ?, 0, 0, NULL)
");
$insert_trans = $pdo->prepare("
    INSERT INTO menu_translations (menu_id, lang_code, menu_name, description) VALUES (?, 'ko', ?, ?)
");

$created = 0;
foreach ($menus_to_insert as $m) {
    $price = (int)$m['price'];
    $price_pickup = (int)($price * 0.9);
    $price_delivery = (int)($price * 1.2);
    $insert_menu->execute([$menu_format_id, $store_id, $category_id, $price, $price_pickup, $price_delivery]);
    $menu_id = (int)$pdo->lastInsertId();
    $insert_trans->execute([$menu_id, $m['name_ko'], $m['desc_ko']]);
    $created++;
}

$is_cli = (php_sapi_name() === 'cli');
if ($is_cli) {
    echo "OK: 음식 메뉴 {$created}개 생성됨.\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>시드 완료</title></head><body>";
    echo "<p><strong>완료.</strong> 음식 메뉴 <strong>{$created}개</strong>가 생성되었습니다.</p>";
    echo "<p><a href='store_menu_list.php'>가맹점 메뉴 목록</a> | <a href='menu.php'>주문 메뉴 보기</a></p>";
    echo "</body></html>";
}
