<?php
// store_dispatch.php - 가맹점 배차 대시보드 (대기 중인 배달 + 우리 기사 지정)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = (int)$_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? '';
$store_login_at = (int)($_SESSION['store_login_at'] ?? time());
$header_locale = $_SESSION['store_locale'] ?? 'ko';

// 수동 배차 처리 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_driver'])) {
    $delivery_id = (int)($_POST['delivery_id'] ?? 0);
    $driver_id = (int)($_POST['driver_id'] ?? 0);
    if ($delivery_id > 0 && $driver_id > 0) {
        try {
            $del = $pdo->prepare("SELECT d.id, d.status, o.store_id FROM deliveries d JOIN orders o ON o.id = d.order_id WHERE d.id = ? AND o.store_id = ?");
            $del->execute([$delivery_id, $store_id]);
            $d = $del->fetch(PDO::FETCH_ASSOC);
            $drv = $pdo->prepare("SELECT id, name, driver_type, store_id FROM drivers WHERE id = ? AND is_active = 1");
            $drv->execute([$driver_id]);
            $dr = $drv->fetch(PDO::FETCH_ASSOC);
            if ($d && $d['status'] === 'WAITING' && $dr && $dr['driver_type'] === 'DELIVER' && (int)$dr['store_id'] === $store_id) {
                $pdo->prepare("UPDATE deliveries SET driver_id = ?, dispatch_type = 'MANUAL', status = 'ACCEPTED', updated_at = NOW() WHERE id = ?")
                    ->execute([$driver_id, $delivery_id]);
                log_activity($pdo, 'store', $store_id, $store_name, 'store_dispatch', 'update', 'delivery', $delivery_id, "배차: 배달 ID {$delivery_id} → Deliver " . ($dr['name'] ?? '') . " (ID {$driver_id})");
                header("Location: store_dispatch.php?assigned=1"); exit;
            }
        } catch (Exception $e) {}
    }
    header("Location: store_dispatch.php?error=1"); exit;
}

// 대기 중인 배달 (본 매장, WAITING)
$waiting = [];
try {
    $stmt = $pdo->prepare("
        SELECT d.id AS delivery_id, d.order_id, d.status, d.created_at,
               o.address, o.tel, o.guest_name, o.guest_tel, o.total_amount,
               (SELECT GROUP_CONCAT(CONCAT(mt.menu_name, ' x', oi.quantity) SEPARATOR ', ')
                FROM order_items oi
                JOIN menu_translations mt ON oi.menu_id = mt.menu_id AND mt.lang_code = 'ko'
                WHERE oi.order_id = o.id) AS items_summary
        FROM deliveries d
        JOIN orders o ON o.id = d.order_id
        WHERE o.store_id = ? AND d.status = 'WAITING' AND d.driver_id IS NULL
        ORDER BY d.created_at ASC
    ");
    $stmt->execute([$store_id]);
    $waiting = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 본 매장 Deliver 목록 (drivers 테이블)
$store_drivers = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, phone, username FROM drivers WHERE store_id = ? AND driver_type = 'DELIVER' AND is_active = 1 ORDER BY name");
    $stmt->execute([$store_id]);
    $store_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>배차 대시보드 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-8">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 shrink-0">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter">배차 대시보드</h1>
                <span class="text-xs text-slate-400 font-bold hidden sm:inline"><?php echo htmlspecialchars($store_name); ?> · 수동 배차</span>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-4 sm:gap-6 text-xs font-bold">
                <span class="text-slate-500 whitespace-nowrap">접속자 <?php echo htmlspecialchars($store_name); ?></span>
                <span id="current-datetime" class="text-slate-600 whitespace-nowrap">—</span>
                <span class="text-slate-500 whitespace-nowrap">머문 <span id="elapsed-time">0분 0초</span></span>
                <button type="button" onclick="location.href='store_dashboard.php'" class="bg-white border-2 border-slate-200 px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all shrink-0">Back to Dashboard</button>
                <a href="logout.php" class="text-rose-500 hover:underline whitespace-nowrap shrink-0">Logout</a>
            </div>
        </header>

        <?php if (isset($_GET['assigned'])): ?>
        <div class="bg-emerald-50 text-emerald-700 text-sm font-bold px-4 py-3 rounded-2xl border border-emerald-200">배차 완료되었습니다.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div class="bg-rose-50 text-rose-600 text-sm font-bold px-4 py-3 rounded-2xl border border-rose-200">배차 처리에 실패했습니다. 기사가 본 매장 소속(STORE)인지 확인하세요.</div>
        <?php endif; ?>

        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-amber-50 border-b border-amber-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">배달 대기 (Deliver 지정)</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">아래 주문에 우리 매장 Deliver를 지정하세요. 지정 후 Rider 앱에서 픽업·배달 완료를 진행합니다.</p>
            </div>
            <?php if (empty($store_drivers)): ?>
            <div class="p-6 text-amber-600 text-sm font-bold">등록된 Deliver가 없습니다. <a href="store_rider_manage.php" class="underline">Deliver 관리</a>에서 Deliver를 등록하세요.</div>
            <?php endif; ?>
            <?php if (empty($waiting)): ?>
            <div class="p-8 text-center text-slate-400 text-sm font-bold">배차 대기 중인 배달이 없습니다.</div>
            <?php else: ?>
            <ul class="divide-y divide-slate-50">
                <?php foreach ($waiting as $w): ?>
                <li class="p-5 hover:bg-slate-50/50 flex flex-wrap items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <span class="text-[10px] font-black text-amber-600 uppercase">#<?php echo (int)$w['order_id']; ?></span>
                        <p class="text-sm font-black text-slate-800 mt-1"><?php echo htmlspecialchars($w['address'] ?: '주소 없음'); ?></p>
                        <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($w['guest_name'] ?: ''); ?> · <?php echo htmlspecialchars($w['tel'] ?: $w['guest_tel'] ?: '-'); ?></p>
                        <p class="text-[10px] text-slate-400 mt-1"><?php echo htmlspecialchars($w['items_summary'] ?? ''); ?></p>
                        <p class="text-xs font-bold text-slate-600 mt-1"><?php echo number_format($w['total_amount']); ?>원</p>
                    </div>
                    <form method="POST" class="flex items-center gap-2 shrink-0">
                        <input type="hidden" name="assign_driver" value="1">
                        <input type="hidden" name="delivery_id" value="<?php echo (int)$w['delivery_id']; ?>">
                        <select name="driver_id" required class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="">Deliver 선택</option>
                            <?php foreach ($store_drivers as $dr): ?>
                            <option value="<?php echo (int)$dr['id']; ?>"><?php echo htmlspecialchars($dr['name']); ?> (<?php echo htmlspecialchars($dr['username'] ?: $dr['phone'] ?: '-'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded-xl text-xs font-black uppercase hover:bg-amber-600">배차</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
    <script>
    (function() {
        var loginAt = <?php echo $store_login_at; ?> * 1000;
        var locale = <?php echo json_encode($header_locale); ?>;
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        var thMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        var enMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        function formatDateTimeLocale(now) {
            var y = now.getFullYear(), m = now.getMonth(), d = now.getDate();
            var h = pad(now.getHours()), i = pad(now.getMinutes()), s = pad(now.getSeconds());
            var time = h + ':' + i + ':' + s;
            if (locale === 'th') return d + ' ' + thMonths[m] + ' ' + (y + 543) + ' ' + time;
            if (locale === 'en' || locale === 'en_us') return enMonths[m] + ' ' + d + ', ' + y + ' ' + time;
            if (locale === 'ja') return y + '年' + (m+1) + '月' + d + '日 ' + time;
            if (locale === 'vi') return d + '/' + (m+1) + '/' + y + ' ' + time;
            return y + '년 ' + (m+1) + '월 ' + d + '일 ' + time;
        }
        function formatElapsed(sec) {
            var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
            if (h > 0) return h + '시간 ' + m + '분 ' + s + '초';
            if (m > 0) return m + '분 ' + s + '초';
            return s + '초';
        }
        function tick() {
            var now = new Date();
            var el = document.getElementById('current-datetime');
            if (el) el.textContent = formatDateTimeLocale(now);
            var et = document.getElementById('elapsed-time');
            if (et && loginAt) { var sec = Math.max(0, Math.floor((now.getTime() - loginAt) / 1000)); et.textContent = formatElapsed(sec); }
        }
        tick();
        setInterval(tick, 1000);
    })();
    </script>
</body>
</html>
