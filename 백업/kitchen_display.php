<?php
// kitchen_display.php - KDS (주방 디스플레이), store_id 기준 주문 표시
include 'db_config.php';
include 'common.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// store_setting.php 날짜/시간 표기 형식(kds_datetime_locale)과 동일하게 포맷 (24시간)
function formatKdsDatetime($locale, $timestamp = null) {
    $ts = $timestamp ?? time();
    switch ($locale) {
        case 'en': return date('m/d/Y H:i', $ts);
        case 'ja': return date('Y/m/d H:i', $ts);
        case 'zh': return date('Y-m-d H:i', $ts);
        case 'th':
        case 'id': return date('d-m-Y H:i', $ts);
        case 'vi': return date('d/m/Y H:i', $ts);
        case 'ko':
        default:   return date('Y-m-d H:i', $ts);
    }
}

// KDS는 URL에 store_id 지정 또는 로그인 세션 사용 (미지정 시 1)
$store_id = (int)($_GET['store_id'] ?? $_SESSION['store_id'] ?? 1);
if ($store_id < 1) { $store_id = 1; }

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // kds 관련 컬럼 없으면 한 번만 추가 (store_setting과 동일)
    $chk = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'kds_sync_order_status'");
    if ($chk && $chk->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN kds_sync_order_status TINYINT(1) NOT NULL DEFAULT 1");
    }
    $chk2 = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'kds_kitchen_theme'");
    if ($chk2 && $chk2->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN kds_kitchen_theme VARCHAR(50) DEFAULT NULL");
    }
} catch (Exception $e) { /* 무시 */ }

try {
    // 가맹점 설정: KDS 테마, 키친 디스플레이 테마, 조리완료 연동, 날짜/시간 표기 형식
    $st_store = $pdo->prepare("SELECT kds_theme, kds_kitchen_theme, kds_sync_order_status, kds_datetime_locale FROM stores WHERE id = ?");
    $st_store->execute([$store_id]);
    $store_row = $st_store->fetch(PDO::FETCH_ASSOC);
    $kitchen_theme_key = $store_row['kds_kitchen_theme'] ?? ($store_row['kds_theme'] ?? 'sky');
    $kds_sync_order_status = (int)($store_row['kds_sync_order_status'] ?? 1);
    $kds_datetime_locale = $store_row['kds_datetime_locale'] ?? 'ko';

    // KDS 전용 테마 팔레트 (가맹점 관리 > KDS & 알림 설정 > 키친 디스플레이 테마에서 선택)
    $kds_themes = [
        'sky' => [
            'bg' => 'bg-slate-100',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-500',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-sky-400/60',
            'cardHeader' => 'bg-sky-50/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-sky-500',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-slate-200',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-sky-600',
            'btnOkBg' => 'bg-green-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-slate-300',
            'emptyText' => 'text-slate-500',
        ],
        'forest' => [
            'bg' => 'bg-emerald-50',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-600',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-emerald-400/60',
            'cardHeader' => 'bg-emerald-50/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-emerald-500',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-emerald-200',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-emerald-600',
            'btnOkBg' => 'bg-green-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-emerald-300',
            'emptyText' => 'text-slate-500',
        ],
        'sunset' => [
            'bg' => 'bg-orange-50',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-600',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-amber-400/60',
            'cardHeader' => 'bg-amber-50/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-amber-500',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-amber-200',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-amber-600',
            'btnOkBg' => 'bg-green-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-amber-300',
            'emptyText' => 'text-slate-500',
        ],
        'pastel' => [
            'bg' => 'bg-slate-50',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-500',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-indigo-300/60',
            'cardHeader' => 'bg-indigo-50/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-indigo-400',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-slate-200',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-indigo-500',
            'btnOkBg' => 'bg-green-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-slate-300',
            'emptyText' => 'text-slate-500',
        ],
        'mono' => [
            'bg' => 'bg-slate-100',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-500',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-slate-400/50',
            'cardHeader' => 'bg-slate-100/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-slate-600',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-slate-300',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-slate-600',
            'btnOkBg' => 'bg-slate-600',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-slate-400',
            'emptyText' => 'text-slate-500',
        ],
        'contrast' => [
            'bg' => 'bg-slate-900',
            'text' => 'text-white',
            'textMuted' => 'text-slate-300',
            'card' => 'bg-slate-800/95',
            'cardBorder' => 'border border-slate-500/50',
            'cardHeader' => 'bg-slate-700/80',
            'cardHeaderText' => 'text-white',
            'btnCompleteBg' => 'bg-emerald-500',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-700/50 border border-slate-600',
            'itemName' => 'text-white',
            'itemQty' => 'text-emerald-300',
            'btnOkBg' => 'bg-emerald-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-slate-600',
            'emptyText' => 'text-slate-300',
        ],
    ];
    $kitchen_theme = $kds_themes[$kitchen_theme_key] ?? $kds_themes['sky'];

    // 1. 단일 메뉴 아이템 조리 완료 (기존 OK 버튼): 같은 매장이면 완료 처리
    if (isset($_GET['complete_item'])) {
        $target_id = (int)$_GET['complete_item'];
        $redirect = 'kitchen_display.php?store_id=' . $store_id;
        $ir = $pdo->prepare("SELECT o.store_id FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.id = ?");
        $ir->execute([$target_id]);
        $ir = $ir->fetch(PDO::FETCH_ASSOC);
        if ($ir && (int)($ir['store_id'] ?? 0) === $store_id) {
            $pdo->prepare("UPDATE order_items SET item_status = 'served' WHERE id = ?")->execute([$target_id]);
        } else if ($ir) {
            $redirect .= '&sync_fail=1';
        }
        header('Location: ' . $redirect);
        exit;
    }

    // 2. 주문 단위 조리 완료: 같은 매장 주문이면 완료 처리 (order_items + orders.status = served → store_orders 조리완료 반영)
    $sync_fail = 0;
    if (isset($_GET['complete_order'])) {
        $order_id = (int)$_GET['complete_order'];
        $chk = $pdo->prepare("SELECT store_id FROM orders WHERE id = ?");
        $chk->execute([$order_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)$row['store_id'] !== $store_id) {
            $sync_fail = 1;
        } else {
            $pdo->prepare("UPDATE order_items SET item_status = 'served' WHERE order_id = ?")->execute([$order_id]);
            $pdo->prepare("UPDATE orders SET status = 'served' WHERE id = ? AND store_id = ?")->execute([$order_id, $store_id]);
        }
        $redirect = 'kitchen_display.php?store_id=' . $store_id;
        if ($sync_fail) $redirect .= '&sync_fail=1';
        header('Location: ' . $redirect);
        exit;
    }

    // 3. 해당 가맹점 주문 중 미완료(미서빙) 항목만 조회
    $sql = "SELECT o.id AS order_id, o.created_at,
            oi.id AS item_id, oi.quantity, oi.item_status, mt.menu_name
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id AND oi.item_status != 'served'
            JOIN menus m ON oi.menu_id = m.id
            JOIN menu_translations mt ON m.id = mt.menu_id AND mt.lang_code = 'ko'
            WHERE o.store_id = ? AND o.status != 'paid'
            ORDER BY o.created_at ASC, oi.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$store_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = [];
    foreach ($rows as $r) {
        $oid = $r['order_id'];
        if (!isset($orders[$oid])) {
            $orders[$oid] = [
                'order_id'   => $oid,
                'created_at' => $r['created_at'],
                'items'      => [],
            ];
        }
        $orders[$oid]['items'][] = [
            'item_id' => $r['item_id'],
            'menu_name' => $r['menu_name'],
            'quantity' => $r['quantity'],
            'item_status' => $r['item_status'],
        ];
    }

    // 주문 히스토리: 모든 order_items가 served인 주문만 최근 30건
    $sth = $pdo->prepare("
        SELECT 
            o.id,
            o.created_at,
            GROUP_CONCAT(CONCAT(mt.menu_name, ' x', oi.quantity) ORDER BY oi.id SEPARATOR ', ') AS items_summary
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        JOIN menus m ON oi.menu_id = m.id
        JOIN menu_translations mt ON m.id = mt.menu_id AND mt.lang_code = 'ko'
        WHERE o.store_id = ?
          AND NOT EXISTS (
              SELECT 1 FROM order_items oi2
              WHERE oi2.order_id = o.id
                AND oi2.item_status <> 'served'
          )
        GROUP BY o.id, o.created_at
        ORDER BY o.id DESC
        LIMIT 30
    ");
    $sth->execute([$store_id]);
    $history_served = $sth->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "DB 접속 또는 쿼리 오류: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="7">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KITCHEN KDS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="<?php echo $kitchen_theme['bg']; ?> <?php echo $kitchen_theme['text']; ?> p-4 min-h-screen">

    <header class="flex justify-between items-center mb-6 border-b border-slate-300 pb-4 <?php echo $kitchen_theme_key === 'contrast' ? 'border-slate-600' : ''; ?>">
        <h1 class="text-2xl font-black <?php echo $kitchen_theme_key === 'contrast' ? 'text-emerald-400' : 'text-sky-600'; ?>">KITCHEN KDS</h1>
        <p class="text-sm font-mono <?php echo $kitchen_theme['textMuted']; ?>"><?php echo formatKdsDatetime($kds_datetime_locale); ?> · Store #<?php echo $store_id; ?></p>
    </header>

    <?php if (!empty($_GET['sync_fail'])): ?>
        <div class="mb-4 p-3 rounded-xl bg-amber-100 border border-amber-300 text-amber-800 text-xs font-bold">
            ⚠️ 조리 완료는 처리되었으나, 주문 상태 연동(store_orders 반영)이 되지 않았습니다. 해당 주문이 현재 KDS(Store #<?php echo $store_id; ?>) 소속인지 확인하세요.
        </div>
    <?php endif; ?>
    <div class="flex gap-6">
        <div class="flex-1 min-w-0">
            <?php if (empty($orders)): ?>
                <div class="flex flex-col items-center justify-center py-40 border-2 border-dashed rounded-3xl <?php echo $kitchen_theme['emptyBorder']; ?>">
                    <p class="text-xl font-bold <?php echo $kitchen_theme['emptyText']; ?>">대기 중인 주문이 없습니다.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($orders as $ord): 
                        $table_label = 'Order #' . $ord['order_id'];
                    ?>
                        <div class="<?php echo $kitchen_theme['card']; ?> rounded-2xl overflow-hidden shadow-2xl <?php echo $kitchen_theme['cardBorder']; ?>">
                            <div class="<?php echo $kitchen_theme['cardHeader']; ?> p-4 flex justify-between items-center">
                                <span class="text-2xl font-black <?php echo $kitchen_theme['cardHeaderText']; ?>"><?php echo htmlspecialchars($table_label); ?></span>
                                <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&complete_order=<?php echo $ord['order_id']; ?>"
                                   class="<?php echo $kitchen_theme['btnCompleteBg']; ?> <?php echo $kitchen_theme['btnCompleteText']; ?> px-4 py-2 rounded-xl font-black text-sm hover:opacity-90">
                                    조리 완료
                                </a>
                            </div>
                            <div class="p-4 space-y-3">
                                <?php foreach ($ord['items'] as $it): ?>
                                    <div class="flex justify-between items-center p-4 rounded-xl <?php echo $kitchen_theme['itemRow']; ?>">
                                        <div class="flex-1">
                                            <span class="text-xl font-bold <?php echo $kitchen_theme['itemName']; ?>"><?php echo htmlspecialchars($it['menu_name']); ?></span>
                                            <span class="text-2xl font-black ml-2 <?php echo $kitchen_theme['itemQty']; ?>">x<?php echo (int)$it['quantity']; ?></span>
                                        </div>
                                        <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&complete_item=<?php echo $it['item_id']; ?>"
                                           class="<?php echo $kitchen_theme['btnOkBg']; ?> <?php echo $kitchen_theme['btnOkText']; ?> px-5 py-2 rounded-xl font-black hover:opacity-90">
                                            OK
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="w-72 shrink-0">
            <div class="<?php echo $kitchen_theme['card']; ?> rounded-2xl overflow-hidden shadow-xl <?php echo $kitchen_theme['cardBorder']; ?> p-4 sticky top-4">
                <h2 class="text-sm font-black <?php echo $kitchen_theme['cardHeaderText']; ?> mb-3 pb-2 border-b <?php echo $kitchen_theme_key === 'contrast' ? 'border-slate-600' : 'border-slate-200'; ?>">주문 히스토리</h2>
                <?php if (empty($history_served)): ?>
                    <p class="text-xs <?php echo $kitchen_theme['emptyText']; ?>">완료된 주문 없음</p>
                <?php else: ?>
                    <ul class="space-y-2 max-h-[70vh] overflow-y-auto">
                        <?php foreach ($history_served as $h): ?>
                            <li class="text-xs <?php echo $kitchen_theme['textMuted']; ?> flex justify-between items-center gap-2 border-t border-slate-200 first:border-t-0 pt-2 mt-2 first:pt-0 first:mt-0">
                                <div class="flex-1 min-w-0">
                                    <?php
                                        $tbl = '-';
                                        $timeOnly = date('H:i', strtotime($h['created_at']));
                                    ?>
                                    <div class="font-bold <?php echo $kitchen_theme['itemName']; ?>">
                                        <?php echo (int)$h['id']; ?> / <?php echo $timeOnly; ?>
                                    </div>
                                    <?php if (!empty($h['items_summary'])): ?>
                                        <div class="text-[11px] leading-snug">
                                            <?php echo htmlspecialchars($h['items_summary']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>
