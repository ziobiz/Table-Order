-- ============================================================
-- 아래 SQL 전체를 복사하여 phpMyAdmin [SQL] 탭에 붙여넣기 후 실행하세요.
-- 변경 이력(로그 분석) 저장 · 본사 30일 / 가맹점 14일 보관 후 자동 삭제
-- ============================================================

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scope` enum('admin','store') NOT NULL COMMENT 'admin=본사, store=가맹점',
  `actor_id` int(11) NOT NULL DEFAULT 0 COMMENT 'admin_id 또는 store_id',
  `actor_name` varchar(100) NOT NULL DEFAULT '' COMMENT '접속자 표시명',
  `page` varchar(100) NOT NULL DEFAULT '' COMMENT '페이지 식별(예: admin_store_manage, store_rider_manage)',
  `action` varchar(30) NOT NULL DEFAULT 'update' COMMENT 'create, update, delete, approve, reject 등',
  `entity_type` varchar(50) DEFAULT NULL COMMENT '대상 엔티티(store, driver, order, application 등)',
  `entity_id` varchar(50) DEFAULT NULL COMMENT '대상 ID',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '요약 설명',
  `details` text DEFAULT NULL COMMENT '상세(JSON 등)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scope_created` (`scope`, `created_at`),
  KEY `idx_page` (`page`),
  KEY `idx_actor` (`scope`, `actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='변경 이력 · 본사 30일/가맹점 14일 보관';
