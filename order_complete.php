<?php
// order_complete.php - 주문 정보(주소 포함) 저장 및 포인트 적립
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'point_engine.php';

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: menu.php"); exit;
}

$cart = $_SESSION['cart'];
$total_amount = 0;
$store_id = $_SESSION['store_id'] ?? 1; // 매장 ID (QR 또는 세션에서 인식)
// 로그인 회원이면 user_id 사용, 아니면 NULL로 저장 (비회원 주문)
$user_id = $_SESSION['user_id'] ?? null;
$use_point = isset($_POST['use_point']) ? (int)$_POST['use_point'] : 0;
$use_gift_card = isset($_POST['use_gift_card_amount']) ? (int)$_POST['use_gift_card_amount'] : 0;
$gift_card_id = isset($_POST['gift_card_id']) ? (int)$_POST['gift_card_id'] : 0;

// 결제 수단 (CASH|CARD|MOBILE|POINT|GIFT_CARD|MIXED|OTHER)
$allowed_methods = ['CASH', 'CARD', 'MOBILE', 'POINT', 'GIFT_CARD', 'MIXED', 'OTHER'];
$payment_method = isset($_POST['payment_method']) && in_array($_POST['payment_method'], $allowed_methods, true)
    ? $_POST['payment_method'] : 'CASH';

// [추가] 배달용 주소 및 전화번호, 비회원 이름/닉네임 수신
$guest_name = $_POST['guest_name'] ?? '';
$address = $_POST['address'] ?? ''; 
$tel = $_POST['tel'] ?? '';

// 체크 분할: FULL(한번에) | BY_GUESTS(인원수로 나누기)
$split_type = isset($_POST['split_type']) && $_POST['split_type'] === 'BY_GUESTS' ? 'BY_GUESTS' : 'FULL';
$split_guests = 1;
if ($split_type === 'BY_GUESTS') {
    $split_guests = isset($_POST['split_guests']) ? (int)$_POST['split_guests'] : 2;
    if ($split_guests < 2) $split_guests = 2;
    if ($split_guests > 20) $split_guests = 20;
}

// 테이블 번호(매장식사용)를 세션에서 가져와 게스트 이름에 태그로 포함
$table_no = $_SESSION['table_no'] ?? null;
if ($table_no) {
    $table_label = 'Table ' . $table_no;
    if ($guest_name !== '') {
        $guest_name = $table_label . ' - ' . $guest_name;
    } else {
        $guest_name = $table_label;
    }
}

try {
    $pdo->beginTransaction();

    // 0. 매장 + 포인트 정책 로드
    $store_stmt = $pdo->prepare("
        SELECT s.*, p.point_value, p.min_use_amount, p.min_use_point, p.max_use_pct
        FROM stores s
        LEFT JOIN point_master_policies p ON s.point_policy_id = p.id
        WHERE s.id = ?
    ");
    $store_stmt->execute([$store_id]);
    $store = $store_stmt->fetch(PDO::FETCH_ASSOC);

    $point_value     = isset($store['point_value']) ? (float)$store['point_value'] : 1.0;
    $min_use_amount  = isset($store['min_use_amount']) ? (int)$store['min_use_amount'] : 0;
    $min_use_point   = isset($store['min_use_point']) ? (int)$store['min_use_point'] : 0;
    $max_use_pct     = isset($store['max_use_pct']) ? (int)$store['max_use_pct'] : 100;

    // 1. 주문 총액 계산
    foreach ($cart as $item) {
        $order_type = $item['order_type'];
        $price_col = ($order_type == 'pickup') ? "price_pickup" : (($order_type == 'delivery') ? "price_delivery" : "price");
        
        $m_stmt = $pdo->prepare("SELECT $price_col FROM menus WHERE id = ?");
        $m_stmt->execute([$item['menu_id']]);
        $base_price = $m_stmt->fetchColumn();
        
        $item_total = (int)$base_price;
        if (!empty($item['options']) && is_array($item['options'])) {
            foreach ($item['options'] as $oid => $oval) {
                $unit_price = 0;
                $opt_qty = 1;
                if (is_array($oval)) {
                    $unit_price = (int)($oval['price'] ?? 0);
                    $opt_qty = (int)($oval['qty'] ?? 1);
                    if ($opt_qty < 1) {
                        $opt_qty = 1;
                    }
                } else {
                    $unit_price = (int)$oval;
                }
                $item_total += $unit_price * $opt_qty;
            }
        }
        $total_amount += ($item_total * (int)$item['quantity']);
    }

    // 1-1. 정책에 따른 실제 사용 포인트 재계산
    $requested_point = max(0, $use_point);

    // 최소 주문 금액 미만이면 포인트 사용 불가
    if ($total_amount < $min_use_amount) {
        $effective_point = 0;
    } else {
        $effective_point = $requested_point;

        // 최소 사용 포인트 미만이면 0 처리 (요청이 0이면 그대로 0)
        if ($effective_point > 0 && $effective_point < $min_use_point) {
            $effective_point = 0;
        }

        // 주문 금액 대비 최대 사용 비율 제한
        $max_by_pct = floor($total_amount * ($max_use_pct / 100));

        // 포인트 가치를 고려한 경우 (1P=1원 가정이면 point_value=1)
        $max_by_amount = min($max_by_pct, $total_amount);

        if ($effective_point > $max_by_amount) {
            $effective_point = $max_by_amount;
        }
    }

    // 1-2. 기프트카드 사용 검증 및 차감
    $effective_gift = 0;
    if ($use_gift_card > 0 && $gift_card_id > 0) {
        $gift_stmt = $pdo->prepare("
            SELECT id, balance, status, store_id, expires_at
            FROM gift_cards
            WHERE id = ? FOR UPDATE
        ");
        $gift_stmt->execute([$gift_card_id]);
        $gift = $gift_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$gift || $gift['status'] !== 'ACTIVE') {
            throw new Exception('기프트카드를 사용할 수 없습니다.');
        }
        if ($gift['expires_at'] && $gift['expires_at'] < date('Y-m-d')) {
            throw new Exception('만료된 기프트카드입니다.');
        }
        if ($gift['store_id'] !== null && (int)$gift['store_id'] !== (int)$store_id) {
            throw new Exception('이 매장에서 사용할 수 없는 기프트카드입니다.');
        }
        $max_use = min((int)$gift['balance'], $total_amount - $effective_point, $use_gift_card);
        if ($max_use <= 0) {
            throw new Exception('기프트카드 잔액이 부족합니다.');
        }
        $effective_gift = $max_use;
        $upd_gift = $pdo->prepare("UPDATE gift_cards SET balance = balance - ?, status = IF(balance - ? <= 0, 'USED', status), updated_at = NOW() WHERE id = ? AND balance >= ?");
        $upd_gift->execute([$effective_gift, $effective_gift, $gift_card_id, $effective_gift]);
        if ($upd_gift->rowCount() === 0) {
            throw new Exception('기프트카드 차감에 실패했습니다.');
        }
    }

    $pay_amount = $total_amount - $effective_point - $effective_gift;

    // 2. 주문 마스터 저장 (회원/비회원 정보, address, tel, payment_method, 기프트카드, 체크 분할 포함)
    $ins_order = $pdo->prepare("
        INSERT INTO orders (user_id, store_id, order_type, total_amount, used_point, used_gift_card, gift_card_id, paid_amount, payment_method, split_type, split_guests, guest_name, guest_tel, address, tel, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $ins_order->execute([$user_id, $store_id, $order_type, $total_amount, $effective_point, $effective_gift, $effective_gift > 0 ? $gift_card_id : null, $pay_amount, $payment_method, $split_type, $split_guests, $guest_name, $tel, $address, $tel]);
    $order_id = $pdo->lastInsertId();

    // 3. 상세 내역 저장 및 재고 차감
    foreach ($cart as $item) {
        $m_stmt = $pdo->prepare("SELECT t.menu_name FROM menu_translations t WHERE t.menu_id = ? AND t.lang_code = 'ko'");
        $m_stmt->execute([$item['menu_id']]);
        $menu_name = $m_stmt->fetchColumn();

        $opt_names = [];
        if (!empty($item['options']) && is_array($item['options'])) {
            foreach ($item['options'] as $oid => $oval) {
                $opt_qty = 1;
                if (is_array($oval)) {
                    $opt_qty = (int)($oval['qty'] ?? 1);
                    if ($opt_qty < 1) {
                        $opt_qty = 1;
                    }
                }

                $o_stmt = $pdo->prepare("SELECT item_name_ko FROM option_items WHERE id = ?");
                $o_stmt->execute([$oid]);
                $name = $o_stmt->fetchColumn();
                if ($name) {
                    $label = $name;
                    if ($opt_qty > 1) {
                        $label .= ' x' . $opt_qty;
                    }
                    $opt_names[] = $label;
                }
            }
        }
        $options_text = implode(", ", $opt_names);

        $ins_item = $pdo->prepare("INSERT INTO order_items (order_id, menu_id, quantity, price, options_text) VALUES (?, ?, ?, ?, ?)");
        $ins_item->execute([$order_id, $item['menu_id'], $item['quantity'], $total_amount, $options_text]);

        // 재고 수량 업데이트
        $upd_stock = $pdo->prepare("UPDATE menus SET current_stock = current_stock + ? WHERE id = ?");
        $upd_stock->execute([$item['quantity'], $item['menu_id']]);
    }

    // 4. 포인트 적립/사용 처리 (정책에 따라 재계산된 포인트 사용값 전달)
    processPoint($user_id, $store_id, $order_id, $total_amount, $effective_point);

    // 5. 배달 주문이면 deliveries 레코드 생성 (WAITING → 기사 배차 대기)
    $order_type = !empty($cart) ? (reset($cart)['order_type'] ?? 'dinein') : 'dinein';
    if ($order_type === 'delivery') {
        try {
            $pdo->prepare("INSERT INTO deliveries (order_id, status) VALUES (?, 'WAITING')")->execute([$order_id]);
        } catch (PDOException $e) {
            // deliveries 테이블이 없으면 무시 (마이그레이션 전)
        }
    }

    $pdo->commit();

    // 주문 타입 보존 (메뉴로 돌아가기 링크용)
    $order_type_for_menu = 'dinein';
    if (!empty($cart)) {
        $first = reset($cart);
        $order_type_for_menu = $first['order_type'] ?? 'dinein';
    }
    unset($_SESSION['cart']);

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Order Complete - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center justify-center p-6 text-center">
    <div class="bg-white p-12 rounded-[3rem] shadow-2xl border border-slate-100 max-w-sm w-full">
        <div class="w-20 h-20 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-emerald-100">
            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <h2 class="text-3xl font-black text-slate-800 tracking-tighter italic uppercase mb-2">Ordered!</h2>
        <p class="text-slate-400 font-bold uppercase tracking-widest text-[10px] mb-2">주문번호 #<?php echo $order_id; ?></p>
        <?php if (isset($split_type) && $split_type === 'BY_GUESTS' && $split_guests > 1): ?>
        <p class="text-emerald-600 text-sm font-bold mb-8"><?php echo (int)$split_guests; ?>명 분할 · 1인당 <?php echo number_format(floor($total_amount / $split_guests)); ?>원</p>
        <?php else: ?>
        <div class="mb-8"></div>
        <?php endif; ?>
        <div class="text-left space-y-2 mb-8 bg-slate-50 p-6 rounded-2xl border border-slate-100">
            <p class="text-xs text-slate-500 font-bold uppercase">Delivery Address</p>
            <p class="text-sm font-black text-slate-800"><?php echo htmlspecialchars($address ?: '매장 식사/픽업'); ?></p>
        </div>
        <div class="space-y-3">
            <a href="menu.php?order_type=<?php echo htmlspecialchars($order_type_for_menu ?? 'dinein'); ?>" class="block w-full py-4 bg-emerald-500 text-white rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-emerald-600 transition-all shadow-xl text-center">
                메뉴로 돌아가기
            </a>
            <button onclick="location.href='review_write.php?order_id=<?php echo $order_id; ?>'" class="w-full py-4 bg-sky-500 text-white rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-sky-600 transition-all shadow-xl">
                Write a Review
            </button>
            <button onclick="location.href='index.php'" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-sky-500 transition-all shadow-xl">
                Back to Home
            </button>
        </div>
    </div>
</body>
</html>