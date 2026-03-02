<?php
// admin_card_header.php - 본사 카드 모드용 공통 상단 (use_sidebar=false일 때)
// 사용 전: $admin_page_title, $admin_username, $admin_name, $admin_login_at, $header_locale 설정
// 선택: $admin_page_subtitle
if (!isset($admin_page_subtitle)) $admin_page_subtitle = '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($admin_page_title); ?> - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-10">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 shrink-0">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter"><?php echo htmlspecialchars($admin_page_title); ?></h1>
                <?php if ($admin_page_subtitle): ?>
                <span class="text-xs text-slate-400 font-bold hidden sm:inline"><?php echo htmlspecialchars($admin_page_subtitle); ?></span>
                <?php endif; ?>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-4 sm:gap-6 text-xs font-bold">
                <span class="text-slate-500 whitespace-nowrap">접속자 ID <?php echo htmlspecialchars($admin_username ?? ''); ?> · <?php echo htmlspecialchars($admin_name ?? ''); ?></span>
                <span id="current-datetime" class="text-slate-600 whitespace-nowrap">—</span>
                <span class="text-slate-500 whitespace-nowrap">머문 <span id="elapsed-time">0분 0초</span></span>
                <button type="button" onclick="location.href='admin_dashboard.php'" class="bg-white border-2 border-slate-200 px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all shrink-0">Back to Dashboard</button>
                <a href="logout.php" class="text-rose-500 hover:underline whitespace-nowrap shrink-0">Logout</a>
            </div>
        </header>
