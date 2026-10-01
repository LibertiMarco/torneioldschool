<?php
declare(strict_types=1);

/** Validate locally without exposing the credential or contacting Instagram. */
function instagram_oauth_secret_error(string $secret): ?string
{
    $secret = trim($secret);
    if ($secret === '') {
        return 'Config Instagram Login mancante: definisci INSTAGRAM_APP_SECRET sul server.';
    }
    if (strlen($secret) > 64 && preg_match('/^(?:IGAA|EAA)/', $secret) === 1) {
        return 'INSTAGRAM_APP_SECRET sembra contenere un token di accesso. In Meta, apri Configurazione API con Instagram Login e copia il valore da Chiave segreta di Instagram > Mostra. Il token generato con Genera token va in INSTAGRAM_ACCESS_TOKEN.';
    }
    return null;
}
