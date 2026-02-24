<?php
// recovery.php - 긴급 계정 복구 스크립트
include 'db_config.php';
$pw = password_hash("admin1!", PASSWORD_DEFAULT);
$sql = "INSERT INTO staff_members (store_id, username, password, name, role, force_password_change, is_active) 
        VALUES (0, 'superadmin', ?, 'HQ_Master', 'SUPERADMIN', 1, 1)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$pw]);
echo "슈퍼 관리자 계정이 생성되었습니다. 아이디: superadmin / 비번: admin1!";
?>