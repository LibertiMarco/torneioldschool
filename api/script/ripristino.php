<?php
/* CONFIGURAZIONE */
$backupPath = __DIR__ . "/db_extract/database"; // percorso cartella con .frm e .ibd
$dbName = "torneioldschool";                    // nome database da ricreare
$mysqli = new mysqli("localhost", "root", "");

/* CREA DATABASE SE NON ESISTE */
$mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbName`");
$mysqli->query("USE `$dbName`");

/* LISTA DELLE TABELLE (ricavate dai file) */
$tables = [
    "blog_commenti",
    "blog_post",
    "giocatori",
    "notifiche_commenti",
    "partita_giocatore",
    "partite",
    "squadre",
    "squadre_giocatori",
    "tornei",
    "utenti"
];

foreach ($tables as $table) {

    echo ">>> Ripristino tabella: $table<br>";

    /* PASSO 1 — DROP SE ESISTE */
    $mysqli->query("DROP TABLE IF EXISTS `$table`");

    /* PASSO 2 — CREA TABELLA VUOTA BASATA SU .frm */
    $frmPath = "$backupPath/$table.frm";
    $targetPath = "C:/xampp/mysql/data/$dbName/$table.frm";
    copy($frmPath, $targetPath);

    /* PASSO 3 — CREA TABELLA IN MYSQL */
    $mysqli->query("CREATE TABLE `$table` (id INT) ENGINE=InnoDB");

    /* PASSO 4 — DISCARD */
    $mysqli->query("ALTER TABLE `$table` DISCARD TABLESPACE");

    /* PASSO 5 — COPIA IBD */
    $ibdPath = "$backupPath/$table.ibd";
    $targetIbd = "C:/xampp/mysql/data/$dbName/$table.ibd";
    copy($ibdPath, $targetIbd);

    /* PASSO 6 — IMPORT */
    $mysqli->query("ALTER TABLE `$table` IMPORT TABLESPACE");

    echo "OK ✔<br><br>";
}

echo "<h2>Ripristino completato!</h2>";
?>
