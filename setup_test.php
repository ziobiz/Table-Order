<?php
// setup_test.php - 배달/픽업/매장 식사 및 기사 설정 통합 버전
include 'db_config.php';

try {
    // 1. 기존 데이터 초기화 (외래 키 제약 조건 해제 후 실행)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE riders; TRUNCATE TABLE order_items; TRUNCATE TABLE orders; TRUNCATE TABLE order_sessions; TRUNCATE TABLE menu_translations; TRUNCATE TABLE menus; TRUNCATE TABLE stores;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 2. 샘플 가맹점 생성 (기본 배달비 3,000원 설정 포함)
    $sql_store = "INSERT INTO stores (
                    id, store_name, pay_mode, currency_code, 
                    tax_inclusive, tax_rate, service_charge_rate, 
                    allow_tip, tip_type, tip_value, base_delivery_fee
                ) VALUES (
                    1, '알리라 글로벌 식당', 'BOTH', 'KRW', 
                    'Y', 10.00, 0.00, 
                    'Y', 'FIXED', 1000.00, 3000.00
                )";
    $pdo->exec($sql_store);

    // 3. 샘플 배달 기사 생성 (정산 테스트용)
    $sql_rider = "INSERT INTO riders (rider_name, phone, commission_rate, vat_rate) 
                  VALUES ('홍길동 기사', '010-1234-5678', 10.00, 10.00)";
    $pdo->exec($sql_rider);

    // 4. 샘플 메뉴 생성 (비빔밥)
    $pdo->exec("INSERT INTO menus (id, store_id, price) VALUES (1, 1, 15000.00)");
    
    $langs = [
        ['ko', '비빔밥', '신선한 야채가 가득한 영양 만점 비빔밥'],
        ['en', 'Bibimbap', 'Mixed rice with fresh vegetables'],
        ['th', 'ข้าวยำเกาหลี', 'ข้าวยำเกาหลีใส่ผักสด'],
        ['ja', 'ビビンバ', '新鮮な野菜가たっぷりのビビンバ'],
        ['zh', '石锅拌饭', '配有新鲜蔬菜的韩式拌饭'],
        ['vi', 'Bibimbap', 'Cơm trộn với rau tươi']
    ];

    foreach ($langs as $l) {
        $stmt = $pdo->prepare("INSERT INTO menu_translations (menu_id, lang_code, menu_name, description) VALUES (1, ?, ?, ?)");
        $stmt->execute([$l[0], $l[1], $l[2]]);
    }

    echo "<div style='padding:20px; border:2px solid green; text-align:center; font-family:sans-serif;'>";
    echo "<h2 style='color:green;'>✅ 배달 시스템 기반 데이터 세팅 완료!</h2>";
    echo "<p>가맹점 정보, 배달 기사, 샘플 메뉴가 정상적으로 등록되었습니다.</p>";
    echo "<a href='admin_store_setting.php' style='display:inline-block; padding:10px 20px; background:black; color:white; text-decoration:none; border-radius:10px;'>매장 설정 확인하기</a>";
    echo "</div>";

} catch (Exception $e) {
    die("<h2 style='color:red;'>❌ 오류 발생:</h2>" . $e->getMessage());
}
?>