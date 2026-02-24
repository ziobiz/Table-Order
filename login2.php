<?php
// login.php - 통합 로그인 및 보안 초기화 시스템
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

$message = "";

// 1. 일반 로그인 로직
if (isset($_POST['login'])) {
    $uname = $_POST['username'];
    $pw = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM staff_members WHERE username = ? AND is_active = 1");
    $stmt->execute([$uname]);
    $user = $stmt->fetch();

    if ($user && password_verify($pw, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['store_id'] = $user['store_id'];

        // 강제 비밀번호 변경 대상 여부 체크
        if ($user['force_password_change'] == 1) {
            $_SESSION['force_change'] = 1;
            header("Location: password_reset_force.php"); exit;
        }

        // 권한별 대시보드 이동
        if ($user['role'] === 'DELIVERY') {
            header("Location: delivery_list.php");
        } else {
            header("Location: admin_menu_list.php");
        }
        exit;
    } else {
        $message = "아이디 또는 비밀번호가 일치하지 않습니다.";
    }
}

// 2. 이메일 인증번호 요청 (OTP)
if (isset($_POST['email_reset'])) {
    $email = $_POST['target_email'];
    $stmt = $pdo->prepare("SELECT id FROM staff_members WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $otp = rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $pdo->prepare("UPDATE staff_members SET otp_code = ?, otp_expiry = ? WHERE id = ?")->execute([$otp, $expiry, $user['id']]);
        
        // mail($email, "[Alrira] 인증번호", "인증번호: $otp"); // 서버 메일 설정 필요
        $message = "인증번호가 발송되었습니다. (테스트용: $otp)";
    } else {
        $message = "등록되지 않은 이메일입니다.";
    }
}

// 3. OTP 검증 및 자동 초기화 (아이디1!)
if (isset($_POST['verify_otp'])) {
    $email = $_POST['target_email'];
    $otp = $_POST['otp_code'];
    $stmt = $pdo->prepare("SELECT * FROM staff_members WHERE email = ? AND otp_code = ? AND otp_expiry > NOW()");
    $stmt->execute([$email, $otp]);
    $user = $stmt->fetch();

    if ($user) {
        $new_temp_pw = password_hash($user['username']."1!", PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE staff_members SET password = ?, otp_code = NULL, force_password_change = 1 WHERE id = ?")->execute([$new_temp_pw, $user['id']]);
        $message = "인증 성공! 비밀번호가 [ {$user['username']}1! ]로 초기화되었습니다.";
    } else {
        $message = "인증번호가 틀렸거나 만료되었습니다.";
    }
}

// 4. 관리자 초기화 요청
if (isset($_POST['admin_request'])) {
    $uname = $_POST['target_uname'];
    $pdo->prepare("UPDATE staff_members SET reset_requested = 1 WHERE username = ?")->execute([$uname]);
    $message = "관리자에게 초기화 승인 요청을 보냈습니다.";
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Alrira Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; }</style>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-slate-900 p-10 rounded-[3rem] border border-slate-800 shadow-2xl text-center">
        <h1 class="text-3xl font-black italic text-sky-400 uppercase mb-8 tracking-tighter">Alrira</h1>
        
        <?php if($message): ?>
            <div class="bg-sky-500/10 text-sky-400 p-4 rounded-2xl text-xs font-bold mb-6"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="text" name="username" placeholder="Username" required class="w-full p-4 bg-slate-800 rounded-2xl text-white outline-none ring-1 ring-slate-700 focus:ring-sky-500 transition">
            <input type="password" name="password" placeholder="Password" required class="w-full p-4 bg-slate-800 rounded-2xl text-white outline-none ring-1 ring-slate-700 focus:ring-sky-500 transition">
            <button type="submit" name="login" class="w-full p-5 bg-sky-500 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-sky-900/20">Sign In</button>
        </form>

        <div class="mt-10 flex justify-center space-x-6">
            <button onclick="toggleBox('email-box')" class="text-[10px] text-slate-500 font-bold uppercase hover:text-white transition">Email Reset</button>
            <button onclick="toggleBox('admin-box')" class="text-[10px] text-slate-500 font-bold uppercase hover:text-white transition">Admin Request</button>
        </div>

        <div id="email-box" class="hidden mt-6 p-6 bg-slate-800 rounded-3xl border border-slate-700 space-y-3">
            <form method="POST" class="space-y-3">
                <input type="email" name="target_email" placeholder="Registered Email" class="w-full p-3 bg-slate-950 rounded-xl text-white text-xs outline-none">
                <button name="email_reset" class="w-full py-2 bg-slate-700 text-white text-[10px] font-black uppercase rounded-lg">Send OTP</button>
                <input type="text" name="otp_code" placeholder="Enter OTP" class="w-full p-3 bg-slate-950 rounded-xl text-white text-xs outline-none">
                <button name="verify_otp" class="w-full py-2 bg-sky-600 text-white text-[10px] font-black uppercase rounded-lg">Verify & Reset</button>
            </form>
        </div>

        <div id="admin-box" class="hidden mt-6 p-6 bg-slate-800 rounded-3xl border border-slate-700">
            <form method="POST" class="space-y-3">
                <input type="text" name="target_uname" placeholder="Your Username" class="w-full p-3 bg-slate-950 rounded-xl text-white text-xs outline-none">
                <button name="admin_request" class="w-full py-2 bg-slate-700 text-white text-[10px] font-black uppercase rounded-lg">Request Approval</button>
            </form>
        </div>
    </div>
    <script>function toggleBox(id){ 
        ['email-box', 'admin-box'].forEach(b => { if(b!==id) document.getElementById(b).classList.add('hidden'); });
        document.getElementById(id).classList.toggle('hidden'); 
    }</script>
</body>
</html>