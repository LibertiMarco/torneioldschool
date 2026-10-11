<?php
require_once __DIR__ . '/../includi/mobile_auth.php';
require_once __DIR__ . '/../includi/staff_engine.php';
function staff_expect(bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
function staff_reject(array $query, array $permissions, int $status): void {
    try { staff_engine_source($query, $permissions); }
    catch (MobileAuthError $error) { staff_expect($error->status === $status, 'Expected status ' . $status); return; }
    throw new RuntimeException('Expected rejection');
}
$admin = ['admin' => true, 'graphics' => true];
$graphic = ['graphics' => true, 'admin' => false];
$public = ['graphics' => false, 'admin' => false];
$routes = [
    'gestione_tornei', 'gestione_partite', 'gestione_squadre', 'gestione_giocatori',
    'crea_giornata_automatica', 'sincronizza_video_partite', 'gestione_account_giocatore',
    'gestione_blog', 'gestione_staff', 'gestione_albo', 'gestione_totocalcio', 'gestione_fantacalcio',
    'statistiche_partita', 'crea_calendario_allinone',
];
foreach (array_merge(array_map(static fn($name) => 'api/' . $name . '.php', $routes), ['admin_mobile.php']) as $route) {
    staff_expect(str_starts_with(staff_engine_source(['tool' => $route], $admin), '/' . $route . '?embed=1'), 'Admin controller available');
    staff_reject(['tool' => $route], $graphic, 403);
    staff_reject(['tool' => $route, 'ruolo' => 'admin'], $public, 403);
}
foreach (['grafiche_settimana', 'grafiche_post_partita', 'copertina_partite', 'grafiche_classifiche', 'grafiche_presentazione'] as $name) {
    staff_expect(str_contains(staff_engine_source(['tool' => 'api/' . $name . '.php', 'sezione' => 'esport'], $graphic), 'sezione=esport'), 'Graphics staff accepted');
    staff_reject(['tool' => 'api/' . $name . '.php'], $public, 403);
}
staff_expect(str_contains(staff_engine_source(['tool' => 'api/grafiche_post_partita.php', 'templates' => '1'], $graphic), 'templates=1'), 'Template editor available');
foreach (['../login.php', 'api/../admin_mobile.php', 'https://evil.test/a.php', '/api/gestione_tornei.php', 'api/gestione_tornei.php?elimina=1', 'api/gestione_finto.php', ['api/gestione_tornei.php']] as $tool) staff_reject(['tool' => $tool], $admin, 400);
staff_reject(['tool' => 'api/gestione_tornei.php', 'sezione' => ['calcio']], $admin, 400);
staff_reject(['tool' => 'api/gestione_tornei.php', 'sezione' => 'bad'], $admin, 400);
staff_reject(['tool' => 'api/gestione_tornei.php', 'templates' => '1'], $admin, 400);
staff_reject(['tool' => 'api/grafiche_post_partita.php', 'templates' => ['1']], $admin, 400);
echo "Staff engine: controller allowlist, section validation and staff permissions passed.\n";
