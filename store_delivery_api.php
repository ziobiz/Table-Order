<?php
// store_delivery_api.php - 가맹점: 배달앱 API 키 입력 (본사 계약 API에 키만 넣어 연동)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = (int)$_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? '';
$store_login_at = (int)($_SESSION['store_login_at'] ?? time());
$header_locale = $_SESSION['store_locale'] ?? 'ko';

$countries = [
    'KR' => '한국', 'TH' => '태국', 'JP' => '일본',
    'SG' => '싱가포르', 'VN' => '베트남', 'ID' => '인도네시아', 'IN' => '인도', 'MY' => '말레이시아'
];
$flag_url = function($code) { return 'https://flagcdn.com/w40/' . strtolower($code) . '.png'; };

// 저장
if (isset($_POST['save_credentials'])) {
    $provider_id = (int)($_POST['provider_id'] ?? 0);
    $credential_key = trim($_POST['credential_key'] ?? '');
    $credential_secret = trim($_POST['credential_secret'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($provider_id <= 0) {
        echo "<script>alert('API를 선택해 주세요.');</script>";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO store_delivery_api_credentials (store_id, provider_id, credential_key, credential_secret, is_active)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE credential_key = VALUES(credential_key), credential_secret = VALUES(credential_secret), is_active = VALUES(is_active), updated_at = NOW()
            ");
            $stmt->execute([$store_id, $provider_id, $credential_key ?: null, $credential_secret ?: null, $is_active]);
            echo "<script>alert('저장되었습니다.'); location.href='store_delivery_api.php';</script>";
            exit;
        } catch (PDOException $e) {
            echo "<script>alert('저장 실패: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

// 본사 등록 API 목록 (활성만)
$providers = [];
try {
    $providers = $pdo->query("SELECT * FROM delivery_api_providers WHERE is_active = 1 ORDER BY country_code ASC, sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 가맹점이 이미 입력한 키 (provider_id => row)
$credentials = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM store_delivery_api_credentials WHERE store_id = ?");
    $stmt->execute([$store_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $credentials[$row['provider_id']] = $row;
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>배달앱 연동 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-10">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 shrink-0">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter">배달앱 연동</h1>
                <span class="text-xs text-slate-400 font-bold hidden sm:inline">본사 계약 API · 키 입력</span>
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
            <div class="p-5 bg-slate-50 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-800 uppercase">연동 가능한 배달앱 (국가별)</h3>
                <p class="text-[10px] text-slate-500 mt-0.5">각 배달앱에서 발급받은 API Key / ID·비밀번호를 입력하세요. 본사가 계약한 API만 표시됩니다.</p>
            </div>
            <?php if (empty($providers)): ?>
            <div class="p-12 text-center text-slate-400 text-sm">본사에서 등록한 배달 API가 없습니다. 본사에 문의하세요.</div>
            <?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($providers as $p): 
                    $cred = $credentials[$p['id']] ?? null;
                    $has_secret = in_array($p['auth_type'], ['KEY_SECRET','OAUTH']);
                ?>
                <form method="POST" class="p-6 hover:bg-slate-50/50">
                    <input type="hidden" name="provider_id" value="<?php echo (int)$p['id']; ?>">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-black text-slate-800"><?php echo htmlspecialchars($p['name']); ?></span>
                                <?php if ($p['name_local']): ?><span class="text-slate-500 text-xs"><?php echo htmlspecialchars($p['name_local']); ?></span><?php endif; ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-600">
                                    <img src="<?php echo htmlspecialchars($flag_url($p['country_code'])); ?>" alt="" class="w-5 h-3.5 object-cover rounded" loading="lazy" onerror="this.remove()">
                                    <?php echo $countries[$p['country_code']] ?? $p['country_code']; ?>
                                </span>
                            </div>
                            <?php if ($p['description']): ?>
                            <p class="text-[10px] text-slate-500 mt-1"><?php echo nl2br(htmlspecialchars($p['description'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto sm:min-w-[280px]">
                            <div class="flex-1">
                                <label class="block text-[9px] font-black text-slate-400 uppercase mb-0.5">API Key / ID</label>
                                <input type="text" name="credential_key" value="<?php echo htmlspecialchars($cred['credential_key'] ?? ''); ?>" placeholder="Key 또는 ID" class="w-full p-2.5 bg-slate-50 rounded-xl border border-slate-200 text-xs font-bold outline-none focus:ring-2 focus:ring-sky-500">
                            </div>
                            <?php if ($has_secret): ?>
                            <div class="flex-1">
                                <label class="block text-[9px] font-black text-slate-400 uppercase mb-0.5">Secret / Password</label>
                                <input type="password" name="credential_secret" value="<?php echo htmlspecialchars($cred['credential_secret'] ?? ''); ?>" placeholder="Secret" class="w-full p-2.5 bg-slate-50 rounded-xl border border-slate-200 text-xs font-bold outline-none focus:ring-2 focus:ring-sky-500" autocomplete="off">
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="credential_secret" value="">
                            <?php endif; ?>
                            <div class="flex items-center gap-2 shrink-0">
                                <input type="checkbox" name="is_active" id="active_<?php echo $p['id']; ?>" value="1" <?php echo ($cred && $cred['is_active']) || !$cred ? 'checked' : ''; ?> class="w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                                <label for="active_<?php echo $p['id']; ?>" class="text-[10px] font-bold text-slate-600">연동 사용</label>
                            </div>
                            <button type="submit" name="save_credentials" class="px-4 py-2.5 bg-sky-200 text-slate-800 text-xs font-black rounded-xl hover:bg-sky-300">저장</button>
                        </div>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
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
            if (et && loginAt) { var sec = Math.max(0, Math.floor((now.getTime() - loginAt) / 1000)); et.textContent = formatElapsed(sec); }
        }
        tick();
        setInterval(tick, 1000);
    })();
    </script>
</body>
</html>
