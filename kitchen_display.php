<?php
// kitchen_display.php - KDS (주방 디스플레이), store_id 기준 주문 표시
include 'db_config.php';
include 'common.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// store_setting.php 날짜/시간 표기 형식(kds_datetime_locale)과 동일하게 포맷 (24시간)
function formatKdsDatetime($locale, $timestamp = null) {
    $ts = $timestamp ?? time();
    switch ($locale) {
        case 'en': return date('m/d/Y H:i', $ts);
        case 'ja': return date('Y/m/d H:i', $ts);
        case 'zh': return date('Y-m-d H:i', $ts);
        case 'th':
        case 'id': return date('d-m-Y H:i', $ts);
        case 'vi': return date('d/m/Y H:i', $ts);
        case 'ko':
        default:   return date('Y-m-d H:i', $ts);
    }
}

// KDS 상태/버튼 다국어 라벨
function kdsLabel($key, $locale = 'ko') {
    $map = [
        'ko' => [
            'ok'        => 'OK',
            'cooking'   => '조리중',
            'ready'     => '준비완료',
            'all_ready' => '모두준비',
            'done'      => '조리완료',
        ],
        'en' => [
            'ok'        => 'OK',
            'cooking'   => 'Cooking',
            'ready'     => 'Ready',
            'all_ready' => 'All Ready',
            'done'      => 'Done',
        ],
        'ja' => [
            'ok'        => 'OK',
            'cooking'   => '調理中',
            'ready'     => '準備完了',
            'all_ready' => '全て準備完了',
            'done'      => '提供済み',
        ],
        'zh' => [
            'ok'        => 'OK',
            'cooking'   => '烹饪中',
            'ready'     => '準備完成',
            'all_ready' => '全部準備好',
            'done'      => '完成',
        ],
    ];
    $loc = isset($map[$locale]) ? $locale : 'ko';
    if (isset($map[$loc][$key])) {
        return $map[$loc][$key];
    }
    // 키가 없으면 한글 기본값 시도
    return $map['ko'][$key] ?? strtoupper($key);
}

// KDS는 URL에 store_id 지정 또는 로그인 세션 사용 (미지정 시 1)
$store_id = (int)($_GET['store_id'] ?? $_SESSION['store_id'] ?? 1);
if ($store_id < 1) { $store_id = 1; }

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // kds 관련 컬럼 없으면 한 번만 추가 (store_setting과 동일)
    $chk = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'kds_sync_order_status'");
    if ($chk && $chk->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN kds_sync_order_status TINYINT(1) NOT NULL DEFAULT 1");
    }
    $chk2 = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'kds_kitchen_theme'");
    if ($chk2 && $chk2->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN kds_kitchen_theme VARCHAR(50) DEFAULT NULL");
    }
    // 경과 시간 강조 사용 여부 플래그
    $chk3 = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'kds_disable_alerts'");
    if ($chk3 && $chk3->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN kds_disable_alerts TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=경과 시간별 강조 사용 안 함'");
    }
    // 주문 히스토리 보관 시간 (24/48/72시간)
    $chk4 = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'kds_history_hours'");
    if ($chk4 && $chk4->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN kds_history_hours TINYINT(3) NOT NULL DEFAULT 24 COMMENT 'KDS 히스토리 보관 시간 (24/48/72시간)'");
    }
} catch (Exception $e) { /* 무시 */ }

try {
    // 가맹점 설정: KDS 테마, 키친 디스플레이 테마, 조리완료 연동, 날짜/시간 표기 형식, KDS 모드
    $st_store = $pdo->prepare("SELECT kds_theme, kds_kitchen_theme, kds_sync_order_status, kds_datetime_locale, kds_alert_5, kds_alert_10, kds_alert_20, kds_alert_30, store_name, kds_mode, kds_disable_alerts, kds_history_hours FROM stores WHERE id = ?");
    $st_store->execute([$store_id]);
    $store_row = $st_store->fetch(PDO::FETCH_ASSOC);
    $kitchen_theme_key = $store_row['kds_kitchen_theme'] ?? ($store_row['kds_theme'] ?? 'sky');
    $kds_sync_order_status = (int)($store_row['kds_sync_order_status'] ?? 1);
    $kds_datetime_locale = $store_row['kds_datetime_locale'] ?? 'ko';
    $kds_mode = ($store_row['kds_mode'] ?? 'A') === 'B' ? 'B' : 'A';
    $kds_alert_5  = (int)($store_row['kds_alert_5'] ?? 0);
    $kds_alert_10 = (int)($store_row['kds_alert_10'] ?? 0);
    $kds_alert_20 = (int)($store_row['kds_alert_20'] ?? 0);
    $kds_alert_30 = (int)($store_row['kds_alert_30'] ?? 0);
    $kds_disable_alerts = (int)($store_row['kds_disable_alerts'] ?? 0);
    $kds_history_hours = (int)($store_row['kds_history_hours'] ?? 24);
    if (!in_array($kds_history_hours, [24,48,72], true)) {
        $kds_history_hours = 24;
    }

    // KDS 전용 테마 팔레트 (가맹점 관리 > KDS & 알림 설정 > 키친 디스플레이 테마에서 선택)
    $kds_themes = [
        'sky' => [
            'bg' => 'bg-slate-100',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-500',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-sky-400/60',
            'cardHeader' => 'bg-sky-50/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-sky-500',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-slate-200',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-sky-600',
            'btnOkBg' => 'bg-green-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-slate-300',
            'emptyText' => 'text-slate-500',
        ],
        'forest' => [
            'bg' => 'bg-emerald-50',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-600',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-emerald-400/60',
            'cardHeader' => 'bg-emerald-50/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-emerald-500',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-emerald-200',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-emerald-600',
            'btnOkBg' => 'bg-green-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-emerald-300',
            'emptyText' => 'text-slate-500',
        ],
        'sunset' => [
            'bg' => 'bg-orange-50',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-600',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-amber-400/60',
            'cardHeader' => 'bg-amber-50/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-amber-500',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-amber-200',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-amber-600',
            'btnOkBg' => 'bg-green-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-amber-300',
            'emptyText' => 'text-slate-500',
        ],
        'pastel' => [
            'bg' => 'bg-slate-50',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-500',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-indigo-300/60',
            'cardHeader' => 'bg-indigo-50/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-indigo-400',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-slate-200',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-indigo-500',
            'btnOkBg' => 'bg-green-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-slate-300',
            'emptyText' => 'text-slate-500',
        ],
        'mono' => [
            'bg' => 'bg-slate-100',
            'text' => 'text-slate-900',
            'textMuted' => 'text-slate-500',
            'card' => 'bg-white/95',
            'cardBorder' => 'border border-slate-400/50',
            'cardHeader' => 'bg-slate-100/80',
            'cardHeaderText' => 'text-slate-900',
            'btnCompleteBg' => 'bg-slate-600',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-50 border border-slate-300',
            'itemName' => 'text-slate-900',
            'itemQty' => 'text-slate-600',
            'btnOkBg' => 'bg-slate-600',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-slate-400',
            'emptyText' => 'text-slate-500',
        ],
        // Contrast: 어두운 배경 + 글자 흰색으로 가독성 확보
        'contrast' => [
            'bg' => 'bg-slate-900',
            'text' => 'text-white',
            'textMuted' => 'text-slate-300',
            'card' => 'bg-slate-800/95',
            'cardBorder' => 'border border-slate-500/50',
            'cardHeader' => 'bg-slate-700/80',
            'cardHeaderText' => 'text-white',
            'btnCompleteBg' => 'bg-emerald-500',
            'btnCompleteText' => 'text-white',
            'itemRow' => 'bg-slate-700/50 border border-slate-600',
            'itemName' => 'text-white',
            'itemQty' => 'text-emerald-300',
            'btnOkBg' => 'bg-emerald-500',
            'btnOkText' => 'text-white',
            'emptyBorder' => 'border-slate-600',
            'emptyText' => 'text-slate-300',
        ],
    ];
    $kitchen_theme = $kds_themes[$kitchen_theme_key] ?? $kds_themes['sky'];

    // 1. 단일 메뉴 아이템 조리 진행/완료 (OK 버튼): 같은 매장이면 진행 단계 변경
    if (isset($_GET['complete_item'])) {
        $target_id = (int)$_GET['complete_item'];
        $redirect = 'kitchen_display.php?store_id=' . $store_id;
        $ir = $pdo->prepare("SELECT o.store_id, oi.item_status, oi.order_id FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.id = ?");
        $ir->execute([$target_id]);
        $ir = $ir->fetch(PDO::FETCH_ASSOC);
        if ($ir && (int)($ir['store_id'] ?? 0) === $store_id) {
            $current_status = strtolower($ir['item_status'] ?? '');
            $order_id_for_item = (int)$ir['order_id'];

            if ($kds_mode === 'B') {
                // B형: OK 한 번만 허용 (PENDING → COOKING), 이후에는 상태 변경 없음
                if (!in_array($current_status, ['cooking','ready','served'], true)) {
                    $pdo->prepare("UPDATE order_items SET item_status = 'COOKING' WHERE id = ?")->execute([$target_id]);
                    if ($kds_sync_order_status === 1) {
                        $pdo->prepare("UPDATE orders SET status = 'COOKING' WHERE id = ? AND store_id = ? AND (status IS NULL OR status = '' OR status = 'pending')")->execute([$order_id_for_item, $store_id]);
                    }
                }
            } else {
                // A형: OK 클릭 시 PENDING → COOKING → READY 단계까지 처리 (SERVED/READY 에서는 변화 없음)
                if ($current_status === 'served' || $current_status === 'ready') {
                    // 이미 서빙완료 또는 준비완료된 항목은 상태 변경 없음
                } elseif ($current_status === 'cooking') {
                    // COOKING → READY(준비완료)
                    $pdo->prepare("UPDATE order_items SET item_status = 'READY' WHERE id = ?")->execute([$target_id]);
                } else {
                    // PENDING/기타 → COOKING
                    $pdo->prepare("UPDATE order_items SET item_status = 'COOKING' WHERE id = ?")->execute([$target_id]);
                }

                // 주문 상태 자동 동기화 (옵션) — A형: COOKING/READY/Served 이면 주문 상태를 COOKING 으로
                if ($kds_sync_order_status === 1) {
                    $pdo->prepare("UPDATE orders SET status = 'COOKING' WHERE id = ? AND store_id = ? AND (status IS NULL OR status = '' OR status = 'pending')")->execute([$order_id_for_item, $store_id]);
                }
            }
        } else if ($ir) {
            $redirect .= '&sync_fail=1';
        }
        header('Location: ' . $redirect);
        exit;
    }

    // 1-2. 단일 메뉴 상태 초기화(R 버튼): 해당 항목을 대기(PENDING) 상태로 되돌림
    if (isset($_GET['reset_item'])) {
        $target_id = (int)$_GET['reset_item'];
        $redirect = 'kitchen_display.php?store_id=' . $store_id;
        $ir = $pdo->prepare("SELECT o.store_id, oi.order_id FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.id = ?");
        $ir->execute([$target_id]);
        $ir = $ir->fetch(PDO::FETCH_ASSOC);
        if ($ir && (int)($ir['store_id'] ?? 0) === $store_id) {
            $pdo->prepare("UPDATE order_items SET item_status = 'PENDING' WHERE id = ?")->execute([$target_id]);
        } else if ($ir) {
            $redirect .= '&sync_fail=1';
        }
        header('Location: ' . $redirect);
        exit;
    }

    // 2-A. (A형) 주문 단위 모두 준비: 같은 매장 주문이면 모든 미서빙 항목을 READY 로 변경
    if ($kds_mode === 'A' && isset($_GET['ready_all'])) {
        $order_id = (int)$_GET['ready_all'];
        $redirect = 'kitchen_display.php?store_id=' . $store_id;
        $chk = $pdo->prepare("SELECT store_id FROM orders WHERE id = ?");
        $chk->execute([$order_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)$row['store_id'] !== $store_id) {
            $redirect .= '&sync_fail=1';
        } else {
            // 아직 서빙되지 않은 항목만 READY 로
            $pdo->prepare("UPDATE order_items SET item_status = 'READY' WHERE order_id = ? AND LOWER(item_status) <> 'served'")->execute([$order_id]);
            if ($kds_sync_order_status === 1) {
                // 주문 상태는 COOKING 으로만 올려줌 (이미 cooking/served 인 경우는 유지)
                $pdo->prepare("UPDATE orders SET status = 'COOKING' WHERE id = ? AND store_id = ? AND (status IS NULL OR status = '' OR status = 'pending')")->execute([$order_id, $store_id]);
            }
        }
        header('Location: ' . $redirect);
        exit;
    }

    // 2-B. (B형) 주문 단위 조리 완료: 모든 항목을 READY 로 처리 (조리 완료, 서빙 전 단계)
    $sync_fail = 0;
    if ($kds_mode === 'B' && isset($_GET['complete_order'])) {
        $order_id = (int)$_GET['complete_order'];
        $chk = $pdo->prepare("SELECT store_id FROM orders WHERE id = ?");
        $chk->execute([$order_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)$row['store_id'] !== $store_id) {
            $sync_fail = 1;
        } else {
            // 아직 서빙되지 않은 항목만 READY 로 (조리완료 상태)
            $pdo->prepare("UPDATE order_items SET item_status = 'READY' WHERE order_id = ? AND LOWER(item_status) <> 'served'")->execute([$order_id]);
            if ($kds_sync_order_status === 1) {
                // 주문 상태는 COOKING 으로만 올려줌 (서빙완료는 ORDER TRACKET에서 처리)
                $upd = $pdo->prepare("UPDATE orders SET status = 'COOKING' WHERE id = ? AND store_id = ? AND (status IS NULL OR status = '' OR status = 'pending')");
                $upd->execute([$order_id, $store_id]);
                if ($upd->rowCount() === 0) {
                    $sync_fail = 1;
                }
            }
        }
        $redirect = 'kitchen_display.php?store_id=' . $store_id;
        if ($sync_fail) $redirect .= '&sync_fail=1';
        header('Location: ' . $redirect);
        exit;
    }

    // 2-C. 호출 버튼 (A/B 공통): 홀 화면(order tracket)에 "주방에서 호출합니다." 알림 표시용 플래그 설정
    if (isset($_GET['call_order'])) {
        $order_id = (int)$_GET['call_order'];
        $redirect = 'kitchen_display.php?store_id=' . $store_id;
        $chk = $pdo->prepare("SELECT store_id FROM orders WHERE id = ?");
        $chk->execute([$order_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)$row['store_id'] !== $store_id) {
            $redirect .= '&sync_fail=1';
        } else {
            // orders 테이블에 kitchen_call 플래그가 있다고 가정 (마이그레이션 필요)
            $pdo->prepare("UPDATE orders SET kitchen_call = 1, kitchen_call_at = NOW() WHERE id = ? AND store_id = ?")->execute([$order_id, $store_id]);
        }
        header('Location: ' . $redirect);
        exit;
    }

    // 3. 해당 가맹점 주문 중 미완료(미서빙) 항목만 조회 (실시간 주문용)
    $sql = "SELECT o.id AS order_id, o.created_at, o.order_type,
            oi.id AS item_id, oi.quantity, oi.item_status, mt.menu_name
            FROM orders o
            -- 아직 서빙되지 않은 항목만 (소문자 served 기준)
            JOIN order_items oi ON oi.order_id = o.id AND LOWER(oi.item_status) <> 'served'
            JOIN menus m ON oi.menu_id = m.id
            JOIN menu_translations mt ON m.id = mt.menu_id AND mt.lang_code = 'ko'
            WHERE o.store_id = ? AND o.status != 'paid'
            ORDER BY o.created_at ASC, oi.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$store_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 주문 단위로 그룹 (실시간 주문용)
    $orders_active = [];
    foreach ($rows as $r) {
        $oid = $r['order_id'];
        if (!isset($orders_active[$oid])) {
            $orders_active[$oid] = [
                'order_id'   => $oid,
                'created_at' => $r['created_at'],
                'order_type' => $r['order_type'],
                'items'      => [],
            ];
        }
        $orders_active[$oid]['items'][] = [
            'item_id' => $r['item_id'],
            'menu_name' => $r['menu_name'],
            'quantity' => $r['quantity'],
            'item_status' => $r['item_status'],
        ];
    }

    // 3-1. 조리완료 리스트(서빙완료 주문 목록) - 주문 단위
    $orders_served = [];
    $sort = $_GET['sort'] ?? 'time';
    $orderByServed = 'o.updated_at ASC, o.id ASC';
    if ($sort === 'id_asc') {
        $orderByServed = 'o.id ASC';
    } elseif ($sort === 'id_desc') {
        $orderByServed = 'o.id DESC';
    } elseif ($sort === 'time') {
        $orderByServed = 'o.updated_at DESC, o.id DESC';
    }
    $sqlServed = "
        SELECT 
            o.id AS order_id,
            o.created_at,
            o.updated_at,
            o.order_type,
            GROUP_CONCAT(CONCAT(mt.menu_name, ' x', oi.quantity) ORDER BY oi.id SEPARATOR ', ') AS items_summary
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        JOIN menus m ON oi.menu_id = m.id
        JOIN menu_translations mt ON m.id = mt.menu_id AND mt.lang_code = 'ko'
        WHERE o.store_id = ?
          AND LOWER(o.status) = 'served'
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        GROUP BY o.id, o.created_at, o.updated_at, o.order_type
        ORDER BY {$orderByServed}
    ";
    $sthServed = $pdo->prepare($sqlServed);
    $sthServed->execute([$store_id, $kds_history_hours]);
    $rowsServed = $sthServed->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rowsServed as $r) {
        $orders_served[$r['order_id']] = $r;
    }

    // 실시간 주문 / 조리완료 리스트 뷰 선택
    $view = $_GET['view'] ?? 'active';
    $showServedList = ($view === 'served');
    $orders_main = $showServedList ? $orders_served : $orders_active;

    // 주문 히스토리: 주문 단위가 아니라 "메뉴 단위"로 최근 SERVED 항목 표시
    // - order_items.item_status = 'SERVED' 가 된 순간, KDS 메인 리스트에서는 사라지고
    //   여기 히스토리(오른쪽)에 한 줄씩 표시된다.
    // - R 버튼으로 복원하면 item_status 가 다시 PENDING 으로 바뀌어, 다음 새로고침 때 히스토리에서 사라진다.
    $sth = $pdo->prepare("
        SELECT 
            oi.id        AS item_id,
            oi.order_id  AS order_id,
            oi.quantity  AS quantity,
            oi.item_status,
            o.created_at,
            o.updated_at,
            mt.menu_name
        FROM order_items oi
        JOIN orders o          ON oi.order_id = o.id
        JOIN menus m           ON oi.menu_id = m.id
        JOIN menu_translations mt ON m.id = mt.menu_id AND mt.lang_code = 'ko'
        WHERE o.store_id = ?
          AND LOWER(oi.item_status) = 'served'
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ORDER BY o.updated_at DESC, oi.id DESC
        LIMIT 80
    ");
    $sth->execute([$store_id, $kds_history_hours]);
    $history_served = $sth->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "DB 접속 또는 쿼리 오류: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="7">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KITCHEN KDS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes blink-blue {
            0% { box-shadow: 0 0 0 0 rgba(59,130,246,0.7); }
            100% { box-shadow: 0 0 0 8px rgba(59,130,246,0); }
        }
        @keyframes blink-yellow {
            0% { box-shadow: 0 0 0 0 rgba(250,204,21,0.7); }
            100% { box-shadow: 0 0 0 8px rgba(250,204,21,0); }
        }
        @keyframes blink-red {
            0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.8); transform: translateY(0); }
            100% { box-shadow: 0 0 0 10px rgba(239,68,68,0); transform: translateY(-2px); }
        }
        @keyframes blink-gray {
            0% { box-shadow: 0 0 0 0 rgba(148,163,184,0.8); opacity: 0.8; }
            100% { box-shadow: 0 0 0 10px rgba(148,163,184,0); opacity: 0.4; }
        }

        .age-5  { animation: blink-blue 1s infinite alternate; }
        .age-10 { animation: blink-yellow 1.2s infinite alternate; }
        .age-20 { animation: blink-red 0.8s infinite alternate; }
        .age-30 { animation: blink-gray 1.5s infinite alternate; }
    </style>
</head>
<body class="<?php echo $kitchen_theme['bg']; ?> <?php echo $kitchen_theme['text']; ?> p-4 min-h-screen">

    <header class="flex justify-between items-center mb-6 border-b border-slate-300 pb-4 <?php echo $kitchen_theme_key === 'contrast' ? 'border-slate-600' : ''; ?>">
        <div>
            <h1 class="text-2xl font-black <?php echo $kitchen_theme_key === 'contrast' ? 'text-emerald-400' : 'text-sky-600'; ?>">KITCHEN KDS</h1>
            <p class="text-sm font-mono <?php echo $kitchen_theme['textMuted']; ?>"><?php echo formatKdsDatetime($kds_datetime_locale); ?> · Store #<?php echo $store_id; ?></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>"
               class="px-4 py-2 rounded-xl text-xs font-black <?php echo !$showServedList ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-700 border border-slate-300'; ?>">
                실시간 주문
            </a>
            <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&view=served"
               class="px-4 py-2 rounded-xl text-xs font-black <?php echo $showServedList ? 'bg-emerald-500 text-white' : 'bg-emerald-50 text-emerald-700 border border-emerald-300'; ?>">
                조리완료 리스트
            </a>
            <?php if ($showServedList): 
                $sort = $_GET['sort'] ?? 'time';
            ?>
                <div class="flex items-center gap-1 text-[11px] ml-2">
                    <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&view=served&sort=id_desc" class="px-2 py-1 rounded-full border <?php echo $sort === 'id_desc' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-300'; ?>">
                        번호↓
                    </a>
                    <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&view=served&sort=id_asc" class="px-2 py-1 rounded-full border <?php echo $sort === 'id_asc' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-300'; ?>">
                        번호↑
                    </a>
                    <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&view=served&sort=time" class="px-2 py-1 rounded-full border <?php echo $sort === 'time' ? 'bg-emerald-500 text-white border-emerald-500' : 'bg-white text-slate-600 border-slate-300'; ?>">
                        최근완료
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($_GET['sync_fail'])): ?>
        <div class="mb-4 p-3 rounded-xl bg-amber-100 border border-amber-300 text-amber-800 text-xs font-bold">
            ⚠️ 조리 완료는 처리되었으나, 주문 상태 연동(store_orders 반영)이 되지 않았습니다. 해당 주문이 현재 KDS(Store #<?php echo $store_id; ?>) 소속인지 확인하세요.
        </div>
    <?php endif; ?>
    <div class="flex gap-6">
        <!-- 왼쪽: 대기/조리 중 주문 또는 조리완료 리스트 -->
        <div class="flex-1 min-w-0">
            <?php if (empty($orders_main)): ?>
                <div class="flex flex-col items-center justify-center py-40 border-2 border-dashed rounded-3xl <?php echo $kitchen_theme['emptyBorder']; ?>">
                    <p class="text-xl font-bold <?php echo $kitchen_theme['emptyText']; ?>">
                        <?php echo $showServedList ? '조리완료된 주문이 없습니다.' : '대기 중인 주문이 없습니다.'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($orders_main as $ord): 
                        $table_label = 'Order #' . $ord['order_id'];

                        // 경과 시간(분) 계산
                        $created = new DateTime($ord['created_at']);
                        $now = new DateTime();
                        $diffMin = floor(($now->getTimestamp() - $created->getTimestamp()) / 60);
                        $ageClass = '';

                        // 가맹점별 알림 기준 시간 (0이면 비활성) + 전체 비활성 플래그
                        $thresholds = [];
                        if ($kds_disable_alerts === 0) {
                            if ($kds_alert_5  > 0) $thresholds[] = ['min' => $kds_alert_5,  'class' => 'age-5'];
                            if ($kds_alert_10 > 0) $thresholds[] = ['min' => $kds_alert_10, 'class' => 'age-10'];
                            if ($kds_alert_20 > 0) $thresholds[] = ['min' => $kds_alert_20, 'class' => 'age-20'];
                            if ($kds_alert_30 > 0) $thresholds[] = ['min' => $kds_alert_30, 'class' => 'age-30'];

                            if (!empty($thresholds)) {
                                usort($thresholds, function($a, $b) { return $a['min'] <=> $b['min']; });
                                foreach ($thresholds as $t) {
                                    if ($diffMin >= $t['min']) {
                                        $ageClass = $t['class'];
                                    } else {
                                        break;
                                    }
                                }
                            }
                        }

                        // 주문 타입별 아이콘 및 레이블 (매장/픽업/배달)
                        $typeIcon = '🍽';
                        $typeLabel = '매장';
                        $otype = $ord['order_type'] ?? 'dinein';
                        if ($otype === 'pickup') {
                            $typeIcon = '📦';
                            $typeLabel = '픽업';
                        } elseif ($otype === 'delivery') {
                            $typeIcon = '🛵';
                            $typeLabel = '배달';
                        }

                        // 주문 내 총 아이템 수 (실시간 주문일 때만 items 배열 기준)
                        $totalQty = 0;
                        if (!$showServedList && !empty($ord['items'])) {
                            foreach ($ord['items'] as $itTmp) {
                                $totalQty += (int)$itTmp['quantity'];
                            }
                        }
                    ?>
                        <div class="<?php echo $kitchen_theme['card']; ?> rounded-2xl overflow-hidden shadow-2xl <?php echo $kitchen_theme['cardBorder']; ?> <?php echo $ageClass; ?>">
                            <div class="<?php echo $kitchen_theme['cardHeader']; ?> p-4 flex justify-between items-center">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xl"><?php echo $typeIcon; ?></span>
                                        <span class="text-xs font-bold <?php echo $kitchen_theme['textMuted']; ?>"><?php echo $typeLabel; ?></span>
                                    </div>
                                    <span class="block text-2xl font-black <?php echo $kitchen_theme['cardHeaderText']; ?>"><?php echo htmlspecialchars($table_label); ?></span>
                                    <span class="ml-0 text-xs font-mono <?php echo $kitchen_theme['textMuted']; ?>">
                                        <?php echo $diffMin; ?>분 경과<?php if ($totalQty > 0): ?> · 총 <?php echo $totalQty; ?>개<?php endif; ?>
                                    </span>
                                </div>
                                <?php if (!$showServedList): ?>
                                    <div class="flex flex-col gap-2 items-end">
                                        <?php if ($kds_mode === 'A'): ?>
                                            <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&ready_all=<?php echo $ord['order_id']; ?>"
                                               class="bg-sky-500 text-white px-4 py-2 rounded-xl font-black text-sm hover:bg-sky-600">
                                                <?php echo kdsLabel('all_ready', $kds_datetime_locale); ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&complete_order=<?php echo $ord['order_id']; ?>"
                                               class="<?php echo $kitchen_theme['btnCompleteBg']; ?> <?php echo $kitchen_theme['btnCompleteText']; ?> px-4 py-2 rounded-xl font-black text-sm hover:opacity-90">
                                                <?php echo kdsLabel('all_ready', $kds_datetime_locale); ?>
                                            </a>
                                        <?php endif; ?>
                                        <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&call_order=<?php echo $ord['order_id']; ?>"
                                           class="bg-rose-500 text-white px-4 py-2 rounded-xl font-black text-xs hover:bg-rose-600">
                                            호출
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="px-3 py-1.5 rounded-full text-xs font-black bg-emerald-500 text-white">
                                        <?php echo kdsLabel('done', $kds_datetime_locale); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($showServedList): ?>
                                <!-- 조리완료 리스트: 메뉴 요약만 표시 -->
                                <div class="p-4">
                                    <p class="text-sm font-bold <?php echo $kitchen_theme['itemName']; ?>">
                                        <?php echo htmlspecialchars($ord['items_summary'] ?? ''); ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="p-4 space-y-3">
                                    <?php foreach ($ord['items'] as $it): 
                                        $st = strtoupper((string)($it['item_status'] ?? ''));
                                        // 상태별 작은 배지 스타일 (다국어)
                                        $pillKey = 'ok';
                                        if ($st === 'COOKING') {
                                            $pillKey = 'cooking';
                                        } elseif ($st === 'READY') {
                                            $pillKey = 'ready';
                                        }
                                        $pillLabel = kdsLabel($pillKey, $kds_datetime_locale);
                                        $pillClass = 'bg-emerald-500 text-white';
                                        if ($pillKey === 'cooking') {
                                            $pillClass = 'bg-amber-500 text-white';
                                        } elseif ($pillKey === 'ready') {
                                            $pillClass = 'bg-sky-500 text-white';
                                        }
                                    ?>
                                        <div class="flex justify-between items-center p-3 rounded-xl <?php echo $kitchen_theme['itemRow']; ?>">
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm font-bold <?php echo $kitchen_theme['itemName']; ?> truncate">
                                                    <?php echo htmlspecialchars($it['menu_name']); ?>
                                                </span>
                                                <span class="text-sm font-black ml-2 <?php echo $kitchen_theme['itemQty']; ?>">
                                                    x<?php echo (int)$it['quantity']; ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2 whitespace-nowrap text-[13px] font-black">
                                                <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&complete_item=<?php echo $it['item_id']; ?>"
                                                   class="<?php echo $pillClass; ?> px-4 py-2 rounded-full hover:opacity-90 min-w-[80px] text-center">
                                                    <?php echo $pillLabel; ?>
                                                </a>
                                                <?php if ($st === 'COOKING' || $st === 'READY'): ?>
                                                    <a href="kitchen_display.php?store_id=<?php echo $store_id; ?>&reset_item=<?php echo $it['item_id']; ?>"
                                                       class="px-3 py-1.5 rounded-full border border-slate-300 text-slate-700 hover:bg-slate-50">
                                                        R
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 오른쪽: 주문 히스토리 (메뉴 단위, 최근 SERVED 항목) -->
        <div class="w-72 shrink-0">
            <div class="<?php echo $kitchen_theme['card']; ?> rounded-2xl overflow-hidden shadow-xl <?php echo $kitchen_theme['cardBorder']; ?> p-4 sticky top-4">
                <h2 class="text-sm font-black <?php echo $kitchen_theme['cardHeaderText']; ?> mb-3 pb-2 border-b <?php echo $kitchen_theme_key === 'contrast' ? 'border-slate-600' : 'border-slate-200'; ?>">주문 히스토리 (메뉴)</h2>
                <?php if (empty($history_served)): ?>
                    <p class="text-xs <?php echo $kitchen_theme['emptyText']; ?>">완료된 메뉴 없음</p>
                <?php else: ?>
                    <ul class="space-y-0 max-h-[70vh] overflow-y-auto">
                        <?php $i = 0; foreach ($history_served as $h): 
                            $rowBg = ($i % 2 === 0) ? 'bg-white/80' : 'bg-slate-50/80';
                            $i++;
                            $timeOnly = date('H:i', strtotime($h['created_at']));
                        ?>
                            <li class="text-xs <?php echo $kitchen_theme['textMuted']; ?> flex justify-between items-center gap-2 border-t border-slate-200 first:border-t-0 px-2 py-2 <?php echo $rowBg; ?>">
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-center">
                                        <div class="flex flex-col min-w-0">
                                            <span class="font-bold <?php echo $kitchen_theme['itemName']; ?> truncate">
                                                #<?php echo (int)$h['order_id']; ?> · <?php echo htmlspecialchars($h['menu_name']); ?> x<?php echo (int)$h['quantity']; ?>
                                            </span>
                                            <span class="text-[11px]"><?php echo $timeOnly; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>
