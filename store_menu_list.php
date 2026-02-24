<?php
// store_menu_list.php - 가맹점(파트너): 본사가 제공한 포맷의 메뉴 목록 (가격/판매여부만 수정 가능)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = (int)$_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? '';
$migration_msg = '';

// 본사가 할당한 메뉴 포맷 ID
$menu_format_id = 1;
try {
    $stmt = $pdo->prepare("SELECT menu_format_id FROM stores WHERE id = ?");
    $stmt->execute([$store_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['menu_format_id'])) $menu_format_id = (int)$row['menu_format_id'];
} catch (PDOException $e) {}

$menus = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.category_id, m.price, m.price_pickup, m.price_delivery, m.is_available, m.is_dinein, m.is_pickup, m.is_delivery, m.image_url,
               (SELECT mt.menu_name FROM menu_translations mt WHERE mt.menu_id = m.id AND mt.lang_code = 'ko' LIMIT 1) AS menu_name_ko
        FROM menus m
        WHERE m.menu_format_id = ?
        ORDER BY m.id DESC
    ");
    $stmt->execute([$menu_format_id]);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'menu_format_id') !== false) {
        $stmt = $pdo->prepare("SELECT m.id, m.category_id, m.price, m.price_pickup, m.price_delivery, m.is_available, m.is_dinein, m.is_pickup, m.is_delivery, m.image_url,
            (SELECT mt.menu_name FROM menu_translations mt WHERE mt.menu_id = m.id AND mt.lang_code = 'ko' LIMIT 1) AS menu_name_ko
            FROM menus m WHERE m.store_id = ? ORDER BY m.id DESC");
        $stmt->execute([$store_id]);
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $migration_msg = '메뉴 솔루션(포맷) 구조 적용을 위해 DB 마이그레이션(docs/sql_menu_format_solution.sql)을 실행해 주세요.';
    }
}

// 가맹점별 오버라이드 (가격·판매여부)
$overrides = [];
try {
    $stmt = $pdo->prepare("SELECT menu_id, price_override, price_pickup_override, price_delivery_override, is_available_override FROM store_menu_overrides WHERE store_id = ?");
    $stmt->execute([$store_id]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $overrides[$r['menu_id']] = $r; }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>메뉴 관리 - <?php echo htmlspecialchars($store_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="max-w-[96rem] mx-auto p-6">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-800 uppercase italic">메뉴 관리</h1>
                <p class="text-xs text-slate-500 font-bold mt-1"><?php echo htmlspecialchars($store_name); ?> · 본사 제공 메뉴 형식 사용 (가격/판매여부만 수정)</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="store_dashboard.php" class="text-sm font-bold text-slate-500 hover:text-slate-700">대시보드</a>
            </div>
        </header>

        <?php if (!empty($migration_msg)): ?>
            <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-bold">
                <?php echo htmlspecialchars($migration_msg); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($menus)): ?>
            <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-12 text-center">
                <p class="text-slate-500 font-bold mb-6">본사에서 할당한 메뉴 포맷에 메뉴가 없습니다. 본사에 문의해 주세요.</p>
                <a href="store_dashboard.php" class="inline-block bg-sky-500 text-white px-8 py-4 rounded-2xl font-black hover:bg-sky-600">대시보드로</a>
            </div>
        <?php else: ?>
            <ul class="space-y-3">
                <?php foreach ($menus as $m):
                    $ov = $overrides[$m['id']] ?? null;
                    $price = $ov && $ov['price_override'] !== null ? (int)$ov['price_override'] : (int)$m['price'];
                    $price_pickup = $ov && $ov['price_pickup_override'] !== null ? (int)$ov['price_pickup_override'] : (int)$m['price_pickup'];
                    $price_delivery = $ov && $ov['price_delivery_override'] !== null ? (int)$ov['price_delivery_override'] : (int)$m['price_delivery'];
                    $is_avail = $ov && $ov['is_available_override'] !== null ? (int)$ov['is_available_override'] : (int)$m['is_available'];
                ?>
                    <li class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <?php if (!empty($m['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($m['image_url']); ?>" alt="" class="w-16 h-16 rounded-xl object-cover">
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 text-xs font-bold">No Image</div>
                            <?php endif; ?>
                            <div>
                                <p class="font-black text-slate-800"><?php echo htmlspecialchars($m['menu_name_ko'] ?? 'Menu #'.$m['id']); ?></p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    매장 <?php echo number_format($price); ?>원 · 픽업 <?php echo number_format($price_pickup); ?>원 · 배달 <?php echo number_format($price_delivery); ?>원
                                    <?php if ($ov): ?><span class="text-sky-600 font-bold ml-1">(가맹점 설정)</span><?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold px-2 py-1 rounded-lg <?php echo $is_avail ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'; ?>">
                                <?php echo $is_avail ? '판매중' : '품절'; ?>
                            </span>
                            <a href="store_menu_edit.php?id=<?php echo (int)$m['id']; ?>" class="bg-slate-800 text-white px-4 py-2 rounded-xl font-bold text-sm hover:bg-slate-700">가격/판매여부</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>
