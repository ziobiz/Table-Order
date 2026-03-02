<?php
// admin_settlement.php - 본사 정산 승인 관리
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
// [정산 승인/거절 로직]
// --------------------------------------------------------------------------------
if (isset($_POST['process_settlement'])) {
    $settlement_id = $_POST['settlement_id'];
    $action = $_POST['action']; // 'APPROVE' or 'REJECT'
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM settlements WHERE id = ? FOR UPDATE");
        $stmt->execute([$settlement_id]);
        $stlm = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stlm || $stlm['status'] != 'REQUESTED') {
            throw new Exception("이미 처리된 내역입니다.");
        }

        if ($action == 'APPROVE') {
            // 1. 상태 완료 처리
            $pdo->prepare("UPDATE settlements SET status = 'COMPLETED', processed_at = NOW() WHERE id = ?")->execute([$settlement_id]);
            
            // 2. (옵션) 통합 거래 원장에 '정산 지급(FEE)' 기록 남기기 -> 본사 지출 기록
            $trx_code = 'STL_' . date('Ymd') . '_' . $settlement_id;
            $pdo->prepare("
                INSERT INTO transactions (trx_code, sender_type, sender_id, receiver_type, receiver_id, asset_type, amount, trx_type, description) 
                VALUES (?, 'HQ', 0, 'STORE', ?, 'GLOBAL', ?, 'FEE', ?)
            ")->execute([$trx_code, $stlm['store_id'], $stlm['total_amount'], "Settlement Paid ({$stlm['asset_type']} x {$stlm['quantity']})"]);

            log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_settlement', 'approve', 'settlement', $settlement_id, "정산 승인: 정산 ID {$settlement_id}, 가맹점 ID {$stlm['store_id']}");
            $msg = "정산 승인 완료. (지급 처리됨)";
        } else {
            // 거절 시 -> 해당 트랜잭션들의 settlement_id 마킹을 풀어줘야 함 (다시 신청 가능하게)
            // 1. 상태 거절
            $pdo->prepare("UPDATE settlements SET status = 'REJECTED', processed_at = NOW() WHERE id = ?")->execute([$settlement_id]);
            // 2. 트랜잭션 락 해제
            $pdo->prepare("UPDATE transactions SET settlement_id = NULL WHERE settlement_id = ?")->execute([$settlement_id]);
            log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_settlement', 'reject', 'settlement', $settlement_id, "정산 반려: 정산 ID {$settlement_id}");
            $msg = "정산 요청이 거절되었습니다.";
        }

        $pdo->commit();
        echo "<script>alert('$msg'); location.href='admin_settlement.php';</script>"; exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('오류: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 목록 조회
$list = $pdo->query("
    SELECT st.*, s.store_name, s.bank_name, s.bank_account 
    FROM settlements st 
    JOIN stores s ON st.store_id = s.id 
    ORDER BY FIELD(st.status, 'REQUESTED', 'COMPLETED', 'REJECTED'), st.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = 'Settlement';
    $admin_page_subtitle = '가맹점 정산 요청 지급 처리';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar):
    $admin_page_title = 'Settlement';
    $admin_page_subtitle = '가맹점 정산 요청 지급 처리';
    include 'admin_card_header.php';
endif; ?>

        <div class="max-w-[96rem] space-y-10">
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-black tracking-widest border-b border-slate-100">
                    <tr>
                        <th class="p-6">Date / ID</th>
                        <th class="p-6">Store Info</th>
                        <th class="p-6">Asset Type</th>
                        <th class="p-6 text-right">Qty</th>
                        <th class="p-6 text-right">Total Payout</th>
                        <th class="p-6 text-center">Status / Action</th>
                    </tr>
                </thead>
                <tbody class="text-xs font-bold text-slate-700 divide-y divide-slate-50">
                    <?php foreach($list as $item): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-6">
                            <div class="flex flex-col">
                                <span class="text-[10px] text-slate-400"><?php echo date('Y.m.d H:i', strtotime($item['created_at'])); ?></span>
                                <span class="font-mono text-[10px] text-violet-400">#STL_<?php echo $item['id']; ?></span>
                            </div>
                        </td>
                        <td class="p-6">
                            <div class="text-sm font-black text-slate-800"><?php echo htmlspecialchars($item['store_name']); ?></div>
                            </td>
                        <td class="p-6">
                            <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-[10px] font-black uppercase"><?php echo $item['asset_type']; ?> Coupon</span>
                        </td>
                        <td class="p-6 text-right text-sm"><?php echo number_format($item['quantity']); ?></td>
                        <td class="p-6 text-right">
                            <div class="text-sm font-black text-slate-900"><?php echo number_format($item['total_amount']); ?> ₩</div>
                            <div class="text-[9px] text-slate-400">@ <?php echo number_format($item['settlement_rate']); ?></div>
                        </td>
                        <td class="p-6 text-center">
                            <?php if($item['status'] == 'REQUESTED'): ?>
                                <form method="POST" class="flex justify-center gap-2">
                                    <input type="hidden" name="settlement_id" value="<?php echo $item['id']; ?>">
                                    
                                    <button type="submit" name="process_settlement" value="APPROVE" class="bg-emerald-500 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-600 shadow-md shadow-emerald-200" onclick="this.form.querySelector('input[name=action]').value='APPROVE'; return confirm('지급 승인 하시겠습니까?');">Approve</button>
                                    
                                    <button type="submit" name="process_settlement" value="REJECT" class="bg-rose-500 text-white px-3 py-1.5 rounded-lg hover:bg-rose-600 shadow-md shadow-rose-200" onclick="this.form.querySelector('input[name=action]').value='REJECT'; return confirm('거절하시겠습니까?');">Reject</button>
                                    
                                    <input type="hidden" name="action" value="">
                                </form>
                            <?php elseif($item['status'] == 'COMPLETED'): ?>
                                <span class="bg-emerald-100 text-emerald-600 px-3 py-1 rounded-full text-[10px] font-black uppercase">Paid Complete</span>
                                <div class="text-[9px] text-slate-400 mt-1"><?php echo date('m.d H:i', strtotime($item['processed_at'])); ?></div>
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