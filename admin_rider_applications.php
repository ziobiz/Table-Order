<?php
// admin_rider_applications.php - 본사: Rider 등록 신청(본사 Rider) 목록 및 승인/반려
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? ('id_' . $admin_id);
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$admin_login_at = (int)($_SESSION['admin_login_at'] ?? time());
$header_locale = 'ko';

// 본사 Rider 신청만: store_id IS NULL AND driver_type = 'HQ'
$hq_where = "store_id IS NULL AND driver_type = 'HQ'";

// 승인 처리
if (isset($_POST['approve']) && isset($_POST['application_id'])) {
    $app_id = (int)$_POST['application_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM driver_applications WHERE id = ? AND status = 'PENDING' AND $hq_where LIMIT 1");
        $stmt->execute([$app_id]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$app) {
            echo "<script>alert('해당 신청을 찾을 수 없거나 이미 처리되었습니다.'); location.reload();</script>";
            exit;
        }
        // username 중복 확인 (drivers에 이미 있으면 승인 불가)
        $chk = $pdo->prepare("SELECT id FROM drivers WHERE username = ? LIMIT 1");
        $chk->execute([$app['username']]);
        if ($chk->fetch()) {
            echo "<script>alert('이미 사용 중인 아이디입니다. 반려 후 신청자에게 연락하세요.'); location.reload();</script>";
            exit;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO drivers (driver_type, store_id, name, last_name, first_name, address, phone, birth_date, email, id_document_path, tax_id, username, password, is_active)
                VALUES ('HQ', NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                $app['name'] ?: trim(($app['last_name'] ?? '') . ' ' . ($app['first_name'] ?? '')),
                $app['last_name'] ?? null,
                $app['first_name'] ?? null,
                $app['address'] ?? null,
                $app['phone'] ?? null,
                $app['birth_date'] ?? null,
                $app['email'] ?? null,
                $app['id_document_path'] ?? null,
                $app['tax_id'] ?? null,
                $app['username'],
                $app['password']
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false || strpos($e->getMessage(), 'last_name') !== false) {
                $pdo->prepare("
                    INSERT INTO drivers (driver_type, store_id, name, phone, username, password, is_active)
                    VALUES ('HQ', NULL, ?, ?, ?, ?, 1)
                ")->execute([
                    $app['name'] ?: trim(($app['last_name'] ?? '') . ' ' . ($app['first_name'] ?? '')),
                    $app['phone'] ?? null,
                    $app['username'],
                    $app['password']
                ]);
            } else {
                throw $e;
            }
        }
        $pdo->prepare("UPDATE driver_applications SET status = 'APPROVED', updated_at = NOW() WHERE id = ?")->execute([$app_id]);
        $pdo->commit();
        log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_rider_applications', 'approve', 'driver_application', $app_id, "본사 Rider 신청 승인: {$app['name']} ({$app['username']}, 신청 ID {$app_id})");
        echo "<script>alert('승인되었습니다. 본사 Rider 계정이 활성화되었습니다.'); location.reload();</script>";
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>alert('처리 중 오류: " . addslashes($e->getMessage()) . "'); location.reload();</script>";
        exit;
    }
}

// 반려 처리
if (isset($_POST['reject']) && isset($_POST['application_id'])) {
    $app_id = (int)$_POST['application_id'];
    $stmt = $pdo->prepare("UPDATE driver_applications SET status = 'REJECTED', updated_at = NOW() WHERE id = ? AND status = 'PENDING' AND $hq_where");
    $stmt->execute([$app_id]);
    if ($stmt->rowCount()) {
        log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_rider_applications', 'reject', 'driver_application', $app_id, "본사 Rider 신청 반려: 신청 ID {$app_id}");
        echo "<script>alert('반려 처리되었습니다.'); location.reload();</script>";
    } else {
        echo "<script>alert('이미 처리된 신청이거나 찾을 수 없습니다.'); location.reload();</script>";
    }
    exit;
}

$tab = $_GET['tab'] ?? 'pending';
$status_where = $tab === 'approved' ? "status = 'APPROVED'" : ($tab === 'rejected' ? "status = 'REJECTED'" : "status = 'PENDING'");
$list = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM driver_applications WHERE $status_where AND $hq_where ORDER BY id DESC");
    $stmt->execute();
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = 'Rider 등록 승인';
    $admin_page_subtitle = '본사 Rider · 승인 시 로그인 가능';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rider 등록 신청 승인 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-10">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 shrink-0">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter">Rider 등록 신청 승인</h1>
                <span class="text-xs text-slate-400 font-bold hidden sm:inline">본사 Rider · 승인 시 로그인 가능</span>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-4 sm:gap-6 text-xs font-bold">
                <span class="text-slate-500 whitespace-nowrap">접속자 ID <?php echo htmlspecialchars($admin_username); ?> · <?php echo htmlspecialchars($admin_name); ?></span>
                <span id="current-datetime" class="text-slate-600 whitespace-nowrap">—</span>
                <span class="text-slate-500 whitespace-nowrap">머문 <span id="elapsed-time">0분 0초</span></span>
                <button type="button" onclick="location.href='admin_dashboard.php'" class="bg-white border-2 border-slate-200 px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all shrink-0">Back to Dashboard</button>
                <a href="logout.php" class="text-rose-500 hover:underline whitespace-nowrap shrink-0">Logout</a>
            </div>
        </header>
<?php endif; ?>

        <div class="max-w-[96rem] space-y-10">
        <div class="flex gap-2 mb-6 border-b border-slate-100">
            <a href="?tab=pending" class="px-4 py-2 text-sm font-bold rounded-t-xl <?php echo $tab === 'pending' ? 'bg-white border border-sky-100 border-b-0 text-sky-600' : 'text-slate-500 hover:text-slate-700'; ?>">대기</a>
            <a href="?tab=approved" class="px-4 py-2 text-sm font-bold rounded-t-xl <?php echo $tab === 'approved' ? 'bg-white border border-sky-100 border-b-0 text-emerald-600' : 'text-slate-500 hover:text-slate-700'; ?>">승인됨</a>
            <a href="?tab=rejected" class="px-4 py-2 text-sm font-bold rounded-t-xl <?php echo $tab === 'rejected' ? 'bg-white border border-sky-100 border-b-0 text-rose-600' : 'text-slate-500 hover:text-slate-700'; ?>">반려됨</a>
        </div>

        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <?php if (empty($list)): ?>
                <div class="p-12 text-center text-slate-400 text-sm">해당 상태의 본사 Rider 신청이 없습니다.</div>
            <?php else: ?>
                <ul class="divide-y divide-sky-50">
                    <?php foreach ($list as $row): ?>
                    <li class="p-5 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="font-black text-slate-800"><?php echo htmlspecialchars($row['name'] ?: trim(($row['last_name'] ?? '') . ' ' . ($row['first_name'] ?? '')) ?: '—'); ?></p>
                            <p class="text-xs text-slate-500 mt-0.5">성/이름: <?php echo htmlspecialchars($row['last_name'] ?? '—'); ?> · <?php echo htmlspecialchars($row['first_name'] ?? '—'); ?></p>
                            <p class="text-xs text-slate-500 mt-0.5">연락처 <?php echo htmlspecialchars($row['phone'] ?? '—'); ?> · 이메일 <?php echo htmlspecialchars($row['email'] ?? '—'); ?></p>
                            <p class="text-xs text-slate-400 mt-0.5">ID: <?php echo htmlspecialchars($row['username']); ?> · 주소 <?php echo htmlspecialchars(mb_substr($row['address'] ?? '—', 0, 40)); ?><?php echo mb_strlen($row['address'] ?? '') > 40 ? '…' : ''; ?></p>
                            <?php if (!empty($row['id_document_path'])): ?>
                            <p class="text-[10px] mt-1"><a href="<?php echo htmlspecialchars($row['id_document_path']); ?>" target="_blank" class="text-sky-600 hover:underline">주민등록증 등 이미지 보기</a></p>
                            <?php endif; ?>
                            <p class="text-[10px] text-slate-400 mt-1">신청일: <?php echo htmlspecialchars($row['created_at'] ?? ''); ?></p>
                        </div>
                        <?php if ($row['status'] === 'PENDING'): ?>
                        <div class="flex items-center gap-2">
                            <form method="POST" class="inline" onsubmit="return confirm('이 신청을 승인하면 본사 Rider 계정이 즉시 오픈됩니다. 진행할까요?');">
                                <input type="hidden" name="application_id" value="<?php echo (int)$row['id']; ?>">
                                <button type="submit" name="approve" value="1" class="px-4 py-2 bg-sky-200 text-slate-800 text-xs font-black rounded-xl hover:bg-sky-300">승인</button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('이 신청을 반려하시겠습니까?');">
                                <input type="hidden" name="application_id" value="<?php echo (int)$row['id']; ?>">
                                <button type="submit" name="reject" value="1" class="px-4 py-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-xl border border-rose-100 hover:bg-rose-100">반려</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
    </div>
    <script>
    (function() {
        var loginAt = <?php echo $admin_login_at; ?> * 1000;
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
<?php endif; ?>
