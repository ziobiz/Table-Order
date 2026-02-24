<?php
// admin_menu_list.php - 통계가 통합된 가맹점주 메인
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// 보안 및 권한 체크
if (!isset($_SESSION['user_id']) || $_SESSION['admin_role'] === 'DELIVERY') {
    header("Location: login.php"); exit;
}

$my_store_id = $_SESSION['store_id'];

// [통계 데이터] 최근 7일 매출
$sql_weekly = "SELECT DATE(created_at) as date, SUM(total_amount) as daily_sales 
               FROM orders WHERE store_id = ? AND status = 'completed' 
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
               GROUP BY DATE(created_at) ORDER BY date ASC";
$stmt = $pdo->prepare($sql_weekly);
$stmt->execute([$my_store_id]);
$weekly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Alrira Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
    <nav class="bg-slate-900 text-white p-5 shadow-xl sticky top-0 z-50">
        <div class="max-w-[96rem] mx-auto flex justify-between items-center">
            <h1 class="text-xl font-black text-sky-400 italic cursor-pointer uppercase tracking-tighter" onclick="location.href='admin_menu_list.php'">Alrira Admin</h1>
            <div class="flex items-center space-x-6">
                <span class="text-[10px] font-black text-slate-500 uppercase"><?php echo $_SESSION['name']; ?> 님</span>
                <a href="admin_order_dashboard.php" class="text-xs font-bold text-slate-300">주문현황</a>
                <a href="logout.php" class="text-xs font-bold text-rose-400">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-[96rem] mx-auto p-8 space-y-10">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <div class="lg:col-span-2 bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6">Weekly Sales Trend</h4>
                <div class="h-64"><canvas id="storeChart"></canvas></div>
            </div>
            <div class="lg:col-span-2 bg-slate-900 p-8 rounded-[2.5rem] shadow-xl flex flex-col justify-center text-center">
                <p class="text-[10px] font-black text-sky-400 uppercase tracking-widest mb-2">Today's Revenue</p>
                <h3 class="text-4xl font-black text-white italic tracking-tighter">
                    ₩ <?php 
                        $today = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE store_id = $my_store_id AND DATE(created_at) = CURDATE() AND status='completed'")->fetchColumn();
                        echo number_format($today ?: 0);
                    ?>
                </h3>
                <button onclick="location.href='admin_order_dashboard.php'" class="mt-8 bg-sky-500 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg shadow-sky-900/20">Go to Kitchen</button>
            </div>
        </div>

        <div class="flex justify-between items-end">
            <h2 class="text-2xl font-black text-slate-800 tracking-tighter uppercase italic">Inventory Management</h2>
            <button onclick="location.href='admin_menu_edit.php'" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition hover:bg-sky-500">Add Item</button>
        </div>
        </main>

    <script>
        new Chart(document.getElementById('storeChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($weekly_stats, 'date')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($weekly_stats, 'daily_sales')); ?>,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14, 165, 233, 0.1)',
                    fill: true, tension: 0.4, borderWidth: 4, pointRadius: 0
                }]
            },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false } } } }
        });
    </script>
</body>
</html>