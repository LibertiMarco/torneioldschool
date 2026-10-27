<?php
require_once __DIR__ . '/../includi/admin_guard.php';
require_once __DIR__ . '/mobile/v1/_bootstrap.php';
require_once __DIR__ . '/../includi/referees.php';
header('Content-Type: text/html; charset=utf-8');
$message = ''; $error = ''; $names = [];
try {
    $directory = new RefereeDirectory(mobile_db());
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!csrf_is_valid(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null, 'referee_directory')) {
            throw new InvalidArgumentException('Sessione scaduta. Ricarica la pagina.');
        }
        $action = (string)($_POST['azione'] ?? '');
        $old = (string)($_POST['old_name'] ?? '');
        if ($action !== 'create' && !in_array($old, $directory->names(), true)) {
            throw new InvalidArgumentException('Arbitro non disponibile. Ricarica la pagina.');
        }
        if ($action === 'create') $directory->create((string)($_POST['name'] ?? ''));
        elseif ($action === 'rename') $directory->rename($old, (string)($_POST['name'] ?? ''));
        elseif ($action === 'remove') $directory->remove($old);
        else throw new InvalidArgumentException('Operazione non valida.');
        $message = 'Picklist aggiornata.';
    }
    $names = $directory->names();
} catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
catch (Throwable $e) { error_log('Gestione arbitri: '.$e->getMessage()); $error = 'Anagrafica arbitri non disponibile.'; }
function referee_h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
$token = csrf_get_token('referee_directory');
?>
<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Gestione arbitri</title><link rel="stylesheet" href="/admin.css">
<style>
body{margin:0;background:#f8f9fb;color:#15293e;font-family:system-ui,sans-serif}
.admin-container{background:white;border-radius:16px;box-sizing:border-box}
.admin-form{display:flex;gap:12px;align-items:end;flex-wrap:wrap;padding:16px;border:1px solid #d5dbe4;border-radius:12px}
label{display:flex;flex:1;flex-direction:column;gap:8px;min-width:180px}
input,button{box-sizing:border-box;font:inherit;padding:12px;border:1px solid #cbd5e1;border-radius:8px}
input{width:100%}button{background:#15293e;color:white;cursor:pointer}
button[value=remove]{background:#a82233}a{color:#1f5380}
[role=alert]{color:#a82233}[role=status]{color:#1f6a44}
@media(max-width:600px){.admin-form button{width:100%}}
</style></head>
<body><main class="admin-container" style="max-width:900px;margin:30px auto;padding:20px">
<h1>Gestione picklist arbitri</h1><p><a href="/api/gestione_partite.php">Torna alle partite</a></p>
<p>Aggiungi, rinomina o rimuovi i nomi selezionabili. I referti delle partite conservano l’arbitro già assegnato.</p>
<?php if ($message): ?><p role="status"><?= referee_h($message) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert"><?= referee_h($error) ?></p><?php endif; ?>
<form method="post" class="admin-form"><input type="hidden" name="_csrf" value="<?= referee_h($token) ?>"><input type="hidden" name="azione" value="create"><label>Nome e cognome<input name="name" maxlength="150" required></label><button type="submit">Aggiungi arbitro</button></form>
<h2>Arbitri disponibili</h2>
<?php foreach ($names as $name): ?>
<form method="post" class="admin-form" style="margin:20px 0"><input type="hidden" name="_csrf" value="<?= referee_h($token) ?>"><input type="hidden" name="old_name" value="<?= referee_h($name) ?>"><label>Arbitro<input name="name" maxlength="150" value="<?= referee_h($name) ?>" required></label><button type="submit" name="azione" value="rename">Rinomina</button><button type="submit" name="azione" value="remove">Rimuovi dalla picklist</button></form>
<?php endforeach; ?>
</main></body></html>
