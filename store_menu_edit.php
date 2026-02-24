<?php
// store_menu_edit.php - 가맹점(파트너): 본사 포맷 메뉴의 가격/판매여부만 오버라이드
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = (int)$_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? '';

$menu_id = (int)($_GET['id'] ?? 0);
if ($menu_id <= 0) {
    echo "<script>alert('메뉴를 선택해 주세요.'); location.href='store_menu_list.php';</script>"; exit;
}

// 본사가 할당한 포맷 ID
$menu_format_id = 1;
try {
    $stmt = $pdo->prepare("SELECT menu_format_id FROM stores WHERE id = ?");
    $stmt->execute([$store_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['menu_format_id'])) $menu_format_id = (int)$row['menu_format_id'];
} catch (PDOException $e) {}

// 해당 포맷의 메뉴만 수정 가능 (본사 제공 메뉴)
$menu = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ? AND menu_format_id = ?");
    $stmt->execute([$menu_id, $menu_format_id]);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ? AND store_id = ?");
    $stmt->execute([$menu_id, $store_id]);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$menu) {
    echo "<script>alert('메뉴를 찾을 수 없거나 수정 권한이 없습니다.'); location.href='store_menu_list.php';</script>"; exit;
}

// 기존 오버라이드
$ov = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM store_menu_overrides WHERE store_id = ? AND menu_id = ?");
    $stmt->execute([$store_id, $menu_id]);
    $ov = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$menu_name_ko = '';
$stmt = $pdo->prepare("SELECT menu_name FROM menu_translations WHERE menu_id = ? AND lang_code = 'ko' LIMIT 1");
$stmt->execute([$menu_id]);
$menu_name_ko = $stmt->fetchColumn() ?: 'Menu #'.$menu_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $price_override = trim($_POST['price_override'] ?? '') !== '' ? (int)$_POST['price_override'] : null;
    $price_pickup_override = trim($_POST['price_pickup_override'] ?? '') !== '' ? (int)$_POST['price_pickup_override'] : null;
    $price_delivery_override = trim($_POST['price_delivery_override'] ?? '') !== '' ? (int)$_POST['price_delivery_override'] : null;
    $is_available_override = isset($_POST['is_available_override']) ? (int)$_POST['is_available_override'] : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO store_menu_overrides (store_id, menu_id, price_override, price_pickup_override, price_delivery_override, is_available_override) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE price_override=VALUES(price_override), price_pickup_override=VALUES(price_pickup_override), price_delivery_override=VALUES(price_delivery_override), is_available_override=VALUES(is_available_override)");
        $stmt->execute([$store_id, $menu_id, $price_override, $price_pickup_override, $price_delivery_override, $is_available_override]);
        header("Location: store_menu_list.php?updated=1"); exit;
    } catch (PDOException $e) {
        $err = $e->getMessage();
    }
}

$price_def = (int)$menu['price'];
$price_pickup_def = (int)$menu['price_pickup'];
$price_delivery_def = (int)$menu['price_delivery'];
$is_avail_def = (int)$menu['is_available'];
$price_ov = $ov ? $ov['price_override'] : null;
$price_pickup_ov = $ov ? $ov['price_pickup_override'] : null;
$price_delivery_ov = $ov ? $ov['price_delivery_override'] : null;
$is_avail_ov = $ov ? $ov['is_available_override'] : null;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>가격/판매여부 - <?php echo htmlspecialchars($store_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-[96rem] mx-auto">
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-black text-slate-800 uppercase italic">가격·판매여부 설정</h1>
            <a href="store_menu_list.php" class="text-sm font-bold text-slate-500 hover:text-slate-700">← 목록</a>
        </header>
        <?php if (!empty($err)): ?><p class="mb-4 text-rose-600 font-bold text-sm"><?php echo htmlspecialchars($err); ?></p><?php endif; ?>

        <div class="bg-white rounded-2xl shadow border border-slate-100 p-6 mb-6">
            <p class="font-black text-slate-800 text-lg"><?php echo htmlspecialchars($menu_name_ko); ?></p>
            <p class="text-xs text-slate-500 mt-1">본사 제공 메뉴. 가맹점에서 가격·판매여부만 설정할 수 있습니다.</p>
        </div>

        <form method="POST" class="bg-white rounded-2xl shadow border border-slate-100 p-8 space-y-6">
            <div>
                <label class="block font-bold text-slate-700 mb-2">매장 가격 (원) · 비우면 본사 기본가 사용</label>
                <input type="number" name="price_override" value="<?php echo $price_ov !== null ? (int)$price_ov : ''; ?>" min="0" placeholder="<?php echo $price_def; ?> (기본)" class="w-full p-3 rounded-xl border border-slate-200">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-2">픽업 가격 (원)</label>
                <input type="number" name="price_pickup_override" value="<?php echo $price_pickup_ov !== null ? (int)$price_pickup_ov : ''; ?>" min="0" placeholder="<?php echo $price_pickup_def; ?> (기본)" class="w-full p-3 rounded-xl border border-slate-200">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-2">배달 가격 (원)</label>
                <input type="number" name="price_delivery_override" value="<?php echo $price_delivery_ov !== null ? (int)$price_delivery_ov : ''; ?>" min="0" placeholder="<?php echo $price_delivery_def; ?> (기본)" class="w-full p-3 rounded-xl border border-slate-200">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-2">판매 여부 (가맹점 설정 시 우선)</label>
                <select name="is_available_override" class="w-full p-3 rounded-xl border border-slate-200">
                    <option value="">본사 기본값 사용 (<?php echo $is_avail_def ? '판매중' : '품절'; ?>)</option>
                    <option value="1" <?php echo $is_avail_ov === 1 ? 'selected' : ''; ?>>판매중</option>
                    <option value="0" <?php echo $is_avail_ov === 0 ? 'selected' : ''; ?>>품절</option>
                </select>
            </div>
            <button type="submit" class="w-full py-4 bg-slate-800 text-white rounded-2xl font-black hover:bg-sky-600">저장</button>
        </form>
    </div>
</body>
</html>
