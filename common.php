<?php
// common.php - 정수 기반 정산 및 환율 함수 확정본
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$supported_langs = ['ko'=>'한국어', 'ja'=>'日本語', 'en'=>'English', 'zh'=>'中国', 'th'=>'ไทย', 'vi'=>'Tiếng Việt'];
$lang_to_currency = ['ko'=>'KRW', 'th'=>'THB', 'en'=>'USD', 'ja'=>'JPY', 'vi'=>'VND', 'zh'=>'CNY'];

function get_live_exchange_rate($base_currency, $target_currency_code) {
    if ($base_currency == $target_currency_code) return 1;
    if (isset($_SESSION['ex_rate'][$target_currency_code]) && (time() - ($_SESSION['ex_time'] ?? 0) < 3600)) {
        return $_SESSION['ex_rate'][$target_currency_code];
    }
    $url = "https://open.er-api.com/v6/latest/{$base_currency}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['rates'][$target_currency_code])) {
            $rate = $data['rates'][$target_currency_code];
            $_SESSION['ex_rate'][$target_currency_code] = $rate;
            $_SESSION['ex_time'] = time();
            return $rate;
        }
    }
    return 1;
}

// 최종 금액 계산 함수 (부가세, 서비스차지 정수 처리)
function calculate_final_total($subtotal, $store) {
    // 1. 서비스 차지 계산 (방식에 따라 분기)
    $service_charge = ($store['service_charge_type'] == 'PERCENT') 
        ? floor($subtotal * ($store['service_charge_rate'] / 100)) 
        : floor($store['service_charge_rate']);

    $before_tax = $subtotal + $service_charge;

    // 2. 부가세 계산 (별도 방식일 때만 적용)
    $tax = ($store['tax_inclusive'] == 'N') 
        ? floor($before_tax * (floor($store['tax_rate']) / 100)) 
        : 0;
    
    // 3. 팁 계산
    $tip_amount = 0;
    if ($store['allow_tip'] == 'Y') {
        $tip_amount = ($store['tip_type'] == 'PERCENT') 
            ? floor($subtotal * ($store['tip_value'] / 100)) 
            : floor($store['tip_value']);
    }
    
    return [
        'subtotal' => $subtotal, 
        'service' => $service_charge, 
        'tax' => $tax, 
        'tip' => $tip_amount, 
        'total' => $before_tax + $tax + $tip_amount
    ];
}

function get_currency_symbol($code) {
    $symbols = ['KRW'=>'₩', 'THB'=>'฿', 'JPY'=>'¥', 'USD'=>'$', 'VND'=>'₫', 'CNY'=>'¥'];
    return $symbols[$code] ?? $code;
}

/**
 * 국가/로케일별 날짜·시간 포맷 (한 줄 표시용)
 * ko: 년월일 시:분:초 | th: วัน เดือน ปี (พุทธศักราช) | en: Month D, Y H:MM:SS
 * $datetime: timestamp 또는 'Y-m-d H:i:s' 문자열
 */
function format_datetime_locale($datetime, $locale = 'ko') {
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    if ($ts <= 0) return '';
    $locale = strtolower(trim($locale ?: 'ko'));
    $y = (int)date('Y', $ts);
    $m = (int)date('n', $ts);
    $d = (int)date('j', $ts);
    $h = date('H', $ts);
    $i = date('i', $ts);
    $s = date('s', $ts);
    $time = $h . ':' . $i . ':' . $s;
    if ($locale === 'th') {
        $th_months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        $buddha_y = $y + 543;
        return $d . ' ' . ($th_months[$m - 1] ?? '') . ' ' . $buddha_y . ' ' . $time;
    }
    if ($locale === 'en' || $locale === 'en_us') {
        $en_months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return ($en_months[$m - 1] ?? '') . ' ' . $d . ', ' . $y . ' ' . $time;
    }
    if ($locale === 'ja') {
        return $y . '年' . $m . '月' . $d . '日 ' . $time;
    }
    if ($locale === 'vi') {
        return $d . '/' . $m . '/' . $y . ' ' . $time;
    }
    // ko 기본: 년월일
    return $y . '년 ' . $m . '월 ' . $d . '일 ' . $time;
}

/**
 * 기사 등록 시 선택 가능한 필수 항목 목록 (가맹점이 선택하면 해당 항목만 필수)
 * 배달기사 온라인 등록 신청서에도 동일 규칙 적용
 */
function get_driver_registration_field_options() {
    return [
        'last_name'   => '성',
        'first_name'  => '이름',
        'address'     => '주소',
        'phone'       => '전화번호',
        'birth_date'  => '생년월일',
        'email'       => '이메일',
        'id_document' => '주민등록증 등 이미지',
        'tax_id'      => 'tax 정보 또는 주민등록번호',
        'username'    => '아이디(로그인 ID)',
        'password'   => '패스워드',
    ];
}

/**
 * 가맹점별 기사 등록 필수 항목 반환 (배열). 설정 없으면 기본값 사용
 * @param PDO $pdo
 * @param int $store_id
 * @return string[] 필수 필드 키 목록
 */
function get_driver_required_fields($pdo, $store_id) {
    $default = ['last_name', 'first_name', 'username', 'password'];
    if ($store_id === null || $store_id === '' || (int)$store_id <= 0) {
        return $default; // 본사(HQ) 등록 시 기본 필수 항목
    }
    try {
        $stmt = $pdo->prepare("SELECT driver_required_fields FROM stores WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$store_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['driver_required_fields'] === null || $row['driver_required_fields'] === '') {
            return $default;
        }
        $list = array_map('trim', explode(',', $row['driver_required_fields']));
        $valid = array_keys(get_driver_registration_field_options());
        $list = array_intersect($list, $valid);
        return array_values($list) ?: $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * 변경 이력 기록 (본사 30일 / 가맹점 14일 보관 후 자동 삭제)
 * @param PDO $pdo
 * @param string $scope 'admin' | 'store'
 * @param int $actor_id admin_id 또는 store_id
 * @param string $actor_name 표시명
 * @param string $page 페이지 식별 (예: admin_store_manage, store_rider_manage)
 * @param string $action create, update, delete, approve, reject 등
 * @param string|null $entity_type store, driver, order, application 등
 * @param string|int|null $entity_id 대상 ID
 * @param string $summary 요약 (누가 무엇을 변경)
 * @param string|null $details JSON 등 상세
 */
function log_activity($pdo, $scope, $actor_id, $actor_name, $page, $action, $entity_type, $entity_id, $summary, $details = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (scope, actor_id, actor_name, page, action, entity_type, entity_id, summary, details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $scope === 'store' ? 'store' : 'admin',
            (int)$actor_id,
            mb_substr($actor_name, 0, 100),
            mb_substr($page, 0, 100),
            mb_substr($action, 0, 30),
            $entity_type ? mb_substr($entity_type, 0, 50) : null,
            $entity_id !== null && $entity_id !== '' ? (string)$entity_id : null,
            mb_substr($summary, 0, 500),
            $details !== null ? mb_substr($details, 0, 65535) : null
        ]);
    } catch (Exception $e) {
        // 로그 실패 시 무시 (테이블 없을 수 있음)
    }
}

/** 본사 로그 보관 일수 (DB에 값 없을 때 폴백) */
if (!defined('ACTIVITY_LOG_ADMIN_RETENTION_DAYS')) { define('ACTIVITY_LOG_ADMIN_RETENTION_DAYS', 30); }
/** 가맹점 로그 보관 일수 (DB에 값 없을 때 폴백) */
if (!defined('ACTIVITY_LOG_STORE_RETENTION_DAYS')) { define('ACTIVITY_LOG_STORE_RETENTION_DAYS', 14); }

/**
 * 본사 통합설정 값 조회 (global_settings 테이블)
 * @param PDO $pdo
 * @param string $key 설정 키 (예: activity_log_admin_retention_days)
 * @param string|int|null $default 키가 없을 때 반환값
 * @return string|int|null
 */
function get_setting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM global_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row !== false && $row['setting_value'] !== null && $row['setting_value'] !== '') ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * 로그 보관 일수 (통합설정 · 일괄 적용). 본사/가맹점 각 1개 값만 사용.
 * @param PDO $pdo
 * @param string $scope 'admin' | 'store'
 * @return int 보관 일수
 */
function get_activity_log_retention_days($pdo, $scope) {
    $key = $scope === 'store' ? 'activity_log_store_retention_days' : 'activity_log_admin_retention_days';
    $default = $scope === 'store' ? ACTIVITY_LOG_STORE_RETENTION_DAYS : ACTIVITY_LOG_ADMIN_RETENTION_DAYS;
    $val = get_setting($pdo, $key, (string)$default);
    $days = (int)$val;
    return $days > 0 ? $days : $default;
}
?>