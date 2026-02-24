<?php
// store_pos.php - 가맹점 전용 POS (QR 스캔, 적립, 사용)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// [테스트용] 가맹점 로그인 처리 (실제로는 로그인 페이지 필요)
if (!isset($_SESSION['store_id'])) {
    // ID 1번 가맹점으로 강제 로그인 가정
    $_SESSION['store_id'] = 1; 
    $_SESSION['store_name'] = '강남 1호점';
}

$store_id = $_SESSION['store_id'];

// 1. 가맹점 정책 정보 가져오기 (적립율 계산용)
$stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->execute([$store_id]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);

// -------------------------------------------------------------------------
// [AJAX 처리] QR 조회 및 트랜잭션 실행
// -------------------------------------------------------------------------
$result_message = "";
$scanned_user = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. QR 코드 스캔 (사용자 조회)
    if (isset($_POST['qr_code'])) {
        $qr = $_POST['qr_code'];
        // QR 포맷: USER_{ID}_{TIMESTAMP}
        $parts = explode('_', $qr);
        if (count($parts) >= 2 && $parts[0] === 'USER') {
            $u_id = intval($parts[1]);
            
            // 사용자 정보 및 자산 조회
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$u_id]);
            $scanned_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($scanned_user) {
                // 자산 조회 (이 가맹점 전용 자산 + 공용 자산)
                $stmt = $pdo->prepare("
                    SELECT * FROM user_wallets 
                    WHERE user_id = ? AND (store_id = ? OR store_id = 0)
                ");
                $stmt->execute([$u_id, $store_id]);
                $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $scanned_user['assets'] = [];
                foreach($assets as $a) {
                    // ME 쿠폰은 내 가맹점 것만, 나머지는 공용(store_id=0) 또는 내것
                    if ($a['asset_type'] == 'ME' && $a['store_id'] != $store_id) continue;
                    $scanned_user['assets'][$a['asset_type']] = $a['balance'];
                }
            }
        }
    }

    // B. [적립] 또는 [사용] 실행
    if (isset($_POST['action_type']) && isset($_POST['target_user_id'])) {
        try {
            $pdo->beginTransaction();
            
            $target_uid = $_POST['target_user_id'];
            $act = $_POST['action_type']; // 'EARN' or 'USE'
            $pay_amt = intval($_POST['payment_amount'] ?? 0); // 결제 금액
            
            // 트랜잭션 코드 생성
            $trx_code = date('YmdHis') . rand(1000,9999);

            // 1. 포인트 적립 로직 (Single, Multi, Global)
            if ($act === 'EARN' && $pay_amt > 0) {
                // Single Point
                if ($store['use_single']) {
                    $p = floor($pay_amt / $store['single_threshold']) * $store['single_amt'];
                    if ($p > 0) {
                        $pdo->prepare("INSERT INTO user_wallets (user_id, store_id, asset_type, balance) VALUES (?, ?, 'SINGLE', ?) ON DUPLICATE KEY UPDATE balance = balance + ?")->execute([$target_uid, $store_id, $p, $p]);
                        $pdo->prepare("INSERT INTO transactions (trx_code, sender_type, sender_id, receiver_type, receiver_id, asset_type, amount, trx_type) VALUES (?, 'STORE', ?, 'USER', ?, 'SINGLE', ?, 'ISSUE')")->execute([$trx_code, $store_id, $target_uid, $p]);
                    }
                }
                // ME Coupon (금액 기준)
                if ($store['use_me_coupon']) {
                    $cp = floor($pay_amt / $store['me_coupon_threshold']); // 1장 단위
                    if ($cp > 0) {
                        $pdo->prepare("INSERT INTO user_wallets (user_id, store_id, asset_type, balance) VALUES (?, ?, 'ME', ?) ON DUPLICATE KEY UPDATE balance = balance + ?")->execute([$target_uid, $store_id, $cp, $cp]);
                        $pdo->prepare("INSERT INTO transactions (trx_code, sender_type, sender_id, receiver_type, receiver_id, asset_type, amount, trx_type) VALUES (?, 'STORE', ?, 'USER', ?, 'ME', ?, 'ISSUE')")->execute([$trx_code.'_ME', $store_id, $target_uid, $cp]);
                    }
                }
                // AD Coupon (금액 기준)
                if ($store['use_ad_coupon']) {
                    $cp = floor($pay_amt / $store['ad_coupon_threshold']);
                    if ($cp > 0) {
                        $pdo->prepare("INSERT INTO user_wallets (user_id, store_id, asset_type, balance) VALUES (?, 0, 'AD', ?) ON DUPLICATE KEY UPDATE balance = balance + ?")->execute([$target_uid, $cp, $cp]); // AD는 store_id=0 (통합)
                        $pdo->prepare("INSERT INTO transactions (trx_code, sender_type, sender_id, receiver_type, receiver_id, asset_type, amount, trx_type) VALUES (?, 'STORE', ?, 'USER', ?, 'AD', ?, 'ISSUE')")->execute([$trx_code.'_AD', $store_id, $target_uid, $cp]);
                    }
                }
                // WE Coupon (금액 기준)
                if ($store['use_we_coupon']) {
                    $cp = floor($pay_amt / $store['we_coupon_threshold']);
                    if ($cp > 0) {
                        $pdo->prepare("INSERT INTO user_wallets (user_id, store_id, asset_type, balance) VALUES (?, 0, 'WE', ?) ON DUPLICATE KEY UPDATE balance = balance + ?")->execute([$target_uid, $cp, $cp]); // WE는 store_id=0 (통합)
                        $pdo->prepare("INSERT INTO transactions (trx_code, sender_type, sender_id, receiver_type, receiver_id, asset_type, amount, trx_type) VALUES (?, 'STORE', ?, 'USER', ?, 'WE', ?, 'ISSUE')")->execute([$trx_code.'_WE', $store_id, $target_uid, $cp]);
                    }
                }
                $result_message = "적립이 완료되었습니다.";
            }

            // 2. 쿠폰/포인트 사용 로직
            if ($act === 'USE') {
                $use_asset = $_POST['use_asset_type']; // ME, AD, WE, SINGLE...
                $use_qty = intval($_POST['use_qty']);
                
                // 잔액 확인 및 차감
                $check_store = ($use_asset == 'ME' || $use_asset == 'SINGLE') ? $store_id : 0;
                
                $stmt = $pdo->prepare("SELECT balance FROM user_wallets WHERE user_id=? AND store_id=? AND asset_type=?");
                $stmt->execute([$target_uid, $check_store, $use_asset]);
                $bal = $stmt->fetchColumn();

                if ($bal >= $use_qty) {
                    $pdo->prepare("UPDATE user_wallets SET balance = balance - ? WHERE user_id=? AND store_id=? AND asset_type=?")->execute([$use_qty, $target_uid, $check_store, $use_asset]);
                    $pdo->prepare("INSERT INTO transactions (trx_code, sender_type, sender_id, receiver_type, receiver_id, asset_type, amount, trx_type) VALUES (?, 'USER', ?, 'STORE', ?, ?, ?, 'REDEEM')")->execute([$trx_code, $target_uid, $store_id, $use_asset, $use_qty]);
                    $result_message = "사용(차감) 처리가 완료되었습니다.";
                } else {
                    throw new Exception("잔액이 부족합니다.");
                }
            }

            $pdo->commit();
            // 처리 후 유저 정보 다시 로드
            $scanned_user = null; // 초기화하여 다시 스캔 유도 or 결과창 표시
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $result_message = "오류: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Store POS - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid #e2e8f0; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col">

    <header class="bg-slate-900 text-white p-4 shadow-lg z-50">
        <div class="max-w-[96rem] mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-violet-600 rounded-xl flex items-center justify-center font-black text-lg">P</div>
                <div>
                    <h1 class="font-black text-lg italic tracking-tighter">PARTNER POS</h1>
                    <p class="text-[10px] text-slate-400 font-bold"><?php echo htmlspecialchars($store['store_name']); ?> (<?php echo $store['currency']; ?>)</p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-xs bg-slate-800 px-3 py-1 rounded-full text-slate-300">Store ID: #<?php echo $store_id; ?></span>
            </div>
        </div>
    </header>

    <div class="flex-1 max-w-[96rem] w-full mx-auto p-4 grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <div class="lg:col-span-3 space-y-4">
            
            <div class="bg-white p-6 rounded-[2rem] shadow-xl border border-slate-200 text-center">
                <h3 class="text-sm font-black text-slate-800 uppercase mb-4">1. Scan Customer QR</h3>
                
                <div class="aspect-square bg-slate-900 rounded-2xl flex items-center justify-center mb-4 relative overflow-hidden group cursor-pointer">
                    <div class="absolute inset-0 border-4 border-violet-500/50 rounded-2xl m-4 animate-pulse"></div>
                    <span class="text-4xl">📷</span>
                    <p class="absolute bottom-4 text-[10px] text-slate-400">Touch to Activate Camera</p>
                </div>

                <form method="POST" class="flex gap-2">
                    <input type="text" name="qr_code" placeholder="QR코드 입력 (예: USER_1_...)" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold focus:ring-2 focus:ring-violet-500 outline-none">
                    <button type="submit" class="bg-slate-900 text-white px-4 rounded-xl font-bold text-xs">SCAN</button>
                </form>
            </div>

            <?php if ($scanned_user): ?>
            <div class="bg-white p-6 rounded-[2rem] shadow-xl border-2 border-violet-500 relative overflow-hidden">
                <div class="absolute top-0 right-0 bg-violet-500 text-white text-[9px] font-bold px-3 py-1 rounded-bl-xl">Verfied</div>
                
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center text-2xl">👤</div>
                    <div>
                        <h2 class="text-xl font-black text-slate-800"><?php echo htmlspecialchars($scanned_user['nickname']); ?></h2>
                        <p class="text-xs text-slate-400 font-bold"><?php echo $scanned_user['phone'] ?: 'No Phone'; ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-center">
                    <div class="bg-rose-50 p-2 rounded-xl border border-rose-100">
                        <span class="block text-[8px] font-bold text-rose-400 uppercase">ME Coupon</span>
                        <span class="block text-lg font-black text-rose-600"><?php echo number_format($scanned_user['assets']['ME'] ?? 0); ?></span>
                    </div>
                    <div class="bg-sky-50 p-2 rounded-xl border border-sky-100">
                        <span class="block text-[8px] font-bold text-sky-400 uppercase">AD Coupon</span>
                        <span class="block text-lg font-black text-sky-600"><?php echo number_format($scanned_user['assets']['AD'] ?? 0); ?></span>
                    </div>
                    <div class="bg-violet-50 p-2 rounded-xl border border-violet-100">
                        <span class="block text-[8px] font-bold text-violet-400 uppercase">WE Coupon</span>
                        <span class="block text-lg font-black text-violet-600"><?php echo number_format($scanned_user['assets']['WE'] ?? 0); ?></span>
                    </div>
                    <div class="bg-slate-50 p-2 rounded-xl border border-slate-200">
                        <span class="block text-[8px] font-bold text-slate-400 uppercase">Points</span>
                        <span class="block text-lg font-black text-slate-600"><?php echo number_format(($scanned_user['assets']['SINGLE'] ?? 0)); ?></span>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="bg-slate-50 p-6 rounded-[2rem] border border-slate-200 border-dashed text-center h-48 flex flex-col items-center justify-center text-slate-400">
                    <span class="text-2xl mb-2">👋</span>
                    <span class="text-xs font-bold">Waiting for Customer...</span>
                </div>
            <?php endif; ?>

        </div>

        <div class="lg:col-span-9">
            <?php if ($result_message): ?>
                <div class="bg-emerald-50 text-emerald-600 p-4 rounded-2xl mb-4 font-bold text-center border border-emerald-100 shadow-sm animate-pulse">
                    ✅ <?php echo $result_message; ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-[3rem] shadow-xl border border-slate-200 overflow-hidden h-full flex flex-col <?php echo !$scanned_user ? 'opacity-50 pointer-events-none grayscale' : ''; ?>">
                
                <div class="flex border-b border-slate-100">
                    <button onclick="switchTab('earn')" id="btn-earn" class="flex-1 py-5 text-center font-black text-sm uppercase bg-slate-900 text-white transition-all">
                        💰 적립 (Earning)
                    </button>
                    <button onclick="switchTab('use')" id="btn-use" class="flex-1 py-5 text-center font-black text-sm uppercase bg-slate-50 text-slate-400 hover:bg-slate-100 transition-all">
                        🎫 사용 (Redeem)
                    </button>
                </div>

                <div class="p-8 flex-1 flex flex-col justify-center">
                    
                    <div id="tab-earn" class="space-y-8">
                        <div class="text-center">
                            <h2 class="text-2xl font-black text-slate-800 mb-2">Payment Amount</h2>
                            <p class="text-xs text-slate-400">결제 금액을 입력하면 정책에 따라 자동 적립됩니다.</p>
                        </div>

                        <form method="POST" class="max-w-md mx-auto w-full space-y-6">
                            <input type="hidden" name="action_type" value="EARN">
                            <input type="hidden" name="target_user_id" value="<?php echo $scanned_user['id'] ?? ''; ?>">
                            
                            <div class="relative">
                                <span class="absolute left-6 top-1/2 -translate-y-1/2 text-lg font-black text-slate-400"><?php echo $store['currency']; ?></span>
                                <input type="number" name="payment_amount" id="payment_amount" placeholder="0" class="w-full bg-slate-50 border-2 border-slate-100 rounded-[2rem] py-6 pl-20 pr-6 text-3xl font-black text-slate-800 text-right outline-none focus:border-slate-900 focus:bg-white transition-all" oninput="calcPreview()">
                            </div>

                            <div class="bg-slate-50 p-5 rounded-2xl space-y-2 border border-slate-100">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Estimated Rewards</p>
                                <div id="preview_list" class="space-y-1 text-sm font-bold text-slate-600">
                                    <div class="flex justify-between"><span>ME Coupon</span> <span id="calc_me" class="text-rose-500">0 CP</span></div>
                                    <div class="flex justify-between"><span>AD Coupon</span> <span id="calc_ad" class="text-sky-500">0 CP</span></div>
                                    <div class="flex justify-between"><span>WE Coupon</span> <span id="calc_we" class="text-violet-500">0 CP</span></div>
                                    <div class="flex justify-between border-t pt-1 mt-1"><span>Single Point</span> <span id="calc_single" class="text-slate-800">0 P</span></div>
                                </div>
                            </div>

                            <button type="submit" class="w-full bg-slate-900 text-white py-5 rounded-[2rem] font-black text-lg shadow-xl hover:bg-slate-800 transform active:scale-95 transition-all">
                                CONFIRM & EARN
                            </button>
                        </form>
                    </div>

                    <div id="tab-use" class="hidden space-y-8">
                        <div class="text-center">
                            <h2 class="text-2xl font-black text-slate-800 mb-2">Redeem Asset</h2>
                            <p class="text-xs text-slate-400">사용할 쿠폰이나 포인트를 선택하세요.</p>
                        </div>

                        <form method="POST" class="max-w-md mx-auto w-full space-y-6">
                            <input type="hidden" name="action_type" value="USE">
                            <input type="hidden" name="target_user_id" value="<?php echo $scanned_user['id'] ?? ''; ?>">

                            <div class="grid grid-cols-3 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="use_asset_type" value="ME" class="peer hidden" checked>
                                    <div class="p-4 rounded-2xl border-2 border-slate-100 peer-checked:border-rose-500 peer-checked:bg-rose-50 text-center transition-all">
                                        <span class="block text-xs font-bold text-slate-400 peer-checked:text-rose-500">ME</span>
                                        <span class="block font-black text-slate-800 text-lg">CP</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="use_asset_type" value="AD" class="peer hidden">
                                    <div class="p-4 rounded-2xl border-2 border-slate-100 peer-checked:border-sky-500 peer-checked:bg-sky-50 text-center transition-all">
                                        <span class="block text-xs font-bold text-slate-400 peer-checked:text-sky-500">AD</span>
                                        <span class="block font-black text-slate-800 text-lg">CP</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="use_asset_type" value="WE" class="peer hidden">
                                    <div class="p-4 rounded-2xl border-2 border-slate-100 peer-checked:border-violet-500 peer-checked:bg-violet-50 text-center transition-all">
                                        <span class="block text-xs font-bold text-slate-400 peer-checked:text-violet-500">WE</span>
                                        <span class="block font-black text-slate-800 text-lg">CP</span>
                                    </div>
                                </label>
                            </div>

                            <div class="relative">
                                <span class="absolute left-6 top-1/2 -translate-y-1/2 text-sm font-black text-slate-400">Qty</span>
                                <input type="number" name="use_qty" placeholder="0" class="w-full bg-slate-50 border-2 border-slate-100 rounded-[2rem] py-5 pl-16 pr-6 text-2xl font-black text-slate-800 text-right outline-none focus:border-rose-500 focus:bg-white transition-all">
                            </div>

                            <button type="submit" class="w-full bg-rose-500 text-white py-5 rounded-[2rem] font-black text-lg shadow-xl shadow-rose-200 hover:bg-rose-600 transform active:scale-95 transition-all">
                                REDEEM NOW
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
    // 정책 데이터 (PHP -> JS)
    const policy = {
        me_th: <?php echo $store['me_coupon_threshold'] ?: 999999; ?>,
        ad_th: <?php echo $store['ad_coupon_threshold'] ?: 999999; ?>,
        we_th: <?php echo $store['we_coupon_threshold'] ?: 999999; ?>,
        single_th: <?php echo $store['single_threshold'] ?: 999999; ?>,
        single_amt: <?php echo $store['single_amt'] ?: 0; ?>,
        
        use_me: <?php echo $store['use_me_coupon']; ?>,
        use_ad: <?php echo $store['use_ad_coupon']; ?>,
        use_we: <?php echo $store['use_we_coupon']; ?>,
        use_single: <?php echo $store['use_single']; ?>
    };

    function switchTab(mode) {
        const earnView = document.getElementById('tab-earn');
        const useView = document.getElementById('tab-use');
        const btnEarn = document.getElementById('btn-earn');
        const btnUse = document.getElementById('btn-use');

        if(mode === 'earn') {
            earnView.classList.remove('hidden');
            useView.classList.add('hidden');
            btnEarn.className = "flex-1 py-5 text-center font-black text-sm uppercase bg-slate-900 text-white transition-all";
            btnUse.className = "flex-1 py-5 text-center font-black text-sm uppercase bg-slate-50 text-slate-400 hover:bg-slate-100 transition-all";
        } else {
            earnView.classList.add('hidden');
            useView.classList.remove('hidden');
            btnEarn.className = "flex-1 py-5 text-center font-black text-sm uppercase bg-slate-50 text-slate-400 hover:bg-slate-100 transition-all";
            btnUse.className = "flex-1 py-5 text-center font-black text-sm uppercase bg-rose-500 text-white transition-all";
        }
    }

    function calcPreview() {
        const amt = parseInt(document.getElementById('payment_amount').value) || 0;
        
        document.getElementById('calc_me').innerText = (policy.use_me && amt > 0) ? Math.floor(amt / policy.me_th) + " CP" : "-";
        document.getElementById('calc_ad').innerText = (policy.use_ad && amt > 0) ? Math.floor(amt / policy.ad_th) + " CP" : "-";
        document.getElementById('calc_we').innerText = (policy.use_we && amt > 0) ? Math.floor(amt / policy.we_th) + " CP" : "-";
        
        const singleP = (policy.use_single && amt > 0) ? Math.floor(amt / policy.single_th) * policy.single_amt : 0;
        document.getElementById('calc_single').innerText = singleP > 0 ? singleP + " P" : "-";
    }
    </script>
</body>
</html>