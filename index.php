<?php
// index.php - 고객 주문 시작 페이지 (판매 방식 선택)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// QR 파라미터로부터 매장/테이블 정보 인식 (비회원 포함 공통)
$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : (int)($_SESSION['store_id'] ?? 1);
if ($store_id < 1) { $store_id = 1; }
$_SESSION['store_id'] = $store_id;

if (isset($_GET['table_no'])) {
    // 테이블 번호는 숫자/영문/하이픈만 허용 (예: A-1, 5 등)
    $raw_table = (string)$_GET['table_no'];
    $table_no = preg_replace('/[^0-9A-Za-z\-]/', '', $raw_table);
    if ($table_no === '') {
        $table_no = '1';
    }
    $_SESSION['table_no'] = $table_no;
}

// 매장 기본 정보 로드 (상호명 등)
$stmt = $pdo->prepare("SELECT store_name, currency_code FROM stores WHERE id = ?");
$stmt->execute([$store_id]);
$store = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($store['store_name']); ?> - Order Now</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; -webkit-tap-highlight-color: transparent; }
        .order-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .order-card:active { transform: scale(0.95); }
        .sky-gradient { background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%); }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center justify-center p-6">

    <div class="fixed top-0 left-0 w-full h-1 sky-gradient"></div>

    <div class="w-full max-w-md space-y-10 text-center">
        <div class="animate__animated animate__fadeInDown">
            <h1 class="text-4xl font-black text-slate-800 tracking-tighter italic uppercase mb-2">
                <?php echo htmlspecialchars($store['store_name'] ?? ''); ?>
            </h1>
            <p class="text-sky-500 font-bold text-sm tracking-widest uppercase">Digital Menu Service</p>
            <?php if (!empty($_SESSION['table_no'])): ?>
                <p class="mt-2 text-[11px] font-bold text-slate-400 tracking-widest uppercase">
                    Table <?php echo htmlspecialchars($_SESSION['table_no']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="space-y-4 animate__animated animate__fadeInUp animate__delay-1s">
            <p class="text-slate-400 text-xs font-black uppercase tracking-widest mb-6">Please select your order type</p>
            
            <button onclick="selectType('dinein')" class="order-card w-full bg-white p-6 rounded-[2rem] shadow-xl shadow-slate-200 border border-slate-100 flex items-center justify-between group hover:border-sky-500">
                <div class="flex items-center space-x-5">
                    <div class="bg-sky-100 p-4 rounded-2xl group-hover:bg-sky-500 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </div>
                    <div class="text-left">
                        <h3 class="text-xl font-black text-slate-800">매장 식사</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">Eat Inside the Restaurant</p>
                    </div>
                </div>
                <span class="text-sky-500 font-black text-xl mr-2">→</span>
            </button>

            <button onclick="selectType('pickup')" class="order-card w-full bg-white p-6 rounded-[2rem] shadow-xl shadow-slate-200 border border-slate-100 flex items-center justify-between group hover:border-sky-500">
                <div class="flex items-center space-x-5">
                    <div class="bg-emerald-50 p-4 rounded-2xl group-hover:bg-sky-500 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    </div>
                    <div class="text-left">
                        <h3 class="text-xl font-black text-slate-800">방문 포장</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">Pickup / Take-away</p>
                    </div>
                </div>
                <span class="text-sky-500 font-black text-xl mr-2">→</span>
            </button>

            <button onclick="selectType('delivery')" class="order-card w-full bg-white p-6 rounded-[2rem] shadow-xl shadow-slate-200 border border-slate-100 flex items-center justify-between group hover:border-sky-500">
                <div class="flex items-center space-x-5">
                    <div class="bg-rose-50 p-4 rounded-2xl group-hover:bg-sky-500 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    </div>
                    <div class="text-left">
                        <h3 class="text-xl font-black text-slate-800">배달 주문</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">Delivery to your home</p>
                    </div>
                </div>
                <span class="text-sky-500 font-black text-xl mr-2">→</span>
            </button>
        </div>

        <div class="pt-10 opacity-50">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Powered by ALRIRA GLOBAL</p>
        </div>
    </div>

    <script>
        function selectType(type) {
            // 세션에 저장된 매장/테이블 정보를 유지한 상태로 메뉴판으로 이동합니다.
            const params = new URLSearchParams();
            params.set('order_type', type);
            <?php if (!empty($store_id)): ?>
            params.set('store_id', '<?php echo (int)$store_id; ?>');
            <?php endif; ?>
            <?php if (!empty($_SESSION['table_no'])): ?>
            params.set('table_no', '<?php echo htmlspecialchars($_SESSION['table_no']); ?>');
            <?php endif; ?>
            location.href = 'menu.php?' + params.toString();
        }
    </script>
</body>
</html>