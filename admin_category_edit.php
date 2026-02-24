<?php
// admin_category_edit.php - 본사: 포맷별 카테고리 추가/수정
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('본사 관리자 전용입니다.'); location.href='login.php';</script>"; exit;
}

$format_id = (int)($_GET['format_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
if ($format_id <= 0) {
    echo "<script>alert('포맷을 선택해 주세요.'); location.href='admin_menu_format_list.php';</script>"; exit;
}

$row = null;
if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND menu_format_id = ?");
        $stmt->execute([$id, $format_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$format_name = '';
try {
    $stmt = $pdo->prepare("SELECT name FROM menu_formats WHERE id = ?");
    $stmt->execute([$format_id]);
    $format_name = $stmt->fetchColumn() ?: '';
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['category_name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    if ($name === '') {
        echo "<script>alert('카테고리명을 입력해 주세요.');</script>";
    } else {
        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE categories SET category_name=?, sort_order=? WHERE id=? AND menu_format_id=?")
                    ->execute([$name, $sort_order, $id, $format_id]);
                echo "<script>alert('수정되었습니다.'); location.href='admin_menu_by_format.php?format_id=$format_id';</script>"; exit;
            } else {
                $pdo->prepare("INSERT INTO categories (menu_format_id, category_name, sort_order) VALUES (?,?,?)")
                    ->execute([$format_id, $name, $sort_order]);
                $new_id = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO category_translations (category_id, lang_code, category_name) VALUES (?, 'ko', ?)")
                    ->execute([$new_id, $name]);
                echo "<script>alert('추가되었습니다.'); location.href='admin_menu_by_format.php?format_id=$format_id';</script>"; exit;
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'menu_format_id') !== false) {
                $pdo->prepare("INSERT INTO categories (category_name, sort_order) VALUES (?,?)")->execute([$name, $sort_order]);
                $new_id = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO category_translations (category_id, lang_code, category_name) VALUES (?, 'ko', ?)")->execute([$new_id, $name]);
                $pdo->prepare("UPDATE categories SET menu_format_id=? WHERE id=?")->execute([$format_id, $new_id]);
                echo "<script>alert('추가되었습니다.'); location.href='admin_menu_by_format.php?format_id=$format_id';</script>"; exit;
            }
            echo "<script>alert('저장 실패: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id ? '카테고리 수정' : '카테고리 추가'; ?> - <?php echo htmlspecialchars($format_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-[96rem] mx-auto">
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-black text-slate-800 uppercase italic"><?php echo $id ? '카테고리 수정' : '카테고리 추가'; ?></h1>
            <a href="admin_menu_by_format.php?format_id=<?php echo $format_id; ?>" class="text-sm font-bold text-slate-500 hover:text-slate-700">← 포맷 메뉴</a>
        </header>

        <form method="POST" class="bg-white rounded-2xl shadow border border-slate-100 p-8 space-y-6">
            <div>
                <label class="block font-bold text-slate-700 mb-2">카테고리명 *</label>
                <input type="text" name="category_name" required value="<?php echo htmlspecialchars($row['category_name'] ?? ''); ?>" placeholder="예: 메인 메뉴" class="w-full p-4 rounded-xl border border-slate-200">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-2">정렬 순서 (작을수록 먼저)</label>
                <input type="number" name="sort_order" value="<?php echo (int)($row['sort_order'] ?? 0); ?>" class="w-32 p-3 rounded-xl border border-slate-200">
            </div>
            <button type="submit" class="w-full py-4 bg-sky-500 text-white rounded-2xl font-black hover:bg-sky-600">저장</button>
        </form>
    </div>
</body>
</html>
