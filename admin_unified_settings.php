<?php
// admin_unified_settings.php - 본사 통합설정 · 로그 보관 기간 등 일괄 적용
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (($_SESSION['admin_role'] ?? '') !== 'SUPERADMIN') { header("Location: login.php"); exit; }
include 'db_config.php';
include 'common.php';

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_username = $_SESSION['admin_username'] ?? ('id_' . $admin_id);
$admin_name = $_SESSION['admin_name'] ?? $admin_username;
$admin_login_at = (int)($_SESSION['admin_login_at'] ?? time());
$header_locale = 'ko';

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_days = isset($_POST['activity_log_admin_retention_days']) ? (int)$_POST['activity_log_admin_retention_days'] : 30;
    $store_days = isset($_POST['activity_log_store_retention_days']) ? (int)$_POST['activity_log_store_retention_days'] : 14;
    $admin_days = $admin_days < 1 ? 30 : ($admin_days > 365 ? 365 : $admin_days);
    $store_days = $store_days < 1 ? 14 : ($store_days > 365 ? 365 : $store_days);
    try {
        $stmt = $pdo->prepare("INSERT INTO global_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['activity_log_admin_retention_days', (string)$admin_days]);
        $stmt->execute(['activity_log_store_retention_days', (string)$store_days]);
        $saved = true;
        log_activity($pdo, 'admin', $admin_id, $admin_name, 'admin_unified_settings', 'update', 'settings', null, "통합설정 변경: 본사 로그 {$admin_days}일, 가맹점 로그 {$store_days}일");
    } catch (Exception $e) {}
}

$admin_retention = (int)get_setting($pdo, 'activity_log_admin_retention_days', '30');
$store_retention = (int)get_setting($pdo, 'activity_log_store_retention_days', '14');
if ($admin_retention < 1) $admin_retention = 30;
if ($store_retention < 1) $store_retention = 14;

$use_sidebar = (($_SESSION['admin_layout'] ?? '') === 'sidebar');
if ($use_sidebar) {
    $admin_page_title = '통합설정';
    $admin_page_subtitle = '본사에서 지정한 값이 전체에 일괄 적용됩니다. 가맹점별 설정이 아닙니다.';
    include 'admin_header.php';
}
?>
<?php if (!$use_sidebar):
    $admin_page_title = '통합설정';
    $admin_page_subtitle = '본사에서 지정한 값이 전체에 일괄 적용됩니다. 가맹점별 설정이 아닙니다.';
    include 'admin_card_header.php';
endif; ?>

        <div class="max-w-[96rem] space-y-6">
        <?php if ($saved): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-bold">설정이 저장되었습니다. 본사·가맹점 로그 보관 기간이 일괄 적용됩니다.</div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-[2rem] shadow-lg border border-slate-100 p-6 md:p-8 space-y-6">
            <section>
                <h2 class="text-sm font-black text-slate-800 uppercase mb-4">로그 분석(변경 이력) 보관 기간</h2>
                <p class="text-xs text-slate-500 mb-4">해당 일수 초과 로그는 자동 삭제됩니다. 본사용·가맹점용 각 1개 값만 사용하며, 모든 본사/모든 가맹점에 동일하게 적용됩니다.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">본사 로그 보관 기간 (일)</label>
                        <input type="number" name="activity_log_admin_retention_days" value="<?php echo (int)$admin_retention; ?>"
                               min="1" max="365" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">가맹점 로그 보관 기간 (일)</label>
                        <input type="number" name="activity_log_store_retention_days" value="<?php echo (int)$store_retention; ?>"
                               min="1" max="365" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold">
                    </div>
                </div>
            </section>
            <div class="pt-2">
                <button type="submit" class="px-6 py-3 bg-violet-500 text-white rounded-xl text-sm font-black uppercase hover:bg-violet-600">저장 (일괄 적용)</button>
            </div>
        </form>
        </div>
<?php if ($use_sidebar): ?>
<?php include 'admin_footer.php'; ?>
<?php else: ?>
<?php include 'admin_card_footer.php'; ?>
<?php endif; ?>
