-- Apply after mobile_auth.sql. Tokens are bound to revocable login sessions.
CREATE TABLE IF NOT EXISTS mobile_push_devices (
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    token TEXT NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at BIGINT NOT NULL,
    last_test_at BIGINT NOT NULL DEFAULT 0,
    KEY idx_mobile_push_user (user_id, active),
    CONSTRAINT fk_mobile_push_session FOREIGN KEY (session_id) REFERENCES mobile_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
