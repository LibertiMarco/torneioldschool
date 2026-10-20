<?php
// Each rostered AllInOneNight tournament contributes one appearance.
function torneo_stats_is_night_expr(string $column): string
{
    return "(LOWER($column) LIKE '%allinonenight%' OR EXISTS (SELECT 1 FROM tornei night_t WHERE (night_t.filetorneo = $column OR night_t.filetorneo = CONCAT($column, '.php') OR night_t.filetorneo = CONCAT($column, '.html') OR night_t.nome = $column) AND LOWER(night_t.nome) LIKE '%allinonenight%'))";
}

function torneo_stats_night_count_expr(string $player, ?string $section = null, array $excluded = []): string
{
    $night = torneo_stats_is_night_expr('night_s.torneo');
    $scope = '';
    if ($section !== null) {
        $section = $section === 'esport' ? 'esport' : 'calcio';
        $scope = " AND COALESCE((SELECT nt.sezione FROM tornei nt WHERE nt.filetorneo = night_s.torneo OR nt.filetorneo = CONCAT(night_s.torneo, '.php') OR nt.nome = night_s.torneo LIMIT 1), 'calcio') = '$section'";
    }
    foreach ($excluded as $slug) {
        $scope .= " AND night_s.torneo <> '" . str_replace("'", "''", $slug) . "'";
    }
    return "(SELECT COUNT(DISTINCT night_s.torneo) FROM squadre_giocatori night_sg JOIN squadre night_s ON night_s.id = night_sg.squadra_id WHERE night_sg.giocatore_id = $player AND $night $scope)";
}
