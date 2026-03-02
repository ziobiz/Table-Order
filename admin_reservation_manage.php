<?php
// admin_reservation_manage.php - 매장 온라인 예약 관리 대시보드
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안] 매장 로그인 여부 체크 (가맹점 전용)
if (!isset($_SESSION['store_id'])) {
    header("Location: login.php"); exit;
}
// 배달 기사/라이더는 접근 불가
if (isset($_SESSION['driver_id']) || isset($_SESSION['rider_id'])) {
    header("Location: driver_dashboard.php"); exit;
}

$my_store_id = (int)($_SESSION['store_id'] ?? 0);
$store_name = $_SESSION['store_name'] ?? ($_SESSION['name'] ?? '');

// 상태 변경 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = (int)($_POST['reservation_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    $allowed = ['PENDING','CONFIRMED','SEATED','CANCELLED','NO_SHOW','COMPLETED'];
    if ($id > 0 && in_array($new_status, $allowed, true)) {
        $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ? AND store_id = ?");
        $stmt->execute([$new_status, $my_store_id]);

        $actor_id = $my_store_id;
        $actor_name = $store_name !== '' ? $store_name : ('store_' . $actor_id);
        log_activity($pdo, 'store', $actor_id, $actor_name, 'admin_reservation_manage', 'update', 'reservation', (string)$id, "예약 상태 변경: ID {$id} → {$new_status}");
    }
    header("Location: admin_reservation_manage.php"); exit;
}

// 날짜 필터 (기본: 오늘)
$date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT * FROM reservations
    WHERE store_id = ? AND reserve_date = ?
    ORDER BY reserve_time ASC, id ASC
");
$stmt->execute([$my_store_id, $date]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Reservation Management - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="60">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

    <nav class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 p-6 sticky top-0 z-50">
        <div class="max-w-[96rem] mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-6">
                <h1 class="text-2xl font-black italic text-sky-400 uppercase tracking-tighter">Reservation Master</h1>
                <span class="px-3 py-1 bg-slate-800 rounded-full text-[10px] font-black text-slate-400 uppercase tracking-widest">Online Booking</span>
            </div>
            <div class="flex items-center space-x-6">
                <span class="text-xs font-bold text-slate-400"><?php echo htmlspecialchars($store_name); ?> 님</span>
                <a href="admin_waiting_manage.php" class="text-xs font-black text-slate-500 hover:text-white transition uppercase">Waiting</a>
                <a href="logout.php" class="bg-rose-500/10 text-rose-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-[96rem] mx-auto p-8">
        <div class="flex justify-between items-end mb-8">
            <div>
                <h2 class="text-4xl font-black text-white italic tracking-tighter uppercase">Reservations</h2>
                <p class="text-slate-500 text-xs font-bold uppercase mt-2 italic">
                    날짜: <?php echo htmlspecialchars($date); ?> · 총 <?php echo count($reservations); ?>건
                </p>
            </div>
            <form method="GET" class="flex items-center gap-3">
                <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" class="bg-slate-900 border border-slate-700 rounded-2xl px-4 py-2 text-xs font-bold text-slate-100 outline-none">
                <button type="submit" class="bg-slate-800 text-slate-100 px-5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-700 transition">Change Date</button>
                <button type="button" onclick="window.open('reservation_register.php?store_id=<?php echo $my_store_id; ?>', '_blank')" class="bg-sky-500 text-white px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-sky-600 transition">Open Reservation Kiosk</button>
            </form>
        </div>

        <?php if (empty($reservations)): ?>
        <div class="py-40 text-center border-2 border-dashed border-slate-800 rounded-[3rem] opacity-30">
            <p class="text-2xl font-black uppercase tracking-widest">No Reservations</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($reservations as $r):
                $status = strtoupper($r['status']);
                $is_confirmed = $status === 'CONFIRMED';
                $is_seated = $status === 'SEATED';
                $is_cancel = $status === 'CANCELLED' || $status === 'NO_SHOW';
                $card_bg = $is_cancel ? 'bg-slate-900 border-rose-700/60' : ($is_seated ? 'bg-emerald-900/40 border-emerald-500/60' : ($is_confirmed ? 'bg-sky-900/40 border-sky-500/60' : 'bg-slate-900 border-slate-800'));
            ?>
            <div class="<?php echo $card_bg; ?> rounded-[2.5rem] border p-8 shadow-2xl transition-all relative overflow-hidden">
                <?php if ($is_cancel): ?>
                    <div class="absolute top-0 right-0 bg-rose-600 text-[10px] font-black px-4 py-1 rounded-bl-2xl uppercase text-white"><?php echo $status; ?></div>
                <?php elseif ($is_seated): ?>
                    <div class="absolute top-0 right-0 bg-emerald-500 text-[10px] font-black px-4 py-1 rounded-bl-2xl uppercase text-white">SEATED</div>
                <?php elseif ($is_confirmed): ?>
                    <div class="absolute top-0 right-0 bg-sky-500 text-[10px] font-black px-4 py-1 rounded-bl-2xl uppercase text-white">CONFIRMED</div>
                <?php endif; ?>

                <div class="flex justify-between items-start mb-6">
                    <span class="text-2xl font-black italic text-sky-400"><?php echo htmlspecialchars(substr($r['reserve_time'], 0, 5)); ?></span>
                    <span class="text-[10px] font-black text-slate-500 uppercase"><?php echo date('Y-m-d', strtotime($r['reserve_date'])); ?></span>
                </div>

                <div class="mb-6">
                    <h3 class="text-2xl font-black text-white mb-1"><?php echo htmlspecialchars($r['customer_name']); ?> 님</h3>
                    <p class="text-sm font-bold text-slate-400 italic">인원: <?php echo (int)$r['party_size']; ?>명 / <?php echo htmlspecialchars($r['tel']); ?></p>
                    <?php if (!empty($r['note'])): ?>
                    <p class="text-[11px] text-slate-400 mt-2">요청: <?php echo htmlspecialchars($r['note']); ?></p>
                    <?php endif; ?>
                </div>

                <form method="POST" class="flex gap-2">
                    <input type="hidden" name="reservation_id" value="<?php echo (int)$r['id']; ?>">
                    <input type="hidden" name="update_status" value="1">

                    <?php if ($status === 'PENDING'): ?>
                        <button name="new_status" value="CONFIRMED" class="flex-1 bg-sky-500 text-white py-3 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-sky-600 transition">Confirm</button>
                        <button name="new_status" value="CANCELLED" class="px-4 bg-slate-800 text-rose-400 rounded-2xl font-black text-[10px] hover:bg-rose-500 hover:text-white transition">Cancel</button>
                    <?php elseif ($status === 'CONFIRMED'): ?>
                        <button name="new_status" value="SEATED" class="flex-1 bg-emerald-500 text-white py-3 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-emerald-600 transition">Seated</button>
                        <button name="new_status" value="NO_SHOW" class="px-4 bg-slate-800 text-amber-400 rounded-2xl font-black text-[10px] hover:bg-amber-500 hover:text-white transition">No Show</button>
                    <?php elseif ($status === 'SEATED'): ?>
                        <button name="new_status" value="COMPLETED" class="flex-1 bg-slate-200 text-slate-900 py-3 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-white transition">Complete</button>
                    <?php else: ?>
                        <button type="button" disabled class="flex-1 bg-slate-700/50 text-slate-400 py-3 rounded-2xl font-black uppercase text-[10px] tracking-widest cursor-not-allowed">Closed</button>
                    <?php endif; ?>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

</body>
</html>

