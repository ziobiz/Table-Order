<?php
// admin_store_stats.php - 가맹점 전용 통계
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

$my_store_id = $_SESSION['store_id'];

// [데이터] 최근 7일간 일별 매출 추이
$sql_weekly = "SELECT DATE(created_at) as date, SUM(total_amount) as daily_sales 
               FROM orders 
               WHERE store_id = ? AND status = 'completed' 
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
               GROUP BY DATE(created_at) ORDER BY date ASC";
$stmt = $pdo->prepare($sql_weekly);
$stmt->execute([$my_store_id]);
$weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// [데이터] 베스트 메뉴 TOP 5
$sql_best = "SELECT t.menu_name, SUM(oi.quantity) as count 
             FROM order_items oi
             JOIN menu_translations t ON oi.menu_id = t.menu_id AND t.lang_code = 'ko'
             JOIN orders o ON oi.order_id = o.id
             WHERE o.store_id = ? AND o.status = 'completed'
             GROUP BY oi.menu_id ORDER BY count DESC LIMIT 5";
$stmt_best = $pdo->prepare($sql_best);
$stmt_best->execute([$my_store_id]);
$best_menus = $stmt_best->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Store Statistics - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 p-8">
    <div class="max-w-[96rem] mx-auto space-y-8">
        <h2 class="text-2xl font-black italic text-slate-800 uppercase tracking-tighter">Store Performance</h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <div class="lg:col-span-2 bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100">
                <h4 class="text-xs font-black text-slate-400 uppercase mb-6 tracking-widest">Weekly Revenue Trend</h4>
                <canvas id="salesChart" height="150"></canvas>
            </div>

            <div class="lg:col-span-3 bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100">
                <h4 class="text-xs font-black text-slate-400 uppercase mb-6 tracking-widest">Best Selling Menus</h4>
                <div class="space-y-4">
                    <?php foreach($best_menus as $idx => $m): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <span class="w-6 h-6 flex items-center justify-center bg-sky-500 text-white text-[10px] font-black rounded-lg"><?php echo $idx+1; ?></span>
                            <span class="text-sm font-bold text-slate-700"><?php echo $m['menu_name']; ?></span>
                        </div>
                        <span class="text-xs font-black text-slate-400"><?php echo $m['count']; ?> 판매</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($weekly_data, 'date')); ?>,
                datasets: [{
                    label: 'Daily Sales',
                    data: <?php echo json_encode(array_column($weekly_data, 'daily_sales')); ?>,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14, 165, 233, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 4
                }]
            },
            options: { plugins: { legend: { display: false } } }
        });
    </script>
</body>
</html>