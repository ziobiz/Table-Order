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

// KDS 테마 팔레트 정의 (스트레스 낮은 색조 위주) + KDS 버튼은 테마와 같은 톤의 불투명 색
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

// 주문 알림 사운드 매핑 (기본 10가지 + 커스텀)
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

// 상태 변경 처리 (DB에 소문자로 저장해 KDS·ENUM과 일치시키기)
if (isset($_GET['status']) && isset($_GET['oid'])) {
    $status = strtolower(trim((string)$_GET['status']));
    $oid = (int)$_GET['oid'];
    if (in_array($status, ['pending', 'cooking', 'served', 'paid'], true)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND store_id = ?");
        $stmt->execute([$status, $oid, $store_id]);
    }
    header("Location: store_orders.php"); exit;
}

// 주문 목록 조회 (최신순)
// - 메뉴명은 order_items + menu_translations 조인으로 구성
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

// 왼쪽: 대기/조리중(pending, cooking) / 오른쪽: 조리완료(served) 히스토리
// status는 DB에서 대소문자 혼용 가능하므로 소문자로 통일 비교. NULL/빈값은 served 아님.
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

        /* 경과 시간에 따른 깜박임 효과 */
        @keyframes blink-blue {
            0% { box-shadow: 0 0 0 0 rgba(59,130,246,0.7); }
            100% { box-shadow: 0 0 0 8px rgba(59,130,246,0); }
        }
        @keyframes blink-yellow {
            0% { box-shadow: 0 0 0 0 rgba(250,204,21,0.7); }
            100% { box-shadow: 0 0 0 8px rgba(250,204,21,0); }
        }
        @keyframes blink-red {
            0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.8); transform: translateY(0); }
            100% { box-shadow: 0 0 0 10px rgba(239,68,68,0); transform: translateY(-2px); }
        }
        @keyframes blink-gray {
            0% { box-shadow: 0 0 0 0 rgba(148,163,184,0.8); opacity: 0.8; }
            100% { box-shadow: 0 0 0 10px rgba(148,163,184,0); opacity: 0.4; }
        }

        .age-5  { animation: blink-blue 1s infinite alternate; }
        .age-10 { animation: blink-yellow 1.2s infinite alternate; }
        .age-20 { animation: blink-red 0.8s infinite alternate; }
        .age-30 { animation: blink-gray 1.5s infinite alternate; }
    </style>
</head>
<body class="<?php echo $theme['bg']; ?> p-6">
    <!-- 주문 알림 사운드 -->
    <audio id="kds-audio" src="<?php echo htmlspecialchars($soundSrc); ?>" preload="auto"></audio>
    <div class="max-w-[96rem] mx-auto">
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
                // 상태 값은 소문자(pending/cooking/served/paid) 기준으로 처리
                $status = strtolower($o['status']);

                // 경과 시간(분) 계산 (포스 화면은 숫자 표시만, 강조 애니메이션은 KDS에서 처리)
                $created = new DateTime($o['created_at']);
                $now = new DateTime();
                $diffMin = floor(($now->getTimestamp() - $created->getTimestamp()) / 60);

                // 상태 라벨 (가맹점 설정에 따라 한글/영문)
                $useLocalStatus = (int)($store['use_local_status'] ?? 1);
                if ($useLocalStatus) {
                    $labelMap = [];
                    // 주문 타입별 플로우에 따라 라벨 변경
                    if ($o['order_type'] === 'delivery') { // 온라인 배달 주문
                        $labelMap = [
                            'pending' => '대기',
                            'cooking' => '조리중',
                            'served'  => '배달출발',
                            'paid'    => '배달완료',
                        ];
                    } elseif ($o['order_type'] === 'pickup') { // 픽업 주문
                        $labelMap = [
                            'pending' => '대기',
                            'cooking' => '조리중',
                            'served'  => '조리완료',
                            'paid'    => '수령완료',
                        ];
                    } else { // 매장 주문 (dinein 등)
                        $labelMap = [
                            'pending' => '대기',
                            'cooking' => '조리중',
                            'served'  => '서빙완료',
                            'paid'    => '결제완료',
                        ];
                    }
                    $statusLabel = $labelMap[$status] ?? strtoupper($status);
                } else {
                    $statusLabel = strtoupper($status);
                }

                // 테마별 카드 색상
                $baseCard = $theme['card'];
                if ($status === 'pending') {
                    $stateColor = $theme['pending'];
                } elseif ($status === 'cooking') {
                    $stateColor = $theme['cooking'];
                } else {
                    $stateColor = $theme['served'];
                }

                $btnColor = $status=='pending' ? 'bg-rose-500' : ($status=='cooking' ? 'bg-amber-500' : 'bg-emerald-500');
            ?>
            <div class="rounded-2xl shadow-lg p-6 <?php echo $baseCard . ' ' . $stateColor; ?>">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <span class="text-3xl font-black <?php echo $themeKey === 'contrast' ? 'text-white' : 'text-slate-800'; ?>">
                            Order #<?php echo $o['id']; ?>
                        </span>
                        <div class="text-xs font-bold text-slate-500 mt-1">
                            <?php echo $o['user_id'] > 0 ? 'Member: '.$o['nickname'] : 'Guest Customer'; ?>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-1">
                            <?php echo $diffMin; ?>분 경과
                        </div>
                    </div>
                    <span class="text-[10px] font-black text-white px-3 py-1 rounded-full <?php echo $btnColor; ?>"><?php echo $statusLabel; ?></span>
                </div>

                <div class="bg-white/60 p-4 rounded-xl border border-slate-200 mb-6">
                    <p class="text-lg font-bold text-slate-800 leading-relaxed">
                        <?php echo htmlspecialchars($o['items_summary']); ?>
                    </p>
                    <p class="text-right text-sm font-black text-slate-600 mt-2">Total: <?php echo number_format($o['total_amount']); ?> ₩</p>
                    <?php if ($status === 'paid'): ?>
                        <?php
                            $dtLocale = $store['kds_datetime_locale'] ?? 'ko';
                            $ts = strtotime($o['updated_at'] ?? $o['created_at']);
                            switch ($dtLocale) {
                                case 'en':
                                    $doneAt = date('m/d/Y H:i', $ts);
                                    break;
                                case 'ja':
                                    $doneAt = date('Y/m/d H:i', $ts);
                                    break;
                                case 'th':
                                    $doneAt = date('d-m-Y H:i', $ts);
                                    break;
                                case 'vi':
                                    $doneAt = date('d/m/Y H:i', $ts);
                                    break;
                                case 'id':
                                    $doneAt = date('d-m-Y H:i', $ts);
                                    break;
                                case 'zh':
                                    $doneAt = date('Y-m-d H:i', $ts);
                                    break;
                                case 'ko':
                                default:
                                    $doneAt = date('Y-m-d H:i', $ts);
                                    break;
                            }
                        ?>
                        <p class="text-right text-[11px] text-slate-400 mt-1">
                            완료 시각: <?php echo $doneAt; ?>
                        </p>
                    <?php endif; ?>
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

            <!-- 오른쪽: 조리완료 히스토리 (SERVED 주문만) -->
            <div class="w-80 shrink-0">
                <div class="<?php echo $theme['card']; ?> rounded-2xl shadow-lg p-4 <?php echo $theme['served']; ?> sticky top-6">
                    <?php $isContrast = ($themeKey === 'contrast'); ?>
                    <h2 class="text-sm font-black mb-3 pb-2 border-b <?php echo $isContrast ? 'text-white border-slate-600' : 'text-slate-800 border-slate-200'; ?>">조리완료 히스토리</h2>
                    <?php if (empty($orders_served)): ?>
                        <p class="text-xs <?php echo $isContrast ? 'text-slate-300' : 'text-slate-500'; ?>">완료된 주문 없음</p>
                    <?php else: ?>
                        <ul class="space-y-3 max-h-[70vh] overflow-y-auto">
                            <?php foreach ($orders_served as $o): 
                                $created = new DateTime($o['created_at']);
                                $dtLocale = $store['kds_datetime_locale'] ?? 'ko';
                                switch ($dtLocale) {
                                    case 'en': $doneAt = $created->format('m/d/Y H:i'); break;
                                    case 'ja': $doneAt = $created->format('Y/m/d H:i'); break;
                                    case 'th':
                                    case 'id': $doneAt = $created->format('d-m-Y H:i'); break;
                                    case 'vi': $doneAt = $created->format('d/m/Y H:i'); break;
                                    default:  $doneAt = $created->format('Y-m-d H:i'); break;
                                }
                            ?>
                                <li class="pb-2 border-b <?php echo $isContrast ? 'border-slate-600' : 'border-slate-200/60'; ?>">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="font-bold <?php echo $isContrast ? 'text-white' : 'text-slate-800'; ?>">Order #<?php echo (int)$o['id']; ?></span>
                                        <span class="font-mono <?php echo $isContrast ? 'text-slate-300' : 'text-slate-500'; ?>"><?php echo $doneAt; ?></span>
                                    </div>
                                    <p class="text-[11px] <?php echo $isContrast ? 'text-slate-400' : 'text-slate-500'; ?> truncate mt-0.5"><?php echo htmlspecialchars($o['items_summary'] ?? ''); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
// 새 주문 알림: 가장 최근 주문 ID가 이전에 본 값보다 크면 소리 재생
(function() {
    const latestOrderId = <?php echo !empty($orders) ? (int)$orders[0]['id'] : 0; ?>;
    const storeId = <?php echo (int)$store_id; ?>;
    const key = 'kds_last_order_' + storeId;
    const prev = parseInt(localStorage.getItem(key) || '0', 10);

    if (latestOrderId > 0 && latestOrderId > prev) {
        const audio = document.getElementById('kds-audio');
        if (audio) {
            audio.play().catch(function(){});
        }
        localStorage.setItem(key, String(latestOrderId));
    }
})();
</script>
</html>