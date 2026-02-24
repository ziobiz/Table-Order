<?php
// POST /api/deliveries_assign.php — 가맹점 수동 배차 (Deliver 지정)
// Body: delivery_id, driver_id (JSON or form)
header('Content-Type: application/json; charset=utf-8');
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db_config.php';

$out = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $out['message'] = 'Method not allowed';
    echo json_encode($out);
    exit;
}
if (!isset($_SESSION['store_id'])) {
    $out['message'] = 'Unauthorized (store login required)';
    echo json_encode($out);
    exit;
}

$store_id = (int)$_SESSION['store_id'];
$delivery_id = (int)($_POST['delivery_id'] ?? $_REQUEST['delivery_id'] ?? 0);
$driver_id = (int)($_POST['driver_id'] ?? $_REQUEST['driver_id'] ?? 0);

if ($delivery_id <= 0 || $driver_id <= 0) {
    $out['message'] = 'delivery_id and driver_id required';
    echo json_encode($out);
    exit;
}

try {
    $pdo->beginTransaction();

    $del = $pdo->prepare("SELECT d.id, d.order_id, d.status, o.store_id FROM deliveries d JOIN orders o ON o.id = d.order_id WHERE d.id = ? AND o.store_id = ?");
    $del->execute([$delivery_id, $store_id]);
    $delivery = $del->fetch(PDO::FETCH_ASSOC);
    if (!$delivery) {
        $pdo->rollBack();
        $out['message'] = 'Delivery not found or not your store';
        echo json_encode($out);
        exit;
    }
    if ($delivery['status'] !== 'WAITING') {
        $pdo->rollBack();
        $out['message'] = 'Delivery already assigned';
        echo json_encode($out);
        exit;
    }

    $drv = $pdo->prepare("SELECT id, driver_type, store_id FROM drivers WHERE id = ? AND is_active = 1");
    $drv->execute([$driver_id]);
    $driver = $drv->fetch(PDO::FETCH_ASSOC);
    if (!$driver) {
        $pdo->rollBack();
        $out['message'] = 'Driver not found';
        echo json_encode($out);
        exit;
    }
    if ($driver['driver_type'] !== 'DELIVER') {
        $pdo->rollBack();
        $out['message'] = 'Rider must be DELIVER type (in-house)';
        echo json_encode($out);
        exit;
    }
    if ((int)$driver['store_id'] !== $store_id) {
        $pdo->rollBack();
        $out['message'] = 'Driver does not belong to your store';
        echo json_encode($out);
        exit;
    }

    $pdo->prepare("UPDATE deliveries SET driver_id = ?, dispatch_type = 'MANUAL', status = 'ACCEPTED', updated_at = NOW() WHERE id = ?")
        ->execute([$driver_id, $delivery_id]);

    $pdo->commit();
    $out['success'] = true;
    $out['message'] = 'Assigned';
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $out['message'] = $e->getMessage();
}

echo json_encode($out);
