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

            <div class="pt-6 border-t border-slate-100">
                <p class="text-xs font-bold text-slate-500 uppercase mb-3">체크 분할</p>
                <div class="flex flex-wrap gap-3 items-center">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="split_type" value="FULL" checked class="split-type accent-sky-500">
                        <span class="text-sm font-bold text-slate-700">한 번에 결제</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="split_type" value="BY_GUESTS" class="split-type accent-sky-500">
                        <span class="text-sm font-bold text-slate-700">인원 수로 나누기</span>
                    </label>
                    <span id="split-guests-wrap" class="hidden items-center gap-2">
                        <span class="text-xs text-slate-500">인원</span>
                        <input type="number" id="split_guests" min="2" max="20" value="2" class="w-16 border border-slate-200 rounded-xl px-2 py-2 text-sm font-bold text-center">
                        <span class="text-xs text-slate-500">명</span>
                        <span id="per-person-label" class="text-sm font-black text-emerald-600"></span>
                    </span>
                </div>
                <p class="text-[10px] text-slate-400 mt-2">인원 수로 나누면 1인당 금액만 참고용으로 표시됩니다. 결제는 매장에서 진행합니다.</p>
            </div>
        </div>

        <form action="order_complete.php" method="post" class="space-y-4" id="checkout-form">
            <input type="hidden" name="split_type" id="form_split_type" value="FULL">
            <input type="hidden" name="split_guests" id="form_split_guests" value="1">
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

                <div id="gift-card-section">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">기프트카드</label>
                    <div class="flex gap-2">
                        <input type="text" id="gift_code" name="gift_card_code" placeholder="GC-XXXX-XXXX" class="flex-1 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-sky-400">
                        <button type="button" id="btn-check-gift" class="px-4 py-3 rounded-2xl bg-sky-100 text-sky-700 font-black text-xs uppercase hover:bg-sky-200 border border-sky-200 transition">확인</button>
                    </div>
                    <div id="gift-result" class="mt-2 text-xs font-bold hidden"></div>
                    <div id="gift-use-wrap" class="mt-2 hidden">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">사용할 금액 (원)</label>
                        <input type="number" id="use_gift_card" name="use_gift_card_amount" min="0" value="0" class="w-full border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold">
                        <input type="hidden" id="gift_card_id" name="gift_card_id" value="">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">결제 수단</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 hover:border-sky-300 cursor-pointer transition has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50">
                            <input type="radio" name="payment_method" value="CASH" checked class="accent-sky-500">
                            <span class="text-sm font-bold text-slate-700">현금</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 hover:border-sky-300 cursor-pointer transition has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50">
                            <input type="radio" name="payment_method" value="CARD" class="accent-sky-500">
                            <span class="text-sm font-bold text-slate-700">카드</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 hover:border-sky-300 cursor-pointer transition has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50">
                            <input type="radio" name="payment_method" value="MOBILE" class="accent-sky-500">
                            <span class="text-sm font-bold text-slate-700">모바일</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 hover:border-sky-300 cursor-pointer transition has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50">
                            <input type="radio" name="payment_method" value="POINT" class="accent-sky-500">
                            <span class="text-sm font-bold text-slate-700">포인트</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 hover:border-sky-300 cursor-pointer transition has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50">
                            <input type="radio" name="payment_method" value="GIFT_CARD" class="accent-sky-500">
                            <span class="text-sm font-bold text-slate-700">기프트카드</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 hover:border-sky-300 cursor-pointer transition has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50">
                            <input type="radio" name="payment_method" value="MIXED" class="accent-sky-500">
                            <span class="text-sm font-bold text-slate-700">혼합</span>
                        </label>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">매장에서 결제할 방식을 선택하세요.</p>
                </div>
            </div>

            <a href="menu.php?order_type=<?php echo urlencode($order_type_for_menu); ?>" class="flex items-center justify-center w-full h-14 rounded-[2rem] bg-sky-100 text-sky-700 font-black text-sm uppercase tracking-widest hover:bg-sky-200 border border-sky-200 transition mb-3">
                추가 주문하기
            </a>
            <button type="submit" class="w-full h-20 bg-sky-500 text-white rounded-[2.5rem] shadow-xl shadow-sky-100 font-black text-xl uppercase tracking-widest hover:bg-sky-600 transition">
                Place Order Now
            </button>
        </form>
    </div>
    <script>
    (function(){
        var totalAmount = <?php echo (int)$total_amount; ?>;
        var giftBalance = 0;
        var giftCardId = 0;
        var elCode = document.getElementById('gift_code');
        var elResult = document.getElementById('gift-result');
        var elUseWrap = document.getElementById('gift-use-wrap');
        var elUse = document.getElementById('use_gift_card');
        var elId = document.getElementById('gift_card_id');
        var btn = document.getElementById('btn-check-gift');

        function resetGift(){
            giftBalance = 0;
            giftCardId = 0;
            elUseWrap.classList.add('hidden');
            elResult.classList.add('hidden');
            elUse.value = '0';
            elId.value = '';
        }

        btn.addEventListener('click', function(){
            var code = (elCode.value || '').trim().replace(/[\s\-]/g,'').toUpperCase();
            if (code.length < 8) {
                elResult.textContent = '코드를 입력해 주세요.';
                elResult.classList.remove('hidden');
                elResult.className = 'mt-2 text-xs font-bold text-rose-600';
                resetGift();
                return;
            }
            btn.disabled = true;
            elResult.textContent = '확인 중...';
            elResult.classList.remove('hidden');
            elResult.className = 'mt-2 text-xs font-bold text-slate-500';
            var fd = new FormData();
            fd.append('gift_code', code);
            fetch('api/gift_card_check.php', { method: 'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    btn.disabled = false;
                    if (data.status === 'success') {
                        giftBalance = data.balance;
                        giftCardId = data.gift_card_id;
                        elId.value = giftCardId;
                        elResult.textContent = '잔액: ' + data.balance.toLocaleString() + '원';
                        elResult.className = 'mt-2 text-xs font-bold text-emerald-600';
                        elUseWrap.classList.remove('hidden');
                        var maxUse = Math.min(giftBalance, totalAmount);
                        elUse.max = maxUse;
                        elUse.value = maxUse > 0 ? maxUse : 0;
                    } else {
                        resetGift();
                        elResult.textContent = data.message || '조회 실패';
                        elResult.className = 'mt-2 text-xs font-bold text-rose-600';
                    }
                })
                .catch(function(){
                    btn.disabled = false;
                    resetGift();
                    elResult.textContent = '조회 중 오류가 발생했습니다.';
                    elResult.className = 'mt-2 text-xs font-bold text-rose-600';
                });
        });

        elCode.addEventListener('input', function(){ resetGift(); });

        var splitTypeRadios = document.querySelectorAll('input.split-type');
        var splitGuestsWrap = document.getElementById('split-guests-wrap');
        var splitGuestsInput = document.getElementById('split_guests');
        var formSplitType = document.getElementById('form_split_type');
        var formSplitGuests = document.getElementById('form_split_guests');
        var perPersonLabel = document.getElementById('per-person-label');

        function updateSplit() {
            var isByGuests = document.querySelector('input.split-type[value="BY_GUESTS"]').checked;
            formSplitType.value = isByGuests ? 'BY_GUESTS' : 'FULL';
            if (isByGuests) {
                splitGuestsWrap.classList.remove('hidden');
                splitGuestsWrap.classList.add('flex');
                var n = parseInt(splitGuestsInput.value, 10) || 2;
                if (n < 2) n = 2;
                if (n > 20) n = 20;
                splitGuestsInput.value = n;
                formSplitGuests.value = n;
                var per = Math.floor(totalAmount / n);
                perPersonLabel.textContent = '1인당 ' + per.toLocaleString() + '원';
            } else {
                splitGuestsWrap.classList.add('hidden');
                splitGuestsWrap.classList.remove('flex');
                formSplitGuests.value = '1';
                perPersonLabel.textContent = '';
            }
        }
        splitTypeRadios.forEach(function(r){ r.addEventListener('change', updateSplit); });
        if (splitGuestsInput) splitGuestsInput.addEventListener('input', updateSplit);
        if (splitGuestsInput) splitGuestsInput.addEventListener('change', updateSplit);
        updateSplit();
    })();
    </script>
</body>
</html>