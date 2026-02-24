<?php
// store_buy.php - 가맹점 자산 구매(충전) 페이지
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// [테스트용] 가맹점 로그인
if (!isset($_SESSION['store_id'])) { $_SESSION['store_id'] = 1; $_SESSION['store_name'] = '강남 1호점'; }
$store_id = $_SESSION['store_id'];

// 1. 현재 내 재고 조회
$inventory = [];
$stmt = $pdo->prepare("SELECT asset_type, balance FROM store_inventory WHERE store_id = ?");
$stmt->execute([$store_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $inventory[$row['asset_type']] = $row['balance'];
}

// 2. 구매 요청 처리
if (isset($_POST['request_buy'])) {
    $asset = $_POST['asset_type'];
    $qty = (int)$_POST['quantity'];
    $pay_type = $_POST['payment_type'];
    
    // 단가 설정 (예시: AD=100원, WE=200원, Point=1원 등 - 실제로는 DB 설정값 연동 필요)
    $unit_price = 0;
    if ($asset == 'AD') $unit_price = 100;
    elseif ($asset == 'WE') $unit_price = 200;
    elseif (strpos($asset, 'POINT') !== false) $unit_price = 1; 
    
    $total = $qty * $unit_price;

    $stmt = $pdo->prepare("INSERT INTO store_orders (store_id, asset_type, quantity, price_per_unit, total_price, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$store_id, $asset, $qty, $unit_price, $total, $pay_type]);
    
    echo "<script>alert('구매 요청이 전송되었습니다. 본사 승인 후 충전됩니다.'); location.href='store_buy.php';</script>"; exit;
}

// 3. 최근 신청 내역 조회
$orders = $pdo->prepare("SELECT * FROM store_orders WHERE store_id = ? ORDER BY id DESC LIMIT 5");
$orders->execute([$store_id]);
$order_list = $orders->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Purchase Assets - Alrira Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }
        .asset-radio:checked + div { border-color: #4f46e5; background-color: #eef2ff; }
        .asset-radio:checked + div .check-icon { display: block; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-8">
        
        <header class="flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-black italic text-slate-900 uppercase tracking-tighter">Purchase Assets</h1>
                <p class="text-slate-400 text-xs font-bold mt-2 uppercase">포인트 및 쿠폰 충전 요청</p>
            </div>
            <div class="text-right">
                <span class="bg-violet-600 text-white px-4 py-2 rounded-xl text-xs font-bold">Store: <?php echo $_SESSION['store_name']; ?></span>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <div class="lg:col-span-2">
                <form method="POST" class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-200">
                    <h3 class="text-sm font-black text-slate-800 uppercase mb-6">Select Product</h3>
                    
                    <div class="grid grid-cols-2 gap-4 mb-8">
                        <label class="cursor-pointer group">
                            <input type="radio" name="asset_type" value="AD" class="asset-radio hidden" checked>
                            <div class="p-6 rounded-3xl border-2 border-slate-100 hover:border-sky-300 transition-all relative">
                                <div class="check-icon hidden absolute top-4 right-4 text-sky-600 text-xl">✔</div>
                                <span class="text-[10px] font-bold text-sky-500 uppercase bg-sky-50 px-2 py-1 rounded-md">Best Seller</span>
                                <h4 class="text-xl font-black text-slate-800 mt-3">AD Coupon</h4>
                                <p class="text-xs text-slate-400 mt-1">타 가맹점 연동형</p>
                                <div class="mt-4 pt-4 border-t border-dashed border-slate-200 flex justify-between items-center">
                                    <span class="text-[10px] font-bold text-slate-400">보유재고</span>
                                    <span class="text-lg font-black text-slate-800"><?php echo number_format($inventory['AD']??0); ?></span>
                                </div>
                            </div>
                        </label>

                        <label class="cursor-pointer group">
                            <input type="radio" name="asset_type" value="WE" class="asset-radio hidden">
                            <div class="p-6 rounded-3xl border-2 border-slate-100 hover:border-violet-300 transition-all relative">
                                <div class="check-icon hidden absolute top-4 right-4 text-violet-600 text-xl">✔</div>
                                <span class="text-[10px] font-bold text-violet-500 uppercase bg-violet-50 px-2 py-1 rounded-md">Premium</span>
                                <h4 class="text-xl font-black text-slate-800 mt-3">WE Coupon</h4>
                                <p class="text-xs text-slate-400 mt-1">통합 교환형</p>
                                <div class="mt-4 pt-4 border-t border-dashed border-slate-200 flex justify-between items-center">
                                    <span class="text-[10px] font-bold text-slate-400">보유재고</span>
                                    <span class="text-lg font-black text-slate-800"><?php echo number_format($inventory['WE']??0); ?></span>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2 ml-1">Quantity</label>
                            <input type="number" name="quantity" value="100" min="10" step="10" class="w-full p-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-slate-900 focus:bg-white outline-none font-black text-lg transition-all">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2 ml-1">Payment Type</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_type" value="PRE" class="peer hidden" checked>
                                    <div class="p-4 rounded-xl border-2 border-slate-100 text-center font-bold text-slate-400 peer-checked:border-emerald-500 peer-checked:text-emerald-600 peer-checked:bg-emerald-50 transition-all">
                                        선불 (Prepaid)
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_type" value="POST" class="peer hidden">
                                    <div class="p-4 rounded-xl border-2 border-slate-100 text-center font-bold text-slate-400 peer-checked:border-amber-500 peer-checked:text-amber-600 peer-checked:bg-amber-50 transition-all">
                                        후불 (Postpaid)
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="request_buy" class="w-full mt-8 bg-slate-900 text-white py-5 rounded-[2rem] font-black text-lg shadow-xl hover:bg-slate-800 transition-all active:scale-95">
                        REQUEST PURCHASE
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-[2.5rem] shadow-lg border border-slate-100 h-full">
                    <h3 class="text-sm font-black text-slate-800 uppercase mb-6">Recent Orders</h3>
                    <div class="space-y-4">
                        <?php if(empty($order_list)): ?>
                            <div class="text-center text-xs text-slate-400 py-10">No recent orders.</div>
                        <?php else: foreach($order_list as $o): 
                            $statusColor = 'bg-slate-100 text-slate-500';
                            if($o['status']=='APPROVED') $statusColor = 'bg-emerald-100 text-emerald-600';
                            if($o['status']=='REJECTED') $statusColor = 'bg-rose-100 text-rose-600';
                            if($o['status']=='PENDING') $statusColor = 'bg-amber-100 text-amber-600';
                        ?>
                        <div class="p-4 rounded-2xl border border-slate-50 hover:bg-slate-50 transition-colors">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-[10px] font-bold text-slate-400"><?php echo date('m.d H:i', strtotime($o['created_at'])); ?></span>
                                <span class="<?php echo $statusColor; ?> px-2 py-0.5 rounded text-[9px] font-black uppercase"><?php echo $o['status']; ?></span>
                            </div>
                            <div class="flex justify-between items-end">
                                <div>
                                    <span class="text-xs font-bold text-slate-500"><?php echo $o['asset_type']; ?> Coupon</span>
                                    <div class="text-sm font-black text-slate-800"><?php echo number_format($o['quantity']); ?> EA</div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[9px] font-bold text-slate-400"><?php echo $o['payment_type']=='PRE'?'Prepaid':'Postpaid'; ?></span>
                                    <div class="text-xs font-bold text-slate-600"><?php echo number_format($o['total_price']); ?> ₩</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>