<?php
// admin_waiting_manage.php - 실시간 대기 고객 관리 대시보드
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안 구문] 로그인 여부 및 권한 체크 (배달원은 접근 불가)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}
if (($_SESSION['admin_role'] ?? '') === 'DELIVERY') {
    header("Location: delivery_list.php"); exit;
}

$my_store_id = $_SESSION['store_id'];
$my_role = $_SESSION['admin_role'];

// 1. 상태 변경 처리 (호출, 입장완료, 취소)
if (isset($_POST['update_status'])) {
    $id = $_POST['waiting_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $pdo->prepare("UPDATE waiting_list SET status = ? WHERE id = ? AND store_id = ?");
    $stmt->execute([$new_status, $id, $my_store_id]);

    $admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['store_id'] ?? 0);
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? $_SESSION['name'] ?? ('id_' . $admin_id);
    log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_waiting_manage', 'update', 'waiting', (string)$id, "대기 상태 변경: ID {$id} → {$new_status}");

    // [참고] 여기서 $new_status가 'called'일 때 알림톡 API 함수를 호출하면 됩니다.
    if ($new_status === 'called') {
        // sendNotification($id); // 추후 API 연동 시 주석 해제
    }
    
    header("Location: admin_waiting_manage.php"); exit;
}

// 2. 대기 목록 조회 (대기 중이거나 호출된 고객만 표시)
$sql = "SELECT * FROM waiting_list 
        WHERE store_id = $my_store_id 
        AND status IN ('waiting', 'called') 
        AND DATE(created_at) = CURDATE() 
        ORDER BY waiting_num ASC";
$waitings = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Waiting Management - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="30"> <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    
    <nav class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 p-6 sticky top-0 z-50">
        <div class="max-w-[96rem] mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-6">
                <h1 class="text-2xl font-black italic text-sky-400 uppercase tracking-tighter">Waiting Master</h1>
                <span class="px-3 py-1 bg-slate-800 rounded-full text-[10px] font-black text-slate-400 uppercase tracking-widest">Live Status</span>
            </div>
            <div class="flex items-center space-x-6">
                <span class="text-xs font-bold text-slate-400"><?php echo $_SESSION['name']; ?> 님</span>
                <a href="admin_order_dashboard.php" class="text-xs font-black text-slate-500 hover:text-white transition uppercase">Orders</a>
                <a href="logout.php" class="bg-rose-500/10 text-rose-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-[96rem] mx-auto p-8">
        <div class="flex justify-between items-end mb-10">
            <div>
                <h2 class="text-4xl font-black text-white italic tracking-tighter uppercase">Waiting List</h2>
                <p class="text-slate-500 text-xs font-bold uppercase mt-2 italic">현재 <?php echo count($waitings); ?>팀이 대기 중입니다.</p>
            </div>
            <button onclick="window.open('waiting_register.php?store_id=<?php echo $my_store_id; ?>', '_blank')" class="bg-slate-800 text-slate-300 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-700 transition">Open Register Kiosk</button>
        </div>

        <?php if(empty($waitings)): ?>
        <div class="py-40 text-center border-2 border-dashed border-slate-800 rounded-[3rem] opacity-30">
            <p class="text-2xl font-black uppercase tracking-widest">No Waiting Teams</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($waitings as $w): 
                $is_called = ($w['status'] == 'called');
                $card_bg = $is_called ? 'bg-sky-500/10 border-sky-500/50' : 'bg-slate-900 border-slate-800';
            ?>
            <div class="<?php echo $card_bg; ?> rounded-[2.5rem] border p-8 shadow-2xl transition-all relative overflow-hidden">
                <?php if($is_called): ?>
                    <div class="absolute top-0 right-0 bg-sky-500 text-[10px] font-black px-4 py-1 rounded-bl-2xl uppercase text-white animate-pulse">Called</div>
                <?php endif; ?>

                <div class="flex justify-between items-start mb-6">
                    <span class="text-4xl font-black italic <?php echo $is_called ? 'text-sky-400' : 'text-slate-700'; ?>">#<?php echo $w['waiting_num']; ?></span>
                    <span class="text-[10px] font-black text-slate-500 uppercase"><?php echo date('H:i', strtotime($w['created_at'])); ?></span>
                </div>

                <div class="mb-8">
                    <h3 class="text-2xl font-black text-white mb-1"><?php echo htmlspecialchars($w['customer_name']); ?> 님</h3>
                    <p class="text-sm font-bold text-slate-400 italic">인원: <?php echo $w['party_size']; ?>명 / <?php echo $w['tel']; ?></p>
                </div>

                <form method="POST" class="flex gap-2">
                    <input type="hidden" name="waiting_id" value="<?php echo $w['id']; ?>">
                    <input type="hidden" name="update_status" value="1">
                    
                    <?php if(!$is_called): ?>
                        <button name="new_status" value="called" class="flex-1 bg-sky-500 text-white py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg shadow-sky-900/20 hover:bg-sky-600 transition">Call Customer</button>
                    <?php else: ?>
                        <button name="new_status" value="completed" class="flex-1 bg-emerald-500 text-white py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-emerald-600 transition">Confirm Entry</button>
                    <?php endif; ?>
                    
                    <button name="new_status" value="cancelled" class="px-5 bg-slate-800 text-rose-500 rounded-2xl font-black text-xs hover:bg-rose-500 hover:text-white transition">X</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

</body>
</html>