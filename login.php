<?php
// login.php - 통합 로그인 (본사/가맹점/고객)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['login_type']; // admin, store, user
    $id = $_POST['username'];
    $pw = $_POST['password'];

    try {
        if ($type === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row && $row['password'] === $pw) { // 실무에선 password_verify 사용 필수
                $_SESSION['admin_role'] = 'SUPERADMIN';
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_username'] = $row['username'];
                $_SESSION['admin_name'] = $row['name'] ?? $row['username']; // admins.name 있으면 사용
                $_SESSION['admin_login_at'] = time(); // 머문 시간 계산용
                header("Location: admin_dashboard.php"); exit;
            }
        } elseif ($type === 'store') {
            $stmt = $pdo->prepare("SELECT * FROM stores WHERE username = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $pw_ok = $row && (password_verify($pw, $row['password']) || $row['password'] === $pw);
            if ($pw_ok) {
                $_SESSION['store_id'] = $row['id'];
                $_SESSION['store_name'] = $row['store_name'];
                $_SESSION['store_login_at'] = time();
                $_SESSION['store_locale'] = $row['kds_datetime_locale'] ?? 'ko'; // 국가별 날짜 형식용
                header("Location: store_dashboard.php"); exit;
            }
        } elseif ($type === 'user') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row && $row['password'] === $pw) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['nickname'] = $row['nickname'];
                header("Location: user_wallet.php"); exit;
            }
        } elseif ($type === 'rider') {
            $stmt = $pdo->prepare("SELECT * FROM riders WHERE username = ? AND (is_active = 1 OR is_active IS NULL)");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $pw_ok = $row && (
                ($row['password'] && (password_verify($pw, $row['password']) || $row['password'] === $pw))
            );
            if ($pw_ok) {
                $_SESSION['rider_id'] = $row['id'];
                $_SESSION['rider_name'] = $row['rider_name'];
                $_SESSION['store_id'] = $row['store_id'];
                header("Location: rider_dashboard.php"); exit;
            }
        } elseif ($type === 'driver') {
            $stmt = $pdo->prepare("SELECT * FROM drivers WHERE username = ? AND is_active = 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $pw_ok = $row && ($row['password'] && (password_verify($pw, $row['password']) || $row['password'] === $pw));
            if ($pw_ok) {
                $_SESSION['driver_id'] = $row['id'];
                $_SESSION['driver_type'] = $row['driver_type'];
                $_SESSION['driver_name'] = $row['name'];
                $_SESSION['store_id'] = $row['store_id'];
                header("Location: driver_dashboard.php"); exit;
            }
        }
        $error = "아이디 또는 비밀번호가 일치하지 않습니다.";
    } catch (Exception $e) {
        $error = "시스템 오류: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Login - Alrira System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard'; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden border border-slate-200">
        
        <div class="bg-slate-900 p-8 text-center">
            <h1 class="text-3xl font-black italic text-white tracking-tighter">ALRIRA</h1>
            <p class="text-[10px] text-slate-400 font-bold uppercase mt-1">Integrated Point & Coupon System</p>
        </div>

        <div class="flex border-b border-slate-100 flex-wrap">
            <button onclick="setType('user')" id="tab-user" class="flex-1 min-w-[70px] py-4 text-xs font-black uppercase text-violet-600 border-b-2 border-violet-600 bg-violet-50">Customer</button>
            <button onclick="setType('store')" id="tab-store" class="flex-1 min-w-[70px] py-4 text-xs font-black uppercase text-slate-400 hover:bg-slate-50">Partner</button>
            <button onclick="setType('rider')" id="tab-rider" class="flex-1 min-w-[70px] py-4 text-xs font-black uppercase text-slate-400 hover:bg-slate-50">본사 Rider</button>
            <button onclick="setType('driver')" id="tab-driver" class="flex-1 min-w-[70px] py-4 text-xs font-black uppercase text-slate-400 hover:bg-slate-50">가맹점 Deliver</button>
            <button onclick="setType('admin')" id="tab-admin" class="flex-1 min-w-[70px] py-4 text-xs font-black uppercase text-slate-400 hover:bg-slate-50">HQ</button>
        </div>

        <form method="POST" class="p-8 space-y-6">
            <input type="hidden" name="login_type" id="login_type" value="user">
            
            <div class="text-center mb-6">
                <span id="role-badge" class="bg-violet-100 text-violet-600 px-3 py-1 rounded-full text-[10px] font-black uppercase">Customer Login</span>
            </div>

            <?php if($error): ?>
                <div class="bg-rose-50 text-rose-500 text-xs font-bold p-3 rounded-xl text-center"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-2">Username</label>
                    <input type="text" name="username" placeholder="Enter ID" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-violet-500 transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1 ml-2">Password</label>
                    <input type="password" name="password" placeholder="Enter Password" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-violet-500 transition-all">
                </div>
            </div>

            <button type="submit" class="w-full bg-slate-900 text-white py-5 rounded-2xl font-black text-sm uppercase shadow-lg hover:bg-slate-800 transition-transform active:scale-95">
                Sign In
            </button>
            <p class="text-center text-xs text-slate-400 mt-4">
                가맹점이신가요? <a href="store_register.php" class="text-sky-600 font-bold hover:underline">온라인 입점 신청</a><br>
                Rider 등록이 필요하신가요? <a href="rider_register.php" class="text-amber-600 font-bold hover:underline">Rider 등록 신청</a>
            </p>
        </form>
    </div>

    <script>
    function setType(type) {
        document.getElementById('login_type').value = type;
        
        // 탭 스타일 초기화
        ['user', 'store', 'rider', 'driver', 'admin'].forEach(t => {
            const btn = document.getElementById('tab-' + t);
            if (btn) btn.className = "flex-1 min-w-[70px] py-4 text-xs font-black uppercase text-slate-400 hover:bg-slate-50 border-b-2 border-transparent";
        });

        // 선택된 탭 스타일 적용
        const activeBtn = document.getElementById('tab-' + type);
        let colorClass = "text-violet-600 border-violet-600 bg-violet-50";
        let badgeText = "Customer Login";
        let badgeColor = "bg-violet-100 text-violet-600";

        if(type === 'store') {
            colorClass = "text-emerald-600 border-emerald-600 bg-emerald-50";
            badgeText = "Partner Store Login";
            badgeColor = "bg-emerald-100 text-emerald-600";
        } else if(type === 'rider') {
            colorClass = "text-amber-600 border-amber-600 bg-amber-50";
            badgeText = "Rider (본사)";
            badgeColor = "bg-amber-100 text-amber-700";
        } else if(type === 'driver') {
            colorClass = "text-sky-600 border-sky-600 bg-sky-50";
            badgeText = "Deliver (가맹점)";
            badgeColor = "bg-sky-100 text-sky-700";
        } else if(type === 'admin') {
            colorClass = "text-slate-900 border-slate-900 bg-slate-100";
            badgeText = "Headquarter Admin";
            badgeColor = "bg-slate-200 text-slate-700";
        }

        activeBtn.className = "flex-1 py-4 text-xs font-black uppercase " + colorClass;
        
        const badge = document.getElementById('role-badge');
        badge.className = "px-3 py-1 rounded-full text-[10px] font-black uppercase " + badgeColor;
        badge.innerText = badgeText;
    }
    </script>
</body>
</html>