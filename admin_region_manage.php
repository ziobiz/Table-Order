<?php
// admin_region_manage.php - 멀티 포인트용 지역 그룹 관리 페이지
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안] 본사 관리자 권한 체크
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('접근 권한이 없습니다.'); location.href='login.php';</script>"; exit;
}

// 1. 그룹 추가 로직
if (isset($_POST['add_region'])) {
    $name = trim($_POST['group_name']);
    $desc = trim($_POST['description']);
    
    if ($name) {
        try {
            $stmt = $pdo->prepare("INSERT INTO region_groups (group_name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            $new_id = $pdo->lastInsertId();
            $admin_id = (int)($_SESSION['admin_id'] ?? 0);
            $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ('id_' . $admin_id);
            log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_region_manage', 'create', 'region', (string)$new_id, "지역 그룹 등록: {$name}");
            echo "<script>location.href='admin_region_manage.php';</script>"; exit;
        } catch (PDOException $e) {
            echo "<script>alert('등록 실패: " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('그룹명을 입력해 주세요.');</script>";
    }
}

// 2. 그룹 삭제 로직
if (isset($_GET['delete'])) {
    try {
        $del_id = (int)$_GET['delete'];
        $pdo->prepare("DELETE FROM region_groups WHERE id = ?")->execute([$del_id]);
        $admin_id = (int)($_SESSION['admin_id'] ?? 0);
        $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ('id_' . $admin_id);
        log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_region_manage', 'delete', 'region', (string)$del_id, "지역 그룹 삭제: ID {$del_id}");
        echo "<script>location.href='admin_region_manage.php';</script>"; exit;
    } catch (PDOException $e) {
        echo "<script>alert('삭제 실패: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 3. 그룹 목록 조회
try {
    $regions = $pdo->query("SELECT * FROM region_groups ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $regions = [];
    echo "<script>console.log('DB 테이블이 아직 없습니다.');</script>";
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? ('id_' . $admin_id);
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$admin_login_at = (int)($_SESSION['admin_login_at'] ?? time());
$header_locale = 'ko';
$admin_page_title = 'Region Groups';
$admin_page_subtitle = '멀티 포인트 공유 그룹 관리';
include 'admin_card_header.php';
?>

        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 p-8">
            <form method="POST" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="flex-1 w-full">
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Group Name</label>
                    <input type="text" name="group_name" placeholder="예: SEOUL-Gangnam" required class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold focus:ring-2 focus:ring-sky-500 outline-none transition-all">
                </div>
                <div class="flex-[2] w-full">
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Description</label>
                    <input type="text" name="description" placeholder="설명 (옵션)" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none transition-all">
                </div>
                <button type="submit" name="add_region" class="w-full md:w-auto bg-slate-900 text-white px-8 py-4 rounded-2xl font-black uppercase tracking-widest hover:bg-sky-600 transition-all shadow-lg shadow-slate-200">
                    Add Group
                </button>
            </form>
        </div>

        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase">ID</th>
                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase">Group Name</th>
                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase">Description</th>
                        <th class="p-6 text-right text-[10px] font-black text-slate-400 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if(empty($regions)): ?>
                        <tr><td colspan="4" class="p-10 text-center text-xs font-bold text-slate-300">등록된 지역 그룹이 없습니다.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach($regions as $r): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-6 font-bold text-slate-500">#<?php echo $r['id']; ?></td>
                        <td class="p-6 font-black text-lg text-slate-800 uppercase tracking-tight"><?php echo htmlspecialchars($r['group_name']); ?></td>
                        <td class="p-6 text-xs font-bold text-slate-400"><?php echo htmlspecialchars($r['description']); ?></td>
                        <td class="p-6 text-right">
                            <a href="?delete=<?php echo $r['id']; ?>" onclick="return confirm('이 그룹을 삭제하시겠습니까?')" class="text-rose-500 text-[10px] font-black uppercase hover:underline">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

<?php include 'admin_card_footer.php'; ?>