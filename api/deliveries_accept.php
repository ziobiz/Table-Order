<?php
// POST /api/deliveries_accept.php — HQ 기사가 콜 수락 (동시성 처리)
// Body: delivery_id
header('Content-Type: application/json; charset=utf-8');
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db_config.php';

$out = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $out['message'] = 'Method not allowed';
    echo json_encode($out);
    exit;
}
if (!isset($_SESSION['driver_id']) || ($_SESSION['driver_type'] ?? '') !== 'HQ') {
    $out['message'] = 'HQ driver login required';
    echo json_encode($out);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];
$delivery_id = (int)($_POST['delivery_id'] ?? $_REQUEST['delivery_id'] ?? 0);

if ($delivery_id <= 0) {
    $out['message'] = 'delivery_id required';
    echo json_encode($out);
    exit;
}

try {
    $pdo->beginTransaction();

    $del = $pdo->prepare("SELECT id, status, driver_id FROM deliveries WHERE id = ? FOR UPDATE");
    $del->execute([$delivery_id]);
    $row = $del->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->rollBack();
        $out['message'] = 'Delivery not found';
        echo json_encode($out);
        exit;
    }
    if ($row['status'] !== 'WAITING' || $row['driver_id'] !== null) {
        $pdo->rollBack();
        $out['message'] = 'Already assigned or not waiting';
        echo json_encode($out);
        exit;
    }

    $pdo->prepare("UPDATE deliveries SET driver_id = ?, dispatch_type = 'AUTO', status = 'ACCEPTED', updated_at = NOW() WHERE id = ?")
        ->execute([$driver_id, $delivery_id]);

    $pdo->commit();
    $out['success'] = true;
    $out['message'] = 'Accepted';
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $out['message'] = $e->getMessage();
}

echo json_encode($out);
