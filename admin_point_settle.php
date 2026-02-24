<?php
// admin_point_settle.php - 본사용 가맹점 포인트 정산 대시보드
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// 본사 관리자만 접근 가능 (간단 예시)
$admin_role = $_SESSION['admin_role'] ?? 'PARTTIME';
if ($admin_role !== 'SUPERADMIN') {
    echo "<script>alert('본사 전용 메뉴입니다.'); location.href='admin_menu_list.php';</script>"; exit;
}

// 가맹점별 포인트 통계 쿼리
$sql = "SELECT s.id as store_id, s.store_name, s.point_payer,
        SUM(CASE WHEN l.type = 'EARN' THEN l.amount ELSE 0 END) as total_earned,
        SUM(CASE WHEN l.type = 'USE' THEN ABS(l.amount) ELSE 0 END) as total_used
        FROM stores s
        LEFT JOIN point_logs l ON s.id = l.store_id
        GROUP BY s.id, s.store_name, s.point_payer";
$settles = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Point Settlement - Alrira Head</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 min-h-screen text-slate-100 p-8">
    <header class="max-w-[96rem] mx-auto flex justify-between items-center mb-12">
        <div>
            <h1 class="text-4xl font-black italic text-sky-400 tracking-tighter uppercase">Point Settle</h1>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-[0.3em] mt-2">Franchise Management System</p>
        </div>
        <button onclick="location.href='admin_menu_list.php'" class="bg-slate-800 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-700 transition">Dashboard</button>
    </header>

    <div class="max-w-[96rem] mx-auto grid grid-cols-1 gap-6">
        <?php foreach($settles as $row): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-[3rem] p-10 flex flex-col md:flex-row justify-between items-center transition-all hover:border-sky-500/50">
            <div class="mb-6 md:mb-0">
                <span class="inline-block px-3 py-1 bg-sky-500/10 text-sky-400 text-[10px] font-black rounded-full mb-3 uppercase tracking-tighter border border-sky-500/20">
                    <?php echo $row['point_payer'] == 'STORE' ? '가맹점 부담 정책' : '본사 지원 정책'; ?>
                </span>
                <h3 class="text-2xl font-black text-white italic"><?php echo htmlspecialchars($row['store_name']); ?></h3>
                <p class="text-slate-500 text-xs mt-1">Store ID: #<?php echo $row['store_id']; ?></p>
            </div>

            <div class="flex flex-wrap gap-12 text-center md:text-right">
                <div class="space-y-1">
                    <span class="block text-[10px] text-slate-500 font-black uppercase">Earned Points</span>
                    <span class="text-xl font-black text-emerald-400">+<?php echo number_format($row['total_earned'] ?? 0); ?> P</span>
                </div>
                <div class="space-y-1">
                    <span class="block text-[10px] text-slate-500 font-black uppercase">Used Points</span>
                    <span class="text-xl font-black text-rose-400">-<?php echo number_format($row['total_used'] ?? 0); ?> P</span>
                </div>
                <div class="md:border-l md:border-slate-800 md:pl-12 space-y-1">
                    <span class="block text-[10px] text-sky-400 font-black uppercase tracking-widest">Settle Amount</span>
                    <span class="text-3xl font-black italic text-white">
                        <?php 
                        // 가맹점이 부담하는 정책인데 타 매장 포인트가 사용됐다면 본사가 보전해줘야 함
                        // 여기서는 간단히 사용된 포인트를 1P=1원 정산액으로 표시
                        echo number_format($row['total_used'] ?? 0); 
                        ?> <small class="text-xs not-italic text-slate-500">KRW</small>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <footer class="max-w-[96rem] mx-auto mt-20 opacity-30 text-center">
        <p class="text-[10px] font-black uppercase tracking-[0.5em]">Alrira Franchise Point Settlement System</p>
    </footer>
</body>
</html>