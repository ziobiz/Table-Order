<?php
// admin_hq_report.php - 본사 전용 통합 리포트
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안/권한 체크] 세션이 없거나 슈퍼관리자가 아니면 튕김 (긴급 계정 권한 포함)
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'SUPERADMIN') {
    echo "<script>alert('접근 권한이 없습니다. 관리자 로그인이 필요합니다.'); location.href='login.php';</script>";
    exit;
}

try {
    // 1. 가맹점별 종합 통계 데이터 (매출, 주문수, 포인트, 평점)
    // 컬럼 유무에 따른 에러 방지를 위해 LEFT JOIN 사용
    $sql = "SELECT 
                s.id, 
                s.store_name,
                COUNT(DISTINCT o.id) as total_orders,
                IFNULL(SUM(o.total_amount), 0) as gross_revenue,
                IFNULL(AVG(r.rating), 0) as avg_rating,
                IFNULL(SUM(CASE WHEN pl.type = 'USE' THEN ABS(pl.amount) ELSE 0 END), 0) as settle_points
            FROM stores s
            LEFT JOIN orders o ON s.id = o.store_id AND o.status = 'completed'
            LEFT JOIN reviews r ON s.id = r.store_id
            LEFT JOIN point_logs pl ON s.id = pl.store_id AND pl.type = 'USE'
            GROUP BY s.id
            ORDER BY gross_revenue DESC";
    $reports = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // 2. 전체 요약 데이터 계산
    $total_revenue = array_sum(array_column($reports, 'gross_revenue'));
    $total_orders = array_sum(array_column($reports, 'total_orders'));
    
} catch (PDOException $e) {
    // 데이터베이스 컬럼이 아직 생성되지 않았을 경우를 대비한 예외 처리
    $reports = [];
    $total_revenue = 0;
    $total_orders = 0;
    $db_error = "일부 데이터 로드 실패: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>HQ Analytics - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    
    <nav class="bg-slate-900/50 backdrop-blur-md border-b border-slate-800 p-6 sticky top-0 z-50">
        <div class="max-w-[96rem] mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-black italic text-sky-400 uppercase tracking-tighter">HQ Analytics</h1>
            <div class="flex items-center space-x-6">
                <a href="admin_staff_manage.php" class="text-xs font-bold text-slate-400 hover:text-white uppercase">User Manage</a>
                <a href="admin_store_manage.php" class="text-xs font-bold text-slate-400 hover:text-white uppercase">Stores</a>
                <a href="logout.php" class="bg-rose-500/10 text-rose-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-[96rem] mx-auto p-8">
        <?php if(isset($db_error)): ?>
            <div class="bg-rose-500/20 text-rose-400 p-4 rounded-2xl mb-8 text-xs font-bold border border-rose-500/30">
                <?php echo $db_error; ?> <br> (가맹점이나 주문 데이터가 하나도 없으면 발생할 수 있습니다.)
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="bg-slate-900 p-8 rounded-[2.5rem] border border-slate-800">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Total Gross Revenue</p>
                <h3 class="text-4xl font-black text-white italic tracking-tighter">₩ <?php echo number_format($total_revenue); ?></h3>
            </div>
            <div class="bg-slate-900 p-8 rounded-[2.5rem] border border-slate-800">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Total Orders</p>
                <h3 class="text-4xl font-black text-sky-400 italic tracking-tighter"><?php echo number_format($total_orders); ?> 건</h3>
            </div>
            <div class="bg-slate-900 p-8 rounded-[2.5rem] border border-slate-800">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Active Stores</p>
                <h3 class="text-4xl font-black text-emerald-400 italic tracking-tighter"><?php echo count($reports); ?> 매장</h3>
            </div>
        </div>

        <div class="bg-slate-900 rounded-[3rem] border border-slate-800 overflow-hidden shadow-2xl">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-800/50 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                        <th class="p-8">Store Name</th>
                        <th class="p-8">Revenue</th>
                        <th class="p-8">Settle Points</th>
                        <th class="p-8">Rating</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php if(empty($reports)): ?>
                        <tr><td colspan="4" class="p-20 text-center text-slate-600 font-bold uppercase tracking-widest">No Data Available</td></tr>
                    <?php endif; ?>
                    <?php foreach($reports as $r): ?>
                    <tr class="hover:bg-slate-800/30 transition-all">
                        <td class="p-8">
                            <span class="block text-lg font-black text-white"><?php echo htmlspecialchars($r['store_name']); ?></span>
                        </td>
                        <td class="p-8 font-black text-sky-400 italic text-xl">
                            ₩ <?php echo number_format($r['gross_revenue']); ?>
                        </td>
                        <td class="p-8 font-bold text-rose-500">
                            - <?php echo number_format($r['settle_points']); ?> P
                        </td>
                        <td class="p-8 text-amber-400 font-black">
                            ⭐ <?php echo round($r['avg_rating'], 1); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>