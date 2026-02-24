<?php
// admin_delivery_waiting.php - 본사: 배달 대기 현황 (본사 Rider 수락 대기 중인 건)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') { header("Location: login.php"); exit; }
include 'db_config.php';
include 'common.php';

$waiting = [];
try {
    $stmt = $pdo->query("
        SELECT d.id AS delivery_id, d.order_id, d.status, d.created_at,
               o.store_id, o.address, o.tel, o.guest_name, o.guest_tel, o.total_amount,
               s.store_name
        FROM deliveries d
        JOIN orders o ON o.id = d.order_id
        LEFT JOIN stores s ON s.id = o.store_id
        WHERE d.status = 'WAITING' AND d.driver_id IS NULL
        ORDER BY d.created_at ASC
    ");
    $waiting = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = '배달 대기 현황';
    $admin_page_subtitle = '본사 Rider가 수락 대기 중인 배달 (배민 구조)';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>배달 대기 현황 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard'; }</style>
</head>
<body class="bg-slate-50 min-h-screen p-8">
    <div class="max-w-[96rem] mx-auto">
        <header class="flex flex-wrap items-center justify-between gap-3 mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-900 uppercase">배달 대기 현황</h1>
                <p class="text-xs text-slate-500 mt-1">본사 Rider가 수락 대기 중인 배달 (배민 구조)</p>
            </div>
            <a href="admin_dashboard.php" class="bg-slate-200 text-slate-700 px-5 py-2.5 rounded-2xl text-xs font-black uppercase hover:bg-slate-300">Back to Dashboard</a>
        </header>
<?php endif; ?>

        <div class="max-w-[96rem]">
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-amber-50 border-b border-amber-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">수락 대기 (driver_id 미배정)</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">본사 Rider 로그인 후 Rider Dashboard에서 수락하면 배차됩니다.</p>
            </div>
            <div class="divide-y divide-slate-50">
                <?php if (empty($waiting)): ?>
                <div class="p-8 text-center text-slate-400 text-sm font-bold">대기 중인 배달이 없습니다.</div>
                <?php endif; ?>
                <?php foreach ($waiting as $w): ?>
                <div class="p-5 hover:bg-slate-50/50">
                    <span class="text-[10px] font-black text-amber-600 uppercase">#<?php echo (int)$w['order_id']; ?> · 배달 ID <?php echo (int)$w['delivery_id']; ?></span>
                    <p class="text-sm font-black text-slate-800 mt-1"><?php echo htmlspecialchars($w['store_name'] ?? '—'); ?></p>
                    <p class="text-sm text-slate-600 mt-0.5"><?php echo htmlspecialchars($w['address'] ?: '주소 없음'); ?></p>
                    <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($w['guest_name'] ?: ''); ?> · <?php echo htmlspecialchars($w['tel'] ?: $w['guest_tel'] ?: '-'); ?></p>
                    <p class="text-xs font-bold text-slate-600 mt-1"><?php echo number_format((int)$w['total_amount']); ?>원</p>
                    <p class="text-[10px] text-slate-400 mt-1"><?php echo htmlspecialchars($w['created_at'] ?? ''); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
    </div>
</body>
</html>
<?php endif; ?>
