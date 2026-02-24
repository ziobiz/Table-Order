<?php
// driver_dashboard.php - Rider 대시보드 (HQ: 대기 목록+수락 / DELIVER: 내 배달+상태 변경)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['driver_id'])) { header("Location: login.php"); exit; }
$driver_id = (int)$_SESSION['driver_id'];
$driver_type = $_SESSION['driver_type'] ?? 'DELIVER';
$driver_name = $_SESSION['driver_name'] ?? 'Rider';

// HQ: 콜 수락 (동시성 처리)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_delivery']) && $driver_type === 'HQ') {
    $delivery_id = (int)($_POST['delivery_id'] ?? 0);
    if ($delivery_id > 0) {
        try {
            $pdo->beginTransaction();
            $del = $pdo->prepare("SELECT id, status, driver_id FROM deliveries WHERE id = ? FOR UPDATE");
            $del->execute([$delivery_id]);
            $row = $del->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['status'] === 'WAITING' && $row['driver_id'] === null) {
                $pdo->prepare("UPDATE deliveries SET driver_id = ?, dispatch_type = 'AUTO', status = 'ACCEPTED', updated_at = NOW() WHERE id = ?")
                    ->execute([$driver_id, $delivery_id]);
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
        header("Location: driver_dashboard.php"); exit;
    }
}

// 상태 변경 (픽업 완료 / 배달 완료)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $delivery_id = (int)($_POST['delivery_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    if ($delivery_id > 0 && in_array($status, ['PICKED_UP', 'DELIVERED'], true)) {
        $chk = $pdo->prepare("SELECT id FROM deliveries WHERE id = ? AND driver_id = ?");
        $chk->execute([$delivery_id, $driver_id]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE deliveries SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $delivery_id]);
            if ($status === 'DELIVERED') {
                $pdo->prepare("UPDATE orders o JOIN deliveries d ON d.order_id = o.id SET o.status = 'completed' WHERE d.id = ?")->execute([$delivery_id]);
            }
            header("Location: driver_dashboard.php"); exit;
        }
    }
}

// HQ: 대기 중인 배달 목록 (수락용)
$available = [];
if ($driver_type === 'HQ') {
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
        $available = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// 내 배달 (ACCEPTED, PICKED_UP, DELIVERED 미완료)
$my_deliveries = [];
try {
    $stmt = $pdo->prepare("
        SELECT d.id AS delivery_id, d.order_id, d.status, d.dispatch_type, d.created_at,
               o.address, o.tel, o.guest_name, o.guest_tel, o.total_amount,
               s.store_name,
               (SELECT GROUP_CONCAT(CONCAT(mt.menu_name, ' x', oi.quantity) SEPARATOR ', ')
                FROM order_items oi
                JOIN menu_translations mt ON oi.menu_id = mt.menu_id AND mt.lang_code = 'ko'
                WHERE oi.order_id = o.id) AS items_summary
        FROM deliveries d
        JOIN orders o ON o.id = d.order_id
        LEFT JOIN stores s ON s.id = o.store_id
        WHERE d.driver_id = ? AND d.status != 'DELIVERED'
        ORDER BY d.id DESC
    ");
    $stmt->execute([$driver_id]);
    $my_deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$status_label = ['WAITING' => '대기', 'ACCEPTED' => '배차됨', 'PICKED_UP' => '픽업 완료', 'DELIVERED' => '배달 완료'];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <header class="bg-white border-b border-slate-200 px-4 py-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-slate-800 uppercase tracking-tight">Rider Dashboard</h1>
            <p class="text-xs text-slate-500 font-bold mt-0.5"><?php echo htmlspecialchars($driver_name); ?> · <?php echo $driver_type === 'HQ' ? '본사 소속' : 'Deliver'; ?></p>
        </div>
        <a href="logout.php" class="bg-slate-200 text-slate-700 px-5 py-2.5 rounded-2xl text-xs font-black uppercase hover:bg-slate-300 transition-all">Logout</a>
    </header>

    <main class="max-w-4xl mx-auto p-4 space-y-8">
        <?php if ($driver_type === 'HQ'): ?>
        <!-- HQ: 대기 중인 배달 (콜 수락) -->
        <section class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-sky-50 border-b border-sky-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">대기 중인 배달 (수락 가능)</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">수락하면 내 배달로 넘어갑니다.</p>
            </div>
            <?php if (empty($available)): ?>
                <div class="p-8 text-center text-slate-400 text-sm font-bold">수락 가능한 배달이 없습니다.</div>
            <?php else: ?>
                <ul class="divide-y divide-slate-50">
                    <?php foreach ($available as $a): ?>
                    <li class="p-5 hover:bg-slate-50/50 flex flex-wrap items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <span class="text-[10px] font-black text-sky-600 uppercase">#<?php echo (int)$a['order_id']; ?></span>
                            <p class="text-sm font-black text-slate-800 mt-1"><?php echo htmlspecialchars($a['store_name'] ?? ''); ?> · <?php echo htmlspecialchars($a['address'] ?: '주소 없음'); ?></p>
                            <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($a['guest_name'] ?: ''); ?> · <?php echo htmlspecialchars($a['tel'] ?: $a['guest_tel'] ?: '-'); ?></p>
                            <p class="text-xs font-bold text-slate-600 mt-1"><?php echo number_format($a['total_amount']); ?>원</p>
                        </div>
                        <form method="POST" class="shrink-0" onsubmit="return confirm('이 배달을 수락하시겠습니까?');">
                            <input type="hidden" name="accept_delivery" value="1">
                            <input type="hidden" name="delivery_id" value="<?php echo (int)$a['delivery_id']; ?>">
                            <button type="submit" class="px-5 py-2.5 bg-sky-500 text-white rounded-xl text-xs font-black uppercase hover:bg-sky-600">수락</button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- 내 배달 (픽업/배달 완료 진행) -->
        <section class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-amber-50 border-b border-amber-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">내 배달</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">픽업 완료 → 배달 완료 순으로 진행하세요.</p>
            </div>
            <?php if (empty($my_deliveries)): ?>
                <div class="p-8 text-center text-slate-400 text-sm font-bold">진행 중인 배달이 없습니다.</div>
            <?php else: ?>
                <ul class="divide-y divide-slate-50">
                    <?php foreach ($my_deliveries as $m):
                        $st = $m['status'] ?? 'ACCEPTED';
                    ?>
                    <li class="p-5 hover:bg-slate-50/50">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <span class="text-[10px] font-black text-amber-600 uppercase">#<?php echo (int)$m['order_id']; ?></span>
                                <span class="ml-2 text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-600"><?php echo $status_label[$st] ?? $st; ?></span>
                                <p class="text-sm font-black text-slate-800 mt-1"><?php echo htmlspecialchars($m['address'] ?: '주소 없음'); ?></p>
                                <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($m['guest_name'] ?: ''); ?> · <?php echo htmlspecialchars($m['tel'] ?: $m['guest_tel'] ?: '-'); ?></p>
                                <p class="text-[10px] text-slate-400 mt-1"><?php echo htmlspecialchars($m['items_summary'] ?? ''); ?></p>
                                <p class="text-xs font-bold text-slate-600 mt-1"><?php echo number_format($m['total_amount']); ?>원</p>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0">
                                <?php if ($st === 'ACCEPTED'): ?>
                                <form method="POST">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="delivery_id" value="<?php echo (int)$m['delivery_id']; ?>">
                                    <input type="hidden" name="status" value="PICKED_UP">
                                    <button type="submit" class="px-4 py-2 bg-sky-500 text-white rounded-xl text-[10px] font-black uppercase hover:bg-sky-600">픽업 완료</button>
                                </form>
                                <?php elseif ($st === 'PICKED_UP'): ?>
                                <form method="POST">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="delivery_id" value="<?php echo (int)$m['delivery_id']; ?>">
                                    <input type="hidden" name="status" value="DELIVERED">
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
