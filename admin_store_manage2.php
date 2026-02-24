<?php
// admin_store_manage.php - 가맹점 등록 및 포인트 정책 관리 (본사 전용)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안] 본사 관리자(SUPERADMIN)만 접근 가능
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'SUPERADMIN') {
    echo "<script>alert('본사 관리자 권한이 필요합니다.'); location.href='login.php';</script>";
    exit;
}

// 1. 가맹점 신규 등록 처리
if (isset($_POST['add_store'])) {
    $name = $_POST['store_name'];
    $address = $_POST['address'];
    $tel = $_POST['tel'];
    $p_rate = $_POST['point_rate'];     // 적립률 (예: 5.0)
    $p_policy = $_POST['point_policy']; // SINGLE 또는 MULTI

    try {
        $stmt = $pdo->prepare("INSERT INTO stores (store_name, address, tel, point_rate, point_policy) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $address, $tel, $p_rate, $p_policy]);
        echo "<script>alert('신규 가맹점이 등록되었습니다.'); location.href='admin_store_manage.php';</script>";
        exit;
    } catch (PDOException $e) {
        // 컬럼이 없는 경우를 대비해 에러 메시지 출력
        echo "<script>alert('오류 발생: " . addslashes($e->getMessage()) . "\\nDB에 point_rate, point_policy 컬럼이 있는지 확인하세요.');</script>";
    }
}

// 2. 가맹점 삭제 처리
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM stores WHERE id = ?")->execute([$id]);
    echo "<script>alert('삭제되었습니다.'); location.href='admin_store_manage.php';</script>";
    exit;
}

// 가맹점 리스트 조회
$stores = $pdo->query("SELECT * FROM stores ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store & Point Policy - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-10">
        
        <header class="flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-black italic text-slate-900 uppercase tracking-tighter">Store Management</h1>
                <p class="text-slate-400 text-[10px] font-black uppercase mt-2 tracking-widest">가맹점 관리 및 포인트 운영 정책 수립</p>
            </div>
            <div class="flex space-x-4">
                <a href="admin_hq_report.php" class="text-xs font-black text-slate-400 hover:text-slate-900 transition uppercase border-b-2 border-transparent hover:border-slate-900">Analytics</a>
                <a href="admin_staff_manage.php" class="text-xs font-black text-slate-400 hover:text-slate-900 transition uppercase border-b-2 border-transparent hover:border-slate-900">Staffs</a>
            </div>
        </header>

        <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden">
            <div class="p-6 bg-slate-900 text-sky-400 text-[10px] font-black uppercase tracking-widest">Register New Store & Set Policy</div>
            <form method="POST" class="p-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 items-end">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Store Name</label>
                    <input type="text" name="store_name" required placeholder="매장명" class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Address</label>
                    <input type="text" name="address" required placeholder="매장 주소" class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Point Rate (%)</label>
                    <input type="number" name="point_rate" step="0.1" value="5.0" class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none text-center">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Point Policy</label>
                    <select name="point_policy" class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold outline-none focus:ring-2 focus:ring-sky-500">
                        <option value="MULTI">MULTI (브랜드 통합)</option>
                        <option value="SINGLE">SINGLE (개별 매장)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Contact</label>
                    <input type="text" name="tel" required placeholder="010-0000-0000" class="w-full p-3 bg-slate-50 rounded-xl border-0 ring-1 ring-slate-200 text-xs font-bold">
                </div>
                <button type="submit" name="add_store" class="bg-sky-500 text-white p-4 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-sky-600 transition-all shadow-lg shadow-sky-100">Add Store</button>
            </form>
        </div>

        <div class="bg-white rounded-[3rem] shadow-xl border border-slate-100 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-900 text-sky-400 text-[10px] font-black uppercase tracking-widest">
                    <tr>
                        <th class="p-8">Store Details</th>
                        <th class="p-8">Policy & Rate</th>
                        <th class="p-8">Management</th>
                        <th class="p-8 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if(empty($stores)): ?>
                        <tr><td colspan="4" class="p-20 text-center text-slate-300 font-bold uppercase tracking-widest text-xs">No registered stores found.</td></tr>
                    <?php endif; ?>
                    <?php foreach($stores as $s): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-8">
                            <span class="block text-lg font-black text-slate-800 tracking-tight"><?php echo htmlspecialchars($s['store_name']); ?></span>
                            <span class="text-xs text-slate-400 font-bold uppercase"><?php echo htmlspecialchars($s['address']); ?></span>
                        </td>
                        <td class="p-8">
                            <?php if($s['point_policy'] === 'MULTI'): ?>
                                <span class="bg-emerald-100 text-emerald-600 px-3 py-1 rounded-full text-[9px] font-black uppercase">Multi (Shared)</span>
                            <?php else: ?>
                                <span class="bg-slate-100 text-slate-500 px-3 py-1 rounded-full text-[9px] font-black uppercase">Single (Store Only)</span>
                            <?php endif; ?>
                            <span class="ml-3 text-sm font-black text-slate-700"><?php echo $s['point_rate']; ?>%</span>
                        </td>
                        <td class="p-8 text-xs font-bold text-slate-400 uppercase">
                            ID: #<?php echo $s['id']; ?><br>
                            TEL: <?php echo htmlspecialchars($s['tel']); ?>
                        </td>
                        <td class="p-8 text-right">
                            <a href="?delete=<?php echo $s['id']; ?>" onclick="return confirm('이 매장을 삭제하시겠습니까? 관련 모든 주문 데이터가 영향을 받을 수 있습니다.')" 
                               class="bg-rose-50 text-rose-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-rose-500 hover:text-white transition-all">Remove</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>