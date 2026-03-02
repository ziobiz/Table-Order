<?php
// api/gift_card_check.php - 기프트카드 잔액 조회 (AJAX)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
include dirname(__DIR__) . '/db_config.php';

$store_id = (int)($_SESSION['store_id'] ?? 1);
$code = trim(preg_replace('/[\s\-]/', '', strtoupper($_POST['gift_code'] ?? $_GET['gift_code'] ?? '')));

if (strlen($code) < 8) {
    echo json_encode(['status' => 'error', 'message' => '코드를 입력해 주세요.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, code, balance, store_id, status, expires_at
        FROM gift_cards
        WHERE REPLACE(REPLACE(UPPER(code), '-', ''), ' ', '') = ?
    ");
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => '유효하지 않은 코드입니다.']);
        exit;
    }
    if ($row['status'] !== 'ACTIVE') {
        echo json_encode(['status' => 'error', 'message' => '사용 불가한 카드입니다.']);
        exit;
    }
    if ($row['expires_at'] && $row['expires_at'] < date('Y-m-d')) {
        echo json_encode(['status' => 'error', 'message' => '만료된 카드입니다.']);
        exit;
    }
    if ($row['store_id'] !== null && (int)$row['store_id'] !== $store_id) {
        echo json_encode(['status' => 'error', 'message' => '이 매장에서 사용할 수 없는 카드입니다.']);
        exit;
    }
    if ((int)$row['balance'] <= 0) {
        echo json_encode(['status' => 'error', 'message' => '잔액이 없습니다.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'gift_card_id' => (int)$row['id'],
        'code' => $row['code'],
        'balance' => (int)$row['balance']
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => '조회 중 오류가 발생했습니다.']);
}
