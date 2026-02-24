<?php
// admin_rider_manage.php - 본사: 본사 Rider 관리 (배민 구조, driver_type=HQ)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') { header("Location: login.php"); exit; }
include 'db_config.php';
include 'common.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$login_at = (int)($_SESSION['admin_login_at'] ?? time());

$riders = [];
try {
    $stmt = $pdo->query("SELECT id, name, phone, username, is_active, created_at FROM drivers WHERE driver_type = 'HQ' ORDER BY id DESC");
    $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = '본사 Rider 관리';
    $admin_page_subtitle = '배민 구조 · 본사 소속 Rider (대기 콜 수락)';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>본사 Rider 관리 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard'; }</style>
</head>
<body class="bg-slate-50 min-h-screen p-8">
    <div class="max-w-[96rem] mx-auto">
        <header class="flex flex-wrap items-center justify-between gap-3 mb-8">
            <div>
                <h1 class="text-2xl font-black italic text-slate-900 uppercase">본사 Rider 관리</h1>
                <p class="text-xs text-slate-500 mt-1">배민 구조 · 본사 소속 Rider (대기 콜 수락)</p>
            </div>
            <a href="admin_dashboard.php" class="bg-slate-200 text-slate-700 px-5 py-2.5 rounded-2xl text-xs font-black uppercase hover:bg-slate-300">Back to Dashboard</a>
        </header>
<?php endif; ?>

        <div class="max-w-[96rem]">
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-sky-50 border-b border-sky-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">본사 Rider 목록 (driver_type=HQ)</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">이 계정들은 로그인 시 Rider Dashboard에서 대기 배달을 수락할 수 있습니다. 신규 등록은 Rider 등록 신청(본사 등록) 후 승인으로 추가합니다.</p>
            </div>
            <div class="divide-y divide-slate-50">
                <?php if (empty($riders)): ?>
                <div class="p-8 text-center text-slate-400 text-sm font-bold">등록된 본사 Rider가 없습니다. Rider 등록 신청에서 본사 등록 후 driver_applications 승인 시 drivers에 추가됩니다.</div>
                <?php endif; ?>
                <?php foreach ($riders as $r): ?>
                <div class="p-5 hover:bg-slate-50/50 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="font-black text-slate-800"><?php echo htmlspecialchars($r['name']); ?></span>
                        <?php if ($r['phone']): ?><span class="text-slate-500 text-xs ml-2"><?php echo htmlspecialchars($r['phone']); ?></span><?php endif; ?>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-sky-100 text-sky-700 ml-2"><?php echo htmlspecialchars($r['username'] ?: '—'); ?></span>
                        <?php if (empty($r['is_active'])): ?><span class="text-[10px] text-rose-500 ml-2">비활성</span><?php endif; ?>
                    </div>
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
