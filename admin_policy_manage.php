<?php
// admin_policy_manage.php - 6대 정책 마스터 관리 (템플릿 생성)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안] 본사 관리자 권한 체크
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('본사 관리자 전용 페이지입니다.'); location.href='login.php';</script>"; exit;
}

// --------------------------------------------------------------------------------
// [데이터 저장 로직]
// --------------------------------------------------------------------------------
if (isset($_POST['save_policy'])) {
    try {
        $pdo->beginTransaction();

        $params = [
            $_POST['policy_name'], isset($_POST['is_default']) ? 1 : 0,
            // 1. Single
            isset($_POST['use_single']) ? 1 : 0, (int)$_POST['single_threshold'], (int)$_POST['single_amt'],
            // 2. Multi
            isset($_POST['use_multi']) ? 1 : 0,
            // 3. Global
            isset($_POST['use_global']) ? 1 : 0,
            // 4. ME
            isset($_POST['use_me_coupon']) ? 1 : 0, (int)$_POST['me_coupon_threshold'], $_POST['me_coupon_currency'], (int)$_POST['me_coupon_target'], $_POST['me_coupon_reward'], isset($_POST['me_use_same_day']) ? 1 : 0,
            // 5. AD
            isset($_POST['use_ad_coupon']) ? 1 : 0, (int)$_POST['ad_coupon_threshold'], $_POST['ad_coupon_currency'], $_POST['ad_coupon_type'], isset($_POST['ad_use_same_day']) ? 1 : 0,
            // 6. WE
            isset($_POST['use_we_coupon']) ? 1 : 0, (int)$_POST['we_coupon_threshold'], $_POST['we_coupon_currency'], (int)$_POST['we_exchange_ratio'], (int)$_POST['we_exchange_fee'], isset($_POST['use_we_buy']) ? 1 : 0, isset($_POST['we_use_same_day']) ? 1 : 0
        ];

        // 기본 정책 설정 시 다른 정책들의 기본 설정 해제
        if (isset($_POST['is_default'])) {
            $pdo->query("UPDATE policy_templates SET is_default = 0");
        }

        if (!empty($_POST['policy_id'])) {
            // 수정
            $sql = "UPDATE policy_templates SET 
                    policy_name=?, is_default=?,
                    use_single=?, single_threshold=?, single_amt=?,
                    use_multi=?, use_global=?,
                    use_me_coupon=?, me_coupon_threshold=?, me_coupon_currency=?, me_coupon_target=?, me_coupon_reward=?, me_use_same_day=?,
                    use_ad_coupon=?, ad_coupon_threshold=?, ad_coupon_currency=?, ad_coupon_type=?, ad_use_same_day=?,
                    use_we_coupon=?, we_coupon_threshold=?, we_coupon_currency=?, we_exchange_ratio=?, we_exchange_fee=?, use_we_buy=?, we_use_same_day=?
                    WHERE id=?";
            $params[] = $_POST['policy_id'];
            $pdo->prepare($sql)->execute($params);
        } else {
            // 등록
            $sql = "INSERT INTO policy_templates 
                (policy_name, is_default, 
                 use_single, single_threshold, single_amt, 
                 use_multi, use_global, 
                 use_me_coupon, me_coupon_threshold, me_coupon_currency, me_coupon_target, me_coupon_reward, me_use_same_day,
                 use_ad_coupon, ad_coupon_threshold, ad_coupon_currency, ad_coupon_type, ad_use_same_day,
                 use_we_coupon, we_coupon_threshold, we_coupon_currency, we_exchange_ratio, we_exchange_fee, use_we_buy, we_use_same_day) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute($params);
        }
        
        $pdo->commit();
        echo "<script>alert('정책이 저장되었습니다.'); location.href='admin_policy_manage.php';</script>"; exit;
    } catch (Exception $e) { 
        if($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>alert('저장 실패: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 삭제
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM policy_templates WHERE id = ?")->execute([$_GET['delete']]);
    header("Location: admin_policy_manage.php"); exit;
}

// 목록 조회
try {
    $policies = $pdo->query("SELECT * FROM policy_templates ORDER BY is_default DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $policies = []; }

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = 'Policy Master';
    $admin_page_subtitle = '가맹점 6대 정책 템플릿 관리';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Policy Master - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }
        .policy-card { transition: all 0.3s ease; border: 1px solid #f1f5f9; }
        .policy-card:not(.disabled) { border-color: #0ea5e9; background-color: #fff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .policy-card.disabled { background-color: #f8fafc !important; border-color: #e2e8f0 !important; opacity: 0.7; }
        .policy-card.disabled input:not(.policy-toggle), .policy-card.disabled select, .policy-card.disabled p, .policy-card.disabled label { 
            opacity: 0.4; pointer-events: none; filter: grayscale(100%); 
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-10">
        <header class="flex justify-between items-end">
            <div>
                <h1 class="text-4xl font-black italic text-violet-900 uppercase tracking-tighter">Policy Master</h1>
                <p class="text-slate-500 text-xs font-bold mt-2 uppercase">가맹점 6대 정책 템플릿 관리</p>
            </div>
            <div class="flex space-x-2">
                <button onclick="location.href='admin_dashboard.php'" class="bg-white border-2 border-slate-200 px-6 py-3 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all">Back to Dashboard</button>
            </div>
        </header>
<?php endif; ?>

        <div class="max-w-[96rem] space-y-10">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-slate-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-black text-slate-800 uppercase">Saved Policies</h3>
                        <button onclick="resetForm()" class="text-[10px] bg-slate-900 text-white px-3 py-1 rounded-full hover:bg-slate-700">NEW</button>
                    </div>
                    <div class="space-y-3 h-[600px] overflow-y-auto pr-1 custom-scroll">
                        <?php foreach($policies as $p): ?>
                        <div onclick='editPolicy(<?php echo json_encode($p); ?>)' class="group p-4 rounded-2xl border border-slate-100 hover:border-violet-200 hover:bg-violet-50 cursor-pointer transition-all relative">
                            <?php if($p['is_default']): ?><span class="absolute top-2 right-2 text-[8px] bg-violet-100 text-violet-600 px-2 py-0.5 rounded-full font-bold">DEFAULT</span><?php endif; ?>
                            <h4 class="text-xs font-bold text-slate-700 group-hover:text-violet-700 mb-1"><?php echo htmlspecialchars($p['policy_name']); ?></h4>
                            <div class="flex gap-1">
                                <?php if($p['use_single']): ?><div class="w-1.5 h-1.5 bg-sky-400 rounded-full"></div><?php endif; ?>
                                <?php if($p['use_multi']): ?><div class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></div><?php endif; ?>
                                <?php if($p['use_global']): ?><div class="w-1.5 h-1.5 bg-amber-400 rounded-full"></div><?php endif; ?>
                                <?php if($p['use_me_coupon']): ?><div class="w-1.5 h-1.5 bg-rose-400 rounded-full"></div><?php endif; ?>
                                <?php if($p['use_ad_coupon']): ?><div class="w-1.5 h-1.5 bg-sky-500 rounded-full"></div><?php endif; ?>
                                <?php if($p['use_we_coupon']): ?><div class="w-1.5 h-1.5 bg-violet-500 rounded-full"></div><?php endif; ?>
                            </div>
                            <a href="?delete=<?php echo $p['id']; ?>" onclick="event.stopPropagation(); return confirm('삭제하시겠습니까?')" class="absolute bottom-2 right-3 text-[9px] text-slate-300 hover:text-rose-500">Del</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4">
                <form id="policy-form" method="POST" class="bg-white p-8 rounded-[3rem] shadow-xl border border-slate-100">
                    <input type="hidden" name="policy_id" id="policy_id">
                    
                    <div class="flex justify-between items-center mb-8 border-b pb-6">
                        <div class="w-2/3">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Policy Name (Template Name)</label>
                            <input type="text" name="policy_name" id="policy_name" placeholder="ex) 2025 Korea Standard Policy" required class="w-full text-lg font-bold text-slate-800 placeholder-slate-300 border-none focus:ring-0 p-0">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_default" id="is_default" class="w-4 h-4 text-violet-600 rounded border-gray-300 focus:ring-violet-500">
                            <label for="is_default" class="text-xs font-bold text-slate-600">Set as Default</label>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div id="card_single" class="policy-card p-6 rounded-3xl h-48 flex flex-col justify-between">
                                <div class="flex justify-between items-center mb-4"><span class="text-[10px] font-black uppercase">1. Single Point</span><input type="checkbox" name="use_single" id="use_single" class="policy-toggle" onchange="updateUI()"></div>
                                <div class="flex items-center gap-1"><input type="number" name="single_threshold" id="single_threshold" value="1000" class="w-16 p-2 text-[10px] rounded-lg border border-slate-200 font-bold text-center"> <span class="text-[9px] font-bold">당</span> <input type="number" name="single_amt" id="single_amt" value="50" class="w-12 p-2 text-[10px] rounded-lg border border-slate-200 font-bold text-center text-sky-500"> <span class="text-[9px] font-bold">P</span></div>
                            </div>
                            <div id="card_multi" class="policy-card p-6 rounded-3xl h-48 flex flex-col justify-between">
                                <div class="flex justify-between items-center mb-2"><span class="text-[10px] font-black uppercase">2. Multi Point</span><input type="checkbox" name="use_multi" id="use_multi" class="policy-toggle" onchange="updateUI()"></div>
                                <p class="text-[9px] text-slate-400 mt-2">* 지역 그룹은 가맹점 등록 시 개별 설정합니다.</p>
                            </div>
                            <div id="card_global" class="policy-card p-6 rounded-3xl h-48 flex flex-col justify-between">
                                <div class="flex justify-between items-center mb-4"><span class="text-[10px] font-black uppercase">3. Global Point</span><input type="checkbox" name="use_global" id="use_global" class="policy-toggle" onchange="updateUI()"></div>
                                <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest italic text-center mt-4">U-Point 통합 변환</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div id="card_me_coupon" class="policy-card p-6 rounded-3xl h-64 flex flex-col justify-between">
                                <div class="flex justify-between items-center">
                                    <div class="flex flex-col"><span class="text-[12px] font-black uppercase text-rose-500">4. ME-Coupon</span><span class="text-[8px] text-slate-400 font-bold">가맹점 전용</span></div>
                                    <input type="checkbox" name="use_me_coupon" id="use_me_coupon" class="policy-toggle" onchange="updateUI()">
                                </div>
                                <div class="space-y-2 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <div class="flex justify-between items-center mb-1"><span class="text-[9px] font-bold text-slate-500">지급 기준</span></div>
                                    <div class="flex items-center justify-end gap-1">
                                        <select name="me_coupon_currency" id="me_coupon_currency" class="text-[10px] font-black text-slate-500 bg-white p-1 rounded border outline-none"><option value="KRW">KRW</option><option value="USD">USD</option><option value="CNY">CNY</option><option value="JPY">JPY</option><option value="THB">THB</option><option value="IDR">IDR</option></select>
                                        <input type="number" name="me_coupon_threshold" id="me_coupon_threshold" value="5000" class="w-20 p-1 text-[10px] text-right rounded border font-bold"> <span class="text-[10px] font-bold text-slate-600">CP</span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 justify-between"><span class="text-[9px] font-bold text-slate-500">목표</span><div class="flex items-center gap-1"><input type="number" name="me_coupon_target" id="me_coupon_target" value="10" class="w-12 p-1 text-[10px] rounded border text-center font-bold"> <span class="text-[9px] font-bold text-slate-600">CP</span></div></div>
                                    <div class="flex items-center gap-2 justify-between"><span class="text-[9px] font-bold text-slate-500">보상</span><input type="text" name="me_coupon_reward" id="me_coupon_reward" placeholder="내용" class="w-24 p-1 text-[10px] rounded border font-bold text-right"></div>
                                    <div class="flex items-center gap-2 pt-1 border-t border-slate-100"><input type="checkbox" name="me_use_same_day" id="me_use_same_day"><span class="text-[9px] font-bold text-emerald-600">Permission D-day</span></div>
                                </div>
                            </div>

                            <div id="card_ad_coupon" class="policy-card p-6 rounded-3xl h-64 flex flex-col justify-between">
                                <div class="flex justify-between items-start">
                                    <div class="flex flex-col"><span class="text-[12px] font-black uppercase text-sky-600">5. AD-Coupon</span><span class="text-[8px] text-slate-400 font-bold">선불/연합형</span></div>
                                    <input type="checkbox" name="use_ad_coupon" id="use_ad_coupon" class="policy-toggle" onchange="updateUI()">
                                </div>
                                <div class="flex gap-2 text-[9px] font-bold bg-slate-50 p-2 rounded-lg justify-center">
                                    <label class="flex items-center gap-1 cursor-pointer"><input type="radio" name="ad_coupon_type" value="PRE" checked class="accent-sky-500"> <span>Prepaid</span></label>
                                    <label class="flex items-center gap-1 cursor-pointer"><input type="radio" name="ad_coupon_type" value="POST" class="accent-rose-500"> <span>Postpaid</span></label>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <div class="flex justify-between items-center mb-1"><span class="text-[9px] font-bold text-slate-500">지급 기준</span></div>
                                    <div class="flex items-center justify-end gap-1">
                                        <select name="ad_coupon_currency" id="ad_coupon_currency" class="text-[10px] font-black text-slate-500 bg-white p-1 rounded border outline-none"><option value="KRW">KRW</option><option value="USD">USD</option><option value="CNY">CNY</option><option value="JPY">JPY</option><option value="THB">THB</option><option value="IDR">IDR</option></select>
                                        <input type="number" name="ad_coupon_threshold" id="ad_coupon_threshold" value="10000" class="w-20 p-1 text-[10px] text-right rounded border font-bold"> <span class="text-[10px] font-bold text-slate-600">CP</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 pt-1 border-t border-slate-100"><input type="checkbox" name="ad_use_same_day" id="ad_use_same_day"><span class="text-[9px] font-bold text-emerald-600">Permission D-day</span></div>
                            </div>

                            <div id="card_we_coupon" class="policy-card p-6 rounded-3xl h-64 flex flex-col justify-between">
                                <div class="flex justify-between items-center">
                                    <div class="flex flex-col"><span class="text-[12px] font-black uppercase text-violet-600">6. WE-Coupon</span><span class="text-[8px] text-slate-400 font-bold">통합/교환형</span></div>
                                    <input type="checkbox" name="use_we_coupon" id="use_we_coupon" class="policy-toggle" onchange="updateUI()">
                                </div>
                                <div class="space-y-2 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <div class="flex justify-between items-center mb-1"><span class="text-[9px] font-bold text-slate-500">지급 기준</span></div>
                                    <div class="flex items-center justify-end gap-1">
                                        <select name="we_coupon_currency" id="we_coupon_currency" class="text-[10px] font-black text-slate-500 bg-white p-1 rounded border outline-none"><option value="KRW">KRW</option><option value="USD">USD</option><option value="CNY">CNY</option><option value="JPY">JPY</option><option value="THB">THB</option><option value="IDR">IDR</option></select>
                                        <input type="number" name="we_coupon_threshold" id="we_coupon_threshold" value="20000" class="w-20 p-1 text-[10px] text-right rounded border font-bold"> <span class="text-[10px] font-bold text-slate-600">CP</span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between"><span class="text-[9px] font-bold text-slate-500">교환비(AD→WE)</span><div class="flex items-center gap-1"><input type="number" name="we_exchange_ratio" id="we_exchange_ratio" value="9" class="w-8 p-1 text-[9px] text-center rounded border text-rose-500 font-bold"> <span class="text-[9px] font-bold text-slate-600">CP</span></div></div>
                                    <div class="flex items-center justify-between"><span class="text-[9px] font-bold text-slate-500">수수료(ME차감)</span><div class="flex items-center gap-1"><input type="number" name="we_exchange_fee" id="we_exchange_fee" value="1" class="w-8 p-1 text-[9px] text-center rounded border text-slate-500 font-bold"> <span class="text-[9px] font-bold text-slate-600">CP</span></div></div>
                                    <div class="flex items-center gap-2 pt-1 border-t border-slate-100 justify-between">
                                        <div class="flex items-center gap-1"><input type="checkbox" name="we_use_same_day" id="we_use_same_day"><span class="text-[9px] font-bold text-emerald-600">Permission D-day</span></div>
                                        <div class="flex items-center gap-1"><input type="checkbox" name="use_we_buy" id="use_we_buy"><span class="text-[8px] text-slate-400">직접 구매</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-8 flex justify-end">
                        <button type="submit" name="save_policy" class="bg-violet-600 text-white px-12 py-4 rounded-[2rem] font-black uppercase tracking-widest shadow-xl hover:bg-violet-700 transition-all active:scale-95">Save Policy Template</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
    </div>
    <script>
    function updateUI() {
        const policies = ['single', 'multi', 'global', 'me_coupon', 'ad_coupon', 'we_coupon'];
        policies.forEach(p => {
            const cb = document.getElementById('use_' + p);
            const card = document.getElementById('card_' + p);
            if(cb && card) {
                if(cb.checked) card.classList.remove('disabled');
                else card.classList.add('disabled');
            }
        });
    }

    function editPolicy(data) {
        document.getElementById('policy_id').value = data.id;
        document.getElementById('policy_name').value = data.policy_name;
        document.getElementById('is_default').checked = (data.is_default == 1);

        // Point
        document.getElementById('use_single').checked = (data.use_single == 1);
        document.getElementById('single_threshold').value = data.single_threshold;
        document.getElementById('single_amt').value = data.single_amt;
        document.getElementById('use_multi').checked = (data.use_multi == 1);
        document.getElementById('use_global').checked = (data.use_global == 1);

        // ME
        document.getElementById('use_me_coupon').checked = (data.use_me_coupon == 1);
        document.getElementById('me_coupon_threshold').value = data.me_coupon_threshold;
        document.getElementById('me_coupon_currency').value = data.me_coupon_currency;
        document.getElementById('me_coupon_target').value = data.me_coupon_target;
        document.getElementById('me_coupon_reward').value = data.me_coupon_reward;
        document.getElementById('me_use_same_day').checked = (data.me_use_same_day == 1);

        // AD
        document.getElementById('use_ad_coupon').checked = (data.use_ad_coupon == 1);
        document.getElementById('ad_coupon_threshold').value = data.ad_coupon_threshold;
        document.getElementById('ad_coupon_currency').value = data.ad_coupon_currency;
        const adTypeRadio = document.querySelector(`input[name="ad_coupon_type"][value="${data.ad_coupon_type}"]`);
        if(adTypeRadio) adTypeRadio.checked = true;
        document.getElementById('ad_use_same_day').checked = (data.ad_use_same_day == 1);

        // WE
        document.getElementById('use_we_coupon').checked = (data.use_we_coupon == 1);
        document.getElementById('we_coupon_threshold').value = data.we_coupon_threshold;
        document.getElementById('we_coupon_currency').value = data.we_coupon_currency;
        document.getElementById('we_exchange_ratio').value = data.we_exchange_ratio;
        document.getElementById('we_exchange_fee').value = data.we_exchange_fee;
        document.getElementById('use_we_buy').checked = (data.use_we_buy == 1);
        document.getElementById('we_use_same_day').checked = (data.we_use_same_day == 1);

        updateUI();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('policy-form').reset();
        document.getElementById('policy_id').value = '';
        updateUI();
    }

    window.onload = updateUI;
    </script>
</body>
</html>
<?php endif; ?>