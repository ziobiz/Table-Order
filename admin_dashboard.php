<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') { header("Location: login.php"); exit; }
include 'common.php';
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? ('id_' . $admin_id);
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$admin_login_at = (int)($_SESSION['admin_login_at'] ?? time());
$header_locale = 'ko';

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = '대시보드';
    $admin_page_subtitle = '본사 통합 관리 · PC 업무 모드';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard'; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-10">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 shrink-0">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter">HQ Dashboard</h1>
                <span class="text-xs text-slate-400 font-bold hidden sm:inline">본사 통합 관리 · 타블렛/간편 보기</span>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-4 sm:gap-6 text-xs font-bold">
                <a href="admin_layout_switch.php?layout=sidebar&back=admin_dashboard.php" class="px-4 py-2 bg-violet-500 text-white rounded-2xl hover:bg-violet-600 font-black uppercase shrink-0">PC 업무 모드 (사이드바)</a>
                <span class="text-slate-500 whitespace-nowrap">접속자 ID <?php echo htmlspecialchars($admin_username); ?> · <?php echo htmlspecialchars($admin_name); ?></span>
                <span id="current-datetime" class="text-slate-600 whitespace-nowrap">—</span>
                <span class="text-slate-500 whitespace-nowrap">머문 <span id="elapsed-time">0분 0초</span></span>
                <a href="logout.php" class="text-rose-500 hover:underline whitespace-nowrap shrink-0">Logout</a>
            </div>
        </header>
<?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="admin_store_manage.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-violet-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-violet-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🏪</div>
                <h3 class="text-lg font-black text-slate-800">Store Manage</h3>
                <p class="text-xs text-slate-400 mt-2">가맹점 등록, 정보 수정, 정책·메뉴 스킨 할당</p>
            </a>

            <a href="admin_store_applications.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-emerald-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📝</div>
                <h3 class="text-lg font-black text-slate-800">가맹점 신청 승인</h3>
                <p class="text-xs text-slate-400 mt-2">온라인 입점 신청 목록 · 승인 시 계정 오픈</p>
            </a>

            <a href="admin_revenue.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-sky-200 hover:border-sky-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-sky-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📈</div>
                <h3 class="text-lg font-black text-slate-800">매출 리포팅</h3>
                <p class="text-xs text-slate-400 mt-2">채널별·결제수단별·메뉴별 매출, 기간 필터</p>
            </a>

            <a href="admin_gift_card.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-rose-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-rose-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🎁</div>
                <h3 class="text-lg font-black text-slate-800">기프트카드 발급</h3>
                <p class="text-xs text-slate-400 mt-2">기프트카드 발급·잔액 조회·주문 시 사용</p>
            </a>

            <a href="admin_menu_format_list.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-sky-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-sky-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📋</div>
                <h3 class="text-lg font-black text-slate-800">메뉴 스킨</h3>
                <p class="text-xs text-slate-400 mt-2">업종별 주문 메뉴 형식(스킨) 제공·카테고리/메뉴/옵션 관리</p>
            </a>

            <a href="admin_region_manage.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-emerald-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🗺️</div>
                <h3 class="text-lg font-black text-slate-800">Region Groups</h3>
                <p class="text-xs text-slate-400 mt-2">멀티 포인트 공유용 지역 그룹 관리</p>
            </a>

            <a href="admin_policy_manage.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-amber-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📜</div>
                <h3 class="text-lg font-black text-slate-800">Policy Master</h3>
                <p class="text-xs text-slate-400 mt-2">6대 정책 표준 템플릿 관리</p>
            </a>

            <a href="admin_sales.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-emerald-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">💰</div>
                <h3 class="text-lg font-black text-slate-800">Sales Approval</h3>
                <p class="text-xs text-slate-400 mt-2">가맹점 포인트/쿠폰 구매 승인</p>
            </a>

            <a href="admin_settlement.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-sky-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-sky-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🏦</div>
                <h3 class="text-lg font-black text-slate-800">Settlement</h3>
                <p class="text-xs text-slate-400 mt-2">가맹점 정산 요청 지급 처리</p>
            </a>

            <a href="admin_ledger.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-slate-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📊</div>
                <h3 class="text-lg font-black text-slate-800">Trx Ledger</h3>
                <p class="text-xs text-slate-400 mt-2">전체 거래 흐름 및 감사 로그</p>
            </a>

            <a href="admin_activity_log.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-slate-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📋</div>
                <h3 class="text-lg font-black text-slate-800">로그 분석</h3>
                <p class="text-xs text-slate-400 mt-2">본사 변경 이력 · 페이지별 구분 · 보관 기간은 통합설정</p>
            </a>

            <a href="admin_unified_settings.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-violet-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-violet-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">⚙️</div>
                <h3 class="text-lg font-black text-slate-800">통합설정</h3>
                <p class="text-xs text-slate-400 mt-2">본사·가맹점 로그 보관 기간 등 일괄 적용</p>
            </a>

            <a href="admin_delivery_api_manage.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-orange-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-orange-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🚚</div>
                <h3 class="text-lg font-black text-slate-800">Delivery API</h3>
                <p class="text-xs text-slate-400 mt-2">계약된 배달앱 API 관리 · 가맹점은 키만 입력해 연동</p>
            </a>

            <a href="admin_rider_applications.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-sky-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-sky-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📝</div>
                <h3 class="text-lg font-black text-slate-800">Rider 등록 신청 승인</h3>
                <p class="text-xs text-slate-400 mt-2">본사 Rider 온라인 신청 · 승인 시 로그인 가능</p>
            </a>

            <a href="admin_rider_manage.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-sky-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-sky-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">🏍</div>
                <h3 class="text-lg font-black text-slate-800">본사 Rider 관리</h3>
                <p class="text-xs text-slate-400 mt-2">배민 구조 · 본사 소속 Rider 목록 (대기 콜 수락)</p>
            </a>

            <a href="admin_delivery_waiting.php" class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 hover:border-amber-500 hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">📋</div>
                <h3 class="text-lg font-black text-slate-800">배달 대기 현황</h3>
                <p class="text-xs text-slate-400 mt-2">본사 Rider 수락 대기 중인 배달 목록</p>
            </a>
        </div>
<?php if (!$use_sidebar): ?>
    </div>
    <script>
    (function() {
        var loginAt = <?php echo $login_at; ?> * 1000;
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
<?php else: ?>
<?php include 'admin_footer.php'; ?>
<?php endif; ?>