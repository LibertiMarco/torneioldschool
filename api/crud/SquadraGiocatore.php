<?php
class SquadraGiocatore {
    private $conn;
    private $table = "squadre_giocatori";

    public function __construct() {
        require __DIR__ . '/../../includi/db.php';
        $this->conn = $conn;
    }

    public function assegna($giocatoreId, $squadraId, $foto = null, array $stats = [], $forzaRimozioneFoto = false) {
        $defaults = [
            'presenze' => 0,
            'reti' => 0,
            'assist' => 0,
            'gialli' => 0,
            'rossi' => 0,
            'media_voti' => null,
        ];
        $stats = array_merge($defaults, $stats);
        $media = $stats['media_voti'];
        $media = ($media === '' || $media === null) ? null : (float)$media;
        $fotoUpdateSql = $forzaRimozioneFoto ? "foto = NULL" : "foto = IFNULL(VALUES(foto), foto)";
        if ($forzaRimozioneFoto) {
            $foto = null;
        }
        $sql = "
            INSERT INTO {$this->table}
                (squadra_id, giocatore_id, foto, presenze, reti, assist, gialli, rossi, media_voti)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                {$fotoUpdateSql},
                presenze = VALUES(presenze),
                reti = VALUES(reti),
                assist = VALUES(assist),
                gialli = VALUES(gialli),
                rossi = VALUES(rossi),
                media_voti = VALUES(media_voti)
        ";

        $stmt = $this->conn->prepare($sql);
        $foto = $foto !== '' ? $foto : null;
        $stmt->bind_param(
            "iisiiiiid",
            $squadraId,
            $giocatoreId,
            $foto,
            $stats['presenze'],
            $stats['reti'],
            $stats['assist'],
            $stats['gialli'],
            $stats['rossi'],
            $media
        );
        $result = $stmt->execute();
        $stmt->close();
        $this->aggiornaTotaliGiocatore($giocatoreId);
        return $result;
    }

    public function dissocia($giocatoreId, $squadraId) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE giocatore_id = ? AND squadra_id = ?");
        $stmt->bind_param("ii", $giocatoreId, $squadraId);
        $result = $stmt->execute();
        $stmt->close();
        $this->aggiornaTotaliGiocatore($giocatoreId);
        return $result;
    }

    public function getSquadrePerGiocatore($giocatoreId) {
        $sql = "
            SELECT s.id, s.nome, s.torneo, sg.foto
            FROM {$this->table} sg
            JOIN squadre s ON s.id = sg.squadra_id
            WHERE sg.giocatore_id = ?
            ORDER BY s.torneo, s.nome
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $giocatoreId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getGiocatoriPerSquadraId($squadraId) {
        $sql = "
            SELECT g.*, s.nome AS squadra_nome, s.torneo,
                   COALESCE(sg.foto, g.foto) AS foto_squadra,
                   sg.presenze AS presenze_squadra,
                   sg.reti AS reti_squadra,
                   sg.assist AS assist_squadra,
                   sg.gialli AS gialli_squadra,
                   sg.rossi AS rossi_squadra,
                   sg.media_voti AS media_squadra
            FROM {$this->table} sg
            JOIN giocatori g ON g.id = sg.giocatore_id
            JOIN squadre s ON s.id = sg.squadra_id
            WHERE s.id = ?
            ORDER BY g.cognome, g.nome
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $squadraId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function esisteAssociazione($giocatoreId, $squadraId) {
        $stmt = $this->conn->prepare("SELECT 1 FROM {$this->table} WHERE giocatore_id = ? AND squadra_id = ? LIMIT 1");
        $stmt->bind_param("ii", $giocatoreId, $squadraId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function getAssociazione($giocatoreId, $squadraId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE giocatore_id = ? AND squadra_id = ? LIMIT 1");
        $stmt->bind_param("ii", $giocatoreId, $squadraId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    private function aggiornaTotaliGiocatore($giocatoreId) {
        $sql = "
            UPDATE giocatori g
            LEFT JOIN (
                SELECT 
                    giocatore_id,
                    SUM(presenze) AS sum_presenze,
                    SUM(reti) AS sum_reti,
                    SUM(assist) AS sum_assist,
                    SUM(gialli) AS sum_gialli,
                    SUM(rossi) AS sum_rossi,
                    SUM(CASE WHEN media_voti IS NOT NULL THEN media_voti ELSE 0 END) AS somma_media,
                    SUM(CASE WHEN media_voti IS NOT NULL THEN 1 ELSE 0 END) AS count_media
                FROM {$this->table}
                WHERE giocatore_id = ?
            ) agg ON agg.giocatore_id = g.id
            SET 
                g.presenze = COALESCE(agg.sum_presenze, 0),
                g.reti = COALESCE(agg.sum_reti, 0),
                g.assist = COALESCE(agg.sum_assist, 0),
                g.gialli = COALESCE(agg.sum_gialli, 0),
                g.rossi = COALESCE(agg.sum_rossi, 0),
                g.media_voti = CASE 
                    WHEN agg.count_media IS NOT NULL AND agg.count_media > 0 
                        THEN ROUND(agg.somma_media / agg.count_media, 2)
                    ELSE NULL
                END
            WHERE g.id = ?
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $giocatoreId, $giocatoreId);
        $stmt->execute();
        $stmt->close();
    }
}
