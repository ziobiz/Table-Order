<?php
// admin_store_stats.php - 가맹점 전용 통계 (기간 필터, 채널별, 결제수단별, 메뉴별)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

if (!isset($_SESSION['store_id'])) {
    header("Location: login.php"); exit;
}
$my_store_id = (int)$_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? '';

// 기간 필터
$range = isset($_GET['range']) && in_array($_GET['range'], ['today', '7d', '30d'], true) ? $_GET['range'] : '7d';
$date_cond = " AND o.created_at >= ";
switch ($range) {
    case 'today':  $date_cond .= "CURDATE()"; break;
    case '7d':     $date_cond .= "DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; break;
    case '30d':    $date_cond .= "DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; break;
    default:       $date_cond .= "DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; break;
}

$currency_symbol = get_currency_symbol('KRW');

try {
    // 1. 최근 일별 매출 추이
    $sql_weekly = "SELECT DATE(o.created_at) AS date, SUM(o.total_amount) AS daily_sales 
                   FROM orders o 
                   WHERE o.store_id = ? AND o.status IN ('completed', 'paid') $date_cond
                   GROUP BY DATE(o.created_at) ORDER BY date ASC";
    $stmt = $pdo->prepare($sql_weekly);
    $stmt->execute([$my_store_id]);
    $weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 채널별 매출
    $sql_channel = "SELECT o.order_type, COUNT(o.id) AS cnt, IFNULL(SUM(o.total_amount), 0) AS amt
                    FROM orders o
                    WHERE o.store_id = ? AND o.status IN ('completed', 'paid') $date_cond
                    GROUP BY o.order_type";
    $stmt = $pdo->prepare($sql_channel);
    $stmt->execute([$my_store_id]);
    $channel_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sales_by_type = ['dinein' => 0, 'pickup' => 0, 'delivery' => 0];
    foreach ($channel_data as $r) {
        $t = strtolower($r['order_type']);
        $sales_by_type[$t] = (int)$r['amt'];
    }
    $total_sales = array_sum($sales_by_type);

    // 3. 결제 수단별
    $payment_breakdown = [];
    try {
        $sql_pm = "SELECT COALESCE(o.payment_method, 'CASH') AS pm, COUNT(o.id) AS cnt, IFNULL(SUM(o.total_amount), 0) AS amt
                   FROM orders o
                   WHERE o.store_id = ? AND o.status IN ('completed', 'paid') $date_cond
                   GROUP BY pm";
        $stmt = $pdo->prepare($sql_pm);
        $stmt->execute([$my_store_id]);
        $payment_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // 4. 베스트 메뉴 TOP 10 (수량 + 매출)
    $sql_best = "SELECT mt.menu_name, oi.menu_id,
                        SUM(oi.quantity) AS qty,
                        SUM(o.total_amount * oi.quantity / NULLIF((SELECT SUM(oi2.quantity) FROM order_items oi2 WHERE oi2.order_id = o.id), 0)) AS revenue
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id AND o.store_id = ? AND o.status IN ('completed', 'paid') $date_cond
                 JOIN menu_translations mt ON mt.menu_id = oi.menu_id AND mt.lang_code = 'ko'
                 GROUP BY oi.menu_id
                 ORDER BY revenue DESC
                 LIMIT 10";
    $stmt = $pdo->prepare($sql_best);
    $stmt->execute([$my_store_id]);
    $best_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $weekly_data = [];
    $sales_by_type = ['dinein' => 0, 'pickup' => 0, 'delivery' => 0];
    $total_sales = 0;
    $payment_breakdown = [];
    $best_menus = [];
    $db_error = $e->getMessage();
}

$pm_labels = ['CASH' => '현금', 'CARD' => '카드', 'MOBILE' => '모바일', 'POINT' => '포인트', 'GIFT_CARD' => '기프트카드', 'MIXED' => '혼합', 'OTHER' => '기타'];
$range_labels = ['today' => '오늘', '7d' => '최근 7일', '30d' => '최근 30일'];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>매장 통계 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-[96rem] mx-auto space-y-8">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tighter italic">매장 통계</h1>
                <p class="text-xs text-slate-500 font-bold mt-1"><?php echo htmlspecialchars($store_name); ?></p>
            </div>
            <div class="flex items-center gap-3">
                <form method="get" class="flex items-center gap-2">
                    <select name="range" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-sky-400">
                        <?php foreach ($range_labels as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo $range === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a href="store_dashboard.php" class="px-4 py-2 rounded-xl bg-sky-100 text-sky-700 font-black text-sm uppercase hover:bg-sky-200 border border-sky-200 transition">대시보드</a>
            </div>
        </header>

        <?php if (isset($db_error)): ?>
        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl border border-rose-200 text-sm font-bold"><?php echo htmlspecialchars($db_error); ?></div>
        <?php endif; ?>

        <!-- 요약 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">총 매출</p>
                <p class="text-2xl font-black text-slate-800"><?php echo $currency_symbol . number_format($total_sales); ?></p>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">매장 식사</p>
                <p class="text-xl font-black text-slate-800"><?php echo number_format($sales_by_type['dinein']); ?></p>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">픽업</p>
                <p class="text-xl font-black text-slate-800"><?php echo number_format($sales_by_type['pickup']); ?></p>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">배달</p>
                <p class="text-xl font-black text-slate-800"><?php echo number_format($sales_by_type['delivery']); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- 일별 매출 차트 -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100">
                <h4 class="text-xs font-black text-slate-400 uppercase mb-6 tracking-widest">일별 매출 추이</h4>
                <canvas id="salesChart" height="180"></canvas>
            </div>

            <!-- 결제 수단별 -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100">
                <h4 class="text-xs font-black text-slate-400 uppercase mb-6 tracking-widest">결제 수단별</h4>
                <?php if (empty($payment_breakdown)): ?>
                <p class="text-sm text-slate-400 font-bold">결제 수단 데이터 없음</p>
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

        <!-- 베스트 메뉴 TOP 10 -->
        <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100">
            <h4 class="text-xs font-black text-slate-400 uppercase mb-6 tracking-widest">베스트 메뉴 TOP 10</h4>
            <?php if (empty($best_menus)): ?>
            <p class="text-sm text-slate-400 font-bold">메뉴 매출 데이터 없음</p>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($best_menus as $idx => $m): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="w-7 h-7 flex items-center justify-center bg-sky-100 text-sky-600 text-xs font-black rounded-lg"><?php echo $idx + 1; ?></span>
                        <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($m['menu_name']); ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-black text-sky-600"><?php echo number_format($m['revenue']); ?>원</span>
                        <span class="text-[10px] text-slate-400 ml-2"><?php echo number_format($m['qty']); ?>개</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        const ctx = document.getElementById('salesChart');
        if (ctx) {
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($weekly_data, 'date')); ?>,
                    datasets: [{
                        label: '일별 매출',
                        data: <?php echo json_encode(array_column($weekly_data, 'daily_sales')); ?>,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    </script>
</body>
</html>
