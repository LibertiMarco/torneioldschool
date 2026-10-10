-- Additive migration only. Does not alter existing accounts or web sessions.
CREATE TABLE IF NOT EXISTS mobile_auth_codes (
    code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    challenge CHAR(43) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    password_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at BIGINT NOT NULL,
    KEY idx_mobile_code_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mobile_sessions (
    id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    password_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    access_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    access_expires_at BIGINT NOT NULL,
    refresh_expires_at BIGINT NOT NULL,
    revoked_at BIGINT NULL,
    KEY idx_mobile_session_user (user_id),
    KEY idx_mobile_session_expiry (refresh_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mobile_refresh_tokens (
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    session_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at BIGINT NOT NULL,
    used_at BIGINT NULL,
    KEY idx_mobile_refresh_session (session_id),
    KEY idx_mobile_refresh_expiry (expires_at),
    CONSTRAINT fk_mobile_refresh_session FOREIGN KEY (session_id) REFERENCES mobile_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
