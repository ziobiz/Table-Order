<?php
// store_category_manage.php - 가맹점(파트너): 본사 포맷의 카테고리 목록 (읽기 전용)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = (int)$_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? '';

$menu_format_id = 1;
try {
    $stmt = $pdo->prepare("SELECT menu_format_id FROM stores WHERE id = ?");
    $stmt->execute([$store_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['menu_format_id'])) $menu_format_id = (int)$row['menu_format_id'];
} catch (PDOException $e) {}

$categories = [];
try {
    $stmt = $pdo->prepare("SELECT id, category_name, sort_order FROM categories WHERE menu_format_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$menu_format_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $pdo->prepare("SELECT id, category_name, sort_order FROM categories WHERE store_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$store_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>카테고리 - <?php echo htmlspecialchars($store_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-[96rem] mx-auto">
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-black text-slate-800 uppercase italic">카테고리</h1>
            <a href="store_menu_list.php" class="text-sm font-bold text-slate-500 hover:text-slate-700">← 메뉴 관리</a>
        </header>
        <p class="mb-6 text-slate-500 text-sm font-bold">본사가 제공한 메뉴 포맷의 카테고리입니다. 카테고리 추가·수정은 본사에서만 가능합니다.</p>
        <ul class="space-y-2">
            <?php foreach ($categories as $c): ?>
                <li class="bg-white rounded-xl shadow border border-slate-100 p-4 flex justify-between items-center">
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($c['category_name']); ?></span>
                    <span class="text-xs text-slate-500">순서 <?php echo (int)$c['sort_order']; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (empty($categories)): ?><p class="text-slate-500 text-sm mt-4">할당된 포맷에 카테고리가 없습니다.</p><?php endif; ?>
    </div>
</body>
</html>
