<?php
// admin_menu_format_edit.php - 본사: 메뉴 포맷(업종) 추가/수정 + 리스트별 템플릿으로 포맷 만들기
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') {
    echo "<script>alert('본사 관리자 전용입니다.'); location.href='login.php';</script>"; exit;
}
include 'common.php';

// 리스트별 메뉴형식 템플릿: 식당 특성에 맞는 주문 방식(옵션 그룹·항목) 포함
// 구조: [포맷명, 설명, 카테고리[], 옵션그룹[]] / 옵션그룹: [그룹명, 필수여부, 최대선택수, 항목들[이름,가격]]
$format_templates = [
    'korean' => [
        '한식당', '한식당 업종용 메뉴 포맷',
        ['메인요리', '찌개/국', '밥/면', '반찬/사이드', '음료'],
        [
            ['밥 양', 0, 1, [['보통', 0], ['공기 추가', 1000]]],
            ['매운맛', 0, 1, [['안맵게', 0], ['보통', 0], ['맵게', 0]]],
        ],
    ],
    'western' => [
        '양식당', '양식당 업종용 메뉴 포맷 (스테이크 굽기·소스 선택)',
        ['스테이크/메인', '파스타/리조또', '샐러드', '수프', '음료/와인'],
        [
            ['굽기 선택', 1, 1, [['레어', 0], ['미디엄레어', 0], ['미디엄', 0], ['미디엄웰', 0], ['웰던', 0]]],
            ['소스 선택', 0, 1, [['블랙페퍼', 0], ['와인소스', 0], ['버터갈릭', 0]]],
        ],
    ],
    'pizza' => [
        '피자음식점', '피자 전문점 (추가 토핑·사이즈 선택)',
        ['피자', '사이드', '음료'],
        [
            ['추가 토핑', 0, 5, [['치즈 추가', 2000], ['페퍼로니', 1500], ['올리브', 1000], ['버섯', 1000], ['양파', 500]]],
            ['사이즈', 1, 1, [['보통', 0], ['큰 것', 3000], ['패밀리', 6000]]],
        ],
    ],
    'mala' => [
        '마라탕음식점', '마라탕 전문점 (맵기·추가 토핑)',
        ['마라탕', '꿔바로우/요리', '음료'],
        [
            ['맵기', 1, 1, [['안맵게', 0], ['보통', 0], ['맵게', 0], ['특맵', 0]]],
            ['추가 토핑', 0, 5, [['당면', 2000], ['우동면', 1500], ['숙주', 1000], ['청경채', 1000], ['배추', 1000]]],
        ],
    ],
    'chinese' => [
        '중국음식점', '중국음식점 업종용 메뉴 포맷',
        ['메인요리', '면/밥', '만두/간식', '음료'],
        [
            ['맵기', 0, 1, [['안맵게', 0], ['보통', 0], ['맵게', 0]]],
            ['면 종류', 0, 1, [['칼국수', 0], ['우동', 0], ['라면', 0]]],
        ],
    ],
    'massage' => [
        '마사지샵', '마사지샵 서비스 포맷 (시술 시간 선택)',
        ['마사지', '페이셜', '기타시술'],
        [
            ['시술 시간', 1, 1, [['60분', 0], ['90분', 5000], ['120분', 10000]]],
        ],
    ],
    'pharmacy' => [
        '약국', '약국 업종용 포맷',
        ['의약품', '건강식품', '생활용품'],
        [
            ['수량', 0, 1, [['1개', 0], ['2개', 0], ['3개 이상', 0]]],
        ],
    ],
    'sushi' => [
        '일본스시집', '일본 스시/초밥 전문점 (와사비·밥 양)',
        ['초밥/스시', '회', '라멘/우동', '사이드', '음료'],
        [
            ['와사비', 0, 1, [['보통', 0], ['조금', 0], ['많이', 0], ['없음', 0]]],
            ['밥 양', 0, 1, [['보통', 0], ['적게', 0], ['많이', 0]]],
        ],
    ],
    'beer' => [
        '비어가든', '비어가든/호프 (사이즈 선택)',
        ['맥주', '안주', '음료'],
        [
            ['사이즈', 0, 1, [['500cc', 0], ['1L', 2000], ['피쳐', 5000]]],
        ],
    ],
    'steak' => [
        '스테이크하우스', '스테이크하우스 (굽기·소스 선택)',
        ['스테이크', '사이드', '수프/샐러드', '음료'],
        [
            ['굽기 선택', 1, 1, [['레어', 0], ['미디엄레어', 0], ['미디엄', 0], ['미디엄웰', 0], ['웰던', 0]]],
            ['소스 선택', 0, 1, [['블랙페퍼', 0], ['와인소스', 0], ['버터갈릭', 0]]],
        ],
    ],
    'buffet' => [
        '부페식당', '부페(뷔페) 식당 업종용 메뉴 포맷',
        ['냉찬', '열찬', '디저트', '음료'],
        [], // 부페는 인원/시간 등 별도 처리 가능
    ],
    'coffee' => [
        '커피숍', '커피숍/카페 (사이즈·샷·시럽·온도)',
        ['커피', '음료', '디저트', '푸드'],
        [
            ['사이즈', 1, 1, [['톨', 0], ['그란데', 500], ['벤티', 1000]]],
            ['샷 추가', 0, 3, [['샷 1개', 500], ['샷 2개', 1000], ['샷 3개', 1500]]],
            ['시럽/토핑', 0, 3, [['바닐라', 500], ['카라멜', 500], ['헤이즐넛', 500], ['휘핑크림', 500]]],
            ['온도', 1, 1, [['핫', 0], ['아이스', 0], ['블렌디드', 0]]],
        ],
    ],
    'bakery' => [
        '베이커리샵', '베이커리/빵집 (수량·포장·컷팅)',
        ['빵', '케이크', '쿠키/과자', '음료'],
        [
            ['수량', 0, 1, [['1개', 0], ['2개', 0], ['3개', 0], ['4개 이상', 0]]],
            ['포장/매장', 0, 1, [['포장', 0], ['매장', 0]]],
            ['컷팅', 0, 1, [['컷팅 안 함', 0], ['4등분', 0], ['6등분', 0], ['8등분', 0]]],
        ],
    ],
    'drivethru' => [
        '드라이브스루', '드라이브스루/패스트푸드 (세트·사이즈)',
        ['버거/메인', '사이드', '음료', '디저트'],
        [
            ['세트 여부', 1, 1, [['단품', 0], ['세트', 1500], ['라지세트', 2500]]],
            ['사이즈', 0, 1, [['레귤러', 0], ['라지', 500], ['점보', 1000]]],
            ['추가 옵션', 0, 3, [['치즈 추가', 500], ['베이컨 추가', 1000], ['세트 음료 업그레이드', 500]]],
        ],
    ],
    'shabushabu' => [
        '샤브샤브점', '샤브샤브 전문점 (국물·밥/국수·추가)',
        ['샤브샤브', '육류/해산물', '밥/면', '사이드', '음료'],
        [
            ['국물/맵기', 1, 1, [['얼큰', 0], ['순한', 0], ['매운', 0], ['청국장', 0]]],
            ['밥/국수', 0, 1, [['밥', 0], ['우동', 0], ['당면', 0], ['라면', 0], ['없음', 0]]],
            ['추가 고기/해산물', 0, 3, [['소고기 추가', 3000], ['돼지고기 추가', 2000], ['해산물 추가', 4000]]],
        ],
    ],
];

// 템플릿으로 포맷 생성 (포맷 + 카테고리 + 업종별 옵션 그룹·옵션 항목 일괄 생성)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_from_template') {
    $template_key = $_POST['template'] ?? '';
    if (!isset($format_templates[$template_key])) {
        echo "<script>alert('잘못된 템플릿입니다.'); location.href='admin_menu_format_edit.php';</script>"; exit;
    }
    $t = $format_templates[$template_key];
    $format_name = $t[0];
    $format_desc = $t[1];
    $category_names = $t[2];
    $option_groups_data = $t[3] ?? [];
    $store_id = 1;
    try {
        $chk = $pdo->prepare("SELECT id FROM menu_formats WHERE name = ?");
        $chk->execute([$format_name]);
        $existing = $chk->fetch();
        if ($existing) {
            $format_id = (int)$existing['id'];
            $msg = "포맷 '{$format_name}'은 이미 있습니다. 카테고리·옵션만 추가합니다.";
        } else {
            $pdo->prepare("INSERT INTO menu_formats (name, description, sort_order, is_active) VALUES (?, ?, 0, 1)")
                ->execute([$format_name, $format_desc]);
            $format_id = (int)$pdo->lastInsertId();
            $msg = "포맷 '{$format_name}'과 카테고리·주문옵션(업종형식)이 생성되었습니다.";
        }
        // 카테고리
        $ins_cat = $pdo->prepare("INSERT INTO categories (menu_format_id, category_name, sort_order) VALUES (?, ?, ?)");
        $ins_trans = $pdo->prepare("INSERT INTO category_translations (category_id, lang_code, category_name) VALUES (?, 'ko', ?)");
        $existing_cats = [];
        try {
            $sc = $pdo->prepare("SELECT category_name FROM categories WHERE menu_format_id = ?");
            $sc->execute([$format_id]);
            while ($r = $sc->fetch(PDO::FETCH_ASSOC)) $existing_cats[$r['category_name']] = true;
        } catch (PDOException $e) {}
        $sort = 0;
        foreach ($category_names as $cname) {
            if (isset($existing_cats[$cname])) continue;
            $ins_cat->execute([$format_id, $cname, $sort]);
            $cid = (int)$pdo->lastInsertId();
            $ins_trans->execute([$cid, $cname]);
            $sort += 10;
        }
        // 옵션 그룹 + 옵션 항목 (식당 특성에 맞는 주문 방식)
        $chk_grp = $pdo->prepare("SELECT id FROM option_groups WHERE menu_format_id = ? AND group_name_ko = ?");
        $ins_grp = $pdo->prepare("INSERT INTO option_groups (menu_format_id, store_id, group_name_ko, is_required, max_select) VALUES (?, ?, ?, ?, ?)");
        $ins_item = $pdo->prepare("INSERT INTO option_items (group_id, item_name_ko, price_dinein, price_pickup, price_delivery) VALUES (?, ?, ?, ?, ?)");
        foreach ($option_groups_data as $grp) {
            $grp_name = $grp[0];
            $is_required = (int)$grp[1];
            $max_select = (int)$grp[2];
            $items = $grp[3] ?? [];
            $chk_grp->execute([$format_id, $grp_name]);
            if ($chk_grp->fetch()) continue; // 이미 같은 이름 그룹 있으면 스킵
            $ins_grp->execute([$format_id, $store_id, $grp_name, $is_required, $max_select]);
            $group_id = (int)$pdo->lastInsertId();
            foreach ($items as $it) {
                $price = (int)($it[1] ?? 0);
                $ins_item->execute([$group_id, $it[0], $price, $price, $price]);
            }
        }
        $admin_id = (int)($_SESSION['admin_id'] ?? 0);
        $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ('id_' . $admin_id);
        log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_menu_format_edit', 'create', 'menu_format', (string)$format_id, "메뉴 포맷 템플릿 생성: {$format_name} (ID {$format_id})");
        echo "<script>alert('" . addslashes($msg) . "'); location.href='admin_menu_by_format.php?format_id=" . $format_id . "';</script>"; exit;
    } catch (PDOException $e) {
        $err = $e->getMessage();
        if (strpos($err, 'store_id') !== false || strpos($err, 'option_groups') !== false) {
            try {
                $chk_grp2 = $pdo->prepare("SELECT id FROM option_groups WHERE menu_format_id = ? AND group_name_ko = ?");
                $ins_grp2 = $pdo->prepare("INSERT INTO option_groups (menu_format_id, group_name_ko, is_required, max_select) VALUES (?, ?, ?, ?)");
                $ins_item2 = $pdo->prepare("INSERT INTO option_items (group_id, item_name_ko, price_dinein, price_pickup, price_delivery) VALUES (?, ?, ?, ?, ?)");
                foreach ($option_groups_data as $grp) {
                    $chk_grp2->execute([$format_id, $grp[0]]);
                    if ($chk_grp2->fetch()) continue;
                    $ins_grp2->execute([$format_id, $grp[0], (int)$grp[1], (int)$grp[2]]);
                    $group_id = (int)$pdo->lastInsertId();
                    foreach ($grp[3] ?? [] as $it) {
                        $price = (int)($it[1] ?? 0);
                        $ins_item2->execute([$group_id, $it[0], $price, $price, $price]);
                    }
                }
                $admin_id = (int)($_SESSION['admin_id'] ?? 0);
                $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ('id_' . $admin_id);
                log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_menu_format_edit', 'create', 'menu_format', (string)$format_id, "메뉴 포맷 템플릿 생성: {$format_name} (ID {$format_id})");
                echo "<script>alert('" . addslashes($msg) . "'); location.href='admin_menu_by_format.php?format_id=" . $format_id . "';</script>"; exit;
            } catch (PDOException $e2) {
                $err = $e2->getMessage();
            }
        }
        echo "<script>alert('저장 실패: " . addslashes($err) . "');</script>";
    }
}

$id = (int)($_GET['id'] ?? 0);
$row = null;
if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM menu_formats WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $row = null; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'create_from_template')) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    if ($name === '') {
        echo "<script>alert('포맷명을 입력해 주세요.');</script>";
    } else {
        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE menu_formats SET name=?, description=?, sort_order=?, is_active=? WHERE id=?")
                    ->execute([$name, $description, $sort_order, $is_active, $id]);
                $admin_id = (int)($_SESSION['admin_id'] ?? 0);
                $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ('id_' . $admin_id);
                log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_menu_format_edit', 'update', 'menu_format', (string)$id, "메뉴 포맷 수정: {$name} (ID {$id})");
                echo "<script>alert('수정되었습니다.'); location.href='admin_menu_format_list.php';</script>"; exit;
            } else {
                $pdo->prepare("INSERT INTO menu_formats (name, description, sort_order, is_active) VALUES (?,?,?,?)")
                    ->execute([$name, $description, $sort_order, $is_active]);
                $new_id = $pdo->lastInsertId();
                $admin_id = (int)($_SESSION['admin_id'] ?? 0);
                $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? ('id_' . $admin_id);
                log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_menu_format_edit', 'create', 'menu_format', (string)$new_id, "메뉴 포맷 등록: {$name} (ID {$new_id})");
                echo "<script>alert('추가되었습니다.'); location.href='admin_menu_format_list.php';</script>"; exit;
            }
        } catch (PDOException $e) {
            echo "<script>alert('저장 실패: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id ? '포맷 수정' : '포맷 추가'; ?> - Alrira HQ</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen p-6">
    <div class="max-w-[96rem] mx-auto">
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-black text-slate-800 uppercase italic"><?php echo $id ? '메뉴 포맷 수정' : '메뉴 포맷 추가'; ?></h1>
            <a href="admin_menu_format_list.php" class="text-sm font-bold text-slate-500 hover:text-slate-700">← 목록</a>
        </header>

        <?php if ($id === 0): ?>
        <div class="mb-8 p-6 rounded-2xl bg-violet-50 border border-violet-200">
            <h2 class="text-lg font-black text-slate-800 mb-2">식당 특성에 맞는 메뉴형식 포맷 만들기</h2>
            <p class="text-sm text-slate-600 mb-4">업종을 선택하면 포맷·카테고리와 함께 <strong>주문 방식(옵션)</strong>이 한 번에 생성됩니다. 예: 피자·마라탕은 추가 토핑, 스테이크/양식은 굽기·소스 선택 등.</p>
            <form method="POST" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="action" value="create_from_template">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">템플릿(업종)</label>
                    <select name="template" required class="p-3 rounded-xl border border-slate-200 bg-white min-w-[240px]">
                        <option value="">선택</option>
                        <?php
                        $template_labels = [
                            'korean' => '한식당 (밥양·매운맛)',
                            'western' => '양식당 (굽기·소스)',
                            'pizza' => '피자 (추가 토핑·사이즈)',
                            'mala' => '마라탕 (맵기·추가 토핑)',
                            'chinese' => '중국음식 (맵기·면종류)',
                            'massage' => '마사지샵 (시술시간)',
                            'pharmacy' => '약국 (수량)',
                            'sushi' => '일본스시 (와사비·밥양)',
                            'beer' => '비어가든 (사이즈)',
                            'steak' => '스테이크하우스 (굽기·소스)',
                            'buffet' => '부페식당',
                            'coffee' => '커피숍 (사이즈·샷·시럽·온도)',
                            'bakery' => '베이커리샵 (수량·포장·컷팅)',
                            'drivethru' => '드라이브스루 (세트·사이즈)',
                            'shabushabu' => '샤브샤브점 (국물·밥/국수·추가)',
                        ];
                        foreach ($format_templates as $key => $t):
                            $label = $template_labels[$key] ?? $t[0];
                            $optCnt = isset($t[3]) ? count($t[3]) : 0;
                        ?>
                            <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?><?php if ($optCnt) echo ' — 옵션 ' . $optCnt . '종'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-6 py-3 rounded-xl font-black bg-violet-500 text-white hover:bg-violet-600">이 템플릿으로 포맷 생성</button>
            </form>
        </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-2xl shadow border border-slate-100 p-8 space-y-6">
            <div>
                <label class="block font-bold text-slate-700 mb-2">포맷명(업종) *</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($row['name'] ?? ''); ?>" placeholder="예: 카페, 한식당, 패스트푸드" class="w-full p-4 rounded-xl border border-slate-200">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-2">설명</label>
                <textarea name="description" rows="3" placeholder="업종/포맷 설명" class="w-full p-4 rounded-xl border border-slate-200"><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-2">정렬 순서 (작을수록 먼저)</label>
                <input type="number" name="sort_order" value="<?php echo (int)($row['sort_order'] ?? 0); ?>" class="w-32 p-3 rounded-xl border border-slate-200">
            </div>
            <div>
                <label class="flex items-center gap-2 font-bold text-slate-700">
                    <input type="checkbox" name="is_active" value="1" <?php echo (($row['is_active'] ?? 1) ? 'checked' : ''); ?>>
                    활성 (가맹점에 할당 가능)
                </label>
            </div>
            <button type="submit" class="w-full py-4 bg-sky-500 text-white rounded-2xl font-black hover:bg-sky-600">저장</button>
        </form>
    </div>
</body>
</html>
