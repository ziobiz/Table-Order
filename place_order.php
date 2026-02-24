<?php
// place_order.php - 전체 소스
include 'db_config.php';
include 'common.php';

header('Content-Type: application/json');

if (empty($_SESSION['cart'])) {
    echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
    exit;
}

try {
    $pdo->beginTransaction();

    $store_id = 1; // 테스트용 ID
    $table_no = $_SESSION['table_no'] ?? '1';

    // 1. 활성화된 세션 확인 또는 생성 (Open Tabs) [cite: 120, 121]
    $stmt = $pdo->prepare("SELECT id FROM order_sessions WHERE store_id = ? AND table_number = ? AND status = 'OPEN'");
    $stmt->execute([$store_id, $table_no]);
    $session = $stmt->fetch();

    if (!$session) {
        $stmt = $pdo->prepare("INSERT INTO order_sessions (store_id, table_number) VALUES (?, ?)");
        $stmt->execute([$store_id, $table_no]);
        $session_id = $pdo->lastInsertId();
    } else {
        $session_id = $session['id'];
    }

    // 2. 가맹점 설정 가져오기 (세금 계산용)
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->execute([$store_id]);
    $store = $stmt->fetch();

    // 3. 장바구니 합계 계산
    $subtotal = 0;
    $items_to_save = [];
    $ids = implode(',', array_keys($_SESSION['cart']));
    $db_items = $pdo->query("SELECT id, price FROM menus WHERE id IN ($ids)")->fetchAll();

    foreach ($db_items as $item) {
        $qty = $_SESSION['cart'][$item['id']];
        $subtotal += ($item['price'] * $qty);
        $items_to_save[] = ['id' => $item['id'], 'qty' => $qty, 'price' => $item['price']];
    }

    $calc = calculate_final_total($subtotal, $store['tax_inclusive'], $store['service_charge_rate']);

    // 4. 주문 정보 저장 [cite: 185]
    $stmt = $pdo->prepare("INSERT INTO orders (session_id, total_amount, payment_status) VALUES (?, ?, ?)");
    $payment_status = ($store['pay_mode'] == 'POST') ? 'PENDING' : 'PENDING'; // 선불일 경우 결제 완료 후 PENDING 해제 로직 필요
    $stmt->execute([$session_id, $calc['total'], $payment_status]);
    $order_id = $pdo->lastInsertId();

    // 5. 개별 품목 저장 (KDS 연동용) [cite: 138]
    foreach ($items_to_save as $item) {
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_id, quantity, price_at_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$order_id, $item['id'], $item['qty'], $item['price']]);
    }

    $pdo->commit();
    unset($_SESSION['cart']); // 주문 완료 후 장바구니 비우기

    echo json_encode(['status' => 'success', 'message' => 'Order placed successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>