<?php
// admin_revenue.php - 본사 매출·리포팅 대시보드 (orders 직접 사용, 기간 필터, 채널별·결제수단별·메뉴별)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('최고관리자만 접근 가능합니다.'); location.href='login.php';</script>";
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? ('id_' . $admin_id);
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$admin_login_at = (int)($_SESSION['admin_login_at'] ?? time());
$admin_page_title = '매출 리포팅';
$admin_page_subtitle = '채널별·결제수단별·메뉴별 매출, 기간 필터';
$header_locale = 'ko';
$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    include 'admin_header.php';
}

// 기간 필터 (today | 7d | 30d)
$range = isset($_GET['range']) && in_array($_GET['range'], ['today', '7d', '30d'], true) ? $_GET['range'] : '7d';
$store_filter = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0; // 0 = 전체

$date_cond = " AND o.created_at >= ";
switch ($range) {
    case 'today':  $date_cond .= "CURDATE()"; break;
    case '7d':     $date_cond .= "DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; break;
    case '30d':    $date_cond .= "DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; break;
    default:       $date_cond .= "DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; break;
}
$store_cond = $store_filter > 0 ? " AND o.store_id = " . $store_filter : "";

$currency_symbol = get_currency_symbol('KRW');

try {
    // 1. 채널별 매출 (order_type: dinein, pickup, delivery)
    $sql_channel = "SELECT 
                        o.order_type,
                        COUNT(o.id) AS order_count,
                        IFNULL(SUM(o.total_amount), 0) AS sales
                    FROM orders o
                    WHERE o.status IN ('completed', 'paid') $date_cond $store_cond
                    GROUP BY o.order_type";
    $channel_rows = $pdo->query($sql_channel)->fetchAll(PDO::FETCH_ASSOC);

    $sales_by_type = ['dinein' => 0, 'pickup' => 0, 'delivery' => 0];
    $order_count_by_type = ['dinein' => 0, 'pickup' => 0, 'delivery' => 0];
    foreach ($channel_rows as $r) {
        $t = strtolower($r['order_type']);
        $sales_by_type[$t] = (int)$r['sales'];
        $order_count_by_type[$t] = (int)$r['order_count'];
    }
    $total_revenue = array_sum($sales_by_type);

    // 2. 배달비 수익 (deliveries 테이블)
    $total_delivery_fee = 0;
    try {
        $sql_delivery = "SELECT IFNULL(SUM(d.delivery_fee), 0) AS total
                         FROM deliveries d
                         JOIN orders o ON o.id = d.order_id
                         WHERE o.status IN ('completed', 'paid') $date_cond $store_cond";
        $total_delivery_fee = (int)$pdo->query($sql_delivery)->fetchColumn();
    } catch (PDOException $e) {}

    // 3. 결제 수단별 (payment_method 컬럼 있으면)
    $payment_breakdown = [];
    try {
        $sql_pm = "SELECT COALESCE(o.payment_method, 'CASH') AS pm, COUNT(o.id) AS cnt, IFNULL(SUM(o.total_amount), 0) AS amt
                   FROM orders o
                   WHERE o.status IN ('completed', 'paid') $date_cond $store_cond
                   GROUP BY pm";
        $payment_breakdown = $pdo->query($sql_pm)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // 4. 메뉴별 매출 (order_items: 주문금액을 수량 비율로 배분)
    $menu_sales = [];
    try {
        $sql_menu = "SELECT mt.menu_name, oi.menu_id,
                            SUM(oi.quantity) AS qty,
                            SUM(o.total_amount * oi.quantity / NULLIF((SELECT SUM(oi2.quantity) FROM order_items oi2 WHERE oi2.order_id = o.id), 0)) AS revenue
                     FROM order_items oi
                     JOIN orders o ON o.id = oi.order_id AND o.status IN ('completed', 'paid') $date_cond $store_cond
                     JOIN menu_translations mt ON mt.menu_id = oi.menu_id AND mt.lang_code = 'ko'
                     GROUP BY oi.menu_id
                     ORDER BY revenue DESC
                     LIMIT 15";
        $menu_sales = $pdo->query($sql_menu)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // 5. 가맹점 목록 (필터용)
    $stores = $pdo->query("SELECT id, store_name FROM stores ORDER BY store_name")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $sales_by_type = ['dinein' => 0, 'pickup' => 0, 'delivery' => 0];
    $total_revenue = 0;
    $total_delivery_fee = 0;
    $payment_breakdown = [];
    $menu_sales = [];
    $stores = [];
    $db_error = $e->getMessage();
}

$pm_labels = ['CASH' => '현금', 'CARD' => '카드', 'MOBILE' => '모바일', 'POINT' => '포인트', 'GIFT_CARD' => '기프트카드', 'MIXED' => '혼합', 'OTHER' => '기타'];
$range_labels = ['today' => '오늘', '7d' => '최근 7일', '30d' => '최근 30일'];
?>
<?php if (!$use_sidebar):
    include 'admin_card_header.php';
endif; ?>
    <div class="max-w-[96rem] space-y-10">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <form method="get" class="flex flex-wrap items-center gap-2">
                <select name="range" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <?php foreach ($range_labels as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $range === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="store_id" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <option value="0">전체 가맹점</option>
                    <?php foreach ($stores as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>" <?php echo $store_filter === (int)$s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['store_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (isset($db_error)): ?>
        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl border border-rose-200 text-sm font-bold"><?php echo htmlspecialchars($db_error); ?></div>
        <?php endif; ?>

        <!-- 요약 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">총 매출</p>
                <p class="text-2xl font-black text-slate-800"><?php echo $currency_symbol . number_format($total_revenue + $total_delivery_fee); ?></p>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">매장 식사</p>
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($sales_by_type['dinein']); ?></p>
                <p class="text-[10px] text-slate-400 mt-1"><?php echo $order_count_by_type['dinein']; ?>건</p>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">픽업</p>
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($sales_by_type['pickup']); ?></p>
                <p class="text-[10px] text-slate-400 mt-1"><?php echo $order_count_by_type['pickup']; ?>건</p>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">배달</p>
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($sales_by_type['delivery']); ?></p>
                <p class="text-[10px] text-slate-400 mt-1"><?php echo $order_count_by_type['delivery']; ?>건 · 배달비 <?php echo number_format($total_delivery_fee); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- 채널별 비율 -->
            <div class="bg-white rounded-[2rem] shadow-lg p-8 border border-slate-100">
                <h3 class="text-sm font-black text-slate-800 uppercase mb-6">채널별 매출 비율</h3>
                <?php 
                $total_sum = array_sum($sales_by_type);
                foreach (['dinein' => '매장 식사', 'pickup' => '픽업', 'delivery' => '배달'] as $t => $label):
                    $pct = $total_sum > 0 ? ($sales_by_type[$t] / $total_sum) * 100 : 0;
                ?>
                <div class="mb-4">
                    <div class="flex justify-between text-xs mb-2">
                        <span class="font-bold text-slate-600"><?php echo $label; ?></span>
                        <span class="text-slate-500"><?php echo round($pct, 1); ?>% · <?php echo number_format($sales_by_type[$t]); ?>원</span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                        <div class="bg-sky-400 h-full transition-all" style="width: <?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- 결제 수단별 -->
            <div class="bg-white rounded-[2rem] shadow-lg p-8 border border-slate-100">
                <h3 class="text-sm font-black text-slate-800 uppercase mb-6">결제 수단별</h3>
                <?php if (empty($payment_breakdown)): ?>
                <p class="text-sm text-slate-400 font-bold">결제 수단 데이터 없음 (payment_method 컬럼 확인)</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($payment_breakdown as $pm): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold text-slate-700"><?php echo $pm_labels[$pm['pm']] ?? $pm['pm']; ?></span>
                        <span class="text-sm font-black text-sky-600"><?php echo number_format($pm['amt']); ?>원 (<?php echo (int)$pm['cnt']; ?>건)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 메뉴별 매출 TOP 15 -->
        <div class="bg-white rounded-[2rem] shadow-lg p-8 border border-slate-100">
            <h3 class="text-sm font-black text-slate-800 uppercase mb-6">메뉴별 매출 TOP 15</h3>
            <?php if (empty($menu_sales)): ?>
            <p class="text-sm text-slate-400 font-bold">메뉴 매출 데이터 없음</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-black uppercase text-slate-500 tracking-wider">
                            <th class="pb-3 pr-4">순위</th>
                            <th class="pb-3 pr-4">메뉴명</th>
                            <th class="pb-3 pr-4 text-right">판매수량</th>
                            <th class="pb-3 pr-4 text-right">매출액</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menu_sales as $idx => $m): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-3 pr-4 font-bold text-slate-600"><?php echo $idx + 1; ?></td>
                            <td class="py-3 pr-4 font-bold text-slate-800"><?php echo htmlspecialchars($m['menu_name']); ?></td>
                            <td class="py-3 pr-4 text-right text-slate-600"><?php echo number_format($m['qty']); ?>개</td>
                            <td class="py-3 pr-4 text-right font-black text-sky-600"><?php echo number_format($m['revenue']); ?>원</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
<?php include 'admin_card_footer.php'; ?>
<?php endif; ?>
