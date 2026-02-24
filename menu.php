<?php
// menu.php - 6개 국어(인도네시아어 포함) 지원 및 차등 가격 메뉴판
ob_start();
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// 1. 판매 방식 및 언어 설정
$order_type = isset($_GET['order_type']) ? $_GET['order_type'] : 'dinein';
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'ko';
$filter_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// 1-1. 장바구니 항목 삭제 (취소)
if (isset($_GET['remove']) && is_string($_GET['remove'])) {
    $remove_key = $_GET['remove'];
    if (isset($_SESSION['cart'][$remove_key])) {
        unset($_SESSION['cart'][$remove_key]);
    }
    $redirect = 'menu.php?order_type=' . urlencode($order_type) . '&lang=' . urlencode($lang);
    if ($filter_category_id > 0) $redirect .= '&category_id=' . $filter_category_id;
    header('Location: ' . $redirect);
    exit;
}

// 2. 가격 컬럼 결정 (사장님의 3단 가격 정책)
$price_col = "price"; 
if($order_type == 'pickup') $price_col = "price_pickup";
if($order_type == 'delivery') $price_col = "price_delivery";

$store_id = isset($_SESSION['store_id']) ? (int)$_SESSION['store_id'] : 1;
$menu_format_id = 1;
try {
    $fmt = $pdo->prepare("SELECT menu_format_id FROM stores WHERE id = ?");
    $fmt->execute([$store_id]);
    $row = $fmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['menu_format_id'])) $menu_format_id = (int)$row['menu_format_id'];
} catch (PDOException $e) {}

$categories = [];
$menus = [];
$reviews = [];

try {
    // 3. 카테고리 로드 (본사 포맷 기준: menu_format_id)
    $cat_sql = "SELECT c.id, IFNULL(ct.category_name, c.category_name) as category_name 
                FROM categories c 
                LEFT JOIN category_translations ct ON c.id = ct.category_id AND ct.lang_code = :lang
                ORDER BY c.sort_order ASC";
    try {
        $pdo->query("SELECT menu_format_id FROM categories LIMIT 1");
        $cat_sql = "SELECT c.id, IFNULL(ct.category_name, c.category_name) as category_name 
                    FROM categories c 
                    LEFT JOIN category_translations ct ON c.id = ct.category_id AND ct.lang_code = :lang
                    WHERE c.menu_format_id = :menu_format_id
                    ORDER BY c.sort_order ASC";
    } catch (PDOException $e) {
        try {
            $pdo->query("SELECT store_id FROM categories LIMIT 1");
            $cat_sql = "SELECT c.id, IFNULL(ct.category_name, c.category_name) as category_name FROM categories c LEFT JOIN category_translations ct ON c.id = ct.category_id AND ct.lang_code = :lang WHERE c.store_id = :store_id ORDER BY c.sort_order ASC";
        } catch (PDOException $e2) {}
    }
    $cat_stmt = $pdo->prepare($cat_sql);
    $cat_params = ['lang' => $lang];
    if (strpos($cat_sql, 'menu_format_id') !== false) $cat_params['menu_format_id'] = $menu_format_id;
    elseif (strpos($cat_sql, 'store_id') !== false) $cat_params['store_id'] = $store_id;
    $cat_stmt->execute($cat_params);
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. 메뉴 로드 (본사 포맷 기준) + 가맹점 오버라이드 반영
    $sql = "SELECT m.*, t.menu_name, t.description 
            FROM menus m 
            LEFT JOIN menu_translations t ON m.id = t.menu_id AND t.lang_code = :lang
            WHERE m.menu_format_id = :menu_format_id AND m.is_{$order_type} = 1
            ORDER BY m.id DESC";
    try {
        $pdo->query("SELECT menu_format_id FROM menus LIMIT 1");
    } catch (PDOException $e) {
        $sql = "SELECT m.*, t.menu_name, t.description FROM menus m LEFT JOIN menu_translations t ON m.id = t.menu_id AND t.lang_code = :lang WHERE m.store_id = :store_id AND m.is_available = 1 AND m.is_{$order_type} = 1 ORDER BY m.id DESC";
    }
    $stmt = $pdo->prepare($sql);
    $params = ['lang' => $lang];
    if (strpos($sql, 'menu_format_id') !== false) { $params['menu_format_id'] = $menu_format_id; } else { $params['store_id'] = $store_id; }
    $stmt->execute($params);
    $menus_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $overrides = [];
    try {
        $ostmt = $pdo->prepare("SELECT menu_id, price_override, price_pickup_override, price_delivery_override, is_available_override FROM store_menu_overrides WHERE store_id = ?");
        $ostmt->execute([$store_id]);
        while ($o = $ostmt->fetch(PDO::FETCH_ASSOC)) { $overrides[$o['menu_id']] = $o; }
    } catch (PDOException $e) {}

    foreach ($menus_raw as $m) {
        $ov = $overrides[$m['id']] ?? null;
        $m['price'] = $ov && $ov['price_override'] !== null ? (int)$ov['price_override'] : (int)$m['price'];
        $m['price_pickup'] = $ov && $ov['price_pickup_override'] !== null ? (int)$ov['price_pickup_override'] : (int)$m['price_pickup'];
        $m['price_delivery'] = $ov && $ov['price_delivery_override'] !== null ? (int)$ov['price_delivery_override'] : (int)$m['price_delivery'];
        $eff_avail = $ov && $ov['is_available_override'] !== null ? (int)$ov['is_available_override'] : (int)$m['is_available'];
        if ($eff_avail) $menus[] = $m;
    }

    // 4-1. 카테고리 필터 (선택 시 해당 카테고리만 표시)
    if ($filter_category_id > 0) {
        $menus = array_values(array_filter($menus, function($m) use ($filter_category_id) {
            return (int)($m['category_id'] ?? 0) === $filter_category_id;
        }));
    }

    // 5. 최근 리뷰 5개 (해당 매장 기준)
    $rev_sql = "SELECT r.*, s.store_name 
                FROM reviews r 
                JOIN stores s ON r.store_id = s.id 
                WHERE r.store_id = ?
                ORDER BY r.id DESC 
                LIMIT 5";
    $rev_stmt = $pdo->prepare($rev_sql);
    $rev_stmt->execute([$store_id]);
    $reviews = $rev_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = $e->getMessage();
}

// 5-1. 장바구니 리스트/수량/금액 집계 (세션 기반, 리스트로 표시·항목별 취소용)
$cart_list = [];
$cart_count = 0;
$cart_total = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cart_key => $line) {
        $qty = (int)($line['quantity'] ?? 0);
        if ($qty <= 0) continue;
        $menu_id_in_cart = (int)($line['menu_id'] ?? 0);
        if ($menu_id_in_cart <= 0) continue;

        $ot = $line['order_type'] ?? $order_type;
        $pcol = 'price';
        if ($ot === 'pickup')   $pcol = 'price_pickup';
        if ($ot === 'delivery') $pcol = 'price_delivery';

        $base_price = 0;
        $menu_name = '';
        try {
            $pstmt = $pdo->prepare("SELECT m.{$pcol}, IFNULL(t.menu_name, m.id) as menu_name FROM menus m LEFT JOIN menu_translations t ON m.id = t.menu_id AND t.lang_code = ? WHERE m.id = ?");
            $pstmt->execute([$lang, $menu_id_in_cart]);
            $row = $pstmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $base_price = (int)$row[$pcol];
                $menu_name = $row['menu_name'] ?: 'Menu #' . $menu_id_in_cart;
            }
        } catch (Exception $e) {}

        $opt_sum = 0;
        $opt_names = [];
        if (!empty($line['options']) && is_array($line['options'])) {
            foreach ($line['options'] as $oid => $oval) {
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

                $opt_sum += $unit_price * $opt_qty;

                try {
                    $ostmt = $pdo->prepare("SELECT item_name_ko FROM option_items WHERE id = ?");
                    $ostmt->execute([$oid]);
                    $n = $ostmt->fetchColumn();
                    if ($n) {
                        $label = $n;
                        if ($opt_qty > 1) {
                            $label .= ' x' . $opt_qty;
                        }
                        $opt_names[] = $label;
                    }
                } catch (Exception $e) {}
            }
        }
        $options_text = empty($opt_names) ? '' : ' ('. implode(', ', $opt_names) . ')';
        $line_total = ($base_price + $opt_sum) * $qty;

        $cart_list[] = [
            'key' => $cart_key,
            'menu_name' => $menu_name,
            'options_text' => $options_text,
            'quantity' => $qty,
            'line_total' => $line_total,
        ];
        $cart_count += $qty;
        $cart_total += $line_total;
    }
}

// 6. 공통 UI 텍스트 다국어 설정 (인도네시아어 추가)
$ui_text = [
    // 하단 장바구니 버튼: 한국어는 '주문완료 하기'로 직관적으로 표기
    'ko' => ['all' => '전체', 'order_list' => '주문완료 하기', 'sold_out' => '품절', 'none' => '상품이 없습니다.'],
    'en' => ['all' => 'ALL', 'order_list' => 'ORDER LIST', 'sold_out' => 'SOLD OUT', 'none' => 'No items available.'],
    'th' => ['all' => 'ทั้งหมด', 'order_list' => 'รายการ 주문', 'sold_out' => '품절', 'none' => 'ไม่มีสินค้า'],
    'ja' => ['all' => 'すべて', 'order_list' => '注文履歴', 'sold_out' => '売り切れ', 'none' => '商品がありません'],
    'vi' => ['all' => 'Tất cả', 'order_list' => 'Đơn hàng', 'sold_out' => 'Hết hàng', 'none' => 'Không có sản phẩm'],
    'id' => ['all' => 'Semua', 'order_list' => 'Daftar Pesanan', 'sold_out' => 'Habis', 'none' => 'Tidak ada produk']
];
$cur_ui = $ui_text[$lang] ?? $ui_text['ko'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Menu - Alrira Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; -webkit-tap-highlight-color: transparent; }
        .hide-scroll::-webkit-scrollbar { display: none; }
        .menu-card { transition: all 0.2s ease-out; }
        .menu-card:active { transform: scale(0.97); opacity: 0.9; }
    </style>
</head>
<body class="bg-sky-50/40 min-h-screen pb-32">

    <header class="bg-white/95 backdrop-blur-md p-4 flex justify-between items-center shadow-sm border-b border-sky-100/80 sticky top-0 z-50">
        <h1 class="text-xl font-black italic text-slate-700 tracking-tighter cursor-pointer" onclick="location.href='index.php'">ALRIRA</h1>
        <div class="flex items-center space-x-2">
            <select onchange="location.href='menu.php?order_type=<?php echo $order_type; ?>&lang='+this.value" 
                    class="bg-sky-50 border border-sky-100 text-[11px] font-bold text-slate-600 p-2 rounded-xl outline-none cursor-pointer">
                <option value="ko" <?php echo $lang=='ko'?'selected':''; ?>>한국어</option>
                <option value="en" <?php echo $lang=='en'?'selected':''; ?>>English</option>
                <option value="id" <?php echo $lang=='id'?'selected':''; ?>>Bahasa Indonesia</option>
                <option value="th" <?php echo $lang=='th'?'selected':''; ?>>ไทย</option>
                <option value="ja" <?php echo $lang=='ja'?'selected':''; ?>>日本語</option>
                <option value="vi" <?php echo $lang=='vi'?'selected':''; ?>>Tiếng Việt</option>
            </select>
            <div class="px-3 py-1 bg-sky-200 text-slate-700 text-[10px] font-black rounded-lg uppercase">
                <?php echo $order_type; ?>
            </div>
        </div>
    </header>

    <nav class="bg-white/80 border-b border-sky-100 sticky top-[61px] z-40 overflow-x-auto hide-scroll px-4">
        <div class="flex space-x-6 py-4">
            <a href="menu.php?order_type=<?php echo urlencode($order_type); ?>&lang=<?php echo urlencode($lang); ?>" class="text-sm font-black pb-1 whitespace-nowrap border-b-2 <?php echo $filter_category_id === 0 ? 'text-sky-600 border-sky-400' : 'text-slate-400 border-transparent hover:text-slate-600'; ?>"><?php echo $cur_ui['all']; ?></a>
            <?php foreach($categories as $cat): ?>
            <a href="menu.php?order_type=<?php echo urlencode($order_type); ?>&lang=<?php echo urlencode($lang); ?>&category_id=<?php echo (int)$cat['id']; ?>" class="text-sm font-bold pb-1 whitespace-nowrap border-b-2 <?php echo $filter_category_id === (int)$cat['id'] ? 'text-sky-600 border-sky-400' : 'text-slate-400 border-transparent hover:text-slate-600'; ?>">
                <?php echo htmlspecialchars($cat['category_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <main class="p-4 max-w-md mx-auto space-y-4 mt-2">
        <?php if(empty($menus)): ?>
            <div class="text-center py-32">
                <p class="text-slate-400 font-bold italic text-lg"><?php echo $cur_ui['none']; ?></p>
            </div>
        <?php else: foreach($menus as $m): 
            // 실시간 품절 체크 (재고 연동)
            $is_sold_out = ($m['is_available'] == 2);
            if($m['daily_limit'] > 0 && $m['current_stock'] >= $m['daily_limit']) $is_sold_out = true;
        ?>
        <div class="menu-card bg-white rounded-[2rem] shadow-sm border border-sky-100 overflow-hidden flex relative" 
             onclick="<?php echo $is_sold_out ? '' : "location.href='menu_detail.php?id={$m['id']}&order_type={$order_type}&lang={$lang}'"; ?>">
            
            <div class="w-32 h-32 bg-sky-50 flex-shrink-0 relative">
                <?php if(!empty($m['image_url'])): ?>
                    <img src="<?php echo $m['image_url']; ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-slate-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                <?php endif; ?>
                
                <?php if($is_sold_out): ?>
                <div class="absolute inset-0 bg-white/85 backdrop-blur-[2px] flex items-center justify-center z-10">
                    <span class="bg-slate-500 text-white px-3 py-1 rounded-lg text-[10px] font-black uppercase italic tracking-tighter">
                        <?php echo $cur_ui['sold_out']; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <div class="p-4 flex flex-col justify-between flex-grow">
                <div>
                    <h3 class="font-black text-slate-800 text-base leading-tight">
                        <?php echo htmlspecialchars($m['menu_name'] ?? 'No Name'); ?>
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                        <?php echo htmlspecialchars($m['description'] ?? ''); ?>
                    </p>
                </div>
                <div class="flex justify-between items-end">
                    <span class="text-sky-600 font-black text-lg italic tracking-tighter">
                        <?php echo number_format($m[$price_col] ?? 0); ?><span class="text-[10px] not-italic ml-0.5">원</span>
                    </span>
                    <div class="w-9 h-9 bg-sky-200 rounded-2xl flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($reviews)): ?>
        <section class="mt-10 pt-6 border-t border-sky-100 space-y-3">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Latest Reviews</h3>
            <?php foreach($reviews as $rev): ?>
            <div class="bg-white rounded-2xl border border-sky-100 p-4 shadow-sm">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-[11px] font-bold text-slate-500">
                        <?php echo htmlspecialchars($rev['guest_name'] ?? 'Member'); ?>
                    </span>
                    <span class="text-[11px] font-black text-amber-400">
                        <?php echo str_repeat('★', (int)$rev['rating']); ?>
                    </span>
                </div>
                <p class="text-[12px] text-slate-600 leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($rev['content'])); ?>
                </p>
                <p class="text-[9px] text-slate-400 mt-1">
                    <?php echo date('Y-m-d', strtotime($rev['created_at'])); ?>
                </p>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- 주문 내역 리스트 (장바구니가 있을 때만 표시, 항목별 취소 가능) -->
    <?php if (!empty($cart_list)): ?>
    <div class="fixed left-0 right-0 bottom-28 z-40 px-4 max-w-md mx-auto pointer-events-auto">
        <div class="bg-white rounded-2xl shadow-lg border border-sky-100 overflow-hidden max-h-56 overflow-y-auto">
            <div class="p-3 border-b border-slate-100 flex justify-between items-center sticky top-0 bg-white z-10">
                <span class="text-xs font-black text-slate-500 uppercase tracking-widest">주문 내역</span>
                <span class="text-[10px] text-slate-400"><?php echo count($cart_list); ?>종 · <?php echo (int)$cart_count; ?>개</span>
            </div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($cart_list as $item): ?>
                <li class="flex justify-between items-center gap-3 p-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($item['menu_name']); ?><?php echo htmlspecialchars($item['options_text']); ?></p>
                        <p class="text-xs text-slate-500 mt-0.5"><?php echo (int)$item['quantity']; ?>개 · <span class="font-black text-sky-600"><?php echo number_format($item['line_total']); ?>원</span></p>
                    </div>
                    <a href="menu.php?order_type=<?php echo urlencode($order_type); ?>&lang=<?php echo urlencode($lang); ?>&remove=<?php echo urlencode($item['key']); ?><?php echo $filter_category_id > 0 ? '&category_id='.$filter_category_id : ''; ?>" class="shrink-0 w-9 h-9 rounded-xl bg-rose-50 text-rose-500 border border-rose-100 flex items-center justify-center font-bold text-sm hover:bg-rose-100 transition" title="취소">×</a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- 하단: 수량·총액·주문완료 하기 (결제 확인 페이지로) -->
    <div class="fixed bottom-6 left-0 w-full px-6 z-50 pointer-events-none">
        <button onclick="location.href='order_review.php'" class="pointer-events-auto w-full max-w-md mx-auto bg-sky-300 text-slate-800 h-20 rounded-[2.5rem] shadow-lg border border-sky-200 flex items-center justify-between px-8 transform active:scale-95 transition-all">
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    <span class="absolute -top-2 -right-2 bg-white text-sky-600 text-[10px] font-black w-5 h-5 rounded-full flex items-center justify-center border-2 border-sky-300">
                        <?php echo (int)$cart_count; ?>
                    </span>
                </div>
                <span class="font-black tracking-widest text-xs uppercase"><?php echo $cur_ui['order_list']; ?></span>
            </div>
            <span class="text-slate-800 font-black text-xl italic tracking-tighter"><?php echo number_format($cart_total); ?>원</span>
        </button>
    </div>

</body>
</html>