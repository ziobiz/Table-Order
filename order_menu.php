<?php
// order_menu.php - 고객용 테이블 오더 메뉴판
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// QR코드를 통해 넘어온 파라미터 (필수)
$store_id = $_GET['store_id'] ?? 1; // 없으면 1번 매장(테스트)
$table_no = $_GET['table_no'] ?? '1';

// 회원 여부 확인
$user_id = $_SESSION['user_id'] ?? 0;
$is_member = ($user_id > 0);
$nickname = $_SESSION['nickname'] ?? 'Guest';

// 1. 매장 및 메뉴 정보 조회
$stmt = $pdo->prepare("SELECT * FROM menus WHERE store_id = ? AND is_soldout = 0");
$stmt->execute([$store_id]);
$menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. 주문 제출 처리 (AJAX 대응)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $cart = json_decode($_POST['cart_json'], true);
    $total = 0;
    
    if (!empty($cart)) {
        try {
            $pdo->beginTransaction();
            
            // 총액 계산
            foreach ($cart as $item) $total += ($item['price'] * $item['qty']);

            // 주문서 생성
            $stmt = $pdo->prepare("INSERT INTO orders (store_id, table_no, user_id, total_amount, status) VALUES (?, ?, ?, ?, 'PENDING')");
            $stmt->execute([$store_id, $table_no, $user_id, $total]);
            $order_id = $pdo->lastInsertId();

            // 주문 상세 저장
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, menu_id, menu_name, price, quantity) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart as $item) {
                $stmt_item->execute([$order_id, $item['id'], $item['name'], $item['price'], $item['qty']]);
            }

            $pdo->commit();
            echo "<script>alert('주문이 주방으로 전달되었습니다!'); location.href='order_menu.php?store_id=$store_id&table_no=$table_no';</script>"; exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('주문 실패');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Table Order - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard'; }</style>
</head>
<body class="bg-slate-50 pb-24">

    <header class="bg-slate-900 text-white p-4 sticky top-0 z-50 shadow-lg">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="font-black text-lg italic">ALRIRA ORDER</h1>
                <span class="text-xs text-slate-400 font-bold">Table No. <?php echo htmlspecialchars($table_no); ?></span>
            </div>
            <div>
                <?php if($is_member): ?>
                    <span class="text-xs bg-violet-600 px-3 py-1 rounded-full font-bold">💎 <?php echo htmlspecialchars($nickname); ?></span>
                <?php else: ?>
                    <a href="login.php" class="text-xs bg-slate-700 border border-slate-500 px-3 py-1 rounded-full font-bold hover:bg-slate-600">Login for Benefits</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if(!$is_member): ?>
    <div class="bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white p-3 text-center cursor-pointer" onclick="location.href='user_register.php'">
        <p class="text-xs font-bold">⚡ 지금 가입하면 첫 주문 <span class="text-yellow-300 font-black">1,000P</span> 적립!</p>
    </div>
    <?php endif; ?>

    <div class="p-4 grid gap-4">
        <?php foreach($menus as $m): ?>
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex gap-4">
            <div class="w-24 h-24 bg-slate-200 rounded-xl flex-shrink-0"></div>
            
            <div class="flex-1 flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-slate-800"><?php echo htmlspecialchars($m['name']); ?></h3>
                    <p class="text-sm text-slate-500 mt-1"><?php echo number_format($m['price']); ?>원</p>
                </div>
                <button onclick="addToCart(<?php echo htmlspecialchars(json_encode($m)); ?>)" class="bg-slate-900 text-white py-2 rounded-lg text-xs font-black uppercase hover:bg-slate-800 active:scale-95 transition-transform">
                    + Add to Cart
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="cart-bar" class="fixed bottom-0 w-full bg-white border-t border-slate-200 p-4 rounded-t-3xl shadow-[0_-5px_20px_rgba(0,0,0,0.1)] transition-transform translate-y-full">
        <div class="flex justify-between items-center mb-4">
            <span class="font-black text-slate-800 text-lg">Total <span id="total-qty" class="text-violet-600">0</span> items</span>
            <span class="font-black text-slate-900 text-xl" id="total-price">0 ₩</span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="cart_json" id="cart_json">
            <input type="hidden" name="place_order" value="1">
            <button type="submit" class="w-full bg-violet-600 text-white py-4 rounded-2xl font-black text-lg shadow-lg hover:bg-violet-700 active:scale-95 transition-transform">
                ORDER NOW 🚀
            </button>
        </form>
    </div>

    <script>
    let cart = [];

    function addToCart(item) {
        const existing = cart.find(i => i.id === item.id);
        if (existing) {
            existing.qty++;
        } else {
            cart.push({ ...item, qty: 1 });
        }
        updateCartUI();
    }

    function updateCartUI() {
        const bar = document.getElementById('cart-bar');
        const qtySpan = document.getElementById('total-qty');
        const priceSpan = document.getElementById('total-price');
        const input = document.getElementById('cart_json');

        if (cart.length > 0) {
            bar.classList.remove('translate-y-full');
        }

        const totalQty = cart.reduce((sum, i) => sum + i.qty, 0);
        const totalPrice = cart.reduce((sum, i) => sum + (i.price * i.qty), 0);

        qtySpan.innerText = totalQty;
        priceSpan.innerText = totalPrice.toLocaleString() + ' ₩';
        input.value = JSON.stringify(cart);
    }
    </script>
</body>
</html>