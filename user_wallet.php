<?php
// user_wallet.php - 고객용 메인 월렛 (6대 자산 조회 및 QR 생성)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// [테스트용] 로그인 처리 (실제로는 로그인 페이지 거쳐야 함)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // 더미 유저 ID 1번 강제 로그인
    $_SESSION['nickname'] = '홍길동';
}

$user_id = $_SESSION['user_id'];

// 1. 사용자 정보 가져오기
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. 보유 자산 가져오기
$assets = [];
$stmt = $pdo->prepare("
    SELECT w.*, s.store_name 
    FROM user_wallets w 
    LEFT JOIN stores s ON w.store_id = s.id 
    WHERE w.user_id = ?
");
$stmt->execute([$user_id]);
$raw_assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 자산 분류 (포인트 / 쿠폰)
$points = ['SINGLE' => [], 'MULTI' => [], 'GLOBAL' => []];
$coupons = ['ME' => [], 'AD' => [], 'WE' => []];

foreach ($raw_assets as $row) {
    if (in_array($row['asset_type'], ['SINGLE', 'MULTI', 'GLOBAL'])) {
        $points[$row['asset_type']][] = $row;
    } else {
        $coupons[$row['asset_type']][] = $row;
    }
}

// 3. 통합 자산 계산 (Global, AD, WE는 store_id=0인 경우가 많음)
$global_point = 0;
foreach($points['GLOBAL'] as $p) $global_point += $p['balance'];

$ad_coupon_cnt = 0;
foreach($coupons['AD'] as $c) $ad_coupon_cnt += $c['balance'];

$we_coupon_cnt = 0;
foreach($coupons['WE'] as $c) $we_coupon_cnt += $c['balance'];

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Wallet - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@300;400;600;800;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; -webkit-tap-highlight-color: transparent; }
        
        /* 카드 그라디언트 효과 */
        .card-glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
        }
        
        /* QR 애니메이션 */
        @keyframes pulse-ring {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
        }
        .qr-active { animation: pulse-ring 2s infinite; }
        
        /* 스크롤바 숨김 */
        .hide-scroll::-webkit-scrollbar { display: none; }
        .hide-scroll { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen pb-24">

    <div class="bg-slate-900 text-white pt-8 pb-16 px-6 rounded-b-[2.5rem] relative shadow-2xl overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-violet-600 rounded-full mix-blend-multiply filter blur-3xl opacity-20 -mr-16 -mt-16"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-sky-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 -ml-16 -mb-16"></div>

        <div class="relative z-10">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">Welcome back,</p>
                    <h1 class="text-2xl font-black italic tracking-tighter"><?php echo htmlspecialchars($user['nickname']); ?>님</h1>
                </div>
                <div class="w-10 h-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-lg">🔔</div>
            </div>

            <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-3xl p-6 text-center shadow-lg relative overflow-hidden group">
                <div id="qr_cover" class="absolute inset-0 bg-slate-900/90 flex flex-col items-center justify-center z-20 transition-all duration-300">
                    <button onclick="generateQR()" class="bg-white text-slate-900 px-8 py-3 rounded-full font-black uppercase text-xs tracking-widest shadow-xl transform group-hover:scale-105 transition-all">
                        Generate QR Code
                    </button>
                    <p class="text-[10px] text-slate-400 mt-3 font-medium">결제 및 적립 시 터치하세요</p>
                </div>
                
                <div id="qr_content" class="flex flex-col items-center justify-center opacity-0 transition-opacity duration-500">
                    <div id="qrcode" class="bg-white p-2 rounded-xl"></div>
                    <p class="text-[10px] text-slate-300 mt-3 font-mono" id="qr_timer">Valid for 30s</p>
                </div>
            </div>
        </div>
    </div>

    <div class="px-6 -mt-8 relative z-20 space-y-8">
        
        <section>
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">My Points</h3>
            <div class="space-y-3">
                <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-100 flex items-center justify-center text-amber-600 text-lg">🌍</div>
                        <div>
                            <h4 class="text-xs font-black text-slate-800 uppercase">Global Point</h4>
                            <p class="text-[9px] text-slate-400 font-bold">통합 사용 가능</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-lg font-black text-amber-500"><?php echo number_format($global_point); ?></span>
                        <span class="text-[10px] font-bold text-slate-400">P</span>
                    </div>
                </div>

                <div class="flex gap-3 overflow-x-auto hide-scroll pb-2">
                    <?php if(empty($points['SINGLE'])): ?>
                        <div class="min-w-[140px] bg-white p-4 rounded-3xl border border-slate-100 flex flex-col justify-center items-center text-center">
                            <span class="text-xs font-bold text-slate-300">No Single<br>Points</span>
                        </div>
                    <?php else: foreach($points['SINGLE'] as $p): ?>
                    <div class="min-w-[160px] bg-white p-4 rounded-3xl border border-sky-100 shadow-sm flex flex-col justify-between h-32 relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-16 h-16 bg-sky-50 rounded-full"></div>
                        <div>
                            <span class="text-[9px] font-black text-sky-500 uppercase tracking-wide bg-sky-50 px-2 py-1 rounded-md">Single</span>
                            <h5 class="text-xs font-bold text-slate-700 mt-2 leading-tight"><?php echo htmlspecialchars($p['store_name']); ?></h5>
                        </div>
                        <div class="text-right">
                            <span class="text-base font-black text-slate-800"><?php echo number_format($p['balance']); ?></span> <span class="text-[9px]">P</span>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>

                    <?php foreach($points['MULTI'] as $p): ?>
                    <div class="min-w-[160px] bg-white p-4 rounded-3xl border border-emerald-100 shadow-sm flex flex-col justify-between h-32 relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-16 h-16 bg-emerald-50 rounded-full"></div>
                        <div>
                            <span class="text-[9px] font-black text-emerald-500 uppercase tracking-wide bg-emerald-50 px-2 py-1 rounded-md">Multi</span>
                            <h5 class="text-xs font-bold text-slate-700 mt-2 leading-tight"><?php echo htmlspecialchars($p['store_name']); ?></h5>
                        </div>
                        <div class="text-right">
                            <span class="text-base font-black text-slate-800"><?php echo number_format($p['balance']); ?></span> <span class="text-[9px]">P</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section>
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">My Coupons</h3>
            
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2 bg-gradient-to-r from-violet-500 to-fuchsia-600 p-5 rounded-3xl shadow-lg text-white relative overflow-hidden">
                    <div class="relative z-10 flex justify-between items-center">
                        <div>
                            <h4 class="text-sm font-black italic uppercase tracking-tighter">WE Coupon</h4>
                            <p class="text-[9px] font-medium opacity-80">통합 교환형 쿠폰</p>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-black"><?php echo number_format($we_coupon_cnt); ?></span>
                            <span class="text-[10px] font-bold">CP</span>
                        </div>
                    </div>
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10"></div>
                </div>

                <div class="bg-white p-4 rounded-3xl border border-slate-100 shadow-sm flex flex-col justify-between h-36">
                    <div>
                        <div class="w-8 h-8 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 text-xs mb-2">AD</div>
                        <h4 class="text-[10px] font-black uppercase text-slate-500">AD Coupon</h4>
                    </div>
                    <div class="text-right">
                        <span class="text-xl font-black text-slate-800"><?php echo number_format($ad_coupon_cnt); ?></span>
                        <span class="text-[9px] text-slate-400 font-bold">CP</span>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-3xl border border-slate-100 shadow-sm flex flex-col justify-between h-36">
                    <div>
                        <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 text-xs mb-2">ME</div>
                        <h4 class="text-[10px] font-black uppercase text-slate-500">ME Coupons</h4>
                        <p class="text-[8px] text-slate-400 mt-1">
                            <?php echo count($coupons['ME']); ?> Stores
                        </p>
                    </div>
                    <div class="text-right">
                        <button onclick="alert('ME쿠폰 상세 보기로 이동')" class="text-[9px] font-bold text-rose-500 underline">View All</button>
                    </div>
                </div>
            </div>
            
            <div class="mt-3 space-y-2">
                <?php foreach(array_slice($coupons['ME'], 0, 3) as $c): ?>
                <div class="bg-white px-4 py-3 rounded-2xl border border-slate-50 flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-600 truncate w-32"><?php echo htmlspecialchars($c['store_name']); ?></span>
                    <div>
                        <span class="text-xs font-black text-rose-500"><?php echo number_format($c['balance']); ?></span>
                        <span class="text-[8px] text-slate-400">CP</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

    </div>

    <nav class="fixed bottom-0 w-full bg-white border-t border-slate-100 pb-6 pt-3 px-6 flex justify-between items-center z-50 rounded-t-[2rem] shadow-[0_-5px_20px_rgba(0,0,0,0.02)]">
        <button class="flex flex-col items-center gap-1 text-slate-900">
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
        <button class="flex flex-col items-center gap-1 text-slate-300 hover:text-violet-600 transition-colors">
            <span class="text-lg">🔁</span>
            <span class="text-[8px] font-bold uppercase">Exch</span>
        </button>
        <button class="flex flex-col items-center gap-1 text-slate-300 hover:text-violet-600 transition-colors">
            <span class="text-lg">⚙️</span>
            <span class="text-[8px] font-bold uppercase">Set</span>
        </button>
    </nav>

    <script>
    let qrTimerInterval;

    function generateQR() {
        const cover = document.getElementById('qr_cover');
        const content = document.getElementById('qr_content');
        const qrDiv = document.getElementById('qrcode');
        const timerText = document.getElementById('qr_timer');
        
        // 1. 커버 숨기기
        cover.style.opacity = '0';
        setTimeout(() => cover.style.display = 'none', 300);
        
        // 2. 내용 보이기
        content.style.opacity = '1';
        
        // 3. QR 생성 (임시 데이터: UserID + Timestamp)
        // 실제로는 AJAX로 서버에서 보안 토큰을 받아와야 함
        const securityToken = "USER_<?php echo $user_id; ?>_" + Date.now();
        qrDiv.innerHTML = ""; // 기존 QR 삭제
        new QRCode(qrDiv, {
            text: securityToken,
            width: 128,
            height: 128,
            colorDark : "#0f172a",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        // 4. 타이머 작동 (30초 후 리셋)
        let timeLeft = 30;
        clearInterval(qrTimerInterval);
        qrTimerInterval = setInterval(() => {
            timeLeft--;
            timerText.innerText = `Valid for ${timeLeft}s`;
            if(timeLeft <= 0) {
                clearInterval(qrTimerInterval);
                resetQR();
            }
        }, 1000);
    }

    function resetQR() {
        const cover = document.getElementById('qr_cover');
        const content = document.getElementById('qr_content');
        
        content.style.opacity = '0';
        cover.style.display = 'flex';
        setTimeout(() => cover.style.opacity = '1', 50);
    }
    </script>
</body>
</html>