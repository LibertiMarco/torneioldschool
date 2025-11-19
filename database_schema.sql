-- Schema SQL per torneioldschool
-- Tabelle derivate dalle query presenti in register.php, login.php, api/gestione_*, api/blog.php e tornei/script-serieA.js

CREATE DATABASE IF NOT EXISTS torneioldschool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE torneioldschool;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifiche_commenti;
DROP TABLE IF EXISTS blog_commenti;
DROP TABLE IF EXISTS blog_post;
DROP TABLE IF EXISTS partita_giocatore;
DROP TABLE IF EXISTS squadre_giocatori;
DROP TABLE IF EXISTS partite;
DROP TABLE IF EXISTS giocatori;
DROP TABLE IF EXISTS squadre;
DROP TABLE IF EXISTS tornei;
DROP TABLE IF EXISTS utenti;

-- Utenti (login.php, register.php, api/gestione_utenti.php, api/blog.php)
CREATE TABLE IF NOT EXISTS utenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL DEFAULT '',
    cognome VARCHAR(100) NOT NULL DEFAULT '',
    username VARCHAR(100) DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    ruolo ENUM('user','admin') NOT NULL DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT NULL,
    email_verificata TINYINT(1) NOT NULL DEFAULT 0,
    token_verifica VARCHAR(64) DEFAULT NULL,
    token_verifica_scadenza DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_utenti_email (email),
    UNIQUE KEY uq_utenti_username (username),
    KEY idx_utenti_token_verifica (token_verifica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tornei (tornei.php, api/gestione_tornei.php, api/crud/Torneo.php)
CREATE TABLE IF NOT EXISTS tornei (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    stato ENUM('programmato','in corso','terminato') NOT NULL DEFAULT 'programmato',
    data_inizio DATE NOT NULL,
    data_fine DATE NOT NULL,
    img VARCHAR(255) DEFAULT '/torneioldschool/img/tornei/pallone.png',
    filetorneo VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tornei_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Squadre (api/crud/Squadra.php, api/leggiClassifica.php, api/gestione_squadre.php)
CREATE TABLE IF NOT EXISTS squadre (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    torneo VARCHAR(255) NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    punti INT NOT NULL DEFAULT 0,
    giocate INT NOT NULL DEFAULT 0,
    vinte INT NOT NULL DEFAULT 0,
    pareggiate INT NOT NULL DEFAULT 0,
    perse INT NOT NULL DEFAULT 0,
    gol_fatti INT NOT NULL DEFAULT 0,
    gol_subiti INT NOT NULL DEFAULT 0,
    differenza_reti INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_squadre_nome_torneo (nome, torneo),
    KEY idx_squadre_torneo (torneo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Giocatori (api/crud/Giocatore.php, api/get_rosa.php, api/partita_giocatore.php)
CREATE TABLE IF NOT EXISTS giocatori (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cognome VARCHAR(255) NOT NULL,
    ruolo VARCHAR(100) NOT NULL,
    presenze INT NOT NULL DEFAULT 0,
    reti INT NOT NULL DEFAULT 0,
    assist INT NOT NULL DEFAULT 0,
    gialli INT NOT NULL DEFAULT 0,
    rossi INT NOT NULL DEFAULT 0,
    media_voti DECIMAL(4,2) DEFAULT NULL,
    foto VARCHAR(255) NOT NULL DEFAULT '/torneioldschool/img/giocatori/unknown.jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_giocatori_torneo (torneo),
    KEY idx_giocatori_squadra (squadra)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partite (api/crud/Partita.php, api/get_partite*.php, tornei/script-serieA.js)
CREATE TABLE IF NOT EXISTS partite (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    torneo VARCHAR(255) NOT NULL,
    squadra_casa VARCHAR(255) NOT NULL,
    squadra_ospite VARCHAR(255) NOT NULL,
    gol_casa INT DEFAULT NULL,
    gol_ospite INT DEFAULT NULL,
    data_partita DATE NOT NULL,
    ora_partita TIME NOT NULL,
    campo VARCHAR(255) NOT NULL,
    giornata TINYINT UNSIGNED NOT NULL,
    giocata TINYINT(1) NOT NULL DEFAULT 0,
    link_youtube VARCHAR(255) DEFAULT NULL,
    link_instagram VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_partite_torneo (torneo),
    KEY idx_partite_giornata (torneo, giornata)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot squadre/giocatori (api/crud/SquadraGiocatore.php, api/get_rosa.php)
CREATE TABLE IF NOT EXISTS squadre_giocatori (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    squadra_id INT UNSIGNED NOT NULL,
    giocatore_id INT UNSIGNED NOT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    presenze INT UNSIGNED NOT NULL DEFAULT 0,
    reti INT UNSIGNED NOT NULL DEFAULT 0,
    assist INT UNSIGNED NOT NULL DEFAULT 0,
    gialli INT UNSIGNED NOT NULL DEFAULT 0,
    rossi INT UNSIGNED NOT NULL DEFAULT 0,
    media_voti DECIMAL(4,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_squadra_giocatore (squadra_id, giocatore_id),
    KEY idx_sg_giocatore (giocatore_id),
    CONSTRAINT fk_sg_squadra FOREIGN KEY (squadra_id) REFERENCES squadre(id) ON DELETE CASCADE,
    CONSTRAINT fk_sg_giocatore FOREIGN KEY (giocatore_id) REFERENCES giocatori(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Statistiche partita/giocatore (api/partita_giocatore.php, api/statistiche_partita.php)
CREATE TABLE IF NOT EXISTS partita_giocatore (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partita_id INT UNSIGNED NOT NULL,
    giocatore_id INT UNSIGNED NOT NULL,
    presenza TINYINT UNSIGNED NOT NULL DEFAULT 1,
    goal INT UNSIGNED NOT NULL DEFAULT 0,
    assist INT UNSIGNED NOT NULL DEFAULT 0,
    cartellino_giallo TINYINT UNSIGNED NOT NULL DEFAULT 0,
    cartellino_rosso TINYINT UNSIGNED NOT NULL DEFAULT 0,
    voto DECIMAL(4,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_partita_giocatore (partita_id, giocatore_id),
    KEY idx_pg_giocatore (giocatore_id),
    CONSTRAINT fk_pg_partita FOREIGN KEY (partita_id) REFERENCES partite(id) ON DELETE CASCADE,
    CONSTRAINT fk_pg_giocatore FOREIGN KEY (giocatore_id) REFERENCES giocatori(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog post (blog.php, articolo.php, api/blog.php)
CREATE TABLE IF NOT EXISTS blog_post (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(255) NOT NULL,
    contenuto LONGTEXT NOT NULL,
    immagine VARCHAR(255) DEFAULT NULL,
    data_pubblicazione DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_blog_data (data_pubblicazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commenti blog (api/blog.php, articolo.php)
CREATE TABLE IF NOT EXISTS blog_commenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    utente_id INT UNSIGNED NOT NULL,
    commento TEXT NOT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_commenti_post (post_id),
    KEY idx_commenti_parent (parent_id),
    CONSTRAINT fk_commento_post FOREIGN KEY (post_id) REFERENCES blog_post(id) ON DELETE CASCADE,
    CONSTRAINT fk_commento_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_commento_parent FOREIGN KEY (parent_id) REFERENCES blog_commenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifiche reply commenti (api/blog.php)
CREATE TABLE IF NOT EXISTS notifiche_commenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utente_id INT UNSIGNED NOT NULL,
    commento_id INT UNSIGNED NOT NULL,
    post_id INT UNSIGNED NOT NULL,
    letto TINYINT(1) NOT NULL DEFAULT 0,
    creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifiche_utente (utente_id),
    KEY idx_notifiche_commento (commento_id),
    CONSTRAINT fk_notifica_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifica_commento FOREIGN KEY (commento_id) REFERENCES blog_commenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifica_post FOREIGN KEY (post_id) REFERENCES blog_post(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
