<?php
// admin_menu_edit.php - 본사: 포맷별 메뉴 추가/수정 (이미지·다국어 포함)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

$admin_role = $_SESSION['admin_role'] ?? 'PARTTIME';
if (!in_array($admin_role, ['SUPERADMIN', 'MANAGER'])) {
    echo "<script>alert('권한이 없습니다.'); location.href='admin_menu_list.php';</script>"; exit;
}

$menu_id = (int)($_GET['id'] ?? 0);
$format_id = (int)($_GET['format_id'] ?? 0);
$is_new = ($menu_id === 0 && $format_id > 0);
$langs = ['ko' => '한국어', 'en' => 'English', 'id' => 'Indonesian', 'th' => 'Thai', 'ja' => 'Japanese', 'vi' => 'Vietnamese'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $image_url_val = null;
        if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] == 0) {
            $target_dir = "uploads/menus/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $file_ext = pathinfo($_FILES["menu_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid() . "." . $file_ext;
            $target_file = $target_dir . $new_filename;
            if (move_uploaded_file($_FILES["menu_image"]["tmp_name"], $target_file)) {
                $image_url_val = $target_file;
            }
        }

        $is_dinein = isset($_POST['is_dinein']) ? 1 : 0;
        $is_pickup = isset($_POST['is_pickup']) ? 1 : 0;
        $is_delivery = isset($_POST['is_delivery']) ? 1 : 0;
        $price = (int)$_POST['price'];
        $price_pickup = (int)$_POST['price_pickup'];
        $price_delivery = (int)$_POST['price_delivery'];
        $is_available = (int)($_POST['is_available'] ?? 0);
        $daily_limit = (int)($_POST['daily_limit'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $post_format_id = (int)($_POST['format_id'] ?? 0);

        if ($is_new && $post_format_id > 0 && $category_id > 0) {
            $ins = "INSERT INTO menus (menu_format_id, category_id, is_available, is_dinein, is_pickup, is_delivery, price, price_pickup, price_delivery, daily_limit, current_stock, image_url) VALUES (?,?,?,?,?,?,?,?,?,?,0," . ($image_url_val ? "?" : "NULL") . ")";
            $params = [$post_format_id, $category_id, $is_available, $is_dinein, $is_pickup, $is_delivery, $price, $price_pickup, $price_delivery, $daily_limit];
            if ($image_url_val) $params[] = $image_url_val;
            $pdo->prepare($ins)->execute($params);
            $menu_id = $pdo->lastInsertId();
        } else {
            $menu_id = (int)$_POST['menu_id'];
            if ($menu_id <= 0) throw new Exception('메뉴 ID가 필요합니다.');
            $up = "UPDATE menus SET price=?, price_pickup=?, price_delivery=?, is_dinein=?, is_pickup=?, is_delivery=?, is_available=?, daily_limit=?";
            $params = [$price, $price_pickup, $price_delivery, $is_dinein, $is_pickup, $is_delivery, $is_available, $daily_limit];
            if ($image_url_val !== null) { $up .= ", image_url=?"; $params[] = $image_url_val; }
            $up .= " WHERE id=?"; $params[] = $menu_id;
            $pdo->prepare($up)->execute($params);
        }

        $pdo->prepare("DELETE FROM menu_translations WHERE menu_id = ?")->execute([$menu_id]);
        foreach ($langs as $code => $name) {
            $m_name = !empty($_POST['name_'.$code]) ? $_POST['name_'.$code] : $_POST['name_ko'];
            $pdo->prepare("INSERT INTO menu_translations (menu_id, lang_code, menu_name, description) VALUES (?, ?, ?, ?)")
                ->execute([$menu_id, $code, $m_name, $_POST['desc_'.$code] ?? '']);
        }

        // 옵션 그룹 연결 (토핑·사이즈·굽기 등) — 피자/마라탕 등 옵션 메뉴용
        $pdo->prepare("DELETE FROM menu_option_groups WHERE menu_id = ?")->execute([$menu_id]);
        $option_group_ids = isset($_POST['option_groups']) && is_array($_POST['option_groups']) ? array_map('intval', $_POST['option_groups']) : [];
        $option_group_ids = array_filter($option_group_ids, function($id) { return $id > 0; });
        if (!empty($option_group_ids)) {
            $ins_mog = $pdo->prepare("INSERT INTO menu_option_groups (menu_id, group_id) VALUES (?, ?)");
            foreach ($option_group_ids as $gid) {
                $ins_mog->execute([$menu_id, $gid]);
            }
        }

        $pdo->commit();
        $back = $format_id ? "admin_menu_by_format.php?format_id=$format_id" : 'admin_menu_list.php';
        echo "<script>alert('저장되었습니다.'); location.href='$back';</script>"; exit;
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

$menu = null;
$trans = [];
if ($menu_id > 0) {
    $menu = $pdo->query("SELECT * FROM menus WHERE id=".(int)$menu_id)->fetch(PDO::FETCH_ASSOC);
    $trans = $pdo->query("SELECT * FROM menu_translations WHERE menu_id=".(int)$menu_id)->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);
}
if (!$menu) {
    $menu = ['id'=>0,'category_id'=>0,'price'=>0,'price_pickup'=>0,'price_delivery'=>0,'is_available'=>1,'is_dinein'=>1,'is_pickup'=>1,'is_delivery'=>1,'daily_limit'=>0,'image_url'=>null];
}

// 수정 시 format_id가 없으면 메뉴의 포맷 사용
if ($format_id <= 0 && !empty($menu['menu_format_id'])) {
    $format_id = (int)$menu['menu_format_id'];
}
$format_categories = [];
if ($format_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, category_name, sort_order FROM categories WHERE menu_format_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$format_id]);
        $format_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->query("SELECT id, category_name, sort_order FROM categories ORDER BY sort_order ASC");
        if ($stmt) $format_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// 이 포맷의 옵션 그룹 목록 (토핑·사이즈·굽기 등) — 피자/마라탕 등 옵션 메뉴용
$format_option_groups = [];
$linked_option_group_ids = [];
if ($format_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, group_name_ko, is_required, max_select FROM option_groups WHERE menu_format_id = ? ORDER BY id ASC");
        $stmt->execute([$format_id]);
        $format_option_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->query("SELECT id, group_name_ko, is_required, max_select FROM option_groups ORDER BY id ASC");
        if ($stmt) $format_option_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($menu_id > 0) {
        $stmt = $pdo->prepare("SELECT group_id FROM menu_option_groups WHERE menu_id = ?");
        $stmt->execute([$menu_id]);
        $linked_option_group_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
$back_url = $format_id ? "admin_menu_by_format.php?format_id=$format_id" : 'admin_menu_list.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>상품 상세 수정 - Alrira Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .price-card:has(input[type="checkbox"]:not(:checked)) { opacity: 0.4; filter: grayscale(1); }
        .lang-tab.active { border-bottom: 4px solid #0ea5e9; color: #0ea5e9; }
    </style>
</head>
<body class="bg-slate-50 p-4 md:p-8">
    <form method="POST" enctype="multipart/form-data" class="max-w-[96rem] mx-auto bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-slate-100">
        <input type="hidden" name="menu_id" value="<?php echo (int)$menu['id']; ?>">
        <input type="hidden" name="format_id" value="<?php echo (int)$format_id; ?>">
        <div class="p-8 bg-slate-900 text-white flex justify-between items-center">
            <h2 class="text-2xl font-black italic text-sky-400 uppercase"><?php echo $is_new ? '메뉴 추가' : 'Product Details & Image'; ?></h2>
            <button type="button" onclick="location.href='<?php echo htmlspecialchars($back_url); ?>'" class="text-slate-400 hover:text-white font-black italic text-xs uppercase">Back</button>
        </div>

        <?php if ($is_new && !empty($format_categories)): ?>
        <div class="p-8 border-b border-slate-100">
            <label class="block font-black text-slate-700 uppercase mb-2">카테고리 *</label>
            <select name="category_id" required class="w-full max-w-xs p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200">
                <option value="">선택</option>
                <?php foreach ($format_categories as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="p-10 space-y-10">
            <div class="flex flex-col md:flex-row gap-8 items-center bg-slate-50 p-8 rounded-[2rem] border-2 border-dashed border-slate-200">
                <div class="w-48 h-48 bg-white rounded-3xl shadow-inner overflow-hidden flex items-center justify-center border border-slate-100">
                    <?php if(!empty($menu['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($menu['image_url']); ?>" class="w-full h-full object-cover" alt="">
                    <?php else: ?>
                        <span class="text-slate-300 font-bold text-xs uppercase">No Image</span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow space-y-3">
                    <h4 class="font-black text-slate-800 uppercase tracking-tighter">Product Image</h4>
                    <p class="text-[10px] text-slate-400 font-bold leading-tight mb-4">권장 규격: 800x800px (JPG, PNG)<br>사진은 메뉴판의 첫인상을 결정합니다.</p>
                    <input type="file" name="menu_image" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-black file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 cursor-pointer">
                </div>
            </div>

            <div class="space-y-6">
                <div class="flex space-x-4 overflow-x-auto border-b border-slate-100">
                    <?php foreach ($langs as $code => $name): ?>
                    <button type="button" onclick="showLang('<?php echo $code; ?>')" id="tab-<?php echo $code; ?>" class="lang-tab pb-4 text-xs font-black text-slate-400 <?php echo $code=='ko'?'active':''; ?> uppercase tracking-widest"><?php echo $name; ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($langs as $code => $name): ?>
                <div id="content-<?php echo $code; ?>" class="lang-content space-y-4 <?php echo $code=='ko'?'':'hidden'; ?>">
                    <input type="text" name="name_<?php echo $code; ?>" value="<?php echo htmlspecialchars($trans[$code]['menu_name'] ?? ''); ?>" placeholder="상품명 (<?php echo $name; ?>)" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none focus:ring-2 focus:ring-sky-500">
                    <textarea name="desc_<?php echo $code; ?>" rows="2" placeholder="상품 설명 (<?php echo $name; ?>)" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-medium outline-none"><?php echo htmlspecialchars($trans[$code]['description'] ?? ''); ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php $fields = [['dinein','price','매장 식사'], ['pickup','price_pickup','매장 픽업'], ['delivery','price_delivery','배달 서비스']];
                foreach($fields as $f): ?>
                <div class="price-card p-6 rounded-3xl border-2 border-slate-100 shadow-sm">
                    <div class="flex justify-between items-center mb-4"><span class="font-black text-slate-700 text-sm uppercase tracking-tighter"><?php echo $f[2]; ?></span>
                    <input type="checkbox" name="is_<?php echo $f[0]; ?>" <?php echo !empty($menu['is_'.$f[0]]) ? 'checked' : ''; ?> class="w-5 h-5 accent-sky-500"></div>
                    <input type="number" name="<?php echo $f[1]; ?>" value="<?php echo (int)($menu[$f[1]] ?? 0); ?>" class="w-full bg-transparent text-2xl font-black text-sky-500 outline-none">
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($format_option_groups)): ?>
            <div class="p-8 rounded-[2.5rem] border-2 border-violet-100 bg-violet-50/50">
                <h4 class="font-black text-slate-800 uppercase tracking-tighter mb-2">옵션 그룹 (토핑·사이즈·굽기 등)</h4>
                <p class="text-[10px] text-slate-500 mb-4">이 메뉴에 적용할 옵션을 선택하세요. 피자면 「추가 토핑」「사이즈」, 스테이크면 「굽기 선택」「소스 선택」 등을 붙일 수 있습니다.</p>
                <div class="flex flex-wrap gap-4">
                    <?php foreach ($format_option_groups as $og): ?>
                    <label class="flex items-center gap-2 p-4 bg-white rounded-2xl border-2 border-slate-100 hover:border-violet-300 cursor-pointer">
                        <input type="checkbox" name="option_groups[]" value="<?php echo (int)$og['id']; ?>" <?php echo in_array($og['id'], $linked_option_group_ids) ? 'checked' : ''; ?> class="w-5 h-5 accent-violet-500">
                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars($og['group_name_ko']); ?></span>
                        <span class="text-[10px] text-slate-400">(<?php echo $og['is_required'] ? '필수' : '선택'; ?>, 최대 <?php echo (int)$og['max_select']; ?>개)</span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-sky-50 p-8 rounded-[2.5rem] flex flex-wrap justify-between items-center border border-sky-100">
                <div class="flex items-center space-x-4"><div class="bg-sky-500 p-3 rounded-2xl text-white"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg></div>
                <div><h4 class="font-black text-slate-800 uppercase tracking-tighter">Daily Limit</h4><p class="text-[10px] text-sky-600 font-bold uppercase tracking-widest">무제한 0 입력</p></div></div>
                <input type="number" name="daily_limit" value="<?php echo (int)$menu['daily_limit']; ?>" class="w-32 p-4 bg-white rounded-2xl border-0 ring-2 ring-sky-100 font-black text-center text-xl text-sky-600 outline-none">
            </div>

            <button type="submit" class="w-full p-6 bg-slate-900 text-white rounded-[2rem] font-black text-xl shadow-xl hover:bg-sky-500 transition-all uppercase tracking-widest"><?php echo $is_new ? '메뉴 추가' : 'Update All Settings'; ?></button>
        </div>
    </form>
    <script>function showLang(c){document.querySelectorAll('.lang-content').forEach(x=>x.classList.add('hidden'));document.querySelectorAll('.lang-tab').forEach(x=>x.classList.remove('active'));document.getElementById('content-'+c).classList.remove('hidden');document.getElementById('tab-'+c).classList.add('active');}</script>
</body>
</html>