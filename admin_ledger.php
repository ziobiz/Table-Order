<?php
// admin_ledger.php - 통합 거래 원장 (Transaction History)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';
include 'common.php';

// [보안] 본사 관리자 권한 체크
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('본사 관리자 전용 페이지입니다.'); location.href='login.php';</script>"; exit;
}

// --------------------------------------------------------------------------------
// [데이터 조회] 필터링 및 페이징
// --------------------------------------------------------------------------------
$where_clauses = [];
$params = [];

// 필터: 자산 타입
if (!empty($_GET['asset_type'])) {
    $where_clauses[] = "asset_type = ?";
    $params[] = $_GET['asset_type'];
}

// 필터: 거래 유형
if (!empty($_GET['trx_type'])) {
    $where_clauses[] = "trx_type = ?";
    $params[] = $_GET['trx_type'];
}

// 필터: 검색어 (이름, 트랜잭션 코드)
if (!empty($_GET['keyword'])) {
    $keyword = $_GET['keyword'];
    // 유저 닉네임이나 가맹점명 검색은 JOIN이 필요하므로, 간단히 trx_code만 검색하거나
    // 고급 쿼리로 작성해야 합니다. 여기서는 TRX Code 검색만 구현합니다.
    $where_clauses[] = "trx_code LIKE ?";
    $params[] = "%$keyword%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// 페이징
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 총 개수
$stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions $where_sql");
$stmt->execute($params);
$total_rows = $stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// 데이터 조회 (User, Store 정보 조인을 위해 서브쿼리나 JOIN 사용)
// 여기서는 간단하게 표시하기 위해 Sender/Receiver ID만 보여주거나, 필요시 함수로 이름 조회
$sql = "
    SELECT t.*, 
           u_send.nickname as sender_user_name, s_send.store_name as sender_store_name,
           u_recv.nickname as receiver_user_name, s_recv.store_name as receiver_store_name
    FROM transactions t
    LEFT JOIN users u_send ON t.sender_type = 'USER' AND t.sender_id = u_send.id
    LEFT JOIN stores s_send ON t.sender_type = 'STORE' AND t.sender_id = s_send.id
    LEFT JOIN users u_recv ON t.receiver_type = 'USER' AND t.receiver_id = u_recv.id
    LEFT JOIN stores s_recv ON t.receiver_type = 'STORE' AND t.receiver_id = s_recv.id
    $where_sql
    ORDER BY t.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 헬퍼 함수: 이름 표시
function getEntityName($type, $user_name, $store_name) {
    if ($type === 'HQ') return '<span class="font-black text-violet-600">HEADQUARTER</span>';
    if ($type === 'STORE') return '<span class="font-bold text-slate-700">🏢 ' . htmlspecialchars($store_name) . '</span>';
    if ($type === 'USER') return '<span class="font-bold text-slate-600">👤 ' . htmlspecialchars($user_name) . '</span>';
    return '-';
}

// 헬퍼 함수: 배지 스타일
function getTrxBadge($type) {
    switch ($type) {
        case 'ISSUE': return '<span class="bg-sky-100 text-sky-600 px-2 py-1 rounded text-[10px] font-black uppercase">Issue (적립)</span>';
        case 'REDEEM': return '<span class="bg-rose-100 text-rose-600 px-2 py-1 rounded text-[10px] font-black uppercase">Redeem (사용)</span>';
        case 'EXCHANGE': return '<span class="bg-violet-100 text-violet-600 px-2 py-1 rounded text-[10px] font-black uppercase">Exchange (교환)</span>';
        case 'BUY': return '<span class="bg-emerald-100 text-emerald-600 px-2 py-1 rounded text-[10px] font-black uppercase">Buy (구매)</span>';
        default: return '<span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-[10px] font-black uppercase">'.$type.'</span>';
    }
}

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = 'Trx Ledger';
    $admin_page_subtitle = '전체 거래 흐름 및 감사 로그';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trx Ledger - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', sans-serif; letter-spacing: -0.025em; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-6 md:p-12">
    <div class="max-w-[96rem] mx-auto space-y-8">
        <header class="flex justify-between items-end">
            <div>
                <h1 class="text-4xl font-black italic text-slate-900 uppercase tracking-tighter">Trx Ledger</h1>
                <p class="text-slate-500 text-xs font-bold mt-2 uppercase">전체 거래 흐름 및 감사 로그</p>
            </div>
            <div class="flex space-x-2">
                <button onclick="location.href='admin_dashboard.php'" class="bg-white border-2 border-slate-200 px-6 py-3 rounded-2xl text-[10px] font-black uppercase shadow-sm hover:bg-slate-50 transition-all">Back to Dashboard</button>
            </div>
        </header>
<?php endif; ?>

        <div class="max-w-[96rem] space-y-8">
        <form class="bg-white p-6 rounded-[2rem] shadow-lg border border-slate-100 flex flex-wrap gap-4 items-end">
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase">Asset Type</label>
                <select name="asset_type" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-violet-500">
                    <option value="">ALL</option>
                    <option value="SINGLE" <?php if(($_GET['asset_type']??'')=='SINGLE') echo 'selected'; ?>>Single Point</option>
                    <option value="MULTI" <?php if(($_GET['asset_type']??'')=='MULTI') echo 'selected'; ?>>Multi Point</option>
                    <option value="GLOBAL" <?php if(($_GET['asset_type']??'')=='GLOBAL') echo 'selected'; ?>>Global Point</option>
                    <option value="ME" <?php if(($_GET['asset_type']??'')=='ME') echo 'selected'; ?>>ME Coupon</option>
                    <option value="AD" <?php if(($_GET['asset_type']??'')=='AD') echo 'selected'; ?>>AD Coupon</option>
                    <option value="WE" <?php if(($_GET['asset_type']??'')=='WE') echo 'selected'; ?>>WE Coupon</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase">Trx Type</label>
                <select name="trx_type" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-violet-500">
                    <option value="">ALL</option>
                    <option value="ISSUE" <?php if(($_GET['trx_type']??'')=='ISSUE') echo 'selected'; ?>>Issue (적립)</option>
                    <option value="REDEEM" <?php if(($_GET['trx_type']??'')=='REDEEM') echo 'selected'; ?>>Redeem (사용)</option>
                    <option value="EXCHANGE" <?php if(($_GET['trx_type']??'')=='EXCHANGE') echo 'selected'; ?>>Exchange (교환)</option>
                </select>
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase">Search Code</label>
                <input type="text" name="keyword" value="<?php echo htmlspecialchars($_GET['keyword']??''); ?>" placeholder="Transaction ID..." class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-violet-500 w-full">
            </div>
            <button type="submit" class="bg-slate-900 text-white px-6 py-2.5 rounded-xl text-xs font-bold shadow-lg hover:bg-slate-800 transition-all">FILTER</button>
        </form>

        <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-black tracking-widest border-b border-slate-100">
                    <tr>
                        <th class="p-6">Time / Code</th>
                        <th class="p-6">Type</th>
                        <th class="p-6">From (Sender)</th>
                        <th class="p-6 text-center">Flow</th>
                        <th class="p-6">To (Receiver)</th>
                        <th class="p-6 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="text-xs font-bold text-slate-700 divide-y divide-slate-50">
                    <?php if(empty($transactions)): ?>
                    <tr><td colspan="6" class="p-10 text-center text-slate-400">No transactions found.</td></tr>
                    <?php else: foreach($transactions as $t): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-6">
                            <div class="flex flex-col">
                                <span class="text-[10px] text-slate-400"><?php echo $t['created_at']; ?></span>
                                <span class="font-mono text-[10px] text-violet-400">#<?php echo $t['trx_code']; ?></span>
                            </div>
                        </td>
                        <td class="p-6">
                            <?php echo getTrxBadge($t['trx_type']); ?>
                            <div class="mt-1 text-[9px] text-slate-400 font-bold"><?php echo $t['asset_type']; ?></div>
                        </td>
                        <td class="p-6">
                            <?php echo getEntityName($t['sender_type'], $t['sender_user_name'], $t['sender_store_name']); ?>
                        </td>
                        <td class="p-6 text-center">
                            <span class="text-slate-300">➝</span>
                        </td>
                        <td class="p-6">
                            <?php echo getEntityName($t['receiver_type'], $t['receiver_user_name'], $t['receiver_store_name']); ?>
                        </td>
                        <td class="p-6 text-right">
                            <span class="text-lg font-black <?php echo $t['trx_type']=='REDEEM' ? 'text-rose-500' : 'text-sky-500'; ?>">
                                <?php echo $t['trx_type']=='REDEEM' ? '-' : '+'; ?>
                                <?php echo number_format($t['amount']); ?>
                            </span>
                            <span class="text-[9px] text-slate-400 font-bold ml-1">
                                <?php echo in_array($t['asset_type'], ['ME','AD','WE']) ? 'CP' : 'P'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            
            <?php if($total_pages > 1): ?>
            <div class="p-6 border-t border-slate-100 flex justify-center gap-2">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all <?php echo $i==$page ? 'bg-slate-900 text-white shadow-lg' : 'bg-white text-slate-400 border border-slate-200 hover:border-slate-400'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
    </div>
</body>
</html>
<?php endif; ?>