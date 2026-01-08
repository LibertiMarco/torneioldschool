<?php

// Invio email: usa SMTP se configurato, altrimenti mail() con envelope corretto
if (!function_exists('tos_mail_send')) {
    /**
     * Invia una mail testuale+HTML. $fromEmailOverride permette di forzare l'email mittente (es. newsletter).
     */
    function tos_mail_send(string $to, string $subject, string $bodyText, string $fromName = 'Tornei Old School', ?string $replyToOverride = null, ?string $fromEmailOverride = null): bool
    {
        $fromEmail = $fromEmailOverride ?: (getenv('MAIL_FROM') ?: 'noreply@torneioldschool.it');
        $replyTo = $replyToOverride ?: (getenv('MAIL_REPLY_TO') ?: 'info@torneioldschool.it');
        $returnPath = getenv('MAIL_RETURN_PATH') ?: $fromEmail;

        // Prepara multipart testo+HTML
        $boundary = 'TOS-' . bin2hex(random_bytes(8));
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "List-Unsubscribe: <mailto:{$replyTo}?subject=Unsubscribe>\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $bodyHtml = nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8'));
        $payload  = "--{$boundary}\r\n";
        $payload .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $payload .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $payload .= $bodyText . "\r\n";
        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Type: text/html; charset=UTF-8\r\n";
        $payload .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $payload .= "<html><body style=\"font-family:Arial,sans-serif;white-space:pre-line;\">{$bodyHtml}</body></html>\r\n";
        $payload .= "--{$boundary}--";

        // Se SMTP è configurato, usa quello
        $smtpHost = getenv('SMTP_HOST') ?: '';
        $smtpUser = getenv('SMTP_USER') ?: '';
        $smtpPass = getenv('SMTP_PASS') ?: '';
        $smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
        $smtpSecure = strtolower(getenv('SMTP_SECURE') ?: 'tls'); // tls|ssl|none

        if ($smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '') {
            return tos_smtp_send($smtpHost, $smtpPort, $smtpSecure, $smtpUser, $smtpPass, $fromEmail, $to, $subject, $headers, $payload, $returnPath);
        }

        // Fallback a mail() locale
        $params = $returnPath ? "-f{$returnPath}" : null;
        return $params ? mail($to, $subject, $payload, $headers, $params) : mail($to, $subject, $payload, $headers);
    }
}

// SMTP minimale via socket
if (!function_exists('tos_smtp_send')) {
    function tos_smtp_send(string $host, int $port, string $secure, string $user, string $pass, string $fromEmail, string $to, string $subject, string $headers, string $body, string $returnPath): bool
    {
        $protocolHost = ($secure === 'ssl') ? 'ssl://' . $host : $host;
        $fp = @stream_socket_client($protocolHost . ':' . $port, $errno, $errstr, 10);
        if (!$fp) {
            error_log("SMTP connect failed: {$errstr}");
            return false;
        }

        $read = function () use ($fp) {
            $data = '';
            while ($line = fgets($fp, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = function (string $command, string $expectCode) use ($fp, $read) {
            fwrite($fp, $command . "\r\n");
            $resp = $read();
            return substr($resp, 0, 3) === $expectCode;
        };

        $read(); // banner
        if (!$cmd('EHLO ' . ($host ?: 'localhost'), '250')) { fclose($fp); return false; }

        if ($secure === 'tls') {
            if (!$cmd('STARTTLS', '220')) { fclose($fp); return false; }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
            if (!$cmd('EHLO ' . ($host ?: 'localhost'), '250')) { fclose($fp); return false; }
        }

        if (!$cmd('AUTH LOGIN', '334')) { fclose($fp); return false; }
        if (!$cmd(base64_encode($user), '334')) { fclose($fp); return false; }
        if (!$cmd(base64_encode($pass), '235')) { fclose($fp); return false; }

        $envFrom = $returnPath ?: $fromEmail;
        if (!$cmd('MAIL FROM: <' . $envFrom . '>', '250')) { fclose($fp); return false; }
        if (!$cmd('RCPT TO: <' . $to . '>', '250')) { fclose($fp); return false; }
        if (!$cmd('DATA', '354')) { fclose($fp); return false; }

        $fullHeaders  = "Subject: {$subject}\r\n";
        $fullHeaders .= $headers;
        $fullHeaders .= "To: <{$to}>\r\n";
        $fullHeaders .= "Date: " . date('r') . "\r\n";

        $payload = $fullHeaders . "\r\n" . $body . "\r\n.";
        fwrite($fp, $payload . "\r\n");
        $dataResp = $read();
        $ok = substr($dataResp, 0, 3) === '250';
        $cmd('QUIT', '221');
        fclose($fp);
        return $ok;
    }
}

if (!function_exists('inviaEmailVerifica')) {
    function inviaEmailVerifica($email, $nome, $token) {
        $link = build_absolute_url('/verify_email.php?token=' . urlencode($token) . '&email=' . urlencode($email));

        $subject = "Conferma la tua registrazione - Tornei Old School";
        $message = "Ciao {$nome},\n\n"
            . "Grazie per esserti registrato su Tornei Old School.\n"
            . "Per completare la registrazione, conferma il tuo indirizzo email cliccando sul link seguente:\n\n"
            . "{$link}\n\n"
            . "Il link scadra' tra 24 ore.\n\n"
            . "Se non hai richiesto questa registrazione, ignora questa email.\n\n"
            . "A presto,\nIl team Tornei Old School";

        return tos_mail_send($email, $subject, $message);
    }
}

if (!function_exists('inviaEmailResetPassword')) {
    function inviaEmailResetPassword(string $email, string $nome, string $token): bool
    {
        $link = build_absolute_url('/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email));
        $subject = "Reimposta la tua password - Tornei Old School";
        $body = "Ciao {$nome},\n\n"
            . "Hai richiesto di reimpostare la password del tuo account su Tornei Old School.\n"
            . "Se non hai effettuato tu la richiesta, puoi ignorare questa email.\n\n"
            . "Per procedere clicca il link qui sotto (scade tra 1 ora):\n{$link}\n\n"
            . "A presto,\nIl team Tornei Old School";

        return tos_mail_send($email, $subject, $body);
    }
}

if (!function_exists('build_absolute_url')) {
    function build_absolute_url(string $path): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $normalizedPath = '/' . ltrim($path, '/');
        return "{$protocol}://{$host}{$normalizedPath}";
    }
}

if (!function_exists('estrai_estratto_testo')) {
    function estrai_estratto_testo(string $htmlOrText, int $maxLen = 240): string {
        $plain = strip_tags($htmlOrText);
        // rimuove marcatori tipo ==testo== lasciando solo il contenuto
        $plain = preg_replace('/==(.+?)==/u', '$1', $plain);
        $plain = preg_replace('/\s+/', ' ', $plain);
        $plain = trim($plain);
        if (mb_strlen($plain) > $maxLen) {
            $plain = mb_substr($plain, 0, $maxLen - 3) . '...';
        }
        return $plain;
    }
}

if (!function_exists('destinatari_newsletter')) {
    function destinatari_newsletter(mysqli $conn): array {
        $lista = [];
        $sql = "SELECT u.email, u.nome FROM consensi_utenti c INNER JOIN utenti u ON u.id = c.user_id WHERE c.newsletter = 1 AND u.email_verificata = 1";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['email'])) {
                    $lista[] = ['email' => $row['email'], 'nome' => $row['nome'] ?? ''];
                }
            }
            $res->close();
        }
        return $lista;
    }
}

if (!function_exists('log_newsletter_send')) {
    function log_newsletter_send(mysqli $conn, int $postId, string $email, string $status, ?string $error = null): void {
        $stmt = $conn->prepare("INSERT INTO newsletter_log (post_id, email, status, error) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('isss', $postId, $email, $status, $error);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('invia_notifica_articolo')) {
    function invia_notifica_articolo(mysqli $conn, int $postId, string $titolo, string $contenuto): array {
        $destinatari = destinatari_newsletter($conn);
        if (empty($destinatari)) {
            return ['inviate' => 0, 'totali' => 0];
        }

        $linkTitle = trim($titolo);
        $linkPath = $linkTitle !== '' ? '/articolo.php?titolo=' . rawurlencode($linkTitle) : '/articolo.php?id=' . $postId;
        $link = build_absolute_url($linkPath);
        $excerpt = estrai_estratto_testo($contenuto);

        $subject = "Nuovo articolo: {$titolo}";
        $sent = 0;
        foreach ($destinatari as $dest) {
            $body = "Ciao " . ($dest['nome'] ?: 'giocatore') . ",\n\n";
            $body .= "Abbiamo pubblicato un nuovo articolo:\n";
            $body .= $titolo . "\n\n";
            if ($excerpt !== '') {
                $body .= $excerpt . "\n\n";
            }
            $body .= "Leggi qui: {$link}\n\n";
            $body .= "Ricevi questa email perche' hai dato il consenso alla newsletter su Tornei Old School.\n";
            $body .= "Puoi revocare il consenso dalla pagina account o dal link \"Gestisci preferenze\" nel sito.\n";

            $ok = tos_mail_send($dest['email'], $subject, $body, 'Tornei Old School', null, 'newsletter@torneioldschool.it');
            if ($ok) {
                $sent++;
                log_newsletter_send($conn, $postId, $dest['email'], 'sent', null);
            } else {
                log_newsletter_send($conn, $postId, $dest['email'], 'failed', 'mail() failed');
            }
        }

        return ['inviate' => $sent, 'totali' => count($destinatari)];
    }
}
