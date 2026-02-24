<?php
// menu_detail.php - 장바구니 담기 기능 통합 버전
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

$menu_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order_type = isset($_GET['order_type']) ? $_GET['order_type'] : 'dinein';
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'ko';

$price_col = ($order_type == 'pickup') ? "price_pickup" : (($order_type == 'delivery') ? "price_delivery" : "price");

$stmt = $pdo->prepare("SELECT m.*, t.menu_name, t.description FROM menus m LEFT JOIN menu_translations t ON m.id = t.menu_id AND t.lang_code = ? WHERE m.id = ?");
$stmt->execute([$lang, $menu_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: menu.php?order_type=' . urlencode($order_type) . '&lang=' . urlencode($lang));
    exit;
}

$sql_opts = "SELECT g.*, i.id as item_id, i.item_name_ko, i.price_dinein, i.price_pickup, i.price_delivery 
             FROM menu_option_groups mog
             JOIN option_groups g ON mog.group_id = g.id
             JOIN option_items i ON g.id = i.group_id
             WHERE mog.menu_id = ? AND i.is_available = 1 ORDER BY g.id ASC, i.id ASC";
$stmt_opts = $pdo->prepare($sql_opts);
$stmt_opts->execute([$menu_id]);
$options = [];
while($opt = $stmt_opts->fetch(PDO::FETCH_ASSOC)) {
    $gid = $opt['id'];
    if (!isset($options[$gid])) { $options[$gid] = ['name' => $opt['group_name_ko'], 'required' => $opt['is_required'], 'max' => $opt['max_select'], 'items' => []]; }
    $opt_price = $opt["price_$order_type"] ?? $opt['price_dinein'];
    $options[$gid]['items'][] = ['id' => $opt['item_id'], 'name' => $opt['item_name_ko'], 'price' => $opt_price];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Detail - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; -webkit-tap-highlight-color: transparent; }
        .option-check:checked + label { border-color: #38bdf8; background-color: #f0f9ff; }
    </style>
</head>
<body class="bg-sky-50/50 min-h-screen pb-36">
    <div class="relative h-56 bg-sky-100">
        <button onclick="history.back()" class="absolute top-5 left-5 w-10 h-10 bg-white/95 border border-sky-100 rounded-full flex items-center justify-center shadow-sm z-20 text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <?php if(!empty($item['image_url'])): ?>
        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="w-full h-full object-cover" alt="">
        <?php else: ?>
        <div class="w-full h-full flex items-center justify-center text-sky-200 font-black italic text-2xl uppercase">Alrira</div>
        <?php endif; ?>
    </div>

    <div class="bg-white px-6 py-8 rounded-t-[2.5rem] -mt-8 relative z-10 shadow-sm border-t border-sky-100">
        <div class="flex justify-between items-start mb-3">
            <h2 class="text-2xl font-black text-slate-700 tracking-tighter"><?php echo htmlspecialchars($item['menu_name']); ?></h2>
            <div class="text-sky-600 font-black text-xl italic"><?php echo number_format($item[$price_col]); ?>원</div>
        </div>
        <p class="text-slate-500 text-sm mb-6"><?php echo htmlspecialchars($item['description'] ?? ''); ?></p>

        <!-- 수량 선택 -->
        <div class="mb-8">
            <h4 class="text-sm font-black text-slate-600 mb-3">수량</h4>
            <div class="inline-flex items-center bg-sky-50 border border-sky-100 rounded-2xl p-1">
                <button type="button" id="qty-minus" class="w-10 h-10 flex items-center justify-center rounded-xl text-slate-600 hover:bg-sky-100 font-bold">−</button>
                <span id="qty-display" class="w-12 text-center font-black text-slate-800 text-lg">1</span>
                <button type="button" id="qty-plus" class="w-10 h-10 flex items-center justify-center rounded-xl text-slate-600 hover:bg-sky-100 font-bold">+</button>
            </div>
        </div>

        <div class="space-y-8">
            <?php foreach($options as $gid => $group): ?>
            <section>
                <div class="flex justify-between items-center mb-3">
                    <h4 class="text-base font-black text-slate-700"><?php echo htmlspecialchars($group['name']); ?></h4>
                    <?php if($group['required']): ?><span class="bg-rose-100 text-rose-600 px-2 py-0.5 rounded-lg text-[9px] font-black uppercase">필수</span><?php endif; ?>
                </div>
                <div class="space-y-2">
                    <?php foreach($group['items'] as $it): ?>
                    <div class="relative option-row" data-id="<?php echo $it['id']; ?>" data-price="<?php echo $it['price']; ?>">
                        <input type="checkbox"
                               id="opt-<?php echo $it['id']; ?>"
                               data-price="<?php echo $it['price']; ?>"
                               data-id="<?php echo $it['id']; ?>"
                               class="option-check hidden"
                               onchange="onOptionToggle(<?php echo $it['id']; ?>)">
                        <label for="opt-<?php echo $it['id']; ?>" class="flex justify-between items-center p-4 bg-sky-50/50 border border-sky-100 rounded-2xl cursor-pointer transition-all hover:border-sky-200">
                            <span class="font-bold text-slate-700"><?php echo htmlspecialchars($it['name']); ?></span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-sky-600">+<?php echo number_format($it['price']); ?>원</span>
                                <div class="flex items-center bg-white border border-sky-100 rounded-xl px-1 py-0.5 text-xs font-bold text-slate-700">
                                    <button type="button"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-500 hover:bg-sky-100"
                                            onclick="changeOptionQty(<?php echo $it['id']; ?>, -1)">
                                        −
                                    </button>
                                    <span class="w-6 text-center" id="opt-qty-display-<?php echo $it['id']; ?>">0</span>
                                    <button type="button"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-sky-200 text-sky-700 hover:bg-sky-300"
                                            onclick="changeOptionQty(<?php echo $it['id']; ?>, 1)">
                                        +
                                    </button>
                                </div>
                            </div>
                        </label>
                        <input type="hidden"
                               id="opt-qty-<?php echo $it['id']; ?>"
                               class="option-qty"
                               data-id="<?php echo $it['id']; ?>"
                               value="0">
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="fixed bottom-0 left-0 w-full p-5 bg-white/95 backdrop-blur border-t border-sky-100 z-50">
        <button type="button" onclick="addToCart()" class="w-full max-w-md mx-auto h-16 bg-sky-300 text-slate-800 rounded-[2rem] shadow-sm border border-sky-200 flex items-center justify-between px-8 transition transform active:scale-[0.98]">
            <span class="font-black text-sm uppercase tracking-tight">장바구니 담기</span>
            <span class="font-black text-xl italic tracking-tighter"><span id="total-display"><?php echo number_format($item[$price_col]); ?></span>원</span>
        </button>
    </div>

    <script>
        const basePrice = <?php echo (int)$item[$price_col]; ?>;
        let mainQty = 1;

        function getMainQty() { return mainQty; }
        function setMainQty(n) {
            if (n < 1) n = 1;
            mainQty = n;
            document.getElementById('qty-display').innerText = String(mainQty);
            updatePrice();
        }

        function updatePrice() {
            let total = basePrice * getMainQty();
            document.querySelectorAll('.option-row').forEach(row => {
                const price = parseInt(row.dataset.price || '0', 10);
                const id = row.dataset.id;
                const qtyInput = document.getElementById('opt-qty-' + id);
                const qty = qtyInput ? parseInt(qtyInput.value || '0', 10) : 0;
                if (qty > 0) total += price * qty;
            });
            document.getElementById('total-display').innerText = total.toLocaleString();
        }

        function onOptionToggle(id) {
            const checkbox = document.getElementById('opt-' + id);
            const qtyInput = document.getElementById('opt-qty-' + id);
            const qtyDisplay = document.getElementById('opt-qty-display-' + id);
            if (!checkbox || !qtyInput || !qtyDisplay) return;

            if (checkbox.checked) {
                if (parseInt(qtyInput.value || '0', 10) <= 0) {
                    qtyInput.value = '1';
                    qtyDisplay.innerText = '1';
                }
            } else {
                qtyInput.value = '0';
                qtyDisplay.innerText = '0';
            }
            updatePrice();
        }

        function changeOptionQty(id, delta) {
            const checkbox = document.getElementById('opt-' + id);
            const qtyInput = document.getElementById('opt-qty-' + id);
            const qtyDisplay = document.getElementById('opt-qty-display-' + id);
            if (!qtyInput || !qtyDisplay || !checkbox) return;

            let current = parseInt(qtyInput.value || '0', 10);
            if (isNaN(current)) current = 0;
            let next = current + delta;
            if (next < 0) next = 0;

            qtyInput.value = String(next);
            qtyDisplay.innerText = String(next);

            if (next <= 0) {
                checkbox.checked = false;
            } else {
                checkbox.checked = true;
            }
            updatePrice();
        }

        function addToCart() {
            const options = {};
            document.querySelectorAll('.option-row').forEach(row => {
                const id = row.dataset.id;
                const price = parseInt(row.dataset.price || '0', 10);
                const qtyInput = document.getElementById('opt-qty-' + id);
                const qty = qtyInput ? parseInt(qtyInput.value || '0', 10) : 0;
                if (qty > 0) options[id] = { price: price, qty: qty };
            });

            const formData = new FormData();
            formData.append('menu_id', '<?php echo $menu_id; ?>');
            formData.append('order_type', '<?php echo $order_type; ?>');
            formData.append('quantity', String(getMainQty()));
            Object.keys(options).forEach(key => {
                formData.append('options[' + key + '][price]', options[key].price);
                formData.append('options[' + key + '][qty]', options[key].qty);
            });

            fetch('cart_process.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (typeof alert !== 'undefined') alert('장바구니에 담겼습니다.');
                        location.href = 'menu.php?order_type=<?php echo urlencode($order_type); ?>&lang=<?php echo urlencode($lang); ?>';
                    } else {
                        if (typeof alert !== 'undefined') alert(data.message || '담기에 실패했습니다.');
                    }
                })
                .catch(function() {
                    if (typeof alert !== 'undefined') alert('오류가 발생했습니다.');
                });
        }

        document.getElementById('qty-minus').addEventListener('click', function() { setMainQty(mainQty - 1); });
        document.getElementById('qty-plus').addEventListener('click', function() { setMainQty(mainQty + 1); });
    </script>
</body>
</html>