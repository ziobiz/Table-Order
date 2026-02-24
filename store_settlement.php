<?php
// store_settlement.php - 가맹점 정산 신청 대시보드
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// [테스트용] 가맹점 로그인
if (!isset($_SESSION['store_id'])) { $_SESSION['store_id'] = 1; $_SESSION['store_name'] = '강남 1호점'; }
$store_id = $_SESSION['store_id'];

// 정산 단가 설정 (예시: AD=90원, WE=180원 - 10% 수수료 차감 가정)
$RATES = ['AD' => 90, 'WE' => 180]; 

// 1. 정산 요청 처리 (POST)
if (isset($_POST['request_settlement'])) {
    $asset = $_POST['asset_type'];
    $rate = $RATES[$asset];
    
    try {
        $pdo->beginTransaction();

        // 정산 가능한(아직 정산 안 된) REDEEM 건수 조회 및 잠금
        $stmt = $pdo->prepare("
            SELECT id FROM transactions 
            WHERE receiver_type='STORE' AND receiver_id=? 
              AND asset_type=? AND trx_type='REDEEM' 
              AND settlement_id IS NULL 
            FOR UPDATE
        ");
        $stmt->execute([$store_id, $asset]);
        $trx_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $qty = count($trx_ids);
        
        if ($qty <= 0) {
            throw new Exception("정산 가능한 내역이 없습니다.");
        }

        $total_amt = $qty * $rate;

        // 1. 정산 요청서 생성
        $pdo->prepare("INSERT INTO settlements (store_id, asset_type, quantity, settlement_rate, total_amount) VALUES (?, ?, ?, ?, ?)")
            ->execute([$store_id, $asset, $qty, $rate, $total_amt]);
        $settlement_id = $pdo->lastInsertId();

        // 2. 해당 트랜잭션들에 정산 ID 마킹 (중복 요청 방지)
        $inQuery = implode(',', array_fill(0, count($trx_ids), '?'));
        $pdo->prepare("UPDATE transactions SET settlement_id = ? WHERE id IN ($inQuery)")
            ->execute(array_merge([$settlement_id], $trx_ids));

        $pdo->commit();
        echo "<script>alert('정산 신청이 완료되었습니다. (수량: {$qty}개)'); location.href='store_settlement.php';</script>"; exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('오류: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 2. 정산 가능 수량(미정산 건) 실시간 조회
$unsettled = [];
foreach (['AD', 'WE'] as $type) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM transactions 
        WHERE receiver_type='STORE' AND receiver_id=? 
          AND asset_type=? AND trx_type='REDEEM' 
          AND settlement_id IS NULL
    ");
    $stmt->execute([$store_id, $type]);
    $unsettled[$type] = $stmt->fetchColumn();
}

// 3. 최근 정산 요청 내역
$history = $pdo->prepare("SELECT * FROM settlements WHERE store_id = ? ORDER BY id DESC LIMIT 5");
$history->execute([$store_id]);
$hist_list = $history->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Settlement - Alrira Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-8">
        
        <header class="flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-black italic text-slate-900 uppercase tracking-tighter">Settlement Center</h1>
                <p class="text-slate-400 text-xs font-bold mt-2 uppercase">쿠폰 정산 요청 및 지급 내역</p>
            </div>
            <div class="text-right">
                <span class="bg-violet-600 text-white px-4 py-2 rounded-xl text-xs font-bold">Store: <?php echo $_SESSION['store_name']; ?></span>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <div class="space-y-6">
                <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border-l-8 border-sky-400 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-sky-50 rounded-full -mr-10 -mt-10 group-hover:scale-110 transition-transform"></div>
                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <span class="bg-sky-100 text-sky-600 px-3 py-1 rounded-lg text-[10px] font-black uppercase">AD Coupon</span>
                                <h3 class="text-2xl font-black text-slate-800 mt-2">Unsettled: <?php echo number_format($unsettled['AD']); ?> CP</h3>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Estimated Amount</p>
                                <p class="text-xl font-black text-sky-600"><?php echo number_format($unsettled['AD'] * $RATES['AD']); ?> ₩</p>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 mb-6">정산 단가: <?php echo $RATES['AD']; ?>원 (수수료 제외)</p>
                        
                        <?php if($unsettled['AD'] > 0): ?>
                        <form method="POST">
                            <input type="hidden" name="asset_type" value="AD">
                            <button type="submit" name="request_settlement" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-sm hover:bg-sky-600 transition-colors shadow-lg" onclick="return confirm('<?php echo $unsettled['AD']; ?>건에 대해 정산을 신청하시겠습니까?')">
                                REQUEST SETTLEMENT
                            </button>
                        </form>
                        <?php else: ?>
                            <button disabled class="w-full bg-slate-100 text-slate-400 py-4 rounded-2xl font-black text-sm cursor-not-allowed">No Data to Settle</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border-l-8 border-violet-500 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-violet-50 rounded-full -mr-10 -mt-10 group-hover:scale-110 transition-transform"></div>
                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <span class="bg-violet-100 text-violet-600 px-3 py-1 rounded-lg text-[10px] font-black uppercase">WE Coupon</span>
                                <h3 class="text-2xl font-black text-slate-800 mt-2">Unsettled: <?php echo number_format($unsettled['WE']); ?> CP</h3>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Estimated Amount</p>
                                <p class="text-xl font-black text-violet-600"><?php echo number_format($unsettled['WE'] * $RATES['WE']); ?> ₩</p>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 mb-6">정산 단가: <?php echo $RATES['WE']; ?>원 (수수료 제외)</p>
                        
                        <?php if($unsettled['WE'] > 0): ?>
                        <form method="POST">
                            <input type="hidden" name="asset_type" value="WE">
                            <button type="submit" name="request_settlement" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-sm hover:bg-violet-600 transition-colors shadow-lg" onclick="return confirm('<?php echo $unsettled['WE']; ?>건에 대해 정산을 신청하시겠습니까?')">
                                REQUEST SETTLEMENT
                            </button>
                        </form>
                        <?php else: ?>
                            <button disabled class="w-full bg-slate-100 text-slate-400 py-4 rounded-2xl font-black text-sm cursor-not-allowed">No Data to Settle</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] shadow-lg border border-slate-100 h-full">
                <h3 class="text-sm font-black text-slate-800 uppercase mb-6 border-b pb-4">Settlement History</h3>
                <div class="space-y-4">
                    <?php if(empty($hist_list)): ?>
                        <div class="text-center text-xs text-slate-400 py-20">No settlement history found.</div>
                    <?php else: foreach($hist_list as $h): 
                        $statusColor = 'bg-slate-100 text-slate-500';
                        if($h['status']=='COMPLETED') $statusColor = 'bg-emerald-100 text-emerald-600';
                        if($h['status']=='REJECTED') $statusColor = 'bg-rose-100 text-rose-600';
                        if($h['status']=='REQUESTED') $statusColor = 'bg-amber-100 text-amber-600';
                    ?>
                    <div class="flex justify-between items-center p-4 rounded-2xl border border-slate-50 hover:bg-slate-50 transition-colors">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] font-bold text-slate-400"><?php echo date('Y.m.d', strtotime($h['created_at'])); ?></span>
                                <span class="<?php echo $statusColor; ?> px-2 py-0.5 rounded text-[8px] font-black uppercase"><?php echo $h['status']; ?></span>
                            </div>
                            <div class="font-bold text-sm text-slate-700">
                                <?php echo $h['asset_type']; ?> Coupon x <?php echo number_format($h['quantity']); ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-black text-slate-900"><?php echo number_format($h['total_amount']); ?> ₩</div>
                            <?php if($h['processed_at']): ?>
                            <div class="text-[8px] text-slate-400">Paid: <?php echo date('m.d', strtotime($h['processed_at'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>