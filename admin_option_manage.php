<?php
// admin_option_manage.php - 옵션 관리 및 가격 자동 입력 기능 포함
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

$admin_role = $_SESSION['admin_role'] ?? 'PARTTIME';
if (!in_array($admin_role, ['SUPERADMIN', 'MANAGER'])) {
    echo "<script>alert('권한이 없습니다.'); location.href='admin_menu_list.php';</script>"; exit;
}

// [1] 옵션 그룹 추가 로직
if (isset($_POST['add_group'])) {
    $stmt = $pdo->prepare("INSERT INTO option_groups (group_name_ko, is_required, min_select, max_select) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['group_name'], $_POST['is_required'] ?? 0, $_POST['min_select'], $_POST['max_select']]);
    header("Location: admin_option_manage.php"); exit;
}

// [2] 세부 옵션 항목 추가 로직
if (isset($_POST['add_item'])) {
    $stmt = $pdo->prepare("INSERT INTO option_items (group_id, item_name_ko, price_dinein, price_pickup, price_delivery) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['group_id'], $_POST['item_name'], floor($_POST['p_d']), floor($_POST['p_p']), floor($_POST['p_v'])]);
    header("Location: admin_option_manage.php"); exit;
}

// 데이터 불러오기
$groups = $pdo->query("SELECT * FROM option_groups ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>옵션 및 토핑 관리 - Alrira Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', 'Pretendard', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <nav class="bg-slate-900 text-white p-4 shadow-xl border-b border-sky-500/30">
        <div class="max-w-[96rem] mx-auto flex justify-between items-center">
            <h1 class="text-xl font-black text-sky-400 italic cursor-pointer tracking-tighter uppercase" onclick="location.href='admin_menu_list.php'">Alrira Admin</h1>
            <div class="flex items-center space-x-6">
                <a href="admin_menu_list.php" class="text-xs font-bold text-slate-400 hover:text-white transition uppercase">Menu</a>
                <a href="admin_option_manage.php" class="text-xs font-bold text-sky-400 border-b-2 border-sky-400 pb-1 uppercase">Options</a>
            </div>
        </div>
    </nav>

    <main class="max-w-[96rem] mx-auto p-6 mt-6">
        <div class="flex justify-between items-end mb-8">
            <div>
                <h2 class="text-3xl font-black text-slate-800 tracking-tighter uppercase">Option Management</h2>
                <p class="text-slate-500 text-sm mt-1 font-bold">매장가 입력 시 픽업/배달가가 자동으로 입력됩니다.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <div class="lg:col-span-1">
                <form method="POST" class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100 sticky top-6">
                    <h3 class="text-lg font-black text-slate-800 mb-6 flex items-center">
                        <span class="w-2 h-6 bg-sky-500 rounded-full mr-3"></span>Create Group
                    </h3>
                    <div class="space-y-5">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 tracking-widest">Group Name</label>
                            <input type="text" name="group_name" placeholder="예: 토핑 추가, 맵기 단계" required class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 focus:ring-2 focus:ring-sky-500 outline-none font-bold">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 tracking-widest">Min Select</label>
                                <input type="number" name="min_select" value="0" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 text-center font-black">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 tracking-widest">Max Select</label>
                                <input type="number" name="max_select" value="1" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 text-center font-black">
                            </div>
                        </div>
                        <div class="flex items-center space-x-3 p-4 bg-slate-50 rounded-2xl">
                            <input type="checkbox" name="is_required" value="1" id="req" class="w-5 h-5 accent-sky-500 rounded">
                            <label for="req" class="text-sm font-bold text-slate-600 uppercase tracking-tight">Required Selection</label>
                        </div>
                        <button type="submit" name="add_group" class="w-full p-5 bg-slate-800 text-white rounded-2xl font-black hover:bg-sky-500 transition-all shadow-lg shadow-slate-200 uppercase tracking-tighter">Save Group</button>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-3 space-y-6">
                <?php foreach($groups as $g): ?>
                <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden">
                    <div class="p-7 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <div>
                            <span class="text-[9px] font-black text-sky-500 uppercase tracking-[0.2em]">Group ID #<?php echo $g['id']; ?></span>
                            <h3 class="text-xl font-black text-slate-800 tracking-tight"><?php echo htmlspecialchars($g['group_name_ko']); ?></h3>
                        </div>
                        <div class="px-4 py-1.5 bg-white border border-slate-200 rounded-full text-[10px] font-black text-slate-500 uppercase tracking-widest">
                            <?php echo $g['is_required'] ? 'Required' : 'Optional'; ?> (<?php echo $g['min_select']; ?>~<?php echo $g['max_select']; ?>)
                        </div>
                    </div>
                    
                    <div class="p-8">
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-8 p-5 bg-sky-50/50 rounded-3xl border border-sky-100 items-end">
                            <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                            <div class="md:col-span-1">
                                <label class="text-[9px] font-black text-sky-600 uppercase mb-1 block ml-1">Item Name</label>
                                <input type="text" name="item_name" placeholder="토핑명" required class="w-full p-3.5 bg-white rounded-xl border-0 ring-1 ring-sky-100 text-sm font-bold outline-none focus:ring-2 focus:ring-sky-500">
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-sky-600 uppercase mb-1 block ml-1">Dine-in (Base)</label>
                                <input type="number" name="p_d" id="p_d_<?php echo $g['id']; ?>" oninput="syncPrices(this, <?php echo $g['id']; ?>)" placeholder="매장가" class="w-full p-3.5 bg-white rounded-xl border-0 ring-1 ring-sky-100 text-sm font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase mb-1 block ml-1">Pickup</label>
                                <input type="number" name="p_p" id="p_p_<?php echo $g['id']; ?>" placeholder="픽업가" class="w-full p-3.5 bg-white rounded-xl border-0 ring-1 ring-sky-100 text-sm font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase mb-1 block ml-1">Delivery</label>
                                <input type="number" name="p_v" id="p_v_<?php echo $g['id']; ?>" placeholder="배달가" class="w-full p-3.5 bg-white rounded-xl border-0 ring-1 ring-sky-100 text-sm font-bold focus:ring-2 focus:ring-sky-500 outline-none">
                            </div>
                            <button type="submit" name="add_item" class="bg-sky-500 text-white p-3.5 rounded-xl font-black text-xs hover:bg-sky-600 transition shadow-lg shadow-sky-100 uppercase tracking-tighter">Add Item</button>
                        </form>

                        <div class="space-y-3">
                            <?php 
                            $items = $pdo->prepare("SELECT * FROM option_items WHERE group_id = ? ORDER BY id ASC");
                            $items->execute([$g['id']]);
                            $rows = $items->fetchAll();
                            if(empty($rows)) echo '<p class="text-center py-6 text-slate-300 font-bold text-xs italic uppercase">No items added yet</p>';
                            foreach($rows as $it):
                            ?>
                            <div class="flex items-center justify-between p-5 bg-slate-50 rounded-2xl hover:bg-white hover:shadow-md transition-all group border border-transparent hover:border-sky-100">
                                <span class="font-black text-slate-700 tracking-tight"><?php echo htmlspecialchars($it['item_name_ko']); ?></span>
                                <div class="flex items-center space-x-6 text-[11px] font-black">
                                    <div class="text-right"><span class="text-slate-400 uppercase text-[9px] block">Dine-in</span><span class="text-slate-800"><?php echo number_format($it['price_dinein']); ?></span></div>
                                    <div class="text-right"><span class="text-slate-400 uppercase text-[9px] block">Pickup</span><span class="text-emerald-500"><?php echo number_format($it['price_pickup']); ?></span></div>
                                    <div class="text-right"><span class="text-slate-400 uppercase text-[9px] block">Delivery</span><span class="text-sky-500"><?php echo number_format($it['price_delivery']); ?></span></div>
                                    <button class="ml-4 p-2 text-rose-300 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        // 사장님 요청: 매장가 입력 시 픽업/배달 자동 미러링
        function syncPrices(input, groupId) {
            const val = input.value;
            const pickupInput = document.getElementById('p_p_' + groupId);
            const deliveryInput = document.getElementById('p_v_' + groupId);
            
            // 사용자가 수동으로 픽업/배달가를 건드리기 전까지만 동기화하고 싶을 경우 로직을 더 짤 수 있지만,
            // 기본적으로 매장가 변경 시 함께 변하도록 설정합니다.
            pickupInput.value = val;
            deliveryInput.value = val;
        }
    </script>
</body>
</html>