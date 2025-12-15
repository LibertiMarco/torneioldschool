<?php
class Partita {
    private $conn;
    private $table = "partite";

    public function __construct() {
        require __DIR__ . '/../../includi/db.php';
        $this->conn = $conn;
    }

    public function getAll() {
        $ordineFase = "
            CASE 
                WHEN fase = 'REGULAR' THEN COALESCE(giornata, 0)
                WHEN fase_round = 'OTTAVI' THEN 1
                WHEN fase_round = 'QUARTI' THEN 2
                WHEN fase_round = 'SEMIFINALE' THEN 3
                WHEN fase_round = 'FINALE' THEN 4
                ELSE 5
            END
        ";
        $sql = "SELECT * FROM {$this->table} ORDER BY torneo, fase, {$ordineFase}, data_partita, ora_partita";
        return $this->conn->query($sql);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // ✅ CREA PARTITA (AGGIUNTI link_youtube E link_instagram)
    public function crea(
        $squadra_casa,
        $squadra_ospite,
        $gol_casa,
        $gol_ospite,
        $data,
        $ora,
        $campo,
        $giornata,
        $torneo,
        $fase = 'REGULAR',
        $fase_round = null,
        $fase_leg = null,
        $link_youtube = null,
        $link_instagram = null,
        $arbitro = null,
        $decisa_rigori = 0,
        $rigori_casa = null,
        $rigori_ospite = null
    ) {
        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table}
            (squadra_casa, squadra_ospite, gol_casa, gol_ospite,
             data_partita, ora_partita, campo, decisa_rigori, rigori_casa, rigori_ospite,
             giornata, torneo, fase,
             fase_round, fase_leg,
             link_youtube, link_instagram, arbitro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssiisssiiiisssssss",
            $squadra_casa,
            $squadra_ospite,
            $gol_casa,
            $gol_ospite,
            $data,
            $ora,
            $campo,
            $decisa_rigori,
            $rigori_casa,
            $rigori_ospite,
            $giornata,
            $torneo,
            strtoupper($fase),
            $fase_round,
            $fase_leg,
            $link_youtube,
            $link_instagram,
            $arbitro
        );

        return $stmt->execute();
    }

    // ✅ AGGIORNA PARTITA (AGGIUNTI link_youtube E link_instagram)
    public function aggiorna(
        $id,
        $squadra_casa,
        $squadra_ospite,
        $gol_casa,
        $gol_ospite,
        $data,
        $ora,
        $campo,
        $giornata,
        $torneo,
        $fase = 'REGULAR',
        $fase_round = null,
        $fase_leg = null,
        $link_youtube = null,
        $link_instagram = null,
        $arbitro = null,
        $decisa_rigori = 0,
        $rigori_casa = null,
        $rigori_ospite = null
    ) {
        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET squadra_casa = ?, squadra_ospite = ?, gol_casa = ?, gol_ospite = ?,
                data_partita = ?, ora_partita = ?, campo = ?, decisa_rigori = ?, rigori_casa = ?, rigori_ospite = ?, giornata = ?, torneo = ?, fase = ?,
                fase_round = ?, fase_leg = ?,
                link_youtube = ?, link_instagram = ?,
                arbitro = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssiisssiiiisssssssi",
            $squadra_casa,
            $squadra_ospite,
            $gol_casa,
            $gol_ospite,
            $data,
            $ora,
            $campo,
            $decisa_rigori,
            $rigori_casa,
            $rigori_ospite,
            $giornata,
            $torneo,
            strtoupper($fase),
            $fase_round,
            $fase_leg,
            $link_youtube,
            $link_instagram,
            $arbitro,
            $id
        );

        return $stmt->execute();
    }

    // ✅ ELIMINA PARTITA
    public function elimina($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // ✅ TORNEI ESISTENTI
    public function getTornei() {
        $sql = "SELECT DISTINCT torneo FROM {$this->table}";
        return $this->conn->query($sql);
    }

    // ✅ SQUADRE PER TORNEO
    public function getSquadre($torneo) {
        $stmt = $this->conn->prepare("SELECT nome FROM squadre WHERE torneo = ?");
        $stmt->bind_param("s", $torneo);
        $stmt->execute();
        return $stmt->get_result();
    }


    // 🔥 LOGICA CLASSIFICA (non modificata)
    public function aggiornaClassifica($torneo, $squadraCasa, $squadraOspite, $golCasa, $golOspite, $vecchiDati = null, $fase = 'REGULAR') {
        $faseCorrente = strtoupper($fase ?? 'REGULAR');

        $vecchiaFase = strtoupper($vecchiDati['fase'] ?? 'REGULAR');
        if ($vecchiDati && $vecchiaFase === 'REGULAR') {
            $this->annullaVecchioRisultato(
                $vecchiDati['torneo'],
                $vecchiDati['squadra_casa'],
                $vecchiDati['squadra_ospite'],
                $vecchiDati['gol_casa'],
                $vecchiDati['gol_ospite']
            );
        }

        if (
            $faseCorrente !== 'REGULAR' ||
            str_ends_with($torneo, '_gold') ||
            str_ends_with($torneo, '_silver')
        ) {
            return;
        }

        $this->aggiornaStatistiche($torneo, $squadraCasa, $golCasa, $golOspite);
        $this->aggiornaStatistiche($torneo, $squadraOspite, $golOspite, $golCasa);
    }

    private function annullaVecchioRisultato($torneo, $squadraCasa, $squadraOspite, $golCasa, $golOspite) {
        $this->annullaStatistiche($torneo, $squadraCasa, $golCasa, $golOspite);
        $this->annullaStatistiche($torneo, $squadraOspite, $golOspite, $golCasa);
    }

    private function annullaStatistiche($torneo, $squadra, $golFatti, $golSubiti) {
        $vittoria = $pareggio = $sconfitta = 0;
        $punti = 0;

        if ($golFatti > $golSubiti) { $vittoria = 1; $punti = 3; }
        elseif ($golFatti == $golSubiti) { $pareggio = 1; $punti = 1; }
        else { $sconfitta = 1; }

        $sql1 = "
            UPDATE squadre
            SET giocate = GREATEST(giocate - 1, 0),
                vinte = GREATEST(vinte - ?, 0),
                pareggiate = GREATEST(pareggiate - ?, 0),
                perse = GREATEST(perse - ?, 0),
                punti = GREATEST(punti - ?, 0),
                gol_fatti = GREATEST(gol_fatti - ?, 0),
                gol_subiti = GREATEST(gol_subiti - ?, 0)
            WHERE torneo = ? AND nome = ?
        ";
        $stmt1 = $this->conn->prepare($sql1);
        $stmt1->bind_param("iiiiiiss",
            $vittoria, $pareggio, $sconfitta, $punti,
            $golFatti, $golSubiti,
            $torneo, $squadra
        );
        $stmt1->execute();

        $sql2 = "UPDATE squadre SET differenza_reti = gol_fatti - gol_subiti WHERE torneo = ? AND nome = ?";
        $stmt2 = $this->conn->prepare($sql2);
        $stmt2->bind_param("ss", $torneo, $squadra);
        $stmt2->execute();
    }

    private function aggiornaStatistiche($torneo, $squadra, $golFatti, $golSubiti) {
        $vittoria = $pareggio = $sconfitta = 0;
        $punti = 0;

        if ($golFatti > $golSubiti) { $vittoria = 1; $punti = 3; }
        elseif ($golFatti == $golSubiti) { $pareggio = 1; $punti = 1; }
        else { $sconfitta = 1; }

        $sql1 = "
            UPDATE squadre
            SET giocate = giocate + 1,
                vinte = vinte + ?,
                pareggiate = pareggiate + ?,
                perse = perse + ?,
                punti = punti + ?,
                gol_fatti = gol_fatti + ?,
                gol_subiti = gol_subiti + ?
            WHERE torneo = ? AND nome = ?
        ";
        $stmt1 = $this->conn->prepare($sql1);
        $stmt1->bind_param("iiiiiiss",
            $vittoria, $pareggio, $sconfitta, $punti,
            $golFatti, $golSubiti,
            $torneo, $squadra
        );
        $stmt1->execute();

        $sql2 = "UPDATE squadre SET differenza_reti = gol_fatti - gol_subiti WHERE torneo = ? AND nome = ?";
        $stmt2 = $this->conn->prepare($sql2);
        $stmt2->bind_param("ss", $torneo, $squadra);
        $stmt2->execute();
    }
}
?>
