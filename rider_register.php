<?php
// rider_register.php - Rider 온라인 등록 신청 (본사 등록 / 가맹점 Deliver 등록)
// 가맹점 등록: 가맹점 찾기(업체명·지역·가맹점번호) 후 선택 → 해당 가맹점 필수 항목으로 신청
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

$driver_field_options = get_driver_registration_field_options();
$type = isset($_GET['type']) ? trim($_GET['type']) : (isset($_POST['type']) ? trim($_POST['type']) : '');
if (!in_array($type, ['hq', 'store'], true)) $type = '';

$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : (isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0);
$store_row = null;
$driver_required = [];
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

// 본사 등록: store_id 없음, HQ 기본 필수 항목
if ($type === 'hq') {
    $driver_required = get_driver_required_fields($pdo, null);
}

// 가맹점 Deliver 등록: store_id 있으면 해당 가맹점 필수 항목
if ($type === 'store' && $store_id > 0) {
    try {
        $st = $pdo->prepare("SELECT id, store_name, address, region_id, store_code FROM stores WHERE id = ? LIMIT 1");
        $st->execute([$store_id]);
        $store_row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $st = $pdo->prepare("SELECT id, store_name FROM stores WHERE id = ? LIMIT 1");
        $st->execute([$store_id]);
        $store_row = $st->fetch(PDO::FETCH_ASSOC);
        if ($store_row) { $store_row['address'] = null; $store_row['region_id'] = null; $store_row['store_code'] = null; }
    }
    if (!empty($store_row)) {
        $driver_required = get_driver_required_fields($pdo, $store_id);
    }
}

// [POST] 등록 신청 저장 — driver_applications (본사 HQ / 가맹점 DELIVER)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_rider'])) {
    $type_post = isset($_POST['register_type']) ? trim($_POST['register_type']) : '';
    $store_id_post = (int)($_POST['store_id'] ?? 0);
    $is_hq = ($type_post === 'hq' || $store_id_post <= 0);
    if (!$is_hq && $store_id_post <= 0) {
        echo "<script>alert('가맹점을 선택해 주세요.'); history.back();</script>"; exit;
    }
    $req = get_driver_required_fields($pdo, $is_hq ? null : $store_id_post);

    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $birth_date_sql = ($birth_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) ? $birth_date : null;
    $email = trim($_POST['email'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $msgs = [];
    if (in_array('last_name', $req, true) && $last_name === '') $msgs[] = '성';
    if (in_array('first_name', $req, true) && $first_name === '') $msgs[] = '이름';
    if (in_array('address', $req, true) && $address === '') $msgs[] = '주소';
    if (in_array('phone', $req, true) && $phone === '') $msgs[] = '전화번호';
    if (in_array('birth_date', $req, true) && $birth_date_sql === null) $msgs[] = '생년월일';
    if (in_array('email', $req, true) && $email === '') $msgs[] = '이메일';
    if (in_array('tax_id', $req, true) && $tax_id === '') $msgs[] = 'tax 정보 또는 주민등록번호';
    if (in_array('username', $req, true) && $username === '') $msgs[] = '아이디';
    if (in_array('password', $req, true) && $password === '') $msgs[] = '패스워드';
    if ($msgs) {
        echo "<script>alert('필수 항목을 입력해 주세요: " . addslashes(implode(', ', $msgs)) . "'); history.back();</script>"; exit;
    }

    $id_document_path = null;
    if (!empty($_FILES['id_document']['name']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/drivers/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
            $filename = 'app_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['id_document']['tmp_name'], $upload_dir . $filename)) {
                $id_document_path = $upload_dir . $filename;
            }
        }
    }
    if (in_array('id_document', $req, true) && $id_document_path === null) {
        echo "<script>alert('주민등록증 등 이미지를 업로드해 주세요.'); history.back();</script>"; exit;
    }

    $name_display = trim($last_name . ' ' . $first_name) ?: '미입력';
    $pw_hash = password_hash($password, PASSWORD_DEFAULT);
    $driver_type_save = $is_hq ? 'HQ' : 'DELIVER';
    $store_id_save = $is_hq ? null : $store_id_post;

    try {
        $chk = $pdo->prepare("SELECT id FROM driver_applications WHERE username = ? AND status = 'PENDING' LIMIT 1");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            echo "<script>alert('이미 동일 아이디로 신청 중입니다.'); history.back();</script>"; exit;
        }
        $chk2 = $pdo->prepare("SELECT id FROM drivers WHERE username = ? LIMIT 1");
        $chk2->execute([$username]);
        if ($chk2->fetch()) {
            echo "<script>alert('이미 사용 중인 아이디입니다.'); history.back();</script>"; exit;
        }

        $pdo->prepare("
            INSERT INTO driver_applications (store_id, driver_type, name, last_name, first_name, address, phone, birth_date, email, id_document_path, tax_id, username, password, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
        ")->execute([
            $store_id_save, $driver_type_save, $name_display, $last_name, $first_name, $address ?: null, $phone ?: null, $birth_date_sql, $email ?: null, $id_document_path, $tax_id ?: null, $username, $pw_hash
        ]);
        $msg = $is_hq ? '본사 등록 신청이 완료되었습니다. 본사 검토 후 연락드립니다.' : '등록 신청이 완료되었습니다. 가맹점 검토 후 연락드립니다.';
        echo "<script>alert('" . addslashes($msg) . "'); location.href='login.php';</script>";
        exit;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "<script>alert('이미 사용 중이거나 신청 중인 아이디입니다.'); history.back();</script>";
        } else {
            echo "<script>alert('저장 중 오류가 발생했습니다. driver_applications 테이블을 확인해 주세요.'); history.back();</script>";
        }
        exit;
    }
}

// 가맹점 찾기: 검색 (업체명, 지역/위치, 가맹점 번호)
$search_results = [];
if ($type === 'store' && $search_q !== '') {
    $like = '%' . $search_q . '%';
    try {
        $stmt = $pdo->prepare("
            SELECT id, store_name, address, region_id, store_code
            FROM stores
            WHERE (store_name LIKE ? OR COALESCE(address,'') LIKE ? OR COALESCE(store_code,'') LIKE ? OR COALESCE(region_id,'') LIKE ?)
            ORDER BY store_name
            LIMIT 30
        ");
        $stmt->execute([$like, $like, $like, $like]);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare("SELECT id, store_name FROM stores WHERE store_name LIKE ? ORDER BY store_name LIMIT 30");
            $stmt->execute([$like]);
            $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($search_results as &$r) { $r['address'] = null; $r['region_id'] = null; $r['store_code'] = null; }
            unset($r);
        } catch (Exception $e2) {}
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider 등록 신청 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-2xl mx-auto space-y-8">
        <header class="text-center">
            <h1 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter">Rider 등록 신청</h1>
            <p class="text-sm text-slate-500 mt-2">본사 등록 또는 가맹점 Deliver 등록을 선택한 뒤, 가맹점 등록은 가맹점 찾기 후 선택하여 신청하세요.</p>
        </header>

        <?php if ($type === ''): ?>
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden p-6">
            <h2 class="text-sm font-black text-slate-800 uppercase mb-4">등록 구분 선택</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="rider_register.php?type=hq" class="block p-6 rounded-2xl border-2 border-slate-200 hover:border-sky-500 hover:bg-sky-50 text-center transition-all">
                    <span class="text-lg font-black text-slate-800">본사 등록</span>
                    <p class="text-xs text-slate-500 mt-2">본사 기준 필수 항목으로 신청</p>
                </a>
                <a href="rider_register.php?type=store" class="block p-6 rounded-2xl border-2 border-slate-200 hover:border-amber-500 hover:bg-amber-50 text-center transition-all">
                    <span class="text-lg font-black text-slate-800">가맹점 Deliver 등록</span>
                    <p class="text-xs text-slate-500 mt-2">가맹점 찾기 후 선택하여 신청</p>
                </a>
            </div>
        </div>
        <?php elseif ($type === 'hq'): ?>
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-sky-50 border-b border-sky-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">본사 등록 신청서</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">본사 기준 필수(*) 항목을 입력해 주세요.</p>
            </div>
            <?php $req_key = function($key) use ($driver_required) { return in_array($key, $driver_required, true); }; ?>
            <form method="POST" action="rider_register.php" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="register_rider" value="1">
                <input type="hidden" name="register_type" value="hq">
                <input type="hidden" name="store_id" value="0">
                <?php include __DIR__ . '/rider_register_form_fields.php'; ?>
                <div class="flex gap-3 pt-2">
                    <a href="rider_register.php" class="px-6 py-3 bg-slate-200 text-slate-700 rounded-xl text-xs font-black uppercase hover:bg-slate-300">뒤로</a>
                    <button type="submit" class="px-6 py-3 bg-sky-500 text-white rounded-xl text-xs font-black uppercase hover:bg-sky-600">본사 등록 신청</button>
                </div>
            </form>
        </div>
        <?php elseif ($type === 'store' && $store_id <= 0): ?>
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden p-6">
            <h2 class="text-sm font-black text-slate-800 uppercase mb-2">가맹점 찾기</h2>
            <p class="text-[10px] text-slate-500 mb-4">업체명, 지역/위치, 가맹점 번호로 검색한 뒤 지원할 가맹점을 선택하세요.</p>
            <form method="GET" action="rider_register.php" class="space-y-4">
                <input type="hidden" name="type" value="store">
                <div class="flex gap-2">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="업체명, 지역/위치, 가맹점 번호" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500">
                    <button type="submit" class="px-5 py-2.5 bg-amber-500 text-white rounded-xl text-xs font-black uppercase hover:bg-amber-600">찾기</button>
                </div>
            </form>
            <?php if ($search_q !== ''): ?>
            <div class="mt-6 border-t border-slate-100 pt-4">
                <?php if (empty($search_results)): ?>
                <p class="text-sm text-slate-500">검색 결과가 없습니다. 검색어를 바꿔 보세요.</p>
                <?php else: ?>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="py-2 font-black text-slate-600 uppercase text-[10px]">업체명</th>
                            <th class="py-2 font-black text-slate-600 uppercase text-[10px]">지역/위치</th>
                            <th class="py-2 font-black text-slate-600 uppercase text-[10px]">가맹점 번호</th>
                            <th class="py-2 font-black text-slate-600 uppercase text-[10px] w-20">선택</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results as $s): ?>
                        <tr class="border-b border-slate-50 hover:bg-slate-50">
                            <td class="py-3 font-bold text-slate-800"><?php echo htmlspecialchars($s['store_name']); ?></td>
                            <td class="py-3 text-slate-600"><?php echo htmlspecialchars(trim(($s['address'] ?? '') . ' ' . ($s['region_id'] ?? '')) ?: '—'); ?></td>
                            <td class="py-3 text-slate-600"><?php echo htmlspecialchars($s['store_code'] ?? '—'); ?></td>
                            <td class="py-3"><a href="rider_register.php?type=store&store_id=<?php echo (int)$s['id']; ?>" class="inline-block px-3 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-black uppercase hover:bg-amber-600">선택</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <p class="text-xs text-slate-400 mt-4"><a href="rider_register.php" class="text-sky-600 hover:underline">등록 구분 선택으로 돌아가기</a></p>
        </div>
        <?php elseif ($type === 'store' && $store_row): ?>
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-amber-50 border-b border-amber-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">가맹점 Deliver 등록 신청서</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">지원 가맹점: <strong><?php echo htmlspecialchars($store_row['store_name']); ?></strong> (가맹점 번호: <?php echo htmlspecialchars($store_row['store_code'] ?? '—'); ?>) — 아래 필수(*) 항목은 가맹점 설정과 동일합니다.</p>
            </div>
            <?php $req_key = function($key) use ($driver_required) { return in_array($key, $driver_required, true); }; ?>
            <form method="POST" action="rider_register.php" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="register_rider" value="1">
                <input type="hidden" name="register_type" value="store">
                <input type="hidden" name="store_id" value="<?php echo (int)$store_row['id']; ?>">
                <?php include __DIR__ . '/rider_register_form_fields.php'; ?>
                <div class="flex gap-3 pt-2">
                    <a href="rider_register.php?type=store" class="px-6 py-3 bg-slate-200 text-slate-700 rounded-xl text-xs font-black uppercase hover:bg-slate-300">가맹점 다시 찾기</a>
                    <button type="submit" class="px-6 py-3 bg-amber-500 text-white rounded-xl text-xs font-black uppercase hover:bg-amber-600">신청하기</button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 p-6 text-center text-slate-500">
            <p>잘못된 접근입니다.</p>
            <a href="rider_register.php" class="inline-block mt-4 text-sky-600 font-bold hover:underline">등록 신청 처음으로</a>
        </div>
        <?php endif; ?>

        <p class="text-center text-sm text-slate-500">
            이미 계정이 있으신가요? <a href="login.php" class="text-sky-600 font-bold hover:underline">로그인</a>
        </p>
    </div>
</body>
</html>
