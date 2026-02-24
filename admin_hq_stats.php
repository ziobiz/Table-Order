<?php
// admin_hq_stats.php - 본사 전용 전체 가맹점 비교 통계
include 'db_config.php';

// [데이터] 가맹점별 매출 비교 (막대 그래프용)
$sql_compare = "SELECT s.store_name, SUM(o.total_amount) as total 
                FROM stores s
                LEFT JOIN orders o ON s.id = o.store_id AND o.status = 'completed'
                GROUP BY s.id ORDER BY total DESC";
$store_comparison = $pdo->query($sql_compare)->fetchAll();

// [데이터] 브랜드 월별 매출 추이 (최근 6개월)
$sql_monthly = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as sales 
                FROM orders WHERE status = 'completed' 
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY month ORDER BY month ASC";
$monthly_sales = $pdo->query($sql_monthly)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>HQ Intelligence - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-950 text-white p-8">
    <div class="max-w-[96rem] mx-auto space-y-10">
        <header>
            <h1 class="text-3xl font-black italic text-sky-400 uppercase tracking-tighter">HQ Business Intelligence</h1>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <div class="lg:col-span-1 bg-slate-900 p-10 rounded-[3rem] border border-slate-800 shadow-2xl">
                <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mb-8">Store Performance Comparison</h4>
                <canvas id="compareChart"></canvas>
            </div>

            <div class="lg:col-span-2 bg-slate-900 p-10 rounded-[3rem] border border-slate-800 shadow-2xl">
                <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mb-8">Monthly Brand Growth</h4>
                <canvas id="growthChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // 가맹점 비교 차트
        new Chart(document.getElementById('compareChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($store_comparison, 'store_name')); ?>,
                datasets: [{
                    label: 'Total Revenue',
                    data: <?php echo json_encode(array_column($store_comparison, 'total')); ?>,
                    backgroundColor: '#0ea5e9',
                    borderRadius: 10
                }]
            }
        });

        // 월별 성장 차트
        new Chart(document.getElementById('growthChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_sales, 'month')); ?>,
                datasets: [{
                    label: 'Monthly Revenue',
                    data: <?php echo json_encode(array_column($monthly_sales, 'sales')); ?>,
                    borderColor: '#10b981',
                    borderWidth: 5,
                    tension: 0.3
                }]
            }
        });
    </script>
</body>
</html>