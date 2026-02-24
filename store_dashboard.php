<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
include 'common.php';
$store_id = (int)$_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? '';
$store_login_at = (int)($_SESSION['store_login_at'] ?? time());
$header_locale = $_SESSION['store_locale'] ?? 'ko';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Store Dashboard - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard'; }</style>
</head>
<body class="bg-slate-50 min-h-screen p-8">
    <div class="max-w-[96rem] mx-auto">
        <header class="flex flex-wrap items-center justify-between gap-3 mb-10">
            <div class="flex items-center gap-2 shrink-0">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase">Partner Center</h1>
                <span class="text-xs text-slate-400 font-bold hidden sm:inline"><?php echo htmlspecialchars($store_name); ?></span>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-4 sm:gap-6 text-xs font-bold">
                <span class="text-slate-500 whitespace-nowrap"><span class="text-slate-400">접속자</span> <?php echo htmlspecialchars($store_name); ?> (ID <?php echo $store_id; ?>)</span>
                <span id="current-datetime" class="text-slate-600 whitespace-nowrap">—</span>
                <span class="text-slate-500 whitespace-nowrap">머문 <span id="elapsed-time" class="text-slate-700">0분 0초</span></span>
                <a href="logout.php" class="text-rose-500 hover:underline whitespace-nowrap shrink-0">Logout</a>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="store_pos.php" class="bg-slate-900 text-white p-8 rounded-[2rem] shadow-xl hover:scale-105 transition-transform group relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10"></div>
                <div class="relative z-10">
                    <div class="text-4xl mb-4">🖥️</div>
                    <h3 class="text-xl font-black uppercase">POS System</h3>
                    <p class="text-[10px] opacity-70 mt-2">QR 스캔 / 적립 / 사용</p>
                </div>
            </a>

            <a href="store_buy.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-emerald-500 transition-all group">
                <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🛒</div>
                <h3 class="text-lg font-black text-slate-800">Purchase</h3>
                <p class="text-xs text-slate-400 mt-2">포인트/쿠폰 충전 요청</p>
            </a>

            <a href="store_settlement.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-sky-500 transition-all group">
                <div class="w-12 h-12 bg-sky-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🏧</div>
                <h3 class="text-lg font-black text-slate-800">Settlement</h3>
                <p class="text-xs text-slate-400 mt-2">정산 신청 및 내역 확인</p>
            </a>

            <a href="store_setting.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-violet-500 transition-all group">
                <div class="w-12 h-12 bg-violet-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">⚙️</div>
                <h3 class="text-lg font-black text-slate-800">KDS & 알림 설정</h3>
                <p class="text-xs text-slate-400 mt-2">주방 화면 테마 / 시간 / 소리 변경</p>
            </a>

            <a href="store_qr_manage.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-sky-500 transition-all group">
                <div class="w-12 h-12 bg-sky-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📱</div>
                <h3 class="text-lg font-black text-slate-800">Table QR 생성</h3>
                <p class="text-xs text-slate-400 mt-2">테이블별 주문용 QR 코드 인쇄</p>
            </a>

            <a href="store_menu_list.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-amber-500 transition-all group">
                <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📋</div>
                <h3 class="text-lg font-black text-slate-800">메뉴 관리</h3>
                <p class="text-xs text-slate-400 mt-2">가맹점 전용 메뉴·카테고리 등록·수정</p>
            </a>

            <a href="store_delivery_api.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-orange-500 transition-all group">
                <div class="w-12 h-12 bg-orange-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🚚</div>
                <h3 class="text-lg font-black text-slate-800">배달앱 연동</h3>
                <p class="text-xs text-slate-400 mt-2">배민·라인·그렙 등 키만 입력해 연동</p>
            </a>

            <a href="store_rider_manage.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-amber-500 transition-all group">
                <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🛵</div>
                <h3 class="text-lg font-black text-slate-800">Deliver 관리</h3>
                <p class="text-xs text-slate-400 mt-2">소속 Deliver 등록·수정·로그인 계정</p>
            </a>

            <a href="store_dispatch.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-sky-500 transition-all group">
                <div class="w-12 h-12 bg-sky-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📋</div>
                <h3 class="text-lg font-black text-slate-800">배차 대시보드</h3>
                <p class="text-xs text-slate-400 mt-2">대기 중인 배달에 우리 기사 지정 (수동 배차)</p>
            </a>

            <a href="store_activity_log.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-slate-500 transition-all group">
                <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📋</div>
                <h3 class="text-lg font-black text-slate-800">로그 분석</h3>
                <p class="text-xs text-slate-400 mt-2">가맹점 변경 이력 · 페이지별 구분 · 14일 보관</p>
            </a>
        </div>
    </div>
    <script>
    (function() {
        var loginAt = <?php echo $store_login_at; ?> * 1000;
        var locale = <?php echo json_encode($header_locale); ?>;
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        var thMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        var enMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        function formatDateTimeLocale(now) {
            var y = now.getFullYear(), m = now.getMonth(), d = now.getDate();
            var h = pad(now.getHours()), i = pad(now.getMinutes()), s = pad(now.getSeconds());
            var time = h + ':' + i + ':' + s;
            if (locale === 'th') return d + ' ' + thMonths[m] + ' ' + (y + 543) + ' ' + time;
            if (locale === 'en' || locale === 'en_us') return enMonths[m] + ' ' + d + ', ' + y + ' ' + time;
            if (locale === 'ja') return y + '年' + (m+1) + '月' + d + '日 ' + time;
            if (locale === 'vi') return d + '/' + (m+1) + '/' + y + ' ' + time;
            return y + '년 ' + (m+1) + '월 ' + d + '일 ' + time;
        }
        function formatElapsed(sec) {
            var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
            if (h > 0) return h + '시간 ' + m + '분 ' + s + '초';
            if (m > 0) return m + '분 ' + s + '초';
            return s + '초';
        }
        function tick() {
            var now = new Date();
            var el = document.getElementById('current-datetime');
            if (el) el.textContent = formatDateTimeLocale(now);
            var et = document.getElementById('elapsed-time');
            if (et && loginAt) {
                var sec = Math.max(0, Math.floor((now.getTime() - loginAt) / 1000));
                et.textContent = formatElapsed(sec);
            }
        }
        tick();
        setInterval(tick, 1000);
    })();
    </script>
</body>
</html>