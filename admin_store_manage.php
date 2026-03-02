<?php
// admin_store_manage.php - 가맹점 관리 + 정책 템플릿 연동 완성
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안] 본사 관리자 권한 체크
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('본사 관리자 전용 페이지입니다.'); location.href='login.php';</script>"; exit;
}
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? ('id_' . $admin_id);
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$admin_login_at = (int)($_SESSION['admin_login_at'] ?? time());
$header_locale = 'ko';

// --------------------------------------------------------------------------------
// [함수] 가맹점 코드 자동 생성 (Format: YY-CC-M-DD-Seq)
// --------------------------------------------------------------------------------
function generateStoreCode($pdo, $currency) {
    $year = date('y'); 
    $countryMap = ['KRW'=>'KR', 'USD'=>'US', 'CNY'=>'CN', 'JPY'=>'JP', 'THB'=>'TH', 'IDR'=>'ID'];
    $country = $countryMap[$currency] ?? 'GL';
    $monthChar = chr(64 + (int)date('n')); 
    $day = date('d');
    $prefix = $year . $country . $monthChar . $day;

    $stmt = $pdo->prepare("SELECT store_code FROM stores WHERE store_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '-%']);
    $lastCode = $stmt->fetchColumn();

    if ($lastCode) {
        $parts = explode('-', $lastCode);
        $seq = (int)end($parts) + 1;
    } else {
        $seq = 1;
    }
    return $prefix . '-' . $seq;
}

// --------------------------------------------------------------------------------
// [데이터 준비] 지역 그룹 & 정책 템플릿 가져오기
// --------------------------------------------------------------------------------
try {
    $raw_regions = $pdo->query("SELECT * FROM region_groups")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $raw_regions = []; }

// ★ 정책 템플릿 목록 가져오기 (연동용)
try {
    $policy_templates = $pdo->query("SELECT * FROM policy_templates ORDER BY is_default DESC, policy_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $policy_templates = []; }

// ★ 메뉴 포맷(업종) 목록 - 가맹점에 할당할 메뉴 솔루션
$menu_formats = [];
try {
    $menu_formats = $pdo->query("SELECT id, name, description FROM menu_formats WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $menu_formats = []; }

// 사용량 통계 (정렬용)
try {
    $usage_data = $pdo->query("SELECT region_id FROM stores")->fetchAll(PDO::FETCH_COLUMN);
    $usage_counts = [];
    foreach ($usage_data as $ids_str) {
        if (empty($ids_str)) continue;
        $ids = explode(',', $ids_str);
        foreach ($ids as $id) {
            $id = trim($id);
            if ($id) $usage_counts[$id] = ($usage_counts[$id] ?? 0) + 1;
        }
    }
} catch (Exception $e) { $usage_counts = []; }

// 언어 우선순위 함수
function getLanguagePriority($str) {
    $firstChar = mb_substr($str, 0, 1, "UTF-8");
    if (preg_match('/[0-9]/', $firstChar)) return 1;
    if (preg_match('/[a-zA-Z]/', $firstChar)) return 2;
    if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $firstChar)) return 3;
    if (preg_match('/[\x{3040}-\x{30FF}]/u', $firstChar)) return 4;
    if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $firstChar)) return 5;
    if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $firstChar)) return 6;
    if (preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu', $firstChar)) return 7;
    return 99;
}

// 지역 정렬 로직
usort($raw_regions, function($a, $b) use ($usage_counts) {
    $countA = $usage_counts[$a['id']] ?? 0;
    $countB = $usage_counts[$b['id']] ?? 0;
    if ($countA == $countB) return 0;
    return ($countA > $countB) ? -1 : 1;
});
$top_regions = array_slice($raw_regions, 0, 4);
$other_regions = array_slice($raw_regions, 4);
usort($other_regions, function($a, $b) {
    $priA = getLanguagePriority($a['group_name']);
    $priB = getLanguagePriority($b['group_name']);
    if ($priA == $priB) return strnatcasecmp($a['group_name'], $b['group_name']);
    return ($priA < $priB) ? -1 : 1;
});
$region_groups = array_merge($top_regions, $other_regions);
$rg_map = [];
foreach($raw_regions as $rg) $rg_map[$rg['id']] = $rg['group_name'];


// --------------------------------------------------------------------------------
// [데이터 저장 로직]
// --------------------------------------------------------------------------------
if (isset($_POST['save_store'])) {
    try {
        $pdo->beginTransaction();
        
        $biz_file_path = $_POST['old_biz_file'] ?? '';
        if (isset($_FILES['biz_file']) && $_FILES['biz_file']['error'] == 0) {
            $file = $_FILES['biz_file'];
            $max_size = 1 * 1024 * 1024;
            $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts)) throw new Exception("JPG, PNG, PDF만 가능");
            if ($file['size'] > $max_size) throw new Exception("용량 1MB 초과");
            $upload_dir = 'uploads/biz_docs/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $biz_file_path = $upload_dir . $filename;
            }
        }

        $region_ids_str = '';
        if (isset($_POST['region_ids']) && is_array($_POST['region_ids'])) {
            $region_ids_str = implode(',', $_POST['region_ids']);
        }
        $menu_format_id = (int)($_POST['menu_format_id'] ?? 1);
        if ($menu_format_id <= 0) $menu_format_id = 1;

        // 파라미터 매핑 (순서 중요) — menu_format_id 포함
        $common_params = [
            $menu_format_id, $_POST['store_name'], $_POST['owner_name'], $_POST['owner_tel'], $_POST['owner_email'],
            $_POST['manager_name'], $_POST['manager_tel'], $_POST['manager_email'],
            $_POST['biz_no'], $_POST['tax_no'], $_POST['tax_address'], $_POST['address'], $_POST['tel'], $_POST['currency'],
            
            isset($_POST['use_single']) ? 1 : 0, isset($_POST['use_multi']) ? 1 : 0, isset($_POST['use_global']) ? 1 : 0, 
            isset($_POST['use_me_coupon']) ? 1 : 0, isset($_POST['use_ad_coupon']) ? 1 : 0, isset($_POST['use_we_coupon']) ? 1 : 0,
            
            (int)$_POST['single_threshold'], (int)$_POST['single_amt'], $region_ids_str,
            
            (int)$_POST['me_coupon_threshold'], $_POST['me_coupon_currency'], (int)$_POST['me_coupon_target'], $_POST['me_coupon_reward'], isset($_POST['me_use_same_day']) ? 1 : 0,
            
            (int)$_POST['ad_coupon_threshold'], $_POST['ad_coupon_currency'], $_POST['ad_coupon_type'], isset($_POST['ad_use_same_day']) ? 1 : 0,
            
            (int)$_POST['we_coupon_threshold'], $_POST['we_coupon_currency'], (int)$_POST['we_exchange_ratio'], (int)$_POST['we_exchange_fee'], isset($_POST['use_we_buy']) ? 1 : 0, isset($_POST['we_use_same_day']) ? 1 : 0,
            
            isset($_POST['use_review']) ? 1 : 0, $biz_file_path
        ];

        if (!empty($_POST['store_id'])) {
            $sql = "UPDATE stores SET 
                    menu_format_id=?, store_name=?, owner_name=?, owner_tel=?, owner_email=?, manager_name=?, manager_tel=?, manager_email=?,
                    biz_no=?, tax_no=?, tax_address=?, address=?, tel=?, currency_code=?,
                    use_single=?, use_multi=?, use_global=?, use_me_coupon=?, use_ad_coupon=?, use_we_coupon=?,
                    single_threshold=?, single_amt=?, region_id=?,
                    me_coupon_threshold=?, me_coupon_currency=?, me_coupon_target=?, me_coupon_reward=?, me_use_same_day=?,
                    ad_coupon_threshold=?, ad_coupon_currency=?, ad_coupon_type=?, ad_use_same_day=?,
                    we_coupon_threshold=?, we_coupon_currency=?, we_exchange_ratio=?, we_exchange_fee=?, use_we_buy=?, we_use_same_day=?,
                    use_review=?, biz_file=?
                    WHERE id=?";
            $update_params = $common_params;
            $update_params[] = $_POST['store_id'];
            $pdo->prepare($sql)->execute($update_params);
            log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_store_manage', 'update', 'store', $_POST['store_id'], "가맹점 수정: " . ($_POST['store_name'] ?? '') . " (ID " . $_POST['store_id'] . ")");
        } else {
            $newStoreCode = generateStoreCode($pdo, $_POST['currency']);
            $sql = "INSERT INTO stores 
                (menu_format_id, store_name, owner_name, owner_tel, owner_email, manager_name, manager_tel, manager_email, 
                 biz_no, tax_no, tax_address, address, tel, currency_code, 
                 use_single, use_multi, use_global, use_me_coupon, use_ad_coupon, use_we_coupon, 
                 single_threshold, single_amt, region_id, 
                 me_coupon_threshold, me_coupon_currency, me_coupon_target, me_coupon_reward, me_use_same_day,
                 ad_coupon_threshold, ad_coupon_currency, ad_coupon_type, ad_use_same_day,
                 we_coupon_threshold, we_coupon_currency, we_exchange_ratio, we_exchange_fee, use_we_buy, we_use_same_day,
                 use_review, biz_file, store_code) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $insert_params = $common_params;
            $insert_params[] = $newStoreCode;
            $pdo->prepare($sql)->execute($insert_params);
            $new_id = (int)$pdo->lastInsertId();
            log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_store_manage', 'create', 'store', $new_id, "가맹점 등록: " . ($_POST['store_name'] ?? '') . " (ID {$new_id})");
        }
        
        $pdo->commit();
        echo "<script>alert('저장되었습니다.'); location.href='admin_store_manage.php';</script>"; exit;
    } catch (Exception $e) { 
        if($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>alert('저장 실패: " . addslashes($e->getMessage()) . "');</script>";
    }
}

if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $store_row = $pdo->prepare("SELECT store_name FROM stores WHERE id = ?");
    $store_row->execute([$del_id]);
    $store_name_del = $store_row->fetchColumn() ?: "ID {$del_id}";
    $pdo->prepare("DELETE FROM stores WHERE id = ?")->execute([$del_id]);
    log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_store_manage', 'delete', 'store', $del_id, "가맹점 삭제: {$store_name_del}");
    header("Location: admin_store_manage.php"); exit;
}

try {
    $stores = $pdo->query("SELECT * FROM stores ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $stores = []; }

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = '가맹점 관리';
    $admin_page_subtitle = '가맹점 등록, 정보 수정, 정책·메뉴 스킨 할당';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store Manage - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }
        .policy-card { transition: all 0.3s ease; border: 1px solid #f1f5f9; }
        .policy-card:not(.disabled) { border-color: #0ea5e9; background-color: #fff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .policy-card.disabled { background-color: #f8fafc !important; border-color: #e2e8f0 !important; opacity: 0.7; }
        .policy-card.disabled input:not(.policy-toggle), .policy-card.disabled select, .policy-card.disabled p, .policy-card.disabled div.multi-scroll-box, .policy-card.disabled label { opacity: 0.4; pointer-events: none; filter: grayscale(100%); }
        .multi-scroll-box { height: 130px !important; max-height: 130px !important; overflow-y: auto !important; border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 4px; background-color: #f8fafc; display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; align-content: start; }
        .multi-scroll-box::-webkit-scrollbar { width: 4px; }
        .multi-scroll-box::-webkit-scrollbar-track { background: #f1f5f9; }
        .multi-scroll-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .region-option input:checked + div { background-color: #10b981; color: white; border-color: #10b981; font-weight: 900; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2); }
        .region-option div { transition: all 0.2s; display: flex; align-items: center; justify-content: center; height: 34px; font-size: 10px; }
        .top-badge { position: absolute; top: -4px; right: -4px; width: 12px; height: 12px; background-color: #f43f5e; color: white; font-size: 7px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; z-index: 10; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-10">
        
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 shrink-0">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter">가맹점 관리</h1>
                <span class="text-xs text-slate-400 font-bold hidden sm:inline">가맹점 등록, 정보 수정, 정책·메뉴 스킨 할당</span>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-4 sm:gap-6 text-xs font-bold">
                <span class="text-slate-500 whitespace-nowrap">접속자 ID <?php echo htmlspecialchars($admin_username); ?> · <?php echo htmlspecialchars($admin_name); ?></span>
                <span id="current-datetime" class="text-slate-600 whitespace-nowrap">—</span>
                <span class="text-slate-500 whitespace-nowrap">머문 <span id="elapsed-time">0분 0초</span></span>
                <a href="admin_policy_manage.php" class="bg-white border-2 border-slate-200 px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all shrink-0">Policy Master</a>
                <a href="admin_region_manage.php" class="bg-white border-2 border-slate-200 px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all shrink-0">Region Groups</a>
                <button type="button" onclick="location.href='admin_dashboard.php'" class="bg-white border-2 border-slate-200 px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all shrink-0">Back to Dashboard</button>
                <a href="logout.php" class="text-rose-500 hover:underline whitespace-nowrap shrink-0">Logout</a>
            </div>
        </header>
<?php endif; ?>

        <div class="max-w-[96rem] space-y-10">
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="p-8">
            <h3 id="form-title" class="text-sm font-black text-slate-800 uppercase mb-6">Store & Policy Setup</h3>
            <form id="store-form" method="POST" enctype="multipart/form-data" onsubmit="return validateFile()" class="space-y-10">
                <input type="hidden" name="store_id" id="store_id">
                <input type="hidden" name="old_biz_file" id="old_biz_file">
                
                <div class="space-y-6">
                    <h4 class="text-xs font-black text-slate-800 uppercase border-l-4 border-sky-500 pl-3">1. 매장 및 사업자 정보</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">메뉴 스킨</label>
                            <select name="menu_format_id" id="menu_format_id" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none">
                                <?php foreach ($menu_formats as $mf): ?>
                                    <option value="<?php echo (int)$mf['id']; ?>"><?php echo htmlspecialchars($mf['name']); ?></option>
                                <?php endforeach; ?>
                                <?php if (empty($menu_formats)): ?>
                                    <option value="1">기본</option>
                                <?php endif; ?>
                            </select>
                            <p class="text-[9px] text-slate-400 mt-1 ml-1">가맹점 업종에 맞는 주문 메뉴 형식. 신규 접수 시 업체 유형에 따라 선택하여 제공합니다.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">매장명</label>
                            <input type="text" name="store_name" id="store_name" required class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">사업자 번호</label>
                            <input type="text" name="biz_no" id="biz_no" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">TAX 번호</label>
                            <input type="text" name="tax_no" id="tax_no" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-rose-500 uppercase mb-2 ml-1 italic">등록증(JPG/PNG)</label>
                            <input type="file" name="biz_file" id="biz_file" accept=".jpg,.jpeg,.png,.pdf" class="text-[10px] font-bold text-slate-500 mt-2">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">TAX 주소지</label>
                            <input type="text" name="tax_address" id="tax_address" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">매장 주소</label>
                            <input type="text" name="address" id="address" required class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold outline-none">
                        </div>
                    </div>
                </div>

                <div class="space-y-6 pt-10 border-t">
                    <h4 class="text-xs font-black text-slate-800 uppercase border-l-4 border-emerald-500 pl-3">2. 대표자 및 담당자</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="p-6 bg-slate-50 rounded-3xl space-y-4">
                            <label class="text-[10px] font-black text-emerald-600 uppercase">Representative</label>
                            <input type="text" name="owner_name" id="owner_name" placeholder="성함" class="w-full p-3 bg-white rounded-xl border-0 ring-1 ring-slate-200 font-bold">
                            <input type="text" name="owner_tel" id="owner_tel" placeholder="연락처" class="w-full p-3 bg-white rounded-xl border-0 ring-1 ring-slate-200 font-bold">
                            <input type="email" name="owner_email" id="owner_email" placeholder="이메일" class="w-full p-3 bg-white rounded-xl border-0 ring-1 ring-slate-200 font-bold">
                        </div>
                        <div class="p-6 bg-slate-50 rounded-3xl space-y-4">
                            <label class="text-[10px] font-black text-sky-600 uppercase">Manager</label>
                            <input type="text" name="manager_name" id="manager_name" placeholder="성함" class="w-full p-3 bg-white rounded-xl border-0 ring-1 ring-slate-200 font-bold">
                            <input type="text" name="manager_tel" id="manager_tel" placeholder="연락처" class="w-full p-3 bg-white rounded-xl border-0 ring-1 ring-slate-200 font-bold">
                            <input type="email" name="manager_email" id="manager_email" placeholder="이메일" class="w-full p-3 bg-white rounded-xl border-0 ring-1 ring-slate-200 font-bold">
                        </div>
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-400 uppercase ml-1">Base Setting</label>
                            <input type="text" name="tel" id="tel" placeholder="매장 대표번호" required class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-bold">
                            <select name="currency" id="currency" class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 font-black italic" onchange="syncCurrencies()">
                                <option value="KRW">KRW (₩)</option>
                                <option value="USD">USD ($)</option>
                                <option value="CNY">CNY (¥)</option>
                                <option value="JPY">JPY (¥)</option>
                                <option value="THB">THB (฿)</option>
                                <option value="IDR">IDR (Rp)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 pt-10 border-t relative">
                    
                    <div class="flex justify-between items-center mb-6 pl-3 border-l-4 border-amber-500">
                        <h4 class="text-xs font-black text-slate-800 uppercase">3. 포인트 및 쿠폰 정책 (6대 정책)</h4>
                        
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-bold text-slate-400">Load Template:</span>
                            <select id="policy_template_select" onchange="applyPolicyTemplate(this.value)" class="text-[10px] font-bold border border-slate-200 rounded-lg p-2 bg-slate-50 hover:bg-white transition-all cursor-pointer outline-none">
                                <option value="">-- Select Policy Template --</option>
                                <?php foreach($policy_templates as $pt): ?>
                                    <option value="<?php echo $pt['id']; ?>"><?php echo htmlspecialchars($pt['policy_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div id="card_single" class="policy-card p-6 rounded-3xl flex flex-col justify-between h-48">
                            <div class="flex justify-between items-center mb-4"><span class="text-[10px] font-black uppercase">1. Single Point</span><input type="checkbox" name="use_single" id="use_single" class="policy-toggle" checked onchange="updateUI()"></div>
                            <div class="flex items-center gap-1"><input type="number" name="single_threshold" id="single_threshold" value="1000" class="w-16 p-2 text-[10px] rounded-lg border border-slate-200 font-bold text-center"> <span class="text-[9px] font-bold">당</span> <input type="number" name="single_amt" id="single_amt" value="50" class="w-12 p-2 text-[10px] rounded-lg border border-slate-200 font-bold text-center text-sky-500"> <span class="text-[9px] font-bold">P</span></div>
                        </div>
                        
                        <div id="card_multi" class="policy-card p-6 rounded-3xl flex flex-col justify-between h-48">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-[10px] font-black uppercase toggle-label">2. Multi (복수 선택)</span>
                                <input type="checkbox" name="use_multi" id="use_multi" class="policy-toggle" onchange="updateUI()">
                            </div>
                            <div class="multi-scroll-box flex-1 w-full">
                                <?php if(empty($region_groups)): ?>
                                    <div class="flex items-center justify-center h-full text-center text-[10px] text-slate-400"><span>그룹 없음</span></div>
                                <?php else: ?>
                                    <?php $idx = 0; foreach($region_groups as $rg): $idx++; ?>
                                    <label class="region-option cursor-pointer group w-full relative">
                                        <input type="checkbox" name="region_ids[]" value="<?php echo $rg['id']; ?>" class="hidden">
                                        <div class="text-[9px] font-bold text-slate-500 bg-white border border-slate-200 rounded-lg text-center hover:border-emerald-300 break-words leading-tight px-1">
                                            <?php echo htmlspecialchars($rg['group_name']); ?>
                                        </div>
                                        <?php if($idx <= 4 && ($usage_counts[$rg['id']] ?? 0) > 0): ?><span class="top-badge">Top</span><?php endif; ?>
                                    </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div id="card_global" class="policy-card p-6 rounded-3xl text-center h-48 flex flex-col justify-center">
                            <div class="flex justify-between items-center mb-4 w-full"><span class="text-[10px] font-black uppercase">3. Global Point</span><input type="checkbox" name="use_global" id="use_global" class="policy-toggle" onchange="updateUI()"></div>
                            <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest italic">U-Point 통합 변환</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div id="card_me_coupon" class="policy-card p-6 rounded-3xl h-64 flex flex-col justify-between">
                            <div class="flex justify-between items-center">
                                <div class="flex flex-col"><span class="text-[12px] font-black uppercase text-rose-500">4. ME-Coupon</span><span class="text-[8px] text-slate-400 font-bold">가맹점 전용</span></div>
                                <input type="checkbox" name="use_me_coupon" id="use_me_coupon" class="policy-toggle" onchange="updateUI()">
                            </div>
                            <div class="space-y-2 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                <div class="flex justify-between items-center mb-1"><span class="text-[9px] font-bold text-slate-500">지급 기준 (Payment)</span></div>
                                <div class="flex items-center justify-end gap-1">
                                    <select name="me_coupon_currency" id="me_coupon_currency" class="text-[10px] font-black text-slate-500 bg-white p-1 rounded border outline-none">
                                        <option value="KRW">KRW</option><option value="USD">USD</option><option value="CNY">CNY</option><option value="JPY">JPY</option><option value="THB">THB</option><option value="IDR">IDR</option>
                                    </select>
                                    <input type="number" name="me_coupon_threshold" id="me_coupon_threshold" value="5000" class="w-24 p-1 text-[10px] text-right rounded border font-bold"> 
                                    <span class="text-[10px] font-bold text-slate-600">CP</span>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 justify-between"><span class="text-[9px] font-bold text-slate-500">목표</span><div class="flex items-center gap-1"><input type="number" name="me_coupon_target" id="me_coupon_target" value="10" class="w-12 p-1 text-[10px] rounded border text-center font-bold"> <span class="text-[10px] font-bold text-slate-600">CP</span></div></div>
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
                                    <select name="ad_coupon_currency" id="ad_coupon_currency" class="text-[10px] font-black text-slate-500 bg-white p-1 rounded border outline-none">
                                        <option value="KRW">KRW</option><option value="USD">USD</option><option value="CNY">CNY</option><option value="JPY">JPY</option><option value="THB">THB</option><option value="IDR">IDR</option>
                                    </select>
                                    <input type="number" name="ad_coupon_threshold" id="ad_coupon_threshold" value="10000" class="w-24 p-1 text-[10px] text-right rounded border font-bold"> 
                                    <span class="text-[10px] font-bold text-slate-600">CP</span>
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
                                    <select name="we_coupon_currency" id="we_coupon_currency" class="text-[10px] font-black text-slate-500 bg-white p-1 rounded border outline-none">
                                        <option value="KRW">KRW</option><option value="USD">USD</option><option value="CNY">CNY</option><option value="JPY">JPY</option><option value="THB">THB</option><option value="IDR">IDR</option>
                                    </select>
                                    <input type="number" name="we_coupon_threshold" id="we_coupon_threshold" value="20000" class="w-24 p-1 text-[10px] text-right rounded border font-bold"> 
                                    <span class="text-[10px] font-bold text-slate-600">CP</span>
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

                <div class="pt-10 flex justify-between items-center border-t">
                    <div class="flex items-center gap-2"><input type="checkbox" name="use_review" id="use_review" checked> <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Review Active</span></div>
                    <button type="submit" name="save_store" class="bg-sky-500 text-white px-20 py-5 rounded-[2rem] font-black uppercase tracking-widest shadow-xl shadow-sky-100 hover:bg-sky-600 transition-all active:scale-95">Save Changes</button>
                </div>
            </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pb-20">
            <?php foreach($stores as $s): 
                $my_r_ids = explode(',', $s['region_id']);
                $my_r_names = [];
                foreach($my_r_ids as $rid) if(isset($rg_map[$rid])) $my_r_names[] = $rg_map[$rid];
                $region_display = implode(', ', $my_r_names);
            ?>
            <div class="bg-white p-8 rounded-[2rem] shadow-lg border border-slate-100 relative group hover:shadow-xl transition-all">
                <div class="flex justify-between items-start mb-6">
                    <div class="flex items-center gap-2">
                        <span class="bg-slate-100 text-slate-700 border border-slate-200 px-3 py-1 rounded-full text-[9px] font-black uppercase">#<?php echo $s['id']; ?></span>
                        <?php if(!empty($s['store_code'])): ?>
                        <span class="bg-sky-100 text-sky-600 border border-sky-200 px-2 py-1 rounded-md text-[9px] font-black shadow-sm"><?php echo htmlspecialchars($s['store_code']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-2">
                        <button onclick='editStore(<?php echo json_encode($s); ?>)' class="text-[10px] font-black text-sky-500 uppercase hover:underline">Edit</button>
                        <a href="?delete=<?php echo $s['id']; ?>" onclick="return confirm('삭제하시겠습니까?')" class="text-[10px] font-black text-rose-500 uppercase hover:underline">Del</a>
                    </div>
                </div>
                <h3 class="text-2xl font-black text-slate-800 italic mb-2 uppercase tracking-tighter"><?php echo htmlspecialchars($s['store_name']); ?></h3>
                <div class="border-t pt-4 flex justify-between items-center">
                    <div class="flex gap-2 items-center">
                        <span class="text-[9px] font-black text-slate-900 uppercase"><?php echo $s['currency']; ?></span>
                        <?php if($region_display): ?>
                        <span class="text-[8px] font-bold text-emerald-500 uppercase leading-tight mt-1 truncate w-24"><?php echo htmlspecialchars($region_display); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-1">
                        <?php if($s['use_single']): ?><div class="w-2 h-2 bg-sky-400 rounded-full" title="Single"></div><?php endif; ?>
                        <?php if($s['use_multi']): ?><div class="w-2 h-2 bg-emerald-400 rounded-full" title="Multi"></div><?php endif; ?>
                        <?php if($s['use_global']): ?><div class="w-2 h-2 bg-amber-400 rounded-full" title="Global"></div><?php endif; ?>
                        <?php if($s['use_me_coupon']): ?><div class="w-2 h-2 bg-rose-400 rounded-full" title="ME-Coupon"></div><?php endif; ?>
                        <?php if($s['use_ad_coupon']): ?><div class="w-2 h-2 bg-sky-500 rounded-full" title="AD-Coupon"></div><?php endif; ?>
                        <?php if($s['use_we_coupon']): ?><div class="w-2 h-2 bg-violet-500 rounded-full" title="WE-Coupon"></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    const policyMap = {
        <?php foreach($policy_templates as $pt): ?>
        "<?php echo $pt['id']; ?>": <?php echo json_encode($pt); ?>,
        <?php endforeach; ?>
    };

    function syncCurrencies() {
        const main = document.getElementById('currency').value;
        const me = document.getElementById('me_coupon_currency');
        const ad = document.getElementById('ad_coupon_currency');
        const we = document.getElementById('we_coupon_currency');
        
        // 개별 설정값이 비어있을 때만 동기화하도록 조건 추가도 가능하지만
        // UX상 메인을 바꾸면 일단 따라가는게 직관적입니다.
        if(me) me.value = main;
        if(ad) ad.value = main;
        if(we) we.value = main;
    }

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

    // [핵심] 정책 템플릿 적용 함수
    function applyPolicyTemplate(id) {
        if (!id) return;
        if (!confirm("정책 템플릿을 적용하시겠습니까?\n기존 설정값은 덮어씌워집니다.")) {
            document.getElementById('policy_template_select').value = "";
            return;
        }

        const p = policyMap[id];
        if (!p) return;

        // 1. Single
        document.getElementById('use_single').checked = (p.use_single == 1);
        document.getElementById('single_threshold').value = p.single_threshold;
        document.getElementById('single_amt').value = p.single_amt;

        // 2. Multi
        document.getElementById('use_multi').checked = (p.use_multi == 1);

        // 3. Global
        document.getElementById('use_global').checked = (p.use_global == 1);

        // 4. ME
        document.getElementById('use_me_coupon').checked = (p.use_me_coupon == 1);
        document.getElementById('me_coupon_threshold').value = p.me_coupon_threshold;
        document.getElementById('me_coupon_currency').value = p.me_coupon_currency;
        document.getElementById('me_coupon_target').value = p.me_coupon_target;
        document.getElementById('me_coupon_reward').value = p.me_coupon_reward;
        document.getElementById('me_use_same_day').checked = (p.me_use_same_day == 1);

        // 5. AD
        document.getElementById('use_ad_coupon').checked = (p.use_ad_coupon == 1);
        document.getElementById('ad_coupon_threshold').value = p.ad_coupon_threshold;
        document.getElementById('ad_coupon_currency').value = p.ad_coupon_currency;
        const adTypeRadio = document.querySelector(`input[name="ad_coupon_type"][value="${p.ad_coupon_type}"]`);
        if(adTypeRadio) adTypeRadio.checked = true;
        document.getElementById('ad_use_same_day').checked = (p.ad_use_same_day == 1);

        // 6. WE
        document.getElementById('use_we_coupon').checked = (p.use_we_coupon == 1);
        document.getElementById('we_coupon_threshold').value = p.we_coupon_threshold;
        document.getElementById('we_coupon_currency').value = p.we_coupon_currency;
        document.getElementById('we_exchange_ratio').value = p.we_exchange_ratio;
        document.getElementById('we_exchange_fee').value = p.we_exchange_fee;
        document.getElementById('use_we_buy').checked = (p.use_we_buy == 1);
        document.getElementById('we_use_same_day').checked = (p.we_use_same_day == 1);

        updateUI(); // UI 상태 갱신 (활성/비활성 처리)
    }

    function validateFile() {
        const file = document.getElementById('biz_file').files[0];
        if (file) {
            if (file.size > 1024 * 1024) { alert("1MB 이하만 가능합니다."); return false; }
            if (!/(\.jpg|\.jpeg|\.png|\.pdf)$/i.exec(file.name)) { alert("JPG, PNG, PDF만 가능합니다."); return false; }
        }
        return true;
    }

    function editStore(data) {
        document.getElementById('form-title').innerText = "Modify: " + data.store_name;
        document.getElementById('store_id').value = data.id;
        document.getElementById('old_biz_file').value = data.biz_file || '';
        if (data.menu_format_id) {
            const sel = document.getElementById('menu_format_id');
            if (sel) sel.value = data.menu_format_id;
        }
        document.getElementById('store_name').value = data.store_name;
        document.getElementById('owner_name').value = data.owner_name;
        document.getElementById('owner_tel').value = data.owner_tel;
        document.getElementById('owner_email').value = data.owner_email;
        document.getElementById('manager_name').value = data.manager_name;
        document.getElementById('manager_tel').value = data.manager_tel;
        document.getElementById('manager_email').value = data.manager_email;
        document.getElementById('biz_no').value = data.biz_no;
        document.getElementById('tax_no').value = data.tax_no;
        document.getElementById('tax_address').value = data.tax_address;
        document.getElementById('address').value = data.address;
        document.getElementById('tel').value = data.tel;
        document.getElementById('currency').value = data.currency;
        
        document.getElementById('use_single').checked = (data.use_single == 1);
        document.getElementById('single_threshold').value = data.single_threshold;
        document.getElementById('single_amt').value = data.single_amt;
        
        document.getElementById('use_multi').checked = (data.use_multi == 1);
        const allRegionChecks = document.querySelectorAll('input[name="region_ids[]"]');
        allRegionChecks.forEach(el => el.checked = false);
        if (data.region_id) {
            const ids = data.region_id.split(',');
            ids.forEach(id => {
                const target = document.querySelector(`input[name="region_ids[]"][value="${id}"]`);
                if(target) target.checked = true;
            });
        }
        document.getElementById('use_global').checked = (data.use_global == 1);

        document.getElementById('use_me_coupon').checked = (data.use_me_coupon == 1);
        document.getElementById('me_coupon_threshold').value = data.me_coupon_threshold || 5000;
        document.getElementById('me_coupon_currency').value = data.me_coupon_currency || 'KRW';
        document.getElementById('me_coupon_target').value = data.me_coupon_target || 10;
        document.getElementById('me_coupon_reward').value = data.me_coupon_reward || '';
        document.getElementById('me_use_same_day').checked = (data.me_use_same_day == 1);
        
        document.getElementById('use_ad_coupon').checked = (data.use_ad_coupon == 1);
        document.getElementById('ad_coupon_threshold').value = data.ad_coupon_threshold || 10000;
        document.getElementById('ad_coupon_currency').value = data.ad_coupon_currency || 'KRW';
        const adType = data.ad_coupon_type || 'PRE';
        const adTypeRadio = document.querySelector(`input[name="ad_coupon_type"][value="${adType}"]`);
        if(adTypeRadio) adTypeRadio.checked = true;
        document.getElementById('ad_use_same_day').checked = (data.ad_use_same_day == 1);

        document.getElementById('use_we_coupon').checked = (data.use_we_coupon == 1);
        document.getElementById('we_coupon_threshold').value = data.we_coupon_threshold || 20000;
        document.getElementById('we_coupon_currency').value = data.we_coupon_currency || 'KRW';
        document.getElementById('we_exchange_ratio').value = data.we_exchange_ratio || 9;
        document.getElementById('we_exchange_fee').value = data.we_exchange_fee || 1;
        document.getElementById('use_we_buy').checked = (data.use_we_buy == 1);
        document.getElementById('we_use_same_day').checked = (data.we_use_same_day == 1);

        document.getElementById('use_review').checked = (data.use_review == 1);
        
        updateUI();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    window.onload = function() {
        updateUI();
    };
    </script>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
<?php include 'admin_card_footer.php'; ?>
<?php endif; ?>