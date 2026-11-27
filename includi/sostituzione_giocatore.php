<?php
require_once __DIR__ . '/../api/crud/SquadraGiocatore.php';

/** Move only the selected team's records; never merge or overwrite another player. */
function sostituisci_giocatore_squadra(mysqli $conn, int $team, int $old, int $new): void
{
    if ($team <= 0 || $old <= 0 || $new <= 0 || $old === $new) {
        throw new InvalidArgumentException('Seleziona una squadra e due giocatori diversi.');
    }
    $driver = new mysqli_driver();
    $previousReportMode = $driver->report_mode;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $run = static function (string $sql, string $types, ...$params) use ($conn): mysqli_stmt {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt;
    };
    try {
        $conn->begin_transaction();
        // Serialize roster edits and lock player identities in a stable order.
        $stmt = $run('SELECT id FROM squadre WHERE id = ? FOR UPDATE', 'i', $team);
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new InvalidArgumentException('Squadra non trovata.');
        }
        $stmt->close();
        $stmt = $run('SELECT id FROM giocatori WHERE id IN (?, ?) ORDER BY id FOR UPDATE', 'ii', $old, $new);
        if ($stmt->get_result()->num_rows !== 2) {
            throw new InvalidArgumentException('Uno dei giocatori non esiste più. Ricarica la pagina.');
        }
        $stmt->close();
        $stmt = $run('SELECT giocatore_id FROM squadre_giocatori WHERE squadra_id = ? AND giocatore_id IN (?, ?) FOR UPDATE', 'iii', $team, $old, $new);
        $members = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'giocatore_id');
        $stmt->close();
        if (!in_array($old, $members)) {
            throw new InvalidArgumentException('Il giocatore da sostituire non è nella squadra selezionata.');
        }
        if (in_array($new, $members)) {
            throw new InvalidArgumentException('Il sostituto è già nella squadra. Nessun dato modificato.');
        }
        foreach (['partita_giocatore', 'giocatore_goal_extra'] as $table) {
            $stmt = $run("SELECT id FROM $table WHERE squadra_id = ? AND giocatore_id = ? FOR UPDATE", 'ii', $team, $new);
            if ($stmt->get_result()->num_rows > 0) {
                throw new InvalidArgumentException('Il sostituto ha già statistiche in questa squadra. Nessun dato modificato.');
            }
            $stmt->close();
        }
        // Keep every statistic and row ID exactly as recorded; change identity only.
        $stmt = $run('UPDATE partita_giocatore SET giocatore_id = ? WHERE squadra_id = ? AND giocatore_id = ?', 'iii', $new, $team, $old);
        $stmt->close();
        $stmt = $run('UPDATE giocatore_goal_extra SET giocatore_id = ? WHERE squadra_id = ? AND giocatore_id = ?', 'iii', $new, $team, $old);
        $stmt->close();
        // Photo belongs to the outgoing person. Retain role, captain and team totals.
        $stmt = $run('UPDATE squadre_giocatori SET giocatore_id = ?, foto = NULL WHERE squadra_id = ? AND giocatore_id = ?', 'iii', $new, $team, $old);
        $stmt->close();
        $pivot = new SquadraGiocatore($conn);
        $pivot->aggiornaTotaliGiocatore($old);
        $pivot->aggiornaTotaliGiocatore($new);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    } finally {
        mysqli_report($previousReportMode);
    }
}
