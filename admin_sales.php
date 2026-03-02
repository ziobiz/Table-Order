<?php
// admin_sales.php - 본사 판매(승인) 관리
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안] 본사 관리자 권한 체크
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('본사 관리자 전용 페이지입니다.'); location.href='login.php';</script>"; exit;
}
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? ('id_' . $admin_id);
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$admin_login_at = (int)($_SESSION['admin_login_at'] ?? time());
$header_locale = 'ko';

// --------------------------------------------------------------------------------
// [주문 승인/거절 로직]
// --------------------------------------------------------------------------------
if (isset($_POST['process_order'])) {
    $order_id = $_POST['order_id'];
    $action = $_POST['action']; // 'APPROVE' or 'REJECT'
    
    try {
        $pdo->beginTransaction();

        // 주문 정보 조회
        $stmt = $pdo->prepare("SELECT * FROM store_orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['status'] != 'PENDING') {
            throw new Exception("이미 처리되었거나 존재하지 않는 주문입니다.");
        }

        if ($action == 'APPROVE') {
            // 1. 상태 업데이트
            $pdo->prepare("UPDATE store_orders SET status = 'APPROVED', processed_at = NOW() WHERE id = ?")->execute([$order_id]);
            
            // 2. 가맹점 재고 증가 (없으면 생성)
            $pdo->prepare("
                INSERT INTO store_inventory (store_id, asset_type, balance) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE balance = balance + ?
            ")->execute([$order['store_id'], $order['asset_type'], $order['quantity'], $order['quantity']]);

            // 3. 통합 거래 원장에 기록 (본사 -> 가맹점 판매)
            $trx_code = 'BUY_' . date('Ymd') . '_' . $order_id;
            $pdo->prepare("
                INSERT INTO transactions (trx_code, sender_type, sender_id, receiver_type, receiver_id, asset_type, amount, trx_type, description) 
                VALUES (?, 'HQ', 0, 'STORE', ?, ?, ?, 'BUY', ?)
            ")->execute([$trx_code, $order['store_id'], $order['asset_type'], $order['quantity'], $order['payment_type'] . ' Purchase']);
            log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_sales', 'approve', 'store_order', $order_id, "판매 승인: 주문 ID {$order_id}, 가맹점 ID {$order['store_id']}");
            $msg = "승인 및 재고 지급이 완료되었습니다.";
        } else {
            // 거절 처리
            $pdo->prepare("UPDATE store_orders SET status = 'REJECTED', processed_at = NOW() WHERE id = ?")->execute([$order_id]);
            log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_sales', 'reject', 'store_order', $order_id, "판매 반려: 주문 ID {$order_id}");
            $msg = "요청이 거절되었습니다.";
        }

        $pdo->commit();
        echo "<script>alert('$msg'); location.href='admin_sales.php';</script>"; exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('오류: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 목록 조회 (대기중인 것 우선)
$orders = $pdo->query("
    SELECT o.*, s.store_name 
    FROM store_orders o 
    JOIN stores s ON o.store_id = s.id 
    ORDER BY FIELD(o.status, 'PENDING', 'APPROVED', 'REJECTED'), o.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = 'Sales 승인';
    $admin_page_subtitle = '가맹점 포인트/쿠폰 구매 승인';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar):
    $admin_page_title = 'Sales 승인';
    $admin_page_subtitle = '가맹점 포인트/쿠폰 구매 승인';
    include 'admin_card_header.php';
endif; ?>

        <div class="max-w-[96rem] space-y-10">
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-black tracking-widest border-b border-slate-100">
                    <tr>
                        <th class="p-6">Date / Order ID</th>
                        <th class="p-6">Store Name</th>
                        <th class="p-6">Product</th>
                        <th class="p-6 text-right">Qty</th>
                        <th class="p-6 text-right">Total Price</th>
                        <th class="p-6 text-center">Payment</th>
                        <th class="p-6 text-center">Status / Action</th>
                    </tr>
                </thead>
                <tbody class="text-xs font-bold text-slate-700 divide-y divide-slate-50">
                    <?php foreach($orders as $o): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-6">
                            <div class="flex flex-col">
                                <span class="text-[10px] text-slate-400"><?php echo date('Y.m.d H:i', strtotime($o['created_at'])); ?></span>
                                <span class="font-mono text-[10px] text-violet-400">#<?php echo $o['id']; ?></span>
                            </div>
                        </td>
                        <td class="p-6 text-sm"><?php echo htmlspecialchars($o['store_name']); ?></td>
                        <td class="p-6">
                            <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-[10px] font-black uppercase"><?php echo $o['asset_type']; ?> Coupon</span>
                        </td>
                        <td class="p-6 text-right text-sm"><?php echo number_format($o['quantity']); ?></td>
                        <td class="p-6 text-right text-sm text-slate-900"><?php echo number_format($o['total_price']); ?> ₩</td>
                        <td class="p-6 text-center">
                            <?php if($o['payment_type'] == 'PRE'): ?>
                                <span class="text-emerald-500 font-black">Prepaid</span>
                            <?php else: ?>
                                <span class="text-amber-500 font-black">Postpaid</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-6 text-center">
                            <?php if($o['status'] == 'PENDING'): ?>
                                <form method="POST" class="flex justify-center gap-2">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <button type="submit" name="process_order" value="APPROVE" class="bg-emerald-500 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-600 shadow-md shadow-emerald-200" onclick="return confirm('승인하시겠습니까? 재고가 즉시 지급됩니다.')">Approve</button>
                                    <input type="hidden" name="action" id="action_<?php echo $o['id']; ?>"> 
                                    <button type="submit" name="process_order" value="REJECT" class="bg-rose-500 text-white px-3 py-1.5 rounded-lg hover:bg-rose-600 shadow-md shadow-rose-200" onclick="document.getElementById('action_<?php echo $o['id']; ?>').value='REJECT'; return confirm('거절하시겠습니까?')">Reject</button>
                                    
                                    <script>
                                        document.querySelectorAll('button[name="process_order"]').forEach(btn => {
                                            btn.onclick = function() {
                                                this.form.querySelector('input[name="action"]').value = this.value;
                                                return confirm(this.value === 'APPROVE' ? '승인하시겠습니까?' : '거절하시겠습니까?');
                                            }
                                        });
                                    </script>
                                </form>
                            <?php elseif($o['status'] == 'APPROVED'): ?>
                                <span class="bg-emerald-100 text-emerald-600 px-3 py-1 rounded-full text-[10px] font-black uppercase">Approved</span>
                                <div class="text-[9px] text-slate-400 mt-1"><?php echo date('m.d H:i', strtotime($o['processed_at'])); ?></div>
                            <?php else: ?>
                                <span class="bg-rose-100 text-rose-600 px-3 py-1 rounded-full text-[10px] font-black uppercase">Rejected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
<?php include 'admin_card_footer.php'; ?>
<?php endif; ?>