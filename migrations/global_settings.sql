-- ============================================================
-- 본사 통합설정 저장 · 로그 보관 기간 등 일괄 적용용
-- phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- ============================================================

CREATE TABLE IF NOT EXISTS `global_settings` (
  `setting_key` varchar(100) NOT NULL COMMENT '예: activity_log_admin_retention_days',
  `setting_value` text DEFAULT NULL COMMENT '문자열 값',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='본사 통합설정 · 일괄 적용';

-- 기본값: 본사 30일, 가맹점 14일 (없을 때만 INSERT)
INSERT IGNORE INTO `global_settings` (`setting_key`, `setting_value`) VALUES
  ('activity_log_admin_retention_days', '30'),
  ('activity_log_store_retention_days', '14');
