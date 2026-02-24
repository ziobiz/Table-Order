<?php
// user_exchange.php - 고객용 포인트/쿠폰 교환소 (ME/AD -> WE)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// [테스트용] 로그인
if (!isset($_SESSION['user_id'])) { $_SESSION['user_id'] = 1; $_SESSION['nickname'] = '홍길동'; }
$user_id = $_SESSION['user_id'];

// 1. 교환 가능한 내 자산(ME, AD) 조회 (보유량이 0보다 큰 것만)
// 동시에 해당 스토어의 교환 정책(비율, 수수료)도 가져와야 함
$stmt = $pdo->prepare("
    SELECT w.store_id, w.asset_type, w.balance, s.store_name, 
           s.we_exchange_ratio, s.we_exchange_fee
    FROM user_wallets w
    JOIN stores s ON w.store_id = s.id
    WHERE w.user_id = ? 
      AND w.asset_type IN ('ME', 'AD') 
      AND w.balance > 0
      AND s.use_we_coupon = 1 -- WE 쿠폰 정책을 사용하는 매장만
    ORDER BY w.balance DESC
");
$stmt->execute([$user_id]);
$my_coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. 교환 실행 로직
if (isset($_POST['execute_exchange'])) {
    $store_id = $_POST['store_id'];
    $asset_type = $_POST['asset_type']; // ME or AD
    $req_we_qty = (int)$_POST['we_quantity']; // 얻고 싶은 WE 개수
    
    try {
        $pdo->beginTransaction();

        // 정책 및 잔액 재확인
        $chk = $pdo->prepare("SELECT s.we_exchange_ratio, s.we_exchange_fee, w.balance 
                              FROM stores s 
                              JOIN user_wallets w ON w.store_id = s.id 
                              WHERE s.id = ? AND w.user_id = ? AND w.asset_type = ?");
        $chk->execute([$store_id, $user_id, $asset_type]);
        $info = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$info) throw new Exception("유효하지 않은 자산입니다.");

        $ratio = $info['we_exchange_ratio'];
        $fee = $info['we_exchange_fee'];
        
        // 필요 총량 계산 ( (비율 + 수수료) * WE개수 ) -> *수수료 로직 수정: 건당 수수료인지 개당 수수료인지 정책에 따름.
        // 여기서는 "WE 1개를 만들기 위해 AD N개 + 수수료 M개가 필요하다"는 식으로, 개당 비용으로 계산합니다.
        $cost_per_unit = $ratio + $fee; 
        $total_deduct = $cost_per_unit * $req_we_qty;

        if ($info['balance'] < $total_deduct) {
            throw new Exception("잔액이 부족합니다. (필요: $total_deduct CP)");
        }

        // 1. 자산 차감 (ME/AD)
        $pdo->prepare("UPDATE user_wallets SET balance = balance - ? WHERE user_id = ? AND store_id = ? AND asset_type = ?")
            ->execute([$total_deduct, $user_id, $store_id, $asset_type]);

        // 2. 자산 지급 (WE) -> WE는 통합이므로 store_id = 0 (혹은 정책에 따라 다름, 여기선 0)
        $pdo->prepare("INSERT INTO user_wallets (user_id, store_id, asset_type, balance) VALUES (?, 0, 'WE', ?) ON DUPLICATE KEY UPDATE balance = balance + ?")
            ->execute([$user_id, $req_we_qty, $req_we_qty]);

        // 3. 거래 기록 (EXCHANGE)
        $trx_code = 'EXC_' . date('YmdHis') . '_' . rand(100,999);
        $description = "Exchanged $req_we_qty WE from $asset_type (Rate:$ratio, Fee:$fee)";
        $pdo->prepare("INSERT INTO transactions (trx_code, sender_type, sender_id, receiver_type, receiver_id, asset_type, amount, trx_type, description) VALUES (?, 'USER', ?, 'USER', ?, 'WE', ?, 'EXCHANGE', ?)")
            ->execute([$trx_code, $user_id, $user_id, $req_we_qty, $description]);

        $pdo->commit();
        echo "<script>alert('교환이 완료되었습니다! WE 쿠폰 +$req_we_qty'); location.href='user_exchange.php';</script>"; exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('교환 실패: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Exchange Center - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;600;800;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; -webkit-tap-highlight-color: transparent; }
        .hide-scroll::-webkit-scrollbar { display: none; }
        .radio-card:checked + div { border-color: #8b5cf6; background-color: #f5f3ff; }
        .radio-card:checked + div .check-circle { background-color: #8b5cf6; border-color: #8b5cf6; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen pb-24">

    <div class="bg-white p-6 sticky top-0 z-50 border-b border-slate-100 shadow-sm">
        <h1 class="text-xl font-black italic text-violet-900 uppercase tracking-tighter">Exchange Center</h1>
        <p class="text-[10px] text-slate-400 font-bold">ME/AD 쿠폰을 통합 WE 쿠폰으로 전환하세요.</p>
    </div>

    <div class="p-6 space-y-8">

        <section>
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">1. Select Source Coupon</h3>
            
            <?php if(empty($my_coupons)): ?>
                <div class="bg-slate-100 p-8 rounded-3xl text-center text-slate-400 border border-dashed border-slate-300">
                    <span class="text-2xl">📭</span>
                    <p class="text-xs mt-2 font-bold">교환 가능한 쿠폰이 없습니다.</p>
                </div>
            <?php else: ?>
                <form id="exchange_form" method="POST" class="space-y-6">
                    <input type="hidden" name="execute_exchange" value="1">
                    
                    <div class="space-y-3 max-h-60 overflow-y-auto hide-scroll pr-1">
                        <?php foreach($my_coupons as $idx => $c): 
                            // 최대 교환 가능 WE 개수 계산 ( floor(보유량 / (비율+수수료)) )
                            $cost = $c['we_exchange_ratio'] + $c['we_exchange_fee'];
                            $max_we = floor($c['balance'] / $cost);
                            $disabled = ($max_we < 1) ? 'disabled opacity-50 grayscale' : '';
                            $badgeColor = ($c['asset_type']=='ME') ? 'bg-rose-100 text-rose-600' : 'bg-sky-100 text-sky-600';
                        ?>
                        <label class="block cursor-pointer <?php echo $disabled; ?>">
                            <input type="radio" name="select_src" 
                                   data-store="<?php echo $c['store_id']; ?>"
                                   data-asset="<?php echo $c['asset_type']; ?>"
                                   data-ratio="<?php echo $c['we_exchange_ratio']; ?>"
                                   data-fee="<?php echo $c['we_exchange_fee']; ?>"
                                   data-max="<?php echo $max_we; ?>"
                                   data-balance="<?php echo $c['balance']; ?>"
                                   class="radio-card hidden"
                                   onchange="updateCalculator(this)"
                                   <?php echo $disabled ? 'disabled' : ''; ?>
                            >
                            <div class="bg-white p-4 rounded-2xl border-2 border-slate-100 transition-all flex justify-between items-center relative overflow-hidden">
                                <div class="flex items-center gap-3 relative z-10">
                                    <div class="w-4 h-4 rounded-full border-2 border-slate-300 check-circle transition-colors"></div>
                                    <div>
                                        <div class="flex items-center gap-1 mb-1">
                                            <span class="<?php echo $badgeColor; ?> px-1.5 py-0.5 rounded text-[8px] font-black uppercase"><?php echo $c['asset_type']; ?></span>
                                            <span class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($c['store_name']); ?></span>
                                        </div>
                                        <p class="text-[9px] text-slate-400">
                                            잔액: <b class="text-slate-800"><?php echo number_format($c['balance']); ?></b> CP
                                            <span class="mx-1 text-slate-300">|</span> 
                                            최대 <b class="text-violet-600"><?php echo $max_we; ?> WE</b> 가능
                                        </p>
                                    </div>
                                </div>
                                <div class="text-[8px] font-bold text-slate-400 text-right">
                                    Ratio <span class="text-slate-600"><?php echo $c['we_exchange_ratio']; ?>:1</span><br>
                                    Fee <span class="text-slate-600"><?php echo $c['we_exchange_fee']; ?></span>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <input type="hidden" name="store_id" id="input_store_id">
                    <input type="hidden" name="asset_type" id="input_asset_type">

                    <div id="calc_section" class="opacity-30 pointer-events-none transition-all">
                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">2. Quantity to Get</h3>
                        
                        <div class="bg-white p-6 rounded-[2rem] shadow-xl border border-slate-100 text-center relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-violet-500 to-fuchsia-500"></div>
                            
                            <div class="flex items-center justify-center gap-4 mb-6">
                                <button type="button" onclick="changeQty(-1)" class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 font-black hover:bg-slate-200 transition-colors">-</button>
                                <div class="w-24">
                                    <input type="number" name="we_quantity" id="we_quantity" value="1" min="1" readonly class="w-full text-center text-3xl font-black text-slate-800 bg-transparent outline-none">
                                    <span class="block text-[10px] font-bold text-violet-500 uppercase mt-1">Target WE</span>
                                </div>
                                <button type="button" onclick="changeQty(1)" class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 font-black hover:bg-slate-200 transition-colors">+</button>
                            </div>

                            <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 space-y-2">
                                <div class="flex justify-between text-[10px] font-bold text-slate-500">
                                    <span>Exchange Cost</span>
                                    <span id="txt_cost">0 CP</span>
                                </div>
                                <div class="flex justify-between text-[10px] font-bold text-slate-500">
                                    <span>Exchange Fee</span>
                                    <span id="txt_fee">0 CP</span>
                                </div>
                                <div class="border-t border-slate-200 my-2"></div>
                                <div class="flex justify-between text-sm font-black text-slate-800">
                                    <span>Total Deduct</span>
                                    <span id="txt_total" class="text-rose-500">0 CP</span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-violet-600 text-white py-5 rounded-[2rem] font-black text-lg shadow-xl shadow-violet-200 hover:bg-violet-700 transform active:scale-95 transition-all mt-6">
                            SWAP NOW ⚡
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

    </div>

    <nav class="fixed bottom-0 w-full bg-white border-t border-slate-100 pb-6 pt-3 px-6 flex justify-between items-center z-50 rounded-t-[2rem] shadow-[0_-5px_20px_rgba(0,0,0,0.02)]">
        <button onclick="location.href='user_wallet.php'" class="flex flex-col items-center gap-1 text-slate-300 hover:text-violet-600 transition-colors">
            <span class="text-lg">🏠</span>
            <span class="text-[8px] font-bold uppercase">Home</span>
        </button>
        <button class="flex flex-col items-center gap-1 text-slate-300 hover:text-violet-600 transition-colors">
            <span class="text-lg">📷</span>
            <span class="text-[8px] font-bold uppercase">Scan</span>
        </button>
        <button onclick="location.reload()" class="bg-slate-900 text-white w-12 h-12 rounded-full flex items-center justify-center -mt-8 shadow-lg shadow-slate-300 border-4 border-slate-50">
            <span class="text-xl">🔄</span>
        </button>
        <button class="flex flex-col items-center gap-1 text-violet-600">
            <span class="text-lg">🔁</span>
            <span class="text-[8px] font-bold uppercase">Exch</span>
        </button>
        <button class="flex flex-col items-center gap-1 text-slate-300 hover:text-violet-600 transition-colors">
            <span class="text-lg">⚙️</span>
            <span class="text-[8px] font-bold uppercase">Set</span>
        </button>
    </nav>

    <script>
    let selectedMax = 0;
    let selectedRatio = 0;
    let selectedFee = 0;

    function updateCalculator(radio) {
        // 선택된 데이터 가져오기
        const storeId = radio.getAttribute('data-store');
        const assetType = radio.getAttribute('data-asset');
        selectedRatio = parseInt(radio.getAttribute('data-ratio'));
        selectedFee = parseInt(radio.getAttribute('data-fee'));
        selectedMax = parseInt(radio.getAttribute('data-max'));

        // 히든 필드 세팅
        document.getElementById('input_store_id').value = storeId;
        document.getElementById('input_asset_type').value = assetType;

        // UI 활성화
        const section = document.getElementById('calc_section');
        section.classList.remove('opacity-30', 'pointer-events-none');
        
        // 수량 초기화 및 계산
        document.getElementById('we_quantity').value = 1;
        recalc();
    }

    function changeQty(delta) {
        const input = document.getElementById('we_quantity');
        let val = parseInt(input.value) || 1;
        val += delta;
        if (val < 1) val = 1;
        if (val > selectedMax) {
            alert("최대 교환 가능 수량을 초과했습니다.");
            val = selectedMax;
        }
        input.value = val;
        recalc();
    }

    function recalc() {
        const qty = parseInt(document.getElementById('we_quantity').value) || 1;
        
        const cost = qty * selectedRatio;
        const fee = qty * selectedFee;
        const total = cost + fee;

        document.getElementById('txt_cost').innerText = cost + " CP";
        document.getElementById('txt_fee').innerText = fee + " CP";
        document.getElementById('txt_total').innerText = total + " CP";
    }
    </script>
</body>
</html>