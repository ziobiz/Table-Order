<?php
// admin_store_info.php - 매장 정보 및 정책 설정
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

$store_id = (int)($_SESSION['store_id'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE stores SET address=?, tel=?, use_review=?, point_policy=?, point_rate=?, point_payer=? WHERE id=?");
    $stmt->execute([
        $_POST['address'], $_POST['tel'], $_POST['use_review'], 
        $_POST['point_policy'], $_POST['point_rate'], $_POST['point_payer'], $store_id
    ]);
    $admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['store_id'] ?? 0);
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? $_SESSION['name'] ?? ('id_' . $admin_id);
    log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_store_info', 'update', 'store', (string)$store_id, "매장 설정 변경: ID {$store_id}");
    echo "<script>alert('설정이 저장되었습니다.'); location.reload();</script>";
}

$store = $pdo->query("SELECT * FROM stores WHERE id = $store_id")->fetch();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>가맹점 설정 - Alrira Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 p-10 font-sans">
    <form method="POST" class="max-w-[96rem] mx-auto bg-white rounded-[2.5rem] shadow-xl overflow-hidden">
        <div class="p-8 bg-slate-900 text-white flex justify-between items-center">
            <h2 class="text-2xl font-black italic text-sky-400 uppercase">Store Policy Setup</h2>
        </div>

        <div class="p-10 space-y-8">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">매장 주소</label>
                    <input type="text" name="address" value="<?php echo $store['address']; ?>" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none focus:ring-2 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">전화번호</label>
                    <input type="text" name="tel" value="<?php echo $store['tel']; ?>" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none focus:ring-2 focus:ring-sky-500">
                </div>
            </div>

            <hr class="border-slate-100">

            <div class="grid grid-cols-2 gap-10">
                <div class="space-y-4">
                    <h4 class="font-black text-slate-800 italic uppercase tracking-tighter">Review System</h4>
                    <div class="flex items-center space-x-4 p-5 bg-sky-50 rounded-3xl border border-sky-100">
                        <span class="text-sm font-bold text-sky-700">리뷰 기능 활성화</span>
                        <input type="checkbox" name="use_review" value="1" <?php echo $store['use_review'] ? 'checked' : ''; ?> class="w-6 h-6 accent-sky-500">
                    </div>
                </div>

                <div class="space-y-4">
                    <h4 class="font-black text-slate-800 italic uppercase tracking-tighter">Point Policy</h4>
                    <select name="point_policy" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none">
                        <option value="NONE" <?php echo $store['point_policy']=='NONE'?'selected':''; ?>>사용 안 함</option>
                        <option value="SINGLE" <?php echo $store['point_policy']=='SINGLE'?'selected':''; ?>>단일 매장 전용 포인트</option>
                        <option value="MULTI" <?php echo $store['point_policy']=='MULTI'?'selected':''; ?>>브랜드 통합 멀티 포인트</option>
                    </select>
                </div>
            </div>

            <div class="p-8 bg-slate-50 rounded-[2rem] grid grid-cols-2 gap-8">
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">적립률 (%)</label>
                    <input type="number" step="0.01" name="point_rate" value="<?php echo $store['point_rate']; ?>" class="w-full p-4 bg-white rounded-2xl border-0 ring-1 ring-slate-200 font-black text-sky-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2">포인트 비용 주체</label>
                    <select name="point_payer" class="w-full p-4 bg-white rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none">
                        <option value="STORE" <?php echo $store['point_payer']=='STORE'?'selected':''; ?>>가맹점 책임 부담</option>
                        <option value="HEAD" <?php echo $store['point_payer']=='HEAD'?'selected':''; ?>>본사 비용 부담</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="w-full p-6 bg-sky-500 rounded-[2rem] font-black text-white text-xl shadow-xl shadow-sky-100 hover:bg-sky-600 transition-all uppercase tracking-widest">
                Save Settings
            </button>
        </div>
    </form>
</body>
</html>