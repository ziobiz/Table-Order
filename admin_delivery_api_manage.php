<?php
// admin_delivery_api_manage.php - 본사: 계약된 배달앱 API 관리 (한국/태국/일본)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    header("Location: login.php");
    exit;
}
include 'common.php';
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? ('id_' . $admin_id);
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$admin_login_at = (int)($_SESSION['admin_login_at'] ?? time());
$header_locale = 'ko';

$countries = [
    'KR' => '한국', 'TH' => '태국', 'JP' => '일본',
    'SG' => '싱가포르', 'VN' => '베트남', 'ID' => '인도네시아', 'IN' => '인도', 'MY' => '말레이시아'
];
// 국기 이미지 CDN (국가명만 나오고 이모지가 안 나올 때 이미지로 표시)
$country_flag_url = function($code) {
    $lower = strtolower($code);
    return "https://flagcdn.com/w40/{$lower}.png";
};
$auth_types = ['API_KEY' => 'API Key', 'KEY_SECRET' => 'Key + Secret', 'OAUTH' => 'OAuth'];
$allowed_country_codes = ['KR', 'TH', 'JP', 'SG', 'VN', 'ID', 'IN', 'MY'];

// 저장
if (isset($_POST['save_provider'])) {
    $id = (int)($_POST['provider_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $name_local = trim($_POST['name_local'] ?? '');
    $country_code = in_array($_POST['country_code'] ?? '', $allowed_country_codes) ? $_POST['country_code'] : 'KR';
    $api_base_url = trim($_POST['api_base_url'] ?? '');
    $auth_type = in_array($_POST['auth_type'] ?? '', array_keys($auth_types)) ? $_POST['auth_type'] : 'KEY_SECRET';
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        echo "<script>alert('API 이름을 입력해 주세요.');</script>";
    } else {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE delivery_api_providers SET name=?, name_local=?, country_code=?, api_base_url=?, auth_type=?, description=?, is_active=?, sort_order=? WHERE id=?");
                $stmt->execute([$name, $name_local, $country_code, $api_base_url, $auth_type, $description, $is_active, $sort_order, $id]);
                log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_delivery_api_manage', 'update', 'delivery_api', (string)$id, "Delivery API 수정: {$name} (ID {$id})");
                echo "<script>alert('수정되었습니다.'); location.href='admin_delivery_api_manage.php';</script>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO delivery_api_providers (name, name_local, country_code, api_base_url, auth_type, description, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$name, $name_local, $country_code, $api_base_url, $auth_type, $description, $is_active, $sort_order]);
                $new_id = $pdo->lastInsertId();
                log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_delivery_api_manage', 'create', 'delivery_api', (string)$new_id, "Delivery API 등록: {$name} (ID {$new_id})");
                echo "<script>alert('등록되었습니다.'); location.href='admin_delivery_api_manage.php';</script>";
            }
            exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "<script>alert('동일 국가에 같은 이름의 API가 이미 있습니다.');</script>";
            } else {
                echo "<script>alert('저장 실패: " . addslashes($e->getMessage()) . "');</script>";
            }
        }
    }
}

// 삭제
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM store_delivery_api_credentials WHERE provider_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM delivery_api_providers WHERE id = ?")->execute([$id]);
    log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_delivery_api_manage', 'delete', 'delivery_api', (string)$id, "Delivery API 삭제: ID {$id}");
    header("Location: admin_delivery_api_manage.php");
    exit;
}

// 목록 (국가별로 그룹화)
$providers_by_country = array_fill_keys($allowed_country_codes, []);
try {
    $rows = $pdo->query("SELECT * FROM delivery_api_providers ORDER BY country_code ASC, sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $p) {
        $code = $p['country_code'] ?? 'KR';
        if (!isset($providers_by_country[$code])) {
            $providers_by_country[$code] = [];
        }
        $providers_by_country[$code][] = $p;
    }
} catch (PDOException $e) {}

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = 'Delivery API';
    $admin_page_subtitle = '계약된 배달앱 API 관리 · 가맹점은 키만 입력해 연동';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar):
    $admin_page_title = 'Delivery API';
    $admin_page_subtitle = '계약된 배달앱 API 관리 · 가맹점은 키만 입력해 연동';
    include 'admin_card_header.php';
endif; ?>

        <div class="max-w-[96rem] space-y-10">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- 등록/수정 폼 -->
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-slate-100">
                    <h3 class="text-sm font-black text-slate-800 uppercase mb-4">API 등록 / 수정</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="provider_id" id="provider_id" value="">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Name (영문)</label>
                            <input type="text" name="name" id="f_name" required class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-sky-500" placeholder="Baemin, Grab">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Name (현지어)</label>
                            <input type="text" name="name_local" id="f_name_local" class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold outline-none" placeholder="배민, 그랩">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Country</label>
                            <select name="country_code" id="f_country_code" class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold outline-none">
                                <?php foreach ($countries as $code => $label): ?>
                                <option value="<?php echo $code; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">API Base URL</label>
                            <input type="text" name="api_base_url" id="f_api_base_url" class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold outline-none" placeholder="https://api.example.com">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Auth Type</label>
                            <select name="auth_type" id="f_auth_type" class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold outline-none">
                                <?php foreach ($auth_types as $k => $v): ?>
                                <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Description (가맹점 안내)</label>
                            <textarea name="description" id="f_description" rows="3" class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold outline-none" placeholder="가맹점이 입력할 키 안내"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Sort Order</label>
                            <input type="number" name="sort_order" id="f_sort_order" value="0" class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold outline-none">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" id="f_is_active" value="1" checked class="w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            <label for="f_is_active" class="text-xs font-bold text-slate-600">활성 (가맹점에 노출)</label>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" name="save_provider" class="flex-1 bg-slate-900 text-white py-3 rounded-xl text-xs font-black uppercase hover:bg-slate-800">Save</button>
                            <button type="button" onclick="resetForm()" class="px-4 py-3 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200">NEW</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 목록 (국가별 구분) -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
                    <div class="p-5 bg-slate-50 border-b border-slate-100">
                        <h3 class="text-sm font-black text-slate-800 uppercase">등록된 API (국가별)</h3>
                        <p class="text-[10px] text-slate-500 mt-0.5">본사에서 계약한 API만 등록. 가맹점은 대시보드에서 키만 입력해 연동.</p>
                    </div>
                    <div class="max-h-[600px] overflow-y-auto p-4 space-y-6">
                        <?php
                        $has_any = false;
                        foreach ($countries as $code => $label):
                            $list = $providers_by_country[$code] ?? [];
                            $flag_src = $country_flag_url($code);
                        ?>
                        <div class="rounded-2xl border-2 border-slate-200 overflow-hidden bg-slate-50/50">
                            <div class="px-4 py-3 bg-white border-b border-slate-200 flex items-center gap-3">
                                <img src="<?php echo htmlspecialchars($flag_src); ?>" alt="<?php echo htmlspecialchars($label); ?>" class="w-10 h-7 object-cover rounded border border-slate-100" loading="lazy" onerror="this.style.display='none'">
                                <span class="font-black text-slate-800 uppercase text-sm"><?php echo $label; ?></span>
                            </div>
                            <div class="divide-y divide-slate-100">
                                <?php if (empty($list)): ?>
                                <div class="p-4 text-center text-slate-400 text-xs">등록된 API 없음</div>
                                <?php else: $has_any = true; foreach ($list as $p): ?>
                                <div class="p-4 flex flex-wrap items-center justify-between gap-3 hover:bg-white/60">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-800"><?php echo htmlspecialchars($p['name']); ?></span>
                                            <?php if ($p['name_local']): ?><span class="text-slate-500 text-xs"><?php echo htmlspecialchars($p['name_local']); ?></span><?php endif; ?>
                                            <?php if (!$p['is_active']): ?><span class="text-[9px] text-rose-500 font-bold">비활성</span><?php endif; ?>
                                        </div>
                                        <p class="text-[10px] text-slate-400 mt-0.5"><?php echo htmlspecialchars($p['auth_type']); ?> · <?php echo htmlspecialchars($p['api_base_url'] ?: '-'); ?></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" onclick='editProvider(<?php echo json_encode($p); ?>)' class="px-3 py-1.5 bg-sky-100 text-sky-700 text-xs font-bold rounded-xl hover:bg-sky-200">수정</button>
                                        <a href="?delete=<?php echo (int)$p['id']; ?>" onclick="return confirm('이 API를 삭제하면 가맹점 연동 정보도 삭제됩니다. 계속할까요?');" class="px-3 py-1.5 bg-rose-50 text-rose-600 text-xs font-bold rounded-xl hover:bg-rose-100">삭제</a>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$has_any): ?>
                        <div class="p-8 text-center text-slate-400 text-sm">등록된 API가 없습니다. 왼쪽 폼에서 추가하세요.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
    </div>
    <script>
    function resetForm() {
        document.getElementById('provider_id').value = '';
        document.getElementById('f_name').value = '';
        document.getElementById('f_name_local').value = '';
        document.getElementById('f_country_code').value = 'KR';
        document.getElementById('f_api_base_url').value = '';
        document.getElementById('f_auth_type').value = 'KEY_SECRET';
        document.getElementById('f_description').value = '';
        document.getElementById('f_sort_order').value = '0';
        document.getElementById('f_is_active').checked = true;
    }
    function editProvider(p) {
        document.getElementById('provider_id').value = p.id;
        document.getElementById('f_name').value = p.name || '';
        document.getElementById('f_name_local').value = p.name_local || '';
        document.getElementById('f_country_code').value = p.country_code || 'KR';
        document.getElementById('f_api_base_url').value = p.api_base_url || '';
        document.getElementById('f_auth_type').value = p.auth_type || 'KEY_SECRET';
        document.getElementById('f_description').value = p.description || '';
        document.getElementById('f_sort_order').value = p.sort_order || 0;
        document.getElementById('f_is_active').checked = (p.is_active == 1);
    }
    </script>
<?php include 'admin_card_footer.php'; ?>
<?php endif; ?>
