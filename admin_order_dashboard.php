<?php
// admin_order_dashboard.php - 실시간 주문 관리 및 영수증 자동 인쇄 통합
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안 구문] 로그인 여부 및 권한 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}
if (($_SESSION['admin_role'] ?? '') === 'DELIVERY') {
    header("Location: delivery_list.php"); exit;
}

$my_store_id = $_SESSION['store_id'];
$my_role = $_SESSION['admin_role'];

// 1. 주문 상태 변경 처리
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    $admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['store_id'] ?? 0);
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? $_SESSION['name'] ?? ('id_' . $admin_id);
    log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_order_dashboard', 'update', 'order', (string)$order_id, "주문 상태 변경: ID {$order_id} → {$new_status}");
    header("Location: admin_order_dashboard.php"); exit;
}

// 2. 주문 리스트 로드 (미완료 주문 대상)
$where_clause = ($my_role === 'SUPERADMIN') ? "status != 'completed' AND status != 'cancelled'" : "store_id = $my_store_id AND status != 'completed' AND status != 'cancelled'";
$sql = "SELECT * FROM orders WHERE $where_clause ORDER BY created_at DESC";
$orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Kitchen Station - Alrira Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="30">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; }
        .order-card { animation: fadeInUp 0.4s ease-out; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <script>
        // 영수증 인쇄 팝업 함수
        function printReceipt(orderId) {
            const width = 400;
            const height = 600;
            const left = (window.screen.width / 2) - (width / 2);
            const top = (window.screen.height / 2) - (height / 2);
            
            // 새 창으로 영수증 레이아웃 호출
            window.open('print_order.php?order_id=' + orderId, 'print_window', 
                `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,location=no,status=no`);
        }
    </script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6">
    
    <header class="max-w-[96rem] mx-auto flex justify-between items-center mb-10 border-b border-slate-800 pb-6">
        <div>
            <h1 class="text-3xl font-black italic text-sky-400 tracking-tighter uppercase">Kitchen Monitor</h1>
            <p class="text-slate-500 text-[10px] font-black uppercase tracking-[0.3em] mt-1">
                <?php echo ($my_role === 'SUPERADMIN') ? "HQ Global Monitor" : "Store Access: #".$my_store_id; ?>
            </p>
        </div>
        <div class="flex items-center space-x-6">
            <div class="bg-slate-900 px-5 py-2 rounded-2xl border border-slate-800">
                <span class="block text-[9px] text-slate-500 font-black uppercase">Staff On Duty</span>
                <span class="text-sm font-bold text-sky-400"><?php echo $_SESSION['name']; ?></span>
            </div>
            <a href="logout.php" class="bg-rose-500/10 text-rose-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase border border-rose-500/20 hover:bg-rose-500 hover:text-white transition">Logout</a>
        </div>
    </header>

    <main class="max-w-[96rem] mx-auto">
        <?php if(empty($orders)): ?>
        <div class="flex flex-col items-center justify-center h-[50vh] opacity-20">
            <p class="text-xl font-black uppercase tracking-[0.3em]">Waiting for orders...</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach($orders as $order): 
                $type_color = ($order['order_type'] == 'delivery') ? 'bg-rose-600' : (($order['order_type'] == 'pickup') ? 'bg-emerald-600' : 'bg-sky-600');
            ?>
            <div class="order-card bg-slate-900 rounded-[2.5rem] border border-slate-800 overflow-hidden shadow-2xl transition-all hover:border-slate-700">
                <div class="p-6 <?php echo $type_color; ?> text-white flex justify-between items-center">
                    <div>
                        <span class="text-[9px] font-black opacity-80 uppercase tracking-widest">No. <?php echo $order['id']; ?></span>
                        <h3 class="text-2xl font-black italic uppercase tracking-tighter"><?php echo $order['order_type']; ?></h3>
                    </div>
                    <button onclick="printReceipt(<?php echo $order['id']; ?>)" class="bg-black/20 p-2 rounded-xl hover:bg-black/40 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    </button>
                </div>

                <div class="p-8">
                    <div class="min-h-[120px] mb-6 space-y-4">
                        <?php 
                        $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                        $items->execute([$order['id']]);
                        foreach($items->fetchAll() as $it): 
                            $m_name = $pdo->query("SELECT menu_name FROM menu_translations WHERE menu_id = {$it['menu_id']} AND lang_code = 'ko'")->fetchColumn();
                        ?>
                        <div class="border-b border-slate-800/50 pb-3">
                            <div class="flex justify-between items-start">
                                <span class="font-black text-slate-100 text-lg leading-tight"><?php echo $m_name; ?></span>
                                <span class="bg-slate-800 px-2 py-1 rounded-lg text-xs font-black text-sky-400">x<?php echo $it['quantity']; ?></span>
                            </div>
                            <?php if($it['options_text']): ?>
                            <p class="text-[11px] text-slate-500 font-bold mt-1 italic">+ <?php echo $it['options_text']; ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if($order['order_type'] !== 'dinein'): ?>
                    <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 mb-6">
                        <p class="text-[9px] text-slate-600 font-black uppercase mb-1">Customer Info</p>
                        <p class="text-xs font-bold text-slate-300"><?php echo $order['tel']; ?></p>
                        <p class="text-[10px] text-slate-500 mt-1 line-clamp-1"><?php echo $order['address']; ?></p>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="flex space-x-2">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="update_status" value="1">
                        
                        <?php if($order['status'] == 'pending'): ?>
                            <button name="new_status" value="cooking" 
                                    onclick="printReceipt(<?php echo $order['id']; ?>)"
                                    class="flex-1 bg-sky-500 p-4 rounded-2xl text-[11px] font-black uppercase hover:bg-sky-600 transition shadow-lg shadow-sky-900/20">
                                Accept & Print
                            </button>
                            <button name="new_status" value="cancelled" class="px-4 bg-slate-800 rounded-2xl text-xs font-black text-rose-500 hover:bg-rose-500 hover:text-white transition">X</button>
                        <?php else: ?>
                            <button name="new_status" value="completed" class="flex-1 bg-emerald-500 p-4 rounded-2xl text-[11px] font-black uppercase hover:bg-emerald-600 transition">Complete</button>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="px-8 py-4 bg-slate-950/50 border-t border-slate-800 text-center">
                    <span class="text-[10px] text-slate-600 font-black uppercase">Received: <?php echo date('H:i', strtotime($order['created_at'])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

</body>
</html>