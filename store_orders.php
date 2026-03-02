<?php
// store_orders.php - 가맹점 주방/포스 주문 접수 현황
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = $_SESSION['store_id'];

// 매장 설정(테마, 알림 시간, 사운드, 날짜/시간 형식, 상태표시 언어, KDS 모드 포함) 로드
$st = $pdo->prepare("SELECT store_name, kds_theme, kds_alert_5, kds_alert_10, kds_alert_20, kds_alert_30, kds_sound, kds_sound_custom, kds_datetime_locale, use_local_status, kds_sync_order_status, kds_mode, kds_history_hours FROM stores WHERE id = ?");
$st->execute([$store_id]);
$store = $st->fetch(PDO::FETCH_ASSOC);
$themeKey = $store['kds_theme'] ?? 'sky';
$kds_mode = ($store['kds_mode'] ?? 'A') === 'B' ? 'B' : 'A';
$kds_sync_order_status = (int)($store['kds_sync_order_status'] ?? 1);
// KDS 히스토리 보관 시간 (24/48/72시간, 기본 24시간)
$kds_history_hours = (int)($store['kds_history_hours'] ?? 24);
if (!in_array($kds_history_hours, [24,48,72], true)) {
    $kds_history_hours = 24;
}

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

// B형 모드: 홀에서 개별 메뉴 서빙완료 처리
if (isset($_GET['serve_item'])) {
    $item_id = (int)$_GET['serve_item'];
    if ($item_id > 0) {
        $q = $pdo->prepare("SELECT oi.order_id, oi.item_status, o.store_id FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.id = ?");
        $q->execute([$item_id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['store_id'] === (int)$store_id) {
            $order_id = (int)$row['order_id'];
            $cur = strtolower($row['item_status'] ?? '');
            // B형: 조리중(cooking) 또는 준비완료(ready) 상태에서 서빙완료로 전환
            if ($cur === 'cooking' || $cur === 'ready') {
                // 해당 아이템 서빙 완료 처리
                $pdo->prepare("UPDATE order_items SET item_status = 'SERVED' WHERE id = ?")->execute([$item_id]);
                if ($kds_sync_order_status === 1) {
                    // 전체 아이템이 모두 SERVED 인 경우 주문 상태도 SERVED 로 변경
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND LOWER(item_status) <> 'served'");
                    $chk->execute([$order_id]);
                    $remain = (int)$chk->fetchColumn();
                    if ($remain === 0) {
                        $pdo->prepare("UPDATE orders SET status = 'served' WHERE id = ? AND store_id = ?")->execute([$order_id, $store_id]);
                    }
                }
            }
        }
    }
    header("Location: store_orders.php"); exit;
}

// 상태 변경 처리 (DB에 소문자로 저장해 KDS·ENUM과 일치시키기)
if (isset($_GET['status']) && isset($_GET['oid'])) {
    $status = strtolower(trim((string)$_GET['status']));
    $oid = (int)$_GET['oid'];
    if (in_array($status, ['pending', 'cooking', 'served', 'paid'], true)) {
        // ORDER SERVED(Serve Done) 버튼: 해당 주문의 모든 메뉴를 서빙완료로 변경 후 주문 상태를 served 로
        if ($status === 'served') {
            // 이 주문의 모든 메뉴 아이템을 SERVED 로 변경
            $updItems = $pdo->prepare("UPDATE order_items SET item_status = 'SERVED' WHERE order_id = ?");
            $updItems->execute([$oid]);
        }
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
        (SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = o.id) AS total_items,
        (SELECT SUM(oi2.quantity) FROM order_items oi2 WHERE oi2.order_id = o.id AND LOWER(oi2.item_status) = 'served') AS served_items,
        (
            SELECT GROUP_CONCAT(CONCAT(mt.menu_name, ' x', oi3.quantity) SEPARATOR ', ')
            FROM order_items oi3
            JOIN menu_translations mt ON oi3.menu_id = mt.menu_id AND mt.lang_code = 'ko'
            WHERE oi3.order_id = o.id
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

    // 서빙완료 리스트(탭)는 리셋 시간과 상관없이 모든 served 주문을 표시
    if ($s === 'served') {
        $orders_served[] = $o;
    } else {
        $orders_active[] = $o;
    }
}

// 서빙완료 리스트 정렬 방식: order_id 내림/오름 또는 최근 완료시간
$sort = $_GET['sort'] ?? 'time';
if (!empty($orders_served)) {
    usort($orders_served, function($a, $b) use ($sort) {
        $idA = (int)($a['id'] ?? 0);
        $idB = (int)($b['id'] ?? 0);
        $tA = isset($a['updated_at']) ? strtotime($a['updated_at']) : strtotime($a['created_at'] ?? '1970-01-01');
        $tB = isset($b['updated_at']) ? strtotime($b['updated_at']) : strtotime($b['created_at'] ?? '1970-01-01');

        switch ($sort) {
            case 'id_asc':
                return $idA <=> $idB; // 작은 주문번호가 위로
            case 'id_desc':
                return $idB <=> $idA; // 큰 주문번호가 위로
            case 'time':
            default:
                // 최근 완료 시간 기준 (가장 최근이 위로)
                if ($tA === $tB) return $idB <=> $idA;
                return $tB <=> $tA;
        }
    });
}

// 메인 영역에서 어떤 리스트를 보여줄지 (실시간 / 서빙완료 리스트)
$view = $_GET['view'] ?? 'active';
$showServedList = ($view === 'served');
$orders_main = $showServedList ? $orders_served : $orders_active;

// 주문별 메뉴 리스트 (메뉴별 진행 상태 표시용)
$items_by_order = [];
$allOrderIds = [];
foreach ($orders as $o) {
    $allOrderIds[] = (int)$o['id'];
}
if (!empty($allOrderIds)) {
    $in = implode(',', $allOrderIds);
    $sqlItems = "
        SELECT oi.id AS item_id, oi.order_id, oi.menu_id, oi.quantity, oi.item_status, mt.menu_name
        FROM order_items oi
        JOIN menu_translations mt ON oi.menu_id = mt.menu_id AND mt.lang_code = 'ko'
        WHERE oi.order_id IN ($in)
        ORDER BY oi.order_id ASC, oi.id ASC
    ";
    try {
        $itStmt = $pdo->query($sqlItems);
        $rows = $itStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $oid = (int)$row['order_id'];
            if (!isset($items_by_order[$oid])) {
                $items_by_order[$oid] = [];
            }
            $items_by_order[$oid][] = $row;
        }
    } catch (Exception $e) {
        // 메뉴별 상태 조회 실패 시, 상세 표시는 생략
        $items_by_order = [];
    }
}

$btnKdsClass = $theme['btnKds'] ?? 'bg-sky-600 hover:bg-sky-700 text-white';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="10"> <title>ORDER TRACKET - 실시간 주문 리스트</title>
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
                <h1 class="text-3xl font-black text-slate-900">ORDER TRACKET</h1>
                <p class="text-xs text-slate-500 font-bold mt-1">
                    <?php if ($showServedList): ?>
                        서빙완료 리스트 · <?php echo htmlspecialchars($store['store_name']); ?>
                    <?php else: ?>
                        실시간 주문 리스트 · <?php echo htmlspecialchars($store['store_name']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="kitchen_display.php?store_id=<?php echo (int)$store_id; ?>" target="_blank" class="px-5 py-3 rounded-xl font-bold text-sm border border-slate-300 text-slate-700 bg-white hover:bg-slate-50">
                    KDS 화면 열기
                </a>
                <a href="store_orders.php" class="px-5 py-3 rounded-xl font-bold text-sm <?php echo $showServedList ? 'bg-slate-100 text-slate-700 border border-slate-300' : 'bg-slate-900 text-white'; ?>">
                    실시간 주문
                </a>
                <a href="store_orders.php?view=served" class="px-5 py-3 rounded-xl font-bold text-sm <?php echo $showServedList ? 'bg-emerald-500 text-white' : 'bg-emerald-100 text-emerald-700 border border-emerald-300'; ?>">
                    서빙완료 리스트
                </a>
                <?php if ($showServedList): 
                    $sort = $_GET['sort'] ?? 'time';
                ?>
                    <div class="flex items-center gap-1 text-[11px]">
                        <a href="store_orders.php?view=served&sort=id_desc" class="px-2.5 py-1 rounded-full border <?php echo $sort === 'id_desc' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-300'; ?>">
                            번호↓
                        </a>
                        <a href="store_orders.php?view=served&sort=id_asc" class="px-2.5 py-1 rounded-full border <?php echo $sort === 'id_asc' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-300'; ?>">
                            번호↑
                        </a>
                        <a href="store_orders.php?view=served&sort=time" class="px-2.5 py-1 rounded-full border <?php echo $sort === 'time' ? 'bg-emerald-500 text-white border-emerald-500' : 'bg-white text-slate-600 border-slate-300'; ?>">
                            최근완료
                        </a>
                    </div>
                <?php endif; ?>
                <button onclick="location.reload()" class="bg-slate-900 text-white px-5 py-3 rounded-xl font-bold text-sm">Refresh 🔄</button>
            </div>
        </header>

        <div class="flex gap-6">
            <div class="flex-1 min-w-0">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($orders_main as $o): 
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

                // 테마별 카드 색상 + 주방 호출 시 파스텔 레드 테두리/배경 강조
                $baseCard = $theme['card'];
                if ($status === 'pending') {
                    $stateColor = $theme['pending'];
                } elseif ($status === 'cooking') {
                    $stateColor = $theme['cooking'];
                } else {
                    $stateColor = $theme['served'];
                }
                $hasKitchenCall = !empty($o['kitchen_call']) && (int)$o['kitchen_call'] === 1;
                if ($hasKitchenCall) {
                    // 주방 호출된 주문은 항상 파스텔 레드 테두리/배경으로 강조
                    $stateColor = 'border-rose-400 bg-rose-50';
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
                    <span class="text-xs font-black text-white px-4 py-1.5 rounded-full <?php echo $btnColor; ?>"><?php echo $statusLabel; ?></span>
                </div>

                <div class="bg-white/60 p-4 rounded-xl border border-slate-200 mb-6">
                    <?php if (!empty($items_by_order[(int)$o['id']])): ?>
                    <div class="space-y-2">
                        <?php foreach ($items_by_order[(int)$o['id']] as $it): 
                            $itStatus = strtolower($it['item_status'] ?? '');
                            $isDone = ($itStatus === 'served');
                            if ($itStatus === 'served') {
                                $badgeClass = 'bg-emerald-100 text-emerald-600 border border-emerald-200';
                                $badgeLabel = '서빙완료';
                            } elseif ($itStatus === 'ready') {
                                $badgeClass = 'bg-sky-100 text-sky-600 border border-sky-200';
                                $badgeLabel = '준비완료';
                            } elseif ($itStatus === 'cooking') {
                                $badgeClass = 'bg-amber-100 text-amber-600 border border-amber-200';
                                $badgeLabel = '조리중';
                            } else {
                                $badgeClass = 'bg-slate-100 text-slate-500 border border-slate-200';
                                $badgeLabel = '대기';
                            }
                        ?>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-bold text-slate-700 truncate mr-3">
                                <?php echo htmlspecialchars($it['menu_name']); ?> x<?php echo (int)$it['quantity']; ?>
                            </span>
                            <?php if ($kds_mode === 'B' && ($itStatus === 'cooking' || $itStatus === 'ready')): ?>
                                <a href="?serve_item=<?php echo (int)$it['item_id']; ?>" class="px-4 py-2 rounded-full bg-emerald-500 text-white text-[13px] font-black hover:bg-emerald-600 min-w-[96px] text-center">
                                    <?php echo $itStatus === 'cooking' ? '조리중' : '준비완료'; ?>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 rounded-full <?php echo $badgeClass; ?> text-[11px] font-black">
                                    <?php echo $badgeLabel; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-sm font-bold text-slate-700 leading-relaxed">
                        <?php echo htmlspecialchars($o['items_summary']); ?>
                    </p>
                    <?php endif; ?>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase"><?php
                            $pm = $o['payment_method'] ?? 'CASH';
                            $pmLabels = ['CASH'=>'현금','CARD'=>'카드','MOBILE'=>'모바일','POINT'=>'포인트','GIFT_CARD'=>'기프트카드','MIXED'=>'혼합','OTHER'=>'기타'];
                            echo $pmLabels[$pm] ?? $pm;
                        ?></span>
                        <p class="text-sm font-black text-slate-600">Total: <?php echo number_format($o['total_amount']); ?> ₩</p>
                    </div>
                    <?php if (!empty($o['split_type']) && $o['split_type'] === 'BY_GUESTS' && !empty($o['split_guests']) && (int)$o['split_guests'] > 1): $n = (int)$o['split_guests']; $per = (int)floor($o['total_amount'] / $n); ?>
                    <p class="text-xs font-bold text-emerald-600 mt-1"><?php echo $n; ?>명 분할 · 1인당 <?php echo number_format($per); ?>원</p>
                    <?php endif; ?>
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
                    <?php if($status=='cooking' || $status=='pending'): ?>
                        <?php if ($hasKitchenCall): ?>
                            <div class="flex-1 bg-rose-100 text-rose-600 py-3 rounded-xl text-center font-black shadow-md border border-rose-300 text-[13px]">
                                주방에서 호출합니다.
                            </div>
                        <?php else: ?>
                            <a href="?status=SERVED&oid=<?php echo $o['id']; ?>"
                               onclick="return confirm('음식이 모두 준비되지 않았을 수 있습니다.\n정말 서빙완료로 처리하시겠습니까?');"
                               class="flex-1 bg-emerald-500 text-white py-3 rounded-xl text-center font-black shadow-md hover:bg-emerald-600">
                                Serve Done ✅
                            </a>
                        <?php endif; ?>
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
                    <h2 class="text-sm font-black mb-3 pb-2 border-b <?php echo $isContrast ? 'text-white border-slate-600' : 'text-slate-800 border-slate-200'; ?>">서빙완료 히스토리 (메뉴)</h2>
                    <?php
                    // 서빙완료 메뉴 히스토리 (KDS와 동일하게 메뉴 단위, 설정된 보관 시간 내)
                    $served_items_history = [];
                    try {
                        $histStmt = $pdo->prepare("
                            SELECT 
                                oi.id       AS item_id,
                                oi.order_id AS order_id,
                                oi.quantity AS quantity,
                                o.created_at,
                                o.updated_at,
                                mt.menu_name
                            FROM order_items oi
                            JOIN orders o          ON oi.order_id = o.id
                            JOIN menus m           ON oi.menu_id = m.id
                            JOIN menu_translations mt ON m.id = mt.menu_id AND mt.lang_code = 'ko'
                            WHERE o.store_id = ?
                              AND LOWER(oi.item_status) = 'served'
                              AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                            ORDER BY o.updated_at DESC, oi.id DESC
                            LIMIT 80
                        ");
                        $histStmt->execute([$store_id, $kds_history_hours]);
                        $served_items_history = $histStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $served_items_history = [];
                    }
                    ?>
                    <?php if (empty($served_items_history)): ?>
                        <p class="text-xs <?php echo $isContrast ? 'text-slate-300' : 'text-slate-500'; ?>">완료된 메뉴 없음</p>
                    <?php else: ?>
                        <ul class="space-y-2 max-h-[70vh] overflow-y-auto">
                            <?php $i = 0; foreach ($served_items_history as $row): 
                                $rowBg = ($i % 2 === 0) ? 'bg-white/80' : 'bg-slate-50/80';
                                $i++;
                                $timeOnly = date('H:i', strtotime($row['created_at']));
                            ?>
                                <li class="px-2 py-2 rounded-xl <?php echo $rowBg; ?> border border-slate-200/60">
                                    <div class="flex justify-between items-center text-[11px] <?php echo $isContrast ? 'text-slate-100' : 'text-slate-700'; ?>">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-bold truncate">
                                                #<?php echo (int)$row['order_id']; ?> · <?php echo htmlspecialchars($row['menu_name']); ?> x<?php echo (int)$row['quantity']; ?>
                                            </div>
                                            <div class="text-[10px] <?php echo $isContrast ? 'text-slate-300' : 'text-slate-500'; ?>">
                                                <?php echo $timeOnly; ?>
                                            </div>
                                        </div>
                                    </div>
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