<?php
// point_engine.php - 포인트 계산 및 정산 엔진
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

function processPoint($user_id, $store_id, $order_id, $total_amount, $use_point = 0) {
    global $pdo;

    // 1. 해당 매장의 포인트 정책 로드
    $store = $pdo->query("SELECT point_policy, point_rate, point_payer FROM stores WHERE id = $store_id")->fetch();
    
    if ($store['point_policy'] == 'NONE') return; // 포인트 미사용 매장

    // 어떤 종류의 지갑에 반영할지 결정 (간단 버전)
    // - MULTI 정책: MULTI 타입 지갑 사용
    // - SINGLE 정책: SINGLE 타입 지갑 사용
    $wallet_type = ($store['point_policy'] == 'MULTI') ? 'MULTI' : 'SINGLE';
    $wallet_store_id = $store['point_policy'] == 'MULTI' ? $store_id : $store_id;

    // 2. 포인트 사용 처리 (결제 시 포인트 사용한 경우)
    if ($use_point > 0) {
        // 로그 기록
        $pdo->prepare("INSERT INTO point_logs (user_id, store_id, amount, type, payer, created_at) VALUES (?, ?, ?, 'USE', ?, NOW())")
            ->execute([$user_id, $store_id, -$use_point, $store['point_payer']]);

        // 지갑 잔액 차감 (없으면 0에서 시작)
        $stmt = $pdo->prepare("
            INSERT INTO user_wallets (user_id, store_id, asset_type, balance)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE balance = GREATEST(balance - VALUES(balance), 0)
        ");
        $stmt->execute([$user_id, $wallet_store_id, $wallet_type, $use_point]);
    }

    // 3. 포인트 적립 처리 (실 결제 금액 기준)
    $actual_payment = $total_amount - $use_point;
    if ($actual_payment > 0 && $store['point_rate'] > 0) {
        $earn_amount = floor($actual_payment * ($store['point_rate'] / 100));

        // 적립 로그
        $pdo->prepare("INSERT INTO point_logs (user_id, store_id, amount, type, payer, created_at) VALUES (?, ?, ?, 'EARN', ?, NOW())")
            ->execute([$user_id, $store_id, $earn_amount, $store['point_payer']]);

        // 지갑 잔액 증가
        $stmt = $pdo->prepare("
            INSERT INTO user_wallets (user_id, store_id, asset_type, balance)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
        ");
        $stmt->execute([$user_id, $wallet_store_id, $wallet_type, $earn_amount]);
    }
}

function getUserPoints($user_id, $current_store_id) {
    global $pdo;
    
    // 매장 정책 확인
    $store = $pdo->query("SELECT point_policy FROM stores WHERE id = $current_store_id")->fetch();
    
    if ($store['point_policy'] == 'MULTI') {
        // 브랜드 전체 통합 포인트 조회
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM point_logs WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } else if ($store['point_policy'] == 'SINGLE') {
        // 해당 매장에서 쌓은 포인트만 조회
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM point_logs WHERE user_id = ? AND store_id = ?");
        $stmt->execute([$user_id, $current_store_id]);
    } else {
        return 0;
    }
    return (int)$stmt->fetchColumn();
}
?>