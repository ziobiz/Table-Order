<?php
// admin_staff_manage.php - 사용자 관리 (본사 및 가맹점 통합)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안] 관리자 권한 체크 (SUPERADMIN 또는 STORE_OWNER만 접근 가능)
if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], ['SUPERADMIN', 'STORE_OWNER'])) {
    header("Location: login.php"); exit;
}

$my_role = $_SESSION['admin_role'];
$my_store_id = $_SESSION['store_id'];

// 1. 신규 사용자 등록 로직 (본사/가맹점 공통)
if (isset($_POST['add_staff'])) {
    $uname = $_POST['username'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    // 기본 패스워드 설정: 아이디+1! (예: admin1!)
    $temp_pw = password_hash($uname . "1!", PASSWORD_DEFAULT);
    
    // 본사 관리자일 경우 선택한 매장 ID를 사용, 점주일 경우 본인의 매장 ID 고정
    $target_store = ($my_role == 'SUPERADMIN') ? $_POST['store_id'] : $my_store_id;

    try {
        $stmt = $pdo->prepare("INSERT INTO staff_members (store_id, username, password, name, email, role, force_password_change, is_active) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
        $stmt->execute([$target_store, $uname, $temp_pw, $name, $email, $role]);
        $admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['store_id'] ?? 0);
        $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? $_SESSION['name'] ?? ('id_' . $admin_id);
        log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_staff_manage', 'create', 'staff', (string)$pdo->lastInsertId(), "직원 등록: {$name} ({$uname})");
        echo "<script>alert('등록 성공! 초기 비밀번호는 [ $uname" . "1! ] 입니다.'); location.href='admin_staff_manage.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>alert('등록 실패: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 2. 비번 초기화 승인 로직
if (isset($_POST['approve_reset'])) {
    $uid = $_POST['user_id'];
    $uname = $_POST['username'];
    $temp_pw_hash = password_hash($uname . "1!", PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("UPDATE staff_members SET password = ?, reset_requested = 0, force_password_change = 1, reset_approved_at = ? WHERE id = ?");
    $stmt->execute([$temp_pw_hash, $now, $uid]);
    $admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['store_id'] ?? 0);
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? $_SESSION['name'] ?? ('id_' . $admin_id);
    log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_staff_manage', 'approve', 'staff', (string)$uid, "비밀번호 초기화 승인: {$uname} (ID {$uid})");
    echo "<script>alert('승인 완료! [ $uname" . "1! ] 로 초기화되었습니다.'); location.href='admin_staff_manage.php';</script>";
    exit;
}

// 목록 조회를 위한 데이터 가져오기
$sql = ($my_role == 'SUPERADMIN') 
    ? "SELECT s.*, st.store_name FROM staff_members s LEFT JOIN stores st ON s.store_id = st.id ORDER BY s.reset_requested DESC, s.id DESC"
    : "SELECT s.*, st.store_name FROM staff_members s LEFT JOIN stores st ON s.store_id = st.id WHERE s.store_id = $my_store_id ORDER BY s.reset_requested DESC, s.id DESC";
$staffs = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>User Control Center - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-50 p-6 md:p-10">
    <div class="max-w-[96rem] mx-auto space-y-8">
        <header class="flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-black italic text-slate-900 tracking-tighter uppercase">Account Management</h1>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-1 italic">본사 및 가맹점 통합 관리 시스템</p>
            </div>
            <a href="admin_hq_report.php" class="text-xs font-black text-slate-400 border-b-2 border-slate-200 hover:text-slate-900 transition-all">Back to Analytics</a>
        </header>

        <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden">
            <div class="p-5 bg-slate-900 text-sky-400 text-[10px] font-black uppercase tracking-widest">Register New Staff / Admin</div>
            <form method="POST" class="p-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 items-end">
                
                <?php if($my_role == 'SUPERADMIN'): ?>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Store Assignment</label>
                    <select name="store_id" class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                        <option value="0">HQ (본사)</option>
                        <?php 
                        $stmt_stores = $pdo->query("SELECT id, store_name FROM stores ORDER BY store_name ASC");
                        while($st = $stmt_stores->fetch()) {
                            echo "<option value='{$st['id']}'>{$st['store_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <?php endif; ?>

                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Username (ID)</label>
                    <input type="text" name="username" required class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold outline-none focus:ring-2 focus:ring-sky-500">
                </div>

                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Full Name</label>
                    <input type="text" name="name" required class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold outline-none focus:ring-2 focus:ring-sky-500">
                </div>

                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Email</label>
                    <input type="email" name="email" required class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold outline-none focus:ring-2 focus:ring-sky-500">
                </div>

                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Role Permission</label>
                    <select name="role" class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                        <option value="STAFF">STAFF (가맹점 알바)</option>
                        <option value="DELIVERY">DELIVERY (배달원)</option>
                        <option value="STORE_OWNER">STORE_OWNER (가맹점주)</option>
                        <?php if($my_role == 'SUPERADMIN'): ?>
                        <option value="SUPERADMIN">SUPERADMIN (본사관리자)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <button type="submit" name="add_staff" class="bg-sky-500 text-white p-3 rounded-xl font-black text-[11px] uppercase tracking-widest hover:bg-sky-600 shadow-lg shadow-sky-200 transition-all">Create Account</button>
            </form>
        </div>

        <div class="bg-white rounded-[3rem] shadow-xl overflow-hidden border border-slate-100">
            <table class="w-full text-left">
                <thead class="bg-slate-900 text-sky-400 text-[10px] font-black uppercase tracking-widest">
                    <tr>
                        <th class="p-8">Identification / Role</th>
                        <th class="p-8">Security Status</th>
                        <th class="p-8 text-right">Approval Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach($staffs as $s): ?>
                    <tr class="<?php echo $s['reset_requested'] ? 'bg-amber-50' : 'hover:bg-slate-50/50'; ?> transition-all">
                        <td class="p-8">
                            <span class="block text-[10px] font-black text-slate-400 uppercase mb-1">
                                <?php echo ($s['store_id'] == 0) ? 'HQ (Main)' : $s['store_name']; ?> 
                                | <span class="text-sky-500"><?php echo $s['role']; ?></span>
                            </span>
                            <span class="text-lg font-black text-slate-800 tracking-tight"><?php echo $s['name']; ?> (<?php echo $s['username']; ?>)</span>
                            <span class="block text-xs text-slate-400 mt-1 font-bold"><?php echo $s['email']; ?></span>
                        </td>
                        <td class="p-8">
                            <?php if($s['reset_requested']): ?>
                                <span class="bg-amber-500 text-white px-3 py-1 rounded-full text-[9px] font-black animate-pulse">RESET PENDING</span>
                            <?php elseif($s['force_password_change']): ?>
                                <span class="bg-sky-100 text-sky-600 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-tighter">Password Change Required</span>
                            <?php else: ?>
                                <span class="bg-emerald-100 text-emerald-600 px-3 py-1 rounded-full text-[9px] font-black uppercase">Active Account</span>
                            <?php endif; ?>
                            <?php if($s['reset_approved_at']): ?>
                                <p class="text-[9px] text-slate-300 mt-2 font-bold italic uppercase">Approved At: <?php echo $s['reset_approved_at']; ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="p-8 text-right">
                            <?php if($s['reset_requested']): ?>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                <input type="hidden" name="username" value="<?php echo $s['username']; ?>">
                                <button type="submit" name="approve_reset" class="bg-amber-500 text-white px-5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-600 shadow-lg shadow-amber-100">Confirm Reset</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>