<?php
// admin_menu_format_list.php - 본사: 메뉴 솔루션(업종별 포맷) 목록
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('본사 관리자 전용입니다.'); location.href='login.php';</script>"; exit;
}

// 미리 정의 포맷 15종 일괄 추가 (포맷명만 추가, 카테고리·옵션은 템플릿으로 생성)
$preset_formats = [
    ['한식당', '한식당 업종용 메뉴 포맷'],
    ['양식당', '양식당 업종용 메뉴 포맷'],
    ['피자음식점', '피자 전문점 업종용 메뉴 포맷'],
    ['마라탕음식점', '마라탕 전문점 업종용 메뉴 포맷'],
    ['중국음식점', '중국음식점 업종용 메뉴 포맷'],
    ['마사지샵', '마사지샵 서비스 포맷'],
    ['약국', '약국 업종용 포맷'],
    ['일본스시집', '일본 스시/초밥 전문점 업종용 메뉴 포맷'],
    ['비어가든', '비어가든/호프 업종용 메뉴 포맷'],
    ['스테이크하우스', '스테이크하우스 업종용 메뉴 포맷'],
    ['부페식당', '부페(뷔페) 식당 업종용 메뉴 포맷'],
    ['커피숍', '커피숍/카페 (사이즈·샷·시럽·온도)'],
    ['베이커리샵', '베이커리/빵집 (수량·포장·컷팅)'],
    ['드라이브스루', '드라이브스루/패스트푸드 (세트·사이즈)'],
    ['샤브샤브점', '샤브샤브 전문점 (국물·밥/국수·추가)'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_presets') {
    $stmt = $pdo->prepare("SELECT id FROM menu_formats WHERE name = ?");
    $ins = $pdo->prepare("INSERT INTO menu_formats (name, description, sort_order, is_active) VALUES (?, ?, ?, 1)");
    $added = 0;
    $sort = 10;
    foreach ($preset_formats as $p) {
        $stmt->execute([$p[0]]);
        if ($stmt->fetch()) continue; // 이미 있으면 스킵
        $ins->execute([$p[0], $p[1], $sort]);
        $added++;
        $sort += 10;
    }
    $_SESSION['format_added_msg'] = $added;
    header('Location: admin_menu_format_list.php');
    exit;
}

$formats = [];
try {
    $formats = $pdo->query("SELECT * FROM menu_formats ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $formats = []; }

// 포맷별 가맹점 수
$store_counts = [];
foreach ($formats as $f) {
    try {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE menu_format_id = ?");
        $cnt->execute([$f['id']]);
        $store_counts[$f['id']] = $cnt->fetchColumn();
    } catch (PDOException $e) { $store_counts[$f['id']] = 0; }
}

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = '메뉴 스킨';
    $admin_page_subtitle = '업종별 주문 메뉴 형식(스킨) 제공 · 카테고리/메뉴/옵션 관리';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>메뉴 스킨 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-10">
        <header class="flex justify-between items-end">
            <div>
                <h1 class="text-4xl font-black italic text-slate-900 uppercase tracking-tighter">메뉴 스킨</h1>
                <p class="text-slate-500 text-xs font-bold mt-2 uppercase">업종별 주문 메뉴 형식(스킨) 제공 · 카테고리/메뉴/옵션 관리</p>
            </div>
            <div class="flex space-x-2">
                <a href="admin_menu_format_edit.php" class="inline-block bg-white border-2 border-slate-200 px-6 py-3 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all">+ 포맷 추가</a>
                <button onclick="location.href='admin_dashboard.php'" class="bg-white border-2 border-slate-200 px-6 py-3 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all">Back to Dashboard</button>
            </div>
        </header>
<?php endif; ?>

        <div class="max-w-[96rem] space-y-10">
        <?php if (isset($_SESSION['format_added_msg'])): $n = (int)$_SESSION['format_added_msg']; unset($_SESSION['format_added_msg']); ?>
            <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-bold">
                미리 정의 포맷 <?php echo $n; ?>개가 추가되었습니다.
            </div>
        <?php endif; ?>

        <div class="mb-8 p-6 rounded-2xl bg-slate-100 border border-slate-200">
            <h2 class="text-sm font-black text-slate-700 uppercase mb-2">미리 정의 포맷 일괄 추가</h2>
            <p class="text-xs text-slate-500 mb-4">한식당, 양식당, 피자, 마라탕, 중국음식, 마사지샵, 약국, 일본스시, 비어가든, 스테이크하우스, 부페, 커피숍, 베이커리샵, 드라이브스루, 샤브샤브점 (이미 있는 이름은 제외). 카테고리·옵션은 포맷 추가 화면에서 템플릿으로 생성하세요.</p>
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="add_presets">
                <button type="submit" class="bg-violet-500 text-white px-5 py-2 rounded-xl font-bold text-sm hover:bg-violet-600">15종 포맷 일괄 추가</button>
            </form>
        </div>

        <?php if (empty($formats)): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center">
                <p class="text-amber-800 font-bold mb-4">메뉴 포맷이 없습니다. DB에 menu_formats 테이블과 기본 데이터가 있는지 확인 후, 포맷을 추가해 주세요.</p>
                <a href="admin_menu_format_edit.php" class="inline-block bg-sky-500 text-white px-6 py-3 rounded-xl font-black">첫 포맷 추가</a>
            </div>
        <?php else: ?>
            <ul class="space-y-4">
                <?php foreach ($formats as $f): ?>
                    <li class="bg-white rounded-2xl shadow border border-slate-100 p-6 flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-black text-slate-800"><?php echo htmlspecialchars($f['name']); ?></h3>
                            <?php if (!empty($f['description'])): ?>
                                <p class="text-sm text-slate-500 mt-1"><?php echo htmlspecialchars($f['description']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-slate-400 mt-2">할당 가맹점: <?php echo (int)($store_counts[$f['id']] ?? 0); ?>곳</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="admin_menu_by_format.php?format_id=<?php echo (int)$f['id']; ?>" class="bg-slate-800 text-white px-5 py-2 rounded-xl font-bold text-sm hover:bg-slate-700">카테고리/메뉴 관리</a>
                            <a href="admin_menu_format_edit.php?id=<?php echo (int)$f['id']; ?>" class="bg-slate-200 text-slate-700 px-5 py-2 rounded-xl font-bold text-sm hover:bg-slate-300">포맷 수정</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
    </div>
</body>
</html>
<?php endif; ?>
