<?php
require_once __DIR__ . '/includi/admin_guard.php';
require_once __DIR__ . '/api/mobile/v1/_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
$error = '';
$message = '';
$managedByEnvironment = getenv('MOBILE_API_ENABLED') !== false && getenv('MOBILE_API_ENABLED') !== '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['_csrf'] ?? '', 'admin_mobile')) {
        http_response_code(403);
        $error = 'Richiesta scaduta. Ricarica la pagina e riprova.';
    } else {
        try {
            if (($_POST['action'] ?? '') === 'enable') {
                if ($managedByEnvironment) { throw new RuntimeException('Environment-managed configuration'); }
                $schema = file_get_contents(__DIR__ . '/migrations/mobile_auth.sql');
                if ($schema === false) { throw new RuntimeException('Schema unavailable'); }
                $db = mobile_db();
                foreach (explode(';', $schema) as $sql) {
                    if (trim($sql) !== '') { $db->exec($sql); }
                }
                // Verify account/tournament prerequisites before opening API access.
                $db->query('SELECT id, email, nome, cognome, ruolo, avatar, password, email_verificata, feature_flags FROM utenti LIMIT 0');
                $db->query('SELECT id, nome, stato, data_inizio, data_fine, img, filetorneo, categoria, sezione FROM tornei LIMIT 0');
                mobile_api_set_enabled(true);
                $message = 'Accesso mobile attivato.';
            } elseif (($_POST['action'] ?? '') === 'disable') {
                mobile_api_set_enabled(false);
                $message = 'Accesso mobile disattivato.';
            } else { throw new RuntimeException('Invalid action'); }
        } catch (Throwable $failure) {
            error_log('Mobile setup failed: ' . get_class($failure));
            $error = 'Operazione non completata. Verifica la configurazione del database e i permessi di scrittura del server.';
        }
    }
}
$enabled = mobile_api_enabled();
$csrf = csrf_get_token('admin_mobile');
$adminHome = login_with_base_path('/admin_dashboard.php');
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accesso app — Tornei Old School</title>
<style>body{margin:0;background:#f4f6fb;color:#15293e;font-family:system-ui,sans-serif}main{max-width:640px;margin:48px auto;padding:28px;background:white;border-radius:16px}h1{font-size:28px}p{line-height:1.6}button{background:#15293e;color:white;border:0;border-radius:8px;padding:14px 20px;font:inherit;cursor:pointer}.status{padding:12px;background:#eef3f8;border-radius:8px}.error{color:#a82020}@media(max-width:700px){main{margin:16px;padding:20px}}</style></head><body><main>
<a href="<?= htmlspecialchars($adminHome, ENT_QUOTES, 'UTF-8') ?>">← Pannello admin</a>
<h1>Accesso all’app</h1>
<p class="status">Stato: <strong><?= $enabled ? 'attivo' : 'disattivato' ?></strong></p>
<?php if ($message): ?><p role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error): ?><p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<p>L’app utilizza gli account, i tornei e i risultati del sito. L’accesso mobile conserva i ruoli e le abilitazioni degli utenti.</p>
<?php if ($managedByEnvironment): ?>
<p>L’attivazione è gestita dalla configurazione del server.</p>
<?php else: ?>
<?php if (!$enabled): ?><p>L’attivazione prepara le tre tabelle dedicate alle sessioni dell’app. Esegui il consueto backup del database prima del rilascio.</p><?php endif; ?>
<form method="post"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="action" value="<?= $enabled ? 'disable' : 'enable' ?>">
<button type="submit"><?= $enabled ? 'Disattiva accesso mobile' : 'Prepara e attiva accesso mobile' ?></button></form>
<?php endif; ?>
</main></body></html>
