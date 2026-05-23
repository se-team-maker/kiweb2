--アクセスログにiframeのアクセスを記録するためのカラムを追加--

ALTER TABLE access_logs
    ADD COLUMN event_type VARCHAR(30) NOT NULL DEFAULT 'page_view' AFTER user_id,
    ADD COLUMN page_key VARCHAR(100) NULL AFTER event_type,
    ADD COLUMN page_label VARCHAR(100) NULL AFTER page_key,
    ADD INDEX idx_event_type_created_at (event_type, created_at),
    ADD INDEX idx_page_key (page_key);