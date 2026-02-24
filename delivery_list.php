<?php
// delivery_list.php - 배달원 전용 배달 관리 페이지
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// 배달원 권한 체크
if (($_SESSION['admin_role'] ?? '') !== 'DELIVERY') {
    echo "<script>alert('배달원 전용 페이지입니다.'); location.href='login.php';</script>"; exit;
}

$store_id = $_SESSION['store_id'];

// 배달 상태 변경 로직
if (isset($_POST['update_delivery'])) {
    $new_status = $_POST['status']; // 'shipping', 'completed' 등
    $order_id = $_POST['order_id'];
    
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND order_type = 'delivery'");
    $stmt->execute([$new_status, $order_id]);
    header("Location: delivery_list.php"); exit;
}

// 배달 주문 리스트 조회 (조리완료 또는 배달 중인 것만)
$sql = "SELECT * FROM orders WHERE store_id = ? AND order_type = 'delivery' AND status IN ('cooking', 'shipping') ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$store_id]);
$deliveries = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery App - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen pb-10">
    <header class="bg-slate-900 p-6 text-white sticky top-0 z-10">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-[10px] font-black text-sky-400 uppercase tracking-widest">Delivery Partner</span>
                <h2 class="text-xl font-black italic uppercase"><?php echo $_SESSION['name']; ?> 님</h2>
            </div>
            <a href="logout.php" class="text-[10px] font-black bg-slate-800 px-4 py-2 rounded-lg">LOGOUT</a>
        </div>
    </header>

    <main class="p-4 space-y-4">
        <?php if(empty($deliveries)): ?>
            <p class="text-center text-slate-400 py-20 font-bold">현재 배달할 주문이 없습니다.</p>
        <?php endif; ?>

        <?php foreach($deliveries as $order): ?>
        <div class="bg-white rounded-3xl shadow-lg border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-50">
                <div class="flex justify-between items-start mb-4">
                    <span class="bg-rose-100 text-rose-500 px-3 py-1 rounded-full text-[10px] font-black uppercase">Order #<?php echo $order['id']; ?></span>
                    <span class="text-slate-400 text-xs font-bold"><?php echo date('H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <h3 class="text-lg font-black text-slate-800 mb-1">배달 주소 : <?php echo $order['address'] ?? '매장 확인 필요'; ?></h3>
                <p class="text-sm text-slate-500 font-medium">전화번호 : <?php echo $order['tel'] ?? '매장 확인 필요'; ?></p>
            </div>

            <div class="p-4 bg-slate-50 flex gap-2">
                <?php if($order['status'] == 'cooking'): ?>
                    <form method="POST" class="w-full">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="status" value="shipping">
                        <button type="submit" name="update_delivery" class="w-full p-4 bg-sky-500 text-white rounded-2xl font-black uppercase text-sm shadow-lg shadow-sky-100">배달 시작</button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="w-full">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" name="update_delivery" class="w-full p-4 bg-emerald-500 text-white rounded-2xl font-black uppercase text-sm shadow-lg shadow-emerald-100">배달 완료</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </main>
</body>
</html>