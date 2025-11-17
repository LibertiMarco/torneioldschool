<?php
require_once __DIR__ . '/../../includi/db.php';


error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Connessione riuscita a DB: " . $conn->host_info . "<br>";

$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
echo "Database selezionato: " . $db_name . "<br><br>";

// Funzione helper per creare tabelle in modo sicuro
function crea_tabella($conn, $sql, $nome) {
    if ($conn->query($sql) === TRUE) {
        echo "✅ Tabella '$nome' creata correttamente.<br>";
    } else {
        echo "❌ Errore nella creazione di '$nome': " . $conn->error . "<br>";
    }
}

// --- Query 1 ---
$sql_partite = "CREATE TABLE IF NOT EXISTS partite (
    id INT AUTO_INCREMENT PRIMARY KEY,
    squadra_casa VARCHAR(255) NOT NULL,
    squadra_ospite VARCHAR(255) NOT NULL,
    gol_casa INT DEFAULT NULL,
    gol_ospite INT DEFAULT NULL,
    data_partita DATE NOT NULL,
    ora_partita TIME NOT NULL,
    campo VARCHAR(255) NOT NULL,
    giornata TINYINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

crea_tabella($conn, $sql_partite, "partite");

// --- Query 2 ---
$sql_squadre = "CREATE TABLE IF NOT EXISTS squadre (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    torneo VARCHAR(255) NOT NULL,
    punti INT DEFAULT 0,
    giocate INT DEFAULT 0,
    vinte INT DEFAULT 0,
    pareggiate INT DEFAULT 0,
    perse INT DEFAULT 0,
    gol_fatti INT DEFAULT 0,
    gol_subiti INT DEFAULT 0,
    differenza_reti INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

crea_tabella($conn, $sql_squadre, "squadre");

// --- Query 3 ---
$sql_rose = "CREATE TABLE IF NOT EXISTS giocatori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cognome VARCHAR(255) NOT NULL,
    ruolo VARCHAR(255) NOT NULL,
    foto VARCHAR(255) DEFAULT '/torneioldschool/img/giocatori/unknown.jpg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

crea_tabella($conn, $sql_rose, "giocatori");

// --- Query 4 ---
$sql_squadre_giocatori = "CREATE TABLE IF NOT EXISTS squadre_giocatori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    squadra_id INT NOT NULL,
    giocatore_id INT NOT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_squadra_giocatore (squadra_id, giocatore_id),
    CONSTRAINT fk_sg_squadra FOREIGN KEY (squadra_id) REFERENCES squadre(id) ON DELETE CASCADE,
    CONSTRAINT fk_sg_giocatore FOREIGN KEY (giocatore_id) REFERENCES giocatori(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

crea_tabella($conn, $sql_squadre_giocatori, "squadre_giocatori");

echo "<br>📦 Controllo tabelle presenti nel DB:<br>";

$res = $conn->query("SHOW TABLES");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_row()) {
        echo "➡️ " . $r[0] . "<br>";
    }
} else {
    echo "⚠️ Nessuna tabella trovata nel database.";
}

$conn->close();
?>
