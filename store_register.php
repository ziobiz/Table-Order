<?php
// store_register.php - 가맹점 온라인 입점 신청
include 'db_config.php';

// [AJAX] 인증번호 발송 및 확인 로직 (실제로는 별도 API 파일로 분리 권장)
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $email = $_POST['email'] ?? '';

    if ($action === 'send_code') {
        // 1. 인증번호 생성 (6자리 난수)
        $code = rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        // DB 저장
        $stmt = $pdo->prepare("INSERT INTO verifications (target, code, type, expires_at) VALUES (?, ?, 'REGISTER', ?)");
        $stmt->execute([$email, $code, $expiry]);

        // ★ 실제 메일 발송 로직 (PHPMailer 등 사용 필요) ★
        // mail($email, "Alrira 인증번호", "인증번호: $code"); 
        
        echo json_encode(['status' => 'success', 'message' => '인증번호가 발송되었습니다. (테스트용: ' . $code . ')']);
        exit;
    }

    if ($action === 'verify_code') {
        $code = $_POST['code'];
        $stmt = $pdo->prepare("SELECT * FROM verifications WHERE target = ? AND code = ? AND type = 'REGISTER' AND expires_at > NOW() AND is_verified = 0 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email, $code]);
        $row = $stmt->fetch();

        if ($row) {
            // 인증 완료 처리
            $pdo->prepare("UPDATE verifications SET is_verified = 1 WHERE id = ?")->execute([$row['id']]);
            echo json_encode(['status' => 'success', 'message' => '인증되었습니다.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '유효하지 않거나 만료된 인증번호입니다.']);
        }
        exit;
    }
}

// [POST] 가입 처리 — store_applications에 PENDING으로 저장
if (isset($_POST['register_store'])) {
    $email_verified = (int)($_POST['email_verified'] ?? 0);
    if ($email_verified !== 1) {
        echo "<script>alert('이메일 인증을 완료해 주세요.'); history.back();</script>";
        exit;
    }
    $store_name = trim($_POST['store_name'] ?? '');
    $owner_name = trim($_POST['owner_name'] ?? '');
    $owner_email = trim($_POST['owner_email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $business_type = in_array($_POST['business_type'] ?? '', ['CORPORATE','INDIVIDUAL_BIZ','PERSONAL']) ? $_POST['business_type'] : 'CORPORATE';
    $biz_no = trim($_POST['biz_no'] ?? '');

    if ($store_name === '' || $owner_name === '' || $owner_email === '' || $username === '' || $password === '') {
        echo "<script>alert('필수 항목을 모두 입력해 주세요.'); history.back();</script>";
        exit;
    }
    if (strlen($password) < 4) {
        echo "<script>alert('비밀번호는 4자 이상 입력해 주세요.'); history.back();</script>";
        exit;
    }

    try {
        // 동일 username 이미 신청 중이거나 이미 가맹점으로 존재하는지 확인
        $chk = $pdo->prepare("SELECT id FROM store_applications WHERE username = ? AND status = 'PENDING' LIMIT 1");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            echo "<script>alert('이미 동일 아이디로 신청 중입니다.'); history.back();</script>";
            exit;
        }
        $chk2 = $pdo->prepare("SELECT id FROM stores WHERE username = ? LIMIT 1");
        $chk2->execute([$username]);
        if ($chk2->fetch()) {
            echo "<script>alert('이미 사용 중인 가맹점 아이디입니다.'); history.back();</script>";
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO store_applications (store_name, owner_name, owner_email, username, password, business_type, biz_no, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')
        ");
        $stmt->execute([$store_name, $owner_name, $owner_email, $username, $password_hash, $business_type, $biz_no === '' ? null : $biz_no]);
        echo "<script>alert('입점 신청이 완료되었습니다. 본사 심사 후 연락드립니다.'); location.href='login.php';</script>";
        exit;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "<script>alert('이미 사용 중이거나 신청 중인 아이디입니다.'); history.back();</script>";
        } else {
            echo "<script>alert('저장 중 오류가 발생했습니다. 다시 시도해 주세요.'); history.back();</script>";
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Partner Registration - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-[96rem] w-full bg-white rounded-[2rem] shadow-xl p-8 border border-slate-100">
        
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-slate-900">Partner Join</h1>
            <p class="text-sm text-slate-500 mt-2">가맹점 온라인 입점 신청 (KYB 인증)</p>
        </div>

        <div class="flex mb-6 bg-slate-100 p-1 rounded-xl">
            <button type="button" onclick="setType('CORPORATE')" class="type-btn flex-1 py-2 text-xs font-bold rounded-lg bg-white shadow-sm text-slate-800">법인 사업자</button>
            <button type="button" onclick="setType('INDIVIDUAL_BIZ')" class="type-btn flex-1 py-2 text-xs font-bold rounded-lg text-slate-400 hover:text-slate-600">개인 사업자</button>
            <button type="button" onclick="setType('PERSONAL')" class="type-btn flex-1 py-2 text-xs font-bold rounded-lg text-slate-400 hover:text-slate-600">개인 (예비)</button>
        </div>

        <form method="POST" id="regForm" class="space-y-5">
            <input type="hidden" name="business_type" id="business_type" value="CORPORATE">
            <input type="hidden" name="email_verified" id="email_verified" value="0">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Store Name</label>
                    <input type="text" name="store_name" required class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Owner Name</label>
                    <input type="text" name="owner_name" required class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">ID</label>
                    <input type="text" name="username" required class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Email Verification</label>
                <div class="flex gap-2">
                    <input type="email" id="email" name="owner_email" placeholder="example@company.com" required class="flex-1 p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold">
                    <button type="button" onclick="sendCode()" class="bg-slate-800 text-white px-4 rounded-xl text-xs font-bold whitespace-nowrap hover:bg-slate-700">인증번호 전송</button>
                </div>
            </div>

            <div id="verify_section" class="hidden flex gap-2">
                <input type="text" id="verify_code" placeholder="인증번호 6자리" class="flex-1 p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold">
                <button type="button" onclick="checkCode()" class="bg-violet-600 text-white px-4 rounded-xl text-xs font-bold whitespace-nowrap hover:bg-violet-500">확인</button>
            </div>

            <div id="biz_no_field">
                <label class="block text-xs font-bold text-slate-500 mb-1">Business License No.</label>
                <input type="text" name="biz_no" placeholder="000-00-00000" class="w-full p-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-bold">
            </div>

            <button type="submit" name="register_store" id="submit_btn" disabled class="w-full py-4 bg-slate-300 text-white rounded-2xl font-black text-sm uppercase transition-colors cursor-not-allowed mt-6">
                Complete Registration
            </button>
        </form>
    </div>

    <script>
    function setType(type) {
        document.getElementById('business_type').value = type;
        document.querySelectorAll('.type-btn').forEach(btn => {
            btn.classList.remove('bg-white', 'shadow-sm', 'text-slate-800');
            btn.classList.add('text-slate-400');
        });
        event.target.classList.add('bg-white', 'shadow-sm', 'text-slate-800');
        event.target.classList.remove('text-slate-400');

        // 개인은 사업자번호 숨김
        document.getElementById('biz_no_field').style.display = (type === 'PERSONAL') ? 'none' : 'block';
    }

    function sendCode() {
        const email = $('#email').val();
        if(!email) { alert('이메일을 입력하세요.'); return; }
        
        $.post('store_register.php', { action: 'send_code', email: email }, function(res) {
            alert(res.message);
            if(res.status === 'success') $('#verify_section').removeClass('hidden');
        });
    }

    function checkCode() {
        const email = $('#email').val();
        const code = $('#verify_code').val();
        
        $.post('store_register.php', { action: 'verify_code', email: email, code: code }, function(res) {
            if(res.status === 'success') {
                alert('인증되었습니다.');
                $('#email_verified').val('1');
                $('#email').prop('readonly', true);
                $('#verify_section').addClass('hidden');
                $('#submit_btn').prop('disabled', false).removeClass('bg-slate-300 cursor-not-allowed').addClass('bg-slate-900 hover:bg-slate-800 cursor-pointer');
            } else {
                alert(res.message);
            }
        });
    }
    </script>
</body>
</html>