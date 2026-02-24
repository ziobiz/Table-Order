<?php
// cart_process.php - 장바구니 담기 백엔드 처리
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_id = (int)$_POST['menu_id'];
    $order_type = $_POST['order_type'] ?? 'dinein';
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    $raw_options = isset($_POST['options']) ? $_POST['options'] : [];

    // 옵션 구조 정규화
    // - 기존 형식: [item_id => price]
    // - 신규 형식: [item_id => ['price' => price, 'qty' => qty]]
    $options = [];
    if (is_array($raw_options)) {
        foreach ($raw_options as $oid => $val) {
            if (is_array($val)) {
                $price = isset($val['price']) ? (int)$val['price'] : 0;
                $qty = isset($val['qty']) ? (int)$val['qty'] : 1;
                if ($qty < 1) {
                    continue;
                }
                $options[(int)$oid] = [
                    'price' => $price,
                    'qty'   => $qty
                ];
            } else {
                $price = (int)$val;
                $options[(int)$oid] = $price;
            }
        }
    }

    // 장바구니 초기화 (없을 경우)
    if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

    // 고유한 한 줄 아이템 생성 (같은 메뉴라도 옵션/옵션수량이 다르면 별개 아이템)
    $cart_item_key = $menu_id . "_" . md5(serialize($options));

    if (isset($_SESSION['cart'][$cart_item_key])) {
        $_SESSION['cart'][$cart_item_key]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$cart_item_key] = [
            'menu_id' => $menu_id,
            'options' => $options,
            'quantity' => $quantity,
            'order_type' => $order_type
        ];
    }
    
    echo json_encode(['status' => 'success', 'cart_count' => count($_SESSION['cart'])]);
    exit;
}