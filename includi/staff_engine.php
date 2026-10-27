<?php
/** Only these existing controllers may drive the native staff interface. */
function staff_engine_source(array $query, array $permissions): string
{
    $admin = [
        'api/gestione_arbitri.php', 'api/gestione_tornei.php', 'api/gestione_partite.php', 'api/gestione_squadre.php',
        'api/gestione_giocatori.php', 'api/crea_giornata_automatica.php',
        'api/sincronizza_video_partite.php', 'api/gestione_account_giocatore.php',
        'admin_mobile.php', 'api/gestione_blog.php', 'api/gestione_staff.php',
        'api/gestione_albo.php', 'api/gestione_totocalcio.php', 'api/gestione_fantacalcio.php',
        'api/statistiche_partita.php', 'api/crea_calendario_allinone.php',
    ];
    $graphics = [
        'api/grafiche_settimana.php', 'api/grafiche_post_partita.php',
        'api/copertina_partite.php', 'api/grafiche_classifiche.php', 'api/grafiche_presentazione.php',
    ];
    $tool = $query['tool'] ?? '';
    if (!is_string($tool) || !in_array($tool, array_merge($admin, $graphics), true)) {
        throw new MobileAuthError('invalid_request', 'Strumento non valido.', 400);
    }
    $isGraphics = in_array($tool, $graphics, true);
    if (empty($permissions['admin']) && (!$isGraphics || empty($permissions['graphics']))) {
        throw new MobileAuthError('forbidden', 'Area riservata allo staff.', 403);
    }
    $section = $query['sezione'] ?? 'calcio';
    if (!in_array($section, ['calcio', 'esport'], true)) {
        throw new MobileAuthError('invalid_request', 'Sezione non valida.', 400);
    }
    $params = ['embed' => '1', 'sezione' => $section];
    if (isset($query['templates'])) {
        if ($tool !== 'api/grafiche_post_partita.php' || $query['templates'] !== '1') {
            throw new MobileAuthError('invalid_request', 'Formato non valido.', 400);
        }
        $params['templates'] = '1';
    }
    return '/' . $tool . '?' . http_build_query($params);
}
