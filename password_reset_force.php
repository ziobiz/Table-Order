<?php
// password_reset_force.php - 첫 로그인 및 초기화 후 필수 비밀번호 변경
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['force_change']) || $_SESSION['force_change'] !== 1) {
    header("Location: login.php"); exit;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw1 = $_POST['pw1'];
    $pw2 = $_POST['pw2'];

    if ($pw1 !== $pw2) {
        $error = "비밀번호가 일치하지 않습니다.";
    } elseif (strlen($pw1) < 4) {
        $error = "비밀번호는 4자 이상이어야 합니다.";
    } else {
        $hashed = password_hash($pw1, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE staff_members SET password = ?, force_password_change = 0 WHERE id = ?");
        $stmt->execute([$hashed, $_SESSION['user_id']]);
        
        session_destroy(); // 정보 갱신을 위해 재로그인 유도
        echo "<script>alert('비밀번호가 변경되었습니다. 새 비밀번호로 다시 로그인하세요.'); location.href='login.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Change Password - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-[3rem] p-12 shadow-2xl text-center">
        <h2 class="text-2xl font-black text-slate-900 uppercase italic tracking-tighter mb-4">Set New Password</h2>
        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-8 leading-loose">보안을 위해 초기 비밀번호를<br>본인의 비밀번호로 변경해야 합니다.</p>
        
        <?php if($error): ?><p class="text-rose-500 text-xs font-bold mb-6"><?php echo $error; ?></p><?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="password" name="pw1" placeholder="New Password" required class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 outline-none focus:ring-2 focus:ring-sky-500 font-bold transition-all text-center">
            <input type="password" name="pw2" placeholder="Confirm New Password" required class="w-full p-4 bg-slate-50 rounded-2xl border-0 ring-1 ring-slate-200 outline-none focus:ring-2 focus:ring-sky-500 font-bold transition-all text-center">
            <button type="submit" class="w-full p-5 bg-slate-900 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-sky-500 transition-all shadow-xl">Update & Sign In</button>
        </form>
    </div>
</body>
</html>