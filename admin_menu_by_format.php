<?php
// admin_menu_by_format.php - 본사: 특정 포맷(업종)의 카테고리/메뉴 관리
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('본사 관리자 전용입니다.'); location.href='login.php';</script>"; exit;
}

$format_id = (int)($_GET['format_id'] ?? 0);
if ($format_id <= 0) {
    echo "<script>alert('포맷을 선택해 주세요.'); location.href='admin_menu_format_list.php';</script>"; exit;
}

$format = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM menu_formats WHERE id = ?");
    $stmt->execute([$format_id]);
    $format = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
if (!$format) {
    echo "<script>alert('포맷을 찾을 수 없습니다.'); location.href='admin_menu_format_list.php';</script>"; exit;
}

$categories = [];
$menus = [];
try {
    $stmt = $pdo->prepare("SELECT id, category_name, sort_order FROM categories WHERE menu_format_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$format_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $pdo->prepare("SELECT id, category_name, sort_order FROM categories WHERE store_id = ? ORDER BY sort_order ASC");
    if ($stmt) { $stmt->execute([1]); $categories = $stmt->fetchAll(PDO::FETCH_ASSOC); }
}
try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.category_id, m.price, m.price_pickup, m.price_delivery, m.is_available, m.image_url,
               (SELECT mt.menu_name FROM menu_translations mt WHERE mt.menu_id = m.id AND mt.lang_code = 'ko' LIMIT 1) AS menu_name_ko
        FROM menus m
        WHERE m.menu_format_id = ?
        ORDER BY m.id DESC
    ");
    $stmt->execute([$format_id]);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $pdo->prepare("SELECT m.id, m.category_id, m.price, m.price_pickup, m.price_delivery, m.is_available, m.image_url,
        (SELECT mt.menu_name FROM menu_translations mt WHERE mt.menu_id = m.id AND mt.lang_code = 'ko' LIMIT 1) AS menu_name_ko
        FROM menus m WHERE m.store_id = 1 ORDER BY m.id DESC");
    if ($stmt) { $stmt->execute(); $menus = $stmt->fetchAll(PDO::FETCH_ASSOC); }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($format['name']); ?> - 카테고리/메뉴 - Alrira HQ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-[96rem] mx-auto">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-800 uppercase italic"><?php echo htmlspecialchars($format['name']); ?></h1>
                <p class="text-xs text-slate-500 mt-1">카테고리·메뉴 관리 (본 포맷에 할당된 가맹점이 이 메뉴를 사용합니다)</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="admin_menu_format_list.php" class="text-sm font-bold text-slate-500 hover:text-slate-700">← 포맷 목록</a>
                <a href="admin_category_edit.php?format_id=<?php echo $format_id; ?>" class="bg-slate-200 text-slate-700 px-5 py-3 rounded-xl font-bold text-sm">+ 카테고리 추가</a>
                <a href="admin_menu_edit.php?format_id=<?php echo $format_id; ?>&id=0" class="bg-sky-500 text-white px-5 py-3 rounded-xl font-black text-sm">+ 메뉴 추가</a>
            </div>
        </header>

        <section class="mb-10">
            <h2 class="text-lg font-black text-slate-700 mb-4 uppercase">카테고리</h2>
            <?php if (empty($categories)): ?>
                <p class="text-slate-500 text-sm mb-4">카테고리가 없습니다. 메뉴 추가 전에 카테고리를 먼저 추가해 주세요.</p>
            <?php else: ?>
                <ul class="flex flex-wrap gap-2">
                    <?php foreach ($categories as $c): ?>
                        <li class="bg-white rounded-xl shadow border border-slate-100 px-4 py-2 flex items-center gap-2">
                            <span class="font-bold text-slate-800"><?php echo htmlspecialchars($c['category_name']); ?></span>
                            <a href="admin_category_edit.php?format_id=<?php echo $format_id; ?>&id=<?php echo (int)$c['id']; ?>" class="text-xs font-bold text-sky-500 hover:underline">수정</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section>
            <h2 class="text-lg font-black text-slate-700 mb-4 uppercase">메뉴</h2>
            <?php if (empty($menus)): ?>
                <p class="text-slate-500 text-sm mb-4">메뉴가 없습니다.</p>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($menus as $m): ?>
                        <li class="bg-white rounded-2xl shadow border border-slate-100 p-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <?php if (!empty($m['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($m['image_url']); ?>" alt="" class="w-14 h-14 rounded-xl object-cover">
                                <?php else: ?>
                                    <div class="w-14 h-14 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 text-xs font-bold">No Image</div>
                                <?php endif; ?>
                                <div>
                                    <p class="font-black text-slate-800"><?php echo htmlspecialchars($m['menu_name_ko'] ?? 'Menu #'.$m['id']); ?></p>
                                    <p class="text-xs text-slate-500">매장 <?php echo number_format($m['price']); ?>원 · 픽업 <?php echo number_format($m['price_pickup']); ?>원 · 배달 <?php echo number_format($m['price_delivery']); ?>원</p>
                                </div>
                            </div>
                            <a href="admin_menu_edit.php?id=<?php echo (int)$m['id']; ?>" class="bg-slate-800 text-white px-4 py-2 rounded-xl font-bold text-sm hover:bg-slate-700">수정</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
