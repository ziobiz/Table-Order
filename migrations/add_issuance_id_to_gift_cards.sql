-- ============================================================
-- gift_cards에 issuance_id 추가 (발급 수/사용 수/잔존 수 구분용)
-- phpMyAdmin [SQL] 탭에 붙여넣기 후 실행
-- ============================================================

ALTER TABLE `gift_cards` 
ADD COLUMN `issuance_id` int(11) DEFAULT NULL COMMENT 'gift_card_issuances.id' AFTER `expires_at`,
ADD KEY `idx_issuance` (`issuance_id`);
