<?php
// admin_menu_delete.php - 상품 삭제 처리 (전체 소스)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_config.php';

// 권한 체크 (운영자 이상만 가능)
$admin_role = $_SESSION['admin_role'] ?? 'PARTTIME';
if (!in_array($admin_role, ['SUPERADMIN', 'MANAGER'])) {
    echo "<script>alert('삭제 권한이 없습니다.'); location.href='admin_menu_list.php';</script>";
    exit;
}

$menu_id = $_GET['id'] ?? 0;

if ($menu_id > 0) {
    try {
        // DB 테이블 설계 시 ON DELETE CASCADE가 걸려있으므로 menus만 삭제해도 번역 데이터가 함께 삭제됩니다.
        $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
        $stmt->execute([$menu_id]);
        
        echo "<script>alert('상품이 성공적으로 삭제되었습니다.'); location.href='admin_menu_list.php';</script>";
    } catch (PDOException $e) {
        die("삭제 중 오류 발생: " . $e->getMessage());
    }
} else {
    header("Location: admin_menu_list.php");
}
exit;