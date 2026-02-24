<?php
// admin_activity_log.php - 본사: 로그 분석(변경 이력) · 30일 보관 후 자동 삭제
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') { header("Location: login.php"); exit; }
include 'db_config.php';
include 'common.php';

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? '';
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$login_at = (int)($_SESSION['admin_login_at'] ?? time());
$header_locale = 'ko';

$admin_retention_days = get_activity_log_retention_days($pdo, 'admin');
// 설정된 일수 초과 본사 로그 자동 삭제 (통합설정 · 일괄 적용)
try {
    $pdo->exec("DELETE FROM activity_logs WHERE scope = 'admin' AND created_at < DATE_SUB(NOW(), INTERVAL " . (int)$admin_retention_days . " DAY)");
} catch (Exception $e) {}

$page_filter = isset($_GET['page']) ? trim($_GET['page']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = "scope = 'admin'";
$params = [];
if ($page_filter !== '') {
    $where .= " AND page = ?";
    $params[] = $page_filter;
}
if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $where .= " AND created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}
if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $where .= " AND created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$list = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE $where ORDER BY page ASC, id DESC LIMIT 500");
    $stmt->execute($params);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 페이지별로 그룹 (표시용)
$by_page = [];
foreach ($list as $row) {
    $p = $row['page'] ?: '(미지정)';
    if (!isset($by_page[$p])) $by_page[$p] = [];
    $by_page[$p][] = $row;
}

// 페이지 목록 (필터용)
$pages = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT page FROM activity_logs WHERE scope = 'admin' ORDER BY page");
    $pages = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = '로그 분석';
    $admin_page_subtitle = '본사 변경 이력 · ' . (int)$admin_retention_days . '일 보관 후 자동 삭제 (통합설정)';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>로그 분석 - 본사 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard'; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black italic text-slate-900 uppercase">로그 분석</h1>
                <p class="text-xs text-slate-500 mt-1">본사 변경 이력 · <?php echo (int)$admin_retention_days; ?>일 보관 후 자동 삭제 (통합설정)</p>
            </div>
            <a href="admin_dashboard.php" class="bg-white border-2 border-slate-200 px-5 py-2.5 rounded-2xl text-xs font-black uppercase hover:bg-slate-50">Back to Dashboard</a>
        </header>
<?php endif; ?>

        <div class="max-w-[96rem] space-y-6">
        <form method="GET" class="bg-white rounded-2xl border border-slate-100 p-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">페이지</label>
                <select name="page" class="px-3 py-2 border border-slate-200 rounded-xl text-sm">
                    <option value="">전체</option>
                    <?php foreach ($pages as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $page_filter === $p ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">시작일</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="px-3 py-2 border border-slate-200 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">종료일</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="px-3 py-2 border border-slate-200 rounded-xl text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-xs font-black uppercase">조회</button>
        </form>

        <div class="space-y-8">
            <?php if (empty($by_page)): ?>
            <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 p-12 text-center text-slate-400 text-sm">조건에 맞는 기록이 없습니다.</div>
            <?php else: ?>
            <?php foreach ($by_page as $page_name => $rows): ?>
            <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
                <div class="px-5 py-4 bg-slate-100 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-black text-slate-800 uppercase"><?php echo htmlspecialchars($page_name); ?></h2>
                    <span class="text-[10px] text-slate-500"><?php echo count($rows); ?>건 · 보관 <?php echo (int)$admin_retention_days; ?>일</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50">
                                <th class="py-2 px-3 font-black text-slate-600 uppercase text-[10px]">일시</th>
                                <th class="py-2 px-3 font-black text-slate-600 uppercase text-[10px]">접속자</th>
                                <th class="py-2 px-3 font-black text-slate-600 uppercase text-[10px]">작업</th>
                                <th class="py-2 px-3 font-black text-slate-600 uppercase text-[10px]">요약</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50">
                                <td class="py-3 px-3 text-slate-600 whitespace-nowrap"><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td class="py-3 px-3 font-bold text-slate-800"><?php echo htmlspecialchars($row['actor_name']); ?> (ID <?php echo (int)$row['actor_id']; ?>)</td>
                                <td class="py-3 px-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-700"><?php echo htmlspecialchars($row['action']); ?></span></td>
                                <td class="py-3 px-3 text-slate-700"><?php echo htmlspecialchars($row['summary']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
</body>
</html>
<?php endif; ?>
