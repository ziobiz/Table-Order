<?php
// admin_category_manage.php - 카테고리 다국어 일괄 관리
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// 1. 권한 체크
$admin_role = $_SESSION['admin_role'] ?? 'PARTTIME';
if (!in_array($admin_role, ['SUPERADMIN', 'MANAGER'])) {
    echo "<script>alert('권한이 없습니다.'); location.href='admin_menu_list.php';</script>"; exit;
}

$langs = [
    'ko' => '한국어', 'en' => 'English', 'id' => 'Indonesian', 
    'th' => 'Thai', 'ja' => 'Japanese', 'vi' => 'Vietnamese'
];

// 2. 카테고리 추가 및 번역 저장
if (isset($_POST['add_category'])) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO categories (category_name, sort_order) VALUES (?, ?)");
        $stmt->execute([$_POST['name_ko'], (int)$_POST['sort_order']]);
        $new_id = $pdo->lastInsertId();

        foreach ($langs as $code => $label) {
            $name = !empty($_POST['name_'.$code]) ? $_POST['name_'.$code] : $_POST['name_ko'];
            $pdo->prepare("INSERT INTO category_translations (category_id, lang_code, category_name) VALUES (?, ?, ?)")
                ->execute([$new_id, $code, $name]);
        }
        $pdo->commit();
        echo "<script>alert('새 카테고리가 등록되었습니다.'); location.href='admin_category_manage.php';</script>"; exit;
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

// 3. 카테고리 수정 (다국어 명칭 업데이트)
if (isset($_POST['update_category'])) {
    try {
        $pdo->beginTransaction();
        $cat_id = $_POST['category_id'];
        $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?")->execute([(int)$_POST['sort_order'], $cat_id]);

        foreach ($langs as $code => $label) {
            $name = $_POST['name_'.$code];
            $pdo->prepare("INSERT INTO category_translations (category_id, lang_code, category_name) 
                           VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE category_name = ?")
                ->execute([$cat_id, $code, $name, $name]);
        }
        $pdo->commit();
        echo "<script>alert('수정되었습니다.'); location.href='admin_category_manage.php';</script>"; exit;
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

// 4. 데이터 로드
$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>카테고리 관리 - Alrira Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <nav class="bg-slate-900 text-white p-5 shadow-xl">
        <div class="max-w-[96rem] mx-auto flex justify-between items-center">
            <h1 class="text-xl font-black text-sky-400 italic cursor-pointer" onclick="location.href='admin_menu_list.php'">ALRIRA ADMIN</h1>
            <div class="space-x-4">
                <a href="admin_menu_list.php" class="text-xs font-bold text-slate-400 hover:text-white">메뉴 관리</a>
                <a href="admin_category_manage.php" class="text-xs font-bold text-sky-400 border-b border-sky-400 pb-1">카테고리 관리</a>
                <a href="admin_order_dashboard.php" class="text-xs font-bold text-slate-400 hover:text-white">주문 대시보드</a>
            </div>
        </div>
    </nav>

    <main class="max-w-[96rem] mx-auto p-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-10">
            
            <div class="lg:col-span-1">
                <form method="POST" class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100 sticky top-10">
                    <h3 class="text-lg font-black text-slate-800 mb-6 flex items-center">
                        <span class="w-2 h-6 bg-sky-500 rounded-full mr-3"></span>신규 카테고리
                    </h3>
                    <div class="space-y-4">
                        <?php foreach($langs as $code => $label): ?>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1"><?php echo $label; ?></label>
                            <input type="text" name="name_<?php echo $code; ?>" placeholder="<?php echo $label; ?> 이름" <?php echo $code=='ko'?'required':''; ?> 
                                   class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-sky-500 outline-none font-bold text-sm">
                        </div>
                        <?php endforeach; ?>
                        <div class="pt-2">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1">정렬 순서 (숫자)</label>
                            <input type="number" name="sort_order" value="1" class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 font-black text-center">
                        </div>
                        <button type="submit" name="add_category" class="w-full p-4 bg-sky-500 text-white rounded-2xl font-black hover:bg-sky-600 transition shadow-lg shadow-sky-100 uppercase tracking-widest mt-4">Add Category</button>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-3 space-y-6">
                <h3 class="text-xl font-black text-slate-800 flex items-center">
                    등록된 카테고리 리스트 <span class="ml-3 text-xs bg-slate-200 px-2 py-1 rounded-md text-slate-500"><?php echo count($categories); ?></span>
                </h3>

                <?php foreach($categories as $c): 
                    $trans_stmt = $pdo->prepare("SELECT lang_code, category_name FROM category_translations WHERE category_id = ?");
                    $trans_stmt->execute([$c['id']]);
                    $t_data = $trans_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                ?>
                <form method="POST" class="bg-white p-8 rounded-[2.5rem] shadow-md border border-slate-50 relative group transition-all hover:border-sky-200">
                    <input type="hidden" name="category_id" value="<?php echo $c['id']; ?>">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <?php foreach($langs as $code => $label): ?>
                        <div>
                            <label class="text-[9px] font-black text-sky-400 uppercase"><?php echo $label; ?></label>
                            <input type="text" name="name_<?php echo $code; ?>" value="<?php echo htmlspecialchars($t_data[$code] ?? ''); ?>" 
                                   class="w-full p-2.5 bg-slate-50 rounded-lg border-0 ring-1 ring-slate-100 focus:ring-1 focus:ring-sky-500 text-xs font-bold outline-none">
                        </div>
                        <?php endforeach; ?>
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase">순서</label>
                            <input type="number" name="sort_order" value="<?php echo $c['sort_order']; ?>" 
                                   class="w-full p-2.5 bg-slate-100 rounded-lg border-0 text-xs font-black text-center outline-none">
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between items-center border-t border-slate-50 pt-5">
                        <span class="text-[10px] text-slate-300 font-black uppercase">Category ID #<?php echo $c['id']; ?></span>
                        <div class="flex space-x-2">
                            <button type="submit" name="update_category" class="px-6 py-2 bg-slate-800 text-white text-[11px] font-black rounded-lg hover:bg-sky-500 transition-colors uppercase">Save Changes</button>
                            <button type="button" onclick="if(confirm('카테고리를 삭제하시겠습니까?')) location.href='?delete=<?php echo $c['id']; ?>'" 
                                    class="px-6 py-2 bg-rose-50 text-rose-400 text-[11px] font-black rounded-lg hover:bg-rose-500 hover:text-white transition-colors uppercase">Delete</button>
                        </div>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</body>
</html>