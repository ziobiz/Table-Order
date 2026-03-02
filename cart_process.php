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

    // 장바구니 합계/수량 재계산 (menu.php 와 동일한 로직 요약)
    $lang = $_POST['lang'] ?? 'ko';
    $cart_count = 0;
    $cart_total = 0;
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $line) {
            $qty = (int)($line['quantity'] ?? 0);
            if ($qty <= 0) continue;
            $menu_id_in_cart = (int)($line['menu_id'] ?? 0);
            if ($menu_id_in_cart <= 0) continue;

            $ot = $line['order_type'] ?? $order_type;
            $pcol = 'price';
            if ($ot === 'pickup')   $pcol = 'price_pickup';
            if ($ot === 'delivery') $pcol = 'price_delivery';

            $base_price = 0;
            try {
                $pstmt = $pdo->prepare("SELECT m.{$pcol} FROM menus m WHERE m.id = ?");
                $pstmt->execute([$menu_id_in_cart]);
                $row = $pstmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $base_price = (int)$row[$pcol];
                }
            } catch (Exception $e) {}

            $opt_sum = 0;
            if (!empty($line['options']) && is_array($line['options'])) {
                foreach ($line['options'] as $oid => $oval) {
                    $unit_price = 0;
                    $opt_qty = 1;
                    if (is_array($oval)) {
                        $unit_price = (int)($oval['price'] ?? 0);
                        $opt_qty = (int)($oval['qty'] ?? 1);
                        if ($opt_qty < 1) { $opt_qty = 1; }
                    } else {
                        $unit_price = (int)$oval;
                    }
                    $opt_sum += $unit_price * $opt_qty;
                }
            }

            $line_total = ($base_price + $opt_sum) * $qty;
            $cart_count += $qty;
            $cart_total += $line_total;
        }
    }

    echo json_encode([
        'status' => 'success',
        'cart_count' => $cart_count,
        'cart_total' => $cart_total
    ]);
    exit;
}