-- Nessun backfill: attestazioni e presenze in porta devono essere verificate dallo staff.
CREATE TABLE IF NOT EXISTS match_record_verification (
 partita_id INT UNSIGNED PRIMARY KEY,
 formato VARCHAR(80) NOT NULL,
 esito ENUM('confermato','annullata','tavolino') NOT NULL,
 risultato_hash CHAR(64) NOT NULL,
 marcature_hash CHAR(64) DEFAULT NULL,
 portieri_hash CHAR(64) DEFAULT NULL,
 portieri_json LONGTEXT NOT NULL,
 verified_by INT UNSIGNED NOT NULL,
 verified_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (partita_id) REFERENCES partite(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
