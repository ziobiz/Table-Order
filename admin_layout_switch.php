<?php
// admin_layout_switch.php - 본사 레이아웃 전환: PC 업무 모드(사이드바) / 간편 보기(카드)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') { header("Location: login.php"); exit; }

$layout = isset($_GET['layout']) ? trim($_GET['layout']) : '';
if ($layout === 'sidebar') {
    $_SESSION['admin_layout'] = 'sidebar';
} elseif ($layout === 'cards') {
    $_SESSION['admin_layout'] = 'cards';
}

$back = isset($_GET['back']) ? $_GET['back'] : 'admin_dashboard.php';
if (strpos($back, 'admin_') !== 0) $back = 'admin_dashboard.php';
header("Location: " . $back);
exit;
