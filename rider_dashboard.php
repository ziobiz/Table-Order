<?php
// rider_dashboard.php - 본사 Rider 대시보드 (할당 대기 / 내 배달 / 상태 변경, riders 테이블)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['rider_id'])) {
    header("Location: login.php"); exit;
}
$rider_id = (int)$_SESSION['rider_id'];
$rider_name = $_SESSION['rider_name'] ?? '기사';
$rider_store_id = isset($_SESSION['store_id']) ? (int)$_SESSION['store_id'] : null;

// 상태 변경 또는 수락 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id > 0) {
        if ($action === 'assign') {
            $st = $pdo->prepare("UPDATE orders SET rider_id = ?, delivery_status = 'assigned' WHERE id = ? AND order_type = 'delivery' AND (rider_id IS NULL OR rider_id = 0) AND (delivery_status IS NULL OR delivery_status = '' OR delivery_status = 'unassigned')");
            $st->execute([$rider_id, $order_id]);
        } elseif ($action === 'picked_up') {
            $st = $pdo->prepare("UPDATE orders SET delivery_status = 'picked_up' WHERE id = ? AND rider_id = ?");
            $st->execute([$order_id, $rider_id]);
        } elseif ($action === 'on_way') {
            $st = $pdo->prepare("UPDATE orders SET delivery_status = 'on_way' WHERE id = ? AND rider_id = ?");
            $st->execute([$order_id, $rider_id]);
        } elseif ($action === 'delivered') {
            $st = $pdo->prepare("UPDATE orders SET delivery_status = 'delivered', status = 'completed' WHERE id = ? AND rider_id = ?");
            $st->execute([$order_id, $rider_id]);
        }
        header("Location: rider_dashboard.php"); exit;
    }
}

// 배달 할당 대기: 배달 주문, 아직 기사 미할당 (소속 매장이면 해당 매장만, 소속 없으면 전체)
$where_store = $rider_store_id ? " AND o.store_id = " . $rider_store_id : "";
$sql_unassigned = "
    SELECT o.*,
        (SELECT GROUP_CONCAT(CONCAT(mt.menu_name, ' x', oi.quantity) SEPARATOR ', ')
         FROM order_items oi
         JOIN menu_translations mt ON oi.menu_id = mt.menu_id AND mt.lang_code = 'ko'
         WHERE oi.order_id = o.id) AS items_summary
    FROM orders o
    WHERE o.order_type = 'delivery'
    AND (o.rider_id IS NULL OR o.rider_id = 0)
    AND (o.delivery_status IS NULL OR o.delivery_status = '' OR o.delivery_status = 'unassigned')
    AND o.status IN ('pending','paid','cooking','served')
    $where_store
    ORDER BY o.id DESC
";
$unassigned = $pdo->query($sql_unassigned)->fetchAll(PDO::FETCH_ASSOC);

// 내 배달: 내가 수락한 배달 중 미완료
$sql_mine = "
    SELECT o.*,
        (SELECT GROUP_CONCAT(CONCAT(mt.menu_name, ' x', oi.quantity) SEPARATOR ', ')
         FROM order_items oi
         JOIN menu_translations mt ON oi.menu_id = mt.menu_id AND mt.lang_code = 'ko'
         WHERE oi.order_id = o.id) AS items_summary
    FROM orders o
    WHERE o.rider_id = ? AND o.delivery_status != 'delivered' AND o.delivery_status IS NOT NULL
    ORDER BY o.id DESC
";
$st_mine = $pdo->prepare($sql_mine);
$st_mine->execute([$rider_id]);
$my_deliveries = $st_mine->fetchAll(PDO::FETCH_ASSOC);

$delivery_status_label = [
    'assigned' => '수락함',
    'picked_up' => '픽업 완료',
    'on_way' => '배달 중',
    'delivered' => '배달 완료',
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>본사 Rider Dashboard - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard'; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <header class="bg-white border-b border-slate-200 px-4 py-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-slate-800 uppercase tracking-tight">본사 Rider Dashboard</h1>
            <p class="text-xs text-slate-500 font-bold mt-0.5"><?php echo htmlspecialchars($rider_name); ?> 님 · 본사 Rider</p>
        </div>
        <a href="logout.php" class="bg-slate-200 text-slate-700 px-5 py-2.5 rounded-2xl text-xs font-black uppercase hover:bg-slate-300 transition-all">Logout</a>
    </header>

    <main class="max-w-4xl mx-auto p-4 space-y-8">
        <!-- 배달 할당 대기 -->
        <section class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-amber-50 border-b border-amber-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">배달 할당 대기</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">수락하면 내 배달로 넘어갑니다.</p>
            </div>
            <?php if (empty($unassigned)): ?>
                <div class="p-8 text-center text-slate-400 text-sm font-bold">할당 대기 중인 배달이 없습니다.</div>
            <?php else: ?>
                <ul class="divide-y divide-slate-50">
                    <?php foreach ($unassigned as $o): ?>
                    <li class="p-5 hover:bg-slate-50/50">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <span class="text-[10px] font-black text-amber-600 uppercase">#<?php echo $o['id']; ?></span>
                                <p class="text-sm font-black text-slate-800 mt-1"><?php echo htmlspecialchars($o['address'] ?: '주소 없음'); ?></p>
                                <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($o['guest_name'] ?: ''); ?> · <?php echo htmlspecialchars($o['tel'] ?: $o['guest_tel'] ?: '-'); ?></p>
                                <p class="text-[10px] text-slate-400 mt-1"><?php echo htmlspecialchars($o['items_summary'] ?? ''); ?></p>
                                <p class="text-xs font-bold text-slate-600 mt-1"><?php echo number_format($o['total_amount']); ?>원</p>
                            </div>
                            <form method="POST" class="shrink-0">
                                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                <input type="hidden" name="action" value="assign">
                                <button type="submit" class="px-5 py-2.5 bg-amber-500 text-white rounded-xl text-xs font-black uppercase hover:bg-amber-600">수락</button>
                            </form>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- 내 배달 -->
        <section class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-sky-50 border-b border-sky-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">내 배달</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">픽업 완료 → 배달 중 → 배달 완료 순으로 진행하세요.</p>
            </div>
            <?php if (empty($my_deliveries)): ?>
                <div class="p-8 text-center text-slate-400 text-sm font-bold">진행 중인 배달이 없습니다.</div>
            <?php else: ?>
                <ul class="divide-y divide-slate-50">
                    <?php foreach ($my_deliveries as $o):
                        $ds = $o['delivery_status'] ?? 'assigned';
                    ?>
                    <li class="p-5 hover:bg-slate-50/50">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <span class="text-[10px] font-black text-sky-600 uppercase">#<?php echo $o['id']; ?></span>
                                <span class="ml-2 text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-600"><?php echo $delivery_status_label[$ds] ?? $ds; ?></span>
                                <p class="text-sm font-black text-slate-800 mt-1"><?php echo htmlspecialchars($o['address'] ?: '주소 없음'); ?></p>
                                <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($o['guest_name'] ?: ''); ?> · <?php echo htmlspecialchars($o['tel'] ?: $o['guest_tel'] ?: '-'); ?></p>
                                <p class="text-[10px] text-slate-400 mt-1"><?php echo htmlspecialchars($o['items_summary'] ?? ''); ?></p>
                                <p class="text-xs font-bold text-slate-600 mt-1"><?php echo number_format($o['total_amount']); ?>원</p>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0">
                                <?php if ($ds === 'assigned'): ?>
                                <form method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <input type="hidden" name="action" value="picked_up">
                                    <button type="submit" class="px-4 py-2 bg-sky-500 text-white rounded-xl text-[10px] font-black uppercase hover:bg-sky-600">픽업 완료</button>
                                </form>
                                <?php elseif ($ds === 'picked_up'): ?>
                                <form method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <input type="hidden" name="action" value="on_way">
                                    <button type="submit" class="px-4 py-2 bg-sky-500 text-white rounded-xl text-[10px] font-black uppercase hover:bg-sky-600">배달 중</button>
                                </form>
                                <?php elseif ($ds === 'on_way'): ?>
                                <form method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <input type="hidden" name="action" value="delivered">
                                    <button type="submit" class="px-4 py-2 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase hover:bg-emerald-600">배달 완료</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
