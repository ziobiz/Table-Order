<?php
// store_setting.php - 가맹점용 KDS/알림 환경설정
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (!isset($_SESSION['store_id'])) { header("Location: login.php"); exit; }
$store_id = $_SESSION['store_id'];

// kds 관련 컬럼 없으면 한 번만 추가
try {
    $chk = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'kds_sync_order_status'");
    if ($chk && $chk->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN kds_sync_order_status TINYINT(1) NOT NULL DEFAULT 1");
    }
    // kitchen_display 전용 테마 컬럼
    $chk2 = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'kds_kitchen_theme'");
    if ($chk2 && $chk2->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN kds_kitchen_theme VARCHAR(50) DEFAULT NULL");
    }
} catch (Exception $e) { /* 컬럼 이미 있거나 권한 문제 시 무시 */ }

// 현재 설정 로드
$stmt = $pdo->prepare("SELECT store_name, kds_theme, kds_kitchen_theme, kds_alert_5, kds_alert_10, kds_alert_20, kds_alert_30, kds_sound, kds_sound_custom, use_local_status, kds_datetime_locale, kds_sync_order_status FROM stores WHERE id = ?");
$stmt->execute([$store_id]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);
if (!isset($store['kds_sync_order_status'])) $store['kds_sync_order_status'] = 1;
if (!isset($store['kds_kitchen_theme']) || !$store['kds_kitchen_theme']) {
    // 키친 디스플레이 테마가 없으면 기존 kds_theme를 기본값으로 사용
    $store['kds_kitchen_theme'] = $store['kds_theme'] ?? 'sky';
}

$themes = [
    'sky'      => 'Sky / 투명 스카이블루',
    'forest'   => 'Forest / 포레스트 그린',
    'sunset'   => 'Sunset / 따뜻한 선셋',
    'pastel'   => 'Pastel / 파스텔',
    'mono'     => 'Mono / 모노톤 그레이',
    'contrast' => 'Contrast / 다크 콘트라스트',
];

$sounds = [
    'chime1'   => 'Chime 1',
    'chime2'   => 'Chime 2',
    'bell1'    => 'Bell 1',
    'bell2'    => 'Bell 2',
    'soft1'    => 'Soft 1',
    'soft2'    => 'Soft 2',
    'alert1'   => 'Alert 1',
    'alert2'   => 'Alert 2',
    'digital1' => 'Digital 1',
    'digital2' => 'Digital 2',
    'custom'   => '직접 업로드한 음원 사용',
];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kds_theme   = $_POST['kds_theme'] ?? 'sky';
    $kds_kitchen_theme = $_POST['kds_kitchen_theme'] ?? ($store['kds_kitchen_theme'] ?? $kds_theme);
    $alert5      = (int)($_POST['kds_alert_5'] ?? 0);
    $alert10     = (int)($_POST['kds_alert_10'] ?? 0);
    $alert20     = (int)($_POST['kds_alert_20'] ?? 0);
    $alert30     = (int)($_POST['kds_alert_30'] ?? 0);
    $kds_sound   = $_POST['kds_sound'] ?? 'chime1';
    $sound_custom = $_POST['kds_sound_custom'] ?? $store['kds_sound_custom'];
    $use_local_status = isset($_POST['use_local_status']) ? (int)$_POST['use_local_status'] : 0;
    $kds_sync_order_status = isset($_POST['kds_sync_order_status']) ? (int)$_POST['kds_sync_order_status'] : 0;

    // 간단한 검증: 음수만 막고, 나머지는 자유롭게 (0이면 비활성)
    if ($alert5 < 0 || $alert10 < 0 || $alert20 < 0 || $alert30 < 0) {
        $message = '알림 시간은 0 이상 분 단위로 입력해 주세요. 0은 사용 안 함입니다.';
    } else {
        $up = $pdo->prepare("
            UPDATE stores
            SET kds_theme = ?, kds_kitchen_theme = ?, kds_alert_5 = ?, kds_alert_10 = ?, kds_alert_20 = ?, kds_alert_30 = ?, kds_sound = ?, kds_sound_custom = ?, use_local_status = ?, kds_datetime_locale = ?, kds_sync_order_status = ?
            WHERE id = ?
        ");
        $up->execute([
            $kds_theme,
            $kds_kitchen_theme,
            $alert5,
            $alert10,
            $alert20,
            $alert30,
            $kds_sound,
            $sound_custom,
            $use_local_status,
            $_POST['kds_datetime_locale'] ?? 'ko',
            $kds_sync_order_status,
            $store_id
        ]);

        $message = '설정이 저장되었습니다.';

        // 다시 로드
        $stmt->execute([$store_id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Store Settings - Alrira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pretendard:wght@400;700;900&display=swap');
        body { font-family: 'Pretendard', 'Arial', sans-serif; letter-spacing: -0.02em; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-[96rem] mx-auto">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900">KDS & 알림 설정</h1>
                <p class="text-xs text-slate-500 font-bold mt-1"><?php echo htmlspecialchars($store['store_name']); ?> 가맹점</p>
            </div>
            <a href="store_dashboard.php" class="text-xs font-bold text-sky-500 hover:underline">← 대시보드로</a>
        </header>

        <?php if($message): ?>
            <div class="mb-4 bg-emerald-50 border border-emerald-100 text-emerald-600 text-xs font-bold px-4 py-3 rounded-2xl">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="bg-white rounded-[2rem] shadow-xl border border-slate-100 p-6 space-y-6">
            <section class="space-y-3">
                <h2 class="text-sm font-black text-slate-700">주방 화면 테마</h2>
                <div class="grid grid-cols-3 gap-3">
                    <?php foreach($themes as $key => $label): 
                        $checked = ($store['kds_theme'] ?? 'sky') === $key;
                    ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="kds_theme" value="<?php echo $key; ?>" class="peer hidden" <?php echo $checked ? 'checked' : ''; ?>>
                        <div class="p-2 rounded-2xl border-2 border-slate-100 peer-checked:border-sky-500 peer-checked:bg-sky-50/70 flex items-center justify-between transition-colors">
                            <div class="text-[11px] font-bold text-slate-600 leading-snug">
                                <?php echo htmlspecialchars($label); ?>
                            </div>
                            <div class="w-8 h-8 rounded-xl bg-gradient-to-tr <?php
                                if ($key === 'sky')      echo 'from-sky-100/80 to-slate-50/60';
                                elseif ($key === 'forest')   echo 'from-emerald-100/80 to-slate-50/60';
                                elseif ($key === 'sunset')   echo 'from-rose-100/80 to-amber-50/60';
                                elseif ($key === 'pastel')   echo 'from-indigo-100/60 to-pink-100/60';
                                elseif ($key === 'mono')     echo 'from-slate-200/80 to-slate-50/60';
                                elseif ($key === 'contrast') echo 'from-slate-800/80 to-slate-600/80';
                            ?>"></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="text-[10px] text-slate-400">기본 테마는 스카이블루/그레이 입니다.</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-black text-slate-700">키친 디스플레이 테마 (kitchen_display.php)</h2>
                <p class="text-[10px] text-slate-400">주방용 KDS 화면(kitchen_display.php)에만 적용되는 별도의 색 테마입니다. 필요 시 store_orders.php와 다른 테마를 사용할 수 있습니다.</p>
                <div class="grid grid-cols-3 gap-3">
                    <?php foreach($themes as $key => $label): 
                        $kitchenChecked = ($store['kds_kitchen_theme'] ?? $store['kds_theme'] ?? 'sky') === $key;
                    ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="kds_kitchen_theme" value="<?php echo $key; ?>" class="peer hidden" <?php echo $kitchenChecked ? 'checked' : ''; ?>>
                        <div class="p-2 rounded-2xl border-2 border-slate-100 peer-checked:border-emerald-500 peer-checked:bg-emerald-50/70 flex items-center justify-between transition-colors">
                            <div class="text-[11px] font-bold text-slate-600 leading-snug">
                                <?php echo htmlspecialchars($label); ?>
                            </div>
                            <div class="w-8 h-8 rounded-xl bg-gradient-to-tr <?php
                                if ($key === 'sky')      echo 'from-sky-100/80 to-slate-50/60';
                                elseif ($key === 'forest')   echo 'from-emerald-100/80 to-slate-50/60';
                                elseif ($key === 'sunset')   echo 'from-rose-100/80 to-amber-50/60';
                                elseif ($key === 'pastel')   echo 'from-indigo-100/60 to-pink-100/60';
                                elseif ($key === 'mono')     echo 'from-slate-200/80 to-slate-50/60';
                                elseif ($key === 'contrast') echo 'from-slate-800/80 to-slate-600/80';
                            ?>"></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-black text-slate-700">경과 시간별 강조 기준 (분)</h2>
                <div class="grid grid-cols-4 gap-3 text-center">
                    <div>
                        <input type="number" name="kds_alert_5"
                               value="<?php echo (int)($store['kds_alert_5'] ?? 5); ?>"
                               class="w-full rounded-xl px-2 py-2 text-sm text-center bg-sky-50/80 border border-sky-200/70">
                    </div>
                    <div>
                        <input type="number" name="kds_alert_10"
                               value="<?php echo (int)($store['kds_alert_10'] ?? 10); ?>"
                               class="w-full rounded-xl px-2 py-2 text-sm text-center bg-amber-50/80 border border-amber-200/70">
                    </div>
                    <div>
                        <input type="number" name="kds_alert_20"
                               value="<?php echo (int)($store['kds_alert_20'] ?? 20); ?>"
                               class="w-full rounded-xl px-2 py-2 text-sm text-center bg-rose-50/80 border border-rose-200/70">
                    </div>
                    <div>
                        <input type="number" name="kds_alert_30"
                               value="<?php echo (int)($store['kds_alert_30'] ?? 30); ?>"
                               class="w-full rounded-xl px-2 py-2 text-sm text-center bg-slate-100/80 border border-slate-300/70">
                    </div>
                </div>
                <p class="text-[10px] text-slate-400">분 단위 입력, 우선 시간 설정 값이 먼저 실행됩니다. 0으로 두면 해당 색은 사용하지 않습니다.</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-black text-slate-700">상태 표시 언어</h2>
                <div class="flex items-center gap-4 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="use_local_status" value="1" <?php echo (int)($store['use_local_status'] ?? 1) === 1 ? 'checked' : ''; ?>>
                        <span class="text-slate-700">한글 상태(대기/조리중/서빙완료 등)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="use_local_status" value="0" <?php echo (int)($store['use_local_status'] ?? 1) === 0 ? 'checked' : ''; ?>>
                        <span class="text-slate-500">영문 코드(PENDING/COOKING/...</span>
                    </label>
                </div>
                <p class="text-[10px] text-slate-400">온라인 주문 화면 등에서 필요하면 영문 코드로도 사용할 수 있도록 선택 옵션을 제공합니다.</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-black text-slate-700">주문 알림 소리</h2>
                <select name="kds_sound" class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <?php foreach($sounds as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($store['kds_sound'] ?? 'chime1') === $key ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-400 mb-1">기본 10가지 소리 중 선택하거나, 아래 입력칸에 직접 업로드한 mp3 경로를 적고 "직접 업로드한 음원 사용"을 선택하세요.</p>
                <input type="text" name="kds_sound_custom" value="<?php echo htmlspecialchars($store['kds_sound_custom'] ?? ''); ?>" placeholder="예: uploads/sounds/my_store_ring.mp3" class="w-full border border-slate-200 rounded-2xl px-4 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400">
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-black text-slate-700">KDS 조리완료 ↔ 주문 상태 연동</h2>
                <p class="text-[10px] text-slate-400">KDS 화면에서 주문 단위로 "조리 완료"를 누를 때, 가맹점 주문 현황(store_orders)의 주문 상태까지 함께 SERVED로 바꿀지 선택합니다.</p>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="kds_sync_order_status" value="1" <?php echo (int)($store['kds_sync_order_status'] ?? 1) === 1 ? 'checked' : ''; ?> class="rounded-full">
                        <span class="text-sm font-bold text-slate-700">연동함 (조리 완료 시 주문 상태도 SERVED)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="kds_sync_order_status" value="0" <?php echo (int)($store['kds_sync_order_status'] ?? 1) === 0 ? 'checked' : ''; ?> class="rounded-full">
                        <span class="text-sm font-bold text-slate-700">연동 안 함</span>
                    </label>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-black text-slate-700">날짜/시간 표기 형식</h2>
                <p class="text-[10px] text-slate-400">KDS·store_orders 등 가맹점 화면의 날짜/시간은 여기서 설정한 형식으로 통일됩니다. 솔루션 사용 국가에 맞게 선택하고, 필요 시 수정할 수 있습니다.</p>
                <select name="kds_datetime_locale" class="w-full border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <?php
                    $dtLocale = $store['kds_datetime_locale'] ?? 'ko';
                    $localeOptions = [
                        'ko' => '한국식 (YYYY-MM-DD HH:MM)',
                        'en' => 'English (MM/DD/YYYY HH:MM)',
                        'ja' => '日本語 (YYYY/MM/DD HH:MM)',
                        'zh' => '中文 (YYYY-MM-DD HH:MM)',
                        'th' => 'ไทย (DD-MM-YYYY HH:MM)',
                        'vi' => 'Tiếng Việt (DD/MM/YYYY HH:MM)',
                        'id' => 'Bahasa Indonesia (DD-MM-YYYY HH:MM)',
                    ];
                    foreach ($localeOptions as $code => $label):
                    ?>
                        <option value="<?php echo $code; ?>" <?php echo $dtLocale === $code ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-400">7개국 표현 방식을 고려하여, 시간은 항상 24시간 형식으로 표시됩니다.</p>
            </section>

            <div class="pt-4">
                <button type="submit" class="w-full bg-slate-900 text-white py-4 rounded-[2rem] font-black text-xs uppercase tracking-widest hover:bg-sky-500 transition-all shadow-xl">
                    설정 저장
                </button>
            </div>
        </form>
    </div>
</body>
</html>

