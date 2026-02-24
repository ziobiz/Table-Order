<?php
// store_orders.php - 가맹점 주방/포스 주문 접수 현황
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = $_SESSION['store_id'];

// 매장 설정(테마, 알림 시간, 사운드, 날짜/시간 형식 포함) 로드
$st = $pdo->prepare("SELECT store_name, kds_theme, kds_alert_5, kds_alert_10, kds_alert_20, kds_alert_30, kds_sound, kds_sound_custom, kds_datetime_locale FROM stores WHERE id = ?");
$st->execute([$store_id]);
$store = $st->fetch(PDO::FETCH_ASSOC);
$themeKey = $store['kds_theme'] ?? 'sky';

// KDS 테마 팔레트 정의
$themes = [
    'sky' => [
        'bg' => 'bg-slate-100',
        'card' => 'bg-white/90',
        'pending' => 'border-sky-400 bg-sky-50',
        'cooking' => 'border-emerald-400 bg-emerald-50',
        'served' => 'border-amber-400 bg-amber-50',
        'btnKds' => 'bg-sky-600 hover:bg-sky-700 text-white',
    ],
    'forest' => [
        'bg' => 'bg-emerald-50',
        'card' => 'bg-white/90',
        'pending' => 'border-emerald-500 bg-emerald-50',
        'cooking' => 'border-lime-500 bg-lime-50',
        'served' => 'border-slate-500 bg-slate-50',
        'btnKds' => 'bg-emerald-600 hover:bg-emerald-700 text-white',
    ],
    'sunset' => [
        'bg' => 'bg-orange-50',
        'card' => 'bg-white/90',
        'pending' => 'border-rose-400 bg-rose-50',
        'cooking' => 'border-amber-400 bg-amber-50',
        'served' => 'border-emerald-400 bg-emerald-50',
        'btnKds' => 'bg-amber-600 hover:bg-amber-700 text-white',
    ],
    'mono' => [
        'bg' => 'bg-slate-100',
        'card' => 'bg-white/95',
        'pending' => 'border-slate-500 bg-slate-50',
        'cooking' => 'border-slate-400 bg-slate-50',
        'served' => 'border-slate-300 bg-slate-50',
        'btnKds' => 'bg-slate-600 hover:bg-slate-700 text-white',
    ],
    'pastel' => [
        'bg' => 'bg-slate-50',
        'card' => 'bg-white/95',
        'pending' => 'border-indigo-300 bg-indigo-50',
        'cooking' => 'border-teal-300 bg-teal-50',
        'served' => 'border-pink-300 bg-pink-50',
        'btnKds' => 'bg-indigo-600 hover:bg-indigo-700 text-white',
    ],
    'contrast' => [
        'bg' => 'bg-slate-900',
        'card' => 'bg-slate-800',
        'pending' => 'border-rose-500 bg-rose-900/40',
        'cooking' => 'border-amber-400 bg-amber-900/30',
        'served' => 'border-emerald-400 bg-emerald-900/30',
        'btnKds' => 'bg-slate-600 hover:bg-slate-500 text-white',
    ],
];

$theme = $themes[$themeKey] ?? $themes['sky'];

// 주문 알림 사운드 매핑
$soundKey = $store['kds_sound'] ?? 'chime1';
$soundMap = [
    'chime1' => 'assets/kds_sounds/chime1.mp3',
    'chime2' => 'assets/kds_sounds/chime2.mp3',
    'bell1'  => 'assets/kds_sounds/bell1.mp3',
    'bell2'  => 'assets/kds_sounds/bell2.mp3',
    'soft1'  => 'assets/kds_sounds/soft1.mp3',
    'soft2'  => 'assets/kds_sounds/soft2.mp3',
    'alert1' => 'assets/kds_sounds/alert1.mp3',
    'alert2' => 'assets/kds_sounds/alert2.mp3',
    'digital1' => 'assets/kds_sounds/digital1.mp3',
    'digital2' => 'assets/kds_sounds/digital2.mp3',
];

if ($soundKey === 'custom' && !empty($store['kds_sound_custom'])) {
    $soundSrc = $store['kds_sound_custom'];
} else {
    $soundSrc = $soundMap[$soundKey] ?? $soundMap['chime1'];
}

// 상태 변경 처리 (DB에 소문자로 저장)
if (isset($_GET['status']) && isset($_GET['oid'])) {
    $status = strtolower(trim((string)$_GET['status']));
    $oid = (int)$_GET['oid'];
    if (in_array($status, ['pending', 'cooking', 'served', 'paid'], true)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND store_id = ?");
        $stmt->execute([$status, $oid, $store_id]);
    }
    header("Location: store_orders.php"); exit;
}

// 주문 목록 조회
$sql = "
    SELECT 
        o.*,
        u.nickname,
        (
            SELECT GROUP_CONCAT(CONCAT(mt.menu_name, ' x', oi.quantity) SEPARATOR ', ')
            FROM order_items oi
            JOIN menu_translations mt ON oi.menu_id = mt.menu_id AND mt.lang_code = 'ko'
            WHERE oi.order_id = o.id
        ) AS items_summary
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.store_id = ? AND (o.status IS NULL OR o.status != 'paid')
    ORDER BY o.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$store_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 왼쪽: 대기/조리중 / 오른쪽: 조리완료(served) 히스토리
$orders_active = [];
$orders_served = [];
foreach ($orders as $o) {
    $s = trim((string)($o['status'] ?? ''));
    $s = $s === '' ? '' : strtolower($s);
    if ($s === 'served') {
        $orders_served[] = $o;
    } else {
        $orders_active[] = $o;
    }
}

$btnKdsClass = $theme['btnKds'] ?? 'bg-sky-600 hover:bg-sky-700 text-white';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="10"> <title>Kitchen View - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', 'Arial', sans-serif; }
        .age-5  { animation: blink-blue 1s infinite alternate; }
        .age-10 { animation: blink-yellow 1.2s infinite alternate; }
        .age-20 { animation: blink-red 0.8s infinite alternate; }
        .age-30 { animation: blink-gray 1.5s infinite alternate; }
    </style>
</head>
<body class="<?php echo $theme['bg']; ?> p-6">
    <audio id="kds-audio" src="<?php echo htmlspecialchars($soundSrc); ?>" preload="auto"></audio>
    <div class="max-w-7xl mx-auto">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-black text-slate-900">Kitchen Display (KDS)</h1>
                <p class="text-xs text-slate-500 font-bold mt-1"><?php echo htmlspecialchars($store['store_name']); ?></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="kitchen_display.php?store_id=<?php echo (int)$store_id; ?>" target="_blank" class="px-5 py-3 rounded-xl font-bold <?php echo $btnKdsClass; ?>">KDS 화면 열기</a>
                <button onclick="location.reload()" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-bold">Refresh 🔄</button>
            </div>
        </header>

        <div class="flex gap-6">
            <div class="flex-1 min-w-0">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($orders_active as $o): 
                $status = strtolower($o['status'] ?? '');
                $stateColor = $status === 'pending' ? $theme['pending'] : ($status === 'cooking' ? $theme['cooking'] : $theme['served']);
                $btnColor = $status=='pending' ? 'bg-rose-500' : ($status=='cooking' ? 'bg-amber-500' : 'bg-emerald-500');
            ?>
            <div class="rounded-2xl shadow-lg p-6 <?php echo $theme['card'] . ' ' . $stateColor; ?>">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <span class="text-3xl font-black text-slate-800">Order #<?php echo $o['id']; ?></span>
                        <div class="text-xs font-bold text-slate-500 mt-1"><?php echo $o['user_id'] > 0 ? 'Member: '.$o['nickname'] : 'Guest Customer'; ?></div>
                    </div>
                    <span class="text-[10px] font-black text-white px-3 py-1 rounded-full <?php echo $btnColor; ?>"><?php echo strtoupper($status); ?></span>
                </div>
                <div class="bg-white/60 p-4 rounded-xl border border-slate-200 mb-6">
                    <p class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($o['items_summary']); ?></p>
                    <p class="text-right text-sm font-black text-slate-600 mt-2">Total: <?php echo number_format($o['total_amount']); ?> ₩</p>
                </div>
                <div class="flex gap-2">
                    <?php if($status=='pending'): ?>
                        <a href="?status=COOKING&oid=<?php echo $o['id']; ?>" class="flex-1 bg-sky-500 text-white py-3 rounded-xl text-center font-black shadow-md hover:bg-sky-600">Start Cooking 🔥</a>
                    <?php elseif($status=='cooking'): ?>
                        <a href="?status=SERVED&oid=<?php echo $o['id']; ?>" class="flex-1 bg-emerald-500 text-white py-3 rounded-xl text-center font-black shadow-md hover:bg-emerald-600">Serve Done ✅</a>
                    <?php elseif($status=='served'): ?>
                        <a href="?status=PAID&oid=<?php echo $o['id']; ?>" class="flex-1 bg-slate-800 text-white py-3 rounded-xl text-center font-black shadow-md hover:bg-slate-900">Payment Complete 💰</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
            </div>

            <div class="w-80 shrink-0">
                <div class="<?php echo $theme['card']; ?> rounded-2xl shadow-lg p-4 <?php echo $theme['served']; ?> sticky top-6">
                    <h2 class="text-sm font-black mb-3 pb-2 border-b text-slate-800 border-slate-200">조리완료 히스토리</h2>
                    <?php if (empty($orders_served)): ?>
                        <p class="text-xs text-slate-500">완료된 주문 없음</p>
                    <?php else: ?>
                        <ul class="space-y-3 max-h-[70vh] overflow-y-auto">
                            <?php foreach ($orders_served as $o): 
                                $created = new DateTime($o['created_at']);
                                $doneAt = $created->format('Y-m-d H:i');
                            ?>
                                <li class="pb-2 border-b border-slate-200/60">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="font-bold text-slate-800">Order #<?php echo (int)$o['id']; ?></span>
                                        <span class="font-mono text-slate-500"><?php echo $doneAt; ?></span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 truncate mt-0.5"><?php echo htmlspecialchars($o['items_summary'] ?? ''); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
