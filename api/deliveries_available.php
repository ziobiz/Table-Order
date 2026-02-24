<?php
// GET /api/deliveries_available.php — HQ 기사용: WAITING 배달 목록 (콜 잡기용)
header('Content-Type: application/json; charset=utf-8');
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db_config.php';

$out = ['success' => false, 'list' => [], 'message' => ''];

if (!isset($_SESSION['driver_id']) || ($_SESSION['driver_type'] ?? '') !== 'HQ') {
    $out['message'] = 'HQ driver login required';
    echo json_encode($out);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT d.id AS delivery_id, d.order_id, d.status, d.created_at,
               o.store_id, o.address, o.tel, o.guest_name, o.guest_tel, o.total_amount,
               s.store_name
        FROM deliveries d
        JOIN orders o ON o.id = d.order_id
        LEFT JOIN stores s ON s.id = o.store_id
        WHERE d.status = 'WAITING' AND d.driver_id IS NULL
        ORDER BY d.created_at ASC
    ");
    $out['list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out['success'] = true;
} catch (Exception $e) {
    $out['message'] = $e->getMessage();
}

echo json_encode($out);
