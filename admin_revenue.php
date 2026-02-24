<?php
// admin_revenue.php - 슈퍼관리자 전용 매출 및 정산 대시보드 (전체 소스)
include 'db_config.php';
include 'common.php';

// 1. 권한 체크 (슈퍼관리자가 아니면 접근 차단)
$admin_role = $_SESSION['admin_role'] ?? 'PARTTIME';
if ($admin_role !== 'SUPERADMIN') {
    echo "<script>alert('최고관리자만 접근 가능한 페이지입니다.'); location.href='admin_menu_list.php';</script>";
    exit;
}

$store_id = 1;
$store = $pdo->query("SELECT * FROM stores WHERE id = $store_id")->fetch();
$currency_symbol = get_currency_symbol($store['currency_code']);

// 2. 매출 데이터 집계 (매장/픽업/배달 구분)
$sql_stats = "SELECT 
                s.order_type, 
                COUNT(o.id) as order_count, 
                SUM(o.total_food_amount) as food_sales,
                SUM(o.delivery_fee) as total_delivery_fee
              FROM orders o
              JOIN order_sessions s ON o.session_id = s.id
              WHERE o.payment_status = 'PAID'
              GROUP BY s.order_type";
$stats = $pdo->query($sql_stats)->fetchAll(PDO::FETCH_ASSOC);

$total_revenue = 0;
$sales_by_type = ['DINEIN' => 0, 'PICKUP' => 0, 'DELIVERY' => 0];
$total_delivery_income = 0;

foreach ($stats as $row) {
    $sales_by_type[$row['order_type']] = $row['food_sales'];
    $total_revenue += $row['food_sales'];
    $total_delivery_income += $row['total_delivery_fee'];
}

// 3. 기사 정산 예정액 계산 (샘플로 전체 기사 합계)
$sql_rider_pay = "SELECT 
                    SUM(delivery_fee) as raw_fee 
                  FROM orders 
                  WHERE payment_status = 'PAID' AND rider_id IS NOT NULL";
$rider_raw = $pdo->query($sql_rider_pay)->fetchColumn();
// 수수료 및 부가세 제외 로직 (기사 평균 설정값 기준 10% 수수료, 수수료의 10% VAT 가정)
$rider_commission = $rider_raw * 0.1;
$rider_vat = $rider_commission * 0.1;
$rider_final_payout = $rider_raw - ($rider_commission + $rider_vat);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>매출 통계 - SuperAdmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 p-6">
    <div class="max-w-[96rem] mx-auto">
        
        <div class="flex justify-between items-end mb-8">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">💰 매출 및 정산 분석</h1>
                <p class="text-slate-500 font-bold">최고관리자 전용 대시보드</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400 uppercase font-black">Today's Total Revenue</p>
                <p class="text-3xl font-black text-indigo-600"><?php echo $currency_symbol . number_format($total_revenue + $total_delivery_income); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <p class="text-xs font-bold text-slate-400 mb-1">매장 식사 매출</p>
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($sales_by_type['DINEIN']); ?></p>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <p class="text-xs font-bold text-slate-400 mb-1">매장 픽업 매출</p>
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($sales_by_type['PICKUP']); ?></p>
            </div>
            <div class="bg-blue-600 p-6 rounded-3xl shadow-lg text-white">
                <p class="text-xs font-bold opacity-80 mb-1 text-white">배달 음식 매출</p>
                <p class="text-2xl font-black"><?php echo number_format($sales_by_type['DELIVERY']); ?></p>
            </div>
            <div class="bg-emerald-500 p-6 rounded-3xl shadow-lg text-white">
                <p class="text-xs font-bold opacity-80 mb-1 text-white">배달비 수익 (Total)</p>
                <p class="text-2xl font-black"><?php echo number_format($total_delivery_income); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-white rounded-[2rem] shadow-xl p-8 border border-slate-100">
                <h3 class="text-lg font-black text-slate-800 mb-6 flex items-center">
                    <span class="mr-2 text-2xl">🛵</span> 기사 정산 현황
                </h3>
                <div class="space-y-4 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">총 배달비 발생액</span>
                        <span class="font-bold"><?php echo number_format($rider_raw); ?></span>
                    </div>
                    <div class="flex justify-between text-red-500">
                        <span>플랫폼 중개수수료 (-)</span>
                        <span><?php echo number_format($rider_commission); ?></span>
                    </div>
                    <div class="flex justify-between text-red-500 border-b pb-4">
                        <span>수수료 부가세 (-)</span>
                        <span><?php echo number_format($rider_vat); ?></span>
                    </div>
                    <div class="flex justify-between items-center pt-2">
                        <span class="text-lg font-black text-slate-800">기사 실지급 합계</span>
                        <span class="text-2xl font-black text-emerald-600"><?php echo number_format($rider_final_payout); ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900 rounded-[2rem] shadow-xl p-8 text-white">
                <h3 class="text-lg font-black text-indigo-400 mb-6 uppercase tracking-widest">Sales Structure</h3>
                <div class="space-y-6">
                    <?php 
                    $total_sum = array_sum($sales_by_type);
                    foreach(['DINEIN'=>'매장 식사', 'PICKUP'=>'매장 픽업', 'DELIVERY'=>'배달 주문'] as $type => $label): 
                        $pct = ($total_sum > 0) ? ($sales_by_type[$type] / $total_sum) * 100 : 0;
                    ?>
                    <div>
                        <div class="flex justify-between text-xs mb-2">
                            <span class="font-bold"><?php echo $label; ?></span>
                            <span class="text-indigo-300"><?php echo round($pct, 1); ?>%</span>
                        </div>
                        <div class="w-full bg-slate-800 h-3 rounded-full overflow-hidden">
                            <div class="bg-indigo-500 h-full" style="width: <?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>