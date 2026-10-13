CREATE TABLE IF NOT EXISTS player_link_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    giocatore_id BIGINT UNSIGNED NOT NULL,
    stato VARCHAR(16) NOT NULL DEFAULT 'pending',
    pending_user_id BIGINT UNSIGNED NULL UNIQUE,
    created_at DATETIME NOT NULL,
    reviewed_at DATETIME NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reason VARCHAR(500) NOT NULL DEFAULT '',
    KEY idx_player_link_queue (stato, id),
    KEY idx_player_link_user (user_id, id),
    KEY idx_player_link_player (giocatore_id, stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
