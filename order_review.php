<?php
// order_review.php - 최종 주문 확인 및 결제 요청
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// 주문서에서 항목 취소 (해당 키만 장바구니에서 제거)
if (isset($_GET['remove']) && is_string($_GET['remove'])) {
    $remove_key = $_GET['remove'];
    if (isset($_SESSION['cart'][$remove_key])) {
        unset($_SESSION['cart'][$remove_key]);
    }
    if (empty($_SESSION['cart'])) {
        header('Location: menu.php');
        exit;
    }
    header('Location: order_review.php');
    exit;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: menu.php');
    exit;
}
// 추가 주문하기 링크용: 기존 주문 타입 유지 (메뉴에서 그대로 이어서 담기)
$order_type_for_menu = 'dinein';
$first = reset($cart);
if ($first && !empty($first['order_type'])) {
    $order_type_for_menu = $first['order_type'];
}
$total_amount = 0;

// 로그인된 사용자 기준 (테스트용 기본값)
$user_id = $_SESSION['user_id'] ?? 1;
// Global 포인트만 사용 대상으로 가져옴 (필요시 SINGLE/MULTI 확장 가능)
$stmt_pt = $pdo->prepare("SELECT COALESCE(SUM(balance),0) FROM user_wallets WHERE user_id = ? AND asset_type = 'GLOBAL'");
$stmt_pt->execute([$user_id]);
$available_point = (int)$stmt_pt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>주문 확인 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 p-6">
    <div class="max-w-md mx-auto space-y-6">
        <h2 class="text-3xl font-black text-slate-800 tracking-tighter italic uppercase">Checkout</h2>
        <?php if (!empty($_SESSION['table_no'])): ?>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                Table <?php echo htmlspecialchars($_SESSION['table_no']); ?>
            </p>
        <?php endif; ?>
        
        <div class="bg-white rounded-[2.5rem] shadow-xl p-8 space-y-6">
            <?php foreach($cart as $key => $item): 
                $m = $pdo->prepare("SELECT menu_name FROM menu_translations WHERE menu_id = ? AND lang_code = 'ko'");
                $m->execute([$item['menu_id']]);
                $name = $m->fetchColumn() ?: 'Menu #'.(int)$item['menu_id'];
                $ot = $item['order_type'] ?? 'dinein';
                $pcol = ($ot === 'pickup') ? 'price_pickup' : (($ot === 'delivery') ? 'price_delivery' : 'price');
                $m_price = $pdo->prepare("SELECT {$pcol} FROM menus WHERE id = ?");
                $m_price->execute([$item['menu_id']]);
                $base = (int)$m_price->fetchColumn();
                $item_total = $base;

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

                $line_total = $item_total * (int)$item['quantity'];
                $total_amount += $line_total;
            ?>
            <div class="flex justify-between items-center gap-3 border-b border-slate-100 pb-4">
                <div class="flex-1 min-w-0">
                    <p class="font-black text-slate-800"><?php echo htmlspecialchars($name); ?> x <?php echo (int)$item['quantity']; ?></p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Options Included</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <p class="font-black text-sky-500"><?php echo number_format($line_total); ?>원</p>
                    <a href="order_review.php?remove=<?php echo urlencode($key); ?>" class="w-9 h-9 rounded-xl bg-sky-100 text-sky-500 flex items-center justify-center font-black text-lg leading-none hover:bg-sky-200 transition" title="이 항목 취소">×</a>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="pt-4 flex justify-between items-center">
                <span class="text-xl font-black text-slate-800 uppercase">Total Amount</span>
                <span class="text-3xl font-black text-sky-500 italic"><?php echo number_format($total_amount); ?>원</span>
            </div>
        </div>

        <form action="order_complete.php" method="post" class="space-y-4">
            <!-- 기본 고객 정보 (회원/비회원 공통) -->
            <div class="bg-white rounded-[2.5rem] shadow-xl p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">이름 / 닉네임</label>
                    <input type="text" name="guest_name" class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400" placeholder="이름 또는 닉네임을 입력하세요">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">전화번호</label>
                    <input type="text" name="tel" class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400" placeholder="010-0000-0000">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">주소 (배달일 경우)</label>
                    <input type="text" name="address" class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400" placeholder="배달 받으실 주소를 입력하세요">
                </div>

                <?php if ($available_point > 0): ?>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">
                        사용 포인트 (보유: <?php echo number_format($available_point); ?> P)
                    </label>
                    <input type="number" name="use_point"
                           class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400"
                           min="0"
                           max="<?php echo min($available_point, $total_amount); ?>"
                           value="0">
                    <p class="text-[10px] text-slate-400 mt-1">0 ~ <?php echo number_format(min($available_point, $total_amount)); ?> P 까지 사용 가능합니다.</p>
                </div>
                <?php else: ?>
                    <input type="hidden" name="use_point" value="0">
                <?php endif; ?>
            </div>

            <a href="menu.php?order_type=<?php echo urlencode($order_type_for_menu); ?>" class="flex items-center justify-center w-full h-14 rounded-[2rem] bg-slate-100 text-slate-600 font-black text-sm uppercase tracking-widest hover:bg-slate-200 transition mb-3">
                추가 주문하기
            </a>
            <button type="submit" class="w-full h-20 bg-sky-500 text-white rounded-[2.5rem] shadow-xl shadow-sky-100 font-black text-xl uppercase tracking-widest hover:bg-sky-600 transition">
                Place Order Now
            </button>
        </form>
    </div>
</body>
</html>