<?php
require_once __DIR__ . '/../includi/mobile_auth.php';
require_once __DIR__ . '/../includi/mobile_follow.php';
class FollowFixture extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
function follow_expect(bool $ok): void { if (!$ok) { throw new RuntimeException('Follow assertion failed'); } }
$db = new FollowFixture('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE utenti (id INTEGER PRIMARY KEY); INSERT INTO utenti VALUES (1), (2);
CREATE TABLE tornei (id INTEGER PRIMARY KEY, filetorneo TEXT); INSERT INTO tornei VALUES (1, 'summer.php');
CREATE TABLE squadre (nome TEXT, torneo TEXT, logo TEXT); INSERT INTO squadre VALUES ('Alpha', 'summer', '/alpha.png'), ('Beta', 'summer', '/beta.png'), ('Other', 'winter', '/other.png');
CREATE TABLE seguiti (utente_id INTEGER, tipo TEXT, torneo_slug TEXT, squadra_nome TEXT);
INSERT INTO seguiti VALUES (2, 'torneo', 'summer', NULL), (1, 'squadra', 'winter', 'Other');");
$read = fn() => mobile_follow_handle($db, 1, ['torneo' => 'summer'], false);
$save = fn($teams, $followed = true) => mobile_follow_handle($db, 1, ['torneo' => 'summer', 'followed' => $followed, 'teams' => $teams], true);
follow_expect(!$read()['followed']);
follow_expect((array)$read()['team_logos'] === ['Alpha' => '/alpha.png', 'Beta' => '/beta.png']);
follow_expect($save(null)['all']);
// Simulate old web follows which also inserted every individual team.
$db->exec("INSERT INTO seguiti VALUES (1, 'squadra', 'summer', 'Beta')");
$state = $save(['Alpha']);
follow_expect($state['followed'] && !$state['all'] && $state['teams'] === ['Alpha']);
// The existing sender's tournament/team union must no longer match Beta alone.
follow_expect((int)$db->query("SELECT COUNT(*) FROM seguiti WHERE utente_id=1 AND torneo_slug='summer' AND (tipo='torneo' OR squadra_nome='Beta')")->fetchColumn() === 0);
try { $save(['Other']); throw new RuntimeException('Invalid team accepted'); }
catch (MobileAuthError $error) { follow_expect($error->status === 422); }
follow_expect($read()['teams'] === ['Alpha']);
follow_expect($save(null)['all']);
follow_expect(!$save([], false)['followed']);
follow_expect((int)$db->query("SELECT COUNT(*) FROM seguiti WHERE utente_id=2 OR torneo_slug='winter'")->fetchColumn() === 2);
echo "Mobile follow: all, subset, invalid teams, legacy cleanup and account isolation passed.\n";
