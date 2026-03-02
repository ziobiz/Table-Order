<?php
// admin_gift_card.php - 기프트카드 발급 (본사)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('최고관리자만 접근 가능합니다.'); location.href='login.php';</script>";
    exit;
}

$admin_page_title = '기프트카드 발급';
$admin_page_subtitle = '선물권 발급 · 잔액 조회';
$header_locale = 'ko';
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? ('id_' . $admin_id);
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$admin_login_at = (int)($_SESSION['admin_login_at'] ?? time());
$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    include 'admin_header.php';
}

// 발급 처리
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_gift'])) {
    $amount = (int)($_POST['amount'] ?? 0);
    $quantity = max(1, min(500, (int)($_POST['quantity'] ?? 1)));
    $store_id = !empty($_POST['store_id']) ? (int)$_POST['store_id'] : null;
    $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

    if ($amount < 1000) {
        $msg = '<span class="text-rose-500">최소 1,000원 이상 발급해 주세요.</span>';
    } else {
        $store_label = '전체매장';
        if ($store_id) {
            $s = $pdo->prepare("SELECT store_name FROM stores WHERE id = ?");
            $s->execute([$store_id]);
            $store_label = $s->fetchColumn() ?: ('매장#' . $store_id);
        }
        $issuance_id = null;
        try {
            $ins_issuance = $pdo->prepare("INSERT INTO gift_card_issuances (issuer_id, issuer_name, store_id, store_name, amount, quantity, total_amount, expires_at, codes_sample) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins_issuance->execute([
                (int)($_SESSION['admin_id'] ?? 0),
                $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ('id_' . (int)($_SESSION['admin_id'] ?? 0)),
                $store_id, $store_label, $amount, 0, 0, $expires ?: null, ''
            ]);
            $issuance_id = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {}

        $stmt = $pdo->prepare("INSERT INTO gift_cards (code, balance, initial_balance, store_id, expires_at, issuance_id) VALUES (?, ?, ?, ?, ?, ?)");
        $issued = [];
        $failed = 0;
        for ($i = 0; $i < $quantity; $i++) {
            $code = 'GC-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
            try {
                $stmt->execute([$code, $amount, $amount, $store_id, $expires ?: null, $issuance_id]);
                $issued[] = $code;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $i--;
                    $failed++;
                    if ($failed > 10) break;
                } else {
                    $msg = '<span class="text-rose-500">오류: ' . htmlspecialchars($e->getMessage()) . '</span>';
                    break;
                }
            }
        }
        if (empty($msg) && !empty($issued)) {
            $qty = count($issued);
            $sample = implode(', ', array_slice($issued, 0, 3));
            if ($qty > 3) {
                $sample .= ' ... 외 ' . ($qty - 3) . '장';
            }
            $msg = '<span class="text-emerald-600 font-black">' . $qty . '장 발급 완료!</span>';
            $msg .= '<p class="text-xs text-slate-600 mt-2 font-bold">코드: ' . htmlspecialchars($sample) . '</p>';

            if ($issuance_id) {
                $pdo->prepare("UPDATE gift_card_issuances SET quantity=?, total_amount=?, codes_sample=? WHERE id=?")
                    ->execute([$qty, $amount * $qty, implode(', ', array_slice($issued, 0, 5)), $issuance_id]);
            }

            $admin_id = (int)($_SESSION['admin_id'] ?? 0);
            $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ('id_' . $admin_id);
            $summary = $qty . '장 발급 · ' . number_format($amount) . '원/장 · ' . $store_label . ($expires ? ' · 유효기간 ' . $expires : '');
            $details = json_encode([
                'amount' => $amount,
                'quantity' => $qty,
                'store_id' => $store_id,
                'store_name' => $store_label,
                'expires_at' => $expires,
                'codes_sample' => array_slice($issued, 0, 5)
            ], JSON_UNESCAPED_UNICODE);
            log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_gift_card', 'create', 'gift_card', (string)$qty, $summary, $details);
        }
    }
}

// 잔액 조회
$check_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_balance'])) {
    $code = trim(strtoupper(str_replace([' ', '-'], '', $_POST['gift_code'] ?? '')));
    if (strlen($code) >= 8) {
        $stmt = $pdo->prepare("SELECT * FROM gift_cards WHERE REPLACE(REPLACE(code, '-', ''), ' ', '') = ?");
        $stmt->execute([$code]);
        $check_result = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $check_result = ['error' => '코드를 입력해 주세요.'];
    }
}

$stores = $pdo->query("SELECT id, store_name FROM stores ORDER BY store_name")->fetchAll(PDO::FETCH_ASSOC);

// 발급 내역 조회 (날짜별·가맹점별·발급자별)
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
$expires_month = isset($_GET['expires_month']) ? trim($_GET['expires_month']) : '';
$expires_from = isset($_GET['expires_from']) ? trim($_GET['expires_from']) : '';
$expires_to   = isset($_GET['expires_to']) ? trim($_GET['expires_to']) : '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires_from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires_to)) {
    // 날짜 직접 지정
} elseif (preg_match('/^\d{4}-\d{2}$/', $expires_month)) {
    $expires_from = $expires_month . '-01';
    $expires_to   = $expires_month . '-' . date('t', strtotime($expires_month . '-01'));
} else {
    $expires_from = date('Y-m-01');
    $expires_to   = date('Y-m-t');
}
$filter_store = isset($_GET['store_id']) ? $_GET['store_id'] : '';
$filter_issuer = isset($_GET['issuer_name']) ? trim($_GET['issuer_name']) : '';
$search_by = isset($_GET['search_by']) && in_array($_GET['search_by'], ['issued', 'expires'], true) ? $_GET['search_by'] : 'expires';

$issuance_list = [];
$issuers = [];
try {
    $where = "1=1";
    $params = [];
    if ($search_by === 'issued') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $where .= " AND issued_at >= ?";
            $params[] = $date_from . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $where .= " AND issued_at <= ?";
            $params[] = $date_to . ' 23:59:59';
        }
    } else {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires_from)) {
            $where .= " AND expires_at >= ?";
            $params[] = $expires_from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires_to)) {
            $where .= " AND expires_at <= ?";
            $params[] = $expires_to;
        }
    }
    if ($filter_store !== '' && $filter_store !== null) {
        if ($filter_store === '0' || $filter_store === 0) {
            $where .= " AND store_id IS NULL";
        } else {
            $where .= " AND store_id = ?";
            $params[] = (int)$filter_store;
        }
    }
    if ($filter_issuer !== '') {
        $where .= " AND issuer_name LIKE ?";
        $params[] = '%' . $filter_issuer . '%';
    }
    $stmt = $pdo->prepare("SELECT * FROM gift_card_issuances WHERE $where ORDER BY issued_at DESC LIMIT 200");
    $stmt->execute($params);
    $issuance_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts_by_issuance = [];
    try {
        $ids = array_filter(array_map(function($r) { return (int)$r['id']; }, $issuance_list));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $cnt_stmt = $pdo->prepare("SELECT issuance_id, SUM(CASE WHEN balance > 0 AND status = 'ACTIVE' THEN 1 ELSE 0 END) AS remain_cnt FROM gift_cards WHERE issuance_id IN ($placeholders) GROUP BY issuance_id");
            $cnt_stmt->execute($ids);
            while ($row = $cnt_stmt->fetch(PDO::FETCH_ASSOC)) {
                $counts_by_issuance[(int)$row['issuance_id']] = ['remain' => (int)$row['remain_cnt']];
            }
        }
    } catch (PDOException $e) {}

    $issuers = $pdo->query("SELECT DISTINCT issuer_id, issuer_name FROM gift_card_issuances ORDER BY issuer_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $issuance_list = [];
    $counts_by_issuance = [];
    $issuers = [];
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>기프트카드 발급 - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap'); body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-10">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 shrink-0">
                <h1 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter">기프트카드 발급</h1>
                <span class="text-xs text-slate-400 font-bold hidden sm:inline">선물권 발급 · 잔액 조회</span>
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
    <div class="max-w-[96rem] mx-auto space-y-10">
        <?php if ($msg): ?>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 text-sm font-bold"><?php echo $msg; ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="p-8">
            <h3 class="text-sm font-black text-slate-800 uppercase mb-6">기프트카드 발급</h3>
            <form method="post" id="issue-form" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">발급 금액 (원)</label>
                    <input type="number" name="amount" min="1000" step="1000" value="10000" required
                           class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-sky-400">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">발급 수량 (장)</label>
                    <input type="number" name="quantity" min="1" max="500" value="1"
                           class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-sky-400"
                           title="해당 가맹점에 발급할 기프트카드 장수">
                    <p class="text-[10px] text-slate-400 mt-1">1 ~ 500장까지 지정 가능</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">사용 가능 매장</label>
                    <select name="store_id" class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-sky-400">
                        <option value="">전체 매장</option>
                        <?php foreach ($stores as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['store_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">유효기간 (선택)</label>
                    <input type="date" name="expires_at" class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-sky-400">
                </div>
                <button type="submit" name="issue_gift" class="w-full py-4 bg-sky-500 text-white rounded-2xl font-black uppercase text-sm hover:bg-sky-600 transition">
                    발급하기
                </button>
            </form>
            <script>
            document.getElementById('issue-form').addEventListener('submit', function(e) {
                if (!confirm('발급하겠습니까?')) {
                    e.preventDefault();
                }
            });
            </script>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="p-8">
            <h3 class="text-sm font-black text-slate-800 uppercase mb-4">발급 내역</h3>
            <p class="text-xs text-slate-400 mb-4">발급일시 · 유효일자 · 가맹점별 · 발급자별 조회</p>
            <form method="get" id="filter-form" class="space-y-4 mb-6">
                <div class="flex flex-wrap gap-4 items-center p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-xs font-black text-slate-600 uppercase">검색 기준</span>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="search_by" value="issued" <?php echo $search_by === 'issued' ? 'checked' : ''; ?> class="accent-sky-500">
                        <span class="text-sm font-bold text-slate-700">발급일 기준</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="search_by" value="expires" <?php echo $search_by === 'expires' ? 'checked' : ''; ?> class="accent-sky-500">
                        <span class="text-sm font-bold text-slate-700">유효일자 기준</span>
                    </label>
                </div>
                <?php
                $q = 'search_by=' . $search_by . '&store_id=' . urlencode($filter_store) . '&issuer_name=' . urlencode($filter_issuer);
                $last_month = date('Y-m', strtotime('first day of last month'));
                $this_month = date('Y-m');
                $next_month = date('Y-m', strtotime('first day of next month'));
                ?>
                <div id="issued-fields" class="flex flex-wrap gap-3 items-end p-4 rounded-xl border border-slate-100 <?php echo $search_by === 'issued' ? '' : 'hidden'; ?>">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">발급 시작일</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">발급 종료일</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
                    </div>
                    <div class="flex gap-2">
                        <a href="?<?php echo $q; ?>&date_from=<?php echo $last_month; ?>-01&date_to=<?php echo $last_month; ?>-<?php echo date('t', strtotime($last_month . '-01')); ?>" class="px-3 py-2 rounded-xl bg-rose-100 text-rose-700 font-bold text-xs hover:bg-rose-200 border border-rose-200 transition">지난달 기준</a>
                        <a href="?<?php echo $q; ?>&date_from=<?php echo $this_month; ?>-01&date_to=<?php echo $this_month; ?>-<?php echo date('t'); ?>" class="px-3 py-2 rounded-xl bg-sky-100 text-sky-700 font-bold text-xs hover:bg-sky-200 transition">이번달 기준</a>
                    </div>
                </div>
                <div id="expires-fields" class="p-4 rounded-xl bg-sky-50/50 border border-sky-100 <?php echo $search_by === 'expires' ? '' : 'hidden'; ?>">
                    <div class="flex flex-wrap gap-3 items-end">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">유효일자 시작</label>
                            <input type="date" name="expires_from" value="<?php echo htmlspecialchars($expires_from); ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">유효일자 종료</label>
                            <input type="date" name="expires_to" value="<?php echo htmlspecialchars($expires_to); ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">또는 검색할 달</label>
                            <input type="month" name="expires_month" value="<?php echo htmlspecialchars($expires_month ?: date('Y-m')); ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
                        </div>
                        <div class="flex gap-2">
                            <a href="?<?php echo $q; ?>&expires_from=<?php echo $last_month; ?>-01&expires_to=<?php echo $last_month; ?>-<?php echo date('t', strtotime($last_month . '-01')); ?>" class="px-3 py-2 rounded-xl bg-rose-100 text-rose-700 font-bold text-xs hover:bg-rose-200 border border-rose-200 transition">지난달 기준</a>
                            <a href="?<?php echo $q; ?>&expires_from=<?php echo $this_month; ?>-01&expires_to=<?php echo $this_month; ?>-<?php echo date('t'); ?>" class="px-3 py-2 rounded-xl bg-sky-100 text-sky-700 font-bold text-xs hover:bg-sky-200 transition">이번달 기준</a>
                            <a href="?<?php echo $q; ?>&expires_from=<?php echo $next_month; ?>-01&expires_to=<?php echo $next_month; ?>-<?php echo date('t', strtotime($next_month . '-01')); ?>" class="px-3 py-2 rounded-xl bg-emerald-50 text-emerald-700 font-bold text-xs hover:bg-emerald-100 transition">다음달 기준</a>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">가맹점</label>
                        <select name="store_id" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
                            <option value="">전체</option>
                            <option value="0" <?php echo $filter_store === '0' ? 'selected' : ''; ?>>전체매장용</option>
                            <?php foreach ($stores as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo (string)$filter_store === (string)$s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['store_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">발급자</label>
                        <input type="text" name="issuer_name" value="<?php echo htmlspecialchars($filter_issuer); ?>" placeholder="이름 검색" class="border border-slate-200 rounded-xl px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-sky-100 text-sky-700 font-black text-xs uppercase hover:bg-sky-200 border border-sky-200 transition">조회</button>
                    </div>
                </div>
            </form>
            <script>
            (function(){
                var rbIssued = document.querySelector('input[name="search_by"][value="issued"]');
                var rbExpires = document.querySelector('input[name="search_by"][value="expires"]');
                var divIssued = document.getElementById('issued-fields');
                var divExpires = document.getElementById('expires-fields');
                function toggle(){
                    if(rbIssued.checked){ divIssued.classList.remove('hidden'); divExpires.classList.add('hidden'); }
                    else { divIssued.classList.add('hidden'); divExpires.classList.remove('hidden'); }
                }
                rbIssued.addEventListener('change', toggle);
                rbExpires.addEventListener('change', toggle);
            })();
            </script>
            <?php if (empty($issuance_list)): ?>
            <p class="text-sm text-slate-500 font-bold py-8 text-center">발급 내역이 없습니다.</p>
            <?php else: ?>
            <div class="overflow-x-auto -mx-2">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="text-left py-3 px-2 font-black text-slate-600 uppercase text-xs">발급일시</th>
                            <th class="text-left py-3 px-2 font-black text-slate-600 uppercase text-xs">유효일자</th>
                            <th class="text-left py-3 px-2 font-black text-slate-600 uppercase text-xs">발급자</th>
                            <th class="text-left py-3 px-2 font-black text-slate-600 uppercase text-xs">가맹점</th>
                            <th class="text-right py-3 px-2 font-black text-slate-600 uppercase text-xs">발급 수</th>
                            <th class="text-right py-3 px-2 font-black text-slate-600 uppercase text-xs">잔존 수</th>
                            <th class="text-right py-3 px-2 font-black text-slate-600 uppercase text-xs">금액/장</th>
                            <th class="text-right py-3 px-2 font-black text-slate-600 uppercase text-xs">총액</th>
                            <th class="text-left py-3 px-2 font-black text-slate-600 uppercase text-xs">코드 샘플</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($issuance_list as $r): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                            <td class="py-3 px-2 text-slate-600 whitespace-nowrap"><?php echo htmlspecialchars($r['issued_at']); ?></td>
                            <td class="py-3 px-2 text-slate-600 whitespace-nowrap"><?php echo !empty($r['expires_at']) ? htmlspecialchars($r['expires_at']) : '무기한'; ?></td>
                            <td class="py-3 px-2 font-bold text-slate-700"><?php echo htmlspecialchars($r['issuer_name']); ?></td>
                            <td class="py-3 px-2 text-slate-600"><?php echo htmlspecialchars($r['store_name'] ?? '전체매장'); ?></td>
                            <?php $cnt = $counts_by_issuance[(int)$r['id']] ?? null; $remain = $cnt ? $cnt['remain'] : null; ?>
                            <td class="py-3 px-2 text-right font-bold"><?php echo number_format($r['quantity']); ?>장</td>
                            <td class="py-3 px-2 text-right font-bold text-sky-600"><?php echo $remain !== null ? number_format($remain) . '장' : '—'; ?></td>
                            <td class="py-3 px-2 text-right"><?php echo number_format($r['amount']); ?>원</td>
                            <td class="py-3 px-2 text-right font-bold text-sky-600"><?php echo number_format($r['total_amount']); ?>원</td>
                            <td class="py-3 px-2 text-slate-500 text-xs max-w-[180px] truncate" title="<?php echo htmlspecialchars($r['codes_sample'] ?? ''); ?>"><?php echo htmlspecialchars($r['codes_sample'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-[10px] text-slate-400 mt-4">최근 200건</p>
            <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-lg border border-slate-100 overflow-hidden">
            <div class="p-8">
            <h3 class="text-sm font-black text-slate-800 uppercase mb-6">잔액 조회</h3>
            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">기프트카드 코드</label>
                    <input type="text" name="gift_code" placeholder="GC-XXXX-XXXX" class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-sky-400">
                </div>
                <button type="submit" name="check_balance" class="w-full py-4 bg-sky-100 text-sky-700 rounded-2xl font-black uppercase text-sm hover:bg-sky-200 border border-sky-200 transition">
                    조회
                </button>
            </form>
            <?php if ($check_result !== null): ?>
            <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-slate-100">
                <?php if (isset($check_result['error'])): ?>
                <p class="text-sm font-bold text-rose-500"><?php echo htmlspecialchars($check_result['error']); ?></p>
                <?php elseif ($check_result): ?>
                <p class="text-sm font-bold text-slate-700">코드: <?php echo htmlspecialchars($check_result['code']); ?></p>
                <p class="text-lg font-black text-sky-600 mt-1">잔액: <?php echo number_format($check_result['balance']); ?>원</p>
                <p class="text-xs text-slate-400 mt-1">상태: <?php echo $check_result['status']; ?> · 유효기간: <?php echo $check_result['expires_at'] ?: '무기한'; ?></p>
                <?php else: ?>
                <p class="text-sm font-bold text-rose-500">유효하지 않은 코드입니다.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            </div>
        </div>

        <a href="admin_dashboard.php" class="block text-center py-3 text-sm font-bold text-slate-500 hover:text-slate-700">← 대시보드</a>
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
