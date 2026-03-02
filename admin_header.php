<?php
// admin_header.php - 본사 공통 레이아웃(PC 업무 모드): 왼쪽 사이드바 + 메인 영역 시작
// 사용 전에 각 페이지에서 $admin_page_title (필수), $admin_page_subtitle (선택) 설정
if (!isset($admin_page_title)) $admin_page_title = '본사 관리';
$admin_username = $_SESSION['admin_username'] ?? ('id_' . ((int)($_SESSION['admin_id'] ?? 0)));
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$header_locale = $header_locale ?? 'ko';

$current_script = basename($_SERVER['PHP_SELF'] ?? '');

$admin_nav_items = [
    ['href' => 'admin_dashboard.php',           'label' => '대시보드',           'icon' => '🏠'],
    ['href' => 'admin_store_manage.php',        'label' => '가맹점 관리',         'icon' => '🏪'],
    ['href' => 'admin_store_applications.php',  'label' => '가맹점 신청 승인',   'icon' => '📝'],
    ['href' => 'admin_menu_format_list.php',    'label' => '메뉴 스킨',           'icon' => '📋'],
    ['href' => 'admin_region_manage.php',       'label' => 'Region Groups',      'icon' => '🗺️'],
    ['href' => 'admin_policy_manage.php',       'label' => 'Policy Master',       'icon' => '📜'],
    ['href' => 'admin_sales.php',               'label' => 'Sales 승인',          'icon' => '💰'],
    ['href' => 'admin_settlement.php',          'label' => 'Settlement',          'icon' => '🏦'],
    ['href' => 'admin_revenue.php',             'label' => '매출 리포팅',         'icon' => '📈'],
    ['href' => 'admin_gift_card.php',           'label' => '기프트카드 발급',       'icon' => '🎁'],
    ['href' => 'admin_ledger.php',              'label' => 'Trx Ledger',         'icon' => '📊'],
    ['href' => 'admin_activity_log.php',        'label' => '로그 분석',           'icon' => '📋'],
    ['href' => 'admin_unified_settings.php',    'label' => '통합설정',            'icon' => '⚙️'],
    ['href' => 'admin_delivery_api_manage.php', 'label' => 'Delivery API',        'icon' => '🚚'],
    ['href' => 'admin_rider_applications.php',  'label' => 'Rider 등록 승인',     'icon' => '📝'],
    ['href' => 'admin_rider_manage.php',        'label' => '본사 Rider 관리',     'icon' => '🏍'],
    ['href' => 'admin_delivery_waiting.php',    'label' => '배달 대기 현황',       'icon' => '📋'],
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($admin_page_title); ?> - Alrira 본사</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard'; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex">
    <aside class="w-56 lg:w-64 shrink-0 bg-slate-900 text-white flex flex-col min-h-screen fixed left-0 top-0 z-30">
        <div class="p-4 border-b border-slate-700 flex items-center justify-between gap-2">
            <a href="admin_dashboard.php" class="flex items-center gap-2 font-black text-lg uppercase tracking-tight shrink-0">
                <span class="text-violet-400">Alrira</span>
                <span class="text-slate-400 text-sm hidden lg:inline">HQ</span>
            </a>
            <a href="admin_layout_switch.php?layout=cards" class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-bold bg-sky-500 text-white hover:bg-sky-600 transition-colors whitespace-nowrap" title="타블렛/간편 보기로 전환">타블렛 모드</a>
        </div>
        <nav class="flex-1 overflow-y-auto py-2">
            <?php foreach ($admin_nav_items as $item): ?>
            <?php $is_active = ($current_script === basename($item['href'])); ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>"
               class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold mx-2 rounded-xl transition-colors <?php echo $is_active ? 'bg-violet-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <span class="text-lg"><?php echo $item['icon']; ?></span>
                <span class="truncate"><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="p-4 border-t border-slate-700 space-y-2 text-xs">
            <div class="text-slate-400 truncate"><?php echo htmlspecialchars($admin_name); ?></div>
            <div id="admin-header-datetime" class="text-slate-500">—</div>
            <a href="admin_layout_switch.php?layout=cards" class="block text-sky-400 hover:text-sky-300 font-bold">타블렛 모드</a>
            <a href="logout.php" class="block text-rose-400 hover:text-rose-300 font-bold">Logout</a>
        </div>
    </aside>
    <main class="flex-1 min-h-screen pl-56 lg:pl-64">
        <div class="p-4 md:p-6 lg:p-8">
            <header class="mb-6">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase"><?php echo htmlspecialchars($admin_page_title); ?></h1>
                <?php if (!empty($admin_page_subtitle)): ?>
                <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($admin_page_subtitle); ?></p>
                <?php endif; ?>
            </header>
