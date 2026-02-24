<?php
// store_rider_manage.php - 가맹점: 소속 Deliver 등록·수정·로그인 계정
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = (int)$_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? '';
$store_login_at = (int)($_SESSION['store_login_at'] ?? time());
$header_locale = $_SESSION['store_locale'] ?? 'ko';

// 기사 등록 시 필수로 할 항목 (가맹점 선택) — 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_required_fields'])) {
    $allowed = array_keys(get_driver_registration_field_options());
    $checked = [];
    foreach ($allowed as $key) {
        if (!empty($_POST['required_fields']) && is_array($_POST['required_fields']) && in_array($key, $_POST['required_fields'], true)) {
            $checked[] = $key;
        }
    }
    $driver_required_fields_value = $checked ? implode(',', $checked) : null;
    try {
        $pdo->prepare("UPDATE stores SET driver_required_fields = ? WHERE id = ?")->execute([$driver_required_fields_value, $store_id]);
        log_activity($pdo, 'store', $store_id, $store_name, 'store_rider_manage', 'update', 'store_settings', $store_id, "Deliver 등록 필수 항목 설정 변경");
        echo "<script>alert('필수 항목 설정이 저장되었습니다. 기사 등록·배달기사 온라인 신청서에 동일하게 적용됩니다.'); location.href='store_rider_manage.php';</script>"; exit;
    } catch (Exception $e) {
        echo "<script>alert('설정 저장 실패. DB에 driver_required_fields 컬럼이 있는지 확인하세요.'); location.href='store_rider_manage.php';</script>"; exit;
    }
}

$driver_required = get_driver_required_fields($pdo, $store_id);
$driver_field_options = get_driver_registration_field_options();

// 저장 (등록/수정) — drivers 테이블 (driver_type=DELIVER 가맹점 Deliver), 확장 필드 포함
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rider'])) {
    $rider_id = (int)($_POST['rider_id'] ?? 0);
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

    // 가맹점이 설정한 필수 항목만 검증
    $req = get_driver_required_fields($pdo, $store_id);
    $msgs = [];
    if (in_array('last_name', $req, true) && $last_name === '') $msgs[] = '성';
    if (in_array('first_name', $req, true) && $first_name === '') $msgs[] = '이름';
    if (in_array('address', $req, true) && $address === '') $msgs[] = '주소';
    if (in_array('phone', $req, true) && $phone === '') $msgs[] = '전화번호';
    if (in_array('birth_date', $req, true) && $birth_date_sql === null) $msgs[] = '생년월일';
    if (in_array('email', $req, true) && $email === '') $msgs[] = '이메일';
    if (in_array('tax_id', $req, true) && $tax_id === '') $msgs[] = 'tax 정보 또는 주민등록번호';
    if (in_array('username', $req, true) && $username === '') $msgs[] = '아이디';
    if (in_array('password', $req, true) && $rider_id <= 0 && $password === '') $msgs[] = '패스워드';
    if (in_array('password', $req, true) && $rider_id > 0 && $password === '') { /* 수정 시 패스워드는 선택 */ }
    if ($msgs) {
        echo "<script>alert('필수 항목을 입력해 주세요: " . addslashes(implode(', ', $msgs)) . "'); location.reload();</script>"; exit;
    }
    if ($username === '' && in_array('username', $req, true)) {
        echo "<script>alert('아이디는 필수입니다.'); location.reload();</script>"; exit;
    }

    $name_display = $last_name . ' ' . $first_name;

    // 주민등록증 등 이미지 업로드
    $id_document_path = null;
    if (!empty($_FILES['id_document']['name']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/drivers/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
            $filename = 'id_' . ($rider_id > 0 ? $rider_id : time()) . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['id_document']['tmp_name'], $upload_dir . $filename)) {
                $id_document_path = $upload_dir . $filename;
            }
        }
    }
    // id_document 필수 시: 신규는 파일 필수, 수정은 기존 또는 새 파일 필요
    if (in_array('id_document', $req, true)) {
        if ($rider_id <= 0 && $id_document_path === null) {
            echo "<script>alert('주민등록증 등 이미지를 업로드해 주세요.'); location.reload();</script>"; exit;
        }
        if ($rider_id > 0) {
            $ex = $pdo->prepare("SELECT id_document_path FROM drivers WHERE id = ? AND store_id = ? LIMIT 1");
            $ex->execute([$rider_id, $store_id]);
            $exRow = $ex->fetch(PDO::FETCH_ASSOC);
            $has_existing = isset($exRow['id_document_path']) && $exRow['id_document_path'] !== '' && $exRow['id_document_path'] !== null;
            if (!$has_existing && $id_document_path === null) {
                echo "<script>alert('주민등록증 등 이미지를 업로드해 주세요.'); location.reload();</script>"; exit;
            }
        }
    }

    try {
        if ($rider_id > 0) {
            $chk = $pdo->prepare("SELECT * FROM drivers WHERE id = ? AND store_id = ? AND driver_type = 'DELIVER'");
            $chk->execute([$rider_id, $store_id]);
            $existing = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                echo "<script>alert('해당 기사를 수정할 수 없습니다.'); location.reload();</script>"; exit;
            }
            $id_document_final = $id_document_path !== null ? $id_document_path : (isset($existing['id_document_path']) ? $existing['id_document_path'] : null);

            $has_ext = false;
            try {
                $pdo->query("SELECT last_name FROM drivers LIMIT 1");
                $has_ext = true;
            } catch (Exception $e) {}

            if ($has_ext) {
                if ($password !== '') {
                    $pw_hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE drivers SET name = ?, last_name = ?, first_name = ?, address = ?, phone = ?, birth_date = ?, email = ?, id_document_path = ?, tax_id = ?, username = ?, password = ?, store_id = ? WHERE id = ?")
                        ->execute([$name_display, $last_name, $first_name, $address ?: null, $phone ?: null, $birth_date_sql, $email ?: null, $id_document_final, $tax_id ?: null, $username, $pw_hash, $store_id, $rider_id]);
                } else {
                    $pdo->prepare("UPDATE drivers SET name = ?, last_name = ?, first_name = ?, address = ?, phone = ?, birth_date = ?, email = ?, id_document_path = ?, tax_id = ?, username = ?, store_id = ? WHERE id = ?")
                        ->execute([$name_display, $last_name, $first_name, $address ?: null, $phone ?: null, $birth_date_sql, $email ?: null, $id_document_final, $tax_id ?: null, $username, $store_id, $rider_id]);
                }
            } else {
                if ($password !== '') {
                    $pw_hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE drivers SET name = ?, phone = ?, username = ?, password = ?, store_id = ? WHERE id = ?")
                        ->execute([$name_display, $phone ?: null, $username, $pw_hash, $store_id, $rider_id]);
                } else {
                    $pdo->prepare("UPDATE drivers SET name = ?, phone = ?, username = ?, store_id = ? WHERE id = ?")
                        ->execute([$name_display, $phone ?: null, $username, $store_id, $rider_id]);
                }
            }
            log_activity($pdo, 'store', $store_id, $store_name, 'store_rider_manage', 'update', 'driver', $rider_id, "Deliver 수정: " . $name_display . " (ID {$rider_id})");
            echo "<script>alert('수정되었습니다.'); location.href='store_rider_manage.php';</script>"; exit;
        } else {
            $pw_hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $pdo->prepare("INSERT INTO drivers (driver_type, store_id, name, last_name, first_name, address, phone, birth_date, email, id_document_path, tax_id, username, password, is_active) VALUES ('DELIVER', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)")
                    ->execute([$store_id, $name_display, $last_name, $first_name, $address ?: null, $phone ?: null, $birth_date_sql, $email ?: null, $id_document_path, $tax_id ?: null, $username, $pw_hash]);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'last_name') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
                    $pdo->prepare("INSERT INTO drivers (driver_type, store_id, name, phone, username, password, is_active) VALUES ('DELIVER', ?, ?, ?, ?, ?, 1)")
                        ->execute([$store_id, $name_display, $phone ?: null, $username, $pw_hash]);
                } else {
                    throw $e;
                }
            }
            $new_rider_id = (int)$pdo->lastInsertId();
            log_activity($pdo, 'store', $store_id, $store_name, 'store_rider_manage', 'create', 'driver', $new_rider_id, "Deliver 등록: " . $name_display . " (ID {$new_rider_id})");
            echo "<script>alert('등록되었습니다.'); location.href='store_rider_manage.php';</script>"; exit;
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "<script>alert('이미 사용 중인 아이디입니다.'); location.reload();</script>";
        } else {
            echo "<script>alert('저장 실패: " . addslashes($e->getMessage()) . "'); location.reload();</script>";
        }
        exit;
    }
}

// 목록: 본 가맹점 소속 STORE 기사만 (drivers 테이블)
$riders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM drivers WHERE store_id = ? AND driver_type = 'DELIVER' ORDER BY id DESC");
    $stmt->execute([$store_id]);
    $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Deliver 관리 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-8">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 shrink-0">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter">Deliver 관리</h1>
                <span class="text-xs text-slate-400 font-bold hidden sm:inline"><?php echo htmlspecialchars($store_name); ?> 소속</span>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-4 sm:gap-6 text-xs font-bold">
                <span class="text-slate-500 whitespace-nowrap">접속자 <?php echo htmlspecialchars($store_name); ?></span>
                <span id="current-datetime" class="text-slate-600 whitespace-nowrap">—</span>
                <span class="text-slate-500 whitespace-nowrap">머문 <span id="elapsed-time">0분 0초</span></span>
                <button type="button" onclick="location.href='store_dashboard.php'" class="bg-white border-2 border-slate-200 px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all shrink-0">Back to Dashboard</button>
                <a href="logout.php" class="text-rose-500 hover:underline whitespace-nowrap shrink-0">Logout</a>
            </div>
        </header>

        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-amber-50 border-b border-amber-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">소속 Deliver 목록</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">등록한 Deliver만 이 매장 배달 할당 대기 목록에 노출됩니다. 로그인 ID/비밀번호를 설정하면 Rider 로그인으로 접속할 수 있습니다.</p>
            </div>
            <div class="divide-y divide-slate-50">
                <?php if (empty($riders)): ?>
                <div class="p-8 text-center text-slate-400 text-sm font-bold">등록된 Deliver가 없습니다. 아래에서 추가하세요.</div>
                <?php endif; ?>
                <?php foreach ($riders as $r): ?>
                <div class="p-5 hover:bg-slate-50/50 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <?php
                        $display_name = '';
                        if (!empty($r['last_name']) || !empty($r['first_name'])) {
                            $display_name = trim(($r['last_name'] ?? '') . ' ' . ($r['first_name'] ?? ''));
                        }
                        if ($display_name === '') $display_name = $r['name'] ?? $r['rider_name'] ?? '';
                        ?>
                        <span class="font-black text-slate-800"><?php echo htmlspecialchars($display_name); ?></span>
                        <?php if (!empty($r['phone'])): ?><span class="text-slate-500 text-xs ml-2"><?php echo htmlspecialchars($r['phone']); ?></span><?php endif; ?>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-600 ml-2"><?php echo htmlspecialchars($r['username'] ?: '—'); ?></span>
                    </div>
                    <a href="?edit=<?php echo (int)$r['id']; ?>" class="text-xs font-bold text-sky-600 hover:underline">수정</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 기사 등록 시 필수로 할 항목 (가맹점 선택) — 기사 등록·배달기사 온라인 신청서에 동일 적용 -->
        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-violet-50 border-b border-violet-100">
                <h2 class="text-sm font-black text-slate-800 uppercase">Rider/Deliver 등록 시 필수 입력 항목 설정</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">체크한 항목만 Deliver 등록·Rider 온라인 등록 신청서에서 필수로 적용됩니다.</p>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="save_required_fields" value="1">
                <div class="flex flex-wrap gap-4 sm:gap-6">
                    <?php foreach ($driver_field_options as $key => $label): ?>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="required_fields[]" value="<?php echo htmlspecialchars($key); ?>" <?php echo in_array($key, $driver_required, true) ? 'checked' : ''; ?> class="rounded border-slate-300 text-amber-500 focus:ring-amber-500">
                        <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4">
                    <button type="submit" class="px-5 py-2.5 bg-violet-500 text-white rounded-xl text-xs font-black uppercase hover:bg-violet-600">필수 항목 설정 저장</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 bg-slate-50 border-b border-slate-100">
                <h2 class="text-sm font-black text-slate-800 uppercase"><?php echo isset($_GET['edit']) ? 'Deliver 수정' : 'Deliver 등록'; ?></h2>
            </div>
            <?php
            $edit = null;
            if (isset($_GET['edit'])) {
                $edit_id = (int)$_GET['edit'];
                foreach ($riders as $r) { if ((int)$r['id'] === $edit_id) { $edit = $r; break; } }
                if ($edit && empty($edit['last_name']) && empty($edit['first_name']) && !empty($edit['name'])) {
                    $parts = preg_split('/\s+/u', trim($edit['name']), 2);
                    $edit['last_name'] = $parts[0] ?? '';
                    $edit['first_name'] = $parts[1] ?? '';
                }
            }
            ?>
            <form method="POST" action="" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="save_rider" value="1">
                <input type="hidden" name="rider_id" value="<?php echo $edit ? (int)$edit['id'] : 0; ?>">
                <?php
                $req_key = function($key) use ($driver_required) { return in_array($key, $driver_required, true); };
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">성 <?php if ($req_key('last_name')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
                        <input type="text" name="last_name" value="<?php echo $edit ? htmlspecialchars($edit['last_name'] ?? '') : ''; ?>" <?php if ($req_key('last_name')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="홍">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">이름 <?php if ($req_key('first_name')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
                        <input type="text" name="first_name" value="<?php echo $edit ? htmlspecialchars($edit['first_name'] ?? '') : ''; ?>" <?php if ($req_key('first_name')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="길동">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">주소 <?php if ($req_key('address')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
                    <input type="text" name="address" value="<?php echo $edit ? htmlspecialchars($edit['address'] ?? '') : ''; ?>" <?php if ($req_key('address')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="주소 입력">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">전화번호 <?php if ($req_key('phone')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
                        <input type="text" name="phone" value="<?php echo $edit ? htmlspecialchars($edit['phone'] ?? '') : ''; ?>" <?php if ($req_key('phone')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="010-0000-0000">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">생년월일 <?php if ($req_key('birth_date')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
                        <input type="date" name="birth_date" value="<?php echo $edit && !empty($edit['birth_date']) ? htmlspecialchars($edit['birth_date']) : ''; ?>" <?php if ($req_key('birth_date')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">이메일 <?php if ($req_key('email')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
                    <input type="email" name="email" value="<?php echo $edit ? htmlspecialchars($edit['email'] ?? '') : ''; ?>" <?php if ($req_key('email')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="email@example.com">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">주민등록증 등 이미지 <?php if ($req_key('id_document')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
                    <?php if ($edit && !empty($edit['id_document_path'])): ?>
                    <p class="text-xs text-slate-500 mb-1">현재: <a href="<?php echo htmlspecialchars($edit['id_document_path']); ?>" target="_blank" class="text-sky-600 hover:underline">이미지 보기</a> — 새 파일 선택 시 교체됩니다.</p>
                    <?php endif; ?>
                    <input type="file" name="id_document" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full px-4 py-2 text-sm border border-slate-200 rounded-xl">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">tax 정보 또는 주민등록번호 <?php if ($req_key('tax_id')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
                    <input type="text" name="tax_id" value="<?php echo $edit ? htmlspecialchars($edit['tax_id'] ?? '') : ''; ?>" <?php if ($req_key('tax_id')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="주민번호 또는 tax ID">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">아이디 (로그인 ID) <?php if ($req_key('username')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
                        <input type="text" name="username" value="<?php echo $edit ? htmlspecialchars($edit['username'] ?? '') : ''; ?>" <?php if ($req_key('username')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="rider1">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">패스워드 <?php echo $edit ? '(변경 시만 입력)' : ''; ?><?php if ($req_key('password') && !$edit): ?> <span class="text-rose-500">*</span><?php endif; ?></label>
                        <input type="password" name="password" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="<?php echo $edit ? '변경 시에만 입력' : '비밀번호'; ?>" autocomplete="off" <?php if ($req_key('password') && !$edit): ?>required<?php endif; ?>>
                    </div>
                </div>
                <p class="text-[10px] text-slate-500">소속 매장: <?php echo htmlspecialchars($store_name); ?> (ID <?php echo $store_id; ?>) — 자동 적용</p>
                <div class="flex gap-3">
                    <button type="submit" class="px-6 py-3 bg-amber-500 text-white rounded-xl text-xs font-black uppercase hover:bg-amber-600"><?php echo $edit ? '수정' : 'Deliver 등록'; ?></button>
                    <a href="store_rider_manage.php" class="px-6 py-3 bg-slate-200 text-slate-700 rounded-xl text-xs font-black uppercase hover:bg-slate-300"><?php echo $edit ? '취소' : '목록'; ?></a>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function() {
        var loginAt = <?php echo $store_login_at; ?> * 1000;
        var locale = <?php echo json_encode($header_locale); ?>;
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        var thMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        var enMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        function formatDateTimeLocale(now) {
            var y = now.getFullYear(), m = now.getMonth(), d = now.getDate();
            var h = pad(now.getHours()), i = pad(now.getMinutes()), s = pad(now.getSeconds());
            var time = h + ':' + i + ':' + s;
            if (locale === 'th') return d + ' ' + thMonths[m] + ' ' + (y + 543) + ' ' + time;
            if (locale === 'en' || locale === 'en_us') return enMonths[m] + ' ' + d + ', ' + y + ' ' + time;
            if (locale === 'ja') return y + '年' + (m+1) + '月' + d + '日 ' + time;
            if (locale === 'vi') return d + '/' + (m+1) + '/' + y + ' ' + time;
            return y + '년 ' + (m+1) + '월 ' + d + '일 ' + time;
        }
        function formatElapsed(sec) {
            var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
            if (h > 0) return h + '시간 ' + m + '분 ' + s + '초';
            if (m > 0) return m + '분 ' + s + '초';
            return s + '초';
        }
        function tick() {
            var now = new Date();
            var el = document.getElementById('current-datetime');
            if (el) el.textContent = formatDateTimeLocale(now);
            var et = document.getElementById('elapsed-time');
            if (et && loginAt) {
                var sec = Math.max(0, Math.floor((now.getTime() - loginAt) / 1000));
                et.textContent = formatElapsed(sec);
            }
        }
        tick();
        setInterval(tick, 1000);
    })();
    </script>
</body>
</html>
